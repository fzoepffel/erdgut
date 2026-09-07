# Katalog-Korrekturen — Werte zum Übernehmen

Zum Audit vom 28.07.2026. Alle Werte sind so formatiert, wie sie im Katalog-Blatt stehen müssen.
Abschnitte mit **[Entscheidung]** enthalten Vorschläge, keine eindeutigen Korrekturen: da geht es um Preispolitik, nicht um Fehler.

---

## 1. Namen

| ID | Feld | Alt | Neu |
|---|---|---|---|
| 92 | Name (DE) | `Weißen Johannisbeeren` | `Weiße Johannisbeeren` |
| 82 | Name (DE) | `Grün Zucchini` | `Grüne Zucchini` |
| 104 | Name (DE) | `Grün Runde Zucchini` | `Grüne runde Zucchini` |
| 56 | Name (DE) | `Bio-Spargel Lila` | `Bio-Spargel lila` |
| 65 | Name (DE) | `Soraya Kartoffel` | `Kartoffel Soraya` |
| 138 | Name (DE) | `Rosetten-Pak-Choi/ Tatsoi` | `Rosetten-Pak-Choi / Tatsoi` |
| 131 | Name (DE) | `Roggen, Alte Sorten` | `Roggen (alte Sorten)` — siehe Dubletten-Abschnitt |
| 93 | Name (FR) | `Groseille Noire / Cassis` | `Groseille Noire` |
| 88 | Name (FR) | `Fraise Verte de Plein Champ` | `Fraise Verte` |
| 107 | Name (DE) | `Junge Bunte Bete, 1kg` | `Junge Bunte Bete (1-kg-Bund)` |
| 94 | Name (DE) | `Junge Bunte Bete, 500g` | `Junge Bunte Bete (500-g-Bund)` |
| 113 | Name (DE) | `Hofeier x10` | `Hofeier (10 Stück)` |

Passend dazu die Übersetzungen:

| ID | Name (EN) | Name (FR) |
|---|---|---|
| 107 | `Young Rainbow Beetroot (1 kg bunch)` | `Betterave Multicolore (botte 1 kg)` |
| 94 | `Young Rainbow Beetroot (500 g bunch)` | `Betterave Multicolore (botte 500 g)` |
| 113 | `Farm Eggs (10 pieces)` | `Œufs Fermiers (10 pièces)` |

**79 „Erster Fenchel"** ist ein Saisonname neben dem dauerhaften „Fenchel" (63). Entweder umbenennen in `Frühfenchel` / `Early Fennel` / `Fenouil Précoce`, oder in 63 einpflegen und 79 stilllegen. Das ist eine Sortimentsfrage, keine reine Textkorrektur.

---

## 2. Allergene und Ernährungs-Tags

| ID | Produkt | Feld | Neu |
|---|---|---|---|
| 130 | Dinkel | Allergens | `gluten` |
| 131 | Roggen, Alte Sorten | Allergens | `gluten` |
| 132 | Haferflocken | Allergens | `gluten` |
| 105 | Stangenbohnen | Dietary Tags | `vegan; vegetarian` |
| 88 | Grüne Erdbeeren | Dietary Tags | `vegan; vegetarian` |
| 117 | Salatblätter | Dietary Tags | `vegan; vegetarian` |
| 120 | Sommerportulak | Dietary Tags | `vegan; vegetarian` |
| 87 | Ringelblumenblüten | Dietary Tags | `vegan; vegetarian` |
| 131 | Roggen, Alte Sorten | Dietary Tags | `vegan; vegetarian` |
| 56 | Bio-Spargel lila | Dietary Tags | `vegan; vegetarian` |

Fehlende Geschmacks-Tags:

| ID | Produkt | Taste Tags |
|---|---|---|
| 130 | Dinkel | `nutty; earthy` |
| 131 | Roggen, Alte Sorten | `nutty; earthy` |
| 132 | Haferflocken | `nutty; creamy` |

---

## 3. Herkunft und Sorte

| ID | Feld | Alt | Neu |
|---|---|---|---|
| 96 | Origin (DE) und (EN) | `Barnim, Brandenbourg` | `Barnim, Brandenburg` |
| 98 | Origin (DE) und (EN) | `Barnim, Brandenbourg` | `Barnim, Brandenburg` |

