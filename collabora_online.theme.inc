<?php

/**
 * @file
 * Preprocess functions for this module.
 */

use Drupal\Core\Extension\ExtensionPathResolver;

/**
 * Prepares variables for the 'collabora-online-full' template.
 *
 * @param array $variables
 *   Theme hook variables.
 */
function template_preprocess_collabora_online_full(array &$variables): void {
  $variables['content'] = $variables['element']['content'];
  /** @var \Drupal\Core\Extension\ExtensionPathResolver $path_resolver */
  $path_resolver = \Drupal::service(ExtensionPathResolver::class);
  $variables['module_path'] = $path_resolver->getPath('module', 'collabora_online');
}
