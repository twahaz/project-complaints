-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 21, 2026 at 12:16 PM
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
-- Database: `iaa_complaint_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_years`
--

CREATE TABLE `academic_years` (
  `id` int(11) NOT NULL,
  `year_name` varchar(20) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_years`
--

INSERT INTO `academic_years` (`id`, `year_name`, `start_date`, `end_date`, `is_current`, `is_active`, `created_at`) VALUES
(1, '2023/2024', '2023-09-01', '2024-08-31', 0, 1, '2026-06-14 15:24:22'),
(2, '2024/2025', '2024-09-01', '2025-08-31', 1, 1, '2026-06-14 15:24:22'),
(3, '2025/2026', '2025-09-01', '2026-08-31', 0, 1, '2026-06-14 15:24:22');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `target_type` enum('all','students','staff','department','individual') DEFAULT 'all',
  `target_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `message`, `target_type`, `target_id`, `created_by`, `is_active`, `expiry_date`, `created_at`, `updated_at`) VALUES
(1, 'Tarifa', 'muhimu kuhuzuria kikao cha egez', 'students', NULL, 1, 0, '2026-06-17', '2026-06-15 19:18:38', '2026-06-15 19:58:05'),
(2, 'ONYO', 'muhasibu anasema tuipe ada', 'department', 2, 1, 0, '2026-06-30', '2026-06-15 19:28:16', '2026-06-15 19:57:57'),
(3, 'TAHAZARI', 'mtihani itaza esho', 'all', NULL, 1, 1, NULL, '2026-06-15 20:08:01', '2026-06-15 20:08:01'),
(4, 'WITO', 'Wanafunzi wote wa third year mnatakiwa kufika katika kongamano la Sayansi na mimi lilianda liwa na EGA \r\nkuazia tareh 23 june saa 4 kamli asbh', 'students', NULL, 1, 1, NULL, '2026-06-15 20:29:23', '2026-06-15 20:29:23'),
(5, 'NEW', 'new massage arrive soon', 'students', NULL, 3, 1, NULL, '2026-06-15 20:46:05', '2026-06-15 20:46:05'),
(6, 'Last', 'Trial', 'all', NULL, 3, 1, NULL, '2026-06-16 15:39:59', '2026-06-16 15:39:59'),
(7, 'MUHASIBU', 'watu wenye changamoto wanione kesho saa 5', 'students', NULL, 4, 1, NULL, '2026-06-16 17:57:46', '2026-06-16 17:57:46'),
(8, 'Exermination', 'Ratiba ya exermnation tayri imetoka \r\ntembemlea moddle yako kuona', 'all', NULL, 19, 1, '2026-06-16', '2026-06-16 18:15:25', '2026-06-16 18:15:25'),
(9, 'IT Officer', 'mnakumbushwa kuactivate moodle zenu', 'students', NULL, 24, 1, NULL, '2026-06-16 18:30:19', '2026-06-16 18:30:19'),
(10, 'INFOMAtic', 'department ya infomatic mnakumbushwa kujaza form ya supervisor', 'department', 2, 14, 1, NULL, '2026-06-16 18:41:43', '2026-06-16 18:41:43'),
(11, 'DUPTY', 'take care', 'all', NULL, 22, 1, '2026-06-17', '2026-06-16 19:06:47', '2026-06-16 19:06:47'),
(12, 'TRY', 'try the one more', 'all', NULL, 22, 1, '2026-06-19', '2026-06-20 08:40:00', '2026-06-20 08:40:00'),
(13, 'TEST', 'now it giod', 'all', 0, 23, 1, '2026-06-27', '2026-06-20 09:05:27', '2026-06-20 09:05:27'),
(14, 'AMRI', 'sio ombi', 'department', 4, 1, 1, '2026-06-27', '2026-06-20 12:02:11', '2026-06-20 12:02:11'),
(15, 'Time', 'Time table is official out', 'students', NULL, 19, 1, '2026-07-04', '2026-06-20 12:32:22', '2026-06-20 12:32:22'),
(16, 'FRESHAZ', 'official trh 18/01/2027\r\ntutakua na freshaz yetu ya kwnza', 'students', NULL, 21, 1, '2026-07-20', '2026-06-20 12:57:24', '2026-06-20 12:57:24');

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
(16, 'Accountant', 'accountant', 'Category for Accountant', 'fa-ticket-alt', '#6c757d', 'hod', 1, '2026-05-17 17:07:09', '2026-05-17 17:07:09'),
(17, 'IT Support', 'it-support', 'Computer, network, software, internet, system problems', 'fa-laptop-code', '#17a2b8', '', 1, '2026-06-14 13:42:29', '2026-06-14 13:42:29');

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
  `escalated_by` int(11) DEFAULT NULL,
  `escalated_at` timestamp NULL DEFAULT NULL,
  `escalated_reason` text DEFAULT NULL,
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

