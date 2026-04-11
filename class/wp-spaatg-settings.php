<?php
use SPAATG\ShortPixelLogger\ShortPixelLogger as Log;

use SPAATG\Model\SettingsModel as SettingsModel;

if ( ! defined( 'ABSPATH' ) ) {
 exit; // Exit if accessed directly.
}


/** Settings Model **/
class WPSPAATGSettings extends \SPAATG\Model {
    private $_apiKey = '';
    private $_compressionType = 1;
    private $_keepExif = 0;
    private $_processThumbnails = 1;
    private $_CMYKtoRGBconversion = 1;
    private $_backupImages = 1;
    private $_verifiedKey = false;

    private $_resizeImages = false;
    private $_resizeWidth = 0;
    private $_resizeHeight = 0;

    private static $_optionsMap = array(
        //This one is accessed also directly via get_option
    //    'frontBootstrap' => array('key' => 'wp-spaatg-front-bootstrap', 'default' => null, 'group' => 'options'), //set to 1 when need the plugin active for logged in user in the front-end
      //  'lastBackAction' => array('key' => 'wp-spaatg-last-back-action', 'default' => null, 'group' => 'state'), //when less than 10 min. passed from this timestamp, the front-bootstrap is ineffective.

        //optimization options
        'apiKey' => array('key' => 'wp-spaatg-apiKey', 'default' => '', 'group' => 'options'),
        'verifiedKey' => array('key' => 'wp-spaatg-verifiedKey', 'default' => false, 'group' => 'options'),
        'compressionType' => array('key' => 'wp-spaatg-compression', 'default' => 1, 'group' => 'options'),
        'processThumbnails' => array('key' => 'wp-spaatg-process-thumbnails', 'default' => 1, 'group' => 'options'),
				'useSmartcrop' => array('key' => 'wpspaatg-usesmartcrop', 'default' => 0, 'group' => 'options'),
        'keepExif' => array('key' => 'wp-spaatg-keep-exif', 'default' => 0, 'group' => 'options'),
        'CMYKtoRGBconversion' => array('key' => 'wp-spaatg-cmyk2rgb', 'default' => 1, 'group' => 'options'),
        'createWebp' => array('key' => 'wp-spaatg-create-webp', 'default' => null, 'group' => 'options'),
        'createAvif' => array('key' => 'wp-spaatg-create-avif', 'default' => null, 'group' => 'options'),
        'deliverWebp' => array('key' => 'wp-spaatg-create-webp-markup', 'default' => 0, 'group' => 'options'),
        'optimizeRetina' => array('key' => 'wp-spaatg-optimize-retina', 'default' => 1, 'group' => 'options'),
        'optimizeUnlisted' => array('key' => 'wp-spaatg-optimize-unlisted', 'default' => 0, 'group' => 'options'),
        'backupImages' => array('key' => 'wp-spaatg-backup-images', 'default' => 1, 'group' => 'options'),
        'resizeImages' => array('key' => 'wp-spaatg-resize-images', 'default' => false, 'group' => 'options'),
        'resizeType' => array('key' => 'wp-spaatg-resize-type', 'default' => null, 'group' => 'options'),
        'resizeWidth' => array('key' => 'wp-spaatg-resize-width', 'default' => 0, 'group' => 'options'),
        'resizeHeight' => array('key' => 'wp-spaatg-resize-height', 'default' => 0, 'group' => 'options'),
        'siteAuthUser' => array('key' => 'wp-spaatg-site-auth-user', 'default' => null, 'group' => 'options'),
        'siteAuthPass' => array('key' => 'wp-spaatg-site-auth-pass', 'default' => null, 'group' => 'options'),
        'autoMediaLibrary' => array('key' => 'wp-spaatg-auto-media-library', 'default' => 1, 'group' => 'options'),
        'doBackgroundProcess' => array('key' => 'wp-spaatg-backgroundprocess', 'default' => 0, 'group' => 'options'),
        'optimizePdfs' => array('key' => 'wp-spaatg-optimize-pdfs', 'default' => 1, 'group' => 'options'),
        'excludePatterns' => array('key' => 'wp-spaatg-exclude-patterns', 'default' => array(), 'group' => 'options'),
        'png2jpg' => array('key' => 'wp-spaatg-png2jpg', 'default' => 0, 'group' => 'options'),
        'excludeSizes' => array('key' => 'wp-spaatg-excludeSizes', 'default' => array(), 'group' => 'options'),
				'currentVersion' => array('key' => 'wp-spaatg-currentVersion', 'default' => null, 'group' => 'options'),
				'showCustomMedia' => array('key' => 'wp-spaatg-show-custom-media', 'default' => 1, 'group' => 'options'),

        //CloudFlare
        /*'cloudflareEmail'   => array( 'key' => 'wp-spaatg-cloudflareAPIEmail', 'default' => '', 'group' => 'options'),
        'cloudflareAuthKey' => array( 'key' => 'wp-spaatg-cloudflareAuthKey', 'default' => '', 'group' => 'options'), */
        'cloudflareZoneID'  => array( 'key' => 'wp-spaatg-cloudflareAPIZoneID', 'default' => '', 'group' => 'options'),
        'cloudflareToken'   => array( 'key' => 'wp-spaatg-cloudflareToken', 'default' => '', 'group' => 'options'),

        //optimize other images than the ones in Media Library
        'includeNextGen' => array('key' => 'wp-spaatg-include-next-gen', 'default' => null, 'group' => 'options'),
        'hasCustomFolders' => array('key' => 'wp-spaatg-has-custom-folders', 'default' => false, 'group' => 'options'),
        //'customBulkPaused' => array('key' => 'wp-spaatg-custom-bulk-paused', 'default' => false, 'group' => 'options'),

        //uninstall
  //      'removeSettingsOnDeletePlugin' => array('key' => 'wp-spaatg-remove-settings-on-delete-plugin', 'default' => false, 'group' => 'options'),

        //stats, notices, etc.
				// @todo Most of this can go. See state machine comment.
        'currentStats' => array('key' => 'wp-spaatg-current-total-files', 'default' => null, 'group' => 'state'),
      //  'fileCount' => array('key' => 'wp-spaatg-fileCount', 'default' => 0, 'group' => 'state'),
        'thumbsCount' => array('key' => 'wp-spaatg-thumbnail-count', 'default' => 0, 'group' => 'state'),
        //'under5Percent' => array('key' => 'wp-spaatg-files-under-5-percent', 'default' => 0, 'group' => 'state'),
    //    'savedSpace' => array('key' => 'wp-spaatg-savedSpace', 'default' => 0, 'group' => 'state'),
       // 'apiRetries' => array('key' => 'wp-spaatg-api-retries', 'default' => 0, 'group' => 'state'),
      //  'totalOptimized' => array('key' => 'wp-spaatg-total-optimized', 'default' => 0, 'group' => 'state'),
      //  'totalOriginal' => array('key' => 'wp-spaatg-total-original', 'default' => 0, 'group' => 'state'),
        'quotaExceeded' => array('key' => 'wp-spaatg-quota-exceeded', 'default' => 0, 'group' => 'state'),
        'httpProto' => array('key' => 'wp-spaatg-protocol', 'default' => 'https', 'group' => 'state'),
        'downloadProto' => array('key' => 'wp-spaatg-download-protocol', 'default' => null, 'group' => 'state'),

				'downloadArchive' => array('key' => 'wp-spaatg-download-archive', 'default' => -1, 'group' => 'state'),

        'activationDate' => array('key' => 'wp-spaatg-activation-date', 'default' => null, 'group' => 'state'),
        'mediaLibraryViewMode' => array('key' => 'wp-spaatg-view-mode', 'default' => false, 'group' => 'state'),
        'redirectedSettings' => array('key' => 'wp-spaatg-redirected-settings', 'default' => null, 'group' => 'state'),
      //  'convertedPng2Jpg' => array('key' => 'wp-spaatg-converted-png2jpg', 'default' => array(), 'group' => 'state'),
				'unlistedCounter' => array('key' => 'wp-spaatg-unlisted-counter', 'default' => 0, 'group' => 'state'),
    );



