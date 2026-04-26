<?php
namespace SPAATG\Controller;

if ( ! defined( 'ABSPATH' ) ) {
 exit; // Exit if accessed directly.
}

use SPAATG\Controller\Optimizer\OptimizeAiController;
use SPAATG\ShortPixelLogger\ShortPixelLogger as Log;
use SPAATG\Controller\Queue\Queue as Queue;

use SPAATG\Model\AiDataModel;

use SPAATG\Model\AccessModel as AccessModel;


/* AdminController is meant for handling events, hooks, filters in WordPress where there is *NO* specific or more precise  ShortPixel Page active.
*
* This should be a delegation class connection global hooks and such to the best shortpixel handler.
*/
class AdminController extends \SPAATG\Controller
{
    protected static $instance;

    public static function getInstance()
    {
      if (is_null(self::$instance))
          self::$instance = new AdminController();

      return self::$instance;
    }

			public function registerMediaBulkActions($actions)
		{
			$optimizeAiController = OptimizeAiController::getInstance();
			if (false === $optimizeAiController->isAiEnabled())
			{
				return $actions;
			}

			$actions['spaatg-generateai'] = __('Generate image SEO data', 'shortpixel-image-optimiser');
			$actions['spaatg-revertai'] = __('Revert AI SEO data', 'shortpixel-image-optimiser');

			return $actions;
		}

		public function handleMediaBulkActions($redirect_to, $doaction, $post_ids)
		{
			if ('spaatg-generateai' !== $doaction && 'spaatg-revertai' !== $doaction)
			{
				return $redirect_to;
			}

			$fs = \wpSPAATG()->filesystem();
			$accessModel = AccessModel::getInstance();
			$queued = 0;
			$reverted = 0;
			$skipped = 0;

			if ('spaatg-generateai' === $doaction)
			{
				$queueController = new QueueController();
			}

			foreach ((array) $post_ids as $post_id)
			{
				$mediaItem = $fs->getImage((int) $post_id, 'media');

				if (! is_object($mediaItem) || false === $accessModel->imageIsEditable($mediaItem))
				{
					$skipped++;
					continue;
				}

				$aiDataModel = AiDataModel::getModelByAttachment($mediaItem->get('id'));
				if ('spaatg-revertai' === $doaction)
				{
					if (true === $aiDataModel->isSomeThingGenerated())
					{
						$aiDataModel->revert();
						AiDataModel::flushModelCache($mediaItem->get('id'));
						$reverted++;
					}
					else
					{
						$skipped++;
					}

					continue;
				}

				if (false === $aiDataModel->isProcessable())
				{
					$skipped++;
					continue;
				}

				$inQueue = $queueController->isItemInQueue($mediaItem, 'requestAlt');
				if (QueueController::IN_QUEUE_ACTION_ADDED === $inQueue)
				{
					$queued++;
					continue;
				}
				if (QueueController::IN_QUEUE_SKIPPED === $inQueue)
				{
					$skipped++;
					continue;
				}

				$result = $queueController->addItemToQueue($mediaItem, ['action' => 'requestAlt']);
				if (is_object($result) && (! property_exists($result, 'is_error') || false === $result->is_error))
				{
					$queued++;
				}
				else
				{
					$skipped++;
				}
			}

			$redirect_to = remove_query_arg(['spaatg_bulk_ai_action', 'spaatg_bulk_ai_queued', 'spaatg_bulk_ai_reverted', 'spaatg_bulk_ai_skipped'], $redirect_to);

			return add_query_arg([
				'spaatg_bulk_ai_action' => ('spaatg-revertai' === $doaction) ? 'revert' : 'generate',
				'spaatg_bulk_ai_queued' => $queued,
				'spaatg_bulk_ai_reverted' => $reverted,
				'spaatg_bulk_ai_skipped' => $skipped,
			], $redirect_to);
		}

