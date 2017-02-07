# no.maf.kidbanking

This extension provides functionality to generate KID numbers.

The KID should consist of:

* pos 1-7 - 7 digits for contact ID (allowing a maximum of 10 million donors), left padded with zeros
* pos 8-13 - 6 digits for campaign ID (allowing a maximum of 1 million campaigns), left padded with zeros
* post 14-21 - 8 digits for contribution ID (allowing a maximum of 100 million contributions, left padded with zeros. This part of the KID will only be used for AvtaleGiro collections where MAF Norge collects the money from the bank (OCR export)
* final digit is a control digit (calculation to be specified)

See https://civicoop.plan.io/projects/maf-norge-civicrm-support-2016/wiki/KID_and_AvtaleGiro_background for more information.

## API: Kid.Generate

This extension implements an API for generating a KID (_Kid.Generate_).
The API will not check whether the contact exists or whether the campaign or contribution exists.

**Parameters**

| Parameter   	    | Type   	| Required  	|
|-------------------|-----------|---------------|
| contact_id        | integer   | Required      |
| campaign_id       | integer   | Required      |
| contribution_id   | integer   | not required  |

**Return value**

The _Kid.Generate_ returns an array containing a key _kid_number_ which holds the generated the kid number.

**Example code**

    $return = civicrm_api3('Kid', 'Generate', array('contact_id' => 34341, 'campaign_id' => 23);
    $kid_number = $return['kid_number']; // Which is 0034341000023

## Unit tests

This extension provides the following tests

* _api_v3_Kid_GenerateTest_ will test whether _Kid.Generate_ api is working as expected.
    `phpunit4 tests/phpunit/api/v3/Kid/GenerateTest.php`

## See also

* Explenation of the Modules 10 algorithm: https://en.wikipedia.org/wiki/Luhn_algorithm



