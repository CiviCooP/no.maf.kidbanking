<?php

class CRM_Banking_PluginImpl_Matcher_StartAvtale extends CRM_Banking_PluginImpl_Matcher_KID {

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
    if (!isset($config->campaign_penalty)) {
      $config->campaign_penalty = 0.15;
    }
    if (!isset($config->mandate_ref_penalty)) {
      $config->mandate_ref_penalty = 0.15;
    }
  }

  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {
    $threshold = $this->getThreshold();

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
      $contribution_id = $kidData['contribution_id'];
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

    $mandates = civicrm_api3('SepaMandate', 'get', array(
      'contact_id' => $contact_id,
      'type' => 'RCUR',
      'options' => array(
        'limit' => 0,
      ),
    ));
var_dump($contact_id); exit();
    $status_to_skip = array('COMPLETE', 'ONHOLD', 'PARTIAL', 'INVALID');
    foreach($mandates['values'] as $mandate) {
      if (in_array($mandate['status'], $status_to_skip)) {
        continue;
      }
      if ($mandate['entity_table'] != 'civicrm_contribution_recur') {
        continue;
      }
      if (CRM_Kidbanking_Utils::isMandateEnabled($mandate['id'])) {
        continue;
      }

      $contribution_recur = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $mandate['entity_id']));

      $probability = 1.00;
      $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
      $suggestion->setTitle(ts("Start Avtale"));
      $suggestion->setId('stopavtale-kid-'.$kid);
      $suggestion->setParameter('kid', $kid);
      $suggestion->setParameter('contact_id', $contact_id);
      $suggestion->setParameter('mandate_id', $mandate['id']);
      $suggestion->setParameter('contribution_recur_id', $contribution_recur['id']);
      $suggestion->setParameter('wants_notification', $wants_notification);
      if ($contribution_recur['campaign_id'] != $campaign_id) {
        $suggestion->addEvidence($this->_plugin_config->campaign_penalty, ts("The campaign of the transaction differs from the campaign of the avtale"));
        $probability = $probability - $this->_plugin_config->campaign_penalty;
      }
      if ($mandate['reference'] != $kid) {
        $suggestion->addEvidence($this->_plugin_config->mandate_ref_penalty, ts("The KID of the mandate does not match the KID"));
        $probability = $probability - $this->_plugin_config->mandate_ref_penalty;
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
    $mandate_id = $match->getParameter('mandate_id');
    CRM_Kidbanking_Utils::enableMandate($mandate_id);

    // Store notification from bank
    $contribution_recur_id = $match->getParameter('contribution_recur_id');
    $wants_notification = $match->getParameter('wants_notification');
    CRM_Kidbanking_Utils::updateNotificationFromBank($contribution_recur_id, $wants_notification);

    // save the account
    $this->storeAccountWithContact($btx, $match->getParameter('contact_id'));

    $newStatus = banking_helper_optionvalueid_by_groupname_and_name('civicrm_banking.bank_tx_status', 'Processed');
    $btx->setStatus($newStatus);
    parent::execute($match, $btx);
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

    $contact_id = $suggestion->getParameter('contact_id');
    $mandate_id = $suggestion->getParameter('mandate_id');
    $contribution_recur_id = $suggestion->getParameter('contribution_recur_id');

    $contact = civicrm_api3('Contact', 'getsingle', array('id' => $contact_id));
    $contribution_recur = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $contribution_recur_id));
    $mandate = civicrm_api3('SepaMandate', 'getsingle', array('id' => $mandate_id));

    // assign to smarty and compile HTML
    $smarty_vars['contact'] = $contact;
    $smarty_vars['contribution_recur'] = $contribution_recur;
    $smarty_vars['mandate'] = $mandate;
    $smarty_vars['wants_notification'] = $suggestion->getParameter('wants_notification');

    if ($contribution_recur['campaign_id']) {
      $smarty_vars['campaign'] = civicrm_api3('Campaign', 'getvalue', array('return' => 'title', 'id' => $contribution_recur['campaign_id']));
    }
    $smarty_vars['penalties'] = $suggestion->getEvidence();

    $smarty = CRM_Banking_Helpers_Smarty::singleton();
    $smarty->pushScope($smarty_vars);
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/StartAvtale.suggestion.tpl');
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
    $html_snippet = $smarty->fetch('CRM/Banking/PluginImpl/Matcher/StartAvtale.execution.tpl');
    $smarty->popScope();
    return $html_snippet;
  }
}
