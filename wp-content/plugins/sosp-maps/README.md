# SOSP Maps Plugin

Et WordPress plugin til at administrere og vise søspejdergrupper på et interaktivt OpenStreetMap-kort.

## Features

✅ Administrer grupper fra WordPress admin panel
✅ Tilføj/rediger/slet gruppemarkører
✅ Interaktivt OpenStreetMap-kort (Leaflet) på frontend med samling af nærtliggende markører
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

## Kort og adresseopslag

Pluginen kræver **ingen API-nøgle**:

- **Kortvisning:** [Leaflet](https://leafletjs.com) 1.9.4 med OpenStreetMaps standardkort
  (data © OpenStreetMap-bidragydere). Leaflet og Leaflet.markercluster 1.5.3 ligger
  lokalt i `assets/lib/`.
- **Adresse → koordinater:** [Nominatim](https://nominatim.org), begrænset til Danmark.
  Nominatim tillader højst 1 opslag pr. sekund, hvilket er rigeligt, da der kun slås
  op, når en gruppe gemmes uden koordinater.

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
