<?php

namespace SPAATG;

if (! defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

?>
<section id="tab-preview" class="<?php echo ($this->display_part == 'preview') ? 'active setting-tab' : 'setting-tab'; ?>" data-part="preview">
  <settinglist class='preview_wrapper'>
    <input type="hidden" name="ai_preview_image_id" value="" />

    <div class='ai_preview'>
      <gridbox class='width_half'>
        <span><img src="<?php echo esc_url(plugins_url('res/img/bulk/placeholder.svg', SPAATG_PLUGIN_FILE)); ?>" class='image_preview'></span>
        <span>
          <h2><i class='shortpixel-icon ai'></i><?php _e('AI Image SEO Preview','shortpixel-image-optimiser'); ?></h2>
          <p><?php _e('Preview only: the current image data will not be touched!', 'shortpixel-image-optimiser'); ?></p>
          <p>
            <button type='button' name='open_change_photo'>
              <i class='shortpixel-icon optimization'></i><?php _e('Select test image', 'shortpixel-image-optimiser'); ?></button>
            <button type='button' name='refresh_ai_preview'>
              <i class='shortpixel-icon refresh'></i><?php _e('Generate AI SEO data preview', 'shortpixel-image-optimiser'); ?></button>
          </p>
          <div class='preview_result'></div>
        </span>
      </gridbox>
    </div>

    <hr>

    <gridbox class='width_two_with_middle result_wrapper'>
      <div class='current result_info'>
        <h3><?php _e('Current SEO Data', 'shortpixel-image-optimiser'); ?></h3>
        <ul>
          <li><label><?php _e('Image ALT tag', 'shortpixel-image-optimiser'); ?>:</label> <span class='alt'></span></li>
          <li><label><?php _e('Image caption', 'shortpixel-image-optimiser'); ?>:</label> <span class='caption'></span></li>
          <li><label><?php _e('Image description', 'shortpixel-image-optimiser'); ?>:</label> <span class='description'></span></li>
          <li><label><?php _e('Image Title', 'shortpixel-image-optimiser'); ?>:</label> <span class='post_title'></span></li>
        </ul>
      </div>
      <div class='icon'><i class='shortpixel-icon chevron rotate_right'></i>&nbsp;</div>
      <div class='result result_info'>
        <h3><?php _e('Generated AI Image SEO data', 'shortpixel-image-optimiser'); ?></h3>
        <ul>
          <li><label><?php _e('Image ALT tag', 'shortpixel-image-optimiser'); ?>:</label> <span class='alt'></span></li>
          <li><label><?php _e('Image caption', 'shortpixel-image-optimiser'); ?>:</label> <span class='caption'></span></li>
          <li><label><?php _e('Image description', 'shortpixel-image-optimiser'); ?>:</label> <span class='description'></span></li>
          <li><label><?php _e('Image Title', 'shortpixel-image-optimiser'); ?>:</label> <span class='post_title'></span></li>
        </ul>
      </div>
    </gridbox>
  </settinglist>
</section>
