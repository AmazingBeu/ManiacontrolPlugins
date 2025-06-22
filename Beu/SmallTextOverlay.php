<?php
namespace Beu;

use FML\Controls\Control;
use FML\Controls\Frame;
use FML\Controls\Label;
use FML\ManiaLink;
use ManiaControl\ManiaControl;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\TimerListener;
use ManiaControl\Players\Player;

use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;

/**
 * SmallTextOverlay
 *
 * @author	Beu
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class SmallTextOverlay implements TimerListener, CallbackListener, Plugin {
	/*
	* Constants
	*/
	const PLUGIN_ID			= 195;
	const PLUGIN_VERSION	= 1.4;
	const PLUGIN_NAME		= 'SmallTextOverlay';
	const PLUGIN_AUTHOR		= 'Beu';

	const SETTING_PRIMARY_TEXT		= "Primary Text";
	const SETTING_SECONDARY_TEXT	= "Secondary Text";
	const MLID_SMALLTEXTAD			= "SmallTextOverlay";

	/*
	* Private properties
	*/
	/** @var ManiaControl $maniaControl */
	private $maniaControl	= null;
	private $manialink		= null;

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
		return "Just add 2 text value on the bottom right of the screen, with settings as value";
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_PRIMARY_TEXT, "Primary Text");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_SECONDARY_TEXT, "Secondary Text");

		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerConnect');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');

		$this->generateManialink();
		$this->maniaControl->getManialinkManager()->sendManialink($this->manialink);

		$this->maniaControl->getTimerManager()->registerTimerListening($this, 'handle5Minutes', 300000);
	}
	
	/**
	 * Handle when a player connects
	 * 
	 * @param Player $player
	 */
	public function handlePlayerConnect(Player $player) {
		$this->maniaControl->getManialinkManager()->sendManialink($this->manialink, $player->login);
	}
	
	/**
	 * updateSettings
	 *
	 * @param  Setting $setting
	 * @return void
	 */
	public function updateSettings(?Setting $setting = null) {
		if ($setting !== null && !$setting->belongsToClass($this)) {
			return;
		}
		$this->generateManialink();
		$this->maniaControl->getManialinkManager()->sendManialink($this->manialink);
	}
	
	/**
	 * handle5Minutes
	 *
	 * @return void
	 */
	public function handle5Minutes() {
		// update UI if updated by an another
		$this->generateManialink();
		$this->maniaControl->getManialinkManager()->sendManialink($this->manialink);
	}
	
	/**
	 * generateManialink
	 *
	 * @return void
	 */
	private function generateManialink() {
		$manialink = new ManiaLink(self::MLID_SMALLTEXTAD);

		$frame = new Frame();
		$manialink->addChild($frame);
		$frame->setX(159.);
		$frame->setY(-60.);

		$primaryLabel = new Label();
		$frame->addChild($primaryLabel);
		$primaryLabel->setTextSize(3.5);
		$primaryLabel->setTextFont("GameFontExtraBold");
		$primaryLabel->setTextEmboss(true);
		$primaryLabel->setHorizontalAlign(Control::RIGHT);
		$primaryLabel->setText($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_PRIMARY_TEXT));

		$secondaryLabel = new Label();
		$frame->addChild($secondaryLabel);
		$secondaryLabel->setY(-6.);
		$secondaryLabel->setTextSize(2.5);
		$secondaryLabel->setTextFont("GameFontExtraBold");
		$secondaryLabel->setTextEmboss(true);
		$secondaryLabel->setHorizontalAlign(Control::RIGHT);
		$secondaryLabel->setText($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_SECONDARY_TEXT));

		$this->manialink = $manialink;
	}

	/**
	 * Unload the plugin and its Resources
	 */
	public function unload() {
		$this->maniaControl->getManialinkManager()->hideManialink(self::MLID_SMALLTEXTAD);
	}
}