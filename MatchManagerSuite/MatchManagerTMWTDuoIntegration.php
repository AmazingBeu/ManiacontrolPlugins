<?php

namespace MatchManagerSuite;

use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Callbacks\TimerListener;
use ManiaControl\Commands\CommandListener;
use ManiaControl\Logger;
use ManiaControl\ManiaControl;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;
use ManiaControl\Players\Player;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Plugins\PluginManager;
use ManiaControl\Plugins\PluginMenu;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;
use ManiaControl\Utils\WebReader;


if (!class_exists('MatchManagerSuite\MatchManagerCore')) {
	$this->maniaControl->getChat()->sendErrorToAdmins('MatchManager Core is required to use one of MatchManager plugin. Install it and restart Maniacontrol');
	Logger::logError('MatchManager Core is required to use one of MatchManager plugin. Install it and restart Maniacontrol');
	return false;
}
use MatchManagerSuite\MatchManagerCore;


/**
 * MatchManager TMWT Duo Integration
 *
 * @author		Beu (based on MatchManagerWidget by jonthekiller)
 * @license		http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class MatchManagerTMWTDuoIntegration implements CallbackListener, ManialinkPageAnswerListener, CommandListener, TimerListener, Plugin {
	/*
	 * Constants
	 */
	const PLUGIN_ID											= 211;
	const PLUGIN_VERSION									= 1.0;
	const PLUGIN_NAME										= 'MatchManager TMWT Duo Integration';
	const PLUGIN_AUTHOR										= 'Beu';

	// Other MatchManager plugin
	const MATCHMANAGERCORE_PLUGIN							= 'MatchManagerSuite\MatchManagerCore';
	const MATCHMANAGERADMINUI_PLUGIN						= 'MatchManagerSuite\MatchManagerAdminUI';

	// Actions
	const ML_ACTION_OPENSETTINGS 							= 'MatchManagerSuite\MatchManagerTMWTDuoIntegration.OpenSettings';

	const XMLRPC_CALLBACK_PICKANDBANCOMPLETE				= 'PickBan.Complete';
	const XMLRPC_CALLBACK_STARTMAP							= 'Maniaplanet.StartMap_Start';

	const XMLRPC_METHOD_STARTPICKANDBAN						= 'PickBan.Start';
	const XMLRPC_METHOD_ADDPLAYER							= 'Club.Match.AddPlayer';
	const XMLRPC_METHOD_REMOVEPLAYER						= 'Club.Match.RemovePlayer';
	const XMLRPC_METHOD_MATCHSTARTED						= 'Club.Match.Start';
	const XMLRPC_METHOD_MATCHCOMPLETED						= 'Club.Match.Completed';

	const SETTING_TEAM1 									= 'Team 1 Id';
	const SETTING_TEAM2 									= 'Team 2 Id';
	const SETTING_PICKANDBAN_ENABLE 						= 'Enable Pick & Ban';
	const SETTING_PICKANDBAN_STEPCONFIG 					= 'Pick & Ban: Step config';
	const SETTING_PICKANDBAN_STEPDURATION 					= 'Pick & Ban: Step duration';
	const SETTING_PICKANDBAN_RESULTDURATION 				= 'Pick & Ban: Result duration';

	const STATE_NOTHING = 0;
	const STATE_PRESETTING = 1;
	const STATE_PREMATCH = 2;
	const STATE_MATCH = 3;

	/*
	 * Private properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl 			= null;
	private $MatchManagerCore		= null;
	private $state = 0;

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
		return 'Integration of TMWT duo teams & pick and ban for MatchManager';
	}

	/**
	 * @param \ManiaControl\ManiaControl $maniaControl
	 * @return bool
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;
		/** @var MatchManagerCore */
		$this->MatchManagerCore = $this->maniaControl->getPluginManager()->getPlugin(self::MATCHMANAGERCORE_PLUGIN);

		if ($this->MatchManagerCore == Null) {
			throw new \Exception('MatchManager Core is needed to use ' . self::PLUGIN_NAME);
		}

		// Settings
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_TEAM1, '', '', 10);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_TEAM2, '', '', 10);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_PICKANDBAN_ENABLE, false, '', 20);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_PICKANDBAN_STEPCONFIG, '', 'Similar syntax as the ofiicial Competition Tool. e.g: b:1,b:0,p:0,p:1,p:1,p:0,b:0,b:1,p:r');
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_PICKANDBAN_STEPDURATION, 60000, 'Each step duration in ms', 110);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_PICKANDBAN_RESULTDURATION, 10000, 'result duration in ms', 120);

		// Callbacks
		$this->maniaControl->getManialinkManager()->registerManialinkPageAnswerListener(self::ML_ACTION_OPENSETTINGS, $this, 'handleActionOpenSettings');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::AFTERINIT, $this, 'handleAfterInit');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PluginManager::CB_PLUGIN_LOADED, $this, 'handlePluginLoaded');
		$this->maniaControl->getCallbackManager()->registerCallbackListener($this->MatchManagerCore::CB_MATCHMANAGER_STARTMATCH, $this, 'handleMatchManagerStartMatch');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::MP_STARTMATCHSTART, $this, 'handleStartMatchStartCallback');
		$this->maniaControl->getCallbackManager()->registerScriptCallbackListener(self::XMLRPC_CALLBACK_STARTMAP, $this, 'handleStartMapStartCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener($this->MatchManagerCore::CB_MATCHMANAGER_STOPMATCH, $this, 'handleMatchManagerEndMatch');
		$this->maniaControl->getCallbackManager()->registerCallbackListener($this->MatchManagerCore::CB_MATCHMANAGER_ENDMATCH, $this, 'handleMatchManagerEndMatch');
		$this->maniaControl->getCallbackManager()->registerScriptCallbackListener(self::XMLRPC_CALLBACK_PICKANDBANCOMPLETE, $this, 'handlePickAndBanComplete');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'handleSettingChanged');

		$this->MatchManagerCore->addCanStartFunction($this, 'canStartMatch'); 
		$this->updateAdminUIMenuItems();
	}

	/**
	 * handle Plugin Loaded
	 * 
	 * @param string $pluginClass 
	 */
	public function handleAfterInit() {
		$this->updateAdminUIMenuItems();
	}

	/**
	 * handle Plugin Loaded
	 * 
	 * @param string $pluginClass 
	 */
	public function handlePluginLoaded(string $pluginClass) {
		if ($pluginClass === self::MATCHMANAGERADMINUI_PLUGIN) {
			$this->updateAdminUIMenuItems();
		}
	}

		/**
	 * Add items in AdminUI plugin
	 */
	public function updateAdminUIMenuItems() {
		/** @var \MatchManagerSuite\MatchManagerAdminUI|null */
		$adminUIPlugin = $this->maniaControl->getPluginManager()->getPlugin(self::MATCHMANAGERADMINUI_PLUGIN);
		if ($adminUIPlugin === null) return;

		$adminUIPlugin->removeMenuItem(self::ML_ACTION_OPENSETTINGS);

		$menuItem = new \MatchManagerSuite\MatchManagerAdminUI_MenuItem();
		$menuItem->setActionId(self::ML_ACTION_OPENSETTINGS)
			->setOrder(50)
			->setImageUrl('https://files.virtit.fr/TrackMania/Images/Others/TMWT_Logo.dds')
			->setSize(4.5)
			->setDescription('Open TMWT Integration Settings');
		$adminUIPlugin->addMenuItem($menuItem);
	}

	/**
	 * handle Open settings manialink action
	 * 
	 * @param array $callback 
	 * @param Player $player 
	 */
	public function handleActionOpenSettings(array $callback, Player $player) {
		if ($player->authLevel <= 0) return;

		$pluginMenu = $this->maniaControl->getPluginManager()->getPluginMenu();
		if (defined("\ManiaControl\ManiaControl::ISTRACKMANIACONTROL")) {
			$player->setCache($pluginMenu, PluginMenu::CACHE_SETTING_CLASS, "PluginMenu.Settings." . self::class);
		} else {
			$player->setCache($pluginMenu, PluginMenu::CACHE_SETTING_CLASS, self::class);
		}
		$this->maniaControl->getConfigurator()->showMenu($player, $pluginMenu);
	}

	/**
	 * Callback function to check if everything is ok before starting the match
	 * 
	 * @return bool 
	 */
	public function canStartMatch() {
		if ($this->maniaControl->getSettingManager()->getSettingValue($this->MatchManagerCore, 'S_TeamsUrl') === '') {
			$this->maniaControl->getChat()->sendErrorToAdmins($this->MatchManagerCore->getChatPrefix() . " S_TeamsUrl must be defined");
			return false;
		}
		if (
			$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_TEAM1) === '' ||
			$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_TEAM2) === ''
		) {
			$this->maniaControl->getChat()->sendErrorToAdmins($this->MatchManagerCore->getChatPrefix() . " Team Id must be defined");
			return false;
		}

		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_PICKANDBAN_ENABLE)) {
			if ($this->maniaControl->getSettingManager()->getSettingValue($this->MatchManagerCore, $this->MatchManagerCore::SETTING_MATCH_SETTINGS_MODE) !== "All from the plugin") {
				$this->maniaControl->getChat()->sendErrorToAdmins('TMWT Pick and bans are only supported in Match Manager Core "All from the plugin" mode');
				return false;
			}
		}

		return true;
	}

	/**
	 * handle MatchManagerCore StartMatch callback
	 * 
	 * @return void 
	 */
	public function handleMatchManagerStartMatch() {
		$this->state = self::STATE_PRESETTING;

		$setting = $this->maniaControl->getSettingManager()->getSettingObject($this->MatchManagerCore, 'S_IsMatchmaking');
		if ($setting !== null && !$setting->value) {
			$setting->value = true;
			$this->maniaControl->getSettingManager()->saveSetting($setting);
			Logger::logWarning('Remplacing S_IsMatchmaking setting value in MatchManagerCore for TMWT integration');
			$this->maniaControl->getChat()->sendErrorToAdmins('Remplacing S_IsMatchmaking setting value in MatchManagerCore for TMWT integration');
		}

		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_PICKANDBAN_ENABLE)) {
			$setting = $this->maniaControl->getSettingManager()->getSettingObject($this->MatchManagerCore, 'S_PickAndBan_Enable');
			if ($setting !== null && !$setting->value) {
				$setting->value = true;
				$this->maniaControl->getSettingManager()->saveSetting($setting);
				Logger::logWarning('Remplacing S_PickAndBan_Enable setting value in MatchManagerCore for TMWT integration');
				$this->maniaControl->getChat()->sendErrorToAdmins('Remplacing S_PickAndBan_Enable setting value in MatchManagerCore for TMWT integration');
			}
		}
	}

	/**
	 * handle MatchManagerCore StopMatch & EndMatch callback
	 * 
	 * @return void 
	 */
	public function handleMatchManagerEndMatch() {
		$this->state = self::STATE_NOTHING;
	}

	/**
	 * handle StartMatch_Start script callback
	 * 
	 * @return void 
	 */
	public function handleStartMatchStartCallback() {
		if ($this->MatchManagerCore->getMatchStatus() && $this->state === self::STATE_PREMATCH) {
			// reset match state in just in case
			$this->maniaControl->getClient()->triggerModeScriptEvent(self::XMLRPC_METHOD_MATCHCOMPLETED, []);
		}
	}

	/**
	 * handle StartMap_Start script callback
	 * 
	 * @return void 
	 */
	public function handleStartMapStartCallback() {
		if (!$this->MatchManagerCore->getMatchStatus()) return;

		if ($this->state === self::STATE_PRESETTING) {
			$this->state = self::STATE_PREMATCH;
			$this->maniaControl->getClient()->setModeScriptSettings(['S_IsMatchmaking' => true], false);
			$this->maniaControl->getClient()->restartMap();
		} else if ($this->state === self::STATE_PREMATCH) {

			$teamsUrl = $this->maniaControl->getSettingManager()->getSettingValue($this->MatchManagerCore, 'S_TeamsUrl');
			if ($teamsUrl !== '') {
				$response = WebReader::getUrl($teamsUrl);
				$content  = $response->getContent();
				$json = json_decode($content);
				if ($json !== null) {
					$team1 = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_TEAM1);
					$team2 = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_TEAM2);

					foreach ($json as $team) {
						if ($team->Id === $team1) {
							$team1 = null;
							foreach ($team->Players as $player) {
								Logger::log($player->AccountId ." added to team 1");
								$this->maniaControl->getClient()->triggerModeScriptEvent(self::XMLRPC_METHOD_ADDPLAYER, [$player->AccountId, "1"], true);
							}
						}
						if ($team->Id === $team2) {
							$team2 = null;
							foreach ($team->Players as $player) {
								Logger::log($player->AccountId ." added to team 2");
								$this->maniaControl->getClient()->triggerModeScriptEvent(self::XMLRPC_METHOD_ADDPLAYER, [$player->AccountId, "2"], true);
							}
						}

						if ($team1 === null && $team2 === null) break;
					}

					if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_PICKANDBAN_ENABLE)) {
						Logger::log('Starting Pick & ban in 10 seconds');
						$this->maniaControl->getChat()->sendSuccess('Starting pick & ban in 10 seconds');
						$this->maniaControl->getTimerManager()->registerOneTimeListening($this, function () {
						$payload = [
							'stepDuration' => $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_PICKANDBAN_STEPDURATION),
							'resultDuration' => $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_PICKANDBAN_RESULTDURATION),
							'steps' => []
						];

						$stepConfig = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_PICKANDBAN_STEPCONFIG);
						foreach (explode(',', $stepConfig) as $stepRaw) {
							$stepPayload = [];
							$step = explode(':', $stepRaw);

							if ($step[1] === 'r') {
								$stepPayload['action'] = 'randompick';
							}  else {
								if ($step[0] === 'p') {
									$stepPayload['action'] = 'pick';
								} else if ($step[0] === 'b') {
									$stepPayload['action'] = 'ban';
								}

								$stepPayload['team'] = $step[1] + 1;
							}

							$payload['steps'][] = $stepPayload;
						}

						$json = json_encode($payload);

						Logger::log('Starting Pick & ban: '. $json);
						$this->maniaControl->getClient()->triggerModeScriptEvent(self::XMLRPC_METHOD_STARTPICKANDBAN, [$json], true);
						}, 5000);
					} else {
						$this->state = self::STATE_MATCH;
						Logger::log('Sending match start callback');
						$this->maniaControl->getClient()->triggerModeScriptEvent(self::XMLRPC_METHOD_MATCHSTARTED, [], true);
					}
				}
			}
		}
	}

	/**
	 * handle Pick & Ban completed script callback
	 * 
	 * @param array $structure 
	 * @return void 
	 */
	public function handlePickAndBanComplete(array $structure) {
		if (!$this->MatchManagerCore->getMatchStatus()) return;
		Logger::log('Received picks: '. $structure[1][0]);

		$this->maniaControl->getTimerManager()->registerOneTimeListening($this, function () use ($structure) {
			try {
				$json = json_decode($structure[1][0]);
	
				$mapUids = array_column($json->playlist, 'uid');
				$mapList = [];
				foreach ($this->maniaControl->getMapManager()->getMaps() as $map) {
					$index = array_search($map->uid, $mapUids);
	
					if ($index === false) {
						$this->maniaControl->getClient()->removeMap($map->fileName);
					} else {
						$mapList[$index] = $map->fileName;
					}
				}
	
				if (count($mapUids) !== count($mapList)) {
					Logger::logError("Missing maps: ". implode(' ', array_diff($mapUids, $mapList)));
				}
	
				ksort($mapList);

				$this->maniaControl->getClient()->chooseNextMapList(array_values($mapList));
			} catch (\Throwable $th) {
				Logger::logError("Can't apply map list: ". $th->getMessage());
				$this->maniaControl->getChat()->sendError("Can't apply map list: ". $th->getMessage());
			}

			$this->state = self::STATE_MATCH;
			Logger::log('Sending match start callback');
			$this->maniaControl->getClient()->triggerModeScriptEvent(self::XMLRPC_METHOD_MATCHSTARTED, [], true);
			$this->maniaControl->getMapManager()->getMapActions()->skipMap();
		}, $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_PICKANDBAN_RESULTDURATION));
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
	 * handle Setting Changed callback
	 * 
	 * @param Setting $setting 
	 * @return void 
	 */
	public function handleSettingChanged(Setting $setting) {
		if ($setting->setting === self::SETTING_PICKANDBAN_ENABLE || $setting->setting === $this->MatchManagerCore::SETTING_MATCH_SETTINGS_MODE) {
			if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_PICKANDBAN_ENABLE)) {
				if ($this->maniaControl->getSettingManager()->getSettingValue($this->MatchManagerCore, $this->MatchManagerCore::SETTING_MATCH_SETTINGS_MODE) !== "All from the plugin") {
					$this->maniaControl->getChat()->sendErrorToAdmins('TMWT Pick and bans are only supported in Match Manager Core "All from the plugin" mode');
				}
			}
		}
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {
		$this->MatchManagerCore->removeCanStartFunction($this, 'canStartMatch'); 
		/** @var \MatchManagerSuite\MatchManagerAdminUI|null */
		$adminUIPlugin = $this->maniaControl->getPluginManager()->getPlugin(self::MATCHMANAGERADMINUI_PLUGIN);
		if ($adminUIPlugin !== null) {
			$adminUIPlugin->removeMenuItem(self::ML_ACTION_OPENSETTINGS);
		}
	}
}
