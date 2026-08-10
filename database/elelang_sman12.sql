CREATE DATABASE IF NOT EXISTS elelang_sman12
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE elelang_sman12;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS learning_results;
DROP TABLE IF EXISTS distributions;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS bank_accounts;
DROP TABLE IF EXISTS bids;
DROP TABLE IF EXISTS auctions;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(40) NOT NULL UNIQUE,
    email VARCHAR(120) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    identity_number VARCHAR(40) NULL,
    gender ENUM('male', 'female') NULL,
    birth_date DATE NULL,
    address TEXT NULL,
    phone VARCHAR(25) NULL,
    role ENUM('student', 'teacher', 'staff', 'admin') NOT NULL DEFAULT 'student',
    status ENUM('pending', 'active', 'rejected', 'suspended') NOT NULL DEFAULT 'pending',
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_users_role_status (role, status)
) ENGINE=InnoDB;

CREATE TABLE items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    brand VARCHAR(80) NULL,
    category VARCHAR(80) NOT NULL,
    item_condition ENUM('new', 'like_new', 'good', 'fair') NOT NULL DEFAULT 'good',
    description TEXT NOT NULL,
    image VARCHAR(255) NULL,
    starting_price DECIMAL(15,2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'auctioned', 'sold') NOT NULL DEFAULT 'pending',
    verification_note VARCHAR(255) NULL,
    verified_by BIGINT UNSIGNED NULL,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_items_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_items_verifier FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_items_status_owner (status, owner_id)
) ENGINE=InnoDB;

CREATE TABLE auctions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id BIGINT UNSIGNED NOT NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    increment_amount DECIMAL(15,2) NOT NULL DEFAULT 10000,
    status ENUM('draft', 'open', 'closed', 'cancelled') NOT NULL DEFAULT 'open',
    winner_id BIGINT UNSIGNED NULL,
    winning_bid DECIMAL(15,2) NULL,
    selection_sort_snapshot JSON NULL,
    closed_by BIGINT UNSIGNED NULL,
    closed_at DATETIME NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_auctions_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE RESTRICT,
    CONSTRAINT fk_auctions_winner FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_auctions_closer FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_auctions_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT chk_auction_period CHECK (end_at > start_at),
    INDEX idx_auctions_status_period (status, start_at, end_at)
) ENGINE=InnoDB;

CREATE TABLE bids (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    auction_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bids_auction FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE CASCADE,
    CONSTRAINT fk_bids_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_bids_auction_amount (auction_id, amount),
    INDEX idx_bids_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE bank_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    bank_name VARCHAR(80) NOT NULL,
    account_number VARCHAR(60) NOT NULL,
    account_holder VARCHAR(120) NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bank_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_bank_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    auction_id BIGINT UNSIGNED NOT NULL UNIQUE,
    payer_id BIGINT UNSIGNED NOT NULL,
    payee_id BIGINT UNSIGNED NOT NULL,
    bank_account_id BIGINT UNSIGNED NULL,
    amount DECIMAL(15,2) NOT NULL,
    proof_image VARCHAR(255) NULL,
    status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    verification_note VARCHAR(255) NULL,
    verified_by BIGINT UNSIGNED NULL,
    paid_at DATETIME NULL,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_auction FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE RESTRICT,
    CONSTRAINT fk_payments_payer FOREIGN KEY (payer_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_payments_payee FOREIGN KEY (payee_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_payments_bank FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL,
    CONSTRAINT fk_payments_verifier FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE distributions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    auction_id BIGINT UNSIGNED NOT NULL UNIQUE,
    seller_id BIGINT UNSIGNED NOT NULL,
    buyer_id BIGINT UNSIGNED NOT NULL,
    method ENUM('school_pickup', 'direct_meet') NOT NULL DEFAULT 'school_pickup',
    meeting_location VARCHAR(180) NULL,
    scheduled_at DATETIME NULL,
    status ENUM('pending', 'ready', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    notes VARCHAR(255) NULL,
    completed_at DATETIME NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_distributions_auction FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE RESTRICT,
    CONSTRAINT fk_distributions_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_distributions_buyer FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_distributions_updater FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE learning_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    score TINYINT UNSIGNED NOT NULL,
    answers JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_learning_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_learning_user (user_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB;

-- Akun awal administrator: admin / Admin123!
INSERT INTO users (username, email, password, full_name, role, status, approved_at)
VALUES ('admin', 'admin@sman12medan.sch.id', '$2a$12$NkUnOayRhyqD3AlhMP/2wOAprz1Leuy.FoK.TWYG1ReeAWlrHY3eK', 'Administrator SMAN 12 Medan', 'admin', 'active', NOW());
