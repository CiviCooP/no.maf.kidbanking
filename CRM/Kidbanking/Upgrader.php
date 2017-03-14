<?php

/**
 * Collection of upgrade steps.
 */
class CRM_Kidbanking_Upgrader extends CRM_Kidbanking_Upgrader_Base {

  public function install() {
    $manager = \CRM_Extension_System::singleton()->getManager();
    if ($manager->getStatus('org.project60.banking') != 'installed') {
      throw new Exception("no.maf.ocrimporter requires the extension org.project60.banking to be installed");
    }

    try {
      $kid_matcher_id = civicrm_api3('OptionValue', 'getvalue', array(
        'return' => "id",
        'option_group_id' => "civicrm_banking.plugin_types",
        'name' => "kid_matcher",
      ));
      civicrm_api3('OptionValue', 'delete', array('id' => $kid_matcher_id));
    } catch (Exception $e) {
      // doesn't exist yet
      $result = civicrm_api3('OptionValue', 'create', array(
        'option_group_id'  => "civicrm_banking.plugin_types",
        'name'             => 'kid_matcher',
        'label'            => 'KID Matcher',
        'value'            => 'CRM_Banking_PluginImpl_Matcher_KID',
        'is_default'       => 0
      ));
      $kid_matcher_id = $result['id'];
    }

    // then, find the correct plugin type
    $matcher_plugin_class = civicrm_api3('OptionValue', 'getsingle', array('version' => 3, 'name' => 'match', 'group_id' => 'civicrm_banking.plugin_class'));

    // Plugin type and plugin class are switched around
    // see issue #29 (https://github.com/Project60/org.project60.banking/issues/29).
    $params['plugin_type_id'] = $matcher_plugin_class['id'];
    $params['plugin_class_id'] = $kid_matcher_id;
    $params['name'] = 'KID Matcher';
    $params['enabled'] = 1;
    civicrm_api3('BankingPluginInstance', 'create', $params);
  }

  public function uninstall() {
    try {
      $matcher_plugin_class = civicrm_api3('OptionValue', 'getsingle', array('version' => 3, 'name' => 'match', 'group_id' => 'civicrm_banking.plugin_class'));
      $kid_matcher_id = civicrm_api3('OptionValue', 'getvalue', array(
        'return' => "id",
        'option_group_id' => "civicrm_banking.plugin_types",
        'name' => "kid_matcher",
      ));

      // Plugin type and plugin class are switched around
      // see issue #29 (https://github.com/Project60/org.project60.banking/issues/29).
      $params['plugin_type_id'] = $matcher_plugin_class['id'];
      $params['plugin_class_id'] = $kid_matcher_id;
      $importerPluginInstances = civicrm_api3('BankingPluginInstance', 'get', $params);
      foreach($importerPluginInstances['values'] as $importerPluginInstance) {
        civicrm_api3('BankingPluginInstance', 'delete', array('id' => $importerPluginInstance['id']));
      }

      civicrm_api3('OptionValue', 'delete', array('id' => $kid_matcher_id));
    } catch (Exception $e) {
      // Do nothing
    }
  }

}
