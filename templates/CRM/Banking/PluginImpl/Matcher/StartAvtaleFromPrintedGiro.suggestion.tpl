{assign var=contact_id value=$contact.id}
<div>
    <p>
        {capture assign=address_text}{if $contact.city}{$contact.street_address}, {$contact.city}{else}{ts}Address incomplete{/ts}{/if}{/capture}
        {capture assign=contact_link}<a title="{$address_text}" href="{crmURL p="civicrm/contact/view" q="reset=1&cid=$contact_id"}">{$contact.display_name} [{$contact.id}]</a>{/capture}
        {ts 1=$contact_link}%1 maintains a matching printed giro.{/ts}
        {ts}If you confirm this suggestion the existing printed giro will be ended and a new avtale giro will be created.{/ts}
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
                <div class="suggestion-value">{$amount|crmMoney:'NOK'}</div>
            </td>
        </tr><tr>
            <td>
                <div class="suggestion-header">{ts}Frequency{/ts}</div>
            </td>
            <td>
                <div class="suggestion-value">{$frequency}</div>
            </td>
        </tr><tr>
            <td>
                <div class="suggestion-header">{ts}Cycle day{/ts}</div>
            </td>
            <td>
                <div class="suggestion-value">
                  <input name="cycle_day" value="{$cycle_day}" />
                </div>
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