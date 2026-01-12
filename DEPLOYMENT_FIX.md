# 🚨 SERVER DEPLOYMENT FIX - URGENT

## Problem:
```
Target class [App\Console\Commands\CheckInactiveUsersCommand] does not exist.
```

Server cannot run ANY artisan command because Kernel.php references deleted commands.

---

## 🔥 IMMEDIATE FIX (On Production Server):

### Option 1: Quick Manual Fix (FASTEST)
```bash
# SSH into production
ssh root@your-server

# Navigate to project
cd /var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com

# Edit Kernel.php and remove command references
nano app/Console/Kernel.php

# Find and REMOVE these lines if they exist:
# - Any line with CheckInactiveUsersCommand
# - Any line with ForceLogoutUser
# - Any line with UnlockUserLogin
# - Any line with AutoLogoutInactiveUsers
# - Any scheduled command: users:check-inactive

# Save and exit (Ctrl+X, Y, Enter)

# Clear Laravel cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

---

### Option 2: Git Deployment (RECOMMENDED)
```bash
# On local machine - commit and push latest code
git add .
git commit -m "Remove auto-logout system completely"
git push origin main

# On production server
cd /var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com
git pull origin main

# Rollback migration to remove columns
php artisan migrate:rollback --step=1

# Clear all cache
php artisan optimize:clear
```

---

## 📋 Files That Need to Be on Server:

### ✅ Updated Files (already done locally):
- `app/Console/Kernel.php` - Command registration removed
- `app/Http/Controllers/Auth/SocialiteController.php` - Column references removed
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php` - Column references removed
- `app/Http/Requests/Auth/LoginRequest.php` - Google login check removed

### ❌ Deleted Files (need to be removed from server):
- `app/Jobs/AutoLogoutInactiveUsers.php`
- `app/Http/Middleware/CheckUserActivity.php`
- `app/Console/Commands/CheckInactiveUsersCommand.php`
- `app/Console/Commands/ForceLogoutUser.php`
- `app/Console/Commands/UnlockUserLogin.php`
- `database/migrations/2026_01_11_065511_add_activity_tracking_to_users_table.php`

---

## 🔄 Migration Rollback (Remove Database Columns):

```bash
# This will remove the columns:
# - last_activity_at
# - require_google_login
# - auto_logged_out_at

php artisan migrate:rollback --step=1
```

---

## ✅ Verification:

```bash
# Test if commands work now
php artisan list

# Check if login works
# Visit: https://inventory.5coremanagement.com/auth/login
```

---

## 🎯 Quick Summary:

**The error happens because:**
- Server has old `Kernel.php` that registers deleted commands
- Laravel tries to load them on EVERY artisan command

**The fix:**
1. Pull latest code from git (recommended)
   OR
2. Manually remove command references from Kernel.php
3. Rollback migration
4. Clear cache

---

## 📞 Need Help?

If still getting errors, check:
1. Is `app/Console/Kernel.php` updated on server?
2. Are deleted command files removed from server?
3. Has migration been rolled back?
4. Has cache been cleared?

Run this diagnostic:
```bash
# Check if deleted files exist
ls -la app/Console/Commands/CheckInactiveUsersCommand.php
ls -la app/Http/Middleware/CheckUserActivity.php

# If they exist, delete them manually:
rm -f app/Console/Commands/CheckInactiveUsersCommand.php
rm -f app/Console/Commands/ForceLogoutUser.php
rm -f app/Console/Commands/UnlockUserLogin.php
rm -f app/Http/Middleware/CheckUserActivity.php
rm -f app/Jobs/AutoLogoutInactiveUsers.php
```

