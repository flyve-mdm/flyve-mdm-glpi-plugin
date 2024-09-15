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

$category = 'Security > User interface';
$securityUserInterface = [
   [
      'name'                                => __('Disable status bar', 'flyvemdm'),
      'symbol'                              => 'disableStatusBar',
      'group'                               => 'ui',
      'type'                                => 'bool',
      'type_data'                           => '',
      'unicity'                             => 1,
      'plugin_flyvemdm_policycategories_id' => $category,
      'comment'                             => __('Disable the status bar. Disabling the status bar blocks notifications, quick settings and other screen overlays that allow escaping from a single use device.',
         'flyvemdm'),
      'default_value'                       => '0',
      'recommended_value'                   => '0',
      'is_android_system'                   => '1',
      'android_min_version'                 => '4.3',
      'android_max_version'                 => '0',
      'apple_min_version'                   => '0',
      'apple_max_version'                   => '0',
   ],

   [
      'name'                                => __('Disable screen capture', 'flyvemdm'),
      'symbol'                              => 'disableScreenCapture',
      'group'                               => 'ui',
      'type'                                => 'bool',
      'type_data'                           => '',
      'unicity'                             => 1,
      'plugin_flyvemdm_policycategories_id' => $category,
      'comment'                             => __('Disable screen capture. Disabling screen capture also prevents the content from being shown on display devices that do not have a secure video output.',
         'flyvemdm'),
      'default_value'                       => '0',
      'recommended_value'                   => '0',
      'is_android_system'                   => '1',
      'android_min_version'                 => '5.0',
      'android_max_version'                 => '0',
      'apple_min_version'                   => '0',
      'apple_max_version'                   => '0',
   ],

   [
      'name'                                => __('Disable device clipboard', 'flyvemdm'),
      'symbol'                              => 'disableClipboard',
      'group'                               => 'ui',
      'type'                                => 'bool',
      'type_data'                           => '',
      'unicity'                             => 1,
      'plugin_flyvemdm_policycategories_id' => $category,
      'comment'                             => __('Disable copy or paste actions on the device.',
         'flyvemdm'),
      'default_value'                       => '0',
      'recommended_value'                   => '0',
      'is_android_system'                   => '0',
      'android_min_version'                 => '3.0',
      'android_max_version'                 => '0',
      'apple_min_version'                   => '0',
      'apple_max_version'                   => '0',
   ],
];

