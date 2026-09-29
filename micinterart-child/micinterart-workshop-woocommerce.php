<?php
/**
 * Plugin Name: Micinterart Workshop WooCommerce
 * Description: Erweitert WooCommerce um einen Workshop-Produkttyp mit speziellen Feldern
 * Version: 2.0.0
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
 * WORKSHOP PRODUKTTYP
 * ============================================================================
 */

/**
 * Workshop-Produktklasse - Erbt von WC_Product_Simple
 * Fügt Workshop-spezifische Logik hinzu
 */
class WC_Product_Workshop extends WC_Product_Simple {
    
    /**
     * Konstrukt: Setzt den Produkttyp auf 'workshop'
     */
    public function __construct($product) {
        $this->product_type = 'workshop';
        parent::__construct($product);
    }
    
    /**
     * Überschreibt die Standard-Preis-Anzeige
     * Zeigt ggf. Preis-Info an
     */
    public function get_price_html($deprecated = '') {
        $price = $this->get_price();
        $preis_info = $this->get_meta('_workshop_preis_info', true);
        
        $html = parent::get_price_html($deprecated);
        
        if (!empty($preis_info)) {
            $html .= '<small class="workshop-preis-info">' . esc_html($preis_info) . '</small>';
        }
        
        return $html;
    }
    
    /**
     * Automatische Stock-Berechnung aus Max. Teilnehmer
     */
    public function get_stock_quantity($context = 'view') {
        $stock = parent::get_stock_quantity($context);
        
        // Falls Stock nicht gesetzt, aber max_teilnehmer vorhanden
        if ($stock === '' || $stock === null) {
            $max_teilnehmer = $this->get_meta('_workshop_max_teilnehmer', true);
            $current_bookings = $this->get_meta('_workshop_current_bookings', true);
            
            if (!empty($max_teilnehmer)) {
                $stock = max(0, (int)$max_teilnehmer - (int)$current_bookings);
                $this->set_stock_quantity($stock);
                $this->save();
            }
        }
        
        return $stock;
    }
    
    /**
     * Prüft ob das Produkt ein Workshop ist
     */
    public function is_workshop() {
        return $this->get_type() === 'workshop';
    }
}

