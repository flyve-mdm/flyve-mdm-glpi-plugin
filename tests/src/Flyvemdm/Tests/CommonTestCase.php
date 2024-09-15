<?php
/**
 * LICENSE
 *
 * Copyright © 2016-2018 Teclib'
 * Copyright © 2010-2018 by the FusionInventory Development Team.
 *
 * This file is part of Flyve MDM Plugin for GLPI.
 *
 * Flyve MDM Plugin for GLPI is a subproject of Flyve MDM. Flyve MDM is a mobile
 * device management software.
 *
 * Flyve MDM Plugin for GLPI is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * Flyve MDM Plugin for GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License
 * along with Flyve MDM Plugin for GLPI. If not, see http://www.gnu.org/licenses/.
 * ------------------------------------------------------------------------------
 * @copyright Copyright © 2018 Teclib
 * @license   https://www.gnu.org/licenses/agpl.txt AGPLv3+
 * @link      https://github.com/flyve-mdm/glpi-plugin
 * @link      https://flyve-mdm.com/
 * ------------------------------------------------------------------------------
 */

namespace Flyvemdm\Tests;

use Glpi\Tests\CommonTestCase as GlpiCommonTestCase;

class CommonTestCase extends GlpiCommonTestCase {

   /**
    * Try to enroll an device by creating an agent. If the enrollment fails
    * the agent returned will not contain an ID. To ensore the enrollment succeeded
    * use isNewItem() method on the returned object.
    *
    * @param integer $userId
    * @param array $input enrollment data for agent creation
    * @param boolean $keepLoggedUser allow change of user for testing
    * @return \PluginFlyvemdmAgent
    */
   protected function enrollFromInvitation($userId, array $input, $keepLoggedUser = true) {
      // Close current session
      $currentUser = $_SESSION['glpiname'];

      // login as invited user
      $_REQUEST['user_token'] = \User::getToken($userId, 'api_token');
      //$this->dump($_REQUEST['user_token']);
      $this->boolean($this->login('', '', false))->isTrue();
      $config = \Config::getConfigurationValues('flyvemdm', ['guest_profiles_id']);
      $guestProfileId = $config['guest_profiles_id'];
      \Session::changeProfile($guestProfileId);
      //$this->dump($_SESSION['glpiactiveprofile']['profile']);
      unset($_REQUEST['user_token']);

      // Try to enroll
      $agent = new \PluginFlyvemdmAgent();
      $agent->add($input);
      if (!$agent->isNewItem()) {
         // item has been created but its data is incomplete so let's load it
         $agent->getFromDB($agent->getID());
      }

      if ($keepLoggedUser && $currentUser) {
         // Go back to previous logged user
         $this->boolean($this->login($currentUser, $currentUser))->isTrue();
      }

      return $agent;
   }

   /**
    * Create a user having with the flyve mddm guest profile
    *
    * @param array $input input data as expected by \User::add()
    */
   protected function createGuestUser($input) {
      $entityId = isset($input['entities_id']) ? $input['entities_id'] : 0;
      $config = \Config::getConfigurationValues('flyvemdm', ['guest_profiles_id']);
      $guestProfileId = $config['guest_profiles_id'];
      $input['_entities_id'] = $entityId;
      $input['profiles_id'] = $guestProfileId;
      $user = new \User();
      $user->add($input);
      return $user;
   }

   /**
    * Create a new invitation
    *
    * @param string $guestEmail
    * @return \PluginFlyvemdmInvitation
    */
   protected function createInvitation($guestEmail) {
      $user = $this->createGuestUser([
         '_useremails' => [
            $guestEmail,
         ],
         'name' => $guestEmail,
         'authtype' => \Auth::DB_GLPI,
         'entities_id' => $_SESSION['glpiactive_entity'],
      ]);
      $invitation = new \PluginFlyvemdmInvitation();
      $invitation->add([
         'entities_id' => $_SESSION['glpiactive_entity'],
         'users_id' => $user->getID(),
      ]);
      $this->boolean($invitation->isNewItem())->isFalse();
      if (!$invitation->isNewItem()) {
         // item has been created but its data is incomplete so let's load it
         $invitation->getFromDB($invitation->getID());
      }
      return $invitation;
   }

