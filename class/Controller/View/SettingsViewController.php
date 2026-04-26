<?php
namespace SPAATG\Controller\View;

if ( ! defined( 'ABSPATH' ) ) {
 exit; // Exit if accessed directly.
}

use SPAATG\ShortPixelLogger\ShortPixelLogger as Log;
use SPAATG\Notices\NoticeController as Notice;
use SPAATG\Helper\UiHelper as UiHelper;
use SPAATG\Helper\InstallHelper as InstallHelper;

use SPAATG\Model\AccessModel as AccessModel;
use SPAATG\Model\SettingsModel as SettingsModel;
use SPAATG\Model\ApiKeyModel as ApiKeyModel;

use SPAATG\Controller\ApiKeyController as ApiKeyController;
use SPAATG\Controller\StatsController as StatsController;
use SPAATG\Controller\QuotaController as QuotaController;
use SPAATG\Controller\AdminNoticesController as AdminNoticesController;
use SPAATG\Controller\QueueController as QueueController;

use SPAATG\Controller\CacheController as CacheController;
use SPAATG\Controller\Optimizer\OptimizeAiController;
use SPAATG\Model\AiDataModel;

class SettingsViewController extends \SPAATG\ViewController
{

     //env
		 protected $has_image_library;
		 protected $is_curl_installed;
     protected $is_multisite;
     protected $is_mainsite;
     protected $do_redirect = false;
     protected $disable_heavy_features = false; // if virtual and stateless, might disable heavy file ops.

     protected $quotaData = null;

     protected $keyModel;

     protected $mapper = array(
       'cmyk2rgb' => 'CMYKtoRGBconversion',
     );

     protected $display_part = 'account';
     protected $all_display_parts = array('account', 'ai', 'preview', 'help', 'debug');
     protected $form_action = 'save-settings';
     protected $view_mode = 'simple'; // advanced or simple
		 protected $is_ajax_save = false; // checker if saved via ajax ( aka no redirect / json return )
		 protected $notices_added = []; // Added notices this run, to report via ajax.

     // Array of updated values to be passed back in the settings page
     protected $returnFormData = []; 

		 protected static $instance;

      public function __construct()
      {
          $this->model = \wpSPAATG()->settings();
					$keyControl = ApiKeyController::getInstance();
          $this->keyModel = $keyControl->getKeyModel();

          parent::__construct();
      }

      // default action of controller
      public function load()
      {
        $this->loadEnv();
        $this->checkPost(); // sets up post data


        if ($this->model->redirectedSettings < 2)
        {
          $this->model->redirectedSettings = 2; // Prevents any redirects after loading settings
        };

        if ($this->is_form_submit)
        {
          $this->processSave();
        }

        $this->load_settings();
      }

			public function saveForm()
			{
				 $this->loadEnv();

			}

      public function indicateAjaxSave()
      {
           $this->is_ajax_save = true;
      }

      // this is the nokey form, submitting api key
      public function action_addkey()
      {
        $this->loadEnv();

        $this->checkPost(false);

        $apiKey = null;
        if (isset($_POST['apiKey']))
        {
            $apiKey = sanitize_text_field($_POST['apiKey']);
        }
        elseif (isset($_POST['login_apiKey']))
        {
            $apiKey = sanitize_text_field($_POST['login_apiKey']);
        }

        if ($this->is_form_submit && ! is_null($apiKey))
        {
            if (strlen(trim($apiKey)) == 0) // display notice when submitting empty API key
            {
              Notice::addError(sprintf(__("The key you provided has %s characters. The API key should have 20 characters, letters and numbers only.",'shortpixel-image-optimiser'), strlen($apiKey) ));
            }
            else
            {

            $this->keyModel->resetTried();
            $this->keyModel->checkKey($apiKey);
            }
        }

        if (true === $this->keyModel->is_verified())
        {
          $this->doRedirect('reload');
        }
        else {
          $this->doRedirect();
        }
      }

