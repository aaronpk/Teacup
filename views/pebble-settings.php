<h2>Finished!</h2>

<script>
var options = {
  token: '<?= $this->token ?>'
};
location.href = 'pebblejs://close#' + encodeURIComponent(JSON.stringify(options));
</script>