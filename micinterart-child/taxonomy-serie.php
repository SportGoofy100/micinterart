<?php
/**
 * Template für Taxonomie-Archiv: Serie
 * 
 * @package Micinterart
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$is_en = micinterart_is_english();
$term = get_queried_object();
?>

<main id="primary" class="site-main serie-archive">

    <header class="page-header serie-header">
        <h1 class="page-title">
            <?php
            printf(
                $is_en ? 'Series: %s' : __('Serie: %s', 'micinterart'),
                '<span class="serie-name">' . esc_html($term->name) . '</span>'
            );
            ?>
        </h1>
        
        <?php
        // Serien-Beschreibung
        if (!empty($term->description)) :
            ?>
            <div class="serie-description">
                <?php echo wp_kses_post(wpautop($term->description)); ?>
            </div>
        <?php endif; ?>
        
        <?php
        // Anzahl der Werke in dieser Serie
        $count = $term->count;
        if ($count > 0) :
            ?>
            <div class="serie-count">
                <?php
                $series_count_text = $is_en
                    ? sprintf(_n('%s artwork in this series', '%s artworks in this series', $count, 'micinterart'), '<strong>' . number_format_i18n($count) . '</strong>')
                    : sprintf(_n('%s Werk in dieser Serie', '%s Werke in dieser Serie', $count, 'micinterart'), '<strong>' . number_format_i18n($count) . '</strong>');
                echo wp_kses_post($series_count_text);
                ?>
            </div>
        <?php endif; ?>
    </header>

    <?php if (have_posts()) : ?>
        
        <div class="werke-serie-grid">
            
            <?php
            while (have_posts()) :
                the_post();
                $post_id = get_the_ID();
                ?>
                
                <article id="post-<?php echo esc_attr($post_id); ?>" <?php post_class('werk-serie-item'); ?>>
                    
                    <a href="<?php the_permalink(); ?>" class="werk-link">
                        
                        <?php if (has_post_thumbnail()) : ?>
                            <div class="werk-thumbnail">
                                <?php
                                the_post_thumbnail('medium', [
                                    'loading' => 'lazy',
                                    'alt' => get_the_title()
                                ]);
                                ?>
                            </div>
                        <?php else : ?>
                            <div class="werk-thumbnail werk-thumbnail-placeholder">
                                <span class="dashicons dashicons-format-image"></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="werk-content">
                            <h2 class="werk-title"><?php the_title(); ?></h2>
                            
                            <?php
                            // Meta-Informationen
                            $year = micinterart_get_translated_meta($post_id, '_werk_year', true);
                            $dimensions = micinterart_get_translated_meta($post_id, '_werk_dimensions', true);
                            $materials = micinterart_get_translated_meta($post_id, '_werk_materials', true);
                            
                            if ($year || $dimensions || $materials) :
                                ?>
                                <div class="werk-meta">
                                    <?php if ($year) : ?>
                                        <span class="werk-year"><?php echo esc_html($year); ?></span>
                                    <?php endif; ?>
                                    
                                    <?php if ($dimensions) : ?>
                                        <span class="werk-dimensions"><?php echo esc_html($dimensions); ?></span>
                                    <?php endif; ?>
                                    
                                    <?php if ($materials) : ?>
                                        <span class="werk-materials"><?php echo esc_html($materials); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php
                            // Excerpt anzeigen (falls vorhanden)
                            if (has_excerpt()) :
                                ?>
                                <div class="werk-excerpt">
                                    <?php the_excerpt(); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                    </a>
                    
                </article>
                
            <?php endwhile; ?>
            
        </div>

        <?php
        // Pagination
        the_posts_pagination([
            'mid_size'           => 2,
            'prev_text'          => __('&laquo; Zurück', 'micinterart'),
            'next_text'          => __('Weiter &raquo;', 'micinterart'),
            'screen_reader_text' => __('Serien Navigation', 'micinterart'),
        ]);
        ?>

    <?php else : ?>
        
        <div class="no-results">
            <p><?php echo esc_html($is_en ? 'No artworks found in this series.' : __('Keine Werke in dieser Serie gefunden.', 'micinterart')); ?></p>
            <a href="<?php echo esc_url(get_post_type_archive_link('werk')); ?>" class="button">
                <?php echo esc_html($is_en ? 'View all artworks' : __('Alle Werke ansehen', 'micinterart')); ?>
            </a>
        </div>
        
    <?php endif; ?>
    
    <?php
    // Navigation zu anderen Serien
    $all_series = get_terms([
        'taxonomy'   => 'serie',
        'hide_empty' => true,
        'exclude'    => [$term->term_id],
    ]);
    
    if ($all_series && !is_wp_error($all_series) && !empty($all_series)) :
        ?>
        <aside class="other-series">
            <h3><?php echo esc_html($is_en ? 'Other series' : __('Weitere Serien', 'micinterart')); ?></h3>
            <ul class="series-list">
                <?php foreach ($all_series as $other_serie) : ?>
                    <li>
                        <a href="<?php echo esc_url(get_term_link($other_serie)); ?>">
                            <?php echo esc_html($other_serie->name); ?>
                            <span class="serie-count">(<?php echo esc_html($other_serie->count); ?>)</span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>
    <?php endif; ?>

</main>

<?php
get_footer();