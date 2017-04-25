<?php

class CRM_Banking_PluginImpl_Matcher_StandingOrder extends CRM_Banking_PluginModel_Matcher {

  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {
    $data = $btx->getDataParsed();
    if (empty($data['kid']) || empty($data['recordName']) || $data['recordName'] != 'StandingOrder' || !isset($data['registrationType'])) {
      return null;
    }
    return null;

    $kid = $data['kid'];
    $registrationType = $data['registrationType'];
    try {
      $kidData = civicrm_api3('kid', 'parse', array('kid' => $kid));
      $contact_id = $kidData['contact_id'];
      $campaign_id = $kidData['campaign_id'];
      $contribution_id = $kidData['contribution_id'];
      $contact = civicrm_api3('Contact', 'getsingle', array('is_deleted' => 0, 'id' => $contact_id));
    } catch (Exception $e) {
      return NULL;
    }

    $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
    $suggestion->setTitle(ts("Update avtale from standing order record (KID: ".$kid.")"));
    $suggestion->setId('standingorder-kid-'.$kid);
    $suggestion->setParameter('kid', $kid);
    $suggestion->setParameter('contact_id', $contact_id);
    $suggestion->setParameter('campaign_id', $campaign_id);
    $suggestion->setParameter('registration_type', $registrationType);
    $suggestion->setProbability(1.00);

    // Try to find the related contact
    try {
      $contact = civicrm_api3('Contact', 'getsingle', array('id' => $contact_id));
      $contactViewUrl = CRM_Utils_System::url('civicrm/contact/view', 'reset=1&cid='.$contact_id);
      $suggestion->addEvidence(+0.10, 'KID belongs to contact: <a href="'.$contactViewUrl.'">'.$contact['display_name'].' ('.$contact_id.')</a>');
    } catch (Exception $e) {
      $suggestion->addEvidence(-0.50, 'Could <strong>not</strong> find contact with ID: '.$contact_id);
    }

    try {
      $campaign = civicrm_api3('Campaign', 'getsingle', array('id' => $campaign_id));
      $suggestion->addEvidence(+0.10, 'Campaign: '.$campaign['title']);
    } catch (Exception $e) {
      $suggestion->addEvidence(-0.50, 'Could <strong>not</strong> find campaign with ID: '.$campaign_id);
    }

    if ($registrationType == 0) {
      $frequencies = CRM_Core_OptionGroup::values('maf_partners_frequency');

      // Start an avtale based the current active agreements
      // Find active printed giro agreements
      $sql = "SELECT * FROM `civicrm_value_maf_partners_non_avtale` WHERE (maf_partners_end_date IS NULL OR maf_partners_end_date >= NOW()) AND entity_id = %1 AND maf_partners_campaign = %2 AND maf_partners_type = 1";
      $sqlCount = "SELECT COUNT(*) FROM `civicrm_value_maf_partners_non_avtale` WHERE (maf_partners_end_date IS NULL OR maf_partners_end_date >= NOW()) AND entity_id = %1 AND maf_partners_campaign = %2 AND maf_partners_type = 1";
      $sqlParams[1] = array($contact_id, 'Integer');
      $sqlParams[2] = array($campaign_id, 'Integer');
      $printedGiroCount = CRM_Core_DAO::singleValueQuery($sqlCount, $sqlParams);
      if ($printedGiroCount > 0) {
        $evidencePerPrintedGiro = 0.80 / $printedGiroCount;
        $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
        while ($dao->fetch()) {
          // @ToDo check whether an active sepa mandate exists if so lower the probability and warn the user
          $amount = $dao->maf_partners_amount;
          if ($amount <= 0) {
            $suggestion->addEvidence(-0.10, 'Found an active printed giro however the amount is set to 0 NOK');
          } else {
            $frequency = $frequencies[$dao->maf_partners_frequency];
            $suggestion->addEvidence($evidencePerPrintedGiro, 'Found an active printed giro for ' . $amount . ' NOK per ' . $frequency);
          }
        }
      } else {
        $suggestion->addEvidence(-0.50, '<strong>Could not find any active printed giro</strong>');
      }
    }

    $btx->addSuggestion($suggestion);
    return $btx->getSuggestions();
  }

  public function getActions($btx) {
    $data = $btx->getDataParsed();
    $registrationType = $data['registrationType'];
    switch ($registrationType) {
      case 0:
        return 'End active printed giro and create an avtale';
        break;
      case 1:
        return 'Update wants notification from bank';
        break;
      case 2:
        return 'End all active avtales with this campaign';
        break;
    }
  }

  public function execute($match, $btx) {
    $data = $btx->getDataParsed();
    $registrationType = $data['registrationType'];
    switch ($registrationType) {
      case 0:
        // Donor started an Avtale
        $this->createAvtaleFromActivePrintedGiro($match, $btx);
        break;
      case 1:
        // Donor changed whether he wants a notification from the bank
        // Update the bank account
        $contact_id = $match->getParameter('contact_id');
        $this->storeAccountWithContact($btx, $contact_id);
        break;
      case 2:
        $this->endAvtale($match, $btx);
        break;
    }

    $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Processed');
    $btx->setStatus($newStatus);
  }

