{assign var=contribution_id value=$contribution.id}
{assign var=contact_id value=$contribution.contact_id}

{capture assign=address_text}{if $contact.city}{$contact.street_address}, {$contact.city}{else}{ts}Address incomplete{/ts}{/if}{/capture}
{capture assign=contact_link}<a title="{$address_text}" href="{crmURL p="civicrm/contact/view" q="reset=1&cid=$contact_id"}">{$contact.display_name} [{$contact.id}]</a>{/capture}
{capture assign=contribution_link}<a href="{crmURL p="civicrm/contact/view/contribution" q="reset=1&id=$contribution_id&cid=$contact_id&action=view"}">[{$contribution_id}]</a>{/capture}

{capture assign=contribution_list}{$contribution_link}{/capture}

<p>
    {ts 1=$contribution_list 2=$contact_link}This transaction has added contribution %1 for %2{/ts}
</p>
