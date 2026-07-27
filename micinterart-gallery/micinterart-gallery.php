<?php
/**
 * Plugin Name: Micinterart Gallery
 * Description: Galerie-Lösung mit CPT "Werk", "Gedicht" und "Workshop", Taxonomie "Serie", erweiterten Metaboxen und Lightbox
 * Version: 2.4.5
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

    private const VERSION = '2.4.5';
    private static $instance = null;

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
        add_action('save_post_gedicht', [$this, 'save_gedicht_meta']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        
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
        $jahr = get_post_meta($post->ID, '_werk_jahr', true);
        $technik = get_post_meta($post->ID, '_werk_technik', true);
        $format = get_post_meta($post->ID, '_werk_format', true);
        $preis = get_post_meta($post->ID, '_werk_preis', true);
        $status = get_post_meta($post->ID, '_werk_status', true) ?: 'verfuegbar';
        $represented = get_post_meta($post->ID, '_werk_represented', true);
        ?>
        <p><label>Jahr:</label><br><input type="text" name="werk_jahr" value="<?php echo esc_attr($jahr); ?>" class="widefat"></p>
        <p><label>Technik:</label><br><input type="text" name="werk_technik" value="<?php echo esc_attr($technik); ?>" class="widefat"></p>
        <p><label>Format:</label><br><input type="text" name="werk_format" value="<?php echo esc_attr($format); ?>" class="widefat"></p>
        <p><label>Preis (€):</label><br><input type="text" name="werk_preis" value="<?php echo esc_attr($preis); ?>" class="widefat"></p>
        <p><label>Status:</label><br>
        <select name="werk_status" class="widefat">
            <option value="verfuegbar" <?php selected($status, 'verfuegbar'); ?>>Verfügbar</option>
            <option value="reserviert" <?php selected($status, 'reserviert'); ?>>Reserviert</option>
            <option value="verkauft" <?php selected($status, 'verkauft'); ?>>Verkauft</option>
            <option value="privatbesitz" <?php selected($status, 'privatbesitz'); ?>>Privatbesitz</option>
        </select></p>
        <p><label><input type="checkbox" name="werk_represented" value="yes" <?php checked($represented, 'yes'); ?>> Vertreten durch Galerie Helligkeit (Represented by Galerie Helligkeit)</label></p>
        <?php
    }

    public function save_werk_meta($post_id) {
        if (!isset($_POST['werk_meta_nonce']) || !wp_verify_nonce($_POST['werk_meta_nonce'], 'werk_meta_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        $fields = ['_werk_jahr'=>'werk_jahr', '_werk_technik'=>'werk_technik', '_werk_format'=>'werk_format', '_werk_preis'=>'werk_preis', '_werk_status'=>'werk_status'];
        foreach($fields as $meta_key => $post_key) {
            if(isset($_POST[$post_key])) update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$post_key]));
        }

        if (isset($_POST['werk_represented']) && $_POST['werk_represented'] === 'yes') {
            update_post_meta($post_id, '_werk_represented', 'yes');
        } else {
            delete_post_meta($post_id, '_werk_represented');
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
        $jahr = get_post_meta($post_id, '_werk_jahr', true);
        $technik = get_post_meta($post_id, '_werk_technik', true);
        $format = get_post_meta($post_id, '_werk_format', true);
        $img = get_the_post_thumbnail_url($post_id, 'large');
        ob_start(); ?>
        <div class="mic-lightbox-content">
            <div class="mic-lightbox-image"><img src="<?php echo $img; ?>" alt="<?php echo esc_attr($post->post_title); ?> – Kunstwerk von Micaella Cervinscaia, micinterart Morsbach"></div>
            <div class="mic-lightbox-info">
                <h2><?php echo esc_html($post->post_title); ?></h2>
                <p><strong>Jahr:</strong> <?php echo esc_html($jahr); ?></p>
                <p><strong>Technik:</strong> <?php echo esc_html($technik); ?></p>
                <p><strong>Format:</strong> <?php echo esc_html($format); ?></p>
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