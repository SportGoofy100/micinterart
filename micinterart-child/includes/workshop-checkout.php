<?php
/**
 * Workshop Checkout Anpassungen: Teilnehmerfelder und Rabatte
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

class Micinterart_Workshop_Checkout {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Teilnehmerfelder zum Checkout hinzufügen
        add_filter('woocommerce_checkout_fields', [$this, 'add_participant_fields']);
        
        // Validierung der Teilnehmerfelder
        add_action('woocommerce_checkout_process', [$this, 'validate_participant_fields']);
        
        // Teilnehmerdaten in Bestellung speichern
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_participant_data']);
        
        // Bestellmail mit Teilnehmerdaten erweitern
        add_filter('woocommerce_email_order_meta_fields', [$this, 'add_participant_to_email']);
        
        // Geschwisterrabatt (10% Rabatt für jedes weitere Kind ab dem 2.)
        add_action('woocommerce_cart_calculate_fees', [$this, 'add_geschwister_discount']);
        
        // Paarpreis-Rabatt (wenn Paar-Ticket im Warenkorb)
        add_action('woocommerce_cart_calculate_fees', [$this, 'add_paarpreis_discount']);
        
        // CSS für Checkout-Felder
        add_action('wp_enqueue_scripts', [$this, 'enqueue_checkout_styles']);
    }
    
    /**
     * Fügt Teilnehmerfelder zum Checkout hinzu
     */
    public function add_participant_fields($fields) {
        // Prüfen ob Workshop-Produkte im Warenkorb sind
        $has_workshop = $this->cart_has_workshop();
        
        if (!$has_workshop) {
            return $fields;
        }
        
        // Teilnehmerfelder
        $fields['billing']['workshop_participants'] = [
            'type' => 'title',
            'class' => ['form-row-wide', 'workshop-participants-title'],
            'label' => __('Teilnehmerdaten', 'micinterart'),
            'priority' => 220,
        ];
        
        $fields['billing']['_workshop_teilnehmer_anzahl'] = [
            'type' => 'number',
            'class' => ['form-row-wide'],
            'label' => __('Anzahl der Teilnehmer', 'micinterart'),
            'required' => true,
            'min' => 1,
            'max' => 10,
            'default' => 1,
            'priority' => 230,
            'description' => __('Wie viele Personen nehmen teil?', 'micinterart'),
        ];
        
        // Teilnehmer-Details (dynamisch über JavaScript hinzugefügt)
        // Hier nur Platzhalter für das erste Feld
        $fields['billing']['_workshop_teilnehmer_names'] = [
            'type' => 'text',
            'class' => ['form-row-wide', 'workshop-teilnehmer-names'],
            'label' => __('Namen der Teilnehmer (durch Komma getrennt)', 'micinterart'),
            'required' => true,
            'priority' => 240,
            'description' => __('Beispiel: Max Mustermann, Anna Schmidt', 'micinterart'),
        ];
        
        $fields['billing']['_workshop_teilnehmer_alter'] = [
            'type' => 'text',
            'class' => ['form-row-wide'],
            'label' => __('Alter der Teilnehmer (durch Komma getrennt)', 'micinterart'),
            'required' => false,
            'priority' => 250,
            'description' => __('Beispiel: 8, 10, 12', 'micinterart'),
        ];
        
        $fields['billing']['_workshop_allergien'] = [
            'type' => 'textarea',
            'class' => ['form-row-wide'],
            'label' => __('Allergien oder besondere Ernährungsbedürfnisse', 'micinterart'),
            'required' => false,
            'priority' => 260,
            'description' => __('Bitte geben Sie an, falls Teilnehmer Allergien oder besondere Ernährungsbedürfnisse haben.', 'micinterart'),
        ];
        
        $fields['billing']['_workshop_notizen'] = [
            'type' => 'textarea',
            'class' => ['form-row-wide'],
            'label' => __('Bemerkungen', 'micinterart'),
            'required' => false,
            'priority' => 270,
            'description' => __('Zusätzliche Informationen oder Wünsche.', 'micinterart'),
        ];
        
        return $fields;
    }
    
    /**
     * Validiert die Teilnehmerfelder
     */
    public function validate_participant_fields() {
        if (empty($_POST['_workshop_teilnehmer_anzahl']) && $this->cart_has_workshop()) {
            wc_add_notice(__('Bitte geben Sie die Anzahl der Teilnehmer an.', 'micinterart'), 'error');
        }
        
        if (empty($_POST['_workshop_teilnehmer_names']) && $this->cart_has_workshop()) {
            wc_add_notice(__('Bitte geben Sie die Namen der Teilnehmer an.', 'micinterart'), 'error');
        }
        
        // Validierung der Anzahl
        $anzahl = isset($_POST['_workshop_teilnehmer_anzahl']) ? intval($_POST['_workshop_teilnehmer_anzahl']) : 0;
        if ($anzahl <= 0 && $this->cart_has_workshop()) {
            wc_add_notice(__('Die Anzahl der Teilnehmer muss mindestens 1 sein.', 'micinterart'), 'error');
        }
        
        // Validierung der Namen
        if (!empty($_POST['_workshop_teilnehmer_names']) && $this->cart_has_workshop()) {
            $namen = sanitize_text_field($_POST['_workshop_teilnehmer_names']);
            $namen_array = array_map('trim', explode(',', $namen));
            
            if (count($namen_array) !== $anzahl) {
                wc_add_notice(sprintf(__('Sie haben %d Namen angegeben, aber %d Teilnehmer. Bitte korrigieren Sie dies.', 'micinterart'), count($namen_array), $anzahl), 'error');
            }
        }
    }
    
    /**
     * Speichert Teilnehmerdaten in der Bestellung
     */
    public function save_participant_data($order_id) {
        if (!empty($_POST['_workshop_teilnehmer_anzahl'])) {
            update_post_meta($order_id, '_workshop_teilnehmer_anzahl', intval($_POST['_workshop_teilnehmer_anzahl']));
        }
        
        if (!empty($_POST['_workshop_teilnehmer_names'])) {
            update_post_meta($order_id, '_workshop_teilnehmer_names', sanitize_text_field($_POST['_workshop_teilnehmer_names']));
        }
        
        if (!empty($_POST['_workshop_teilnehmer_alter'])) {
            update_post_meta($order_id, '_workshop_teilnehmer_alter', sanitize_text_field($_POST['_workshop_teilnehmer_alter']));
        }
        
        if (!empty($_POST['_workshop_allergien'])) {
            update_post_meta($order_id, '_workshop_allergien', sanitize_textarea_field($_POST['_workshop_allergien']));
        }
        
        if (!empty($_POST['_workshop_notizen'])) {
            update_post_meta($order_id, '_workshop_notizen', sanitize_textarea_field($_POST['_workshop_notizen']));
        }
    }
    
    /**
     * Fügt Teilnehmerdaten zur Bestellmail hinzu
     */
    public function add_participant_to_email($fields) {
        $order_id = $fields['id'];
        
        $anzahl = get_post_meta($order_id, '_workshop_teilnehmer_anzahl', true);
        $namen = get_post_meta($order_id, '_workshop_teilnehmer_names', true);
        $alter = get_post_meta($order_id, '_workshop_teilnehmer_alter', true);
        $allergien = get_post_meta($order_id, '_workshop_allergien', true);
        $notizen = get_post_meta($order_id, '_workshop_notizen', true);
        
        if ($anzahl || $namen || $alter || $allergien || $notizen) {
            $fields['teilnehmer'] = [
                'label' => __('Teilnehmerdaten', 'micinterart'),
                'value' => $this->format_participant_data($anzahl, $namen, $alter, $allergien, $notizen),
            ];
        }
        
        return $fields;
    }
    
    /**
     * Formatiert Teilnehmerdaten für die E-Mail
     */
    private function format_participant_data($anzahl, $namen, $alter, $allergien, $notizen) {
        $output = [];
        
        if ($anzahl) {
            $output[] = 'Anzahl der Teilnehmer: ' . $anzahl;
        }
        
        if ($namen) {
            $output[] = 'Namen: ' . $namen;
        }
        
        if ($alter) {
            $output[] = 'Alter: ' . $alter;
        }
        
        if ($allergien) {
            $output[] = 'Allergien: ' . $allergien;
        }
        
        if ($notizen) {
            $output[] = 'Bemerkungen: ' . $notizen;
        }
        
        return implode('\n', $output);
    }
    
    /**
     * Prüft ob Workshop-Produkte im Warenkorb sind
     */
    private function cart_has_workshop() {
        if (!function_exists('WC')) {
            return false;
        }
        
        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return false;
        }
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $product = wc_get_product($product_id);
            
            if ($product && $this->is_workshop_product($product)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Prüft ob ein Produkt ein Workshop ist
     */
    private function is_workshop_product($product) {
        if (!is_a($product, 'WC_Product')) {
            return false;
        }
        
        $product_id = $product->get_id();
        $terms = get_the_terms($product_id, 'product_cat');
        
        if (is_wp_error($terms) || empty($terms)) {
            return false;
        }
        
        foreach ($terms as $term) {
            if ($term->slug === 'workshops' || $term->slug === 'atelierkurse' || $term->slug === 'kinderworkshops') {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Fügt Geschwisterrabatt hinzu (10% für jedes weitere Kind ab dem 2.)
     */
    public function add_geschwister_discount() {
        if (!function_exists('WC')) {
            return;
        }
        
        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return;
        }
        
        $workshop_items = [];
        $kinderworkshop_items = [];
        
        // Workshop-Produkte sammeln
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $product = wc_get_product($product_id);
            
            if ($product && $this->is_workshop_product($product)) {
                $workshop_items[] = $cart_item;
                
                // Prüfen ob Kinderworkshop
                $terms = get_the_terms($product_id, 'product_cat');
                if ($terms && !is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        if ($term->slug === 'kinderworkshops') {
                            $kinderworkshop_items[] = $cart_item;
                            break;
                        }
                    }
                }
            }
        }
        
        // Geschwisterrabatt nur für Kinderworkshops mit mehreren Kindern
        if (!empty($kinderworkshop_items) && count($kinderworkshop_items) >= 2) {
            // Anzahl der Kinderworkshop-Items
            $kinder_count = 0;
            foreach ($kinderworkshop_items as $item) {
                $kinder_count += $item['quantity'];
            }
            
            // Rabatt: 10% für jedes Kind ab dem 2.
            $rabatt_prozent = 10;
            $rabatt_betrag = 0;
            
            // Nur die zusätzlichen Kinder (ab dem 2.) erhalten Rabatt
            $rabattfaehige_kinder = max(0, $kinder_count - 1);
            
            foreach ($kinderworkshop_items as $item) {
                $product = wc_get_product($item['product_id']);
                if ($product) {
                    // Für jedes Kind ab dem 2. wird 10% Rabatt auf den Preis gewährt
                    $rabatt_betrag += $product->get_price() * $rabatt_prozent / 100 * min($item['quantity'], $rabattfaehige_kinder);
                }
            }
            
            if ($rabatt_betrag > 0) {
                $cart->add_fee(__('Geschwisterrabatt', 'micinterart'), -$rabatt_betrag, false);
            }
        }
    }
    
    /**
     * Fügt Paarpreis-Rabatt hinzu (wenn Paar-Ticket im Warenkorb)
     */
    public function add_paarpreis_discount() {
        if (!function_exists('WC')) {
            return;
        }
        
        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return;
        }
        
        $paar_items = [];
        
        // Paartickets suchen
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $is_paar = get_post_meta($product_id, '_workshop_is_paar_preis', true);
            
            if ($is_paar === 'yes') {
                $paar_items[] = $cart_item;
            }
        }
        
        // Wenn ein Paar-Ticket gefunden wurde und die Menge > 1
        if (!empty($paar_items)) {
            foreach ($paar_items as $item) {
                if ($item['quantity'] > 1) {
                    // Rabatt von 10% für jedes Paar ab dem 2.
                    $product = wc_get_product($item['product_id']);
                    if ($product) {
                        $preis_pro_paar = $product->get_price();
                        $rabatt_prozent = 10;
                        $rabatt_betrag = $preis_pro_paar * $rabatt_prozent / 100 * ($item['quantity'] - 1);
                        
                        if ($rabatt_betrag > 0) {
                            $cart->add_fee(__('Paarrabatt', 'micinterart'), -$rabatt_betrag, false);
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Lädt Checkout-Styles
     */
    public function enqueue_checkout_styles() {
        if (!function_exists('is_checkout') || !is_checkout()) {
            return;
        }
        
        // Nur laden wenn Workshop-Produkte im Warenkorb sind
        if (!$this->cart_has_workshop()) {
            return;
        }
        
        $css = '
            .workshop-participants-title {
                font-size: 1.2em;
                font-weight: 600;
                margin-top: 20px;
                padding-top: 20px;
                border-top: 2px solid #ddd;
                background: #f9f9f9;
                padding: 15px 0;
                text-align: center;
            }
            
            .workshop-teilnehmer-names {
                margin-bottom: 15px;
            }
            
            .workshop-teilnehmer-names input,
            .workshop-teilnehmer-names textarea {
                min-height: 45px;
            }
            
            #_workshop_teilnehmer_anzahl_field label {
                font-weight: 600;
            }
            
            .workshop-participant-row {
                display: flex;
                gap: 15px;
                margin-bottom: 15px;
            }
            
            .workshop-participant-row .form-row {
                flex: 1;
            }
            
            @media (max-width: 768px) {
                .workshop-participant-row {
                    flex-direction: column;
                    gap: 0;
                }
            }
        ';
        
        wp_add_inline_style('woocommerce-checkout', $css);
    }
}

// Initialisierung
function micinterart_workshop_checkout_init() {
    Micinterart_Workshop_Checkout::get_instance();
}

add_action('woocommerce_loaded', 'micinterart_workshop_checkout_init');

// JavaScript für dynamische Teilnehmerfelder
function micinterart_workshop_checkout_js() {
    if (!function_exists('is_checkout') || !is_checkout()) {
        return;
    }
    
    $js = '
        jQuery(document).ready(function($) {
            var participantRows = function() {
                var anzahl = parseInt($("#_workshop_teilnehmer_anzahl").val()) || 1;
                var namesContainer = $("#_workshop_teilnehmer_names").closest(".form-row");
                
                // Einfache Lösung: Nur Hinweistext anpassen
                if (anzahl > 1) {
                    $("#_workshop_teilnehmer_names").attr("placeholder", "Beispiel: Max Mustermann, Anna Schmidt, Peter Müller");
                    $("#_workshop_teilnehmer_names").closest(".form-row").find("label").text("Namen aller " + anzahl + " Teilnehmer (durch Komma getrennt)");
                } else {
                    $("#_workshop_teilnehmer_names").attr("placeholder", "Beispiel: Max Mustermann");
                    $("#_workshop_teilnehmer_names").closest(".form-row").find("label").text("Name des Teilnehmers");
                }
            };
            
            $("#_workshop_teilnehmer_anzahl").on("change", participantRows);
            participantRows();
        });
    ';
    
    wp_add_inline_script('wc-checkout', $js);
}

add_action('wp_enqueue_scripts', 'micinterart_workshop_checkout_js');
