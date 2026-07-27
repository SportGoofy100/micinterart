<?php
/**
 * Template fuer Workshop-Uebersicht mit Archiv
 * Hero-Card + Weitere-Toggle pro Kategorie
 *
 * @package Micinterart
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
$is_en = (function_exists('pll_current_language') && pll_current_language() === 'en');
?>

<style>
/* Workshop-Uebersicht Styling */
.workshops-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 60px 20px;
}

.workshops-page-title {
    text-align: center;
    font-size: 3em;
    margin-bottom: 40px;
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    letter-spacing: 2px;
}

.workshops-intro {
    max-width: 900px;
    margin: 0 auto 50px;
    text-align: center;
}

.workshops-hero-image {
    aspect-ratio: 14 / 4;
    margin-bottom: 30px;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
}

.workshops-hero-image img {
    width: 100%;
    height: auto;
    display: block;
    max-height: 400px;
    object-fit: cover;
}

.workshops-intro p {
    font-size: 1.15em;
    line-height: 1.8;
    color: #555;
    margin: 15px 0;
}

.workshops-intro p:first-of-type {
    font-weight: 600;
    color: #2c2c2c;
}

/* Sektions-Trennung */
.workshop-section {
    margin-bottom: 60px;
}
.workshop-section.archiv { margin-bottom: 0 !important; }

.section-header {
    text-align: center;
    margin-bottom: 40px;
    padding-bottom: 20px;
    border-bottom: 3px solid #d4a574;
}

.section-title {
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    font-size: 2.5em;
    margin: 0 0 10px 0;
    letter-spacing: 2px;
    color: #2c2c2c;
}

.section-subtitle {
    font-size: 1.1em;
    color: #666;
    font-style: italic;
}

/* Kinderworkshops spezielle Farbe */
.workshop-section.kinder .section-header {
    border-bottom-color: #ff6b9d;
}

.workshop-section.kinder .section-title {
    color: #ff6b9d;
}

/* Archiv-Sektion */
.workshop-section.archiv {
    background: #f9f9f9;
    padding: 20px 20px 25px;
    border-radius: 12px;
    margin-top: 30px;
    margin-bottom: 0;
}

.workshop-section.archiv .section-header {
    border-bottom-color: #999;
}

.workshop-section.archiv .section-title {
    color: #666;
}

.archiv-toggle {
    text-align: center;
    margin-bottom: 30px;
}

.archiv-toggle-button {
    padding: 10px 22px;
    background: transparent;
    color: #666;
    border: 1.5px solid #bbb;
    border-radius: 8px;
    font-size: 0.95em;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.archiv-toggle-button:hover {
    background: #f0f0f0;
    border-color: #888;
    color: #333;
    transform: translateY(-1px);
}

.archiv-toggle-button .arrow {
    transition: transform 0.3s ease;
}

.archiv-toggle-button.active .arrow {
    transform: rotate(180deg);
}

.archiv-content {
    display: none;
}

.archiv-content.show {
    display: block;
}

.workshops-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 30px;
}

.workshop-card {
    background: #fff;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    position: relative;
}

.workshop-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

/* Vergangene Workshops ausgegraut */
.workshop-card.past {
    opacity: 0.8;
}

.workshop-card.past .workshop-thumbnail {
    filter: grayscale(50%);
}

.workshop-status-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 600;
    z-index: 2;
}

.workshop-monat-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 600;
    z-index: 2;
    background: rgba(44, 44, 44, 0.9);
    color: #fff;
}