			public function action_request_new_key()
			{
					$this->loadEnv();
 	        $this->checkPost(false);

					$email = isset($_POST['pluginemail']) ? trim(sanitize_text_field($_POST['pluginemail'])) : null;

					// Not a proper form post.
					if (is_null($email))
					{
						$this->load();
						return;
					}


					$bodyArgs = array(
							'plugin_version' => SPAATG_IMAGE_OPTIMISER_VERSION,
							'email' => $email,
							'ip' => isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ? sanitize_text_field($_SERVER["HTTP_X_FORWARDED_FOR"]) : sanitize_text_field($_SERVER['REMOTE_ADDR']),
					);

	        $params = array(
	            'method' => 'POST',
	            'timeout' => 10,
	            'redirection' => 5,
	            'httpversion' => '1.0',
	            'blocking' => true,
	            'sslverify' => false,
	            'headers' => array(),
	            'body' => $bodyArgs,
	        );

	        $newKeyResponse = wp_remote_post("https://shortpixel.com/free-sign-up-plugin", $params);

					$errorText = __("There was problem requesting a new code. Server response: ", 'shortpixel-image-optimiser');

	        if ( is_object($newKeyResponse) && get_class($newKeyResponse) == 'WP_Error' ) {
	            //die(json_encode((object)array('Status' => 'fail', 'Details' => '503')));
							Notice::addError($errorText . $newKeyResponse->get_error_message() );
							$this->doRedirect(); // directly redirect because other data / array is not set.
	        }
	        elseif ( isset($newKeyResponse['response']['code']) && $newKeyResponse['response']['code'] <> 200 ) {
	            //die(json_encode((object)array('Status' => 'fail', 'Details' =>
							Notice::addError($errorText . $newKeyResponse['response']['code']);
							$this->doRedirect(); // strange http status, redirect with error.
	        }
					$body = $newKeyResponse['body'];
        	$body = json_decode($body);

	        if($body->Status == 'success') {
	            $key = trim($body->Details);
							$valid = $this->keyModel->checkKey($key);

	            if($valid === true) {
	                \SPAATG\Controller\AdminNoticesController::resetAPINotices();

	            }
							$this->doRedirect('reload');

	        }
					elseif($body->Status == 'existing')
					{
						 Notice::addWarning( sprintf(__('This email address is already in use. Please use your API-key in the "Already have an API key" field. You can obtain your license key via %s your account %s ', 'shortpixel-image-optimiser'), '<a href="https://shortpixel.com/login/">', '</a>') );
					}
					else
					{
						 Notice::addError( __('Unexpected error obtaining the ShortPixel key. Please contact support about this:', 'shortpixel-image-optimiser') . '  ' . json_encode($body) );

					}
					$this->doRedirect();

			}

      public function action_end_quick_tour()
      {
          $this->loadEnv();
          $this->checkPost(false);

          $this->model->redirectedSettings = 3;

          $this->doRedirect('reload');
      }

      public function action_debug_editSetting()
      {

        $this->loadEnv();
        $this->checkPost(false);

        $setting_name =  isset($_POST['edit_setting']) ? sanitize_text_field($_POST['edit_setting']) : false;
        $new_value = isset($_POST['new_value']) ? sanitize_text_field($_POST['new_value']) : false;
        $submit_name = isset($_POST['Submit']) ? sanitize_text_field($_POST['Submit']) : false; 

      //  $apiKeyModel = (isset($_POST['apiKeySettings']) && 'true' == $_POST['apikeySettings'])  ? true : false;

      // @todo ApiKeyModel will not really work, for no autosave/ public save, only via keychecks. Will be an issue when updating redirectedSettings, probably move back to settings where it was.
        if ($setting_name !== false && $new_value !== false)
        {
        //    $model = ($apiKeyModel) ? $this->keyModel : $this->model;
            $model = $this->model;
            if ($model->exists($setting_name))
            {
              if ('remove' == $submit_name)
              {
                 $this->model->deleteOption($setting_name);
              }
              else
              {
                 $this->model->$setting_name = $new_value;
              }
              
            }
        }
        

        $this->doRedirect();
      }

