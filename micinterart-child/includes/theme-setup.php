<?php
/**
 * Theme-Setup: Grundfunktionen, Assets, Performance, Cache
 *
 * @package Micinterart
 */

/**
 * Theme-Setup: Features aktivieren
 */
function micinterart_theme_setup() {
    add_theme_support('post-thumbnails', ['post', 'page', 'workshop', 'werk']); // Liste hier die Typen explizit auf
    add_theme_support('html5', ['search-form','comment-form','comment-list','gallery','caption','script','style']);
    add_theme_support('responsive-embeds');
    add_theme_support('custom-logo', ['height'=>100,'width'=>400,'flex-height'=>true,'flex-width'=>true]);
}
add_action('after_setup_theme', 'micinterart_theme_setup');

/**
 * Scripts und Styles einbinden
 */
function micinterart_enqueue_assets() {
    $theme_version = wp_get_theme()->get('Version');
    wp_enqueue_style('micinterart-style', get_stylesheet_directory_uri() . '/style.css', [], $theme_version);
    
    $custom_css_path = get_stylesheet_directory() . '/assets/css/custom.css';
    if (file_exists($custom_css_path)) {
        wp_enqueue_style('micinterart-custom', get_stylesheet_directory_uri() . '/assets/css/custom.css', ['micinterart-style'], filemtime($custom_css_path));
    }
    
    $custom_js_path = get_stylesheet_directory() . '/assets/js/custom.js';
    if (file_exists($custom_js_path)) {
        wp_enqueue_script('micinterart-custom', get_stylesheet_directory_uri() . '/assets/js/custom.js', ['jquery'], filemtime($custom_js_path), true);
        wp_localize_script('micinterart-custom', 'micinterartData', ['ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('micinterart_nonce')]);
    }
}
add_action('wp_enqueue_scripts', 'micinterart_enqueue_assets');

/**
 * Entfernt Meta-Ausgabe für CPT "gedicht"
 */
function micinterart_remove_meta_for_gedichte($enabled, $location) {
    if (is_singular('gedicht') || is_post_type_archive('gedicht')) {
        return false;
    }
    return $enabled;
}
add_filter('blocksy:single:post-meta:enabled', 'micinterart_remove_meta_for_gedichte', 10, 2);
add_filter('blocksy:archive:post-meta:enabled', 'micinterart_remove_meta_for_gedichte', 10, 2);

/**
 * Post Type Supports sicherstellen
 */
function micinterart_restore_post_type_supports() {
    $supports = ['title','editor','thumbnail','excerpt'];
    add_post_type_support('post', $supports);
    add_post_type_support('page', $supports);
    if (post_type_exists('werk')) {
        add_post_type_support('werk', $supports);
    }
}
add_action('init', 'micinterart_restore_post_type_supports', 100);

/**
 * Beitragsbild-Metabox erzwingen - Jetzt inklusive Workshops
 */
function micinterart_force_featured_image_box() {
    // Wir fügen 'workshop' und sicherheitshalber auch 'workshop_thema' hinzu
    add_meta_box(
        'postimagediv', 
        __('Beitragsbild', 'micinterart'), 
        'post_thumbnail_meta_box', 
        ['post', 'page', 'werk', 'workshop', 'workshop_thema'], 
        'side', 
        'low'
    );
}
add_action('add_meta_boxes', 'micinterart_force_featured_image_box');

/**
 * Performance: Emoji-Scripts entfernen
 */
function micinterart_disable_emojis() {
    remove_action('wp_head','print_emoji_detection_script',7);
    remove_action('admin_print_scripts','print_emoji_detection_script');
    remove_action('wp_print_styles','print_emoji_styles');
    remove_action('admin_print_styles','print_emoji_styles');
    remove_filter('the_content_feed','wp_staticize_emoji');
    remove_filter('comment_text_rss','wp_staticize_emoji');
    remove_filter('wp_mail','wp_staticize_emoji_for_email');
}
add_action('init', 'micinterart_disable_emojis');

/**
 * Performance: jQuery in Footer laden
 */
function micinterart_move_jquery_to_footer() {
    if (!is_admin()) {
        wp_scripts()->add_data('jquery','group',1);
        wp_scripts()->add_data('jquery-core','group',1);
        wp_scripts()->add_data('jquery-migrate','group',1);
    }
}
add_action('wp_enqueue_scripts', 'micinterart_move_jquery_to_footer');

/**
 * Performance: Lazy Loading für iframes aktivieren
 */
function micinterart_add_iframe_lazy_loading($content) {
    if (is_admin()) return $content;
    return str_replace('<iframe','<iframe loading="lazy"',$content);
}
add_filter('the_content', 'micinterart_add_iframe_lazy_loading', 99);

/**
 * SEO: Excerpt Length anpassen
 */
function micinterart_excerpt_length($length) {
    return 30;
}
add_filter('excerpt_length', 'micinterart_excerpt_length');

/**
 * SEO: Excerpt More anpassen
 */
function micinterart_excerpt_more($more) {
    if (is_admin()) return $more;
    global $post;
    return '... <a class="read-more" href="'.get_permalink($post->ID).'">'.__('Weiterlesen','micinterart').'</a>';
}
add_filter('excerpt_more', 'micinterart_excerpt_more');

/**
 * Accessibility: Skip Link hinzufügen
 */
function micinterart_skip_link() {
    echo '<a class="skip-link screen-reader-text" href="#primary">'.__('Zum Inhalt springen','micinterart').'</a>';
}
add_action('wp_body_open', 'micinterart_skip_link');

/**
 * Custom Image Sizes
 */
function micinterart_custom_image_sizes() {
    add_image_size('gallery-thumbnail',400,400,true);
    add_image_size('werk-preview',600,600,false);
    add_image_size('hero',1920,800,true);
}
add_action('after_setup_theme', 'micinterart_custom_image_sizes');

/**
 * Admin: Custom Image Sizes in Media Library anzeigen
 */
function micinterart_custom_image_sizes_names($sizes) {
    return array_merge($sizes,['gallery-thumbnail'=>__('Galerie Thumbnail','micinterart'),'werk-preview'=>__('Werk Vorschau','micinterart'),'hero'=>__('Hero Bild','micinterart')]);
}
add_filter('image_size_names_choose', 'micinterart_custom_image_sizes_names');

/**
 * Cache-Helper: Transients löschen bei Post-Änderungen
 */
function micinterart_clear_related_caches($post_id) {
    $post_type = get_post_type($post_id);
    if (in_array($post_type,['werk','gedicht'])) {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mic_gallery_%' OR option_name LIKE '_transient_timeout_mic_gallery_%'");
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
}
add_action('save_post', 'micinterart_clear_related_caches');
add_action('delete_post', 'micinterart_clear_related_caches');
