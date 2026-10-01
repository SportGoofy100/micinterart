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
    return;
}

// Prüfen ob WooCommerce aktiv ist
if (!class_exists('WooCommerce')) {
    return;
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
        add_action('admin_footer', [$this, 'output_admin_type_toggle_js']);
        
        // Standardfelder ausblenden
        add_action('woocommerce_product_options_general_product_data', [$this, 'hide_standard_fields_for_werk']);
    }
    
    /**
     * Fügt Werk als Produkttyp hinzu und entfernt unnötige Typen
     */
    public function add_werk_product_type($types) {
        // Entferne unnötige Produkttypen
        unset($types['grouped']);   // Gruppiertes Produkt
        unset($types['external']); // Externes/Partnerprodukt
        unset($types['variable']); // Variables Produkt
        
        // Füge Werk hinzu
        $types['werk'] = __('Werk', 'micinterart');
        return $types;
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
        // Immer sichtbar machen, Sichtbarkeit wird über JS gesteuert
        $tabs['werk'] = [
            'label' => __('Werk-Details', 'micinterart'),
            'target' => 'werk_product_data',
            'priority' => 5,
        ];
        
        // Allgemein-Tab für Werke sichtbar lassen
        if (isset($tabs['general'])) {
            // Vermeide doppelte Klassen
            if (!in_array('show_if_werk', $tabs['general']['class'])) {
                $tabs['general']['class'][] = 'show_if_werk';
            }
        }
        
        // Für Werke nicht relevante Tabs ausblenden (Linked Product, Attribute, Advanced)
        // Versand und Lager bleiben sichtbar, da Werke physische Produkte sind
        $tabs_to_hide = ['linked_product', 'attribute', 'advanced'];
        foreach ($tabs_to_hide as $key) {
            if (isset($tabs[$key])) {
                // Vermeide doppelte Klassen
                if (!in_array('hide_if_werk', $tabs[$key]['class'])) {
                    $tabs[$key]['class'][] = 'hide_if_werk';
                }
            }
        }
        
        return $tabs;
    }
    
    /**
     * Blendet nicht relevante Standard-Felder für Werk-Produkte aus
     * (nur Preis-Felder, Versand und Lager bleiben sichtbar)
     */
    public function hide_standard_fields_for_werk($options) {
        global $post;
        
        if (!isset($post->ID)) {
            return $options;
        }
        
        $product = wc_get_product($post->ID);
        if ($product && $product->get_type() === 'werk') {
            // Preis-Felder ausblenden (Werk hat keinen Preis im alten System)
            unset($options['_regular_price']);
            unset($options['_sale_price']);
            unset($options['_price']);
            unset($options['_sold_individually']);
        }
        
        return $options;
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
        
        // Nur ausführen wenn jQuery verfügbar ist
        if (!wp_script_is('jquery', 'done')) {
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
                var productType = $('select#product-type').val();
                var $werkPanel = $('#werk_product_data');
                
                // Für Werke alle Typ-Optionen ausblenden (außer Virtuell - aber Virtuell soll DEAKTIVIERT sein)
                if (productType === 'werk') {
                    $typeOptions.not(':has(input[name="_virtual"])').hide();
                    // Virtuell deaktivieren für Werke (nicht automatisch aktiviert)
                    $typeOptions.has('input[name="_virtual"]').show().find('input').prop('checked', false).prop('disabled', false);
                    // Werk-Panel anzeigen
                    $werkPanel.closest('.panel-wrap').show();
                } else {
                    // Werk-Panel verstecken, wenn nicht Werk-Typ
                    $werkPanel.closest('.panel-wrap').hide();
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

// Initialisierung
function micinterart_werk_wc_init() {
    Micinterart_Werk_WooCommerce::get_instance();
}
add_action('after_setup_theme', 'micinterart_werk_wc_init', 25);

// Hilfsfunktion zum Prüfen ob ein Produkt ein Werk ist
function micinterart_wc_is_werk_product($product) {
    if (!is_a($product, 'WC_Product')) {
        return false;
    }
    return $product->get_type() === 'werk';
}
