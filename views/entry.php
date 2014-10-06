<div class="narrow">
  <?= partial('partials/header') ?>
  <br>

  <ul class="entries">
    <?= partial('partials/entry', array('entry' => $this->entry, 'user' => $this->user)) ?>
  </ul>
</div>