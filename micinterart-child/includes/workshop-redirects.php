<?php
/**
 * 301-Weiterleitungen von alten Workshop-URLs zu neuen WC-Produkt-URLs
 * 
 * @package Micinterart
 */

if (!defined('ABSPATH')) {
    exit;
}

class Micinterart_Workshop_Redirects {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Weiterleitungen registrieren
        add_action('init', [$this, 'setup_redirects']);
        
        // Migration Map-Löscher (für Debug)
        // add_action('admin_menu', [$this, 'add_debug_menu']);
    }
    
    /**
     * Richtet die 301-Weiterleitungen ein
     */
    public function setup_redirects() {
        // Nur auf der Frontend-Seite
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        // Migration Map aus der Datenbank holen
        $migration_map = get_option('micinterart_workshop_migration_map', []);
        
        if (empty($migration_map)) {
            return;
        }
        
        // Aktuelle URL abrufen
        $current_url = $this->get_current_url();
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Workshops (CPT)
        if (preg_match('#^/workshops/(.+?)(?:/|$)#', $request_uri, $matches)) {
            $slug = rtrim($matches[1], '/');
            
            // Prüfen ob diese Workshop-ID in der Map ist
            foreach ($migration_map as $old_id => $new_id) {
                $old_post = get_post($old_id);
                if ($old_post && $old_post->post_name === $slug) {
                    $new_url = get_permalink($new_id);
                    if ($new_url) {
                        wp_redirect($new_url, 301);
                        exit;
                    }
                    break;
                }
            }
        }
        
        // Workshop-Themen
        if (preg_match('#^/workshop_thema/(.+?)(?:/|$)#', $request_uri, $matches)) {
            $slug = rtrim($matches[1], '/');
            
            foreach ($migration_map as $old_id => $new_id) {
                $old_post = get_post($old_id);
                if ($old_post && $old_post->post_type === 'workshop_thema' && $old_post->post_name === $slug) {
                    $new_url = get_permalink($new_id);
                    if ($new_url) {
                        wp_redirect($new_url, 301);
                        exit;
                    }
                    break;
                }
            }
        }
        
        // Workshop-Kategorien
        if (preg_match('#^/workshop-kategorie/(.+?)(?:/|$)#', $request_uri, $matches)) {
            $slug = rtrim($matches[1], '/');
            
            // Map Workshop-Kategorien zu WC-Produktkategorien
            $category_map = [
                'atelierkurse' => 'atelierkurse',
                'kinderworkshops' => 'kinderworkshops',
            ];
            
            if (isset($category_map[$slug])) {
                $new_url = home_url('/product-category/' . $category_map[$slug] . '/');
                wp_redirect($new_url, 301);
                exit;
            }
        }
        
        // Workshop-Archiv
        if ($request_uri === '/workshops/' || $request_uri === '/workshops') {
            $workshops_term = get_term_by('slug', 'workshops', 'product_cat');
            if ($workshops_term) {
                $new_url = get_term_link($workshops_term);
                if ($new_url) {
                    wp_redirect($new_url, 301);
                    exit;
                }
            }
        }
    }
    
    /**
     * Gibt die aktuelle URL zurück
     */
    private function get_current_url() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        return $protocol . '://' . $host . $uri;
    }
    
    /**
     * Fügt Debug-Menü hinzu (nur für Admins)
     */
    public function add_debug_menu() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        add_submenu_page(
            'tools.php',
            'Redirect Debug',
            'Redirect Debug',
            'manage_options',
            'micinterart-redirect-debug',
            [$this, 'render_debug_page']
        );
    }
    
    /**
     * Render Debug-Seite
     */
    public function render_debug_page() {
        echo '<div class="wrap">';
        echo '<h1>Redirect Debug</h1>';
        
        $migration_map = get_option('micinterart_workshop_migration_map', []);
        
        echo '<h2>Migration Map</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Old ID</th><th>New ID</th><th>Old Post Type</th><th>Old Title</th><th>New URL</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($migration_map as $old_id => $new_id) {
            $old_post = get_post($old_id);
            $new_post = get_post($new_id);
            
            if ($old_post) {
                echo '<tr>';
                echo '<td>' . esc_html($old_id) . '</td>';
                echo '<td>' . esc_html($new_id) . '</td>';
                echo '<td>' . esc_html($old_post->post_type) . '</td>';
                echo '<td>' . esc_html($old_post->post_title) . '</td>';
                echo '<td><a href="' . esc_url(get_permalink($new_id)) . '" target="_blank">' . esc_html(get_permalink($new_id)) . '</a></td>';
                echo '</tr>';
            }
        }
        
        echo '</tbody></table>';
        echo '</div>';
    }
}

// Initialisierung
function micinterart_workshop_redirects_init() {
    Micinterart_Workshop_Redirects::get_instance();
}

add_action('plugins_loaded', 'micinterart_workshop_redirects_init');
