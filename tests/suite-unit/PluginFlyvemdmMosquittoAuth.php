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
 * @author    Domingo Oropeza
 * @copyright Copyright © 2018 Teclib
 * @license   AGPLv3+ http://www.gnu.org/licenses/agpl.txt
 * @link      https://github.com/flyve-mdm/glpi-plugin
 * @link      https://flyve-mdm.com/
 * ------------------------------------------------------------------------------
 */
namespace tests\units;

use Flyvemdm\Tests\CommonTestCase;

/**
 *
 * @engine inline
 */
class PluginFlyvemdmMosquittoAuth extends CommonTestCase {

   private $mqttUser = null;
   private $mqttDisabledUser = null;

   public function setup() {
      parent::setup();
      $this->mqttDisabledUser = new \PluginFlyvemdmMqttuser();
      $this->mqttDisabledUser->add([
         'user'      => 'disabled',
         'password'  => 'password',
         'enabled'   => '0',
      ]);
      $this->boolean($this->mqttDisabledUser->isNewItem())->isFalse('Failed to create a MQTT user');

      $mqttAcl = new \PluginFlyvemdmMqttAcl();
      $mqttAcl->add([
         \PluginFlyvemdmMqttUser::getForeignKeyField() => $this->mqttDisabledUser->getID(),
         'topic' => 'test/disabled/#',
         'access_level' => \PluginFlyvemdmMqttAcl::MQTTACL_ALL,
      ]);
      $this->boolean($mqttAcl->isNewItem())->isFalse('Failed to create a MQTT user');

      $this->mqttUser = new \PluginFlyvemdmMqttuser();
      $this->mqttUser->add([
         'user'      => 'john',
         'password'  => 'doe',
         'enabled'   => '1',
      ]);
      $this->boolean($this->mqttUser->isNewItem())->isFalse('Failed to create a MQTT user');

      $mqttAcl = new \PluginFlyvemdmMqttAcl();
      $mqttAcl->add([
         \PluginFlyvemdmMqttUser::getForeignKeyField() => $this->mqttUser->getID(),
         'topic' => 'test/1/#',
         'access_level' => \PluginFlyvemdmMqttAcl::MQTTACL_NONE,
      ]);
      $this->boolean($mqttAcl->isNewItem())->isFalse('Failed to create a MQTT user');

      $mqttAcl = new \PluginFlyvemdmMqttAcl();
      $mqttAcl->add([
         \PluginFlyvemdmMqttUser::getForeignKeyField() => $this->mqttUser->getID(),
         'topic' => 'test/2/#',
         'access_level' => \PluginFlyvemdmMqttAcl::MQTTACL_READ,
      ]);
      $this->boolean($mqttAcl->isNewItem())->isFalse('Failed to create a MQTT user');

      $mqttAcl = new \PluginFlyvemdmMqttAcl();
      $mqttAcl->add([
         \PluginFlyvemdmMqttUser::getForeignKeyField() => $this->mqttUser->getID(),
         'topic' => 'test/3/#',
         'access_level' => \PluginFlyvemdmMqttAcl::MQTTACL_WRITE,
      ]);
      $this->boolean($mqttAcl->isNewItem())->isFalse('Failed to create a MQTT user');

      $mqttAcl = new \PluginFlyvemdmMqttAcl();
      $mqttAcl->add([
         \PluginFlyvemdmMqttUser::getForeignKeyField() => $this->mqttUser->getID(),
         'topic' => 'test/4/#',
         'access_level' => \PluginFlyvemdmMqttAcl::MQTTACL_ALL,
      ]);
      $this->boolean($mqttAcl->isNewItem())->isFalse('Failed to create a MQTT user');

      $mqttAcl = new \PluginFlyvemdmMqttAcl();
      $mqttAcl->add([
         \PluginFlyvemdmMqttUser::getForeignKeyField() => $this->mqttUser->getID(),
         'topic' => 'test/5/+/a',
         'access_level' => \PluginFlyvemdmMqttAcl::MQTTACL_ALL,
      ]);
      $this->boolean($mqttAcl->isNewItem())->isFalse('Failed to create a MQTT user');

   }

