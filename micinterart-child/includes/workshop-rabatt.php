<?php
/**
 * Workshop Rabatt-System: Hilfsfunktionen, Metaboxen, Speichern,
 * Preisberechnung, PayPal-Mail, Frontend-Rabattanzeige, AJAX, PayPal-Integration
 *
 * @package Micinterart
 * @version 2.0
 */

// ============================================================================
// TEIL 1: HILFSFUNKTIONEN
// ============================================================================

if (!function_exists('micinterart_get_serie_groesse')) {
    function micinterart_get_serie_groesse($workshop_id) {
        $themen_args = array(
            'post_type' => 'workshop_thema',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_thema_workshop_id',
                    'value' => $workshop_id,
                    'compare' => '='
                )
            )
        );
        $themen = get_posts($themen_args);
        
        $heute = date('Y-m-d');
        $gueltige_themen = 0;
        
        foreach ($themen as $thema) {
            $thema_datum = get_post_meta($thema->ID, '_thema_datum', true);
            if (empty($thema_datum) || $thema_datum >= $heute) {
                $gueltige_themen++;
            }
        }
        
        return $gueltige_themen;
    }
}

if (!function_exists('micinterart_get_serie_details')) {
    function micinterart_get_serie_details($workshop_id) {
        $themen_args = array(
            'post_type' => 'workshop_thema',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_thema_workshop_id',
                    'value' => $workshop_id,
                    'compare' => '='
                )
            )
        );
        $themen = get_posts($themen_args);
        
        $heute = date('Y-m-d');
        $zukuenftige = array();
        $vergangene = array();
        $ohne_datum = array();
        
        foreach ($themen as $thema) {
            $thema_datum = get_post_meta($thema->ID, '_thema_datum', true);
            
            if (empty($thema_datum)) {
                $ohne_datum[] = $thema;
            } elseif ($thema_datum >= $heute) {
                $zukuenftige[] = $thema;
            } else {
                $vergangene[] = $thema;
            }
        }
        
        return array(
            'gesamt' => count($themen),
            'gueltig' => count($zukuenftige) + count($ohne_datum),
            'zukuenftige' => $zukuenftige,
            'ohne_datum' => $ohne_datum,
            'vergangene' => $vergangene
        );
    }
}

// ============================================================================
// TEIL 2: RABATT METABOXEN
// ============================================================================

if (!function_exists('micinterart_add_rabatt_metaboxes')) {
    function micinterart_add_rabatt_metaboxes() {
        add_meta_box(
            'workshop_rabatte',
            '💰 Rabatt-Einstellungen',
            'micinterart_render_rabatt_metabox',
            'workshop',
            'side',
            'default'
        );
    }
    add_action('add_meta_boxes', 'micinterart_add_rabatt_metaboxes');
}

