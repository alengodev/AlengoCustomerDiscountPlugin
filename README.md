# AlengoCustomerDiscount

Ermöglicht die Vergabe von kundenspezifischen Guthaben-Rabatten, die beim Checkout automatisch als negativer Warenkorb-Posten abgezogen und nach Bestellabschluss vom Kundenkonto abgebucht werden.

## Voraussetzungen

- Shopware 6.5.8+
- PHP 8.3+

## Installation

```bash
bin/console plugin:install --activate AlengoCustomerDiscount
bin/console cache:clear
```

## Konfiguration

Das Plugin verwendet Custom Fields auf der Kunden-Entity, die manuell über die Shopware-Administration gesetzt werden:

**Pfad:** Administration → Kunden → Kunde auswählen → Tab "Einstellungen für Kundenrabatte"

| Custom Field | Typ | Bedeutung |
|---|---|---|
| `alengoCustomerDiscount_name` | Text | Anzeigename des Rabatts (z. B. "Treuebonus 2024") |
| `alengoCustomerDiscount_amount` | Dezimalzahl | Verfügbarer Restbetrag in der Shop-Währung |
| `alengoCustomerDiscount_expirationDate` | Datum | Ablaufdatum (einschliesslich); leer = unbegrenzt gültig |

> Das Ablaufdatum wird inklusiv gewertet: ein Datum von "2024-12-31" bedeutet gültig bis 31.12.2024, 23:59:59.
> Ist kein Ablaufdatum gesetzt, gilt der Rabatt als unbegrenzt gültig.

Nach dem Deaktivieren des Rabattes reicht es, `alengoCustomerDiscount_amount` auf `0` zu setzen oder das Ablaufdatum in die Vergangenheit zu legen.

## Funktionsweise

### Rabatt im Warenkorb

Sobald ein eingeloggter Kunde mit gültigem Rabatt-Guthaben den Warenkorb aufruft, fügt der `CustomerDiscountProcessor` automatisch einen Rabatt-Posten vom Typ `special_discount` hinzu. Der Betrag entspricht dem Minimum aus dem verfügbaren Guthaben und dem aktuellen Warenkorbwert (kein negativer Warenkorb möglich).

Der Rabatt-Posten ist nicht durch den Kunden entfernbar und wird im Checkout als eigene Zeile dargestellt.

### Abzug nach Bestellung

Nach erfolgreichem Bestellabschluss reduziert der `OrderDiscountSubscriber` das `alengoCustomerDiscount_amount`-Feld des Kunden um den tatsächlich abgezogenen Betrag. Das Guthaben wird niemals unter 0 gesetzt.

### Anzeige im Kundenkonto

Auf der Konto-Übersichtsseite (`/account`) wird das verfügbare Guthaben angezeigt, wenn:
- `alengoCustomerDiscount_amount` > 0
- `alengoCustomerDiscount_name` ist gesetzt
- Das Ablaufdatum ist nicht überschritten

Die Anzeige enthält Name, formatierten Betrag (in der Shop-Währung) und — sofern gesetzt — das Ablaufdatum.

## Bekannte Einschränkungen

- **Steuerberechnung:** Der Rabatt wird als absoluter Bruttobetrag ohne steuerliche Aufschlüsselung angewendet. Bei Warenkörben mit mehreren Steuersätzen ist die Aufteilung auf die Steuerpositionen nicht exakt.
- **Race Conditions:** Bei gleichzeitigen Bestellungen desselben Kunden (z. B. mehrere Browser-Tabs) gibt es kein Locking — das Guthaben könnte mehrfach verbraucht werden.
- **Kein Sales-Channel-Filter:** Das Guthaben gilt in allen Sales Channels des Shops.
- **Sprache:** Die Beschreibung im Warenkorb-Posten ("Rabatt gültig bis ...") ist hartcodiert auf Deutsch.
- **Plugin-Updates:** `update()` im Plugin-Bootstrap ist leer — neue Custom Fields werden bei Plugin-Updates nicht automatisch angelegt. Stattdessen muss `plugin:reinstall` ausgeführt werden.

## Entwicklung

```bash
# Tests ausführen
vendor/bin/phpunit --configuration custom/plugins/AlengoCustomerDiscount/phpunit.xml

# Coding Standards prüfen
vendor/bin/php-cs-fixer fix custom/plugins/AlengoCustomerDiscount --dry-run
```

## Changelog

Siehe [CHANGELOG.md](CHANGELOG.md).

## Architektur

Siehe [ARCHITECTURE.md](ARCHITECTURE.md).
