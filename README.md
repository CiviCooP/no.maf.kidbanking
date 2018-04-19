# no.maf.kidbanking

This extension provides functionality to generate KID numbers and to match CiviBanking transactions on the given KID number.
It also provides export functionality of contacts with a KID number

The KID should consist of:

* pos 1-7 - 7 digits for contact ID (allowing a maximum of 10 million donors), left padded with zeros
* pos 8-13 - 6 digits for campaign ID (allowing a maximum of 1 million campaigns), left padded with zeros
* final digit is a control digit (calculation to be specified)

See https://civicoop.plan.io/projects/maf-norge-civicrm-support-2016/wiki/KID_and_AvtaleGiro_background for more information.

## Provided matchers for CiviBanking

* **Find any pending contribution** This matcher will lookup all pending contributions for a contact. The contact is derived from the KID. The contribution status is updated to completed.
* **Create a contribution** Create a new contribution for a contact. The contact is derived from the KID.

* **Start avtale from printed giro** This matcher will lookup all active printed giro for a contact and gives the possibility to start an avtale from it
* **Start avtale** This matcher will lookup all inactive avtales will activate the selected avtale
* **Change notification from bank** This matcher will lookup active avtales and will update the notification from bank field on the selected avtale.
* **Stop avtale** This matcher will lookup all active avtales and gives the possibility to stop the avtale

## API: Kid.Generate

This extension implements an API for generating a KID (_Kid.Generate_).
The API will not check whether the contact exists or whether the campaign or contribution exists.
After generation the KID will be stored in the _identitytracker extension_.

**Parameters**

| Parameter   	        | Type   	| Required  	|
|-----------------------|-----------|---------------|
| contact_id            | integer   | Required      |
| campaign_id           | integer   | Required      |

**Return value**

The _Kid.Generate_ returns an array containing a key _kid_number_ which holds the generated the kid number.

**Example code**

    $return = civicrm_api3('Kid', 'Generate', array('contact_id' => 34341, 'campaign_id' => 23);
    $kid_number = $return['kid_number']; // Which is 0034341000023

## API: Kid.Parse

This extension implements an API for parsing a KID (_Kid.Parse_).
The api will translate the KID into a contact_id, a campaign_id and a contribution_recur_id 
The contact_id is will be looked up through the _identitytracker extension_. 
When an invalid KID number is given the api will return an error. 

**Parameters**

| Parameter             | Type      | Required       |
|-----------------------|-----------|----------------|
| kid                   | string    | Required       |

**Return**

| Parameter   	        | Type   	| Required  	 |
|-----------------------|-----------|----------------|
| contact_id            | integer   | Always present |
| campaign_id           | integer   | Optional       |

**Example code**

    $return = civicrm_api3('Kid', 'Parse', array('kid' => '0034341000023'));
    // return values are:
    // $return['contact_id'] = 34341;
    // $return['campaign_id'] = 23;
    
## KID Matcher

The KID matcher looks for pending contributions based on the KID number. 
When a pending contribution is found the contribution status is updated to completed.

The matcher will also check for the following criteria and if not matched it will lower the probability of the matching:

* Whether the contribution belongs to the same contact as the contact_id in the KID number
* Whether the contribution has the same amount as the amount specified in the transaction
* Whether the contribution has the status Pending
* Whether the contribution is on the same date as the date in the transaction file
* Whether the contribution is linked to the same campaign as the campaign id in the transaction
* Whether the campaign and the contact could be found based on the kid number
* Whether the transaction type (15) matches with a recurring contribution

### How to set automatic matching and update?

Update in the database the config of the plugin instance set the config to

    {
    	"auto_exec": true,
        "threshold": 1.0
    }

The value of 1.0 is the threshold for the matching percentage (1.0 means 100% sure that the contribution is found).

## Unit tests

This extension provides the following tests

* _api_v3_Kid_GenerateTest_ will test whether _Kid.Generate_ api is working as expected.
    `phpunit4 tests/phpunit/api/v3/Kid/GenerateTest.php`

## ToDo

The following things are not (yet) implemented:

* The kid.parse api should also lookup kid numbers on the dev.systopia.identitytracker extension. This in case a contact has been merged and the old contact_id is present in the kid number.

## See also

* Explanation of the Modules 10 algorithm: https://en.wikipedia.org/wiki/Luhn_algorithm
* no.maf.ocrimporter (https://github.com/civicoop/no.maf.ocrimporter)
* no.maf.avtalebanking (https://github.com/civicoop/no.maf.avtalebanking)



