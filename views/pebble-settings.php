<h2>Finished!</h2>

<script>
var options = {
  token: '<?= $this->token ?>'
};
document.location = 'pebblejs://close#' + encodeURIComponent(JSON.stringify(options));
</script>