   /**
    * @param string $userIdField
    * @return array
    */
   protected function createUserInvitation($userIdField) {
      // Create an invitation
      $serial = $this->getUniqueString();
      $guestEmail = $this->getUniqueEmail();
      $invitation = $this->createInvitation($guestEmail);
      $user = new \User();
      if (!$invitation->isNewItem()) {
         // item has been created but its data is incomplete so let's load it
         $user->getFromDB($invitation->getField($userIdField));
      }

      return [$user, $serial, $guestEmail, $invitation];
   }

   /**
    * @param \User $user object
    * @param string $guestEmail
    * @param string|null $serial if null the value is not used
    * @param string $invitationToken
    * @param array $defaults
    * @param array $customInput
    * @param boolean $keepSession
    * @return \PluginFlyvemdmAgent
    */
   protected function agentFromInvitation(
      \User $user,
      $guestEmail,
      $serial,
      $invitationToken,
      array $defaults = [],
      array $customInput = [],
      $keepSession = false
   ) {

      if (!key_exists('notificationType', $defaults)) {
         $defaults['notificationType'] = 'mqtt';
      }
      if (!key_exists('notificationToken', $defaults)) {
         $defaults['notificationToken'] = '';
      }

      // Default values for BC
      if (!key_exists('mdmType', $defaults)) {
         $defaults['mdmType'] = 'android';
      }
      if (!key_exists('version', $defaults)) {
         $defaults['version'] = '';
      }
      if (!key_exists('inventory', $defaults)) {
         $defaults['inventory'] = null;
      }

      //Version change
      $finalVersion = \PluginFlyvemdmAgent::MINIMUM_ANDROID_VERSION . '.0';
      if ($defaults['version']) {
         $finalVersion = $defaults['version'];
      }
      if (null === $defaults['version']) {
         $finalVersion = null;
      }

      $finalInventory = (null !== $defaults['inventory']) ? $defaults['inventory']: self::AgentXmlInventory($serial);

      $input = [
         'entities_id'        => $_SESSION['glpiactive_entity'],
         '_email'             => $guestEmail,
         '_invitation_token'  => $invitationToken,
         'csr'                => '',
         'firstname'          => 'John',
         'lastname'           => 'Doe',
         'type'               => $defaults['mdmType'],
         'inventory'          => $finalInventory,
         'notification_type'  => $defaults['notificationType'],
         'notification_token' => $defaults['notificationToken'],
      ];

      if ($serial) {
         $input['_serial'] = $serial;
      }
      if ($finalVersion) {
         $input['version'] = $finalVersion;
      }

      return $this->enrollFromInvitation($user->getID(), array_merge($input, $customInput), $keepSession);
   }

   /**
    * @param array $input input data
    * @return \PluginFlyvemdmFleet
    */
   protected function createFleet($input) {
      $fleet = $this->newMockInstance(\PluginFlyvemdmFleet::class, '\MyMock');
      $fleet->getMockController()->post_addItem = function () {};
      $fleetId = $fleet->add($input);
      $this->boolean($fleet->isNewItem())->isFalse();

      $fleet = new \PluginFlyvemdmFleet();
      $fleet->getFromDB($fleetId);

      return $fleet;
   }

   /**
    * @param array $input input data
    * @param boolean $noPostActions
    * @return \PluginFlyvemdmTask
    */
   protected function createTask($input, $noPostActions = true) {
      $task = $this->newMockInstance(\PluginFlyvemdmTask::class, '\MyMock');
      if ($noPostActions) {
         $task->getMockController()->post_addItem = function () {};
         $task->getMockController()->post_updateItem = function () {};
      }
      $taskId = $task->add($input);
      $this->boolean($task->isNewItem())->isFalse();

      $task = new \PluginFlyvemdmTask();
      $task->getFromDB($taskId);

      return $task;
   }

