<?php

namespace Drupal\lb_plus_section_library\Form;

use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\layout_builder\Form\OverridesEntityForm as LayoutBuilderOverridesEntityForm;

class OverridesEntityForm implements ContainerInjectionInterface {

  use StringTranslationTrait;

  protected ModuleHandlerInterface $moduleHandler;
  protected ClassResolverInterface $classResolver;

  public function __construct(ModuleHandlerInterface $module_handler, ClassResolverInterface $class_resolver) {
    $this->moduleHandler = $module_handler;
    $this->classResolver = $class_resolver;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('class_resolver'),
    );
  }

  /**
   * Adds a Section Library menu to the LB+ toolbar.
   */
  public function formAlter(&$form, FormStateInterface $form_state, $form_id) {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof LayoutBuilderOverridesEntityForm) {
      return;
    }
    $path = $this->moduleHandler->getModule('lb_plus_section_library')->getPath();
    $section_storage = $form_object->getSectionStorage();

    $form['actions']['section_library'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['lb-plus-toolbar-menu']],
      '#weight' => 970,
      'menu' => [
        '#theme' => 'item_list',
        '#title' => [
          '#type' => 'container',
          '#attributes' => [
            'title' => $this->t('Section Library'),
            'class' => ['lb-plus-icon', 'section-library'],
            'style' => [
              "background-image: url(/$path/assets/library.svg);",
            ],
          ],
        ],
        '#items' => [
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
              'section_storage_type' => $section_storage->getStorageType(),
              'section_storage' => $section_storage->getStorageId(),
              'delta' => 0,
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
              'section_storage_type' => $section_storage->getStorageType(),
              'section_storage' => $section_storage->getStorageId(),
              'delta' => 0,
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
  }

}
