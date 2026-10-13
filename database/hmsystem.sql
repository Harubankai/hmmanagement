-- ============================================================
-- Gerona Hospital Information and Billing Management System
-- Database Schema with Sample Data
-- ============================================================

CREATE DATABASE IF NOT EXISTS hmsystem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hmsystem;

-- TABLE: users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    patient_id INT NULL DEFAULT NULL,
    role ENUM('admin','staff','patient') NOT NULL DEFAULT 'patient',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    email_verified TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- TABLE: email_verifications
CREATE TABLE IF NOT EXISTS email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    code VARCHAR(10) NOT NULL,
    payload TEXT,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (email),
    INDEX (code)
) ENGINE=InnoDB;


-- TABLE: departments
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    head_doctor VARCHAR(100),
    contact_number VARCHAR(20),
    location VARCHAR(100),
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- TABLE: patients
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('Male','Female','Other') NOT NULL,
    contact_number VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    emergency_contact_name VARCHAR(100),
    emergency_contact_number VARCHAR(20),
    blood_type ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') DEFAULT 'Unknown',
    status ENUM('Active','Inactive','Discharged') NOT NULL DEFAULT 'Active',
    registration_date DATE NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- TABLE: appointments
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id VARCHAR(20) NOT NULL UNIQUE,
    patient_id INT NOT NULL,
    department_id INT NOT NULL,
    doctor_name VARCHAR(100),
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    purpose TEXT,
    status ENUM('Scheduled','Completed','Cancelled','Pending') NOT NULL DEFAULT 'Pending',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- TABLE: medical_records
CREATE TABLE IF NOT EXISTS medical_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    record_id VARCHAR(20) NOT NULL UNIQUE,
    patient_id INT NOT NULL,
    record_date DATE NOT NULL,
    attending_staff INT,
    chief_complaint TEXT,
    diagnosis TEXT,
    treatment TEXT,
    prescription TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (attending_staff) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- TABLE: bills
CREATE TABLE IF NOT EXISTS bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id VARCHAR(20) NOT NULL UNIQUE,
    patient_id INT NOT NULL,
    bill_date DATE NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payment_status ENUM('Unpaid','Partially Paid','Paid') NOT NULL DEFAULT 'Unpaid',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- TABLE: bill_items
