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
 * @author    Domingo Oropeza <doropeza@teclib.com>
 * @copyright Copyright © 2018 Teclib
 * @license   http://www.gnu.org/licenses/agpl.txt AGPLv3+
 * @link      https://github.com/flyve-mdm/glpi-plugin
 * @link      https://flyve-mdm.com/
 * ------------------------------------------------------------------------------
 */

namespace tests\units\GlpiPlugin\Flyvemdm\Fcm;


use Flyvemdm\Tests\CommonTestCase;
use GlpiPlugin\Flyvemdm\Broker\BrokerMessage;
use GlpiPlugin\Flyvemdm\Fcm\FcmEnvelope;
use GlpiPlugin\Flyvemdm\Fcm\FcmSendMessageHandler as SendMessageHandler;
use Sly\NotificationPusher\Adapter\Gcm;
use Sly\NotificationPusher\PushManager;

class FcmSendMessageHandler extends CommonTestCase {

   /**
    * @tags testSendMessageHandler
    */
   public function testSendMessageHandler() {
      $pushManager = new PushManager();
      $adapter = new Gcm(['apiKey' => 'ServerApikey']);
      $toolbox = new \Toolbox();

      $message = 'Hello world';
      $topic = 'lorem/ipsum/dolor';
      $scope = [['type' => 'fcm', 'token' => 'Sup3rT0k3n']];
      $envelope = new FcmEnvelope(['scope' => $scope, 'topic' => $topic]);
      $brokerMessage = new BrokerMessage($message);
      $mockedConnection = $this->newMockInstance('\GlpiPlugin\Flyvemdm\Fcm\FcmConnection', null,
         null, [$pushManager, $adapter, $toolbox]);
      $mockedConnection->getMockController()->push = function () {};
      $instance = new SendMessageHandler($envelope, $mockedConnection);
      $this->object($instance)->isCallable();
      $instance($brokerMessage);
      $this->mock($mockedConnection)->call('push')->once();
   }

}