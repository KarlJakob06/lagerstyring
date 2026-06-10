# 📦 Lagerstyring for Arbeidsbil

En enkel, sikker webapp for å holde oversikt over varelager i arbeidsbilen.

---

## Funksjoner

- ✅ Innlogging med brukernavn og passord (bcrypt-kryptert)
- ✅ Kontolås etter 5 mislykkede innloggingsforsøk (10 min), opplåsing
     og passord-tilbakestilling av admin, «sist innlogget»-oversikt
- ✅ Felles lager + personlig lager per bruker, med tilgangsstyring
- ✅ Legg til, rediger og slett varer (og flytt dem mellom lagre)
- ✅ Felter: Varenavn, Elnummer, Beholdning, Minimum, Enhet, Bilde
- ✅ Automatisk enhetsdeteksjon: kabel/ledning/rør måles i meter,
     resten i stk (kan overstyres manuelt)
- ✅ Advarsel ved lav beholdning (markeres tydelig)
- ✅ Automatisk henting av produktbilde fra EFObasen via elnummer
     (felleskilden grossistene Ahlsell, Onninen, Solar og Sonepar bruker)
- ✅ Hurtigoppdatering av antall med +/– knapper
- ✅ Søk på varenavn og elnummer
- ✅ Brukerstyring for admin (legg til / slett brukere)
- ✅ Mobilevennlig design
- ✅ CSRF-beskyttelse og sikre SQL-spørringer

---

## Installasjon hos Uniweb

### Steg 1 — Opprett database

1. Logg inn på Uniweb-kontrollpanelet
2. Gå til **MySQL-databaser** og opprett en ny database
3. Noter ned: databasenavn, brukernavn og passord

### Steg 2 — Konfigurer appen

Kopier `config.local.example.php` til `config.local.php` **på serveren**
og fyll inn dine Uniweb-databasedetaljer:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'DIN_DATABASE_NAVN');
define('DB_USER', 'DIN_DATABASE_BRUKER');
define('DB_PASS', 'DITT_DATABASE_PASSORD');
```

> `config.local.php` ligger ikke i git. Dermed kan koden oppdateres
> automatisk fra GitHub uten at passordet overskrives — og passordet
> havner aldri i repoet.

### Steg 3 — Last opp filer

Last opp **alle filene** (inkl. `uploads/`-mappen) til webhotellet via FTP (f.eks. FileZilla).

Anbefalt mappestruktur:
```
/public_html/lagerstyring/
    config.php
    setup.php
    login.php
    index.php
    ... (alle .php-filer)
    includes/
    uploads/
```

### Steg 4 — Gjør uploads/ skrivbar

Sett skrivetillatelse (`chmod 755`) på `uploads/`-mappen via FTP-klienten din (høyreklikk → Filrettigheter → 755).

### Steg 5 — Kjør oppsettveiviseren

Åpne nettleseren og gå til:
```
https://ditt-domene.no/lagerstyring/setup.php
```

1. Fyll inn ønsket brukernavn og passord for admin-brukeren
2. Klikk **"Opprett administrator og sett opp database"**
3. Tabellene opprettes automatisk, og du er klar til å logge inn

### ⚠️ Viktig etter oppsett!

**Slett eller gi nytt navn til `setup.php` umiddelbart etter bruk!**
Denne filen gir ubeskyttet tilgang til å opprette nye administratorer.

```bash
# Via FTP: høyreklikk setup.php → Slett
# Eller gi nytt navn til: setup.php.bak
```

### Steg 6 — Logg inn

Gå til:
```
https://ditt-domene.no/lagerstyring/login.php
```

---

## Legge til flere brukere

Logg inn som administrator og gå til **Brukere** i menyen. Her kan du:
- Opprette nye brukere
- Gi / fjerne admin-tilgang
- Slette brukere

---

## Filstruktur

```
lagerstyring/
├── config.php                 ← Applikasjonskonfig (i git — ikke rediger på server)
├── config.local.example.php   ← Mal for databasedetaljer
├── config.local.php           ← Dine databasedetaljer (KUN på server, ikke i git)
├── auth.php                   ← Autentiseringshjelper (ikke rediger)
├── setup.php                  ← SLETT ETTER BRUK
├── login.php                  ← Innloggingsside
├── logout.php                 ← Utlogging
├── index.php                  ← Lageroversikt (startside)
├── add_item.php               ← Legg til vare
├── edit_item.php              ← Rediger vare
├── delete_item.php            ← Slett vare
├── ajax_quantity.php          ← AJAX antallsoppdatering
├── users.php                  ← Brukerstyring (kun admin)
├── change_password.php        ← Bytt passord
├── assets/
│   └── style.css              ← Felles CSS (caches av nettleseren)
├── includes/
│   ├── bootstrap.php          ← Felles oppstart (config + auth + innlogging)
│   ├── header.php             ← Felles topp
│   └── footer.php             ← Felles bunn
└── uploads/
    └── .htaccess              ← Sikkerhetsregler for opplastinger
```

---

## 🔄 Automatisk oppdatering fra GitHub

Se [`deploy/DEPLOY.md`](../deploy/DEPLOY.md) for tre alternativer:

1. **Cron-jobb + PHP-skript** (`deploy/update_from_github.php`) — krever
   ikke git/SSH på webhotellet. Anbefalt hos Uniweb.
2. **Cron-jobb + git** (`deploy/deploy.sh`) — hvis abonnementet har SSH.
3. **GitHub Actions + FTP** — push-basert, oppdaterer umiddelbart.

---

## Teknisk

- **Backend**: PHP 7.4+ med PDO/MySQL
- **Autentisering**: PHP-sesjoner, bcrypt-passord, CSRF-tokens
- **Database**: MySQL/MariaDB
- **Frontend**: Ren HTML/CSS/JavaScript — ingen eksterne rammeverk
- **Mobiloptimalisert**: Responsivt design

---

## Feilsøking

**"Databasefeil"** — Sjekk at DB_HOST, DB_NAME, DB_USER og DB_PASS i `config.php` er riktige.

**"Kunne ikke lagre bildet"** — Sjekk at `uploads/`-mappen har skrivetillatelse (chmod 755).

**Siden vises ikke** — Sjekk at alle filene er lastet opp og at `includes/`-mappen finnes.
