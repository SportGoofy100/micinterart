<?php
/**
 * Workshop-Thema → Workshop Sync
 *
 * Minimal-invasiv:
 * - Wenn ein Workshop Themen (CPT: workshop_thema) hat, wird _workshop_datum
 *   automatisch auf das Datum des naechsten zukuenftigen Themas gesetzt.
 * - Wenn kein zukuenftiges Thema existiert: _workshop_datum wird geleert.
 *
 * Benoetigt Meta:
 * - Thema: _thema_workshop_id (Parent workshop ID)
 * - Thema: _thema_datum (Y-m-d)
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('micinterart_get_next_thema_date_for_workshop')) {
    function micinterart_get_next_thema_date_for_workshop($workshop_id) {
        $workshop_id = (int) $workshop_id;
        if ($workshop_id <= 0) return '';

        $heute = date('Y-m-d');

        $posts = get_posts([
            'post_type'      => 'workshop_thema',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'meta_key'       => '_thema_datum',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => '_thema_workshop_id',
                    'value'   => $workshop_id,
                    'compare' => '=',
                ],
                [
                    'key'     => '_thema_datum',
                    'value'   => $heute,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ],
            ],
        ]);

        if (empty($posts)) return '';

        $thema_id = $posts[0]->ID;
        $datum = get_post_meta($thema_id, '_thema_datum', true);
        return is_string($datum) ? trim($datum) : '';
    }
}

if (!function_exists('micinterart_sync_workshop_datum_from_themen')) {
    function micinterart_sync_workshop_datum_from_themen($workshop_id) {
        $workshop_id = (int) $workshop_id;
        if ($workshop_id <= 0 || get_post_type($workshop_id) !== 'workshop') return;

        $next_date = micinterart_get_next_thema_date_for_workshop($workshop_id);

        if ($next_date !== '') {
            update_post_meta($workshop_id, '_workshop_datum', $next_date);
        } else {
            // Gewuenscht: leeren
            update_post_meta($workshop_id, '_workshop_datum', '');
        }
    }
}

add_action('save_post_workshop_thema', function($thema_id, $post, $update) {
    if (wp_is_post_revision($thema_id)) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    $workshop_id = (int) get_post_meta($thema_id, '_thema_workshop_id', true);
    if ($workshop_id > 0) {
        micinterart_sync_workshop_datum_from_themen($workshop_id);
    }
}, 10, 3);

add_action('before_delete_post', function($post_id) {
    if (get_post_type($post_id) !== 'workshop_thema') return;

    $workshop_id = (int) get_post_meta($post_id, '_thema_workshop_id', true);
    if ($workshop_id > 0) {
        micinterart_sync_workshop_datum_from_themen($workshop_id);
    }
}, 10);
