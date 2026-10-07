<?php
/**
 * SEO-Ergänzungen
 *
 * Title, Meta-Description, Open Graph und Sitemap kommen von Rank Math.
 * Hier steht nur, was Rank Math nicht kennt: Event-Daten der Workshops.
 *
 * @package Micinterart
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Strukturierte Daten (schema.org/Event) für Workshop-Termine.
 * Ermöglicht Termin-Darstellung in der Google-Suche.
 */
function micinterart_seo_workshop_event_schema() {
    if (is_admin() || !function_exists('is_product') || !is_product()) {
        return;
    }

    $product_id = get_the_ID();
    $datum = get_post_meta($product_id, '_workshop_datum', true);
    if (empty($datum)) {
        return; // Kein Workshop oder "nach Absprache" - kein fester Termin
    }

    $tz = wp_timezone();
    $start_time = get_post_meta($product_id, '_workshop_uhrzeit_von', true);
    if (!$start_time) {
        $start_time = get_post_meta($product_id, '_workshop_startzeit', true);
    }
    $end_time = get_post_meta($product_id, '_workshop_uhrzeit_bis', true);

    $start = date_create($datum . ($start_time ? ' ' . $start_time : ''), $tz);
    if (!$start) {
        return;
    }
    $end = $end_time ? date_create($datum . ' ' . $end_time, $tz) : false;

    $status = get_post_meta($product_id, '_workshop_status', true) ?: 'geplant';
    $event_status = ($status === 'abgesagt')
        ? 'https://schema.org/EventCancelled'
        : 'https://schema.org/EventScheduled';
    $availability = in_array($status, ['ausgebucht', 'beendet', 'abgesagt'], true)
        ? 'https://schema.org/SoldOut'
        : 'https://schema.org/InStock';

    $ort = get_post_meta($product_id, '_workshop_ort', true);
    $adresse = get_post_meta($product_id, '_workshop_adresse', true);

    $schema = [
        '@context'            => 'https://schema.org',
        '@type'               => 'Event',
        'name'                => wp_strip_all_tags(get_the_title($product_id)),
        'startDate'           => $start_time ? $start->format('c') : $start->format('Y-m-d'),
        'eventStatus'         => $event_status,
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'url'                 => get_permalink($product_id),
        'organizer'           => [
            '@type' => 'Organization',
            'name'  => get_bloginfo('name'),
            'url'   => home_url('/'),
        ],
    ];

    if ($end && $start_time) {
        $schema['endDate'] = $end->format('c');
    }

    if ($ort || $adresse) {
        $location = ['@type' => 'Place', 'name' => $ort ?: $adresse];
        if ($adresse) {
            $location['address'] = $adresse;
        }
        $schema['location'] = $location;
    }

    $description = wp_strip_all_tags(get_the_excerpt($product_id));
    if ($description) {
        $schema['description'] = wp_trim_words($description, 40, '…');
    }

    $image = get_the_post_thumbnail_url($product_id, 'large');
    if ($image) {
        $schema['image'] = [$image];
    }

    $product = wc_get_product($product_id);
    $price = $product ? $product->get_price() : '';
    if ($price !== '') {
        $schema['offers'] = [
            '@type'         => 'Offer',
            'price'         => number_format((float) $price, 2, '.', ''),
            'priceCurrency' => get_woocommerce_currency(),
            'availability'  => $availability,
            'url'           => get_permalink($product_id),
        ];
    }

    echo "\n<script type=\"application/ld+json\">"
        . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . "</script>\n";
}
add_action('wp_head', 'micinterart_seo_workshop_event_schema', 20);
