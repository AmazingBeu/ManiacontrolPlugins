<?php
namespace Beu;

use Exception;
use ManiaControl\ManiaControl;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Logger;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;
use Maniaplanet\DedicatedServer\InvalidArgumentException;

/**
 * OpenplanetDetector
 *
 * @author	Beu
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class OpenplanetDetector implements ManialinkPageAnswerListener, CallbackListener, Plugin {
	/*
	* Constants
	*/
	const PLUGIN_ID			= 203;
	const PLUGIN_VERSION	= 1.0;
	const PLUGIN_NAME		= 'Openplanet Detector';
	const PLUGIN_AUTHOR		= 'Beu';

    const SETTING_SIGNATURE_BLACKLIST   = 'Openplanet Signature blacklist';
    const SETTING_SIGNATURE_WHITELIST   = 'Openplanet Signature whitelist';
    const SETTING_ACTION                = 'Action for player';

    const ACTION_KICK                   = 'kick';
    const ACTION_FORCE_AS_SPEC          = 'force as spec';

	/*
	 * Private properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl	= null;

    private $manialink = null;

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
		return "Detect Openplanet and allow to force as spec and kick players";
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;
        $this->manialink = $this->getManialink();

        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_SIGNATURE_BLACKLIST, "DEVMODE", "Comma separated signature banned (Only used is whitelist is empty)");
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_SIGNATURE_WHITELIST, "", "Comma separated signature allowed.");
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_ACTION, [self::ACTION_FORCE_AS_SPEC, self::ACTION_KICK], "");

        $this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerConnect');
        $this->maniaControl->getManialinkManager()->registerManialinkPageAnswerRegexListener('/^Maniacontrol.OpenplanetDetector:/', $this, 'handleOpenplanetSignature');

        $this->maniaControl->getManialinkManager()->sendManialink($this->manialink);
	}

    /**
     * handleOpenplanetSignature
     * 
     * @param array $callback 
     * @param Player $player 
     * @return void 
     */
    public function handleOpenplanetSignature(array $callback, Player $player) {
        $whitelist = array_filter(explode(',', $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_SIGNATURE_WHITELIST)));
        $signature = explode(':', $callback[1][2])[1];
        if ($signature === "") $signature = "REGULAR";

        if (count($whitelist) > 0) {
            if (!in_array($signature, $whitelist)) {
                $this->triggerAction($player, $signature);
            }
        } else {
            $blacklist = array_filter(explode(',', $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_SIGNATURE_BLACKLIST)));
            
            if (in_array($signature, $blacklist)) {
                $this->triggerAction($player, $signature);
            }
        }
    }

    /**
     * triggerAction
     * 
     * @param Player $player 
     * @param string $signature 
     * @return void 
     * @throws InvalidArgumentException 
     */
    private function triggerAction(Player $player, string $signature) {
        $this->maniaControl->getChat()->sendInformationToAdmins("Player ". $player->nickname ." has the wrong Openplanet Signature: " . $signature);
        Logger::log("Player ". $player->nickname ." has the wrong Openplanet Signature: " . $signature);

        switch ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_ACTION)) {
            case self::ACTION_FORCE_AS_SPEC:
                $this->maniaControl->getClient()->forceSpectator($player->login, 1);
                $this->maniaControl->getChat()->sendInformation("Your Openplanet signature is not allowed. Change it and try to re-join the server", $player->login);
                break;
            case self::ACTION_KICK:
                $this->maniaControl->getClient()->kick($player->login, "Your Openplanet signature is not allowed. Change it and try to re-join the server");
                break;
        }
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
	 * Unload the plugin and its Resources
	 */
	public function unload() {}

    /**
     * getManialink
     * 
     * @return string 
     */
    private function getManialink() {
        return <<<'EOD'
<?xml version="1.0" encoding="utf-8" standalone="yes" ?>
<manialink version="3" id="Maniacontrol.OpenplanetDetector" name="Maniacontrol.OpenplanetDetector">
<script><!--
#Include "TextLib" as TL

Boolean GetOpenplanet() {
    return (TL::RegexFind("^Openplanet ", System.ExtraTool_Info, "").count > 0);
}
Text GetOpenplanetSignature() {
    if (GetOpenplanet()) {
        declare Text[] SignatureMode = TL::RegexMatch(" \\[([A-Z]*)\\]$", System.ExtraTool_Info, "");
        if (SignatureMode.count >= 2) {
            return SignatureMode[1];
        }
    } 

    return "";
}

main () {
    log("Init Maniacontrol.OpenplanetDetector");
    wait(InputPlayer != Null);

    declare Text Last_ExtraTool_Info;

    while (True) {
        yield;
        if (Last_ExtraTool_Info != System.ExtraTool_Info) {
            Last_ExtraTool_Info = System.ExtraTool_Info;

            if (GetOpenplanet()) {
                TriggerPageAction("Maniacontrol.OpenplanetDetector:" ^ GetOpenplanetSignature());
            }
        }
    }
}
--></script>
</manialink>
EOD;
    }
}
