  <div class="narrow">
    <?= partial('partials/header') ?>

      <form role="form" style="margin-top: 20px;" id="note_form" action="/post" method="post">

        <h3>Caffeine</h3>
        <ul class="caffeine">
          <?php foreach(caffeine_options() as $val): ?>
            <li><input type="submit" name="drank" class="btn btn-default" value="<?= $val ?>"></li>
          <?php endforeach; ?>
          <li>
            <input type="text" class="form-control" name="custom_caffeine" placeholder="Custom" style="width: 72%; float: left; margin-right: 2px;">
            <input type="submit" class="btn btn-default" value="Post" style="width: 26%; float: right;">
          </li>
        </ul>
        <br>

        <h3>Alcohol</h3>
        <ul class="alcohol">
          <?php foreach(alcohol_options() as $val): ?>
            <li><input type="submit" name="drank" class="btn btn-default" value="<?= $val ?>"></li>
          <?php endforeach; ?>
          <li>
            <input type="text" class="form-control" name="custom_alcohol" placeholder="Custom" style="width: 72%; float: left; margin-right: 2px;">
            <input type="submit" class="btn btn-default" value="Post" style="width: 26%; float: right;">
          </li>
        </ul>
        <br><br>

        <div class="form-group">
          <label for="note_location">Location</label>
          <input type="checkbox" id="note_location_chk" value="">
          <img src="/images/spinner.gif" id="note_location_loading" style="display: none;">

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

        <?php if($this->test_response): ?>
          <h4>Last response from your Micropub endpoint <span id="last_response_date">(<?= relative_time($this->response_date) ?>)</span></h4>
        <?php endif; ?>
        <pre id="test_response" style="width: 100%; min-height: 240px;"><?= htmlspecialchars($this->test_response) ?></pre>

        <div class="callout">
          <p>Clicking "Post" will post this note to your Micropub endpoint. Below is some information about the request that will be made.</p>

          <table class="table table-condensed">
            <tr>
              <td>me</td>
              <td><code><?= session('me') ?></code> (should be your URL)</td>
            </tr>
            <tr>
              <td>scope</td>
              <td><code><?= $this->token_scope ?></code> (should be a space-separated list of permissions including "post")</td>
            </tr>
            <tr>
              <td>micropub endpoint</td>
              <td><code><?= $this->micropub_endpoint ?></code> (should be a URL)</td>
            </tr>
            <tr>
              <td>access token</td>
              <td>String of length <b><?= strlen($this->access_token) ?></b><?= (strlen($this->access_token) > 0) ? (', ending in <code>' . substr($this->access_token, -7) . '</code>') : '' ?> (should be greater than length 0)</td>
            </tr>
          </table>
        </div>

      <?php endif; ?>

  </div>

<script>
$(function(){

  // ctrl-s to save
  $(window).on('keydown', function(e){ 
    if(e.keyCode == 83 && e.ctrlKey){
      $("#btn_post").click();
    } 
  });

  $("#btn_post").click(function(){

    var syndications = [];
    $("#syndication-container button.btn-info").each(function(i,btn){
      syndications.push($(btn).data('syndication'));
    });

    $.post("/micropub/post", {
      content: $("#note_content").val(),
      'in-reply-to': $("#note_in_reply_to").val(),
      location: $("#note_location").val(),
      category: $("#note_category").val(),
      slug: $("#note_slug").val(),
      'syndicate-to': syndications.join(',')
    }, function(data){
      var response = JSON.parse(data);

      if(response.location != false) {
        $("#note_form").slideUp(200, function(){
          $(window).scrollTop($("#test_success").position().top);
        });

        $("#test_success").removeClass('hidden');
        $("#test_error").addClass('hidden');
        $("#post_href").attr("href", response.location);

        $("#note_content").val("");
        $("#note_in_reply_to").val("");
        $("#note_category").val("");
        $("#note_slug").val("");

      } else {
        $("#test_success").addClass('hidden');
        $("#test_error").removeClass('hidden');
      }

      $("#last_response_date").html("(just now)");
      $("#test_request").html(response.request);
      $("#last_request_container").show();
      $("#test_response").html(response.response);
    });
    return false;
  });

  function location_error(msg) {
    $("#note_location_msg").val(msg);
    $("#note_location_chk").removeAttr("checked");
    $("#note_location_loading").hide();
    $("#note_location_img").hide();
    $("#note_location_msg").removeClass("img-visible");
  }

  var map_template_wide = "<?= static_map('{lat}', '{lng}', 180, 700, 15) ?>";
  var map_template_small = "<?= static_map('{lat}', '{lng}', 320, 480, 15) ?>";

  function fetch_location() {
    $("#note_location_loading").show();

    navigator.geolocation.getCurrentPosition(function(position){

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


