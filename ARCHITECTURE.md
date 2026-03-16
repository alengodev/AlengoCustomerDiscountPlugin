# Architektur: AlengoCustomerDiscount

## Übersicht

Das Plugin implementiert ein einfaches Guthaben-Rabatt-System ohne eigene Datenbanktabellen. Der Rabattbetrag wird als Custom Field direkt auf dem Shopware-Kunden-Datensatz gespeichert. Die Logik verteilt sich auf zwei voneinander unabhängige Kernel-Hooks: den Cart-Processor (Anzeige im Warenkorb) und den Event-Subscriber (Abbuchung nach Bestellung).

```
Shopware-Kernel
    |
    |-- CartPipeline
    |       |-- CustomerDiscountProcessor (Priority 100)
    |               collect() --> DeliveryProcessor.collect() --> deliveries in CartDataCollection
    |               process() --> liest Customer-CustomFields --> fügt LineItem ein
    |
    |-- EventDispatcher
            |-- CheckoutOrderPlacedEvent
                    |-- OrderDiscountSubscriber.onOrderPlaced()
                            --> summiert special_discount-LineItems
                            --> reduziert alengoCustomerDiscount_amount via customer.repository
```

## Klassen und Verantwortlichkeiten

| Klasse | Namespace | Verantwortung |
|---|---|---|
| `AlengoCustomerDiscount` | `AlengoCustomerDiscount` | Plugin-Bootstrap; Lifecycle install/uninstall/activate; definiert `LINE_ITEM_TYPE`-Konstante |
| `CustomerDiscountProcessor` | `AlengoCustomerDiscount\Core\Checkout` | Cart-Processor; liest Kundenguthaben; erstellt Rabatt-LineItem |
| `CustomFieldsInstaller` | `AlengoCustomerDiscount\Service` | Idempotente Verwaltung des Custom Field Sets; install, addRelations, uninstall |
| `OrderDiscountSubscriber` | `AlengoCustomerDiscount\Subscriber` | Lauscht auf `CheckoutOrderPlacedEvent`; bucht verbrauchtes Guthaben ab |
| `CartDiscountSubscriber` | `AlengoCustomerDiscount\Subscriber` | **Inaktiv (auskommentiert)** — früherer Ansatz via Shopware-Promotions; nicht in Betrieb |

## Cart-Processor-Flow

`CustomerDiscountProcessor` wird von der Cart-Pipeline bei jedem Warenkorb-Neuberechnung aufgerufen.

```
process()
  |
  +-- Warenkorb enthält Produkte?         Nein --> return (kein Rabatt ohne Produkte)
  |
  +-- Kunde eingeloggt?                   Nein --> return
  |
  +-- Custom Fields vorhanden             Nein --> return
  |   (name + amount)?
  |
  +-- Ablaufdatum überschritten?          Ja  --> return
  |   (null = morgen 00:00, also heute gültig)
  |
  +-- Rabatt-LineItem (gleicher Name)     Ja  --> return (kein Duplikat)
  |   bereits im Warenkorb?
  |
  +-- adjustedAmount = min(customerAmount, cartTotal)
  |
  +-- AbsolutePriceDefinition(-adjustedAmount)
  |
  +-- AbsolutePriceCalculator.calculate()
  |
  +-- LineItem Typ 'special_discount' erstellen
      Label = alengoCustomerDiscount_name
      setGood(false), setStackable(false), setRemovable(false)
      --> toCalculate.add(lineItem)
```

### Versandkosten-Behandlung

`collect()` ruft `DeliveryProcessor::collect()` auf und schreibt die berechneten Lieferoptionen in die `CartDataCollection`. In `process()` wird `$cartTotal` direkt aus `$toCalculate->getPrice()->getTotalPrice()` gelesen. Versandkosten werden bewusst nicht separat addiert: Deliveries werden erst nach dem Durchlauf aller Cart-Processors berechnet und sind zum Ausführungszeitpunkt von `process()` noch nicht im Cart-Preis enthalten — eine explizite Addition wäre konzeptionell falsch.

## Datenbankstruktur

Das Plugin legt keine eigenen Tabellen an. Alle Daten werden über Custom Fields auf der `customer`-Entity gespeichert.

### Custom Field Set: `alengoCustomerDiscount`

