<?php

class CRM_Banking_PluginImpl_Matcher_StartAvtaleFromPrintedGiro extends CRM_Banking_PluginImpl_Matcher_KID {

  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->threshold))                     $config->threshold = 0.00;
    if (!isset($config->campaign_penalty))              $config->campaign_penalty = 0.15;
  }

  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {
    $threshold   = $this->getThreshold();
    $data = $btx->getDataParsed();
    if (empty($data['kid']) || empty($data['recordName']) || $data['recordName'] != 'StandingOrder' || !isset($data['registrationType'])) {
      return NULL;
    }

    $kid = $data['kid'];
    $registrationType = $data['registrationType'];
    try {
      $kidData = civicrm_api3('kid', 'parse', array('kid' => $kid));
      $contact_id = $kidData['contact_id'];
      $campaign_id = $kidData['campaign_id'];
      $contact = civicrm_api3('Contact', 'getsingle', array(
        'is_deleted' => 0,
        'id' => $contact_id
      ));
    } catch (Exception $e) {
      return NULL;
    }
    if ($registrationType !== 1) {
      return NULL;
    }

    $wants_notification = 0;
    if (!empty($data['wantsNotification']) && $data['wantsNotification'] == 'J') {
      $wants_notification = 1;
    }


    if ($campaign_id) {
      // Find all active printed giro's with the same campaign_id
      $sql = "
        SELECT * FROM `civicrm_value_printed_giro` WHERE (maf_printed_giro_end_date IS NULL OR maf_printed_giro_end_date >= NOW()) AND entity_id = %1 AND maf_printed_giro_campaign = %2
        UNION SELECT * FROM `civicrm_value_printed_giro` WHERE (maf_printed_giro_end_date IS NULL OR maf_printed_giro_end_date >= NOW()) AND entity_id = %1 AND maf_printed_giro_campaign != %2
      ";
      $sqlParams[1] = array($contact_id, 'Integer');
      $sqlParams[2] = array($campaign_id, 'Integer');
    } else {
      $sql = "SELECT * FROM `civicrm_value_printed_giro` WHERE (maf_printed_giro_end_date IS NULL OR maf_printed_giro_end_date >= NOW()) AND entity_id = %1";
      $sqlParams[1] = array($contact_id, 'Integer');
    }
    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
    while ($dao->fetch()) {
      $probability = 1.00;
      $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
      $suggestion->setTitle(ts("Create Avtale from printed giro"));
      $suggestion->setId('standingorder-kid-'.$kid);
      $suggestion->setParameter('kid', $kid);
      $suggestion->setParameter('contact_id', $contact_id);
      $suggestion->setParameter('civicrm_value_printed_giro_id', $dao->id);
      $suggestion->setParameter('wants_notification', $wants_notification);
      if ($campaign_id) {
        $suggestion->setParameter('campaign_id', $campaign_id);
      }
      if ($dao->maf_printed_giro_campaign != $campaign_id) {
        $suggestion->addEvidence($this->_plugin_config->campaign_penalty, ts("The campaign of the transaction differs from the campaign of the printed giro"));
        $probability = $probability - $this->_plugin_config->campaign_penalty;
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

  public function execute($match, $btx) {
    $id = $match->getParameter('civicrm_value_printed_giro_id');
    $cycle_day = $match->getParameter('cycle_day');
    $contact_id = $match->getParameter('contact_id');

    $sql = "SELECT * FROM `civicrm_value_printed_giro` WHERE id = %1";
    $sqlParams[1] = array($id, 'Integer');
    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
    $dao->fetch();

    $params['version'] = 3;
    $params['currency'] = 'NOK';
    $params['contact_id'] = $contact_id;
    $params['type'] = 'RCUR';
    $params['amount'] = $dao->maf_printed_giro_amount;
    $params['frequency_interval'] = $dao->maf_printed_giro_frequency;
    if ($dao->maf_printed_giro_campaign) {
      $params['campaign_id'] = $dao->maf_printed_giro_campaign;
    }
    if ($cycle_day) {
      $params['cycle_day'] = $cycle_day;
    }
    $result = civicrm_api('SepaMandate', 'createfull', $params);
    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Session::setStatus(ts("Couldn't create avtale from printed giro.") . "<br/>" . $result['error_message'], ts('Error'), 'error');
      return;
    }

    if (empty($result['is_error'])) {
      $updateParams = array();
      $updateParams[1] = array($id, 'Integer');
      $updateSql = "UPDATE `civicrm_value_printed_giro` SET `maf_printed_giro_end_date` = CURRENT_DATE() WHERE `id` = %1";
      CRM_Core_DAO::executeQuery($updateSql, $updateParams);
    }

    $match->setParameter('mandate_id', $result['id']);
    $mandate = civicrm_api3('SepaMandate', 'getsingle', array('id' => $result['id']));

    //Also enable the mandate
    CRM_Kidbanking_Utils::enableMandate($result['id']);

    if ($mandate['entity_table'] == 'civicrm_contribution_recur') {
      // Store notification from bank
      $contribution_recur_id = $mandate['entity_id'];
      $wants_notification = $match->getParameter('wants_notification');
      CRM_Kidbanking_Utils::updateNotificationFromBank($contribution_recur_id, $wants_notification);
    }

    $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Processed');
    $btx->setStatus($newStatus);
    parent::execute($match, $btx);
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
      // Noting to do in the abstract matcher. Override for a matcher that uses input fields
      if (isset($parameters['cycle_day'])) {
        $match->setParameter('cycle_day', $parameters['cycle_day']);
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
    $frequencies = CRM_Core_OptionGroup::values('maf_printed_giro_frequency');
    $smarty_vars = array();

    $id = $suggestion->getParameter('civicrm_value_printed_giro_id');
    $contact_id = $suggestion->getParameter('contact_id');

    $contact = civicrm_api3('Contact', 'getsingle', array('id' => $contact_id));
    $sql = "SELECT * FROM `civicrm_value_printed_giro` WHERE id = %1";
    $sqlParams[1] = array($id, 'Integer');
    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
    $dao->fetch();

    // assign to smarty and compile HTML
    $smarty_vars['contact'] = $contact;
    $smarty_vars['amount'] = $dao->maf_printed_giro_amount;
    $smarty_vars['frequency'] = $frequencies[$dao->maf_printed_giro_frequency];
    $smarty_vars['wants_notification'] = $suggestion->getParameter('wants_notification');

    if ($dao->maf_printed_giro_campaign) {
      $smarty_vars['campaign'] = civicrm_api3('Campaign', 'getvalue', array('return' => 'title', 'id' => $dao->maf_printed_giro_campaign));
    }
    $smarty_vars['penalties'] = $suggestion->getEvidence();
    
    $avtaleDefaults = CRM_Mafsepa_Utils::readDefaultsJson('avtale_defaults');
    $smarty_vars['cycle_day'] = $avtaleDefaults['cycle_day'];

    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/StartAvtaleFromPrintedGiro.suggestion.tpl');
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
    $mandate_id = $match->getParameter('mandate_id');
    $contribution_recur_id = 0;
    try {
      $mandate = civicrm_api3('SepaMandate', 'getsingle', array('id' => $mandate_id));
      if ($mandate['entity_table'] == 'civicrm_contribution_recur') {
        $contribution_recur_id = $mandate['entity_id'];
      }
    } catch (Exception $e) {
      // Do nothing.
    }
    $contact_id = $match->getParameter('contact_id');

    // just assign to smarty and compile HTML
    $smarty_vars = array();
    $smarty_vars['contribution_recur_id'] = $contribution_recur_id;
    $smarty_vars['contact_id'] = $contact_id;

    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/StartAvtaleFromPrintedGiro.execution.tpl');
    $smarty->popScope();
    return $html_snippet;
  }

}