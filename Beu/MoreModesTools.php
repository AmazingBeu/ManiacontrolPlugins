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
	const PLUGIN_VERSION	= 1.1;
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
		$this->maniaControl->getCommandManager()->registerCommandListener('setpoints', $this, 'onCommandSetPoints', true, 'Set Points for a player or a team');

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
			var_dump($text[1]);
			$this->maniaControl->getModeScriptEventManager()->triggerModeScriptEvent("Trackmania.WarmUp.Extend", [ strval(intval($text[1]) * 1000)]);
			$this->maniaControl->getChat()->sendSuccessToAdmins('Extend Warmup Sent');
		} else {
			$this->maniaControl->getChat()->sendError('Usage: //extendwu <number of secs>', $player);
		}
	}

	/**
	 * Send SetPoints
	 *
	 * @param array $chat
	 * @param \ManiaControl\Players\Player $player
	*/
	public function onCommandSetPoints(Array $chat, Player $player) {	
		$text = $chat[1][2];
		$text = explode(" ", $text);

		if (isset($text[1]) && isset($text[2]) && is_numeric($text[2]) && $text[2] >= 0 ) {
			if (strcasecmp($text[1], "Blue") == 0 || $text[1] == "0") { 
				$this->maniaControl->getModeScriptEventManager()->setTrackmaniaTeamPoints("0", "", $text[2], $text[2]);
				$this->maniaControl->getChat()->sendSuccess('$<$00fBlue$> Team now has $<$ff0' . $text[2] . '$> points!');
			} elseif (strcasecmp($text[1], "Red") == 0 || $text[1] == "1") {
				$this->maniaControl->getModeScriptEventManager()->setTrackmaniaTeamPoints("1", "", $text[2]	, $text[2]);
				$this->maniaControl->getChat()->sendSuccess('$<$f00Red$> Team now has $<$ff0' . $text[2] . '$> points!');
			} elseif (is_numeric($text[1])) {//TODO: add support of name of teams (need update from NADEO)
				$this->maniaControl->getModeScriptEventManager()->setTrackmaniaTeamPoints($text[1], "", $text[2]	, $text[2]);
				$this->maniaControl->getChat()->sendSuccess('Team ' . $text[1] . ' now has $<$ff0' . $text[2] . '$> points!');
			} else {
				$mysqli = $this->maniaControl->getDatabase()->getMysqli();

				$query = $mysqli->prepare('SELECT login FROM `' . PlayerManager::TABLE_PLAYERS . '` WHERE nickname LIKE ?');
				$query->bind_param('s', $text[1]);
				if (!$query->execute()) {
					trigger_error('Error executing MySQL query: ' . $query->error);
					return;
				}
				$result = $query->get_result();
				$array = mysqli_fetch_array($result);

				if (isset($array[0])) {
					$login = $array[0];
				} elseif (strlen($text[1]) == 22) {
					$login = $text[1];
				}
				if ($mysqli->error) {
					trigger_error($mysqli->error, E_USER_ERROR);
				}

				if (isset($login)) {
					$playerpoints = $this->maniaControl->getPlayerManager()->getPlayer($login, true);
					if ($player) {
						$this->maniaControl->getModeScriptEventManager()->setTrackmaniaPlayerPoints($playerpoints, "", "", $text[2]);
						$this->maniaControl->getChat()->sendSuccess('Player $<$ff0' . $playerpoints->nickname . '$> now has $<$ff0' . $text[2] . '$> points!');
					} else {
						$this->maniaControl->getChat()->sendError('Player ' . $text[1] . " isn't connected", $player);
					}
				} else {
					$this->maniaControl->getChat()->sendError('Player ' . $text[1] . " doesn't exist", $player);
				}
			}
		} else {
			$this->maniaControl->getChat()->sendError($this->chatprefix . 'Missing or invalid parameters', $player);
		}
	}
}

