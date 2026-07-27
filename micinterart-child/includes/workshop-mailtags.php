<?php
/**
 * Custom CF7 Mail-Tags: [workshop_datum], [paypal_amount]
 *
 * @package Micinterart
 */

// ============================================================================
// [paypal_amount] – Gesamtpreis als lesbarer Text, z.B. "55,00 €"
// ============================================================================

if (!function_exists('micinterart_cf7_tag_paypal_amount')) {
    function micinterart_cf7_tag_paypal_amount($output, $name, $html) {
        if ($name !== 'paypal_amount') return $output;

        $submission = WPCF7_Submission::get_instance();
        if (!$submission) return '(Preis nicht verfügbar)';

        $unit_tag    = $submission->get_meta('unit_tag');
        $workshop_id = 0;
        if (preg_match('/wpcf7-f\d+-p(\d+)-o\d+/', $unit_tag, $m)) {
            $workshop_id = intval($m[1]);
        }
        if (!$workshop_id || get_post_type($workshop_id) !== 'workshop') {
            return '(Preis nicht verfügbar)';
        }

        $posted_data = $submission->get_posted_data();

        // Priorität 1: calculated_price aus dem JS-Preisrechner
        $final_price = 0;
        if (isset($posted_data['calculated_price']) && floatval($posted_data['calculated_price']) > 0) {
            $final_price = floatval($posted_data['calculated_price']);
        }

        // Priorität 2: Thema-Preis × Teilnehmer
        if ($final_price <= 0) {
            $thema_post_id = micinterart_get_thema_post_id($workshop_id, $posted_data);
            if ($thema_post_id > 0) {
                $thema_preis_raw = get_post_meta($thema_post_id, '_thema_preis', true);
                if (!empty($thema_preis_raw)) {
                    $thema_preis = floatval(preg_replace('/[^0-9.]/', '', str_replace(',', '.', $thema_preis_raw)));
                    if ($thema_preis > 0) {
                        $anzahl      = max(1, intval($posted_data['teilnehmer'] ?? 1));
                        $final_price = $thema_preis * $anzahl;
                    }
                }
            }
        }

        // Priorität 3: serverseitige Neuberechnung
        if ($final_price <= 0 && function_exists('micinterart_calculate_final_price')) {
            $final_price = micinterart_calculate_final_price($workshop_id, $posted_data);
        }

        if ($final_price <= 0) return '(Preis nicht verfügbar)';

        // Gutschein-Abzug
        $coupon_code = isset($posted_data['gutscheincode'])
            ? sanitize_text_field(trim($posted_data['gutscheincode']))
            : '';

        if (!empty($coupon_code) && class_exists('WC_Coupon')) {
            $coupon = new WC_Coupon($coupon_code);
            if ($coupon->get_id()) {
                $discount_type   = $coupon->get_discount_type();
                $discount_amount = floatval($coupon->get_amount());

                if ($discount_type === 'percent') {
                    $rabatt = round($final_price * ($discount_amount / 100), 2);
                } elseif ($discount_type === 'fixed_cart') {
                    $rabatt = min($discount_amount, $final_price);
                } else {
                    $rabatt = 0;
                }

                $final_price = max(0, round($final_price - $rabatt, 2));
            }
        }

        return number_format($final_price, 2, ',', '.') . ' €';
    }
    add_filter('wpcf7_special_mail_tags', 'micinterart_cf7_tag_paypal_amount', 10, 3);
}

// ============================================================================
// [workshop_datum] – Datum + Uhrzeit des gebuchten Themas / Workshops
// Format: "Samstag, 19. April 2025, 10:00 – 13:00 Uhr"
// ============================================================================

if (!function_exists('micinterart_cf7_tag_workshop_datum')) {
    function micinterart_cf7_tag_workshop_datum($output, $name, $html) {
        if ($name !== 'workshop_datum') return $output;

        $submission = WPCF7_Submission::get_instance();
        if (!$submission) return '(kein Datum)';

        $unit_tag    = $submission->get_meta('unit_tag');
        $workshop_id = 0;
        if (preg_match('/wpcf7-f\d+-p(\d+)-o\d+/', $unit_tag, $m)) {
            $workshop_id = intval($m[1]);
        }
        if (!$workshop_id || get_post_type($workshop_id) !== 'workshop') {
            return '(kein Datum)';
        }

        $posted_data   = $submission->get_posted_data();
        $datum_text    = '';
        $uhrzeit_text  = '';

        // Thema-Daten bevorzugen
        $thema_post_id = micinterart_get_thema_post_id($workshop_id, $posted_data);
        if ($thema_post_id > 0) {
            $thema_datum = get_post_meta($thema_post_id, '_thema_datum', true);
            if (!empty($thema_datum)) {
                $datum_text = date_i18n('l, d. F Y', strtotime($thema_datum));
            }

            $von = get_post_meta($thema_post_id, '_thema_uhrzeit_von', true)
                ?: get_post_meta($workshop_id, '_workshop_uhrzeit_von', true);
            $bis = get_post_meta($thema_post_id, '_thema_uhrzeit_bis', true)
                ?: get_post_meta($workshop_id, '_workshop_uhrzeit_bis', true);
            if ($von) {
                $uhrzeit_text = $von . ($bis ? ' – ' . $bis : '') . ' Uhr';
            }
        }

        // Fallback auf Haupt-Workshop
        if (empty($datum_text)) {
            $ws_datum = get_post_meta($workshop_id, '_workshop_datum', true);
            if (!empty($ws_datum)) {
                $datum_text = date_i18n('l, d. F Y', strtotime($ws_datum));
            }
        }
        if (empty($uhrzeit_text)) {
            $von = get_post_meta($workshop_id, '_workshop_uhrzeit_von', true);
            $bis = get_post_meta($workshop_id, '_workshop_uhrzeit_bis', true);
            if ($von) {
                $uhrzeit_text = $von . ($bis ? ' – ' . $bis : '') . ' Uhr';
            }
        }

        if (empty($datum_text)) return '(kein Datum hinterlegt)';

        return $datum_text . ($uhrzeit_text ? ', ' . $uhrzeit_text : '');
    }
    add_filter('wpcf7_special_mail_tags', 'micinterart_cf7_tag_workshop_datum', 10, 3);
}

// ============================================================================
// HILFSFUNKTION: Thema-Post-ID aus posted_data ermitteln
// Wird von paypal_amount und workshop_datum gemeinsam genutzt
// ============================================================================

if (!function_exists('micinterart_get_thema_post_id')) {
    function micinterart_get_thema_post_id($workshop_id, $posted_data) {
        // Bevorzugt: thema_id Hidden Field (direkte ID, zuverlässiger)
        if (isset($posted_data['thema_id']) && intval($posted_data['thema_id']) > 0) {
            return intval($posted_data['thema_id']);
        }

        // Fallback: Thema per Titel suchen
        $thema_name = isset($posted_data['thema']) ? trim($posted_data['thema']) : '';
        if (empty($thema_name)) return 0;

        $thema_posts = get_posts([
            'post_type'      => 'workshop_thema',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'title'          => $thema_name,
            'meta_query'     => [[
                'key'     => '_thema_workshop_id',
                'value'   => $workshop_id,
                'compare' => '='
            ]],
        ]);

        return !empty($thema_posts) ? $thema_posts[0]->ID : 0;
    }
}