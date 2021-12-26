<?php
namespace Beu;

use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\ManiaControl;
use ManiaControl\Plugins\Plugin;
use \ManiaControl\Logger;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;
use ManiaControl\Commands\CommandListener;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;

/**
 * ManiaControl
 *
 * @author	Beu
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class SimpleChatColorer implements CommandListener, CallbackListener, Plugin {
	/*
	* Constants
	*/
	const PLUGIN_ID			= 161;
	const PLUGIN_VERSION	= 1.2;
	const PLUGIN_NAME		= 'SimpleChatColorer';
	const PLUGIN_AUTHOR		= 'Beu';

	const SETTING_CHATDAMDMINCOLORER_USEADMINCOLOR		= 'Use Admin Color';
	const SETTING_CHATDAMDMINCOLORER_NUMBEROFGROUPS		= 'Number of groups';

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

		$this->maniaControl->getCommandManager()->registerCommandListener('chatformat', $this, 'onCommandChatFormat', false, 'Add support of multiple chat formats (for Better Chat Openplanet plugin for exemple');

		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_CHATDAMDMINCOLORER_USEADMINCOLOR, true, "Use Admin Color of Maniacontrol settings");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_CHATDAMDMINCOLORER_NUMBEROFGROUPS, 1, "Number of groups to setup");

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
	private function InitGroupsSettings() {
		$i = 1;
		$nbofgroups = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHATDAMDMINCOLORER_NUMBEROFGROUPS);
		$this->groups = [];

		for ($i; $i <= $nbofgroups; $i++) {
			$this->maniaControl->getSettingManager()->initSetting($this, "Group " . $i . " prefix", "", "Chat prefix of the group one");
			$this->maniaControl->getSettingManager()->initSetting($this, "Group " . $i . " players login", "", "Comma separated players login");

			$this->groups[$this->maniaControl->getSettingManager()->getSettingValue($this, "Group " . $i . " prefix")] = explode(',', str_replace(' ', '', $this->maniaControl->getSettingManager()->getSettingValue($this, "Group " . $i . " players login")));
		}

		$allsettings = $this->maniaControl->getSettingManager()->getSettingsByClass($this);
		foreach ($allsettings as $key => $value) {
			$name = $value->setting;
			preg_match('/^Group (\d*) prefix$/', $name, $match);
			if (count($match) > 0 && $match[1] > $nbofgroups) {
				$this->maniaControl->getSettingManager()->deleteSetting($this, "Group " . $match[1] . " prefix");
				$this->maniaControl->getSettingManager()->deleteSetting($this, "Group " . $match[1] . " players login");
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

			if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHATDAMDMINCOLORER_USEADMINCOLOR) && $authLevel > 0) {
				$prefix = $this->maniaControl->getColorManager()->getColorByLevel($authLevel);
			} else {
				foreach ($this->groups as $groupprefix => $players) {
					if (in_array($login, $players)) {
						$prefix = $groupprefix;
						break;
					}
				}
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
						$this->maniaControl->getClient()->chatSendServerMessage('[$<' . $prefix . $nick . '$>] ' . $text, $defaultchatlogins);
					}
				} else {
					$this->maniaControl->getClient()->chatSendServerMessage('[$<' . $prefix . $nick . '$>] ' . $text);
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
