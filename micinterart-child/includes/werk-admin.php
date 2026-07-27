<?php
/**
 * Werk CPT: Admin-Spalten, Sortierung, Gedicht-Excerpt
 *
 * @package Micinterart
 */

/**
 * Admin: Custom Columns für CPT Werk
 */
function micinterart_werk_custom_columns($columns) {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['thumbnail'] = __('Bild','micinterart');
            $new_columns['dimensions'] = __('Maße','micinterart');
            $new_columns['year'] = __('Jahr','micinterart');
            $new_columns['serie'] = __('Serie','micinterart');
        }
    }
    return $new_columns;
}
add_filter('manage_werk_posts_columns', 'micinterart_werk_custom_columns');

/**
 * Admin: Custom Column Content für CPT Werk
 */
function micinterart_werk_custom_column_content($column, $post_id) {
    switch ($column) {
        case 'thumbnail':
            echo has_post_thumbnail($post_id) ? get_the_post_thumbnail($post_id,[50,50]) : '<span style="color:#999;">—</span>';
            break;
        case 'dimensions':
            $dimensions = get_post_meta($post_id,'_werk_dimensions',true);
            echo $dimensions ? esc_html($dimensions) : '<span style="color:#999;">—</span>';
            break;
        case 'year':
            $year = get_post_meta($post_id,'_werk_year',true);
            echo $year ? esc_html($year) : '<span style="color:#999;">—</span>';
            break;
        case 'serie':
            $terms = get_the_terms($post_id,'serie');
            if ($terms && !is_wp_error($terms)) {
                $serie_names = array_map(function($term) {
                    return '<a href="'.get_term_link($term).'">'.esc_html($term->name).'</a>';
                },$terms);
                echo implode(', ',$serie_names);
            } else {
                echo '<span style="color:#999;">—</span>';
            }
            break;
    }
}
add_action('manage_werk_posts_custom_column', 'micinterart_werk_custom_column_content', 10, 2);

/**
 * Admin: Sortierbare Columns
 */
function micinterart_werk_sortable_columns($columns) {
    $columns['year'] = 'year';
    return $columns;
}
add_filter('manage_edit-werk_sortable_columns', 'micinterart_werk_sortable_columns');

/**
 * Admin: Custom Sorting Query
 */
function micinterart_werk_custom_orderby($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    $orderby = $query->get('orderby');
    if ('year' === $orderby) {
        $query->set('meta_key','_werk_year');
        $query->set('orderby','meta_value_num');
    }
}
add_action('pre_get_posts', 'micinterart_werk_custom_orderby');

/**
 * Contact Form 7: Workshop-Titel dynamisch einfügen
 */
add_action('wpcf7_init', function() {
    if (function_exists('wpcf7_add_form_tag')) {
        wpcf7_add_form_tag('CF7_get_post_var', function($tag) {
            $key = $tag->get_option('key','',true);
            if ($key === 'post_title') {
                return get_the_title();
            }
            return '';
        });
    }
});

/**
 * Eigene Auszug-Funktion für CPT "gedicht"
 */
function micinterart_gedicht_excerpt($length = 50) {
    $content = get_the_content();
    $trimmed = wp_trim_words($content,$length,'…');
    return nl2br($trimmed);
}
