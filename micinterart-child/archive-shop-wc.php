<?php
/**
 * Template für die Shop-Seite (WooCommerce)
 *
 * Zeigt die Produkte getrennt nach Bereichen:
 * 1. Werke (Produkttyp 'werk')
 * 2. Workshops (Produkttyp 'workshop')
 * 3. Weitere Produkte (alle übrigen Typen, mit Seitennummerierung)
 *
 * Nutzt das Kartendesign des Werk-Archivs (archive-werk-styles.php).
 *
 * @package Micinterart
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WooCommerce')) {
    exit;
}

/**
 * Eine Produktkarte ausgeben.
 *
 * @param int    $product_id Produkt-ID
 * @param string $kind       'werk', 'workshop' oder 'product'
 */
if (!function_exists('micinterart_shop_render_card')) {
    function micinterart_shop_render_card($product_id, $kind) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $is_en = function_exists('micinterart_is_english') && micinterart_is_english();
        $meta  = [];
        $badge = '';

        if ($kind === 'werk') {
            $year       = $product->get_meta('_werk_year', true);
            $dimensions = $product->get_meta('_werk_dimensions', true);
            if ($year) {
                $meta[] = $year;
            }
            if ($dimensions) {
                $meta[] = $dimensions;
            }

            $badges = [
                'reserviert'   => $is_en ? 'Reserved' : 'Reserviert',
                'verkauft'     => $is_en ? 'Sold' : 'Verkauft',
                'privatbesitz' => $is_en ? 'Private collection' : 'Privatbesitz',
            ];
            $status = $product->get_meta('_werk_status', true);
            if (isset($badges[$status])) {
                $badge = $badges[$status];
            }
        } elseif ($kind === 'workshop') {
            $datum = $product->get_meta('_workshop_datum', true);
            $ort   = $product->get_meta('_workshop_ort', true);
            if ($datum) {
                $timestamp = strtotime($datum);
                if ($timestamp) {
                    $meta[] = date_i18n('d.m.Y', $timestamp);
                }
            }
            if ($ort) {
                $meta[] = $ort;
            }

            $status = $product->get_meta('_workshop_status', true);
            if ($status === 'ausgebucht' || !$product->is_in_stock()) {
                $badge = $is_en ? 'Fully booked' : 'Ausgebucht';
            } elseif ($status === 'abgesagt') {
                $badge = $is_en ? 'Cancelled' : 'Abgesagt';
            }
        }

        $cta = [
            'werk'     => $is_en ? '🎨 View Artwork' : '🎨 Werk ansehen',
            'workshop' => $is_en ? '📅 View Workshop' : '📅 Workshop ansehen',
            'product'  => $is_en ? '🛒 View Product' : '🛒 Produkt ansehen',
        ];
        $price_html = $product->get_price_html();
        ?>
        <article id="post-<?php echo esc_attr($product_id); ?>" class="werk-item shop-item">
            <a href="<?php echo esc_url(get_permalink($product_id)); ?>" class="werk-link">
                <?php if (has_post_thumbnail($product_id)) : ?>
                    <div class="werk-thumbnail">
                        <?php echo get_the_post_thumbnail($product_id, 'medium_large', [
                            'loading' => 'lazy',
                            'alt'     => get_the_title($product_id),
                        ]); ?>
                        <?php if ($badge !== '') : ?>
                            <span class="shop-item-badge"><?php echo esc_html($badge); ?></span>
                        <?php endif; ?>
                        <div class="werk-thumbnail-overlay">
                            <span class="werk-cta-btn"><?php echo esc_html($cta[$kind]); ?></span>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="werk-thumbnail werk-thumbnail-placeholder">
                        <span class="dashicons dashicons-format-image"></span>
                        <?php if ($badge !== '') : ?>
                            <span class="shop-item-badge"><?php echo esc_html($badge); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="werk-content">
                    <h3 class="werk-title"><?php echo esc_html(get_the_title($product_id)); ?></h3>
                    <?php if (!empty($meta)) : ?>
                        <div class="werk-meta">
                            <?php foreach ($meta as $line) : ?>
                                <span><?php echo esc_html($line); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($price_html !== '') : ?>
                        <div class="shop-item-price"><?php echo wp_kses_post($price_html); ?></div>
                    <?php endif; ?>
                </div>
            </a>
        </article>
        <?php
    }
}

get_header();

$is_en = function_exists('micinterart_is_english') && micinterart_is_english();
$paged = max(1, (int) get_query_var('paged'));

$werke_query = null;
$workshops_query = null;

// Werke und Workshops nur auf der ersten Seite zeigen
if ($paged === 1) {
    $werke_query = new WP_Query([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 8,
        'fields'         => 'ids',
        'orderby'        => 'date',
        'order'          => 'DESC',
        'tax_query'      => [[
            'taxonomy' => 'product_type',
            'field'    => 'slug',
            'terms'    => 'werk',
        ]],
    ]);

    $workshops_query = new WP_Query([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 6,
        'fields'         => 'ids',
        'tax_query'      => [[
            'taxonomy' => 'product_type',
            'field'    => 'slug',
            'terms'    => 'workshop',
        ]],
        'meta_query'     => [
            'relation' => 'OR',
            ['key' => '_workshop_datum', 'value' => date('Y-m-d'), 'compare' => '>=', 'type' => 'DATE'],
            ['key' => '_workshop_datum', 'compare' => 'NOT EXISTS'],
            ['key' => '_workshop_datum', 'value' => '', 'compare' => '='],
        ],
        'meta_key'       => '_workshop_datum',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
    ]);
}

