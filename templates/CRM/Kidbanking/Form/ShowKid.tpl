<div class="crm-block crm-form-block">
  <table class="form-layout">
    <tr>
      <td class="label">{$form.contact_id.label}</td>
      <td>{$form.contact_id.html}</td>
    </tr>

    <tr>
      <td class="label">{$form.campaign_id.label}</td>
      <td>{$form.campaign_id.html}</td>
    </tr>

    <tr id="GeneratedKidNumber">
      <td class="label"></td>
      <td><h2 class="value"></h2></td>
    </tr>

  </table>

  <div class="crm-submit-buttons">
      <a class="button" id="btnShowKid">{ts}Show Kid{/ts}</a>
  </div>
</div>

<script type="text/javascript">
  {literal}
  cj(function() {
    cj('#btnShowKid').click(showKid);
  });


  function showKid() {
    var contact_id = cj('#contact_id').val();
    var campaign_id = cj('#campaign_id').val();
    cj('#GeneratedKidNumber .value').html('');
    if (!contact_id && !campaign_id) {
      alert(ts('Contact and Campaign are required'));
    } else if (!contact_id) {
      alert(ts('Contact is required'));
    } else if (!campaign_id) {
      alert(ts('Campaign is required'));
    }

    if (contact_id && campaign_id) {
      CRM.api3('Kid', 'generate', {
        "sequential": 1,
        "contact_id": contact_id,
        "campaign_id": campaign_id,
      }).done(function(result) {
        cj('#GeneratedKidNumber .value').html(result.kid_number);
      });
    }
  }

  {/literal}
</script>