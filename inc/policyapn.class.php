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
 * @license   http://www.gnu.org/licenses/agpl.txt AGPLv3+
 * @link      https://github.com/flyve-mdm/glpi-plugin
 * @link      https://flyve-mdm.com/
 * ------------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * Class PluginFlyvemdmPolicyApn
 * @since 2.1
 */
class PluginFlyvemdmPolicyApn extends PluginFlyvemdmPolicyBase implements PluginFlyvemdmPolicyInterface {

   private $formFields = [];
   private $apnAuthType = [];
   private $apnType = [];

   /**
    * PluginFlyvemdmPolicyApn constructor.
    * @param PluginFlyvemdmPolicy $policy
    */
   public function __construct(PluginFlyvemdmPolicy $policy) {
      $this->formFields = [
         'apn_name'           => ['label' => __('Name', 'flyvemdm'), 'type' => 'text'],
         'apn_fqn'            => ['label' => __('APN', 'flyvemdm'), 'type' => 'text'],
         'apn_proxy'          => ['label' => __('Proxy', 'flyvemdm'), 'type' => 'text'],
         'apn_port'           => ['label' => __('Port', 'flyvemdm'), 'type' => 'text'],
         'apn_username'       => ['label' => __('Username', 'flyvemdm'), 'type' => 'text'],
         'apn_password'       => ['label' => __('Password', 'flyvemdm'), 'type' => 'password'],
         'apn_server'         => ['label' => __('Server', 'flyvemdm'), 'type' => 'text'],
         'apn_mmsc'           => ['label' => __('MMSC', 'flyvemdm'), 'type' => 'text'],
         'apn_proxy_mms'      => ['label' => __('Proxy MMS', 'flyvemdm'), 'type' => 'text'],
         'apn_proxy_mms_port' => ['label' => __('Proxy MMC port', 'flyvemdm'), 'type' => 'text'],
         'apn_mmc'            => ['label' => __('MMC', 'flyvemdm'), 'type' => 'text'],
         'apn_mnc'            => ['label' => __('MNC', 'flyvemdm'), 'type' => 'text'],
      ];
      $this->apnType = [__('Default'), 'MMS', 'SUPL', 'DUN', 'HIPRI', 'FOTA'];
      $this->apnAuthType = [__('No authentication'), 'PAP', 'CHAP', 'CHAP/PAP'];
      parent::__construct($policy);
      $this->symbol = $policy->getField('symbol');
      $this->unicityRequired = ($policy->getField('unicity') != '0');
      $this->group = $policy->getField('group');
   }

   /**
    * @param mixed $value
    * @param mixed $itemtype
    * @param integer $itemId
    * @return bool
    */
   public function integrityCheck($value, $itemtype, $itemId) {
      // Check the value exists
      if (!isset($value['apn_name']) || !$value['apn_name']) {
         Session::addMessageAfterRedirect(__('APN name is mandatory', 'flyvemdm'));
         return false;
      }
      if (!isset($value['apn_fqn']) || !$value['apn_fqn']) {
         Session::addMessageAfterRedirect(__('APN value is mandatory', 'flyvemdm'));
         return false;
      }
      return true;
   }

   /**
    * @param mixed $value
    * @param mixed $itemtype
    * @param int $itemId
    * @return array|bool
    */
   public function getBrokerMessage($value, $itemtype, $itemId) {
      $decodedValue = json_decode($value, JSON_OBJECT_AS_ARRAY);
      if (!$this->integrityCheck($decodedValue, $itemtype, $itemId)) {
         return false;
      }
      $array = [
         $this->symbol => $value,
      ];
      return $array;
   }

   public function showValueInput($value = '', $itemType = '', $itemId = 0) {

      $value = json_decode($value, JSON_OBJECT_AS_ARRAY);

      $data = [];
      foreach ($this->formFields as $inputName => $inputOptions) {
         $data['inputs'][] = [
            'name'  => "value[$inputName]",
            'label' => $inputOptions['label'],
            'type'  => $inputOptions['type'],
            'value' => ($value[$inputName]) ? $value[$inputName] : '',
         ];
      }

      $apnAuthType = ($value['apn_auth_type']) ? $value['apn_auth_type'] : 0;
      $apnType = ($value['apn_type']) ? $value['apn_type'] : 0;

      $data['apnAuthType'] = [
         'label'    => 'Authentication Type',
         'dropdown' => Dropdown::showFromArray('value[apn_auth_type]',
            $this->apnAuthType,
            ['display' => false, 'value' => $apnAuthType]),
      ];
      $data['apnType'] = [
         'label'    => 'APN Type',
         'dropdown' => Dropdown::showFromArray('value[apn_type]',
            $this->apnType,
            ['display' => false, 'value' => $apnType]),
      ];

      $twig = plugin_flyvemdm_getTemplateEngine();
      return $twig->render('policy_apn_form.html.twig', $data);
   }

   public function showValue(PluginFlyvemdmTask $task) {
      $values = json_decode($task->getField('value'), JSON_OBJECT_AS_ARRAY);
      $stringValues = $values['apn_name'];
      return $stringValues;
   }
}