Die elf Melchhof-Produkte mit nur `Brandenburg` auf `Barnim, Brandenburg` angleichen (alle drei Sprachen):
**102, 103, 104, 105, 106, 107, 108, 109, 110, 111, 112**

Englisch im deutschen Sortenfeld:

| ID | Produkt | Variety (DE) alt | Variety (DE) neu |
|---|---|---|---|
| 73 | Schnittlauch | `Regular` | `Standard` |
| 63 | Fenchel | `Regular` | `Standard` |
| 59 | Rhabarber | `Regular` | `Standard` |

---

## 4. Einheiten und Mengen

| ID | Produkt | Feld | Alt | Neu |
|---|---|---|---|---|
| 111 | Salbei | Quantity per Unit | `1` | `50` |
| 107 | Junge Bunte Bete (1-kg-Bund) | Quantity per Unit | leer | `1000` |
| 189 | Pflaumen | Unit | `Bd` | `Schale` |
| 189 | Pflaumen | Quantity per Unit | `250` | `250` (bleibt) |
| 128 | Basilikum Topf | Unit | `Bd` | `Topf` |
| 129 | Petersilie Topf | Unit | `Bd` | `Topf` |
| 181 | Champagnerroggen-Vollkornmehl | Unit | `Beutel` | `Schale` oder `St` |

**Bd bleibt, aber Menge nachtragen** (Bund ist als Verkaufseinheit in Ordnung, die Grammatur fehlt nur):

| ID | Produkt | Quantity per Unit |
|---|---|---|
| 77 | Zuckerschoten | `250` (bereits gesetzt) |
| 58 | Rhabarber Goliath | `500` (bereits gesetzt) |
| 94 | Junge Bunte Bete (500-g-Bund) | `500` (bereits gesetzt) |
| 106 | Neue Möhren mit Grün | `750` (bereits gesetzt) |

Bei 181 „Beutel" gibt es keine passende Einheit im System. Wenn der Beutel 1 kg wiegt, ist die saubere Lösung `kg` mit Preis pro Kilo statt einer eigenen Packungseinheit.

---

## 5. Konfiguration

| ID | Produkt | Feld | Alt | Neu |
|---|---|---|---|---|
| 163 | Ganze Korianderpflanze | Available | `1` | `0` |
| 113 | Hofeier | Season Start | `01-01` | leer |
| 113 | Hofeier | Season End | `12-31` | leer |
| 130 | Dinkel | Freshness (days) | `1` | `7` |
| 131 | Roggen, Alte Sorten | Freshness (days) | `1` | `7` |
| 132 | Haferflocken | Freshness (days) | `1` | `7` |

Bei 163: veröffentlicht = 0 und verfügbar = 1 widerspricht sich. Entweder ganz aus dem Verkehr (`Available` = 0, wie oben) oder wieder veröffentlichen (`Published` = 1). Solange kein Bild hinterlegt ist, würde ich es auf 0 lassen.

**Gruppenpositionen nachtragen.** In jeder Gruppe fehlt mindestens eine Position, dadurch ist die Reihenfolge undefiniert:

| Gruppe | ID | Group Position |
|---|---|---|
| Apfelsaft | 71 Hof-Apfel-Quitten-Saft | `3` |
| Basilikum | 134 Basilikum Genovese | `5` |
| Bete | 142 Rote Bete | `1` |
| Bete | 143 Geringelte Bete | `2` |
| Bete | 144 Gelbe Bete | `3` |
| Borretsch | 95 Borretsch | `2` |
| Borretsch | 191 Borretschblüten | `3` |
| Cocktailtomaten | 147 Cocktailtomaten (250-g-Schale) | `2` |
| Flocken & Getreide | 177 Dinkelflocken | `10` |
| Gurken | 83 Gurke | `3` |
| Gurken | 190 Mini Gurke | `4` |
| Kapuzinerkresse | 89 Kapuzinerkresse (Blätter & Blüten) | `3` |
| Kohlrabi | 122 Grüner Kohlrabi | `2` |
| Mangold | 62 Mangold | `4` |