			public function action_debug_redirectBulk()
			{
				$this->checkPost(false);

				QueueController::resetQueues();

				$action = isset($_REQUEST['bulk']) ? sanitize_text_field($_REQUEST['bulk']) : null;

        if ('restoreAI' == $action)
        {
          $this->doRedirect('bulk-restoreAI');
        }
			}

      /** Button in part-debug, routed via custom Action */
      public function action_debug_resetStats()
      {
          $this->loadEnv();
					$this->checkPost(false);
          $statsController = StatsController::getInstance();
          $statsController->reset();
					$this->doRedirect('reload');
      }

      public function action_debug_resetquota()
      {

          $this->loadEnv();
					$this->checkPost(false);
          $quotaController = QuotaController::getInstance();
          $quotaController->forceCheckRemoteQuota();
					$this->doRedirect('reload');
      }

      public function action_debug_resetNotices()
      {
          $this->loadEnv();
					$this->checkPost(false);
          Notice::resetNotices();
          $nControl = new Notice(); // trigger reload.
					$this->doRedirect('reload');
      }

			public function action_debug_triggerNotice()
			{
				$this->checkPost(false);
				$key = isset($_REQUEST['notice_constant']) ? sanitize_text_field($_REQUEST['notice_constant']) : false;

				if ($key !== false)
				{
					$adminNoticesController = AdminNoticesController::getInstance();

					if ($key == 'trigger-all')
					{
						$notices = $adminNoticesController->getAllNotices();
						foreach($notices as $noticeObj)
						{
							 $noticeObj->addManual();
						}
					}
					else
					{
						$model = $adminNoticesController->getNoticeByKey($key);
						if (is_object($model))
							$model->addManual();
					}
				}
				$this->doRedirect();
			}

			public function action_debug_resetQueue()
			{
				 $queue = isset($_REQUEST['queue']) ? sanitize_text_field($_REQUEST['queue']) : null;

				 $this->loadEnv();
				 $this->checkPost(false);

         $uninstall = isset($_REQUEST['use_uninstall']) ? true : false;

				 if (! is_null($queue))
				 {
					 	 	$opt = new QueueController();

              if (true === $uninstall)
              {
                  Log::addDebug("Using Debug UnInstall");
                  QueueController::uninstallPlugin();
                  $this->doRedirect('');
              }
				 		 	$statsMedia = $opt->getQueue('media');

              $opt = new QueueController(['is_bulk' => true]);


							$bulkMedia = $opt->getQueue('media');

							$queues = array('media' => $statsMedia, 'mediaBulk' => $bulkMedia);

					   if ( strtolower($queue) == 'all')
						 {
							  foreach($queues as $q)
								{
										$q->resetQueue();
								}
						 }
						 else
						 {
							 	$queues[$queue]->resetQueue();
						 }

						 if ($queue == 'all')
						 {
						 	$message = sprintf(__('All items in the queues have been removed and the process is stopped', 'shortpixel-image-optimiser'));
						 }
						 else
						 {
								 $message = sprintf(__('All items in the %s queue have been removed and the process is stopped', 'shortpixel-image-optimiser'), $queue);
 						 }

						 Notice::addSuccess($message);
			 }

				$this->doRedirect('reload');
			}

			public function action_debug_removePrevented()
			{
				$this->loadEnv();
				$this->checkPost(false);

				global $wpdb;
				$sql = 'delete from ' . $wpdb->postmeta . ' where meta_key = %s';

				$sql = $wpdb->prepare($sql, '_spaatg_prevent_optimize');

				$wpdb->query($sql);

				$message = __('Item blocks have been removed. It is recommended to create a backup before trying to optimize image.', 'shortpixel-image-optimiser');

				Notice::addSuccess($message);
				$this->doRedirect();
			}

