<?php
/**
 * Contact Form 7: Workshop-ID Uebergabe, Mail-Tags, WhatsApp-Button
 *
 * @package Micinterart
 */

// ============================================================================
// CONTACT FORM 7 - WORKSHOP ID ÜBERGABE & PAYPAL MAIL-TAGS
// ============================================================================

/**
 * Workshop-ID als Hidden Field automatisch setzen
 */
add_filter('wpcf7_form_hidden_fields', 'micinterart_add_workshop_id_to_cf7');

function micinterart_add_workshop_id_to_cf7($hidden_fields) {
    if (is_singular('workshop')) {
        $hidden_fields['workshop_id'] = get_the_ID();
    }
    return $hidden_fields;
}

/**
 * JavaScript Fallback: Workshop-ID in CF7 Formular einfügen
 */
add_action('wp_footer', 'micinterart_inject_workshop_id_js');

function micinterart_inject_workshop_id_js() {
    if (!is_singular('workshop')) {
        return;
    }
    
    $post_id = get_the_ID();
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var workshopIdField = document.querySelector('input[name="workshop_id"]');
        if (workshopIdField) {
            workshopIdField.value = <?php echo intval($post_id); ?>;
            console.log('Workshop ID gesetzt:', <?php echo intval($post_id); ?>);
        }
    });
    </script>
    <?php
}
/**
 * JavaScript: thema_id-Feld in CF7-Formular befüllen wenn Thema gewählt
 */
add_action('wp_footer', 'micinterart_inject_thema_id_js');

function micinterart_inject_thema_id_js() {
    if (!is_singular('workshop')) return;
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // thema_id-Feld initial leeren
        var themaIdField = document.querySelector('input[name="thema_id"]');
        if (themaIdField) {
            themaIdField.value = '';
        }

        // Auf Dropdown-Änderung reagieren
        document.addEventListener('change', function(e) {
            if (e.target && e.target.id === 'thema-dropdown') {
                var selectedOption = e.target.options[e.target.selectedIndex];
                var themaId = selectedOption.getAttribute('data-thema-id') || '';
                if (themaIdField) {
                    themaIdField.value = themaId;
                }
            }
        });
    });
    </script>
    <?php
}

/**
 * Registrierung der Mail-Tags [workshop_title] und [paypal_link]
 */
add_filter('wpcf7_special_mail_tags', 'micinterart_register_custom_mail_tags', 10, 3);

function micinterart_register_custom_mail_tags($output, $name, $html) {
    if ($name === 'workshop_title' || $name === 'paypal_link') {
        $submission = WPCF7_Submission::get_instance();
        if ($submission) {
            $data = $submission->get_posted_data();
            $workshop_id = isset($data['workshop_id']) ? intval($data['workshop_id']) : 0;

            if ($workshop_id > 0) {
                if ($name === 'workshop_title') {
                    // Immer der Titel der Reihe/des Workshops selbst - nicht des gewählten Themas,
                    // damit "Workshop:" und "Thema:" in der Mail nicht denselben Wert doppeln.
                    return html_entity_decode(get_the_title($workshop_id), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }

                if ($name === 'paypal_link' && function_exists('micinterart_generate_paypal_link')) {
                    // Wir generieren den Link und stellen sicher, dass er URL-konform ist
                    $link = micinterart_generate_paypal_link($workshop_id);
                    return esc_url_raw($link);
                }
            }
        }
    }

    if ($name === 'thema_zeile') {
        $submission = WPCF7_Submission::get_instance();
        if (!$submission) return '';

        $data = $submission->get_posted_data();
        $thema_id   = isset($data['thema_id']) ? intval($data['thema_id']) : 0;
        $thema_text = isset($data['thema']) ? trim($data['thema']) : '';

        // Bevorzugt: echtes workshop_thema-Objekt über thema_id
        if ($thema_id > 0 && get_post_type($thema_id) === 'workshop_thema') {
            $label = html_entity_decode(get_the_title($thema_id), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($label !== '') {
                return '<strong>Thema:</strong> ' . esc_html($label) . '<br>';
            }
        }

        // Fallback: freies Thema-Textfeld, falls kein thema_id gesetzt wurde
        if ($thema_text !== '') {
            return '<strong>Thema:</strong> ' . esc_html($thema_text) . '<br>';
        }

        // Kein Thema vorhanden -> komplette Zeile weglassen
        return '';
    }

    return $output;
}

/**
 * WhatsApp-Button automatisch unter CF7-Formular einfügen
 */
add_filter('wpcf7_form_response_output', 'micinterart_add_whatsapp_after_form', 10, 4);

function micinterart_add_whatsapp_after_form($output, $class, $content, $contact_form) {
    if (!is_singular('workshop')) {
        return $output;
    }
    
    $workshop_title = get_the_title();
    $phone = '4915679004153';
    $message = rawurlencode("Hallo Micaella, ich habe eine Frage zum Workshop: " . $workshop_title);
    $url = "https://wa.me/{$phone}?text={$message}";
    
    $whatsapp_html = '
    <style>
        .whatsapp-divider {
            width: 100%;
            text-align: center;
            border-bottom: 1px solid #ddd;
            line-height: 0.1em;
            margin: 30px 0 20px;
        }
        .whatsapp-divider span {
            background: #fff;
            padding: 0 10px;
            color: #999;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .whatsapp-wrapper {
            margin: 20px 0;
            text-align: center;
        }
        .whatsapp-workshop-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #25D366;
            color: #fff !important;
            padding: 14px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-family: inherit;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
            border: none;
            font-size: 16px;
        }
        .whatsapp-workshop-btn:hover {
            background-color: #1ebe57;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(37, 211, 102, 0.4);
            color: #fff !important;
            text-decoration: none;
        }
        .whatsapp-icon {
            margin-right: 8px;
            font-size: 1.3em;
        }
    </style>
    <div class="whatsapp-divider">
        <span>ODER</span>
    </div>
    <div class="whatsapp-wrapper">
        <a href="' . esc_url($url) . '" class="whatsapp-workshop-btn" target="_blank" rel="noopener">
            <span class="whatsapp-icon">💬</span> Per WhatsApp anfragen & anmelden
        </a>
    </div>';
    
    return $output . $whatsapp_html;
}

/**
 * Shortcode für WhatsApp-Button (optional)
 */
function micinterart_whatsapp_button_shortcode() {
    if (!is_singular('workshop')) return '';
    $workshop_title = get_the_title();
    $phone = '4915679004153';
    $message = rawurlencode("Hallo Micaella, ich habe eine Frage zum Workshop: " . $workshop_title);
    $url = "https://wa.me/{$phone}?text={$message}";
    return '<div class="whatsapp-wrapper" style="margin:20px 0;text-align:center;"><a href="' . esc_url($url) . '" class="whatsapp-workshop-btn" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;background:#25D366;color:#fff!important;padding:14px 30px;border-radius:50px;text-decoration:none;font-weight:600;"><span style="margin-right:8px;font-size:1.3em;">💬</span> Per WhatsApp anfragen & anmelden</a></div>';
}
add_shortcode('workshop_whatsapp_btn', 'micinterart_whatsapp_button_shortcode');