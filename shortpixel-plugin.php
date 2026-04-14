<?php
namespace SPAATG;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use SPAATG\ShortPixelLogger\ShortPixelLogger as Log;
use SPAATG\Notices\NoticeController as Notices;
use SPAATG\Controller\QueueController as QueueController;
use SPAATG\Controller\QuotaController as QuotaController;
use SPAATG\Controller\AjaxController as AjaxController;
use SPAATG\Controller\AdminController as AdminController;
use SPAATG\Controller\ImageEditorController as ImageEditorController;
use SPAATG\Controller\ApiKeyController as ApiKeyController;
use SPAATG\Controller\FileSystemController;
use SPAATG\Controller\Optimizer\OptimizeAiController;
use SPAATG\Controller\OtherMediaController as OtherMediaController;
use SPAATG\NextGenController as NextGenController;

use SPAATG\Controller\Queue\MediaLibraryQueue as MediaLibraryQueue;
use SPAATG\Controller\Queue\CustomQueue as CustomQueue;

use SPAATG\Helper\InstallHelper as InstallHelper;
use SPAATG\Helper\UiHelper as UiHelper;

use SPAATG\Model\AccessModel as AccessModel;
use SPAATG\Model\SettingsModel as SettingsModel;

/** Plugin class
 * This class is meant for: WP Hooks, init of runtime and Controller Routing.
 */
class ShortPixelPlugin {

	private static $instance;
	protected static $modelsLoaded = array(); // don't require twice, limit amount of require looksups..

	protected $is_noheaders = false;

	protected $plugin_path;
	protected $plugin_url;

	protected $shortPixel; // shortpixel megaclass

	protected $admin_pages = array();  // admin page hooks.

	public function __construct() {
		// $this->initHooks();
		add_action( 'plugins_loaded', [$this, 'lowInit'], 5 ); // early as possible init.
		
	}

	/** LowInit after all Plugins are loaded. Core WP function can still be missing. This should mostly add hooks */
	public function lowInit() {

		$this->plugin_path = plugin_dir_path( SPAATG_PLUGIN_FILE );
		$this->plugin_url  = plugin_dir_url( SPAATG_PLUGIN_FILE );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended  -- This is not a form
		if ( isset( $_REQUEST['noheader'] ) ) {
			$this->is_noheaders = true;
		}

		/*
		Filter to prevent SPIO from starting. This can be used by third-parties to prevent init when needed for a particular situation.
		* Hook into plugins_loaded with priority lower than 5 */
		$init = apply_filters( 'shortpixel/plugin/init', true );

		if (false === $init ) {
			return;
		}


		$front        = new Controller\FrontController(); // init front checkers
		$admin        = Controller\AdminController::getInstance();
		$adminNotices = Controller\AdminNoticesController::getInstance(); // Hook in the admin notices.

//		$this->initHooks();
		$this->ajaxHooks();

		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			WPCliController::getInstance();
		}

