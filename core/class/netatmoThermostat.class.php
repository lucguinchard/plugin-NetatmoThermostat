<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
require_once dirname(__FILE__) . '/../../3rdparty/Netatmo-API-PHP/Netatmo/autoload.php';

use Netatmo\Clients\NAThermApiClient;
use Netatmo\Common\NAScopes;

class netatmoThermostat extends eqLogic {
	/*	 * *************************Attributs****************************** */

	private static $_client = null;
	/*	 * ***********************Methode static*************************** */
	public static $_widgetPossibility = array('custom' => true);

	public static function getClient() {
		if (self::$_client == null) {
			self::$_client = new NAThermApiClient(array(
				'client_id' => config::byKey('client_id', 'netatmoThermostat'),
				'client_secret' => config::byKey('client_secret', 'netatmoThermostat'),
				'username' => config::byKey('username', 'netatmoThermostat'),
				'password' => config::byKey('password', 'netatmoThermostat'),
				'scope' => NAScopes::SCOPE_READ_THERM . " " . NAScopes::SCOPE_WRITE_THERM,
			));
		}
		try {
			self::$_client->getAccessToken();
		} catch (NAClientException $ex) {
			$error_msg = "An error happened  while trying to retrieve your tokens \n" . $ex->getMessage() . "\n";
			log::add('netatmoThermostat', 'debug', $error_msg);
		}
		return self::$_client;
	}

	public static function getFromWelcome() {
		$client_id = config::byKey('client_id', 'netatmoWelcome');
		$client_secret = config::byKey('client_secret', 'netatmoWelcome');
		$username = config::byKey('username', 'netatmoWelcome');
		$password = config::byKey('password', 'netatmoWelcome');
		return (array($client_id, $client_secret, $username, $password));
	}

	public static function getFromWeather() {
		$client_id = config::byKey('client_id', 'netatmoWeather');
		$client_secret = config::byKey('client_secret', 'netatmoWeather');
		$username = config::byKey('username', 'netatmoWeather');
		$password = config::byKey('password', 'netatmoWeather');
		return (array($client_id, $client_secret, $username, $password));
	}

	public function changemodeTherm($multiId, $action, $endtime = NULL) {
		$ids = explode('|', $multiId);
		$deviceid = $ids[0];
		$modid = $ids[1];
		$client = self::getClient();
		switch ($action) {
			case 'away':
				$client->setToAwayMode($deviceid, $modid, $endtime);
				break;
			case 'program':
				$client->setToProgramMode($deviceid, $modid);
				break;
			case 'hg':
				$client->setToFrostGuardMode($deviceid, $modid, $endtime);
				break;
			case 'off':
				$client->turnOff($deviceid, $modid);
				break;
			case 'max':
				$client->setToMaxMode($deviceid, $modid, $endtime);
				break;
		}
		sleep(2);
		$this->syncWithTherm($multiId);
	}

	public function changesetpointTherm($multiId, $setpoint, $endtime = null) {
		$ids = explode('|', $multiId);
		$deviceid = $ids[0];
		$modid = $ids[1];
		if ($endtime == null) {
			$length = $this->getConfiguration('maxdefault');
			if ($length == null || $length == '') {
				$length = 60;
			}
			$endtime = time() + ($length * 60);
		} else {
			$length = ($endtime - time()) / 60;
		}
		log::add('netatmoThermostat', 'debug', "Setting temperature to : " . $setpoint . " for " . $length . " minutes");
		$client = self::getClient();
		$client->setToManualMode($deviceid, $modid, $setpoint, $endtime);
		sleep(2);
		$this->syncWithTherm($multiId, $setpoint);
	}

	public function changescheduleTherm($multiId, $scheduleid) {
		$ids = explode('|', $multiId);
		$deviceid = $ids[0];
		$modid = $ids[1];
		log::add('netatmoThermostat', 'debug', "Setting schedule to : " . $scheduleid);
		$client = self::getClient();
		$client->switchSchedule($deviceid, $modid, $scheduleid);
		sleep(2);
		$this->syncWithTherm($multiId, null, $scheduleid);
	}

	public static function getLastMesure($multiId = null) {
		if ($multiId != null) {
			$eqLogics[] = eqLogic::byLogicalId($multiId, 'netatmoThermostat');
		} else {
			$eqLogics = migoThermostat::byType('netatmoThermostat');
		}
		foreach ($eqLogics as $eqLogic) {
			$multiId = $eqLogic->getLogicalId();
			$ids = explode('|', $multiId);
			$deviceid = $ids[0];
			$moduleid = $ids[1];
			$client = self::getClient();

			$type = 'Temperature,Sp_Temperature';
			$thermmeasure[$multiId] = $client->getMeasure($deviceid, $moduleid, 'max', $type, NULL, 'last', NULL, TRUE, FALSE)[0]['value'][0];
			log::add('netatmoThermostat', 'debug', __FUNCTION__ . ' thermmeasure: ' . json_encode($thermmeasure, true));
		}

		return $thermmeasure;
	}

