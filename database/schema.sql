-- =====================================
-- UKHSA Data Governance Portal Prototype
-- Database Schema
-- =====================================

-- ==============================
-- USERS TABLE
-- ==============================
CREATE TABLE User (
    user_id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    team VARCHAR(50),

    training_completed BOOLEAN NOT NULL DEFAULT FALSE,
    training_expiry DATE,

    system_role VARCHAR(20) NOT NULL
        CHECK (system_role IN ('User','Administrator')),

    job_type VARCHAR(50) NOT NULL
        CHECK (job_type IN ('Researcher','Staff','Intern')),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==============================
-- DATASETS TABLE
-- ==============================
CREATE TABLE Dataset (
    dataset_id SERIAL PRIMARY KEY,

    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    category TEXT NOT NULL

    sensitivity VARCHAR(20) NOT NULL
        CHECK (sensitivity IN ('Sensitive','Non-sensitive')),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==============================
-- ACCESS REQUESTS TABLE
-- (Also stores active permissions)
-- ==============================
CREATE TABLE Access_Request (
    request_id SERIAL PRIMARY KEY,

    user_id INT NOT NULL,
    dataset_id INT NOT NULL,

    access_type VARCHAR(20) NOT NULL
        CHECK (access_type IN ('Read','Write','Admin')),

    purpose TEXT NOT NULL,

    training_confirmed BOOLEAN NOT NULL,

    request_status VARCHAR(20) DEFAULT 'Pending'
        CHECK (request_status IN ('Pending','Approved','Rejected')),

    approver_id INT,

    approval_reason TEXT,

    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    approved_date TIMESTAMP,

    expiry_date DATE,

    is_active BOOLEAN DEFAULT TRUE,

    CONSTRAINT fk_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_dataset
        FOREIGN KEY (dataset_id)
        REFERENCES datasets(dataset_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_approver
        FOREIGN KEY (approver_id)
        REFERENCES users(user_id)
);

-- ==============================
-- RULES TABLE
-- ==============================
CREATE TABLE Rules (
    rule_id SERIAL PRIMARY KEY,

    dataset_id INT,

    auto_approve BOOLEAN DEFAULT FALSE,

    required_approver_role VARCHAR(20)
        CHECK (required_approver_role IN ('Administrator')),

    CONSTRAINT fk_rule_dataset
        FOREIGN KEY (dataset_id)
        REFERENCES datasets(dataset_id)
        ON DELETE CASCADE
);

-- ==============================
-- AUDIT LOG TABLE
-- ==============================
CREATE TABLE Audit_log (
    log_id SERIAL PRIMARY KEY,

    user_id INT,

    action TEXT NOT NULL,

    target_table VARCHAR(50) NOT NULL,

    target_id INT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_log_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
);