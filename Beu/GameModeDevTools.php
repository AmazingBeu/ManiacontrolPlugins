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
 * ManiaControl Profanity filter
 *
 * @author	Beu
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class GameModeDevTools implements ManialinkPageAnswerListener, CallbackListener, Plugin {
	/*
	* Constants
	*/
	const PLUGIN_ID			= 165;
	const PLUGIN_VERSION	= 1.0;
	const PLUGIN_NAME		= 'GameModeDevTools';
	const PLUGIN_AUTHOR		= 'Beu';


	const GAMEMODE_TO_LOAD	= 'Gamemode to load';
	/*
	* Private properties
	*/
	/** @var ManiaControl $maniaControl */
	private $maniaControl	= null;
	private $manialink		= <<<'EOD'
	<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
	<manialink id="DEBUG_ReloadGamemode" version="3">
		<script><!--
			main () {
				log("DEBUG_ReloadGamemode loaded");
				while(True) {
					yield;
					foreach(Event in PendingEvents) {
						if (Event.Type == CMlScriptEvent::Type::KeyPress && Event.KeyName == "F5") {
							TriggerPageAction("DEBUG_ReloadGamemode");
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
		return "";
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		$this->maniaControl->getSettingManager()->initSetting($this, self::GAMEMODE_TO_LOAD, "", "");
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerConnect');
		$this->maniaControl->getManialinkManager()->registerManialinkPageAnswerListener("DEBUG_ReloadGamemode", $this, 'LoadGamemode');

		$players = $this->maniaControl->getPlayerManager()->getPlayers();
		if (!empty($players)) {
			$this->maniaControl->getManialinkManager()->sendManialink($this->manialink,$players);
		}
		$this->LoadGamemode();
	}
		/**
	 * Handle when a player connects
	 * 
	 * @param Player $player
	 */
	public function handlePlayerConnect(Player $player) {
		$this->maniaControl->getManialinkManager()->sendManialink($this->manialink,$player->login);
	}

	public function LoadGamemode() {
		Logger::log('Load Gamemode');
		$file = $this->maniaControl->getServer()->getDirectory()->getUserDataFolder() . "Scripts/Modes" . DIRECTORY_SEPARATOR . $this->maniaControl->getSettingManager()->getSettingValue($this, self::GAMEMODE_TO_LOAD);
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


	/**
	 * Unload the plugin and its Resources
	 */
	public function unload() {

	}
}
