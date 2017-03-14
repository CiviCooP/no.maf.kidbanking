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

}