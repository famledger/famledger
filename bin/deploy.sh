#!/bin/bash

echo "Switching to main branch..."
git checkout main
git pull origin main

echo "Installing Composer dependencies for production..."
# temporarily install development dependencies to work around error:
# > Attempted to load class "FidryAliceDataFixturesBundle" from namespace "Fidry\AliceDataFixtures\Bridge\Symfony".
composer install --optimize-autoloader
# composer install --no-dev --optimize-autoloader

echo "Installing Yarn dependencies for production..."
yarn install

echo "Building assets for production..."
yarn encore production

echo "Clearing Symfony cache for production..."
php bin/console cache:clear

echo "Running database migrations for production..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

echo "Deployment complete!"
