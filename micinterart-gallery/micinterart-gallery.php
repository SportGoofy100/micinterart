<?php
/**
 * Plugin Name: Micinterart Gallery
 * Description: Galerie-Lösung mit CPT "Werk", "Gedicht" und "Workshop", Taxonomie "Serie", erweiterten Metaboxen und Lightbox
 * Version: 2.4.6
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

    private const VERSION = '2.4.6';
    private static $instance = null;
    /** Verhindert erneute Synchronisierungen, die durch wp_update_post ausgelöst werden. */
    private $translation_sync_in_progress = [];

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
        add_action('init', [$this, 'register_werk_meta_fields']);
        add_action('init', [$this, 'register_taxonomies']);
        add_action('init', [$this, 'register_gallery_block']);
        add_action('add_meta_boxes', [$this, 'add_metaboxes']);
        add_action('save_post_werk', [$this, 'save_werk_meta']);
        add_action('save_post_werk', [$this, 'save_werk_gallery_meta']);
        add_action('save_post_werk', [$this, 'sync_werk_on_save'], 20);
        add_action('save_post_gedicht', [$this, 'save_gedicht_meta']);
        add_action('save_post_gedicht', [$this, 'save_gedicht_relation_meta']);
        add_action('save_post_gedicht', [$this, 'sync_gedicht_on_save'], 20);
        add_action('pll_save_post_translations', [$this, 'sync_gedicht_on_polylang_save'], 10, 2);
        add_action('pll_save_post_translations', [$this, 'sync_werk_on_polylang_save'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_menu', [$this, 'add_plugin_settings_menu']);
        add_action('admin_init', [$this, 'register_plugin_settings']);
        
        add_action('wp_ajax_micinterart_get_werk_details', [$this, 'ajax_get_werk_details']);
        add_action('wp_ajax_nopriv_micinterart_get_werk_details', [$this, 'ajax_get_werk_details']);
        add_action('wp_ajax_micinterart_filter_gallery', [$this, 'ajax_filter_gallery']);
        add_action('wp_ajax_nopriv_micinterart_filter_gallery', [$this, 'ajax_filter_gallery']);
    }

    public function activate(): void {
        $this->register_post_types();
        $this->register_taxonomies();
        flush_rewrite_rules();
    }

    public function deactivate(): void {
        flush_rewrite_rules();
    }

    public function register_post_types(): void {
        register_post_type('werk', [
            'labels' => ['name' => 'Werke', 'singular_name' => 'Werk', 'add_new_item' => 'Neues Werk hinzufügen', 'edit_item' => 'Werk bearbeiten'],
            'public' => true, 'has_archive' => true, 'menu_icon' => 'dashicons-art', 'supports' => ['title', 'editor', 'thumbnail', 'excerpt'], 'show_in_rest' => true, 'rewrite' => ['slug' => 'galerie'],
        ]);

        register_post_type('gedicht', [
            'labels' => ['name' => 'Gedichte', 'singular_name' => 'Gedicht', 'add_new_item' => 'Neues Gedicht hinzufügen'],
            'public' => true, 'has_archive' => true, 'menu_icon' => 'dashicons-editor-quote', 'supports' => ['title', 'editor', 'thumbnail'], 'show_in_rest' => true, 'rewrite' => ['slug' => 'lyrik'],
        ]);

        register_post_type('workshop', [
    'labels' => [
        'name'               => 'Workshops',
        'singular_name'      => 'Workshop',
        'add_new'            => 'Neuer Workshop',
        'add_new_item'       => 'Neuen Workshop hinzufügen',
        'edit_item'          => 'Workshop bearbeiten',
        'all_items'          => 'Alle Workshops',
        'menu_name'          => 'Workshops'
    ],
    'public'        => true,
    'has_archive'   => true,
    'show_in_rest'  => true, // Aktiviert den modernen Editor
    'menu_icon'     => 'dashicons-art',
    'supports'      => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
    'taxonomies'    => ['workshop_kategorie'],
    'rewrite'       => ['slug' => 'workshops'],
]);

        register_post_type('workshop_thema', [
            'labels' => ['name' => 'Workshop Themen', 'singular_name' => 'Workshop Thema'],
            'public' => false, 'show_ui' => true, 'show_in_menu' => 'edit.php?post_type=workshop', 'supports' => ['title', 'editor', 'thumbnail'],
        ]);
    }

    // ✅ KORRIGIERT: <?php entfernt!
    public function register_taxonomies(): void {
        // Serie für Werke
        register_taxonomy('serie', ['werk'], [
            'labels' => ['name' => 'Serien', 'singular_name' => 'Serie'],
            'hierarchical' => true, 
            'show_in_rest' => true, 
            'rewrite' => ['slug' => 'serie'],
        ]);
        
        // Workshop-Kategorien
        register_taxonomy('workshop_kategorie', ['workshop'], [
            'labels' => [
                'name' => 'Workshop-Kategorien',
                'singular_name' => 'Workshop-Kategorie',
                'add_new_item' => 'Neue Kategorie hinzufügen',
                'edit_item' => 'Kategorie bearbeiten',
                'all_items' => 'Alle Kategorien',
                'search_items' => 'Kategorien durchsuchen',
                'parent_item' => 'Übergeordnete Kategorie',
                'parent_item_colon' => 'Übergeordnete Kategorie:',
                'update_item' => 'Kategorie aktualisieren',
                'menu_name' => 'Kategorien'
            ],
            'hierarchical' => true,
            'public' => true,
            'show_ui' => true,
            'show_in_rest' => true,
            'show_admin_column' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'workshop-kategorie', 'with_front' => false],
        ]);
    }

    public function add_metaboxes(): void {
        add_meta_box('werk_details', 'Werk-Informationen', [$this, 'render_werk_metabox'], 'werk', 'normal', 'high');
        add_meta_box('werk_gallery', 'Weitere Ansichten (Galerie)', [$this, 'render_werk_gallery_metabox'], 'werk', 'normal', 'default');
        add_meta_box('gedicht_details', 'Gedicht-Informationen', [$this, 'render_gedicht_metabox'], 'gedicht', 'normal', 'high');
        
        add_meta_box('ws_datum', 'Datum & Uhrzeit', 'micinterart_render_workshop_datum_metabox', 'workshop', 'side');
        add_meta_box('ws_wiederholung', 'Wiederholung & Rhythmus', 'micinterart_render_workshop_wiederholung_metabox', 'workshop', 'normal', 'high');
        add_meta_box('ws_preis', 'Preis & Leistungen', 'micinterart_render_workshop_preis_metabox', 'workshop', 'normal');
        add_meta_box('ws_status', 'Anmeldestatus', 'micinterart_render_workshop_status_metabox', 'workshop', 'side');
        add_meta_box('ws_ort', 'Ort & Adresse', 'micinterart_render_workshop_ort_metabox', 'workshop', 'normal');
        add_meta_box('ws_teil', 'Teilnehmer', 'micinterart_render_workshop_teilnehmer_metabox', 'workshop', 'side');
        add_meta_box('ws_alter', 'Altersempfehlung', 'micinterart_render_workshop_alter_metabox', 'workshop', 'side');
        add_meta_box('thema_ws_link', 'Zugehörigkeit', 'micinterart_render_thema_link_metabox', 'workshop_thema', 'side');
        add_meta_box('thema_datum', 'Fixes Datum (optional)', 'micinterart_render_thema_datum_metabox', 'workshop_thema', 'side');
        add_meta_box('thema_preis', 'Preis (optional)', 'micinterart_render_thema_preis_metabox', 'workshop_thema', 'side');
        add_meta_box('thema_uhrzeit', 'Uhrzeit (optional)', 'micinterart_render_thema_uhrzeit_metabox', 'workshop_thema', 'side');
        add_meta_box('ws_sprache', 'Kurssprache', 'micinterart_render_workshop_sprache_metabox', 'workshop', 'side');

    }

    public function render_werk_metabox($post) {
        wp_nonce_field('werk_meta_save', 'werk_meta_nonce');
        $jahr = get_post_meta($post->ID, '_werk_year', true);
        $technik = get_post_meta($post->ID, '_werk_materials', true);
        $format = get_post_meta($post->ID, '_werk_dimensions', true);
        $preis = get_post_meta($post->ID, '_werk_preis', true);
        $represented = get_post_meta($post->ID, '_werk_represented', true);
        ?>
        <p><label>Jahr:</label><br><input type="text" name="werk_jahr" value="<?php echo esc_attr($jahr); ?>" class="widefat"></p>
        <p><label>Materialien:</label><br><input type="text" name="werk_technik" value="<?php echo esc_attr($technik); ?>" class="widefat"></p>
        <p><label>Maße:</label><br><input type="text" name="werk_format" value="<?php echo esc_attr($format); ?>" class="widefat"></p>
        <p><label>Preis (€):</label><br><input type="text" name="werk_preis" value="<?php echo esc_attr($preis); ?>" class="widefat"></p>
        <p><label>Galerie-Hinweis (z. B. "Galerie Helligkeit"):</label><br>
        <input type="text" name="werk_represented" value="<?php echo esc_attr($represented); ?>" class="widefat" placeholder="Leer lassen, falls nicht zutreffend">
        <span class="description">Wird im Frontend als "Represented by [Text]" bzw. "Vertreten durch [Text]" angezeigt, wenn ausgefüllt.</span></p>
        <?php
    }

    public function save_werk_meta($post_id) {
        if (!isset($_POST['werk_meta_nonce']) || !wp_verify_nonce($_POST['werk_meta_nonce'], 'werk_meta_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        $fields = ['_werk_year'=>'werk_jahr', '_werk_materials'=>'werk_technik', '_werk_dimensions'=>'werk_format', '_werk_preis'=>'werk_preis'];
        foreach($fields as $meta_key => $post_key) {
            if(isset($_POST[$post_key])) update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$post_key]));
        }

        if (isset($_POST['werk_represented'])) {
            $represented = sanitize_text_field($_POST['werk_represented']);
            if ($represented !== '') {
                update_post_meta($post_id, '_werk_represented', $represented);
            } else {
                delete_post_meta($post_id, '_werk_represented');
            }
        }
    }

    public function render_werk_gallery_metabox($post) {
        wp_nonce_field('werk_gallery_save', 'werk_gallery_nonce');
        $image_ids = get_post_meta($post->ID, '_werk_additional_images', true);
        $image_ids = is_array($image_ids) ? $image_ids : [];
        ?>
        <div id="werk-gallery-wrap">
            <ul id="werk-gallery-list" style="display:flex; flex-wrap:wrap; gap:10px; padding:0; margin:0 0 15px;">
                <?php foreach ($image_ids as $attachment_id):
                    $thumb = wp_get_attachment_image_url($attachment_id, 'thumbnail');
                    if (!$thumb) continue;
                ?>
                    <li class="werk-gallery-item" data-id="<?php echo esc_attr($attachment_id); ?>" style="list-style:none; position:relative; width:100px; height:100px;">
                        <img src="<?php echo esc_url($thumb); ?>" style="width:100%; height:100%; object-fit:cover; border-radius:4px;">
                        <button type="button" class="werk-gallery-remove" style="position:absolute; top:-6px; right:-6px; width:22px; height:22px; border-radius:50%; background:#dc3232; color:#fff; border:none; cursor:pointer; line-height:1;">&times;</button>
                        <input type="hidden" name="werk_gallery_ids[]" value="<?php echo esc_attr($attachment_id); ?>">
                    </li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="button" id="werk-gallery-add">Bilder hinzufügen</button>
            <p class="description">Diese Bilder erscheinen im Frontend unter "Weitere Ansichten". Bereits vorhandene Bilder (z. B. aus einem früheren Import) werden hier automatisch angezeigt.</p>
        </div>

        <script>
        (function($) {
            var frame;
            $('#werk-gallery-add').on('click', function(e) {
                e.preventDefault();
                if (frame) { frame.open(); return; }
                frame = wp.media({
                    title: 'Bilder auswählen',
                    button: { text: 'Übernehmen' },
                    multiple: true
                });
                frame.on('select', function() {
                    var selection = frame.state().get('selection');
                    selection.each(function(attachment) {
                        attachment = attachment.toJSON();
                        var thumb = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                        var $li = $('<li class="werk-gallery-item" style="list-style:none; position:relative; width:100px; height:100px;">' +
                            '<img src="' + thumb + '" style="width:100%; height:100%; object-fit:cover; border-radius:4px;">' +
                            '<button type="button" class="werk-gallery-remove" style="position:absolute; top:-6px; right:-6px; width:22px; height:22px; border-radius:50%; background:#dc3232; color:#fff; border:none; cursor:pointer; line-height:1;">&times;</button>' +
                            '<input type="hidden" name="werk_gallery_ids[]" value="' + attachment.id + '">' +
                            '</li>');
                        $('#werk-gallery-list').append($li);
                    });
                });
                frame.open();
            });

            $('#werk-gallery-list').on('click', '.werk-gallery-remove', function() {
                $(this).closest('.werk-gallery-item').remove();
            });
        })(jQuery);
        </script>
        <?php
    }

    public function save_werk_gallery_meta($post_id) {
        if (!isset($_POST['werk_gallery_nonce']) || !wp_verify_nonce($_POST['werk_gallery_nonce'], 'werk_gallery_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $ids = isset($_POST['werk_gallery_ids']) ? array_map('absint', (array) $_POST['werk_gallery_ids']) : [];
        $ids = array_values(array_unique(array_filter($ids)));

        if (!empty($ids)) {
            update_post_meta($post_id, '_werk_additional_images', $ids);
        } else {
            delete_post_meta($post_id, '_werk_additional_images');
        }
    }

    public function register_werk_meta_fields() {
        register_post_meta('werk', '_werk_additional_images', [
            'type'         => 'array',
            'single'       => true,
            'show_in_rest' => [
                'schema' => [
                    'type'  => 'array',
                    'items' => ['type' => 'integer'],
                ],
            ],
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public function render_gedicht_metabox($post) {
        wp_nonce_field('gedicht_meta_save', 'gedicht_meta_nonce');
        $datum = get_post_meta($post->ID, '_gedicht_datum', true);
        ?>
        <p><label>Entstehungsdatum:</label><br><input type="text" name="gedicht_datum" value="<?php echo esc_attr($datum); ?>" class="widefat"></p>
        <?php
    }

    public function save_gedicht_meta($post_id) {
        if (!isset($_POST['gedicht_meta_nonce']) || !wp_verify_nonce($_POST['gedicht_meta_nonce'], 'gedicht_meta_save')) return;
        if(isset($_POST['gedicht_datum'])) update_post_meta($post_id, '_gedicht_datum', sanitize_text_field($_POST['gedicht_datum']));
    }

    public function enqueue_admin_assets($hook) {
        wp_enqueue_style('micinterart-admin', plugin_dir_url(__FILE__) . 'assets/css/admin.css', [], self::VERSION);

        global $post_type;
        if (in_array($hook, ['post.php', 'post-new.php'], true) && $post_type === 'werk') {
            wp_enqueue_media();
        }
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_style('micinterart-frontend', plugin_dir_url(__FILE__) . 'assets/css/frontend.css', [], self::VERSION);
        wp_enqueue_script('micinterart-frontend', plugin_dir_url(__FILE__) . 'assets/js/frontend.js', ['jquery'], self::VERSION, true);
        wp_localize_script('micinterart-frontend', 'micinterart_ajax', ['ajax_url' => admin_url('admin-ajax.php')]);
    }

    public function ajax_get_werk_details() {
        $post_id = intval($_POST['post_id']);
        if (!$post_id) wp_send_json_error();
        $post = get_post($post_id);
        $jahr = get_post_meta($post_id, '_werk_year', true);
        $technik = get_post_meta($post_id, '_werk_materials', true);
        $format = get_post_meta($post_id, '_werk_dimensions', true);
        $img = get_the_post_thumbnail_url($post_id, 'large');
        ob_start(); ?>
        <div class="mic-lightbox-content">
            <div class="mic-lightbox-image"><img src="<?php echo $img; ?>" alt="<?php echo esc_attr($post->post_title); ?> – Kunstwerk von Micaella Cervinscaia, micinterart Morsbach"></div>
            <div class="mic-lightbox-info">
                <h2><?php echo esc_html($post->post_title); ?></h2>
                <p><strong>Jahr:</strong> <?php echo esc_html($jahr); ?></p>
                <p><strong>Materialien:</strong> <?php echo esc_html($technik); ?></p>
                <p><strong>Maße:</strong> <?php echo esc_html($format); ?></p>
                <div class="mic-lightbox-desc"><?php echo apply_filters('the_content', $post->post_content); ?></div>
            </div>
        </div>
        <?php
        $html = ob_get_clean();
        wp_send_json_success($html);
    }

    public function ajax_filter_gallery() {
        $serie = $_POST['serie'];
        $args = ['post_type'=>'werk', 'posts_per_page'=>-1];
        if($serie !== 'all') { $args['tax_query'] = [['taxonomy'=>'serie', 'field'=>'slug', 'terms'=>$serie]]; }
        $query = new WP_Query($args);
        ob_start();
        if($query->have_posts()): while($query->have_posts()): $query->the_post();
            get_template_part('template-parts/content', 'werk-grid');
        endwhile; endif;
        wp_send_json_success(ob_get_clean());
    }

    public function register_gallery_block() {
        register_block_type('micinterart/gallery', ['editor_script' => 'micinterart-block-editor', 'render_callback' => [$this, 'render_gallery_block']]);
    }

    // ===== POLYLANG SYNCHRONIZATION =====
    
    public function sync_werk_on_polylang_save($post_id, $translations) {
        $this->sync_new_polylang_translations($post_id, $translations, 'werk');
    }

    public function sync_gedicht_on_polylang_save($post_id, $translations) {
        $this->sync_new_polylang_translations($post_id, $translations, 'gedicht');
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
            $this->copy_gedicht_metadata($source_id, $post_id);
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

    private function copy_werk_metadata($source_id, $target_id) {
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
        if (($this->is_empty_translation_title($target_post) || ($should_translate && $target_post->post_title === $source_post->post_title)) && !empty($source_post->post_title)) {
            $update_post['ID'] = $target_id;
            $update_post['post_title'] = $should_translate ? $this->translate_text($source_post->post_title, $deepl_target) : $source_post->post_title;
        }
        if ((empty($target_post->post_content) || ($should_translate && $target_post->post_content === $source_post->post_content)) && !empty($source_post->post_content)) {
            $update_post['ID'] = $target_id;
            $update_post['post_content'] = $should_translate ? $this->translate_text($source_post->post_content, $deepl_target) : $source_post->post_content;
        }
        if ((empty($target_post->post_excerpt) || ($should_translate && $target_post->post_excerpt === $source_post->post_excerpt)) && !empty($source_post->post_excerpt)) {
            $update_post['ID'] = $target_id;
            $update_post['post_excerpt'] = $should_translate ? $this->translate_text($source_post->post_excerpt, $deepl_target) : $source_post->post_excerpt;
        }
        $this->update_translation_post($target_id, $update_post);

        $meta_keys = ['_werk_year', '_werk_materials', '_werk_dimensions', '_werk_preis', '_werk_represented', '_werk_additional_images'];
        foreach ($meta_keys as $meta_key) {
            $target_value = get_post_meta($target_id, $meta_key, true);
            
            if (empty($target_value) || ($should_translate && in_array($meta_key, ['_werk_materials', '_werk_represented'], true) && $target_value === get_post_meta($source_id, $meta_key, true))) {
                $source_value = get_post_meta($source_id, $meta_key, true);
                if (!empty($source_value)) {
                    if ($should_translate && in_array($meta_key, ['_werk_materials', '_werk_represented'])) {
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

    private function copy_gedicht_metadata($source_id, $target_id, $initialize_translation = false) {
        $source_post = get_post($source_id);
        $target_post = get_post($target_id);
        
        if (!$source_post || !$target_post) return;

        $target_lang = '';
        if (function_exists('pll_get_post_language')) {
            $target_lang = pll_get_post_language($target_id, 'slug');
        }
        $deepl_target = $this->get_deepl_target_language($target_id, $target_lang);
        $should_translate = $deepl_target !== '';
        // Nur beim Anlegen darf DeepL Titel und Text vorbefüllen. Danach
        // bleibt jede redaktionell eingetragene Übersetzung unverändert.
        $is_initial_translation = $initialize_translation
            && !metadata_exists('post', $target_id, '_micinterart_gedicht_translation_initialized');

        $update_post = [];
        if ($is_initial_translation && ($this->is_empty_translation_title($target_post) || ($should_translate && $target_post->post_title === $source_post->post_title)) && !empty($source_post->post_title)) {
            $update_post['ID'] = $target_id;
            $update_post['post_title'] = $should_translate ? $this->translate_text($source_post->post_title, $deepl_target) : $source_post->post_title;
        }
        if ($is_initial_translation && (empty($target_post->post_content) || ($should_translate && $target_post->post_content === $source_post->post_content)) && !empty($source_post->post_content)) {
            $update_post['ID'] = $target_id;
            $update_post['post_content'] = $should_translate ? $this->translate_text($source_post->post_content, $deepl_target) : $source_post->post_content;
        }
        if ($is_initial_translation && (empty($target_post->post_excerpt) || ($should_translate && $target_post->post_excerpt === $source_post->post_excerpt)) && !empty($source_post->post_excerpt)) {
            $update_post['ID'] = $target_id;
            $update_post['post_excerpt'] = $should_translate ? $this->translate_text($source_post->post_excerpt, $deepl_target) : $source_post->post_excerpt;
        }
        $this->update_translation_post($target_id, $update_post);
        if ($is_initial_translation) {
            update_post_meta($target_id, '_micinterart_gedicht_translation_initialized', '1');
        }

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

    private function update_translation_post($target_id, $update_post) {
        if (empty($update_post)) return;

        $this->translation_sync_in_progress[$target_id] = true;
        wp_update_post($update_post);
        unset($this->translation_sync_in_progress[$target_id]);
    }

    private function is_empty_translation_title($post) {
        return empty($post->post_title)
            || ($post->post_status === 'auto-draft' && $post->post_title === 'Auto Draft');
    }

    /** Verknüpft ein übersetztes Gedicht mit der passenden Werk-Übersetzung. */
    private function copy_related_werk($source_id, $target_id, $target_lang) {
        if (!empty(get_post_meta($target_id, '_related_werk', true))) return;

        $related_werk_id = (int) get_post_meta($source_id, '_related_werk', true);
        if (!$related_werk_id) return;

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
        if (empty($text)) return $text;

        $api_key = get_option('micinterart_deepl_api_key', '');
        if (empty($api_key)) return $text;
        if (!$deepl_target) return $text;

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
            return $text;
        }

        $response_body = wp_remote_retrieve_body($response);
        if (wp_remote_retrieve_response_code($response) !== 200) {
            $message = 'DeepL antwortet mit HTTP ' . wp_remote_retrieve_response_code($response) . '. Bitte API-Schlüssel und API-Zugang prüfen.';
            error_log($message . ' Antwort: ' . $response_body);
            $this->record_deepl_error($message);
            return $text;
        }

        $body = json_decode($response_body, true);
        if (isset($body['translations'][0]['text'])) {
            delete_option('micinterart_deepl_last_error');
            return $body['translations'][0]['text'];
        }

        error_log('DeepL Translation Error: unexpected API response.');
        $this->record_deepl_error('DeepL hat eine unerwartete Antwort zurückgegeben.');
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
                            <p class="description">Wird für die automatische Übersetzung von Titel, Beschreibung, Materialien und Galerie-Hinweis (Werke) sowie Titel und Text (Gedichte) genutzt, sobald du eine Englisch- oder Russisch-Übersetzung anlegst. Es werden nur leere Felder befüllt, bestehende Übersetzungen werden nie überschrieben. Get it at <a href="https://www.deepl.com/pro-api" target="_blank">DeepL</a>.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function render_gedicht_relation_metabox($post) {
        wp_nonce_field('gedicht_relation_nonce', 'gedicht_relation_nonce');
        
        $related_werk_id = get_post_meta($post->ID, '_related_werk', true);
        
        $werke = get_posts([
            'post_type' => 'werk',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        
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
            $werk_id = sanitize_text_field($_POST['gedicht_related_werk']);
            if (!empty($werk_id)) {
                update_post_meta($post_id, '_related_werk', (int) $werk_id);
            } else {
                delete_post_meta($post_id, '_related_werk');
            }
        }
    }
}

MicinterartGallery::get_instance();

// =========================================================================
// WORKSHOP METABOX RENDERING
// =========================================================================

function micinterart_render_workshop_wiederholung_metabox($post) {
    wp_nonce_field('workshop_wiederholung_save', 'workshop_wiederholung_nonce');
    
    $frequenz = get_post_meta($post->ID, '_workshop_wiederholung_frequenz', true) ?: 'einmalig';
    $gespeicherte_tage = json_decode(get_post_meta($post->ID, '_workshop_wiederholung_tage', true) ?: '[]', true);
    $monat_regelung = get_post_meta($post->ID, '_workshop_monat_regelung', true) ?: 'erster';
    $intervall = get_post_meta($post->ID, '_workshop_intervall', true) ?: '1';
    $anzahl_termine = get_post_meta($post->ID, '_workshop_wiederholung_anzahl', true) ?: '10';

    $frequenzen = [
        'einmalig' => 'Einmaliges Ereignis',
        'woechentlich' => 'Wöchentlich',
        'zweiwoechentlich' => 'Zweiwöchentlich',
        'monatlich' => 'Monatlich (bestimmter Wochentag)',
        'nach_absprache' => 'Nach Absprache'
    ];

    $wochentage = [
        'Monday' => 'Montag', 'Tuesday' => 'Dienstag', 'Wednesday' => 'Mittwoch', 
        'Thursday' => 'Donnerstag', 'Friday' => 'Freitag', 'Saturday' => 'Samstag', 'Sunday' => 'Sonntag'
    ];
    ?>
    <p>
        <label><strong>Wiederholung:</strong></label><br>
        <select name="workshop_wiederholung_frequenz" id="ws_freq" class="widefat">
            <?php foreach ($frequenzen as $val => $label): ?>
                <option value="<?php echo $val; ?>" <?php selected($frequenz, $val); ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
    </p>

    <div id="ws_tage_wrap" style="display: <?php echo ($frequenz === 'monatlich' || $frequenz === 'woechentlich' || $frequenz === 'zweiwoechentlich') ? 'block' : 'none'; ?>;">
        <p><label><strong>An welchen Tagen?</strong></label><br>
        <?php foreach ($wochentage as $en => $de): ?>
            <label style="margin-right:10px;">
                <input type="checkbox" name="workshop_wiederholung_tage[]" value="<?php echo $en; ?>" <?php checked(in_array($en, $gespeicherte_tage)); ?>> <?php echo $de; ?>
            </label>
        <?php endforeach; ?>
        </p>
    </div>

    <p id="ws_monat_wrap" style="display: <?php echo ($frequenz === 'monatlich') ? 'block' : 'none'; ?>;">
        <label><strong>Monats-Regel:</strong></label><br>
        <select name="workshop_monat_regelung" class="widefat">
            <option value="erster" <?php selected($monat_regelung, 'erster'); ?>>Jeweils der erste gewählte Wochentag im Monat</option>
            <option value="zweiter" <?php selected($monat_regelung, 'zweiter'); ?>>Jeweils der zweite gewählte Wochentag im Monat</option>
            <option value="dritter" <?php selected($monat_regelung, 'dritter'); ?>>Jeweils der dritter gewählte Wochentag im Monat</option>
            <option value="vierter" <?php selected($monat_regelung, 'vierter'); ?>>Jeweils der vierter gewählte Wochentag im Monat</option>
            <option value="letzter" <?php selected($monat_regelung, 'letzter'); ?>>Jeweils der letzte gewählte Wochentag im Monat</option>
        </select>
    </p>

    <p id="ws_intervall_wrap" style="display: <?php echo ($frequenz === 'monatlich') ? 'block' : 'none'; ?>;">
        <label><strong>Wiederholungs-Intervall:</strong></label><br>
        <select name="workshop_intervall" id="ws_intervall" class="widefat">
            <option value="1" <?php selected($intervall, '1'); ?>>Jeden Monat</option>
            <option value="2" <?php selected($intervall, '2'); ?>>Jeden 2. Monat</option>
            <option value="3" <?php selected($intervall, '3'); ?>>Jeden 3. Monat</option>
            <option value="4" <?php selected($intervall, '4'); ?>>Jeden 4. Monat</option>
            <option value="6" <?php selected($intervall, '6'); ?>>Alle 6 Monate</option>
        </select>
    </p>

    <p>
        <label><strong>Anzahl Termine im Dropdown (Anmeldeformular):</strong></label><br>
        <input type="number" name="workshop_wiederholung_anzahl" value="<?php echo esc_attr($anzahl_termine); ?>" min="1" max="50" step="1" class="widefat">
    </p>

    <script>
    document.getElementById('ws_freq').addEventListener('change', function() {
        var freq = this.value;
        var isTage = (freq === 'monatlich' || freq === 'woechentlich' || freq === 'zweiwoechentlich');
        var isMonat = (freq === 'monatlich');
        document.getElementById('ws_tage_wrap').style.display = isTage ? 'block' : 'none';
        document.getElementById('ws_monat_wrap').style.display = isMonat ? 'block' : 'none';
        document.getElementById('ws_intervall_wrap').style.display = isMonat ? 'block' : 'none';
    });
    </script>
    <?php
}

function micinterart_render_workshop_datum_metabox($post) {
    wp_nonce_field('ws_datum_save', 'ws_datum_nonce');
    $datum = get_post_meta($post->ID, '_workshop_datum', true);
    $von = get_post_meta($post->ID, '_workshop_uhrzeit_von', true);
    $bis = get_post_meta($post->ID, '_workshop_uhrzeit_bis', true);
    ?>
    <p><label>Datum:</label><br><input type="date" name="ws_datum" value="<?php echo esc_attr($datum); ?>" class="widefat"></p>
    <p><label>Von:</label><br><input type="time" name="ws_von" value="<?php echo esc_attr($von); ?>" class="widefat"></p>
    <p><label>Bis:</label><br><input type="time" name="ws_bis" value="<?php echo esc_attr($bis); ?>" class="widefat"></p>
    <?php
}

function micinterart_render_workshop_preis_metabox($post) {
    wp_nonce_field('ws_preis_save', 'ws_preis_nonce');
    $preis = get_post_meta($post->ID, '_workshop_preis', true);
    $info = get_post_meta($post->ID, '_workshop_preis_info', true);
    $inklusiv = get_post_meta($post->ID, '_workshop_preis_inklusiv', true);
    $is_paar = get_post_meta($post->ID, '_workshop_is_paar_preis', true);
    ?>
    <p><label>Preis (€):</label><br><input type="text" name="ws_preis" value="<?php echo esc_attr($preis); ?>" class="widefat"></p>
    <p><label><input type="checkbox" name="workshop_is_paar_preis" value="yes" <?php checked($is_paar, 'yes'); ?>> Dies ist ein Paartarif</label></p>
    <p><label>Zusatz-Info (z.B. "pro Person"):</label><br><input type="text" name="ws_info" value="<?php echo esc_attr($info); ?>" class="widefat"></p>
    <p><label>Inklusive Leistungen:</label><br><textarea name="ws_inklusiv" class="widefat" rows="3"><?php echo esc_textarea($inklusiv); ?></textarea></p>
    <?php
}

function micinterart_render_workshop_status_metabox($post) {
    wp_nonce_field('ws_status_save', 'ws_status_nonce');
    $status = get_post_meta($post->ID, '_workshop_status', true) ?: 'geplant';
    $stati = ['geplant' => 'Geplant', 'anmeldung_offen' => 'Anmeldung offen', 'fast_ausgebucht' => 'Fast ausgebucht', 'ausgebucht' => 'Ausgebucht', 'abgesagt' => 'Abgesagt'];
    ?>
    <select name="ws_status" class="widefat">
        <?php foreach($stati as $k=>$v): ?>
            <option value="<?php echo $k; ?>" <?php selected($status, $k); ?>><?php echo $v; ?></option>
        <?php endforeach; ?>
    </select>
    <?php
}

function micinterart_render_workshop_ort_metabox($post) {
    wp_nonce_field('ws_ort_save', 'ws_ort_nonce');
    $ort = get_post_meta($post->ID, '_workshop_ort', true);
    $adr = get_post_meta($post->ID, '_workshop_adresse', true);
    ?>
    <p><label>Ort Name:</label><br><input type="text" name="ws_ort" value="<?php echo esc_attr($ort); ?>" class="widefat"></p>
    <p><label>Vollständige Adresse:</label><br><textarea name="ws_adr" class="widefat" rows="2"><?php echo esc_textarea($adr); ?></textarea></p>
    <?php
}

function micinterart_render_workshop_teilnehmer_metabox($post) {
    wp_nonce_field('ws_teil_save', 'ws_teil_nonce');
    $max = get_post_meta($post->ID, '_workshop_max_teilnehmer', true);
    ?>
    <input type="number" name="ws_max" value="<?php echo esc_attr($max); ?>" class="widefat">
    <?php
}

function micinterart_render_workshop_alter_metabox($post) {
    wp_nonce_field('ws_alter_save', 'ws_alter_nonce');
    $von = get_post_meta($post->ID, '_workshop_alter_von', true);
    $bis = get_post_meta($post->ID, '_workshop_alter_bis', true);
    ?>
    <p>Von: <input type="number" name="ws_alter_v" value="<?php echo esc_attr($von); ?>" style="width:50px;"> Bis: <input type="number" name="ws_alter_b" value="<?php echo esc_attr($bis); ?>" style="width:50px;"></p>
    <?php
}

function micinterart_render_thema_link_metabox($post) {
    $current_ws = get_post_meta($post->ID, '_thema_workshop_id', true);
    $workshops = get_posts(['post_type'=>'workshop', 'posts_per_page'=>-1]);
    ?>
    <select name="thema_ws_id" class="widefat">
        <option value="">-- Bitte wählen --</option>
        <?php foreach($workshops as $ws): ?>
            <option value="<?php echo $ws->ID; ?>" <?php selected($current_ws, $ws->ID); ?>><?php echo esc_html($ws->post_title); ?></option>
        <?php endforeach; ?>
    </select>
    <?php
}

function micinterart_render_thema_datum_metabox($post) {
    $datum = get_post_meta($post->ID, '_thema_datum', true);
    ?>
    <input type="date" name="thema_datum" value="<?php echo esc_attr($datum); ?>" class="widefat">
    <p class="description">Falls dieses Thema an einem spezifischen Datum stattfindet.</p>
    <?php
}

function micinterart_render_thema_preis_metabox($post) {
    wp_nonce_field('thema_preis_save', 'thema_preis_nonce');
    $preis = get_post_meta($post->ID, '_thema_preis', true);
    $ws_id = get_post_meta($post->ID, '_thema_workshop_id', true);
    $fallback_preis = '';
    
    if ($ws_id) {
        $fallback_preis = get_post_meta($ws_id, '_workshop_preis', true);
    }
    ?>
    <p>
        <label>Eigener Preis (€):</label><br>
        <input type="text" name="thema_preis" value="<?php echo esc_attr($preis); ?>" class="widefat" placeholder="<?php echo $fallback_preis ? 'Standard: ' . esc_attr($fallback_preis) . ' €' : 'Kein Standardpreis'; ?>">
    </p>
    <p class="description">Wird hier nichts eingetragen, gilt der Preis des Haupt-Workshops.</p>
    <?php
}

function micinterart_render_thema_uhrzeit_metabox($post) {
    wp_nonce_field('thema_uhrzeit_save', 'thema_uhrzeit_nonce');
    $von = get_post_meta($post->ID, '_thema_uhrzeit_von', true);
    $bis = get_post_meta($post->ID, '_thema_uhrzeit_bis', true);
    $ws_id = get_post_meta($post->ID, '_thema_workshop_id', true);
    $fallback_von = $ws_id ? get_post_meta($ws_id, '_workshop_uhrzeit_von', true) : '';
    $fallback_bis = $ws_id ? get_post_meta($ws_id, '_workshop_uhrzeit_bis', true) : '';
    ?>
    <p>
        <label>Von:</label><br>
        <input type="time" name="thema_von" value="<?php echo esc_attr($von); ?>" class="widefat">
        <?php if ($fallback_von): ?><small style="color:#666;">Standard: <?php echo esc_html($fallback_von); ?></small><?php endif; ?>
    </p>
    <p>
        <label>Bis:</label><br>
        <input type="time" name="thema_bis" value="<?php echo esc_attr($bis); ?>" class="widefat">
        <?php if ($fallback_bis): ?><small style="color:#666;">Standard: <?php echo esc_html($fallback_bis); ?></small><?php endif; ?>
    </p>
    <p class="description">Leer lassen, um die Zeiten des Haupt-Workshops zu übernehmen.</p>
    <?php
}

function micinterart_render_workshop_sprache_metabox($post) {
    wp_nonce_field('ws_sprache_save', 'ws_sprache_nonce');
    $sprache = get_post_meta($post->ID, '_workshop_sprache', true) ?: 'deutsch';
    ?>
    <p>
        <label><strong>In welcher Sprache findet der Kurs statt?</strong></label><br>
        <select name="ws_sprache" class="widefat">
            <option value="deutsch" <?php selected($sprache, 'deutsch'); ?>>Deutsch</option>
            <option value="russisch" <?php selected($sprache, 'russisch'); ?>>Russisch</option>
        </select>
    </p>
    <p class="description">Diese Information wird im "Was dich erwartet"-Bereich angezeigt.</p>
    <?php
}

// =========================================================================
// SPEICHERN WORKSHOPS
// =========================================================================

add_action('save_post_workshop', function($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['workshop_wiederholung_nonce']) && wp_verify_nonce($_POST['workshop_wiederholung_nonce'], 'workshop_wiederholung_save')) {
        update_post_meta($post_id, '_workshop_wiederholung_frequenz', sanitize_text_field($_POST['workshop_wiederholung_frequenz']));
        $tage = isset($_POST['workshop_wiederholung_tage']) ? (array)$_POST['workshop_wiederholung_tage'] : [];
        update_post_meta($post_id, '_workshop_wiederholung_tage', json_encode(array_map('sanitize_text_field', $tage)));
        if(isset($_POST['workshop_monat_regelung'])) update_post_meta($post_id, '_workshop_monat_regelung', sanitize_text_field($_POST['workshop_monat_regelung']));
        if(isset($_POST['workshop_intervall'])) update_post_meta($post_id, '_workshop_intervall', sanitize_text_field($_POST['workshop_intervall']));
        if(isset($_POST['workshop_wiederholung_anzahl'])) update_post_meta($post_id, '_workshop_wiederholung_anzahl', sanitize_text_field($_POST['workshop_wiederholung_anzahl']));
    }
    
    if (isset($_POST['ws_datum_nonce']) && wp_verify_nonce($_POST['ws_datum_nonce'], 'ws_datum_save')) {
        update_post_meta($post_id, '_workshop_datum', sanitize_text_field($_POST['ws_datum']));
        update_post_meta($post_id, '_workshop_uhrzeit_von', sanitize_text_field($_POST['ws_von']));
        update_post_meta($post_id, '_workshop_uhrzeit_bis', sanitize_text_field($_POST['ws_bis']));
    }
    
    if (isset($_POST['ws_preis_nonce']) && wp_verify_nonce($_POST['ws_preis_nonce'], 'ws_preis_save')) {
        update_post_meta($post_id, '_workshop_preis', sanitize_text_field($_POST['ws_preis']));
        update_post_meta($post_id, '_workshop_preis_info', sanitize_text_field($_POST['ws_info']));
        update_post_meta($post_id, '_workshop_preis_inklusiv', sanitize_textarea_field($_POST['ws_inklusiv']));
        if (isset($_POST['workshop_is_paar_preis']) && $_POST['workshop_is_paar_preis'] === 'yes') {
            update_post_meta($post_id, '_workshop_is_paar_preis', 'yes');
        } else {
            delete_post_meta($post_id, '_workshop_is_paar_preis');
        }
    }
    
    if (isset($_POST['ws_status_nonce']) && wp_verify_nonce($_POST['ws_status_nonce'], 'ws_status_save')) update_post_meta($post_id, '_workshop_status', sanitize_text_field($_POST['ws_status']));
    if (isset($_POST['ws_ort_nonce']) && wp_verify_nonce($_POST['ws_ort_nonce'], 'ws_ort_save')) {
        update_post_meta($post_id, '_workshop_ort', sanitize_text_field($_POST['ws_ort']));
        update_post_meta($post_id, '_workshop_adresse', sanitize_textarea_field($_POST['ws_adr']));
    }
    if (isset($_POST['ws_teil_nonce']) && wp_verify_nonce($_POST['ws_teil_nonce'], 'ws_teil_save')) update_post_meta($post_id, '_workshop_max_teilnehmer', absint($_POST['ws_max']));
    if (isset($_POST['ws_alter_nonce']) && wp_verify_nonce($_POST['ws_alter_nonce'], 'ws_alter_save')) {
        update_post_meta($post_id, '_workshop_alter_von', absint($_POST['ws_alter_v']));
        update_post_meta($post_id, '_workshop_alter_bis', absint($_POST['ws_alter_b']));
    }
    if (isset($_POST['ws_sprache_nonce']) && wp_verify_nonce($_POST['ws_sprache_nonce'], 'ws_sprache_save')) {
    update_post_meta($post_id, '_workshop_sprache', sanitize_text_field($_POST['ws_sprache']));
    }

});

add_action('save_post_workshop_thema', function($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (isset($_POST['thema_ws_id'])) update_post_meta($post_id, '_thema_workshop_id', sanitize_text_field($_POST['thema_ws_id']));
    if (isset($_POST['thema_datum'])) update_post_meta($post_id, '_thema_datum', sanitize_text_field($_POST['thema_datum']));
    if (isset($_POST['thema_preis_nonce']) && wp_verify_nonce($_POST['thema_preis_nonce'], 'thema_preis_save')) {
        update_post_meta($post_id, '_thema_preis', sanitize_text_field($_POST['thema_preis']));
    }
    if (isset($_POST['thema_uhrzeit_nonce']) && wp_verify_nonce($_POST['thema_uhrzeit_nonce'], 'thema_uhrzeit_save')) {
        update_post_meta($post_id, '_thema_uhrzeit_von', sanitize_text_field($_POST['thema_von']));
        update_post_meta($post_id, '_thema_uhrzeit_bis', sanitize_text_field($_POST['thema_bis']));
    }
});

add_filter('manage_workshop_thema_posts_columns', function($columns) {
    $columns['workshop'] = 'Zugehöriger Workshop';
    $columns['datum'] = 'Fixes Datum';
    return $columns;
});

add_action('manage_workshop_thema_posts_custom_column', function($column, $post_id) {
    switch ($column) {
        case 'workshop':
            $ws_id = get_post_meta($post_id, '_thema_workshop_id', true);
            if ($ws_id) {
                $ws = get_post($ws_id);
                if ($ws) { echo '<a href="' . get_edit_post_link($ws_id) . '">' . esc_html($ws->post_title) . '</a>'; }
            } else { echo '<span style="color:#999;">—</span>'; }
            break;
        case 'datum':
            $datum = get_post_meta($post_id, '_thema_datum', true);
            if ($datum) {
                $date_obj = new DateTime($datum);
                echo '<strong>' . date_i18n('l, d. F Y', $date_obj->getTimestamp()) . '</strong>';
            } else { echo '<span style="color:#999;">—</span>'; }
            break;
    }
}, 10, 2);

add_action('edit_form_after_title', function($post) {
    if ($post->post_type !== 'workshop_thema') return;
    ?>
    <div style="background:#e7f3ff; border-left:4px solid #2271b1; padding:15px; margin:20px 0; border-radius:4px;">
        <p style="margin:0; line-height:1.5;"><strong>Hinweis:</strong> Dieses Thema wird automatisch dem ausgewählten Workshop zugeordnet und erscheint im Kalender entweder am fixen Datum oder rollierend im Rhythmus des Haupt-Workshops.</p>
    </div>
    <?php
});
