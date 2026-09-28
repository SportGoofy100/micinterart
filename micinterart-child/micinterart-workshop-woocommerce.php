<?php
/**
 * Plugin Name: Micinterart Workshop WooCommerce
 * Description: Wandelt Workshops in WooCommerce-Produkte um mit speziellen Feldern und Kategorien
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

class Micinterart_Workshop_WooCommerce {
    
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
        // Produktkategorien erstellen
        add_action('init', [$this, 'create_workshop_categories']);
        
        // Benutzerdefinierte Felder für Workshops registrieren
        add_action('init', [$this, 'register_workshop_product_fields']);
        
        // Workshop-Reiter im Produkt-Editor hinzufügen
        add_filter('woocommerce_product_data_tabs', [$this, 'add_workshop_product_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'render_workshop_product_tab']);
        add_action('woocommerce_process_product_meta', [$this, 'save_workshop_product_fields']);
        
        // Workshop-Produkte beim Speichern verarbeiten
        add_action('woocommerce_before_product_object_save', [$this, 'before_product_save'], 10, 2);
        
        // Produkt ist ein Workshop? (Hilfsfunktion)
        add_filter('micinterart_is_workshop_product', [$this, 'is_workshop_product'], 10, 2);
        
        // Workshop-spezifische Validierung
        add_filter('woocommerce_product_is_purchasable', [$this, 'workshop_product_is_purchasable'], 10, 2);
        
        // Workshop-spezifische Stock-Logik
        add_filter('woocommerce_product_get_stock_quantity', [$this, 'workshop_product_stock_quantity'], 10, 2);
    }
    
    /**
     * Erstellt die Workshop-Produktkategorien
     */
    public function create_workshop_categories() {
        // Prüfen ob die Kategorien bereits existieren
        $workshops_term = get_term_by('slug', 'workshops', 'product_cat');
        if (!$workshops_term) {
            $workshops_id = wp_insert_term(
                'Workshops',
                'product_cat',
                [
                    'description' => 'Alle Workshops',
                    'slug' => 'workshops'
                ]
            );
        } else {
            $workshops_id = $workshops_term->term_id;
        }
        
        // Unterkategorien erstellen
        $sub_categories = [
            ['name' => 'Atelierkurse', 'slug' => 'atelierkurse'],
            ['name' => 'Kinderworkshops', 'slug' => 'kinderworkshops']
        ];
        
        foreach ($sub_categories as $sub_cat) {
            $term = get_term_by('slug', $sub_cat['slug'], 'product_cat');
            if (!$term) {
                wp_insert_term(
                    $sub_cat['name'],
                    'product_cat',
                    [
                        'description' => $sub_cat['name'],
                        'slug' => $sub_cat['slug'],
                        'parent' => $workshops_id ? (is_array($workshops_id) ? $workshops_id['term_id'] : $workshops_id) : 0
                    ]
                );
            }
        }
    }
    
    /**
     * Registriert benutzerdefinierte Felder für Workshop-Produkte
     */
    public function register_workshop_product_fields() {
        $fields = [
            '_workshop_datum',
            '_workshop_uhrzeit_von',
            '_workshop_uhrzeit_bis',
            '_workshop_ort',
            '_workshop_adresse',
            '_workshop_alter_von',
            '_workshop_alter_bis',
            '_workshop_preis_info',
            '_workshop_sprache',
            '_workshop_max_teilnehmer',
            '_workshop_is_paar_preis',
            '_workshop_current_bookings',
            '_workshop_status',
        ];
        
        foreach ($fields as $field) {
            register_post_meta('product', $field, [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => function() {
                    return current_user_can('edit_posts');
                }
            ]);
        }
        
        // "Was dich erwartet" Felder
        for ($i = 1; $i <= 8; $i++) {
            foreach (['emoji', 'titel', 'text'] as $part) {
                register_post_meta('product', "_workshop_erwartet_{$i}_{$part}", [
                    'type' => 'string',
                    'single' => true,
                    'show_in_rest' => true,
                ]);
            }
        }
    }
    
    /**
     * Fügt Workshop-Reiter zum Produkt-Editor hinzu
     */
    public function add_workshop_product_tab($tabs) {
        $tabs['workshop'] = [
            'label' => __('Workshop-Details', 'micinterart'),
            'target' => 'workshop_product_data',
            'priority' => 25,
            'class' => ['show_if_workshop'],
        ];
        return $tabs;
    }
    
    /**
     * Render Workshop-Reiter im Produkt-Editor
     */
    public function render_workshop_product_tab() {
        global $post, $product_object;
        
        if (!is_a($product_object, 'WC_Product')) {
            $product_object = wc_get_product($post->ID);
        }
        
        // Nur für Produkte in der Workshops-Kategorie anzeigen
        if (!$this->is_workshop_product($product_object)) {
            echo '<div id="workshop_product_data" class="panel woocommerce_options_panel hidden">';
            echo '<p>' . __('Dieser Reiter ist nur für Produkte in der Kategorie "Workshops" sichtbar.', 'micinterart') . '</p>';
            echo '</div>';
            return;
        }
        
        $product_id = $product_object->get_id();
        
        // Meta-Werte laden
        $datum = get_post_meta($product_id, '_workshop_datum', true);
        $uhrzeit_von = get_post_meta($product_id, '_workshop_uhrzeit_von', true);
        $uhrzeit_bis = get_post_meta($product_id, '_workshop_uhrzeit_bis', true);
        $ort = get_post_meta($product_id, '_workshop_ort', true);
        $adresse = get_post_meta($product_id, '_workshop_adresse', true);
        $alter_von = get_post_meta($product_id, '_workshop_alter_von', true);
        $alter_bis = get_post_meta($product_id, '_workshop_alter_bis', true);
        $preis_info = get_post_meta($product_id, '_workshop_preis_info', true);
        $sprache = get_post_meta($product_id, '_workshop_sprache', true);
        $max_teilnehmer = get_post_meta($product_id, '_workshop_max_teilnehmer', true);
        $is_paar = get_post_meta($product_id, '_workshop_is_paar_preis', true);
        $status = get_post_meta($product_id, '_workshop_status', true);
        $current_bookings = get_post_meta($product_id, '_workshop_current_bookings', true);
        
        // Lagerbestand aus WC holen
        $stock_quantity = $product_object->get_stock_quantity();
        
        // Werkzeug: Falls Lagerbestand nicht gesetzt, aber max_teilnehmer vorhanden, synchronisieren
        if (empty($stock_quantity) && !empty($max_teilnehmer)) {
            $product_object->set_stock_quantity($max_teilnehmer - (int)$current_bookings);
            $product_object->save();
        }
        
        echo '<div id="workshop_product_data" class="panel woocommerce_options_panel">';
        
        // Datum und Uhrzeit
        woocommerce_wp_text_input([
            'id' => '_workshop_datum',
            'label' => __('Datum', 'micinterart'),
            'placeholder' => 'YYYY-MM-DD',
            'value' => $datum,
            'desc_tip' => true,
            'description' => __('Workshop-Datum im Format JJJJ-MM-TT', 'micinterart'),
        ]);
        
        echo '<div class="options_group">';
        woocommerce_wp_text_input([
            'id' => '_workshop_uhrzeit_von',
            'label' => __('Uhrzeit von', 'micinterart'),
            'placeholder' => '10:00',
            'value' => $uhrzeit_von,
        ]);
        woocommerce_wp_text_input([
            'id' => '_workshop_uhrzeit_bis',
            'label' => __('Uhrzeit bis', 'micinterart'),
            'placeholder' => '15:00',
            'value' => $uhrzeit_bis,
        ]);
        echo '</div>';
        
        // Ort und Adresse
        echo '<div class="options_group">';
        woocommerce_wp_text_input([
            'id' => '_workshop_ort',
            'label' => __('Ort', 'micinterart'),
            'placeholder' => 'Morsbach',
            'value' => $ort,
        ]);
        woocommerce_wp_text_input([
            'id' => '_workshop_adresse',
            'label' => __('Adresse', 'micinterart'),
            'placeholder' => 'Straße und Hausnummer',
            'value' => $adresse,
        ]);
        echo '</div>';
        
        // Altersempfehlung
        echo '<div class="options_group">';
        woocommerce_wp_text_input([
            'id' => '_workshop_alter_von',
            'label' => __('Alter von', 'micinterart'),
            'placeholder' => '6',
            'value' => $alter_von,
        ]);
        woocommerce_wp_text_input([
            'id' => '_workshop_alter_bis',
            'label' => __('Alter bis', 'micinterart'),
            'placeholder' => '12',
            'value' => $alter_bis,
        ]);
        echo '</div>';
        
        // Preis-Informationen
        echo '<div class="options_group">';
        woocommerce_wp_text_input([
            'id' => '_workshop_preis_info',
            'label' => __('Preis-Info', 'micinterart'),
            'placeholder' => 'pro Kind / pro Paar',
            'value' => $preis_info,
        ]);
        woocommerce_wp_select([
            'id' => '_workshop_sprache',
            'label' => __('Kurssprache', 'micinterart'),
            'options' => [
                'deutsch' => 'Deutsch',
                'russisch' => 'Russisch',
            ],
            'value' => $sprache ?: 'deutsch',
        ]);
        echo '</div>';
        
        // Teilnehmer und Status
        echo '<div class="options_group">';
        woocommerce_wp_text_input([
            'id' => '_workshop_max_teilnehmer',
            'label' => __('Max. Teilnehmer', 'micinterart'),
            'placeholder' => '8',
            'value' => $max_teilnehmer,
            'type' => 'number',
        ]);
        woocommerce_wp_text_input([
            'id' => '_workshop_current_bookings',
            'label' => __('Aktuelle Buchungen', 'micinterart'),
            'placeholder' => '0',
            'value' => $current_bookings,
            'type' => 'number',
            'desc_tip' => true,
            'description' => __('Wird automatisch aus dem Lagerbestand berechnet', 'micinterart'),
        ]);
        echo '</div>';
        
        woocommerce_wp_checkbox([
            'id' => '_workshop_is_paar_preis',
            'label' => __('Paarpreis', 'micinterart'),
            'description' => __('Aktivieren, wenn der Preis pro Paar gilt', 'micinterart'),
            'value' => $is_paar ? 'yes' : 'no',
        ]);
        
        woocommerce_wp_select([
            'id' => '_workshop_status',
            'label' => __('Status', 'micinterart'),
            'options' => [
                'geplant' => 'Geplant',
                'anmeldung_offen' => 'Anmeldung offen',
                'fast_ausgebucht' => 'Fast ausgebucht',
                'ausgebucht' => 'Ausgebucht',
                'beendet' => 'Beendet',
                'abgesagt' => 'Abgesagt',
            ],
            'value' => $status ?: 'geplant',
        ]);
        
        // "Was dich erwartet" Felder
        $this->render_erwartet_fields($product_id);
        
        echo '</div>';
    }
    
    /**
     * Rendert die "Was dich erwartet" Felder
     */
    private function render_erwartet_fields($product_id) {
        $default_felder = [
            1 => ['emoji' => '🎨', 'titel' => 'Alle Materialien inklusive', 'text' => 'Du brauchst nichts mitzubringen – alles ist vorbereitet'],
            3 => ['emoji' => '🎓', 'titel' => 'Keine Vorkenntnisse nötig', 'text' => 'Ich begleite dich Schritt für Schritt'],
            4 => ['emoji' => '🖼️', 'titel' => 'Dein fertiges Kunstwerk', 'text' => 'Zum Mitnehmen und stolz nach Hause tragen'],
            5 => ['emoji' => '☕', 'titel' => 'Inklusive:', 'text' => 'Kaffee, Tee, Wasser und kleine Leckereien'],
        ];
        
        echo '<h3>' . __('Was dich erwartet', 'micinterart') . '</h3>';
        echo '<p class="description">' . __('Feld 2 (Kleine Gruppen) und Feld 7 (Kurssprache) sind vollautomatisch. Feld 6 (Parkplätze) passt sich automatisch an.', 'micinterart') . '</p>';
        
        foreach ($default_felder as $nr => $feld) {
            $emoji = get_post_meta($product_id, "_workshop_erwartet_{$nr}_emoji", true);
            $titel = get_post_meta($product_id, "_workshop_erwartet_{$nr}_titel", true);
            $text = get_post_meta($product_id, "_workshop_erwartet_{$nr}_text", true);
            
            echo '<div class="options_group">';
            echo '<p style="margin-bottom: 5px; font-weight: bold;">Feld ' . $nr . '</p>';
            woocommerce_wp_text_input([
                'id' => "_workshop_erwartet_{$nr}_emoji",
                'label' => 'Emoji',
                'placeholder' => $feld['emoji'],
                'value' => $emoji,
            ]);
            woocommerce_wp_text_input([
                'id' => "_workshop_erwartet_{$nr}_titel",
                'label' => 'Titel',
                'placeholder' => $feld['titel'],
                'value' => $titel,
            ]);
            woocommerce_wp_text_input([
                'id' => "_workshop_erwartet_{$nr}_text",
                'label' => 'Beschreibung',
                'placeholder' => $feld['text'],
                'value' => $text,
            ]);
            echo '</div>';
        }
    }
    
    /**
     * Speichert die Workshop-Felder
     */
    public function save_workshop_product_fields($post_id) {
        $fields = [
            '_workshop_datum',
            '_workshop_uhrzeit_von',
            '_workshop_uhrzeit_bis',
            '_workshop_ort',
            '_workshop_adresse',
            '_workshop_alter_von',
            '_workshop_alter_bis',
            '_workshop_preis_info',
            '_workshop_sprache',
            '_workshop_max_teilnehmer',
            '_workshop_current_bookings',
            '_workshop_status',
        ];
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
        
        // Checkbox für Paarpreis
        if (isset($_POST['_workshop_is_paar_preis'])) {
            update_post_meta($post_id, '_workshop_is_paar_preis', 'yes');
        } else {
            delete_post_meta($post_id, '_workshop_is_paar_preis');
        }
        
        // "Was dich erwartet" Felder
        for ($i = 1; $i <= 8; $i++) {
            foreach (['emoji', 'titel', 'text'] as $part) {
                $field_name = "_workshop_erwartet_{$i}_{$part}";
                if (isset($_POST[$field_name])) {
                    update_post_meta($post_id, $field_name, sanitize_text_field($_POST[$field_name]));
                }
            }
        }
        
        // Synchronisiere max_teilnehmer mit Lagerbestand
        if (isset($_POST['_workshop_max_teilnehmer']) && !empty($_POST['_workshop_max_teilnehmer'])) {
            $max_teilnehmer = (int)$_POST['_workshop_max_teilnehmer'];
            $current_bookings = (int)(isset($_POST['_workshop_current_bookings']) ? $_POST['_workshop_current_bookings'] : 0);
            $stock_quantity = max(0, $max_teilnehmer - $current_bookings);
            
            $product = wc_get_product($post_id);
            if ($product) {
                $product->set_manage_stock(true);
                $product->set_stock_quantity($stock_quantity);
                $product->save();
            }
        }
    }
    
    /**
     * Prüft ob ein Produkt ein Workshop ist
     */
    public function is_workshop_product($is_workshop, $product) {
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
     * Prüft ob ein Workshop-Produkt kaufbar ist
     */
    public function workshop_product_is_purchasable($is_purchasable, $product) {
        if (!$this->is_workshop_product(false, $product)) {
            return $is_purchasable;
        }
        
        $product_id = $product->get_id();
        $status = get_post_meta($product_id, '_workshop_status', true);
        
        // Nicht kaufbar bei diesen Status
        $non_purchasable_status = ['beendet', 'abgesagt', 'ausgebucht'];
        
        if (in_array($status, $non_purchasable_status)) {
            return false;
        }
        
        return $is_purchasable;
    }
    
    /**
     * Workshop-spezifische Lagerbestands-Logik
     */
    public function workshop_product_stock_quantity($quantity, $product) {
        if (!$this->is_workshop_product(false, $product)) {
            return $quantity;
        }
        
        $product_id = $product->get_id();
        $max_teilnehmer = get_post_meta($product_id, '_workshop_max_teilnehmer', true);
        $current_bookings = get_post_meta($product_id, '_workshop_current_bookings', true);
        
        if (!empty($max_teilnehmer)) {
            $calculated = max(0, (int)$max_teilnehmer - (int)$current_bookings);
            return $calculated;
        }
        
        return $quantity;
    }
    
    /**
     * Vor dem Speichern des Produkts
     */
    public function before_product_save($product, $data_store) {
        // Nicht Workshops überspringen
        if (!$this->is_workshop_product(false, $product)) {
            return;
        }
        
        $product_id = $product->get_id();
        
        // Wenn Stock-Management aktiviert und max_teilnehmer gesetzt
        if ($product->get_manage_stock() && $product->get_stock_quantity() !== null) {
            $max_teilnehmer = get_post_meta($product_id, '_workshop_max_teilnehmer', true);
            if (!empty($max_teilnehmer)) {
                // Synchronisiere current_bookings
                $stock = $product->get_stock_quantity();
                $max = (int)$max_teilnehmer;
                $current_bookings = max(0, $max - $stock);
                update_post_meta($product_id, '_workshop_current_bookings', $current_bookings);
            }
        }
    }
}

// Initialisierung
function micinterart_workshop_wc_init() {
    Micinterart_Workshop_WooCommerce::get_instance();
}

add_action('woocommerce_loaded', 'micinterart_workshop_wc_init');

// Hilfsfunktion zum Prüfen ob ein Produkt ein Workshop ist
function micinterart_wc_is_workshop_product($product) {
    return Micinterart_Workshop_WooCommerce::get_instance()->is_workshop_product(false, $product);
}
