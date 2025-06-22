<?php

namespace MatchManagerSuite;

use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Logger;
use ManiaControl\ManiaControl;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Plugins\PluginManager;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;
use ManiaControl\Callbacks\TimerListener;

if (!class_exists('MatchManagerSuite\MatchManagerCore')) {
	$this->maniaControl->getChat()->sendErrorToAdmins('MatchManager Core is required to use MatchManagerAutomaticLauncher plugin. Install it and restart Maniacontrol');
	Logger::logError('MatchManager Core is required to use MatchManagerAutomaticLauncher plugin. Install it and restart Maniacontrol');
	return false;
}

/**
 * MatchManager Automatic Launcher
 *
 * @author		Beu
 * @license		http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class MatchManagerAutomaticLauncher implements CallbackListener, TimerListener, Plugin {
	/*
	 * Constants
	 */
	const PLUGIN_ID											= 172;
	const PLUGIN_VERSION									= 1.1;
	const PLUGIN_NAME										= 'MatchManager Automatic Launcher';
	const PLUGIN_AUTHOR										= 'Beu';

	// MatchManagerWidget Properties
	const MATCHMANAGERCORE_PLUGIN							= 'MatchManagerSuite\MatchManagerCore';

	const SETTING_TIMESTAMPS_START_MATCHES					= 'Timestamps of the start of the matches:';

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
		return 'Automatic launch matches with timestamps';
	}

	/**
	 * @param \ManiaControl\ManiaControl $maniaControl
	 * @return bool
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		// Init plugin
		$this->maniaControl = $maniaControl;
		if ($this->maniaControl->getPluginManager()->getSavedPluginStatus(self::MATCHMANAGERCORE_PLUGIN)) {
			// plugin are loaded in alphabetic order, just wait 1 sec before trying to load MatchManager Core
			$this->maniaControl->getTimerManager()->registerOneTimeListening($this, function () {
				$this->MatchManagerCore = $this->maniaControl->getPluginManager()->getPlugin(self::MATCHMANAGERCORE_PLUGIN);
				if ($this->MatchManagerCore == null) {
					$this->maniaControl->getChat()->sendErrorToAdmins('MatchManager Core is needed to use ' . self::PLUGIN_NAME . ' plugin.');
					$this->maniaControl->getPluginManager()->deactivatePlugin((get_class($this)));
				} else {
					$this->createTimers();
				}
			}, 1000);
		} else {
			throw new \Exception('MatchManager Core is needed to use ' . self::PLUGIN_NAME);
		}

		$this->maniaControl->getCallbackManager()->registerCallbackListener(PluginManager::CB_PLUGIN_UNLOADED, $this, 'handlePluginUnloaded');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');

		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_TIMESTAMPS_START_MATCHES, "", "Comma separated of the start of the matches");

		return true;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {
	}

	/**
	 * Update Widgets on Setting Changes
	 *
	 * @param Setting $setting
	 */
	public function updateSettings(Setting $setting) {
		if ($setting->belongsToClass($this)) {
			$this->createTimers();
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
			$this->maniaControl->getPluginManager()->deactivatePlugin((get_class($this)));
		}
	}
	
	/**
	 * createTimers
	 *
	 * @return void
	 */
	private function createTimers() {
		$this->maniaControl->getTimerManager()->unregisterTimerListenings($this);

		$timestamps = explode(",", $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_TIMESTAMPS_START_MATCHES));
		$now = time();
		$newtimers = 0;
		foreach ($timestamps as $timestamp) {
			if ($now < $timestamp) {
				$delta = ($timestamp - $now) * 1000;
				$this->maniaControl->getTimerManager()->registerOneTimeListening($this, function () {
					$this->MatchManagerCore->MatchStart();
				}, $delta);
				$newtimers++;
			}
		}
		$this->maniaControl->getChat()->sendSuccessToAdmins($this->MatchManagerCore->getChatPrefix() . $newtimers . " matches are planned");
	}
}