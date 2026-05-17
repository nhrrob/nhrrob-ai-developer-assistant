<?php
namespace Nhrada\AIDeveloperAssistant;

if (!defined('ABSPATH'))
    exit;

class Assets
{

    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'register_assets']);
        add_action('wp_footer', [$this, 'output_custom_js'], 99);
    }

    public function get_scripts()
    {
        $asset = $this->get_build_asset();

        return [
            'nhrada-app' => [
                'src'     => NHRADA_URL . '/admin/build/index.js',
                'deps'    => $asset['dependencies'],
                'version' => $asset['version'],
            ],
        ];
    }

    public function get_styles()
    {
        $asset = $this->get_build_asset();

        return [
            'nhrada-app-css' => [
                'src'     => NHRADA_URL . '/admin/build/style-index.css',
                'version' => $asset['version'],
            ],
        ];
    }

    public function register_assets()
    {
        foreach ($this->get_scripts() as $handle => $script) {
            $deps = isset($script['deps']) ? $script['deps'] : [];
            wp_register_script($handle, $script['src'], $deps, $script['version'], true);
        }

        foreach ($this->get_styles() as $handle => $style) {
            $deps = isset($style['deps']) ? $style['deps'] : [];
            wp_register_style($handle, $style['src'], $deps, $style['version']);
        }
    }

    public function output_custom_js()
    {
        $js = get_option('nhrada_custom_js', '');
        if (!empty($js)) {
            echo "<script type='text/javascript'>\n" . $js . "\n</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
        }
    }

    private function get_build_asset()
    {
        $asset_file = NHRADA_PLUGIN_DIR . 'admin/build/index.asset.php';
        return file_exists($asset_file)
            ? require $asset_file
            : ['dependencies' => [], 'version' => NHRADA_VERSION];
    }
}
