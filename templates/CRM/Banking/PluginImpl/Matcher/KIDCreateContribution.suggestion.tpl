<div>
    <table border="1">
        <tbody>
        <tr>
            <td>
                <div class="suggestion-header">{ts}Amount{/ts}</div>
            </td>
            <td>
                <div class="suggestion-value">{$amount|crmMoney:$currency}</div>
            </td>
        </tr>
        <tr>
          <td>
          	<div class="suggestion-header">{ts}Donor{/ts}:</div>
        	</td>
        	<td>
            <div class="suggestion-value">
          		<input type="text" class="crm-form-entityref required" placeholder="{ts}- select contact -{/ts}" data-select-params="[]" data-api-params="{literal}{'extra':['email']}{/literal}" data-api-entity="contact" data-create-links="true" name="lookup_contact_id" id="lookup_contact_id" value="{if (!empty($contact_id))}{$contact_id}{/if}" />
            </div>
          </td>
        </tr>
        <tr>
            <td>
                <div class="suggestion-header">{ts}Type{/ts}</div>
            </td>
            <td>
                <div class="suggestion-value">
                    <select name="kid_create_contribution_financial_type_id" class="crm-form-select">
                        <option value="">{ts} - Select - {/ts}</option>
                        {foreach from=$financial_types item=financial_type key=financial_type_id}
                            <option value="{$financial_type_id}" {if $financial_type_id == $selected_financial_type_id}selected="selected"{/if}>{$financial_type}</option>
                        {/foreach}
                    </select>
                </div>
            </td>
        </tr><tr>
            <td>
                <div class="suggestion-header">{ts}Payment instrument{/ts}</div>
            </td>
            <td>
                <div class="suggestion-value">
                    <select name="kid_create_contribution_payment_instrument_id" class="crm-form-select">
                        <option value="">{ts} - Select - {/ts}</option>
                        {foreach from=$payment_instruments item=payment_instrument key=payment_instrument_id}
                            <option value="{$payment_instrument_id}" {if $payment_instrument_id == $selected_payment_instrument_id}selected="selected"{/if}>{$payment_instrument}</option>
                        {/foreach}
                    </select>
                </div>
            </td>
        </tr><tr>
            <td>
                <div class="suggestion-header">{ts}Campaign{/ts}</div>
            </td>
            <td>
                <div class="suggestion-value">
                    <select name="kid_create_contribution_campaign_id" class="crm-form-select">
                        <option value="">{ts} - Select - {/ts}</option>
                        {foreach from=$campaigns item=campaign key=campaign_id}
                            <option value="{$campaign_id}" {if $campaign_id == $selected_campaign_id}selected="selected"{/if}>{$campaign}</option>
                        {/foreach}
                    </select>
                </div>
            </td>
        </tr><tr>
            <td>
                <div class="suggestion-header">{ts}Date{/ts}</div>
            </td>
            <td>
                <div class="suggestion-value">{$date|crmDate:$config->dateformatFull}</div>
            </td>
        </tr><tr>
            <td>
                <div class="suggestion-header">{ts}Status{/ts}</div>
            </td>
            <td>
                <div class="suggestion-value">{$create_contribution_status}</div>
            </td>
        </tr>
        </tbody>
    </table>
</div>

{if $penalties}
    <div>
        {ts}This suggestion has been downgraded:{/ts}
        <ul>
            {foreach from=$penalties item=reason}
                <li>{$reason}</li>
            {/foreach}
        </ul>
    </div>
{/if}

{literal}
<script language="JavaScript">
cj(function($){
  $('#lookup_contact_id').on('change', function(e) {
    e.stopPropagation();
    $('tr.suggestion:first').trigger('click');
  });
});
</script>
{/literal}