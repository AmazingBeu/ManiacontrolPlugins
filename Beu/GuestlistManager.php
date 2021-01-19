<?php

namespace Beu;

use ManiaControl\Commands\CommandListener;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Plugins\Plugin;
use ManiaControl\ManiaControl;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;
use \ManiaControl\Logger;

/**
 * Plugin Description
 *
 * @author  Template Author
 * @version 1.0
 */
class GuestlistManager implements CommandListener, Plugin {
	/*
	 * Constants
	 */
	const PLUGIN_ID			= 154;
	const PLUGIN_VERSION	= 1.0;
	const PLUGIN_NAME		= 'Guestlist Manager';
	const PLUGIN_AUTHOR		= 'Beu';

	const SETTING_GUESTLIST_FILE = 'Guestlist file';

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
		return 'Tool to manage the Guestlist';
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_GUESTLIST_FILE, "guestlist.txt", 'guestlist file');
		$this->maniaControl->getCommandManager()->registerCommandListener('addalltogl', $this, 'doaddalltogl', true, 'Add all connected players to the guestlist');
		$this->maniaControl->getCommandManager()->registerCommandListener('addtogl', $this, 'doaddtogl', true, 'Add someone to the guestlist');
		$this->maniaControl->getCommandManager()->registerCommandListener('savegl', $this, 'dosavegl', true, 'Save the guestlist');
		$this->maniaControl->getCommandManager()->registerCommandListener('loadgl', $this, 'doloadgl', true, 'Load the guestlist');
		$this->maniaControl->getCommandManager()->registerCommandListener('cleangl', $this, 'docleangl', true, 'Clean the guestlist');

		return true;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {
	}

	/**
	 * Add all connected players to the guestlist
	 *
	 * @param array $chat
	 * @param \ManiaControl\Players\Player $player
	*/
	public function doaddalltogl(Array $chat, Player $player) {
		$players = $this->maniaControl->getPlayerManager()->getPlayers();
		$guestlist = $this->maniaControl->getClient()->getGuestList();
		$i = 0;
		foreach ($players as &$index) {
			if ($this->addLoginToGL($index->login, $guestlist)) {
				$i++;
			}
		}
		$this->maniaControl->getChat()->sendSuccess( "All connected players have been added to the Guestlist");
		$this->maniaControl->getChat()->sendSuccess( "Added: " . $i . "/" . count($players), $player);
	}

	/**
	 * Add players to the guestlist
	 *
	 * @param array $chat
	 * @param \ManiaControl\Players\Player $player
	*/
	public function doaddtogl(Array $chat, Player $player) {
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
				if ($this->addLoginToGL($login)) {
					$this->maniaControl->getChat()->sendSuccess( "Player " . $peopletoadd . " added to the Guestlist" , $player);
				} else {
					$this->maniaControl->getChat()->sendSuccess( "Player " . $peopletoadd . " already in the Guestlist" , $player);
				}
			}
		}
	}

	/**
	 * Add login to the guestlist
	 *
	 * @param string $login
	 * @param array $guestlist
	*/
	public function addLoginToGL(String $login, array $guestlist = []) {
		if (empty($guestlist)) {
			$guestlist = $this->maniaControl->getClient()->getGuestList();
		}
		$logintoadd = "";
		$logintoadd = array_search($login ,array_column($guestlist, 'login'));
		if (strlen($logintoadd) == 0) {
			$this->maniaControl->getClient()->addGuest($login);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * load from the guestlist file
	 *
	 * @param array $chat
	 * @param \ManiaControl\Players\Player $player
	*/
	public function doloadgl(Array $chat, Player $player) {
		if (!empty(self::SETTING_GUESTLIST_FILE)) {
			$this->maniaControl->getClient()->loadGuestList($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_GUESTLIST_FILE));
			$this->maniaControl->getChat()->sendSuccess( "Guestlist loaded!" , $player);
		} else {
			$this->maniaControl->getChat()->sendError( "Guestlist option empty" , $player);
		}
	}

	/**
	 * save to the guestlist file
	 *
	 * @param array $chat
	 * @param \ManiaControl\Players\Player $player
	*/
	public function dosavegl(Array $chat, Player $player) {
		if (!empty(self::SETTING_GUESTLIST_FILE)) {
			$this->maniaControl->getClient()->saveGuestList($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_GUESTLIST_FILE));
			$this->maniaControl->getChat()->sendSuccess( "Guestlist saved!" , $player);
		} else {
			$this->maniaControl->getChat()->sendError( "Guestlist option empty" , $player);
		}
	}

	/**
	 * clean the guestlist
	 *
	 * @param array $chat
	 * @param \ManiaControl\Players\Player $player
	*/
	public function docleangl(Array $chat, Player $player) {
		$this->maniaControl->getClient()->cleanGuestList();
		$this->maniaControl->getChat()->sendSuccess( "Guestlist cleaned!" , $player);
	}
}
