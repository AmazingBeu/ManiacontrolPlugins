<?php

namespace MatchManagerSuite;

use FML\Controls\Labels\Label_Button;
use FML\Controls\Labels\Label_Text;
use FML\Controls\Quad;
use FML\Controls\Frame;
use FML\Controls\Entry;
use FML\Components\CheckBox;
use FML\Controls\Quads\Quad_BgsPlayerCard;
use FML\ManiaLink;
use FML\Script\Features\Paging;
use ManiaControl\Manialinks\LabelLine;
use ManiaControl\Manialinks\ManialinkManager;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;

use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\CallbackManager;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Logger;
use ManiaControl\ManiaControl;
use ManiaControl\Players\Player;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Plugins\PluginManager;
use ManiaControl\Commands\CommandListener;
use ManiaControl\Plugins\PluginMenu;

if (!class_exists('MatchManagerSuite\MatchManagerCore')) {
	$this->maniaControl->getChat()->sendErrorToAdmins('MatchManager Core is required to use MatchManagerMultipleConfigManager plugin. Install it and restart Maniacontrol');
	Logger::logError('MatchManager Core is required to use MatchManagerMultipleConfigManager plugin. Install it and restart Maniacontrol');
	return false;
}
use MatchManagerSuite\MatchManagerCore;

