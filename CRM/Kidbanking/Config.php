<?php

class CRM_Kidbanking_Config {

  private static $instance;

  private $_avtaleGiroCustomGroup = array();
  private $_avtaleGiroCustomFields = array();

  private function __construct() {
    $this->setAvtaleGiroCustomData();
  }

  /**
   * @return CRM_Kidbanking_Config
   */
  public static function instance() {
    if (!self::$instance) {
      self::$instance = new CRM_Kidbanking_Config();
    }
    return self::$instance;
  }

  /**
   * Method to set custom group and custom fields for avtale giro
   * @throws Exception
   */
  private function setAvtaleGiroCustomData() {
    try {
      $this->_avtaleGiroCustomGroup = civicrm_api3('CustomGroup', 'getsingle', array(
        'name' => 'maf_avtale_giro',
        'extends' => 'ContributionRecur'
      ));
      $customFields = civicrm_api3('CustomField', 'get', array(
        'custom_group_id' => 'maf_avtale_giro',
        'options' => array('limit' => 0),
      ));
      $this->_avtaleGiroCustomFields = $customFields['values'];
    }
    catch (CiviCRM_API3_Exception $ex) {
      throw new Exception('Could not find custom group and/or custom fields for Avtale Giro in '.__METHOD__
        .', contact your system administrator. Error from API: '.$ex->getMessage());
    }
  }

  /**
   * Getter for avtale giro custom group
   *
   * @param $key
   * @return mixed
   */
  public function getAvtaleGiroCustomGroup($key = NULL) {
    if (empty($key)) {
      return $this->_avtaleGiroCustomGroup;
    } else {
      return $this->_avtaleGiroCustomGroup[$key];
    }
  }

  /**
   * Getter for avtale giro custom fields
   *
   * @param $name
   * @return mixed
   */
  public function getAvtaleGiroCustomField($name = 'all') {
    if ($name == 'all') {
      return $this->_avtaleGiroCustomFields;
    } else {
      foreach ($this->_avtaleGiroCustomFields as $customFieldId => $customField) {
        if ($customField['name'] == $name) {
          return $customField;
        }
      }
    }
    return NULL;
  }

}