   public function teardown() {
      $this->mqttUser->delete([
            'id' => $this->mqttUser->getID(),
         ],
         1
      );
      $this->mqttDisabledUser->delete([
         'id' => $this->mqttDisabledUser->getID(),
         ],
         1
      );
   }

   public function providerAuthenticate() {
      return [
         [
            'input' => [
               'username' => 'foo',
               'password' => 'bar',
            ],
            'remoteIp' => '127.0.0.1',
            'expected' => [
               'httpCode' => '404',
            ],
         ],
         [
            'input' => [
               'username' => 'disabled',
               'password' => 'password',
            ],
            'remoteIp' => '127.0.0.1',
            'expected' => [
               'httpCode' => '404',
            ],
         ],
         [
            'input' => [
               'username' => 'john',
               'password' => 'doe',
            ],
            'remoteIp' => '127.0.0.1',
            'expected' => [
               'httpCode' => '200',
            ],
         ],
         [
            'input' => [
               'username' => 'john',
               'password' => 'bar',
            ],
            'remoteIp' => '127.0.0.1',
            'expected' => [
               'httpCode' => '404',
            ],
         ],
         [
            'input' => [
               'username' => 'john',
               'password' => 'doe',
            ],
            'remoteIp' => '10.0.0.1',
            'expected' => [
               'httpCode' => '403',
            ],
         ],
      ];
   }

   /**
    * @dataProvider providerAuthenticate
    */
   public function testAuthenticate($input, $remoteIp, $expected) {
      $backupServer = $_SERVER;
      $_SERVER['REMOTE_ADDR'] = $remoteIp;

      $instance = new \PluginFlyvemdmMosquittoAuth();
      $output = $instance->authenticate($input);
      $this->integer((int) $output)->isEqualTo($expected['httpCode']);

      $_SERVER = $backupServer;
   }

   public function providerIsSuperuser() {
      return [
         [
            'input' => [
               'username' => 'foo',
            ],
            'expected' => [
               'httpCode' => 403,
            ],
         ],
         [
            'input' => [
               'username' => 'john',
            ],
            'expected' => [
               'httpCode' => 403,
            ],
         ],
      ];
   }

   /**
    * @dataProvider providerIsSuperuser
    */
   public function testIsSuperuser($input, $expected) {
      $instance = new \PluginFlyvemdmMosquittoAuth();
      $output = $instance->isSuperuser($input);
      $this->integer($output)->isEqualTo($expected['httpCode']);
   }

