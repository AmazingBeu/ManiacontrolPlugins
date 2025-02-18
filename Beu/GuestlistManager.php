<?php

namespace Beu;

use ManiaControl\ManiaControl;
use ManiaControl\Logger;
use ManiaControl\Admin\AuthenticationManager;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\TimerListener;
use ManiaControl\Commands\CommandListener;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Settings\SettingManager;

/**
 * Plugin Description
 *
 * @author  Beu
 */
class GuestlistManager implements CommandListener, CallbackListener, TimerListener, Plugin {
	/*
	 * Constants
	 */
	const PLUGIN_ID			= 154;
	const PLUGIN_VERSION	= 2.0;
	const PLUGIN_NAME		= 'Guestlist Manager';
	const PLUGIN_AUTHOR		= 'Beu';

	const SETTING_GUESTLIST_FILE = 'Guestlist file';
	const SETTING_LOAD_AT_START = 'Load Guestlist at Maniacontrol start';
	const SETTING_ADD_ADMINS = 'Automatically add admins to the guestlist';
	const SETTING_ADMIN_LEVEL = 'Minimum Admin level to automatically add admin to the guestlist';
	const SETTING_ENFORCE_GUESTLIST = 'Kick all non-guestlisted players';

	/**
	 * Private Properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl = null;
	private $checkNonGuestlistedPlayers = true;

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

		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_GUESTLIST_FILE, "guestlist.txt", 'guestlist file', 10);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_LOAD_AT_START, false, 'Load guestlist file at maniacontrol start', 20);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_ADD_ADMINS, false, 'Due to a bug, player can join a server without being guestlisted. This setting is to prevent this.', 90);
		$this->maniaControl->getAuthenticationManager()->definePluginPermissionLevel($this, self::SETTING_ADMIN_LEVEL, AuthenticationManager::AUTH_LEVEL_MODERATOR);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_ENFORCE_GUESTLIST, false, 'Due to a bug, player can join a server without being guestlisted. This setting is to prevent this.', 110);
		
		$this->maniaControl->getCommandManager()->registerCommandListener(['gladdall', 'addalltogl'], $this, 'handleAddAll', true, 'Add all connected players to the guestlist');
		$this->maniaControl->getCommandManager()->registerCommandListener(['gladd', 'addtogl'], $this, 'handleAdd', true, 'Add player to the guestlist');
		$this->maniaControl->getCommandManager()->registerCommandListener(['glremove', 'gldelete', 'removefromgl', 'deletefromgl'], $this, 'handleRemove', true, 'Remove player to the guestlist');
		$this->maniaControl->getCommandManager()->registerCommandListener(['glsave', 'savegl'], $this, 'handleSave', true, 'Save the guestlist file');
		$this->maniaControl->getCommandManager()->registerCommandListener(['glload', 'loadgl'], $this, 'handleLoad', true, 'Load the guestlist file');
		$this->maniaControl->getCommandManager()->registerCommandListener(['glclear', 'glclean', 'cleangl'], $this, 'handleClear', true, 'Clear the guestlist');
		$this->maniaControl->getCommandManager()->registerCommandListener(['glkickall'], $this, 'handleKickAll', true, 'Kick non-guestlisted players');

		$this->maniaControl->getCallbackManager()->registerCallbackListener(AuthenticationManager::CB_AUTH_LEVEL_CHANGED, $this, 'handleAuthLevelChanged');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerConnect');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'handleSettingChanged');


		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_LOAD_AT_START)) {
			$guestlist = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_GUESTLIST_FILE);
			if ($guestlist === "" || is_file($this->maniaControl->getServer()->getDirectory()->getUserDataFolder() . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . $guestlist)) {
				$this->maniaControl->getClient()->loadGuestList($guestlist);
			}
		}
		$this->addAdminsToGuestlist();

		$this->maniaControl->getTimerManager()->registerTimerListening($this, function () {
			if (!$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_ENFORCE_GUESTLIST)) return;
			if (!$this->checkNonGuestlistedPlayers) return;
			$this->checkNonGuestlistedPlayers = false;
			$this->kickNonGuestlistedPlayers();

		}, 1000);
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {
	}

	/**
	 * handle Add All to GL command
	 *
	 * @param array $chat
	 * @param \ManiaControl\Players\Player $player
	 */
	public function handleAddAll(Array $chat, Player $player) {
		$players = $this->maniaControl->getPlayerManager()->getPlayers();
		$guestlist = $this->maniaControl->getClient()->getGuestList();
		$i = 0;
		foreach ($players as &$index) {
			if ($this->addLoginToGL($index->login, $guestlist)) {
				$i++;
			}
		}
		Logger::log('Adding all connected players to the guestlist by '. $player->nickname);
		$this->maniaControl->getChat()->sendSuccess("All connected players have been added to the Guestlist");
		$this->maniaControl->getChat()->sendSuccess( "Added: " . $i . "/" . count($players), $player);
	}

