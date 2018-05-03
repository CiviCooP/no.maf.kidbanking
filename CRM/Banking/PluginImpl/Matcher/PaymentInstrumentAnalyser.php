<?php

class CRM_Banking_PluginImpl_Matcher_PaymentInstrumentAnalyser extends CRM_Banking_PluginModel_Analyser {
  
  /**
   * class constructor
   */ 
  function __construct($config_name) {
    parent::__construct($config_name);
    // read config, set defaults
    $config = $this->_plugin_config;
    if (!isset($config->transaction_types))         $config->transaction_types        = new stdClass();
    if (!isset($config->payment_instrument_field))  $config->payment_instrument_field = 'payment_instrument_id';
    if (!isset($config->transaction_type_field))    $config->transaction_type_field   = 'transactionType';  
  }
  
  /** 
   * this matcher does not really create suggestions, but rather enriches the parsed data
   */
  public function analyse(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {
    $config = $this->_plugin_config;      
    $data = $btx->getDataParsed();
    
    if (isset($data[$config->transaction_type_field])) {
      $transactionType = $data[$config->transaction_type_field];
      if (isset($config->transaction_types->$transactionType)) { 
        $data[$config->payment_instrument_field] = $config->transaction_types->$transactionType;
      }
    }
    $btx->setDataParsed($data);
    $btx->save();
  }
}
