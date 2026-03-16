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
 *   description = @Translation("Rewrites Linkit links (Glossary vocabulary) to open in a Drupal AJAX modal."),
 *   settings = {
 *     "bootstrap_version" = "5",
 *     "icon_enabled" = TRUE,
 *     "icon_class" = "fas fa-external-link-alt",
 *     "additional_classes" = "",
 *     "view_mode" = "glossary_modal",
 *     "button_view_mode" = "inline_reference_link"
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
            '#default_value' => $this->settings['icon_enabled'] ?? TRUE,
            '#description' => $this->t('Show a Font Awesome icon after the glossary link.'),
        ];
        $form['icon_class'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Font Awesome icon class'),
            '#default_value' => $this->settings['icon_class'] ?? 'fas fa-external-link-alt',
            '#description' => $this->t('Enter the Font Awesome icon class to use for glossary modal links (e.g., <code>fas fa-external-link-alt</code>). <em>Note: The icon will only appear when rendered on the front end, not in the CKEditor</em>'),
            '#required' => TRUE,
        ];
        $form['additional_classes'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Additional classes'),
            '#default_value' => $this->settings['additional_classes'] ?? '',
            '#description' => $this->t('Enter any additional classes to add to the link, separated by spaces.'),
        ];
        $form['modal_view_mode'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Modal View mode'),
            '#default_value' => $this->settings['modal_view_mode'] ?? 'glossary_modal',
            '#description' => $this->t('Enter the machine name of the view mode to use for the modal content. It is up to the site builder to ensure the view mode makes sense in a modal.'),
        ];
        $form['button_view_mode'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Button View Mode'),
            '#default_value' => $this->settings['button_view_mode'] ?? 'inline_reference_link',
            '#description' => $this->t('Enter the machine name of the view mode to use for the inline button. A special view mode that renders the content as a button with inline styles is recommended.'),
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
        $modal_view_mode = $this->settings['modal_view_mode'] ?? 'glossary_modal';
        $button_view_mode = $this->settings['button_view_mode'] ?? 'inline_reference_link';
        $summary[] = $this->t('Font Awesome icon: @enabled', ['@enabled' => $icon_enabled ? 'Enabled' : 'Disabled']);
        $summary[] = $this->t('Font Awesome icon class: <code>@icon</code>', ['@icon' => $icon_class]);
        $summary[] = $this->t('Additional classes: <code>@classes</code>', ['@classes' => $additional_classes]);
        $summary[] = $this->t('View mode: @view_mode', ['@view_mode' => $modal_view_mode]);
        return $summary;
    }

    public function process($text, $langcode): FilterProcessResult {
        // Fast exit if there are no <span data-glossary-id> tags.
        if (stripos($text, 'data-glossary-id') === FALSE) {
            return new FilterProcessResult($text);
        }

        $dom = Html::load($text);
        $xpath = new \DOMXPath($dom);

        // Find all <span> elements with data-glossary-id attribute.
        $nodes = $xpath->query('//span[@data-glossary-id]');

        /** @var \DOMElement $el */
        foreach ($nodes as $el) {

            $glossary_id = $el->getAttribute('data-glossary-id');
            $glossary_uuid = $el->getAttribute('data-glossary-uuid');
            $button_display_text = $el->getAttribute('data-glossary-display-text');
            $entity_type = $el->getAttribute('data-glossary-type');
            if (!$glossary_id) {
                continue;
            }

            // Load entity by UUID.
            $storage = $this->entityTypeManager->getStorage($entity_type);
            $entities = $storage->loadByProperties(['uuid' => $glossary_uuid]);
            $entity = $entities ? reset($entities) : NULL;
            if (!$entity) {
                continue;
            }

            // Get button view mode from settings, fallback to 'inline_reference_link'.
            $button_view_mode = $this->settings['button_view_mode'] ?? 'inline_reference_link';
            $modal_view_mode = $this->settings['modal_view_mode'] ?? 'inline_reference_link';
            $icon_enabled = !empty($this->settings['icon_enabled']);
            $icon_class = $this->settings['icon_class'] ?? 'fas fa-external-link-alt';
            $additional_classes = $this->settings['additional_classes'] ?? '';

            // Create <a> element to replace <span>.
            $view_builder = \Drupal::entityTypeManager()->getViewBuilder($entity_type);
            $content = $view_builder->view($entity, $button_view_mode);
            if (!empty($button_display_text)) {
                $content['#button_display_text'] = $button_display_text;
            }

            // Build attributes for the button.
            $attributes = [
                'class' => array_merge(['glossary-modal-link', 'btn', 'btn-light', 'btn-sm', 'py-0', 'mx-0'], $additional_classes ? explode(' ', $additional_classes) : []),
                // Utility Data
                'data-bs-target' => '#glossaryModal',
                'data-bs-toggle' => 'modal',
                'data-ajax-url' => $this->urlGenerator->generateFromRoute('glossary_modal_link.entity_modal', [
                    'entity_type' => $entity_type,
                    'entity' => $entity->id(),
                    'view_mode' => $modal_view_mode,
                ]),
                'data-node-view' => 'modal',
                // Content Data
                'data-modal-title' => $entity->label(),
                'data-node-id' => $entity->id(),
                'data-modal-size' => 'xl',
                // Button Label
                'data-button-label' => "View Glossary",//$button_display_text,
                'data-button-link' => "/glossary#glossary-id-{$entity->id()}",//$entity->toUrl()->toString(),
                'data-disabled-status' => 'false',
            ];

            // Build the button render array using the theme hook.
            $button = [
                '#theme' => 'glossary_modal_link_button',
                '#entity' => $entity,
                '#attributes' => $attributes,
                '#button_display_text' => $button_display_text,
                '#url' => $entity->toUrl()->toString(),
                '#icon_enabled' => $icon_enabled,
                '#icon_class' => $icon_class,
            ];

            $rendered_node = \Drupal::service('renderer')->renderRoot($button);

            // Normalize rendered markup so DOM parsing doesn't introduce stray
            // whitespace/text nodes from newlines.
            $rendered_node_string = (string) $rendered_node;
            $rendered_node_string = str_replace(["\r\n", "\n", "\r"], '', $rendered_node_string);
            // Collapse any remaining runs of whitespace between tags.
            $rendered_node_string = preg_replace('/>\s+</', '><', $rendered_node_string) ?? $rendered_node_string;

            // Create a new DOMDocument to parse the rendered node string.
            $temp_doc = Html::load($rendered_node_string);
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
