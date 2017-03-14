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
        CRM_Banking_Matcher_Suggestion_UpdateExistingContributionBasedOnKid::execute($match, $btx);
        break;
    }
  }

}
