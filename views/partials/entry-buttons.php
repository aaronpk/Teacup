<?php
  foreach($this->options['sections'] as $section) {
    echo '<div class="entry-section" style="margin-bottom: 20px;">';
      echo '<h3>' . $section['title'] . '</h3>';
      echo '<ul class="entry-buttons">';
        foreach($section['items'] as $item) {
          echo '<li><input type="submit" name="' . $item['type'] . '" class="btn btn-default" value="' . $item['title'] . '"></li>';
        }
        $type = null;

        if($section['title'] == 'Drinks') {
          $type = 'drink';
          $noun = 'Drink';
        }
        if($section['title'] == 'Food') {
          $type = 'eat';
          $noun = 'Food';
        }
        if($type == 'drink' || $type == 'eat') {
          echo '<li>';
            echo '<input type="text" class="form-control text-custom-'.$type.'" name="custom_'.$type.'" placeholder="Custom '.$noun.'" style="width: 72%; float: left; margin-right: 2px;">';
            echo '<input type="submit" class="btn btn-default btn-custom-'.$type.'" value="Post" style="width: 26%; float: right;">';
            echo '<div style="clear:both;"></div>';
          echo '</li>';
        }
      echo '</ul>';
    echo '</div>';
  }
