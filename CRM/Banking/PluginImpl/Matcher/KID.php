<?php

class CRM_Banking_PluginImpl_Matcher_KID extends CRM_Banking_PluginModel_Matcher {

  /**
   * Do the actual matching of the transaction based on the KID number.
   *
   * The KID should consist of:
   *   pos 1-7 - 7 digits for contact ID (allowing a maximum of 10 million donors), left padded with zeros
   *   pos 8-13 - 6 digits for campaign ID (allowing a maximum of 1 million campaigns), left padded with zeros
   *   post 14-21 - 8 digits for contribution ID (allowing a maximum of 100 million contributions, left padded with zeros. This part of the KID will only be used for AvtaleGiro collections where MAF Norge collects the money from the bank (OCR export)
   *   final digit is a control digit. Calculated through the Modulo 10 algorithm
   *
   *
   * @see https://civicoop.plan.io/projects/maf-norge-civicrm-support-2016/wiki/KID_and_AvtaleGiro_background
   *
   * @param \CRM_Banking_BAO_BankTransaction $btx
   * @param \CRM_Banking_Matcher_Context $context
   * @return array
   */
  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {
    // Generate an suggestion when an existing contribution exists with the KID and the status pending
    CRM_Banking_Matcher_Suggestion_UpdateExistingContributionBasedOnKid::parseKidIntoSuggestion($this, $btx);
    return $btx->getSuggestions();

  }

  public function execute($match, $btx) {
    // Get the class name parameter from the matcher that way we know we have sit it in the matching function above.
    $suggestion_name = $match->getParameter('suggestion_name');
    switch ($suggestion_name) {
      case CRM_Banking_Matcher_Suggestion_UpdateExistingContributionBasedOnKid::SUGGESTION_NAME:
        CRM_Banking_Matcher_Suggestion_UpdateExistingContributionBasedOnKid::execute($match, $btx, $this);
        break;
    }
    return true;
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
    if (empty($data['debitAccount'])) {
      return;
    }
    $bank_account = $data['debitAccount'];
    try {
      $ba_id = civicrm_api3('BankingAccountReference', 'getvalue', array('return' => 'ba_id', 'reference' => $bank_account));
      $id = civicrm_api3('BankingAccount', 'getvalue', array('return' => 'id', 'id' => $ba_id, 'contact_id' => $contact_id));
      // Found the bank account for this contact.
      return;
    } catch (Exception $e) {
      // Do nothing
    }

    $ba_params['contact_id'] = $contact_id; // Default Organisation
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
      'name' => 'ocr',
      'option_group_id' => 'civicrm_banking.reference_types'
    ));
    civicrm_api3('BankingAccountReference', 'create', $ba_ref_params);
  }

}
