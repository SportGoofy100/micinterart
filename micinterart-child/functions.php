<?php
/**
 * Micinterart Child-Theme – functions.php
 *
 * Alle Funktionen sind in einzelne Module ausgelagert.
 * 
 * NEU: Workshop-Buchung über WooCommerce
 * 
 * @package Micinterart
 */

// ============================================================================
// 1. THEME-GRUNDLAGEN
//    Theme-Setup, Assets, Performance-Optimierungen, Cache
// ============================================================================
$theme_setup_file = get_stylesheet_directory() . '/includes/theme-setup.php';
if (file_exists($theme_setup_file)) {
    require_once $theme_setup_file;
}

/**
 * Minimale Sprachhilfe für Polylang/Englisch-Fallback.
 * Dadurch können einzelne Templates ohne großen Aufwand bilingual arbeiten.
 */
function micinterart_is_english() {
    if (function_exists('pll_current_language')) {
        $lang = pll_current_language();
        return in_array($lang, ['en', 'en_US', 'en_US.UTF-8', 'en-gb', 'en-GB'], true);
    }

    return false;
}

function micinterart_t($de, $en = '') {
    return micinterart_is_english() ? ($en !== '' ? $en : $de) : $de;
}

/**
 * Liefert einen Meta-Wert für die aktuelle Sprache.
 * Falls Polylang aktiv ist und ein übersetztes Post existiert, wird der Wert
 * des übersetzten Posts bevorzugt. Für einfache Felder reicht das aus.
 */
function micinterart_get_translated_meta($post_id, $meta_key, $single = true) {
    $value = get_post_meta($post_id, $meta_key, $single);

    if (!function_exists('pll_get_post_translations')) {
        return $value;
    }

    $translations = pll_get_post_translations($post_id);
    if (empty($translations)) {
        return $value;
    }

    $current_lang = function_exists('pll_current_language') ? pll_current_language('slug') : '';
    if ($current_lang === '') {
        return $value;
    }

    if (!isset($translations[$current_lang])) {
        return $value;
    }

    $translated_id = $translations[$current_lang];
    if (!$translated_id) {
        return $value;
    }

    $translated_value = get_post_meta($translated_id, $meta_key, $single);
    return $translated_value !== '' ? $translated_value : $value;
}

// ============================================================================
// 3. GEDICHT CPT – ADMIN & FRONTEND
// ============================================================================
// Gedicht-Funktionen bleiben aktiv

// ============================================================================
// 4. WOOCOMMERCE WORKSHOP INTEGRATION (NEU)
//    Workshop-Produktfelder, Kategorien, Checkout-Anpassungen
//    Lade erst NACH dem WooCommerce geladen wurde (plugins_loaded Hook)
// ============================================================================

/**
 * Lade Workshop-WooCommerce-Includes erst nach dem WooCommerce geladen wurde
 * WooCommerce wird im plugins_loaded Hook mit Priorität 0 geladen,
 * also nutzen wir Priorität 100 um sicherzustellen, dass WC verfügbar ist.
 */
function micinterart_load_workshop_wc_includes() {
    if (!class_exists('WooCommerce')) {
        return;
    }
    
    // Workshop WooCommerce Plugin (Produktfelder und Kategorien)
    $workshop_wc_file = get_stylesheet_directory() . '/micinterart-workshop-woocommerce.php';
    if (file_exists($workshop_wc_file)) {
        require_once $workshop_wc_file;
        // Initialisiere Workshop-WC sofort
        if (class_exists('Micinterart_Workshop_WooCommerce')) {
            Micinterart_Workshop_WooCommerce::get_instance();
        }
    }
    
    // Werk WooCommerce Plugin (wird über eigenen Hook initialisiert)
    $werk_wc_file = get_stylesheet_directory() . '/micinterart-werk-woocommerce.php';
    if (file_exists($werk_wc_file)) {
        require_once $werk_wc_file;
    }
    
    // Workshop Checkout Anpassungen (Teilnehmerfelder, Rabatte)
    $workshop_checkout_file = get_stylesheet_directory() . '/includes/workshop-checkout.php';
    if (file_exists($workshop_checkout_file)) {
        require_once $workshop_checkout_file;
    }
    
}
add_action('after_setup_theme', 'micinterart_load_workshop_wc_includes', 20);

