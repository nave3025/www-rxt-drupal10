<?php

namespace Drupal\lb_plus\Controller;

use Drupal\Component\Uuid\Php;
use Drupal\Core\Render\Element;
use Drupal\Core\Database\Database;
use Drupal\layout_builder\Section;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\lb_plus\LbPlusRebuildTrait;
use Drupal\lb_plus\LbPlusSettingsTrait;
use Drupal\lb_plus\SectionStorageHandler;
use Drupal\lb_plus\Event\PlaceBlockEvent;
use Drupal\Core\Controller\ControllerBase;
use Drupal\layout_builder\SectionComponent;
use Drupal\Core\Layout\LayoutPluginManager;
use Drupal\Core\Block\BlockManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder\Plugin\Block\FieldBlock;
use Drupal\Core\Plugin\ContextAwarePluginAssignmentTrait;
use Drupal\layout_builder\Context\LayoutBuilderContextTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Returns responses for Layout Builder + routes.
 */
class DropZones extends ControllerBase {

  use ContextAwarePluginAssignmentTrait;
  use LayoutBuilderContextTrait;
  use LbPlusSettingsTrait;
  use LbPlusRebuildTrait;

  protected Php $uuid;
  protected LayoutPluginManager $layoutManager;
  protected BlockManagerInterface $blockManager;
  protected EventDispatcherInterface $eventDispatcher;
  protected SectionStorageHandler $sectionStorageHandler;
  protected LayoutTempstoreRepositoryInterface $layoutTempstoreRepository;

