<?php
/**
 * Template für Workshop-Übersicht mit WooCommerce-Produkten
 * 
 * Zeigt Workshops als WC-Produkte mit dem gleichen Design wie archive-workshop.php
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
    exit;
}

get_header();
$is_en = (function_exists('pll_current_language') && pll_current_language() === 'en');
?>

<style>
/* ============================================================================
   WORKSHOP ARCHIVE STYLING (von archive-workshop.php)
   ============================================================================ */

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

/* Archiv Toggle */
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

/* Workshop Grid */
.workshops-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 30px;
}

/* Workshop Card */
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

/* Hero Card */
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

.hero-title a:hover {
    color: #666;
}

.hero-meta {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 25px;
}

.hero-meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #666;
    font-size: 0.95em;
}

.hero-meta-item strong {
    color: #2c2c2c;
    min-width: 100px;
}

.hero-preis {
    font-size: 1.5em;
    font-weight: 600;
    color: #2c2c2c;
    margin-bottom: 20px;
}

.hero-button {
    padding: 12px 24px;
    background: #2c2c2c;
    color: #fff;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 500;
    display: inline-block;
    transition: all 0.2s ease;
}

.hero-button:hover {
    background: #000;
    transform: translateY(-2px);
}

@media (max-width: 968px) {
    .workshop-hero-card {
        grid-template-columns: 1fr;
    }
    .hero-image-wrapper {
        min-height: 250px;
    }
}

@media (max-width: 768px) {
    .workshops-grid {
        grid-template-columns: 1fr;
    }
    .workshops-container {
        padding: 40px 15px;
    }
    .workshops-page-title {
        font-size: 2.2em;
    }
}
</style>

<?php
// ============================================================================
// WORKSHOP DATA LOADING
// ============================================================================

$heute = date('Y-m-d');

// Übergeordnete Workshop-Kategorie holen
$workshops_term = get_term_by('slug', 'workshops', 'product_cat');

// Sammle alle Workshops nach Kategorie
$kinder_upcoming = [];
$erwachsenen_upcoming = [];
$archiv_workshops = [];
$workshop_category_type = static function($product_id) {
    $categories = get_the_terms($product_id, 'product_cat');
    if (!$categories || is_wp_error($categories)) {
        return '';
    }

    foreach ($categories as $category) {
        if (in_array($category->slug, ['kinderworkshop', 'kinderworkshops'], true)) {
            return 'kinder';
        }
        if (in_array($category->slug, ['erwachsenenworkshop', 'erwachsenenworkshops', 'atelierkurse'], true)) {
            return 'erwachsene';
        }
    }

    return '';
};

$workshop_product_tax_query = [
    'relation' => 'OR',
    [
        'taxonomy' => 'product_type',
        'field' => 'slug',
        'terms' => 'workshop',
    ],
];

if ($workshops_term) {
    $workshop_product_tax_query[] = [
        'taxonomy' => 'product_cat',
        'field' => 'term_id',
        'terms' => $workshops_term->term_id,
        'include_children' => true,
    ];
}

