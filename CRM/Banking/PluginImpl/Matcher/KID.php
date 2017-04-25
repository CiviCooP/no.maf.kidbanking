<?php

abstract class CRM_Banking_PluginImpl_Matcher_KID extends CRM_Banking_PluginModel_Matcher {

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
      $id = civicrm_api3('BankingAccount', 'getvalue', array('return' => 'id', 'data_raw' => $bank_account, 'contact_id' => $contact_id));
      // Found the bank account for this contact.
      return;
    } catch (Exception $e) {
      // Do nothing
    }

    $ba_params['contact_id'] = $contact_id;
    $ba_params['data_parsed'] = json_encode(array(
      'name' => $bank_account
    ));
    $ba_params['data_raw'] = $bank_account;
    $ba_params['description'] = $bank_account;
    $result = civicrm_api3('BankingAccount', 'create', $ba_params);
  }

}
