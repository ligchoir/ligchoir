<?php

namespace Drupal\modifiers_bg_image\Plugin\modifiers;

use Drupal\modifiers\Modification;
use Drupal\modifiers\ModifierPluginBase;

/**
 * Provides a Modifier to set the image background on an element.
 *
 * @Modifier(
 *   id = "image_bg_modifier",
 *   label = @Translation("Image Background Modifier"),
 *   description = @Translation("Provides a Modifier to set the image background on an element"),
 * )
 */
class ImageBgModifier extends ModifierPluginBase {

  /**
   * {@inheritdoc}
   */
  public static function modification($selector, array $config) {

    if (!empty($config['image'])) {
      $media = parent::getMediaQuery($config);

      $css[$media][$selector][] = 'background-image:url("' . $config['image'] . '")';
      $attributes[$media][$selector]['class'][] = 'modifiers-has-background';

      // if the image style only accepts 1 value, as did originally,
      // then we're dealing with a string to put csss
      //
      // single value - config: cardinality: 1 in:
      // set web/modules/contrib/modifiers_pack/modules/modifiers_bg_image/config/optional/field.storage.paragraph.field_mod_image_style.yml
      //
      if (gettype($config['image_style']) == "string") {
        $css[$media][$selector][] .= self::_populateCssStylesFromSelection($config['image_style']);
      }
      else {
        // if the style input field has been configured to accept multiple values
        // then we are dealing with an array
        //
        // multiple values - config: cardinality: -1 in:
        // set web/modules/contrib/modifiers_pack/modules/modifiers_bg_image/config/optional/field.storage.paragraph.field_mod_image_style.yml
        //
        if (gettype($config['image_style']) == "array") {
          foreach($config['image_style'] as $index => $style_setting) {
            $css[$media][$selector][] 
              .= self::_populateCssStylesFromSelection($style_setting)
              .' ';
          }
        }
      }

      if (!empty($config['image_position'])) {
        $position = str_replace('-', ' ', $config['image_position']);
        $css[$media][$selector][] = 'background-position:' . $position;
      }
      if (!empty($config['bgi_color_val'])) {
        $css[$media][$selector][] = 'background-color:' . $config['bgi_color_val'];
      }

      return new Modification($css, [], [], $attributes);
    }
    return NULL;
  }

  /**
   * helper function
   * 
   * generates css from style setting provided by user as input into the modifier settings form
   * 
   */
  private static function _populateCssStylesFromSelection($style_setting) {
    $css_statement = "";
    
    if (!empty($style_setting)) {
      switch ($style_setting) {

        case 'stretch':
          $css_statement = 'background-size:cover';
          break;

        case 'no-repeat':
        case 'repeat':
        case 'repeat-x':
        case 'repeat-y':
          $css_statement = 'background-repeat:' . $style_setting;
          break;
      }
    }

    return $css_statement;
  }

}
