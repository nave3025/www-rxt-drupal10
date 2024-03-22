<?php

namespace Drupal\lb_plus\Element;

use Drupal\Core\Url;
use Drupal\Component\Uuid\Php;
use Drupal\Core\Render\Element;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\lb_plus\SectionStorageHandler;
use Drupal\lb_plus\Event\AdminButtonsEvent;
use Drupal\Core\Render\Element\RenderElement;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder\Event\PrepareLayoutEvent;
use Drupal\layout_builder\LayoutBuilderHighlightTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\layout_builder\Context\LayoutBuilderContextTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Defines a render element for the LB+ UI.
 *
 * @RenderElement("layout_builder_plus")
 *
 * @internal
 *   Plugin classes are internal.
 */
class LayoutBuilderPlus extends RenderElement implements ContainerFactoryPluginInterface {

  use AjaxHelperTrait;
  use LayoutBuilderContextTrait;
  use LayoutBuilderHighlightTrait;

  protected Php $uuid;
  protected ?string $nestedStoragePath;
  protected ModuleHandlerInterface $moduleHandler;
  protected ConfigFactoryInterface $configFactory;
  protected SectionStorageInterface $sectionStorage;
  protected EventDispatcherInterface $eventDispatcher;
  protected SectionStorageHandler $sectionStorageHandler;
  protected ?SectionStorageInterface $layoutBlockSectionStorage;
  protected LayoutTempstoreRepositoryInterface $tempstoreRepository;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('layout_builder.tempstore_repository'),
      $container->get('lb_plus.section_storage_handler'),
      $container->get('event_dispatcher'),
      $container->get('module_handler'),
      $container->get('config.factory'),
      $container->get('uuid')
    );
  }

  public function __construct(array $configuration, $plugin_id, $plugin_definition, LayoutTempstoreRepositoryInterface $tempstore_repository, SectionStorageHandler $section_storage_handler, EventDispatcherInterface $event_dispatcher, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory, Php $uuid) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->sectionStorageHandler = $section_storage_handler;
    $this->tempstoreRepository = $tempstore_repository;
    $this->eventDispatcher = $event_dispatcher;
    $this->moduleHandler = $module_handler;
    $this->configFactory = $config_factory;
    $this->uuid = $uuid;
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#section_storage' => NULL,
      '#pre_render' => [
        [$this, 'preRender'],
      ],
    ];
  }

  /**
   * Pre-render callback: Renders the Layout Builder UI.
   */
  public function preRender($element) {
    if ($element['#section_storage'] instanceof SectionStorageInterface) {
      $this->sectionStorage = $element['#section_storage'];
      $this->nestedStoragePath = $element['#nested_storage_path'] ?? NULL;
      if ($this->isLayoutBlock()) {
        $this->layoutBlockSectionStorage = $this->sectionStorageHandler->getCurrentSectionStorage($this->sectionStorage, $this->nestedStoragePath);
        $layout_builder_type = 'layout_block';
      }
      else {
        $layout_builder_type = 'entity';
      }

      $element['layout_builder'] = $this->layout();

      $this->addThemeColors($element);
      LayoutBuilderPlusUI::LbType($layout_builder_type);
    }
    return $element;
  }

  /**
   * Renders the Layout UI.
   *
   * @return array
   *   A render array.
   */
  protected function layout() {
    $this->prepareLayout($this->currentSectionStorage());

    $output = [];
    if ($this->isAjax()) {
      $output['status_messages'] = [
        '#type' => 'status_messages',
      ];
    }
    $count = 0;
    $sections_count = $this->currentSectionStorage()->count();
    if ($sections_count) {
      // Build the admin controls for each section.
      for ($i = 0; $i < $sections_count; $i++) {
        $output[] = $this->buildAdministrativeSection($this->currentSectionStorage(), $count);
        $count++;
      }
    }
    else {
      // Show a default blank page.
      $output['add_block'] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'lb-plus-blank-page',
          'class' => ['blank-page-wrapper'],
        ],
        'background' => [
          '#type' => 'container',
          '#attributes' => [
            'title' => $this->t('Add block'),
            'class' => ['lb-plus-icon'],
            'style' => [
              'background-image: url("/' . $this->moduleHandler->getModule('lb_plus')->getPath() . '/assets/plus.svg");',
            ],
          ],
        ],
        'description' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['blank-page-description']],
          'markup' => ['#markup' => $this->t('Get started by dragging a block from the side bar and dropping it here.')],
        ],
      ];
    }
    $output['#attached']['library'][] = 'lb_plus/layout_builder';

    // As the Layout Builder UI is typically displayed using the frontend theme,
    // it is not marked as an administrative page at the route level even though
    // it performs an administrative task. Mark this as an administrative page
    // for JavaScript.
    $output['#attached']['drupalSettings']['path']['currentPathIsAdmin'] = TRUE;

    $output['#attached']['library'][] = 'lb_plus/layout_builder';
    $output['#attached']['drupalSettings']['LB+'] = [
      'sectionStorageType' => $this->sectionStorage->getStorageType(),
      'sectionStorage' => $this->sectionStorage->getStorageId(),
      'isLayoutBlock' => $this->isLayoutBlock(),
    ];
    if ($this->isLayoutBlock()) {
      $output['#attached']['drupalSettings']['LB+']['nestedStoragePath'] = $this->nestedStoragePath;
      $storage_component_uuid = SectionStorageHandler::decodeNestedStoragePath($this->nestedStoragePath);
      $output['#attributes']['data-nested-storage-uuid'] = end($storage_component_uuid);
    }
    else {
      $output['#attributes']['id'] = 'layout-builder';
    }
    $output['#type'] = 'container';
    $output['#attributes']['class'][] = 'layout-builder';
    $output['#attributes']['class'][] = 'active';
    // Mark this UI as uncacheable.
    $output['#cache']['max-age'] = 0;
    return $output;
  }

  /**
   * Prepares a layout for use in the UI.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   */
  protected function prepareLayout(SectionStorageInterface $section_storage) {
    $event = new PrepareLayoutEvent($section_storage);
    $this->eventDispatcher->dispatch($event, LayoutBuilderEvents::PREPARE_LAYOUT);
  }

  /**
   * Builds the render array for the layout section while editing.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $section_delta
   *   The delta of the section.
   *
   * @return array
   *   The render array for a given section.
   */
  protected function buildAdministrativeSection(SectionStorageInterface $section_storage, $section_delta) {
    $section = $section_storage->getSection($section_delta);
    // Add a UUID so we can keep track during sorting.
    $section_uuid = $section->getThirdPartySetting('lb_plus', 'uuid');
    if (empty($section_uuid)) {
      $section_uuid = $this->uuid->generate();
      $section->setThirdPartySetting('lb_plus', 'uuid', $section_uuid);
      $this->sectionStorageHandler->updateSectionStorage($this->sectionStorage, $this->nestedStoragePath, $section_storage);
    }

    $layout = $section->getLayout($this->getPopulatedContexts($section_storage));

    $contexts = $this->getPopulatedContexts($section_storage);
    if ($this->isLayoutBlock()) {
      // Make the top level parent entity context available to nested layouts.
      $context_id = $this->sectionStorageHandler->mapContextToParentEntity($this->sectionStorage, 'layout_builder.entity');
      $contexts[$context_id] = $this->getPopulatedContexts($this->sectionStorage)[$context_id];
    }

    $section_render = $section->toRenderArray($contexts, TRUE);

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['lb-plus-section', 'hover', 'layout-builder__section'],
        'id' => $section_uuid,
        'data-layout-delta' => $section_delta,
        'data-nested-storage-path' => $this->nestedStoragePath,
        'data-layout-update-url' => Url::fromRoute('lb_plus.js.move_block', [
          'section_storage_type' => $this->sectionStorage->getStorageType(),
          'section_storage' => $this->sectionStorage->getStorageId(),
        ])->toString(),
        'data-layout-builder-highlight-id' => "section-update-$section_delta",
      ],
      'admin_buttons' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['lb-plus-section-admin'],
        ],
        ...$this->eventDispatcher->dispatch(new AdminButtonsEvent($section_render, $this->sectionStorage->getStorageType(), $this->sectionStorage->getStorageId(), $section_delta, $this->nestedStoragePath))->getButtons(),
      ],
      'section' => $section_render,
    ];

    $layout_definition = $layout->getPluginDefinition();
    $build['#layout'] = $layout_definition;

    foreach ($layout_definition->getRegions() as $region => $info) {
      $build['section'][$region]['#attributes']['class'][] = 'layout__region';
      $build['section'][$region]['#attributes']['class'][] = 'js-layout-builder-region';
      $build['section'][$region]['#attributes']['region'] = $region;
      if (!empty($build['section'][$region])) {
        foreach (Element::children($build['section'][$region]) as $uuid) {
          $build['section'][$region][$uuid]['#attributes']['class'][] = 'js-layout-builder-block';
          $build['section'][$region][$uuid]['#attributes']['class'][] = 'layout-builder-block';
          $build['section'][$region][$uuid]['#attributes']['data-block-uuid'] = $uuid;
          $build['section'][$region][$uuid]['#attributes']['data-layout-builder-highlight-id'] = $this->blockUpdateHighlightId($uuid);
          $build['section'][$region][$uuid]['#contextual_links'] = [
            'layout_builder_block' => [
              'route_parameters' => [
                'section_storage_type' => $this->sectionStorage->getStorageType(),
                'section_storage' => $this->sectionStorage->getStorageId(),
                'nested_storage_path' => $this->nestedStoragePath,
                'region' => $region,
                'delta' => $section_delta,
                'uuid' => $uuid,
              ],
              'metadata' => [
                'operations' => 'move:update:remove:duplicate',
              ],
            ],
          ];
          // Add an edit layout contextual link for layout blocks.
          if ($this->sectionStorageHandler->isLayoutBlock($section->getComponent($uuid)->getPlugin())) {
            $nested_storage_path = SectionStorageHandler::encodeNestedStoragePath([
              $section_delta,
              $uuid,
            ]);
            if (!empty($this->nestedStoragePath)) {
              $nested_storage_path = "$this->nestedStoragePath&$nested_storage_path";
            }
            // Edit layout block layout.
            $build['section'][$region][$uuid]['#contextual_links']['lb_plus_layout_block'] = [
              'route_parameters' => [
                'section_storage_type' => $this->sectionStorage->getStorageType(),
                'section_storage' => $this->sectionStorage->getStorageId(),
                'nested_storage_path' => $nested_storage_path,
                'region' => $region,
                'delta' => $section_delta,
                'uuid' => $uuid,
              ],
            ];
          }
        }
      }
    }
    return [
      'layout-builder__section' => $build,
    ];
  }

  /**
   * Add theme colors.
   *
   * @param array $element
   *   The layout builder element.
   */
  private function addThemeColors(array &$element) {
    // Add theme specific colors.
    $colors = $this->configFactory->get('lb_plus.settings')->get('colors');
    $rules = '';
    if (!empty($colors)) {
      foreach($colors as $color => $hex) {
        $rules .= "--lb-plus-$color-color: {$hex};\n";
      }
    }
    else {
      $rules = "--lb-plus-main-color: '#4b9ae4';\n";
    }
    if (!empty($element['#lb_plus_settings_page']) && !empty($colors)) {
      $element['layout_builder']['#attached']['drupalSettings']['LB+']['theme_styles'] = [
        'main' => $colors['main'],
        'secondary' => $colors['secondary'],
      ];
    }
    else if (!empty($rules)){
      $element['layout_builder']['theme_styles'] = [
        '#type' => 'html_tag',
        '#tag' => 'style',
        '#value' => ":root {\n$rules}\n",
      ];
    }
  }

  /**
   * Current section storage.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface
   *   The current section storage.
   */
  private function currentSectionStorage() {
    return $this->layoutBlockSectionStorage ?? $this->sectionStorage;
  }

  /**
   * Is layout block.
   *
   * @return bool
   *   Whether this layout builder is a nested layout block.
   */
  private function isLayoutBlock() {
    return !empty($this->nestedStoragePath);
  }

}
