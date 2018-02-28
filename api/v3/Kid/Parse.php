<?php

/**
 * Kid.Parse API specification (optional)
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_kid_Parse_spec(&$spec) {
  $spec['kid']['api.required'] = 1;
}

/**
 * Kid.Parse API
 *
 * Parses an KID number and returns the contact_id, campaign_id and when
 * present the contribution_id
 *
 * Will also do some extra validation whether the contact exists or whether the contact
 * has been merged.
 *
 * The KID should consist of:
 *   pos 1-7 - 7 digits for contact ID (allowing a maximum of 10 million donors), left padded with zeros
 *   pos 8-13 - 6 digits for campaign ID (allowing a maximum of 1 million campaigns), left padded with zeros
 *   final digit is a control digit. Calculated through the Modulo 10 algorithm
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_kid_Parse($params) {
  $kid = ltrim($params['kid']);
  $contact_id = kidbanking_find_contact_id_by_kid($kid);
  $campaign_id = false;

  // Check whether the KID has a valid length to hold the campaign_id
  if (strlen($kid) == 22 || strlen($kid) == 14) {
    $campaign_id = (int) substr($kid, 7, 6);
  }

  // When contact_id is missing is missing then we are not able to match
  // those transactions.
  if (empty($contact_id)) {
    return civicrm_api3_create_error("Invalid KID number", $params);
  }

  $entity['contact_id'] = $contact_id;
  $entity['campaign_id'] = $campaign_id;

  return $entity;
}