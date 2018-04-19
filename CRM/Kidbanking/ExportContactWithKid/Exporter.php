<?php

class CRM_Kidbanking_ExportContactWithKid_Exporter {
  
  /**
   * @var CRM_Kidbanking_ExportContactWithKid_Exporter
   */
  private static $instance = null;
  
  private $campaign_id;
  
  private function __construct() {
    
  }
  
  /**
   * @return CRM_Kidbanking_ExportContactWithKid_Exporter
   */
  public static function singleton() {
    if (!self::$instance) {
      self::$instance = new CRM_Kidbanking_ExportContactWithKid_Exporter();
    }
    return self::$instance;
  }
  
  public function setCampaignId($campaign_id) {
    $this->campaign_id = $campaign_id;
  }
  
  /**
   * Method to process the civicrm hook export
   *
   * @param $exportTempTable
   * @param $headerRows
   * @param $sqlColumns
   * @param $exportMode
   */
  public function export(&$exportTempTable, &$headerRows, &$sqlColumns, &$exportMode) {
    // only for contacts and only when a campaign has been selected    
    if ($exportMode == 1 && $this->campaign_id) {
      $sql = "ALTER TABLE " . $exportTempTable . " ADD COLUMN kid_number VARCHAR(45)";
      CRM_Core_DAO::singleValueQuery($sql);
      $headerRows[] = "KID";
      $sqlColumns['kid_number'] = 'kid_number VARCHAR(45)';

      // update temp table
      $dao = CRM_Core_DAO::executeQuery("SELECT * FROM " . $exportTempTable);
      while ($dao->fetch()) {
        if (!$dao->civicrm_primary_id) {
          CRM_Core_Session::setStatus(ts('No contact ID selected for export. Not possible to export KID numbers'));
          break;
        }
        try {
          $kid = civicrm_api3('Kid', 'generate', array(
            'campaign_id' => $this->campaign_id,
            'contact_id' => $dao->civicrm_primary_id,
          ));
          $kidNumber = $kid['kid_number'];
        } catch (CiviCRM_API3_Exception $ex) {
          $kidNumber = '';
        }
        $query = "UPDATE ".$exportTempTable." SET kid_number = %1 WHERE id = %2";
        CRM_Core_DAO::executeQuery($query, array(
          1 => array($kidNumber, 'String'),
          2 => array($dao->id, 'Integer'),
        ));
      }
    }
    return;
  }
  
}
