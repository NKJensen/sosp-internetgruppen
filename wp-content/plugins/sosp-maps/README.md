# SOSP Maps Plugin

Et WordPress plugin til at administrere og vise søspejdergrupper på et interaktivt Google Maps kort.

## Features

✅ Administrer grupper fra WordPress admin panel
✅ Tilføj/rediger/slet gruppemarkører
✅ Interaktivt Google Maps kort på frontend
✅ Responsive design
✅ GPS koordinater styring
✅ Gruppekontaktoplysninger (e-mail, hjemmeside, adresse)
✅ SEO-venligt
✅ Sikker med nonce verifikation

## Installation

Se [DEPLOYMENT.md](DEPLOYMENT.md) for detaljerede installations- og deployment-instruktioner.

### Quick Start

1. Upload plugin mappen til `/wp-content/plugins/`
2. Aktivér pluginen i WordPress admin
3. Gå til "SOSP Maps" menuen for at administrere grupper
4. Indsæt `[sosp_maps]` shortcode på en side for at vise kortet

## Krav

- WordPress 5.0+
- PHP 5.6+
- MySQL 5.7+

## Brug

### Admin

1. Naviger til "SOSP Maps" i WordPress admin menu
2. Klik "Tilføj ny gruppe"
3. Udfyld formular med gruppens oplysninger
4. Gem gruppe
5. Markøren vil automatisk vises på kortet

### Frontend

Tilføj shortcoden på en side/post:

```
[sosp_maps]
```

## Konfiguration: wp-config.php

API-nøgler og andre hemmeligheder må **ikke** ligge i plugin-koden eller i git.
De skal i stedet defineres i WordPress-installationens `wp-config.php`, som aldrig
commites til git.

Tilføj følgende linje i `wp-config.php` **før** linjen
`/* That's all, stop editing! Happy publishing. */`:

```php
// Google Maps API-nøgle til sosp-maps plugin
define( 'SOSP_MAPS_GOOGLE_API_KEY', 'din-google-maps-api-nøgle-her' );
```

### Sådan får du en Google Maps API-nøgle

1. Gå til [Google Cloud Console](https://console.cloud.google.com/)
2. Opret eller vælg et projekt
3. Aktivér **Maps JavaScript API** og **Geocoding API**
4. Gå til **APIs & Services → Credentials** og opret en API-nøgle
5. Begræns nøglen til de to ovenstående API'er og til dit domæne

## Tilpasning

Se [DEPLOYMENT.md](DEPLOYMENT.md) for detaljer om:
- Marker billedet
- Kort højde
- Stilisering

## Database

Pluginen opretter automatisk tabel: `wp_sosp_groups`

Struktur:
- ID (int) - Gruppe ID
- gr_navn (varchar) - Gruppens navn
- gr_side (varchar) - Hjemmeside URL
- gr_lat (decimal) - Bredde koordinat
- gr_lng (decimal) - Længde koordinat
- gr_add (varchar) - Adresse
- gr_mail (varchar) - E-mail
- created_at (datetime) - Oprettelsestidspunkt

## Licens

GPL2

## Forfatter

Søspejderne