	/**
	 * handle Add GL command
	 *
	 * @param array $chat
	 * @param \ManiaControl\Players\Player $player
	 */
	public function handleAdd(Array $chat, Player $player) {
		$command = explode(" ", $chat[1][2]);
		$playerToAdd = $command[1];

		if (empty($playerToAdd)) {
			$this->maniaControl->getChat()->sendError("You must set the login or the nickname as argument", $player);
		} else {
			$mysqli = $this->maniaControl->getDatabase()->getMysqli();
			$stmt = $mysqli->prepare('SELECT `login` FROM `' . PlayerManager::TABLE_PLAYERS . '` WHERE nickname LIKE ? LIMIT 1;');
			$stmt->bind_param('s', $playerToAdd);
			$stmt->execute();
			$result = $stmt->get_result();
			$array = mysqli_fetch_array($result);

			if (isset($array[0])) {
				$login = $array[0];
			} elseif (strlen($playerToAdd) == 22) {
				$login = $playerToAdd;
			}
			if ($mysqli->error) {
				trigger_error($mysqli->error, E_USER_ERROR);
			}

			if (!isset($login)) {
				$this->maniaControl->getChat()->sendError("Player not found. Use the nickname of already connected player or just their login", $player);
			} else {
				if ($this->addLoginToGL($login)) {
					Logger::log('Player "'. $playerToAdd .'" added to the guestlist by '. $player->nickname);
					$this->maniaControl->getChat()->sendSuccess('Player "' . $playerToAdd . '" added to the Guestlist', $player);
				} else {
					$this->maniaControl->getChat()->sendSuccess('Player "' . $playerToAdd . '" already in the Guestlist', $player);
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
	public function addLoginToGL(String $login, ?array $guestlist = null) {
		if ($guestlist === null) {
			$guestlist = $this->maniaControl->getClient()->getGuestList();
		}

		if (!in_array($login, array_column($guestlist, 'login'))) {
			Logger::log('Player "'. $login .'" added to the guestlist');
			$this->maniaControl->getClient()->addGuest($login);
			$this->checkNonGuestlistedPlayers = true;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * handle Remove from GL command
	 * 
	 * @param array $chat 
	 * @param Player $player 
	 * @return void 
	 */
	public function handleRemove(Array $chat, Player $player) {
		$command = explode(" ", $chat[1][2]);
		$playerToRemove = $command[1];

		if (empty($playerToRemove)) {
			$this->maniaControl->getChat()->sendError("You must set the login or the nickname as argument", $player);
		} else {
			$mysqli = $this->maniaControl->getDatabase()->getMysqli();
			$stmt = $mysqli->prepare('SELECT `login` FROM `' . PlayerManager::TABLE_PLAYERS . '` WHERE nickname LIKE ? LIMIT 1;');
			$stmt->bind_param('s', $playerToRemove);
			$stmt->execute();
			$result = $stmt->get_result();
			$array = mysqli_fetch_array($result);

			if (isset($array[0])) {
				$login = $array[0];
			} elseif (strlen($playerToRemove) == 22) {
				$login = $playerToRemove;
			}
			if ($mysqli->error) {
				trigger_error($mysqli->error, E_USER_ERROR);
			}

			if (!isset($login)) {
				$this->maniaControl->getChat()->sendError('Player not found. Use the nickname of already connected player or just their login', $player);
			} else {
				if ($this->removeLoginFromGL($login)) {
					Logger::log('Player "'. $playerToRemove .'" removed from the guestlist by '. $player->nickname);
					$this->maniaControl->getChat()->sendSuccess('Player "' . $playerToRemove . '" removed to the Guestlist', $player);
				} else {
					$this->maniaControl->getChat()->sendSuccess('Player "' . $playerToRemove . '" not in the Guestlist', $player);
				}
			}
		}
	}

	/**
	 * Remove login from the guestlist
	 *
	 * @param string $login
	 * @param array $guestlist
	 */
	public function removeLoginFromGL(String $login, ?array $guestlist = null) {
		if ($guestlist === null) {
			$guestlist = $this->maniaControl->getClient()->getGuestList();
		}

		if (in_array($login, array_column($guestlist, 'login'))) {
			Logger::log('Player "'. $login .'" removed from the guestlist');
			$this->maniaControl->getClient()->removeGuest($login);
			$this->checkNonGuestlistedPlayers = true;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * hangle Load GL command
	 *
	 * @param array $chat
	 * @param \ManiaControl\Players\Player $player
	 */
	public function handleLoad(Array $chat, Player $player) {
		$guestlist = '';

		$text = explode(" ",$chat[1][2]);
		if (count($text) > 1 && $text[1] != "") {
			$guestlist = $text[1];

			if (substr($guestlist , -4) != ".txt" && substr($guestlist , -4) != ".xml") {
				$guestlist .= ".txt";
			}
		} else {
			$guestlist = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_GUESTLIST_FILE);
		}

		try {
			if ($guestlist === "") {
				Logger::log('Player "'. $player->nickname .'" loaded default guestlist');
				$this->maniaControl->getClient()->loadGuestList();
				$this->checkNonGuestlistedPlayers = true;
			} else {
				$filepath = $this->maniaControl->getServer()->getDirectory()->getUserDataFolder() . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . $guestlist;
				if (is_file($filepath)) {
					Logger::log('Player "'. $player->nickname .'" loaded guestlist file: '. $guestlist);
					$this->maniaControl->getClient()->loadGuestList($guestlist);
					$this->checkNonGuestlistedPlayers = true;
				} else {
					$this->maniaControl->getChat()->sendError("No guestlist file: ". $filepath, $player);
					Logger::logError("Can't load guestlist, no file: ". $filepath);
				}
			}
			
			$this->maniaControl->getChat()->sendSuccess("Guestlist loaded!", $player);
		} catch (\Throwable $th) {
			$this->maniaControl->getChat()->sendError("Can't load guestlist: ". $th->getMessage(), $player);
			Logger::logError("Can't load guestlist: ". $th->getMessage());
		}
	}

	/**
	 * handle Save GL command
	 *
	 * @param array $chat
	 * @param \ManiaControl\Players\Player $player
	 */
	public function handleSave(Array $chat, Player $player) {
		$guestlist = '';
		$text = explode(" ",$chat[1][2]);
		if (count($text) > 1 && $text[1] != "") {
			$guestlist = $text[1];

			if (substr($guestlist , -4) != ".txt" && substr($guestlist , -4) != ".xml") {
				$guestlist .= ".txt";
			}
		} else {
			$guestlist = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_GUESTLIST_FILE);
		}

		if ($guestlist === "") {
			$this->maniaControl->getChat()->sendError('No guestlist file provided');
			return;
		}

		try {
			$filepath = $this->maniaControl->getServer()->getDirectory()->getUserDataFolder() . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR  . $guestlist;
			$directory = dirname($filepath);

			/**
			 * The server can't save a file if it was not loaded before: https://forum.nadeo.com/viewtopic.php?p=11976
			 * this is a workaround
			 */
			if (!is_dir($directory)) {
				mkdir($directory, 0755, true);
			}

			if (!is_file($filepath)) {
				file_put_contents($filepath, '<?xml version="1.0" encoding="utf-8" ?><guestlist></guestlist>');
			}

			// Workaround when the file was never loaded by the server
			$currentguestlist = $this->maniaControl->getClient()->getGuestList();
			$this->maniaControl->getClient()->loadGuestList($guestlist);

			$this->maniaControl->getClient()->cleanGuestList();
			foreach ($currentguestlist as $guest) {
				$this->maniaControl->getClient()->addGuest($guest->login);
			}

			Logger::log('Player "'. $player->nickname .'" saved guestlist file: '. $guestlist);
			$this->maniaControl->getClient()->saveGuestList($guestlist);
			$this->checkNonGuestlistedPlayers = true;

			$this->maniaControl->getChat()->sendSuccess("Guestlist saved!", $player);
		} catch (\Exception $e) {
			Logger::logError("Impossible to save guestlist: " . $e->getMessage());
			$this->maniaControl->getChat()->sendError("Impossible to save guestlist: " . $e->getMessage(), $player);
		}
	}

	/**
	 * handle Clear GL command
	 *
	 * @param array $chat
	 * @param \ManiaControl\Players\Player $player
	 */
	public function handleClear(Array $chat, Player $player) {
		Logger::log('Guestlist cleared by '. $player->nickname);
		$this->maniaControl->getClient()->cleanGuestList();
		$this->checkNonGuestlistedPlayers = true;
		$this->maniaControl->getChat()->sendSuccess("Guestlist cleaned!", $player);
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_ADD_ADMINS)) {
			$this->maniaControl->getChat()->sendSuccess("Re-adding admins to the guestlist", $player);
			$this->addAdminsToGuestlist();
		}
	}

	/**
	 * handle Kick non-guestlisted players command
	 *
	 * @param array $chat
	 * @param \ManiaControl\Players\Player $player
	 */
	public function handleKickAll(Array $chat, Player $player) {
		Logger::log('All non-guestlisted players kicked by '. $player->nickname);
		$kicked = $this->kickNonGuestlistedPlayers();
		$this->maniaControl->getChat()->sendSuccess($kicked . ' players kicked', $player);
	}

	/**
	 * Kick non-guestlist players from the server
	 * 
	 * @return int 
	 */
	private function kickNonGuestlistedPlayers() {
		$kicked = 0;
		$guests = array_column($this->maniaControl->getClient()->getGuestList(), 'login');
		foreach ($this->maniaControl->getPlayerManager()->getPlayers() as $player) {
			if (!in_array($player->login, $guests)) {
				try {
					Logger::log('Player "'. $player->nickname .'" kicked from the server as not guestlisted');
					$this->maniaControl->getClient()->kick($player->nickname, "You are not guestlisted on the server");
					$kicked++;
				} catch (\Throwable $th) {
					Logger::logError("Can't kick ". $player->nickname .": ". $th->getMessage());
				}
			}
		}
		return $kicked;
	}

	/**
	 * add Admins to GL
	 *
	 * @param \ManiaControl\Players\Player $player
	 */
	private function addAdminsToGuestlist() {
		if (!$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_ADD_ADMINS)) return;

		$guestlist = $this->maniaControl->getClient()->getGuestList();

		foreach ($this->maniaControl->getAuthenticationManager()->getAdmins() as $admin) {
			if ($this->maniaControl->getAuthenticationManager()->checkRight($admin, $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_ADMIN_LEVEL))) continue;
			$this->addLoginToGL($admin->login, $guestlist);
		}
	}

	/**
	 * handle Auth Level changed callback
	 *
	 * @param \ManiaControl\Players\Player $player
	 */
	public function handleAuthLevelChanged(Player $player) {
		if (!$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_ADD_ADMINS)) return;
		
		$guestlist = $this->maniaControl->getClient()->getGuestList();

		$isGuestlisted = in_array($player->login, array_column($guestlist, 'login'));
		$isAdmin = $this->maniaControl->getAuthenticationManager()->checkRight($player,$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_ADMIN_LEVEL));

		if (!$isGuestlisted && $isAdmin) {
			$this->addLoginToGL($player->login, $guestlist);
			$this->maniaControl->getChat()->sendSuccessToAdmins('New admin "'. $player->nickname . '" automatically added to the guestlist');
			Logger::log('New admin "'. $player->nickname . '" automatically added to the guestlist');
		} else if ($isGuestlisted && !$isAdmin) {
			$this->maniaControl->getChat()->sendErrorToAdmins('Non-admin "'. $player->nickname . '" is still in the guestlist. Remove them manually if needed');
		}
	}

	/**
	 * handle Player connect callback
	 */
	public function handlePlayerConnect() {
		if (!$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_ENFORCE_GUESTLIST)) return;
		$this->checkNonGuestlistedPlayers = true;
	}

	/**
	 * handle Setting changed callback
	 */
	public function handleSettingChanged() {
		if (!$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_ENFORCE_GUESTLIST)) return;
		$this->checkNonGuestlistedPlayers = true;
	}
}
