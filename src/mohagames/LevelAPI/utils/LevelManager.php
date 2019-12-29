<?php

namespace mohagames\LevelAPI\utils;

use mohagames\LevelAPI\Main;
use pocketmine\Player;

class LevelManager
{

    public $main;
    public $db;
    public const FLAG_SKIP_CALCULATE = "skip_calculate";
    public const FLAG_SET_COMMAND = "set_command";
    public static $instance;

    public function __construct()
    {
        $this->main = Main::getInstance();
        $this->db = $this->main->db;
        LevelManager::$instance = $this;
    }

    public function userExists(?string $player): bool
    {
        if ($player == null) {
            return false;
        }
        $player = strtolower($player);
        $stmt = $this->db->prepare("SELECT * FROM users WHERE lower(player_name) = :player_name");
        $stmt->bindParam("player_name", $player, SQLITE3_TEXT);
        $res = $stmt->execute();

        $count = 0;
        while ($row = $res->fetchArray()) {
            $count++;
        }

        return $count > 0;
    }

    public function getUsers()
    {
        $res = $this->db->query("SELECT player_name FROM users");
        $users = null;
        while ($row = $res->fetchArray()) {
            $users[] = $row["player_name"];
        }
        return $users;
    }

    public function initUser(string $player)
    {
        $default = 0;
        $stmt = $this->db->prepare("INSERT INTO users (player_name, player_xp, player_level) values(:player_name, :player_xp, :player_level)");
        $stmt->bindParam("player_name", $player, SQLITE3_TEXT);
        $stmt->bindParam("player_xp", $default, SQLITE3_INTEGER);
        $stmt->bindParam("player_level", $default, SQLITE3_INTEGER);
        $stmt->execute();
        Main::getInstance()->getLogger()->info("De user $player is succesvol aangemaakt.");
    }


    public function updateLevels(string $player)
    {
        $xp = $this->getXp($player);
        if ($xp !== null) {
            $calculated_level = $this->calculateLevels($xp);
            $this->setLevel($player, $calculated_level);
        }
    }

    public function calculateLevels(int $xp)
    {
        if ($xp >= 0) {
            $xp_values = $this->getXpValues();
            foreach ($xp_values as $i => $xp_value) {
                if ($xp == $xp_value) {
                    $level = $i;
                    break;
                } elseif ($xp_value > $xp) {
                    $level = $i - 1;
                    break;
                }
            }
        } else {
            $level = 0;
        }

        return $level;
    }

    public function calculateXp(string $level)
    {
        $xp_values = $this->getXpValues();
        foreach ($xp_values as $i => $xp_value) {
            if ($level == $i) {
                return $xp_value;
            }
        }
        return 0;
    }

    public function getXpValues()
    {
        $max = Main::getInstance()->getConfig()->get("max_level");
        $val = $this->main->getConfig()->get("level-multiplier");
        $index = 0;
        while ($index < $max) {
            $xpValues[] = ($index * ($val + ($val * $index)) / 2);
            $index++;
        }
        return $xpValues;
    }

    public function getLevel(string $player): ?int
    {
        $player = strtolower($player);
        $stmt = $this->db->prepare("SELECT player_level FROM users WHERE lower(player_name) = :player_name");
        $stmt->bindParam("player_name", $player, SQLITE3_TEXT);
        $res = $stmt->execute();

        $player_level = null;

        while ($row = $res->fetchArray()) {
            $player_level = $row["player_level"];
        }
        return $player_level;
    }

    public function getXp(string $player): ?int
    {
        $player = strtolower($player);
        $stmt = $this->db->prepare("SELECT player_xp FROM users WHERE lower(player_name) = :player_name");
        $stmt->bindParam("player_name", $player, SQLITE3_TEXT);
        $res = $stmt->execute();

        $player_xp = null;

        while ($row = $res->fetchArray()) {
            $player_xp = $row["player_xp"];
        }
        return $player_xp;
    }

    public function setLevel(string $player, int $level, $flags = null)
    {
        if ($this->userExists($player)) {
            if ($level >= 0) {
                $player = strtolower($player);
                $stmt = $this->db->prepare("UPDATE users SET player_level = :player_level WHERE lower(player_name) = :player_name");
                $stmt->bindParam("player_level", $level, SQLITE3_INTEGER);
                $stmt->bindParam("player_name", $player, SQLITE3_TEXT);
                $stmt->execute();

                if ($flags == self::FLAG_SET_COMMAND) {
                    $xp_c_level = $this->calculateXp($this->getLevel($player));
                    $this->setXp($player, $xp_c_level, LevelManager::FLAG_SKIP_CALCULATE);
                }

                $potential_player = $this->main->getServer()->getPlayer($player);
                if ($potential_player instanceof Player) {
                    $pc = $this->main->getServer()->getPluginManager()->getPlugin("PureChat");
                    if ($pc !== null) {
                        $pc->setSuffix($this->getLevel($potential_player->getName()), $potential_player);
                    }
                }
            }
        }
    }

    public function setXp(string $player, int $xp, $flags = null)
    {
        if ($this->userExists($player)) {
            $player = strtolower($player);
            $stmt = $this->db->prepare("UPDATE users SET player_xp = :player_xp WHERE lower(player_name) = :player_name");
            $stmt->bindParam("player_xp", $xp, SQLITE3_INTEGER);
            $stmt->bindParam("player_name", $player, SQLITE3_TEXT);
            $stmt->execute();

            if ($flags != LevelManager::FLAG_SKIP_CALCULATE) {
                $this->updateLevels($player);
            }
        }
    }

    public function addLevel(string $player, int $level)
    {
        $level += $this->getLevel($player);
        $this->setLevel($player, $level);
    }

    public function addXp(string $player, int $xp, $flags = null)
    {
        $xp += $this->getXp($player);
        $this->setXp($player, $xp, $flags);
    }

    public function removeXp(string $player, int $xp, $flags = null)
    {
        $this->setXp($player, $this->getXp($player) - $xp, $flags);
    }

    public function removeLevel(string $player, int $level)
    {
        $new_level = $this->getLevel($player) - $level;
        $this->setLevel($player, $new_level);
    }

    public static function getManager(): LevelManager
    {
        return LevelManager::$instance;
    }


}