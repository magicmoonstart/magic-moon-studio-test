<?php
/*
Plugin Name: Magic Moon Tools
Plugin URI: https://magic-moon.de
Description: Deployment and maintenance tools for Magic Moon Studio.
Version: 1.0.0
Author: Magic Moon Studio
Author URI: https://magic-moon.de
License: GPL2
*/

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_menu_page('Magic Moon Tools', 'MM Tools', 'manage_options', 'mm-tools', 'mm_tools_page', 'dashicons-hammer', 80);
});

function mm_tools_page() {
    $message = '';
    if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'fix_cta') {
        global $wpdb;
        $a = $wpdb->query("UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, 'Book Consultation', 'Beratung buchen') WHERE meta_key = '_elementor_data' AND meta_value LIKE '%Book Consultation%'");
        $b = $wpdb->query("UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, 'Book Consultation', 'Beratung buchen') WHERE post_content LIKE '%Book Consultation%'");
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_elementor_css', '_elementor_element_cache')");
        $message = "Done! Updated $a Elementor pages + $b post content rows. Cache cleared.";
    }
    ?>
    <div class="wrap">
        <h1>Magic Moon Tools</h1>
        <?php if ($message): ?>
            <div class="notice notice-success"><p><?= esc_html($message) ?></p></div>
        <?php endif; ?>
        <h2>CTA Text Fix</h2>
        <p>Changes all "Book Consultation" buttons to <strong>"Beratung buchen"</strong> sitewide.</p>
        <form method="post">
            <input type="hidden" name="mm_action" value="fix_cta">
            <?php submit_button('Run CTA Fix Now', 'primary'); ?>
        </form>
    </div>
    <?php
}
