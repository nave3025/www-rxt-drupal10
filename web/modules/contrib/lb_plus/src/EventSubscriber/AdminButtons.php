<?php

namespace Drupal\lb_plus\EventSubscriber;

use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;
use Drupal\lb_plus\Event\AdminButtonsEvent;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Layout Builder + event subscriber.
 */
class AdminButtons implements EventSubscriberInterface {

  use StringTranslationTrait;

  protected ModuleHandlerInterface $moduleHandler;
  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $moduleHandler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $moduleHandler;
  }

  public function onAdminButtons(AdminButtonsEvent $event) {
    $path = $this->moduleHandler->getModule('lb_plus')->getPath();
    $event->setButtons([
      'sort' => [
        '#type' => 'container',
        '#attributes' => [
          'title' => $this->t('Sort sections'),
          'class' => ['lb-plus-icon', 'sort'],
          'style' => [
            'background-image: url("/' . $path . '/assets/updown.svg");',
            'cursor: move;',
          ],
        ],
      ],
      'configure' => [
        '#type' => 'link',
        '#url' => Url::fromRoute('lb_plus.admin_button.configure_section', [
          'section_storage_type' => $event->getStorageType(),
          'section_storage' => $event->getStorageId(),
          'delta' => $event->getSectionDelta(),
          'nested_storage_path' => $event->getnestedStoragePath(),
        ], [
          'attributes' => [
            'class' => ['use-ajax', 'configure-link'],
            'data-dialog-type' => 'dialog',
            'data-dialog-options' => Json::encode([
              'width' => 1024,
              'height' => 'auto',
              'target' => 'layout-builder-modal',
              'autoResize' => TRUE,
              'modal' => TRUE,
            ]),
          ],
        ]),
        '#title' => [
          '#type' => 'container',
          '#attributes' => [
            'title' => $this->t('Configure'),
            'class' => ['lb-plus-icon', 'configure'],
            'style' => [
              'background-image: url("/' . $path . '/assets/cog.svg");',
            ],
          ],
        ],
      ],
      'layout' => [
        '#type' => 'link',
        '#url' => Url::fromRoute('lb_plus.admin_button.choose_layout', [
          'section_storage_type' => $event->getStorageType(),
          'section_storage' => $event->getStorageId(),
          'section_delta' => $event->getSectionDelta(),
          'nested_storage_path' => $event->getnestedStoragePath(),
        ], [
          'attributes' => [
            'class' => ['use-ajax'],
            'data-dialog-type' => 'dialog',
          ],
        ]),
        '#title' => [
          '#type' => 'container',
          '#attributes' => [
            'title' => $this->t('Layout'),
            'class' => ['lb-plus-icon', 'change-layout'],
            'style' => [
              'background-image: url("/' . $path . '/assets/3column.svg");',
            ],
          ],
        ],
      ],
      'remove' => [
        '#type' => 'link',
        '#url' => Url::fromRoute('lb_plus.admin_button.remove_section', [
          'section_storage_type' => $event->getStorageType(),
          'section_storage' => $event->getStorageId(),
          'delta' => $event->getSectionDelta(),
          'nested_storage_path' => $event->getnestedStoragePath(),
        ], [
          'attributes' => [
            'class' => ['use-ajax'],
            'data-dialog-type' => 'dialog',
            'data-dialog-options' => Json::encode(['width' => 300]),
          ],
        ]),
        '#title' => [
          '#type' => 'container',
          '#attributes' => [
            'title' => $this->t('Remove'),
            'class' => ['lb-plus-icon', 'remove'],
            'style' => [
              'background-image: url("/' . $path . '/assets/remove.svg");',
            ],
          ],
        ],
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      AdminButtonsEvent::class => ['onAdminButtons'],
    ];
  }

}
