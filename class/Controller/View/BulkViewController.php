<?php
namespace SPAATG\Controller\View;

if ( ! defined( 'ABSPATH' ) ) {
 exit; // Exit if accessed directly.
}

use SPAATG\Controller\AdminNoticesController as AdminNoticesController;
use SPAATG\Controller\ApiKeyController as ApiKeyController;
use SPAATG\Controller\QuotaController as QuotaController;
use SPAATG\Controller\QueueController as QueueController;
use SPAATG\Controller\BulkController as BulkController;
use SPAATG\Helper\UiHelper as UiHelper;

use SPAATG\Model\AiDataModel;


class BulkViewController extends \SPAATG\ViewController
{

  protected $form_action = 'sp-bulk';
  protected $template = 'view-bulk';

  protected $quotaData;

	protected static $instance;


  public function load()
  {
    $quota = QuotaController::getInstance();
    $queueController = new QueueController();
    $bulkController = BulkController::getInstance();

    $this->view->quotaData = $quota->getQuota();

    $this->view->stats = $queueController->getStartupData();
    $this->view->approx = $this->getApproxData();

    $this->view->logHeaders = array(__('Images', 'shortpixel_image_optimiser'), __('Errors', 'shortpixel_image_optimizer'), __('Date', 'shortpixel_image_optimizer'), '');
    $this->view->logs = $this->getLogs();

    $keyControl = ApiKeyController::getInstance();

    $this->view->error = false;

    if ( ! $keyControl->keyIsVerified() )
    {
        $this->view->error = true;
        $this->view->errorTitle = __('Missing API Key', 'shortpixel_image_optimiser');
        $this->view->errorContent = $this->getActivationNotice();
        $this->view->showError = 'key';
    }
    elseif ( ! $quota->hasQuota())
    {
        $this->view->error = true;
        $this->view->errorTitle = __('Quota Exceeded','shortpixel-image-optimiser');
        $this->view->errorContent = __('Can\'t start the Bulk AI SEO process due to lack of credits.', 'shortpixel-image-optimiser');
        $this->view->errorText = __('Please check or add quota and refresh the page', 'shortpixel-image-optimiser');
        $this->view->showError = 'quota';

    }

			$this->view->mediaErrorLog = $this->loadCurrentLog('media');

		$this->view->buyMoreHref = 'https://shortpixel.com/' . ($keyControl->getKeyForDisplay() ? 'login/' . $keyControl->getKeyForDisplay() . '/spio-unlimited' : 'pricing');


    $custom_operation_media = $bulkController->getCustomOperation('media');
    $custom_operation_media = (false === $custom_operation_media) ? $this->checkBulkViaPanelArg() : $custom_operation_media; 

    $this->view->customOperationMedia = (false !== $custom_operation_media) ? $this->getCustomLabel($custom_operation_media) : false;
    

    $noticesController = AdminNoticesController::getInstance(); 

    $this->view->remoteOffer = $noticesController->getRemoteOffer(); 

    $this->loadDashboard();

    $this->loadView();

  }

  private function loadDashboard()
  {
      $noticesController = AdminNoticesController::getInstance();
      $offer = $noticesController->getRemoteOffer(); 

          $this->view->dashboard_icon = plugins_url('res/images/icon/shortpixel.svg', SPAATG_PLUGIN_FILE); 
          $this->view->dashboard_link = false; 
          $this->view->dashboard_title = false; 
          $this->view->dashboard_message = ''; 
      if (is_array($offer))
      {
         $this->view->dashboard_icon = $offer['icon']; 
         $this->view->dashboard_link = $offer['link']; 
         $this->view->dashboard_title = $offer['title'];
         $this->view->dashboard_message = $offer['message'];

      } 
  }

  private function getCustomLabel($operation)
  {
      switch($operation)
      {
          case 'bulk-undoAI':
            $label = __('Bulk Revert AI SEO Data', 'shortpixel-image-optimiser');
          break; 
      }

      return $label;
  }
  
  /** This function has no other purpose than the map the Panel get argument to the proper bulk action. Reason this exists is because at the time the bulk screen is loaded, the bulk hasn't started, thus the specialOPeration is not in place, not showing the text in process / finished
   * @todo Harmonize the panel name, bulk action name etc so this function is not needed to display string
   * @return false|string 
   */
  private function checkBulkViaPanelArg()
  {
      $panel = isset($_GET['panel']) ? sanitize_text_field($_GET['panel']) : null;

      if (is_null($panel))
      {
         return false; 
      }

      $action = false; 

      switch($panel)
      {
         case 'bulk-restoreAI':
            $action = 'bulk-undoAI';
         break; 
      }

      return $action;

  }

