{assign var=contribution_id value=$contribution.id}
{assign var=contact_id value=$contribution.contact_id}

{capture assign=contribution_link}<a href="{crmURL p="civicrm/contact/view/contribution" q="reset=1&id=$contribution_id&cid=$contact_id&action=view"}">[{$contribution_id}]</a>{/capture}

<p>
    {ts 1=$contribution_link}This transaction has updated contribution %1.{/ts}
</p>