$category = 'Configuration > User interface';
$configurationUserInterface = [
   [
      'name'                                => __('Disable media sounds', 'flyvemdm'),
      'symbol'                              => 'disableStreamMusic',
      'group'                               => 'ui',
      'type'                                => 'bool',
      'type_data'                           => '',
      'unicity'                             => 1,
      'plugin_flyvemdm_policycategories_id' => $category,
      'comment'                             => __('Disable all media sounds from device', 'flyvemdm'),
      'default_value'                       => '0',
      'recommended_value'                   => '0',
      'is_android_system'                   => '0',
      'android_min_version'                 => '5.0',
      'android_max_version'                 => '0',
      'apple_min_version'                   => '0',
      'apple_max_version'                   => '0',
   ],

   [
      'name'                                => __('Disable ringer sounds', 'flyvemdm'),
      'symbol'                              => 'disableStreamRing',
      'group'                               => 'ui',
      'type'                                => 'bool',
      'type_data'                           => '',
      'unicity'                             => 1,
      'plugin_flyvemdm_policycategories_id' => $category,
      'comment'                             => __('Disable all ringer sounds from device', 'flyvemdm'),
      'default_value'                       => '0',
      'recommended_value'                   => '0',
      'is_android_system'                   => '0',
      'android_min_version'                 => '5.0',
      'android_max_version'                 => '0',
      'apple_min_version'                   => '0',
      'apple_max_version'                   => '0',
   ],

   [
      'name'                                => __('Disable alarm sounds', 'flyvemdm'),
      'symbol'                              => 'disableStreamAlarm',
      'group'                               => 'ui',
      'type'                                => 'bool',
      'type_data'                           => '',
      'unicity'                             => 1,
      'plugin_flyvemdm_policycategories_id' => $category,
      'comment'                             => __('Disable alarm sounds from device', 'flyvemdm'),
      'default_value'                       => '0',
      'recommended_value'                   => '0',
      'is_android_system'                   => '0',
      'android_min_version'                 => '5.0',
      'android_max_version'                 => '0',
      'apple_min_version'                   => '0',
      'apple_max_version'                   => '0',
   ],

   [
      'name'                                => __('Disable notifications sounds', 'flyvemdm'),
      'symbol'                              => 'disableStreamNotification',
      'group'                               => 'ui',
      'type'                                => 'bool',
      'type_data'                           => '',
      'unicity'                             => 1,
      'plugin_flyvemdm_policycategories_id' => $category,
      'comment'                             => __('Disable notifications sounds from device', 'flyvemdm'),
      'default_value'                       => '0',
      'recommended_value'                   => '0',
      'is_android_system'                   => '0',
      'android_min_version'                 => '5.0',
      'android_max_version'                 => '0',
      'apple_min_version'                   => '0',
      'apple_max_version'                   => '0',
   ],

   [
      'name'                                => __('Disable accessibility sounds', 'flyvemdm'),
      'symbol'                              => 'disableStreamAccessibility',
      'group'                               => 'ui',
      'type'                                => 'bool',
      'type_data'                           => '',
      'unicity'                             => 1,
      'plugin_flyvemdm_policycategories_id' => $category,
      'comment'                             => __('Disable accessibility prompts sounds from device', 'flyvemdm'),
      'default_value'                       => '0',
      'recommended_value'                   => '0',
      'is_android_system'                   => '1',
      'android_min_version'                 => '5.0',
      'android_max_version'                 => '0',
      'apple_min_version'                   => '0',
      'apple_max_version'                   => '0',
   ],

   [
      'name'                                => __('Disable DTMF sounds', 'flyvemdm'),
      'symbol'                              => 'disableStreamDTMF',
      'group'                               => 'ui',
      'type'                                => 'bool',
      'type_data'                           => '',
      'unicity'                             => 1,
      'plugin_flyvemdm_policycategories_id' => $category,
      'comment'                             => __('Disable DTMF tones sounds from device', 'flyvemdm'),
      'default_value'                       => '0',
      'recommended_value'                   => '0',
      'is_android_system'                   => '0',
      'android_min_version'                 => '5.0',
      'android_max_version'                 => '0',
      'apple_min_version'                   => '0',
      'apple_max_version'                   => '0',
   ],

   [
      'name'                                => __('Disable system sounds', 'flyvemdm'),
      'symbol'                              => 'disableStreamSystem',
      'group'                               => 'ui',
      'type'                                => 'bool',
      'type_data'                           => '',
      'unicity'                             => 1,
      'plugin_flyvemdm_policycategories_id' => $category,
      'comment'                             => __('Disable system sounds from device', 'flyvemdm'),
      'default_value'                       => '0',
      'recommended_value'                   => '0',
      'is_android_system'                   => '1',
      'android_min_version'                 => '5.0',
      'android_max_version'                 => '0',
      'apple_min_version'                   => '0',
      'apple_max_version'                   => '0',
   ],

   [
      'name'                                => __('Audio profile mode', 'flyvemdm'),
      'symbol'                              => 'defaultStreamType',
      'group'                               => 'ui',
      'type'                                => 'dropdown',
      'type_data' => [
         'RINGER_MODE_NORMAL'  => __('Normal', 'flyvemdm'),
         'RINGER_MODE_SILENT'  => __('Silent', 'flyvemdm'),
         'RINGER_MODE_VIBRATE' => __('Vibrate', 'flyvemdm'),
      ],
      'unicity'                             => 1,
      'plugin_flyvemdm_policycategories_id' => $category,
      'comment'                             => __('Audio profile mode used for device', 'flyvemdm'),
      'default_value'                       => 'RINGER_MODE_NORMAL',
      'recommended_value'                   => 'RINGER_MODE_NORMAL',
      'is_android_system'                   => '1',
      'android_min_version'                 => '5.0',
      'android_max_version'                 => '0',
      'apple_min_version'                   => '0',
      'apple_max_version'                   => '0',
   ],
];

return array_merge($securityUserInterface, $configurationUserInterface);