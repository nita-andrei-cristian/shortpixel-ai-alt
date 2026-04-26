<?php
foreach($this->view->actions as $actionName => $action):

  $layout = isset($action['layout']) ? $action['layout'] : false;
  $disabled = ! empty($action['disabled']);
  $itemId = property_exists($this->view, 'id') ? intval($this->view->id) : 0;


  if (isset($action['display']))
  {
     $display = $action['display'];
     $classes = $actionName;
     switch($display)
     {
         case 'button':
           $classes = " button-smaller button-primary $actionName ";
         break;
         case 'button-secondary':
           $classes = " button-smaller button button-secondary $actionName ";
         break;
     }
  }

  if ($disabled)
  {
    $classes .= ' disabled';
  }

  $link = $disabled ? 'javascript:void(0)' : (($action['type'] == 'js') ? 'javascript:' . $action['function'] : $action['function']);

  $title = isset($action['title']) ? ' title="' . esc_attr($action['title']) . '" ' : '';
  $disabledAttrs = $disabled ? ' aria-disabled="true" tabindex="-1" ' : '';
  $actionAttrs = '';

  if ($itemId > 0)
  {
    $actionAttrs .= ' data-spaatg-action-id="' . esc_attr($itemId) . '" data-spaatg-action-name="' . esc_attr($actionName) . '"';

    if (! empty($action['ai-action']))
    {
      $actionAttrs .= ' data-spaatg-ai-action-id="' . esc_attr($itemId) . '"';
    }
  }

  if ($layout && $layout == 'paragraph')
  {
     echo "<P>";
  }
  ?>
  <a href="<?php echo esc_attr($link) ?>" <?php echo $title . $disabledAttrs . $actionAttrs ?> class="<?php echo esc_attr($classes) ?>"><?php echo esc_html($action['text']) ?></a>

  <?php
    if ($layout && $layout == 'paragraph')
    {
       echo "</P>";
    }
  ?>


  <?php
endforeach;
