<?php

namespace MatchManagerSuite;

use FML\Controls\Frame;
use FML\Controls\Labels\Label_Text;
use FML\Controls\Quad;
use FML\ManiaLink;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Logger;
use ManiaControl\ManiaControl;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Plugins\PluginManager;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;
use ManiaControl\Commands\CommandListener;


if (!class_exists('MatchManagerSuite\MatchManagerCore')) {
	$this->maniaControl->getChat()->sendErrorToAdmins('MatchManager Core is required to use MatchManagerPlayersPause plugin. Install it and restart Maniacontrol');
	Logger::logError('MatchManager Core is required to use MatchManagerPlayersPause plugin. Install it and restart Maniacontrol');
	return false;
}


/**
 * MatchManager Players Pause
 *
 * @author		Beu
 * @license		http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class MatchManagerPlayersPause implements ManialinkPageAnswerListener, CommandListener, CallbackListener, Plugin {
	/*
	 * Constants
	 */
	const PLUGIN_ID											= 159;
	const PLUGIN_VERSION									= 1.5;
	const PLUGIN_NAME										= 'MatchManager Players Pause';
	const PLUGIN_AUTHOR										= 'Beu';

	const LOG_PREFIX										= '[MatchManagerPlayersPause] ';

	// MatchManagerWidget Properties
	const MATCHMANAGERCORE_PLUGIN							= 'MatchManagerSuite\MatchManagerCore';

	const ACTION_PAUSE_BUTTON								= 'PauseButton.Action';
	const MLID_MATCH_PAUSE_WIDGET							= 'Pause ButtonWidget';
	const SETTING_MATCH_PAUSE_MODE							= 'Enable plugin';
	const SETTING_MATCH_PAUSE_WAIT_END_ROUND				= 'Wait the end of the round to start the pause';
	const SETTING_MATCH_PAUSE_NBPLAYERS						= 'Minimal number of players before pause the match';
	const SETTING_MATCH_PAUSE_POSX							= 'Position: X';
	const SETTING_MATCH_PAUSE_POSY							= 'Position: Y';

	/*
	 * Private properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl 			= null;
	/** @var MatchManagerCore $MatchManagerCore */
	private $MatchManagerCore		= null;

	private $playerspausestate		= array();

	private $MLPauseAsked				= null;
	private $MLPauseNotAsked			= null;

	private $LaunchPauseAtTheEnd		= false;


	/**
	 * @param \ManiaControl\ManiaControl $maniaControl
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
		return 'Add a button for players to launch a pause if needed';
	}

	/**
	 * @param \ManiaControl\ManiaControl $maniaControl
	 * @return bool
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;
		$this->MatchManagerCore = $this->maniaControl->getPluginManager()->getPlugin(self::MATCHMANAGERCORE_PLUGIN);

		if ($this->MatchManagerCore == Null) {
			throw new \Exception('MatchManager Core is needed to use ' . self::PLUGIN_NAME);
		}

		$this->maniaControl->getCommandManager()->registerCommandListener('pause', $this, 'onCommandSetPausePlayer', false, 'Change status to Pause.');

		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_PAUSE_MODE, false, "Activate Pause widget");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_PAUSE_WAIT_END_ROUND, true, "Wait the end of the round to launch the pause");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_PAUSE_NBPLAYERS, 2, "Minimal number of players to start a match if all are pause");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_PAUSE_POSX, 152.5, "Position of the Pause widget (on X axis)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_PAUSE_POSY, 40, "Position of the Pause widget (on Y axis)");

		$this->maniaControl->getCallbackManager()->registerCallbackListener(PluginManager::CB_PLUGIN_UNLOADED, $this, 'handlePluginUnloaded');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');

		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERDISCONNECT, $this, 'handlePlayerDisconnect');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERINFOCHANGED, $this, 'displayPauseWidgetIfNeeded');

		$this->maniaControl->getCallbackManager()->registerCallbackListener(MatchManagerCore::CB_MATCHMANAGER_STARTMATCH, $this, 'handleMatchManagerCoreCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(MatchManagerCore::CB_MATCHMANAGER_ENDMATCH, $this, 'handleMatchManagerCoreCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(MatchManagerCore::CB_MATCHMANAGER_STOPMATCH, $this, 'handleMatchManagerCoreCallback');

		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::MP_STARTROUNDSTART, $this, 'handleBeginRoundCallback');


		// Register ManiaLink Pages
		$this->maniaControl->getManialinkManager()->registerManialinkPageAnswerListener(self::ACTION_PAUSE_BUTTON, $this, 'handlePause');

		$this->updateManialinks();
		$this->displayPauseWidgetIfNeeded();

		return true;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {
		$this->closePauseWidget();
	}

	/**
	 * Custom log function to add prefix
	 * 
	 * @param mixed $message
	 */
	private function log(mixed $message) {
		Logger::log(self::LOG_PREFIX . $message);
	}

	/**
	 * Custom logError function to add prefix
	 * 
	 * @param mixed $message
	 */
	private function logError(mixed $message) {
		Logger::logError(self::LOG_PREFIX . $message);
	}

	/**
	 * handlePluginUnloaded
	 *
	 * @param  string $pluginClass
	 * @param  Plugin $plugin
	 * @return void
	 */
	public function handlePluginUnloaded(string $pluginClass, Plugin $plugin) {
		if ($pluginClass == self::MATCHMANAGERCORE_PLUGIN) {
			$this->maniaControl->getChat()->sendErrorToAdmins(self::PLUGIN_NAME . " disabled because MatchManager Core is now disabled");
			$this->log(self::PLUGIN_NAME . " disabled because MatchManager Core is now disabled");
			$this->maniaControl->getPluginManager()->deactivatePlugin((get_class($this)));
		}
	}

	/**
	 * Generate Manialinks variables
	*/
	public function getPlayersPauseState() {
		return $this->playerspausestate;
	}

	/**
	 * Update on Setting Changes
	 *
	 * @param Setting $setting
	 */
	public function updateSettings(Setting $setting) {
		if ($setting->belongsToClass($this)) {
			$this->updateManialinks();
			$this->PauseMatchIfNeeded();			
		}
	}

	/**
	 * Start match if needed
	 *
	 * @param Player $player or null
	 */
	private function PauseMatchIfNeeded($player = null) {
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_PAUSE_MODE) && $this->MatchManagerCore->getMatchStatus()) {
			$nbplayers = $this->maniaControl->getPlayerManager()->getPlayerCount();
			if ($nbplayers >= $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_PAUSE_NBPLAYERS)) {
				$nbpause = 0;
				foreach ($this->playerspausestate as $pausestate) {
					if ($pausestate == 1) {
						$nbpause++;
					}
				}
				if ($nbpause >= $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_PAUSE_NBPLAYERS)) {
					$this->playerspausestate = array();
					$this->closePauseWidget();
					$this->log('Pause requested by players');
					if (!$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_PAUSE_WAIT_END_ROUND)) {
						if ($this->maniaControl->getSettingManager()->getSettingValue($this->maniaControl, MatchManagerCore::SETTING_MATCH_PAUSE_DURATION) <= 0) {
							$this->maniaControl->getChat()->sendInformation($this->MatchManagerCore->getChatPrefix() . 'Ask the admins to resume the match');
						}
						$this->MatchManagerCore->setNadeoPause();
					} else {
						$this->maniaControl->getChat()->sendInformation($this->MatchManagerCore->getChatPrefix() . 'Pause will start at the end of this round');
						$this->LaunchPauseAtTheEnd = true;
					}
					return;
				}
			}

			$this->displayPauseWidgetIfNeeded($player);	
		}
	}

	/**
	 * Display (or not) the Pause Widget
	 *
	 * @param string $login
	 */
	public function displayPauseWidgetIfNeeded($player = null) {

		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_PAUSE_MODE) && $this->MatchManagerCore->getMatchStatus() && !$this->LaunchPauseAtTheEnd &&!$this->MatchManagerCore->getPauseStatus()) {
			if ($player == null) {
				$players = $this->maniaControl->getPlayerManager()->getPlayers(true,true);
			} else {
				$players = array($player);
			}

			foreach ($players as $player) {
				if ($player->isSpectator && isset($this->playerspausestate[$player->login])) {
					unset($this->playerspausestate[$player->login]);
					$this->closePauseWidget($player->login);
				} else if (!$player->isSpectator && !isset($this->playerspausestate[$player->login])) {
					$this->playerspausestate[$player->login] = 0;
					$this->maniaControl->getManialinkManager()->sendManialink($this->MLPauseNotAsked, $player->login, 0, false, false);
				} else if (!$player->isSpectator && isset($this->playerspausestate[$player->login])) {
					if ($this->playerspausestate[$player->login] == 1) {
						$this->maniaControl->getManialinkManager()->sendManialink($this->MLPauseAsked, $player->login, 0, false, false);
					} else {
						$this->maniaControl->getManialinkManager()->sendManialink($this->MLPauseNotAsked, $player->login, 0, false, false);
					}
				}
			}
		} else {
			$this->playerspausestate = array();
			$this->closePauseWidget();
		}
	}

	/**
	 * Close Pause Widget
	 *
	 * @param string $login
	 */
	public function closePauseWidget($login = null) {
		$this->maniaControl->getManialinkManager()->hideManialink(self::MLID_MATCH_PAUSE_WIDGET, $login);
	}

	/**
	 * Handle when a player disconnects
	 * 
	 * @param \ManiaControl\Players\Player $player
	*/
	public function handlePlayerDisconnect(Player $player) {
		if (isset($this->playerspausestate[$player->login])) {
			unset($this->playerspausestate[$player->login]);
			$this->closePauseWidget($player->login);
		}
	}

	/**
	 * Handle Pause state of the player
	 * 
	 * @param array			$callback
	 * @param \ManiaControl\Players\Player $player
	*/
	public function handlePause(array $callback, Player $player) {
		if (isset($this->playerspausestate[$player->login])) {
			if ($this->playerspausestate[$player->login] == 0) {
				$this->playerspausestate[$player->login] = 1;
				$this->maniaControl->getChat()->sendInformation($this->MatchManagerCore->getChatPrefix() . 'Player $<$ff0' . $player->nickname . '$> asks a pause');
			} elseif ($this->playerspausestate[$player->login] == 1) {
				$this->playerspausestate[$player->login] = 0;
				$this->maniaControl->getChat()->sendInformation($this->MatchManagerCore->getChatPrefix()  . 'Player $<$ff0' . $player->nickname . '$> no longer asks for a pause');
			}
			$this->PauseMatchIfNeeded($player);
		}
	}



	/**
	 * Command /pause for players
	 *
	 * @param array			$chatCallback
	 * @param \ManiaControl\Players\Player $player
	*/
	public function onCommandSetPausePlayer(array $chatCallback, Player $player) {
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_PAUSE_MODE) && ($this->MatchManagerCore->getMatchStatus())) {
			$this->handlePause($chatCallback, $player);
		}
	}

	public function handleBeginRoundCallback() {
		if ($this->LaunchPauseAtTheEnd) {
			if ($this->maniaControl->getSettingManager()->getSettingValue($this->maniaControl, MatchManagerCore::SETTING_MATCH_PAUSE_DURATION) <= 0) {
				$this->maniaControl->getChat()->sendInformation($this->MatchManagerCore->getChatPrefix() . 'Ask the admins to resume the match');
			}

			$this->LaunchPauseAtTheEnd = false;
			$this->MatchManagerCore->setNadeoPause();
		} else {
			$this->displayPauseWidgetIfNeeded();
		}
	}

	/**
	 * handleMatchManagerCoreCallback
	 * 
	 * @return void 
	 */
	public function handleMatchManagerCoreCallback() {
		$this->displayPauseWidgetIfNeeded();
	}

	/**
	 * Generate Manialinks variables
	*/
	private function updateManialinks() {
		$width			= 17;
		$height			= 6;
		$posX			= $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_PAUSE_POSX);
		$posY			= $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_PAUSE_POSY);
		$quadStyle		= $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadStyle();
		$quadSubstyle	= $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadSubstyle();

		$MLPauseAsked = new ManiaLink(self::MLID_MATCH_PAUSE_WIDGET);

		// mainframe
		$frameisPause = new Frame();
		$MLPauseAsked->addChild($frameisPause);
		$frameisPause->setSize($width, $height);
		$frameisPause->setPosition($posX, $posY);

		// Background Quad
		$backgroundQuadisPause = new Quad();
		$frameisPause->addChild($backgroundQuadisPause);
		$backgroundQuadisPause->setSize($width, $height);
		$backgroundQuadisPause->setStyles($quadStyle, $quadSubstyle);
		$backgroundQuadisPause->setAction(self::ACTION_PAUSE_BUTTON);

		$labelisPause = new Label_Text();
		$frameisPause->addChild($labelisPause);
		$labelisPause->setPosition(0, 1.75, 0.2);
		$labelisPause->setVerticalAlign($labelisPause::TOP);
		$labelisPause->setTextSize(2);
		$labelisPause->setTextFont("GameFontBlack");
		$labelisPause->setTextPrefix('$s');
		$labelisPause->setText("Pause?");
		$labelisPause->setTextColor('0f0');

		$MLPauseNotAsked = new ManiaLink(self::MLID_MATCH_PAUSE_WIDGET);

		// mainframe
		$frameisNotPause = new Frame();
		$MLPauseNotAsked->addChild($frameisNotPause);
		$frameisNotPause->setSize($width, $height);
		$frameisNotPause->setPosition($posX, $posY);

		// Background Quad
		$backgroundQuadisNotPause = new Quad();
		$frameisNotPause->addChild($backgroundQuadisNotPause);
		$backgroundQuadisNotPause->setSize($width, $height);
		$backgroundQuadisNotPause->setStyles($quadStyle, $quadSubstyle);
		$backgroundQuadisNotPause->setAction(self::ACTION_PAUSE_BUTTON);

		$labelisNotPause = new Label_Text();
		$frameisNotPause->addChild($labelisNotPause);
		$labelisNotPause->setPosition(0, 1.75, 0.2);
		$labelisNotPause->setVerticalAlign($labelisNotPause::TOP);
		$labelisNotPause->setTextSize(2);
		$labelisNotPause->setTextFont("GameFontBlack");
		$labelisNotPause->setTextPrefix('$s');
		$labelisNotPause->setText("Pause?");
		$labelisNotPause->setTextColor('f00');

		$this->MLPauseAsked = $MLPauseAsked;
		$this->MLPauseNotAsked = $MLPauseNotAsked;
	}
}