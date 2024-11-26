<?php

namespace Niteo\Tailwind {

    // Generate the JS to inject Tailwind CSS
    function generateJS($css)
    {
        return "
        import { install, injectGlobal } from 'https://esm.run/@twind/core';
        import presetAutoprefix from 'https://esm.run/@twind/preset-autoprefix';
        import presetTailwind from 'https://esm.run/@twind/preset-tailwind';
        import presetTypography from 'https://esm.run/@twind/preset-typography';
        install({
            presets: [presetAutoprefix(), presetTailwind(), presetTypography()],
            darkMode: 'class',
            hash: false,
        });
        injectGlobal(`" . $css . "`);
        ";
    }

    // Remove customizer styles, we re-add them via Tailwind
    remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles_custom_css');

    add_action('after_setup_theme', function () {

        // Adding support for featured images.
        add_theme_support('post-thumbnails');

        // Adding support for core block visual styles.
        add_theme_support('wp-block-styles');

        // Adding support for responsive embedded content.
        add_theme_support('responsive-embeds');

        // Adding support for full navigation menu functionality.
        add_theme_support('block-nav-menus');
    });

    // Inject tailwind as a module as soon as possible
    add_action('wp_head', function () {
        echo '<script type="module">' . generateJS(wp_get_custom_css()) . '</script>';
    });

    // Inject inline CSS to hide the body until Tailwind is loaded
    add_filter('body_class', function ($classes) {
        $classes[] = '!block';
        return $classes;
    });
    add_action('wp_enqueue_scripts', function () {
        $css = <<<EOD
            body {
                display: none;
            }
        EOD;
        wp_register_style('fouc-tailwind', false);
        wp_enqueue_style('fouc-tailwind');
        wp_add_inline_style('fouc-tailwind', $css);
    });
    // END Inject inline CSS to hide the body until Tailwind is loaded

    // Inject inline Tailwind for Site Editor
    add_action('enqueue_block_assets', function () {

        wp_enqueue_script(
            'tailwind',
            wp_upload_dir()['baseurl'] . '/tailwind.js',
            array('wp-blocks', 'wp-dom-ready', 'wp-edit-post'),
            filemtime(wp_upload_dir()['basedir'] . '/tailwind.js')
        );
        // Add inline script
        wp_add_inline_script(
            'tailwind',
            "wp.domReady(() => {
    // Wait for the editor iframe to load
    const observer = new MutationObserver(() => {
        const iframe = document.querySelector('iframe[name=\"editor-canvas\"]');

        if (iframe && iframe.contentDocument) {
            const iframeDoc = iframe.contentDocument;
            const canvas = iframeDoc.querySelector('body');
            if (
                canvas &&
                canvas.classList.length > 1 && // Has more than one class
                !canvas.classList.contains('prose') && // Does not have 'prose'
                canvas.classList.contains('wp-embed-responsive') // Contains 'wp-embed-responsive'
            ) {
                // Add a custom class to the canvas
                canvas.classList.add('prose');

                // Stop observing since the element has been found
                observer.disconnect();
            }
        }
    });

    // Start observing the DOM for iframe changes
    observer.observe(document.body, { childList: true, subtree: true });
});");

    });
    add_filter('wp_script_attributes', function ($attributes) {
        if (isset($attributes['id']) && $attributes['id'] === 'tailwind-js') {
            $attributes['type'] = 'module';
        }
        return $attributes;
    }, 10, 1);
    // END Inject inline Tailwind for Site Editor

    add_filter('update_custom_css_data', function ($data, $args) {
        $jsContent = generateJS($data["css"]);
        $filePath = wp_upload_dir()['basedir'] . '/tailwind.js';
        file_put_contents($filePath, $jsContent);
        return $data;
    }, 10, 2);

};
