<?php
/**
 * Workshop: Freie Plaetze, Status-Aktualisierung, Buchungs-Metabox
 *
 * @package Micinterart
 */

// ============================================================================
// HILFSFUNKTION: FREIE PLÄTZE (aggregiert Themen wenn vorhanden)
// ============================================================================

function micinterart_get_freie_plaetze( $workshop_id ) {
    $heute = date( 'Y-m-d' );

    // Nur das NÄCHSTE zukünftige Thema abfragen (nicht alle summieren)
    $naechstes_thema = get_posts( [
        'post_type'      => 'workshop_thema',
        'posts_per_page' => 1,
        'post_status'    => 'publish',
        'meta_key'       => '_thema_datum',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => '_thema_workshop_id',
                'value'   => $workshop_id,
                'compare' => '=',
            ],
            [
                'key'     => '_thema_datum',
                'value'   => $heute,
                'compare' => '>=',
                'type'    => 'DATE',
            ],
        ],
    ] );

    if ( ! empty( $naechstes_thema ) ) {
        $thema         = $naechstes_thema[0];
        $thema_max     = (int) get_post_meta( $thema->ID, '_thema_max_teilnehmer', true );
        $thema_current = (int) get_post_meta( $thema->ID, '_thema_current_bookings', true );

        if ( $thema_max > 0 ) {
            return [
                'frei'   => max( 0, $thema_max - $thema_current ),
                'belegt' => $thema_current,
                'max'    => $thema_max,
            ];
        }
    }

    // Fallback: direkte Workshop-Meta (nur wenn keine Themen vorhanden)
    $max     = (int) get_post_meta( $workshop_id, '_workshop_max_teilnehmer', true );
    $current = (int) get_post_meta( $workshop_id, '_workshop_current_bookings', true );

    if ( $max > 0 ) {
        return [
            'frei'   => max( 0, $max - $current ),
            'belegt' => $current,
            'max'    => $max,
        ];
    }


    return null; // Kein Limit definiert
}


// ============================================================================
// AUTOMATISCHE STATUS-AKTUALISIERUNG BEI ANMELDUNG
// ============================================================================

/**
 * Zählt Anmeldungen und aktualisiert Workshop-Status automatisch
 */
add_action('wpcf7_mail_sent', 'micinterart_update_workshop_status_after_booking');

