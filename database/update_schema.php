<?php
require_once __DIR__ . '/../config/database.php';

$conn = getDBConnection();

// 1. Modify users table role column to include 'patient'
$conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('admin','staff','patient') NOT NULL DEFAULT 'staff'");

// 2. Add patient_id column if it doesn't exist
$check_col = $conn->query("SHOW COLUMNS FROM users LIKE 'patient_id'");
if ($check_col->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN patient_id INT NULL DEFAULT NULL AFTER email");
    $conn->query("ALTER TABLE users ADD CONSTRAINT fk_users_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE");
    echo "Added patient_id column to users table.\n";
} else {
    echo "patient_id column already exists in users table.\n";
}

// 3. Add email_verified column if it doesn't exist
$check_verified = $conn->query("SHOW COLUMNS FROM users LIKE 'email_verified'");
if ($check_verified->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN email_verified TINYINT(1) DEFAULT 1 AFTER status");
    echo "Added email_verified column to users table.\n";
}

// 4. Create email_verifications table
$sql_verification = "CREATE TABLE IF NOT EXISTS email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    code VARCHAR(10) NOT NULL,
    payload TEXT,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (email),
    INDEX (code)
) ENGINE=InnoDB;";

if ($conn->query($sql_verification)) {
    echo "email_verifications table ready.\n";
} else {
    echo "Error creating email_verifications table: " . $conn->error . "\n";
}

$conn->close();
echo "Schema update completed successfully!\n";
