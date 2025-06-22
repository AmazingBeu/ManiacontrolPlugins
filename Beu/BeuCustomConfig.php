<?php
namespace Beu;

use ManiaControl\ManiaControl;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Callbacks\CallbackListener;

use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;

use FML\Controls\Quads\Quad_BgsPlayerCard;
use ManiaControl\Admin\AuthenticationManager;
use ManiaControl\Configurator\Configurator;
use ManiaControl\Manialinks\StyleManager;
use ManiaControl\Maps\MapManager;
use ManiaControl\Maps\MapQueue;
use ManiaControl\Plugins\PluginMenu;
use ManiaControl\Server\UsageReporter;
use ManiaControl\Update\UpdateManager;
use Maniaplanet\DedicatedServer\Structures\VoteRatio;

/**
 * BeuCustomConfig
 *
 * @author	Beu
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class BeuCustomConfig implements CallbackListener, Plugin {
	/*
	* Constants
	*/
	const PLUGIN_ID			= 193;
	const PLUGIN_VERSION	= 1.3;
	const PLUGIN_NAME		= 'BeuCustomConfig';
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
		return "Default config for event servers";
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		$this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');

		$this->changeManiacontrolSettings();
	}

	private function changeManiacontrolSettings() {
		$settingstochange = [
			AuthenticationManager::class => [
				MapQueue::SETTING_PERMISSION_ADD_TO_QUEUE => AuthenticationManager::AUTH_NAME_ADMIN
			],
			UpdateManager::class => [
				UpdateManager::SETTING_ENABLE_UPDATECHECK => false
			],
			UsageReporter::class => [
				UsageReporter::SETTING_REPORT_USAGE => false
			],
			MapManager::class => [
				MapManager::SETTING_AUTOSAVE_MAPLIST => false,
				MapManager::SETTING_ENABLE_MX => false
			],
			PlayerManager::class => [
				PlayerManager::SETTING_VERSION_JOIN_MESSAGE => false
			],
			PluginMenu::class => [
				PluginMenu::SETTING_CHECK_UPDATE_WHEN_OPENING => false
			],
			Configurator::class => [
				Configurator::SETTING_MENU_HEIGHT => 120,
				Configurator::SETTING_MENU_WIDTH => 220,
			],
			StyleManager::class => [
				StyleManager::SETTING_LIST_WIDGETS_HEIGHT => 120,
				StyleManager::SETTING_LIST_WIDGETS_WIDTH => 220,
				StyleManager::SETTING_LABEL_DEFAULT_STYLE => "TextClock",
				StyleManager::SETTING_QUAD_DEFAULT_STYLE => Quad_BgsPlayerCard::STYLE,
				StyleManager::SETTING_QUAD_DEFAULT_SUBSTYLE => Quad_BgsPlayerCard::SUBSTYLE_BgPlayerName
			]
		];

		foreach ($settingstochange as $classname => $settings) {
			foreach ($settings as $settingname => $value) {
				$setting = $this->maniaControl->getSettingManager()->getSettingObject($classname, $settingname);
				$setting->value = $value;
				$this->maniaControl->getSettingManager()->saveSetting($setting);
			}
		}

		// Disable all votes
		$this->maniaControl->getClient()->setCallVoteRatios([ 
			new VoteRatio(VoteRatio::COMMAND_DEFAULT, -1.),
			new VoteRatio(VoteRatio::COMMAND_SCRIPT_SETTINGS, -1.),
			new VoteRatio(VoteRatio::COMMAND_JUMP_MAP, -1.),
			new VoteRatio(VoteRatio::COMMAND_SET_NEXT_MAP, -1.),
			new VoteRatio(VoteRatio::COMMAND_KICK, -1.),
			new VoteRatio(VoteRatio::COMMAND_RESTART_MAP, -1.),
			new VoteRatio(VoteRatio::COMMAND_TEAM_BALANCE, -1.),
			new VoteRatio(VoteRatio::COMMAND_NEXT_MAP, -1.),
			new VoteRatio(VoteRatio::COMMAND_BAN, -1.)
		]);
	}

	public function updateSettings(?Setting $setting = null) {
		if ($setting !== null && $setting->belongsToClass($this)) {
			$this->changeManiacontrolSettings();
		}
	} 
	
	/**
	 * Unload the plugin and its Resources
	 */
	public function unload() {
	}
}
