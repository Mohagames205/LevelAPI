<?php

namespace mohagames\LevelAPI;

use mohagames\LevelAPI\utils\LevelManager;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener
{

    public $db;
    public $lvl_mngr;
    public static $instance;

    public function onEnable()
    {
        Main::$instance = $this;
        $config = new Config($this->getDataFolder() . "config.yml", CONFIG::YAML, array("level-multiplier" => 500, "max_level" => 100));
        $this->db = new \SQLite3($this->getDataFolder() . "LevelAPI.db");
        $this->db->query("CREATE TABLE IF NOT EXISTS users(user_id INTEGER PRIMARY KEY AUTOINCREMENT,player_name TEXT,player_xp INTEGER, player_level INTEGER)");
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->lvl_mngr = new LevelManager();


    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        switch ($command->getName()) {
            case "setlevel":
                if (isset($args[0]) && isset($args[1])) {
                    if (is_numeric($args[1])) {
                        $this->lvl_mngr->setLevel($args[0], $args[1], LevelManager::FLAG_SET_COMMAND);
                    }
                }
                return true;

            case "setxp":
                if (isset($args[0]) && isset($args[1])) {
                    if (is_numeric($args[1])) {
                        $this->lvl_mngr->setXp($args[0], $args[1]);
                    }
                }
                return true;

            case "mylevel":
                $level = $this->lvl_mngr->getLevel($sender->getName());
                $xp = $this->lvl_mngr->getXp($sender->getName());
                $sender->sendMessage("§aLevel: §2$level\n§aXP: §2$xp");
                return true;

            case "addlevel":
                if (isset($args[0]) && isset($args[1])) {
                    if (is_numeric($args[1])) {
                        $this->lvl_mngr->addLevel($args[0], $args[1]);
                    }
                }
                return true;

            case "addxp":
                if (isset($args[0]) && isset($args[1])) {
                    if (is_numeric($args[1])) {
                        $this->lvl_mngr->addXp($args[0], $args[1]);
                    }
                }

                return true;

            case "seelevel":
                if (isset($args[0])) {
                    $name = $sender->getName();
                    $level = $this->lvl_mngr->getLevel($args[0]);
                    $xp = $this->lvl_mngr->getXp($args[0]);
                    $sender->sendMessage("§aLevel van §2$name:\n§2Level: §a$level\n§2XP: §a$xp");
                }

                return true;

            default:
                return false;
        }
    }

    public function onJoin(PlayerJoinEvent $e)
    {
        $pc = $this->getServer()->getPluginManager()->getPlugin("PureChat");
        $player = $e->getPlayer();
        $this->getLogger()->info("Checking if user exists");
        if (!LevelManager::getManager()->userExists($player->getName())) {
            $this->getLogger()->info("User does not exist. Initialising account...");
            LevelManager::getManager()->initUser($player->getName());
            if ($pc !== null) {
                $pc->setSuffix(0, $player);
            }
        } else {
            $pc->setSuffix(LevelManager::getManager()->getLevel($player->getName()), $player);
        }
    }

    public static function getInstance(): Main
    {
        return Main::$instance;
    }


}