    // This  array --  field_name -> (s)anitize mask
    protected $model = array(
        'apiKey' => array('s' => 'string'), // string
    //    'verifiedKey' => array('s' => 'string'), // string
        'compressionType' => array('s' => 'int'), // int
        'resizeWidth' => array('s' => 'int'), // int
        'resizeHeight' => array('s' => 'int'), // int
        'processThumbnails' => array('s' => 'boolean'), // checkbox
				'useSmartcrop' => array('s' => 'boolean'),
        'backupImages' => array('s' => 'boolean'), // checkbox
        'keepExif' => array('s' => 'int'), // checkbox
        'resizeImages' => array('s' => 'boolean'),
        'resizeType' => array('s' => 'string'),
        'includeNextGen' => array('s' => 'boolean'), // checkbox
        'png2jpg' => array('s' => 'int'), // checkbox
        'CMYKtoRGBconversion' => array('s' => 'boolean'), //checkbox
        'createWebp' => array('s' => 'boolean'), // checkbox
        'createAvif' => array('s' => 'boolean'),  // checkbox
        'deliverWebp' => array('s' => 'int'), // checkbox
        'optimizeRetina' => array('s' => 'boolean'), // checkbox
        'optimizeUnlisted' => array('s' => 'boolean'), // $checkbox
        'optimizePdfs' => array('s' => 'boolean'), //checkbox
        'excludePatterns' => array('s' => 'exception'), //  - processed, multi-layer, so skip
        'siteAuthUser' => array('s' => 'string'), // string
        'siteAuthPass' => array('s' => 'string'), // string
      //  'frontBootstrap' => array('s' =>'boolean'), // checkbox
        'autoMediaLibrary' => array('s' => 'boolean'), // checkbox
        'doBackgroundProcess' => array('s' => 'boolean'), // checkbox
        'excludeSizes' => array('s' => 'array'), // Array
      //  'cloudflareEmail' => array('s' => 'string'), // string
      //  'cloudflareAuthKey' => array('s' => 'string'), // string
        'cloudflareZoneID' => array('s' => 'string'), // string
        'cloudflareToken' => array('s' => 'string'),

				'showCustomMedia' => array('s' => 'boolean'),
        'currentStats' => array('s' => 'array')
    );

