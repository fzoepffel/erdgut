# Abgleich Sourcing-Tabelle gegen Erdgut-Katalog

Quelle: Google-Tabelle, Blatt `gid=586605980`, 168 Zeilen, Kopfzeile in Zeile 4.
Gegenstück: Katalog-Export vom 28.07.2026, 125 Produkte.

Die beiden Tabellen benennen dieselbe Ware unterschiedlich. Die Sourcing-Tabelle führt
Erzeugernamen und eine Zeile pro Hof, der Katalog führt Kundennamen und eine Zeile pro Produkt.
Verglichen wurde deshalb über den Kundennamen (`Erdgut Name`), mit Umrechnung der Einheiten.

## Gesamtbild

**Die Preise stimmen weitgehend überein.** Ganze Warengruppen sind exakt deckungsgleich,
inklusive der Umrechnung von Kilopreis auf Gebindepreis:

- Erdbeeren, Johannisbeeren (kg), sämtliches Getreide und alle Flocken
- Cocktailtomaten in allen drei Gebinden, Salattomaten
- Gurken, Landgurken, alle Zucchini-Sorten, Rondini
- Mangold in allen Varianten, Schnittmangold
- Bete in allen Varianten, Kohlrabi, Spargel
- Basilikum-Familie, die meisten Kräuter, die meisten Salate

Stichprobe der Umrechnungen, alle korrekt:

| ID | Produkt | Sourcing | Katalog | Prüfung |
|---|---|---|---|---|
| 64 | Sauerampfer | 13,00 €/kg | 1,30 € pro 100-g-Bund | 13,00 × 0,1 = 1,30 ✓ |
| 76 | Salbeiblüten | 18,00 €/kg | 1,80 € pro 100 g | ✓ |
| 75 | Asiasalat Mizuna | 15,00 €/kg | 1,50 € pro 100 g | ✓ |
| 120 | Sommerportulak | 20,00 €/kg | 2,00 € pro 100 g | ✓ |
| 155 | Salatmix | 19,50 €/kg | 1,95 € pro 100 g | ✓ |
| 189 | Pflaumen | 4,90 €/kg | 1,23 € pro 250 g | 4,90 × 0,25 = 1,225 ✓ |
| 58 | Rhabarber Goliath | 4,50 €/kg | 2,25 € pro 500 g | ✓ |
| 161 | Riesentagetes | 5,00 €/100 g | 0,05 €/g | ✓ |

Der Katalog ist also sauber aus dieser Tabelle befüllt worden. Die Abweichungen unten sind
Einzelfälle, keine systematische Drift.

---

## 1. Echte Preisfehler — die Sourcing-Tabelle liefert den richtigen Wert

### 136 Kleine Zucchini — Einkaufspreis steht im Verkaufspreisfeld

Sourcing-Tabelle:

| Hof | EK | B2B netto | B2C brutto |
|---|---|---|---|
| Jens Looke | 3,00 € | 7,90 € | 7,50 € |
| Melchhof Gärtnerei | 6,50 € | 7,90 € | 8,50 € |
| Biohof Hübner | 1,70 € | 7,90 € | — |

Katalog: EK 3,00 €, **B2B 6,50 €**, B2C 6,90 €.

Die 6,50 € im B2B-Feld sind der **Einkaufspreis von Melchhof**, nicht ein Verkaufspreis.
Das erklärt sowohl die 54-%-Marge als auch den Verkauf unter Einkauf, den ich im Audit gemeldet hatte.

- `Business Net` → `7,90`
- `Private Price` → `7,50` (Jens Looke) oder `8,50` (Melchhof)
- Biohof Hübner mit EK `1,70` als dritte Bezugsquelle ergänzen

Anmerkung: In der Sourcing-Tabelle selbst steht bei Jens Looke B2B 7,90 € über B2C 7,50 €. Diese Umkehrung stammt aus der Quelle.

### 100 Salat — Hof und Einkaufspreis passen zu keiner Zeile

Sourcing-Tabelle kennt keinen Melchhof-Kopfsalat:

| Hof | EK | B2B | B2C |
|---|---|---|---|
| Biohof Hübner | 1,00 € | 1,80 € | 2,00 € |
| Jens Looke | 2,00 € | 2,50 € | 2,80 € |