	public static function syncWithTherm($multiId = null, $forcedSetpoint = null, $scheduleid = null) {
		if ($multiId !== null) {
			$ids = explode('|', $multiId);
			$deviceid = $ids[0];
			$client = self::getClient();
			$therminfo = $client->getData($deviceid);
		} else {
			$client = self::getClient();
			$therminfo = $client->getData();
		}

		log::add('netatmoThermostat', 'debug', json_encode($therminfo, true));
		foreach ($therminfo['devices'] as $thermostat) {
			$modename == '';
			$consigne == '';
			$deviceid = $thermostat['_id'];
			$moduleid = $thermostat['modules'][0]['_id'];
			$multiId = $deviceid . '|' . $moduleid;


			$eqLogic = eqLogic::byLogicalId($multiId, 'netatmoThermostat');
			if (!is_object($eqLogic)) {
				continue;
			}

			$thermmeasure = self::getLastMesure($multiId);

			//$temperature_thermostat = $thermostat["modules"][0]["measured"]["temperature"];
			$temperature_thermostat = null;
			if (isset($thermostat["modules"][0]["measured"]["temperature"])) {
				$temperature_thermostat = $thermostat["modules"][0]["measured"]["temperature"];
			} else {
				$temperature_thermostat = $thermmeasure[$multiId][0];
			}
			//fin modif

			$wifistatus = $thermostat["wifi_status"];
			$anticipation = 0;
			if (isset($thermostat["modules"][0]["anticipating"])) {
				$anticipation = $thermostat["modules"][0]["anticipating"];
			}
			$devicefirm = $thermostat["firmware"];
			$modulefirm = $thermostat["modules"][0]["firmware"];
			$eqLogic->setConfiguration('devicefirm', $devicefirm);
			$eqLogic->setConfiguration('modulefirm', $modulefirm);
			$batterie = $thermostat["modules"][0]["battery_vp"];
			$rfstatus = $thermostat["modules"][0]["rf_status"];
			$chaudierestate = $thermostat["modules"][0]["therm_relay_cmd"];
			if ($chaudierestate != 0) {
				$chaudierestate = 1;
			}
			$planindex = 0;
			$count = 0;
			$listplanning = '';
			foreach ($thermostat["modules"][0]["therm_program_list"] as $plan) {
				$status = (isset($plan['selected'])) ? "YES" : "NO";
				if ($status == "YES" && $scheduleid == null) {
					$planningname = $plan['name'];
					$planindex = $count;
				} else if ($scheduleid != null && $plan['program_id'] == $scheduleid) {
					$planningname = $plan['name'];
					$planindex = $count;
				}
				$listplanning = $listplanning . $plan['name'] . ';' . $plan['program_id'] . '|';
				$count++;
			}
			$mode = $thermostat["modules"][0]["setpoint"]["setpoint_mode"];
			$planning = 'Aucun';
			$nextplanning = 'Aucun';
			$setpointmode_endtime = 'Nouvel Ordre';
			$actualdate = date('d/m/Y');
			if ($mode == 'away') {
				foreach ($thermostat["modules"][0]["therm_program_list"][$planindex]["zones"] as $listmode) {
					if ($listmode['type'] == 2) {
						$consigne = $listmode["temp"];
						$modename = $listmode["name"];
					}
				}
				if (isset($thermostat["modules"][0]["setpoint"]["setpoint_endtime"])) {
					if ($actualdate == $setpointmode_endtime = date('d/m/Y', $thermostat["modules"][0]["setpoint"]["setpoint_endtime"])) {
						$setpointmode_endtime = date('H:i', $thermostat["modules"][0]["setpoint"]["setpoint_endtime"]);
					} else {
						$setpointmode_endtime = date('d/m H:i', $thermostat["modules"][0]["setpoint"]["setpoint_endtime"]);
					}
				}
			} elseif ($mode == 'hg') {
				foreach ($thermostat["modules"][0]["therm_program_list"][$planindex]["zones"] as $listmode) {
					if ($listmode['type'] == 3) {
						$consigne = $listmode["temp"];
						$modename = $listmode["name"];
					}
				}
				if (isset($thermostat["modules"][0]["setpoint"]["setpoint_endtime"])) {
					if ($actualdate == $setpointmode_endtime = date('d/m/Y', $thermostat["modules"][0]["setpoint"]["setpoint_endtime"])) {
						$setpointmode_endtime = date('H:i', $thermostat["modules"][0]["setpoint"]["setpoint_endtime"]);
					} else {
						$setpointmode_endtime = date('d/m H:i', $thermostat["modules"][0]["setpoint"]["setpoint_endtime"]);
					}
				}
			} elseif ($mode == 'manual') {
				$consigne = $thermostat["modules"][0]["setpoint"]["setpoint_temp"];
				if (isset($thermostat["modules"][0]["setpoint"]["setpoint_endtime"])) {
					if ($actualdate == $setpointmode_endtime = date('d/m/Y', $thermostat["modules"][0]["setpoint"]["setpoint_endtime"])) {
						$setpointmode_endtime = date('H:i', $thermostat["modules"][0]["setpoint"]["setpoint_endtime"]);
					} else {
						$setpointmode_endtime = date('d/m H:i', $thermostat["modules"][0]["setpoint"]["setpoint_endtime"]);
					}
				}
				$modename = 'Manuel';
			} elseif ($mode == 'program') {
				$day = date('w', time()) - 1;
				$hour = date('H', time());
				$min = date('i', time());
				if ($day == -1) {
					$day = 6;
				};
				$temps = ($day * 86400) + ($hour * 3600) + ($min * 60);
				$idfound = 99;
				$goodkey = 0;
				foreach ($thermostat["modules"][0]["therm_program_list"][$planindex]["timetable"] as $key => $time) {
					if ($time["m_offset"] * 60 > $temps) {
						$idfound = $time["id"];
						$goodkey = $key;
						$minutes = $time["m_offset"];
						break;
					}
				}
				if ($idfound == 99) {
					if (count($thermostat["modules"][0]["therm_program_list"][$planindex]["timetable"] == 1)) {
						$minutes = 0;
						$planning_id = $thermostat["modules"][0]["therm_program_list"][$planindex]["timetable"][0]["id"];
						$nextplanning_id = $thermostat["modules"][0]["therm_program_list"][$planindex]["timetable"][0]["id"];
					} else {
						$planning_id = $thermostat["modules"][0]["therm_program_list"][$planindex]["timetable"][count($thermostat["modules"][0]["therm_program_list"][$planindex]["timetable"])]["id"];
						$nextplanning_id = $thermostat["modules"][0]["therm_program_list"][$planindex]["timetable"][0]["id"];
					}
				} else {
					$planning_id = $thermostat["modules"][0]["therm_program_list"][$planindex]["timetable"][$goodkey - 1]["id"];
					$nextplanning_id = $thermostat["modules"][0]["therm_program_list"][$planindex]["timetable"][$goodkey]["id"];
				}
				foreach ($thermostat["modules"][0]["therm_program_list"][$planindex]["zones"] as $zone) {
					if ($zone["id"] == $planning_id) {
						if (isset($zone["name"])) {
							$planning = $zone["name"];
						} else {
							$planning = 'Aucun';
						}
						if (isset($thermostat["modules"][0]["measured"]["setpoint_temp"]) && $anticipation == true) {
							$consigne = $thermostat["modules"][0]["measured"]["setpoint_temp"];
						} else {
							$consigne = $zone["temp"];
						}
					} else if ($zone["id"] == $nextplanning_id) {
						$nextplanning = $zone["name"];
					}
				}
				$secondes = 60 * $minutes;
				$jour = floor($secondes / 86400);
				$reste = $secondes % 86400;
				$heure = floor($reste / 3600);
				$reste = $reste % 3600;
				$minute = floor($reste / 60);
				$seconde = $reste % 60;
				$zero = '';
				$zeroh = '';
				if ($minute <= 9) {
					$zero = '0';
				}
				if ($heure <= 9) {
					$zeroh = '0';
				}
				$setpointmode_endtime = $zeroh . $heure . ':' . $zero . $minute;
				if ($setpointmode_endtime == '00:00') {
					$setpointmode_endtime = '';
				}
				$modename = 'Programme';
			} elseif ($mode == 'off') {
				$consigne = 0;
				$modename = 'Off';
			} elseif ($mode == 'max') {
				$consigne = 30;
				$modename = 'Forcé';
				if (isset($thermostat["modules"][0]["setpoint"]["setpoint_endtime"])) {
					if ($actualdate == $setpointmode_endtime = date('d/m/Y', $thermostat["modules"][0]["setpoint"]["setpoint_endtime"])) {
						$setpointmode_endtime = date('H:i', $thermostat["modules"][0]["setpoint"]["setpoint_endtime"]);
					} else {
						$setpointmode_endtime = date('d/m H:i', $thermostat["modules"][0]["setpoint"]["setpoint_endtime"]);
					}
				}
			} else {
				$consigne = $thermostat["modules"][0]["measured"]["setpoint_temp"];
				$modename = $mode;
			}
			if ($modename == '') {
				$modename = $mode;
			}
			if ($consigne == '') {
				$consigne = 1;
			}
			foreach ($eqLogic->getCmd('info') as $cmd) {
				switch ($cmd->getLogicalId()) {
					case 'temperature':
						$value = $temperature_thermostat;
						break;
					case 'mode':
						$value = $modename;
						break;
					case 'modetech':
						$value = $mode;
						break;
					case 'wifistatus':
						$value = $wifistatus;
						break;
					case 'rfstatus':
						$value = $rfstatus;
						break;
					case 'consigne':
						if ($forcedSetpoint != null) {
							$value = $forcedSetpoint;
						} else {
							$value = $consigne;
						}
						break;
					case 'planning':
						$value = $planning;
						break;
					case 'nextplanning':
						$value = $nextplanning;
						break;
					case 'calendar':
						$value = $planningname;
						break;
					case 'listcalendar':
						$value = substr($listplanning, 0, -1);
						break;
					case 'heatstatus':
						$value = $chaudierestate;
						break;
					case 'endsetpoint':
						$value = $setpointmode_endtime;
						break;
					case 'anticipation':
						$value = $anticipation;
						break;
					case 'batterie':
						$batterylevel = round(($batterie - 3000) / 15);
						if ($batterylevel < 0) {
							$batterylevel = 0;
						} else if ($batterylevel > 100) {
							$batterylevel = 100;
						}
						$eqLogic->batteryStatus($batterylevel);
						$value = $batterylevel;
						break;
					case 'statusname':
						if ($mode == 'away') {
							$value = 'Jusqu’à ' . $setpointmode_endtime;
						} else if ($mode == 'hg') {
							$value = 'Jusqu’à ' . $setpointmode_endtime;
						} else if ($mode == 'max') {
							$value = 'Jusqu’à ' . $setpointmode_endtime;
						} else if ($mode == 'program') {
							$value = $planning . ' -> ' . $nextplanning . ' ' . $setpointmode_endtime;
						} else if ($mode == 'off') {
							$value = 'Eteint';
						} else {
							$value = 'Jusqu’à ' . $setpointmode_endtime;
						}
						break;
				}
				$cmd->event($value);
				log::add('netatmoThermostat', 'debug', 'set: ' . $cmd->getName() . ' to ' . $value);
			}
			$eqLogic->refreshWidget();
		}
	}

