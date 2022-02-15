<?php
namespace Beu;

use ManiaControl\ManiaControl;
use ManiaControl\Plugins\Plugin;
use \ManiaControl\Logger;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Players\Player;

use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;

use \Exception;

/**
 * ReloadDevTool
 *
 * @author	Beu
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class ReloadDevTool implements ManialinkPageAnswerListener, CallbackListener, Plugin {
	/*
	* Constants
	*/
	const PLUGIN_ID			= 165;
	const PLUGIN_VERSION	= 1.0;
	const PLUGIN_NAME		= 'ReloadDevTool';
	const PLUGIN_AUTHOR		= 'Beu';

	const SETTING_RELOAD_GAMEMODE		= 'Reload Gamemode';
	const SETTING_GAMEMODE_TO_LOAD		= 'Gamemode to load';
	const SETTING_RESTART_MANIACONTROL	= 'Restart Maniacontrol';

	/*
	* Private properties
	*/
	/** @var ManiaControl $maniaControl */
	private $maniaControl	= null;
	private $manialink		= <<<'EOD'
	<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
	<manialink id="ReloadDevTool" version="3">
		<script><!--
			main () {
				log("DEBUG_ReloadGamemode loaded");
				while(True) {
					yield;
					foreach(Event in PendingEvents) {
						if (Event.Type == CMlScriptEvent::Type::KeyPress && Event.KeyName == "F5") {
							TriggerPageAction("ReloadDevTool_Reload");
						}
					}
				}
			}
		--></script>
	</manialink>
	EOD;

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
		return "Simple plugin to reload gamemode or maniacontrol with F5";
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_RELOAD_GAMEMODE, false, "");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_GAMEMODE_TO_LOAD, "", 'File to load in UserData/Scripts/Modes/');
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_RESTART_MANIACONTROL, false, "");

		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerConnect');
		$this->maniaControl->getManialinkManager()->registerManialinkPageAnswerListener("ReloadDevTool_Reload", $this, 'handleReload');

		$admins = $this->maniaControl->getAuthenticationManager()->getAdmins();
		if (!empty($admins)) {
			$this->maniaControl->getManialinkManager()->sendManialink($this->manialink,$admins);
		}

	}
	
	/**
	 * Handle when a player connects
	 * 
	 * @param Player $player
	 */
	public function handlePlayerConnect(Player $player) {
		if ($player->authLevel > 0) {
			$this->maniaControl->getManialinkManager()->sendManialink($this->manialink,$player->login);
		}
	}

	public function handleReload(array $callback, Player $player) {
		if ($player->authLevel <= 0) {
			Logger::logError('Wrong authlevel');
			return;
		}
		Logger::log('handleReload');
		$this->maniaControl->getChat()->sendSuccess("Handle Reload");

		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_RELOAD_GAMEMODE)) {
			$file = $this->maniaControl->getServer()->getDirectory()->getUserDataFolder() . "Scripts" . DIRECTORY_SEPARATOR .  "Modes" . DIRECTORY_SEPARATOR . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_GAMEMODE_TO_LOAD);
			if ($file && is_file($file)) {
				try {
					$this->maniaControl->getChat()->sendSuccess("Loading In-Dev Script");
					$this->maniaControl->getClient()->setModeScriptText(file_get_contents($file));
				} catch (\Exception $e) {
					Logger::logError($e->getMessage());
					$this->maniaControl->getChat()->sendErrorToAdmins($e->getMessage());
				}
			} else {
				Logger::logError('No Game mode to load');
				$this->maniaControl->getChat()->sendErrorToAdmins("No Game mode to load");
			}
		}
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_RESTART_MANIACONTROL)) {
			$this->maniaControl->reboot();
		}
	}

	/**
	 * Unload the plugin and its Resources
	 */
	public function unload() {

	}
}
