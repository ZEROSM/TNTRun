<?php

namespace TNTRun;

use pocketmine\tile\Sign;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\Task;
use pocketmine\Player;
use pocketmine\level\Level;

class sendPopupAgain extends Task {

    public $prefix;

    public function __construct($plugin, $text, Player $player) {
        $this->plugin = $plugin;
        $this->text = $text;
        $this->player = $player;
        $this->prefix = $this->plugin->getConfig()->get("prefix");
    }

    public function onRun($tick) {
        $this->player->sendPopup($this->text);
    }

}