	public static function syncWithNetatmo() {
		$client = self::getClient();
		$devicelist = $client->getData();
		log::add('netatmoThermostat', 'debug', print_r($devicelist, true));
		foreach ($devicelist['devices'] as $thermostat) {
			$deviceid = $thermostat['_id'];
			$devicefirm = $thermostat['firmware'];
			$module_name = $thermostat['station_name'];
			$modulefirm = $thermostat['modules'][0]['firmware'];
			$moduleid = $thermostat['modules'][0]['_id'];
			$multiId = $deviceid . '|' . $moduleid;
			$eqLogic = netatmoThermostat::byLogicalId($multiId, 'netatmoThermostat');
			if (!is_object($eqLogic)) {
				$eqLogic = new self();
				foreach (jeeObject::all() as $object) {
					if (stristr($module_name, $object->getName())) {
						$eqLogic->setObject_id($object->getId());
						break;
					}
				}
				$eqLogic->setLogicalId($multiId);
				$eqLogic->setCategory('heating', 1);
				$eqLogic->setName('Netatmo ' . $module_name);
				$eqLogic->setConfiguration('battery_type', '3x1.5V AAA');
				$eqLogic->setEqType_name('netatmoThermostat');
				$eqLogic->setIsVisible(1);
				$eqLogic->setIsEnable(1);
				$eqLogic->save();
				$eqLogic->syncWithTherm($multiId);
			}
		}
	}

