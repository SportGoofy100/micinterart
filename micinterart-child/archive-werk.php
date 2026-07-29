<?php
/**
 * Template für Archivseite: Werke (CPT: werk)
 * 
 * @package Micinterart
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
$is_en = micinterart_is_english();
?>

<style>
/* Werke Archive - Modernes Gallery Design */
.werke-archive {
    background: #fafafa;
    min-height: 100vh;
    padding: 40px 20px 60px;
}

.page-header {
    text-align: center;
    max-width: 1400px;
    margin: 0 auto 50px;
    padding: 40px 20px;
    background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%);
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.page-title {
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    font-size: 3.5em;
    margin: 0;
    letter-spacing: 3px;
    color: #d4af37;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
}

.archive-description {
    color: #f5f5f5;
    font-size: 1.1em;
    margin-top: 15px;
    line-height: 1.6;
}

/* Grid Layout */
.werke-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 30px;
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Werk Card */
.werk-item {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transition: all 0.4s ease;
    position: relative;
}

.werk-item:hover {
    transform: translateY(-10px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.2);
}

.werk-link {
    display: block;
    text-decoration: none;
    color: inherit;
}

/* Thumbnail */
.werk-thumbnail {
    position: relative;
    width: 100%;
    height: 130px;
    overflow: hidden;
    background: #f0f0f0;
}

.werk-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center top;
    transition: transform 0.5s ease;
}

.werk-item:hover .werk-thumbnail img {
    transform: scale(1.08);
}

/* Hover-Overlay mit CTA */
.werk-thumbnail-overlay {
    position: absolute;
    inset: 0;
    background: rgba(28, 28, 28, 0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.35s ease;
}

.werk-item:hover .werk-thumbnail-overlay {
    opacity: 1;
}

.werk-cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 26px;
    background: #d4af37;
    color: #1a1a1a;
    font-weight: 700;
    font-size: 0.95em;
    letter-spacing: 0.5px;
    border-radius: 6px;
    text-decoration: none;
    box-shadow: 0 4px 16px rgba(0,0,0,0.3);
    transform: translateY(8px);
    transition: transform 0.35s ease, background 0.2s ease;
    pointer-events: none; /* Klick geht an .werk-link */
}

.werk-item:hover .werk-cta-btn {
    transform: translateY(0);
}

/* Placeholder */
.werk-thumbnail-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #e8e8e8 0%, #f5f5f5 100%);
}

.werk-thumbnail-placeholder .dashicons {
    font-size: 80px;
    width: 80px;
    height: 80px;
    color: #ccc;
}

/* Content */
.werk-content {
    padding: 20px;
}

.werk-title {
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    font-size: 1.8em;
    margin: 0 0 15px 0;
    color: #2c2c2c;
    letter-spacing: 1px;
    transition: color 0.3s ease;
}

.werk-item:hover .werk-title {
    color: #d4af37;
}

/* Meta Informationen */
.werk-meta {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 15px;
    font-size: 0.9em;
    color: #666;
}

.werk-meta span {
    display: flex;
    align-items: center;
    gap: 8px;
}

.werk-meta span::before {
    content: '';
    width: 4px;
    height: 4px;
    background: #d4af37;
    border-radius: 50%;
}

.werk-year {
    font-weight: 600;
    color: #2c2c2c;
}

.werk-dimensions {
    color: #555;
}

.werk-materials {
    color: #666;
    font-style: italic;
}

/* Serie Badges */
.werk-series {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 15px;
}

.werk-serie-badge {
    display: inline-block;
    padding: 5px 12px;
    background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%);
    color: #d4af37;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 600;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
}