/**
 * ============================================================================
 * WORKSHOP WOOCOMMERCE INTEGRATION
 * ============================================================================
 */

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
        // Produkttyp registrieren (FRÜH, damit WC ihn kennt)
        add_filter('woocommerce_product_type_selector', [$this, 'add_workshop_product_type']);
        
        // Produktklasse für Workshop-Typ registrieren
        add_filter('woocommerce_product_class', [$this, 'add_workshop_product_class'], 10, 4);
        
        // Felder registrieren
        add_action('init', [$this, 'register_workshop_product_fields']);
        
        // Reiter und Panels für Workshop-Produkte
        add_filter('woocommerce_product_data_tabs', [$this, 'add_workshop_product_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'render_workshop_product_tab']);
        add_action('woocommerce_process_product_meta', [$this, 'save_workshop_product_fields']);
        
        // Workshop-spezifische Validierung
        add_filter('woocommerce_product_is_purchasable', [$this, 'workshop_product_is_purchasable'], 10, 2);
        
        // Lagerbestand aus Workshop-Feldern
        add_filter('woocommerce_product_get_stock_quantity', [$this, 'workshop_product_stock_quantity'], 10, 2);
    }
    
    /**
     * Fügt Workshop als Produkttyp hinzu
     */
    public function add_workshop_product_type($types) {
        $types['workshop'] = __('Workshop', 'micinterart');
        return $types;
    }
    
    /**
     * Registriert die WC_Product_Workshop Klasse für den Produkttyp 'workshop'
     */
    public function add_workshop_product_class($classname, $product_type, $product_id, $product) {
        if ($product_type === 'workshop') {
            $classname = 'WC_Product_Workshop';
        }
        return $classname;
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
     * Nur für Workshop-Produkttyp
     */
    public function add_workshop_product_tab($tabs) {
        // Nur für Workshop-Produkte
        global $post, $product_object;
        
        if (!isset($product_object) || !is_a($product_object, 'WC_Product')) {
            $product_object = wc_get_product($post->ID ?? 0);
        }
        
        if ($product_object && $product_object->get_type() === 'workshop') {
            $tabs['workshop'] = [
                'label' => __('Workshop-Details', 'micinterart'),
                'target' => 'workshop_product_data',
                'priority' => 25,
            ];
        }
        
        return $tabs;
    }
    
    /**
     * Render Workshop-Reiter im Produkt-Editor
     */
    public function render_workshop_product_tab() {
        global $post, $product_object;
        
        if (!is_a($product_object, 'WC_Product')) {
            $product_object = wc_get_product($post->ID ?? 0);
        }
        
        // Nur für Workshop-Produkte
        if ($product_object->get_type() !== 'workshop') {
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
        
        // Synchronisiere Lagerbestand mit max_teilnehmer
        if (empty($stock_quantity) && !empty($max_teilnehmer)) {
            $stock = max(0, (int)$max_teilnehmer - (int)$current_bookings);
            $product_object->set_stock_quantity($stock);
            $product_object->set_manage_stock(true);
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
     * Alle 8 Felder werden angezeigt, Feld 2/6/7 werden automatisch generiert
     */
    private function render_erwartet_fields($product_id) {
        // Automatisch generierte Werte
        $max_teilnehmer = get_post_meta($product_id, '_workshop_max_teilnehmer', true);
        $ort = get_post_meta($product_id, '_workshop_ort', true);
        $sprache = get_post_meta($product_id, '_workshop_sprache', true);
        
        // Default-Felder für alle 8 Slots
        $default_felder = [
            1 => ['emoji' => '🎨', 'titel' => 'Alle Materialien inklusive', 'text' => 'Du brauchst nichts mitzubringen – alles ist vorbereitet'],
            2 => ['emoji' => '👥', 'titel' => 'Kleine Gruppen', 'text' => $max_teilnehmer ? 'Maximal ' . esc_html($max_teilnehmer) . ' Teilnehmer für individuelle Betreuung' : 'Intensive Betreuung in kleiner Runde'],
            3 => ['emoji' => '🎓', 'titel' => 'Keine Vorkenntnisse nötig', 'text' => 'Ich begleite dich Schritt für Schritt'],
            4 => ['emoji' => '🖼️', 'titel' => 'Dein fertiges Kunstwerk', 'text' => 'Zum Mitnehmen und stolz nach Hause tragen'],
            5 => ['emoji' => '☕', 'titel' => 'Inklusive:', 'text' => 'Kaffee, Tee, Wasser und kleine Leckereien'],
            6 => ['emoji' => '🅿️', 'titel' => 'Parkplätze', 'text' => $ort ? 'Kostenlose Parkplätze vor Ort in ' . esc_html($ort) : 'Parkmöglichkeiten in der Nähe'],
            7 => ['emoji' => '💬', 'titel' => 'Kurssprache', 'text' => $sprache ? 'Der Workshop findet auf ' . esc_html(ucfirst($sprache)) . ' statt' : 'Deutsch'],
            8 => ['emoji' => '🎁', 'titel' => 'Überraschung', 'text' => 'Eine kleine Überraschung wartet auf dich'],
        ];
        
        echo '<h3>' . __('Was dich erwartet', 'micinterart') . '</h3>';
        echo '<p class="description">' . __('Feld 2, 6 und 7 werden automatisch aus deinen Eingaben generiert. Du kannst sie überschreiben.', 'micinterart') . '</p>';
        
        for ($nr = 1; $nr <= 8; $nr++) {
            $emoji = get_post_meta($product_id, "_workshop_erwartet_{$nr}_emoji", true);
            $titel = get_post_meta($product_id, "_workshop_erwartet_{$nr}_titel", true);
            $text = get_post_meta($product_id, "_workshop_erwartet_{$nr}_text", true);
            
            // Falls noch nichts gespeichert ist, Default-Werte verwenden
            if (empty($emoji)) $emoji = $default_felder[$nr]['emoji'];
            if (empty($titel)) $titel = $default_felder[$nr]['titel'];
            if (empty($text)) $text = $default_felder[$nr]['text'];
            
            echo '<div class="options_group" style="border: 1px solid #eee; padding: 15px; margin-bottom: 15px; border-radius: 4px;">';
            echo '<h4 style="margin-top: 0;">Feld ' . $nr . '</h4>';
            
            // Emoji-Feld
            echo '<p class="form-field">';
            echo '<label for="_workshop_erwartet_' . $nr . '_emoji">Emoji</label>';
            echo '<input type="text" class="short" name="_workshop_erwartet_' . $nr . '_emoji" id="_workshop_erwartet_' . $nr . '_emoji" value="' . esc_attr($emoji) . '" placeholder="' . esc_attr($default_felder[$nr]['emoji']) . '" />';
            echo '</p>';
            
            // Titel-Feld
            woocommerce_wp_text_input([
                'id' => "_workshop_erwartet_{$nr}_titel",
                'label' => 'Titel',
                'placeholder' => $default_felder[$nr]['titel'],
                'value' => $titel,
                'desc_tip' => false,
            ]);
            
            // Text-Feld (Textarea für bessere Bearbeitung)
            woocommerce_wp_textarea_input([
                'id' => "_workshop_erwartet_{$nr}_text",
                'label' => 'Beschreibung',
                'placeholder' => $default_felder[$nr]['text'],
                'value' => $text,
                'rows' => 2,
                'desc_tip' => false,
            ]);
            
            echo '</div>';
        }
    }
    
    /**
     * Speichert die Workshop-Felder
     */
    public function save_workshop_product_fields($post_id) {
        // Nur für Workshop-Produkte
        $product = wc_get_product($post_id);
        if (!$product || $product->get_type() !== 'workshop') {
            return;
        }
        
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
     * Workshop-spezifische Validierung: Prüfe ob Lagerbestand > 0
     */
    public function workshop_product_is_purchasable($is_purchasable, $product) {
        if ($product->get_type() === 'workshop') {
            $stock = $product->get_stock_quantity();
            $status = $product->get_meta('_workshop_status', true);
            
            // Nicht kaufbar wenn:
            // - Ausverkauft
            // - Status ist "ausgebucht", "beendet" oder "abgesagt"
            if ($stock <= 0 || in_array($status, ['ausgebucht', 'beendet', 'abgesagt'])) {
                $is_purchasable = false;
            }
        }
        return $is_purchasable;
    }
    
    /**
     * Workshop-spezifische Stock-Logik: Lagerbestand aus Workshop-Feldern
     */
    public function workshop_product_stock_quantity($stock_quantity, $product) {
        if ($product->get_type() === 'workshop') {
            $max_teilnehmer = $product->get_meta('_workshop_max_teilnehmer', true);
            $current_bookings = $product->get_meta('_workshop_current_bookings', true);
            
            if (!empty($max_teilnehmer)) {
                $stock_quantity = max(0, (int)$max_teilnehmer - (int)$current_bookings);
            }
        }
        return $stock_quantity;
    }
}

// Initialisierung - MUSS FRÜH sein, damit der Produkttyp registriert wird
function micinterart_workshop_wc_init() {
    Micinterart_Workshop_WooCommerce::get_instance();
}

// Hilfsfunktion zum Prüfen ob ein Produkt ein Workshop ist
function micinterart_wc_is_workshop_product($product) {
    if (!is_a($product, 'WC_Product')) {
        return false;
    }
    return $product->get_type() === 'workshop';
}
