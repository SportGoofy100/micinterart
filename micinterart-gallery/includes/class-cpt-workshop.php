<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Micinterart_CPT_Workshop {
    public static function register() {
        $labels = [
            'name'          => 'Workshops',
            'singular_name' => 'Workshop',
            'add_new'       => 'Neuen Workshop hinzufügen',
            'edit_item'     => 'Workshop bearbeiten',
            'all_items'     => 'Alle Workshops',
        ];

        $args = [
            'labels'       => $labels,
            'public'       => true,
            'has_archive'  => true,
            'menu_icon'    => 'dashicons-welcome-learn-more',
            'supports'     => ['title','editor','thumbnail','excerpt'],
            'rewrite'      => ['slug' => 'workshops'],
            'show_in_rest' => true,
        ];

        register_post_type( 'workshop', $args );
    }
}