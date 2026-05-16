<?php
/**
 * Plugin Name: NHR AI Developer Assistant
 * Plugin URI: http://wordpress.org/plugins/nhrrob-ai-developer-assistant/
 * Description: Gives site owners a personal AI developer inside their WordPress admin. Describe a change in plain English and the assistant implements it — CSS, JS, PHP snippets, or site options — with full undo support.
 * Author: Nazmul Hasan Robin
 * Author URI: https://profiles.wordpress.org/nhrrob/
 * Version: 1.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: nhrrob-ai-developer-assistant
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH'))
    exit;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * The main plugin class
 */
final class Nhrada_AI_Developer_Assistant
{

    /**
     * Plugin version
     *
     * @var string
     */
    const version = '1.1.0';

    /**
     * Class constructor
     */
    private function __construct()
    {
        $this->define_constants();

        add_action('plugins_loaded', [$this, 'init_plugin']);

        register_activation_hook(NHRADA_FILE, [$this, 'activate']);
        register_deactivation_hook(NHRADA_FILE, [$this, 'deactivate']);
    }

    /**
     * Initialize a singleton instance
     *
     * @return \Nhrada_AI_Developer_Assistant
     */
    public static function init()
    {
        static $instance = false;

        if (!$instance) {
            $instance = new self();
        }

        return $instance;
    }

    /**
     * Define the required plugin constants
     *
     * @return void
     */
    public function define_constants()
    {
        define('NHRADA_VERSION', self::version);
        define('NHRADA_FILE', __FILE__);
        define('NHRADA_PATH', __DIR__);
        define('NHRADA_PLUGIN_DIR', plugin_dir_path(NHRADA_FILE));
        define('NHRADA_URL', plugins_url('', NHRADA_FILE));
        define('NHRADA_ASSETS', NHRADA_URL . '/assets');
        $upload = wp_upload_dir();
        define('NHRADA_SNIPPETS_DIR', $upload['basedir'] . '/nhrada-ai-developer-assistant');
        define('NHRADA_SNIPPETS_FILE', NHRADA_SNIPPETS_DIR . '/snippets-cache.php');
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function init_plugin()
    {
        new \Nhrada\AIDeveloperAssistant\Assets();

        if (is_admin()) {
            $admin = new \Nhrada\AIDeveloperAssistant\Admin\Admin();
            $admin->init();
        }

        $api = new \Nhrada\AIDeveloperAssistant\Api\Api();
        $api->init();

        $this->load_php_snippets();
    }

/**
     * Activate the plugin
     *
     * @return void
     */
    public function activate()
    {
        \Nhrada\AIDeveloperAssistant\Activator::activate();
    }

    /**
     * Deactivate the plugin
     *
     * @return void
     */
    public function deactivate()
    {
        // No scheduled hooks to clear
    }

    /**
     * Load PHP snippets file if it exists
     *
     * @return void
     */
    public function load_php_snippets()
    {
        if (file_exists(NHRADA_SNIPPETS_FILE)) {
            require_once NHRADA_SNIPPETS_FILE;
        }
    }
}

/**
 * Initializes the main plugin
 *
 * @return \Nhrada_AI_Developer_Assistant
 */
function nhrada_ai_developer_assistant()
{
    return Nhrada_AI_Developer_Assistant::init();
}

// Call the plugin
nhrada_ai_developer_assistant();
