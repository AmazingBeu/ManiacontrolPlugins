<?php

namespace Beu;

use ManiaControl\Commands\CommandListener;
use ManiaControl\Logger;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Plugins\Plugin;
use ManiaControl\ManiaControl;

/**
 * Plugin Description
 *
 * @author  Beu
 * @version 1.0
 */
class BlacklistManager implements CommandListener, Plugin {
	/*
	 * Constants
	 */
	const PLUGIN_ID			= 200;
	const PLUGIN_VERSION	= 1.0;
	const PLUGIN_NAME		= 'Blacklist Manager';
	const PLUGIN_AUTHOR		= 'Beu';

	const SETTING_BLACKLIST_FILE = 'Blacklist file';

	/**
	 * Private Properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl = null;

	/**
	 * @see \ManiaControl\Plugins\Plugin::prepare()
	 */
	public static function prepare(ManiaControl $maniaControl) {
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::getId()
	 */
	public static function getId() {
		return self::PLUGIN_ID;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::getName()
	 */
	public static function getName() {
		return self::PLUGIN_NAME;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::getVersion()
	 */
	public static function getVersion() {
		return self::PLUGIN_VERSION;
	}

	/**
	 * @see \Man$this->maniaControl = $maniaControl;iaControl\Plugins\Plugin::getAuthor()
	 */
	public static function getAuthor() {
		return self::PLUGIN_AUTHOR;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::getDescription()
	 */
	public static function getDescription() {
		return 'Tool to manage the Blacklist';
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_BLACKLIST_FILE, "blacklist.txt", 'blacklist file');
		$this->maniaControl->getCommandManager()->registerCommandListener('addtobl', $this, 'doaddtobl', true, 'Add someone to the blacklist');
		$this->maniaControl->getCommandManager()->registerCommandListener('savebl', $this, 'dosavebl', true, 'Save the blacklist');
		$this->maniaControl->getCommandManager()->registerCommandListener('loadbl', $this, 'doloadbl', true, 'Load the blacklist');
		$this->maniaControl->getCommandManager()->registerCommandListener('cleanbl', $this, 'docleanbl', true, 'Clean the blacklist');

		$blacklist = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_BLACKLIST_FILE);
		if ($blacklist === "" || is_file($this->maniaControl->getServer()->getDirectory()->getUserDataFolder() . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . $blacklist)) {
			$this->maniaControl->getClient()->loadBlackList($blacklist);
		}
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {
	}

	/**
	 * Add players to the blacklist
	 *
	 * @param array $chat
	 * @param \ManiaControl\Players\Player $player
	*/
	public function doaddtobl(Array $chat, Player $player) {
		$command = explode(" ", $chat[1][2]);
		$peopletoadd = $command[1];

		if (empty($peopletoadd)) {
			$this->maniaControl->getChat()->sendError("You must set the nickname as argument", $player);
		} else {
			$mysqli = $this->maniaControl->getDatabase()->getMysqli();
			$query  = 'SELECT login FROM `' . PlayerManager::TABLE_PLAYERS . '` WHERE nickname LIKE "' . $peopletoadd . '"';
			$result = $mysqli->query($query);
			$array = mysqli_fetch_array($result);

			if (isset($array[0])) {
				$login = $array[0];
			} elseif (strlen($peopletoadd) == 22) {
				$login = $peopletoadd ;
			}
			if ($mysqli->error) {
				trigger_error($mysqli->error, E_USER_ERROR);
			}

			if (!isset($login)) {
				$this->maniaControl->getChat()->sendError( "Login not found. FYI The player must be connected" , $player);
			} else {
				if ($this->addLoginToBL($login)) {
					$this->maniaControl->getChat()->sendSuccess( "Player " . $peopletoadd . " added to the Blacklist" , $player);
				} else {
					$this->maniaControl->getChat()->sendSuccess( "Player " . $peopletoadd . " already in the Blacklist" , $player);
				}
			}
		}
	}

	/**
	 * Add login to the blacklist
	 *
	 * @param string $login
	 * @param array $blacklist
	*/
	public function addLoginToBL(String $login, array $blacklist = []) {
		if (empty($blacklist)) {
			$blacklist = $this->maniaControl->getClient()->getBlackList();
		}
		$logintoadd = "";
		$logintoadd = array_search($login ,array_column($blacklist, 'login'));
		if (strlen($logintoadd) == 0) {
			$this->maniaControl->getClient()->blackList($login);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * load from the blacklist file
	 *
	 * @param array $chat
	 * @param \ManiaControl\Players\Player $player
	*/
	public function doloadbl(Array $chat, Player $player) {
		$text = explode(" ",$chat[1][2]);
		if (count($text) > 1 && $text[1] != "") {
			$blacklist = $text[1];

			if (substr($blacklist , -4) != ".txt" && substr($blacklist , -4) != ".xml") {
				$blacklist .= ".txt";
			}
		} else {
			$blacklist = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_BLACKLIST_FILE);
		}
		if ($blacklist === "" || is_file($this->maniaControl->getServer()->getDirectory()->getUserDataFolder() . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . $blacklist)) {
			$this->maniaControl->getClient()->loadBlackList($blacklist);
			$this->maniaControl->getChat()->sendSuccess( "Blacklist loaded!" , $player);
		} else {
			$this->maniaControl->getChat()->sendError("Impossible to load the blacklist file" , $player);
		}
	}

	/**
	 * save to the blacklist file
	 *
	 * @param array $chat
	 * @param \ManiaControl\Players\Player $player
	*/
	public function dosavebl(Array $chat, Player $player) {
		try {
			$blacklist = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_BLACKLIST_FILE);
			
			if ($blacklist !== "") {
				$filepath = $this->maniaControl->getServer()->getDirectory()->getUserDataFolder() . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR  . $blacklist;

				if (!is_file($filepath)) {
					file_put_contents($filepath, '<?xml version="1.0" encoding="utf-8" ?><blacklist></blacklist>');
				}
			}

			// Workaround when the file was never loaded by the server
			$currentblacklist = $this->maniaControl->getClient()->getBlackList();
			$this->maniaControl->getClient()->loadBlackList($blacklist);

			$this->maniaControl->getClient()->cleanBlackList();
			foreach ($currentblacklist as $guest) {
				$this->maniaControl->getClient()->addGuest($guest->login);
			}

			$this->maniaControl->getClient()->saveBlackList($blacklist);

			$this->maniaControl->getChat()->sendSuccess("Blacklist saved!" , $player);
		} catch (\Exception $e) {
			Logger::logError("Impossible to save blacklist: " . $e->getMessage());
			$this->maniaControl->getChat()->sendError("Impossible to save blacklist: " . $e->getMessage(), $player);
		}
	}

	/**
	 * clean the blacklist
	 *
	 * @param array $chat
	 * @param \ManiaControl\Players\Player $player
	*/
	public function docleanbl(Array $chat, Player $player) {
		$this->maniaControl->getClient()->cleanBlackList();
		$this->maniaControl->getChat()->sendSuccess( "Blacklist cleaned!" , $player);
	}
}
