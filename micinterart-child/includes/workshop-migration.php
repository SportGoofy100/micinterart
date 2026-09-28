<?php
/**
 * Workshop Migration: Wandelt bestehende Workshops und Themen in WooCommerce-Produkte um
 * 
 * @package Micinterart
 */

if (!defined('ABSPATH')) {
    exit;
}

// Nur ausführen, wenn WooCommerce aktiv ist
if (!class_exists('WooCommerce')) {
    return;
}

class Micinterart_Workshop_Migration {
    
    private static $instance = null;
    
    private $migration_allowed = false;
    private $dry_run = true;
    private $migration_log = [];
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Admin-Menü für Migration hinzufügen
        add_action('admin_menu', [$this, 'add_migration_menu']);
        
        // Admin-Styles
        add_action('admin_enqueue_scripts', [$this, 'admin_styles']);
    }
    
    /**
     * Fügt Migration-Menüpunkt hinzu
     */
    public function add_migration_menu() {
        add_submenu_page(
            'edit.php?post_type=product',
            'Workshop Migration',
            'Workshop Migration',
            'manage_options',
            'micinterart-workshop-migration',
            [$this, 'render_migration_page']
        );
    }
    
    /**
     * Admin-Styles
     */
    public function admin_styles($hook) {
        if ($hook !== 'edit-pages.php' && strpos($hook, 'micinterart-workshop-migration') === false) {
            return;
        }
        
        echo '<style>';
        echo '.mic-migration-log { background: #f9f9f9; padding: 20px; border-radius: 8px; max-height: 400px; overflow-y: auto; }';
        echo '.mic-migration-log entry { margin-bottom: 10px; padding: 5px 10px; border-left: 4px solid #4CAF50; }';
        echo '.mic-migration-log .error { border-left-color: #f44336; }';
        echo '.mic-migration-log .warning { border-left-color: #FF9800; }';
        echo '.mic-migration-progress { margin: 20px 0; }';
        echo '</style>';
    }
    
    /**
     * Render Migration-Seite
     */
    public function render_migration_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Keine Berechtigung', 'micinterart'));
        }
        
        // Prüfen ob Migration bereits ausgeführt wurde
        $migration_done = get_option('micinterart_workshop_migration_done', false);
        
        // Aktionen verarbeiten
        if (isset($_POST['micinterart_migration_action']) && wp_verify_nonce($_POST['micinterart_migration_nonce'], 'micinterart_migration')) {
            $action = sanitize_text_field($_POST['micinterart_migration_action']);
            
            if ($action === 'analyze') {
                $this->analyze_workshops();
            } elseif ($action === 'migrate') {
                $this->dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] === '1';
                $this->execute_migration();
            } elseif ($action === 'reset') {
                $this->reset_migration();
            }
        }
        
        // Statistiken holen
        $stats = $this->get_migration_stats();
        
        echo '<div class="wrap">';
        echo '<h1>Workshop Migration zu WooCommerce</h1>';
        
        // Status-Meldung
        if ($migration_done) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>Migration abgeschlossen!</strong> Die Workshops wurden in WooCommerce-Produkte umgewandelt.</p>';
            echo '</div>';
        }
        
        echo '<p>Dieses Tool wandelt bestehende Workshops und Workshop-Themen in WooCommerce-Produkte um.</p>';
        
        // Übersicht
        echo '<h2>Übersicht</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Typ</th><th>Anzahl</th><th>Beschreibung</th></tr></thead>';
        echo '<tbody>';
        echo '<tr><td>Workshops (CPT)</td><td>' . $stats['workshop_count'] . '</td><td>Wird in Produkte umgewandelt</td></tr>';
        echo '<tr><td>Workshop-Themen (CPT)</td><td>' . $stats['thema_count'] . '</td><td>Wird in einzelne Produkte umgewandelt</td></tr>';
        echo '<tr><td>Workshops mit Datum</td><td>' . $stats['workshop_with_date'] . '</td><td>Kann direkt migriert werden</td></tr>';
        echo '<tr><td>Workshops mit Themen</td><td>' . $stats['workshop_with_themen'] . '</td><td>Jedes Thema wird ein Produkt</td></tr>';
        echo '<tr><td>Existierende WC-Produkte in Workshops-Kategorie</td><td>' . $stats['existing_wc_products'] . '</td><td>Schon vorhanden</td></tr>';
        echo '</tbody></table>';
        
        // Analyse-Button
        echo '<h2>Analyse</h2>';
        echo '<form method="post">';
        wp_nonce_field('micinterart_migration', 'micinterart_migration_nonce');
        echo '<input type="hidden" name="micinterart_migration_action" value="analyze">';
        echo '<p><input type="submit" class="button button-primary" value="Workshops analysieren"></p>';
        echo '</form>';
        
        // Migrations-Button
        echo '<h2>Migration ausführen</h2>';
        echo '<div class="notice notice-warning inline">';
        echo '<p><strong>Achtung!</strong> Dies ist eine einmalige Aktion. Bitte erstelle ein Backup der Datenbank bevor du fortfährst.</p>';
        echo '</div>';
        
        echo '<form method="post">';
        wp_nonce_field('micinterart_migration', 'micinterart_migration_nonce');
        echo '<input type="hidden" name="micinterart_migration_action" value="migrate">';
        echo '<p>';
        echo '<label><input type="checkbox" name="dry_run" value="1" checked> Testlauf (keine Änderungen)</label><br>';
        echo '<small>Im Testlauf werden keine Änderungen an der Datenbank vorgenommen.</small>';
        echo '</p>';
        echo '<p><input type="submit" class="button button-primary" value="Migration starten"></p>';
        echo '</form>';
        
        // Migration zurücksetzen
        if ($migration_done) {
            echo '<h2>Migration zurücksetzen</h2>';
            echo '<form method="post" onsubmit="return confirm(\'Möchtest du die Migration wirklich zurücksetzen?\');">';
            wp_nonce_field('micinterart_migration', 'micinterart_migration_nonce');
            echo '<input type="hidden" name="micinterart_migration_action" value="reset">';
            echo '<p><input type="submit" class="button button-secondary" value="Migration zurücksetzen"></p>';
            echo '</form>';
        }
        
        // Log anzeigen
        if (!empty($this->migration_log)) {
            echo '<h2>Protokoll</h2>';
            echo '<div class="mic-migration-log">';
            foreach ($this->migration_log as $entry) {
                $class = isset($entry['type']) ? $entry['type'] : 'info';
                echo '<div class="' . esc_attr($class) . '">' . esc_html($entry['message']) . '</div>';
            }
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Gibt Statistiken zur Migration zurück
     */
    private function get_migration_stats() {
        $stats = [
            'workshop_count' => 0,
            'thema_count' => 0,
            'workshop_with_date' => 0,
            'workshop_with_themen' => 0,
            'existing_wc_products' => 0,
        ];
        
        // Workshops zählen
        $workshops = get_posts([
            'post_type' => 'workshop',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);
        $stats['workshop_count'] = count($workshops);
        
        // Themen zählen
        $themen = get_posts([
            'post_type' => 'workshop_thema',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);
        $stats['thema_count'] = count($themen);
        
        // Workshops mit Datum
        foreach ($workshops as $workshop) {
            $datum = get_post_meta($workshop->ID, '_workshop_datum', true);
            if (!empty($datum)) {
                $stats['workshop_with_date']++;
            }
        }
        
        // Workshops mit Themen
        foreach ($workshops as $workshop) {
            $themen = get_posts([
                'post_type' => 'workshop_thema',
                'posts_per_page' => 1,
                'post_status' => 'publish',
                'meta_query' => [
                    ['key' => '_thema_workshop_id', 'value' => $workshop->ID, 'compare' => '='],
                ],
            ]);
            if (!empty($themen)) {
                $stats['workshop_with_themen']++;
            }
        }
        
        // Existierende WC-Produkte in Workshops-Kategorie
        $workshops_cat = get_term_by('slug', 'workshops', 'product_cat');
        if ($workshops_cat) {
            $products = get_posts([
                'post_type' => 'product',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'tax_query' => [
                    ['taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $workshops_cat->term_id],
                ],
            ]);
            $stats['existing_wc_products'] = count($products);
        }
        
        return $stats;
    }
    
    /**
     * Analysiert die Workshops und zeigt Details an
     */
    private function analyze_workshops() {
        $workshops = get_posts([
            'post_type' => 'workshop',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);
        
        echo '<div class="wrap">';
        echo '<h2>Workshop-Analyse</h2>';
        
        foreach ($workshops as $workshop) {
            $workshop_id = $workshop->ID;
            $datum = get_post_meta($workshop_id, '_workshop_datum', true);
            $preis = get_post_meta($workshop_id, '_workshop_preis', true);
            $max_teilnehmer = get_post_meta($workshop_id, '_workshop_max_teilnehmer', true);
            $current_bookings = get_post_meta($workshop_id, '_workshop_current_bookings', true);
            $status = get_post_meta($workshop_id, '_workshop_status', true);
            
            // Kategorie
            $categories = get_the_terms($workshop_id, 'workshop_kategorie');
            $category_name = '';
            if ($categories && !is_wp_error($categories)) {
                $category_name = $categories[0]->name;
            }
            
            // Themen zählen
            $themen = get_posts([
                'post_type' => 'workshop_thema',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'meta_query' => [
                    ['key' => '_thema_workshop_id', 'value' => $workshop_id, 'compare' => '='],
                ],
            ]);
            
            echo '<h3>' . esc_html($workshop->post_title) . '</h3>';
            echo '<ul>';
            echo '<li><strong>ID:</strong> ' . $workshop_id . '</li>';
            echo '<li><strong>Kategorie:</strong> ' . esc_html($category_name) . '</li>';
            echo '<li><strong>Datum:</strong> ' . esc_html($datum) . '</li>';
            echo '<li><strong>Preis:</strong> ' . esc_html($preis) . '</li>';
            echo '<li><strong>Max. Teilnehmer:</strong> ' . esc_html($max_teilnehmer) . '</li>';
            echo '<li><strong>Aktuelle Buchungen:</strong> ' . esc_html($current_bookings) . '</li>';
            echo '<li><strong>Status:</strong> ' . esc_html($status) . '</li>';
            echo '<li><strong>Themen:</strong> ' . count($themen) . '</li>';
            
            if (!empty($themen)) {
                echo '<ul style="margin-left: 20px;">';
                foreach ($themen as $thema) {
                    $thema_datum = get_post_meta($thema->ID, '_thema_datum', true);
                    $thema_preis = get_post_meta($thema->ID, '_thema_preis', true);
                    $thema_max = get_post_meta($thema->ID, '_thema_max_teilnehmer', true);
                    $thema_current = get_post_meta($thema->ID, '_thema_current_bookings', true);
                    
                    echo '<li>' . esc_html($thema->post_title) . ' (Datum: ' . esc_html($thema_datum) . ', Preis: ' . esc_html($thema_preis) . ', Plätze: ' . esc_html($thema_max - $thema_current) . ' frei)</li>';
                }
                echo '</ul>';
            }
            echo '</ul>';
            echo '<hr>';
        }
        echo '</div>';
    }
    
    /**
     * Setzt die Migration zurück
     */
    private function reset_migration() {
        delete_option('micinterart_workshop_migration_done');
        delete_option('micinterart_workshop_migration_map');
        
        $this->migration_log[] = ['type' => 'success', 'message' => 'Migration wurde zurückgesetzt.'];
    }
    
    /**
     * Führt die Migration aus
     */
    private function execute_migration() {
        $this->migration_log = [];
        
        // 1. Workshops-Kategorien sicherstellen
        $this->log('Starte Migration...');
        $this->ensure_categories();
        
        // 2. Mapping-Array initialisieren
        $migration_map = [];
        
        // 3. Zuerst Workshops OHNE Themen migrieren (als einzelne Produkte)
        $this->log('Migriere Workshops ohne Themen...');
        $simple_workshops = $this->get_workshops_without_themen();
        
        foreach ($simple_workshops as $workshop) {
            $wc_product_id = $this->migrate_simple_workshop($workshop);
            if ($wc_product_id) {
                $migration_map[$workshop->ID] = $wc_product_id;
            }
        }
        
        // 4. Dann Workshops MIT Themen migrieren (jeder Termin = ein Produkt)
        $this->log('Migriere Workshops mit Themen...');
        $workshops_with_themen = $this->get_workshops_with_themen();
        
        foreach ($workshops_with_themen as $workshop) {
            $themen = $this->get_workshop_themen($workshop->ID);
            
            foreach ($themen as $thema) {
                $wc_product_id = $this->migrate_workshop_thema($workshop, $thema);
                if ($wc_product_id) {
                    $migration_map[$thema->ID] = $wc_product_id;
                    // Auch den Workshop selbst mappen
                    if (!isset($migration_map[$workshop->ID])) {
                        $migration_map[$workshop->ID] = $wc_product_id;
                    }
                }
            }
        }
        
        // 5. Mapping speichern für 301-Weiterleitungen
        if (!$this->dry_run) {
            update_option('micinterart_workshop_migration_map', $migration_map);
            update_option('micinterart_workshop_migration_done', true);
        }
        
        $this->log('Migration abgeschlossen!' . ($this->dry_run ? ' (Testlauf)' : ''));
        
        // Statistik ausgeben
        $this->log('Erstellte Produkte: ' . count($migration_map));
    }
    
    /**
     * Fügt eine Log-Nachricht hinzu
     */
    private function log($message, $type = 'info') {
        $this->migration_log[] = [
            'type' => $type,
            'message' => $message,
        ];
    }
    
    /**
     * Stellt sicher dass die Kategorien existieren
     */
    private function ensure_categories() {
        $categories = [
            ['name' => 'Workshops', 'slug' => 'workshops', 'parent' => 0],
            ['name' => 'Atelierkurse', 'slug' => 'atelierkurse', 'parent' => 'workshops'],
            ['name' => 'Kinderworkshops', 'slug' => 'kinderworkshops', 'parent' => 'workshops'],
        ];
        
        foreach ($categories as $cat) {
            $parent_id = 0;
            if ($cat['parent'] !== 0) {
                $parent_term = get_term_by('slug', $cat['parent'], 'product_cat');
                if ($parent_term) {
                    $parent_id = $parent_term->term_id;
                }
            }
            
            $term = get_term_by('slug', $cat['slug'], 'product_cat');
            if (!$term) {
                $result = wp_insert_term($cat['name'], 'product_cat', [
                    'slug' => $cat['slug'],
                    'parent' => $parent_id,
                ]);
                
                if (is_wp_error($result)) {
                    $this->log('Fehler beim Erstellen der Kategorie: ' . $cat['name'] . ' - ' . $result->get_error_message(), 'error');
                } else {
                    $this->log('Kategorie erstellt: ' . $cat['name']);
                }
            }
        }
    }
    
    /**
     * Gibt Workshops OHNE Themen zurück
     */
    private function get_workshops_without_themen() {
        $workshops = get_posts([
            'post_type' => 'workshop',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);
        
        $workshops_without_themen = [];
        
        foreach ($workshops as $workshop) {
            $themen = get_posts([
                'post_type' => 'workshop_thema',
                'posts_per_page' => 1,
                'post_status' => 'publish',
                'meta_query' => [
                    ['key' => '_thema_workshop_id', 'value' => $workshop->ID, 'compare' => '='],
                ],
            ]);
            
            if (empty($themen)) {
                $workshops_without_themen[] = $workshop;
            }
        }
        
        return $workshops_without_themen;
    }
    
    /**
     * Gibt Workshops MIT Themen zurück
     */
    private function get_workshops_with_themen() {
        $workshops = get_posts([
            'post_type' => 'workshop',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);
        
        $workshops_with_themen = [];
        
        foreach ($workshops as $workshop) {
            $themen = get_posts([
                'post_type' => 'workshop_thema',
                'posts_per_page' => 1,
                'post_status' => 'publish',
                'meta_query' => [
                    ['key' => '_thema_workshop_id', 'value' => $workshop->ID, 'compare' => '='],
                ],
            ]);
            
            if (!empty($themen)) {
                $workshops_with_themen[] = $workshop;
            }
        }
        
        return $workshops_with_themen;
    }
    
    /**
     * Gibt alle Themen eines Workshops zurück
     */
    private function get_workshop_themen($workshop_id) {
        return get_posts([
            'post_type' => 'workshop_thema',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                ['key' => '_thema_workshop_id', 'value' => $workshop_id, 'compare' => '='],
            ],
            'orderby' => 'meta_value',
            'meta_key' => '_thema_datum',
            'order' => 'ASC',
        ]);
    }
    
    /**
     * Migriert einen einfachen Workshop (ohne Themen) in ein WC-Produkt
     */
    private function migrate_simple_workshop($workshop) {
        $workshop_id = $workshop->ID;
        
        // Meta-Daten holen
        $datum = get_post_meta($workshop_id, '_workshop_datum', true);
        $preis = get_post_meta($workshop_id, '_workshop_preis', true);
        $preis_info = get_post_meta($workshop_id, '_workshop_preis_info', true);
        $max_teilnehmer = get_post_meta($workshop_id, '_workshop_max_teilnehmer', true);
        $current_bookings = get_post_meta($workshop_id, '_workshop_current_bookings', true);
        $status = get_post_meta($workshop_id, '_workshop_status', true);
        $ort = get_post_meta($workshop_id, '_workshop_ort', true);
        $adresse = get_post_meta($workshop_id, '_workshop_adresse', true);
        $startzeit = get_post_meta($workshop_id, '_workshop_startzeit', true);
        $dauer = get_post_meta($workshop_id, '_workshop_dauer_stunden', true);
        $uhrzeit_von = get_post_meta($workshop_id, '_workshop_uhrzeit_von', true);
        $uhrzeit_bis = get_post_meta($workshop_id, '_workshop_uhrzeit_bis', true);
        $alter_von = get_post_meta($workshop_id, '_workshop_alter_von', true);
        $alter_bis = get_post_meta($workshop_id, '_workshop_alter_bis', true);
        $sprache = get_post_meta($workshop_id, '_workshop_sprache', true);
        $is_paar = get_post_meta($workshop_id, '_workshop_is_paar_preis', true);
        
        // Kategorie
        $categories = get_the_terms($workshop_id, 'workshop_kategorie');
        $category_name = '';
        if ($categories && !is_wp_error($categories)) {
            $category_name = $categories[0]->slug;
        }
        
        // Titel für Produkt anpassen
        $datum_obj = $datum ? date_create($datum) : null;
        $datum_formatted = $datum_obj ? $datum_obj->format('d.m.Y') : '';
        
        $product_title = $workshop->post_title;
        if ($datum_formatted) {
            $product_title = $datum_formatted . ' - ' . $product_title;
        }
        
        // Produkt-Daten
        $product_data = [
            'post_title' => $product_title,
            'post_content' => $workshop->post_content,
            'post_excerpt' => $workshop->post_excerpt,
            'post_status' => 'publish',
            'post_type' => 'product',
            'post_name' => sanitize_title($product_title),
        ];
        
        if ($this->dry_run) {
            $this->log('Would create product: ' . $product_title);
            return false;
        }
        
        // Produkt erstellen
        $product_id = wp_insert_post($product_data);
        
        if (is_wp_error($product_id)) {
            $this->log('Fehler beim Erstellen des Produkts für Workshop #' . $workshop_id . ': ' . $product_id->get_error_message(), 'error');
            return false;
        }
        
        // Produkt als einfaches Produkt festlegen
        wp_set_object_terms($product_id, 'simple', 'product_type');
        
        // Kategorie zuweisen
        $wc_category = $category_name === 'kinderworkshops' ? 'kinderworkshops' : 'atelierkurse';
        $category_term = get_term_by('slug', $wc_category, 'product_cat');
        if ($category_term) {
            wp_set_object_terms($product_id, [$category_term->term_id], 'product_cat');
        } else {
            // Fallback zu "workshops"
            $workshops_term = get_term_by('slug', 'workshops', 'product_cat');
            if ($workshops_term) {
                wp_set_object_terms($product_id, [$workshops_term->term_id], 'product_cat');
            }
        }
        
        // WC-Produkt-Objekt erstellen
        $product = wc_get_product($product_id);
        if (!$product) {
            $product = new WC_Product_Simple($product_id);
        }
        
        // Preis setzen
        if ($preis) {
            $preis_clean = preg_replace('/[^0-9.]/', '', str_replace(',', '.', $preis));
            $product->set_price($preis_clean);
            $product->set_regular_price($preis_clean);
        }
        
        // Lagerbestand
        $stock_quantity = 0;
        if ($max_teilnehmer && $current_bookings) {
            $stock_quantity = max(0, (int)$max_teilnehmer - (int)$current_bookings);
        } elseif ($max_teilnehmer) {
            $stock_quantity = (int)$max_teilnehmer;
        }
        
        $product->set_manage_stock(true);
        $product->set_stock_quantity($stock_quantity);
        $product->set_stock_status($stock_quantity > 0 ? 'instock' : 'outofstock');
        
        // Virtuelles Produkt (kein Versand)
        $product->set_virtual(true);
        
        // Downloadable? Nein
        $product->set_downloadable(false);
        
        // Bild übetragen
        $thumbnail_id = get_post_thumbnail_id($workshop_id);
        if ($thumbnail_id) {
            set_post_thumbnail($product_id, $thumbnail_id);
        }
        
        // Meta-Felder übertragen
        $this->transfer_workshop_meta($product_id, $workshop_id);
        
        // Speichern
        $product->save();
        
        $this->log('Produkt erstellt: ' . $product_title . ' (ID: ' . $product_id . ') aus Workshop #' . $workshop_id);
        
        return $product_id;
    }
    
    /**
     * Migriert ein Workshop-Thema in ein WC-Produkt
     */
    private function migrate_workshop_thema($workshop, $thema) {
        $workshop_id = $workshop->ID;
        $thema_id = $thema->ID;
        
        // Meta-Daten aus Thema und Workshop holen
        $thema_datum = get_post_meta($thema_id, '_thema_datum', true);
        $thema_preis = get_post_meta($thema_id, '_thema_preis', true);
        $thema_max = get_post_meta($thema_id, '_thema_max_teilnehmer', true);
        $thema_current = get_post_meta($thema_id, '_thema_current_bookings', true);
        $thema_ort = get_post_meta($thema_id, '_thema_ort', true);
        $thema_uhrzeit_von = get_post_meta($thema_id, '_thema_uhrzeit_von', true);
        $thema_uhrzeit_bis = get_post_meta($thema_id, '_thema_uhrzeit_bis', true);
        
        // Fallbacks aus Workshop
        $preis = $thema_preis ?: get_post_meta($workshop_id, '_workshop_preis', true);
        $max_teilnehmer = $thema_max ?: get_post_meta($workshop_id, '_workshop_max_teilnehmer', true);
        $current_bookings = $thema_current ?: get_post_meta($workshop_id, '_workshop_current_bookings', true);
        $ort = $thema_ort ?: get_post_meta($workshop_id, '_workshop_ort', true);
        $uhrzeit_von = $thema_uhrzeit_von ?: get_post_meta($workshop_id, '_workshop_uhrzeit_von', true);
        $uhrzeit_bis = $thema_uhrzeit_bis ?: get_post_meta($workshop_id, '_workshop_uhrzeit_bis', true);
        $status = get_post_meta($workshop_id, '_workshop_status', true);
        
        // Kategorie
        $categories = get_the_terms($workshop_id, 'workshop_kategorie');
        $category_name = '';
        if ($categories && !is_wp_error($categories)) {
            $category_name = $categories[0]->slug;
        }
        
        // Titel für Produkt
        $datum_obj = $thema_datum ? date_create($thema_datum) : null;
        $datum_formatted = $datum_obj ? $datum_obj->format('d.m.Y') : '';
        
        $product_title = $workshop->post_title;
        if ($thema->post_title !== $workshop->post_title) {
            $product_title = $thema->post_title;
        }
        if ($datum_formatted) {
            $product_title = $datum_formatted . ' - ' . $product_title;
        }
        
        // Produkt-Daten
        $product_data = [
            'post_title' => $product_title,
            'post_content' => $thema->post_content ?: $workshop->post_content,
            'post_excerpt' => $thema->post_excerpt ?: $workshop->post_excerpt,
            'post_status' => 'publish',
            'post_type' => 'product',
            'post_name' => sanitize_title($product_title),
        ];
        
        if ($this->dry_run) {
            $this->log('Would create product from theme: ' . $product_title);
            return false;
        }
        
        // Produkt erstellen
        $product_id = wp_insert_post($product_data);
        
        if (is_wp_error($product_id)) {
            $this->log('Fehler beim Erstellen des Produkts für Thema #' . $thema_id . ': ' . $product_id->get_error_message(), 'error');
            return false;
        }
        
        // Produkt als einfaches Produkt festlegen
        wp_set_object_terms($product_id, 'simple', 'product_type');
        
        // Kategorie zuweisen
        $wc_category = $category_name === 'kinderworkshops' ? 'kinderworkshops' : 'atelierkurse';
        $category_term = get_term_by('slug', $wc_category, 'product_cat');
        if ($category_term) {
            wp_set_object_terms($product_id, [$category_term->term_id], 'product_cat');
        } else {
            // Fallback zu "workshops"
            $workshops_term = get_term_by('slug', 'workshops', 'product_cat');
            if ($workshops_term) {
                wp_set_object_terms($product_id, [$workshops_term->term_id], 'product_cat');
            }
        }
        
        // WC-Produkt-Objekt
        $product = wc_get_product($product_id);
        if (!$product) {
            $product = new WC_Product_Simple($product_id);
        }
        
        // Preis setzen
        if ($preis) {
            $preis_clean = preg_replace('/[^0-9.]/', '', str_replace(',', '.', $preis));
            $product->set_price($preis_clean);
            $product->set_regular_price($preis_clean);
        }
        
        // Lagerbestand
        $stock_quantity = 0;
        if ($max_teilnehmer && $current_bookings) {
            $stock_quantity = max(0, (int)$max_teilnehmer - (int)$current_bookings);
        } elseif ($max_teilnehmer) {
            $stock_quantity = (int)$max_teilnehmer;
        }
        
        $product->set_manage_stock(true);
        $product->set_stock_quantity($stock_quantity);
        $product->set_stock_status($stock_quantity > 0 ? 'instock' : 'outofstock');
        
        // Virtuelles Produkt
        $product->set_virtual(true);
        $product->set_downloadable(false);
        
        // Bild übetragen (erst Thema, dann Workshop)
        $thumbnail_id = get_post_thumbnail_id($thema_id);
        if (!$thumbnail_id) {
            $thumbnail_id = get_post_thumbnail_id($workshop_id);
        }
        if ($thumbnail_id) {
            set_post_thumbnail($product_id, $thumbnail_id);
        }
        
        // Meta-Felder übertragen (aus Thema und Workshop)
        $this->transfer_thema_meta($product_id, $workshop, $thema);
        
        // Speichern
        $product->save();
        
        $this->log('Produkt aus Thema erstellt: ' . $product_title . ' (ID: ' . $product_id . ') aus Workshop #' . $workshop_id . ' Thema #' . $thema_id);
        
        return $product_id;
    }
    
    /**
     * Übertragt Meta-Felder von Workshop zu Produkt
     */
    private function transfer_workshop_meta($product_id, $workshop_id) {
        $fields = [
            '_workshop_datum',
            '_workshop_uhrzeit_von',
            '_workshop_uhrzeit_bis',
            '_workshop_startzeit',
            '_workshop_dauer_stunden',
            '_workshop_ort',
            '_workshop_adresse',
            '_workshop_alter_von',
            '_workshop_alter_bis',
            '_workshop_preis',
            '_workshop_preis_info',
            '_workshop_sprache',
            '_workshop_max_teilnehmer',
            '_workshop_current_bookings',
            '_workshop_status',
            '_workshop_is_paar_preis',
        ];
        
        foreach ($fields as $field) {
            $value = get_post_meta($workshop_id, $field, true);
            if (!empty($value)) {
                update_post_meta($product_id, $field, $value);
            }
        }
        
        // "Was dich erwartet" Felder
        for ($i = 1; $i <= 8; $i++) {
            foreach (['emoji', 'titel', 'text'] as $part) {
                $field = "_workshop_erwartet_{$i}_{$part}";
                $value = get_post_meta($workshop_id, $field, true);
                if (!empty($value)) {
                    update_post_meta($product_id, $field, $value);
                }
            }
        }
        
        // Speichern, dass dies ein migriertes Produkt ist
        update_post_meta($product_id, '_micinterart_migrated_from_workshop', $workshop_id);
    }
    
    /**
     * Übertragt Meta-Felder von Thema und Workshop zu Produkt
     */
    private function transfer_thema_meta($product_id, $workshop, $thema) {
        $workshop_id = $workshop->ID;
        $thema_id = $thema->ID;
        
        // Felder aus Thema (Priorität)
        $fields = [
            '_workshop_datum' => '_thema_datum',
            '_workshop_uhrzeit_von' => '_thema_uhrzeit_von',
            '_workshop_uhrzeit_bis' => '_thema_uhrzeit_bis',
            '_workshop_ort' => '_thema_ort',
            '_workshop_preis' => '_thema_preis',
            '_workshop_max_teilnehmer' => '_thema_max_teilnehmer',
            '_workshop_current_bookings' => '_thema_current_bookings',
        ];
        
        foreach ($fields as $target_field => $source_field) {
            $value = get_post_meta($thema_id, $source_field, true);
            if (!empty($value)) {
                update_post_meta($product_id, $target_field, $value);
            }
        }
        
        // Felder aus Workshop (Fallback)
        $workshop_fields = [
            '_workshop_adresse',
            '_workshop_alter_von',
            '_workshop_alter_bis',
            '_workshop_preis_info',
            '_workshop_sprache',
            '_workshop_status',
            '_workshop_is_paar_preis',
            '_workshop_startzeit',
            '_workshop_dauer_stunden',
        ];
        
        foreach ($workshop_fields as $field) {
            $value = get_post_meta($workshop_id, $field, true);
            if (!empty($value) && !metadata_exists('post', $product_id, $field)) {
                update_post_meta($product_id, $field, $value);
            }
        }
        
        // "Was dich erwartet" Felder aus Thema, dann Workshop
        for ($i = 1; $i <= 8; $i++) {
            foreach (['emoji', 'titel', 'text'] as $part) {
                $field = "_workshop_erwartet_{$i}_{$part}";
                $value = get_post_meta($thema_id, $field, true);
                if (!empty($value)) {
                    update_post_meta($product_id, $field, $value);
                } else {
                    $value = get_post_meta($workshop_id, $field, true);
                    if (!empty($value)) {
                        update_post_meta($product_id, $field, $value);
                    }
                }
            }
        }
        
        // Speichern, dass dies aus einem Thema migriert wurde
        update_post_meta($product_id, '_micinterart_migrated_from_workshop', $workshop_id);
        update_post_meta($product_id, '_micinterart_migrated_from_thema', $thema_id);
    }
}

// Initialisierung
function micinterart_workshop_migration_init() {
    if (class_exists('WooCommerce')) {
        Micinterart_Workshop_Migration::get_instance();
    }
}

add_action('admin_init', 'micinterart_workshop_migration_init');