function micinterart_update_workshop_status_after_booking($contact_form) {
    $submission = WPCF7_Submission::get_instance();

    if (!$submission) {
        error_log('Workshop-Buchung: Keine Submission gefunden');
        return;
    }

    $data        = $submission->get_posted_data();
    $workshop_id = isset($data['workshop_id']) ? intval($data['workshop_id']) : 0;
    $thema_id    = isset($data['thema_id'])    ? intval($data['thema_id'])    : 0;

    if ($workshop_id <= 0) {
        error_log('Workshop-Buchung: Keine gültige Workshop-ID');
        return;
    }

    // DEBUG: Alle Formulardaten loggen
    error_log('Workshop-Buchung - Formulardaten: ' . print_r($data, true));

    // Teilnehmerzahl ermitteln – erkennt beide Formular-Typen
    $teilnehmer_anzahl = 1; // Fallback

    if (isset($data['kinder-anzahl']) && intval($data['kinder-anzahl']) > 0) {
        // KINDERWORKSHOP-FORMULAR
        $teilnehmer_anzahl = intval($data['kinder-anzahl']);
        error_log("Workshop {$workshop_id}: Kinder-Anzahl erkannt: {$teilnehmer_anzahl}");

    } elseif (isset($data['teilnehmer']) && intval($data['teilnehmer']) > 0) {
        // ERWACHSENEN-FORMULAR
        $teilnehmer_anzahl = intval($data['teilnehmer']);
        error_log("Workshop {$workshop_id}: Teilnehmer-Anzahl erkannt: {$teilnehmer_anzahl}");

    } else {
        // Fallback: 1 Teilnehmer
        error_log("Workshop {$workshop_id}: Keine Anzahl gefunden, Standard = 1");
    }

    // -----------------------------------------------------------------------
    // FALL A: Anmeldung gehört zu einem Workshop-Thema
    // -----------------------------------------------------------------------
    if ($thema_id > 0 && get_post_type($thema_id) === 'workshop_thema') {

        $current = intval(get_post_meta($thema_id, '_thema_current_bookings', true));
        $new     = $current + $teilnehmer_anzahl;
        update_post_meta($thema_id, '_thema_current_bookings', $new);
        error_log("Thema {$thema_id}: Buchungen {$current} → {$new}");

        // Workshop-Status anhand des Themas aktualisieren
        $max = intval(get_post_meta($thema_id, '_thema_max_teilnehmer', true));
        if ($max > 0) {
            $freie = $max - $new;
            if ($freie <= 0) {
                update_post_meta($workshop_id, '_workshop_status', 'ausgebucht');
            } elseif ($freie <= 3) {
                update_post_meta($workshop_id, '_workshop_status', 'fast_ausgebucht');
            } else {
                update_post_meta($workshop_id, '_workshop_status', 'anmeldung_offen');
            }
            error_log("Workshop {$workshop_id}: Status über Thema {$thema_id} aktualisiert ({$new}/{$max}, {$freie} frei)");
        }

        return; // WICHTIG: Nicht auch noch Workshop-Zähler hochzählen
    }

    // -----------------------------------------------------------------------
    // FALL B: Normaler Workshop ohne Thema
    // -----------------------------------------------------------------------
    $current_bookings = intval(get_post_meta($workshop_id, '_workshop_current_bookings', true));
    $new_bookings     = $current_bookings + $teilnehmer_anzahl;
    update_post_meta($workshop_id, '_workshop_current_bookings', $new_bookings);
    error_log("Workshop {$workshop_id}: Buchungen aktualisiert von {$current_bookings} auf {$new_bookings}");

    // Max. Teilnehmer abrufen
    $max_teilnehmer = intval(get_post_meta($workshop_id, '_workshop_max_teilnehmer', true));

    if ($max_teilnehmer <= 0) {
        error_log("Workshop {$workshop_id}: Kein Maximum definiert, Status-Update übersprungen");
        return;
    }

    // Freie Plätze berechnen und Status aktualisieren
    $freie_plaetze = $max_teilnehmer - $new_bookings;
    $new_status    = 'anmeldung_offen';

    if ($freie_plaetze <= 0) {
        $new_status = 'ausgebucht';
    } elseif ($freie_plaetze <= 3) {
        $new_status = 'fast_ausgebucht';
    }

    update_post_meta($workshop_id, '_workshop_status', $new_status);
    error_log("Workshop {$workshop_id}: Status auf '{$new_status}' gesetzt ({$new_bookings}/{$max_teilnehmer} Teilnehmer, {$freie_plaetze} Plätze frei)");
}


/**
 * Zeige aktuelle Buchungen in der Workshop-Metabox an
 */
add_action('add_meta_boxes', 'micinterart_add_bookings_metabox');

function micinterart_add_bookings_metabox() {
    add_meta_box(
        'workshop_bookings',
        '📊 Aktuelle Anmeldungen',
        'micinterart_render_bookings_metabox',
        'workshop',
        'side',
        'high'
    );
}

