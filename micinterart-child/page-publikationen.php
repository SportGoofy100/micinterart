<?php
/**
 * Template für die Seite "Publikationen" (Slug: publikationen)
 *
 * Zeigt PDFs aus der Mediathek nach Medien-Kategorie:
 * "Meine Publikationen" (includes/publikationen.php); Artikel über die Künstlerin stehen als Text im Seiteneditor.
 *
 * @package Micinterart
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
$is_en = function_exists('micinterart_is_english') ? micinterart_is_english() : (function_exists('pll_current_language') && pll_current_language() === 'en');

$pdfs = function_exists('micinterart_get_publikationen') ? micinterart_get_publikationen('von-ihr') : [];
?>
<style>
.publikationen-page { background: #fafafa; padding: 40px 20px 60px; }
.publikationen-page .page-header { text-align: center; max-width: 1400px; margin: 0 auto 50px; padding: 40px 20px; background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%); border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
.publikationen-page .page-title { font-family: 'Bebas Neue', 'Arial', sans-serif; font-size: 3.5em; margin: 0; letter-spacing: 3px; color: var(--mic-gold, #E2AC12); }
.publikationen-page .archive-description { color: #f5f5f5; font-size: 1.1em; margin-top: 15px; line-height: 1.6; }
.publikationen-section { max-width: 1400px; margin: 0 auto 60px; padding: 0 20px; }
.publikationen-section h2 { font-family: 'Bebas Neue', 'Arial', sans-serif; font-size: 2.2em; letter-spacing: 2px; margin: 0 0 25px; padding-bottom: 10px; border-bottom: 3px solid var(--mic-gold, #E2AC12); }
.publikationen-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 28px; }
.publikation-card { display: flex; flex-direction: column; background: #fff; border-radius: 12px; overflow: hidden; text-decoration: none; color: inherit; box-shadow: 0 4px 12px rgba(0,0,0,0.1); transition: transform 0.3s ease, box-shadow 0.3s ease; }
.publikation-card:hover { transform: translateY(-6px); box-shadow: 0 12px 28px rgba(0,0,0,0.2); }
.publikation-cover { aspect-ratio: 3 / 4; background: #eee; overflow: hidden; display: flex; align-items: center; justify-content: center; }
.publikation-cover img { width: 100%; height: 100%; object-fit: cover; object-position: top; display: block; }
.publikation-cover .publikation-icon { font-size: 3em; color: #999; }
.publikation-info { padding: 14px 16px 18px; }
.publikation-titel { font-weight: 600; line-height: 1.35; margin: 0 0 8px; }
.publikation-link { font-size: 0.85em; color: var(--mic-gold-deep, #C48F00); text-transform: uppercase; letter-spacing: 1px; }
.publikationen-text { max-width: 800px; line-height: 1.7; }
.publikationen-text ul { padding-left: 1.2em; }
.publikationen-empty { color: #666; font-style: italic; }
@media (max-width: 600px) {
    .publikationen-page { padding: 20px 10px 40px; }
    .publikationen-page .page-title { font-size: 2.4em; }
    .publikationen-grid { grid-template-columns: repeat(2, 1fr); gap: 16px; }
}
</style>

<main id="primary" class="site-main publikationen-page">

    <header class="page-header">
        <h1 class="page-title"><?php echo esc_html(get_the_title()); ?></h1>
    </header>

    <section class="publikationen-section">
        <h2><?php echo $is_en ? 'My publications' : 'Meine Publikationen'; ?></h2>

        <?php if ($pdfs) : ?>
            <div class="publikationen-grid">
                <?php foreach ($pdfs as $pdf) :
                    $cover = wp_get_attachment_image($pdf->ID, 'medium', false, ['loading' => 'lazy', 'alt' => '']);
                    ?>
                    <a class="publikation-card" href="<?php echo esc_url(wp_get_attachment_url($pdf->ID)); ?>" target="_blank" rel="noopener">
                        <div class="publikation-cover">
                            <?php echo $cover ? $cover : '<span class="publikation-icon" aria-hidden="true">📄</span>'; ?>
                        </div>
                        <div class="publikation-info">
                            <p class="publikation-titel"><?php echo esc_html(get_the_title($pdf)); ?></p>
                            <span class="publikation-link"><?php echo $is_en ? 'Open PDF' : 'PDF öffnen'; ?> &rarr;</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p class="publikationen-empty"><?php echo $is_en ? 'Publications will follow soon.' : 'Publikationen folgen in Kürze.'; ?></p>
        <?php endif; ?>
    </section>

    <?php
    // Artikel über die Künstlerin: Text aus dem Seiteneditor
    while (have_posts()) {
        the_post();
        if (trim(get_the_content()) !== '') {
            echo '<section class="publikationen-section publikationen-presse">';
            echo '<h2>' . ($is_en ? 'In the press' : 'In der Presse') . '</h2>';
            echo '<div class="publikationen-text">';
            the_content();
            echo '</div></section>';
        }
    }
    ?>

</main>

<?php
get_footer();