   /**
    * @param string $policySymbol
    * @param boolean $noPostActions
    * @return array
    */
   protected function createFleetAndTask($policySymbol = 'storageEncryption', $noPostActions = true) {
      $policy = new \PluginFlyvemdmPolicy();
      $policy->getFromDbBySymbol($policySymbol);
      $fleet = $this->createFleet(['name' => $this->getUniqueString()]);
      $task = $this->createTask([
         'value'                       => '0',
         'plugin_flyvemdm_policies_id' => $policy->getID(),
         'itemtype_applied'            => \PluginFlyvemdmFleet::class,
         'items_id_applied'            => $fleet->getID(),
         'itemtype'                    => '',
         'items_id'                    => '',
      ], $noPostActions);
      return [$fleet, $task, $policy];
   }

   /**
    * @return array
    */
   protected function createAgentTaskstatus() {
      list($fleet, $task) = $this->createFleetAndTask('storageEncryption', false);
      $agent = $this->createAgent();
      $agent->update([
         'id' => $agent->getID(),
         \PluginFlyvemdmFleet::getForeignKeyField() => $fleet->getID(),
         ]);
      $taskStatus = new \PluginFlyvemdmTaskStatus;
      $taskStatus->getFromDBByCrit([
         \PluginFlyvemdmAgent::getForeignKeyField() => $agent->getID(),
         \PluginFlyvemdmTask::getForeignKeyField()  => $task->getID(),
      ]);
      return [$taskStatus, $fleet, $task];
   }

   /**
    * Create a new enrolled agent in the database
    *
    * @param array $input
    * @return \PluginFlyvemdmAgent
    */
   public function createAgent(array $input = []) {
      list($user, $serial, $guestEmail, $invitation) = $this->createUserInvitation(\User::getForeignKeyField());
      $invitationToken = $invitation->getField('invitation_token');
      $agent = $this->agentFromInvitation($user, $guestEmail, $serial, $invitationToken, [], $input, true);
      return $agent;
   }

   /**
    * Create a file directly on DB and return the object and the filename generated
    *
    * @param $entityId
    * @param null $userFilename
    * @param int $version
    * @return \PluginFlyvemdmFile
    */
   function createDummyFile($entityId, $userFilename = null, $version = 1) {
      global $DB;

      // Create an file (directly in DB)
      $fileName = ((null !== $userFilename) ? $userFilename : $this->getUniqueString());
      $destination = $entityId . '/dummy_file_' . $fileName;
      if (!is_dir($directory = FLYVEMDM_FILE_PATH . "/" . $entityId)) {
         @mkdir($directory);
      }
      $fileSize = file_put_contents(FLYVEMDM_FILE_PATH . '/' . $destination, 'dummy');
      $this->integer($fileSize)->isGreaterThan(0);
      $fileTable = \PluginFlyvemdmFile::getTable();
      $query = "INSERT INTO $fileTable (
         `name`,
         `source`,
         `entities_id`,
         `version`
      ) VALUES (
         '$fileName',
         '$destination',
         '$entityId',
         '$version'
      )";

