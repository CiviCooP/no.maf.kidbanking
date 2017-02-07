<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * This test class will test whether the API for generating the Kid Number works as expected.
 *
 * @group headless
 */
class api_v3_Kid_GenerateTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface {

  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  /**
   * This test whether the generate function works.
   */
  public function testChecksumCalculation() {
    // Kid Number = 79927398713
    // This number is taken from https://en.wikipedia.org/wiki/Luhn_algorithm
    $result = kidbanking_generate_checksum_digit('7992739871');
    $this->assertSame($result, '3', 'Checksum digit generation failed got '.$result);

    // Kid Number = 005304373
    // This number comes from an test OCR file
    $result = kidbanking_generate_checksum_digit('00530437');
    $this->assertSame($result, '3', 'Checksum digit generation failed got '.$result);

    // Kid Number = 005310321
    // This number comes from an test OCR file
    $result = kidbanking_generate_checksum_digit('00531032');
    $this->assertSame($result, '1', 'Checksum digit generation failed got '.$result);

    // Kid Number = 001562005329477
    // This number comes from an test OCR file
    $result = kidbanking_generate_checksum_digit('00156200532947');
    $this->assertSame($result, '7', 'Checksum digit generation failed got '.$result);
  }

  public function testKidGenerationForContactAndCampaign() {
    $params['contact_id'] = 34341;
    $params['campaign_id'] = 23;
    $params['version'] = 3;
    $result = civicrm_api('Kid', 'Generate', $params);
    $this->assertArrayHasKey('kid_number', $result);
    $this->assertSame($result['kid_number'], '00343410000230');
  }

  public function testKidGenerationForContactAndContribution() {
    $params['contact_id'] = 1752;
    $params['campaign_id'] = 104;
    $params['contribution_id'] = 231974;
    $params['version'] = 3;
    $result = civicrm_api('Kid', 'Generate', $params);
    $this->assertArrayHasKey('kid_number', $result);
    $this->assertSame($result['kid_number'], '0001752000104002319742');
  }

  public function testGenerationOfFailingKids() {
    // Should fail because campaign_id is not provided
    $params = array();
    $params['contact_id'] = 78901;
    $params['version'] = 3;
    $result = civicrm_api('Kid', 'Generate', $params);
    $this->assertArrayHasKey('is_error', $result);
    $this->assertNotEmpty($result['is_error']);

    // Should fail because contact_id is not provided
    $params = array();
    $params['campaign_id'] = 78901;
    $params['version'] = 3;
    $result = civicrm_api('Kid', 'Generate', $params);
    $this->assertArrayHasKey('is_error', $result);
    $this->assertNotEmpty($result['is_error']);

  }

}
