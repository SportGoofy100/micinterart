# Workshop WooCommerce Migration - Anleitung

## Übersicht

Dieses Dokument beschreibt die Migration des aktuellen Workshop-Buchungssystems (basierend auf Contact Form 7, eigenen CPTs und manuellen Prozessen) zu einem WooCommerce-basierten System.

## Was sich ändert

### Bisheriges System
- **CPTs**: `workshop` (Haupt-Workshops) und `workshop_thema` (einzelne Termine/Themen)
- **Buchung**: Contact Form 7 mit PayPal-Links
- **Plätze-Zählung**: Manuell über `_workshop_current_bookings` und `_thema_current_bookings`
- **Preisberechnung**: Eigenes System mit Rabatten (Geschwister, Paar, Frühbucher)
- **Gutscheine**: Eigenes System
- **Templates**: `single-workshop.php`, `archive-workshop.php` mit komplexer Logik

### Neues System (WooCommerce)
- **Produkte**: Jeder Workshop-Termin ist ein WooCommerce-Produkt
- **Kategorien**: `Workshops` → `Atelierkurse` und `Kinderworkshops`
- **Buchung**: Standard WooCommerce Checkout
- **Plätze-Zählung**: Lagerbestand (`stock_quantity`) des Produkts
- **Zahlung**: WooCommerce Zahlungsmethoden (PayPal, Überweisung, etc.)
- **Gutscheine**: Native WooCommerce Coupons
- **Rabatte**: 
  - Geschwisterrabatt: 10% für jedes weitere Kind ab dem 2.
  - Paarpreis: Automatisch über Produktpreis
- **Templates**: `single-product-workshop.php`, `archive-workshop-wc.php`

## Dateistruktur

### Neue Dateien
```
micinterart-child/
├── micinterart-workshop-woocommerce.php    # Plugin: Produktfelder & Kategorien
├── includes/
│   ├── workshop-migration.php              # Migrationstool (Admin)
│   ├── workshop-checkout.php              # Checkout-Anpassungen
│   └── workshop-redirects.php              # 301-Weiterleitungen
├── single-product-workshop.php            # Template: Einzelnes Workshop-Produkt
├── archive-workshop-wc.php                # Template: Workshop-Produktarchiv
└── single-product.php                      # Template: WC-Produkt-Router
└── archive-product.php                    # Template: WC-Archiv-Router
```

### Geänderte Dateien
```
micinterart-child/
├── functions.php                          # Neue Includes, Template-Routing
└── style.css (optional)                  # Anpassungen für WC-Workshops
```

### Deaktivierte Dateien (nicht mehr geladen)
```
micinterart-child/includes/
├── workshop-cf7.php                      # DEAKTIVIERT
├── workshop-preisrechner.php             # DEAKTIVIERT
├── workshop-plaetze.php                  # DEAKTIVIERT
├── workshop-rabatt.php                   # TEILWEISE DEAKTIVIERT
├── workshop-thema-bookings.php           # DEAKTIVIERT
├── workshop-thema-sync.php               # DEAKTIVIERT
├── workshop-monat.php                    # DEAKTIVIERT
└── gutschein-integration.php             # DEAKTIVIERT
```

## Einrichtung (Staging)

### Schritt 1: Vorbedingungen prüfen

1. **WooCommerce aktivieren**
   - Plugin muss aktiviert sein
   - Grundkonfiguration abschließen
   
2. **Backup erstellen**
   - Vollständiges Datenbank-Backup
   - Dateisystem-Backup

3. **PHP-Version prüfen**
   - Mindestens PHP 7.4 erforderlich

### Schritt 2: Neue Dateien hochladen

Alle neuen Dateien aus diesem Projekt in `/wp-content/themes/micinterart-child/` hochladen:

```bash
# Neue Dateien
- micinterart-workshop-woocommerce.php
- includes/workshop-migration.php
- includes/workshop-checkout.php
- includes/workshop-redirects.php
- single-product-workshop.php
- archive-workshop-wc.php
- single-product.php
- archive-product.php

# Geänderte Dateien
- functions.php (ersetzt die bestehende)
```

### Schritt 3: Migration vorbereiten

