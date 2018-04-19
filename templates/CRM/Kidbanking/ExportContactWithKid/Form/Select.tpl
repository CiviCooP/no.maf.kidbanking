{include file="CRM/Export/Form/Select.tpl"}

<div id="campaignSelectLabel" class="label crm-label-campaign-select">{$form.campaign_id.label}</div>
<div id = "campaignSelectContent" class= "content crm-content crm-campaign-select">{$form.campaign_id.html}</div>

{literal}
  <script type="text/javascript">
    cj("#campaignSelectLabel").insertAfter(".crm-content-additionalGroup");
    cj("#campaignSelectContent").insertAfter("#campaignSelectLabel");
  </script>
{/literal}