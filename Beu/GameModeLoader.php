<?php
namespace Beu;

use Exception;
use ManiaControl\ManiaControl;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Logger;
use ManiaControl\Commands\CommandListener;
use ManiaControl\Players\Player;


/**
 * GameModeLoader
 *
 * @author	Beu
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class GameModeLoader implements CommandListener, Plugin {
	/*
	* Constants
	*/
	const PLUGIN_ID			= 191;
	const PLUGIN_VERSION	= 1.1;
	const PLUGIN_NAME		= 'GameModeLoader';
	const PLUGIN_AUTHOR		= 'Beu';

	const GAMEMODE_ALIASES 	= [
		"ta"				=> 'TrackMania/TM_TimeAttack_Online.Script.txt',
		"timeattack"		=> 'TrackMania/TM_TimeAttack_Online.Script.txt',
		"time-attack"		=> 'TrackMania/TM_TimeAttack_Online.Script.txt',
		"lap"				=> 'TrackMania/TM_Laps_Online.Script.txt',
		"laps"				=> 'TrackMania/TM_Laps_Online.Script.txt',
		"rounds"			=> 'TrackMania/TM_Rounds_Online.Script.txt',
		"round"				=> 'TrackMania/TM_Rounds_Online.Script.txt',
		"cup"				=> 'TrackMania/TM_Cup_Online.Script.txt',
		"ko"				=> 'TrackMania/TM_Knockout_Online.Script.txt',
		"knockout"			=> 'TrackMania/TM_Knockout_Online.Script.txt',
		"team"				=> 'TrackMania/TM_Teams_Online.Script.txt',
		"teams"				=> 'TrackMania/TM_Teams_Online.Script.txt',
		"team"				=> 'TrackMania/TM_Teams_Online.Script.txt',
		"teams"				=> 'TrackMania/TM_Teams_Online.Script.txt',
		"tmwt"				=> 'TrackMania/TM_TMWT2025_Online.Script.txt',
		"tmwt2025"			=> 'TrackMania/TM_TMWT2025_Online.Script.txt',
		"tmwc"				=> 'TrackMania/TM_TMWC2024_Online.Script.txt',
		"tmwc2024"			=> 'TrackMania/TM_TMWC2024_Online.Script.txt',
		"tmwtteam"			=> 'TrackMania/TM_TMWTTeams_Online.Script.txt',
		"tmwtteams"			=> 'TrackMania/TM_TMWTTeams_Online.Script.txt',
	];

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
		return "Simple plugin to load any mode with the command //mode";
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		$this->maniaControl->getCommandManager()->registerCommandListener('mode', $this, 'commandMode', true, '');
	}
	
	/**
	 * Handle when a player connects
	 * 
	 * @param Player $player
	 */
	public function commandMode(array $chatCallback, Player $player) {
		$params = explode(' ', trim($chatCallback[1][2]));
		if (array_key_exists(1, $params) && $params[1] !== "") {
			$mode = $params[1];
			if (array_key_exists(strtolower($params[1]), self::GAMEMODE_ALIASES)) {
				$mode = self::GAMEMODE_ALIASES[strtolower($params[1])];
			}

			try {
				$this->maniaControl->getClient()->setScriptName($mode);
				$this->maniaControl->getChat()->sendSuccess("Game mode loaded, restart or skip the map", $player->login);
				Logger::log("Game mode " . $mode . " loaded by " . $player->login);
			} catch (Exception $e) {
				$this->maniaControl->getChat()->sendError("Can't load the game mode: " . $e->getMessage(), $player->login);
				Logger::log("Can't load the game mode: " . $e->getMessage());
			}
		} else {
			$this->maniaControl->getChat()->sendError("usage: //mode TrackMania/TM_TimeAttack_Online.Script.txt", $player->login);
		}
	}

	/**
	 * Unload the plugin and its Resources
	 */
	public function unload() {
	}
}
