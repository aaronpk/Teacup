  <div class="footer">
    <div class="nav">
      <ul class="nav navbar-nav">

        <? if(session('me')) { ?>
          <li><a href="/new">New Post</a></li>
        <? } ?>

        <li><a href="/docs">Docs</a></li>
      </ul>
      <ul class="nav navbar-nav navbar-right">
        <? if(session('me')) { ?>
          <li><a href="/add-to-home?start">Add to Home Screen</a></li>
          <li><a href="/settings"><?= preg_replace(['/https?:\/\//','/\/$/'],'',session('me')) ?></a></li>
          <li><a href="/signout">Sign Out</a></li>
        <? } else if(property_exists($this, 'authorizing')) { ?>
          <li class="navbar-text"><?= htmlspecialchars($this->authorizing) ?></li>
        <? } else { ?>
          <form action="/auth/start" method="get" class="navbar-form">
            <input type="text" name="me" placeholder="yourdomain.com" class="form-control" />
            <button type="submit" class="btn">Sign In</button>
          </form>
        <? } ?>

      </ul>
    </div>

    <p class="credits">&copy; <?=date('Y')?> by <a href="https://aaronparecki.com">Aaron Parecki</a>.
      This code is <a href="https://github.com/aaronpk/Teacup">open source</a>. 
      Feel free to send a pull request, or <a href="https://github.com/aaronpk/Teacup/issues">file an issue</a>.</p>
  </div>
