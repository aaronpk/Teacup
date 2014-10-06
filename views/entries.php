<div class="narrow">
  <?= partial('partials/header') ?>
  <br>
  
  <ul class="entries">
    <?php foreach($this->entries as $entry): ?>
      <?= partial('partials/entry', array('entry' => $entry, 'user' => $this->user)) ?>
    <?php endforeach; ?>
  </ul>

  <nav class="site-navigation">
    <? if($this->older) { ?>
      <a class="prev" href="/<?= $this->user->url ?>?before=<?= $this->older ?>" rel="prev"><abbr>&larr;</abbr> <span>Older</span></a>
    <? } else { ?>
      <a class="prev disabled"><abbr>&larr;</abbr> <span>Older</span></a>
    <? } ?>
    <? if($this->newer) { ?>
      <a class="next" href="/<?= $this->user->url ?>?before=<?= $this->newer ?>" rel="next"><abbr>&rarr;</abbr> <span>Newer</span></a>
    <? } else { ?>
      <a class="next disabled"><abbr>&rarr;</abbr> <span>Newer</span></a>
    <? } ?>
  </nav>
</div>