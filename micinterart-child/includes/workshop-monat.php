<?php
/**
 * Workshop-Monat CPT: Registrierung, Metaboxen, Admin-Spalten
 *
 * @package Micinterart
 */

// ============================================================================
// WORKSHOP-MONAT CPT
// ============================================================================

function micinterart_register_workshop_monat_cpt() {
    register_post_type('workshop_monat',[
        'labels'=>['name'=>'Workshop-Monate','singular_name'=>'Workshop-Monat','add_new'=>'Neuer Monat','add_new_item'=>'Neuen Workshop-Monat hinzufügen'],
        'public'=>true,'show_ui'=>true,'show_in_menu'=>'edit.php?post_type=workshop','supports'=>['title','editor','thumbnail'],'show_in_rest'=>true
    ]);
}
add_action('init', 'micinterart_register_workshop_monat_cpt');

function micinterart_workshop_monat_meta_boxes() {
    add_meta_box('workshop_monat_details','Monats-Details','micinterart_workshop_monat_meta_box_callback','workshop_monat','normal','high');
}
add_action('add_meta_boxes', 'micinterart_workshop_monat_meta_boxes');

function micinterart_workshop_monat_meta_box_callback($post) {
    wp_nonce_field('workshop_monat_meta_box','workshop_monat_meta_box_nonce');
    $monat = get_post_meta($post->ID,'_workshop_monat_monat',true);
    $jahr = get_post_meta($post->ID,'_workshop_monat_jahr',true) ?: date('Y');
    $thema = get_post_meta($post->ID,'_workshop_monat_thema',true);
    $farbe = get_post_meta($post->ID,'_workshop_monat_farbe',true) ?: '#2c2c2c';
    ?>
    <style>.workshop-monat-meta-box{display:grid;grid-template-columns:200px 1fr;gap:20px;}</style>
    <div class="workshop-monat-meta-box">
        <label>Monat *</label>
        <select name="workshop_monat_monat" required>
            <option value="">-- Monat wählen --</option>
            <?php foreach(['01'=>'Januar','02'=>'Februar','03'=>'März','04'=>'April','05'=>'Mai','06'=>'Juni','07'=>'Juli','08'=>'August','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Dezember'] as $num=>$name): ?>
                <option value="<?php echo $num; ?>" <?php selected($monat,$num); ?>><?php echo $name; ?></option>
            <?php endforeach; ?>
        </select>
        <label>Jahr *</label>
        <input type="number" name="workshop_monat_jahr" value="<?php echo esc_attr($jahr); ?>" min="2024" max="2030" required>
        <label>Monats-Thema *</label>
        <input type="text" name="workshop_monat_thema" value="<?php echo esc_attr($thema); ?>" required>
        <label>Themen-Farbe</label>
        <input type="color" name="workshop_monat_farbe" value="<?php echo esc_attr($farbe); ?>">
    </div>
    <?php
}