Katalog: Melchhof Gärtnerei, EK 1,80 €, B2B 1,80 €, B2C 2,00 €.

Die Verkaufspreise stammen von Biohof Hübner, der Einkaufspreis kommt aus keiner Zeile,
und der Hof ist ein dritter. Daraus entsteht die 0-%-B2B-Marge.

- `Producer` → `Biohof Hübner`
- `Cost` → `1,00`

Damit liegt die Marge bei 44 % B2B und 47 % B2C, statt bei 0 % und 4 %.

### 170 Mirabellen — Verkaufspreis zu hoch

Sourcing: 5,00 € / 7,90 € / **11,90 €** pro kg. Bei 250-g-Schalen sind das 1,25 / 1,98 / **2,98 €**.
Katalog: 1,25 / 1,95 / **3,50 €**.

- `Private Price` → `2,98`

Das war der 62-%-Margen-Ausreißer aus dem Audit.

### 77 Zuckerschoten — drei Felder daneben

Sourcing führt zwei Zeilen: `Zuckerschoten` 13,00 / 19,90 / 21,50 €/kg und `Zuckerschoten Spitz` 25,00 / 35,00 / 39,00 €/kg.
Der Katalog nutzt einen 250-g-Bund. Auf Basis der ersten Zeile:

| Feld | Katalog | Soll |
|---|---|---|
| Cost | 3,75 | `3,25` |
| Business Net | leer | `4,98` |
| Private Price | 5,50 | `5,38` |

Falls stattdessen die Spitz-Variante gemeint ist, lauten die Werte 6,25 / 8,75 / 9,75.

### 58 Rhabarber Goliath — fehlender B2B-Preis

Sourcing 6,50 €/kg, Gebinde 500 g.

- `Business Net` → `3,25`

### 117 Salatblätter — fehlender Einkaufspreis

Sourcing `Pflücksalat`, Jens Looke, 25,00 €/kg. Verkaufspreise im Katalog stimmen bereits.

- `Cost` → `2,50`

### 132 Haferflocken — fehlender Einkaufspreis

Sourcing führt zwei Mahlgrade: `grob` 6,50 / 8,90 / 9,90 und `fein` 6,00 / 8,50 / 9,50.
Die Verkaufspreise im Katalog entsprechen **grob**.

- `Cost` → `6,50`

Wenn beide Mahlgrade verkauft werden sollen, fehlt `Haferflocken fein` als eigenes Produkt.

### 131 Roggen, Alte Sorten — vermischte Werte, bestätigt die Dublette

Sourcing kennt nur eine Zeile `Roggen (alte Sorten)`: 3,50 / 4,50 / **4,90**.
Katalog ID 171 hat genau diese Werte. Katalog ID 131 hat 4,50 B2B und **5,50** B2C.
5,50 ist der B2C-Preis von **Dinkel**.

ID 131 trägt also den Namen von Roggen mit dem Endpreis von Dinkel und ohne Einkaufspreis.
Das bestätigt die Empfehlung aus dem Audit: ID 131 stilllegen, ID 171 behalten.

---

## 2. Produkte im Katalog ohne Entsprechung in der Sourcing-Tabelle

Diese Einträge lassen sich in der Quelle nicht wiederfinden. Ihre Preise stehen nirgends sonst.

| ID | Produkt | Katalogpreise | Anmerkung |
|---|---|---|---|
| 90 | Kapuzinerkresseblüten | 2,50 / 3,25 / 3,50 (Bd) | Die Tabelle kennt nur `Kapuzinerblüten` St 0,25/0,35/0,40 (= ID 183) und `Kapuzinerkresse, Bund` 1,60/2,10/2,50 (= ID 89). ID 90 passt zu keiner. |
| 84 | Mini Gurke | 8,00 / — / 11,90 (kg) | Die Tabelle kennt nur `Gurken Mini` St 1,20/1,60/1,80 (= ID 190). Eine Kilo-Variante gibt es nicht. |

Beides sind zugleich die Namensdubletten aus dem Audit. Der Abgleich stützt die Vermutung,
dass ID 90 und ID 84 Altlasten sind.

