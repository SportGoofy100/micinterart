<?php
/**
 * Template für Workshop-Übersicht mit WooCommerce-Produkten
 * 
 * @package Micinterart
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!class_exists('WooCommerce')) {
    // Fallback: Alte Workshop-Übersicht anzeigen
    include(get_template_directory() . '/archive-workshop.php');
    return;
}

get_header();
$is_en = (function_exists('pll_current_language') && pll_current_language() === 'en');

// Holen aller Workshop-Produkte
$heute = date('Y-m-d');

// Workshop-Kategorie Term holen
$workshops_term = get_term_by('slug', 'workshops', 'product_cat');
$atelierkurse_term = get_term_by('slug', 'atelierkurse', 'product_cat');
$kinderworkshops_term = get_term_by('slug', 'kinderworkshops', 'product_cat');

// Sammle alle Workshops nach Kategorie
$atelierkurse_products = [];
$kinderworkshops_products = [];
$archiv_products = [];

if ($workshops_term) {
    // Alle Workshops holen
    $workshops_query = new WP_Query([
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'tax_query' => [
            [
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $workshops_term->term_id,
                'include_children' => true,
            ],
        ],
        'meta_query' => [
            'relation' => 'OR',
            [
                'key' => '_workshop_datum',
                'value' => $heute,
                'compare' => '>=',
                'type' => 'DATE',
            ],
            [
                'key' => '_workshop_datum',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => '_workshop_datum',
                'value' => '',
                'compare' => '=',
            ],
        ],
        'orderby' => 'meta_value',
        'meta_key' => '_workshop_datum',
        'order' => 'ASC',
    ]);

    if ($workshops_query->have_posts()) {
        while ($workshops_query->have_posts()) {
            $workshops_query->the_post();
            $product_id = get_the_ID();
            $product = wc_get_product($product_id);

            // Prüfen ob Produkt ein Workshop ist
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

            if (!$is_workshop) continue;

            $datum = get_post_meta($product_id, '_workshop_datum', true);
            $status = get_post_meta($product_id, '_workshop_status', true) ?: 'geplant';
            $stock = $product ? $product->get_stock_quantity() : 0;
            $is_past = !empty($datum) && $datum < $heute;

            // Kategorie bestimmen
            $is_kinder = false;
            $is_erwachsene = false;

            if ($kinderworkshops_term && has_term($kinderworkshops_term->term_id, 'product_cat', $product_id)) {
                $is_kinder = true;
            } elseif ($atelierkurse_term && has_term($atelierkurse_term->term_id, 'product_cat', $product_id)) {
                $is_erwachsene = true;
            } else {
                $is_erwachsene = true; // Default
            }

            $workshop_data = [
                'post' => get_post($product_id),
                'product' => $product,
                'status' => $status,
                'datum' => $datum,
                'stock' => $stock,
                'is_kind' => $is_kinder,
                'is_past' => $is_past,
                'nach_absprache' => empty($datum),
            ];

            if ($is_kinder) {
                $kinderworkshops_products[] = $workshop_data;
            } elseif ($is_erwachsene) {
                $atelierkurse_products[] = $workshop_data;
            }
        }
        wp_reset_postdata();
    }

    // Vergangene Workshops für Archiv
    $past_query = new WP_Query([
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'tax_query' => [
            [
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $workshops_term->term_id,
                'include_children' => true,
            ],
        ],
        'meta_query' => [
            [
                'key' => '_workshop_datum',
                'value' => $heute,
                'compare' => '<',
                'type' => 'DATE',
            ],
        ],
        'orderby' => 'meta_value',
        'meta_key' => '_workshop_datum',
        'order' => 'DESC',
    ]);

    if ($past_query->have_posts()) {
        while ($past_query->have_posts()) {
            $past_query->the_post();
            $product_id = get_the_ID();
            $datum = get_post_meta($product_id, '_workshop_datum', true);
            $status = get_post_meta($product_id, '_workshop_status', true) ?: 'beendet';

            $archiv_products[] = [
                'post' => get_post($product_id),
                'status' => $status,
                'datum' => $datum,
                'is_past' => true,
            ];
        }
        wp_reset_postdata();
    }
}

// Sortierung
$workshop_sorter = function($a, $b) {
    if (empty($a['datum']) && empty($b['datum'])) return 0;
    if (empty($a['datum'])) return 1;
    if (empty($b['datum'])) return -1;
    return strcmp($a['datum'], $b['datum']);
};

usort($atelierkurse_products, $workshop_sorter);
usort($kinderworkshops_products, $workshop_sorter);

// Terminübersicht: Alle kommenden Workshops mit Datum
$alle_termine_rows = [];

foreach (array_merge($atelierkurse_products, $kinderworkshops_products) as $w) {
    if (!empty($w['datum']) && $w['datum'] >= $heute) {
        $product = $w['product'];
        $preis = $product ? $product->get_price() : '';
        
        $alle_termine_rows[] = [
            'datum' => $w['datum'],
            'workshop' => $w['post']->ID,
            'preis' => $preis,
            'is_kind' => $w['is_kind'],
        ];
    }
}

usort($alle_termine_rows, function($a, $b) {
    return strcmp($a['datum'], $b['datum']);
});

$alle_termine_rows = array_slice($alle_termine_rows, 0, 6);

// Helfer-Funktion: Status-Badge
function micinterart_wc_status_badge($status) {
    $labels = [
        'geplant' => 'Geplant',
        'anmeldung_offen' => 'Anmeldung offen',
        'fast_ausgebucht' => 'Fast ausgebucht',
        'ausgebucht' => 'Ausgebucht',
        'beendet' => 'Beendet',
        'abgesagt' => 'Abgesagt',
    ];
    $classes = [
        'geplant' => 'status-geplant',
        'anmeldung_offen' => 'status-anmeldung_offen',
        'fast_ausgebucht' => 'status-fast_ausgebucht',
        'ausgebucht' => 'status-ausgebucht',
        'beendet' => 'status-beendet',
        'abgesagt' => 'status-abgesagt',
    ];
    
    $label = isset($labels[$status]) ? $labels[$status] : $status;
    $class = isset($classes[$status]) ? $classes[$status] : 'status-geplant';
    
    return '<span class="workshop-status-badge ' . esc_attr($class) . '">' . esc_html($label) . '</span>';
}

// Helfer-Funktion: Preis formatieren
function micinterart_wc_format_price($preis, $is_en = false) {
    if (empty($preis)) return '';
    
    $preis_clean = (float)$preis;
    if ($is_en) {
        return '€ ' . number_format($preis_clean, 2, '.', ',');
    }
    return number_format($preis_clean, 2, ',', '.') . ' €';
}

// Helfer-Funktion: Preissuffix
function micinterart_wc_preis_suffix($product_id, $is_en = false) {
    $preis_info = get_post_meta($product_id, '_workshop_preis_info', true);
    if (!empty($preis_info)) {
        return $preis_info;
    }
    
    $is_paar = get_post_meta($product_id, '_workshop_is_paar_preis', true);
    $is_kinder = false;
    
    $terms = get_the_terms($product_id, 'product_cat');
    if ($terms && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            if ($term->slug === 'kinderworkshops') {
                $is_kinder = true;
                break;
            }
        }
    }
    
    if ($is_paar === 'yes') {
        return $is_en ? 'per couple' : 'pro Paar';
    } elseif ($is_kinder) {
        return $is_en ? 'per child' : 'pro Kind';
    } else {
        return $is_en ? 'per person' : 'pro Person';
    }
}

?>

<style>
/* Workshop-Übersicht Styling */
.workshops-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 60px 20px;
}

