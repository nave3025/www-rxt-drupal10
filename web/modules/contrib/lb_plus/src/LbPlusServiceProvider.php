<?php

namespace Drupal\lb_plus;

use Symfony\Component\DependencyInjection\Reference;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\lb_plus\EventSubscriber\SetInlineBlockDependency;
use Drupal\lb_plus\ContextProvider\NodeRouteContextOverride;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;

class LbPlusServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Add nested storage awareness to SetInlineBlockDependency.
    $definition = $container->getDefinition('layout_builder.get_block_dependency_subscriber');
    $arguments = $definition->getArguments();
    $arguments[] = new Reference('lb_plus.section_storage_handler');
    $definition->setArguments($arguments);
    $definition->setClass(SetInlineBlockDependency::class);
    $container->setDefinition('layout_builder.get_block_dependency_subscriber', $definition);

    // Add nested storage awareness to NodeRouteContext.
    if ($container->hasDefinition('node.node_route_context')) {
      $definition = $container->getDefinition('node.node_route_context');
      $definition->setClass(NodeRouteContextOverride::class);
      $container->setDefinition('node.node_route_context', $definition);
    }
  }

}
