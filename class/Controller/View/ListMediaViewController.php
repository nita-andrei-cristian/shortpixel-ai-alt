<?php
namespace SPAATG\Controller\View;

if ( ! defined( 'ABSPATH' ) ) {
 exit; // Exit if accessed directly.
}

use SPAATG\Helper\UiHelper as UiHelper;

use SPAATG\Controller\Optimizer\OptimizeAiController;
use SPAATG\Model\AiDataModel;


// Controller for the MediaLibraryView
class ListMediaViewController extends \SPAATG\ViewController
{

	protected static $instance;

  protected $template = 'view-list-media';
//  protected $model = 'image';

  public function load()
  {
			$fs = \wpSPAATG()->filesystem();
			$fs->startTrustedMode();

      $this->loadHooks();
  }

	
  /** Hooks for the MediaLibrary View */
  protected function loadHooks()
  {
    add_filter( 'manage_media_columns', array( $this, 'headerColumns' ) );//add media library column header
    add_action( 'manage_media_custom_column', array( $this, 'doColumn' ), 10, 2 );//generate the media library column
  }

  public function headerColumns($defaults)
  {
    $defaults['wp-spaatg'] = __('ShortPixel AI Status', 'shortpixel-image-optimiser');


    return $defaults;
  }

  public function doColumn($column_name, $id)
  {
     if($column_name == 'wp-spaatg')
     {
       $this->view = new \stdClass; // reset every row
       $this->view->id = $id;
       $this->loadItem($id);
       $this->loadView(null, false);
      
     }



  }

  protected function loadItem($id)
  {
     $fs = \wpSPAATG()->filesystem();
     $mediaItem = $fs->getMediaImage($id);

		 // Asking for something non-existing.
	 if ($mediaItem === false)
     {
       $this->view->text = __('File Error. This could be not an image or the file is missing', 'shortpixel-image-optimiser');
		 	 return;
     }
     $this->view->mediaItem = $mediaItem;

     $actions = array();
     $list_actions = array();

     $optimizeAiController = OptimizeAiController::getInstance(); 


     if (true === $optimizeAiController->isAiEnabled())
     {
        $aiDataModel = $this->loadAiItem($id);
     }
     else
     {
        $aiDataModel = null; 
     }

    $this->view->text = UiHelper::getStatusText($mediaItem);

		$list_actions = UiHelper::getListActions($mediaItem, $aiDataModel);
    $this->view->list_actions = $list_actions;

    if ( count($this->view->list_actions) > 0)
		{
      $this->view->list_actions = '';
		}
    else
		{
      $this->view->list_actions = '';
		}

		$actions = UiHelper::getActions($mediaItem);
    $this->view->actions = $actions;

		$allActions = array_merge($list_actions, $actions);

    if (
      true === $optimizeAiController->isAiEnabled() &&
      false === is_null($aiDataModel) &&
      0 === strlen(trim(wp_strip_all_tags((string) $this->view->text)))
    ) {
      if (true === $aiDataModel->isSomeThingGenerated()) {
        $this->view->text = '<p>' . $this->getAiGeneratedEditLink($id) . '<!-- eofsngline --></p>';
      } elseif (count($actions) > 0) {
        $this->view->text = '<p>' . esc_html__('No AI-generated SEO data yet', 'shortpixel-image-optimiser') . '<!-- eofsngline --></p>';
      } else {
        $processableStatus = $aiDataModel->getProcessableReason(true);
        $message = ($processableStatus === AiDataModel::P_PROCESSABLE)
          ? __('No AI-generated SEO data yet', 'shortpixel-image-optimiser')
          : $aiDataModel->getProcessableReason();

        $this->view->text = '<p>' . esc_html($message) . '<!-- eofsngline --></p>';
      }
    }

  	$checkBoxActions = array();
    if (array_key_exists('spaatg-generateai', $allActions))
    {
       $checkBoxActions[] = 'ai-action'; 
    }

		$infoData  = array(); // stuff to write as data-tag.

		$this->view->infoClass = implode(' ', $checkBoxActions);
		$this->view->infoData = $infoData;
    //$this->view->actions = $actions;

    if (! $this->userIsAllowed)
    {
      $this->view->actions = array();
      $this->view->list_actions = '';
    }

  }

  protected function getAiGeneratedEditLink($item_id)
  {
    $text = esc_html__('AI-generated SEO data available', 'shortpixel-image-optimiser');
    $editUrl = get_edit_post_link($item_id, 'raw');

    if (empty($editUrl))
    {
      return $text;
    }

    return sprintf(
      '<a href="%s" title="%s">%s</a>',
      esc_url($editUrl),
      esc_attr__('Edit this image to review the generated captions and SEO fields', 'shortpixel-image-optimiser'),
      $text
    );
  }

  protected function loadAiItem($item_id)
  {
     $AiDataModel = AiDataModel::getModelByAttachment($item_id); 
     $this->view->item_id = $item_id;

     $generated_data = $AiDataModel->getGeneratedData(); 
     if ($AiDataModel->isSomeThingGenerated())
     {
        if (isset($generated_data['filebase']))
        {
           unset($generated_data['filebase']);
        }
        $generated_fields = implode(',', array_keys(array_filter($generated_data)));
        $this->view->ai_icon = 'ai'; 
        $this->view->ai_title = sprintf(__('AI-generated image SEO data: %s', 'shortpixel-image-optimiser'), $generated_fields); 

     }
     else
     {
       $this->view->ai_icon = 'no-ai'; 
       $this->view->ai_title = __('No AI-generated SEO data for this image', 'shortpixel-image-optimiser'); 

     }

     return $AiDataModel;


  }

}
