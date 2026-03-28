<?php
/**
 * Plugin Name: Mikesoft TeamVault
 * Plugin URI: https://github.com/mikesoft-codex/mikesoft-teamvault
 * Description: Private shared document management separated from the WordPress Media Library with secure access control, preview and drag-and-drop.
 * Version: 1.1.25
 * Author: Michael Gasperini
 * Author URI: https://mikesoft.it
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mikesoft-teamvault
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

defined('ABSPATH') || exit;

define('PDM_VERSION', '1.1.25');
define('PDM_PLUGIN_FILE', __FILE__);
define('PDM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PDM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PDM_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once PDM_PLUGIN_DIR . 'includes/class-pdm-bootstrap.php';

PDM_Bootstrap::instance()->init();