// ============================================================================
// 5. WORKSHOP – ARCHIV & EINZELSEITE (NEUE TEMPLATES)
//    Archiv und Einzelseite für WC-Produkte
//    Die alten Templates werden durch neue WC-basierte ersetzt
// ============================================================================
// Neue Templates werden direkt aufgerufen, keine Includes nötig

// ============================================================================
// 6. LIGHTHOUSE OPTIMIERUNGEN
// ============================================================================

/**
 * Lighthouse: Preload LCP hero image on /workshops/ to improve LCP.
 */
function micinterart_lh_preload_workshops_hero() {
    // Nur auf der alten Workshop-Archivseite
    if (!is_admin() && function_exists('is_post_type_archive') && is_post_type_archive('workshop')) {
        echo "\n<link rel=\"preload\" as=\"image\" href=\"https://micinterart.de/wp-content/uploads/2025/10/37-DSC_9606.jpg\" fetchpriority=\"high\">\n";
    }
}
add_action('wp_head', 'micinterart_lh_preload_workshops_hero', 1);


/**
 * Lighthouse: Ensure font-display: swap for Bebas Neue.
 */
function micinterart_lh_font_display_swap() {
    echo "\n<style id=\"micinterart_lh_font_display_swap\">\n";
    echo "@font-face{font-family:'Bebas Neue';font-display:swap;}\n";
    echo "</style>\n";
}
add_action('wp_head', 'micinterart_lh_font_display_swap', 2);

// ============================================================================
// 8. VERALTETE WORKSHOP-INCLUDES (DEAKTIVIERT)
//    Die folgenden Includes werden nicht mehr geladen, da Workshops nun
//    über WooCommerce verwaltet werden
// ============================================================================
// 
// DEAKTIVIERT (ersetzt durch WooCommerce):
// - workshop-cf7.php (Contact Form 7 Integration)
// - workshop-preisrechner.php (Preisrechner und PayPal-Buttons)
// - workshop-plaetze.php (Plätze-Zähler)
// - workshop-rabatt.php (Rabatt-System - teilweise durch WC ersetzt)
// - workshop-thema-bookings.php (Thema-Buchungen)
// - workshop-thema-sync.php (Thema-Synchronisation)
// - workshop-monat.php (Workshop-Monate CPT)
// - gutschein-integration.php (Gutscheine - durch WC Coupons ersetzt)
//
// BEIBEHALTEN (noch benötigt für "Was dich erwartet" Felder):
// - workshop-admin.php (wird noch für CPT Workshop benötigt, bis Migration abgeschlossen)
//

// ============================================================================
// 9. TEMPLATE-FILTER (für WC-Workshop-Produkte)
// ============================================================================

/**
 * Gedicht-Templates: Belasse bestehende Templates
 */
function micinterart_gedicht_templates($template) {
    if (get_post_type() === 'gedicht') {
        $new_template = locate_template('single-gedicht.php');
        if ($new_template) {
            return $new_template;
        }
    }
    return $template;
}
add_filter('single_template', 'micinterart_gedicht_templates');

/**
 * Workshop-Templates: Leite zu WC-Produkt-Templates um
 */
function micinterart_workshop_templates($template) {
    if (get_post_type() === 'workshop') {
        wp_safe_redirect(home_url('/workshops/'), 301);
        exit;
    }
    return $template;
}
add_filter('single_template', 'micinterart_workshop_templates');

/**
 * Workshop-Archiv: Leite zur WC-Produktkategorie um
 */
