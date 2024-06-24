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

use GlpiPlugin\Flyvemdm\Broker\BrokerBus;
use GlpiPlugin\Flyvemdm\Broker\BrokerEnvelope;
use GlpiPlugin\Flyvemdm\Broker\BrokerMessage;
use GlpiPlugin\Flyvemdm\Exception\AgentSendQueryException;
use GlpiPlugin\Flyvemdm\Exception\TaskPublishPolicyPolicyNotFoundException;
use GlpiPlugin\Flyvemdm\Fcm\FcmEnvelope;
use GlpiPlugin\Flyvemdm\Fcm\FcmMiddleware;
use GlpiPlugin\Flyvemdm\Mqtt\MqttEnvelope;
use GlpiPlugin\Flyvemdm\Mqtt\MqttMiddleware;
use ZendService\Google\Gcm\Message as ZendGcmMessage;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * @since 0.1.0
 */
class PluginFlyvemdmAgent extends CommonDBTM implements PluginFlyvemdmNotifiableInterface {

   const ENROLL_DENY             = 0;
   const ENROLL_INVITATION_TOKEN = 1;
   const ENROLL_ENTITY_TOKEN     = 2;

   const DEFAULT_TOKEN_LIFETIME  = 'P7D';

   const MINIMUM_ANDROID_VERSION = '2.0';
   const MINIMUM_APPLE_VERSION = '1.0';

   /**
    * @var string $rightname name of the right in DB
    */
   public static $rightname            = 'flyvemdm:agent';

   /**
    * @var bool $dohistory maintain history
    */
   public $dohistory                   = true;

   /**
    * @var bool $usenotepad enable notepad for the itemtype (GLPi < 0.85)
    */
   protected $usenotepad               = true;

   /**
    * @var bool $usenotepad enable notepad for the itemtype (GLPi >=0.85)
    */
   protected $usenotepadRights         = true;

   protected $topic = null;

   /**
    * Invitation used for an enrollment process
    *
    * @var PluginFlyvemdmInvitation
    */
   protected $invitation = null;

   /**
    *
    * Returns the minimum version of the agent accepted by the backend
    * @param string $mdmType the type of the agent.
    * @return string the minimum version of the agent depending on its type
    */
   private function getMinVersioForType($mdmType) {
      switch ($mdmType) {
         case 'android':
            return self::MINIMUM_ANDROID_VERSION;
            break;

         case 'apple':
            return self::MINIMUM_APPLE_VERSION;
            break;
      }

      return '';
   }

   /**
    * get mdm types availables
    */
   public static function getEnumMdmType() {
      return [
         'android'   => __('Android', 'flyvemdm'),
         'apple'     => __('Apple', 'flyvemdm'),
      ];
   }

   /**
    * Localized name of the type
    * @param integer $nb number of item in the type (default 0)
    * @return string
    */
   public static function getTypeName($nb = 0) {
      return _n('Agent', 'Agents', $nb, 'flyvemdm');
   }

   /**
    * Return the picture file for the menu
    * @return string
    */
   public static function getMenuPicture() {
      return 'fa-tablet';
   }

   /**
    * @since version 0.1.0
    * @see commonDBTM::getRights()
     */
   public function getRights($interface = 'central') {
      $rights = parent::getRights();
      /// For additional rights if needed
      //$rights[self::RIGHTS] = self::getTypeName();

      return $rights;
   }

   /**
    * Define tabs available for this itemtype
    */
   public function defineTabs($options = []) {
      $tab = [];
      $this->addDefaultFormTab($tab);
      $this->addStandardTab(PluginFlyvemdmGeolocation::class, $tab, $options);
      $this->addStandardTab(__CLASS__, $tab, $options);
      if (!$this->isNewItem()) {
         $this->addStandardTab(PluginFlyvemdmTask::class, $tab, $options);
         $this->addStandardTab(PluginFlyvemdmTaskstatus::class, $tab, $options);
         $this->addStandardTab(PluginFlyvemdmMqttlog::class, $tab, $options);
      }
      $this->addStandardTab(Notepad::class, $tab, $options);
      $this->addStandardTab(Log::class, $tab, $options);

      return $tab;
   }

   /**
    * @see CommonGLPI::getTabNameForItem()
    *
    * @since version 9.1
    * @param CommonGLPI $item
    * @param integer $withtemplate
    * @return array|string
    */
   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if (!static::canView()) {
         return '';
      }

