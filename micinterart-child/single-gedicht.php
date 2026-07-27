<?php
/**
 * Template für einzelne Gedichte (CPT: gedicht)
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
/* Gedicht Single - Elegantes Zettel-Design */
.gedicht-single-wrapper {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
    padding: 60px 20px;
}

.gedicht-container {
    max-width: 800px;
    margin: 0 auto;
}

.gedicht {
    background: linear-gradient(to bottom, #ffffff 0%, #f8f9fa 100%);
    padding: 50px 40px;
    border-radius: 3px;
    box-shadow: 
        0 10px 40px rgba(0,0,0,0.15),
        0 20px 60px rgba(0,0,0,0.1),
        inset 0 1px 0 rgba(255,255,255,0.6);
    position: relative;
    border: 1px solid #e0e0e0;
    margin-bottom: 40px;
}

/* Reißzwecke / Pin oben */
.gedicht::before {
    content: '';
    position: absolute;
    top: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 20px;
    height: 20px;
    background: radial-gradient(circle, #2c3e50 0%, #1a252f 100%);
    border-radius: 50%;
    box-shadow: 
        0 3px 6px rgba(0,0,0,0.4),
        inset 0 -2px 3px rgba(0,0,0,0.4),
        inset 0 2px 2px rgba(255,255,255,0.4);
    z-index: 2;
}

/* Schatten der Reißzwecke */
.gedicht::after {
    content: '';
    position: absolute;
    top: -4px;
    left: 50%;
    transform: translateX(-50%);
    width: 15px;
    height: 4px;
    background: rgba(0,0,0,0.25);
    border-radius: 50%;
    filter: blur(3px);
}

.gedicht-header {
    text-align: center;
    margin-bottom: 40px;
    padding-bottom: 30px;
    border-bottom: 2px solid #d4c5a9;
}

.gedicht-titel {
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    font-size: 2.8em;
    color: #2c2c2c;
    margin: 0 0 15px 0;
    letter-spacing: 2px;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.05);
}

.gedicht-meta {
    color: #666;
    font-size: 0.9em;
    font-style: italic;
}

/* Werk-Hinweis oben */
.gedicht-werk-hinweis {
    background: #f0f4f8;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
    border-left: 4px solid #3498db;
}

.werk-thumbnail-preview {
    margin-bottom: 15px;
}

.werk-thumbnail-preview img {
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    max-width: 200px;
    height: auto;
}

.werk-info {
    font-size: 1.1em;
    color: #444;
}

.werk-link {
    color: #3498db;
    text-decoration: none;
    transition: color 0.3s ease;
}

.werk-link:hover {
    color: #2980b9;
    text-decoration: underline;
}

/* Gedicht-Text */
.gedicht-text {
    font-family: 'Georgia', serif;
    font-size: 1.2em;
    line-height: 1.8;
    color: #2c2c2c;
    padding: 30px 0;
    text-align: left;
    white-space: pre-line;
}

.gedicht-text p {
    margin: 0 0 1.5em 0;
}

.gedicht-text p:last-child {
    margin-bottom: 0;
}

/* Footer */
.gedicht-footer {
    margin-top: 40px;
    padding-top: 30px;
    border-top: 2px dashed #d4c5a9;
}

.gedicht-werk-cta {
    margin-bottom: 30px;
}

.gedicht-werk-cta .button {
    display: inline-block;
    padding: 12px 30px;
    background: #3498db;
    color: white;
    text-decoration: none;
    border-radius: 30px;
    font-weight: 600;
    font-size: 1em;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
}

.gedicht-werk-cta .button:hover {
    background: #2980b9;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
}

/* Share Buttons */
.gedicht-share {
    text-align: center;
    padding: 20px 0;
}

.share-label {
    margin-right: 10px;
    color: #666;
    font-weight: 600;
}

.share-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #f0f0f0;
    color: #333;
    margin: 0 5px;
    transition: all 0.3s ease;
    text-decoration: none;
}

.share-button:hover {
    transform: translateY(-3px);
}

.share-facebook:hover {
    background: #3b5998;
    color: white;
}

.share-twitter:hover {
    background: #1da1f2;
    color: white;
}

/* Navigation */
.gedicht-navigation {
    margin: 30px 0;
    padding: 20px 0;
}

.nav-links {
    display: flex;
    justify-content: center;
    gap: 30px;
    flex-wrap: wrap;
}

.nav-links a {
    display: inline-block;
    padding: 10px 20px;
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    color: #2c2c2c;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.nav-links a:hover {
    background: #2c2c2c;
    color: white;
    border-color: #2c2c2c;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
}

.nav-subtitle {
    font-size: 0.95em;
}

/* Zurück Link */
.gedicht-back-to-archive {
    margin-top: 30px;
}

.back-link {
    display: inline-block;
    color: #666;
    text-decoration: none;
    font-size: 1em;
    transition: color 0.3s ease;
    padding: 8px 16px;
}

.back-link:hover {
    color: #2c2c2c;
    text-decoration: underline;
}

/* Responsive */
@media (max-width: 768px) {
    .gedicht-single-wrapper {
        padding: 30px 15px;
    }
    
    .gedicht {
        padding: 30px 25px;
    }
    
    .gedicht-titel {
        font-size: 2em;
    }
    
    .gedicht-text {
        font-size: 1.1em;
        line-height: 1.7;
    }
    
    .nav-links {
        flex-direction: column;
        gap: 15px;
    }
}

