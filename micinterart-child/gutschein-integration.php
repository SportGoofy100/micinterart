<?php
/**
 * Gutschein-Integration für Micinterart Workshop-Buchungen
 *
 * Integriert WooCommerce-Coupons als physische Gutscheincodes in das
 * CF7-Anmeldeformular (Kinder + Erwachsene). Der Code läuft vollständig
 * server-seitig für die Validierung und die PayPal-Preisberechnung.
 *
 * VORAUSSETZUNGEN:
 *   - WooCommerce aktiv
 *   - Contact Form 7 aktiv
 *   - Gutscheine als WooCommerce-Coupons angelegt (usage_limit = 1)
 *
 * INSTALLATION:
 *   Diese Datei in das Child-Theme-Verzeichnis legen und in functions.php
 *   am Ende einbinden:
 *       require_once get_stylesheet_directory() . '/gutschein-integration.php';
 *
 * CF7-FORMULARE ANPASSEN:
 *   In beiden CF7-Formularen (ID 9260249 Kinder, e4aab6c Erwachsene)
 *   folgendes Textfeld ergänzen:
 *       [text gutscheincode id:gutscheincode-field placeholder "Gutscheincode (optional)"]
 *
 * CF7-MAIL-TAGS die neu verfügbar sind:
 *   [gutschein_info]   – Zeigt Gutschein-Details in der Bestätigungsmail
 *
 * @package Micinterart
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// WooCommerce muss aktiv sein
if ( ! function_exists( 'WC' ) ) {
    return;
}

// ============================================================================
// TEIL 1: AJAX-ENDPUNKT – GUTSCHEIN VALIDIEREN
// Wird vom Frontend per AJAX aufgerufen (unabhängig vom Preisrechner-AJAX).
// Gibt zurück: gültig/ungültig, Rabatt-Betrag, Rabatt-Typ, neue Gesamtsumme.
// ============================================================================

/**
 * AJAX-Handler: Gutscheincode serverseitig gegen WooCommerce-Coupons prüfen.
 * Endpunkt: action=validate_workshop_coupon
 *
 * POST-Parameter:
 *   nonce        – Sicherheits-Nonce
 *   coupon_code  – Der eingegebene Gutscheincode
 *   workshop_id  – Post-ID des Workshops
 *   base_price   – Bereits berechneter Preis VOR Gutschein (float, aus client-
 *                  seitiger oder AJAX-Berechnung, wird hier nur als Referenz
 *                  genutzt; der echte Abzug rechnet der Server neu)
 *   form_data    – Array mit Formularfeldern (kinder-anzahl, teilnehmer etc.)
 */
add_action( 'wp_ajax_validate_workshop_coupon',        'micinterart_ajax_validate_coupon' );
add_action( 'wp_ajax_nopriv_validate_workshop_coupon', 'micinterart_ajax_validate_coupon' );