	public static function cron15() {
		log::add('netatmoThermostat', 'debug', 'Started');
		try {
			try {
				$client = self::getClient();
				if (config::byKey('numberFailed', 'netatmoThermostat', 0) > 0) {
					config::save('numberFailed', 0, 'netatmoThermostat');
				}
				netatmoThermostat::syncWithTherm();
			} catch (NAClientException $ex) {
				if (config::byKey('numberFailed', 'netatmoThermostat', 0) > 3) {
					log::add('netatmoThermostat', 'error', __('Erreur sur synchro netatmo thermostat ', __FILE__) . ' (' . config::byKey('numberFailed', 'netatmoThermostat', 0) . ') ' . $ex->getMessage());
				} else {
					config::save('numberFailed', config::byKey('numberFailed', 'netatmoThermostat', 0) + 1, 'netatmoThermostat');
				}
				return;
			}
		} catch (Exception $e) {
			return '';
		}
	}

	/*	 * *********************Methode d'instance************************* */

	public function postSave() {
		$netatmoThermostatcmd = $this->getCmd(null, 'consigne');
		if (!is_object($netatmoThermostatcmd)) {
			$netatmoThermostatcmd = new netatmoThermostatcmd();
			$netatmoThermostatcmd->setName(__('Consigne', __FILE__));
			$netatmoThermostatcmd->setIsHistorized(1);
		}
		$netatmoThermostatcmd->setEqLogic_id($this->getId());
		$netatmoThermostatcmd->setLogicalId('consigne');
		$netatmoThermostatcmd->setUnite('°C');
		$netatmoThermostatcmd->setType('info');
		$netatmoThermostatcmd->setSubType('numeric');
		$netatmoThermostatcmd->setDisplay('generic_type', 'THERMOSTAT_SETPOINT');
		$netatmoThermostatcmd->save();

		$netatmoThermostatcmd = $this->getCmd(null, 'temperature');
		if (!is_object($netatmoThermostatcmd)) {
			$netatmoThermostatcmd = new netatmoThermostatcmd();
			$netatmoThermostatcmd->setName(__('Température', __FILE__));
			$netatmoThermostatcmd->setIsHistorized(1);
		}
		$netatmoThermostatcmd->setEqLogic_id($this->getId());
		$netatmoThermostatcmd->setLogicalId('temperature');
		$netatmoThermostatcmd->setUnite('°C');
		$netatmoThermostatcmd->setType('info');
		$netatmoThermostatcmd->setSubType('numeric');
		$netatmoThermostatcmd->setDisplay('generic_type', 'THERMOSTAT_TEMPERATURE');
		$netatmoThermostatcmd->save();

		$netatmoThermostatcmd = $this->getCmd(null, 'mode');
		if (!is_object($netatmoThermostatcmd)) {
			$netatmoThermostatcmd = new netatmoThermostatcmd();
			$netatmoThermostatcmd->setName(__('Mode', __FILE__));
		}
		$netatmoThermostatcmd->setEqLogic_id($this->getId());
		$netatmoThermostatcmd->setLogicalId('mode');
		$netatmoThermostatcmd->setType('info');
		$netatmoThermostatcmd->setSubType('string');
		$netatmoThermostatcmd->setDisplay('generic_type', 'THERMOSTAT_MODE');
		$netatmoThermostatcmd->save();

		$netatmoThermostatcmd = $this->getCmd(null, 'modetech');
		if (!is_object($netatmoThermostatcmd)) {
			$netatmoThermostatcmd = new netatmoThermostatcmd();
			$netatmoThermostatcmd->setName(__('ModeTech', __FILE__));
		}
		$netatmoThermostatcmd->setEqLogic_id($this->getId());
		$netatmoThermostatcmd->setLogicalId('modetech');
		$netatmoThermostatcmd->setType('info');
		$netatmoThermostatcmd->setSubType('string');
		$netatmoThermostatcmd->save();

		$netatmoThermostatcmd = $this->getCmd(null, 'planning');
		if (!is_object($netatmoThermostatcmd)) {
			$netatmoThermostatcmd = new netatmoThermostatcmd();
			$netatmoThermostatcmd->setName(__('Planning', __FILE__));
		}
		$netatmoThermostatcmd->setEqLogic_id($this->getId());
		$netatmoThermostatcmd->setLogicalId('planning');
		$netatmoThermostatcmd->setType('info');
		$netatmoThermostatcmd->setSubType('string');
		$netatmoThermostatcmd->save();

		$netatmoThermostatcmd = $this->getCmd(null, 'calendar');
		if (!is_object($netatmoThermostatcmd)) {
			$netatmoThermostatcmd = new netatmoThermostatcmd();
			$netatmoThermostatcmd->setName(__('Calendrier', __FILE__));
		}
		$netatmoThermostatcmd->setEqLogic_id($this->getId());
		$netatmoThermostatcmd->setLogicalId('calendar');
		$netatmoThermostatcmd->setType('info');
		$netatmoThermostatcmd->setSubType('string');
		$netatmoThermostatcmd->save();

		$netatmoThermostatcmd = $this->getCmd(null, 'nextplanning');
		if (!is_object($netatmoThermostatcmd)) {
			$netatmoThermostatcmd = new netatmoThermostatcmd();
			$netatmoThermostatcmd->setName(__('Planning suivant', __FILE__));
		}
		$netatmoThermostatcmd->setEqLogic_id($this->getId());
		$netatmoThermostatcmd->setLogicalId('nextplanning');
		$netatmoThermostatcmd->setType('info');
		$netatmoThermostatcmd->setSubType('string');
		$netatmoThermostatcmd->save();

		$netatmoThermostatcmd = $this->getCmd(null, 'anticipation');
		if (!is_object($netatmoThermostatcmd)) {
			$netatmoThermostatcmd = new netatmoThermostatcmd();
			$netatmoThermostatcmd->setName(__('Anticipation en cours', __FILE__));
		}
		$netatmoThermostatcmd->setEqLogic_id($this->getId());
		$netatmoThermostatcmd->setLogicalId('anticipation');
		$netatmoThermostatcmd->setType('info');
		$netatmoThermostatcmd->setSubType('binary');
		$netatmoThermostatcmd->save();

		$netatmoThermostatcmd = $this->getCmd(null, 'listcalendar');
		if (!is_object($netatmoThermostatcmd)) {
			$netatmoThermostatcmd = new netatmoThermostatcmd();
			$netatmoThermostatcmd->setName(__('Liste Calendrier', __FILE__));
		}
		$netatmoThermostatcmd->setEqLogic_id($this->getId());
		$netatmoThermostatcmd->setLogicalId('listcalendar');
		$netatmoThermostatcmd->setType('info');
		$netatmoThermostatcmd->setSubType('string');
		$netatmoThermostatcmd->save();

		$netatmoThermostatcmd = $this->getCmd(null, 'rfstatus');
		if (!is_object($netatmoThermostatcmd)) {
			$netatmoThermostatcmd = new netatmoThermostatcmd();
			$netatmoThermostatcmd->setName(__('Signal RF', __FILE__));
			$netatmoThermostatcmd->setIsHistorized(1);
		}
		$netatmoThermostatcmd->setEqLogic_id($this->getId());
		$netatmoThermostatcmd->setLogicalId('rfstatus');
		$netatmoThermostatcmd->setUnite('%');
		$netatmoThermostatcmd->setType('info');
		$netatmoThermostatcmd->setSubType('numeric');
		$netatmoThermostatcmd->save();

		$netatmoThermostatcmd = $this->getCmd(null, 'batterie');
		if (!is_object($netatmoThermostatcmd)) {
			$netatmoThermostatcmd = new netatmoThermostatcmd();
			$netatmoThermostatcmd->setName(__('Batterie', __FILE__));
			$netatmoThermostatcmd->setIsHistorized(1);
		}
		$netatmoThermostatcmd->setEqLogic_id($this->getId());
		$netatmoThermostatcmd->setLogicalId('batterie');
		$netatmoThermostatcmd->setType('info');
		$netatmoThermostatcmd->setSubType('numeric');
		$netatmoThermostatcmd->save();

		$netatmoThermostatcmd = $this->getCmd(null, 'endsetpoint');
		if (!is_object($netatmoThermostatcmd)) {
			$netatmoThermostatcmd = new netatmoThermostatcmd();
			$netatmoThermostatcmd->setName(__('Fin Mode en Cours', __FILE__));
			$netatmoThermostatcmd->setIsHistorized(0);
		}
		$netatmoThermostatcmd->setEqLogic_id($this->getId());
		$netatmoThermostatcmd->setLogicalId('endsetpoint');
		$netatmoThermostatcmd->setType('info');
		$netatmoThermostatcmd->setSubType('string');
		$netatmoThermostatcmd->save();

		$netatmoThermostatcmd = $this->getCmd(null, 'wifistatus');
		if (!is_object($netatmoThermostatcmd)) {
			$netatmoThermostatcmd = new netatmoThermostatcmd();
			$netatmoThermostatcmd->setName(__('Signal Wifi', __FILE__));
			$netatmoThermostatcmd->setIsHistorized(1);
		}
		$netatmoThermostatcmd->setEqLogic_id($this->getId());
		$netatmoThermostatcmd->setLogicalId('wifistatus');
		$netatmoThermostatcmd->setType('info');
		$netatmoThermostatcmd->setUnite('%');
		$netatmoThermostatcmd->setSubType('numeric');
		$netatmoThermostatcmd->save();

		$netatmoThermostatcmd = $this->getCmd(null, 'heatstatus');
		if (!is_object($netatmoThermostatcmd)) {
			$netatmoThermostatcmd = new netatmoThermostatcmd();
			$netatmoThermostatcmd->setName(__('Etat Chauffage', __FILE__));
			$netatmoThermostatcmd->setIsHistorized(1);
		}
		$netatmoThermostatcmd->setEqLogic_id($this->getId());
		$netatmoThermostatcmd->setLogicalId('heatstatus');
		$netatmoThermostatcmd->setType('info');
		$netatmoThermostatcmd->setSubType('binary');
		$netatmoThermostatcmd->setDisplay('generic_type', 'THERMOSTAT_STATE');
		$netatmoThermostatcmd->save();

		$netatmoThermostatcmd = $this->getCmd(null, 'statusname');
		if (!is_object($netatmoThermostatcmd)) {
			$netatmoThermostatcmd = new netatmoThermostatcmd();
			$netatmoThermostatcmd->setName(__('Statut pour mobile', __FILE__));
			$netatmoThermostatcmd->setIsHistorized(1);
		}
		$netatmoThermostatcmd->setEqLogic_id($this->getId());
		$netatmoThermostatcmd->setLogicalId('statusname');
		$netatmoThermostatcmd->setType('info');
		$netatmoThermostatcmd->setSubType('string');
		$netatmoThermostatcmd->setDisplay('generic_type', 'THERMOSTAT_STATE_NAME');
		$netatmoThermostatcmd->save();

		$refresh = $this->getCmd(null, 'refresh');
		if (!is_object($refresh)) {
			$refresh = new netatmoThermostatcmd();
			$refresh->setLogicalId('refresh');
			$refresh->setIsVisible(1);
			$refresh->setName(__('Rafraichir', __FILE__));
		}
		$refresh->setType('action');
		$refresh->setSubType('other');
		$refresh->setEqLogic_id($this->getId());
		$refresh->save();

		$away = $this->getCmd(null, 'away');
		if (!is_object($away)) {
			$away = new netatmoThermostatcmd();
			$away->setLogicalId('away');
			$away->setIsVisible(1);
			$away->setName(__('Absent', __FILE__));
		}
		$away->setType('action');
		$away->setDisplay('title_disable', 1);
		$away->setDisplay('message_placeholder', __('Durée (minutes)', __FILE__));
		$away->setSubType('message');
		$away->setEqLogic_id($this->getId());
		$away->save();

		$program = $this->getCmd(null, 'program');
		if (!is_object($program)) {
			$program = new netatmoThermostatcmd();
			$program->setLogicalId('program');
			$program->setIsVisible(1);
			$program->setName(__('Mode Auto', __FILE__));
		}
		$program->setType('action');
		$program->setSubType('other');
		$program->setOrder(1);
		$program->setDisplay('generic_type', 'THERMOSTAT_SET_MODE');
		$program->setEqLogic_id($this->getId());
		$program->save();

		$awaymobile = $this->getCmd(null, 'awaymobile');
		if (!is_object($awaymobile)) {
			$awaymobile = new netatmoThermostatcmd();
			$awaymobile->setLogicalId('awaymobile');
			$awaymobile->setIsVisible(1);
			$awaymobile->setName(__('Mode Absent', __FILE__));
		}
		$awaymobile->setType('action');
		$awaymobile->setSubType('other');
		$awaymobile->setOrder(2);
		$awaymobile->setDisplay('generic_type', 'THERMOSTAT_SET_MODE');
		$awaymobile->setEqLogic_id($this->getId());
		$awaymobile->save();

		$hg = $this->getCmd(null, 'hg');
		if (!is_object($hg)) {
			$hg = new netatmoThermostatcmd();
			$hg->setLogicalId('hg');
			$hg->setIsVisible(1);
			$hg->setName(__('Hors-gel', __FILE__));
		}
		$hg->setType('action');
		$hg->setDisplay('title_disable', 1);
		$hg->setDisplay('message_placeholder', __('Durée (minutes)', __FILE__));
		$hg->setSubType('message');
		$hg->setEqLogic_id($this->getId());
		$hg->save();

		$hgmobile = $this->getCmd(null, 'hgmobile');
		if (!is_object($hgmobile)) {
			$hgmobile = new netatmoThermostatcmd();
			$hgmobile->setLogicalId('hgmobile');
			$hgmobile->setIsVisible(1);
			$hgmobile->setName(__('Mode Hors-gel ', __FILE__));
		}
		$hgmobile->setType('action');
		$hgmobile->setSubType('other');
		$hgmobile->setOrder(3);
		$hgmobile->setDisplay('generic_type', 'THERMOSTAT_SET_MODE');
		$hgmobile->setEqLogic_id($this->getId());
		$hgmobile->save();

		$maxmobile = $this->getCmd(null, 'maxmobile');
		if (!is_object($maxmobile)) {
			$maxmobile = new netatmoThermostatcmd();
			$maxmobile->setLogicalId('maxmobile');
			$maxmobile->setIsVisible(1);
			$maxmobile->setName(__('Mode Forcé', __FILE__));
		}
		$maxmobile->setType('action');
		$maxmobile->setSubType('other');
		$maxmobile->setOrder(4);
		$maxmobile->setDisplay('generic_type', 'THERMOSTAT_SET_MODE');
		$maxmobile->setEqLogic_id($this->getId());
		$maxmobile->save();

		$off = $this->getCmd(null, 'off');
		if (!is_object($off)) {
			$off = new netatmoThermostatcmd();
			$off->setLogicalId('off');
			$off->setIsVisible(1);
			$off->setName(__('Eteindre', __FILE__));
		}
		$off->setType('action');
		$off->setSubType('other');
		$off->setOrder(5);
		$off->setEqLogic_id($this->getId());
		$off->setDisplay('generic_type', 'THERMOSTAT_SET_MODE');
		$off->save();

		$max = $this->getCmd(null, 'max');
		if (!is_object($max)) {
			$max = new netatmoThermostatcmd();
			$max->setLogicalId('max');
			$max->setIsVisible(1);
			$max->setName(__('Max', __FILE__));
		}
		$max->setType('action');
		$max->setDisplay('title_disable', 1);
		$max->setDisplay('message_placeholder', __('Durée (minutes)', __FILE__));
		$max->setSubType('message');
		$max->setEqLogic_id($this->getId());
		$max->save();

		$consigneset = $this->getCmd(null, 'consigneset');
		if (!is_object($consigneset)) {
			$consigneset = new netatmoThermostatcmd();
			$consigneset->setLogicalId('consigneset');
			$consigneset->setIsVisible(1);
			$consigneset->setName(__('Réglage Consigne', __FILE__));
		}
		$consigneset->setType('action');
		$consigneset->setDisplay('title_placeholder', __('Température', __FILE__));
		$consigneset->setDisplay('message_placeholder', __('Durée (minutes)', __FILE__));
		$consigneset->setSubType('message');
		$consigneset->setEqLogic_id($this->getId());
		$consigneset->save();

		$consignesetmobile = $this->getCmd(null, 'consignemobile');
		if (!is_object($consignesetmobile)) {
			$consignesetmobile = new netatmoThermostatcmd();
			$consignesetmobile->setLogicalId('consignemobile');
			$consignesetmobile->setIsVisible(1);
			$consignesetmobile->setName(__('Consigne pour appmobile', __FILE__));
		}
		$consignesetmobile->setType('action');
		$consignesetmobile->setSubType('slider');
		$consignesetmobile->setEqLogic_id($this->getId());
		$consignesetmobile->setDisplay('generic_type', 'THERMOSTAT_SET_SETPOINT');
		$consignesetmobile->save();

		$calendarset = $this->getCmd(null, 'calendarset');
		if (!is_object($calendarset)) {
			$calendarset = new netatmoThermostatcmd();
			$calendarset->setLogicalId('calendarset');
			$calendarset->setIsVisible(1);
			$calendarset->setName(__('Réglage Calendrier', __FILE__));
		}
		$calendarset->setType('action');
		$calendarset->setDisplay('title_disable', 1);
		$calendarset->setDisplay('message_placeholder', __('Id Calendrier', __FILE__));
		$calendarset->setSubType('message');
		$calendarset->setEqLogic_id($this->getId());
		$calendarset->save();

		$dureeset = $this->getCmd(null, 'dureeset');
		if (!is_object($dureeset)) {
			$dureeset = new netatmoThermostatcmd();
			$dureeset->setLogicalId('dureeset');
			$dureeset->setIsVisible(1);
			$dureeset->setName(__('Réglage Fin', __FILE__));
		}
		$dureeset->setType('action');
		$dureeset->setDisplay('title_disable', 1);
		$dureeset->setDisplay('message_placeholder', __('Timestamp de fin', __FILE__));
		$dureeset->setSubType('message');
		$dureeset->setEqLogic_id($this->getId());
		$dureeset->save();
	}

