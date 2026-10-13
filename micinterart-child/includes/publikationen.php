<?php
/**
 * Publikationen: Medien-Kategorie für PDFs (von ihr / über sie)
 *
 * PDFs in der Mediathek bekommen die Kategorie "Publikationen"; die Seite
 * "Publikationen" (page-publikationen.php) listet sie automatisch.
 */

if (!defined('ABSPATH')) {
    exit;
}

function micinterart_register_publikation_taxonomy() {
    register_taxonomy('publikation_typ', 'attachment', [
        'labels' => [
            'name'          => 'Publikationen',
            'singular_name' => 'Publikationstyp',
            'menu_name'     => 'Publikationen',
            'all_items'     => 'Alle Publikationstypen',
            'edit_item'     => 'Publikationstyp bearbeiten',
            'add_new_item'  => 'Publikationstyp hinzufügen',
        ],
        'public'            => false,
        'show_ui'           => true,
        'show_in_menu'      => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'hierarchical'      => true,
        'rewrite'           => false,
    ]);

    $terms = [
        'von-ihr'   => 'Publikationen von ihr',
        'ueber-sie' => 'Publikationen über sie',
    ];
    foreach ($terms as $slug => $name) {
        if (!term_exists($slug, 'publikation_typ')) {
            wp_insert_term($name, 'publikation_typ', ['slug' => $slug]);
        }
    }
}
add_action('init', 'micinterart_register_publikation_taxonomy');

/**
 * PDFs eines Typs holen (Reihenfolge: manuell, dann Titel).
 */
function micinterart_get_publikationen($slug) {
    return get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'application/pdf',
        'posts_per_page' => -1,
        'orderby'        => ['menu_order' => 'ASC', 'title' => 'ASC'],
        'tax_query'      => [[
            'taxonomy' => 'publikation_typ',
            'field'    => 'slug',
            'terms'    => $slug,
        ]],
    ]);
}
