-- 세종새누리교회 스마트 재정행정시스템 MariaDB 10.X DB 스키마
-- 테이블 접두사: z_ch_saenuri_

CREATE TABLE IF NOT EXISTS z_ch_saenuri_departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dept_name VARCHAR(50) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS z_ch_saenuri_church_titles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title_name VARCHAR(50) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS z_ch_saenuri_user_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_code VARCHAR(30) UNIQUE NOT NULL,
    role_name VARCHAR(50) NOT NULL,
    description VARCHAR(255) DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS z_ch_saenuri_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    oauth_provider VARCHAR(20) NOT NULL,
    oauth_id VARCHAR(100) NOT NULL,
    name VARCHAR(50) NOT NULL,
    title_name VARCHAR(30) NOT NULL DEFAULT '성도',
    email VARCHAR(100),
    phone VARCHAR(20),
    department VARCHAR(50) NOT NULL DEFAULT '행정/재정부',
    role VARCHAR(30) NOT NULL DEFAULT 'APPLICANT',
    status ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    remember_token VARCHAR(100) NULL,
    preferred_mode VARCHAR(20) DEFAULT 'wizard',
    preferred_theme VARCHAR(20) DEFAULT 'green',
    default_bank VARCHAR(30) NULL,
    default_account VARCHAR(50) NULL,
    default_holder VARCHAR(50) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_oauth (oauth_provider, oauth_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 👑 초기 개발자 사이트 관리자 사전 등록
INSERT INTO z_ch_saenuri_users (oauth_provider, oauth_id, name, title_name, email, department, role, status)
VALUES ('google', 'dev_admin_leeshkr', '이승호 개발자', '개발자/관리자', 'leeshkr@gmail.com', '행정/재정부', 'ADMIN', 'APPROVED')
ON DUPLICATE KEY UPDATE role='ADMIN', status='APPROVED';

CREATE TABLE IF NOT EXISTS z_ch_saenuri_expense_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doc_no VARCHAR(30) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    applicant_name VARCHAR(50) NOT NULL,
    department VARCHAR(50) NOT NULL,
    request_date DATE NOT NULL,
    expense_date DATE NOT NULL,
    category VARCHAR(50) DEFAULT '일반지출',
    purpose TEXT NOT NULL,
    total_amount DECIMAL(12, 0) NOT NULL DEFAULT 0,
    bank_name VARCHAR(30) NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    account_holder VARCHAR(50) NOT NULL,
    signature_data LONGTEXT,
    payment_date DATE NULL,
    advance_additional DECIMAL(12, 0) DEFAULT 0,
    advance_return DECIMAL(12, 0) DEFAULT 0,
    advance_return_date DATE NULL,
    status ENUM('PENDING', 'APPROVED', 'REJECTED', 'PAID') DEFAULT 'PENDING',
    reject_reason TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES z_ch_saenuri_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS z_ch_saenuri_expense_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    item_order INT NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    amount DECIMAL(12, 0) NOT NULL,
    note VARCHAR(255) DEFAULT '',
    FOREIGN KEY (request_id) REFERENCES z_ch_saenuri_expense_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS z_ch_saenuri_expense_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES z_ch_saenuri_expense_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS z_ch_saenuri_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    request_id INT NOT NULL,
    type VARCHAR(20) DEFAULT 'STATUS_CHANGE',
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES z_ch_saenuri_users(id) ON DELETE CASCADE,
    FOREIGN KEY (request_id) REFERENCES z_ch_saenuri_expense_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
