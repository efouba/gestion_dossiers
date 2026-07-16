<?php
// Chemins absolus
define('BASE_URL', 'https://foldermag.ct.ws');
define('AUTH_PATH', BASE_URL . '/auth');
define('TEMPLATES_PATH', BASE_URL . '/templates');
define('INCLUDES_PATH', BASE_URL . '/includes');
define('FILES_PATH', BASE_URL . '/files');

// Chemins relatifs (pour les inclusions PHP)
define('ROOT_DIR', __DIR__ . '/..');
define('AUTH_DIR', ROOT_DIR . '/auth');
define('TEMPLATES_DIR', ROOT_DIR . '/templates');
define('INCLUDES_DIR', ROOT_DIR . '/includes');
define('FILES_DIR', ROOT_DIR . '/files');