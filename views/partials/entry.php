<li class="h-entry">

  <?php if($this->entry->latitude): ?>
    <img src="<?= build_static_map_url($this->entry->latitude, $this->entry->longitude, 174,300,14) ?>" class="map-img">
  <?php endif; ?>

  <div class="author h-card p-author">
    <a class="photo" href="http://<?= $this->user->url ?>"><img src="<?= $this->user->photo_url ?>" class="u-photo" height="50" width="50" alt="<?= $this->user->url ?>"></a> 
    <a class="name p-name" href="http://<?= $this->user->url ?>"><?= $this->user->name ?></a>
    <a class="url u-url" href="http://<?= $this->user->url ?>"><?= $this->user->url ?></a>
    <div style="clear:both;"></div>
  </div>

  <div class="content e-content p-name"><?= $this->entry->content ?></div>

  <?php if($this->entry->latitude): ?>
    <div class="location">
      <div class="p-location h-geo">
        <i class="fa fa-map-marker"></i> 
        <data class="p-latitude" value="<?= $this->entry->latitude ?>"><?= $this->entry->latitude ?></data>, <data class="p-longitude" value="<?= $this->entry->longitude ?>"><?= $this->entry->longitude ?></data>
      </div>
    </div>
  <?php endif; ?>

  <div class="date">
    <a href="<?= entry_url($this->entry, $this->user) ?>" class="u-url"><time class="dt-published" datetime="<?= entry_date($this->entry, $this->user)->format('c') ?>"><?= entry_date($this->entry, $this->user)->format('F j, Y g:ia P') ?></time></a>
  </div>
</li>

