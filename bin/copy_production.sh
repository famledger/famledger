#!/bin/zsh

# Better to store password securely outside the script
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=D3lph1

DUMP_FILE=/Volumes/Backup/FamLedger/fam-ledger-prod.sql

# Export database
time mysqldump --host=$DB_HOST --user=$DB_USER --password=$DB_PASSWORD --extended-insert --set-gtid-purged=OFF --result-file=$DUMP_FILE fam-ledger-prod

# Import database
time mysql --host=$DB_HOST --user=$DB_USER --password=$DB_PASSWORD -e "source $DUMP_FILE;" fam-ledger-dev

PRODUCTION_ROOT="/Users/jorgo/Library/CloudStorage/GoogleDrive-jmiridis@gmail.com/My Drive/Apps/FamLedger"
DEVELOPMENT_ROOT="/Users/jorgo/FamLedger"

# Remove existing files in DEVELOPMENT_ROOT
rm -rf "${DEVELOPMENT_ROOT:?}"/*

# Copy files from PRODUCTION_ROOT to DEVELOPMENT_ROOT
rsync -av --delete "$PRODUCTION_ROOT/" "$DEVELOPMENT_ROOT/"

bin/console doctrine:migrations:migrate --no-interaction