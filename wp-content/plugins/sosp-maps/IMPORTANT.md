# Vigtig Information

## Marker billede

Husk at kopiere marker.png filen fra:
```
CSS og jquery/Vladyslavs random mappe/maps/marker.png
```

Til:
```
sosp-maps/assets/images/marker.png
```

## Databasen

Pluginen bruger tabellen: `wp_sosp_groups` (automatisk oprettet)

Hvis du skal migrere fra gamle system (`markers_soa`), kør denne SQL:

```sql
INSERT INTO wp_sosp_groups (gr_navn, gr_side, gr_lat, gr_lng, gr_add, gr_mail)
SELECT gr_navn, gr_side, gr_lat, gr_lng, gr_add, gr_mail FROM markers_soa;
```

## Filer struktur

```
sosp-maps/
├── sosp-maps.php              (Hovedfil - start her)
├── README.md                   (Plugin info)
├── DEPLOYMENT.md              (Detaljeret installations guide)
├── QUICK_START.md             (Hurtig guide på dansk)
├── includes/
│   ├── class-sosp-maps-db.php       (Database klasse)
│   ├── class-sosp-maps-admin.php    (Admin panel)
│   └── class-sosp-maps-frontend.php (Frontend kort)
└── assets/
    ├── js/
    │   └── maps-frontend.js    (Google Maps JavaScript)
    ├── css/
    │   └── maps-frontend.css   (Styling)
    └── images/
        └── marker.png         (Marker ikon - skal uploades)
```

## Næste skridt

1. ✅ Plugin er klar til upload
2. 📦 Upload til: `/wp-content/plugins/sosp-maps/`
3. 🔌 Aktivér i WordPress admin
4. 🗺️ Tilføj grupper via "SOSP Maps" menu
5. 📝 Indsæt `[sosp_maps]` shortcode på en side

## Vigtige detaljer

- **Google Maps API key**: Allerede sat, men kan ændres hvis nødvendigt
- **Koordinater format**: Decimal (f.eks. 55.408, 10.389)
- **Sikkerhed**: Kun admin kan ændre data
- **Responsive**: Virker på alle enheder

Held og lykke! 🎉
