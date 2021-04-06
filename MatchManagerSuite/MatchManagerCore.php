<?php 

namespace MatchManagerSuite;

use Exception;
use FML\Controls\Frame;
use FML\Controls\Labels\Label_Text;
use FML\Controls\Quad;
use FML\ManiaLink;
use ManiaControl\Admin\AuthenticationManager;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Callbacks\CallbackManager;
use ManiaControl\Callbacks\Structures\Common\StatusCallbackStructure;
use ManiaControl\Callbacks\Structures\TrackMania\OnScoresStructure;
use ManiaControl\Callbacks\Structures\TrackMania\OnWayPointEventStructure;
use ManiaControl\Callbacks\TimerListener;
use ManiaControl\Commands\CommandListener;
use ManiaControl\Communication\CommunicationAnswer;
use ManiaControl\Communication\CommunicationListener;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Plugins\Plugin;
use ManiaControl\ManiaControl;
use \ManiaControl\Logger;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;
use ManiaControl\Configurator\GameModeSettings;
use ManiaControl\Utils\Formatter;
use Maniaplanet\DedicatedServer\InvalidArgumentException;

/**
 * MatchManager Core
 *
 * @author		Beu (based on MatchPlugin by jonthekiller)
 * @license		http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class MatchManagerCore implements ManialinkPageAnswerListener, CallbackListener, CommandListener, TimerListener, CommunicationListener, Plugin {

	const PLUGIN_ID											= 152;
	const PLUGIN_VERSION									= 1.1;
	const PLUGIN_NAME										= 'MatchManager Core';
	const PLUGIN_AUTHOR										= 'Beu';

	// Specific const
	const ACTION_READY										= 'ReadyButton.Action';
	const DB_MATCHESINDEX									= 'MatchManager_MatchesIndex';
	const DB_ROUNDSINDEX									= 'MatchManager_RoundsIndex';
	const DB_ROUNDSDATA										= 'MatchManager_RoundsData';
	const DB_TEAMSDATA										= 'MatchManager_TeamsData';
	const MLID_MATCH_PAUSE_WIDGET							= 'Pause Widget';
	const MLID_MATCH_READY_WIDGET							= 'Ready ButtonWidget';

	// Internal Callback Trigger
	const CB_MATCHMANAGER_BEGINMAP							= 'MatchManager.BeginMap';
	const CB_MATCHMANAGER_ENDROUND							= 'MatchManager.EndRound';
	const CB_MATCHMANAGER_STARTMATCH						= 'MatchManager.StartMatch';
	const CB_MATCHMANAGER_ENDMATCH							= 'MatchManager.EndMatch';
	const CB_MATCHMANAGER_STOPMATCH							= 'MatchManager.StopMatch';

	// Plugin Settings
	const SETTING_MATCH_AUTHLEVEL							= 'Auth level for the match* commands:';
	const SETTING_MATCH_CUSTOM_GAMEMODE						= 'Custom Gamemode file';
	const SETTING_MATCH_GAMEMODE_BASE						= 'Gamemode used during match:';
	const SETTING_MATCH_LOAD_MAPLIST_FILE					= 'Load maps from maplist file';
	const SETTING_MATCH_MAPLIST								= 'Maplist to use';
	const SETTING_MATCH_MATCHSETTINGS_CONF					= 'Match settings from matchsettings file only:';
	const SETTING_MATCH_GAMEMODE_AFTERMATCH					= 'Gamemode used after match:';
	const SETTING_MATCH_PAUSE_DURATION						= 'Default Pause Duration in seconds';
	const SETTING_MATCH_PAUSE_MAXNUMBER						= 'Pause Max per Player';
	const SETTING_MATCH_PAUSE_POSX							= 'Pause Widget-Position: X';
	const SETTING_MATCH_PAUSE_POSY							= 'Pause Widget-Position: Y';
	const SETTING_MATCH_READY_HEIGHT						= 'Ready Button-Size: Height';
	const SETTING_MATCH_READY_MODE							= 'Ready Button';
	const SETTING_MATCH_READY_NBPLAYERS						= 'Ready Button-Minimal number of players before start';
	const SETTING_MATCH_READY_POSX							= 'Ready Button-Position: X';
	const SETTING_MATCH_READY_POSY							= 'Ready Button-Position: Y';
	const SETTING_MATCH_READY_WIDTH							= 'Ready Button-Size: Width';
	const SETTING_MATCH_SHUFFLEMAPS							= 'Randomize map order (shuffle)';


	// Gamemodes Settings
	const SETTING_MATCH_S_BESTLAPBONUSPOINTS				= 'S_BestLapBonusPoints';
	const SETTING_MATCH_S_CHATTIME							= 'S_ChatTime';
	const SETTING_MATCH_S_CUMULATEPOINTS					= 'S_CumulatePoints';
	const SETTING_MATCH_S_DISABLEGIVEUP						= 'S_DisableGiveUp';
	const SETTING_MATCH_S_EARLYENDMATCHCALLBACK				= 'S_EarlyEndMatchCallback';
	const SETTING_MATCH_S_ELIMINATEDPLAYERSNBRANKS			= 'S_EliminatedPlayersNbRanks';
	const SETTING_MATCH_S_ENDROUNDPOSTSCOREUPDATEDURATION	= 'S_EndRoundPostScoreUpdateDuration';
	const SETTING_MATCH_S_ENDROUNDPRESCOREUPDATEDURATION	= 'S_EndRoundPreScoreUpdateDuration';
	const SETTING_MATCH_S_FINISHTIMEOUT						= 'S_FinishTimeout';
	const SETTING_MATCH_S_FORCELAPSNB						= 'S_ForceLapsNb';
	const SETTING_MATCH_S_FORCEWINNERSNB					= 'S_ForceWinnersNb';
	const SETTING_MATCH_S_INFINITELAPS						= 'S_InfiniteLaps';
	const SETTING_MATCH_S_MAPSPERMATCH						= 'S_MapsPerMatch';
	const SETTING_MATCH_S_MATCHPOSITION						= 'S_MatchPosition';
	const SETTING_MATCH_S_MAXPOINTSPERROUND					= 'S_MaxPointsPerRound';
	const SETTING_MATCH_S_NBOFWINNERS						= 'S_NbOfWinners';
	const SETTING_MATCH_S_PAUSEBEFOREROUNDNB				= 'S_PauseBeforeRoundNb';
	const SETTING_MATCH_S_PAUSEDURATION						= 'S_PauseDuration';
	const SETTING_MATCH_S_POINTSGAP							= 'S_PointsGap';
	const SETTING_MATCH_S_POINTSLIMIT						= 'S_PointsLimit';
	const SETTING_MATCH_S_POINTSREPARTITION					= 'S_PointsRepartition';
	const SETTING_MATCH_S_RESPAWNBEHAVIOUR					= 'S_RespawnBehaviour';
	const SETTING_MATCH_S_ROUNDSLIMIT						= 'S_RoundsLimit';
	const SETTING_MATCH_S_ROUNDSPERMAP						= 'S_RoundsPerMap';
	const SETTING_MATCH_S_ROUNDSWITHAPHASECHANGE			= 'S_RoundsWithAPhaseChange';
	const SETTING_MATCH_S_ROUNDSWITHOUTELIMINATION			= 'S_RoundsWithoutElimination';
	const SETTING_MATCH_S_TIMELIMIT							= 'S_TimeLimit';
	const SETTING_MATCH_S_TIMEOUTPLAYERSNUMBER				= 'S_TimeOutPlayersNumber';
	const SETTING_MATCH_S_USEALTERNATERULES					= 'S_UseAlternateRules';
	const SETTING_MATCH_S_USECUSTOMPOINTSREPARTITION		= 'S_UseCustomPointsRepartition';
	const SETTING_MATCH_S_USETIEBREAK						= 'S_UseTieBreak';
	const SETTING_MATCH_S_WARMUPDURATION					= 'S_WarmUpDuration';
	const SETTING_MATCH_S_WARMUPNB							= 'S_WarmUpNb';
	const SETTING_MATCH_S_WARMUPTIMEOUT						= 'S_WarmUpTimeout';
	const SETTING_MATCH_S_WINNERSRATIO						= 'S_WinnersRatio';

	// RELATIONS BETWEEN GAMEMODE AND GAMEMODES SETTINGS
	const GAMEMODES_LIST_SETTINGS = [
		self::SETTING_MATCH_S_BESTLAPBONUSPOINTS => [ 
			'gamemode' => ['Champion'],
			'type' => 'integer',
			'default' => 2,
			'description' => 'Point bonus for who made the best lap time' ],
		self::SETTING_MATCH_S_CHATTIME => [
			'gamemode' => ['Global'],
			'type' => 'integer',
			'default' => 10,
			'description' => 'Time before loading the next map' ],
		self::SETTING_MATCH_S_CUMULATEPOINTS => [
			'gamemode' => ['Teams'],
			'type' => 'boolean',
			'default' => false,
			'description' => 'Cumulate players points to the team score (false = 1 point to the winner team)' ],
		self::SETTING_MATCH_S_DISABLEGIVEUP => [
			'gamemode' => ['Champion', 'Laps'],
			'type' => 'boolean',
			'default' => false,
			'description' => 'Disable GiveUp' ],
		self::SETTING_MATCH_S_EARLYENDMATCHCALLBACK => [
			'gamemode' => ['Champion', 'Knockout'],
			'type' => 'boolean',
			'default' => true,
			'description' => 'Send End Match Callback early (expert user only)' ],
		self::SETTING_MATCH_S_ELIMINATEDPLAYERSNBRANKS => [
			'gamemode' => ['Knockout'],
			'type' => 'string',
			'default' => '4',
			'description' => 'Rank at which one more player is eliminated per round (use coma to add more values. Ex COTD: 8,16,16)' ],
		self::SETTING_MATCH_S_ENDROUNDPOSTSCOREUPDATEDURATION => [
			'gamemode' => ['Champion'],
			'type' => 'integer',
			'default' => 5,
			'description' => 'Time after score computed on scoreboard' ],
		self::SETTING_MATCH_S_ENDROUNDPRESCOREUPDATEDURATION => [
			'gamemode' => ['Champion'],
			'type' => 'integer',
			'default' => 5,
			'description' => 'Time before score computed on scoreboard' ],
		self::SETTING_MATCH_S_FINISHTIMEOUT => [
			'gamemode' => ['Champion', 'Cup', 'Knockout', 'Laps', 'Teams', 'Rounds'],
			'type' => 'integer',
			'default' => 10,
			'description' => 'Time after the first finished (-1 = based on Author time)' ],
		self::SETTING_MATCH_S_FORCELAPSNB => [
			'gamemode' => ['Global'],
			'type' => 'integer',
			'default' => -1,
			'description' => 'Force number of laps for laps maps (-1 = author, 0 for unlimited in TA)' ],
		self::SETTING_MATCH_S_FORCEWINNERSNB => [
			'gamemode' => ['Champion'],
			'type' => 'integer',
			'default' => 0,
			'description' => 'Force the number of players who will win points (0 = for use S_WinnersRatio)' ],
		self::SETTING_MATCH_S_INFINITELAPS => [
			'gamemode' => ['Global'],
			'type' => 'boolean',
			'default' => false,
			'description' => 'Never end a race in laps (override S_ForceLapsNb)' ],
		self::SETTING_MATCH_S_MAPSPERMATCH => [
			'gamemode' => ['Teams', 'Rounds'],
			'type' => 'integer',
			'default' => 3,
			'description' => 'Number of maps maximum in the match' ],
		self::SETTING_MATCH_S_MATCHPOSITION => [
			'gamemode' => ['Knockout'],
			'type' => 'integer',
			'default' => -1,
			'description' => 'Server number to define global player rank (by server of 64, used for COTD, mostly useless)' ],
		self::SETTING_MATCH_S_MAXPOINTSPERROUND => [
			'gamemode' => ['Teams'],
			'type' => 'integer',
			'default' => 6,
			'description' => 'The maximum number of points attributed to the first player (exclude by S_UseCustomPointsRepartition)' ],
		self::SETTING_MATCH_S_NBOFWINNERS => [
			'gamemode' => ['Cup'],
			'type' => 'integer',
			'default' => 1,
			'description' => 'Number of winners' ],
		self::SETTING_MATCH_S_PAUSEBEFOREROUNDNB => [
			'gamemode' => ['Champion'],
			'type' => 'integer',
			'default' => 0,
			'description' => 'Run a pause before the round number (depend of S_PauseDuration) (0 = disabled)' ],
		self::SETTING_MATCH_S_PAUSEDURATION => [
			'gamemode' => ['Champion'],
			'type' => 'integer',
			'default' => 360,
			'description' => 'Pause time in seconds (depend of S_PauseBeforeRoundNb) (0 = disabled)' ],
		self::SETTING_MATCH_S_POINTSGAP => [
			'gamemode' => ['Teams'],
			'type' => 'integer',
			'default' => 0,
			'description' => 'Points Gap to win (depend of S_PointsLimit & S_UseTieBreak)' ],
		self::SETTING_MATCH_S_POINTSLIMIT => [
			'gamemode' => ['Champion', 'Cup', 'Teams', 'Rounds'],
			'type' => 'integer',
			'default' => 100,
			'description' => 'Limit number of points (0 = unlimited for Ch & R)' ],
		self::SETTING_MATCH_S_POINTSREPARTITION => [
			'gamemode' => ['Champion', 'Cup', 'Knockout', 'Teams', 'Rounds'],
			'type' => 'string',
			'default' => '10,6,4,3,2,1',
			'description' => 'Point repartition from first to last' ],
		self::SETTING_MATCH_S_RESPAWNBEHAVIOUR => [
			'gamemode' => ['Global'],
			'type' => 'integer',
			'default' => [0,1,2,3,4,5],
			'description' => 'Respawn behavior (0 = GM setting, 1 = normal, 2 = do nothing, 3 = DNF before 1st CP, 4 = always DNF, 5 = never DNF)' ],
		self::SETTING_MATCH_S_ROUNDSLIMIT => [
			'gamemode' => ['Champion'],
			'type' => 'integer',
			'default' => 6,
			'description' => 'Number of rounds to play before finding a winner of the step' ],
		self::SETTING_MATCH_S_ROUNDSPERMAP => [
			'gamemode' => ['Champion', 'Cup', 'Knockout', 'Teams', 'Rounds'],
			'type' => 'integer',
			'default' => 5,
			'description' => 'Number of rounds par map (0 = unlimited) (multiple maps doesn\'t work in KO)' ],
		self::SETTING_MATCH_S_ROUNDSWITHAPHASECHANGE => [
			'gamemode' => ['Champion'],
			'type' => 'string',
			'default' => '3,5',
			'description' => 'Rounds with a Phase change (Openning, Semi-Final, Final)' ],
		self::SETTING_MATCH_S_ROUNDSWITHOUTELIMINATION => [
			'gamemode' => ['Knockout'],
			'type' => 'integer',
			'default' => 1,
			'description' => 'Rounds without elimination (like a Warmup, but just for the first map)' ],
		self::SETTING_MATCH_S_TIMELIMIT => [
			'gamemode' => ['Champion', 'Laps', 'TimeAttack'],
			'type' => 'integer',
			'default' => 600,
			'description' => 'Time limit (0 = unlimited)' ],
		self::SETTING_MATCH_S_TIMEOUTPLAYERSNUMBER => [
			'gamemode' => ['Champion'],
			'type' => 'integer',
			'default' => 0,
			'description' => 'Number of players who must finish before starting S_FinishTimeout (0 = S_ForceWinnersNb)' ],
		self::SETTING_MATCH_S_USEALTERNATERULES => [
			'gamemode' => ['Teams'],
			'type' => 'boolean',
			'default' => false,
			'description' => 'F: Give 1 point to the all first players of a team | T: MaxPoints - Rank - 1 (exclude by S_UseCustomPointsRepartition)' ],
		self::SETTING_MATCH_S_USECUSTOMPOINTSREPARTITION => [
			'gamemode' => ['Teams'],
			'type' => 'boolean',
			'default' => false,
			'description' => 'Use S_PointsRepartition (instead of rules defined by S_UseAlternateRules)' ],
		self::SETTING_MATCH_S_USETIEBREAK => [
			'gamemode' => ['Champion', 'Teams', 'Rounds'],
			'type' => 'boolean',
			'default' => false,
			'description' => 'Use Tie Break (Only available when S_MapsPerMatch > 1)' ],
		self::SETTING_MATCH_S_WARMUPDURATION => [
			'gamemode' => ['Global'],
			'type' => 'integer',
			'default' => -1,
			'description' => 'Duration of 1 Warm Up in sec (-1 = one round, 0 = based on Author time)' ],
		self::SETTING_MATCH_S_WARMUPNB => [
			'gamemode' => ['Global'],
			'type' => 'integer',
			'default' => 1,
			'description' => 'Number of Warm Up' ],
		self::SETTING_MATCH_S_WARMUPTIMEOUT => [
			'gamemode' => ['Global'],
			'type' => 'integer',
			'default' => -1,
			'description' => 'Time after the first finished the WarmUP (-1 = based on Author time, only when S_WarmUpDuration = -1)' ],
		self::SETTING_MATCH_S_WINNERSRATIO => [
			'gamemode' => ['Champion'],
			'type' => 'float',
			'default' => 0.5,
			'description' => 'Ratio of players who will win points' ]
	];

	/*
	 * Private properties
	 */
	private $matchStarted			= false;
	/** @var ManiaControl $maniaControl */
	private $maniaControl			= null;
	private $chatprefix				= '$<$fc3$w🏆$m$> '; // Would like to create a setting but MC database doesn't support utf8mb4
	private $times					= array();
	private $nbmaps					= 0;
	private $nbrounds				= 0;
	private $playersreadystate		= array();
	private $nbplayers				= 0;
	private $nbspectators			= 0;
	private $currentgmbase			= "";
	private $currentmap				= null;
	private $matchrecover			= false;
	private $pointstorecover		= array();

	// Settings to keep in memory
	private $settings_nbroundsbymap	= 5;
	private $settings_nbwinner		= 2;
	private $settings_nbmapsbymatch	= 0;
	private $settings_pointlimit	= 100;

	private $nbwinners				= 0;
	private $nbstillalive			= 0;
	private $scriptSettings			= array();

	private $currentscore			= array();
	private $currentteamsscore		= array();
	private $playerpause			= array();
	private $pausetimer				= 0;
	private $pauseasked				= false;
	private $pauseon				= false;
	private $pauseaskedbyplayer		= "";
	private $numberpause			= 0;

	private $skipround				= false;

	private $settingsloaded			= false;
	private $postmatch				= false;
	private $mapsshuffled			= false;
	private $currentgmsettings		= [];

	private $matchid				= "";

	/**
	 * @param ManiaControl $maniaControl
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
		return 'Plugin offers a match Plugin';
	}


	public function load(ManiaControl $maniaControl) {
		// Init plugin
		$this->maniaControl = $maniaControl;
		$this->initTables();
		$this->maniaControl->getModeScriptEventManager()->unblockCallback("Trackmania.Event.WayPoint");
		$this->maniaControl->getModeScriptEventManager()->unBlockAllCallbacks();
		$this->maniaControl->getModeScriptEventManager()->enableCallbacks();

		//Settings
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_AUTHLEVEL, AuthenticationManager::getPermissionLevelNameArray(AuthenticationManager::AUTH_LEVEL_ADMIN), "Admin level needed to use the plugin");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_CUSTOM_GAMEMODE, "", "Load custom gamemode script (some functions can bug, for expert only)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_GAMEMODE_AFTERMATCH, array("TimeAttack"), "Gamemode to launch after the match");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_GAMEMODE_BASE, array("Champion", "Cup", "Knockout", "Laps", "Teams", "TimeAttack", "Rounds"), "Gamemode to launch for the match");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_LOAD_MAPLIST_FILE, false, "Load Maps + Matchsettings from the file (or use the current maplist on the server)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MAPLIST, "match.txt", "Maps + Matchsettings file to load (empty to use server login)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_MATCHSETTINGS_CONF, false, "Load configuration from matchsettings file instead of Admin Interface (can be usefull with a custom script)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_PAUSE_DURATION, 120, "Default Pause Duration in seconds");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_PAUSE_MAXNUMBER, 0, "Number of pause a player can ask during a match");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_PAUSE_POSX, 0, "Position of the Pause Countdown (on X axis)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_PAUSE_POSY, 43, "Position of the Pause Countdown (on Y axis)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_READY_HEIGHT, 6, "Height of the Ready widget");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_READY_MODE, false, "Activate Ready widget");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_READY_NBPLAYERS, 2, "Minimal number of players to start a match if all are ready");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_READY_POSX, 152.5, "Position of the Ready widget (on X axis)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_READY_POSY, 40, "Position of the Ready widget (on Y axis)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_READY_WIDTH, 15, "Width of the Ready widget");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_SHUFFLEMAPS, true, "Shuffle maps order");

		// Gamemodes Settings
		foreach (self::GAMEMODES_LIST_SETTINGS as $setting => $n) {
			$this->maniaControl->getSettingManager()->initSetting($this, $setting, $n['default'], $this->getDescriptionPrefix($setting) . $n['description']);
		}

		//Register Admin Commands
		$this->maniaControl->getCommandManager()->registerCommandListener('matchstart', $this, 'onCommandMatchStart', true, 'Start a match');
		$this->maniaControl->getCommandManager()->registerCommandListener('matchstop', $this, 'onCommandMatchStop', true, 'Stop a match');
		$this->maniaControl->getCommandManager()->registerCommandListener('matchrecover', $this, 'onCommandMatchRecover', true, 'Recover a match');
		$this->maniaControl->getCommandManager()->registerCommandListener('matchendround', $this, 'onCommandMatchEndRound', true, 'Force end a round during a match');
		$this->maniaControl->getCommandManager()->registerCommandListener('matchendwu', $this, 'onCommandMatchEndWU', true, 'Force end a WU during a match');
		$this->maniaControl->getCommandManager()->registerCommandListener('matchsetpoints', $this, 'onCommandSetPoints', true, 'Sets points to a player.');
		$this->maniaControl->getCommandManager()->registerCommandListener(array('matchpause','pause'), $this, 'onCommandSetPause', true, 'Set pause during a match. [time] in seconds can be added to force another value');
		$this->maniaControl->getCommandManager()->registerCommandListener(array('matchendpause','endpause'), $this, 'onCommandUnsetPause', true, 'End the pause during a match.');

		//Register Player Commands
		$this->maniaControl->getCommandManager()->registerCommandListener('pause', $this, 'onCommandSetPausePlayer', false, 'Set pause during a match.');
		$this->maniaControl->getCommandManager()->registerCommandListener('ready', $this, 'onCommandSetReadyPlayer', false, 'Change status to Ready.');

		//Register Callbacks
		$this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(GameModeSettings::CB_GAMEMODESETTINGS_CHANGED, $this, 'updateGMvariables');

		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerConnect');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERDISCONNECT, $this, 'handlePlayerDisconnect');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERINFOCHANGED, $this, 'handlePlayerInfoChanged');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_SCORES, $this, 'handleEndRoundCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::MP_STARTROUNDSTART, $this, 'handleBeginRoundCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_WARMUPSTARTROUND, $this, 'handleStartWarmUpCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(CallbackManager::CB_MP_BEGINMATCH, $this, 'handleBeginMatchCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_ONFINISHLINE, $this, 'handleFinishCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(CallbackManager::CB_MP_MAPLISTMODIFIED, $this, 'handleMapListModified');

		// Register Socket commands
		$this->maniaControl->getCommunicationManager()->registerCommunicationListener("Match.GetMatchStatus", $this, function () { return new CommunicationAnswer($this->getMatchStatus()); });
		$this->maniaControl->getCommunicationManager()->registerCommunicationListener("Match.GetCurrentScore", $this, function () { return new CommunicationAnswer($this->getCurrentScore()); });
		$this->maniaControl->getCommunicationManager()->registerCommunicationListener("Match.GetPlayers", $this, function () { return new CommunicationAnswer($this->getPlayers()); });
		$this->maniaControl->getCommunicationManager()->registerCommunicationListener("Match.MatchStart", $this, function () { return new CommunicationAnswer($this->MatchStart()); });
		$this->maniaControl->getCommunicationManager()->registerCommunicationListener("Match.MatchStop", $this, function () { return new CommunicationAnswer($this->MatchStop()); });
		$this->maniaControl->getCommunicationManager()->registerCommunicationListener("Match.GetMatchOptions", $this, function () { return new CommunicationAnswer($this->getGMSettings()); });

		// Register Timers
		$this->maniaControl->getTimerManager()->registerTimerListening($this, 'Periodic5SecondsLoop', 5000);

		// Register ManiaLink Pages
		$this->maniaControl->getManialinkManager()->registerManialinkPageAnswerListener(self::ACTION_READY, $this, 'handleReady');

		// Init Ready mode
		$players = $this->maniaControl->getPlayerManager()->getPlayers();
		foreach ($players as $player) {
			$this->handlePlayerConnect($player);
		}

	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {
		$this->closeReadyWidget();
	}

	/**
	 * Initialize needed database tables
	 */
	private function initTables() {
		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		$query = 'CREATE TABLE IF NOT EXISTS `' . self::DB_MATCHESINDEX . '` (
			`matchid` VARCHAR(100) NOT NULL,
			`server` VARCHAR(60) NOT NULL,
			`gamemodebase` VARCHAR(32) NOT NULL,
			`started` INT(10) NOT NULL,
			`ended` INT(10) NOT NULL,
				PRIMARY KEY (`matchid`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
		$mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error, E_USER_ERROR);
		}
		$query = 'CREATE TABLE IF NOT EXISTS `' . self::DB_ROUNDSINDEX . '` (
			`matchid` VARCHAR(100) NOT NULL,
			`timestamp` INT(10) NOT NULL,
			`nbmaps` INT(4) NOT NULL,
			`nbrounds` INT(4) NOT NULL,
			`settings` TEXT NOT NULL,
			`map` VARCHAR(100) NOT NULL,
			`nbplayers` INT(4) NOT NULL,
			`nbspectators` INT(4) NOT NULL
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
		$mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error, E_USER_ERROR);
		}
		$query = 'CREATE TABLE IF NOT EXISTS `' . self::DB_ROUNDSDATA . '` (
			`matchid` VARCHAR(100) NOT NULL,
			`timestamp` INT(10) NOT NULL,
			`rank` INT(4) NOT NULL,
			`login` VARCHAR(30) NOT NULL,
			`matchpoints` INT(10) NOT NULL,
			`roundpoints` INT(10) NOT NULL,
			`time` INT(10) NOT NULL,
			`teamid` VARCHAR(30) NOT NULL
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
		$mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error, E_USER_ERROR);
		}
		$query = 'CREATE TABLE IF NOT EXISTS `' . self::DB_TEAMSDATA . '` (
			`matchid` VARCHAR(100) NOT NULL,
			`timestamp` INT(10) NOT NULL,
			`id` VARCHAR(30) NOT NULL,
			`team` VARCHAR(30) NOT NULL,
			`points` INT(10) NOT NULL
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
		$mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error, E_USER_ERROR);
		}
	}

	/**
	 * Generate description prefix (ex: [*] or [Ch,C,KO])
	 *
	 * @param string $gamesetting
	*/
	public function getDescriptionPrefix(string $gamesetting) {
		if (in_array('Global', self::GAMEMODES_LIST_SETTINGS[$gamesetting]['gamemode'])) {
			$prefix = '[*] ';
		} else {
			$prefix = '[';
			foreach (self::GAMEMODES_LIST_SETTINGS[$gamesetting]['gamemode'] as $gamemode) {
				if ($gamemode == "Champion") {
					$prefix .= 'Ch,';
				} elseif ($gamemode == "TimeAttack") {
					$prefix .= 'TA,';
				} elseif ($gamemode == "Knockout") {
					$prefix .= 'KO,';
				} else {
					$prefix .= substr($gamemode, 0 ,1) . ',';
				}
			}
			$prefix = substr($prefix, 0, -1) . '] ';
		}
		return $prefix;
	}

	public function getMatchStatus() {
		return $this->matchStarted;
	}

	public function getCountRound() {
		return $this->nbrounds . "/" . $this->settings_nbroundsbymap;
	}

	public function getCountMap() {
		return $this->nbmaps . "/" . $this->settings_nbmapsbymatch;
	}

	public function getCurrentGamemodeSettings() {
		return $this->currentgmsettings;
	}

	public function getCurrentScore() {
		return $this->currentscore;
	}

	public function getCurrentTeamsScore() {
		return $this->currentteamsscore;
	}

	public function getPlayersReadyState() {
		return $this->playersreadystate;
	}

	public function getRoundNumber() {
		return $this->nbrounds;
	}

	public function getMatchPointsLimit() {
		return $this->settings_pointlimit;
	}

	public function getNbWinners() {
		return $this->settings_nbwinners;
	}

	/**
	 * Update Widgets on Setting Changes
	 *
	 * @param Setting $setting
	*/
	public function updateSettings(Setting $setting = null) {

		if ($setting->belongsToClass($this)) {
			$players = $this->maniaControl->getPlayerManager()->getPlayers();

			foreach ($players as $player) {
				$this->displayReadyWidget($player->login);
			}

			if ($this->matchStarted) {
				if ($setting->setting == self::SETTING_MATCH_GAMEMODE_BASE && $setting->value != $this->currentgmbase) {
					$setting->value = $this->currentgmbase; 
					$this->maniaControl->getSettingManager()->saveSetting($setting);
					$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . 'You can\'t change Gamemode during a Match');
				} else {
					if (!$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCHSETTINGS_CONF)) {
						Logger::log("Load Script Settings");
						try {
							$this->maniaControl->getClient()->setModeScriptSettings($this->getGMSettings());
							Logger::log("Parameters updated");
							$this->maniaControl->getChat()->sendSuccessToAdmins($this->chatprefix . 'Parameters updated');
						} catch (InvalidArgumentException $e) {
							Logger::log("Parameters not updated");
							$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . 'Parameters not updated');
						}
						$this->updateGMvariables();
					} else {
						$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . 'Settings are loaded by Matchsettings file only.');
					}
				}
			}
		}
	}

	/**
	 * Get Array with all settings of the Gamemode
	*/
	public function getGMSettings() {
		$gamesettings = [];
		foreach (self::GAMEMODES_LIST_SETTINGS as $gamesetting => $info) {
			if (in_array('Global', $info['gamemode']) || in_array($this->currentgmbase, $info['gamemode'])) {
				$value = $this->maniaControl->getSettingManager()->getSettingValue($this, $gamesetting);
				settype($value, $info['type']);
				$gamesettings = array_merge($gamesettings , array($gamesetting => $value ));
			}
		}
		return $gamesettings;
	}

	/**
	 * Load functionnal variables
	*/
	public function updateGMvariables() {
		Logger::log("Updating internal variables");
		$this->currentgmsettings = $this->maniaControl->getClient()->getModeScriptSettings();

		if (isset($this->currentgmsettings[self::SETTING_MATCH_S_POINTSLIMIT])) {
			$this->settings_pointlimit		= (int) $this->currentgmsettings[self::SETTING_MATCH_S_POINTSLIMIT];
		}
		if (isset($this->currentgmsettings[self::SETTING_MATCH_S_NBOFWINNERS])) {
			$this->settings_nbwinners		= (int) $this->currentgmsettings[self::SETTING_MATCH_S_NBOFWINNERS];
		}
		if (isset($this->currentgmsettings[self::SETTING_MATCH_S_ROUNDSPERMAP])) {
			$this->settings_nbroundsbymap	= (int) $this->currentgmsettings[self::SETTING_MATCH_S_ROUNDSPERMAP];
		}
		if ($this->currentgmbase == "Champion") {
			$this->settings_nbmapsbymatch	= (int) $this->currentgmsettings[self::SETTING_MATCH_S_ROUNDSLIMIT];
		} elseif (isset($this->currentgmsettings[self::SETTING_MATCH_S_MAPSPERMATCH])) {	
			$this->settings_nbmapsbymatch	= (int) $this->currentgmsettings[self::SETTING_MATCH_S_MAPSPERMATCH];
		}
		if (isset($this->currentgmsettings[self::SETTING_MATCH_S_POINTSGAP])) {
			$this->settings_pointsgap		= (int) $this->currentgmsettings[self::SETTING_MATCH_S_POINTSGAP];
		}
		if (isset($this->currentgmsettings[self::SETTING_MATCH_S_USETIEBREAK])) {
			$this->settings_usetiebreak		= (int) $this->currentgmsettings[self::SETTING_MATCH_S_USETIEBREAK];
		}
	}

	/**
	 * Function called to list matches
	 */
	public function getMatchesList($limit = 10) {
		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		$query = "SELECT `gamemodebase`,`started`,`ended` FROM `" . self::DB_MATCHESINDEX . "`
				ORDER BY `started` DESC LIMIT " . $limit;
		$result = $mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error);
			return false;
		}
		while($row = $result->fetch_array()) {
			$array[] = $row;
		}
		return $array;
	}

	/**
	 * Function called to start the match
	 */
	public function MatchStart() {
		$this->matchid = $this->maniaControl->getServer()->login . "-" . time();
		$this->currentgmbase = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_GAMEMODE_BASE);
		$maplist = "";

		if (empty($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_CUSTOM_GAMEMODE))) {
			$scriptName = "Trackmania/TM_" ;
			$scriptName .= $this->currentgmbase;
			$scriptName .= "_Online.Script.txt";
			$this->maniaControl->getChat()->sendSuccess($this->chatprefix . 'Match start in ' . $this->currentgmbase . ' mode!');
		} else {
			$scriptName = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_CUSTOM_GAMEMODE);
			$this->maniaControl->getChat()->sendSuccess($this->chatprefix . 'Match start with script ' . $scriptName . ' (based on ' . $this->currentgmbase . ')');

		}

		Logger::log("Match start with script " . $scriptName . '!');

		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_LOAD_MAPLIST_FILE)) {
			Logger::log("Loading maplist + matchsettings");
			if (empty($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MAPLIST))) {
				$server = $this->maniaControl->getServer()->login;
				$maplist = 'MatchSettings' . DIRECTORY_SEPARATOR . $server . ".txt";
			} else {
				$maplist = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MAPLIST);
				$maplist = 'MatchSettings' . DIRECTORY_SEPARATOR . $maplist;
			}
			Logger::log("Load matchsettings: " . $maplist);
			if (!is_file($this->maniaControl->getServer()->getDirectory()->getMapsFolder() . $maplist)) {
				Logger::log("The Maplist file is not accessible or does not exist (the match has not started)");
				$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . "The Maplist file is not accessible or does not exist (the match has not started)");
				return;
			}
			$this->maniaControl->getClient()->loadMatchSettings($maplist);
			Logger::log("Restructure maplist");
			$this->maniaControl->getMapManager()->restructureMapList();
		} else {
			if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCHSETTINGS_CONF)) {
				$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . 'FYI, No Settings will be load, check "' . self::SETTING_MATCH_MATCHSETTINGS_CONF . "\" and \"" . self::SETTING_MATCH_MAPLIST . "\"");
			}
		}

		Logger::log("Load Script");
		$this->maniaControl->getClient()->setScriptName($scriptName);

		$this->matchStarted = true;
		$this->nbmaps		= 0;
		$this->nbrounds		= 0;

		Logger::log("Get Players");
		$players = $this->maniaControl->getPlayerManager()->getPlayers();

		Logger::log("Player State");
		foreach ($players as $player) {
			$this->handlePlayerConnect($player);
		}

		//Reset Player Pause counter
		foreach ($players as $player) {
			if (!$player->isSpectator) {
				$this->playerpause[$player->login] = 0;
			}
		}
		$this->pauseasked	 = false;
		$this->pauseaskedbyplayer = "";

		// MYSQL DATA INSERT
		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		$query = 'INSERT INTO `' . self::DB_MATCHESINDEX . '`
			(`matchid`, `server`, `gamemodebase`, `started`, `ended`)
			VALUES
			("' . $this->matchid . '","' . $this->maniaControl->getServer()->login . '","' . $this->currentgmbase . '","' . time() . '","0" )';
		$mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error);
			return false;
		}

		// Trigger Callback
		$settings = [
			'currentgmbase' => $this->currentgmbase,
			'scriptName'	=> $scriptName,
			'maplist'		=> $maplist,
			'mapsshuffled'	=> $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_SHUFFLEMAPS)];
		$this->maniaControl->getCallbackManager()->triggerCallback(self::CB_MATCHMANAGER_STARTMATCH, $this->matchid, $settings);

		Logger::log("Skip map");
		$this->maniaControl->getMapManager()->getMapActions()->skipMap();		
	}

	/**
	 * Function called to end the match
	 */
	public function MatchEnd() {
		$scriptName = "Trackmania/TM_" ;
		$scriptName .= $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_GAMEMODE_AFTERMATCH);
		$scriptName .= "_Online.Script.txt";

		try {
			$this->maniaControl->getClient()->setScriptName($scriptName);

			// MYSQL DATA INSERT
			$settings = json_encode($this->maniaControl->getClient()->getModeScriptSettings());
			$mysqli = $this->maniaControl->getDatabase()->getMysqli();
			$query = 'UPDATE `' . self::DB_MATCHESINDEX . '` SET `ended` = "' . time() . '" WHERE `matchid` = "' . $this->matchid . '"';
			$mysqli->query($query);
			if ($mysqli->error) {
				trigger_error($mysqli->error);
			}

			// Trigger Callback
			$this->maniaControl->getCallbackManager()->triggerCallback(self::CB_MATCHMANAGER_ENDMATCH, $this->matchid, $this->currentscore, $this->currentteamsscore);

			// End notifications
			$this->maniaControl->getChat()->sendSuccess($this->chatprefix . "Match finished");
			Logger::log("Loading script: $scriptName");
			Logger::log("Match finished");
			$this->matchStarted		= false;
			$this->matchrecover		= false;
			$this->pointstorecover	= array();
			$this->currentscore		= [];
			$this->settingsloaded	= false;
			$this->mapsshuffled		= false;
			$this->postmatch		= true;
			$this->matchid			= "";

			// KO Specifics variables
			$this->nbstillalive = 0;

			// Teams Specifics variables
			$this->currentteamsscore = [];

			$players = $this->maniaControl->getPlayerManager()->getPlayers();
			foreach ($players as $player) {
				$this->handlePlayerConnect($player);
			}

		} catch (Exception $e) {
			$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . 'Can not finish match: ' . $e->getMessage());
		}
	}

	/**
	 * Function called to stop the match
	 */
	public function MatchStop() {
		Logger::log("Match stop");
		$scriptName = "Trackmania/TM_" ;
		$scriptName .= $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_GAMEMODE_AFTERMATCH);
		$scriptName .= "_Online.Script.txt";

		try {

			// Trigger Callback
			$this->maniaControl->getCallbackManager()->triggerCallback(self::CB_MATCHMANAGER_STOPMATCH, $this->matchid, $this->currentscore, $this->currentteamsscore);

			$this->maniaControl->getChat()->sendError($this->chatprefix . 'Match stopped by an Admin!');
			$this->maniaControl->getClient()->setScriptName($scriptName);
			Logger::log("Loading script: $scriptName");
			$this->matchStarted		= false;
			$this->matchrecover		= false;
			$this->pointstorecover	= array();
			$this->currentscore		= [];
			$this->settingsloaded	= false;
			$this->mapsshuffled		= false;
			$this->postmatch		= true;
			$this->matchid			= "";

			$players = $this->maniaControl->getPlayerManager()->getPlayers();
			foreach ($players as $player) {
				$this->handlePlayerConnect($player);
			}

		} catch (Exception $e) {
			$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . 'Can not stop match: ' . $e->getMessage());
		}
		Logger::log("Restarting map to load Gamemode");
		$this->maniaControl->getClient()->restartMap();
	}

	/**
	 * Function called to recover a match
	 * @param integer $index
	 */
	public function MatchRecover(Int $index) {
		Logger::log("Match Recover");

		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		$query = "SELECT `matchid`,`gamemodebase` FROM `" . self::DB_MATCHESINDEX . "`
				ORDER BY `started` DESC LIMIT " . $index . ",1";
		$result = $mysqli->query($query);
		$array = mysqli_fetch_array($result);
		if (isset($array[0])) {
			$gamemodebase = $array['gamemodebase'];
			$matchid = $array['matchid'];
			unset($array);
			$this->matchrecover = true;
			$query = "SELECT `timestamp`,`settings`,`nbmaps`,`nbrounds` FROM `" . self::DB_ROUNDSINDEX . "`
					WHERE `matchid` = '" . $matchid . "'
					ORDER BY `timestamp` DESC LIMIT 1";
			$result = $mysqli->query($query);
			$array = mysqli_fetch_array($result);
			if (isset($array[0])) {
				$nbmaps=$array['nbmaps'];
				$nbrounds=$array['nbrounds'];
				$settings=$array['settings'];
				$timestamp=$array['timestamp'];
				unset($array);
				if ($gamemodebase == "Teams") {
					$query = "SELECT `id` AS login,`points` AS matchpoints FROM `" . self::DB_TEAMSDATA . "`
					WHERE `timestamp` = (SELECT `timestamp` FROM `" . self::DB_TEAMSDATA . "`
					WHERE `matchid` = '" . $matchid . "' ORDER BY `timestamp` DESC LIMIT 1)" ;
				} else {
					$query = "SELECT `login`,`matchpoints` FROM `" . self::DB_ROUNDSDATA . "`
					WHERE `timestamp` = '" . $timestamp . "'";
				}
				$result = $mysqli->query($query);
				if ($mysqli->error) {
					trigger_error($mysqli->error);
					return false;
				}
				while($row = $result->fetch_array()) {
					$array[] = $row;
				}
				if (isset($array[0])) {
					$this->matchrecover = true;
					foreach ($array as $index => $value) {
						if (isset($value['login'])) {
							$this->pointstorecover[$value['login']] = $value['matchpoints'];
						}
					}
					$this->maniaControl->getChat()->sendSuccess($this->chatprefix . 'Recovering the match: ' . $matchid );
					Logger::log('Recovering the match: ' . $matchid);
					$this->MatchStart();
				} else {
					$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . 'No data found from the last round');
				}
			} else {
				$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . 'No Rounds found for this match');
			}
		} else {
			$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . 'Match not found');
		}
	}

	/**
	 * Function called to recover points
	 */
	private function recoverPoints() {
		if (!empty($this->pointstorecover)) {
			if ($this->currentgmbase == "Teams") {
				// Blue Team
				$this->maniaControl->getModeScriptEventManager()->setTrackmaniaTeamPoints("0", "", $this->pointstorecover[0], $this->pointstorecover[0]);
				$this->maniaControl->getChat()->sendSuccess($this->chatprefix . '$<$ff0' . $this->pointstorecover[0] . '$> points recovered for the $<$00fBlue$> Team');
				// Red Team
				$this->maniaControl->getModeScriptEventManager()->setTrackmaniaTeamPoints("1", "", $this->pointstorecover[1], $this->pointstorecover[1]);
				$this->maniaControl->getChat()->sendSuccess($this->chatprefix . '$<$ff0' . $this->pointstorecover[1] . '$> points recovered for the $<$f00Red$> Team');
				Logger::log("Point recovered: Blue " . $this->pointstorecover[0] . " - Red " . $this->pointstorecover[1]);
				$this->pointstorecover = [];
			} else {
				foreach ($this->pointstorecover as $index => $value) {
					$player = $this->maniaControl->getPlayerManager()->getPlayer($index, true);
					if ($player) {
						if (!empty($this->currentscore)) {
							$key = array_search($index, array_column($this->currentscore, '1'));
							if (!($key === false)) {
								$points = $value + $this->currentscore[$key][2];
							} else {
								$points = $value;
							}
						} else {
							$points = $value;
						}
						$this->maniaControl->getModeScriptEventManager()->setTrackmaniaPlayerPoints($player, "", "", $points);
						$this->maniaControl->getChat()->sendSuccess($this->chatprefix . 'Your $<$ff0' . $value . '$> points have been recovered', $player);
						unset($this->pointstorecover[$index]);
						Logger::log("Point recovered: " . $index . " " . $points . "(+" . $value . ")");
					}
				}
			}
		}
	}

	/**
	 * Set pause
	 * 
	 * @param boolean $admin 
	 * @param integer $time
	 */
	private function setNadeoPause($admin = false, $time = null) {
		Logger::log("Nadeo Pause");

		if ($time === null) {
			$this->pausetimer = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_PAUSE_DURATION);
		} else {
			$this->pausetimer = $time;
		}

		$this->numberpause++;
		$numberpause	= $this->numberpause;
		$this->pauseon	= true;

		$this->maniaControl->getModeScriptEventManager()->startPause();
		$this->maniaControl->getChat()->sendSuccessToAdmins($this->chatprefix . 'You can interrupt the pause with the command //matchendpause');


		if ($this->pausetimer > 0) {
				$this->maniaControl->getTimerManager()->registerOneTimeListening($this, function () use (&$numberpause) {

				if ($numberpause == $this->numberpause) {
					$this->unsetNadeoPause();
				} else {
					Logger::log("Pause not stopped because another is in progress!");
				}
			}, $this->pausetimer * 1000);
			$this->displayPauseWidget($numberpause);
		}
	}

	/**
	 * Unset pause
	 */
	private function unsetNadeoPause() {
		if ($this->pauseon) {
			Logger::log("End Pause");
			$this->closePauseWidget();
			$this->maniaControl->getModeScriptEventManager()->endPause();			
		}
	}

	/**
	 * Display (or not) the Ready Widget
	 *
	 * @param string $login
	 */
	public function displayReadyWidget($login) {
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_MODE) && (!$this->matchStarted)) {
			$posX			= $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_POSX);
			$posY			= $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_POSY);
			$width			= $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_WIDTH);
			$height			= $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_HEIGHT);
			$quadStyle		= $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadStyle();
			$quadSubstyle	= $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadSubstyle();

			$maniaLink = new ManiaLink(self::MLID_MATCH_READY_WIDGET);

			// mainframe
			$frame = new Frame();
			$maniaLink->addChild($frame);
			$frame->setSize($width, $height);
			$frame->setPosition($posX, $posY);

			// Background Quad
			$backgroundQuad = new Quad();
			$frame->addChild($backgroundQuad);
			$backgroundQuad->setSize($width, $height);
			$backgroundQuad->setStyles($quadStyle, $quadSubstyle);
			$backgroundQuad->setAction(self::ACTION_READY);

			$label = new Label_Text();
			$frame->addChild($label);
			$label->setPosition(0, 1.5, 0.2);
			$label->setVerticalAlign($label::TOP);
			$label->setTextSize(2);

			if ($this->playersreadystate[$login] == 1) {
				$label->setTextColor('0f0');
			} else {
				$label->setTextColor('f00');
			}
			$label->setText("Ready?");

			// Send manialink
			$this->maniaControl->getManialinkManager()->sendManialink($maniaLink, $login);
		} else {
			$this->playersreadystate[$login] = 0;
			$this->closeReadyWidget($login);
		}
	}

	/**
	 * Display Pause Widget
	 *
	 * @param integer $numberpause
	 */
	public function displayPauseWidget($numberpause) {
		$posX = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_PAUSE_POSX);
		$posY = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_PAUSE_POSY);

		$maniaLink = new ManiaLink(self::MLID_MATCH_PAUSE_WIDGET);

		// mainframe
		$frame = new Frame();
		$maniaLink->addChild($frame);
		$frame->setSize(30, 20);
		$frame->setPosition($posX, $posY);


		$label = new Label_Text();
		$frame->addChild($label);

		$label->setPosition(0, 2, 0.2);

		$label->setVerticalAlign($label::TOP);
		$label->setTextSize(8);
		$label->setTextFont("GameFontBlack");
		$label->setTextPrefix('$s');

		if ($this->pausetimer < 10) {
			$label->setTextColor('f00');
		} else {
			$label->setTextColor('fff');
		}
		$label->setText($this->pausetimer);

		// Send manialink
		$this->maniaControl->getManialinkManager()->sendManialink($maniaLink);

		$this->maniaControl->getTimerManager()->registerOneTimeListening($this, function () use (&$numberpause) {
			if ($numberpause == $this->numberpause) {
				if ($this->pausetimer > 0 && $this->pauseon) {
					$this->pausetimer--;
					$this->displayPauseWidget($numberpause);
				} else {
					$this->closePauseWidget();
				}
			}
		}, 1000);
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
	 * Close Ready Widget
	 *
	 * @param string $login
	 */
	public function closeReadyWidget($login = null) {
		$this->maniaControl->getManialinkManager()->hideManialink(self::MLID_MATCH_READY_WIDGET, $login);
	}

	/**
	 * Run this function every 5 seconds. Used to start a match with Ready button
	 */
	public function Periodic5SecondsLoop() {
		if (!$this->matchStarted) {
			if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_MODE)) {
				$nbplayers = $this->maniaControl->getPlayerManager()->getPlayerCount();
				if ($nbplayers >= $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_NBPLAYERS)) {
					$nbready = 0;
					foreach ($this->playersreadystate as $readystate) {
						if ($readystate == 1) {
							$nbready++;
						}
					}
					if ($nbready >= $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_NBPLAYERS)) {
						$players = $this->maniaControl->getPlayerManager()->getPlayers();
						foreach ($players as $player) {
							$this->playersreadystate[$player->login] = 0;
						}
						Logger::log('Start Match via Ready Button');
						$this->MatchStart();
					}
				}
			}
		}
	}

	/**
	 * Handle when a player connect
	 *
	 * @param \ManiaControl\Players\Player $player
	 */
	public function handlePlayerConnect(Player $player) {
		if ($player->isSpectator) {
			$this->playersreadystate[$player->login] = -1;
			$this->nbspectators++;
		} else {
			$this->playersreadystate[$player->login] = 0;
			$this->nbplayers++;
			$this->displayReadyWidget($player->login);
		}

		if ($this->pauseon) {
			$this->displayPauseWidget($player->login);
		}

		if ($this->matchStarted) {
			if (!isset($this->playerpause[$player->login])) {
				$this->playerpause[$player->login] = 0;
			}
		}
	}

	/**
	 * Handle when a player disconnects
	 * 
	 * @param \ManiaControl\Players\Player $player
	*/
	public function handlePlayerDisconnect(Player $player) {
		$this->playersreadystate[$player->login] = -1;
		$this->nbplayers--;
		$this->closeReadyWidget($player->login);
		$this->closePauseWidget($player->login);
	}

	/**
	 * Handle Ready state of the player
	 * 
	 * @param array			$callback
	 * @param \ManiaControl\Players\Player $player
	*/
	public function handleReady(array $callback, Player $player) {
		Logger::log("handleReady");
		if ($this->playersreadystate[$player->login]) {
			if ($this->playersreadystate[$player->login] == 0) {
				$this->playersreadystate[$player->login] = 1;
				$this->nbplayers++;
				$this->displayReadyWidget($player->login);
				$this->maniaControl->getChat()->sendInformation($this->chatprefix . 'Player $<$ff0' . $player->nickname . '$> now is $<$z$0f0ready$>');
			} elseif ($this->playersreadystate[$player->login] == 1) {
				$this->playersreadystate[$player->login] = 0;
				$this->nbplayers--;
				$this->displayReadyWidget($player->login);
				$this->maniaControl->getChat()->sendInformation($this->chatprefix . 'Player $<$ff0' . $player->nickname . '$> now is $<$z$f00not ready$>');
			}
		} else {
			$this->playersreadystate[$player->login] = 1;
			$this->nbplayers++;
			$this->displayReadyWidget($player->login);
			$this->maniaControl->getChat()->sendInformation($this->chatprefix . 'Player $<$ff0' . $player->nickname . '$> now is $<$z$0f0ready$>');
		}
	}

	/**
	 * Handle callback "MapListModified"
	 */
	public function handleMapListModified() {
		Logger::log("handleMapListModified");
		if ($this->matchStarted && !$this->mapsshuffled && $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_SHUFFLEMAPS)) {
			Logger::log("Shuffle maplist");
			$this->maniaControl->getMapManager()->shuffleMapList();
			$this->mapsshuffled = true;
		}
	}

	/**
	 * Handle callback "BeginMatch"
	 */
	public function handleBeginMatchCallback() {
		Logger::log("handleBeginMatchCallback");

		if ($this->matchStarted === true) {
			Logger::log("Check settingsloaded: " . $this->settingsloaded);
			if (!($this->settingsloaded))
			{
				Logger::log("Loading settings");
				$this->settingsloaded = true;

				if (!$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_MATCHSETTINGS_CONF)) {
					Logger::log("Load Script Settings");
					$this->maniaControl->getClient()->setModeScriptSettings($this->getGMSettings());
				}

				$this->updateGMvariables();

				Logger::log("Restarting Map for load settings");
				$this->maniaControl->getClient()->restartMap();
			}
			else {
				$this->nbmaps++;
				Logger::log('nbmaps: ' . $this->nbmaps);

				$this->nbrounds = 0;

				if ($this->nbmaps > 0) {
					Logger::log("Map: " . $this->nbmaps);

					$maps = $this->maniaControl->getMapManager()->getMaps();
					$nbmaps = $this->maniaControl->getMapManager()->getMapsCount();

					$i = 1;
					$this->currentmap = $this->maniaControl->getMapManager()->getCurrentMap();
					$message = $this->chatprefix . '$<$o$iCurrent Map:$>' . "\n";
					$message .= Formatter::stripCodes($this->currentmap->name);
					$this->maniaControl->getChat()->sendInformation($message);
					Logger::log("Current Map: " . Formatter::stripCodes($this->currentmap->name));
					$continue = false;
					$current = false;
					$message = "";
					foreach ($maps as $map) { //TODO check no nextmap in cup mode
						if ($this->currentmap->uid == $map->uid) {
							$continue = true;
							$current = true;
						}
						if ($continue && $this->nbmaps < $this->settings_nbmapsbymatch) {
							if ($current) {
								$current = false;
							} elseif (($this->nbmaps + $i) <= ($this->settings_nbmapsbymatch)) {
								Logger::log("i: " . $i);
								if ($i > 0) {
									if ($i == 1) {
										$message = $this->chatprefix . '$<$o$iNext Maps:$>';
									}
									Logger::log("Map " . $i . ": " . Formatter::stripCodes($map->name));
									$message .= "\n" . $i . ": " . Formatter::stripCodes($map->name);
									$i++;
									if ($this->nbmaps == ($nbmaps - ($i - 1) + 1) or $this->settings_nbmapsbymatch == (($i - 1) + $this->nbmaps - 1)) {
										$continue = false;
									}
								}
							}
						}
					}
					if (!empty($message)) {
						$this->maniaControl->getChat()->sendInformation($message);
					}
				}

				// Trigger Callback
				$currentstatus = [
					'nbmaps'			=> $this->nbmaps,
					'settings_nbmapsbymatch'	=> $this->settings_nbmapsbymatch];
				$this->maniaControl->getCallbackManager()->triggerCallback(self::CB_MATCHMANAGER_BEGINMAP,$this->matchid, $currentstatus, $this->currentmap);
			}
		}
		if ($this->postmatch) {
			$scriptName = $this->maniaControl->getClient()->getScriptName()['CurrentValue'];
			Logger::log("scriptName: " . $scriptName);
			if ($scriptName == "Trackmania/TM_TimeAttack_Online.Script.txt") {
				$this->postmatch = false;	

				Logger::log("Load Script Settings");
				$postmatchsettings = [
					self::SETTING_MATCH_S_FORCELAPSNB => 0,
					self::SETTING_MATCH_S_RESPAWNBEHAVIOUR => 0,
					self::SETTING_MATCH_S_WARMUPNB => 0,
					self::SETTING_MATCH_S_TIMELIMIT => 600
				];
				$this->maniaControl->getClient()->setModeScriptSettings($postmatchsettings);

				Logger::log("Restarting Map for load settings");
				$this->maniaControl->getClient()->restartMap();
			} else {
				// Depending of the load of the server and the match gamemode, the script could be not loaded, this is a workaround to reload thhe script
				$this->maniaControl->getClient()->restartMap();
			}

		}
	}

	/**
	 * Handle callback "WarmUp.StartRound"
	 */
	public function handleStartWarmUpCallback() {
		Logger::log("handleStartWarmUpCallback");

		// Match Recover
		if ($this->matchrecover) {
			$this->recoverPoints();
		}
	}

	/**
	 * Handle callback "BeginRound"
	 */
	public function handleBeginRoundCallback() {
		Logger::log("handleBeginRoundCallback");
		$this->times	 = array();

		if ($this->pauseasked) {
			$this->pauseasked = false;
			Logger::log("Set Pause");
			$this->setNadeoPause();
		}

		if ($this->matchStarted && $this->nbmaps > 0) {
			if (in_array($this->currentgmbase, ["Cup", "Teams", "Rounds"])) {
				$this->maniaControl->getModeScriptEventManager()->getPauseStatus()->setCallable(function (StatusCallbackStructure $structure) {
					if ($structure->getActive()) {
						$this->maniaControl->getChat()->sendSuccess($this->chatprefix . 'The match is currently on $<$F00pause$>!');
						Logger::log("Pause");
					} else {
						if ($this->pauseon) {
							$this->pauseon = false;
						}
						$this->maniaControl->getChat()->sendInformation($this->chatprefix . '$o$iRound: ' . ($this->nbrounds + 1) . ' / ' . $this->settings_nbroundsbymap);
						Logger::log("Round: " . ($this->nbrounds + 1) . ' / ' . $this->settings_nbroundsbymap);
					}
				});

			} elseif (in_array($this->currentgmbase, ["Laps", "TimeAttack"])) {
				if ($this->nbmaps > 0) {
					$this->maniaControl->getChat()->sendInformation($this->chatprefix . '$s$<$o$iMap: ' . ($this->nbmaps));
					Logger::log("Map: " . $this->nbmaps);
				}
			}

			// Match Recover
			if ($this->matchrecover) {
				$this->recoverPoints();
			}
		}
	}

	/**
	 * Handle Finish Callback
	 *
	 * @param \ManiaControl\Callbacks\Structures\TrackMania\OnWayPointEventStructure $structure
	 */
	public function handleFinishCallback(OnWayPointEventStructure $structure) {
		if ($this->matchStarted) {
			if ($structure->getRaceTime() <= 0) {
				Logger::log("handleFinishCallback: Invalid time");
				return;
			}
			$player = $structure->getPlayer();
			if (!$player) {
				return;
			}
			$this->times[] = array($player->login, $structure->getRaceTime());
		}

	}

	/**
	 * Handle when a player becomes a spectator (or vice versa)
	 * 
	 * @param \ManiaControl\Players\Player $player
	*/
	public function handlePlayerInfoChanged(Player $player) { //TODO optimize
		$this->handlePlayerConnect($player);
		if ($this->playersreadystate[$player->login]) {
			if ($player->isSpectator) {
				if ($this->playersreadystate[$player->login] == 1 || $this->playersreadystate[$player->login] == 0) {
					$this->nbplayers--;
				}
				$this->playersreadystate[$player->login] = -1;
				$this->closeReadyWidget($player->login);
			} else {
				if ($this->playersreadystate[$player->login] == -1) {
					$this->nbplayers++;
				}
				$this->playersreadystate[$player->login] = 0;
				$this->displayReadyWidget($player->login);
			}
		}
	}

	/**
	 * Handle callback "EndRound"
	 * 
	 * @param OnScoresStructure $structure
	 */
	public function handleEndRoundCallback(OnScoresStructure $structure) {
		Logger::log("handleEndRoundCallback-" . $structure->getSection());
		if ($this->matchStarted && $this->settingsloaded && !$this->postmatch) {
			Logger::log("Section: " . $structure->getSection());
			if ($structure->getSection() == "EndMatch") {
				$this->MatchEnd();
			}
			else {
				$timestamp = time();

				if ($structure->getSection() == "PreEndRound" && in_array($this->currentgmbase, ["Cup", "Teams", "Rounds"])) {
					$realSection = true;
				} elseif ($structure->getSection() == "EndRound" && in_array($this->currentgmbase, ["Champion", "Knockout", "Laps", "TimeAttack"])) {
					$realSection = true;
				} else {
					$realSection = false;
				}

				if ($realSection) {
					if ($this->nbmaps != 0 and ($this->nbrounds != $this->settings_nbroundsbymap || $this->nbrounds == 0 )) {
						$database		= "";
						$this->currentscore = array();
						$results		= $structure->getPlayerScores();
						$scores			= array();

						// CUP Specific variables
						$this->nbwinners = 0;

						// Knockout Specific variables
						$this->nbstillalive = 0;

						$pointsrepartition = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_S_POINTSREPARTITION);
						$pointsrepartition = explode(',', $pointsrepartition);

						$dbquery = 'INSERT INTO `' . self::DB_ROUNDSDATA . '` (`matchid`,`timestamp`,`rank`,`login`,`matchpoints`,`roundpoints`,`time`,`teamid`) VALUES ';

						foreach ($results as $result) {
							$rank		= $result->getRank();
							$player		= $result->getPlayer();
							$time		= $result->getPrevRaceTime();

							if (in_array($this->currentgmbase, ["Champion", "Cup", "Knockout", "Teams", "Rounds"]) && !($result->getMatchPoints() == 0 && ($player->isSpectator || $player->isFakePlayer()))) {
								$roundpoints	= $result->getRoundPoints();
								$points			= $result->getMatchPoints();

								if ($this->currentgmbase == "Champion") {
									$this->currentscore = array_merge($this->currentscore, array(array($rank, $player->login, $points , $result->getMapPoints(), $time, "")));
								} elseif ($this->currentgmbase == "Cup") {	
									$this->currentscore = array_merge($this->currentscore, array(array($rank, $player->login, ($points + $roundpoints), $roundpoints, $time, "-1")));
								} elseif ($this->currentgmbase == "Rounds" ) {
									$this->currentscore = array_merge($this->currentscore, array(array($rank, $player->login, ($points + $roundpoints), $roundpoints, $time, "-1")));
								} elseif ($this->currentgmbase == "Teams") {
									$this->currentscore = array_merge($this->currentscore, array(array($rank, $player->login, ($points + $roundpoints), $roundpoints, $time, $player->teamId)));
								} elseif ($this->currentgmbase == "Knockout") {
									$this->currentscore = array_merge($this->currentscore, array(array($rank, $player->login, $points, "-1", $time, "-1")));
								}
							} elseif (in_array($this->currentgmbase, ["Laps", "TimeAttack"]) && !(($time == 0 || $time == -1) && ($player->isSpectator || $player->isFakePlayer()))) {
								$besttime = $result->getBestRaceTime();
								if ($this->currentgmbase == "Laps") {
									$nbcp = count($result->getBestRaceCheckpoints());
									$this->currentscore = array_merge($this->currentscore, array(array($rank, $player->login, $nbcp, "-1", $besttime, "-1")));
								} elseif ($this->currentgmbase == "TimeAttack") {
									$this->currentscore = array_merge($this->currentscore, array(array($rank, $player->login, "-1", "-1", $besttime, "-1")));
								}
							}
						}

						Logger::log('Count number of players finish: '. count($this->times));

						if (!$this->pauseon && !$this->skipround) {
							$this->nbrounds++;
						}
						if ($this->skipround) {
							$this->skipround = false;
						}

						if ($this->currentgmbase == "Knockout" && $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_S_ROUNDSWITHOUTELIMINATION) <= $this->nbrounds && $this->nbmaps == 1) {
							Logger::log("Round without elimination");
						}

						// MYSQL DATA INSERT
						$settings = json_encode($this->maniaControl->getClient()->getModeScriptSettings());
						$mysqli = $this->maniaControl->getDatabase()->getMysqli();

						$query = 'INSERT INTO `' . self::DB_ROUNDSINDEX . '` 
							(`matchid`,`timestamp`,`nbmaps`,`nbrounds`,`settings`,`map`,`nbplayers`,`nbspectators`)
							VALUES
							("'. $this->matchid . '","' . $timestamp . '","' . $this->nbmaps . '","' . $this->nbrounds . '",' . "'" . $settings . "'" . ',"' . $this->currentmap->uid . '","' . $this->maniaControl->getPlayerManager()->getPlayerCount() . '","' . $this->maniaControl->getPlayerManager()->getSpectatorCount() . '")';
						$mysqli->query($query);
						if ($mysqli->error) {
							trigger_error($mysqli->error);
						}

						// Round data
						foreach ($this->currentscore as $value) {
							$dbquery .= '("' . $this->matchid . '","' . $timestamp . '","' . implode('","',$value) . '"),';
						}
						$dbquery = substr($dbquery, 0, -1);
						$mysqli->query($dbquery);
						if ($mysqli->error) {
							trigger_error($mysqli->error);
						}

						Logger::log("Rounds finished: " . $this->nbrounds);

						// Trigger Callback
						$this->maniaControl->getCallbackManager()->triggerCallback(self::CB_MATCHMANAGER_ENDROUND, $this->matchid, $this->currentscore, []);
					}
				} elseif ($structure->getSection() == "EndRound" && $this->currentgmbase == "Teams") {

					// Teams Specific variables
					$teamresults = $structure->getTeamScores();
					$this->currentteamsscore = array();

					$teamdbquery = 'INSERT INTO `' . self::DB_TEAMSDATA . '` (`matchid`,`timestamp`,`id`,`team`,`points`) VALUES ';
					$this->currentteamsscore = [];
					$rank = 1;
					foreach ($teamresults as $teamresult) {
						$this->currentteamsscore = array_merge($this->currentteamsscore, array(array($rank, $teamresult->getTeamId(), $teamresult->getMatchPoints())));
						$teamdbquery .= '("' . $this->matchid . '","' . $timestamp . '","' . $teamresult->getTeamId() . '","' . $teamresult->getName() . '","' . $teamresult->getMatchPoints() . '"),';
						$rank++;
					}
					$teamdbquery = substr($teamdbquery, 0, -1);

					$mysqli = $this->maniaControl->getDatabase()->getMysqli();
					// Teams data
					$mysqli->query($teamdbquery);
					if ($mysqli->error) {
						trigger_error($mysqli->error);
					}
					$this->maniaControl->getCallbackManager()->triggerCallback(self::CB_MATCHMANAGER_ENDROUND, $this->matchid, $this->currentscore, $this->currentteamsscore);
				}
			}
			return true;
		}
	}

	/**
	 * Command //matchstart for admins
	 * 
	 * @param array			$chatCallback
	 * @param \ManiaControl\Players\Player $player
	 */
	public function onCommandMatchStart(array $chatCallback, Player $player) {
		$authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_AUTHLEVEL);
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::getAuthLevel($authLevel))) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);
			return;
		}
		$this->MatchStart();
	}

	/**
	 * Command //matchstop for admins
	 * 
	 * @param array			$chatCallback
	 * @param \ManiaControl\Players\Player $player
	 */
	public function onCommandMatchStop(array $chatCallback, Player $player) {
		$authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_AUTHLEVEL);
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::getAuthLevel($authLevel))) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);
			return;
		}
		if ($this->pauseon == false) {
			$this->MatchStop();
		} else {
			$this->maniaControl->getChat()->sendError($this->chatprefix . 'Impossible to stop a match during a pause' ,$player);
		}
	}

	/**
	 * Command //matchrecover for admins
	 * 
	 * @param array			$chatCallback
	 * @param \ManiaControl\Players\Player $player
	 */
	public function onCommandMatchRecover(array $chatCallback, Player $player) {
		$authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_AUTHLEVEL);
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::getAuthLevel($authLevel))) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);
			return;
		}
		$text = $chatCallback[1][2];
		$text = explode(" ", $text);

		if (is_numeric($text[1])) {
			$this->MatchRecover($text[1]);
		} elseif ($text[1] == "latest") {
			$this->MatchRecover(0);
		} else {
			$lastmatches = $this->getMatchesList(3);

			$message = $this->chatprefix . '$<run this command with an index or "latest"$>' . "\n";

			foreach ($lastmatches as $index => $value) {
				if ($index >= 3) {
					break;
				}
				$message .= '$<' . $index . ' - ' . $value['gamemodebase'] . ' started at ' . date("H:i:s", $value['started']);
				if ($value['ended'] == "0") {
					$message .= " (Not finished)$>\n";
				} else {
					$message .= " (Finished at " . date("H:i:s", $value['ended'] ). ")$>\n";
				}
			}
			$this->maniaControl->getChat()->sendSuccess($message, $player);
			$this->maniaControl->getChat()->sendError($this->chatprefix . 'For the moment, only point recovery is supported, you have to manage maps and rounds manually');

		}
	}

	/**
	 * Command //matchendround for admin
	 *
	 * @param array			$chatCallback
	 * @param \ManiaControl\Players\Player $player
	*/
	public function onCommandMatchEndRound(array $chatCallback, Player $player) {
		$authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_AUTHLEVEL);
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::getAuthLevel($authLevel))) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);
			return;
		}
		$this->skipround = true;
		$this->maniaControl->getModeScriptEventManager()->forceTrackmaniaRoundEnd();

	}

	/**
	 * Command //matchendwu for admin
	 *
	 * @param array			$chatCallback
	 * @param \ManiaControl\Players\Player $player
	*/
	public function onCommandMatchEndWU(array $chatCallback, Player $player) {
		$authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_AUTHLEVEL);
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::getAuthLevel($authLevel))) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);
			return;
		}
		try {
			$this->maniaControl->getModeScriptEventManager()->triggerModeScriptEvent("Trackmania.WarmUp.ForceStop");
		} catch (InvalidArgumentException $e) {
		}
	}

	/**
	 * Command //matchpause or //pause for admin
	 *
	 * @param array			$chatCallback
	 * @param \ManiaControl\Players\Player $player
	*/
	public function onCommandSetPause(array $chatCallback, Player $player) {
		Logger::log("Pause asked by admin");
		$authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_AUTHLEVEL);
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::getAuthLevel($authLevel))) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);
			return;
		}

		if (($this->matchStarted) && ($this->currentgmbase != "TimeAttack" || !empty($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_CUSTOM_GAMEMODE)))) {
			$text = $chatCallback[1][2];
			$text = explode(" ", $text);
			if (isset($text[1]) && $text[1] != "") {
				if (is_numeric($text[1]) && $text[1] > 0) {
					$this->maniaControl->getChat()->sendSuccess($this->chatprefix . 'Admin force a break for $<$ff0' . $text[1] . '$> seconds!');
					$this->setNadeoPause(true, $text[1]);
				} elseif (is_numeric($text[1]) && $text[1] == 0) {
					$this->maniaControl->getChat()->sendSuccess($this->chatprefix . 'Admin force an unlimited break');
					$this->setNadeoPause(true, $text[1]);
				} else {
					$this->maniaControl->getChat()->sendError($this->chatprefix . 'Pause time sent is invalid', $player);
				}
			} else {
				$this->maniaControl->getChat()->sendSuccess($this->chatprefix . 'Admin force a break for ' . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_PAUSE_DURATION) . ' seconds!');
				$this->setNadeoPause(true);
			}
		}
		else {
			$this->maniaControl->getChat()->sendError($this->chatprefix . 'Can\'t start Pause, match not started (or TA)', $player);
		}
	}

	/**
	 * Command //matchendpause for admins
	 * 
	 * @param array			$chatCallback
	 * @param \ManiaControl\Players\Player $player
	 */
	public function onCommandUnsetPause(array $chatCallback, Player $player) {
		$authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_AUTHLEVEL);
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::getAuthLevel($authLevel))) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);
			return;
		}
		if (($this->matchStarted) && ($this->currentgmbase != "TimeAttack" || !empty($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_CUSTOM_GAMEMODE))) && $this->pauseon) {
			$this->maniaControl->getChat()->sendSuccess($this->chatprefix . 'Admin stopped the break');
			$this->unsetNadeoPause();
		} else {
			$this->maniaControl->getChat()->sendError($this->chatprefix . 'No pause in progress', $player->login);
		}
	}

	/**
	 * Command //matchsetpoints for admin
	 *
	 * @param array			$chatCallback
	 * @param \ManiaControl\Players\Player $player
	*/
	public function onCommandSetPoints(array $chatCallback, Player $adminplayer) { 
		$authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_AUTHLEVEL);
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($adminplayer, AuthenticationManager::getAuthLevel($authLevel))) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($adminplayer);
			return;
		}

		$text = $chatCallback[1][2];
		$text = explode(" ", $text);

		if (isset($text[1]) && isset($text[2]) && is_numeric($text[2]) && $text[2] >= 0 ) {
			if (strcasecmp($text[1], "Blue") == 0 || $text[1] == "0") { //TODO: add support of Custom teams
				$this->maniaControl->getModeScriptEventManager()->setTrackmaniaTeamPoints("0", "", $text[2], $text[2]);
				$this->maniaControl->getChat()->sendSuccess($this->chatprefix . '$<$00fBlue$> Team now has $<$ff0' . $text[2] . '$> points!');
			} elseif (strcasecmp($text[1], "Red") == 0 || $text[1] == "1") {
				$this->maniaControl->getModeScriptEventManager()->setTrackmaniaTeamPoints("1", "", $text[2]	, $text[2]);
				$this->maniaControl->getChat()->sendSuccess($this->chatprefix . '$<$f00Red$> Team now has $<$ff0' . $text[2] . '$> points!');
			} else {
				$mysqli = $this->maniaControl->getDatabase()->getMysqli();
				$query = 'SELECT login FROM `' . PlayerManager::TABLE_PLAYERS . '` WHERE nickname LIKE "' . $text[1] . '"';
				$result = $mysqli->query($query);
				$array = mysqli_fetch_array($result);

				if (isset($array[0])) {
						$login = $array[0];
				} elseif (strlen($peopletoadd) == 22) {
						$login = $peopletoadd ;
				}
				if ($mysqli->error) {
						trigger_error($mysqli->error, E_USER_ERROR);
				}

				if (isset($login)) {
					$player = $this->maniaControl->getPlayerManager()->getPlayer($login,true);
					if ($player) {
						$this->maniaControl->getModeScriptEventManager()->setTrackmaniaPlayerPoints($player, "", "", $text[2]);
						$this->maniaControl->getChat()->sendSuccess($this->chatprefix . 'Player $<$ff0' . $player->nickname . '$> now has $<$ff0' . $text[2] . '$> points!');
					} else {
						$this->maniaControl->getChat()->sendError($this->chatprefix . 'Player ' . $text[1] . " isn't connected", $adminplayer);
					}
				} else {
					$this->maniaControl->getChat()->sendError($this->chatprefix . 'Player ' . $text[1] . " doesn't exist", $adminplayer);
				}
			}
		} else {
			$this->maniaControl->getChat()->sendError($this->chatprefix . 'Missing or invalid parameters', $adminplayer);
		}
	}

	/**
	 * Command /ready for players
	 *
	 * @param array			$chatCallback
	 * @param \ManiaControl\Players\Player $player
	*/
	public function onCommandSetReadyPlayer(array $chatCallback, Player $player) {
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_READY_MODE) && (!$this->matchStarted)) {
			$this->handleReady($chatCallback, $player);
		}
	}

	/**
	 * Command /pause for players
	 * 
	 * @param OnScoresStructure $structure
	 */
	public function onCommandSetPausePlayer(array $chatCallback, Player $player) {
		if (($this->matchStarted) && ($this->currentgmbase != "TimeAttack" || !empty($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_CUSTOM_GAMEMODE))) && (!$player->isSpectator && !$player->isPureSpectator)) {
			if ($this->playerpause[$player->login] < $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_PAUSE_MAXNUMBER)) {
				if ($this->pauseasked === false && $this->pauseon === false) {
					$this->maniaControl->getChat()->sendSuccess($this->chatprefix . 'Player $<$ff0' . $player->nickname . '$> ask for a break of ' . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_PAUSE_DURATION) . ' seconds! It will start after this round.');
					$this->playerpause[$player->login] = $this->playerpause[$player->login] + 1;
					Logger::log($this->playerpause[$player->login] . " pause for " . $player->login);
					$this->pauseasked	 = true;
					$this->pauseaskedbyplayer = $player->login;
				} else {
					$this->maniaControl->getChat()->sendError($this->chatprefix . 'Pause already asked by a player!', $player);
				}
			} else {
				$this->maniaControl->getChat()->sendError($this->chatprefix . 'Pause not available, too many pause asked', $player);
			}
		} else {
			$this->maniaControl->getChat()->sendError($this->chatprefix . 'Pause not available (TA or you are spectator)', $player);
		}
	}
}