	// Double with ApiNotice . @todo Fix.
	protected function getActivationNotice()
	{
				$message = "<p>" . __('In order to start generating AI image SEO data, you need to validate your API Key on the '
							. '<a href="options-general.php?page=wp-spaatg-settings">ShortPixel AI SEO</a> page in your WordPress Admin.','shortpixel-image-optimiser') . "
		</p>
		<p>" .  __('If you don’t have an API Key, just fill out the form and a key will be created.','shortpixel-image-optimiser') . "</p>";
		return $message;
	}

	  protected function getApproxData()
	  {
    $approx = new \stdClass;
    $approx->media = new \stdClass;
    $approx->custom = new \stdClass;
    $approx->total = new \stdClass;

			$pendingAiItems = AiDataModel::countCandidateMediaItems();

	    $approx->custom->images = 0;
			$approx->custom->has_custom = false;
			$approx->media->items = $pendingAiItems;
			$approx->media->thumbs = 0;
			$approx->media->total = $pendingAiItems;
			$approx->media->isLimited = false;

	    $approx->total->images = $pendingAiItems;

		// Prevent any guesses to go below zero.
		foreach($approx->media as $item => $value)
		{
				if (is_numeric($value))
			  	$approx->media->$item = max($value, 0);
		}
		foreach($approx->total as $item => $value)
		{
				if (is_numeric($value))
					$approx->total->$item = max($value, 0);
		}
    return $approx;

  }

	/* Function to check for and load the current Log.  This can be present on load time when the bulk page is refreshed during operations.
	*  Reload the past error and display them in the error box.
	* @param String $type  media or custom
	*/
	protected function loadCurrentLog($type = 'media')
	{
		$bulkController = BulkController::getInstance();

		$log = $bulkController->getLog('current_bulk_' . $type . '.log');

		if ($log == false)
			return false;

		 $content = $log->getContents();
		 $lines = array_filter(explode(';', $content));

		 $output = '';

		 foreach ($lines as $line)
		 {
			 	$cells = array_filter(explode('|', $line));

				if (count($cells) == 1)
					continue; // empty line.

				$date = $filename = $message = $item_id = false;

				$date = $cells[0];
				$filename = isset($cells[1]) ? $cells[1] : false;
				$item_id = isset($cells[2]) ? $cells[2] : false;
				$message = isset($cells[3]) ? $cells[3] : false;

				$kblink = UIHelper::getKBSearchLink($message);
				$kbinfo = '<span class="kbinfo"><a href="' . $kblink . '" target="_blank" ><span class="dashicons dashicons-editor-help">&nbsp;</span></a></span>';

				$output .= '<div class="fatal">';
				$output .= $date . ': ';
				if ($message)
					$output .= $message;
				if ($filename)
					$output .= ' ( '. __('in file ','shortpixel-image-optimiser') . ' ' . $filename . ' ) ' . $kbinfo;

				$output .= '</div>';
		 }


		 return $output;
	}

  public function getLogs()
  {
      $bulkController = BulkController::getInstance();
      $logs = $bulkController->getLogs();
      $fs = \wpSPAATG()->filesystem();
      $backupDir = $fs->getDirectory(SPAATG_BACKUP_FOLDER);

      $view = array();

      foreach($logs as $logData)
      {
          $logFile = $fs->getFile($backupDir->getPath() . 'bulk_' . $logData['type'] . '_' . $logData['date'] . '.log');
          $errors = $logData['fatal_errors'];

          if ($logFile->exists())
					{
            $errors = '<a data-action="OpenLog" data-file="' . $logFile->getFileBase() . '" href="' . $fs->pathToUrl($logFile) . '">' . $errors . '</a>';
					}

						$op = (isset($logData['operation'])) ? $logData['operation'] : false;

						$bulkName = __('Media Library Bulk', 'shortpixel-image-optimiser') . ' ';

					switch($op)
					{
							 case 'bulk-undoAI':
								$bulkName  = __('Bulk Revert AI SEO Data', 'shortpixel-image-optimiser');
							 break;
								 default:
									$bulkName .= __('AI SEO Generation', 'shortpixel-image-optimiser');
							 break;
					}

					$images = isset($logData['total_images']) ? $logData['total_images'] : $logData['processed'];

          $view[] = array('type' => $logData['type'], 'images' => $images, 'errors' => $errors, 'date' => UiHelper::formatTS($logData['date']), 'operation' => $op, 'bulkName' => $bulkName);
      }

      krsort($view);

      return $view;
  }

} // class
