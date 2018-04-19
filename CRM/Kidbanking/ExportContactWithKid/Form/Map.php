<?php

class CRM_Kidbanking_ExportContactWithKid_Form_Map extends CRM_Export_Form_Map {
  
  public function postProcess() {
    $params = $this->controller->exportValues('Select');
    $exporter = CRM_Kidbanking_ExportContactWithKid_Exporter::singleton();
    $exporter->setCampaignId($params['campaign_id']);
    parent::postProcess();
  }
  
}