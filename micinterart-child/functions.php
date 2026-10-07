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
    $status = micinterart_werk_status($product_id);
    return isset($labels[$status]) ? $labels[$status] : '';
}

/**
 * Mehrsprachigkeit (Polylang): Das deutsche Original eines Werks ist maßgeblich für
 * Status und Verfügbarkeit. Ohne Polylang ist das Werk selbst das Original.
 */
function micinterart_werk_source_id($post_id) {
    if (function_exists('pll_default_language') && function_exists('pll_get_post')) {
        $source = pll_get_post($post_id, pll_default_language());
        if ($source) {
            return (int) $source;
        }
    }
    return (int) $post_id;
}

/**
 * Werk-Status ('' = verfügbar, 'verfuegbar', 'reserviert', 'verkauft', 'privatbesitz') vom Original
 */
function micinterart_werk_status($post_id) {
    return (string) get_post_meta(micinterart_werk_source_id($post_id), '_werk_status', true);
}

/**
 * IDs aller Sprachfassungen eines Werks (einschließlich des Werks selbst)
 */
function micinterart_werk_translation_ids($post_id) {
    $ids = [(int) $post_id];
    if (function_exists('pll_get_post_translations')) {
        $translations = pll_get_post_translations($post_id);
        if (is_array($translations)) {
            $ids = array_merge($ids, array_map('intval', array_values($translations)));
        }
    }
    return array_values(array_unique(array_filter($ids)));
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

    // Dieselben Seiten mit Sprachpräfix (Polylang, z.B. /en/werke/); 'lang' erkennt Polylang selbst
    add_rewrite_rule('^([a-z]{2,3})/werke/page/([0-9]+)/?$', 'index.php?lang=$matches[1]&micinterart_werke=1&paged=$matches[2]', 'top');
    add_rewrite_rule('^([a-z]{2,3})/werke/?$', 'index.php?lang=$matches[1]&micinterart_werke=1', 'top');
    add_rewrite_rule('^([a-z]{2,3})/atelier-shop/page/([0-9]+)/?$', 'index.php?lang=$matches[1]&micinterart_atelier=1&paged=$matches[2]', 'top');
    add_rewrite_rule('^([a-z]{2,3})/atelier-shop/?$', 'index.php?lang=$matches[1]&micinterart_atelier=1', 'top');
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
    if (get_option('micinterart_werke_rewrite_version') !== '4') {
        flush_rewrite_rules(false);
        update_option('micinterart_werke_rewrite_version', '4');
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

// ============================================================================
// MEHRSPRACHIGKEIT (Polylang): WooCommerce-Seiten in der aktuellen Sprache
// ============================================================================

/**
 * Shop, Warenkorb, Kasse und Mein Konto: im Frontend die übersetzte Seite der aktuellen Sprache
 * verwenden (WooCommerce kennt sonst nur die deutsche Seite). Ohne Polylang oder ohne
 * Übersetzung der Seite bleibt alles wie es ist.
 */
function micinterart_wc_translate_page_id($page_id) {
    if (is_admin() && !wp_doing_ajax()) {
        return $page_id;
    }

    if ($page_id > 0 && function_exists('pll_get_post')) {
        $translated_id = pll_get_post($page_id);
        if ($translated_id) {
            return (int) $translated_id;
        }
    }

    return $page_id;
}
foreach (['shop', 'cart', 'checkout', 'myaccount', 'terms'] as $micinterart_wc_page) {
    add_filter('woocommerce_get_' . $micinterart_wc_page . '_page_id', 'micinterart_wc_translate_page_id');
}
unset($micinterart_wc_page);


// ============================================================================
// ADMIN: Produktliste aufgeräumt
// ============================================================================

/**
 * Die Spalte "SEO Details" von Rank Math wird in der Produktliste sehr schmal und dadurch
 * extrem hoch (jedes Wort in einer eigenen Zeile). Feste Mindestbreite verhindert das.
 */
function micinterart_admin_product_list_styles() {
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'edit-product') {
        return;
    }
    echo '<style>
        .post-type-product .wp-list-table th.column-rank_math_seo_details,
        .post-type-product .wp-list-table td.column-rank_math_seo_details { width: 170px; min-width: 170px; }
        .post-type-product .wp-list-table td.column-rank_math_seo_details { word-break: normal; overflow-wrap: normal; }
        .post-type-product .wp-list-table th.column-taxonomy-serie,
        .post-type-product .wp-list-table td.column-taxonomy-serie { min-width: 70px; word-break: normal; }
        .post-type-product .wp-list-table th.column-micinterart_status { width: 150px; }
    </style>';
}
add_action('admin_head', 'micinterart_admin_product_list_styles');

/**
 * Status-Spalte in der Produktliste: Werke und Workshops lassen sich direkt dort umstellen.
 */
function micinterart_product_status_options($type) {
    if ($type === 'werk') {
        return [
            'verfuegbar'   => 'Verfügbar',
            'reserviert'   => 'Reserviert',
            'verkauft'     => 'Verkauft',
            'privatbesitz' => 'Privatbesitz',
        ];
    }
    return [
        'geplant'         => 'Geplant',
        'anmeldung_offen' => 'Anmeldung offen',
        'fast_ausgebucht' => 'Fast ausgebucht',
        'ausgebucht'      => 'Ausgebucht',
        'beendet'         => 'Beendet',
        'abgesagt'        => 'Abgesagt',
    ];
}

function micinterart_product_status_column($columns) {
    $new = [];
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        if ($key === 'is_in_stock') {
            $new['micinterart_status'] = 'Status';
        }
    }
    if (!isset($new['micinterart_status'])) {
        $new['micinterart_status'] = 'Status';
    }
    return $new;
}
add_filter('manage_edit-product_columns', 'micinterart_product_status_column', 20);

