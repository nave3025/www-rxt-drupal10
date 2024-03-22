<?php

namespace Drupal\lb_plus\Element;

use Drupal\Core\Url;
use Drupal\lb_plus\LbPlusSettingsTrait;
use Drupal\lb_plus\SectionStorageHandler;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\layout_builder\Context\LayoutBuilderContextTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

/**
 * LB+ UI.
 */
class LayoutBuilderPlusUI implements ContainerInjectionInterface {

  use LbPlusSettingsTrait;
  use StringTranslationTrait;
  use LayoutBuilderContextTrait;

  protected CurrentRouteMatch $routeMatch;
  protected BlockManagerInterface $blockManager;
  protected ModuleHandlerInterface $moduleHandler;
  protected SectionStorageHandler $sectionStorageHandler;
  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(SectionStorageHandler $section_storage_handler, BlockManagerInterface $block_manager, EntityTypeManagerInterface $entity_type_manager, CurrentRouteMatch $current_route_match, ModuleHandlerInterface $module_handler) {
    $this->sectionStorageHandler = $section_storage_handler;
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $current_route_match;
    $this->moduleHandler = $module_handler;
    $this->blockManager = $block_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lb_plus.section_storage_handler'),
      $container->get('plugin.manager.block'),
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('module_handler'),
    );
  }

  /**
   * Build LB+ UI.
   *
   * Builds the side bar part of the LB+ UI. The bottom bar is the form actions
   * fixed to the bottom of the page. See OverridesEntityForm for the bottom bar.
   *
   * @param array $page_bottom
   *   The page_bottom render array.
   *
   * @throws \Exception
   */
  public function build(array &$page_bottom) {
    $layout_builder_type = self::LbType();
    if (empty($layout_builder_type) || $this->routeMatch->getRouteName() === 'lb_plus.settings') {
      // This is not a layout builder page. Don't show the sidebar.
      return;
    }
    $parameters = $this->routeMatch->getParameters();
    // Include a side bar for adding blocks to the layout.
    $page_bottom['sidebar_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'lb-plus-sidebar-wrapper',
        'class' => ["lb-plus-sidebar-$layout_builder_type"],
        'data-offset-right' => '350',
      ],
      'sidebar' => $this->buildSidebar($layout_builder_type, $parameters->get('section_storage'), $parameters->get('nested_storage_path')),
    ];
  }

  /**
   * Show LB+ UI.
   *
   * Flags that there is a layout builder element on the page and we should
   * render the LB+ UI.
   *
   * @param string $type
   *   Either "entity" or "layout_block".
   *
   * @return string
   *   The type of layout builder. Either "entity" meaning the main/top level
   *   entity with the parent section storage or "layout_block" meaning a layout
   *   builder that has been nested inside the section storage of another entity.
   */
  public static function LbType(string $type = NULL) {
    $flag = &drupal_static(__FUNCTION__);
    if (!isset($flag) || !empty($type)) {
      $flag = $type;
    }

    return $flag;
  }

  /**
   * Build sidebar.
   *
   * @param string $section_storage_type
   *   Whether the layout builder is the main entity or a nested layout block.
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return array
   *   A sidebar render array.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function buildSidebar(string $layout_builder_type, SectionStorageInterface $section_storage, string $nested_storage_path = NULL) {
    $current_section_storage = $this->sectionStorageHandler->getCurrentSectionStorage($section_storage, $nested_storage_path);
    $path = $this->moduleHandler->getModule('lb_plus')->getPath();
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'lb-plus-sidebar',
      ],
      '#attached' => ['library' => ['lb_plus/lb_plus_ui']],
    ];

    // Add a tabbed layout to toggle between promoted blocks and the rest of them.
    $build['tabs'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'choose-block-tabs'],
      'promoted' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'promoted-block',
          'class' => ['choose-block-tab', 'active'],
        ],
        'markup' => ['#markup' => $this->t('Promoted')],
      ],
      'other' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'other-block',
          'class' => ['choose-block-tab'],
        ],
        'markup' => ['#markup' => $this->t('Other')],
      ],
      'close' => [
        '#type' => 'container',
        '#attributes' => [
          'title' => t('Close'),
          'id' => 'close-add-block-sidebar',
        ],
        'background' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['lb-plus-icon'],
            'style' => [
              'background-image: url("/' . $this->moduleHandler->getModule('lb_plus')->getPath() . '/assets/plus.svg");',
            ],
          ],
        ],
      ],
    ];

    $block_definitions = $this->blockManager->getDefinitions();
    $promoted_block_ids = $this->getLbPlusSetting($current_section_storage, 'promoted_blocks');
    $blocks_config = $this->getLbPlusSetting($current_section_storage, 'block_config');

    $blocks = [];
    foreach ($promoted_block_ids as $promoted_block_id) {
      $promoted_block_definition = $block_definitions[$promoted_block_id];
      $icon_path = $blocks_config['icon'][$promoted_block_id] ?? '/' . $path . '/assets/default-block-icon.svg';
      $blocks[] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => $promoted_block_id,
          'class' => ['draggable-block'],
          'draggable' => 'true',
        ],
        'icon' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['draggable-block-image'],
            'style' => ["background-image: url('$icon_path');"],
          ],
        ],
        'label' => ['#markup' => "<div class='draggable-block-label'>{$promoted_block_definition['admin_label']}</div>"],
      ];
    }

    $build['promoted_blocks'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'promoted-block-content',
        'class' => ['tabbed-content', 'active'],
      ],
    ];
    if (!empty($blocks)) {
      $build['promoted_blocks']['blocks'] = $blocks;
      // Let users place an empty section.
      $build['promoted_blocks']['blocks'][] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'empty-section',
          'class' => ['draggable-section'],
          'draggable' => 'true',
        ],
        'icon' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['draggable-block-image'],
            'style' => ['background-image: url("/' . $this->moduleHandler->getModule('lb_plus')->getPath() . '/assets/section.svg");'],
          ],
        ],
        'label' => ['#markup' => t("<div class='draggable-block-label'>Add empty Section</div>")],
      ];
    }
    else {
      // Give users a link to promote blocks if there are none.
      $entity_view_display_id = $this->loadEntityViewDisplay($current_section_storage)->id();
      $build['promoted_blocks']['blocks'] = [
        '#markup' => $this->t('No blocks have been promoted. Click <a href="@url">here</a> to promote some.', [
          '@url' => Url::fromRoute('lb_plus.settings.promoted_blocks', [
            'entity' => $entity_view_display_id,
          ])->toString(),
        ]),
      ];
    }

    $build['other_blocks'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'other-block-content',
        'class' => ['tabbed-content'],
      ],
    ];
    $build['other_blocks']['filter'] = [
      '#type' => 'search',
      '#title' => $this->t('Filter by block name'),
      '#title_display' => 'invisible',
      '#size' => 30,
      '#placeholder' => $this->t('Filter by block name'),
      '#attributes' => [
        'class' => ['js-layout-builder-filter'],
        'title' => $this->t('Enter a part of the block name to filter by.'),
      ],
    ];
    $block_categories['#type'] = 'container';
    $block_categories['#attributes']['class'][] = 'block-categories';
    $block_categories['#attributes']['class'][] = 'js-layout-builder-categories';

    $definitions = $this->blockManager->getFilteredDefinitions('layout_builder', $this->getPopulatedContexts($current_section_storage), [
      'section_storage' => $current_section_storage,
    ]);
    if ($layout_builder_type === 'layout_block') {
      // Include fields from the parent entity.
      $parent_definitions = $this->blockManager->getFilteredDefinitions('layout_builder', $this->getPopulatedContexts($section_storage), [
        'section_storage' => $section_storage,
      ]);
      foreach ($parent_definitions as $name => $parent_definition) {
        if (str_contains($name, 'field_block:')) {
          $definitions[$name] = $parent_definition;
        }
      }
    }
    $grouped_definitions = $this->blockManager->getGroupedDefinitions($definitions);
    foreach ($grouped_definitions as $category => $blocks) {
      $block_categories[$category]['#type'] = 'details';
      $block_categories[$category]['#attributes']['class'][] = 'js-layout-builder-category';
      $block_categories[$category]['#open'] = TRUE;
      $block_categories[$category]['#title'] = $category;
      $block_categories[$category]['blocks'] = $this->getBlocks($blocks);
    }
    $build['other_blocks']['block_categories'] = $block_categories;

    return $build;
  }

  /**
   * Gets a render array of draggable blocks.
   *
   * @param array $blocks
   *   The information for each block.
   *
   * @return array
   *   The block links render array.
   */
  protected function getBlocks(array $blocks) {
    $draggable_blocks = [];
    foreach ($blocks as $block_id => $block) {
      $draggable_blocks[] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => $block_id,
          'class' => ['draggable-block', 'js-layout-builder-block-link'],
          'draggable' => 'true',
        ],
        'label' => ['#markup' => "<div class='draggable-block-label'>{$block['admin_label']}</div>"],
      ];
    }
    return $draggable_blocks;
  }

  protected function entityTypeManager(): EntityTypeManagerInterface {
    return $this->entityTypeManager;
  }

}
