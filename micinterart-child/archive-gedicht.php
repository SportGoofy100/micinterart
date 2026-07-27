<?php
/**
 * Template für Archivseite: Gedichte (CPT: gedicht)
 * Design: Gedichte auf Zetteln
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
$is_en = micinterart_is_english();
?>

<style>
/* Zettel-Design für Gedichte */
.gedichte-zettel-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 60px 20px;
    background: #ffffff;
}

.gedichte-page-title {
    text-align: center;
    font-size: 3em;
    margin-bottom: 50px;
    color: #2c2c2c;
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
    letter-spacing: 2px;
}

.gedichte-zettel-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 40px;
    perspective: 1000px;
}

.gedicht-zettel {
    background: linear-gradient(to bottom, #ffffff 0%, #f8f9fa 100%);
    padding: 30px 25px;
    border-radius: 2px;
    box-shadow: 
        0 2px 5px rgba(0,0,0,0.15),
        0 10px 20px rgba(0,0,0,0.1),
        inset 0 1px 0 rgba(255,255,255,0.5);
    position: relative;
    transition: all 0.3s ease;
    transform: rotate(0deg);
    border: 1px solid #e0e0e0;
    min-height: 280px;
}

/* Leichte zufällige Rotation für jeden Zettel */
.gedicht-zettel:nth-child(3n+1) {
    transform: rotate(-1deg);
}

.gedicht-zettel:nth-child(3n+2) {
    transform: rotate(1deg);
}

.gedicht-zettel:nth-child(3n+3) {
    transform: rotate(-0.5deg);
}

.gedicht-zettel:hover {
    transform: rotate(0deg) translateY(-5px) scale(1.02);
    box-shadow: 
        0 5px 15px rgba(0,0,0,0.2),
        0 15px 30px rgba(0,0,0,0.15),
        inset 0 1px 0 rgba(255,255,255,0.5);
    z-index: 10;
}

/* Reißzwecke / Pin oben - SCHWARZ */
.gedicht-zettel::before {
    content: '';
    position: absolute;
    top: -8px;
    left: 50%;
    transform: translateX(-50%);
    width: 16px;
    height: 16px;
    background: radial-gradient(circle, #2c3e50 0%, #1a252f 100%);
    border-radius: 50%;
    box-shadow: 
        0 2px 4px rgba(0,0,0,0.3),
        inset 0 -1px 2px rgba(0,0,0,0.3),
        inset 0 1px 1px rgba(255,255,255,0.3);
    z-index: 2;
}

/* Schatten der Reißzwecke */
.gedicht-zettel::after {
    content: '';
    position: absolute;
    top: -3px;
    left: 50%;
    transform: translateX(-50%);
    width: 12px;
    height: 3px;
    background: rgba(0,0,0,0.2);
    border-radius: 50%;
    filter: blur(2px);
}

.gedicht-zettel-titel {
    margin: 0 0 20px 0;
    font-size: 1.8em;
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    color: #2c2c2c;
    text-align: center;
    padding-bottom: 15px;
    border-bottom: 2px solid #d4c5a9;
    letter-spacing: 1px;
}

.gedicht-zettel-titel a {
    text-decoration: none;
    color: #2c2c2c;
    transition: color 0.2s ease;
}

.gedicht-zettel-titel a:hover {
    color: #e74c3c;
}

.gedicht-zettel-text {
    font-family: 'Georgia', serif;
    font-size: 1em;
    line-height: 1.2;
    color: #3c3c3c;
    margin: 20px 0;
    text-align: left;
    white-space: pre-line;
}

.gedicht-zettel-footer {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px dashed #d4c5a9;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.gedicht-werk-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85em;
    color: #666;
    text-decoration: none;
    transition: color 0.2s ease;
}

.gedicht-werk-link:hover {
    color: #e74c3c;
}

.gedicht-lesen-link {
    display: inline-block;
    color: #3498db;
    font-size: 0.9em;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s ease;
}

.gedicht-lesen-link:hover {
    color: #2980b9;
    text-decoration: underline;
}

.no-gedichte {
    text-align: center;
    padding: 60px 20px;
    font-size: 1.2em;
    color: #666;
}

.gedichte-pagination {
    margin-top: 60px;
    text-align: center;
}

/* Responsive */
@media (max-width: 768px) {
    .gedichte-zettel-grid {
        grid-template-columns: 1fr;
        gap: 30px;
    }
    
    .gedicht-zettel {
        transform: rotate(0deg) !important;
    }
    
    .gedichte-page-title {
        font-size: 2em;
    }
}
</style>

<main id="primary" class="site-main">
    <div class="gedichte-zettel-container">
        
        <h1 class="gedichte-page-title"><?php echo esc_html($is_en ? 'My Poems' : 'Meine Gedichte'); ?></h1>

        <?php if (have_posts()) : ?>
            
            <div class="gedichte-zettel-grid">
                
                <?php
                // Alle Gedichte mit ihren zugehörigen Werken vorladen
                $poem_ids = wp_list_pluck($GLOBALS['wp_query']->posts, 'ID');
                $related_werke_meta = [];
                
                if (!empty($poem_ids)) {
                    global $wpdb;
                    $placeholders = implode(',', array_fill(0, count($poem_ids), '%d'));
                    $results = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT post_id, meta_value 
                             FROM {$wpdb->postmeta} 
                             WHERE post_id IN ($placeholders) 
                             AND meta_key = '_related_werk'",
                            ...$poem_ids
                        )
                    );
                    
                    foreach ($results as $row) {
                        $related_werke_meta[$row->post_id] = $row->meta_value;
                    }
                }
                
                while (have_posts()) : the_post();
                    $post_id = get_the_ID();
                    ?>
                    
                    <article id="post-<?php echo esc_attr($post_id); ?>" <?php post_class('gedicht-zettel'); ?>>
                        
                        <h2 class="gedicht-zettel-titel">
                            <a href="<?php the_permalink(); ?>">
                                <?php the_title(); ?>
                            </a>
                        </h2>
                        
                        <div class="gedicht-zettel-text">
                            <?php
                            // Erste 20 Wörter anzeigen
                            $content = get_the_content();
                            
                            // <br> Tags in Zeilenumbrüche umwandeln (für Shift+Enter)
                            $content = str_replace(['<br>', '<br/>', '<br />'], "\n", $content);
                            
                            // Dann alle anderen HTML-Tags entfernen
                            $content = wp_strip_all_tags($content);
                            
                            if (!empty($content)) {
                                // In Zeilen splitten
                                $lines = preg_split('/\r\n|\r|\n/', $content);
                                
                                // Wörter zählen und Zeilen sammeln
                                $word_count = 0;
                                $display_lines = [];
                                
                                foreach ($lines as $line) {
                                    $line = trim($line);
                                    
                                    // Leere Zeilen auch anzeigen (für Strophen-Trennung)
                                    if (empty($line)) {
                                        if (!empty($display_lines)) {
                                            $display_lines[] = '';
                                        }
                                        continue;
                                    }
                                    
                                    // Wörter in dieser Zeile zählen
                                    $words_in_line = str_word_count($line);
                                    
                                    if ($word_count + $words_in_line <= 20) {
                                        // Ganze Zeile hinzufügen
                                        $display_lines[] = esc_html($line);
                                        $word_count += $words_in_line;
                                    } else {
                                        // Nur noch die fehlenden Wörter
                                        $remaining = 20 - $word_count;
                                        if ($remaining > 0) {
                                            $words = preg_split('/\s+/', $line, -1, PREG_SPLIT_NO_EMPTY);
                                            $partial_line = implode(' ', array_slice($words, 0, $remaining));
                                            $display_lines[] = esc_html($partial_line) . '...';
                                        }
                                        break;
                                    }
                                }
                                
                                // Mit normalen Zeilenumbrüchen ausgeben (white-space: pre-line macht daraus sichtbare Umbrüche)
                                echo implode("\n", $display_lines);
                            } else {
                                echo '<em>Keine Vorschau verfügbar</em>';
                            }
                            ?>
                        </div>
                        
                        <footer class="gedicht-zettel-footer">
                            <?php
                            // Zugehöriges Werk anzeigen
                            $related_werk_id = isset($related_werke_meta[$post_id]) ? absint($related_werke_meta[$post_id]) : 0;
                            
                            if ($related_werk_id > 0 && get_post_status($related_werk_id) === 'publish') :
                                $werk_title = get_the_title($related_werk_id);
                                $werk_link = get_permalink($related_werk_id);
                                ?>
                                <a href="<?php echo esc_url($werk_link); ?>" class="gedicht-werk-link">
                                    <span>🔗</span>
                                    <span><?php echo esc_html($is_en ? 'To the artwork:' : 'Zum Werk:'); ?> <?php echo esc_html($werk_title); ?></span>
                                </a>
                            <?php endif; ?>
                            
                            <a href="<?php the_permalink(); ?>" class="gedicht-lesen-link">
                                <?php echo esc_html($is_en ? 'Read the full poem →' : 'Vollständiges Gedicht lesen →'); ?>
                            </a>
                        </footer>
                        
                    </article>
                    
                <?php endwhile; ?>
                
            </div>

            <div class="gedichte-pagination">
                <?php
                the_posts_pagination([
                    'mid_size'           => 2,
                    'prev_text'          => $is_en ? '← Back' : '← Zurück',
                    'next_text'          => $is_en ? 'Next →' : 'Weiter →',
                    'screen_reader_text' => $is_en ? 'Poems navigation' : 'Gedichte Navigation',
                ]);
                ?>
            </div>

        <?php else : ?>
            
            <div class="no-gedichte">
                <p><?php echo esc_html($is_en ? 'No poems found.' : 'Keine Gedichte gefunden.'); ?></p>
            </div>
            
        <?php endif; ?>
        
    </div>
</main>

<?php
get_footer();