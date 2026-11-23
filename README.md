# Encryptica — IT 107 Final Laboratory Project

A PHP/MySQL secure vault with image CAPTCHA, email OTP verification, tiered
lockout, IP blocking, password recovery, and an admin security dashboard.

## About the system

Encryptica is a web-based secure information vault developed for the IT 107
Final Laboratory Project. It demonstrates how a PHP and MySQL application can
protect user accounts and personal records through layered security controls
rather than relying on a password alone.

The application serves two roles:

- **Regular users** register with a validated profile, select three distinct
  authentication questions, manage their vault profile, and recover access
  through a one-time password sent to their registered email address.
- **Administrators** monitor account activity, security flags, temporary
  lockouts, and blocked IP addresses from a dedicated security dashboard.

Its security controls include an image CAPTCHA on every public form, hashed
passwords and authentication answers, server-side validation, CSRF protection,
a hidden honeypot field, progressive account lockout, an idle timeout for user
sessions, and automatic IP blocking after repeated failed attempts. Validation
rules are shared between the browser and the server so both layers enforce the
same limits.

The interface uses a split-screen authentication layout with light and dark
themes. This repository contains the application source, database schema,
sample data, and setup notes. Live credentials and mail App Passwords are
deliberately kept outside the repository.

## Local setup

1. Put the project in `C:\xampp\htdocs\IT107`.
2. Import the SQL files in `db/sql/` into the `encryptica` database.
3. Database settings are in `db/db.php` (local XAMPP defaults: `root` / empty).
4. Mail settings are kept outside the repository at
   `C:\xampp\encryptica_storage\mail_config.php`. Use `config.sample.php` as a
   reference and **never commit real passwords or App Passwords**.
5. Open `http://localhost/IT107/log_in_page/html/login.php`.

## Health check

While signed in as an administrator, open `check.php` in the `html` folder.