INSERT INTO `complaints` (`id`, `complaint_number`, `student_id`, `category_id`, `priority`, `is_anonymous`, `title`, `description`, `attachment_path`, `status`, `assigned_to`, `escalated_by`, `escalated_at`, `escalated_reason`, `escalated_to`, `rejected_by`, `rejection_reason`, `resolved_at`, `created_at`, `updated_at`, `location`, `incident_date`) VALUES
(1, 'CMP-2025-001', 8, 9, 'high', 0, 'Fees Overcharge of 50,000 TZS', 'I was charged extra 50,000 TZS this semester.', NULL, 'resolved', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-01-10 06:30:00', '2026-06-19 07:14:17', NULL, NULL),
(2, 'CMP-2025-002', 9, 9, 'high', 0, 'No Water in Hostel Block B', 'Block B has had no water for 3 days.', NULL, 'in_progress', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-01-12 11:20:00', '2026-06-19 07:14:17', NULL, NULL),
(3, '', 8, 1, 'medium', 0, 'fcfvcs', 'ascxzsxz', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-16 10:48:20', '2026-05-16 10:48:20', NULL, NULL),
(37, 'CMP-2026-0001', 10, 11, 'high', 0, 'k', 'ab', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-16 12:05:48', '2026-05-16 12:05:48', '', '2026-05-23'),
(38, 'CMP-2026-0002', 10, 12, 'high', 0, 'twahaz', 'infom', 'uploads/complaints/complaint_10_1778933679.pdf', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-16 12:14:39', '2026-05-16 12:14:39', 'ch3', '2026-05-30'),
(39, 'CMP-2026-0003', 10, 11, 'high', 0, 't', 'ds', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-16 12:22:42', '2026-05-16 12:22:42', 'dsa', '2026-05-29'),
(40, 'CMP-2026-0004', 9, 1, 'medium', 0, 'fc', 'wbvd', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-16 12:28:17', '2026-05-16 12:28:17', 'df', '2026-05-28'),
(41, 'CMP-2026-0005', 9, 13, 'high', 0, 'vija', 'e', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-16 12:48:08', '2026-05-16 12:48:08', 's', '2026-05-29'),
(42, 'CMP-2026-0006', 8, 9, 'high', 0, 'road', 'it defficult to pass', 'uploads/complaints/complaint_8_1779000397.jpg', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-17 06:46:37', '2026-05-17 06:46:37', 'main gate', '2026-05-22'),
(43, 'CMP-2026-0007', 10, 9, 'low', 0, 'suma gaid', 'there are not responsible with there jutitie', 'uploads/complaints/complaint_10_1779006032.pdf', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-17 08:20:32', '2026-06-19 07:14:17', 'getni dogo', '2026-05-10'),
(44, 'CMP-2026-0008', 8, 12, 'high', 0, 'kitanda kibovu', 'kitanda kilikatika jana usiku hivyo ni saidie kupata sehm ya kitanda chngne', NULL, 'resolved', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-17 09:16:36', '2026-06-20 10:11:02', 'new hostel', '2026-05-16'),
(45, 'CMP-2026-0009', 11, 10, 'low', 0, 'sexual harasmnet', 'there is teacher who theate me in sexual harasment', 'uploads/complaints/complaint_11_1779009587.jpg', 'resolved', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-17 09:19:47', '2026-05-17 09:34:31', 'pgb1', '2026-05-23'),
(46, 'CMP-2026-0010', 11, 11, 'medium', 0, 'secretary prblem', 'secretary hawa wajibiki kweny mjukumu yao \r\nhii ni tatizo na hawatupi maelekezo mazuri', NULL, 'resolved', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-17 10:02:50', '2026-05-17 10:08:24', 'admin block', '2026-05-15'),
(47, 'CMP-2026-0011', 12, 9, 'high', 0, 'madeski yamevunjika', 'jina uski katika mdarsa y ch4a kulikua na madeski ymejunjwa ila \r\nhajui nani kavunja hayo madeski\r\ntunaomba usidizi wako tutaue hii cganmoto\r\nmna tunapata tabu kutikana na madarsa kua kidogo mno \r\ntunaomba msaada wako \r\nmimi ni Cr kwa hyo nawajibika na mattizo ya wenzangu', 'uploads/complaints/complaint_12_1779012877.pdf', 'resolved', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-17 10:14:37', '2026-05-17 10:17:42', 'nyangumi class', '2026-05-15'),
(48, 'CMP-2026-0012', 12, 11, 'high', 0, 'mwalima', 'anazingua', NULL, 'resolved', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-17 10:20:25', '2026-05-17 10:35:07', 'nyangumi class', '2026-05-23'),
(49, 'CMP-2026-0013', 10, 9, 'high', 0, 'uhaba wa vibweta', 'kutokana na engezeko la vimbweta imesabbisha kukosa maneo ya kudiscuss especcially kwenye vibweta', NULL, 'resolved', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-17 15:02:17', '2026-05-17 15:05:50', 'chuo kizima', '2026-05-17'),
(50, 'CMP-2026-0014', 8, 1, 'high', 0, 'mwalim hafundish', 'mwalim wa mathe hafundishi', NULL, 'escalated', 14, NULL, NULL, NULL, 22, NULL, NULL, NULL, '2026-05-17 15:50:09', '2026-05-20 17:39:17', 'ch3', '2026-05-16'),
(51, 'CMP-2026-0015', 12, 1, 'high', 0, 'distric mathe', 'teaching problem\r\nways of teaching', NULL, 'pending', 17, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-17 15:53:40', '2026-05-17 15:53:40', 'nyangumi class', '2026-05-16'),
(52, 'CMP-2026-0016', 10, 15, 'high', 0, 'skufanya mtihani', 'ue', NULL, 'escalated', 19, NULL, NULL, NULL, 22, NULL, NULL, NULL, '2026-05-17 16:35:43', '2026-05-20 18:31:37', 'ch5', '2026-05-20'),
(53, 'CMP-2026-0017', 10, 16, 'medium', 0, 'ada imezidi', 'nakidai chuo pesa yangu laki 5', NULL, 'pending', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-17 17:07:09', '2026-06-14 09:32:36', '', '2026-05-11'),
(54, 'CMP-2026-0018', 9, 15, 'high', 0, 'next', 'malindi', NULL, 'resolved', 19, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-18 17:25:13', '2026-05-18 17:25:42', 'ch3', '2026-05-17'),
(55, 'CMP-2026-0019', 9, 13, 'medium', 0, 'president', 'wrong choice', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-18 17:46:01', '2026-05-18 17:46:01', 'ch3', '2026-05-18'),
(56, 'CMP-2026-0020', 9, 13, 'high', 0, 'waziri mkuu ajiuzulu', 'this must be deom', NULL, 'escalated', 21, NULL, NULL, NULL, 22, NULL, NULL, NULL, '2026-05-18 18:02:48', '2026-05-20 18:39:05', '', '2026-05-18'),
(57, 'CMP-2026-0021', 9, 11, 'medium', 0, 'lecture', 'hafundishi vizry', NULL, 'escalated', 23, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-18 18:41:30', '2026-05-18 18:47:44', 'ch3', '2026-05-17'),
(58, 'CMP-2026-0022', 9, 10, 'high', 0, 'missunderstand', 'problem', NULL, 'escalated', 22, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-18 18:51:53', '2026-05-18 18:53:32', '', '2026-05-17'),
(59, 'CMP-2026-0023', 8, 1, 'high', 0, 'total complaints', 'problem', NULL, 'escalated', 22, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-20 17:02:32', '2026-05-20 17:19:35', 'che4', '2026-05-20'),
(60, 'CMP-2026-0024', 10, 12, 'low', 0, 'hmna madirisha', 'yamevunjwa', NULL, 'escalated', 22, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-20 17:06:50', '2026-05-20 17:07:36', 'new hostel', '2026-05-20'),
(61, 'CMP-2026-0025', 10, 11, 'low', 0, 'internet', 'hakuna internet mdarsani', NULL, 'resolved', 22, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-20 17:09:07', '2026-05-20 17:11:13', 'madarsa yote', '2026-05-20'),
(62, 'CMP-2026-0026', 8, 1, 'medium', 0, 'rectorrr deputy', 'peleka kwa rector deputy', NULL, 'resolved', 23, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-20 17:35:18', '2026-06-19 09:09:08', '', '2026-05-20'),
(63, 'CMP-2026-0027', 8, 1, 'medium', 0, 'trial', 'mock', NULL, 'resolved', 23, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-20 18:00:45', '2026-06-19 06:27:48', '', '2026-05-20'),
(64, 'CMP-2026-0028', 12, 12, 'medium', 0, 'maji hayatoki', 'shida ipo kwenye pipi line na mifereji ya chooni', NULL, 'escalated', 3, NULL, NULL, NULL, 22, NULL, NULL, NULL, '2026-05-20 18:16:09', '2026-05-21 06:04:57', '', '2026-05-20'),
(65, 'CMP-2026-0029', 12, 15, 'medium', 0, 'examm', 'examm', NULL, 'escalated', 19, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-20 18:19:23', '2026-05-20 18:23:10', '', '2026-05-20'),
(66, 'CMP-2026-0030', 12, 1, 'medium', 0, 'new test rec', 'test', NULL, 'pending', 17, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-20 19:18:33', '2026-05-20 19:18:33', '', '2026-05-20'),
(67, 'CMP-2026-0031', 11, 1, 'medium', 0, 'test mpya', 'mpya', NULL, 'resolved', 23, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-20 19:19:49', '2026-06-14 14:09:04', '', '2026-05-21'),
(68, 'CMP-2026-0032', 11, 1, 'medium', 0, 'another', 'one', NULL, 'resolved', 23, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-20 19:33:31', '2026-05-21 06:08:29', '', '2026-05-20'),
(69, 'CMP-2026-0033', 10, 11, 'medium', 0, 'last trial', 'trial me', NULL, 'resolved', 23, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-21 05:58:30', '2026-05-21 06:06:00', '', '2026-05-21'),
(70, 'CMP-2026-0034', 8, 16, 'medium', 0, 'watu wewee', 'watu wowoooo', NULL, 'pending', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-13 18:08:59', '2026-06-14 09:32:36', '', '2026-06-12'),
(71, 'CMP-2026-0035', 8, 16, 'medium', 0, 'test', 'now', NULL, 'pending', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-13 18:11:14', '2026-06-14 09:32:36', 'sd', '2026-06-12'),
(72, 'CMP-2026-0036', 8, 12, 'medium', 0, 'test-hostel', 'test again', NULL, 'resolved', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-13 18:13:57', '2026-06-20 10:11:15', '23', '2026-06-12'),
(73, 'CMP-2026-0037', 8, 1, 'medium', 0, 'test-academic', 'now test', NULL, 'pending', 14, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-13 18:14:56', '2026-06-13 18:14:56', '', '2026-06-12'),
(74, 'CMP-2026-0038', 8, 11, 'medium', 0, 'test-serives', 'now', NULL, 'resolved', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-13 18:15:46', '2026-06-20 10:11:24', '', '2026-06-12'),
(75, 'CMP-2026-0039', 8, 10, 'medium', 0, 'test-gender', 'noew', NULL, 'resolved', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-13 18:16:31', '2026-06-20 10:11:36', '', '2026-06-12'),
(76, 'CMP-2026-0040', 8, 9, 'medium', 0, 'other test', 'nowq', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-13 18:17:11', '2026-06-19 07:14:17', '', '2026-06-12'),
(77, 'CMP-2026-0041', 8, 16, 'medium', 0, 'new', 'comp', NULL, 'pending', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-13 18:39:09', '2026-06-14 09:26:12', '', '2026-06-12'),
(78, 'CMP-2026-0042', 8, 10, 'medium', 0, 'dffd', 'ds', NULL, 'resolved', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-13 18:40:06', '2026-06-20 10:10:50', '', '2026-06-20'),
(79, 'CMP-2026-0043', 8, 16, 'medium', 0, 'sdc', 'scz', NULL, 'pending', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-13 18:41:04', '2026-06-14 09:26:12', '', '2026-06-12'),
(80, 'CMP-2026-0044', 8, 16, 'medium', 0, 'watu wewee', 'sdx', NULL, 'pending', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-13 18:43:02', '2026-06-14 09:26:12', '', '2026-06-12'),
(81, 'CMP-2026-0045', 8, 16, 'medium', 0, 'test-accnt', 'youi', NULL, 'pending', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-14 08:09:34', '2026-06-14 09:26:12', '', '2026-06-13'),
(82, 'CMP-2026-0046', 8, 16, 'medium', 0, 'yes it work', 'okay', NULL, 'pending', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-14 09:28:09', '2026-06-14 09:32:36', '', '2026-06-13'),
(83, 'CMP-2026-0047', 8, 16, 'medium', 0, 'noye', 'daya', NULL, 'pending', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-14 09:29:49', '2026-06-14 09:32:36', '', '2026-06-13'),
(84, 'CMP-2026-0048', 8, 16, 'medium', 0, 'nowa', 'dayz', NULL, 'pending', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-14 09:34:35', '2026-06-14 09:38:59', '', '2026-06-14'),
(85, 'CMP-2026-0049', 8, 16, 'medium', 0, 'text', 'tet', NULL, 'pending', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-14 09:37:32', '2026-06-14 09:38:59', '', '2026-06-13'),
(86, 'CMP-2026-0050', 8, 16, 'medium', 0, 'acn', 'nt', NULL, 'pending', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-14 09:39:52', '2026-06-14 09:44:22', '', '2026-06-13'),
(87, 'CMP-2026-0051', 8, 16, 'medium', 0, 'try', 'tyy', NULL, 'pending', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-14 09:43:29', '2026-06-14 09:44:22', '', '2026-06-13'),
(88, 'CMP-2026-0052', 8, 16, 'medium', 0, 'okam', 'fsd', NULL, 'pending', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-14 09:45:01', '2026-06-14 09:52:37', '', '2026-06-14'),
(89, 'CMP-2026-0053', 8, 16, 'medium', 0, 'fxc', 'dfc', NULL, 'resolved', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-14 09:51:40', '2026-06-19 08:23:34', '', '2026-06-13'),
(90, 'CMP-2026-0054', 8, 16, 'medium', 0, 'null', 'eoor', NULL, 'pending', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-14 09:54:15', '2026-06-14 13:25:55', '', '2026-06-13'),
(91, 'CMP-2026-0055', 8, 16, 'medium', 0, 'how', 'dsxz', NULL, 'pending', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-14 09:57:55', '2026-06-14 13:25:55', '', '2026-06-14'),
(92, 'CMP-2026-0056', 10, 16, 'medium', 0, 'me', 'hsazb', NULL, 'pending', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-14 13:11:06', '2026-06-14 13:25:55', '', '2026-06-13'),
(93, 'CMP-2026-0057', 10, 16, 'medium', 0, 'test', 'sd', NULL, 'pending', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-14 13:14:09', '2026-06-14 13:25:55', '', '2026-06-13'),
(94, 'CMP-2026-0058', 10, 16, 'medium', 0, 'test-academic', 'sas', NULL, 'pending', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-14 13:20:17', '2026-06-14 13:25:55', 'df', '2026-06-14'),
(95, 'CMP-2026-0059', 10, 16, 'medium', 0, 'j', 'j', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-14 13:26:48', '2026-06-14 13:26:48', '', '2026-06-13'),
(96, 'CMP-2026-0060', 10, 16, 'medium', 0, 'kiswa', 'hili', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-14 13:27:32', '2026-06-14 13:27:32', '', '2026-06-14'),
(97, 'CMP-2026-0061', 10, 16, 'medium', 0, 'good', 'work', NULL, 'pending', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-14 13:33:00', '2026-06-14 13:33:00', '', '2026-06-14'),
(98, 'CMP-2026-0062', 10, 17, 'medium', 0, 'ds', 'fs', NULL, 'resolved', 23, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-14 13:49:32', '2026-06-19 06:25:17', '', '2026-06-13'),
(99, 'CMP-2026-0063', 10, 17, 'medium', 0, 'thsi tie,', 'to say helloew', NULL, 'resolved', 23, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-14 14:03:38', '2026-06-14 14:08:51', '', '2026-06-20'),
(100, 'CMP-2026-0064', 26, 15, 'medium', 0, 'exm', 'mine', NULL, 'pending', 19, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-15 16:54:30', '2026-06-15 16:54:30', 'che3', '2026-06-15'),
(101, 'CMP-2026-0065', 8, 16, 'medium', 0, 'Endelea', 'Hali mbaya', NULL, 'pending', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-16 18:02:00', '2026-06-16 18:02:00', 'hd', '2026-06-15'),
(102, 'CMP-2026-0066', 8, 15, 'high', 0, 'mthna', 'mthn', NULL, 'pending', 19, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-16 18:18:22', '2026-06-16 18:18:22', '', '2026-06-17'),
(103, 'CMP-2026-0067', 10, 16, 'medium', 0, 'mthna', 'wsx', 'uploads/complaints/complaint_10_1781722158.pdf', 'pending', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-17 18:49:18', '2026-06-17 18:49:18', '', '2026-06-17'),
(104, 'CMP-2026-0068', 10, 17, 'high', 0, 'Support Team', 'problem kwnye kutafuta matatizo', NULL, 'resolved', 24, NULL, NULL, NULL, 22, NULL, NULL, NULL, '2026-06-17 18:58:18', '2026-06-18 06:54:17', 'CH5', '2026-06-16'),
(105, 'CMP-2026-0069', 11, 17, 'high', 0, 'support', 'team program', 'uploads/complaints/complaint_11_1781951041.pdf', 'resolved', 24, NULL, NULL, NULL, 22, NULL, NULL, NULL, '2026-06-20 10:24:01', '2026-06-20 10:28:19', '', '2026-06-19'),
(106, 'CMP-2026-0070', 33, 1, 'high', 0, 'ACCOUNTING', 'mwalim hatumfaham', NULL, 'pending', 16, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-20 11:48:45', '2026-06-20 11:48:45', 'CH5', '2026-06-19'),
(107, 'CMP-2026-0071', 9, 13, 'low', 0, 'Tarehe Y Freshaz', 'tumekua tukisbri kwa mda mrefu mno tamasha la freshaz \r\ntunaomba kujua lini linafanyika', NULL, 'resolved', 21, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-20 13:00:46', '2026-06-20 13:02:09', '', '2026-06-20'),
(108, 'CMP-2026-0072', 9, 17, 'medium', 0, 'Modle haifunguki', 'tuna asimnt za kusubmit', 'uploads/complaints/complaint_9_1781970214.png', 'pending', 24, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-20 15:43:34', '2026-06-20 15:43:34', 'CH5', '2026-06-20');

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
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) NOT NULL,
  `department_id` int(11) NOT NULL,
  `level` enum('certificate','diploma','bachelor','master') NOT NULL,
  `duration_years` int(11) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `name`, `code`, `department_id`, `level`, `duration_years`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Basic Technician Certificate in Accountancy', 'BTC-ACC', 1, 'certificate', 1, 1, '2026-06-14 15:24:44', '2026-06-14 15:24:44'),
(2, 'Basic Technician Certificate in Accountancy with IT', 'BTC-ACCIT', 1, 'certificate', 1, 1, '2026-06-14 15:24:44', '2026-06-14 15:24:44'),
(3, 'Basic Technician Certificate in Business Management', 'BTC-BM', 5, 'certificate', 1, 1, '2026-06-14 15:24:44', '2026-06-14 15:24:44'),
(4, 'Basic Technician Certificate in Computer Networking', 'BTC-CN', 2, 'certificate', 1, 1, '2026-06-14 15:24:44', '2026-06-14 15:24:44'),
(5, 'Basic Technician Certificate in Computing and Information Technology', 'BTC-CIT', 2, 'certificate', 1, 1, '2026-06-14 15:24:44', '2026-06-14 15:24:44'),
(6, 'Diploma in Accountancy', 'DIP-ACC', 1, 'diploma', 2, 1, '2026-06-14 15:24:44', '2026-06-14 15:24:44'),
(7, 'Diploma in Computer Science', 'DIP-CS', 2, 'diploma', 2, 1, '2026-06-14 15:24:44', '2026-06-14 15:24:44'),
(8, 'Diploma in Information Technology', 'DIP-IT', 2, 'diploma', 2, 1, '2026-06-14 15:24:44', '2026-06-14 15:24:44'),
(9, 'Diploma in Business Management', 'DIP-BM', 5, 'diploma', 2, 1, '2026-06-14 15:24:44', '2026-06-14 15:24:44'),
(10, 'Bachelor Degree in Accountancy', 'BSC-ACC', 1, 'bachelor', 3, 1, '2026-06-14 15:24:44', '2026-06-14 15:24:44'),
(11, 'Bachelor Degree in Computer Science', 'BSC-CS', 2, 'bachelor', 3, 1, '2026-06-14 15:24:44', '2026-06-14 15:24:44'),
(12, 'Bachelor Degree in Information Technology', 'BSC-IT', 2, 'bachelor', 3, 1, '2026-06-14 15:24:44', '2026-06-14 15:24:44'),
(13, 'Bachelor Degree in Business Management', 'BSC-BM', 5, 'bachelor', 3, 1, '2026-06-14 15:24:44', '2026-06-14 15:24:44'),
(14, 'Bachelor Degree in Cyber Security', 'BSC-CYB', 2, 'bachelor', 3, 1, '2026-06-14 15:24:44', '2026-06-14 15:24:44'),
(15, 'Master of Accountancy', 'MSC-ACC', 1, 'master', 1, 1, '2026-06-14 15:24:44', '2026-06-14 15:24:44'),
(16, 'Master of Business Administration in Corporate Management', 'MBA-CM', 5, 'master', 1, 1, '2026-06-14 15:24:44', '2026-06-14 15:24:44'),
(17, 'Master of Information Security', 'MSC-IS', 2, 'master', 1, 1, '2026-06-14 15:24:44', '2026-06-14 15:24:44'),
(54, 'Basic Technician Certificate in Finance and Banking', 'BTC-FB', 1, 'certificate', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(55, 'Basic Technician Certificate in Insurance and Risk Management', 'BTC-IRM', 1, 'certificate', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(56, 'Basic Technician Certificate in Library and Information studies', 'BTC-LIS', 2, 'certificate', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(57, 'Basic Technician Certificate in Mobile Application Development', 'BTC-MAD', 2, 'certificate', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(58, 'Basic Technician Certificate in Multimedia', 'BTC-MM', 2, 'certificate', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(59, 'Basic Technician Certificate in Records and Information Management', 'BTC-RIM', 2, 'certificate', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(60, 'Basic Technician Certificate in Agricultural Value Chain Management', 'BTC-AVCM', 4, 'certificate', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(61, 'Basic Technician Certificate in Economics and Finance', 'BTC-ECF', 4, 'certificate', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(62, 'Basic Technician Certificate in Business Management with Chinese', 'BTC-BMC', 5, 'certificate', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(63, 'Basic Technician Certificate in Clearing and Forwarding Management', 'BTC-CFM', 5, 'certificate', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(64, 'Basic Technician Certificate in Human Resources Management', 'BTC-HRM', 5, 'certificate', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(65, 'Basic Technician Certificate in Marketing and Public Relations', 'BTC-MPR', 5, 'certificate', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(66, 'Basic Technician Certificate in Procurement and Supply Chain Management', 'BTC-PSCM', 5, 'certificate', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(72, 'Diploma in Accountancy with IT', 'DIP-ACCIT', 1, 'diploma', 2, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(73, 'Diploma in Finance and Banking', 'DIP-FB', 1, 'diploma', 2, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(74, 'Diploma in Insurance and Risk Management', 'DIP-IRM', 1, 'diploma', 2, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(75, 'Diploma in Computer Networking', 'DIP-CN', 2, 'diploma', 2, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(76, 'Diploma in Library and Information Studies', 'DIP-LIS', 2, 'diploma', 2, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(77, 'Diploma in Mobile Applications Development', 'DIP-MAD', 2, 'diploma', 2, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(78, 'Diploma in Multimedia', 'DIP-MM', 2, 'diploma', 2, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(79, 'Diploma in Records and Information Management', 'DIP-RIM', 2, 'diploma', 2, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(80, 'Diploma in Economics and Finance', 'DIP-ECF', 4, 'diploma', 2, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(81, 'Ordinary Diploma in Agricultural Value Chain Management', 'DIP-AVCM', 4, 'diploma', 2, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(82, 'Diploma in Business Management with Chinese', 'DIP-BMC', 5, 'diploma', 2, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(83, 'Diploma in Human Resources Management', 'DIP-HRM', 5, 'diploma', 2, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(84, 'Diploma in Marketing & Public Relations', 'DIP-MPR', 5, 'diploma', 2, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(85, 'Diploma in Procurement and Supply Chain Management', 'DIP-PSCM', 5, 'diploma', 2, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(86, 'Ordinary Diploma in Clearing and Forwarding Management', 'DIP-CFM', 5, 'diploma', 2, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(91, 'Bachelor Degree In Accountancy and Finance', 'BSC-ACCF', 1, 'bachelor', 3, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(92, 'Bachelor Degree in Accountancy with Information Technology', 'BSC-ACCIT', 1, 'bachelor', 3, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(93, 'Bachelor Degree in Auditing and Assurance', 'BSC-AUD', 1, 'bachelor', 3, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(94, 'Bachelor Degree in Banking with Apprenticeship', 'BSC-BANK', 1, 'bachelor', 3, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(95, 'Bachelor Degree in Credit Management', 'BSC-CM', 1, 'bachelor', 3, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(96, 'Bachelor Degree in Finance and Banking', 'BSC-FB', 1, 'bachelor', 3, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(97, 'Bachelor Degree in Finance and Investment', 'BSC-FI', 1, 'bachelor', 3, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(98, 'Bachelor Degree in Insurance and Risk Management with Apprenticeship', 'BSC-IRM', 1, 'bachelor', 3, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(99, 'Bachelor Degree in Computer Networks Technologies', 'BSC-CNT', 2, 'bachelor', 3, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(100, 'Bachelor Degree in Education with Computer Science', 'BSC-EDCS', 2, 'bachelor', 3, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(101, 'Bachelor Degree in Library Studies and Information Science', 'BSC-LIS', 2, 'bachelor', 3, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(102, 'Bachelor Degree in Multimedia and Mass Communication', 'BSC-MMC', 2, 'bachelor', 3, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(103, 'Bachelor Degree In Records and Information Management', 'BSC-RIM', 2, 'bachelor', 3, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(104, 'Bachelor Degree in Security and Strategic Studies', 'BSC-SSS', 3, 'bachelor', 3, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(105, 'Bachelor Degree in Economics and Finance', 'BSC-ECF', 4, 'bachelor', 3, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(106, 'Bachelor Degree in Economics and Project Management', 'BSC-ECPM', 4, 'bachelor', 3, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(107, 'Bachelor Degree in Economics and Taxation', 'BSC-ECT', 4, 'bachelor', 3, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(108, 'Bachelor Degree in Natural Resources Economics', 'BSC-NRE', 4, 'bachelor', 3, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(109, 'Bachelor Degree in Human Resources and Management', 'BSC-HRM', 5, 'bachelor', 3, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(110, 'Bachelor Degree in Marketing and Public Relations', 'BSC-MPR', 5, 'bachelor', 3, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(111, 'Bachelor Degree in Procurement and Supply Chain Management', 'BSC-PSCM', 5, 'bachelor', 3, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(112, 'Bachelor Degree in Tourism and Hospitality Management with Apprenticeship', 'BSC-THM', 5, 'bachelor', 3, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(118, 'Master of Accounting and Finance', 'MSC-ACCF', 1, 'master', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(119, 'Master of Finance and Investment', 'MSC-FI', 1, 'master', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(120, 'Master of Science in Finance and Banking', 'MSC-FB', 1, 'master', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(121, 'Master of Arts in Peace and Security Studies', 'MA-PSS', 3, 'master', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(122, 'Master of Education Management', 'MEd-MGT', 4, 'master', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(123, 'Master of Science in Economics and Finance', 'MSC-ECF', 4, 'master', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(124, 'Master of Business Administration in Information Technology Management', 'MBA-ITM', 5, 'master', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(125, 'Master of Business Administration in Leadership and Governance', 'MBA-LG', 5, 'master', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(126, 'Master of Business Administration in Policy Development and Execution', 'MBA-PDE', 5, 'master', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(127, 'Master of Business Administration in Procurement and Supply Management', 'MBA-PSM', 5, 'master', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(128, 'Master of Human Resources Management', 'MSC-HRM', 5, 'master', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44'),
(129, 'Master of Project Planning and Management', 'MSC-PPM', 5, 'master', 1, 1, '2026-06-14 15:59:44', '2026-06-14 15:59:44');

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
(7, 61, 5, '', 1, '2026-05-21 06:07:19'),
(8, 99, 5, 'exlent', 1, '2026-06-14 14:09:40'),
(9, 1, 5, '', 1, '2026-06-15 19:25:38'),
(10, 54, 4, 'Excent', 1, '2026-06-20 15:01:55'),
(11, 107, 5, '', 1, '2026-06-20 15:02:38');

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
(28, 69, 23, 'tatizo lako tumelitatua ipasavyo', NULL, 0, 0, '2026-05-21 06:06:00'),
(29, 89, 4, 'sawa', NULL, 0, 0, '2026-06-14 09:58:22'),
(30, 99, 24, 'im therre', NULL, 0, 0, '2026-06-14 14:04:13'),
(31, 99, 10, 'okay sir', NULL, 0, 0, '2026-06-14 14:04:41'),
(32, 99, 22, 'no problem', NULL, 0, 0, '2026-06-14 14:07:11'),
(33, 99, 23, 'fine', NULL, 0, 0, '2026-06-14 14:08:34'),
(34, 104, 22, 'done', NULL, 0, 0, '2026-06-18 06:54:17'),
(35, 62, 23, 'Done', NULL, 0, 0, '2026-06-19 08:57:28'),
(36, 62, 23, 'Done', NULL, 0, 0, '2026-06-19 08:57:57'),
(37, 107, 21, 'usjally soon tunatangaza tarehe', NULL, 0, 0, '2026-06-20 13:02:09');

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
-- Table structure for table `student_academic_records`
--

CREATE TABLE `student_academic_records` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `academic_year_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `year_of_study` int(11) NOT NULL,
  `registration_date` date DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `status` enum('active','completed','suspended','dropped') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_academic_records`
--

INSERT INTO `student_academic_records` (`id`, `student_id`, `academic_year_id`, `course_id`, `year_of_study`, `registration_date`, `completion_date`, `status`, `created_at`) VALUES
(1, 33, 3, 105, 3, '2026-06-20', NULL, 'active', '2026-06-20 11:47:39'),
(2, 34, 3, 11, 3, '2026-06-20', NULL, 'active', '2026-06-20 18:27:15'),
(3, 35, 3, 107, 2, '2026-06-20', NULL, 'active', '2026-06-20 18:30:05');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'create', 'Created announcement: Tarifa', '::1', NULL, '2026-06-15 19:18:38'),
(2, 1, 'create', 'Created announcement: ONYO', '::1', NULL, '2026-06-15 19:28:16'),
(3, 1, 'create', 'Created announcement: TAHAZARI', '::1', NULL, '2026-06-15 20:08:01'),
(4, 1, 'create', 'Created announcement: WITO', '::1', NULL, '2026-06-15 20:29:23'),
(5, 1, 'delete', 'Deleted user: Bahati Fazil (ID: 30)', '::1', NULL, '2026-06-20 10:33:15'),
(6, 1, 'delete', 'Deleted user: Muzdalifa Twaha Faki (ID: 29)', '::1', NULL, '2026-06-20 10:33:34'),
(7, 1, 'delete', 'Deleted user: twahaz yahya (ID: 25)', '::1', NULL, '2026-06-20 10:34:02'),
(8, 1, 'update', 'Updated user: Sarah Hassan (ID: 9)', '::1', NULL, '2026-06-20 10:35:25'),
(9, 1, 'update', 'Updated user: Yamal Lamines (ID: 26)', '::1', NULL, '2026-06-20 10:53:56'),
(10, 1, 'delete', 'Soft deleted user: Rashid Mohamed (ID: 12) - Account deactivated', '::1', NULL, '2026-06-20 11:08:40'),
(11, 1, 'update', 'Updated user: Yamal Lamines (ID: 26)', '::1', NULL, '2026-06-20 11:13:16'),
(12, 1, 'create', 'Added new staff: Mr P (Role: president)', '::1', NULL, '2026-06-20 11:29:02'),
(13, 1, 'delete', 'Soft deleted user: Mr P (ID: 31) - Account deactivated', '::1', NULL, '2026-06-20 11:29:16'),
(14, 1, 'create', 'Added new staff: MR exam (Role: examination_officer)', '::1', NULL, '2026-06-20 11:43:26'),
(15, 1, 'delete', 'Soft deleted user: MR exam (ID: 32) - Account deactivated', '::1', NULL, '2026-06-20 11:44:21'),
(16, 1, 'create', 'Added new student: Thumaiya Rashid (Reg: BEF-01-0002-2023)', '::1', NULL, '2026-06-20 11:47:39'),
(17, 1, 'create', 'Created announcement: AMRI', '::1', NULL, '2026-06-20 12:02:11'),
(18, 1, 'update', 'Updated user: Thumaiya Rashidd (ID: 33)', '::1', NULL, '2026-06-20 17:35:00'),
(19, 1, 'delete', 'Soft deleted user: Prof. Grace Msangi (ID: 7) - Account deactivated', '::1', NULL, '2026-06-20 18:12:39'),
(20, 1, 'delete', 'Soft deleted user: Dr. Paul Lucas (ID: 6) - Account deactivated', '::1', NULL, '2026-06-20 18:12:53'),
(21, 1, 'delete', 'Soft deleted user: Dr. Anna Mushi (ID: 5) - Account deactivated', '::1', NULL, '2026-06-20 18:13:05'),
(22, 1, 'create', 'Added new student: Salma Juma Kilindo (Reg: BCS-01-0067-2023)', '::1', NULL, '2026-06-20 18:27:15'),
(23, 1, 'create', 'Added new student: Jasmn Ali Omar (Reg: BET-02-0001-2024)', '::1', NULL, '2026-06-20 18:30:05');

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
  `role` enum('student','hod','dean','accountant','director','admin','examination_officer','president','deputy_rector','rector','it_officer') NOT NULL DEFAULT 'student',
  `department_id` int(11) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `year_of_study` int(11) DEFAULT NULL,
  `profile_picture` varchar(500) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `current_course_id` int(11) DEFAULT NULL,
  `current_academic_year_id` int(11) DEFAULT NULL,
  `current_year_of_study` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `reg_number`, `email`, `password_hash`, `full_name`, `role`, `department_id`, `course`, `year_of_study`, `profile_picture`, `phone_number`, `is_active`, `last_login`, `created_at`, `updated_at`, `current_course_id`, `current_academic_year_id`, `current_year_of_study`) VALUES
(1, NULL, 'admin@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Twaha Faki', 'admin', NULL, NULL, NULL, 'uploads/profiles/admin_1_1781957597.jpg', '0712345678', 1, '2026-06-20 18:21:00', '2026-05-16 08:30:40', '2026-06-20 18:21:00', NULL, NULL, NULL),
(3, NULL, 'dean@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Prof. Abdul Majid', 'dean', NULL, NULL, NULL, 'uploads/profiles/dean_3_1781979595.jpg', '0712345680', 1, '2026-06-20 18:18:38', '2026-05-16 08:30:40', '2026-06-20 18:19:55', NULL, NULL, NULL),
(4, NULL, 'accountant@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mr. James Peter', 'accountant', NULL, NULL, NULL, NULL, '0712345681', 1, '2026-06-18 06:25:51', '2026-05-16 08:30:40', '2026-06-18 06:25:51', NULL, NULL, NULL),
(8, 'IAA-2023-001', 'john.mbwana@students.iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Idrissa Yahya', 'student', 2, 'Computer Science', 3, 'uploads/profiles/student_8_1781709456.jpg', '0755123456', 1, '2026-06-17 14:07:40', '2026-05-16 08:30:40', '2026-06-17 15:18:12', NULL, NULL, NULL),
(9, 'BIT-01-0001-2023', 'sarahassan@gmail.com', '$2y$10$EbQlZXBKsKKeHCMOi49poOX/sb5WKX7uIjt1Z7wItgq/qukzUULAu', 'Sarah Hassann', 'student', 5, 'Business Administration', 2, 'uploads/profiles/student_9_1781968869.png', '0755123457', 1, '2026-06-20 12:58:56', '2026-05-16 08:30:40', '2026-06-20 15:26:49', NULL, NULL, NULL),
(10, 'IAA-2023-003', 'juma.ali@students.iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juma Ali', 'student', 5, 'Accounting and Finance', 4, 'uploads/profiles/student_10_1781723625.jpg', '0755123458', 1, '2026-06-17 17:31:01', '2026-05-16 08:30:40', '2026-06-17 19:13:45', NULL, NULL, NULL),
(11, 'IAA-2024-001', 'amina.juma@students.iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Amina Juma', 'student', 2, 'Computer Science', 1, NULL, '0755123459', 1, '2026-06-20 10:22:59', '2026-05-16 08:30:40', '2026-06-20 10:22:59', NULL, NULL, NULL),
(12, 'IAA-2024-002', 'rashid.mohamed@students.iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Rashid Mohamed', 'student', 5, 'Business Administration', 1, NULL, '0755123460', 1, '2026-06-20 11:10:48', '2026-05-16 08:30:40', '2026-06-20 11:10:48', NULL, NULL, NULL),
(13, NULL, 'hod.finance@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Omari Haji', 'hod', 1, NULL, NULL, 'uploads/profiles/hod_13_1781979121.jpg', '0623435890', 1, '2026-06-20 18:11:08', '2026-05-16 09:06:23', '2026-06-20 18:12:01', NULL, NULL, NULL),
(14, NULL, 'hod.informatics@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Prof. Twaha Yahya', 'hod', 2, NULL, NULL, 'uploads/profiles/hod_14_1781979008.jpg', '0693377010', 1, '2026-06-20 18:08:08', '2026-05-16 09:06:23', '2026-06-20 18:10:08', NULL, NULL, NULL),
(15, NULL, 'hod.governance@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Khatib Abdallah', 'hod', 3, NULL, NULL, 'uploads/profiles/hod_15_1781978833.jpg', '', 1, '2026-06-20 18:04:48', '2026-05-16 09:06:23', '2026-06-20 18:07:13', NULL, NULL, NULL),
(16, NULL, 'hod.humanities@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Prof. Salma Kilindo', 'hod', 4, NULL, NULL, 'uploads/profiles/hod_16_1781978427.jpg', '', 1, '2026-06-20 17:55:21', '2026-05-16 09:06:23', '2026-06-20 18:00:27', NULL, NULL, NULL),
(17, NULL, 'hod.business@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ummy Yahya', 'hod', 5, NULL, NULL, 'uploads/profiles/hod_17_1781978029.jpg', '0773344402', 1, '2026-06-20 17:52:49', '2026-05-16 09:06:23', '2026-06-20 17:53:49', NULL, NULL, NULL),
(19, NULL, 'examination@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Badra Kilindo', 'examination_officer', NULL, NULL, NULL, 'uploads/profiles/exam_19_1781979785.jpg', '', 1, '2026-06-20 18:22:35', '2026-05-17 16:23:55', '2026-06-20 18:23:05', NULL, NULL, NULL),
(21, NULL, 'president@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Haruna Suleiman', 'president', NULL, NULL, NULL, 'uploads/profiles/president_21_1781976562.jpg', '', 1, '2026-06-20 12:34:19', '2026-05-18 17:56:57', '2026-06-20 17:29:22', NULL, NULL, NULL),
(22, NULL, 'deputy@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Jassmin Omar', 'deputy_rector', NULL, NULL, NULL, 'uploads/profiles/deputy_22_1781977475.jpg', '', 1, '2026-06-20 17:42:07', '2026-05-18 18:16:48', '2026-06-20 17:44:35', NULL, NULL, NULL),
(23, '', 'rector@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Idrissa Yahya', 'rector', NULL, NULL, NULL, 'uploads/profiles/rector_23_1781977245.jpg', '086456784', 1, '2026-06-20 17:38:13', '2026-05-18 18:16:48', '2026-06-20 17:40:45', NULL, NULL, NULL),
(24, NULL, 'it.officer@iaa.ac.tz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Festus Mwitumba', 'it_officer', NULL, NULL, NULL, 'uploads/profiles/it_officer_24_1781976974.jpg', '0712345690', 1, '2026-06-20 17:32:02', '2026-06-14 13:42:45', '2026-06-20 17:36:14', NULL, NULL, NULL),
(26, 'IAA-10-990', 'students@gmail.com', '$2y$10$u3BWiQAvU.Ec57tIK1iigu4WtqCL/P920HQwrCcQDyrxFDQkjUtem', 'Yamal Lamines', 'student', 1, NULL, NULL, NULL, '0693377010', 1, '2026-06-15 16:53:46', '2026-06-15 16:51:43', '2026-06-20 11:13:16', 97, 2, 2),
(33, 'BEF-01-0002-2023', 'Thumaiya@gmail.com', '$2y$10$gtWdKW/qe.bjB1XEi7qvHubT8CaBfw5EbQ9x0HyYxvith7q2RDYly', 'Thumaiya Rashidd', 'student', 4, NULL, NULL, NULL, '', 1, '2026-06-20 11:48:02', '2026-06-20 11:47:39', '2026-06-20 17:35:00', 105, 3, 3),
(34, 'BCS-01-0067-2023', 'kilindo@gmail.com', '$2y$10$KxwM6sCghFIpC9e2O34x0.sXijEv6wcuM4cv9cUmE99PvVD8K/62y', 'Salma Juma Kilindo', 'student', 2, NULL, NULL, 'uploads/profiles/student_1781980035_6a36db833a0d9.jpg', '0693377010', 1, NULL, '2026-06-20 18:27:15', '2026-06-20 18:27:15', 11, 3, 3),
(35, 'BET-02-0001-2024', 'Jasmin@gmail.com', '$2y$10$pmxmyr2ptW4bMg05zT0C9e3t17t3UpWQXwUKu6aNZQJvIxP0k38DW', 'Jasmn Ali Omar', 'student', 4, NULL, NULL, NULL, '0693377010', 1, NULL, '2026-06-20 18:30:05', '2026-06-20 18:30:05', 107, 3, 2);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_years`
--
ALTER TABLE `academic_years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `year_name` (`year_name`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `is_active` (`is_active`);

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
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `fk_complaints_escalated_by` (`escalated_by`);

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
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `department_id` (`department_id`);

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
-- Indexes for table `student_academic_records`
--
ALTER TABLE `student_academic_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `academic_year_id` (`academic_year_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `action` (`action`),
  ADD KEY `created_at` (`created_at`);

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
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `current_course_id` (`current_course_id`),
  ADD KEY `current_academic_year_id` (`current_academic_year_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_years`
--
ALTER TABLE `academic_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

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
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=130;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `responses`
--
ALTER TABLE `responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `student_academic_records`
--
ALTER TABLE `student_academic_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

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
  ADD CONSTRAINT `complaints_ibfk_5` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_complaints_escalated_by` FOREIGN KEY (`escalated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

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
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`);

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
-- Constraints for table `student_academic_records`
--
ALTER TABLE `student_academic_records`
  ADD CONSTRAINT `student_academic_records_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `student_academic_records_ibfk_2` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`),
  ADD CONSTRAINT `student_academic_records_ibfk_3` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`);

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
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`current_course_id`) REFERENCES `courses` (`id`),
  ADD CONSTRAINT `users_ibfk_3` FOREIGN KEY (`current_academic_year_id`) REFERENCES `academic_years` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