   public function providerAuthorize() {
      return [
         [
            'input' => [
               'username' => 'foo',
               'topic'    => 'test/4',
               'acc'      => 1,
            ],
            'remoteIp' => '127.0.0.1',
            'expected' => [
               'httpCode' => 403,
            ],
         ],
         [
            'input' => [
               'username' => 'disabled',
               'topic'    => 'test/disabled',
               'acc'      => 1,
            ],
            'remoteIp' => '127.0.0.1',
            'expected' => [
               'httpCode' => 403,
            ],
         ],
         [
            'input' => [
               'username' => 'john',
               'topic'    => 'test/1',
               'acc'      => 1,
            ],
            'remoteIp' => '127.0.0.1',
            'expected' => [
               'httpCode' => 403,
            ],
         ],
         [
            'input' => [
               'username' => 'john',
               'topic'    => 'test/1',
               'acc'      => 2,
            ],
            'remoteIp' => '127.0.0.1',
            'expected' => [
               'httpCode' => 403,
            ],
         ],
         [
            'input' => [
               'username' => 'john',
               'topic'    => 'test/1/subtopic',
               'acc'      => 1,
            ],
            'remoteIp' => '127.0.0.1',
            'expected' => [
               'httpCode' => 403,
            ],
         ],
         [
            'input' => [
               'username' => 'john',
               'topic'    => 'test/1/subtopic',
               'acc'      => 2,
            ],
            'remoteIp' => '127.0.0.1',
            'expected' => [
               'httpCode' => 403,
            ],
         ],
         [
            'input' => [
               'username' => 'john',
               'topic'    => 'test/2',
               'acc'      => 1,
            ],
            'remoteIp' => '127.0.0.1',
            'expected' => [
               'httpCode' => 200,
            ],
         ],
         [
            'input' => [
               'username' => 'john',
               'topic'    => 'test/2',
               'acc'      => 2,
            ],
            'remoteIp' => '127.0.0.1',
            'expected' => [
               'httpCode' => 403,
            ],
         ],
         [
            'input' => [
               'username' => 'john',
               'topic'    => 'test/2/subtopic',
               'acc'      => 1,
            ],
            'remoteIp' => '127.0.0.1',
            'expected' => [
               'httpCode' => 200,
            ],
         ],
         [
            'input' => [
               'username' => 'john',
               'topic'    => 'test/2/subtopic',
               'acc'      => 2,
            ],
            'remoteIp' => '127.0.0.1',
            'expected' => [
               'httpCode' => 403,
            ],
         ],
         [
            'input' => [
               'username' => 'john',
               'topic'    => 'test/3',
               'acc'      => 1,
            ],
            'remoteIp' => '127.0.0.1',
            'expected' => [
               'httpCode' => 403,
            ],
         ],
         [
            'input' => [
               'username' => 'john',
               'topic'    => 'test/3',
               'acc'      => 2,
            ],
            'remoteIp' => '127.0.0.1',
            'expected' => [
               'httpCode' => 200,
            ],
         ],
         [
            'input' => [
               'username' => 'john',
               'topic'    => 'test/3/subtopic',
               'acc'      => 1,
            ],
            'remoteIp' => '127.0.0.1',
            'expected' => [
               'httpCode' => 403,
            ],
         ],
         [
            'input' => [
               'username' => 'john',
               'topic'    => 'test/3/subtopic',
               'acc'      => 2,
            ],
            'remoteIp' => '127.0.0.1',
            'expected' => [
               'httpCode' => 200,
            ],
         ],
         [
            'input' => [
               'username' => 'john',
               'topic'    => 'test/4',
               'acc'      => 1,
            ],
            'remoteIp' => '127.0.0.1',
            'expected' => [
               'httpCode' => 200,
            ],
         ],
         [
            'input' => [
               'username' => 'john',
               'topic'    => 'test/4',
               'acc'      => 2,
            ],
            'remoteIp' => '127.0.0.1',
            'expected' => [
               'httpCode' => 200,
            ],
         ],
         [
            'input' => [
               'username' => 'john',
               'topic'    => 'test/4/subtopic',
               'acc'      => 1,
            ],
            'remoteIp' => '127.0.0.1',
            'expected' => [
               'httpCode' => 200,
            ],
         ],
         [
            'input' => [
               'username' => 'john',
               'topic'    => 'test/4/subtopic',
               'acc'      => 2,
            ],
            'remoteIp' => '127.0.0.1',
            'expected' => [
               'httpCode' => 200,
            ],
         ],
         [
            'input' => [
               'username' => 'john',
               'topic'    => 'test/5/sub-a/a',
               'acc'      => 1,
            ],
            'remoteIp' => '127.0.0.1',
            'expected' => [
               'httpCode' => 200,
            ],
         ],
         [
            'input' => [
               'username' => 'john',
               'topic'    => 'test/5/sub-b/a',
               'acc'      => 1,
            ],
            'remoteIp' => '127.0.0.1',
            'expected' => [
               'httpCode' => 200,
            ],
         ],
      ];
   }

   /**
    * @dataProvider providerAuthorize
    */
   public function testAuthorize($input, $remoteIp, $expected) {
      $backupServer = $_SERVER;
      $_SERVER['REMOTE_ADDR'] = $remoteIp;

      $instance = new \PluginFlyvemdmMosquittoAuth();
      $output = $instance->authorize($input);
      $this->integer((int) $output)->isEqualTo($expected['httpCode']);

      $_SERVER = $backupServer;

   }
}