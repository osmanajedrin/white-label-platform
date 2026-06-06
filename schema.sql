-- White Label Platform — PostgreSQL schema
-- Run against an empty database:  psql -U postgres -d white_label -f schema.sql
-- Multi-tenant: every tenant-owned table carries an indexed tenant_id.

BEGIN;

-- ========================= Tenancy & Identity =========================

-- Master admins: platform owners that sit ABOVE all tenants (no tenant_id).
CREATE TABLE admins (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    email         text NOT NULL UNIQUE,
    password_hash text NOT NULL,
    full_name     text,
    created_at    timestamptz NOT NULL DEFAULT now(),
    deleted_at    timestamptz
);

CREATE TABLE tenants (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name        text NOT NULL,
    slug        text NOT NULL UNIQUE,              -- subdomain, e.g. acme
    is_active   boolean NOT NULL DEFAULT true,     -- master admin can disable a tenant
    created_at  timestamptz NOT NULL DEFAULT now(),
    deleted_at  timestamptz
);

-- Tenant admins: users that manage a single tenant.
CREATE TABLE users (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id     uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    email         text NOT NULL,
    password_hash text NOT NULL,
    created_at    timestamptz NOT NULL DEFAULT now(),
    deleted_at    timestamptz,
    UNIQUE (tenant_id, email)
);
CREATE INDEX idx_users_tenant ON users(tenant_id);

CREATE TABLE user_profiles (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id     uuid NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    full_name   text,
    phone       text,
    avatar_url  text
);

CREATE TABLE roles (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   uuid REFERENCES tenants(id) ON DELETE CASCADE,  -- NULL = system role
    name        text NOT NULL
);
CREATE INDEX idx_roles_tenant ON roles(tenant_id);

CREATE TABLE user_roles (
    user_id     uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role_id     uuid NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, role_id)
);

CREATE TABLE sessions (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id     uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token       text NOT NULL UNIQUE,
    ip_address  text,
    user_agent  text,
    expires_at  timestamptz NOT NULL,
    created_at  timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX idx_sessions_user ON sessions(user_id);

-- ========================= Product Catalog (global) =========================

CREATE TABLE product_categories (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name        text NOT NULL,
    slug        text NOT NULL UNIQUE
);

CREATE TABLE products (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    category_id uuid REFERENCES product_categories(id) ON DELETE SET NULL,
    name        text NOT NULL,
    status      text NOT NULL DEFAULT 'draft',     -- draft|active|archived
    created_at  timestamptz NOT NULL DEFAULT now(),
    deleted_at  timestamptz
);
CREATE INDEX idx_products_category ON products(category_id);

-- which tenant offers which product (white-label catalog)
CREATE TABLE tenant_products (
    id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id      uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    product_id     uuid NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    enabled        boolean NOT NULL DEFAULT true,
    label_override text,
    price_override numeric(12,2),
    UNIQUE (tenant_id, product_id)
);
CREATE INDEX idx_tenant_products_tenant ON tenant_products(tenant_id);

CREATE TABLE product_details (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id  uuid NOT NULL UNIQUE REFERENCES products(id) ON DELETE CASCADE,
    summary     text,
    body        text
);

CREATE TABLE product_plans (
    id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id     uuid NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    name           text NOT NULL,                  -- tier name
    billing_period text NOT NULL DEFAULT 'monthly' -- monthly|yearly|once
);
CREATE INDEX idx_product_plans_product ON product_plans(product_id);

CREATE TABLE product_costs (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    plan_id     uuid NOT NULL REFERENCES product_plans(id) ON DELETE CASCADE,
    amount      numeric(12,2) NOT NULL,
    currency    text NOT NULL DEFAULT 'USD'
);
CREATE INDEX idx_product_costs_plan ON product_costs(plan_id);

CREATE TABLE product_how_it_works (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id  uuid NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    step_order  int NOT NULL DEFAULT 1,
    title       text NOT NULL,
    description text
);
CREATE INDEX idx_how_it_works_product ON product_how_it_works(product_id);

CREATE TABLE product_beneficiary (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id  uuid NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    benefit     text NOT NULL
);
CREATE INDEX idx_beneficiary_product ON product_beneficiary(product_id);

CREATE TABLE product_why (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id  uuid NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    reason      text NOT NULL
);
CREATE INDEX idx_why_product ON product_why(product_id);

CREATE TABLE product_faqs (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id  uuid NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    question    text NOT NULL,
    answer      text
);
CREATE INDEX idx_faqs_product ON product_faqs(product_id);

-- ========================= Patients & Commerce =========================

CREATE TABLE patients (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    email       text NOT NULL,
    full_name   text,
    created_at  timestamptz NOT NULL DEFAULT now(),
    UNIQUE (tenant_id, email)
);
CREATE INDEX idx_patients_tenant ON patients(tenant_id);

CREATE TABLE product_reviews (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id  uuid NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    patient_id  uuid REFERENCES patients(id) ON DELETE SET NULL,
    rating      int NOT NULL CHECK (rating BETWEEN 1 AND 5),
    body        text,
    created_at  timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX idx_reviews_product ON product_reviews(product_id);

CREATE TABLE patient_purchases (
    id           uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id    uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    patient_id   uuid NOT NULL REFERENCES patients(id) ON DELETE CASCADE,
    plan_id      uuid NOT NULL REFERENCES product_plans(id) ON DELETE RESTRICT,
    status       text NOT NULL DEFAULT 'pending',  -- pending|active|cancelled
    purchased_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX idx_purchases_tenant ON patient_purchases(tenant_id);
CREATE INDEX idx_purchases_patient ON patient_purchases(patient_id);

CREATE TABLE invoices (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    purchase_id uuid NOT NULL REFERENCES patient_purchases(id) ON DELETE CASCADE,
    number      text NOT NULL,
    total       numeric(12,2) NOT NULL,
    status      text NOT NULL DEFAULT 'draft',     -- draft|sent|paid|void
    issued_at   timestamptz NOT NULL DEFAULT now(),
    UNIQUE (tenant_id, number)
);
CREATE INDEX idx_invoices_tenant ON invoices(tenant_id);

CREATE TABLE payments (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    invoice_id  uuid NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    amount      numeric(12,2) NOT NULL,
    method      text,
    status      text NOT NULL DEFAULT 'succeeded', -- succeeded|failed|refunded
    paid_at     timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX idx_payments_invoice ON payments(invoice_id);

-- ========================= Operational =========================

CREATE TABLE audit_logs (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    user_id     uuid REFERENCES users(id) ON DELETE SET NULL,
    action      text NOT NULL,
    entity      text,
    entity_id   uuid,
    changes     jsonb,
    created_at  timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX idx_audit_tenant ON audit_logs(tenant_id);

CREATE TABLE notifications (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    user_id     uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type        text,
    message     text NOT NULL,
    read        boolean NOT NULL DEFAULT false,
    created_at  timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX idx_notifications_user ON notifications(user_id);

COMMIT;
