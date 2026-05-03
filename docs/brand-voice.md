# Erdgut brand voice glossary

For all customer-facing copy in `lang/{de,en,fr}.json`, the marketing site, the wizard, the dashboard, and transactional emails.

> **One line.** Talk to the customer like a market trader who already knows them by name — warm, informal, specific.

---

## German pronoun policy

**Always informal — `du / dein / dich / dir`. Never formal `Sie / Ihr / Ihnen`.**

- Lowercase **du / dein / dich / dir** mid-sentence.
- Capitalize **Du / Dein / Dich / Dir** only when starting a sentence.
- The customer is *one person* — second-person singular, never plural.
- This applies to wizard prose, dashboard copy, transactional emails, error messages, modal text, the privacy notice, the spargel page — everywhere a customer reads something.

### Common verb-form replacements

| Formal (don't ship) | Informal (ship this) |
|---|---|
| Wählen Sie | Wähle |
| Geben Sie ein | Gib ein |
| Klicken Sie | Klicke |
| Bestätigen Sie | Bestätige |
| Hinterlegen Sie | Hinterlege |
| Bitte aktualisieren Sie | Aktualisiere bitte |
| Bei Fragen wenden Sie sich an uns | Bei Fragen schreib uns einfach zurück |
| Bitte stellen Sie sicher | Sorge bitte dafür |
| Vielen Dank für Ihr Vertrauen | Danke, dass du dabei bist |

The full set lives in `lang/de.json`; if you find a formal-address phrase, rewrite it in place and update parity in `en.json` / `fr.json`.

### Edge cases

- **Privacy notice (`/privacy`)** — also informal. The legal content can be precise without being stiff.
- **Email subject lines** — informal. *"Deine Erdgut-Box ist unterwegs"* not *"Ihre Erdgut-Lieferung"*.
- **Validation errors / system messages** — informal. *"Diese E-Mail kennen wir noch nicht"* not *"Diese E-Mail-Adresse ist nicht in unserem System hinterlegt"*.
- **Admin views** are *not* customer-facing — admins can read more direct copy (no need for warmth).
- **3rd-person `sie / ihr`** (she / her / they / their) is fine and orthogonal — only the formal 2nd-person `Sie / Ihr / Ihnen` is forbidden.

---

## Tone

- **Warm, editorial, factual.** Like a producer talking about their own work.
- **Specific over abstract.** *"Donnerstagnachmittag"* beats *"später in der Woche"*. *"Max im Spreewald"* beats *"einer unserer Lieferanten"*.
- **Names matter.** Producers, founders, places get named. *"Lucille, Arthur und Milan"*, *"Spargelhof Kalkwitz"*, *"Max Scharkowski"*. The opposite (anonymous platform language) is the homogenized supermarket voice we're rejecting.
- **Italic editorial lines** (Fraunces serif, `font-editorial italic`) are reserved for moments where the brand voice peeks through prose — quotes, signatures, weekly intros, story endings. Don't italicize utility copy (form labels, error messages, button text).
- **No corporate softeners.** *"Unsere Mitarbeiter"*, *"Im Bestreben um Kundenzufriedenheit"*, *"Bitte beachten Sie"* — all out.

---

## Forbidden words (product policy, not just style)

- **`vegan`** — Erdgut boxes contain eggs and dairy add-ons; the word "vegan" misleads.
- **`vegetarisch`** — current boxes are vegetable + fruit only, but the wording promises something the supply chain doesn't enforce.
- **`Bio` without context** — fine when the producer actually carries the certification (Spargelhof Kalkwitz does; not every product does). Default to *"vom Hof"* / *"direkt vom Erzeuger"* when in doubt.

---

## Punctuation + typography

- **Em dash (`—`)** for editorial pauses. *"Eine Box jede Woche — direkt vom Hof."* The em dash is part of the brand's signature rhythm.
- **Lowercase eyebrows.** Capitalisation comes from the `uppercase` Tailwind class, not from the source string. Source: *"From the farm"*. Rendered: *"FROM THE FARM"*.
- **Numbers.** German thousands separator is `.`; decimal separator is `,`. *"24,50 €"* not *"24.50 €"*.
- **Currency symbol.** Always after the number with a non-breaking space if possible: *"24,50 €"* not *"€24,50"*.
- **Quotes.** German curly quotes „so" or guillemets «so». ASCII straight quotes only inside code/JSON values.

---

## When in doubt

Read it aloud. If it sounds like an ad email from a payment provider, rewrite. If it sounds like Lucille / Arthur / Milan would actually say it at the kitchen table — ship it.
