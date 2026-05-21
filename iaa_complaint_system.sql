-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 21, 2026 at 07:33 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `iaa_complaint_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'fa-ticket-alt',
  `color` varchar(20) DEFAULT '#6c757d',
  `assigned_role` enum('hod','dean','accountant','director') DEFAULT 'hod',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `icon`, `color`, `assigned_role`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Academic', 'academic', 'Grades, assignments, lectures', 'fa-book', '#3498db', 'hod', 1, '2026-05-16 08:30:40', '2026-05-16 08:30:40'),
(2, 'Fees', 'fees', 'Tuition fee, payments, receipts', 'fa-money-bill', '#e74c3c', 'accountant', 1, '2026-05-16 08:30:40', '2026-05-16 08:30:40'),
(3, 'Accommodation', 'accommodation', 'Hostel, water, electricity', 'fa-home', '#2ecc71', 'dean', 1, '2026-05-16 08:30:40', '2026-05-16 08:30:40'),
(4, 'Cafeteria', 'cafeteria', 'Food quality, portion sizes', 'fa-utensils', '#f39c12', 'dean', 1, '2026-05-16 08:30:40', '2026-05-16 08:30:40'),
(5, 'Security', 'security', 'Campus safety, theft, harassment', 'fa-shield-alt', '#9b59b6', 'dean', 1, '2026-05-16 08:30:40', '2026-05-16 08:30:40'),
(6, 'Library', 'library', 'Book availability, fines', 'fa-book', '#1abc9c', 'hod', 1, '2026-05-16 08:30:40', '2026-05-16 08:30:40'),
(7, 'Sports', 'sports', 'Facilities, equipment, events', 'fa-futbol', '#e67e22', 'dean', 1, '2026-05-16 08:30:40', '2026-05-16 08:30:40'),
(8, 'Other', 'other', 'General suggestions', 'fa-question', '#95a5a6', 'hod', 1, '2026-05-16 08:30:40', '2026-05-16 08:30:40'),
(9, 'Infrastructure', 'infrastructure', 'Category for Infrastructure', 'fa-ticket-alt', '#6c757d', 'hod', 1, '2026-05-16 11:17:15', '2026-05-16 11:17:15'),
(10, 'Gender issue', 'gender-issue', 'Category for Gender issue', 'fa-ticket-alt', '#6c757d', 'hod', 1, '2026-05-16 11:59:59', '2026-05-16 11:59:59'),
(11, 'Service', 'service', 'Category for Service', 'fa-ticket-alt', '#6c757d', 'hod', 1, '2026-05-16 12:05:48', '2026-05-16 12:05:48'),
(12, 'Hostel', 'hostel', 'Category for Hostel', 'fa-ticket-alt', '#6c757d', 'hod', 1, '2026-05-16 12:14:39', '2026-05-16 12:14:39'),
(13, 'Students Government', 'students-government', 'Category for Students Government', 'fa-ticket-alt', '#6c757d', 'hod', 1, '2026-05-16 12:48:08', '2026-05-16 12:48:08'),
(15, 'Examination case', 'examination-case', 'Category for Examination case', 'fa-ticket-alt', '#6c757d', 'hod', 1, '2026-05-17 16:35:43', '2026-05-17 16:35:43'),
(16, 'Accountant', 'accountant', 'Category for Accountant', 'fa-ticket-alt', '#6c757d', 'hod', 1, '2026-05-17 17:07:09', '2026-05-17 17:07:09');

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `id` int(11) NOT NULL,
  `complaint_number` varchar(20) NOT NULL,
  `student_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `is_anonymous` tinyint(1) DEFAULT 0,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `attachment_path` varchar(500) DEFAULT NULL,
  `status` enum('pending','in_progress','resolved','closed','escalated','rejected') DEFAULT 'pending',
  `assigned_to` int(11) DEFAULT NULL,
  `escalated_to` int(11) DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `location` varchar(255) DEFAULT NULL,
  `incident_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `complaints`
--

INSERT INTO `complaints` (`id`, `complaint_number`, `student_id`, `category_id`, `priority`, `is_anonymous`, `title`, `description`, `attachment_path`, `status`, `assigned_to`, `escalated_to`, `rejected_by`, `rejection_reason`, `resolved_at`, `created_at`, `updated_at`, `location`, `incident_date`) VALUES
(1, 'CMP-2025-001', 8, 2, 'high', 0, 'Fees Overcharge of 50,000 TZS', 'I was charged extra 50,000 TZS this semester.', NULL, 'resolved', 4, NULL, NULL, NULL, NULL, '2025-01-10 06:30:00', '2026-05-18 17:20:37', NULL, NULL),
(2, 'CMP-2025-002', 9, 3, 'high', 0, 'No Water in Hostel Block B', 'Block B has had no water for 3 days.', NULL, 'in_progress', 3, NULL, NULL, NULL, NULL, '2025-01-12 11:20:00', '2026-05-16 08:30:40', NULL, NULL),
(3, '', 8, 1, 'medium', 0, 'fcfvcs', 'ascxzsxz', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, '2026-05-16 10:48:20', '2026-05-16 10:48:20', NULL, NULL),
(37, 'CMP-2026-0001', 10, 11, 'high', 0, 'k', 'ab', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, '2026-05-16 12:05:48', '2026-05-16 12:05:48', '', '2026-05-23'),
(38, 'CMP-2026-0002', 10, 12, 'high', 0, 'twahaz', 'infom', 'uploads/complaints/complaint_10_1778933679.pdf', 'pending', NULL, NULL, NULL, NULL, NULL, '2026-05-16 12:14:39', '2026-05-16 12:14:39', 'ch3', '2026-05-30'),
(39, 'CMP-2026-0003', 10, 11, 'high', 0, 't', 'ds', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, '2026-05-16 12:22:42', '2026-05-16 12:22:42', 'dsa', '2026-05-29'),
(40, 'CMP-2026-0004', 9, 1, 'medium', 0, 'fc', 'wbvd', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, '2026-05-16 12:28:17', '2026-05-16 12:28:17', 'df', '2026-05-28'),
(41, 'CMP-2026-0005', 9, 13, 'high', 0, 'vija', 'e', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, '2026-05-16 12:48:08', '2026-05-16 12:48:08', 's', '2026-05-29'),
(42, 'CMP-2026-0006', 8, 9, 'high', 0, 'road', 'it defficult to pass', 'uploads/complaints/complaint_8_1779000397.jpg', 'pending', NULL, NULL, NULL, NULL, NULL, '2026-05-17 06:46:37', '2026-05-17 06:46:37', 'main gate', '2026-05-22'),
(43, 'CMP-2026-0007', 10, 8, 'low', 0, 'suma gaid', 'there are not responsible with there jutitie', 'uploads/complaints/complaint_10_1779006032.pdf', 'pending', NULL, NULL, NULL, NULL, NULL, '2026-05-17 08:20:32', '2026-05-17 08:20:32', 'getni dogo', '2026-05-10'),
(44, 'CMP-2026-0008', 8, 12, 'high', 0, 'kitanda kibovu', 'kitanda kilikatika jana usiku hivyo ni saidie kupata sehm ya kitanda chngne', NULL, 'pending', 3, NULL, NULL, NULL, NULL, '2026-05-17 09:16:36', '2026-05-17 09:16:36', 'new hostel', '2026-05-16'),
(45, 'CMP-2026-0009', 11, 10, 'low', 0, 'sexual harasmnet', 'there is teacher who theate me in sexual harasment', 'uploads/complaints/complaint_11_1779009587.jpg', 'resolved', 3, NULL, NULL, NULL, NULL, '2026-05-17 09:19:47', '2026-05-17 09:34:31', 'pgb1', '2026-05-23'),
(46, 'CMP-2026-0010', 11, 11, 'medium', 0, 'secretary prblem', 'secretary hawa wajibiki kweny mjukumu yao \r\nhii ni tatizo na hawatupi maelekezo mazuri', NULL, 'resolved', 3, NULL, NULL, NULL, NULL, '2026-05-17 10:02:50', '2026-05-17 10:08:24', 'admin block', '2026-05-15'),
(47, 'CMP-2026-0011', 12, 9, 'high', 0, 'madeski yamevunjika', 'jina uski katika mdarsa y ch4a kulikua na madeski ymejunjwa ila \r\nhajui nani kavunja hayo madeski\r\ntunaomba usidizi wako tutaue hii cganmoto\r\nmna tunapata tabu kutikana na madarsa kua kidogo mno \r\ntunaomba msaada wako \r\nmimi ni Cr kwa hyo nawajibika na mattizo ya wenzangu', 'uploads/complaints/complaint_12_1779012877.pdf', 'resolved', 3, NULL, NULL, NULL, NULL, '2026-05-17 10:14:37', '2026-05-17 10:17:42', 'nyangumi class', '2026-05-15'),
(48, 'CMP-2026-0012', 12, 11, 'high', 0, 'mwalima', 'anazingua', NULL, 'resolved', 3, NULL, NULL, NULL, NULL, '2026-05-17 10:20:25', '2026-05-17 10:35:07', 'nyangumi class', '2026-05-23'),
(49, 'CMP-2026-0013', 10, 9, 'high', 0, 'uhaba wa vibweta', 'kutokana na engezeko la vimbweta imesabbisha kukosa maneo ya kudiscuss especcially kwenye vibweta', NULL, 'resolved', 3, NULL, NULL, NULL, NULL, '2026-05-17 15:02:17', '2026-05-17 15:05:50', 'chuo kizima', '2026-05-17'),
(50, 'CMP-2026-0014', 8, 1, 'high', 0, 'mwalim hafundish', 'mwalim wa mathe hafundishi', NULL, 'escalated', 14, 22, NULL, NULL, NULL, '2026-05-17 15:50:09', '2026-05-20 17:39:17', 'ch3', '2026-05-16'),
(51, 'CMP-2026-0015', 12, 1, 'high', 0, 'distric mathe', 'teaching problem\r\nways of teaching', NULL, 'pending', 17, NULL, NULL, NULL, NULL, '2026-05-17 15:53:40', '2026-05-17 15:53:40', 'nyangumi class', '2026-05-16'),
(52, 'CMP-2026-0016', 10, 15, 'high', 0, 'skufanya mtihani', 'ue', NULL, 'escalated', 19, 22, NULL, NULL, NULL, '2026-05-17 16:35:43', '2026-05-20 18:31:37', 'ch5', '2026-05-20'),
(53, 'CMP-2026-0017', 10, 16, 'medium', 0, 'ada imezidi', 'nakidai chuo pesa yangu laki 5', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, '2026-05-17 17:07:09', '2026-05-17 17:07:09', '', '2026-05-11'),
(54, 'CMP-2026-0018', 9, 15, 'high', 0, 'next', 'malindi', NULL, 'resolved', 19, NULL, NULL, NULL, NULL, '2026-05-18 17:25:13', '2026-05-18 17:25:42', 'ch3', '2026-05-17'),
(55, 'CMP-2026-0019', 9, 13, 'medium', 0, 'president', 'wrong choice', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, '2026-05-18 17:46:01', '2026-05-18 17:46:01', 'ch3', '2026-05-18'),
(56, 'CMP-2026-0020', 9, 13, 'high', 0, 'waziri mkuu ajiuzulu', 'this must be deom', NULL, 'escalated', 21, 22, NULL, NULL, NULL, '2026-05-18 18:02:48', '2026-05-20 18:39:05', '', '2026-05-18'),
(57, 'CMP-2026-0021', 9, 11, 'medium', 0, 'lecture', 'hafundishi vizry', NULL, 'escalated', 23, NULL, NULL, NULL, NULL, '2026-05-18 18:41:30', '2026-05-18 18:47:44', 'ch3', '2026-05-17'),
(58, 'CMP-2026-0022', 9, 10, 'high', 0, 'missunderstand', 'problem', NULL, 'escalated', 22, NULL, NULL, NULL, NULL, '2026-05-18 18:51:53', '2026-05-18 18:53:32', '', '2026-05-17'),
(59, 'CMP-2026-0023', 8, 1, 'high', 0, 'total complaints', 'problem', NULL, 'escalated', 22, NULL, NULL, NULL, NULL, '2026-05-20 17:02:32', '2026-05-20 17:19:35', 'che4', '2026-05-20'),
(60, 'CMP-2026-0024', 10, 12, 'low', 0, 'hmna madirisha', 'yamevunjwa', NULL, 'escalated', 22, NULL, NULL, NULL, NULL, '2026-05-20 17:06:50', '2026-05-20 17:07:36', 'new hostel', '2026-05-20'),
(61, 'CMP-2026-0025', 10, 11, 'low', 0, 'internet', 'hakuna internet mdarsani', NULL, 'resolved', 22, NULL, NULL, NULL, NULL, '2026-05-20 17:09:07', '2026-05-20 17:11:13', 'madarsa yote', '2026-05-20'),
(62, 'CMP-2026-0026', 8, 1, 'medium', 0, 'rectorrr deputy', 'peleka kwa rector deputy', NULL, 'escalated', 23, NULL, NULL, NULL, NULL, '2026-05-20 17:35:18', '2026-05-21 05:40:11', '', '2026-05-20'),
(63, 'CMP-2026-0027', 8, 1, 'medium', 0, 'trial', 'mock', NULL, 'pending', 14, 22, NULL, NULL, NULL, '2026-05-20 18:00:45', '2026-05-20 19:36:24', '', '2026-05-20'),
(64, 'CMP-2026-0028', 12, 12, 'medium', 0, 'maji hayatoki', 'shida ipo kwenye pipi line na mifereji ya chooni', NULL, 'escalated', 3, 22, NULL, NULL, NULL, '2026-05-20 18:16:09', '2026-05-21 06:04:57', '', '2026-05-20'),
(65, 'CMP-2026-0029', 12, 15, 'medium', 0, 'examm', 'examm', NULL, 'escalated', 19, NULL, NULL, NULL, NULL, '2026-05-20 18:19:23', '2026-05-20 18:23:10', '', '2026-05-20'),
(66, 'CMP-2026-0030', 12, 1, 'medium', 0, 'new test rec', 'test', NULL, 'pending', 17, NULL, NULL, NULL, NULL, '2026-05-20 19:18:33', '2026-05-20 19:18:33', '', '2026-05-20'),
(67, 'CMP-2026-0031', 11, 1, 'medium', 0, 'test mpya', 'mpya', NULL, 'escalated', 23, NULL, NULL, NULL, NULL, '2026-05-20 19:19:49', '2026-05-21 09:00:26', '', '2026-05-21'),
(68, 'CMP-2026-0032', 11, 1, 'medium', 0, 'another', 'one', NULL, 'resolved', 23, NULL, NULL, NULL, NULL, '2026-05-20 19:33:31', '2026-05-21 06:08:29', '', '2026-05-20'),
(69, 'CMP-2026-0033', 10, 11, 'medium', 0, 'last trial', 'trial me', NULL, 'resolved', 23, NULL, NULL, NULL, NULL, '2026-05-21 05:58:30', '2026-05-21 06:06:00', '', '2026-05-21');

--
-- Triggers `complaints`
--
DELIMITER $$
CREATE TRIGGER `before_complaint_insert` BEFORE INSERT ON `complaints` FOR EACH ROW BEGIN
    DECLARE next_num INT;
    DECLARE year_part VARCHAR(4);
    SET year_part = YEAR(NOW());
    SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(complaint_number, '-', -1) AS UNSIGNED)), 0) + 1
    INTO next_num
    FROM complaints
    WHERE complaint_number LIKE CONCAT('CMP-', year_part, '-%');
    SET NEW.complaint_number = CONCAT('CMP-', year_part, '-', LPAD(next_num, 4, '0'));
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_attachments`
--