if (!empty($workshop_product_tax_query)) {
    // Alle Workshops holen (upcoming)
    $args_upcoming = [
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'tax_query' => $workshop_product_tax_query,
        'meta_query' => [
            'relation' => 'OR',
            [ 'key' => '_workshop_datum', 'value' => $heute, 'compare' => '>=', 'type' => 'DATE' ],
            [ 'key' => '_workshop_datum', 'compare' => 'NOT EXISTS' ],
            [ 'key' => '_workshop_datum', 'value' => '', 'compare' => '=' ],
        ],
        'orderby' => 'meta_value',
        'meta_key' => '_workshop_datum',
        'order' => 'ASC'
    ];
    
    $upcoming_query = new WP_Query($args_upcoming);
    
    if ($upcoming_query->have_posts()) {
        while ($upcoming_query->have_posts()) {
            $upcoming_query->the_post();
            $product_id = get_the_ID();
            $product = wc_get_product($product_id);
            
            $datum = get_post_meta($product_id, '_workshop_datum', true);
            
            $category_type = $workshop_category_type($product_id);
            if ($category_type === '') {
                continue;
            }
            $is_kinder = $category_type === 'kinder';
            
            $status = get_post_meta($product_id, '_workshop_status', true) ?: 'geplant';
            $stock = $product ? $product->get_stock_quantity() : 0;
            
            // Wenn ausverkauft, Status anpassen
            if ($stock <= 0 && $status !== 'beendet' && $status !== 'abgesagt') {
                $status = 'ausgebucht';
            }
            
            $workshop_data = [
                'post' => get_post($product_id),
                'product' => $product,
                'datum' => $datum,
                'status' => $status,
                'stock' => $stock,
                'is_kind' => $is_kinder,
            ];
            
            if ($is_kinder) {
                $kinder_upcoming[] = $workshop_data;
            } else {
                $erwachsenen_upcoming[] = $workshop_data;
            }
        }
        wp_reset_postdata();
    }
    
    // Archiv-Workshops (vergangene)
    $args_past = [
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'tax_query' => $workshop_product_tax_query,
        'meta_query' => [
            [ 'key' => '_workshop_datum', 'value' => $heute, 'compare' => '<', 'type' => 'DATE' ],
        ],
        'orderby' => 'meta_value',
        'meta_key' => '_workshop_datum',
        'order' => 'DESC'
    ];
    
    $past_query = new WP_Query($args_past);
    
    if ($past_query->have_posts()) {
        while ($past_query->have_posts()) {
            $past_query->the_post();
            $product_id = get_the_ID();
            
            $datum = get_post_meta($product_id, '_workshop_datum', true);
            
            $category_type = $workshop_category_type($product_id);
            if ($category_type === '') {
                continue;
            }
            $is_kinder = $category_type === 'kinder';
            
            $archiv_workshops[] = [
                'post' => get_post($product_id),
                'datum' => $datum,
                'is_kind' => $is_kinder,
            ];
        }
        wp_reset_postdata();
    }
}

// Sortierung: Workshops mit Datum nach oben, dann "Nach Absprache"
$workshop_sorter = function($a, $b) {
    if (empty($a['datum']) && empty($b['datum'])) return 0;
    if (empty($a['datum'])) return 1;
    if (empty($b['datum'])) return -1;
    return strcmp($a['datum'], $b['datum']);
};

usort($kinder_upcoming, $workshop_sorter);
usort($erwachsenen_upcoming, $workshop_sorter);
usort($archiv_workshops, function($a, $b) {
    if (empty($a['datum']) && empty($b['datum'])) return 0;
    if (empty($a['datum'])) return 1;
    if (empty($b['datum'])) return -1;
    return strcmp($b['datum'], $a['datum']);
});

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function format_workshop_date($datum) {
    if (empty($datum)) {
        return 'Nach Absprache';
    }
    $date_obj = date_create($datum);
    if (!$date_obj) return $datum;
    return strftime('%A, %d. %B %Y', $date_obj->getTimestamp());
}

function format_workshop_time($post_id) {
    $von = get_post_meta($post_id, '_workshop_uhrzeit_von', true);
    $bis = get_post_meta($post_id, '_workshop_uhrzeit_bis', true);
    if (empty($von) && empty($bis)) return '';
    if (!empty($von) && !empty($bis)) return $von . ' – ' . $bis . ' Uhr';
    return ($von ?: $bis) . ' Uhr';
}

function get_status_class($status) {
    $map = [
        'geplant' => 'status-geplant',
        'anmeldung_offen' => 'status-anmeldung_offen',
        'fast_ausgebucht' => 'status-fast_ausgebucht',
        'ausgebucht' => 'status-ausgebucht',
        'beendet' => 'status-beendet',
        'abgesagt' => 'status-abgesagt',
    ];
    return $map[$status] ?? 'status-geplant';
}

function get_status_text($status, $stock = null) {
    if ($stock !== null && $stock <= 0 && $status !== 'beendet' && $status !== 'abgesagt') {
        return 'Ausgebucht';
    }
    $map = [
        'geplant' => 'Geplant',
        'anmeldung_offen' => 'Anmeldung offen',
        'fast_ausgebucht' => 'Fast ausgebucht',
        'ausgebucht' => 'Ausgebucht',
        'beendet' => 'Beendet',
        'abgesagt' => 'Abgesagt',
    ];
    return $map[$status] ?? 'Geplant';
}

// ============================================================================
// DISPLAY WORKSHOP CARD
// ============================================================================

