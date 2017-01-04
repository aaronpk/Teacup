<div class="narrow">

  <div class="jumbotron">

    <h3>Device Code</h3>
    <p id="device-message">If you are prompted to log in on a device, click the button below to generate a device code.</p>

    <div id="device-code">
      <input type="button" class="btn btn-primary" value="Generate Device Code" id="generate-code">
    </div>

  </div>

</div>
<style type="text/css">
.screenshot {
  -webkit-border-radius: 6px;
  -moz-border-radius: 6px;
  border-radius: 6px;
}
</style>
<script>
$(function(){
  $("#generate-code").click(function(){
    $.post("/settings/device-code", {
      generate: 1
    }, function(response){
      $("#device-code").html('<h3>'+response.code+'</h3>');
      $("#device-message").html('Enter the code below on the device in order to sign in. This code is valid for 5 minutes.');
      setTimeout(function(){
        window.location.reload();
      }, 1000*300);
    });
  });
})
</script>
