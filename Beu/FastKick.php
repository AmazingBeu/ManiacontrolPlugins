<?php
namespace Beu;

use ManiaControl\Logger;
use ManiaControl\ManiaControl;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerActions;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Commands\CommandListener;
use FML\Controls\Frame;
use FML\Controls\Label;
use FML\Controls\Quad;
use FML\Controls\Quads\Quad_Icons64x64_1;
use FML\ManiaLink;

/**
 * Beu Donation Button
 *
 * @author	Beu
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class FastKick implements ManialinkPageAnswerListener, CommandListener, Plugin {
	/*
	* MARK: Constants
	*/
	const PLUGIN_ID			= 212;
	const PLUGIN_VERSION	= 1.0;
	const PLUGIN_NAME		= 'Fast Kick';
	const PLUGIN_AUTHOR		= 'Beu';

	const MANIALINK_ID		= 'FastKick::MainWindow';
	const ACTION_CLOSE		= 'FastKick::close';
	const ACTION_KICK		= 'FastKick::kick';

	/*
	 * MARK: Private properties
	 */
	private ManiaControl $maniaControl;

	/*
	 * MARK: Functions
	 */
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
		return "Quick plugin to kick player easily using //fk command by matching the nearest name";
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		$this->maniaControl->getCommandManager()->registerCommandListener(['fkick', 'fk', 'fastkick'], $this, 'handleFastKick', true);

		$this->maniaControl->getManialinkManager()->registerManialinkPageAnswerListener(self::ACTION_CLOSE, $this, 'handleClose');
		$this->maniaControl->getManialinkManager()->registerManialinkPageAnswerRegexListener('/^'. self::ACTION_KICK .'/', $this, 'handleKick');
	}

	/**
	 * handle Fast Kick command
	 * 
	 * @param array $structure 
	 * @param Player $adminPlayer 
	 * @return void 
	 */
	public function handleFastKick(array $structure, Player $adminPlayer) {
		if (!$this->maniaControl->getAuthenticationManager()->checkPermission($adminPlayer, PlayerActions::SETTING_PERMISSION_KICK_PLAYER)) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($adminPlayer);
			return;
		}

		$params = explode(' ', $structure[1][2], 3);
		if (count($params) <= 1 || $params[1] === '') {
			$message = $this->maniaControl->getChat()->formatMessage(
				'No player name given! Example: %s',
				$params[0] .' <player name>'
			);
			$this->maniaControl->getChat()->sendUsageInfo($message, $adminPlayer);
			return;
		}

		$target = $params[1];

		$players = $this->maniaControl->getPlayerManager()->getPlayers();
	
		$indexedList = [];
		foreach ($players as $player) {
			similar_text($target, $player->nickname, $percent);
			$indexedList[intval($percent)][] = $player;
		}
		krsort($indexedList);

		$manialink = new ManiaLink(self::MANIALINK_ID);
		
		$parentFrame = new Frame();
		$manialink->addChild($parentFrame);
		$parentFrame->setPosition(-150., -35., 100.);

		$background = new Quad();
		$parentFrame->addChild($background);
		$background->setHorizontalAlign(Quad::LEFT);
		$background->setVerticalAlign(Quad::TOP);
		$background->setBackgroundColor('000000');
		$background->setOpacity(0.7);
		$background->setSize(60., 25.);
		$background->setZ(-1.);

		$closeButton = new Quad_Icons64x64_1();
		$parentFrame->addChild($closeButton);
		$closeButton->setPosition(58., -2.);
		$closeButton->setSize(6, 6);
		$closeButton->setSubStyle($closeButton::SUBSTYLE_QuitRace);
		$closeButton->setAction(self::ACTION_CLOSE);

		$headerName = new Label();
		$parentFrame->addChild($headerName);
		$headerName->setPosition(1., -3);
		$headerName->setHorizontalAlign($headerName::LEFT);
		$headerName->setTextFont('GameFontExtraBold');
		$headerName->setTextColor('ffffff');
		$headerName->setTextSize(1.5);
		$headerName->setText('Player Name');

		$headerMatching = new Label();
		$parentFrame->addChild($headerMatching);
		$headerMatching->setPosition(40., -3);
		$headerMatching->setHorizontalAlign($headerMatching::CENTER);
		$headerMatching->setTextFont('GameFontExtraBold');
		$headerMatching->setTextColor('ffffff');
		$headerMatching->setTextSize(1.5);
		$headerMatching->setText('Matching');

		$count = 1;
		$posY = -7.;
		foreach ($indexedList as $percent => $players) {
			foreach ($players as $player) {
				$frame = new Frame();
				$parentFrame->addChild($frame);
				$frame->setY($posY);

				$name = new Label();
				$frame->addChild($name);
				$name->setX(1.5);
				$name->setSize(30., 3.5);
				$name->setHorizontalAlign($name::LEFT);
				$name->setTextFont('GameFontSemiBold');
				$name->setTextColor('ffffff');
				$name->setTextSize(1.2);
				$name->setText($player->nickname);

				$matching = new Label();
				$frame->addChild($matching);
				$matching->setX(40.);
				$matching->setHorizontalAlign($name::CENTER);
				$matching->setTextFont('GameFontSemiBold');
				$matching->setTextColor('ffffff');
				$matching->setTextSize(1.2);
				$matching->setText($percent . '%');

				$kickButton = new Quad();
				$frame->addChild($kickButton);
				$kickButton->setX(57.);
				$kickButton->setSize(4, 4);
				$kickButton->setStyle('UICommon64_2');
				$kickButton->setSubStyle('UserDelete_light');
				$kickButton->setAction(self::ACTION_KICK . '.' . $player->login);	
				
				$posY += -3.8;
				$count++;
				if ($count > 5) break 2;
			}
		}

		$this->maniaControl->getManialinkManager()->sendManialink($manialink, $adminPlayer, ToggleUIFeature: false);
	}

	public function handleClose(array $structure, Player $adminPlayer) {
		$this->maniaControl->getManialinkManager()->hideManialink(self::MANIALINK_ID, $adminPlayer);
	}

	public function handleKick(array $structure, Player $adminPlayer) {
		if (!$this->maniaControl->getAuthenticationManager()->checkPermission($adminPlayer, PlayerActions::SETTING_PERMISSION_KICK_PLAYER)) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($adminPlayer);
			return;
		}
		$targetLogin = explode('.', $structure[1][2])[1];

		$targetPlayer = $this->maniaControl->getPlayerManager()->getPlayer($targetLogin);
		Logger::log("========================= Fast kick info: ====");
		Logger::log(json_encode($targetPlayer));
		Logger::log(json_encode($this->maniaControl->getClient()->getNetworkStats()));

		$this->maniaControl->getPlayerManager()->getPlayerActions()->kickPlayer($adminPlayer, $targetLogin);
		$this->maniaControl->getManialinkManager()->hideManialink(self::MANIALINK_ID, $adminPlayer);
	}

	/**
	 * Unload the plugin and its Resources
	 */
	public function unload() {}
}