.werk-item:hover .werk-serie-badge {
    background: linear-gradient(135deg, #d4af37 0%, #f4e5c3 100%);
    color: #2c2c2c;
    transform: scale(1.05);
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin-top: 60px;
    padding: 20px;
}

.pagination .nav-links {
    display: flex;
    gap: 10px;
    align-items: center;
}

.pagination a,
.pagination span.current {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 45px;
    height: 45px;
    padding: 0 15px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.pagination a {
    background: white;
    color: #2c2c2c;
    border: 2px solid #e0e0e0;
}

.pagination a:hover {
    background: #d4af37;
    color: white;
    border-color: #d4af37;
    transform: translateY(-2px);
}

.pagination span.current {
    background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%);
    color: #d4af37;
    border: 2px solid #d4af37;
}

/* No Results */
.no-results {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: 12px;
    max-width: 600px;
    margin: 0 auto;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.no-results p {
    font-size: 1.3em;
    color: #666;
    margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .page-title {
        font-size: 2.5em;
    }
    
    .werke-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .werk-title {
        font-size: 1.5em;
    }
}

@media (max-width: 480px) {
    .werke-grid {
        grid-template-columns: 1fr;
    }
}

/* Animation */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.werk-item {
    animation: fadeInUp 0.6s ease-out backwards;
}

.werk-item:nth-child(1) { animation-delay: 0.1s; }
.werk-item:nth-child(2) { animation-delay: 0.2s; }
.werk-item:nth-child(3) { animation-delay: 0.3s; }
.werk-item:nth-child(4) { animation-delay: 0.4s; }
.werk-item:nth-child(5) { animation-delay: 0.5s; }
.werk-item:nth-child(6) { animation-delay: 0.6s; }

/* Representation Badge */
.werk-represented-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    align-self: flex-start;
    padding: 5px 10px;
    background: #fdfaf2;
    border: 1px solid #d4af37;
    color: #b89020;
    border-radius: 6px;
    font-size: 0.8em;
    font-weight: 600;
    letter-spacing: 0.3px;
    box-shadow: 0 2px 5px rgba(212,175,55,0.08);
}
</style>

<main id="primary" class="site-main werke-archive">
    
    <header class="page-header">
        <h1 class="page-title"><?php echo $is_en ? 'Artworks' : esc_html__('Meine Werke', 'micinterart'); ?></h1>
        
        <?php
        // Optional: Beschreibung des Post Type Archives
        $post_type_obj = get_post_type_object('werk');
        if ($post_type_obj && !empty($post_type_obj->description)) {
            echo '<div class="archive-description">' . wp_kses_post($post_type_obj->description) . '</div>';
        }
        ?>
    </header>

    <?php if (have_posts()) : ?>
        
        <div class="werke-grid">
            <?php
            while (have_posts()) :
                the_post();
                ?>
                
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
                                <div class="werk-thumbnail-overlay">
                                    <span class="werk-cta-btn"><?php echo $is_en ? '🎨 View Artwork' : '🎨 Werk ansehen'; ?></span>
                                </div>
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
                            $year = micinterart_get_translated_meta(get_the_ID(), '_werk_year', true);
                            $dimensions = micinterart_get_translated_meta(get_the_ID(), '_werk_dimensions', true);
                            $materials = micinterart_get_translated_meta(get_the_ID(), '_werk_materials', true);
                            $represented = micinterart_get_translated_meta(get_the_ID(), '_werk_represented', true);
                            
                            if ($year || $dimensions || $materials || $represented) :
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
                            <?php endif; ?>
                            
                            <?php
                            // Serie anzeigen
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
        // Pagination
        the_posts_pagination([
            'mid_size'           => 2,
            'prev_text'          => $is_en ? '&laquo; Prev' : __('&laquo; Zurück', 'micinterart'),
            'next_text'          => $is_en ? 'Next &raquo;' : __('Weiter &raquo;', 'micinterart'),
            'screen_reader_text' => $is_en ? 'Artworks Navigation' : __('Werke Navigation', 'micinterart'),
        ]);
        ?>

    <?php else : ?>
        
        <div class="no-results">
            <p><?php echo $is_en ? 'No artworks found.' : esc_html__('Keine Werke gefunden.', 'micinterart'); ?></p>
        </div>
        
    <?php endif; ?>

</main>

<?php
get_footer();
