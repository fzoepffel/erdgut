# Katalog-Audit — Export vom 28.07.2026

125 Produkte, 23 Zweit-Erzeuger-Zeilen, 6 Höfe. Geprüft: Katalog, Preise, Bestand.
Preise- und Bestand-Blatt stimmen mit dem Katalog überein, keine Abweichung gefunden.

Sortiert nach Dringlichkeit. IDs sind Erdgut-IDs.

---

## 1. Kundenseitig sichtbare Fehler

| ID | Produkt | Problem |
|---|---|---|
| 88 | Grüne Erdbeeren | Kurz- und Langbeschreibung ist in allen drei Sprachen `XX`. Produkt ist veröffentlicht. |
| 91 | Rote Johannisbeeren | DE-Kurztext endet auf „…wie als Marmelade oder zu Begleiter." Grammatikfehler. |
| 88 | Grüne Erdbeeren | FR-Name „Fraise Verte de Plein Champ". „de Plein Champ" gehört zu ID 74 (Freiland-Erdbeeren). Vermutlich kopiert. |
| 96, 98 | Zimtbasilikum, Thai-Basilikum | Herkunft steht als „Barnim, Brandenbourg" (französische Schreibweise) auch im deutschen und englischen Feld. |

## 2. Allergene und Kennzeichnung

| ID | Produkt | Problem |
|---|---|---|
| 130 | Dinkel | Kein Gluten-Allergen gesetzt. Dinkelflocken (177) hat es. |
| 131 | Roggen, Alte Sorten | Kein Gluten-Allergen. Roggen (alte Sorten, 171) und Roggenflocken (178) haben es. |
| 132 | Haferflocken | Kein Gluten-Allergen. Hafer (173) hat es. |
| 105 | Stangenbohnen | Ernährungs-Tags: `vegan; contains_dairy`. Widerspruch und sachlich falsch. |
| 88, 117, 120, 87, 131, 56 | — | Gar keine Ernährungs-Tags gesetzt. |
| 132, 131, 130 | — | Gar keine Geschmacks-Tags gesetzt. |

Buchweizen (179) und Hirse (174) ohne Gluten ist korrekt, beide sind von Natur aus glutenfrei.

## 3. Preise

**Verkauf unter Einkaufspreis, wenn vom Zweithof bezogen**

| ID | Produkt | Netto-Verkauf | EK Hof 1 | EK Hof 2 |
|---|---|---|---|---|
| 136 | Kleine Zucchini | 6,45 | 3,00 (Jens Looke) | **6,50 (Melchhof)** |
| 143 | Geringelte Bete | 4,58 | 3,00 (Melchhof) | **5,00 (Jens Looke)** |
| 142 | Rote Bete | 4,58 | 3,00 (Melchhof) | **5,00 (Jens Looke)** |

**B2B zahlt netto gleich viel oder mehr als B2C**

| ID | Produkt | B2B netto | B2C netto |
|---|---|---|---|
| 71 | Hof-Apfel-Quitten-Saft | 3,50 | 3,28 |
| 72 | Hof-Rotebete-Möhren-Apfelsaft | 3,50 | 3,28 |
| 179 | Buchweizen, geschält | 9,50 | 9,25 |
| 136 | Kleine Zucchini | 6,50 | 6,45 |
| 155 | Salatmix | 2,80 | 2,80 |
| 161 | Mexikanische Riesentagetes | 0,07 | 0,07 |

**Fehlender B2B-Preis** — Geschäftskunden fallen auf den Brutto-B2C-Preis zurück und zahlen dadurch zu viel:
84 Mini Gurke, 77 Zuckerschoten, 58 Rhabarber Goliath.

**Fehlender Einkaufspreis** — keine Margenkontrolle, die Margenuntergrenze greift nicht:
117 Salatblätter, 132 Haferflocken, 131 Roggen Alte Sorten.

**Fehlender EK beim Zweithof**: 122 Grüner Kohlrabi (Biohof Hübner), 121 Violetter Kohlrabi (Biohof Hübner), 144 Gelbe Bete (Jens Looke).

**Margen-Ausreißer** (üblich sind 29 % / 31 %)