function micinterart_workshop_archive_template($template) {
    if (is_post_type_archive('workshop')) {
        $template = locate_template('archive-workshop.php');
        if ($template) {
            return $template;
        }
    }
    return $template;
}
add_filter('archive_template', 'micinterart_workshop_archive_template');

/**
 * Werk-Archiv: Zeige WC-Produkte statt altem CPT
 */
function micinterart_werk_archive_template($template) {
    if (is_post_type_archive('werk')) {
        $new_template = locate_template('archive-werk-wc.php');
        if ($new_template) {
            return $new_template;
        }
    }
    return $template;
}
add_filter('archive_template', 'micinterart_werk_archive_template', 25);

function micinterart_render_wc_default_single_product() {
    get_header('shop');
    do_action('woocommerce_before_main_content');

    while (have_posts()) {
        the_post();
        wc_get_template_part('content', 'single-product');
    }

    do_action('woocommerce_after_main_content');
    get_footer('shop');
}

/**
 * WC-Produkt-Templates: Verwende angepasste Workshop-Templates für WC-Produkte
 */
function micinterart_wc_product_templates($template) {
    if (is_singular('product')) {
        $product_id = get_the_ID();
        
        // Prüfen ob es ein Workshop-Produkt ist
        $product = wc_get_product($product_id);
        $is_workshop = function_exists('micinterart_wc_is_workshop_product')
            && micinterart_wc_is_workshop_product($product);
        $terms = get_the_terms($product_id, 'product_cat');
        
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                if ($term->slug === 'workshops' || $term->slug === 'atelierkurse' || $term->slug === 'kinderworkshops') {
                    $is_workshop = true;
                    break;
                }
            }
        }
        
        if ($is_workshop) {
            // Verwende unser angepasstes Template
            $new_template = locate_template('single-product-workshop.php');
            if ($new_template) {
                return $new_template;
            }
        }
    }
    return $template;
}
add_filter('single_product_template', 'micinterart_wc_product_templates');

/**
 * WC-Produktkategorie-Archiv: Verwende angepasstes Workshop-Archiv-Template
 */
function micinterart_wc_product_cat_archive_template($template) {
    if (is_tax('product_cat')) {
        $term = get_queried_object();
        
        if ($term && ($term->slug === 'workshops' || $term->slug === 'atelierkurse' || $term->slug === 'kinderworkshops')) {
            $new_template = locate_template('archive-workshop-wc.php');
            if ($new_template) {
                return $new_template;
            }
        }
    }
    return $template;
}
add_filter('taxonomy_template', 'micinterart_wc_product_cat_archive_template');

/**
 * Werk-Einzelseite: Leite zum WC-Produkt um (nach Migration)
 */
function micinterart_werk_single_template($template) {
    if (is_singular('werk')) {
        wp_safe_redirect(home_url('/galerie/'), 301);
        exit;
    }
    return $template;
}
add_filter('single_template', 'micinterart_werk_single_template', 20);

// ============================================================================
// 10. WOOCMOMERCE SPEZIFISCHE ANPASSUNGEN
// ============================================================================

/**
 * Entferne unnötige WC-Styles und Scripts für Workshop-Produkte
 */
function micinterart_dequeue_wc_assets() {
    if (is_product() || is_product_category() || is_product_tag()) {
        // Prüfen ob Workshop-Produkt
        if (is_product()) {
            $product_id = get_the_ID();
            $terms = get_the_terms($product_id, 'product_cat');
            
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    if ($term->slug === 'workshops' || $term->slug === 'atelierkurse' || $term->slug === 'kinderworkshops') {
                        // WC-Standard-CSS deaktivieren für Workshops
                        wp_dequeue_style('woocommerce-layout');
                        wp_dequeue_style('woocommerce-smallscreen');
                        wp_dequeue_style('woocommerce-general');
                        break;
                    }
                }
            }
        }
    }
}
// add_action('wp_enqueue_scripts', 'micinterart_dequeue_wc_assets', 999);
// DEAKTIVIERT: Besser WC-Styles beibehalten für Kompatibilität
