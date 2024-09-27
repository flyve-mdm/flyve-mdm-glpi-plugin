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
 * @author    Thierry Bugier
 * @copyright Copyright © 2018 Teclib
 * @license   AGPLv3+ http://www.gnu.org/licenses/agpl.txt
 * @link      https://github.com/flyve-mdm/glpi-plugin
 * @link      https://flyve-mdm.com/
 * ------------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class PluginFlyvemdmMosquittoAuth extends PluginFlyvemdmM2mAuth {
   public function authenticate($input) {
      if (!$this->checkRemote()) {
         return 403;
      }

      if (!isset($input['username']) || !isset($input['password'])) {
         // No credentials or credentials incomplete
         return 404;
      }

      $mqttUser = new PluginFlyvemdmMqttUser();
      if (!$mqttUser->getByUser($input['username'])) {
         return 404;
      }
      if ($mqttUser->getField('enabled') == '0') {
         return 404;
      }
      $input['password'] = Toolbox::stripslashes_deep($input['password']);
      if ($mqttUser->comparePasswords($input['password'])) {
         return 200;
      }

      return 404;
   }

   public function authorize($input) {
      $mqttUser = new PluginFlyvemdmMqttUser();
      if (!$mqttUser->getByUser($input['username'])) {
         return 403;
      }
      if ($mqttUser->getField('enabled') == '0') {
         return 403;
      }

      $mqttUserId = $mqttUser->getID();
      $acc = (int) $input['acc'];
      $requestedTopic = explode('/', $input['topic']);
      $mqttAcl = new PluginFlyvemdmMqttAcl();
      $rows = $mqttAcl->find("`plugin_flyvemdm_mqttusers_id`='$mqttUserId'
         AND `access_level` & $acc");
      foreach ($rows as $row) {
         $topic =  explode('/', $row['topic']);
         $match = true;
         foreach ($topic as $index => $pathItem) {
            if ($pathItem === '+') {
               // This path item matches a joker
               continue;
            }
            if ($pathItem === '#' && $index === count($topic) - 1) {
               continue;
            }
            if (!isset($requestedTopic[$index])) {
               $match = false;
               break;
            }
            if ($pathItem !== $requestedTopic[$index]) {
               // This topic does not match, try the next one
               $match = false;
               break;
            }
         }
         if ($match) {
            return 200;
         }
      }

      return 403;
   }

   public function isSuperuser($input) {
      return 403;
   }
}