.workshops-page-title {
    text-align: center;
    font-size: 3em;
    margin-bottom: 40px;
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    letter-spacing: 2px;
}

.workshops-intro {
    max-width: 900px;
    margin: 0 auto 50px;
    text-align: center;
}

.workshops-hero-image {
    aspect-ratio: 14 / 4;
    margin-bottom: 30px;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
}

.workshops-hero-image img {
    width: 100%;
    height: auto;
    display: block;
    max-height: 400px;
    object-fit: cover;
}

.workshops-intro p {
    font-size: 1.15em;
    line-height: 1.8;
    color: #555;
    margin: 15px 0;
}

.workshops-intro p:first-of-type {
    font-weight: 600;
    color: #2c2c2c;
}

/* Sektions-Trennung */
.workshop-section {
    margin-bottom: 60px;
}
.workshop-section.archiv { margin-bottom: 0 !important; }

.section-header {
    text-align: center;
    margin-bottom: 40px;
    padding-bottom: 20px;
    border-bottom: 3px solid #d4a574;
}

.section-title {
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    font-size: 2.5em;
    margin: 0 0 10px 0;
    letter-spacing: 2px;
    color: #2c2c2c;
}

.section-subtitle {
    font-size: 1.1em;
    color: #666;
    font-style: italic;
}

/* Kinderworkshops spezielle Farbe */
.workshop-section.kinder .section-header {
    border-bottom-color: #ff6b9d;
}

.workshop-section.kinder .section-title {
    color: #ff6b9d;
}

/* Archiv-Sektion */
.workshop-section.archiv {
    background: #f9f9f9;
    padding: 20px 20px 25px;
    border-radius: 12px;
    margin-top: 30px;
    margin-bottom: 0;
}

.workshop-section.archiv .section-header {
    border-bottom-color: #999;
}

.workshop-section.archiv .section-title {
    color: #666;
}

.archiv-toggle {
    text-align: center;
    margin-bottom: 30px;
}

.archiv-toggle-button {
    padding: 10px 22px;
    background: transparent;
    color: #666;
    border: 1.5px solid #bbb;
    border-radius: 8px;
    font-size: 0.95em;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.archiv-toggle-button:hover {
    background: #f0f0f0;
    border-color: #888;
    color: #333;
    transform: translateY(-1px);
}

.archiv-toggle-button .arrow {
    transition: transform 0.3s ease;
}

.archiv-toggle-button.active .arrow {
    transform: rotate(180deg);
}

.archiv-content {
    display: none;
}

.archiv-content.show {
    display: block;
}

.workshops-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 30px;
}

.workshop-card {
    background: #fff;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    position: relative;
}

.workshop-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

/* Vergangene Workshops ausgegraut */
.workshop-card.past {
    opacity: 0.8;
}

.workshop-card.past .workshop-thumbnail {
    filter: grayscale(50%);
}

.workshop-status-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 600;
    z-index: 2;
}

