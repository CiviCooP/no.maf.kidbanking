<?php

/**
 * Collection of upgrade steps.
 */
class CRM_Kidbanking_Upgrader extends CRM_Kidbanking_Upgrader_Base {

  public function install() {
    $this->addMatcher('kid_matcher', 'KID Matcher', 'CRM_Banking_PluginImpl_Matcher_KID');
    $this->addMatcher('ocr_standingorder', 'Standing Order Matcher', 'CRM_Banking_PluginImpl_Matcher_StandingOrder');

    // Add the OCR bank account type
    civicrm_api3('OptionValue', 'create', array(
      'option_group_id' => 'civicrm_banking.reference_types',
      'name' => 'kid',
      'label' => 'KID Number',
      'value' => 'kid',
    ));
  }

  public function uninstall() {
    $this->removeMatcher('kid_matcher');
    $this->removeMatcher('ocr_standing_order');
  }

  private function addMatcher($name, $label, $class) {
    try {
      $matcher_id = civicrm_api3('OptionValue', 'getvalue', array(
        'return' => "id",
        'option_group_id' => "civicrm_banking.plugin_types",
        'name' => $name,
      ));
      civicrm_api3('OptionValue', 'delete', array('id' => $matcher_id));
    } catch (Exception $e) {
      // doesn't exist yet
      $result = civicrm_api3('OptionValue', 'create', array(
        'option_group_id'  => "civicrm_banking.plugin_types",
        'name'             => $name,
        'label'            => $label,
        'value'            => $class,
        'is_default'       => 0
      ));
      $matcher_id = $result['id'];
    }

    // then, find the correct plugin type
    $matcher_plugin_class = civicrm_api3('OptionValue', 'getsingle', array('version' => 3, 'name' => 'match', 'group_id' => 'civicrm_banking.plugin_class'));

    // Plugin type and plugin class are switched around
    // see issue #29 (https://github.com/Project60/org.project60.banking/issues/29).
    $params['plugin_type_id'] = $matcher_plugin_class['id'];
    $params['plugin_class_id'] = $matcher_id;
    $params['name'] = 'KID Matcher';
    $params['enabled'] = 1;
    civicrm_api3('BankingPluginInstance', 'create', $params);
  }

  private function removeMatcher($name) {
    try {
      $matcher_plugin_class = civicrm_api3('OptionValue', 'getsingle', array('version' => 3, 'name' => 'match', 'group_id' => 'civicrm_banking.plugin_class'));
      $matcher_id = civicrm_api3('OptionValue', 'getvalue', array(
        'return' => "id",
        'option_group_id' => "civicrm_banking.plugin_types",
        'name' => $name,
      ));

      // Plugin type and plugin class are switched around
      // see issue #29 (https://github.com/Project60/org.project60.banking/issues/29).
      $params['plugin_type_id'] = $matcher_plugin_class['id'];
      $params['plugin_class_id'] = $matcher_id;
      $importerPluginInstances = civicrm_api3('BankingPluginInstance', 'get', $params);
      foreach($importerPluginInstances['values'] as $importerPluginInstance) {
        civicrm_api3('BankingPluginInstance', 'delete', array('id' => $importerPluginInstance['id']));
      }

      civicrm_api3('OptionValue', 'delete', array('id' => $matcher_id));
    } catch (Exception $e) {
      // Do nothing
    }
  }

}
