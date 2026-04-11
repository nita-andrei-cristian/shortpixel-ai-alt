<?php
namespace SPAATG\Controller\Queue;

if ( ! defined( 'ABSPATH' ) ) {
 exit; // Exit if accessed directly.
}
// Attempt to standardize what goes around in the queue and keep some overview.

use SPAATG\ShortPixelLogger\ShortPixelLogger as Log;
use SPAATG\Model\Image\ImageModel as ImageModel;
use SPAATG\Model\Queue\QueueItem as QueueItem;

class QueueItems
{

    protected static $items = [
        'media' => [],
        'custom' => [],
    ];

    /**
     * GetImageItem
     *
     * @param ImageModel $imageModel
     * @return QueueItem QueueItem
     */

    public static function getImageItem(ImageModel $imageModel)
    {
        $type = $imageModel->get('type');
        $id = $imageModel->get('id');

        /*
        if (! isset(self::$items[$type][$id]))
        {
            $item = new QueueItem(['imageModel' => $imageModel]);
            self::$items[$type][$id] = $item;
        }

        return self::$items[$type][$id];
        */
        $item = new QueueItem(['imageModel' => $imageModel]);
        return $item;
    }

    public static function getEmptyItem($id, $type)
    {

      $item = new QueueItem(['item_id' => $id, 'type' => $type]);
      return $item;
      /*
      if (! isset(self::$items[$type][$id]))
      {
          $item = new QueueItem(['item_id' => $id, 'type' => $type]);
          self::$items[$type][$id] = $item;
      }

      return self::$items[$type][$id]; */
    }


    /*
      @param int $id of the item
      @param string $type Custom / Media
     */
    public static function getImageItemByID($id, $type)
    {
        $fs = \wpSPAATG()->filesystem();
         $image = $fs->getMediaImage($id, $type);
         if (false !== $image)
         {
            return self::getImageItem($image);
         }
         else {
            return false;
         }

    }



} // class
