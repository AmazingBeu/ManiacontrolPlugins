<?php
namespace Beu;

use ManiaControl\ManiaControl;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Logger;
use ManiaControl\Callbacks\CallbackListener;

/**
 * AFKNotifier
 *
 * @author	Beu
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class AFKNotifier implements CallbackListener, Plugin {
	/*
	* Constants
	*/
	const PLUGIN_ID			= 187;
	const PLUGIN_VERSION	= 1.0;
	const PLUGIN_NAME		= 'AFK Notifier';
	const PLUGIN_AUTHOR		= 'Beu';

	const CB_IsAFK			= 'AFK.IsAFK';


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
		return "Notify in the chat that a player has been kicked by the official AFK library";
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		$this->maniaControl->getCallbackManager()->registerScriptCallbackListener(self::CB_IsAFK, $this, 'handleIsAFK');
	}
	
	/**
	 * Handle when a player disconnects
	 * 
	 * @param array $data
	 */
	public function handleIsAFK(array $data) {
		$json = json_decode($data[1][0],false);
		foreach ($json->accountIds as $accountid) {
			$login = $this->getLoginFromAccountID($accountid);
			Logger::log("Player " . $login . " has been kicked by the AFK lib");

			$player = $this->maniaControl->getPlayerManager()->getPlayer($login);

			if ($player !== null) {
				$this->maniaControl->getChat()->sendInformation("\$ff9" . $player->nickname . " has been kicked for being AFK");
			}
		}
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


	/**
	 * Unload the plugin and its Resources
	 */
	public function unload() {

	}
}
