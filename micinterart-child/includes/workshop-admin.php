<?php
/**
 * Workshop CPT: Admin-Metabox "Was dich erwartet" – Anpassbare Felder
 * Gilt für CPT "workshop" UND "workshop_thema"
 *
 * @package Micinterart
 */

// ============================================================================
// WORKSHOP "WAS DICH ERWARTET" – ANPASSBARE FELDER (Metabox im Admin)
// ============================================================================

add_action('add_meta_boxes', 'micinterart_add_erwartet_metabox');

function micinterart_add_erwartet_metabox() {
    // Metabox auf "workshop" UND "workshop_thema" registrieren
    foreach (['workshop', 'workshop_thema'] as $post_type) {
        add_meta_box(
            'workshop_erwartet_box',
            '✨ Was dich erwartet – Anpassbare Felder',
            'micinterart_render_erwartet_metabox',
            $post_type,
            'normal',
            'default'
        );
    }
}

function micinterart_render_erwartet_metabox($post) {
    wp_nonce_field('micinterart_erwartet_save', 'micinterart_erwartet_nonce');

    // Felder 1, 3, 4, 5: anpassbar (Emoji, Titel, Text)
    // Feld 2 (Kleine Gruppen):  hardcoded, zieht max. Teilnehmer automatisch
    // Feld 6 (Parkplätze):      automatisch per Ort-Logik
    // Feld 7 (Kurssprache):     aus _workshop_sprache

    $felder = [
        1 => [
            'label'         => '🎨 Feld 1 – Materialien',
            'emoji_default' => '🎨',
            'titel_default' => 'Alle Materialien inklusive',
            'text_default'  => 'Du brauchst nichts mitzubringen – alles ist vorbereitet',
        ],
        3 => [
            'label'         => '🎓 Feld 3 – Vorkenntnisse',
            'emoji_default' => '🎓',
            'titel_default' => 'Keine Vorkenntnisse nötig',
            'text_default'  => 'Ich begleite dich Schritt für Schritt',
        ],
        4 => [
            'label'         => '🖼️ Feld 4 – Kunstwerk',
            'emoji_default' => '🖼️',
            'titel_default' => 'Dein fertiges Kunstwerk',
            'text_default'  => 'Zum Mitnehmen und stolz nach Hause tragen',
        ],
        5 => [
            'label'         => '☕ Feld 5 – Inklusive',
            'emoji_default' => '☕',
            'titel_default' => 'Inklusive:',
            'text_default'  => 'Kaffee, Tee, Wasser und kleine Leckereien',
        ],
    ];

    // Hinweis je nach CPT anpassen
    $is_thema = ($post->post_type === 'workshop_thema');
    $hinweis_extra = $is_thema
        ? 'Felder hier befüllen überschreibt die Einstellungen des übergeordneten Workshops.'
        : 'Felder leer lassen = Standard-Text wird verwendet. Themen können die Felder individuell überschreiben.';

    echo '<p style="color:#666;font-size:0.9em;margin-bottom:0;">
        <strong>Hinweis:</strong> ' . $hinweis_extra . '<br>
        <em>Feld 2 (Kleine Gruppen)</em> und <em>Feld 7 (Kurssprache)</em> sind vollautomatisch.<br>
        <em>Feld 6 (Parkplätze)</em> passt sich automatisch an den Workshop-Ort an.
    </p>';

    echo '<table class="form-table" style="margin-top:10px;">';

    foreach ($felder as $nr => $feld) {
        $emoji = get_post_meta($post->ID, "_workshop_erwartet_{$nr}_emoji", true);
        $titel = get_post_meta($post->ID, "_workshop_erwartet_{$nr}_titel", true);
        $text  = get_post_meta($post->ID, "_workshop_erwartet_{$nr}_text",  true);

        echo '<tr><td colspan="2" style="padding-top:20px;padding-bottom:2px;border-top:1px solid #f0f0f0;">
            <strong>' . esc_html($feld['label']) . '</strong>
            <span style="color:#aaa;font-size:0.85em;margin-left:8px;">
                Standard-Titel: „' . esc_html($feld['titel_default']) . '"
            </span>
        </td></tr>';

        echo '<tr>
            <th style="width:130px;vertical-align:middle;">Emoji</th>
            <td><input type="text" name="_workshop_erwartet_' . $nr . '_emoji"
                value="' . esc_attr($emoji) . '"
                placeholder="' . esc_attr($feld['emoji_default']) . '"
                style="width:80px;" /></td>
        </tr>';

        echo '<tr>
            <th style="vertical-align:middle;">Titel</th>
            <td><input type="text" name="_workshop_erwartet_' . $nr . '_titel"
                value="' . esc_attr($titel) . '"
                placeholder="' . esc_attr($feld['titel_default']) . '"
                style="width:100%;max-width:500px;" /></td>
        </tr>';

        echo '<tr>
            <th style="vertical-align:middle;">Beschreibung</th>
            <td><input type="text" name="_workshop_erwartet_' . $nr . '_text"
                value="' . esc_attr($text) . '"
                placeholder="' . esc_attr($feld['text_default']) . '"
                style="width:100%;max-width:500px;" /></td>
        </tr>';
    }

    echo '</table>';
}

// Save für beide CPTs
add_action('save_post_workshop',       'micinterart_save_erwartet_metabox');
add_action('save_post_workshop_thema', 'micinterart_save_erwartet_metabox');

function micinterart_save_erwartet_metabox($post_id) {
    if (!isset($_POST['micinterart_erwartet_nonce'])) return;
    if (!wp_verify_nonce($_POST['micinterart_erwartet_nonce'], 'micinterart_erwartet_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    foreach ([1, 3, 4, 5] as $nr) {
        foreach (['emoji', 'titel', 'text'] as $key) {
            $meta_key = "_workshop_erwartet_{$nr}_{$key}";
            if (isset($_POST[$meta_key])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$meta_key]));
            }
        }
    }
}
