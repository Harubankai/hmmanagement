-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 20, 2026 at 09:05 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hmsystem`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES
(1, 1, 'User Login', 'Administrator logged into the system', '127.0.0.1', '2026-10-08 14:21:46'),
(2, 1, 'Patient Registration', 'Registered new patient: Juan Dela Cruz (PT-0001)', '127.0.0.1', '2026-10-08 14:21:46'),
(3, 2, 'Appointment Created', 'Created appointment APT-0001 for Juan Dela Cruz', '127.0.0.1', '2026-10-08 14:21:46'),
(4, 1, 'Bill Created', 'Created bill BILL-0001 for Juan Dela Cruz', '127.0.0.1', '2026-10-08 14:21:46'),
(5, 3, 'Payment Recorded', 'Recorded payment PAY-0001 of P1600.00 for BILL-0001', '127.0.0.1', '2026-10-08 14:21:46'),
(6, 2, 'Medical Record Added', 'Added medical record MR-0001 for Juan Dela Cruz', '127.0.0.1', '2026-10-08 14:21:46'),
(7, 1, 'User Login', 'Administrator logged into the system', '127.0.0.1', '2026-10-08 14:21:46'),
(8, 4, 'Appointment Created', 'Created appointment APT-0006 for Rosa Navarro', '127.0.0.1', '2026-10-08 14:21:46'),
(9, 1, 'User Login', 'Administrator logged into the system', '::1', '2026-10-08 14:22:06'),
(10, 1, 'User Logout', 'Administrator logged out of the system', '::1', '2026-10-08 14:27:45'),
(11, 2, 'User Login', 'Dr. Maria Garcia logged into the system', '::1', '2026-10-08 14:27:56'),
(12, 2, 'User Logout', 'Dr. Maria Garcia logged out of the system', '::1', '2026-10-08 14:28:24'),
(13, 1, 'User Login', 'Administrator logged into the system', '::1', '2026-10-08 14:29:01'),
(14, 1, 'User Logout', 'Administrator logged out of the system', '::1', '2026-10-08 14:37:11'),
(15, 1, 'User Login', 'Administrator logged into the system', '::1', '2026-10-08 14:42:27'),
(16, 1, 'User Logout', 'Administrator logged out of the system', '::1', '2026-10-08 15:02:45'),
(17, 1, 'User Login', 'Administrator logged into the system', '::1', '2026-10-10 10:35:11'),
(18, 1, 'User Logout', 'Administrator logged out of the system', '::1', '2026-10-10 10:42:22'),
(19, 1, 'User Login', 'Administrator logged into the system', '::1', '2026-10-10 10:44:14'),
(20, 1, 'User Logout', 'Administrator logged out of the system', '::1', '2026-10-10 10:47:27'),
(21, 1, 'User Login', 'Administrator logged into the system', '::1', '2026-10-10 10:52:01'),
(22, 1, 'User Logout', 'Administrator logged out of the system', '::1', '2026-10-10 10:52:33'),
(23, 6, 'Patient Sign Up', 'New patient signed up & verified email: Toffee Dela Cruz (PT-0013)', '::1', '2026-10-10 11:20:26'),
(24, 6, 'User Logout', 'Toffee Dela Cruz logged out of the system', '::1', '2026-10-10 11:23:56'),
(25, 1, 'User Login', 'Administrator logged into the system', '::1', '2026-10-10 11:24:30'),
(26, 1, 'User Logout', 'Administrator logged out of the system', '::1', '2026-10-10 11:25:08'),
(27, 6, 'User Login', 'Toffee Dela Cruz logged into the system', '::1', '2026-10-10 11:26:05'),
(28, 6, 'User Logout', 'Toffee Dela Cruz logged out of the system', '::1', '2026-10-10 11:26:17'),
(29, 6, 'User Login', 'Toffee Dela Cruz logged into the system', '::1', '2026-10-10 11:32:55'),
(30, 6, 'User Logout', 'Toffee Dela Cruz logged out of the system', '::1', '2026-10-10 11:34:15'),
(31, 6, 'User Login', 'Toffee Dela Cruz logged into the system', '::1', '2026-10-10 11:36:06'),
(32, 6, 'User Logout', 'Toffee Dela Cruz logged out of the system', '::1', '2026-10-10 11:37:27'),
(33, 6, 'User Login', 'Toffee Dela Cruz logged into the system', '::1', '2026-10-10 11:38:05'),
(34, 6, 'User Logout', 'Toffee Dela Cruz logged out of the system', '::1', '2026-10-10 11:38:57'),
(35, 1, 'User Login', 'Administrator logged into the system', '::1', '2026-10-10 11:43:15'),
(36, 1, 'User Logout', 'Administrator logged out of the system', '::1', '2026-10-10 11:43:53'),
(37, 6, 'User Login', 'Toffee Dela Cruz logged into the system', '::1', '2026-10-10 11:50:53'),
(38, 6, 'Appointment Created', 'Created appointment APT-0015 for PT-0013 - Toffee Dela Cruz', '::1', '2026-10-10 11:57:36'),
(39, 6, 'User Logout', 'Toffee Dela Cruz logged out of the system', '::1', '2026-10-10 11:57:52'),
(40, 1, 'User Login', 'Administrator logged into the system', '::1', '2026-10-10 11:58:10'),
(41, 1, 'User Logout', 'Administrator logged out of the system', '::1', '2026-10-10 11:58:52'),
(42, 2, 'User Login', 'Dr. Maria Garcia logged into the system', '::1', '2026-10-10 12:00:11'),
(43, 2, 'User Logout', 'Dr. Maria Garcia logged out of the system', '::1', '2026-10-10 12:00:42'),
(44, 6, 'User Login', 'Toffee Dela Cruz logged into the system', '::1', '2026-10-10 12:03:43'),
(45, 6, 'Appointment Created', 'Created appointment APT-0016 for PT-0013 - Toffee Dela Cruz', '::1', '2026-10-10 12:07:29'),
(46, 6, 'User Logout', 'Toffee Dela Cruz logged out of the system', '::1', '2026-10-10 12:08:38'),
(47, 2, 'User Login', 'Dr. Maria Garcia logged into the system', '::1', '2026-10-10 12:08:51'),
(48, 2, 'User Logout', 'Dr. Maria Garcia logged out of the system', '::1', '2026-10-10 12:12:00'),
(49, 6, 'User Login', 'Toffee Dela Cruz logged into the system', '::1', '2026-10-10 12:12:13'),
(50, 6, 'User Logout', 'Toffee Dela Cruz logged out of the system', '::1', '2026-10-10 12:16:53'),
(51, 2, 'User Login', 'Dr. Maria Garcia logged into the system', '::1', '2026-10-10 12:17:07'),
(52, 2, 'Appointment Updated', 'Updated appointment ID: 16', '::1', '2026-10-10 12:17:58'),
(53, 2, 'Bill Created', 'Created bill BILL-0011 for PT-0013 - Toffee Dela Cruz, total: 500.00', '::1', '2026-10-10 12:19:39'),
(54, 2, 'User Logout', 'Dr. Maria Garcia logged out of the system', '::1', '2026-10-10 12:19:53'),
(55, 6, 'User Login', 'Toffee Dela Cruz logged into the system', '::1', '2026-10-10 12:20:05'),
(56, 6, 'User Logout', 'Toffee Dela Cruz logged out of the system', '::1', '2026-10-10 12:21:59'),
(57, 2, 'User Login', 'Dr. Maria Garcia logged into the system', '::1', '2026-10-10 12:22:16'),
(58, 2, 'Payment Recorded', 'Recorded payment PAY-0009 of ₱500.00 for bill BILL-0011', '::1', '2026-10-10 12:22:57'),
(59, 2, 'User Logout', 'Dr. Maria Garcia logged out of the system', '::1', '2026-10-10 12:23:08'),
(60, 6, 'User Login', 'Toffee Dela Cruz logged into the system', '::1', '2026-10-10 12:23:22'),
(61, 6, 'User Logout', 'Toffee Dela Cruz logged out of the system', '::1', '2026-10-10 12:23:44'),
(62, 1, 'User Login', 'Administrator logged into the system', '::1', '2026-10-10 23:47:50'),
(63, 6, 'User Login', 'Toffee Dela Cruz logged into the system', '::1', '2026-10-12 01:27:24'),
(64, 6, 'User Logout', 'Toffee Dela Cruz logged out of the system', '::1', '2026-10-12 01:28:10'),
(65, 1, 'User Login', 'Administrator logged into the system', '::1', '2026-10-12 01:36:01'),
(66, 1, 'User Logout', 'Administrator logged out of the system', '::1', '2026-10-12 01:37:23'),
(67, 2, 'User Login', 'Dr. Maria Garcia logged into the system', '::1', '2026-10-12 01:37:46'),
(68, 2, 'User Logout', 'Dr. Maria Garcia logged out of the system', '::1', '2026-10-12 01:41:30'),
(69, 1, 'User Login', 'Administrator logged into the system', '::1', '2026-10-12 04:35:42'),
(70, 1, 'User Logout', 'Administrator logged out of the system', '::1', '2026-10-12 04:39:43'),
(71, 2, 'User Login', 'Dr. Maria Garcia logged into the system', '::1', '2026-10-12 04:40:25'),
(72, 2, 'User Logout', 'Dr. Maria Garcia logged out of the system', '::1', '2026-10-12 04:44:16'),
(73, 6, 'User Login', 'Toffee Dela Cruz logged into the system', '::1', '2026-10-12 04:44:28'),
(74, 6, 'User Logout', 'Toffee Dela Cruz logged out of the system', '::1', '2026-10-12 04:45:03'),
(75, 1, 'User Login', 'Administrator logged into the system', '::1', '2026-10-12 05:54:26'),
(76, 1, 'User Logout', 'Administrator logged out of the system', '::1', '2026-10-12 05:56:09'),
(77, 1, 'User Login', 'Administrator logged into the system', '::1', '2026-10-13 07:11:24'),
(78, 1, 'User Logout', 'Administrator logged out of the system', '::1', '2026-10-13 07:11:39'),
(79, 1, 'User Login', 'Administrator logged into the system', '::1', '2026-10-15 14:12:21'),
(80, 1, 'User Logout', 'Administrator logged out of the system', '::1', '2026-10-15 14:16:13'),
(81, 1, 'User Login', 'Administrator logged into the system', '::1', '2026-10-15 14:20:09'),
(82, 1, 'User Logout', 'Administrator logged out of the system', '::1', '2026-10-15 14:20:23'),
(83, 1, 'User Login', 'Administrator logged into the system', '::1', '2026-10-15 14:26:10'),
(84, 1, 'User Logout', 'Administrator logged out of the system', '::1', '2026-10-15 14:31:40'),
(85, 7, 'Patient Sign Up', 'New patient signed up & verified email: Toffee Dela Cruz (PT-0014)', '::1', '2026-10-15 14:33:16'),
(86, 7, 'User Logout', 'Toffee Dela Cruz logged out of the system', '::1', '2026-10-15 14:33:29'),
(87, 1, 'User Login', 'Administrator logged into the system', '::1', '2026-10-18 10:37:40'),
(88, 1, 'User Logout', 'Administrator logged out of the system', '::1', '2026-10-18 10:39:25'),
(89, 1, 'User Login', 'Administrator logged into the system', '::1', '2026-10-18 11:39:37'),
(90, 1, 'User Logout', 'Administrator logged out of the system', '::1', '2026-10-18 11:57:42'),
(91, 1, 'User Login', 'Administrator logged into the system', '::1', '2026-10-20 02:18:01'),
(92, 1, 'User Logout', 'Administrator logged out of the system', '::1', '2026-10-20 02:18:18'),
(93, 1, 'User Login', 'Administrator logged into the system', '::1', '2026-10-20 06:15:14'),
(94, 1, 'User Login', 'Administrator logged into the system', '::1', '2026-10-20 06:15:56'),
(95, 1, 'User Logout', 'Administrator logged out of the system', '::1', '2026-10-20 06:16:09'),
(96, 2, 'User Login', 'Dr. Maria Garcia logged into the system', '::1', '2026-10-20 06:16:42'),
(97, 2, 'User Logout', 'Dr. Maria Garcia logged out of the system', '::1', '2026-10-20 06:17:41'),
(98, 6, 'User Login', 'Toffee Dela Cruz logged into the system', '::1', '2026-10-20 06:18:14');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `appointment_id` varchar(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `doctor_name` varchar(100) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `purpose` text DEFAULT NULL,
  `status` enum('Scheduled','Completed','Cancelled','Pending') NOT NULL DEFAULT 'Pending',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `appointment_id`, `patient_id`, `department_id`, `doctor_name`, `appointment_date`, `appointment_time`, `purpose`, `status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'APT-0001', 1, 1, 'Dr. Maria Garcia', '2026-10-08', '08:00:00', 'General check-up and blood pressure monitoring', 'Completed', NULL, 1, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(2, 'APT-0002', 2, 2, 'Dr. Jose Reyes', '2026-10-08', '09:00:00', 'Pediatric consultation for fever', 'Completed', NULL, 2, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(3, 'APT-0003', 3, 6, 'Dr. Carl Bautista', '2026-10-08', '10:00:00', 'Chest X-ray and follow-up', 'Scheduled', NULL, 1, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(4, 'APT-0004', 4, 7, 'Dr. Lisa Navarro', '2026-10-08', '11:00:00', 'Dental cleaning and check-up', 'Pending', NULL, 3, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(5, 'APT-0005', 5, 1, 'Dr. Maria Garcia', '2026-10-09', '08:30:00', 'Hypertension management follow-up', 'Scheduled', NULL, 1, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(6, 'APT-0006', 6, 3, 'Dr. Ana Lim', '2026-10-09', '09:30:00', 'Emergency consultation - abdominal pain', 'Pending', NULL, 2, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(7, 'APT-0007', 7, 4, 'Dr. Mark Torres', '2026-10-10', '10:30:00', 'Laboratory tests - CBC and urinalysis', 'Scheduled', NULL, 1, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(8, 'APT-0008', 8, 2, 'Dr. Jose Reyes', '2026-10-10', '14:00:00', 'Pediatric vaccination schedule', 'Scheduled', NULL, 3, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(9, 'APT-0009', 9, 1, 'Dr. Maria Garcia', '2026-10-11', '08:00:00', 'Geriatric care consultation', 'Pending', NULL, 1, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(10, 'APT-0010', 10, 6, 'Dr. Carl Bautista', '2026-10-11', '10:00:00', 'Abdominal ultrasound', 'Scheduled', NULL, 2, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(11, 'APT-0011', 11, 7, 'Dr. Lisa Navarro', '2026-10-07', '09:00:00', 'Tooth extraction', 'Completed', NULL, 1, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(12, 'APT-0012', 12, 3, 'Dr. Ana Lim', '2026-10-07', '11:00:00', 'Emergency - high fever and convulsion', 'Completed', NULL, 1, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(13, 'APT-0013', 13, 1, 'Dr. Maria Garcia', '2026-10-11', '09:00:00', 'Checkup', 'Pending', 'Test appointment', 6, '2026-10-10 11:52:42', '2026-10-10 11:52:42'),
(14, 'APT-0014', 13, 1, 'Dr. Juan Santos', '2026-10-11', '10:00:00', 'Routine General Checkup', 'Pending', 'Booked by patient online portal', 6, '2026-10-10 11:55:50', '2026-10-10 11:55:50'),
(15, 'APT-0015', 13, 3, '', '2026-10-10', '08:00:00', '', 'Pending', '', 6, '2026-10-10 11:57:36', '2026-10-10 11:57:36'),
(16, 'APT-0016', 13, 7, 'Dr. Jose Reyes', '2026-10-10', '20:11:00', '', 'Completed', 'yawa', 6, '2026-10-10 12:07:29', '2026-10-10 12:17:58');

-- --------------------------------------------------------

--
-- Table structure for table `bills`
--

CREATE TABLE `bills` (
  `id` int(11) NOT NULL,
  `bill_id` varchar(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `bill_date` date NOT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('Unpaid','Partially Paid','Paid') NOT NULL DEFAULT 'Unpaid',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bills`
--

INSERT INTO `bills` (`id`, `bill_id`, `patient_id`, `bill_date`, `total_amount`, `amount_paid`, `balance`, `payment_status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'BILL-0001', 1, '2026-10-08', 1600.00, 1600.00, 0.00, 'Paid', 'Consultation and laboratory fees', 1, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(2, 'BILL-0002', 2, '2026-10-08', 900.00, 500.00, 400.00, 'Partially Paid', 'Pediatric consultation and medicine', 2, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(3, 'BILL-0003', 3, '2026-09-20', 3500.00, 0.00, 3500.00, 'Unpaid', 'Consultation, X-ray, and antibiotics', 1, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(4, 'BILL-0004', 4, '2026-08-15', 2200.00, 2200.00, 0.00, 'Paid', 'Dental services and medication', 3, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(5, 'BILL-0005', 5, '2026-07-10', 4800.00, 2000.00, 2800.00, 'Partially Paid', 'Cardiology consultation and diagnostics', 1, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(6, 'BILL-0006', 6, '2026-06-25', 15000.00, 15000.00, 0.00, 'Paid', 'Emergency appendectomy and hospitalization', 1, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(7, 'BILL-0007', 7, '2026-05-18', 1100.00, 0.00, 1100.00, 'Unpaid', 'Consultation and iron supplements', 2, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(8, 'BILL-0008', 8, '2026-04-18', 800.00, 800.00, 0.00, 'Paid', 'Vaccination and well-child check-up', 1, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(9, 'BILL-0009', 9, '2026-05-07', 2500.00, 1000.00, 1500.00, 'Partially Paid', 'Rheumatology consultation and medications', 3, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(10, 'BILL-0010', 10, '2026-05-22', 1300.00, 0.00, 1300.00, 'Unpaid', 'GI consultation and medications', 1, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(11, 'BILL-0011', 13, '2026-10-10', 500.00, 500.00, 0.00, 'Paid', 'dad', 2, '2026-10-10 12:19:39', '2026-10-10 12:22:57');

-- --------------------------------------------------------

--
-- Table structure for table `bill_items`
--

CREATE TABLE `bill_items` (
  `id` int(11) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `service_type` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bill_items`
--

INSERT INTO `bill_items` (`id`, `bill_id`, `description`, `service_type`, `quantity`, `unit_price`, `subtotal`, `created_at`) VALUES
(1, 1, 'General Consultation', 'Consultation', 1, 500.00, 500.00, '2026-10-08 14:21:46'),
(2, 1, 'Complete Blood Count (CBC)', 'Laboratory', 1, 600.00, 600.00, '2026-10-08 14:21:46'),
(3, 1, 'Urinalysis', 'Laboratory', 1, 200.00, 200.00, '2026-10-08 14:21:46'),
(4, 1, 'Antihypertensive Medication', 'Medicine', 1, 300.00, 300.00, '2026-10-08 14:21:46'),
(5, 2, 'Pediatric Consultation', 'Consultation', 1, 500.00, 500.00, '2026-10-08 14:21:46'),
(6, 2, 'Paracetamol 500mg (10 tablets)', 'Medicine', 1, 80.00, 80.00, '2026-10-08 14:21:46'),
(7, 2, 'Vitamin C 500mg (10 tablets)', 'Medicine', 1, 50.00, 50.00, '2026-10-08 14:21:46'),
(8, 2, 'Nebulization Treatment', 'Procedure', 1, 270.00, 270.00, '2026-10-08 14:21:46'),
(9, 3, 'General Consultation', 'Consultation', 1, 500.00, 500.00, '2026-10-08 14:21:46'),
(10, 3, 'Chest X-ray PA View', 'Radiology', 1, 800.00, 800.00, '2026-10-08 14:21:46'),
(11, 3, 'Sputum Culture', 'Laboratory', 1, 900.00, 900.00, '2026-10-08 14:21:46'),
(12, 3, 'Amoxicillin 500mg (21 capsules)', 'Medicine', 1, 315.00, 315.00, '2026-10-08 14:21:46'),
(13, 3, 'Carbocisteine 500mg (21 capsules)', 'Medicine', 1, 420.00, 420.00, '2026-10-08 14:21:46'),
(14, 3, 'Nebulization', 'Procedure', 3, 155.00, 465.00, '2026-10-08 14:21:46'),
(15, 4, 'Dental Consultation', 'Consultation', 1, 300.00, 300.00, '2026-10-08 14:21:46'),
(16, 4, 'Tooth Extraction (Molar)', 'Dental', 1, 1200.00, 1200.00, '2026-10-08 14:21:46'),
(17, 4, 'Amoxicillin 500mg (15 capsules)', 'Medicine', 1, 225.00, 225.00, '2026-10-08 14:21:46'),
(18, 4, 'Ibuprofen 400mg (15 tablets)', 'Medicine', 1, 150.00, 150.00, '2026-10-08 14:21:46'),
(19, 4, 'Antiseptic Mouthwash', 'Medicine', 1, 125.00, 125.00, '2026-10-08 14:21:46'),
(20, 5, 'General Consultation', 'Consultation', 1, 500.00, 500.00, '2026-10-08 14:21:46'),
(21, 5, 'ECG (12-Lead)', 'Procedure', 1, 600.00, 600.00, '2026-10-08 14:21:46'),
(22, 5, 'Echocardiography', 'Procedure', 1, 2500.00, 2500.00, '2026-10-08 14:21:46'),
(23, 5, 'Metoprolol 50mg (30 tablets)', 'Medicine', 1, 450.00, 450.00, '2026-10-08 14:21:46'),
(24, 5, 'Furosemide 40mg (30 tablets)', 'Medicine', 1, 150.00, 150.00, '2026-10-08 14:21:46'),
(25, 5, 'Potassium Chloride Supplement', 'Medicine', 1, 200.00, 200.00, '2026-10-08 14:21:46'),
(26, 5, 'Blood Pressure Monitoring (1 week)', 'Service', 1, 400.00, 400.00, '2026-10-08 14:21:46'),
(27, 6, 'Emergency Consultation', 'Consultation', 1, 800.00, 800.00, '2026-10-08 14:21:46'),
(28, 6, 'Appendectomy (Surgery Fee)', 'Procedure', 1, 8000.00, 8000.00, '2026-10-08 14:21:46'),
(29, 6, 'Operating Room Fee', 'Procedure', 1, 2500.00, 2500.00, '2026-10-08 14:21:46'),
(30, 6, 'Anesthesia Fee', 'Procedure', 1, 1500.00, 1500.00, '2026-10-08 14:21:46'),
(31, 6, 'Hospital Room (3 days)', 'Accommodation', 3, 400.00, 1200.00, '2026-10-08 14:21:46'),
(32, 6, 'Post-op Medications', 'Medicine', 1, 1000.00, 1000.00, '2026-10-08 14:21:46'),
(33, 7, 'General Consultation', 'Consultation', 1, 500.00, 500.00, '2026-10-08 14:21:46'),
(34, 7, 'CBC with Differential', 'Laboratory', 1, 350.00, 350.00, '2026-10-08 14:21:46'),
(35, 7, 'Serum Iron Test', 'Laboratory', 1, 150.00, 150.00, '2026-10-08 14:21:46'),
(36, 7, 'Ferrous Sulfate 325mg (30 tablets)', 'Medicine', 1, 100.00, 100.00, '2026-10-08 14:21:46'),
(37, 8, 'Pediatric Well-Child Check-up', 'Consultation', 1, 400.00, 400.00, '2026-10-08 14:21:46'),
(38, 8, 'MMR Vaccine', 'Vaccination', 1, 250.00, 250.00, '2026-10-08 14:21:46'),
(39, 8, 'Varicella Vaccine', 'Vaccination', 1, 150.00, 150.00, '2026-10-08 14:21:46'),
(40, 9, 'Rheumatology Consultation', 'Consultation', 1, 600.00, 600.00, '2026-10-08 14:21:46'),
(41, 9, 'ESR Test', 'Laboratory', 1, 200.00, 200.00, '2026-10-08 14:21:46'),
(42, 9, 'Rheumatoid Factor Test', 'Laboratory', 1, 350.00, 350.00, '2026-10-08 14:21:46'),
(43, 9, 'X-ray Both Hands', 'Radiology', 1, 600.00, 600.00, '2026-10-08 14:21:46'),
(44, 9, 'Methotrexate 7.5mg (4 tablets)', 'Medicine', 1, 250.00, 250.00, '2026-10-08 14:21:46'),
(45, 9, 'Meloxicam 15mg (30 tablets)', 'Medicine', 1, 300.00, 300.00, '2026-10-08 14:21:46'),
(46, 9, 'Folic Acid 1mg (30 tablets)', 'Medicine', 1, 200.00, 200.00, '2026-10-08 14:21:46'),
(47, 10, 'General Consultation', 'Consultation', 1, 500.00, 500.00, '2026-10-08 14:21:46'),
(48, 10, 'Stool Exam', 'Laboratory', 1, 150.00, 150.00, '2026-10-08 14:21:46'),
(49, 10, 'Abdominal Ultrasound', 'Radiology', 1, 450.00, 450.00, '2026-10-08 14:21:46'),
(50, 10, 'Hyoscine Butylbromide (30 tablets)', 'Medicine', 1, 200.00, 200.00, '2026-10-08 14:21:46'),
(51, 11, 'htht', 'Consultation', 1, 500.00, 500.00, '2026-10-10 12:19:39');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `head_doctor` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `description`, `head_doctor`, `contact_number`, `location`, `status`, `created_at`, `updated_at`) VALUES
(1, 'General Medicine', 'Handles general health consultations and primary care for all age groups.', 'Dr. Maria Garcia', '09171234567', 'Building A, Ground Floor', 'active', '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(2, 'Pediatrics', 'Specialized medical care for infants, children, and adolescents.', 'Dr. Jose Reyes', '09181234568', 'Building B, 2nd Floor', 'active', '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(3, 'Emergency', 'Provides immediate treatment for acute illnesses and injuries.', 'Dr. Ana Lim', '09191234569', 'Building A, Emergency Wing', 'active', '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(4, 'Laboratory', 'Diagnostic laboratory services including blood tests and urinalysis.', 'Dr. Mark Torres', '09201234570', 'Building C, Ground Floor', 'active', '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(5, 'Pharmacy', 'Dispensing of prescription medications and health products.', 'Pharmacist Rosa Dela Cruz', '09211234571', 'Building A, Pharmacy Counter', 'active', '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(6, 'Radiology', 'X-ray, ultrasound, and other imaging services.', 'Dr. Carl Bautista', '09221234572', 'Building C, 1st Floor', 'active', '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(7, 'Dental', 'Dental care including cleaning, extraction, and orthodontics.', 'Dr. Lisa Navarro', '09231234573', 'Building B, Ground Floor', 'active', '2026-10-08 14:21:46', '2026-10-08 14:21:46');

-- --------------------------------------------------------

--
-- Table structure for table `email_verifications`
--

CREATE TABLE `email_verifications` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `code` varchar(10) NOT NULL,
  `payload` text DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medical_records`
--

CREATE TABLE `medical_records` (
  `id` int(11) NOT NULL,
  `record_id` varchar(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `record_date` date NOT NULL,
  `attending_staff` int(11) DEFAULT NULL,
  `chief_complaint` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment` text DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_records`
--

INSERT INTO `medical_records` (`id`, `record_id`, `patient_id`, `record_date`, `attending_staff`, `chief_complaint`, `diagnosis`, `treatment`, `prescription`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'MR-0001', 1, '2026-10-08', 2, 'Persistent headache and dizziness', 'Hypertension Stage 1', 'Lifestyle modification, low-sodium diet, regular exercise', 'Amlodipine 5mg once daily, Losartan 50mg once daily', 'Patient advised to monitor blood pressure daily and return in 2 weeks', '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(2, 'MR-0002', 2, '2026-10-08', 4, 'Fever for 3 days, loss of appetite', 'Viral Upper Respiratory Tract Infection', 'Rest, adequate fluid intake, symptomatic treatment', 'Paracetamol 500mg every 4-6 hours, Vitamin C 500mg once daily', 'Patient to return if fever persists beyond 5 days', '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(3, 'MR-0003', 3, '2026-09-20', 2, 'Cough with phlegm for 2 weeks', 'Community-Acquired Pneumonia', 'Chest X-ray, antibiotics, chest physiotherapy', 'Amoxicillin 500mg three times daily for 7 days, Carbocisteine 500mg three times daily', 'Follow-up in 1 week for repeat chest X-ray', '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(4, 'MR-0004', 4, '2026-08-15', 4, 'Toothache and gum swelling', 'Dental Caries with Periapical Abscess', 'Tooth extraction of affected tooth, irrigation', 'Amoxicillin 500mg three times daily for 5 days, Ibuprofen 400mg every 8 hours', 'Patient instructed on proper oral hygiene', '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(5, 'MR-0005', 5, '2026-07-10', 2, 'Chest pain and shortness of breath on exertion', 'Hypertensive Heart Disease', 'ECG, echocardiography referral, lifestyle modification', 'Metoprolol 50mg once daily, Furosemide 40mg once daily', 'Referred to cardiologist for further evaluation', '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(6, 'MR-0006', 6, '2026-06-25', 4, 'Severe abdominal pain, right lower quadrant', 'Appendicitis', 'Emergency appendectomy, post-op care', 'Ceftriaxone 1g IV every 12 hours, Metronidazole 500mg IV every 8 hours', 'Post-op recovery excellent, discharged after 3 days', '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(7, 'MR-0007', 7, '2026-05-18', 2, 'Fatigue, pale skin, frequent headaches', 'Iron Deficiency Anemia', 'Dietary modification, iron supplementation', 'Ferrous Sulfate 325mg twice daily, Vitamin C 500mg once daily', 'CBC repeat in 1 month', '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(8, 'MR-0008', 8, '2026-04-18', 4, 'Routine well-child check-up, scheduled vaccines', 'Normal growth and development', 'Vaccination administered (MMR, Varicella)', 'Multivitamins as directed', 'Next visit in 6 months', '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(9, 'MR-0009', 9, '2026-05-07', 2, 'Joint pain and stiffness in knees and hands', 'Rheumatoid Arthritis', 'Physical therapy referral, anti-inflammatory treatment', 'Methotrexate 7.5mg weekly, Folic Acid 1mg daily, Meloxicam 15mg once daily', 'Monitor liver function test monthly', '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(10, 'MR-0010', 10, '2026-05-22', 4, 'Abdominal discomfort, bloating, irregular bowel movements', 'Irritable Bowel Syndrome', 'Dietary counseling, stress management', 'Hyoscine Butylbromide 10mg three times daily, Psyllium fiber supplement', 'Follow-up in 4 weeks', '2026-10-08 14:21:46', '2026-10-08 14:21:46');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `patient_id` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_number` varchar(20) DEFAULT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') DEFAULT 'Unknown',
  `status` enum('Active','Inactive','Discharged') NOT NULL DEFAULT 'Active',
  `registration_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `patient_id`, `first_name`, `middle_name`, `last_name`, `date_of_birth`, `gender`, `contact_number`, `email`, `address`, `emergency_contact_name`, `emergency_contact_number`, `blood_type`, `status`, `registration_date`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'PT-0001', 'Juan', 'Santos', 'Dela Cruz', '1985-03-15', 'Male', '09151111001', 'juan.dc@email.com', '123 Rizal Street, Gerona, Tarlac', 'Maria Dela Cruz', '09151111002', 'O+', 'Active', '2026-01-10', NULL, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(2, 'PT-0002', 'Maria', 'Reyes', 'Garcia', '1990-07-22', 'Female', '09152222001', 'maria.g@email.com', '456 Mabini Avenue, Gerona, Tarlac', 'Pedro Garcia', '09152222002', 'A+', 'Active', '2026-01-15', NULL, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(3, 'PT-0003', 'Pedro', 'Cruz', 'Bautista', '1975-11-08', 'Male', '09153333001', 'pedro.b@email.com', '789 Bonifacio Road, Gerona, Tarlac', 'Ana Bautista', '09153333002', 'B+', 'Active', '2026-02-01', NULL, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(4, 'PT-0004', 'Ana', 'Lopez', 'Torres', '2000-05-30', 'Female', '09154444001', 'ana.t@email.com', '321 Luna Street, Gerona, Tarlac', 'Jose Torres', '09154444002', 'AB+', 'Active', '2026-02-14', NULL, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(5, 'PT-0005', 'Carlos', 'Reyes', 'Mendoza', '1960-09-12', 'Male', '09155555001', 'carlos.m@email.com', '654 Aguinaldo Drive, Gerona, Tarlac', 'Rosa Mendoza', '09155555002', 'O-', 'Active', '2026-03-05', NULL, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(6, 'PT-0006', 'Rosa', 'Santos', 'Navarro', '1995-01-25', 'Female', '09156666001', 'rosa.n@email.com', '987 Quezon Boulevard, Gerona, Tarlac', 'Miguel Navarro', '09156666002', 'A-', 'Active', '2026-03-20', NULL, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(7, 'PT-0007', 'Miguel', 'Cruz', 'Ramos', '1988-06-18', 'Male', '09157777001', 'miguel.r@email.com', '147 Magsaysay Street, Gerona, Tarlac', 'Lita Ramos', '09157777002', 'B-', 'Active', '2026-04-02', NULL, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(8, 'PT-0008', 'Lita', 'Reyes', 'Villanueva', '2010-12-03', 'Female', '09158888001', 'parent.v@email.com', '258 Marcos Highway, Gerona, Tarlac', 'John Villanueva', '09158888002', 'O+', 'Active', '2026-04-18', NULL, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(9, 'PT-0009', 'Ramon', 'Santos', 'Castillo', '1952-04-09', 'Male', '09159999001', 'ramon.c@email.com', '369 Jacinto Street, Gerona, Tarlac', 'Carmen Castillo', '09159999002', 'A+', 'Active', '2026-05-07', NULL, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(10, 'PT-0010', 'Carmen', 'Lopez', 'Flores', '1970-08-27', 'Female', '09150000001', 'carmen.f@email.com', '741 Lapu-Lapu Road, Gerona, Tarlac', 'Danny Flores', '09150000002', 'AB-', 'Active', '2026-05-22', NULL, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(11, 'PT-0011', 'Danny', 'Reyes', 'Pascual', '1982-02-14', 'Male', '09161111001', 'danny.p@email.com', '852 Sikatuna Street, Gerona, Tarlac', 'Nora Pascual', '09161111002', 'O+', 'Active', '2026-06-10', NULL, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(12, 'PT-0012', 'Nora', 'Cruz', 'Espiritu', '1998-10-05', 'Female', '09162222001', 'nora.e@email.com', '963 Tinio Avenue, Gerona, Tarlac', 'Mark Espiritu', '09162222002', 'B+', 'Active', '2026-07-01', NULL, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(13, 'PT-0013', 'Toffee', 'Santos', 'Dela Cruz', '2026-10-10', 'Other', '0912141211', 'akatsukiharold25@gmail.com', 'msaa', 'qeqeqe', '0921212121', 'O-', 'Active', '2026-10-10', NULL, '2026-10-10 11:20:26', '2026-10-10 11:20:26'),
(14, 'PT-0014', 'Toffee', 'Santos', 'Dela Cruz', '2026-10-01', 'Female', '0912141211', 'genjutsukaido25@gmail.com', 'msaa', 'qeqeqe', '0921212121', 'B+', 'Active', '2026-10-15', NULL, '2026-10-15 14:33:16', '2026-10-15 14:33:16');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `payment_id` varchar(20) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount_paid` decimal(12,2) NOT NULL,
  `payment_method` enum('Cash','GCash','Bank Transfer','Other') NOT NULL DEFAULT 'Cash',
  `reference_number` varchar(100) DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `payment_id`, `bill_id`, `patient_id`, `payment_date`, `amount_paid`, `payment_method`, `reference_number`, `received_by`, `notes`, `created_at`) VALUES
(1, 'PAY-0001', 1, 1, '2026-10-08', 1600.00, 'Cash', NULL, 3, 'Full payment received', '2026-10-08 14:21:46'),
(2, 'PAY-0002', 2, 2, '2026-10-08', 500.00, 'GCash', 'GC-2026100801', 3, 'Partial payment via GCash', '2026-10-08 14:21:46'),
(3, 'PAY-0003', 4, 4, '2026-08-15', 2200.00, 'Cash', NULL, 5, 'Full payment received', '2026-10-08 14:21:46'),
(4, 'PAY-0004', 5, 5, '2026-07-10', 2000.00, 'Bank Transfer', 'BT-20260710-001', 3, 'Partial bank transfer payment', '2026-10-08 14:21:46'),
(5, 'PAY-0005', 6, 6, '2026-06-25', 10000.00, 'Cash', NULL, 5, 'Initial partial payment for surgery', '2026-10-08 14:21:46'),
(6, 'PAY-0006', 6, 6, '2026-06-28', 5000.00, 'GCash', 'GC-2026062801', 3, 'Final payment to complete the bill', '2026-10-08 14:21:46'),
(7, 'PAY-0007', 8, 8, '2026-04-18', 800.00, 'Cash', NULL, 5, 'Full payment received', '2026-10-08 14:21:46'),
(8, 'PAY-0008', 9, 9, '2026-05-07', 1000.00, 'Cash', NULL, 3, 'Partial payment received', '2026-10-08 14:21:46'),
(9, 'PAY-0009', 11, 13, '2026-10-10', 500.00, 'GCash', '', 2, '0', '2026-10-10 12:22:56');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `role` enum('admin','staff','patient') NOT NULL DEFAULT 'staff',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `email_verified` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `patient_id`, `role`, `status`, `email_verified`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@geronhospital.com', NULL, 'admin', 'active', 1, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(2, 'drgarcia', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Maria Garcia', 'mgarcia@geronhospital.com', NULL, 'staff', 'active', 1, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(3, 'nurse_santos', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Nurse Ana Santos', 'asantos@geronhospital.com', NULL, 'staff', 'active', 1, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(4, 'drreyes', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Jose Reyes', 'jreyes@geronhospital.com', NULL, 'staff', 'active', 1, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(5, 'staff_cruz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Carlo Cruz', 'ccruz@geronhospital.com', NULL, 'staff', 'active', 1, '2026-10-08 14:21:46', '2026-10-08 14:21:46'),
(6, 'akatsukiharold25@gmail.com', '$2y$10$Jzb8gKass7A4Oo1Nt7F09.FjBQj.BSFl6LWGk/dqsjlgk1QX2sOJW', 'Toffee Dela Cruz', 'akatsukiharold25@gmail.com', 13, 'patient', 'active', 1, '2026-10-10 11:20:26', '2026-10-10 11:32:22'),
(7, 'genjutsukaido25@gmail.com', '$2y$10$EvxEYeR9bIy2.Ol56hmexetXAciVKYtRMTjMsp9UHfaWMgzPvdpSK', 'Toffee Dela Cruz', 'genjutsukaido25@gmail.com', 14, 'patient', 'active', 1, '2026-10-15 14:33:16', '2026-10-15 14:33:16');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `appointment_id` (`appointment_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `bills`
--
ALTER TABLE `bills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bill_id` (`bill_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `bill_items`
--
ALTER TABLE `bill_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bill_id` (`bill_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `code` (`code`);

--
-- Indexes for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `record_id` (`record_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `attending_staff` (`attending_staff`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `patient_id` (`patient_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_id` (`payment_id`),
  ADD KEY `bill_id` (`bill_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `received_by` (`received_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_users_patient` (`patient_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `bills`
--
ALTER TABLE `bills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `bill_items`
--
ALTER TABLE `bill_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `medical_records`
--
ALTER TABLE `medical_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bills`
--
ALTER TABLE `bills`
  ADD CONSTRAINT `bills_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bills_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bill_items`
--
ALTER TABLE `bill_items`
  ADD CONSTRAINT `bill_items_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD CONSTRAINT `medical_records_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medical_records_ibfk_2` FOREIGN KEY (`attending_staff`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
