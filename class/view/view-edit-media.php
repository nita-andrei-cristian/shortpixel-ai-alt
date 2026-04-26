<?php
namespace SPAATG;
use SPAATG\ShortPixelLogger\ShortPixelLogger as Log;

if ( ! defined( 'ABSPATH' ) ) {
 exit; // Exit if accessed directly.
}
?>
<div id='spaatg-data-<?php echo( esc_attr($view->id) );?>' class='column-wp-spaatg view-edit-media'
  data-imagewidth="<?php echo $view->image['width'] ?>" data-imageheight="<?php echo $view->image['height'] ?>"
  data-extension="<?php echo $view->image['extension']; ?>"
>
<?php // Debug Data
if (! is_null($view->debugInfo) && is_array($view->debugInfo) && count($view->debugInfo) > 0 ):  ?>
      <div class='debugInfo' id='debugInfo'>

        <a class='debugModal' data-modal="debugInfo" ><?php esc_html_e('Debug Window', 'shortpixel-image-optimiser') ?></a>
        <div class='content wrapper'>

          <?php
          foreach($view->debugInfo as $index => $item):

        ?>
          <ul class="debug-<?php echo esc_attr($index) ?>">
            <li><strong><?php echo $item[0]; ?></strong>
              <?php
              if (is_array($item[1]) || is_object($item[1]))
              {
                echo "<PRE>" . print_r($item[1], true) . "</PRE>";
              }
              else
                echo $item[1];
              ?>
            </li>
          </ul>
          <?php endforeach; ?>
          <p>&nbsp;</p>
       </div>
    </div>
  <?php endif; ?>

  <div class='sp-column-info generated-alt-box' data-spaatg-generated-alt="<?php echo esc_attr($view->id); ?>">
    <p class='generated-alt-label'><?php esc_html_e('Alt Text', 'shortpixel-image-optimiser'); ?></p>
    <p class='shortpixel-generated-alt-content <?php echo $view->hasAltText ? '' : 'is-empty'; ?>'>
      <?php
      if ($view->hasAltText) {
          echo esc_html($view->altText);
      } else {
          esc_html_e('No alt text available yet.', 'shortpixel-image-optimiser');
      }
      ?>
    </p>
  </div>

  <?php if (property_exists($view, 'aiSnippet') && strlen($view->aiSnippet) > 0): ?>
  <div class='spaatg-edit-media-ai-interface shortpixel-ai-interface' data-spaatg-ai-actions="<?php echo esc_attr($view->id); ?>">
    <?php echo $view->aiSnippet; ?>
  </div>
  <?php endif; ?>

</div>

  <div id="sp-message-<?php echo( esc_attr($this->view->id) ); ?>" class='spio-message'>
  <?php if (! is_null($view->status_message)): ?>
  <?php echo esc_html($view->status_message); ?>
  <?php endif; ?>
  </div>

  <div id='shortpixel-errorbox' class="errorbox">&nbsp;</div>
