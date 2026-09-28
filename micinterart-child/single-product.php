<?php
/**
 * Template für einzelne WooCommerce-Produkte
 * 
 * Diese Datei leitet zu den spezifischen Templates weiter:
 * - Workshop-Produkte → single-product-workshop.php
 * - Normale Produkte → WooCommerce-Standardtemplate
 * 
 * @package Micinterart
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!class_exists('WooCommerce')) {
    return;
}

$product_id = get_the_ID();
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

if ($is_workshop) {
    // Workshop-Produkt: Verwende unser angepasstes Template
    wc_get_template('single-product-workshop.php');
} else {
    // Normales Produkt: Verwende WooCommerce-Standardtemplate
    wc_get_template('single-product-default.php');
}
