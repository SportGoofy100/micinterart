<?php
/**
 * Template für Archivseite: Werke (WC-Produkte vom Typ 'werk')
 * 
 * Ersetzt die alte archive-werk.php für das CPT 'werk'
 * 
 * @package Micinterart
 */

if (!defined('ABSPATH')) {
    exit;
}

// WC-Produkte vom Typ 'werk' abfragen
$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
$werk_query = new WP_Query([
    'post_type' => 'product',
    'posts_per_page' => 12,
    'paged' => $paged,
    'tax_query' => [[
        'taxonomy' => 'product_type',
        'field' => 'slug',
        'terms' => 'werk',
    ]],
]);

get_header();
$is_en = function_exists('micinterart_is_english') ? micinterart_is_english() : (function_exists('pll_current_language') && pll_current_language() === 'en');

// Styles: assets/css/werke-archive.css (wird in functions.php eingebunden)
?>

<main id="primary" class="site-main werke-archive">
    
    <header class="page-header">
        <h1 class="page-title"><?php echo $is_en ? 'Artworks' : esc_html__('Meine Werke', 'micinterart'); ?></h1>
        
        <?php
        // Beschreibung - wie in der alten archive-werk.php
        $post_type_obj = get_post_type_object('product');
        $description = '';
        if ($post_type_obj && !empty($post_type_obj->description)) {
            $description = $post_type_obj->description;
        }
        if (empty($description)) {
            $description = $is_en ? 'Discover my artistic creations' : 'Entdecke meine künstlerischen Schöpfungen';
        }
        echo '<div class="archive-description">' . wp_kses_post($description) . '</div>';
        ?>
    </header>

    <?php micinterart_shop_subnav('werke'); ?>

    <?php if ($werk_query->have_posts()) : ?>
        
        <div class="werke-grid">
            <?php while ($werk_query->have_posts()) : $werk_query->the_post(); ?>
                
                <article id="post-<?php the_ID(); ?>" <?php post_class('werk-item'); ?>>
                    
                    <a href="<?php the_permalink(); ?>" class="werk-link">
                        
                        <?php if (has_post_thumbnail()) : ?>
                            <div class="werk-thumbnail">
                                <?php 
                                the_post_thumbnail('medium_large', [
                                    'loading' => 'lazy',
                                    'alt' => get_the_title()
                                ]);
                                ?>
                                <?php $status_label = micinterart_werk_status_label(get_the_ID()); ?>
                                <?php if ($status_label !== '') : ?>
                                    <span class="shop-item-badge"><?php echo esc_html($status_label); ?></span>
                                <?php endif; ?>
                                <div class="werk-thumbnail-overlay">
                                    <span class="werk-cta-btn"><?php echo $is_en ? '🎨 View Artwork' : '🎨 Werk ansehen'; ?></span>
                                </div>
                            </div>
                        <?php else : ?>
                            <div class="werk-thumbnail werk-thumbnail-placeholder">
                                <span class="dashicons dashicons-format-image"></span>
                                <?php $status_label = micinterart_werk_status_label(get_the_ID()); ?>
                                <?php if ($status_label !== '') : ?>
                                    <span class="shop-item-badge"><?php echo esc_html($status_label); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="werk-content">
                            <h2 class="werk-title"><?php the_title(); ?></h2>
                            <div class="shop-item-price"><?php echo micinterart_werk_price_html(wc_get_product(get_the_ID())); ?></div>
                            
                            <?php
                            // Meta-Informationen
                            $year = get_post_meta(get_the_ID(), '_werk_year', true);
                            $dimensions = get_post_meta(get_the_ID(), '_werk_dimensions', true);
                            $materials = get_post_meta(get_the_ID(), '_werk_materials', true);
                            $represented = get_post_meta(get_the_ID(), '_werk_represented', true);
                            $exhibited = get_post_meta(get_the_ID(), '_werk_exhibited', true);
                            
                            if ($year || $dimensions || $materials || $represented || $exhibited) :
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
                                <?php if ($represented) : ?>
                                    <div class="werk-meta-status">
                                        <span class="werk-represented-badge">
                                            👑 <?php echo $is_en ? 'Represented by ' : 'Vertreten durch '; ?><?php echo esc_html($represented); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($exhibited) : ?>
                                    <div class="werk-meta-status">
                                        <span class="werk-exhibited-badge">
                                            🏛️ <?php echo $is_en ? 'Exhibited at ' : 'Ausgestellt bei '; ?><?php echo esc_html($exhibited); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php
                            // Serie anzeigen (falls taxonomy 'serie' existiert)
                            $series = get_the_terms(get_the_ID(), 'serie');
                            if ($series && !is_wp_error($series)) :
                                ?>
                                <div class="werk-series">
                                    <?php foreach ($series as $serie) : ?>
                                        <span class="werk-serie-badge"><?php echo esc_html($serie->name); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                    </a>
                    
                </article>
                
            <?php endwhile; ?>
        </div>

        <?php
        wp_reset_postdata();

        // Pagination anzeigen (auf Basis der Werk-Query, nicht der Hauptquery)
        if ($werk_query->max_num_pages > 1) {
            echo '<div class="pagination">';
            echo paginate_links([
                'base'      => trailingslashit(get_pagenum_link(1)) . '%_%',
                'format'    => 'page/%#%/',
                'current'   => max(1, (int) $paged),
                'total'     => (int) $werk_query->max_num_pages,
                'mid_size'  => 2,
                'prev_text' => $is_en ? '&laquo; Prev' : __('&laquo; Zurück', 'micinterart'),
                'next_text' => $is_en ? 'Next &raquo;' : __('Weiter &raquo;', 'micinterart'),
            ]);
            echo '</div>';
        }
        ?>

    <?php else : ?>
        
        <div class="no-results">
            <p><?php echo $is_en ? 'No artworks found.' : esc_html__('Keine Werke gefunden.', 'micinterart'); ?></p>
        </div>
        
    <?php endif; ?>
    
    <?php wp_reset_query(); ?>

</main>

<?php
get_footer();
