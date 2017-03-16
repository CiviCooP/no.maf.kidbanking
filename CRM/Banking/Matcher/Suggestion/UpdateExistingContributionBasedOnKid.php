<?php

class CRM_Banking_Matcher_Suggestion_UpdateExistingContributionBasedOnKid {

  const SUGGESTION_NAME = 'UpdateExistingContributionBasedOnKid';

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
   * @param CRM_Banking_PluginModel_Matcher $plugin
   * @param CRM_Banking_BAO_BankTransaction $btx
   * @return bool
   */
  public static function parseKidIntoSuggestion(CRM_Banking_PluginModel_Matcher $plugin, CRM_Banking_BAO_BankTransaction $btx) {
    $utils = CRM_Kidbanking_Utils::singleton();

    $data = $btx->getDataParsed();
    if (empty($data['kid'])) {
      return false;
    }

    $kid = $data['kid'];
    try {
      $kidData = civicrm_api3('kid', 'parse', array('kid' => $kid));
      $contact_id = $kidData['contact_id'];
      $campaign_id = $kidData['campaign_id'];
      $contribution_id = $kidData['contribution_id'];
    } catch (Exception $e) {
      return false;
    }

    // When contact_id is missing or campaign_id is missing then we are not able to match
    // those transactions.
    if (empty($contact_id) || empty($campaign_id) || empty($contribution_id)) {
      return false;
    }

    try {
      $contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $contribution_id));
    } catch (Exception $e) {
      // Could not find the contribution.
      // Return as this suggestion expects an existing contribution
      return false;
    }

    $evidence = array();

    // We will set the matching level to 100% sure. And we calculate a penalty if something goes wrong.
    // E.g. the contact could not be found or the campaign id could not be found etc...
    $probability = 1.00; // 0% sure
    $penalty = 0.00; //

    $suggestion = new CRM_Banking_Matcher_Suggestion($plugin, $btx);
    $suggestion->setTitle(ts("Update existing contribution status based on the KID (=".$kid.')'));
    $suggestion->setId('kid-'.$kid);
    $suggestion->setParameter('kid', $kid);
    $suggestion->setParameter('suggestion_name', self::SUGGESTION_NAME);

    // Try to find the related contact
    try {
      $contact = civicrm_api3('Contact', 'getsingle', array('id' => $contact_id));
      $contactViewUrl = CRM_Utils_System::url('civicrm/contact/view', 'reset=1&cid='.$contact_id);
      $evidence[] = 'KID belongs to contact: <a href="'.$contactViewUrl.'">'.$contact['display_name'].' ('.$contact_id.')</a>';
      $suggestion->setParameter('contact_id', $contact_id);
    } catch (Exception $e) {
      $penalty += 0.5;
      $evidence[] = 'Could <strong>not</strong> find contact with ID: '.$contact_id;
    }

    try {
      $campaign = civicrm_api3('Campaign', 'getsingle', array('id' => $campaign_id));
      $evidence[] = 'Campaign: '.$campaign['title'];
      $suggestion->setParameter('campaign_id', $campaign_id);
    } catch (Exception $e) {
      $penalty += 0.4;
      $evidence[] = 'Could <strong>not</strong> find campaign with ID: '.$campaign_id;
    }

    $status_label = '<em>'.$utils->contribution_status_labels[$contribution['contribution_status_id']].'</em>';
    $evidence[] = 'Found matching '.$status_label.' contribution ' . $contribution['currency'] . ' ' . $contribution['total_amount'] . ' ('.$contribution_id.')';
    $suggestion->setParameter('contribution_id', $contribution_id);
    if ($contribution['total_amount'] != $btx->amount) {
      $penalty += 0.4;
      $evidence[] = 'Contribution amount is different from the amount in the bank file.';
    }
    if ($contribution['currency'] != $btx->currency) {
      $penalty += 0.2;
      $evidence[] = 'Currency of contributionis different from the currency in the bank file';
    }
    if ($contribution['contribution_status_id'] != $utils->pending_status_id) {
      $penalty += 0.4;
      $evidence[] = '<strong>Contribution does not have the status pending</strong>';
    }
    if ($contribution['contact_id'] != $contact_id) {
      $penalty += 0.4;
      $evidence[] = '<strong>Contribution belongs to a different contact (contact id: '.$contribution['contact_id'].'). The contact id in the KID number is '.$contact_id.'</strong>';
    }
    if ($contribution['campaign_id'] != $campaign_id) {
      $penalty += 0.2;
      $evidence[] = '<strong>Contribution belongs to a different campaign (campaign id: '.$contribution['campaign_id'].'). The campaign id in the KID number is '.$campaign_id.'</strong>';
    }

    $bookDate = new DateTime($btx->booking_date);
    $contributionDate = new DateTime($contribution['receive_date']);
    $bookDateFormatted = $bookDate->format('Ymd');
    $contributionDateFormatted = $contributionDate->format('Ymd');
    if ($bookDateFormatted != $contributionDateFormatted) {
      $penalty += 0.1;
      $evidence[] = '<strong>Contribution date ('.$contributionDate->format('d-m-Y').') does not match transaction date ('.$bookDate->format('d-m-Y').')</strong><br>Selecting this action will set the contribution date to the transaction date.';
    }

    $probability = $probability - $penalty;
    if ($probability < 0.00) {
      $probability = 0.00;
    }

    $suggestion->setProbability($probability);
    $suggestion->setEvidence($evidence);

    $btx->addSuggestion($suggestion);

    return true;
  }


  /**
   * Execute the matching action. In the match function we found an existing contribution.
   * Now we are updating the status of this contribution to complete.
   *
   * @param \CRM_Banking_Matcher_Suggestion $match
   * @param \CRM_Banking_BAO_BankTransaction $btx
   * @param \CRM_Banking_PluginModel_Matcher $plugin
   * @return bool|void
   */
  public static function execute(CRM_Banking_Matcher_Suggestion $match, CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_PluginModel_Matcher $plugin) {
    // only execute if not completed yet
    if (banking_helper_tx_status_closed($btx->status_id)) {
      return;
    }
    if ($match->getParameter('suggestion_name') != self::SUGGESTION_NAME) {
      return;
    }

    $contribution_id = $match->getParameter('contribution_id');
    // double check contribution (see https://github.com/Project60/CiviBanking/issues/61)
    $contribution = civicrm_api('Contribution', 'getsingle', array('id' => $contribution_id, 'version' => 3));
    if (!empty($contribution['is_error'])) {
      CRM_Core_Session::setStatus(ts('Contribution has disappeared.').' '.ts('Error was:').' '.$contribution['error_message'], ts('Execution Failure'), 'alert');
      return false;
    }

    $query = array('version' => 3, 'id' => $contribution_id);
    $query['contribution_status_id'] = banking_helper_optionvalue_by_groupname_and_name('contribution_status', 'Completed');
    $query['receive_date'] = date('YmdHis', strtotime($btx->booking_date));
    $result = civicrm_api('Contribution', 'create', $query);
    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Session::setStatus(ts("Couldn't modify contribution.") . "<br/>" . $result['error_message'], ts('Error'), 'error');
      return;
    }

    // save the account
    $plugin->storeAccountWithContact($btx, $match->getParameter('contact_id'));

    $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Processed');
    $btx->setStatus($newStatus);

    return;
  }

}