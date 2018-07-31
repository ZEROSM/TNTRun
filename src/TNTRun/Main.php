<?php

namespace TNTRun;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\item\Item;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\block\Block;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\level\Position;
use pocketmine\tile\Sign;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBreakEvent;

class Main extends PluginBase implements Listener {

    public $mode = 0;
    public $signregister = false;
    public $levelname = "";

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        @mkdir($this->getDataFolder());
        @mkdir($this->getDataFolder() . "/maps");
        $this->saveDefaultConfig();
        $this->reloadConfig();
        $this->getLogger()->info("§aTNTRun by §6CraftYourBukkit §aloaded.");
        $this->prefix = $this->getConfig()->get("prefix");
        $this->getScheduler()->scheduleRepeatingTask(new GameSender($this), 20);
        $this->getScheduler()->scheduleRepeatingTask(new RefreshSigns($this), 20);
        foreach ($this->getConfig()->getNested("arenas") as $a) {
            if (!$this->getServer()->getLevelByName($a["name"]) instanceof Level) {
                $this->deleteDirectory($this->getServer()->getDataPath() . "/worlds/" . $a["name"]);
                $this->copymap($this->getDataFolder() . "/maps/" . $a["name"], $this->getServer()->getDataPath() . "/worlds/" . $a["name"]);
                $this->getServer()->loadLevel($a["name"]);
            } else {
                $this->getServer()->unloadLevel($this->getServer()->getLevelByName($a["name"]));
                $this->deleteDirectory($this->getServer()->getDataPath() . "/worlds/" . $a["name"]);
                $this->copymap($this->getDataFolder() . "/maps/" . $a["name"], $this->getServer()->getDataPath() . "/worlds/" . $a["name"]);
                $this->getServer()->loadLevel($a["name"]);
            }
        }
    }

    public function onDamage(EntityDamageEvent $event) {
        $player = $event->getEntity();
        if ($player instanceof Player) {
            if ($this->getConfig()->getNested("arenas." . $player->getLevel()->getFolderName() . ".name") === $player->getLevel()->getFolderName()) {
                $event->setCancelled(true);
            }
        }
    }

    public function onQuit(PlayerQuitEvent $event) {
        $player = $event->getPlayer();
        if ($this->getConfig()->getNested("arenas." . $player->getLevel()->getFolderName() . ".name") === $player->getLevel()->getFolderName()) {
            foreach ($player->getLevel()->getPlayers() as $pl) {
                $pl->sendMessage("§f<§eTNT Run§f> 플레이어 §e" . $player->getName() . "§f님이 §aTNT Run§f 미니게임에서 나가셨습니다.");
            }
            $players = $this->getConfig()->getNested("arenas." . $player->getLevel()->getFolderName() . ".players");
            $current = 0;
            foreach ($players as $name) {
                if ($name === strtolower($player->getName())) {
                    unset($players[$current]);
                }
                $current++;
            }
            $this->getConfig()->setNested("arenas." . $player->getLevel()->getFolderName() . ".players", $players);
            $this->getConfig()->save();
            $spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
            $this->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
            $player->teleport($spawn, 0, 0);
        }
    }

    public function onJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        $this->getScheduler()->scheduleDelayedTask(new sendBack($this, $player), 2);
        $spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
        $player->setSpawn(new Position($spawn->getX(), $spawn->getY(), $spawn->getZ(), $this->getServer()->getDefaultLevel()));
    }

    public function onRespawn(PlayerRespawnEvent $event) {
        $player = $event->getPlayer();
        $spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
        $player->setSpawn(new Position($spawn->getX(), $spawn->getY(), $spawn->getZ(), $this->getServer()->getDefaultLevel()));
    }

    public function onMove(PlayerMoveEvent $event) {
        $player = $event->getPlayer();
        if ($this->getConfig()->getNested("arenas." . $player->getLevel()->getFolderName() . ".name") === $player->getLevel()->getFolderName()) {
            if ($player->getY() <= 1 && count($player->getLevel()->getPlayers()) > 0) {
                foreach ($player->getLevel()->getPlayers() as $pl) {
                    $pl->sendMessage("§f<§eTNT Run§f> 플레이어 §e" . $player->getName() . "§f님이 사망하였습니다.");
                }
                $players = $this->getConfig()->getNested("arenas." . $player->getLevel()->getFolderName() . ".players");
                $current = 0;
                foreach ($players as $name) {
                    if ($name === strtolower($player->getName())) {
                        unset($players[$current]);
                    }
                    $current++;
                }
                $this->getConfig()->setNested("arenas." . $player->getLevel()->getFolderName() . ".players", $players);
                $this->getConfig()->save();
                $bug_fix = 0;
                $bug_fix2 = 0;
                foreach ($player->getLevel()->getPlayers() as $pl) {
                    if ($pl->getY() <= 1)
                        $bug_fix++;
                }
                if ($bug_fix === count($player->getLevel()->getPlayers())) {
                    foreach ($player->getLevel()->getPlayers() as $pl) {
                        if ($bug_fix2 != $bug_fix) {
                            $spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
                            $this->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
                            $pl->teleport($spawn, 0, 0);
                            $bug_fix2++;
                        } else {
                            $spawn = $pl->getLevel()->getSafeSpawn();
                            $this->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
                            $pl->teleport($spawn, 0, 0);
                        }
                    }
                } else {
                    $spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
                    $this->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
                    $player->teleport($spawn, 0, 0);
                }
            }
            if (($this->getConfig()->getNested("arenas." . $player->getLevel()->getFolderName() . ".time")) <= ($this->getConfig()->get("time") - 10)) {
                if ($this->getConfig()->getNested("arenas." . $player->getLevel()->getFolderName() . ".start") === 0) {
                    $block = $player->getLevel()->getBlock($player->floor()->subtract(0, 1));
                    $player->getLevel()->setBlock($block, Block::get(Block::AIR));
                }
            }
        }
    }

    public function onCommand(\pocketmine\command\CommandSender $sender, \pocketmine\command\Command $cmd, $label, array $args) : bool{
        switch ($cmd->getName()) {
            case "tr":
                if (!$sender instanceof Player) {
                    $sender->sendMessage(TextFormat::RED . "You have to be a Player to perform this command!");
                    return true;
                }
                if (!isset($args[0])) {
                    $sender->sendMessage(TextFormat::RED . "사용법: " . $cmd->getUsage());
                    return true;
                }
                if (strtolower($args[0]) === "addarena") { // /tr addarena <name> <maxPlayers> <minPlayers>
                    if (!(isset($args[1])) || !(isset($args[2])) || !(isset($args[3]))) {
                        $sender->sendMessage("사용법: /tr addarena <월드 이름> <최대 플레이어> <최소 플레이어>");
                        return true;
                    }
                    if (file_exists($this->getServer()->getDataPath() . "/worlds/" . $args[1])) {
                        $this->mode = 1;
                        $min = $args[3];
                        $this->getServer()->loadLevel($args[1]);
                        $this->getServer()->getLevelByName($args[1])->loadChunk($this->getServer()->getLevelByName($args[1])->getSafeSpawn()->getX(), $this->getServer()->getLevelByName($args[1])->getSafeSpawn()->getZ());
						$sender->sendMessage("§f<§eTNT Run§f> 해당 미니게임의 스폰장소를 터치해주세요!");
                        $level = $this->getServer()->getLevelByName($args[1]);
                        $sender->teleport($level->getSafeSpawn());
                        $this->getConfig()->setNested("arenas." . $args[1] . ".name", $args[1]);
                        $this->getConfig()->setNested("arenas." . $args[1] . ".time", $this->getConfig()->get("time"));
                        $this->getConfig()->setNested("arenas." . $args[1] . ".start", 60);
                        $this->getConfig()->setNested("arenas." . $args[1] . ".players", array());
                        $this->getConfig()->setNested("arenas." . $args[1] . ".maxPlayers", (int) $args[2]);
                        $this->getConfig()->setNested("arenas." . $args[1] . ".minPlayers", (int) $min);
                        $this->getConfig()->save();
                        return true;
                    } else {
                        $sender->sendMessage("§f<§eTNT Run§f> $args[1] 월드는 존재하지 않는 월드 입니다!");
                        return true;
                    }
                } elseif (strtolower($args[0]) === "regsign") { // /tr regsign <name>
                    if (!isset($args[1])) {
                        $sender->sendMessage(TextFormat::RED . "사용법: /tr regsign <월드 이름>");
                        return true;
                    }
                    if (file_exists($this->getServer()->getDataPath() . "/worlds/" . $args[1])) {
                        $this->signregister = true;
                        $this->levelname = $args[1];
                        $sender->sendMessage("§f<§eTNT Run§f> 미니게임 입장 표지판을 터치해주세요!");
                        return true;
                    } else {
                        $sender->sendMessage("§f<§eTNT Run§f> $args[1] 월드는 존재하지 않는 월드 입니다!");
                        return true;
                    }
                }
                return true;
        }
    }

    public function onInteract(PlayerInteractEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $sign = $player->getLevel()->getTile($block);
        if ($sign instanceof Sign) {
            if ($this->signregister === false) {
                $text = $sign->getText();
                if ($text[0] === $this->prefix) {
                    $level = TextFormat::clean($text[1]);
                    $this->getServer()->loadLevel($level);
                    $lvl = $this->getServer()->getLevelByName($level);
                    if ($text[2] != "§c현재 게임 진행중") {
                        if (count($lvl->getPlayers()) != (int) $this->getConfig()->getNested("arenas." . $level . ".maxPlayers")) {
                            $array = $this->getConfig()->getNested("arenas." . $level . ".players");
                            array_push($array, strtolower($player->getName()));
                            $this->getConfig()->setNested("arenas." . $level . ".players", $array);
                            $this->getConfig()->save();
                            $spawn = $lvl->getSafeSpawn();
                            $player->teleport(new Position($spawn->getX(), $spawn->getY(), $spawn->getZ(), $lvl));
                            $s = $this->getServer()->getDefaultLevel()->getSafeSpawn();
                            $player->setSpawn(new Position($s->getX(), $s->getY(), $s->getZ(), $this->getServer()->getDefaultLevel()));
                            foreach ($lvl->getPlayers() as $pl) {
                                $pl->sendMessage("§f<§eTNT Run§f> 플레이어 §e" . $player->getName() . "§f님이 §aTNT Run§f 미니게임에 참여하였습니다.");
                            }
                        } else {
                            $player->addTitle("§l§eTNT Run", "§7해당 게임의 최대인원이 꽉 찼습니다!");
                        }
                    } else {
                        $player->addTitle("§l§eTNT Run", "§7해당 게임은 이미 시작했습니다!");
                    }
                }
            } else {
                $sign->setText($this->prefix, $this->levelname, "§f참여인원: §e0/" . $this->getConfig()->getNested("arenas." . $this->levelname . ".maxPlayers"));
                $this->signregister = false;
                $this->levelname = "";
                $player->sendMessage(TextFormat::GRAY . "Sign for $this->levelname set.");
            }
        }
        if ($this->mode != 0) {
            $this->getConfig()->setNested("arenas." . $player->getLevel()->getFolderName() . ".spawn", array("x" => $block->getX(), "y" => $block->getY() + 2, "z" => $block->getZ()));
            $this->getConfig()->save();
            $player->sendMessage("§f<§eTNT Run§f> 성공적으로 미니게임 스폰지점을 설정하였습니다. 다음 명령어를 입력하여 입장 표지판을 설정해주세요! §e/tr regsign <월드이름>");
            $this->copymap($this->getServer()->getDataPath() . "/worlds/" . $player->getLevel()->getFolderName(), $this->getDataFolder() . "/maps/" . $player->getLevel()->getFolderName());
            $player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn(), 0, 0);
            $this->mode = 0;
        }
    }

    public function copymap($src, $dst) {
        $dir = opendir($src);
        @mkdir($dst);
        while (false !== ( $file = readdir($dir))) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if (is_dir($src . '/' . $file)) {
                    $this->copymap($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    public function deleteDirectory($dirPath) {
        if (is_dir($dirPath)) {
            $objects = scandir($dirPath);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dirPath . DIRECTORY_SEPARATOR . $object) == "dir") {
                        $this->deleteDirectory($dirPath . DIRECTORY_SEPARATOR . $object);
                    } else {
                        unlink($dirPath . DIRECTORY_SEPARATOR . $object);
                    }
                }
            }
            reset($objects);
            rmdir($dirPath);
        }
    }

}
