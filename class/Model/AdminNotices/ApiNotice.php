<?php
namespace SPAATG\Model\AdminNotices;

if ( ! defined( 'ABSPATH' ) ) {
 exit; // Exit if accessed directly.
}

use SPAATG\ShortPixelLogger\ShortPixelLogger as Log;
use SPAATG\Controller\ApiKeyController as ApiKeyController;


class ApiNotice extends \SPAATG\Model\AdminNoticeModel
{
	protected $key = 'MSG_NO_APIKEY';

  protected $exclude_screens = ['settings_page_wp-spaatg-settings'];

	public function load()
	{
		$activationDate = \wpSPAATG()->settings()->activationDate;
		if (! $activationDate)
		{
			 $activationDate = time();
			 \wpSPAATG()->settings()->activationDate = $activationDate;
		}

		parent::load();
	}

	protected function checkTrigger()
	{
			$keyControl = ApiKeyController::getInstance();
			if ($keyControl->keyIsVerified())
			{
				return false;
			}

			// If not key is verified.
			return true;
	}

  protected function checkReset()
  {

		$keyControl = ApiKeyController::getInstance();
		if ($keyControl->keyIsVerified())
		{
      return true;
    }
    return false;
  }

	protected function getMessage()
	{
		$message = "<p>" . __('To start generating AI image SEO data, you need to validate your API key on the '
						. '<a href="options-general.php?page=wp-spaatg-settings">ShortPixel AI Alt Text Generator</a> page in your WordPress admin.','shortpixel-image-optimiser') . "
		</p>
		<p>" .  __('If you do not have an API key yet, just fill out the form and a key will be created.','shortpixel-image-optimiser') . "</p>";

		return $message;
	}
}
