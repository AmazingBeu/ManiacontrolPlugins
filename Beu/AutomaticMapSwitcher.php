<?php

namespace Beu;

use ManiaControl\ManiaControl;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\TimerListener;
use ManiaControl\Logger;
use ManiaControl\Plugins\Plugin;

/**
 * GSheetRecords
 *
 * @author		Beu
 * @license		http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class AutomaticMapSwitcher implements TimerListener, CallbackListener, Plugin {
	/*
	 * Constants
	 */
	const PLUGIN_ID											= 208;
	const PLUGIN_VERSION									= 1;
	const PLUGIN_NAME										= 'AutomaticMapSwitcher';
	const PLUGIN_AUTHOR										= 'Beu';

    const SETTING_SCHEDULE                                  = 'Map schedule';
    const SETTING_DEBOUCEMAPCHANGEDELAY                     = 'Debounce map change delay';

	/*
	 * Private properties
	 */
	private ManiaControl $maniaControl;
    private int $debounceMapChangeTime = 0;

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
		return 'Automatic change map based on timestamp';
	}

	/**
	 * @param \ManiaControl\ManiaControl $maniaControl
	 * @return bool
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_SCHEDULE, '', 'format: "timestamp:mapuid,timestamp:mapuid"');
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_DEBOUCEMAPCHANGEDELAY, 30, 'delay between 2 map change requests', 110);

        $this->maniaControl->getTimerManager()->registerTimerListening($this, 'handle1Second', 1000);
    }

	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {
	}

    /**
     * handle1Second
     * @return void 
     */
    public function handle1Second() {
        $now = time();
        if ($this->debounceMapChangeTime > $now) return;

        $schedule = trim($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_SCHEDULE));
        if ($schedule === '') return;
        
        
        $matchingTimestamp = -1;
        $matchingMapUid = '';
        foreach (explode(',', $schedule) as $pair) {
            list($timestampText, $mapuid) = explode(':', $pair);

            if (!is_numeric($timestampText)) {
                $this->maniaControl->getChat()->sendErrorToAdmins('invalid timestamp in pair: '. $pair);
                Logger::logWarning('invalid timestamp in pair: '. $pair);
                continue;
            }
            $timestamp = intval($timestampText);

            if ($this->maniaControl->getMapManager()->getMapByUid($mapuid) === null) {
                $this->maniaControl->getChat()->sendErrorToAdmins('map not loaded in pair: '. $pair);
                Logger::logWarning('map not loaded in pair: '. $pair);
                continue;
            }

            if ($matchingTimestamp < $timestamp && $now > $timestamp) {
                $matchingTimestamp = $timestamp;
                $matchingMapUid = $mapuid;
            }
            
        }

        if ($matchingMapUid !== '' && $matchingMapUid !== $this->maniaControl->getMapManager()->getCurrentMap()->uid) {
            $this->debounceMapChangeTime = time() + $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_DEBOUCEMAPCHANGEDELAY);

            $nextmap = $this->maniaControl->getMapManager()->getMapByUid($matchingMapUid);

            $this->maniaControl->getChat()->sendSuccess('Automatic switching to map: $z'. $nextmap->name);
            Logger::logWarning('Automatic switching to map: '. $matchingMapUid);
            $this->maniaControl->getMapManager()->getMapActions()->skipToMapByUid($matchingMapUid);
        }
    }
}