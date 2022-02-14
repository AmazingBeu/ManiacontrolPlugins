<?php
Namespace TFH;

use ManiaControl\ManiaControl;
use ManiaControl\Plugins\Plugin;
use \ManiaControl\Logger;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Players\Player;
use ManiaControl\Commands\CommandListener;

use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;

use \Exception;

/**
 * ManiaControl
 *
 * @author	Beu
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class TFHXmlRpcDataHandler implements CommandListener, CallbackListener, Plugin {
	/*
	* Constants
	*/
	const PLUGIN_ID				= 166;
	const PLUGIN_VERSION		= 1.0;
	const PLUGIN_NAME			= 'TFHXmlRpcDataHandler';
	const PLUGIN_AUTHOR			= 'Beu';

	const DB_EVENTSLOGS			= 'TFH_EventsLogs';
	const DB_PLAYERSDATA		= 'TFH_PlayersData';
	const DB_TEAMSDATA			= 'TFH_TeamsData';

	const CB_InitiatedEventData	= 'Maniacontrol.TFH.InitiatedEventData';

	const CB_InitEventData		= 'Trackmania.TFH.InitEventData';
	const CB_SetCurrentPlayer	= 'Trackmania.TFH.SetCurrentPlayer';
	const CB_WaypointEvent		= 'Trackmania.TFH.WaypointEvent';
	const CB_RequestRecovery	= 'Trackmania.TFH.RequestRecovery';
	const CB_RequestConfig		= 'Trackmania.TFH.RequestConfig';
	const CB_IsAFK				= 'AFK.IsAFK';

	const CB_SetTeamsConfig		= 'Trackmania.TFH.SetTeamsConfig';
	const CB_RestoreScores		= 'Trackmania.TFH.RestoreScores';
	const CB_RestoreLapsStats	= 'Trackmania.TFH.RestoreLapsStats';
	const CB_SetTeamPoints		= 'Trackmania.TFH.SetTeamScore';
	const CB_SkipRecovery		= 'Trackmania.TFH.SkipRecovery';
	const CB_AdminMessage		= 'Trackmania.TFH.AdminMessage';

	const T_WAYPOINT			= 0;
	const T_CONNECT				= 1;
	const T_DISCONNECT			= 2;
	const T_SETCURRENTPLAYER	= 3;
	const T_INITEVENTDATA		= 4;
	const T_REQUESTRECOVERY		= 5;
	const T_RESTORESCORES		= 6;
	const T_SKIPRECOVERY		= 7;
	const T_SETTEAMPOINTS		= 8;
	const T_PLAYERAFK 			= 9;
	const T_ADMINMESSAGE		= 10;
	const T_REQUESTCONFIG		= 11;
	const T_SETTEAMSCONFIG		= 12;

	const ST_STOREEVENTSLOGS			=  'Store Events logs on the database';

	/*
	* Private properties
	*/
	/** @var ManiaControl $maniaControl */
	private $maniaControl	= null;

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

		$this->initTables();
		$this->maniaControl->getSettingManager()->initSetting($this, self::ST_STOREEVENTSLOGS, true, "Can be useful to disable it if it create issues");

		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerConnect');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERDISCONNECT, $this, 'handlePlayerDisconnect');

		$this->maniaControl->getCommandManager()->registerCommandListener('setteampoints', $this, 'onCommandSetTeamPoins', true, '[TFH] Set team points');
		$this->maniaControl->getCommandManager()->registerCommandListener('adminmessage', $this, 'onCommandAdminMessage', true, '[TFH] Send Admin Message');

		$this->maniaControl->getCallbackManager()->registerScriptCallbackListener(self::CB_InitEventData, $this, 'handleInitEventData');
		$this->maniaControl->getCallbackManager()->registerScriptCallbackListener(self::CB_SetCurrentPlayer, $this, 'handleSetCurrentPlayer');
		$this->maniaControl->getCallbackManager()->registerScriptCallbackListener(self::CB_WaypointEvent, $this, 'handleWaypointEvent');
		$this->maniaControl->getCallbackManager()->registerScriptCallbackListener(self::CB_RequestRecovery, $this, 'handleRequestRecovery');
		$this->maniaControl->getCallbackManager()->registerScriptCallbackListener(self::CB_RequestConfig, $this, 'handleRequestConfig');

		$this->maniaControl->getCallbackManager()->registerScriptCallbackListener(self::CB_IsAFK, $this, 'handleIsAFK');
	}

	/**
	 * Initialize needed database tables
	 */
	private function initTables() {
		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		$query = 'CREATE TABLE IF NOT EXISTS `' . self::DB_PLAYERSDATA . '` (
			`Name` VARCHAR(32) NOT NULL,
			`Login` VARCHAR(32) NOT NULL,
			`TimeStamp` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			`TeamId` TINYINT UNSIGNED NOT NULL,
			`CPcount` INT(5) DEFAULT 0 NOT NULL,
			`BestLapTimes` VARCHAR(1000) DEFAULT NULL,
			`AverageLapTimes` VARCHAR(1000) DEFAULT NULL,
			`AveragesComputed` INT(5) DEFAULT 0 NOT NULL,
			`LastLapTimes` VARCHAR(1000) DEFAULT NULL,
			`TotalPlayTime` INT(10) DEFAULT 0 NOT NULL,
				PRIMARY KEY (`Login`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
		$mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error, E_USER_ERROR);
		}

		$query = 'CREATE TABLE IF NOT EXISTS `' . self::DB_TEAMSDATA . '` (
			`TeamId` TINYINT UNSIGNED NOT NULL,
			`Name` VARCHAR(32) NOT NULL,
			`Trigram` VARCHAR(30) NOT NULL,
			`Banned` TINYINT(1) DEFAULT 0 NOT NULL,
			`CurrentPlayer` VARCHAR(32) NULL,
			`CurrentPlayerUpdate` TIMESTAMP DEFAULT 0 NULL,
			`CPcount` INT(5) DEFAULT 0 NOT NULL,
			`LastTime` INT(10) DEFAULT 0 NOT NULL,
				PRIMARY KEY (`TeamId`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
		$mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error, E_USER_ERROR);
		}
		$query = 'CREATE TABLE IF NOT EXISTS `' . self::DB_EVENTSLOGS . '` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`TimeStamp` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			`Type` TINYINT UNSIGNED NOT NULL,
			`Login` VARCHAR(32) DEFAULT NULL,
			`EventRaceTime` INT(10) DEFAULT NULL,
			`LapTime` INT(10) DEFAULT NULL,
			`IsEndLap` TINYINT(1) DEFAULT NULL,
			`LandmarkId` TINYINT UNSIGNED DEFAULT NULL,
				PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
		$mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error, E_USER_ERROR);
		}
	}

	private function LogEvent(Int $Type, $data) {
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::ST_STOREEVENTSLOGS)) {
			$mysqli = $this->maniaControl->getDatabase()->getMysqli();

			switch ($Type) {
				case self::T_WAYPOINT:
					$query = $mysqli->prepare('INSERT INTO `' . self::DB_EVENTSLOGS . '` (`Type`,`Login`,`EventRaceTime`,`LapTime`,`IsEndLap`,`LandmarkId`) VALUES (?, ?, ?, ?, ?, ?);');
					$IsEndLap = intval($data->IsEndLap);
					$query->bind_param('isiiii', $Type, $data->Login, $data->EventRaceTime, $data->LapTime, $IsEndLap, $data->LandmarkId);
					break;
				case self::T_SETTEAMPOINTS:
				case self::T_CONNECT:
				case self::T_DISCONNECT:
				case self::T_PLAYERAFK:
				case self::T_SETCURRENTPLAYER:
				case self::T_ADMINMESSAGE:
					$query = $mysqli->prepare('INSERT INTO `' . self::DB_EVENTSLOGS . '` (`Type`,`Login`) VALUES (?, ?);');
					if (is_string($data)) {
						$query->bind_param('is', $Type, $data);
					} else {
						$query->bind_param('is', $Type, $data->Login);
					}
					break;

				default:
					$query = $mysqli->prepare('INSERT INTO `' . self::DB_EVENTSLOGS . '` (`Type`) VALUES (?);');
					$query->bind_param('i', $Type);
					break;

			}
	
			if (!$query->execute()) {
				trigger_error('Error executing MySQL query: ' . $query->error);
			}
		}
	}


	/**
	 * Handle when a player connects
	 * 
	 * @param Player $player
	 */
	public function handlePlayerConnect(Player $player) {
		$this->LogEvent(self::T_CONNECT, $player->login);
	}

	/**
	 * Handle when a player disconnects
	 * 
	 * @param Player $player
	 */
	public function handlePlayerDisconnect(Player $player) {
		$this->LogEvent(self::T_DISCONNECT, $player->login);
	}

	/**
	 * Handle when a player disconnects
	 * 
	 * @param Player $player
	 */
	public function handleIsAFK(array $data) {
		$json = json_decode($data[1][0],false);
		foreach ($json->accountIds as $accountid) {
			$this->LogEvent(self::T_PLAYERAFK, $this->getLoginFromAccountID($accountid));
		}
	}

	public function handleInitEventData(array $data) {
		Logger::Log("handleInitEventData");
		$this->LogEvent(self::T_INITEVENTDATA, null);

		$json = json_decode($data[1][0],false);
		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		$teamquery = $mysqli->prepare('INSERT INTO `' . self::DB_TEAMSDATA . '` (`TeamId`,`Name`,`Trigram`,`Banned`) 
																				VALUES (?, ?, ?, ?) 
																				ON DUPLICATE KEY UPDATE `Name` = VALUES(`Name`), `Trigram` = VALUES(`Trigram`), `Banned` = VALUES(`Banned`) ;');
		$teamquery->bind_param('issi', $id, $Name, $Trigram, $Banned);
		$playerquery = $mysqli->prepare('INSERT INTO `' . self::DB_PLAYERSDATA . '` (`Name`,`Login`,`TeamId`, `TimeStamp`)
																				VALUES (?, ?, ?, NOW()) 
																				ON DUPLICATE KEY UPDATE `Name` = VALUES(`Name`), `TeamId` = VALUES(`TeamId`), `TimeStamp` = NOW();');
		$playerquery->bind_param('ssi', $Name, $Login, $id);
		
		foreach ($json as $id => $team) {
			$Name = $team->Name;
			$Trigram = $team->Trigram;
			$Banned = intval($team->Banned);
			if (!$teamquery->execute()) {
				trigger_error('Error executing MySQL query: ' . $teamquery->error);
			}
			foreach ($team->Players as $player) {
				$Name = $player->Name;
				$Login = $player->Login;
				if (!$playerquery->execute()) {
					trigger_error('Error executing MySQL query: ' . $playerquery->error);
				}
			}
		}
		$deletequery = 'DELETE FROM  `' . self::DB_PLAYERSDATA . '` WHERE `TimeStamp` < NOW() - INTERVAL 1 SECOND;';
		$mysqli->query($deletequery);
		
		$this->maniaControl->getCallbackManager()->triggerCallback(self::CB_InitiatedEventData);
	}


	public function handleSetCurrentPlayer(array $data) {
		$json = json_decode($data[1][0],false);
		$this->LogEvent(self::T_SETCURRENTPLAYER, $json);

		$mysqli = $this->maniaControl->getDatabase()->getMysqli();

		// Update TotalPlayTime
		$query = $mysqli->prepare('UPDATE `'. self::DB_PLAYERSDATA .'`, `'. self::DB_TEAMSDATA .'`
									SET `'. self::DB_PLAYERSDATA .'`.`TotalPlayTime` =  `'. self::DB_PLAYERSDATA .'`.`TotalPlayTime` + now() - `'. self::DB_TEAMSDATA .'`.`CurrentPlayerUpdate`
									WHERE `'. self::DB_TEAMSDATA .'`.`TeamId` = ? AND `'. self::DB_TEAMSDATA .'`.`CurrentPlayerUpdate` > 0 AND `'. self::DB_PLAYERSDATA .'`.`Login` = `'. self::DB_TEAMSDATA .'`.`CurrentPlayer`;');
		$query->bind_param('i', $json->TeamId);

		if (!$query->execute()) {
			trigger_error('Error executing MySQL query: ' . $query->error);
		}

		// Update CurrentPlayer
		$query = $mysqli->prepare('UPDATE `'. self::DB_TEAMSDATA .'`
		SET `'. self::DB_TEAMSDATA .'`.`CurrentPlayer` = ?,
		`'. self::DB_TEAMSDATA .'`.`CurrentPlayerUpdate` = now()
		WHERE `'. self::DB_TEAMSDATA .'`.`TeamId` = ?;');
		$query->bind_param('si', $json->Login, $json->TeamId);

		if (!$query->execute()) {
		trigger_error('Error executing MySQL query: ' . $query->error);
		}
	}

	public function handleWaypointEvent(array $data) {
		$json = json_decode($data[1][0],false);
		$this->LogEvent(self::T_WAYPOINT, $json);

		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		if ($json->IsEndLap) {
			$query = $mysqli->prepare('UPDATE `'. self::DB_TEAMSDATA .'`,`'. self::DB_PLAYERSDATA .'` 
						SET `'. self::DB_TEAMSDATA .'`.`CPcount` = `'. self::DB_TEAMSDATA .'`.`CPcount` + 1,
						`'. self::DB_TEAMSDATA .'`.`LastTime` = ?,
						`'. self::DB_PLAYERSDATA .'`.`CPcount` = `'. self::DB_PLAYERSDATA .'`.`CPcount` + 1,
						`'. self::DB_PLAYERSDATA .'`.`BestLapTimes` = ?,
						`'. self::DB_PLAYERSDATA .'`.`AverageLapTimes` = ?,
						`'. self::DB_PLAYERSDATA .'`.`AveragesComputed` = ?,
						`'. self::DB_PLAYERSDATA .'`.`LastLapTimes` = ?
						WHERE `'. self::DB_TEAMSDATA .'`.`TeamId` = ? AND `'. self::DB_PLAYERSDATA .'`.`Login` = ?;');
			$best = implode(",",$json->BestLapTimes);
			$average = implode(",",$json->AverageLapTimes);
			$last = implode(",",$json->LastLapTimes);
			$query->bind_param('issisis', $json->EventRaceTime, $best, $average, $json->AveragesComputed, $last, $json->TeamId, $json->Login);
		} else {
			$query = $mysqli->prepare('UPDATE `'. self::DB_TEAMSDATA .'`,`'. self::DB_PLAYERSDATA .'` 
						SET `'. self::DB_TEAMSDATA .'`.`CPcount` = `'. self::DB_TEAMSDATA .'`.`CPcount` + 1,
						 `'. self::DB_TEAMSDATA .'`.`LastTime` = ?,
						 `'. self::DB_PLAYERSDATA .'`.`CPcount` = `'. self::DB_PLAYERSDATA .'`.`CPcount` + 1 
						 WHERE `'. self::DB_TEAMSDATA .'`.`TeamId` = ? AND `'. self::DB_PLAYERSDATA .'`.`Login` = ?;');
			$query->bind_param('iis',  $json->EventRaceTime, $json->TeamId, $json->Login);
		}

		if (!$query->execute()) {
			trigger_error('Error executing MySQL query: ' . $query->error);
		}
	}

	public function handleRequestRecovery(array $data) {
		Logger::log("handleRequestRecovery");
		$this->LogEvent(self::T_REQUESTRECOVERY, null);

		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		$query = "SELECT `TeamId`,`CPcount`,`LastTime` FROM `" . self::DB_TEAMSDATA . "`";

		$result = $mysqli->query($query);
		while($row = $result->fetch_array(MYSQLI_ASSOC)) {
			$array[] = $row;
		}

		if (isset($array[0])) {
			Logger::log("Recovery Datas sent to the server");
			$this->maniaControl->getClient()->triggerModeScriptEvent(self::CB_RestoreScores, [json_encode($array)]);
		}

		$query = "SELECT `Login`,`BestLapTimes`,`AverageLapTimes`,`AveragesComputed` FROM `" . self::DB_PLAYERSDATA . "`";

		$result = $mysqli->query($query);
		while($row = $result->fetch_array(MYSQLI_ASSOC)) {
			$class[$row["Login"]] = (object)[];
			if (strlen($row["BestLapTimes"]) > 0) {
				$class[$row["Login"]]->BestLapTimes = array_map('intval',explode(",",$row["BestLapTimes"]));
			} else {
				$class[$row["Login"]]->BestLapTimes = [];
			}
			if (strlen($row["AverageLapTimes"]) > 0) {
				$class[$row["Login"]]->AverageLapTimes = array_map('intval',explode(",",$row["AverageLapTimes"])) ;
			} else {
				$class[$row["Login"]]->AverageLapTimes = [];
			}			
			$class[$row["Login"]]->AveragesComputed = intval($row["AveragesComputed"]);
		}

		$this->maniaControl->getClient()->triggerModeScriptEvent(self::CB_RestoreLapsStats, [json_encode($class)]);

	}

	public function handleRequestConfig(array $data) {
		Logger::log("handleRequestConfig");
		$this->LogEvent(self::T_REQUESTCONFIG, null);

		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		$query = 'SELECT `TeamId`,`Name`,`Trigram`,`Banned` FROM `' . self::DB_TEAMSDATA . '` ORDER BY `TeamId`';
		$queryplayer = $mysqli->prepare('SELECT `Login`,`Name` FROM `' . self::DB_PLAYERSDATA . '` WHERE `TeamId` = ?');

		$result = $mysqli->query($query);
		$array = array();
		while($row = $result->fetch_array(MYSQLI_ASSOC)) {
			settype($row["Banned"],"boolean");
			$queryplayer->bind_param('i',  $row["TeamId"]);
			if (!$queryplayer->execute()) {
				trigger_error('Error executing MySQL query: ' . $query->error);
			}
			$resultplayer = $queryplayer->get_result();
			$arrayplayer = array();
			while ($rowplayer = $resultplayer->fetch_assoc()) {
				array_push($arrayplayer, $rowplayer);
			}
			$row += ["Players" => $arrayplayer];

			unset($row["TeamId"]);
			array_push($array, $row);
		}
		if (isset($array[0])) {
			Logger::log("Config sent to the server");
			$this->maniaControl->getClient()->triggerModeScriptEvent(self::CB_SetTeamsConfig, [json_encode($array)]);
		}
	}

	public function onCommandSetTeamPoins(array $chatCallback, Player $adminplayer) {
		$text = $chatCallback[1][2];
		$text = explode(" ", $text);

		if (isset($text[1]) && isset($text[2]) && isset($text[3]) && is_numeric($text[1]) && is_numeric($text[2]) && $text[2] >= 0 && is_numeric($text[3]) && $text[3] >= 0 ) {
			$mysqli = $this->maniaControl->getDatabase()->getMysqli();

			$query = $mysqli->prepare('UPDATE `'. self::DB_TEAMSDATA .'` SET `CPcount` = ?, `LastTime` = ? WHERE `TeamId` = ?;');
			$query->bind_param('iii', $text[2], $text[3], $text[1]);

			if (!$query->execute()) {
				trigger_error('Error executing MySQL query: ' . $query->error);
			} else {
				log(mysqli_stmt_affected_rows($query));
				if (mysqli_stmt_affected_rows($query) === 1) {
					$data = (object) [
						"TeamId" => intval($text[1]),
						"CPcount" => intval($text[2]),
						"LastTime" => intval($text[3])
					];
					$this->maniaControl->getClient()->triggerModeScriptEvent(self::CB_SetTeamPoints, [json_encode($data)]);
					$this->LogEvent(self::T_SETTEAMPOINTS, $adminplayer->login);
				}
			}
		} else {
			$this->maniaControl->getChat()->sendError("usage: //setteampoints <TeamId> <CPpoint> <LastTime>", $adminplayer);
		}
	}

	public function onCommandAdminMessage(array $chatCallback, Player $adminplayer) {
		$text = explode(" ", $chatCallback[1][2]);
		array_shift($text);
		$this->LogEvent(self::T_ADMINMESSAGE, $adminplayer->login);
		$this->maniaControl->getClient()->triggerModeScriptEvent(self::CB_AdminMessage, [implode(" ",$text)]);
	}

	/**
	 * Unload the plugin and its Resources
	 */
	public function unload() {
		$this->maniaControl->getCallbackManager()->unregisterScriptCallbackListener($this);
	}

	private function getLoginFromAccountID(string $accountid) {
		$accountid = str_replace("-","", $accountid);
		$login = "";
		foreach(str_split($accountid, 2) as $pair){
			$login .= chr(hexdec($pair));
		}
		$login = base64_encode($login);	
		$login = str_replace("+", "-", str_replace("/","_",$login));
		$login = trim($login,"=");

		return $login;
	}
}