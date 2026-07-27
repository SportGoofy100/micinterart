<?php
/**
 * Workshop Preisrechner: PayPal-Link in Mail, dynamische PayPal-Buttons,
 * Kinder-Preisrechner (Live), Erwachsenen-Preisrechner (Live)
 *
 * Abhaengig von: workshop-rabatt.php (micinterart_calculate_final_price)
 *
 * @package Micinterart
 */


/**
 * PayPal-Link in CF7 E-Mail einfügen
 */
if (!function_exists('micinterart_cf7_paypal_link')) {
    function micinterart_cf7_paypal_link($components, $cf7, $mail) {
        // Prüfen ob [paypal_link] überhaupt in der Mail vorkommt
        if (strpos($components['body'], '[paypal_link]') === false) {
            return $components;
        }
        
        $submission = WPCF7_Submission::get_instance();
        if (!$submission) {
            $components['body'] = str_replace(
                '[paypal_link]', 
                '❌ Fehler: Keine Submission gefunden', 
                $components['body']
            );
            return $components;
        }
        
        // Workshop-ID aus unit_tag extrahieren
        $workshop_id = 0;
        $unit_tag = $submission->get_meta('unit_tag');
        
        // Unit-Tag Format: wpcf7-f{FORM_ID}-p{POST_ID}-o{COUNTER}
        if (preg_match('/wpcf7-f(\d+)-p(\d+)-o(\d+)/', $unit_tag, $matches)) {
            $workshop_id = intval($matches[2]);
        }
        
        // Fallback: Aus workshop_id Formularfeld
        if ($workshop_id <= 0) {
            $posted_data = $submission->get_posted_data();
            if (isset($posted_data['workshop_id']) && !empty($posted_data['workshop_id'])) {
                $workshop_id = intval($posted_data['workshop_id']);
            }
        }
        
        // Keine Workshop-ID gefunden
        if ($workshop_id <= 0) {
            $components['body'] = str_replace(
                '[paypal_link]', 
                '❌ Workshop-ID konnte nicht ermittelt werden (Unit-Tag: ' . $unit_tag . ')', 
                $components['body']
            );
            return $components;
        }
        
        // Prüfen ob es ein Workshop-Post ist
        if (get_post_type($workshop_id) !== 'workshop') {
            $components['body'] = str_replace(
                '[paypal_link]', 
                '❌ Post-ID ' . $workshop_id . ' ist kein Workshop', 
                $components['body']
            );
            return $components;
        }
        
        // PayPal E-Mail
        $paypal_email = defined('MICINTERART_PAYPAL_EMAIL') 
            ? MICINTERART_PAYPAL_EMAIL 
            : get_option('micinterart_paypal_email', 'info@micinterart.de');
        
        if (empty($paypal_email)) {
            $components['body'] = str_replace(
                '[paypal_link]', 
                '❌ PayPal E-Mail nicht konfiguriert', 
                $components['body']
            );
            return $components;
        }
        
        // Preis berechnen
        // Priorität 1: calculated_price aus dem Formular (vom Frontend korrekt berechnet)
        // Priorität 2: serverseitige Neuberechnung als Fallback
        $posted_data = $submission->get_posted_data();
        if ( isset( $posted_data['calculated_price'] ) && floatval( $posted_data['calculated_price'] ) > 0 ) {
            $final_price = floatval( $posted_data['calculated_price'] );
        } else {
            $final_price = micinterart_calculate_final_price($workshop_id, $posted_data);
        // Preis berechnen
        $posted_data = $submission->get_posted_data();
        if ( isset( $posted_data['calculated_price'] ) && floatval( $posted_data['calculated_price'] ) > 0 ) {
            // Priorität 1: Vom Frontend berechneter Preis (zuverlässigste Quelle)
            $final_price = floatval( $posted_data['calculated_price'] );
        } else {
            // Priorität 2: Serverseitige Neuberechnung
            $final_price = micinterart_calculate_final_price($workshop_id, $posted_data);
        }

        // Priorität 3: Thema-Preis als Fallback (bei Themen-Workshops ohne calculated_price)
        if ( $final_price <= 0 ) {
            $thema_name = isset( $posted_data['thema'] ) ? trim( $posted_data['thema'] ) : '';
            if ( !empty( $thema_name ) ) {
                // Thema anhand des Titels suchen
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
                if ( !empty( $thema_posts ) ) {
                    $thema_preis_raw = get_post_meta( $thema_posts[0]->ID, '_thema_preis', true );
                    if ( !empty( $thema_preis_raw ) ) {
                        $final_price = floatval( preg_replace( '/[^0-9.]/', '', str_replace( ',', '.', $thema_preis_raw ) ) );
                    }
                }
            }
            // Letzter Fallback: _workshop_preis direkt
            if ( $final_price <= 0 ) {
                $final_price = floatval( get_post_meta( $workshop_id, '_workshop_preis', true ) );
            }
        }
            }
        
        if ($final_price <= 0) {
            $components['body'] = str_replace(
                '[paypal_link]', 
                '❌ Preis konnte nicht berechnet werden', 
                $components['body']
            );
            return $components;
        }
        
        // Workshop-Titel
        $thema_id = isset($posted_data['thema_id']) ? intval($posted_data['thema_id']) : 0;
        if ($thema_id > 0 && get_post_type($thema_id) === 'workshop_thema') {
            $workshop_title = get_the_title($thema_id);
        } else {
            $workshop_title = get_the_title($workshop_id);
        }
        
        // PayPal-Parameter
        $params = array(
            'cmd' => '_xclick',
            'business' => $paypal_email,
            'item_name' => $workshop_title,
            'amount' => number_format($final_price, 2, '.', ''),
            'currency_code' => 'EUR',
            'no_shipping' => '1',
            'return' => get_permalink($workshop_id) . '?payment=success',
            'cancel_return' => get_permalink($workshop_id) . '?payment=cancelled',
            'notify_url' => home_url('/paypal-ipn/'),
        );
        
        // PayPal-URL zusammenbauen
        $paypal_link = 'https://www.paypal.com/cgi-bin/webscr?' . http_build_query($params);
        
        // In E-Mail einfügen
        $components['body'] = str_replace('[paypal_link]', $paypal_link, $components['body']);
        $components['subject'] = str_replace('[paypal_link]', $paypal_link, $components['subject']);
        
        return $components;
    }
    add_filter('wpcf7_mail_components', 'micinterart_cf7_paypal_link', 10, 3);
}

