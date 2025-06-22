<?php
namespace Beu;

use ManiaControl\ManiaControl;
use ManiaControl\Plugins\Plugin;
use \ManiaControl\Logger;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Players\Player;

use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;

use \Exception;

/**
 * Beu Donation Button
 *
 * @author	Beu
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class BeuDonationButton implements ManialinkPageAnswerListener, CallbackListener, Plugin {
	/*
	* Constants
	*/
	const PLUGIN_ID			= 169;
	const PLUGIN_VERSION	= 1.1;
	const PLUGIN_NAME		= 'Beu Donation Button';
	const PLUGIN_AUTHOR		= 'Beu';

	const SETTING_ISANEVENT		= "It's an Event server";

	/*
	* Private properties
	*/
	/** @var ManiaControl $maniaControl */
	private $maniaControl	= null;
	private $manialink		= "";

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
		return "A plugin to display a donation button";
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_ISANEVENT, True, 'Display the message at the 3rd person');

		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerConnect');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');

		$this->generateManialink();
		$this->maniaControl->getManialinkManager()->sendManialink($this->manialink);
	}
	
	/**
	 * Handle when a player connects
	 * 
	 * @param Player $player
	 */
	public function handlePlayerConnect(Player $player) {
		$this->maniaControl->getManialinkManager()->sendManialink($this->manialink,$player->login);
	}

	public function updateSettings(?Setting $setting = null) {
		$this->generateManialink();
		$this->maniaControl->getManialinkManager()->sendManialink($this->manialink);
	}

	private function generateManialink() {
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_ISANEVENT)) {
			$message = "This server is hosted for free by Beu. You can support him by sending a donation";
		} else {
			$message = "Support me and my work by sending a donation \$f00♥";
		}

		$this->manialink = <<<EOD
			<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
			<manialink id="XmlRpcUI_BeuDonationButton" version="3">
				<frame id="global-ui" pos="-40 -90" z-index="100" hidden="1">
					<quad size="80 15" z-index="-1" bgcolor="000" opacity="0.7" />
					<quad id="quad-button" pos="7.5 -7.5" size="10 10" bgcolor="fff" opacity="" valign="center" halign="center" url="https://www.paypal.com/donate/?hosted_button_id=8AG2MU7XQCKHU" scriptevents="1"/>
					<quad pos="7.5 -7.5" z-index="1" size="7 7" valign="center" image="https://files.virtit.fr/TrackMania/Images/Others/Paypal.png" halign="center" />
					<label pos="46 -7.5" z-index="0" size="60 10" text="{$message}" halign="center" valign="center" textfont="GameFontBlack" autonewline="1" textsize="2"/>
				</frame>
				<script><!--
					main () {
						declare CMlFrame Global_UI = (Page.GetFirstChild("global-ui") as CMlFrame);
						declare Boolean Component_UIModule_Race_ScoresTable_Visibility_LayerIsVisible for UI;
						declare Boolean Last_FrameIsVisible;
						while(True) {
							yield;
							if (Component_UIModule_Race_ScoresTable_Visibility_LayerIsVisible && !Last_FrameIsVisible) {
								if (GUIPlayer != InputPlayer || (GUIPlayer != Null && GUIPlayer.SpawnStatus == CSmPlayer::ESpawnStatus::NotSpawned)) {
									Last_FrameIsVisible = True;

									AnimMgr.Flush(Global_UI);
									AnimMgr.Add(Global_UI, "<anim hidden=\"0\" pos=\"-40 -75\"/>", 100 , CAnimManager::EAnimManagerEasing::SineInOut);
								}
							} else if (!Component_UIModule_Race_ScoresTable_Visibility_LayerIsVisible && Last_FrameIsVisible) {
								Last_FrameIsVisible = False;

								AnimMgr.Flush(Global_UI);
								AnimMgr.Add(Global_UI, "<anim hidden=\"1\" pos=\"-40 -90\"/>", 100 , CAnimManager::EAnimManagerEasing::SineInOut);
							}

							foreach(Event in PendingEvents) {
								log(Event.Type);
								if (Event.Type == CMlScriptEvent::Type::MouseOver && Event.ControlId == "quad-button") {
									declare CMlQuad Quad = (Event.Control as CMlQuad);
									Quad.Opacity = .8;
								} else if (Event.Type == CMlScriptEvent::Type::MouseOut && Event.ControlId == "quad-button") {
									declare CMlQuad Quad = (Event.Control as CMlQuad);
									Quad.Opacity = 1.;
								}
							}
						}
					}
				--></script>
			</manialink>
		EOD;

	}

	/**
	 * Unload the plugin and its Resources
	 */
	public function unload() {
		$this->maniaControl->getManialinkManager()->hideManialink("XmlRpcUI_BeuDonationButton");
	}
}
