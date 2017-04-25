<?php

class CRM_Banking_PluginImpl_Matcher_KIDWithContributionId extends CRM_Banking_PluginImpl_Matcher_KID {

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->threshold))                     $config->threshold = 0.00;
    if (!isset($config->update_contribution_status))    $config->update_contribution_status = 'Completed';
    if (!isset($config->recurring_contribution_status)) $config->contribution_status = array('Pending', 'In Progress');
    if (!isset($config->amount_penalty))                $config->amount_penalty = 0.1;
    if (!isset($config->currency_penalty))              $config->currency_penalty = 0.2;
    if (!isset($config->campaign_penalty))              $config->campaign_penalty = 0.05;

    // date check / date range
    if (!isset($config->received_date_check))           $config->received_date_check = "1";  // WARNING: DISABLING THIS COULD MAKE THE PROCESS VERY SLOW
    if (!isset($config->acceptable_date_offset_from))   $config->acceptable_date_offset_from = "-1 days";
    if (!isset($config->acceptable_date_offset_to))     $config->acceptable_date_offset_to = "+1 days";
    if (!isset($config->date_offset_minimum))           $config->date_offset_minimum = "-1 year";
    if (!isset($config->date_offset_maximum))           $config->date_offset_maximum = "+1 year";
    if (!isset($config->date_penalty))                  $config->date_penalty = 0.1;

    $this->_plugin_config->auto_exec = true;
  }

  /**
   * Generate a set of suggestions for the given bank transaction
   *
   * @return array(match structures)
   */
  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {
    $data = $btx->getDataParsed();
    $threshold   = $this->getThreshold();

    if (empty($data['kid'])) {
      return null;
    }

    $kid = $data['kid'];
    try {
      $kidData = civicrm_api3('kid', 'parse', array('kid' => $kid));
      $contact_id = $kidData['contact_id'];
      $campaign_id = $kidData['campaign_id'];
      $contribution_id = $kidData['contribution_id'];
      $contact = civicrm_api3('Contact', 'getsingle', array('is_deleted' => 0, 'id' => $contact_id));
    } catch (Exception $e) {
      return NULL;
    }
    if (empty($contribution_id)) {
      return null;
    }

    $query['contact_id'] = $contact_id;
    // add status restriction
    if (!empty($this->_plugin_config->contribution_status)) {
      $query['contribution_status_id'] = array('IN' => $this->_plugin_config->contribution_status);
    }
    $query['id'] = $contribution_id;

    $pending_contributions = civicrm_api3('Contribution', 'get', $query);
    foreach ($pending_contributions['values'] as $key => $contribution) {
      $probability = 1.0;
      $contribution_id = $contribution['id'];

      $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
      $suggestion->setId("kid_contribution-$contribution_id");
      $suggestion->setParameter('contribution_id', $contribution_id);
      $suggestion->setParameter('contact_id', $contribution['contact_id']);
      $suggestion->setParameter('update_contribution_status', $this->_plugin_config->update_contribution_status);
      $suggestion->setParameter('kid', $kid);
      $suggestion->setTitle(ts('Found contribution with KID as contribution ID'));

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
        if ( $date_offset < strtotime($this->_plugin_config->acceptable_date_offset_from, 0)
          || $date_offset > strtotime($this->_plugin_config->acceptable_date_offset_to, 0)) {

          // check if the payment is completely out of bounds
          if ($date_offset < strtotime($this->_plugin_config->date_offset_minimum, 0)) continue;
          if ($date_offset > strtotime($this->_plugin_config->date_offset_maximum, 0)) continue;

          // calculate the date penalties
          $date_range =   (strtotime($this->_plugin_config->date_offset_maximum) - strtotime($this->_plugin_config->date_offset_minimum))
            -(strtotime($this->_plugin_config->acceptable_date_offset_to) - strtotime($this->_plugin_config->acceptable_date_offset_from));
          if ($date_offset < 0) {
            $date_delta = abs($date_offset - strtotime($this->_plugin_config->acceptable_date_offset_from, 0));
          } else {
            $date_delta = abs($date_offset - strtotime($this->_plugin_config->acceptable_date_offset_to, 0));
          }

          if ($date_range) {
            $date_penalty = $this->_plugin_config->date_penalty * ($date_delta / $date_range);
            if ($date_penalty) {
              $suggestion->addEvidence($date_penalty, ts("The date of the transaction deviates too much from the expected date."));
              $probability = $probability - $date_penalty;
            }
          }
        }
      }

      $suggestion->setProbability($probability);
      if ($suggestion->getProbability() >= $threshold) {
        $btx->addSuggestion($suggestion);
        $this->addSuggestion($suggestion);
      }
    }

    // that's it...
    return empty($this->_suggestions) ? null : $this->_suggestions;
  }

  public function execute($suggestion, $btx) {
    $contribution_id = $suggestion->getParameter('contribution_id');
    $update_contribution_status = $suggestion->getParameter('update_contribution_status');
    // double check contribution (see https://github.com/Project60/CiviBanking/issues/61)
    $contribution = civicrm_api('Contribution', 'getsingle', array('id' => $contribution_id, 'version' => 3));
    if (!empty($contribution['is_error'])) {
      CRM_Core_Session::setStatus(ts('Contribution has disappeared.').' '.ts('Error was:').' '.$contribution['error_message'], ts('Execution Failure'), 'alert');
      return false;
    }

    $params = array();
    $params['version'] = 3;
    $params['id'] = $contribution_id;
    $params['contribution_status_id'] = banking_helper_optionvalue_by_groupname_and_name('contribution_status', $update_contribution_status);
    $params['receive_date'] = date('YmdHis', strtotime($btx->value_date));
    $result = civicrm_api('Contribution', 'create', $params);
    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Session::setStatus(ts("Couldn't modify contribution.") . "<br/>" . $result['error_message'], ts('Error'), 'error');
      return;
    }

    // Remove the KID from the contact identity history
    kidbanking_remove_kid_from_contact_identity($suggestion->getParameter('kid'));

    // save the account
    $this->storeAccountWithContact($btx, $suggestion->getParameter('contact_id'));

    $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Processed');
    $btx->setStatus($newStatus);
    parent::execute($suggestion, $btx);
    return true;
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
    $smarty_vars['update_contribution_status'] = $suggestion->getParameter('update_contribution_status');
    $smarty_vars['penalties'] = $suggestion->getEvidence();

    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/KIDWithContributionId.suggestion.tpl');
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
    $smarty_vars['update_contribution_status'] = $match->getParameter('update_contribution_status');

    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/KIDWithContributionId.execution.tpl');
    $smarty->popScope();
    return $html_snippet;
  }

}