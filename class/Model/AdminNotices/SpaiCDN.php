<?php
namespace SPAATG\Model\AdminNotices;

if ( ! defined( 'ABSPATH' ) ) {
 exit; // Exit if accessed directly.
}

class SpaiCDN extends \SPAATG\Model\AdminNoticeModel
{

	protected $key = 'MSG_SPAICDN';
  protected $errorLevel = 'error';

	protected function checkTrigger()
	{
		  if (\wpSPAATG()->env()->plugin_active('spai') && \wpSPAATG()->settings()->useCDN == true)
      {
          return true;
      }
      return false;
	}

  protected function checkReset()
  {
    if (\wpSPAATG()->env()->plugin_active('spai') && \wpSPAATG()->settings()->useCDN == true)
    {
        return false;
    }

     return true;
  }

// @todo This message is not properly stringF'ed.
	protected function getMessage()
	{
		$settings = \wpSPAATG()->settings();

		//$unlisted = isset($settings->currentStats['foundUnlistedThumbs']) ? $settings->currentStats['foundUnlistedThumbs'] : null;
		$unlisted_id = $this->getData('id');
		$unlisted_name = $this->getData('name');
		$unlistedFiles = (is_array($this->getData('filelist'))) ? $this->getData('filelist') : array();

		$admin_url = esc_url(admin_url('options-general.php?page=wp-spaatg-settings&part=webp'));


		$message = __("Please deactivate the ShortPixel Adaptive Images plugin if CDN delivery is enabled in ShortPixel Image Optimization. If both are activated, this can lead to over-optimization and errors on your website.", 'shortpixel-image-optimiser');


    $action = 'Deactivate';
    $path = 'shortpixel-adaptive-images/short-pixel-ai.php';
    $link = wp_nonce_url( admin_url( 'admin-post.php?action=spaatg_deactivate_conflict_plugin&plugin=' . urlencode( $path ) ), 'sp_deactivate_plugin_nonce' );

    $message .= sprintf('<p><a class="button button-primary" href="%s">%s</a></p>', $link, __('Deactivate ShortPixel Adaptive Images'));

		return $message;

	}
}