      public static function resetOptions() {
        foreach(self::$_optionsMap as $key => $val) {
            delete_option($val['key']);
        }
        delete_option("wp-spaatg-bulk-previous-percent");
    }

    public static function onActivate() {
        /*if(!self::getOpt('wp-spaatg-verifiedKey', false)) {
            update_option('wp-spaatg-activation-notice', true, 'no');
        } */
        update_option( 'wp-spaatg-activation-date', time(), 'no');

        delete_option( 'wp-spaatg-current-total-files');
				//delete_option('wp-spaatg-remove-settings-on-delete-plugin');

        /*
				if (isset(self::$_optionsMap['removeSettingsOnDeletePlugin']) && isset(self::$_optionsMap['removeSettingsOnDeletePlugin']['key']))
				{
        	delete_option(self::$_optionsMap['removeSettingsOnDeletePlugin']['key']);
				} */

        $settingsModel = SettingsModel::getInstance();
				$updated = false;

				foreach(self::$_optionsMap as $option_name => $data)
				{
					 $value = self::getOpt($data['key'], $data['default']);
					 $bool = $settingsModel->setIfEmpty($option_name, $value);

					 // Remove setting if set, or if it doesn't exist in model anymore
					 if (true === $bool || false === $settingsModel->exists($option_name))
					 {
            //  Log::AddTrace('Would delete non-existing? setting ' . $option_name);
					//	  delete_option($data['key']);
					 		$updated = true;
					 }
				}


    }