function micinterart_ajax_validate_coupon() {

    check_ajax_referer( 'micinterart_coupon_check', 'nonce' );

    $coupon_code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';
    $workshop_id = isset( $_POST['workshop_id'] ) ? intval( $_POST['workshop_id'] ) : 0;
    $form_data   = isset( $_POST['form_data'] )   ? $_POST['form_data']             : array();

    // Eingaben prüfen
    if ( empty( $coupon_code ) ) {
        wp_send_json_error( array( 'message' => 'Bitte gib einen Gutscheincode ein.' ) );
    }
    if ( $workshop_id <= 0 || get_post_type( $workshop_id ) !== 'workshop' ) {
        wp_send_json_error( array( 'message' => 'Ungültige Workshop-ID.' ) );
    }

    // Basis-Preis server-seitig berechnen
    $base_price = micinterart_calculate_final_price( $workshop_id, $form_data );

    // Fallback: Thema-Preis × Teilnehmer (bei Themen-Workshops)
    if ( $base_price <= 0 ) {
        $thema_post_id = micinterart_get_thema_post_id( $workshop_id, $form_data );
        if ( $thema_post_id > 0 ) {
            $thema_preis_raw = get_post_meta( $thema_post_id, '_thema_preis', true );
            if ( ! empty( $thema_preis_raw ) ) {
                $thema_preis = floatval( preg_replace( '/[^0-9.]/', '', str_replace( ',', '.', $thema_preis_raw ) ) );
                if ( $thema_preis > 0 ) {
                    $anzahl      = max( 1, intval( $form_data['teilnehmer'] ?? 1 ) );
                    $base_price  = $thema_preis * $anzahl;
                }
            }
        }
    }

    // Letzter Fallback: calculated_price aus dem Formular
    if ( $base_price <= 0 && isset( $form_data['calculated_price'] ) ) {
        $base_price = floatval( $form_data['calculated_price'] );
    }

    if ( $base_price <= 0 ) {
        wp_send_json_error( array( 'message' => 'Basispreis konnte nicht ermittelt werden.' ) );
    }

    // WooCommerce-Coupon laden
    $coupon = new WC_Coupon( $coupon_code );

    // Existiert der Coupon?
    if ( ! $coupon->get_id() ) {
        wp_send_json_error( array( 'message' => 'Dieser Gutscheincode ist nicht gültig.' ) );
    }

    // Abgelaufen?
    $expiry = $coupon->get_date_expires();
    if ( $expiry && $expiry->getTimestamp() < time() ) {
        wp_send_json_error( array( 'message' => 'Dieser Gutscheincode ist abgelaufen.' ) );
    }

    // Nutzungslimit erreicht?
    $usage_limit = $coupon->get_usage_limit();
    $usage_count = $coupon->get_usage_count();
    if ( $usage_limit > 0 && $usage_count >= $usage_limit ) {
        wp_send_json_error( array( 'message' => 'Dieser Gutscheincode wurde bereits eingelöst.' ) );
    }

    // Rabatt berechnen
    $discount_type   = $coupon->get_discount_type(); // 'percent' oder 'fixed_cart'
    $discount_amount = floatval( $coupon->get_amount() );
    $rabatt_absolut  = 0.0;

    if ( $discount_type === 'percent' ) {
        $rabatt_absolut = round( $base_price * ( $discount_amount / 100 ), 2 );
    } elseif ( $discount_type === 'fixed_cart' ) {
        $rabatt_absolut = min( $discount_amount, $base_price ); // nie unter 0
    } else {
        // Unbekannter Typ – kein Rabatt gewähren
        wp_send_json_error( array( 'message' => 'Dieser Gutscheintyp wird für Workshops nicht unterstützt.' ) );
    }

    $final_price = max( 0, round( $base_price - $rabatt_absolut, 2 ) );

    // Coupon-Beschreibung für die Anzeige
    if ( $discount_type === 'percent' ) {
        $rabatt_text = number_format( $discount_amount, 0, ',', '.' ) . '% Rabatt';
    } else {
        $rabatt_text = number_format( $discount_amount, 2, ',', '.' ) . ' € Rabatt';
    }

    wp_send_json_success( array(
        'coupon_code'     => $coupon_code,
        'rabatt_typ'      => $discount_type,
        'rabatt_betrag'   => $rabatt_absolut,
        'rabatt_text'     => $rabatt_text,
        'basis_preis'     => $base_price,
        'final_price'     => $final_price,
        'final_price_fmt' => number_format( $final_price, 2, ',', '.' ) . ' €',
        'message'         => '✅ Gutschein <strong>' . esc_html( strtoupper( $coupon_code ) ) . '</strong> eingelöst! Du sparst ' . $rabatt_text . '.',
    ) );
}


// ============================================================================
// TEIL 2: GUTSCHEIN-VERWENDUNG BUCHEN (nach erfolgreichem CF7-Submit)
// Wird auf wpcf7_mail_sent gefeuert.
// Erhöht den usage_count des WooCommerce-Coupons um 1.
// ============================================================================

add_action( 'wpcf7_mail_sent', 'micinterart_redeem_coupon_on_booking', 5 );