/**
 * Dynamischer PayPal-Button im Formular
 */
if (!function_exists('micinterart_add_paypal_button_to_form')) {
    function micinterart_add_paypal_button_to_form() {
        if (!is_singular('workshop')) return;
        
        // Nur wenn PayPal aktiviert ist
        if (!defined('MICINTERART_PAYPAL_EMAIL') && empty(get_option('micinterart_paypal_email'))) {
            return;
        }
        
        $post_id = get_the_ID();
        $preis = floatval(get_post_meta($post_id, '_workshop_preis', true));
        
        if ($preis <= 0) return;
        
        ?>
        <style>
        .paypal-button-wrapper {
            margin: 25px 0;
            padding: 20px;
            background: linear-gradient(135deg, #fef9f3 0%, #fff 100%);
            border: 2px solid #0070ba;
            border-radius: 10px;
            text-align: center;
        }
        .paypal-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #0070ba;
            color: #fff !important;
            padding: 15px 35px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 112, 186, 0.3);
            border: none;
            cursor: pointer;
        }
        .paypal-button:hover {
            background: #005ea6;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 112, 186, 0.4);
            color: #fff !important;
        }
        .paypal-icon {
            width: 24px;
            height: 24px;
        }
        .paypal-hinweis {
            margin-top: 15px;
            font-size: 0.9em;
            color: #666;
        }
        .paypal-divider {
            width: 100%;
            text-align: center;
            border-bottom: 1px solid #ddd;
            line-height: 0.1em;
            margin: 25px 0 20px;
        }
        .paypal-divider span {
            background: #fff;
            padding: 0 10px;
            color: #999;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            const workshopId = <?php echo $post_id; ?>;
            
            $('.paypal-button-wrapper').hide();
            
            function updatePayPalLink() {
                const kinderAnzahl = parseInt($('input[name="kinder-anzahl"]').val()) || 0;
                const geschwisterAnzahl = parseInt($('input[name="geschwister-anzahl"]').val()) || 0;
                
                if (kinderAnzahl === 0) {
                    $('.paypal-button-wrapper').slideUp();
                    return;
                }
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'calculate_workshop_price',
                        nonce: '<?php echo wp_create_nonce('micinterart_price_calc'); ?>',
                        workshop_id: workshopId,
                        data: {
                            'kinder-anzahl': kinderAnzahl,
                            'geschwister-anzahl': geschwisterAnzahl
                        }
                    },
                    success: function(response) {
                        if (response.success && response.data.preis_raw > 0) {
                            const paypalEmail = '<?php echo defined('MICINTERART_PAYPAL_EMAIL') ? MICINTERART_PAYPAL_EMAIL : get_option('micinterart_paypal_email'); ?>';
                            const workshopTitle = '<?php echo addslashes(get_the_title($post_id)); ?>';
                            const price = response.data.preis_raw;
                            
                            const paypalUrl = 'https://www.paypal.com/cgi-bin/webscr?' + $.param({
                                cmd: '_xclick',
                                business: paypalEmail,
                                item_name: workshopTitle,
                                amount: price.toFixed(2),
                                currency_code: 'EUR',
                                no_shipping: '1',
                                return: window.location.href + '?payment=success',
                                cancel_return: window.location.href + '?payment=cancelled'
                            });
                            
                            $('#dynamic-paypal-link').attr('href', paypalUrl);
                            $('#paypal-price-display').text(response.data.preis + ' €');
                            $('.paypal-button-wrapper').slideDown();
                            // calculated_price-Hidden-Field befüllen (wird beim CF7-Submit mitsent)
                            $('input[name="calculated_price"]').val(price.toFixed(2));
                        }
                    }
                });
            }
            
            $('input[name="kinder-anzahl"], input[name="geschwister-anzahl"]').on('change input', updatePayPalLink);
            updatePayPalLink();
        });
        </script>
        
        <div class="paypal-divider">
            <span>ODER</span>
        </div>
        
        <div class="paypal-button-wrapper">
            <h4 style="margin: 0 0 15px 0; color: #2c2c2c;">💳 Direkt online bezahlen</h4>
            <a href="#" id="dynamic-paypal-link" class="paypal-button" target="_blank" rel="noopener">
                <svg class="paypal-icon" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20.067 8.478c.492.88.556 2.014.3 3.327-.74 3.806-3.276 5.12-6.514 5.12h-.5a.805.805 0 00-.794.68l-.04.22-.63 3.993-.032.17a.804.804 0 01-.794.68H7.72a.483.483 0 01-.477-.558L7.418 21h1.518l.95-6.02h1.385c4.678 0 7.75-2.203 8.796-6.502z"/>
                    <path d="M2.697 20.535l1.18-7.482a.783.783 0 01.772-.66h4.627c.313 0 .612.034.893.098a4.97 4.97 0 00-.37 1.88c0 2.754 2.24 5.164 5.637 5.164.177 0 .352-.008.525-.022l-.929 5.887H7.72a.483.483 0 01-.477-.558l.18-1.142H2.697z"/>
                </svg>
                Jetzt mit PayPal bezahlen (<span id="paypal-price-display">0,00 €</span>)
            </a>
            <p class="paypal-hinweis">
                🔒 Sichere Zahlung über PayPal<br>
                <small>Nach der Zahlung erhältst du eine Bestätigung per E-Mail</small>
            </p>
        </div>
        <?php
    }
    add_action('wp_footer', 'micinterart_add_paypal_button_to_form');
}
/**
 * Live-Preisanzeige direkt nach dem CF7-Formular einfügen
 */
