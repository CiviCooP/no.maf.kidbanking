<?php

class CRM_Kidbanking_Utils {

  private static $singleton;

  /**
   * @var int
   *   The status id for the contribution status pending.
   */
  public $pending_status_id = false;

  /**
   * @var array
   *   An array of labels of contribution status ids
   */
  public $contribution_status_labels = array();

  private function __construct($config_name) {
    $status_ids = civicrm_api3('OptionValue', 'get', array(
      'option_group_id' => 'contribution_status',
      'options' => array('limit' => 0),
    ));
    foreach($status_ids['values'] as $contribution_status) {
      $this->contribution_status_labels[$contribution_status['value']] = $contribution_status['label'];
      if ($contribution_status['name'] == 'Pending') {
        $this->pending_status_id = $contribution_status['value'];
      }
    }
  }

  public static function singleton() {
    if (!self::$singleton) {
      self::$singleton = new CRM_Kidbanking_Utils();
    }
    return self::$singleton;
  }

  /**
   * Check whether the mandate is enabled or not
   *
   * @param $mandate_id
   * @return bool
   */
  public static function isMandateEnabled($mandate_id) {
    $sql = 'SELECT is_enabled FROM civicrm_sdd_mandate WHERE id = %1';
    $sqlParams = array(
      1 => array($mandate_id, 'Integer'),
    );
    $is_enabled = CRM_Core_DAO::singleValueQuery($sql, $sqlParams);
    return $is_enabled ? true : false;
  }

  /**
   * Enable a mandate
   *
   * @param $mandate_id
   */
  public static function enableMandate($mandate_id) {
    $sql = 'UPDATE civicrm_sdd_mandate SET is_enabled = 1 WHERE id = %1';
    $sqlParams = array(
      1 => array($mandate_id, 'Integer'),
    );
    CRM_Core_DAO::executeQuery($sql, $sqlParams);
  }

  /**
   * Disable a mandate
   *
   * @param $mandate_id
   */
  public static function disableMandate($mandate_id) {
    $sql = 'UPDATE civicrm_sdd_mandate SET is_enabled = 0 WHERE id = %1';
    $sqlParams = array(
      1 => array($mandate_id, 'Integer'),
    );
    CRM_Core_DAO::executeQuery($sql, $sqlParams);
  }

  public static function updateNotificationFromBank($contribution_recur_id, $wants_notification) {
    $config = CRM_Kidbanking_Config::instance();
    $wantsNotificationCustomField = $config->getAvtaleGiroCustomField('maf_notification_bank');
    $wantsNotificationCustomFieldIdentifier = 'custom_'.$wantsNotificationCustomField['id'];
    $contributionRecur['id'] = $contribution_recur_id;
    $contributionRecur[$wantsNotificationCustomFieldIdentifier] = $wants_notification ? '1' : '0';
    civicrm_api3('ContributionRecur', 'create', $contributionRecur);
  }

}