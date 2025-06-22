<?php
namespace MatchManagerSuite;

use ManiaControl\ManiaControl;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Logger;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Callbacks\Structures\ManiaPlanet\StartEndStructure;
use ManiaControl\Callbacks\Structures\TrackMania\OnScoresStructure;
use ManiaControl\Files\AsyncHttpRequest;

use ManiaControl\Callbacks\Structures\TrackMania\OnWayPointEventStructure;
use ManiaControl\Callbacks\TimerListener;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;
use ManiaControl\Players\Player;
use ManiaControl\Plugins\PluginManager;
use ManiaControl\Plugins\PluginMenu;

if (!class_exists('MatchManagerSuite\MatchManagerCore')) {
	$this->maniaControl->getChat()->sendErrorToAdmins('MatchManager Core is required to use MatchManagerECircuitMania plugin. Install it and restart Maniacontrol');
	Logger::logError('MatchManager Core is required to use MatchManagerECircuitMania plugin. Install it and restart Maniacontrol');
	return false;
}

/**
 * MatchManagerECircuitMania
 *
 * @author	Beu
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class MatchManagerECircuitMania implements CallbackListener, ManialinkPageAnswerListener, TimerListener, Plugin {
	/*
	* Constants
	*/
	const PLUGIN_ID			= 213;
	const PLUGIN_VERSION	= 1.1;
	const PLUGIN_NAME		= 'MatchManager eCircuitMania';
	const PLUGIN_AUTHOR		= 'Beu';

	const LOG_PREFIX					= '[MatchManagerECircuitMania] ';

	const MATCHMANAGERCORE_PLUGIN		= 'MatchManagerSuite\MatchManagerCore';
	const MATCHMANAGERADMINUI_PLUGIN	= 'MatchManagerSuite\MatchManagerAdminUI';

	const ML_ACTION_OPENSETTINGS		= 'MatchManagerSuite\MatchManagerECircuitMania.OpenSettings';
	
	const SETTING_URL					= 'API URL';
	const SETTING_MATCH_API_KEY			= 'Match API Key';
	const SETTING_WITHMATCHMANAGER		= 'Only send data when a Match Manager match is running';

	const CB_STARTMAP					= 'Maniaplanet.StartMap_Start';

	/*
	 * Private properties
	 */
	private ManiaControl $maniaControl;
	private \MatchManagerSuite\MatchManagerCore $MatchManagerCore;
	private int $trackNum = 0;
	private int $roundNum = 0;

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
		return "Plugin to send match data to eCM";
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		if ($this->maniaControl->getPluginManager()->getSavedPluginStatus(self::MATCHMANAGERCORE_PLUGIN)) {
			$this->maniaControl->getTimerManager()->registerOneTimeListening($this, function () {
				$this->MatchManagerCore = $this->maniaControl->getPluginManager()->getPlugin(self::MATCHMANAGERCORE_PLUGIN);
				if ($this->MatchManagerCore === null) {
					$this->maniaControl->getChat()->sendErrorToAdmins('MatchManager Core is needed to use ' . self::PLUGIN_NAME . ' plugin.');
					$this->maniaControl->getPluginManager()->deactivatePlugin((get_class($this)));
					return;
				}

				$this->maniaControl->getCallbackManager()->registerCallbackListener(PluginManager::CB_PLUGIN_LOADED, $this, 'handlePluginLoaded');
				$this->maniaControl->getCallbackManager()->registerCallbackListener(PluginManager::CB_PLUGIN_UNLOADED, $this, 'handlePluginUnloaded');
				$this->maniaControl->getCallbackManager()->registerScriptCallbackListener(self::CB_STARTMAP, $this, 'handleStartMap');
				$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::MP_STARTROUNDSTART, $this, 'handleStartRound');
				$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_ONWAYPOINT, $this, 'handleOnWaypoint');
				$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_SCORES, $this, 'handleTrackmaniaScores');
				$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::MP_ENDROUNDEND, $this, 'handleEndRound');

				$this->updateAdminUIMenuItems();
			}, 1);
		} else {
			throw new \Exception('MatchManager Core is needed to use ' . self::PLUGIN_NAME);
		}

		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_API_KEY, "", "", 5);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_URL, "https://us-central1-fantasy-trackmania.cloudfunctions.net", "", 10);

		$this->maniaControl->getManialinkManager()->registerManialinkPageAnswerListener(self::ML_ACTION_OPENSETTINGS, $this, 'handleActionOpenSettings');
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
	 * Add items in AdminUI plugin
	 */
	public function updateAdminUIMenuItems() {
		/** @var \MatchManagerSuite\MatchManagerAdminUI|null */
		$adminUIPlugin = $this->maniaControl->getPluginManager()->getPlugin(self::MATCHMANAGERADMINUI_PLUGIN);
		if ($adminUIPlugin === null) return;

		$adminUIPlugin->removeMenuItem(self::ML_ACTION_OPENSETTINGS);

		$menuItem = new \MatchManagerSuite\MatchManagerAdminUI_MenuItem();
		$menuItem->setActionId(self::ML_ACTION_OPENSETTINGS)
			->setOrder(60)
			->setImageUrl('https://ecircuitmania.com/favicon.png')
			->setSize(4.)
			->setDescription('Open eCM Settings');
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

	public function handleStartMap(array $structure) {
		if (!$this->MatchManagerCore->getMatchIsRunning()) return;
		$data = json_decode($structure[1][0]);

		$this->trackNum = $data->valid;
		$this->roundNum = 0;
	}

	public function handleStartRound(StartEndStructure $structure) {
		if (!$this->MatchManagerCore->getMatchIsRunning()) return;
		$this->roundNum = $structure->getValidRoundCount();
	}

	public function handleOnWaypoint(OnWayPointEventStructure $structure) {
		if (!$this->MatchManagerCore->getMatchIsRunning()) return;
		if (!$structure->getIsEndRace()) return;
		if ($this->roundNum <= 0) return; // probably during the WU

		$mapuid = "";
		$map = $this->maniaControl->getMapManager()->getCurrentMap();
		if ($map !== null) {
			$mapuid = $map->uid;
		}
		
		$payload = json_encode([
			"ubisoftUid" => $structure->getPlayer()->getAccountId(),
			"finishTime" => $structure->getRaceTime(),
			"mapId" => $mapuid,
			"trackNum" => $this->trackNum,
			"roundNum" => $this->roundNum
		]);

		$request = $this->getAPIRequest("/match-addRoundTime");
		if ($request !== null) {
			$request->setContent($payload)->setCallable(function ($content, $error, $headers) use ($payload) {
				if ($content !== "Created" || $error !== null) {
					$this->logError("Error on the 'addRoundTime' request. answer: " . $content . " / error: " . $error . " / payload: " . $payload);
				}
			})->postData();
		}
	}

	public function handleTrackmaniaScores(OnScoresStructure $structure) {
		if (!$this->MatchManagerCore->getMatchIsRunning()) return;
		if ($structure->getSection() !== "PreEndRound") return;

		$scores = [];
		foreach ($structure->getPlayerScores() as $playerscore) {
			$scores[] = $playerscore;
		}

		/** @var \ManiaControl\Callbacks\Structures\TrackMania\Models\PlayerScore[] $scores */
		usort($scores, function ($a, $b) {
			if ($a->getPrevRaceTime() === -1 && $b->getPrevRaceTime() === -1) {
				return $b->getRoundPoints() - $a->getRoundPoints();
			}

			if ($a->getPrevRaceTime() === -1) return 1;
			if ($b->getPrevRaceTime() === -1) return -1;

			if ($a->getPrevRaceTime() === $b->getPrevRaceTime()) {
				$acheckpoints = $a->getPrevRaceCheckpoints();
				$bcheckpoints = $b->getPrevRaceCheckpoints();

				while (end($acheckpoints) === end($bcheckpoints)) {
					if (count($acheckpoints) === 0 || count($bcheckpoints) === 0) return 0;
					array_pop($acheckpoints);
					array_pop($bcheckpoints);
				}
				return end($acheckpoints) - end($bcheckpoints);
			}
			return $a->getPrevRaceTime() - $b->getPrevRaceTime();
		});

		$players = [];
		$rank = 1;
		foreach ($scores as $playerscore) {
			/** @var \ManiaControl\Callbacks\Structures\TrackMania\Models\PlayerScore $playerscore */
			if ($playerscore->getPlayer()->isSpectator) continue;

			$players[] = [
				"ubisoftUid" => $playerscore->getPlayer()->getAccountId(),
				"finishTime" => $playerscore->getPrevRaceTime(),
				"position" => $rank
			];
			$rank++;
		}

		$mapuid = "";
		$map = $this->maniaControl->getMapManager()->getCurrentMap();
		if ($map !== null) {
			$mapuid = $map->uid;
		}

		$payload = json_encode([
			"players" => $players,
			"mapId" => $mapuid,
			"trackNum" => $this->trackNum,
			"roundNum" => $this->roundNum
		]);

		$request = $this->getAPIRequest("/match-addRound");
		if ($request !== null) {
			$request->setContent($payload)->setCallable(function ($content, $error, $headers) use ($payload) {
				if ($content !== "Created" || $error !== null) {
					$this->logError("Error on the 'addRound' request. answer: " . $content . " / error: " . $error . " / payload: " . $payload);
				}
			})->postData();
		}
	}

	public function handleEndRound(StartEndStructure $structure) {
		if (!$this->MatchManagerCore->getMatchIsRunning()) return;
		$json = $structure->getPlainJsonObject();
		if (!property_exists($json, 'isvalid') || $json->isvalid) return;

		$payload = json_encode([
			"trackNum" => $this->trackNum,
			"roundNum" => $this->roundNum
		]);

		$request = $this->getAPIRequest("/match-removeRound");
		if ($request !== null) {
			$request->setContent($payload)->setCallable(function ($content, $error, $headers) use ($payload) {
				if ($content !== "Created" || $error !== null) {
					$this->logError("Error on the 'removeRound' request. answer: " . $content . " / error: " . $error . " / payload: " . $payload);
				}
			})->postData();
		}
	}

	private function getAPIRequest(string $url) : ?AsyncHttpRequest {
		$baseurl = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_URL);
		$matchapikey = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_API_KEY);

		$array = explode("_", $matchapikey);
		if (count($array) !== 2) return null;

		$matchid = $array[0];
		$token = $array[1];

		if ($baseurl === "") return null;
		if ($token === "") return null;
		if ($matchid === "") return null;

		$asyncHttpRequest = new AsyncHttpRequest($this->maniaControl, $baseurl . $url . "?matchId=" . $matchid);
		$asyncHttpRequest->setContentType("application/json");
		$asyncHttpRequest->setHeaders(["Authorization: " . $token]);

		return $asyncHttpRequest;
	}

	/**
	 * Unload the plugin and its Resources
	 */
	public function unload() {
		/** @var \MatchManagerSuite\MatchManagerAdminUI|null */
		$adminUIPlugin = $this->maniaControl->getPluginManager()->getPlugin(self::MATCHMANAGERADMINUI_PLUGIN);
		if ($adminUIPlugin !== null) {
			$adminUIPlugin->removeMenuItem(self::ML_ACTION_OPENSETTINGS);
		}
	}
}