if (!function_exists('micinterart_add_price_display_to_form')) {
    function micinterart_add_price_display_to_form() {
        if (!is_singular('workshop')) return;
        
        $post_id = get_the_ID();
        $preis = floatval(get_post_meta($post_id, '_workshop_preis', true));
        $geschwister_rabatt = floatval(get_post_meta($post_id, '_workshop_geschwister_rabatt', true));
        $fruehbucher_rabatt = floatval(get_post_meta($post_id, '_workshop_fruehbucher_rabatt', true));
        $fruehbucher_bis = get_post_meta($post_id, '_workshop_fruehbucher_bis', true);
        
        if ($preis <= 0) return;
        
        // Frühbucher aktiv?
        $fruehbucher_aktiv = false;
        if (!empty($fruehbucher_bis)) {
            $bis_date = DateTime::createFromFormat('Y-m-d', $fruehbucher_bis);
            $heute = new DateTime();
            $fruehbucher_aktiv = ($bis_date && $heute <= $bis_date);
        }
        
        ?>
        <style>
        .workshop-price-calculator {
            background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%);
            color: #fff;
            padding: 30px;
            border-radius: 12px;
            margin: 30px 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border: 2px solid #d4a574;
        }
        .price-calculator-header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #d4a574;
        }
        .price-calculator-header h3 {
            margin: 0 0 8px 0;
            font-size: 1.8em;
            color: #d4a574;
            font-family: 'Bebas Neue', 'Arial', sans-serif;
            letter-spacing: 2px;
        }
        .price-calculator-header p {
            margin: 0;
            color: #ccc;
            font-size: 0.95em;
        }
        .price-breakdown {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(212, 165, 116, 0.2);
        }
        .price-row:last-child {
            border-bottom: none;
        }
        .price-row-label {
            font-weight: 500;
            color: #fff;
        }
        .price-row-value {
            font-weight: 700;
            font-size: 1.2em;
            color: #d4a574;
        }
        .price-total {
            background: #d4a574;
            color: #2c2c2c;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        .price-total-label {
            font-size: 0.95em;
            font-weight: 600;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .price-total-value {
            font-size: 2.8em;
            font-weight: 900;
            line-height: 1;
            font-family: 'Bebas Neue', 'Arial', sans-serif;
            letter-spacing: 1px;
        }
        .price-discount-badge {
            display: inline-block;
            background: #d4a574;
            color: #2c2c2c;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 700;
            margin-left: 10px;
        }
        .price-info-text {
            text-align: center;
            margin-top: 15px;
            font-size: 0.9em;
            color: #d4a574;
            font-weight: 600;
        }
        .price-hidden {
            display: none !important;
        }
        
        @media (max-width: 768px) {
            .workshop-price-calculator {
                padding: 20px;
            }
            .price-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            .price-total-value {
                font-size: 2.2em;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            const workshopId = <?php echo $post_id; ?>;
            const basisPreis = <?php echo $preis; ?>;
            const geschwisterRabatt = <?php echo $geschwister_rabatt; ?>;
            const fruehbucherRabatt = <?php echo $fruehbucher_rabatt; ?>;
            const fruehbucherAktiv = <?php echo $fruehbucher_aktiv ? 'true' : 'false'; ?>;
            
            // Preisrechner-HTML erstellen
            const preisrechnerHTML = `
                <div class="workshop-price-calculator" style="display:none;">
                    <div class="price-calculator-header">
                        <h3>💰 Dein Preis</h3>
                        <p>Live-Berechnung mit allen Rabatten</p>
                    </div>
                    
                    <div class="price-breakdown">
                        <div class="price-row">
                            <div class="price-row-label">
                                📊 <span id="price-basis-anzahl">0 × 0,00 €</span>
                            </div>
                            <div class="price-row-value" id="price-basis-summe">0,00 €</div>
                        </div>
                        
                        <div class="price-row price-hidden" id="price-geschwister-row">
                            <div class="price-row-label">
                                👨‍👩‍👧‍👦 Geschwister-Rabatt
                                <span class="price-discount-badge" id="price-geschwister-anzahl">0 × 0,00 €</span>
                            </div>
                            <div class="price-row-value" id="price-geschwister-summe">- 0,00 €</div>
                        </div>
                        
                        <div class="price-row price-hidden" id="price-fruehbucher-row">
                            <div class="price-row-label">
                                ⚡ Frühbucher-Rabatt
                                <span class="price-discount-badge" id="price-fruehbucher-prozent">0%</span>
                            </div>
                            <div class="price-row-value" id="price-fruehbucher-summe">- 0,00 €</div>
                        </div>
                    </div>
                    
                    <div class="price-total">
                        <div class="price-total-label">Gesamt-Preis</div>
                        <div class="price-total-value" id="price-total">0,00 €</div>
                    </div>
                    
                    <div class="price-info-text" id="price-info-text"></div>
                </div>
            `;
            
            // Preisrechner direkt nach dem CF7-Formular einfügen
            function insertPreisrechner() {
                if ($('.workshop-price-calculator').length > 0) {
                    return; // Bereits eingefügt
                }
                
                const cf7Form = $('.wpcf7-form');
                if (cf7Form.length > 0) {
                    cf7Form.after(preisrechnerHTML);
                    console.log('✅ Preisrechner nach CF7-Formular eingefügt');
                } else {
                    // Fallback: Nach wpcf7-Container
                    const cf7Container = $('.wpcf7');
                    if (cf7Container.length > 0) {
                        cf7Container.after(preisrechnerHTML);
                        console.log('✅ Preisrechner nach CF7-Container eingefügt');
                    }
                }
            }
            
            // Einfügen sobald DOM bereit
            insertPreisrechner();
            
            // Auch nach WPCF7-Initialisierung nochmal versuchen
            $(document).on('wpcf7:ready', function() {
                setTimeout(insertPreisrechner, 100);
            });
            
            function updatePreisAnzeige() {
                const kinderAnzahl = parseInt($('input[name="kinder-anzahl"]').val()) || 0;
                const geschwisterAnzahl = parseInt($('input[name="geschwister-anzahl"]').val()) || 0;
                
                if (kinderAnzahl === 0) {
                    $('.workshop-price-calculator').slideUp();
                    return;
                }
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'calculate_workshop_price',
                        nonce: '<?php echo wp_create_nonce('micinterart_price_calc'); ?>',
                        workshop_id: workshopId,
                        data: {
                            'kinder-anzahl': kinderAnzahl,
                            'geschwister-anzahl': geschwisterAnzahl
                        }
                    },
                    success: function(response) {
    console.log('✅ AJAX Response:', response);
    
    if (response.success) {
        const data = response.data;
        
        // Basis-Preis Anzeige
        $('#price-basis-anzahl').text(kinderAnzahl + ' × ' + basisPreis.toFixed(2) + ' €');
        $('#price-basis-summe').text(data.basis_preis_raw.toFixed(2) + ' €');
        
        // ========================================
        // GESCHWISTER-RABATT ANZEIGEN
        // ========================================
        if (data.geschwister_rabatt_raw > 0) {
            $('#price-geschwister-row').removeClass('price-hidden');
            
            // Rabatt pro Kind berechnen
            const rabattProKind = data.geschwister_rabatt_raw / geschwisterAnzahl;
            
            $('#price-geschwister-anzahl').text(
                geschwisterAnzahl + ' × ' + rabattProKind.toFixed(2) + ' €'
            );
            $('#price-geschwister-summe').text('- ' + data.geschwister_rabatt_raw.toFixed(2) + ' €');
        } else {
            $('#price-geschwister-row').addClass('price-hidden');
        }
        
        // ========================================
        // FRÜHBUCHER-RABATT ANZEIGEN
        // ========================================
        if (data.fruehbucher_rabatt_raw > 0) {
            $('#price-fruehbucher-row').removeClass('price-hidden');
            
            // Prozent berechnen (Rabatt / Preis vor Frühbucher-Rabatt * 100)
            const preisVorFruehbucher = data.preis_raw + data.fruehbucher_rabatt_raw;
            const fruehbucherProzent = Math.round(
                (data.fruehbucher_rabatt_raw / preisVorFruehbucher) * 100
            );
            
            $('#price-fruehbucher-prozent').text(fruehbucherProzent + '%');
            $('#price-fruehbucher-summe').text('- ' + data.fruehbucher_rabatt_raw.toFixed(2) + ' €');
        } else {
            $('#price-fruehbucher-row').addClass('price-hidden');
        }
        
        // Gesamt-Preis
        $('#price-total').text(data.preis_raw.toFixed(2) + ' €');
        
        // Ersparnis-Text
        let infoText = '';
        if (data.ersparnis_raw > 0) {
            infoText = '🎉 Sie sparen ' + data.ersparnis_raw.toFixed(2) + ' €!';
        }
        $('#price-info-text').text(infoText);
        
        $('.workshop-price-calculator').slideDown();
    }
},

                    error: function(xhr, status, error) {
                        console.error('❌ AJAX Fehler:', error);
                    }
                });
            }
            
            // Event-Listener mit Delegation
            $(document).on('change input', 'input[name="kinder-anzahl"], input[name="geschwister-anzahl"]', function() {
                updatePreisAnzeige();
            });
            
            // Initial-Berechnung
            setTimeout(updatePreisAnzeige, 500);
        });
        </script>
        <?php
    }
    add_action('wp_footer', 'micinterart_add_price_display_to_form', 15);
}

