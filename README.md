# Søspejder.dk udvikling

## Ænkå - nkj@internetgruppen.dk's område til at lege med WP og de filer, Villads fik lavet for næsten 10 år siden :-)

---

## Sådan retter du kortet

Denne vejledning er til dig, der vil tilføje eller opdatere en søspejdergruppe på kortet — uden at kende til programmering.

### 1. Gå til redigeringssiden

Åbn siden [sosp.internetgruppen.dk/ret-kortet/](https://sosp.internetgruppen.dk/ret-kortet/) i din browser.

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

---

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
