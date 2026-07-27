<?php
/**
 * Workshop Schema.org Event-Markup (JSON-LD)
 *
 * Gibt strukturierte Daten für Google Rich Results aus.
 * Wird automatisch im <head> auf Einzel-Workshop-Seiten
 * und auf der Workshop-Archivseite eingebunden.
 *
 * @package Micinterart
 */

// ============================================================================
// EINZELNER WORKSHOP – Schema.org/Event JSON-LD
// ============================================================================

add_action('wp_head', 'micinterart_workshop_schema_single');

function micinterart_workshop_schema_single() {
    if (!is_singular('workshop')) {
        return;
    }

    $post_id = get_the_ID();
    $schema  = micinterart_build_event_schema($post_id);

    if (!$schema) {
        return;
    }

    echo "\n<!-- Micinterart: Schema.org Event (Einzelseite) -->\n";
    echo '<script type="application/ld+json">' . "\n";
    echo wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    echo "\n</script>\n";
}

// ============================================================================
// WORKSHOP-ARCHIV – Schema.org/ItemList + Event JSON-LD
// ============================================================================

add_action('wp_head', 'micinterart_workshop_schema_archive');

function micinterart_workshop_schema_archive() {
    if (!is_post_type_archive('workshop')) {
        return;
    }

    // Alle zukünftigen Workshops holen
    $args = [
        'post_type'      => 'workshop',
        'posts_per_page' => 50,
        'meta_key'       => '_workshop_datum',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => [
            [
                'key'     => '_workshop_datum',
                'value'   => date('Y-m-d'),
                'compare' => '>=',
                'type'    => 'DATE',
            ],
        ],
    ];

    $workshops = get_posts($args);

    if (empty($workshops)) {
        return;
    }

    $items = [];
    $position = 1;

    foreach ($workshops as $workshop) {
        $event = micinterart_build_event_schema($workshop->ID);
        if ($event) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position,
                'item'     => $event,
            ];
            $position++;
        }
    }

    if (empty($items)) {
        return;
    }

    $item_list = [
        '@context'        => 'https://schema.org',
        '@type'           => 'ItemList',
        'name'            => 'Workshops & Events – Micinterart',
        'description'     => 'Mixed-Media-Kunstworkshops im Atelier Micinterart in Morsbach',
        'numberOfItems'   => count($items),
        'itemListElement' => $items,
    ];

    echo "\n<!-- Micinterart: Schema.org ItemList + Events (Archiv) -->\n";
    echo '<script type="application/ld+json">' . "\n";
    echo wp_json_encode($item_list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    echo "\n</script>\n";
}

// ============================================================================
// HELPER: Event-Schema für einen einzelnen Workshop aufbauen
// ============================================================================

function micinterart_build_event_schema($post_id) {
    $datum    = get_post_meta($post_id, '_workshop_datum', true);
    $status   = get_post_meta($post_id, '_workshop_status', true);

    // Ohne Datum kein Event-Markup möglich
    if (empty($datum)) {
        return null;
    }

    // Vergangene oder archivierte Workshops überspringen
    if ($datum < date('Y-m-d') || $status === 'archiviert') {
        return null;
    }

    $title       = get_the_title($post_id);
    $url         = get_permalink($post_id);
    $description = get_the_excerpt($post_id);
    $thumbnail   = get_the_post_thumbnail_url($post_id, 'large');

    // Meta-Felder auslesen
    $uhrzeit_von = get_post_meta($post_id, '_workshop_uhrzeit_von', true);
    $uhrzeit_bis = get_post_meta($post_id, '_workshop_uhrzeit_bis', true);
    $preis       = get_post_meta($post_id, '_workshop_preis', true);
    $max_tn      = get_post_meta($post_id, '_workshop_max_teilnehmer', true);

    // Freie Plätze ermitteln
    $freie = null;
    if (function_exists('micinterart_get_freie_plaetze')) {
        $plaetze_data = micinterart_get_freie_plaetze($post_id);
        if ($plaetze_data !== null) {
            $freie = $plaetze_data['frei'];
        }
    }
    // Fallback
    if ($freie === null) {
        $current = (int) get_post_meta($post_id, '_workshop_current_bookings', true);
        $max     = (int) $max_tn;
        if ($max > 0) {
            $freie = max(0, $max - $current);
        }
    }

    // Start- und Endzeit
    $start_datetime = $datum;
    $end_datetime   = $datum;
    if (!empty($uhrzeit_von)) {
        $start_datetime .= 'T' . $uhrzeit_von . ':00';
    }
    if (!empty($uhrzeit_bis)) {
        $end_datetime .= 'T' . $uhrzeit_bis . ':00';
    }

    // EventAttendanceMode & EventStatus
    $event_status = 'https://schema.org/EventScheduled';
    if ($status === 'ausgebucht' || ($freie !== null && $freie <= 0)) {
        // Event bleibt "Scheduled", aber Verfügbarkeit wird über offers gesteuert
    }
    if ($status === 'abgesagt') {
        $event_status = 'https://schema.org/EventCancelled';
    }

    // Verfügbarkeit für Offers
    $availability = 'https://schema.org/InStock';
    if ($status === 'ausgebucht' || ($freie !== null && $freie <= 0)) {
        $availability = 'https://schema.org/SoldOut';
    } elseif ($freie !== null && $freie <= 3) {
        $availability = 'https://schema.org/LimitedAvailability';
    }

    // Beschreibung Fallback
    if (empty($description)) {
        $description = wp_strip_all_tags(get_post_field('post_content', $post_id));
        $description = wp_trim_words($description, 40, '…');
    }

    // Schema aufbauen
    $schema = [
        '@context'         => 'https://schema.org',
        '@type'            => 'Event',
        'name'             => $title,
        'url'              => $url,
        'description'      => $description,
        'startDate'        => $start_datetime,
        'eventStatus'      => $event_status,
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'location'         => [
            '@type'   => 'Place',
            'name'    => 'Atelier Micinterart',
            'address' => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => 'Bitze 2', // ← Hier die Straße eintragen
                'addressLocality' => 'Morsbach',
                'addressRegion'   => 'Nordrhein-Westfalen',
                'postalCode'      => '51597', // ← Hier die PLZ eintragen
                'addressCountry'  => 'DE',
            ],
        ],
        'organizer'        => [
            '@type' => 'Person',
            'name'  => 'Micaella Cervinscaia',
            'url'   => 'https://micinterart.de/wer-ich-bin/',
        ],
        'performer'        => [
            '@type' => 'Person',
            'name'  => 'Micaella Cervinscaia',
        ],
    ];

    // Endzeit nur setzen wenn vorhanden
    if (!empty($uhrzeit_bis)) {
        $schema['endDate'] = $end_datetime;
    }

    // Bild
    if (!empty($thumbnail)) {
        $schema['image'] = [$thumbnail];
    }

    // Preis / Angebot
    if (!empty($preis) && $preis > 0) {
        $schema['offers'] = [
            '@type'           => 'Offer',
            'url'             => $url,
            'price'           => number_format((float) $preis, 2, '.', ''),
            'priceCurrency'   => 'EUR',
            'availability'    => $availability,
            'validFrom'       => get_the_date('c', $post_id),
        ];

        if ($freie !== null) {
            $schema['remainingAttendeeCapacity'] = max(0, $freie);
        }
    }

    // Max. Teilnehmer
    if (!empty($max_tn) && (int) $max_tn > 0) {
        $schema['maximumAttendeeCapacity'] = (int) $max_tn;
    }

    return $schema;
}