.status-geplant { background: #e3f2fd; color: #1976d2; }
.status-anmeldung_offen { background: #e8f5e9; color: #388e3c; }
.status-fast_ausgebucht { background: #fff3e0; color: #f57c00; }
.status-ausgebucht { background: #ffebee; color: #d32f2f; }
.status-beendet { background: #f5f5f5; color: #666; }
.status-abgesagt { background: #fce4ec; color: #c2185b; }

.workshop-thumbnail {
    width: 100%;
    height: 250px;
    object-fit: cover;
    background: #f5f5f5;
}

.workshop-content {
    padding: 25px;
}

.workshop-title {
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    font-size: 1.8em;
    margin: 0 0 15px 0;
    letter-spacing: 1px;
}

.workshop-title a {
    color: #2c2c2c;
    text-decoration: none;
}

.workshop-title a:hover {
    color: #666;
}

.workshop-meta {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 15px;
    font-size: 0.95em;
}

.workshop-meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #666;
}

.workshop-meta-item strong {
    color: #2c2c2c;
    min-width: 80px;
}

.workshop-excerpt {
    color: #666;
    line-height: 1.6;
    margin-bottom: 20px;
}

.workshop-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
}

.workshop-preis {
    font-size: 1.3em;
    font-weight: 600;
    color: #2c2c2c;
}

.workshop-button {
    padding: 10px 20px;
    background: #2c2c2c;
    color: #fff;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 500;
    transition: background 0.2s ease;
    display: inline-block;
}

.workshop-button:hover {
    background: #000;
}

.no-workshops {
    text-align: center;
    padding: 60px 20px;
    color: #666;
    background: #f9f9f9;
    border-radius: 12px;
    margin: 40px 0;
}

/* ========================================
   HERO CARD - Prominenter naechster Workshop
   ======================================== */
.workshop-hero-card {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 12px 40px rgba(0,0,0,0.12);
    margin-bottom: 40px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    position: relative;
}
.workshop-hero-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 50px rgba(0,0,0,0.18);
}
.hero-badge-next {
    position: absolute;
    top: 20px;
    left: 20px;
    background: linear-gradient(135deg, #d4a574, #c4915e);
    color: #fff;
    padding: 8px 18px;
    border-radius: 25px;
    font-size: 0.85em;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    z-index: 3;
    box-shadow: 0 4px 12px rgba(212,165,116,0.4);
}
.workshop-section.kinder .hero-badge-next {
    background: linear-gradient(135deg, #ff6b9d, #e8547a);
    box-shadow: 0 4px 12px rgba(255,107,157,0.4);
}
.hero-image-wrapper {
    position: relative;
    overflow: hidden;
    min-height: 400px;
}
.hero-image-wrapper img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.hero-image-placeholder {
    width: 100%;
    height: 100%;
    min-height: 400px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #f5f0eb, #e8ddd3);
    font-size: 80px;
}
.hero-status-badge {
    position: absolute;
    top: 20px;
    right: 20px;
    padding: 8px 16px;
    border-radius: 25px;
    font-size: 0.9em;
    font-weight: 600;
    z-index: 2;
}
.hero-content {
    padding: 45px 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.hero-title {
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    font-size: 2.4em;
    margin: 0 0 20px 0;
    letter-spacing: 1.5px;
    line-height: 1.15;
}
.hero-title a {
    color: #2c2c2c;
    text-decoration: none;
    transition: color 0.2s ease;
}
.hero-title a:hover { color: #d4a574; }
.workshop-section.kinder .hero-title a:hover { color: #ff6b9d; }
.hero-meta {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 25px;
    font-size: 1.05em;
}
.hero-meta-item {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #555;
}
.hero-meta-item strong {
    color: #2c2c2c;
    min-width: 90px;
}
.hero-excerpt {
    color: #555;
    line-height: 1.8;
    margin-bottom: 30px;
    font-size: 1.05em;
}
.hero-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 25px;
    border-top: 2px solid #f0ebe5;
}
.hero-preis {
    font-size: 1.6em;
    font-weight: 700;
    color: #2c2c2c;
}
.hero-preis .preis-suffix {
    display: block;
    font-size: 0.5em;
    font-weight: 400;
    color: #888;
    margin-top: 3px;
}
.hero-button {
    padding: 14px 32px;
    background: linear-gradient(135deg, #2c2c2c, #444);
    color: #fff;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1.05em;
    transition: all 0.3s ease;
    display: inline-block;
}
.hero-button:hover {
    background: linear-gradient(135deg, #000, #2c2c2c);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.2);
    color: #fff;
}

/* Weitere Workshops Toggle */
.weitere-toggle {
    text-align: center;
    margin-bottom: 30px;
}
.weitere-toggle-button {
    padding: 10px 22px;
    background: transparent;
    color: #2c2c2c;
    border: 1.5px solid #2c2c2c;
    border-radius: 8px;
    font-size: 0.95em;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}
.weitere-toggle-button:hover {
    background: #2c2c2c;
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(0,0,0,0.12);
}
.workshop-section.kinder .weitere-toggle-button {
    background: transparent;
    color: #ff6b9d;
    border-color: #ff6b9d;
}
.workshop-section.kinder .weitere-toggle-button:hover {
    background: #ff6b9d;
    color: #fff;
}
.weitere-toggle-button .arrow {
    transition: transform 0.3s ease;
    font-size: 0.85em;
}
.weitere-toggle-button.active .arrow {
    transform: rotate(180deg);
}
.weitere-content {
    display: none;
    margin-top: 30px;
}
.weitere-content.show {
    display: block;
    animation: fadeInDown 0.4s ease;
}
@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-15px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Responsive */
@media (max-width: 900px) {
    .workshop-hero-card { grid-template-columns: 1fr; }
    .hero-image-wrapper { min-height: 280px; max-height: 350px; }
    .hero-content { padding: 30px 25px; }
    .hero-title { font-size: 1.8em; }
}
@media (max-width: 768px) {
    .workshops-grid { grid-template-columns: 1fr; }
    .workshops-hero-image img { max-height: 250px; }
    .hero-footer {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
        text-align: center;
    }
    .hero-button { text-align: center; }
    
    
}

/* ========================================
   TERMINUEBERSICHT (Quick Overview)
   ======================================== */
.termin-uebersicht {
    margin: 0 auto 60px;
    max-width: 1100px;
    background: #fff;
    border: 1px solid #e8e2d8;
    border-radius: 12px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.06);
    overflow: hidden;
}
.termin-uebersicht-header {
    background: linear-gradient(135deg, #f5f0eb, #ece2d3);
    padding: 18px 25px;
    border-bottom: 1px solid #e8e2d8;
}
.termin-uebersicht-header h2 {
    margin: 0;
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    font-size: 1.6em;
    letter-spacing: 1.5px;
    color: #2c2c2c;
}
.termin-uebersicht-header p {
    margin: 4px 0 0 0;
    font-size: 0.9em;
    color: #777;
    font-style: italic;
}
.termin-uebersicht-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.98em;
}
.termin-uebersicht-table th {
    text-align: left;
    padding: 12px 18px;
    background: #faf7f2;
    font-weight: 600;
    color: #555;
    font-size: 0.85em;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e8e2d8;
}
.termin-uebersicht-table td {
    padding: 14px 18px;
    border-bottom: 1px solid #f0ebe2;
    color: #333;
    vertical-align: middle;
}
.termin-uebersicht-table tr:last-child td { border-bottom: none; }
.termin-uebersicht-table tr:hover td { background: #fbf9f5; }
.termin-uebersicht-table .tu-datum { font-weight: 600; white-space: nowrap; color: #2c2c2c; }
.termin-uebersicht-table .tu-titel a { color: #2c2c2c; text-decoration: none; font-weight: 500; }
.termin-uebersicht-table .tu-titel a:hover { color: #d4a574; text-decoration: underline; }
.termin-uebersicht-table .tu-tag {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.78em;
    font-weight: 600;
    white-space: nowrap;
}
.termin-uebersicht-table .tu-tag-erw { background: #f5ede0; color: #8a6a3d; }
.termin-uebersicht-table .tu-tag-kind { background: #ffe6ef; color: #c2185b; }
.termin-uebersicht-table .tu-preis { font-weight: 600; color: #2c2c2c; white-space: nowrap; }
.termin-uebersicht-table .tu-cta {
    display: inline-block;
    padding: 6px 14px;
    background: #2c2c2c;
    color: #fff;
    text-decoration: none;
    border-radius: 6px;
    font-size: 0.85em;
    font-weight: 500;
    transition: background 0.2s;
    white-space: nowrap;
}
.termin-uebersicht-table .tu-cta:hover { background: #000; color: #fff; }
@media (max-width: 768px) {
    .termin-uebersicht-table thead { display: none; }
    .termin-uebersicht-table, .termin-uebersicht-table tbody, .termin-uebersicht-table tr, .termin-uebersicht-table td { display: block; width: 100%; }
    .termin-uebersicht-table tr { padding: 14px 18px; border-bottom: 1px solid #f0ebe2; }
    .termin-uebersicht-table td { padding: 4px 0; border: none; }
    .termin-uebersicht-table .tu-cta { margin-top: 8px; }
}

/* ========================================
   IMPRESSIONEN-SLIDER
   ======================================== */
.impressionen-section {
    margin: 50px auto 60px;
    max-width: 1200px;
    overflow: hidden;
}
.impressionen-header {
    text-align: center;
    margin-bottom: 25px;
}
.impressionen-header h2 {
    font-family: 'Bebas Neue', 'Arial', sans-serif;
    font-size: 2.2em;
    letter-spacing: 2px;
    color: #2c2c2c;
    margin: 0 0 8px 0;
}
.impressionen-header p {
    color: #888;
    font-size: 0.95em;
    font-style: italic;
    margin: 0;
}
.impressionen-slider-wrapper {
    position: relative;
    overflow: hidden;
    border-radius: 12px;
}
.impressionen-track {
    display: flex;
    transition: transform 0.5s cubic-bezier(0.25, 0.8, 0.25, 1);
    gap: 12px;
    will-change: transform;
}
.impressionen-slide {
    flex: 0 0 auto;
    width: calc(25% - 9px);
    border-radius: 10px;
    overflow: hidden;
    cursor: pointer;
    position: relative;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.impressionen-slide:hover {
    transform: scale(1.04);
    box-shadow: 0 8px 24px rgba(0,0,0,0.18);
    z-index: 2;
}
.impressionen-slide img {
    width: 100%;
    height: 220px;
    object-fit: cover;
    display: block;
    transition: filter 0.3s ease;
}
.impressionen-slide:hover img {
    filter: brightness(1.08);
}
/* Slider Navigation Arrows */
.imp-slider-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255,255,255,0.92);
    border: none;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    font-size: 1.4em;
    cursor: pointer;
    z-index: 5;
    box-shadow: 0 2px 12px rgba(0,0,0,0.15);
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2c2c2c;
}
.imp-slider-btn:hover {
    background: #fff;
    box-shadow: 0 4px 20px rgba(0,0,0,0.22);
    transform: translateY(-50%) scale(1.1);
}
.imp-slider-prev { left: 12px; }
.imp-slider-next { right: 12px; }
/* Dots */
.imp-slider-dots {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 18px;
}
.imp-slider-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #ccc;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 0;
}
.imp-slider-dot.active {
    background: #2c2c2c;
    transform: scale(1.3);
}
/* Lightbox */
.mic-lightbox-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.92);
    z-index: 99999;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}
.mic-lightbox-overlay.active {
    display: flex;
}
.mic-lightbox-overlay img {
    max-width: 90vw;
    max-height: 90vh;
    border-radius: 8px;
    box-shadow: 0 0 40px rgba(0,0,0,0.5);
    cursor: default;
}
.mic-lightbox-close {
    position: fixed;
    top: 20px;
    right: 30px;
    color: #fff;
    font-size: 2.5em;
    cursor: pointer;
    z-index: 100000;
    line-height: 1;
    text-shadow: 0 2px 8px rgba(0,0,0,0.5);
    transition: transform 0.2s;
}
.mic-lightbox-close:hover { transform: scale(1.2); }
.mic-lightbox-nav {
    position: fixed;
    top: 50%;
    transform: translateY(-50%);
    color: #fff;
    font-size: 3em;
    cursor: pointer;
    z-index: 100000;
    padding: 10px;
    user-select: none;
    text-shadow: 0 2px 8px rgba(0,0,0,0.5);
    transition: transform 0.2s;
}
.mic-lightbox-nav:hover { transform: translateY(-50%) scale(1.15); }
.mic-lightbox-prev { left: 20px; }
.mic-lightbox-next { right: 20px; }

@media (max-width: 900px) {
    .impressionen-slide {
        width: calc(33.333% - 8px);
    }
    .impressionen-slide img { height: 180px; }
}
@media (max-width: 600px) {
    .impressionen-slide {
        width: calc(50% - 6px);
    }
    .impressionen-slide img { height: 150px; }
    .imp-slider-btn { width: 36px; height: 36px; font-size: 1.1em; }
}



/* ========================================
   MOBILE OPTIMIERUNG (Overrides)
   Ziel: keine Sections verschieben, nur Responsiveness verbessern
   ======================================== */

@media (max-width: 768px) {
    .workshops-container { padding: 35px 16px; }
    .workshops-page-title { font-size: 2.2em; margin-bottom: 24px; }
    .workshops-intro { margin: 0 auto 30px; }
    .workshops-intro p { font-size: 1.05em; }

    .section-header { margin-bottom: 26px; padding-bottom: 14px; }
    .section-title { font-size: 2.0em; }

    /* Grids: 1 Spalte auf Mobile */
    .workshops-grid { grid-template-columns: 1fr; gap: 18px; }

    /* Hero-Card: 1 Spalte */
    .workshop-hero-card { grid-template-columns: 1fr !important; }
    .hero-image-wrapper { min-height: 220px; }

    /* Toggle Buttons: volle Breite */
    .weitere-toggle-button, .archiv-toggle-button { width: 100%; justify-content: center; }

    /* Terminübersicht: falls nicht schon komplett, robustes Stacking */
    .termin-uebersicht { overflow: hidden; }
    .termin-uebersicht-table { width: 100%; border-collapse: collapse; }
    .termin-uebersicht-table thead { display: none !important; }
    .termin-uebersicht-table,
    .termin-uebersicht-table tbody,
    .termin-uebersicht-table tr,
    .termin-uebersicht-table td {
        display: block;
        width: 100%;
    }
    .termin-uebersicht-table tr {
        background: #fff;
        border: 1px solid #e7e7e7;
        border-radius: 12px;
        margin-bottom: 14px;
        overflow: hidden;
    }
    .termin-uebersicht-table td {
        padding: 10px 12px;
        border: 0;
        border-bottom: 1px solid #f0f0f0;
    }
    .termin-uebersicht-table td:last-child { border-bottom: 0; }
    .termin-uebersicht-table td[data-label]::before {
        content: attr(data-label) ": ";
        display: inline-block;
        font-weight: 700;
        color: #444;
        margin-right: 6px;
    }
    /* CTA in letzter Spalte */
    .termin-uebersicht-table .tu-cta {
        display: inline-block;
        width: 100%;
        text-align: center;
        padding: 10px 12px;
    }

    /* Galerie: etwas weniger Spalten/Abstand (falls Masonry/Columns genutzt) */
    .impressionen-grid { column-count: 1 !important; column-gap: 14px !important; }
}

@media (max-width: 420px) {
    .workshops-page-title { font-size: 2.0em; }
    .section-title { font-size: 1.85em; }
}

</style>

<main id="primary" class="site-main">
    <div class="workshops-container">

        <h1 class="workshops-page-title"><?php echo $is_en ? 'Workshops for Everyone' : 'Workshops f&uuml;r jeden'; ?></h1>

        <div class="workshops-intro">
            <div class="workshops-hero-image">
                <img src="https://micinterart.de/wp-content/uploads/2026/06/5341273144251062761_121.jpg" alt="micinterart Atelier" / loading="eager" decoding="async" fetchpriority="high" width="1400" height="400">
            </div>
            <h2><?php echo $is_en ? 'I invite you to my studio.' : 'Ich lade dich ein in mein Atelier.'; ?></h2>
            <p><?php 
                if ($is_en) {
                    echo 'This is the place where you can switch off your mind and just create. Whether you are treating yourself to a timeout, laughing with friends, spending a special evening as a couple, or giving your children an unforgettable day – I will guide you and ensure you feel comfortable from the very first moment. The materials, the cocktails, the wine, the snacks – I will take care of everything. You only need to bring yourself.';
                } else {
                    echo 'Hier ist der Ort, an dem du den Kopf ausschalten und einfach mal machen darfst. Ob du dir eine Auszeit gönnst, mit Freundinnen lachst, als Paar einen besonderen Abend verbringst oder deinen Kindern einen unvergesslichen Tag schenkst – ich begleite euch und sorge dafür, dass ihr euch vom ersten Moment an wohlfühlt. Die Materialien, die Cocktails, der Wein, die Snacks – darum kümmere ich mich. Ihr bringt nur euch mit.';
                }
            ?></p>
            <p><strong><?php echo $is_en ? 'Come on by, I look forward to seeing you!' : 'Komm vorbei, ich freue mich auf dich!'; ?></strong></p>
        </div>

        <?php
        // ========================================
        // Daten fuer Workshops VORZIEHEN (fuer Terminuebersicht)
        // ========================================
        $heute = date('Y-m-d');

        $args_upcoming = [
            'post_type'      => 'workshop',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'OR',
                [ 'key' => '_workshop_datum', 'value' => $heute, 'compare' => '>=', 'type' => 'DATE' ],
                [ 'key' => '_workshop_datum', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_workshop_datum', 'value' => '', 'compare' => '=' ],
            ],
            'orderby'  => 'meta_value',
            'meta_key' => '_workshop_datum',
            'order'    => 'ASC'
        ];
        $args_past = [
            'post_type'      => 'workshop',
            'posts_per_page' => -1,
            'meta_query'     => [
                [ 'key' => '_workshop_datum', 'value' => $heute, 'compare' => '<', 'type' => 'DATE' ],
            ],
            'orderby'  => 'meta_value',
            'meta_key' => '_workshop_datum',
            'order'    => 'DESC'
        ];

        $upcoming_workshops = new WP_Query($args_upcoming);
        $past_workshops     = new WP_Query($args_past);

        $kinder_upcoming      = [];
        $erwachsenen_upcoming = [];
        $archiv_workshops     = [];

        if ($upcoming_workshops->have_posts()) {
            while ($upcoming_workshops->have_posts()) {
                $upcoming_workshops->the_post();
                $post_id = get_the_ID();
                
                // Themen abfragen für automatische Datums- und Preisermittlung
                $themen = get_posts([
                    'post_type'      => 'workshop_thema',
                    'posts_per_page' => -1,
                    'post_status'    => 'publish',
                    'meta_query'     => [['key' => '_thema_workshop_id', 'value' => $post_id, 'compare' => '=']],
                    'meta_key'       => '_thema_datum',
                    'orderby'        => 'meta_value',
                    'order'          => 'ASC'
                ]);

                $datum = get_post_meta($post_id, '_workshop_datum', true);
                $frequenz = get_post_meta($post_id, '_workshop_wiederholung_frequenz', true);
                
                // 1. Wenn Serie: Nächsten Termin berechnen falls Hauptdatum vergangen oder leer
                if (!empty($frequenz) && !empty($datum)) {
                    try {
                        $interval_map = ['woechentlich'=>'P1W', 'zweiwoechentlich'=>'P2W', 'monatlich'=>'P1M'];
                        if (isset($interval_map[$frequenz])) {
                            $current_date = new DateTime($datum);
                            $heute_dt = new DateTime($heute);
                            $interval = new DateInterval($interval_map[$frequenz]);
                            // Solange das Datum in der Vergangenheit liegt, Interval addieren
                            while ($current_date < $heute_dt) {
                                $current_date->add($interval);
                            }
                            $datum = $current_date->format('Y-m-d');
                        }
                    } catch (Exception $e) {}
                }

                // 2. Falls Themen existieren: Das Datum des nächsten anstehenden Themas hat Vorrang
                if (!empty($themen)) {
                    $found_theme_date = false;
                    foreach ($themen as $t) {
                        $t_d = get_post_meta($t->ID, '_thema_datum', true);
                        if ($t_d >= $heute) {
                            $datum = $t_d;
                            $found_theme_date = true;
                            break;
                        }
                    }
                }

                $is_kinderworkshop      = false;
                $is_erwachsenenworkshop = false;
                $categories = get_the_terms($post_id, 'workshop_kategorie');
                if ($categories && !is_wp_error($categories)) {
                    foreach ($categories as $category) {
                        if ($category->slug === 'kinderworkshops')      { $is_kinderworkshop = true; }
                        if ($category->slug === 'erwachsenenworkshops') { $is_erwachsenenworkshop = true; }
                    }
                }
                if (!$is_kinderworkshop && !$is_erwachsenenworkshop) { $is_erwachsenenworkshop = true; }
                $status   = get_post_meta($post_id, '_workshop_status', true);
                $monat_id = get_post_meta($post_id, '_workshop_monat_id', true);
                $workshop_data = [
                    'post'           => get_post($post_id),
                    'status'         => $status ?: 'geplant',
                    'datum'          => $datum,
                    'monat_id'       => $monat_id,
                    'nach_absprache' => (empty($datum) && empty($themen) && empty($frequenz)),
                    'is_kind'        => $is_kinderworkshop,
                ];
                if ($is_kinderworkshop)      { $kinder_upcoming[]      = $workshop_data; }
                if ($is_erwachsenenworkshop) { $erwachsenen_upcoming[] = $workshop_data; }
            }
            wp_reset_postdata();
        }

        // Sortierung der Arrays: Workshops mit festem Datum nach oben (ASC), danach "Nach Absprache"
        $workshop_sorter = function($a, $b) {
            if (empty($a['datum']) && empty($b['datum'])) return 0;
            if (empty($a['datum'])) return 1;
            if (empty($b['datum'])) return -1;
            return strcmp($a['datum'], $b['datum']);
        };
        usort($erwachsenen_upcoming, $workshop_sorter);
        usort($kinder_upcoming, $workshop_sorter);

        if ($past_workshops->have_posts()) {
            while ($past_workshops->have_posts()) {
                $past_workshops->the_post();
                $post_id  = get_the_ID();
                $status   = get_post_meta($post_id, '_workshop_status',   true);
                $datum    = get_post_meta($post_id, '_workshop_datum',     true);
                $monat_id = get_post_meta($post_id, '_workshop_monat_id', true);
                $archiv_workshops[] = [
                    'post'           => get_post($post_id),
                    'status'         => $status ?: 'beendet',
                    'datum'          => $datum,
                    'monat_id'       => $monat_id,
                    'nach_absprache' => false,
                ];
            }
            wp_reset_postdata();
        }

        // ========================================
        // Terminuebersicht: Alle Termine (Themen- Termine bei Reihen) - nur naechste 6
        // - Workshops mit 'Nach Absprache' erscheinen hier nicht
        // - Wenn ein Workshop Themen (workshop_thema) hat, werden ALLE kommenden Themen-Termine gelistet
        // - Insgesamt werden nur die naechsten 6 Termine (ueber alle Workshops hinweg) angezeigt
        // ========================================

        $heute_str = date('Y-m-d');

        // Workshop-Index (ID -> Meta fuer die Anzeige)
        $workshop_index = array();
        foreach ($erwachsenen_upcoming as $w) {
            $wid_tmp = $w['post']->ID;
            if (!isset($workshop_index[$wid_tmp])) {
                $workshop_index[$wid_tmp] = array('post' => $w['post'], 'audience' => array());
            }
            if (!in_array('Erwachsene', $workshop_index[$wid_tmp]['audience'], true)) {
                $workshop_index[$wid_tmp]['audience'][] = 'Erwachsene';
            }
        }
        foreach ($kinder_upcoming as $w) {
            $wid_tmp = $w['post']->ID;
            if (!isset($workshop_index[$wid_tmp])) {
                $workshop_index[$wid_tmp] = array('post' => $w['post'], 'audience' => array());
            }
            if (!in_array('Kinder', $workshop_index[$wid_tmp]['audience'], true)) {
                $workshop_index[$wid_tmp]['audience'][] = 'Kinder';
            }
        }

        $alle_termine_rows = array();

        foreach ($workshop_index as $wid => $meta) {
            // 1) Themen-Termine holen (falls vorhanden)
            $themen = get_posts(array(
                'post_type'      => 'workshop_thema',
                'posts_per_page' => -1,
                'orderby'        => 'meta_value',
                'meta_key'       => '_thema_datum',
                'order'          => 'ASC',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array('key' => '_thema_workshop_id', 'value' => $wid),
                    array('key' => '_thema_datum', 'value' => $heute_str, 'compare' => '>=', 'type' => 'DATE'),
                ),
            ));

            if (!empty($themen)) {
                foreach ($themen as $thema_post) {
                    $t_datum = get_post_meta($thema_post->ID, '_thema_datum', true);
                    if (empty($t_datum)) {
                        continue; // ohne Datum nicht anzeigen
                    }
                    $alle_termine_rows[] = array(
                        'datum'    => $t_datum,
                        'workshop' => $wid,
                        'thema'    => $thema_post->ID,
                        'audience' => !empty($meta['audience']) ? $meta['audience'] : array(),
                    );
                }
                continue;
            }

            // 2) Fallback: Workshop-eigenes Datum
            $w_datum = get_post_meta($wid, '_workshop_datum', true);
            if (empty($w_datum)) {
                continue; // Nach Absprache: nicht anzeigen
            }
            $alle_termine_rows[] = array(
                'datum'    => $w_datum,
                'workshop' => $wid,
                'thema'    => null,
                'audience' => !empty($meta['audience']) ? $meta['audience'] : array(),
            );
        }

        // Sortieren nach Datum ASC
        usort($alle_termine_rows, function($a, $b) {
            return strcmp($a['datum'], $b['datum']);
        });

        // Nur die naechsten 6 Termine
        $alle_termine_rows = array_slice($alle_termine_rows, 0, 6);

        ?>

        <?php if (!empty($alle_termine_rows)) : ?>

        <div class="termin-uebersicht">
            <div class="termin-uebersicht-header">
                <h2>&#128197; <?php echo $is_en ? 'All Dates at a Glance' : 'Alle Termine auf einen Blick'; ?></h2>
                <p><?php echo $is_en ? 'Quick overview of all current workshops' : 'Schneller &Uuml;berblick &uuml;ber alle aktuellen Workshops'; ?></p>
            </div>
            <table class="termin-uebersicht-table">
                <thead>
                    <tr>
                        <th><?php echo $is_en ? 'Date' : 'Datum'; ?></th>
                        <th><?php echo $is_en ? 'Title' : 'Titel'; ?></th>
                        <th><?php echo $is_en ? 'For Whom' : 'F&uuml;r wen'; ?></th>
                        <th><?php echo $is_en ? 'Price' : 'Preis'; ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($alle_termine_rows as $row):
                    $wid     = (int) $row['workshop'];
                    $thema_id = !empty($row['thema']) ? (int) $row['thema'] : 0;
                    
                    // Preis-Ermittlung für die Tabellen-Zeile
                    $display_preis = '';
                    $thema_preis = $thema_id ? get_post_meta($thema_id, '_thema_preis', true) : '';

                    if (!empty($thema_preis)) {
                        $display_preis = $thema_preis;
                    } else {
                        $preise = [];
                        $ws_themen = get_posts(['post_type'=>'workshop_thema','posts_per_page'=>-1,'meta_query'=>[['key'=>'_thema_workshop_id','value'=>$wid]]]);
                        foreach($ws_themen as $wt) {
                            $p_raw = get_post_meta($wt->ID, '_thema_preis', true);
                            if ($p_raw) $preise[] = floatval(preg_replace('/[^0-9.]/', '', str_replace(',', '.', $p_raw)));
                        }
                        if (empty($preise)) {
                            $p_ws = get_post_meta($wid, '_workshop_preis', true);
                            if ($p_ws) {
                                $display_preis = $is_en ? '€ ' . number_format((float)$p_ws, 2, '.', ',') : number_format((float)$p_ws, 2, ',', '.') . ' €';
                            } else {
                                $display_preis = '';
                            }
                        } else {
                            $min_p = min($preise); $max_p = max($preise);
                            if ($min_p != $max_p) {
                                $display_preis = $is_en ? 'from € ' . number_format($min_p, 2, '.', ',') : 'ab ' . number_format($min_p, 2, ',', '.') . ' €';
                            } else {
                                $display_preis = $is_en ? '€ ' . number_format($min_p, 2, '.', ',') : number_format($min_p, 2, ',', '.') . ' €';
                            }
                        }
                    }

                    $wdatum  = date_i18n('D, d. M Y', strtotime($row['datum']));
                    $tags = array();
                    $aud = !empty($row['audience']) ? (array) $row['audience'] : array();
                    if (in_array('Kinder', $aud, true)) { $tags[] = array('label' => $is_en ? 'Kids' : 'Kinder', 'class' => 'tu-tag-kind'); }
                    if (in_array('Erwachsene', $aud, true)) { $tags[] = array('label' => $is_en ? 'Adults' : 'Erwachsene', 'class' => 'tu-tag-erw'); }
                    if (empty($tags)) { $tags[] = array('label' => '—', 'class' => ''); }
                    $wlink   = get_permalink($wid);
                ?>
                    <tr>
                        <td class="tu-datum" data-label="<?php echo $is_en ? 'Date' : 'Datum'; ?>"><?php echo esc_html($wdatum); ?></td>
                        <td class="tu-titel" data-label="<?php echo $is_en ? 'Title' : 'Titel'; ?>"><a href="<?php echo esc_url($wlink); ?>"><?php echo esc_html($thema_id ? get_the_title($thema_id) : get_the_title($wid)); ?></a></td>
                        <td data-label="<?php echo $is_en ? 'For Whom' : 'F&uuml;r wen'; ?>"><?php foreach ($tags as $t): ?><span class="tu-tag <?php echo !empty($t['class']) ? esc_attr($t['class']) : ''; ?>" style="margin-right:6px; display:inline-block;"><?php echo esc_html($t['label']); ?></span><?php endforeach; ?></td>
                        <td class="tu-preis" data-label="<?php echo $is_en ? 'Price' : 'Preis'; ?>"><?php echo $display_preis ? esc_html($display_preis) : '&mdash;'; ?></td>
                        <td><a class="tu-cta" href="<?php echo esc_url($wlink); ?>">Details &rarr;</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>



<?php
if (!function_exists('micinterart_get_freie_plaetze')) {
function micinterart_get_freie_plaetze($post_id) {
    $heute_str = date('Y-m-d');
    $naechste_themen = get_posts(array(
        'post_type'      => 'workshop_thema',
        'posts_per_page' => 1,
        'orderby'        => 'meta_value',
        'meta_key'       => '_thema_datum',
        'order'          => 'ASC',
        'meta_query'     => array(
            'relation' => 'AND',
            array('key' => '_thema_workshop_id', 'value' => $post_id),
            array('key' => '_thema_datum', 'value' => $heute_str, 'compare' => '>=', 'type' => 'DATE'),
        ),
    ));
    if (!empty($naechste_themen)) {
        $thema_id  = $naechste_themen[0]->ID;
        $t_max     = (int) get_post_meta($thema_id, '_thema_max_teilnehmer',    true);
        $t_current = (int) get_post_meta($thema_id, '_thema_current_bookings', true);
        if ($t_max > 0) {
            return array('max' => $t_max, 'frei' => max(0, $t_max - $t_current));
        }
    }
    $ws_max     = (int) get_post_meta($post_id, '_workshop_max_teilnehmer',    true);
    $ws_current = (int) get_post_meta($post_id, '_workshop_current_bookings', true);
    if ($ws_max > 0) {
        return array('max' => $ws_max, 'frei' => max(0, $ws_max - $ws_current));
    }
    return null;
}
}
?>

<?php
function micinterart_get_workshop_thumbnail_id($post_id) {
    $heute_str = date('Y-m-d');
    $naechste_themen = get_posts(array(
        'post_type'      => 'workshop_thema',
        'posts_per_page' => 1,
        'orderby'        => 'meta_value',
        'meta_key'       => '_thema_datum',
        'order'          => 'ASC',
        'meta_query'     => array(
            'relation' => 'AND',
            array('key' => '_thema_workshop_id', 'value' => $post_id),
            array('key' => '_thema_datum', 'value' => $heute_str, 'compare' => '>=', 'type' => 'DATE'),
        ),
    ));
    if (!empty($naechste_themen)) {
        $thema_id = $naechste_themen[0]->ID;
        if (has_post_thumbnail($thema_id)) {
            return get_post_thumbnail_id($thema_id);
        }
    }
    if (has_post_thumbnail($post_id)) {
        return get_post_thumbnail_id($post_id);
    }
    return null;
}
?>

<?php
function render_workshop_card($workshop_data, $is_past = false) {
    $is_en = (function_exists('pll_current_language') && pll_current_language() === 'en');
    $post    = $workshop_data['post'];
    $post_id = $post->ID;
    $status  = $workshop_data['status'];
    $datum   = $workshop_data['datum'];
    $monat_id = $workshop_data['monat_id'];

    $is_kinderworkshop = false;
    $categories = get_the_terms($post_id, 'workshop_kategorie');
    if ($categories && !is_wp_error($categories)) {
        foreach ($categories as $category) {
            if ($category->slug === 'kinderworkshops') { $is_kinderworkshop = true; }
        }
    }

    $uhrzeit_von = get_post_meta($post_id, '_workshop_uhrzeit_von',    true);
    $uhrzeit_bis = get_post_meta($post_id, '_workshop_uhrzeit_bis',    true);
    $alter_von   = get_post_meta($post_id, '_workshop_alter_von',      true);
    $alter_bis   = get_post_meta($post_id, '_workshop_alter_bis',      true);
    $preis_info  = get_post_meta($post_id, '_workshop_preis_info',     true);
    $is_paar     = get_post_meta($post_id, '_workshop_is_paar_preis',  true);

    // Dynamische Ermittlung von Datum, Ort und Preis (Themen-basiert)
    $themen = get_posts(['post_type'=>'workshop_thema','posts_per_page'=>-1,'meta_query'=>[['key'=>'_thema_workshop_id','value'=>$post_id]],'meta_key'=>'_thema_datum','orderby'=>'meta_value','order'=>'ASC']);
    $heute_str = date('Y-m-d');
    $display_datum = $datum;
    $display_ort = get_post_meta($post_id, '_workshop_ort', true);
    $display_uhrzeit_von = $uhrzeit_von;
    $display_uhrzeit_bis = $uhrzeit_bis;
    $themen_preise = [];
    
    if (!empty($themen)) {
        $next_thema = null;
        foreach ($themen as $t) {
            $t_d = get_post_meta($t->ID, '_thema_datum', true);
            if ($t_d >= $heute_str && !$next_thema) $next_thema = $t;
            $p_raw = get_post_meta($t->ID, '_thema_preis', true);
            if ($p_raw) {
                $p_num = floatval(preg_replace('/[^0-9.]/', '', str_replace(',', '.', $p_raw)));
                if ($p_num > 0) $themen_preise[] = $p_num;
            }
        }
        if ($next_thema) {
            $display_datum = get_post_meta($next_thema->ID, '_thema_datum', true);
            $display_ort = get_post_meta($next_thema->ID, '_thema_ort', true) ?: $display_ort;
            $display_uhrzeit_von = get_post_meta($next_thema->ID, '_thema_uhrzeit_von', true) ?: $display_uhrzeit_von;
            $display_uhrzeit_bis = get_post_meta($next_thema->ID, '_thema_uhrzeit_bis', true) ?: $display_uhrzeit_bis;
        }
    }

    $display_preis_html = '';
    if (!empty($themen_preise)) {
        $min_p = min($themen_preise); $max_p = max($themen_preise);
        if ($min_p != $max_p) {
            $display_preis_html = $is_en ? 'from € ' . number_format($min_p, 2, '.', ',') : 'ab ' . number_format($min_p, 2, ',', '.') . ' €';
        } else {
            $display_preis_html = $is_en ? '€ ' . number_format($min_p, 2, '.', ',') : number_format($min_p, 2, ',', '.') . ' €';
        }
    } else {
        $p_ws = get_post_meta($post_id, '_workshop_preis', true);
        if ($p_ws > 0) {
            $display_preis_html = $is_en ? '€ ' . number_format((float)$p_ws, 2, '.', ',') : number_format((float)$p_ws, 2, ',', '.') . ' €';
        }
    }

    $plaetze_data = null;
    if (!$is_past && !empty($datum)) {
        $plaetze_data = micinterart_get_freie_plaetze($post_id);
    }

    if ($preis_info)          { $preis_suffix = $preis_info; }
    elseif ($is_paar === 'yes') { $preis_suffix = $is_en ? 'per couple' : 'pro Paar'; }
    elseif ($is_kinderworkshop) { $preis_suffix = $is_en ? 'per child' : 'pro Kind'; }
    else                        { $preis_suffix = $is_en ? 'per person' : 'pro Person'; }

    $status_labels = $is_en ? array(
        'geplant'         => 'Planned',
        'anmeldung_offen' => 'Registration open',
        'fast_ausgebucht' => 'Almost booked out',
        'ausgebucht'      => 'Fully booked',
        'beendet'         => 'Ended',
        'abgesagt'        => 'Cancelled',
    ) : array(
        'geplant'         => 'Geplant',
        'anmeldung_offen' => 'Anmeldung offen',
        'fast_ausgebucht' => 'Fast ausgebucht',
        'ausgebucht'      => 'Ausgebucht',
        'beendet'         => 'Beendet',
        'abgesagt'        => 'Abgesagt',
    );

    $card_class = $is_past ? 'workshop-card past' : 'workshop-card';
    ?>
    <article class="<?php echo $card_class; ?>"
             data-status="<?php echo esc_attr($status); ?>"
             data-monat-id="<?php echo esc_attr($monat_id ? intval($monat_id) : ''); ?>">

        <span class="workshop-status-badge status-<?php echo esc_attr($status); ?>">
            <?php echo esc_html(isset($status_labels[$status]) ? $status_labels[$status] : $status); ?>
        </span>

        <?php $thumb_id = micinterart_get_workshop_thumbnail_id($post_id); ?>
        <?php if ($thumb_id) : ?>
            <a href="<?php echo get_permalink($post_id); ?>">
                <?php echo wp_get_attachment_image($thumb_id, 'medium_large', false, array('class' => 'workshop-thumbnail')); ?>
            </a>
        <?php else : ?>
            <div class="workshop-thumbnail" style="display:flex;align-items:center;justify-content:center;background:#f0f0f0;">
                <span style="font-size:60px;">&#127912;</span>
            </div>
        <?php endif; ?>

        <div class="workshop-content">
            <h2 class="workshop-title">
                <a href="<?php echo get_permalink($post_id); ?>"><?php echo get_the_title($post_id); ?></a>
            </h2>

            <div class="workshop-meta">
                <div class="workshop-meta-item">
                    <strong>&#128197; <?php echo $is_en ? 'Date:' : 'Datum:'; ?></strong>
                    <span><?php echo ($display_datum && !empty($display_datum)) ? esc_html(date_i18n('d.m.Y', strtotime($display_datum))) : ($is_en ? 'By arrangement' : 'Nach Absprache'); ?></span>
                </div>
                <?php if ($display_uhrzeit_von) : ?>
                    <div class="workshop-meta-item">
                        <strong>&#128336; <?php echo $is_en ? 'Time:' : 'Uhrzeit:'; ?></strong>
                        <span><?php echo esc_html($display_uhrzeit_von); ?><?php echo $display_uhrzeit_bis ? ' - ' . esc_html($display_uhrzeit_bis) : ''; ?><?php echo $is_en ? ' hrs' : ' Uhr'; ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($alter_von && $alter_bis) : ?>
                    <div class="workshop-meta-item">
                        <strong>&#128118; <?php echo $is_en ? 'Age:' : 'Alter:'; ?></strong>
                        <span><?php echo esc_html($alter_von . '-' . $alter_bis); ?><?php echo $is_en ? ' years' : ' Jahre'; ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($display_ort) : ?>
                    <div class="workshop-meta-item">
                        <strong>&#128205; <?php echo $is_en ? 'Location:' : 'Ort:'; ?></strong>
                        <span><?php echo esc_html($display_ort); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (false && $plaetze_data !== null) : ?>
                    <div class="workshop-meta-item">
                        <strong>&#128101; <?php echo $is_en ? 'Spots:' : 'Pl&auml;tze:'; ?></strong>
                        <span>
                            <?php if ($plaetze_data['frei'] <= 0) : ?>
                                <span style="color:#d32f2f;font-weight:700;"><?php echo $is_en ? 'Fully Booked' : 'Ausgebucht'; ?></span>
                            <?php elseif ($plaetze_data['frei'] <= 3) : ?>
                                <span style="color:#f57c00;font-weight:700;"><?php 
                                    if ($is_en) {
                                        echo 'Only ' . esc_html($plaetze_data['frei']) . ' ' . (($plaetze_data['frei'] === 1) ? 'spot' : 'spots') . ' left!';
                                    } else {
                                        echo 'Nur noch ' . esc_html($plaetze_data['frei']) . ' ' . (($plaetze_data['frei'] === 1) ? 'Platz' : 'Pl&auml;tze') . ' frei!';
                                    }
                                ?></span>
                            <?php else : ?>
                                <?php echo $is_en ? esc_html($plaetze_data['frei']) . ' spots available' : 'Noch ' . esc_html($plaetze_data['frei']) . ' Pl&auml;tze frei'; ?>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <?php 
            $excerpt_post_id = (!empty($themen) && !empty($next_thema)) ? $next_thema->ID : $post_id;
            $content = get_post_field('post_content', $excerpt_post_id);
            $clean_text = wp_strip_all_tags($content);
            if (!empty($clean_text)) :
                ?>
                <div class="workshop-excerpt">
                    <?php echo wp_trim_words($clean_text, 20, '... <a href="' . esc_url(get_permalink($post_id)) . '">' . ($is_en ? 'read more' : 'mehr erfahren') . '</a>'); ?>
                </div>
            <?php endif; ?>

            <div class="workshop-footer">
                <div class="workshop-preis">
                    <?php if ($display_preis_html) : ?>
                        <?php echo $display_preis_html; ?>
                        <div style="font-size:0.7em;font-weight:400;color:#666;margin-top:3px;"><?php echo esc_html($preis_suffix); ?></div>
                    <?php else : ?>
                        <span style="font-size:0.9em;"><?php echo $is_en ? 'Price on request' : 'Preis auf Anfrage'; ?></span>
                    <?php endif; ?>
                </div>
                <a href="<?php echo get_permalink($post_id); ?>" class="workshop-button">
                    <?php echo $is_past ? 'Details' : ($is_en ? 'More Info' : 'Mehr Infos'); ?>
                </a>
            </div>
        </div>
    </article>
    <?php
}
?>

<?php
function render_workshop_hero_card($workshop_data) {
    $is_en = (function_exists('pll_current_language') && pll_current_language() === 'en');
    $post    = $workshop_data['post'];
    $post_id = $post->ID;
    $status  = $workshop_data['status'];
    $datum   = $workshop_data['datum'];

    $is_kinderworkshop = false;
    $categories = get_the_terms($post_id, 'workshop_kategorie');
    if ($categories && !is_wp_error($categories)) {
        foreach ($categories as $category) {
            if ($category->slug === 'kinderworkshops') { $is_kinderworkshop = true; }
        }
    }

    $uhrzeit_von = get_post_meta($post_id, '_workshop_uhrzeit_von',   true);
    $uhrzeit_bis = get_post_meta($post_id, '_workshop_uhrzeit_bis',   true);
    $alter_von   = get_post_meta($post_id, '_workshop_alter_von',     true);
    $alter_bis   = get_post_meta($post_id, '_workshop_alter_bis',     true);
    $preis_info  = get_post_meta($post_id, '_workshop_preis_info',    true);
    $is_paar     = get_post_meta($post_id, '_workshop_is_paar_preis', true);

    // Dynamische Ermittlung von Datum, Ort und Preis für Hero Card
    $themen = get_posts(['post_type'=>'workshop_thema','posts_per_page'=>-1,'meta_query'=>[['key'=>'_thema_workshop_id','value'=>$post_id]],'meta_key'=>'_thema_datum','orderby'=>'meta_value','order'=>'ASC']);
    $heute_str = date('Y-m-d');
    $display_datum = $datum;
    $display_ort = get_post_meta($post_id, '_workshop_ort', true);
    $display_uhrzeit_von = $uhrzeit_von;
    $display_uhrzeit_bis = $uhrzeit_bis;
    $themen_preise = [];
    
    if (!empty($themen)) {
        $next_thema = null;
        foreach ($themen as $t) {
            $t_d = get_post_meta($t->ID, '_thema_datum', true);
            if ($t_d >= $heute_str && !$next_thema) $next_thema = $t;
            $p_raw = get_post_meta($t->ID, '_thema_preis', true);
            if ($p_raw) {
                $p_num = floatval(preg_replace('/[^0-9.]/', '', str_replace(',', '.', $p_raw)));
                if ($p_num > 0) $themen_preise[] = $p_num;
            }
        }
        if ($next_thema) {
            $display_datum = get_post_meta($next_thema->ID, '_thema_datum', true);
            $display_ort = get_post_meta($next_thema->ID, '_thema_ort', true) ?: $display_ort;
            $display_uhrzeit_von = get_post_meta($next_thema->ID, '_thema_uhrzeit_von', true) ?: $display_uhrzeit_von;
            $display_uhrzeit_bis = get_post_meta($next_thema->ID, '_thema_uhrzeit_bis', true) ?: $display_uhrzeit_bis;
        }
    }

    $display_preis_html = '';
    if (!empty($themen_preise)) {
        $min_p = min($themen_preise); $max_p = max($themen_preise);
        if ($min_p != $max_p) {
            $display_preis_html = $is_en ? 'from € ' . number_format($min_p, 2, '.', ',') : 'ab ' . number_format($min_p, 2, ',', '.') . ' €';
        } else {
            $display_preis_html = $is_en ? '€ ' . number_format($min_p, 2, '.', ',') : number_format($min_p, 2, ',', '.') . ' €';
        }
    } else {
        $p_ws = get_post_meta($post_id, '_workshop_preis', true);
        if ($p_ws > 0) {
            $display_preis_html = $is_en ? '€ ' . number_format((float)$p_ws, 2, '.', ',') : number_format((float)$p_ws, 2, ',', '.') . ' €';
        }
    }

    $plaetze_data = micinterart_get_freie_plaetze($post_id);

    if ($preis_info)            { $preis_suffix = $preis_info; }
    elseif ($is_paar === 'yes') { $preis_suffix = $is_en ? 'per couple' : 'pro Paar'; }
    elseif ($is_kinderworkshop) { $preis_suffix = $is_en ? 'per child' : 'pro Kind'; }
    else                        { $preis_suffix = $is_en ? 'per person' : 'pro Person'; }

    $status_labels = $is_en ? array(
        'geplant'         => 'Planned',
        'anmeldung_offen' => 'Registration open',
        'fast_ausgebucht' => 'Almost booked out',
        'ausgebucht'      => 'Fully booked',
        'beendet'         => 'Ended',
        'abgesagt'        => 'Cancelled',
    ) : array(
        'geplant'         => 'Geplant',
        'anmeldung_offen' => 'Anmeldung offen',
        'fast_ausgebucht' => 'Fast ausgebucht',
        'ausgebucht'      => 'Ausgebucht',
        'beendet'         => 'Beendet',
        'abgesagt'        => 'Abgesagt',
    );
    ?>
    <div class="workshop-hero-card">
        <span class="hero-badge-next">&#11088; <?php echo $is_en ? 'Next Date' : 'N&auml;chster Termin'; ?></span>

        <div class="hero-image-wrapper">
            <span class="hero-status-badge status-<?php echo esc_attr($status); ?>">
                <?php echo esc_html(isset($status_labels[$status]) ? $status_labels[$status] : $status); ?>
            </span>
            <?php $thumb_id = micinterart_get_workshop_thumbnail_id($post_id); ?>
            <?php if ($thumb_id) : ?>
                <a href="<?php echo get_permalink($post_id); ?>">
                    <?php echo wp_get_attachment_image($thumb_id, 'large', false, array('style' => 'width:100%;height:100%;object-fit:cover;')); ?>
                </a>
            <?php else : ?>
                <div class="hero-image-placeholder">&#127912;</div>
            <?php endif; ?>
        </div>

        <div class="hero-content">
            <h2 class="hero-title">
                <a href="<?php echo get_permalink($post_id); ?>"><?php echo get_the_title($post_id); ?></a>
            </h2>

            <div class="hero-meta">
                <div class="hero-meta-item">
                    <strong>&#128197; <?php echo $is_en ? 'Date:' : 'Datum:'; ?></strong>
                    <span><?php echo ($display_datum && !empty($display_datum)) ? esc_html(date_i18n('l, d. F Y', strtotime($display_datum))) : ($is_en ? 'By arrangement' : 'Nach Absprache'); ?></span>
                </div>
                <?php if ($display_uhrzeit_von) : ?>
                    <div class="hero-meta-item">
                        <strong>&#128336; <?php echo $is_en ? 'Time:' : 'Uhrzeit:'; ?></strong>
                        <span><?php echo esc_html($display_uhrzeit_von); ?><?php echo $display_uhrzeit_bis ? ' - ' . esc_html($display_uhrzeit_bis) : ''; ?><?php echo $is_en ? ' hrs' : ' Uhr'; ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($alter_von && $alter_bis) : ?>
                    <div class="hero-meta-item">
                        <strong>&#128118; <?php echo $is_en ? 'Age:' : 'Alter:'; ?></strong>
                        <span><?php echo esc_html($alter_von . '-' . $alter_bis); ?><?php echo $is_en ? ' years' : ' Jahre'; ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($display_ort) : ?>
                    <div class="hero-meta-item">
                        <strong>&#128205; <?php echo $is_en ? 'Location:' : 'Ort:'; ?></strong>
                        <span><?php echo esc_html($display_ort); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (false && $plaetze_data !== null) : ?>
                    <div class="hero-meta-item">
                        <strong>&#128101; <?php echo $is_en ? 'Spots:' : 'Pl&auml;tze:'; ?></strong>
                        <span>
                            <?php if ($plaetze_data['frei'] <= 0) : ?>
                                <span style="color:#d32f2f;font-weight:700;"><?php echo $is_en ? 'Fully Booked' : 'Ausgebucht'; ?></span>
                            <?php elseif ($plaetze_data['frei'] <= 3) : ?>
                                <span style="color:#f57c00;font-weight:700;"><?php 
                                    if ($is_en) {
                                        echo 'Only ' . esc_html($plaetze_data['frei']) . ' ' . (($plaetze_data['frei'] === 1) ? 'spot' : 'spots') . ' left!';
                                    } else {
                                        echo 'Nur noch ' . esc_html($plaetze_data['frei']) . ' ' . (($plaetze_data['frei'] === 1) ? 'Platz' : 'Pl&auml;tze') . ' frei!';
                                    }
                                ?></span>
                            <?php else : ?>
                                <?php echo $is_en ? esc_html($plaetze_data['frei']) . ' spots available' : 'Noch ' . esc_html($plaetze_data['frei']) . ' Pl&auml;tze frei'; ?>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <?php 
            $excerpt_post_id = (!empty($themen) && !empty($next_thema)) ? $next_thema->ID : $post_id;
            $content = get_post_field('post_content', $excerpt_post_id);
            $clean_text = wp_strip_all_tags($content);
            if (!empty($clean_text)) :
                ?>
                <div class="hero-excerpt">
                    <?php echo wp_trim_words($clean_text, 20, '... <a href="' . esc_url(get_permalink($post_id)) . '">' . ($is_en ? 'read more' : 'mehr erfahren') . '</a>'); ?>
                </div>
            <?php endif; ?>

            <div class="hero-footer">
                <div class="hero-preis">
                    <?php if ($display_preis_html) : ?>
                        <?php echo $display_preis_html; ?>
                        <span class="preis-suffix"><?php echo esc_html($preis_suffix); ?></span>
                    <?php else : ?>
                        <span style="font-size:0.7em;"><?php echo $is_en ? 'Price on request' : 'Preis auf Anfrage'; ?></span>
                    <?php endif; ?>
                </div>
                <a href="<?php echo get_permalink($post_id); ?>" class="hero-button">
                    <?php echo $is_en ? 'Register now &rarr;' : 'Jetzt anmelden &rarr;'; ?>
                </a>
            </div>
        </div>
    </div>
    <?php
}
?>

<?php if (!empty($erwachsenen_upcoming)) : ?>
    <?php
    $hero_erwachsene    = null;
    $weitere_erwachsene = array();
    foreach ($erwachsenen_upcoming as $ws) {
        // Hero Card nur für Workshops, die ein konkretes Datum haben
        if ($hero_erwachsene === null && !empty($ws['datum'])) {
            $hero_erwachsene = $ws;
        } else {
            $weitere_erwachsene[] = $ws;
        }
    }
    ?>
    <div class="workshop-section erwachsene">
        <div class="section-header">
            <h2 class="section-title">&#127912; <?php echo $is_en ? 'Studio Workshops - Space for Ideas' : 'Atelierkurse - Raum f&uuml;r Ideen'; ?></h2>
            <p class="section-subtitle"><?php echo $is_en ? 'Timeouts, Art &amp; Inspiration - enjoy, laugh, create' : 'Auszeiten, Art &amp; Inspiration - genie&szlig;en, lachen, gestalten'; ?></p>
        </div>

        <?php if ($hero_erwachsene) : ?>
            <?php render_workshop_hero_card($hero_erwachsene); ?>

            <?php if (!empty($weitere_erwachsene)) : ?>
                <div class="weitere-toggle">
                    <button class="weitere-toggle-button" data-target="weitere-erwachsene">
                        &#127912; <?php echo $is_en ? 'Show more studio workshops' : 'Weitere Atelierkurse anzeigen'; ?> (<?php echo count($weitere_erwachsene); ?>) <span class="arrow">&#9660;</span>
                    </button>
                </div>
                <div class="weitere-content" id="weitere-erwachsene">
                    <div class="workshops-grid" id="grid-erwachsene">
                        <?php foreach ($weitere_erwachsene as $workshop) { render_workshop_card($workshop); } ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else : ?>
            <div class="workshops-grid" id="grid-erwachsene">
                <?php foreach ($weitere_erwachsene as $workshop) { render_workshop_card($workshop); } ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (!empty($kinder_upcoming)) : ?>
    <?php
    $hero_kinder    = null;
    $weitere_kinder = array();
    foreach ($kinder_upcoming as $ws) {
        // Hero Card nur für Workshops, die ein konkretes Datum haben
        if ($hero_kinder === null && !empty($ws['datum'])) {
            $hero_kinder = $ws;
        } else {
            $weitere_kinder[] = $ws;
        }
    }
    ?>
    <div class="workshop-section kinder">
        <div class="section-header">
            <h2 class="section-title">&#127752; <?php echo $is_en ? 'Young Studio' : 'Junges Atelier'; ?></h2>
            <p class="section-subtitle"><?php echo $is_en ? 'Creative space for art &amp; birthdays' : 'Freier Raum f&uuml;r Kunst &amp; Geburtstage'; ?></p>
        </div>

        <?php if ($hero_kinder) : ?>
            <?php render_workshop_hero_card($hero_kinder); ?>

            <?php if (!empty($weitere_kinder)) : ?>
                <div class="weitere-toggle">
                    <button class="weitere-toggle-button" data-target="weitere-kinder">
                        &#127752; <?php echo $is_en ? "Show more children's workshops" : 'Weitere Kinderworkshops anzeigen'; ?> (<?php echo count($weitere_kinder); ?>) <span class="arrow">&#9660;</span>
                    </button>
                </div>
                <div class="weitere-content" id="weitere-kinder">
                    <div class="workshops-grid" id="grid-kinder">
                        <?php foreach ($weitere_kinder as $workshop) { render_workshop_card($workshop); } ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else : ?>
            <div class="workshops-grid" id="grid-kinder">
                <?php foreach ($weitere_kinder as $workshop) { render_workshop_card($workshop); } ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (empty($erwachsenen_upcoming) && empty($kinder_upcoming)) : ?>
    <div class="no-workshops">
        <p><?php echo $is_en ? 'Currently, no upcoming workshops are available.' : 'Aktuell sind keine kommenden Workshops verf&uuml;gbar.'; ?></p>
    </div>
<?php endif; ?>

        <!-- ========================================
             IMPRESSIONEN-GALERIE
             Liest Bilder von der Seite "Workshop Impressionen" aus
             und zeigt sie zufaellig in einem Masonry-Grid an.
             ======================================== -->
        <?php
        // Seite "Workshop Impressionen" finden (auch private Seiten!)
        $impressionen_page = get_page_by_path('workshop-impressionen', OBJECT, 'page');
        if ($impressionen_page && $impressionen_page->post_status === 'private') {
            // OK - private Seite gefunden
        } elseif (!$impressionen_page || $impressionen_page->post_status !== 'publish') {
            // Fallback: direkt per DB suchen (publish + private)
            global $wpdb;
            $impressionen_page = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'page' AND post_status IN ('publish','private') LIMIT 1",
                    'workshop-impressionen'
                )
            );
            if ($impressionen_page) {
                $impressionen_page = get_post($impressionen_page->ID);
            }
        }

        // Fallback: Per Titel suchen
        if (!$impressionen_page) {
            $pages = get_posts(array(
                'post_type'      => 'page',
                'posts_per_page' => 1,
                'title'          => 'Workshop Impressionen',
                'post_status'    => array('publish', 'private'),
            ));
            if (!empty($pages)) {
                $impressionen_page = $pages[0];
            }
        }

        $gallery_images = array();

        if ($impressionen_page) {
            // 1. Gutenberg Gallery-Block Bilder extrahieren
            $blocks = parse_blocks($impressionen_page->post_content);
            foreach ($blocks as $block) {
                if ($block['blockName'] === 'core/gallery') {
                    if (!empty($block['innerBlocks'])) {
                        foreach ($block['innerBlocks'] as $inner) {
                            if ($inner['blockName'] === 'core/image' && !empty($inner['attrs']['id'])) {
                                $gallery_images[] = intval($inner['attrs']['id']);
                            }
                        }
                    }
                    if (empty($gallery_images) && !empty($block['attrs']['ids'])) {
                        $gallery_images = array_map('intval', $block['attrs']['ids']);
                    }
                }
                // Einzelne Bilder ausserhalb einer Galerie
                if ($block['blockName'] === 'core/image' && !empty($block['attrs']['id'])) {
                    $gallery_images[] = intval($block['attrs']['id']);
                }
            }

            // 2. Fallback: [gallery]-Shortcode
            if (empty($gallery_images)) {
                if (preg_match('/\[gallery[^\]]*ids=["\'?]?([0-9,]+)["\'?]?/', $impressionen_page->post_content, $gm)) {
                    $gallery_images = array_map('intval', explode(',', $gm[1]));
                }
            }

            // 3. Letzter Fallback: Angehaengte Bilder
            if (empty($gallery_images)) {
                $attached = get_posts(array(
                    'post_type'      => 'attachment',
                    'post_mime_type' => 'image',
                    'post_parent'    => $impressionen_page->ID,
                    'posts_per_page' => -1,
                    'post_status'    => 'inherit',
                ));
                foreach ($attached as $att) {
                    $gallery_images[] = $att->ID;
                }
            }
        }

        if (!empty($gallery_images)) :
            shuffle($gallery_images);
            $gallery_images = array_slice($gallery_images, 0, 12);
        ?>
        <div class="impressionen-section">
            <div class="impressionen-header">
                <h2>&#128247; <?php echo $is_en ? 'Studio Impressions' : 'Impressionen aus dem Atelier'; ?></h2>
                <p><?php echo $is_en ? 'Impressions from our workshops' : 'Eindr&uuml;cke aus unseren Workshops'; ?></p>
            </div>
            <div class="impressionen-slider-wrapper">
                <button class="imp-slider-btn imp-slider-prev" id="imp-slider-prev" aria-label="<?php echo $is_en ? 'Previous' : 'Zur&uuml;ck'; ?>">&#10094;</button>
                <button class="imp-slider-btn imp-slider-next" id="imp-slider-next" aria-label="<?php echo $is_en ? 'Next' : 'Weiter'; ?>">&#10095;</button>
                <div class="impressionen-track" id="impressionen-track">
                    <?php foreach ($gallery_images as $img_id) :
                        $img_medium = wp_get_attachment_image_url($img_id, 'medium_large');
                        $img_full   = wp_get_attachment_image_url($img_id, 'full');
                        $img_alt    = get_post_meta($img_id, '_wp_attachment_image_alt', true);
                        if (!$img_alt) { $img_alt = 'Workshop Impression'; }
                        if ($img_medium) :
                    ?>
                        <div class="impressionen-slide" data-full="<?php echo esc_url($img_full); ?>">
                            <img src="<?php echo esc_url($img_medium); ?>"
                                 alt="<?php echo esc_attr($img_alt); ?>"
                                 loading="lazy" />
                        </div>
                    <?php endif; endforeach; ?>
                </div>
            </div>
            <div class="imp-slider-dots" id="imp-slider-dots"></div>
        </div>

        <!-- Lightbox -->
        <div class="mic-lightbox-overlay" id="mic-lightbox">
            <span class="mic-lightbox-close" id="mic-lightbox-close">&times;</span>
            <span class="mic-lightbox-nav mic-lightbox-prev" id="mic-lightbox-prev">&#10094;</span>
            <span class="mic-lightbox-nav mic-lightbox-next" id="mic-lightbox-next">&#10095;</span>
            <img src="" alt="Lightbox" id="mic-lightbox-img" />
        </div>
        <?php endif; ?>

<?php if (!empty($archiv_workshops)) : ?>
    <div class="workshop-section archiv">
        <div class="archiv-toggle">
            <button class="archiv-toggle-button" id="archiv-toggle">
                &#128218; <?php echo $is_en ? 'Show past workshops' : 'Vergangene Workshops anzeigen'; ?> (<?php echo count($archiv_workshops); ?>) <span class="arrow">&#9660;</span>
            </button>
        </div>
        <div class="archiv-content" id="archiv-content">
            <div class="section-header">
                <h2 class="section-title">&#128218; <?php echo $is_en ? 'Archive' : 'Archiv'; ?></h2>
            </div>
            <div class="workshops-grid">
                <?php foreach ($archiv_workshops as $workshop) { render_workshop_card($workshop, true); } ?>
            </div>
        </div>
    </div>
<?php endif; ?>

    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // ---- Archiv Toggle ----
    var archivToggle  = document.getElementById('archiv-toggle');
    var archivContent = document.getElementById('archiv-content');
    if (archivToggle && archivContent) {
        archivToggle.addEventListener('click', function() {
            archivContent.classList.toggle('show');
            archivToggle.classList.toggle('active');
        });
    }

    // ---- Weitere-Toggle ----
    var weitereBtns = document.querySelectorAll('.weitere-toggle-button');
    for (var i = 0; i < weitereBtns.length; i++) {
        weitereBtns[i].addEventListener('click', function() {
            var targetId = this.getAttribute('data-target');
            var targetEl = document.getElementById(targetId);
            if (targetEl) {
                targetEl.classList.toggle('show');
                this.classList.toggle('active');
            }
        });
    }
    // ---- Impressionen Slider ----
    var track      = document.getElementById('impressionen-track');
    var slides     = track ? track.querySelectorAll('.impressionen-slide') : [];
    var prevBtn    = document.getElementById('imp-slider-prev');
    var nextBtn    = document.getElementById('imp-slider-next');
    var dotsWrap   = document.getElementById('imp-slider-dots');
    var sliderPage = 0;

    function getSlidesPerView() {
        if (window.innerWidth <= 600) return 2;
        if (window.innerWidth <= 900) return 3;
        return 4;
    }

    function getTotalPages() {
        var perView = getSlidesPerView();
        return Math.max(1, Math.ceil(slides.length / perView));
    }

    function buildDots() {
        if (!dotsWrap) return;
        dotsWrap.innerHTML = '';
        var total = getTotalPages();
        for (var d = 0; d < total; d++) {
            var dot = document.createElement('button');
            dot.className = 'imp-slider-dot' + (d === sliderPage ? ' active' : '');
            dot.setAttribute('aria-label', 'Seite ' + (d + 1));
            (function(idx) {
                dot.addEventListener('click', function() { goToPage(idx); });
            })(d);
            dotsWrap.appendChild(dot);
        }
    }

    function goToPage(page) {
        var perView = getSlidesPerView();
        var total = getTotalPages();
        if (page < 0) page = total - 1;
        if (page >= total) page = 0;
        sliderPage = page;
        if (slides.length === 0 || !track) return;
        var slide = slides[0];
        var gap = 12;
        var slideW = slide.offsetWidth + gap;
        var offset = sliderPage * perView * slideW;
        var maxOffset = track.scrollWidth - track.parentElement.offsetWidth;
        if (offset > maxOffset) offset = maxOffset;
        track.style.transform = 'translateX(-' + offset + 'px)';
        var dots = dotsWrap ? dotsWrap.querySelectorAll('.imp-slider-dot') : [];
        for (var i = 0; i < dots.length; i++) {
            dots[i].classList.toggle('active', i === sliderPage);
        }
    }

    if (prevBtn) prevBtn.addEventListener('click', function() { goToPage(sliderPage - 1); });
    if (nextBtn) nextBtn.addEventListener('click', function() { goToPage(sliderPage + 1); });

    var autoSlide = null;
    function startAuto() {
        stopAuto();
        autoSlide = setInterval(function() { goToPage(sliderPage + 1); }, 4000);
    }
    function stopAuto() {
        if (autoSlide) { clearInterval(autoSlide); autoSlide = null; }
    }
    if (slides.length > 0) {
        buildDots();
        startAuto();
        var sliderWrapper = track ? track.parentElement : null;
        if (sliderWrapper) {
            sliderWrapper.addEventListener('mouseenter', stopAuto);
            sliderWrapper.addEventListener('mouseleave', startAuto);
        }
    }

    var touchStartX = 0;
    var touchEndX = 0;
    if (track) {
        track.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
            stopAuto();
        }, {passive: true});
        track.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            var diff = touchStartX - touchEndX;
            if (Math.abs(diff) > 50) {
                if (diff > 0) goToPage(sliderPage + 1);
                else goToPage(sliderPage - 1);
            }
            startAuto();
        }, {passive: true});
    }

    var resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            sliderPage = 0;
            buildDots();
            goToPage(0);
        }, 200);
    });

    // ---- Lightbox ----
    var lightbox     = document.getElementById('mic-lightbox');
    var lightboxImg  = document.getElementById('mic-lightbox-img');
    var lightboxClose = document.getElementById('mic-lightbox-close');
    var lightboxPrev = document.getElementById('mic-lightbox-prev');
    var lightboxNext = document.getElementById('mic-lightbox-next');
    var allSlides    = document.querySelectorAll('.impressionen-slide');
    var currentIndex = 0;

    function openLightbox(index) {
        if (!allSlides[index]) return;
        currentIndex = index;
        lightboxImg.src = allSlides[index].getAttribute('data-full');
        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
        stopAuto();
    }
    function closeLightbox() {
        lightbox.classList.remove('active');
        document.body.style.overflow = '';
        lightboxImg.src = '';
        startAuto();
    }

    for (var gi = 0; gi < allSlides.length; gi++) {
        (function(idx) {
            allSlides[idx].addEventListener('click', function() { openLightbox(idx); });
        })(gi);
    }

    if (lightboxClose) lightboxClose.addEventListener('click', closeLightbox);
    if (lightbox) lightbox.addEventListener('click', function(e) {
        if (e.target === lightbox) closeLightbox();
    });
    if (lightboxPrev) lightboxPrev.addEventListener('click', function(e) {
        e.stopPropagation();
        currentIndex = (currentIndex - 1 + allSlides.length) % allSlides.length;
        lightboxImg.src = allSlides[currentIndex].getAttribute('data-full');
    });
    if (lightboxNext) lightboxNext.addEventListener('click', function(e) {
        e.stopPropagation();
        currentIndex = (currentIndex + 1) % allSlides.length;
        lightboxImg.src = allSlides[currentIndex].getAttribute('data-full');
    });
    document.addEventListener('keydown', function(e) {
        if (!lightbox || !lightbox.classList.contains('active')) return;
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft' && lightboxPrev) lightboxPrev.click();
        if (e.key === 'ArrowRight' && lightboxNext) lightboxNext.click();
    });

});
</script>

<?php get_footer(); ?>