function micinterart_redeem_coupon_on_booking( $contact_form ) {

    $submission = WPCF7_Submission::get_instance();
    if ( ! $submission ) {
        return;
    }

    $data        = $submission->get_posted_data();
    $coupon_code = isset( $data['gutscheincode'] ) ? sanitize_text_field( trim( $data['gutscheincode'] ) ) : '';

    if ( empty( $coupon_code ) ) {
        return; // Kein Gutschein eingegeben – nichts tun
    }

    $coupon = new WC_Coupon( $coupon_code );

    if ( ! $coupon->get_id() ) {
        error_log( 'Gutschein-Einlösung: Coupon "' . $coupon_code . '" nicht gefunden.' );
        return;
    }

    // usage_count um 1 erhöhen
    // wc_update_coupon_usage_counts() erwartet eine Order-ID; da wir keine
    // WooCommerce-Order anlegen, setzen wir den Counter direkt per update_post_meta.
    $current_usage = intval( get_post_meta( $coupon->get_id(), 'usage_count', true ) );
    update_post_meta( $coupon->get_id(), 'usage_count', $current_usage + 1 );

    // WooCommerce-internen Cache leeren (damit das neue usage_count sofort gilt)
    WC_Cache_Helper::invalidate_cache_group( 'coupon_' . $coupon->get_id() );

    $workshop_id = isset( $data['workshop_id'] ) ? intval( $data['workshop_id'] ) : 0;
    error_log( sprintf(
        'Gutschein "%s" eingelöst. Workshop-ID: %d. Neuer usage_count: %d.',
        $coupon_code,
        $workshop_id,
        $current_usage + 1
    ) );
}


// ============================================================================
// TEIL 3: CF7 MAIL-TAG [gutschein_info]
// Gibt in der Bestätigungsmail die Gutschein-Details aus.
// ============================================================================

add_filter( 'wpcf7_special_mail_tags', 'micinterart_cf7_tag_gutschein_info', 10, 3 );

function micinterart_cf7_tag_gutschein_info( $output, $name, $html ) {

    if ( $name !== 'gutschein_info' ) {
        return $output;
    }

    $submission = WPCF7_Submission::get_instance();
    if ( ! $submission ) {
        return $output;
    }

    $data        = $submission->get_posted_data();
    $coupon_code = isset( $data['gutscheincode'] ) ? sanitize_text_field( trim( $data['gutscheincode'] ) ) : '';

    if ( empty( $coupon_code ) ) {
        return '(kein Gutschein verwendet)';
    }

    $coupon = new WC_Coupon( $coupon_code );
    if ( ! $coupon->get_id() ) {
        return '(ungültiger Gutscheincode: ' . esc_html( $coupon_code ) . ')';
    }

    $discount_type   = $coupon->get_discount_type();
    $discount_amount = floatval( $coupon->get_amount() );

    if ( $discount_type === 'percent' ) {
        $rabatt_text = number_format( $discount_amount, 0, ',', '.' ) . '%';
    } else {
        $rabatt_text = number_format( $discount_amount, 2, ',', '.' ) . ' €';
    }

    // Preis neu berechnen um Ersparnis korrekt auszugeben
    $workshop_id = 0;
    $unit_tag    = $submission->get_meta( 'unit_tag' );
    if ( preg_match( '/wpcf7-f\d+-p(\d+)-o\d+/', $unit_tag, $m ) ) {
        $workshop_id = intval( $m[1] );
    }
    if ( ! $workshop_id && isset( $data['workshop_id'] ) ) {
        $workshop_id = intval( $data['workshop_id'] );
    }

    $ersparnis_text = '';
    if ( $workshop_id && function_exists( 'micinterart_calculate_final_price' ) ) {
        $base_price = micinterart_calculate_final_price( $workshop_id, $data );
        if ( $discount_type === 'percent' ) {
            $ersparnis = round( $base_price * ( $discount_amount / 100 ), 2 );
        } else {
            $ersparnis = min( $discount_amount, $base_price );
        }
        $final = max( 0, round( $base_price - $ersparnis, 2 ) );
        $ersparnis_text = "\nErsparnis: " . number_format( $ersparnis, 2, ',', '.' ) . ' €'
                        . "\nEndpreis nach Gutschein: " . number_format( $final, 2, ',', '.' ) . ' €';
    }

    return sprintf(
        "Gutscheincode: %s\nRabatt: %s%s",
        strtoupper( $coupon_code ),
        $rabatt_text,
        $ersparnis_text
    );
}


// ============================================================================
// TEIL 4: PayPal-Link mit Gutschein-Abzug (wpcf7_mail_components-Filter)
// Überschreibt den bereits bestehenden [paypal_link]-Wert, wenn ein gültiger
// Gutscheincode im Formular übergeben wurde. Läuft nach dem existierenden
// micinterart_cf7_paypal_link()-Filter (Priority 20 statt 10).
// ============================================================================

add_filter( 'wpcf7_mail_components', 'micinterart_cf7_paypal_link_with_coupon', 20, 3 );