		public function displayMediaBulkActionNotice()
		{
			if (! isset($_GET['spaatg_bulk_ai_queued']) && ! isset($_GET['spaatg_bulk_ai_reverted']) && ! isset($_GET['spaatg_bulk_ai_skipped']))
			{
				return;
			}

			$screen = get_current_screen();
			if (! is_object($screen) || 'upload' !== $screen->id)
			{
				return;
			}

			$queued = isset($_GET['spaatg_bulk_ai_queued']) ? absint(wp_unslash($_GET['spaatg_bulk_ai_queued'])) : 0;
			$reverted = isset($_GET['spaatg_bulk_ai_reverted']) ? absint(wp_unslash($_GET['spaatg_bulk_ai_reverted'])) : 0;
			$skipped = isset($_GET['spaatg_bulk_ai_skipped']) ? absint(wp_unslash($_GET['spaatg_bulk_ai_skipped'])) : 0;
			$action = isset($_GET['spaatg_bulk_ai_action']) ? sanitize_key(wp_unslash($_GET['spaatg_bulk_ai_action'])) : '';

			if ($queued > 0)
			{
				$message = sprintf(
					_n('%s image was queued for AI SEO generation.', '%s images were queued for AI SEO generation.', $queued, 'shortpixel-image-optimiser'),
					number_format_i18n($queued)
				);
				printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html($message));
			}

