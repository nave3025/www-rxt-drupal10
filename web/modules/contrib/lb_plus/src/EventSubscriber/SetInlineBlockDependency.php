<?php

namespace Drupal\lb_plus\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\lb_plus\SectionStorageHandler;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\layout_builder\InlineBlockUsageInterface;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\layout_builder\EventSubscriber\SetInlineBlockDependency as SetInlineBlockDependencyAliasBase;

/**
 * Layout Builder + event subscriber.
 */
class SetInlineBlockDependency extends SetInlineBlockDependencyAliasBase {

  protected SectionStorageHandler $sectionStorageHandler;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database, InlineBlockUsageInterface $usage, SectionStorageManagerInterface $section_storage_manager, SectionStorageHandler $section_storage_handler) {
    parent::__construct($entity_type_manager, $database, $usage, $section_storage_manager);
    $this->sectionStorageHandler = $section_storage_handler;
  }

  /**
   * Overrides LayoutEntityHelperTrait->getEntitySections.
   *
   * Makes the dependency setting aware of sections in nested layouts.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The layout builder enabled entity.
   *
   * @return array|\Drupal\layout_builder\Section[]
   *   All sections within this entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getEntitySections(EntityInterface $entity) {
    $section_storage = $this->getSectionStorageForEntity($entity);
    return $section_storage ? $this->sectionStorageHandler->getAllSections($section_storage) : [];
  }

}
