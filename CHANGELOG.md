# Changelog

Alle relevanten Änderungen an diesem Plugin werden hier dokumentiert.

Das Format folgt [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).

---

## [v6.6.0] - 2026-08-12

### Behoben
- `AlengoCustomerDiscount::install()` und `::uninstall()` scheiterten bei einer echten Erstinstallation bzw. beim Deinstallieren mit `ServiceNotFoundException`: Der Plugin-Container lädt die eigene `services.xml` nur, wenn das Plugin bereits aktiv ist — während `install()` (vor der Aktivierung) und `uninstall()` (nach dem automatischen Deaktivieren) ist das nie der Fall. `CustomFieldsInstaller` wird jetzt in `getCustomFieldsInstaller()` direkt mit den Core-Repositories (`custom_field_set.repository` u.a.) instanziiert statt über den Plugin-Container aufgelöst. Die vorherige `public="true"`-Korrektur in v0.2.5 hatte nur den Aktivierungspfad abgedeckt, nicht die Erstinstallation.
- `CustomFieldsInstaller`-Service-Definition aus `services.xml` entfernt, da die Klasse nirgends mehr via DI referenziert wird

### Geändert
- Branch- und Versionierungsstrategie umgestellt: Dieser Branch (`sw-6.6`) verfolgt Shopware 6.6, Releases werden als `v6.6.x` getaggt. Zukünftige Shopware-Hauptversionen erhalten eigene Branches (`sw-6.7`, ...) mit entsprechendem Versionspräfix
- `composer.json`: `shopware/core` und `shopware/storefront` auf `^6.6.0` präzisiert (vorher `^6.5.8`)
- Automatisierter GitHub-Actions-Release-Workflow ergänzt (Tag + Plugin-ZIP bei Push auf `sw-6.6`)

### Verifiziert
- Vollständiger Lifecycle (`plugin:install --activate`, `plugin:uninstall`) gegen Shopware 6.6.10.21 getestet — keine Deprecations, keine Signatur-Abweichungen bei den verwendeten Core-APIs (`CartProcessorInterface`, `CartDataCollectorInterface`, `AbsolutePriceCalculator`, `DeliveryProcessor`)

---

## [0.2.5] - 2026-03-18

### Behoben
- `CustomFieldsInstaller`-Service in `services.xml` als `public="true"` markiert — der Symfony-Container entfernt/inlined private Services beim Kompilieren, weshalb der direkte Zugriff via `$this->container->get()` in den Plugin-Lifecycle-Hooks (`install`, `activate`, `uninstall`) mit einer `ServiceNotFoundException` fehlschlug

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