function micinterart_cf7_paypal_link_with_coupon( $components, $cf7, $mail ) {

    // Nur aktiv wenn [paypal_link] im Mail-Body vorkommt
    if ( strpos( $components['body'], '[paypal_link]' ) === false ) {
        return $components;
    }

    $submission = WPCF7_Submission::get_instance();
    if ( ! $submission ) {
        return $components;
    }

    $data        = $submission->get_posted_data();
    $coupon_code = isset( $data['gutscheincode'] ) ? sanitize_text_field( trim( $data['gutscheincode'] ) ) : '';

    // Kein Gutschein – bestehenden Filter (Priority 10) unberührt lassen
    if ( empty( $coupon_code ) ) {
        return $components;
    }

    // Workshop-ID bestimmen
    $workshop_id = 0;
    $unit_tag    = $submission->get_meta( 'unit_tag' );
    if ( preg_match( '/wpcf7-f\d+-p(\d+)-o\d+/', $unit_tag, $m ) ) {
        $workshop_id = intval( $m[1] );
    }
    if ( ! $workshop_id && isset( $data['workshop_id'] ) ) {
        $workshop_id = intval( $data['workshop_id'] );
    }
    if ( ! $workshop_id || get_post_type( $workshop_id ) !== 'workshop' ) {
        return $components;
    }

    // Basis-Preis bestimmen:
    // Priorität 1: calculated_price aus dem Formular (vom Frontend korrekt berechnet)
    // Priorität 2: serverseitige Neuberechnung als Fallback
    $base_price = 0;
    if ( isset( $data['calculated_price'] ) && floatval( $data['calculated_price'] ) > 0 ) {
        $base_price = floatval( $data['calculated_price'] );
    } elseif ( function_exists( 'micinterart_calculate_final_price' ) ) {
        $base_price = micinterart_calculate_final_price( $workshop_id, $data );
    }
    if ( $base_price <= 0 ) {
        return $components;
    }

    // Coupon validieren (noch einmal server-seitig, da dies die E-Mail ist)
    $coupon        = new WC_Coupon( $coupon_code );
    $final_price   = $base_price;

    if ( $coupon->get_id() ) {
        $discount_type   = $coupon->get_discount_type();
        $discount_amount = floatval( $coupon->get_amount() );

        if ( $discount_type === 'percent' ) {
            $rabatt = round( $base_price * ( $discount_amount / 100 ), 2 );
        } elseif ( $discount_type === 'fixed_cart' ) {
            $rabatt = min( $discount_amount, $base_price );
        } else {
            $rabatt = 0;
        }
        $final_price = max( 0, round( $base_price - $rabatt, 2 ) );
    }

    if ( $final_price <= 0 ) {
        $components['body'] = str_replace( '[paypal_link]', '(Preis = 0,00 € nach Gutschein – keine PayPal-Zahlung nötig)', $components['body'] );
        return $components;
    }

    // PayPal-URL bauen
    $paypal_email  = defined( 'MICINTERART_PAYPAL_EMAIL' )
        ? MICINTERART_PAYPAL_EMAIL
        : get_option( 'micinterart_paypal_email', 'info@micinterart.de' );
    $workshop_title = get_the_title( $workshop_id );

    $params = array(
        'cmd'           => '_xclick',
        'business'      => $paypal_email,
        'item_name'     => $workshop_title,
        'amount'        => number_format( $final_price, 2, '.', '' ),
        'currency_code' => 'EUR',
        'no_shipping'   => '1',
        'return'        => get_permalink( $workshop_id ) . '?payment=success',
        'cancel_return' => get_permalink( $workshop_id ) . '?payment=cancelled',
        'notify_url'    => home_url( '/paypal-ipn/' ),
    );

    $paypal_link = 'https://www.paypal.com/cgi-bin/webscr?' . http_build_query( $params );

    $components['body'] = str_replace( '[paypal_link]', $paypal_link, $components['body'] );

    return $components;
}


// ============================================================================
// TEIL 5: FRONTEND-SCRIPT
// Fügt das Gutschein-Eingabefeld dynamisch per JavaScript in beide CF7-
// Formulare ein und kommuniziert mit dem AJAX-Endpunkt (Teil 1).
// Aktualisiert den PayPal-Link und die Preisanzeige nach Gutschein-Einlösung.
// ============================================================================

add_action( 'wp_footer', 'micinterart_gutschein_frontend_script', 25 );

