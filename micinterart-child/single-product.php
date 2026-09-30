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
    exit;
}

$product_id = get_the_ID();
$product = wc_get_product($product_id);
$is_workshop = function_exists('micinterart_wc_is_workshop_product')
    && micinterart_wc_is_workshop_product($product);

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
    // Normales Produkt mit dem WooCommerce-Standardlayout rendern
    micinterart_render_wc_default_single_product();
}
