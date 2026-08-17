<?php

namespace MatchManagerSuite;

use Exception;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Callbacks\TimerListener;
use ManiaControl\Files\AsyncHttpRequest;
use ManiaControl\Logger;
use ManiaControl\ManiaControl;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;
use ManiaControl\Players\Player;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Plugins\PluginManager;
use ManiaControl\Plugins\PluginMenu;

if (!class_exists('MatchManagerSuite\MatchManagerCore')) {
	$this->maniaControl->getChat()->sendErrorToAdmins('MatchManager Core is required to use MatchManagerPickAndBan plugin. Install it and restart Maniacontrol');
	Logger::logError('MatchManager Core is required to use MatchManagerPickAndBan plugin. Install it and restart Maniacontrol');
	return false;
}

/**
 * MatchManager Pick and Ban
 *
 * @author		Beu
 * @license		http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class MatchManagerPickAndBan implements ManialinkPageAnswerListener, CallbackListener, TimerListener, Plugin {
	/*
	 * Constants
	 */
	const PLUGIN_ID											= 223;
	const PLUGIN_VERSION									= 0.3;
	const PLUGIN_NAME										= 'MatchManager Pick & Ban';
	const PLUGIN_AUTHOR										= 'Beu';

	const LOG_PREFIX										= '[MatchManagerPickAndBan] ';

	// Settings
	const SETTING_ENABLE									= 'Enable pick & ban';
	const SETTING_STEPCONFIG 								= 'Step config';
	const SETTING_STEPDURATION 								= 'Step duration';
	const SETTING_DURATIONAFTERTHELASTSTEP					= 'Duration after the last step';
	const SETTING_STARTMATCHAUTOMATICALLY					= 'Start match automatically after the end of the pick & ban';
	const SETTING_PRODUCTIONAPIURL							= 'Production API URL';


	// Other MatchManager plugin
	const MATCHMANAGERCORE_PLUGIN							= 'MatchManagerSuite\MatchManagerCore';
	const MATCHMANAGERADMINUI_PLUGIN						= 'MatchManagerSuite\MatchManagerAdminUI';

	// Steps
	const ML_STEP_OPENSETTINGS 							= 'MatchManagerSuite\MatchManagerPickAndBan.OpenSettings';
	const ML_STEP_MAPCHOOSENREGEX							= '/^MatchManagerPickAndBan_MapChoosen:.*/';

	/*
	 * Private properties
	 */
	private ManiaControl $maniaControl;
	private MatchManagerCore $MatchManagerCore;
	private MatchManagerPickAndBan_State $state;

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
		return 'Pick & Ban plugin for MatchManager';
	}

	/**
	 * @param \ManiaControl\ManiaControl $maniaControl
	 * @return bool
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		$this->clearManialinks();

		$this->MatchManagerCore = $this->maniaControl->getPluginManager()->getPlugin(self::MATCHMANAGERCORE_PLUGIN);

		if ($this->MatchManagerCore == Null) {
			throw new \Exception('MatchManager Core is needed to use ' . self::PLUGIN_NAME);
		}

		$this->state = new MatchManagerPickAndBan_State;

		// Settings
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_ENABLE, true, '', 0);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_STEPCONFIG, '', '', 100);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_STEPDURATION, 60, '',100);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_DURATIONAFTERTHELASTSTEP, 30, '', 150);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_STARTMATCHAUTOMATICALLY, true, '', 150);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_PRODUCTIONAPIURL, '', '', 200);

		// Callbacks
		$this->maniaControl->getManialinkManager()->registerManialinkPageAnswerListener(self::ML_STEP_OPENSETTINGS, $this, 'handleStepOpenSettings');
		$this->maniaControl->getManialinkManager()->registerManialinkPageAnswerRegexListener(self::ML_STEP_MAPCHOOSENREGEX, $this, 'handleStepMapChoosen');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::AFTERINIT, $this, 'handleAfterInit');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PluginManager::CB_PLUGIN_LOADED, $this, 'handlePluginLoaded');
		$this->maniaControl->getCallbackManager()->registerCallbackListener($this->MatchManagerCore::CB_MATCHMANAGER_ENDMATCH, $this, 'handleEndMatch');
		$this->maniaControl->getCallbackManager()->registerCallbackListener($this->MatchManagerCore::CB_MATCHMANAGER_STOPMATCH, $this, 'handleEndMatch');

		$this->MatchManagerCore->addCanStartFunction($this, 'handleCanStartMatch'); 

		$this->updateAdminUIMenuItems();

		$this->sendStateToPlayers();		
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {
		/** @var \MatchManagerSuite\MatchManagerAdminUI|null */
		$adminUIPlugin = $this->maniaControl->getPluginManager()->getPlugin(self::MATCHMANAGERADMINUI_PLUGIN);
		if ($adminUIPlugin !== null) {
			$adminUIPlugin->removeMenuItem(self::ML_STEP_OPENSETTINGS);
		}

		$this->clearManialinks();
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

		$adminUIPlugin->removeMenuItem(self::ML_STEP_OPENSETTINGS);

		$menuItem = new \MatchManagerSuite\MatchManagerAdminUI_MenuItem();
		$menuItem->setActionId(self::ML_STEP_OPENSETTINGS)
			->setOrder(40)
			->setStyle('MeshModelerIcons')
			->setSubStyle('LayersFocused')
			->setDescription('Open Pick & Ban Settings');
		$adminUIPlugin->addMenuItem($menuItem);
	}

	/**
	 * manage Step
	 */
	public function manageStep() {
		// Do random pick
		while (
			array_key_exists($this->state->stepIndex, $this->state->steps) &&
			$this->state->steps[$this->state->stepIndex]->login === ""
		) {
			$availableMaps = array_filter($this->state->maps, function($map) {
				return !$map->picked && !$map->banned;
			});

			$mapUid = array_rand($availableMaps);

			$this->state->maps[$mapUid]->banned = ($this->state->steps[$this->state->stepIndex]->type === MatchManagerPickAndBan_Step::STEP_BAN);
			$this->state->maps[$mapUid]->picked = ($this->state->steps[$this->state->stepIndex]->type === MatchManagerPickAndBan_Step::STEP_PICK);

			$this->state->steps[$this->state->stepIndex]->mapUid = $mapUid;
			$this->state->stepIndex++;
		}

		$this->state->startTimestamp = time();
		if ($this->state->stepIndex >= count($this->state->steps)) {
			$this->state->endTimestamp = time() + $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_DURATIONAFTERTHELASTSTEP);
		} else {
			$this->state->endTimestamp = time() + $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_STEPDURATION);
		}

		$this->sendStateToProduction();
		$this->sendStateToPlayers();
	}

	/**
	 * handle timer
	 * @return void 
	 */
	public function handleTimer() {
		if ($this->state->status !== MatchManagerPickAndBan_State::STATUS_INPROGRESS) {
			$this->maniaControl->getTimerManager()->unregisterTimerListening($this, 'handleTimer');
			return;
		}

		if ($this->state->endTimestamp < time()) {
			if (array_key_exists($this->state->stepIndex, $this->state->steps)) {
				$this->state->steps[$this->state->stepIndex]->login = "";
				$this->manageStep();
			} else {
				$this->log("Pick & ban are over");
				$this->state->status = MatchManagerPickAndBan_State::STATUS_COMPLETED;
				$this->clearManialinks();

				if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_STARTMATCHAUTOMATICALLY)) {
					$this->log("Automatically starting the match");
					$this->MatchManagerCore->MatchStart();
				}
			}
		}
	}

	/**
	 * handle Open settings manialink step
	 * 
	 * @param array $callback 
	 * @param Player $player 
	 */
	public function handleStepOpenSettings(array $callback, Player $player) {
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
	 * handle Map Choosen
	 * 
	 * @param array $callback 
	 * @param Player $player 
	 */
	public function handleStepMapChoosen(array $callback, Player $player) {
		if ($this->state->status !== MatchManagerPickAndBan_State::STATUS_INPROGRESS) return;
		if (!array_key_exists($this->state->stepIndex, $this->state->steps)) return;

		if ($this->state->steps[$this->state->stepIndex]->login !== $player->login) return;

		$step = $callback[1][2];
		$stepArray = explode(':', $step);

		if (count($stepArray) !== 2) return;

		$mapUid = $stepArray[1];

		if (!array_key_exists($mapUid, $this->state->maps)) return;

		$this->state->maps[$mapUid]->banned = ($this->state->steps[$this->state->stepIndex]->type === MatchManagerPickAndBan_Step::STEP_BAN);
		$this->state->maps[$mapUid]->picked = ($this->state->steps[$this->state->stepIndex]->type === MatchManagerPickAndBan_Step::STEP_PICK);

		$this->state->steps[$this->state->stepIndex]->mapUid = $mapUid;
		$this->state->stepIndex++;

		$this->manageStep();
	}

	/**
	 * handle MatchManagerCore Can Start Match callback
	 */
	public function handleCanStartMatch() {
		if (!$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_ENABLE)) return true;

		if ($this->state->status === MatchManagerPickAndBan_State::STATUS_COMPLETED) {
			$maplist = [];
			foreach ($this->state->steps as $step) {
				if ($step->type !== MatchManagerPickAndBan_Step::STEP_PICK) continue;
				if (!array_key_exists($step->mapUid, $this->state->maps)) {
					$this->logError("Picked map doesn't exist in map list, should never happen.");
					$this->maniaControl->getChat()->sendErrorToAdmins($this->MatchManagerCore->getChatPrefix() . "Picked map doesn't exist in map list, should never happen.");

					$this->state = new MatchManagerPickAndBan_State;
					return false;
				}


				$maplist[] = $this->state->maps[$step->mapUid]->filePath;
			}

			$setting = $this->maniaControl->getSettingManager()->getSettingObject($this->MatchManagerCore, $this->MatchManagerCore::SETTING_MODE_MAPS);
			$setting->value = implode(',', $maplist);
			
			$this->log('Starting match with maps "'. $setting->value .'"');

			return true;
		}

		if ($this->state->status === MatchManagerPickAndBan_State::STATUS_INPROGRESS) {
			$this->maniaControl->getChat()->sendErrorToAdmins($this->MatchManagerCore->getChatPrefix() . "Pick and Ban in progress");
			return false;
		}

		$stepConfig = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_STEPCONFIG);
		if ($stepConfig === '') {
			$this->maniaControl->getChat()->sendErrorToAdmins($this->MatchManagerCore->getChatPrefix() . "'Step config' setting must be set");
			return false;
		}

		if ($this->maniaControl->getSettingManager()->getSettingValue($this->MatchManagerCore, $this->MatchManagerCore::SETTING_MATCH_SETTINGS_MODE) !== 'All from the plugin') {
			$this->maniaControl->getChat()->sendErrorToAdmins($this->MatchManagerCore->getChatPrefix() . "Setting mode must be in 'All from the plugin' to use Pick & Ban");
			return false;
		}

		$mapFiles = $this->maniaControl->getSettingManager()->getSettingValue($this->MatchManagerCore, $this->MatchManagerCore::SETTING_MODE_MAPS);
		if ($mapFiles === '') {
			$this->maniaControl->getChat()->sendErrorToAdmins($this->MatchManagerCore->getChatPrefix() . "Maps must be defined to use Pick & Ban");
			return false;
		}

		if (count(explode(',', $mapFiles)) < count(explode(',', $stepConfig))) {
			$this->maniaControl->getChat()->sendErrorToAdmins($this->MatchManagerCore->getChatPrefix() . "You must have at least same number of maps than steps");
			return false;
		}

		$this->log('Building Pick & Ban config');

		$this->state = new MatchManagerPickAndBan_State;
		$this->state->status = MatchManagerPickAndBan_State::STATUS_INPROGRESS;
		$this->state->originalMapList = $mapFiles;

		// build map config
		$maps = [];
		foreach (explode(',', $mapFiles) as $mapPath) {
			try {
				$mapInfo = $this->maniaControl->getClient()->getMapInfo($mapPath);

				$mapObject = new MatchManagerPickAndBan_Map();
				$mapObject->mapUid = $mapInfo->uId;
				$mapObject->filePath = $mapPath;
				$mapObject->name = $mapInfo->name;
				$maps[$mapInfo->uId] = $mapObject;

			} catch (Exception $e) {
				$this->maniaControl->getChat()->sendErrorToAdmins($this->MatchManagerCore->getChatPrefix() . 'Error with the Map to play "' . $mapPath . '": ' . $e->getMessage());
				return false;
			}
		}

		uasort($maps, function ($a, $b) {
			return strnatcasecmp($a->name, $b->name);
		});

		$this->state->maps = $maps;
		$this->log(count($this->state->maps) . ' maps loaded for Pick & Ban');

		// build steps
		$steps = [];
		foreach (explode(',', $stepConfig) as $index => $stepRaw) {
			$stepArray = explode(':', $stepRaw);
			if (count($stepArray) !== 2) {
				$this->maniaControl->getChat()->sendErrorToAdmins($this->MatchManagerCore->getChatPrefix() . 'Invalid step ' . $index  . 'config: ' . $stepRaw);
				return false;
			}

			$step =  new MatchManagerPickAndBan_Step;

			if ($stepArray[0] === 'p') {
				$step->type = MatchManagerPickAndBan_Step::STEP_PICK;
			} else if ($stepArray[0] === 'b') {
				$step->type = MatchManagerPickAndBan_Step::STEP_BAN;
			}

			if ($stepArray[1] === 'r') {
				$step->login = "";
			} else {
				$step->login = $stepArray[1];
			}
			$steps[] = $step;
		}
		$this->state->steps = $steps;
		$this->log(count($this->state->steps) . ' steps loaded for Pick & Ban');

		$this->maniaControl->getChat()->sendSuccess('Starting Pick & Ban phase');
		$this->log('Starting Pick & Ban phase');
		$this->sendManialink();
		$this->manageStep();

		$this->maniaControl->getTimerManager()->registerTimerListening($this, 'handleTimer', 1000);

		return false;
	}

	public function handleEndMatch() {
		if ($this->state->status === MatchManagerPickAndBan_State::STATUS_COMPLETED) {
			$setting = $this->maniaControl->getSettingManager()->getSettingObject($this->MatchManagerCore, $this->MatchManagerCore::SETTING_MODE_MAPS);
			$setting->value = $this->state->originalMapList;
			$this->state = new MatchManagerPickAndBan_State;
		}
	}

	private function sendManialink(): void {
		$manialink = '
<manialink version="3" id="'. self::getShortClassName() .':UI" name="'. self::getShortClassName() . ':UI">
<stylesheet>
	<style class="text" textfont="GameFontExtraBold" textcolor="ffffff" />
</stylesheet>
<framemodel id="framemodel-map">
	<quad id="quad-thumbnail" size="50 33" halign="center" valign="center" bgcolor="555555" keepratio="Clip" scriptevents="1"/>
	<frame pos="0 -10.5">
		<quad id="quad-map-name-background" size="50 5.5" halign="center" valign="center" bgcolor="000000" opacity="0.5"/>
		<label id="label-map-name" class="text" pos="0 0.4" size="45 5" halign="center" valign="center" textsize="1.8" text="Map name"/>
	</frame>
</framemodel>
<framemodel id="framemodel-step">
	<quad id="quad-thumbnail" size="80 14" halign="center" valign="center" bgcolor="555555" keepratio="Clip"/>
	<frame pos="0 0">
		<frame id="frame-map-name" pos="0 7">
			<quad size="80 5.5" halign="center"  bgcolor="000000" opacity="0.5"/>
			<label id="label-map-name" class="text" pos="0 -2.3" size="45 5" halign="center" valign="center" textsize="1.8" text="Map name"/>
		</frame>
		<label id="label-step" class="text" pos="0 -2.3" size="75 8" halign="center" valign="center" textsize="3" textprefix="$t$s" text="Banned By CARLJR."/>
	</frame>
</framemodel>
<frame id="frame-global" hidden="1" z-index="5000">
	<quad size="320 180" fullscreen="1" halign="center" valign="center" image="file://Media/Manialinks/Nadeo/Trackmania/Menus/PageProfile/UI_profile_background_map_gradients.dds"/>
	<frame pos="-160 0">
		<label class="text" pos="116 80" halign="center"  textsize="10" text="Pick & Ban"/>
		<frame id="frame-maps" pos="116 0">
			<frameinstance modelid="framemodel-map" hidden="1"/>
			<frameinstance modelid="framemodel-map" hidden="1"/>
			<frameinstance modelid="framemodel-map" hidden="1"/>
			<frameinstance modelid="framemodel-map" hidden="1"/>
			<frameinstance modelid="framemodel-map" hidden="1"/>
			<frameinstance modelid="framemodel-map" hidden="1"/>
			<frameinstance modelid="framemodel-map" hidden="1"/>
			<frameinstance modelid="framemodel-map" hidden="1"/>
			<frameinstance modelid="framemodel-map" hidden="1"/>
			<frameinstance modelid="framemodel-map" hidden="1"/>
			<frameinstance modelid="framemodel-map" hidden="1"/>
			<frameinstance modelid="framemodel-map" hidden="1"/>
		</frame>
		<label id="label-current-action" class="text" pos="116 -68" size="200 8" halign="center" textsize="5" text=""/>
		<label id="label-timer" class="text" pos="116 -77" halign="center" textsize="8" text=""/>
		<quad pos="116 -88" size="232 2" halign="center" bgcolor="ffffff" opacity="0.4"/>
		<quad id="quad-timer" pos="116 -88" size="0 2" halign="center" bgcolor="ffffff"/>
	</frame>
	<frame pos="72 0">
		<quad size="120 180" valign="center" bgcolor="000000" opacity="0.2"/>
		<frame id="frame-steps" pos="44 0">
			<frameinstance modelid="framemodel-step" hidden="1"/>
			<frameinstance modelid="framemodel-step" hidden="1"/>
			<frameinstance modelid="framemodel-step" hidden="1"/>
			<frameinstance modelid="framemodel-step" hidden="1"/>
			<frameinstance modelid="framemodel-step" hidden="1"/>
			<frameinstance modelid="framemodel-step" hidden="1"/>
			<frameinstance modelid="framemodel-step" hidden="1"/>
			<frameinstance modelid="framemodel-step" hidden="1"/>
			<frameinstance modelid="framemodel-step" hidden="1"/>
			<frameinstance modelid="framemodel-step" hidden="1"/>
			<frameinstance modelid="framemodel-step" hidden="1"/>
			<frameinstance modelid="framemodel-step" hidden="1"/>
		</frame>
	</frame>
</frame>
<script><!--
#Include "MathLib" as ML
#Include "TextLib" as TL
#Include "TimeLib" as TiL

#Include "Libs/Nadeo/CMGame/Utils/Task@2.Script.txt" as Task
#Include "Libs/Nadeo/CMGame/Utils/Tools.Script.txt" as Tools
#Include "Libs/Nadeo/Trackmania/Stores/UserStore_ML.Script.txt" as UserStore


#Const C_Status_InProgress 1

#Const C_StepType_Pick 1
#Const C_StepType_Ban 2

#Const C_Color_White <1., 1., 1.>
#Const C_Color_Gray <.7, .7, .7>
#Const C_Color_Reset <-1., -1., -1.>

#Const C_CooldownDuration 1000
#Const C_MaxAccountIdsPerTask 150 //< Asking for more at one time will create an http error 414 URI Too Long

'. $this->getManialinkStructures() . '

Void Focus(CMlFrame _Frame) {
	declare CMlQuad Quad <=> (_Frame.GetFirstChild("quad-map-name-background") as CMlQuad);
	Quad.BgColor = <.1, .1, .1>;
	AnimMgr.Flush(_Frame);
	AnimMgr.Add(_Frame, "<a scale=\"1.05\" />", 200, CAnimManager::EAnimManagerEasing::Linear);
}
Void Unfocus(CMlFrame _Frame) {
	declare CMlQuad Quad <=> (_Frame.GetFirstChild("quad-map-name-background") as CMlQuad);
	Quad.BgColor = <0., 0., 0.>;
	AnimMgr.Flush(_Frame);
	AnimMgr.Add(_Frame, "<a scale=\"1\" />", 200, CAnimManager::EAnimManagerEasing::Linear);
}

main() {
	log("['. self::getShortClassName() .'] Loading UI");
	declare CMlFrame Frame_Global <=> (Page.GetFirstChild("frame-global") as CMlFrame);
	declare CMlFrame Frame_Maps <=> (Frame_Global.GetFirstChild("frame-maps") as CMlFrame);
	declare CMlFrame Frame_Steps <=> (Frame_Global.GetFirstChild("frame-steps") as CMlFrame);
	declare CMlLabel Label_Timer <=> (Frame_Global.GetFirstChild("label-timer") as CMlLabel);
	declare CMlLabel Label_CurrentAction <=> (Frame_Global.GetFirstChild("label-current-action") as CMlLabel);
	declare CMlQuad Quad_Timer <=> (Frame_Global.GetFirstChild("quad-timer") as CMlQuad);

	wait(InputPlayer != Null);

	declare K_MatchManagerPickAndBan_State MatchManagerPickAndBan_State for This;
	declare Integer MatchManagerPickAndBan_State_Serial for This;
	declare Integer Last_State_Serial = -1;

	declare Integer Last_Timer_Update;

	declare Task::K_Task Task_RetrievingName;
	declare Integer Cooldown;
	declare Text[] PendingAccountIds;
	declare Text[] RequestingAccountIds;

	declare Boolean UpdateNow;

	while(True) {
		yield;

		if (Last_State_Serial != MatchManagerPickAndBan_State_Serial) {
			Last_State_Serial = MatchManagerPickAndBan_State_Serial;
			UpdateNow = True;
		}

		if (UpdateNow) {
			UpdateNow = False;
			log("Updating interface");

			Frame_Global.Visible = (MatchManagerPickAndBan_State.status == C_Status_InProgress);
			if (Frame_Global.Visible) {

				// Build maps
				declare Integer Index = 0;
				declare Integer MapsCount = MatchManagerPickAndBan_State.maps.count;
				declare Integer MapsPerLine = 4;
				declare Integer NbLines = (MapsCount + MapsPerLine - 1) / MapsPerLine;
				declare Real StepX = 54.; // ElementW(50) + GapX(4)
				declare Real StepY = 37.; // ElementH(33) + GapY(4)

				foreach (MapInfo in MatchManagerPickAndBan_State.maps) {
					if (!Frame_Maps.Controls.existskey(Index)) break;

					declare CMlFrame Frame_Map <=> (Frame_Maps.Controls[Index] as CMlFrame);
					Frame_Map.Visible = True;

					declare K_MatchManagerPickAndBan_Map MatchManagerPickAndBan_MapInfo for Frame_Map;
					MatchManagerPickAndBan_MapInfo = MapInfo;

					declare Integer Row = Index / MapsPerLine;
					declare Integer Col = Index - (Row * MapsPerLine);
					declare Integer MapsOnThisRow;
					if (Row < NbLines - 1) {
						MapsOnThisRow = MapsPerLine;
					} else {
						MapsOnThisRow = MapsCount - (Row * MapsPerLine);
					}
					Frame_Map.RelativePosition_V3.X = (Col - (MapsOnThisRow - 1) / 2.) * StepX;
					Frame_Map.RelativePosition_V3.Y = -1. * (Row - (NbLines - 1) / 2.) * StepY;

					declare CMlLabel Label_MapName <=> (Frame_Map.GetFirstChild("label-map-name") as CMlLabel);
					Label_MapName.Value = MapInfo.name;
					Tools::FitLabelValue(Label_MapName, 1.8, 0.5, 0.1);

					declare CMlQuad Quad_MapThumbnail <=> (Frame_Map.GetFirstChild("quad-thumbnail") as CMlQuad);
					Quad_MapThumbnail.ImageUrl = "file://Thumbnails/MapUid/" ^ MapInfo.mapUid;

					if (MapInfo.picked || MapInfo.banned) {
						Quad_MapThumbnail.Colorize = C_Color_White;
						Label_MapName.TextColor = C_Color_Gray;
					} else {
						Quad_MapThumbnail.Colorize = C_Color_Reset;
						Label_MapName.TextColor = C_Color_White;
					}

					Index += 1;
				}
				while (Frame_Maps.Controls.existskey(Index)) {
					(Frame_Maps.Controls[Index] as CMlFrame).Visible = False;
					Index += 1;
				}


				// Build steps
				Index = 0;
				declare Integer StepsCount = MatchManagerPickAndBan_State.steps.count;

				foreach (Step in MatchManagerPickAndBan_State.steps) {
					if (!Frame_Steps.Controls.existskey(Index)) break;

					declare CMlFrame Frame_Step <=> (Frame_Steps.Controls[Index] as CMlFrame);
					Frame_Step.Visible = True;

					declare Real StepStepY = 18.; // ElementH(14) + GapY(4)
					Frame_Step.RelativePosition_V3.Y = -1. * (Index - (StepsCount - 1) / 2.) * StepStepY;

					declare CMlLabel Label_Step <=> (Frame_Step.GetFirstChild("label-step") as CMlLabel);
					declare CMlFrame Frame_MapName <=> (Frame_Step.GetFirstChild("frame-map-name") as CMlFrame);
					declare CMlQuad Quad_MapThumbnail <=> (Frame_Step.GetFirstChild("quad-thumbnail") as CMlQuad);

					if (Step.mapUid == "") {
						Frame_MapName.Visible = False;
						Label_Step.RelativePosition_V3.Y = 0.4;
						Quad_MapThumbnail.ImageUrl = "";
						Quad_MapThumbnail.Colorize = C_Color_Reset;

						if (Step.login == "") {
							if (Step.type == C_StepType_Ban) {
								Label_Step.Value = "Random ban";
							} else {
								Label_Step.Value = "Random pick";
							}
							if (MatchManagerPickAndBan_State.stepIndex == Index) {
								Label_CurrentAction.Value = "";
							}
						} else if (Step.type == C_StepType_Ban) {
							declare Text DisplayName = UserStore::GetUserMgrPlayerName(Step.login);
							if (DisplayName == "") {
								Label_Step.Value = "Ban by "^ Step.login;
								if (!PendingAccountIds.exists(Step.login)) {
									PendingAccountIds.add(Step.login);
								}
								if (MatchManagerPickAndBan_State.stepIndex == Index) {
									Label_CurrentAction.Value = "";
								}
							} else {
								Label_Step.Value = "Ban by "^ DisplayName;
								if (MatchManagerPickAndBan_State.stepIndex == Index) {
									if (Step.login == InputPlayer.User.Login) {
										Label_CurrentAction.Value = "You have to ban a map";
										System.FlashWindow();
									} else {
										Label_CurrentAction.Value = DisplayName ^" has to ban a map";
									}
								}
							}
						} else {
							declare Text DisplayName = UserStore::GetUserMgrPlayerName(Step.login);
							if (DisplayName == "") {
								Label_Step.Value = "Pick by "^ Step.login;
								if (!PendingAccountIds.exists(Step.login)) {
									PendingAccountIds.add(Step.login);
								}
								if (MatchManagerPickAndBan_State.stepIndex == Index) {
									Label_CurrentAction.Value = "";
								}
							} else {
								Label_Step.Value = "Pick by "^ DisplayName;
								if (MatchManagerPickAndBan_State.stepIndex == Index) {
									if (Step.login == InputPlayer.User.Login) {
										Label_CurrentAction.Value = "You have to pick a map";
										System.FlashWindow();
									} else {
										Label_CurrentAction.Value = DisplayName ^" has to pick a map";
									}
								}
							}
						}
						log(Label_CurrentAction.Value );
						log(MatchManagerPickAndBan_State.stepIndex ^"=="^ Index);
					} else {
						Frame_MapName.Visible = True;
						Label_Step.RelativePosition_V3.Y = -2.3;

						declare K_MatchManagerPickAndBan_Map MapInfo = MatchManagerPickAndBan_State.maps.get(Step.mapUid, K_MatchManagerPickAndBan_Map {});

						declare CMlLabel Label_MapName <=> (Frame_Step.GetFirstChild("label-map-name") as CMlLabel);
						Label_MapName.Value = MapInfo.name;
						Tools::FitLabelValue(Label_MapName, 1.8, 0.5, 0.1);

						Quad_MapThumbnail.ImageUrl = "file://Thumbnails/MapUid/" ^ MapInfo.mapUid;

						if (Step.login == "") {
							if (Step.type == C_StepType_Ban) {
								Label_Step.Value = "Randomly banned";
								Quad_MapThumbnail.Colorize = C_Color_White;
							} else {
								Label_Step.Value = "Randomly picked";
								Quad_MapThumbnail.Colorize = C_Color_Reset;
							}
						} else if (Step.type == C_StepType_Ban) {
							declare Text DisplayName = UserStore::GetUserMgrPlayerName(Step.login);
							if (DisplayName == "") {
								Label_Step.Value = "Ban by "^ Step.login;
								if (!PendingAccountIds.exists(Step.login)) {
									PendingAccountIds.add(Step.login);
								}
							} else {
								Label_Step.Value = "Ban by "^ DisplayName; 
							}
							Quad_MapThumbnail.Colorize = C_Color_White;
						} else {
							declare Text DisplayName = UserStore::GetUserMgrPlayerName(Step.login);
							if (DisplayName == "") {
								Label_Step.Value = "Pick by "^ Step.login;
								if (!PendingAccountIds.exists(Step.login)) {
									PendingAccountIds.add(Step.login);
								}
							} else {
								Label_Step.Value = "Pick by "^ DisplayName;
							}
							Quad_MapThumbnail.Colorize = C_Color_Reset;
						}

						if (MatchManagerPickAndBan_State.stepIndex == Index || MatchManagerPickAndBan_State.stepIndex >= StepsCount) {
							Label_CurrentAction.Value = "";
						}
					}

					Tools::FitLabelValue(Label_Step, 3., 1., 0.25);
					Tools::FitLabelValue(Label_CurrentAction, 5., 3., 0.25);
					Index += 1;
				}
				while (Frame_Steps.Controls.existskey(Index)) {
					(Frame_Steps.Controls[Index] as CMlFrame).Visible = False;
					Index += 1;
				}
			}
		}

		foreach (Event in PendingEvents) {
			if (Event.Type == CMlScriptEvent::Type::MouseOut && Event.ControlId == "quad-thumbnail") {
				Unfocus(Event.Control.Parent);
			} else if (
				MatchManagerPickAndBan_State.steps.existskey(MatchManagerPickAndBan_State.stepIndex) &&
				MatchManagerPickAndBan_State.steps[MatchManagerPickAndBan_State.stepIndex].login == InputPlayer.User.Login
			) {
				if (Event.Type == CMlScriptEvent::Type::MouseOver && Event.ControlId == "quad-thumbnail") {
					declare K_MatchManagerPickAndBan_Map MatchManagerPickAndBan_MapInfo for Event.Control.Parent;
					if (MatchManagerPickAndBan_MapInfo.picked || MatchManagerPickAndBan_MapInfo.banned) continue;

					Focus(Event.Control.Parent);
				} else if (Event.Type == CMlScriptEvent::Type::MouseClick && Event.ControlId == "quad-thumbnail") {
					declare K_MatchManagerPickAndBan_Map MatchManagerPickAndBan_MapInfo for Event.Control.Parent;
					if (MatchManagerPickAndBan_MapInfo.picked || MatchManagerPickAndBan_MapInfo.banned) continue;

					TriggerPageAction("MatchManagerPickAndBan_MapChoosen:"^ MatchManagerPickAndBan_MapInfo.mapUid);
					Unfocus(Event.Control.Parent);
				}
			}
		}


		if (Last_Timer_Update < Now) {
			Last_Timer_Update = Now + 100;
			if (MatchManagerPickAndBan_State.startTimestamp != 0 && MatchManagerPickAndBan_State.endTimestamp != 0) {
				declare Integer RemainingTime = ML::Max(MatchManagerPickAndBan_State.endTimestamp - TL::ToInteger(TiL::GetCurrent()), 0);
				Label_Timer.Value = ""^ RemainingTime;
				declare Real Width = RemainingTime * 232. / (MatchManagerPickAndBan_State.endTimestamp - MatchManagerPickAndBan_State.startTimestamp);
				AnimMgr.Flush(Quad_Timer);
				AnimMgr.Add(Quad_Timer, "<a	size=\""^ Width ^" 2\"/>", 100, CAnimManager::EAnimManagerEasing::Linear);
			} else {
				AnimMgr.Flush(Quad_Timer);
				AnimMgr.Add(Quad_Timer, "<a	size=\"0 2\"/>", 100, CAnimManager::EAnimManagerEasing::Linear);
			}
		}

		if (Task::IsProcessing(Task_RetrievingName)) {
			Task_RetrievingName = Task::Update(UserMgr, UserMgr, Task_RetrievingName);

			if (!Task::IsProcessing(Task_RetrievingName)) {
				log("Ending RetrieveNames Task_RetrievingName for: "^ RequestingAccountIds);
				Task_RetrievingName = Task::Destroy(UserMgr, UserMgr, Task_RetrievingName);

				UpdateNow = True;
			}
		} else if (PendingAccountIds.count > 0 && Cooldown <= Now) {
			RequestingAccountIds = PendingAccountIds.slice(0, C_MaxAccountIdsPerTask);
			PendingAccountIds = PendingAccountIds.slice(C_MaxAccountIdsPerTask);
			Task_RetrievingName = Task::DestroyAndCreate(
				UserMgr,
				UserMgr,
				Task_RetrievingName,
				UserMgr.RetrieveDisplayName(UserMgr.MainUser.Id, RequestingAccountIds)
			);
			log("Starting RetrieveNames Task_RetrievingName for: "^ RequestingAccountIds);
		}

	}
}
--></script>
</manialink>
';

		$this->maniaControl->getManialinkManager()->sendManialink($manialink);
	}

	private function sendStateToPlayers(): void {
		$manialink = '
<manialink version="3" id="'. self::getShortClassName() .':State" name="'. self::getShortClassName() . ':State">
<script><!--

#Const C_State """'. json_encode($this->state) .'"""

'. $this->getManialinkStructures() . '

main() {
	log("['. self::getShortClassName() .'] Updating State");
	declare K_MatchManagerPickAndBan_State MatchManagerPickAndBan_State for This;
	MatchManagerPickAndBan_State.fromjson(C_State);
	declare Integer MatchManagerPickAndBan_State_Serial for This;
	MatchManagerPickAndBan_State_Serial += 1;
}
--></script>
</manialink>';
		$this->maniaControl->getManialinkManager()->sendManialink($manialink);
	}

	private function sendStateToProduction(): void {
		$url = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_PRODUCTIONAPIURL);
		if ($url === '') return;

		$this->log('Sending state to the API');

		$asyncHttpRequest = new AsyncHttpRequest($this->maniaControl, $url);
		$asyncHttpRequest->setContentType(AsyncHttpRequest::CONTENT_TYPE_JSON);
		$asyncHttpRequest->setContent(json_encode($this->state));

		$asyncHttpRequest->setCallable(function ($data, $error, $headers){
			if ($error) {
				Logger::logError('Error when sending state: '. $error);
				return;
			}
			Logger::log('Succesfully sent the state to the Production API');
		});
		$asyncHttpRequest->postData();
	}

	private function clearManialinks() {
		$this->maniaControl->getManialinkManager()->hideManialink(self::getShortClassName() . ':UI');
		$this->maniaControl->getManialinkManager()->hideManialink(self::getShortClassName() . ':State');
	}

	private function getManialinkStructures(): string {
		return '
#Struct K_MatchManagerPickAndBan_Step {
	Integer type;
	Text login;
	Text mapUid;
}

