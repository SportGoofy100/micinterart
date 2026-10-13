<?php
/**
 * Publikationen: Medien-Kategorie für PDFs 
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

    // Nur ein Typ: die eigenen Publikationen (Artikel über die Künstlerin stehen als Text im Seiteneditor)
    $term = get_term_by('slug', 'von-ihr', 'publikation_typ');
    if (!$term) {
        wp_insert_term('Meine Publikationen', 'publikation_typ', ['slug' => 'von-ihr']);
    } elseif ($term->name !== 'Meine Publikationen') {
        wp_update_term($term->term_id, 'publikation_typ', ['name' => 'Meine Publikationen']);
    }
    $alt = get_term_by('slug', 'ueber-sie', 'publikation_typ');
    if ($alt) {
        wp_delete_term($alt->term_id, 'publikation_typ');
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

/**
 * Auswahlfeld "Publikation" im Medien-Dialog und in den Anhang-Details,
 * damit PDFs direkt in der Mediathek zugeordnet werden können.
 */
function micinterart_publikation_attachment_field($form_fields, $post) {
    if ($post->post_mime_type !== 'application/pdf') {
        return $form_fields;
    }
    $current = wp_get_object_terms($post->ID, 'publikation_typ', ['fields' => 'slugs']);
    $current = is_wp_error($current) || empty($current) ? '' : $current[0];

    $options = [
        ''          => '— keine —',
        'von-ihr'   => 'Meine Publikationen',
    ];
    $html = '<select name="attachments[' . (int) $post->ID . '][publikation_typ]">';
    foreach ($options as $slug => $label) {
        $html .= '<option value="' . esc_attr($slug) . '"' . selected($current, $slug, false) . '>' . esc_html($label) . '</option>';
    }
    $html .= '</select>';

    $form_fields['publikation_typ'] = [
        'label' => 'Publikation',
        'input' => 'html',
        'html'  => $html,
    ];
    return $form_fields;
}
add_filter('attachment_fields_to_edit', 'micinterart_publikation_attachment_field', 10, 2);

function micinterart_publikation_attachment_save($post, $attachment) {
    if (isset($attachment['publikation_typ'])) {
        $slug = sanitize_key($attachment['publikation_typ']);
        if ($slug === '') {
            wp_set_object_terms($post['ID'], [], 'publikation_typ');
        } elseif ($slug === 'von-ihr') {
            wp_set_object_terms($post['ID'], [$slug], 'publikation_typ');
        }
    }
    return $post;
}
add_filter('attachment_fields_to_save', 'micinterart_publikation_attachment_save', 10, 2);

/**
 * Sammelaktionen in der Mediathek (Listenansicht -> "Mehrfachauswahl").
 */
function micinterart_publikation_bulk_actions($actions) {
    $actions['publikation_von_ihr'] = 'Zuordnen: Meine Publikationen';
    $actions['publikation_keine']   = 'Publikation: Zuordnung entfernen';
    return $actions;
}
add_filter('bulk_actions-upload', 'micinterart_publikation_bulk_actions');

function micinterart_publikation_handle_bulk($redirect, $action, $ids) {
    $map = [
        'publikation_von_ihr' => 'von-ihr',
        'publikation_keine'   => '',
    ];
    if (!isset($map[$action]) || !current_user_can('upload_files')) {
        return $redirect;
    }
    foreach ($ids as $id) {
        if (get_post_mime_type($id) === 'application/pdf') {
            wp_set_object_terms((int) $id, $map[$action] === '' ? [] : [$map[$action]], 'publikation_typ');
        }
    }
    return add_query_arg('publikation_updated', count($ids), $redirect);
}
add_filter('handle_bulk_actions-upload', 'micinterart_publikation_handle_bulk', 10, 3);

function micinterart_publikation_bulk_notice() {
    if (!empty($_GET['publikation_updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Publikationen aktualisiert (' . (int) $_GET['publikation_updated'] . ' Dateien).</p></div>';
    }
}
add_action('admin_notices', 'micinterart_publikation_bulk_notice');
