<?php
/**
 * Template für Archivseite: Werke (CPT: werk)
 * 
 * Leitet zur WC-basierten archive-werk-wc.php weiter
 * 
 * @package Micinterart
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prüfe ob WooCommerce aktiv ist
if (class_exists('WooCommerce')) {
    // Versuche die angepasste WC-Archivseite zu laden
    $new_template = locate_template('archive-werk-wc.php');
    if (!empty($new_template)) {
        include($new_template);
        exit;
    }
    
    // Fallback: Leite zur WC-Kategorie weiter
    $werke_term = get_term_by('slug', 'werke', 'product_cat');
    if ($werke_term) {
        wp_redirect(get_term_link($werke_term), 301);
        exit;
    }
}

// Wenn WooCommerce nicht aktiv: Zeige eine Nachricht
get_header();
$is_en = function_exists('micinterart_is_english') ? micinterart_is_english() : (function_exists('pll_current_language') && pll_current_language() === 'en');
?>

<div style="padding: 60px 20px; text-align: center;">
    <h1><?php echo $is_en ? 'Artworks' : 'Meine Werke'; ?></h1>
    <p><?php echo $is_en ? 'WooCommerce must be active to display artworks.' : 'WooCommerce muss aktiviert sein, um die Werke anzuzeigen.'; ?></p>
</div>

<?php
get_footer();