	public function toHtml($_version = 'dashboard') {
		$replace = $this->preToHtml($_version);
		if (!is_array($replace)) {
			return $replace;
		}
		$version = jeedom::versionAlias($_version);
		if ($this->getDisplay('hideOn' . $version) == 1) {
			return '';
		}
		$replace['#endtime#'] = $this->getCmd(null, 'endsetpoint')->execCmd();
		foreach ($this->getCmd('info') as $cmd) {
			$replace['#' . $cmd->getLogicalId() . '#'] = $cmd->execCmd();
			$replace['#' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
			if ($cmd->getIsHistorized() == 1) {
				$replace['#' . $cmd->getLogicalId() . '_history#'] = 'history cursor';
			}
		}
		$actualcalendar = $this->getCmd(null, 'calendar')->execCmd();
		$calendar_list_cmd = $this->getCmd(null, 'listcalendar');
		$calendar_string = $calendar_list_cmd->execCmd();
		$calendar_list = explode('|', $calendar_string);
		foreach ($calendar_list as $calendar) {
			$detail_calendar = explode(';', $calendar);
			if ($actualcalendar == $detail_calendar[0]) {
				$valuecalendar = '"' . $detail_calendar[1] . '" selected';
			} else {
				$valuecalendar = '"' . $detail_calendar[1] . '"';
			}
			if (!isset($replace['#calendar_selector#'])) {
				$replace['#calendar_selector#'] = '<option value=' . $valuecalendar . '>' . $detail_calendar[0] . '</option>';
			} else {
				$replace['#calendar_selector#'] .= '<option value=' . $valuecalendar . '>' . $detail_calendar[0] . '</option>';
			}
		}
		$refresh = $this->getCmd(null, 'refresh');
		$replace['#refresh_id#'] = $refresh->getId();

		$consigneset = $this->getCmd(null, 'consigneset');
		$replace['#thermostat_cmd_id#'] = $consigneset->getId();

		$away = $this->getCmd(null, 'away');
		$replace['#away_id#'] = $away->getId();

		$hg = $this->getCmd(null, 'hg');
		$replace['#hg_id#'] = $hg->getId();

		$program = $this->getCmd(null, 'program');
		$replace['#program_id#'] = $program->getId();

		$max = $this->getCmd(null, 'max');
		$replace['#max_id#'] = $max->getId();

		$off = $this->getCmd(null, 'off');
		$replace['#off_id#'] = $off->getId();

		$calendarset = $this->getCmd(null, 'calendarset');
		$replace['#calendar_change#'] = $calendarset->getId();

		$dureeset = $this->getCmd(null, 'dureeset');
		$replace['#endtime_change#'] = $dureeset->getId();

		$anticipation = $this->getCmd(null, 'anticipation')->execCmd();
		$replace['#anticipation#'] = $anticipation;

		$html = template_replace($replace, getTemplate('core', $version, 'eqLogic', 'netatmoThermostat'));
		cache::set('widgetHtml' . $version . $this->getId(), $html, 0);
		return $html;
	}

}

class netatmoThermostatCmd extends cmd {
	/*	 * *************************Attributs****************************** */