			if ($reverted > 0)
			{
				$message = sprintf(
					_n('%s image had its AI SEO data reverted.', '%s images had their AI SEO data reverted.', $reverted, 'shortpixel-image-optimiser'),
					number_format_i18n($reverted)
				);
				printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html($message));
			}

			if ($skipped > 0)
			{
				if ('revert' === $action)
				{
					$message = sprintf(
						_n('%s image was skipped because it has no AI SEO data to revert or cannot be edited.', '%s images were skipped because they have no AI SEO data to revert or cannot be edited.', $skipped, 'shortpixel-image-optimiser'),
						number_format_i18n($skipped)
					);
				}
				else
				{
					$message = sprintf(
						_n('%s image was skipped because it already has SEO data, is already queued, or cannot be processed.', '%s images were skipped because they already have SEO data, are already queued, or cannot be processed.', $skipped, 'shortpixel-image-optimiser'),
						number_format_i18n($skipped)
					);
				}
				printf('<div class="notice notice-info is-dismissible"><p>%s</p></div>', esc_html($message));
			}
		}

    /** Handle AI processing on upload image 
     * 
     * @param mixed $meta 
     * @param mixed $id 
     * @return mixed 
     */
    public function handleAiImageUploadHook($meta, $id)
    {
        $fs = \wpSPAATG()->filesystem();
				$fs->flushImageCache(); // it's possible file just changed by external plugin.
        $mediaItem = $fs->getImage($id, 'media');

				if ($mediaItem === false)
				{
					 Log::addError('Handle Image Upload Hook triggered, by error in image :' . $id );
					 return $meta;
				}

         $queueController = new QueueController();

        
        $args = ['action' => 'requestAlt'];
        $queueController->addItemToQueue($mediaItem, $args); 
         
        return $meta;
    }

    /* Function to process Hook coming from the WP cron system */
    public function processCronHook($bulk)
    {
       // Cron shenenigans
        if (is_array($bulk) && isset($bulk['bulk']))
        {
           $bulk = $bulk['bulk'];
        }

        $args = array(
            'max_runs' => 10,
            'run_once' => false,
            'bulk' => $bulk,
            'source' => 'cron',
            'timelimit' => 50,
            'wait' => 1,
        );


        return $this->processQueueHook($args);
    }

		public function processQueueHook($args = array())
		{
				$defaults = array(
					'wait' => 3, // amount of time to wait for next round. Prevents high loads
					'run_once' => false, //  If true queue must be run at least every few minutes. If false, it tries to complete all.
						'queues' => array('media'),
					'bulk' => false, // changing this might change important behavior
          'max_runs' => -1, // if < 0 run until end, otherwise cut out at some point.
          'source' => 'hook', // not used but can be used in the filter to see what type of job is running
          'timelimit' => false, //timelimit in seconds or false
				);

				if (wp_doing_cron())
				{
					 $this->loadCronCompat();
				}

				$args = wp_parse_args($args, $defaults);
        $args = apply_filters('shortpixel/process_hook/options', $args);

        $queueArgs = []; 
				if (true == $args['bulk'])
				{
					 $queueArgs['is_bulk'] = true;
				}


			  $control = new QueueController($queueArgs);
        $env = \wpSPAATG()->env();

			 	if ($args['run_once'] === true)
				{
					 return	$control->processQueue($args['queues']);
				}

				$running = true;
				$i = 0;
        $max_runs = $args['max_runs'];
        $timelimit = $args['timelimit'];

				while($running)
				{
							 	$results = $control->processQueue($args['queues']);
								$running = false;

								foreach($args['queues'] as $qname)
								{
									  if (property_exists($results, $qname))
										{
											  $result = $results->$qname;
												// If Queue is not completely empty, there should be something to do.
												if ($result->qstatus != QUEUE::RESULT_QUEUE_EMPTY)
												{
													 $running = true;
													 continue;
												}
										}
								}

              $i++;
              if($max_runs > 0 && $i >= $max_runs)
              {
                 break;
              }
              if ($timelimit !== false && true === $env->IsOverTimeLimit(['limit' => $timelimit]))
              {
                 Log::addDebug('Hook: over timelimit detected, returning', $timelimit);
                 break;
              }
							sleep($args['wait']);
				}
		}

			// WP functions that are not loaded during Cron Time.
		protected function loadCronCompat()
		{
			  if (false === function_exists('download_url'))
				{
					 include_once(ABSPATH . "wp-admin/includes/admin.php");
				}

         if (false === function_exists('wp_generate_attachment_metadata'))
         {
           include_once(ABSPATH . 'wp-admin/includes/image.php' );
         }
		}

    public function generatePluginLinks($links) {
        $in = '<a href="options-general.php?page=wp-spaatg-settings">Settings</a>';
        array_unshift($links, $in);
        return $links;
    }



    /** Displays an icon in the toolbar when processing images
    *   hook - admin_bar_menu
    *  @param Obj $wp_admin_bar
    */
    public function toolbar_spaatg_processing( $wp_admin_bar ) {

        if (! \wpSPAATG()->env()->is_screen_to_use )
          return; // not ours, don't load JS and such.

        $settings = \wpSPAATG()->settings();
        $access = AccessModel::getInstance();
				$quotaController = QuotaController::getInstance();

        $extraClasses = " shortpixel-hide";
        /*translators: toolbar icon tooltip*/
        $id = 'spaatg-notice-toolbar';
        $tooltip = __('ShortPixel AI Alt Text Generator processing...','shortpixel-image-optimiser');
        $icon = "shortpixel.png";
        $successLink = $link = admin_url(current_user_can( 'edit_others_posts')? 'upload.php?page=wp-spaatg-bulk' : 'upload.php');
        $blank = "";

        if($quotaController->hasQuota() === false)
				{
            $extraClasses = " shortpixel-alert shortpixel-quota-exceeded";
            /*translators: toolbar icon tooltip*/
            $id = 'spaatg-notice-exceed';
            $tooltip = '';

            if ($access->userIsAllowed('quota-warning'))
            {
              $exceedTooltip = __('ShortPixel quota exceeded. Click for details.','shortpixel-image-optimiser');
              //$link = "http://shortpixel.com/login/" . $this->_settings->apiKey;
              $link = "options-general.php?page=wp-spaatg-settings";
            }
            else {
              $exceedTooltip = __('ShortPixel quota exceeded. Click for details.','shortpixel-image-optimiser');
              //$link = "http://shortpixel.com/login/" . $this->_settings->apiKey;
              $link = false;
            }
        }

        $args = array(
                'id'    => 'spaatg_processing',
                'title' => '<div id="' . $id . '" title="' . $tooltip . '"><span class="stats hidden">0</span><img alt="' . __('ShortPixel icon','shortpixel-image-optimiser') . '" src="'
                         . plugins_url( 'res/img/'.$icon, SPAATG_PLUGIN_FILE ) . '" success-url="' . $successLink . '"><span class="shp-alert">!</span>'
                         . '<div class="controls">
                              <span class="dashicons dashicons-controls-pause pause" title="' . __('Pause', 'shortpixel-image-optimiser') . '">&nbsp;</span>
                              <span class="dashicons dashicons-controls-play play" title="' . __('Resume', 'shortpixel-image-optimiser') . '">&nbsp;</span>
                            </div>'

                         .'<div class="cssload-container"><div class="cssload-speeding-wheel"></div></div></div>',
    //            'href'  => 'javascript:void(0)', // $link,
                'meta'  => array('target'=> $blank, 'class' => 'spaatg-toolbar-processing' . $extraClasses)
        );
        $wp_admin_bar->add_node( $args );

        if($quotaController->hasQuota() === false)
				{
            $wp_admin_bar->add_node( array(
                'id'    => 'spaatg_processing-title',
                'parent' => 'spaatg_processing',
                'title' => $exceedTooltip,
                'href'  => $link
            ));

        }
    }

} // class