function micinterart_save_workshop_monat_meta($post_id) {
    if (!isset($_POST['workshop_monat_meta_box_nonce']) || !wp_verify_nonce($_POST['workshop_monat_meta_box_nonce'],'workshop_monat_meta_box')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post',$post_id)) return;
    
    foreach(['monat','jahr','thema','farbe'] as $key) {
        if (isset($_POST['workshop_monat_'.$key])) {
            update_post_meta($post_id,'_workshop_monat_'.$key,sanitize_text_field($_POST['workshop_monat_'.$key]));
        }
    }
}
add_action('save_post_workshop_monat', 'micinterart_save_workshop_monat_meta');

function micinterart_workshop_monat_columns($columns) {
    $new = [];
    foreach ($columns as $k=>$v) {
        if ($k==='title') $new['thumbnail']='Bild';
        $new[$k]=$v;
        if ($k==='title') {
            $new['monat_jahr']='Monat/Jahr';
            $new['thema']='Thema';
        }
    }
    unset($new['date']);
    return $new;
}
add_filter('manage_workshop_monat_posts_columns', 'micinterart_workshop_monat_columns');

function micinterart_workshop_monat_column_content($column, $post_id) {
    if ($column==='thumbnail') {
        echo has_post_thumbnail($post_id) ? get_the_post_thumbnail($post_id,[50,50]) : '📅';
    } elseif ($column==='monat_jahr') {
        $m = get_post_meta($post_id,'_workshop_monat_monat',true);
        $j = get_post_meta($post_id,'_workshop_monat_jahr',true);
        if ($m && $j) {
            $monate=['01'=>'Januar','02'=>'Februar','03'=>'März','04'=>'April','05'=>'Mai','06'=>'Juni','07'=>'Juli','08'=>'August','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Dezember'];
            echo '<strong>'.$monate[$m].' '.$j.'</strong>';
        }
    } elseif ($column==='thema') {
        $t = get_post_meta($post_id,'_workshop_monat_thema',true);
        $f = get_post_meta($post_id,'_workshop_monat_farbe',true);
        if ($t) echo $f ? '<span style="padding:4px 10px;background:'.$f.';color:#fff;border-radius:4px;">'.$t.'</span>' : '<strong>'.$t.'</strong>';
    }
}
add_action('manage_workshop_monat_posts_custom_column', 'micinterart_workshop_monat_column_content', 10, 2);

function micinterart_workshop_wochentag_callback($post) {
    wp_nonce_field('workshop_wochentag','workshop_wochentag_nonce');
    $wochentag = get_post_meta($post->ID,'_workshop_wochentag',true);
    $uhrzeit = get_post_meta($post->ID,'_workshop_wochentag_uhrzeit',true);
    $frequenz = get_post_meta($post->ID,'_workshop_wiederholung_frequenz',true);
    $anzahl_termine = get_post_meta($post->ID, '_workshop_wiederholung_anzahl', true) ?: '10';
    ?>
    <select name="workshop_wochentag" style="width:100%;margin-bottom:10px;">
        <option value="">-- Kein Wochentag --</option>
        <?php foreach(['montag'=>'Montag','dienstag'=>'Dienstag','mittwoch'=>'Mittwoch','donnerstag'=>'Donnerstag','freitag'=>'Freitag','samstag'=>'Samstag','sonntag'=>'Sonntag'] as $k=>$v): ?>
            <option value="<?php echo $k; ?>" <?php selected($wochentag,$k); ?>><?php echo $v; ?></option>
        <?php endforeach; ?>
    </select>
    <input type="text" name="workshop_wochentag_uhrzeit" value="<?php echo esc_attr($uhrzeit); ?>" placeholder="z.B. 18:00 Uhr" style="width:100%;">

    <p style="margin-top:15px;margin-bottom:5px;"><strong>Wiederholungs-Frequenz:</strong></p>
    <select name="workshop_wiederholung_frequenz" style="width:100%;">
        <option value="">-- Einmalig (keine Wiederholung) --</option>
        <option value="woechentlich" <?php selected($frequenz, 'woechentlich'); ?>>Wöchentlich</option>
        <option value="zweiwoechentlich" <?php selected($frequenz, 'zweiwoechentlich'); ?>>Zweiwöchentlich</option>
        <option value="monatlich" <?php selected($frequenz, 'monatlich'); ?>>Monatlich</option>
    </select>

    <p style="margin-top:15px;margin-bottom:5px;"><strong>Anzahl Termine im Dropdown:</strong></p>
    <input type="number" name="_workshop_wiederholung_anzahl" value="<?php echo esc_attr($anzahl_termine); ?>" min="1" max="50" step="1" class="widefat">
    <p class="description">Wie viele zukünftige Termine sollen im Formular zur Auswahl stehen? (Standard: 10)</p>

    <?php
}

function micinterart_save_workshop_wochentag($post_id) {
    if (!isset($_POST['workshop_wochentag_nonce']) || !wp_verify_nonce($_POST['workshop_wochentag_nonce'],'workshop_wochentag')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post',$post_id)) return;
    
    $wochentag = sanitize_text_field($_POST['workshop_wochentag'] ?? '');
    $uhrzeit = sanitize_text_field($_POST['workshop_wochentag_uhrzeit'] ?? '');
    $frequenz = sanitize_text_field($_POST['workshop_wiederholung_frequenz'] ?? '');
    $anzahl = sanitize_text_field($_POST['_workshop_wiederholung_anzahl'] ?? '');
    
    empty($wochentag) ? delete_post_meta($post_id, '_workshop_wochentag') : update_post_meta($post_id, '_workshop_wochentag', $wochentag);
    empty($uhrzeit) ? delete_post_meta($post_id, '_workshop_wochentag_uhrzeit') : update_post_meta($post_id, '_workshop_wochentag_uhrzeit', $uhrzeit);
    empty($frequenz) ? delete_post_meta($post_id, '_workshop_wiederholung_frequenz') : update_post_meta($post_id, '_workshop_wiederholung_frequenz', $frequenz);
    empty($anzahl) ? delete_post_meta($post_id, '_workshop_wiederholung_anzahl') : update_post_meta($post_id, '_workshop_wiederholung_anzahl', $anzahl);
}
add_action('save_post_workshop', 'micinterart_save_workshop_wochentag');

function micinterart_get_workshop_monat_data($monat_id) {
    if (empty($monat_id)) return null;
    $monate=['01'=>'Januar','02'=>'Februar','03'=>'März','04'=>'April','05'=>'Mai','06'=>'Juni','07'=>'Juli','08'=>'August','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Dezember'];
    return [
        'id'=>$monat_id,
        'monat'=>get_post_meta($monat_id,'_workshop_monat_monat',true),
        'jahr'=>get_post_meta($monat_id,'_workshop_monat_jahr',true),
        'monat_name'=>$monate[get_post_meta($monat_id,'_workshop_monat_monat',true)]??'',
        'thema'=>get_post_meta($monat_id,'_workshop_monat_thema',true),
        'farbe'=>get_post_meta($monat_id,'_workshop_monat_farbe',true),
        'post'=>get_post($monat_id)
    ];
}
