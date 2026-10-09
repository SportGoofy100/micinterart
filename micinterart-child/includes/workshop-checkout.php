<?php
/**
 * Workshop Checkout Anpassungen: Teilnehmerfelder und Familienworkshops
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

/**
 * Prüft, ob ein Produkt zur Kategorie "Familienworkshop" gehört
 * (auch übersetzte Kategorien, deren Slug mit "familienworkshop" beginnt).
 */
function micinterart_is_familienworkshop($product_id) {
    $terms = get_the_terms((int) $product_id, 'product_cat');
    if (!$terms || is_wp_error($terms)) {
        return false;
    }
    foreach ($terms as $term) {
        if (strpos($term->slug, 'familienworkshop') === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Aufpreise für jeden weiteren Erwachsenen bzw. jedes weitere Kind
 *
 * @return array{adult: float, child: float}
 */
function micinterart_familie_extra_prices($product_id) {
    $adult = (float) wc_format_decimal((string) get_post_meta($product_id, '_workshop_preis_erwachsener_extra', true));
    $child = (float) wc_format_decimal((string) get_post_meta($product_id, '_workshop_preis_kind_extra', true));
    return ['adult' => $adult, 'child' => $child];
}

/**
 * Gesamtpreis: Duo-Preis (1 Erwachsener + 1 Kind) plus Aufpreise ab dem 2. Erwachsenen / 2. Kind
 */
function micinterart_familie_total($product, $adults, $children) {
    $extra = micinterart_familie_extra_prices($product->get_id());
    return (float) $product->get_price()
        + max(0, $adults - 1) * $extra['adult']
        + max(0, $children - 1) * $extra['child'];
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
        
        // CSS für Checkout-Felder
        add_action('wp_enqueue_scripts', [$this, 'enqueue_checkout_styles']);

        // Blocks-Checkout: ein Zusatzfeld für die Namen aller weiteren Teilnehmer
        add_action('woocommerce_init', [$this, 'register_blocks_checkout_field']);
        add_action('woocommerce_store_api_checkout_update_order_from_request', [$this, 'process_blocks_participants'], 10, 2);
        // Gecachte Workshop-IDs (für die Sichtbarkeit des Feldes) bei Produktänderungen verwerfen
        add_action('woocommerce_update_product', [$this, 'flush_workshop_ids_cache']);
        add_action('woocommerce_new_product', [$this, 'flush_workshop_ids_cache']);
        add_action('woocommerce_delete_product', [$this, 'flush_workshop_ids_cache']);

        // Familienworkshops: Anmeldung nach Erwachsenen und Kindern, Duo-Preis plus Aufpreise
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_familie_add_to_cart'], 10, 3);
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_familie_cart_item_data'], 10, 2);
        add_filter('woocommerce_add_to_cart_quantity', [$this, 'set_familie_quantity'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'apply_familie_price'], 20);
        add_filter('woocommerce_get_item_data', [$this, 'show_familie_item_data'], 10, 2);
        add_filter('woocommerce_cart_item_price', [$this, 'show_familie_cart_price'], 10, 3);
        add_filter('woocommerce_cart_item_quantity', [$this, 'lock_familie_cart_quantity'], 10, 3);
        add_filter('woocommerce_store_api_product_quantity_editable', [$this, 'lock_familie_blocks_quantity'], 10, 3);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_familie_line_item'], 10, 3);
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
            $quantity = (int) $item->get_quantity();
            if ($quantity > 1 && $this->is_workshop_product($item->get_product())) {
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
                    'terms'    => 'workshop',
                ],
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => ['workshops', 'atelierkurse', 'kinderworkshops', 'kinderworkshop', 'erwachsenenworkshop', 'erwachsenenworkshops', 'familienworkshop'],
                ],
            ],
        ]);
        $ids = array_values(array_unique(array_map('intval', $ids)));

        set_transient(self::WORKSHOP_IDS_TRANSIENT, $ids, 6 * HOUR_IN_SECONDS);
        return $ids;
    }

    public function flush_workshop_ids_cache() {
        delete_transient(self::WORKSHOP_IDS_TRANSIENT);
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
            echo '<p>' . esc_html(__('Du bist als Besteller automatisch als erste Person berücksichtigt. Bitte gib die Namen der weiteren Teilnehmer jeweils in einer eigenen Zeile an.', 'micinterart'));
            if (isset($cart_item['micinterart_adults'])) {
                echo ' ' . esc_html(__('Das gilt für alle weiteren Erwachsenen und Kinder.', 'micinterart'));
            }
            echo '</p>';
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
            if (in_array($term->slug, ['workshops', 'atelierkurse', 'kinderworkshops', 'kinderworkshop', 'erwachsenenworkshop', 'erwachsenenworkshops', 'familienworkshop'], true)) {
                return true;
            }
        }
        
        return false;
    }
    
    // ------------------------------------------------------------------
    // Familienworkshops
    // Die Menge im Warenkorb bleibt die Personenzahl (Erwachsene + Kinder),
    // damit Lagerbestand und Namensabfrage wie bisher funktionieren.
    // Der Preis der Position wird aus Duo-Preis und Aufpreisen berechnet.
    // ------------------------------------------------------------------

    /**
     * Gebuchte Anzahl Erwachsene/Kinder aus dem Formular
     */
    private function get_posted_familie_counts() {
        return [
            'adults'   => isset($_REQUEST['workshop_adults']) ? absint(wp_unslash($_REQUEST['workshop_adults'])) : 0,
            'children' => isset($_REQUEST['workshop_children']) ? absint(wp_unslash($_REQUEST['workshop_children'])) : 0,
        ];
    }

    public function validate_familie_add_to_cart($passed, $product_id, $quantity) {
        if (!$passed || !micinterart_is_familienworkshop($product_id)) {
            return $passed;
        }

        $counts = $this->get_posted_familie_counts();
        if ($counts['adults'] < 1) {
            wc_add_notice(__('Bei Familienworkshops muss mindestens ein Erwachsener angemeldet werden.', 'micinterart'), 'error');
            return false;
        }
        if ($counts['children'] < 1) {
            wc_add_notice(__('Bei Familienworkshops muss mindestens ein Kind angemeldet werden.', 'micinterart'), 'error');
            return false;
        }

        $product = wc_get_product($product_id);
        $persons = $counts['adults'] + $counts['children'];
        if ($product && $product->managing_stock() && !$product->backorders_allowed() && $persons > (int) $product->get_stock_quantity()) {
            wc_add_notice(__('So viele Plätze sind leider nicht mehr frei.', 'micinterart'), 'error');
            return false;
        }

        return true;
    }

    public function add_familie_cart_item_data($cart_item_data, $product_id) {
        if (micinterart_is_familienworkshop($product_id)) {
            $counts = $this->get_posted_familie_counts();
            $cart_item_data['micinterart_adults']   = $counts['adults'];
            $cart_item_data['micinterart_children'] = $counts['children'];
            // Jede Anmeldung bleibt eine eigene Position, sonst würde WooCommerce
            // zwei gleiche Anmeldungen zu einer Position mit doppelter Menge, aber einfachem Preis zusammenfassen
            $cart_item_data['micinterart_line'] = wp_generate_uuid4();
        }
        return $cart_item_data;
    }

    public function set_familie_quantity($quantity, $product_id) {
        if (micinterart_is_familienworkshop($product_id)) {
            $counts = $this->get_posted_familie_counts();
            return max(1, $counts['adults'] + $counts['children']);
        }
        return $quantity;
    }

    public function apply_familie_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {
            if (!isset($cart_item['micinterart_adults'])) {
                continue;
            }
            $quantity = max(1, (int) $cart_item['quantity']);
            $total = $this->get_familie_line_total($cart_item);
            if ($total !== null) {
                $cart_item['data']->set_price(round($total / $quantity, 6));
            }
        }
    }

    /**
     * Gesamtpreis einer Familien-Position: Duo-Preis + Aufpreise ab dem 2. Erwachsenen / 2. Kind
     */
    private function get_familie_line_total($cart_item) {
        $base = wc_get_product($cart_item['product_id']);
        if (!$base) {
            return null;
        }
        return micinterart_familie_total($base, (int) $cart_item['micinterart_adults'], (int) $cart_item['micinterart_children']);
    }

    public function show_familie_item_data($item_data, $cart_item) {
        if (isset($cart_item['micinterart_adults'])) {
            $item_data[] = ['key' => __('Erwachsene', 'micinterart'), 'value' => (int) $cart_item['micinterart_adults']];
            $item_data[] = ['key' => __('Kinder', 'micinterart'), 'value' => (int) $cart_item['micinterart_children']];
        }
        return $item_data;
    }

    public function show_familie_cart_price($price_html, $cart_item, $cart_item_key) {
        if (isset($cart_item['micinterart_adults'])) {
            $total = $this->get_familie_line_total($cart_item);
            if ($total !== null) {
                return wc_price($total);
            }
        }
        return $price_html;
    }

    public function lock_familie_cart_quantity($product_quantity, $cart_item_key, $cart_item) {
        if (isset($cart_item['micinterart_adults'])) {
            return '<span class="workshop-familie-quantity">' . (int) $cart_item['quantity'] . '</span>';
        }
        return $product_quantity;
    }

    public function lock_familie_blocks_quantity($editable, $product, $cart_item) {
        if (is_array($cart_item) && isset($cart_item['micinterart_adults'])) {
            return false;
        }
        return $editable;
    }

    public function save_familie_line_item($item, $cart_item_key, $values) {
        if (isset($values['micinterart_adults'])) {
            $item->add_meta_data(__('Erwachsene', 'micinterart'), (int) $values['micinterart_adults'], true);
            $item->add_meta_data(__('Kinder', 'micinterart'), (int) $values['micinterart_children'], true);
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