function micinterart_render_bookings_metabox($post) {
    $current = get_post_meta($post->ID, '_workshop_current_bookings', true) ?: 0;
    $max = get_post_meta($post->ID, '_workshop_max_teilnehmer', true) ?: 0;
    $freie = $max > 0 ? ($max - $current) : '∞';
    
    ?>
    <style>
        .bookings-stats {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
        .bookings-number {
            font-size: 32px;
            font-weight: bold;
            color: #2271b1;
            margin: 10px 0;
        }
        .bookings-label {
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .bookings-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 15px 0;
        }
        .bookings-btn {
            width: 36px;
            height: 36px;
            border: 2px solid #2271b1;
            background: #fff;
            color: #2271b1;
            font-size: 20px;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .bookings-btn:hover {
            background: #2271b1;
            color: #fff;
        }
        .bookings-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .bookings-input {
            width: 60px;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .bookings-reset {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }
        .bookings-freie-plaetze {
            margin-top: 10px;
            padding: 8px;
            border-radius: 4px;
        }
    </style>
    
    <div class="bookings-stats">
        <div class="bookings-label">Angemeldet</div>
        
        <div class="bookings-controls">
            <button type="button" class="bookings-btn" id="bookings-down" title="Anmeldung verringern">&#9660;</button>
            <input type="number" name="manual_bookings" id="bookings-value" class="bookings-input" value="<?php echo intval($current); ?>" min="0" <?php echo $max > 0 ? 'max="' . intval($max) . '"' : ''; ?>>
            <button type="button" class="bookings-btn" id="bookings-up" title="Anmeldung erhöhen">&#9650;</button>
        </div>
        
        <div class="bookings-label">
            von <?php echo $max > 0 ? intval($max) : '∞'; ?> Plätzen
        </div>
        
        <div class="bookings-freie-plaetze bookings-label" id="freie-plaetze-display">
            Freie Plätze: <strong id="freie-plaetze-zahl"><?php echo $freie; ?></strong>
        </div>
    </div>
    
    <div class="bookings-reset">
        <label>
            <input type="checkbox" name="reset_bookings" value="1">
            Anmeldungen zurücksetzen (auf 0)
        </label>
        <p class="description">Setzt die Anmeldezahl auf 0 zurück.</p>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        var maxVal = <?php echo $max > 0 ? intval($max) : 'null'; ?>;
        var $input = $('#bookings-value');
        var $freie = $('#freie-plaetze-zahl');
        
        function updateFreie() {
            var current = parseInt($input.val()) || 0;
            if (maxVal !== null) {
                $freie.text(maxVal - current);
            } else {
                $freie.text('∞');
            }
        }
        
        $('#bookings-up').on('click', function() {
            var current = parseInt($input.val()) || 0;
            if (maxVal === null || current < maxVal) {
                $input.val(current + 1);
                updateFreie();
            }
        });
        
        $('#bookings-down').on('click', function() {
            var current = parseInt($input.val()) || 0;
            if (current > 0) {
                $input.val(current - 1);
                updateFreie();
            }
        });
        
        $input.on('change input', function() {
            var val = parseInt($(this).val()) || 0;
            if (val < 0) val = 0;
            if (maxVal !== null && val > maxVal) val = maxVal;
            $(this).val(val);
            updateFreie();
        });
    });
    </script>
    <?php
    
    wp_nonce_field('workshop_bookings_save', 'workshop_bookings_nonce');
}

/**
 * Speichern der Buchungen (inkl. manuelles Zurücksetzen)
 */
add_action('save_post_workshop', 'micinterart_save_bookings_meta');

function micinterart_save_bookings_meta($post_id) {
    if (!isset($_POST['workshop_bookings_nonce']) || !wp_verify_nonce($_POST['workshop_bookings_nonce'], 'workshop_bookings_save')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Zurücksetzen wenn gewünscht
    if (isset($_POST['reset_bookings']) && $_POST['reset_bookings'] == '1') {
        update_post_meta($post_id, '_workshop_current_bookings', 0);
        update_post_meta($post_id, '_workshop_status', 'anmeldung_offen');
        return;
    }
    
    // Manuellen Wert speichern
    if (isset($_POST['manual_bookings'])) {
        $new_value = intval($_POST['manual_bookings']);
        if ($new_value < 0) $new_value = 0;
        
        $max = get_post_meta($post_id, '_workshop_max_teilnehmer', true);
        $max = $max ? intval($max) : 0;
        
        if ($max > 0 && $new_value > $max) {
            $new_value = $max;
        }
        
        update_post_meta($post_id, '_workshop_current_bookings', $new_value);
        
        // Status automatisch aktualisieren
        if ($max > 0) {
            $freie = $max - $new_value;
            if ($freie <= 0) {
                update_post_meta($post_id, '_workshop_status', 'ausgebucht');
            } elseif ($freie <= 3) {
                update_post_meta($post_id, '_workshop_status', 'fast_ausgebucht');
            } else {
                update_post_meta($post_id, '_workshop_status', 'anmeldung_offen');
            }
        }
    }
}
