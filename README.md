# Encryptica — IT 107 Final Laboratory Project

A PHP/MySQL secure vault with image CAPTCHA, email OTP verification, tiered
lockout, IP blocking, password recovery, and an admin security dashboard.

## Local setup

1. Put the project in `C:\xampp\htdocs\IT107`.
2. Import the SQL files in `db/sql/` into the `encryptica` database.
3. Database settings are in `db/db.php` (local XAMPP defaults: `root` / empty).
4. Mail settings are kept outside the repository at
   `C:\xampp\encryptica_storage\mail_config.php`. Use `config.sample.php` as a
   reference and **never commit real passwords or App Passwords**.
5. Open `http://localhost/IT107/log_in_page/html/login.php`.

## Demo accounts

| Role | Username | Password | Email inbox |
| --- | --- | --- | --- |
| Admin | `admin123` | `admin123` | `gonzalesjoecel@gmail.com` |
| User | `karl123` | `Demo1234!` | `karl@gmail.com` |
| User | `rocaberts` | contact instructor | `rocaberte@gmail.com` |

OTP codes are sent to the registered email address. For a demo, sign in as
`admin123` so the code arrives in the Gmail inbox configured in the mail file.

## Health check

While signed in as an administrator, open `check.php` in the `html` folder.