// ============================================================================
// PREISRECHNER FÜR ERWACHSENEN-WORKSHOPS (LIVE-BERECHNUNG)
// ============================================================================

add_action('wp_footer', 'micinterart_add_adult_workshop_price_calculator');

function micinterart_add_adult_workshop_price_calculator() {
    if (!is_singular('workshop')) {
        return;
    }

    $post_id = get_the_ID();

    // Nicht für Kinderworkshops (die haben eigenen Rechner)
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
    if ($is_kinderworkshop) {
        return;
    }

    // Basis-Preis vom Haupt-Workshop
    $preis = floatval(get_post_meta($post_id, '_workshop_preis', true));

    // Themen-Preise ermitteln
    $themen_preise_map = []; // [ thema_id => preis_float ]
    $themen = get_posts([
        'post_type'      => 'workshop_thema',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_query'     => [[
            'key'     => '_thema_workshop_id',
            'value'   => $post_id,
            'compare' => '='
        ]],
    ]);

    foreach ($themen as $thema) {
        $thema_preis_raw = get_post_meta($thema->ID, '_thema_preis', true);
        if (!empty($thema_preis_raw)) {
            $p = floatval(preg_replace('/[^0-9.]/', '', str_replace(',', '.', $thema_preis_raw)));
            if ($p > 0) {
                $themen_preise_map[$thema->ID] = $p;
            }
        }
    }

    $hat_themen = !empty($themen_preise_map);

    // Wenn weder Basis-Preis noch Themenpreise → kein Rechner
    if ($preis <= 0 && !$hat_themen) {
        return;
    }

    $is_paar = get_post_meta($post_id, '_workshop_is_paar_preis', true);

    ?>
    <style>
    .workshop-price-calculator-adult {
        background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%);
        padding: 30px;
        border-radius: 12px;
        margin: 30px 0;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        color: white;
        display: none;
    }
    .price-calculator-header-adult h3 {
        margin: 0 0 8px 0;
        font-size: 1.8em;
        color: white;
    }
    .price-calculator-header-adult p {
        margin: 0 0 20px 0;
        opacity: 0.9;
        font-size: 0.95em;
    }
    .price-breakdown-adult {
        background: rgba(255,255,255,0.05);
        padding: 20px;
        border-radius: 8px;
        backdrop-filter: blur(10px);
    }
    .price-row-adult {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .price-row-adult:last-child { border-bottom: none; }
    .price-row-label-adult {
        font-size: 1em;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .price-row-value-adult {
        font-size: 1.2em;
        font-weight: 600;
    }
    .price-total-adult {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 2px solid #d4a574;
        text-align: center;
    }
    .price-total-label-adult { font-size: 1em; opacity: 0.9; margin-bottom: 8px; }
    .price-total-value-adult { font-size: 2.8em; font-weight: 700; color: #d4a574; }
    .price-info-text-adult { text-align: center; margin-top: 15px; font-size: 0.9em; color: #d4a574; font-weight: 600; }
    .price-paar-badge { background: #d4a574; color: #2c2c2c; padding: 4px 12px; border-radius: 20px; font-size: 0.85em; font-weight: 700; margin-left: 10px; }
    @media (max-width: 768px) {
        .workshop-price-calculator-adult { padding: 20px; }
        .price-total-value-adult { font-size: 2.2em; }
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        const workshopId = <?php echo $post_id; ?>;
        const basisPreis = <?php echo $preis > 0 ? $preis : 0; ?>;
        const isPaar     = <?php echo $is_paar ? 'true' : 'false'; ?>;
        const hatThemen  = <?php echo $hat_themen ? 'true' : 'false'; ?>;

        // Map: thema_id (string) → preis (float)
        const themenPreise = <?php echo json_encode(array_map('floatval', $themen_preise_map)); ?>;

        const preisrechnerHTML = `
            <div class="workshop-price-calculator-adult">
                <div class="price-calculator-header-adult">
                    <h3>💰 Dein Preis</h3>
                    <p>Live-Berechnung basierend auf deiner Auswahl</p>
                </div>
                <div class="price-breakdown-adult">
                    <div class="price-row-adult">
                        <div class="price-row-label-adult">
                            <span id="price-basis-anzahl-adult">0 × 0,00 €</span>
                            ${isPaar ? '<span class="price-paar-badge">Paarpreis</span>' : ''}
                        </div>
                        <div class="price-row-value-adult" id="price-basis-summe-adult">0,00 €</div>
                    </div>
                </div>
                <div class="price-total-adult">
                    <div class="price-total-label-adult">Gesamt-Preis</div>
                    <div class="price-total-value-adult" id="price-total-adult">0,00 €</div>
                </div>
                <div class="price-info-text-adult" id="price-info-text-adult"></div>
            </div>
        `;

        function insertPreisrechner() {
            if ($('.workshop-price-calculator-adult').length > 0) return;
            const cf7Form = $('.wpcf7-form');
            if (cf7Form.length > 0) {
                cf7Form.after(preisrechnerHTML);
            }
        }

        insertPreisrechner();
        $(document).on('wpcf7:ready', function() { setTimeout(insertPreisrechner, 100); });

        /**
         * Aktiven Preis ermitteln:
         * Wenn Themen vorhanden → Preis des gewählten Themas aus data-thema-id,
         * Fallback auf basisPreis.
         */
        function getAktivenPreis() {
            if (!hatThemen) return basisPreis;

            const $select = $('#thema-dropdown');
            if (!$select.length) return basisPreis;

            const selectedOption = $select.find(':selected');
            const themaId = selectedOption.data('thema-id');

            if (themaId && themenPreise[themaId] > 0) {
                return themenPreise[themaId];
            }

            return basisPreis;
        }

        function updatePreisAnzeige() {
            const teilnehmerAnzahl = parseInt($('input[name="teilnehmer"]').val()) || 0;

            if (teilnehmerAnzahl === 0) {
                $('.workshop-price-calculator-adult').slideUp();
                return;
            }

            // Bei Themen: Preis erst anzeigen wenn ein Thema gewählt ist
            if (hatThemen) {
                const $select = $('#thema-dropdown');
                if ($select.length && !$select.val()) {
                    $('.workshop-price-calculator-adult').slideUp();
                    return;
                }
            }

            const aktiverPreis = getAktivenPreis();

            if (aktiverPreis <= 0) {
                $('.workshop-price-calculator-adult').slideUp();
                return;
            }

            let gesamtPreis = 0;
            let anzeigeText = '';

            if (isPaar) {
                gesamtPreis = aktiverPreis;
                anzeigeText = 'Paarpreis (für 2 Personen)';
                $('#price-basis-anzahl-adult').html(anzeigeText);
            } else {
                gesamtPreis = aktiverPreis * teilnehmerAnzahl;
                anzeigeText = teilnehmerAnzahl + ' × ' + aktiverPreis.toFixed(2).replace('.', ',') + ' €';
                $('#price-basis-anzahl-adult').text(anzeigeText);
            }

            $('#price-basis-summe-adult').text(gesamtPreis.toFixed(2).replace('.', ',') + ' €');
            $('#price-total-adult').text(gesamtPreis.toFixed(2).replace('.', ',') + ' €');

            if (isPaar && teilnehmerAnzahl > 2) {
                $('#price-info-text-adult').text('ℹ️ Paarpreis gilt für maximal 2 Personen');
            } else {
                $('#price-info-text-adult').text('');
            }
            // calculated_price für CF7-Submit und PayPal-Mail mitschicken
            $('input[name="calculated_price"]').val(gesamtPreis.toFixed(2));

            $('.workshop-price-calculator-adult').slideDown();
        }

        // Teilnehmer-Feld
        $(document).on('input change', 'input[name="teilnehmer"]', updatePreisAnzeige);

        // Thema-Dropdown (wird dynamisch eingefügt, daher Delegation)
        $(document).on('change', '#thema-dropdown', updatePreisAnzeige);

        setTimeout(updatePreisAnzeige, 500);
    });
    </script>
    <?php
}

add_action('wp_footer', 'micinterart_add_adult_workshop_price_calculator');

/**
 * [workshop_datum] und [workshop_uhrzeit] in CF7-Mails ersetzen
 */
if ( ! function_exists( 'micinterart_cf7_workshop_datum' ) ) {
    function micinterart_cf7_workshop_datum( $components, $cf7, $mail ) {
        $needs_datum   = strpos( $components['body'], '[workshop_datum]' )   !== false;
        $needs_uhrzeit = strpos( $components['body'], '[workshop_uhrzeit]' ) !== false;
        $needs_preis   = strpos( $components['body'], '[workshop_preis_text]' ) !== false;

        if ( ! $needs_datum && ! $needs_uhrzeit && ! $needs_preis ) {
            return $components;
        }

        $submission = WPCF7_Submission::get_instance();
        if ( ! $submission ) return $components;

        // Workshop-ID aus unit_tag
        $workshop_id = 0;
        $unit_tag = $submission->get_meta( 'unit_tag' );
        if ( preg_match( '/wpcf7-f(\d+)-p(\d+)-o(\d+)/', $unit_tag, $matches ) ) {
            $workshop_id = intval( $matches[2] );
        }
        if ( $workshop_id <= 0 ) return $components;

        $posted_data = $submission->get_posted_data();
        $thema_name  = isset( $posted_data['thema'] ) ? trim( $posted_data['thema'] ) : '';

        $datum_text   = '';
        $uhrzeit_text = '';
        $preis_text   = '';

        // Thema-Daten suchen (wenn ein Thema gewählt wurde)
        if ( ! empty( $thema_name ) ) {
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

            if ( ! empty( $thema_posts ) ) {
                $thema_id = $thema_posts[0]->ID;

                // Datum
                $thema_datum = get_post_meta( $thema_id, '_thema_datum', true );
                if ( ! empty( $thema_datum ) ) {
                    $datum_text = date_i18n( 'l, d. F Y', strtotime( $thema_datum ) );
                }

                // Uhrzeit (Thema → Fallback Workshop)
                $von = get_post_meta( $thema_id, '_thema_uhrzeit_von', true )
                    ?: get_post_meta( $workshop_id, '_workshop_uhrzeit_von', true );
                $bis = get_post_meta( $thema_id, '_thema_uhrzeit_bis', true )
                    ?: get_post_meta( $workshop_id, '_workshop_uhrzeit_bis', true );
                if ( $von ) {
                    $uhrzeit_text = $von . ( $bis ? ' – ' . $bis : '' ) . ' Uhr';
                }

                // Preis
                $thema_preis_raw = get_post_meta( $thema_id, '_thema_preis', true );
                if ( ! empty( $thema_preis_raw ) ) {
                    $preis_text = $thema_preis_raw;
                }
            }
        }

        // Fallback auf Haupt-Workshop wenn noch leer
        if ( empty( $datum_text ) ) {
            $ws_datum = get_post_meta( $workshop_id, '_workshop_datum', true );
            if ( ! empty( $ws_datum ) ) {
                $datum_text = date_i18n( 'l, d. F Y', strtotime( $ws_datum ) );
            }
        }
        if ( empty( $uhrzeit_text ) ) {
            $von = get_post_meta( $workshop_id, '_workshop_uhrzeit_von', true );
            $bis = get_post_meta( $workshop_id, '_workshop_uhrzeit_bis', true );
            if ( $von ) {
                $uhrzeit_text = $von . ( $bis ? ' – ' . $bis : '' ) . ' Uhr';
            }
        }
        if ( empty( $preis_text ) ) {
            $preis_text = get_post_meta( $workshop_id, '_workshop_preis', true );
        }

        // Ersetzen
        if ( $needs_datum ) {
            $components['body']    = str_replace( '[workshop_datum]',      $datum_text ?: '(kein Datum)',     $components['body'] );
            $components['subject'] = str_replace( '[workshop_datum]',      $datum_text ?: '(kein Datum)',     $components['subject'] );
        }
        if ( $needs_uhrzeit ) {
            $components['body']    = str_replace( '[workshop_uhrzeit]',    $uhrzeit_text ?: '(keine Uhrzeit)', $components['body'] );
            $components['subject'] = str_replace( '[workshop_uhrzeit]',    $uhrzeit_text ?: '(keine Uhrzeit)', $components['subject'] );
        }
        if ( $needs_preis ) {
            $components['body']    = str_replace( '[workshop_preis_text]', $preis_text ?: '',                 $components['body'] );
            $components['subject'] = str_replace( '[workshop_preis_text]', $preis_text ?: '',                 $components['subject'] );
        }

        return $components;
    }
    add_filter( 'wpcf7_mail_components', 'micinterart_cf7_workshop_datum', 10, 3 );
}