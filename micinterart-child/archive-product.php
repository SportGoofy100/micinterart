<?php
/**
 * Template für Produkt-Archiv (WooCommerce)
 * 
 * Diese Datei leitet zu den spezifischen Templates weiter:
 * - Workshop-Produktkategorien → archive-workshop-wc.php
 * - Normale Produkte → WooCommerce-Standardtemplate
 * 
 * @package Micinterart
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!class_exists('WooCommerce')) {
    exit;
}

// Prüfen ob wir in einer Workshop-Kategorie sind
$is_workshop_category = false;
$queried_object = get_queried_object();

if ($queried_object && isset($queried_object->taxonomy) && $queried_object->taxonomy === 'product_cat') {
    $workshop_slugs = ['workshops', 'atelierkurse', 'kinderworkshops', 'kinderworkshop', 'erwachsenenworkshop', 'erwachsenenworkshops'];
    if (in_array($queried_object->slug, $workshop_slugs)) {
        $is_workshop_category = true;
    }
}

// Shop-Seite: Produkte nach Bereichen getrennt anzeigen
if (function_exists('is_shop') && is_shop()) {
    $shop_template = locate_template('archive-shop-wc.php');
    if ($shop_template) {
        include($shop_template);
        exit;
    }
}

// Werke-Kategorie: Werk-Archiv (Produkte vom Typ 'werk') anzeigen
$is_werke_category = $queried_object
    && isset($queried_object->taxonomy, $queried_object->slug)
    && $queried_object->taxonomy === 'product_cat'
    && $queried_object->slug === 'werke';

if ($is_werke_category) {
    $werk_template = locate_template('archive-werk-wc.php');
    if ($werk_template) {
        include($werk_template);
        exit;
    }
}

if ($is_workshop_category) {
    // Workshop-Kategorie: Verwende unser angepasstes Template
    $workshop_template = locate_template('archive-workshop-wc.php');
    if ($workshop_template) {
        include($workshop_template);
        exit;
    }
} else {
    // Normales Produkt-Archiv: WooCommerce Standard-Loop
    get_header();
    woocommerce_content();
    get_footer();
}
