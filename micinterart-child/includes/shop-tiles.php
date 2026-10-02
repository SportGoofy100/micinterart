<?php
/**
 * Shop-Startseite: Bilder für die drei Kacheln
 *
 * Die Bilder lassen sich im Customizer festlegen:
 * Design → Anpassen → Shop-Kacheln. Solange keins gewählt ist,
 * zeigt die Kachel einen Platzhalter.
 *
 * @package Micinterart
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Kachel-Schlüssel => Beschriftung im Customizer
 */
function micinterart_shop_tile_labels() {
    return [
        'workshops' => 'Workshops',
        'werke'     => 'Werke',
        'atelier'   => 'Atelier-Shop',
    ];
}

function micinterart_shop_tiles_customize_register($wp_customize) {
    $wp_customize->add_section('micinterart_shop_tiles', [
        'title'       => 'Shop-Kacheln',
        'priority'    => 160,
        'description' => 'Bilder für die drei Kacheln auf der Shop-Startseite. Ohne Bild wird ein Platzhalter angezeigt. Empfohlen: Querformat, mindestens 1200 × 800 px.',
    ]);

    foreach (micinterart_shop_tile_labels() as $key => $label) {
        $setting_id = 'micinterart_shop_tile_' . $key;

        $wp_customize->add_setting($setting_id, [
            'default'           => 0,
            'sanitize_callback' => 'absint',
        ]);

        $wp_customize->add_control(new WP_Customize_Media_Control($wp_customize, $setting_id, [
            'label'     => 'Bild: ' . $label,
            'section'   => 'micinterart_shop_tiles',
            'mime_type' => 'image',
        ]));
    }
}
add_action('customize_register', 'micinterart_shop_tiles_customize_register');

/**
 * URL des Kachelbilds oder leerer String, wenn keins gewählt ist
 */
function micinterart_shop_tile_image($key) {
    $attachment_id = absint(get_theme_mod('micinterart_shop_tile_' . $key, 0));
    if (!$attachment_id) {
        return '';
    }

    $url = wp_get_attachment_image_url($attachment_id, 'large');
    return $url ? $url : '';
}
