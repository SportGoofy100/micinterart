<?php
/**
 * Workshop-Archiv: Zeigt WooCommerce-Workshops an
 * 
 * Workshops werden nun als WooCommerce-Produkte verwaltet.
 * Diese Datei leitet zur angepassten WC-Ansicht weiter.
 * 
 * @package Micinterart
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prüfe ob WooCommerce aktiv ist
if (class_exists('WooCommerce')) {
    // Versuche die angepasste WC-Archivseite zu laden
    $new_template = locate_template('archive-workshop-wc.php');
    if (!empty($new_template)) {
        include($new_template);
        exit;
    }
    
    // Fallback: Leite zur WC-Kategorie weiter
    $workshops_term = get_term_by('slug', 'workshops', 'product_cat');
    if ($workshops_term) {
        wp_redirect(get_term_link($workshops_term), 301);
        exit;
    }
}

// Wenn WooCommerce nicht aktiv: Zeige eine Nachricht
get_header();
echo '<div style="padding: 60px 20px; text-align: center;">';
echo '<h1>Workshops</h1>';
echo '<p>WooCommerce muss aktiviert sein, um die Workshop-Übersicht anzuzeigen.</p>';
echo '</div>';
get_footer();