  public function __construct(LayoutTempstoreRepositoryInterface $tempstore_repository, SectionStorageHandler $section_storage_handler, LayoutPluginManager $layout_manager, BlockManagerInterface $block_manager, EventDispatcherInterface $event_dispatcher, Php $uuid) {
    $this->layoutTempstoreRepository = $tempstore_repository;
    $this->sectionStorageHandler = $section_storage_handler;
    $this->eventDispatcher = $event_dispatcher;
    $this->layoutManager = $layout_manager;
    $this->blockManager = $block_manager;
    $this->uuid = $uuid;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.tempstore_repository'),
      $container->get('lb_plus.section_storage_handler'),
      $container->get('plugin.manager.core.layout'),
      $container->get('plugin.manager.block'),
      $container->get('event_dispatcher'),
      $container->get('uuid')
    );
  }

  /**
   * Move section.
   *
   * Stores the updated position after a section has been moved and reordered.
   */
  public function moveSection(Request $request, SectionStorageInterface $section_storage) {
    try {
      $transaction = Database::getConnection()->startTransaction();
      // Get the section info from drop-zones.js.
      $from_section_delta = $request->get('from_section_delta');
      $preceding_section_delta = $request->get('preceding_section_delta');
      $nested_storage_path_to = $request->get('nested_storage_path_to');
      $nested_storage_path_from = $request->get('nested_storage_path_from');

      // Get the section storage where the section will be placed.
      $section_storage_to = $this->sectionStorageHandler->getCurrentSectionStorage($section_storage, $nested_storage_path_to);

      // Remove the section from the "from" section storage.
      if ($nested_storage_path_to !== $nested_storage_path_from) {
        $from_section_storage = $this->sectionStorageHandler->getCurrentSectionStorage($section_storage, $nested_storage_path_from);
        $section = $from_section_storage->getSection($from_section_delta);
        $from_section_storage->removeSection($from_section_delta);
        // Update the from section storage.
        $section_storage = $this->sectionStorageHandler->updateSectionStorage($section_storage, $nested_storage_path_from, $from_section_storage);
        $section_storage_to = $this->sectionStorageHandler->getCurrentSectionStorage($section_storage, $nested_storage_path_to);
      } else {
        $section = $section_storage_to->getSection($from_section_delta);
        $section_storage_to->removeSection($from_section_delta);
      }

      // If the section was moved from higher to lower on the page we need to
      // account for the delta's changing after it was removed.
      $new_delta = $from_section_delta < $preceding_section_delta ? $preceding_section_delta - 1 : $preceding_section_delta;
      $section_storage_to->insertSection($new_delta, $section);

      $section_storage = $this->sectionStorageHandler->updateSectionStorage($section_storage, $nested_storage_path_to, $section_storage_to);

      // Prepare an updated Layout Builder UI response.
      $response = new AjaxResponse();
      $response->addCommand(new RemoveCommand('[data-drupal-messages]'));
      $layout = [
        '#type' => 'layout_builder_plus',
        '#section_storage' => $section_storage,
        '#nested_storage_path' => $nested_storage_path_to,
      ];
      $selector = '#layout-builder';
      if (!empty($nested_storage_path_to)) {
        // Replace the nested layout.
        $pieces = SectionStorageHandler::decodeNestedStoragePath($nested_storage_path_to);
        $storage_uuid = end($pieces);
        $selector = "[data-nested-storage-uuid='$storage_uuid']";
      }
      $response->addCommand(new ReplaceCommand($selector, $layout));

      return $response;
    }
    catch (\Exception $e) {
      if (isset($transaction)) {
        $transaction->rollBack();
      }
      throw $e;
    }
  }

  /**
   * Move section.
   *
   * Stores the updated position after a section has been moved and reordered.
   */
  public function addEmptySection(Request $request, SectionStorageInterface $section_storage, string $nested_storage_path = NULL) {
    $current_section_storage = $this->sectionStorageHandler->getCurrentSectionStorage($section_storage, $nested_storage_path);
    // Get the new next_section from drop-zones.js.
    $next_section = $request->get('preceding_section');

    // Place the a blank section.
    $this->getSection([
      'type' => 'section',
      'section' => $next_section,
    ], $current_section_storage);

    $section_storage = $this->sectionStorageHandler->updateSectionStorage($section_storage, $nested_storage_path, $current_section_storage);

    return $this->rebuildLayout($section_storage, $nested_storage_path);
  }

  /**
   * Place block.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param string|null $nested_storage_path
   *   The nested storage path.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The layout builder UI with the updated block placement.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function placeBlock(Request $request, SectionStorageInterface $section_storage, string $nested_storage_path = NULL) {
    $current_section_storage = $this->sectionStorageHandler->getCurrentSectionStorage($section_storage, $nested_storage_path);

    // Get the block information from block.js.
    $block_info = $request->get('place_block');
    if (empty($block_info)) {
      throw new \InvalidArgumentException('Missing block placement info.');
    }
    // Ensure we have a valid block plugin ID.
    $block_definitions = $this->blockManager->getDefinitions();
    if (empty($block_definitions[$block_info['plugin_id']])) {
      throw new \InvalidArgumentException('Invalid block_plugin_id');
    }

    // Ensure we have a valid destination type.
    if (!in_array($block_info['destination']['type'], ['region', 'section'])) {
      throw new \InvalidArgumentException('Invalid block_plugin_id');
    }

    $section = $this->getSection($block_info['destination'], $current_section_storage);
    if (is_null($section->getLayoutId())) {
      throw new \InvalidArgumentException('Please configure a default layout for this section.');
    }

    // Ensure we have a valid region.
    if ($block_info['destination']['type'] === 'region' && empty($this->layoutManager->getDefinition($section->getLayoutId())->getRegions()[$block_info['destination']['region']])) {
      throw new \InvalidArgumentException('Invalid region to place block.');
    }
    $region = $block_info['destination']['region'];
    if ($block_info['destination']['type'] !== 'region') {
      $region = $section->getDefaultRegion();
    }

    // Create a block to place.
    $block_plugin = $this->blockManager->createInstance($block_info['plugin_id']);

    // Add context mapping to the configuration. Field blocks especially expect
    // context mapping.
    $configuration = $block_plugin->getConfiguration();
    $contexts = $this->getPopulatedContexts($current_section_storage);
    $assigned_context_element = $this->addContextAssignmentElement($block_plugin, $contexts);
    foreach (Element::children($assigned_context_element) as $key) {
      $configuration['context_mapping'][$key] = $assigned_context_element[$key]['#value'];
    }
    if (!empty($configuration['context_mapping']) && empty($configuration['context_mapping']['entity']) && array_key_exists('entity', $configuration['context_mapping'])) {
      // Multiple context options are available, but I think we always want the
      // layout_builder.entity context here if there's no default set.
      $configuration['context_mapping']['entity'] = 'layout_builder.entity';
    }

    [$block_plugin_id, $bundle] = explode(':', $block_plugin->getPluginId());

    if ($block_plugin_id === 'inline_block') {
      // Create a block content entity with placeholder content.
      $block_content = $this->entityTypeManager()->getStorage('block_content')->create([
        'type' => $bundle,
        'reusable' => FALSE,
      ]);
      foreach ($block_content as $field) {
        if ($field->getFieldDefinition()->getFieldStorageDefinition()->isBaseField()) {
          continue;
        }
        if ($this->moduleHandler()->moduleExists('field_sample_value')) {
          \Drupal::service('field_sample_value.generator')->populateWithSampleValue($field);
        } else {
          $field->generateSampleItems();
        }
      }
      $configuration['block_serialized'] = serialize($block_content);
    }

    $configuration['label_display'] = 0;
    $block_plugin->setConfiguration($configuration);
    $this->eventDispatcher->dispatch(new PlaceBlockEvent($block_plugin), PlaceBlockEvent::class);

    // Add the new block to the section.
    $component = new SectionComponent($this->uuid->generate(), $region, ['id' => $block_info['plugin_id']]);
    $component->setConfiguration($block_plugin->getConfiguration());

    if (!empty($block_info['destination']['preceding_block_uuid']) && $block_info['destination']['preceding_block_uuid'] !== 'null') {
      $section->insertAfterComponent($block_info['destination']['preceding_block_uuid'], $component);
    }
    else {
      // Insert to first place.
      $section->insertComponent(0, $component);
    }

    $section_storage = $this->sectionStorageHandler->updateSectionStorage($section_storage, $nested_storage_path, $current_section_storage);

    return $this->rebuildLayout($section_storage, $nested_storage_path);
  }

  /**
   * Move block.
   *
   * Moves an existing block from one place on the page to another.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The updated layout builder.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function moveBlock(Request $request, SectionStorageInterface $section_storage) {
    try {
      $transaction = Database::getConnection()->startTransaction();
      // Get the block information from block.js.
      $block_info = $request->get('place_block');
      if (empty($block_info)) {
        throw new \InvalidArgumentException('Missing block placement info.');
      }

      // Ensure we have a valid destination type.
      if (!in_array($block_info['destination']['type'], ['region', 'section'])) {
        throw new \InvalidArgumentException('Invalid block destination type');
      }

      $delta_from = $block_info['destination']['delta_from'] ?? NULL;
      $delta_to = $block_info['destination']['delta_to'] ?? NULL;
      $region_to = $block_info['destination']['region_to'] ?? NULL;
      $block_uuid = $block_info['destination']['block_uuid'] ?? NULL;
      $preceding_block_uuid = $block_info['destination']['preceding_block_uuid'] ?? NULL;
      $nested_storage_path_from = $block_info['destination']['nested_storage_path_from'] ?? NULL;
      $nested_storage_path_to = $block_info['destination']['nested_storage_path_to'] ?? NULL;
      $changing_section_storage = $nested_storage_path_to !== $nested_storage_path_from;

      // Get the section storage where the block will be placed.
      $section_storage_to = $this->sectionStorageHandler->getCurrentSectionStorage($section_storage, $nested_storage_path_to);

      // Remove the component from the section storage.
      if ($changing_section_storage) {
        $from_section_storage = $this->sectionStorageHandler->getCurrentSectionStorage($section_storage, $nested_storage_path_from);
        $section = $from_section_storage->getSection($delta_from);
      } else {
        $section = $section_storage_to->getSection($delta_from);
      }
      $component = $section->getComponent($block_uuid);
      $section->removeComponent($block_uuid);

      // If the block is moving to a new section storage update the from section
      // storage before placing the block.
      if ($changing_section_storage) {
        $section_storage = $this->sectionStorageHandler->updateSectionStorage($section_storage, $nested_storage_path_from, $from_section_storage);
        $section_storage_to = $this->sectionStorageHandler->getCurrentSectionStorage($section_storage, $nested_storage_path_to);
      }

      // Get the destination section.
      if ($block_info['destination']['type'] === 'section') {
        $section = $this->getSection($block_info['destination'], $section_storage_to);
        $component->setRegion($section->getDefaultRegion());
        if (is_null($section->getLayoutId())) {
          throw new \InvalidArgumentException('Please configure a default layout for this section.');
        }
      } else {
        $section = $section_storage_to->getSection($delta_to);
        $component->setRegion($region_to);
      }

      $block = $component->getPlugin();
      $configuration = $block->getConfiguration();
      // Are we moving a field block to or from a nested entity?
      if ($block instanceof FieldBlock && $changing_section_storage && !empty($configuration['context_mapping']['entity'])) {
        if (empty($nested_storage_path_to)) {
          $configuration['context_mapping']['entity'] = $this->sectionStorageHandler->mapContextBackToLbEntity($section_storage_to, $configuration['context_mapping']['entity']);
        }
        else {
          $configuration['context_mapping']['entity'] = $this->sectionStorageHandler->mapContextToParentEntity($from_section_storage, $configuration['context_mapping']['entity']);
        }
        $component = new SectionComponent($component->getUuid(), $component->getRegion(), $configuration);
      }

      // Place the block in it's new location.
      if (!empty($preceding_block_uuid) && $preceding_block_uuid !== 'null') {
        $section->insertAfterComponent($preceding_block_uuid, $component);
      }
      else {
        $section->insertComponent(0, $component);
      }
      // Save the changes.
      $section_storage = $this->sectionStorageHandler->updateSectionStorage($section_storage, $nested_storage_path_to, $section_storage_to);

      // Prepare an updated Layout Builder UI response.
      $response = new AjaxResponse();
      $response->addCommand(new RemoveCommand('[data-drupal-messages]'));
      $layout = [
        '#type' => 'layout_builder_plus',
        '#section_storage' => $section_storage,
        '#nested_storage_path' => $nested_storage_path_to,
      ];
      $selector = '#layout-builder';
      if (!empty($nested_storage_path_to)) {
        // Replace the nested layout.
        $pieces = SectionStorageHandler::decodeNestedStoragePath($nested_storage_path_to);
        $storage_uuid = end($pieces);
        $selector = "[data-nested-storage-uuid='$storage_uuid']";
        if ($changing_section_storage) {
          // Remove the moved block if it was moved from the page to a nested layout.
          $response->addCommand(new RemoveCommand("[data-block-uuid='$block_uuid']"));
        }
      }
      $response->addCommand(new ReplaceCommand($selector, $layout));
      return $response;
    }
    catch (\Exception $e) {
      if (isset($transaction)) {
        $transaction->rollBack();
      }
      throw $e;
    }
  }


  /**
   * Get section.
   *
   * Find the relevant section or create a new one for block placement.
   *
   * @param array $destination
   *   The block section destination.
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return \Drupal\layout_builder\Section
   *   The section to place the block.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getSection(array $destination, SectionStorageInterface $section_storage) {
    if ($destination['type'] === 'section' && $destination['section'] === 'last') {
      $delta = $section_storage->count();
    }
    else {
      // Find the section and delta.
      for ($delta = 0; $delta < $section_storage->count(); $delta++) {
        $section = $section_storage->getSection($delta);
        if ($section->getThirdPartySetting('lb_plus', 'uuid') === $destination['section']) {
          break;
        }
      }
    }

    // Insert a new section.
    if ($destination['type'] === 'section') {
      $layout_settings = $this->getLbPlusSetting($section_storage, 'default_section');
      $layout_plugin_id = $layout_settings['layout_plugin'];
      $section = new Section($layout_plugin_id, $layout_settings);
      $section_uuid = $this->uuid->generate();
      $section->setThirdPartySetting('lb_plus', 'uuid', $section_uuid);
      $section_storage->insertSection($delta, $section);
    }

    return $section;
  }

}
