<?php
/**
 * Gutscheine als Shop-Produkt
 *
 * Produkte der Kategorie "Gutscheine" (Slug beginnt mit "gutschein") werden normal
 * im Shop angelegt. Sobald die Bestellung bezahlt ist (Status "In Bearbeitung" oder
 * "Abgeschlossen"), erzeugt dieses Modul pro gekauftem Gutschein einen WooCommerce-
 * Gutscheincode (Coupon) in Höhe des Kaufpreises und fügt ihn der Bestätigungsmail
 * hinzu.
 *
 * @package Micinterart
 */

if (!defined('ABSPATH')) {
    exit;
}

// Nur laden wenn WooCommerce aktiv ist
if (!class_exists('WooCommerce')) {
    exit;
}

// Prefix des Kategorie-Slugs, an dem Gutschein-Produkte erkannt werden
const MICINTERART_VOUCHER_CAT_PREFIX = 'gutschein';

// Bestellmeta: erzeugte Gutscheine (Liste aus code, amount, expires)
const MICINTERART_VOUCHERS_META = '_mic_vouchers';

/**
 * Gibt die Term-IDs aller Gutschein-Kategorien zurück (auch übersetzte).
 */
function micinterart_voucher_category_ids() {
    $terms = get_terms([
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
    ]);
    if (is_wp_error($terms)) {
        return [];
    }
    $ids = [];
    foreach ($terms as $term) {
        if (strpos($term->slug, MICINTERART_VOUCHER_CAT_PREFIX) === 0) {
            $ids[] = (int) $term->term_id;
        }
    }
    return $ids;
}

/**
 * Prüft, ob ein Produkt (oder dessen Eltern-Produkt bei Varianten) ein Gutschein ist.
 */
