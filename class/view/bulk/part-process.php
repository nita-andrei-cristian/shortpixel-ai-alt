<?php
namespace SPAATG;

use SPAATG\Helper\UiHelper;

if ( ! defined( 'ABSPATH' ) ) {
 exit; // Exit if accessed directly.
}

$settings = \wpSPAATG()->settings();
?>
<section class="panel process" data-panel="process" >
  <div class="panel-container">

  <?php $this->loadView('bulk/part-progressbar', false, ['part' => 'process']); ?>


    <div class='process_heading'>
    <h3 class="heading">
      <?php esc_html_e('ShortPixel AI SEO bulk process is in progress','shortpixel-image-optimiser'); ?>
    </h3>

    <?php
     if ($settings->doBackgroundProcess): ?>
      <p class="description">

        <?php
        $link = 'https://shortpixel.com/knowledge-base/article/background-processing-using-cron-jobs-in-shortpixel-image-optimizer/';
        printf(esc_html('ShortPixel Bulk AI SEO is processing in the background. You can close this browser window now and reopen it at any time to check the status of the bulk processing. %sLearn more%s','shortpixel-image-optimiser'), '<strong><a href="' . esc_attr($link) . '" target="_blank">','</a></strong>'); ?>
      </p>
    <?php else: ?>
      <p class='description'>
        <?php esc_html_e('ShortPixel is generating AI SEO data for your images. Please keep this window open to complete the process.', 'shortpixel-image-optimiser'); ?>
      </p>
    <?php endif; ?>


    </div>


		<!--- ###### MEDIA ###### -->
		<span class='hidden' data-check-media-total data-stats-media="total">0</span>
    <div class='bulk-summary' data-check-visibility data-control="data-check-media-total">
      <div class='heading'>
        <span><i class='dashicons dashicons-format-image'>&nbsp;</i> <?php esc_html_e('Media Library' ,'shortpixel-image-optimiser'); ?>
              <?php printf(esc_html__('( %s items )', 'shortpixel-image-optimiser'), '<i data-stats-media="total">--</i>'); ?>
        <?php if (false !== $this->view->customOperationMedia) {
            echo "</br><span class='special-op'>" . $this->view->customOperationMedia . "</span>";
         } ?>
        </span>
        <span>
              <span class='line-progressbar'>
                <span class='done-text'><i data-stats-media="percentage_done"></i> %</span>
                <span class='done' data-stats-media="percentage_done" data-presentation="css.width.percentage"></span>

              </span>
							<span class='dashicons spin dashicons-update line-progressbar-spinner' data-check-visibility data-control="data-check-media-in_process">&nbsp;</span>

        </span>
        <span><?php esc_html_e('Processing', 'shortpixel-image-optimiser') ?>: <i data-stats-media="in_process" data-check-media-in_process >0</i></span>
      </div>

      <div>
        <span><?php esc_html_e('Processed', 'shortpixel-image-optimiser'); ?>: <i data-stats-media="done">0</i></span>

        <span><?php esc_html_e('Waiting','shortpixel-image-optimiser'); ?>: <i data-stats-media="in_queue">0</i></span>
        <span><?php esc_html_e('Errors','shortpixel-image-optimiser') ?>: <i data-check-media-fatalerrors data-stats-media="fatal_errors" class='error'>0 </i>
					<span class="display-error-box" data-check-visibility data-control="data-check-media-fatalerrors" ><label title="<?php esc_html_e('Show Errors', 'shortpixel-image-optimiser'); ?>">
						<input type="checkbox" name="show-errors" value="show" data-action='ToggleErrorBox' data-errorbox='media' data-event='change'>
						<span><?php esc_html_e('Show Errors','shortpixel-image-optimiser'); ?></span>
            <span class='collap-arrow'><?php echo UIHelper::getIcon('res/images/icon/chevron.svg'); ?></span>

            </label>
				 </span>

				</span>


      </div>

    </div>

		<div data-error-media="message" data-presentation="append" class='errorbox media'>
				<?php if(property_exists($this->view, 'mediaErrorLog') && $this->view->mediaErrorLog !== false)
				{
					echo $this->view->mediaErrorLog;
				}
				?>
		</div>

		<nav>
			<button class='button stop' type='button' data-action="StopBulk" >
          <span class='dashicons dashicons-no'></span>  
					<?php esc_html_e('Stop Bulk Processing' ,'shortpixel-image-optimiser'); ?>
			</button>
			<button class='button pause' type='button' data-action="PauseBulk" id="PauseBulkButton">
        <span class='dashicons dashicons-controls-pause'></span>  
				<?php esc_html_e('Pause Bulk Processing' ,'shortpixel-image-optimiser') ?>
			</button>
			<button class='button button-primary resume' type='button' data-action='ResumeBulk' id="ResumeBulkButton">
         <span class='dashicons dashicons-controls-play'></span>  
				<?php esc_html_e('Resume Bulk Processing','shortpixel-image-optimiser'); ?>
			</button>

		</nav>

		<div id="preloader" class="hidden">

  	</div>

</section>