			public function action_debug_removeProcessorKey()
			{
				$this->checkPost(false);

				$cacheControl = new CacheController();
				$cacheControl->deleteItem('bulk-secret');
				exit('reloading settings would cause processorKey to be set again. Navigate away');
			}

      protected function processSave()
      {
          // write checked and verified post data to model. With normal models, this should just be call to update() function
          foreach($this->postData as $name => $value)
          {
            $this->model->{$name} = $value;
          }

					// Check at the model if any checkboxes are not checked.
					$data = $this->model->getData();

					foreach($data as $name => $value)
					{
							$type = $this->model->getType($name);
							if ('boolean' === $type )
							{
                if( ! isset($this->postData[$name]))
                {
								  $this->model->{$name} = false;
                }
                else
                {
                   $this->model->{$name} = true; 
                }
							}
					}

					// Every save, force load the quota. One reason, because of the HTTP Auth settings refresh.
					$this->loadQuotaData(true);
          // end

					if ($this->do_redirect)
					{
            $this->doRedirect('bulk');
					}
					elseif (false === $this->is_ajax_save) {

						$noticeController = Notice::getInstance();
						$notice = Notice::addSuccess(__('Settings Saved', 'shortpixel-image-optimiser'));
						$notice->is_removable = false;
						$noticeController->update();


          }
					  $this->doRedirect();
      }

      /* Loads the view data and the view */
      public function load_settings()
      {
         $this->view->data = (Object) $this->model->getData();

				 $this->loadAPiKeyData();

         if ($this->keyModel->is_verified()) // supress quotaData alerts when handing unset API's.
          $this->loadQuotaData();
        else
          InstallHelper::checkTables();

         $this->view->is_unlimited =  (!is_null($this->quotaData) && $this->quotaData->unlimited) ? true : false;

         require_once( ABSPATH . 'wp-admin/includes/translation-install.php' );
         $this->view->languages = wp_get_available_translations();
        
         $this->view->hide_banner = false; 
         $bool = apply_filters('shortpixel/settings/no_banner', false);
         if (true === $bool )
            $this->view->hide_banner = true; 

         if ( defined('SPAATG_NO_BANNER') && SPAATG_NO_BANNER == true)
         {
           $this->view->hide_banner = true; 
         }
          

         // Set viewMode
				 if (false === $this->view->key->is_verifiedkey)
				 {
					 	$view_mode = 'onboarding';
						$this->display_part = 'nokey';
				 }
				 else {
					 $view_mode = get_user_option('spaatg-settings-mode');
	         if (false === $view_mode)
           {
	          $view_mode = $this->view_mode;
           }

				 }

				 $this->view_mode = $view_mode;

				 $this->loadView('view-settings');
      }

			protected function loadAPiKeyData()
			{
				 $keyController = ApiKeyController::getInstance();

				 $keyObj = new \stdClass;
//				 $this->view->key = new \stdClass;
				 // $this->keyModel->loadKey();

				 $keyObj->is_verifiedkey = $this->keyModel->is_verified();
				 $keyObj->is_constant_key = $this->keyModel->is_constant();
				 $keyObj->hide_api_key = $this->keyModel->is_hidden();
				 $keyObj->apiKey = $keyController->getKeyForDisplay();
         $keyObj->validation_error_message = $this->keyModel->getValidationErrorMessage();
        // $keyObj->redirectedSettings =

				 $showApiKey = false;

				 if (true === $keyObj->hide_api_key)
				 {
					  $keyObj->apiKey = '***************';
				 }
				 elseif($this->is_multisite && $keyObj->is_constant_key)
				 {
					 $keyObj->apiKey = esc_html__('Multisite API Key','shortpixel-image-optimiser');
				 }
				 else {
				 	 $showApiKey = true;
				 }

				 $canValidate = false;

				 $keyObj->is_editable = (! $keyObj->is_constant_key && $showApiKey) ? true : false; ;
				 $keyObj->can_validate = $canValidate;

				 $this->view->key = $keyObj;
			}

