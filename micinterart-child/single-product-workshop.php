<?php
/**
 * Template für einzelne Workshop-Produkte (WooCommerce)
 * 
 * @package Micinterart
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!class_exists('WooCommerce')) {
    // Fallback: Alte Workshop-Einzelseite anzeigen
    include(get_template_directory() . '/single-workshop.php');
    exit;
}

// Check if this is a workshop product
$product = wc_get_product(get_the_ID());
if (!$product) {
    // Fallback to standard product template
    micinterart_render_wc_default_single_product();
    return;
}

$is_workshop = function_exists('micinterart_wc_is_workshop_product')
    && micinterart_wc_is_workshop_product($product);
$terms = get_the_terms(get_the_ID(), 'product_cat');
if ($terms && !is_wp_error($terms)) {
    foreach ($terms as $term) {
        if (in_array($term->slug, ['workshops', 'atelierkurse', 'kinderworkshops', 'kinderworkshop', 'erwachsenenworkshop', 'erwachsenenworkshops', 'familienworkshop'], true)) {
            $is_workshop = true;
            break;
        }
    }
}

if (!$is_workshop) {
    // Falls kein Workshop, normale Produktseite anzeigen
    micinterart_render_wc_default_single_product();
    return;
}

get_header();
$is_en = (function_exists('pll_current_language') && pll_current_language() === 'en');

// Meta-Daten holen
$product_id = get_the_ID();
$datum = get_post_meta($product_id, '_workshop_datum', true);
$uhrzeit_von = get_post_meta($product_id, '_workshop_uhrzeit_von', true);
$uhrzeit_bis = get_post_meta($product_id, '_workshop_uhrzeit_bis', true);
$startzeit = get_post_meta($product_id, '_workshop_startzeit', true);
$dauer_stunden = get_post_meta($product_id, '_workshop_dauer_stunden', true);
$ort = get_post_meta($product_id, '_workshop_ort', true);
$adresse = get_post_meta($product_id, '_workshop_adresse', true);
$alter_von = get_post_meta($product_id, '_workshop_alter_von', true);
$alter_bis = get_post_meta($product_id, '_workshop_alter_bis', true);
$preis_string = get_post_meta($product_id, '_workshop_preis', true);
$preis_info = get_post_meta($product_id, '_workshop_preis_info', true);
$sprache = get_post_meta($product_id, '_workshop_sprache', true) ?: 'deutsch';
$sprache_text = ($sprache === 'russisch') ? ($is_en ? 'Russian' : 'Russisch') : ($is_en ? 'German' : 'Deutsch');
$max_teilnehmer = get_post_meta($product_id, '_workshop_max_teilnehmer', true);
$status = get_post_meta($product_id, '_workshop_status', true) ?: 'geplant';
$current_bookings = get_post_meta($product_id, '_workshop_current_bookings', true);
$flyer_id = get_post_meta($product_id, '_workshop_flyer', true);

// Kategorie prüfen
$is_kinderworkshop = false;
$is_erwachsenenworkshop = false;

if ($terms && !is_wp_error($terms)) {
    foreach ($terms as $term) {
        if (in_array($term->slug, ['kinderworkshop', 'kinderworkshops'], true)) {
            $is_kinderworkshop = true;
        }
        if (in_array($term->slug, ['erwachsenenworkshop', 'erwachsenenworkshops', 'atelierkurse'], true)) {
            $is_erwachsenenworkshop = true;
        }
    }
}

// Familienworkshop: Duo-Preis plus Aufpreise, Anmeldung nach Erwachsenen und Kindern
$is_familienworkshop = function_exists('micinterart_is_familienworkshop') && micinterart_is_familienworkshop($product_id);
$familie_extra = $is_familienworkshop ? micinterart_familie_extra_prices($product_id) : ['adult' => 0, 'child' => 0];
$familie_format = function ($amount) use ($is_en) {
    return $is_en ? '€ ' . number_format($amount, 2, '.', ',') : number_format($amount, 2, ',', '.') . ' €';
};

// Dynamische Texte je nach Typ
$price_label = $is_kinderworkshop ? ($is_en ? 'Price per child' : 'Preis pro Kind') : ($is_en ? 'Price per person' : 'Preis pro Person');
$termin_label = $is_en ? 'Workshop Date' : 'Workshop-Termin';
$anmeldung_title = $is_en ? 'Register now!' : 'Jetzt anmelden!';
$nach_absprache_text = $is_en ? 'Workshop by appointment' : 'Workshop findet nach Absprache statt';
$nach_absprache_beschreibung = $is_en 
    ? 'This workshop takes place at an individually agreed date. Please contact me to find a suitable date!' 
    : 'Dieser Workshop findet zu einem individuell vereinbarten Termin statt. Kontaktiere mich gerne, um einen passenden Termin zu finden!';

$status_labels = [
    'geplant' => $is_en ? 'Planned' : 'Geplant',
    'anmeldung_offen' => $is_en ? 'Registration open' : 'Anmeldung offen',
    'fast_ausgebucht' => $is_en ? 'Almost full' : 'Fast ausgebucht',
    'ausgebucht' => $is_en ? 'Fully booked' : 'Ausgebucht',
    'beendet' => $is_en ? 'Completed' : 'Beendet',
    'abgesagt' => $is_en ? 'Cancelled' : 'Abgesagt',
];

// Preis-Formatierung
$preis = $product->get_price();
$preis_formatted = $preis ? ($is_en ? '€ ' . number_format($preis, 2, '.', ',') : number_format($preis, 2, ',', '.') . ' €') : '';
$suffix = '';
if (!empty($preis_info)) {
    $suffix = $preis_info;
} elseif ($is_familienworkshop) {
    $suffix = $is_en ? 'Duo price (1 adult, 1 child)' : 'Duo-Preis (1 Erwachsener, 1 Kind)';
} elseif ($is_kinderworkshop) {
    $suffix = $is_en ? 'per child' : 'pro Kind';
} else {
    $suffix = $is_en ? 'per person' : 'pro Person';
}

// Stock-Status
$stock_quantity = $product->get_stock_quantity();
$stock_status = $product->get_stock_status();
$kann_anmelden = ($status !== 'beendet' && $status !== 'abgesagt' && $status !== 'ausgebucht' && $stock_status !== 'outofstock');

// Uhrzeiten formatieren
$uhrzeit_display = '';
if ($uhrzeit_von) {
    $uhrzeit_display = $uhrzeit_von;
    if ($uhrzeit_bis) {
        $uhrzeit_display .= ' – ' . $uhrzeit_bis . ' ' . ($is_en ? 'o\'clock' : 'Uhr');
    } else {
        $uhrzeit_display .= ' ' . ($is_en ? 'o\'clock' : 'Uhr');
    }
} elseif ($startzeit) {
    $uhrzeit_display = $startzeit . ' ' . ($is_en ? 'o\'clock' : 'Uhr');
    if ($dauer_stunden) {
        $uhrzeit_display .= ' (' . $dauer_stunden . ' ' . ($is_en ? 'hours' : 'Stunden') . ') ';
    }
}

// Datum formatieren
$datum_formatted = '';
if ($datum) {
    try {
        $date_obj = new DateTime($datum);
        $datum_formatted = date_i18n('l, d. F Y', $date_obj->getTimestamp());
    } catch (Exception $e) {
        $datum_formatted = $datum;
    }
}

$ist_nach_absprache = empty($datum);

// Altersempfehlung
$alter_display = '';
if ($alter_von || $alter_bis) {
    $alter_display = $alter_von . ($alter_bis ? ' – ' . $alter_bis : '+') . ' ' . ($is_en ? 'years' : 'Jahre');
}

// Ort formatieren
$ort_display = $ort;
if ($adresse) {
    $ort_display .= ', ' . $adresse;
}

// Status-Class
$status_class = 'status-' . $status;

// Weitere Workshops für die Box
$related_workshops_query = new WP_Query([
    'post_type' => 'product',
    'lang' => '',
    'post_status' => 'publish',
    'posts_per_page' => 3,
    'post__not_in' => [$product_id],
    'tax_query' => [[
        'taxonomy' => 'product_type',
        'field' => 'slug',
        'terms' => 'workshop',
    ]],
    'meta_query' => [
        'relation' => 'OR',
        [ 'key' => '_workshop_datum', 'value' => date('Y-m-d'), 'compare' => '>=', 'type' => 'DATE' ],
        [ 'key' => '_workshop_datum', 'compare' => 'NOT EXISTS' ],
        [ 'key' => '_workshop_datum', 'value' => '', 'compare' => '=' ],
    ],
    'meta_key' => '_workshop_datum',
    'orderby' => 'meta_value',
    'order' => 'ASC',
]);
$related_workshops = [];

while ($related_workshops_query->have_posts()) {
    $related_workshops_query->the_post();
    $related_workshops[] = [
        'id' => get_the_ID(),
        'title' => get_the_title(),
    ];
}
wp_reset_postdata();

?>

<style>
/* Single Workshop Product Styling */
.workshop-single-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 60px 20px;
}

