<?php
/**
 * Plugin Name: Micinterart Werk WooCommerce
 * Description: Erweitert WooCommerce um einen Werk-Produkttyp mit speziellen Feldern
 * Version: 1.0.0
 * Author: Micinterart
 * Text Domain: micinterart
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prüfen ob WooCommerce aktiv ist
if (!class_exists('WooCommerce')) {
    exit;
}

/**
 * ============================================================================
 * WERK PRODUKTTYP
 * ============================================================================
 */

/**
 * Werk-Produktklasse - Erbt von WC_Product_Simple
 * Fügt Werk-spezifische Logik hinzu
 */
class WC_Product_Werk extends WC_Product_Simple {
    
    /**
     * Überschreibt den Produkttyp
     */
    public function get_type() {
        return 'werk';
    }
    
    // Werke sind physische Produkte
    public function is_virtual() {
        return false;
    }
    
    public function needs_shipping() {
        return true;
    }
    
    public function is_sold_individually() {
        return true;
    }
}

/**
 * ============================================================================
 * WERK WOOCOMMERCE INTEGRATION
 * ============================================================================
 */

class Micinterart_Werk_WooCommerce {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Produkttyp registrieren
        add_filter('product_type_selector', [$this, 'add_werk_product_type']);
        
        // Produktklasse für Werk-Typ registrieren
        add_filter('woocommerce_product_class', [$this, 'add_werk_product_class'], 10, 2);
        
        // Felder registrieren
        add_action('init', [$this, 'register_werk_product_fields']);
        