| ID | Produkt | B2B | B2C |
|---|---|---|---|
| 100 | Salat | 0 % | 4 % |
| 71, 72 | Säfte | 14 % | 9 % |
| 170 | Mirabellen | 36 % | 62 % |
| 136 | Kleine Zucchini | 54 % | 53 % |
| 66, 65 | Kartoffel Laura, Soraya | 47 % | 51 % |
| 56, 57 | Bio-Spargel Lila / grün | 50 % | 51 % |

Bei den Kartoffeln fällt auf: Laura und Soraya bei 51 %, Annabelle (150) und Solist (149) bei 24 % und 34 %. Gleiche Warengruppe, doppelt so hohe Marge.

**Gleiches Produkt, unterschiedlicher Preis**
- Mangold Rainbow: 61 kostet 8,50 €/kg (EK 5,90), 78 kostet 5,90 €/kg (EK 3,50).
- Bio-Spargel grün: 54 und 57 beide 19,50 €/kg, aber EK 14,00 gegen 9,00. Marge 22 % gegen 50 %.

**81 Gelbe Zucchini**: der Haupthof ist der teuerste von dreien (3,00 gegen 2,00 und 1,70).

## 4. Doppelte Produkte und doppelte Unterkategorien

**Zwei Unterkategorien mit identischem Namen.** Im Katalog erscheint die Überschrift zweimal:
- „Kohlgewächse": einmal mit 80, 108, einmal mit 122, 138, 121
- „Zwiebelgewächse": einmal mit 145, 140, 123, 187, 188, einmal mit 192 (Porree)

Das sind zwei getrennte Datensätze mit gleichem Label, nicht ein Sortierfehler.

**Vermutlich echte Dubletten**
- 131 „Roggen, Alte Sorten" gegen 171 „Roggen (alte Sorten)": unterschiedlicher Preis (5,50 / 4,90), unterschiedliche Unterkategorie, unterschiedliches Allergen, unterschiedliche Haltbarkeit. Beide in derselben Gruppe.
- 160 „Borretsch-Blüten (einzeln)" gegen 191 „Borretschblüten": eines unter Essbare Blüten, eines unter Kräuter.
- 168 „Griechischer Oregano im Topf" gegen 169 „Oregano im Topf": identischer Preis, identische Einheit.

**Gleicher Anzeigename, zwei Produkte.** Im Shop stehen zwei identisch benannte Einträge nebeneinander:
- Mangold Rainbow (61 / 78)
- Kapuzinerkresseblüten (90 Bund / 183 Stück)
- Mini Gurke (84 kg / 190 Stück)
- Bio-Spargel grün (54 / 57)

## 5. Taxonomie

- Drei überlappende Blatt-Kategorien: „Blattgemüse" (62, 61, 78, 64), „Blattgemüse & Salate" (8 Produkte), „Salate" (75, 100). Mangold liegt in der einen, Schnittmangold in der anderen.
- Getreide verteilt sich auf drei Orte: „Getreideprodukte" (nur 172 Weizen), „Samen & Körner" (131, 132) und gar keine Unterkategorie (9 Produkte, darunter Hafer, Roggen, Dinkel, Mehl).
- „Säfte" liegt unter der Kategorie „Obst & Gemüse".
- 9 Produkte ohne Unterkategorie, alle aus dem Getreide-/Mehlbereich.

## 6. Namen und Schreibweisen

| ID | Ist | Vorschlag |
|---|---|---|
| 92 | Weißen Johannisbeeren | Weiße Johannisbeeren |
| 82 | Grün Zucchini | Grüne Zucchini |
| 104 | Grün Runde Zucchini | Grüne runde Zucchini |
| 56 | Bio-Spargel Lila | Bio-Spargel lila (wie „grün", „weiß") |
| 65 | Soraya Kartoffel | Kartoffel Soraya (wie Laura, Solist, Annabelle) |
| 138 | Rosetten-Pak-Choi/ Tatsoi | Leerzeichen vor dem Schrägstrich fehlt |
| 93 | Groseille Noire / Cassis (FR) | ohne Leerzeichen, wie die übrigen Namen |
| 131 / 171 / 172 | „Roggen, Alte Sorten" / „Roggen (alte Sorten)" / „Weizen (alte Sorten)" | eine Schreibweise wählen |

Gewichtsangabe im Namen ist uneinheitlich: „Junge Bunte Bete, 1kg" gegen „Cocktailtomaten (250-g-Schale)".
Weiter uneinheitlich: „Hofeier x10", „Erster Fenchel" (79) neben „Fenchel" (63).
Der Zusatz „Bio-" steht nur bei den vier Spargel-Produkten, obwohl das gesamte Sortiment biodynamisch ist.

