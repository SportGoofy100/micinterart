<?php
/**
 * Template für einzelnen Workshop
 * 
 * @package Micinterart
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
$is_en = micinterart_is_english();
?>
<?php
/**
 * Hilfsfunktion: "Was dich erwartet"-Box für ein Thema rendern.
 * Felder werden zuerst aus Thema gelesen, Fallback auf übergeordneten Workshop, dann Defaults.
 *
 * @param int $thema_id    Post-ID des workshop_thema
 * @param int $workshop_id Post-ID des übergeordneten Workshop
 */
if (!function_exists('micinterart_render_thema_erwartet_box')) {
function micinterart_render_thema_erwartet_box($thema_id, $workshop_id) {
    global $is_en;

    // Hilfsfunktion: Thema → Workshop → Default
    $get_f = function($nr, $key, $default) use ($thema_id, $workshop_id) {
        $val = get_post_meta($thema_id, "_workshop_erwartet_{$nr}_{$key}", true);
        if (!empty($val)) return $val;
        $val = get_post_meta($workshop_id, "_workshop_erwartet_{$nr}_{$key}", true);
        return !empty($val) ? $val : $default;
    };

    $max_teilnehmer = get_post_meta($thema_id, '_thema_max_teilnehmer', true)
                   ?: get_post_meta($workshop_id, '_workshop_max_teilnehmer', true)
                   ?: 8;

    $sprache      = get_post_meta($workshop_id, '_workshop_sprache', true) ?: 'deutsch';
    $sprache_text = ($sprache === 'russisch') ? 'Russisch' : 'Deutsch';

    $ort = get_post_meta($thema_id, '_thema_ort', true)
        ?: get_post_meta($workshop_id, '_workshop_ort', true);
    $parkplatz_text = (empty($ort) || stripos($ort, 'morsbach') !== false)
        ? 'Direkt vor dem Atelier in Morsbach'
        : 'Bitte informiere dich vorab über die Parkmöglichkeiten vor Ort.';

    // Feld 8 (optional): Freifeld (Emoji+Titel+Text)
    $feld8_emoji = $get_f(8, 'emoji', '📝');
    $feld8_titel = $get_f(8, 'titel', '');
    $feld8_text  = $get_f(8, 'text', '');
    $feld8_has_content = (trim((string) $feld8_titel) !== '') || (trim((string) $feld8_text) !== '');

    $preis_inklusiv = get_post_meta($thema_id, '_workshop_erwartet_5_text', true)
                   ?: get_post_meta($workshop_id, '_workshop_erwartet_5_text', true)
                   ?: get_post_meta($workshop_id, '_workshop_preis_inklusiv', true)
                   ?: '';
    ?>
    
    <div style="background:#f9f9f9; border-left:5px solid #d4a574; border-radius:8px; padding:20px; margin-top:15px;">
        <strong style="font-size:1em; color:#2c2c2c; display:block; margin-bottom:12px;">✨ <?php echo esc_html($is_en ? 'What to expect' : 'Was dich erwartet'); ?></strong>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:12px;">

            <div style="display:flex;align-items:start;gap:8px;">
                <span><?php echo esc_html($get_f(1,'emoji','🎨')); ?></span>
                <div style="font-size:0.9em;">
                    <strong><?php echo esc_html($get_f(1,'titel','Alle Materialien inklusive')); ?></strong><br>
                    <span style="color:#666;"><?php echo esc_html($get_f(1,'text','Du brauchst nichts mitzubringen – alles ist vorbereitet')); ?></span>
                </div>
            </div>

            <div style="display:flex;align-items:start;gap:8px;">
                <span>👥</span>
                <div style="font-size:0.9em;">
                    <strong><?php echo esc_html($is_en ? 'Small groups' : 'Kleine Gruppen'); ?></strong><br>
                    <span style="color:#666;"><?php echo esc_html($is_en ? 'Max. ' . $max_teilnehmer . ' participants – personal guidance guaranteed' : 'Max. ' . $max_teilnehmer . ' Teilnehmer – persönliche Betreuung garantiert'); ?></span>
                </div>
            </div>

            <div style="display:flex;align-items:start;gap:8px;">
                <span><?php echo esc_html($get_f(3,'emoji','🎓')); ?></span>
                <div style="font-size:0.9em;">
                    <strong><?php echo esc_html($get_f(3,'titel','Keine Vorkenntnisse nötig')); ?></strong><br>
                    <span style="color:#666;"><?php echo esc_html($get_f(3,'text','Ich begleite dich Schritt für Schritt')); ?></span>
                </div>
            </div>

            <div style="display:flex;align-items:start;gap:8px;">
                <span><?php echo esc_html($get_f(4,'emoji','🖼️')); ?></span>
                <div style="font-size:0.9em;">
                    <strong><?php echo esc_html($get_f(4,'titel','Dein fertiges Kunstwerk')); ?></strong><br>
                    <span style="color:#666;"><?php echo esc_html($get_f(4,'text','Zum Mitnehmen und stolz nach Hause tragen')); ?></span>
                </div>
            </div>

            <div style="display:flex;align-items:start;gap:8px;">
                <span><?php echo esc_html($get_f(5,'emoji','☕')); ?></span>
                <div style="font-size:0.9em;">
                    <strong><?php echo esc_html($get_f(5,'titel','Inklusive:')); ?></strong><br>
                    <span style="color:#666;"><?php echo !empty($preis_inklusiv) ? esc_html($preis_inklusiv) : 'Kaffee, Tee, Wasser und kleine Leckereien'; ?></span>
                </div>
            </div>

            <div style="display:flex;align-items:start;gap:8px;">
                <span>🚗</span>
                <div style="font-size:0.9em;">
                    <strong><?php echo esc_html($is_en ? 'Free parking' : 'Kostenlose Parkplätze'); ?></strong><br>
                    <span style="color:#666;"><?php echo esc_html($parkplatz_text); ?></span>
                </div>
            </div>

            <?php if ($feld8_has_content) : ?>
                <div style="display:flex;align-items:start;gap:8px;">
                    <span><?php echo esc_html($feld8_emoji); ?></span>
                    <div style="font-size:0.9em;">
                        <?php if (trim((string)$feld8_titel) !== '') : ?>
                            <strong><?php echo esc_html($feld8_titel); ?></strong><br>
                        <?php endif; ?>
                        <?php if (trim((string)$feld8_text) !== '') : ?>
                            <span style="color:#666;"><?php echo esc_html($feld8_text); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
    <?php
}
}
?>