    public static function onDeactivate() {

        delete_option('wp-spaatg-activation-notice');
				delete_option('wp-spaatg-bulk-last-status'); // legacy shizzle
				delete_option('wp-spaatg-current-total-files');
				delete_option('wp-spaatg-remove-settings-on-delete-plugin');

				// Bulk State machine legacy
				$bulkLegacyOptions = array(
						'wp-spaatg-bulk-type',
						'wp-spaatg-bulk-last-status',
						'wp-spaatg-query-id-start',
						'wp-spaatg-query-id-stop',
						'wp-spaatg-bulk-count',
						'wp-spaatg-bulk-previous-percent',
						'wp-spaatg-bulk-processed-items',
						'wp-spaatg-bulk-done-count',
						'wp-spaatg-last-bulk-start-time',
						'wp-spaatg-last-bulk-success-time',
						'wp-spaatg-bulk-running-time',
						'wp-spaatg-cancel-pointer',
						'wp-spaatg-skip-to-custom',
						'wp-spaatg-bulk-ever-ran',
						'wp-spaatg-flag-id',
						'wp-spaatg-failed-imgs',
						'bulkProcessingStatus',
						'wp-spaatg-prioritySkip',
				);

				$removedStats = array(
						'wp-spaatg-helpscout-optin',
						'wp-spaatg-activation-notice',
						'wp-spaatg-dismissed-notices',
						'wp-spaatg-media-alert',
				);

				$removedOptions = array(
						'wp-spaatg-remove-settings-on-delete-plugin',
						'wp-spaatg-custom-bulk-paused',
						'wp-spaatg-last-back-action',
						'wp-spaatg-front-bootstrap',
				);

        // Settings completely removed during the settings redo
        $settingsRevamp = [
          'wp-spaatg-cloudflareAPIEmail',
          'wp-spaatg-cloudflareAuthKey',
          'wp-spaatg-front-bootstrap',
					'wp-spaatg-api-retries',
					'wp-spaatg-total-optimized',
					'wp-spaatg-total-original',
					'wp-spaatg-download-archive',
					'wp-spaatg-converted-png2jpg',
          'wp-spaatg-savedSpace',
          'wp-spaatg-fileCount',
          'wp-spaatg-files-under-5-percent',
        ];

				$toRemove = array_merge($bulkLegacyOptions, $removedStats, $removedOptions, $settingsRevamp);

				foreach($toRemove as $option)
				{
					 delete_option($option);
				}
    }

    public function __get($name)
    {
        if (array_key_exists($name, self::$_optionsMap)) {
            return $this->getOpt(self::$_optionsMap[$name]['key'], self::$_optionsMap[$name]['default']);
        }
        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . esc_html($name) .
            ' in ' . esc_html($trace[0]['file']) .
            ' on line ' . esc_html($trace[0]['line']),
            E_USER_NOTICE);
        return null;
    }

    public function __set($name, $value) {
        if (array_key_exists($name, self::$_optionsMap)) {
            if($value !== null) {
                $this->setOpt(self::$_optionsMap[$name]['key'], $value);
            } else {
                delete_option(self::$_optionsMap[$name]['key']);
            }
        }
    }

		// Remove option. Only deletes with defined key!
		public function deleteOption($key)
		{
			  if(isset(self::$_optionsMap[$key]) && isset(self::$_optionsMap[$key]['key']))
				{
						$deleteKey = self::$_optionsMap[$key]['key'];
						delete_option($deleteKey);
				}
		}

    public static function getOpt($key, $default = null) {

				// This function required the internal Key. If this not given, but settings key, overwrite.
        if(isset(self::$_optionsMap[$key]['key'])) { //first try our name
						$default = self::$_optionsMap[$key]['default']; // first do default do to overwrite.
						$key = self::$_optionsMap[$key]['key'];
        }

        $opt = get_option($key, $default);
				return $opt;
    }

    public function setOpt($key, $val) {
        $autoload = true;
        $ret = update_option($key, $val, $autoload);

        //hack for the situation when the option would just not update....
        if($ret === false && !is_array($val) && $val != get_option($key)) {
            delete_option($key);
            $alloptions = wp_load_alloptions();
            if ( isset( $alloptions[$key] ) ) {
                wp_cache_delete( 'alloptions', 'options' );
            } else {
                wp_cache_delete( $key, 'options' );
            }
            delete_option($key);
            add_option($key, $val, '', $autoload);

            // still not? try the DB way...
            if($ret === false && $val != get_option($key)) {
                global $wpdb;
                $sql = "SELECT * FROM {$wpdb->prefix}options WHERE option_name = '" . $key . "'";
                $rows = $wpdb->get_results($sql);
                if(count($rows) === 0) {
                    $wpdb->insert($wpdb->prefix.'options',
                                 array("option_name" => $key, "option_value" => (is_array($val) ? serialize($val) : $val), "autoload" => $autoload),
                                 array("option_name" => "%s", "option_value" => (is_numeric($val) ? "%d" : "%s")));
                } else { //update
                    $sql = "update {$wpdb->prefix}options SET option_value=" .
                           (is_array($val)
                               ? "'" . serialize($val) . "'"
                               : (is_numeric($val) ? $val : "'" . $val . "'")) . " WHERE option_name = '" . $key . "'";
                    $rows = $wpdb->get_results($sql);
                }

                if($val != get_option($key)) {
                    //tough luck, gonna use the bomb...
                    wp_cache_flush();
                    delete_option($key);
                    add_option($key, $val, '', $autoload);
                }
            }
        }
    }

} // class
