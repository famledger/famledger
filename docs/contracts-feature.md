# Rental Contracts Management Feature

## Overview
This feature adds rental contract management to the FamLedger application, allowing you to track rental agreements and monitor contract expiration dates.

## Features

### Contract Entity
- **Customer**: Link to existing customer
- **Property**: Link to existing property  
- **Start Date**: Contract start date
- **End Date**: Contract end date
- **Signing Date**: When the contract was signed
- **Monthly Amount**: Rental amount per month
- **Notary Name**: Name of the notary who handled the contract
- **Notes**: Additional notes about the contract
- **Active Status**: Whether the contract is currently active

### Dashboard Widget
- Shows the next 5 contracts expiring within 90 days
- Color-coded badges:
  - **Red**: Expires within 30 days
  - **Yellow**: Expires within 60 days  
  - **Blue**: Expires within 90 days

### Admin Interface
- Full CRUD operations for contracts
- Search by customer name, RFC, property caption, or notary name
- Sortable by expiration date
- Shows days until expiration with color coding
- Entity history tracking

## Usage

### Adding a New Contract
1. Go to Admin > Contracts
2. Click "Create Contract"
3. Fill in the required fields:
   - Customer (required)
   - Property (required)
   - Start Date (required)
   - End Date (required)
   - Monthly Amount (required)
4. Optionally add signing date, notary name, and notes
5. Save the contract

### Monitoring Expiring Contracts
- Check the dashboard for the "Expiring Contracts" widget
- Contracts are automatically sorted by expiration date
- Use the color-coded badges to prioritize renewals

### Managing Contract Renewals
1. When a contract is near expiration, create a new contract with updated dates
2. Set the old contract to inactive
3. The new contract will appear in the expiring contracts list

## Database Migration
Run the migration to create the contract table:
```bash
php bin/console doctrine:migrations:migrate
```

## Sample Data
Load sample contract data using fixtures:
```bash
php bin/console hautelook:fixtures:load --append
```