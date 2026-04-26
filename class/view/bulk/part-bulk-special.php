<?php
namespace SPAATG;

if ( ! defined( 'ABSPATH' ) ) {
 exit; // Exit if accessed directly.
}

?>


<section class='panel bulk-restoreAI' data-panel="bulk-restoreAI"  >
  <h3 class='heading'>
    <?php esc_html_e("Bulk Revert AI SEO", 'shortpixel-image-optimiser'); ?>
  </h3>


	<div class='bulk-special-wrapper'>

	  <h4 class='warning'><?php esc_html_e('Warning', 'shortpixel-image-optimiser'); ?></h4>

	  <p><?php printf(esc_html__('By starting the %s Bulk Revert AI SEO %s process, the plugin will try to revert %s all AI-generated SEO fields %s to their previous values. This affects image titles, alt text, captions and descriptions where previous values are available.', 'shortpixel-image-optimiser'), '<b>', '</b>', '<b>', '</b>'); ?></p>

		<p class='warning'><?php esc_html_e('It is strongly advised to create a full backup before starting this process.', 'shortpixel-image-optimiser'); ?></p>


	  <p class='optiongroup' ><input type="checkbox" id="bulk-restoreAI-agree" value="agree" data-action="ToggleButton" data-target="bulk-restoreAI-button"> <?php esc_html_e('I want to revert all AI-generated SEO data where previous values are available. I understand this action is permanent.', 'shortpixel-image-optimiser'); ?></p>

	  <nav>
    	<button type="button" class="button" data-action="open-panel" data-panel="dashboard"><?php esc_html_e('Back','shortpixel-image-optimiser'); ?></button>

			<button type="button" class="button button-primary disabled" id='bulk-restoreAI-button' data-action="BulkUndoAI" disabled><?php esc_html_e('Bulk Revert AI SEO Data', 'shortpixel-image-optimiser') ?></button>
	  </nav>

</div>
</section>
