<?php

require_once 'kidbanking.civix.php';

/**
 * Generate a KID number based on the contact_id, campaign_id and optionally the contribution_id.
 *
 * The KID should consist of:
 *   pos 1-7 - 7 digits for contact ID (allowing a maximum of 10 million donors), left padded with zeros
 *   pos 8-13 - 6 digits for campaign ID (allowing a maximum of 1 million campaigns), left padded with zeros
 *   post 14-21 - 8 digits for contribution ID (allowing a maximum of 100 million contributions, left padded with zeros. This part of the KID will only be used for AvtaleGiro collections where MAF Norge collects the money from the bank (OCR export)
 *   final digit is a control digit. Calculated through the Modulo 10 algorithm
 *
 * @see https://civicoop.plan.io/projects/maf-norge-civicrm-support-2016/wiki/KID_and_AvtaleGiro_background#KID-generation
 *
 * @param $contact_id
 *   Required
 * @param $campaign_id
 *   Required
 * @param bool $contribution_id
 *   Optional
 * @return string
 *   The generated KidNumber
 */
function kidbanking_generate_kidnumber($contact_id, $campaign_id, $contribution_id=false) {
  $kidNumber = str_pad($contact_id, 7, '0', STR_PAD_LEFT);
  $kidNumber = $kidNumber . str_pad($campaign_id, 6, '0', STR_PAD_LEFT);
  if (!empty($contribution_id)) {
    $kidNumber = $kidNumber . str_pad($contribution_id, 8, '0', STR_PAD_LEFT);
  }
  $kidNumber = $kidNumber . kidbanking_generate_checksum_digit($kidNumber);
  return $kidNumber;
}

/**
 * This function calculate the checksum digit of the KID Number.
 * The algorithm for calculating the checksum digit is a modulo 10.
 * @See http://stackoverflow.com/a/4352921/3853493
 * @see https://en.wikipedia.org/wiki/Luhn_algorithm for explenation of the algorithm.
 *
 * @param $number
 * @return string
 */
function kidbanking_generate_checksum_digit($number) {
  $chars = str_split(strrev($number));
  $stack = 0;
  for($i=0; $i < count($chars); $i++) {
    $value = $chars[$i];
    if ($i % 2 == 0) {
      $value = array_sum(str_split($value * 2));
    }
    $stack += $value;
  }
  $stack %= 10;
  if ($stack != 0) {
    $stack -= 10;
    $stack = abs($stack);
  }
  return (string) $stack;
}

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function kidbanking_civicrm_config(&$config) {
  _kidbanking_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function kidbanking_civicrm_xmlMenu(&$files) {
  _kidbanking_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function kidbanking_civicrm_install() {
  _kidbanking_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function kidbanking_civicrm_postInstall() {
  _kidbanking_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function kidbanking_civicrm_uninstall() {
  _kidbanking_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function kidbanking_civicrm_enable() {
  _kidbanking_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function kidbanking_civicrm_disable() {
  _kidbanking_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function kidbanking_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _kidbanking_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function kidbanking_civicrm_managed(&$entities) {
  _kidbanking_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function kidbanking_civicrm_caseTypes(&$caseTypes) {
  _kidbanking_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function kidbanking_civicrm_angularModules(&$angularModules) {
  _kidbanking_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function kidbanking_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _kidbanking_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 *
function kidbanking_civicrm_preProcess($formName, &$form) {

} // */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 *
function kidbanking_civicrm_navigationMenu(&$menu) {
  _kidbanking_civix_insert_navigation_menu($menu, NULL, array(
    'label' => ts('The Page', array('domain' => 'no.maf.kidbanking')),
    'name' => 'the_page',
    'url' => 'civicrm/the-page',
    'permission' => 'access CiviReport,access CiviContribute',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _kidbanking_civix_navigationMenu($menu);
} // */
