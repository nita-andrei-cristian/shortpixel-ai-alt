<?php

namespace SPAATG\Controller;

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

use SPAATG\Controller\View\ListMediaViewController as ListMediaViewController;

use SPAATG\ShortPixelLogger\ShortPixelLogger as Log;
use SPAATG\Notices\NoticeController as Notices;

//use SPAATG\Controller\BulkController as BulkController;
use SPAATG\Helper\UiHelper as UiHelper;
use SPAATG\Helper\InstallHelper as InstallHelper;
use SPAATG\Helper\UtilHelper;

use SPAATG\Model\Image\ImageModel as ImageModel;
use SPAATG\Model\AccessModel as AccessModel;

// @todo This should probably become settingscontroller, for saving
use SPAATG\Controller\View\SettingsViewController as SettingsViewController;
use SPAATG\Controller\Queue\QueueItems as QueueItems;
use SPAATG\Model\AiDataModel;
use SPAATG\Model\Queue\QueueItem;

// Class for containing all Ajax Related Actions.
class AjaxController
{
	const PROCESSOR_ACTIVE = -1;
	const NONCE_FAILED = -2;
	const NO_ACTION = -3;
	const APIKEY_FAILED = -4;
	const NOQUOTA = -5;
	const SERVER_ERROR = -6;
	const NO_ACCESS = -7;

	private static $instance;

	public static function getInstance()
	{
		if (is_null(self::$instance))
			self::$instance = new static();

		return self::$instance;
	}

	// Support for JS Processor - also used by localize to get for init.
	public function getProcessorKey()
	{
		// Get a Secret Key.
		$cacheControl = new CacheController();
		$bulkSecret = $cacheControl->getItem('bulk-secret');

		$secretKey = $bulkSecret->getValue();
		if (is_null($secretKey) || strlen($secretKey) == 0 || $secretKey === 'null') {
			$secretKey = false;
		}
		return $secretKey;
	}

