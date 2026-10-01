<?php
/**
 * Template für die Workshops Produkt-Kategorie
 * Leitet zur archive-workshop-wc.php weiter
 * 
 * @package Micinterart
 */

if (!defined('ABSPATH')) {
    exit;
}

// Weiterleitung zur archive-workshop-wc.php
$new_template = locate_template('archive-workshop-wc.php');
if ($new_template) {
    include($new_template);
    exit;
}

// Fallback: Versuche die archive-workshop.php
$new_template = locate_template('archive-workshop.php');
if ($new_template) {
    include($new_template);
    exit;
}

// Letzter Fallback: Zeige eine Fehlermeldung
get_header();
?>
<div style="padding: 60px 20px; text-align: center;">
    <h1>Workshops</h1>
    <p>Die Workshop-Übersicht konnte nicht geladen werden.</p>
</div>
<?php
get_footer();
