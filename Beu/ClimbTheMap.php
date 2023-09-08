<?php
namespace Beu;

use FML\Controls\Frame;
use FML\Controls\Quads\Quad_BgsPlayerCard;
use FML\ManiaLink;
use FML\Script\Features\Paging;
use ManiaControl\ManiaControl;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Callbacks\Structures\TrackMania\OnWayPointEventStructure;
use ManiaControl\Commands\CommandListener;
use ManiaControl\Manialinks\LabelLine;
use ManiaControl\Manialinks\ManialinkManager;
use ManiaControl\Players\Player;
use ManiaControl\Callbacks\TimerListener;


use ManiaControl\Manialinks\ManialinkPageAnswerListener;
use ManiaControl\Maps\Map;
use ManiaControl\Utils\Formatter;

/**
 * ClimbTheMap
 *
 * @author	Beu
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class ClimbTheMap implements ManialinkPageAnswerListener, TimerListener, CommandListener, CallbackListener, Plugin {
	/*
	* Constants
	*/
	const PLUGIN_ID			= 192;
	const PLUGIN_VERSION	= 1.0;
	const PLUGIN_NAME		= 'ClimbTheMap';
	const PLUGIN_AUTHOR		= 'Beu';

	const DB_CLIMBTHEMAP    = "ClimbTheMap";

	const MLID_ALTITUDE_RECORDS         = "ClimbTheMap.AltitudeRecords";

	// Callbacks
	const CB_UPDATEPBS					= 'Trackmania.ClimbTheMap.UpdatePBs';

	// Methods
	const M_SETPLAYERSPB				= 'Trackmania.ClimbTheMap.SetPlayersPB';
	const M_SETWR						= 'Trackmania.ClimbTheMap.SetWR';

	// Actions
	const A_SHOW_ALTITUDE_RECORDS		= 'Trackmania.ClimbTheMap.ShowAltitudeRecords';
	

	/*
	* Private properties
	*/
	/** @var ManiaControl $maniaControl */
	private $maniaControl	= null;
	private $manialink		= "";
	private $wraltitude		= 0;
	private $wrtime			= 0;

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
		return "[TM2020 only] Used to save the altitude record";
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		$this->initTables();

		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::AFTERINIT, $this, 'handleAfterInit');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::MP_STARTROUNDSTART, $this, 'handleStartRound');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::TM_ONFINISHLINE, $this, 'handleFinishCallback');

		$this->maniaControl->getCallbackManager()->registerScriptCallbackListener(self::CB_UPDATEPBS, $this, 'handleUpdatePBs');

		$this->maniaControl->getManialinkManager()->registerManialinkPageAnswerListener(self::A_SHOW_ALTITUDE_RECORDS, $this, 'handleShowAltitudeRecords');
		$this->maniaControl->getCommandManager()->registerCommandListener('records', $this, 'handleShowAltitudeRecords', false);

		$this->maniaControl->getTimerManager()->registerTimerListening($this, 'handle1Minute', 60000);
	}

	private function initTables() {
		$mysqli = $this->maniaControl->getDatabase()->getMysqli();

		$query = 'CREATE TABLE IF NOT EXISTS `' . self::DB_CLIMBTHEMAP . '` (
			`index` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`mapIndex` INT(11) NOT NULL,
			`login` varchar(36) NOT NULL,
			`altitude` INT(11) NOT NULL,
			`time` int(11) DEFAULT -1,
			`date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (`index`),
				UNIQUE KEY `map_player` (`mapIndex`,`login`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
		$mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error, E_USER_ERROR);
		}
	}

	public function handleAfterInit() {
		$this->handleStartRound();
	}
	
	/**
	 * Handle when a player connects
	 * 
	 * @param Player $player
	 */
	public function handlePlayerConnect(Player $player) {
		$mysqli = $this->maniaControl->getDatabase()->getMysqli();

	}

	public function handleStartRound() {
		$map = $this->maniaControl->getMapManager()->getCurrentMap();
		$logins = [];

		foreach ($this->maniaControl->getPlayerManager()->getPlayers() as $player) {
			$logins[] = $player->login;
		}

		// Send PB
		$pbs = $this->getPlayersPB($map->index, $logins);
		if (count($pbs) > 0) {
			$this->maniaControl->getClient()->triggerModeScriptEvent(self::M_SETPLAYERSPB, [json_encode($pbs)]);
		}

		// Send WR
		$wr = $this->getWR($map->index);
		if ($wr !== null) {
			$this->wraltitude = $wr[1];
			$this->wrtime = $wr[2];
			$this->maniaControl->getClient()->triggerModeScriptEvent(self::M_SETWR, [$wr[0], strval($wr[1]), strval($wr[2])]);
		} else {
			$this->wraltitude = 0;
		}
	}

	public function handleFinishCallback(OnWayPointEventStructure $structure) {
		$map = $this->maniaControl->getMapManager()->getCurrentMap();
		if ($map === null) return;
		$mapIndex = $map->index;
		$login = $structure->getLogin();
		$time = $structure->getRaceTime();

		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		$stmt = $mysqli->prepare("INSERT INTO `" . self::DB_CLIMBTHEMAP . "` (`mapIndex`, `login`, `time`, `altitude`) 
			VALUES (?, ?, ?, -1) ON DUPLICATE KEY UPDATE
			`time` = IF(`time` < 0 OR `time` > VALUES(`time`),
				VALUES(`time`),
				`time`);"); 
		$stmt->bind_param('isi', $mapIndex, $login, $time);
		$stmt->execute();

		// Reset manialink cache
		$this->manialink = "";
	}

	public function handleUpdatePBs(array $data) {
		$json = json_decode($data[1][0]);
		if ($json !== null) {
			$map = $this->maniaControl->getMapManager()->getCurrentMap();
			$mapIndex = -1;
			if ($map !== null) $mapIndex = $map->index;

			$mysqli = $this->maniaControl->getDatabase()->getMysqli();
			$mysqli->begin_transaction();

			$stmt = $mysqli->prepare("INSERT INTO `" . self::DB_CLIMBTHEMAP . "` (`mapIndex`, `login`, `altitude`) 
				VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE
				`altitude` = GREATEST(VALUES(`altitude`), `altitude`);"); 
			$stmt->bind_param('iss', $mapIndex, $login, $altitude);
			foreach ($json as $login => $altitude) {
				$stmt->execute();
			}
			$mysqli->commit();

			// Reset manialink cache
			$this->manialink = "";
		}
	}

	public function handle1Minute() {
		$map = $this->maniaControl->getMapManager()->getCurrentMap();
		if ($map === null) return;

		$wr = $this->getWR($map->index);

		// Update WR if done on an another server
		if ($wr !== null && ($this->wraltitude !== $wr[1] || $this->wrtime !== $wr[2])) {
			$this->wraltitude = $wr[1];
			$this->wrtime = $wr[2];
			$this->maniaControl->getClient()->triggerModeScriptEvent(self::M_SETWR, [$wr[0], strval($wr[1]), strval($wr[2])]);
		}
	}


	private function getPlayersPB(int $mapIndex, array $logins) {
		if (count($logins) === 0) return [];
		$return = [];
		$mysqli = $this->maniaControl->getDatabase()->getMysqli();

		$stmt = $mysqli->prepare('SELECT login,altitude FROM `' . self::DB_CLIMBTHEMAP . '` WHERE `mapIndex` = ? and login IN (' .  str_repeat('?,', count($logins) - 1) . '?' . ' )'); 
		$stmt->bind_param('i' . str_repeat('s', count($logins)), $mapIndex, ...$logins); // bind array at once
		if (!$stmt->execute()) {
			trigger_error('Error executing MySQL query: ' . $stmt->error);
		}
		$result = $stmt->get_result(); // get the mysqli result
		if ($result !== false) {
			foreach ($result->fetch_all(MYSQLI_ASSOC) as $data) {
				$return[$data["login"]] = $data["altitude"];
			}
		}

		return $return;
	}

	private function getWR(int $mapIndex) {
		$mysqli = $this->maniaControl->getDatabase()->getMysqli();

		$stmt = $mysqli->prepare('SELECT `login`,`altitude`,`time` FROM `' . self::DB_CLIMBTHEMAP . '`
			WHERE `mapIndex` = ?
			ORDER BY 
				CASE 
					WHEN `time` > 0 THEN `time` 
					ELSE `altitude`
				END DESC,
			`date` ASC
			LIMIT 1;');
		$stmt->bind_param('i', $mapIndex);
		if (!$stmt->execute()) {
			trigger_error('Error executing MySQL query: ' . $stmt->error);
		}
		$result = $stmt->get_result();
        if ($result !== false) {
            $data = $result->fetch_assoc();
			if ($data !== null) {

				$player = $this->maniaControl->getPlayerManager()->getPlayer($data["login"]);
				if ($player !== null) {
					return [$player->nickname, $data["altitude"], $data["time"]];
				}	
			}

        }
        return null;
	}

	public function getRecords(Map $map) {
		if ($map === null) return [];

		$mapIndex = $map->index;

		$mysqli = $this->maniaControl->getDatabase()->getMysqli();

		$stmt = $mysqli->prepare('SELECT ctm.index,ctm.login,p.nickname,ctm.altitude,ctm.time,ctm.date FROM `' . self::DB_CLIMBTHEMAP . '` ctm
			LEFT JOIN `' . PlayerManager::TABLE_PLAYERS . '` p
			ON ctm.login = p.login
			WHERE `mapIndex` = ?
			ORDER BY 
				CASE 
					WHEN `time` > 0 THEN `time` 
					ELSE `altitude`
				END DESC,
			`date` ASC'); 
		$stmt->bind_param('i', $mapIndex);
		if (!$stmt->execute()) {
			trigger_error('Error executing MySQL query: ' . $stmt->error);
		}
		$result = $stmt->get_result(); // get the mysqli result
		if ($result !== false) {
			return $result->fetch_all(MYSQLI_ASSOC);
		}
		return [];
	}

	public function handleShowAltitudeRecords(array $callback, Player $player) {
		$this->maniaControl->getManialinkManager()->displayWidget($this->getManialink(), $player, self::MLID_ALTITUDE_RECORDS);
	}

	private function getManialink() {
		if ($this->manialink !== "") return $this->manialink;

		$width  = $this->maniaControl->getManialinkManager()->getStyleManager()->getListWidgetsWidth();
		$height = $this->maniaControl->getManialinkManager()->getStyleManager()->getListWidgetsHeight();

		// get PlayerList
		$records = $this->getRecords($this->maniaControl->getMapManager()->getCurrentMap());

		// create manialink
		$maniaLink = new ManiaLink(ManialinkManager::MAIN_MLID);
		$script    = $maniaLink->getScript();
		$paging    = new Paging();
		$script->addFeature($paging);

		// Main frame
		$frame = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultListFrame($script, $paging);
		$maniaLink->addChild($frame);

		// Start offsets
		$posX = -$width / 2;
		$posY = $height / 2;

		// Headline
		$headFrame = new Frame();
		$frame->addChild($headFrame);
		$headFrame->setY($posY - 5);

		$labelLine = new LabelLine($headFrame);
		$labelLine->addLabelEntryText('Rank', $posX + 5);
		$labelLine->addLabelEntryText('Nickname', $posX + 18);
		$labelLine->addLabelEntryText('Altitude', $posX + $width * 0.5);
		$labelLine->addLabelEntryText('Time', $posX + $width * 0.6);
		$labelLine->addLabelEntryText('Date (UTC)', $posX + $width * 0.75);
		$labelLine->render();

		$index     = 0;
		$posY      = $height / 2 - 10;
		$pageFrame = null;

		$pageMaxCount = floor(($height - 5 - 10) / 4);

		foreach ($records as $record) {
			if ($index % $pageMaxCount === 0) {
				$pageFrame = new Frame();
				$frame->addChild($pageFrame);
				$posY = $height / 2 - 10;
				$paging->addPageControl($pageFrame);
			}

			$recordFrame = new Frame();
			$pageFrame->addChild($recordFrame);

			if ($index % 2 === 0) {
				$lineQuad = new Quad_BgsPlayerCard();
				$recordFrame->addChild($lineQuad);
				$lineQuad->setSize($width, 4);
				$lineQuad->setSubStyle($lineQuad::SUBSTYLE_BgPlayerCardBig);
				$lineQuad->setZ(-0.001);
			}

			$labelLine = new LabelLine($recordFrame);
			$labelLine->addLabelEntryText($index + 1, $posX + 5, 13);
			$labelLine->addLabelEntryText($record["nickname"], $posX + 18, 52);
			$labelLine->addLabelEntryText($record["altitude"], $posX + $width * 0.5, 31);
			if ($record["time"] > 0) {
				$labelLine->addLabelEntryText(Formatter::formatTime($record["time"]), $posX + $width * 0.6, 30);
			}
			$labelLine->addLabelEntryText($record["date"], $posX + $width * 0.75, 30);
			$labelLine->render();

			$recordFrame->setY($posY);

			$posY -= 4;
			$index++;
		}

		$this->manialink = (string) $maniaLink;
		return $this->manialink;
	}

	/**
	 * Unload the plugin and its Resources
	 */
	public function unload() {
	}
}
