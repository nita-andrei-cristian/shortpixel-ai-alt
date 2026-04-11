<?php
/**
 * Plugin Name: ShortPixel AI Alt Text Generator
 * Plugin URI: https://shortpixel.com/
 * Description: Generate AI-powered alt text and image SEO fields for your WordPress media library. Configure the plugin in <a href="/wp-admin/options-general.php?page=wp-spaatg-settings" target="_blank">Settings &gt; ShortPixel AI Alt Text Generator</a>.
 * Version: 6.4.4.5
 * Author: ShortPixel
 * Author URI: https://shortpixel.com
 * GitHub Plugin URI: https://github.com/short-pixel-optimizer/shortpixel-image-optimiser
 * Text Domain: shortpixel-image-optimiser
 * Domain Path: /lang
 */


 if ( ! defined( 'ABSPATH' ) ) {
 	exit('No Direct Access'); // Exit if accessed directly.
 }

// Preventing double load crash.
if (function_exists('wpSPAATG'))
{
    add_action('admin_notices', function () {
      echo '<div class="error"><h4>';
      printf(esc_html__('ShortPixel AI Alt Text Generator plugin already loaded. You might have two versions active. Not loaded: %s', 'shortpixel-image-optimiser'), __FILE__);
      echo '</h4></div>';
    });
    return;
}

if (! defined('SPAATG_RESET_ON_ACTIVATE'))
  define('SPAATG_RESET_ON_ACTIVATE', false);

//define('SPAATG_DEBUG', true);
//define('SPAATG_DEBUG_TARGET', true);

define('SPAATG_PLUGIN_FILE', __FILE__);
define('SPAATG_PLUGIN_DIR', __DIR__);

define('SPAATG_IMAGE_OPTIMISER_VERSION', "6.4.4.5");

define('SPAATG_BACKUP', 'ShortpixelBackups');
define('SPAATG_MAX_FAIL_RETRIES', 3);

if(!defined('SPAATG_USE_DOUBLE_WEBP_EXTENSION')) { //can be defined in wp-config.php
    define('SPAATG_USE_DOUBLE_WEBP_EXTENSION', false);
}

if(!defined('SPAATG_USE_DOUBLE_AVIF_EXTENSION')) { //can be defined in wp-config.php
    define('SPAATG_USE_DOUBLE_AVIF_EXTENSION', false);
}

define('SPAATG_API', 'api.shortpixel.com');

$max_exec = intval(ini_get('max_execution_time'));
if ($max_exec === 0) // max execution time of zero means infinite. Quantify.
  $max_exec = 60;
elseif($max_exec < 0) // some hosts like to set negative figures on this. Ignore that.
  $max_exec = 30;
define('SPAATG_MAX_EXECUTION_TIME', $max_exec);

// ** Load the modules */
require_once(SPAATG_PLUGIN_DIR . '/build/shortpixel/autoload.php');

$sp__uploads = wp_get_upload_dir();

define('SPAATG_UPLOADS_BASE', (file_exists($sp__uploads['basedir']) ? '' : ABSPATH) . $sp__uploads['basedir'] );
define('SPAATG_UPLOADS_URL', is_main_site() ? $sp__uploads['baseurl'] : dirname(dirname($sp__uploads['baseurl'])));
define('SPAATG_UPLOADS_NAME', basename(is_main_site() ? SPAATG_UPLOADS_BASE : dirname(dirname(SPAATG_UPLOADS_BASE))));
$sp__backupBase = is_main_site() ? SPAATG_UPLOADS_BASE : dirname(dirname(SPAATG_UPLOADS_BASE));
define('SPAATG_BACKUP_FOLDER', $sp__backupBase . '/' . SPAATG_BACKUP);



//define('SPAATG_SILENT_MODE', true); // no global notifications. Can lead to data damage. After setting, reactivate plugin.
//define('SPAATG_TRUSTED_MODE', false); // doesn't do any file checks on the view-side of things.
// define('SPAATG_SKIP_FEEDBACK', true);

// Starting logging services, early as possible.
if (! defined('SPAATG_DEBUG'))
{
    define('SPAATG_DEBUG', false);
}


if (false === defined( 'WP_CLI' ) || false === WP_CLI)
{
	$log = \SPAATG\ShortPixelLogger\ShortPixelLogger::getInstance();
	if (\SPAATG\ShortPixelLogger\ShortPixelLogger::debugIsActive() )
	{
  	$log->setLogPath(SPAATG_BACKUP_FOLDER . "/shortpixel_log");
	}
}

/* Function to reach core function of ShortPixel
* Use to get plugin url, plugin path, or certain core controllers
*/

if (! function_exists("wpSPAATG"))	{
  function wpSPAATG()
  {
     return \SPAATG\ShortPixelPlugin::getInstance();
  }
}
// Start runtime here
require_once(SPAATG_PLUGIN_DIR . '/shortpixel-plugin.php'); // loads runtime and needed classes.

// PSR-4 package loader.
$loader = new SPAATG\Build\PackageLoader();
$loader->setComposerFile(SPAATG_PLUGIN_DIR . '/class/plugin.json');
$loader->load(SPAATG_PLUGIN_DIR);

wpSPAATG(); // let's go!

// Activation / Deactivation services
register_activation_hook( __FILE__, array('\SPAATG\Helper\InstallHelper','activatePlugin') );
register_deactivation_hook( __FILE__,  array('\SPAATG\Helper\InstallHelper','deactivatePlugin') );
register_uninstall_hook(__FILE__,  array('\SPAATG\Helper\InstallHelper','uninstallPlugin') );
