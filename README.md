# White Label Platform

A multi-tenant white-label healthcare platform — **plain PHP + PostgreSQL**, no framework.

Three levels of access:
- **Master Admins** — platform owners, above all tenants. Manage (create / edit / enable / disable) every tenant. Log in with the **Tenant field left blank**.
- **Tenant Admins** — users who manage a single tenant (products, patients, other admins).
- **Patients** — the end customers belonging to a tenant.

## What's here

```
.
├── schema.sql          # PostgreSQL schema (24 tables) — run this to create the DB
├── ER_DIAGRAM.md       # Mermaid ER diagram (full columns)
├── er_diagram.png      # Rendered ER diagram
├── composer.json       # Project metadata / autoload
├── .env.example        # Copy to .env and set your DB credentials
├── public/index.php    # Demo page: connects to the DB and lists the tables
└── src/db.php          # Tiny .env loader + PDO PostgreSQL connection
```

## Requirements

- PHP 8.2+ with `pdo_pgsql` extension
- PostgreSQL 14+
- (optional) Composer

## Setup

1. **Create the database and tables**
   ```bash
   createdb -U postgres white_label
   psql -U postgres -d white_label -f schema.sql
   ```

2. **Configure credentials**
   ```bash
   cp .env.example .env
   # edit .env if your Postgres user/password differs
   ```

3. **Seed demo data** (a master admin, a tenant + admin, products, patients)
   ```bash
   php seed.php
   ```

4. **Run the app**
   ```bash
   php -S localhost:8000 -t public
   ```
   Open http://localhost:8000 and sign in as either:

   **Master admin** (leave Tenant blank):

   | Field    | Value                  |
   |----------|------------------------|
   | Tenant   | *(blank)*              |
   | Email    | `master@platform.test` |
   | Password | `master123`            |

   **Tenant admin:**

   | Field    | Value              |
   |----------|--------------------|
   | Tenant   | `acme`             |
   | Email    | `admin@acme.test`  |
   | Password | `password123`      |

   The master admin lands on the tenant-management portal (`/admin`); the tenant admin lands
   on a dashboard with stats, products, tenant admins, and patients.

## Open in DBeaver

New connection → PostgreSQL:

| Field    | Value         |
|----------|---------------|
| Host     | `localhost`   |
| Port     | `5432` (see note) |
| Database | `white_label` |
| Username | `postgres`    |
| Password | `postgres`    |

> **Note:** On a fresh machine PostgreSQL listens on the default port `5432`.
> On Edrin's dev machine it runs on **`5433`** (port `5432` is used by another
> project), so the local `.env` uses `5433`. Adjust to match your own setup.

## Schema overview

- **Platform:** admins (master admins)
- **Tenancy/identity:** tenants, tenant_products, users (tenant admins), user_profiles, roles, user_roles, sessions
- **Product catalog (global):** products, product_categories, product_details, product_plans,
  product_costs, product_how_it_works, product_beneficiary, product_why, product_reviews, product_faqs
- **Patients & commerce:** patients, patient_purchases, invoices, payments
- **Operational:** audit_logs, notifications

`products` is a global catalog; `tenant_products` controls which products each tenant offers.
Every tenant-owned table carries an indexed `tenant_id`.