		add_action ('init', [$this, 'init']);
		add_action('init', [$this, 'initHooks']);
		add_action( 'admin_init', [ $this, 'admin_init' ] );
	}

	public function init()
	{
		Controller\CronController::getInstance();  // cron jobs - must be init to function!

		$access = AccessModel::getInstance();

		$isAdminUser = $access->userIsAllowed('is_admin_user');
	
		if ( $isAdminUser ) {
			// toolbar notifications

			// deactivate conflicting plugins if found
			add_action( 'admin_post_spaatg_deactivate_conflict_plugin', array( '\SPAATG\Helper\InstallHelper', 'deactivateConflictingPlugin' ) );

			// only if the key is not yet valid or the user hasn't bought any credits.
			// @todo This should not be done here.
			$settings     = $this->settings();
			$stats        = $settings->currentStats;
			$totalCredits = isset( $stats['APICallsQuotaNumeric'] ) ? $stats['APICallsQuotaNumeric'] + $stats['APICallsQuotaOneTimeNumeric'] : 0;
			$keyControl = ApiKeyController::getInstance();


			if ( true || false === $keyControl->keyIsVerified() || $totalCredits < 4000 ) {
				require_once 'class/view/shortpixel-feedback.php';
				new ShortPixelFeedback( SPAATG_PLUGIN_FILE, 'shortpixel-image-optimiser' );
			}
		}
		
	}


	/** Mainline Admin Init. Tasks that can be loaded later should go here */
	public function admin_init() {
			// This runs activation thing. Should be -after- init
			$this->check_plugin_version();


			$notices             = Notices::getInstance(); // This hooks the ajax listener
			$quotaController = QuotaController::getInstance();
			$quotaController->getQuota();

			/* load_plugin_textdomain( 'shortpixel-image-optimiser', false, plugin_basename( dirname( SPAATG_PLUGIN_FILE ) ) . '/lang' ); */
	}

	/** Function to get plugin settings
     *
     * @return SettingsModel The settings model object.
     */
	public function settings() {
			return SettingsModel::getInstance();
	}

	/** Function to get all enviromental variables
     *
     * @return EnvironmentModel
     */
	public function env() {
		return Model\EnvironmentModel::getInstance();
	}

	/** Get the SPIO FileSystemController
	 * 
	 * @return FileSystemController 
	 */
	public function fileSystem() {
		return new Controller\FileSystemController();
	}

	/** Create instance. This should not be needed to call anywhere else than main plugin file
     * This should not be called *after* plugins_loaded action
     **/
	public static function getInstance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new ShortPixelPlugin();
		}
		return self::$instance;

	}

	/** Hooks for all WordPress related hooks
     * For now hooks in the lowInit, asap.
     */
	public function initHooks() {

		add_action( 'admin_menu', array( $this, 'admin_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) ); // admin scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) ); // admin styles
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts' ), 90 ); // loader via route.
		add_action( 'enqueue_block_assets', array($this, 'load_admin_scripts'), 90);
		// defer notices a little to allow other hooks ( notable adminnotices )

		$queueController = new QueueController();
		add_action( 'shortpixel-thumbnails-regenerated', array( $queueController, 'thumbnailsChangedHookLegacy' ), 10, 4 );
		add_action( 'rta/image/thumbnails_regenerated', array( $queueController, 'thumbnailsChangedHook' ), 10, 2 );
		add_action( 'rta/image/thumbnails_removed', array( $queueController, 'thumbnailsChangedHook' ), 10, 2 );
		add_action('rta/image/scaled_image_regenerated', array($queueController, 'scaledImageChangedHook'), 10, 2);


		// Media Library - Actions to route screen
		add_action( 'load-upload.php', array( $this, 'route' ) );
		add_action( 'load-post.php', array( $this, 'route' ) );

		$admin = AdminController::getInstance();
		$imageEditor = ImageEditorController::getInstance();

		// Handle for EMR
		add_action( 'wp_handle_replace', array( $admin, 'handleReplaceHook' ) );
		add_action( 'admin_init', array( $admin, 'removeEmrFeatureNotice' ), 999 );
		add_action( 'admin_footer', array( $admin, 'removeEmrFeatureNotice' ), 999 );

		// Action / hook for who wants to use CRON. Please refer to manual / support to prevent loss of credits.
		add_action( 'shortpixel/hook/processqueue', array( $admin, 'processQueueHook' ) );
		add_action( 'shortpixel/hook/scancustomfolders', array($admin, 'scanCustomFoldersHook'));

		// Action for media library gallery view
		//add_filter('attachment_fields_to_edit', array($admin, 'editAttachmentScreen'), 10, 2);
		add_action('print_media_templates', array($admin, 'printComparer'));

		// Placeholder function for heic and such, return placeholder URL in image to help w/ database replacements after conversion.
		add_filter('wp_get_attachment_url', array($admin, 'checkPlaceHolder'), 10, 2);
		add_filter('media_row_actions', array($admin, 'filterMediaRowActions'), 999, 2);
		add_filter('manage_media_columns', array($admin, 'filterMediaColumns'), 999, 1);
		add_filter('bulk_actions-upload', array($admin, 'registerMediaBulkActions'));
		add_filter('handle_bulk_actions-upload', array($admin, 'handleMediaBulkActions'), 10, 3);
		add_action('admin_notices', array($admin, 'displayMediaBulkActionNotice'));

		add_filter('rest_post_dispatch', [$admin, 'checkRestMedia'],10, 3);

		/** When automagically process images when uploaded is on */
		if ( $this->env()->is_autoprocess ) {
			// compat filter to shortcircuit this in cases.  (see external - visualcomposer)
			if ( apply_filters( 'shortpixel/init/automedialibrary', true ) ) {

      			add_action( 'shortpixel-thumbnails-before-regenerate', array( $admin, 'preventImageHook' ), 10, 1 );

				add_action( 'enable-media-replace-upload-done', array( $admin, 'handleReplaceEnqueue' ), 10, 3 );

				add_filter( 'wp_generate_attachment_metadata', array( $admin, 'handleImageUploadHook' ), 5, 2 );
				add_action('add_attachment', array($admin, 'addAttachmentHook'));

				// @integration MediaPress
				add_filter( 'mpp_generate_metadata', array( $admin, 'handleImageUploadHook' ), 10, 2 );
			}
		}

		$optimizeAiController = OptimizeAiController::getInstance(); 
		if (true === $optimizeAiController->isAutoAiEnabled())
		{

			// Run one hit earlier than optimization, to do this action first if needed.
			add_filter( 'wp_generate_attachment_metadata', array( $admin, 'handleAiImageUploadHook' ), 4, 2 );
			add_filter( 'mpp_generate_metadata', array( $admin, 'handleAiImageUploadHook' ), 9, 2 );
		}


		$this->env()->setDefaultViewModeList();// set default mode as list. only @ first run

		add_filter( 'plugin_action_links_' . plugin_basename( SPAATG_PLUGIN_FILE ), array( $admin, 'generatePluginLinks' ) );// for plugin settings page

		// for cleaning up the WebP images when an attachment is deleted . Loading this early because it's possible other plugins delete files in the uploads, but we need those to remove backups.
		add_action( 'delete_attachment', array( $admin, 'onDeleteAttachment' ), 5 );
		add_action( 'mime_types', array( $admin, 'addMimes' ) );

		// integration with WP/LR Sync plugin
		//add_action( 'wplr_update_media', array( AjaxController::getInstance(), 'onWpLrUpdateMedia' ), 10, 2 );
		add_action( 'wplr_sync_media', array( AjaxController::getInstance(), 'onWpLrSyncMedia' ), 10, 2 );

		add_action( 'admin_bar_menu', array( $admin, 'toolbar_spaatg_processing' ), 999 );

		// Image Editor Actions
		add_filter('load_image_to_edit_path', array($imageEditor, 'getImageForEditor'), 10, 3);
		add_filter('wp_save_image_editor_file', array($imageEditor, 'saveImageFile'), 10, 5);  // hook when saving
	//	add_action('update_post_meta', array($imageEditor, 'checkUpdateMeta'), 10, 4 );


		if (is_admin())
		{
			  add_filter('pre_get_posts', array($admin, 'filter_listener'));
		}

		if ($this->env()->is_multisite)
		{
			 add_action('network_admin_menu', [$this, 'admin_network_pages']) ;
		}

	}

	protected function ajaxHooks() {

		// Ajax hooks. Should always be prepended with ajax_ and *must* check on nonce in function
		add_action( 'wp_ajax_spaatg_image_processing', array( AjaxController::getInstance(), 'ajax_processQueue' ) );

		// Custom Media

		//add_action( 'wp_ajax_spaatg_get_backup_size', array( AjaxController::getInstance(), 'ajax_getBackupFolderSize' ) );

		add_action( 'wp_ajax_spaatg_propose_upgrade', array( AjaxController::getInstance(), 'ajax_proposeQuotaUpgrade' ) );
		add_action( 'wp_ajax_spaatg_check_quota', array( AjaxController::getInstance(), 'ajax_checkquota' ) );


		add_action( 'wp_ajax_spaatg_ajaxRequest', array( AjaxController::getInstance(), 'ajaxRequest' ) );
		add_action( 'wp_ajax_spaatg_settingsRequest', array( AjaxController::getInstance(), 'settingsRequest'));

	}

	/** Hook in our admin pages */
	public function admin_pages() {
		$admin_pages = array();
		// settings page
		$admin_pages[] = add_options_page( __( 'ShortPixel AI Alt Text Generator', 'shortpixel-image-optimiser' ), __( 'ShortPixel AI Alt Text Generator', 'shortpixel-image-optimiser' ), 'manage_options', 'wp-spaatg-settings', array( $this, 'route' ) );

		/*translators: title and menu name for the Bulk Processing page*/
		$admin_pages[] = add_media_page( __( 'ShortPixel AI Alt Text Generator Bulk Process', 'shortpixel-image-optimiser' ), __( 'Bulk AI Alt Text', 'shortpixel-image-optimiser' ), 'edit_others_posts', 'wp-spaatg-bulk', array( $this, 'route' ) );

		$this->admin_pages = $admin_pages;
	}

	public function admin_network_pages()
	{
	//	  	add_menu_page(__('Shortpixel MU', 'shortpixel-image-optimiser'), __('Shortpixel', 'shortpixel_image_optimiser'), 'manage_sites', 'spaatg-network-settings', [$this, 'route'], $this->plugin_url('res/img/shortpixel.png') );
	}

	/** All scripts should be registed, not enqueued here (unless global wp-admin is needed )
     *
     * Not all those registered must be enqueued however.
     */
	public function admin_scripts( $hook_suffix ) {

		$settings       = \wpSPAATG()->settings();
		$env = \wpSPAATG()->env();
		$ajaxController = AjaxController::getInstance();

		$secretKey = $ajaxController->getProcessorKey();

		$keyControl = \SPAATG\Controller\ApiKeyController::getInstance();
		$apikey     = $keyControl->getKeyForDisplay();

		$is_bulk_page = \wpSPAATG()->env()->is_bulk_page;

		$queueController = new QueueController(['is_bulk' =>  $is_bulk_page ]);
		$quotaController = QuotaController::getInstance();

		$OptimizeAiController = OptimizeAiController::getInstance(); 

		$args_footer_async = ['strategy' => 'async', 'in_footer' => true];

	 wp_register_script('spaatg-folderbrowser', plugins_url('/res/js/shortpixel-folderbrowser.js', SPAATG_PLUGIN_FILE), array(), SPAATG_IMAGE_OPTIMISER_VERSION, true );

	 wp_localize_script('spaatg-folderbrowser', 'spaatg_folderbrowser', array(
		 		'strings' => array(
						'loading' => __('Loading', 'shortpixel-image-optimiser'),
						'empty_result' => __('No Directories found that can be added to Custom Folders', 'shortpixel-image-optimiser'),
				),
				'icons' => array(
						'folder_closed' => plugins_url('res/img/filebrowser/folder-closed.svg', SPAATG_PLUGIN_FILE),
						'folder_open' => plugins_url('res/img/filebrowser/folder-closed.svg', SPAATG_PLUGIN_FILE),
				),
	 ));

		wp_register_script( 'spaatg-jquery-knob', plugins_url( '/res/js/jquery.knob.min.js', SPAATG_PLUGIN_FILE ), array(), SPAATG_IMAGE_OPTIMISER_VERSION, true );

		wp_register_script( 'spaatg-debug', plugins_url( '/res/js/debug.js', SPAATG_PLUGIN_FILE ), array( 'jquery', 'jquery-ui-draggable' ), SPAATG_IMAGE_OPTIMISER_VERSION, true );

		wp_register_script( 'spaatg-tooltip', plugins_url( '/res/js/shortpixel-tooltip.js', SPAATG_PLUGIN_FILE ), array( 'jquery' ), SPAATG_IMAGE_OPTIMISER_VERSION, true );

		$tooltip_localize = array(
			'processing' => __('Processing... ','shortpixel-image-optimiser'),
			'pause' =>  __('Click to pause', 'shortpixel-image-optimiser'),
			'resume' => __('Click to resume', 'shortpixel-image-optimiser'),
			'item' => __('item in queue', 'shortpixel-image-optimiser'),
			'items' => __('items in queue', 'shortpixel-image-optimiser'),
		);

		wp_localize_script( 'spaatg-tooltip', 'spaatg_tooltipStrings', $tooltip_localize);

		wp_register_script( 'spaatg-settings', plugins_url( 'res/js/shortpixel-settings.js', SPAATG_PLUGIN_FILE ), array('spaatg', 'spaatg-shiftselect', 'spaatg-inline-help', 'media-editor'), SPAATG_IMAGE_OPTIMISER_VERSION, true );

		wp_register_script('spaatg-shiftselect', plugins_url('res/js/shift-select.js', SPAATG_PLUGIN_FILE), array(), SPAATG_IMAGE_OPTIMISER_VERSION, true);

		wp_localize_script('spaatg-settings', 'settings_strings', UiHelper::getSettingsStrings(false));


		wp_register_script( 'spaatg-onboarding', plugins_url( 'res/js/shortpixel-onboarding.js', SPAATG_PLUGIN_FILE ), array('spaatg-settings'), SPAATG_IMAGE_OPTIMISER_VERSION, true );

		wp_register_script('spaatg-media', plugins_url('res/js/shortpixel-media.js',  SPAATG_PLUGIN_FILE), array('jquery'), SPAATG_IMAGE_OPTIMISER_VERSION, true);

		wp_register_script('spaatg-inline-help', plugins_url('res/js/shortpixel-inline-help.js',  SPAATG_PLUGIN_FILE), [], SPAATG_IMAGE_OPTIMISER_VERSION, true);
		wp_register_script('spaatg-chatbot', 
			apply_filters('shortpixel/plugin/nohelp', 'https://spcdn.shortpixel.ai/assets/js/ext/ai-chat-agent.js'), [], SPAATG_IMAGE_OPTIMISER_VERSION, $args_footer_async);

		$editor_localize = ImageEditorController::localizeScript();
		wp_localize_script('spaatg-media', 'spaatg_media', $editor_localize);

		wp_register_script( 'spaatg-processor', plugins_url( '/res/js/shortpixel-processor.js', SPAATG_PLUGIN_FILE ), array( 'jquery', 'spaatg-tooltip' ), SPAATG_IMAGE_OPTIMISER_VERSION, true );

		 // How often JS processor asks for next tick on server. Low for fastestness and high loads, high number for surviving servers.
		$interval = apply_filters( 'shortpixel/processor/interval', 3000 );

		// If the queue is empty how often to check if something new appeared from somewhere. Excluding the manual items added by current processor user.
		$deferInterval = apply_filters( 'shortpixel/process/deferInterval', 60000 );

		wp_localize_script(
            'spaatg-processor',
            'SPAATGProcessorData',
            array(
				'bulkSecret'        => $secretKey,
				'isBulkPage'        => (bool) $is_bulk_page,
				'workerURL'         => plugins_url( 'res/js/shortpixel-worker.js', SPAATG_PLUGIN_FILE ),
				'nonce_process'     => wp_create_nonce( 'processing' ),
				'nonce_exit'        => wp_create_nonce( 'exit_process' ),
				'nonce_ajaxrequest' => wp_create_nonce( 'ajax_request' ),
				'nonce_settingsrequest' => wp_create_nonce('settings_request'),
				'startData'         => ( \wpSPAATG()->env()->is_screen_to_use ) ? $queueController->getStartupData() : false,
				'interval'          => $interval,
				'deferInterval'     => $deferInterval,
				'debugIsActive' 		=> (\wpSPAATG()->env()->is_debug) ? 'true' : 'false',
				'autoMediaLibrary'  => ($settings->autoMediaLibrary) ? 'true' : 'false',
				'disable_processor' => apply_filters('shortpixel/processorjs/disable', false),
            )
        );

		//https://github.com/thedatepicker/thedatepicker
		wp_register_script('spaatg-datepicker', plugins_url('res/js/the-datepicker.min.js', SPAATG_PLUGIN_FILE),  ['wp-components', 'wp-i18n', 'wp-element', 'wp-hooks'], SPAATG_IMAGE_OPTIMISER_VERSION, true);
		

		/*** SCREENS */
		wp_register_script('spaatg-screen-base', plugins_url( '/res/js/screens/screen-base.js', SPAATG_PLUGIN_FILE ), array( 'jquery', 'spaatg-processor' ), SPAATG_IMAGE_OPTIMISER_VERSION, true );

		wp_register_script('spaatg-screen-item-base', plugins_url( '/res/js/screens/screen-item-base.js', SPAATG_PLUGIN_FILE ), array( 'jquery', 'spaatg-processor', 'spaatg-screen-base'), SPAATG_IMAGE_OPTIMISER_VERSION, true );

		wp_register_script( 'spaatg-screen-media', plugins_url( '/res/js/screens/screen-media.js', SPAATG_PLUGIN_FILE ), array( 'jquery', 'spaatg-processor', 'spaatg-screen-base', 'spaatg-screen-item-base' ), SPAATG_IMAGE_OPTIMISER_VERSION, true );

		wp_register_script( 'spaatg-screen-custom', plugins_url( '/res/js/screens/screen-custom.js', SPAATG_PLUGIN_FILE ), array( 'jquery', 'spaatg-processor', 'spaatg-screen-base', 'spaatg-screen-item-base' ), SPAATG_IMAGE_OPTIMISER_VERSION, true );

		wp_register_script( 'spaatg-screen-nolist', plugins_url( '/res/js/screens/screen-nolist.js', SPAATG_PLUGIN_FILE ), array( 'jquery', 'spaatg-processor', 'spaatg-screen-base' ), SPAATG_IMAGE_OPTIMISER_VERSION, true );

	  $screen_localize = array(  // Item Base
			'startAction' => __('Processing... ','shortpixel-image-optimiser'),
			'startActionAI' => __('Generating image SEO data', 'shortpixel-image-optimiser'),
			'fatalError' => __('ShortPixel encountered a fatal error when optimizing images. Please check the issue below. If this is caused by a bug please contact our support', 'shortpixel-image-optimiser'),
			'fatalErrorStop' => __('ShortPixel has encounted multiple errors and has now stopped processing', 'shortpixel-image-optimiser'),
			'fatalErrorStopText' => __('No items are being processed. To try again after solving the issues, please reload the page ', 'shortpixel-image-optimiser'),
			'fatalError500' => __('A fatal error HTTP 500 has occurred. On the bulk screen, this may be caused by the script running out of memory. Check your error log, increase memory or disable heavy plugins.'),

		);
	

	 $screen_localize_custom = array( // Custom Screen
			'stopActionMessage' => __('Folder scan has stopped', 'shortpixel-image-optimiser'),
		);

	 $screen_localize_media = [ 
			'hide_ai' => ! $OptimizeAiController->isAiEnabled(),  // turn around negative setting
			'hide_spaatg_in_popups' => apply_filters('shortpixel/js/media/hide_in_popups', false), 
			'modalcss' => plugins_url('res/css/shortpixel-media-modal.css', SPAATG_PLUGIN_FILE), 
			'remove_background_title' => __('AI Background Removal', 'shortpixel-image-optimiser'),
			'scale_title' => __('AI Image Upscale', 'shortpixel-image-optimiser'),
			'alt_text_label' => __('Alt Text', 'shortpixel-image-optimiser'),
			'alt_text_empty' => __('No alt text available yet.', 'shortpixel-image-optimiser'),
			'alt_text_loading' => __('Loading alt text...', 'shortpixel-image-optimiser'),
			'upscale_max_width' => 1200, // Scale X and max width pin Pixels.
			'popup_load_preview' => true, // Upon opening, load Preview or not.
			'too_big_for_scale_title'  => __('Image too big for scaling', 'shortpixel-image-optimiser'), 
			'wp_screen_id' => $env->screen_id, 
	 ];

		wp_localize_script('spaatg-screen-media', 'spaatg_mediascreen_settings', $screen_localize_media); 

		wp_localize_script( 'spaatg-screen-base', 'spaatg_screenStrings', array_merge($screen_localize, $screen_localize_custom));

		wp_register_script( 'spaatg-screen-bulk', plugins_url( '/res/js/screens/screen-bulk.js', SPAATG_PLUGIN_FILE ), array( 'jquery', 'spaatg-processor', 'spaatg-screen-base'), SPAATG_IMAGE_OPTIMISER_VERSION, true );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended  -- This is not a form
		$panel = isset( $_GET['panel'] ) ? sanitize_text_field( wp_unslash($_GET['panel']) ) : false;

		$bulkLocalize = [
			'endBulk'   => __( 'This will stop the bulk processing and take you back to the start. Are you sure you want to do this?', 'shortpixel-image-optimiser' ),
			'reloadURL' => admin_url( 'upload.php?page=wp-spaatg-bulk'),
		];
		if ( $panel ) {
			$bulkLocalize['panel'] = $panel;
        }

		// screen translations. Can all be loaded on the same var, since only one screen can be active.
		wp_localize_script( 'spaatg-screen-bulk', 'spaatgScreen', $bulkLocalize );

		wp_register_script( 'spaatg', plugins_url( '/res/js/shortpixel.js', SPAATG_PLUGIN_FILE ), array( 'jquery', 'spaatg-jquery-knob' ), SPAATG_IMAGE_OPTIMISER_VERSION, true );

		// Using an Array within another Array to protect the primitive values from being cast to strings
		$SPAATGConstants = array(
			array(
				'WP_PLUGIN_URL'     => plugins_url( '', SPAATG_PLUGIN_FILE ),
				'WP_ADMIN_URL'      => admin_url(),
				'API_IS_ACTIVE'     => $keyControl->keyIsVerified(),
				'AJAX_URL'          => admin_url( 'admin-ajax.php' ),
				'BULK_SECRET'       => $secretKey,
				'nonce_ajaxrequest' => wp_create_nonce( 'ajax_request' ),
				'HAS_QUOTA'         => ( $quotaController->hasQuota() ) ? 1 : 0,

			),
		);

		if ( Log::isManualDebug() ) {
			Log::addInfo( 'Ajax Manual Debug Mode' );
			$logLevel                           = Log::getLogLevel();
			$SPAATGConstants[0]['AJAX_URL'] = admin_url( 'admin-ajax.php?SPAATG_DEBUG=' . $logLevel );
		}

		$jsTranslation = array(
			'optimize'              => __( 'Optimize', 'shortpixel-image-optimiser' ),
			'redoLossy'                   => __( 'Re-optimize Lossy', 'shortpixel-image-optimiser' ),
			'redoGlossy'                  => __( 'Re-optimize Glossy', 'shortpixel-image-optimiser' ),
			'redoLossless'                => __( 'Re-optimize Lossless', 'shortpixel-image-optimiser' ),
			'redoSmartcrop'               => __( 'Re-optimize with SmartCrop', 'shortpixel-image-optimiser'),
			'redoSmartcropless'           => __( 'Re-optimize without SmartCrop', 'shortpixel-image-optimiser'),
			'restoreOriginal'             => __( 'Restore Originals', 'shortpixel-image-optimiser' ),
			'generateAI' 				  => __( 'Generate image SEO data', 'shortpixel-image-optimiser'),
			'markCompleted' 			  => __('Mark as completed' ,'shortpixel-image-optimiser'),
			'areYouSureStopOptimizing'    => __( 'Are you sure you want to stop optimizing the folder {0}?', 'shortpixel-image-optimiser' ),
			'pleaseDoNotSetLesserSize'    => __( "Please do not set a {0} less than the {1} of the largest thumbnail which is {2}, to be able to still regenerate all your thumbnails in case you'll ever need this.", 'shortpixel-image-optimiser' ),
			'pleaseDoNotSetLesser1024'    => __( "Please do not set a {0} less than 1024, to be able to still regenerate all your thumbnails in case you'll ever need this.", 'shortpixel-image-optimiser' ),
			'confirmBulkRestore'          => __( 'Are you sure you want to restore from backup all the images in your Media Library optimized with ShortPixel?', 'shortpixel-image-optimiser' ),
			'confirmBulkCleanup'          => __( "Are you sure you want to cleanup the ShortPixel metadata info for the images in your Media Library optimized with ShortPixel? This will make ShortPixel 'forget' that it optimized them and will optimize them again if you re-run the Bulk Optimization process.", 'shortpixel-image-optimiser' ),
			'alertDeliverWebPAltered'     => __( "Warning: Using this method alters the structure of the rendered HTML code (IMG tags get included in PICTURE tags), which, in some rare \ncases, can lead to CSS/JS inconsistencies.\n\nPlease test this functionality thoroughly after activating!\n\nIf you notice any issue, just deactivate it and the HTML will will revert to the previous state.", 'shortpixel-image-optimiser' ),
			'alertDeliverWebPUnaltered'   => __( 'This option will serve both WebP and the original image using the same URL, based on the web browser capabilities, please make sure you\'re serving the images from your server and not using a CDN which caches the images.', 'shortpixel-image-optimiser' ),
			'originalImage'               => __( 'Original image', 'shortpixel-image-optimiser' ),
			'optimizedImage'              => __( 'Optimized image', 'shortpixel-image-optimiser' ),
			'loading'                     => __( 'Loading...', 'shortpixel-image-optimiser' ),

		);

		wp_localize_script( 'spaatg', 'spaatgTr', $jsTranslation );
		wp_localize_script( 'spaatg', 'SPAATGConstants', $SPAATGConstants );

	}

	public function admin_styles() {

		wp_register_style( 'spaatg-folderbrowser', plugins_url( '/res/css/shortpixel-folderbrowser.css', SPAATG_PLUGIN_FILE ),[], SPAATG_IMAGE_OPTIMISER_VERSION );

		//wp_register_style( 'shortpixel', plugins_url( '/res/css/short-pixel.css', SPAATG_PLUGIN_FILE ), array(), SPAATG_IMAGE_OPTIMISER_VERSION );

		// notices. additional styles for SPIO.
		wp_register_style( 'spaatg-notices', plugins_url( '/res/css/shortpixel-notices.css', SPAATG_PLUGIN_FILE ), array( 'spaatg-admin' ), SPAATG_IMAGE_OPTIMISER_VERSION );

		wp_register_style('spaatg-notices-module', plugins_url('/build/shortpixel/notices/src/css/notices.css', SPAATG_PLUGIN_FILE), array(), SPAATG_IMAGE_OPTIMISER_VERSION);

		// other media screen
		wp_register_style( 'spaatg-othermedia', plugins_url( '/res/css/shortpixel-othermedia.css', SPAATG_PLUGIN_FILE ), array(), SPAATG_IMAGE_OPTIMISER_VERSION );

		// load everywhere, because we are inconsistent.
		wp_register_style( 'spaatg-toolbar', plugins_url( '/res/css/shortpixel-toolbar.css', SPAATG_PLUGIN_FILE ), array( 'dashicons' ), SPAATG_IMAGE_OPTIMISER_VERSION );

		// @todo Might need to be removed later on
		wp_register_style( 'spaatg-admin', plugins_url( '/res/css/shortpixel-admin.css', SPAATG_PLUGIN_FILE ), array(), SPAATG_IMAGE_OPTIMISER_VERSION );

		wp_register_style( 'spaatg-bulk', plugins_url( '/res/css/shortpixel-bulk.css', SPAATG_PLUGIN_FILE ), array(), SPAATG_IMAGE_OPTIMISER_VERSION );

		wp_register_style( 'spaatg-nextgen', plugins_url( '/res/css/shortpixel-nextgen.css', SPAATG_PLUGIN_FILE ), array(), SPAATG_IMAGE_OPTIMISER_VERSION );

		wp_register_style( 'spaatg-settings', plugins_url( '/res/css/shortpixel-settings.css', SPAATG_PLUGIN_FILE ), array(), SPAATG_IMAGE_OPTIMISER_VERSION );

		wp_register_style('spaatg-datepicker', plugins_url('res/css/the-datepicker.css', SPAATG_PLUGIN_FILE), [], SPAATG_IMAGE_OPTIMISER_VERSION );
	}


	/** Load Style via Route, on demand */
	public function load_style( $name ) {
		if ( $this->is_noheaders ) {  // fail silently, if this is a no-headers request.
			return;
		}

		if ( wp_style_is( $name, 'registered' ) ) {
			wp_enqueue_style( $name );
		} else {
			Log::addWarn( "Style $name was asked for, but not registered", $_SERVER['REQUEST_URI'] );
		}
	}

	/** Load Style via Route, on demand */
	public function load_script( $script ) {
		if ( $this->is_noheaders ) {  // fail silently, if this is a no-headers request.
			return;
		}

		if ( ! is_array( $script ) ) {
			$script = array( $script );
		}

		foreach ( $script as $index => $name ) {
			if ( wp_script_is( $name, 'registered' ) ) {
				wp_enqueue_script( $name );
			} else {
				Log::addWarn( "Script $name was asked for, but not registered", $_SERVER['REQUEST_URI']  );
			}
		}
	}

	/** This is separated from route to load in head, preventing unstyled content all the time */
	 public function load_admin_scripts( $hook_suffix ) {
		global $plugin_page;
		$screen_id = $this->env()->screen_id;

		$load_processor = array( 'spaatg', 'spaatg-processor' );  // a whole suit needed for processing, not more. Always needs a screen as well!
		$load_bulk      = array();  // the whole suit needed for bulking.
		if ( \wpSPAATG()->env()->is_screen_to_use ) {
			$this->load_script( $load_processor );
			$this->load_style( 'spaatg-toolbar' );
			$this->load_style('spaatg-notices');
			$this->load_style('spaatg-notices-module');
		}

		if ( $plugin_page == 'wp-spaatg-settings' || $plugin_page == 'spaatg-network-settings' ) {
			wp_enqueue_media();
			$this->load_script( $load_processor );

			$this->load_script( 'spaatg-screen-nolist' ); // screen
			$this->load_script( 'spaatg-settings' );
			$this->load_script('spaatg-chatbot');

			// @todo Load onboarding only when no api key / onboarding required
			$this->load_script('spaatg-onboarding');

			$this->load_style( 'spaatg-admin' );

			$this->load_style( 'spaatg-settings' );

		} elseif ( $plugin_page == 'wp-spaatg-bulk' ) {
			$this->load_script( 'spaatg-screen-bulk' );
			$this->load_script('spaatg-chatbot');
			$this->load_script('spaatg-datepicker');

			$this->load_style('spaatg-datepicker');
			$this->load_style( 'spaatg-admin' );
			$this->load_style( 'spaatg-bulk' );
		} elseif ( $screen_id == 'upload' || $screen_id == 'attachment' ) {

			$this->load_script( 'spaatg-screen-media' ); // screen
			$this->load_script( 'spaatg-media' );

			$this->load_style( 'spaatg-admin' );
			$this->load_style( 'spaatg-notices-module');
		//	$this->load_style( 'shortpixel' );

			if ( $this->env()->is_debug ) {
				$this->load_script( 'spaatg-debug' );
			}

		} elseif ( $plugin_page == 'wp-spaatg-custom' ) { // custom media
		//	$this->load_style( 'shortpixel' );

			$this->load_script( 'spaatg-folderbrowser' );
			$this->load_script('spaatg-chatbot');

			$this->load_style( 'spaatg-admin' );
			$this->load_style( 'spaatg-folderbrowser' );
			$this->load_style( 'spaatg-othermedia' );
			$this->load_script( 'spaatg-screen-custom' ); // screen

		} elseif ( NextGenController::getInstance()->isNextGenScreen() ) {

			$this->load_script( 'spaatg-screen-custom' ); // screen
			$this->load_style( 'spaatg-admin' );

		//	$this->load_style( 'shortpixel' );
			$this->load_style( 'spaatg-nextgen' );
		}
		elseif (true === $this->env()->is_gutenberg_editor || true === $this->env()->is_classic_editor)
		{
			$this->load_script( $load_processor );
			$this->load_script( 'spaatg-screen-media' ); // screen
			$this->load_script( 'spaatg-media' );

			$this->load_style( 'spaatg-admin' );
		}
		elseif (true === \wpSPAATG()->env()->is_screen_to_use  )
		{
			// If our screen, but we don't have a specific handler for it, do the no-list screen.
			$this->load_script( 'spaatg-screen-nolist' ); // screen
		}

	}

	/** Route, based on the page slug
     *
     * Principially all page controller should be routed from here.
     */
	public function route() {
		global $plugin_page;

		$default_action = 'load'; // generic action on controller.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended  -- This is not a form
		$action         = isset( $_REQUEST['sp-action'] ) ? sanitize_text_field( wp_unslash($_REQUEST['sp-action']) ) : $default_action;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended  -- This is not a form
		$template_part  = isset( $_GET['part'] ) ? sanitize_text_field( wp_unslash($_GET['part']) ) : false;

		$controller = false;

		$url       = menu_page_url( $plugin_page, false );
		$screen_id = \wpSPAATG()->env()->screen_id;

        switch ( $plugin_page ) {
            case 'wp-spaatg-settings': // settings
						$controller = 'SPAATG\Controller\View\SettingsViewController';
						wp_enqueue_media();
        	break;
			case 'spaatg-network-settings':
					 	$controller = 'SPAATG\Controller\View\MultiSiteViewController';
			break;
          case 'wp-spaatg-custom': // other media
						if ('folders'  === $template_part )
						{
							$controller = 'SPAATG\Controller\View\OtherMediaFolderViewController';
						}
						elseif('scan' === $template_part)
						{
							$controller = 'SPAATG\Controller\View\OtherMediaScanViewController';
						}
						else {
							$controller = 'SPAATG\Controller\View\OtherMediaViewController';
						}

        	break;
        	case 'wp-spaatg-bulk':
						$controller = '\SPAATG\Controller\View\BulkViewController';
           break;
           case null:
            default:
                switch ( $screen_id ) {
					case 'upload':
                  $controller = '\SPAATG\Controller\View\ListMediaViewController';
                        break;
					case 'attachment': // edit-media
                   $controller = '\SPAATG\Controller\View\EditMediaViewController';
                     break;
                }
                break;

		}
		if ( $controller !== false ) {
			$c = $controller::getInstance();
			$c->setControllerURL( $url );
			if ( method_exists( $c, $action ) ) {
				$c->$action();
			} else {
				Log::addWarn( "Attempted Action $action on $controller does not exist!" );
				$c->$default_action();
			}
		}
	}


	// Get the plugin URL, based on real URL.
	public function plugin_url( $urlpath = '' ) {
		$url = trailingslashit( $this->plugin_url );
		if ( strlen( $urlpath ) > 0 ) {
			$url .= $urlpath;
		}
		return $url;
	}

	// Get the plugin path.
	public function plugin_path( $path = '' ) {
		$plugin_path = trailingslashit( $this->plugin_path );
		if ( strlen( $path ) > 0 ) {
			$plugin_path .= $path;
		}

		return $plugin_path;
	}

	/** Returns defined admin page hooks. Internal use - check states via environmentmodel
     *
     * @returns Array
     */
	public function get_admin_pages() {
		return $this->admin_pages;
	}

	protected function check_plugin_version() {
      $version     = SPAATG_IMAGE_OPTIMISER_VERSION;
			$db_version = $this->settings()->currentVersion;

		if ( $version !== $db_version ) {
			InstallHelper::activatePlugin();
			$this->settings()->currentVersion = $version;

		}
	}




} // class plugin
