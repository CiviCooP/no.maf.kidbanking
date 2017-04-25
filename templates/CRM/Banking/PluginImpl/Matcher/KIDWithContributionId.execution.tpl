{assign var=contribution_id value=$contribution.id}
{assign var=contact_id value=$contribution.contact_id}

{capture assign=contribution_link}<a href="{crmURL p="civicrm/contact/view/contribution" q="reset=1&id=$contribution_id&cid=$contact_id&action=view"}">{$contribution_id}</a>{/capture}

<p>
    {ts 1=$contribution_link 2=$update_contribution_status}This transaction did find a contribution (%1) based on the KID and updated the status to %2.{/ts}
</p>
