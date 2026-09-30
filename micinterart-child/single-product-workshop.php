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
        if ($term->slug === 'workshops' || $term->slug === 'atelierkurse' || $term->slug === 'kinderworkshops') {
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
$is_paar = get_post_meta($product_id, '_workshop_is_paar_preis', true);
$status = get_post_meta($product_id, '_workshop_status', true) ?: 'geplant';
$current_bookings = get_post_meta($product_id, '_workshop_current_bookings', true);
$flyer_id = get_post_meta($product_id, '_workshop_flyer', true);

// Kategorie prüfen
$is_kinderworkshop = false;
$is_erwachsenenworkshop = false;

if ($terms && !is_wp_error($terms)) {
    foreach ($terms as $term) {
        if ($term->slug === 'kinderworkshops') {
            $is_kinderworkshop = true;
        }
        if ($term->slug === 'atelierkurse') {
            $is_erwachsenenworkshop = true;
        }
    }
}

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
} elseif ($is_paar === 'yes') {
    $suffix = $is_en ? 'per couple' : 'pro Paar';
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

// „Was dich erwartet“-Felder
function get_erwartet_field($nr, $key, $default, $product_id) {
    $val = get_post_meta($product_id, "_workshop_erwartet_{$nr}_{$key}", true);
    return !empty($val) ? $val : $default;
}

$felder = [
    1 => ['emoji' => '🎨', 'titel' => $is_en ? 'All materials included' : 'Alle Materialien inklusive', 'text' => $is_en ? 'You don\'t need to bring anything - everything is prepared' : 'Du brauchst nichts mitzubringen – alles ist vorbereitet'],
    3 => ['emoji' => '🎓', 'titel' => $is_en ? 'No prior knowledge needed' : 'Keine Vorkenntnisse nötig', 'text' => $is_en ? 'I will guide you step by step' : 'Ich begleite dich Schritt für Schritt'],
    4 => ['emoji' => '🖼️', 'titel' => $is_en ? 'Your finished artwork' : 'Dein fertiges Kunstwerk', 'text' => $is_en ? 'To take home and proudly display' : 'Zum Mitnehmen und stolz nach Hause tragen'],
    5 => ['emoji' => '☕', 'titel' => $is_en ? 'Inclusive:' : 'Inklusive:', 'text' => $preis_info ?: ($is_en ? 'Coffee, tea, water and small snacks' : 'Kaffee, Tee, Wasser und kleine Leckereien')],
];

// Parkplätze-Logik
$parkplatz_text = '';
if (empty($ort) || stripos($ort, 'morsbach') !== false) {
    $parkplatz_text = $is_en ? 'Directly in front of the studio in Morsbach' : 'Direkt vor dem Atelier in Morsbach';
} else {
    $parkplatz_text = $is_en ? 'Please check parking options in advance.' : 'Bitte informiere dich vorab über die Parkmöglichkeiten vor Ort.';
}

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
$related_workshops = $this->get_related_workshops($product_id, $is_kinderworkshop);

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
    background: #d4a574;
    color: #fff;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1.1em;
    transition: background 0.2s ease, transform 0.2s ease;
}

.workshop-cta-inline a:hover {
    background: #c08f5a;
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
    border-left: 5px solid #d4a574;
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
    display: flex;
    align-items: start;
    gap: 12px;
}

.workshop-expectations-item span {
    font-size: 1.5em;
}

.workshop-expectations-item strong {
    display: block;
    color: #2c2c2c;
}

