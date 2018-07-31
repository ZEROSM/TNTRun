<?php

namespace TNTRun;

use pocketmine\scheduler\Task;
use pocketmine\Player;
use pocketmine\level\Position;
use pocketmine\utils\TextFormat;
use pocketmine\level\Level;
use pocketmine\tile\Chest;
use pocketmine\math\Vector3;
use pocketmine\item\Item;
use pocketmine\tile\Sign;

class GameSender extends Task {

    public $prefix;

    public function __construct($plugin) {
        $this->plugin = $plugin;
        $this->prefix = $this->plugin->getConfig()->get("prefix");
    }

    public function onRun($tick) {
        $config = $this->plugin->getConfig();
        $arenas = $config->get("arenas");
        if (count($arenas) > 0) {
            foreach ($arenas as $arena) {
                $name = $arena["name"];
                $time = $arena["time"];
                $timeToStart = $config->getNested("arenas." . $name . ".start");
                $minPlayers = $arena["minPlayers"];
                $ingameplayers = $arena["players"];
                $levelArena = $this->plugin->getServer()->getLevelByName($name);
                if ($levelArena instanceof Level) {
                    $playersArena = $this->plugin->getServer()->getLevelByName($name)->getPlayers();
                    $onlineplayers = $this->plugin->getServer()->getOnlinePlayers();
                    if (count($playersArena) === 0) {
                        $config->setNested("arenas." . $name . ".time", $config->get("time"));
                        $config->setNested("arenas." . $name . ".players", array());
                        $config->setNested("arenas." . $name . ".start", 60);
                        $config->save();
                    } elseif (count($playersArena) < $minPlayers && $timeToStart > 0) {
                        foreach ($playersArena as $pl) {
                            $pl->sendPopup("§l§7<< §f플레이어를 기다리고 있습니다... §l§7>>");
                            $this->plugin->getScheduler()->scheduleDelayedTask(new sendPopupAgain($this->plugin, "§l§7<< §f플레이어를 기다리고 있습니다... §l§7>>", $pl), 10);
                        }
                        $config->setNested("arenas." . $name . ".time", $config->get("time"));
                        $config->setNested("arenas." . $name . ".start", 60);
                        $config->save();
                    } else {
                        if ((count($playersArena) === 1) && ($timeToStart === 0)) {
                            foreach ($playersArena as $pl) {
                                $level = $this->plugin->getServer()->getDefaultLevel();
                                $tiles = $level->getTiles();
                                foreach ($tiles as $t) {
                                    if ($t instanceof Sign) {
                                        $text = $t->getText();
                                        if (TextFormat::clean($text[1]) == $pl->getLevel()->getFolderName()) {
                                            $ingame = "§a미니게임 입장 가능";
                                            $t->setText($this->prefix, $text[1], $ingame, "§f참여인원: §e0/" . $config->getNested("arenas." . $name . ".maxPlayers"));
                                        }
                                    }
                                }
                                $pl->addTitle("§l§eTNT Run", "§7미니게임에서 승리하셨습니다.");
								$pl->getServer()->broadcastMessage("§7----------------");
								$pl->getServer()->broadcastMessage("§f<§eTNT Run§f> §aTNT Run§f 미니게임이 종료 되었습니다.");
								$pl->getServer()->broadcastMessage("§f<§eTNT Run§f> 이번 경기 우승자는 §e".$pl->getPlayer()->getName()."§f님 입니다. 입장 표지판을 터치해서 입장해주세요!");
								$pl->getServer()->broadcastMessage("§7----------------");
                                $spawn = $this->plugin->getServer()->getDefaultLevel()->getSafeSpawn();
                                $this->plugin->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
                                if ($pl->isOnline()) {
                                    $pl->teleport($spawn, 0, 0);
                                    
                                }

                                $this->plugin->getServer()->unloadLevel($levelArena);
                                $this->deleteDirectory($this->plugin->getServer()->getDataPath() . "/worlds/" . $name);
                                $this->copymap($this->plugin->getDataFolder() . "/maps/" . $name, $this->plugin->getServer()->getDataPath() . "/worlds/" . $name);
                                $this->plugin->getServer()->loadLevel($name);
                            }
                            foreach ($levelArena->getEntities() as $entity) {
                                if (!$entity instanceof Player) {
                                    $entity->despawnFromAll();
                                }
                            }
                            $config->setNested("arenas." . $name . ".time", $time);
                            $config->save();
                        }
                        if ((count($playersArena) > 1) && ($timeToStart === 0)) {
                            foreach ($playersArena as $pl) {
                                $pl->sendPopup("§l§7<< §e" . count($playersArena) . "§f명의 플레이어가 남았습니다. §l§7>>");
                                $this->plugin->getScheduler()->scheduleDelayedTask(new sendPopupAgain($this->plugin, "§l§7<< §e" . count($playersArena) . "§f명의 플레이어가 남았습니다. §l§7>>", $pl), 10);
                            }
                        }
                        if (count($playersArena) >= $minPlayers) {
                            if ($timeToStart > 0) {
                                $timeToStart--;
                                $config->setNested("arenas." . $name . ".start", $timeToStart);
                                if ($timeToStart <= 0) {
                                    if ((count($playersArena) != 0) || (count($playersArena) != 1)) {
                                        sort($ingameplayers, SORT_NATURAL | SORT_FLAG_CASE);
                                        foreach ($ingameplayers as $key => $igp) {
                                            $p = $this->plugin->getServer()->getPlayer($igp);
                                            $spawns = $this->plugin->getConfig()->getNested("arenas." . $levelArena->getFolderName() . ".spawn");
                                            $x = $spawns["x"];
                                            $y = $spawns["y"];
                                            $z = $spawns["z"];
                                            $p->teleport(new Vector3($x, $y, $z));
                                            $p->addTitle("§l§eTNT Run", "§7게임이 시작되었습니다!");
                                        }
                                        $config->setNested("arenas." . $name . ".time", $config->get("time"));
                                        $config->save();
                                        $levelArena->setTime(0);
                                    } else {
                                        $timeToStart = 30;
                                        foreach ($playersArena as $pl) {
                                            $pl->sendMessage("§f<§eTNT Run§f> 타이머를 리셋했습니다. 혼자서는 플레이 할수 없습니다!");
                                        }
                                    }
                                }
                                foreach ($playersArena as $pl) {
									$pl->addTitle("§e".$timeToStart."§f초뒤에 게임 시작");
                                    $this->plugin->getScheduler()->scheduleDelayedTask(new sendTitleAgain($this->plugin, "§e".$timeToStart."§f초뒤에 게임 시작", $pl), 5);
                                }
                                $config->setNested("arenas." . $name . ".time", $config->get("time"));
                                $config->save();
                            } elseif ((count($playersArena) > 1) && ($timeToStart === 0)) {
                                foreach ($playersArena as $pl) {
                                    $pl->sendPopup("§l§7<< §e" . count($playersArena) . "§f명의 플레이어가 남았습니다. §l§7>>");
                                    $this->plugin->getScheduler()->scheduleDelayedTask(new sendPopupAgain($this->plugin, "§l§7<< §e" . count($playersArena) . "§f명의 플레이어가 남았습니다. §l§7>>", $pl), 10);
                                }
                            }
                            $time--;
                            $minutes = $time / 60;
                            if (is_int($minutes) && $minutes != 0 && $minutes < 0) {
                                foreach ($playersArena as $pl) {
									$pl->sendMessage("§f<§eTNT Run§f> ". $minutes . " " . ($minutes > 1 ? "Minutes" : "Minute") ."초 남았습니다..");
                                }
                                $levelArena->setTime(0);
                            } else if ($time == 30 || $time == 15 || $time == 10 || $time == 5 || $time == 4 || $time == 3 || $time == 2) {
                                foreach ($playersArena as $pl) {
									$pl->sendMessage("§f<§eTNT Run§f> ". $time ."초 남았습니다..");
                                }
                            } else if ($time == 1) {
                                foreach ($playersArena as $pl) {
                                    $pl->sendMessage("§f<§eTNT Run§f> ". $time ."초 남았습니다..");
                                }
                            } else if ($time <= 0) {
                                foreach ($playersArena as $pl) {
                                    $level = $this->plugin->getServer()->getDefaultLevel();
                                    $tiles = $level->getTiles();
                                    foreach ($tiles as $t) {
                                        if ($t instanceof Sign) {
                                            $text = $t->getText();
                                            if (TextFormat::clean($text[1]) == $pl->getLevel()->getName()) {
                                                $ingame = "§a미니게임 입장 가능";
                                                $t->setText($this->prefix, $text[1], $ingame, "§f참여인원: §e0/" . $config->getNested("arenas." . $name . ".maxPlayers"));
                                            }
                                        }
                                    }
                                    $pl->addTitle("§l§eTNT Run", "§7이번 게임에서 승자가 나오지 않았습니다!");
									$pl->getServer()->broadcastMessage("§7----------------");
									$pl->getServer()->broadcastMessage("§f<§eTNT Run§f> §aTNT Run§f 미니게임이 종료 되었습니다.");
									$pl->getServer()->broadcastMessage("§f<§eTNT Run§f> 이번 경기는 우승자가 나오지 않았습니다. 입장 표지판을 터치해서 입장해주세요!");
									$pl->getServer()->broadcastMessage("§7----------------");
                                    $spawn = $this->plugin->getServer()->getDefaultLevel()->getSafeSpawn();
                                    $this->plugin->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());

                                    if ($pl->isOnline()) {
                                        $pl->teleport($spawn, 0, 0);
                                        
                                    }
                                }
                                $this->plugin->getServer()->unloadLevel($levelArena);
                                $this->deleteDirectory($this->plugin->getServer()->getDataPath() . "/worlds/" . $name);
                                $this->copymap($this->plugin->getDataFolder() . "/maps/" . $name, $this->plugin->getServer()->getDataPath() . "/worlds/" . $name);
                                $this->plugin->getServer()->loadLevel($levelArena);
                                foreach ($levelArena->getEntities() as $entity) {
                                    if (!$entity instanceof Player) {
                                        $entity->despawnFromAll();
                                    }
                                }
                            }
                            $config->setNested("arenas." . $name . ".time", $time);
                            $config->save();
                        } else {
                            if ($config->getNested("arenas." . $name . ".start") != 60) {
                                if ($timeToStart > 0) {
                                    $timeToStart--;
                                    $config->setNested("arenas." . $name . ".start", $timeToStart);
                                    if ($timeToStart <= 0) {
                                        if ((count($playersArena) != 0) || (count($playersArena) != 1)) {
                                            sort($ingameplayers, SORT_NATURAL | SORT_FLAG_CASE);
                                            foreach ($ingameplayers as $key => $igp) {
                                                $p = $this->plugin->getServer()->getPlayer($igp);
                                                $spawns = $this->plugin->getConfig()->getNested("arenas." . $levelArena->getFolderName() . ".spawn");
                                                $x = $spawns["x"];
                                                $y = $spawns["y"];
                                                $z = $spawns["z"];
                                                $p->teleport(new Vector3($x, $y, $z));
                                                
                                                $p->addTitle("§l§eTNT Run", "§7게임이 시작되었습니다!");
                                            }
                                            $config->setNested("arenas." . $name . ".time", $config->get("time"));
                                            $config->save();
                                            $levelArena->setTime(0);
                                        } else {
                                            $timeToStart = 60;
                                            foreach ($playersArena as $pl) {
                                                $pl->sendMessage("§f<§eTNT Run§f> 타이머를 리셋했습니다. 혼자서는 플레이 할수 없습니다!");
                                            }
                                        }
                                    }
                                    foreach ($playersArena as $pl) {
										$pl->addTitle("§e".$timeToStart."§f초뒤에 게임 시작");
                                    $this->plugin->getScheduler()->scheduleDelayedTask(new sendTitleAgain($this->plugin, "§e".$timeToStart."§f초뒤에 게임 시작", $pl), 5);
									}
									$config->setNested("arenas." . $name . ".time", $config->get("time"));
									$config->save();
								} elseif ((count($playersArena) > 1) && ($timeToStart === 0)) {
									foreach ($playersArena as $pl) {
										$pl->sendPopup("§l§7<< §e" . count($playersArena) . "§f명의 플레이어가 남았습니다. §l§7>>");
										$this->plugin->getScheduler()->scheduleDelayedTask(new sendPopupAgain($this->plugin, "§l§7<< §e" . count($playersArena) . "§f명의 플레이어가 남았습니다. §l§7>>", $pl), 10);
									}
                                }
                                $time--;
                                $minutes = $time / 60;
                                if (is_int($minutes) && $minutes != 0 && $minutes < 0) {
                                    foreach ($playersArena as $pl) {
                                        $pl->sendMessage("§f<§eTNT Run§f> ". $minutes . " " . ($minutes > 1 ? "Minutes" : "Minute") ."초 남았습니다..");
                                    }
                                    $levelArena->setTime(0);
                                } else if ($time == 30 || $time == 15 || $time == 10 || $time == 5 || $time == 4 || $time == 3 || $time == 2) {
                                    foreach ($playersArena as $pl) {
                                        $pl->sendMessage("§f<§eTNT Run§f> ". $time ."초 남았습니다..");
                                    }
                                } else if ($time == 1) {
                                    foreach ($playersArena as $pl) {
                                        $pl->sendMessage("§f<§eTNT Run§f> ". $time ."초 남았습니다..");
                                    }
                                } else if ($time <= 0) {
                                    foreach ($playersArena as $pl) {
                                        $level = $this->plugin->getServer()->getDefaultLevel();
                                        $tiles = $level->getTiles();
                                        foreach ($tiles as $t) {
                                            if ($t instanceof Sign) {
                                                $text = $t->getText();
                                                if (TextFormat::clean($text[1]) == $pl->getLevel()->getName()) {
                                                    $ingame = "§a미니게임 입장 가능";
                                                    $t->setText($this->prefix, $text[1], $ingame, "§f참여인원: §e0/" . $config->getNested("arenas." . $name . ".maxPlayers"));
                                                }
                                            }
                                        }
                                        $pl->addTitle("§l§eTNT Run", "§7이번 게임에서 승자가 나오지 않았습니다!");
										$pl->getServer()->broadcastMessage("§7----------------");
										$pl->getServer()->broadcastMessage("§f<§eTNT Run§f> §aTNT Run§f 미니게임이 종료 되었습니다.");
										$pl->getServer()->broadcastMessage("§f<§eTNT Run§f> 이번 경기는 우승자가 나오지 않았습니다. 입장 표지판을 터치해서 입장해주세요!");
										$pl->getServer()->broadcastMessage("§7----------------");
                                        $spawn = $this->plugin->getServer()->getDefaultLevel()->getSafeSpawn();
                                        $this->plugin->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());

                                        if ($pl->isOnline()) {
                                            $pl->teleport($spawn, 0, 0);
                                            
                                        }
                                    }
                                    $this->plugin->getServer()->unloadLevel($levelArena);
                                    $this->deleteDirectory($this->plugin->getServer()->getDataPath() . "/worlds/" . $name);
                                    $this->copymap($this->plugin->getDataFolder() . "/maps/" . $name, $this->plugin->getServer()->getDataPath() . "/worlds/" . $name);
                                    $this->plugin->getServer()->loadLevel($name);
                                    foreach ($levelArena->getEntities() as $entity) {
                                        if (!$entity instanceof Player) {
                                            $entity->despawnFromAll();
                                        }
                                    }
                                }
                                $config->setNested("arenas." . $name . ".time", $time);
                                $config->save();
                            } else {
                                foreach ($playersArena as $pl) {
                                    $pl->sendPopup("§l§7<< §f플레이어를 기다리고 있습니다... §l§7>>");
                                    $this->plugin->getScheduler()->scheduleDelayedTask(new sendPopupAgain($this->plugin, "§l§7<< §f플레이어를 기다리고 있습니다... §l§7>>", $pl), 10);
                                }
                                $config->setNested("arenas." . $name . ".start", 60);
                                $config->save();
                            }
                        }
                    }
                }
            }
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
