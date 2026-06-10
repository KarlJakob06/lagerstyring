# 🔄 Automatisk oppdatering fra GitHub (Uniweb webhotell)

Tre alternativer, fra enklest til mest avansert. Felles for alle:
**`config.local.php` og `uploads/` røres aldri** — databasepassord og
opplastede bilder overlever hver oppdatering.

> ⚠️ **Forutsetning:** Databasedetaljene ligger nå i `config.local.php`
> (ikke i git). Opprett denne på serveren før første automatiske
> oppdatering — se `config.local.example.php`.

---

## Alternativ A — Cron-jobb med PHP-skript (anbefalt på webhotell)

Krever **ingen** git eller SSH på serveren — kun PHP med curl og zip
(standard hos Uniweb).

1. Last opp `deploy/update_from_github.php` til en mappe **utenfor**
   `public_html`, f.eks. `/home/DITT_BRUKERNAVN/deploy/`.
2. Åpne filen og juster `$WEB_DIR` til stien der appen ligger, f.eks.:
   ```php
   $WEB_DIR = '/home/DITT_BRUKERNAVN/public_html/lagerstyring';
   ```
3. I Uniweb-kontrollpanelet: gå til **Cron-jobber** (under "Avansert"
   eller tilsvarende) og opprett en ny jobb:

   | Felt     | Verdi |
   |----------|-------|
   | Kommando | `php /home/DITT_BRUKERNAVN/deploy/update_from_github.php >> /home/DITT_BRUKERNAVN/deploy/deploy.log 2>&1` |
   | Intervall| Hvert 15. minutt: `*/15 * * * *` |

4. Ferdig! Hver gang du pusher til `main` på GitHub, oppdateres siden
   automatisk innen 15 minutter. Skriptet sjekker først om det finnes
   en ny commit (via GitHub-API), så det bruker nesten ingen ressurser
   når ingenting er endret.

**Tips:** Sjekk `deploy.log` hvis noe ikke fungerer.

### Cron-syntaks forklart

```
*/15 * * * *
│    │ │ │ └─ ukedag (0–7, 0/7 = søndag)
│    │ │ └─── måned (1–12)
│    │ └───── dag i måneden (1–31)
│    └─────── time (0–23)
└──────────── minutt — */15 = hvert 15. minutt
```

Eksempler: `0 * * * *` = hver hele time, `0 3 * * *` = hver natt kl. 03:00.

---

## Alternativ B — Cron-jobb med git (krever SSH-tilgang)

Hvis abonnementet ditt har SSH og git installert:

```bash
# Én gang, via SSH:
git clone https://github.com/KarlJakob06/lagerstyring.git ~/lagerstyring-repo
chmod +x ~/lagerstyring-repo/deploy/deploy.sh
```

Cron-jobb i kontrollpanelet:
```
*/15 * * * * /bin/sh $HOME/lagerstyring-repo/deploy/deploy.sh >> $HOME/deploy.log 2>&1
```

Skriptet henter siste endringer med `git fetch` og synkroniserer til
`public_html/lagerstyring/` med rsync. Juster stiene øverst i
`deploy/deploy.sh` hvis du bruker andre mapper.

---

## Alternativ C — GitHub Actions med FTP (push-basert, ingen cron)

Oppdaterer siden **umiddelbart** ved hver push, i stedet for å vente på
neste cron-kjøring. Krever at du legger FTP-detaljene dine inn som
"secrets" i GitHub.

1. På GitHub: **Settings → Secrets and variables → Actions** og opprett:
   - `FTP_SERVER` (f.eks. `ftp.ditt-domene.no`)
   - `FTP_USERNAME`
   - `FTP_PASSWORD`
2. Opprett filen `.github/workflows/deploy.yml` i repoet:

```yaml
name: Deploy til Uniweb

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Last opp via FTP
        uses: SamKirkland/FTP-Deploy-Action@v4.3.5
        with:
          server: ${{ secrets.FTP_SERVER }}
          username: ${{ secrets.FTP_USERNAME }}
          password: ${{ secrets.FTP_PASSWORD }}
          local-dir: lagerstyring/
          server-dir: public_html/lagerstyring/
          exclude: |
            config.local.php
            uploads/**
```

---

## Viktig om sikkerhet

- **Bytt databasepassordet** i Uniweb-kontrollpanelet — det gamle lå i
  git-historikken og må regnes som lekket. Legg det nye kun i
  `config.local.php` på serveren.
- Ikke legg `update_from_github.php` i `public_html` uten å sette en
  lang, tilfeldig `$DEPLOY_SECRET` i filen.
- `setup.php` (hvis den finnes på serveren) skal slettes etter bruk.
