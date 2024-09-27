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

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * @since 0.1.0
 */
class PluginFlyvemdmMqttuser extends CommonDBTM {

   private $pbdkf2Settings = [
      'algorithm'  => 'sha256',
      'iterations' => 901,
      'saltSize'   => 12,
      'keyLength'  => 48,
   ];

   /**
    * @see CommonDBTM::prepareInputForAdd()
    */
   public function prepareInputForAdd($input) {
      if (isset($input['password'])) {
         $input['password'] = $this->hashPassword($input['password']);
      }

      if (!isset($input['_reset_acl'])) {
         $input['_reset_acl'] = false;
      }

      return $input;
   }

   /**
    * @see CommonDBTM::prepareInputForUpdate()
    */
   public function prepareInputForUpdate($input) {
      if (isset($input['password'])) {
         $input['password'] = $this->hashPassword($input['password']);
      }

      if (!isset($input['_reset_acl'])) {
         $input['_reset_acl'] = false;
      }

      return $input;
   }

   /**
    * @see CommonDBTM::post_addItem()
    */
   public function post_addItem() {
      if ($this->input['_reset_acl'] === true) {
         $mqttAcl = new PluginFlyvemdmMqttacl();
         $mqttAcl->removeAllForUser($this);
      }
      if (!isset($this->input['_acl']) || !is_array($this->input['_acl'])) {
         return;
      }
      foreach ($this->input['_acl'] as $acl) {
         if (!isset($acl['topic']) || !isset($acl['access_level'])) {
            continue;
         }
         $mqttAcl = new PluginFlyvemdmMqttacl();
         $mqttAcl->add([
            'plugin_flyvemdm_mqttusers_id' => $this->fields['id'],
            'topic'                        => $acl['topic'],
            'access_level'                 => $acl['access_level'],
         ]);
      }
   }

   /**
    * @see CommonDBTM::post_updateItem()
    */
   public function post_updateItem($history = 1) {
      if ($this->input['_reset_acl'] === true) {
         $mqttAcl = new PluginFlyvemdmMqttacl();
         $mqttAcl->removeAllForUser($this);
      }
      if (!isset($this->input['_acl']) || !is_array($this->input['_acl'])) {
         return;
      }
      foreach ($this->input['_acl'] as $acl) {
         if (!isset($acl['topic']) || !isset($acl['access_level'])) {
            continue;
         }
         $mqttAcl = new PluginFlyvemdmMqttacl();
         $mqttAcl->add([
            'plugin_flyvemdm_mqttusers_id' => $this->fields['id'],
            'topic'                        => $acl['topic'],
            'access_level'                 => $acl['access_level'],
         ]);
      }
   }


   /**
    * Hash a password
    * @param string $clearPassword
    * @return string PBKDF2 hashed password
    */
   public function hashPassword($clearPassword) {
      // These parameters may be added to the function as a future improvement
      $algorithm = $this->pbdkf2Settings['algorithm'];
      $keyLength = $this->pbdkf2Settings['keyLength'];
      $salt = base64_encode(openssl_random_pseudo_bytes($this->pbdkf2Settings['saltSize']));
      $iterations = $this->pbdkf2Settings['iterations'];

      $hashed = hash_pbkdf2('sha256', $clearPassword, $salt, $iterations, $keyLength, true);

      return implode(
         '$',
         [
            'PBKDF2',
            $algorithm,
            $iterations,
            $salt,
            base64_encode($hashed)
         ]
      );
   }

   /**
    * Challenge a password against tha hashed password stored in database
    *
    * @param string $challenged
    *
    * @return boolean true if the password matches the hashed password
    */
   public function comparePasswords($challenged) {
      if ($this->isNewItem()) {
         return false;
      }

      $pbdkf2 = explode('$', $this->fields['password']);
      $algorithm = $pbdkf2[1];
      $salt = $pbdkf2[3];
      $iterations = $pbdkf2[2];
      $keyLength = strlen(base64_decode($pbdkf2[4]));
      $hashed = hash_pbkdf2($algorithm, $challenged, $salt, $iterations, $keyLength, true);
      return $hashed === base64_decode($pbdkf2[4]);
   }

   /**
    * Generate a random password havind a determined set of chars
    * @see http://stackoverflow.com/a/31284266
    *
    * @param integer $length password length to generate
    * @param string $keyspace characters available to build the password
    *
    * @return string
    * @throws Exception
    */
   public static function getRandomPassword($length = 0, $keyspace = '') {
      if ($length == 0) {
         $length = '32';
      }

      if ($keyspace == '') {
         $keyspace = '0123456789';
         $keyspace = $keyspace . 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
         //$keyspace = $keyspace . '&#{[|]}@^_*%<>.,;:!';
      }

      $password = '';
      $max = mb_strlen($keyspace, '8bit') - 1;
      if ($max < 1) {
         throw new Exception('$keyspace must be at least two characters long');
      }

      $randomIntExists = function_exists('random_int');
      for ($i = 0; $i < $length; $i++) {
         // random_int needs PHP 7, not yet widely used
         if ($randomIntExists) {
            $password .= $keyspace[random_int(0, $max)];
         } else {
            $password .= $keyspace[mt_rand(0, $max)];
         }
      }

      return $password;
   }

   /**
    * @see CommonDBTM::post_purgeItem()
    */
   public function post_purgeItem() {
      $mqttAcl = new PluginFlyvemdmMqttacl();
      $mqttAcl->deleteByCriteria([
         'plugin_flyvemdm_mqttusers_id' => $this->getID(),
      ]);
   }

   /**
    * Retrieve a mqtt user by name
    * @param string $user
    * @return bool
    */
   public function getByUser($user) {
      global $DB;

      $user = $DB->escape($user);
      return $this->getFromDBByCrit(['user' => $user]);
   }

   /**
    * Returns an array of PluginFlyvemdmMqttACL for the user
    *
    * @return PluginFlyvemdmMqttacl[]
    */
   public function getACLs() {
      if ($this->isNewItem()) {
         return [];
      }

      $aclList = [];
      $mqttAcl = new PluginFlyvemdmMqttacl();
      $userId = $this->fields['id'];
      if (version_compare(GLPI_VERSION, '9.4') < 0) {
         $condition = "`plugin_flyvemdm_mqttusers_id` = '$userId'";
      } else {
         $condition = ['plugin_flyvemdm_mqttusers_id' => $userId];
      }
      $rows = $mqttAcl->find($condition);
      foreach ($rows as $row) {
         $mqttAcl = new PluginFlyvemdmMqttacl();
         $mqttAcl->getFromDB($row['id']);
         if (!$mqttAcl->isNewItem()) {
            $aclList[] = $mqttAcl;
         }
      }

      return $aclList;
   }
}
