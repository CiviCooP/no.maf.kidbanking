{capture assign=contribution_recur_link}<a href="{crmURL p="civicrm/contact/view/contributionrecur" q="reset=1&id=$contribution_recur_id&cid=$contact_id"}">{$contribution_recur_id}</a>{/capture}

<p>
    {ts 1=$contribution_recur_link}This transaction created an Avtalle %1 from a printed giro{/ts}
</p>