1. **Im WordPress Admin:**
   - Zu `Produkte → Workshop Migration` navigieren
   - Seite öffnen und "Workshops analysieren" klicken
   - Ergebnisse prüfen

2. **Migration ausführen (Testlauf):**
   - "Migration starten" mit aktiviertem "Testlauf" klicken
   - Protokoll prüfen
   - Alle Workshops sollten korrekt erkannt werden

3. **Migration ausführen (echt):**
   - "Testlauf" deaktivieren
   - "Migration starten" klicken
   - Warten bis alle Produkte erstellt sind

### Schritt 4: WooCommerce einrichten

1. **Zahlungsmethoden:**
   - PayPal Payments (kostenlos) aktivieren
   - Überweisung aktivieren
   - Optional: Stripe aktivieren

2. **Versand:**
   - Alle Produkte als "Virtuell" markieren (kein Versand)

3. **Steuern:**
   - Steuersatz für Workshops prüfen (19% oder 7%)

4. **Rechtliche Seiten:**
   - AGB erstellen
   - Widerrufsbelehrung mit Hinweis auf Ausnahme bei Terminbuchungen
   - Datenschutzerklärung prüfen
   
   **Empfohlene Plugins:**
   - Germanized (kostenlos) - für deutsche Rechtstexte
   - German Market (kostenlos) - Alternative

5. **E-Mails:**
   - Bestell-E-Mails prüfen
   - Teilnehmerdaten werden automatisch hinzugefügt

### Schritt 5: Templates testen

1. **Archiv:**
   - `/product-category/workshops/` aufrufen
   - `/product-category/atelierkurse/` aufrufen
   - `/product-category/kinderworkshops/` aufrufen

2. **Einzelseite:**
   - Einzelne Workshop-Produkte aufrufen
   - "In den Warenkorb" testen
   - Checkout-Prozess durchführen

3. **Responsive Design:**
   - Auf verschiedenen Bildschirmgrößen testen

### Schritt 6: Altes System deaktivieren

1. **Nach erfolgreicher Migration:**
   - Contact Form 7 deaktivieren (optional)
   - PayPal IPN-Handler deaktivieren
   - Alte Workshop-CPTs können behalten werden (für Datenhistorik)

### Schritt 7: 301-Weiterleitungen prüfen

- Alte URLs sollten automatisch weiterleiten:
  - `/workshops/mehr-farbe-in-dein-leben/` → `/product/mehr-farbe-in-dein-leben/`
  - `/workshops/` → `/product-category/workshops/`

## Felder Mapping

### Workshop → WooCommerce Produkt

| Bisheriges Feld | Neues Feld | Typ |
|-----------------|------------|-----|
| `_workshop_datum` | `_workshop_datum` | Meta |
| `_workshop_preis` | `price` | WC |
| `_workshop_max_teilnehmer` | `_workshop_max_teilnehmer` + `stock_quantity` | Meta + WC |
| `_workshop_current_bookings` | `_workshop_current_bookings` | Meta |
| `_workshop_ort` | `_workshop_ort` | Meta |
| `_workshop_adresse` | `_workshop_adresse` | Meta |
| `_workshop_status` | `_workshop_status` | Meta |
| `_workshop_preis_info` | `_workshop_preis_info` | Meta |
| `_workshop_is_paar_preis` | `_workshop_is_paar_preis` | Meta |
| `_workshop_alter_von/bis` | `_workshop_alter_von/bis` | Meta |
| `_workshop_sprache` | `_workshop_sprache` | Meta |
| `_workshop_uhrzeit_von/bis` | `_workshop_uhrzeit_von/bis` | Meta |
| `_workshop_erwartet_*_*` | `_workshop_erwartet_*_*` | Meta |
| `workshop_kategorie` | `product_cat` | Taxonomie |

## Rabatt-System

### Geschwisterrabatt
- **Bedingung**: Mindestens 2 Kinderworkshop-Produkte im Warenkorb
- **Rabatt**: 10% für jedes Kind ab dem 2.
- **Berechnung**: Automatisch über `woocommerce_cart_calculate_fees`
- **Anzeige**: Wird als Negativ-Posten im Warenkorb angezeigt