#Struct K_MatchManagerPickAndBan_Map {
	Text mapUid;
	Text name;
	Boolean picked;
	Boolean banned;
}

#Struct K_MatchManagerPickAndBan_State {
	Integer status;
	Integer stepIndex;
	Integer startTimestamp;
	Integer endTimestamp;
	K_MatchManagerPickAndBan_Map[Text] maps;
	K_MatchManagerPickAndBan_Step[] steps;
}
';
	}

	private static function getShortClassName(): string {
		return (new \ReflectionClass(static::class))->getShortName();
	}
}

class MatchManagerPickAndBan_State {
	// States
	const STATUS_WAITING		= 0;
	const STATUS_INPROGRESS		= 1;
	const STATUS_COMPLETED		= 2;

	public int $status = self::STATUS_WAITING;
	public string $originalMapList = '';
	public int $stepIndex = 0;
	public int $startTimestamp;
	public int $endTimestamp;
	public array $steps = [];
	public array $maps = [];
}

class MatchManagerPickAndBan_Step {
	// Steps
	const STEP_PICK = 1;
	const STEP_BAN = 2;

	public int $type;
	public string $login;
	public string $mapUid;
}

class MatchManagerPickAndBan_Map {
	public string $mapUid;
	public string $filePath;
	public string $name;
	public bool $picked = false;
	public bool $banned = false;
}