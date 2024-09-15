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

$category = 'Security > Peripherals';
return [
   [
      'name'                                => __('Disable Camera', 'flyvemdm'),
      'symbol'                              => 'disableCamera',
      'group'                               => 'camera',
      'type'                                => 'bool',
      'type_data'                           => '',
      'unicity'                             => 1,
      'plugin_flyvemdm_policycategories_id' => $category,
      'comment'                             => __('Prevent usage of the Camera.', 'flyvemdm'),
      'default_value'                       => '0',
      'recommended_value'                   => '0',
      'is_android_system'                   => '0',
      'android_min_version'                 => '4.0',
      'android_max_version'                 => '0',
      'apple_min_version'                   => '0',
      'apple_max_version'                   => '0',
   ],

   [
      'name'                                => __('Disable Wifi', 'flyvemdm'),
      'symbol'                              => 'disableWifi',
      'group'                               => 'connectivity',
      'type'                                => 'bool',
      'type_data'                           => '',
      'unicity'                             => 1,
      'plugin_flyvemdm_policycategories_id' => $category,
      'comment'                             => __('Disable wifi connectivity.', 'flyvemdm'),
      'default_value'                       => '0',
      'recommended_value'                   => '0',
      'is_android_system'                   => '0',
      'android_min_version'                 => '1.0',
      'android_max_version'                 => '0',
      'apple_min_version'                   => '0',
      'apple_max_version'                   => '0',
   ],

   [
      'name'                                => __('Disable Bluetooth', 'flyvemdm'),
      'symbol'                              => 'disableBluetooth',
      'group'                               => 'connectivity',
      'type'                                => 'bool',
      'type_data'                           => '',
      'unicity'                             => 1,
      'plugin_flyvemdm_policycategories_id' => $category,
      'comment'                             => __('Disable Bluetooth connectivity.',
         'flyvemdm'),
      'default_value'                       => '0',
      'recommended_value'                   => '0',
      'is_android_system'                   => '0',
      'android_min_version'                 => '2.0',
      'android_max_version'                 => '0',
      'apple_min_version'                   => '0',
      'apple_max_version'                   => '0',
   ],

   [
      'name'                                => __('Disable roaming', 'flyvemdm'),
      'symbol'                              => 'disableRoaming',
      'group'                               => 'connectivity',
      'type'                                => 'bool',
      'type_data'                           => '',
      'unicity'                             => 1,
      'plugin_flyvemdm_policycategories_id' => $category,
      'comment'                             => __('Disable roaming.', 'flyvemdm'),
      'default_value'                       => '0',
      'recommended_value'                   => '0',
      'is_android_system'                   => '0',
      'android_min_version'                 => '5.0',
      'android_max_version'                 => '0',
      'apple_min_version'                   => '0',
      'apple_max_version'                   => '0',
   ],

   [
      'name'                                => __('Disable GPS', 'flyvemdm'),
      'symbol'                              => 'disableGPS',
      'group'                               => 'connectivity',
      'type'                                => 'bool',
      'type_data'                           => '',
      'unicity'                             => 1,
      'plugin_flyvemdm_policycategories_id' => $category,
      'comment'                             => __('Disable GPS.', 'flyvemdm'),
      'default_value'                       => '0',
      'recommended_value'                   => '0',
      'is_android_system'                   => '1',
      'android_min_version'                 => '1.5',
      'android_max_version'                 => '0',
      'apple_min_version'                   => '0',
      'apple_max_version'                   => '0',
   ],

   [
      'name'                                => __('Disable Mobile line', 'flyvemdm'),
      'symbol'                              => 'disableMobileLine',
      'group'                               => 'connectivity',
      'type'                                => 'bool',
      'type_data'                           => '',
      'unicity'                             => 1,
      'plugin_flyvemdm_policycategories_id' => $category,
      'comment'                             => __('Disable Mobile line.', 'flyvemdm'),
      'default_value'                       => '0',
      'recommended_value'                   => '0',
      'is_android_system'                   => '1',
      'android_min_version'                 => '5.0',
      'android_max_version'                 => '0',
      'apple_min_version'                   => '0',
      'apple_max_version'                   => '0',
   ],

   [
      'name'                                => __('Disable NFC', 'flyvemdm'),
      'symbol'                              => 'disableNfc',
      'group'                               => 'connectivity',
      'type'                                => 'bool',
      'type_data'                           => '',
      'unicity'                             => 1,
      'plugin_flyvemdm_policycategories_id' => $category,
      'comment'                             => __('Disable Near Field Contact.', 'flyvemdm'),
      'default_value'                       => '0',
      'recommended_value'                   => '0',
      'is_android_system'                   => '1',
      'android_min_version'                 => '4.3',
      'android_max_version'                 => '0',
      'apple_min_version'                   => '0',
      'apple_max_version'                   => '0',
   ],

   [
      'name'                                => __('Disable hotspot and tethering',
         'flyvemdm'),
      'symbol'                              => 'disableHostpotTethering',
      'group'                               => 'connectivity',
      'type'                                => 'bool',
      'type_data'                           => '',
      'unicity'                             => 1,
      'plugin_flyvemdm_policycategories_id' => $category,
      'comment'                             => __('Disable hotspot and tethering.',
         'flyvemdm'),
      'default_value'                       => '0',
      'recommended_value'                   => '0',
      'is_android_system'                   => '0',
      'android_min_version'                 => '5.0',
      'android_max_version'                 => '0',
      'apple_min_version'                   => '0',
      'apple_max_version'                   => '0',
   ],

   [
      'name'                                => __('Disable airplane mode', 'flyvemdm'),
      'symbol'                              => 'disableAirplaneMode',
      'group'                               => 'connectivity',
      'type'                                => 'bool',
      'type_data'                           => '',
      'unicity'                             => 1,
      'plugin_flyvemdm_policycategories_id' => $category,
      'comment'                             => __('Disable airplane mode.', 'flyvemdm'),
      'default_value'                       => '0',
      'recommended_value'                   => '0',
      'is_android_system'                   => '1',
      'android_min_version'                 => '4.2',
      'android_max_version'                 => '0',
      'apple_min_version'                   => '0',
      'apple_max_version'                   => '0',
   ],

   [
      'name'                                => __('Disable speakerphone', 'flyvemdm'),
      'symbol'                              => 'disableSpeakerphone',
      'group'                               => 'connectivity',
      'type'                                => 'bool',
      'type_data'                           => '',
      'unicity'                             => 1,
      'plugin_flyvemdm_policycategories_id' => $category,
      'comment'                             => __('Disable the speakerphone.', 'flyvemdm'),
      'default_value'                       => '0',
      'recommended_value'                   => '0',
      'is_android_system'                   => '0',
      'android_min_version'                 => '5.0',
      'android_max_version'                 => '0',
      'apple_min_version'                   => '0',
      'apple_max_version'                   => '0',
   ],

   [
      'name'                                => __('APN configuration', 'flyvemdm'),
      'symbol'                              => 'apnConfiguration',
      'group'                               => 'connectivity',
      'type'                                => 'apn',
      'type_data'                           => '',
      'unicity'                             => 0,
      'plugin_flyvemdm_policycategories_id' => $category,
      'comment'                             => __('Set-up you APN values manually', 'flyvemdm'),
      'default_value'                       => '',
      'recommended_value'                   => '',
      'is_android_policy'                   => '1',
      'is_android_system'                   => '0',
      'is_apple_policy'                     => '0',
   ],
];