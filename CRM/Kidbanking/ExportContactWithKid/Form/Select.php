<?php

/**
 * Class to export contacts with KID
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 31 July 2017
 * @license AGPL-3.0
 */
class CRM_Kidbanking_ExportContactWithKid_Form_Select extends CRM_Export_Form_Select {
  
  protected static $campaign_id = false;
   
  public function buildQuickForm() {
    parent::buildQuickForm();
    // add campaign select field
    $campaignSelectList = array('- select campaign -');
    $campaigns = civicrm_api3('Campaign', 'get', array(
      'is_active' => 1,
      'options' => array('limit' => 0,),
      ));
    foreach ($campaigns['values'] as $campaignId => $campaignData) {
      $campaignSelectList[$campaignId] = $campaignData['title'];
    }
    asort($campaignSelectList);
    $this->add('select', 'campaign_id', ts('Campaign (for KID)'), $campaignSelectList, true, array('class' => 'crm-select2'));
    
    $this->addFormRule(array('CRM_Generic_Export', 'validateCampaign'), $this);
  }
  
  public static function validateCampaign($fields, $files, $self) {
    $errors = array();  
    if (!isset($fields['campaign_id']) || empty($fields['campaign_id'])) {
        $errors['campaign_id'] = ts('You have to select a campaign when exporting contacts with a KID');
    }
    return $errors;
  }
  
  public function postProcess() {
    $params = $this->controller->exportValues($this->_name);
    $exporter = CRM_Kidbanking_ExportContactWithKid_Exporter::singleton();
    $exporter->setCampaignId($params['campaign_id']);
    parent::postProcess();
  }
 
 } 