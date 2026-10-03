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

// Shop-Startseite: Kachelbilder (Customizer)
$shop_tiles_file = get_stylesheet_directory() . '/includes/shop-tiles.php';
if (file_exists($shop_tiles_file)) {
    require_once $shop_tiles_file;
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
    
    // Werk WooCommerce Plugin
    $werk_wc_file = get_stylesheet_directory() . '/micinterart-werk-woocommerce.php';
    if (file_exists($werk_wc_file)) {
        require_once $werk_wc_file;
        // Initialisiere Werk-WC sofort
        if (class_exists('Micinterart_Werk_WooCommerce')) {
            Micinterart_Werk_WooCommerce::get_instance();
        }
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
// 8. ALTE WORKSHOP-INCLUDES
//    Workshops werden über WooCommerce verwaltet. Die früheren Includes
//    (CF7, Preisrechner, Plätze, Rabatt, Themen, Gutscheine, ...) wurden aus
//    dem Repository entfernt und sind in der Git-Historie nachlesbar.
//    includes/workshop-admin.php liegt noch im Ordner, wird aber nicht geladen.
// ============================================================================

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
 * Workshop-Archiv: Erzwinge die WooCommerce-Produktübersicht als finales Template.
 */
function micinterart_workshop_archive_template($template) {
    if (is_post_type_archive('workshop')) {
        $workshop_template = locate_template('archive-workshop-wc.php');
        if ($workshop_template) {
            return $workshop_template;
        }
    }
    return $template;
}
add_filter('template_include', 'micinterart_workshop_archive_template', 99);

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

// Hinweis: Die Wahl zwischen Workshop- und Standard-Produktseite trifft single-product.php
// (WooCommerce lädt diese Datei aus dem Theme).

/**
 * WC-Produktkategorie-Archiv: Verwende angepasstes Workshop-Archiv-Template
 */
function micinterart_wc_product_cat_archive_template($template) {
    if (is_tax('product_cat')) {
        $term = get_queried_object();
        
        if ($term && in_array($term->slug, ['workshops', 'atelierkurse', 'kinderworkshops', 'kinderworkshop', 'erwachsenenworkshop', 'erwachsenenworkshops'], true)) {
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
                    if (in_array($term->slug, ['workshops', 'atelierkurse', 'kinderworkshops', 'kinderworkshop', 'erwachsenenworkshop', 'erwachsenenworkshops'], true)) {
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

// ============================================================================
// SHOP- UND WERK-ARCHIV: STYLES UND PREISANZEIGE
// ============================================================================

/**
 * Allgemeine Frontend-Styles: Mobil/Tablet, Werk-Details, Workshop-Seiten (assets/css/frontend.css)
 */
function micinterart_enqueue_frontend_styles() {
    $css_path = get_stylesheet_directory() . '/assets/css/frontend.css';
    if (file_exists($css_path)) {
        wp_enqueue_style(
            'micinterart-frontend',
            get_stylesheet_directory_uri() . '/assets/css/frontend.css',
            [],
            filemtime($css_path)
        );
    }
}
add_action('wp_enqueue_scripts', 'micinterart_enqueue_frontend_styles', 20);

/**
 * Kartendesign für Shop-Seite und Werk-Archiv (assets/css/werke-archive.css)
 */
function micinterart_enqueue_archive_styles() {
    $is_werke_page   = (bool) get_query_var('micinterart_werke');
    $is_atelier_page = (bool) get_query_var('micinterart_atelier');
    $is_shop_page    = function_exists('is_shop') && is_shop();
    $is_werk_cpt     = is_post_type_archive('werk');

    if (!$is_werke_page && !$is_atelier_page && !$is_shop_page && !$is_werk_cpt) {
        return;
    }

    $css_path = get_stylesheet_directory() . '/assets/css/werke-archive.css';
    if (file_exists($css_path)) {
        wp_enqueue_style(
            'micinterart-werke-archive',
            get_stylesheet_directory_uri() . '/assets/css/werke-archive.css',
            [],
            filemtime($css_path)
        );
    }
}
add_action('wp_enqueue_scripts', 'micinterart_enqueue_archive_styles');

/**
 * Schmale Navigationsleiste zwischen den Shop-Bereichen (auf kleinen Bildschirmen wischbar).
 *
 * @param string $active 'workshops', 'werke' oder 'atelier'
 */
function micinterart_shop_subnav($active) {
    $items = [
        'workshops' => ['Workshops', 'Workshops', get_post_type_archive_link('workshop') ?: home_url('/workshops/')],
        'werke'     => ['Werke', 'Artworks', home_url('/werke/')],
        'atelier'   => ['Atelier-Shop', 'Atelier Shop', home_url('/atelier-shop/')],
    ];

    echo '<nav class="shop-subnav" aria-label="' . esc_attr(micinterart_t('Shop-Bereiche', 'Shop areas')) . '">';
    foreach ($items as $key => $item) {
        $is_active = ($key === $active);
        echo '<a class="shop-subnav-link' . ($is_active ? ' is-active' : '') . '" href="' . esc_url($item[2]) . '"'
            . ($is_active ? ' aria-current="page"' : '') . '>'
            . esc_html(micinterart_t($item[0], $item[1])) . '</a>';
    }
    echo '</nav>';
}

/**
 * Beschriftung für Werke, die nicht (mehr) verfügbar sind ("Verkauft", "Reserviert", ...);
 * leer, wenn das Werk verfügbar ist.
 */
function micinterart_werk_status_label($product_id) {
    $labels = [
        'reserviert'   => micinterart_t('Reserviert', 'Reserved'),
        'verkauft'     => micinterart_t('Verkauft', 'Sold'),
        'privatbesitz' => micinterart_t('Privatbesitz', 'Private collection'),
    ];
    $status = get_post_meta($product_id, '_werk_status', true);
    return isset($labels[$status]) ? $labels[$status] : '';
}

/**
 * Preis eines Werks als HTML; ohne hinterlegten Preis "Preis auf Anfrage".
 */
function micinterart_werk_price_html($product) {
    $html = $product ? $product->get_price_html() : '';
    if ($html !== '') {
        return wp_kses_post($html);
    }
    return '<span class="shop-item-price-request">'
        . esc_html(micinterart_t('Preis auf Anfrage', 'Price on request'))
        . '</span>';
}

// ============================================================================
// REWRITE-REGELN FÜR WERKE
// ============================================================================

/**
 * Rewrite-Regeln: /werke/ zeigt alle Produkte vom Typ "werk" (archive-werk-wc.php).
 * Unabhängig von einer Produktkategorie "werke".
 */
function micinterart_werke_rewrite_rules() {
    add_rewrite_rule('^werke/page/([0-9]+)/?$', 'index.php?micinterart_werke=1&paged=$matches[1]', 'top');
    add_rewrite_rule('^werke/?$', 'index.php?micinterart_werke=1', 'top');

    // /atelier-shop/ zeigt alle übrigen Produkte (weder Werk noch Workshop)
    add_rewrite_rule('^atelier-shop/page/([0-9]+)/?$', 'index.php?micinterart_atelier=1&paged=$matches[1]', 'top');
    add_rewrite_rule('^atelier-shop/?$', 'index.php?micinterart_atelier=1', 'top');
}
add_action('init', 'micinterart_werke_rewrite_rules', 10, 0);

function micinterart_werke_query_vars($vars) {
    $vars[] = 'micinterart_werke';
    $vars[] = 'micinterart_atelier';
    return $vars;
}
add_filter('query_vars', 'micinterart_werke_query_vars');

/**
 * Permalinks einmalig neu schreiben, sobald sich die Werke-Regeln ändern
 * (hochzählen, wenn die Regeln oben angepasst werden).
 */
function micinterart_werke_maybe_flush_rewrite_rules() {
    if (get_option('micinterart_werke_rewrite_version') !== '3') {
        flush_rewrite_rules(false);
        update_option('micinterart_werke_rewrite_version', '3');
    }
}
add_action('init', 'micinterart_werke_maybe_flush_rewrite_rules', 99);

/**
 * /werke/ mit dem Werk-Archiv-Template, /atelier-shop/ mit dem Shop-Template ausliefern
 */
function micinterart_werke_template($template) {
    $template_file = '';
    if (get_query_var('micinterart_werke')) {
        $template_file = 'archive-werk-wc.php';
    } elseif (get_query_var('micinterart_atelier')) {
        $template_file = 'archive-shop-wc.php';
    }

    if ($template_file !== '') {
        $new_template = locate_template($template_file);
        if ($new_template) {
            global $wp_query;
            $wp_query->is_404 = false;
            status_header(200);
            return $new_template;
        }
    }
    return $template;
}
add_filter('template_include', 'micinterart_werke_template', 99);

// Kein Canonical-Redirect für /werke/ und /atelier-shop/
function micinterart_werke_no_canonical_redirect($redirect_url) {
    return (get_query_var('micinterart_werke') || get_query_var('micinterart_atelier')) ? false : $redirect_url;
}
add_filter('redirect_canonical', 'micinterart_werke_no_canonical_redirect');

// ============================================================================
// E-MAILS (WooCommerce): Produktbilder und Logo
// ============================================================================

/**
 * Produktbilder in der Bestellübersicht der WooCommerce-E-Mails anzeigen
 * (Bestellbestätigung, Rechnung, ...). Standardmäßig sind sie ausgeschaltet.
 */
function micinterart_email_show_product_images($args) {
    $args['show_image'] = true;
    $args['image_size'] = [100, 100];
    return $args;
}
add_filter('woocommerce_email_order_items_args', 'micinterart_email_show_product_images');

/**
 * E-Mail-Kopfbild: Ist unter WooCommerce → Einstellungen → E-Mails kein Bild gesetzt,
 * wird das Website-Logo (Design → Anpassen → Website-Identität) verwendet.
 */
function micinterart_email_header_image_fallback($value) {
    if (!empty($value)) {
        return $value;
    }

    // In den WooCommerce-Einstellungen das Feld nicht scheinbar "gefüllt" anzeigen
    if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'wc-settings') {
        return $value;
    }

    $logo_id = get_theme_mod('custom_logo');
    if ($logo_id) {
        $url = wp_get_attachment_image_url($logo_id, 'medium');
        if ($url) {
            return $url;
        }
    }

    return $value;
}
add_filter('option_woocommerce_email_header_image', 'micinterart_email_header_image_fallback');
