<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Micinterart_Taxonomies {
    public static function register() {
        register_taxonomy( 'serie', ['werk'], [
            'label'        => 'Serien',
            'hierarchical' => true,
            'public'       => true,
            'show_in_rest' => true,
        ]);
    }
}