.workshop-expectations-item span+span {
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
    width: 100%;
    height: 180px;
    object-fit: cover;
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

            <?php if (has_post_thumbnail()) : ?>
                <div class="workshop-featured-image">
                    <?php the_post_thumbnail('large'); ?>
                </div>
            <?php endif; ?>

            <?php if ($kann_anmelden) : ?>
                <div class="workshop-cta-inline">
                    <a href="#workshop-anmeldung">
                        ✏️ <?php echo $is_en ? 'Register now' : 'Jetzt anmelden'; ?>
                    </a>
                </div>
            <?php endif; ?>

            <div class="workshop-content">
                <?php the_content(); ?>
            </div>

            <?php if ($ist_nach_absprache) : ?>
                <div class="workshop-nach-absprache-box">
                    <h3>🗓️ <?php echo esc_html($nach_absprache_text); ?></h3>
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
            // "Was dich erwartet" Box
            if ($felder) :
            ?>
            <div class="workshop-expectations">
                <h3>✨ <?php echo $is_en ? 'What to expect' : 'Was dich erwartet'; ?></h3>
                <div class="workshop-expectations-grid">
                    <div class="workshop-expectations-item">
                        <span><?php echo esc_html(get_erwartet_field(1, 'emoji', '🎨', $product_id)); ?></span>
                        <div>
                            <strong><?php echo esc_html(get_erwartet_field(1, 'titel', $felder[1]['titel'], $product_id)); ?></strong>
                            <span><?php echo esc_html(get_erwartet_field(1, 'text', $felder[1]['text'], $product_id)); ?></span>
                        </div>
                    </div>
                    
                    <div class="workshop-expectations-item">
                        <span>👥</span>
                        <div>
                            <strong><?php echo $is_en ? 'Small groups' : 'Kleine Gruppen'; ?></strong>
                            <span><?php echo $is_en ? 'Max. ' . ($max_teilnehmer ?: 8) . ' participants – personal guidance guaranteed' : 'Max. ' . ($max_teilnehmer ?: 8) . ' Teilnehmer – persönliche Betreuung garantiert'; ?></span>
                        </div>
                    </div>
                    
                    <div class="workshop-expectations-item">
                        <span><?php echo esc_html(get_erwartet_field(3, 'emoji', '🎓', $product_id)); ?></span>
                        <div>
                            <strong><?php echo esc_html(get_erwartet_field(3, 'titel', $felder[3]['titel'], $product_id)); ?></strong>
                            <span><?php echo esc_html(get_erwartet_field(3, 'text', $felder[3]['text'], $product_id)); ?></span>
                        </div>
                    </div>
                    
                    <div class="workshop-expectations-item">
                        <span><?php echo esc_html(get_erwartet_field(4, 'emoji', '🖼️', $product_id)); ?></span>
                        <div>
                            <strong><?php echo esc_html(get_erwartet_field(4, 'titel', $felder[4]['titel'], $product_id)); ?></strong>
                            <span><?php echo esc_html(get_erwartet_field(4, 'text', $felder[4]['text'], $product_id)); ?></span>
                        </div>
                    </div>
                    
                    <div class="workshop-expectations-item">
                        <span><?php echo esc_html(get_erwartet_field(5, 'emoji', '☕', $product_id)); ?></span>
                        <div>
                            <strong><?php echo esc_html(get_erwartet_field(5, 'titel', $felder[5]['titel'], $product_id)); ?></strong>
                            <span><?php echo esc_html(get_erwartet_field(5, 'text', $felder[5]['text'], $product_id)); ?></span>
                        </div>
                    </div>
                    
                    <div class="workshop-expectations-item">
                        <span>🚗</span>
                        <div>
                            <strong><?php echo $is_en ? 'Free parking' : 'Kostenlose Parkplätze'; ?></strong>
                            <span><?php echo esc_html($parkplatz_text); ?></span>
                        </div>
                    </div>
                    
                    <div class="workshop-expectations-item">
                        <span>🗣️</span>
                        <div>
                            <strong><?php echo $is_en ? 'Course language' : 'Kurssprache'; ?></strong>
                            <span><?php echo $is_en ? 'Course takes place in ' . $sprache_text : 'Kurs findet auf ' . $sprache_text . ' statt'; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="workshop-anmeldung-box" id="workshop-anmeldung">
                <h3 class="workshop-anmeldung-title">📅 <?php echo esc_html($anmeldung_title); ?></h3>
                
                <?php if ($kann_anmelden) : ?>
                    <div class="workshop-anmeldung-content">
                        <p style="font-size: 1.1em; margin-bottom: 20px;">
                            <?php echo $is_en ? 'Book this workshop now!' : 'Buche diesen Workshop jetzt!'; ?>
                        </p>
                        
                        <div class="workshop-anmeldung-buttons">
                            <?php
                            // Add to cart form
                            do_action('woocommerce_before_add_to_cart_form');
                            
                            if ($product->is_purchasable() && $product->is_in_stock()) {
                                woocommerce_quantity_input([
                                    'min_value' => 1,
                                    'max_value' => $stock_quantity > 0 ? $stock_quantity : 1,
                                    'step' => 1,
                                    'input_value' => 1,
                                ]);
                                
                                echo '<button type="submit" name="add-to-cart" value="' . esc_attr($product_id) . '" class="workshop-anmeldung-button">';
                                echo '🛒 ' . ($is_en ? 'Add to cart' : 'In den Warenkorb');
                                echo '</button>';
                            } else {
                                echo '<button type="button" class="workshop-anmeldung-button" style="opacity: 0.7; cursor: not-allowed;">';
                                echo $is_en ? 'Not available' : 'Nicht verfügbar';
                                echo '</button>';
                            }
                            
                            do_action('woocommerce_after_add_to_cart_form');
                            
                            // View cart button
                            echo '<a href="' . esc_url(wc_get_cart_url()) . '" class="workshop-anmeldung-button" style="background: linear-gradient(135deg, #d4a574, #c4915e); border: none;">';
                            echo '📋 ' . ($is_en ? 'View cart' : 'Zum Warenkorb');
                            echo '</a>';
                            ?>
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
                        $rw_has_image = has_post_thumbnail($rw['id']);
                        $rw_image_url = $rw_has_image ? get_the_post_thumbnail_url($rw['id'], 'medium') : 'https://micinterart.de/wp-content/uploads/2026/06/5341273144251062761_121.jpg';
                    ?>
                    <div class="related-workshop-card">
                        <a href="<?php echo esc_url(get_permalink($rw['id'])); ?>">
                            <img src="<?php echo esc_url($rw_image_url); ?>" alt="<?php echo esc_attr($rw['title']); ?>" loading="lazy">
                        </a>
                        <div class="related-workshop-card-content">
                            <h4 class="related-workshop-card-title">
                                <a href="<?php echo esc_url(get_permalink($rw['id'])); ?>"><?php echo esc_html($rw['title']); ?></a>
                            </h4>
                            <?php if ($rw_datum_formatted) : ?>
                                <div class="related-workshop-card-date">📅 <?php echo esc_html($rw_datum_formatted); ?></div>
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
                ✏️ <?php echo $is_en ? 'Book now' : 'Jetzt buchen'; ?>
            </a>
        </div>
        <?php endif; ?>

    </div>
</main>

<script>
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
</script>