---

## 3. Sortierung: die Sourcing-Tabelle widerspricht dem Katalog

| ID | Produkt | Sourcing | Katalog | Wer hat recht |
|---|---|---|---|---|
| 192 | Porree | Stängel & Sprossen | Zwiebelgewächse | Katalog. Diese Änderung hat aber die doppelte Unterkategorie erzeugt. |
| 170 | Mirabellen | Kernobst | Steinobst | **Katalog.** Mirabellen sind Steinobst. |
| 189 | Pflaumen | Kernobst | Steinobst | **Katalog.** |
| 191 | Borretschblüten | Essbare Blüten | Kräuter | **Sourcing.** |
| 185 | Drachenkopf | Essbare Blüten | Kräuter | **Sourcing.** |
| 133 | Zucchiniblüten | Fruchtgemüse | Essbare Blüten | Katalog. |
| 151 | Schnittmangold | Blattgemüse | Blattgemüse & Salate | offen, siehe Audit |
| 119, 118, 154 | Salate | Salate | Blattgemüse & Salate | offen, siehe Audit |

**Getreide.** Die Sourcing-Tabelle ordnet konsequent zu: `Samen & Körner` für ganze Körner,
`Flocken` für Flocken. Im Katalog haben neun dieser Produkte gar keine Unterkategorie und eines
liegt in `Getreideprodukte`. Die Quelle beantwortet damit die offene Frage aus dem Audit.

**Produktgruppen.** Die Sourcing-Tabelle pflegt eine Gruppe für nahezu jede Zeile
(Zucchini, Tomaten, Bohnen, Zwiebeln, Kartoffeln, Johannisbeeren, Salbei, Dill, Minze …).
Im Katalog sind nur 42 von 125 Produkten einer Gruppe zugeordnet. Diese Spalte ließe sich
weitgehend übernehmen.

---

## 4. Der Spargel-Fall erklärt die Namensdubletten

Sourcing-Tabelle:

| Zeile | Hof | EK |
|---|---|---|
| Spargel Grün | Biohof Hübner | 9,00 € |
| Spargel Grün | Spargelhof Kalkwitz | 14,00 € |
| Spargel Lila | Biohof Hübner | 9,00 € |
| Spargel Weiß | Spargelhof Kalkwitz | 14,00 € |

Katalog: ID 57 (Hübner, 9,00) und ID 54 (Kalkwitz, 14,00) als **zwei Produkte** mit identischem Namen.

Die Sourcing-Tabelle führt bewusst eine Zeile pro Hof. Beim Übertragen wurden daraus zwei
Produkte statt eines Produkts mit zwei Bezugsquellen. Verkaufspreis ist bei beiden 19,50 €,
also handelt es sich um dieselbe Ware.

Derselbe Mechanismus erklärt `Mangold Rainbow` (ID 61 Melchhof 5,90, ID 78 Hübner 3,50) —
in der Tabelle stehen dafür drei Zeilen, eine je Hof.

---

## 5. Bestand: Woche 31 gegen die App

Die Wochenspalten der Sourcing-Tabelle stehen in der Erzeugereinheit, der App-Bestand in der
Verkaufseinheit. Ein direkter Vergleich funktioniert nur dort, wo beide dieselbe Einheit nutzen.
Wo er funktioniert, passt er: Sauerampfer 3 kg gegen 30 Bund à 100 g, Mirabellen 23 kg gegen
100 Schalen à 250 g, Pflaumen 4 kg gegen 14 Bund à 250 g.

Auffällig:

- **Die Sourcing-Tabelle meldet für Woche 31 Ware, die in der App nicht eingebucht ist**:
  Buschbohnen (5), Gewürztagetes (5), Hirse (5), Petersilie Gigante (7), Salbei (5), Dinkelflocken.
  Diese Produkte stehen im Shop auf ausverkauft.
- **Hofeier**: Tabelle 100, App 60.
- **Riesentagetes**: Tabelle 0,3 kg (= 300 g), App 20 g.
- **Zelle `Hafer` Woche 31** enthält den Text `Lieferzeit anfragen` in einer Mengenspalte.
  Beim Import würde das als Fehler auflaufen oder stillschweigend ignoriert.

