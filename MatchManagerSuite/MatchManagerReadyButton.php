<?php

namespace MatchManagerSuite;

use FML\Controls\Frame;
use FML\Controls\Labels\Label_Text;
use FML\Controls\Quad;
use FML\ManiaLink;
use ManiaControl\Callbacks\CallbackListener;
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
use Maniaplanet\DedicatedServer\InvalidArgumentException;

if (!class_exists('MatchManagerSuite\MatchManagerCore')) {
	$this->maniaControl->getChat()->sendErrorToAdmins('MatchManager Core is required to use one of MatchManager plugin. Install it and restart Maniacontrol');
	Logger::logError('MatchManager Core is required to use one of MatchManager plugin. Install it and restart Maniacontrol');
	return false;
}


/**
 * MatchManager Ready Button
 *
 * @author		Beu
 * @license		http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class MatchManagerReadyButton implements ManialinkPageAnswerListener, CommandListener, CallbackListener, Plugin {
	/*
	 * Constants
	 */
	const PLUGIN_ID											= 158;
	const PLUGIN_VERSION									= 1.4;
	const PLUGIN_NAME										= 'MatchManager Ready Button';
	const PLUGIN_AUTHOR										= 'Beu';

	// MatchManagerWidget Properties
	const MATCHMANAGERCORE_PLUGIN							= 'MatchManagerSuite\MatchManagerCore';

	const ACTION_READY										= 'ReadyButton.Action';
	const MLID_MATCH_READY_WIDGET							= 'Ready ButtonWidget';
	const SETTING_MATCH_ENABLE_PLUGIN						= 'Enable plugin';
	const SETTING_MATCH_DISABLE_AFTER_MATCH					= 'Disable widget after the match';
	const SETTING_MATCH_READY_NBPLAYERS						= 'Minimal number of players before start';
	const SETTING_MATCH_READY_POSX							= 'Position: X';
	const SETTING_MATCH_READY_POSY							= 'Position: Y';

	/*
	 * Private properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl 			= null;
	private $MatchManagerCore		= null;
	private $chatprefix				= '$<$fc3$w🏆$m$> '; // Would like to create a setting but MC database doesn't support utf8mb4

	private $playersreadystate		= array();

	private $MLisReady				= null;
	private $MLisNotReady			= null;


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
		return 'Add a button for players to get ready and start the match automatically';
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

		$this->maniaControl->getCommandManager()->registerCommandListener('ready', $this, 'onCommandSetReadyPlayer', false, 'Change status to Ready.');

		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_ENABLE_PLUGIN, false, "Activate Ready widget");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_DISABLE_AFTER_MATCH, false, "Disable the widget after the match");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_READY_NBPLAYERS, 2, "Minimal number of players to start a match if all are ready");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_READY_POSX, 152.5, "Position of the Ready widget (on X axis)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_READY_POSY, 40, "Position of the Ready widget (on Y axis)");

		$this->maniaControl->getCallbackManager()->registerCallbackListener(PluginManager::CB_PLUGIN_UNLOADED, $this, 'handlePluginUnloaded');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');

		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERDISCONNECT, $this, 'handlePlayerDisconnect');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERINFOCHANGED, $this, 'displayReadyWidgetIfNeeded');

		$this->maniaControl->getCallbackManager()->registerCallbackListener(MatchManagerCore::CB_MATCHMANAGER_STARTMATCH, $this, 'handleMatchManagerCoreCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(MatchManagerCore::CB_MATCHMANAGER_ENDMATCH, $this, 'handleMatchManagerCoreCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(MatchManagerCore::CB_MATCHMANAGER_STOPMATCH, $this, 'handleMatchManagerCoreCallback');

		// Register ManiaLink Pages
		$this->maniaControl->getManialinkManager()->registerManialinkPageAnswerListener(self::ACTION_READY, $this, 'handleReady');

		$this->updateManialinks();
		$this->displayReadyWidgetIfNeeded();

		return true;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {
		$this->closeReadyWidget();
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
			$this->maniaControl->getPluginManager()->deactivatePlugin((get_class()));
		}
	}

	/**
	 * Generate Manialinks variables
	*/
	public function getPlayersReadyState() {
		return $this->playersreadystate;
	}

	/**
	 * Update on Setting Changes
	 *
	 * @param Setting $setting
	 */
	public function updateSettings(Setting $setting) {
		if ($setting->belongsToClass($this)) {
			$this->updateManialinks();
			$this->StartMatchIfNeeded();
		}
	}

	/**
	 * Start match if needed
	 *
	 * @param Player $player or null
	 */
	private function StartMatchIfNeeded($player = null) {
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_ENABLE_PLUGIN) && !$this->MatchManagerCore->getMatchStatus()) {
			$nbplayers = $this->maniaControl->getPlayerManager()->getPlayerCount();
			if ($nbplayers >= $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_NBPLAYERS)) {
				$nbready = 0;
				foreach ($this->playersreadystate as $readystate) {
					if ($readystate == 1) {
						$nbready++;
					}
				}
				if ($nbready >= $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_NBPLAYERS)) {
					$this->playersreadystate = array();
					$this->closeReadyWidget();
					Logger::log('Start Match via Ready Button');
					$this->MatchManagerCore->MatchStart();
					return;
				}
			}
		}
		$this->displayReadyWidgetIfNeeded($player);
	}

	/**
	 * Display (or not) the Ready Widget
	 *
	 * @param string $login
	 */
	public function displayReadyWidgetIfNeeded($player = null) {
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_ENABLE_PLUGIN) && !$this->MatchManagerCore->getMatchStatus()) {
			if ($player == null) {
				$players = $this->maniaControl->getPlayerManager()->getPlayers(true,true);
			} else {
				$players = array($player);
			}

			foreach ($players as $player) {
				if ($player->isSpectator && isset($this->playersreadystate[$player->login])) {
					unset($this->playersreadystate[$player->login]);
					$this->closeReadyWidget($player->login);
				} else if (!$player->isSpectator && !isset($this->playersreadystate[$player->login])) {
					$this->playersreadystate[$player->login] = 0;
					$this->maniaControl->getManialinkManager()->sendManialink($this->MLisNotReady, $player->login, ToggleUIFeature: false);
					$this->maniaControl->getChat()->sendSuccess($this->chatprefix . 'You can now set you $<$f00Ready$> by clicking on the button', $player);
				} else if (!$player->isSpectator && isset($this->playersreadystate[$player->login])) {
					if ($this->playersreadystate[$player->login] == 1) {
						$this->maniaControl->getManialinkManager()->sendManialink($this->MLisReady, $player->login, ToggleUIFeature: false);
					} else {
						$this->maniaControl->getManialinkManager()->sendManialink($this->MLisNotReady, $player->login, ToggleUIFeature: false);
					}
				}
			}
		} else if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_ENABLE_PLUGIN) && $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_DISABLE_AFTER_MATCH) && $this->MatchManagerCore->getMatchStatus()) {
			$this->maniaControl->getSettingManager()->setSetting($this, self::SETTING_MATCH_ENABLE_PLUGIN, false);
		} else {
			$this->playersreadystate = array();
			$this->closeReadyWidget();
		}
	}

	/**
	 * Close Ready Widget
	 *
	 * @param string $login
	 */
	public function closeReadyWidget($login = null) {
		$this->maniaControl->getManialinkManager()->hideManialink(self::MLID_MATCH_READY_WIDGET, $login);
	}

	/**
	 * Handle when a player disconnects
	 *
	 * @param \ManiaControl\Players\Player $player
	*/
	public function handlePlayerDisconnect(Player $player) {
		if (isset($this->playersreadystate[$player->login])) {
			unset($this->playersreadystate[$player->login]);
			$this->closeReadyWidget($player->login);
		}
	}

	/**
	 * Handle Ready state of the player
	 *
	 * @param array			$callback
	 * @param \ManiaControl\Players\Player $player
	*/
	public function handleReady(array $callback, Player $player) {
		if (isset($this->playersreadystate[$player->login])) {
			if ($this->playersreadystate[$player->login] == 0) {
				$this->playersreadystate[$player->login] = 1;
				$this->maniaControl->getChat()->sendInformation($this->chatprefix . 'Player $<$ff0' . $player->nickname . '$> now is $<$z$0f0ready$>');
			} elseif ($this->playersreadystate[$player->login] == 1) {
				$this->playersreadystate[$player->login] = 0;
				$this->maniaControl->getChat()->sendInformation($this->chatprefix . 'Player $<$ff0' . $player->nickname . '$> now is $<$z$f00not ready$>');
			}
			$this->StartMatchIfNeeded($player);
		}
	}

	/**
	 * Command /ready for players
	 *
	 * @param array			$chatCallback
	 * @param \ManiaControl\Players\Player $player
	*/
	public function onCommandSetReadyPlayer(array $chatCallback, Player $player) {
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_ENABLE_PLUGIN) && (!$this->MatchManagerCore->getMatchStatus())) {
			$this->handleReady($chatCallback, $player);
		}
	}

	/**
	 * handleMatchManagerCoreCallback
	 * 
	 * @return void 
	 */
	public function handleMatchManagerCoreCallback() {
		$this->displayReadyWidgetIfNeeded();
	}

	/**
	 * Generate Manialinks variables
	*/
	private function updateManialinks() {
		$width			= 17;
		$height			= 6;
		$posX			= $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_POSX);
		$posY			= $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_POSY);
		$quadStyle		= $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadStyle();
		$quadSubstyle	= $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadSubstyle();

		$MLisReady = new ManiaLink(self::MLID_MATCH_READY_WIDGET);

		// mainframe
		$frameisReady = new Frame();
		$MLisReady->addChild($frameisReady);
		$frameisReady->setSize($width, $height);
		$frameisReady->setPosition($posX, $posY);

		// Background Quad
		$backgroundQuadisReady = new Quad();
		$frameisReady->addChild($backgroundQuadisReady);
		$backgroundQuadisReady->setSize($width, $height);
		$backgroundQuadisReady->setStyles($quadStyle, $quadSubstyle);
		$backgroundQuadisReady->setAction(self::ACTION_READY);

		$labelisReady = new Label_Text();
		$frameisReady->addChild($labelisReady);
		$labelisReady->setPosition(0, 1.75, 0.2);
		$labelisReady->setVerticalAlign($labelisReady::TOP);
		$labelisReady->setTextSize(2);
		$labelisReady->setTextFont("GameFontBlack");
		$labelisReady->setTextPrefix('$s');
		$labelisReady->setText("Ready?");
		$labelisReady->setTextColor('0f0');

		$MLisNotReady = new ManiaLink(self::MLID_MATCH_READY_WIDGET);

		// mainframe
		$frameisNotReady = new Frame();
		$MLisNotReady->addChild($frameisNotReady);
		$frameisNotReady->setSize($width, $height);
		$frameisNotReady->setPosition($posX, $posY);

		// Background Quad
		$backgroundQuadisNotReady = new Quad();
		$frameisNotReady->addChild($backgroundQuadisNotReady);
		$backgroundQuadisNotReady->setSize($width, $height);
		$backgroundQuadisNotReady->setStyles($quadStyle, $quadSubstyle);
		$backgroundQuadisNotReady->setAction(self::ACTION_READY);

		$labelisNotReady = new Label_Text();
		$frameisNotReady->addChild($labelisNotReady);
		$labelisNotReady->setPosition(0, 1.75, 0.2);
		$labelisNotReady->setVerticalAlign($labelisNotReady::TOP);
		$labelisNotReady->setTextSize(2);
		$labelisNotReady->setTextFont("GameFontBlack");
		$labelisNotReady->setTextPrefix('$s');
		$labelisNotReady->setText("Ready?");
		$labelisNotReady->setTextColor('f00');

		$this->MLisReady = $MLisReady;
		$this->MLisNotReady = $MLisNotReady;
	}
}