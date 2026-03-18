# Changelog

Alle relevanten Änderungen an diesem Plugin werden hier dokumentiert.

Das Format folgt [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).

---

## [0.2.4] - 2026-03-18

### Entfernt
- Leere `config.xml` aus `src/Resources/config/` entfernt — die Datei enthielt keine Plugin-Konfiguration und war ungenutzt

---

## [0.2.3] - 2026-03-16

### Behoben
- Versandkosten-Dopplung in `CustomerDiscountProcessor::process()` entfernt: `$cartTotal` basiert jetzt ausschliesslich auf `$toCalculate->getPrice()->getTotalPrice()`, da Deliveries zum Berechnungszeitpunkt ohnehin noch nicht im Cart-Preis enthalten sind
- Potenzielle NullPointerException in `process()` beseitigt: `$taxRate` und `$taxAmount` riefen `->getCalculatedTaxes()->first()->getTaxRate()` auf, was bei steuerfreien Warenkörben auf `null` fiel — die Variablen wurden vollständig entfernt
- Tote Variablen `$cartNetTotal` und `$taxStatus` aus `process()` entfernt (wurden berechnet, aber nie verwendet)

---

## [0.2.2] - 2025-05-12

### Geändert
- Code-Stil-Korrekturen durch php-cs-fixer (keine funktionalen Änderungen)

---

## [0.2.1] - 2025-05-12

### Geändert
- `CustomerDiscountProcessor` auf `autowire`/`autoconfigure` umgestellt, manuelle Service-Definition entfernt
- `CartDiscountSubscriber` (Promotion-basierter Ansatz) deaktiviert und auskommentiert — ersetzt durch `CustomerDiscountProcessor`
- Zentrale Konstante `LINE_ITEM_TYPE = 'special_discount'` in die Plugin-Hauptklasse verschoben

### Behoben
- Service-Locator-Antipattern im Plugin-Bootstrap entfernt
- `services.xml` auf korrekte Referenz zu `customer.repository` korrigiert

---

## [0.1.0] - 2025-05-12

### Hinzugefügt
- Erste veröffentlichte Version
- `CustomerDiscountProcessor`: Fügt Rabatt-LineItem automatisch in den Warenkorb ein
- `OrderDiscountSubscriber`: Bucht das Guthaben nach Bestellabschluss ab
- `CustomFieldsInstaller`: Legt Custom Field Set `alengoCustomerDiscount` auf der Kunden-Entity an
- Storefront-Template: Anzeige des verfügbaren Guthabens im Kundenkonto
- Unterstützung für Ablaufdatum (inkl. Sonderfall "kein Datum = unbegrenzt gültig")
