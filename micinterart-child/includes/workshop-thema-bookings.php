<?php
/**
 * Workshop-Thema: Buchungs-Metabox (Anmeldungen verwalten)
 *
 * @package Micinterart
 */

// ============================================================================
// ANMELDUNGEN-METABOX FÜR WORKSHOP_THEMA
// ============================================================================

add_action('add_meta_boxes', 'micinterart_add_thema_bookings_metabox');

function micinterart_add_thema_bookings_metabox() {
    add_meta_box(
        'workshop_thema_bookings',
        '📊 Anmeldungen für dieses Thema',
        'micinterart_render_thema_bookings_metabox',
        'workshop_thema',
        'side',
        'high'
    );
}

function micinterart_render_thema_bookings_metabox($post) {
    $current = intval(get_post_meta($post->ID, '_thema_current_bookings', true));
    $max     = intval(get_post_meta($post->ID, '_thema_max_teilnehmer', true));
    $freie   = $max > 0 ? max(0, $max - $current) : '∞';
    ?>
    <div class="bookings-stats">
        <div class="bookings-label">Angemeldet</div>

        <div class="bookings-controls">
            <button type="button" class="bookings-btn" id="thema-bookings-down" title="Verringern">&#9660;</button>
            <input type="number" name="thema_manual_bookings" id="thema-bookings-value" class="bookings-input"
                   value="<?php echo $current; ?>" min="0"
                   <?php echo $max > 0 ? 'max="' . $max . '"' : ''; ?>>
            <button type="button" class="bookings-btn" id="thema-bookings-up" title="Erhöhen">&#9650;</button>
        </div>

        <div class="bookings-label">
            von <?php echo $max > 0 ? $max : '∞'; ?> Plätzen
        </div>

        <div class="bookings-freie-plaetze bookings-label">
            Freie Plätze: <strong id="thema-freie-plaetze-zahl"><?php echo $freie; ?></strong>
        </div>

        <div style="margin-top:15px;">
            <label><strong>Max. Teilnehmer:</strong></label>
            <input type="number" name="thema_max_teilnehmer" value="<?php echo $max; ?>" min="0"
                   style="width:100%;margin-top:5px;padding:4px 8px;border:1px solid #ddd;border-radius:4px;">
            <p class="description">Leer lassen = unbegrenzt</p>
        </div>
    </div>

    <div class="bookings-reset" style="margin-top:15px;padding-top:15px;border-top:1px solid #ddd;">
        <label>
            <input type="checkbox" name="thema_reset_bookings" value="1">
            Anmeldungen zurücksetzen (auf 0)
        </label>
        <p class="description">Setzt die Anmeldezahl auf 0 zurück.</p>
    </div>

    <script>
    jQuery(document).ready(function($) {
        var maxVal = <?php echo $max > 0 ? $max : 'null'; ?>;
        var $input = $('#thema-bookings-value');
        var $freie = $('#thema-freie-plaetze-zahl');

        function updateFreie() {
            var current = parseInt($input.val()) || 0;
            if (maxVal !== null) {
                $freie.text(maxVal - current);
            } else {
                $freie.text('∞');
            }
        }

        $('#thema-bookings-up').on('click', function() {
            var current = parseInt($input.val()) || 0;
            if (maxVal === null || current < maxVal) {
                $input.val(current + 1);
                updateFreie();
            }
        });

        $('#thema-bookings-down').on('click', function() {
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
    wp_nonce_field('workshop_thema_bookings_save', 'workshop_thema_bookings_nonce');
}

add_action('save_post_workshop_thema', 'micinterart_save_thema_bookings_meta');

function micinterart_save_thema_bookings_meta($post_id) {
    if (!isset($_POST['workshop_thema_bookings_nonce']) ||
        !wp_verify_nonce($_POST['workshop_thema_bookings_nonce'], 'workshop_thema_bookings_save')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // Max. Teilnehmer speichern
    if (isset($_POST['thema_max_teilnehmer'])) {
        $max = intval($_POST['thema_max_teilnehmer']);
        update_post_meta($post_id, '_thema_max_teilnehmer', $max);
    }

    // Zurücksetzen
    if (isset($_POST['thema_reset_bookings']) && $_POST['thema_reset_bookings'] == '1') {
        update_post_meta($post_id, '_thema_current_bookings', 0);
        return;
    }

    // Manuellen Wert speichern
    if (isset($_POST['thema_manual_bookings'])) {
        $new_value = intval($_POST['thema_manual_bookings']);
        if ($new_value < 0) $new_value = 0;

        $max = intval(get_post_meta($post_id, '_thema_max_teilnehmer', true));
        if ($max > 0 && $new_value > $max) $new_value = $max;

        update_post_meta($post_id, '_thema_current_bookings', $new_value);
    }
}

