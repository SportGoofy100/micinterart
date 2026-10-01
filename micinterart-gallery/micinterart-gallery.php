<?php
/**
 * Plugin Name: Micinterart Gallery
 * Description: Gedichte mit Verknüpfung zu WooCommerce-Werkprodukten und Serien
 * Version: 2.5.0
 * Author: Urs
 * Text Domain: micinterart
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// =========================================================================
// HAUPTKLASSE
// =========================================================================

class MicinterartGallery {

    private const VERSION = '2.5.0';
    private const PAGE_TRANSLATION_FLAG = '_micinterart_page_translation_initialized';
    private const GEDICHT_TRANSLATION_FLAG = '_micinterart_gedicht_translation_initialized';
    private static $instance = null;
    /** Verhindert erneute Synchronisierungen, die durch wp_update_post ausgelöst werden. */
    private $translation_sync_in_progress = [];
    /** Wird von translate_text() gesetzt und zeigt an, ob DeepL geantwortet hat. */
    private $last_translation_succeeded = true;

    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks(): void {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('init', [$this, 'register_post_types']);
        add_action('init', [$this, 'register_taxonomies']);
        add_action('add_meta_boxes', [$this, 'add_metaboxes']);
        add_action('admin_menu', [$this, 'remove_legacy_cpt_admin_menus'], 999);
        add_action('save_post_gedicht', [$this, 'save_gedicht_meta']);
        add_action('save_post_gedicht', [$this, 'save_gedicht_relation_meta']);
        add_action('save_post_gedicht', [$this, 'sync_gedicht_on_save'], 20);
        add_action('pll_save_post_translations', [$this, 'sync_gedicht_on_polylang_save'], 10, 2);
        add_action('pll_save_post_translations', [$this, 'sync_page_on_polylang_save'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_menu', [$this, 'add_plugin_settings_menu']);
        add_action('admin_init', [$this, 'register_plugin_settings']);
        add_filter('page_row_actions', [$this, 'add_retranslate_row_action'], 10, 2);
        add_filter('post_row_actions', [$this, 'add_retranslate_row_action'], 10, 2);
        add_action('admin_post_micinterart_retranslate', [$this, 'handle_retranslate_request']);
        add_action('admin_notices', [$this, 'render_retranslate_notice']);
        
        add_action('woocommerce_single_product_summary', [$this, 'render_related_poems_for_werk_product'], 45);
    }

    public function activate(): void {
        $this->register_post_types();
        $this->register_taxonomies();
        flush_rewrite_rules();
    }

    public function deactivate(): void {
        flush_rewrite_rules();
    }

    public function remove_legacy_cpt_admin_menus(): void {
        foreach (['edit.php?post_type=werk', 'edit.php?post_type=workshop', 'edit.php?post_type=workshop_thema'] as $menu_slug) {
            remove_menu_page($menu_slug);
        }
    }

    public function register_post_types(): void {
        register_post_type('werk', [
            'labels' => ['name' => 'Legacy-Werke', 'singular_name' => 'Legacy-Werk'],
            'public' => true,
            'show_ui' => false,
            'show_in_menu' => false,
            'show_in_admin_bar' => false,
            'exclude_from_search' => true,
            'has_archive' => true,
            'supports' => [],
            'show_in_rest' => false,
            'rewrite' => ['slug' => 'galerie'],
        ]);

        register_post_type('gedicht', [
            'labels' => ['name' => 'Gedichte', 'singular_name' => 'Gedicht', 'add_new_item' => 'Neues Gedicht hinzufügen'],
            'public' => true, 'has_archive' => true, 'menu_icon' => 'dashicons-editor-quote', 'supports' => ['title', 'editor', 'thumbnail'], 'show_in_rest' => true, 'rewrite' => ['slug' => 'lyrik'],
        ]);

        register_post_type('workshop', [
            'labels' => ['name' => 'Legacy-Workshops', 'singular_name' => 'Legacy-Workshop'],
            'public' => true,
            'show_ui' => false,
            'show_in_menu' => false,
            'show_in_admin_bar' => false,
            'exclude_from_search' => true,
            'has_archive' => true,
            'supports' => [],
            'show_in_rest' => false,
            'rewrite' => ['slug' => 'workshops'],
        ]);
    }

    public function register_taxonomies(): void {
        register_taxonomy('serie', ['product'], [
            'labels' => [
                'name' => 'Serien',
                'singular_name' => 'Serie',
            ],
            'hierarchical' => true,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'rewrite' => ['slug' => 'serie'],
        ]);
    }

    public function add_metaboxes(): void {
        add_meta_box('gedicht_details', 'Gedicht-Informationen', [$this, 'render_gedicht_metabox'], 'gedicht', 'normal', 'high');
        add_meta_box('gedicht_relation', 'Zugeordnetes Werk', [$this, 'render_gedicht_relation_metabox'], 'gedicht', 'side', 'default');
    }

    public function render_gedicht_metabox($post): void {
        wp_nonce_field('gedicht_meta_save', 'gedicht_meta_nonce');
        $datum = get_post_meta($post->ID, '_gedicht_datum', true);
        echo '<p><label for="gedicht_datum">Entstehungsdatum:</label><br>';
        echo '<input id="gedicht_datum" type="text" name="gedicht_datum" value="' . esc_attr($datum) . '" class="widefat"></p>';
    }

    public function save_gedicht_meta($post_id): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (!isset($_POST['gedicht_meta_nonce']) || !wp_verify_nonce($_POST['gedicht_meta_nonce'], 'gedicht_meta_save')) return;

        if (isset($_POST['gedicht_datum'])) {
            update_post_meta($post_id, '_gedicht_datum', sanitize_text_field(wp_unslash($_POST['gedicht_datum'])));
        }
    }

    public function sync_gedicht_on_polylang_save($post_id, $translations): void {
        $this->sync_new_polylang_translations($post_id, $translations, 'gedicht');
    }

    public function enqueue_admin_assets($hook): void {
        wp_enqueue_style('micinterart-admin', plugin_dir_url(__FILE__) . 'assets/css/admin.css', [], self::VERSION);
    }

    public function sync_page_on_polylang_save($post_id, $translations) {
        if (!function_exists('pll_get_post_language')) return;

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'page') return;

        $source_id = $this->get_german_translation_id($translations);
        if (!$source_id) return;

        foreach ($translations as $lang => $translation_id) {
            if (!$translation_id || $translation_id == $source_id || $lang === 'de') continue;
            $this->copy_page_translation($source_id, $translation_id);
        }
    }

    public function sync_werk_on_save($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!function_exists('pll_get_post_language') || !function_exists('pll_get_post_translations')) return;
        if (!empty($this->translation_sync_in_progress[$post_id])) return;

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'werk') return;

        $translations = pll_get_post_translations($post_id);
        if (empty($translations)) return;

        $source_id = $this->get_german_translation_id($translations);
        if ($source_id && $source_id != $post_id) {
            $this->copy_werk_metadata($source_id, $post_id);
        }
    }

    public function sync_gedicht_on_save($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!function_exists('pll_get_post_language') || !function_exists('pll_get_post_translations')) return;
        if (!empty($this->translation_sync_in_progress[$post_id])) return;

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'gedicht') return;

        $translations = pll_get_post_translations($post_id);
        if (empty($translations)) return;

        $source_id = $this->get_german_translation_id($translations);
        if ($source_id && $source_id != $post_id) {
            $this->copy_gedicht_metadata($source_id, $post_id, true);
        }
    }

    /**
     * Polylang ruft diesen Hook beim Verknüpfen der Übersetzung auf. Als
     * Ausgangspunkt dient immer die deutsche Fassung, damit beim Speichern
     * einer Übersetzung keine Inhalte in die falsche Richtung kopiert werden.
     */
    private function sync_new_polylang_translations($post_id, $translations, $post_type) {
        if (!function_exists('pll_get_post_language')) return;

        $post = get_post($post_id);
        if (!$post || $post->post_type !== $post_type) return;

        $source_id = $this->get_german_translation_id($translations);
        if (!$source_id) return;

        foreach ($translations as $lang => $translation_id) {
            if (!$translation_id || $translation_id == $source_id || $lang === 'de') continue;
            if ($post_type === 'werk') {
                $this->copy_werk_metadata($source_id, $translation_id);
            } else {
                $this->copy_gedicht_metadata($source_id, $translation_id, true);
            }
        }
    }

    private function get_german_translation_id($translations) {
        if (!is_array($translations)) return 0;

        if (!empty($translations['de'])) return (int) $translations['de'];
        foreach ($translations as $translation_id) {
            if ($translation_id && pll_get_post_language($translation_id, 'slug') === 'de') {
                return (int) $translation_id;
            }
        }
        return 0;
    }

    /**
     * Titel und Gutenberg-Inhalt werden nur beim ersten Anlegen der
     * Zielseite übersetzt. Bereits redaktionell gepflegte Seiten bleiben
     * unverändert. Die Merkung als "übersetzt" erfolgt erst, wenn DeepL
     * tatsächlich geantwortet hat – sonst wird beim nächsten Speichern
     * erneut versucht zu übersetzen.
     */
    private function copy_page_translation($source_id, $target_id, $force = false) {
        if (!$force && metadata_exists('post', $target_id, self::PAGE_TRANSLATION_FLAG)) return;

        $source_post = get_post($source_id);
        $target_post = get_post($target_id);
        if (!$source_post || !$target_post) return;

        $target_lang = pll_get_post_language($target_id, 'slug');
        $deepl_target = $this->get_deepl_target_language($target_id, $target_lang);
        if ($deepl_target === '') {
            $this->record_deepl_error(sprintf('Für die Sprache "%s" ist kein DeepL-Zielcode hinterlegt, die Seite wurde nicht übersetzt.', (string) $target_lang));
            return;
        }

        $translation_complete = true;
        $update_post = [];
        if (!empty($source_post->post_title)
            && ($force || $this->is_empty_translation_title($target_post) || $this->is_untranslated_field($target_post->post_title, $source_post->post_title))) {
            $update_post['ID'] = $target_id;
            $update_post['post_title'] = $this->translate_text($source_post->post_title, $deepl_target);
            $translation_complete = $translation_complete && $this->last_translation_succeeded;
        }
        if (!empty($source_post->post_content)
            && ($force || $this->is_untranslated_field($target_post->post_content, $source_post->post_content))) {
            $update_post['ID'] = $target_id;
            $update_post['post_content'] = $this->translate_text($source_post->post_content, $deepl_target);
            $translation_complete = $translation_complete && $this->last_translation_succeeded;
        }

        $this->update_translation_post($target_id, $update_post);
        if ($translation_complete) {
            update_post_meta($target_id, self::PAGE_TRANSLATION_FLAG, '1');
        }
    }

    private function copy_werk_metadata($source_id, $target_id, $force = false) {
        $source_post = get_post($source_id);
        $target_post = get_post($target_id);
        
        if (!$source_post || !$target_post) return;

        $target_lang = '';
        if (function_exists('pll_get_post_language')) {
            $target_lang = pll_get_post_language($target_id, 'slug');
        }
        $deepl_target = $this->get_deepl_target_language($target_id, $target_lang);
        $should_translate = $deepl_target !== '';
        $update_post = [];
        if (($force || $this->is_empty_translation_title($target_post) || ($should_translate && $this->is_untranslated_field($target_post->post_title, $source_post->post_title))) && !empty($source_post->post_title)) {
            $update_post['ID'] = $target_id;
            $update_post['post_title'] = $should_translate ? $this->translate_text($source_post->post_title, $deepl_target) : $source_post->post_title;
        }
        if (($force || empty($target_post->post_content) || ($should_translate && $this->is_untranslated_field($target_post->post_content, $source_post->post_content))) && !empty($source_post->post_content)) {
            $update_post['ID'] = $target_id;
            $update_post['post_content'] = $should_translate ? $this->translate_text($source_post->post_content, $deepl_target) : $source_post->post_content;
        }
        if (($force || empty($target_post->post_excerpt) || ($should_translate && $this->is_untranslated_field($target_post->post_excerpt, $source_post->post_excerpt))) && !empty($source_post->post_excerpt)) {
            $update_post['ID'] = $target_id;
            $update_post['post_excerpt'] = $should_translate ? $this->translate_text($source_post->post_excerpt, $deepl_target) : $source_post->post_excerpt;
        }
        $this->update_translation_post($target_id, $update_post);

        $meta_keys = ['_werk_year', '_werk_materials', '_werk_dimensions', '_werk_preis', '_werk_represented', '_werk_exhibited', '_werk_additional_images'];
        $translatable_keys = ['_werk_materials', '_werk_represented', '_werk_exhibited'];
        foreach ($meta_keys as $meta_key) {
            $target_value = get_post_meta($target_id, $meta_key, true);
            
            if (empty($target_value) || ($should_translate && in_array($meta_key, $translatable_keys, true) && $target_value === get_post_meta($source_id, $meta_key, true))) {
                $source_value = get_post_meta($source_id, $meta_key, true);
                if (!empty($source_value)) {
                    if ($should_translate && in_array($meta_key, $translatable_keys, true)) {
                        $source_value = $this->translate_text($source_value, $deepl_target);
                    }
                    update_post_meta($target_id, $meta_key, $source_value);
                }
            }
        }

        if (!has_post_thumbnail($target_id) && has_post_thumbnail($source_id)) {
            set_post_thumbnail($target_id, get_post_thumbnail_id($source_id));
        }

        $series = get_the_terms($source_id, 'serie');
        if ($series && !is_wp_error($series)) {
            $serie_ids = wp_list_pluck($series, 'term_id');
            wp_set_post_terms($target_id, $serie_ids, 'serie', false);
        }
    }

    /**
     * Titel, Text und Auszug werden genau einmal von DeepL vorbefüllt.
     * "Einmal" bezieht sich dabei auf den Inhalt, nicht auf den Zeitpunkt:
     * Solange im Zielgedicht noch der deutsche Text steht, wird bei jedem
     * Speichern erneut übersetzt – auch wenn Gutenberg das Ergebnis des
     * ersten Versuchs direkt wieder überschrieben hat. Sobald eine
     * abweichende Fassung in der Datenbank steht, wird sie als Übersetzung
     * gemerkt und nie wieder angefasst.
     */
    private function copy_gedicht_metadata($source_id, $target_id, $initialize_translation = false, $force = false) {
        $source_post = get_post($source_id);
        $target_post = get_post($target_id);
        
        if (!$source_post || !$target_post) return;

        $target_lang = '';
        if (function_exists('pll_get_post_language')) {
            $target_lang = pll_get_post_language($target_id, 'slug');
        }
        $deepl_target = $this->get_deepl_target_language($target_id, $target_lang);
        $should_translate = $deepl_target !== '';
        $is_initial_translation = $force || ($initialize_translation
            && $target_post->post_status !== 'auto-draft'
            && !metadata_exists('post', $target_id, self::GEDICHT_TRANSLATION_FLAG));

        if ($is_initial_translation && !$force && $this->has_translated_content($source_post, $target_post)) {
            update_post_meta($target_id, self::GEDICHT_TRANSLATION_FLAG, '1');
            $is_initial_translation = false;
        }

        $update_post = [];
        foreach ($is_initial_translation ? ['post_title', 'post_content', 'post_excerpt'] : [] as $field) {
            if (empty($source_post->$field)) continue;

            $is_empty_target = $field === 'post_title'
                ? $this->is_empty_translation_title($target_post)
                : empty($target_post->$field);
            if (!$force && !$is_empty_target
                && !($should_translate && $this->is_untranslated_field($target_post->$field, $source_post->$field))) {
                continue;
            }

            $update_post['ID'] = $target_id;
            $update_post[$field] = $should_translate ? $this->translate_text($source_post->$field, $deepl_target) : $source_post->$field;
        }
        $this->update_translation_post($target_id, $update_post);

        $meta_keys = ['_gedicht_datum'];
        foreach ($meta_keys as $meta_key) {
            $target_value = get_post_meta($target_id, $meta_key, true);
            
            if (empty($target_value)) {
                $source_value = get_post_meta($source_id, $meta_key, true);
                if (!empty($source_value)) {
                    update_post_meta($target_id, $meta_key, $source_value);
                }
            }
        }

        $this->copy_related_werk($source_id, $target_id, $target_lang);

        if (!has_post_thumbnail($target_id) && has_post_thumbnail($source_id)) {
            set_post_thumbnail($target_id, get_post_thumbnail_id($source_id));
        }
    }

    /** Steht im Zielgedicht bereits etwas anderes als der deutsche Text? */
    private function has_translated_content($source_post, $target_post) {
        $field = !empty($source_post->post_content) ? 'post_content' : 'post_title';
        return !empty($target_post->$field)
            && !$this->is_untranslated_field($target_post->$field, $source_post->$field);
    }

    private function update_translation_post($target_id, $update_post) {
        if (empty($update_post)) return;

        $this->translation_sync_in_progress[$target_id] = true;
        // wp_update_post erwartet maskierte Daten und entfernt sonst
        // Backslashes aus dem übersetzten Text.
        wp_update_post(wp_slash($update_post));
        unset($this->translation_sync_in_progress[$target_id]);
    }

    private function is_empty_translation_title($post) {
        return empty($post->post_title)
            || ($post->post_status === 'auto-draft' && $post->post_title === 'Auto Draft');
    }

    /**
     * Ein Feld gilt als noch nicht übersetzt, wenn es leer ist oder – nach
     * Abzug von Markup und Leerraum – dem deutschen Original entspricht.
     * PolyLang kopiert Gutenberg-Inhalte häufig mit geänderten Medien-IDs
     * und Blockattributen, ein Zeichenvergleich würde dann fehlschlagen.
     */
    private function is_untranslated_field($target_value, $source_value) {
        if (trim((string) $target_value) === '') return true;
        return $this->normalize_for_comparison($target_value) === $this->normalize_for_comparison($source_value);
    }

    private function normalize_for_comparison($value) {
        $plain = wp_strip_all_tags((string) $value);
        $plain = html_entity_decode($plain, ENT_QUOTES, 'UTF-8');
        $plain = preg_replace('/\s+/u', ' ', $plain);
        return trim(function_exists('mb_strtolower') ? mb_strtolower($plain, 'UTF-8') : strtolower($plain));
    }

    /** Verknüpft ein übersetztes Gedicht mit der passenden Werk-Übersetzung. */
    private function copy_related_werk($source_id, $target_id, $target_lang) {
        if (!function_exists('wc_get_product')) return;

        $target_werk_id = absint(get_post_meta($target_id, '_related_werk', true));
        $target_werk = $target_werk_id ? wc_get_product($target_werk_id) : false;
        if ($target_werk && $target_werk->get_type() === 'werk') return;

        $related_werk_id = (int) get_post_meta($source_id, '_related_werk', true);
        if (!$related_werk_id) return;

        $related_werk = wc_get_product($related_werk_id);
        if (!$related_werk || $related_werk->get_type() !== 'werk') {
            delete_post_meta($target_id, '_related_werk');
            return;
        }

        if (function_exists('pll_get_post_translations')) {
            $werk_translations = pll_get_post_translations($related_werk_id);
            if (!empty($werk_translations[$target_lang])) {
                $related_werk_id = (int) $werk_translations[$target_lang];
            }
        }
        update_post_meta($target_id, '_related_werk', $related_werk_id);
    }

    /**
     * Übersetzt einen Text via DeepL in die Zielsprache.
     * $deepl_target ist ein DeepL-Sprachcode, z.B. 'EN-GB' oder 'RU'.
     */
    private function translate_text($text, $deepl_target) {
        $this->last_translation_succeeded = true;
        if (empty($text)) return $text;

        $api_key = get_option('micinterart_deepl_api_key', '');
        if (empty($api_key)) {
            $this->last_translation_succeeded = false;
            $this->record_deepl_error('Es ist kein DeepL API-Schlüssel hinterlegt, es wurde nichts übersetzt.');
            return $text;
        }
        if (!$deepl_target) {
            $this->last_translation_succeeded = false;
            return $text;
        }

        // DeepL Free-Keys enden auf ":fx" und nutzen einen anderen Endpunkt als Pro-Keys.
        $api_url = (substr($api_key, -3) === ':fx')
            ? 'https://api-free.deepl.com/v2/translate'
            : 'https://api.deepl.com/v2/translate';

        $response = wp_remote_post($api_url, [
            'timeout' => 15,
            // DeepL empfiehlt die Authentifizierung per Header. Dadurch wird
            // der Schlüssel auch nicht als Formularparameter weitergegeben.
            'headers' => [
                'Authorization' => 'DeepL-Auth-Key ' . $api_key,
            ],
            'body' => [
                'text' => $text,
                'source_lang' => 'DE',
                'target_lang' => $deepl_target,
                // Beschreibungen können Gutenberg-/HTML-Markup enthalten.
                // DeepL übersetzt dann nur Textknoten und erhält das Markup.
                'tag_handling' => 'html',
            ],
        ]);

        if (is_wp_error($response)) {
            $message = 'DeepL-Verbindung fehlgeschlagen: ' . $response->get_error_message();
            error_log($message);
            $this->record_deepl_error($message);
            $this->last_translation_succeeded = false;
            return $text;
        }

        $response_body = wp_remote_retrieve_body($response);
        if (wp_remote_retrieve_response_code($response) !== 200) {
            $message = 'DeepL antwortet mit HTTP ' . wp_remote_retrieve_response_code($response) . '. Bitte API-Schlüssel und API-Zugang prüfen.';
            error_log($message . ' Antwort: ' . $response_body);
            $this->record_deepl_error($message);
            $this->last_translation_succeeded = false;
            return $text;
        }

        $body = json_decode($response_body, true);
        if (isset($body['translations'][0]['text'])) {
            delete_option('micinterart_deepl_last_error');
            return $body['translations'][0]['text'];
        }

        error_log('DeepL Translation Error: unexpected API response.');
        $this->record_deepl_error('DeepL hat eine unerwartete Antwort zurückgegeben.');
        $this->last_translation_succeeded = false;
        return $text;
    }

    private function record_deepl_error($message) {
        update_option('micinterart_deepl_last_error', [
            'message' => sanitize_text_field($message),
            'time' => time(),
        ], false);
    }

    /**
     * PolyLang installations use different slugs for the same language
     * (for example en, en-gb or en_US). The locale is therefore checked as
     * a fallback before a DeepL code is selected.
     */
    private function get_deepl_target_language($post_id, $target_lang_slug) {
        $candidates = [$target_lang_slug];
        if (function_exists('pll_get_post_language')) {
            $candidates[] = pll_get_post_language($post_id, 'locale');
            $candidates[] = pll_get_post_language($post_id, 'name');
        }

        $lang_map = [
            'en' => 'EN-GB',
            'en-gb' => 'EN-GB',
            'en-us' => 'EN-US',
            'english' => 'EN-GB',
            'englisch' => 'EN-GB',
            'eng' => 'EN-GB',
            'ru' => 'RU',
            'ru-ru' => 'RU',
            'russian' => 'RU',
            'russisch' => 'RU',
            'rus' => 'RU',
        ];

        foreach ($candidates as $candidate) {
            $normalized = strtolower(str_replace('_', '-', (string) $candidate));
            if (isset($lang_map[$normalized])) {
                return $lang_map[$normalized];
            }
        }
        return '';
    }

    // ===== MANUELLE NEUÜBERSETZUNG =====

    /** Bietet in der Übersicht eine Aktion an, um eine Übersetzung neu zu erzeugen. */
    public function add_retranslate_row_action($actions, $post) {
        if (!in_array($post->post_type, ['page', 'gedicht'], true)) return $actions;
        if (!function_exists('pll_get_post_translations')) return $actions;
        if (!current_user_can('edit_post', $post->ID)) return $actions;

        $source_id = $this->get_german_translation_id(pll_get_post_translations($post->ID));
        if (!$source_id || $source_id == $post->ID) return $actions;

        $url = wp_nonce_url(
            admin_url('admin-post.php?action=micinterart_retranslate&post=' . $post->ID),
            'micinterart_retranslate_' . $post->ID
        );
        $actions['micinterart_retranslate'] = '<a href="' . esc_url($url) . '">Neu übersetzen</a>';
        return $actions;
    }

    public function handle_retranslate_request() {
        $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_die('Keine Berechtigung für diese Übersetzung.');
        }
        check_admin_referer('micinterart_retranslate_' . $post_id);

        $status = $this->retranslate_post($post_id) ? 'ok' : 'failed';
        $redirect = wp_get_referer() ?: admin_url('edit.php?post_type=' . get_post_type($post_id));
        wp_safe_redirect(add_query_arg('micinterart_retranslated', $status, $redirect));
        exit;
    }

    /** Verwirft die Merkung als "übersetzt" und übersetzt aus dem Deutschen neu. */
    private function retranslate_post($post_id) {
        if (!function_exists('pll_get_post_translations')) return false;

        $source_id = $this->get_german_translation_id(pll_get_post_translations($post_id));
        if (!$source_id || $source_id == $post_id) return false;

        switch (get_post_type($post_id)) {
            case 'page':
                delete_post_meta($post_id, self::PAGE_TRANSLATION_FLAG);
                $this->copy_page_translation($source_id, $post_id, true);
                return true;
            case 'gedicht':
                delete_post_meta($post_id, self::GEDICHT_TRANSLATION_FLAG);
                $this->copy_gedicht_metadata($source_id, $post_id, true, true);
                return true;
        }
        return false;
    }

    public function render_retranslate_notice() {
        if (!isset($_GET['micinterart_retranslated'])) return;

        $status = sanitize_key(wp_unslash($_GET['micinterart_retranslated']));
        $deepl_error = get_option('micinterart_deepl_last_error');
        if ($status === 'ok' && !(is_array($deepl_error) && !empty($deepl_error['message']))) {
            echo '<div class="notice notice-success is-dismissible"><p>Übersetzung wurde neu von DeepL erzeugt.</p></div>';
            return;
        }

        $message = is_array($deepl_error) && !empty($deepl_error['message'])
            ? $deepl_error['message']
            : 'Es wurde keine deutsche Ausgangsfassung gefunden.';
        echo '<div class="notice notice-error is-dismissible"><p>Übersetzung fehlgeschlagen: ' . esc_html($message) . '</p></div>';
    }

    public function add_plugin_settings_menu() {
        add_submenu_page('options-general.php', 'Micinterart Settings', 'Micinterart', 'manage_options', 'micinterart-settings', [$this, 'render_plugin_settings_page']);
    }

    public function register_plugin_settings() {
        register_setting('micinterart-settings-group', 'micinterart_deepl_api_key', ['sanitize_callback' => 'sanitize_text_field']);
    }

    public function render_plugin_settings_page() {
        if (!current_user_can('manage_options')) return;
        $deepl_error = get_option('micinterart_deepl_last_error');
        ?>
        <div class="wrap">
            <h1>Micinterart Plugin Settings</h1>
            <?php if (is_array($deepl_error) && !empty($deepl_error['message'])) : ?>
                <div class="notice notice-error"><p><strong>Letzter DeepL-Fehler:</strong> <?php echo esc_html($deepl_error['message']); ?></p></div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('micinterart-settings-group'); ?>
                <?php do_settings_sections('micinterart-settings-group'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">DeepL API Key</th>
                        <td>
                            <input type="password" name="micinterart_deepl_api_key" value="<?php echo esc_attr(get_option('micinterart_deepl_api_key')); ?>" style="width: 300px;" />
                            <p class="description">Wird für die automatische Übersetzung von Titel, Beschreibung, Materialien und Galerie-Hinweis (Werke), Titel und Text (Gedichte) sowie Titel und Gutenberg-Inhalt (Seiten) genutzt, sobald du eine Englisch- oder Russisch-Übersetzung anlegst. Es werden nur leere oder noch deutschsprachige Felder befüllt, bestehende Übersetzungen werden nie überschrieben. Eine bereits angelegte Übersetzung lässt sich in der Seiten-, Gedicht- oder Werk-Übersicht über „Neu übersetzen“ erneut erzeugen. Get it at <a href="https://www.deepl.com/pro-api" target="_blank">DeepL</a>.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function render_related_poems_for_werk_product(): void {
        if (!function_exists('wc_get_product')) {
            return;
        }

        $product = wc_get_product(get_the_ID());
        if (!$product || $product->get_type() !== 'werk') {
            return;
        }

        $poems = get_posts([
            'post_type' => 'gedicht',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_key' => '_related_werk',
            'meta_value' => $product->get_id(),
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        if (empty($poems)) {
            return;
        }

        echo '<section class="werk-gedichte-links"><h2>' . esc_html__('Gedichte zu diesem Werk', 'micinterart') . '</h2><ul>';
        foreach ($poems as $poem) {
            echo '<li><a href="' . esc_url(get_permalink($poem->ID)) . '">' . esc_html(get_the_title($poem->ID)) . '</a></li>';
        }
        echo '</ul></section>';
    }

    public function render_gedicht_relation_metabox($post) {
        wp_nonce_field('gedicht_relation_nonce', 'gedicht_relation_nonce');
        
        $related_werk_id = get_post_meta($post->ID, '_related_werk', true);
        
        $werke = function_exists('wc_get_product') ? get_posts([
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'tax_query' => [[
                'taxonomy' => 'product_type',
                'field' => 'slug',
                'terms' => 'werk',
            ]],
        ]) : [];
        
        ?>
        <label for="gedicht_related_werk">Zugeordnetes Werk:</label>
        <select name="gedicht_related_werk" id="gedicht_related_werk" style="width: 100%;">
            <option value="">-- Kein Werk --</option>
            <?php foreach ($werke as $werk) : ?>
                <option value="<?php echo $werk->ID; ?>" <?php selected($related_werk_id, $werk->ID); ?>>
                    <?php echo esc_html($werk->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function save_gedicht_relation_meta($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        
        if (!isset($_POST['gedicht_relation_nonce']) || !wp_verify_nonce($_POST['gedicht_relation_nonce'], 'gedicht_relation_nonce')) {
            return;
        }

        if (isset($_POST['gedicht_related_werk'])) {
            $werk_id = absint(wp_unslash($_POST['gedicht_related_werk']));
            $werk_product = function_exists('wc_get_product') && $werk_id ? wc_get_product($werk_id) : false;
            if ($werk_product && $werk_product->get_type() === 'werk') {
                update_post_meta($post_id, '_related_werk', $werk_product->get_id());
            } else {
                delete_post_meta($post_id, '_related_werk');
            }
        }
    }
}

MicinterartGallery::get_instance();
