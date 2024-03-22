<?php

namespace Drupal\lb_plus\Form;

use Drupal\Component\Uuid\Php;
use Drupal\layout_builder\Section;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\SectionComponent;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;

/**
 * Configure Layout Builder + settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  protected Php $uuid;
  protected BlockManagerInterface $blockManager;
  protected EntityTypeManagerInterface $entityTypeManager;
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;
  protected SectionStorageManagerInterface $sectionStorageManager;
  protected EntityDisplayRepositoryInterface $entityDisplayRepository;

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('uuid'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.block'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_display.repository'),
      $container->get('plugin.manager.layout_builder.section_storage'),
    );
  }

  public function __construct(Php $uuid, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entityTypeManager, BlockManagerInterface $blockManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, EntityDisplayRepositoryInterface $entityDisplayRepository, SectionStorageManagerInterface $sectionStorageManager) {
    parent::__construct($config_factory);
    $this->entityDisplayRepository = $entityDisplayRepository;
    $this->sectionStorageManager = $sectionStorageManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->entityTypeManager = $entityTypeManager;
    $this->blockManager = $blockManager;
    $this->uuid = $uuid;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lb_plus_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['lb_plus.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $colors = $this->config('lb_plus.settings')->get('colors');

    $section_storage = $this->sectionStorageManager->loadEmpty('overrides');

    // Find an entity type with layout builder enabled.
    $entity_types = $this->entityTypeManager->getDefinitions();
    $found_entity_type = FALSE;
    foreach ($entity_types as $entity_type) {
      if ($entity_type instanceof ContentEntityTypeInterface) {
        $entity_type_id = $entity_type->id();
        $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
        foreach ($bundles as $bundle => $_) {
          $view_modes = [
            'default' => 'default',
            ...$this->entityDisplayRepository->getViewModes($entity_type_id),
          ];
          foreach ($view_modes as $view_mode => $_) {
            $entity_view_display = $this->entityTypeManager->getStorage('entity_view_display')->load("$entity_type_id.$bundle.$view_mode");
            if ($entity_view_display) {
              $layout_builder_setting = $entity_view_display->getThirdpartySetting('layout_builder', 'enabled');
              if ($layout_builder_setting) {
                $found_entity_type = TRUE;
                break 3;
              }
            }
          }
        }
      }
    }
    if (!$found_entity_type) {
      return ['#markup' => $this->t('No entities have layout builder enabled.')];
    }

    // Set the entity context.
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->create(['type' => $bundle]);
    $section_storage->setContext('entity', EntityContext::fromEntity($entity));

    $one_column_section = new Section('layout_onecol');
    $section_storage->insertSection(0, $one_column_section);
    $this->addBlock($section_storage, $one_column_section, 'content');
    $two_column_section = new Section('layout_twocol_section');
    $section_storage->insertSection(1, $two_column_section);
    $this->addBlock($section_storage, $two_column_section, 'first');
    $this->addBlock($section_storage, $two_column_section, 'second');

    $form['content'] = [
      '#type' => 'layout_builder_plus',
      '#section_storage' => $section_storage,
      '#lb_plus_settings_page' => TRUE,
    ];
    $form['settings'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['lb-plus-settings-sidebar']
      ],
      '#attached' => ['library' => [
        'lb_plus/settings',
        'lb_plus/layout_builder',
      ]],
    ];
    $form['settings']['colors']['#tree'] = TRUE;
    $form['settings']['colors']['main'] = [
      '#type' => 'color',
      '#title' => $this->t('Main color'),
      '#default_value' => $colors['main'] ?? '#4b9ae4',
      '#description' => $this->t('This is used for the section highlighting.'),
      '#attributes' => [
        'css-rule' => '--lb-plus-main-color',
      ],
    ];
    $form['settings']['colors']['secondary'] = [
      '#type' => 'color',
      '#title' => $this->t('Secondary color'),
      '#default_value' => $colors['secondary'] ?? '#4b9ae4',
      '#attributes' => [
        'css-rule' => '--lb-plus-secondary-color',
      ],
    ];
    $form['settings']['actions']['#type'] = 'actions';
    $form['settings']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save colors'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  protected function addBlock(SectionStorageInterface $section_storage, Section $section, string $region) {
    $component = new SectionComponent($this->uuid->generate(), $region, ['id' => 'color_block']);
    $block_plugin = $this->blockManager->createInstance('color_block');
    $component->setConfiguration($block_plugin->getConfiguration());
    $section->insertComponent(0, $component);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('lb_plus.settings');
    $form_state->cleanValues();
    foreach ($form_state->getValues() as $key => $value) {
      $config->set($key, $value);
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