      /** Checks on things and set them for information. */
      protected function loadEnv()
      {
          $env = wpSPAATG()->env();

          $this->has_image_library = ($env->is_gd_installed || $env->is_imagick_installed); // Any library 
          $this->is_curl_installed = $env->is_curl_installed;

          $this->is_multisite = $env->is_multisite;
          $this->is_mainsite = $env->is_mainsite;

          $this->disable_heavy_features = (false === \wpSPAATG()->env()->useVirtualHeavyFunctions()) ? true : false;

          $this->display_part = (isset($_GET['part']) && in_array($_GET['part'], $this->all_display_parts) ) ? sanitize_text_field($_GET['part']) : 'account';
      }

      protected function settingLink($args)
      {
          $defaults = [
             'part' => '',
             'title' => __('Title', 'shortpixel-image-optimiser'),
             'icon' => false,
             'icon_position' => 'left',
             'class' => 'anchor-link',

          ];

          $args = wp_parse_args($args, $defaults);

          $link = esc_url(admin_url('options-general.php?page=wp-spaatg-settings&part=' . $args['part'] ));
          $active = ($this->display_part == $args['part']) ? ' active ' : '';

          $title = $args['title'];

          $class = $active . $args['class'];

          if (false !== $args['icon'])
          {
             $icon  = '<i class="' . esc_attr($args['icon']) . '"></i>';
             if ($args['icon_position'] == 'left')
               $title = $icon . $title;
             else
               $title = $title . $icon;
          }

          $html = sprintf('<a href="%s" class="%s" data-menu-link="%s" %s >%s</a>', $link, $class, $args['part'], $active, $title);

          return $html;
      }

			// @param Force.  needed on settings save because it sends off the HTTP Auth
      protected function loadQuotaData($force = false)
      {
        $quotaController = QuotaController::getInstance();

				if ($force === true)
				{
					 $quotaController->forceCheckRemoteQuota();
					 $this->quotaData = null;
				}

        if (is_null($this->quotaData))
          $this->quotaData = $quotaController->getQuota(); //$this->shortPixel->checkQuotaAndAlert();


        $quotaData = $this->quotaData;

        $remainingImages = $quotaData->total->remaining; // $quotaData['APICallsRemaining'];
        $remainingImages = ( $remainingImages < 0 ) ? 0 : $this->formatNumber($remainingImages, 0);

        $this->view->quotaData = $quotaData;
        $this->view->remainingImages = $remainingImages;

      }


