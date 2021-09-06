<?php

namespace MatchManagerSuite;

use FML\Controls\Frame;
use FML\Controls\Label;
use FML\Controls\Quad;
use FML\Controls\Quads\Quad_Bgs1InRace;
use FML\ManiaLink;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\CallbackManager;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Callbacks\Structures\Common\BasePlayerTimeStructure;
use ManiaControl\Callbacks\Structures\TrackMania\OnScoresStructure;
use ManiaControl\Callbacks\Structures\TrackMania\OnWayPointEventStructure;
use ManiaControl\Logger;
use ManiaControl\ManiaControl;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;
use ManiaControl\Utils\Formatter;

if (! class_exists('MatchManagerSuite\MatchManagerCore')) {
	$this->maniaControl->getChat()->sendErrorToAdmins('MatchManager Core is needed to use MatchManager Widget plugin. Install it and restart Maniacontrol');
	Logger::logError('MatchManager Core is needed to use MatchManager Widget plugin. Install it and restart Maniacontrol');
	return false;
}
use MatchManagerSuite\MatchManagerCore;


/**
 * MatchManager Widgets
 *
 * @author		Beu (based on MatchManagerWidget by jonthekiller)
 * @license		http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class MatchManagerWidget implements ManialinkPageAnswerListener, CallbackListener, Plugin {
	/*
	 * Constants
	 */
	const PLUGIN_ID											= 153;
	const PLUGIN_VERSION									= 1.4;
	const PLUGIN_NAME										= 'MatchManager Widget';
	const PLUGIN_AUTHOR										= 'Beu';

	// MatchManagerWidget Properties
	const MATCHMANAGERWIDGET_COMPATIBLE_GM					= ["Cup", "Teams", "Rounds"];
	const MATCHMANAGERCORE_PLUGIN							= 'MatchManagerSuite\MatchManagerCore';

	const MLID_MATCHMANAGERWIDGET_LIVE_WIDGETBACKGROUND		= 'MatchManagerWidget.Background';
	const MLID_MATCHMANAGERWIDGET_LIVE_WIDGETDATA			= 'MatchManagerWidget.Data';

	const SETTING_MATCHMANAGERWIDGET_LIVE_POSX				= 'MatchManagerWidget-Position: X';
	const SETTING_MATCHMANAGERWIDGET_LIVE_POSY				= 'MatchManagerWidget-Position: Y';
	const SETTING_MATCHMANAGERWIDGET_LIVE_LINESCOUNT		= 'Widget Displayed Lines Count';
	const SETTING_MATCHMANAGERWIDGET_LIVE_WIDTH				= 'MatchManagerWidget-Size: Width';
	const SETTING_MATCHMANAGERWIDGET_SHOWPLAYERS			= 'Show for Players';
	const SETTING_MATCHMANAGERWIDGET_SHOWSPECTATORS			= 'Show for Spectators';

	const MATCH_ACTION_SPEC									= 'Spec.Action';


	/*
	 * Private properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl 			= null;
	private $gmbase					= "";
	private $manialinkData			= "";
	private $manialinkBackground	= "";
	private $playerswithML			= [];
	private $specswithML			= [];

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
		return 'Display Match live widget (for Rounds/Teams/Cup mode)';
	}

	/**
	 * @param \ManiaControl\ManiaControl $maniaControl
	 * @return bool
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;
		$this->MatchManagerCore = $this->maniaControl->getPluginManager()->getPlugin(self::MATCHMANAGERCORE_PLUGIN);

		if ($this->MatchManagerCore == Null) {
			throw new \Exception('MatchManager Core is needed to use MatchManager Widget plugin');
		}

		// Callbacks
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerConnect');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(MatchManagerCore::CB_MATCHMANAGER_STARTMATCH, $this, 'InitMatch');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(MatchManagerCore::CB_MATCHMANAGER_ENDROUND, $this, 'MatchManager_EndRound');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(MatchManagerCore::CB_MATCHMANAGER_ENDMATCH, $this, 'ClearMatch');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(MatchManagerCore::CB_MATCHMANAGER_STOPMATCH, $this, 'ClearMatch');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(CallbackManager::CB_MP_PLAYERMANIALINKPAGEANSWER, $this, 'handleSpec');

		// Settings
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCHMANAGERWIDGET_SHOWPLAYERS, true, "Display widget for players");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCHMANAGERWIDGET_SHOWSPECTATORS, true, "Display widget for spectators");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCHMANAGERWIDGET_LIVE_POSX, -139, "Position of the widget (on X axis)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCHMANAGERWIDGET_LIVE_POSY, -10, "Position of the widget (on Y axis)");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCHMANAGERWIDGET_LIVE_LINESCOUNT, 4, "Number of players to display");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCHMANAGERWIDGET_LIVE_WIDTH, 42, "Width of the widget");

		$this->generateMatchLiveWidgetBackground();
		return true;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {
		$this->closeWidgets();
	}

	/**
	 * Update Widgets on Setting Changes
	 *
	 * @param Setting $setting
	 */
	public function updateSettings(Setting $setting) {
		if ($setting->belongsToClass($this)) {
			$this->closeWidgets();
			if (strlen($this->gmbase) > 0) {
				$this->generateMatchLiveWidgetBackground();
				if ($this->gmbase == "Teams") {
					$currentscore = $this->MatchManagerCore->getCurrentTeamsScore();
				} else {
					$currentscore = $this->MatchManagerCore->getCurrentScore();
				}
				if (count($currentscore) > 0) {
					$this->generateMatchLiveWidgetData($currentscore);
				}
				$this->displayManialinks(false);
			}
		}
	}

	/**
	 * Init variable and display widget background
	 * 
	 * @param string $matchid
	 * @param array $settings
	 */
	public function InitMatch(string $matchid, array $settings) {
		Logger::Log("InitMatch");
		$this->gmbase = $settings['currentgmbase'];
		$this->displayManialinks(false);
	}

	/**
	 * Clear variables and hide widget
	 */
	public function ClearMatch() {
		Logger::Log("ClearMatch");
		$this->gmbase = "";
		$this->manialinkData = "";
		$this->closeWidgets();
	}

	/**
	 * Close a Widget
	 *
	 * @param string $widgetId
	 */
	public function closeWidgets($login = null) {
		$this->maniaControl->getManialinkManager()->hideManialink(self::MLID_MATCHMANAGERWIDGET_LIVE_WIDGETDATA, $login);
		$this->maniaControl->getManialinkManager()->hideManialink(self::MLID_MATCHMANAGERWIDGET_LIVE_WIDGETBACKGROUND, $login);
	}

	/**
	 * Endround Callback of MatchManagerCore plugin
	 * 
	 * @param string $matchid
	 * @param array $currentscore
	 * @param array $currentteamsscore
	 */
	public function MatchManager_EndRound(string $matchid, array $currentscore, array $currentteamsscore) {
		if ($this->gmbase == "Teams") {
			$currentscore = $currentteamsscore ;
		}
		if (count($currentscore) > 0) {
			$this->generateMatchLiveWidgetData($currentscore);
			$this->displayManialinks(true);
		}
	}

	/**
	 * handle when a spectator click on a player name on the widget
	 * 
	 * @param array $callback
	 */
	public function handleSpec(array $callback) {
		$actionId    = $callback[1][2];
		$actionArray = explode('.', $actionId, 3);
		if (count($actionArray) < 2) {
			return;
		}
		$action = $actionArray[0] . '.' . $actionArray[1];

		if (count($actionArray) > 2) {

			switch ($action) {
				case self::MATCH_ACTION_SPEC:
					$adminLogin  = $callback[1][1];
					$targetLogin = $actionArray[2];
					foreach ($this->maniaControl->getPlayerManager()->getPlayers() as $players) {
						if ($targetLogin == $players->login && !$players->isSpectator && !$players->isTemporarySpectator && !$players->isFakePlayer() && $players->isConnected) {

							$player = $this->maniaControl->getPlayerManager()->getPlayer($adminLogin);
							if ($player->isSpectator) {
								$this->maniaControl->getClient()->forceSpectatorTarget($adminLogin, $targetLogin, -1);
							}
						}
					}
			}
		}
	}

	/**
	 * Handle PlayerConnect callback
	 *
	 * @param Player $player
	 */
	public function handlePlayerConnect(Player $player) {
		Logger::Log("handlePlayerConnect");

		if (strlen($this->gmbase) > 0) {
			$this->displayManialinks($player->login);
		}
	}

	/**
	 * Display Widget Manialinks 
	 * 
	 * @param bool/string $diff
	 */
	public function displayManialinks($diff) {
		if (in_array($this->gmbase, self::MATCHMANAGERWIDGET_COMPATIBLE_GM)) {
			// $diff can be boolean or string
			if (!is_bool($diff)) {
				$login = $diff;
				$diff = false;
			}

			if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHMANAGERWIDGET_SHOWPLAYERS) || $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHMANAGERWIDGET_SHOWSPECTATORS)) {
				if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHMANAGERWIDGET_SHOWPLAYERS) && $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHMANAGERWIDGET_SHOWSPECTATORS)) {
					if (!$diff){
						$this->maniaControl->getManialinkManager()->sendManialink($this->manialinkBackground);
					}
					$this->maniaControl->getManialinkManager()->sendManialink($this->manialinkData);
				} else {
					$players = [];
					$specs = [];
					$diffspecs = [];
					$diffplayers = [];

					if (isset($login)) {
						$player = $this->maniaControl->getPlayerManager()->getPlayer($login);
						if ($player->isSpectator) {
							$specs[] = $player->login;
						} else {
							$players[] = $player->login;
						}
					} else {
						foreach ($this->maniaControl->getPlayerManager()->getPlayers(true) as $player) {
							$players[] = $player->login;
						}
						foreach ($this->maniaControl->getPlayerManager()->getSpectators() as $spec) {
							$specs[] = $spec->login;
						}
					}

					// In diff mode, get the list of those who need to have the BG, and those who need to hide the ML
					if ($diff) {
						$diffspecs = array_diff($specs, $this->specswithML);
						$diffplayers = array_diff($players, $this->playerswithML);
					}

					if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHMANAGERWIDGET_SHOWPLAYERS)) {
						// hiding the ML from spectators who still have it
						if (count($diffspecs) > 0) {
							$this->closeWidgets($diffspecs);
							$specs = [];
						}

						if (count($players) > 0) {
							if (!$diff){
								// if no diff, display the BG for all
								$this->maniaControl->getManialinkManager()->sendManialink($this->manialinkBackground,$players);
							} elseif (count($diffplayers) > 0) {
								// if diff, display the BG for those who don't have it
								$this->maniaControl->getManialinkManager()->sendManialink($this->manialinkBackground,$diffplayers);
							}
							// display data
							$this->maniaControl->getManialinkManager()->sendManialink($this->manialinkData,$players);
						}
					} elseif ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHMANAGERWIDGET_SHOWSPECTATORS)) {
						// hiding the ML from players who still have it
						if (count($diffplayers) > 0) {
							$this->closeWidgets($diffplayers);
							$players = [];
						}

						if (count($specs) > 0) {
							if (!$diff){
								// if no diff, display the BG for all
								$this->maniaControl->getManialinkManager()->sendManialink($this->manialinkBackground,$specs);
							} elseif (count($diffspecs) > 0) {
								// if diff, display the BG for those who don't have it
								$this->maniaControl->getManialinkManager()->sendManialink($this->manialinkBackground,$diffspecs);
							}
							// display data
							$this->maniaControl->getManialinkManager()->sendManialink($this->manialinkData,$specs);
						}
					}

					// Store in memory playars/specs with ML (usefull for diff mode)
					if (!isset($login)) {
						$this->playerswithML = $players;
						$this->specswithML = $specs;
					}
				}
			} else {
				$this->closeWidgets();
			}
		}
		
	}


	/**
	 * Generate the manilink of the background of the widget
	 */
	public function generateMatchLiveWidgetBackground() {
		Logger::Log("generateMatchLiveWidgetBackground");

		$posX       = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHMANAGERWIDGET_LIVE_POSX);
		$posY       = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHMANAGERWIDGET_LIVE_POSY);
		$width      = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHMANAGERWIDGET_LIVE_WIDTH);
		$lines      = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHMANAGERWIDGET_LIVE_LINESCOUNT);
		$height       = 7 + $lines * 4;
		$labelStyle   = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultLabelStyle();
		$quadSubstyle = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadSubstyle();
		$quadStyle    = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadStyle();

		// mainframe
		$frame = new Frame();
		$frame->setPosition($posX, $posY);

		// Background Quad
		$backgroundQuad = new Quad();
		$frame->addChild($backgroundQuad);
		$backgroundQuad->setVerticalAlign($backgroundQuad::TOP);
		$backgroundQuad->setSize($width, $height);
		$backgroundQuad->setStyles($quadStyle, $quadSubstyle);

		$titleLabel = new Label();
		$frame->addChild($titleLabel);
		$titleLabel->setPosition(0, -3.6);
		$titleLabel->setWidth($width);
		$titleLabel->setStyle($labelStyle);
		$titleLabel->setTextSize(2);
		$titleLabel->setText('$<$z$i$fc3🏆$> Match Live');
		$titleLabel->setTranslate(true);
		$titleLabel->setZ(1);

		$this->manialinkBackground = new ManiaLink(self::MLID_MATCHMANAGERWIDGET_LIVE_WIDGETBACKGROUND);
		$this->manialinkBackground->addChild($frame);

	}

	/**
	 * Generate the manilink of the data of the widget
	 * 
	 * @param array $currentscore
	 */
	public function generateMatchLiveWidgetData(array $currentscore) {
		Logger::Log("generateMatchLiveWidgetData");

		if ($this->gmbase == "Cup") {
			$pointlimit = $this->MatchManagerCore->getMatchPointsLimit();
		}

		// Sort if possible
		if (count($currentscore) > 1) {
			usort($currentscore, function($a, $b) {
				return $b[2] - $a[2];
			});
		}

		$lines      = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHMANAGERWIDGET_LIVE_LINESCOUNT);
		$posX       = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHMANAGERWIDGET_LIVE_POSX);
		$posY       = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHMANAGERWIDGET_LIVE_POSY);
		$width      = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHMANAGERWIDGET_LIVE_WIDTH);

		$this->manialinkData = new ManiaLink(self::MLID_MATCHMANAGERWIDGET_LIVE_WIDGETDATA);
		$listFrame = new Frame();
		$this->manialinkData->addChild($listFrame);
		$listFrame->setPosition($posX, $posY);
		$listFrame->setZ(1);

		$rank = 1;

		foreach ($currentscore as $score) {
			if ($rank > $lines) {
				break;
			}

			$points = $score[2];
			if (isset($pointlimit)) {
				if ($score[2] > $pointlimit) {
					$points = '$0f0Winner';
				} elseif ($score[2] == $pointlimit) {
					$points = '$f00Finalist';
				}
			}

			$y = -6 - $rank  * 4;

			$recordFrame = new Frame();
			$listFrame->addChild($recordFrame);
			$recordFrame->setPosition(0, $y + 2);
			$recordFrame->setZ(1);

			//Rank
			$rankLabel = new Label();
			$recordFrame->addChild($rankLabel);
			$rankLabel->setHorizontalAlign($rankLabel::LEFT);
			$rankLabel->setX(($width - 1) * -0.47);
			$rankLabel->setSize($width * 0.06, 4);
			$rankLabel->setTextSize(1);
			$rankLabel->setTextPrefix('$o');
			$rankLabel->setText($rank);
			$rankLabel->setTextEmboss(true);
			$rankLabel->setZ(1);

			//Name
			$nameLabel = new Label();
			$recordFrame->addChild($nameLabel);
			$nameLabel->setHorizontalAlign($nameLabel::LEFT);
			$nameLabel->setX(($width - 1) * -0.4);
			$nameLabel->setSize($width * 0.6, 4);
			$nameLabel->setTextSize(1);
			$nameLabel->setTextEmboss(true);
			$nameLabel->setZ(1);

			//Points
			$pointsLabel = new Label();
			$recordFrame->addChild($pointsLabel);
			$pointsLabel->setHorizontalAlign($pointsLabel::RIGHT);
			$pointsLabel->setX(($width - 1) * 0.47);
			$pointsLabel->setSize($width * 0.25, 4);
			$pointsLabel->setTextSize(1);
			$pointsLabel->setText('$z' . $points);
			$pointsLabel->setTextEmboss(true);
			$pointsLabel->setZ(1);

			//Background with Spec action
			$quad = new Quad();
			$recordFrame->addChild($quad);
			$quad->setStyles(Quad_Bgs1InRace::STYLE, Quad_Bgs1InRace::SUBSTYLE_BgCardList);
			$quad->setSize($width-2, 4);
			$quad->setZ(1);

			if ($this->gmbase == "Teams") {
				$team = $this->maniaControl->getClient()->getTeamInfo($score[1] + 1);
				$nameLabel->setText('$<$' . $team->rGB . $team->name  . '$>');
			} else {
				$player = $this->maniaControl->getPlayerManager()->getPlayer($score[1]);
				$nameLabel->setText($player->nickname);
				$quad->setAction(self::MATCH_ACTION_SPEC . '.' . $score[1]);
			}
			$rank++;
		}
	}
}
