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

        // Blocks-Checkout: ein Zusatzfeld für die Namen aller weiteren Teilnehmer
        add_action('woocommerce_init', [$this, 'register_blocks_checkout_field']);
        add_action('woocommerce_store_api_checkout_update_order_from_request', [$this, 'process_blocks_participants'], 10, 2);
        // Gecachte Workshop-IDs (für die Sichtbarkeit des Feldes) bei Produktänderungen verwerfen
        add_action('woocommerce_update_product', [$this, 'flush_workshop_ids_cache']);
        add_action('woocommerce_new_product', [$this, 'flush_workshop_ids_cache']);
        add_action('woocommerce_delete_product', [$this, 'flush_workshop_ids_cache']);
        add_action('woocommerce_new_product_variation', [$this, 'flush_workshop_ids_cache']);
        add_action('woocommerce_update_product_variation', [$this, 'flush_workshop_ids_cache']);
        add_action('woocommerce_delete_product_variation', [$this, 'flush_workshop_ids_cache']);
    }

    // ------------------------------------------------------------------
    // Blocks-Checkout (Zusatzfeld-API von WooCommerce, Ort "order")
    // Die Blocks-Kasse kennt nur einzeilige Textfelder und keine Felder pro
    // Warenkorbposition. Daher gibt es ein gemeinsames Feld, in das alle
    // weiteren Teilnehmer mit Komma getrennt eingetragen werden.
    // ------------------------------------------------------------------

    const BLOCKS_FIELD_ID = 'micinterart/workshop-teilnehmer';
    const WORKSHOP_IDS_TRANSIENT = 'micinterart_workshop_product_ids';

    public function register_blocks_checkout_field() {
        if (!function_exists('woocommerce_register_additional_checkout_field')) {
            return; // Zu alte WooCommerce-Version
        }

        $label = __('Weitere Teilnehmer (Namen mit Komma getrennt)', 'micinterart');
        $field = [
            'id'            => self::BLOCKS_FIELD_ID,
            'label'         => $label,
            'optionalLabel' => $label,
            'location'      => 'order',
            'type'          => 'text',
            'required'      => false,
        ];

        // Feld nur zeigen, wenn ein Workshop im Warenkorb liegt (Bedingungen ab WooCommerce 9.9)
        $workshop_ids = $this->get_workshop_product_ids();
        if (!empty($workshop_ids) && defined('WC_VERSION') && version_compare(WC_VERSION, '9.9', '>=')) {
            $field['hidden'] = [
                'cart' => [
                    'properties' => [
                        'items' => [
                            'not' => ['contains' => ['enum' => $workshop_ids]],
                        ],
                    ],
                ],
            ];
        }

        woocommerce_register_additional_checkout_field($field);
    }

    /**
     * Prüft die Namen beim Abschicken der Bestellung und verteilt sie auf die Workshop-Positionen
     */
    public function process_blocks_participants($order, $request) {
        if (!is_a($order, 'WC_Order')) {
            return;
        }

        // Positionen mit mehreren Plätzen: pro Position werden (Menge - 1) weitere Namen benötigt
        $positions = [];
        $required = 0;
        foreach ($order->get_items('line_item') as $item) {
            $product = $item->get_product();
            $quantity = $this->item_places($product, (int) $item->get_quantity());
            if ($quantity > 1 && $this->is_workshop_product($product)) {
                $positions[] = [$item, $quantity - 1];
                $required += $quantity - 1;
            }
        }
        if ($required === 0) {
            return;
        }

        $additional = $request['additional_fields'] ?? [];
        $raw = (is_array($additional) && isset($additional[self::BLOCKS_FIELD_ID]) && is_string($additional[self::BLOCKS_FIELD_ID]))
            ? $additional[self::BLOCKS_FIELD_ID]
            : '';
        $names = $this->split_participant_names($raw);

        if (count($names) !== $required) {
            $message = sprintf(
                _n(
                    'Bitte gib im Feld „Weitere Teilnehmer“ den Namen des weiteren Teilnehmers an.',
                    'Bitte gib im Feld „Weitere Teilnehmer“ genau %d Namen an (mit Komma getrennt).',
                    $required,
                    'micinterart'
                ),
                $required
            );
            if (class_exists('\Automattic\WooCommerce\StoreApi\Exceptions\RouteException')) {
                throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException('micinterart_participants_invalid', $message, 400);
            }
            return;
        }

        // Namen der Reihe nach auf die Workshop-Positionen verteilen
        $offset = 0;
        foreach ($positions as $position) {
            list($item, $count) = $position;
            $chunk = array_slice($names, $offset, $count);
            $offset += $count;
            $item->add_meta_data(__('Weitere Teilnehmer', 'micinterart'), implode("\n", $chunk), true);
            $item->save_meta_data();
        }
    }

    private function split_participant_names($value) {
        $names = preg_split('/[,;\r\n]+/', (string) $value);
        $names = array_map('sanitize_text_field', array_map('trim', $names));
        return array_values(array_filter($names, function ($name) {
            return $name !== '';
        }));
    }

    /**
     * IDs aller Workshop-Produkte (Produkttyp oder Workshop-Kategorie), gecacht
     */
    private function get_workshop_product_ids() {
        $ids = get_transient(self::WORKSHOP_IDS_TRANSIENT);
        if (is_array($ids)) {
            return $ids;
        }

        // Über alle Sprachen suchen ('lang' => ''), sonst fehlen bei aktivem Polylang die Workshops
        // der jeweils anderen Sprache
        $ids = get_posts([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'lang'           => '',
            'tax_query'      => [
                'relation' => 'OR',
                [
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => ['workshop', 'workshop_variable'],
                ],
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => ['workshops', 'atelierkurse', 'kinderworkshops', 'kinderworkshop', 'erwachsenenworkshop', 'erwachsenenworkshops'],
                ],
            ],
        ]);
        $ids = array_map('intval', $ids);

        // Bei variablen Workshops zählen auch die Variationen (im Warenkorb liegt die Variation)
        if (!empty($ids)) {
            $variation_ids = get_posts([
                'post_type'        => 'product_variation',
                'post_status'      => 'any',
                'posts_per_page'   => -1,
                'fields'           => 'ids',
                'no_found_rows'    => true,
                'post_parent__in'  => $ids,
            ]);
            $ids = array_merge($ids, array_map('intval', $variation_ids));
        }
        $ids = array_values(array_unique($ids));

        set_transient(self::WORKSHOP_IDS_TRANSIENT, $ids, 6 * HOUR_IN_SECONDS);
        return $ids;
    }

    public function flush_workshop_ids_cache() {
        delete_transient(self::WORKSHOP_IDS_TRANSIENT);
    }

    public function render_participant_fields($checkout) {
        foreach ($this->get_workshop_cart_items() as $cart_item_key => $cart_item) {
            $quantity = $this->item_places($cart_item['data'] ?? null, (int) $cart_item['quantity']);
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
            $quantity = $this->item_places($cart_item['data'] ?? null, (int) $cart_item['quantity']);
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
        $quantity = $this->item_places($product, isset($values['quantity']) ? (int) $values['quantity'] : 1);

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
     * Belegte Plätze einer Position: Gruppengröße der Variation mal Menge
     */
    private function item_places($product, $quantity) {
        return $product ? micinterart_workshop_personen($product) * max(1, (int) $quantity) : max(1, (int) $quantity);
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

        if (micinterart_is_workshop_type($product)) {
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
        
        // Einzelpreise aller Kinder (ein Eintrag pro Teilnehmer, also pro Menge)
        $kinder_preise = [];

        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'] ?? null;
            if (!$product || !$this->is_workshop_product($product)) {
                continue;
            }

            // Gruppenpreise (variabler Workshop) sind pauschal: kein Geschwister- oder Paarrabatt
            if (micinterart_is_workshop_variable($product)) {
                continue;
            }

            // Prüfen ob Kinderworkshop
            $is_kinderworkshop = false;
            $terms = get_the_terms($cart_item['product_id'], 'product_cat');
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    if (in_array($term->slug, ['kinderworkshop', 'kinderworkshops'], true)) {
                        $is_kinderworkshop = true;
                        break;
                    }
                }
            }
            if (!$is_kinderworkshop) {
                continue;
            }

            // Tatsächlicher Preis im Warenkorb (berücksichtigt Angebotspreise)
            $preis = (float) $product->get_price();
            for ($i = 0; $i < (int) $cart_item['quantity']; $i++) {
                $kinder_preise[] = $preis;
            }
        }

        // Geschwisterrabatt erst ab dem 2. Kind, unabhängig davon,
        // ob die Kinder in einer Zeile (Menge) oder mehreren Zeilen liegen
        if (count($kinder_preise) < 2) {
            return;
        }

        // Das teuerste Kind zahlt den vollen Preis, 10% Rabatt auf alle weiteren
        rsort($kinder_preise);
        array_shift($kinder_preise);

        $rabatt_prozent = 10;
        $rabatt_betrag = array_sum($kinder_preise) * $rabatt_prozent / 100;

        if ($rabatt_betrag > 0) {
            $cart->add_fee(__('Geschwisterrabatt', 'micinterart'), -$rabatt_betrag, false);
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
        
        // Rabatt von 10% für jedes Paar ab dem 2. (nur Workshop-Produkte mit Paarpreis).
        // Eine gemeinsame Gebühr, damit sie bei mehreren Zeilen nur einmal auftaucht.
        $rabatt_betrag = 0;
        $rabatt_prozent = 10;

        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'] ?? null;
            if (!$product || !$this->is_workshop_product($product)) {
                continue;
            }

            // Gruppenpreise (variabler Workshop) sind pauschal: kein Geschwister- oder Paarrabatt
            if (micinterart_is_workshop_variable($product)) {
                continue;
            }
            if ($product->get_meta('_workshop_is_paar_preis', true) !== 'yes') {
                continue;
            }
            if ((int) $cart_item['quantity'] > 1) {
                $rabatt_betrag += (float) $product->get_price() * $rabatt_prozent / 100 * ((int) $cart_item['quantity'] - 1);
            }
        }

        if ($rabatt_betrag > 0) {
            $cart->add_fee(__('Paarrabatt', 'micinterart'), -$rabatt_betrag, false);
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