Damit stehen bei „Bete" die Positionen 1–5 lückenlos (Rote, Geringelte, Gelbe, 500-g-Bund, 1-kg-Bund) und bei „Cocktailtomaten" 1–3.

---

## 6. Texte

### 88 Grüne Erdbeeren — ersetzt die `XX`-Platzhalter

Die Geschmacks-Tags stehen auf `sour; herbal; fresh`, der Kilopreis liegt über dem der reifen Erdbeeren. Der Text ist entsprechend als Küchenprodukt geschrieben. Bitte gegenlesen, ob das die Ware trifft.

**Short Description (DE)**
```
Unreif geerntete Erdbeeren, fest und säuerlich. Ein Küchenprodukt zum Einlegen, Hobeln und Würzen.
```
**Short Description (EN)**
```
Strawberries picked unripe, firm and tart. A kitchen ingredient for pickling, shaving and seasoning.
```
**Short Description (FR)**
```
Des fraises cueillies vertes, fermes et acidulées. Un produit de cuisine à mariner, râper et assaisonner.
```

**Long Description (DE)**
```
Diese Erdbeeren werden bewusst vor der Reife geerntet. Dadurch bleiben sie fest, hell und deutlich säuerlich, mit einer krautigen Note, die reifen Erdbeeren völlig fehlt. In der Küche verhalten sie sich eher wie ein Gemüse als wie Obst. Eingelegt in Essig oder Salzlake werden sie zu einem Würzelement für Fisch, Ziegenkäse oder Salate. Dünn gehobelt geben sie Rohkost und Vorspeisen eine frische Säure. Auch geschmort oder kurz angebraten behalten sie ihren Biss.
```
**Long Description (EN)**
```
These strawberries are picked deliberately before they ripen. That keeps them firm, pale and distinctly tart, with a green, herbaceous note that ripe fruit does not have. In the kitchen they behave more like a vegetable than a fruit. Pickled in vinegar or brine they become a seasoning for fish, goat's cheese or salads. Shaved thin they bring a fresh acidity to raw dishes and starters. They also hold their bite when braised or briefly seared.
```
**Long Description (FR)**
```
Ces fraises sont cueillies volontairement avant maturité. Elles restent ainsi fermes, pâles et franchement acidulées, avec une note herbacée que le fruit mûr n'a pas. En cuisine, elles se comportent davantage comme un légume que comme un fruit. Marinées au vinaigre ou en saumure, elles deviennent un condiment pour le poisson, le fromage de chèvre ou les salades. Taillées finement, elles apportent une acidité vive aux crudités et aux entrées. Elles gardent aussi leur tenue braisées ou juste saisies.
```

### 91 Rote Johannisbeeren — Grammatikfehler

**Short Description (DE)**
```
Alte biodynamische Sträucher für lebhafte, säuerliche Beeren, die frisch genauso gut schmecken wie als Marmelade oder als Begleiter.
```

### 186, 187, 188, 192 — Anrede auf Sie umstellen und Gedankenstriche auflösen

**186 Petersilie Gigante — Short Description (DE)**
```
Große, tiefgrüne Blätter mit einem kräftigen, frischen Petersilien-Geschmack. Perfekt zum Garnieren und für eine lebendige Kräuternote in Ihren Gerichten.
```
**186 Petersilie Gigante — Long Description (DE)**
```
Die Petersilie Gigante besticht durch ihre großen, tiefgrünen Blätter und ihren kräftigen, frischen Geschmack, der Ihren Gerichten eine lebendige Kräuternote verleiht. Großzügig in der Größe und intensiv im Geschmack eignet sie sich perfekt zum Garnieren von Salaten, Suppen oder Saucen sowie zum Verfeinern von Gerichten mit einer frischen, leicht würzigen Note. Ihr auffälliges Erscheinungsbild und ihr kräftiger Geschmack machen sie zu einer vielseitigen und aromatischen Zutat, ideal für eine kreative und naturnahe Küche.
```

