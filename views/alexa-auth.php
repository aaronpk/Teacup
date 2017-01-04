<div class="narrow">
  <?= partial('partials/header') ?>

  <h2>Sign in to Teacup</h2>

  <p>Go to teacup.p3k.io on your phone or computer and sign in.</p>
  <p>Then go to the settings page to get a "device code", and enter that code here.</p>

  <form action="/alexa/login" method="post" class="form-inline">
    <div class="form-group">
      <input type="number" name="code" value="" class="form-control">
    </div>
    <input type="submit" value="Sign In" class="btn btn-primary">
    <input type="hidden" name="redirect_uri" value="<?= $this->redirect_uri ?>">
    <input type="hidden" name="client_id" value="<?= $this->client_id ?>">
    <input type="hidden" name="state" value="<?= $this->state ?>">
  </form>  

</div>