---

## 6. In der Sourcing-Tabelle gepflegt, im Katalog nicht angelegt

Diese Zeilen haben Einkaufs- und Verkaufspreise, existieren im Shop aber nicht. 38 Zeilen, gruppiert:

**Gebindevarianten bestehender Produkte**

| Zeile | Hof | EK | B2C |
|---|---|---|---|
| Johannisbeeren 250 g, rot / schwarz / weiß | Melchhof | 4,00 € | 6,50 € |
| Johannisbeeren 500 g, rot / schwarz / weiß | Melchhof | 7,00 € | 10,75 € |
| Hofeier x6 | Bauer Gürgen | 2,88 € | 4,20 € |
| Hofeier 1 kg | Bauer Gürgen | 8,00 € | 10,90 € |
| Champagnerroggen-Vollkornmehl 4 kg | Bauer Gürgen | 8,80 € | 11,90 € |
| Champagnerroggen-Vollkornmehl 10 kg | Bauer Gürgen | 17,60 € | 23,90 € |
| Champagnerroggen-Vollkornmehl 20 kg | Bauer Gürgen | 40,00 € | 54,90 € |
| Haferflocken fein | Jens Looke | 6,00 € | 9,50 € |
| Linsen, 400-g-Pack | Biohof Hübner | 3,50 € | 4,90 € |

**Eigenständige Produkte**

| Zeile | Hof | EK | B2C |
|---|---|---|---|
| Zuckermais | Melchhof | 4,50 € | 6,90 € |
| Linsen | Biohof Hübner | 7,50 € | 9,90 € |
| Leinsaat | Jens Looke | 5,00 € | (kein Preis) |
| Kornblume | Melchhof | 25,00 € | 39,00 € |
| Roter Amaranth | Melchhof | 25,00 € | 39,00 € |
| Winter Postelein Salat | Jens Looke | 20,00 € | 35,00 € |
| Kopfsalat | Biohof Hübner / Jens Looke | 1,00 / 2,00 € | 2,00 / 2,80 € |
| Zucchini mit Blüte | Jens Looke | 1,00 € | 1,80 € |
| Zucchini, groß ab 1,3 kg | Biohof Hübner | 1,30 € | 2,50 € |
| Zucchini, groß ab 1,6 kg | Biohof Hübner | 1,00 € | 1,90 € |
| Kohlrabi, eiförmig | Biohof Hübner | 0,95 € | 1,90 € |

**Zusätzliche Bezugsquellen für vorhandene Produkte**: Gelbe / Rote / Geringelte Bete von Biohof
Hübner als eigene Kilo-Zeilen (2,60 €), Landgurken von Melchhof (4,50 € neben Jens Looke 5,00 €),
Zucchini gelb und grün von Jens Looke (je 2,00 €), Kleine Zucchini von Biohof Hübner (1,70 €).

**`Hofeier Rabatt`** ist eine Zeile ohne jeden Preis. Vermutlich ein Platzhalter, kein Produkt.

---

## 7. Auffälligkeiten in der Sourcing-Tabelle selbst

- Kategorie mal `Bakery & Dry goods`, mal `Bakery & Dry Goods` (Zeile Dinkel).
- Einheit mal `kg`, mal `Kg` (Rote Bete Jens Looke, Schalotten).
- Produzentennamen mit Leerzeichen am Ende: `Basilikum Zimt `, `Basilikum Zitrone `, `Basilikum Genovese `.
- `Kleine Zucchini` Jens Looke: B2B 7,90 € über B2C 7,50 €. Umkehrung an der Quelle.
- `Rote Bete` und `Geringelte Bete` von Jens Looke: EK 5,00 € eingetragen, B2B und B2C bewusst leer
  gelassen. Der Katalog hat den EK übernommen und verkauft die Ware zu 4,90 €, also unter Einkauf.
  Die leeren Preisfelder in der Quelle waren offenbar genau die Warnung.
- `Kornblume/ cornflower`, `Roter Amaranth/ red amaranth`, `Spitzwegerich/ Ribwort plantain`:
  deutscher und englischer Name in einer Zelle.
