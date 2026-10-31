# Søspejder.dk udvikling

## Ænkå - nkj@internetgruppen.dk's område til at lege med WP og de filer, Villads fik lavet for næsten 10 år siden :-)

---

## Sådan retter du kortet

Denne vejledning er til dig, der vil tilføje eller opdatere en søspejdergruppe på kortet — uden at kende til programmering.

### 1. Gå til redigeringssiden

Åbn siden [sosp.internetgruppen.dk/index.php/ret-kortet/](https://sosp.internetgruppen.dk/index.php/ret-kortet/) i din browser.

### 2. Log ind med din e-mail

Du skal ikke oprette en konto. I stedet fungerer det sådan:

1. Skriv din e-mailadresse i feltet og klik **Send kode**
2. Du modtager en e-mail med en 6-cifret kode (tjek evt. dit spamfilter)
3. Skriv koden ind på siden og klik **Bekræft kode**
4. Du er nu logget ind — login gælder i 24 timer

> Bemærk: Ikke alle e-mailadresser har adgang. Kontakt Ænkå (nkj@internetgruppen.dk), hvis du skal have adgang.

### 3. Udfyld gruppeoplysninger

Når du er logget ind, ser du en formular. Du har to muligheder:

**Mulighed A — hent data automatisk fra gruppens hjemmeside:**
1. Indsæt adressen på gruppens hjemmeside i feltet øverst (f.eks. `https://min-gruppe.dk`)
2. Klik **Hent data** — formularen udfyldes automatisk med det, siden kan finde

**Mulighed B — udfyld manuelt:**
- **Gruppens navn** (påkrævet)
- **Adresse** (påkrævet) — bruges til automatisk at placere gruppen på kortet
- **E-mail** (valgfrit)
- **Hjemmeside URL** (valgfrit)
- Koordinater behøver du ikke udfylde — de beregnes automatisk ud fra adressen

### 4. Gem

Klik **Gem gruppe**. Gruppen vises nu på kortet på forsiden.

> Hvis en gruppe med samme hjemmeside allerede findes, vises en fejlbesked og gruppen gemmes ikke. Tjek om gruppen er oprettet i forvejen.

### 5. Log ud

Klik **Log ud**, når du er færdig.

### Slet en gruppe

Grupper kan ikke slettes direkte. I stedet:

1. Gå til **Gruppeliste** og klik på papirkurven 🗑 ud for gruppen
2. Skriv evt. en begrundelse og din e-mail, og klik **Send anmodning**
3. Administrator får en mail med et link og sletter gruppen, hvis anmodningen godkendes

---

## Kort og adresseopslag

Kortet bruger [OpenStreetMap](https://www.openstreetmap.org) og kræver **ingen API-nøgle**:

- **Kortvisning:** [Leaflet](https://leafletjs.com) med OpenStreetMaps standardkort (ingen nøgle).
  Leaflet og Leaflet.markercluster ligger lokalt i `sosp-maps/assets/lib/`.
- **Adresse → koordinater:** [Nominatim](https://nominatim.org) (OpenStreetMaps geokoder),
  begrænset til Danmark. Bruges kun, når en gruppe gemmes uden koordinater.

Den tidligere linje `define( 'SOSP_MAPS_GOOGLE_API_KEY', ... )` i `wp-config.php`
bruges ikke længere og kan slettes.
