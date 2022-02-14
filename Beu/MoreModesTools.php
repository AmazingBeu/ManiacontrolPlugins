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
	const PLUGIN_VERSION	= 1.0;
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
		return 'Tool to manage the Guestlist';
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		$this->maniaControl->getCommandManager()->registerCommandListener('pause', $this, 'onCommandPause', true, 'Launch the pause');
		$this->maniaControl->getCommandManager()->registerCommandListener('endpause', $this, 'onCommandEndPause', true, 'End the pause');
		$this->maniaControl->getCommandManager()->registerCommandListener('endround', $this, 'onCommandEndRound', true, 'end the round');
		$this->maniaControl->getCommandManager()->registerCommandListener(['endwu', 'endwarmup'], $this, 'onCommandEndWarmUp', true, 'End the WarmUp');

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
}
