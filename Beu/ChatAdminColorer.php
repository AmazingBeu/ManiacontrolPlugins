<?php
namespace Beu;

use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\ManiaControl;
use ManiaControl\Plugins\Plugin;
use \ManiaControl\Logger;

/**
 * ManiaControl Profanity filter
 *
 * @author	Beu
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class ChatAdminColorer implements CallbackListener, Plugin {
	/*
	* Constants
	*/
	const PLUGIN_ID			= 155;
	const PLUGIN_VERSION	= 1.0;
	const PLUGIN_NAME		= 'ChatAdminColorer';
	const PLUGIN_AUTHOR		= 'Beu';

	/*
	* Private properties
	*/
	/** @var ManiaControl $maniaControl */
	private $maniaControl	= null;
	private $enabled		= false;

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
		return "A simple plugin that colors the admin logins in the chat";
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;
		$this->maniaControl->getCallbackManager()->registerCallbackListener('ManiaPlanet.PlayerChat', $this, 'handlePlayerChat');
		try {
			$this->maniaControl->getClient()->chatEnableManualRouting();
			$this->enabled = true;
		} catch (\Exception $ex) {
			$this->enabled = false;
			echo "error! \n";
		}
	}

	public function handlePlayerChat($callback) {
		$args = $callback[1];
		$this->onPlayerChatter($args[0], $args[1], $args[2]);
	}

	private function onPlayerChatter($playerUid, $login, $text) {
		if ($playerUid != 0 && substr($text, 0, 1) != "/" && $this->enabled) {
			$source_player = $this->maniaControl->getPlayerManager()->getPlayer($login);
			if ($source_player == null) {
				return;
			}
			$nick = $source_player->nickname;
			$authLevel = $source_player->authLevel;

			if ($authLevel > 0) {
					$color = $this->maniaControl->getColorManager()->getColorByLevel($authLevel);
			}
			
			try {
				// change text color, if admin is defined at admingroups
				if ($authLevel > 0) {
						$this->chatSendServerMessage('[$<' . $color . $nick . '$>] ' . $text);
				} else {
						$this->chatSendServerMessage('[' . $nick . '] ' . $text);
				}
			} catch (\Exception $e) {
				echo "error while sending chat message to $login: " . $e->getMessage() . "\n";
			}
		}
	}

	public function chatSendServerMessage($text) {
		$this->maniaControl->getClient()->chatSendServerMessage($text);
	}

	/**
	 * Unload the plugin and its Resources
	 */
	public function unload() {
		$this->maniaControl->getClient()->chatEnableManualRouting(false);		
		$this->maniaControl->getCallbackManager()->unregisterCallbackListening('ManiaPlanet.OnPlayerChat', $this);
		
	}
}
