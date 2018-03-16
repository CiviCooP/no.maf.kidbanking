<?php

/**
 * Kid.Generate API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_kid_Generate_spec(&$spec) {
  $spec['contact_id']['api.required'] = 1;
  $spec['contact_id']['type'] = 'Integer';
  $spec['campaign_id']['api.required'] = 1;
  $spec['campaign_id']['type'] = 'Integer';
}

/**
 * Kid.Generate API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_kid_Generate($params) {
  $contact_id = $params['contact_id'];
  $campaign_id = $params['campaign_id'];
	$store = true;
	if (isset($params['store'])) {
		$store = $params['store'] ? true : false;
	}
  $kid_number = kidbanking_generate_kidnumber($contact_id, $campaign_id, $store);
  $entity['kid_number'] = $kid_number;
  return $entity;
}
