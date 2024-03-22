<?php

namespace Drupal\lb_plus\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lb_plus\Element\LayoutBuilderPlusUI;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
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

  public function formAlter(&$form, FormStateInterface $form_state, $form_id) {
    $form_object = $form_state->getFormObject();
    if ($form_object instanceof LayoutBuilderOverridesEntityForm || in_array($form_id, [
        'lb_plus_edit_block_layout',
        'entity_view_display_layout_builder_form',
      ])) {
      // Clean up the LB UI by hiding a few things.
      if (!empty($form['revision_information']['#access'])) {
        $form['revision_information']['#access'] = FALSE;
      }
      if (!empty($form['layout_builder_message'])) {
        unset($form['layout_builder_message']);
      }
      // Style the form actions as a toolbar.
      $form['actions']['#attributes']['class'][] = 'lb-plus-toolbar';
      $form['actions']['#attributes']['data-offset-bottom'] = '';
      $form['actions']['nested-layout'] = [
        '#type' => 'container',
        '#weight' => 980,
        '#attributes' => [
          'id' => 'lb-plus-nested-layout-breadcrumb',
          'style' => [
            'display: none;',
          ],
        ],
        'exit_nested_layout' => [
          '#type' => 'link',
          '#title' => $this->t('Exit nested layout'),
          '#url' => Url::createFromRequest(\Drupal::request()),
          '#attributes' => [
            'class' => ['button'],
          ],
        ],
      ];
      $form['actions']['toggle_sidebar_button'] = [
        '#type' => 'container',
        '#weight' => 999,
        '#attributes' => [
          'title' => t('Toggle sidebar'),
          'id' => 'lb-plus-toggle-sidebar',
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
      ];
    }
  }

}