## 7. Texte und Tonalität

- **Anredeform gemischt**: 43 Produkte siezen, 4 duzen (186, 187, 188, 192). Bei 187 und 188 kommen beide Formen im selben Text vor.
- **Gedankenstriche**: 186, 187, 188, 192 verwenden im Deutschen den „Aussage – Nachschub"-Halbgeviertstrich, entgegen der Hausregel. Beispiel 192: „…mildem, süßlichem Zwiebelgeschmack – perfekt für Suppen…"
- **Englische Rechtschreibung gemischt**: 72 Produkte britisch (flavour, colour), 19 amerikanisch (flavor, color). Einige Produkte mischen beides im selben Text (61, 78, 165, 53, 58).
- **Französische Apostrophe gemischt**: 85 Produkte mit geradem `'`, 17 mit typografischem `'`, 3 mit beiden.
- **Variety-Spalte**: bei 73, 63, 59 steht englisch „Regular" im deutschen Feld.
- **Herkunft uneinheitlich**: 109 Produkte „Barnim, Brandenburg", 11 nur „Brandenburg" (alle vom Melchhof: 102–112), 2 „Barnim, Brandenbourg", 3 „Spreewald, Brandenburg".

## 8. Einheiten

- **111 Salbei**: Menge pro Einheit = 1 Gramm pro Bund. Eingabefehler.
- **107 Junge Bunte Bete, 1kg**: keine Menge pro Einheit hinterlegt, das 500-g-Geschwister (94) hat 500.
- **189 Pflaumen**: Einheit „Bd" (Bund), die Beschreibung spricht aber von einer 250-g-Schale. 170 Mirabellen nutzt für dieselbe 250-g-Schale die Einheit „Schale".
- **„Bd" für Ware, die nicht gebündelt wird**: 77 Zuckerschoten (250 g), 58 Rhabarber Goliath (500 g), 94 und 107 Bete, 106 Möhren (750 g).
- **Topfkräuter uneinheitlich**: 128 Basilikum Topf und 129 Petersilie Topf haben Einheit „Bd", 167/168/169 „im Topf" haben Einheit „Topf".
- **181 Champagnerroggen-Vollkornmehl**: Einheit „Beutel", kommt nur einmal vor.
- **58 Rhabarber Goliath** in Bund, **59 Rhabarber** in kg. Gleiche Frucht, zwei Systeme.

## 9. Bestand und Betrieb

- **Der gesamte Bestand liegt auf einem einzigen Tag: 29.07.2026.** Für heute (28.07.) und für 30.07. bis 24.08. ist nichts eingetragen.
- **49 von 125 Produkten haben in den kommenden 28 Tagen null Bestand**, sind aber veröffentlicht und aktiv. Darunter alle Beeren, aller Spargel, beide Zwiebeln, Kirschen, Salattomaten.
- **Haltbarkeit 1 Tag bei 113 von 125 Produkten**, darunter 130 Dinkel, 131 Roggen und 132 Haferflocken. Die übrigen Getreide stehen auf 7 Tagen, Topfkräuter auf 14.
- **184 Cocktailtomaten**: 0,5 kg Bestand.

## 10. Sonstige Konfiguration

- **163 Ganze Korianderpflanze**: veröffentlicht = nein, verfügbar = ja, Anfrage-Sichtbarkeit = immer. Widersprüchlich. Zusätzlich kein Bild.
- **Kein Bild**: 193 Gewürztagetes, 162 Thymianblüten, 185 Drachenkopf, 163 Ganze Korianderpflanze.
- **Saison praktisch ungenutzt**: nur 113 Hofeier hat eine Saison, und die läuft vom 01-01 bis 12-31, also ganzjährig. Das entspricht „keine Saison".
- **Gruppenpositionen lückenhaft**: „Bete" beginnt bei Position 4 (1–3 unbesetzt), „Cocktailtomaten" springt von 1 auf 3, und in jeder einzelnen Gruppe hat mindestens ein Produkt gar keine Position. Betroffen: 71, 134, 144, 143, 142, 95, 191, 147, 83, 190, 89, 122, 62, 177.
- **Metadaten-Spalten komplett leer** bei allen 125 Produkten: Producer Name, Sourcing ID, Selling ID, Comment.
