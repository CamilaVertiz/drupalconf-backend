<?php

/**
 * Load services definition file.
 */
$settings['container_yamls'][] = __DIR__ . '/services.yml';

/**
 * Skipping permissions hardening will make scaffolding
 * work better, but will also raise a warning when you
 * install Drupal.
 *
 * https://www.drupal.org/project/drupal/issues/3091285
 */
// $settings['skip_permissions_hardening'] = TRUE;

/**
 * Configuration sync directory.
 */
$settings['config_sync_directory'] = '../config';

/**
 * If there is a local settings file, then include it
 */
if (getenv('IS_DDEV_PROJECT') === 'true') {
  // Local Site Settings configuration.
  $local_settings = __DIR__ . "/settings.local.php";
  if (file_exists($local_settings)) {
    include $local_settings;
  }

  // Automatically generated include for settings managed by ddev.
  $ddev_settings = __DIR__ . '/settings.ddev.php';
  if (is_readable($ddev_settings)) {
    require $ddev_settings;
  }

  // Local services configuration.
  $local_services = __DIR__ . '/../development.services.yml';
  if (is_readable($local_services)) {
    $settings['container_yamls'][] = $local_services;
  }

  // Deactivate local aggregation.
  $config['system.performance']['css']['preprocess'] = FALSE;
  $config['system.performance']['js']['preprocess'] = FALSE;

  // Deactive local caching.
  $settings['cache']['bins']['page'] = 'cache.backend.null';
  $settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
  $settings['cache']['bins']['render'] = 'cache.backend.null';

  $config['system.performance']['cache']['page']['max_age'] = 0;
}
$databases['default']['default'] = array (
  'database' => 'db',
  'username' => 'db',
  'password' => 'db',
  'prefix' => '',
  'host' => 'db',
  'port' => 3306,
  'isolation_level' => 'READ COMMITTED',
  'driver' => 'mysql',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
);
$settings['hash_salt'] = 'FjXQL9IJx-_iaT9BFkeAMbciatzm3IwOoJQ7AMb5HMlkZPCUg_guoyeP074aFSwagQRxayfEUA';
