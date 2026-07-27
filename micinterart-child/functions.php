<?php
/**
 * Micinterart Child-Theme – functions.php
 *
 * Alle Funktionen sind in einzelne Module ausgelagert.
 * Reihenfolge beachten: Abhängigkeiten werden zuerst geladen.
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
// 3. WORKSHOP-MONAT CPT
//    Registrierung, Metaboxen, Admin-Spalten
// ============================================================================
require_once get_stylesheet_directory() . '/includes/workshop-monat.php';

// ============================================================================
// 4. CONTACT FORM 7 – WORKSHOP-INTEGRATION
//    Hidden Fields (Workshop-ID, Thema-ID), Mail-Tags, WhatsApp-Button
// ============================================================================
require_once get_stylesheet_directory() . '/includes/workshop-cf7.php';

// ============================================================================
// 5. WORKSHOP – PLÄTZE & BUCHUNGEN
//    Freie Plätze berechnen, Status-Aktualisierung, Buchungs-Metabox
// ============================================================================
require_once get_stylesheet_directory() . '/includes/workshop-plaetze.php';

// ============================================================================
// 6. WORKSHOP-THEMA – BUCHUNGS-METABOX
//    Anmeldungen pro Thema verwalten
// ============================================================================
require_once get_stylesheet_directory() . '/includes/workshop-thema-bookings.php';

// ============================================================================
// 7. WORKSHOP – FRONTEND-ANZEIGE
//    "Was dich erwartet"-Box, Nächster-Workshop-Banner-Shortcode
// ============================================================================
require_once get_stylesheet_directory() . '/includes/workshop-frontend.php';

// ============================================================================
// 8. WORKSHOP – ARCHIVIERUNG & ADMIN
//    Auto-Archivierung vergangener Workshops, Admin-Spalten, Kinder-Validierung
// ============================================================================
require_once get_stylesheet_directory() . '/includes/workshop-archiv.php';

// ============================================================================
// 9. WORKSHOP – RABATT-SYSTEM
//    Hilfsfunktionen, Metaboxen, Speichern, Preisberechnung (Geschwister,
//    Frühbucher, Serien), PayPal-Mail-Filter, Frontend-Rabattanzeige,
//    AJAX-Preisberechnung, PayPal-Integration
//    ⚠️  Muss VOR workshop-preisrechner.php geladen werden!
// ============================================================================
require_once get_stylesheet_directory() . '/includes/workshop-rabatt.php';

// ============================================================================
// 10. WORKSHOP – CUSTOM MAIL-TAGS
//     [workshop_datum], [paypal_amount]
// ============================================================================
require_once get_stylesheet_directory() . '/includes/workshop-mailtags.php';

// ============================================================================
// 11. WORKSHOP – PREISRECHNER & PAYPAL-BUTTONS
//     PayPal-Link in CF7-Mail, dynamische PayPal-Buttons (Kinder & Erwachsene),
//     Live-Preisrechner mit Geschwister-/Frühbucherrabatt-Anzeige
//     Abhängig von: workshop-rabatt.php (micinterart_calculate_final_price)
// ============================================================================
require_once get_stylesheet_directory() . '/includes/workshop-preisrechner.php';

// ============================================================================
// 12. GUTSCHEIN-INTEGRATION
//     WooCommerce-Coupons als Gutscheincodes in CF7-Formularen
//     Abhängig von: workshop-rabatt.php (micinterart_calculate_final_price)
// ============================================================================
require_once get_stylesheet_directory() . '/gutschein-integration.php';

// ============================================================================
// 13. WORKSHOP – SCHEMA.ORG EVENT-MARKUP (JSON-LD)
//    Strukturierte Daten für Google Rich Results (Einzel + Archiv)
// ============================================================================
require_once get_stylesheet_directory() . '/includes/workshop-schema.php';

require_once get_stylesheet_directory() . '/includes/workshop-admin.php';


/**
 * Lighthouse: Preload LCP hero image on /workshops/ to improve LCP.
 */
function micinterart_lh_preload_workshops_hero() {
    // Only on the workshops archive page
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
