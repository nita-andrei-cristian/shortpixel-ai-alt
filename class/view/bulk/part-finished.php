<?php
namespace SPAATG;
use SPAATG\Helper\UiHelper as UiHelper;

if ( ! defined( 'ABSPATH' ) ) {
 exit; // Exit if accessed directly.
}
?>
<section class="panel finished" data-panel="finished">
  <div class="panel-container">
  
  <?php $this->loadView('bulk/part-progressbar', false, ['part' => 'finished']); ?>

    <h3 class="heading">
       <?php esc_html_e('The ShortPixel AI SEO bulk process is finished' ,'shortpixel-image-optimiser'); ?>
    </h3>
		<span class='hidden' data-check-media-total data-stats-media="total">0</span>

    <span class='hidden' data-check-total-customOperation data-stats-total="isCustomOperation">-1</span>

		<div class='bulk-summary' data-check-visibility="false" data-control='data-check-total-customOperation'>
		<p class='finished-paragraph'>
			<?php printf(__('ShortPixel has generated AI SEO data for %s %s images %s on your website.', 'shortpixel-image-optimiser'), '<b>', '<span data-stats-total="total"></span>','</b>');
			?>
		</p>
	</div>
  <div class='bulk-summary' data-check-visibility="true" data-control='data-check-total-customOperation'>
		<p class='finished-paragraph'>
			<?php printf(__('ShortPixel has completed the %s task', 'shortpixel-image-optimiser'), '<span data-stats-total="customOperation">&nbsp;</span>');
			?>
		</p>
	</div>

    <div class='bulk-summary' data-check-visibility data-control="data-check-media-total">
      <div class='heading'>
        <span><i class='dashicons dashicons-images-alt2'>&nbsp;</i> <?php esc_html_e('Media Library','shortpixel-image-optimiser'); ?>
          <?php if (false !== $this->view->customOperationMedia) {
             echo "</br><span class='special-op'>" . $this->view->customOperationMedia . "</span>";
          } ?>
        </span>

        <span>
              <span class='line-progressbar'>
                <span class='done-text'><i data-stats-media="percentage_done"></i> %</span>
                <span class='done' data-stats-media="percentage_done" data-presentation="css.width.percentage"></span>
              </span>
        </span>
        <span><?php esc_html_e('Processing','shortpixel-image-optimiser') ?>: <i data-stats-media="in_process">0</i></span>

      </div>

      <div>
        <span><?php esc_html_e('Processed','shortpixel-image-optimiser'); ?>: <i data-stats-media="done">0</i></span>

        <span><?php esc_html_e('Images Left','shortpixel-image-optimiser'); ?>: <i data-stats-media="in_queue">0</i></span>
        <span><?php esc_html_e('Errors','shortpixel-image-optimiser'); ?>: <i data-check-media-fatalerrors data-stats-media="fatal_errors" class='error'>0 </i>
					<span class="display-error-box" data-check-visibility data-control="data-check-media-fatalerrors" ><label title="<?php esc_html_e('Show Errors', 'shortpixel-image-optimiser'); ?>">
						<input type="checkbox" name="show-errors" value="show" data-action='ToggleErrorBox' data-errorbox='media' data-event='change'><?php esc_html_e('Show Errors','shortpixel-image-optimiser'); ?>
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
      <button class='button finish' type="button" data-action="FinishBulk" id="FinishBulkButton"><?php esc_html_e('Finish Bulk Process','shortpixel-image-optimiser'); ?></button>
    </nav>




  </div>
</section>
