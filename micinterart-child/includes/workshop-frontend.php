<?php
/**
 * Workshop Frontend: "Was dich erwartet", Naechster-Workshop-Banner
 *
 * @package Micinterart
 */

// ============================================================================
// WORKSHOP "WAS DICH ERWARTET" AUTOMATISCH ANZEIGEN
// ============================================================================

/**
 * Zeigt "Was dich erwartet" unter dem Workshop-Inhalt an
 */
add_filter('the_content', 'micinterart_add_workshop_expectations');

function micinterart_add_workshop_expectations($content) {
    // Nur auf einzelnen Workshop-Seiten
    if (!is_singular('workshop')) {
        return $content;
    }

    $post_id        = get_the_ID();
    $preis          = get_post_meta($post_id, '_workshop_preis', true);
    $max_teilnehmer = get_post_meta($post_id, '_workshop_max_teilnehmer', true);
    $preis_inklusiv = get_post_meta($post_id, '_workshop_preis_inklusiv', true);
    $is_paar        = get_post_meta($post_id, '_workshop_is_paar_preis', true);
    $sprache        = get_post_meta($post_id, '_workshop_sprache', true) ?: 'deutsch';
    $sprache_text   = ($sprache === 'russisch') ? 'Russisch' : 'Deutsch';
    $ort            = get_post_meta($post_id, '_workshop_ort', true);

    // ── Hilfsfunktion: Custom Field oder Fallback ──────────────────────────
    $get_erwartet = function($nr, $key, $default) use ($post_id) {
        $val = get_post_meta($post_id, "_workshop_erwartet_{$nr}_{$key}", true);
        return !empty($val) ? $val : $default;
    };

    // ── Parkplätze-Logik (Feld 6) ─────────────────────────────────────────
    // Wenn kein Ort angegeben ODER Ort enthält "Morsbach" → Standardtext
    $parkplatz_text = '';
    if (empty($ort) || stripos($ort, 'morsbach') !== false) {
        $parkplatz_text = 'Direkt vor dem Atelier in Morsbach';
    } else {
        $parkplatz_text = 'Bitte informiere dich vorab über die Parkmöglichkeiten vor Ort.';
    }

    // ── Preis-Suffix ──────────────────────────────────────────────────────
    $preis_info = get_post_meta($post_id, '_workshop_preis_info', true);
    $is_kinderworkshop = false;
    $categories = get_the_terms($post_id, 'workshop_kategorie');
    if ($categories && !is_wp_error($categories)) {
        foreach ($categories as $category) {
            if ($category->slug === 'kinderworkshops') {
                $is_kinderworkshop = true;
                break;
            }
        }
    }
    if (!empty($preis_info)) {
        $suffix = $preis_info;
    } elseif ($is_paar === 'yes') {
        $suffix = 'pro Paar';
    } elseif ($is_kinderworkshop) {
        $suffix = 'pro Kind';
    } else {
        $suffix = 'pro Person';
    }

    $preis_text = '';
    if ($preis > 0) {
        $preis_text = number_format($preis, 2, ',', '.') . ' €';
    }

    ob_start();
    ?>
    <div class="workshop-expectations" style="background: #f9f9f9; padding: 30px; border-radius: 10px; margin: 40px 0; border-left: 5px solid #d4a574;">
        <h3 style="margin-top: 0; color: #2c2c2c; font-size: 1.5em;">✨ Was dich erwartet</h3>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">

            <?php // ── Feld 1: Materialien ────────────────────────────────────────── ?>
            <div style="display: flex; align-items: start; gap: 12px;">
                <span style="font-size: 1.5em;"><?php echo esc_html($get_erwartet(1, 'emoji', '🎨')); ?></span>
                <div>
                    <strong><?php echo esc_html($get_erwartet(1, 'titel', 'Alle Materialien inklusive')); ?></strong><br>
                    <span style="color: #666; font-size: 0.95em;"><?php echo esc_html($get_erwartet(1, 'text', 'Du brauchst nichts mitzubringen – alles ist vorbereitet')); ?></span>
                </div>
            </div>

            <?php // ── Feld 2: Kleine Gruppen (hardcoded, Zahl dynamisch) ─────────── ?>
            <div style="display: flex; align-items: start; gap: 12px;">
                <span style="font-size: 1.5em;">👥</span>
                <div>
                    <strong>Kleine Gruppen</strong><br>
                    <span style="color: #666; font-size: 0.95em;">Max. <?php echo $max_teilnehmer ?: 8; ?> Teilnehmer – persönliche Betreuung garantiert</span>
                </div>
            </div>

            <?php // ── Feld 3: Vorkenntnisse ──────────────────────────────────────── ?>
            <div style="display: flex; align-items: start; gap: 12px;">
                <span style="font-size: 1.5em;"><?php echo esc_html($get_erwartet(3, 'emoji', '🎓')); ?></span>
                <div>
                    <strong><?php echo esc_html($get_erwartet(3, 'titel', 'Keine Vorkenntnisse nötig')); ?></strong><br>
                    <span style="color: #666; font-size: 0.95em;"><?php echo esc_html($get_erwartet(3, 'text', 'Ich begleite dich Schritt für Schritt')); ?></span>
                </div>
            </div>

            <?php // ── Feld 4: Kunstwerk ──────────────────────────────────────────── ?>
            <div style="display: flex; align-items: start; gap: 12px;">
                <span style="font-size: 1.5em;"><?php echo esc_html($get_erwartet(4, 'emoji', '🖼️')); ?></span>
                <div>
                    <strong><?php echo esc_html($get_erwartet(4, 'titel', 'Dein fertiges Kunstwerk')); ?></strong><br>
                    <span style="color: #666; font-size: 0.95em;"><?php echo esc_html($get_erwartet(4, 'text', 'Zum Mitnehmen und stolz nach Hause tragen')); ?></span>
                </div>
            </div>

            <?php // ── Feld 5: Inklusive ──────────────────────────────────────────── ?>
            <div style="display: flex; align-items: start; gap: 12px;">
                <span style="font-size: 1.5em;"><?php echo esc_html($get_erwartet(5, 'emoji', '☕')); ?></span>
                <div>
                    <strong><?php echo esc_html($get_erwartet(5, 'titel', 'Inklusive:')); ?></strong><br>
                    <span style="color: #666; font-size: 0.95em;">
                        <?php
                        // Text: Custom Field hat Vorrang, dann _workshop_preis_inklusiv, dann Default
                        $feld5_text = get_post_meta($post_id, '_workshop_erwartet_5_text', true);
                        if (!empty($feld5_text)) {
                            echo esc_html($feld5_text);
                        } elseif (!empty($preis_inklusiv)) {
                            echo esc_html($preis_inklusiv);
                        } else {
                            echo 'Kaffee, Tee, Wasser und kleine Leckereien';
                        }
                        ?>
                    </span>
                </div>
            </div>

            <?php // ── Feld 6: Parkplätze (automatisch per Ort) ──────────────────── ?>
            <div style="display: flex; align-items: start; gap: 12px;">
                <span style="font-size: 1.5em;">🚗</span>
                <div>
                    <strong>Kostenlose Parkplätze</strong><br>
                    <span style="color: #666; font-size: 0.95em;"><?php echo esc_html($parkplatz_text); ?></span>
                </div>
            </div>

            <?php // ── Feld 7: Kurssprache (dynamisch aus _workshop_sprache) ─────── ?>
            <div style="display: flex; align-items: start; gap: 12px;">
                <span style="font-size: 1.5em;">🗣️</span>
                <div>
                    <strong>Kurssprache</strong><br>
                    <span style="color: #666; font-size: 0.95em;">
                        Kurs findet auf <?php echo esc_html($sprache_text); ?> statt.
                    </span>
                </div>
            </div>

        </div>

        <?php if ($preis_text): ?>
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center;">
            <span style="font-size: 2em; font-weight: 700; color: #d4a574;"><?php echo $preis_text; ?></span>
            <span style="color: #666; display: block; margin-top: 5px;"><?php echo esc_html($suffix); ?></span>
        </div>
        <?php endif; ?>
    </div>
    <?php

    $expectations_html = ob_get_clean();
    return $content . $expectations_html;
}
/**
 * Shortcode für den nächsten Workshop-Banner (zentriert & dynamisch)
 */
