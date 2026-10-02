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
        $price_html = $kind === 'werk'
            ? micinterart_werk_price_html($product)
            : wp_kses_post($product->get_price_html());
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
                        <div class="shop-item-price"><?php echo $price_html; ?></div>
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

// Zwei Ansichten: Shop-Startseite (Kacheln mit je einem aktuellen Produkt) oder
// Unterseite "Atelier-Shop" (alle übrigen Produkte)
$is_atelier_page = (bool) get_query_var('micinterart_atelier');

$werke_url     = home_url('/werke/');
$workshops_url = get_post_type_archive_link('workshop') ?: home_url('/workshops/');
$atelier_url   = home_url('/atelier-shop/');
$atelier_title = $is_en ? 'Atelier Shop' : 'Atelier-Shop';

// Produkte, die weder Werk noch Workshop sind
$other_tax_query = [[
    'taxonomy' => 'product_type',
    'field'    => 'slug',
    'terms'    => ['werk', 'workshop'],
    'operator' => 'NOT IN',
]];

$other_query = null;
$previews = [];

if ($is_atelier_page) {
    $other_query = new WP_Query([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 12,
        'paged'          => $paged,
        'fields'         => 'ids',
        'tax_query'      => $other_tax_query,
    ]);
} else {
    // Je Kachel genau ein aktuelles Produkt
    $previews['workshops'] = new WP_Query([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'tax_query'      => [[
            'taxonomy' => 'product_type',
            'field'    => 'slug',
            'terms'    => 'workshop',
        ]],
        // der nächste anstehende Termin (ohne Datum zählt ebenfalls)
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

    $previews['werke'] = new WP_Query([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'orderby'        => 'date',
        'order'          => 'DESC',
        'tax_query'      => [[
            'taxonomy' => 'product_type',
            'field'    => 'slug',
            'terms'    => 'werk',
        ]],
    ]);

    $previews['atelier'] = new WP_Query([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'orderby'        => 'date',
        'order'          => 'DESC',
        'tax_query'      => $other_tax_query,
    ]);
}

// Kacheln der Shop-Startseite (Bilder: Design → Anpassen → Shop-Kacheln)
$tiles = [
    [
        'key'         => 'workshops',
        'title'       => 'Workshops',
        'text'        => $is_en ? 'Courses & events in the studio' : 'Kurse & Events im Atelier',
        'url'         => $workshops_url,
        'placeholder' => '📅',
        'preview'     => $is_en ? 'Next workshop' : 'Nächster Workshop',
        'kind'        => 'workshop',
    ],
    [
        'key'         => 'werke',
        'title'       => $is_en ? 'Artworks' : 'Werke',
        'text'        => $is_en ? 'Original works of art' : 'Originale Kunstwerke',
        'url'         => $werke_url,
        'placeholder' => '🎨',
        'preview'     => $is_en ? 'Latest artwork' : 'Neuestes Werk',
        'kind'        => 'werk',
    ],
    [
        'key'         => 'atelier',
        'title'       => $atelier_title,
        'text'        => $is_en ? 'Art boxes, painting tools & texture pastes' : 'Artboxen, Maltools & Strukturmassen',
        'url'         => $atelier_url,
        'placeholder' => '🖌️',
        'preview'     => $is_en ? 'New in the shop' : 'Neu im Shop',
        'kind'        => 'product',
    ],
];
?>
<main id="primary" class="site-main werke-archive shop-archive">

    <header class="page-header">
        <?php if ($is_atelier_page) : ?>
            <h1 class="page-title"><?php echo esc_html($atelier_title); ?></h1>
            <div class="archive-description">
                <?php echo $is_en
                    ? 'Art boxes, 3D-printed paint tools and spatulas, self-made texture pastes and more'
                    : 'Artboxen, 3D-gedruckte Malmesser und Spachtel, selbst hergestellte Strukturmassen und mehr'; ?>
            </div>
        <?php else : ?>
            <h1 class="page-title">Shop</h1>
            <div class="archive-description">
                <?php echo $is_en
                    ? 'Workshops, original artworks and more from the studio'
                    : 'Workshops, originale Kunstwerke und mehr aus dem Atelier'; ?>
            </div>
        <?php endif; ?>
    </header>

    <?php if (!$is_atelier_page) : ?>
        <div class="shop-tiles">
            <?php foreach ($tiles as $tile) :
                $image_url = function_exists('micinterart_shop_tile_image') ? micinterart_shop_tile_image($tile['key']) : '';
                $preview_ids = isset($previews[$tile['key']]) ? $previews[$tile['key']]->posts : [];
                ?>
                <div class="shop-tile-col">
                    <a class="shop-tile" href="<?php echo esc_url($tile['url']); ?>">
                        <?php if ($image_url !== '') : ?>
                            <span class="shop-tile-image" style="background-image: url('<?php echo esc_url($image_url); ?>');"></span>
                        <?php else : ?>
                            <span class="shop-tile-image shop-tile-placeholder" aria-hidden="true"><?php echo esc_html($tile['placeholder']); ?></span>
                        <?php endif; ?>
                        <span class="shop-tile-body">
                            <span class="shop-tile-title"><?php echo esc_html($tile['title']); ?></span>
                            <span class="shop-tile-text"><?php echo esc_html($tile['text']); ?></span>
                            <span class="shop-tile-cta"><?php echo esc_html($is_en ? 'Browse →' : 'Ansehen →'); ?></span>
                        </span>
                    </a>

                    <?php if (!empty($preview_ids)) : ?>
                        <div class="shop-tile-preview">
                            <div class="shop-tile-preview-label"><?php echo esc_html($tile['preview']); ?></div>
                            <?php micinterart_shop_render_card($preview_ids[0], $tile['kind']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($is_atelier_page) : ?>
        <?php if ($other_query->have_posts()) : ?>
            <section class="shop-section shop-section-other">
                <div class="shop-section-header">
                    <h2 class="shop-section-title"><?php echo esc_html($atelier_title); ?></h2>
                    <a class="shop-section-link" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">
                        <?php echo $is_en ? '← Back to the shop' : '← Zurück zum Shop'; ?>
                    </a>
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
        <?php else : ?>
            <div class="no-results">
                <p><?php echo $is_en ? 'No products here yet.' : 'Hier gibt es noch keine Produkte.'; ?></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</main>

<?php
get_footer();
