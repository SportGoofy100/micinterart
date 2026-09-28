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
require_once get_stylesheet_directory() . '/includes/theme-setup.php';

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
// 2. WERK CPT – ADMIN
//    Custom Columns, Sortierung, Gedicht-Excerpt
// ============================================================================
require_once get_stylesheet_directory() . '/includes/werk-admin.php';

// ============================================================================
// 3. GEDICHT CPT – ADMIN & FRONTEND
// ============================================================================
// Gedicht-Funktionen bleiben aktiv

// ============================================================================
// 4. WOOCOMMERCE WORKSHOP INTEGRATION (NEU)
//    Workshop-Produktfelder, Kategorien, Checkout-Anpassungen
// ============================================================================

// Workshop WooCommerce Plugin (Produktfelder und Kategorien)
if (class_exists('WooCommerce')) {
    require_once get_stylesheet_directory() . '/micinterart-workshop-woocommerce.php';
    
    // Workshop Migration (nur im Admin)
    if (is_admin()) {
        require_once get_stylesheet_directory() . '/includes/workshop-migration.php';
    }
    
    // Workshop Checkout Anpassungen (Teilnehmerfelder, Rabatte)
    require_once get_stylesheet_directory() . '/includes/workshop-checkout.php';
    
    // 301-Weiterleitungen von alten URLs
    require_once get_stylesheet_directory() . '/includes/workshop-redirects.php';
}

// ============================================================================
// 5. WORKSHOP – FRONTEND-ANZEIGE (ANGEPASST)
//    "Was dich erwartet"-Box für WC-Produkte
// ============================================================================
require_once get_stylesheet_directory() . '/includes/workshop-frontend.php';

// ============================================================================
// 6. WORKSHOP – ARCHIV & EINZELSEITE (NEUE TEMPLATES)
//    Archiv und Einzelseite für WC-Produkte
//    Die alten Templates werden durch neue WC-basierte ersetzt
// ============================================================================
// Neue Templates werden direkt aufgerufen, keine Includes nötig

// ============================================================================
// 7. LIGHTHOUSE OPTIMIERUNGEN
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
    // über WooCommerce verwaltet werden
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
 * Werk-Templates: Belasse bestehende Templates
 */
function micinterart_werk_templates($template) {
    if (get_post_type() === 'werk') {
        $new_template = locate_template('single-werk.php');
        if ($new_template) {
            return $new_template;
        }
    }
    return $template;
}
add_filter('single_template', 'micinterart_werk_templates');

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
    // Wenn Workshop CPT noch existiert und aufgerufen wird
    if (get_post_type() === 'workshop') {
        // Prüfen ob Migration abgeschlossen
        $migration_done = get_option('micinterart_workshop_migration_done', false);
        
        // Wenn Migration abgeschlossen, zur neuen WC-Ansicht umleiten
        if ($migration_done) {
            $migration_map = get_option('micinterart_workshop_migration_map', []);
            $workshop_id = get_the_ID();
            
            if (isset($migration_map[$workshop_id])) {
                $product_url = get_permalink($migration_map[$workshop_id]);
                if ($product_url) {
                    wp_redirect($product_url, 301);
                    exit;
                }
            }
        }
        
        // Sonst altes Template laden
        $new_template = locate_template('single-workshop.php');
        if ($new_template) {
            return $new_template;
        }
    }
    return $template;
}
add_filter('single_template', 'micinterart_workshop_templates');

/**
 * Workshop-Archiv: Leite zur WC-Produktkategorie um
 */
function micinterart_workshop_archive_template($template) {
    if (is_post_type_archive('workshop')) {
        $migration_done = get_option('micinterart_workshop_migration_done', false);
        
        if ($migration_done) {
            $workshops_term = get_term_by('slug', 'workshops', 'product_cat');
            if ($workshops_term) {
                $product_archive_url = get_term_link($workshops_term);
                if ($product_archive_url) {
                    wp_redirect($product_archive_url, 301);
                    exit;
                }
            }
        }
        
        // Sonst altes Template laden
        $new_template = locate_template('archive-workshop.php');
        if ($new_template) {
            return $new_template;
        }
    }
    return $template;
}
add_filter('archive_template', 'micinterart_workshop_archive_template');

/**
 * WC-Produkt-Templates: Verwende angepasste Workshop-Templates für WC-Produkte
 */
function micinterart_wc_product_templates($template) {
    if (is_singular('product')) {
        $product_id = get_the_ID();
        
        // Prüfen ob es ein Workshop-Produkt ist
        $is_workshop = false;
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