        // Reiter und Panels für Werk-Produkte
        add_filter('woocommerce_product_data_tabs', [$this, 'add_werk_product_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'render_werk_product_tab']);
        add_action('woocommerce_admin_process_product_object', [$this, 'save_werk_product_fields']);
        
        // Admin JS für Tab-Toggling
        // (erst nach den Footer-Scripts, damit jQuery sicher geladen ist)
        add_action('admin_print_footer_scripts', [$this, 'output_admin_type_toggle_js'], 100);
        
        // Frontend: Werk-Details auf der Produktseite anzeigen (nach dem Kurztext)
        add_action('woocommerce_single_product_summary', [$this, 'render_werk_details_frontend'], 25);

        // Frontend: Warenkorb-Button wie bei einfachen Produkten
        add_action('woocommerce_werk_add_to_cart', 'woocommerce_simple_add_to_cart');

        // Kaufbar nur bei Status "verfügbar"
        add_filter('woocommerce_is_purchasable', [$this, 'werk_product_is_purchasable'], 10, 2);

        // Nach bezahlter Bestellung Status auf "verkauft" setzen, bei Storno/Erstattung zurück
        add_action('woocommerce_order_status_processing', [$this, 'mark_werke_sold']);
        add_action('woocommerce_order_status_completed', [$this, 'mark_werke_sold']);
        add_action('woocommerce_order_status_cancelled', [$this, 'release_werke']);
        add_action('woocommerce_order_status_refunded', [$this, 'release_werke']);
    }

    /**
     * Bezahlte Bestellung (in Bearbeitung / abgeschlossen): enthaltene Werke als verkauft markieren
     */
    public function mark_werke_sold($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        foreach ($order->get_items('line_item') as $item) {
            $product = $item->get_product();
            if (!$product || $product->get_type() !== 'werk') {
                continue;
            }
            update_post_meta($product->get_id(), '_werk_status', 'verkauft');
            update_post_meta($product->get_id(), '_werk_sold_order', (int) $order_id);
        }
    }

    /**
     * Stornierte oder erstattete Bestellung: Werke wieder verfügbar machen,
     * aber nur, wenn genau diese Bestellung sie als verkauft markiert hat
     */
    public function release_werke($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        foreach ($order->get_items('line_item') as $item) {
            $product = $item->get_product();
            if (!$product || $product->get_type() !== 'werk') {
                continue;
            }
            if ((int) get_post_meta($product->get_id(), '_werk_sold_order', true) !== (int) $order_id) {
                continue;
            }
            update_post_meta($product->get_id(), '_werk_status', 'verfuegbar');
            delete_post_meta($product->get_id(), '_werk_sold_order');
        }
    }

    /**
     * Ein Werk kann nur gekauft werden, solange der Status "verfügbar" ist
     * (ohne gesetzten Status gilt es als verfügbar).
     */
    public function werk_product_is_purchasable($is_purchasable, $product) {
        if ($product && $product->get_type() === 'werk') {
            $status = $product->get_meta('_werk_status', true);
            $is_purchasable = $is_purchasable && ($status === '' || $status === 'verfuegbar');
        }
        return $is_purchasable;
    }

    /**
     * Zeigt Jahr, Maße, Materialien usw. auf der Produktseite eines Werks
     */
    public function render_werk_details_frontend() {
        $product = wc_get_product(get_the_ID());
        if (!$product || $product->get_type() !== 'werk') {
            return;
        }

        $is_en = function_exists('micinterart_is_english') && micinterart_is_english();
        $rows = [
            ($is_en ? 'Year' : 'Jahr')                  => $product->get_meta('_werk_year', true),
            ($is_en ? 'Dimensions' : 'Maße')            => $product->get_meta('_werk_dimensions', true),
            ($is_en ? 'Materials' : 'Materialien')      => $product->get_meta('_werk_materials', true),
            ($is_en ? 'Represented by' : 'Vertreten durch') => $product->get_meta('_werk_represented', true),
            ($is_en ? 'Exhibited at' : 'Ausgestellt bei')   => $product->get_meta('_werk_exhibited', true),
        ];
        // Status nur anzeigen, wenn das Werk nicht (mehr) verfügbar ist
        $status_labels = [
            'reserviert'   => $is_en ? 'Reserved' : 'Reserviert',
            'verkauft'     => $is_en ? 'Sold' : 'Verkauft',
            'privatbesitz' => $is_en ? 'Private collection' : 'Privatbesitz',
        ];
        $status = $product->get_meta('_werk_status', true);
        if (isset($status_labels[$status])) {
            $rows['Status'] = $status_labels[$status];
        }
        $rows = array_filter($rows, function ($value) {
            return trim((string) $value) !== '';
        });
        if (empty($rows)) {
            return;
        }

        echo '<ul class="werk-details">';
        foreach ($rows as $label => $value) {
            echo '<li><strong>' . esc_html($label) . ':</strong> ' . esc_html($value) . '</li>';
        }
        echo '</ul>';
    }
    
    /**
     * Fügt Werk als Produkttyp hinzu und entfernt unnötige Typen
     */
    public function add_werk_product_type($types) {
        // Nicht benötigte Produkttypen ausblenden, aber nur, wenn sie nirgends verwendet werden.
        // Sonst würden bestehende Produkte dieses Typs im Editor keinen passenden Typ mehr haben
        // und beim Speichern ungewollt zu "Einfaches Produkt".
        foreach (['grouped', 'external', 'variable'] as $unused_type) {
            if (isset($types[$unused_type]) && !$this->product_type_is_in_use($unused_type)) {
                unset($types[$unused_type]);
            }
        }

        // Füge Werk hinzu
        $types['werk'] = __('Werk', 'micinterart');
        return $types;
    }

    /**
     * Prüft, ob ein Produkttyp noch verwendet wird (bei einem Produkt in irgendeinem Status
     * oder beim gerade bearbeiteten Produkt)
     */
    private function product_type_is_in_use($type) {
        // Das gerade bearbeitete Produkt hat diesen Typ
        $edited_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
        if ($edited_id && get_post_type($edited_id) === 'product' && class_exists('WC_Product_Factory')) {
            if (WC_Product_Factory::get_product_type($edited_id) === $type) {
                return true;
            }
        }

        // Irgendein Produkt (auch Entwurf, privat, Papierkorb) hat diesen Typ
        $ids = get_posts([
            'post_type'      => 'product',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'tax_query'      => [[
                'taxonomy' => 'product_type',
                'field'    => 'slug',
                'terms'    => $type,
            ]],
        ]);

        return !empty($ids);
    }
    
    /**
     * Registriert die WC_Product_Werk Klasse für den Produkttyp 'werk'
     */
    public function add_werk_product_class($classname, $product_type) {
        if ($product_type === 'werk') {
            $classname = 'WC_Product_Werk';
        }
        return $classname;
    }
    
    /**
     * Registriert benutzerdefinierte Felder für Werk-Produkte
     */
    public function register_werk_product_fields() {
        // Auth-Callback Funktion (wird für alle Felder verwendet)
        $auth_callback = function() {
            // Im REST-Kontext
            if (defined('REST_REQUEST') && REST_REQUEST) {
                return current_user_can('edit_posts');
            }
            // Im Admin-Kontext
            return current_user_can('edit_posts');
        };
        
        $fields = [
            '_werk_materials',
            '_werk_dimensions',
            '_werk_year',
            '_werk_represented',
            '_werk_exhibited',
            '_werk_status',
        ];
        
        foreach ($fields as $field) {
            register_post_meta('product', $field, [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => $auth_callback
            ]);
        }
        
        // Mehrere Bilder als serialisiertes Array (gespeichert als string, aber Array beim Speichern)
        register_post_meta('product', '_werk_additional_images', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => $auth_callback,
            'sanitize_callback' => function($value) {
                // Akzeptiert sowohl String als auch Array
                if (is_array($value)) {
                    return implode(',', array_map('intval', $value));
                }
                if (is_string($value)) {
                    return sanitize_text_field($value);
                }
                return '';
            }
        ]);
    }
    
