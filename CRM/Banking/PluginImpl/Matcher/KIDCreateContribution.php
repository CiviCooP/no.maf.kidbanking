<?php

class CRM_Banking_PluginImpl_Matcher_KIDCreateContribution extends CRM_Banking_PluginImpl_Matcher_KID {

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->threshold)) {
      $config->threshold = 0.00;
    }
    if (!isset($config->default_penalty)) {
      $config->default_penalty = 0.9;
    }
		if (!isset($config->default_financial_type_id)) {
      $config->default_financial_type_id = 0;
    }
		if (!isset($config->default_payment_instrument_id)) {
      $config->default_payment_instrument_id = 0;
    }
    if (!isset($config->create_contribution_status)) {
      $config->create_contribution_status = 'Completed';
    }
    if (!isset($config->payment_instrument_field)) {
      $config->payment_instrument_field = 'payment_instrument_id';
    }

    if (!isset($config->transactionTypeField))					$config->transactionTypeField = 'transactionType';
    if (!isset($config->transactionTypeValue))					$config->transactionTypeValue = array(10, 13);
    if (!isset($config->transactionTypePenalty))        $config->transactionTypePenalty = 0.15;
    if (!isset($config->noCampaignPenalty))             $config->noCampaignPenalty = 0.15;
    if (!isset($config->noContactPenalty))              $config->noContactPenalty = 0.15;
  }

  /**
   * Generate a set of suggestions for the given bank transaction
   *
   * @return array(match structures)
   */
  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {
    $data = $btx->getDataParsed();
    $threshold = $this->getThreshold();
    $penalty = $this->getPenalty($btx);

    $probability = 1.00;
    if ($this->_plugin_config->default_penalty) {
      $probability = $probability - $this->_plugin_config->default_penalty;
    }

    if (empty($data['kid'])) {
      return NULL;
    }

    $kid = $data['kid'];
    try {
      $kidData = civicrm_api3('kid', 'parse', array('kid' => $kid));
      $contact_id = $kidData['contact_id'];
      $campaign_id = $kidData['campaign_id'];
      $contact = civicrm_api3('Contact', 'getsingle', array('is_deleted' => 0, 'id' => $contact_id));
    } catch (Exception $e) {
      // Do nothing.
    }
    if ($btx->amount == 0.00) {
      return NULL;
    }

    if (empty($contact_id)) {
      $probability = $probability - $this->_plugin_config->noContactPenalty;
    }

    if (!empty($campaign_id)) {
      try {
        $campaign = civicrm_api3('Campaign', 'getsingle', array('id' => $campaign_id));
      } catch (Exception $e) {
        $campaign_id = null;
      }
    }

		if (empty($campaign_id) && $contact_id) {
			// Try to find the campaign based on an active printed giro
			// or an active avtale giro
			$campaign_id = CRM_Core_DAO::singleValueQuery("SELECT maf_printed_giro_campaign FROM civicrm_value_printed_giro WHERE maf_printed_giro_amount = %1 and entity_id = %2 AND (maf_printed_giro_end_date IS NULL OR maf_printed_giro_end_date >= NOW())", array(
				1 => array($btx->amount, 'Float'),
				2 => array($contact_id, 'Integer'),
			));
			if (empty($campaign_id)) {
				// Try to find campaign based on an active avtale giro	
				$campaign_id = CRM_Core_DAO::singleValueQuery("SELECT campaign_id FROM civicrm_contribution_recur WHERE amount = %1 and contact_id = %2 AND (end_date IS NULL OR end_date >= NOW())", array(
					1 => array($btx->amount, 'Float'),
					2 => array($contact_id, 'Integer'),
				));	
			}
		}

    if (empty($campaign_id)) {
      $probability = $probability - $this->_plugin_config->noCampaignPenalty;
    }

    $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
    $suggestion->setId('kid-'.$kid);
    $suggestion->setTitle('Create new contribution');
    $suggestion->setParameter('contact_id', $contact_id);
    $suggestion->setParameter('campaign_id', $campaign_id);
    $suggestion->setParameter('financial_type_id', $this->_plugin_config->default_financial_type_id);
    $payment_instrument_id = $this->_plugin_config->default_payment_instrument_id;
    if (isset($data[$this->_plugin_config->payment_instrument_field])) {
      $payment_instrument_id = $data[$this->_plugin_config->payment_instrument_field];
    }
    $suggestion->setParameter('payment_instrument_id', $payment_instrument_id);

    $date = new DateTime($btx->value_date);
    $suggestion->setParameter('date', $date->format('Ymd His'));
    $suggestion->setParameter('amount', $btx->amount);
    $suggestion->setParameter('currency', $btx->currency);

    $transactionTypeField = $this->_plugin_config->transactionTypeField;
    if (isset($data[$transactionTypeField]) && !in_array($data[$transactionTypeField], $this->_plugin_config->transactionTypeValue)) {
      $suggestion->addEvidence($this->_plugin_config->transactionTypePenalty, ts("The transaction type is not valid. Transaction type should be one of %1", array('1'=>implode(',', $this->_plugin_config->transactionTypeValue))));
      $probability = $probability - $this->_plugin_config->transactionTypePenalty;
    }

    $suggestion->setProbability($probability);
    if ($suggestion->getProbability() >= $threshold) {
      if ($penalty) {
        $suggestion->addEvidence($penalty, ts("A general penalty was applied."));
      }
      $btx->addSuggestion($suggestion);
      $this->addSuggestion($suggestion);
    }

    // that's it...
    return empty($this->_suggestions) ? null : $this->_suggestions;
  }

  public function execute($suggestion, $btx) {
    $contact_id = $suggestion->getParameter('contact_id');

    $financial_type_id = $suggestion->getParameter('financial_type_id');
    $payment_instrument_id = $suggestion->getParameter('payment_instrument_id');
    if (empty($financial_type_id) || empty($payment_instrument_id)) {
      CRM_Core_Session::setStatus(ts('Financial type and payment instrument are required fields'), ts('Error', 'error'));
      return;
    }

    $params = array();
    $params['version'] = 3;
    $params['contact_id'] = $contact_id;
    $params['contribution_status_id'] = banking_helper_optionvalue_by_groupname_and_name('contribution_status', $this->_plugin_config->create_contribution_status);
    $params['receive_date'] = $suggestion->getParameter('date');
    $params['total_amount'] = $suggestion->getParameter('amount');
    $params['currency'] = $suggestion->getParameter('currency');
    $params['financial_type_id'] = $financial_type_id;
    $params['payment_instrument_id'] = $payment_instrument_id;
    if ($suggestion->getParameter('campaign_id')) {
      $params['campaign_id'] = $suggestion->getParameter('campaign_id');
    }
    
    $result = civicrm_api('Contribution', 'create', $params);
    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Session::setStatus(ts("Couldn't modify contribution.") . "<br/>" . $result['error_message'], ts('Error'), 'error');
      return;
    }

    $suggestion->setParameter('contribution_id', $result['id']);

    $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Processed');
    $btx->setStatus($newStatus);
    parent::execute($suggestion, $btx);
    return true;
  }

  /**
   * If the user has modified the input fields provided by the "visualize" html code,
   * the new values will be passed here BEFORE execution
   *
   * CAUTION: there might be more parameters than provided. Only process the ones that
   *  'belong' to your suggestion.
   */
  public function update_parameters(CRM_Banking_Matcher_Suggestion $match, $parameters) {
    if (isset($parameters['kid_create_contribution_campaign_id'])) {
      $match->setParameter('campaign_id', $parameters['kid_create_contribution_campaign_id']);
    }
    if (isset($parameters['kid_create_contribution_financial_type_id'])) {
      $match->setParameter('financial_type_id', $parameters['kid_create_contribution_financial_type_id']);
    }
    if (isset($parameters['kid_create_contribution_payment_instrument_id'])) {
      $match->setParameter('payment_instrument_id', $parameters['kid_create_contribution_payment_instrument_id']);
    }
		if (isset($parameters['lookup_contact_id'])) {
      $match->setParameter('contact_id', $parameters['lookup_contact_id']);
    }
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

    $contact_id = $suggestion->getParameter('contact_id');
		$smarty_vars['contact_id'] = $contact_id;		

    $smarty_vars['financial_types'] = array();
    $financial_types = civicrm_api3('FinancialType', 'get', array('options' => array('limit' => 0)));
    foreach($financial_types['values'] as $financial_type) {
      $smarty_vars['financial_types'][$financial_type['id']] = $financial_type['name'];
    }

    $smarty_vars['payment_instruments'] = array();
    $payment_instruments = civicrm_api3('OptionValue', 'get', array('options' => array('limit' => 0), 'option_group_id' => 'payment_instrument'));
    foreach($payment_instruments['values'] as $payment_instrument) {
      $smarty_vars['payment_instruments'][$payment_instrument['value']] = $payment_instrument['label'];
    }

    $smarty_vars['campaigns'] = array();
    $campaigns = civicrm_api3('Campaign', 'get', array('options' => array('limit' => 0)));
    foreach($campaigns['values'] as $campaign) {
      $smarty_vars['campaigns'][$campaign['id']] = $campaign['title'];
    }

    $smarty_vars['selected_campaign_id'] = $suggestion->getParameter('campaign_id');
    $smarty_vars['selected_financial_type_id'] = $suggestion->getParameter('financial_type_id');
    $smarty_vars['selected_payment_instrument_id'] = $suggestion->getParameter('payment_instrument_id');
    $smarty_vars['create_contribution_status'] = $this->_plugin_config->create_contribution_status;

    $smarty_vars['date'] = $suggestion->getParameter('date');
    $smarty_vars['amount'] = $suggestion->getParameter('amount');
    $smarty_vars['currency'] = $suggestion->getParameter('currency');

    $smarty_vars['penalties'] = $suggestion->getEvidence();


    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/KIDCreateContribution.suggestion.tpl');
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
    $smarty_vars = array();
    $contact_id = $match->getParameter('contact_id');
    $contact = civicrm_api3('Contact', 'getsingle', array('id' => $contact_id));
    $smarty_vars['contact'] = $contact;
    $contribution_id = $match->getParameter('contribution_id');
    $contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $contribution_id));
    $smarty_vars['contribution'] = $contribution;

    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/KIDCreateContribution.execution.tpl');
    $smarty->popScope();
    return $html_snippet;
  }

}
