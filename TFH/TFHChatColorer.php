<?php
namespace TFH;

use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\ManiaControl;
use ManiaControl\Plugins\Plugin;
use \ManiaControl\Logger;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;
use ManiaControl\Commands\CommandListener;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;

if (! class_exists('TFH\TFHXmlRpcDataHandler')) {
	$this->maniaControl->getChat()->sendErrorToAdmins('TFHXmlRpcDataHandler is needed to use TFHChatColorer plugin. Install it and restart Maniacontrol');
	Logger::logError('TFHXmlRpcDataHandler is needed to use TFHChatColorer plugin. Install it and restart Maniacontrol');
	return false;
}
use TFH\TFHXmlRpcDataHandler;

/**
 * ManiaControl
 *
 * @author	Beu
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class TFHChatColorer implements CommandListener, CallbackListener, Plugin {
	/*
	* Constants
	*/
	const PLUGIN_ID			= 167;
	const PLUGIN_VERSION	= 1.0;
	const PLUGIN_NAME		= 'TFHChatColorer';
	const PLUGIN_AUTHOR		= 'Beu';


	/*
	* Private properties
	*/
	/** @var ManiaControl $maniaControl */
	private $maniaControl		= null;
	private $enabled			= false;
	private $groups				= [];
	private $betterchatlogins	= [];

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
	 * @see \ManiaControl\Plugins\Plugin::getAuthor()
	 */
	public static function getAuthor() {
		return self::PLUGIN_AUTHOR;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::getDescription()
	 */
	public static function getDescription() {
		return "A simple plugin that colors the logins in the chat";
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;
		$this->maniaControl->getCallbackManager()->registerCallbackListener('ManiaPlanet.PlayerChat', $this, 'handlePlayerChat');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERDISCONNECT, $this, 'handlePlayerDisconnect');

		$this->maniaControl->getCallbackManager()->registerCallbackListener(TFHXmlRpcDataHandler::CB_InitiatedEventData, $this, 'InitGroupsSettings');

		$this->maniaControl->getCommandManager()->registerCommandListener('chatformat', $this, 'onCommandChatFormat', false, 'Add support of multiple chat formats (for Better Chat Openplanet plugin for exemple');
		$this->maniaControl->getCommandManager()->registerCommandListener('reloadtrigram', $this, 'onCommandReloadTrigram', true, '[TFH] Reload Players Trigrams');

		$this->InitGroupsSettings();

		try {
			$this->maniaControl->getClient()->chatEnableManualRouting();
			$this->enabled = true;
		} catch (\Exception $ex) {
			$this->enabled = false;
			echo "error! \n";
		}
	}

	/**
	 * Update Widgets on Setting Changes
	 *
	 * @param Setting $setting
	 */
	public function updateSettings(Setting $setting) {
		if ($setting->belongsToClass($this)) {
			$this->InitGroupsSettings();
		}
	}


	/**
	 * Init chat prefix, groups, and players logins
	 */
	public function InitGroupsSettings() {
		Logger::Log("InitGroupsSettings");
		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		$query = 'select TFH_TeamsData.Trigram, TFH_PlayersData.Login FROM TFH_PlayersData JOIN TFH_TeamsData ON TFH_TeamsData.TeamId = TFH_PlayersData.TeamId;';

		$result = $mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error);
			return false;
		}
		while($row = $result->fetch_array()) {
			$array[] = $row;
		}
		if (isset($array[0])) {
			$this->groups = [];
			foreach ($array as $index => $value) {
				$this->groups[$value['Login']] = $value['Trigram'];
			}
		}
	}

	/**
	 * Handle when a player send a message in the chat
	 *
	 * @param array $callback
	 */
	public function handlePlayerChat($callback) {
		$playerUid = $callback[1][0];
		$login = $callback[1][1];
		$text = $callback[1][2];

		if ($playerUid != 0 && substr($text, 0, 1) != "/" && $this->enabled) {
			$source_player = $this->maniaControl->getPlayerManager()->getPlayer($login);
			if ($source_player == null) {
				return;
			}
			$nick = $source_player->nickname;
			$authLevel = $source_player->authLevel;

			$prefix = "";

			if (isset($this->groups[$login])) {
				$prefix = '$<'. $this->groups[$login] .'$>$<$n$fff➤$>';
			} else if ($authLevel > 0) {
				$prefix = '$<$b33TFH$>$<$n$fff➤$>'; 
			} else {
				$prefix = '$<$6acCAST$>$<$n$fff➤$>';
			}

			try {
				if (!empty($this->betterchatlogins)) {
					$jsonMessage = json_encode(['login' => $source_player->login, 'nickname' => $prefix . $nick, 'text' => $text], JSON_UNESCAPED_UNICODE);
					$this->maniaControl->getClient()->chatSendServerMessage('CHAT_JSON:' . $jsonMessage, $this->betterchatlogins);

					$defaultchatlogins = array();
					foreach($this->maniaControl->getPlayerManager()->getPlayers(false) as $player) {
						if (!in_array($player->login, $this->betterchatlogins)) {
							array_push($defaultchatlogins, $player->login);
						}
					}
					if (!empty($defaultchatlogins)) {
						$this->maniaControl->getClient()->chatSendServerMessage('$<' . $prefix . '$>' . $nick . '$<$226$w»$> ' . $text, $defaultchatlogins);
					}
				} else {
					//$this->maniaControl->getClient()->chatSendServerMessage('[$<' . $prefix .'$>'. $nick . '] ' . $text);
					$this->maniaControl->getClient()->chatSendServerMessage('$<' . $prefix . '$>' . $nick . '$<$226$w»$> ' . $text);
				}
			} catch (\Exception $e) {
				echo "error while sending chat message from $login: " . $e->getMessage() . "\n";
			}
		}
	}


	/**
	 * Command /chatformat
	 * 
	 * @param array			$chatCallback
	 * @param \ManiaControl\Players\Player $player
	 */
	public function onCommandChatFormat(array $chatCallback, Player $player) {
		$argument = $chatCallback[1][2];
		$argument = explode(" ", $argument);

		if (isset($argument[1]) && $argument[1] == "json") {
			if (!in_array($player->login, $this->betterchatlogins)) {
				array_push($this->betterchatlogins, $player->login);
			}
		} else if (isset($argument[1]) && $argument[1] == "text") {
			if (($key = array_search($player->login, $this->betterchatlogins)) !== false) {
				unset($this->betterchatlogins[$key]);
			}
		}
	}

	/**
	 * Command /chatformat
	 * 
	 * @param array			$chatCallback
	 * @param \ManiaControl\Players\Player $player
	 */
	public function onCommandReloadTrigram(array $chatCallback, Player $player) {
		Logger::Log("onCommandReloadTrigram");
		$this->InitGroupsSettings();
		$this->maniaControl->getChat()->sendSuccess("Trigrams reloaded", $player);
	}


	/**
	 * Handle when a player disconnects
	 * 
	 * @param \ManiaControl\Players\Player $player
	*/
	public function handlePlayerDisconnect(Player $player) {
		if (($key = array_search($player->login, $this->betterchatlogins)) !== false) {
			unset($this->betterchatlogins[$key]);
		}
	}

	/**
	 * Unload the plugin and its Resources
	 */
	public function unload() {
		$this->maniaControl->getClient()->chatEnableManualRouting(false);		
		$this->maniaControl->getCallbackManager()->unregisterCallbackListening('ManiaPlanet.OnPlayerChat', $this);
	}
}
