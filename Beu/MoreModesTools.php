<?php

namespace Beu;

use ManiaControl\Commands\CommandListener;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Plugins\Plugin;
use ManiaControl\ManiaControl;
use \ManiaControl\Logger;

/**
 * Plugin Description
 *
 * @author  Beu
 * @version 1.0
 */
class MoreModesTools implements CommandListener, Plugin {
	/*
	 * Constants
	 */
	const PLUGIN_ID			= 164;
	const PLUGIN_VERSION	= 1.2;
	const PLUGIN_NAME		= 'MoreModesTools';
	const PLUGIN_AUTHOR		= 'Beu';

	/**
	 * Private Properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl = null;

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
	 * @see \Man$this->maniaControl = $maniaControl;iaControl\Plugins\Plugin::getAuthor()
	 */
	public static function getAuthor() {
		return self::PLUGIN_AUTHOR;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::getDescription()
	 */
	public static function getDescription() {
		return 'Simple tool to send XmlRpc Callbacks';
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		$this->maniaControl->getCommandManager()->registerCommandListener('pause', $this, 'onCommandPause', true, 'Launch the pause');
		$this->maniaControl->getCommandManager()->registerCommandListener('endpause', $this, 'onCommandEndPause', true, 'End the pause');
		$this->maniaControl->getCommandManager()->registerCommandListener('endround', $this, 'onCommandEndRound', true, 'End the round');
		$this->maniaControl->getCommandManager()->registerCommandListener(['endwu', 'endwarmup'], $this, 'onCommandEndWarmUp', true, 'End the WarmUp');
		$this->maniaControl->getCommandManager()->registerCommandListener(['extendwu', 'extendwarmup'], $this, 'onCommandExtendWarmUp', true, 'If the warm up has a time limit, increase it');
		$this->maniaControl->getCommandManager()->registerCommandListener('setpoints', $this, 'onCommandSetPoints', true, 'Set Points for a player');
		$this->maniaControl->getCommandManager()->registerCommandListener('setteampoints', $this, 'onCommandSetPoints', true, 'Set Points for a team');

		return true;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {
	}

	/**
	 * Send Pause
	 *
	 * @param array $chat
	 * @param \ManiaControl\Players\Player $player
	*/
	public function onCommandPause(Array $chat, Player $player) {
		$this->maniaControl->getModeScriptEventManager()->startPause();
		$this->maniaControl->getChat()->sendSuccessToAdmins('Pause sent');
	}

	/**
	 * Send End Pause
	 *
	 * @param array $chat
	 * @param \ManiaControl\Players\Player $player
	*/
	public function onCommandEndPause(Array $chat, Player $player) {
		$this->maniaControl->getModeScriptEventManager()->endPause();	
		$this->maniaControl->getChat()->sendSuccessToAdmins('Pause stopped');
	}

	/**
	 * Send End Round
	 *
	 * @param array $chat
	 * @param \ManiaControl\Players\Player $player
	*/
	public function onCommandEndRound(Array $chat, Player $player) {	
		$this->maniaControl->getModeScriptEventManager()->forceTrackmaniaRoundEnd();
		$this->maniaControl->getChat()->sendSuccessToAdmins('End Round sent');
	}

	/**
	 * Send End Warmup
	 *
	 * @param array $chat
	 * @param \ManiaControl\Players\Player $player
	*/
	public function onCommandEndWarmUp(Array $chat, Player $player) {	
		$this->maniaControl->getModeScriptEventManager()->triggerModeScriptEvent("Trackmania.WarmUp.ForceStop");
		$this->maniaControl->getChat()->sendSuccessToAdmins('End Round sent');
	}

	/**
	 * Send Extend Warmup
	 *
	 * @param array $chat
	 * @param \ManiaControl\Players\Player $player
	*/
	public function onCommandExtendWarmUp(Array $chat, Player $player) {
		$text = $chat[1][2];
		$text = explode(" ", $text);
		if (is_numeric($text[1])) {
			$this->maniaControl->getModeScriptEventManager()->triggerModeScriptEvent("Trackmania.WarmUp.Extend", [ strval(intval($text[1]) * 1000)]);
			$this->maniaControl->getChat()->sendSuccessToAdmins('Extend Warmup Sent');
		} else {
			$this->maniaControl->getChat()->sendError('Usage: //extendwu <number of secs>', $player);
		}
	}

	/**
	 * Command //setpoints for admin
	 *
	 * @param array			$chatCallback
	 * @param \ManiaControl\Players\Player $player
	*/
	public function onCommandSetPoints(array $chatCallback, Player $adminplayer) { 
		$text = $chatCallback[1][2];
		$text = explode(" ", $text);

		if (count($text) < 3) {
			$this->maniaControl->getChat()->sendError('Missing parameters. Eg: //matchsetpoints <Player Name or Login> <Match points> <Map Points (optional)> <Round Points (optional)>', $adminplayer);
			return;
		}

		$target = $text[1];
		$matchpoints = $text[2];
		$mappoints = '';
		$roundpoints = '';

		if (!is_numeric($matchpoints)) {
			$this->maniaControl->getChat()->sendError('Invalid argument: Match points', $adminplayer);
			return;
		}
		
		if (isset($text[3])) {
			$mappoints = $text[3];
			if (!is_numeric($mappoints)) {
				$this->maniaControl->getChat()->sendError('Invalid argument: Map points', $adminplayer);
				return;
			}
		}

		if (isset($text[4])) {
			$roundpoints = $text[4];
			if (!is_numeric($roundpoints)) {
				$this->maniaControl->getChat()->sendError('Invalid argument: Round points', $adminplayer);
				return;
			}
		}

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
				$this->maniaControl->getChat()->sendSuccess('Player $<$ff0' . $player->nickname . '$> now has $<$ff0' . $matchpoints . '$> points!');
			} else {
				$this->maniaControl->getChat()->sendError('Player ' . $target . " isn't connected", $adminplayer);
			}
		} else {
			$this->maniaControl->getChat()->sendError('Player ' . $target . " doesn't exist", $adminplayer);
		}
	}

