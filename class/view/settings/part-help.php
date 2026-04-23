<?php
namespace SPAATG;

if ( ! defined( 'ABSPATH' ) ) {
 exit; // Exit if accessed directly.
}

use SPAATG\Helper\UiHelper as UiHelper;
?>

<section id="tab-help" class="<?php echo ($this->display_part == 'help') ? 'active setting-tab' :'setting-tab'; ?>" data-part="help" >

  <div class='help-center-wrap step-highlight-4'>
    <div class='help-center-stack'>
      <div class='help-center help-center-row-top'>
        <div class='help-center-card'>
          <span class='main-icon'><?php echo UIHelper::getIcon('res/images/icon/help.svg'); ?></span>
          <h4><?php _e('Knowledge base', 'shortpixel-image-optimiser'); ?></h4>
          <p><?php esc_html_e('Most customer questions are answered in our Knowledge Base.', 'shortpixel-image-optimiser'); ?></p>

          <span class="shortpixel-button-container">
          <a href="https://shortpixel.com/knowledge-base/" target="_blank" class="button-setting">
             <?php esc_html_e('Knowledge Base', 'shortpixel-image-optimiser'); ?>
          </a>
          </span>
        </div>
        <div class='help-center-card'>
          <span class='main-icon'><?php echo UIHelper::getIcon('res/images/icon/envelope.svg'); ?></span>
          <h4><?php _e('Contact us', 'shortpixel-image-optimiser'); ?></h4>
          <p><?php esc_html_e('Contact us with any issues, bug reports, or questions.', 'shortpixel-image-optimiser'); ?></p>

          <span class="shortpixel-button-container">
          <a href="https://shortpixel.com/contact" target="_blank" class="button-setting">
             <?php esc_html_e('Contact Us', 'shortpixel-image-optimiser'); ?>
          </a>
          </span>
        </div>
      </div>
      <div class='help-center help-center-row-bottom'>
        <div class='help-center-card'>
          <span class='main-icon'><?php echo UIHelper::getIcon('res/images/icon/lightbulb.svg'); ?></span>
          <h4><?php _e('Feature Request', 'shortpixel-image-optimiser'); ?></h4>
          <p><?php esc_html_e('Is there a feature missing? Do you have suggestions for improving ShortPixel?', 'shortpixel-image-optimiser'); ?></p>

          <span class="shortpixel-button-container">
          <a href="https://ideas.shortpixel.com/" target="_blank" class="button-setting">
             <?php esc_html_e('Feature Request', 'shortpixel-image-optimiser'); ?>
          </a>
          </span>
        </div>
        <div class='help-center-card'>
          <span class='main-icon'><?php echo UIHelper::getIcon('res/images/icon/chatbot.png'); ?></span>
          <h4><?php _e('ShortPixel AI Agent', 'shortpixel-image-optimiser'); ?></h4>
          <p><?php esc_html_e('Our AI-robot would be happy to assist you with simple, optimized questions.', 'shortpixel-image-optimiser'); ?></p>

          <span class="shortpixel-button-container">
          <a href="#" class="button-setting" setting-action="OpenChatEvent">
             <?php esc_html_e('Try our chatbot', 'shortpixel-image-optimiser'); ?>
          </a>
          </span>
        </div>
      </div>
    </div>
  </div>
</section>