			/** This is done before handing it off to the parent controller, to sanitize and check against model.
			* @param $post Array (raw) $_POST object
			**/
      protected function processPostData($post)
      {
          if (isset($post['display_part']) && strlen($post['display_part']) > 0)
          {
              $this->display_part = sanitize_text_field($post['display_part']);
          }

          // analyse the save button
          if (isset($post['save-bulk']))
          {
            $this->do_redirect = true;
          }

					$check_key = false;

          if (false === $this->keyModel->is_constant())
          {
              $currentKey = $this->keyModel->getKey();
              $postedApiKey = isset($post['apiKey']) ? sanitize_text_field($post['apiKey']) : null;
              $postedLoginApiKey = isset($post['login_apiKey']) ? sanitize_text_field($post['login_apiKey']) : null;

              // When the onboarding field is changed, the main settings form still carries the stored API key.
              if (! is_null($postedLoginApiKey) && $postedLoginApiKey !== $currentKey
                  && (is_null($postedApiKey) || $postedApiKey === $currentKey))
              {
                  $post['apiKey'] = $postedLoginApiKey;
              }
          }

          if (isset($post['apiKey']) && false === $this->keyModel->is_constant())
					{
							$check_key = sanitize_text_field($post['apiKey']);
		          $this->keyModel->resetTried(); // reset the tried api keys on a specific post request.
              $this->keyModel->checkKey($check_key);

            if (false === $this->keyModel->is_verified())
            {
                $this->doRedirect('reload');
            }
            unset($post['apiKey']); // unset, since keyModel does the saving.

          }

        if (false === isset($post['enable_ai']))
        {
             if (isset($post['autoAI']))
             {
                unset($post['autoAI']);
             }
             if (isset($post['autoAIBulk']))
             {
                unset($post['autoAIBulk']);
             }
        }

        
				// Field that are in form for other purpososes, but are not part of model and should not be saved.
					$ignore_fields = array(
							'display_part',
							'save-bulk',
							'save',
							'removeExif',
							'png2jpgForce',
							'sp-nonce',
							'_wp_http_referer',
							'validate', // validate button from nokey part
							'new-index',
							'edit-exclusion',
							'exclusion-type',
							'exclusion-value',
							'exclusion-minwidth',
							'exclusion-maxwidth',
							'exclusion-minheight',
							'exclusion-maxheight',
							'exclusion-width',
							'exclusion-height',
              'exclusion-filesize-value',
              'exclusion-filesize-denom',
              'exclusion-filesize-operator',
							'apply-select',
							'screen_action',
							'tools-nonce',
							'confirm',
							'tos',  // toss checkbox in nokey
							'pluginemail',
              'nonce',
              'action',
              'form-nonce',
              'request_url', 
              'login_apiKey',
              'ajaxSave',
              'ai_preview_image_id',

					);

					foreach($ignore_fields as $ignore)
					{
						 if (isset($post[$ignore]))
						 {
						 		unset($post[$ignore]);
						 }
					}

          parent::processPostData($post);

      }

			/**
			* Each form save / action results in redirect
			*
			**/
      protected function doRedirect($redirect = 'self')
      {

        $url = null;


        if ($redirect == 'self'  || $redirect == 'reload')
        {
          if (true === $this->is_ajax_save)
          {
              $url = $this->url;
          }
          else {
            $url = esc_url_raw(add_query_arg('part', $this->display_part, $this->url));
            $url = remove_query_arg('noheader', $url); // has url
            $url = remove_query_arg('sp-action', $url); // has url
          }
        }
        elseif('bulk' == $redirect )
        {
          $url = admin_url("upload.php?page=wp-spaatg-bulk");
        }
        elseif ('bulk-restoreAI' == $redirect)
        {
            $url = admin_url('upload.php?page=wp-spaatg-bulk&panel=bulk-restoreAI');
        }

        if (true === $this->is_ajax_save)
				{
					$this->handleAjaxSave($redirect, $url);
				}

        wp_redirect($url);
        exit();
      }

			protected function handleAjaxSave($redirect, $url = false)
			{
						// Intercept new notices and add them
						// Return JSON object with status of save action
						$json = new \stdClass;
						$json->result = true;


							$noticeController = Notice::getInstance();

							$json->notices = $noticeController->getNewNotices();
              $json->key_verified = $this->keyModel->is_verified();
							if(count($json->notices) > 0)
							{
								$json->display_notices = [];
							foreach($json->notices as $notice)
							{
								$json->display_notices[] = $notice->getForDisplay(['class' => 'is_ajax', 'is_removable' => false]);
							}
						}
						if ($redirect !== 'self')
						{
              $json->redirect = ($url !== false && ! is_null($url) ) ? $url : $redirect;
						}

            if (count($this->returnFormData) > 0)
            {
               $json->returnFormData = $this->returnFormData;
            }

						$noticeController->update(); // dismiss one-time ponies
						wp_send_json($json);
						exit();
			}



}