CREATE TABLE `complaint_attachments` (
  `id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_logs`
--

CREATE TABLE `complaint_logs` (
  `id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `hod_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `code`, `description`, `hod_id`, `created_at`, `updated_at`) VALUES
(1, 'Department of Finance and Accountancy', 'FIN', 'Finance, Accountancy, Banking, Insurance, Auditing, Taxation', 13, '2026-05-16 09:15:47', '2026-05-16 09:18:45'),
(2, 'Department of Informatics', 'INF', 'Computer Science, IT, Networking, Cyber Security, Multimedia, Library', 14, '2026-05-16 09:15:47', '2026-05-16 09:18:45'),
(3, 'Department of Governance and Security Studies', 'GOV', 'Governance, Security Studies, Peace and Security, Leadership', 15, '2026-05-16 09:15:47', '2026-05-16 09:18:45'),
(4, 'Department of Humanities and Social Sciences', 'HUM', 'Economics, Education, Natural Resources, Social Sciences', 16, '2026-05-16 09:15:47', '2026-05-16 09:18:45'),
(5, 'Department of Business Management', 'BUS', 'Business Management, HR, Marketing, Procurement, Tourism, Agriculture', 17, '2026-05-16 09:15:47', '2026-05-16 09:18:45');

-- --------------------------------------------------------

--
-- Table structure for table `escalation_rules`
--

CREATE TABLE `escalation_rules` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `hours_to_escalate` int(11) NOT NULL DEFAULT 48,
  `from_role` enum('hod','dean','accountant') NOT NULL,
  `to_role` enum('dean','director','director') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `escalation_rules`
--

INSERT INTO `escalation_rules` (`id`, `category_id`, `hours_to_escalate`, `from_role`, `to_role`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 48, 'hod', 'dean', 1, '2026-05-16 08:30:40', '2026-05-16 08:30:40'),
(2, 2, 48, 'accountant', 'director', 1, '2026-05-16 08:30:40', '2026-05-16 08:30:40'),
(3, 3, 24, 'hod', 'dean', 1, '2026-05-16 08:30:40', '2026-05-16 08:30:40'),
(4, 4, 24, 'hod', 'dean', 1, '2026-05-16 08:30:40', '2026-05-16 08:30:40'),
(5, 5, 12, 'hod', 'director', 1, '2026-05-16 08:30:40', '2026-05-16 08:30:40'),
(6, 6, 72, 'hod', 'dean', 1, '2026-05-16 08:30:40', '2026-05-16 08:30:40'),
(7, 7, 48, 'hod', 'dean', 1, '2026-05-16 08:30:40', '2026-05-16 08:30:40');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `complaint_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `type` enum('new_complaint','reply','escalation','reminder','resolution','assignment','rejection') NOT NULL,
  `sent_via_sms` tinyint(1) DEFAULT 0,
  `sent_via_email` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ratings`
--

CREATE TABLE `ratings` (
  `id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `rating_score` int(11) NOT NULL CHECK (`rating_score` between 1 and 5),
  `feedback` text DEFAULT NULL,
  `resolved_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ratings`
--

INSERT INTO `ratings` (`id`, `complaint_id`, `rating_score`, `feedback`, `resolved_by`, `created_at`) VALUES
(1, 45, 1, 'good', 1, '2026-05-17 10:00:20'),
(2, 46, 5, 'very good', 1, '2026-05-17 10:09:08'),
(3, 47, 4, 'good', 1, '2026-05-17 10:18:04'),
(4, 48, 3, 'well', 1, '2026-05-17 10:35:22'),
(5, 49, 1, 'low', 1, '2026-05-17 15:06:07'),
(6, 69, 5, 'exclent', 1, '2026-05-21 06:06:56'),
(7, 61, 5, '', 1, '2026-05-21 06:07:19');

-- --------------------------------------------------------

--
-- Table structure for table `responses`
--

CREATE TABLE `responses` (
  `id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `attachment_path` varchar(500) DEFAULT NULL,
  `is_internal_note` tinyint(1) DEFAULT 0,
  `is_system_message` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `responses`
--

INSERT INTO `responses` (`id`, `complaint_id`, `user_id`, `message`, `attachment_path`, `is_internal_note`, `is_system_message`, `created_at`) VALUES
(1, 42, 8, 'okay', NULL, 0, 0, '2026-05-17 07:35:22'),
(2, 45, 3, 'this problem is under investigation so please wait for respond', NULL, 0, 0, '2026-05-17 09:21:50'),
(3, 45, 11, 'okay madam thanks you', NULL, 0, 0, '2026-05-17 09:22:57'),
(4, 46, 3, 'give the name of supervisor \r\nand the role in which office', NULL, 0, 0, '2026-05-17 10:05:06'),
(5, 46, 11, 'his name is Twaha Faki Yahya\r\nsecretary in bima office', NULL, 0, 0, '2026-05-17 10:07:02'),
(6, 47, 12, 'sir tunaomba usidizi wa hii changamoto', NULL, 0, 0, '2026-05-17 10:15:07'),
(7, 47, 3, 'usjali Cr kesho tutakuja kukagua', NULL, 0, 0, '2026-05-17 10:16:53'),
(8, 47, 12, 'thanks madam', NULL, 0, 0, '2026-05-17 10:17:20'),
(9, 47, 3, 'umeona majimbu', NULL, 0, 0, '2026-05-17 10:18:49'),
(10, 48, 12, 'mbona hamja soma walimu', NULL, 0, 0, '2026-05-17 10:20:51'),
(11, 48, 12, 'helloe teachrs', NULL, 0, 0, '2026-05-17 10:29:19'),
(12, 48, 3, 'okm on progress', NULL, 0, 0, '2026-05-17 10:29:49'),
(13, 49, 10, 'what should we do', NULL, 0, 0, '2026-05-17 15:04:47'),
(14, 64, 22, 'okay this will be handled soon inshallah', NULL, 0, 0, '2026-05-20 18:54:37'),
(15, 65, 12, 'hey', NULL, 0, 0, '2026-05-20 18:55:30'),
(16, 65, 12, 'hey', NULL, 0, 0, '2026-05-20 19:05:54'),
(17, 64, 22, 'finne', NULL, 0, 0, '2026-05-20 19:06:19'),
(18, 65, 12, 'hi', NULL, 0, 0, '2026-05-20 19:16:55'),
(19, 64, 22, 'swa', NULL, 0, 0, '2026-05-20 19:17:22'),
(20, 67, 22, 'let us see it', NULL, 0, 0, '2026-05-20 19:20:46'),
(21, 67, 11, 'ok sir thanks', NULL, 0, 0, '2026-05-20 19:21:55'),
(22, 68, 22, 'redirected', NULL, 0, 0, '2026-05-20 19:53:37'),
(23, 62, 22, 'rector anafuatilia', NULL, 0, 0, '2026-05-21 05:40:11'),
(24, 69, 3, 'okay wait now it going to deputy', NULL, 0, 0, '2026-05-21 05:59:41'),
(25, 69, 10, 'okay sir', NULL, 0, 0, '2026-05-21 06:00:09'),
(26, 69, 22, 'tatizo lako lipo chini ya uzo wangu\r\nnaliteleka kwa rector', NULL, 0, 0, '2026-05-21 06:02:26'),
(27, 69, 10, 'okay sir shurkan', NULL, 0, 0, '2026-05-21 06:03:48'),
(28, 69, 23, 'tatizo lako tumelitatua ipasavyo', NULL, 0, 0, '2026-05-21 06:06:00');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` text NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','number','boolean','json') DEFAULT 'text',
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_by`, `updated_at`) VALUES
(1, 'site_name', 'IAA Complaint Management System', 'text', 'Name of the system', NULL, '2026-05-16 08:30:40'),
(2, 'sla_hours', '48', 'number', 'Default SLA hours for response', NULL, '2026-05-16 08:30:40'),
(3, 'auto_escalation_enabled', 'true', 'boolean', 'Enable auto-escalation', NULL, '2026-05-16 08:30:40'),
(4, 'sms_enabled', 'false', 'boolean', 'Enable SMS notifications', NULL, '2026-05-16 08:30:40'),
(5, 'email_enabled', 'true', 'boolean', 'Enable email notifications', NULL, '2026-05-16 08:30:40'),
(6, 'maintenance_mode', 'false', 'boolean', 'Maintenance mode flag', NULL, '2026-05-16 08:30:40');

-- --------------------------------------------------------

--
-- Table structure for table `templates`
--

CREATE TABLE `templates` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `body` text NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `reg_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('student','hod','dean','accountant','director','admin','examination_officer','president','deputy_rector','rector') NOT NULL DEFAULT 'student',
  `department_id` int(11) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `year_of_study` int(11) DEFAULT NULL,
  `profile_picture` varchar(500) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `reg_number`, `email`, `password_hash`, `full_name`, `role`, `department_id`, `course`, `year_of_study`, `profile_picture`, `phone_number`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, NULL, 'admin@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', NULL, NULL, NULL, NULL, '0712345678', 1, '2026-05-16 08:34:05', '2026-05-16 08:30:40', '2026-05-16 08:34:05'),
(2, NULL, 'director@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. John Mwita', 'director', NULL, NULL, NULL, NULL, '0712345679', 1, NULL, '2026-05-16 08:30:40', '2026-05-16 08:30:40'),
(3, NULL, 'dean@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Prof. Sarah John', 'dean', NULL, NULL, NULL, NULL, '0712345680', 1, '2026-05-21 07:35:20', '2026-05-16 08:30:40', '2026-05-21 07:35:20'),
(4, NULL, 'accountant@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mr. James Peter', 'accountant', NULL, NULL, NULL, NULL, '0712345681', 1, '2026-05-18 17:19:42', '2026-05-16 08:30:40', '2026-05-18 17:19:42'),
(5, NULL, 'hod.cs@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Anna Mushi', 'hod', NULL, NULL, NULL, NULL, '0712345682', 1, NULL, '2026-05-16 08:30:40', '2026-05-16 09:14:38'),
(6, NULL, 'hod.ba@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Paul Lucas', 'hod', NULL, NULL, NULL, NULL, '0712345683', 1, NULL, '2026-05-16 08:30:40', '2026-05-16 09:14:38'),
(7, NULL, 'hod.af@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Prof. Grace Msangi', 'hod', NULL, NULL, NULL, NULL, '0712345684', 1, NULL, '2026-05-16 08:30:40', '2026-05-16 09:14:38'),
(8, 'IAA-2023-001', 'john.mbwana@students.iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Mbwana', 'student', 2, 'Computer Science', 3, NULL, '0755123456', 1, '2026-05-20 18:00:16', '2026-05-16 08:30:40', '2026-05-20 18:00:16'),
(9, 'IAA-2023-002', 'sarah.hassan@students.iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah Hassan', 'student', 5, 'Business Administration', 2, NULL, '0755123457', 1, '2026-05-18 18:45:16', '2026-05-16 08:30:40', '2026-05-18 18:45:16'),
(10, 'IAA-2023-003', 'juma.ali@students.iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juma Ali', 'student', 5, 'Accounting and Finance', 4, NULL, '0755123458', 1, '2026-05-21 06:06:33', '2026-05-16 08:30:40', '2026-05-21 06:06:33'),
(11, 'IAA-2024-001', 'amina.juma@students.iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Amina Juma', 'student', 2, 'Computer Science', 1, NULL, '0755123459', 1, '2026-05-20 19:21:38', '2026-05-16 08:30:40', '2026-05-20 19:21:38'),
(12, 'IAA-2024-002', 'rashid.mohamed@students.iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Rashid Mohamed', 'student', 5, 'Business Administration', 1, NULL, '0755123460', 1, '2026-05-21 09:02:36', '2026-05-16 08:30:40', '2026-05-21 09:02:36'),
(13, NULL, 'hod.finance@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. James Mwita', 'hod', 1, NULL, NULL, NULL, NULL, 1, '2026-05-16 12:26:10', '2026-05-16 09:06:23', '2026-05-16 12:26:10'),
(14, NULL, 'hod.informatics@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Prof. Anna Kisanga', 'hod', 2, NULL, NULL, NULL, NULL, 1, '2026-05-21 08:21:55', '2026-05-16 09:06:23', '2026-05-21 08:21:55'),
(15, NULL, 'hod.governance@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Hassan Juma', 'hod', 3, NULL, NULL, NULL, NULL, 1, NULL, '2026-05-16 09:06:23', '2026-05-16 09:18:45'),
(16, NULL, 'hod.humanities@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Prof. Neema Lucas', 'hod', 4, NULL, NULL, NULL, NULL, 1, NULL, '2026-05-16 09:06:23', '2026-05-16 09:18:45'),
(17, NULL, 'hod.business@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Fatma Hassan', 'hod', 5, NULL, NULL, 'uploads/profiles/hod_17_1778924446.jpg', '0773344402', 1, '2026-05-17 15:54:41', '2026-05-16 09:06:23', '2026-05-17 15:54:41'),
(19, NULL, 'examination@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Peter Examination', 'examination_officer', NULL, NULL, NULL, NULL, NULL, 1, '2026-05-21 08:07:07', '2026-05-17 16:23:55', '2026-05-21 08:07:07'),
(20, NULL, 'iaaso@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mr. Student President', '', NULL, NULL, NULL, NULL, NULL, 1, '2026-05-21 08:11:32', '2026-05-18 17:40:44', '2026-05-21 08:11:32'),
(21, NULL, 'president@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Hon. John Student', 'president', NULL, NULL, NULL, NULL, NULL, 1, '2026-05-21 08:11:52', '2026-05-18 17:56:57', '2026-05-21 08:11:52'),
(22, NULL, 'deputy@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Deputy Rector', 'deputy_rector', NULL, NULL, NULL, NULL, NULL, 1, '2026-05-21 08:22:32', '2026-05-18 18:16:48', '2026-05-21 08:22:32'),
(23, NULL, 'rector@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Prof. Rector', 'rector', NULL, NULL, NULL, NULL, NULL, 1, '2026-05-21 05:04:13', '2026-05-18 18:16:48', '2026-05-21 05:04:13');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_table_record` (`table_name`,`record_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `complaint_number` (`complaint_number`),
  ADD KEY `escalated_to` (`escalated_to`),
  ADD KEY `rejected_by` (`rejected_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_assigned_to` (`assigned_to`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_category_id` (`category_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `complaint_attachments`
--
ALTER TABLE `complaint_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `complaint_id` (`complaint_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `complaint_logs`
--
ALTER TABLE `complaint_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `complaint_id` (`complaint_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `escalation_rules`
--
ALTER TABLE `escalation_rules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_rule` (`category_id`,`from_role`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `complaint_id` (`complaint_id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `ratings`
--
ALTER TABLE `ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `complaint_id` (`complaint_id`),
  ADD KEY `resolved_by` (`resolved_by`);

--
-- Indexes for table `responses`
--
ALTER TABLE `responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_complaint_id` (`complaint_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_last_activity` (`last_activity`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `templates`
--
ALTER TABLE `templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `reg_number` (`reg_number`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_reg_number` (`reg_number`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `complaint_attachments`
--
ALTER TABLE `complaint_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_logs`
--
ALTER TABLE `complaint_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `escalation_rules`
--
ALTER TABLE `escalation_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ratings`
--
ALTER TABLE `ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `responses`
--
ALTER TABLE `responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `templates`
--
ALTER TABLE `templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `complaints_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `complaints_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `complaints_ibfk_4` FOREIGN KEY (`escalated_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `complaints_ibfk_5` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `complaint_attachments`
--
ALTER TABLE `complaint_attachments`
  ADD CONSTRAINT `complaint_attachments_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `complaint_attachments_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `complaint_logs`
--
ALTER TABLE `complaint_logs`
  ADD CONSTRAINT `complaint_logs_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `complaint_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `escalation_rules`
--
ALTER TABLE `escalation_rules`
  ADD CONSTRAINT `escalation_rules_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ratings`
--
ALTER TABLE `ratings`
  ADD CONSTRAINT `ratings_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ratings_ibfk_2` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `responses`
--
ALTER TABLE `responses`
  ADD CONSTRAINT `responses_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `responses_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `templates`
--
ALTER TABLE `templates`
  ADD CONSTRAINT `templates_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `templates_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
