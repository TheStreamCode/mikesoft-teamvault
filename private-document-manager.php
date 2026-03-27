<?php
/**
 * Plugin Name: Private Document Manager
 * Plugin URI: https://github.com/mikesoft-codex/wp-private-document-manager
 * Description: Private document management separated from the WordPress Media Library with secure access control, preview and drag-and-drop.
 * Version: 1.1.22
 * Author: Michael Gasperini
 * Author URI: https://mikesoft.it
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: private-document-manager
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

defined('ABSPATH') || exit;

define('PDM_VERSION', '1.1.22');
define('PDM_PLUGIN_FILE', __FILE__);
define('PDM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PDM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PDM_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once PDM_PLUGIN_DIR . 'includes/class-pdm-bootstrap.php';

PDM_Bootstrap::instance()->init();
