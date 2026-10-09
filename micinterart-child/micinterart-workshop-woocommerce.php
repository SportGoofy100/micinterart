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
     * Überschreibt den Produkttyp
     */
    public function get_type() {
        return 'workshop';
    }

    // Workshops sind Dienstleistungen: kein Versand
    public function is_virtual() {
        return true;
    }

    public function needs_shipping() {
        return false;
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

        $max_teilnehmer = absint($this->get_meta('_workshop_max_teilnehmer', true));
        if ($max_teilnehmer > 0) {
            $needs_save = false;

            if (!$this->get_manage_stock('edit')) {
                $this->set_manage_stock(true);
                $needs_save = true;
            }

            if ($stock === '' || $stock === null) {
                $current_bookings = absint($this->get_meta('_workshop_current_bookings', true));
                $stock = max(0, $max_teilnehmer - $current_bookings);
                $this->set_stock_quantity($stock);
                $needs_save = true;
            }

            if ($needs_save) {
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
        // Produkttyp registrieren
        add_filter('product_type_selector', [$this, 'add_workshop_product_type']);
        
        // Produktklasse für Workshop-Typ registrieren
        add_filter('woocommerce_product_class', [$this, 'add_workshop_product_class'], 10, 2);
        
        // Felder registrieren
        add_action('init', [$this, 'register_workshop_product_fields']);
        
        // Reiter und Panels für Workshop-Produkte
        add_filter('woocommerce_product_data_tabs', [$this, 'add_workshop_product_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'render_workshop_product_tab']);
        add_action('woocommerce_admin_process_product_object', [$this, 'save_workshop_product_fields']);
        
        // Standard-Felder (Versand, Lager, Preisfelder ...) per JS/Klassen ausblenden
        // (erst nach den Footer-Scripts, damit jQuery sicher geladen ist)
        add_action('admin_print_footer_scripts', [$this, 'output_admin_type_toggle_js'], 100);
        
        // Frontend: Warenkorb-Button wie bei einfachen Produkten
        add_action('woocommerce_workshop_add_to_cart', 'woocommerce_simple_add_to_cart');
        
        // Workshop-spezifische Validierung
        add_filter('woocommerce_is_purchasable', [$this, 'workshop_product_is_purchasable'], 10, 2);
        
        // Aktuelle Buchungen mit WooCommerce-Bestandsänderungen synchronisieren
        add_action('woocommerce_product_set_stock', [$this, 'sync_workshop_bookings_from_stock']);
        add_action('woocommerce_reduce_order_stock', [$this, 'sync_workshop_bookings_for_order'], 20);
        add_action('woocommerce_restore_order_stock', [$this, 'sync_workshop_bookings_for_order'], 20);

        // Restplätze in der Produktübersicht statt eines allgemeinen Lagerstatus anzeigen
        add_filter('woocommerce_admin_stock_html', [$this, 'render_workshop_admin_stock_html'], 10, 2);

        // Bezahlte Workshop-Bestellungen benötigen keine manuelle Bearbeitung oder Versandmail
        add_action('woocommerce_payment_complete', [$this, 'complete_paid_workshop_order'], 20, 2);
        add_filter('woocommerce_email_enabled_customer_completed_order', [$this, 'disable_workshop_completed_email'], 10, 3);
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
    public function add_workshop_product_class($classname, $product_type) {
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
            '_workshop_startzeit',
            '_workshop_uhrzeit_von',
            '_workshop_uhrzeit_bis',
            '_workshop_dauer_stunden',
            '_workshop_ort',
            '_workshop_adresse',
            '_workshop_alter_von',
            '_workshop_alter_bis',
            '_workshop_preis',
            '_workshop_preis_info',
            '_workshop_preis_erwachsener_extra',
            '_workshop_preis_kind_extra',
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
            foreach (['titel', 'text'] as $part) {
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
        // Immer registrieren; Sichtbarkeit steuert WooCommerce per Klasse show_if_workshop.
        // (Nur bei gespeicherten Workshops auszugeben würde beim Umschalten des Dropdowns nichts anzeigen.)
        $tabs['workshop'] = [
            'label'    => __('Workshop-Details', 'micinterart'),
            'target'   => 'workshop_product_data',
            'class'    => ['show_if_workshop'],
            'priority' => 5,
        ];

        // Der Allgemein-Tab ist bei WooCommerce für alle Typen außer "Gruppiert" sichtbar.
        // Ihm keine show_if_*-Klasse geben: WooCommerce versteckt sonst bei anderen Typen
        // (z.B. "Einfaches Produkt") alle show_if_<Typ>-Elemente, die nicht zum Typ passen.
        // Für Workshops irrelevante Tabs ausblenden
        foreach (['shipping', 'linked_product', 'attribute', 'inventory'] as $key) {
            if (isset($tabs[$key])) {
                $tabs[$key]['class'][] = 'hide_if_workshop';
            }
        }

        return $tabs;
    }

    /**
     * Blendet Preis-/Versandfelder aus und zeigt Steuerfelder für den Typ 'workshop'
     */
    public function output_admin_type_toggle_js() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'product') {
            return;
        }
        ?>
        <script>
        jQuery(function($) {
            // Preisfelder von WC ausblenden (Preis kommt aus "Workshop-Details")
            $('#general_product_data .options_group.pricing').addClass('hide_if_workshop');
            // Steuer-Felder für Workshops anzeigen
            $('#general_product_data .options_group').has('#_tax_status, #_tax_class').addClass('show_if_workshop');
            // Typ-Optionen (Virtuell, Herunterladbar, Elektrogerät, Differenzbesteuert,
            // Lebensmittel ...) sind Checkbox-Labels im Kopf der Box, außerhalb der Panels.
            var $typeOptions = $('#woocommerce-product-data label').filter(function() {
                return $(this).find('input[type="checkbox"]').length > 0 &&
                       $(this).closest('.panel, .woocommerce_options_panel').length === 0;
            });
            function toggleTypeOptions() {
                var productType = $('select#product-type').val();
                var isWorkshop = (productType === 'workshop');

                // show_if_workshop / hide_if_workshop (Reiter, Preisfelder) schaltet WooCommerce selbst um.
                // Hier nur die Typ-Optionen; der Werk-Typ regelt sie in seinem eigenen Script.
                if (isWorkshop) {
                    $typeOptions.hide();
                } else if (productType !== 'werk') {
                    $typeOptions.show();
                }

                // Ist der aktive Reiter ausgeblendet worden, den ersten sichtbaren aktivieren,
                // sonst bleibt das Panel leer
                if ($('.product_data_tabs li.active').is(':hidden')) {
                    $('.product_data_tabs li:visible').first().find('a').trigger('click');
                }
            }
            $('select#product-type').on('change', toggleTypeOptions);
            $(document.body).on('woocommerce-product-type-change', toggleTypeOptions);

            $('select#product-type').trigger('change');
        });
        </script>
        <?php
    }
    
    /**
     * Render Workshop-Reiter im Produkt-Editor
     */
    public function render_workshop_product_tab() {
        global $post, $product_object;
        
        $product_id = 0;
        if (is_a($product_object, 'WC_Product')) {
            $product_id = $product_object->get_id();
        } elseif ($post && isset($post->ID)) {
            $product_id = $post->ID;
            $product_object = wc_get_product($post->ID);
        }
        
        // Meta-Werte laden
        $datum = $product_id ? get_post_meta($product_id, '_workshop_datum', true) : '';
        $startzeit = $product_id ? get_post_meta($product_id, '_workshop_startzeit', true) : '';
        $uhrzeit_von = $product_id ? get_post_meta($product_id, '_workshop_uhrzeit_von', true) : '';
        $uhrzeit_bis = $product_id ? get_post_meta($product_id, '_workshop_uhrzeit_bis', true) : '';
        $dauer_stunden = $product_id ? get_post_meta($product_id, '_workshop_dauer_stunden', true) : '';
        $ort = $product_id ? get_post_meta($product_id, '_workshop_ort', true) : '';
        $adresse = $product_id ? get_post_meta($product_id, '_workshop_adresse', true) : '';
        $alter_von = $product_id ? get_post_meta($product_id, '_workshop_alter_von', true) : '';
        $alter_bis = $product_id ? get_post_meta($product_id, '_workshop_alter_bis', true) : '';
        $preis = $product_id ? get_post_meta($product_id, '_workshop_preis', true) : '';
        $preis_info = $product_id ? get_post_meta($product_id, '_workshop_preis_info', true) : '';
        $sprache = $product_id ? get_post_meta($product_id, '_workshop_sprache', true) : '';
        $max_teilnehmer = $product_id ? get_post_meta($product_id, '_workshop_max_teilnehmer', true) : '';
        $is_paar = $product_id ? get_post_meta($product_id, '_workshop_is_paar_preis', true) : '';
        $status = $product_id ? get_post_meta($product_id, '_workshop_status', true) : 'geplant';
        $current_bookings = $product_id ? get_post_meta($product_id, '_workshop_current_bookings', true) : '';
        
        // Lagerbestand aus WC holen
        $stock_quantity = ($product_object && is_a($product_object, 'WC_Product')) ? $product_object->get_stock_quantity() : '';
        
        echo '<div id="workshop_product_data" class="panel woocommerce_options_panel hidden">';
        
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
            'id' => '_workshop_preis',
            'label' => __('Preis', 'micinterart'),
            'placeholder' => '35,00 €',
            'value' => $preis,
        ]);
        woocommerce_wp_text_input([
            'id' => '_workshop_preis_info',
            'label' => __('Preis-Info', 'micinterart'),
            'placeholder' => 'pro Kind / pro Paar',
            'value' => $preis_info,
        ]);
        // Nur für die Kategorie "Familienworkshop": Aufpreise zum Duo-Preis
        woocommerce_wp_text_input([
            'id' => '_workshop_preis_erwachsener_extra',
            'label' => __('Aufpreis je weiterer Erwachsener', 'micinterart'),
            'placeholder' => '25,00',
            'value' => $product_id ? get_post_meta($product_id, '_workshop_preis_erwachsener_extra', true) : '',
            'wrapper_class' => 'mic-familie-field',
            'desc_tip' => true,
            'description' => __('Gilt ab dem 2. Erwachsenen. Der Preis oben ist der Duo-Preis für 1 Erwachsenen und 1 Kind.', 'micinterart'),
        ]);
        woocommerce_wp_text_input([
            'id' => '_workshop_preis_kind_extra',
            'label' => __('Aufpreis je weiteres Kind', 'micinterart'),
            'placeholder' => '15,00',
            'value' => $product_id ? get_post_meta($product_id, '_workshop_preis_kind_extra', true) : '',
            'wrapper_class' => 'mic-familie-field',
            'desc_tip' => true,
            'description' => __('Gilt ab dem 2. Kind.', 'micinterart'),
        ]);
        $familie_term_ids = [];
        $all_cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'lang' => '']);
        if (!is_wp_error($all_cats)) {
            foreach ($all_cats as $cat) {
                if (strpos($cat->slug, 'familienworkshop') === 0) {
                    $familie_term_ids[] = (int) $cat->term_id;
                }
            }
        }
        ?>
        <script>
        jQuery(function($) {
            var ids = <?php echo wp_json_encode($familie_term_ids); ?>;
            function toggleFamilieFields() {
                var active = false;
                $('#product_catchecklist input:checked').each(function() {
                    var id = parseInt($(this).val(), 10);
                    var label = $.trim($(this).parent().text()).toLowerCase();
                    if (ids.indexOf(id) !== -1 || label.indexOf('familienworkshop') === 0) {
                        active = true;
                    }
                });
                $('.mic-familie-field').toggle(active);
            }
            $(document).on('change', '#product_catchecklist input', toggleFamilieFields);
            toggleFamilieFields();
        });
        </script>
        <?php
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
            1 => ['titel' => 'Alle Materialien inklusive', 'text' => 'Du brauchst nichts mitzubringen – alles ist vorbereitet'],
            2 => ['titel' => 'Kleine Gruppen', 'text' => $max_teilnehmer ? 'Maximal ' . esc_html($max_teilnehmer) . ' Teilnehmer für individuelle Betreuung' : 'Intensive Betreuung in kleiner Runde'],
            3 => ['titel' => 'Keine Vorkenntnisse nötig', 'text' => 'Ich begleite dich Schritt für Schritt'],
            4 => ['titel' => 'Dein fertiges Kunstwerk', 'text' => 'Zum Mitnehmen und stolz nach Hause tragen'],
            5 => ['titel' => 'Inklusive:', 'text' => 'Kaffee, Tee, Wasser und kleine Leckereien'],
            6 => ['titel' => 'Parkplätze', 'text' => $ort ? 'Kostenlose Parkplätze vor Ort in ' . esc_html($ort) : 'Parkmöglichkeiten in der Nähe'],
            7 => ['titel' => 'Kurssprache', 'text' => $sprache ? 'Der Workshop findet auf ' . esc_html(ucfirst($sprache)) . ' statt' : 'Deutsch'],
            8 => ['titel' => 'Überraschung', 'text' => 'Eine kleine Überraschung wartet auf dich'],
        ];
        
        echo '<h3>' . __('Was dich erwartet', 'micinterart') . '</h3>';
        echo '<p class="description">' . __('Ein Feld wird auf der Workshop-Seite nur angezeigt, wenn Titel oder Beschreibung ausgefüllt sind. Zum Ausblenden beides leeren und speichern. Felder 2, 6 und 7 werden bei einem neuen Workshop automatisch vorbelegt.', 'micinterart') . '</p>';
        
        for ($nr = 1; $nr <= 8; $nr++) {
            $titel = get_post_meta($product_id, "_workshop_erwartet_{$nr}_titel", true);
            $text = get_post_meta($product_id, "_workshop_erwartet_{$nr}_text", true);
            
            // Nur die automatischen Felder 2, 6 und 7 werden bei einem neuen Workshop vorbelegt.
            // Alle anderen Felder bleiben leer (Standardtext nur als Platzhalter) und erscheinen
            // im Frontend nur, wenn Titel oder Beschreibung ausgefüllt sind.
            if (in_array($nr, [2, 6, 7], true) && !metadata_exists('post', $product_id, "_workshop_erwartet_{$nr}_titel")) {
                if (empty($titel)) $titel = $default_felder[$nr]['titel'];
                if (empty($text)) $text = $default_felder[$nr]['text'];
            }
            
            echo '<div class="options_group" style="border: 1px solid #eee; padding: 15px; margin-bottom: 15px; border-radius: 4px;">';
            echo '<h4 style="margin-top: 0;">Feld ' . $nr . '</h4>';
            
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
    public function save_workshop_product_fields($product) {
        if (!is_a($product, 'WC_Product') || $product->get_type() !== 'workshop') {
            return;
        }

        $post_id = $product->get_id();
        
        $fields = [
            '_workshop_datum',
            '_workshop_startzeit',
            '_workshop_uhrzeit_von',
            '_workshop_uhrzeit_bis',
            '_workshop_dauer_stunden',
            '_workshop_ort',
            '_workshop_adresse',
            '_workshop_alter_von',
            '_workshop_alter_bis',
            '_workshop_preis',
            '_workshop_preis_info',
            '_workshop_preis_erwachsener_extra',
            '_workshop_preis_kind_extra',
            '_workshop_sprache',
            '_workshop_max_teilnehmer',
            '_workshop_current_bookings',
            '_workshop_status',
        ];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $posted_value = wp_unslash($_POST[$field]);
                $value = sanitize_text_field($posted_value);
                // Für numerische Felder
                if (in_array($field, ['_workshop_dauer_stunden', '_workshop_max_teilnehmer', '_workshop_current_bookings'])) {
                    $value = is_numeric($posted_value) ? absint($posted_value) : '';
                }
                $product->update_meta_data($field, $value);
            }
        }
        
        // Checkbox für Paarpreis
        if (isset($_POST['_workshop_is_paar_preis'])) {
            $product->update_meta_data('_workshop_is_paar_preis', 'yes');
        } else {
            $product->delete_meta_data('_workshop_is_paar_preis');
        }
        
        // "Was dich erwartet" Felder
        for ($i = 1; $i <= 8; $i++) {
            foreach (['titel', 'text'] as $part) {
                $field_name = "_workshop_erwartet_{$i}_{$part}";
                if (isset($_POST[$field_name])) {
                    $product->update_meta_data($field_name, sanitize_text_field(wp_unslash($_POST[$field_name])));
                }
            }
        }

        // WooCommerce-Preis aus _workshop_preis übernehmen (Warenkorb nutzt _price)
        if (isset($_POST['_workshop_preis']) && $_POST['_workshop_preis'] !== '') {
            $wc_preis = wc_format_decimal(wp_unslash($_POST['_workshop_preis']));
            $product->set_regular_price($wc_preis);
            $product->set_virtual(true);
        }

        // Synchronisiere max_teilnehmer mit Lagerbestand
        if (isset($_POST['_workshop_max_teilnehmer']) && !empty($_POST['_workshop_max_teilnehmer'])) {
            $max_teilnehmer = absint(wp_unslash($_POST['_workshop_max_teilnehmer']));
            $current_bookings = isset($_POST['_workshop_current_bookings'])
                ? absint(wp_unslash($_POST['_workshop_current_bookings']))
                : absint($product->get_meta('_workshop_current_bookings', true));
            $stock_quantity = max(0, $max_teilnehmer - $current_bookings);

            $product->set_manage_stock(true);
            $product->set_stock_quantity($stock_quantity);
        }
    }
    
    /**
     * Workshop-spezifische Validierung: Prüfe ob Lagerbestand > 0
     */
    public function workshop_product_is_purchasable($is_purchasable, $product) {
        if ($product->get_type() === 'workshop') {
            $stock = $product->get_stock_quantity();
            $status = $product->get_meta('_workshop_status', true) ?: 'geplant';
            $registration_open = in_array($status, ['anmeldung_offen', 'fast_ausgebucht'], true);
            $has_places = $stock === null || $stock === '' || (int) $stock > 0;

            $is_purchasable = $is_purchasable && $registration_open && $has_places;
        }
        return $is_purchasable;
    }
    
    /**
     * Keeps the booking counter aligned with WooCommerce's stock reductions and restores.
     */
    public function sync_workshop_bookings_from_stock($product) {
        if (!is_a($product, 'WC_Product') || $product->get_type() !== 'workshop') {
            return;
        }

        $max_teilnehmer = absint($product->get_meta('_workshop_max_teilnehmer', true));
        $stock_quantity = $product->get_stock_quantity('edit');

        if ($max_teilnehmer <= 0 || $stock_quantity === null || $stock_quantity === '') {
            return;
        }

        $remaining_places = min($max_teilnehmer, max(0, (int) $stock_quantity));
        update_post_meta($product->get_id(), '_workshop_current_bookings', $max_teilnehmer - $remaining_places);

        $status = $product->get_meta('_workshop_status', true);
        if (!in_array($status, ['anmeldung_offen', 'fast_ausgebucht', 'ausgebucht'], true)) {
            return;
        }

        if ($remaining_places === 0) {
            $next_status = 'ausgebucht';
        } elseif ($remaining_places <= 2) {
            $next_status = 'fast_ausgebucht';
        } else {
            $next_status = 'anmeldung_offen';
        }

        if ($status !== $next_status) {
            update_post_meta($product->get_id(), '_workshop_status', $next_status);
        }
    }

    public function sync_workshop_bookings_for_order($order): void {
        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }

        if (!$order instanceof WC_Order) {
            return;
        }

        foreach ($order->get_items('line_item') as $item) {
            $product = $item->get_product();
            if ($product && $product->get_type() === 'workshop') {
                $this->sync_workshop_bookings_from_stock($product);
            }
        }
    }

    public function render_workshop_admin_stock_html($stock_html, $product) {
        if (!is_a($product, 'WC_Product') || $product->get_type() !== 'workshop') {
            return $stock_html;
        }

        $max_teilnehmer = absint($product->get_meta('_workshop_max_teilnehmer', true));
        if ($max_teilnehmer <= 0) {
            return $stock_html;
        }

        $stock_quantity = get_post_meta($product->get_id(), '_stock', true);
        if ($stock_quantity === '') {
            $current_bookings = absint($product->get_meta('_workshop_current_bookings', true));
            $stock_quantity = max(0, $max_teilnehmer - $current_bookings);
        } else {
            $stock_quantity = max(0, (int) $stock_quantity);
        }

        $status_class = $stock_quantity > 0 ? 'instock' : 'outofstock';
        $label = sprintf(
            _n('%d Platz verfügbar', '%d Plätze verfügbar', $stock_quantity, 'micinterart'),
            $stock_quantity
        );

        return '<mark class="' . esc_attr($status_class) . '">' . esc_html($label) . '</mark>';
    }

    public function complete_paid_workshop_order($order_id, $transaction_id = '') {
        $order = wc_get_order($order_id);
        if (!$this->order_contains_only_workshop_products($order) || $order->has_status('completed')) {
            return;
        }

        $order->update_status('completed', __('Workshop-Buchung bezahlt; kein Versand erforderlich.', 'micinterart'));
    }

    public function disable_workshop_completed_email($enabled, $order, $email = null) {
        if ($this->order_contains_only_workshop_products($order)) {
            return false;
        }

        return $enabled;
    }

    private function order_contains_only_workshop_products($order) {
        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }

        if (!$order instanceof WC_Order) {
            return false;
        }

        $items = $order->get_items('line_item');
        if (empty($items)) {
            return false;
        }

        foreach ($items as $item) {
            $product = $item->get_product();
            if (!$product || $product->get_type() !== 'workshop') {
                return false;
            }
        }

        return true;
    }
}

// Die Initialisierung (get_instance) erfolgt in functions.php
// und muss früh genug passieren, damit der Produkttyp registriert wird.

// Hilfsfunktion zum Prüfen ob ein Produkt ein Workshop ist
function micinterart_wc_is_workshop_product($product) {
    if (!is_a($product, 'WC_Product')) {
        return false;
    }
    return $product->get_type() === 'workshop';
}
