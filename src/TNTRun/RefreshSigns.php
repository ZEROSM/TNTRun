<?php

namespace TNTRun;

use pocketmine\tile\Sign;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\Task;
use pocketmine\level\Level;

class RefreshSigns extends Task {

    public $prefix;

    public function __construct($plugin) {
        $this->plugin = $plugin;
        $this->prefix = $this->plugin->getConfig()->get("prefix");
    }

    public function onRun($tick) {
        $allplayers = $this->plugin->getServer()->getOnlinePlayers();
        $level = $this->plugin->getServer()->getDefaultLevel();
        $tiles = $level->getTiles();
        foreach ($tiles as $t) {
            if ($t instanceof Sign) {
                $text = $t->getText();
                if ($text[0] == $this->prefix) {
                    $aop = 0;
                    foreach ($allplayers as $player) {
                        if ($player->getLevel()->getFolderName() == TextFormat::clean($text[1])) {
                            $aop = $aop + 1;
                        }
                    }
                    $ingame = "§a미니게임 입장 가능";
                    $config = $this->plugin->getConfig();
                    $time = (int) $config->getNested("arenas." . TextFormat::clean($text[1]) . ".start");
                    $maxPlayers = (int) $config->getNested("arenas." . TextFormat::clean($text[1]) . ".maxPlayers");
                    if ($time === 0) {
                        $ingame = "§c현재 게임 진행중";
                    }
                    if ($aop >= $maxPlayers && $time != 0) {
                        $ingame = "§6참여 인원 가득참";
                    }
                    $t->setText($this->prefix, $text[1], $ingame, "§f참여인원: §e". $aop . "/" . $config->getNested("arenas." . TextFormat::clean($text[1]) . ".maxPlayers"));
                }
            }
        }
    }

}