/**
 * MatchManager Multiple Config Manager
 *
 * @author		Beu
 * @license		http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class MatchManagerMultipleConfigManager implements ManialinkPageAnswerListener, CommandListener, CallbackListener, Plugin {
	/*
	 * Constants
	 */
	const PLUGIN_ID											= 171;
	const PLUGIN_VERSION									= 1.6;
	const PLUGIN_NAME										= 'MatchManager Multiple Config Manager';
	const PLUGIN_AUTHOR										= 'Beu';

	const LOG_PREFIX										= '[MatchManagerMultipleConfigManager] ';


	// MatchManagerWidget Properties
	const MATCHMANAGERCORE_PLUGIN							= 'MatchManagerSuite\MatchManagerCore';
	const MATCHMANAGERADMINUI_PLUGIN						= 'MatchManagerSuite\MatchManagerAdminUI';

	const DB_MATCHCONFIG									= 'MatchManager_MatchConfigs';

	const ML_ID												= 'MatchManager.MultiConfigManager.UI';
	const ML_ACTION_OPENSETTINGS							= 'MatchManagerSuite\MatchManagerMultipleConfigManager.OpenSettings';
	const ML_ACTION_REMOVE_CONFIG							= 'MatchManagerSuite\MatchManagerMultipleConfigManager.RemoveConfig';
	const ML_ACTION_LOAD_CONFIG								= 'MatchManagerSuite\MatchManagerMultipleConfigManager.LoadConfig';
	const ML_ACTION_LOAD_CONFIG_PAGE						= 'MatchManagerSuite\MatchManagerMultipleConfigManager.LoadConfigPage';
	const ML_ACTION_SAVE_CONFIG								= 'MatchManagerSuite\MatchManagerMultipleConfigManager.SaveConfig';
	const ML_ACTION_SAVE_CONFIG_PAGE						= 'MatchManagerSuite\MatchManagerMultipleConfigManager.SaveConfigPage';
	const ML_NAME_CONFIGNAME								= 'MatchManager.MultiConfigManager.ConfigName';

	const CB_LOADCONFIG										= 'MatchManager.MultiConfigManager.LoadConfig';
	const CB_SAVECONFIG										= 'MatchManager.MultiConfigManager.SaveConfig';

	/*
	 * Private properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl 			= null;
	/** @var MatchManagerCore $MatchManagerCore */
	private $MatchManagerCore		= null;

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
		return 'Manage your multiple MatchManager configurations';
	}

	/**
	 * @param \ManiaControl\ManiaControl $maniaControl
	 * @return bool
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		// Init plugin
		$this->maniaControl = $maniaControl;
		$this->MatchManagerCore = $this->maniaControl->getPluginManager()->getPlugin(self::MATCHMANAGERCORE_PLUGIN);

		if ($this->MatchManagerCore == Null) {
			throw new \Exception('MatchManager Core is needed to use MatchManager Players Pause plugin');
		}

		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::AFTERINIT, $this, 'handleAfterInit');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PluginManager::CB_PLUGIN_LOADED, $this, 'handlePluginLoaded');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PluginManager::CB_PLUGIN_UNLOADED, $this, 'handlePluginUnloaded');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(CallbackManager::CB_MP_PLAYERMANIALINKPAGEANSWER, $this, 'handleManialinkPageAnswer');

		$this->maniaControl->getCommandManager()->registerCommandListener('matchconfig', $this, 'showConfigListUI', true, 'Start a match');

		$this->initTables();
		$this->updateAdminUIMenuItems();
		return true;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {
		/** @var \MatchManagerSuite\MatchManagerAdminUI|null */
		$adminUIPlugin = $this->maniaControl->getPluginManager()->getPlugin(self::MATCHMANAGERADMINUI_PLUGIN);
		if ($adminUIPlugin !== null) {
			$adminUIPlugin->removeMenuItem(self::ML_ACTION_OPENSETTINGS);
		}
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
	 * handle Plugin Loaded
	 * 
	 * @param string $pluginClass 
	 */
	public function handleAfterInit() {
		$this->updateAdminUIMenuItems();
	}

	/**
	 * handle Plugin Loaded
	 * 
	 * @param string $pluginClass 
	 */
	public function handlePluginLoaded(string $pluginClass) {
		if ($pluginClass === self::MATCHMANAGERADMINUI_PLUGIN) {
			$this->updateAdminUIMenuItems();
		}
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
			$this->log(self::PLUGIN_NAME . " disabled because MatchManager Core is now disabled");
			$this->maniaControl->getPluginManager()->deactivatePlugin((get_class($this)));
		}
	}

	/**
	 * Initialize needed database tables
	 */
	private function initTables() {
		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		$query = 'CREATE TABLE IF NOT EXISTS `' . self::DB_MATCHCONFIG . '` (
			`id` int NOT NULL AUTO_INCREMENT,
			`name` VARCHAR(255) NOT NULL,
			`gamemodebase` VARCHAR(32) NOT NULL,
			`config` TEXT,
			`date` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
		$mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error, E_USER_ERROR);
		}
	}

	/**
	 * Add items in AdminUI plugin
	 */
	public function updateAdminUIMenuItems() {
		/** @var \MatchManagerSuite\MatchManagerAdminUI|null */
		$adminUIPlugin = $this->maniaControl->getPluginManager()->getPlugin(self::MATCHMANAGERADMINUI_PLUGIN);
		if ($adminUIPlugin === null) return;

		$adminUIPlugin->removeMenuItem(self::ML_ACTION_OPENSETTINGS);

		$menuItem = new \MatchManagerSuite\MatchManagerAdminUI_MenuItem();
		$menuItem->setActionId(self::ML_ACTION_OPENSETTINGS)->setOrder(200)->setStyle('UICommon64_2')->setSubStyle('Plugin_light')->setDescription('Manage Multiple Configs');
		$adminUIPlugin->addMenuItem($menuItem);
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

		if ($actionArray[0] !== self::class) {
			return;
		}

		$login = $callback[1][1];
		$player = $this->maniaControl->getPlayerManager()->getPlayer($login);
		if ($player->authLevel <= 0) {
			return;
		}

		$action = $actionArray[0] . "." . $actionArray[1];

		switch ($action) {
			case self::ML_ACTION_OPENSETTINGS:
				$this->showConfigListUI(array(), $player);
				break;
			case self::ML_ACTION_REMOVE_CONFIG:
				$id = intval($actionArray[2]);
				$this->log("Removing config: " . $id);
				$mysqli = $this->maniaControl->getDatabase()->getMysqli();

				$query = $mysqli->prepare('DELETE FROM  `'. self::DB_MATCHCONFIG .'` WHERE id = ?;');
				$query->bind_param('i', $id);
				if (!$query->execute()) {
					trigger_error('Error executing MySQL query: ' . $query->error);
				}
				$this->showConfigListUI(array(), $player);
				break;
			case self::ML_ACTION_LOAD_CONFIG_PAGE:
				$this->showConfigListUI(array(), $player);
				break;
			case self::ML_ACTION_LOAD_CONFIG:
				$id = intval($actionArray[2]);
				// Hide loading before because it can take few seconds
				$this->maniaControl->getManialinkManager()->hideManialink(ManialinkManager::MAIN_MLID, $login);
				$this->loadConfig($id);
				break;
			case self::ML_ACTION_SAVE_CONFIG_PAGE:
				$this->showSaveConfigUI($player);
				break;
			case self::ML_ACTION_SAVE_CONFIG:
				if ($callback[1][3][0]["Value"]) {
					$this->showConfigListUI(array(), $player);
					$this->saveCurrentConfig($callback[1][3]);
					$this->showConfigListUI(array(), $player);
				}
			break;
		}
	}

	/**
	 * loadConfig
	 *
	 * @param  int $id
	 * @return void
	 */
	public function loadConfig(int $id) {
		if ($this->MatchManagerCore->getMatchStatus()) {
			$this->logError("Impossible to load config during a match");
			$this->maniaControl->getChat()->sendErrorToAdmins($this->MatchManagerCore->getChatPrefix() .'Impossible to load config during a match');
			return;
		}
		$mysqli = $this->maniaControl->getDatabase()->getMysqli();

		$query = $mysqli->prepare('SELECT name,config FROM  `'. self::DB_MATCHCONFIG .'` WHERE id = ?;');
		$query->bind_param('i', $id);

		if (!$query->execute()) {
			trigger_error('Error executing MySQL query: ' . $query->error);
		}
		$mysqlresult = $query->get_result();

		$result = array();
		while ($row = $mysqlresult->fetch_assoc()) {
			array_push($result, $row);
		}

		if ($result[0] && $result[0]["config"]) {
			$allconfigs = json_decode($result[0]["config"],true);
			if ($allconfigs != null) {
				$this->log("Loading config: " . $id);
				$someconfignotloaded = false;
				foreach ($allconfigs as $plugin => $configs) {
					$pluginclass = $this->maniaControl->getPluginManager()->getPlugin($plugin);
					if ($pluginclass != null) {
						foreach ($configs as $name => $value) {
							// When loading setting, cache could be wrong compared to the data stored in the database. So force clear everytime to be sure to have the good value
							$this->maniaControl->getSettingManager()->clearStorage();
							$setting = $this->maniaControl->getSettingManager()->getSettingObject($pluginclass, $name);
							if ($setting != null) {
								if ($setting->value != $value) {
									$this->log("Saving new setting " . $name);
									$setting->value = $value;
									$this->maniaControl->getSettingManager()->saveSetting($setting);
								}
							} else {
								$someconfignotloaded = true;
								$this->log("Unable to load setting: " . $name);
							}
						}
					}
				}
				if ($someconfignotloaded) {
					$this->maniaControl->getChat()->sendErrorToAdmins($this->MatchManagerCore->getChatPrefix() .'One or more settings could not be imported');
				}
				$this->maniaControl->getSettingManager()->clearStorage();
				$this->maniaControl->getChat()->sendSuccessToAdmins($this->MatchManagerCore->getChatPrefix() .'MatchManager Config "' . $result[0]["name"] . '" loaded');
			}
		}

		$this->maniaControl->getCallbackManager()->triggerCallback(self::CB_LOADCONFIG, $result[0]["name"]);
	}

	/**
	 * saveCurrentConfig
	 *
	 * @param  array $fields
	 * @return void
	 */
	public function saveCurrentConfig(array $fields) {
		$this->log("Saving current config");
		$result = array();
		$configname = "";
		$gamemodebase = "";

		foreach($fields as $field) {
			if ($field["Name"] == self::ML_NAME_CONFIGNAME) {
				$configname = $field["Value"];
				continue;
			}
			if (strpos($field["Name"], self::ML_ACTION_SAVE_CONFIG) === 0) {
				if ($field["Value"] == "1") {
					$class = substr($field["Name"],strlen(self::ML_ACTION_SAVE_CONFIG) + 1);
					$result[$class] = array();
					$settings = $this->maniaControl->getSettingManager()->getSettingsByClass($class);
					foreach ($settings as $setting) {
						$result[$class][$setting->setting] = $setting->value;

						if ($setting->setting == MatchManagerCore::SETTING_MATCH_GAMEMODE_BASE) {
							$gamemodebase = $setting->value;
						}
					}
				}
			}
		}

		if ($configname == "" || count($result) == 0 || $gamemodebase == "") {
			return;
		}

		$this->saveConfig($configname, $gamemodebase, json_encode($result));
	}

	/**
	 * getSavedConfigs
	 * @return array 
	 */
	public function getSavedConfigs() {
		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		$query = 'SELECT `id`,`name`,`gamemodebase`,`date` FROM `' . self::DB_MATCHCONFIG . '` ORDER BY id DESC';
		$result = $mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error, E_USER_ERROR);
		}

		return $result->fetch_all(MYSQLI_ASSOC);
	}

	/**
	 * saveConfig
	 * @param string $configname 
	 * @param string $gamemodebase 
	 * @param string $config 
	 * @return void 
	 */
	public function saveConfig(string $configname, string $gamemodebase, string $config) {
		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		$query = $mysqli->prepare('INSERT INTO `' . self::DB_MATCHCONFIG . '` (`name`,`gamemodebase`,`config`) VALUES (?, ?, ?);');
		$query->bind_param('sss', $configname, $gamemodebase, $config);
		if (!$query->execute()) {
			trigger_error('Error executing MySQL query: ' . $query->error);
		}
		$this->maniaControl->getCallbackManager()->triggerCallback(self::CB_SAVECONFIG, $configname);
	}

	/**
	 * getConfig
	 * @param string $name 
	 * @return object|null config 
	 */
	public function getConfig(string $name) {
		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		$stmt = $mysqli->prepare('SELECT id FROM `' . self::DB_MATCHCONFIG . '` WHERE `name` = ? LIMIT 1;');
		$stmt->bind_param('s', $name);
		if (!$stmt->execute()) {
			trigger_error('Error executing MySQL query: ' . $stmt->error);
		}
		return $stmt->get_result()->fetch_object();
	}

	/**
	 * Shows a ManiaLink list with the local records.
	 *
	 * @api
	 * @param array  $chat
	 * @param Player $player
	 */
	public function showConfigListUI(array $chat, Player $player) {
		$width  = $this->maniaControl->getManialinkManager()->getStyleManager()->getListWidgetsWidth();
		$height = $this->maniaControl->getManialinkManager()->getStyleManager()->getListWidgetsHeight();

		// create manialink
		$maniaLink = new ManiaLink(ManialinkManager::MAIN_MLID);
		$script    = $maniaLink->getScript();
		$paging    = new Paging();
		$script->addFeature($paging);

		// Main frame
		$frame = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultListFrame($script, $paging);
		$maniaLink->addChild($frame);

		// Start offsets
		$posX = -$width / 2;
		$posY = $height / 2;

		// Predefine Description Label
		$descriptionLabel = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultDescriptionLabel();
		$frame->addChild($descriptionLabel);

		// Headline
		$headFrame = new Frame();
		$frame->addChild($headFrame);
		$headFrame->setY($posY - 5);

		$labelLine = new LabelLine($headFrame);
		$labelLine->addLabelEntryText('ID', $posX + 5);
		$labelLine->addLabelEntryText('Name', $posX + 15);
		$labelLine->addLabelEntryText('Game mode base', $posX + 70);
		$labelLine->addLabelEntryText('Date', $posX + 110);
		$labelLine->addLabelEntryText('Actions', $width / 2 - 16);
		$labelLine->render();

		$index     = 0;
		$posY      = $height / 2 - 10;
		$pageFrame = null;

		foreach ($this->getSavedConfigs() as $config) {
			if ($index % 16 === 0) {
				$pageFrame = new Frame();
				$frame->addChild($pageFrame);
				$posY = $height / 2 - 10;
				$paging->addPageControl($pageFrame);
			}

			$configFrame = new Frame();
			$pageFrame->addChild($configFrame);

			if ($index % 2 === 0) {
				$lineQuad = new Quad_BgsPlayerCard();
				$configFrame->addChild($lineQuad);
				$lineQuad->setSize($width, 4);
				$lineQuad->setSubStyle($lineQuad::SUBSTYLE_BgPlayerCardBig);
				$lineQuad->setZ(-0.001);
			}

			$labelLine = new LabelLine($configFrame);
			$labelLine->addLabelEntryText($config["id"], $posX + 5, 13);
			$labelLine->addLabelEntryText($config["name"], $posX + 15, 52);
			$labelLine->addLabelEntryText($config["gamemodebase"], $posX + 70, 31);
			$labelLine->addLabelEntryText($config["date"], $posX + 100, $width / 2 - ($posX + 110));
			$labelLine->render();

			// Remove Config button
			$removeButton = new Label_Button();
			$configFrame->addChild($removeButton);
			$removeButton->setX($width / 2 - 5);
			$removeButton->setZ(0.2);
			$removeButton->setSize(3, 3);
			$removeButton->setTextSize(1);
			$removeButton->setText('');
			$removeButton->setTextColor('a00');

			$confirmFrame = $this->buildConfirmFrame($maniaLink, $posY, $config["id"], true);
			$removeButton->addToggleFeature($confirmFrame);
			$description = 'Remove Config: ' . $config["name"];
			$removeButton->addTooltipLabelFeature($descriptionLabel, $description);

			// Load config button
			if (!$this->MatchManagerCore->getMatchStatus()) {
				$loadLabel = new Label_Button();
				$configFrame->addChild($loadLabel);
				$loadLabel->setX($width / 2 - 9);
				$loadLabel->setZ(0.2);
				$loadLabel->setSize(3, 3);
				$loadLabel->setTextSize(1);
				$loadLabel->setText('');
				$loadLabel->setTextColor('0f0');

				$confirmFrame = $this->buildConfirmFrame($maniaLink, $posY, $config["id"]);
				$loadLabel->addToggleFeature($confirmFrame);
				$description = 'Load Config: ' . $config["name"];
				$loadLabel->addTooltipLabelFeature($descriptionLabel, $description);
			}
			$configFrame->setY($posY);

			$posY -= 4;
			$index++;
		}

		// Save config button
		$saveConfigButton = $this->maniaControl->getManialinkManager()->getElementBuilder()->buildRoundTextButton(
			'Save current config',
			35,
			5,
			self::ML_ACTION_SAVE_CONFIG_PAGE
		);
		$frame->addChild($saveConfigButton);
		$saveConfigButton->setPosition(-$width / 2 + 110, -$height / 2 + 6);

		// Render and display xml
		$this->maniaControl->getManialinkManager()->displayWidget($maniaLink, $player, self::ML_ID);
	}

	/**
	 * Shows a ManiaLink list with the local records.
	 *
	 * @api
	 * @param array  $chat
	 * @param Player $player
	 */
	public function showSaveConfigUI(Player $player) {
		$width  = $this->maniaControl->getManialinkManager()->getStyleManager()->getListWidgetsWidth();
		$height = $this->maniaControl->getManialinkManager()->getStyleManager()->getListWidgetsHeight();

		// create manialink
		$maniaLink = new ManiaLink(ManialinkManager::MAIN_MLID);
		$script    = $maniaLink->getScript();
		$paging    = new Paging();
		$script->addFeature($paging);

		// Main frame
		$frame = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultListFrame($script, $paging);
		$maniaLink->addChild($frame);

		// Start offsets
		$posX = -$width / 2;
		$posY = $height / 2 -12;

		// Predefine Description Label
		$descriptionLabel = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultDescriptionLabel();
		$frame->addChild($descriptionLabel);

		// Headline
		$headFrame = new Frame();
		$frame->addChild($headFrame);
		$headFrame->setY($posY + 7);

		$labelLine = new LabelLine($headFrame);
		$labelLine->addLabelEntryText('Plugin Name', $posX + 5);
		$labelLine->addLabelEntryText('Save', $width / 2 - 20);
		$labelLine->setTextSize(2);
		$labelLine->setPrefix('$o');
		$labelLine->render();

		$index      = 0;
		$lineHeight = 5;
		$pageFrame  = null;

		// get data
		$plugins = $this->maniaControl->getSettingManager()->getSettingClasses();

		foreach ($plugins as $class) {
			if (strpos($class, "MatchManagerSuite\\") !== 0) {
				continue;
			}

			if ($index % 12 === 0) {
				$pageFrame = new Frame();
				$frame->addChild($pageFrame);
				$posY = $height / 2 - 12;
				$paging->addPageControl($pageFrame);
			}
			$settingFrame = new Frame();
			$pageFrame->addChild($settingFrame);
			$settingFrame->setY($posY);

			$nameLabel = new Label_Text();
			$settingFrame->addChild($nameLabel);
			$nameLabel->setHorizontalAlign($nameLabel::LEFT);
			$nameLabel->setX($width * -0.46);
			$nameLabel->setSize($width * 0.6, $lineHeight);
			$nameLabel->setStyle($nameLabel::STYLE_TextCardSmall);
			$nameLabel->setTextSize(2);
			$nameLabel->setText($class);
			$nameLabel->setTextColor('fff');

			$quad = new Quad();
			$quad->setPosition($width * 0.4, 0, -0.01);
			$quad->setSize(4, 4);
			$checkBox = new CheckBox(self::ML_ACTION_SAVE_CONFIG . '.' . $class, true, $quad);
			$settingFrame->addChild($checkBox);

			$posY -= $lineHeight;

			$index++;
		}

		$backButton = new Label_Button();
		$frame->addChild($backButton);
		$backButton->setStyle($backButton::STYLE_CardMain_Quit);
		$backButton->setHorizontalAlign($backButton::LEFT);
		$backButton->setScale(0.5);
		$backButton->setText('Back');
		$backButton->setPosition(-$width / 2 + 5, -$height / 2 + 5);
		$backButton->setAction(self::ML_ACTION_LOAD_CONFIG_PAGE);

		//Search for Map-Name
		$mapNameButton = $this->maniaControl->getManialinkManager()->getElementBuilder()->buildRoundTextButton(
			'Save with the name:',
			35,
			5,
			self::ML_ACTION_SAVE_CONFIG
		);
		$frame->addChild($mapNameButton);
		$mapNameButton->setPosition(-$width / 2 + 60, -$height / 2 + 5);

		$entry = new Entry();
		$frame->addChild($entry);
		$entry->setStyle(Label_Text::STYLE_TextValueSmall);
		$entry->setHorizontalAlign($entry::LEFT);
		$entry->setPosition(-$width / 2 + 80, -$height / 2 + 5);
		$entry->setTextSize(1);
		$entry->setSize($width * 0.25, 4);
		$entry->setName(self::ML_NAME_CONFIGNAME);

		// Render and display xml
		$this->maniaControl->getManialinkManager()->displayWidget($maniaLink, $player, self::ML_ID);
	}


	/**
	 * Builds the confirmation frame
	 *
	 * @param ManiaLink $maniaLink
	 * @param float     $posY
	 * @param bool      $mapUid
	 * @param bool      $remove
	 * @return Frame
	 */
	public function buildConfirmFrame(Manialink $maniaLink, $posY, $configId, $remove = false) {
		$width        = $this->maniaControl->getManialinkManager()->getStyleManager()->getListWidgetsWidth();
		$quadStyle    = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultMainWindowStyle();
		$quadSubstyle = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultMainWindowSubStyle();

		$confirmFrame = new Frame();
		$maniaLink->addChild($confirmFrame);
		$confirmFrame->setPosition($width / 2 + 6, $posY);
		$confirmFrame->setVisible(false);
		$confirmFrame->setZ(ManialinkManager::MAIN_MANIALINK_Z_VALUE);

		$quad = new Quad();
		$confirmFrame->addChild($quad);
		$quad->setStyles($quadStyle, $quadSubstyle);
		$quad->setSize(13, 4);
		$quad->setZ(-0.5);

		$quad = new Quad_BgsPlayerCard();
		$confirmFrame->addChild($quad);
		$quad->setSubStyle($quad::SUBSTYLE_BgCardSystem);
		$quad->setSize(12, 3.5);
		$quad->setZ(-0.3);

		$label = new Label_Button();
		$confirmFrame->addChild($label);
		$label->setText('Sure?');
		$label->setTextSize(1);
		$label->setScale(0.90);
		$label->setX(-1.3);

		$buttLabel = new Label_Button();
		$confirmFrame->addChild($buttLabel);
		$buttLabel->setPosition(4, 0, 0.2);
		$buttLabel->setSize(3, 3);

		if ($remove) {
			$buttLabel->setTextSize(1);
			$buttLabel->setTextColor('a00');
			$buttLabel->setText('');
			$quad->setAction(self::ML_ACTION_REMOVE_CONFIG . '.' . $configId);
		} else {
			$buttLabel->setTextSize(1);
			$buttLabel->setTextColor('0f0');
			$buttLabel->setText('');
			$quad->setAction(self::ML_ACTION_LOAD_CONFIG . '.' . $configId);
		}

		return $confirmFrame;
	}
}