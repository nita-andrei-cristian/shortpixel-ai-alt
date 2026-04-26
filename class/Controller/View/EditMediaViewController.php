<?php
namespace SPAATG\Controller\View;

use SPAATG\Controller\Optimizer\OptimizeAiController;

if ( ! defined( 'ABSPATH' ) ) {
 exit; // Exit if accessed directly.
}

use SPAATG\Controller\Queue\QueueItems as QueueItems;
use SPAATG\Model\AiDataModel;


// Future contoller for the edit media metabox view.
class EditMediaViewController extends \SPAATG\ViewController
{
      protected $template = 'view-edit-media';
  //    protected $model = 'image';

      protected $post_id;
      protected $legacyViewObj;

      protected $imageModel;
      protected $hooked;

			protected static $instance;

      protected function loadHooks()
      {
            add_action( 'add_meta_boxes_attachment', array( $this, 'addMetaBox') );
          //  add_action( 'attachment_fields_to_edit', [ $this, 'addAIAlter'], 10, 2);
            $this->hooked = true;
      }

      public function load()
      {
        if (! $this->hooked)
          $this->loadHooks();

					$fs = \wpSPAATG()->filesystem();
					$fs->startTrustedMode();

      }

      public function addMetaBox()
      {
          add_meta_box(
              'spaatg_ai_seo_box',          // this is HTML id of the box on edit screen
              __('ShortPixel AI SEO', 'shortpixel-image-optimiser'),    // title of the box
              array( $this, 'doMetaBox'),   // function to be called to display the info
              null,//,        // on which edit screen the box should appear
              'side'//'normal',      // part of page where the box should appear
              //'default'      // priority of the box
          );
      }

      /** Wordpress Filter to ( temp ) add a alt button for AI to the interface.
       * 
       * @param array $fields 
       * @param object $post 
       * @return array 
       */
      public function addAIAlter($fields, $post)
      { 
          $post_id = intval($post->ID);
          $fields['aibutton'] = [
              'label' => __('ShortPixel AI Data', 'shortpixel-image-optimiser'), 
              'input' => 'html', 
              'html' => "<a href='javascript:window.SPAATGProcessor.screen.RequestAlt($post_id)' class='button button-secondary shortpixel-ai-generate-button'>" . __('Generate', 'shortpixel-image-optimiser') . "</a>
                 <div class='shortpixel-alt-messagebox' id='shortpixel-ai-messagebox-$post_id'>&nbsp;</div>
               ",
          ];
         
          return $fields;
      }

      public function dometaBox($post)
      {
          $this->post_id = $post->ID;
					$this->view->debugInfo = array();
					$this->view->id = $this->post_id;

          $fs = \wpSPAATG()->filesystem();
          $this->imageModel = $fs->getMediaImage($this->post_id);

					// Asking for something non-existing.
					if ($this->imageModel === false)
					{
						$this->view->status_message = __('File Error. This could be not an image or the file is missing', 'shortpixel-image-optimiser');

						$this->loadView();
						return false;
					}

          $this->view->status_message = null;

          $this->view->image = [ 'width' => $this->imageModel->get('width'), 'height' => $this->imageModel->get('height'), 'extension' => $this->imageModel->getExtension() ];
          $this->view->altText = $this->getAltText();
          $this->view->hasAltText = (strlen(trim($this->view->altText)) > 0);
          $this->view->aiSnippet = $this->getAiSnippet();

          if (! $this->userIsAllowed)
          {
            $this->view->aiSnippet = '';
          }

          if(true === \wpSPAATG()->env()->is_debug )
          {
            $this->view->debugInfo = $this->getDebugInfo();
          }

          $this->loadView();
      }

      protected function getAiSnippet()
      {
          $optimizeAiController = OptimizeAiController::getInstance();

          if (false === $optimizeAiController->isAiEnabled()) {
              return '';
          }

          $item = QueueItems::getImageItem($this->imageModel);
          $item->getAltDataAction();

          $aiData = $optimizeAiController->getAltData($item);

          if (! is_array($aiData) || ! isset($aiData['snippet'])) {
              return '';
          }

          return $this->prepareAiSnippetForMetaBox($aiData['snippet']);
      }

      protected function prepareAiSnippetForMetaBox($snippet)
      {
          $messageId = 'shortpixel-ai-messagebox-' . $this->post_id;
          $metaBoxMessageId = 'shortpixel-ai-messagebox-box-' . $this->post_id;

          return str_replace(
              'id="' . $messageId . '"',
              'id="' . $metaBoxMessageId . '" data-spaatg-ai-messagebox="' . $this->post_id . '"',
              $snippet
          );
      }

      protected function getAltText()
      {
          $aiDataModel = AiDataModel::getModelByAttachment($this->post_id, 'media');
          $currentData = $aiDataModel->getCurrentData();
          $generatedData = $aiDataModel->getGeneratedData();

          $currentAlt = (is_array($currentData) && isset($currentData['alt'])) ? $currentData['alt'] : '';
          $generatedAlt = (is_array($generatedData) && isset($generatedData['alt'])) ? $generatedData['alt'] : '';

          if (strlen(trim($currentAlt)) > 0) {
              return $currentAlt;
          }

          if (strlen(trim($generatedAlt)) > 0) {
              return $generatedAlt;
          }

          return (string) get_post_meta($this->post_id, '_wp_attachment_image_alt', true);
      }

      protected function getDebugInfo()
      {
          if(! \wpSPAATG()->env()->is_debug )
          {
            return [];
          }

          $meta = \wp_get_attachment_metadata($this->post_id);
					$imageObj = $this->imageModel;
          $optimizeAiController = OptimizeAiController::getInstance();
					$processable = ($imageObj->isProcessable()) ? '<span class="green">Yes</span>' : '<span class="red">No</span> (' . $imageObj->getReason('processable') . ')';

          $debugInfo = array();
          $debugInfo[] = array(__('URL (get attachment URL)', 'shortpixel_image_optiser'), wp_get_attachment_url($this->post_id));
          $debugInfo[] = array(__('File (get attached)'), get_attached_file($this->post_id));
          $debugInfo[] = array(__('Size and Mime (ImageObj)'), $imageObj->get('width') . 'x' . $imageObj->get('height'). ' (' . $imageObj->get('mime') . ')');
					$debugInfo[] = array(__('Processable'), $processable);

          if ( $optimizeAiController->isAIEnabled())
          {
            $aiDataModel = AiDataModel::getModelByAttachment($this->post_id);

            $aiProcessable = ($aiDataModel->isProcessable()) ? '<span class="green">Yes</span>' : '<span class="red">No</span> ';

            $debugInfo[] = ['AI - is Processable', $aiProcessable]; 

            if (true === $aiDataModel->isProcessable())
            {
              $debugInfo[] = ['Ai - Paramlist ', $aiDataModel->getOptimizeData() ];            
            }
            else
            {
               $debugInfo[] = ['Ai - Reason', $aiDataModel->getProcessableReason()];
            }
            if (true === $aiDataModel->isSomeThingGenerated())
            {
              $debugInfo[] = ['Ai -Generated ', $aiDataModel->getGeneratedData()];
            }

          }

          $debugInfo['imagemetadata'] = array(__('ImageModel Metadata (ShortPixel)'), $imageObj);
					$debugInfo[] = array('', '<hr>');

          $debugInfo['wpmetadata'] = array(__('WordPress Get Attachment Metadata'), $meta );

          return $debugInfo;
      }



} // controller .
