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
    $workshop_slugs = ['workshops', 'atelierkurse', 'kinderworkshops'];
    if (in_array($queried_object->slug, $workshop_slugs)) {
        $is_workshop_category = true;
    }
}

if ($is_workshop_category) {
    // Workshop-Kategorie: Verwende unser angepasstes Template
    wc_get_template('archive-workshop-wc.php');
} else {
    // Normales Produkt-Archiv: Verwende WooCommerce-Standardtemplate
    wc_get_template('archive-product-default.php');
}