function micinterart_product_status_column_content($column, $post_id) {
    if ($column !== 'micinterart_status') {
        return;
    }
    $product = wc_get_product($post_id);
    $type = $product ? $product->get_type() : '';
    if (!in_array($type, ['werk', 'workshop', 'workshop_variable'], true)) {
        echo '<span aria-hidden="true">–</span>';
        return;
    }
    if ($type === 'werk') {
        // Der Status gilt für alle Sprachfassungen und wird am Original gepflegt
        $current = micinterart_werk_status($post_id) ?: 'verfuegbar';
    } else {
        $current = get_post_meta($post_id, '_workshop_status', true) ?: 'geplant';
    }
    echo '<select class="micinterart-status-select" data-id="' . (int) $post_id . '" data-prev="' . esc_attr($current) . '">';
    foreach (micinterart_product_status_options($type) as $value => $label) {
        echo '<option value="' . esc_attr($value) . '"' . selected($current, $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select><span class="micinterart-status-note" aria-live="polite"></span>';
}
add_action('manage_product_posts_custom_column', 'micinterart_product_status_column_content', 10, 2);

function micinterart_ajax_set_product_status() {
    check_ajax_referer('micinterart_product_status', 'nonce');

    $post_id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    $status  = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';
    if (!$post_id || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error('Keine Berechtigung.', 403);
    }

    $product = wc_get_product($post_id);
    $type = $product ? $product->get_type() : '';
    if (!in_array($type, ['werk', 'workshop', 'workshop_variable'], true) || !isset(micinterart_product_status_options($type)[$status])) {
        wp_send_json_error('Ungültiger Status.', 400);
    }

    if ($type === 'werk') {
        // Alle Sprachfassungen gemeinsam; ohne Lagerverwaltung folgt der Lagerstatus dem Werk-Status
        foreach (micinterart_werk_translation_ids($post_id) as $werk_id) {
            update_post_meta($werk_id, '_werk_status', $status);
            if ($status !== 'verkauft') {
                delete_post_meta($werk_id, '_werk_sold_order');
            }
            $werk = wc_get_product($werk_id);
            if ($werk && !$werk->get_manage_stock()) {
                $werk->set_stock_status($status === 'verfuegbar' ? 'instock' : 'outofstock');
                $werk->save();
            }
        }
    } else {
        update_post_meta($post_id, '_workshop_status', $status);
    }

    wp_send_json_success();
}
add_action('wp_ajax_micinterart_set_product_status', 'micinterart_ajax_set_product_status');

function micinterart_product_status_script() {
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'edit-product') {
        return;
    }
    ?>
    <script>
    (function () {
        var nonce = <?php echo wp_json_encode(wp_create_nonce('micinterart_product_status')); ?>;
        document.addEventListener('change', function (e) {
            var select = e.target.closest ? e.target.closest('.micinterart-status-select') : null;
            if (!select) { return; }
            var note = select.parentNode.querySelector('.micinterart-status-note');
            var body = new URLSearchParams({
                action: 'micinterart_set_product_status',
                nonce: nonce,
                id: select.dataset.id,
                status: select.value
            });
            select.disabled = true;
            note.textContent = ' …';
            fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        select.dataset.prev = select.value;
                        note.textContent = ' ✓';
                    } else {
                        select.value = select.dataset.prev;
                        note.textContent = ' Fehler';
                    }
                })
                .catch(function () {
                    select.value = select.dataset.prev;
                    note.textContent = ' Fehler';
                })
                .then(function () {
                    select.disabled = false;
                    setTimeout(function () { note.textContent = ''; }, 2500);
                });
        });
    })();
    </script>
    <?php
}
add_action('admin_footer', 'micinterart_product_status_script');
