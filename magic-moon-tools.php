<?php
/*
Plugin Name: Magic Moon Tools
Plugin URI: https://magic-moon.de
Description: Deployment and maintenance tools for Magic Moon Studio.
Version: 1.3.0
Author: Magic Moon Studio
Author URI: https://magic-moon.de
License: GPL2
*/

if (!defined('ABSPATH')) exit;

/**
 * Restore All-in-One WP Migration from the clean bundled zip.
 * Overwrites the broken copy in wp-content/plugins.
 */
function mm_repair_ai1wm() {
    $zip = __DIR__ . '/assets/ai1wm-clean.mmzip';
    if (!file_exists($zip)) {
        return 'ERROR: assets/ai1wm-clean.mmzip not found â€” deploy the latest version from git first.';
    }
    // unzip_file requires a .zip extension â€” copy to a temp .zip first
    $tmp = get_temp_dir() . 'ai1wm-clean-' . time() . '.zip';
    if (!copy($zip, $tmp)) {
        return 'ERROR: could not create temp copy of the archive.';
    }
    $zip = $tmp;
    require_once ABSPATH . 'wp-admin/includes/file.php';
    WP_Filesystem();
    $result = unzip_file($zip, WP_PLUGIN_DIR);
    @unlink($tmp);
    if (is_wp_error($result)) {
        return 'ERROR: ' . $result->get_error_message();
    }
    return 'SUCCESS: All-in-One WP Migration restored from clean copy. Go to Plugins and activate it.';
}

// Auto-repair once per plugin version after deployment
add_action('admin_init', function () {
    if (get_option('mm_ai1wm_repair_done') !== '1.1.0') {
        $msg = mm_repair_ai1wm();
        update_option('mm_ai1wm_repair_done', '1.1.0');
        update_option('mm_ai1wm_repair_result', $msg);
    }
});

/**
 * Replace English CTA texts with German across all Elementor data and post content.
 */
function mm_fix_cta_german() {
    global $wpdb;
    // Ordered longest-first so longer phrases are replaced before their substrings
    $pairs = array(
        'Book a Concept Tattoo Consultation' => 'Concept-Tattoo-Beratung buchen',
        'Book an Intensive Consultation'     => 'Intensivberatung buchen',
        'Book Your Consultation Slot'        => 'Beratungstermin buchen',
        'Book Intensive Consultation'        => 'Intensivberatung buchen',
        'REQUEST FOR CONSULTATION'           => 'BERATUNG ANFRAGEN',
        'Request For Consultation'           => 'Beratung anfragen',
        'Request for Consultation'           => 'Beratung anfragen',
        'Request a Consultation'             => 'Beratung anfragen',
        'Book Your Consultation'             => 'Beratung buchen',
        'Request Consultation'               => 'Beratung anfragen',
        'Book a Consultation'                => 'Beratung buchen',
        'BOOK CONSULTATION'                  => 'BERATUNG BUCHEN',
        'Book Consultation'                  => 'Beratung buchen',
        'Book Your Slot'                     => 'Termin buchen',
        'Get in Touch'                       => 'Kontakt aufnehmen',
        'Get In Touch'                       => 'Kontakt aufnehmen',
        'Learn More'                         => 'Mehr erfahren',
        'Read More'                          => 'Weiterlesen',
        'Contact Us'                         => 'Kontaktiere uns',
        'Get Started'                        => 'Jetzt starten',
        'View All'                           => 'Alle ansehen',
        'See More'                           => 'Mehr sehen',
    );
    $total = 0;
    foreach ($pairs as $from => $to) {
        $total += (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s)
             WHERE meta_key = '_elementor_data' AND meta_value LIKE %s",
            $from, $to, '%' . $wpdb->esc_like($from) . '%'
        ));
        $total += (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s)
             WHERE post_content LIKE %s",
            $from, $to, '%' . $wpdb->esc_like($from) . '%'
        ));
    }
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_elementor_css', '_elementor_element_cache')");
    return "Done! Updated $total rows (Request for Consultation / Book Consultation â†’ German). Cache cleared.";
}

// Auto-run the German CTA fix once per plugin version after deployment
add_action('admin_init', function () {
    if (get_option('mm_cta_de_fix_done') !== '1.3.0') {
        $msg = mm_fix_cta_german();
        update_option('mm_cta_de_fix_done', '1.3.0');
        update_option('mm_cta_de_fix_result', $msg);
    }
});

add_action('admin_menu', function () {
    add_menu_page('Magic Moon Tools', 'MM Tools', 'manage_options', 'mm-tools', 'mm_tools_page', 'dashicons-hammer', 80);
});

function mm_tools_page() {
    $message = '';

    if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'fix_cta') {
        $message = mm_fix_cta_german();
    }

    if (isset($_POST['mm_action']) && $_POST['mm_action'] === 'repair_ai1wm') {
        $message = mm_repair_ai1wm();
    }

    $auto_result = get_option('mm_ai1wm_repair_result', '');
    ?>
    <div class="wrap">
        <h1>Magic Moon Tools</h1>
        <?php if ($message): ?>
            <div class="notice notice-success"><p><?= esc_html($message) ?></p></div>
        <?php endif; ?>
        <?php if ($auto_result): ?>
            <div class="notice notice-info"><p>Last auto-repair: <?= esc_html($auto_result) ?></p></div>
        <?php endif; ?>

        <h2>Repair All-in-One WP Migration</h2>
        <p>Restores the plugin from the clean bundled copy (overwrites broken files).</p>
        <form method="post">
            <input type="hidden" name="mm_action" value="repair_ai1wm">
            <?php submit_button('Repair AI1WM Now', 'primary'); ?>
        </form>

        <hr>

        <h2>CTA Text Fix</h2>
        <p>Changes all "Book Consultation" buttons to <strong>"Beratung buchen"</strong> sitewide.</p>
        <form method="post">
            <input type="hidden" name="mm_action" value="fix_cta">
            <?php submit_button('Run CTA Fix Now', 'secondary'); ?>
        </form>
    </div>
    <?php
}
