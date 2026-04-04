# SOSP Maps Plugin - Deployment Guide

## Oversigt

SOSP Maps er en WordPress plugin til administrering af søspejdergrupper på et interaktivt Google Maps kort.

## Installation via SSH

### Trin 1: Forbind via SSH

```bash
ssh brugernavn@sosp.internetgruppen.dk
```

### Trin 2: Naviger til WordPress plugins mappe

```bash
cd public_html/wp-content/plugins
```

### Trin 3: Upload plugin filerne

Du kan enten:

**Mulighed A: Clone fra git (hvis git er tilgængelig)**
```bash
git clone <dit-repo-url> sosp-maps
```

**Mulighed B: Upload via SCP (fra din lokale computer)**
```bash
# Fra din lokale machine
scp -r SOSP-Maps-Plugin brugernavn@sosp.internetgruppen.dk:public_html/wp-content/plugins/sosp-maps
```

**Mulighed C: Upload via FTP**
- Åbn FTP-program (f.eks. FileZilla)
- Forbind til sosp.internetgruppen.dk
- Naviger til `/public_html/wp-content/plugins/`
- Upload hele `SOSP-Maps-Plugin` mappen og omdøb den til `sosp-maps`

### Trin 4: Aktivér pluginen

1. Log ind i WordPress admin panel (sosp.internetgruppen.dk/wp-admin)
2. Gå til "Plugins"
3. Find "SOSP Maps - Gruppe Markører"
4. Klik "Aktivér"

Databasetabellen oprettes automatisk ved aktivering.

## Konfiguration

### Google Maps API Key

Pluginen bruger en default Google Maps API key. Hvis du vil ændre den:

1. Åbn `/sosp-maps/includes/class-sosp-maps-frontend.php`
2. Find linjen: `wp_enqueue_script( 'google-maps', 'https://maps.googleapis.com/maps/api/js?key=AIzaSyCOLP7abuEWwxQSFIp9dQg_TnFP1CiOJhk', ...`
3. Erstat API key med din egen fra Google Cloud Console

### Marker billede

Marker billedet skal placeres i: `/wp-content/plugins/sosp-maps/assets/images/marker.png`

## Administratorer bruger

### Tilføj grupper

1. I WordPress admin: "SOSP Maps" → "Tilføj ny gruppe"
2. Udfyld formularen:
   - **Gruppens navn**: Navn på søspejdergruppen
   - **Adresse**: Gruppens adresse
   - **E-mail**: Gruppe kontakt e-mail
   - **Hjemmeside URL**: Gruppens website
   - **Bredde/Længde**: GPS koordinater

### Find GPS koordinater

1. Åbn https://maps.google.com
2. Søg efter gruppens adresse
3. Højreklik på markeøren
4. Kopier de viste koordinater
5. Indsæt i formularen (lat = bredde, lng = længde)

## Frontend - Vis kortet

### Shortcode

Indsæt på en side eller post hvor du vil vise kortet:

```
[sosp_maps]
```

Kortet vil have en højde på 600px som standard.

### Tilpas højde

Åbn `/sosp-maps/includes/class-sosp-maps-frontend.php` og ændre:

```php
return '<div id="sosp-map-canvas" style="width: 100%; height: 600px;"></div>';
```

Ændre `600px` til ønsket højde.

## Migrering fra gammelt system

Hvis du har eksisterende data i den gamle `markers_soa` tabel:

1. SSH ind på serveren
2. Kør denne SQL kommando i phpMyAdmin eller via CLI:

```sql
INSERT INTO wp_sosp_groups (gr_navn, gr_side, gr_lat, gr_lng, gr_add, gr_mail)
SELECT gr_navn, gr_side, gr_lat, gr_lng, gr_add, gr_mail FROM markers_soa;
```

## Fejlfinding

### Kort vises ikke

- Tjek at pluginen er aktiveret
- Tjek at shortcoden `[sosp_maps]` er på siden
- Kontroller browser console for JavaScript fejl (F12)
- Tjek Google Maps API key er gyldig

### Markørerne vises ikke

- Kontroller at grupperne er tilføjet i admin panelet
- Tjek at koordinater er gyldige
- Åbn browser console (F12) og tjek for fejlmeddelelser

### Database fejl

- Tjek at WordPress database brugeren har CREATE TABLE permission
- Deaktivér og reaktivér pluginen for at genskabe tabellen

## Sikkerhed

- Kun WordPress administratorer kan administrere grupper
- Alle brugerinput bliver sanitized og escaped
- Databaseforespørgsler bruger prepared statements

## Support & Vedligeholdelse

For spørgsmål eller problemer kontakt: [din kontakt information]

## Changelog

### Version 2.0
- Konverteret fra standalone til WordPress plugin
- Admin interface for gruppestyring
- Shortcode til frontend display
- Forbedret sikkerhed og WordPress integration
- Responsive design
