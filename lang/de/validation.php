<?php

return [

    /*
    |--------------------------------------------------------------------------
    | German validation messages — closes the K3/B4 finding from the UX
    | review. Without this file, Laravel falls back to the default English
    | messages even when the locale is `de`, leaking strings like
    | "The recipient name field is required." into otherwise German UIs.
    |
    | When adding a new field-name-aware error, prefer adding the field
    | label to the `attributes` array at the bottom rather than rewriting
    | the rule message.
    |--------------------------------------------------------------------------
    */

    'accepted' => ':attribute muss akzeptiert werden.',
    'accepted_if' => ':attribute muss akzeptiert werden, wenn :other gleich :value ist.',
    'active_url' => ':attribute muss eine gültige URL sein.',
    'after' => ':attribute muss ein Datum nach :date sein.',
    'after_or_equal' => ':attribute muss ein Datum am oder nach :date sein.',
    'alpha' => ':attribute darf nur Buchstaben enthalten.',
    'alpha_dash' => ':attribute darf nur Buchstaben, Zahlen, Bindestriche und Unterstriche enthalten.',
    'alpha_num' => ':attribute darf nur Buchstaben und Zahlen enthalten.',
    'array' => ':attribute muss eine Liste sein.',
    'ascii' => ':attribute darf nur Einzelbyte-Zeichen und -Symbole enthalten.',
    'before' => ':attribute muss ein Datum vor :date sein.',
    'before_or_equal' => ':attribute muss ein Datum am oder vor :date sein.',
    'between' => [
        'array' => ':attribute muss zwischen :min und :max Einträge haben.',
        'file' => ':attribute muss zwischen :min und :max Kilobyte groß sein.',
        'numeric' => ':attribute muss zwischen :min und :max liegen.',
        'string' => ':attribute muss zwischen :min und :max Zeichen lang sein.',
    ],
    'boolean' => ':attribute muss „wahr" oder „falsch" sein.',
    'can' => ':attribute enthält einen unzulässigen Wert.',
    'confirmed' => ':attribute stimmt nicht mit der Bestätigung überein.',
    'contains' => ':attribute fehlt ein erforderlicher Wert.',
    'current_password' => 'Das Passwort ist nicht korrekt.',
    'date' => ':attribute muss ein gültiges Datum sein.',
    'date_equals' => ':attribute muss ein Datum gleich :date sein.',
    'date_format' => ':attribute muss dem Format :format entsprechen.',
    'decimal' => ':attribute muss :decimal Nachkommastellen haben.',
    'declined' => ':attribute muss abgelehnt werden.',
    'declined_if' => ':attribute muss abgelehnt werden, wenn :other gleich :value ist.',
    'different' => ':attribute und :other müssen sich unterscheiden.',
    'digits' => ':attribute muss :digits Stellen haben.',
    'digits_between' => ':attribute muss zwischen :min und :max Stellen haben.',
    'dimensions' => ':attribute hat ungültige Bildmaße.',
    'distinct' => ':attribute hat einen doppelten Wert.',
    'doesnt_end_with' => ':attribute darf nicht mit einem der folgenden Werte enden: :values.',
    'doesnt_start_with' => ':attribute darf nicht mit einem der folgenden Werte beginnen: :values.',
    'email' => ':attribute muss eine gültige E-Mail-Adresse sein.',
    'ends_with' => ':attribute muss mit einem der folgenden Werte enden: :values.',
    'enum' => 'Die Auswahl für :attribute ist ungültig.',
    'exists' => 'Die Auswahl für :attribute ist ungültig.',
    'extensions' => ':attribute muss eine der folgenden Endungen haben: :values.',
    'file' => ':attribute muss eine Datei sein.',
    'filled' => ':attribute darf nicht leer sein.',
    'gt' => [
        'array' => ':attribute muss mehr als :value Einträge haben.',
        'file' => ':attribute muss größer als :value Kilobyte sein.',
        'numeric' => ':attribute muss größer als :value sein.',
        'string' => ':attribute muss länger als :value Zeichen sein.',
    ],
    'gte' => [
        'array' => ':attribute muss :value oder mehr Einträge haben.',
        'file' => ':attribute muss größer oder gleich :value Kilobyte sein.',
        'numeric' => ':attribute muss größer oder gleich :value sein.',
        'string' => ':attribute muss mindestens :value Zeichen lang sein.',
    ],
    'hex_color' => ':attribute muss eine gültige Hex-Farbe sein.',
    'image' => ':attribute muss ein Bild sein.',
    'in' => 'Die Auswahl für :attribute ist ungültig.',
    'in_array' => ':attribute muss in :other vorkommen.',
    'integer' => ':attribute muss eine Ganzzahl sein.',
    'ip' => ':attribute muss eine gültige IP-Adresse sein.',
    'ipv4' => ':attribute muss eine gültige IPv4-Adresse sein.',
    'ipv6' => ':attribute muss eine gültige IPv6-Adresse sein.',
    'json' => ':attribute muss ein gültiger JSON-String sein.',
    'list' => ':attribute muss eine Liste sein.',
    'lowercase' => ':attribute muss in Kleinbuchstaben geschrieben sein.',
    'lt' => [
        'array' => ':attribute muss weniger als :value Einträge haben.',
        'file' => ':attribute muss kleiner als :value Kilobyte sein.',
        'numeric' => ':attribute muss kleiner als :value sein.',
        'string' => ':attribute muss kürzer als :value Zeichen sein.',
    ],
    'lte' => [
        'array' => ':attribute darf nicht mehr als :value Einträge haben.',
        'file' => ':attribute muss kleiner oder gleich :value Kilobyte sein.',
        'numeric' => ':attribute muss kleiner oder gleich :value sein.',
        'string' => ':attribute darf höchstens :value Zeichen lang sein.',
    ],
    'mac_address' => ':attribute muss eine gültige MAC-Adresse sein.',
    'max' => [
        'array' => ':attribute darf höchstens :max Einträge haben.',
        'file' => ':attribute darf höchstens :max Kilobyte groß sein.',
        'numeric' => ':attribute darf höchstens :max sein.',
        'string' => ':attribute darf höchstens :max Zeichen lang sein.',
    ],
    'max_digits' => ':attribute darf höchstens :max Stellen haben.',
    'mimes' => ':attribute muss eine Datei vom Typ :values sein.',
    'mimetypes' => ':attribute muss eine Datei vom Typ :values sein.',
    'min' => [
        'array' => ':attribute muss mindestens :min Einträge haben.',
        'file' => ':attribute muss mindestens :min Kilobyte groß sein.',
        'numeric' => ':attribute muss mindestens :min sein.',
        'string' => ':attribute muss mindestens :min Zeichen lang sein.',
    ],
    'min_digits' => ':attribute muss mindestens :min Stellen haben.',
    'missing' => ':attribute darf nicht vorhanden sein.',
    'missing_if' => ':attribute darf nicht vorhanden sein, wenn :other gleich :value ist.',
    'missing_unless' => ':attribute darf nicht vorhanden sein, außer wenn :other gleich :value ist.',
    'missing_with' => ':attribute darf nicht vorhanden sein, wenn :values vorhanden ist.',
    'missing_with_all' => ':attribute darf nicht vorhanden sein, wenn :values vorhanden sind.',
    'multiple_of' => ':attribute muss ein Vielfaches von :value sein.',
    'not_in' => 'Die Auswahl für :attribute ist ungültig.',
    'not_regex' => 'Das Format von :attribute ist ungültig.',
    'numeric' => ':attribute muss eine Zahl sein.',
    'password' => [
        'letters' => ':attribute muss mindestens einen Buchstaben enthalten.',
        'mixed' => ':attribute muss mindestens einen Großbuchstaben und einen Kleinbuchstaben enthalten.',
        'numbers' => ':attribute muss mindestens eine Zahl enthalten.',
        'symbols' => ':attribute muss mindestens ein Sonderzeichen enthalten.',
        'uncompromised' => 'Dieses :attribute ist in einem Datenleck aufgetaucht. Bitte wähle ein anderes.',
    ],
    'present' => ':attribute muss vorhanden sein.',
    'present_if' => ':attribute muss vorhanden sein, wenn :other gleich :value ist.',
    'present_unless' => ':attribute muss vorhanden sein, außer wenn :other gleich :value ist.',
    'present_with' => ':attribute muss vorhanden sein, wenn :values vorhanden ist.',
    'present_with_all' => ':attribute muss vorhanden sein, wenn :values vorhanden sind.',
    'prohibited' => ':attribute ist nicht erlaubt.',
    'prohibited_if' => ':attribute ist nicht erlaubt, wenn :other gleich :value ist.',
    'prohibited_if_accepted' => ':attribute ist nicht erlaubt, wenn :other akzeptiert ist.',
    'prohibited_if_declined' => ':attribute ist nicht erlaubt, wenn :other abgelehnt ist.',
    'prohibited_unless' => ':attribute ist nicht erlaubt, außer wenn :other in :values ist.',
    'prohibits' => ':attribute schließt :other aus.',
    'regex' => 'Das Format von :attribute ist ungültig.',
    'required' => ':attribute ist erforderlich.',
    'required_array_keys' => ':attribute muss Einträge für :values enthalten.',
    'required_if' => ':attribute ist erforderlich, wenn :other gleich :value ist.',
    'required_if_accepted' => ':attribute ist erforderlich, wenn :other akzeptiert ist.',
    'required_if_declined' => ':attribute ist erforderlich, wenn :other abgelehnt ist.',
    'required_unless' => ':attribute ist erforderlich, außer wenn :other in :values ist.',
    'required_with' => ':attribute ist erforderlich, wenn :values vorhanden ist.',
    'required_with_all' => ':attribute ist erforderlich, wenn :values vorhanden sind.',
    'required_without' => ':attribute ist erforderlich, wenn :values nicht vorhanden ist.',
    'required_without_all' => ':attribute ist erforderlich, wenn keiner der Werte :values vorhanden ist.',
    'same' => ':attribute muss mit :other übereinstimmen.',
    'size' => [
        'array' => ':attribute muss genau :size Einträge enthalten.',
        'file' => ':attribute muss genau :size Kilobyte groß sein.',
        'numeric' => ':attribute muss genau :size sein.',
        'string' => ':attribute muss genau :size Zeichen lang sein.',
    ],
    'starts_with' => ':attribute muss mit einem der folgenden Werte beginnen: :values.',
    'string' => ':attribute muss eine Zeichenkette sein.',
    'timezone' => ':attribute muss eine gültige Zeitzone sein.',
    'unique' => ':attribute ist bereits vergeben.',
    'uploaded' => ':attribute konnte nicht hochgeladen werden.',
    'uppercase' => ':attribute muss in Großbuchstaben geschrieben sein.',
    'url' => ':attribute muss eine gültige URL sein.',
    'ulid' => ':attribute muss eine gültige ULID sein.',
    'uuid' => ':attribute muss eine gültige UUID sein.',

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Field labels for German error messages.
    |--------------------------------------------------------------------------
    |
    | Anything submitted by the customer-facing wizard or by the admin forms
    | should have a friendly German label here so the validator never echoes
    | the raw snake_case column name back at the user.
    |
    | When adding a new FormRequest, add its required fields here too. If
    | every form continues to share field names (e.g. recipient_name) the
    | label is reusable as-is.
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name' => 'Name',
        'email' => 'E-Mail',
        'password' => 'Passwort',
        'password_confirmation' => 'Passwortbestätigung',
        'phone' => 'Telefonnummer',

        // Address fields (onboarding + profile)
        'recipient_name' => 'Empfängername',
        'street_line_1' => 'Straße und Hausnummer',
        'street_line_2' => 'Adresszusatz',
        'postal_code' => 'PLZ',
        'city' => 'Stadt',
        'country_code' => 'Land',
        'building_code' => 'Gebäude / Türcode',
        'floor' => 'Stockwerk / Wohnung',
        'delivery_instructions' => 'Lieferhinweise',

        // Subscription / preferences
        'box_size' => 'Box-Größe',
        'plan' => 'Tarif',
        'fulfillment_mode' => 'Zustellung',
        'delivery_weekday' => 'Liefertag',
        'preferred_timeslot' => 'Bevorzugtes Zeitfenster',
        'preferred_delivery_window' => 'Wunschwoche oder -Datum',
        'special_instructions' => 'Besondere Hinweise',
        'allergies' => 'Allergien',
        'allergies.*' => 'Allergie',

        // Payment / billing
        'amount' => 'Betrag',
        'reason' => 'Grund',

        // Catalog / admin
        'producer_id' => 'Erzeuger',
        'category' => 'Kategorie',
        'unit_price' => 'Stückpreis',
        'buying_price' => 'Einkaufspreis',
        'translations.de.name' => 'Name (DE)',
        'translations.en.name' => 'Name (EN)',
        'translations.fr.name' => 'Name (FR)',
        'translations.de.unit' => 'Einheit (DE)',
        'translations.en.unit' => 'Einheit (EN)',
        'translations.fr.unit' => 'Einheit (FR)',
        'translations.de.origin' => 'Herkunft (DE)',
        'translations.en.origin' => 'Herkunft (EN)',
        'translations.fr.origin' => 'Herkunft (FR)',
        'translations.de.variety' => 'Sorte (DE)',
        'translations.en.variety' => 'Sorte (EN)',
        'translations.fr.variety' => 'Sorte (FR)',
        'translations.de.taste_profile' => 'Geschmacksprofil (DE)',
        'translations.en.taste_profile' => 'Geschmacksprofil (EN)',
        'translations.fr.taste_profile' => 'Geschmacksprofil (FR)',
        'translations.de.region' => 'Region (DE)',
        'translations.en.region' => 'Region (EN)',
        'translations.fr.region' => 'Region (FR)',
        'translations.de.description' => 'Beschreibung (DE)',
        'translations.en.description' => 'Beschreibung (EN)',
        'translations.fr.description' => 'Beschreibung (FR)',

        // Delivery admin
        'address_id' => 'Adresse',
        'scheduled_for_date' => 'Liefertermin',
        'timeslot' => 'Zeitfenster',
        'status' => 'Status',
        'overrides_delivery_id' => 'Basis-Lieferung',

        // Weekly catalog
        'week_start_date' => 'Wochenstart',
        'rows.*.product_id' => 'Produkt',
        'rows.*.quantity' => 'Menge',
        'rows.*.unit' => 'Einheit',
        'rows.*.new.name' => 'Produktname',
        'rows.*.new.category' => 'Kategorie',
        'rows.*.new.unit_price_cents' => 'Stückpreis',
    ],

];
