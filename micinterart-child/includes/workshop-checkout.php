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
        // Zusätzliche Teilnehmer pro Workshop-Position erfassen
        add_action('woocommerce_after_order_notes', [$this, 'render_participant_fields']);
        
        // Validierung der Teilnehmerfelder
        add_action('woocommerce_checkout_process', [$this, 'validate_participant_fields']);
        
        // Teilnehmerdaten an der jeweiligen Bestellposition speichern
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_participant_line_item'], 10, 4);
        
        // Geschwisterrabatt (10% Rabatt für jedes weitere Kind ab dem 2.)
        add_action('woocommerce_cart_calculate_fees', [$this, 'add_geschwister_discount']);
        
        // Paarpreis-Rabatt (wenn Paar-Ticket im Warenkorb)
        add_action('woocommerce_cart_calculate_fees', [$this, 'add_paarpreis_discount']);
        
        // CSS für Checkout-Felder
        add_action('wp_enqueue_scripts', [$this, 'enqueue_checkout_styles']);
    }
    
    public function render_participant_fields($checkout) {
        foreach ($this->get_workshop_cart_items() as $cart_item_key => $cart_item) {
            $quantity = (int) $cart_item['quantity'];
            if ($quantity <= 1) {
                continue;
            }

            $product = $cart_item['data'];
            $field_id = 'workshop-participants-' . sanitize_html_class($cart_item_key);
            $field_name = 'workshop_participants[' . $cart_item_key . ']';
            $value = implode("\n", $this->get_submitted_participant_names($cart_item_key));
            $additional_count = $quantity - 1;

            echo '<div class="workshop-participant-fields">';
            echo '<h3>' . esc_html(sprintf(__('Teilnehmer für „%s“', 'micinterart'), $product->get_name())) . '</h3>';
            echo '<p>' . esc_html(__('Du bist als Besteller automatisch als erste Person berücksichtigt. Bitte gib die Namen der weiteren Teilnehmer jeweils in einer eigenen Zeile an.', 'micinterart')) . '</p>';
            echo '<p class="form-row form-row-wide workshop-teilnehmer-names">';
            echo '<label for="' . esc_attr($field_id) . '">' . esc_html(sprintf(_n('Name des weiteren Teilnehmers', 'Namen der %d weiteren Teilnehmer', $additional_count, 'micinterart'), $additional_count)) . ' <span class="required">*</span></label>';
            echo '<textarea id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" rows="' . esc_attr(max(2, $additional_count)) . '" required>' . esc_textarea($value) . '</textarea>';
            echo '</p></div>';
        }
    }

    public function validate_participant_fields() {
        foreach ($this->get_workshop_cart_items() as $cart_item_key => $cart_item) {
            $quantity = (int) $cart_item['quantity'];
            if ($quantity <= 1) {
                continue;
            }

            $names = $this->get_submitted_participant_names($cart_item_key);
            $required_names = $quantity - 1;

            if (count($names) !== $required_names) {
                wc_add_notice(
                    sprintf(
                        __('Bitte gib für „%1$s“ genau %2$d zusätzliche Namen an (jeweils eine Zeile).', 'micinterart'),
                        $cart_item['data']->get_name(),
                        $required_names
                    ),
                    'error'
                );
            }
        }
    }

    public function save_participant_line_item($item, $cart_item_key, $values, $order) {
        $product = $values['data'] ?? null;
        $quantity = isset($values['quantity']) ? (int) $values['quantity'] : 1;

        if ($quantity <= 1 || !$this->is_workshop_product($product)) {
            return;
        }

        $names = $this->get_submitted_participant_names($cart_item_key);
        if (count($names) === $quantity - 1) {
            $item->add_meta_data(__('Weitere Teilnehmer', 'micinterart'), implode("\n", $names), true);
        }
    }

    private function get_submitted_participant_names($cart_item_key) {
        $submitted_names = $_POST['workshop_participants'] ?? null;
        if (!is_array($submitted_names) || !isset($submitted_names[$cart_item_key]) || !is_string($submitted_names[$cart_item_key])) {
            return [];
        }

        $value = sanitize_textarea_field(wp_unslash($submitted_names[$cart_item_key]));
        $names = preg_split('/\\r\\n|\\r|\\n/', $value);

        return array_values(array_filter(array_map('trim', $names), function($name) {
            return $name !== '';
        }));
    }
    
    /**
     * Prüft ob Workshop-Produkte im Warenkorb sind
     */
    private function cart_has_workshop() {
        return !empty($this->get_workshop_cart_items());
    }

    private function get_workshop_cart_items() {
        if (!function_exists('WC')) {
            return [];
        }
        
        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return [];
        }
        
        $workshop_items = [];
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'] ?? wc_get_product($cart_item['product_id'] ?? 0);
            
            if ($product && $this->is_workshop_product($product)) {
                $workshop_items[$cart_item_key] = $cart_item;
            }
        }
        
        return $workshop_items;
    }
    
    /**
     * Prüft ob ein Produkt ein Workshop ist
     */
    private function is_workshop_product($product) {
        if (!is_a($product, 'WC_Product')) {
            return false;
        }

        if ($product->get_type() === 'workshop') {
            return true;
        }
        
        $product_id = $product->get_id();
        $terms = get_the_terms($product_id, 'product_cat');
        
        if (is_wp_error($terms) || empty($terms)) {
            return false;
        }
        
        foreach ($terms as $term) {
            if (in_array($term->slug, ['workshops', 'atelierkurse', 'kinderworkshops', 'kinderworkshop', 'erwachsenenworkshop', 'erwachsenenworkshops'], true)) {
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
                        if (in_array($term->slug, ['kinderworkshop', 'kinderworkshops'], true)) {
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
            .workshop-participant-fields {
                margin-top: 20px;
                padding: 16px;
                border: 1px solid #ddd;
            }
            
            .workshop-teilnehmer-names {
                margin-bottom: 15px;
            }
            
            .workshop-teilnehmer-names textarea {
                width: 100%;
            }
        ';
        
        wp_add_inline_style('woocommerce-checkout', $css);
    }
}

// Initialisierung
function micinterart_workshop_checkout_init() {
    Micinterart_Workshop_Checkout::get_instance();
}

if (did_action('woocommerce_loaded')) {
    micinterart_workshop_checkout_init();
} else {
    add_action('woocommerce_loaded', 'micinterart_workshop_checkout_init');
}
