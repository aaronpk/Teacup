<div class="narrow">
  <?= partial('partials/header') ?>

  <h2>Sign in with your Domain</h2>

  <p>Enter your website below to sign in.</p>
  <p>If your website supports Micropub, it will log posts directly to your site. Otherwise, it will post to your profile on this website.</p>

  <form action="/auth/start" method="get" class="form-inline">
    <input type="url" name="me" placeholder="http://me.com" value="" class="form-control">
    <input type="hidden" name="redirect" value="settings">
    <input type="submit" value="Sign In" class="btn btn-primary">
  </form>  

</div>