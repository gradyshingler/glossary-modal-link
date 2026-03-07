<?php

namespace Drupal\glossary_modal_link\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GlossaryModalController extends ControllerBase {
  private RendererInterface $renderer;

  public function __construct(RendererInterface $renderer) {
    $this->renderer = $renderer;
  }

  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('renderer')
    );
  }

  public function title(EntityInterface $entity): string {
    return $entity->label();
  }

  public function entityViewModeRetrieval(string $entity_type, $entity, string $view_mode = 'default') {
    $node = Node::load($entity->id());
    if ($node) {
      $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
      $content = $view_builder->view($node, $view_mode);

      $rendered_node = \Drupal::service('renderer')->renderRoot($content);
      $response = new \Symfony\Component\HttpFoundation\Response($rendered_node);
      return $response;
    }
    return ['#markup' => $this->t('Content not found.')];
  }
}
