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

/**
 * Kurzname eines Workshops: Titel bis zum ersten " - " bzw. " – " (Untertitel entfällt).
 */
function micinterart_seo_workshop_short_title($product_id) {
    $title = html_entity_decode(wp_strip_all_tags(get_the_title($product_id)), ENT_QUOTES, 'UTF-8');
    $parts = preg_split('/\s[-–—]\s/u', $title, 2);
    $short = trim($parts[0]);
    return mb_strlen($short) >= 12 ? $short : $title;
}

/**
 * Ist die aktuelle Seite ein Workshop-Produkt mit Datum oder Workshop-Typ?
 * Gibt die Produkt-ID zurück (sonst 0). Manuell in Rank Math gepflegte Werte haben Vorrang.
 */
function micinterart_seo_workshop_id($rank_math_meta_key) {
    if (is_admin() || !function_exists('is_product') || !is_product()) {
        return 0;
    }
    $product_id = get_the_ID();
    if (!$product_id || get_post_meta($product_id, $rank_math_meta_key, true) !== '') {
        return 0;
    }
    $product = wc_get_product($product_id);
    $is_workshop = ($product && $product->get_type() === 'workshop') || get_post_meta($product_id, '_workshop_datum', true);
    return $is_workshop ? $product_id : 0;
}

/**
 * Titel: "<Kurzname> in Morsbach" (max. ca. 60 Zeichen, Untertitel entfällt).
 */
function micinterart_seo_workshop_title($title) {
    $product_id = micinterart_seo_workshop_id('rank_math_title');
    if (!$product_id) {
        return $title;
    }
    $suffix = ' in Morsbach';
    $short = micinterart_seo_workshop_short_title($product_id);
    return mb_strimwidth($short, 0, 60 - mb_strlen($suffix), '…', 'UTF-8') . $suffix;
}
add_filter('rank_math/frontend/title', 'micinterart_seo_workshop_title');

/**
 * Meta-Description aus den Workshop-Feldern: Kurzname, Ort, Datum, Uhrzeit, Preis.
 */
function micinterart_seo_workshop_description($description) {
    $product_id = micinterart_seo_workshop_id('rank_math_description');
    if (!$product_id) {
        return $description;
    }

    $is_en = function_exists('micinterart_is_english') && micinterart_is_english();
    $name  = micinterart_seo_workshop_short_title($product_id);
    $datum = get_post_meta($product_id, '_workshop_datum', true);
    $von   = get_post_meta($product_id, '_workshop_uhrzeit_von', true) ?: get_post_meta($product_id, '_workshop_startzeit', true);
    $bis   = get_post_meta($product_id, '_workshop_uhrzeit_bis', true);

    $ts = $datum ? strtotime($datum) : false;
    $past = $ts && $ts < strtotime('today');

    $text = $name . ($is_en ? ' at the micinterart studio in Morsbach' : ' im Atelier micinterart in Morsbach');
    if ($ts) {
        $text .= ($is_en ? ' on ' : ' am ') . date_i18n($is_en ? 'j F Y' : 'd.m.Y', $ts);
        if ($von) {
            $text .= ', ' . $von . ($bis ? '–' . $bis : '') . ($is_en ? '' : ' Uhr');
        }
    } else {
        $text .= $is_en ? ' – date by arrangement' : ' – Termin nach Absprache';
    }
    $text .= '.';

    if ($past) {
        $text .= $is_en ? ' This workshop has already taken place. See current dates.' : ' Dieser Workshop hat bereits stattgefunden. Aktuelle Termine ansehen.';
    } else {
        $product = wc_get_product($product_id);
        $price = $product ? $product->get_price() : '';
        if ($price !== '' && (float) $price > 0) {
            $price_text = $is_en
                ? '€ ' . number_format((float) $price, 2, '.', ',')
                : number_format((float) $price, 2, ',', '.') . ' €';
            $text .= ($is_en ? ' Price: ' : ' Preis: ') . $price_text . '.';
        }
        $text .= $is_en ? ' Book your place now!' : ' Jetzt Platz sichern!';
    }

    return mb_strimwidth($text, 0, 158, '…', 'UTF-8');
}
add_filter('rank_math/frontend/description', 'micinterart_seo_workshop_description');
