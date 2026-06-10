#!/bin/sh
# ============================================================
# Automatisk oppdatering av lagerstyring fra GitHub.
# Kjøres av en cron-jobb på webhotellet (krever SSH + git).
#
# Oppsett (én gang, via SSH):
#   1. git clone https://github.com/KarlJakob06/lagerstyring.git ~/lagerstyring-repo
#   2. chmod +x ~/lagerstyring-repo/deploy/deploy.sh
#   3. Legg til cron-jobb i kontrollpanelet, f.eks. hvert 15. minutt:
#      */15 * * * * /bin/sh $HOME/lagerstyring-repo/deploy/deploy.sh >> $HOME/deploy.log 2>&1
#
# Skriptet rører ALDRI config.local.php eller uploads/ i webroten,
# så databasepassord og opplastede bilder overlever hver oppdatering.
# ============================================================

REPO_DIR="$HOME/lagerstyring-repo"          # Hvor repoet er klonet
WEB_DIR="$HOME/public_html/lagerstyring"    # Webroten siden serveres fra
BRANCH="main"                               # Grenen som skal deployes

set -e

cd "$REPO_DIR"

# Hent siste endringer fra GitHub
git fetch origin "$BRANCH"

# Hopp over hvis ingenting er nytt (sparer ressurser på webhotellet)
LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse "origin/$BRANCH")
if [ "$LOCAL" = "$REMOTE" ]; then
    exit 0
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Oppdaterer $LOCAL -> $REMOTE"
git reset --hard "origin/$BRANCH"

# Synkroniser app-filene til webroten.
# --delete fjerner filer som er slettet i repoet, men exclude-listen
# beskytter lokal konfig og opplastede bilder.
mkdir -p "$WEB_DIR"
rsync -a --delete \
    --exclude 'config.local.php' \
    --exclude 'uploads/' \
    "$REPO_DIR/lagerstyring/" "$WEB_DIR/"

# Sørg for at uploads/ finnes og er skrivbar
mkdir -p "$WEB_DIR/uploads"
cp -n "$REPO_DIR/lagerstyring/uploads/.htaccess" "$WEB_DIR/uploads/.htaccess" 2>/dev/null || true

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Ferdig."
