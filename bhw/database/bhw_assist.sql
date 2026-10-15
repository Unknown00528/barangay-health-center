CREATE DATABASE IF NOT EXISTS bhw_assist;
USE bhw_assist;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'BHW'
);

INSERT INTO users (username,password,role)
VALUES ('admin','$2y$10$DNRRvU2ynLEf8/lCYCV.BO.tqSYNm4aUlchPHSw6Kw0cfsIoyocPu','Admin')
ON DUPLICATE KEY UPDATE password=VALUES(password), role=VALUES(role);

INSERT INTO users (username,password,role)
VALUES ('bhw','$2y$10$X9WnE9.9kK9UHta72OuHmep3CG8lmwGIeuc2bP4KT2aDzXXjKdmGW','BHW')
ON DUPLICATE KEY UPDATE password=VALUES(password), role=VALUES(role);

CREATE TABLE IF NOT EXISTS login_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    login_at DATETIME NOT NULL,
    logout_at DATETIME NULL,
    ip_address VARCHAR(45),
    role VARCHAR(20) NOT NULL,
    INDEX idx_login_at (login_at),
    INDEX idx_login_username (username),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_no VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    first_name VARCHAR(50) NULL,
    last_name VARCHAR(50) NULL,
    age INT NOT NULL,
    sex VARCHAR(20) NOT NULL,
    address VARCHAR(255),
    house_no VARCHAR(50) NULL,
    street VARCHAR(100) NULL,
    purok VARCHAR(100) NULL,
    contact VARCHAR(30),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS consultations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    consultation_date DATE NOT NULL,
    complaint TEXT,
    assessment TEXT,
    action_taken TEXT,
    followup_date DATE,
    FOREIGN KEY (patient_id) REFERENCES patients(id)
);

CREATE TABLE IF NOT EXISTS home_visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    visit_date DATE NOT NULL,
    reason TEXT,
    findings TEXT,
    action_taken TEXT,
    FOREIGN KEY (patient_id) REFERENCES patients(id)
);

CREATE TABLE IF NOT EXISTS vaccinations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    vaccine VARCHAR(100) NOT NULL,
    dose VARCHAR(50),
    vaccination_date DATE NOT NULL,
    next_date DATE,
    FOREIGN KEY (patient_id) REFERENCES patients(id)
);

CREATE TABLE IF NOT EXISTS medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    medicine_name VARCHAR(100) NOT NULL,
    quantity INT NOT NULL,
    distribution_date DATE NOT NULL,
    purpose VARCHAR(255),
    FOREIGN KEY (patient_id) REFERENCES patients(id)
);

CREATE TABLE IF NOT EXISTS followups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    followup_date DATE NOT NULL,
    reason TEXT,
    status VARCHAR(20) DEFAULT 'Pending',
    notes TEXT,
    FOREIGN KEY (patient_id) REFERENCES patients(id)
);