function display_workshop_card($workshop, $is_archiv = false) {
    $post = $workshop['post'];
    $product = $workshop['product'] ?? wc_get_product($post->ID);
    $datum = $workshop['datum'] ?? '';
    $status = $workshop['status'] ?? 'geplant';
    $stock = $workshop['stock'] ?? ($product ? $product->get_stock_quantity() : 0);
    $is_kind = $workshop['is_kind'] ?? false;
    
    $status_class = get_status_class($status);
    $status_text = get_status_text($status, $stock);
    
    $thumbnail_url = '';
    if (has_post_thumbnail($post->ID)) {
        $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'medium_large');
    }
    
    $preis = $product ? $product->get_price() : 0;
    $uhrzeit = format_workshop_time($post->ID);
    $ort = get_post_meta($post->ID, '_workshop_ort', true);
    $alter = '';
    $alter_von = get_post_meta($post->ID, '_workshop_alter_von', true);
    $alter_bis = get_post_meta($post->ID, '_workshop_alter_bis', true);
    if (!empty($alter_von) || !empty($alter_bis)) {
        $alter = ($alter_von ? 'ab ' . $alter_von : '') . ($alter_von && $alter_bis ? ' – ' : '') . ($alter_bis ? $alter_bis . ' Jahre' : '');
    }
    
    $card_classes = ['workshop-card'];
    if ($is_archiv || (!empty($datum) && $datum < date('Y-m-d'))) {
        $card_classes[] = 'past';
    }
    
    echo '<div class="' . esc_attr(implode(' ', $card_classes)) . '">';
    
    // Thumbnail
    echo '<div class="workshop-thumbnail">';
    if (!empty($thumbnail_url)) {
        echo '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($post->post_title) . '" loading="lazy">';
    } else {
        echo '<div style="width:100%; height:100%; background:#f5f5f5; display:flex; align-items:center; justify-content:center; font-size:40px;">' . ($is_kind ? '👧' : '🎨') . '</div>';
    }
    echo '</div>';
    
    // Status Badge
    echo '<div class="workshop-status-badge ' . esc_attr($status_class) . '">' . esc_html($status_text) . '</div>';
    
    // Content
    echo '<div class="workshop-content">';
    echo '<h3 class="workshop-title"><a href="' . get_permalink($post->ID) . '">' . esc_html($post->post_title) . '</a></h3>';
    
    // Meta
    echo '<div class="workshop-meta">';
    echo '<div class="workshop-meta-item"><strong>Wann:</strong> ' . esc_html(format_workshop_date($datum)) . '</div>';
    if (!empty($uhrzeit)) {
        echo '<div class="workshop-meta-item"><strong>Uhrzeit:</strong> ' . esc_html($uhrzeit) . '</div>';
    }
    if (!empty($alter)) {
        echo '<div class="workshop-meta-item"><strong>Alter:</strong> ' . esc_html($alter) . '</div>';
    }
    if (!empty($ort)) {
        echo '<div class="workshop-meta-item"><strong>Wo:</strong> ' . esc_html($ort) . '</div>';
    }
    echo '</div>';
    
    // Footer mit Preis und Button
    echo '<div class="workshop-footer">';
    if ($preis > 0) {
        echo '<span class="workshop-preis">' . wc_price($preis) . '</span>';
    }
    echo '<a href="' . get_permalink($post->ID) . '" class="workshop-button">Mehr Infos</a>';
    echo '</div>';
    
    echo '</div>';
    echo '</div>';
}

// ============================================================================
// RENDER PAGE
// ============================================================================

// Titel und Einleitung
echo '<div class="workshops-container">';

if ($is_en) {
    echo '<h1 class="workshops-page-title">My Workshops</h1>';
    echo '<div class="workshops-intro">';
    echo '<p><strong>Discover your creativity!</strong></p>';
    echo '<p>From painting to sculpture to creative techniques for children - find the perfect workshop for you.</p>';
    echo '<p><strong>Come on by, I look forward to seeing you!</strong></p>';
    echo '</div>';
} else {
    echo '<h1 class="workshops-page-title">Meine Workshops</h1>';
    echo '<div class="workshops-intro">';
    echo '<p><strong>Entdecke deine kreative Seite!</strong></p>';
    echo '<p>Von Malerei über Skulptur bis hin zu kreativen Techniken für Kinder – hier findest du den perfekten Workshop für dich.</p>';
    echo '<p><strong>Komm vorbei, ich freue mich auf dich!</strong></p>';
    echo '</div>';
}

