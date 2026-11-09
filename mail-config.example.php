<?php

/*
 * Vorlage für die SMTP-Konfiguration des Anfrageformulars.
 *
 * 1. Datei kopieren und in "mail-config.php" umbenennen.
 * 2. Auf dem Server AUSSERHALB des Web-Verzeichnisses ablegen,
 *    z. B. /home/<user>/mail-config.php (neben public_html/).
 * 3. Niemals ins Git-Repository einchecken (.gitignore).
 */

return [
    'host' => 'smtp.example.com',
    'port' => 465,
    'encryption' => 'smtps', // 'smtps' (Port 465) oder 'starttls' (Port 587)

    'username' => 'your-email@example.com',
    'password' => 'YOUR_PASSWORD',

    // Absender sollte zur Domain bzw. zum SMTP-Konto passen (SPF/DKIM).
    'from_email' => 'your-email@example.com',
    'from_name' => 'Montageservice Finta Website',

    'to_email' => 'info@example.com',
    'to_name' => 'Montageservice Finta',

    // Optional: Spam-Schutz – max. Anfragen pro IP im Zeitfenster (Sekunden)
    'rate_limit_max' => 5,
    'rate_limit_window' => 900,
    'rate_limit_salt' => 'bitte-durch-zufaelligen-wert-ersetzen',
];
