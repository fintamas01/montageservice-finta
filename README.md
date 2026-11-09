# Montageservice Finta – Website

Statische Website für **https://montagefinta.de** (Astro 7 + Tailwind CSS 4) mit
einem kleinen PHP-Endpunkt (PHPMailer) für das Anfrageformular.

## Voraussetzungen

- Node.js ≥ 22.12
- PHP ≥ 8.1 mit `fileinfo`, `mbstring`, `openssl` (lokal und auf dem Server)
- Composer (nur für den Build)

## Befehle

| Befehl              | Zweck                                                        |
| ------------------- | ------------------------------------------------------------ |
| `npm install`       | Abhängigkeiten installieren                                  |
| `cd backend && composer install --no-dev --optimize-autoloader` | PHPMailer installieren (vor dem ersten Build) |
| `npm run dev`       | Entwicklungsserver (`localhost:4321`, ohne PHP-Formular)     |
| `npm run build`     | Produktions-Build nach `dist/` inkl. `dist/api/`             |
| `npm run preview`   | Build + lokaler PHP-Server auf `localhost:8080` (mit Formular) |
| `npm run check`     | TypeScript-/Astro-Prüfung                                    |
| `npm run verify`    | Build prüfen (Meta-Tags, H1, Alt-Texte, Links, Sitemap)      |
| `npm run test:php`  | Tests der Formular-Validierung                               |
| `npm test`          | alles oben in einem Lauf                                     |

## Struktur

```
src/data/site.ts        Unternehmensdaten, Leistungen, Referenzen (einzige Quelle!)
src/data/form.ts        Upload-Limits und Fehlermeldungen des Formulars
src/styles/global.css   Design-Tokens, Typografie, Buttons, Animationen
src/components/         Header, Footer, Sektionen der Startseite, Formular
src/components/ui/      Icon (SVG), Pending (Markierung fehlender Pflichtangaben)
src/assets/images/      Originalfotos (werden beim Build zu AVIF/WebP optimiert)
backend/                PHP-Endpunkt, Validierung, Tests, .htaccess für /api
public/.htaccess        Apache: saubere URLs, 404, Caching, Security-Header
```

## Vor dem Livegang: fehlende Angaben

In `src/data/site.ts` sind folgende Felder noch `null` und müssen vom Inhaber
bestätigt und eingetragen werden. Solange sie fehlen, zeigen Impressum und
Datenschutzerklärung deutlich markierte Hinweise („Angabe folgt“):

- `contact.phone`, `contact.email` (erscheinen danach automatisch in Header,
  Footer, Kontaktbereich, Anfrage-Seite und strukturierten Daten)
- `legal.owner`, `legal.street`, `legal.postalCodeCity`
- `legal.vatId` (nur falls vorhanden)
- `legal.hostingProvider`

Impressum und Datenschutzerklärung sind sorgfältig erstellte Entwürfe, aber
**keine Rechtsberatung** – bitte vor Veröffentlichung rechtlich prüfen lassen.

## Deployment (Apache-Shared-Hosting)

1. `npm ci && (cd backend && composer install --no-dev --optimize-autoloader)`
2. `npm test` – muss fehlerfrei durchlaufen.
3. Inhalt von `dist/` (inkl. versteckter Dateien `.htaccess` und
   `api/.htaccess`) in das Web-Verzeichnis hochladen, z. B. `public_html/`.
4. `mail-config.example.php` kopieren, als `mail-config.php` mit den echten
   SMTP-Daten **außerhalb** des Web-Verzeichnisses ablegen
   (z. B. `/home/<user>/mail-config.php` neben `public_html/`).
5. Testanfrage über `/anfrage` senden (mit und ohne Foto) und Eingang prüfen.
6. Wenn HTTPS aktiv ist: in `public/.htaccess` die HTTPS-/www-Weiterleitung
   und anschließend HSTS einkommentieren, neu bauen, erneut hochladen.
7. Sitemap `https://montagefinta.de/sitemap-index.xml` in der Google Search
   Console einreichen.

PHP-Fehlerausgabe (`display_errors`) muss auf dem Server deaktiviert sein;
Versandfehler werden per `error_log` protokolliert.
