<?php

namespace Drupal\glossary_modal_link\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Glossary Modal' Block.
 *
 * @Block(
 *   id = "glossary_modal_block",
 *   admin_label = @Translation("Glossary Modal Block"),
 *   category = @Translation("Custom"),
 * )
 */
class GlossaryModalBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'glossary_modal_block',
    ];
  }

}
