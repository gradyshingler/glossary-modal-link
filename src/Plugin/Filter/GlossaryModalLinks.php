<?php

declare(strict_types=1);

namespace Drupal\glossary_modal_link\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Rewrites Linkit glossary term links to open in a modal.
 *
 * @Filter(
 *   id = "glossary_modal_links",
 *   title = @Translation("Glossary links open in modal"),
 *   description = @Translation("Rewrites Linkit taxonomy term links (Glossary vocabulary) to open in a Drupal AJAX modal."),
 *   settings = {
 *     "bootstrap_version" = "5",
 *     "icon_enabled" = TRUE,
 *     "icon_class" = "fas fa-external-link-alt",
 *     "additional_classes" = "",
 *     "view_mode" = "glossary_modal"
 *   },
 *   type = \Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE
 * )
 */
final class GlossaryModalLinks extends FilterBase implements ContainerFactoryPluginInterface {

    private $entityTypeManager;

    private $urlGenerator;

    public function __construct(
        array $configuration,
              $plugin_id,
              $plugin_definition,
        EntityTypeManagerInterface $entityTypeManager,
        UrlGeneratorInterface $urlGenerator
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->entityTypeManager = $entityTypeManager;
        $this->urlGenerator = $urlGenerator;
    }

    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('entity_type.manager'),
            $container->get('url_generator')
        );
    }

    public function settingsForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
        $form['bootstrap_version'] = [
            '#type' => 'select',
            '#title' => $this->t('Bootstrap version'),
            '#options' => [
                '4' => $this->t('Bootstrap 4'),
                '5' => $this->t('Bootstrap 5'),
            ],
            '#default_value' => $this->settings['bootstrap_version'] ?? '5',
            '#description' => $this->t('Select the Bootstrap version used by your theme for modal attributes. Bootstrap 5 will add bs to the data attributes.'),
        ];
        $form['icon_enabled'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Enable Font Awesome icon'),
            '#default_value' => $this->settings['icon_enabled'],
            '#description' => $this->t('Show a Font Awesome icon after the glossary link.'),
        ];
        $form['icon_class'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Font Awesome icon class'),
            '#default_value' => $this->settings['icon_class'] ?? 'fas fa-external-link-alt',
            '#description' => $this->t('Enter the Font Awesome icon class to use for glossary modal links (e.g., <code>fas fa-external-link-alt</code>).'),
            '#required' => TRUE,
        ];
        $form['additional_classes'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Additional classes'),
            '#default_value' => $this->settings['additional_classes'] ?? '',
            '#description' => $this->t('Enter any additional classes to add to the link, separated by spaces.'),
        ];
        $form['view_mode'] = [
            '#type' => 'textfield',
            '#title' => $this->t('View mode'),
            '#default_value' => $this->settings['view_mode'] ?? 'glossary_modal',
            '#description' => $this->t('Enter the machine name of the view mode to use for the modal content. It is up to the site builder to ensure the view mode makes sense in a modal.'),
        ];
        return $form;
    }

    public function settingsSummary() {
        $summary = [];
        $bootstrap_version = $this->settings['bootstrap_version'] ?? '5';
        $summary[] = $this->t('Bootstrap version: @version', ['@version' => $bootstrap_version]);
        $icon_enabled = !empty($this->settings['icon_enabled']);
        $icon_class = $this->settings['icon_class'] ?? 'fas fa-external-link-alt';
        $additional_classes = $this->settings['additional_classes'] ?? '';
        $view_mode = $this->settings['view_mode'] ?? 'glossary_modal';
        $summary[] = $this->t('Font Awesome icon: @enabled', ['@enabled' => $icon_enabled ? 'Enabled' : 'Disabled']);
        $summary[] = $this->t('Font Awesome icon class: <code>@icon</code>', ['@icon' => $icon_class]);
        $summary[] = $this->t('Additional classes: <code>@classes</code>', ['@classes' => $additional_classes]);
        $summary[] = $this->t('View mode: @view_mode', ['@view_mode' => $view_mode]);
        return $summary;
    }

    public function process($text, $langcode): FilterProcessResult {
        // Fast exit if there are no <span data-glossary-id> tags.
        if (stripos($text, 'data-glossary-id') === FALSE) {
            return new FilterProcessResult($text);
        }

        $dom = Html::load($text);
        $xpath = new \DOMXPath($dom);
        $storage = $this->entityTypeManager->getStorage('taxonomy_term');

        // Find all <span> elements with data-glossary-id attribute.
        $nodes = $xpath->query('//span[@data-glossary-id]');

        /** @var \DOMElement $el */
        foreach ($nodes as $el) {
            $glossary_id = $el->getAttribute('data-glossary-id');
            $glossary_uuid = $el->getAttribute('data-glossary-uuid');
            $glossary_href = $el->getAttribute('data-glossary-path');
            $button_display_text = $el->getAttribute('data-glossary-display-text');
            $entity_type = $el->getAttribute('data-glossary-type');
            if (!$glossary_id) {
                continue;
            }

//      // Try to determine entity type from the href.
//      $matches = [];
//      if (!preg_match('/^\/(?<entity_type>[a-z_]+)\//', $glossary_href, $matches)) {
//        continue;
//      }
//      $entity_type = $matches['entity_type'];

            // Load entity by UUID.
            $storage = $this->entityTypeManager->getStorage($entity_type);
            $entities = $storage->loadByProperties(['uuid' => $glossary_uuid]);
            $entity = $entities ? reset($entities) : NULL;
            if (!$entity) {
                continue;
            }

            // Create <a> element to replace <span>.
            // TODO: have a filter option that selects the button view mode to use
            $view_builder = \Drupal::entityTypeManager()->getViewBuilder($entity_type);
            $content = $view_builder->view($entity, "inline_reference_link");
            if (!empty($button_display_text)) {
                $content['#button_display_text'] = $button_display_text;
            }
            $rendered_node = \Drupal::service('renderer')->renderRoot($content);

//      $a = $dom->createElement('a');
//      $a->setAttribute('href', '#');
//      $a->setAttribute('data-ajax-url', $this->urlGenerator->generateFromRoute('glossary_modal_link.entity_modal', [
//        'entity_type' => $entity->getEntityTypeId(),
//        'entity' => $entity->id(),
//        'view_mode' => $this->settings['view_mode'] ?? 'glossary_modal',
//      ]));
//      $bootstrap_version = $this->settings['bootstrap_version'] ?? '5';
//      if ($bootstrap_version === '4') {
//        $a->setAttribute('data-toggle', 'modal');
//        $a->setAttribute('data-target', '#glossaryModal');
//      }
//      else {
//        $a->setAttribute('data-bs-toggle', 'modal');
//        $a->setAttribute('data-bs-target', '#glossaryModal');
//      }
//      $a->setAttribute('data-entity-uuid', $glossary_uuid);
//      $classes = ['glossary-modal-link'];
//      $additional_classes = $this->settings['additional_classes'] ?? '';
//      if (!empty($additional_classes)) {
//        $classes = array_merge($classes, explode(' ', $additional_classes));
//      }
//      $a->setAttribute('class', implode(' ', $classes));
//      // Add the content attributes for the modal (title, button, and size)
//      $a->setAttribute('data-modal-title', $entity->label());
//      $a->setAttribute('data-modal-size', "xl");
//      // Add the display text as a text node.
//      $textNode = $dom->createTextNode($el->nodeValue . ' ');
//      $a->appendChild($textNode);
//      // Add the Font Awesome icon if enabled.
//      if (!empty($this->settings['icon_enabled'])) {
//        $icon = $dom->createElement('i');
//        $icon_class = $this->settings['icon_class'] ?? 'fas fa-external-link-alt';
//        $icon->setAttribute('class', $icon_class);
//        $icon->setAttribute('aria-hidden', 'true');
//        $a->appendChild($icon);
//      }
            // Replace <span> with <a>.
//      $el->parentNode->replaceChild($a, $el);
            // Create a new DOMDocument to parse the rendered node string.
            $temp_doc = Html::load((string) $rendered_node);
            $body_node = $temp_doc->getElementsByTagName('body')->item(0);
            foreach ($body_node->childNodes as $child_node) {
                $imported_node = $dom->importNode($child_node, TRUE);
                $el->parentNode->insertBefore($imported_node, $el);
            }
            $el->parentNode->removeChild($el);
        }

        return new FilterProcessResult(Html::serialize($dom));
    }

}
