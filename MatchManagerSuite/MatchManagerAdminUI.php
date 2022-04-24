<?php

namespace MatchManagerSuite;

use FML\Controls\Frame;
use FML\Controls\Label;
use FML\Controls\Quad;
use FML\ManiaLink;

use ManiaControl\Manialinks\ManialinkManager;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;

use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Logger;
use ManiaControl\ManiaControl;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Plugins\PluginManager;
use ManiaControl\Plugins\PluginMenu;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;
use ManiaControl\Callbacks\TimerListener;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Callbacks\CallbackManager;

use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;

use ManiaControl\Script\InvokeScriptCallback;


if (!class_exists('MatchManagerSuite\MatchManagerCore')) {
	$this->maniaControl->getChat()->sendErrorToAdmins('MatchManager Core is required to use one of MatchManager plugin. Install it and restart Maniacontrol');
	Logger::logError('MatchManager Core is required to use one of MatchManager plugin. Install it and restart Maniacontrol');
	return false;
}

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
	const PLUGIN_VERSION									= 1;
	const PLUGIN_NAME										= 'MatchManager Admin UI';
	const PLUGIN_AUTHOR										= 'Beu';

	const MLID_ADMINUI_SIDEMENU	 							= 'Matchmanager.AdminUI';

	const ML_ACTION_CORE_MANAGESETTINGS						= 'MatchManager.AdminUI.ManageSettings';
	const ML_ACTION_CORE_STOPMATCH							= 'MatchManager.AdminUI.StopMatch';
	const ML_ACTION_CORE_PAUSEMATCH							= 'MatchManager.AdminUI.PauseMatch';
	const ML_ACTION_CORE_SKIPROUND							= 'MatchManager.AdminUI.SkipRound';
	const ML_ACTION_CORE_STARTMATCH							= 'MatchManager.AdminUI.StartMatch';
	const ML_ACTION_MULTIPLECONFIGMANAGER_OPENCONFIGMANAGER	= 'MatchManager.AdminUI.OpenConfigManager';
	const ML_ACTION_GSHEET_MANAGESETTINGS					= 'MatchManager.AdminUI.ManageGSheet';


	const SETTING_POSX										= 'Position X of the plugin';
	const SETTING_POSY										= 'Position Y of the plugin';

	// MatchManagerWidget Properties
	const MATCHMANAGERCORE_PLUGIN							= 'MatchManagerSuite\MatchManagerCore';
	const MATCHMANAGERGSHEET_PLUGIN							= 'MatchManagerSuite\MatchManagerGSheet';
	const MATCHMANAGERMULTIPLECONFIGMANAGER_PLUGIN			= 'MatchManagerSuite\MatchManagerMultipleConfigManager';

	/*
	 * Private properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl 			= null;
	/** @var \MatchManagerSuite\MatchManagerCore */
	private $MatchManagerCore 		= null;

	private $manialink 				= null;

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
		if (!$this->maniaControl->getPluginManager()->getSavedPluginStatus(self::MATCHMANAGERCORE_PLUGIN)) {
			throw new \Exception('MatchManager Core is needed to use ' . self::PLUGIN_NAME);
		}

		if ($this->maniaControl->getPluginManager()->getSavedPluginStatus(self::MATCHMANAGERCORE_PLUGIN)) {
			// plugin are loaded in alphabetic order, just wait 1 sec before trying to load MatchManager Core
			$this->maniaControl->getTimerManager()->registerOneTimeListening($this, function () {
				$this->MatchManagerCore = $this->maniaControl->getPluginManager()->getPlugin(self::MATCHMANAGERCORE_PLUGIN);
				if ($this->MatchManagerCore === null) {
					$this->maniaControl->getChat()->sendErrorToAdmins('MatchManager Core is needed to use ' . self::PLUGIN_NAME . ' plugin.');
					$this->maniaControl->getPluginManager()->deactivatePlugin((get_class()));
				} else {
					$this->generateManialink();
					$this->displayManialink();
				}
			}, 1000);
		} else {
			throw new \Exception('MatchManager Core is needed to use ' . self::PLUGIN_NAME);
		}

		$this->maniaControl->getCallbackManager()->registerCallbackListener(PluginManager::CB_PLUGIN_LOADED, $this, 'handlePluginLoaded');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PluginManager::CB_PLUGIN_UNLOADED, $this, 'handlePluginUnloaded');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerConnect');

		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::MP_STARTMATCHSTART, $this, 'generateManialink');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(MatchManagerCore::CB_MATCHMANAGER_STARTMATCH, $this, 'generateManialink');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(MatchManagerCore::CB_MATCHMANAGER_ENDMATCH, $this, 'generateManialink');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(MatchManagerCore::CB_MATCHMANAGER_STOPMATCH, $this, 'generateManialink');

		$this->maniaControl->getCallbackManager()->registerCallbackListener(CallbackManager::CB_MP_PLAYERMANIALINKPAGEANSWER, $this, 'handleManialinkPageAnswer');

		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_POSX, 156., "");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_POSY, 24., "");

		return true;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {
		$this->maniaControl->getManialinkManager()->hideManialink(self::MLID_ADMINUI_SIDEMENU);
	}

	/**
	 * Update Widgets on Setting Changes
	 *
	 * @param Setting $setting
	 */
	public function updateSettings(Setting $setting) {
		if ($setting->belongsToClass($this)) {
			$this->generateManialink();
		}
	}

	/**
	 * Handle when a player connects
	 * 
	 * @param Player $player
	 */
	public function handlePlayerConnect(Player $player) {
		if ($player->authLevel > 0) {
			$this->maniaControl->getManialinkManager()->sendManialink($this->manialink,$player->login);
		}
	}
	
	/**
	 * send Manialink to admins
	 *
	 * @return void
	 */
	private function displayManialink() {
		$admins = $this->maniaControl->getAuthenticationManager()->getAdmins();
		if (!empty($admins)) {
			$this->maniaControl->getManialinkManager()->sendManialink($this->manialink, $admins);
		}
	}

	/**
	 * handleManialinkPageAnswer
	 *
	 * @param  array $callback
	 * @return void
	 */
	public function handleManialinkPageAnswer(array $callback) {
		$actionId    = $callback[1][2];
		$actionArray = explode('.', $actionId);
		if ($actionArray[0] != "MatchManager" || $actionArray[1] != "AdminUI") {
			return;
		}

		$login = $callback[1][1];
		$player = $this->maniaControl->getPlayerManager()->getPlayer($login);
		if ($player->authLevel <= 0) {
			return;
		}

		switch ($actionId) {
			case self::ML_ACTION_CORE_MANAGESETTINGS:
				$pluginMenu = $this->maniaControl->getPluginManager()->getPluginMenu();
				if (defined("\ManiaControl\ManiaControl::ISTRACKMANIACONTROL")) {
					$player->setCache($pluginMenu, PluginMenu::CACHE_SETTING_CLASS, "PluginMenu.Settings." . self::MATCHMANAGERCORE_PLUGIN);
				} else {
					$player->setCache($pluginMenu, PluginMenu::CACHE_SETTING_CLASS, self::MATCHMANAGERCORE_PLUGIN);
				}
				$this->maniaControl->getConfigurator()->showMenu($player, $pluginMenu);
				break;
			case self::ML_ACTION_CORE_STOPMATCH:
				$this->MatchManagerCore->MatchStop();
				break;
			case self::ML_ACTION_CORE_PAUSEMATCH:
				$this->MatchManagerCore->setNadeoPause();
				break;
			case self::ML_ACTION_CORE_SKIPROUND:
				$this->MatchManagerCore->onCommandMatchEndWU(array(), $player); 
				$this->MatchManagerCore->onCommandUnsetPause(array(), $player); 
				$this->MatchManagerCore->onCommandMatchEndRound(array(), $player); 
				break;
			case self::ML_ACTION_CORE_STARTMATCH:
				$this->MatchManagerCore->MatchStart();
				break;
			case self::ML_ACTION_MULTIPLECONFIGMANAGER_OPENCONFIGMANAGER:
				/** @var \MatchManagerSuite\MatchManagerMultipleConfigManager */
				$MatchManagerMultipleConfigManager = $this->maniaControl->getPluginManager()->getPlugin(self::MATCHMANAGERMULTIPLECONFIGMANAGER_PLUGIN);
				if ($MatchManagerMultipleConfigManager !== null) {
					$MatchManagerMultipleConfigManager->showConfigListUI(array(), $player);
				}
				break;
			case self::ML_ACTION_GSHEET_MANAGESETTINGS:
				$pluginMenu = $this->maniaControl->getPluginManager()->getPluginMenu();
				if (defined("\ManiaControl\ManiaControl::ISTRACKMANIACONTROL")) {
					$player->setCache($pluginMenu, PluginMenu::CACHE_SETTING_CLASS, "PluginMenu.Settings." . self::MATCHMANAGERGSHEET_PLUGIN);
				} else {
					$player->setCache($pluginMenu, PluginMenu::CACHE_SETTING_CLASS, self::MATCHMANAGERGSHEET_PLUGIN);
				}
				$this->maniaControl->getConfigurator()->showMenu($player, $pluginMenu);
				break;
		}
	}
	
	/**
	 * generate, store and automatically send it to the admin
	 *
	 * @return void
	 */
	public function generateManialink() {
		if ($this->MatchManagerCore === null) return;
		$itemSize          = 6.;
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

		// Admin Menu Icon Frame
		$iconFrame = new Frame();
		$frame->addChild($iconFrame);
		$iconFrame->setPosition($posX, $posY);

		$backgroundQuad = new Quad();
		$iconFrame->addChild($backgroundQuad);
		$backgroundQuad->setSize($itemSize * $itemMarginFactorX, $itemSize * $itemMarginFactorY);
		$backgroundQuad->setStyles($quadStyle, $quadSubstyle);
		$backgroundQuad->setZ(-1.);

		$itemQuad = new Label();
		$iconFrame->addChild($itemQuad);
		$itemQuad->setText('$fc3$w🏆$m');
		$itemQuad->setSize($itemSize, $itemSize);
		$itemQuad->setAreaFocusColor("00000000");
		$itemQuad->setAreaColor("00000000");

		// Admin Menu Description
		$descriptionLabel = new Label();
		$frame->addChild($descriptionLabel);
		$descriptionLabel->setAlign($descriptionLabel::RIGHT, $descriptionLabel::TOP);
		$descriptionLabel->setSize(40, 4);
		$descriptionLabel->setTextSize(1);
		$descriptionLabel->setTextColor('fff');
		$descriptionLabel->setTextPrefix('$s');

		// Admin Menu
		$popoutFrame = new Frame();
		$frame->addChild($popoutFrame);
		$popoutFrame->setPosition($posX - $itemSize * 0.5, $posY);
		$popoutFrame->setHorizontalAlign($popoutFrame::RIGHT);
		$popoutFrame->setVisible(false);

		$backgroundQuad = new Quad();
		$popoutFrame->addChild($backgroundQuad);
		$backgroundQuad->setHorizontalAlign($backgroundQuad::RIGHT);
		$backgroundQuad->setStyles($quadStyle, $quadSubstyle);

		$backgroundQuad->setZ(-1.);

		$itemQuad->addToggleFeature($popoutFrame);

		// Add items
		$itemPosX = -1;

		// Settings:
		$menuQuad = new Quad();
		$popoutFrame->addChild($menuQuad);
		$menuQuad->setStyle("UICommon64_1");
		$menuQuad->setSubStyle("Settings_light");
		$menuQuad->setSize($itemSize, $itemSize);
		$menuQuad->setX($itemPosX);
		$menuQuad->setHorizontalAlign($menuQuad::RIGHT);
		$itemPosX -= $itemSize * 1.05;
		$menuQuad->addTooltipLabelFeature($descriptionLabel, "Manage Core Settings");
		$menuQuad->setAction(self::ML_ACTION_CORE_MANAGESETTINGS);

		$MatchManagerMultipleConfigManager = $this->maniaControl->getPluginManager()->getPlugin(self::MATCHMANAGERMULTIPLECONFIGMANAGER_PLUGIN);
		if ($MatchManagerMultipleConfigManager !== null) {
			$menuQuad = new Quad();
			$popoutFrame->addChild($menuQuad);
			$menuQuad->setStyle("UICommon64_2");
			$menuQuad->setSubStyle("Plugin_light");
			$menuQuad->setSize($itemSize, $itemSize);
			$menuQuad->setX($itemPosX);
			$menuQuad->setHorizontalAlign($menuQuad::RIGHT);
			$itemPosX -= $itemSize * 1.05;
			$menuQuad->addTooltipLabelFeature($descriptionLabel, "Manage Multiple Configs");
			$menuQuad->setAction(self::ML_ACTION_MULTIPLECONFIGMANAGER_OPENCONFIGMANAGER);
		}

		$MatchManagerGsheet = $this->maniaControl->getPluginManager()->getPlugin(self::MATCHMANAGERGSHEET_PLUGIN);
		if ($MatchManagerGsheet !== null) {
			$menuQuad = new Quad();
			$popoutFrame->addChild($menuQuad);
			$menuQuad->setStyle("UICommon64_2");
			$menuQuad->setSubStyle("DisplayIcons_light");
			$menuQuad->setSize($itemSize, $itemSize);
			$menuQuad->setX($itemPosX);
			$menuQuad->setHorizontalAlign($menuQuad::RIGHT);
			$itemPosX -= $itemSize * 1.05;
			$menuQuad->addTooltipLabelFeature($descriptionLabel, "Manage Google Sheet config");
			$menuQuad->setAction(self::ML_ACTION_GSHEET_MANAGESETTINGS);
		}

		if ($this->MatchManagerCore->getMatchStatus()) {
			$menuQuad = new Quad();
			$popoutFrame->addChild($menuQuad);
			$menuQuad->setStyle("UICommon64_1");
			$menuQuad->setSubStyle("Stop_light");
			$menuQuad->setSize($itemSize, $itemSize);
			$menuQuad->setX($itemPosX);
			$menuQuad->setHorizontalAlign($menuQuad::RIGHT);
			$itemPosX -= $itemSize * 1.05;
			$menuQuad->addTooltipLabelFeature($descriptionLabel, "Stop the match");
			$menuQuad->setAction(self::ML_ACTION_CORE_STOPMATCH);

			$menuQuad = new Quad();
			$popoutFrame->addChild($menuQuad);
			$menuQuad->setStyle("UICommon64_1");
			$menuQuad->setSubStyle("Pause_light");
			$menuQuad->setSize($itemSize, $itemSize);
			$menuQuad->setX($itemPosX);
			$menuQuad->setHorizontalAlign($menuQuad::RIGHT);
			$itemPosX -= $itemSize * 1.05;
			$menuQuad->addTooltipLabelFeature($descriptionLabel, "Pause the match");
			$menuQuad->setAction(self::ML_ACTION_CORE_PAUSEMATCH);

			$menuQuad = new Quad();
			$popoutFrame->addChild($menuQuad);
			$menuQuad->setStyle("UICommon64_1");
			$menuQuad->setSubStyle("Cross_light");
			$menuQuad->setSize($itemSize, $itemSize);
			$menuQuad->setX($itemPosX);
			$menuQuad->setHorizontalAlign($menuQuad::RIGHT);
			$itemPosX -= $itemSize * 1.05;
			$menuQuad->addTooltipLabelFeature($descriptionLabel, "Skip the round / Warmup / Pause");
			$menuQuad->setAction(self::ML_ACTION_CORE_SKIPROUND);
		} else {
			$menuQuad = new Quad();
			$popoutFrame->addChild($menuQuad);
			$menuQuad->setStyle("UICommon64_1");
			$menuQuad->setSubStyle("Play_light");
			$menuQuad->setSize($itemSize, $itemSize);
			$menuQuad->setX($itemPosX);
			$menuQuad->setHorizontalAlign($menuQuad::RIGHT);
			$itemPosX -= $itemSize * 1.05;
			$menuQuad->addTooltipLabelFeature($descriptionLabel, "Start the match");
			$menuQuad->setAction(self::ML_ACTION_CORE_STARTMATCH);
		}

		$descriptionLabel->setPosition($posX - (count($popoutFrame->getChildren()) - 1) * $itemSize * 1.05 - 5, $posY);
		$backgroundQuad->setSize((count($popoutFrame->getChildren()) - 1) * $itemSize * 1.05 + 2, $itemSize * $itemMarginFactorY);

		$this->manialink = $maniaLink;
		$this->displayManialink();
	}
	
	/**
	 * handlePluginUnloaded
	 *
	 * @param  string $pluginClass
	 * @param  Plugin $plugin
	 * @return void
	 */
	public function handlePluginUnloaded(string $pluginClass, Plugin $plugin) {
		if ($pluginClass == self::MATCHMANAGERCORE_PLUGIN) {
			$this->maniaControl->getChat()->sendErrorToAdmins(self::PLUGIN_NAME . " disabled because MatchManager Core is now disabled");
			$this->maniaControl->getPluginManager()->deactivatePlugin((get_class()));
		}
		if (strstr($pluginClass, "MatchManagerSuite")) {
			$this->generateManialink();
		}
	}

	/**
	 * handlePluginLoaded
	 *
	 * @param  string $pluginClass
	 * @param  Plugin $plugin
	 * @return void
	 */
	public function handlePluginLoaded(string $pluginClass, Plugin $plugin) {
		if (strstr($pluginClass, "MatchManagerSuite")) {
			$this->generateManialink();
		}
	}
}