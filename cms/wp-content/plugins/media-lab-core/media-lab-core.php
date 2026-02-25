<?php
/**
 * Plugin Name: Media Lab Core
 * Description: Core Funktionalität für Media Lab Websites
 * Version: 1.0.0
 * Author: Media Lab
 */

if (!defined('ABSPATH')) exit;

// Plugin Konstanten
define('MEDIA_LAB_CORE_VERSION', '1.0.0');
define('MEDIA_LAB_CORE_PATH', plugin_dir_path(__FILE__));
define('MEDIA_LAB_CORE_URL', plugin_dir_url(__FILE__));

// Post Order laden
require_once MEDIA_LAB_CORE_PATH . 'inc/class-post-order.php';