**187 Zwiebel Gelb — Short Description (DE)**
```
Feste Zwiebeln mit goldener Schale und einem herzhaften, ausgewogenen Geschmack. Perfekt als Basis zum Kochen und zum Verfeinern Ihrer Gerichte.
```
**187 Zwiebel Gelb — Long Description (DE)**
```
Die gelbe Zwiebel überzeugt durch ihre feste Struktur und goldene, papierartige Schale sowie ihren herzhaften, ausgewogenen Geschmack, der beim Kochen mild und süßlich wird. Vielseitig und geschmacklich kräftig eignet sie sich perfekt als Basis für Suppen, Eintöpfe oder Saucen sowie zum Rösten, Karamellisieren oder für einen schärferen Biss im rohen Zustand. Ihre verlässliche Textur und ihr reichhaltiger Geschmack machen sie zu einer unverzichtbaren und vielseitigen Zutat, ideal für die alltägliche wie auch kreative Küche.
```

**188 Zwiebel Rot — Short Description (DE)**
```
Knackige Zwiebeln mit leuchtend violett-roter Schale und einem milden, leicht süßlichen Geschmack. Perfekt für Salate und als farbliches Highlight in Ihren Gerichten.
```
**188 Zwiebel Rot — Long Description (DE)**
```
Die rote Zwiebel besticht durch ihre knackige Textur und leuchtend violett-rote Schale sowie ihren milden, leicht süßlichen Geschmack mit einer feinen Schärfe. Farbenfroh und erfrischend im Geschmack eignet sie sich perfekt zum Schneiden für Salate, Sandwiches oder Salsas sowie zum Einlegen oder Grillen für eine mildere, karamellisierte Note. Ihre auffällige Farbe und ihr feiner Geschmack machen sie zu einer ebenso blickfangenden wie vielseitigen Zutat, ideal für eine frische und kreative Küche.
```

**192 Porree — Short Description (DE)**
```
Lange, zarte Stangen mit einem milden, süßlichen Zwiebelgeschmack. Perfekt für Suppen und für eine sanfte Geschmackstiefe in Ihren Gerichten.
```
**192 Porree — Long Description (DE)**
```
Porree besticht durch seine langen, zarten weiß-grünen Stangen und seinen milden, süßlichen Geschmack, der an Zwiebeln erinnert, jedoch sanfter und feiner ist. Subtil und aromatisch im Geschmack eignet er sich perfekt als Basis für Suppen, Eintöpfe oder Quiches sowie zum Schmoren, Anbraten oder Rösten, um seine natürliche Süße hervorzuheben. Seine elegante Form und sein milder Geschmack machen ihn zu einer ebenso wohltuenden wie vielseitigen Zutat, ideal für die alltägliche wie auch kreative Küche.
```

### Englische Rechtschreibung vereinheitlichen (britisch, wie bei 72 der 91 Produkte)

Reines Suchen-und-Ersetzen in den englischen Feldern dieser 19 Produkte:
`flavor` → `flavour`, `flavorful` → `flavourful`, `color` → `colour`, `colorful` → `colourful`, `Colorful` → `Colourful`, `savory` → `savoury`, `caramelizing` → `caramelising`, `caramelized` → `caramelised`

Betroffen: **53, 58, 60, 61, 68, 72, 74, 75, 78, 81, 82, 87, 100, 165, 167, 186, 187, 188, 192**

Achtung bei **165 „Summer Savory"** und **167 „Summer Savory Pot"**: das ist der Pflanzenname (Bohnenkraut), nicht das Adjektiv. Diese beiden Namen bleiben unverändert, nur der Fließtext wird angepasst.

### Französische Apostrophe vereinheitlichen (gerade `'`, wie bei 85 der 102 Produkte)

In den französischen Feldern dieser 17 Produkte `’` durch `'` ersetzen:
**53, 54, 56, 57, 58, 59, 61, 62, 64, 72, 74, 75, 78, 82, 130, 131, 132**

---

## 7. Preise [Entscheidung]

Als Referenz aus dem eigenen Katalog: Median-Marge B2B **28 %**, B2C **31 %**, B2B-Netto liegt typischerweise bei **95 %** des B2C-Netto.

### Fehlender B2B-Preis — hier zahlen Geschäftskunden aktuell den Brutto-B2C-Preis

