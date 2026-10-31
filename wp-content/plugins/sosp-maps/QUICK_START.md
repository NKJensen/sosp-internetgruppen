# Hurtig Opstarts Guide - SOSP Maps Plugin

## Før du starter

Du skal have:
- SSH/FTP adgang til sosp.internetgruppen.dk
- WordPress admin adgang
- Gruppernes GPS koordinater (eller adresser for at slå op)

## Installation (5 minutter)

### 1. Upload plugin (via SSH)

```bash
# Forbind til serveren
ssh brugernavn@sosp.internetgruppen.dk

# Naviger til plugins mappen
cd public_html/wp-content/plugins

# Upload filerne (fra din lokale computer - åbn ny terminal)
scp -r SOSP-Maps-Plugin brugernavn@sosp.internetgruppen.dk:/home/brugernavn/sosp/wp-content/plugins/sosp-maps
```

### 2. Aktivér i WordPress

1. Log ind: `sosp.internetgruppen.dk/wp-admin`
2. Menuen: "Plugins" → find "SOSP Maps" → Klik "Aktivér"

✅ Databasen oprettes automatisk!

## Sådan bruger du det

### Tilføj en gruppe

1. **WordPress admin** → "SOSP Maps"
2. Klik blå knap "Tilføj ny gruppe"
3. Udfyld:
   - **Navn**: "Odense Søspejdere"
   - **Adresse**: "Vesterskoven 1, 5000 Odense"
   - **E-mail**: "kontakt@odsoe.dk"
   - **Hjemmeside**: "http://odsoe.dk"
   - **Koordinater**: 55.408 og 10.389

4. Klik "Gem gruppe"

### Find koordinater nemt

Koordinaterne udfyldes automatisk ud fra adressen, hvis felterne er tomme. Vil du finde dem selv:

1. Åbn https://www.openstreetmap.org
2. Søg gruppens adresse
3. Højreklik på stedet og vælg "Vis adresse"
4. Se tallene i venstre side (det er lat,lng)
5. Kopier dem til formularen

### Vis kortet på siden

1. Rediger den side hvor du vil kortet skal være
2. Indsæt teksten: `[sosp_maps]`
3. Publish

🎉 Kortet vises nu med alle dine grupper!

## Marker billede

Hvis markørerne ikke vises korrekt:

1. Find `marker.png` i mappen `CSS og jquery/Vladyslavs random mappe/maps/`
2. Upload det til: `/wp-content/plugins/sosp-maps/assets/images/marker.png`
3. Opdatér siden

## Alt fungerer - hvad nu?

- **Rediger gruppe**: Klik "Ret" i listen
- **Slet gruppe**: Klik "Slet" i listen  
- **Kopier data fra gammel system**: Se DEPLOYMENT.md afsnit "Migrering fra gammelt system"

## Hvis noget ikke virker

| Problem | Løsning |
|---------|---------|
| Kort vises ikke | Kontrollér at `[sosp_maps]` er på siden |
| Markørerne mangler | Kontrollér at koordinaterne er helt/korrekte |
| Plugin vises ikke i menuen | Tjek at pluginen er aktiveret |
| Fejl ved gemning | Check WordPress database permissions |

## Brugerspørgsmål

**Q: Kan man migrere fra den gamle system?**
A: Ja! Se DEPLOYMENT.md for SQL kommandoen.

**Q: Er det sikkert?**
A: Ja, kun admin kan ændre data, og alle input bliver tjekket.

**Q: Kan jeg ændre kortets farver?**
A: Ja, se DEPLOYMENT.md under "Tilpasning".

---

**Brug:** Indsæt `[sosp_maps]` shortcode på siden hvor du vil vise kortet.
**Admin:** Gå til "SOSP Maps" menuen i WordPress for at administrere grupper.
