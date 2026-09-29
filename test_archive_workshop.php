<?php
/**
 * Template fuer Workshop-Uebersicht mit Archiv
 * Hero-Card + Weitere-Toggle pro Kategorie
 *
 * @package Micinterart
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active and workshops category exists
if (class_exists('WooCommerce')) {
    $workshops_term = get_term_by('slug', 'workshops', 'product_cat');
    if ($workshops_term) {
        // Redirect to WC category page
        wp_redirect(get_term_link($workshops_term), 301);
        exit;
    }
}

// Fallback: Include the old template if WC not active or category not found
include(get_template_directory() . '/archive-workshop.php');
exit;
