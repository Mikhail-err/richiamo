# Richiamo Coffee — Setup Guide

## Requirements
- XAMPP (Apache + MySQL + PHP 8.1+)
- Browser (Chrome / Firefox)

---

## 1. Copy files to XAMPP

Place the entire `richiamo/` folder inside:
```
C:\xampp\htdocs\richiamo\        (Windows)
/Applications/XAMPP/htdocs/richiamo/  (Mac)
```

---

## 2. Import the database

1. Start XAMPP → start **Apache** and **MySQL**
2. Open **phpMyAdmin** → `http://localhost/phpmyadmin`
3. Click **Import** → choose `database.sql` → click **Go**

---

## 3. Configure credentials

Open `config.php` and update:
```php
define('DB_USER', 'root');   // your MySQL username
define('DB_PASS', '');       // your MySQL password
define('APP_URL', 'http://localhost/richiamo');
```

---

## 4. Access the app

| URL | Role |
|-----|------|
| `http://localhost/richiamo/auth/login.php` | All users |
| `http://localhost/richiamo/customer/menu.php` | Customer |
| `http://localhost/richiamo/admin/dashboard.php` | Admin |
| `http://localhost/richiamo/developer/logs.php` | Developer |

---

## 5. Default login credentials

> **Change these immediately after first login!**

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@richiamo.my | password |
| Developer | dev@richiamo.my | password |
| Customer | ali@example.com | password |

---

## Token security — how it works

Every protected page includes `auth/auth_check.php` at the top:

```php
$allowed = [ROLE_ADMIN];   // optional: restrict to role(s)
require_once __DIR__ . '/../auth/auth_check.php';
// $current_user is now available
```

- Tokens are generated with `random_bytes(32)` and stored **SHA-256 hashed** in the DB
- Raw token lives only in the PHP session cookie (HttpOnly, SameSite=Strict)
- Tokens expire after 8 hours and are revoked on logout
- Direct URL access without a valid session → redirect to login
- Wrong role → 403 page

---

## File structure

```
richiamo/
├── config.php              ← DB + app settings
├── database.sql            ← Run once to set up DB
├── index.php               ← Public landing page
├── auth/
│   ├── auth_check.php      ← Include on every protected page
│   ├── login.php
│   ├── logout.php
│   ├── register.php
│   └── 403.php
├── customer/
│   ├── menu.php
│   ├── order.php
│   └── track.php
├── admin/
│   ├── dashboard.php
│   ├── menu.php
│   └── orders.php
├── developer/
│   ├── logs.php
│   └── config.php
├── includes/
│   ├── db.php              ← PDO connection
│   └── functions.php       ← Helpers: auth, token, CSRF, price
└── assets/
    ├── css/style.css
    ├── js/app.js
    └── img/
```
