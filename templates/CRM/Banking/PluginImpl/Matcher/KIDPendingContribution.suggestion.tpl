{assign var=contact_id value=$contact.id}
{assign var=recurring_contribution_id value=$contribution.contribution_recur_id}
{assign var=contribution_id value=$contribution.id}

{* calculate a more user friendly display of the recurring_contribution transaction interval *}
{if $recurring_contribution}
    {if $recurring_contribution.frequency_unit eq 'month'}
        {if $recurring_contribution.frequency_interval eq 1}
            {capture assign=frequency_words}{ts}monthly{/ts}{/capture}
        {elseif $recurring_contribution.frequency_interval eq 3}
            {capture assign=frequency_words}{ts}quarterly{/ts}{/capture}
        {elseif $recurring_contribution.frequency_interval eq 6}
            {capture assign=frequency_words}{ts}semi-annually{/ts}{/capture}
        {elseif $recurring_contribution.frequency_interval eq 12}
            {capture assign=frequency_words}{ts}annually{/ts}{/capture}
        {else}
            {capture assign=frequency_words}{ts 1=$recurring_contribution.frequency_interval}every %1 months{/ts}{/capture}
        {/if}
    {elseif $recurring_contribution.frequency_unit eq 'year'}
        {if $recurring_contribution.frequency_interval eq 1}
            {capture assign=frequency_words}{ts}annually{/ts}{/capture}
        {else}
            {capture assign=frequency_words}{ts 1=$recurring_contribution.frequency_interval}every %1 years{/ts}{/capture}
        {/if}
    {else}
        {capture assign=frequency_words}{ts}on an irregular basis{/ts}{/capture}
    {/if}
{/if}

<div>
    <p>
        {capture assign=address_text}{if $contact.city}{$contact.street_address}, {$contact.city}{else}{ts}Address incomplete{/ts}{/if}{/capture}
        {capture assign=contact_link}<a title="{$address_text}" href="{crmURL p="civicrm/contact/view" q="reset=1&cid=$contact_id"}">{$contact.display_name} [{$contact.id}]</a>{/capture}
        {if $recurring_contribution}
        	{ts 1=$contact_link}%1 maintains a matching recurring contribution.{/ts}
      	{else}
      		{ts 1=$contact_link}%1 has a pending contribution.{/ts}
      	{/if}
        {ts 1=$update_contribution_status}If you confirm this suggestion the existing contribution status will be updated to %1.{/ts}
    </p>
</div>

<div>
    <table border="1">
        <tbody>
        <tr>
            <td>
                <div class="suggestion-header">{ts}Contribution ID{/ts}</div>
            </td>
            <td>
                <div class="suggestion-value popup"><a href="{crmURL p="civicrm/contact/view/contribution" q="reset=1&id=$contribution_id&cid=$contact_id&action=view"}">[{$contribution_id}]</a></div>
            </td>
        </tr>
        {if $recurring_contribution}
        <tr>
            <td>
                <div class="suggestion-header">{ts}Contribution Recur ID{/ts}</div>
            </td>
            <td>
                <div class="suggestion-header">{if ($recurring_contribution_id)}<a href="{crmURL p="civicrm/contact/view/contributionrecur" q="reset=1&id=$recurring_contribution_id&cid=$contact_id"}">[{$recurring_contribution_id}]</a>{/if} {if ($frequency_words)}{$frequency_words}{/if}</div>
            </td>
        </tr>
        {/if}
        <tr>
            <td>
                <div class="suggestion-header">{ts}Amount{/ts}</div>
            </td>
            <td>
                <div class="suggestion-value">{$contribution.total_amount|crmMoney:$contribution.currency}</div>
            </td>
        </tr><tr>
            <td>
                <div class="suggestion-header">{ts}Type{/ts}</div>
            </td>
            <td>
                <div class="suggestion-value">{$contribution.financial_type}</div>
            </td>
        </tr><tr>
            <td>
                <div class="suggestion-header">{ts}Campaign{/ts}</div>
            </td>
            <td>
                <div class="suggestion-value">{if ($contribution.campaign)}{$contribution.campaign}{/if}</div>
            </td>
        </tr><tr>
            <td>
                <div class="suggestion-header">{ts}Due{/ts}</div>
            </td>
            <td>
                <div class="suggestion-value">{$contribution.receive_date|crmDate:$config->dateformatFull}</div>
            </td>
        </tr><tr>
            <td>
                <div class="suggestion-header">{ts}Status{/ts}</div>
            </td>
            <td>
                <div class="suggestion-value">{$contribution.contribution_status}</div>
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