if (!function_exists('micinterart_render_rabatt_metabox')) {
    function micinterart_render_rabatt_metabox($post) {
        wp_nonce_field('workshop_rabatt_save', 'workshop_rabatt_nonce');
        
        $is_kinderworkshop = false;
        $categories = get_the_terms($post->ID, 'workshop_kategorie');
        if ($categories && !is_wp_error($categories)) {
            foreach ($categories as $category) {
                if ($category->slug === 'kinderworkshops') {
                    $is_kinderworkshop = true;
                    break;
                }
            }
        }
        
        $geschwister_rabatt = get_post_meta($post->ID, '_workshop_geschwister_rabatt', true);
        if (empty($geschwister_rabatt)) $geschwister_rabatt = '10';
        
        $geschwister_ab = get_post_meta($post->ID, '_workshop_geschwister_ab', true);
        if (empty($geschwister_ab)) $geschwister_ab = '2';
        
        $serien_rabatt = get_post_meta($post->ID, '_workshop_serien_rabatt', true);
        if (empty($serien_rabatt)) $serien_rabatt = '15';
        
        $serien_rabatt_aktiv = get_post_meta($post->ID, '_workshop_serien_rabatt_aktiv', true);
        
        $frequenz = get_post_meta($post->ID, '_workshop_wiederholung_frequenz', true);
        $hat_serie = in_array($frequenz, array('woechentlich', 'zweiwoechentlich', 'monatlich'));
        
        ?>
        <style>
            .rabatt-section { padding: 12px 0; border-bottom: 1px solid #e5e5e5; }
            .rabatt-section:last-child { border-bottom: none; }
            .rabatt-section h4 { margin: 0 0 10px 0; font-size: 13px; color: #1d2327; }
            .rabatt-input-group { display: flex; align-items: center; gap: 8px; margin: 8px 0; }
            .rabatt-input-group input[type="number"] { width: 60px; }
            .rabatt-input-group label { margin: 0; font-weight: normal; }
            .rabatt-info { background: #f0f6fc; border-left: 3px solid #0073aa; padding: 10px; margin: 10px 0; font-size: 12px; line-height: 1.5; }
            .rabatt-preview { background: #f9f9f9; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 12px; }
            .rabatt-preview strong { color: #d4a574; }
        </style>
        
        <?php if ($is_kinderworkshop) : ?>
            <div class="rabatt-section">
                <h4>👶 Geschwisterrabatt</h4>
                
                <div class="rabatt-input-group">
                    <input type="number" name="workshop_geschwister_rabatt" value="<?php echo esc_attr($geschwister_rabatt); ?>" min="0" max="100" step="1">
                    <label>% Rabatt</label>
                </div>
                
                <div class="rabatt-input-group">
                    <label>Ab dem</label>
                    <input type="number" name="workshop_geschwister_ab" value="<?php echo esc_attr($geschwister_ab); ?>" min="2" max="10" step="1">
                    <label>. Kind</label>
                </div>
                
                <div class="rabatt-preview">
                    <?php 
                    $preis = get_post_meta($post->ID, '_workshop_preis', true);
                    if (!empty($preis) && $preis > 0) {
                        $rabatt_betrag = $preis * ($geschwister_rabatt / 100);
                        $preis_mit_rabatt = $preis - $rabatt_betrag;
                        ?>
                        <strong>Beispiel:</strong><br>
                        Normal: <?php echo number_format($preis, 2, ',', '.'); ?> €<br>
                        Mit Rabatt: <?php echo number_format($preis_mit_rabatt, 2, ',', '.'); ?> €<br>
                        <small>(Ersparnis: <?php echo number_format($rabatt_betrag, 2, ',', '.'); ?> € pro Kind)</small>
                    <?php } else { ?>
                        <em>Bitte erst einen Preis festlegen</em>
                    <?php } ?>
                </div>
                
                <div class="rabatt-info">
                    💡 <strong>Info:</strong> Dieser Rabatt wird im Formular automatisch angezeigt.
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!$is_kinderworkshop && $hat_serie) : 
            $serie_details = micinterart_get_serie_details($post->ID);
            $serie_groesse = $serie_details['gueltig'];
            $hat_serie_einheiten = ($serie_groesse >= 2);
        ?>
            
            <?php if ($serie_details['gesamt'] > 0) : ?>
                <div class="rabatt-section">
                    <h4>📚 Workshop-Themen</h4>
                    
                    <div class="rabatt-info" style="background: #e3f2fd; border-left-color: #2196f3;">
                        <strong>📋 Gesamt: <?php echo $serie_details['gesamt']; ?></strong><br>
                        <?php if (count($serie_details['zukuenftige']) > 0) : ?>✅ <?php echo count($serie_details['zukuenftige']); ?> zukünftig<br><?php endif; ?>
                        <?php if (count($serie_details['ohne_datum']) > 0) : ?>🔄 <?php echo count($serie_details['ohne_datum']); ?> rotierend<br><?php endif; ?>
                        <?php if (count($serie_details['vergangene']) > 0) : ?>⏰ <?php echo count($serie_details['vergangene']); ?> vergangen<br><?php endif; ?>
                        <hr style="margin: 10px 0; border: none; border-top: 1px solid #90caf9;">
                        <strong style="color: #1976d2;">Gültige Einheiten: <?php echo $serie_groesse; ?></strong>
                    </div>
                    
                    <?php if ($hat_serie_einheiten) : ?>
                        <div class="rabatt-input-group">
                            <label><input type="checkbox" name="workshop_serien_rabatt_aktiv" value="yes" <?php checked($serien_rabatt_aktiv, 'yes'); ?>> Aktivieren</label>
                        </div>
                        
                        <div class="rabatt-input-group">
                            <input type="number" name="workshop_serien_rabatt" value="<?php echo esc_attr($serien_rabatt); ?>" min="0" max="100" step="1">
                            <label>% Rabatt</label>
                        </div>
                        
                        <div class="rabatt-preview">
                            <?php 
                            $preis = get_post_meta($post->ID, '_workshop_preis', true);
                            if (!empty($preis) && $preis > 0) {
                                $gesamtpreis_normal = $preis * $serie_groesse;
                                $rabatt_betrag = $gesamtpreis_normal * ($serien_rabatt / 100);
                                $gesamtpreis_rabatt = $gesamtpreis_normal - $rabatt_betrag;
                                ?>
                                <strong><?php echo $serie_groesse; ?> Einheiten:</strong><br>
                                Einzeln: <?php echo number_format($gesamtpreis_normal, 2, ',', '.'); ?> €<br>
                                Serie: <strong><?php echo number_format($gesamtpreis_rabatt, 2, ',', '.'); ?> €</strong><br>
                                <small>(Ersparnis: <?php echo number_format($rabatt_betrag, 2, ',', '.'); ?> €)</small>
                            <?php } ?>
                        </div>
                    <?php else : ?>
                        <div class="rabatt-info" style="background: #fff3e0; border-left-color: #ff9800;">
                            ⚠️ Mindestens 2 gültige Themen benötigt
                        </div>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <div class="rabatt-info" style="background: #fff3e0; border-left-color: #ff9800;">
                    ⚠️ Keine Workshop-Themen zugeordnet
                </div>
            <?php endif; ?>
        <?php elseif (!$is_kinderworkshop) : ?>
            <div class="rabatt-info">ℹ️ Nur für wiederkehrende Workshops</div>
        <?php endif; ?>
        <?php
    }
}

// ============================================================================
// TEIL 3: SPEICHERN
// ============================================================================

if (!function_exists('micinterart_save_rabatt_meta')) {
    function micinterart_save_rabatt_meta($post_id) {
        if (!isset($_POST['workshop_rabatt_nonce']) || !wp_verify_nonce($_POST['workshop_rabatt_nonce'], 'workshop_rabatt_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        
        if (isset($_POST['workshop_geschwister_rabatt'])) {
            update_post_meta($post_id, '_workshop_geschwister_rabatt', min(100, absint($_POST['workshop_geschwister_rabatt'])));
        }
        
        if (isset($_POST['workshop_geschwister_ab'])) {
            update_post_meta($post_id, '_workshop_geschwister_ab', max(2, absint($_POST['workshop_geschwister_ab'])));
        }
        
        if (isset($_POST['workshop_serien_rabatt_aktiv']) && $_POST['workshop_serien_rabatt_aktiv'] === 'yes') {
            update_post_meta($post_id, '_workshop_serien_rabatt_aktiv', 'yes');
        } else {
            delete_post_meta($post_id, '_workshop_serien_rabatt_aktiv');
        }
        
        if (isset($_POST['workshop_serien_rabatt'])) {
            update_post_meta($post_id, '_workshop_serien_rabatt', min(100, absint($_POST['workshop_serien_rabatt'])));
        }
    }
    add_action('save_post_workshop', 'micinterart_save_rabatt_meta');
}

// ============================================================================
// VERBESSERTE PREIS-BERECHNUNG FÜR PAYPAL (KINDER + ERWACHSENE)
// ============================================================================

/**
 * ERSETZE die bestehende Funktion micinterart_calculate_final_price
 * mit dieser erweiterten Version
 */
if (!function_exists('micinterart_calculate_final_price')) {
    function micinterart_calculate_final_price($workshop_id, $data = array()) {
        $preis = floatval(get_post_meta($workshop_id, '_workshop_preis', true));
        if ($preis <= 0) return 0;
        
        // Prüfen ob Kinderworkshop
        $is_kinderworkshop = false;
        $categories = get_the_terms($workshop_id, 'workshop_kategorie');
        if ($categories && !is_wp_error($categories)) {
            foreach ($categories as $category) {
                if ($category->slug === 'kinderworkshops') {
                    $is_kinderworkshop = true;
                    break;
                }
            }
        }
        
        // KINDERWORKSHOP-BERECHNUNG
        if ($is_kinderworkshop && isset($data['kinder-anzahl'])) {
            $anzahl_kinder = intval($data['kinder-anzahl']);
            $anzahl_geschwister = isset($data['geschwister-anzahl']) ? intval($data['geschwister-anzahl']) : 0;
            
            $basis_preis = $anzahl_kinder * $preis;
            $geschwister_rabatt_gesamt = 0;
            
            // Geschwisterrabatt
            $geschwister_rabatt = intval(get_post_meta($workshop_id, '_workshop_geschwister_rabatt', true));
            if (empty($geschwister_rabatt)) $geschwister_rabatt = 10;
            
            $geschwister_ab = intval(get_post_meta($workshop_id, '_workshop_geschwister_ab', true));
            if (empty($geschwister_ab)) $geschwister_ab = 2;
            
            // Rabatt greift wenn: Gesamtkinder >= ab-Schwelle UND Geschwisterkinder <= Gesamtkinder
            if ($anzahl_kinder >= $geschwister_ab && $anzahl_geschwister > 0 && $anzahl_geschwister <= $anzahl_kinder) {
                $rabatt_pro_kind = $preis * ($geschwister_rabatt / 100);
                $geschwister_rabatt_gesamt = $rabatt_pro_kind * $anzahl_geschwister;
            }
            
            // Frühbucherrabatt
            $fruehbucher_rabatt_gesamt = 0;
            $fruehbucher_bis = get_post_meta($workshop_id, '_workshop_fruehbucher_bis', true);
            $fruehbucher_rabatt_prozent = floatval(get_post_meta($workshop_id, '_workshop_fruehbucher_rabatt', true));
            
            if (!empty($fruehbucher_bis) && $fruehbucher_rabatt_prozent > 0) {
                $bis_date = DateTime::createFromFormat('Y-m-d', $fruehbucher_bis);
                $heute = new DateTime();
                
                if ($bis_date && $heute <= $bis_date) {
                    $fruehbucher_rabatt_gesamt = $basis_preis * ($fruehbucher_rabatt_prozent / 100);
                }
            }
            
            $final_price = $basis_preis - $geschwister_rabatt_gesamt - $fruehbucher_rabatt_gesamt;
            return round(max(0, $final_price), 2);
        }
        
        // ERWACHSENEN-WORKSHOP-BERECHNUNG
        elseif (!$is_kinderworkshop && isset($data['teilnehmer'])) {
            $anzahl_teilnehmer = intval($data['teilnehmer']);
            
            // Paarpreis prüfen
            $is_paar = get_post_meta($workshop_id, '_workshop_is_paar_preis', true);
            
            if ($is_paar) {
                // Paarpreis: Preis bleibt fix (gilt für 2 Personen)
                return round($preis, 2);
            } else {
                // Normaler Preis: Anzahl × Preis pro Person
                return round($anzahl_teilnehmer * $preis, 2);
            }
        }
        
        // SERIEN-BUCHUNG (falls vorhanden)
        elseif (!$is_kinderworkshop && isset($data['serie_buchen']) && $data['serie_buchen'] === 'ja') {
            $serie_groesse = micinterart_get_serie_groesse($workshop_id);
            
            if ($serie_groesse >= 2) {
                $serien_rabatt = intval(get_post_meta($workshop_id, '_workshop_serien_rabatt', true));
                if (empty($serien_rabatt)) $serien_rabatt = 15;
                
                $normal_preis = $preis * $serie_groesse;
                $rabatt = $normal_preis * ($serien_rabatt / 100);
                return round($normal_preis - $rabatt, 2);
            }
        }
        
        // Fallback: Basis-Preis
        return round($preis, 2);
    }
}


// ============================================================================
// PAYPAL-BUTTON MIT KORREKTEM PREIS (ERWACHSENE)
// ============================================================================

/**
 * FÜGE diese Funktion NACH dem bestehenden PayPal-Button-Code ein
 * (suche nach "micinterart_add_paypal_button_to_form" und füge DANACH ein)
 */
if (!function_exists('micinterart_add_adult_paypal_button')) {
    function micinterart_add_adult_paypal_button() {
        if (!is_singular('workshop')) return;
        
        // Nur wenn PayPal aktiviert ist
        if (!defined('MICINTERART_PAYPAL_EMAIL') && empty(get_option('micinterart_paypal_email'))) {
            return;
        }
        
        $post_id = get_the_ID();
        
        // Prüfen ob Kinderworkshop (dann abbrechen, die haben schon einen Button)
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
            return; // Kinderworkshops haben bereits ihren eigenen PayPal-Button
        }
        
        $preis = floatval(get_post_meta($post_id, '_workshop_preis', true));
        if ($preis <= 0) return;
        
        $is_paar = get_post_meta($post_id, '_workshop_is_paar_preis', true);
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            const workshopId = <?php echo $post_id; ?>;
            const basisPreis = <?php echo $preis; ?>;
            const isPaar = <?php echo $is_paar ? 'true' : 'false'; ?>;
            
            // PayPal-Button HTML erstellen
            const paypalButtonHTML = `
                <div class="paypal-divider">
                    <span>ODER</span>
                </div>
                
                <div class="paypal-button-wrapper" style="display:none;">
                    <h4 style="margin: 0 0 15px 0; color: #2c2c2c;">💳 Direkt online bezahlen</h4>
                    <a href="#" id="dynamic-paypal-link-adult" class="paypal-button" target="_blank" rel="noopener">
                        <svg class="paypal-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M20.067 8.478c.492.88.556 2.014.3 3.327-.74 3.806-3.276 5.12-6.514 5.12h-.5a.805.805 0 00-.794.68l-.04.22-.63 3.993-.032.17a.804.804 0 01-.794.68H7.72a.483.483 0 01-.477-.558L7.418 21h1.518l.95-6.02h1.385c4.678 0 7.75-2.203 8.796-6.502z"/>
                            <path d="M2.697 20.535l1.18-7.482a.783.783 0 01.772-.66h4.627c.313 0 .612.034.893.098a4.97 4.97 0 00-.37 1.88c0 2.754 2.24 5.164 5.637 5.164.177 0 .352-.008.525-.022l-.929 5.887H7.72a.483.483 0 01-.477-.558l.18-1.142H2.697z"/>
                        </svg>
                        Jetzt mit PayPal bezahlen (<span id="paypal-price-display-adult">0,00 €</span>)
                    </a>
                    <p class="paypal-hinweis">
                        Sichere Zahlung über PayPal<br>
                        <small>Nach der Zahlung erhältst du eine Bestätigung per E-Mail</small>
                    </p>
                </div>
            `;
            
            // PayPal-Button nach CF7-Formular einfügen
            function insertPayPalButton() {
                if ($('.paypal-button-wrapper').length > 0) {
                    return; // Bereits eingefügt
                }
                
                const cf7Form = $('.wpcf7-form');
                if (cf7Form.length > 0) {
                    cf7Form.after(paypalButtonHTML);
                    console.log('✅ Erwachsenen-PayPal-Button eingefügt');
                }
            }
            
            insertPayPalButton();
            
            $(document).on('wpcf7:ready', function() {
                setTimeout(insertPayPalButton, 100);
            });
            
            // PayPal-Link aktualisieren
            function updatePayPalLink() {
                const teilnehmerAnzahl = parseInt($('input[name="teilnehmer"]').val()) || 0;
                
                if (teilnehmerAnzahl === 0) {
                    $('.paypal-button-wrapper').slideUp();
                    return;
                }
                
                let finalPrice = 0;
                
                if (isPaar) {
                    // Paarpreis: Immer Basispreis
                    finalPrice = basisPreis;
                } else {
                    // Normaler Preis: Anzahl × Preis
                    finalPrice = basisPreis * teilnehmerAnzahl;
                }
                
                const paypalEmail = '<?php echo defined('MICINTERART_PAYPAL_EMAIL') ? MICINTERART_PAYPAL_EMAIL : get_option('micinterart_paypal_email'); ?>';
                const workshopTitle = '<?php echo addslashes(get_the_title($post_id)); ?>';
                
                const paypalUrl = 'https://www.paypal.com/cgi-bin/webscr?' + $.param({
                    cmd: '_xclick',
                    business: paypalEmail,
                    item_name: workshopTitle,
                    amount: finalPrice.toFixed(2),
                    currency_code: 'EUR',
                    no_shipping: '1',
                    return: window.location.href + '?payment=success',
                    cancel_return: window.location.href + '?payment=cancelled'
                });
                
                $('#dynamic-paypal-link-adult').attr('href', paypalUrl);
                $('#paypal-price-display-adult').text(finalPrice.toFixed(2) + ' €');
                $('.paypal-button-wrapper').slideDown();
            }
            
            // Event-Listener
            $(document).on('input change', 'input[name="teilnehmer"]', updatePayPalLink);
            
            // Initial ausführen
            setTimeout(updatePayPalLink, 500);
        });
        </script>
        <?php
    }
    add_action('wp_footer', 'micinterart_add_adult_paypal_button', 20);
}


// ============================================================================
// TEIL 5: FRONTEND RABATT-ANZEIGE
// ============================================================================

if (!function_exists('micinterart_rabatt_formular_script')) {
    function micinterart_rabatt_formular_script() {
        if (!is_singular('workshop')) return;
        
        $post_id = get_the_ID();
        
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
        
        $preis = floatval(get_post_meta($post_id, '_workshop_preis', true));
        $geschwister_rabatt = intval(get_post_meta($post_id, '_workshop_geschwister_rabatt', true));
        if (empty($geschwister_rabatt)) $geschwister_rabatt = 10;
        
        $geschwister_ab = intval(get_post_meta($post_id, '_workshop_geschwister_ab', true));
        if (empty($geschwister_ab)) $geschwister_ab = 2;
        
        $serien_rabatt_aktiv = get_post_meta($post_id, '_workshop_serien_rabatt_aktiv', true);
        $serien_rabatt = intval(get_post_meta($post_id, '_workshop_serien_rabatt', true));
        if (empty($serien_rabatt)) $serien_rabatt = 15;
        
        $frequenz = get_post_meta($post_id, '_workshop_wiederholung_frequenz', true);
        $hat_serie = in_array($frequenz, array('woechentlich', 'zweiwoechentlich', 'monatlich'));
        
        $serie_groesse = 0;
        if ($hat_serie) {
            $serie_groesse = micinterart_get_serie_groesse($post_id);
        }
        $hat_serie_einheiten = ($serie_groesse >= 2);
        
        ?>
        <style>
        .rabatt-info-box { background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); border-left: 4px solid #ff9800; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .rabatt-info-box h3 { margin: 0 0 12px 0; color: #e65100; font-size: 1.2em; display: flex; align-items: center; gap: 8px; }
        .rabatt-berechnung { background: white; padding: 15px; border-radius: 6px; margin-top: 12px; }
        .rabatt-zeile { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .rabatt-zeile:last-child { border-bottom: none; font-weight: 700; font-size: 1.1em; color: #d4a574; padding-top: 12px; margin-top: 8px; border-top: 2px solid #d4a574; }
        .rabatt-ersparnis { color: #4caf50; font-weight: 600; }
        .serien-option { background: #e3f2fd; border: 2px solid #2196f3; padding: 15px; border-radius: 8px; margin: 15px 0; cursor: pointer; transition: all 0.3s ease; }
        .serien-option:hover { background: #bbdefb; transform: translateY(-2px); }
        .serien-option input[type="checkbox"] { margin-right: 10px; transform: scale(1.2); }
        .serien-option label { font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 10px; }
        .serien-vorteil { background: white; padding: 12px; margin-top: 10px; border-radius: 4px; font-size: 0.95em; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            <?php if ($is_kinderworkshop && $preis > 0) : ?>
            const preis = <?php echo $preis; ?>;
            const geschwisterRabatt = <?php echo $geschwister_rabatt; ?>;
            const geschwisterAb = <?php echo $geschwister_ab; ?>;
            
            const kinderAnzahl = $('input[name="kinder-anzahl"]');
            const geschwisterAnzahl = $('input[name="geschwister-anzahl"]');
            const gruppeGeschwister = $('.gruppe-geschwister');
            
            gruppeGeschwister.hide();
            
            function berechneGeschwisterRabatt() {
                const anzahlKinder = parseInt(kinderAnzahl.val()) || 0;
                const anzahlGeschwister = parseInt(geschwisterAnzahl.val()) || 0;
                
                if (anzahlKinder >= 2) {
                    gruppeGeschwister.slideDown();
                } else {
                    gruppeGeschwister.slideUp();
                    geschwisterAnzahl.val('0');
                }
                
                $('.rabatt-info-box').remove();
                
                if (anzahlGeschwister >= geschwisterAb && anzahlGeschwister <= anzahlKinder) {
                    const normalPreis = preis * anzahlKinder;
                    const kinderOhneRabatt = anzahlKinder - anzahlGeschwister;
                    const kinderMitRabatt = anzahlGeschwister;
                    const rabattProKind = preis * (geschwisterRabatt / 100);
                    const gesamtRabatt = rabattProKind * kinderMitRabatt;
                    const endpreis = normalPreis - gesamtRabatt;
                    
                    const rabattHTML = `
                        <div class="rabatt-info-box">
                            <h3>🎉 Geschwisterrabatt aktiviert!</h3>
                            <p>Bei ${anzahlGeschwister} Geschwisterkindern sparst du ${geschwisterRabatt}% pro Kind!</p>
                            <div class="rabatt-berechnung">
                                <div class="rabatt-zeile">
                                    <span>${kinderOhneRabatt} Kind(er) Normalpreis:</span>
                                    <span>${(preis * kinderOhneRabatt).toFixed(2).replace('.', ',')} €</span>
                                </div>
                                <div class="rabatt-zeile">
                                    <span>${kinderMitRabatt} Geschwisterkind(er):</span>
                                    <span>${((preis - rabattProKind) * kinderMitRabatt).toFixed(2).replace('.', ',')} €</span>
                                </div>
                                <div class="rabatt-zeile">
                                    <span class="rabatt-ersparnis">Deine Ersparnis:</span>
                                    <span class="rabatt-ersparnis">-${gesamtRabatt.toFixed(2).replace('.', ',')} €</span>
                                </div>
                                <div class="rabatt-zeile">
                                    <span>Gesamtpreis:</span>
                                    <span>${endpreis.toFixed(2).replace('.', ',')} €</span>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    gruppeGeschwister.after(rabattHTML);
                }
            }
            
            kinderAnzahl.on('input change', berechneGeschwisterRabatt);
            geschwisterAnzahl.on('input change', berechneGeschwisterRabatt);
            berechneGeschwisterRabatt();
            <?php endif; ?>
            
            <?php if (!$is_kinderworkshop && $hat_serie_einheiten && $serien_rabatt_aktiv === 'yes' && $preis > 0) : ?>
            const serienPreis = <?php echo $preis; ?>;
            const serienRabatt = <?php echo $serien_rabatt; ?>;
            const serieGroesse = <?php echo $serie_groesse; ?>;
            
            const normalGesamtpreis = serienPreis * serieGroesse;
            const rabattBetrag = normalGesamtpreis * (serienRabatt / 100);
            const serienGesamtpreis = normalGesamtpreis - rabattBetrag;
            
            const serienHTML = `
                <div class="serien-option">
                    <label>
                        <input type="checkbox" name="serie_buchen" value="ja" id="serie-checkbox">
                        <span>📚 Komplette Serie (${serieGroesse} Einheiten) und ${serienRabatt}% sparen!</span>
                    </label>
                    <div class="serien-vorteil" id="serien-vorteil" style="display:none;">
                        <div class="rabatt-zeile">
                            <span>${serieGroesse} Einheiten einzeln:</span>
                            <span>${normalGesamtpreis.toFixed(2).replace('.', ',')} €</span>
                        </div>
                        <div class="rabatt-zeile">
                            <span class="rabatt-ersparnis">Serien-Rabatt (${serienRabatt}%):</span>
                            <span class="rabatt-ersparnis">-${rabattBetrag.toFixed(2).replace('.', ',')} €</span>
                        </div>
                        <div class="rabatt-zeile">
                            <span><strong>Gesamtpreis Serie:</strong></span>
                            <span><strong>${serienGesamtpreis.toFixed(2).replace('.', ',')} €</strong></span>
                        </div>
                    </div>
                </div>
            `;
            
            $('.wpcf7-form p:first').after(serienHTML);
            
            $('#serie-checkbox').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#serien-vorteil').slideDown();
                } else {
                    $('#serien-vorteil').slideUp();
                }
            });
            <?php endif; ?>
        });
        </script>
        <?php
    }
    add_action('wp_footer', 'micinterart_rabatt_formular_script');
}

// ============================================================================
// TEIL 6: E-MAIL INTEGRATION
// ============================================================================

if (!function_exists('micinterart_add_rabatt_to_mail')) {
    function micinterart_add_rabatt_to_mail($replaced, $submitted, $html, $mail_tag) {
        if ($mail_tag->name !== 'rabatt_info') return $replaced;
        
        $submission = WPCF7_Submission::get_instance();
        if (!$submission) return $replaced;
        
        $data = $submission->get_posted_data();
        $workshop_id = isset($data['workshop_id']) ? intval($data['workshop_id']) : 0;
        if ($workshop_id <= 0) return $replaced;
        
        $output = '';
        
        if (isset($data['geschwister-anzahl'])) {
            $anzahl_geschwister = intval($data['geschwister-anzahl']);
            
            if ($anzahl_geschwister > 0) {
                $rabatt = intval(get_post_meta($workshop_id, '_workshop_geschwister_rabatt', true));
                if (empty($rabatt)) $rabatt = 10;
                
                $preis = floatval(get_post_meta($workshop_id, '_workshop_preis', true));
                $rabatt_betrag = ($preis * ($rabatt / 100)) * $anzahl_geschwister;
                
                $output .= "\n--- GESCHWISTERRABATT ---\n";
                $output .= "Anzahl Geschwisterkinder: {$anzahl_geschwister}\n";
                $output .= "Rabatt: {$rabatt}% pro Kind\n";
                $output .= "Ersparnis: " . number_format($rabatt_betrag, 2, ',', '.') . " €\n";
            }
        }
        
        if (isset($data['serie_buchen']) && $data['serie_buchen'] === 'ja') {
            $serien_rabatt = intval(get_post_meta($workshop_id, '_workshop_serien_rabatt', true));
            if (empty($serien_rabatt)) $serien_rabatt = 15;
            
            $serie_groesse = micinterart_get_serie_groesse($workshop_id);
            
            if ($serie_groesse >= 2) {
                $preis = floatval(get_post_meta($workshop_id, '_workshop_preis', true));
                $normal_preis = $preis * $serie_groesse;
                $rabatt_betrag = $normal_preis * ($serien_rabatt / 100);
                
                $output .= "\n--- SERIENRABATT ---\n";
                $output .= "Anzahl Einheiten: {$serie_groesse}\n";
                $output .= "Rabatt: {$serien_rabatt}%\n";
                $output .= "Normalpreis: " . number_format($normal_preis, 2, ',', '.') . " €\n";
                $output .= "Ersparnis: " . number_format($rabatt_betrag, 2, ',', '.') . " €\n";
                $output .= "Endpreis: " . number_format($normal_preis - $rabatt_betrag, 2, ',', '.') . " €\n";
            }
        }
        
        return $output;
    }
    wpcf7_add_form_tag('rabatt_info', 'micinterart_add_rabatt_to_mail', true);
}

// ============================================================================
// TEIL 7: AJAX PREISBERECHNUNG
// ============================================================================

// ============================================================================
// ERSETZE DIE KOMPLETTE FUNKTION IN functions.php
// SUCHE NACH: if (!function_exists('micinterart_ajax_calculate_price'))
// ============================================================================

if (!function_exists('micinterart_ajax_calculate_price')) {
    function micinterart_ajax_calculate_price() {
        check_ajax_referer('micinterart_price_calc', 'nonce');
        
        $workshop_id = isset($_POST['workshop_id']) ? intval($_POST['workshop_id']) : 0;
        $data = isset($_POST['data']) ? $_POST['data'] : array();
        
        if ($workshop_id <= 0) {
            wp_send_json_error('Ungültige Workshop-ID');
        }
        
        // BASIS-PREIS
        $preis = floatval(get_post_meta($workshop_id, '_workshop_preis', true));
        if ($preis <= 0) {
            wp_send_json_error('Kein Preis definiert');
        }
        
        // KINDERWORKSHOP PRÜFEN
        $is_kinderworkshop = false;
        $categories = get_the_terms($workshop_id, 'workshop_kategorie');
        if ($categories && !is_wp_error($categories)) {
            foreach ($categories as $category) {
                if ($category->slug === 'kinderworkshops') {
                    $is_kinderworkshop = true;
                    break;
                }
            }
        }
        
        // INITIALISIERUNG
        $anzahl_kinder = isset($data['kinder-anzahl']) ? intval($data['kinder-anzahl']) : 0;
        $anzahl_geschwister = isset($data['geschwister-anzahl']) ? intval($data['geschwister-anzahl']) : 0;
        
        $basis_preis = $anzahl_kinder * $preis;
        $geschwister_rabatt_gesamt = 0;
        $fruehbucher_rabatt_gesamt = 0;
        $preis_gesamt = $basis_preis;
        
        // ========================================
        // GESCHWISTER-RABATT BERECHNEN (KORRIGIERT!)
        // ========================================
        if ($is_kinderworkshop && $anzahl_kinder > 0 && $anzahl_geschwister > 0) {
            $geschwister_rabatt_prozent = floatval(get_post_meta($workshop_id, '_workshop_geschwister_rabatt', true));
            if (empty($geschwister_rabatt_prozent)) $geschwister_rabatt_prozent = 10;
            
            $geschwister_ab = intval(get_post_meta($workshop_id, '_workshop_geschwister_ab', true));
            if (empty($geschwister_ab)) $geschwister_ab = 2;
            
            // WICHTIG: Nur wenn genug Kinder UND Geschwister <= Kinder
            if ($anzahl_kinder >= $geschwister_ab && $anzahl_geschwister <= $anzahl_kinder) {
                // RICHTIGE BERECHNUNG:
                // Das ERSTE Kind zahlt vollen Preis
                // Nur die WEITEREN Geschwister bekommen Rabatt
                
                $rabatt_pro_kind = $preis * ($geschwister_rabatt_prozent / 100);
                
                // Wenn 2 Kinder, 1 Geschwister:
                // → Nur 1 Kind bekommt Rabatt (das 2. Kind, nicht das 1.)
                $geschwister_rabatt_gesamt = $rabatt_pro_kind * $anzahl_geschwister;
                
                $preis_gesamt -= $geschwister_rabatt_gesamt;
            }
        }
        
        // ========================================
        // FRÜHBUCHER-RABATT BERECHNEN
        // ========================================
        $fruehbucher_bis = get_post_meta($workshop_id, '_workshop_fruehbucher_bis', true);
        $fruehbucher_rabatt_prozent = floatval(get_post_meta($workshop_id, '_workshop_fruehbucher_rabatt', true));
        
        if (!empty($fruehbucher_bis) && $fruehbucher_rabatt_prozent > 0) {
            $bis_date = DateTime::createFromFormat('Y-m-d', $fruehbucher_bis);
            $heute = new DateTime();
            
            if ($bis_date && $heute <= $bis_date) {
                // Frühbucher-Rabatt auf den bereits reduzierten Preis anwenden
                $fruehbucher_rabatt_gesamt = $preis_gesamt * ($fruehbucher_rabatt_prozent / 100);
                $preis_gesamt -= $fruehbucher_rabatt_gesamt;
            }
        }
        
        // ERSPARNIS BERECHNEN
        $ersparnis = $basis_preis - $preis_gesamt;
        
        // ========================================
        // ALLE WERTE ZURÜCKGEBEN
        // ========================================
        wp_send_json_success(array(
            'preis' => number_format($preis_gesamt, 2, ',', '.') . ' €',
            'preis_raw' => round($preis_gesamt, 2),
            'basis_preis_raw' => round($basis_preis, 2),
            'geschwister_rabatt_raw' => round($geschwister_rabatt_gesamt, 2),
            'fruehbucher_rabatt_raw' => round($fruehbucher_rabatt_gesamt, 2),
            'ersparnis_raw' => round($ersparnis, 2),
            'anzahl_kinder' => $anzahl_kinder,
            'anzahl_geschwister' => $anzahl_geschwister,
            'geschwister_rabatt_prozent' => $geschwister_rabatt_prozent ?? 0
        ));
    }
    add_action('wp_ajax_calculate_workshop_price', 'micinterart_ajax_calculate_price');
    add_action('wp_ajax_nopriv_calculate_workshop_price', 'micinterart_ajax_calculate_price');
}



// ============================================================================
// TEIL 8: PAYPAL-INTEGRATION (FINALE VERSION)
// ============================================================================

/**
 * Workshop-Daten automatisch ins Formular einfügen
 */
if (!function_exists('micinterart_inject_workshop_data_to_cf7')) {
    function micinterart_inject_workshop_data_to_cf7() {
        if (!is_singular('workshop')) return;
        
        $post_id = get_the_ID();
        $post_title = get_the_title($post_id);
        ?>
        <script>
        jQuery(document).ready(function($) {
            var cf7Form = $('.wpcf7-form');
            if (cf7Form.length > 0) {
                // Workshop-ID einfügen
                if (cf7Form.find('input[name="workshop_id"]').length > 0) {
                    cf7Form.find('input[name="workshop_id"]').val('<?php echo esc_js($post_id); ?>');
                } else {
                    cf7Form.prepend('<input type="hidden" name="workshop_id" value="<?php echo esc_js($post_id); ?>">');
                }
                
                // Workshop-Titel einfügen
                if (cf7Form.find('input[name="workshop"]').length > 0) {
                    cf7Form.find('input[name="workshop"]').val('<?php echo esc_js($post_title); ?>');
                } else {
                    cf7Form.prepend('<input type="hidden" name="workshop" value="<?php echo esc_js($post_title); ?>">');
                }
            }
        });
        </script>
        <?php
    }
    add_action('wp_footer', 'micinterart_inject_workshop_data_to_cf7', 5);
}

/**
 * Custom Mail-Tag für [_post_title]
 */
if (!function_exists('micinterart_cf7_post_title_tag')) {
    function micinterart_cf7_post_title_tag($output, $name, $html) {
        if ($name === '_post_title') {
            $submission = WPCF7_Submission::get_instance();
            if ($submission) {
                $unit_tag = $submission->get_meta('unit_tag');
                // Unit-Tag Format: wpcf7-f{FORM_ID}-p{POST_ID}-o{COUNTER}
                if (preg_match('/wpcf7-f(\d+)-p(\d+)-o(\d+)/', $unit_tag, $matches)) {
                    $post_id = intval($matches[2]);
                    if ($post_id > 0) {
                        $data = $submission->get_posted_data();
                        $thema_id = isset($data['thema_id']) ? intval($data['thema_id']) : 0;
                        if ($thema_id > 0 && get_post_type($thema_id) === 'workshop_thema') {
                            return get_the_title($thema_id);
                        }
                        return get_the_title($post_id);
                    }
                }
            }
        }
        return $output;
    }
    add_filter('wpcf7_special_mail_tags', 'micinterart_cf7_post_title_tag', 10, 3);
}

/**
 * Custom Mail-Tag für [_post_id]
 */
if (!function_exists('micinterart_cf7_post_id_tag')) {
    function micinterart_cf7_post_id_tag($output, $name, $html) {
        if ($name === '_post_id') {
            $submission = WPCF7_Submission::get_instance();
            if ($submission) {
                $unit_tag = $submission->get_meta('unit_tag');
                if (preg_match('/wpcf7-f(\d+)-p(\d+)-o(\d+)/', $unit_tag, $matches)) {
                    return intval($matches[2]);
                }
            }
        }
        return $output;
    }
    add_filter('wpcf7_special_mail_tags', 'micinterart_cf7_post_id_tag', 10, 3);
}


