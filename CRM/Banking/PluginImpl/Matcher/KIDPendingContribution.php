<?php

class CRM_Banking_PluginImpl_Matcher_KIDPendingContribution extends CRM_Banking_PluginModel_Matcher {

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->threshold))                     $config->threshold = 0.40;
    if (!isset($config->update_contribution_status))    $config->update_contribution_status = 'Completed';
    if (!isset($config->recurring_contribution_status)) $config->contribution_status = array('Pending', 'In Progress');
    if (!isset($config->amount_penalty))                $config->amount_penalty = 0.15;
    if (!isset($config->currency_penalty))              $config->currency_penalty = 0.15;
    if (!isset($config->campaign_penalty))              $config->campaign_penalty = 0.15;
    if (!isset($config->contribution_id_penalty))       $config->contribution_id_penalty = 0.15;

    // date check / date range
    if (!isset($config->received_date_check))           $config->received_date_check = "1";  // WARNING: DISABLING THIS COULD MAKE THE PROCESS VERY SLOW
    if (!isset($config->acceptable_date_offset_from))   $config->acceptable_date_offset_from = "-1 days";
    if (!isset($config->acceptable_date_offset_to))     $config->acceptable_date_offset_to = "+1 days";
    if (!isset($config->date_penalty))                  $config->date_penalty = 0.15;
  }

  /**
   * Generate a set of suggestions for the given bank transaction
   *
   * @return array(match structures)
   */
  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {
    $data = $btx->getDataParsed();
    $threshold   = $this->getThreshold();
    $penalty     = $this->getPenalty($btx);

    if (empty($data['kid'])) {
      return null;
    }

    $kid = $data['kid'];
    try {
      $kidData = civicrm_api3('kid', 'parse', array('kid' => $kid));
      $contact_id = $kidData['contact_id'];
      $campaign_id = $kidData['campaign_id'];
      $contribution_id = $kidData['contribution_id'];
    } catch (Exception $e) {
      return NULL;
    }

    // Find pending contributions but exclude those who are linked to a mandate
    // with the KID in the reference.
    $query['contact_id'] = $contact_id;
    // add status restriction
    if (!empty($this->_plugin_config->contribution_status)) {
      $query['contribution_status_id'] = array('IN' => $this->_plugin_config->contribution_status);
    }
    if (!empty($contribution_id)) {
      $query['id'] = array('NOT IN' => array($contribution_id));
    }

    $pending_contributions = civicrm_api3('Contribution', 'get', $query);
    foreach ($pending_contributions['values'] as $key => $contribution) {
      $probability = 1.0;
      // When contribution id is present in the KID number lower the probablity of this guess.
      if ($contribution_id && $this->_plugin_config->contribution_id_penalty) {
        $probability = $probability - $this->_plugin_config->contribution_id_penalty;
      }
      $contribution_id = $contribution['id'];

      $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
      $suggestion->setId("kid_contribution-$contribution_id");
      $suggestion->setParameter('contribution_id', $contribution_id);
      $suggestion->setParameter('contact_id', $contribution['contact_id']);
      $suggestion->setTitle(ts('Update contribution status of existing contribution'));

      if ($btx->amount != $contribution['total_amount']) {
        $suggestion->addEvidence($this->_plugin_config->amount_penalty, ts("The amount of the transaction differs from the expected amount."));
        $probability = $probability - $this->_plugin_config->amount_penalty;
      }

      if ($btx->currency != $contribution['currency']) {
        $suggestion->addEvidence($this->_plugin_config->currency_penalty, ts("The currency of the transaction is not as expected."));
        $probability = $probability - $this->_plugin_config->currency_penality;
      }

      if ($campaign_id != $contribution['campaign_id']) {
        $suggestion->addEvidence($this->_plugin_config->campaign_penalty, ts("The campaign of the transaction differs from the campaign of the contribution"));
        $probability = $probability - $this->_plugin_config->campaign_penalty;
      }

      if ($this->_plugin_config->received_date_check) {
        // use date only
        $expected_date = strtotime(date('Y-m-d', strtotime($contribution['receive_date'])));
        $transaction_date = strtotime(date('Y-m-d', strtotime($btx->value_date)));

        // only apply penalties, if the offset is outside the accepted range
        $date_offset = $transaction_date - $expected_date;
        if ( $date_offset < strtotime($this->_plugin_config->acceptable_date_offset_from, 0)  || $date_offset > strtotime($this->_plugin_config->acceptable_date_offset_to, 0)) {
          if ($this->_plugin_config->date_penalty) {
            $suggestion->addEvidence($this->_plugin_config->date_penalty, ts("The date of the transaction deviates too much from the expected date."));
            $probability = $probability - $this->_plugin_config->date_penalty;
          }
        }
      }

      $suggestion->setProbability($probability);
      if ($suggestion->getProbability() >= $threshold) {
        if ($penalty) {
          $suggestion->addEvidence($penalty, ts("A general penalty was applied."));
        }
        $btx->addSuggestion($suggestion);
        $this->addSuggestion($suggestion);
      }
    }

    // that's it...
    return empty($this->_suggestions) ? null : $this->_suggestions;
  }

  public function execute($suggestion, $btx) {
    $contribution_id = $suggestion->getParameter('contribution_id');
    // double check contribution (see https://github.com/Project60/CiviBanking/issues/61)
    $contribution = civicrm_api('Contribution', 'getsingle', array('id' => $contribution_id, 'version' => 3));
    if (!empty($contribution['is_error'])) {
      CRM_Core_Session::setStatus(ts('Contribution has disappeared.').' '.ts('Error was:').' '.$contribution['error_message'], ts('Execution Failure'), 'alert');
      return false;
    }

    $params = array();
    $params['version'] = 3;
    $params['id'] = $contribution_id;
    $params['contribution_status_id'] = banking_helper_optionvalue_by_groupname_and_name('contribution_status', $this->_plugin_config->update_contribution_status);
    $params['receive_date'] = date('YmdHis', strtotime($btx->value_date));
    $result = civicrm_api('Contribution', 'create', $params);
    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Session::setStatus(ts("Couldn't modify contribution.") . "<br/>" . $result['error_message'], ts('Error'), 'error');
      return;
    }

    // save the account
    $this->storeAccountWithContact($btx, $suggestion->getParameter('contact_id'));

    $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Processed');
    $btx->setStatus($newStatus);
    parent::execute($suggestion, $btx);
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

  /**
   * Generate html code to visualize the given match. The visualization may also provide interactive form elements.
   *
   * @val $match    match data as previously generated by this plugin instance
   * @val $btx      the bank transaction the match refers to
   * @return html code snippet
   */
  function visualize_match( CRM_Banking_Matcher_Suggestion $suggestion, $btx) {
    $smarty_vars = array();

    $contribution_id = $suggestion->getParameter('contribution_id');
    // double check contribution (see https://github.com/Project60/CiviBanking/issues/61)
    $contribution = civicrm_api('Contribution', 'getsingle', array('id' => $contribution_id, 'version' => 3));
    // load contact
    $contact_id = $contribution['contact_id'];
    $contact = civicrm_api3('Contact', 'getsingle', array('id' => $contact_id));

    $contribution['financial_type'] = civicrm_api3('FinancialType', 'getvalue', array('return' => "name",  'id' => $contribution['financial_type_id']));
    $contribution['contribution_status'] = civicrm_api3('OptionValue', 'getvalue', array(
      'return' => "label",
      'option_group_id' => "contribution_status",
      'value' => $contribution['contribution_status_id'],
    ));

    if ($contribution['campaign_id']) {
      $contribution['campaign'] = civicrm_api3('Campaign', 'getvalue', array('return' => 'title', 'id' => $contribution['campaign_id']));
    }
    if ($contribution['contribution_recur_id']) {
      $smarty_vars['recurring_contribution'] = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $contribution['contribution_recur_id']));
    }

    // assign to smarty and compile HTML
    $smarty_vars['contact'] = $contact;
    $smarty_vars['contribution'] = $contribution;
    $smarty_vars['update_contribution_status'] = $this->_plugin_config->update_contribution_status;
    $smarty_vars['penalties'] = $suggestion->getEvidence();

    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/KIDPendingContribution.suggestion.tpl');
    $smarty->popScope();
    return $html_snippet;
  }


  /**
   * Generate html code to visualize the executed match.
   *
   * @val $match    match data as previously generated by this plugin instance
   * @val $btx      the bank transaction the match refers to
   * @return html code snippet
   */
  function visualize_execution_info( CRM_Banking_Matcher_Suggestion $match, $btx) {
    $contribution_id = $match->getParameter('contribution_id');
    $contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $contribution_id));

    // just assign to smarty and compile HTML
    $smarty_vars = array();
    $smarty_vars['contribution'] = $contribution;

    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/KIDPendingContribution.execution.tpl');
    $smarty->popScope();
    return $html_snippet;
  }

}