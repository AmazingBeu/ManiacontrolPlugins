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
	const PLUGIN_VERSION	= 1.0;
	const PLUGIN_NAME		= 'GameModeLoader';
	const PLUGIN_AUTHOR		= 'Beu';

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

		$this->maniaControl->getCommandManager()->registerCommandListener('mode', $this, 'commandMode', true, 'Add all connected players to the guestlist');
	}
	
	/**
	 * Handle when a player connects
	 * 
	 * @param Player $player
	 */
	public function commandMode(array $chatCallback, Player $player) {
		$params = explode(' ', $chatCallback[1][2]);
		if (array_key_exists(1, $params) && $params[1] !== "") {
			try {
				$this->maniaControl->getClient()->setScriptName($params[1]);
				$this->maniaControl->getChat()->sendSuccess("Game mode loaded, restart or skip the map", $player->login);
				Logger::log("Game mode " . $params[1] . " loaded by " . $player->login);
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