      $DB->query($query);
      $mysqlError = $DB->error();
      $flyvemdmFile = new \PluginFlyvemdmFile();
      $flyvemdmFile->getFromDBByCrit(['name' => $fileName]);
      $this->boolean($flyvemdmFile->isNewItem())->isFalse($mysqlError);
      return $flyvemdmFile;
   }

   /**
    * Fake XML data for inventory
    * @param $serial
    * @param string $macAddress
    * @param string $deviceId
    * @param string $uuid
    * @return string
    */
   public static function AgentXmlInventory($serial, $macAddress = '', $deviceId = '', $uuid = '') {
      $uuid = ($uuid) ? $uuid : '1d24931052f35d92';
      $macAddress = ($macAddress) ? $macAddress : '02:00:00:00:00:00';
      $deviceId = ($deviceId) ? $deviceId : $serial . "_" . $macAddress;
      $insalldate = mt_rand(1, time());
      $xml = "<?xml version='1.0' encoding='utf-8' standalone='yes'?>
            <REQUEST>
              <QUERY>INVENTORY</QUERY>
              <VERSIONCLIENT>FlyveMDM-Agent_v1.0</VERSIONCLIENT>
              <DEVICEID>" . $deviceId . "</DEVICEID>
              <CONTENT>
                <ACCESSLOG>
                  <LOGDATE>" . date("Y-m-d H:i:s") . "</LOGDATE>
                  <USERID>N/A</USERID>
                </ACCESSLOG>
                <ACCOUNTINFO>
                  <KEYNAME>TAG</KEYNAME>
                  <KEYVALUE/>
                </ACCOUNTINFO>
                <HARDWARE>
                  <DATELASTLOGGEDUSER>09/11/17</DATELASTLOGGEDUSER>
                  <LASTLOGGEDUSER>jenkins</LASTLOGGEDUSER>
                  <NAME>" . $serial . "</NAME>
                  <OSNAME>Android</OSNAME>
                  <OSVERSION>6.0</OSVERSION>
                  <ARCHNAME>aarch64</ARCHNAME>
                  <UUID>" . $uuid . "</UUID>
                  <MEMORY>1961</MEMORY>
                </HARDWARE>
                <BIOS>
                  <BDATE>09/11/17</BDATE>
                  <BMANUFACTURER>bq</BMANUFACTURER>
                  <MMANUFACTURER>bq</MMANUFACTURER>
                  <SMODEL>Aquaris M10 FHD</SMODEL>
                  <SSN>" . $serial . "</SSN>
                </BIOS>
                <MEMORIES>
                  <DESCRIPTION>Memory</DESCRIPTION>
                  <CAPACITY>1961</CAPACITY>
                </MEMORIES>
                <INPUTS>
                  <CAPTION>Touch Screen</CAPTION>
                  <DESCRIPTION>Touch Screen</DESCRIPTION>
                  <TYPE>FINGER</TYPE>
                </INPUTS>
                <SENSORS>
                  <NAME>ACCELEROMETER</NAME>
                  <NAME>MTK</NAME>
                  <TYPE>ACCELEROMETER</TYPE>
                  <POWER>0.13</POWER>
                  <VERSION>3</VERSION>
                </SENSORS>
                <SENSORS>
                  <NAME>LIGHT</NAME>
                  <NAME>MTK</NAME>
                  <TYPE>Unknow</TYPE>
                  <POWER>0.13</POWER>
                  <VERSION>1</VERSION>
                </SENSORS>
                <SENSORS>
                  <NAME>ORIENTATION</NAME>
                  <NAME>MTK</NAME>
                  <TYPE>Unknow</TYPE>
                  <POWER>0.25</POWER>
                  <VERSION>3</VERSION>
                </SENSORS>
                <SENSORS>
                  <NAME>MAGNETOMETER</NAME>
                  <NAME>MTK</NAME>
                  <TYPE>MAGNETIC FIELD</TYPE>
                  <POWER>0.25</POWER>
                  <VERSION>3</VERSION>
                </SENSORS>
                <DRIVES>
                  <VOLUMN>/system</VOLUMN>
                  <TOTAL>1487</TOTAL>
                  <FREE>72</FREE>
                </DRIVES>
                <DRIVES>
                  <VOLUMN>/storage/emulated/0</VOLUMN>
                  <TOTAL>12529</TOTAL>
                  <FREE>8322</FREE>
                </DRIVES>
                <DRIVES>
                  <VOLUMN>/data</VOLUMN>
                  <TOTAL>12529</TOTAL>
                  <FREE>8322</FREE>
                </DRIVES>
                <DRIVES>
                  <VOLUMN>/cache</VOLUMN>
                  <TOTAL>410</TOTAL>
                  <FREE>410</FREE>
                </DRIVES>
                <CPUS>
                  <NAME>AArch64 Processor rev 3 (aarch64)</NAME>
                  <SPEED>1500</SPEED>
                </CPUS>
                <SIMCARDS>
                  <STATE>SIM_STATE_UNKNOWN</STATE>
                </SIMCARDS>
                <VIDEOS>
                  <RESOLUTION>1920x1128</RESOLUTION>
                </VIDEOS>
                <CAMERAS>
                  <RESOLUTIONS>3264x2448</RESOLUTIONS>
                </CAMERAS>
                <CAMERAS>
                  <RESOLUTIONS>2880x1728</RESOLUTIONS>
                </CAMERAS>
                <NETWORKS>
                  <TYPE>WIFI</TYPE>
                  <MACADDR>" . $macAddress . "</MACADDR>
                  <SPEED>65</SPEED>
                  <BSSID>aa:5b:78:78:52:7e</BSSID>
                  <SSID>aa:5b:78:78:52:7e</SSID>
                  <IPGATEWAY>172.20.10.1</IPGATEWAY>
                  <IPADDRESS>172.20.10.3</IPADDRESS>
                  <IPMASK>0.0.0.0</IPMASK>
                  <IPDHCP>172.20.10.1</IPDHCP>
                </NETWORKS>
                <ENVS>
                  <KEY>SYSTEMSERVERCLASSPATH</KEY>
                  <VAL>/system/framework/services.jar:/system/framework/ethernet-service.jar:/system/framework/wifi-service.jar</VAL>
                </ENVS>
                <ENVS>
                  <KEY>ANDROID_SOCKET_zygote</KEY>
                  <VAL>11</VAL>
                </ENVS>
                <ENVS>
                  <KEY>ANDROID_DATA</KEY>
                  <VAL>/data</VAL>
                </ENVS>
                <ENVS>
                  <KEY>PATH</KEY>
                  <VAL>/sbin:/vendor/bin:/system/sbin:/system/bin:/system/xbin</VAL>
                </ENVS>
                <ENVS>
                  <KEY>ANDROID_ASSETS</KEY>
                  <VAL>/system/app</VAL>
                </ENVS>
                <ENVS>
                  <KEY>ANDROID_ROOT</KEY>
                  <VAL>/system</VAL>
                </ENVS>
                <ENVS>
                  <KEY>ASEC_MOUNTPOINT</KEY>
                  <VAL>/mnt/asec</VAL>
                </ENVS>
                <ENVS>
                  <KEY>LD_PRELOAD</KEY>
                  <VAL>libdirect-coredump.so</VAL>
                </ENVS>
                <ENVS>
                  <KEY>ANDROID_BOOTLOGO</KEY>
                  <VAL>1</VAL>
                </ENVS>
                <ENVS>
                  <KEY>BOOTCLASSPATH</KEY>
                  <VAL>/system/framework/core-libart.jar:/system/framework/conscrypt.jar:/system/framework/okhttp.jar:/system/framework/core-junit.jar:/system/framework/bouncycastle.jar:/system/framework/ext.jar:/system/framework/framework.jar:/system/framework/telephony-common.jar:/system/framework/voip-common.jar:/system/framework/ims-common.jar:/system/framework/apache-xml.jar:/system/framework/org.apache.http.legacy.boot.jar:/system/framework/mediatek-common.jar:/system/framework/mediatek-framework.jar:/system/framework/mediatek-telephony-common.jar:/system/framework/dolby_ds2.jar:/system/framework/dolby_ds1.jar</VAL>
                </ENVS>
                <ENVS>
                  <KEY>ANDROID_PROPERTY_WORKSPACE</KEY>
                  <VAL>9,0</VAL>
                </ENVS>
                <ENVS>
                  <KEY>EXTERNAL_STORAGE</KEY>
                  <VAL>/sdcard</VAL>
                </ENVS>
                <ENVS>
                  <KEY>ANDROID_STORAGE</KEY>
                  <VAL>/storage</VAL>
                </ENVS>
                <JVMS>
                  <NAME>Dalvik</NAME>
                  <LANGUAGE>en_GB</LANGUAGE>
                  <VENDOR>The Android Project</VENDOR>
                  <RUNTIME>0.9</RUNTIME>
                  <HOME>/system</HOME>
                  <VERSION>2.1.0</VERSION>
                  <CLASSPATH>.</CLASSPATH>
                </JVMS>
                <BATTERIES>
                  <CHEMISTRY>Li-ion</CHEMISTRY>
                  <TEMPERATURE>23.0c</TEMPERATURE>
                  <VOLTAGE>3.745V</VOLTAGE>
                  <LEVEL>60%</LEVEL>
                  <HEALTH>Good</HEALTH>
                  <STATUS>Not charging</STATUS>
                </BATTERIES>
                <SOFTWARES>
                  <NAME><![CDATA[Bluetooth Pairing Utility]]></NAME>
                  <COMMENTS>com.symbol.btapp</COMMENTS>
                  <VERSION>3.10</VERSION>
                  <FILESIZE>0</FILESIZE>
                  <FROM>Android</FROM>
                  <INSTALLDATE>" . $insalldate . "</INSTALLDATE>
                </SOFTWARES>
                <SOFTWARES>
                  <NAME><![CDATA[com.android.cts.priv.ctsshim]]></NAME>
                  <COMMENTS>com.android.cts.priv.ctsshim</COMMENTS>
                  <VERSION>7.0-2996264</VERSION>
                  <FILESIZE>0</FILESIZE>
                  <FROM>Android</FROM>
                  <INSTALLDATE>" . $insalldate . "</INSTALLDATE>
                </SOFTWARES>
                <SOFTWARES>
                  <NAME><![CDATA[YouTube]]></NAME>
                  <COMMENTS>com.google.android.youtube</COMMENTS>
                  <VERSION>12.43.52</VERSION>
                  <FILESIZE>0</FILESIZE>
                  <FROM>Android</FROM>
                  <INSTALLDATE>" . $insalldate . "</INSTALLDATE>
                </SOFTWARES>
                <SOFTWARES>
                  <NAME><![CDATA[SampleExtAuthService]]></NAME>
                  <COMMENTS>com.qualcomm.qti.auth.sampleextauthservice</COMMENTS>
                  <VERSION>1.0</VERSION>
                  <FILESIZE>0</FILESIZE>
                  <FROM>Android</FROM>
                  <INSTALLDATE>" . $insalldate . "</INSTALLDATE>
                </SOFTWARES>
                <SOFTWARES>
                  <NAME><![CDATA[Kingdoms &amp; Lords]]></NAME>
                  <COMMENTS>com.gameloft.android.GloftKLMF</COMMENTS>
                  <VERSION>1.0.0</VERSION>
                  <FILESIZE>0</FILESIZE>
                  <FROM>Android</FROM>
                  <INSTALLDATE>" . $insalldate . "</INSTALLDATE>
                </SOFTWARES>
              </CONTENT>
            </REQUEST>";
      return base64_encode($xml);
   }

   /**
    * Create an application (directly in DB) because we are not uploading any file
    * @param $entityId
    * @param string|null $filename
    * @param string $version
    * @return \PluginFlyvemdmPackage
    */
   protected function createDummyPackage($entityId, $filename = null, $version = '1.0.5') {
      global $DB;

      // Create an file (directly in DB)
      $apk = $this->createDummyApkFile($entityId, $filename, false);
      $packageTable = \PluginFlyvemdmPackage::getTable();
      $query = "INSERT INTO $packageTable (
         `package_name`,
         `alias`,
         `version`,
         `filename`,
         `entities_id`,
         `dl_filename`,
         `icon`
      ) VALUES (
         '" . $apk['package_name'] . "',
         'application',
         '$version',
         '" . $apk['destination'] . "',
         '$entityId',
         '" . $apk['filename'] . "',
         ''
      )";

      $DB->query($query);
      $mysqlError = $DB->error();
      $flyvemdmPackage = new \PluginFlyvemdmPackage();
      $flyvemdmPackage->getFromDBByCrit(['package_name' => $apk['package_name']]);
      $this->boolean($flyvemdmPackage->isNewItem())->isFalse($mysqlError);
      return $flyvemdmPackage;
   }

   /**
    * Creeate a physical dummy APK file for tests
    *
    * @param integer $entityId
    * @param string|null $filename
    * @param boolean $temp set the file on glpi temp or package folder
    * @return array
    */
   protected function createDummyApkFile($entityId = 0, $filename = null, $temp = true) {
      $uniqueString = ((null !== $filename) ? $filename : $this->getUniqueString());
      $dumbPackageName = 'com.domain.' . $uniqueString . '.application';
      $filename = '123456789_application_' . $uniqueString . '.apk';
      $destinationFolder = ($temp) ? '' : 'flyvemdm/package/' . $entityId;
      $rootFolder = ($temp) ? GLPI_TMP_DIR : GLPI_PLUGIN_DOC_DIR;
      $destination = $destinationFolder . '/' . $filename;
      if (!is_dir($directory = FLYVEMDM_PACKAGE_PATH . "/" . $entityId)) {
         @mkdir($directory);
      }
      $fileSize = file_put_contents($rootFolder . '/' . $destination, 'dummy');
      $this->integer($fileSize)->isGreaterThan(0);
      return [
         'uid'          => $uniqueString,
         'package_name' => $dumbPackageName,
         'destination'  => $destination,
         'filename'     => $filename,
      ];
   }

   /**
    * @return array
    */
   public static function commandList() {
      return [
         'Command/Subscribe',
         'Command/Ping',
         'Command/Geolocate',
         'Command/Inventory',
         'Command/Lock',
         'Command/Wipe',
         'Command/Unenroll',
      ];
   }

   /**
    * @return array
    */
   public static function policyList() {
      return [
         'Policy/passwordEnabled',
         'Policy/passwordMinLength',
         'Policy/passwordQuality',
         'Policy/passwordMinLetters',
         'Policy/passwordMinLowerCase',
         'Policy/passwordMinNonLetter',
         'Policy/passwordMinNumeric',
         'Policy/passwordMinSymbols',
         'Policy/passwordMinUpperCase',
         'Policy/maximumFailedPasswordsForWipe',
         'Policy/maximumTimeToLock',
         'Policy/storageEncryption',
         'Policy/disableCamera',
         'Policy/deployApp',
         'Policy/removeApp',
         'Policy/deployFile',
         'Policy/removeFile',
         'Policy/disableWifi',
         'Policy/disableBluetooth',
         'Policy/disableRoaming',
         'Policy/disableGPS',
         'Policy/disableUsbMtp',
         'Policy/disableUsbPtp',
         'Policy/disableUsbAdb',
         'Policy/disableMobileLine',
         'Policy/disableNfc',
         'Policy/disableHostpotTethering',
         'Policy/disableAirplaneMode',
         'Policy/disableStatusBar',
         'Policy/disableScreenCapture',
         'Policy/disableSpeakerphone',
         'Policy/disableUnknownAppSources',
         'Policy/disableStreamMusic',
         'Policy/disableStreamRing',
         'Policy/disableStreamAlarm',
         'Policy/disableStreamNotification',
         'Policy/disableStreamAccessibility',
         'Policy/disableStreamDTMF',
         'Policy/disableStreamSystem',
         'Policy/defaultStreamType',
         'Policy/periodicGeolocation',
         'Policy/apnConfiguration',
      ];
   }

   /**
    * @param \PluginFlyvemdmNotifiableInterface $item
    * @param \PluginFlyvemdmMqttlog $log
    * @param string $topic
    * @param mixed $mqttMessage
    * @return integer
    */
   protected function asserLastMqttlog(
      \PluginFlyvemdmNotifiableInterface $item,
      \PluginFlyvemdmMqttlog $log,
      $topic,
      $mqttMessage
   ) {
      if (version_compare(GLPI_VERSION, '9.4') < 0) {
         $logQuery = "itemtype='" . $item::getType() . "' AND `items_id`='" . $item->getID() . "' AND `topic`='" . $topic . "'";
         $order = '`id` DESC';
      } else {
         $logQuery = [
            'itemtype' => $item::getType(),
            'items_id' => $item->getID(),
            'topic' => $topic
         ];
         $order = [
            'id DESC'
         ];
      }
      $rows = $log->find($logQuery, $order, 1);
      $this->array($rows)->sizeOf($rows)->isGreaterThanOrEqualTo(1);
      foreach ($rows as $row) {
         // check the message
         $this->string($row['message'])->isEqualTo($mqttMessage);
         return (int)$row['id'];
      }
      return 0;
   }

   /**
    * @param $result
    * @param $message
    */
   protected function assertInvalidResult($result, $message) {
      $this->boolean($result)->isFalse();
      $this->string($_SESSION["MESSAGE_AFTER_REDIRECT"][0][0])->isEqualTo($message);
      unset($_SESSION["MESSAGE_AFTER_REDIRECT"]); // to clear the buffer
   }
}