function micinterart_is_voucher_product($product) {
    if (!$product instanceof WC_Product) {
        return false;
    }
    $product_id = $product->get_parent_id() ?: $product->get_id();
    $terms = get_the_terms($product_id, 'product_cat');
    if (!$terms || is_wp_error($terms)) {
        return false;
    }
    foreach ($terms as $term) {
        if (strpos($term->slug, MICINTERART_VOUCHER_CAT_PREFIX) === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Erzeugt einen eindeutigen, gut lesbaren Code, z. B. MIC-7KQ4-XH2M.
 */
function micinterart_generate_voucher_code() {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // ohne 0/O/1/I
    do {
        $code = 'MIC-';
        for ($i = 0; $i < 8; $i++) {
            if ($i === 4) {
                $code .= '-';
            }
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
    } while (wc_get_coupon_id_by_code($code));
    return $code;
}

/**
 * Erzeugt die Gutscheincodes einer Bestellung (einmalig) und gibt sie zurück.
 * Mehrfachaufrufe liefern die bereits erzeugten Codes.
 *
 * @return array[] Liste aus ['code' => string, 'amount' => float, 'expires' => int]
 */
function micinterart_create_order_vouchers($order) {
    static $running = [];

    if (!$order instanceof WC_Order) {
        return [];
    }

    $existing = $order->get_meta(MICINTERART_VOUCHERS_META);
    if (is_array($existing) && !empty($existing)) {
        return $existing;
    }

    // Nur bezahlte Bestellungen bekommen Gutscheine
    if (!in_array($order->get_status(), ['processing', 'completed'], true)) {
        return [];
    }

    $order_id = $order->get_id();
    if (isset($running[$order_id])) {
        return [];
    }
    $running[$order_id] = true;

    // Gültig bis Ende des dritten Jahres nach dem Kauf (gesetzliche Regelverjährung)
    $year    = (int) gmdate('Y', $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time());
    $expires = strtotime(($year + 3) . '-12-31 23:59:59');

    $vouchers = [];
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if (!micinterart_is_voucher_product($product)) {
            continue;
        }
        $qty = max(1, (int) $item->get_quantity());
        // Wert pro Gutschein = tatsächlich bezahlter Betrag (brutto, nach Rabatt)
        $unit_amount = round(((float) $item->get_total() + (float) $item->get_total_tax()) / $qty, 2);
        if ($unit_amount <= 0) {
            continue;
        }

        for ($i = 0; $i < $qty; $i++) {
            $code   = micinterart_generate_voucher_code();
            $coupon = new WC_Coupon();
            $coupon->set_code($code);
            $coupon->set_discount_type('fixed_cart');
            $coupon->set_amount($unit_amount);
            $coupon->set_usage_limit(1);
            $coupon->set_date_expires($expires);
            // Mit einem Gutschein können keine weiteren Gutscheine gekauft werden
            $coupon->set_excluded_product_categories(micinterart_voucher_category_ids());
            $coupon->set_description(sprintf('Gutschein aus Bestellung #%s', $order->get_order_number()));
            $coupon->update_meta_data('_mic_voucher_order_id', $order_id);
            $coupon->save();

            $vouchers[] = [
                'code'    => $code,
                'amount'  => $unit_amount,
                'expires' => $expires,
            ];
        }
    }

    if (!empty($vouchers)) {
        $order->update_meta_data(MICINTERART_VOUCHERS_META, $vouchers);
        $order->add_order_note(sprintf(
            'Gutscheine erzeugt: %s',
            implode(', ', wp_list_pluck($vouchers, 'code'))
        ));
        $order->save();
    }

    unset($running[$order_id]);
    return $vouchers;
}

/**
 * Gutscheine erzeugen, sobald eine Bestellung bezahlt ist.
 */
function micinterart_vouchers_on_paid($order_id) {
    micinterart_create_order_vouchers(wc_get_order($order_id));
}
add_action('woocommerce_order_status_processing', 'micinterart_vouchers_on_paid', 20);
add_action('woocommerce_order_status_completed', 'micinterart_vouchers_on_paid', 20);

/**
 * HTML bzw. Text-Block mit den Gutscheincodes.
 */
function micinterart_render_vouchers($vouchers, $plain_text = false) {
    if (empty($vouchers)) {
        return;
    }

    if ($plain_text) {
        echo "\n" . esc_html__('Dein Gutschein', 'micinterart-child') . "\n";
        foreach ($vouchers as $v) {
            echo sprintf(
                "%s – %s (gültig bis %s)\n",
                $v['code'],
                wp_strip_all_tags(wc_price($v['amount'])),
                date_i18n('d.m.Y', $v['expires'])
            );
        }
        echo "\n";
        return;
    }

    echo '<div style="margin:24px 0;padding:20px;border:2px solid #E2AC12;border-radius:8px;background:#fdfaf2;">';
    echo '<h2 style="margin:0 0 12px;">' . esc_html__('Dein Gutschein', 'micinterart-child') . '</h2>';
    foreach ($vouchers as $v) {
        echo '<p style="margin:0 0 14px;">';
        echo '<strong style="font-size:1.4em;letter-spacing:2px;font-family:monospace;">' . esc_html($v['code']) . '</strong><br>';
        echo wp_kses_post(wc_price($v['amount'])) . ' &middot; ';
        echo esc_html(sprintf('gültig bis %s', date_i18n('d.m.Y', $v['expires'])));
        echo '</p>';
    }
    echo '<p style="margin:0;font-size:0.9em;">Den Code gibst du im Warenkorb im Feld „Gutscheincode“ ein.</p>';
    echo '</div>';
}

/**
 * Gutscheincodes in die Bestätigungsmails einfügen (bezahlt / abgeschlossen).
 * Die Codes werden hier bei Bedarf direkt erzeugt, damit sie auch dann in der Mail
 * stehen, wenn diese vor dem Status-Hook versendet wird.
 */
function micinterart_vouchers_in_email($order, $sent_to_admin, $plain_text, $email) {
    if ($sent_to_admin || !$email || !in_array($email->id, ['customer_processing_order', 'customer_completed_order'], true)) {
        return;
    }
    micinterart_render_vouchers(micinterart_create_order_vouchers($order), $plain_text);
}
add_action('woocommerce_email_after_order_table', 'micinterart_vouchers_in_email', 10, 4);

/**
 * Gutscheincodes auch auf der Bestellbestätigungs-Seite und im Kundenkonto zeigen.
 */
function micinterart_vouchers_on_order_page($order) {
    $vouchers = $order->get_meta(MICINTERART_VOUCHERS_META);
    if (is_array($vouchers) && in_array($order->get_status(), ['processing', 'completed'], true)) {
        micinterart_render_vouchers($vouchers);
    }
}
add_action('woocommerce_order_details_after_order_table', 'micinterart_vouchers_on_order_page');

/**
 * Wird eine Bestellung storniert oder erstattet, werden unbenutzte Gutscheine gelöscht.
 */
function micinterart_vouchers_on_cancel($order_id) {
    $order    = wc_get_order($order_id);
    $vouchers = $order ? $order->get_meta(MICINTERART_VOUCHERS_META) : [];
    if (!is_array($vouchers)) {
        return;
    }
    foreach ($vouchers as $v) {
        $coupon_id = wc_get_coupon_id_by_code($v['code']);
        if (!$coupon_id) {
            continue;
        }
        $coupon = new WC_Coupon($coupon_id);
        if ($coupon->get_usage_count() === 0) {
            wp_trash_post($coupon_id);
            $order->add_order_note(sprintf('Gutschein %s entfernt (Bestellung storniert/erstattet).', $v['code']));
        } else {
            $order->add_order_note(sprintf('Gutschein %s wurde bereits eingelöst und blieb bestehen.', $v['code']));
        }
    }
}
add_action('woocommerce_order_status_cancelled', 'micinterart_vouchers_on_cancel');
add_action('woocommerce_order_status_refunded', 'micinterart_vouchers_on_cancel');
