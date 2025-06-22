<?php

namespace MatchManagerSuite;

use FML\Controls\Frame;
use FML\Controls\Label;
use FML\Controls\Quad;
use FML\ManiaLink;
use ManiaControl\Admin\AuthenticationManager;
use ManiaControl\Manialinks\ManialinkManager;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;

use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\ManiaControl;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;
use ManiaControl\Callbacks\TimerListener;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Logger;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;

/**
 * MatchManager Admin UI
 *
 * @author		Beu
 * @license		http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class MatchManagerAdminUI implements CallbackListener, ManialinkPageAnswerListener, TimerListener, Plugin {
	/*
	 * Constants
	 */
	const PLUGIN_ID											= 174;
	const PLUGIN_VERSION									= 2.1;
	const PLUGIN_NAME										= 'MatchManager Admin UI';
	const PLUGIN_AUTHOR										= 'Beu';

	const LOG_PREFIX										= '[MatchManagerAdminUI] ';

	const MLID_ADMINUI_SIDEMENU	 							= 'Matchmanager.AdminUI';

	const SETTING_POSX										= 'Position X of the plugin';
	const SETTING_POSY										= 'Position Y of the plugin';
	const SETTING_ADMIN_LEVEL 								= 'Minimum Admin level to see the Admin UI';

	const ML_ITEM_SIZE 		= 6.;

	/*
	 * Private properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl 			= null;

	private $manialink 				= null;
	private $updateManialink 		= true;

	/** @var MatchManagerAdminUI_MenuItem[] */
	private $menuItems					= [];

	/**
	 * @param \ManiaControl\ManiaControl $maniaControl
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
		return 'A small UI for admins';
	}

	/**
	 * @param \ManiaControl\ManiaControl $maniaControl
	 * @return bool
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		// Init plugin
		$this->maniaControl = $maniaControl;
		
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::AFTERLOOP, $this, 'handleAfterLoop');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerConnect');

		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_POSX, 156., "");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_POSY, 24., "");
		$this->maniaControl->getAuthenticationManager()->definePluginPermissionLevel($this, self::SETTING_ADMIN_LEVEL, AuthenticationManager::AUTH_LEVEL_ADMIN);

		return true;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {
		$this->maniaControl->getManialinkManager()->hideManialink(self::MLID_ADMINUI_SIDEMENU);
	}
	/**
	 * Custom log function to add prefix
	 * 
	 * @param mixed $message
	 */
	private function log(mixed $message) {
		Logger::log(self::LOG_PREFIX . $message);
	}

	/**
	 * Custom logError function to add prefix
	 * 
	 * @param mixed $message
	 */
	private function logError(mixed $message) {
		Logger::logError(self::LOG_PREFIX . $message);
	}

	/**
	 * afterPluginInit
	 *
	 * @return void 
	 */
	public function handleAfterLoop() {
		if ($this->updateManialink) {
			$this->updateManialink = false;
			$this->generateManialink();
		}
	}

	/**
	 * Update Widgets on Setting Changes
	 *
	 * @param Setting $setting
	 */
	public function updateSettings(Setting $setting) {
		if ($setting->belongsToClass($this)) {
			$this->updateManialink = true;
		}
	}

	/**
	 * Handle when a player connects
	 * 
	 * @param Player $player
	 */
	public function handlePlayerConnect(Player $player) {
		$authLevel = $this->maniaControl->getAuthenticationManager()->getAuthLevelInt($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_ADMIN_LEVEL));
		if ($this->maniaControl->getAuthenticationManager()->checkRight($player, $authLevel)) {
			$this->maniaControl->getManialinkManager()->sendManialink($this->manialink, $player->login);
		}
	}
	
	/**
	 * send Manialink to admins
	 *
	 * @return void
	 */
	private function displayManialink() {
		$authLevel = $this->maniaControl->getAuthenticationManager()->getAuthLevelInt($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_ADMIN_LEVEL));
		$admins = $this->maniaControl->getAuthenticationManager()->getAdmins($authLevel);
		if (count($admins) > 0) {
			$this->maniaControl->getManialinkManager()->sendManialink($this->manialink, $admins);
		}
	}
	
	/**
	 * generate, store and automatically send it to the admin
	 *
	 * @return void
	 */
	public function generateManialink() {
		$quadStyle         = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadStyle();
		$quadSubstyle      = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadSubstyle();
		$itemMarginFactorX = 1.3;
		$itemMarginFactorY = 1.2;

		$maniaLink = new ManiaLink(self::MLID_ADMINUI_SIDEMENU);
		$frame     = new Frame();
		$maniaLink->addChild($frame);
		$frame->setZ(ManialinkManager::MAIN_MANIALINK_Z_VALUE);

		$posX = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_POSX);
		$posY = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_POSY);

		if (count($this->menuItems) > 0) {
			// Admin Menu Icon Frame
			$iconFrame = new Frame();
			$frame->addChild($iconFrame);
			$iconFrame->setPosition($posX, $posY);

			$backgroundQuad = new Quad();
			$iconFrame->addChild($backgroundQuad);
			$backgroundQuad->setSize(self::ML_ITEM_SIZE * $itemMarginFactorX, self::ML_ITEM_SIZE * $itemMarginFactorY);
			$backgroundQuad->setStyles($quadStyle, $quadSubstyle);
			$backgroundQuad->setZ(-1.);

			$itemQuad = new Label();
			$iconFrame->addChild($itemQuad);
			$itemQuad->setText('$fc3$w🏆$m');
			$itemQuad->setSize(self::ML_ITEM_SIZE, self::ML_ITEM_SIZE);
			$itemQuad->setAreaFocusColor("00000000");
			$itemQuad->setAreaColor("00000000");

			// Admin Menu Description
			$descriptionLabel = new Label();
			$frame->addChild($descriptionLabel);
			$descriptionLabel->setAlign(Label::RIGHT, Label::TOP);
			$descriptionLabel->setSize(40, 4);
			$descriptionLabel->setTextSize(1);
			$descriptionLabel->setTextColor('fff');
			$descriptionLabel->setTextPrefix('$s');

			// Admin Menu
			$popoutFrame = new Frame();
			$frame->addChild($popoutFrame);
			$popoutFrame->setPosition($posX - self::ML_ITEM_SIZE * 0.5, $posY);
			$popoutFrame->setHorizontalAlign($popoutFrame::RIGHT);
			$popoutFrame->setVisible(false);

			$backgroundQuad = new Quad();
			$popoutFrame->addChild($backgroundQuad);
			$backgroundQuad->setHorizontalAlign($backgroundQuad::RIGHT);
			$backgroundQuad->setStyles($quadStyle, $quadSubstyle);
			$backgroundQuad->setZ(-1.);

			$itemQuad->addToggleFeature($popoutFrame);

			// Add items
			$itemPosX = -4.;

			// sort by Order desc
			usort($this->menuItems, function($a, $b) {
				return $b->getOrder() <=> $a->getOrder();
			});

			foreach ($this->menuItems as $menuItem) {
				$menuItem->buildControl($popoutFrame, $descriptionLabel, $itemPosX);
				$itemPosX -= self::ML_ITEM_SIZE * 1.05;
			}

			$descriptionLabel->setPosition($posX - (count($popoutFrame->getChildren()) - 1) * self::ML_ITEM_SIZE * 1.05 - 5, $posY);
			$backgroundQuad->setSize((count($popoutFrame->getChildren()) - 1) * self::ML_ITEM_SIZE * 1.05 + 2, self::ML_ITEM_SIZE * $itemMarginFactorY);
		}

		$this->manialink = $maniaLink;
		$this->displayManialink();
	}

	public function addMenuItem(MatchManagerAdminUI_MenuItem $menuItem) {
		$this->removeMenuItem($menuItem->getActionId());
		$this->log("New Menu Item: ". $menuItem->getActionId());
		$this->menuItems[] = $menuItem;

		$this->updateManialink = true;
	}

	public function removeMenuItem(string $actionId) {
		$this->log("Removing Menu Item: ". $actionId);
		$this->menuItems = array_filter($this->menuItems, function($menuItem) use ($actionId) {
			return $menuItem->getActionId() !== $actionId;
		});

		$this->updateManialink = true;
	}
}

