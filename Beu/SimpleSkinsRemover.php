<?php

namespace Beu;

use ManiaControl\Plugins\Plugin;
use ManiaControl\Players\Player;
use ManiaControl\ManiaControl;
use \ManiaControl\Logger;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Commands\CommandListener;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Callbacks\CallbackListener;

/**
 * Plugin Description
 *
 * @author  Beu
 * @version 1.0
 */
class SimpleSkinsRemover implements CallbackListener, CommandListener, Plugin {
	/*
	 * Constants
	 */
	const PLUGIN_ID			= 157;
	const PLUGIN_VERSION	= 1.0;
	const PLUGIN_NAME		= 'Simple Skins Remover';
	const PLUGIN_AUTHOR		= 'Beu';

	const SETTING_SIMPLESKINSREMOVER_DISABLE_SKINS		= "Allways disable skins";
	const SETTING_SIMPLESKINSREMOVER_AUTOMATIC			= "Disable skins automatically";
	const SETTING_SIMPLESKINSREMOVER_AUTOMATIC_VALUE	= "Number of players to disable skins";
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
		return 'Allows to disable skins very easily, manually or automatically depending on the number of players on the server';
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		$this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerConnectOrDisconnect');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERDISCONNECT, $this, 'handlePlayerConnectOrDisconnect');
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_SIMPLESKINSREMOVER_DISABLE_SKINS, false, "Disable skins (ignore automatic disabling)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_SIMPLESKINSREMOVER_AUTOMATIC, false, "Disable skins depending on the number of players");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_SIMPLESKINSREMOVER_AUTOMATIC_VALUE, 50, "Number of players before deactivating the skins");

		$this->maniaControl->getCommandManager()->registerCommandListener('skinsstatus', $this, 'skinsstatus', false, 'Check if skins are really disabled');

		$this->UpdateSkinsRemover();

		return true;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {
        $this->maniaControl->getClient()->execute('DisableProfileSkins', array(false));
	}

	/**
	 * Update Widgets on Setting Changes
	 *
	 * @param Setting $setting
	 */
	public function updateSettings(Setting $setting) {
		if ($setting->belongsToClass($this)) {
			$this->UpdateSkinsRemover();
		}
	}

	/**
	 * Enables or disables skins, depending on conditions
	 *
	 */
	private function UpdateSkinsRemover() {
		$currentstatus = $this->maniaControl->getClient()->execute('AreProfileSkinsDisabled', array());
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_SIMPLESKINSREMOVER_DISABLE_SKINS)) {
			if ($currentstatus == false) {
				$this->maniaControl->getClient()->execute('DisableProfileSkins', array(true));
				$this->maniaControl->getChat()->sendSuccess(' Skins are now disabled on this server to improve the quality of life of all players');
			}
		} else if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_SIMPLESKINSREMOVER_AUTOMATIC) && $this->maniaControl->getPlayerManager()->getPlayerCount(false,false) >= $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_SIMPLESKINSREMOVER_AUTOMATIC_VALUE)) {
			if ($currentstatus == false) {
				$this->maniaControl->getClient()->execute('DisableProfileSkins', array(true));
				$this->maniaControl->getChat()->sendSuccess(' Skins are now disabled on this server to improve the quality of life of all players');
			}
		} else {
			if ($currentstatus == true) {
				$this->maniaControl->getClient()->execute('DisableProfileSkins', array(false));
				$this->maniaControl->getChat()->sendSuccess(' Skins are now enabled on this server');
			}
		}
	}

	/**
	 * Informs if the skins are activated or not
	 *
	 * @param array $chat
	 * @param \ManiaControl\Players\Player $player
	*/
	public function skinsstatus(Array $chat, Player $player) {
        if ($this->maniaControl->getClient()->execute('AreProfileSkinsDisabled', array())) {
            $this->maniaControl->getChat()->sendSuccess(' Skins are disabled', $player);
        } else {
            $this->maniaControl->getChat()->sendError(' Skins are enabled', $player);
        }
	}

	/**
	 * Handle when a player connect
	 *
	 * @param \ManiaControl\Players\Player $player
	*/
	public function handlePlayerConnectOrDisconnect(Player $player) {
		$currentstatus = $this->maniaControl->getClient()->execute('AreProfileSkinsDisabled', array());
		$this->UpdateSkinsRemover();
		if ($currentstatus == $this->maniaControl->getClient()->execute('AreProfileSkinsDisabled', array()) && $currentstatus == true) {
			$this->maniaControl->getChat()->sendSuccess(' Skins are disabled on this server to improve the quality of life of all players', $player);
		}
	}
}
