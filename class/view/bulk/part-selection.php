<?php
namespace SPAATG;

use SPAATG\Controller\Optimizer\OptimizeAiController;
use SPAATG\Helper\UiHelper;

if ( ! defined( 'ABSPATH' ) ) {
 exit; // Exit if accessed directly.
}

$approx = $this->view->approx;
?>


<section class='panel selection' data-panel="selection" data-status="loaded" >
  <div class="panel-container">
			<span class='hidden' data-check-custom-hascustom >
				<?php echo  ($this->view->approx->custom->has_custom === true) ? 1 : 0;  ?>
			</span>

	 <?php $this->loadView('bulk/part-progressbar', false,  ['part' => 'selection']); ?>

      <div class='load wrapper' >
         <div class='loading'>
             <span><img src="<?php echo esc_url(\wpSPAATG()->plugin_url('res/img/bulk/loading-hourglass.svg')); ?>" /></span>
             <span>
             <p><?php esc_html_e('Please wait, ShortPixel is checking the images to be processed...','shortpixel-image-optimiser'); ?><br>
               <span class="number" data-stats-total="total">x</span> <?php esc_html_e('items found', 'shortpixel-image-optimiser'); ?></p>
           </span>
         </div>


				 <div class='loading skip'>
					<nav>
					 <span><p><button class='button' data-action="SkipPreparing"><?php _e('Start now', 'shortpixel-image-optimiser'); ?></button></p>

					 </span>
					 <span>
	 						 <p><?php _e("Clicking this button will start optimization of the items added to the queue. The remaining items can be processed in a new bulk. After completion, you can start bulk and the system will continue with the unprocessed images.",'shortpixel-image-optimiser'); ?></p>
						</span>
					</nav>
				</div>

        <div class='loading overlimit'>
              <p><?php _e('ShortPixel has detected that there are no more resources available during preparation. The plugin will try to complete the process, but may be slower. Increase memory, disable heavy plugins or reduce the number of prepared items per load.', 'shortpixel-image-optimiser'); ?></p>

        </div>
      </div>

       <div class="interface wrapper">

	   <h3 class="heading">
        <?php esc_html_e('ShortPixel Bulk Alt Text Generation - Select Images', 'shortpixel-image-optimiser'); ?>
      </h3>

      <p class='description'><?php esc_html_e('Select the type of images for which ShortPixel should generate alt text and SEO data.','shortpixel-image-optimiser'); ?></p>
				 <div class="option-block">

					<!-- <h2><?php esc_html_e('Optimize:','shortpixel-image-optimiser'); ?> </h2> -->
				<!--	 <p><?php printf(esc_html__('ShortPixel has %sestimated%s the number of images that can still be optimized. %sAfter you select the options, the plugin will calculate exactly how many images to optimize.','shortpixel-image-optimiser'), '<b>','</b>', '<br />'); ?></p>

					 <?php if ($approx->media->isLimited): ?>
						 <h4 class='count_limited'><?php esc_html_e('ShortPixel has detected a high number of images. This estimates are limited for performance reasons. On the next step an accurate count will be produced', 'shortpixel-image-optimiser'); ?></h4>
					 <?php endif; ?>
				 -->

		         <div class="media-library optiongroup hidden">
		            <input type="checkbox" class="switch" id="media_checkbox" checked>
		            <span class="hidden" data-check-approx-total><?php echo esc_html($approx->total->images) ?></span>
		         </div>


					<?php if (! \wpSPAATG()->settings()->processThumbnails): ?>
					<div class='thumbnails optiongroup'>
						<div class='switch_button'>
							<label>
								<input type="checkbox" class="switch" id="thumbnails_checkbox" <?php checked(\wpSPAATG()->settings()->processThumbnails); ?>>
								<div class="the_switch">&nbsp; </div>
							</label>
						</div>
						<h4><label for="thumbnails_checkbox"><?php esc_html_e('Process Image Thumbnails','shortpixel-image-optimiser'); ?></label></h4>
						<div class='option'>
							<label><?php esc_html_e('Thumbnails (estimate)','shortpixel-image-optimiser'); ?></label>
							 <span class="number" ><?php echo esc_html($approx->media->total) ?> </span>
						</div>

						<p><?php esc_html_e('It is recommended to process the WordPress thumbnails. These are the small images that are most often used in posts and pages. This option changes the global ShortPixel AI Alt Text Generator settings of your site.','shortpixel-image-optimiser'); ?></p>

					</div>
				<?php endif; ?>

				<?php
				$optimizeAiController = OptimizeAiController::getInstance(); 
				if (true === $optimizeAiController->isAiEnabled()):  ?>
			 <div class='ai-images optiongroup'>
				<div class='switch_button'>
				<label>
		               <input type="checkbox" class="switch" id="autoai_checkbox" name="autoai_checkbox"
		                <?php checked(\wpSPAATG()->settings()->autoAIBulk); ?>  />
		               <div class="the_switch">&nbsp; </div>
	             </label>
				 <h4><label for="autoai_checkbox">
					<?php printf(esc_html__('Use ShortPixel AI to generate image SEO data for all Media Library images, according to the %ssettings%s', 'shortpixel-image-optimiser'), '<a href="options-general.php?page=wp-spaatg-settings&part=ai">', '</a>' ); ?>
				 </label></h4>

				</div>	

				<div class='switch_button indent'>
				<label>
		               <input type="checkbox" class="switch" id="aipreserve_checkbox" name="aipreserve_checkbox"
		                <?php checked(\wpSPAATG()->settings()->aiPreserve); ?>  />
		               <div class="the_switch">&nbsp; </div>
	             </label>
				 <h4><label for="aipreserve_checkbox">
					<?php printf(esc_html__('Prevent overriding any of the existing data with the one generated by AI', 'shortpixel-image-optimiser'), '<a href="options-general.php?page=wp-spaatg-settings&part=ai">', '</a>' ); ?>
				 </label></h4>

				</div>	

			 </div>

			<?php endif ?>
			
	         <div class="custom-images optiongroup hidden"  data-check-visibility data-control="data-check-custom-hascustom" >
	           <div class='switch_button'>
	             <label>
	               <input type="checkbox" class="switch" id="custom_checkbox" disabled>
	               <div class="the_switch">&nbsp; </div>
	             </label>
	           </div>
	           <h4><label for="custom_checkbox"><?php esc_html_e('Custom Media images','shortpixel-image-optimiser') ?></label></h4>
	            <div class='option'>
	              <label><?php esc_html_e('Images (estimate)','shortpixel-image-optimiser'); ?></label>
	               <span class="number" ><?php echo esc_html($approx->custom->images) ?></span>
	            </div>
	         </div>

