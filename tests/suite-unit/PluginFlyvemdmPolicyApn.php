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

namespace tests\units;

use Flyvemdm\Tests\CommonTestCase;

class PluginFlyvemdmPolicyApn extends CommonTestCase {

   private $dataField = [
      'group'     => 'connectivity',
      'symbol'    => 'apnConfiguration',
      'type'      => 'apn',
      'type_data' => '',
      'unicity'   => '0',
   ];

   protected function validationProvider() {
      return [
         'Check apn_name is not set'   => [
            'data'     => [[], null, null],
            'expected' => [false, 'APN name is mandatory'],
         ],
         'Check apn_name is not empty' => [
            'data'     => [['apn_name' => ''], null, null],
            'expected' => [false, 'APN name is mandatory'],
         ],
         'Check apn_fqn is not set'    => [
            'data'     => [['apn_name' => 'lorem'], null, null],
            'expected' => [false, 'APN value is mandatory'],
         ],
         'Check apn_fqn is not empty'  => [
            'data'     => [['apn_name' => 'lorem', 'apn_fqn' => ''], null, null],
            'expected' => [false, 'APN value is mandatory'],
         ],
         'Valid check 1'               => [
            'data'     => [['apn_name' => 'lorem', 'apn_fqn' => 'ipsum'], null, null],
            'expected' => [true],
         ],
      ];
   }

   /**
    * @dataProvider validationProvider
    * @tags testCreatePolicy
    * @param array $data
    * @param array $expected
    */
   public function testCreatePolicy($data, $expected) {
      list($policy) = $this->createNewPolicyInstance();
      $success = $policy->integrityCheck($data[0], $data[1], $data[2]);
      $this->boolean($success)->isEqualTo($expected[0]);
      if (!$expected[0]) {
         $this->string($_SESSION["MESSAGE_AFTER_REDIRECT"][0][0])->isEqualTo($expected[1]);
         unset($_SESSION["MESSAGE_AFTER_REDIRECT"]); // to clear the buffer
      }
   }

   private function createNewPolicyInstance() {
      $policyData = new \PluginFlyvemdmPolicy();
      $policyData->fields = $this->dataField;
      $policy = $this->newTestedInstance($policyData);
      return [$policy, $policyData];
   }

   /**
    * @tags testGetBrokerMessage
    */
   public function testGetBrokerMessage() {
      list($policy) = $this->createNewPolicyInstance();

      $this->boolean($policy->getBrokerMessage(null, null, null))->isFalse();
      $value = '{"apn_name":"lorem","apn_fqn":"ipsum","apn_auth_type":"0","apn_type":"0"}';
      $result = $policy->getBrokerMessage($value, null, null);
      $this->array($result)->hasKeys([$this->dataField['symbol']])
         ->string($result[$this->dataField['symbol']])->contains('"apn_name":"lorem","apn_fqn":"ipsum"');
   }

   /**
    * @tags testShowValueInput
    */
   public function testShowValueInput() {
      list($policy) = $this->createNewPolicyInstance();
      $value = $policy->showValueInput();
      $this->string($value)
         ->contains('input type="text" name="value[apn_name]" value=""')
         ->contains('input type="text" name="value[apn_fqn]" value=""');

      $dropdowns = ['apn_auth_type', 'apn_type'];
      foreach ($dropdowns as $inputName) {
         $matches = null;
         preg_match('/.*<select[^>]*name=\'value\[' . $inputName . '\]\'[^>]*>.*/',
            $value, $matches);
         $this->array($matches)->hasSize(1);
      }
   }

   /**
    * @tags testShowValue
    */
   public function testShowValue() {
      list($policy) = $this->createNewPolicyInstance();
      $mockInstance = $this->newMockInstance('\PluginFlyvemdmTask');
      $mockInstance->getMockController()->getField = '{"apn_name":"lorem","apn_fqn":"ipsum","apn_auth_type":"0","apn_type":"0"}';
      $this->string($policy->showValue($mockInstance))->isEqualTo('lorem');
   }
}