<?php
if (!defined('ABSPATH')) exit;

// Admin-Menü
function mic_gallery_admin_menu() {
    add_menu_page(
        __('Galerie-Übersicht', 'micinterart-gallery'),
        __('Galerie', 'micinterart-gallery'),
        'manage_options',
        'mic_gallery_overview',
        'mic_gallery_admin_page',
        'dashicons-images-alt2',
        25
    );
}
add_action('admin_menu', 'mic_gallery_admin_menu');

// Admin-Seite rendern
function mic_gallery_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Keine Berechtigung.', 'micinterart-gallery'));
    }

    $terms = get_terms([
        'taxonomy' => 'serie',
        'hide_empty' => false,
    ]);

    echo '<div class="wrap mic-gallery-admin">';
    echo '<h1>'.esc_html__('Galerie-Übersicht', 'micinterart-gallery').'</h1>';
    echo '<p>'.esc_html__('Ziehe die Werke innerhalb einer Serie per Drag & Drop in die gewünschte Reihenfolge und speichere anschließend.', 'micinterart-gallery').'</p>';

    if (is_wp_error($terms)) {
        echo '<p>'.esc_html__('Fehler beim Laden der Serien.', 'micinterart-gallery').'</p>';
        echo '</div>';
        return;
    }

    if (empty($terms)) {
        echo '<p>'.esc_html__('Noch keine Serien vorhanden. Lege Serien unter „Werke → Serien“ an.', 'micinterart-gallery').'</p>';
        echo '</div>';
        return;
    }

    echo '<div id="mic-gallery-series-container">';
    foreach ($terms as $term) {
        // Werke dieser Serie laden (mit gespeicherter Reihenfolge)
        $posts = get_posts([
            'post_type' => 'werk',
            'posts_per_page' => -1,
            'tax_query' => [[
                'taxonomy' => 'serie',
                'field' => 'term_id',
                'terms' => (int)$term->term_id,
            ]],
            'meta_key' => '_mic_order_in_serie',
            'orderby' => 'meta_value_num title',
            'order' => 'ASC',
        ]);

        echo '<div class="mic-series-block">';
        echo '<h2>'.esc_html($term->name).'</h2>';
        echo '<ul class="mic-series-list" data-term-id="'.(int)$term->term_id.'">';
        if (!empty($posts)) {
            foreach ($posts as $p) {
                $thumb = get_the_post_thumbnail_url($p->ID, 'thumbnail');
                $thumb_html = $thumb ? '<img src="'.esc_url($thumb).'" alt="" />' : '<div class="no-thumb">Kein Bild</div>';
                echo '<li class="mic-series-item" data-post-id="'.(int)$p->ID.'">';
                echo $thumb_html;
                echo '<span class="mic-title">'.esc_html(get_the_title($p->ID)).'</span>';
                echo '<span class="mic-order">'.(int)get_post_meta($p->ID, '_mic_order_in_serie', true).'</span>';
                echo '</li>';
            }
        } else {
            echo '<li class="mic-series-item mic-empty">'.esc_html__('Keine Werke in dieser Serie.', 'micinterart-gallery').'</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
    echo '</div>';

    echo '<button class="button button-primary" id="mic-save-order">'.esc_html__('Reihenfolge speichern', 'micinterart-gallery').'</button>';
    echo '</div>'; // wrap
}