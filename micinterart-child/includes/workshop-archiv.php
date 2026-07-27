<?php
/**
 * Workshop: Auto-Archivierung, Admin-Spalten, Kinder-Validierung
 *
 * @package Micinterart
 */

// ============================================================================
// AUTOMATISCHES ARCHIVIEREN VERGANGENER WORKSHOPS
// ============================================================================

/**
 * Markiert vergangene Workshops automatisch als "beendet"
 */
function micinterart_auto_archive_past_workshops() {
    $args = [
        'post_type' => 'workshop',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => '_workshop_datum',
                'value' => date('Y-m-d'),
                'compare' => '<',
                'type' => 'DATE'
            ],
            [
                'key' => '_workshop_status',
                'value' => 'beendet',
                'compare' => '!='
            ]
        ]
    ];
    
    $past_workshops = new WP_Query($args);
    
    if ($past_workshops->have_posts()) {
        while ($past_workshops->have_posts()) {
            $past_workshops->the_post();
            $post_id = get_the_ID();
            
            // Nur wenn das Datum wirklich in der Vergangenheit liegt
            $datum = get_post_meta($post_id, '_workshop_datum', true);
            if ($datum && strtotime($datum) < strtotime('today')) {
                update_post_meta($post_id, '_workshop_status', 'beendet');
                
                // Optional: Log für Debugging
                error_log("Workshop #{$post_id} automatisch auf 'beendet' gesetzt");
            }
        }
    }
    wp_reset_postdata();
}

// Täglich automatisch prüfen (nutzt WordPress eigenen Cron)
add_action('wp_scheduled_delete', 'micinterart_auto_archive_past_workshops');

// Auch bei jedem Seitenaufruf im Admin prüfen (nicht zu oft!)
add_action('admin_init', function() {
    // Nur einmal pro Tag prüfen
    $last_check = get_option('micinterart_last_archive_check');
    if (!$last_check || $last_check < strtotime('today')) {
        micinterart_auto_archive_past_workshops();
        update_option('micinterart_last_archive_check', time());
    }
});

/**
 * Admin-Notice wenn Workshops automatisch archiviert wurden
 */
add_action('admin_notices', function() {
    if (get_transient('micinterart_workshops_archived')) {
        $count = get_transient('micinterart_workshops_archived');
        ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>✓ <?php echo $count; ?> Workshop(s) wurden automatisch archiviert.</strong></p>
        </div>
        <?php
        delete_transient('micinterart_workshops_archived');
    }
});

/**
 * Manueller "Jetzt archivieren" Button im Admin
 */
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=workshop',
        'Workshops archivieren',
        '📁 Archivieren',
        'manage_options',
        'archive-workshops',
        'micinterart_archive_workshops_page'
    );
});

function micinterart_archive_workshops_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Wenn Button geklickt wurde
    if (isset($_POST['archive_now']) && check_admin_referer('archive_workshops')) {
        micinterart_auto_archive_past_workshops();
        echo '<div class="notice notice-success"><p><strong>✓ Vergangene Workshops wurden archiviert!</strong></p></div>';
    }
    
    // Zähle vergangene Workshops die noch nicht archiviert sind
    $args = [
        'post_type' => 'workshop',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => '_workshop_datum',
                'value' => date('Y-m-d'),
                'compare' => '<',
                'type' => 'DATE'
            ],
            [
                'key' => '_workshop_status',
                'value' => 'beendet',
                'compare' => '!='
            ]
        ]
    ];
    
    $past_workshops = new WP_Query($args);
    $count = $past_workshops->found_posts;
    
    ?>
    <div class="wrap">
        <h1>📁 Workshops archivieren</h1>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Automatisches Archivieren</h2>
            <p>Workshops werden automatisch als "beendet" markiert, wenn ihr Datum in der Vergangenheit liegt.</p>
            
            <?php if ($count > 0) : ?>
                <div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;">
                    <p style="margin: 0;">
                        <strong>⚠️ Es gibt <?php echo $count; ?> vergangene Workshop(s), die noch nicht archiviert wurden.</strong>
                    </p>
                </div>
                
                <form method="post">
                    <?php wp_nonce_field('archive_workshops'); ?>
                    <button type="submit" name="archive_now" class="button button-primary button-large">
                        📁 Jetzt archivieren
                    </button>
                </form>
            <?php else : ?>
                <div style="background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0;">
                    <p style="margin: 0;">
                        <strong>✓ Alle vergangenen Workshops sind bereits archiviert!</strong>
                    </p>
                </div>
            <?php endif; ?>
            
            <hr style="margin: 30px 0;">
            
            <h3>Wie funktioniert es?</h3>
            <ul style="line-height: 1.8;">
                <li><strong>Automatisch:</strong> Workshops werden täglich automatisch geprüft</li>
                <li><strong>Status:</strong> Vergangene Workshops erhalten den Status "beendet"</li>
                <li><strong>Sichtbarkeit:</strong> Auf der Workshop-Seite werden sie in einer separaten Archiv-Sektion angezeigt</li>
                <li><strong>Manuell:</strong> Du kannst jederzeit mit dem Button oben manuell archivieren</li>
            </ul>
        </div>
    </div>
    <?php
}

