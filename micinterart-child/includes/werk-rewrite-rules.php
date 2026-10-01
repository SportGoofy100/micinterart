<?php
/**
 * Rewrite-Regeln für Werke-Archivseite
 * 
 * Leitet /werke/ auf die Produktkategorie um, damit die URL
 * analog zu /workshops/ funktioniert.
 * 
 * @package Micinterart
 */

if (!defined('ABSPATH')) {
    return;
}

// Rewrite-Regel hinzufügen
function micinterart_werke_rewrite_rules() {
    add_rewrite_rule(
        '^werke/?$',
        'index.php?product_cat=werke',
        'top'
    );
    
    // Flush rules nur einmal (nach Theme-Aktivierung oder Regel-Änderung)
    // flush_rewrite_rules(); // nur temporär aktivieren, dann deaktivieren
}
add_action('init', 'micinterart_werke_rewrite_rules', 10, 0);

// Query-Vars für die Werks-Kategorie hinzufügen
function micinterart_werke_query_vars($vars) {
    $vars[] = 'product_cat';
    return $vars;
}
add_filter('query_vars', 'micinterart_werke_query_vars');
