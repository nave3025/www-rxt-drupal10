<?php

namespace Drupal\lb_plus_section_library\EventSubscriber;

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

  protected EntityTypeManagerInterface $entityTypeManager;
  protected ModuleHandlerInterface $moduleHandler;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $moduleHandler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * On admin buttons.
   *
   * @param \Drupal\lb_plus\Event\AdminButtonsEvent $event
   *   The admin buttons event.
   *
   * @return void
   */
  public function onAdminButtons(AdminButtonsEvent $event) {
    $path = $this->moduleHandler->getModule('lb_plus_section_library')->getPath();
    $section_library = [
      'section_library' => [
        '#type' => 'container',
        '#attributes' => [
          'title' => $this->t('Section Library'),
          'class' => ['lb-plus-icon', 'section-library'],
          'style' => [
            "background-image: url(/$path/assets/library.svg);",
          ],
        ],
        '#attached' => ['library' => ['lb_plus_section_library/menu']],
        'menu' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['lb-plus-menu', 'section-library-menu'],
          ],
          'section_save' => [
            '#type' => 'link',
            '#attributes' => [
              'class' => ['section-library-link'],
            ],
            '#title' => [
              'icon' => [
                '#type' => 'container',
                '#attributes' => [
                  'class' => ['lb-plus-icon', 'add'],
                  'style' => [
                    "background-image: url(/$path/assets/save.svg);",
                  ],
                ],
              ],
              'title' => ['#markup' => $this->t('Add this section to library')],
            ],
            '#url' => Url::fromRoute('section_library.add_section_to_library', [
              'section_storage_type' => $event->getStorageType(),
              'section_storage' => $event->getStorageId(),
              'delta' => $event->getSectionDelta(),
            ], [
              'attributes' => [
                'class' => ['use-ajax'],
                'data-dialog-type' => 'dialog',
                'data-dialog-renderer' => 'off_canvas',
                'data-dialog-options' => Json::encode(['width' => 300]),
              ],
            ]),
          ],
          'template_save' => [
            '#type' => 'link',
            '#title' => [
              'icon' => [
                '#type' => 'container',
                '#attributes' => [
                  'class' => ['lb-plus-icon', 'add'],
                  'style' => [
                    "background-image: url(/$path/assets/save.svg);",
                  ],
                ],
              ],
              'title' => ['#markup' => $this->t('Add this page to library')],
            ],
            '#url' => Url::fromRoute('section_library.add_template_to_library', [
              'section_storage_type' => $event->getStorageType(),
              'section_storage' => $event->getStorageId(),
              'delta' => $event->getSectionDelta(),
            ], [
              'attributes' => [
                'class' => ['use-ajax'],
                'data-dialog-type' => 'dialog',
                'data-dialog-renderer' => 'off_canvas',
                'data-dialog-options' => Json::encode(['width' => 300]),
              ],
            ]),
          ],
          'section_library_choose' => [
            '#type' => 'link',
            '#title' => [
              'icon' => [
                '#type' => 'container',
                '#attributes' => [
                  'class' => ['lb-plus-icon', 'add'],
                  'style' => [
                    "background-image: url(/$path/assets/import.svg);",
                  ],
                ],
              ],
              'title' => ['#markup' => $this->t('Import from library')],
            ],
            '#url' => Url::fromRoute('section_library.choose_template_from_library', [
              'section_storage_type' => $event->getStorageType(),
              'section_storage' => $event->getStorageId(),
              'delta' => $event->getSectionDelta(),
            ], [
              'attributes' => [
                'class' => ['use-ajax'],
                'data-dialog-type' => 'dialog',
                'data-dialog-renderer' => 'off_canvas',
                'data-dialog-options' => Json::encode(['width' => 300]),
              ],
            ]),
          ],
        ],
      ],
    ];

    $buttons = $event->getButtons();
    $before = array_slice($buttons, 0, array_search('remove', array_keys($buttons)));
    $after = array_slice($buttons, array_search('remove', array_keys($buttons)));
    $buttons = array_merge($before, $section_library, $after);
    $event->setButtons($buttons);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      AdminButtonsEvent::class => ['onAdminButtons', -1],
    ];
  }

}