/**
 * Admin-Spalte: Zeigt "VORBEI" bei vergangenen Workshops
 */
add_filter('manage_workshop_posts_columns', function($columns) {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['workshop_zeitstatus'] = '⏰ Zeitstatus';
        }
    }
    return $new_columns;
});

add_action('manage_workshop_posts_custom_column', function($column, $post_id) {
    if ($column === 'workshop_zeitstatus') {
        $datum = get_post_meta($post_id, '_workshop_datum', true);
        if ($datum) {
            if (strtotime($datum) < strtotime('today')) {
                echo '<span style="background: #ffebee; color: #d32f2f; padding: 4px 8px; border-radius: 3px; font-weight: 600; font-size: 0.85em;">VORBEI</span>';
            } else {
                echo '<span style="background: #e8f5e9; color: #388e3c; padding: 4px 8px; border-radius: 3px; font-weight: 600; font-size: 0.85em;">KOMMEND</span>';
            }
        }
    }
}, 10, 2);

/**
 * Passt die Fehlermeldung an, wenn die Anzahl der Kinder das Workshop-Limit überschreitet.
 */
add_filter('wpcf7_validate_number*', 'custom_kinder_anzahl_validation', 20, 2);
add_filter('wpcf7_validate_number', 'custom_kinder_anzahl_validation', 20, 2);

function custom_kinder_anzahl_validation($result, $tag) {
    if ($tag->name === 'kinder-anzahl') {
        $value = isset($_POST[$tag->name]) ? intval($_POST[$tag->name]) : 0;
        $post_id = isset($_POST['_wpcf7_container_post']) ? intval($_POST['_wpcf7_container_post']) : get_the_ID();

        if ($post_id) {
            // Holen des Limits aus deinem Plugin
            $max_participants = get_post_meta($post_id, '_workshop_max_teilnehmer', true);

            if (!empty($max_participants) && $value > $max_participants) {
                $result->invalidate($tag, "Entschuldigung, für diesen Workshop sind nur noch maximal " . $max_participants . " Plätze verfügbar.");
            }
        }
    }
    return $result;
}

/**
 * Workshop Rabatt-System - KOMPLETT MIT PAYPAL
 * Füge diesen Code in deine functions.php ein (OHNE <?php am Anfang!)
 * 
 * Version: 2.0 (mit PayPal-Integration)
 * Autor: Entwickelt für Micinterart
 * 
 * INSTALLATION:
 * 1. Code komplett kopieren (ohne <?php am Anfang)
 * 2. In functions.php am Ende einfügen
 * 3. PayPal aktivieren (optional):
 *    define('MICINTERART_PAYPAL_EMAIL', 'deine@paypal-email.de');
 * 4. CF7 E-Mail anpassen mit: [rabatt_info] und [paypal_link]
 */