| ID | Produkt | EK | B2C brutto | B2C netto | **Business Net neu** | Marge |
|---|---|---|---|---|---|---|
| 84 | Mini Gurke | 8,00 | 11,90 | 11,12 | `10,50` | 24 % |
| 77 | Zuckerschoten | 3,75 | 5,50 | 5,14 | `4,90` | 23 % |
| 58 | Rhabarber Goliath | 2,25 | 3,75 | 3,50 | `3,30` | 32 % |

### Fehlender Einkaufspreis

| ID | Produkt | **Cost neu** | Herleitung |
|---|---|---|---|
| 117 | Salatblätter | `2,50` | 28 % Marge auf B2B 3,50; passt zu 155 und 120 |
| 132 | Haferflocken | `6,40` | 28 % Marge auf B2B 8,90; passt zu 177 und 178 |
| 131 | Roggen, Alte Sorten | `3,50` | identisch mit 171, gleiches Produkt |

### B2B teurer als B2C — B2B-Netto muss unter B2C-Netto liegen

| ID | Produkt | B2C netto | B2B alt | **B2B neu** |
|---|---|---|---|---|
| 155 | Salatmix | 2,80 | 2,80 | `2,65` |
| 161 | Mexikanische Riesentagetes | 0,07 | 0,07 | `0,06` |
| 136 | Kleine Zucchini | 6,45 | 6,50 | `6,10` |
| 179 | Buchweizen, geschält | 9,25 | 9,50 | `8,90` |
| 71 | Hof-Apfel-Quitten-Saft | 3,28 | 3,50 | `3,10` |
| 72 | Hof-Rotebete-Möhren-Apfelsaft | 3,28 | 3,50 | `3,10` |

Damit sinkt die Marge bei den beiden Säften auf 3 %. Die eigentliche Ursache ist der zu niedrige Endkundenpreis: 3,90 € brutto bei 19 % Umsatzsteuer und 3,00 € Einkauf ergibt nur 9 % Marge. Für 31 % müsste der Bruttopreis auf **5,20 €** und B2B auf **4,20 €**. Gleiches Bild bei 70 Hof-Apfelsaft (15 % Marge). Das ist eine Preisentscheidung, kein Datenfehler.

Bei 179 Buchweizen gilt dasselbe in kleiner: für 31 % Marge müsste der Bruttopreis auf **10,90 €** und B2B auf **9,70 €**.

### Verkauf unter Einkaufspreis beim Zweithof

| ID | Produkt | Netto-Verkauf | EK Hof 1 | EK Hof 2 |
|---|---|---|---|---|
| 136 | Kleine Zucchini | 6,45 | 3,00 Jens Looke | 6,50 Melchhof |
| 143 | Geringelte Bete | 4,58 | 3,00 Melchhof | 5,00 Jens Looke |
| 142 | Rote Bete | 4,58 | 3,00 Melchhof | 5,00 Jens Looke |

Hier hilft kein Feldwert. Entweder den teuren Hof als Bezugsquelle entfernen, den EK neu verhandeln, oder den Verkaufspreis anheben. Bei 142 und 143 wäre `6,90 €` brutto (netto 6,45, 23 % Marge auf den teuren EK) die Untergrenze, bei der beide Quellen tragen.

### Fehlender EK beim Zweithof

| ID | Produkt | Hof | Cost |
|---|---|---|---|
| 122 | Grüner Kohlrabi | Biohof Hübner | nachtragen |
| 121 | Violetter Kohlrabi | Biohof Hübner | nachtragen |
| 144 | Gelbe Bete | Jens Looke | nachtragen |

### Margen-Ausreißer

| ID | Produkt | EK | B2C brutto | Marge | Angleichung auf 31 % |
|---|---|---|---|---|---|
| 100 | Salat | 1,80 | 2,00 | 4 % | `2,80` brutto, B2B `2,50` |
| 66 | Kartoffel Laura | 1,00 | 2,20 | 51 % | im Sortiment stimmig machen: Solist 34 %, Annabelle 24 % |
| 65 | Kartoffel Soraya | 1,00 | 2,20 | 51 % | dito |
| 170 | Mirabellen | 1,25 | 3,50 | 62 % | dito |

