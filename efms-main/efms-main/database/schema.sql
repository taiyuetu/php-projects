-- Enterprise Financial Management System — schema
-- SQLite 3
-- Run: php database/setup.php

PRAGMA foreign_keys = OFF;

-- -----------------------------------------------------------------
-- users
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT NOT NULL,
    email         TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role          TEXT NOT NULL DEFAULT 'viewer' CHECK (role IN ('admin', 'accountant', 'viewer')),
    created_at    TEXT NULL,
    updated_at    TEXT NULL
);

-- -----------------------------------------------------------------
-- accounts (chart of accounts)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS accounts (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    code        TEXT NOT NULL UNIQUE,
    name        TEXT NOT NULL,
    type        TEXT NOT NULL CHECK (type IN ('asset','liability','equity','revenue','expense')),
    parent_id   INTEGER NULL,
    description TEXT NULL,
    is_active   INTEGER NOT NULL DEFAULT 1,
    created_at  TEXT NULL,
    updated_at  TEXT NULL,
    FOREIGN KEY (parent_id) REFERENCES accounts(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_accounts_type ON accounts(type);
CREATE INDEX IF NOT EXISTS idx_accounts_parent ON accounts(parent_id);

-- -----------------------------------------------------------------
-- journal_entries (general ledger transaction headers)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS journal_entries (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    entry_date  TEXT NOT NULL,
    reference   TEXT NULL,
    memo        TEXT NULL,
    source_type TEXT NULL,
    source_id   INTEGER NULL,
    posted      INTEGER NOT NULL DEFAULT 0,
    created_by  INTEGER NULL,
    created_at  TEXT NULL,
    updated_at  TEXT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_je_date ON journal_entries(entry_date);
CREATE INDEX IF NOT EXISTS idx_je_source ON journal_entries(source_type, source_id);

-- -----------------------------------------------------------------
-- journal_lines (debit/credit lines — always inserted via
-- JournalEntry::post(), never directly)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS journal_lines (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    journal_entry_id  INTEGER NOT NULL,
    account_id        INTEGER NOT NULL,
    debit             DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    credit            DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    memo              TEXT NULL,
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS idx_jl_entry ON journal_lines(journal_entry_id);
CREATE INDEX IF NOT EXISTS idx_jl_account ON journal_lines(account_id);

-- -----------------------------------------------------------------
-- invoices (accounts receivable)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS invoices (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_number  TEXT NOT NULL UNIQUE,
    customer_name   TEXT NOT NULL,
    customer_email  TEXT NULL,
    issue_date      TEXT NOT NULL,
    due_date        TEXT NOT NULL,
    status          TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','posted','paid','void')),
    subtotal        DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    tax             DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    total           DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    notes           TEXT NULL,
    created_by      INTEGER NULL,
    created_at      TEXT NULL,
    updated_at      TEXT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_invoices_status ON invoices(status);
CREATE INDEX IF NOT EXISTS idx_invoices_due ON invoices(due_date);

-- -----------------------------------------------------------------
-- invoice_items
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS invoice_items (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_id  INTEGER NOT NULL,
    description TEXT NOT NULL,
    quantity    DECIMAL(12,2) NOT NULL DEFAULT 1.00,
    unit_price  DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    amount      DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_items_invoice ON invoice_items(invoice_id);

PRAGMA foreign_keys = ON;

-- -----------------------------------------------------------------
-- Seed data: a starter chart of accounts + an admin user
-- Default login: admin@example.com / password  (CHANGE IMMEDIATELY)
-- -----------------------------------------------------------------
INSERT OR IGNORE INTO users (name, email, password_hash, role, created_at, updated_at) VALUES
('System Administrator', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', datetime('now'), datetime('now'));

INSERT OR IGNORE INTO accounts (code, name, type, is_active, created_at, updated_at) VALUES
('1000', 'Cash',                 'asset',     1, datetime('now'), datetime('now')),
('1100', 'Accounts Receivable',  'asset',     1, datetime('now'), datetime('now')),
('1200', 'Inventory',            'asset',     1, datetime('now'), datetime('now')),
('2000', 'Accounts Payable',     'liability', 1, datetime('now'), datetime('now')),
('2100', 'Accrued Liabilities',  'liability', 1, datetime('now'), datetime('now')),
('3000', 'Owner''s Equity',      'equity',    1, datetime('now'), datetime('now')),
('4000', 'Sales Revenue',        'revenue',   1, datetime('now'), datetime('now')),
('4100', 'Service Revenue',      'revenue',   1, datetime('now'), datetime('now')),
('5000', 'Cost of Goods Sold',   'expense',   1, datetime('now'), datetime('now')),
('5100', 'Salaries & Wages',     'expense',   1, datetime('now'), datetime('now')),
('5200', 'Rent Expense',         'expense',   1, datetime('now'), datetime('now')),
('5300', 'Office Supplies',      'expense',   1, datetime('now'), datetime('now'));
