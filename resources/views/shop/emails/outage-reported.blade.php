<!DOCTYPE html>
<html lang="de">
<head><meta charset="utf-8"></head>
<body style="font-family: sans-serif; color: #1f2937; line-height: 1.6;">
    <h2 style="color: #b91c1c;">Hofladen meldet: Nichts auf Lager</h2>
    <p>
        Eine Besucherin oder ein Besucher hat im Hofladen den Leer-Zustand gesehen
        und den Melde-Knopf gedrückt ({{ $reportedAt }}).
    </p>
    <p>
        Da der Hofladen normalerweise immer Bestand führt, ist das sehr wahrscheinlich
        eine Störung. Bitte prüfen:
    </p>
    <ol>
        <li>Admin-Inventar: Gibt es Eingänge für die kommenden Tage?</li>
        <li>Migrationen auf dem Server: <code>php artisan migrate --force</code></li>
        <li>Caches: <code>php artisan cache:clear &amp;&amp; php artisan config:clear</code></li>
        <li>cron-job.org: Läuft der Cron-Tick fehlerfrei? (Er materialisiert die Lieferpläne.)</li>
    </ol>
    <p style="color: #6b7280; font-size: 12px;">
        Diese Mail wird höchstens einmal pro Stunde verschickt, egal wie viele Personen den Knopf drücken.
    </p>
</body>
</html>
