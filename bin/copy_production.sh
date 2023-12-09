#!/bin/zsh

# Better to store password securely outside the script
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=D3lph1

DUMP_FILE=/Volumes/KC3000-2TB/Backups/FamLedger/fam-ledger-prod.sql

# Export database
mysqldump --host=$DB_HOST --user=$DB_USER --password=$DB_PASSWORD --extended-insert --set-gtid-purged=OFF --result-file=$DUMP_FILE fam-ledger-prod

# Import database
mysql --host=$DB_HOST --user=$DB_USER --password=$DB_PASSWORD -e "source $DUMP_FILE;" fam-ledger-dev

PRODUCTION_ROOT="/Volumes/KC3000-2TB/DataStorage/FamLedger"
DEVELOPMENT_ROOT="/Users/jorgo/FamLedger"

# Remove existing files in DEVELOPMENT_ROOT
rm -rf "${DEVELOPMENT_ROOT:?}"/*

# Copy files from PRODUCTION_ROOT to DEVELOPMENT_ROOT
rsync -av --delete "$PRODUCTION_ROOT/" "$DEVELOPMENT_ROOT/"

bin/console doctrine:migrations:migrate --no-interaction