// ============================================================================
// HERO SECTION - Nächster Workshop
// ============================================================================

$next_workshop = null;
$hero_section = 'erwachsenen';

// Versuche zuerst Kinderworkshop
if (!empty($kinder_upcoming)) {
    foreach ($kinder_upcoming as $w) {
        if (!empty($w['datum']) && $w['datum'] >= $heute) {
            $next_workshop = $w;
            $hero_section = 'kinder';
            break;
        }
    }
}

// Falls kein Kinderworkshop, versuche Erwachsene
if (!$next_workshop && !empty($erwachsenen_upcoming)) {
    foreach ($erwachsenen_upcoming as $w) {
        if (!empty($w['datum']) && $w['datum'] >= $heute) {
            $next_workshop = $w;
            $hero_section = 'erwachsenen';
            break;
        }
    }
}

// Falls kein Workshop mit Datum, nimm den ersten
if (!$next_workshop) {
    if (!empty($kinder_upcoming)) {
        $next_workshop = $kinder_upcoming[0];
        $hero_section = 'kinder';
    } elseif (!empty($erwachsenen_upcoming)) {
        $next_workshop = $erwachsenen_upcoming[0];
        $hero_section = 'erwachsenen';
    }
}

if ($next_workshop) :
    $post = $next_workshop['post'];
    $product = $next_workshop['product'];
    $datum = $next_workshop['datum'];
    $status = $next_workshop['status'];
    $stock = $next_workshop['stock'];
    $is_kind = $next_workshop['is_kind'];
    
    $next_status_text = get_status_text($status, $stock);
    $next_status_class = get_status_class($status);
    
    if ($stock <= 0) {
        $next_status_class = 'status-ausgebucht';
        $next_status_text = 'Ausgebucht';
    }
    
    $thumbnail_url = '';
    if (has_post_thumbnail($post->ID)) {
        $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'large');
    }
    
    $preis = $product ? $product->get_price() : 0;
    $uhrzeit = format_workshop_time($post->ID);
    $ort = get_post_meta($post->ID, '_workshop_ort', true);
    
    $section_class = $is_kind ? 'kinder' : '';
    $placeholder_emoji = $is_kind ? '👧' : '🎨';
    
    // Sektions-Titel
    echo '<div class="workshop-section ' . esc_attr($section_class) . '" style="margin-bottom: 0;">';
    echo '<div class="section-header">';
    echo '<h2 class="section-title">' . ($is_kind ? ($is_en ? 'Children\'s Workshops' : 'Kinderworkshops') : ($is_en ? 'Art Courses for Adults' : 'Atelierkurse für Erwachsene')) . '</h2>';
    echo '</div>';
    
    // Hero Card
    echo '<div class="workshop-hero-card">';
    
    // Badge
    echo '<div class="hero-badge-next">' . ($is_en ? 'NEXT DATE' : 'NÄCHSTER TERMIN') . '</div>';
    
    // Bild-Seite
    echo '<div class="hero-image-wrapper">';
    if (!empty($thumbnail_url)) {
        echo '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($post->post_title) . '">';
    } else {
        echo '<div class="hero-image-placeholder">' . esc_html($placeholder_emoji) . '</div>';
    }
    
    // Status-Badge
    echo '<div class="hero-status-badge ' . esc_attr($next_status_class) . '">' . esc_html($next_status_text) . '</div>';
    echo '</div>';
    
    // Inhalt-Seite
    echo '<div class="hero-content">';
    echo '<h2 class="hero-title"><a href="' . get_permalink($post->ID) . '">' . esc_html($post->post_title) . '</a></h2>';
    
    echo '<div class="hero-meta">';
    echo '<div class="hero-meta-item"><strong>' . ($is_en ? 'When:' : 'Wann:') . '</strong> ' . esc_html(format_workshop_date($datum)) . '</div>';
    if (!empty($uhrzeit)) {
        echo '<div class="hero-meta-item"><strong>' . ($is_en ? 'Time:' : 'Uhrzeit:') . '</strong> ' . esc_html($uhrzeit) . '</div>';
    }
    if (!empty($ort)) {
        echo '<div class="hero-meta-item"><strong>' . ($is_en ? 'Where:' : 'Wo:') . '</strong> ' . esc_html($ort) . '</div>';
    }
    echo '</div>';
    
    // Preis
    if ($preis > 0) {
        echo '<div class="hero-preis">' . wc_price($preis) . '</div>';
    }
    
    // Button
    echo '<a href="' . get_permalink($post->ID) . '" class="hero-button">' . ($is_en ? 'More info &raquo;' : 'Mehr erfahren &raquo;') . '</a>';
    
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // Markiere diesen Workshop als bereits angezeigt
    $displayed_ids = [$post->ID];