    /**
     * Fügt Werk-Reiter zum Produkt-Editor hinzu
     */
    public function add_werk_product_tab($tabs) {
        // Immer registrieren; die Sichtbarkeit steuert WooCommerce über die Klasse show_if_werk
        $tabs['werk'] = [
            'label'    => __('Werk-Details', 'micinterart'),
            'target'   => 'werk_product_data',
            'class'    => ['show_if_werk'],
            'priority' => 5,
        ];

        // Der Allgemein-Tab (Preis, Steuer) ist bei WooCommerce für alle Typen außer "Gruppiert"
        // sichtbar. Ihm keine show_if_*-Klasse geben: WooCommerce versteckt sonst bei anderen Typen
        // (z.B. "Einfaches Produkt") alle show_if_<Typ>-Elemente, die nicht zum Typ passen.

        // Für Werke nicht relevante Tabs ausblenden (Linked Product, Attribute, Advanced)
        // Versand und Lager bleiben sichtbar, da Werke physische Produkte sind
        $tabs_to_hide = ['linked_product', 'attribute', 'advanced'];
        foreach ($tabs_to_hide as $key) {
            if (isset($tabs[$key])) {
                if (!isset($tabs[$key]['class'])) {
                    $tabs[$key]['class'] = [];
                }
                // Vermeide doppelte Klassen
                if (!in_array('hide_if_werk', $tabs[$key]['class'], true)) {
                    $tabs[$key]['class'][] = 'hide_if_werk';
                }
            }
        }
        
        return $tabs;
    }
    