CREATE TABLE IF NOT EXISTS bill_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    service_type VARCHAR(100),
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- TABLE: payments
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id VARCHAR(20) NOT NULL UNIQUE,
    bill_id INT NOT NULL,
    patient_id INT NOT NULL,
    payment_date DATE NOT NULL,
    amount_paid DECIMAL(12,2) NOT NULL,
    payment_method ENUM('Cash','GCash','Bank Transfer','Other') NOT NULL DEFAULT 'Cash',
    reference_number VARCHAR(100),
    received_by INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- TABLE: activity_logs
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- SAMPLE DATA: users (password = "password123")
INSERT INTO users (username, password, full_name, email, role, status) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@geronhospital.com', 'admin', 'active'),
('drgarcia', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Maria Garcia', 'mgarcia@geronhospital.com', 'staff', 'active'),
('nurse_santos', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Nurse Ana Santos', 'asantos@geronhospital.com', 'staff', 'active'),
('drreyes', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Jose Reyes', 'jreyes@geronhospital.com', 'staff', 'active'),
('staff_cruz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Carlo Cruz', 'ccruz@geronhospital.com', 'staff', 'active');

-- SAMPLE DATA: departments
INSERT INTO departments (name, description, head_doctor, contact_number, location, status) VALUES
('General Medicine', 'Handles general health consultations and primary care for all age groups.', 'Dr. Maria Garcia', '09171234567', 'Building A, Ground Floor', 'active'),
('Pediatrics', 'Specialized medical care for infants, children, and adolescents.', 'Dr. Jose Reyes', '09181234568', 'Building B, 2nd Floor', 'active'),
('Emergency', 'Provides immediate treatment for acute illnesses and injuries.', 'Dr. Ana Lim', '09191234569', 'Building A, Emergency Wing', 'active'),
('Laboratory', 'Diagnostic laboratory services including blood tests and urinalysis.', 'Dr. Mark Torres', '09201234570', 'Building C, Ground Floor', 'active'),
('Pharmacy', 'Dispensing of prescription medications and health products.', 'Pharmacist Rosa Dela Cruz', '09211234571', 'Building A, Pharmacy Counter', 'active'),
('Radiology', 'X-ray, ultrasound, and other imaging services.', 'Dr. Carl Bautista', '09221234572', 'Building C, 1st Floor', 'active'),
('Dental', 'Dental care including cleaning, extraction, and orthodontics.', 'Dr. Lisa Navarro', '09231234573', 'Building B, Ground Floor', 'active');

-- SAMPLE DATA: patients
INSERT INTO patients (patient_id, first_name, middle_name, last_name, date_of_birth, gender, contact_number, email, address, emergency_contact_name, emergency_contact_number, blood_type, status, registration_date) VALUES
('PT-0001', 'Juan', 'Santos', 'Dela Cruz', '1985-03-15', 'Male', '09151111001', 'juan.dc@email.com', '123 Rizal Street, Gerona, Tarlac', 'Maria Dela Cruz', '09151111002', 'O+', 'Active', '2026-01-10'),
('PT-0002', 'Maria', 'Reyes', 'Garcia', '1990-07-22', 'Female', '09152222001', 'maria.g@email.com', '456 Mabini Avenue, Gerona, Tarlac', 'Pedro Garcia', '09152222002', 'A+', 'Active', '2026-01-15'),
('PT-0003', 'Pedro', 'Cruz', 'Bautista', '1975-11-08', 'Male', '09153333001', 'pedro.b@email.com', '789 Bonifacio Road, Gerona, Tarlac', 'Ana Bautista', '09153333002', 'B+', 'Active', '2026-02-01'),
('PT-0004', 'Ana', 'Lopez', 'Torres', '2000-05-30', 'Female', '09154444001', 'ana.t@email.com', '321 Luna Street, Gerona, Tarlac', 'Jose Torres', '09154444002', 'AB+', 'Active', '2026-02-14'),
('PT-0005', 'Carlos', 'Reyes', 'Mendoza', '1960-09-12', 'Male', '09155555001', 'carlos.m@email.com', '654 Aguinaldo Drive, Gerona, Tarlac', 'Rosa Mendoza', '09155555002', 'O-', 'Active', '2026-03-05'),
('PT-0006', 'Rosa', 'Santos', 'Navarro', '1995-01-25', 'Female', '09156666001', 'rosa.n@email.com', '987 Quezon Boulevard, Gerona, Tarlac', 'Miguel Navarro', '09156666002', 'A-', 'Active', '2026-03-20'),
('PT-0007', 'Miguel', 'Cruz', 'Ramos', '1988-06-18', 'Male', '09157777001', 'miguel.r@email.com', '147 Magsaysay Street, Gerona, Tarlac', 'Lita Ramos', '09157777002', 'B-', 'Active', '2026-04-02'),
('PT-0008', 'Lita', 'Reyes', 'Villanueva', '2010-12-03', 'Female', '09158888001', 'parent.v@email.com', '258 Marcos Highway, Gerona, Tarlac', 'John Villanueva', '09158888002', 'O+', 'Active', '2026-04-18'),
('PT-0009', 'Ramon', 'Santos', 'Castillo', '1952-04-09', 'Male', '09159999001', 'ramon.c@email.com', '369 Jacinto Street, Gerona, Tarlac', 'Carmen Castillo', '09159999002', 'A+', 'Active', '2026-05-07'),
('PT-0010', 'Carmen', 'Lopez', 'Flores', '1970-08-27', 'Female', '09150000001', 'carmen.f@email.com', '741 Lapu-Lapu Road, Gerona, Tarlac', 'Danny Flores', '09150000002', 'AB-', 'Active', '2026-05-22'),
('PT-0011', 'Danny', 'Reyes', 'Pascual', '1982-02-14', 'Male', '09161111001', 'danny.p@email.com', '852 Sikatuna Street, Gerona, Tarlac', 'Nora Pascual', '09161111002', 'O+', 'Active', '2026-06-10'),
('PT-0012', 'Nora', 'Cruz', 'Espiritu', '1998-10-05', 'Female', '09162222001', 'nora.e@email.com', '963 Tinio Avenue, Gerona, Tarlac', 'Mark Espiritu', '09162222002', 'B+', 'Active', '2026-07-01');

-- SAMPLE DATA: appointments
INSERT INTO appointments (appointment_id, patient_id, department_id, doctor_name, appointment_date, appointment_time, purpose, status, created_by) VALUES
('APT-0001', 1, 1, 'Dr. Maria Garcia', '2026-10-08', '08:00:00', 'General check-up and blood pressure monitoring', 'Completed', 1),
('APT-0002', 2, 2, 'Dr. Jose Reyes', '2026-10-08', '09:00:00', 'Pediatric consultation for fever', 'Completed', 2),
('APT-0003', 3, 6, 'Dr. Carl Bautista', '2026-10-08', '10:00:00', 'Chest X-ray and follow-up', 'Scheduled', 1),
('APT-0004', 4, 7, 'Dr. Lisa Navarro', '2026-10-08', '11:00:00', 'Dental cleaning and check-up', 'Pending', 3),
('APT-0005', 5, 1, 'Dr. Maria Garcia', '2026-10-09', '08:30:00', 'Hypertension management follow-up', 'Scheduled', 1),
('APT-0006', 6, 3, 'Dr. Ana Lim', '2026-10-09', '09:30:00', 'Emergency consultation - abdominal pain', 'Pending', 2),
('APT-0007', 7, 4, 'Dr. Mark Torres', '2026-10-10', '10:30:00', 'Laboratory tests - CBC and urinalysis', 'Scheduled', 1),
('APT-0008', 8, 2, 'Dr. Jose Reyes', '2026-10-10', '14:00:00', 'Pediatric vaccination schedule', 'Scheduled', 3),
('APT-0009', 9, 1, 'Dr. Maria Garcia', '2026-10-11', '08:00:00', 'Geriatric care consultation', 'Pending', 1),
('APT-0010', 10, 6, 'Dr. Carl Bautista', '2026-10-11', '10:00:00', 'Abdominal ultrasound', 'Scheduled', 2),
('APT-0011', 11, 7, 'Dr. Lisa Navarro', '2026-10-07', '09:00:00', 'Tooth extraction', 'Completed', 1),
('APT-0012', 12, 3, 'Dr. Ana Lim', '2026-10-07', '11:00:00', 'Emergency - high fever and convulsion', 'Completed', 1);

-- SAMPLE DATA: medical_records
INSERT INTO medical_records (record_id, patient_id, record_date, attending_staff, chief_complaint, diagnosis, treatment, prescription, notes) VALUES
('MR-0001', 1, '2026-10-08', 2, 'Persistent headache and dizziness', 'Hypertension Stage 1', 'Lifestyle modification, low-sodium diet, regular exercise', 'Amlodipine 5mg once daily, Losartan 50mg once daily', 'Patient advised to monitor blood pressure daily and return in 2 weeks'),
('MR-0002', 2, '2026-10-08', 4, 'Fever for 3 days, loss of appetite', 'Viral Upper Respiratory Tract Infection', 'Rest, adequate fluid intake, symptomatic treatment', 'Paracetamol 500mg every 4-6 hours, Vitamin C 500mg once daily', 'Patient to return if fever persists beyond 5 days'),
('MR-0003', 3, '2026-09-20', 2, 'Cough with phlegm for 2 weeks', 'Community-Acquired Pneumonia', 'Chest X-ray, antibiotics, chest physiotherapy', 'Amoxicillin 500mg three times daily for 7 days, Carbocisteine 500mg three times daily', 'Follow-up in 1 week for repeat chest X-ray'),
('MR-0004', 4, '2026-08-15', 4, 'Toothache and gum swelling', 'Dental Caries with Periapical Abscess', 'Tooth extraction of affected tooth, irrigation', 'Amoxicillin 500mg three times daily for 5 days, Ibuprofen 400mg every 8 hours', 'Patient instructed on proper oral hygiene'),
('MR-0005', 5, '2026-07-10', 2, 'Chest pain and shortness of breath on exertion', 'Hypertensive Heart Disease', 'ECG, echocardiography referral, lifestyle modification', 'Metoprolol 50mg once daily, Furosemide 40mg once daily', 'Referred to cardiologist for further evaluation'),
('MR-0006', 6, '2026-06-25', 4, 'Severe abdominal pain, right lower quadrant', 'Appendicitis', 'Emergency appendectomy, post-op care', 'Ceftriaxone 1g IV every 12 hours, Metronidazole 500mg IV every 8 hours', 'Post-op recovery excellent, discharged after 3 days'),
('MR-0007', 7, '2026-05-18', 2, 'Fatigue, pale skin, frequent headaches', 'Iron Deficiency Anemia', 'Dietary modification, iron supplementation', 'Ferrous Sulfate 325mg twice daily, Vitamin C 500mg once daily', 'CBC repeat in 1 month'),
('MR-0008', 8, '2026-04-18', 4, 'Routine well-child check-up, scheduled vaccines', 'Normal growth and development', 'Vaccination administered (MMR, Varicella)', 'Multivitamins as directed', 'Next visit in 6 months'),
('MR-0009', 9, '2026-05-07', 2, 'Joint pain and stiffness in knees and hands', 'Rheumatoid Arthritis', 'Physical therapy referral, anti-inflammatory treatment', 'Methotrexate 7.5mg weekly, Folic Acid 1mg daily, Meloxicam 15mg once daily', 'Monitor liver function test monthly'),
('MR-0010', 10, '2026-05-22', 4, 'Abdominal discomfort, bloating, irregular bowel movements', 'Irritable Bowel Syndrome', 'Dietary counseling, stress management', 'Hyoscine Butylbromide 10mg three times daily, Psyllium fiber supplement', 'Follow-up in 4 weeks');

-- SAMPLE DATA: bills
INSERT INTO bills (bill_id, patient_id, bill_date, total_amount, amount_paid, balance, payment_status, notes, created_by) VALUES
('BILL-0001', 1, '2026-10-08', 1600.00, 1600.00, 0.00, 'Paid', 'Consultation and laboratory fees', 1),
('BILL-0002', 2, '2026-10-08', 900.00, 500.00, 400.00, 'Partially Paid', 'Pediatric consultation and medicine', 2),
('BILL-0003', 3, '2026-09-20', 3500.00, 0.00, 3500.00, 'Unpaid', 'Consultation, X-ray, and antibiotics', 1),
('BILL-0004', 4, '2026-08-15', 2200.00, 2200.00, 0.00, 'Paid', 'Dental services and medication', 3),
('BILL-0005', 5, '2026-07-10', 4800.00, 2000.00, 2800.00, 'Partially Paid', 'Cardiology consultation and diagnostics', 1),
('BILL-0006', 6, '2026-06-25', 15000.00, 15000.00, 0.00, 'Paid', 'Emergency appendectomy and hospitalization', 1),
('BILL-0007', 7, '2026-05-18', 1100.00, 0.00, 1100.00, 'Unpaid', 'Consultation and iron supplements', 2),
('BILL-0008', 8, '2026-04-18', 800.00, 800.00, 0.00, 'Paid', 'Vaccination and well-child check-up', 1),
('BILL-0009', 9, '2026-05-07', 2500.00, 1000.00, 1500.00, 'Partially Paid', 'Rheumatology consultation and medications', 3),
('BILL-0010', 10, '2026-05-22', 1300.00, 0.00, 1300.00, 'Unpaid', 'GI consultation and medications', 1);

-- SAMPLE DATA: bill_items
INSERT INTO bill_items (bill_id, description, service_type, quantity, unit_price, subtotal) VALUES
(1, 'General Consultation', 'Consultation', 1, 500.00, 500.00),
(1, 'Complete Blood Count (CBC)', 'Laboratory', 1, 600.00, 600.00),
(1, 'Urinalysis', 'Laboratory', 1, 200.00, 200.00),
(1, 'Antihypertensive Medication', 'Medicine', 1, 300.00, 300.00),
(2, 'Pediatric Consultation', 'Consultation', 1, 500.00, 500.00),
(2, 'Paracetamol 500mg (10 tablets)', 'Medicine', 1, 80.00, 80.00),
(2, 'Vitamin C 500mg (10 tablets)', 'Medicine', 1, 50.00, 50.00),
(2, 'Nebulization Treatment', 'Procedure', 1, 270.00, 270.00),
(3, 'General Consultation', 'Consultation', 1, 500.00, 500.00),
(3, 'Chest X-ray PA View', 'Radiology', 1, 800.00, 800.00),
(3, 'Sputum Culture', 'Laboratory', 1, 900.00, 900.00),
(3, 'Amoxicillin 500mg (21 capsules)', 'Medicine', 1, 315.00, 315.00),
(3, 'Carbocisteine 500mg (21 capsules)', 'Medicine', 1, 420.00, 420.00),
(3, 'Nebulization', 'Procedure', 3, 155.00, 465.00),
(4, 'Dental Consultation', 'Consultation', 1, 300.00, 300.00),
(4, 'Tooth Extraction (Molar)', 'Dental', 1, 1200.00, 1200.00),
(4, 'Amoxicillin 500mg (15 capsules)', 'Medicine', 1, 225.00, 225.00),
(4, 'Ibuprofen 400mg (15 tablets)', 'Medicine', 1, 150.00, 150.00),
(4, 'Antiseptic Mouthwash', 'Medicine', 1, 125.00, 125.00),
(5, 'General Consultation', 'Consultation', 1, 500.00, 500.00),
(5, 'ECG (12-Lead)', 'Procedure', 1, 600.00, 600.00),
(5, 'Echocardiography', 'Procedure', 1, 2500.00, 2500.00),
(5, 'Metoprolol 50mg (30 tablets)', 'Medicine', 1, 450.00, 450.00),
(5, 'Furosemide 40mg (30 tablets)', 'Medicine', 1, 150.00, 150.00),
(5, 'Potassium Chloride Supplement', 'Medicine', 1, 200.00, 200.00),
(5, 'Blood Pressure Monitoring (1 week)', 'Service', 1, 400.00, 400.00),
(6, 'Emergency Consultation', 'Consultation', 1, 800.00, 800.00),
(6, 'Appendectomy (Surgery Fee)', 'Procedure', 1, 8000.00, 8000.00),
(6, 'Operating Room Fee', 'Procedure', 1, 2500.00, 2500.00),
(6, 'Anesthesia Fee', 'Procedure', 1, 1500.00, 1500.00),
(6, 'Hospital Room (3 days)', 'Accommodation', 3, 400.00, 1200.00),
(6, 'Post-op Medications', 'Medicine', 1, 1000.00, 1000.00),
(7, 'General Consultation', 'Consultation', 1, 500.00, 500.00),
(7, 'CBC with Differential', 'Laboratory', 1, 350.00, 350.00),
(7, 'Serum Iron Test', 'Laboratory', 1, 150.00, 150.00),
(7, 'Ferrous Sulfate 325mg (30 tablets)', 'Medicine', 1, 100.00, 100.00),
(8, 'Pediatric Well-Child Check-up', 'Consultation', 1, 400.00, 400.00),
(8, 'MMR Vaccine', 'Vaccination', 1, 250.00, 250.00),
(8, 'Varicella Vaccine', 'Vaccination', 1, 150.00, 150.00),
(9, 'Rheumatology Consultation', 'Consultation', 1, 600.00, 600.00),
(9, 'ESR Test', 'Laboratory', 1, 200.00, 200.00),
(9, 'Rheumatoid Factor Test', 'Laboratory', 1, 350.00, 350.00),
(9, 'X-ray Both Hands', 'Radiology', 1, 600.00, 600.00),
(9, 'Methotrexate 7.5mg (4 tablets)', 'Medicine', 1, 250.00, 250.00),
(9, 'Meloxicam 15mg (30 tablets)', 'Medicine', 1, 300.00, 300.00),
(9, 'Folic Acid 1mg (30 tablets)', 'Medicine', 1, 200.00, 200.00),
(10, 'General Consultation', 'Consultation', 1, 500.00, 500.00),
(10, 'Stool Exam', 'Laboratory', 1, 150.00, 150.00),
(10, 'Abdominal Ultrasound', 'Radiology', 1, 450.00, 450.00),
(10, 'Hyoscine Butylbromide (30 tablets)', 'Medicine', 1, 200.00, 200.00);

-- SAMPLE DATA: payments
INSERT INTO payments (payment_id, bill_id, patient_id, payment_date, amount_paid, payment_method, reference_number, received_by, notes) VALUES
('PAY-0001', 1, 1, '2026-10-08', 1600.00, 'Cash', NULL, 3, 'Full payment received'),
('PAY-0002', 2, 2, '2026-10-08', 500.00, 'GCash', 'GC-2026100801', 3, 'Partial payment via GCash'),
('PAY-0003', 4, 4, '2026-08-15', 2200.00, 'Cash', NULL, 5, 'Full payment received'),
('PAY-0004', 5, 5, '2026-07-10', 2000.00, 'Bank Transfer', 'BT-20260710-001', 3, 'Partial bank transfer payment'),
('PAY-0005', 6, 6, '2026-06-25', 10000.00, 'Cash', NULL, 5, 'Initial partial payment for surgery'),
('PAY-0006', 6, 6, '2026-06-28', 5000.00, 'GCash', 'GC-2026062801', 3, 'Final payment to complete the bill'),
('PAY-0007', 8, 8, '2026-04-18', 800.00, 'Cash', NULL, 5, 'Full payment received'),
('PAY-0008', 9, 9, '2026-05-07', 1000.00, 'Cash', NULL, 3, 'Partial payment received');

-- SAMPLE DATA: activity_logs
INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES
(1, 'User Login', 'Administrator logged into the system', '127.0.0.1'),
(1, 'Patient Registration', 'Registered new patient: Juan Dela Cruz (PT-0001)', '127.0.0.1'),
(2, 'Appointment Created', 'Created appointment APT-0001 for Juan Dela Cruz', '127.0.0.1'),
(1, 'Bill Created', 'Created bill BILL-0001 for Juan Dela Cruz', '127.0.0.1'),
(3, 'Payment Recorded', 'Recorded payment PAY-0001 of P1600.00 for BILL-0001', '127.0.0.1'),
(2, 'Medical Record Added', 'Added medical record MR-0001 for Juan Dela Cruz', '127.0.0.1'),
(1, 'User Login', 'Administrator logged into the system', '127.0.0.1'),
(4, 'Appointment Created', 'Created appointment APT-0006 for Rosa Navarro', '127.0.0.1');
