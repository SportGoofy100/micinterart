<?php
/**
 * Info-Banner auf Startseite und Workshop-Seiten (z. B. bei technischen Störungen)
 *
 * Ein-/Ausschalten: MICINTERART_INFO_BANNER_ACTIVE unten auf false setzen
 * (oder die Zeile 'includes/info-banner.php' in functions.php entfernen) und neu deployen.
 * Danach ggf. den Seiten-Cache leeren.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('MICINTERART_INFO_BANNER_ACTIVE')) {
    define('MICINTERART_INFO_BANNER_ACTIVE', true);
}

/**
 * Seiten mit Banner: Startseite, Workshop-Übersicht und einzelne Workshop-Produkte.
 */
function micinterart_info_banner_applies() {
    if (is_front_page() || is_post_type_archive('workshop')) {
        return true;
    }
    if (function_exists('is_product') && is_product()) {
        $product_id = get_the_ID();
        $product = wc_get_product($product_id);
        return ($product && $product->get_type() === 'workshop') || (bool) get_post_meta($product_id, '_workshop_datum', true);
    }
    return false;
}

function micinterart_info_banner_render() {
    if (!MICINTERART_INFO_BANNER_ACTIVE || !micinterart_info_banner_applies()) {
        return;
    }
    $is_en = function_exists('micinterart_is_english') && micinterart_is_english();
    $text = $is_en
        ? 'Due to a technical problem, workshops cannot be booked at the moment. We are working on a fix. Questions? Write to info@micinterart.de.'
        : 'Aufgrund eines technischen Problems können aktuell leider keine Workshops gebucht werden. Wir arbeiten an der Behebung. Bei Fragen erreichst du uns unter info@micinterart.de.';
    ?>
    <div class="micinterart-info-banner" role="status">
        <p><?php echo esc_html($text); ?></p>
    </div>
    <style>
    .micinterart-info-banner { background: #fff3cd; color: #5c4400; border-bottom: 2px solid var(--mic-gold, #E2AC12); padding: 12px 20px; text-align: center; font-size: 1em; line-height: 1.5; }
    .micinterart-info-banner p { margin: 0; max-width: 1100px; margin-inline: auto; }
    </style>
    <?php
}
add_action('wp_body_open', 'micinterart_info_banner_render', 5);