$other_query = new WP_Query([
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => 12,
    'paged'          => $paged,
    'fields'         => 'ids',
    'tax_query'      => [[
        'taxonomy' => 'product_type',
        'field'    => 'slug',
        'terms'    => ['werk', 'workshop'],
        'operator' => 'NOT IN',
    ]],
]);

$werke_url     = home_url('/werke/');
$workshops_url = get_post_type_archive_link('workshop') ?: home_url('/workshops/');
?>

<?php
$archive_werk_styles_file = get_stylesheet_directory() . '/archive-werk-styles.php';
if (file_exists($archive_werk_styles_file)) {
    // enthält bereits das umschließende <style>-Element
    readfile($archive_werk_styles_file);
}
?>
<style>
/* Shop-Seite: Ergänzungen zum Werk-Archiv-Design */
.shop-section {
    max-width: 1400px;
    margin: 0 auto 70px;
}

.shop-section-header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px 20px;
    padding: 0 20px;
    margin-bottom: 25px;
    border-bottom: 2px solid #d4af37;
}

.shop-section-title {
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    font-size: 2.4em;
    letter-spacing: 2px;
    margin: 0 0 8px;
    color: #2c2c2c;
}

.shop-section-link {
    color: #2c2c2c;
    font-weight: 600;
    text-decoration: none;
    padding-bottom: 8px;
    transition: color 0.2s ease;
}

.shop-section-link:hover {
    color: #b89020;
}

.shop-item-price {
    margin-top: 12px;
    font-size: 1.15em;
    font-weight: 700;
    color: #2c2c2c;
}

.shop-item-price small {
    display: block;
    font-size: 0.75em;
    font-weight: 400;
    color: #666;
}

.shop-item-price del {
    color: #999;
    font-weight: 400;
}

.shop-item-price ins {
    text-decoration: none;
}

.shop-item-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    z-index: 2;
    padding: 5px 12px;
    background: #2c2c2c;
    color: #d4af37;
    border-radius: 20px;
    font-size: 0.8em;
    font-weight: 700;
    letter-spacing: 0.5px;
}

.shop-item .werk-thumbnail {
    height: 220px;
}

.shop-item .werk-title {
    font-size: 1.5em;
    margin-bottom: 10px;
}
</style>

<main id="primary" class="site-main werke-archive shop-archive">

    <header class="page-header">
        <h1 class="page-title"><?php echo $is_en ? 'Shop' : 'Shop'; ?></h1>
        <div class="archive-description">
            <?php echo $is_en
                ? 'Original artworks, workshops and more'
                : 'Originale Kunstwerke, Workshops und mehr'; ?>
        </div>
    </header>

    <?php if ($werke_query && $werke_query->have_posts()) : ?>
        <section class="shop-section shop-section-werke">
            <div class="shop-section-header">
                <h2 class="shop-section-title"><?php echo $is_en ? 'Artworks' : 'Werke'; ?></h2>
                <a class="shop-section-link" href="<?php echo esc_url($werke_url); ?>">
                    <?php echo $is_en ? 'All artworks →' : 'Alle Werke →'; ?>
                </a>
            </div>
            <div class="werke-grid">
                <?php foreach ($werke_query->posts as $product_id) {
                    micinterart_shop_render_card($product_id, 'werk');
                } ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($workshops_query && $workshops_query->have_posts()) : ?>
        <section class="shop-section shop-section-workshops">
            <div class="shop-section-header">
                <h2 class="shop-section-title"><?php echo $is_en ? 'Workshops' : 'Workshops'; ?></h2>
                <a class="shop-section-link" href="<?php echo esc_url($workshops_url); ?>">
                    <?php echo $is_en ? 'All workshops →' : 'Alle Workshops →'; ?>
                </a>
            </div>
            <div class="werke-grid">
                <?php foreach ($workshops_query->posts as $product_id) {
                    micinterart_shop_render_card($product_id, 'workshop');
                } ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($other_query->have_posts()) : ?>
        <section class="shop-section shop-section-other">
            <div class="shop-section-header">
                <h2 class="shop-section-title"><?php echo $is_en ? 'More products' : 'Weitere Produkte'; ?></h2>
            </div>
            <div class="werke-grid">
                <?php foreach ($other_query->posts as $product_id) {
                    micinterart_shop_render_card($product_id, 'product');
                } ?>
            </div>

            <?php if ($other_query->max_num_pages > 1) : ?>
                <div class="pagination">
                    <?php echo paginate_links([
                        'base'      => trailingslashit(get_pagenum_link(1)) . '%_%',
                        'format'    => 'page/%#%/',
                        'current'   => $paged,
                        'total'     => (int) $other_query->max_num_pages,
                        'mid_size'  => 2,
                        'prev_text' => $is_en ? '&laquo; Prev' : '&laquo; Zurück',
                        'next_text' => $is_en ? 'Next &raquo;' : 'Weiter &raquo;',
                    ]); ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php
    $has_content = ($werke_query && $werke_query->have_posts())
        || ($workshops_query && $workshops_query->have_posts())
        || $other_query->have_posts();
    if (!$has_content) : ?>
        <div class="no-results">
            <p><?php echo $is_en ? 'No products found.' : 'Keine Produkte gefunden.'; ?></p>
        </div>
    <?php endif; ?>

</main>

<?php
get_footer();
