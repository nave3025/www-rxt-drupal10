<?php

namespace Drupal\lb_plus\Event;

use Drupal\Component\EventDispatcher\Event;

class AdminButtonsEvent extends Event {

  private array $section;
  private array $buttons;
  private string $storageId;
  private string $storageType;
  private ?string $nestedStoragePath;

  private int $sectionDelta;

  public function __construct(array $section_render, string $storage_type, string $storage_id, int $section_delta, string $nested_storage_path = NULL) {
    $this->storageType = $storage_type;
    $this->storageId = $storage_id;
    $this->section = $section_render;
    $this->nestedStoragePath = $nested_storage_path;
    $this->sectionDelta = $section_delta;
  }

  /**
   * Get section.
   *
   * @return array
   *   Get the current section.
   */
  public function getSection(): array {
    return $this->section;
  }

  /**
   * Get buttons.
   *
   * @return array
   *   A render array of buttons.
   */
  public function getButtons(): array {
    return $this->buttons;
  }

  /**
   * Set buttons.
   *
   * @param array $buttons
   *   A render array of buttons.
   */
  public function setButtons(array $buttons): void {
    $this->buttons = $buttons;
  }

  /**
   * Get storage type.
   *
   * @return string
   *   The storage type.
   */
  public function getStorageType(): string {
    return $this->storageType;
  }

  /**
   * Get storage ID.
   *
   * @return string
   *   The storage ID.
   */
  public function getStorageId(): string {
    return $this->storageId;
  }

  public function isLayoutBlock(): bool {
    return !empty($this->nestedStoragePath);
  }

  public function getNestedStoragePath(): ?string {
    return $this->nestedStoragePath;
  }

  public function setNestedStoragePath(?string $nestedStoragePath): void {
    $this->nestedStoragePath = $nestedStoragePath;
  }

  public function getSectionDelta(): int {
    return $this->sectionDelta;
  }

  public function setSectionDelta(int $sectionDelta): void {
    $this->sectionDelta = $sectionDelta;
  }

}