/* Animationen */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.gedicht {
    animation: fadeInUp 0.6s ease-out;
}
</style>

<main id="primary" class="site-main gedicht-single-wrapper">

    <div class="gedicht-container">
        <?php
        while (have_posts()) :
            the_post();
            $post_id = get_the_ID();
            ?>

            <article id="post-<?php echo esc_attr($post_id); ?>" <?php post_class('gedicht'); ?>>

                <header class="gedicht-header">
                    <h1 class="gedicht-titel"><?php the_title(); ?></h1>
                    
                    <?php
                    // Optional: Autor oder Datum anzeigen
                    $show_meta = apply_filters('micinterart_show_gedicht_meta', false);
                    if ($show_meta) :
                        ?>
                        <div class="gedicht-meta">
                            <time datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                                <?php echo esc_html(get_the_date()); ?>
                            </time>
                        </div>
                    <?php endif; ?>
                </header>

                <?php
                // Rückverweis zum Werk (oben)
                $werk_id = micinterart_get_translated_meta($post_id, '_related_werk', true);
                
                if ($werk_id && get_post_status($werk_id) === 'publish') :
                    $werk_title = get_the_title($werk_id);
                    $werk_link = get_permalink($werk_id);
                    $werk_thumbnail = get_the_post_thumbnail($werk_id, 'thumbnail');
                    ?>
                    <div class="gedicht-werk-hinweis">
                        <?php if ($werk_thumbnail) : ?>
                            <div class="werk-thumbnail-preview" style="text-align:center;">
                                <a href="<?php echo esc_url($werk_link); ?>">
                                    <?php echo $werk_thumbnail; ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="werk-info" style="text-align:center;">
                            <span class="dashicons dashicons-admin-links" style="vertical-align:middle;"></span>
                            <?php
                            echo wp_kses_post(
                                '<span>' . esc_html($is_en ? 'This poem belongs to the artwork ' : 'Dieses Gedicht gehört zum Werk ') . '</span>' .
                                '<a href="' . esc_url($werk_link) . '" class="werk-link"><strong>' . esc_html($werk_title) . '</strong></a>'
                            );
                            ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="gedicht-text">
                    <?php the_content(); ?>
                </div>

                <footer class="gedicht-footer">
                    
                    <?php
                    // Werk-Link wiederholen (unten)
                    if ($werk_id && get_post_status($werk_id) === 'publish') :
                        ?>
                        <div class="gedicht-werk-cta">
                            <a href="<?php echo esc_url($werk_link); ?>" class="button">
                                <?php echo esc_html($is_en ? 'View the artwork →' : 'Zum zugehörigen Werk'); ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php
                    // Social Share Buttons (optional)
                    $show_share = apply_filters('micinterart_show_share_buttons', false);
                    if ($show_share) :
                        ?>
                        <div class="gedicht-share">
                            <span class="share-label"><?php echo esc_html__('Teilen:', 'micinterart'); ?></span>
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(get_permalink()); ?>" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               class="share-button share-facebook"
                               aria-label="<?php echo esc_attr__('Auf Facebook teilen', 'micinterart'); ?>">
                                <span class="dashicons dashicons-facebook"></span>
                            </a>
                            <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(get_permalink()); ?>&text=<?php echo urlencode(get_the_title()); ?>" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               class="share-button share-twitter"
                               aria-label="<?php echo esc_attr__('Auf Twitter teilen', 'micinterart'); ?>">
                                <span class="dashicons dashicons-twitter"></span>
                            </a>
                        </div>
                    <?php endif; ?>

                </footer>

                <?php
                // Navigation zu vorherigem/nächstem Gedicht
                ?>
                <nav class="gedicht-navigation" aria-label="<?php echo esc_attr($is_en ? 'Poem navigation' : 'Gedicht Navigation'); ?>">
                    <div class="nav-links">
                        <?php
                        $prev_post = get_previous_post();
                        if ($prev_post) :
                            ?>
                            <div class="nav-previous">
                                <a href="<?php echo esc_url(get_permalink($prev_post)); ?>" rel="prev">
                                    <span class="nav-subtitle">&larr; <?php echo esc_html($is_en ? 'Previous' : 'Vorheriges'); ?></span>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php
                        $next_post = get_next_post();
                        if ($next_post) :
                            ?>
                            <div class="nav-next">
                                <a href="<?php echo esc_url(get_permalink($next_post)); ?>" rel="next">
                                    <span class="nav-subtitle"><?php echo esc_html($is_en ? 'Next' : 'Nächstes'); ?> &rarr;</span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </nav>

                <?php
                // Zurück zur Übersicht
                ?>
                <div class="gedicht-back-to-archive">
                    <a href="<?php echo esc_url(get_post_type_archive_link('gedicht')); ?>" class="back-link">
                        &larr; <?php echo esc_html($is_en ? 'Back to the poems overview' : 'Zurück zur Gedichte-Übersicht'); ?>
                    </a>
                </div>

            </article>

        <?php endwhile; ?>
    </div>

</main>

<?php
get_footer();