Angezeigt in der Administration unter: Kunden → [Kundendatensatz] → Tab "Einstellungen für Kundenrabatte"

| Field Name | Typ (CustomFieldTypes) | Bedeutung |
|---|---|---|
| `alengoCustomerDiscount_name` | `TEXT` | Anzeigename des Rabatts; dient gleichzeitig als Duplikatschutz-Key im Warenkorb |
| `alengoCustomerDiscount_amount` | `FLOAT` | Verfügbares Guthaben in der Shopwährung; wird nach Bestellung reduziert |
| `alengoCustomerDiscount_expirationDate` | `DATE` | Ablaufdatum (inklusiv, bis 23:59:59); `null` = kein Ablaufdatum |

### Lifecycle der Custom Fields

```
install()
  --> CustomFieldsInstaller.install()
      --> Existiert Custom Field Set 'alengoCustomerDiscount'?
          Nein: upsert (anlegen)
          Ja: fehlende Custom Fields nachtragen (Sync)

activate()
  --> CustomFieldsInstaller.addRelations()
      --> Relation 'alengoCustomerDiscount' <-> 'customer' noch nicht vorhanden?
          Ja: upsert in custom_field_set_relation

uninstall() [keepUserData = false]
  --> CustomFieldsInstaller.uninstall()
      --> Relationen löschen
      --> Custom Field Set löschen
```

**Hinweis:** `update()` im Plugin-Bootstrap ist leer. Neue Custom Fields bei Plugin-Updates werden nicht automatisch deployed. Workaround: `bin/console plugin:reinstall AlengoCustomerDiscount`.

## Erweiterungspunkte

Das Plugin bietet keine eigenen Events oder Decorator-Interfaces. Erweiterungen sind über Standard-Shopware-Mechanismen möglich:

| Erweiterungspunkt | Verwendung |
|---|---|
| Decorator für `CustomerDiscountProcessor` | Eigene Rabatt-Logik (z. B. Sales-Channel-Filter, prozentuale Rabatte) |
| Twig-Block `page_account_overview_payment_content` | Anpassung der Anzeige im Kundenkonto |
| `CheckoutOrderPlacedEvent` (eigener Subscriber) | Zusätzliche Aktionen nach Guthaben-Verbrauch (z. B. E-Mail-Benachrichtigung) |

## Architekturentscheidungen

### Warum Custom Fields statt eigener Tabelle?

Custom Fields auf der Kunden-Entity ermöglichen die direkte Bearbeitung über die Shopware-Administration ohne eigenes UI. Für einen einfachen, vom Shop-Admin manuell gepflegten Guthabenstand ist der Overhead einer eigenen Tabelle nicht gerechtfertigt.

**Nachteil:** Kein Audit-Log, kein Transaktions-History. Bei Bedarf wäre eine eigene Tabelle (`alengo_customer_discount_transaction`) der saubere Weg.

### Warum kein Shopware-Promotion-System?

`CartDiscountSubscriber` (inaktiv) war ein erster Prototyp, der das native Shopware-Promotion-System genutzt hat. Dieser Ansatz wurde verworfen, da er serverseitig Promotionen on-the-fly erzeugt und damit Nebeneffekte im Promotion-Management erzeugt. Der direkte Cart-Processor-Ansatz ist isolierter und besser kontrollierbar.

### Warum Priority 100 beim Cart-Processor?

Priority 100 stellt sicher, dass der Rabatt nach Standard-Produktpreisen und Steuern, aber vor abschliessenden Rundungsschritten angewendet wird. Niedrigere Prioritätswerte laufen später in der Pipeline.

## Bekannte Bugs und technische Schulden

| # | Beschreibung | Schwere |
|---|---|---|
| 1 | Race Condition bei parallelen Bestellungen (kein DB-Locking) | Mittel |
| 2 | `update()` ist leer — Custom-Field-Migration bei Plugin-Updates manuell | Niedrig |
| 3 | Beschreibungstext im LineItem hartcodiert auf Deutsch | Niedrig |
| 4 | Kein Sales-Channel-Filter — Guthaben gilt global | Niedrig |
| 5 | Keine Unit- oder Integrationstests trotz vorhandener `phpunit.xml` | Niedrig |
