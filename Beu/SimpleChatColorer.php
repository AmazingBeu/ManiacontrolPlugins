<?php
namespace Beu;

use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\ManiaControl;
use ManiaControl\Plugins\Plugin;
use \ManiaControl\Logger;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;

/**
 * ManiaControl
 *
 * @author	Beu
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class SimpleChatColorer implements CallbackListener, Plugin {
	/*
	* Constants
	*/
	const PLUGIN_ID			= 161;
	const PLUGIN_VERSION	= 1.0;
	const PLUGIN_NAME		= 'SimpleChatColorer';
	const PLUGIN_AUTHOR		= 'Beu';

	const SETTING_CHATDAMDMINCOLORER_USEADMINCOLOR		= 'Use Admin Color';
	const SETTING_CHATDAMDMINCOLORER_NUMBEROFGROUPS		= 'Number of groups';

	/*
	* Private properties
	*/
	/** @var ManiaControl $maniaControl */
	private $maniaControl	= null;
	private $enabled		= false;
	private $groups			= [];

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

		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_CHATDAMDMINCOLORER_USEADMINCOLOR, true, "Use Admin Color of Maniacontrol settings");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_CHATDAMDMINCOLORER_NUMBEROFGROUPS, 1, "Nomber of groups to setup");

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
				$this->maniaControl->getClient()->chatSendServerMessage('[$<' . $prefix . $nick . '$>] ' . $text);
			} catch (\Exception $e) {
				echo "error while sending chat message to $login: " . $e->getMessage() . "\n";
			}
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