function micinterart_next_workshop_banner_shortcode() {
    $args = [
        'post_type'      => 'workshop',
        'posts_per_page' => 1,
        'meta_key'       => '_workshop_datum',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => [['key' => '_workshop_datum', 'value' => date('Y-m-d'), 'compare' => '>=', 'type' => 'DATE']]
    ];

    $query = new WP_Query($args);
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $datum   = get_post_meta($post_id, '_workshop_datum', true);
            $status  = get_post_meta($post_id, '_workshop_status', true);

            // ✅ NUR das nächste Thema zählen (nicht alle aggregieren)
$freie = null;
$heute = date('Y-m-d');

// Nächstes Thema zu diesem Workshop suchen
$naechstes_thema = get_posts([
    'post_type'      => 'workshop_thema',
    'posts_per_page' => 1,
    'post_status'    => 'publish',
    'meta_key'       => '_thema_datum',
    'orderby'        => 'meta_value',
    'order'          => 'ASC',
    'meta_query'     => [
        [
            'key'     => '_thema_workshop_id',
            'value'   => $post_id,
            'compare' => '=',
        ],
        [
            'key'     => '_thema_datum',
            'value'   => $heute,
            'compare' => '>=',
            'type'    => 'DATE',
        ],
    ],
]);

if (!empty($naechstes_thema)) {
    $thema     = $naechstes_thema[0];
    $thema_max = (int) get_post_meta($thema->ID, '_thema_max_teilnehmer', true);
    $thema_cur = (int) get_post_meta($thema->ID, '_thema_current_bookings', true);
    if ($thema_max > 0) {
        $freie = max(0, $thema_max - $thema_cur);
    }
} else {
    // Fallback: direkte Workshop-Meta (nur wenn keine Themen vorhanden)
    $max     = (int) get_post_meta($post_id, '_workshop_max_teilnehmer', true);
    $current = (int) get_post_meta($post_id, '_workshop_current_bookings', true);
    if ($max > 0) {
        $freie = max(0, $max - $current);
    }
}


            $date_obj       = new DateTime($datum);
            $formatted_date = date_i18n('d. F Y', $date_obj->getTimestamp());
            // Status & Plätze Logik (PLAETZE AUSGEBLENDET)
            $status_msg = '';
            ob_start(); ?>
            <div style="text-align: center; width: 100%; margin: 30px 0;">
                <a href="<?php the_permalink(); ?>" style="text-decoration: none; display: inline-block;">
                    <div class="next-workshop-banner" style="background: #fff; border: 1px solid #eee; border-left: 4px solid #d4a574; padding: 15px 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); text-align: left;">
                        <div style="text-transform: uppercase; font-size: 0.7rem; letter-spacing: 1.5px; color: #999; margin-bottom: 3px; font-weight: 700;">Nächster Termin</div>
                        <div style="font-size: 1.05rem; color: #2c2c2c; line-height: 1.4;">
                            <strong><?php the_title(); ?></strong> am <?php echo $formatted_date; ?><?php echo $status_msg; ?>
                        </div>
                    </div>
                </a>
            </div>
            <?php
            wp_reset_postdata();
            return ob_get_clean();
        }
    }
    return '';
}
add_shortcode('naechster_workshop_banner', 'micinterart_next_workshop_banner_shortcode');

