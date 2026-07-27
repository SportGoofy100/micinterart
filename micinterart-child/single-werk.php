<?php
/**
 * Template für Einzelansicht: Werk
 */

get_header();
$is_en = (function_exists('pll_current_language') && pll_current_language() === 'en');
?>

<style>
/* Werk Single - Modernes Gallery-Design */
.werk-single-wrapper {
    background: #fafafa;
    min-height: 100vh;
    padding: 40px 20px;
}

.werk-single-container {
    max-width: 1200px;
    margin: 0 auto;
}

.werk-single {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 20px rgba(0,0,0,0.08);
    overflow: hidden;
}

.werk-header {
    text-align: center;
    padding: 40px 30px 30px;
    background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%);
    color: #d4af37;
}

.werk-title {
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    font-size: 3em;
    margin: 0;
    letter-spacing: 2px;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
    color: #d4af37;
}

/* Hinweise */
.werk-hinweis-gedicht,
.werk-hinweis-serie {
    margin: 20px 30px;
    padding: 15px 20px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1em;
}

.werk-hinweis-gedicht {
    background: linear-gradient(135deg, #3a3a3a 0%, #2c2c2c 100%);
    color: #d4af37;
    border: none;
    border-left: 4px solid #d4af37;
}

.werk-hinweis-serie {
    background: linear-gradient(135deg, #3a3a3a 0%, #2c2c2c 100%);
    color: #d4af37;
    border: none;
    border-left: 4px solid #d4af37;
}

.werk-hinweis-gedicht a,
.werk-hinweis-serie a {
    color: #f4e5c3;
    text-decoration: underline;
    font-weight: 600;
}

.werk-hinweis-gedicht::before {
    content: '📝';
    font-size: 1.5em;
}

.werk-hinweis-serie::before {
    content: '🎨';
    font-size: 1.5em;
}

/* Hauptbild - ZENTRIERT */
.werk-thumbnail {
    text-align: center;
    padding: 40px 30px;
    background: #f8f9fa;
}

.werk-thumbnail img {
    max-width: 800px;
    width: 100%;
    height: auto;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    transition: transform 0.3s ease;
}

.werk-thumbnail img:hover {
    transform: scale(1.02);
}

/* Content */
.werk-content {
    padding: 40px 30px;
    font-size: 1.1em;
    line-height: 1.8;
    color: #333;
}

/* Meta-Informationen */
.werk-meta {
    margin: 30px;
    padding: 25px;
    background: linear-gradient(135deg, #ffffff 0%, #f5f5f5 50%, #e8e8e8 100%);
    border-radius: 12px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    border: 2px solid #d4af37;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.werk-meta-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.werk-meta-label {
    font-size: 0.85em;
    color: #d4af37;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 1px;
}

.werk-meta-value {
    font-size: 1.2em;
    color: #2c2c2c;
    font-weight: 600;
}

/* Weitere Bilder */
.werk-additional-images {
    padding: 40px 30px;
    background: #f8f9fa;
}

.werk-additional-images h2 {
    text-align: center;
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    font-size: 2em;
    color: #2c2c2c;
    margin: 0 0 30px 0;
    letter-spacing: 2px;
}

.werk-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.werk-gallery-item {
    display: block;
    position: relative;
    overflow: hidden;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    aspect-ratio: 1;
}

.werk-gallery-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.3s ease;
}

.werk-gallery-item:hover {
    transform: translateY(-8px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
}

.werk-gallery-item:hover img {
    transform: scale(1.1);
}

/* Lightbox */
.lightbox-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.95);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    cursor: pointer;
}

.lightbox-overlay.active {
    display: flex;
}

.lightbox-image {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
    border-radius: 8px;
}

.lightbox-close {
    position: absolute;
    top: 30px;
    right: 40px;
    font-size: 50px;
    color: white;
    cursor: pointer;
    z-index: 10000;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    transition: all 0.3s ease;
}

.lightbox-close:hover {
    background: rgba(255,255,255,0.2);
    transform: rotate(90deg);
}

.lightbox-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    font-size: 50px;
    color: white;
    cursor: pointer;
    padding: 20px;
    user-select: none;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.lightbox-nav:hover {
    background: rgba(255,255,255,0.2);
    transform: translateY(-50%) scale(1.1);
}

.lightbox-prev {
    left: 40px;
}

.lightbox-next {
    right: 40px;
}

/* Zurück-Link */
.werk-back-link {
    text-align: center;
    padding: 30px;
}

.werk-back-link a {
    display: inline-block;
    color: #2c2c2c;
    text-decoration: none;
    font-size: 1.1em;
    font-weight: 600;
    transition: all 0.3s ease;
    padding: 10px 20px;
    border-radius: 8px;
    border: 2px solid #d4af37;
}

.werk-back-link a:hover {
    background: #d4af37;
    color: #2c2c2c;
    transform: translateX(-5px);
}

/* Responsive */
@media (max-width: 768px) {
    .werk-title {
        font-size: 2em;
    }
    
    .werk-gallery {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 15px;
    }
    
    .werk-thumbnail img {
        max-width: 100%;
    }
    
    .werk-meta {
        grid-template-columns: 1fr;
    }
    
    .lightbox-nav {
        font-size: 35px;
        width: 50px;
        height: 50px;
        padding: 10px;
    }
    
    .lightbox-prev {
        left: 20px;
    }
    
    .lightbox-next {
        right: 20px;
    }
    
    .lightbox-close {
        font-size: 40px;
        width: 40px;
        height: 40px;
        top: 20px;
        right: 20px;
    }
}

/* Animation */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.werk-single {
    animation: fadeIn 0.6s ease-out;
}

/* Status and Representation Badges */
.werk-meta-status {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 15px;
}

.werk-status-badge {
    display: inline-block;
    align-self: flex-start;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 0.78em;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.status-verfuegbar {
    background: #e8f5e9;
    color: #2e7d32;
}

.status-reserviert {
    background: #fff3e0;
    color: #ef6c00;
}

.status-verkauft {
    background: #ffebee;
    color: #c62828;
}

.status-privatbesitz {
    background: #f5f5f5;
    color: #616161;
}

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

<main id="primary" class="site-main werk-single-wrapper">

<div class="werk-single-container">

<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>

<article <?php post_class('werk-single'); ?>>

    <header class="werk-header">
        <h1 class="werk-title"><?php the_title(); ?></h1>
    </header>

    <?php
    // Hinweis: Gedicht vorhanden?
    $poems = get_posts([
        'post_type'  => 'gedicht',
        'meta_key'   => '_related_werk',
        'meta_value' => get_the_ID(),
        'numberposts'=> 1
    ]);
    if ($poems) {
        echo '<div class="werk-hinweis-gedicht">';
        if ($is_en) {
            echo 'There is a <a href="'.get_permalink($poems[0]->ID).'">poem</a> for this artwork!';
        } else {
            echo 'Zu diesem Werk gibt es ein <a href="'.get_permalink($poems[0]->ID).'">Gedicht</a>!';
        }
        echo '</div>';
    }

    // Hinweis: Serie vorhanden?
    $serien = get_the_terms(get_the_ID(), 'serie');
    if ($serien && !is_wp_error($serien)) {
        foreach ($serien as $serie) {
            echo '<div class="werk-hinweis-serie">';
            if ($is_en) {
                echo 'Part of the series <strong>'.esc_html($serie->name).'</strong> – ';
                echo '<a href="'.esc_url(get_term_link($serie)).'">View all artworks</a>';
            } else {
                echo 'Teil der Serie <strong>'.esc_html($serie->name).'</strong> – ';
                echo '<a href="'.esc_url(get_term_link($serie)).'">Alle Werke ansehen</a>';
            }
            echo '</div>';
        }
    }
    ?>

    <?php if ( has_post_thumbnail() ) : ?>
        <div class="werk-thumbnail">
            <?php the_post_thumbnail('large'); ?>
        </div>
    <?php endif; ?>

    <div class="werk-content">
        <?php the_content(); ?>
    </div>

    <?php
    // Maße & Materialien
    $materials  = micinterart_get_translated_meta(get_the_ID(), '_werk_materials', true);
    $dimensions = micinterart_get_translated_meta(get_the_ID(), '_werk_dimensions', true);
    $year       = micinterart_get_translated_meta(get_the_ID(), '_werk_year', true);
    $represented = micinterart_get_translated_meta(get_the_ID(), '_werk_represented', true);

    if ($materials || $dimensions || $year || $represented) {
        echo '<div class="werk-meta">';
        if ($year) {
            echo '<div class="werk-meta-item">';
            echo '<span class="werk-meta-label">' . ($is_en ? 'Year' : 'Jahr') . '</span>';
            echo '<span class="werk-meta-value">'.esc_html($year).'</span>';
            echo '</div>';
        }
        if ($dimensions) {
            echo '<div class="werk-meta-item">';
            echo '<span class="werk-meta-label">' . ($is_en ? 'Dimensions' : 'Maße') . '</span>';
            echo '<span class="werk-meta-value">'.esc_html($dimensions).'</span>';
            echo '</div>';
        }
        if ($materials) {
            echo '<div class="werk-meta-item">';
            echo '<span class="werk-meta-label">' . ($is_en ? 'Materials' : 'Materialien') . '</span>';
            echo '<span class="werk-meta-value">'.esc_html($materials).'</span>';
            echo '</div>';
        }

        if ($represented) {
            echo '<div class="werk-meta-item" style="grid-column: 1 / -1;">';
            echo '<span class="werk-represented-badge">👑 ' . ($is_en ? 'Represented by ' : 'Vertreten durch ') . esc_html($represented) . '</span>';
            echo '</div>';
        }
        echo '</div>';
    }
    ?>

    <?php
    // Weitere Bilder Galerie
    $additional_images = micinterart_get_translated_meta(get_the_ID(), '_werk_additional_images', true);
    
    if (!empty($additional_images) && is_array($additional_images)) :
    ?>
        <div class="werk-additional-images">
            <h2><?php echo $is_en ? 'Additional Views' : 'Weitere Ansichten'; ?></h2>
            
            <div class="werk-gallery">
                <?php foreach ($additional_images as $image_id) : 
                    $image_url = wp_get_attachment_image_url($image_id, 'large');
                    $thumbnail_url = wp_get_attachment_image_url($image_id, 'medium');
                    
                    if ($image_url) :
                ?>
                    <a href="<?php echo esc_url($image_url); ?>" 
                       class="werk-gallery-item" 
                       data-lightbox="werk-gallery">
                        <img src="<?php echo esc_url($thumbnail_url); ?>" 
                             alt="<?php echo esc_attr(get_the_title()); ?>"
                             loading="lazy">
                    </a>
                <?php 
                    endif;
                endforeach; ?>
            </div>
        </div>

        <script>
        (function() {
            // Lightbox Funktionalität
            const galleryItems = document.querySelectorAll('.werk-gallery-item');
            
            if (galleryItems.length === 0) return;
            
            // Lightbox erstellen
            const overlay = document.createElement('div');
            overlay.className = 'lightbox-overlay';
            
            const closeBtn = document.createElement('span');
            closeBtn.className = 'lightbox-close';
            closeBtn.innerHTML = '&times;';
            
            const img = document.createElement('img');
            img.className = 'lightbox-image';
            
            const prevBtn = document.createElement('span');
            prevBtn.className = 'lightbox-nav lightbox-prev';
            prevBtn.innerHTML = '&#10094;';
            
            const nextBtn = document.createElement('span');
            nextBtn.className = 'lightbox-nav lightbox-next';
            nextBtn.innerHTML = '&#10095;';
            
            overlay.appendChild(closeBtn);
            overlay.appendChild(prevBtn);
            overlay.appendChild(img);
            overlay.appendChild(nextBtn);
            document.body.appendChild(overlay);
            
            let currentIndex = 0;
            const images = Array.from(galleryItems).map(item => item.href);
            
            function showImage(index) {
                currentIndex = index;
                img.src = images[index];
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
            
            function closeLightbox() {
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
            
            function showNext() {
                currentIndex = (currentIndex + 1) % images.length;
                img.src = images[currentIndex];
            }
            
            function showPrev() {
                currentIndex = (currentIndex - 1 + images.length) % images.length;
                img.src = images[currentIndex];
            }
            
            // Event Listeners
            galleryItems.forEach((item, index) => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    showImage(index);
                });
            });
            
            closeBtn.addEventListener('click', closeLightbox);
            
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    closeLightbox();
                }
            });
            
            nextBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                showNext();
            });
            
            prevBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                showPrev();
            });
            
            // Keyboard Navigation
            document.addEventListener('keydown', function(e) {
                if (!overlay.classList.contains('active')) return;
                
                if (e.key === 'Escape') closeLightbox();
                if (e.key === 'ArrowRight') showNext();
                if (e.key === 'ArrowLeft') showPrev();
            });
        })();
        </script>
    <?php endif; ?>

    <div class="werk-back-link">
        <a href="<?php echo esc_url(get_post_type_archive_link('werk')); ?>">
            <?php echo $is_en ? '← Back to Artworks' : '← Zurück zur Werke-Übersicht'; ?>
        </a>
    </div>

</article>

<?php endwhile; endif; ?>

</div>

</main>

<?php get_footer();