<?php

namespace SPAATG;

if (! defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

?>
<section id="tab-account" class="<?php echo ($this->display_part == 'account') ? 'active setting-tab' : 'setting-tab'; ?>" data-part="account">
  <input type="hidden" name="enable_ai" value="1">

  <div class="ai-top-panels">
    <settinglist class="ai-api-key-setting">
      <input type="checkbox" id="toggle-content" style="display: none;">
      <closed-apikey-dropdown>
        <name>
          <?php esc_html_e('API Key & Account Information ', 'shortpixel-image-optimiser'); ?>
        </name>
        <info>
          <?php if ($view->key->is_constant_key && ! $view->key->hide_api_key) {
            esc_html_e('Key defined in wp-config.php.', 'shortpixel-image-optimiser');
          } ?>
          <span class="shortpixel-key-valid" <?php echo $view->key->is_verifiedkey ? '' : 'style="display:none;"' ?>>
            <?php esc_html_e('Yay! Your API Key is Valid ', 'shortpixel-image-optimiser'); ?><i class="shortpixel-icon ok"></i>
          </span>
          <?php if (! empty($view->key->validation_error_message) && ! $view->key->is_verifiedkey) { ?>
          <span class="shortpixel-key-error">
            <?php echo wp_kses_post($view->key->validation_error_message); ?>
          </span>
          <?php } ?>
        </info>
        <?php if (! $view->key->hide_api_key) { ?>
        <label for="toggle-content" class="toggle-link">
          <span class="toggle-text"><?php _e('Show API Key', 'shortpixel-image-optimiser'); ?></span>
          <span class="shortpixel-icon chevron"></span>
        </label>
        <?php } ?>
      </closed-apikey-dropdown>

      <hr>

      <content>
        <div class="apifield">
          <input name="apiKey" type="password" id="key" value="<?php echo esc_attr($view->key->apiKey); ?>"
                 class="regular-text" <?php echo ($view->key->is_editable ? '' : 'disabled') ?>>
          <i class="shortpixel-icon eye"></i>
        </div>

        <button type="submit" id="validate" class="button button-primary" title="<?php esc_html_e('Validate the provided API key','shortpixel-image-optimiser');?>"
                 <?php echo $view->key->is_editable ? '' : 'disabled' ?>>
          <i class='shortpixel-icon save'></i>
          <span class="save-button-text"><?php esc_html_e('Save settings & validate', 'shortpixel-image-optimiser'); ?></span>
        </button>
      </content>
    </settinglist>

    <?php if ($view->key->is_verifiedkey && property_exists($view, 'quotaData') && is_object($view->quotaData)) { ?>
      <div class="ai-credit-panel">
        <span><?php esc_html_e('AI Credits Used', 'shortpixel-image-optimiser'); ?></span>
        <strong>
          <?php if (true === $view->quotaData->unlimited) {
            printf(
              esc_html__('%1$s / Unlimited', 'shortpixel-image-optimiser'),
              esc_html($this->formatNumber($view->quotaData->total->consumed, 0))
            );
          } else {
            printf(
              esc_html__('%1$s / %2$s', 'shortpixel-image-optimiser'),
              esc_html($this->formatNumber($view->quotaData->total->consumed, 0)),
              esc_html($this->formatNumber($view->quotaData->total->total, 0))
            );
          } ?>
        </strong>
      </div>
    <?php } ?>
  </div>

  <settinglist>
    <h2><?php esc_html_e('Account & Settings', 'shortpixel-image-optimiser'); ?></h2>

    <gridbox class="width_half">
      <setting class='switch'>
        <content>
          <?php $this->printSwitchButton(
            [
              'name' => 'autoAI',
              'checked' => $view->data->autoAI,
              'label' => esc_html__('Generate image SEO data on upload', 'shortpixel-image-optimiser'),
            ]
          );
          ?>

          <i class='documentation dashicons dashicons-editor-help' data-link="https://shortpixel.com/knowledge-base/article/ai-image-seo-settings-explained/#1-toc-title?target=iframe"></i>
          <name>
            <?php esc_html_e('Automatically generate image SEO data with AI after uploading the image, based on the settings below.', 'shortpixel-image-optimiser'); ?>
          </name>
        </content>
      </setting>

      <setting class='switch'>
        <content>
          <?php $this->printSwitchButton(
            [
              'name' => 'aiPreserve',
              'checked' => $view->data->aiPreserve,
              'label' => esc_html__('Preserve existing Image SEO data', 'shortpixel-image-optimiser'),
              'data' => ['data-toggle="ai_overwrite_warning"']
            ]
          );
          ?>
          <i class='documentation dashicons dashicons-editor-help' data-link="https://shortpixel.com/knowledge-base/article/ai-image-seo-settings-explained/#2-toc-title?target=iframe"></i>

          <name>
            <?php esc_html_e('When enabled, all existing ALT tags, captions and descriptions are retained. Disabling the switch means that the SEO data for images created with AI will overwrite the existing data.', 'shortpixel-image-optimiser'); ?>
          </name>
        </content>
        <warning class="ai_overwrite_warning">
          <message>
            <?php _e('SPIO may still write image title when preserving data, since image title is always set', 'shortpixel-image-optimiser'); ?>
          </message>
        </warning>
      </setting>
    </gridbox>
  </settinglist>

  <?php $this->loadView('settings/part-savebuttons', false); ?>
</section>
