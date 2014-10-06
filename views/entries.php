<div class="narrow">
  <?= partial('partials/header') ?>
  <br>
  
  <ul class="entries">
    <?php foreach($this->entries as $entry): ?>
      <?= partial('partials/entry', array('entry' => $entry, 'user' => $this->user)) ?>
    <?php endforeach; ?>
  </ul>
</div>