<!--
			<div class='maximum-items'> 
			<div class='switch_button'>
			<br>
				<div class='switch_button'>
	             <label>
	               <input type="checkbox" class="switch" id="limit_items" name='limit_items' >
	               <div class="the_switch">&nbsp; </div>
				   <?php printf(esc_html__('Limit Items to %s and then start', 'shortpixel-image-optimiser'), 
				'<input type="text" name="limit_numitems" value="1000">'); ?>
				</div>	
				</label>
	           </div>

			</div>
			-->			

				</div> <!-- // option top block -->

 	 	 <div class="option-block all-round">
       <div class='optiongroup' data-check-visibility="false" data-control="data-check-approx-total">
          <h3><?php esc_html_e('No images found', 'shortpixel-image-optimiser'); ?></h3>
          <p><?php esc_html_e('ShortPixel Bulk couldn\'t find any images that need alt text generation.','shortpixel-image-optimiser'); ?></p>
       </div>

		 </div>

      <nav>
        <button class="button" type="button" data-action="FinishBulk">
					<span class='dashicons dashicons-arrow-left'></span>
					<p><?php esc_html_e('Back', 'shortpixel-image-optimiser'); ?></p>
				</button>

        <button class="button-primary button" type="button" data-action="CreateBulk" data-panel="summary" data-check-disable data-control="data-check-total-total">
					<span class='dashicons dashicons-arrow-right'></span>
					<p><?php esc_html_e('Calculate', 'shortpixel-image-optimiser'); ?></p>
				</button>
      </nav>

    </div> <!-- interface wrapper -->
  </div><!-- container -->
</section>