else :
    $displayed_ids = [];
endif;

// ============================================================================
// KINDERWORKSHOPS SECTION
// ============================================================================

if (!empty($kinder_upcoming)) :
    echo '<div class="workshop-section kinder">';
    echo '<div class="section-header">';
    echo '<h2 class="section-title">' . ($is_en ? 'Children\'s Workshops' : 'Kinderworkshops') . '</h2>';
    echo '<p class="section-subtitle">' . ($is_en ? 'Creativity for our youngest artists' : 'Kreativität für unsere jüngsten Künstler') . '</p>';
    echo '</div>';
    
    // Filtere bereits in Hero angezeigten Workshop
    $kinder_display = array_filter($kinder_upcoming, function($w) use ($displayed_ids) {
        return !in_array($w['post']->ID, $displayed_ids);
    });
    
    if (!empty($kinder_display)) :
        echo '<div class="workshops-grid">';
        foreach ($kinder_display as $workshop) :
            display_workshop_card($workshop);
        endforeach;
        echo '</div>';
    endif;
    
    echo '</div>';
endif;

// ============================================================================
// ATELIERKURSE SECTION
// ============================================================================

if (!empty($erwachsenen_upcoming)) :
    echo '<div class="workshop-section">';
    
    // Filtere bereits in Hero angezeigten Workshop
    $erwachsenen_display = array_filter($erwachsenen_upcoming, function($w) use ($displayed_ids) {
        return !in_array($w['post']->ID, $displayed_ids);
    });
    
    if (!empty($erwachsenen_display)) :
        echo '<div class="workshops-grid">';
        foreach ($erwachsenen_display as $workshop) :
            display_workshop_card($workshop);
        endforeach;
        echo '</div>';
    endif;
    
    echo '</div>';
endif;

// ============================================================================
// ARCHIV SECTION (Vergangene Workshops)
// ============================================================================

if (!empty($archiv_workshops)) :
    echo '<div class="workshop-section archiv">';
    echo '<div class="section-header">';
    echo '<h2 class="section-title">' . ($is_en ? 'Past Workshops' : 'Vergangene Workshops') . '</h2>';
    echo '<p class="section-subtitle">' . ($is_en ? 'A look back at creative moments' : 'Ein Rückblick auf kreative Momente') . '</p>';
    echo '</div>';
    
    echo '<div class="archiv-toggle">';
    echo '<button class="archiv-toggle-button" onclick="toggleArchiv()">';
    echo ($is_en ? 'Show past workshops' : 'Vergangene Workshops anzeigen') . ' <span class="arrow">▼</span>';
    echo '</button>';
    echo '</div>';
    
    echo '<div class="archiv-content">';
    echo '<div class="workshops-grid">';
    foreach ($archiv_workshops as $workshop) :
        display_workshop_card($workshop, true);
    endforeach;
    echo '</div>';
    echo '</div>';
    echo '</div>';
endif;

// No workshops message
if (empty($kinder_upcoming) && empty($erwachsenen_upcoming) && empty($archiv_workshops)) :
    echo '<div class="no-workshops">';
    echo '<p>' . ($is_en ? 'No workshops found.' : 'Keine Workshops gefunden.') . '</p>';
    echo '</div>';
endif;

echo '</div>';

// Close container
?>

<script>
function toggleArchiv() {
    var content = document.querySelector('.archiv-content');
    var button = document.querySelector('.archiv-toggle-button');
    
    if (content.classList.contains('show')) {
        content.classList.remove('show');
        button.classList.remove('active');
        button.querySelector('.arrow').textContent = '▼';
    } else {
        content.classList.add('show');
        button.classList.add('active');
        button.querySelector('.arrow').textContent = '▲';
    }
}
</script>

<?php get_footer();