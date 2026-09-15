-- Enterprise Financial Management System — schema
-- MySQL 8.0+ / MariaDB 10.5+
-- Run: mysql -u root -p efms < database/schema.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------
-- users
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(255) NOT NULL,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          VARCHAR(32)  NOT NULL DEFAULT 'viewer', -- admin | accountant | viewer
    created_at    DATETIME NULL,
    updated_at    DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------
-- accounts (chart of accounts)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS accounts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(20)  NOT NULL UNIQUE,
    name        VARCHAR(255) NOT NULL,
    type        ENUM('asset','liability','equity','revenue','expense') NOT NULL,
    parent_id   INT UNSIGNED NULL,
    description TEXT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NULL,
    updated_at  DATETIME NULL,
    KEY idx_accounts_type (type),
    KEY idx_accounts_parent (parent_id),
    CONSTRAINT fk_accounts_parent FOREIGN KEY (parent_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------
-- journal_entries (general ledger transaction headers)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS journal_entries (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_date  DATE NOT NULL,
    reference   VARCHAR(100) NULL,
    memo        VARCHAR(500) NULL,
    source_type VARCHAR(50)  NULL,   -- e.g. 'invoice', 'manual', 'payroll'
    source_id   INT UNSIGNED NULL,   -- id in the source_type's table
    posted      TINYINT(1) NOT NULL DEFAULT 0,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME NULL,
    updated_at  DATETIME NULL,
    KEY idx_je_date (entry_date),
    KEY idx_je_source (source_type, source_id),
    CONSTRAINT fk_je_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------
-- journal_lines (debit/credit lines — always inserted via
-- JournalEntry::post(), never directly)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS journal_lines (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    journal_entry_id  INT UNSIGNED NOT NULL,
    account_id        INT UNSIGNED NOT NULL,
    debit             DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    credit            DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    memo              VARCHAR(255) NULL,
    KEY idx_jl_entry (journal_entry_id),
    KEY idx_jl_account (account_id),
    CONSTRAINT fk_jl_entry FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    CONSTRAINT fk_jl_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------
-- invoices (accounts receivable)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS invoices (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number  VARCHAR(50)  NOT NULL UNIQUE,
    customer_name   VARCHAR(255) NOT NULL,
    customer_email  VARCHAR(255) NULL,
    issue_date      DATE NOT NULL,
    due_date        DATE NOT NULL,
    status          ENUM('draft','posted','paid','void') NOT NULL DEFAULT 'draft',
    subtotal        DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    tax             DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    total           DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    notes           TEXT NULL,
    created_by      INT UNSIGNED NULL,
    created_at      DATETIME NULL,
    updated_at      DATETIME NULL,
    KEY idx_invoices_status (status),
    KEY idx_invoices_due (due_date),
    CONSTRAINT fk_invoices_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------
-- invoice_items
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS invoice_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id  INT UNSIGNED NOT NULL,
    description VARCHAR(500) NOT NULL,
    quantity    DECIMAL(12,2) NOT NULL DEFAULT 1.00,
    unit_price  DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    amount      DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    KEY idx_items_invoice (invoice_id),
    CONSTRAINT fk_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------
-- Seed data: a starter chart of accounts + an admin user
-- Default login: admin@example.com / password  (CHANGE IMMEDIATELY)
-- -----------------------------------------------------------------
INSERT INTO users (name, email, password_hash, role, created_at, updated_at) VALUES
('System Administrator', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NOW(), NOW());
-- NOTE: the hash above is a PASSWORD_BCRYPT hash of "password" — regenerate with
-- password_hash('your-password', PASSWORD_BCRYPT) before deploying.

INSERT INTO accounts (code, name, type, is_active, created_at, updated_at) VALUES
('1000', 'Cash',                 'asset',     1, NOW(), NOW()),
('1100', 'Accounts Receivable',  'asset',     1, NOW(), NOW()),
('1200', 'Inventory',            'asset',     1, NOW(), NOW()),
('2000', 'Accounts Payable',     'liability', 1, NOW(), NOW()),
('2100', 'Accrued Liabilities',  'liability', 1, NOW(), NOW()),
('3000', 'Owner''s Equity',      'equity',    1, NOW(), NOW()),
('4000', 'Sales Revenue',        'revenue',   1, NOW(), NOW()),
('4100', 'Service Revenue',      'revenue',   1, NOW(), NOW()),
('5000', 'Cost of Goods Sold',   'expense',   1, NOW(), NOW()),
('5100', 'Salaries & Wages',     'expense',   1, NOW(), NOW()),
('5200', 'Rent Expense',         'expense',   1, NOW(), NOW()),
('5300', 'Office Supplies',      'expense',   1, NOW(), NOW());
