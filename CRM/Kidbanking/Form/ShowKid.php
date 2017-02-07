<?php

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Kidbanking_Form_ShowKid extends CRM_Core_Form {

  public function buildQuickForm() {

    $contactAttributes = array(
      'multiple' => False,
      'create' => False,
      'api' => array('params' => array('is_deceased' => 0, 'is_delete' => 0))
    );
    $this->addEntityRef('contact_id', ts('Contact'), $contactAttributes, true);


    $campaignAttributes = array(
      'entity' => 'Campaign',
      'multiple' => False,
      'create' => False,
      'api' => array('params' => array())
    );
    $this->addEntityRef('campaign_id', ts('Campaign'), $campaignAttributes, true);

    CRM_Utils_System::setTitle('Show Kid Number');

    parent::buildQuickForm();
  }

  public function postProcess() {
    parent::postProcess();
  }

}
