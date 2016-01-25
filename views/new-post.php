  <div class="narrow">
    <?= partial('partials/header') ?>

      <form role="form" style="margin-top: 20px;" id="note_form" action="/post" method="post">

        <div class="form-group">
          <h3>Date</h3>

          <input type="date" class="form-control" style="max-width:160px; float:left; margin-right: 4px;" id="note_date" name="note_date" value="">
          <input type="text" class="form-control" style="max-width:85px; float:left; margin-right: 4px;" id="note_time" name="note_time" value="">
          <input type="text" class="form-control" style="max-width:75px;" id="note_tzoffset" name="note_tzoffset" value="">
        </div>

        <div id="entry-buttons">
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
            <?php if($this->enable_array_micropub): ?>
              <tr>
                <td>ate or drank</td>
                <td class="break">
                  <p>The parameter named <code>ate</code> or <code>drank</code> will include an <code>h-food</code> object corresponding to the item you tapped.</p>
                  <pre>ate[type]=h-food&amp;ate[properties][name]=Coffee</pre>
                </td>
              </tr>
            <?php else: ?>
            <tr>
              <td>h-food</td>
              <td class="break"><form action="/prefs/enable-h-food" method="post"><input type="submit" class="btn btn-default" value="Switch to new h-food format"></form></td>
            </tr>
            <tr>
              <td>p3k-food</td>
              <td class="break">The button you tap (or your custom text) will be sent to your Micropub endpoint in a field named <code>p3k-food</code></td>
            </tr>
            <tr>
              <td>p3k-type</td>
              <td class="break">Will be either <code>drink</code> or <code>eat</code> depending on the type of entry</td>
            </tr>
            <?php endif; ?>
          </table>
        </div>
      </div>
      <?php endif; ?>

  </div>

<script>
$(function(){

  function tz_seconds_to_offset(seconds) {
    var tz_offset = '';
    var hours = zero_pad(Math.abs(seconds / 60 / 60));
    var minutes = zero_pad(Math.floor(seconds / 60) % 60);
    return (seconds < 0 ? '-' : '+') + hours + ":" + minutes;
  }
  function zero_pad(num) {
    num = "" + num;
    if(num.length == 1) {
      num = "0" + num;
    }
    return num;
  }

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

  function location_error(msg) {
    $("#note_location_msg").val(msg);
    // $("#note_location_chk").removeAttr("checked");
    $("#note_location_loading").hide();
    $("#note_location_img").hide();
    $("#note_location_msg").removeClass("img-visible");
  }

  var map_template_wide = "<?= build_static_map_url('{lat}', '{lng}', 180, 700, 15) ?>";
  var map_template_small = "<?= build_static_map_url('{lat}', '{lng}', 320, 480, 15) ?>";

  function fetch_location() {
    $("#note_location_loading").show();

    navigator.geolocation.getCurrentPosition(function(position){

      load_entry_buttons(position.coords);

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

      // Load the entry buttons with no location context
      load_entry_buttons();
    });
  }

  function set_location_enabled(enabled) {
    localforage.setItem('location-enabled', {enabled: enabled});
  }
  function get_location_enabled(callback) {
    localforage.getItem('location-enabled', function(err,val){
      if(val) {
        callback(val.enabled);
      } else {
        callback(false);
      }
    });
  }

  $("#note_location_chk").click(function(){
    if($(this).attr("checked") == "checked") {
      if(navigator.geolocation) {
        set_location_enabled(true);
        fetch_location(); // will load the entry buttons even if location fails
      } else {
        set_location_enabled(false);
        location_error("Browser location is not supported");
      }
    } else {
      $("#note_location_img").hide();
      $("#note_location_msg").removeClass("img-visible");
      $("#note_location_msg").val('');
      $("#note_location").val('');

      set_location_enabled(false);

      // Load the buttons now
      // This is for when the browser is taking too long to find location,
      // the user might un-check the box
      load_entry_buttons();
    }
  });

  // This loads the buttons with or without location
  function load_entry_buttons(coords) {
    var latitude = coords ? coords.latitude : '';
    var longitude = coords ? coords.longitude : '';

    $.getJSON('/options.json', {
      latitude: latitude,
      longitude: longitude
    }, function(response) {
      $("#entry-buttons").html(response.buttons);
      bind_keyboard_shortcuts();
    });
  }

  ///////////////////////////////////////////////////////////////
  // App Start

  // Set the date from JS
  var d = new Date();
  $("#note_date").val(d.getFullYear()+"-"+zero_pad(d.getMonth()+1)+"-"+zero_pad(d.getDate()));
  $("#note_time").val(zero_pad(d.getHours())+":"+zero_pad(d.getMinutes())+":"+zero_pad(d.getSeconds()));
  $("#note_tzoffset").val(tz_seconds_to_offset(d.getTimezoneOffset() * 60 * -1));

  // Check if location is enabled in the localstorage prefs
  get_location_enabled(function(enabled){
    if(enabled) {
      // If location is enabled, fetch location and load the entry buttons
      fetch_location(); // will load the buttons even if location fails
      $("#note_location_chk").attr("checked","checked");

    } else {
      // If location is not enabled, fetch prefs immediately
      $("#note_location_chk").removeAttr("checked");
      load_entry_buttons();

    }
  });

  function onUpdateReady() {
    // Show the notice that says there is a new version of the app
    $("#new_version_available").show();
  }

  window.applicationCache.addEventListener('updateready', onUpdateReady);
  if(window.applicationCache.status === window.applicationCache.UPDATEREADY) {
    onUpdateReady();
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
