<?php
/**
 * Werk Migration: Migriert alte Werk-CPTs zu WooCommerce Produkten
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WooCommerce')) {
    return;
}

class Micinterart_Werk_Migration {
    
    private static $instance = null;
    private $dry_run = true;
    private $migration_map = [];
    private $stats = [
        'total' => 0,
        'created' => 0,
        'skipped' => 0,
        'errors' => 0,
    ];
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function set_dry_run($dry_run = true) {
        $this->dry_run = $dry_run;
    }
    
    /**
     * Führt die Migration aus
     */
    public function execute_migration() {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung für Migration');
        }
        
        // Prüfen ob bereits migriert
        $migration_done = get_option('micinterart_werk_migration_done', false);
        if ($migration_done) {
            echo '<p>Werk-Migration wurde bereits durchgeführt.</p>';
            return;
        }
        
        echo '<div style="font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto;">';
        echo '<h1>Werk Migration</h1>';
        echo '<p>' . ($this->dry_run ? '<strong>TESTLAUF - Keine Änderungen werden gespeichert!</strong>' : 'Echte Migration wird durchgeführt.') . '</p>';
        
        // Alte Werke finden
        $alte_werke = get_posts([
            'post_type' => 'werk',
            'posts_per_page' => -1,
            'post_status' => ['publish', 'draft', 'private'],
        ]);
        
        $this->stats['total'] = count($alte_werke);
        
        if (empty($alte_werke)) {
            echo '<p>Keine alten Werke zum Migrieren gefunden.</p>';
            echo '</div>';
            return;
        }
        
        echo '<p>Finde ' . $this->stats['total'] . ' alte Werke...</p>';
        echo '<div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;">';
        
        foreach ($alte_werke as $altes_werk) {
            $this->migrate_werk($altes_werk);
        }
        
        echo '</div>';
        
        // Statistik
        echo '<h2>Zusammenfassung</h2>';
        echo '<ul style="list-style: none; padding: 0;">';
        echo '<li>Gesamt: ' . $this->stats['total'] . '</li>';
        echo '<li>Erstellt: ' . $this->stats['created'] . '</li>';
        echo '<li>Übersprungen: ' . $this->stats['skipped'] . '</li>';
        echo '<li>Fehler: ' . $this->stats['errors'] . '</li>';
        echo '</ul>';
        
        if (!$this->dry_run) {
            // Mapping speichern
            update_option('micinterart_werk_migration_map', $this->migration_map);
            update_option('micinterart_werk_migration_done', true);
            echo '<p><strong>Migration abgeschlossen! Mapping gespeichert.</strong></p>';
        } else {
            echo '<p><strong>TESTLAUF - Keine Daten wurden geändert.</strong></p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Migriert ein einzelnes Werk
     */
    private function migrate_werk($altes_werk) {
        $alt_id = $altes_werk->ID;
        $slug = $altes_werk->post_name;
        
        // Prüfen ob bereits migriert
        if (isset($this->migration_map[$alt_id])) {
            $this->stats['skipped']++;
            return;
        }
        
        try {
            // Neues Produkt erstellen
            $new_product_id = wp_insert_post([
                'post_title' => $altes_werk->post_title,
                'post_content' => $altes_werk->post_content,
                'post_excerpt' => $altes_werk->post_excerpt,
                'post_status' => $altes_werk->post_status,
                'post_name' => $slug,
                'post_type' => 'product',
                'post_author' => $altes_werk->post_author,
                'post_date' => $altes_werk->post_date,
                'post_modified' => $altes_werk->post_modified,
                'menu_order' => $altes_werk->menu_order,
            ]);
            
            if (is_wp_error($new_product_id) || !$new_product_id) {
                throw new Exception('Fehler beim Erstellen des Produkts: ' . ($new_product_id->get_error_message() ?? 'Unbekannter Fehler'));
            }
            
            // Produkttyp auf 'werk' setzen
            wp_set_object_terms($new_product_id, 'werk', 'product_type');
            
            // Featured Image kopieren
            $thumbnail_id = get_post_thumbnail_id($alt_id);
            if ($thumbnail_id) {
                set_post_thumbnail($new_product_id, $thumbnail_id);
            }
            
            // Taxonomien kopieren (z.B. 'serie')
            $this->copy_taxonomies($alt_id, $new_product_id);
            
            // Meta-Daten kopieren
            $this->copy_meta_data($alt_id, $new_product_id);
            
            // Ernzeuge Map
            $this->migration_map[$alt_id] = $new_product_id;
            
            if (!$this->dry_run) {
                // Neu laden, um sicherzustellen, dass alle Daten da sind
                $product = wc_get_product($new_product_id);
                if ($product) {
                    $product->save();
                }
            }
            
            $this->stats['created']++;
            
            echo '<div style="padding: 5px; border-bottom: 1px solid #eee;">';
            echo '<span style="color: green;">✓</span> ' . esc_html($altes_werk->post_title) . ' (ID: ' . $alt_id . ' → ' . $new_product_id . ')';
            echo '</div>';
            
        } catch (Exception $e) {
            $this->stats['errors']++;
            echo '<div style="padding: 5px; border-bottom: 1px solid #eee; color: red;">';
            echo '<span style="color: red;">✗</span> ' . esc_html($altes_werk->post_title) . ': ' . esc_html($e->getMessage());
            echo '</div>';
        }
    }
    
    /**
     * Kopiert Taxonomien
     */
    private function copy_taxonomies($old_id, $new_id) {
        $taxonomies = ['serie'];
        
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($old_id, $taxonomy);
            if (!empty($terms) && !is_wp_error($terms)) {
                $term_ids = wp_list_pluck($terms, 'term_id');
                wp_set_object_terms($new_id, $term_ids, $taxonomy);
            }
        }
    }
    
    /**
     * Kopiert Meta-Daten
     */
    private function copy_meta_data($old_id, $new_id) {
        $meta_keys = [
            '_werk_materials',
            '_werk_dimensions',
            '_werk_year',
            '_werk_represented',
            '_werk_exhibited',
            '_werk_status',
            '_werk_additional_images',
        ];
        
        foreach ($meta_keys as $key) {
            $value = get_post_meta($old_id, $key, true);
            if ($value !== '') {
                update_post_meta($new_id, $key, $value);
            }
        }
        
        // _werk_additional_images ist serialisiert - wir speichern es als Array
        $additional_images = get_post_meta($old_id, '_werk_additional_images', true);
        if (!empty($additional_images)) {
            if (is_string($additional_images)) {
                // Vielleicht comma-separated
                $image_ids = array_map('intval', array_filter(explode(',', $additional_images)));
                update_post_meta($new_id, '_werk_additional_images', $image_ids);
            } elseif (is_array($additional_images)) {
                update_post_meta($new_id, '_werk_additional_images', array_map('intval', $additional_images));
            }
        }
    }
    
    /**
     * Manuelle Migration auslösen
     */
    public static function maybe_run_migration() {
        if (!isset($_GET['micinterart_migrate_werke']) || $_GET['micinterart_migrate_werke'] !== '1') {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung');
        }
        
        $migration = self::get_instance();
        $dry_run = !isset($_GET['dry_run']) || $_GET['dry_run'] !== '0';
        $migration->set_dry_run($dry_run);
        
        if (!$dry_run) {
            if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'micinterart_werk_migration_nonce')) {
                wp_die('Nonce ungültig');
            }
        }
        
        $migration->execute_migration();
        exit;
    }
}

/**
 * Migration-Trigger URL: /wp-admin/?micinterart_migrate_werke=1
 * Für Test: /wp-admin/?micinterart_migrate_werke=1&dry_run=1
 * Für echte Migration: /wp-admin/?micinterart_migrate_werke=1&dry_run=0&nonce=...
 * 
 * HINWEIS: Der Hook wird in functions.php registriert, nicht hier.
 * Das ermöglicht eine bessere Kontrolle über das Laden der Datei.
 */