.status-geplant { background: #e3f2fd; color: #1976d2; }
.status-anmeldung_offen { background: #e8f5e9; color: #388e3c; }
.status-fast_ausgebucht { background: #fff3e0; color: #f57c00; }
.status-ausgebucht { background: #ffebee; color: #d32f2f; }
.status-beendet { background: #f5f5f5; color: #666; }
.status-abgesagt { background: #fce4ec; color: #c2185b; }

.workshop-thumbnail {
    width: 100%;
    height: 250px;
    object-fit: cover;
    background: #f5f5f5;
}

.workshop-content {
    padding: 25px;
}

.workshop-title {
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    font-size: 1.8em;
    margin: 0 0 15px 0;
    letter-spacing: 1px;
}

.workshop-title a {
    color: #2c2c2c;
    text-decoration: none;
}

.workshop-title a:hover {
    color: #666;
}

.workshop-meta {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 15px;
    font-size: 0.95em;
}

.workshop-meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #666;
}

.workshop-meta-item strong {
    color: #2c2c2c;
    min-width: 80px;
}

.workshop-excerpt {
    color: #666;
    line-height: 1.6;
    margin-bottom: 20px;
}

.workshop-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
}

.workshop-preis {
    font-size: 1.3em;
    font-weight: 600;
    color: #2c2c2c;
}

.workshop-button {
    padding: 10px 20px;
    background: #2c2c2c;
    color: #fff;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 500;
    transition: background 0.2s ease;
    display: inline-block;
}

.workshop-button:hover {
    background: #000;
}

.no-workshops {
    text-align: center;
    padding: 60px 20px;
    color: #666;
    background: #f9f9f9;
    border-radius: 12px;
    margin: 40px 0;
}

/* ========================================
   HERO CARD - Prominenter naechster Workshop
   ======================================== */
.workshop-hero-card {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 12px 40px rgba(0,0,0,0.12);
    margin-bottom: 40px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    position: relative;
}
.workshop-hero-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 50px rgba(0,0,0,0.18);
}
.hero-badge-next {
    position: absolute;
    top: 20px;
    left: 20px;
    background: linear-gradient(135deg, #d4a574, #c4915e);
    color: #fff;
    padding: 8px 18px;
    border-radius: 25px;
    font-size: 0.85em;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    z-index: 3;
    box-shadow: 0 4px 12px rgba(212,165,116,0.4);
}
.workshop-section.kinder .hero-badge-next {
    background: linear-gradient(135deg, #ff6b9d, #e8547a);
    box-shadow: 0 4px 12px rgba(255,107,157,0.4);
}
.hero-image-wrapper {
    position: relative;
    overflow: hidden;
    min-height: 400px;
}
.hero-image-wrapper img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.hero-image-placeholder {
    width: 100%;
    height: 100%;
    min-height: 400px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #f5f0eb, #e8ddd3);
    font-size: 80px;
}
.hero-status-badge {
    position: absolute;
    top: 20px;
    right: 20px;
    padding: 8px 16px;
    border-radius: 25px;
    font-size: 0.9em;
    font-weight: 600;
    z-index: 2;
}
.hero-content {
    padding: 45px 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.hero-title {
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    font-size: 2.4em;
    margin: 0 0 20px 0;
    letter-spacing: 1.5px;
    line-height: 1.15;
}
.hero-title a {
    color: #2c2c2c;
    text-decoration: none;
    transition: color 0.2s ease;
}
.hero-title a:hover { color: #d4a574; }
.workshop-section.kinder .hero-title a:hover { color: #ff6b9d; }
.hero-meta {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 25px;
    font-size: 1.05em;
}
.hero-meta-item {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #555;
}
.hero-meta-item strong {
    color: #2c2c2c;
    min-width: 90px;
}
.hero-excerpt {
    color: #555;
    line-height: 1.8;
    margin-bottom: 30px;
    font-size: 1.05em;
}
.hero-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 25px;
    border-top: 2px solid #f0ebe5;
}
.hero-preis {
    font-size: 1.6em;
    font-weight: 700;
    color: #2c2c2c;
}
.hero-preis .preis-suffix {
    display: block;
    font-size: 0.5em;
    font-weight: 400;
    color: #888;
    margin-top: 3px;
}
.hero-button {
    padding: 14px 32px;
    background: linear-gradient(135deg, #2c2c2c, #444);
    color: #fff;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1.05em;
    transition: all 0.3s ease;
    display: inline-block;
}
.hero-button:hover {
    background: linear-gradient(135deg, #000, #2c2c2c);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.2);
    color: #fff;
}

/* Weitere Workshops Toggle */
.weitere-toggle {
    text-align: center;
    margin-bottom: 30px;
}
.weitere-toggle-button {
    padding: 10px 22px;
    background: transparent;
    color: #2c2c2c;
    border: 1.5px solid #2c2c2c;
    border-radius: 8px;
    font-size: 0.95em;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}
.weitere-toggle-button:hover {
    background: #2c2c2c;
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(0,0,0,0.12);
}
.workshop-section.kinder .weitere-toggle-button {
    background: transparent;
    color: #ff6b9d;
    border-color: #ff6b9d;
}
.workshop-section.kinder .weitere-toggle-button:hover {
    background: #ff6b9d;
    color: #fff;
}
.weitere-toggle-button .arrow {
    transition: transform 0.3s ease;
    font-size: 0.85em;
}
.weitere-toggle-button.active .arrow {
    transform: rotate(180deg);
}
.weitere-content {
    display: none;
    margin-top: 30px;
}
.weitere-content.show {
    display: block;
    animation: fadeInDown 0.4s ease;
}
@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-15px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ========================================
   TERMINUEBERSICHT (Quick Overview)
   ======================================== */
.termin-uebersicht {
    margin: 0 auto 60px;
    max-width: 1100px;
    background: #fff;
    border: 1px solid #e8e2d8;
    border-radius: 12px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.06);
    overflow: hidden;
}
.termin-uebersicht-header {
    background: linear-gradient(135deg, #f5f0eb, #ece2d3);
    padding: 18px 25px;
    border-bottom: 1px solid #e8e2d8;
}
.termin-uebersicht-header h2 {
    margin: 0;
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    font-size: 1.6em;
    letter-spacing: 1.5px;
    color: #2c2c2c;
}
.termin-uebersicht-header p {
    margin: 4px 0 0 0;
    font-size: 0.9em;
    color: #777;
    font-style: italic;
}
.termin-uebersicht-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.98em;
}
.termin-uebersicht-table th {
    text-align: left;
    padding: 12px 18px;
    background: #faf7f2;
    font-weight: 600;
    color: #555;
    font-size: 0.85em;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e8e2d8;
}
.termin-uebersicht-table td {
    padding: 14px 18px;
    border-bottom: 1px solid #f0ebe2;
    color: #333;
    vertical-align: middle;
}
.termin-uebersicht-table tr:last-child td { border-bottom: none; }
.termin-uebersicht-table tr:hover td { background: #fbf9f5; }
.termin-uebersicht-table .tu-datum { font-weight: 600; white-space: nowrap; color: #2c2c2c; }
.termin-uebersicht-table .tu-titel a { color: #2c2c2c; text-decoration: none; font-weight: 500; }
.termin-uebersicht-table .tu-titel a:hover { color: #d4a574; text-decoration: underline; }
.termin-uebersicht-table .tu-tag {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.78em;
    font-weight: 600;
    white-space: nowrap;
}
.termin-uebersicht-table .tu-tag-erw { background: #f5ede0; color: #8a6a3d; }
.termin-uebersicht-table .tu-tag-kind { background: #ffe6ef; color: #c2185b; }
.termin-uebersicht-table .tu-preis { font-weight: 600; color: #2c2c2c; white-space: nowrap; }
.termin-uebersicht-table .tu-cta {
    display: inline-block;
    padding: 6px 14px;
    background: #2c2c2c;
    color: #fff;
    text-decoration: none;
    border-radius: 6px;
    font-size: 0.85em;
    font-weight: 500;
    transition: background 0.2s;
    white-space: nowrap;
}
.termin-uebersicht-table .tu-cta:hover { background: #000; color: #fff; }

@media (max-width: 768px) {
    .workshop-hero-card { grid-template-columns: 1fr; }
    .hero-image-wrapper { min-height: 280px; max-height: 350px; }
    .hero-content { padding: 30px 25px; }
    .hero-title { font-size: 1.8em; }
    .workshops-grid { grid-template-columns: 1fr; }
    .workshops-hero-image img { max-height: 250px; }
    .hero-footer {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
        text-align: center;
    }
    .hero-button { text-align: center; }
    
    .termin-uebersicht-table thead { display: none; }
    .termin-uebersicht-table, .termin-uebersicht-table tbody, .termin-uebersicht-table tr, .termin-uebersicht-table td { display: block; width: 100%; }
    .termin-uebersicht-table tr { padding: 14px 18px; border-bottom: 1px solid #f0ebe2; }
    .termin-uebersicht-table td { padding: 4px 0; border: none; }
    .termin-uebersicht-table td:last-child { border-bottom: 0; }
    .termin-uebersicht-table td[data-label]::before {
        content: attr(data-label) ": ";
        display: inline-block;
        font-weight: 700;
        color: #444;
        margin-right: 6px;
    }
    .termin-uebersicht-table .tu-cta { margin-top: 8px; }
}

/* Responsive */
@media (max-width: 900px) {
    .workshop-section { margin-bottom: 40px; }
}

@media (max-width: 768px) {
    .workshops-container { padding: 35px 16px; }
    .workshops-page-title { font-size: 2.2em; margin-bottom: 24px; }
    .workshops-intro { margin: 0 auto 30px; }
    .workshops-intro p { font-size: 1.05em; }
    .section-header { margin-bottom: 26px; padding-bottom: 14px; }
    .section-title { font-size: 2.0em; }
    .workshops-grid { grid-template-columns: 1fr; gap: 18px; }
    .toggle-button, .archiv-toggle-button { width: 100%; justify-content: center; }
}

@media (max-width: 420px) {
    .workshops-page-title { font-size: 2.0em; }
    .section-title { font-size: 1.85em; }
}
</style>

<main id="primary" class="site-main">
    <div class="workshops-container">

        <h1 class="workshops-page-title"><?php echo $is_en ? 'Workshops for Everyone' : 'Workshops für jeden'; ?></h1>

        <div class="workshops-intro">
            <div class="workshops-hero-image">
                <img src="https://micinterart.de/wp-content/uploads/2026/06/5341273144251062761_121.jpg" alt="micinterart Atelier" loading="eager" decoding="async" fetchpriority="high" width="1400" height="400">
            </div>
            <h2><?php echo $is_en ? 'I invite you to my studio.' : 'Ich lade dich ein in mein Atelier.'; ?></h2>
            <p><?php 
                if ($is_en) {
                    echo 'This is the place where you can switch off your mind and just create. Whether you are treating yourself to a timeout, laughing with friends, spending a special evening as a couple, or giving your children an unforgettable day – I will guide you and ensure you feel comfortable from the very first moment. The materials, the cocktails, the wine, the snacks – I will take care of everything. You only need to bring yourself.';
                } else {
                    echo 'Hier ist der Ort, an dem du den Kopf ausschalten und einfach mal machen darfst. Ob du dir eine Auszeit gönnst, mit Freundinnen lachst, als Paar einen besonderen Abend verbringst oder deinen Kindern einen unvergesslichen Tag schenkst – ich begleite euch und sorge dafür, dass ihr euch vom ersten Moment an wohlfühlt. Die Materialien, die Cocktails, der Wein, die Snacks – darum kümmere ich mich. Ihr bringt nur euch mit.';
                }
            ?></p>
            <p><strong><?php echo $is_en ? 'Come on by, I look forward to seeing you!' : 'Komm vorbei, ich freue mich auf dich!'; ?></strong></p>
        </div>

        <?php if (!empty($alle_termine_rows)) : ?>

        <div class="termin-uebersicht">
            <div class="termin-uebersicht-header">
                <h2>📅 <?php echo $is_en ? 'All Dates at a Glance' : 'Alle Termine auf einen Blick'; ?></h2>
                <p><?php echo $is_en ? 'Quick overview of all current workshops' : 'Schneller Überblick über alle aktuellen Workshops'; ?></p>
            </div>
            <table class="termin-uebersicht-table">
                <thead>
                    <tr>
                        <th><?php echo $is_en ? 'Date' : 'Datum'; ?></th>
                        <th><?php echo $is_en ? 'Title' : 'Titel'; ?></th>
                        <th><?php echo $is_en ? 'For Whom' : 'Für wen'; ?></th>
                        <th><?php echo $is_en ? 'Price' : 'Preis'; ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($alle_termine_rows as $row):
                    $product_id = (int) $row['workshop'];
                    $product = wc_get_product($product_id);
                    $datum_formatted = date_i18n('D, d. F Y', strtotime($row['datum']));
                    $preis = micinterart_wc_format_price($row['preis'], $is_en);
                    $suffix = micinterart_wc_preis_suffix($product_id, $is_en);
                    $link = get_permalink($product_id);
                    $tag = $row['is_kind'] ? 'Kinder' : 'Erwachsene';
                    $tag_en = $row['is_kind'] ? 'Kids' : 'Adults';
                    $tag_class = $row['is_kind'] ? 'tu-tag-kind' : 'tu-tag-erw';
                    
                    // Stock Status
                    $stock = $product ? $product->get_stock_quantity() : 0;
                    $stock_status = '';
                    if ($stock <= 0) {
                        $stock_status = '<span class="tu-status ausgebucht">' . ($is_en ? 'Fully booked' : 'Ausgebucht') . '</span>';
                    } elseif ($stock <= 3) {
                        $stock_status = '<span class="tu-status fast-ausgebucht">' . ($is_en ? 'Hurry, only ' . $stock . ' left!' : 'Nur noch ' . $stock . ' Plätze!') . '</span>';
                    }
                ?>
                    <tr>
                        <td class="tu-datum" data-label="<?php echo $is_en ? 'Date' : 'Datum'; ?>"><?php echo esc_html($datum_formatted); ?></td>
                        <td class="tu-titel" data-label="<?php echo $is_en ? 'Title' : 'Titel'; ?>"><a href="<?php echo esc_url($link); ?>"><?php echo get_the_title($product_id); ?></a></td>
                        <td class="tu-tag" data-label="<?php echo $is_en ? 'For Whom' : 'Für wen'; ?>"><span class="<?php echo esc_attr($tag_class); ?>"><?php echo $is_en ? $tag_en : $tag; ?></span></td>
                        <td class="tu-preis" data-label="<?php echo $is_en ? 'Price' : 'Preis'; ?>"><?php echo $preis; ?> <?php echo $preis && $suffix ? '<small>' . esc_html($suffix) . '</small>' : ''; ?></td>
                        <td class="tu-cta" data-label="<?php echo $is_en ? 'Action' : 'Aktion'; ?>"><a href="<?php echo esc_url($link); ?>"><?php echo $is_en ? 'Book now' : 'Jetzt buchen'; ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php
        // ========================================
        // ERWACHSENENWORKSHOPS
        // ========================================
        if (!empty($atelierkurse_products)):
            $first_erwachsene = reset($atelierkurse_products);
            $more_erwachsene = count($atelierkurse_products) > 1;
        ?>
        
        <div class="workshop-section erwachsenen">
            <div class="section-header">
                <h2 class="section-title">🎨 <?php echo $is_en ? 'Studio Courses' : 'Atelierkurse'; ?></h2>
                <p class="section-subtitle"><?php echo $is_en ? 'Creative workshops for adults' : 'Kreative Workshops für Erwachsene'; ?></p>
            </div>

            <?php if (!empty($first_erwachsene)):
                $first_product = $first_erwachsene['product'];
                $first_post = $first_erwachsene['post'];
                $first_datum = $first_erwachsene['datum'];
                $first_preis = $first_product ? $first_product->get_price() : '';
                $first_ort = get_post_meta($first_post->ID, '_workshop_ort', true);
                $first_uhrzeit_von = get_post_meta($first_post->ID, '_workshop_uhrzeit_von', true);
                $first_uhrzeit_bis = get_post_meta($first_post->ID, '_workshop_uhrzeit_bis', true);
                $first_stock = $first_erwachsene['stock'];
                
                $has_image = has_post_thumbnail($first_post->ID);
                $image_url = $has_image ? get_the_post_thumbnail_url($first_post->ID, 'large') : 'https://micinterart.de/wp-content/uploads/2026/06/5341273144251062761_121.jpg';
                $datum_obj = $first_datum ? date_create($first_datum) : null;
                $datum_formatted = $datum_obj ? date_i18n('l, d. F Y', $datum_obj->getTimestamp()) : '';
                $preis_formatted = micinterart_wc_format_price($first_preis, $is_en);
                $suffix = micinterart_wc_preis_suffix($first_post->ID, $is_en);
                $stock_status = '';
                if ($first_stock <= 0) {
                    $stock_status = micinterart_wc_status_badge('ausgebucht');
                } elseif ($first_stock <= 3) {
                    $stock_status = '<span style="color:#f57c00;font-weight:600;">' . ($is_en ? 'Hurry, only ' . $first_stock . ' left!' : 'Nur noch ' . $first_stock . ' Plätze!') . '</span>';
                }
            ?>
            
            <div class="workshop-hero-card">
                <div class="hero-image-wrapper">
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($first_post->post_title); ?>" loading="lazy">
                    <?php echo micinterart_wc_status_badge($first_erwachsene['status']); ?>
                </div>
                <div class="hero-content">
                    <div class="hero-badge-next">Nächster Termin</div>
                    <h3 class="hero-title"><a href="<?php echo esc_url(get_permalink($first_post->ID)); ?>"><?php echo esc_html($first_post->post_title); ?></a></h3>
                    <div class="hero-meta">
                        <div class="hero-meta-item">
                            <strong>📅 Datum:</strong>
                            <span><?php echo esc_html($datum_formatted); ?></span>
                        </div>
                        <?php if ($first_uhrzeit_von || $first_uhrzeit_bis): ?>
                        <div class="hero-meta-item">
                            <strong>⏰ Uhrzeit:</strong>
                            <span><?php echo esc_html($first_uhrzeit_von); ?> <?php echo $first_uhrzeit_von && $first_uhrzeit_bis ? '–' : ''; ?> <?php echo esc_html($first_uhrzeit_bis); ?> Uhr</span>
                        </div>
                        <?php endif; ?>
                        <?php if ($first_ort): ?>
                        <div class="hero-meta-item">
                            <strong>📍 Ort:</strong>
                            <span><?php echo esc_html($first_ort); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="hero-excerpt"><?php echo wp_trim_words($first_post->post_excerpt ?: $first_post->post_content, 20); ?></div>
                    <div class="hero-footer">
                        <div class="hero-preis">
                            <?php echo $preis_formatted; ?>
                            <?php if ($preis_formatted && $suffix): ?>
                                <span class="preis-suffix"><?php echo esc_html($suffix); ?></span>
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo esc_url(get_permalink($first_post->ID)); ?>" class="hero-button">📅 <?php echo $is_en ? 'Book now' : 'Jetzt buchen'; ?></a>
                    </div>
                </div>
            </div>
            
            <?php if ($more_erwachsene): ?>
            <div class="weitere-toggle">
                <button type="button" class="weitere-toggle-button" onclick="toggleWeitere('erwachsenen')">
                    <span><?php echo $is_en ? 'Show all studio courses' : 'Weitere Atelierkurse anzeigen'; ?></span>
                    <span class="arrow">▼</span>
                </button>
            </div>
            <div class="weitere-content" id="weitere-erwachsenen">
                <div class="workshops-grid">
                    <?php foreach (array_slice($atelierkurse_products, 1) as $w):
                        $product = $w['product'];
                        $post = $w['post'];
                        $datum = $w['datum'];
                        $preis = $product ? $product->get_price() : '';
                        $preis_formatted = micinterart_wc_format_price($preis, $is_en);
                        $suffix = micinterart_wc_preis_suffix($post->ID, $is_en);
                        $ort = get_post_meta($post->ID, '_workshop_ort', true);
                        $uhrzeit_von = get_post_meta($post->ID, '_workshop_uhrzeit_von', true);
                        $uhrzeit_bis = get_post_meta($post->ID, '_workshop_uhrzeit_bis', true);
                        $stock = $w['stock'];
                        
                        $datum_obj = $datum ? date_create($datum) : null;
                        $datum_formatted = $datum_obj ? date_i18n('d.m.Y', $datum_obj->getTimestamp()) : '';
                        $has_image = has_post_thumbnail($post->ID);
                        $image_url = $has_image ? get_the_post_thumbnail_url($post->ID, 'large') : 'https://micinterart.de/wp-content/uploads/2026/06/5341273144251062761_121.jpg';
                        
                        $stock_status = '';
                        if ($stock <= 0) {
                            $stock_status = micinterart_wc_status_badge('ausgebucht');
                        } elseif ($stock <= 3) {
                            $stock_status = '<span style="background:#fff3e0;color:#f57c00;padding:3px 10px;border-radius:12px;font-size:0.85em;font-weight:700;">' . ($is_en ? 'Hurry, only ' . $stock . ' left!' : 'Nur noch ' . $stock . ' Plätze!') . '</span>';
                        }
                    ?>
                    <div class="workshop-card <?php echo $w['is_past'] ? 'past' : ''; ?>">
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($post->post_title); ?>" class="workshop-thumbnail" loading="lazy">
                        <div class="workshop-content">
                            <h3 class="workshop-title"><a href="<?php echo esc_url(get_permalink($post->ID)); ?>"><?php echo esc_html($post->post_title); ?></a></h3>
                            <div class="workshop-meta">
                                <?php if ($datum_formatted): ?>
                                <div class="workshop-meta-item">
                                    <strong>📅 Datum:</strong>
                                    <span><?php echo esc_html($datum_formatted); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($uhrzeit_von || $uhrzeit_bis): ?>
                                <div class="workshop-meta-item">
                                    <strong>⏰ Uhrzeit:</strong>
                                    <span><?php echo esc_html($uhrzeit_von); ?> <?php echo $uhrzeit_von && $uhrzeit_bis ? '–' : ''; ?> <?php echo esc_html($uhrzeit_bis); ?> Uhr</span>
                                </div>
                                <?php endif; ?>
                                <?php if ($ort): ?>
                                <div class="workshop-meta-item">
                                    <strong>📍 Ort:</strong>
                                    <span><?php echo esc_html($ort); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="workshop-excerpt"><?php echo wp_trim_words($post->post_excerpt ?: $post->post_content, 10); ?></div>
                            <div class="workshop-footer">
                                <span class="workshop-preis">
                                    <?php echo $preis_formatted; ?>
                                    <?php if ($preis_formatted && $suffix): ?>
                                        <small><?php echo esc_html($suffix); ?></small>
                                    <?php endif; ?>
                                </span>
                                <a href="<?php echo esc_url(get_permalink($post->ID)); ?>" class="workshop-button"><?php echo $is_en ? 'Details' : 'Mehr erfahren'; ?></a>
                            </div>
                            <?php echo $stock_status; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php
        // ========================================
        // KINDERWORKSHOPS
        // ========================================
        if (!empty($kinderworkshops_products)):
            $first_kinder = reset($kinderworkshops_products);
            $more_kinder = count($kinderworkshops_products) > 1;
        ?>
        
        <div class="workshop-section kinder">
            <div class="section-header">
                <h2 class="section-title">🎨 <?php echo $is_en ? 'Children\'s Workshops' : 'Kinderworkshops'; ?></h2>
                <p class="section-subtitle"><?php echo $is_en ? 'Fun and creativity for kids' : 'Spaß und Kreativität für Kinder'; ?></p>
            </div>

            <?php if (!empty($first_kinder)):
                $first_product = $first_kinder['product'];
                $first_post = $first_kinder['post'];
                $first_datum = $first_kinder['datum'];
                $first_preis = $first_product ? $first_product->get_price() : '';
                $first_ort = get_post_meta($first_post->ID, '_workshop_ort', true);
                $first_uhrzeit_von = get_post_meta($first_post->ID, '_workshop_uhrzeit_von', true);
                $first_uhrzeit_bis = get_post_meta($first_post->ID, '_workshop_uhrzeit_bis', true);
                $first_stock = $first_kinder['stock'];
                
                $has_image = has_post_thumbnail($first_post->ID);
                $image_url = $has_image ? get_the_post_thumbnail_url($first_post->ID, 'large') : 'https://micinterart.de/wp-content/uploads/2026/06/5341273144251062761_121.jpg';
                $datum_obj = $first_datum ? date_create($first_datum) : null;
                $datum_formatted = $datum_obj ? date_i18n('l, d. F Y', $datum_obj->getTimestamp()) : '';
                $preis_formatted = micinterart_wc_format_price($first_preis, $is_en);
                $suffix = micinterart_wc_preis_suffix($first_post->ID, $is_en);
                $stock_status = '';
                if ($first_stock <= 0) {
                    $stock_status = micinterart_wc_status_badge('ausgebucht');
                } elseif ($first_stock <= 3) {
                    $stock_status = '<span style="color:#f57c00;font-weight:600;">' . ($is_en ? 'Hurry, only ' . $first_stock . ' left!' : 'Nur noch ' . $first_stock . ' Plätze!') . '</span>';
                }
            ?>
            
            <div class="workshop-hero-card">
                <div class="hero-image-wrapper">
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($first_post->post_title); ?>" loading="lazy">
                    <?php echo micinterart_wc_status_badge($first_kinder['status']); ?>
                </div>
                <div class="hero-content">
                    <div class="hero-badge-next">Nächster Termin</div>
                    <h3 class="hero-title"><a href="<?php echo esc_url(get_permalink($first_post->ID)); ?>"><?php echo esc_html($first_post->post_title); ?></a></h3>
                    <div class="hero-meta">
                        <div class="hero-meta-item">
                            <strong>📅 Datum:</strong>
                            <span><?php echo esc_html($datum_formatted); ?></span>
                        </div>
                        <?php if ($first_uhrzeit_von || $first_uhrzeit_bis): ?>
                        <div class="hero-meta-item">
                            <strong>⏰ Uhrzeit:</strong>
                            <span><?php echo esc_html($first_uhrzeit_von); ?> <?php echo $first_uhrzeit_von && $first_uhrzeit_bis ? '–' : ''; ?> <?php echo esc_html($first_uhrzeit_bis); ?> Uhr</span>
                        </div>
                        <?php endif; ?>
                        <?php if ($first_ort): ?>
                        <div class="hero-meta-item">
                            <strong>📍 Ort:</strong>
                            <span><?php echo esc_html($first_ort); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="hero-excerpt"><?php echo wp_trim_words($first_post->post_excerpt ?: $first_post->post_content, 20); ?></div>
                    <div class="hero-footer">
                        <div class="hero-preis">
                            <?php echo $preis_formatted; ?>
                            <?php if ($preis_formatted && $suffix): ?>
                                <span class="preis-suffix"><?php echo esc_html($suffix); ?></span>
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo esc_url(get_permalink($first_post->ID)); ?>" class="hero-button">📅 <?php echo $is_en ? 'Book now' : 'Jetzt buchen'; ?></a>
                    </div>
                </div>
            </div>
            
            <?php if ($more_kinder): ?>
            <div class="weitere-toggle">
                <button type="button" class="weitere-toggle-button" onclick="toggleWeitere('kinder')">
                    <span><?php echo $is_en ? 'Show all children\'s workshops' : 'Weitere Kinderworkshops anzeigen'; ?></span>
                    <span class="arrow">▼</span>
                </button>
            </div>
            <div class="weitere-content" id="weitere-kinder">
                <div class="workshops-grid">
                    <?php foreach (array_slice($kinderworkshops_products, 1) as $w):
                        $product = $w['product'];
                        $post = $w['post'];
                        $datum = $w['datum'];
                        $preis = $product ? $product->get_price() : '';
                        $preis_formatted = micinterart_wc_format_price($preis, $is_en);
                        $suffix = micinterart_wc_preis_suffix($post->ID, $is_en);
                        $ort = get_post_meta($post->ID, '_workshop_ort', true);
                        $uhrzeit_von = get_post_meta($post->ID, '_workshop_uhrzeit_von', true);
                        $uhrzeit_bis = get_post_meta($post->ID, '_workshop_uhrzeit_bis', true);
                        $stock = $w['stock'];
                        
                        $datum_obj = $datum ? date_create($datum) : null;
                        $datum_formatted = $datum_obj ? date_i18n('d.m.Y', $datum_obj->getTimestamp()) : '';
                        $has_image = has_post_thumbnail($post->ID);
                        $image_url = $has_image ? get_the_post_thumbnail_url($post->ID, 'large') : 'https://micinterart.de/wp-content/uploads/2026/06/5341273144251062761_121.jpg';
                        
                        $stock_status = '';
                        if ($stock <= 0) {
                            $stock_status = micinterart_wc_status_badge('ausgebucht');
                        } elseif ($stock <= 3) {
                            $stock_status = '<span style="background:#fff3e0;color:#f57c00;padding:3px 10px;border-radius:12px;font-size:0.85em;font-weight:700;">' . ($is_en ? 'Hurry, only ' . $stock . ' left!' : 'Nur noch ' . $stock . ' Plätze!') . '</span>';
                        }
                    ?>
                    <div class="workshop-card <?php echo $w['is_past'] ? 'past' : ''; ?>">
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($post->post_title); ?>" class="workshop-thumbnail" loading="lazy">
                        <div class="workshop-content">
                            <h3 class="workshop-title"><a href="<?php echo esc_url(get_permalink($post->ID)); ?>"><?php echo esc_html($post->post_title); ?></a></h3>
                            <div class="workshop-meta">
                                <?php if ($datum_formatted): ?>
                                <div class="workshop-meta-item">
                                    <strong>📅 Datum:</strong>
                                    <span><?php echo esc_html($datum_formatted); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($uhrzeit_von || $uhrzeit_bis): ?>
                                <div class="workshop-meta-item">
                                    <strong>⏰ Uhrzeit:</strong>
                                    <span><?php echo esc_html($uhrzeit_von); ?> <?php echo $uhrzeit_von && $uhrzeit_bis ? '–' : ''; ?> <?php echo esc_html($uhrzeit_bis); ?> Uhr</span>
                                </div>
                                <?php endif; ?>
                                <?php if ($ort): ?>
                                <div class="workshop-meta-item">
                                    <strong>📍 Ort:</strong>
                                    <span><?php echo esc_html($ort); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="workshop-excerpt"><?php echo wp_trim_words($post->post_excerpt ?: $post->post_content, 10); ?></div>
                            <div class="workshop-footer">
                                <span class="workshop-preis">
                                    <?php echo $preis_formatted; ?>
                                    <?php if ($preis_formatted && $suffix): ?>
                                        <small><?php echo esc_html($suffix); ?></small>
                                    <?php endif; ?>
                                </span>
                                <a href="<?php echo esc_url(get_permalink($post->ID)); ?>" class="workshop-button"><?php echo $is_en ? 'Details' : 'Mehr erfahren'; ?></a>
                            </div>
                            <?php echo $stock_status; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php
        // ========================================
        // ARCHIV (vergangene Workshops)
        // ========================================
        if (!empty($archiv_products)):
        ?>
        <div class="workshop-section archiv">
            <div class="section-header">
                <h2 class="section-title">📜 <?php echo $is_en ? 'Past Workshops' : 'Archiv'; ?></h2>
            </div>
            
            <div class="archiv-toggle">
                <button type="button" class="archiv-toggle-button" onclick="toggleArchiv()">
                    <span><?php echo $is_en ? 'Show past workshops' : 'Vergangene Workshops anzeigen'; ?></span>
                    <span class="arrow">▼</span>
                </button>
            </div>
            
            <div class="archiv-content">
                <div class="workshops-grid">
                    <?php foreach ($archiv_products as $a):
                        $post = $a['post'];
                        $datum = $a['datum'];
                        $product = wc_get_product($post->ID);
                        $preis = $product ? $product->get_price() : '';
                        $preis_formatted = micinterart_wc_format_price($preis, $is_en);
                        $suffix = micinterart_wc_preis_suffix($post->ID, $is_en);
                        
                        $datum_obj = $datum ? date_create($datum) : null;
                        $datum_formatted = $datum_obj ? date_i18n('d.m.Y', $datum_obj->getTimestamp()) : '';
                        $has_image = has_post_thumbnail($post->ID);
                        $image_url = $has_image ? get_the_post_thumbnail_url($post->ID, 'large') : 'https://micinterart.de/wp-content/uploads/2026/06/5341273144251062761_121.jpg';
                    ?>
                    <div class="workshop-card past">
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($post->post_title); ?>" class="workshop-thumbnail" loading="lazy">
                        <div class="workshop-content">
                            <h3 class="workshop-title"><a href="<?php echo esc_url(get_permalink($post->ID)); ?>"><?php echo esc_html($post->post_title); ?></a></h3>
                            <div class="workshop-meta">
                                <?php if ($datum_formatted): ?>
                                <div class="workshop-meta-item">
                                    <strong>📅 Datum:</strong>
                                    <span><?php echo esc_html($datum_formatted); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="workshop-excerpt"><?php echo wp_trim_words($post->post_excerpt ?: $post->post_content, 10); ?></div>
                            <div class="workshop-footer">
                                <span class="workshop-preis">
                                    <?php echo $preis_formatted; ?>
                                    <?php if ($preis_formatted && $suffix): ?>
                                        <small><?php echo esc_html($suffix); ?></small>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</main>

<script>
function toggleWeitere(section) {
    var content = document.getElementById('weitere-' + section);
    var button = content.previousElementSibling.querySelector('button');
    
    if (content.classList.contains('show')) {
        content.classList.remove('show');
        button.classList.remove('active');
    } else {
        content.classList.add('show');
        button.classList.add('active');
    }
}

function toggleArchiv() {
    var content = document.querySelector('.archiv-content');
    var button = document.querySelector('.archiv-toggle-button');
    
    if (content.classList.contains('show')) {
        content.classList.remove('show');
        button.classList.remove('active');
    } else {
        content.classList.add('show');
        button.classList.add('active');
    }
}
</script>