	/**
	 * Command //setteampoints for admin
	 * 
	 * @param array $chatCallback 
	 * @param Player $adminplayer 
	 */
	public function onCommandSetTeamPoints(array $chatCallback, Player $adminplayer) {
		$text = $chatCallback[1][2];
		$text = explode(" ", $text);

		if (count($text) < 3) {
			$this->maniaControl->getChat()->sendError('Missing parameters. Eg: //matchsetteampoints <Team Name or Id> <Match points> <Map Points (optional)> <Round Points (optional)>', $adminplayer);
			return;
		}

		$target = $text[1];
		$matchpoints = $text[2];
		$mappoints = '';
		$roundpoints = '';

		if (!is_numeric($matchpoints)) {
			$this->maniaControl->getChat()->sendError('Invalid argument: Match points', $adminplayer);
			return;
		}
		
		if (isset($text[3])) {
			$mappoints = $text[3];
			if (!is_numeric($mappoints)) {
				$this->maniaControl->getChat()->sendError('Invalid argument: Map points', $adminplayer);
				return;
			}
		}

		if (isset($text[4])) {
			$roundpoints = $text[4];
			if (!is_numeric($roundpoints)) {
				$this->maniaControl->getChat()->sendError('Invalid argument: Round points', $adminplayer);
				return;
			}
		}

		if (strcasecmp($target, "Blue") == 0 || $target == "0") { 
			$this->maniaControl->getModeScriptEventManager()->setTrackmaniaTeamPoints("0", $roundpoints, $mappoints, $matchpoints);
			$this->maniaControl->getChat()->sendSuccess('$<$00fBlue$> Team now has $<$ff0' . $matchpoints . '$> points!');
		} elseif (strcasecmp($target, "Red") == 0 || $target == "1") {
			$this->maniaControl->getModeScriptEventManager()->setTrackmaniaTeamPoints("1", $roundpoints, $mappoints, $matchpoints);
			$this->maniaControl->getChat()->sendSuccess('$<$f00Red$> Team now has $<$ff0' . $matchpoints . '$> points!');
		} elseif (is_numeric($target)) { //TODO: add support of name of teams (need update from NADEO)
			$this->maniaControl->getModeScriptEventManager()->setTrackmaniaTeamPoints($target, $roundpoints, $mappoints, $matchpoints);
			$this->maniaControl->getChat()->sendSuccess('Team ' . $target . ' now has $<$ff0' . $matchpoints . '$> points!');
		} else {
			$this->maniaControl->getChat()->sendError('Can\'t find team: ' . $target, $adminplayer);
		}
	}
}

