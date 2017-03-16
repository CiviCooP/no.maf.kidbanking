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
 *   post 14-21 - 8 digits for contribution ID (allowing a maximum of 100 million contributions, left padded with zeros. This part of the KID will only be used for AvtaleGiro collections where MAF Norge collects the money from the bank (OCR export)
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

  // Check whether the KID has a valid length.
  // A length of 22 indicates that contact_id, campaign_id and contribution id are included.
  if (strlen($kid) != 22 && strlen($kid) != 14) {
    return civicrm_api3_create_error("Invalid KID number", $params);
  }

  $contact_id = (int) substr($kid, 0, 7);
  $campaign_id = (int) substr($kid, 7, 6);
  $contribution_id = false;
  if (strlen($kid) == 22) {
    $contribution_id = (int) substr($kid, 13, 8);
  }

  // When contact_id is missing or campaign_id is missing then we are not able to match
  // those transactions.
  if (empty($contact_id) || empty($campaign_id)) {
    return civicrm_api3_create_error("Invalid KID number", $params);
  }

  // Check if the de.systopia.identitytracker extension is installed.
  // We do this by checking whether the api contact.findbyidentity exists.
  $contact_actions = civicrm_api3('Contact', 'getactions', array());
  if (in_array('findbyidentity', $contact_actions['values'])) {
    $result = civicrm_api3('Contact', 'findbyidentity', array(
      'identifier_type' => 'internal',
      'identifier' => $contact_id,
    ));
    if ($result['count'] != 1) {
      return civicrm_api3_create_error("Invalid KID number", $params);
    }
    $contact_id = $result['id'];
  }

  $entity['contact_id'] = $contact_id;
  $entity['campaign_id'] = $campaign_id;
  $entity['contribution_id'] = $contribution_id;

  return $entity;
}