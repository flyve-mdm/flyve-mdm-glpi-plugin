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
 *
 * @since 0.1.0.33
 */
class PluginFlyvemdmPolicyFactory {

   /**
    * Create an empty policy by type
    *
    * @param PluginFlyvemdmPolicy $policyData
    * @return null|PluginFlyvemdmPolicyBase depending on the field type.
    */
   public function createFromPolicy(PluginFlyvemdmPolicy $policyData) {
      switch ($policyData->getField('type')) {
         case 'string':
            $policy = new PluginFlyvemdmPolicyString($policyData);
            break;

         case 'bool':
            $policy = new PluginFlyvemdmPolicyBoolean($policyData);
            break;

         case 'int':
            $policy = new PluginFlyvemdmPolicyInteger($policyData);
            break;

         case 'dropdown':
            $policy = new PluginFlyvemdmPolicyDropdown($policyData);
            break;

         case 'deployapp':
            $policy = new PluginFlyvemdmPolicyDeployapplication($policyData);
            break;

         case 'removeapp':
            $policy = new PluginFlyvemdmPolicyRemoveapplication($policyData);
            break;

         case 'deployfile':
            $policy = new PluginFlyvemdmPolicyDeployfile($policyData);
            break;

         case 'removefile':
            $policy = new PluginFlyvemdmPolicyRemovefile($policyData);
            break;

         case 'apn':
            $policy = new PluginFlyvemdmPolicyApn($policyData);
            break;

         default:
            return null;
      }

      return $policy;
   }

   /**
    * Create and returns a policy from DB
    *
    * @param integer $id
    * @return PluginFlyvemdmPolicyBase
    */
   public function createFromDBByID($id) {
      $policyData = new PluginFlyvemdmPolicy();
      if (!$policyData->getFromDB($id)) {
         return null;
      }

      return $this->createFromPolicy($policyData);
   }
}