<style>
/* Single Workshop Styling */
.workshop-single-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 60px 20px;
}

.workshop-single-header {
    text-align: center;
    margin-bottom: 40px;
}

.workshop-single-title {
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    font-size: 2.8em;
    margin: 0 0 20px 0;
    letter-spacing: 2px;
}

.workshop-single-status {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 1em;
    font-weight: 600;
    margin-bottom: 20px;
}

.status-geplant { background: #e3f2fd; color: #1976d2; }
.status-anmeldung_offen { background: #e8f5e9; color: #388e3c; }
.status-fast_ausgebucht { background: #fff3e0; color: #f57c00; }
.status-ausgebucht { background: #ffebee; color: #d32f2f; }
.status-beendet { background: #f5f5f5; color: #666; }
.status-abgesagt { background: #fce4ec; color: #c2185b; }

.workshop-featured-image {
    margin: 30px 0;
    text-align: center;
}

.workshop-featured-image img {
    max-width: 100%;
    height: auto;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.workshop-info-box {
    background: #f5f5f5;
    padding: 30px;
    border-radius: 12px;
    margin: 30px 0;
}

.workshop-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.workshop-info-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.workshop-info-label {
    font-size: 0.9em;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.workshop-info-value {
    font-size: 1.2em;
    font-weight: 600;
    color: #2c2c2c;
}

.workshop-content {
    line-height: 1.8;
    font-size: 1.1em;
    margin: 30px 0;
}

.workshop-anmeldung-box {
    background: #fff;
    border: 2px solid #2c2c2c;
    padding: 30px;
    border-radius: 12px;
    margin: 40px 0;
    text-align: center;
}

.workshop-anmeldung-title {
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    font-size: 2em;
    margin: 0 0 20px 0;
}

.workshop-anmeldung-buttons {
    display: flex;
    flex-direction: column;
    gap: 15px;
    max-width: 400px;
    margin: 0 auto;
}

.workshop-anmeldung-button {
    padding: 15px 30px;
    background: #2c2c2c;
    color: #fff;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1.1em;
    transition: background 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.workshop-anmeldung-button:hover {
    background: #000;
}

.workshop-back-link {
    text-align: center;
    margin-top: 50px;
}

.workshop-back-link a {
    color: #666;
    text-decoration: none;
    font-size: 1.1em;
}

.workshop-back-link a:hover {
    color: #2c2c2c;
}

.workshop-meta-dates h3, .workshop-meta-dates h4 {
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    letter-spacing: 1px;
    border-bottom: 1px solid #ddd;
    padding-bottom: 5px;
    margin-top: 30px;
}

.workshop-nach-absprache-box {
    background: #e3f2fd;
    border-left: 4px solid #1976d2;
    padding: 20px;
    margin: 30px 0;
    border-radius: 4px;
}

.workshop-nach-absprache-box h3 {
    margin-top: 0;
    color: #1976d2;
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    font-size: 1.8em;
    letter-spacing: 1px;
}

.workshop-nach-absprache-box p {
    margin: 10px 0;
    line-height: 1.6;
}

@media (max-width: 768px) {
    .workshop-info-grid {
        grid-template-columns: 1fr;
    }
}

/* Inline Anmelden-Button (unter Hero-Bereich) */
.workshop-cta-inline {
    text-align: center;
    margin: 25px 0;
}

.workshop-cta-inline a {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 32px;
    background: #d4a574;
    color: #fff;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1.1em;
    transition: background 0.2s ease, transform 0.2s ease;
}

.workshop-cta-inline a:hover {
    background: #c08f5a;
    transform: translateY(-1px);
}

/* Floating Anmelden-Button */
.workshop-cta-floating {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
    opacity: 0;
    transform: translateY(20px);
    pointer-events: none;
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.workshop-cta-floating.is-visible {
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
}

.workshop-cta-floating a {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 24px;
    background: #2c2c2c;
    color: #fff;
    text-decoration: none;
    border-radius: 50px;
    font-weight: 600;
    font-size: 1em;
    box-shadow: 0 4px 16px rgba(0,0,0,0.25);
    transition: background 0.2s ease, transform 0.2s ease;
}

.workshop-cta-floating a:hover {
    background: #000;
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .workshop-cta-floating {
        bottom: 16px;
        right: 16px;
        left: 16px;
    }
    .workshop-cta-floating a {
        width: 100%;
        justify-content: center;
        box-sizing: border-box;
    }
}
</style>

<main id="primary" class="site-main">
    <div class="workshop-single-container">

        <?php
        while (have_posts()) : the_post();
            $post_id = get_the_ID();
            
            // Alle Meta-Daten holen
            $datum = micinterart_get_translated_meta($post_id, '_workshop_datum', true);
            $startzeit = micinterart_get_translated_meta($post_id, '_workshop_startzeit', true);
            $dauer_stunden = micinterart_get_translated_meta($post_id, '_workshop_dauer_stunden', true);
            
            $alter_von = micinterart_get_translated_meta($post_id, '_workshop_alter_von', true);
            $alter_bis = micinterart_get_translated_meta($post_id, '_workshop_alter_bis', true);
            $preis_string = micinterart_get_translated_meta($post_id, '_workshop_preis', true);
            $preis_info = micinterart_get_translated_meta($post_id, '_workshop_preis_info', true);
            $ort = micinterart_get_translated_meta($post_id, '_workshop_ort', true);
            $adresse = micinterart_get_translated_meta($post_id, '_workshop_adresse', true);
            $status = micinterart_get_translated_meta($post_id, '_workshop_status', true);
            $max_teilnehmer = micinterart_get_translated_meta($post_id, '_workshop_max_teilnehmer', true);
            $anmeldung_email = micinterart_get_translated_meta($post_id, '_workshop_anmeldung_email', true);
            $anmeldung_telefon = micinterart_get_translated_meta($post_id, '_workshop_anmeldung_telefon', true);
            $anmeldung_link = micinterart_get_translated_meta($post_id, '_workshop_anmeldung_link', true);
            $flyer_id = micinterart_get_translated_meta($post_id, '_workshop_flyer', true);
            
            
            if (empty($status)) $status = 'geplant';

            // Kategorie-Prüfung
            $is_kinderworkshop = false;
            $is_event = false;
            $categories = get_the_terms($post_id, 'workshop_kategorie');
            
            if ($categories && !is_wp_error($categories)) {
                foreach ($categories as $category) {
                    if ($category->slug === 'kinderworkshops') {
                        $is_kinderworkshop = true;
                    }
                    if ($category->slug === 'event' || $category->slug === 'events') {
                        $is_event = true;
                    }
                }
            }
            
            // Dynamische Texte je nach Typ
            $price_label = $is_kinderworkshop ? 'Preis pro Kind' : 'Preis pro Person';
            $termin_label = $is_event ? 'Event-Termin' : 'Workshop-Termin';
            $anmeldung_title = $is_event ? ($is_en ? 'Register for the workshop now!' : 'Jetzt für den Workshop anmelden!') : ($is_en ? 'Register now!' : 'Jetzt anmelden!');
            $nach_absprache_text = $is_event ? 'Event findet nach Absprache statt' : 'Workshop findet nach Absprache statt';
            $nach_absprache_beschreibung = $is_event 
                ? 'Dieses Event findet zu einem individuell vereinbarten Termin statt. Kontaktiere mich gerne, um einen passenden Termin zu finden!' 
                : 'Dieser Workshop findet zu einem individuell vereinbarten Termin statt. Kontaktiere mich gerne, um einen passenden Termin zu finden!';
            
            $status_labels = [
                'geplant' => 'Geplant',
                'anmeldung_offen' => 'Anmeldung offen',
                'fast_ausgebucht' => 'Fast ausgebucht',
                'ausgebucht' => 'Ausgebucht',
                'beendet' => 'Beendet',
                'abgesagt' => 'Abgesagt',
            ];
            
            // Themen dieses Workshops frühzeitig abfragen, um zu entscheiden ob "Nach Absprache"
            $themen_termine = get_posts([
                'post_type'      => 'workshop_thema',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'meta_query'     => [[
                    'key'     => '_thema_workshop_id',
                    'value'   => $post_id,
                    'compare' => '='
                ]],
                'orderby'  => 'meta_value',
                'meta_key' => '_thema_datum',
                'order'    => 'ASC',
            ]);

            // Aktuelle Werte für Ort und Zeit basierend auf dem nächsten Thema ermitteln
            $active_ort = $ort;
            $active_von = micinterart_get_translated_meta($post_id, '_workshop_uhrzeit_von', true);
            $active_bis = micinterart_get_translated_meta($post_id, '_workshop_uhrzeit_bis', true);
            $heute_comp = new DateTime('today');

            if (!empty($themen_termine)) {
                foreach ($themen_termine as $t_post) {
                    $t_datum = get_post_meta($t_post->ID, '_thema_datum', true);
                    if ($t_datum && new DateTime($t_datum) >= $heute_comp) {
                        $active_ort = get_post_meta($t_post->ID, '_thema_ort', true) ?: $active_ort;
                        $active_von = get_post_meta($t_post->ID, '_thema_uhrzeit_von', true) ?: $active_von;
                        $active_bis = get_post_meta($t_post->ID, '_thema_uhrzeit_bis', true) ?: $active_bis;
                        break;
                    }
                }
            }

            // Preis-Logik: Falls Themen vorhanden sind, Preise der Themen prüfen
            $themen_preise = [];
            if (!empty($themen_termine)) {
                foreach ($themen_termine as $t_post) {
                    $t_preis_raw = get_post_meta($t_post->ID, '_thema_preis', true);
                    if (!empty($t_preis_raw)) {
                        // Extrahiere numerischen Wert für den Vergleich (z.B. "35,00 €" -> 35.0)
                        $p_numeric = floatval(preg_replace('/[^0-9.]/', '', str_replace(',', '.', $t_preis_raw)));
                        if ($p_numeric > 0) {
                            $themen_preise[] = $p_numeric;
                        }
                    }
                }
            }

            if (!empty($themen_preise)) {
                $min_p = min($themen_preise);
                $max_p = max($themen_preise);
                if ($min_p !== $max_p) {
                    $preis_string = 'ab ' . number_format($min_p, 2, ',', '.') . ' €';
                } else {
                    $preis_string = number_format($min_p, 2, ',', '.') . ' €';
                }
            }

            // Ein Workshop ist nur "Nach Absprache", wenn weder ein Hauptdatum noch Themen existieren
            $frequenz = get_post_meta($post_id, '_workshop_wiederholung_frequenz', true);
            $ist_nach_absprache = empty($datum) && empty($themen_termine) && empty($frequenz);

            // Zentrale Prüfung: Ist eine Anmeldung aktuell überhaupt möglich?
            $kann_anmelden = ($status !== 'beendet' && $status !== 'abgesagt' && $status !== 'ausgebucht');
            $anmelden_button_text = $is_en ? 'Register now' : 'Jetzt anmelden';
            ?>

            <article id="post-<?php echo esc_attr($post_id); ?>" <?php post_class('workshop-single'); ?>>

                <header class="workshop-single-header">
                    <h1 class="workshop-single-title"><?php the_title(); ?></h1>
                    
                    <div class="workshop-single-status status-<?php echo esc_attr($status); ?>">
                        <?php echo esc_html($status_labels[$status]); ?>
                    </div>
                </header>

                <?php if (has_post_thumbnail()) : ?>
                    <div class="workshop-featured-image">
                        <?php the_post_thumbnail('large'); ?>
                    </div>
                <?php endif; ?>

                <?php if ($kann_anmelden) : ?>
                    <div class="workshop-cta-inline">
                        <a href="#workshop-anmeldung" class="js-scroll-anmeldung">
                            ✏️ <?php echo esc_html($anmelden_button_text); ?>
                        </a>
                    </div>
                <?php endif; ?>

                <div class="workshop-content">
                    <?php the_content(); ?>
                </div>
                
                <?php if ($ist_nach_absprache) : ?>
                    <!-- Nach Absprache Hinweis -->
                    <div class="workshop-nach-absprache-box">
                        <h3>🗓️ <?php echo esc_html($nach_absprache_text); ?></h3>
                        <p><?php echo esc_html($nach_absprache_beschreibung); ?></p>
                    </div>
                <?php else : ?>
                    <!-- Terminanzeige: Themen-basiert -->
                    <div class="workshop-meta-dates">
                        <?php
                        $von   = $active_von;
                        $bis   = $active_bis;
                        $heute = new DateTime('today');

                        // Termine für wiederholende Workshops (Serien) berechnen, falls keine Themen vorhanden sind
                        $recurring_dates = [];
                        if (empty($themen_termine)) {
                            $anzahl_limit = intval(get_post_meta($post_id, '_workshop_wiederholung_anzahl', true)) ?: 10;

                            if (!empty($frequenz) && !empty($datum)) {
                                try {
                                    $interval_map = [
                                        'woechentlich'     => 'P1W',
                                        'zweiwoechentlich' => 'P2W',
                                        'monatlich'        => 'P1M'
                                    ];
                                    if (isset($interval_map[$frequenz])) {
                                        $current_date = new DateTime($datum);
                                        $interval     = new DateInterval($interval_map[$frequenz]);
                                        // Falls das Startdatum in der Vergangenheit liegt, zum nächsten Termin springen
                                        while ($current_date < $heute) { $current_date->add($interval); }
                                        for ($i = 0; $i < $anzahl_limit; $i++) {
                                            $recurring_dates[] = clone $current_date;
                                            $current_date->add($interval);
                                        }
                                    }
                                } catch (Exception $e) {}
                            }
                        }

                        if (!empty($themen_termine)) :
                            // Workshop HAT Themen: max. 3 nächste Termine anzeigen
                            $naechste_themen = [];
                            foreach ($themen_termine as $thema_post) {
                                $thema_datum = get_post_meta($thema_post->ID, '_thema_datum', true);
                                if (empty($thema_datum)) continue;
                                try {
                                    // Individuelle Zeit für dieses Thema extrahieren
                                    $t_von = get_post_meta($thema_post->ID, '_thema_uhrzeit_von', true) ?: $active_von;
                                    $t_bis = get_post_meta($thema_post->ID, '_thema_uhrzeit_bis', true) ?: $active_bis;

                                    $thema_dt = new DateTime($thema_datum);
                                    if ($thema_dt >= $heute) {
                                        $naechste_themen[] = [
                                            'post'  => $thema_post, 
                                            'datum' => $thema_dt,
                                            'von'   => $t_von,
                                            'bis'   => $t_bis,
                                            'preis' => get_post_meta($thema_post->ID, '_thema_preis', true)
                                        ];
                                    }
                                } catch (Exception $e) {}
                            }
                            $naechste_themen = array_slice($naechste_themen, 0, 4);
                        ?>

                            <h3>🗓️ <?php echo esc_html($termin_label); ?>übersicht:</h3>

                            <?php if (!empty($naechste_themen)) : ?>
                                <?php
                                $first = array_shift($naechste_themen);
                                $first_post  = $first['post'];
                                $first_datum = $first['datum'];
                                ?>
                                <div style="background:#f9f9f9; padding:20px; border-radius:8px; margin:20px 0;">
                                    <p style="margin:0 0 10px 0;">
                                        <strong style="font-size:1.2em;">Nächster Termin:</strong><br>
                                        <span style="font-size:1.1em;">
                                            <?php echo date_i18n('l, d. F Y', $first_datum->getTimestamp()); ?>
                                            <?php if ($first['von']) { echo ', ' . esc_html($first['von']); if ($first['bis']) echo ' – ' . esc_html($first['bis']); echo ' Uhr'; } ?>
                                        </span>
                                    </p>
                                    <div class="workshop-thema-box" style="background:#fff; padding:15px; margin-top:15px; border-left:4px solid #d4a574; border-radius:4px; cursor:pointer;"
                                         onclick="toggleThemaDetails(this)">
                                        <div style="display:flex; justify-content:space-between; align-items:center;">
                                            <div>
                                                <p style="margin:0 0 5px 0;"><strong>📌 Thema dieses Termins:</strong></p>
                                                <h4 style="margin:5px 0 0 0; font-size:1.3em; color:#d4a574;">
                                                    <?php echo esc_html($first_post->post_title); ?>
                                                </h4>
                                                <?php
                                                $first_content = wp_strip_all_tags(strip_shortcodes(apply_filters('the_content', $first_post->post_content)));
                                                if (!empty($first_content)) :
                                                    ?>
                                                    <p style="margin:5px 0 0 0; font-size:0.95em; color:#666; line-height:1.4;">
                                                        <?php echo wp_trim_words($first_content, 10, '... <span style="color:#d4a574; font-weight:600;">' . ($is_en ? 'read more' : 'mehr erfahren') . '</span>'); ?>
                                                    </p>
                                                <?php endif; ?>
                                                <?php if (!empty($first['preis'])) : ?>
                                                    <div style="margin-top:5px; font-weight:600; color:#2c2c2c;">
                                                        💰 Preis für diesen Termin: <?php echo esc_html($first['preis']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php
                                                $fp_max  = intval(get_post_meta($first_post->ID, '_thema_max_teilnehmer', true));
                                                $fp_curr = intval(get_post_meta($first_post->ID, '_thema_current_bookings', true));
                                                $fp_frei = ($fp_max > 0) ? max(0, $fp_max - $fp_curr) : null;
                                                if (false) /* PLAETZE AUSGEBLENDET */ :
                                                    if ($fp_frei <= 0) : ?>
                                                        <span style="display:inline-block;margin-top:6px;padding:3px 10px;background:#ffebee;color:#d32f2f;border-radius:12px;font-size:0.85em;font-weight:700;"><?php echo esc_html($is_en ? 'Fully booked' : 'Ausgebucht'); ?></span>
                                                    <?php elseif ($fp_frei <= 3) : ?>
                                                        <span style="display:inline-block;margin-top:6px;padding:3px 10px;background:#fff3e0;color:#f57c00;border-radius:12px;font-size:0.85em;font-weight:700;">Nur noch <?php echo esc_html($fp_frei); ?> <?php echo ($fp_frei === 1) ? 'Platz' : 'Plätze'; ?> frei!</span>
                                                    <?php else : ?>
                                                        <span style="display:inline-block;margin-top:6px;padding:3px 10px;background:#e8f5e9;color:#388e3c;border-radius:12px;font-size:0.85em;font-weight:600;">Noch <?php echo esc_html($fp_frei); ?> Plätze frei</span>
                                                    <?php endif;
                                                endif; ?>
                                            </div>
                                            <span class="thema-toggle-icon" style="font-size:1.5em; transition:transform 0.3s;">▼</span>
                                        </div>
                                        <div class="thema-details" style="display:none; margin-top:15px; padding-top:15px; border-top:1px solid #eee;">
                                            <?php if (has_post_thumbnail($first_post->ID)) : ?>
                                                <div style="margin-bottom:15px;">
                                                    <?php echo get_the_post_thumbnail($first_post->ID, 'medium', ['style' => 'width:100%; height:auto; border-radius:6px;']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div style="color:#666; line-height:1.6;"><?php echo wpautop($first_post->post_content); ?></div>
                                            <?php micinterart_render_thema_erwartet_box($first_post->ID, $post_id); ?>
                                            <?php if ($kann_anmelden) : ?>
                                                <div style="text-align:center; margin-top:20px;">
                                                    <a href="#workshop-anmeldung"
                                                       class="js-scroll-anmeldung js-preselect-thema"
                                                       data-thema="<?php echo esc_attr($first_post->post_title); ?>"
                                                       onclick="event.stopPropagation();"
                                                       style="display:inline-flex; align-items:center; gap:8px; padding:10px 22px; background:#d4a574; color:#fff; text-decoration:none; border-radius:6px; font-weight:600; font-size:0.95em;">
                                                        ✏️ Für dieses Thema anmelden
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($naechste_themen)) : ?>
                                    <h4 style="margin-top:30px;">Weitere kommende Termine:</h4>
                                    <ul style="list-style:none; padding:0; margin:20px 0;">
                                        <?php foreach ($naechste_themen as $item) :
                                            $t_post  = $item['post'];
                                            $t_datum = $item['datum'];
                                        ?>
                                            <li class="workshop-termin-item" style="background:#f5f5f5; padding:15px; margin-bottom:10px; border-radius:6px; border-left:3px solid #ddd;">
                                                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                                    <div>
                                                        <strong><?php echo date_i18n('l, d. F Y', $t_datum->getTimestamp()); ?></strong>
                                                        <?php if ($item['von']) { echo ', ' . esc_html($item['von']); if ($item['bis']) echo ' – ' . esc_html($item['bis']); echo ' Uhr'; } ?>
                                                        <?php if (!empty($item['preis'])) : ?>
                                                            <br><span style="font-weight:600; color:#2c2c2c;">💰 Preis: <?php echo esc_html($item['preis']); ?></span>
                                                        <?php endif; ?>
                                                        <?php
                                                        $t_content = wp_strip_all_tags(strip_shortcodes(apply_filters('the_content', $t_post->post_content)));
                                                        if (!empty($t_content)) :
                                                            ?>
                                                            <div style="margin-top:5px; font-size:0.9em; color:#666; line-height:1.4;">
                                                                <?php echo wp_trim_words($t_content, 10, '... <span style="color:#d4a574; font-weight:600;">' . ($is_en ? 'read more' : 'mehr erfahren') . '</span>'); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <button type="button" onclick="toggleThemaDetails(this.closest('.workshop-termin-item'))"
                                                            style="padding:6px 12px; background:#d4a574; color:#fff; border:none; border-radius:4px; cursor:pointer;">
                                                        📌 <?php echo esc_html($t_post->post_title); ?> <span>▼</span>
                                                    </button>
                                                </div>
                                                <?php
                                                $tp_max  = intval(get_post_meta($t_post->ID, '_thema_max_teilnehmer', true));
                                                $tp_curr = intval(get_post_meta($t_post->ID, '_thema_current_bookings', true));
                                                $tp_frei = ($tp_max > 0) ? max(0, $tp_max - $tp_curr) : null;
                                                if (false) /* PLAETZE AUSGEBLENDET */ :
                                                    if ($tp_frei <= 0) : ?>
                                                        <div style="margin-top:6px;"><span style="padding:3px 10px;background:#ffebee;color:#d32f2f;border-radius:12px;font-size:0.85em;font-weight:700;"><?php echo esc_html($is_en ? 'Fully booked' : 'Ausgebucht'); ?></span></div>
                                                    <?php elseif ($tp_frei <= 3) : ?>
                                                        <div style="margin-top:6px;"><span style="padding:3px 10px;background:#fff3e0;color:#f57c00;border-radius:12px;font-size:0.85em;font-weight:700;">Nur noch <?php echo esc_html($tp_frei); ?> <?php echo ($tp_frei === 1) ? 'Platz' : 'Plätze'; ?> frei!</span></div>
                                                    <?php else : ?>
                                                        <div style="margin-top:6px;"><span style="padding:3px 10px;background:#e8f5e9;color:#388e3c;border-radius:12px;font-size:0.85em;font-weight:600;">Noch <?php echo esc_html($tp_frei); ?> Plätze frei</span></div>
                                                    <?php endif;
                                                endif; ?>
                                                <div class="thema-details" style="display:none; margin-top:15px; padding-top:15px; border-top:1px solid #ddd;">
                                                    <?php if (has_post_thumbnail($t_post->ID)) : ?>
                                                        <div style="margin-bottom:15px;">
                                                            <?php echo get_the_post_thumbnail($t_post->ID, 'medium', ['style' => 'width:100%; max-width:400px; height:auto; border-radius:6px;']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div style="color:#666; line-height:1.6; font-size:0.95em;"><?php echo wpautop($t_post->post_content); ?></div>
                                                <?php micinterart_render_thema_erwartet_box($t_post->ID, $post_id); ?>
                                                    <?php if ($kann_anmelden) : ?>
                                                        <div style="text-align:center; margin-top:20px;">
                                                            <a href="#workshop-anmeldung"
                                                               class="js-scroll-anmeldung js-preselect-thema"
                                                               data-thema="<?php echo esc_attr($t_post->post_title); ?>"
                                                               onclick="event.stopPropagation();"
                                                               style="display:inline-flex; align-items:center; gap:8px; padding:10px 22px; background:#d4a574; color:#fff; text-decoration:none; border-radius:6px; font-weight:600; font-size:0.95em;">
                                                                ✏️ <?php echo esc_html($is_en ? 'Register for this topic' : 'Für dieses Thema anmelden'); ?>
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

                            <?php else : ?>
                                <p><strong>Hinweis:</strong> Aktuell sind keine kommenden Termine geplant.</p>
                            <?php endif; // $naechste_themen ?>

                        <?php elseif (!empty($recurring_dates)) : ?>
                            <!-- Workshop ist eine Serie: Termine anzeigen -->
                            <h3>🗓️ <?php echo esc_html($termin_label); ?>übersicht:</h3>
                            <?php
                            $display_recurring = $recurring_dates;
                            $first_rec = array_shift($display_recurring);
                            ?>
                            <div style="background:#f9f9f9; padding:20px; border-radius:8px; margin:20px 0;">
                                <p style="margin:0;">
                                    <strong style="font-size:1.2em;">Nächster Termin:</strong><br>
                                    <span style="font-size:1.1em;">
                                        <?php echo date_i18n('l, d. F Y', $first_rec->getTimestamp()); ?>
                                        <?php if ($von) { echo ', ' . esc_html($von); if ($bis) echo ' – ' . esc_html($bis); echo ' Uhr'; } ?>
                                    </span>
                                </p>
                            </div>

                            <?php if (!empty($display_recurring)) : ?>
                                <h4 style="margin-top:30px;">Weitere kommende Termine:</h4>
                                <ul style="list-style:none; padding:0; margin:20px 0;">
                                    <?php foreach (array_slice($display_recurring, 0, 3) as $rd) : ?>
                                        <li style="background:#f5f5f5; padding:15px; margin-bottom:10px; border-radius:6px; border-left:3px solid #ddd;">
                                            <strong><?php echo date_i18n('l, d. F Y', $rd->getTimestamp()); ?></strong>
                                            <?php if ($von) { echo ', ' . esc_html($von); if ($bis) echo ' – ' . esc_html($bis); echo ' Uhr'; } ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                        <?php else : ?>
                            <!-- Kein Thema & keine Serie: einmaliges Workshop-Datum anzeigen -->
                            <?php if (!empty($datum)) : ?>
                                <h3>🗓️ <?php echo esc_html($termin_label); ?>:</h3>
                                <div style="background:#f9f9f9; padding:20px; border-radius:8px; margin:20px 0;">
                                    <p style="margin:0;">
                                        <strong style="font-size:1.2em;"><?php echo date_i18n('l, d. F Y', strtotime($datum)); ?></strong><br>
                                        <?php if ($von) { echo '<span style="font-size:1.1em;">' . esc_html($von); if ($bis) echo ' – ' . esc_html($bis); echo ' Uhr</span>'; } ?>
                                    </p>
                                </div>
                            <?php else : ?>
                                <p><strong>Hinweis:</strong> Es wurde noch kein Datum festgelegt.</p>
                            <?php endif; ?>
                        <?php endif; // Ende der Themen/Serien-Prüfung ?>
                    </div>
                <?php endif; ?>
                
                <script>
                function toggleThemaDetails(container) {
                    const details = container.querySelector('.thema-details');
                    const icon = container.querySelector('.thema-toggle-icon');
                    const btnArrow = container.querySelector('.btn-arrow');
                    
                    if (!details) return;

                    if (details.style.display === 'none' || details.style.display === '') {
                        details.style.display = 'block';
                        if (icon) icon.style.transform = 'rotate(180deg)';
                        if (btnArrow) btnArrow.innerHTML = '▲';
                    } else {
                        details.style.display = 'none';
                        if (icon) icon.style.transform = 'rotate(0deg)';
                        if (btnArrow) btnArrow.innerHTML = '▼';
                    }
                }
                </script>

                <div class="workshop-info-box">
                    <div class="workshop-info-grid">
                        
                        <?php if ($active_ort) : ?>
                            <div class="workshop-info-item">
                                <div class="workshop-info-label">📍 Ort</div>
                                <div class="workshop-info-value">
                                    <?php echo esc_html($active_ort); ?>
                                    <?php if ($adresse) : ?>
                                        <br><small style="font-size:0.8em;font-weight:400;color:#666;">
                                            <?php echo nl2br(esc_html($adresse)); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($alter_von || $alter_bis) : ?>
                            <div class="workshop-info-item">
                                <div class="workshop-info-label">👶 Alter</div>
                                <div class="workshop-info-value">
                                    <?php 
                                    if ($alter_von && $alter_bis) {
                                        echo esc_html($alter_von . '-' . $alter_bis);
                                    } elseif ($alter_von) {
                                        echo 'Ab ' . esc_html($alter_von);
                                    } elseif ($alter_bis) {
                                        echo 'Bis ' . esc_html($alter_bis);
                                    }
                                    ?> Jahre
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($preis_string) : ?>
                            <div class="workshop-info-item">
                                <div class="workshop-info-label">💰 <?php echo esc_html($price_label); ?></div>
                                <div class="workshop-info-value">
                                    <?php echo esc_html($preis_string); ?>
                                    <?php if ($preis_info) : ?>
                                        <br><small style="font-size:0.8em;font-weight:400;color:#666;">
                                            <?php echo esc_html($preis_info); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php
                        // Plätze-Box nur anzeigen wenn Workshop KEINE Themen hat
                        // (bei Themen-Workshops erscheint die Platz-Anzeige direkt pro Termin)
                        $hat_themen_check = get_posts([
                            'post_type'      => 'workshop_thema',
                            'posts_per_page' => 1,
                            'post_status'    => 'publish',
                            'fields'         => 'ids',
                            'meta_query'     => [[
                                'key'     => '_thema_workshop_id',
                                'value'   => $post_id,
                                'compare' => '='
                            ]]
                        ]);
                        if (empty($hat_themen_check)) :
                            $plaetze_data = null;
                            if (function_exists('micinterart_get_freie_plaetze')) {
                                $plaetze_data = micinterart_get_freie_plaetze($post_id);
                            }
                            if ($plaetze_data === null && $max_teilnehmer) {
                                $current_bookings_direct = intval(get_post_meta($post_id, '_workshop_current_bookings', true));
                                $plaetze_data = [
                                    'max'  => intval($max_teilnehmer),
                                    'frei' => max(0, intval($max_teilnehmer) - $current_bookings_direct),
                                ];
                            }
                            $freie_plaetze = $plaetze_data ? $plaetze_data['frei'] : null;
                        ?>
                        <?php if (false) /* PLAETZE AUSGEBLENDET */ : ?>
    <div class="workshop-info-item">
        <div class="workshop-info-label">&#128101; Pl&auml;tze</div>
        <div class="workshop-info-value">
            <?php if ($freie_plaetze <= 0) : ?>
                <span style="color:#e74c3c;font-weight:700;">Ausgebucht</span>
            <?php elseif ($freie_plaetze <= 3) : ?>
                <span style="color:#e67e22;font-weight:700;">Nur noch <?php echo esc_html($freie_plaetze); ?> <?php echo ($freie_plaetze === 1) ? 'Platz' : 'Pl&auml;tze'; ?> frei!</span>
            <?php else : ?>
                Noch <?php echo esc_html($freie_plaetze); ?> Pl&auml;tze frei
            <?php endif; ?>
        </div>
    </div>
<?php endif; // $freie_plaetze ?>
                        <?php endif; // empty($hat_themen_check) ?>
                        
                    </div>
                </div>

                <?php if ($status !== 'beendet' && $status !== 'abgesagt') : ?>
                    <div class="workshop-anmeldung-box" id="workshop-anmeldung">
                        <h2 class="workshop-anmeldung-title">
                            <?php 
                            if ($status === 'ausgebucht') {
                                echo $is_event ? ($is_en ? 'Event fully booked' : 'Event ausgebucht') : ($is_en ? 'Workshop fully booked' : 'Workshop ausgebucht');
                            } else {
                                echo esc_html($anmeldung_title);
                            }
                            ?>
                        </h2>
                        
                        <?php if ($status === 'ausgebucht') : ?>
                            <p>
                                <?php 
                                if ($is_event) {
                                    echo $is_en ? 'This event is already fully booked. Please feel free to browse our other events.' : 'Dieses Event ist leider bereits ausgebucht. Schauen Sie sich gerne unsere anderen Events an!';
                                } else {
                                    echo $is_en ? 'This workshop is already fully booked. Please feel free to browse our other workshops.' : 'Dieser Workshop ist leider bereits ausgebucht. Schauen Sie sich gerne unsere anderen Workshops an!';
                                }
                                ?>
                            </p>
                        <?php else : ?>
                            
                            <div class="workshop-anmeldung-form">
                                
                                <?php 
                                // Form IDs
                                $kinder_form_id = '9260249';
                                $erwachsenen_form_id = 'e4aab6c';
                                $form_id = $is_kinderworkshop ? $kinder_form_id : $erwachsenen_form_id;
                                
                                // Das Contact Form 7 Shortcode mit der dynamischen ID ausführen
                                $cf7_shortcode = '[contact-form-7 id="' . esc_attr($form_id) . '" workshop_id="' . esc_attr($post_id) . '"]'; 
                                echo do_shortcode($cf7_shortcode);
                                
                                // Themen-Dropdown: Prüfe ob dieser Workshop mehrere Themen hat
                                // Nur zukünftige oder datumlose Themen ins Dropdown
                                $heute_str = date('Y-m-d');
                                $themen_args = [
                                    'post_type'   => 'workshop_thema',
                                    'posts_per_page' => -1,
                                    'post_status' => 'publish',
                                    'orderby'     => 'meta_value',
                                    'meta_key'    => '_thema_datum',
                                    'order'       => 'ASC',
                                    'meta_query'  => [
                                        'relation' => 'AND',
                                        [
                                            'key'     => '_thema_workshop_id',
                                            'value'   => $post_id,
                                            'compare' => '='
                                        ],
                                        [
                                            'relation' => 'OR',
                                            [
                                                'key'     => '_thema_datum',
                                                'value'   => $heute_str,
                                                'compare' => '>=',
                                                'type'    => 'DATE'
                                            ],
                                            [
                                                'key'     => '_thema_datum',
                                                'compare' => 'NOT EXISTS'
                                            ],
                                            [
                                                'key'     => '_thema_datum',
                                                'value'   => '',
                                                'compare' => '='
                                            ]
                                        ]
                                    ]
                                ];
                                $workshop_themen = get_posts($themen_args);

                                ?>
                                
                                <script>
                                jQuery(document).ready(function($) {
                                    // Das Feld 'thema' im CF7 Formular finden (Input oder Select)
                                    var $themaField = $('form.wpcf7-form [name="thema"], .wpcf7-form-control-wrap.thema input, .wpcf7-form-control-wrap.thema select');
                                    if (!$themaField.length) return;
                                    
                                    var $themaWrapper = $themaField.closest('.wpcf7-form-control-wrap, p').first();
                                    var $themaLabel = $themaField.closest('label').length ? $themaField.closest('label') : $themaField.parent();
                                    
                                    <?php if (!empty($workshop_themen) && count($workshop_themen) > 1) : ?>
                                        // Workshop hat mehrere Themen -> Dropdown erstellen
                                        var themen = [
                                            <?php foreach ($workshop_themen as $wt) : ?>
                                                { value: <?php echo json_encode($wt->post_title); ?>, id: <?php echo intval($wt->ID); ?> },
                                            <?php endforeach; ?>
                                        ];
                                        
                                        // Select-Element erstellen
                                        var $select = $('<select>')
                                            .attr('name', 'thema')
                                            .attr('id', 'thema-dropdown')
                                            .css({
                                                'width': '100%',
                                                'padding': '8px 12px',
                                                'border': '1px solid #ddd',
                                                'border-radius': '4px',
                                                'font-size': '1em',
                                                'background': '#fff',
                                                'cursor': 'pointer'
                                            });
                                        
                                        // Platzhalter-Option
                                        $select.append($('<option>').val('').text('— Bitte Thema wählen —').prop('disabled', true).prop('selected', true));
                                        
                                        // Themen als Optionen hinzufügen (inkl. thema-id als data-Attribut)
                                        $.each(themen, function(i, t) {
                                            $select.append($('<option>').val(t.value).text(t.value).attr('data-thema-id', t.id));
                                        });
                                        
                                        // Textfeld durch Dropdown ersetzen
                                        $themaField.replaceWith($select);
                                        $themaLabel.show();
                                        
                                    <?php else : ?>
                                        <!-- Nutzt die oben berechneten $recurring_dates -->
                                        <?php if (!empty($recurring_dates)) : ?>
                                        // Workshop hat Wiederholungen -> Termine als Dropdown
                                        var dates = [
                                            <?php foreach ($recurring_dates as $rd) : ?>
                                                <?php echo json_encode(date_i18n('l, d. F Y', $rd->getTimestamp())); ?>,
                                            <?php endforeach; ?>
                                        ];
                                        
                                        var $select = $('<select>')
                                            .attr('name', 'thema')
                                            .attr('id', 'thema-dropdown')
                                            .css({
                                                'width': '100%',
                                                'padding': '8px 12px',
                                                'border': '1px solid #ddd',
                                                'border-radius': '4px',
                                                'font-size': '1em',
                                                'background': '#fff',
                                                'cursor': 'pointer'
                                            });
                                        
                                        $select.append($('<option>').val('').text('— Bitte Termin wählen —').prop('disabled', true).prop('selected', true));
                                        $.each(dates, function(i, d) {
                                            $select.append($('<option>').val(d).text(d));
                                        });
                                        
                                        $themaField.replaceWith($select);
                                        $themaLabel.show();
                                        
                                        <?php else : ?>
                                        // Workshop hat KEINE mehreren Themen -> Feld komplett ausblenden
                                        // Das gesamte Label-Element (inkl. Text "Workshop Thema") ausblenden
                                        var $parentLabel = $themaField.closest('label');
                                        if ($parentLabel.length) {
                                            $parentLabel.hide();
                                        } else {
                                            $themaWrapper.hide();
                                        }
                                        // Leeren Wert setzen damit CF7 nicht meckert
                                        $themaField.val('').removeAttr('required');
                                        <?php endif; ?>

                                    <?php endif; ?>
                                });
                                </script>
                                
                            </div>
                            
                            <div class="workshop-anmeldung-buttons" style="margin-top: 20px;">
                                <?php
                                // WhatsApp Button
                                if ($anmeldung_telefon) :
                                    $whatsapp_number = preg_replace('/[^0-9+]/', '', $anmeldung_telefon);
                                    if (substr($whatsapp_number, 0, 1) === '0') {
                                        $whatsapp_number = '+49' . substr($whatsapp_number, 1);
                                    }
                                    $whatsapp_text_prefix = $is_event ? 'Event' : 'Workshop';
                                    $whatsapp_text = urlencode('Hallo, ich möchte mich für den ' . $whatsapp_text_prefix . ' "' . get_the_title() . '" anmelden.');
                                    ?>
                                    <a href="https://wa.me/<?php echo esc_attr($whatsapp_number); ?>?text=<?php echo $whatsapp_text; ?>" class="workshop-anmeldung-button" target="_blank" rel="noopener noreferrer">
                                        💬 Per WhatsApp anmelden
                                    </a>
                                    <p style="margin:5px 0 0;color:#666;font-size:0.9em;">
                                        <?php echo esc_html($anmeldung_telefon); ?>
                                    </p>
                                <?php endif; ?>

                                <?php if ($anmeldung_link) : ?>
                                    <a href="<?php echo esc_url($anmeldung_link); ?>" class="workshop-anmeldung-button" target="_blank">
                                        🔗 Externer Anmelde-Link
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($kann_anmelden) : ?>
                    <div class="workshop-cta-floating" id="workshop-cta-floating">
                        <a href="#workshop-anmeldung" class="js-scroll-anmeldung">
                            ✏️ <?php echo esc_html($anmelden_button_text); ?>
                        </a>
                    </div>

                    <script>
                    (function() {
                        // Smooth-Scroll zum Anmeldeformular für alle CTA-Buttons
                        document.querySelectorAll('.js-scroll-anmeldung').forEach(function(link) {
                            link.addEventListener('click', function(e) {
                                e.preventDefault();
                                var target = document.getElementById('workshop-anmeldung');
                                if (target) {
                                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                                }

                                // Falls der Button ein bestimmtes Thema vorauswählen soll (Klick aus Themen-Karte):
                                // Dropdown wird per JS erst nach DOM-ready erzeugt, daher kurz nachfassen
                                var themaName = link.getAttribute('data-thema');
                                if (themaName) {
                                    var attempts = 0;
                                    var trySelect = setInterval(function() {
                                        attempts++;
                                        var select = document.getElementById('thema-dropdown');
                                        if (select) {
                                            var matched = false;
                                            for (var i = 0; i < select.options.length; i++) {
                                                if (select.options[i].value === themaName) {
                                                    select.selectedIndex = i;
                                                    matched = true;
                                                    select.dispatchEvent(new Event('change', { bubbles: true }));
                                                    break;
                                                }
                                            }
                                            if (matched || attempts > 20) {
                                                clearInterval(trySelect);
                                            }
                                        } else if (attempts > 20) {
                                            clearInterval(trySelect);
                                        }
                                    }, 100);
                                }
                            });
                        });

                        // Floating Button erst einblenden, sobald man am Hero/Inline-Button vorbeigescrollt ist,
                        // und ausblenden sobald man das eigentliche Anmeldeformular erreicht hat
                        var floatingBtn = document.getElementById('workshop-cta-floating');
                        var anmeldungBox = document.getElementById('workshop-anmeldung');
                        if (!floatingBtn) return;

                        var showThreshold = 400; // px gescrollt, bevor der Button erscheint

                        function updateFloatingVisibility() {
                            var scrolled = window.scrollY || window.pageYOffset;
                            var nearForm = false;

                            if (anmeldungBox) {
                                var rect = anmeldungBox.getBoundingClientRect();
                                nearForm = rect.top < window.innerHeight * 0.7;
                            }

                            if (scrolled > showThreshold && !nearForm) {
                                floatingBtn.classList.add('is-visible');
                            } else {
                                floatingBtn.classList.remove('is-visible');
                            }
                        }

                        window.addEventListener('scroll', updateFloatingVisibility, { passive: true });
                        updateFloatingVisibility();
                    })();
                    </script>
                <?php endif; ?>

            </article>

        <?php endwhile; ?>

        <div class="workshop-back-link">
            <a href="<?php echo esc_url(get_post_type_archive_link('workshop')); ?>">
                ← Zurück zur <?php echo $is_event ? 'Event' : 'Workshop'; ?>-Übersicht
            </a>
        </div>

    </div>
</main>

<?php
get_footer();