class MatchManagerAdminUI_MenuItem {
	private string $actionId;
	private int $order = 100;
	private string $description = '';
	private string $text = '';
	private string $imageUrl = '';
	private string $style = '';
	private string $subStyle = '';
	private float $size = MatchManagerAdminUI::ML_ITEM_SIZE;

	public function getActionId() {
		return $this->actionId;
	}
	public function setActionId(string $actionId) {
		$this->actionId = $actionId;
		return $this;
	}

	public function getOrder() {
		return $this->order;
	}
	public function setOrder(int $order) {
		$this->order = $order;
		return $this;
	}
	
	public function getDescription() {
		return $this->description;
	}
	public function setDescription(string $description) {
		$this->description = $description;
		return $this;
	}
	
	public function getImageUrl() {
		return $this->imageUrl;
	}
	public function setImageUrl(string $imageUrl) {
		$this->imageUrl = $imageUrl;
		return $this;
	}

	public function getSize() {
		return $this->size;
	}
	public function setSize(float $size) {
		$this->size = $size;
		return $this;
	}

	public function getStyle() {
		return $this->imageUrl;
	}
	public function setStyle(string $style) {
		$this->style = $style;
		return $this;
	}

	public function getSubStyle() {
		return $this->imageUrl;
	}
	public function setSubStyle(string $subStyle) {
		$this->subStyle = $subStyle;
		return $this;
	}

	public function buildControl(Frame $parent, Label $descriptionLabel, float $posX) {
		$control = null;
		if ($this->text !== '') {
			$control = new Label();
			$control->setText($this->text);
		} else {
			$control = new Quad();
			if ($this->imageUrl !== '') {
				$control->setImageUrl($this->imageUrl);
				$control->setKeepRatio('fit');
			} else if ($this->style !== '') {
				$control->setStyles($this->style, $this->subStyle);
			} else {
				$control->setBackgroundColor('cccccc');
			}
		}

		$parent->addChild($control);
		$control->setSize($this->size, $this->size);
		$control->setX($posX);
		$control->setHorizontalAlign(Quad::CENTER);
		$control->addTooltipLabelFeature($descriptionLabel, $this->description);
		$control->setAction($this->actionId);
	}
}