function micinterart_gutschein_frontend_script() {

    if ( ! is_singular( 'workshop' ) ) {
        return;
    }

$paypal_email = defined( 'MICINTERART_PAYPAL_EMAIL' )
    ? MICINTERART_PAYPAL_EMAIL
    : get_option( 'micinterart_paypal_email', '' );
// Kein hard return wenn PayPal-Email fehlt – Gutschein-Box trotzdem anzeigen

    $post_id = get_the_ID();

    // Preis prüfen – auch Themen-Workshops berücksichtigen
    $preis = floatval( get_post_meta( $post_id, '_workshop_preis', true ) );

    // Falls kein Basis-Preis am Workshop selbst: prüfen ob Themen mit Preisen existieren
    if ( $preis <= 0 ) {
        $themen_mit_preis = get_posts([
            'post_type'      => 'workshop_thema',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => '_thema_workshop_id',
                    'value'   => $post_id,
                    'compare' => '='
                ],
                [
                    'key'     => '_thema_preis',
                    'value'   => '',
                    'compare' => '!='
                ],
            ],
        ]);
        if ( empty( $themen_mit_preis ) ) {
            return; // Wirklich kein Preis irgendwo → Widget nicht ausgeben
        }
    }

    // Kinderworkshop-Flag für clientseitige Logik
    $is_kinderworkshop = false;
    $categories        = get_the_terms( $post_id, 'workshop_kategorie' );
    if ( $categories && ! is_wp_error( $categories ) ) {
        foreach ( $categories as $cat ) {
            if ( $cat->slug === 'kinderworkshops' ) {
                $is_kinderworkshop = true;
                break;
            }
        }
    }

    $nonce_coupon = wp_create_nonce( 'micinterart_coupon_check' );
    $ajax_url     = admin_url( 'admin-ajax.php' );
    $workshop_title_js = addslashes( get_the_title( $post_id ) );
    ?>
    <style>
    /* ── Gutschein-Bereich ──────────────────────────────────────────────── */
    .mic-coupon-wrapper {
        margin: 20px 0 0;
        padding: 20px;
        background: #f9f6f1;
        border: 1px solid #e8ddd0;
        border-radius: 10px;
    }
    .mic-coupon-wrapper h4 {
        margin: 0 0 12px 0;
        font-size: 1em;
        color: #2c2c2c;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .mic-coupon-input-row {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .mic-coupon-input-row input[type="text"] {
        flex: 1 1 200px;
        padding: 10px 14px;
        border: 2px solid #ddd;
        border-radius: 6px;
        font-size: 1em;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: border-color 0.2s;
        outline: none;
    }
    .mic-coupon-input-row input[type="text"]:focus {
        border-color: #d4a574;
    }
    .mic-coupon-btn {
        padding: 10px 22px;
        background: #2c2c2c;
        color: #fff;
        border: none;
        border-radius: 6px;
        font-size: 1em;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
        white-space: nowrap;
    }
    .mic-coupon-btn:hover {
        background: #d4a574;
    }
    .mic-coupon-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    .mic-coupon-msg {
        margin-top: 12px;
        padding: 10px 14px;
        border-radius: 6px;
        font-size: 0.95em;
        display: none;
    }
    .mic-coupon-msg.success {
        background: #e8f5e9;
        color: #2e7d32;
        border-left: 4px solid #4caf50;
        display: block;
    }
    .mic-coupon-msg.error {
        background: #ffebee;
        color: #c62828;
        border-left: 4px solid #ef5350;
        display: block;
    }
    .mic-coupon-rabatt-row {
        display: none; /* wird eingeblendet wenn Gutschein aktiv */
    }
    .mic-coupon-remove-link {
        font-size: 0.85em;
        color: #999;
        text-decoration: underline;
        cursor: pointer;
        margin-top: 8px;
        display: inline-block;
    }
    .mic-coupon-remove-link:hover {
        color: #c62828;
    }
    </style>

    <script>
    jQuery(document).ready(function($) {

        // ── Konfiguration ──────────────────────────────────────────────────
        var workshopId      = <?php echo intval( $post_id ); ?>;
        var isKinder        = <?php echo $is_kinderworkshop ? 'true' : 'false'; ?>;
        var basisPreis      = <?php echo $preis; ?>;
        var ajaxUrl         = '<?php echo esc_js( $ajax_url ); ?>';
        var nonceGutschein  = '<?php echo esc_js( $nonce_coupon ); ?>';
        var paypalEmail     = '<?php echo esc_js( $paypal_email ); ?>';
        var workshopTitle   = '<?php echo $workshop_title_js; ?>';

        // Gutschein-Status (globaler Zustand für diese Seite)
        var aktiverGutschein = null; // { code, rabatt_betrag, final_price }

        // ── Gutschein-UI erstellen ─────────────────────────────────────────
        var couponHTML = '' +
            '<div class="mic-coupon-wrapper" id="mic-coupon-wrapper">' +
                '<h4>🏷️ Gutscheincode einlösen</h4>' +
                '<div class="mic-coupon-input-row">' +
                    '<input type="text" id="mic-coupon-code" placeholder="GUTSCHEIN-CODE" autocomplete="off">' +
                    '<button type="button" class="mic-coupon-btn" id="mic-coupon-btn">Einlösen</button>' +
                '</div>' +
                '<div class="mic-coupon-msg" id="mic-coupon-msg"></div>' +
                '<a class="mic-coupon-remove-link" id="mic-coupon-remove" style="display:none;">✕ Gutschein entfernen</a>' +
            '</div>';

        // ── Gutschein-UI nach CF7-Formular einfügen ────────────────────────
        function insertCouponUI() {
            if ( $('#mic-coupon-wrapper').length > 0 ) return;
            var $form = $('.wpcf7-form');
            if ( $form.length > 0 ) {
                $form.after( couponHTML );
                bindCouponEvents();
            }
        }

        insertCouponUI();
        $(document).on('wpcf7:ready', function() {
            setTimeout( insertCouponUI, 150 );
        });

        // ── Gutschein-Events ───────────────────────────────────────────────
        function bindCouponEvents() {

            // Enter-Taste im Eingabefeld
            $(document).on('keydown', '#mic-coupon-code', function(e) {
                if ( e.which === 13 ) {
                    e.preventDefault();
                    validateCoupon();
                }
            });

            // Button-Klick
            $(document).on('click', '#mic-coupon-btn', function() {
                validateCoupon();
            });

            // Gutschein entfernen
            $(document).on('click', '#mic-coupon-remove', function() {
                removeCoupon();
            });
        }

        // ── Gutschein validieren ───────────────────────────────────────────
        function validateCoupon() {

            var code = $('#mic-coupon-code').val().trim();
            if ( ! code ) {
                showMsg('error', 'Bitte gib einen Gutscheincode ein.');
                return;
            }

            // Aktuelle Formulardaten sammeln (für serverseitige Preisberechnung)
            var formData = {};
            if ( isKinder ) {
                formData['kinder-anzahl']     = parseInt($('input[name="kinder-anzahl"]').val()) || 0;
                formData['geschwister-anzahl'] = parseInt($('input[name="geschwister-anzahl"]').val()) || 0;
            } else {
					formData['teilnehmer']        = parseInt($('input[name="teilnehmer"]').val()) || 0;
					formData['serie_buchen']      = $('input[name="serie_buchen"]:checked').val() || '';
					formData['thema_id']          = $('#thema-dropdown').find(':selected').data('thema-id') || '';
					formData['thema']             = $('#thema-dropdown').find(':selected').text().trim() || '';
					formData['calculated_price']  = $('input[name="calculated_price"]').val() || 0;
			}

            // Mindestanzahl prüfen
            var anzahl = isKinder
                ? (formData['kinder-anzahl'] || 0)
                : (formData['teilnehmer'] || 0);
            if ( anzahl < 1 ) {
                showMsg('error', 'Bitte zuerst die Anzahl der ' + (isKinder ? 'Kinder' : 'Teilnehmer') + ' angeben.');
                return;
            }
            // Bei Themen-Workshops: Thema muss gewählt sein
            if ( ! isKinder && $('#thema-dropdown').length && ! $('#thema-dropdown').val() ) {
                showMsg('error', 'Bitte zuerst ein Thema auswählen.');
                return;
            }

            $('#mic-coupon-btn').prop('disabled', true).text('Prüfe...');
            $('#mic-coupon-msg').hide().removeClass('success error');

            $.ajax({
                url:  ajaxUrl,
                type: 'POST',
                data: {
                    action:      'validate_workshop_coupon',
                    nonce:       nonceGutschein,
                    coupon_code: code,
                    workshop_id: workshopId,
                    form_data:   formData
                },
                success: function(response) {
                    $('#mic-coupon-btn').prop('disabled', false).text('Einlösen');

                    if ( response.success ) {
                        aktiverGutschein = {
                            code:          response.data.coupon_code,
                            rabatt_betrag: response.data.rabatt_betrag,
                            final_price:   response.data.final_price,
                            final_fmt:     response.data.final_price_fmt
                        };
                        showMsg('success', response.data.message);
                        $('#mic-coupon-code').prop('readonly', true);
                        $('#mic-coupon-btn').hide();
                        $('#mic-coupon-remove').show();

                        // Verstecktes CF7-Feld mit dem validierten Code befüllen
                        // (damit der Code beim Submit in den Formulardaten landet)
                        var $hidden = $('input[name="gutscheincode"]');
                        if ( $hidden.length === 0 ) {
                            $('form.wpcf7-form').prepend(
                                '<input type="hidden" name="gutscheincode" id="gutscheincode-hidden">'
                            );
                            $hidden = $('#gutscheincode-hidden');
                        }
                        $hidden.val( code );

                        // Preisanzeigen aktualisieren
                        updatePreisanzeigenNachGutschein( response.data.final_price, response.data.rabatt_betrag );
                        // PayPal-Link aktualisieren
                        updatePayPalNachGutschein( response.data.final_price );

                    } else {
                        showMsg('error', response.data.message || 'Ungültiger Gutscheincode.');
                    }
                },
                error: function() {
                    $('#mic-coupon-btn').prop('disabled', false).text('Einlösen');
                    showMsg('error', 'Verbindungsfehler. Bitte versuche es erneut.');
                }
            });
        }

        // ── Gutschein entfernen ────────────────────────────────────────────
        function removeCoupon() {
            aktiverGutschein = null;
            $('#mic-coupon-code').prop('readonly', false).val('');
            $('#mic-coupon-btn').show();
            $('#mic-coupon-remove').hide();
            $('#mic-coupon-msg').hide().removeClass('success error');
            $('input[name="gutscheincode"]').val('');

            // Gutschein-Rabatt-Zeile ausblenden (falls sichtbar)
            $('.mic-coupon-rabatt-row').hide();

            // Preisanzeigen zurücksetzen
            resetPreisanzeigen();
        }

        // ── Hilfsfunktionen: Meldungen ─────────────────────────────────────
        function showMsg(type, html) {
            $('#mic-coupon-msg')
                .removeClass('success error')
                .addClass(type)
                .html(html)
                .show();
        }

        // ── Preisanzeigen aktualisieren (Kinder-Preisrechner) ──────────────
        function updatePreisanzeigenNachGutschein(finalPrice, rabattBetrag) {

            // ─ Kinder-Preisrechner (.workshop-price-calculator) ─
            var $rechnerKinder = $('.workshop-price-calculator');
            if ( $rechnerKinder.length > 0 && $rechnerKinder.is(':visible') ) {

                // Gutschein-Zeile einfügen (falls noch nicht vorhanden)
                if ( $rechnerKinder.find('.mic-coupon-rabatt-row').length === 0 ) {
                    $rechnerKinder.find('.price-breakdown').append(
                        '<div class="price-row mic-coupon-rabatt-row" style="color:#4caf50;">' +
                            '<div class="price-row-label">🏷️ Gutschein</div>' +
                            '<div class="price-row-value" id="mic-coupon-abzug" style="color:#4caf50;">- 0,00 €</div>' +
                        '</div>'
                    );
                }
                $rechnerKinder.find('.mic-coupon-rabatt-row').show();
                $rechnerKinder.find('#mic-coupon-abzug').text('- ' + rabattBetrag.toFixed(2).replace('.', ',') + ' €');
                $rechnerKinder.find('#price-total').text(finalPrice.toFixed(2).replace('.', ',') + ' €');
            }

            // ─ Erwachsenen-Preisrechner (.workshop-price-calculator-adult) ─
            var $rechnerAdult = $('.workshop-price-calculator-adult');
            if ( $rechnerAdult.length > 0 && $rechnerAdult.is(':visible') ) {

                if ( $rechnerAdult.find('.mic-coupon-rabatt-row').length === 0 ) {
                    $rechnerAdult.find('.price-breakdown-adult').append(
                        '<div class="price-row-adult mic-coupon-rabatt-row" style="color:#4caf50;">' +
                            '<div class="price-row-label-adult">🏷️ Gutschein</div>' +
                            '<div class="price-row-value-adult" id="mic-coupon-abzug-adult" style="color:#4caf50;">- 0,00 €</div>' +
                        '</div>'
                    );
                }
                $rechnerAdult.find('.mic-coupon-rabatt-row').show();
                $rechnerAdult.find('#mic-coupon-abzug-adult').text('- ' + rabattBetrag.toFixed(2).replace('.', ',') + ' €');
                $rechnerAdult.find('#price-total-adult').text(finalPrice.toFixed(2).replace('.', ',') + ' €');
            }
        }

        function resetPreisanzeigen() {
            // Trigger normalen Preisrechner-Update
            $('input[name="kinder-anzahl"], input[name="geschwister-anzahl"]').trigger('change');
            $('input[name="teilnehmer"]').trigger('change');
        }

        // ── PayPal-Link aktualisieren ──────────────────────────────────────
        function updatePayPalNachGutschein(finalPrice) {

            if ( finalPrice <= 0 ) {
                // Preis = 0 → PayPal-Button ausblenden, Hinweis zeigen
                $('.paypal-button-wrapper').slideUp();
                return;
            }

            var paypalUrl = 'https://www.paypal.com/cgi-bin/webscr?' + $.param({
                cmd:           '_xclick',
                business:      paypalEmail,
                item_name:     workshopTitle,
                amount:        finalPrice.toFixed(2),
                currency_code: 'EUR',
                no_shipping:   '1',
                return:        window.location.href + '?payment=success',
                cancel_return: window.location.href + '?payment=cancelled'
            });

            // Kinder-Button  (#dynamic-paypal-link)
            $('#dynamic-paypal-link').attr('href', paypalUrl);
            $('#paypal-price-display').text(
                finalPrice.toFixed(2).replace('.', ',') + ' €'
            );

            // Erwachsenen-Button (#dynamic-paypal-link-adult)
            $('#dynamic-paypal-link-adult').attr('href', paypalUrl);
            $('#paypal-price-display-adult').text(
                finalPrice.toFixed(2).replace('.', ',') + ' €'
            );

            $('.paypal-button-wrapper').slideDown();
            // calculated_price-Hidden-Field befüllen (wird beim CF7-Submit mitsent)
            $('input[name="calculated_price"]').val(finalPrice.toFixed(2));
        }

        // ── Wenn Anzahl sich ändert: Gutschein-Abzug neu berechnen ─────────
        // (der Basis-Preis ändert sich → Gutschein muss neu berechnet werden)
        $(document).on('input change',
            'input[name="kinder-anzahl"], input[name="geschwister-anzahl"], input[name="teilnehmer"]',
            function() {
                if ( aktiverGutschein ) {
                    // Gutschein neu validieren mit aktuellen Werten
                    setTimeout(function() {
                        validateCoupon();
                    }, 400);
                }
            }
        );

    }); // end jQuery ready
    </script>
    <?php
}


