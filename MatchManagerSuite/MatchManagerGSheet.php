<?php

namespace MatchManagerSuite;

use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\CallbackManager;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Logger;
use ManiaControl\ManiaControl;
use ManiaControl\Players\Player;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;
use MatchManagerSuite\MatchManagerCore;
use ManiaControl\Utils\WebReader;
use ManiaControl\Files\AsyncHttpRequest;
use ManiaControl\Commands\CommandListener;
use ManiaControl\Admin\AuthenticationManager;


/**
 * MatchManager GSheet
 *
 * @author		Beu
 * @license		http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class MatchManagerGSheet implements  CallbackListener, CommandListener, Plugin {
	/*
	 * Constants
	 */
	const PLUGIN_ID											= 156;
	const PLUGIN_VERSION									= 0.1;
	const PLUGIN_NAME										= 'MatchManager GSheet';
	const PLUGIN_AUTHOR										= 'Beu';

	// MatchManagerGSheet Properties
	const DB_GSHEETSECRETSETTINGS							= 'MatchManagerGSheet_SecretSettings';

	const MATCHMANAGERCORE_PLUGIN							= 'MatchManagerSuite\MatchManagerCore';

	const SETTING_MATCHMANAGERGSHEET_CLIENT_ID				= 'Google API Client_ID:';
	const SETTING_MATCHMANAGERGSHEET_CLIENT_SECRET			= 'Google API Client_Secret:';
	const SETTING_MATCHMANAGERGSHEET_SPREADSHEET			= 'GSheet Spreadsheet ID:';	

	const SETTING_MATCHMANAGERGSHEET_SHEETNAME				= 'GSheet Sheet name:';	


	/*
	 * Private properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl 			= null;
	private $matchstatus			= "";
	private $device_code			= "";
	private $access_token			= "";
	private $matchid				= "";

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
		return 'Export data from each round to Google Sheet';
	}

	/**
	 * @param \ManiaControl\ManiaControl $maniaControl
	 * @return bool
	 * @see \ManiaControl\Plugins\Plugin::load()
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;
		$this->initTables();
		$this->MatchManagerCore = $this->maniaControl->getPluginManager()->getPlugin(self::MATCHMANAGERCORE_PLUGIN);

		// Callbacks
		$this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSettings');
		
		$this->maniaControl->getCallbackManager()->registerCallbackListener(MatchManagerCore::CB_MATCHMANAGER_STARTMATCH, $this, 'CheckAndPrepareSheet');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(MatchManagerCore::CB_MATCHMANAGER_ENDROUND, $this, 'onCallbackEndRound');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(MatchManagerCore::CB_MATCHMANAGER_ENDMATCH, $this, 'onCallbackEndMatch');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(MatchManagerCore::CB_MATCHMANAGER_STOPMATCH, $this, 'onCallbackStopMatch');

		// Settings
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCHMANAGERGSHEET_CLIENT_ID, "", "Used to Authenticate Maniacontrol. See the documentation of the plugin.");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCHMANAGERGSHEET_CLIENT_SECRET, "", "Used to Authenticate Maniacontrol. See the documentation of the plugin.");
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCHMANAGERGSHEET_SPREADSHEET, "", "Spreadsheet ID from the URL");

		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_MATCHMANAGERGSHEET_SHEETNAME, "#NAME# Finals", "Variables available: #MATCHID# #NAME# #LOGIN#");

		$this->maniaControl->getCommandManager()->registerCommandListener('matchgsheet', $this, 'onCommandMatchGSheet', true, 'All MatchManager GSheet plugin commands');

		$this->access_token = $this->getSecretSetting("access_token");

		$this->maniaControl->getChat()->sendErrorToAdmins('To use the MatchManagerGSheet plugin, $<$l[https://github.com/AmazingBeu/ManiacontrolPlugins/wiki/MatchManager-GSheet]check the doc$>');
		return true;
	}

	/**
	 * @see \ManiaControl\Plugins\Plugin::unload()
	 */
	public function unload() {
		$this->closeWidgets();
	}

	/**
	 * Initialize needed database tables
	 */
	private function initTables() {
		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		$query = 'CREATE TABLE IF NOT EXISTS `' . self::DB_GSHEETSECRETSETTINGS . '` (
			`settingname` VARCHAR(32) NOT NULL,
			`value` VARCHAR(255) NOT NULL,
				PRIMARY KEY (`settingname`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
		$mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error, E_USER_ERROR);
		}
	}

	/**
	 * Update Widgets on Setting Changes
	 *
	 * @param Setting $setting
	 */
	public function updateSettings(Setting $setting) {
		if ($setting->belongsToClass($this)) {
			if (($setting->setting == self::SETTING_MATCHMANAGERGSHEET_CLIENT_SECRET && $setting->value == "hidden") || $setting->setting == self::SETTING_MATCHMANAGERGSHEET_CLIENT_ID) {
				$this->maniaControl->getChat()->sendErrorToAdmins('Google API Session cleared. You must revalidate a session with //matchgsheet step1');

				$this->saveSecretSetting("access_token");
				$this->saveSecretSetting("expire");
				$this->saveSecretSetting("refresh_token");
			}
			if ($setting->setting == self::SETTING_MATCHMANAGERGSHEET_CLIENT_SECRET && $setting->value != "hidden" && $setting->value != "") {
				$this->saveSecretSetting("client_secret",$setting->value);
				$this->maniaControl->getSettingManager()->setSetting($this, self::SETTING_MATCHMANAGERGSHEET_CLIENT_SECRET, "hidden");
			}
		}
	}

	public function onCommandMatchGSheet(array $chatCallback, Player $player) {
		$authLevel = $this->maniaControl->getSettingManager()->getSettingValue($this->MatchManagerCore, MatchManagerCore::SETTING_MATCH_AUTHLEVEL);
		if (!$this->maniaControl->getAuthenticationManager()->checkRight($player, AuthenticationManager::getAuthLevel($authLevel))) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);
			return;
		}

		$text = $chatCallback[1][2];
		$text = explode(" ", $text);

		if (isset($text[1]) && $text[1] == 'step1') {
			$this->OAuth2Step1($player);
		} elseif (isset($text[1]) && $text[1] == 'step2') {
			$this->OAuth2Step2($player);
		} elseif (isset($text[1]) && $text[1] == 'check') { 
			$this->CheckSpeadsheetAccess($player);
		} else {
			$this->maniaControl->getChat()->sendError('use argument "step1", "step2" or "check"', $player);
		}
	}

	private function OAuth2Step1(Player $player) {
		$clientid = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHMANAGERGSHEET_CLIENT_ID);
		if (empty($clientid)) {
			Logger::logError('Client ID empty');
			$this->maniaControl->getChat()->sendError('Client ID empty', $player);
			return;
		}
		$response = WebReader::postUrl('https://oauth2.googleapis.com/device/code?scope=https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fspreadsheets&client_id=' . $clientid);
		$json = $response->getContent();
		if (!$json) {
			Logger::logError('Impossible to Google API: ' . $json);
			$this->maniaControl->getChat()->sendError('Impossible to Google API: ' . $json, $player);
			return;
		}
		$data = json_decode($json);
		if (!$data) {
			Logger::logError('Json parse error: ' . $json);
			$this->maniaControl->getChat()->sendError('Json parse error: ' . $json, $player);
			return;
		}
		if (isset($data->device_code)) {
			$this->device_code = $data->device_code;
			$this->maniaControl->getChat()->sendSuccess('Open $<$l['. $data->verification_url . ']this link$> and type this code: "' . $data->user_code .'"' , $player);
			$this->maniaControl->getChat()->sendSuccess('After have validate the App, type the commande "//matchgsheet step2"' , $player);
		} elseif (isset($data->error_code)) {
			$this->maniaControl->getChat()->sendError('Google refused the request: ' . $data->error_code, $player);
		} else {
			$this->maniaControl->getChat()->sendError('Unkown error' , $player);
		}
	}

	private function OAuth2Step2(Player $player) {
		$clientid = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHMANAGERGSHEET_CLIENT_ID);
		if (empty($clientid)) {
			Logger::logError('Client ID empty');
			$this->maniaControl->getChat()->sendError('Client ID empty', $player);
			return;
		}

		$clientsecret = $this->getSecretSetting("client_secret");
		if (empty($clientsecret)) {
			Logger::logError('Client Secret empty');
			$this->maniaControl->getChat()->sendError('Client Secret empty', $player);
			return;
		}

		if (empty($this->device_code)) {
			Logger::logError('No device_code. Have you run the step 1?');
			$this->maniaControl->getChat()->sendError('No device_code. Have you run the step 1?', $player);
			return;
		}

		$response = WebReader::postUrl('https://oauth2.googleapis.com/token?grant_type=urn%3Aietf%3Aparams%3Aoauth%3Agrant-type%3Adevice_code&client_id=' . $clientid . '&client_secret=' . $clientsecret . '&device_code=' . $this->device_code);
		$json = $response->getContent();
		if (!$json) {
			Logger::logError('Impossible to Google API: ' . $json);
			$this->maniaControl->getChat()->sendError('Impossible to Google API: ' . $json, $player);
			return;
		}
		$data = json_decode($json);
		if (!$data) {
			Logger::logError('Json parse error: ' . $json);
			$this->maniaControl->getChat()->sendError('Json parse error: ' . $json, $player);
			return;
		}
		if (isset($data->access_token)) {
			$this->access_token = $data->access_token;
			$this->saveSecretSetting("access_token", $data->access_token);
			$this->saveSecretSetting("expire", time() + $data->expires_in);
			$this->saveSecretSetting("refresh_token", $data->refresh_token);
			$this->maniaControl->getChat()->sendSuccess('Maniacontrol is registered' , $player);
		} elseif (isset($data->error_description)) {
			Logger::logError('Google refused the request: ' . $data->error_description);
			$this->maniaControl->getChat()->sendError('Google refused the request: ' . $data->error_description , $player);
		} else {
			Logger::logError('Unkown error' . $data->error_description);
			$this->maniaControl->getChat()->sendError('Unkown error' , $player);
		}
	}

	private function refreshTokenIfNeeded() {
		Logger::Log('refreshTokenIfNeeded');
		$expire = $this->getSecretSetting("expire");
		$refreshtoken = $this->getSecretSetting("refresh_token");
		$clientid = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHMANAGERGSHEET_CLIENT_ID);
		$clientsecret = $this->getSecretSetting("client_secret");
		
		if (!empty($refreshtoken) && !empty($expire) && !empty($clientid) && !empty($clientsecret)) {
			if (time() >= $expire) {
				$response = WebReader::postUrl('https://oauth2.googleapis.com/token?grant_type=refresh_token&client_id=' . $clientid . '&client_secret=' . $clientsecret . '&refresh_token=' . $refreshtoken);
				$json = $response->getContent();
				if (!$json) {
					Logger::logError('Impossible to Google API: ' . $json);
					return;
				}
				$data = json_decode($json);
				if (!$data) {
					Logger::logError('Json parse error: ' . $json);
					return;
				}
				if (isset($data->access_token)) {
					$this->access_token = $data->access_token;
					$this->saveSecretSetting("access_token", $data->access_token);
					$this->saveSecretSetting("expire", time() + $data->expires_in);
				} elseif (isset($data->error_description)) {
					$this->maniaControl->getChat()->sendError('Google refused the request: ' . $data->error_description , $player);
				} else {
					$this->maniaControl->getChat()->sendError('Unkown error' , $player);
				}
			}
			return true;
		}
		return false;
	}

	private function saveSecretSetting(string $settingname, string $value = "") {
		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		$query  = "INSERT INTO `" . self::DB_GSHEETSECRETSETTINGS . "` (
				`settingname`,
				`value`
				) VALUES (
				'" . $settingname . "',
				'" . $value . "'
				) ON DUPLICATE KEY UPDATE
				`value` = '" . $value . "';";
		$result = $mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error);
			return false;
		}

		return $result;
	}

	private function getSecretSetting(string $settingname) {
		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		$query = "SELECT `settingname`,`value` FROM `" . self::DB_GSHEETSECRETSETTINGS . "`
				WHERE `settingname` = '" . $settingname . "' LIMIT 1";
		$result = $mysqli->query($query);
		$array = mysqli_fetch_array($result);
		if (isset($array[0])) {
			return $array['value'];
		} else {
			return "";
		}
	}

	private function CheckSpeadsheetAccess(Player $player) {
		if ($this->refreshTokenIfNeeded()) {
			$asyncHttpRequest = new AsyncHttpRequest($this->maniaControl, 'https://sheets.googleapis.com/v4/spreadsheets/' . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHMANAGERGSHEET_SPREADSHEET));
			$asyncHttpRequest->setContentType(AsyncHttpRequest::CONTENT_TYPE_JSON);
			$asyncHttpRequest->setHeaders(array("Authorization: Bearer " . $this->access_token));
			$asyncHttpRequest->setCallable(function ($json, $error) use ($player) {
				if ($error) {
					Logger::logError('Error: ' . $error);
					$this->maniaControl->getChat()->sendError('Error: ' . $error, $player);
					return;
				}
				$data = json_decode($json);
				if (!$data) {
					Logger::logError('Json parse error: ' . $json);
					$this->maniaControl->getChat()->sendError('Json parse error: ' . $json, $player);
					return;
				}
				if (isset($data->properties->title)) {
					$this->maniaControl->getChat()->sendSuccess('Speadsheet name: ' . $data->properties->title, $player);
				} else {
					$this->maniaControl->getChat()->sendError("Can't access to the Spreadsheet: " . $data->error->message, $player);
				}
			});
	
			$asyncHttpRequest->getData(1000);
		}
	}

	private function getSheetName() {
		$sheetname = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHMANAGERGSHEET_SHEETNAME);
		$login = $this->maniaControl->getServer()->login;
		$server_name = $this->maniaControl->getClient()->getServerName();

		$sheetname = str_replace("#MATCHID#", $this->matchid, $sheetname);
		$sheetname = str_replace("#LOGIN#", $login, $sheetname);
		$sheetname = str_replace("#NAME#", $server_name, $sheetname);

		return $sheetname;
	}

	public function UpdateGSheetData(String $matchid, Array $currentscore, Array $currentteamsscore) {
		if ($this->refreshTokenIfNeeded()) {
			$sheetname = $this->getSheetName();

			$data = new \stdClass;
			$data->valueInputOption = "USER_ENTERED";

			$data->data[0] = new \stdClass;
			$data->data[0]->range = "'" . $sheetname . "'!B2";
			$data->data[0]->values = array(array($this->matchstatus),array($this->MatchManagerCore->getCountMap()),array($this->MatchManagerCore->getCountRound()));

			$data->data[1] = new \stdClass;
			$data->data[1]->range = "'" . $sheetname . "'!D2";
			$data->data[1]->values = $currentscore;

			$data->data[2] = new \stdClass;
			$data->data[2]->range = "'" . $sheetname . "'!K2";
			$data->data[2]->values = $currentteamsscore;

			$asyncHttpRequest = new AsyncHttpRequest($this->maniaControl, 'https://sheets.googleapis.com/v4/spreadsheets/' . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHMANAGERGSHEET_SPREADSHEET) . '/values:batchUpdate');
			$asyncHttpRequest->setContentType(AsyncHttpRequest::CONTENT_TYPE_JSON);
			$asyncHttpRequest->setHeaders(array("Authorization: Bearer " . $this->access_token));
			$asyncHttpRequest->setContent(json_encode($data));
			$asyncHttpRequest->setCallable(function ($json, $error) {
				$data = json_decode($json);
				if ($error || !$data) {
					Logger::logError('Error while Sending data: ' . print_r($error, true));
				}
			});
	
			$asyncHttpRequest->postData(1000);
		} else {
			$this->maniaControl->getChat()->sendErrorToAdmins('Impossible to update the Google Sheet');
		}
	}

	function onCallbackEndRound(String $matchid, Array $currentscore, Array $currentteamsscore) {
		$this->matchstatus = "running";
		$this->UpdateGSheetData($matchid, $currentscore, $currentteamsscore);
	}
	function onCallbackEndMatch(String $matchid, Array $currentscore, Array $currentteamsscore) {
		$this->matchstatus = "ended";
		$this->UpdateGSheetData($matchid, $currentscore, $currentteamsscore);
	}
	function onCallbackStopMatch(String $matchid, Array $currentscore, Array $currentteamsscore) {
		$this->matchstatus = "stopped";
		$this->UpdateGSheetData($matchid, $currentscore, $currentteamsscore);
	}

	public function CheckAndPrepareSheet(String $matchid, Array $settings) {
		if ($this->refreshTokenIfNeeded()) {
			$this->matchid = $matchid;
			$asyncHttpRequest = new AsyncHttpRequest($this->maniaControl, 'https://sheets.googleapis.com/v4/spreadsheets/' . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHMANAGERGSHEET_SPREADSHEET));
			$asyncHttpRequest->setContentType(AsyncHttpRequest::CONTENT_TYPE_JSON);
			$asyncHttpRequest->setHeaders(array("Authorization: Bearer " . $this->access_token));
			$asyncHttpRequest->setCallable(function ($json, $error) {
				if ($error) {
					Logger::logError('Error: ' . $error);
					return;
				}
				$data = json_decode($json);
				if (!$data) {
					Logger::logError('Json parse error: ' . $json);
					return;
				}
				if ($data->properties->title) {
					$sheetname = $this->getSheetName();

					$sheetsid = array();
					foreach($data->sheets as $value) {
						if ($value->properties->title == $sheetname) {
							unset($sheetsid);
							$sheetsid = array();
							$sheetsid[0] = $value->properties->sheetId;
							break;
						} else {
							array_push($sheetsid,$value->properties->sheetId);
						}
					}
					$this->matchstatus = "starting";
					$this->PrepareSheet($sheetname, $sheetsid);

				}
			});
	
			$asyncHttpRequest->getData(1000);
		}
	}

	private function PrepareSheet(String $sheetname, array $sheetsid) {
		if ($this->refreshTokenIfNeeded()) {
			Logger::Log("Creating new Sheet: " . $sheetname);
 
			$data = new \stdClass;
			$data->requests = array();
			$i = 0;

			if (count($sheetsid) > 1 || (count($sheetsid) == 1 && $sheetsid[0] == "0")) {
				$sheetid = rand(1000000000,2147483646);
				while (in_array($sheetid, $sheetsid)) {
					$sheetid = rand(1000000000,2147483646);
				}
				$data->requests[$i] = new \stdClass;
				$data->requests[$i]->addSheet = new \stdClass;
				$data->requests[$i]->addSheet->properties = new \stdClass;
				$data->requests[$i]->addSheet->properties->title = $sheetname;
				$data->requests[$i]->addSheet->properties->sheetId = $sheetid;
				$i++;
			} else {
				$sheetid = $sheetsid[0];
			}

			//Merge First & Second Cells
			$data->requests[$i] = new \stdClass;
			$data->requests[$i]->mergeCells = new \stdClass;
			$data->requests[$i]->mergeCells->range = new \stdClass;
			$data->requests[$i]->mergeCells->range->sheetId = $sheetid;
			$data->requests[$i]->mergeCells->range->startRowIndex = 0;
			$data->requests[$i]->mergeCells->range->endRowIndex = 1;
			$data->requests[$i]->mergeCells->range->startColumnIndex = 0;
			$data->requests[$i]->mergeCells->range->endColumnIndex = 2;
			$data->requests[$i]->mergeCells->mergeType = "MERGE_ROWS";
			$i++;

			//Match Infos
			$data->requests[$i] = new \stdClass;
			$data->requests[$i]->repeatCell = new \stdClass;
			$data->requests[$i]->repeatCell->range = new \stdClass;
			$data->requests[$i]->repeatCell->range->sheetId = $sheetid;
			$data->requests[$i]->repeatCell->range->startRowIndex = 0;
			$data->requests[$i]->repeatCell->range->endRowIndex = 4;
			$data->requests[$i]->repeatCell->range->startColumnIndex = 0;
			$data->requests[$i]->repeatCell->range->endColumnIndex = 1;
			$data->requests[$i]->repeatCell->cell = new \stdClass;
			$data->requests[$i]->repeatCell->cell->userEnteredFormat = new \stdClass;
			$data->requests[$i]->repeatCell->cell->userEnteredFormat->backgroundColor = new \stdClass;
			$data->requests[$i]->repeatCell->cell->userEnteredFormat->backgroundColor->red = 0.6;
			$data->requests[$i]->repeatCell->cell->userEnteredFormat->backgroundColor->green = 0.9;
			$data->requests[$i]->repeatCell->cell->userEnteredFormat->backgroundColor->blue = 0.6;
			$data->requests[$i]->repeatCell->cell->userEnteredFormat->textFormat = new \stdClass;
			$data->requests[$i]->repeatCell->cell->userEnteredFormat->textFormat->bold = true;
			$data->requests[$i]->repeatCell->fields = "userEnteredFormat(backgroundColor,textFormat)";

			$i++;

			//Score Table
			$data->requests[$i] = new \stdClass;
			$data->requests[$i]->repeatCell = new \stdClass;
			$data->requests[$i]->repeatCell->range = new \stdClass;
			$data->requests[$i]->repeatCell->range->sheetId = $sheetid;
			$data->requests[$i]->repeatCell->range->startRowIndex = 0;
			$data->requests[$i]->repeatCell->range->endRowIndex = 1;
			$data->requests[$i]->repeatCell->range->startColumnIndex = 3;
			$data->requests[$i]->repeatCell->range->endColumnIndex = 9;
			$data->requests[$i]->repeatCell->cell = new \stdClass;
			$data->requests[$i]->repeatCell->cell->userEnteredFormat = new \stdClass;
			$data->requests[$i]->repeatCell->cell->userEnteredFormat->backgroundColor = new \stdClass;
			$data->requests[$i]->repeatCell->cell->userEnteredFormat->backgroundColor->red = 0.6;
			$data->requests[$i]->repeatCell->cell->userEnteredFormat->backgroundColor->green = 0.9;
			$data->requests[$i]->repeatCell->cell->userEnteredFormat->backgroundColor->blue = 0.6;
			$data->requests[$i]->repeatCell->cell->userEnteredFormat->textFormat = new \stdClass;
			$data->requests[$i]->repeatCell->cell->userEnteredFormat->textFormat->bold = true;
			$data->requests[$i]->repeatCell->cell->userEnteredFormat->horizontalAlignment = "CENTER";
			$data->requests[$i]->repeatCell->fields = "userEnteredFormat(backgroundColor,textFormat,horizontalAlignment)";
			$i++;

			//Team Score Table
			$data->requests[$i] = new \stdClass;
			$data->requests[$i]->repeatCell = new \stdClass;
			$data->requests[$i]->repeatCell->range = new \stdClass;
			$data->requests[$i]->repeatCell->range->sheetId = $sheetid;
			$data->requests[$i]->repeatCell->range->startRowIndex = 0;
			$data->requests[$i]->repeatCell->range->endRowIndex = 1;
			$data->requests[$i]->repeatCell->range->startColumnIndex = 10;
			$data->requests[$i]->repeatCell->range->endColumnIndex = 14;
			$data->requests[$i]->repeatCell->cell = new \stdClass;
			$data->requests[$i]->repeatCell->cell->userEnteredFormat = new \stdClass;
			$data->requests[$i]->repeatCell->cell->userEnteredFormat->backgroundColor = new \stdClass;
			$data->requests[$i]->repeatCell->cell->userEnteredFormat->backgroundColor->red = 0.6;
			$data->requests[$i]->repeatCell->cell->userEnteredFormat->backgroundColor->green = 0.9;
			$data->requests[$i]->repeatCell->cell->userEnteredFormat->backgroundColor->blue = 0.6;
			$data->requests[$i]->repeatCell->cell->userEnteredFormat->textFormat = new \stdClass;
			$data->requests[$i]->repeatCell->cell->userEnteredFormat->textFormat->bold = true;
			$data->requests[$i]->repeatCell->cell->userEnteredFormat->horizontalAlignment = "CENTER";
			$data->requests[$i]->repeatCell->fields = "userEnteredFormat(backgroundColor,textFormat,horizontalAlignment)";
			$i++;

			$asyncHttpRequest = new AsyncHttpRequest($this->maniaControl, 'https://sheets.googleapis.com/v4/spreadsheets/' . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHMANAGERGSHEET_SPREADSHEET) . ':batchUpdate');
			$asyncHttpRequest->setContentType(AsyncHttpRequest::CONTENT_TYPE_JSON);
			$asyncHttpRequest->setHeaders(array("Authorization: Bearer " . $this->access_token));
			$asyncHttpRequest->setContent(json_encode($data));
			$asyncHttpRequest->setCallable(function ($json, $error) {
				$data = json_decode($json);
				if ($error || !$data) {
					Logger::logError('Error while Sending data: ' . print_r($error, true));
				}
			});
			$asyncHttpRequest->postData(1000);


			// Add headers data
			$data = new \stdClass;
			$data->valueInputOption = "USER_ENTERED";

			$data->data[0] = new \stdClass;
			$data->data[0]->range = "'" . $sheetname . "'!A1";
			$data->data[0]->values = array(array("Informations"),array("Match status:", $this->matchstatus),array("Maps:","0/0"),array("Rounds:","0/0"));

			$data->data[1] = new \stdClass;
			$data->data[1]->range = "'" . $sheetname . "'!D1";
			$data->data[1]->values = array(array("Rank","Login", "MatchPoints", "RoundPoints","Time","Team"));

			$data->data[2] = new \stdClass;
			$data->data[2]->range = "'" . $sheetname . "'!K1";
			$data->data[2]->values = array(array("Rank","Team ID", "Name", "MatchPoints"));

			$asyncHttpRequest = new AsyncHttpRequest($this->maniaControl, 'https://sheets.googleapis.com/v4/spreadsheets/' . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHMANAGERGSHEET_SPREADSHEET) . '/values:batchUpdate');
			$asyncHttpRequest->setContentType(AsyncHttpRequest::CONTENT_TYPE_JSON);
			$asyncHttpRequest->setHeaders(array("Authorization: Bearer " . $this->access_token));
			$asyncHttpRequest->setContent(json_encode($data));
			$asyncHttpRequest->setCallable(function ($json, $error) {
				$data = json_decode($json);
				if ($error || !$data) {
					Logger::logError('Error while Sending data: ' . print_r($error, true));
				}
			});
	
			$asyncHttpRequest->postData(1000);

			// Clear Scoreboards data
			$asyncHttpRequest = new AsyncHttpRequest($this->maniaControl, 'https://sheets.googleapis.com/v4/spreadsheets/' . $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_MATCHMANAGERGSHEET_SPREADSHEET) . '/values/' . urlencode("'". $sheetname . "'") . '!D2:N300:clear');
			$asyncHttpRequest->setHeaders(array("Authorization: Bearer " . $this->access_token));
			$asyncHttpRequest->setCallable(function ($json, $error) {
				var_dump($json);
				$data = json_decode($json);
				if ($error || !$data) {
					Logger::logError('Error while Sending data: ' . print_r($error, true));
				}
			});
	
			$asyncHttpRequest->postData(1000);
		}
	}
}