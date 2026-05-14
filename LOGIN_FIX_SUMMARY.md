# Login Error Fix - Server Error 500

## Problem
When attempting to log in as admin using:
- **Email**: admin@gmail.com  
- **Password**: password123

The application returned: **"Server error: 500"**

## Root Cause
The `users` table did not exist in the MySQL database `lukes_seafood`. When the login code in `Auth.php` attempted to query the users table with:

```php
$stmt = $db->prepare(
    'SELECT id, name, email, password_hash, provider, role, status FROM users WHERE email = ? AND COALESCE(is_archived,0) = 0'
);
$stmt->execute([$email]);
```

This triggered a PDO exception:
```
SQLSTATE[42S02]: Base table or view not found: 1146 Table 'lukes_seafood.users' doesn't exist
```

This unhandled exception resulted in a 500 Internal Server Error.

## Solution
I created and executed a database initialization script (`setup_database.php`) that:

1. **Created the `lukes_seafood` database** (if not already present)
2. **Created the `users` table** with all required columns:
   - Authentication fields: `email`, `password_hash`, `provider`
   - User metadata: `name`, `role`, `email_verified`
   - OTP fields: `otp_code`, `otp_expires_at`, `otp_attempts`
   - Account status: `status`, `is_archived`, `archived_at`
   - OAuth fields: `provider_id`, `avatar_url`
   - Password reset: `reset_token`, `token_expiry`
   - Timestamps: `created_at`, `updated_at`

3. **Inserted the admin user**:
   - Email: `admin@gmail.com`
   - Password hash (for `password123`): `$2y$12$KBNsBLoOobrK.T8zx9KeNehmWHB4Suij0IhHQ7hX/4hDMswZDl5xu`
   - Role: `admin`
   - Email verified: ✓

## How to Run the Setup

If the database hasn't been initialized yet:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/WEBTOOLSs
php setup_database.php
```

This script is safe to run multiple times—it uses `IF NOT EXISTS` to avoid overwriting existing data.

## Verification

You can now log in with:
- **Email**: admin@gmail.com
- **Password**: password123

The login should bypass OTP and redirect directly to the admin dashboard at `adminSide/admin-dashboard.php`.

## Additional Notes

- The database connection uses the XAMPP MySQL socket: `/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock`
- The admin account bypasses 2FA (OTP verification) by design—see the `isAdminOrStaff` check in `Auth.php`
- All password hashes use bcrypt with cost 12 for security

---

**File created**: `setup_database.php` — Use this for future database initialization if needed.
