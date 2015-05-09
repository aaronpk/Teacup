  <div class="narrow">
    <?= partial('partials/header') ?>

      <form role="form" style="margin-top: 20px;" id="note_form" action="/post" method="post">

        <div id="entry-buttons">
          <?= partial('partials/entry-buttons', ['options'=>$this->default_options]) ?>
        </div>

        <div class="form-group">
          <h3>Location <input type="checkbox" id="note_location_chk" value=""><img src="/images/spinner.gif" id="note_location_loading" style="display: none;"></h3>

          <input type="text" id="note_location_msg" value="" class="form-control" placeholder="" readonly="readonly">
          <input type="hidden" id="note_location" name="location">
          <input type="hidden" id="location_enabled" value="<?= $this->location_enabled ?>">

          <div id="note_location_img" style="display: none;">
            <img src="" height="180" id="note_location_img_wide" class="img-responsive">
            <img src="" height="320" id="note_location_img_small" class="img-responsive">
          </div>
        </div>
        
        <div class="form-group">
          <h3>Date</h3>
          
          <input type="date" value="<?= date('Y-m-d') ?>" id="note_date">
          <input type="time" value="<?= date('H:i') ?>" id="note_time">
        </div>
      </form>

      <?php if($this->micropub_endpoint): ?>
      <div class="scroll-container">
        <div class="callout">
          <p>Clicking an item will post this note to your Micropub endpoint. Below is some information about the request that will be made.</p>

          <table class="table table-condensed">
            <tr>
              <td>me</td>
              <td class="break"><code><?= session('me') ?></code> (should be your URL)</td>
            </tr>
            <tr>
              <td>scope</td>
              <td class="break"><code><?= $this->token_scope ?></code> (should be a space-separated list of permissions including "post")</td>
            </tr>
            <tr>
              <td>micropub endpoint</td>
              <td class="break"><code><?= $this->micropub_endpoint ?></code> (should be a URL)</td>
            </tr>
            <tr>
              <td>access token</td>
              <td class="break">String of length <b><?= strlen($this->access_token) ?></b><?= (strlen($this->access_token) > 0) ? (', ending in <code>' . substr($this->access_token, -7) . '</code>') : '' ?> (should be greater than length 0)</td>
            </tr>
            <tr>
              <td>p3k-food</td>
              <td class="break">The button you tap (or your custom text) will be sent to your Micropub endpoint in a field named <code>p3k-food</code></td>
            </tr>
            <tr>
              <td>p3k-type</td>
              <td class="break">Will be either <code>drink</code> or <code>eat</code> depending on the type of entry</td>
            </tr>
          </table>
        </div>
      </div>
      <?php endif; ?>

  </div>

<script>
$(function(){

  function bind_keyboard_shortcuts() {
    $(".text-custom-eat").keydown(function(e){
      if(e.keyCode == 13) {
        $(".btn-custom-eat").click();
        return false;
      }
    });
    $(".text-custom-drink").keydown(function(e){
      if(e.keyCode == 13) {
        $(".btn-custom-drink").click();
        return false;
      }
    });
  }

  bind_keyboard_shortcuts();

  function location_error(msg) {
    $("#note_location_msg").val(msg);
    $("#note_location_chk").removeAttr("checked");
    $("#note_location_loading").hide();
    $("#note_location_img").hide();
    $("#note_location_msg").removeClass("img-visible");
  }

  var map_template_wide = "<?= build_static_map_url('{lat}', '{lng}', 180, 700, 15) ?>";
  var map_template_small = "<?= build_static_map_url('{lat}', '{lng}', 320, 480, 15) ?>";

  function fetch_location() {
    $("#note_location_loading").show();

    navigator.geolocation.getCurrentPosition(function(position){

      $.get('/options', {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude
      }, function(response) {
        // save and restore the value entered in the custom fields 
        var custom_eat = $('#custom_eat').val();
        var custom_drink = $('#custom_drink').val();

        var selected = false;
        if($("#custom_drink:focus").length == 1) {
          selected = '#custom_drink';
        }
        if($("#custom_eat:focus").length == 1) {
          selected = '#custom_eat';
        }

        $("#entry-buttons").html(response);

        // restore the custom values entered
        $('#custom_eat').val(custom_eat);
        $('#custom_drink').val(custom_drink);

        if(selected) {
          $(selected).focus();
        }

        bind_keyboard_shortcuts();
      });

      $("#note_location_loading").hide();
      var geo = "geo:" + (Math.round(position.coords.latitude * 100000) / 100000) + "," + (Math.round(position.coords.longitude * 100000) / 100000) + ";u=" + position.coords.accuracy;
      $("#note_location_msg").val(geo);
      $("#note_location").val(geo);
      $("#note_location_img_small").attr("src", map_template_small.replace('{lat}', position.coords.latitude).replace('{lng}', position.coords.longitude));
      $("#note_location_img_wide").attr("src", map_template_wide.replace('{lat}', position.coords.latitude).replace('{lng}', position.coords.longitude));
      $("#note_location_img").show();
      $("#note_location_msg").addClass("img-visible");

    }, function(err){
      if(err.code == 1) {
        location_error("The website was not able to get permission");
      } else if(err.code == 2) {
        location_error("Location information was unavailable");
      } else if(err.code == 3) {
        location_error("Timed out getting location");
      }
    });
  }

  $("#note_location_chk").click(function(){
    if($(this).attr("checked") == "checked") {
      if(navigator.geolocation) {
        $.post("/prefs", {
          enabled: 1
        });
        fetch_location();
      } else {
        location_error("Browser location is not supported");
      }
    } else {
      $("#note_location_img").hide();
      $("#note_location_msg").removeClass("img-visible");
      $("#note_location_msg").val('');
      $("#note_location").val('');

      $.post("/prefs", {
        enabled: 0
      });
    }
  });

  if($("#location_enabled").val() == 1) {
    $("#note_location_chk").attr("checked","checked");
    fetch_location();
  }

});

</script>
<style type="text/css">
.scroll-container {
  width: 100%;
  overflow-x: scroll;
}
.scroll-container .break {
  word-break: break-all;
}
</style>