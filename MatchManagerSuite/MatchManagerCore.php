<?php 

namespace MatchManagerSuite;

use Exception;
use FML\Controls\Frame;
use FML\Controls\Labels\Label_Text;
use FML\ManiaLink;
use ManiaControl\Admin\AuthenticationManager;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Callbacks\CallbackManager;
use ManiaControl\Callbacks\Listening;
use ManiaControl\Callbacks\Structures\Common\StatusCallbackStructure;
use ManiaControl\Callbacks\Structures\ManiaPlanet\StartEndStructure;
use ManiaControl\Callbacks\Structures\TrackMania\OnPointsRepartitionStructure;
use ManiaControl\Callbacks\Structures\TrackMania\OnScoresStructure;
use ManiaControl\Commands\CommandListener;
use ManiaControl\Communication\CommunicationAnswer;
use ManiaControl\Communication\CommunicationListener;
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
use ManiaControl\Callbacks\TimerListener; // for pause

/**
 * MatchManager Core
 *
 * @author		Beu (based on MatchPlugin by jonthekiller)
 * @license		http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class MatchManagerCore implements CallbackListener, CommandListener, TimerListener, CommunicationListener, Plugin {
	/*
	 * MARK: Constants
	 */
	const PLUGIN_ID											= 152;
	const PLUGIN_VERSION									= 5.6;
	const PLUGIN_NAME										= 'MatchManager Core';
	const PLUGIN_AUTHOR										= 'Beu';

	// Specific const
	const DB_MATCHESINDEX									= 'MatchManager_MatchesIndex';
	const DB_ROUNDSINDEX									= 'MatchManager_RoundsIndex';
	const DB_ROUNDSDATA										= 'MatchManager_RoundsData';
	const DB_TEAMSDATA										= 'MatchManager_TeamsData';
	const DB_MATCHESRESULT									= 'MatchManager_MatchesResult';
	const DB_MATCHESTEAMSRESULT								= 'MatchManager_MatchesTeamsResult';
	const MLID_MATCH_PAUSE_WIDGET							= 'Pause Widget';

	// Internal Callback Trigger
	const CB_MATCHMANAGER_BEGINMAP							= 'MatchManager.BeginMap';
	const CB_MATCHMANAGER_ENDROUND							= 'MatchManager.EndRound';
	const CB_MATCHMANAGER_STARTMATCH						= 'MatchManager.StartMatch';
	const CB_MATCHMANAGER_ENDMATCH							= 'MatchManager.EndMatch';
	const CB_MATCHMANAGER_STOPMATCH							= 'MatchManager.StopMatch';

	// Plugin Settings
	const SETTING_MATCH_AUTHLEVEL							= 'Auth level for the match* commands:';
	const SETTING_MATCH_CUSTOM_GAMEMODE						= 'Custom Gamemode file';
	const SETTING_MATCH_DONT_DELETE_SETTINGS				= 'Don\'t delete settings';
	const SETTING_MATCH_GAMEMODE_BASE						= 'Gamemode used during match:';
	const SETTING_MATCH_PAUSE_DURATION						= 'Default Pause Duration in seconds';
	const SETTING_MATCH_PAUSE_POSX							= 'Pause Widget-Position: X';
	const SETTING_MATCH_PAUSE_POSY							= 'Pause Widget-Position: Y';
	const SETTING_MATCH_POST_MATCH_MAPLIST					= 'Post Match Maplist file';

	const SETTING_MATCH_SETTINGS_MODE						= 'Loading mode for settings and maps';

	const SETTING_MODE_MAPS									= 'Maps to play';
	const SETTING_MODE_SHUFFLE								= 'Randomize map order (shuffle)';
	const SETTING_MODE_SHUFFLE_SEED							= 'Randomize map seed';
	const SETTING_MODE_HIDENEXTMAPS							= 'Mask the next maps during the match';
	const SETTING_MODE_MAPLIST_FILE							= 'Maplist to use';

	// Gamemodes Settings
	const SETTING_MATCH_S_CHATTIME							= 'S_ChatTime';
	const SETTING_MATCH_S_CRASHDETECTIONTHRESHOLD			= 'S_CrashDetectionThreshold';
	const SETTING_MATCH_S_CUMULATEPOINTS					= 'S_CumulatePoints';
	const SETTING_MATCH_S_DISABLEGIVEUP						= 'S_DisableGiveUp';
	const SETTING_MATCH_S_DISABLEGOTOMAP					= 'S_DisableGoToMap';
	const SETTING_MATCH_S_EARLYENDMATCHCALLBACK				= 'S_EarlyEndMatchCallback';
	const SETTING_MATCH_S_ELIMINATEDPLAYERSNBRANKS			= 'S_EliminatedPlayersNbRanks';
	const SETTING_MATCH_S_ENABLEDOSSARDCOLOR				= 'S_EnableDossardColor';
	const SETTING_MATCH_S_FINISHTIMEOUT						= 'S_FinishTimeout';
	const SETTING_MATCH_S_FORCELAPSNB						= 'S_ForceLapsNb';
	const SETTING_MATCH_S_FORCEROADSPECTATORSNB				= 'S_ForceRoadSpectatorsNb';
	const SETTING_MATCH_S_INFINITELAPS						= 'S_InfiniteLaps';
	const SETTING_MATCH_S_LOADINGSCREENIMAGEURL				= 'S_LoadingScreenImageUrl';
	const SETTING_MATCH_S_MAPPOINTSLIMIT					= 'S_MapPointsLimit';
	const SETTING_MATCH_S_MAPSPERMATCH						= 'S_MapsPerMatch';
	const SETTING_MATCH_S_MATCHPOSITION						= 'S_MatchPosition';
	const SETTING_MATCH_S_MATCHINFO							= 'S_MatchInfo';
	const SETTING_MATCH_S_MATCHPOINTSLIMIT					= 'S_MatchPointsLimit';
	const SETTING_MATCH_S_MAXPOINTSPERROUND					= 'S_MaxPointsPerRound';
	const SETTING_MATCH_S_NBOFWINNERS						= 'S_NbOfWinners';
	const SETTING_MATCH_S_POINTSGAP							= 'S_PointsGap';
	const SETTING_MATCH_S_POINTSLIMIT						= 'S_PointsLimit';
	const SETTING_MATCH_S_POINTSREPARTITION					= 'S_PointsRepartition';
	const SETTING_MATCH_S_RESPAWNBEHAVIOUR					= 'S_RespawnBehaviour';
	const SETTING_MATCH_S_ROUNDSPERMAP						= 'S_RoundsPerMap';
	const SETTING_MATCH_S_ROUNDSWITHOUTELIMINATION			= 'S_RoundsWithoutElimination';
	const SETTING_MATCH_S_SPONSORSURL						= 'S_SponsorsUrl';
	const SETTING_MATCH_S_TEAMSURL							= 'S_TeamsUrl';
	const SETTING_MATCH_S_TIMELIMIT							= 'S_TimeLimit';
	const SETTING_MATCH_S_USEALTERNATERULES					= 'S_UseAlternateRules';
	const SETTING_MATCH_S_USECUSTOMPOINTSREPARTITION		= 'S_UseCustomPointsRepartition';
	const SETTING_MATCH_S_USETIEBREAK						= 'S_UseTieBreak';
	const SETTING_MATCH_S_WARMUPDURATION					= 'S_WarmUpDuration';
	const SETTING_MATCH_S_WARMUPNB							= 'S_WarmUpNb';
	const SETTING_MATCH_S_WARMUPTIMEOUT						= 'S_WarmUpTimeout';

	// RELATIONS BETWEEN GAMEMODE AND GAMEMODES SETTINGS
	const GAMEMODES_LIST_SETTINGS = [
		self::SETTING_MATCH_S_CHATTIME => [
			'gamemode' => ['Global'],
			'type' => 'integer',
			'default' => 10,
			'description' => 'Time before loading the next map' ],
		self::SETTING_MATCH_S_CRASHDETECTIONTHRESHOLD => [
			'gamemode' => ['TMWC2023'],
			'type' => 'integer',
			'default' => 2000,
			'description' => 'Time delta in ms with the first player that will be considered as a crash' ],
		self::SETTING_MATCH_S_CUMULATEPOINTS => [
			'gamemode' => ['Teams'],
			'type' => 'boolean',
			'default' => false,
			'description' => 'Cumulate players points to the team score (false = 1 point to the winner team)' ],
		self::SETTING_MATCH_S_DISABLEGIVEUP => [
			'gamemode' => ['Laps'],
			'type' => 'boolean',
			'default' => false,
			'description' => 'Disable GiveUp' ],
		self::SETTING_MATCH_S_DISABLEGOTOMAP => [
			'gamemode' => ['Global'],
			'type' => 'boolean',
			'default' => false,
			'description' => 'Disable the "Go To Map" in the Pause Menu' ],
		self::SETTING_MATCH_S_EARLYENDMATCHCALLBACK => [
			'gamemode' => ['Knockout', 'TMWC2023', 'TMWTTeams'],
			'type' => 'boolean',
			'default' => true,
			'description' => 'Send End Match Callback early (expert user only)' ],
		self::SETTING_MATCH_S_ELIMINATEDPLAYERSNBRANKS => [
			'gamemode' => ['Knockout'],
			'type' => 'string',
			'default' => '4',
			'description' => 'Rank at which one more player is eliminated per round (use coma to add more values. Ex COTD: 8,16,16)' ],
		self::SETTING_MATCH_S_ENABLEDOSSARDCOLOR => [
			'gamemode' => ['TMWC2023', 'TMWTTeams'],
			'type' => 'boolean',
			'default' => true,
			'description' => 'Apply color team on the dossard' ],
		self::SETTING_MATCH_S_FINISHTIMEOUT => [
			'gamemode' => ['Cup', 'Knockout', 'Laps', 'Teams', 'TMWC2023', 'TMWTTeams', 'Rounds'],
			'type' => 'integer',
			'default' => 10,
			'description' => 'Time after the first finished (-1 = based on Author time)' ],
		self::SETTING_MATCH_S_FORCELAPSNB => [
			'gamemode' => ['Global'],
			'type' => 'integer',
			'default' => -1,
			'description' => 'Force number of laps for laps maps (-1 = author, 0 for unlimited in TA)' ],
		self::SETTING_MATCH_S_FORCEROADSPECTATORSNB => [
			'gamemode' => ['TMWC2023', 'TMWTTeams'],
			'type' => 'integer',
			'default' => -1,
			'description' => 'Force the number of spectators displayed on the border of the road' ],
		self::SETTING_MATCH_S_INFINITELAPS => [
			'gamemode' => ['Global'],
			'type' => 'boolean',
			'default' => false,
			'description' => 'Never end a race in laps (override S_ForceLapsNb)' ],
		self::SETTING_MATCH_S_LOADINGSCREENIMAGEURL => [
			'gamemode' => ['Global'],
			'type' => 'string',
			'default' => "",
			'description' => 'Image URL of the Loading Screen during the map change' ],
		self::SETTING_MATCH_S_MAPPOINTSLIMIT => [
			'gamemode' => ['TMWC2023', 'TMWTTeams'],
			'type' => 'integer',
			'default' => 10,
			'description' => 'Track points limit' ],
		self::SETTING_MATCH_S_MAPSPERMATCH => [
			'gamemode' => ['Teams', 'Rounds'],
			'type' => 'integer',
			'default' => 3,
			'description' => 'Number of maps maximum in the match' ],
		self::SETTING_MATCH_S_MATCHINFO => [
			'gamemode' => ['TMWC2023', 'TMWTTeams'],
			'type' => 'string',
			'default' => "",
			'description' => 'Match info displayed in the UI' ],
		self::SETTING_MATCH_S_MATCHPOINTSLIMIT => [
			'gamemode' => ['TMWC2023', 'TMWTTeams'],
			'type' => 'integer',
			'default' => 4,
			'description' => 'Match points limit' ],
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
		self::SETTING_MATCH_S_POINTSGAP => [
			'gamemode' => ['Teams'],
			'type' => 'integer',
			'default' => 0,
			'description' => 'Points Gap to win (depend of S_PointsLimit & S_UseTieBreak)' ],
		self::SETTING_MATCH_S_POINTSLIMIT => [
			'gamemode' => ['Cup', 'Teams', 'Rounds'],
			'type' => 'integer',
			'default' => 100,
			'description' => 'Limit number of points (0 = unlimited for Ch & R)' ],
		self::SETTING_MATCH_S_POINTSREPARTITION => [
			'gamemode' => ['Cup', 'Knockout', 'Teams', 'TMWC2023', 'TMWTTeams', 'Rounds'],
			'type' => 'string',
			'default' => '10,6,4,3,2,1',
			'description' => 'Point repartition from first to last' ],
		self::SETTING_MATCH_S_RESPAWNBEHAVIOUR => [
			'gamemode' => ['Global'],
			'type' => 'integer',
			'default' => [0,1,2,3,4,5],
			'description' => 'Respawn behavior (0 = GM setting, 1 = normal, 2 = do nothing, 3 = DNF before 1st CP, 4 = always DNF, 5 = never DNF)' ],
		self::SETTING_MATCH_S_ROUNDSPERMAP => [
			'gamemode' => ['Cup', 'Knockout', 'Teams', 'Rounds'],
			'type' => 'integer',
			'default' => 5,
			'description' => 'Number of rounds par map (0 = unlimited)' ],
		self::SETTING_MATCH_S_ROUNDSWITHOUTELIMINATION => [
			'gamemode' => ['Knockout'],
			'type' => 'integer',
			'default' => 1,
			'description' => 'Rounds without elimination (like a Warmup, but just for the first map)' ],
		self::SETTING_MATCH_S_SPONSORSURL => [
			'gamemode' => ['TMWC2023', 'TMWTTeams'],
			'type' => 'string',
			'default' => "",
			'description' => 'URLs separated by a space' ],
		self::SETTING_MATCH_S_TEAMSURL => [
			'gamemode' => ['TMWC2023', 'TMWTTeams'],
			'type' => 'string',
			'default' => "",
			'description' => 'URL where to get the teams info' ],
		self::SETTING_MATCH_S_TIMELIMIT => [
			'gamemode' => ['Laps', 'TimeAttack', 'RoyalTimeAttack'],
			'type' => 'integer',
			'default' => 600,
			'description' => 'Time limit (0 = unlimited)' ],
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
			'gamemode' => ['Teams', 'Rounds'],
			'type' => 'boolean',
			'default' => false,
			'description' => 'Use Tie Break (Only available when S_MapsPerMatch > 1)' ],
		self::SETTING_MATCH_S_WARMUPDURATION => [
			'gamemode' => ['Cup', 'Knockout', 'Laps', 'Teams', 'TimeAttack', 'TMWC2023', 'TMWTTeams', 'Rounds'],
			'type' => 'integer',
			'default' => -1,
			'description' => 'Duration of 1 Warm Up in sec (-1 = one round, 0 = based on Author time)' ],
		self::SETTING_MATCH_S_WARMUPNB => [
			'gamemode' => ['Cup', 'Knockout', 'Laps', 'Teams', 'TimeAttack', 'TMWC2023', 'TMWTTeams', 'Rounds'],
			'type' => 'integer',
			'default' => 1,
			'description' => 'Number of Warm Up' ],
		self::SETTING_MATCH_S_WARMUPTIMEOUT => [
			'gamemode' => ['Cup', 'Knockout', 'Laps', 'Teams', 'TimeAttack', 'TMWC2023', 'TMWTTeams', 'Rounds'],
			'type' => 'integer',
			'default' => -1,
			'description' => 'Time after the first finished the WarmUP (-1 = based on Author time, only when S_WarmUpDuration = -1)' ]
	];

	const SETTINGS_MODE_LIST = [
		self::SETTING_MODE_MAPS => [ 
			'mode' => ['All from the plugin'],
			'type' => 'string',
			'default' => 'Campaigns/Training/Training - 01.Map.Gbx,Campaigns/Training/Training - 02.Map.Gbx',
			'description' => 'Map files separated by comma' ],
		self::SETTING_MODE_SHUFFLE => [ 
			'mode' => ['All from the plugin'],
			'type' => 'boolean',
			'default' => false,
			'description' => 'Shuffle maps order' ],
		self::SETTING_MODE_SHUFFLE_SEED => [ 
			'mode' => ['All from the plugin'],
			'type' => 'string',
			'default' => '',
			'description' => 'Seed to shuffle maps to have the same maps between multiple matches (empty for random)' ],
		self::SETTING_MODE_HIDENEXTMAPS => [ 
			'mode' => ['All from the plugin'],
			'type' => 'boolean',
			'default' => false,
			'description' => 'Hide maps to players' ],
		self::SETTING_MODE_MAPLIST_FILE => [ 
			'mode' => ['Maps from file & Settings from plugin', 'All from file'],
			'type' => 'string',
			'default' => 'match.txt',
			'description' => 'Maps + Matchsettings file to load (empty to use server login)' ],
		self::SETTING_MATCH_CUSTOM_GAMEMODE => [ 
			'mode' => ['All from the plugin', 'Maps from file & Settings from plugin'],
			'type' => 'string',
			'default' => '',
			'description' => 'Load custom gamemode script (some functions can bug, for expert only)' ],
	];

	/*
	 * MARK: Private properties
	 */
	private $matchStarted			= false;
	/** @var ManiaControl $maniaControl */
	private $maniaControl			= null;
	private $chatprefix				= '$<$fc3$w🏆$m$> '; // Would like to create a setting but MC database doesn't support utf8mb4
	private $nbmaps					= 0;
	private $nbrounds				= 0;
	private $currentgmbase			= "";
	private $currentcustomgm		= "";
	private $currentsettingmode		= "";
	private $currentmap				= null;
	private $matchrecover			= false;
	private $pointstorecover		= array();

	/** @var Listening[] $canStartCallbacks */
	private $canStartCallbacks		= array();

	// Settings to keep in memory
	private $settings_nbroundsbymap	= 5;
	private $settings_nbwinners		= 2;
	private $settings_nbmapsbymatch	= 0;
	private $settings_pointlimit	= 100;
	private $settings_disablegotomap= false;

	private $currentscore			= array();
	/** @var OnScoresStructure|null $preendroundscore */
	private $preendroundscore		= null;
	private $currentteamsscore		= array();
	private $pausetimer				= 0;
	private $pauseon				= false;

	private $settingsloaded			= false;
	private $postmatch				= false;
	private $mapsshuffled			= false;
	private $mapshidden				= false;
	private $hidenextmaps			= false;
	private $maps					= array();
	private $currentgmsettings		= array();

	private $matchid				= "";

	/*
	 * MARK: Functions
	 */
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

		//Settings
		// Last argument is the priority to sort settings, works only with the TrackManiaControl fork (https://git.virtit.fr/beu/TrackManiaControl)
		if (defined("\ManiaControl\ManiaControl::ISTRACKMANIACONTROL") && $this->maniaControl->getSettingManager()->getSettingValue($this->maniaControl->getSettingManager(), SettingManager::SETTING_ALLOW_UNLINK_SERVER)) {
			$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_DONT_DELETE_SETTINGS, false, "to prevent to remove a setting of an another server", 5);
		}

		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_AUTHLEVEL, AuthenticationManager::getPermissionLevelNameArray(AuthenticationManager::AUTH_LEVEL_ADMIN), "Admin level needed to use the plugin", 10);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_PAUSE_DURATION, 0, "Default Pause Duration in seconds", 15);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_PAUSE_POSX, 0, "Position of the Pause Countdown (on X axis)", 15);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_PAUSE_POSY, 43, "Position of the Pause Countdown (on Y axis)", 15);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_SETTINGS_MODE, array('All from the plugin', 'Maps from file & Settings from plugin', 'All from file'), "Loading mode for maps and match settings, depending on your needs", 20);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_POST_MATCH_MAPLIST, "", "Load Mapfile after the match (empty to just load TA on the same maps) (can be unstable)", 20);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCH_GAMEMODE_BASE, array("Cup", "Knockout", "Laps", "Teams", "TimeAttack", "TMWC2023", "TMWTTeams", "Rounds", "RoyalTimeAttack"), "Gamemode to launch for the match", 25);

		// Init dynamics settings
		$this->updateSettings();

		//Register Admin Commands
		$this->maniaControl->getCommandManager()->registerCommandListener('matchstart', $this, 'onCommandMatchStart', true, 'Start a match');
		$this->maniaControl->getCommandManager()->registerCommandListener('matchstop', $this, 'onCommandMatchStop', true, 'Stop a match');
		$this->maniaControl->getCommandManager()->registerCommandListener('matchrecover', $this, 'onCommandMatchRecover', true, 'Recover a match');
		$this->maniaControl->getCommandManager()->registerCommandListener('matchendround', $this, 'onCommandMatchEndRound', true, 'Force end a round during a match');
		$this->maniaControl->getCommandManager()->registerCommandListener('matchendwu', $this, 'onCommandMatchEndWU', true, 'Force end a WU during a match');
		$this->maniaControl->getCommandManager()->registerCommandListener('matchsetpoints', $this, 'onCommandSetPoints', true, 'Sets points to a player.');
		$this->maniaControl->getCommandManager()->registerCommandListener(array('matchpause','pause'), $this, 'onCommandSetPause', true, 'Set pause during a match. [time] in seconds can be added to force another value');
		$this->maniaControl->getCommandManager()->registerCommandListener(array('matchendpause','endpause'), $this, 'onCommandUnsetPause', true, 'End the pause during a match.');

		//Register Callbacks
		$this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(GameModeSettings::CB_GAMEMODESETTINGS_CHANGED, $this, 'updateGMvariables');

		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerConnect');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERDISCONNECT, $this, 'handlePlayerDisconnect');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_SCORES, $this, 'handleTrackmaniaScore');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::MP_STARTROUNDSTART, $this, 'handleBeginRoundCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_WARMUPSTARTROUND, $this, 'handleStartWarmUpCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::MP_STARTMATCHSTART, $this, 'handleStartMatchStartCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(CallbackManager::CB_MP_BEGINMAP, $this, 'handleBeginMapCallback');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(CallbackManager::CB_MP_BEGINMATCH, $this, 'handleBeginMatchCallback');

		// Register Socket commands
		$this->maniaControl->getCommunicationManager()->registerCommunicationListener("Match.GetMatchStatus", $this, function () { return new CommunicationAnswer($this->getMatchStatus()); });
		$this->maniaControl->getCommunicationManager()->registerCommunicationListener("Match.GetCurrentScore", $this, function () { return new CommunicationAnswer($this->getCurrentScore()); });
		$this->maniaControl->getCommunicationManager()->registerCommunicationListener("Match.MatchStart", $this, function () { return new CommunicationAnswer($this->MatchStart()); });
		$this->maniaControl->getCommunicationManager()->registerCommunicationListener("Match.MatchStop", $this, function () { return new CommunicationAnswer($this->MatchStop()); });
		$this->maniaControl->getCommunicationManager()->registerCommunicationListener("Match.GetMatchOptions", $this, function () { return new CommunicationAnswer($this->getGMSettings($this->currentgmbase,$this->currentcustomgm)); });
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {
		$this->closePauseWidget();
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
			`login` VARCHAR(36) NOT NULL,
			`matchpoints` INT(10) NOT NULL,
			`mappoints` INT(10) NOT NULL,
			`roundpoints` INT(10) NOT NULL,
			`bestracetime` INT(10) NOT NULL,
			`bestracecheckpoints` VARCHAR(1000) NOT NULL,
			`bestlaptime` INT(10) NOT NULL,
			`bestlapcheckpoints` VARCHAR(1000) NOT NULL,
			`prevracetime` INT(10) NOT NULL,
			`prevracecheckpoints` VARCHAR(1000) NOT NULL,
			`teamid` INT(3) NOT NULL
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
		$mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error, E_USER_ERROR);
		}
		$query = 'CREATE TABLE IF NOT EXISTS `' . self::DB_TEAMSDATA . '` (
			`matchid` VARCHAR(100) NOT NULL,
			`timestamp` INT(10) NOT NULL,
			`rank` INT(3) NOT NULL,
			`id` INT(3) NOT NULL,
			`team` VARCHAR(30) NOT NULL,
			`matchpoints` INT(10) NOT NULL,
			`mappoints` INT(10) NOT NULL,
			`roundpoints` INT(10) NOT NULL
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
		$mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error, E_USER_ERROR);
		}
		$query = 'CREATE TABLE IF NOT EXISTS `' . self::DB_MATCHESRESULT . '` (
			`matchid` VARCHAR(100) NOT NULL,
			`timestamp` INT(10) NOT NULL,
			`rank` INT(4) NOT NULL,
			`login` VARCHAR(36) NOT NULL,
			`matchpoints` INT(10) NOT NULL,
			`teamid` INT(3) NOT NULL
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
		$mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error, E_USER_ERROR);
		}
		$query = 'CREATE TABLE IF NOT EXISTS `' . self::DB_MATCHESTEAMSRESULT . '` (
			`matchid` VARCHAR(100) NOT NULL,
			`timestamp` INT(10) NOT NULL,
			`rank` INT(3) NOT NULL,
			`id` INT(3) NOT NULL,
			`team` VARCHAR(30) NOT NULL,
			`matchpoints` INT(10) NOT NULL
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
		$mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error, E_USER_ERROR);
		}

		// Update table data
		$mysqliconfig = $this->maniaControl->getDatabase()->getConfig();

		$query = 'SELECT NULL FROM INFORMATION_SCHEMA.COLUMNS WHERE `table_schema` = "' . $mysqliconfig->name . '" AND `table_name` = "' . self::DB_ROUNDSDATA . '" AND `column_name` = "mappoints";';
		if ($mysqli->query($query)->num_rows === 0) {
			$query = 'ALTER TABLE `' . self::DB_ROUNDSDATA . '`
						ADD COLUMN `mappoints` INT(10) NOT NULL AFTER `matchpoints`,
						CHANGE `time` `bestracetime` INT(10) NOT NULL,
						ADD COLUMN `bestracecheckpoints` VARCHAR(1000) NOT NULL AFTER `bestracetime`,
						ADD COLUMN `bestlaptime` INT(10) NOT NULL AFTER `bestracecheckpoints`,
						ADD COLUMN `bestlapcheckpoints` VARCHAR(1000) NOT NULL AFTER `bestlaptime`,
						ADD COLUMN `prevracetime` INT(10) NOT NULL AFTER `bestlapcheckpoints`,
						ADD COLUMN `prevracecheckpoints` VARCHAR(1000) NOT NULL AFTER `prevracetime`,
						MODIFY `teamid` INT(3) NOT NULL;';
			$mysqli->query($query);
			if ($mysqli->error) {
				trigger_error($mysqli->error, E_USER_ERROR);
			}

			$query = 'ALTER TABLE `' . self::DB_TEAMSDATA . '`
						ADD COLUMN `rank` INT(3) NOT NULL,
						MODIFY `id` INT(3) NOT NULL,
						CHANGE `points` `matchpoints` INT(10) NOT NULL,
						ADD COLUMN `mappoints` INT(10) NOT NULL AFTER `matchpoints`,
						ADD COLUMN `roundpoints` INT(10) NOT NULL AFTER `mappoints`;';
			$mysqli->query($query);
			if ($mysqli->error) {
				trigger_error($mysqli->error, E_USER_ERROR);
			}
		}

		$query = 'SELECT NULL FROM INFORMATION_SCHEMA.COLUMNS WHERE `table_schema` = "' . $mysqliconfig->name . '" AND `table_name` = "' . self::DB_ROUNDSDATA . '" AND `column_name` = "login" AND `CHARACTER_MAXIMUM_LENGTH` = 36;';
		if ($mysqli->query($query)->num_rows === 0) {
			$query = 'ALTER TABLE `' . self::DB_ROUNDSDATA . '` MODIFY `login` VARCHAR(36) NOT NULL;';
			$mysqli->query($query);
			if ($mysqli->error) {
				trigger_error($mysqli->error, E_USER_ERROR);
			}
		}
	}

	/*
	 * MARK: Informational Functions
	 */
	public function getMatchStatus() {
		return $this->matchStarted;
	}

	public function getMatchIsRunning() {
		return ($this->matchStarted && $this->settingsloaded && !$this->postmatch);
	}

	public function getCurrentGamemodeBase() {
		return $this->currentgmbase;
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

	public function getRoundNumber() {
		return $this->nbrounds;
	}

	public function getMapNumber() {
		return $this->nbmaps;
	}

	public function getMatchPointsLimit() {
		return $this->settings_pointlimit;
	}

	public function getNbWinners() {
		return $this->settings_nbwinners;
	}

	public function getPauseStatus() {
		return $this->pauseon;
	}

	public function getChatPrefix() {
		return $this->chatprefix;
	}

	/*
	 * MARK: Internal Functions
	 */

	/**
	 * Update Widgets on Setting Changes
	 *
	 * @param Setting $setting
	*/
	public function updateSettings(?Setting $setting = null) {
		if (isset($setting) && $setting->belongsToClass($this) && $this->matchStarted) {
			if ($setting->setting == self::SETTING_MATCH_GAMEMODE_BASE && $setting->value != $this->currentgmbase) {
				$setting->value = $this->currentgmbase; 
				$this->maniaControl->getSettingManager()->saveSetting($setting);
				$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . 'You can\'t change Gamemode during a Match');
			} else if ($setting->setting == self::SETTING_MATCH_CUSTOM_GAMEMODE && $setting->value != $this->currentcustomgm) {
				$setting->value = $this->currentcustomgm;
				$this->maniaControl->getSettingManager()->saveSetting($setting);
				$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . 'You can\'t change the Custom Gamemode during a Match'); 
			} else if ($setting->setting == self::SETTING_MATCH_SETTINGS_MODE && $setting->value != $this->currentsettingmode) {
				$setting->value = $this->currentsettingmode;
				$this->maniaControl->getSettingManager()->saveSetting($setting);
				$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . 'You can\'t change the Setting Mode during a Match'); 
			} else if ($setting->setting == self::SETTING_MODE_HIDENEXTMAPS && $setting->value != $this->hidenextmaps) {
				$setting->value = $this->hidenextmaps;
				$this->maniaControl->getSettingManager()->saveSetting($setting);
				$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . 'It\'s not possible to choose to hide or display the maps during a match'); 
			} else {
				if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_SETTINGS_MODE) != 'All from file') {
					Logger::log("Load Script Settings");
					try {
						$this->loadGMSettings($this->getGMSettings($this->currentgmbase,$this->currentcustomgm));
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
		} else if (isset($setting) && $setting->belongsToClass($this)) {
			if ($setting->setting == self::SETTING_MATCH_CUSTOM_GAMEMODE && $setting->value != "") {
				$scriptfile = $this->maniaControl->getServer()->getDirectory()->getUserDataFolder() . DIRECTORY_SEPARATOR . "Scripts" . DIRECTORY_SEPARATOR . "Modes" . DIRECTORY_SEPARATOR . $setting->value;
				if (!file_exists($scriptfile)) {
					$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . 'Unable to find the gamemode file: "' . $setting->value . '"');
				}
			} else if ($setting->setting == self::SETTING_MODE_MAPLIST_FILE && $setting->value != "") {
				$scriptfile = $this->maniaControl->getServer()->getDirectory()->getMapsFolder() ."MatchSettings" . DIRECTORY_SEPARATOR . $setting->value;
				if (!file_exists($scriptfile)) {
					$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . 'Unable to find the Maplist file: "' . $setting->value . '"');
				}
			} else if ($setting->setting == self::SETTING_MATCH_POST_MATCH_MAPLIST && $setting->value != "") {
				$scriptfile = $this->maniaControl->getServer()->getDirectory()->getMapsFolder() ."MatchSettings" . DIRECTORY_SEPARATOR . $setting->value;
				if (!file_exists($scriptfile)) {
					$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . 'Unable to find the Post match Maplist file: "' . $setting->value . '"');
				}
			} else if ($setting->setting == self::SETTING_MODE_MAPS && $setting->value != "") {
				$maps = explode(',', $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MODE_MAPS));
				foreach ($maps as $map) {
					try {
						$this->maniaControl->getClient()->getMapInfo($map);
					} catch (\Exception $e) {
						$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . 'Unable to find the map: "' . $map . '"');
					}
				}
			}
		}

		if (defined("\ManiaControl\ManiaControl::ISTRACKMANIACONTROL") && $this->maniaControl->getSettingManager()->getSettingValue($this->maniaControl->getSettingManager(), SettingManager::SETTING_ALLOW_UNLINK_SERVER)) {
			$deletesettings = !$this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_DONT_DELETE_SETTINGS);
		} else {
			$deletesettings = true;
		}
		$allsettings = $this->maniaControl->getSettingManager()->getSettingsByClass($this);
		$settingsmode = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_SETTINGS_MODE);
		$modesettings = $this->getModeSettings($settingsmode);
		foreach ($allsettings as $key => $value) {
			$name = $value->setting;
			if (array_key_exists($name,self::SETTINGS_MODE_LIST)) {
				if (!isset($modesettings[$name])) {
					if ($deletesettings) $this->maniaControl->getSettingManager()->deleteSetting($this, $name);
				}
			}
		}
		foreach ($modesettings as $key => $value) {
			$this->maniaControl->getSettingManager()->initSetting($this, $key, self::SETTINGS_MODE_LIST[$key]['default'], self::SETTINGS_MODE_LIST[$key]['description'], 50);
		}

		if ($settingsmode == 'Maps from file & Settings from plugin' || $settingsmode == 'All from the plugin') {
			$gmsettings = $this->getGMSettings($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_GAMEMODE_BASE), $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_CUSTOM_GAMEMODE));

			foreach ($allsettings as $key => $value) {
				$name = $value->setting;
				if (substr($name,0, 2) == "S_" && !isset($gmsettings[$name])) {
					if ($deletesettings) $this->maniaControl->getSettingManager()->deleteSetting($this, $name);
				}
			}
			foreach ($gmsettings as $key => $value) {
				$this->maniaControl->getSettingManager()->initSetting($this, $key, $value['default'], $value['description'], 100);
			}
		} else {
			foreach ($allsettings as $key => $value) {
				$name = $value->setting;
				if (substr($name,0, 2) == "S_") {
					if ($deletesettings) $this->maniaControl->getSettingManager()->deleteSetting($this, $name);
				}
			}
		}
	}


	/**
	 * Reset match variables
	 *
	 * @param Setting $setting
	*/
	public function resetMatchVariables() {
		$this->matchStarted		= false;
		$this->matchrecover		= false;
		$this->pauseon			= false;
		$this->pointstorecover	= array();
		$this->currentscore		= array(); // TODO CHECK
		$this->preendroundscore	= null;
		$this->settingsloaded	= false;
		$this->mapsshuffled		= false;
		$this->mapshidden		= false;
		$this->hidenextmaps		= false;
		$this->maps				= array();
		$this->postmatch		= true;
		$this->matchid			= "";

		$this->settings_nbroundsbymap	= -1;
		$this->settings_nbwinners		= 2;
		$this->settings_nbmapsbymatch	= 0;
		$this->settings_pointlimit		= 100;

		$this->currentgmbase		= "";
		$this->currentcustomgm		= "";
		$this->currentsettingmode	= "";
	}

	/**
	 * Load Gamemode settings excluding not used settings in custom gamemodes
	 * 
	 * @param array $gmsettings
	*/
	private function loadGMSettings($gmallsettings) {
		$currentgmsettings = $this->maniaControl->getClient()->getModeScriptSettings();
		foreach ($gmallsettings as $gamemodename => $info) {
			if (isset($currentgmsettings[$gamemodename])) {
				$gmsettings[$gamemodename] = $info['value'];
			}
		}
		$this->maniaControl->getClient()->setModeScriptSettings($gmsettings);
	}

	/**
	 * Get Array with all settings of the Gamemode
	 * 
	 * @param String $gamemode
	*/
	public function getGMSettings(String $gamemodebase, String $customgamemode) {
		$gamesettings = [];

		foreach (self::GAMEMODES_LIST_SETTINGS as $gamesetting => $info) {
			if (in_array('Global', $info['gamemode']) || in_array($gamemodebase, $info['gamemode'])) {
				$gamesettings = array_merge($gamesettings , array($gamesetting => $info));
			}
		}
		if ($customgamemode != "") {
			$filename = $this->maniaControl->getServer()->getDirectory()->getUserDataFolder() . DIRECTORY_SEPARATOR . "Scripts" . DIRECTORY_SEPARATOR . "Modes" . DIRECTORY_SEPARATOR . $customgamemode;
			if (file_exists($filename)) {
				$handle = fopen($filename, "r");
				if ($handle) {
					while (($line = fgets($handle)) !== false) {
						if (preg_match('/^(\s*)\#Setting\s+(S_\S+)\s+(\S+)\s*(as\s|)(_\(|)("|\'|)([^\'"]*)("\)|\'\)|"|\'| |)/', $line, $matches)) {
							$gamesettingname = $matches[2];
							$defaultvalue = $matches[3];
							if (is_numeric($matches[3]) && is_float($matches[3]+0)) {
								$type = "float";
							} else if (is_numeric($matches[3])) {
								$type = "integer";
							} else if (strtolower($matches[3]) == "true" || strtolower($matches[3]) == "false") {
								$type = "boolean";
								if (strtolower($matches[3]) == "false") $defaultvalue = "";
							} else {
								$defaultvalue = str_replace(["'", '"'], "" , $matches[3]);
								$type = "string";
							}
							$description = $matches[7];
							settype($defaultvalue, $type);

							$gamesettings = array_merge($gamesettings , array($gamesettingname => [
								'type' => $type,
								'default' => $defaultvalue,
								'description' => $description
							]));
						} else if (preg_match('/^\*\*\*/',$line)) {
							break;
						}
					}
					fclose($handle);
				} else {
					Logger::logError("Impossible to read custom gamemode file");
					$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . " Impossible to read custom gamemode file");
				}
			}
		}

		foreach ($gamesettings as $settingname => $info) {
			$gamesettings[$settingname]['value'] = $this->maniaControl->getSettingManager()->getSettingValue($this, $settingname);
			if ($gamesettings[$settingname]['value'] == null) {
				$gamesettings[$settingname]['value'] = $info['default'];
			}
			settype($gamesettings[$settingname]['value'], $info['type']);
		}

		return $gamesettings;
	}

	/**
	 * Get Array with all settings of the Gamemode
	 * 
	 * @param String $gamemode
	*/
	public function getModeSettings(String $mode) {
		$modesettings = [];
		foreach (self::SETTINGS_MODE_LIST as $setting => $info) {
			if (in_array('Global', $info['mode']) || in_array($mode, $info['mode'])) {
				$value = $this->maniaControl->getSettingManager()->getSettingValue($this, $setting);
				if ($value == null) {
					$value = $info['default'];
				}
				settype($value, $info['type']);
				$modesettings = array_merge($modesettings , array($setting => $value ));
			}
		}
		return $modesettings;
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
		} else {
			$this->settings_nbroundsbymap = -1;
		}
		if (isset($this->currentgmsettings[self::SETTING_MATCH_S_MAPSPERMATCH])) {	
			$this->settings_nbmapsbymatch	= (int) $this->currentgmsettings[self::SETTING_MATCH_S_MAPSPERMATCH];
		}
		if (isset($this->currentgmsettings[self::SETTING_MATCH_S_DISABLEGOTOMAP])) {	
			$this->settings_disablegotomap	= (bool) $this->currentgmsettings[self::SETTING_MATCH_S_DISABLEGOTOMAP];
		}
	}

	/**
	 * Function called to list matches
	 */
	public function getMatchesList(int $limit = 10) {
		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		$stmt = $mysqli->prepare("SELECT `gamemodebase`,`started`,`ended` FROM `" . self::DB_MATCHESINDEX . "` ORDER BY `started` DESC LIMIT ?");
		$stmt->bind_param('i', $limit);

		if (!$stmt->execute()) {
			Logger::logError('Error executing MySQL query: '. $stmt->error);
		}

		$result = $stmt->get_result();
		while($row = $result->fetch_array()) {
			$array[] = $row;
		}
		return $array;
	}

	/*
	 * MARK: Can Start Functions
	 */
	/**
	 * Add Can Start Function (for other plugins)
	 * @param CallbackListener $listener 
	 * @param string $method 
	 */
	public function addCanStartFunction(CallbackListener $listener, string $method) {
		if (!Listening::checkValidCallback($listener, $method))	return;

		if (!array_key_exists($listener::class . "::" . $method, $this->canStartCallbacks)) {
			$this->canStartCallbacks[] = new Listening($listener, $method);
		}
	}

	/**
	 * Remove Can Start Function (for other plugins)
	 * @param CallbackListener $listener 
	 * @param string $method 
	 */
	public function removeCanStartFunction(CallbackListener $listener, string $method) {
		$name = $listener::class . "::" . $method;
		if (array_key_exists($name, $this->canStartCallbacks)) {
			$this->canStartCallbacks = array_diff_key($this->canStartCallbacks, [$name]);
		}
	}

	/**
	 * Match can start or not
	 * @return bool 
	 */
	private function canStartMatch() {
		if ($this->matchStarted) {
			$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . " a match is already launched");
			return false;
		}

		foreach ($this->canStartCallbacks as $listening) {
			if (!$listening->triggerCallbackWithParams([])) return false;
		}

		return true;
	}

	/*
	 * MARK: Start / Stop / End functions
	 */
	/**
	 * Function called to start the match
	 */
	public function MatchStart() {
		if (!$this->canStartMatch()) return;

		try {
			$this->matchid = $this->maniaControl->getServer()->login . "-" . time();
			$this->currentgmbase = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_GAMEMODE_BASE);
			$this->currentcustomgm = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_CUSTOM_GAMEMODE);
			$this->currentsettingmode = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_SETTINGS_MODE);
			$maplist = "";

			if  ($this->currentgmbase == "RoyalTimeAttack") {
				$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . "No data are save in RoyalTimeAttack for the moment, it's not implemented on server side. Waiting a fix from NADEO");
				Logger::Log("No data are save in RoyalTimeAttack for the moment, it's not implemented on server side. Waiting a fix from NADEO");
			}

			// Prepare maps in case of "All from the plugin" mode
			if ($this->currentsettingmode == 'All from the plugin' && strlen($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MODE_MAPS)) >= 1) {
				$maps = explode(',', $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MODE_MAPS));
				foreach ($maps as $map) {
					try {
						$mapInfo = $this->maniaControl->getMapManager()->initializeMap($this->maniaControl->getClient()->getMapInfo($map));
					} catch (Exception $e) {
						throw new \Exception("Error with the map " . $map . ": " . $e->getMessage());
					}
				}

				if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MODE_SHUFFLE)) {
					$this->mapsshuffled = true;

					$seed = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MODE_SHUFFLE_SEED);
					if ($seed !== "") {
						mt_srand(crc32($seed));
					}
					shuffle($maps);
				}

				// Remove all maps
				foreach ($this->maniaControl->getMapManager()->getMaps() as $map) {
					$this->maniaControl->getClient()->removeMap($map->fileName);
				}

				$this->maps = $maps;
				$this->hidenextmaps = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MODE_HIDENEXTMAPS);
			}

			// Define Gamemode
			if ($this->currentsettingmode != 'All from file') {
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
				$this->maniaControl->getClient()->setScriptName($scriptName);
			}

			// Must be after loading the script in case of different MapType
			if ($this->currentsettingmode != 'All from the plugin') {
				Logger::log("Loading maplist + matchsettings");
				$maplist = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MODE_MAPLIST_FILE);
				if (empty($maplist)) {
					$server = $this->maniaControl->getServer()->login;
					$maplist = 'MatchSettings' . DIRECTORY_SEPARATOR . $server . ".txt";
				} else {
					$maplist = 'MatchSettings' . DIRECTORY_SEPARATOR . $maplist;
				}
				Logger::log("Load matchsettings: " . $maplist);
				if (!is_file($this->maniaControl->getServer()->getDirectory()->getMapsFolder() . $maplist)) {
					throw new \Exception("The Maplist file is not accessible or does not exist (the match has not started)");
				}

				//Remove all maps
				foreach ($this->maniaControl->getMapManager()->getMaps() as $map) {
					$this->maniaControl->getClient()->removeMap($map->fileName);
				}

				$this->maniaControl->getClient()->InsertPlaylistFromMatchSettings($maplist);
			} elseif ($this->currentsettingmode == 'All from the plugin') {
				if ($this->hidenextmaps) {
					$this->maniaControl->getClient()->addMap($this->maps[0]);
				} else {
					foreach ($this->maps as $map) {
						$this->maniaControl->getClient()->addMap($map);
					}
				}
			}

			Logger::log("Restructure maplist");
			$this->maniaControl->getMapManager()->restructureMapList();

			$this->matchStarted = true;
			$this->nbmaps		= 0;
			$this->nbrounds		= 0;

			Logger::log("Get Players");
			$players = $this->maniaControl->getPlayerManager()->getPlayers();

			Logger::log("Player State");
			foreach ($players as $player) {
				$this->handlePlayerConnect($player);
			}

			$serverlogin = $this->maniaControl->getServer()->login;
			$timestamp = time();

			// MYSQL DATA INSERT
			$mysqli = $this->maniaControl->getDatabase()->getMysqli();
			$stmt = $mysqli->prepare('INSERT INTO `' . self::DB_MATCHESINDEX . '` (`matchid`, `server`, `gamemodebase`, `started`, `ended`)
				VALUES (?, ?, ?, ?, 0)');
			$stmt->bind_param('sssi', $this->matchid, $serverlogin, $this->currentgmbase, $timestamp);

			if (!$stmt->execute()) {
				Logger::logError('Error executing MySQL query: '. $stmt->error);
			}

			// Trigger Callback
			$settings = [
				'currentgmbase' => $this->currentgmbase,
				'scriptName'	=> $scriptName,
				'maplist'		=> $maplist,
				'mapsshuffled'	=> $this->mapsshuffled,
				'mapshidden'	=> $this->mapshidden,
				'maps'			=> $this->maps];
			$this->maniaControl->getCallbackManager()->triggerCallback(self::CB_MATCHMANAGER_STARTMATCH, $this->matchid, $settings);

			Logger::log("Skip map");
			$this->maniaControl->getMapManager()->getMapActions()->skipMap();
		} catch (Exception $e) {
			$this->resetMatchVariables();
			Logger::log($e->getMessage());
			$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . $e->getMessage());
		}
	}

	/**
	 * Function called to end the match
	 */
	public function MatchEnd() {
		try {
			// Since now, we conside the match not started
			$this->matchStarted = false;

			// Load TimeAttack gamemode if possible
			$maplist = 'MatchSettings' . DIRECTORY_SEPARATOR . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_POST_MATCH_MAPLIST);
			if (is_file($this->maniaControl->getServer()->getDirectory()->getMapsFolder() . $maplist)) {
				$this->maniaControl->getClient()->loadMatchSettings($maplist);
			} else {
				if ($this->currentmap->mapType == "TrackMania\TM_Race") {
					$scriptname = "Trackmania/TM_TimeAttack_Online.Script.txt" ;
				} else if ($this->currentmap->mapType == "TrackMania\TM_Royal") {
					$scriptname = "Trackmania/TM_RoyalTimeAttack_Online.Script.txt" ;
				}
				if (isset($scriptname)) {
					Logger::log("Loading script: " . $scriptname);
					$this->maniaControl->getClient()->setScriptName($scriptname);
				}
			}

			// MYSQL DATA INSERT
			$timestamp = time();

			$mysqli = $this->maniaControl->getDatabase()->getMysqli();
			$mysqli->begin_transaction();

			$stmt = $mysqli->prepare('UPDATE `' . self::DB_MATCHESINDEX . '` SET `ended` = ? WHERE `matchid` = ?');
			$stmt->bind_param('is', $timestamp, $this->matchid);

			if (!$stmt->execute()) {
				Logger::logError('Error executing MySQL query: '. $stmt->error);
			}

			// Match Result
			$stmt = $mysqli->prepare('INSERT INTO `' . self::DB_MATCHESRESULT . '` 
				(`matchid`,`timestamp`,`rank`,`login`,`matchpoints`,`teamid`) 
				VALUES (?, ?, ?, ?, ? ,?)');
			$stmt->bind_param('siisii', 
				$this->matchid, 
				$timestamp, 
				$rank, 
				$login, 
				$matchpoints, 
				$teamid
			);
			
			foreach ($this->currentscore as $score) {
				list($rank, $login, $matchpoints, $mappoints, $roundpoints, $bestracetime, $bestracecheckpoints, $bestlaptime, $bestlapcheckpoints, $prevracetime, $prevracecheckpoints, $teamid) = $score;

				if (!$stmt->execute()) {
					Logger::logError('Error executing MySQL query: '. $stmt->error);
				}
			}
			$stmt->close();

			// Teams Rounds data
			if (count($this->currentteamsscore) > 1) {
				$stmt = $mysqli->prepare('INSERT INTO `' . self::DB_MATCHESTEAMSRESULT . '` (`matchid`,`timestamp`,`rank`,`id`,`team`,`matchpoints`) 
					VALUES (?, ?, ?, ?, ?, ?)');
				$stmt->bind_param('siiisi', $this->matchid, $timestamp, $rank, $teamid, $teamname, $matchpoints);


				foreach ($this->currentteamsscore as $score) {
					list($rank, $teamid, $teamname, $matchpoints) = $score;

					if (!$stmt->execute()) {
						Logger::logError('Error executing MySQL query: '. $stmt->error);
					}
				}
				$stmt->close();
			}
			$mysqli->commit();

			// Trigger Callback
			$this->maniaControl->getCallbackManager()->triggerCallback(self::CB_MATCHMANAGER_ENDMATCH, $this->matchid, $this->currentscore, $this->currentteamsscore);

			// End notifications
			$this->maniaControl->getChat()->sendSuccess($this->chatprefix . "Match finished");
			Logger::log("Match finished");

			$this->resetMatchVariables();

			// Teams Specifics variables
			$this->currentteamsscore = [];

		} catch (Exception $e) {
			$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . 'Can not finish match: ' . $e->getMessage());
		}
	}

	/**
	 * Function called to stop the match
	 */
	public function MatchStop() {
		Logger::log("Match stop");
		if (!$this->matchStarted) {
			$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . " No match launched");
			return;
		}

		try {
			// Since now, we conside the match not started
			$this->matchStarted = false;

			// Trigger Callback
			$this->maniaControl->getCallbackManager()->triggerCallback(self::CB_MATCHMANAGER_STOPMATCH, $this->matchid, $this->currentscore, $this->currentteamsscore);

			$this->maniaControl->getChat()->sendError($this->chatprefix . 'Match stopped by an Admin!');

			// Cancel pause if match stopped during a pause
			if ($this->pauseon) {
				$this->unsetNadeoPause();
			}

			// Load TimeAttack gamemode if possible
			$maplist = 'MatchSettings' . DIRECTORY_SEPARATOR . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_POST_MATCH_MAPLIST);
			if (is_file($this->maniaControl->getServer()->getDirectory()->getMapsFolder() . $maplist)) {
				$this->maniaControl->getClient()->loadMatchSettings($maplist);
			} else {
				if ($this->currentmap->mapType == "TrackMania\TM_Race") {
					$scriptname = "Trackmania/TM_TimeAttack_Online.Script.txt" ;
				} else if ($this->currentmap->mapType == "TrackMania\TM_Royal") {
					$scriptname = "Trackmania/TM_RoyalTimeAttack_Online.Script.txt" ;
				}
				if (isset($scriptname)) {
					Logger::log("Loading script: " . $scriptname);
					$this->maniaControl->getClient()->setScriptName($scriptname);
				}
			}

			$this->resetMatchVariables();
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
	public function MatchRecover(int $index): bool {
		Logger::log("Match Recover");

		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		$stmt = $mysqli->prepare('SELECT `matchid`,`gamemodebase` FROM `' . self::DB_MATCHESINDEX . '` ORDER BY `started` DESC LIMIT ? , 1');
		$stmt->bind_param('i', $index);

		if (!$stmt->execute()) {
			Logger::logError('Error executing MySQL query: '. $stmt->error);
			return false;
		}
		$result = $stmt->get_result();
		
		$array = mysqli_fetch_array($result);

		if (isset($array[0])) {
			$gamemodebase = $array['gamemodebase'];
			$matchid = $array['matchid'];

			$this->matchrecover = true;

			$stmt = $mysqli->prepare('SELECT `timestamp` FROM `' . self::DB_ROUNDSINDEX . '` WHERE `matchid` = ? ORDER BY `timestamp` DESC LIMIT 1');
			$stmt->bind_param('s', $matchid);

			if (!$stmt->execute()) {
				Logger::logError('Error executing MySQL query: '. $stmt->error);
				return false;
			}
			$result = $stmt->get_result();

			$array = mysqli_fetch_array($result);
			if (isset($array[0])) {
				$timestamp = $array['timestamp'];
				if ($gamemodebase == "Teams") {
					$stmt = $mysqli->prepare('SELECT `id` AS login, `matchpoints` FROM `' . self::DB_TEAMSDATA . '`
						WHERE `matchid` = ? AND `timestamp` = ?');
					/*$stmt = $mysqli->prepare('SELECT `id` AS login, `points` AS matchpoints FROM `' . self::DB_TEAMSDATA . '`
						WHERE `timestamp` = (SELECT `timestamp` FROM `' . self::DB_TEAMSDATA . '`
						WHERE `matchid` = ? ORDER BY `timestamp` DESC LIMIT 1)');
						*/
				} else {
					$stmt = $mysqli->prepare('SELECT `login`,`matchpoints` FROM `' . self::DB_ROUNDSDATA . '`
						WHERE `matchid` = ? AND `timestamp` = ?');
				}
				$stmt->bind_param('si', $matchid, $timestamp);

				if (!$stmt->execute()) {
					Logger::logError('Error executing MySQL query: '. $stmt->error);
					return false;
				}

				$result = $stmt->get_result();

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
					return true;
				} else {
					$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . 'No data found from the last round');
				}
			} else {
				$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . 'No Rounds found for this match');
			}
		} else {
			$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . 'Match not found');
		}

		return false;
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
	 * Compute Current Scores properties
	 * 
	 * @param OnScoresStructure $structure 
	 * @return void 
	 */
	private function computeCurrentScores(OnScoresStructure $structure) {
		//
		// Players Scores
		//
		$this->currentscore = array();
		$results = $structure->getPlayerScores();

		if  ($this->currentgmbase == "RoyalTimeAttack") {
			$this->maniaControl->getChat()->sendErrorToAdmins($this->chatprefix . "No data are save in RoyalTimeAttack for the moment, it's not implemented on server side. Waiting a fix from NADEO");
			Logger::Log("No data are save in RoyalTimeAttack for the moment, it's not implemented on server side. Waiting a fix from NADEO");
		}

		$preendroundplayersscore = [];
		$preendroundteamsscore = [];
		if ($this->preendroundscore !== null) {
			$preendroundplayersscore = $this->preendroundscore->getPlayerScores();
			$preendroundteamsscore = $this->preendroundscore->getTeamScores();
		}
		$this->preendroundscore = null;

		foreach ($results as $result) {
			/** @var \ManiaControl\Callbacks\Structures\TrackMania\Models\PlayerScore $result */
			$rank 					= $result->getRank();
			$player					= $result->getPlayer();
			$matchpoints			= $result->getMatchPoints();
			$mappoints				= $result->getMapPoints();
			$roundpoints			= $result->getRoundPoints();
			$bestracetime			= $result->getBestRaceTime();
			$bestracecheckpoints	= implode(",", $result->getBestRaceCheckpoints());
			$bestlaptime			= $result->getBestLapTime();
			$bestlapcheckpoints		= implode(",", $result->getBestLapCheckpoints());
			$prevracetime			= $result->getPrevRaceTime();
			$prevracecheckpoints	= implode(",", $result->getPrevRaceCheckpoints());

			if (count($preendroundplayersscore) > 0) {
				$preendroundarray = array_filter($preendroundplayersscore, function ($e) use ($player) { return $e->getPlayer() === $player ; });

				foreach ($preendroundarray as $key => $preendround) {
					if ($roundpoints == 0 && $preendround->getRoundPoints() != 0) {
						$roundpoints = $preendround->getRoundPoints();
					}
					if ($mappoints == 0 && $preendround->getMapPoints() != 0) {
						$mappoints = $preendround->getMapPoints();
					}
					unset($preendroundplayersscore[$key]);
					break;
				}
			}

			$this->currentscore = array_merge($this->currentscore, array(
				array($rank, $player->login, $matchpoints, $mappoints, $roundpoints, $bestracetime, $bestracecheckpoints, $bestlaptime, $bestlapcheckpoints, $prevracetime, $prevracecheckpoints, $player->teamId)
			));
		}

		//
		// Teams Scores
		//
		$this->currentteamsscore = array();
		$teamresults = $structure->getTeamScores();

		if (count($teamresults) > 1) {
			// Resort scores
			usort($teamresults, function ($a, $b) { return -($a->getMatchPoints() <=> $b->getMatchPoints()); });

			$rank = 1;
			foreach ($teamresults as $teamresult) {
				$teamid					= $teamresult->getTeamId();
				$teamname				= $teamresult->getName();
				$matchpoints			= $teamresult->getMatchPoints();
				$mappoints				= $teamresult->getMapPoints();
				$roundpoints			= $teamresult->getRoundPoints();

				if (count($preendroundteamsscore) > 0) {
					$preendroundarray = array_filter($preendroundteamsscore, function ($e) use ($teamid) { return $e->getTeamId() === $teamid ; });

					foreach ($preendroundarray as $key => $preendround) {
						if ($roundpoints == 0 && $preendround->getRoundPoints() != 0) {
							$roundpoints = $preendround->getRoundPoints();
						}
						if ($mappoints == 0 && $preendround->getMapPoints() != 0) {
							$mappoints = $preendround->getMapPoints();
						}
						unset($preendroundteamsscore[$key]);
						break;
					}
				}

				$this->currentteamsscore = array_merge($this->currentteamsscore, array(
					array($rank, $teamid, $teamname, $matchpoints, $mappoints, $roundpoints)
				));
				$rank++;
			}
		}
	}

	/*
	 * MARK: Pause Management
	 */

	/**
	 * Set pause
	 * 
	 * @param boolean $admin 
	 * @param integer $time
	 */
	public function setNadeoPause($admin = false, $time = null) {
		Logger::log("Nadeo Pause");

		if ($time === null) {
			$this->pausetimer = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_PAUSE_DURATION);
		} else {
			$this->pausetimer = $time;
		}

		$this->pauseon = true;

		$this->maniaControl->getModeScriptEventManager()->startPause();
		$this->maniaControl->getChat()->sendSuccessToAdmins($this->chatprefix . 'You can interrupt the pause with the command //matchendpause');

		if ($this->pausetimer > 0) {
			$this->displayPauseWidget();
			$this->maniaControl->getTimerManager()->registerOneTimeListening($this, function () {
				$this->unsetNadeoPause();
			}, $this->pausetimer * 1000);
		}
	}

	/**
	 * Unset pause
	 */
	private function unsetNadeoPause() {
		if ($this->pauseon) {
			Logger::log("End Pause");
			$this->closePauseWidget();
			$this->pauseon = false;
			$this->maniaControl->getModeScriptEventManager()->endPause();
		}
	}

	/**
	 * Display Pause Widget
	 *
	 */
	public function displayPauseWidget() {
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

		$this->maniaControl->getTimerManager()->registerOneTimeListening($this, function () {
			if ($this->pausetimer > 0 && $this->pauseon) {
				$this->pausetimer--;
				$this->displayPauseWidget();
			} else {
				$this->closePauseWidget();
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

	/*
	 * MARK: On Game Callbacks
	 */

	/**
	 * Handle when a player connect
	 *
	 * @param \ManiaControl\Players\Player $player
	 */
	public function handlePlayerConnect(Player $player) {
		if ($this->pauseon) {
			$this->displayPauseWidget($player->login);
		}
	}

	/**
	 * Handle when a player disconnects
	 * 
	 * @param \ManiaControl\Players\Player $player
	*/
	public function handlePlayerDisconnect(Player $player) {
		$this->closePauseWidget($player->login);
	}

	/**
	 * Handle XMLRPC callback "Maniaplanet.StartMatch_Start"
	 */
	public function handleStartMatchStartCallback() {
		Logger::log("handleStartMatchStartCallback");

		if ($this->matchStarted) {
			Logger::log("Loading settings");
			$this->settingsloaded = true;

			if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_SETTINGS_MODE) != 'All from file') {
				Logger::log("Load Script Settings");
				$this->loadGMSettings($this->getGMSettings($this->currentgmbase, $this->currentcustomgm));
			}

			$this->updateGMvariables();
		} else if ($this->postmatch) {
			$this->postmatch = false;

			Logger::log("Load PostMatch Gamemode Settings");
			$maplist = 'MatchSettings' . DIRECTORY_SEPARATOR . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_POST_MATCH_MAPLIST);
			if (is_file($this->maniaControl->getServer()->getDirectory()->getMapsFolder() . $maplist)) {
				$this->maniaControl->getClient()->loadMatchSettings($maplist);
			} else {
				$postmatchsettings = [
					self::SETTING_MATCH_S_FORCELAPSNB => 0,
					self::SETTING_MATCH_S_RESPAWNBEHAVIOUR => 0,
					self::SETTING_MATCH_S_WARMUPNB => 0,
					self::SETTING_MATCH_S_TIMELIMIT => 600
				];
				$currentgmsettings = $this->maniaControl->getClient()->getModeScriptSettings();
				foreach ($postmatchsettings as $gamesettingname => $value) {
					if (!array_key_exists($gamesettingname,$currentgmsettings)) {
						unset($postmatchsettings[$gamesettingname]);
					}
				}
				$this->maniaControl->getClient()->setModeScriptSettings($postmatchsettings);
			}
		}
	}


	/**
	 * Handle Maniacontrol callback "BeginMatch"
	 */
	public function handleBeginMatchCallback() {
		Logger::log("handleBeginMatchCallback");
		if ($this->matchStarted && !$this->settingsloaded) {
			Logger::log("Restarting map to restart match data");
			$this->maniaControl->getClient()->restartMap();
		} else if ($this->matchStarted && $this->settingsloaded && $this->nbrounds == 0) {
			Logger::Log("Check if Points Repartition need to be re-applied");
			$this->maniaControl->getModeScriptEventManager()->getTrackmaniaPointsRepartition()->setCallable(function (OnPointsRepartitionStructure $structure) {
				$currentgmsettings = $this->maniaControl->getClient()->getModeScriptSettings();
				if (!is_null($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_S_POINTSREPARTITION)) && isset($currentgmsettings[self::SETTING_MATCH_S_POINTSREPARTITION])) {
					$pointrepartitionarray = explode(",", $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_S_POINTSREPARTITION));
					if ($structure->getPointsRepartition() != $pointrepartitionarray) {
						Logger::Log("re-applying Points Repartition for workaround");
						$newpoints = array(self::SETTING_MATCH_S_POINTSREPARTITION => $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_S_POINTSREPARTITION) . ',' . end($pointrepartitionarray));
						$this->maniaControl->getClient()->setModeScriptSettings($newpoints);
					}
				}
			});
		}
	}

	/**
	 * Handle Maniacontrol callback "BeginMap"
	 */
	public function handleBeginMapCallback() {
		Logger::log("handleBeginMapCallback");
		if ($this->matchStarted) {
			$this->nbmaps++;
			$this->nbrounds = 0;

			if ($this->nbmaps > 0) {
				$maps = $this->maniaControl->getMapManager()->getMaps();
				$totalnbmaps = $this->maniaControl->getMapManager()->getMapsCount();

				$this->currentmap = $this->maniaControl->getMapManager()->getCurrentMap();
				Logger::log("Current Map: " . Formatter::stripCodes($this->currentmap->name));
				$message = $this->chatprefix . '$<$o$iCurrent Map:$>' . "\n";
				$message .= Formatter::stripCodes($this->currentmap->name);
				$this->maniaControl->getChat()->sendInformation($message);

				if (!in_array($this->currentgmbase, ["Laps", "TimeAttack", "RoyalTimeAttack"]) && !$this->settings_disablegotomap) {
					$message = "";
					$i = 0;
					foreach ($maps as $map) {
						if ($this->currentmap->uid == $map->uid) {
							break;
						}
						$i++;
					}
					if (($this->settings_nbmapsbymatch > 0 && $i < $this->settings_nbmapsbymatch - 1 && $this->nbmaps < $this->settings_nbmapsbymatch) || ($this->settings_nbmapsbymatch <= 0 && ($totalnbmaps >= 2 || count($this->maps) >= 2))) { // TODO manage maps in queue added by an admin
						$message = $this->chatprefix . '$<$o$iNext Maps:$>';

						$nbhiddenmaps = 0;
						if ($this->hidenextmaps) {
							if ($totalnbmaps < count($this->maps)) {
								if ($this->settings_nbmapsbymatch > 0) {
									$nbhiddenmaps = min(count($this->maps) - $totalnbmaps, $this->settings_nbmapsbymatch - 1);
								} else {
									$nbhiddenmaps = count($this->maps) - $totalnbmaps;
								}
								$message .= "\nThen " . $nbhiddenmaps . " hidden maps";
								Logger::log("Then " . $nbhiddenmaps . " hidden maps");
							}
						}

						for ($j = 1; $j + $nbhiddenmaps <= 4 && (($this->settings_nbmapsbymatch > 0 && $j + $nbhiddenmaps <= $this->settings_nbmapsbymatch - $this->nbmaps) || ($this->settings_nbmapsbymatch <= 0 && $j + $nbhiddenmaps <= $totalnbmaps))  ; $j++ ) {
							$index = $i + $j;

							while ($index >= $totalnbmaps) { // return to the start of the array if end of array
								$index = $index - $totalnbmaps;
							}

							if ($index != $i) {
								$message .= "\n" . $j . ": " . Formatter::stripCodes($maps[$index]->name);
								Logger::log(Formatter::stripCodes($index . ": " . $maps[$index]->name));
							} else {
								$message .= "\nThen we will return to this map";
								Logger::log("Then we will return to this map");
								$j = 0;
								break;
							}
						}
						if ($j + $nbhiddenmaps >= 4) {
							if ($this->settings_nbmapsbymatch > 0 && $this->settings_nbmapsbymatch - $j - $nbhiddenmaps - $this->nbmaps + 1 > 0) {
								$message .= "\n" . "And " . ($this->settings_nbmapsbymatch - $j - $nbhiddenmaps - $this->nbmaps + 1) . " more maps";
								Logger::log("And " . ($this->settings_nbmapsbymatch - $j - $nbhiddenmaps - $this->nbmaps + 1) . " more maps");
							} elseif ($this->settings_nbmapsbymatch <= 0 && ($totalnbmaps - $j - $nbhiddenmaps > 0 || count($this->maps) - $j - $nbhiddenmaps > 0)) {
								$n = max($totalnbmaps - $j - $nbhiddenmaps, count($this->maps) - $j - $nbhiddenmaps);
								$message .= "\n" . "And " . $n . " more maps";
								Logger::log("And " . $n . " more maps");
							}
						}
						$this->maniaControl->getChat()->sendInformation($message);
					}
				}
			}

			// Trigger Callback
			$currentstatus = [
				'nbmaps'			=> $this->nbmaps,
				'settings_nbmapsbymatch'	=> $this->settings_nbmapsbymatch];
			$this->maniaControl->getCallbackManager()->triggerCallback(self::CB_MATCHMANAGER_BEGINMAP,$this->matchid, $currentstatus, $this->currentmap);
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
	public function handleBeginRoundCallback(StartEndStructure $structure) {
		Logger::log("handleBeginRoundCallback");

		if ($this->matchStarted && $this->nbmaps > 0) {

			if (defined("\ManiaControl\ManiaControl::ISTRACKMANIACONTROL") && method_exists($structure, "getValidRoundCount")) {
				$this->nbrounds = $structure->getValidRoundCount();
			} else if (property_exists($structure->getPlainJsonObject(), "valid")) {
				$this->nbrounds = $structure->getPlainJsonObject()->valid;
			} else {
				$this->nbrounds = $structure->getCount();
			}

			if (in_array($this->currentgmbase, ["Cup", "Teams", "Rounds"])) {
				$this->maniaControl->getModeScriptEventManager()->getPauseStatus()->setCallable(function (StatusCallbackStructure $structure) {
					if ($structure->getActive()) {
						$this->maniaControl->getChat()->sendSuccess($this->chatprefix . 'The match is currently on $<$F00pause$>!');
						Logger::log("Pause");
					} else {
						if ($this->settings_nbroundsbymap > 1) {
							$this->maniaControl->getChat()->sendInformation($this->chatprefix . '$o$iRound: ' . $this->nbrounds . ' / ' . $this->settings_nbroundsbymap);
							Logger::log("Round: " . $this->nbrounds . ' / ' . $this->settings_nbroundsbymap);
						}
					}
				});
			}

			// Match Recover
			if ($this->matchrecover) {
				$this->recoverPoints();
			}
		}
	}


	/**
	 * Handle callback "EndRound"
	 * 
	 * @param OnScoresStructure $structure
	 */
	public function handleTrackmaniaScore(OnScoresStructure $structure) {
		Logger::log("handleTrackmaniaScore-" . $structure->getSection());

		if ($this->matchStarted && $this->settingsloaded && !$this->postmatch) {
			Logger::log("Section: " . $structure->getSection());
			if ($structure->getSection() == "EndMatchEarly" || $structure->getSection() == "EndMatch") {
				$this->computeCurrentScores($structure);
				$this->MatchEnd();
			} elseif ($structure->getSection() == "EndMap" && $this->hidenextmaps && isset($this->maps[$this->nbmaps])) {
				$this->maniaControl->getClient()->addMap($this->maps[$this->nbmaps]);
			} elseif ($structure->getSection() == "PreEndRound") {
				$this->preendroundscore = $structure;
			} elseif ($structure->getSection() == "EndRound") {
				if ($this->nbmaps != 0 && ($this->nbrounds <= $this->settings_nbroundsbymap || $this->settings_nbroundsbymap <= 0)) {
					$this->computeCurrentScores($structure);

					$timestamp = time();
					$settings = json_encode($this->maniaControl->getClient()->getModeScriptSettings());
					$mysqli = $this->maniaControl->getDatabase()->getMysqli();

					$mysqli->begin_transaction();

					$playercount = $this->maniaControl->getPlayerManager()->getPlayerCount();
					$spectatorcount = $this->maniaControl->getPlayerManager()->getSpectatorCount();

					$stmt = $mysqli->prepare('INSERT INTO `' . self::DB_ROUNDSINDEX . '`
						(`matchid`,`timestamp`,`nbmaps`,`nbrounds`,`settings`,`map`,`nbplayers`,`nbspectators`)
						VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
					$stmt->bind_param('siiissii', $this->matchid, $timestamp, $this->nbmaps, $this->nbrounds, $settings, $this->currentmap->uid, $playercount, $spectatorcount);
					if (!$stmt->execute()) {
						Logger::logError('Error executing MySQL query: '. $stmt->error);
					}
					$stmt->close();

					// Round data
					$stmt = $mysqli->prepare('INSERT INTO `' . self::DB_ROUNDSDATA . '` 
						(`matchid`,`timestamp`,`rank`,`login`,`matchpoints`,`mappoints`,`roundpoints`,`bestracetime`,`bestracecheckpoints`,`bestlaptime`,`bestlapcheckpoints`,`prevracetime`,`prevracecheckpoints`,`teamid`) 
						VALUES (?, ?, ?, ?, ? ,? ,? ,?, ? ,? ,? ,? ,?, ?)');
					$stmt->bind_param('siisiiiisisisi', 
						$this->matchid, 
						$timestamp, 
						$rank, 
						$login, 
						$matchpoints, 
						$mappoints, 
						$roundpoints, 
						$bestracetime,
						$bestracecheckpoints,
						$bestlaptime,
						$bestlapcheckpoints,
						$prevracetime,
						$prevracecheckpoints,
						$teamid
					);
					
					foreach ($this->currentscore as $score) {
						list($rank, $login, $matchpoints, $mappoints, $roundpoints, $bestracetime, $bestracecheckpoints, $bestlaptime, $bestlapcheckpoints, $prevracetime, $prevracecheckpoints, $teamid) = $score;

						if (!$stmt->execute()) {
							Logger::logError('Error executing MySQL query: '. $stmt->error);
						}
					}
					$stmt->close();

					// Teams Rounds data
					if (count($this->currentteamsscore) > 1) {
						$stmt = $mysqli->prepare('INSERT INTO `' . self::DB_TEAMSDATA . '` (`matchid`,`timestamp`,`rank`,`id`,`team`,`matchpoints`,`mappoints`,`roundpoints`) 
							VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
						$stmt->bind_param('siiisiii', $this->matchid, $timestamp, $rank, $teamid, $teamname, $matchpoints, $mappoints, $roundpoints);


						foreach ($this->currentteamsscore as $score) {
							list($rank, $teamid, $teamname, $matchpoints, $mappoints, $roundpoints) = $score;

							if (!$stmt->execute()) {
								Logger::logError('Error executing MySQL query: '. $stmt->error);
							}
						}
						$stmt->close();
					}
					$mysqli->commit();

					Logger::log("Rounds finished: " . $this->nbrounds);
					$this->maniaControl->getCallbackManager()->triggerCallback(self::CB_MATCHMANAGER_ENDROUND, $this->matchid, $this->currentscore, $this->currentteamsscore);
				}
			}
			return true;
		}
	}

	/*
	 * MARK: On Command Callbacks
	 */

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

		if (($this->matchStarted) && (!in_array($this->currentgmbase, ["Laps", "TimeAttack", "RoyalTimeAttack"]) || ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_SETTINGS_MODE) != 'All from file' && !empty($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_CUSTOM_GAMEMODE))))) {
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
		if (($this->matchStarted) && (!in_array($this->currentgmbase, ["Laps", "TimeAttack", "RoyalTimeAttack"]) || ( $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_SETTINGS_MODE) != 'All from file' && !empty($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCH_CUSTOM_GAMEMODE)))) && $this->pauseon) {
			$this->maniaControl->getChat()->sendSuccess($this->chatprefix . 'Admin stopped the break');
			$this->unsetNadeoPause();
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

		if (count($text) < 3) {
			$this->maniaControl->getChat()->sendError($this->chatprefix . 'Missing parameters. Eg: //matchsetpoints <Team Name or id / Player Name or Login> <Match points> <Map Points (optional)> <Round Points (optional)>', $adminplayer);
			return;
		}

		$target = $text[1];
		$matchpoints = $text[2];
		$mappoints = '';
		$roundpoints = '';

		if (!is_numeric($matchpoints)) {
			$this->maniaControl->getChat()->sendError($this->chatprefix . 'Invalid argument: Match points', $adminplayer);
			return;
		}
		
		if (isset($text[3])) {
			$mappoints = $text[3];
			if (!is_numeric($mappoints)) {
				$this->maniaControl->getChat()->sendError($this->chatprefix . 'Invalid argument: Map points', $adminplayer);
				return;
			}
		}

		if (isset($text[4])) {
			$roundpoints = $text[4];
			if (!is_numeric($roundpoints)) {
				$this->maniaControl->getChat()->sendError($this->chatprefix . 'Invalid argument: Round points', $adminplayer);
				return;
			}
		}

		if (strcasecmp($target, "Blue") == 0 || $target == "0") { 
			$this->maniaControl->getModeScriptEventManager()->setTrackmaniaTeamPoints("0", $roundpoints, $mappoints, $matchpoints);
			$this->maniaControl->getChat()->sendSuccess($this->chatprefix . '$<$00fBlue$> Team now has $<$ff0' . $matchpoints . '$> points!');
		} elseif (strcasecmp($target, "Red") == 0 || $target == "1") {
			$this->maniaControl->getModeScriptEventManager()->setTrackmaniaTeamPoints("1", $roundpoints, $mappoints, $matchpoints);
			$this->maniaControl->getChat()->sendSuccess($this->chatprefix . '$<$f00Red$> Team now has $<$ff0' . $matchpoints . '$> points!');
		} elseif (is_numeric($target)) { //TODO: add support of name of teams (need update from NADEO)
			$this->maniaControl->getModeScriptEventManager()->setTrackmaniaTeamPoints($target, $roundpoints, $mappoints, $matchpoints);
			$this->maniaControl->getChat()->sendSuccess($this->chatprefix . 'Team ' . $target . ' now has $<$ff0' . $matchpoints . '$> points!');
		} else {
			$mysqli = $this->maniaControl->getDatabase()->getMysqli();
			$stmt = $mysqli->prepare('SELECT login FROM `' . PlayerManager::TABLE_PLAYERS . '` WHERE nickname LIKE ?');
			$stmt->bind_param('s', $target);

			if (!$stmt->execute()) {
				Logger::logError('Error executing MySQL query: '. $stmt->error);
			}

			$result = $stmt->get_result();
			$array = mysqli_fetch_array($result);

			if (isset($array[0])) {
					$login = $array[0];
			} elseif (strlen($target) == 22) {
					$login = $target;
			}
			if ($mysqli->error) {
					trigger_error($mysqli->error, E_USER_ERROR);
			}

			if (isset($login)) {
				$player = $this->maniaControl->getPlayerManager()->getPlayer($login,true);
				if ($player) {
					$this->maniaControl->getModeScriptEventManager()->setTrackmaniaPlayerPoints($player, $roundpoints, $mappoints, $matchpoints);
					$this->maniaControl->getChat()->sendSuccess($this->chatprefix . 'Player $<$ff0' . $player->nickname . '$> now has $<$ff0' . $matchpoints . '$> points!');
				} else {
					$this->maniaControl->getChat()->sendError($this->chatprefix . 'Player ' . $target . " isn't connected", $adminplayer);
				}
			} else {
				$this->maniaControl->getChat()->sendError($this->chatprefix . 'Player ' . $target . " doesn't exist", $adminplayer);
			}
		}
	}
}