### Paarpreis
- **Funktion**: Wenn `_workshop_is_paar_preis = yes`, gilt der Preis pro Paar
- **Rabatt**: 10% für jedes weitere Paar ab dem 2.
- **Anzeige**: Preis im Frontend mit "pro Paar" markiert

## Teilnehmerfelder (Checkout)

Folgende Felder werden zum Checkout hinzugefügt:

1. **Anzahl der Teilnehmer** (Pflichtfeld)
2. **Namen der Teilnehmer** (Pflichtfeld, kommagetrennt)
3. **Alter der Teilnehmer** (optional, kommagetrennt)
4. **Allergien** (optional)
5. **Bemerkungen** (optional)

Diese Felder werden:
- Validiert
- In der Bestellung gespeichert
- In der Bestell-E-Mail angezeigt
- Im Admin-Bereich sichtbar

## Deployment

### Schritt-für-Schritt

1. **Alle Dateien hochladen**
   ```bash
   cd /pfad/zur/website/wp-content/themes/micinterart-child/
   git pull origin main
   ```

2. **Migration im Admin ausführen**
   - WordPress Admin → Produkte → Workshop Migration
   - "Migration starten" (ohne Testlauf)

3. **WooCommerce einrichten**
   - Zahlungsmethoden
   - Rechtliche Seiten
   - E-Mail-Templates

4. **Testen**
   - Workshop-Produkte anlegen
   - Buchung durchführen
   - E-Mails prüfen

5. **Live schalten**
   - Auf Staging testen
   - Backup erstellen
   - Auf Live deployen

### Rollback

Falls Probleme auftreten:

1. **Backup wiederherstellen**
   - Datenbank-Backup
   - Dateisystem-Backup

2. **Neue Dateien entfernen:**
   ```bash
   rm micinterart-workshop-woocommerce.php
   rm -rf includes/workshop-migration.php
   rm -rf includes/workshop-checkout.php
   rm -rf includes/workshop-redirects.php
   rm single-product-workshop.php
   rm archive-workshop-wc.php
   rm single-product.php
   rm archive-product.php
   ```

3. **Alte functions.php wiederherstellen:**
   ```bash
   cp functions.php.backup functions.php
   ```

## Wichtige Hinweise

1. **Vergangene Workshops**: Werden nicht migriert (bleiben im alten CPT)
2. **Workshop-Monate**: CPT wird nicht mehr verwendet
3. **Workshop-Themen**: Werden zu einzelnen Produkten migriert
4. **Buchungen**: Bestehende Buchungen bleiben in den alten Metadaten
5. **Url-Struktur**: Ändert sich von `/workshops/...` zu `/product/...`

## Support

Bei Fragen oder Problemen:

1. **Protokoll prüfen**: Migration → Protokoll
2. **WooCommerce-Logs**: WooCommerce → Status → Logs
3. **PHP-Error-Logs**: Server-Logs prüfen

## Nächste Schritte

1. ✅ Migration-Skript erstellen
2. ✅ Produktfelder erstellen
3. ✅ Templates anpassen
4. ✅ 301-Weiterleitungen einrichten
5. ✅ Checkout-Anpassungen
6. ⏳ **Auf Staging deployen und testen**
7. ⏳ **Live schalten**

## Technik-Stack

- **WooCommerce**: 7.0+
- **WordPress**: 5.8+
- **PHP**: 7.4+
- **JQuery**: Für Checkout-Anpassungen
- **CSS**: Modernes Grid/Flexbox Layout

## Performance

Die Migration sollte die Performance verbessern:
- Weniger Datenbank-Abfragen
- Weniger komplexe Logik
- Standard-WooCommerce-Funktionen nutzen
- Bessere Caching-Möglichkeiten

## Sicherheit

- Alle Eingaben werden validiert und sanitized
- WooCommerce Sicherheitsstandards werden eingehalten
- Keine direkten SQL-Abfragen (nur WP-Funktionen)
- Nonces für Admin-Formulare

---

**Erstellt**: 2026-09-28  
**Version**: 1.0.0  
**Status**: Bereit für Staging-Test