Bei 100 Salat verkaufen Sie aktuell zum Einkaufspreis an Geschäftskunden (0 % Marge). Das ist der dringendste Fall in dieser Tabelle.

---

## 8. Dubletten und Taxonomie [Entscheidung]

Diese lassen sich nicht über Feldwerte lösen, sie brauchen einen Datenbank-Eingriff.

**Doppelte Unterkategorien.** Es gibt je zwei Datensätze mit identischem Namen. Zusammenführen und die Produkte umhängen:
- „Kohlgewächse": 122, 138, 121 auf die Kategorie von 80 und 108 umhängen
- „Zwiebelgewächse": 192 Porree auf die Kategorie von 145, 140, 123, 187, 188 umhängen

**Dublette 131 / 171 Roggen.** Beide sind „Roggen, alte Sorten" vom selben Hof. Vorschlag: 171 behalten (hat Gluten-Tag, 7 Tage Haltbarkeit, korrekte Schreibweise), 131 stilllegen. Falls 131 doch etwas anderes ist, braucht es einen unterscheidenden Namen.

**Gleicher Anzeigename, zwei Produkte.** Diese vier Paare sind bewusst als Gebindevarianten angelegt, tragen aber identische Namen. Im Shop stehen zwei gleich benannte Einträge nebeneinander:

| ID | Alt | Neu |
|---|---|---|
| 61 | Mangold Rainbow | `Mangold Rainbow (Bund)` — Preis/EK prüfen, 61 kostet 8,50, 78 kostet 5,90 |
| 78 | Mangold Rainbow | `Mangold Rainbow (klein)` |
| 90 | Kapuzinerkresseblüten | `Kapuzinerkresseblüten (Bund)` |
| 183 | Kapuzinerkresseblüten | `Kapuzinerkresseblüten (einzeln)` |
| 84 | Mini Gurke | `Mini Gurke (lose, kg)` |
| 190 | Mini Gurke | `Mini Gurke (Stück)` |
| 54 | Bio-Spargel grün | `Bio-Spargel grün (Kalkwitz)` — EK 14,00 |
| 57 | Bio-Spargel grün | `Bio-Spargel grün` — EK 9,00 |

Bei 54 und 57 ist der Verkaufspreis identisch (19,50), der Einkauf aber 14,00 gegen 9,00. Wenn das zwei Höfe für dieselbe Ware sind, gehört das in ein Produkt mit zwei Bezugsquellen, nicht in zwei Produkte.

**Weitere Verdachtsfälle**: 160 „Borretsch-Blüten (einzeln)" gegen 191 „Borretschblüten", und 168 „Griechischer Oregano im Topf" gegen 169 „Oregano im Topf" (identischer Preis, identische Einheit).

**Überlappende Kategorien.** „Blattgemüse", „Blattgemüse & Salate" und „Salate" gehören zusammengelegt. Getreide liegt in drei Töpfen: „Getreideprodukte" (nur 172), „Samen & Körner" (131, 132) und ohne Unterkategorie (9 Produkte). Vorschlag: eine Unterkategorie `Getreide & Mehl` für 130, 131, 132, 171, 172, 173, 174, 177, 178, 179, 180, 181.

**„Säfte"** liegt unter der Kategorie „Obst & Gemüse". Sachlich gehört das eher zu „Brot & Trockenware" oder eine eigene Kategorie, je nachdem wie das Menü aufgebaut sein soll.

---

## 9. Bestand [Entscheidung]

Der gesamte Bestand liegt auf dem 29.07. Für heute und für den 30.07. bis 24.08. ist nichts erfasst. 49 veröffentlichte Produkte haben null Bestand über die ganzen 28 Tage. Solange das so bleibt, zeigt der Shop diese Produkte als ausverkauft.

Die Haltbarkeit von 1 Tag bei 113 von 125 Produkten verstärkt das: eine Lieferung ist genau einen Tag lang verkäuflich. Falls das nicht der Realität entspricht, ist das der wirkungsvollste einzelne Hebel im ganzen Katalog. Frischgemüse liegt sonst eher bei 2 bis 4 Tagen.
