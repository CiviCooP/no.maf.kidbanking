<?php

/**
 * Collection of upgrade steps.
 */
class CRM_Kidbanking_Upgrader extends CRM_Kidbanking_Upgrader_Base {

  public function install() {
    $this->addMatcher('kid_with_contribution_id', 'Find contributions with KID as contribution_id', 'CRM_Banking_PluginImpl_Matcher_KIDWithContributionId');
    $this->addMatcher('kid_pending_contribution', 'Find pending contributions with KID', 'CRM_Banking_PluginImpl_Matcher_KIDPendingContribution');
    $this->addMatcher('kid_create_contribution', 'Create contribution based on KID', 'CRM_Banking_PluginImpl_Matcher_KIDCreateContribution');
    $this->addMatcher('ocr_standingorder', 'Standing Order Matcher', 'CRM_Banking_PluginImpl_Matcher_StandingOrder');
  }

  public function uninstall() {
    $this->removeMatcher('ocr_standingorder');
    $this->removeMatcher('kid_create_contribution');
    $this->removeMatcher('kid_pending_contribution');
    $this->removeMatcher('kid_with_contribution_id');
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
    $params['name'] = $name;
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
      $params[1] = array($matcher_plugin_class['id'], 'Integer');
      $params[2] = array($matcher_id, 'Integer');
      CRM_Core_DAO::executeQuery("DELETE FROM `civicrm_bank_plugin_instance` WHERE `plugin_type_id` = %1 AND `plugin_class_id` = %2", $params);

      civicrm_api3('OptionValue', 'delete', array('id' => $matcher_id));

      CRM_Core_Session::setStatus(ts('Deleted %1 matcher', array(1=>$name)), '', 'Success');
    } catch (Exception $e) {
      // Do nothing
    }
  }

}
