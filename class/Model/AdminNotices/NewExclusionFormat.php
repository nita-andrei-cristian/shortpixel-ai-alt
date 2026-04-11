<?php
namespace SPAATG\Model\AdminNotices;

if ( ! defined( 'ABSPATH' ) ) {
 exit; // Exit if accessed directly.
}

class NewExclusionFormat extends \SPAATG\Model\AdminNoticeModel
{

  protected $key = 'MSG_EXCLUSION_WARNING';


	protected function checkTrigger()
	{
      $patterns = \wpSPAATG()->settings()->excludePatterns;

      if (! is_array($patterns))
      {
         return false; 
      }

      foreach($patterns as $index => $pattern)
      {
        if (! isset($pattern['apply']))
        {
           return true;
        }
      }
      return false;
	}

	protected function getMessage()
	{
		$message = "<p>" . __('Since version 5.5.0, ShortPixel AI Alt Text Generator also checks thumbnails for exclusions. This can change which images are optimized and which are excluded. Please check your exclusion rules on the '
						. '<a href="options-general.php?page=wp-spaatg-settings&part=exclusions">ShortPixel AI Alt Text Generator</a> page.','shortpixel-image-optimiser') . "
		</p>";

		return $message;
	}
}