.workshop-single-header {
    text-align: center;
    margin-bottom: 40px;
}

.workshop-single-title {
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    font-size: 2.8em;
    margin: 0 0 20px 0;
    letter-spacing: 2px;
}

.workshop-single-status {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 1em;
    font-weight: 600;
    margin-bottom: 20px;
}

.status-geplant { background: #e3f2fd; color: #1976d2; }
.status-anmeldung_offen { background: #e8f5e9; color: #388e3c; }
.status-fast_ausgebucht { background: #fff3e0; color: #f57c00; }
.status-ausgebucht { background: #ffebee; color: #d32f2f; }
.status-beendet { background: #f5f5f5; color: #666; }
.status-abgesagt { background: #fce4ec; color: #c2185b; }

.workshop-featured-image {
    margin: 30px 0;
    text-align: center;
}

.workshop-featured-image img {
    max-width: 100%;
    height: auto;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.workshop-gallery-section {
    margin: 40px 0;
}

.workshop-gallery-section h2 {
    margin: 0 0 20px 0;
}

.workshop-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px;
}

.workshop-gallery-item {
    display: block;
    overflow: hidden;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    aspect-ratio: 1;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.workshop-gallery-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.workshop-gallery-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.18);
}

.workshop-lightbox {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 99999;
    background: rgba(0,0,0,0.92);
    align-items: center;
    justify-content: center;
}

.workshop-lightbox.active { display: flex; }

.workshop-lightbox img {
    max-width: 90vw;
    max-height: 90vh;
    border-radius: 8px;
}

.workshop-lightbox-btn {
    position: absolute;
    color: #fff;
    font-size: 2.5em;
    cursor: pointer;
    user-select: none;
    padding: 10px 20px;
}

.workshop-lightbox-close { top: 10px; right: 10px; }
.workshop-lightbox-prev { left: 10px; top: 50%; transform: translateY(-50%); }
.workshop-lightbox-next { right: 10px; top: 50%; transform: translateY(-50%); }

@media (max-width: 768px) {
    .workshop-gallery {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 10px;
    }
}

.workshop-info-box {
    background: #f5f5f5;
    padding: 30px;
    border-radius: 12px;
    margin: 30px 0;
}

.workshop-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.workshop-info-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.workshop-info-label {
    font-size: 0.9em;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.workshop-info-value {
    font-size: 1.2em;
    font-weight: 600;
    color: #2c2c2c;
}

.workshop-content {
    line-height: 1.8;
    font-size: 1.1em;
    margin: 30px 0;
}

.workshop-anmeldung-box {
    background: #fff;
    border: 2px solid #2c2c2c;
    padding: 30px;
    border-radius: 12px;
    margin: 40px 0;
    text-align: center;
}

.workshop-anmeldung-title {
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    font-size: 2em;
    margin: 0 0 20px 0;
}

.workshop-anmeldung-content {
    margin: 20px 0;
}

.workshop-anmeldung-buttons {
    display: flex;
    flex-direction: column;
    gap: 15px;
    max-width: 400px;
    margin: 0 auto;
}

.workshop-familie-preise {
    margin-bottom: 15px;
}

.workshop-familie-preise p {
    margin: 0 0 4px;
}

.workshop-familie-steppers {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px 30px;
}

.workshop-familie-stepper-label {
    display: block;
    text-align: center;
    font-weight: 600;
    margin-bottom: 6px;
}

.workshop-familie-hinweis {
    background: #fff4e5;
    border-left: 3px solid var(--mic-gold, #E2AC12);
    padding: 10px 14px;
    margin: 0 0 15px;
    text-align: left;
}

.workshop-familie-total {
    margin: 0 0 15px;
    font-size: 1.1em;
}

.workshop-anmeldung-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.workshop-quantity-control {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-bottom: 15px;
}

.workshop-quantity-step {
    width: 44px;
    height: 44px;
    border: 1px solid #999;
    border-radius: 4px;
    background: #fff;
    color: #2c2c2c;
    font-size: 1.4em;
    cursor: pointer;
}

.workshop-quantity-step:disabled {
    color: #aaa;
    cursor: not-allowed;
}

.workshop-quantity-input {
    width: 72px;
    height: 44px;
    padding: 8px;
    text-align: center;
}

.workshop-anmeldung-button {
    padding: 15px 30px;
    background: #2c2c2c;
    color: #fff;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1.1em;
    transition: background 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.workshop-anmeldung-button:hover {
    background: #000;
}

.workshop-back-link {
    text-align: center;
    margin-top: 50px;
}

.workshop-back-link a {
    color: #666;
    text-decoration: none;
    font-size: 1.1em;
}

.workshop-back-link a:hover {
    color: #2c2c2c;
}

.workshop-meta-dates h3 {
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    letter-spacing: 1px;
    border-bottom: 1px solid #ddd;
    padding-bottom: 5px;
    margin-top: 30px;
}

.workshop-nach-absprache-box {
    background: #e3f2fd;
    border-left: 4px solid #1976d2;
    padding: 20px;
    margin: 30px 0;
    border-radius: 4px;
}

.workshop-nach-absprache-box h3 {
    margin-top: 0;
    color: #1976d2;
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    font-size: 1.8em;
    letter-spacing: 1px;
}

.workshop-nach-absprache-box p {
    margin: 10px 0;
    line-height: 1.6;
}

@media (max-width: 768px) {
    .workshop-info-grid {
        grid-template-columns: 1fr;
    }
}

/* Inline Anmelden-Button (unter Hero-Bereich) */
.workshop-cta-inline {
    text-align: center;
    margin: 25px 0;
}

.workshop-cta-inline a {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 32px;
    background: var(--mic-gold, #E2AC12);
    color: #1a1a1a;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1.1em;
    transition: background 0.2s ease, transform 0.2s ease;
}

.workshop-cta-inline a:hover {
    background: var(--mic-gold-deep, #C48F00);
    transform: translateY(-1px);
}

/* Floating Anmelden-Button */
.workshop-cta-floating {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
    opacity: 0;
    transform: translateY(20px);
    pointer-events: none;
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.workshop-cta-floating.is-visible {
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
}

.workshop-cta-floating a {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 24px;
    background: #2c2c2c;
    color: #fff;
    text-decoration: none;
    border-radius: 50px;
    font-weight: 600;
    font-size: 1em;
    box-shadow: 0 4px 16px rgba(0,0,0,0.25);
    transition: background 0.2s ease, transform 0.2s ease;
}

.workshop-cta-floating a:hover {
    background: #000;
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .workshop-cta-floating {
        bottom: 16px;
        right: 16px;
        left: 16px;
    }
    .workshop-cta-floating a {
        width: 100%;
        justify-content: center;
        box-sizing: border-box;
    }
}

/* Was dich erwartet Box */
.workshop-expectations {
    background: #f9f9f9;
    padding: 30px;
    border-radius: 8px;
    margin: 40px 0;
    border-left: 5px solid var(--mic-gold, #E2AC12);
}

.workshop-expectations h3 {
    margin-top: 0;
    color: #2c2c2c;
    font-size: 1.5em;
}

.workshop-expectations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.workshop-expectations-item {
    padding-left: 14px;
    border-left: 2px solid var(--mic-gold, #E2AC12);
}

.workshop-expectations-item strong {
    display: block;
    color: #2c2c2c;
}

.workshop-expectations-item span {
    color: #666;
    font-size: 0.95em;
    display: block;
    margin-top: 3px;
}

/* Related Workshops */
.related-workshops {
    margin: 60px 0 40px;
}

.related-workshops h3 {
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    font-size: 2em;
    text-align: center;
    margin-bottom: 30px;
    letter-spacing: 1.5px;
}

.related-workshops-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}

.related-workshop-card {
    background: #fff;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.related-workshop-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.related-workshop-card img {
    display: block;
    width: 100%;
    height: 180px;
    object-fit: cover;
    opacity: 1 !important;
}

.related-workshop-card-noimage {
    height: 180px;
    background: #f0f0f0;
}

.related-workshop-card-content {
    padding: 15px;
}

.related-workshop-card-title {
    font-size: 1.1em;
    font-weight: 600;
    margin: 0 0 10px 0;
}

.related-workshop-card-title a {
    color: #2c2c2c;
    text-decoration: none;
}

.related-workshop-card-date {
    color: #666;
    font-size: 0.9em;
}

.related-workshop-card-preis {
    color: #2c2c2c;
    font-weight: 600;
    margin-top: 10px;
}
</style>

<main id="primary" class="site-main">
    <div class="workshop-single-container">

        <article id="post-<?php echo esc_attr($product_id); ?>" <?php post_class('workshop-single'); ?>>

            <header class="workshop-single-header">
                <h1 class="workshop-single-title"><?php the_title(); ?></h1>
                
                <div class="workshop-single-status <?php echo esc_attr($status_class); ?>">
                    <?php echo esc_html($status_labels[$status] ?? $status); ?>
                </div>
            </header>

            <?php if ($product->get_image_id()) : ?>
                <div class="workshop-featured-image">
                    <?php echo wp_get_attachment_image($product->get_image_id(), 'large', false, [
                        'class' => 'skip-lazy',
                        'alt' => get_the_title($product_id),
                        'loading' => 'eager',
                        'fetchpriority' => 'high',
                    ]); ?>
                </div>
            <?php endif; ?>

            <?php if ($kann_anmelden) : ?>
                <div class="workshop-cta-inline">
                    <a href="#workshop-anmeldung">
                        <?php echo $is_en ? 'Register now' : 'Jetzt anmelden'; ?>
                    </a>
                </div>
            <?php endif; ?>

            <div class="workshop-content">
                <?php the_content(); ?>
            </div>

            <?php
            // WooCommerce-Produktgalerie (zusaetzliche Fotos zum Workshop)
            $gallery_ids = $product->get_gallery_image_ids();
            if (!empty($gallery_ids)) :
            ?>
                <section class="workshop-gallery-section">
                    <h2><?php echo $is_en ? 'Impressions' : 'Impressionen'; ?></h2>
                    <div class="workshop-gallery">
                        <?php foreach ($gallery_ids as $gallery_id) :
                            $full_url = wp_get_attachment_image_url($gallery_id, 'large');
                            if (!$full_url) continue;
                        ?>
                            <a href="<?php echo esc_url($full_url); ?>" class="workshop-gallery-item">
                                <?php echo wp_get_attachment_image($gallery_id, 'medium_large', false, [
                                    'alt' => get_the_title($product_id),
                                    'loading' => 'lazy',
                                ]); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($ist_nach_absprache) : ?>
                <div class="workshop-nach-absprache-box">
                    <h3><?php echo esc_html($nach_absprache_text); ?></h3>
                    <p><?php echo esc_html($nach_absprache_beschreibung); ?></p>
                </div>
            <?php else : ?>
                <div class="workshop-info-box">
                    <h3 style="margin-top: 0; color: #2c2c2c;">ℹ️ <?php echo $is_en ? 'Workshop Details' : 'Workshop-Details'; ?></h3>
                    <div class="workshop-info-grid">
                        <?php if ($datum_formatted) : ?>
                        <div class="workshop-info-item">
                            <div class="workshop-info-label"><?php echo $is_en ? 'Date' : 'Datum'; ?></div>
                            <div class="workshop-info-value"><?php echo esc_html($datum_formatted); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($uhrzeit_display) : ?>
                        <div class="workshop-info-item">
                            <div class="workshop-info-label"><?php echo $is_en ? 'Time' : 'Uhrzeit'; ?></div>
                            <div class="workshop-info-value"><?php echo esc_html($uhrzeit_display); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($ort_display) : ?>
                        <div class="workshop-info-item">
                            <div class="workshop-info-label"><?php echo $is_en ? 'Location' : 'Ort'; ?></div>
                            <div class="workshop-info-value"><?php echo esc_html($ort_display); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($alter_display) : ?>
                        <div class="workshop-info-item">
                            <div class="workshop-info-label"><?php echo $is_en ? 'Age' : 'Alter'; ?></div>
                            <div class="workshop-info-value"><?php echo esc_html($alter_display); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($max_teilnehmer) : ?>
                        <div class="workshop-info-item">
                            <div class="workshop-info-label"><?php echo $is_en ? 'Group Size' : 'Gruppengröße'; ?></div>
                            <div class="workshop-info-value">Max. <?php echo esc_html($max_teilnehmer); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="workshop-info-item">
                            <div class="workshop-info-label"><?php echo $is_en ? 'Language' : 'Sprache'; ?></div>
                            <div class="workshop-info-value"><?php echo esc_html($sprache_text); ?></div>
                        </div>
                    </div>
                </div>
                
                <?php if ($stock_quantity > 0) : ?>
                    <div style="background: #e8f5e9; color: #388e3c; padding: 15px; border-radius: 8px; margin-top: 20px; text-align: center; font-weight: 600;">
                        <?php echo $is_en ? 'Still ' . $stock_quantity . ' places available!' : 'Noch ' . $stock_quantity . ' Plätze frei!'; ?>
                    </div>
                <?php elseif ($stock_quantity <= 0 && $stock_quantity !== null) : ?>
                    <div style="background: #ffebee; color: #d32f2f; padding: 15px; border-radius: 8px; margin-top: 20px; text-align: center; font-weight: 600;">
                        <?php echo $is_en ? 'This workshop is fully booked!' : 'Dieser Workshop ist ausgebucht!'; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php
            // "Was dich erwartet" Box: nur Felder mit Inhalt (Titel oder Beschreibung)
            $erwartet_items = [];
            for ($nr = 1; $nr <= 8; $nr++) {
                $e_titel = trim((string) get_post_meta($product_id, "_workshop_erwartet_{$nr}_titel", true));
                $e_text  = trim((string) get_post_meta($product_id, "_workshop_erwartet_{$nr}_text", true));
                if ($e_titel === '' && $e_text === '') {
                    continue;
                }
                $erwartet_items[] = ['titel' => $e_titel, 'text' => $e_text];
            }
            if ($erwartet_items) :
            ?>
            <div class="workshop-expectations">
                <h3><?php echo $is_en ? 'What to expect' : 'Was dich erwartet'; ?></h3>
                <div class="workshop-expectations-grid">
                    <?php foreach ($erwartet_items as $item) : ?>
                    <div class="workshop-expectations-item">
                        <div>
                            <?php if ($item['titel'] !== '') : ?><strong><?php echo esc_html($item['titel']); ?></strong><?php endif; ?>
                            <?php if ($item['text'] !== '') : ?><span><?php echo esc_html($item['text']); ?></span><?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="workshop-anmeldung-box" id="workshop-anmeldung">
                <h3 class="workshop-anmeldung-title"><?php echo esc_html($anmeldung_title); ?></h3>
                
                <?php if ($kann_anmelden) : ?>
                    <div class="workshop-anmeldung-content">
                        <p style="font-size: 1.1em; margin-bottom: 20px;">
                            <?php echo $is_en ? 'Book this workshop now!' : 'Buche diesen Workshop jetzt!'; ?>
                        </p>
                        
                        <div class="workshop-anmeldung-buttons">
                            <?php if ($product->is_purchasable() && $product->is_in_stock()) : ?>
                                <?php do_action('woocommerce_before_add_to_cart_form'); ?>
                                <form class="cart<?php echo $is_familienworkshop ? ' workshop-familie-form' : ''; ?>"<?php if ($is_familienworkshop) : ?> data-base="<?php echo esc_attr((float) $preis); ?>" data-adult-extra="<?php echo esc_attr($familie_extra['adult']); ?>" data-child-extra="<?php echo esc_attr($familie_extra['child']); ?>" data-max="<?php echo esc_attr($stock_quantity > 0 ? (int) $stock_quantity : 10); ?>" data-locale="<?php echo $is_en ? 'en-GB' : 'de-DE'; ?>" data-msg-adult="<?php echo esc_attr($is_en ? 'Family workshops require at least one adult to be registered.' : 'Bei Familienworkshops muss mindestens ein Erwachsener angemeldet werden.'); ?>" data-msg-child="<?php echo esc_attr($is_en ? 'Please register at least one child.' : 'Bitte melde mindestens ein Kind an.'); ?>" data-msg-max="<?php echo esc_attr($is_en ? 'Not enough places left.' : 'So viele Plätze sind leider nicht mehr frei.'); ?>"<?php endif; ?> action="<?php echo esc_url(apply_filters('woocommerce_add_to_cart_form_action', $product->get_permalink())); ?>" method="post" enctype="multipart/form-data">
                                    <?php do_action('woocommerce_before_add_to_cart_button'); ?>
                                    <?php if ($is_familienworkshop) : ?>
                                    <?php
                                    $familie_max = $stock_quantity > 0 ? (int) $stock_quantity : 10;
                                    $familie_steppers = [
                                        ['name' => 'workshop_adults', 'label' => $is_en ? 'Adults' : 'Erwachsene', 'less' => $is_en ? 'One adult less' : 'Einen Erwachsenen weniger', 'more' => $is_en ? 'One adult more' : 'Einen Erwachsenen mehr'],
                                        ['name' => 'workshop_children', 'label' => $is_en ? 'Children' : 'Kinder', 'less' => $is_en ? 'One child less' : 'Ein Kind weniger', 'more' => $is_en ? 'One child more' : 'Ein Kind mehr'],
                                    ];
                                    ?>
                                    <div class="workshop-familie-preise">
                                        <p><strong><?php echo esc_html($preis_formatted); ?></strong> <?php echo esc_html($suffix); ?></p>
                                        <?php if ($familie_extra['adult'] > 0) : ?>
                                            <p><?php echo esc_html(($is_en ? 'Each additional adult: +' : 'Jeder weitere Erwachsene: +') . $familie_format($familie_extra['adult'])); ?></p>
                                        <?php endif; ?>
                                        <?php if ($familie_extra['child'] > 0) : ?>
                                            <p><?php echo esc_html(($is_en ? 'Each additional child: +' : 'Jedes weitere Kind: +') . $familie_format($familie_extra['child'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="workshop-familie-steppers">
                                        <?php foreach ($familie_steppers as $stepper) : ?>
                                            <div class="workshop-familie-stepper">
                                                <span class="workshop-familie-stepper-label"><?php echo esc_html($stepper['label']); ?></span>
                                                <div class="workshop-quantity-control">
                                                    <button type="button" class="workshop-quantity-step" data-step="-1" aria-label="<?php echo esc_attr($stepper['less']); ?>">−</button>
                                                    <input class="input-text qty text workshop-quantity-input" type="number" name="<?php echo esc_attr($stepper['name']); ?>" value="1" min="0" max="<?php echo esc_attr($familie_max); ?>" step="1" inputmode="numeric" aria-label="<?php echo esc_attr($stepper['label']); ?>">
                                                    <button type="button" class="workshop-quantity-step" data-step="1" aria-label="<?php echo esc_attr($stepper['more']); ?>">+</button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="quantity" value="2" class="workshop-familie-quantity-input">
                                    <p class="workshop-familie-hinweis" hidden></p>
                                    <p class="workshop-familie-total"><?php echo esc_html($is_en ? 'Total: ' : 'Gesamt: '); ?><strong></strong></p>
                                    <?php else : ?>

                                    <div class="workshop-quantity-control">
                                        <button type="button" class="workshop-quantity-step" data-step="-1" aria-label="<?php echo esc_attr($is_en ? 'Remove one place' : 'Einen Platz weniger'); ?>" disabled>−</button>
                                        <label class="screen-reader-text" for="workshop-quantity-<?php echo esc_attr($product_id); ?>">
                                            <?php echo esc_html($is_en ? 'Number of places' : 'Anzahl der Plätze'); ?>
                                        </label>
                                        <input
                                            id="workshop-quantity-<?php echo esc_attr($product_id); ?>"
                                            class="input-text qty text workshop-quantity-input"
                                            type="number"
                                            name="quantity"
                                            value="1"
                                            min="1"
                                            max="<?php echo esc_attr($stock_quantity > 0 ? $stock_quantity : 1); ?>"
                                            step="1"
                                            inputmode="numeric"
                                            required
                                        >
                                        <button type="button" class="workshop-quantity-step" data-step="1" aria-label="<?php echo esc_attr($is_en ? 'Add one place' : 'Einen Platz mehr'); ?>">+</button>
                                    </div>
                                    <?php endif; ?>
                                    <button type="submit" name="add-to-cart" value="<?php echo esc_attr($product_id); ?>" class="single_add_to_cart_button button alt workshop-anmeldung-button">
                                        <?php echo $is_en ? 'Add to cart' : 'In den Warenkorb'; ?>
                                    </button>
                                    <?php do_action('woocommerce_after_add_to_cart_button'); ?>
                                </form>
                                <?php do_action('woocommerce_after_add_to_cart_form'); ?>
                            <?php else : ?>
                                <button type="button" class="workshop-anmeldung-button" style="opacity: 0.7; cursor: not-allowed;">
                                <?php echo $is_en ? 'Not available' : 'Nicht verfügbar'; ?>
                                </button>
                            <?php endif; ?>
                            
                            <a href="<?php echo esc_url(wc_get_cart_url()); ?>" class="workshop-anmeldung-button" style="background: linear-gradient(135deg, var(--mic-gold, #E2AC12), var(--mic-gold-deep, #C48F00)); border: none; color: #1a1a1a;">
                                <?php echo $is_en ? 'View cart' : 'Zum Warenkorb'; ?>
                            </a>
                        </div>
                        
                        <p style="margin-top: 20px; color: #666; font-size: 0.95em;">
                            <?php echo $is_en ? 'After adding to cart, you can proceed to checkout to complete your booking.' : 'Nach dem Hinzufügen zum Warenkorb kannst du zur Kasse gehen, um deine Buchung abzuschließen.'; ?>
                        </p>
                    </div>
                <?php else : ?>
                    <div class="workshop-anmeldung-content">
                        <p style="color: #666;"><?php echo $is_en ? 'This workshop cannot be booked at the moment.' : 'Dieser Workshop kann momentan nicht gebucht werden.'; ?></p>
                        
                        <?php if ($status === 'ausgebucht' || $stock_status === 'outofstock') : ?>
                            <p style="color: #666;"><?php echo $is_en ? 'All places are taken.' : 'Alle Plätze sind belegt.'; ?></p>
                        <?php elseif ($status === 'beendet') : ?>
                            <p style="color: #666;"><?php echo $is_en ? 'This workshop has already taken place.' : 'Dieser Workshop hat bereits stattgefunden.'; ?></p>
                        <?php elseif ($status === 'abgesagt') : ?>
                            <p style="color: #666;"><?php echo $is_en ? 'This workshop has been cancelled.' : 'Dieser Workshop wurde abgesagt.'; ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($related_workshops)) : ?>
            <div class="related-workshops">
                <h3><?php echo $is_en ? 'You may also like' : 'Weitere Workshops'; ?></h3>
                <div class="related-workshops-grid">
                    <?php foreach ($related_workshops as $rw) :
                        $rw_product = wc_get_product($rw['id']);
                        $rw_preis = $rw_product ? $rw_product->get_price() : '';
                        $rw_preis_formatted = $rw_preis ? ($is_en ? '€ ' . number_format($rw_preis, 2, '.', ',') : number_format($rw_preis, 2, ',', '.') . ' €') : '';
                        $rw_datum = get_post_meta($rw['id'], '_workshop_datum', true);
                        $rw_datum_formatted = $rw_datum ? date_i18n('d.m.Y', strtotime($rw_datum)) : '';
                        $rw_image_url = get_the_post_thumbnail_url($rw['id'], 'medium');
                        if (!$rw_image_url && $rw_product) {
                            $rw_gallery_ids = $rw_product->get_gallery_image_ids();
                            if ($rw_gallery_ids) {
                                $rw_image_url = wp_get_attachment_image_url($rw_gallery_ids[0], 'medium');
                            }
                        }
                    ?>
                    <div class="related-workshop-card">
                        <a href="<?php echo esc_url(get_permalink($rw['id'])); ?>">
                            <?php if ($rw_image_url) : ?>
                                <img src="<?php echo esc_url($rw_image_url); ?>" alt="<?php echo esc_attr($rw['title']); ?>" class="skip-lazy" data-skip-lazy="1" data-no-lazy="1" loading="eager">
                            <?php else : ?>
                                <div class="related-workshop-card-noimage"></div>
                            <?php endif; ?>
                        </a>
                        <div class="related-workshop-card-content">
                            <h4 class="related-workshop-card-title">
                                <a href="<?php echo esc_url(get_permalink($rw['id'])); ?>"><?php echo esc_html($rw['title']); ?></a>
                            </h4>
                            <?php if ($rw_datum_formatted) : ?>
                                <div class="related-workshop-card-date"><?php echo esc_html($rw_datum_formatted); ?></div>
                            <?php endif; ?>
                            <?php if ($rw_preis_formatted) : ?>
                                <div class="related-workshop-card-preis">
                                    <?php echo $rw_preis_formatted; ?>
                                    <?php if ($is_kinderworkshop) : ?>
                                        <small><?php echo $is_en ? 'per child' : 'pro Kind'; ?></small>
                                    <?php else : ?>
                                        <small><?php echo $is_en ? 'per person' : 'pro Person'; ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="workshop-back-link">
                <a href="<?php echo esc_url(get_post_type_archive_link('workshop')); ?>">
                    ← <?php echo $is_en ? 'Back to all workshops' : 'Zurück zu allen Workshops'; ?>
                </a>
            </div>

        </article>

        <?php if ($kann_anmelden) : ?>
        <div class="workshop-cta-floating" id="workshop-cta-floating">
            <a href="#workshop-anmeldung" onclick="event.preventDefault(); document.getElementById('workshop-anmeldung').scrollIntoView({ behavior: 'smooth' });">
                <?php echo $is_en ? 'Book now' : 'Jetzt buchen'; ?>
            </a>
        </div>
        <?php endif; ?>

    </div>
</main>

<script>
// Lightbox fuer die Workshop-Galerie
(function() {
    var items = Array.from(document.querySelectorAll('.workshop-gallery-item'));
    if (!items.length) return;

    var overlay = document.createElement('div');
    overlay.className = 'workshop-lightbox';
    overlay.innerHTML = '<span class="workshop-lightbox-btn workshop-lightbox-close">&times;</span>' +
        '<span class="workshop-lightbox-btn workshop-lightbox-prev">&#10094;</span>' +
        '<img alt="">' +
        '<span class="workshop-lightbox-btn workshop-lightbox-next">&#10095;</span>';
    document.body.appendChild(overlay);

    var img = overlay.querySelector('img');
    var current = 0;

    function show(i) {
        current = (i + items.length) % items.length;
        img.src = items[current].href;
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function close() {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    items.forEach(function(item, i) {
        item.addEventListener('click', function(e) { e.preventDefault(); show(i); });
    });
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay || e.target.classList.contains('workshop-lightbox-close')) close();
    });
    overlay.querySelector('.workshop-lightbox-prev').addEventListener('click', function(e) { e.stopPropagation(); show(current - 1); });
    overlay.querySelector('.workshop-lightbox-next').addEventListener('click', function(e) { e.stopPropagation(); show(current + 1); });
    document.addEventListener('keydown', function(e) {
        if (!overlay.classList.contains('active')) return;
        if (e.key === 'Escape') close();
        if (e.key === 'ArrowRight') show(current + 1);
        if (e.key === 'ArrowLeft') show(current - 1);
    });
})();

// Floating CTA Button
var ctaFloating = document.getElementById('workshop-cta-floating');
if (ctaFloating) {
    var anmeldungBox = document.getElementById('workshop-anmeldung');
    
    window.addEventListener('scroll', function() {
        if (anmeldungBox) {
            var rect = anmeldungBox.getBoundingClientRect();
            if (rect.top < -100) {
                ctaFloating.classList.add('is-visible');
            } else {
                ctaFloating.classList.remove('is-visible');
            }
        }
    });
}

// Scroll to anchor
if (window.location.hash === '#workshop-anmeldung') {
    setTimeout(function() {
        var el = document.getElementById('workshop-anmeldung');
        if (el) {
            el.scrollIntoView({ behavior: 'smooth' });
        }
    }, 300);
}

function micQty(value, fallback) {
    var number = parseInt(value, 10);
    return isNaN(number) ? fallback : number;
}

function updateWorkshopQuantityButtons(input) {
    var controls = input.closest('.workshop-quantity-control');
    if (!controls) {
        return;
    }

    var minimum = micQty(input.min, 1);
    var maximum = micQty(input.max, minimum);
    var quantity = micQty(input.value, minimum);
    controls.querySelector('[data-step="-1"]').disabled = quantity <= minimum;
    controls.querySelector('[data-step="1"]').disabled = quantity >= maximum;
}

document.querySelectorAll('.workshop-quantity-input').forEach(updateWorkshopQuantityButtons);

document.addEventListener('click', function(event) {
    var button = event.target.closest('.workshop-quantity-step');
    if (!button) {
        return;
    }

    var input = button.closest('.workshop-quantity-control').querySelector('.workshop-quantity-input');
    var minimum = micQty(input.min, 1);
    var maximum = micQty(input.max, minimum);
    var quantity = micQty(input.value, minimum);
    var step = parseInt(button.dataset.step, 10) || 0;

    input.value = Math.min(maximum, Math.max(minimum, quantity + step));
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
    updateWorkshopQuantityButtons(input);
});

document.addEventListener('change', function(event) {
    if (!event.target.matches('.workshop-quantity-input')) {
        return;
    }

    var input = event.target;
    var minimum = micQty(input.min, 1);
    var maximum = micQty(input.max, minimum);
    var quantity = micQty(input.value, minimum);

    input.value = Math.min(maximum, Math.max(minimum, quantity));
    updateWorkshopQuantityButtons(input);
});
// Familienworkshop: Personenzahl, Gesamtpreis und Hinweis
document.querySelectorAll('.workshop-familie-form').forEach(function(form) {
    var adultsInput = form.querySelector('[name="workshop_adults"]');
    var childrenInput = form.querySelector('[name="workshop_children"]');
    var quantityInput = form.querySelector('.workshop-familie-quantity-input');
    var notice = form.querySelector('.workshop-familie-hinweis');
    var total = form.querySelector('.workshop-familie-total strong');
    var submit = form.querySelector('button[type="submit"]');

    function updateFamilie() {
        var adults = Math.max(0, parseInt(adultsInput.value, 10) || 0);
        var children = Math.max(0, parseInt(childrenInput.value, 10) || 0);
        var maximum = parseInt(form.dataset.max, 10) || 10;
        var message = '';

        if (adults < 1) {
            message = form.dataset.msgAdult;
        } else if (children < 1) {
            message = form.dataset.msgChild;
        } else if (adults + children > maximum) {
            message = form.dataset.msgMax;
        }

        quantityInput.value = Math.max(1, adults + children);
        var sum = parseFloat(form.dataset.base)
            + Math.max(0, adults - 1) * parseFloat(form.dataset.adultExtra)
            + Math.max(0, children - 1) * parseFloat(form.dataset.childExtra);
        total.textContent = sum.toLocaleString(form.dataset.locale, { style: 'currency', currency: 'EUR' });

        notice.textContent = message;
        notice.hidden = message === '';
        submit.disabled = message !== '';
    }

    form.addEventListener('input', updateFamilie);
    form.addEventListener('change', updateFamilie);
    form.addEventListener('click', function() { setTimeout(updateFamilie, 0); });
    updateFamilie();
});

</script>
