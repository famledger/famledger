#!/bin/bash

# Database credentials
DB_HOST="localhost"
DB_USER="root"
DB_PASSWORD="D3lph1"
DB_NAME="fam-ledger-prod"

# Base backup directory
BASE_DUMP_FOLDER="/Users/jorgo/Library/CloudStorage/GoogleDrive-jmiridis@gmail.com/My Drive/Apps/Backup"

# Backup directories for daily, weekly, and monthly backups
DAILY_DUMP_FOLDER="${BASE_DUMP_FOLDER}/daily"
WEEKLY_DUMP_FOLDER="${BASE_DUMP_FOLDER}/weekly"
MONTHLY_DUMP_FOLDER="${BASE_DUMP_FOLDER}/monthly"

# Ensure backup directories exist
mkdir -p "${DAILY_DUMP_FOLDER}" "${WEEKLY_DUMP_FOLDER}" "${MONTHLY_DUMP_FOLDER}"

# Filename for SQL backup
FILE_NAME="db_backup_$(date +%Y%m%d_%H%M%S).sql"

# Function to perform a backup
perform_backup () {
  local backup_dir=$1
  local dump_file="${backup_dir}/${FILE_NAME}"

  # Dump the database into an SQL file
  mysqldump -h ${DB_HOST} -u ${DB_USER} -p"${DB_PASSWORD}" ${DB_NAME} > "${dump_file}"

  if [ $? -eq 0 ]; then
    echo "$(date): Backup successfully created at ${dump_file}"
  else
    echo "$(date): Backup failed"
    exit 1
  fi
}

# Function to cleanup old backups
cleanup_backups () {
  local backup_dir=$1
  local days_to_keep=$2

  find "${backup_dir}" -name 'db_backup_*.sql' -mtime +${days_to_keep} -type f -delete
}

# Determine if today's backup is daily, weekly, or monthly
DAY_OF_WEEK=$(date +%u) # day of week (1..7); 1 is Monday
DAY_OF_MONTH=$(date +%d) # day of month (1..31)

# Perform daily backups
perform_backup "${DAILY_DUMP_FOLDER}"

# Cleanup daily backups older than 7 days
cleanup_backups "${DAILY_DUMP_FOLDER}" 7

# Perform weekly backup if today is Sunday
if [ "${DAY_OF_WEEK}" -eq 7 ]; then
  perform_backup "${WEEKLY_DUMP_FOLDER}"
  # Cleanup weekly backups older than 5 weeks
  cleanup_backups "${WEEKLY_DUMP_FOLDER}" 35
fi

# Perform monthly backup if today is the 1st of the month
if [ "${DAY_OF_MONTH}" -eq 1 ]; then
  perform_backup "${MONTHLY_DUMP_FOLDER}"
  # Cleanup monthly backups older than 12 months
  cleanup_backups "${MONTHLY_DUMP_FOLDER}" 365
fi

# Log backup completion
echo "$(date): Backup script completed" >> "${BASE_DUMP_FOLDER}/backup_log.txt"
