{assign var=contact_id value=$contact.id}

{if $contribution_recur.frequency_unit eq 'month'}
    {if $contribution_recur.frequency_interval eq 1}
        {capture assign=frequency_words}{ts}monthly{/ts}{/capture}
    {elseif $contribution_recur.frequency_interval eq 3}
        {capture assign=frequency_words}{ts}quarterly{/ts}{/capture}
    {elseif $contribution_recur.frequency_interval eq 6}
        {capture assign=frequency_words}{ts}semi-annually{/ts}{/capture}
    {elseif $contribution_recur.frequency_interval eq 12}
        {capture assign=frequency_words}{ts}annually{/ts}{/capture}
    {else}
        {capture assign=frequency_words}{ts 1=$contribution_recur.frequency_interval}every %1 months{/ts}{/capture}
    {/if}
{elseif $contribution_recur.frequency_unit eq 'year'}
    {if $contribution_recur.frequency_interval eq 1}
        {capture assign=frequency_words}{ts}annually{/ts}{/capture}
    {else}
        {capture assign=frequency_words}{ts 1=$contribution_recur.frequency_interval}every %1 years{/ts}{/capture}
    {/if}
{else}
    {capture assign=frequency_words}{ts}on an irregular basis{/ts}{/capture}
{/if}

<div>
    <p>
        {capture assign=address_text}{if $contact.city}{$contact.street_address}, {$contact.city}{else}{ts}Address incomplete{/ts}{/if}{/capture}
        {capture assign=contact_link}<a title="{$address_text}" href="{crmURL p="civicrm/contact/view" q="reset=1&cid=$contact_id"}">{$contact.display_name} [{$contact.id}]</a>{/capture}
        {ts 1=$contact_link}%1 maintains a matching avtale.{/ts}
        {ts}If you confirm this suggestion the existing avtale will be enabled and the field notification from bank will be updated.{/ts}
    </p>
</div>

<div>
    <table border="1">
        <tbody>
        <tr>
            <td>
                <div class="suggestion-header">{ts}Amount{/ts}</div>
            </td>
            <td>
                <div class="suggestion-value">{$contribution_recur.amount|crmMoney:$contribution_recur.currency}</div>
            </td>
        </tr><tr>
            <td>
                <div class="suggestion-header">{ts}Frequency{/ts}</div>
            </td>
            <td>
                <div class="suggestion-value">{$frequency_words}</div>
            </td>
        </tr><tr>
            <td>
                <div class="suggestion-header">{ts}KID of the mandate{/ts}</div>
            </td>
            <td>
                <div class="suggestion-value">{$mandate.reference}</div>
            </td>
        </tr><tr>
            <td>
                <div class="suggestion-header">{ts}Campaign{/ts}</div>
            </td>
            <td>
                <div class="suggestion-value">{if ($campaign)}{$campaign}{/if}</div>
            </td>
        </tr><tr>
            <td>
                <div class="suggestion-header">{ts}Wants notification{/ts}</div>
            </td>
            <td>
                <div class="suggestion-value">
                    {if ($wants_notification)}
                        {ts}Yes{/ts}
                    {else}
                        {ts}No{/ts}
                    {/if}
                </div>
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