    /**
     * Blendet Preis-/Versandfelder aus und zeigt Steuerfelder für den Typ 'werk'
     */
    public function output_admin_type_toggle_js() {
        // Nur auf der Produkt-Bearbeitungsseite
        if (!function_exists('get_current_screen')) {
            return;
        }
        
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'product') {
            return;
        }
        ?>
        <script>
        jQuery(function($) {
            // Typ-Optionen (Virtuell, Herunterladbar, Elektrogerät, Differenzbesteuert,
            // Lebensmittel ...) sind Checkbox-Labels im Kopf der Box, außerhalb der Panels.
            var $typeOptions = $('#woocommerce-product-data label').filter(function() {
                return $(this).find('input[type="checkbox"]').length > 0 &&
                       $(this).closest('.panel, .woocommerce_options_panel').length === 0;
            });
            
            function toggleWerkTypeOptions() {
                var isWerk = ($('select#product-type').val() === 'werk');

                // Preis- und Steuerfelder von WooCommerce auch für Werke anzeigen
                var $priceGroups = $('#general_product_data .options_group.pricing')
                    .add($('#general_product_data .options_group').has('#_tax_status, #_tax_class'));
                $priceGroups.addClass('show_if_werk');

                // show_if_werk / hide_if_werk (Reiter) schaltet WooCommerce selbst um.
                // Nie den gesamten .panel-wrap verstecken, sonst verschwinden auch die
                // Panels anderer Produkttypen (z.B. Workshop-Details).
                if (!isWerk) {
                    return;
                }
                $priceGroups.show();

                // Für Werke alle Typ-Optionen ausblenden (außer Virtuell - aber Virtuell soll DEAKTIVIERT sein)
                $typeOptions.not(':has(input[name="_virtual"])').hide();
                // Virtuell deaktivieren für Werke (nicht automatisch aktiviert)
                $typeOptions.has('input[name="_virtual"]').show().find('input').prop('checked', false).prop('disabled', false);

                // Ist der aktive Reiter ausgeblendet worden, den ersten sichtbaren aktivieren,
                // sonst bleibt das Panel leer
                if ($('.product_data_tabs li.active').is(':hidden')) {
                    $('.product_data_tabs li:visible').first().find('a').trigger('click');
                }
            }
            
            $('select#product-type').on('change', toggleWerkTypeOptions);
            $(document.body).on('woocommerce-product-type-change', toggleWerkTypeOptions);
            toggleWerkTypeOptions();
        });
        </script>
        <?php
    }
    
    /**
     * Render Werk-Reiter im Produkt-Editor
     */
    public function render_werk_product_tab() {
        global $post;
        
        $product_id = $post->ID ?? 0;
        
        // Meta-Werte laden (auch wenn Produkt noch nicht gespeichert ist)
        $materials = get_post_meta($product_id, '_werk_materials', true);
        $dimensions = get_post_meta($product_id, '_werk_dimensions', true);
        $year = get_post_meta($product_id, '_werk_year', true);
        $represented = get_post_meta($product_id, '_werk_represented', true);
        $exhibited = get_post_meta($product_id, '_werk_exhibited', true);
        $status = get_post_meta($product_id, '_werk_status', true);
        $additional_images = get_post_meta($product_id, '_werk_additional_images', true);
        
        // Konvertiere Array zu comma-separiertem String für das Textarea-Feld
        if (is_array($additional_images)) {
            $additional_images = implode(',', array_map('intval', $additional_images));
        } elseif (!is_string($additional_images)) {
            $additional_images = '';
        }
        
        echo '<div id="werk_product_data" class="panel woocommerce_options_panel hidden">';
        
        // Jahr, Maße, Materialien
        echo '<div class="options_group">';
        woocommerce_wp_text_input([
            'id' => '_werk_year',
            'label' => __('Jahr', 'micinterart'),
            'placeholder' => '2024',
            'value' => $year,
        ]);
        echo '</div>';
        
        echo '<div class="options_group">';
        woocommerce_wp_text_input([
            'id' => '_werk_dimensions',
            'label' => __('Maße', 'micinterart'),
            'placeholder' => '50 x 70 cm',
            'value' => $dimensions,
        ]);
        echo '</div>';
        
        echo '<div class="options_group">';
        woocommerce_wp_text_input([
            'id' => '_werk_materials',
            'label' => __('Materialien', 'micinterart'),
            'placeholder' => 'Acryl auf Leinwand, Holz',
            'value' => $materials,
        ]);
        echo '</div>';
        
        // Vertreten durch / Ausgestellt bei
        echo '<div class="options_group">';
        woocommerce_wp_text_input([
            'id' => '_werk_represented',
            'label' => __('Vertreten durch', 'micinterart'),
            'placeholder' => 'Galerie XYZ',
            'value' => $represented,
        ]);
        woocommerce_wp_text_input([
            'id' => '_werk_exhibited',
            'label' => __('Ausgestellt bei', 'micinterart'),
            'placeholder' => 'Museum ABC, Berlin',
            'value' => $exhibited,
        ]);
        echo '</div>';
        
        // Status
        woocommerce_wp_select([
            'id' => '_werk_status',
            'label' => __('Status', 'micinterart'),
            'options' => [
                'verfuegbar' => 'Verfügbar',
                'reserviert' => 'Reserviert',
                'verkauft' => 'Verkauft',
                'privatbesitz' => 'Privatbesitz',
            ],
            'value' => $status,
        ]);
        
        // Weitere Bilder (Media IDs)
        echo '<div class="options_group">';
        echo '<p class="form-field">';
        echo '<label for="_werk_additional_images">' . __('Weitere Bilder (Media-IDs)', 'micinterart') . '</label>';
        echo '<textarea name="_werk_additional_images" id="_werk_additional_images" rows="3" cols="40" placeholder="123, 456, 789">' . esc_textarea($additional_images) . '</textarea>';
        echo '<span class="description">' . __('Komma-separierte Liste von Medien-IDs', 'micinterart') . '</span>';
        echo '</p>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Speichert die Werk-Felder
     */
    public function save_werk_product_fields($product) {
        if (!is_a($product, 'WC_Product') || $product->get_type() !== 'werk') {
            return;
        }

        $post_id = $product->get_id();
        
        // Verifizierung des Nonce-Felds
        if (!isset($_POST['woocommerce_meta_nonce']) || !wp_verify_nonce($_POST['woocommerce_meta_nonce'], 'woocommerce_save_data')) {
            return;
        }
        
        $fields = [
            '_werk_year',
            '_werk_dimensions',
            '_werk_materials',
            '_werk_represented',
            '_werk_exhibited',
            '_werk_status',
        ];
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $product->update_meta_data($field, sanitize_text_field(wp_unslash($_POST[$field])));
            } else {
                $product->delete_meta_data($field);
            }
        }
        
        // Weitere Bilder (Textarea mit comma-separierten IDs)
        if (isset($_POST['_werk_additional_images'])) {
            $images = sanitize_text_field(wp_unslash($_POST['_werk_additional_images']));
            // Als comma-separierten String speichern
            $image_ids = array_map('intval', array_filter(explode(',', $images)));
            $product->update_meta_data('_werk_additional_images', implode(',', $image_ids));
        } else {
            $product->delete_meta_data('_werk_additional_images');
        }
    }
}

// Die Initialisierung (get_instance) erfolgt in functions.php

// Hilfsfunktion zum Prüfen ob ein Produkt ein Werk ist
function micinterart_wc_is_werk_product($product) {
    if (!is_a($product, 'WC_Product')) {
        return false;
    }
    return $product->get_type() === 'werk';
}