	/*	 * ***********************Methode static*************************** */

	/*	 * *********************Methode d'instance************************* */

	public function execute($_options = array()) {
		if ($this->getType() == '') {
			return '';
		}
		$eqLogic = $this->getEqlogic();
		$action = $this->getLogicalId();
		if ($action == 'refresh') {
			$eqLogic->syncWithTherm($eqLogic->getLogicalId());
		} elseif ($action == 'away' || $action == 'hg' || $action == 'max') {
			$time = '';
			if (isset($_options['message'])) {
				$time = $_options['message'];
			}
			if ($time == '' && $action != 'max') {
				$eqLogic->changemodeTherm($eqLogic->getLogicalId(), $action);
			} else if ($time == '' && $action == 'max') {
				$defaultime = $eqLogic->getConfiguration('maxdefault');
				if ($defaultime == null || $defaultime == '') {
					$defaultime = 60;
				}
				$endtime = time() + ($defaultime * 60);
				$eqLogic->changemodeTherm($eqLogic->getLogicalId(), $action, $endtime);
			} else {
				$endtime = time() + ($time * 60);
				$eqLogic->changemodeTherm($eqLogic->getLogicalId(), $action, $endtime);
			}
		} elseif ($action == 'off' || $action == 'program') {
			$eqLogic->changemodeTherm($eqLogic->getLogicalId(), $action);
		} elseif ($action == 'consigneset') {
			$temperatureset = $_options['title'];
			$time = '';
			if (isset($_options['message'])) {
				$time = $_options['message'];
			}
			if ($time == '') {
				$eqLogic->changesetpointTherm($eqLogic->getLogicalId(), $temperatureset);
			} else {
				$endtime = time() + ($time * 60);
				$eqLogic->changesetpointTherm($eqLogic->getLogicalId(), $temperatureset, $endtime);
			}
		} elseif ($action == 'awaymobile' || $action == 'hgmobile' || $action == 'maxmobile') {
			if ($action == 'maxmobile') {
				$defaultime = $eqLogic->getConfiguration('maxdefault');
				if ($defaultime == null || $defaultime == '') {
					$defaultime = 60;
				}
				$endtime = time() + ($defaultime * 60);
				$eqLogic->changemodeTherm($eqLogic->getLogicalId(), str_replace('mobile', '', $action), $endtime);
			} else {
				$eqLogic->changemodeTherm($eqLogic->getLogicalId(), str_replace('mobile', '', $action));
			}
		} elseif ($action == 'consignemobile') {
			$temperatureset = $_options['slider'];
			$eqLogic->changesetpointTherm($eqLogic->getLogicalId(), $temperatureset);
		} elseif ($action == 'dureeset') {
			$dureeset = $_options['message'];
			$timestamp = strtotime($dureeset);
			$mode = $eqLogic->getCmd(null, 'modetech')->execCmd();
			switch ($mode) {
				case 'away':
					$eqLogic->changemodeTherm($eqLogic->getLogicalId(), $mode, $timestamp);
					break;
				case 'hg':
					$eqLogic->changemodeTherm($eqLogic->getLogicalId(), $mode, $timestamp);
					break;
				case 'manual':
					$temperatureset = $eqLogic->getCmd(null, 'consigne')->execCmd();
					$eqLogic->changesetpointTherm($eqLogic->getLogicalId(), $temperatureset, $timestamp);
					break;
				case 'max':
					$eqLogic->changemodeTherm($eqLogic->getLogicalId(), $mode, $timestamp);
					break;
				default:
					log::add('netatmoThermostat', 'debug', 'Vous n’êtes pas dans un mode pour lequel une durée peut être définie');
					break;
			}
		} elseif ($action == 'calendarset') {
			$scheduleid = $_options['message'];
			$eqLogic->changescheduleTherm($eqLogic->getLogicalId(), $scheduleid);
		}
	}

	/*	 * **********************Getteur Setteur*************************** */
}

?>
