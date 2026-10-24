# NRB National ID Registration System

CIS-DMS-421 Database Management Systems - Individual Assignment
Student: Fraswell Chiyenda (MSE/22/SS/009)

PHP 8.1+ / PDO / MySQL 8.0.16+ or MariaDB 10.5+. Fictional data only.

## Structure
```
database/nrb.sql        schema, constraints, sample data (importable into a fresh server)
database/queries.sql    SELECT/JOIN/UPDATE/DELETE demonstration queries
includes/               db connection (PDO), helpers/validation, header, footer
public/                 web root: index, register, applicants, applicant_view/edit/delete, cards, photo, assets/
docs/TESTING.md         test checklist with expected results
.env.example            environment variable names (copy to .env)
```

## Installation
1. Install XAMPP/WAMP (or PHP 8.1+ and MySQL/MariaDB) and start MySQL.
2. Import the database (this drops and recreates `nrb_db`):
   `mysql -u root -p < database/nrb.sql`   (or use phpMyAdmin > Import)
3. Copy `.env.example` to `.env` and set `DB_USER` / `DB_PASS`. Never commit `.env`.
4. Run: `php -S localhost:8000 -t public` and open http://localhost:8000
   (with XAMPP you can instead place the folder in `htdocs` and open `/nrb-system/public/`).

## Features
- Register citizens / resident foreigners (+ passport, registration, optional photo) in one transaction
- List and search applicants (name, person ID, citizen ID, passport no., card no., district) using JOINs
- View, edit and controlled delete (blocked once an ID card has been issued)
- Issue ID cards for approved registrations and change card status
- Security: PDO prepared statements, server-side validation, CSRF tokens, output escaping with htmlspecialchars,
  friendly messages for duplicate/constraint errors

## Mapping to the logical model
Tables follow the submitted logical model (PERSON, CITIZEN, RESIDENT_FOREIGNER, PASSPORT, REGISTRATION OFFICE
-> `registration_office`, OFFICER, ID_CARD, DOCUMENT, WITNESS, DOCUMENT_WITNESS, BIOMETRIC) in snake_case.
`registration_center` is added because centerNo is referenced but the table was not defined.

## Git submission
```
git init && git add . && git commit -m "NRB system"
git remote add origin <your-repo-url> && git push -u origin main
```
Add `Mwamvani` as collaborator. Email credentials privately to mkaunde2@gmail.com (deadline 28 Oct 2026).