  protected function endAvtale($match, $btx) {
    $data = $btx->getDataParsed();
    if (empty($data['kid'])) {
      return;
    }
    $kid = ltrim($data['kid']);
    $contact_id = $match->getParameter('contact_id');
    $campaign_id = $match->getParameter('campaign_id');
    $cancel_reason = ts('Cancelled by donor through his/her bank');

    $mandates = civicrm_api3('SepaMandate', 'get', array(
      'contact_id' => $contact_id,
      'iban' => $kid,
      'type' => 'RCUR',
      'campaign_id' => $campaign_id,
    ));
    foreach($mandates['values'] as $mandate) {
      CRM_Sepa_BAO_SEPAMandate::terminateMandate($mandate['id'], date("Y-m-d"), $cancel_reason);
    }
  }

  protected function createAvtaleFromActivePrintedGiro($match, $btx) {
    $data = $btx->getDataParsed();
    if (empty($data['kid'])) {
      return;
    }
    $kid = ltrim($data['kid']);
    $contact_id = $match->getParameter('contact_id');
    $campaign_id = $match->getParameter('campaign_id');

    $this->storeAccountWithContact($btx, $contact_id);

    $params['version'] = 3;
    $params['currency'] = 'NOK';
    $params['bank_account'] = $kid;
    $params['contact_id'] = $contact_id;
    $params['type'] = 'RCUR';
    $params['campaign_id'] = $campaign_id;
    $sql = "SELECT * FROM `civicrm_value_maf_partners_non_avtale` WHERE (maf_partners_end_date IS NULL OR maf_partners_end_date >= NOW()) AND entity_id = %1 AND maf_partners_campaign = %2 AND maf_partners_type = 1";
    $sqlParams[1] = array($contact_id, 'Integer');
    $sqlParams[2] = array($campaign_id, 'Integer');
    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
    while ($dao->fetch()) {
      $params['amount'] = $dao->maf_partners_amount;
      $params['frequency_interval'] = $dao->maf_partners_frequency;
      $result = civicrm_api('SepaMandate', 'createfull', $params);
      if (empty($result['is_error'])) {
        $updateParams = array();
        $updateParams[1] = array($dao->id, 'Integer');
        $updateSql = "UPDATE `civicrm_value_maf_partners_non_avtale` SET `maf_partners_end_date` = CURRENT_DATE() WHERE `id` = %1";
        CRM_Core_DAO::executeQuery($updateSql, $updateParams);
      }
    }

  }

  /**
   * Try to find a bank account if not found create a new bank account.
   *
   * @param CRM_Banking_BAO_BankTransaction $btx
   * @param int $contact_id
   * @return void
   */
  function storeAccountWithContact($btx, $contact_id) {
    $data = $btx->getDataParsed();
    if (empty($data['kid'])) {
      return;
    }
    $wants_notification = 0;
    if (!empty($data['wantsNotification']) && $data['wantsNotification'] == 'J') {
      $wants_notification = 1;
    }
    $bank_account = ltrim($data['kid']);
    $ba_references = civicrm_api3('BankingAccountReference', 'get', array('reference' => $bank_account));
    foreach($ba_references['values'] as $ba_reference) {
      $ba_id = $ba_reference['ba_id'];
      try {
        $id = civicrm_api3('BankingAccount', 'getvalue', array(
          'return' => 'id',
          'id' => $ba_id,
          'contact_id' => $contact_id
        ));
        // Found the bank account for this contact.

        //Update the notifcation from bank
        if (CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM `civicrm_avtale_banking` WHERE `ba_id` = %1", array(1=>array($ba_id, 'Integer')))) {
          CRM_Core_DAO::executeQuery("UPDATE civicrm_avtale_banking SET notification_to_bank = %1 AND ba_id = %2", array(
            1 => array($wants_notification, 'Integer'),
            2 => array($ba_id, 'Integer')
          ));
        } else {
          CRM_Core_DAO::executeQuery("INSERT INTO civicrm_avtale_banking (ba_id, notification_to_bank, maximum_amount) VALUES(%1, %2, 1000)", array(
            1 => array($ba_id, 'Integer'),
            2 => array($wants_notification, 'Integer')
          ));
        }

        return;
      } catch (Exception $e) {
        // Do nothing
      }
    }


    $ba_params['contact_id'] = $contact_id;
    $ba_params['data_parsed'] = json_encode(array(
      'name' => $bank_account
    ));
    $ba_params['data_raw'] = $bank_account;
    $ba_params['description'] = $bank_account;
    $result = civicrm_api3('BankingAccount', 'create', $ba_params);
    $ba_ref_params['ba_id'] = $result['id'];
    $ba_ref_params['reference'] = $bank_account;
    $ba_ref_params['reference_type_id'] = civicrm_api3('OptionValue', 'getvalue', array(
      'return' => 'id',
      'name' => 'kid',
      'option_group_id' => 'civicrm_banking.reference_types'
    ));
    civicrm_api3('BankingAccountReference', 'create', $ba_ref_params);

    CRM_Core_DAO::executeQuery("INSERT INTO civicrm_avtale_banking (ba_id, notification_to_bank, maximum_amount) VALUES(%1, %2, 1000)", array(
      1 => array($result['id'], 'Integer'),
      2 => array($wants_notification, 'Integer')
    ));

  }

}