// ============================================================================
// TEIL 6: CF7-VALIDIERUNG – Gutscheincode beim Submit server-seitig prüfen
// Verhindert, dass ein ungültiger oder bereits eingelöster Code durchkommt.
// ============================================================================

add_filter( 'wpcf7_validate_text',  'micinterart_validate_gutscheincode_field', 20, 2 );
add_filter( 'wpcf7_validate_text*', 'micinterart_validate_gutscheincode_field', 20, 2 );

function micinterart_validate_gutscheincode_field( $result, $tag ) {

    if ( $tag->name !== 'gutscheincode' ) {
        return $result;
    }

    $code = isset( $_POST['gutscheincode'] ) ? sanitize_text_field( trim( $_POST['gutscheincode'] ) ) : '';

    // Leer → erlaubt (optionales Feld)
    if ( empty( $code ) ) {
        return $result;
    }

    $coupon = new WC_Coupon( $code );

    if ( ! $coupon->get_id() ) {
        $result->invalidate( $tag, 'Dieser Gutscheincode ist nicht gültig.' );
        return $result;
    }

    // Abgelaufen?
    $expiry = $coupon->get_date_expires();
    if ( $expiry && $expiry->getTimestamp() < time() ) {
        $result->invalidate( $tag, 'Dieser Gutscheincode ist leider abgelaufen.' );
        return $result;
    }

    // Nutzungslimit?
    $usage_limit = $coupon->get_usage_limit();
    $usage_count = $coupon->get_usage_count();
    if ( $usage_limit > 0 && $usage_count >= $usage_limit ) {
        $result->invalidate( $tag, 'Dieser Gutscheincode wurde bereits eingelöst.' );
        return $result;
    }

    return $result;
}

