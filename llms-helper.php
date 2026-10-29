<?php
/**
 * Plugin Name:       LLMs Helper
 * Plugin URI:        https://github.com/Pink-Crab/llms-helper
 * Description:       Writes static Markdown copies of your content and an llms.txt to uploads, served at the llmstxt.org URLs (/page/index.html.md and /llms.txt).
 * Version:           0.1.0-rc.1
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            PinkCrab
 * Author URI:        https://pinkcrab.co.uk
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       llms-helper
 *
 * @package PinkCrab\LLMs_Helper
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PINKCRAB_LLMS_HELPER_VERSION', '0.1.0-rc.1' );
define( 'PINKCRAB_LLMS_HELPER_FILE', __FILE__ );

require_once __DIR__ . '/vendor/autoload.php';

Plugin::boot( __FILE__ );
