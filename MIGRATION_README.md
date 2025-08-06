# MySQL to PostgreSQL Migration Guide

This guide helps you migrate your Laravel application from MySQL (XAMPP) to PostgreSQL on Railway.

## 📋 Migration Summary

- **Source Database**: MySQL (db_fail_tongod)
- **Target Database**: PostgreSQL on Railway
- **Tables Migrated**: 4 tables
- **Records Migrated**: 12 records

## 🔧 Files Generated

### Migration Files
- `mysql-to-postgresql-migrator.php` - Complete migration script
- `mysql-export-complete.json` - Full MySQL export
- `postgresql-export.json` - Converted PostgreSQL data
- `postgresql-migration.sql` - Ready-to-run SQL script

### Laravel Integration
- `database/seeders/ProductionDataSeeder.php` - Laravel seeder for production data
- `deploy-to-railway.sh` - Railway deployment script
- Updated `composer.json` - Added PostgreSQL dependency
- Updated `.env` - PostgreSQL configuration

## 🚀 Migration Steps

### 1. Local Testing (Optional)
```bash
# Install PostgreSQL locally for testing
# Update .env with local PostgreSQL settings
php artisan migrate
php artisan db:seed --class=ProductionDataSeeder
```

### 2. Deploy to Railway

#### Step 1: Initialize Railway Project
```bash
railway login
railway init
```

#### Step 2: Add PostgreSQL Database
```bash
railway add postgresql
```

#### Step 3: Deploy Application
```bash
railway deploy
```

#### Step 4: Set Environment Variables
In Railway Dashboard, set these environment variables:
```env
APP_NAME="Sistem Penyimpanan Fail Tongod"
APP_ENV=production
APP_KEY=base64:YWJjZGVmZ2hpamtsbW5vcHFyc3R1dnd4eXowMTIzNDU2Nzg5QUJDREVGR0g=
APP_DEBUG=false
APP_URL=https://your-railway-app.railway.app

DB_CONNECTION=pgsql
# PostgreSQL variables are automatically set by Railway:
# PGHOST, PGPORT, PGDATABASE, PGUSER, PGPASSWORD
```

#### Step 5: Run Migration
```bash
# Connect to your Railway deployment
railway shell

# Run the deployment script
chmod +x deploy-to-railway.sh
./deploy-to-railway.sh
```

## 📊 Migration Details

### Tables Migrated
1. **users** - 4 records
   - Admin and staff accounts
   - Roles: admin, staff_jabatan, staff_pembantu, user_view

2. **locations** - 6 records
   - Physical storage locations
   - Building, floor, room information

3. **files** - 1 record
   - Document records
   - File tracking information

4. **borrowing_records** - 1 record
   - File borrowing history
   - Status tracking

### Key Changes for PostgreSQL

1. **Auto Increment**: `AUTO_INCREMENT` → `SERIAL`/`BIGSERIAL`
2. **ENUM Types**: Converted to `VARCHAR` with constraints
3. **Date/Time**: `DATETIME` → `TIMESTAMP`
4. **Text Fields**: Properly mapped to PostgreSQL text types
5. **Foreign Keys**: Laravel relationships maintained

## 🔍 Verification

After migration, verify your data:

```bash
# Check tables exist
railway connect postgresql
\dt

# Check record counts
SELECT 'users' as table_name, COUNT(*) as count FROM users
UNION ALL
SELECT 'locations', COUNT(*) FROM locations
UNION ALL
SELECT 'files', COUNT(*) FROM files
UNION ALL  
SELECT 'borrowing_records', COUNT(*) FROM borrowing_records;
```

Expected results:
- users: 4 records
- locations: 6 records  
- files: 1 record
- borrowing_records: 1 record

## 🛠 Troubleshooting

### Issue: Migration fails
- Check PostgreSQL connection variables
- Ensure Railway PostgreSQL addon is connected
- Verify export files exist

### Issue: Data seeder fails
- Check foreign key constraints
- Verify data integrity in export files
- Run migrations before seeding

### Issue: Application doesn't start
- Check APP_KEY is set
- Verify all environment variables
- Check storage permissions

## 📁 File Structure

```
sistem-penyimpanan-fail/
├── database/
│   ├── migrations/           # Laravel migrations (already compatible)
│   └── seeders/
│       └── ProductionDataSeeder.php
├── mysql-to-postgresql-migrator.php
├── postgresql-migration.sql
├── postgresql-export.json
├── deploy-to-railway.sh
├── railway.json
├── Dockerfile
└── MIGRATION_README.md
```

## 🎉 Success!

Your Laravel application is now successfully migrated from MySQL to PostgreSQL on Railway!

Access your application at: `https://your-app-name.railway.app`