      switch ($item->getType()) {
         case __CLASS__ :
            $tab = [1 => __('Danger zone!', 'flyvemdm')];
            return $tab;
            break;

         case PluginFlyvemdmFleet::class:
            if (!$withtemplate) {
               $nb = 0;
               $fleetId = $item->getID();
               $pluralNumber = Session::getPluralNumber();
               if ($_SESSION['glpishow_count_on_tabs']) {
                  $DbUtil = new DbUtils();
                  $nb = $DbUtil->countElementsInTable(static::getTable(), ['plugin_flyvemdm_fleets_id' => $fleetId]);
               }
               return self::createTabEntry(self::getTypeName($pluralNumber), $nb);
            }
            break;

         case Computer::class:
            return __('Flyve MDM Agent', 'flyvemdm');
            break;
      }
      return '';
   }

   /**
    * @param CommonGLPI $item
    * @param integer $tabnum (default 1)
    * @param integer $withtemplate (default 0)
    *
    * @since version 9.1
    *
    * @return bool
    */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      switch (get_class($item)) {
         case static::class:
            self::showDangerZone($item);
            return true;
            break;

         case PluginFlyvemdmFleet::class:
            self::showForFleet($item);
            return true;
            break;
      }
   }

   /**
    * Shows form for edition
    * @param integer $ID Id of the agent
    * @param array $options
    */
   public function showForm($ID, array $options = []) {
      $this->initForm($ID, $options);
      $this->showFormHeader($options);
      $canUpdate = (!$this->isNewID($ID)) && ($this->canUpdate() > 0);

      $fields              = $this->fields;
      $objectName          = (new DbUtils)->autoName($this->fields['name'], 'name',
                             (isset($options['withtemplate']) && $options['withtemplate'] == 2),
                             $this->getType(), -1);
      $fields['name']      = Html::autocompletionTextField($this, 'name',
                             ['value' => $objectName, 'display' => false]);
      $fields['computer']  = Computer::dropdown([
                                 'display'      => false,
                                 'name'         => 'computers_id',
                                 'value'        => $this->fields['computers_id'],
                                 'entity'       => $this->fields['entities_id']
                             ]);
      $fields['fleet']     = PluginFlyvemdmFleet::dropdown([
                                    'display'      => false,
                                    'name'         => 'plugin_flyvemdm_fleets_id',
                                    'value'        => $this->fields['plugin_flyvemdm_fleets_id'],
                                    'entity'       => $this->fields['entities_id']
                             ]);
      $fields['is_online'] = self::getSpecificValueToDisplay('is_online', $fields['is_online']);
      if (empty($fields['last_contact'])) {
         $fields['last_contact'] = __('Never seen online', 'flyvemdm');
      }
      $data = [
            'withTemplate'    => (isset($options['withtemplate']) && $options['withtemplate'] ? '*' : ''),
            'isNewID'         => $this->isNewID($ID),
            'canUpdate'       => $canUpdate,
            'agent'           => $fields,
            'pingButton'      => Html::submit(_x('button', 'Ping'), ['name' => 'ping']),
            'rebootButton'    => Html::submit(_x('button', 'Reboot'), ['name' => 'reboot']),
            'geolocateButton' => Html::submit(_x('button', 'Geolocate'), ['name' => 'geolocate']),
            'inventoryButton' => Html::submit(_x('button', 'Inventory'), ['name' => 'inventory']),

      ];
      $twig = plugin_flyvemdm_getTemplateEngine();
      echo $twig->render('agent.html.twig', $data);

      $this->showFormButtons($options);
   }

   /**
    * Prints the computer's operating system form
    *
    * @param PluginFlyvemdmAgent $item
    *
    * @since version 9.1
    */
   public static function showDangerZone(PluginFlyvemdmAgent $item) {
      $ID = $item->fields['id'];
      $item->initForm($ID);
      $item->showFormHeader(['formtitle' => false]);
      $canedit = static::canUpdate();

      $fields              = $item->fields;

      $fields['lock']      = Html::getCheckbox([
            'title'        => __('Lock the device as soon as possible', 'flyvemdm'),
            'name'         => 'lock',
            'checked'      => $item->fields['lock'],
            'value'        => '1',
            'readonly'     => ($canedit == '0' ? '1' : '0'),
      ]);
      $fields['wipe']      = Html::getCheckbox([
            'title'        => __('Wipe the device as soon as possible', 'flyvemdm'),
            'name'         => 'wipe',
            'checked'      => $item->fields['wipe'],
            'value'        => '1',
            'readonly'     => ($canedit == '0' ? '1' : '0'),
      ]);

      $data = [
            'withTemplate'    => '',
            'isNewID'         => $item->isNewID($ID),
            'canUpdate'       => (!$item->isNewID($ID)) && ($item->canUpdate() > 0),
            'agent'           => $fields,
            'unenrollButton'  => Html::submit(_x('button', 'Unenroll'), ['name' => 'unenroll']),
      ];

      $twig = plugin_flyvemdm_getTemplateEngine();
      echo $twig->render('agent_dangerzone.html.twig', $data);

      $item->showFormButtons(['candel' => false, 'formfooter' => false]);
   }

   /**
    * Displays the agents according the fleet
    * @param PluginFlyvemdmFleet $item
    * @return string an html with the agents
    */
   public static function showForFleet(PluginFlyvemdmFleet $item) {
      if (!PluginFlyvemdmFleet::canView()) {
         return;
      }

      $start = isset($_GET["start"]) ? intval($_GET["start"]) : 0;

      $dbUtils = new DbUtils();

      // get items
      $agent = new PluginFlyvemdmAgent();
      $items_id = $item->getField('id');
      $itemFk = $item::getForeignKeyField();
      if (version_compare(GLPI_VERSION, '9.4') < 0) {
         $condition = "`$itemFk` = '$items_id' " . $dbUtils->getEntitiesRestrictRequest();
      } else {
         $condition = [$itemFk => $items_id]
         + $dbUtils->getEntitiesRestrictCriteria();
      }
      $rows = $agent->find($condition);
      $number = count($rows);

      // get the pager
      $pager_top = Html::printAjaxPager(self::getTypeName(1), $start, $number, '', false);
      $pager_bottom = Html::printAjaxPager(self::getTypeName(1), $start, $number, '', false);

      $data = [
         'number'       => $number,
         'pager_top'    => $pager_top,
         'pager_bottom' => $pager_bottom,
         'agents'       => $rows,
         'agentUrl'     => Toolbox::getItemTypeFormURL(self::class),
         'start'        => $start,
         'stop'         => $start + $_SESSION['glpilist_limit'],
      ];

      $twig = plugin_flyvemdm_getTemplateEngine();
      echo $twig->render('agent_fleet.html.twig', $data);
   }

   /**
    * Shows informations about the agent linhked to a computer
    *
    * @param CommonDBTM $item
    */
   public static function displayTabContentForComputer(CommonDBTM $item) {
      $agent = new static();
      if (!$agent->getFromDBByCrit(['computers_id' => $item->getID()])) {
         return;
      }
      $fields = $agent->fields;
      $fields['fleet']=$agent->getFleet();
      if (empty($fields['last_contact'])) {
         $fields['last_contact'] = __('Never seen online', 'flyvemdm');
      }
      $twig = plugin_flyvemdm_getTemplateEngine();
      echo $twig->render('agentComputerInfo.html.twig', [
         'agent' => $fields,
         'agentUrl' => Toolbox::getItemTypeFormURL(self::class),
         'fleetUrl' => Toolbox::getItemTypeFormURL(PluginFlyvemdmFleet::class)
      ]);
   }

   public function canViewItem() {
      // Check the active profile
      $config = Config::getConfigurationValues('flyvemdm', ['guest_profiles_id']);
      if ($_SESSION['glpiactiveprofile']['id'] != $config['guest_profiles_id']) {
         return parent::canViewItem();
      }

      if (!$this->checkEntity(true)) {
         return false;
      }

      // the active profile is guest user, then check the user is
      // owner of the item's computer
      $computer = $this->getComputer();
      if ($computer === null) {
         return false;
      }

      return $_SESSION['glpiID'] == $computer->getField('users_id');
   }

   public function canUpdateItem() {
      // Check the active profile
      if (!PluginFlyvemdmCommon::isAgent()) {
         return parent::canUpdateItem();
      }

      return PluginFlyvemdmCommon::isCurrentUser($this) && parent::canUpdateItem();
   }

   function canDeleteItem() {
      // Check the active profile
      if (!PluginFlyvemdmCommon::isAgent()) {
         return parent::canDeleteItem();
      }

      return PluginFlyvemdmCommon::isCurrentUser($this) && parent::canDeleteItem();
   }

   /**
    * Sends a wipe command to the agent
    */
   protected function sendWipeQuery() {
      $message = json_encode(['wipe' => 'now'], JSON_UNESCAPED_SLASHES);
      $brokerMessage = new BrokerMessage($message);
      $envelopeConfig = [];
      $topic = $this->getTopic();
      if ($topic !== null) {
         $finalTopic = "$topic/Command/Wipe";
         $envelopeConfig[] = new MqttEnvelope([
            'topic'  => $finalTopic,
            'retain' => 1,
         ]);
         $envelopeConfig[] = new FcmEnvelope([
            'topic' => $finalTopic,
            'scope' => $this->getPushNotificationInfo(),
         ]);
      }
      $envelope = new BrokerEnvelope($brokerMessage, $envelopeConfig);
      $this->notify($envelope);
   }

   /**
    * Sends a lock command to the agent
    */
   protected function sendLockQuery() {
      $message = json_encode(['lock' => 'now'], JSON_UNESCAPED_SLASHES);
      $brokerMessage = new BrokerMessage($message);
      $envelopeConfig = [];
      $topic = $this->getTopic();
      if ($topic !== null) {
         $finalTopic = "$topic/Command/Lock";
         $envelopeConfig[] = new MqttEnvelope([
            'topic'  => $finalTopic,
            'retain' => 1,
         ]);
         $envelopeConfig[] = new FcmEnvelope([
            'topic' => $finalTopic,
            'scope' => $this->getPushNotificationInfo(),
         ]);
      }
      $envelope = new BrokerEnvelope($brokerMessage, $envelopeConfig);
      $this->notify($envelope);
   }

   /**
    * Sends a lock command to the agent
    */
   protected function sendUnlockQuery() {
      $message = json_encode(['lock' => 'unlock'], JSON_UNESCAPED_SLASHES);
      $brokerMessage = new BrokerMessage($message);
      $envelopeConfig = [];
      $topic = $this->getTopic();
      if ($topic !== null) {
         $finalTopic = "$topic/Command/Lock";
         $envelopeConfig[] = new MqttEnvelope([
            'topic'  => $finalTopic,
            'retain' => 1,
         ]);
         $envelopeConfig[] = new FcmEnvelope([
            'topic' => $finalTopic,
            'scope' => $this->getPushNotificationInfo(),
         ]);
      }
      $envelope = new BrokerEnvelope($brokerMessage, $envelopeConfig);
      $this->notify($envelope);
   }

   /**
    * Sends unenrollment command to the agent
    */
   protected function sendUnenrollQuery() {
      $message = json_encode(['unenroll' => 'now'], JSON_UNESCAPED_SLASHES);
      $brokerMessage = new BrokerMessage($message);
      $envelopeConfig = [];
      $topic = $this->getTopic();
      if ($topic !== null) {
         $finalTopic = "$topic/Command/Unenroll";
         $envelopeConfig[] = new MqttEnvelope([
            'topic'  => $finalTopic,
            'retain' => 1,
         ]);
         $envelopeConfig[] = new FcmEnvelope([
            'topic' => $finalTopic,
            'scope' => $this->getPushNotificationInfo(),
         ]);
      }
      $envelope = new BrokerEnvelope($brokerMessage, $envelopeConfig);
      $this->notify($envelope);
   }

   public function prepareInputForAdd($input) {
      // Get the maximum quantity of devices allowed for the current entity
      $entityConfig = new PluginFlyvemdmEntityConfig();
      if (!$entityConfig->getFromDBOrCreate($_SESSION['glpiactive_entity'])) {
         $this->filterMessages(Session::addMessageAfterRedirect(__('Failed to read configuration of the entity', 'flyvemdm')));
         return false;
      }

      if (!$entityConfig->canAddAgent($_SESSION['glpiactive_entity'])) {
         // Too many devices
         $this->filterMessages(Session::addMessageAfterRedirect(__('Too many devices', 'flyvemdm')));
         return false;
      }

      // User already logged in : user token has been validated
      ini_set("memory_limit", "-1");
      ini_set("max_execution_time", "0");
      switch ($this->chooseEnrollMethod($input)) {
         case self::ENROLL_DENY:
            $this->filterMessages(Session::addMessageAfterRedirect(__('Unable to find a enrollment method', 'flyvemdm')));
            return false;
            break;

         case self::ENROLL_INVITATION_TOKEN:
            $input = $this->enrollByInvitationToken($input);
            break;

         case self::ENROLL_ENTITY_TOKEN:
            // Method disabled, waiting for implementation
            return false;
            break;
      }

      unset($input['is_online']);

      return $input;
   }

   public function prepareInputForUpdate($input) {
      if (isset($input['plugin_flyvemdm_fleets_id'])) {
         // Update MQTT ACL for the fleet
         $oldFleet = new PluginFlyvemdmFleet();
         if (!$oldFleet->getFromDB($this->fields['plugin_flyvemdm_fleets_id'])) {
            // Unable to load fleet currently associated to  the agent
            Session::addMessageAfterRedirect(__("The fleet of the device does not longer exists", 'flyvemdm'));
            return false;
         }

         $newFleet = new PluginFlyvemdmFleet();
         if (!$newFleet->getFromDB($input['plugin_flyvemdm_fleets_id'])) {
            //Unable to load the new fleet
            Session::addMessageAfterRedirect(__("The target fleet does not exists", 'flyvemdm'));
            return false;
         }

         $this->changeMqttAcl($oldFleet, $newFleet);
      }

      // send wipe to the agent
      if (isset($input['wipe']) && $input['wipe'] != '0') {
         $input['wipe'] = '1';
      }

      // send lock to the agent
      if (isset($input['lock']) && $input['lock'] != '0') {
         $input['lock'] = '1';
      }

      if (array_key_exists('lock', $input)
          && ($this->fields['wipe'] != '0'
              || (isset($input['wipe']) && $input['wipe'] != '0') )) {
         unset($input['lock']);
      }

      unset($input['enroll_status']);
      if (isset($input['_unenroll_request'])) {
         $input['enroll_status'] = 'unenrolling';
      }

      //Send a connection status request to the device
      if (isset($input['_ping_request']) || isset($input['_reboot']) || isset($input['_geolocate_request']) || isset($input['_inventory_request'])) {
         if ($this->getTopic() === null) {
            Session::addMessageAfterRedirect(__("The device is not enrolled yet", 'flyvemdm'));
            return false;
         }
      }

      if (isset($input['_ping_request'])) {
         try {
            $this->sendPingQuery();
         } catch (AgentSendQueryException $exception) {
            Session::addMessageAfterRedirect($exception->getMessage());
            return false;
         }
      }

      if (isset($input['_reboot'])) {
         try {
            $this->sendRebootQuery();
         } catch (AgentSendQueryException $exception) {
            Session::addMessageAfterRedirect($exception->getMessage());
            return false;
         }
      }

      if (isset($input['_geolocate_request'])) {
         try {
            $this->sendGeolocationQuery();
         } catch (AgentSendQueryException $exception) {
            Session::addMessageAfterRedirect($exception->getMessage());
            return false;
         }
      }

      if (isset($input['_inventory_request'])) {
         try {
            $this->sendInventoryQuery();
         } catch (AgentSendQueryException $exception) {
            Session::addMessageAfterRedirect($exception->getMessage());
            return false;
         }
      }

      if (PluginFlyvemdmCommon::isAgent()) {
         if (isset($input['_inventory'])) {
            $this->updateInventory($input['_inventory']);
         }
         if (isset($input['is_online'])) {
            if ($input['is_online'] != "1" && $input['is_online'] != "0") {
               Session::addMessageAfterRedirect(__("Invalid status value", 'flyvemdm'));
               return false;
            }
         }
         $input['last_contact'] = $_SESSION['glpi_currenttime'];
      }

      return $input;
   }

   public function post_addItem() {
      // Notify the agent about its fleets
      $this->updateSubscription();
   }

   public function post_getFromDB() {
      // set Topic after getting an item
      // Useful for post_purgeItem
      $this->getTopic();
      $this->setupMqttAccess();
      $this->fields['api_token'] = User::getToken($this->fields[User::getForeignKeyField()], 'api_token');
   }

   /**
    * Actions done before the DELETE of the item in the database /
    * Maybe used to add another check for deletion
    *
    * @return bool : true if item need to be deleted else false
    */
   public function pre_deleteItem() {
      // get serial of the computer
      $computer = $this->getComputer();
      if ($computer === null) {
         // The associated computer is already deleted
         return true;
      }

      // get the guest profile ID
      $config = Config::getConfigurationValues('flyvemdm', ['guest_profiles_id']);
      $guestProfileId = $config['guest_profiles_id'];
      if ($guestProfileId === null) {
         Session::addMessageAfterRedirect(__('Failed to find the guest user profile', 'flyvemdm'));
         return false;
      }

      $computerId = $computer->getID();
      $serial = $computer->getField('serial');
      $entityId = $this->getField('entities_id');
      $ownerUserId = $computer->getField('users_id');

      // Find other computers belong to the user in the current entity
      // TODO : maybe use getEntityRestrict for multientity support
      if (version_compare(GLPI_VERSION, '9.4') < 0) {
         $condition = "`entities_id`='$entityId' AND `users_id`='$ownerUserId' AND `id` <> '$computerId'";
      } else {
         $condition = [
            'entities_id' => $entityId,
            'users_id' => $ownerUserId,
            'id' => ['!=' => $computerId],
         ];
      }
      if (version_compare(GLPI_VERSION, '9.4') < 0) {
         $order = '';
      } else {
         $order = [];
      }
      $rows = $computer->find($condition, $order, '1');
      if (count($rows) == 0) {
         // Remove guest habilitation for the entity
         $profile_User = new Profile_User();
         $success = $profile_User->deleteByCriteria([
               'users_id'        => $ownerUserId,
               'entities_id'     => $entityId,
               'profiles_id'     => $guestProfileId,
               'is_dynamic'      => 0
         ]);
         if (!$success) {
            Session::addMessageAfterRedirect(__('Failed to remove guest habilitation for the user of the device', 'flyvemdm'));
            return false;
         }
      }

      // Delete the user account of the agent
      $agentUser = new User();
      $agentUser->delete([
            'id'  => $this->fields['users_id'],
      ], true);

      // Send unrolling message to reset agent status
      if ($this->fields['enroll_status'] == 'enrolled') {
         $this->sendUnenrollQuery();
      }

      // Delete the MQTT user for the agent
      if (!empty($serial)) {
         $mqttUser = new PluginFlyvemdmMqttuser();
         if ($mqttUser->getFromDBByCrit(['user' => $serial])) {
            if (!$mqttUser->delete(['id' => $mqttUser->getID()], true)) {
               Session::addMessageAfterRedirect(__('Failed to delete MQTT user for the device', 'flyvemdm'));
               return false;
            }
         }
      }

      // Delete documents associated to the agent
      $document_Item = new Document_Item();
      $success = $document_Item->deleteByCriteria([
            'itemtype'  => PluginFlyvemdmAgent::class,
            'items_id'  => $this->fields['id']
      ]);
      if (!$success) {
         Session::addMessageAfterRedirect(__('Failed to delete documents attached to the device', 'flyvemdm'));
         return false;
      }

      return true;
   }

   /**
    * Actions done after the UPDATE of the item in the database
    * @param integer $history store changes history ? (default 1)
    * @return void
    */
   public function post_updateItem($history = 1) {
      if (in_array('plugin_flyvemdm_fleets_id', $this->updates)) {
         $this->updateSubscription();
         $this->pushFleetPolicies();
         $newFleet = new PluginFlyvemdmFleet();
         if ($newFleet->getFromDB($this->fields['plugin_flyvemdm_fleets_id'])) {
            // Create task status for the agent and the applied policies
            $this->createTaskStatuses($newFleet);
         }

         // update tasks for the agent from already applied policies in the old fleet
         if (isset($this->oldvalues['plugin_flyvemdm_fleets_id'])) {
            $oldFleet = new PluginFlyvemdmFleet();
            $oldFleet->getFromDB($this->oldvalues['plugin_flyvemdm_fleets_id']);
            $this->cancelTaskStatuses($oldFleet);
         }
      }

      // If both wipe and lock are enabled for the device, only send wipe command
      if (in_array('wipe', $this->updates) && $this->fields['wipe'] != '0') {
         $this->sendWipeQuery();
      }

      if (in_array('lock', $this->updates) && $this->fields['wipe'] == '0') {
         if ($this->fields['lock'] != '0') {
            $this->sendLockQuery();
         } else {
            $this->sendUnlockQuery();
         }
      }

      if (in_array('enroll_status', $this->updates) && $this->fields['enroll_status'] == 'unenrolling') {
         $this->sendUnenrollQuery();
      }
   }

   /**
    * creates task statuses for the agent and the associated fleet
    * @param PluginFlyvemdmNotifiableInterface $notifiable
    */
   private function createTaskStatuses(PluginFlyvemdmNotifiableInterface $notifiable) {
      $notifiableType = $notifiable->getType();
      $notifiableId = $notifiable->getID();
      $task = new PluginFlyvemdmTask();
      if (version_compare(GLPI_VERSION, '9.4') < 0) {
         $condition = "`itemtype_applied` = '$notifiableType' AND `items_id_applied` = '$notifiableId'";
      } else {
         $condition = [
            'itemtype_applied' => $notifiableType,
            'items_id_applied' => $notifiableId,
         ];
      }
      $rows = $task->find($condition);
      foreach ($rows as $row) {
         $taskStatus = new PluginFlyvemdmTaskstatus();
         $taskStatus->add([
            'plugin_flyvemdm_agents_id' => $this->getID(),
            'plugin_flyvemdm_tasks_id'  => $row['id'],
            'status'                    => 'pending',
         ]);
      }
   }

   /**
    * cancels task statuses for the agent anf the given fleet
    * @param PluginFlyvemdmNotifiableInterface $notifiable
    */
   private function cancelTaskStatuses(PluginFlyvemdmNotifiableInterface $notifiable) {
      global $DB;

      $notifiableId = $notifiable->getID();
      $taskStatusTable = PluginFlyvemdmTaskstatus::getTable();
      $taskTable = PluginFlyvemdmTask::getTable();
      $request = [
         'SELECT'     => $taskStatusTable . '.*',
         'FROM'       => $taskStatusTable,
         'INNER JOIN' => [
            $taskTable => [
               'FKEY' => [
                  $taskTable       => 'id',
                  $taskStatusTable => PluginFlyvemdmTask::getForeignKeyField(),
               ],
            ],
         ],
         'WHERE'      => [
            'items_id_applied' => [$notifiableId],
         ],
      ];
      $status = new PluginFlyvemdmTaskstatus();
      foreach ($DB->request($request) as $row) {
         $status->update([
            'id' => $row['id'],
            'status' => 'canceled',
         ]);
      }
   }

   /**
    * Actions done after the restore of the item
    * @return void
    */
   public function post_restoreItem() {

      $computer = $this->getComputer();
      if ($computer !== null) {
         $mqttUser = new PluginFlyvemdmMqttuser();
         if ($mqttUser->getFromDB($this->fields['computers_id'])) {
            $mqttUser->update([
                  'id'        => $mqttUser->getID(),
                  'enabled'   => '1'
            ], 0);
         }
      }
   }

   /**
    * Actions done after the purge of an item
    */
   public function post_purgeItem() {
      $this->cleanupSubtopics();
   }

   public function getSearchOptionsNew() {
      return $this->rawSearchOptions();
   }

   public function rawSearchOptions() {
      if (method_exists('CommonDBTM', 'rawSearchOptions')) {
         $tab = parent::rawSearchOptions();
      } else {
         $tab = parent::getSearchOptionsNew();
      }

      $tab[] = [
         'id'                 => '2',
         'table'              => $this->getTable(),
         'field'              => 'id',
         'name'               => __('ID'),
         'massiveaction'      => false,
         'datatype'           => 'number'
      ];

      $tab[] = [
         'id'                 => '3',
         'table'              => PluginFlyvemdmFleet::getTable(),
         'field'              => 'name',
         'name'               => __('Fleet', 'flyvemdm'),
         'datatype'           => 'dropdown'
      ];

      $tab[] = [
         'id'                 => '4',
         'table'              => Computer::getTable(),
         'field'              => 'id',
         'name'               => __('Computer'),
         'datatype'           => 'dropdown',
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '7',
         'table'              => PluginFlyvemdmFleet::getTable(),
         'field'              => 'id',
         'name'               => __('Fleet - ID'),
         'massiveaction'      => false,
         'datatype'           => 'number'
      ];

      $tab[] = [
         'id'                 => '8',
         'table'              => $this->getTable(),
         'field'              => 'last_contact',
         'name'               => __('Last contact', 'flyvemdm'),
         'datatype'           => 'datetime',
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '10',
         'table'              => $this->getTable(),
         'field'              => 'version',
         'name'               => __('Version'),
         'datatype'           => 'string',
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '11',
         'table'              => $this->getTable(),
         'field'              => 'is_online',
         'name'               => __('Is online', 'flyvemdm'),
         'datatype'           => 'specific',
         'searchtype'         => ['equals'],
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '12',
         'table'              => $this->getTable(),
         'field'              => 'mdm_type',
         'name'               => __('MDM type', 'flyvemdm'),
         'datatype'           => 'specific',
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '13',
         'table'              => $this->getTable(),
         'field'              => 'has_system_permission',
         'name'               => __('Has system permission', 'flyvemdm'),
         'datatype'           => 'bool',
         'searchtype'         => ['equals'],
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '14',
         'table'              => $this->getTable(),
         'field'              => 'enroll_status',
         'name'               => __('Enroll status', 'flyvemdm'),
         'datatype'           => 'bool',
         'searchtype'         => ['equals'],
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '15',
         'table'              => $this->getTable(),
         'field'              => 'wipe',
         'name'               => __('Wipe requested', 'flyvemdm'),
         'datatype'           => 'bool',
         'searchtype'         => ['equals'],
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '16',
         'table'              => $this->getTable(),
         'field'              => 'lock',
         'name'               => __('Lock requested', 'flyvemdm'),
         'datatype'           => 'bool',
         'searchtype'         => ['equals'],
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '17',
         'table'              => Entity::getTable(),
         'field'              => 'completename',
         'name'               => __('Entity'),
         'datatype'           => 'dropdown'
      ];

      $tab[] = [
         'id'                 => '18',
         'table'              => PluginFlyvemdmPolicy::getTable(),
         'field'              => 'name',
         'name'               => __('Applied policy', 'flyvemdm'),
         'datatype'           => 'dropdown',
         'comments'           => '1',
         'nosort'             => true,
         'joinparams'         => [
            'beforejoin'         => [
               'table'           => PluginFlyvemdmTask::getTable(),
               'joinparams'      => [
                  'jointype'     => 'child',
                  'linkfield'    => 'items_id_applied',
                  'condition'    => "AND NEWTABLE.`itemtype_applied`='" . PluginFlyvemdmAgent::class . "'",
               ],
            ],
            'jointype'           => 'empty',
         ],
         'massiveaction'      => false
      ];

      return $tab;
   }

   /**
    * Limits search for agents of guest user
    */
   public static function addDefaultJoin($ref_table, $already_link_tables) {
      $join = '';

      $config = Config::getConfigurationValues('flyvemdm', ['guest_profiles_id']);
      $guestProfileId = $config['guest_profiles_id'];
      if ($_SESSION['glpiactiveprofile']['id'] == $guestProfileId) {
         $computerTable = Computer::getTable();
         $join = "LEFT JOIN `$computerTable` AS `c` ON `$ref_table`.`computers_id`=`c`.`id` ";
      }

      return $join;
   }

   /**
    * Limit search for agents if guest user
    */
   public static function addDefaultWhere() {
      $where = '';

      $config = Config::getConfigurationValues('flyvemdm', ['guest_profiles_id']);
      $guestProfileId = $config['guest_profiles_id'];
      if ($_SESSION['glpiactiveprofile']['id'] == $guestProfileId) {
         $userId = $_SESSION['glpiID'];
         $where = " `c`.`users_id`='$userId'";
      }

      return $where;
   }

   /**
    * Returns the topic the agent shall listen
    *
    * @return string MQTT Topic
    *
    */
   public function getSubscribedTopic() {
      $fleet = new PluginFlyvemdmFleet();
      $subscribedTopic = null;
      if ($fleet->getFromDB($this->fields['plugin_flyvemdm_fleets_id'])) {
         if (! $fleet->fields['is_default']) {
            $subscribedTopic = $fleet->getTopic();
         }
      }
      return $subscribedTopic;
   }

   /**
    * Send to an agent an up to date list of MQTT topics it must subscribe
    */
   public function updateSubscription() {
      $topicToSubscribe = $this->getSubscribedTopic();

      $topicList = [
         'subscribe' => [
            ['topic' => $topicToSubscribe]
         ]
      ];

      $message = json_encode($topicList, JSON_UNESCAPED_SLASHES);
      $brokerMessage = new BrokerMessage($message);
      $envelopeConfig = [];
      $topic = $this->getTopic();
      if ($topic !== null) {
         $finalTopic = "$topic/Command/Subscribe";
         $envelopeConfig[] = new MqttEnvelope([
            'topic'  => $finalTopic,
            'retain' => 1,
         ]);
      }
      $envelope = new BrokerEnvelope($brokerMessage, $envelopeConfig);
      $this->notify($envelope);
   }

   /**
    * Update policies applied if the device change of fleet
    */
   public function pushFleetPolicies() {
      $config = Config::getConfigurationValues('flyvemdm', ['fcm_enabled']);
      if (!$config['fcm_enabled']) {
         return;
      }

      $topic = $this->getTopic();
      $scope = $this->getPushNotificationInfo();

      $fleetId = $this->getFleet()->getID();
      $itemtype = PluginFlyvemdmFleet::getType();

      $task = new PluginFlyvemdmTask();
      if (version_compare(GLPI_VERSION, '9.4') < 0) {
         $condition = "`itemtype_applied` = '$itemtype' AND `items_id_applied` = '$fleetId'";
      } else {
         $condition = [
            'itemtype_applied' => $itemtype,
            'items_id_applied' => $fleetId,
         ];
      }
      $appliedPolicies = $task->find($condition);
      foreach ($appliedPolicies as $taskId => $appliedPolicy) {
         $policyId = $appliedPolicy[PluginFlyvemdmPolicy::getForeignKeyField()];
         $envelopeConfig = [];
         try {
            list($policyMessage, $policyName) = $task->buildPolicyMessage($policyId,
               $appliedPolicy['value'], $appliedPolicy['itemtype'], $appliedPolicy['items_id'],
               $taskId);
         } catch (TaskPublishPolicyPolicyNotFoundException $e) {
            continue;
         }
         $message = json_encode($policyMessage, JSON_UNESCAPED_SLASHES);
         $brokerMessage = new BrokerMessage($message);
         if ($topic !== null) {
            $finalTopic = "$topic/Policy/$policyName/Task/$taskId";
            $envelopeConfig[] = new FcmEnvelope([
               'topic'  => $finalTopic,
               'scope' => $scope,
            ]);
         }
         $envelope = new BrokerEnvelope($brokerMessage, $envelopeConfig);
         $this->notify($envelope);
      }
   }

   /**
    * get the MQTT topic of the agent
    *
    * @return string|null the permanent MQTT topic of the agent.
    */
   public function getTopic() {
      if ($this->topic === null) {
         $computer = $this->getComputer();
         if ($computer !== null) {
            $serial = $computer->getField('serial');
            if (strlen($serial)) {
               $entity = $this->getField('entities_id');
               $this->topic = "$entity/agent/$serial";
            }
         }
      }

      return $this->topic;
   }

   /**
    * get an agent from DB by topic
    *
    * @param string $topic a M2M topic
    * @return bool
    */
   public function getByTopic($topic) {
      $mqttPath = explode('/', $topic);
      if (!isset($mqttPath[2])) {
         return false;
      }
      if ($mqttPath[1] != 'agent') {
         return false;
      }
      $entity = (int) $mqttPath[0];
      $serial = $mqttPath[2];
      if (strlen($serial) <= 0) {
         return false;
      }
      if (method_exists($this, 'getFromDBByRequest')) {
         return $this->getFromDbByRequest([
            'LEFT JOIN' => [
               Computer::getTable() => [
                  'FKEY' => [
                     Computer::getTable() => 'id',
                     self::getTable() => Computer::getForeignKeyField(),
                  ]
               ]
            ],
            'WHERE' => [
               'AND' => [
                  PluginFlyvemdmAgent::getTable() . '.' . Entity::getForeignKeyField() => $entity,
                  Computer::getTable() . '.serial' => $serial
               ]
            ]
         ]);
      } else {
         $computerTable = Computer::getTable();
         $agentTable = self::getTable();
         return $this->getFromDBByQuery("LEFT JOIN `$computerTable` `c` ON (
                                    `c`.`id` = `$agentTable`.`computers_id`
                                 )
                                 WHERE `$agentTable`.`entities_id`='$entity' AND `c`.`serial` = '$serial'");
      }
   }


   /**
    * Checks if the data provided for enrollment satisfy our requirements
    * @param array $authFactors
    * @return integer enroll method
    */
   protected static function checkChallengeCombinations($authFactors) {
      $method = self::ENROLL_DENY;

      if (array_key_exists('email', $authFactors) && array_key_exists('agentToken', $authFactors)) {
         // require challenge on email and an agent token only
         if (count($authFactors) == 2) {
            $method = self::ENROLL_AGENT_TOKEN;
         }

      } else if (array_key_exists('entityToken', $authFactors)) {
         // or require challenge on a entity token only
         if (count($authFactors) == 1) {
            $method = self::ENROLL_ENTITY_TOKEN;
         }
      }

      return $method;
   }

   /**
    * Attempt to enroll using an invitation token
    * @param array $input Enrollment data
    * @return array|bool
    */
   protected function enrollByInvitationToken($input) {
      global $LOADED_PLUGINS, $DB;

      $invitationToken  = isset($input['_invitation_token']) ? $input['_invitation_token'] : null;
      $email            = isset($input['_email']) ? $input['_email'] : null;
      $csr              = isset($input['csr']) ? $input['csr'] : null;
      $firstname        = isset($input['firstname']) ? $input['firstname'] : null;
      $lastname         = isset($input['lastname']) ? $input['lastname'] : null;
      $version          = isset($input['version']) ? $input['version'] : null;
      $mdmType          = isset($input['type']) ? $input['type'] : null;
      $notificationType = isset($input['notification_type']) ? $input['notification_type'] : null;
      $notificationToken= isset($input['notification_token']) ? $input['notification_token'] : null;
      $inventory        = isset($input['inventory']) ? htmlspecialchars_decode(base64_decode($input['inventory']),
         ENT_COMPAT | ENT_XML1) : null;
      $systemPermission = isset($input['has_system_permission']) ? $input['has_system_permission'] : 0;
      // For non-android agents, system permssion might be forced to 1 depending on the lack of such constraint

      $input = [];

      $config = Config::getConfigurationValues('flyvemdm', [
         'mqtt_tls_for_clients',
         'mqtt_use_client_cert',
         'debug_noexpire',
         'debug_save_inventory',
         'computertypes_id',
         'agentusercategories_id',
         'agent_profiles_id',
         'mqtt_enabled',
         'fcm_enabled',
         'fcm_api_token',
      ]);

      // Find the invitation
      // TODO : use $this->invitation for all other checks being logged in invitationlog
      $this->invitation = new PluginFlyvemdmInvitation();
      if (!$this->invitation->getFromDBByToken($invitationToken)) {
         $this->filterMessages(__('Invitation token invalid', 'flyvemdm'));
         return false;
      }
      // TODO : use $this->invitation for all other checks being logged in invitationlog

      if (!$this->checkNotificationForEnrollment($notificationType, $notificationToken, $config)) {
         return false;
      }

      if ($config['fcm_enabled'] && $notificationType == 'fcm') {
         // let's try first if the connection works
         $fcmMiddleware = new FcmMiddleware();
         $fcmConnection = $fcmMiddleware->getConnection($config['fcm_api_token']);
         if (!$fcmConnection->testConnection(new ZendGcmMessage(), $notificationToken)) {
            $event = __('Invalid FCM credentials', 'flyvemdm');
            $this->logInvitationEvent($this->invitation, $event);
            return false;
         }
      }

      if (empty($inventory)) {
         $event = __('Device inventory XML is mandatory', 'flyvemdm');
         $this->logInvitationEvent($this->invitation, $event);
         return false;
      }

      if ($config['debug_save_inventory'] != '0') {
         PluginFlyvemdmCommon::saveInventoryFile($inventory, $invitationToken);
      }
      $parsedXml = PluginFlyvemdmCommon::parseXML($inventory);
      if (!$parsedXml) {
         $event = __('Inventory XML is not well formed', 'flyvemdm');
         $this->logInvitationEvent($this->invitation, $event);
         return false;
      }

      if (empty($version)) {
         $event = __('Agent version missing', 'flyvemdm');
         $this->logInvitationEvent($this->invitation, $event);
         return false;
      }

      if (empty($mdmType)) {
         $event = __('MDM type missing', 'flyvemdm');
         $this->logInvitationEvent($this->invitation, $event);
         return false;
      }

      if (!in_array($mdmType, array_keys($this::getEnumMdmType()))) {
         $event = __('unknown MDM type', 'flyvemdm');
         $this->logInvitationEvent($this->invitation, $event);
         return false;
      }

      if (preg_match(PluginFlyvemdmCommon::SEMVER_VERSION_REGEX, $version) !== 1) {
         $event = __('Bad agent version', 'flyvemdm');
         $this->logInvitationEvent($this->invitation, $event);
         return false;
      }

      // Check the agent matches the minimum version requirement of the backend
      $minVersion = $this->getMinVersioForType($mdmType);
      if (version_compare($minVersion, $version) > 0) {
         $event = __('The agent version is too low', 'flyvemdm');
         $this->logInvitationEvent($this->invitation, $event);
         return false;
      }

      // Check the agent shall provide or not the system permissions flag
      switch ($mdmType) {
         case 'android':
            if ($systemPermission === null) {
               $event = __('The agent does not advertise its system permissions', 'flyvemdm');
               $this->logInvitationEvent($this->invitation, $event);
               return false;
            }
      }

      // Check the invitation is pending
      if ($this->invitation->getField('status') != 'pending') {
         $event = __('Invitation is not pending', 'flyvemdm');
         $this->logInvitationEvent($this->invitation, $event);
         return false;
      }

      // Check the token has not yet expired
      if ($this->invitation->getField('expiration_date') === null) {
         $event = __('Expiration date of the invitation is not set', 'flyvemdm');
         $this->logInvitationEvent($this->invitation, $event);
         return false;
      }
      $currentDatetime = new DateTime("now");
      $expirationDatetime = new DateTime($this->invitation->getField('expiration_date'));
      if ($currentDatetime >= $expirationDatetime) {
         $event = __('Invitation token expired', 'flyvemdm');
         $this->logInvitationEvent($this->invitation, $event);
         return false;
      }

      // Check the given email belongs to the same user than the user in the invitation
      $user = new User();
      $userTable = User::getTable();
      $condition = $userTable.".`id`='" . $this->invitation->getField('users_id') . "'";
      if (version_compare(GLPI_VERSION, '9.3-dev') >= 0) {
         $condition = [$userTable . '.id' => $this->invitation->getField('users_id')];
      }
      if ($user->getFromDBbyEmail($email, $condition) === false) {
         $event = __('Wrong email address', 'flyvemdm');
         $this->logInvitationEvent($this->invitation, $event);
         return false;
      }
      $userId = $user->getId();

      $entityId = $this->invitation->getField('entities_id');

      //sign the agent's certificate (if TLS enabled)
      $input['certificate'] = '';
      if ($config['mqtt_tls_for_clients'] != '0' && $config['mqtt_use_client_cert'] != '0') {
         $answer = self::signCertificate($csr);
         $crt = isset($answer['crt']) ? $answer['crt'] : false;
         if ($crt === false) {
            $event = __("Failed to sign the certificate", 'flyvemdm')  . "\n " . $answer['message'];
            $this->logInvitationEvent($this->invitation, $event);
            return false;
         }
         $input['certificate'] = $crt;
      }

      // Prepare invitation update
      $invitationInput = [
         'id'                 => $this->invitation->getID(),
         'status'             => 'done'
      ];

      // Invalidate the token
      if ($config['debug_noexpire'] == '0') {
         $invitationInput['expiration_date'] = '0000-00-00 00:00:00';
         $invitationInput['status']          = 'done';

         // Update the invitation
         if (!$this->invitation->update($invitationInput)) {
            $event = __("Failed to update the invitation", 'flyvemdm');
            $this->logInvitationEvent($this->invitation, $event);
            return false;
         }
      }

      // Create the device
      $pfCommunication = new PluginFusioninventoryCommunication();
      $_SESSION['glpi_fusionionventory_nolock'] = true;
      ob_start();
      if (!key_exists('glpi_plugin_fusioninventory', $_SESSION) || !key_exists('xmltags',
            $_SESSION['glpi_plugin_fusioninventory'])) {
         // forced reload of the FI plugin for correct import of inventory
         unset($LOADED_PLUGINS['fusioninventory']);
         Plugin::load('fusioninventory');
      }
      $pfCommunication->handleOCSCommunication('', $inventory, 'glpi');
      $fiOutput = ob_get_contents();
      ob_end_clean();
      if (strlen($fiOutput) != 0) {
         // FI print errors to sdt output and agents needs a correct response, let's save this to logs.
         $this->logInvitationEvent($this->invitation, $fiOutput, false);
      }
      unset($_SESSION['glpi_fusionionventory_nolock']);
      $fiAgentId = $_SESSION['plugin_fusioninventory_agents_id']; // generated by FusionInventory

      if ($fiAgentId === 0 || !property_exists($parsedXml, 'DEVICEID')) {
         $event = __('Cannot get the FusionInventory agent', 'flyvemdm');
         $this->logInvitationEvent($this->invitation, $event);
         return false;
      }

      $pfAgent = new PluginFusioninventoryAgent();
      if (!$pfAgent->getFromDBByCrit(['device_id' => $parsedXml->DEVICEID])) {
         $event = __('FusionInventory agent not created', 'flyvemdm');
         $this->logInvitationEvent($this->invitation, $event);
         return false;
      }
      $computerId = $pfAgent->getField(Computer::getForeignKeyField());

      if ($computerId === 0) {
         $event = __("Cannot create the device", 'flyvemdm');
         $this->logInvitationEvent($this->invitation, $event);
         return false;
      }

      // Set the type of computer
      $computerTypeId = $config['computertypes_id'];
      if ($computerTypeId == -1 || $computerTypeId === false) {
         $computerTypeId = 0;
      }
      $computer = new Computer();
      $computer->update([
         'id'               => $computerId,
         'computertypes_id' => $computerTypeId,
         'users_id'         => $userId,
      ]);

      // Awful hack because the current user profile does not
      // have more rights than the profile of the agent.
      // @see User::post_addItem()
      // @see Profle::currentUserHaveMoreRightThan()
      // @see Profile::getUnderActiveProfileRestrictRequest()
      $backupProfileRight = $_SESSION['glpiactiveprofile']['profile'];
      $_SESSION['glpiactiveprofile']['profile'] = $_SESSION['glpiactiveprofile']['profile'] | CREATE;

      //create agent user account
      $agentAccount = new User();
      $agentAccount->add([
         'usercategories_id' => $config['agentusercategories_id'],
         'name'              => 'flyvemdm-' . PluginFlyvemdmCommon::generateUUID(),
         'realname'          => $computer->getField('serial'),
         '_profiles_id'      => $config['agent_profiles_id'],
         'profiles_id'       => $config['agent_profiles_id'],      // Default profile when user logs in
         '_entities_id'      => $entityId,
         '_is_recursive'     => 0,
         'authtype'          => Auth::DB_GLPI,
      ]);

      // End of awful hack !
      $_SESSION['glpiactiveprofile']['profile'] = $backupProfileRight;

      if ($agentAccount->isNewItem()) {
         $event = __('Cannot create a user account for the agent', 'flyvemdm');
         $this->logInvitationEvent($this->invitation, $event);
         return false;
      }

      $agentToken = User::getToken($agentAccount->getID(), 'api_token');
      if ($agentToken === false) {
         $event = __('Cannot create the API token for the agent', 'flyvemdm');
         $this->logInvitationEvent($this->invitation, $event);
         return false;
      }

      // Create the agent
      $defaultFleet = PluginFlyvemdmFleet::getDefaultFleet();
      if ($defaultFleet === null) {
         $event = __("No default fleet available for the device", 'flyvemdm');
         $this->logInvitationEvent($this->invitation, $event);
         return false;
      }

      // Enrollment is about to succeed, then update the user
      if (!empty($user->getField('firstname')) || !empty($user->getField('lastname'))) {
         $user->update([
               'id'        => $userId,
               'firstname' => $firstname,
               'realname'  => $lastname,
         ]);
      }

      // Enrollment is about to succeed then cleanup subtopics
      $this->fields['computers_id'] = $computerId;
      $this->fields['entities_id']  = $entityId;
      $this->cleanupSubtopics();

      $input['name']                      = $email;
      $input['computers_id']              = $computerId;
      $input['entities_id']               = $entityId;
      $input['plugin_flyvemdm_fleets_id'] = $defaultFleet->getID();
      $input['_invitations_id']           = $this->invitation->getID();
      $input['enroll_status']             = 'enrolled';
      $input['version']                   = $version;
      $input['users_id']                  = $agentAccount->getID();
      $input['mdm_type']                  = $mdmType;
      $input['systemPermission']          = $systemPermission;
      $input['notification_type']         = $notificationType;
      $input['notification_token']        = $notificationToken;
      return $input;
   }

   /**
    * @param string $serial
    * @param array $authFactors
    * @param string $csr Certificate Signing Request from the agent
    * @param &string $notFoundMessage Contains the error message if the enrollment failed
    * @return boolean|PluginFlyvemdmAgent
    *
    */
   //protected static function enrollByEntityToken($serial, $authFactors, $csr, &$errorMessage) {
      //global $DB;

      //$token = $DB->escape($authFactors['entityToken']);

      //// Find an entity matching the given token
      //$entity = new PluginFlyvemdmEntityconfig();
      //if (!$entity->getFromDBByCrit(['enroll_token' => $token])) {
      //   $errorMessage = "no entity token not found";
      //   return false;
      //}

      //// Create a new computer for the device being enrolled
      //// TODO : Enable localization of the type
      //$computerType = new ComputerType();
      //$computerTypeId = $computerType->import(['name' => 'Smartphone']);
      //if ($computerTypeId == -1 || $computerTypeId === false) {
      //   $computerTypeId = 0;
      //}
      //$computer = new Computer();
      //$condition = "`serial`='" . $DB->escape($serial) . "' AND `entities_id`='" . $entity->getID() . "'";
      //$computerCollection = $computer->find($condition);
      //if (count($computerCollection) > 1) {
      //   $errorMessage = "failed to find the computer";
      //   return false;
      //}
      //if (count($computerCollection) == 1) {

      //   reset($computerCollection);
      //   $computer->getFromDB(key($computerCollection));
      //   $computerId = $computer->getID();

      //} else {
      //   $computerId = $computer->add([
      //      'entities_id'        => $entity->getID(),
      //      'serial'             => $serial,
      //      'computertypes_id'   => $computerTypeId
      //   ]);

      //   if ($computerId === false) {
      //      $errorMessage = "failed to create the computer";
      //      return false;
      //   }
      //}

      //if (! $computerId > 0) {
      //   $errorMessage = "failed to update the computer";
      //   return false;
      //}

      //// Create an agent for this device, linked to the new computer
      //$agent = new PluginFlyvemdmAgent();
      //$condition = "`computers_id`='$computerId'";
      //$agentCollection = $agent->find($condition);
      //if (count($agentCollection) > 1) {
      //   return false;
      //}
      //if (count($agentCollection) == 1) {

      //   reset($agentCollection);
      //   $agent->getFromDB(key($agentCollection));
      //   $agentId = $agent->getId();

      //} else {
      //   $agentId = $agent->add([
      //         'entities_id'     => $entity->getID(),
      //         'computers_id'    => $computer->getID(),
      //         'token_expire'    => '0000-00-00 00:00:00'
      //   ]);
      //}

      //if (! $agentId > 0) {
      //   return false;
      //}

      //return $agent;

   //}

   /**
    * Erase delete persisted MQTT topics of the agent
    */
   public function cleanupSubtopics() {
      $message = '';
      $brokerMessage = new BrokerMessage($message);
      $envelopeConfig = [];
      $topic = $this->getTopic();
      if ($topic !== null) {
         foreach (self::getTopicsToCleanup() as $subTopic) {
            $finalTopic = "$topic/$subTopic";
            $envelopeConfig[] = new MqttEnvelope([
               'topic'  => $finalTopic,
               'retain' => 1,
            ]);
         }
      }
      $envelope = new BrokerEnvelope($brokerMessage, $envelopeConfig);
      $this->notify($envelope);
   }

   /**
    * list of topics to cleanup on unenrollment or on enrollment
    *
    * @return string[]
    */
   public static function getTopicsToCleanup() {
      $policy = new PluginFlyvemdmPolicy();
      $rows = $policy->find();

      // get all policies sub topics
      $topics = [];
      foreach ($rows as $row) {
         $topics[] = 'Policy/' . $row['symbol'];
      }
      return array_merge($topics, [
         'Command/Subscribe',
         'Command/Ping',
         'Command/Reboot',
         'Command/Geolocate',
         'Command/Inventory',
         'Command/Lock',
         'Command/Wipe',
         'Command/Unenroll',
      ]);
   }

   /**
    * Send an geolocation request to the agent
    *
    * @return bool
    * @throws AgentSendQueryException
    */
   private function sendGeolocationQuery() {

      $computerId = $this->fields['computers_id'];
      $geolocation = new PluginFlyvemdmGeolocation();
      if (version_compare(GLPI_VERSION, '9.4') < 0) {
         $condition = "`computers_id`='$computerId'";
         $order = '`date` DESC, `id` DESC';
      } else {
         $condition = [
            'computers_id' => $computerId,
         ];
         $order = [
            'date DESC',
            'id DESC',
         ];
      }
      $lastPositionRows = $geolocation->find($condition, $order, '1');
      $lastPosition = array_pop($lastPositionRows);

      $message = json_encode(['query' => 'Geolocate'], JSON_UNESCAPED_SLASHES);
      $brokerMessage = new BrokerMessage($message);
      $envelopeConfig = [];
      $topic = $this->getTopic();
      if ($topic !== null) {
         $finalTopic = $topic . "/Command/Geolocate";
         $envelopeConfig[] = new MqttEnvelope([
            'topic' => $finalTopic,
         ]);
         $envelopeConfig[] = new FcmEnvelope([
            'topic' => $finalTopic,
            'scope' => $this->getPushNotificationInfo(),
         ]);
      }
      $envelope = new BrokerEnvelope($brokerMessage, $envelopeConfig);
      $this->notify($envelope);

      // Wait for a reply within a short delay
      $loopCount = 50;
      while ($loopCount > 0) {
         usleep(200000); // 200 milliseconds
         $loopCount--;
         if (version_compare(GLPI_VERSION, '9.4') < 0) {
            $condition = "`computers_id`='$computerId'";
            $order = '`date` DESC, `id` DESC';
         } else {
            $condition = [
               'computers_id' => $computerId,
            ];
            $order = [
               'date DESC',
               'id DESC',
            ];
         }
         $updatedPositionRows = $geolocation->find($condition, $order, '1');
         $updatedPosition = array_pop($updatedPositionRows);
         if ($lastPosition === null && $updatedPosition !== null
            || $lastPosition !== null && $lastPosition['id'] != $updatedPosition['id']) {
            if ($updatedPosition['latitude'] == 'na') {
               throw new AgentSendQueryException(__('GPS is turned off or is not ready', 'flyvemdm'));
            }
            Session::addMessageAfterRedirect(__('The device sent its position', 'flyvemdm'));
            return true;
         }
      }
      throw new AgentSendQueryException(__('Timeout requesting position', 'flyvemdm'));
   }

   /**
    * Send an inventory request to the device
    *
    * @return bool
    * @throws AgentSendQueryException
    */
   private function sendInventoryQuery() {
      $message = json_encode(['query' => 'Inventory'], JSON_UNESCAPED_SLASHES);
      $brokerMessage = new BrokerMessage($message);
      $envelopeConfig = [];
      $topic = $this->getTopic();
      if ($topic !== null) {
         $finalTopic = $topic . "/Command/Inventory";
         $envelopeConfig[] = new MqttEnvelope([
            'topic' => $finalTopic,
         ]);
         $envelopeConfig[] = new FcmEnvelope([
            'topic' => $finalTopic,
            'scope' => $this->getPushNotificationInfo(),
         ]);
      }
      $envelope = new BrokerEnvelope($brokerMessage, $envelopeConfig);
      $this->notify($envelope);

      $computerFk = Computer::getForeignKeyField();
      $computerId = $this->fields[$computerFk];
      $inventory = new PluginFusioninventoryInventoryComputerComputer();
      if (version_compare(GLPI_VERSION, '9.4') < 0) {
         $condition = "`$computerFk`='$computerId'";
         $order = '';
      } else {
         $condition = [
            $computerFk => $computerId,
         ];
         $order = [];
      }
      $inventoryRows = $inventory->find($condition, $order, '1');
      $lastInventory = array_pop($inventoryRows);

      $loopCount = 50;
      while ($loopCount > 0) {
         usleep(200000); // 200 milliseconds
         $loopCount--;
         if (version_compare(GLPI_VERSION, '9.4') < 0) {
            $condition = "`$computerFk`='$computerId'";
            $order = '';
         } else {
            $condition = [
               $computerFk => $computerId,
            ];
            $order = [];
         }
         $inventoryRows = $inventory->find($condition, $order, '1');
         $updatedInventory = array_pop($inventoryRows);
         if ($lastInventory === null && $updatedInventory !== null
            || $lastInventory !== null && $lastInventory != $updatedInventory) {
            Session::addMessageAfterRedirect(__('Inventory received', 'flyvemdm'));
            return true;
         }
      }
      throw new AgentSendQueryException(__("Timeout querying the device inventory", 'flyvemdm'));
   }

   /**
    * Sends a message on the subtopic dedicated to ping requests
    *
    * @return bool
    * @throws AgentSendQueryException
    */
   private function sendRebootQuery() {
      $message = json_encode(['query' => 'Reboot'], JSON_UNESCAPED_SLASHES);
      $brokerMessage = new BrokerMessage($message);
      $envelopeConfig = [];
      $topic = $this->getTopic();
      if ($topic !== null) {
         $finalTopic = $topic . "/Command/Reboot";
         $envelopeConfig[] = new MqttEnvelope([
            'topic' => $finalTopic,
         ]);
         $envelopeConfig[] = new FcmEnvelope([
            'topic' => $finalTopic,
            'scope' => $this->getPushNotificationInfo(),
         ]);
      }
      $envelope = new BrokerEnvelope($brokerMessage, $envelopeConfig);
      $this->notify($envelope);

      $loopCount = 50;
      $updatedAgent = new self();
      while ($loopCount > 0) {
         usleep(200000); // 200 milliseconds
         $loopCount--;
         $updatedAgent->getFromDB($this->getID());
         if ($updatedAgent->getField('last_contact') != $this->fields['last_contact']) {
            Session::addMessageAfterRedirect(__('The device restarted', 'flyvemdm'));
            return true;
         }
      }
      throw new AgentSendQueryException(__("Timeout querying the device", 'flyvemdm'));
   }

   /**
    * Sends a message on the subtopic dedicated to ping requests
    *
    * @return bool
    * @throws AgentSendQueryException
    */
   private function sendPingQuery() {
      $message = json_encode(['query' => 'Ping'], JSON_UNESCAPED_SLASHES);
      $brokerMessage = new BrokerMessage($message);
      $envelopeConfig = [];
      $topic = $this->getTopic();
      if ($topic !== null) {
         $finalTopic = $topic . "/Command/Ping";
         $envelopeConfig[] = new MqttEnvelope([
            'topic' => $finalTopic,
         ]);
         $envelopeConfig[] = new FcmEnvelope([
            'topic' => $finalTopic,
            'scope' => $this->getPushNotificationInfo(),
         ]);
      }
      $envelope = new BrokerEnvelope($brokerMessage, $envelopeConfig);
      $this->notify($envelope);

      $loopCount = 50;
      $updatedAgent = new self();
      while ($loopCount > 0) {
         usleep(200000); // 200 milliseconds
         $loopCount--;
         $updatedAgent->getFromDB($this->getID());
         if ($updatedAgent->getField('last_contact') != $this->fields['last_contact']) {
            Session::addMessageAfterRedirect(__('The device answered', 'flyvemdm'));
            return true;
         }
      }
      throw new AgentSendQueryException(__("Timeout querying the device", 'flyvemdm'));
   }

   /**
    * Attempts to sign the certificate against the CA
    * @param string $csr Certificate signing request
    * @return bool|mixed
    */
   protected static function signCertificate($csr) {
      $config = Config::getConfigurationValues('flyvemdm', ['ssl_cert_url']);
      if ($config === null) {
         return false;
      }
      $url = $config['ssl_cert_url'];

      $csr = urlencode($csr);

      $json = file_get_contents("$url/csr_sign.php?csr=$csr");
      if ($json === false) {
         return false;
      }

      $answer = json_decode($json, true);

      return $answer;
   }

   /**
    * @see PluginFlyvemdmNotifiableInterface::getAgents()
    */
   public function getAgents() {
      return [$this];
   }

   /**
    * @see PluginFlyvemdmNotifiableInterface::getPackages()
    */
   public function getPackages() {
      if ($this->isNewItem()) {
         return [];
      }

      $fleet = new PluginFlyvemdmFleet();
      if (!$fleet->getFromDB($this->fields['plugin_flyvemdm_fleets_id'])) {
         return [];
      }

      return $fleet->getPackages();
   }

   /**
    * @see PluginFlyvemdmNotifiableInterface::getFiles()
    */
   public function getFiles() {
      if ($this->isNewItem()) {
         return [];
      }

      $fleet = new PluginFlyvemdmFleet();
      if (!$fleet->getFromDB($this->fields['plugin_flyvemdm_fleets_id'])) {
         return [];
      }

      return $fleet->getFiles();
   }

   /**
    * @see PluginFlyvemdmNotifiableInterface::getFleet()
    */
   public function getFleet() {
      $fleet = null;

      if ($this->isNewItem()) {
         return null;
      }

      // The agent exists in DB
      $fleet = new PluginFlyvemdmFleet();
      if ($fleet->isNewID($this->fields['plugin_flyvemdm_fleets_id'])) {
         return null;
      }
      if (!$fleet->getFromDB($this->fields['plugin_flyvemdm_fleets_id'])) {
         return null;
      }

      return $fleet;
   }

   /**
    * Get the user owner of the agent's device
    */
   public function getOwner() {
      $computer = new Computer();
      if (!$computer->getFromDB($this->fields['computers_id'])) {
         return null;
      }
      $user = new User();
      if (!$user->getFromDB($computer->getField('users_id'))) {
         return null;
      }

      return $user;
   }

   /**
    * Determine the enrollment method depending on input data
    * @param array $input
    * @return integer
    */
   protected function chooseEnrollMethod($input) {
      if (isset($input['_email'])
            && isset($input['_invitation_token'])) {
         return self::ENROLL_INVITATION_TOKEN;
      } else if (isset($input['_entity_token'])) {
         return self::ENROLL_ENTITY_TOKEN;
      }

      return self::ENROLL_DENY;
   }

   /**
    * Creates virtual fields with enrollment data
    */
   protected function setupMqttAccess() {
      if (!isset($_SESSION['glpiID'])) {
         return;
      }

      $user = $this->getOwner();
      if (!$user) {
         return;
      }

      $config = Config::getConfigurationValues('flyvemdm', [
         'guest_profiles_id',
         'android_bugcollecctor_url',
         'android_bugcollector_login',
         'android_bugcollector_passwd',
         'mqtt_broker_address',
         'mqtt_broker_port',
         'mqtt_broker_tls_port',
         'mqtt_tls_for_clients',
      ]);
      $guestProfileId = $config['guest_profiles_id'];
      if ($user->getID() != $_SESSION['glpiID'] || $_SESSION['glpiactiveprofile']['id'] != $guestProfileId) {
         return;
      }
      $mqttClearPassword = '';

      // Create, or re-eanble the mqtt user for the device
      $computer = new Computer();
      if (!$computer->getFromDB($this->fields['computers_id'])) {
         // TODO : failed to find the computer
         return;
      }
      $serial = $computer->getField('serial');
      if (!empty($serial)) {
         $acls = [
            [
               'topic'        => $this->getTopic() . '/Status/#',
               'access_level' => PluginFlyvemdmMqttacl::MQTTACL_WRITE
            ],
            [
               'topic'        => $this->getTopic() . '/Command/#',
               'access_level' => PluginFlyvemdmMqttacl::MQTTACL_READ
            ],
            [
               'topic'        => $this->getTopic() . '/Policy/#',
               'access_level' => PluginFlyvemdmMqttacl::MQTTACL_READ
            ],
            [
               'topic'        => $this->getTopic() . '/FlyvemdmManifest/#',
               'access_level' => PluginFlyvemdmMqttacl::MQTTACL_WRITE
            ],
            [
               'topic'        => 'FlyvemdmManifest/#',
               'access_level' => PluginFlyvemdmMqttacl::MQTTACL_READ
            ],
         ];

         $mqttUser = new PluginFlyvemdmMqttuser();
         $mqttClearPassword = PluginFlyvemdmMqttuser::getRandomPassword();
         // TODO: try make the enrollment fails at this point if getRandomPassword throw exception.
         if (!$mqttUser->getByUser($serial)) {
            // The user does not exists
            $mqttUser->add([
               'user'       => $serial,
               'enabled'    => '1',
               'password'   => $mqttClearPassword,
               '_acl'       => $acls,
               '_reset_acl' => true,
            ]);
         } else {
            // The user exists
            $mqttUser->update([
               'id'         => $mqttUser->getID(),
               'enabled'    => '1',
               'password'   => $mqttClearPassword,
               '_acl'       => $acls,
               '_reset_acl' => true,
            ]);
         }
      }

      // The request comes from the owner of the device or the device itself, mandated by the user
      $this->fields['topic'] = $this->getTopic();
      $this->fields['mqttpasswd'] = $mqttClearPassword;
      $this->fields['broker'] = $config['mqtt_broker_address'];
      $this->fields['port'] = $config['mqtt_tls_for_clients'] !== '0'
         ? $config['mqtt_broker_tls_port']
         : $config['mqtt_broker_port'];
      $this->fields['tls'] = $config['mqtt_tls_for_clients'];
      $this->fields['android_bugcollecctor_url'] = $config['android_bugcollecctor_url'];
      $this->fields['android_bugcollector_login'] = $config['android_bugcollector_login'];
      $this->fields['android_bugcollector_passwd'] = $config['android_bugcollector_passwd'];
   }

   /**
    * If debug node is disabled, disable detailed error messages
    * @param string $error
    */
   protected function filterMessages($error) {
      $config = Config::getConfigurationValues('flyvemdm', ['debug_enrolment']);
      if ($config['debug_enrolment'] == 0) {
         $error = __('Enrollment failed', 'flyvemdm');
      }
      Session::addMessageAfterRedirect($error, false, ERROR);
   }

   /**
    * Logs invitation events
    * @param PluginFlyvemdmInvitation $invitation
    * @param string $event
    * @param boolean $filtered
    */
   protected function logInvitationEvent(PluginFlyvemdmInvitation $invitation, $event, $filtered = true) {
      if ($filtered) {
         $this->filterMessages($event);
      }
      $invitationLog = new PluginFlyvemdmInvitationlog();
      $invitationLog->add([
            'plugin_flyvemdm_invitations_id' => $invitation->getID(),
            'event'                          => $event
      ]);
   }

   /**
    * Update settings related to fleet change
    * @param PluginFlyvemdmFleet $old old fleet
    * @param PluginFlyvemdmFleet $new new fleet
    */
   protected function changeMqttAcl(PluginFlyvemdmFleet $old, PluginFlyvemdmFleet $new) {
      // Update MQTT account
      $computerId = $this->getField('computers_id');
      $mqttUser = new PluginFlyvemdmMqttuser();
      if (method_exists($this, 'getFromDBByRequest')) {
         $request = [
            'LEFT JOIN' => [
               Computer::getTable() => [
                  'FKEY' => [
                     Computer::getTable() => 'serial',
                     PluginFlyvemdmMqttuser::getTable() => 'user'
                  ]
               ]
            ],
            'WHERE' => [Computer::getTable() . '.id' => $computerId]
         ];
         $success = $mqttUser->getFromDBByRequest($request);
      } else {
         $success = $mqttUser->getFromDBByQuery("LEFT JOIN `glpi_computers` `c` ON (`c`.`serial`=`user`) WHERE `c`.`id`='$computerId'");
      }

      if (!$success) {
         return;
      }

      $mqttAcl = new PluginFlyvemdmMqttacl();
      if ($old->getField('is_default') == '0') {
         $mqttAcl->getFromDBByCrit([
            'AND' => [
               'topic'                                      => $old->getTopic() . '/#',
               PluginFlyvemdmMqttuser::getForeignKeyField() => $mqttUser->getID()
            ]
         ]);
         if ($new->getField('is_default') != '0') {
            $mqttAcl->delete(['id' => $mqttAcl->getID()]);
         } else {
            $mqttAcl->update([
               'id'           => $mqttAcl->getID(),
               'topic'        => $new->getTopic() . '/#',
               'access_level' => PluginFlyvemdmMqttacl::MQTTACL_READ
            ]);
         }
      } else {
         $mqttAcl->add([
            'plugin_flyvemdm_mqttusers_id' => $mqttUser->getID(),
            'topic'                        => $new->getTopic() . '/#',
            'access_level'                 => PluginFlyvemdmMqttacl::MQTTACL_READ
         ]);
      }
   }

   /**
    * Get the computer associatated to the agent
    * @return NULL|Computer
    */
   public function getComputer() {
      if (!isset($this->fields['computers_id'])) {
         return null;
      }

      $computer = new Computer();
      if (!$computer->getFromDB($this->fields['computers_id'])) {
         return null;
      }

      return $computer;
   }

   /**
    * @see PluginFlyvemdmNotifiableInterface::notify()
    * @param BrokerEnvelope $envelope
    */
   public function notify(BrokerEnvelope $envelope) {
      $middlewareHandlers = [];
      $config = Config::getConfigurationValues('flyvemdm', ['mqtt_enabled', 'fcm_enabled']);
      if ($config['mqtt_enabled'] && null !== $envelope->get(MqttEnvelope::class)) {
         $middlewareHandlers[] = new MqttMiddleware();
      }
      if ($config['fcm_enabled'] && null !== $envelope->get(FcmEnvelope::class)) {
         $middlewareHandlers[] = new FcmMiddleware();
      }
      $broker = new BrokerBus($middlewareHandlers);
      $broker->dispatch($envelope);
   }

   /**
    * purges agents in the entity being purged
    *
    * @param CommonDBTM $item
    */
   public function hook_entity_purge(CommonDBTM $item) {
      $agent = new static();
      $agent->deleteByCriteria(['entities_id' => $item->getField('id')], 1);
   }

   /**
    * Deletes agents related to the computers id
    * @param CommonDBTM $item
    */
   public function hook_computer_purge(CommonDBTM $item) {
      $agent = new static();
      $agent->deleteByCriteria(['computers_id' => $item->getField('id')], 1);
   }

   public function refreshPersistedNotifications() {
      if ($this->isNewItem()) {
         return;
      }

      if ($this->fields['wipe'] != '0') {
         $this->sendWipeQuery();
      }

      if ($this->fields['lock'] != '0') {
         $this->sendLockQuery();
      }

      if ($this->fields['enroll_status'] != 'unenrolling') {
         $this->sendUnenrollQuery();
      }
   }

   /**
    * Is the agent notifiable ?
    *
    * @return boolean
    */
   public function isNotifiable() {
      return true;
   }

   /**
    * Define how to display a specific value in search result table
    *
    * @param  String $field   Name of the field as define in $this->getSearchOptions()
    * @param  Mixed  $values  The value as it is stored in DB
    * @param  Array  $options Options (optional)
    * @return Mixed           Value to be displayed
    */
   public static function getSpecificValueToDisplay($field, $values, array $options = []) {
      if (!is_array($values)) {
         $values = [$field => $values];
      }
      switch ($field) {
         case 'is_online':
            if (!isAPI()) {
               $class = $values[$field] == 0 ? "plugin-flyvemdm-offline" : "plugin-flyvemdm-online";
               $output = '<i class="fa fa-circle '
                  . $class
                  . '" aria-hidden="true" ></i>';
               if (isset($options['center']) && $options['center']) {
                  $output = '<div style="text-align: center">' . $output . '</div>';
               }
               return $output;
            }
            break;
         case 'mdm_type':
            if (!isAPI()) {
               $enumtypes = self::getEnumMdmType();
               return $enumtypes[$values[$field]];
            }
            break;
      }
      return parent::getSpecificValueToDisplay($field, $values, $options);
   }

   public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = []) {
      if (!is_array($values)) {
         $values = [$field => $values];
      }
      $options['display'] = false;

      switch ($field) {
         case 'is_online' :
            return Dropdown::showFromArray($name, [
               '0' => __('No', 'flyvemdm'),
               '1' => __('Yes', 'flyvemdm'),
            ], [
               'value'               => $values[$field],
               'display_emptychoice' => false,
               'display'             => false
            ]);
            break;
      }
      return parent::getSpecificValueToSelect($field, $name, $values, $options);
   }

   /**
    * Updates the last contact date of the agent
    *
    * The data to update is a datetime
    *
    * @param string $message
    */
   public function updateLastContact($message) {
      if ($message !== '!') {
         return;
      }
      if ($this->fields['is_online'] == 1) {
         $date = new \DateTime("now");
         $this->update([
            'id'              => $this->getID(),
            'last_contact'    => $date->format('Y-m-d H:i:s')
         ]);
      }
   }

   /**
    * Updates the inventory from a notification sent by a device
    * @param string $inventoryXML
    */
   private function updateInventory($inventoryXML) {
      if (!$this->getComputer()) {
         return;
      }
      $_SESSION["MESSAGE_AFTER_REDIRECT"] = [];
      $communication = new PluginFusioninventoryCommunication();
      $communication->handleOCSCommunication('', base64_decode($inventoryXML), 'glpi');
      if (count($_SESSION["MESSAGE_AFTER_REDIRECT"]) > 0) {
         foreach ($_SESSION["MESSAGE_AFTER_REDIRECT"][0] as $logMessage) {
            $logMessage = "Import message: $logMessage\n";
            \Toolbox::logInFile('plugin_flyvemdm_inventory', $logMessage);
         }
      }
   }

   /**
    * Checks that the notification settings for  an enrollment are valid
    *
    * @param string $notificationType
    * @param string $notificationToken
    * @param array $config
    * @return boolean
    */
   private function checkNotificationForEnrollment($notificationType, $notificationToken, array $config) {
      switch ($notificationType) {
         case 'mqtt':
            if (!$config['mqtt_enabled']) {
               $event = sprintf(__('%s service is not available', 'flyvemdm'), 'MQTT');
               $this->logInvitationEvent($this->invitation, $event);
               return false;
            }
            return true;
            break;

         case 'fcm':
            if (!$config['fcm_enabled'] || !$config['fcm_api_token']) {
               $event = sprintf(__('%s service is not available', 'flyvemdm'), 'FCM');
               $this->logInvitationEvent($this->invitation, $event);
               return false;
            }
            if ($notificationToken === null) {
               $event = __('Notification token is missing', 'flyvemdm');
               $this->logInvitationEvent($this->invitation, $event);
               return false;
            }
            return true;
            break;
      }

      $event = __('Notification settings are invalid', 'flyvemdm');
      $this->logInvitationEvent($this->invitation, $event);
      return false;
   }

   /**
    * Get the notification token for each device
    * @return array
    */
   public function getPushNotificationInfo() {
      $devicesInfo[] = [
         'type'  => ($this->fields['notification_type'] != 'mqtt') ? $this->fields['notification_type'] : '',
         'token' => $this->fields['notification_token'] ? $this->fields['notification_token'] : '',
      ];
      return $devicesInfo;
   }
}
