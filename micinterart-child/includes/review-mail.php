<?php
/**
 * Bewertungs-Mail: 2 Tage nach dem Workshop bittet eine Mail um eine Google-Bewertung.
 *
 * Ein täglicher WP-Cron-Lauf sucht Workshops, die vor 2 bis 14 Tagen stattgefunden haben,
 * und schreibt die Besteller der bezahlten Bestellungen an. Pro Bestellung und Workshop
 * wird nur einmal gemailt (Merker an der Bestellung).
 *
 * @package Micinterart
 */

if (!defined('ABSPATH')) {
    exit;
}

// Link zur Google-Bewertung (Google Unternehmensprofil → "Bewertungen einholen").
// Solange das leer ist, werden keine Mails verschickt.
// Verschickt wird nur auf der Live-Seite (micinterart.de), nicht auf der Testseite.
if (!defined('MICINTERART_GOOGLE_REVIEW_URL')) {
    define('MICINTERART_GOOGLE_REVIEW_URL', 'https://g.page/r/Cdoibo_FsilXEBM/review');
}

const MICINTERART_REVIEW_MAIL_HOOK = 'micinterart_review_mail_daily';
const MICINTERART_REVIEW_MAIL_DELAY_DAYS = 2;   // Mail frühestens so viele Tage nach dem Workshop
const MICINTERART_REVIEW_MAIL_WINDOW_DAYS = 14; // Ältere Workshops werden nicht mehr beachtet

/**
 * Nur die Live-Seite verschickt Bewertungs-Mails
 */
function micinterart_review_mail_is_live() {
    $host = wp_parse_url(home_url(), PHP_URL_HOST);
    return in_array($host, ['micinterart.de', 'www.micinterart.de'], true);
}

function micinterart_review_mail_schedule() {
    if (!micinterart_review_mail_is_live()) {
        return;
    }
    if (!wp_next_scheduled(MICINTERART_REVIEW_MAIL_HOOK)) {
        wp_schedule_event(time(), 'daily', MICINTERART_REVIEW_MAIL_HOOK);
    }
}
add_action('init', 'micinterart_review_mail_schedule');

/**
 * IDs der Workshop-Produkte, die im Zeitfenster stattgefunden haben
 */
function micinterart_review_mail_get_workshop_ids() {
    $today = new DateTimeImmutable('today', wp_timezone());
    $from = $today->modify('-' . MICINTERART_REVIEW_MAIL_WINDOW_DAYS . ' days')->format('Y-m-d');
    $to = $today->modify('-' . MICINTERART_REVIEW_MAIL_DELAY_DAYS . ' days')->format('Y-m-d');

    return array_map('intval', get_posts([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'lang'           => '', // alle Sprachen (Polylang)
        'meta_query'     => [
            [
                'key'     => '_workshop_datum',
                'value'   => [$from, $to],
                'compare' => 'BETWEEN',
                'type'    => 'DATE',
            ],
            [
                'key'     => '_workshop_status',
                'value'   => 'abgesagt',
                'compare' => '!=',
            ],
        ],
    ]));
}

function micinterart_review_mail_run() {
    if (!micinterart_review_mail_is_live() || MICINTERART_GOOGLE_REVIEW_URL === '' || !function_exists('WC')) {
        return;
    }

    $product_ids = micinterart_review_mail_get_workshop_ids();
    if (!$product_ids) {
        return;
    }

    // Bestellungen, in denen eines dieser Workshops liegt
    global $wpdb;
    $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
    $order_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT oi.order_id
         FROM {$wpdb->prefix}woocommerce_order_items oi
         INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta om ON om.order_item_id = oi.order_item_id
         WHERE oi.order_item_type = 'line_item' AND om.meta_key = '_product_id' AND om.meta_value IN ($placeholders)",
        $product_ids
    ));

    foreach ($order_ids as $order_id) {
        $order = wc_get_order((int) $order_id);
        if (!$order instanceof WC_Order || !$order->has_status(['processing', 'completed'])) {
            continue;
        }

        foreach ($order->get_items('line_item') as $item) {
            $product_id = (int) $item->get_product_id();
            if (!in_array($product_id, $product_ids, true)) {
                continue;
            }

            $meta_key = '_micinterart_review_mail_' . $product_id;
            if ($order->get_meta($meta_key)) {
                continue; // schon verschickt
            }

            if (micinterart_review_mail_send($order, $item->get_name())) {
                $order->update_meta_data($meta_key, time());
                $order->add_order_note(sprintf('Bewertungs-Mail für „%s“ verschickt.', $item->get_name()));
                $order->save();
            }
        }
    }
}
add_action(MICINTERART_REVIEW_MAIL_HOOK, 'micinterart_review_mail_run');

function micinterart_review_mail_send($order, $workshop_name) {
    $to = $order->get_billing_email();
    if (!is_email($to)) {
        return false;
    }

    $first_name = $order->get_billing_first_name();
    $greeting = $first_name ? sprintf('Hallo %s,', $first_name) : 'Hallo,';
    $url = esc_url(MICINTERART_GOOGLE_REVIEW_URL);

    $body  = '<p>' . esc_html($greeting) . '</p>';
    $body .= '<p>' . sprintf(
        'vielen Dank, dass du beim Workshop „%s“ dabei warst. Ich hoffe, es hat dir gefallen!',
        esc_html($workshop_name)
    ) . '</p>';
    $body .= '<p>Wenn du magst, hilft mir eine kurze Bewertung bei Google sehr. Sie dauert nur eine Minute und '
        . 'zeigt anderen, was sie im Atelier erwartet.</p>';
    $body .= '<p><a href="' . $url . '" style="display:inline-block;padding:12px 24px;background:#E2AC12;color:#1a1a1a;'
        . 'text-decoration:none;font-weight:bold;border-radius:4px;">Jetzt bewerten</a></p>';
    $body .= '<p>Ich freue mich, wenn wir uns bald wiedersehen!<br>Herzliche Grüße<br>Micaella</p>';

    $mailer = WC()->mailer();
    $subject = sprintf('Wie hat dir „%s“ gefallen?', $workshop_name);
    $message = $mailer->wrap_message('Danke für deinen Besuch!', $body);

    return (bool) $mailer->send($to, $subject, $message);
}
