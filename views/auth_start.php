<div class="narrow">
  <?= partial('partials/header') ?>

  <?php if($this->micropubUser): ?>

    <h3>Awesome! We found your Micropub endpoint!</h3>

    <div class="alert alert-success">
      Click below to authorize this app to create posts at your Micropub endpoint.
    </div>

  <?php else: ?>

    <h3>Would you like to use a hosted account?</h3>

    <div class="alert alert-warning">
      It looks like your site doesn't support <a href="http://micropub.net">Micropub</a>. 
      You can still use this site to track what you drink, and the posts will live here instead of on your own site.
    </div>

  <?php endif; ?>

  <a href="<?= $this->authorizationURL ?>" class="btn btn-primary"><?= $this->micropubUser ? 'Authorize' : 'Sign In' ?></a>


  <div class="bs-callout bs-callout-<?= $this->micropubUser ? 'success' : 'warning' ?>">
    Your authorization endpoint: <code><?= $this->authorizationEndpoint ?: 'none' ?></code><br>
    Your token endpoint: <code><?= $this->tokenEndpoint ?: 'none' ?></code><br>
    Your Micropub endpoint: <code><?= $this->micropubEndpoint ?: 'none' ?></code>
  </div>

</div>