	protected function checkProcessorKey()
	{
		$processKey = $this->getProcessorKey();
		// phpcs:ignore -- Nonce is checked
		$bulkSecret = isset($_POST['bulk-secret']) ? sanitize_text_field(wp_unslash($_POST['bulk-secret'])) : false;
		// phpcs:ignore -- Nonce is checked
		$isBulk = isset($_POST['isBulk']) ? filter_var(sanitize_text_field(wp_unslash($_POST['isBulk'])), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : false;

		$is_processor = false;
		if ($processKey == false && $bulkSecret !== false) {
			$is_processor = true;
		} elseif ($processKey == $bulkSecret) {
			$is_processor = true;
		} elseif ($isBulk) {
			$is_processor = true;
		}

		// Save new ProcessorKey
		if ($is_processor && $bulkSecret !== $processKey) {
			$cacheControl = new CacheController();
			$cachedObj = $cacheControl->getItem('bulk-secret');

			$cachedObj->setValue($bulkSecret);
			$cachedObj->setExpires(2 * MINUTE_IN_SECONDS);
			$cachedObj->save();
		}

		if (false === $is_processor) {
			$json = new \stdClass;
			$json->message = __('Processor is active in another window', 'shortpixel-image-optimiser');
			$json->status = false;
			$json->error = self::PROCESSOR_ACTIVE; // processor active
			$this->send($json);
		}
	}

	protected function getItemView()
	{
		// phpcs:ignore -- Nonce is checked
			$type = 'media';
		// phpcs:ignore -- Nonce is checked
		$id = isset($_POST['id']) ? intval($_POST['id']) : false;
		$result = '';


		$item = \wpSPAATG()->filesystem()->getImage($id, $type);

		$this->checkImageAccess($item);

			if ($id > 0) {
				ob_start();
				$control = ListMediaViewController::getInstance();
				$control->doColumn('wp-spaatg', $id);
				$result = ob_get_contents();
				ob_end_clean();
			}

		$json = new \stdClass;
		$json->$type = new \stdClass;
		$json->$type->itemView = $result;
		$json->$type->is_optimizable = (false !== $item) ? $item->isProcessable() : false;
		$json->$type->id = $id;
		$json->$type->image = [
			'width' => $item->get('width'), 
			'height' => $item->get('height'), 
			'extension' => $item->getExtension(), 
		];
		$json->$type->results = null;
		$json->$type->is_error = false;
		$json->status = true;

		return $json;
		// $this->send($json);
	}

	public function ajax_processQueue()
	{
		$this->checkNonce('processing');
		$this->checkActionAccess('processQueue', 'is_author');
		$this->checkProcessorKey();

		ErrorController::start(); // Capture fatal errors for us.

		// Notice that POST variables are always string, so 'true', not true.
		// phpcs:ignore -- Nonce is checked
		$isBulk = (isset($_POST['isBulk']) && $_POST['isBulk'] == 'true') ? true : false;
		// phpcs:ignore -- Nonce is checked
		$queue = (isset($_POST['queues'])) ? sanitize_text_field($_POST['queues']) : 'media';

		$queues = array_filter(explode(',', $queue), 'trim');

		$control = new QueueController(['is_bulk' => $isBulk]);
		$result = $control->processQueue($queues);

		$this->send($result);
	}

	/** Ajax function to recheck if something can be active. If client is doens't have the processor key, it will check later if the other client is 'done' or went away. */
	protected function recheckActive()
	{
		// If not processor, this ends the processing and sends JSON.
		$this->checkProcessorKey();

		$json = new \stdClass;
		$json->message = __('Became processor', 'shortpixel-image-optimiser');
		$json->status = true;
		$this->send($json);
	}

	public function ajaxRequest()
	{
		$this->checkNonce('ajax_request');
		ErrorController::start(); // Capture fatal errors for us.

		$this->checkActionAccess('ajax', 'is_author');

		// phpcs:ignore -- Nonce is checked
		$action = isset($_POST['screen_action']) ? sanitize_text_field($_POST['screen_action']) : false;
		// phpcs:ignore -- Nonce is checked
		$typeArray = isset($_POST['type'])  ? array(sanitize_text_field($_POST['type'])) : array('media');
		// phpcs:ignore -- Nonce is checked
		$id = isset($_POST['id']) ? intval($_POST['id']) : false;

		$json = new \stdClass;
		foreach ($typeArray as $type) {
			$json->$type = new \stdClass;
			//      $json->$type->id = $id;  // This one is off because item_id belongs to results -> itemResult.
			$json->$type->results = null;
			//      $json->$type->is_error = false;
			$json->status = false;
		}

		$data = array('id' => $id, 'typeArray' => $typeArray, 'action' => $action);

		if (count($typeArray) == 1) // Actions that need a specific queue type.
		{
			$data['type'] = $typeArray[0];
			unset($data['typeArray']);
		}

		// First Item action,  alphabet.  Second general actions, alpha.
		switch ($action) {
			case 'cancelOptimize':
				$json = $this->cancelOptimize($json, $data);
				break;
			case 'getItemView':
				$json = $this->getItemView($json, $data);
				break;
			case 'settings/importexport':
				$json = $this->importexportSettings($json, $data);
			break; 
			case 'ai/requestalt': 
				$json = $this->requestAlt($json, $data);	
			break; 
			case 'ai/getAltData': 
				$json = $this->getAltData($json, $data);
			break; 
			case 'ai/undoAlt':
				$json = $this->undoAltData($json, $data);			
			break;
			case 'applyBulkSelection':
				$this->checkActionAccess($action, 'is_editor');
				$json = $this->applyBulkSelection($json, $data);
				break;
			case 'createBulk':
				$this->checkActionAccess($action, 'is_editor');
				$json = $this->createBulk($json, $data);
				break;
			case 'finishBulk':
				$this->checkActionAccess($action, 'is_editor');
				$json = $this->finishBulk($json, $data);
				break;
			case 'startBulk':
				$this->checkActionAccess($action, 'is_editor');
				$json = $this->startBulk($json, $data);
				break;
				case 'startBulkUndoAI':
					$this->checkActionAccess($action, 'is_admin_user');
					$json = $this->startUndoAI($json, $data);
					break;
			case "toolsRemoveAll":
				$this->checkActionAccess($action, 'is_admin_user');
				$json = $this->removeAllData($json, $data);
				break;
			case "toolsRemoveBackup":
				$this->checkActionAccess($action, 'is_admin_user');
				$json = $this->removeBackup($json, $data);
				break;
			case 'request_new_api_key': // @todo Dunnoo why empty, should go if not here.

			break;
			case "loadLogFile":
				$this->checkActionAccess($action, 'is_editor');
				$data['logFile'] = isset($_POST['loadFile']) ? sanitize_text_field($_POST['loadFile']) : null;
				$json = $this->loadLogFile($json, $data);
				break;

			case 'recheckActive':
				$this->checkActionAccess($action, 'is_editor');
				$json = $this->recheckActive($json, $data);
				break;
			case 'settings/changemode':
				$this->handleChangeMode($data);
				break;
			case 'settings/getAiExample': 
				$this->checkActionAccess($action, 'is_admin_user');
				$this->getSettingsAiExample($data);
			break; 
			case 'settings/setAiImageId': 
				$this->checkActionAccess($action, 'is_admin_user');
				$this->setSettingsAiImage($data);
			break; 
			case 'settings/getNewAiImagePreview': 
				$this->getNewAiImagePreview($data);
			break;
			default:
				$json->$type->message = __('Ajaxrequest - no action found', 'shorpixel-image-optimiser');
				$json->error = self::NO_ACTION;
			break;
		}
		$this->send($json);
	}

	public function settingsRequest()
	{
		$this->checkNonce('settings_request');
		ErrorController::start(); // Capture fatal errors for us.

		$action = isset($_POST['screen_action']) ? sanitize_text_field($_POST['screen_action']) : false;

		$this->checkActionAccess($action, 'is_admin_user');

		switch ($action) {
			case 'form_submit':
			case 'action_addkey':
			case 'action_debug_redirectBulk':
			case 'action_debug_removePrevented':
			case 'action_debug_removeProcessorKey':
			case 'action_debug_resetNotices':
			case 'action_debug_resetQueue':
			case 'action_debug_resetquota':
			case 'action_debug_resetStats':
			case 'action_debug_triggerNotice':
			case 'action_request_new_key':
			case 'action_debug_editSetting':
			case 'action_end_quick_tour':
				$this->settingsFormSubmit($action);
				break;
			default:

				Log::addError('Issue with settingsRequest, not valid action');
				exit('0');
				break;
		}
	}

	protected function settingsFormSubmit($action)
	{
		$viewController =  new SettingsViewController();
		$viewController->indicateAjaxSave(); // set ajax save method

		$url = isset($_POST['request_url']) ? sanitize_text_field($_POST['request_url']) : null;
		if (is_null($url)) {
			Log::addError('Ajax : redirect URL not set!');
		}
		$viewController->setControllerURL($url); // set url for redirects, otherwise set by route / plugin
		if (method_exists($viewController, $action)) {
			$viewController->$action();
		} else {
			$viewController->load();
		}

		exit('ajaxcontroller - formsubmit');
	}

	protected function getMediaItem($id, $type)
	{
		$fs = \wpSPAATG()->filesystem();
		return $fs->getImage($id, $type);
	}

	

				
	protected function importexportSettings($json, $data)
	{
		$action = (isset($_POST['actionType'])) ? sanitize_text_field($_POST['actionType']) : 'export'; 
		$this->checkActionAccess($action, 'is_admin_user');
		$settings = \wpSPAATG()->settings();

		if ('import' === $action)
		{
			$importdata = (isset($_POST['importData'])) ? sanitize_text_field(trim($_POST['importData'])) : false; 
			$importdata = stripslashes($importdata); 
			$importdata = trim($importdata); 

			if (false === $importdata || 0 == strlen($importdata))
			{
				 $json->settings->results = ['is_error' => true, 'message' => __('Import contained empty field', 'shortpixel-image-optimiser')];
			}
			elseif (true ===  UtilHelper::validateJson($importdata) )
			{
				//$result = ['is_error' => false];
				$messages = []; 

				$importjson = json_decode($importdata, true);
				Log::addInfo('JSON Import: ', $importjson);
				$counter = 0;
				foreach($importjson as $name => $value )
				{
					if (false === $settings->exists($name))
					{
						$messages[] = sprintf(__('Field with name %s does not exist in current version', 'shortpixel-image-optimiser'), $name);
					}
					else
					{
						 $settings->$name = $value;
						 $counter++;
					}
				}

				$messages[] = sprintf(__('%s settings imported! Reload page to see changes', 'shortpixel-image-optimiser'), $counter); 
				$json->settings->results = ['is_error' => false, 'messages' => $messages];
		 
			}
			else
			{
				$json->settings->results = ['is_error' => true, 
				'message' => sprintf(__('Invalid JSON sent: %s', 'shortpixel-image-optimiser'), json_last_error_msg())];
			}

		}
		else
		{	
			$data = $settings->getExport(); 
			
			$json->settings->exportData = json_encode($data);
			$json->settings->message = __('Export completed. Copy the string below', 'shortpixel-image-optimiser');
			
		}
		
		$json->status = true; 
		return $json;
	}

	protected function cancelOptimize($json, $data)
	{
		$id = intval($_POST['id']);
		$type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'media';

		$mediaItem = $this->getMediaItem($id, $type);

		$this->checkImageAccess($mediaItem);

		$mediaItem->dropFromQueue();


		$qItem = QueueItems::getImageItem($mediaItem);
		$qItem->addResult([
			'apiName' => 'ai',
			'fileStatus' => ImageModel::FILE_STATUS_SUCCESS, 
			'item_id' => $id, 
			'message' => __('Item removed from queue', 'shortpixel-image-optimiser'), 
			'is_done' => true, 
			'is_error' => false, 
		]);

		$json->$type->results = [$qItem->result()];
		$json->status = true;

		return $json;
	}

	protected function requestAlt($json, $data)
	{
		$id = $data['id'];
		$type = $data['type'];

		$preview_only = isset($_POST['preview_only']) ? true : false; 
		$aiPreserve = isset($_POST['aiPreserve']) ? filter_var(sanitize_text_field($_POST['aiPreserve']), FILTER_VALIDATE_BOOLEAN) : null;
		$imageModel = $this->getMediaItem($id, $type);

		$queueController = new QueueController();

		$args = [
			'action' => 'requestAlt',
		];
		if (true === $preview_only)
		{
			$args['preview_only'] = true; 
		}
		if (false === is_null($aiPreserve))
		{
			$args['aiPreserve'] = $aiPreserve;
		}
		$result = $queueController->addItemToQueue($imageModel, $args);
		$result->apiName = 'ai'; // prevent response leaking to media interface.
		$json->$type->results = [$result];
		$json->$type->qstatus = $queueController->getLastQueueStatus();
		$json->status = true;

		return $json;
	} 

	protected function getAltData($json, $data)
	{
		 $id = $data['id'];
		 $type = $data['type']; 

		 $imageModel = $this->getMediaItem($id, $type); 

		 $this->checkImageAccess($imageModel);

		 $queueItem = new QueueItem(['imageModel' => $imageModel]);

		 $queueItem->getAltDataAction(); 

		 $api = $queueItem->getApiController('getAltData'); 

		 $metadata = $api->getAltData($queueItem);

		 $json->$type = (object) $metadata; 
		 $json->$type->results = null;
		 $json->status = true; 
		 
		 return $json;
	}
	
	protected function undoAltData($json, $data)
	{
		$id = $data['id'];
		$type = $data['type']; 
		// undo or redo 
		$action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : 'undo'; 

		$imageModel = $this->getMediaItem($id, $type); 
		$this->checkImageAccess($imageModel);


		// @todo Should e.v be moved to QItem hop. 
		/*$queueController = new QueueController();
		$action = ('redo' == $action_type) ? 'redoAI' : 'undoAI'; 
		
		$result  = $queueController->addItemToQueue($imageModel, ['action' => $action]);
*/
		$queueItem = new QueueItem(['imageModel' => $imageModel]);

		$queueItem->getAltDataAction(); 

		$api = $queueItem->getApiController('getAltData'); 

		$altData = $api->undoAltData($queueItem);

		if ('redo' == $action_type)
		{
			 return $this->requestAlt($json, $data);
		} 

		$json->$type = $altData;
		$json->status = true;
		
		return $json;
	}



	protected function finishBulk($json, $data)
	{
		$bulkControl = BulkController::getInstance();

		$bulkControl->finishBulk('media');
		$bulkControl->finishBulk('custom');

		$json->status = 1;

		return $json;
	}


	protected function createBulk($json, $data)
	{
		$filters = []; 
		$has_filters = false; 
		
		if (isset($_POST['filter_startdate'])) 
		{
			 $filters['start_date'] = sanitize_text_field($_POST['filter_startdate']); 
			 $has_filters = true; 	 
		}
		if (isset($_POST['filter_enddate']))
		{
			 $filters['end_date'] = sanitize_text_field($_POST['filter_enddate']); 
			 $has_filters = true; 
		}

		$args = []; 
		if (true === $has_filters)
		{ 
			$args['filters'] = $filters; 
			Log::addTemp('Queue starting with filters: ', $filters);
		}

		
			$bulkControl = BulkController::getInstance();
			$mediaArgs = array_merge($args, ['doMedia' => false, 'doAi' => true]);

		$stats = $bulkControl->createNewBulk('media', $mediaArgs);
		$json->media->stats = $stats;

		$json = $this->applyBulkSelection($json, $data);
		return $json;
	}

	protected function applyBulkSelection($json, $data)
	{
		// These values should always be given!
		$doCustom = filter_var(sanitize_text_field($_POST['customActive']), FILTER_VALIDATE_BOOLEAN);
		$doAi = true;

		$aiPreserve = isset($_POST['aiPreserve']) ? filter_var(sanitize_text_field($_POST['aiPreserve']), FILTER_VALIDATE_BOOLEAN) : null; 
		$backgroundProcess = isset($_POST['backgroundProcess']) ? filter_var(sanitize_text_field($_POST['backgroundProcess']), FILTER_VALIDATE_BOOLEAN) : null;

		// Can be hidden
		if (isset($_POST['thumbsActive'])) {
			$doThumbs = filter_var(sanitize_text_field($_POST['thumbsActive']), FILTER_VALIDATE_BOOLEAN);
			\wpSPAATG()->settings()->processThumbnails = $doThumbs;
		}

		\wpSPAATG()->settings()->createWebp = false;
		\wpSPAATG()->settings()->createAvif = false;
		if (isset($_POST['backgroundProcess']))
		{
			\wpSPAATG()->settings()->doBackgroundProcess = $backgroundProcess;
		}
		\wpSPAATG()->settings()->autoAIBulk = $doAi;

		if (false === is_null($aiPreserve))
		{
			\wpSPAATG()->settings()->aiPreserve = $aiPreserve;
		}

		$bulkControl = BulkController::getInstance();

		if (! $doCustom) {
			$bulkControl->finishBulk('custom');
		}

		$queueController = new QueueController(['is_bulk' => true]);

		$data = $queueController->getStartupData();

		$json->media->stats = $data->media->stats;
		$json->custom->stats = $data->custom->stats;
		$json->total = $data->total;

		$json->status = true;

		return $json;
	}



	protected function startBulk($json, $data)
	{
		$bulkControl = BulkController::getInstance();

		$result = $bulkControl->startBulk('media');

		$this->send($result);
	}

	protected function startUndoAI($json, $data)
	{
		$bulkControl = BulkController::getInstance();
		QueueController::resetQueues(); // prevent any weirdness

		$stats = $bulkControl->createNewBulk('media', ['customOp' => 'bulk-undoAI']);
		$json->media->stats = $stats;

		return $json;

	}

	protected function handleChangeMode($data)
	{
		$user_id = get_current_user_id();
		$new_mode = isset($_POST['new_mode']) ? sanitize_text_field($_POST['new_mode']) : false;

		if (false === $new_mode) {
			return false;
		}

		update_user_option($user_id, 'spaatg-settings-mode', $new_mode);
	}

	protected function getNewAiImagePreview($data)
	{
		$item_id = $data['id'];
		$settingsData = isset($_POST['settingsData']) ? $_POST['settingsData'] : null; 

		if (! is_null($settingsData))
		{
			 $json = json_decode(stripslashes($settingsData), true);
			 $settings = \wpSPAATG()->settings(); 
			 //$settingsData = array_map('sanitize_text_field', $json); 
			 $settingsData = $settings->getSanitizedData($json, false);
		}
		else
		{
			 $settingsData = [];  // null - empty array
		}

		$result_json = [
			'error' => __('Something went wrong', 'shortpixel-image-optimiser'), 
			'is_error' => true, 
		];

		$imageModel = \wpSPAATG()->filesystem()->getMediaImage($item_id); 
		

		if (false === $imageModel)
		{
			 $result_json['message'] = __('This image could not be loaded', 'shortpixel-image-optimiser'); 
			 $this->send((object) $result_json);
		}

		$qItem = QueueItems::getImageItem($imageModel);

		$optimizer = $qItem->getApiController('requestAlt');

		$qItem->requestAltAction(array_merge(['preview_only' => true], $settingsData));
		$optimizer->sendToProcessing($qItem);
		$result = $qItem->result(); 
		
		$state = 'requestAlt'; // mimic here the double task of the Ai gen. 
		$is_done = false; 
		$i = 0; 


		while (false === $is_done)
		{

			if (false === property_exists($result, 'is_done') || $result->is_done === false)
			{ 
				$optimizer->sendToProcessing($qItem);
				$result = $qItem->result();
			}
			
			if (property_exists($result, 'is_done') && true === $result->is_done)
			{
				// If is done and is error, bail out. 
				if (true === $result->is_error) 
				{
					$this->send($result);
				}
				
				if ('requestAlt' === $state)
				{
					$remote_id = $result->remote_id; 
					
					$result = $optimizer->enqueueItem($qItem, ['preview_only' => true, 'action' => 'retrieveAlt', 'remote_id' => $remote_id]); 
					$state = 'retrieveAlt';
					
				}
				if ('retrieveAlt' === $state)
				{
					Log::addTemp('Result', $result); 
					if (property_exists($result, 'aiData'))
					{
						$aiModel = AiDataModel::getModelByAttachment($qItem->item_id, 'media');

						 $aiData = $optimizer->formatResultData($result->aiData, $qItem);
						 list($items, $aiData) = $optimizer->formatGenerated($aiData, $aiModel->getCurrentData(), $aiModel->getOriginalData(), true);
						 $aiData['item_id'] = $qItem->item_id;
						 $aiData['time_generated'] = time(); 

						 set_transient('spaatg_settings_ai_example', $aiData, MONTH_IN_SECONDS);
						 set_transient('spaatg_settings_ai_example_id', $qItem->item_id, MONTH_IN_SECONDS); 
						 
						 $aiData['aiData'] = true; // for the JS check
						 $this->send((object) $aiData);
						 $is_done = true; 
						 break;  // safe guards.

					}
					
					if ($result->is_done)
					{
					 $this->send($result); 
					 break;
					}
				}
				
			}

			if ('retrieveAlt' === $state)
			{
				sleep(2); // prevent in case of fast connection hammering the API
			}

			if ($i >= 30) // safeguard. 
			{
				$this->send((object) $result_json);
				break; 
			}
			$i++; 
		}
	}

	protected function getSettingsAiExample($data)
	{
		 
		$id = get_transient('spaatg_settings_ai_example_id');

		if (false === $id || ! is_numeric($id))
		{
			$item = AiDataModel::getMostRecent();
			$attach_id = $item->getAttachId(); 
		}
		else
		{
			$item = AiDataModel::getModelByAttachment($id);
			$attach_id = $id; 
		}
		
		$imageModel = \wpSPAATG()->fileSystem()->getMediaImage($attach_id);

        if (is_null($attach_id) || false === $imageModel)
        {
           // make something up
		   $json = [
				'preview_image' => '', 
				'item_id' => -1, 
				'generated' => ['alt' => __('Select an image for example', 'shortpixel-image-optimser')], 
				'original'	=> [], 
		   ]; 
		   $this->send((object) $json);
        }
        else
        {
		  $transient = get_transient('spaatg_settings_ai_example'); 
		  if (is_array($transient) && $transient['item_id'] == $id)
		  { 
			 $generated = $transient; 
		  }
		  else
		  {
			$generated = $item->getGeneratedData();
		  }

		  if ($item->isSomeThingGenerated())
		  {
          	$original = $item->getOriginalData();
		  }
		  else
		  {
			 $original = $item->getCurrentData();
		  }
        }


        $json = [
          'preview_image' => UiHelper::findBestPreview($imageModel)->getURL(), 
		  'item_id' => $attach_id,
          'generated' => $generated, 
          'original' => $original,
        ];

        $this->send((object) $json);
	}

	protected function setSettingsAiImage($data)
	{
		 $id = $data['id']; 
		 set_transient('spaatg_settings_ai_example_id', $id, MONTH_IN_SECONDS); 

		 return $this->getSettingsAiExample($data);
	}

	/*
	public function ajax_getBackupFolderSize()
	{
		$this->checkNonce('ajax_request');
		$this->checkActionAccess($action, 'is_editor');

		$dirObj = \wpSPAATG()->filesystem()->getDirectory(SPAATG_BACKUP_FOLDER);

		$size = $dirObj->getFolderSize();
		echo UiHelper::formatBytes($size);
		exit();
	}
	*/
	
	public function ajax_proposeQuotaUpgrade()
	{
		$this->checkNonce('ajax_request');
		$this->checkActionAccess('propose_upgrade', 'is_editor');

		$notices = AdminNoticesController::getInstance();
		$notices->proposeUpgradeRemote();
		exit();
	}

	public function ajax_checkquota()
	{

		$this->checkNonce('ajax_request');
		$action = 'check_quota';
		$this->checkActionAccess($action, 'is_author');

		$quotaController = QuotaController::getInstance();
		$quotaController->forceCheckRemoteQuota();

		$quota = $quotaController->getQuota();

		$settings = \wpSPAATG()->settings();

		$sendback = wp_get_referer();
		// sanitize the referring webpage location
		$sendback = preg_replace('|[^a-z0-9-~+_.?#=&;,/:]|i', '', $sendback);

		$result = array('status' => 'no-quota', 'redirect' => $sendback);
		if (! $settings->quotaExceeded) {
			$result['status'] = 'has-quota';
		} else {
			Notices::addWarning(__('You have no available image credits. If you just bought a package, please note that sometimes it takes a few minutes for the payment processor to send us the payment confirmation.', 'shortpixel-image-optimiser'));
		}

		wp_send_json($result);
	}



	protected function loadLogFile($json, $data)
	{
		$logFile = $data['logFile'] . '.log';
		$type = $data['type'];
		$fs = \wpSPAATG()->filesystem();

		if (is_null($logFile)) {
			$json->$type->is_error = true;
			$json->$type->result = __('Could not load log file', 'shortpixel-image-optimiser');
			return $json;
		}

		$bulkController = BulkController::getInstance();
		$log = $bulkController->getLog($logFile);
		$logData = $bulkController->getLogData($logFile);

		$logType = $logData['type']; // custom or media.

		$json->$type->logType = $logType;

		if (false === $log) {
			$json->$type->is_error = true;
			$json->$type->result = __('Log file does not exist', 'shortpixel-image-optimiser');
			return $json;
		}

		$date = (isset($logData['date'])) ? UiHelper::formatTS($logData['date']) : false;
		$content = trim($log->getContents());
		$lines = array_filter(explode(';', $content));

		$headers = [
			__('Time', 'shortpixel-image-optimiser'),
			__('Filename', 'shortpixel-image-optimiser'),
			__('ID', 'shortpixel-image-optimiser'),
			__('Error', 'shortpixel-image-optimiser'),
		];

		if ('custom' == $logType) {
			array_splice($headers, 3, 0, __('Info', 'shortpixel-image-optimiser'));
		}

		foreach ($lines as $index => $line) {
			$cells = array_filter(explode('|', $line));

			$date = $cells[0];
			$filename = $cells[1];
			$id = isset($cells[2]) ? $cells[2] : false;
			$error = isset($cells[3]) ? $cells[3] : false;

			$line = ['date' => $date, 'filename' => $filename, 'id' => $id, 'error' => $error];

			if ($id !== false && $logType !== 'custom') {
				// replaces the image id with a link to image.
				$line['link'] = esc_url(admin_url('post.php?post=' . trim($id) . '&action=edit'));
			} elseif ($logType === 'custom') {
				$base = esc_url(admin_url('upload.php?page=wp-spaatg-custom'));
				$line['link'] = add_query_arg('s', sanitize_text_field($filename), $base);
			}

			if ($error !== false) {
				$line['kblink'] = UiHelper::getKBSearchLink($error);
			}

			if ('custom' == $logType && $id !== false) {
				$imageObj = $fs->getImage($id, 'custom');
				if (is_object($imageObj)) {
					$dir = $imageObj->getFileDir();
					if (is_object($dir)) {
						$path = $dir->getRelativePath();
						$line['path'] = $path;
					}
				}
			}

			$lines[$index] = $line;
		}
		$lines = array_values(array_filter($lines));
		array_unshift($lines, $headers);
		$json->$type->title = sprintf(__('Bulk ran on %s', 'shortpixel-image-optimiser'), $date);
		$json->$type->results = $lines;
		return $json;
	}

	protected function checkNonce($action)
	{
		if (! wp_verify_nonce($_POST['nonce'], $action)) {

			$id = isset($_POST['id']) ? intval($_POST['id']) : false;
			$action = isset($_POST['screen_action']) ? sanitize_text_field($_POST['screen_action']) : false;

			$json = new \stdClass;
			$json->message = __('Nonce is missing or wrong - Try to refresh the page', 'shortpixel-image-optimiser');
			$json->item_id = $id;
			$json->action = $action;
			$json->status = false;
			$json->error = self::NONCE_FAILED;
			$this->send($json);
			exit();
		}
	}

	protected function checkActionAccess($action, $access)
	{
		$accessModel = AccessModel::getInstance();

		$bool = $accessModel->userIsAllowed($access);

		if ($bool === false) {
			$json = new \stdClass;
			$json->message = __('This user is not allowed to perform this action', 'shortpixel-image-optimiser');
			$json->action = $action;
			$json->status = false;
			$json->error = self::NO_ACCESS;
			$this->send($json);
			exit();
		}

		return true;
	}

	protected function checkImageAccess($mediaItem)
	{

		// defaults 
		$message = __('This user is not allowed to edit this image', 'shortpixel-image-optimiser');

		$accessModel = AccessModel::getInstance();
		if (is_object($mediaItem)) {
			$bool = $accessModel->imageIsEditable($mediaItem);
			$id = $mediaItem->get('id');

		} else {
			$bool = false;
			$id = false;
			if (! is_object($mediaItem))
			{
				$message = __('Image does not exist or could not be loaded', 'shortpixel-image-optimiser');
			}
		}

		if ($bool === false) {
			$json = new \stdClass;
			$json->message = $message; 
			$json->status = false;
			$json->id = $id;
			$json->error = self::NO_ACCESS;
			$this->send($json);
			exit();
		}

		return true;
	}

	protected function send($json)
	{
		$callback = isset($_POST['callback']) ? sanitize_text_field($_POST['callback']) : false;
		if ($callback)
			$json->callback = $callback; // which type of request we just fullfilled ( response processing )

		$pKey = $this->getProcessorKey();
		if ($pKey !== false)
			$json->processorKey = $pKey;

		wp_send_json($json);
		exit();
	}


	private function removeAllData($json, $data)
	{
		if (1 === wp_verify_nonce($_POST['tools-nonce'], 'remove-all')) {
			InstallHelper::hardUninstall();
			$json->settings->results = __('All Data has been removed. The plugin has been deactivated', 'shortpixel-image-optimiser');
		} else {
			Log::addError('RemoveAll detected with wrong nonce');
		}

		$json->settings->redirect = admin_url('plugins.php');

		return $json;
	}

	private function removeBackup($json, $data)
	{
		if (wp_verify_nonce($_POST['tools-nonce'], 'empty-backup')) {			

			$fs = \wpSPAATG()->filesystem(); 
			
			$fs->moveLogFiles(); 

			$dir = \wpSPAATG()->filesystem()->getDirectory(SPAATG_BACKUP_FOLDER);
			$dir->recursiveDelete(); 

			$fs->moveLogFiles(['to_temp' => false]);

			$json->settings->results = __('The backups have been removed. You can close the window', 'shortpixel-image-optimiser');
		} else {
			$json->settings->results = __('Error: Invalid Nonce in empty backups', 'shortpixel-image-optimiser');
		}

		return $json;
	}
}
