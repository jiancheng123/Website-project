-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 16, 2026 at 12:21 PM
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
-- Database: `fypdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `activity`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'Password reset requested', 'Password reset requested for username: SCSJ2200858', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 10:45:28'),
(2, 1, 'Password reset completed', 'Password successfully reset for username: scsj2200858', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 10:51:46'),
(3, 5, 'Password reset completed', 'Password successfully reset for username: SCSJ2200057', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 10:54:22'),
(4, 5, 'Password reset completed', 'Password successfully reset for username: scsj2200057', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 10:55:01'),
(5, 5, 'Password reset completed', 'Password successfully reset for username: scsj2200057', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 10:56:50'),
(6, 4, 'Password reset completed', 'Password successfully reset for username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-15 06:35:14'),
(7, 4, 'Password reset completed', 'Password successfully reset for username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-15 06:43:40'),
(8, 7, 'Password reset completed', 'Password successfully reset for username: umie12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-04 14:53:17'),
(9, 13, 'profile_update', 'Updated profile information', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 14:22:40'),
(10, 13, 'profile_update', 'Updated profile information', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 14:22:47'),
(11, 1, 'profile_update', 'Updated profile information', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 14:41:52'),
(12, 1, 'profile_update', 'Updated profile information', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 14:41:58');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `room_id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `is_system_message` tinyint(1) DEFAULT 0,
  `message_type` varchar(50) DEFAULT 'chat',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `room_id`, `user_id`, `username`, `message`, `file_path`, `file_name`, `file_size`, `is_system_message`, `message_type`, `created_at`) VALUES
(2, 'xY4CUPLJYYE0', 1, 'SCSJ2200858', 'hi', NULL, NULL, NULL, 0, 'chat', '2026-01-22 06:17:04'),
(3, 'xY4CUPLJYYE0', 1, 'SCSJ2200858', 'Shared a file: FYP Proposal.docx', 'uploads/chat_files/6971c0e8cbb8a_FYP Proposal.docx', 'FYP Proposal.docx', 73266, 0, 'chat', '2026-01-22 06:17:12'),
(4, 'u1qvQr0QPrOs', 1, 'SCSJ2200858', 'hi', NULL, NULL, NULL, 0, 'chat', '2026-01-22 06:21:14'),
(5, 'u1qvQr0QPrOs', 1, 'SCSJ2200858', 'test', NULL, NULL, NULL, 0, 'chat', '2026-01-22 06:25:06'),
(6, 'u1qvQr0QPrOs', 1, 'SCSJ2200858', 'Shared a file: FYP Proposal.pdf', 'uploads/chat_files/6971c2c7e4dbf_FYP Proposal.pdf', 'FYP Proposal.pdf', 302974, 0, 'chat', '2026-01-22 06:25:11'),
(7, 'lqSwUuaWQ0UN', 1, 'SCSJ2200858', 'hi', NULL, NULL, NULL, 0, 'chat', '2026-01-22 08:48:11'),
(8, 'lqSwUuaWQ0UN', 1, 'SCSJ2200858', 'Shared a file: FYP Proposal.pdf', 'uploads/chat_files/6971e540ad918_FYP Proposal.pdf', 'FYP Proposal.pdf', 302974, 0, 'chat', '2026-01-22 08:52:16'),
(9, 'lqSwUuaWQ0UN', 1, 'SCSJ2200858', '@SCSJ2200858', NULL, NULL, NULL, 0, 'chat', '2026-01-22 08:52:23'),
(10, 'aNf4n6YlIwKS', 1, 'SCSJ2200858', 'hi', NULL, NULL, NULL, 0, 'chat', '2026-01-22 10:32:08'),
(11, 'aNf4n6YlIwKS', 1, 'SCSJ2200858', 'Shared a file: FYP Proposal.pdf', 'uploads/chat_files/6971fcaf1875e_FYP Proposal.pdf', 'FYP Proposal.pdf', 302974, 0, 'chat', '2026-01-22 10:32:15'),
(12, 'CuPLzgU15q3V', 1, 'SCSJ2200858', 'hi', NULL, NULL, NULL, 0, 'chat', '2026-01-22 10:53:37'),
(13, 'CuPLzgU15q3V', 1, 'SCSJ2200858', 'Shared a file: FYP Proposal.pdf', 'uploads/chat_files/697201b83d185_FYP Proposal.pdf', 'FYP Proposal.pdf', 302974, 0, 'chat', '2026-01-22 10:53:44'),
(14, 'kbeMVfuezJa9', 5, 'SCSJ2200057', 'hi', NULL, NULL, NULL, 0, 'chat', '2026-01-22 12:28:30'),
(15, 'kbeMVfuezJa9', 5, 'SCSJ2200057', 'Shared a file: FYP Proposal.pdf', 'uploads/chat_files/697217f4065b4_FYP Proposal.pdf', 'FYP Proposal.pdf', 302974, 0, 'chat', '2026-01-22 12:28:36'),
(16, 'mD03islnklbH', 1, 'SCSJ2200858', 'hi', NULL, NULL, NULL, 0, 'chat', '2026-01-23 13:06:15'),
(17, 'mD03islnklbH', 1, 'SCSJ2200858', 'Shared a file: Chapter1_WongJianCheng_SCSJ2200858.docx', 'uploads/chat_files/69737265bc8bb_Chapter1_WongJianCheng_SCSJ2200858.docx', 'Chapter1_WongJianCheng_SCSJ2200858.docx', 22668, 0, 'chat', '2026-01-23 13:06:45'),
(18, 'mD03islnklbH', 1, 'SCSJ2200858', 'Shared a file: SUBMISSION_PLANNING.pdf', 'uploads/chat_files/69737739c7159_SUBMISSION_PLANNING.pdf', 'SUBMISSION_PLANNING.pdf', 295041, 0, 'chat', '2026-01-23 13:27:21'),
(19, 'mD03islnklbH', 1, 'SCSJ2200858', 'Shared a file: Chapter1_WongJianCheng_SCSJ2200858.docx', 'uploads/chat_files/6973774a9def1_Chapter1_WongJianCheng_SCSJ2200858.docx', 'Chapter1_WongJianCheng_SCSJ2200858.docx', 22668, 0, 'chat', '2026-01-23 13:27:38'),
(20, 'mD03islnklbH', 1, 'SCSJ2200858', 'test', NULL, NULL, NULL, 0, 'chat', '2026-01-23 14:08:15'),
(21, 'mD03islnklbH', 1, 'SCSJ2200858', 'Shared a file: Chapter1_WongJianCheng_SCSJ2200858.docx', 'uploads/chat_files/697380de92482_Chapter1_WongJianCheng_SCSJ2200858.docx', 'Chapter1_WongJianCheng_SCSJ2200858.docx', 22668, 0, 'chat', '2026-01-23 14:08:30'),
(22, 'mD03islnklbH', 1, 'SCSJ2200858', 'hi', NULL, NULL, NULL, 0, 'chat', '2026-01-23 14:27:39'),
(23, 'mD03islnklbH', 1, 'SCSJ2200858', 'hi', NULL, NULL, NULL, 0, 'chat', '2026-01-23 14:27:42'),
(24, 'mD03islnklbH', 1, 'SCSJ2200858', 'hi', NULL, NULL, NULL, 0, 'chat', '2026-01-23 14:27:43'),
(25, 'mD03islnklbH', 1, 'SCSJ2200858', 'hi', NULL, NULL, NULL, 0, 'chat', '2026-01-23 14:27:49'),
(26, 'mD03islnklbH', 1, 'SCSJ2200858', 'Shared a file: Chapter2_WongJianCheng_SCSJ2200858.docx', 'uploads/chat_files/69738565ba54a_Chapter2_WongJianCheng_SCSJ2200858.docx', 'Chapter2_WongJianCheng_SCSJ2200858.docx', 18863, 0, 'chat', '2026-01-23 14:27:49'),
(27, 'mD03islnklbH', 1, 'SCSJ2200858', 'hi', NULL, NULL, NULL, 0, 'chat', '2026-01-23 16:31:12'),
(28, 'mD03islnklbH', 1, 'SCSJ2200858', 'hi', NULL, NULL, NULL, 0, 'chat', '2026-01-23 16:31:18'),
(29, '36', 1, 'SCSJ2200858', 'texting', NULL, NULL, NULL, 0, 'chat', '2026-01-24 13:36:21'),
(30, 'QilBSl1kggzt', 5, 'SCSJ2200057', 'hi', NULL, NULL, NULL, 0, 'chat', '2026-01-24 13:50:32'),
(31, 'QilBSl1kggzt', 1, 'SCSJ2200858', 'hi', NULL, NULL, NULL, 0, 'chat', '2026-01-24 13:50:40'),
(32, 'cwmJAZpVMa1c', 1, 'SCSJ2200858', 'hi', NULL, NULL, NULL, 0, 'chat', '2026-01-26 07:04:26'),
(33, 'cwmJAZpVMa1c', 1, 'SCSJ2200858', 'Shared a file: SampleLRincomparativestudy.docx', 'uploads/chat_files/69771204a6ce6_SampleLRincomparativestudy.docx', 'SampleLRincomparativestudy.docx', 18858, 0, 'chat', '2026-01-26 07:04:36'),
(34, 'v7TqhBtWSYKM', 5, 'SCSJ2200057', 'hi', NULL, NULL, NULL, 0, 'chat', '2026-02-12 08:52:15'),
(35, 'v7TqhBtWSYKM', 1, 'SCSJ2200858', 'how r u', NULL, NULL, NULL, 0, 'chat', '2026-02-12 08:52:21'),
(36, 'WmAATo35mDw7', 1, 'SCSJ2200858', 'test', NULL, NULL, NULL, 0, 'chat', '2026-02-12 10:32:12'),
(37, 'jApTxS4Oe9AZ', 5, 'SCSJ2200057', 'hi', NULL, NULL, NULL, 0, 'chat', '2026-02-13 09:38:45'),
(38, 'jApTxS4Oe9AZ', 5, 'SCSJ2200057', 'test', NULL, NULL, NULL, 0, 'chat', '2026-02-13 09:38:49'),
(39, 'jApTxS4Oe9AZ', 1, 'SCSJ2200858', '123', NULL, NULL, NULL, 0, 'chat', '2026-02-13 09:38:53'),
(40, '4QCK4IVyKF3D', 1, 'SCSJ2200858', 'test', NULL, NULL, NULL, 0, 'chat', '2026-02-13 10:28:14'),
(41, 'bpHx1GtmmOYJ', 4, 'Admin', 'testing', NULL, NULL, NULL, 0, 'chat', '2026-02-14 16:54:08'),
(42, 'vxZEsHjvisEF', 1, 'SCSJ2200858', 'test', NULL, NULL, NULL, 0, 'chat', '2026-02-25 05:49:45'),
(43, 'prRQL1XeSblh', 5, 'SCSJ2200057', 'hi', NULL, NULL, NULL, 0, 'chat', '2026-02-28 04:40:28'),
(44, 'prRQL1XeSblh', 1, 'SCSJ2200858', 'hi', NULL, NULL, NULL, 0, 'chat', '2026-02-28 04:40:33'),
(45, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:12'),
(46, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:12'),
(47, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:13'),
(48, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:13'),
(49, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:13'),
(50, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:15'),
(51, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:16'),
(52, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:17'),
(53, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:18'),
(54, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:18'),
(55, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:19'),
(56, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:19'),
(57, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:21'),
(58, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:22'),
(59, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:23'),
(60, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:23'),
(61, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:23'),
(62, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:25'),
(63, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:25'),
(64, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:27'),
(65, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:28'),
(66, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:28'),
(67, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:28'),
(68, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:29'),
(69, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:31'),
(70, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:31'),
(71, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:33'),
(72, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:33'),
(73, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:33'),
(74, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:34'),
(75, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:35'),
(76, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:37'),
(77, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:37'),
(78, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:38'),
(79, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:38'),
(80, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:39'),
(81, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:40'),
(82, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:41'),
(83, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:43'),
(84, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:43'),
(85, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:43'),
(86, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:43'),
(87, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:45'),
(88, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:46'),
(89, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:47'),
(90, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:48'),
(91, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:48'),
(92, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:49'),
(93, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:49'),
(94, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:51'),
(95, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:52'),
(96, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:53'),
(97, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:53'),
(98, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:53'),
(99, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:55'),
(100, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:55'),
(101, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:57'),
(102, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:58'),
(103, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:58'),
(104, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:58'),
(105, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:44:59'),
(106, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:01'),
(107, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:01'),
(108, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:03'),
(109, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:03'),
(110, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:03'),
(111, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:04'),
(112, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:05'),
(113, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:07'),
(114, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:07'),
(115, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:08'),
(116, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:08'),
(117, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:09'),
(118, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:10'),
(119, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:11'),
(120, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:13'),
(121, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:13'),
(122, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:13'),
(123, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:13'),
(124, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:15'),
(125, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:16'),
(126, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:17'),
(127, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:18'),
(128, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:18'),
(129, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:19'),
(130, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:19'),
(131, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:21'),
(132, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:22'),
(133, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:23'),
(134, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:23'),
(135, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:23'),
(136, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:25'),
(137, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:25'),
(138, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:27'),
(139, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:28'),
(140, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:28'),
(141, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:28'),
(142, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:29'),
(143, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:31'),
(144, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:31'),
(145, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:33'),
(146, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:33'),
(147, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:33'),
(148, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:34'),
(149, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:35'),
(150, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:37'),
(151, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:37'),
(152, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:38'),
(153, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:38'),
(154, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:39'),
(155, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:40'),
(156, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:41'),
(157, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:41'),
(158, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 left the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:41'),
(159, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:45:41'),
(160, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:18'),
(161, 'prRQL1XeSblh', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:18'),
(162, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:19'),
(163, 'prRQL1XeSblh', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:19'),
(164, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:19'),
(165, 'prRQL1XeSblh', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:19'),
(166, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:19'),
(167, 'prRQL1XeSblh', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:19'),
(168, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:19'),
(169, 'prRQL1XeSblh', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:19'),
(170, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:21'),
(171, 'prRQL1XeSblh', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:21'),
(172, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:22'),
(173, 'prRQL1XeSblh', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:22'),
(174, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:23'),
(175, 'prRQL1XeSblh', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:23'),
(176, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:24'),
(177, 'prRQL1XeSblh', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:24'),
(178, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:24'),
(179, 'prRQL1XeSblh', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:24'),
(180, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:25'),
(181, 'prRQL1XeSblh', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:25'),
(182, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:25'),
(183, 'prRQL1XeSblh', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:25'),
(184, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:27'),
(185, 'prRQL1XeSblh', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:27'),
(186, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:28'),
(187, 'prRQL1XeSblh', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:28'),
(188, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:28'),
(189, 'prRQL1XeSblh', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:28'),
(190, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 left the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:28'),
(191, 'prRQL1XeSblh', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:28'),
(192, 'prRQL1XeSblh', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:28'),
(193, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:32'),
(194, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:32'),
(195, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:33'),
(196, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:33'),
(197, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:33'),
(198, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:33'),
(199, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:33'),
(200, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:33'),
(201, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:33'),
(202, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:33'),
(203, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:35'),
(204, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:35'),
(205, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:36'),
(206, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:36'),
(207, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:37'),
(208, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:37'),
(209, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:38'),
(210, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:38'),
(211, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:38'),
(212, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:38'),
(213, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:39'),
(214, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:39'),
(215, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:39'),
(216, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:39'),
(217, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:41'),
(218, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:41'),
(219, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:42'),
(220, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:42'),
(221, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:43'),
(222, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:43'),
(223, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:43'),
(224, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:43'),
(225, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:43'),
(226, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:43'),
(227, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:45'),
(228, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:45'),
(229, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:45'),
(230, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:45'),
(231, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:47'),
(232, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:47'),
(233, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:48'),
(234, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:48'),
(235, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:48'),
(236, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:48'),
(237, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:48'),
(238, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:48'),
(239, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:49'),
(240, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:49'),
(241, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:51'),
(242, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:51'),
(243, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:51'),
(244, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:51'),
(245, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:53'),
(246, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:53'),
(247, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:53'),
(248, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:53'),
(249, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:53'),
(250, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:53'),
(251, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:54'),
(252, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:54'),
(253, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:55'),
(254, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:55'),
(255, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:57'),
(256, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:57'),
(257, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:57'),
(258, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:57'),
(259, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:58'),
(260, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:58'),
(261, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:58'),
(262, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:58'),
(263, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:59'),
(264, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:47:59'),
(265, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:00'),
(266, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:00'),
(267, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:01'),
(268, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:01'),
(269, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:03'),
(270, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:03'),
(271, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:03'),
(272, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:03'),
(273, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:03'),
(274, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:03'),
(275, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:03'),
(276, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:03'),
(277, 'ExPQYR3NESQe', 5, 'SCSJ2200057', '👋 SCSJ2200057 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:05'),
(278, 'ExPQYR3NESQe', 5, 'System', '🎉 Welcome to the meeting, SCSJ2200057!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:05'),
(279, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:05'),
(280, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:05'),
(281, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:06'),
(282, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:06'),
(283, 'ExPQYR3NESQe', 5, 'SCSJ2200057', '👋 SCSJ2200057 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:07'),
(284, 'ExPQYR3NESQe', 5, 'System', '🎉 Welcome to the meeting, SCSJ2200057!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:07'),
(285, 'ExPQYR3NESQe', 5, 'SCSJ2200057', '👋 SCSJ2200057 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:07'),
(286, 'ExPQYR3NESQe', 5, 'System', '🎉 Welcome to the meeting, SCSJ2200057!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:07'),
(287, 'ExPQYR3NESQe', 5, 'SCSJ2200057', '👋 SCSJ2200057 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:07'),
(288, 'ExPQYR3NESQe', 5, 'System', '🎉 Welcome to the meeting, SCSJ2200057!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:07'),
(289, 'ExPQYR3NESQe', 5, 'SCSJ2200057', '👋 SCSJ2200057 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:07'),
(290, 'ExPQYR3NESQe', 5, 'System', '🎉 Welcome to the meeting, SCSJ2200057!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:07'),
(291, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:07'),
(292, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:07'),
(293, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:08'),
(294, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:08'),
(295, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:08'),
(296, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:08'),
(297, 'ExPQYR3NESQe', 5, 'SCSJ2200057', '👋 SCSJ2200057 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:09'),
(298, 'ExPQYR3NESQe', 5, 'System', '🎉 Welcome to the meeting, SCSJ2200057!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:09'),
(299, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:09'),
(300, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:09'),
(301, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:09'),
(302, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:09'),
(303, 'ExPQYR3NESQe', 5, 'SCSJ2200057', '👋 SCSJ2200057 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:10'),
(304, 'ExPQYR3NESQe', 5, 'System', '🎉 Welcome to the meeting, SCSJ2200057!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:10'),
(305, 'ExPQYR3NESQe', 5, 'SCSJ2200057', '👋 SCSJ2200057 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:11'),
(306, 'ExPQYR3NESQe', 5, 'System', '🎉 Welcome to the meeting, SCSJ2200057!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:11'),
(307, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:11'),
(308, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:11'),
(309, 'ExPQYR3NESQe', 5, 'SCSJ2200057', '👋 SCSJ2200057 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:12'),
(310, 'ExPQYR3NESQe', 5, 'System', '🎉 Welcome to the meeting, SCSJ2200057!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:12'),
(311, 'ExPQYR3NESQe', 5, 'SCSJ2200057', '👋 SCSJ2200057 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:12'),
(312, 'ExPQYR3NESQe', 5, 'System', '🎉 Welcome to the meeting, SCSJ2200057!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:12'),
(313, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:12'),
(314, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:12'),
(315, 'ExPQYR3NESQe', 5, 'SCSJ2200057', '👋 SCSJ2200057 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:13'),
(316, 'ExPQYR3NESQe', 5, 'System', '🎉 Welcome to the meeting, SCSJ2200057!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:13'),
(317, 'ExPQYR3NESQe', 5, 'SCSJ2200057', '👋 SCSJ2200057 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:13'),
(318, 'ExPQYR3NESQe', 5, 'System', '🎉 Welcome to the meeting, SCSJ2200057!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:13'),
(319, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:13'),
(320, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:13'),
(321, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:13'),
(322, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:13'),
(323, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:13'),
(324, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:13'),
(325, 'ExPQYR3NESQe', 5, 'SCSJ2200057', '👋 SCSJ2200057 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:15'),
(326, 'ExPQYR3NESQe', 5, 'System', '🎉 Welcome to the meeting, SCSJ2200057!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:15'),
(327, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:15'),
(328, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:15'),
(329, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:15'),
(330, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:15'),
(331, 'ExPQYR3NESQe', 5, 'SCSJ2200057', '👋 SCSJ2200057 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:16'),
(332, 'ExPQYR3NESQe', 5, 'System', '🎉 Welcome to the meeting, SCSJ2200057!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:16'),
(333, 'ExPQYR3NESQe', 5, 'SCSJ2200057', '👋 SCSJ2200057 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:17'),
(334, 'ExPQYR3NESQe', 5, 'System', '🎉 Welcome to the meeting, SCSJ2200057!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:17'),
(335, 'ExPQYR3NESQe', 5, 'SCSJ2200057', '👋 SCSJ2200057 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:17'),
(336, 'ExPQYR3NESQe', 5, 'System', '🎉 Welcome to the meeting, SCSJ2200057!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:17'),
(337, 'ExPQYR3NESQe', 5, 'SCSJ2200057', '👋 SCSJ2200057 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:17'),
(338, 'ExPQYR3NESQe', 5, 'System', '🎉 Welcome to the meeting, SCSJ2200057!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:17'),
(339, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:17'),
(340, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:17'),
(341, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:18'),
(342, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:18'),
(343, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:18'),
(344, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:18'),
(345, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:18'),
(346, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:18'),
(347, 'ExPQYR3NESQe', 5, 'SCSJ2200057', '👋 SCSJ2200057 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:19'),
(348, 'ExPQYR3NESQe', 5, 'System', '🎉 Welcome to the meeting, SCSJ2200057!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:19'),
(349, 'ExPQYR3NESQe', 5, 'SCSJ2200057', '👋 SCSJ2200057 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:19'),
(350, 'ExPQYR3NESQe', 5, 'System', '🎉 Welcome to the meeting, SCSJ2200057!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:19'),
(351, 'ExPQYR3NESQe', 5, 'SCSJ2200057', '👋 SCSJ2200057 left the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:19'),
(352, 'ExPQYR3NESQe', 5, 'SCSJ2200057', '👋 SCSJ2200057 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:19'),
(353, 'ExPQYR3NESQe', 5, 'System', '🎉 Welcome to the meeting, SCSJ2200057!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:19'),
(354, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:19'),
(355, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:19'),
(356, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:21'),
(357, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:21'),
(358, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:21'),
(359, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:21'),
(360, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:23'),
(361, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:23'),
(362, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:23'),
(363, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:23'),
(364, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:23'),
(365, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:23'),
(366, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:24'),
(367, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:24'),
(368, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:25'),
(369, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:25'),
(370, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:27'),
(371, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:27'),
(372, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:27'),
(373, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:27'),
(374, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:28'),
(375, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:28'),
(376, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:28'),
(377, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:28'),
(378, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:29'),
(379, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:29'),
(380, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:30'),
(381, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:30'),
(382, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:31'),
(383, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:31'),
(384, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:33'),
(385, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:33'),
(386, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:33'),
(387, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:33'),
(388, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:33'),
(389, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:33'),
(390, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:33'),
(391, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:33');
INSERT INTO `chat_messages` (`id`, `room_id`, `user_id`, `username`, `message`, `file_path`, `file_name`, `file_size`, `is_system_message`, `message_type`, `created_at`) VALUES
(392, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:35'),
(393, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:35'),
(394, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:36'),
(395, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:36'),
(396, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:37'),
(397, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:37'),
(398, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:38'),
(399, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:38'),
(400, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:38'),
(401, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:38'),
(402, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:39'),
(403, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:39'),
(404, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:39'),
(405, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:39'),
(406, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:41'),
(407, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:41'),
(408, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:42'),
(409, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:42'),
(410, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:43'),
(411, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:43'),
(412, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:43'),
(413, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:43'),
(414, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:43'),
(415, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:43'),
(416, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:45'),
(417, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:45'),
(418, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:45'),
(419, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:45'),
(420, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:47'),
(421, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:47'),
(422, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:48'),
(423, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:48'),
(424, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:48'),
(425, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:48'),
(426, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:48'),
(427, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:48'),
(428, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:49'),
(429, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:49'),
(430, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:51'),
(431, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:51'),
(432, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:51'),
(433, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:51'),
(434, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:53'),
(435, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:53'),
(436, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:53'),
(437, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:53'),
(438, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:53'),
(439, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:53'),
(440, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:54'),
(441, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:54'),
(442, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:55'),
(443, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:55'),
(444, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:57'),
(445, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:57'),
(446, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:57'),
(447, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:57'),
(448, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:58'),
(449, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:58'),
(450, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:58'),
(451, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:58'),
(452, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:59'),
(453, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:48:59'),
(454, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:00'),
(455, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:00'),
(456, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:01'),
(457, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:01'),
(458, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:03'),
(459, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:03'),
(460, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:03'),
(461, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:03'),
(462, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:03'),
(463, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:03'),
(464, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:03'),
(465, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:03'),
(466, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:05'),
(467, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:05'),
(468, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:06'),
(469, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:06'),
(470, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:07'),
(471, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:07'),
(472, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:08'),
(473, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:08'),
(474, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:08'),
(475, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:08'),
(476, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:09'),
(477, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:09'),
(478, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:09'),
(479, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:09'),
(480, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:11'),
(481, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:11'),
(482, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:12'),
(483, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:12'),
(484, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:13'),
(485, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:13'),
(486, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:13'),
(487, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:13'),
(488, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:13'),
(489, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:13'),
(490, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:15'),
(491, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:15'),
(492, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:15'),
(493, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:15'),
(494, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:17'),
(495, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:17'),
(496, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:18'),
(497, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:18'),
(498, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:18'),
(499, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:18'),
(500, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:18'),
(501, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:18'),
(502, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:19'),
(503, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:19'),
(504, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:21'),
(505, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:21'),
(506, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:21'),
(507, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:21'),
(508, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:23'),
(509, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:23'),
(510, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:23'),
(511, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:23'),
(512, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:23'),
(513, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:23'),
(514, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:24'),
(515, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:24'),
(516, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:25'),
(517, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:25'),
(518, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:27'),
(519, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:27'),
(520, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:27'),
(521, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:27'),
(522, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:28'),
(523, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:28'),
(524, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:28'),
(525, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:28'),
(526, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:29'),
(527, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:29'),
(528, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:30'),
(529, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:30'),
(530, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:31'),
(531, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:31'),
(532, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:33'),
(533, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:33'),
(534, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:33'),
(535, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:33'),
(536, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:33'),
(537, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:33'),
(538, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:33'),
(539, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:33'),
(540, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:35'),
(541, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:35'),
(542, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:36'),
(543, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:36'),
(544, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:37'),
(545, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:37'),
(546, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:38'),
(547, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:38'),
(548, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:38'),
(549, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:38'),
(550, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:39'),
(551, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:39'),
(552, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:39'),
(553, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:39'),
(554, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:41'),
(555, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:41'),
(556, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:42'),
(557, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:42'),
(558, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:43'),
(559, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:43'),
(560, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:43'),
(561, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:43'),
(562, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:43'),
(563, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:43'),
(564, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:45'),
(565, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:45'),
(566, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:45'),
(567, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:45'),
(568, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:47'),
(569, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:47'),
(570, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:48'),
(571, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:48'),
(572, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:48'),
(573, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:48'),
(574, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:48'),
(575, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:48'),
(576, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:49'),
(577, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:49'),
(578, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:51'),
(579, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:51'),
(580, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:51'),
(581, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:51'),
(582, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:53'),
(583, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:53'),
(584, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:53'),
(585, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:53'),
(586, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:53'),
(587, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:53'),
(588, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:54'),
(589, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:54'),
(590, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:55'),
(591, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:55'),
(592, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:57'),
(593, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:57'),
(594, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:57'),
(595, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:57'),
(596, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:58'),
(597, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:58'),
(598, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:58'),
(599, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:58'),
(600, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:59'),
(601, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:49:59'),
(602, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:00'),
(603, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:00'),
(604, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:01'),
(605, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:01'),
(606, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:03'),
(607, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:03'),
(608, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:03'),
(609, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:03'),
(610, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:03'),
(611, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:03'),
(612, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:03'),
(613, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:03'),
(614, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:05'),
(615, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:05'),
(616, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:06'),
(617, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:06'),
(618, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:07'),
(619, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:07'),
(620, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:08'),
(621, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:08'),
(622, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:08'),
(623, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:08'),
(624, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:09'),
(625, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:09'),
(626, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:09'),
(627, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:09'),
(628, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:11'),
(629, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:11'),
(630, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:12'),
(631, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:12'),
(632, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:13'),
(633, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:13'),
(634, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:13'),
(635, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:13'),
(636, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:13'),
(637, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:13'),
(638, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:15'),
(639, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:15'),
(640, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:15'),
(641, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:15'),
(642, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:17'),
(643, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:17'),
(644, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:18'),
(645, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:18'),
(646, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:18'),
(647, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:18'),
(648, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:18'),
(649, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:18'),
(650, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:19'),
(651, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:19'),
(652, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:21'),
(653, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:21'),
(654, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:21'),
(655, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:21'),
(656, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 left the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:21'),
(657, 'ExPQYR3NESQe', 1, 'SCSJ2200858', '👋 SCSJ2200858 joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:21'),
(658, 'ExPQYR3NESQe', 1, 'System', '🎉 Welcome to the meeting, SCSJ2200858!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 05:50:21'),
(659, 'v5is1YvFu1KP', 4, 'Admin', '👋 Admin joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 07:57:31'),
(660, 'v5is1YvFu1KP', 4, 'System', '🎉 Welcome to the meeting, Admin!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 07:57:31'),
(661, 'v5is1YvFu1KP', 4, 'Admin', '👋 Admin joined the meeting', NULL, NULL, NULL, 1, 'chat', '2026-02-28 07:57:34'),
(662, 'v5is1YvFu1KP', 4, 'System', '🎉 Welcome to the meeting, Admin!', NULL, NULL, NULL, 1, 'chat', '2026-02-28 07:57:34'),
(663, 'ZvdQVtzE9csB', 4, 'Admin', '🔇 SCSJ2200858 was muted by the host', NULL, NULL, NULL, 1, 'mute', '2026-02-28 18:16:03'),
(664, 'ZvdQVtzE9csB', 4, 'Admin', '🔊 SCSJ2200858 was unmuted by the host', NULL, NULL, NULL, 1, 'unmute', '2026-02-28 18:16:05'),
(665, 'ZvdQVtzE9csB', 4, 'Admin', '🚫 SCSJ2200858 was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-02-28 18:16:10'),
(666, 'ZvdQVtzE9csB', 1, 'SCSJ2200858', '👋 SCSJ2200858 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-02-28 18:17:45'),
(667, 'ZvdQVtzE9csB', 4, 'Admin', '👋 Admin left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-02-28 18:17:50'),
(668, 's5slazxFBPh2', 5, 'SCSJ2200057', '🔇 SCSJ2200858 was muted by the host', NULL, NULL, NULL, 1, 'mute', '2026-02-28 18:44:57'),
(669, 's5slazxFBPh2', 5, 'SCSJ2200057', '🔊 SCSJ2200858 was unmuted by the host', NULL, NULL, NULL, 1, 'unmute', '2026-02-28 18:45:01'),
(670, 's5slazxFBPh2', 5, 'SCSJ2200057', '🚫 SCSJ2200858 was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-02-28 18:45:03'),
(671, 's5slazxFBPh2', 5, 'SCSJ2200057', '👋 SCSJ2200057 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-02-28 18:46:38'),
(672, 's5slazxFBPh2', 5, 'SCSJ2200057', '🔇 SCSJ2200858 was muted by the host', NULL, NULL, NULL, 1, 'mute', '2026-02-28 18:51:32'),
(673, 's5slazxFBPh2', 5, 'SCSJ2200057', '🔊 SCSJ2200858 was unmuted by the host', NULL, NULL, NULL, 1, 'unmute', '2026-02-28 18:51:33'),
(674, 's5slazxFBPh2', 5, 'SCSJ2200057', '🔇 SCSJ2200858 was muted by the host', NULL, NULL, NULL, 1, 'mute', '2026-02-28 18:51:37'),
(675, 's5slazxFBPh2', 5, 'SCSJ2200057', '🔊 SCSJ2200858 was unmuted by the host', NULL, NULL, NULL, 1, 'unmute', '2026-02-28 18:51:39'),
(676, 's5slazxFBPh2', 5, 'SCSJ2200057', '🚫 SCSJ2200858 was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-02-28 18:51:52'),
(677, 's5slazxFBPh2', 5, 'SCSJ2200057', '👋 SCSJ2200057 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-02-28 18:52:44'),
(678, 's5slazxFBPh2', 5, 'SCSJ2200057', '👋 SCSJ2200057 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-02-28 18:56:03'),
(679, 's5slazxFBPh2', 1, 'SCSJ2200858', '👋 SCSJ2200858 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-02-28 18:58:31'),
(680, 's5slazxFBPh2', 5, 'SCSJ2200057', '👋 SCSJ2200057 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-02-28 18:58:36'),
(681, 'Z78snbxCnPoK', 1, 'SCSJ2200858', '🔇 SCSJ2200057 was muted by the host', NULL, NULL, NULL, 1, 'mute', '2026-03-03 18:19:45'),
(682, 'Z78snbxCnPoK', 1, 'SCSJ2200858', '🔊 SCSJ2200057 was unmuted by the host', NULL, NULL, NULL, 1, 'unmute', '2026-03-03 18:19:47'),
(683, 'Z78snbxCnPoK', 1, 'SCSJ2200858', '🚫 SCSJ2200057 was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-03 18:19:49'),
(684, 'Z78snbxCnPoK', 5, 'SCSJ2200057', '👋 SCSJ2200057 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-03 18:19:59'),
(685, 'Z78snbxCnPoK', 1, 'SCSJ2200858', '👋 SCSJ2200858 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-03 18:22:59'),
(686, 'Z78snbxCnPoK', 1, 'SCSJ2200858', '👋 SCSJ2200858 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-03 18:30:48'),
(687, 'Z78snbxCnPoK', 1, 'SCSJ2200858', '👋 SCSJ2200858 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-03 18:35:21'),
(688, 'Io5U69lzW25D', 6, 'SCSJ2200001', '🚫 SCSJ2200858 was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-03 18:43:12'),
(689, 'Io5U69lzW25D', 6, 'SCSJ2200001', '🔇 SCSJ2200858 was muted by the host', NULL, NULL, NULL, 1, 'mute', '2026-03-03 18:48:00'),
(690, 'Io5U69lzW25D', 6, 'SCSJ2200001', '🚫 SCSJ2200858 was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-03 18:48:11'),
(691, 'Io5U69lzW25D', 6, 'SCSJ2200001', '🔇 SCSJ2200858 was muted by the host', NULL, NULL, NULL, 1, 'mute', '2026-03-03 18:48:33'),
(692, 'Io5U69lzW25D', 6, 'SCSJ2200001', '🔊 SCSJ2200858 was unmuted by the host', NULL, NULL, NULL, 1, 'unmute', '2026-03-03 18:48:58'),
(693, 'Q8ofsUnaXoPP', 4, 'Admin', '👋 Admin left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-03 19:32:11'),
(694, 'Q8ofsUnaXoPP', 4, 'Admin', '👋 Admin left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-03 19:35:15'),
(695, 'Q8ofsUnaXoPP', 1, 'SCSJ2200858', '🔇 Admin was muted by the host', NULL, NULL, NULL, 1, 'mute', '2026-03-03 19:35:31'),
(696, 'Q8ofsUnaXoPP', 1, 'SCSJ2200858', '🔊 Admin was unmuted by the host', NULL, NULL, NULL, 1, 'unmute', '2026-03-03 19:35:32'),
(697, 'Q8ofsUnaXoPP', 1, 'SCSJ2200858', '🚫 Admin was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-03 19:35:34'),
(698, 'Q8ofsUnaXoPP', 1, 'SCSJ2200858', '🚫 Admin was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-03 19:46:03'),
(699, 'Q8ofsUnaXoPP', 4, 'Admin', '👋 Admin left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-03 19:46:04'),
(700, 'Q8ofsUnaXoPP', 1, 'SCSJ2200858', '👋 SCSJ2200858 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-03 19:47:36'),
(701, '2uB7BK8FRVlt', 5, 'SCSJ2200057', '🔇 SCSJ2200858 was muted by the host', NULL, NULL, NULL, 1, 'mute', '2026-03-03 19:48:20'),
(702, '2uB7BK8FRVlt', 5, 'SCSJ2200057', '🔊 SCSJ2200858 was unmuted by the host', NULL, NULL, NULL, 1, 'unmute', '2026-03-03 19:48:23'),
(703, '2uB7BK8FRVlt', 5, 'SCSJ2200057', '🚫 SCSJ2200858 was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-03 19:48:27'),
(704, '2uB7BK8FRVlt', 1, 'SCSJ2200858', '👋 SCSJ2200858 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-03 19:48:30'),
(705, '2uB7BK8FRVlt', 5, 'SCSJ2200057', '👋 SCSJ2200057 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-03 19:56:41'),
(706, 'Q8ofsUnaXoPP', 1, 'SCSJ2200858', '🚫 SCSJ2200057 was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-03 20:12:33'),
(707, 'Q8ofsUnaXoPP', 5, 'SCSJ2200057', '👋 SCSJ2200057 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-03 20:12:35'),
(708, 'Q8ofsUnaXoPP', 1, 'SCSJ2200858', '👋 SCSJ2200858 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-03 20:13:07'),
(709, 'eT85mPTo3EL6', 1, 'SCSJ2200858', '👋 SCSJ2200858 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-04 02:19:21'),
(710, 'wFx8h3eWInbn', 1, 'SCSJ2200858', '👋 SCSJ2200858 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-04 06:11:54'),
(711, 'RLfpIr3lS3MB', 7, 'umie12', 'ghjfdhjgdjfghjdf', NULL, NULL, NULL, 0, 'chat', '2026-03-04 06:38:29'),
(712, 'RLfpIr3lS3MB', 7, 'umie12', 'yriwuruf', NULL, NULL, NULL, 0, 'chat', '2026-03-04 06:38:33'),
(713, 'RLfpIr3lS3MB', 7, 'umie12', 'bvnbdn', NULL, NULL, NULL, 0, 'chat', '2026-03-04 06:38:35'),
(714, 'RLfpIr3lS3MB', 7, 'umie12', 'Shared a file: CHAPTER 2-TEXT part 1.pptx', 'uploads/chat_files/69a7d38f54df1_CHAPTER 2-TEXT part 1.pptx', 'CHAPTER 2-TEXT part 1.pptx', 22578832, 0, 'file', '2026-03-04 06:39:11'),
(715, 'RLfpIr3lS3MB', 7, 'umie12', 'Shared a file: Malaysia_CultureWeek_1920x1080.mp4', 'uploads/chat_files/69a7d399e0d6c_Malaysia_CultureWeek_1920x1080.mp4', 'Malaysia_CultureWeek_1920x1080.mp4', 15638561, 0, 'file', '2026-03-04 06:39:21'),
(716, 'RLfpIr3lS3MB', 7, 'umie12', 'Shared a file: Multimedia LAB TUTORIAL 3.docx', 'uploads/chat_files/69a7d3a5b228b_Multimedia LAB TUTORIAL 3.docx', 'Multimedia LAB TUTORIAL 3.docx', 33306, 0, 'file', '2026-03-04 06:39:33'),
(717, 'RLfpIr3lS3MB', 7, 'umie12', 'Shared a file: multimedia test.pdf', 'uploads/chat_files/69a7d3afbd90b_multimedia test.pdf', 'multimedia test.pdf', 427997, 0, 'file', '2026-03-04 06:39:43'),
(718, 'ZvdQVtzE9csB', 4, 'Admin', '👋 Admin left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-04 07:14:34'),
(719, 'RLfpIr3lS3MB', 1, 'SCSJ2200858', '👋 SCSJ2200858 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-04 07:27:00'),
(720, 'RLfpIr3lS3MB', 7, 'umie12', '🔇 SCSJ2200858 was muted by the host', NULL, NULL, NULL, 1, 'mute', '2026-03-04 07:28:10'),
(721, 'd9fq7iq9dhZc', 1, 'SCSJ2200858', '👋 SCSJ2200858 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-04 17:03:37'),
(722, 'd9fq7iq9dhZc', 1, 'SCSJ2200858', '👋 SCSJ2200858 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-04 17:08:17'),
(723, 'yB4G9oZeXath', 1, 'SCSJ2200858', '👋 SCSJ2200858 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-04 17:09:17'),
(724, 'sXKrsXowBwa5', 7, 'umie12', '🔇 Admin was muted by the host', NULL, NULL, NULL, 1, 'mute', '2026-03-05 04:36:28'),
(725, 'sXKrsXowBwa5', 7, 'umie12', '🔊 Admin was unmuted by the host', NULL, NULL, NULL, 1, 'unmute', '2026-03-05 04:36:32'),
(726, 'ETjsNDYW118w', 1, 'SCSJ2200858', '🔇 SCSJ2200057 was muted by the host', NULL, NULL, NULL, 1, 'mute', '2026-03-05 06:42:53'),
(727, 'ETjsNDYW118w', 1, 'SCSJ2200858', 'Shared a file: TestCaseTemplate.xlsx', 'uploads/chat_files/69a92604756cd_TestCaseTemplate.xlsx', 'TestCaseTemplate.xlsx', 28141, 0, 'file', '2026-03-05 06:43:16'),
(728, 'ETjsNDYW118w', 1, 'SCSJ2200858', 'Shared a file: flash.jpg', 'uploads/chat_files/69a9260ecab8e_flash.jpg', 'flash.jpg', 8266, 0, 'file', '2026-03-05 06:43:26'),
(729, 'ETjsNDYW118w', 1, 'SCSJ2200858', '🚫 SCSJ2200057 was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-05 06:44:15'),
(730, 'ETjsNDYW118w', 5, 'SCSJ2200057', '👋 SCSJ2200057 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-05 06:44:16'),
(731, 'RIAuGiY0Osxm', 7, 'umie12', 'Shared a file: users_export_2026-03-06 (2).csv', 'uploads/chat_files/69ac002a19de3_users_export_2026-03-06 (2).csv', 'users_export_2026-03-06 (2).csv', 655, 0, 'file', '2026-03-07 10:38:34'),
(732, 'FcI5zvdkjqy6', 7, 'umie12', '🚫 SCSJ2200858 was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-07 11:02:42'),
(733, 'FcI5zvdkjqy6', 1, 'SCSJ2200858', '👋 SCSJ2200858 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-07 11:02:44'),
(734, 'FcI5zvdkjqy6', 7, 'umie12', 'test', NULL, NULL, NULL, 0, 'chat', '2026-03-07 11:03:10'),
(735, 'afpfIxu8aZZg', 7, 'umie12', '🔇 SCSJ2200858 was muted by the host', NULL, NULL, NULL, 1, 'mute', '2026-03-07 11:38:17'),
(736, 'afpfIxu8aZZg', 7, 'umie12', '🔊 SCSJ2200858 was unmuted by the host', NULL, NULL, NULL, 1, 'unmute', '2026-03-07 11:38:20'),
(737, 'afpfIxu8aZZg', 1, 'SCSJ2200858', 'test', NULL, NULL, NULL, 0, 'chat', '2026-03-07 11:39:09'),
(738, 'lI2y1gLmyi3O', 1, 'SCSJ2200858', 'test', NULL, NULL, NULL, 0, 'chat', '2026-03-07 12:09:29'),
(739, 'lI2y1gLmyi3O', 1, 'SCSJ2200858', '🚫 umie12 was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-07 12:11:25'),
(740, 'lI2y1gLmyi3O', 1, 'SCSJ2200858', 'test', NULL, NULL, NULL, 0, 'chat', '2026-03-07 12:11:53'),
(741, 'lI2y1gLmyi3O', 1, 'SCSJ2200858', '🚫 umie12 was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-07 12:11:59'),
(742, 'lI2y1gLmyi3O', 1, 'SCSJ2200858', '🔇 umie12 was muted by the host', NULL, NULL, NULL, 1, 'mute', '2026-03-07 12:12:37'),
(743, 'lI2y1gLmyi3O', 1, 'SCSJ2200858', '🚫 umie12 was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-07 12:12:45'),
(744, 'lI2y1gLmyi3O', 1, 'SCSJ2200858', '🚫 umie12 was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-07 12:13:14'),
(745, 'lI2y1gLmyi3O', 7, 'umie12', 'test', NULL, NULL, NULL, 0, 'chat', '2026-03-07 12:14:01'),
(746, 'lI2y1gLmyi3O', 1, 'SCSJ2200858', '🚫 umie12 was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-07 12:14:17'),
(747, 'lI2y1gLmyi3O', 1, 'SCSJ2200858', '🚫 umie12 was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-07 12:14:42'),
(748, 'jb3Ulapkggum', 7, 'umie12', '🚫 SCSJ2200858 was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-07 12:47:24'),
(749, 'jb3Ulapkggum', 1, 'SCSJ2200858', '👋 SCSJ2200858 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-07 12:47:27'),
(750, '0pPNhlFBomJE', 1, 'SCSJ2200858', '🚫 umie12 was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-07 13:22:31'),
(751, '0pPNhlFBomJE', 1, 'SCSJ2200858', '🚫 umie12 was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-07 13:22:58'),
(752, '82W7jWYdLvBc', 7, 'umie12', '🔇 SCSJ2200858 was muted by the host', NULL, NULL, NULL, 1, 'mute', '2026-03-07 13:29:11'),
(753, '82W7jWYdLvBc', 7, 'umie12', '🔊 SCSJ2200858 was unmuted by the host', NULL, NULL, NULL, 1, 'unmute', '2026-03-07 13:29:18'),
(754, '82W7jWYdLvBc', 7, 'umie12', '🚫 SCSJ2200858 was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-07 13:29:21'),
(755, '82W7jWYdLvBc', 7, 'umie12', '🚫 SCSJ2200858 was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-07 13:30:00'),
(756, '4onMYeXUNKbL', 7, 'umie12', '🚫 SCSJ2200858 was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-07 14:42:02'),
(757, '4onMYeXUNKbL', 1, 'SCSJ2200858', '👋 SCSJ2200858 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-07 14:42:02'),
(758, 'EQQrlOWFC7Vz', 13, 'mastura', 'hello', NULL, NULL, NULL, 0, 'chat', '2026-03-09 03:06:02'),
(759, 'EQQrlOWFC7Vz', 13, 'mastura', 'everybody here', NULL, NULL, NULL, 0, 'chat', '2026-03-09 03:06:08'),
(760, 'EQQrlOWFC7Vz', 13, 'mastura', 'saasda', NULL, NULL, NULL, 0, 'chat', '2026-03-09 03:06:25'),
(761, 'EQQrlOWFC7Vz', 13, 'mastura', 'daefes', NULL, NULL, NULL, 0, 'chat', '2026-03-09 03:06:26'),
(762, 'EQQrlOWFC7Vz', 13, 'mastura', 'awgf', NULL, NULL, NULL, 0, 'chat', '2026-03-09 03:06:27'),
(763, 'EQQrlOWFC7Vz', 13, 'mastura', 'bfjrow', NULL, NULL, NULL, 0, 'chat', '2026-03-09 03:06:33'),
(764, 'EQQrlOWFC7Vz', 13, 'mastura', 'send', NULL, NULL, NULL, 0, 'chat', '2026-03-09 03:06:56'),
(765, 'EQQrlOWFC7Vz', 13, 'mastura', 'Shared a file: GUIDELINEFORCONCLUSION.pptx', 'uploads/chat_files/69ae396f97041_GUIDELINEFORCONCLUSION.pptx', 'GUIDELINEFORCONCLUSION.pptx', 367984, 0, 'file', '2026-03-09 03:07:27'),
(766, 'PfdtaBBB8F59', 13, 'mastura', '🔇 SCSJ2200858 was muted by the host', NULL, NULL, NULL, 1, 'mute', '2026-03-09 03:26:43'),
(767, 'PfdtaBBB8F59', 13, 'mastura', '🚫 SCSJ2200858 was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-09 03:27:02'),
(768, 'PfdtaBBB8F59', 1, 'SCSJ2200858', '👋 SCSJ2200858 left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-09 03:27:04'),
(769, 'fl0qlwLV95Gv', 14, 'chongyi', '🔇 SCSJ2200858 was muted by the host', NULL, NULL, NULL, 1, 'mute', '2026-03-09 03:59:02'),
(770, 'fl0qlwLV95Gv', 14, 'chongyi', 'yyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy', NULL, NULL, NULL, 0, 'chat', '2026-03-09 04:00:25'),
(771, 'haCTAPJq3aG1', 1, 'SCSJ2200858', 'asd', NULL, NULL, NULL, 0, 'chat', '2026-03-09 13:05:32'),
(772, 'haCTAPJq3aG1', 1, 'SCSJ2200858', '🚫 mastura was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-09 13:32:53'),
(773, 'haCTAPJq3aG1', 1, 'SCSJ2200858', '🚫 mastura was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-09 13:33:21'),
(774, '3YDO6vRrZiTG', 1, 'SCSJ2200858', '🚫 mastura was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-09 13:34:56'),
(775, '3YDO6vRrZiTG', 1, 'SCSJ2200858', 'asd', NULL, NULL, NULL, 0, 'chat', '2026-03-09 13:39:34'),
(776, '3YDO6vRrZiTG', 1, 'SCSJ2200858', 'asdsa', NULL, NULL, NULL, 0, 'chat', '2026-03-09 13:39:36'),
(777, '3YDO6vRrZiTG', 1, 'SCSJ2200858', 'asdasd', NULL, NULL, NULL, 0, 'chat', '2026-03-09 13:39:37');
INSERT INTO `chat_messages` (`id`, `room_id`, `user_id`, `username`, `message`, `file_path`, `file_name`, `file_size`, `is_system_message`, `message_type`, `created_at`) VALUES
(778, '3YDO6vRrZiTG', 1, 'SCSJ2200858', 'asdadas', NULL, NULL, NULL, 0, 'chat', '2026-03-09 13:39:38'),
(779, '3YDO6vRrZiTG', 1, 'SCSJ2200858', 'asdads', NULL, NULL, NULL, 0, 'chat', '2026-03-09 13:39:39'),
(780, '3YDO6vRrZiTG', 1, 'SCSJ2200858', 'asdad', NULL, NULL, NULL, 0, 'chat', '2026-03-09 13:39:41'),
(781, 'haCTAPJq3aG1', 1, 'SCSJ2200858', 'asdadad', NULL, NULL, NULL, 0, 'chat', '2026-03-09 13:40:00'),
(782, 'haCTAPJq3aG1', 1, 'SCSJ2200858', 'asdsadd', NULL, NULL, NULL, 0, 'chat', '2026-03-09 13:40:02'),
(783, 'haCTAPJq3aG1', 1, 'SCSJ2200858', 'asdasdad', NULL, NULL, NULL, 0, 'chat', '2026-03-09 13:40:03'),
(784, 'eJmuKoxqcGd8', 1, 'SCSJ2200858', '🚫 mastura was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-09 13:41:16'),
(785, 'eJmuKoxqcGd8', 1, 'SCSJ2200858', '🚫 mastura was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-09 13:41:35'),
(786, 'eJmuKoxqcGd8', 1, 'SCSJ2200858', '🚫 mastura was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-09 13:41:53'),
(787, 'eJmuKoxqcGd8', 1, 'SCSJ2200858', '🚫 mastura was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-09 14:24:54'),
(788, 'eJmuKoxqcGd8', 1, 'SCSJ2200858', '🚫 mastura was removed from the meeting by the host', NULL, NULL, NULL, 1, 'kick', '2026-03-09 14:25:25'),
(789, 'eJmuKoxqcGd8', 13, 'mastura', '👋 mastura left the meeting', NULL, NULL, NULL, 1, 'leave', '2026-03-09 14:25:36');

-- --------------------------------------------------------

--
-- Table structure for table `friends`
--

CREATE TABLE `friends` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `friend_id` int(11) NOT NULL,
  `status` enum('pending','accepted','blocked') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `accepted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `friends`
--

INSERT INTO `friends` (`id`, `user_id`, `friend_id`, `status`, `requested_at`, `accepted_at`) VALUES
(7, 1, 6, 'accepted', '2026-03-03 18:34:35', NULL),
(14, 7, 1, 'accepted', '2026-03-06 10:02:58', NULL),
(15, 1, 9, 'accepted', '2026-03-07 12:34:57', NULL),
(16, 1, 10, 'accepted', '2026-03-07 12:34:58', NULL),
(17, 1, 11, 'accepted', '2026-03-07 12:34:58', NULL),
(19, 13, 7, 'pending', '2026-03-09 03:21:09', NULL),
(22, 13, 12, 'pending', '2026-03-09 03:21:12', NULL),
(23, 13, 11, 'pending', '2026-03-09 03:21:14', NULL),
(24, 13, 10, 'pending', '2026-03-09 03:21:15', NULL),
(25, 13, 9, 'pending', '2026-03-09 03:21:16', NULL),
(26, 1, 5, 'accepted', '2026-03-10 06:19:56', NULL),
(27, 1, 15, 'accepted', '2026-03-10 06:20:28', NULL),
(28, 1, 12, 'accepted', '2026-03-16 11:13:45', NULL),
(29, 16, 1, 'accepted', '2026-03-16 11:15:08', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `groups`
--

CREATE TABLE `groups` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `avatar_url` varchar(500) DEFAULT NULL,
  `is_private` tinyint(1) DEFAULT 0,
  `join_type` enum('open','invite_only') DEFAULT 'invite_only',
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `groups`
--

INSERT INTO `groups` (`id`, `name`, `description`, `created_by`, `created_at`, `avatar_url`, `is_private`, `join_type`, `is_deleted`, `deleted_at`, `deleted_by`) VALUES
(1, 'groupchat_test', 'test', 1, '2026-01-31 04:45:10', NULL, 0, 'invite_only', 1, '2026-03-09 05:55:38', 1),
(2, '343', '', 7, '2026-03-04 06:44:54', NULL, 0, 'invite_only', 0, NULL, NULL),
(3, 'gossip 1.0', 'for student only', 13, '2026-03-09 03:21:43', NULL, 0, 'invite_only', 0, NULL, NULL),
(4, 'asdfghjklhbhjvgbljkk vhjcvjjvhjjbhlhnklklmlohiol.nlkjk', '', 13, '2026-03-09 03:31:22', NULL, 0, 'invite_only', 1, '2026-03-09 13:13:52', 13),
(6, 'asdasdasdasdasdasd', '', 1, '2026-03-09 05:33:36', NULL, 0, 'invite_only', 1, '2026-03-09 11:39:50', 1),
(7, 'asdasdasdasdasd', '', 1, '2026-03-09 05:34:06', NULL, 0, 'invite_only', 1, '2026-03-09 05:55:30', 1),
(8, 'group asdasdasdasdasda', '', 1, '2026-03-09 05:56:18', NULL, 0, 'invite_only', 1, '2026-03-09 05:56:27', 1),
(9, 'asdadasdsadsaas', '', 1, '2026-03-09 11:51:14', NULL, 0, 'invite_only', 1, '2026-03-10 06:19:25', 1),
(10, 'test', 'test', 15, '2026-03-09 17:37:39', NULL, 0, 'invite_only', 0, NULL, NULL),
(11, 'testing', 'asdads', 5, '2026-03-10 06:22:58', NULL, 0, 'invite_only', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `group_members`
--

CREATE TABLE `group_members` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('admin','member') DEFAULT 'member',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_read_message_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_members`
--

INSERT INTO `group_members` (`id`, `group_id`, `user_id`, `role`, `joined_at`, `last_read_message_id`) VALUES
(1, 1, 1, 'admin', '2026-01-31 04:45:10', 39),
(9, 1, 5, 'member', '2026-02-28 04:59:20', 9),
(10, 2, 7, 'admin', '2026-03-04 06:44:54', 45),
(12, 2, 1, 'member', '2026-03-06 09:28:52', 45),
(13, 2, 9, 'member', '2026-03-06 10:30:32', 36),
(14, 3, 13, 'admin', '2026-03-09 03:21:43', 42),
(16, 4, 13, 'admin', '2026-03-09 03:31:22', NULL),
(17, 5, 14, 'admin', '2026-03-09 03:54:46', NULL),
(18, 6, 1, 'admin', '2026-03-09 05:33:36', 47),
(19, 7, 1, 'admin', '2026-03-09 05:34:06', NULL),
(20, 8, 1, 'admin', '2026-03-09 05:56:18', NULL),
(21, 6, 5, 'member', '2026-03-09 11:36:26', 47),
(22, 9, 1, 'admin', '2026-03-09 11:51:14', NULL),
(23, 10, 15, 'admin', '2026-03-09 17:37:39', 52),
(24, 10, 13, 'member', '2026-03-09 17:38:30', 53),
(25, 11, 5, 'admin', '2026-03-10 06:22:58', 54),
(26, 11, 1, 'member', '2026-03-10 06:23:06', 54);

-- --------------------------------------------------------

--
-- Table structure for table `group_messages`
--

CREATE TABLE `group_messages` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_messages`
--

INSERT INTO `group_messages` (`id`, `group_id`, `sender_id`, `message`, `created_at`, `is_deleted`) VALUES
(1, 1, 1, 'testing', '2026-01-31 04:45:51', 1),
(2, 1, 1, 'User has been removed from the group by an admin.', '2026-02-27 13:29:46', 1),
(3, 1, 1, 'SCSJ2200057 has been removed from the group by an admin.', '2026-02-27 13:48:31', 1),
(4, 1, 1, 'SCSJ2200057 has been removed from the group.', '2026-02-27 13:50:52', 1),
(5, 1, 1, 'SCSJ2200057 has been removed from the group.', '2026-02-28 04:19:22', 1),
(6, 1, 1, 'SCSJ2200057 has been removed from the group.', '2026-02-28 04:35:46', 1),
(7, 1, 1, 'SCSJ2200057 has been removed from the group.', '2026-02-28 04:36:12', 1),
(8, 1, 1, 'SCSJ2200057 has been removed from the group.', '2026-02-28 04:59:09', 1),
(9, 1, 1, 'SCSJ2200057 has been added to the group by an admin.', '2026-02-28 04:59:20', 1),
(10, 2, 7, 'geg', '2026-03-04 06:45:06', 0),
(11, 2, 7, '', '2026-03-04 06:45:14', 0),
(12, 2, 7, '', '2026-03-04 06:45:27', 0),
(13, 2, 7, '', '2026-03-04 06:45:37', 0),
(14, 2, 7, '', '2026-03-04 06:45:56', 0),
(15, 2, 7, 'SCSJ2200858 has been added to the group by an admin.', '2026-03-04 16:33:44', 0),
(16, 2, 7, 'test', '2026-03-04 16:33:49', 0),
(17, 2, 7, '', '2026-03-05 04:23:01', 0),
(18, 2, 7, '', '2026-03-05 04:23:22', 0),
(19, 2, 7, '', '2026-03-05 04:55:32', 0),
(20, 2, 7, '', '2026-03-05 05:15:44', 1),
(21, 2, 7, '', '2026-03-05 05:33:51', 1),
(22, 2, 1, 'test', '2026-03-05 09:29:24', 0),
(23, 2, 1, '', '2026-03-05 09:29:29', 1),
(24, 2, 7, '', '2026-03-05 10:03:35', 1),
(25, 2, 7, '', '2026-03-05 10:03:59', 0),
(26, 2, 7, '', '2026-03-05 11:05:43', 0),
(27, 2, 7, 'asd', '2026-03-05 11:29:11', 1),
(28, 2, 7, '', '2026-03-05 11:29:19', 0),
(29, 1, 1, '', '2026-03-05 11:30:06', 1),
(30, 2, 1, '', '2026-03-05 11:55:03', 0),
(31, 2, 1, '', '2026-03-05 11:55:08', 0),
(32, 2, 1, '', '2026-03-05 11:55:14', 0),
(33, 2, 7, '', '2026-03-06 09:28:13', 0),
(34, 2, 7, 'SCSJ2200858 has been removed from the group.', '2026-03-06 09:28:43', 1),
(35, 2, 7, 'SCSJ2200858 has been added to the group by an admin.', '2026-03-06 09:28:52', 1),
(36, 2, 1, 'hi', '2026-03-06 10:29:54', 0),
(37, 2, 7, 'SCSJ2200001 has been added to the group by an admin.', '2026-03-06 10:30:32', 1),
(38, 1, 1, '', '2026-03-06 10:31:38', 1),
(39, 1, 1, '', '2026-03-06 10:32:06', 1),
(40, 2, 7, 'test', '2026-03-07 11:25:44', 1),
(41, 3, 13, 'umie12 has been added to the group by an admin.', '2026-03-09 03:21:53', 0),
(42, 3, 13, 'umie12 has been removed from the group.', '2026-03-09 03:28:32', 0),
(43, 6, 1, 'd', '2026-03-09 05:33:51', 1),
(44, 2, 1, 'test', '2026-03-09 11:36:05', 0),
(45, 2, 1, '', '2026-03-09 11:36:13', 0),
(46, 6, 1, 'SCSJ2200057 has been added to the group by an admin.', '2026-03-09 11:36:26', 1),
(47, 6, 1, 'test', '2026-03-09 11:36:29', 1),
(48, 10, 15, 'test', '2026-03-09 17:37:45', 0),
(49, 10, 15, '', '2026-03-09 17:37:57', 0),
(50, 10, 15, '', '2026-03-09 17:38:01', 0),
(51, 10, 15, '', '2026-03-09 17:38:09', 0),
(52, 10, 15, 'mastura has been added to the group by an admin.', '2026-03-09 17:38:30', 0),
(53, 10, 15, 'asd', '2026-03-09 18:03:02', 0),
(54, 11, 5, 'SCSJ2200858 has been added to the group by an admin.', '2026-03-10 06:23:06', 0);

-- --------------------------------------------------------

--
-- Table structure for table `group_message_files`
--

CREATE TABLE `group_message_files` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_message_files`
--

INSERT INTO `group_message_files` (`id`, `message_id`, `file_name`, `file_path`, `file_size`, `file_type`, `uploaded_at`) VALUES
(1, 30, 'dog.jpg', 'uploads/chat_files/69a96f17254ec_dog.jpg', 5759, 'image/jpeg', '2026-03-05 11:55:03'),
(2, 31, 'PRG4034-TUTORIAL1.docx', 'uploads/chat_files/69a96f1c60d89_PRG4034-TUTORIAL1.docx', 20901, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '2026-03-05 11:55:08'),
(3, 32, 'Video_conferencing_as_a_face-to-face_online_meetin.pdf', 'uploads/chat_files/69a96f2201658_Video_conferencing_as_a_face-to-face_online_meetin.pdf', 745256, 'application/pdf', '2026-03-05 11:55:14'),
(4, 33, 'exam docket.pdf', 'uploads/chat_files/69aa9e2d87eb5_exam docket.pdf', 34477, 'application/pdf', '2026-03-06 09:28:13'),
(5, 38, 'flash.jpg', 'uploads/chat_files/69aaad0a10e5c_flash.jpg', 8266, 'image/jpeg', '2026-03-06 10:31:38'),
(6, 39, 'PRG4034-TUTORIAL1.docx', 'uploads/chat_files/69aaad26bb3c8_PRG4034-TUTORIAL1.docx', 20901, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '2026-03-06 10:32:06'),
(7, 45, 'INTERIM REPORT.zip', 'uploads/chat_files/69aeb0ad2297a_INTERIM REPORT.zip', 5068392, 'application/x-zip-compressed', '2026-03-09 11:36:13'),
(8, 49, 'CPT4214 INTERIM_REPORT.pdf', 'uploads/chat_files/69af057513805_CPT4214 INTERIM_REPORT.pdf', 1829162, 'application/pdf', '2026-03-09 17:37:57'),
(9, 50, 'flash.jpg', 'uploads/chat_files/69af05790f202_flash.jpg', 8266, 'image/jpeg', '2026-03-09 17:38:01'),
(10, 51, 'SDD4023  DEC 2024 TEST (MS).docx', 'uploads/chat_files/69af0581751db_SDD4023  DEC 2024 TEST (MS).docx', 549580, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '2026-03-09 17:38:09');

-- --------------------------------------------------------

--
-- Table structure for table `host_actions`
--

CREATE TABLE `host_actions` (
  `id` int(11) NOT NULL,
  `room_id` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `target_user_id` int(11) NOT NULL,
  `host_user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `host_actions`
--

INSERT INTO `host_actions` (`id`, `room_id`, `action`, `target_user_id`, `host_user_id`, `created_at`) VALUES
(4, 's5slazxFBPh2', 'mute', 1, 5, '2026-02-28 18:44:57'),
(5, 's5slazxFBPh2', 'unmute', 1, 5, '2026-02-28 18:45:01'),
(6, 's5slazxFBPh2', 'kick', 1, 5, '2026-02-28 18:45:03'),
(7, 's5slazxFBPh2', 'mute', 1, 5, '2026-02-28 18:51:32'),
(8, 's5slazxFBPh2', 'unmute', 1, 5, '2026-02-28 18:51:33'),
(9, 's5slazxFBPh2', 'mute', 1, 5, '2026-02-28 18:51:37'),
(10, 's5slazxFBPh2', 'unmute', 1, 5, '2026-02-28 18:51:39'),
(11, 's5slazxFBPh2', 'kick', 1, 5, '2026-02-28 18:51:52'),
(12, 's5slazxFBPh2', 'kick', 1, 5, '2026-02-28 18:51:52'),
(13, 'Z78snbxCnPoK', 'mute', 5, 1, '2026-03-03 18:19:45'),
(14, 'Z78snbxCnPoK', 'unmute', 5, 1, '2026-03-03 18:19:47'),
(15, 'Z78snbxCnPoK', 'kick', 5, 1, '2026-03-03 18:19:49'),
(21, 'Q8ofsUnaXoPP', 'mute', 4, 1, '2026-03-03 19:35:31'),
(22, 'Q8ofsUnaXoPP', 'unmute', 4, 1, '2026-03-03 19:35:32'),
(23, 'Q8ofsUnaXoPP', 'kick', 4, 1, '2026-03-03 19:35:34'),
(24, 'Q8ofsUnaXoPP', 'kick', 4, 1, '2026-03-03 19:46:03'),
(25, '2uB7BK8FRVlt', 'mute', 1, 5, '2026-03-03 19:48:20'),
(26, '2uB7BK8FRVlt', 'unmute', 1, 5, '2026-03-03 19:48:23'),
(27, '2uB7BK8FRVlt', 'kick', 1, 5, '2026-03-03 19:48:27'),
(28, 'Q8ofsUnaXoPP', 'kick', 5, 1, '2026-03-03 20:12:33'),
(29, 'RLfpIr3lS3MB', 'mute', 1, 7, '2026-03-04 07:28:10'),
(30, 'sXKrsXowBwa5', 'mute', 4, 7, '2026-03-05 04:36:28'),
(31, 'sXKrsXowBwa5', 'unmute', 4, 7, '2026-03-05 04:36:32'),
(32, 'ETjsNDYW118w', 'mute', 5, 1, '2026-03-05 06:42:53'),
(33, 'ETjsNDYW118w', 'kick', 5, 1, '2026-03-05 06:44:15'),
(34, 'FcI5zvdkjqy6', 'kick', 1, 7, '2026-03-07 11:02:42'),
(35, 'afpfIxu8aZZg', 'mute', 1, 7, '2026-03-07 11:38:17'),
(36, 'afpfIxu8aZZg', 'unmute', 1, 7, '2026-03-07 11:38:20'),
(37, 'lI2y1gLmyi3O', 'kick', 7, 1, '2026-03-07 12:11:25'),
(38, 'lI2y1gLmyi3O', 'kick', 7, 1, '2026-03-07 12:11:59'),
(39, 'lI2y1gLmyi3O', 'mute', 7, 1, '2026-03-07 12:12:37'),
(40, 'lI2y1gLmyi3O', 'kick', 7, 1, '2026-03-07 12:12:45'),
(41, 'lI2y1gLmyi3O', 'kick', 7, 1, '2026-03-07 12:13:14'),
(42, 'lI2y1gLmyi3O', 'kick', 7, 1, '2026-03-07 12:14:17'),
(43, 'lI2y1gLmyi3O', 'kick', 7, 1, '2026-03-07 12:14:42'),
(44, 'jb3Ulapkggum', 'kick', 1, 7, '2026-03-07 12:47:24'),
(45, '0pPNhlFBomJE', 'kick', 7, 1, '2026-03-07 13:22:31'),
(46, '0pPNhlFBomJE', 'kick', 7, 1, '2026-03-07 13:22:58'),
(47, '82W7jWYdLvBc', 'mute', 1, 7, '2026-03-07 13:29:11'),
(48, '82W7jWYdLvBc', 'unmute', 1, 7, '2026-03-07 13:29:18'),
(49, '82W7jWYdLvBc', 'kick', 1, 7, '2026-03-07 13:29:21'),
(50, '82W7jWYdLvBc', 'kick', 1, 7, '2026-03-07 13:30:00'),
(51, '4onMYeXUNKbL', 'kick', 1, 7, '2026-03-07 14:42:02'),
(52, 'PfdtaBBB8F59', 'mute', 1, 13, '2026-03-09 03:26:43'),
(53, 'PfdtaBBB8F59', 'kick', 1, 13, '2026-03-09 03:27:02'),
(54, 'fl0qlwLV95Gv', 'mute', 1, 14, '2026-03-09 03:59:02'),
(55, 'haCTAPJq3aG1', 'kick', 13, 1, '2026-03-09 13:32:53'),
(56, 'haCTAPJq3aG1', 'kick', 13, 1, '2026-03-09 13:33:21'),
(57, '3YDO6vRrZiTG', 'kick', 13, 1, '2026-03-09 13:34:56'),
(58, 'eJmuKoxqcGd8', 'kick', 13, 1, '2026-03-09 13:41:16'),
(59, 'eJmuKoxqcGd8', 'kick', 13, 1, '2026-03-09 13:41:35'),
(60, 'eJmuKoxqcGd8', 'kick', 13, 1, '2026-03-09 13:41:53'),
(61, 'eJmuKoxqcGd8', 'kick', 13, 1, '2026-03-09 14:24:54'),
(62, 'eJmuKoxqcGd8', 'kick', 13, 1, '2026-03-09 14:25:25');

-- --------------------------------------------------------

--
-- Table structure for table `meetings`
--

CREATE TABLE `meetings` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `host_id` int(11) NOT NULL,
  `meeting_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `participants` text DEFAULT NULL,
  `status` enum('scheduled','ongoing','completed','cancelled') DEFAULT 'scheduled',
  `room_id` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `is_password_protected` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `username` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meetings`
--

INSERT INTO `meetings` (`id`, `title`, `description`, `host_id`, `meeting_date`, `start_time`, `end_time`, `participants`, `status`, `room_id`, `password`, `is_password_protected`, `created_at`, `updated_at`, `username`) VALUES
(33, 'Instant Meeting - Jan 22, 1:28 PM', 'Instant meeting started by SCSJ2200057', 5, '2026-01-22', '20:28:23', '21:28:23', NULL, 'ongoing', 'kbeMVfuezJa9', NULL, 0, '2026-01-22 12:28:23', '2026-01-22 12:28:23', ''),
(40, 'Instant Meeting - Jan 24, 2:42 PM', 'Instant meeting started by SCSJ2200057', 5, '2026-01-24', '21:42:39', '22:42:39', NULL, 'ongoing', 'QEgP42fCnJui', NULL, 0, '2026-01-24 13:42:39', '2026-01-24 13:42:39', ''),
(41, 'Instant Meeting - Jan 24, 10:28 PM', 'Instant meeting started by SCSJ2200858', 1, '2026-01-24', '22:28:21', '23:28:21', NULL, 'ongoing', 'F1OPEybAZY3k', NULL, 0, '2026-01-24 14:28:21', '2026-01-24 14:28:21', ''),
(42, 'Instant Meeting - Jan 24, 10:30 PM', 'Instant meeting started by SCSJ2200858', 1, '2026-01-24', '22:30:09', '23:30:09', NULL, 'ongoing', 'RLRUVZl8le2e', NULL, 0, '2026-01-24 14:30:09', '2026-01-24 14:30:09', ''),
(43, 'Instant Meeting - Jan 26, 3:04 PM', 'Instant meeting started by SCSJ2200858', 1, '2026-01-26', '15:04:07', '16:04:07', NULL, 'ongoing', 'cwmJAZpVMa1c', NULL, 0, '2026-01-26 07:04:07', '2026-01-26 07:04:07', 'SCSJ2200858'),
(44, 'Instant Meeting - Jan 28, 10:32 AM', 'Instant meeting started by SCSJ2200858', 1, '2026-01-28', '10:32:46', '11:32:46', NULL, 'ongoing', 'EeqSm83FqmV1', NULL, 0, '2026-01-28 02:32:46', '2026-01-28 02:32:46', 'SCSJ2200858'),
(47, 'Instant Meeting - Jan 31, 3:08 PM', 'Instant meeting started by SCSJ2200858', 1, '2026-01-31', '15:08:20', '16:08:20', NULL, 'ongoing', 'hs9yQbEZLBpE', NULL, 0, '2026-01-31 07:08:20', '2026-01-31 07:08:20', 'SCSJ2200858'),
(50, 'Meeting - Feb 12, 05:44 PM', 'test', 1, '2026-02-12', '17:44:57', '18:44:57', NULL, 'ongoing', 'WmAATo35mDw7', '$2y$10$hAMrheFsULO/q50GruJv8O0YL8X3/Q8qSW9Kq4QX9vZ1/DTDianJ6', 1, '2026-02-12 09:44:57', '2026-02-12 09:44:57', 'SCSJ2200858'),
(51, 'Meeting - Feb 13, 05:08 PM', '', 1, '2026-02-13', '17:08:18', '18:08:18', NULL, 'ongoing', 'jApTxS4Oe9AZ', NULL, 0, '2026-02-13 09:08:18', '2026-02-13 09:08:18', 'SCSJ2200858'),
(52, 'Meeting - Feb 13, 06:27 PM', 'test', 5, '2026-02-13', '18:27:19', '19:27:19', NULL, 'ongoing', '4QCK4IVyKF3D', '$2y$10$Lzlv2vqc6oVOaSXQCMVmWOSk3vu.ne/YpMEfnzeojbXS7oZ6MYYOW', 1, '2026-02-13 10:27:19', '2026-02-13 10:27:19', 'SCSJ2200057'),
(54, 'Meeting - Feb 14, 02:58 PM', '', 1, '2026-02-14', '14:58:43', '15:58:43', NULL, 'ongoing', '7ZAbUId4aBJd', NULL, 0, '2026-02-14 06:58:43', '2026-02-14 06:58:43', 'SCSJ2200858'),
(56, 'Meeting - Feb 14, 04:43 PM', '', 5, '2026-02-14', '16:43:47', '17:43:47', NULL, 'ongoing', 'DJYa6btnUC2t', NULL, 0, '2026-02-14 08:43:47', '2026-02-14 08:43:47', 'SCSJ2200057'),
(57, 'Meeting - Feb 15, 12:24 AM', '', 5, '2026-02-15', '00:24:52', '01:24:52', NULL, 'ongoing', 'MAQSebXJ6eBC', '$2y$10$Ntkeo2wKkFG7MRbHydvHfuTuZhZXMupXyDydr3vv1boo29eeW9ohO', 1, '2026-02-14 16:24:52', '2026-02-14 16:24:52', 'SCSJ2200057'),
(58, 'Meeting - Feb 15, 12:49 AM', '', 1, '2026-02-15', '00:49:14', '01:49:14', NULL, 'ongoing', 'bpHx1GtmmOYJ', NULL, 0, '2026-02-14 16:49:14', '2026-02-14 16:49:14', 'SCSJ2200858'),
(63, 'Meeting - Feb 25, 02:02 PM', '', 1, '2026-02-25', '14:02:19', '15:02:19', NULL, 'ongoing', 'uBCL2afemBlx', '$2y$10$nh3yR67u8.pEBXY3pC.M2umNxe5vzyGw1XhmNqbL7/8KfsFXumw7G', 1, '2026-02-25 06:02:19', '2026-02-25 06:02:19', 'SCSJ2200858'),
(67, 'Meeting - Feb 26, 01:40 PM', '', 1, '2026-02-26', '13:40:15', '14:40:15', NULL, 'ongoing', 'Tgez3blyGbsp', '$2y$10$XPJ7KNyKvoysE90Exc9eo.NrPQm5rK2HJ/KID6.ZSo5BE1m8w7UYq', 1, '2026-02-26 05:40:15', '2026-02-26 05:40:15', 'SCSJ2200858'),
(69, 'Meeting - Feb 26, 02:21 PM', '', 1, '2026-02-26', '14:21:47', '15:21:47', NULL, 'ongoing', 'RL7Gk9tqO1hZ', '$2y$10$Oafy3mWsTLiWDi56JeiV6.3uSfIVBstZPLmwf/KcuZS0a3MeuH.By', 1, '2026-02-26 06:21:47', '2026-02-26 06:21:47', 'SCSJ2200858'),
(80, 'Meeting - Feb 26, 03:56 PM', '312', 1, '2026-02-26', '15:56:53', '16:56:53', NULL, 'ongoing', 'eRTpMdPhgJ3G', '$2y$10$LprwIXI48CCF3TVcJ/nfo.Pyc0Tr8Wx8d.KTxZWxY44Dx2jHOxB2a', 1, '2026-02-26 07:56:53', '2026-02-26 07:56:53', 'SCSJ2200858'),
(84, 'Meeting - Feb 26, 04:35 PM', '', 5, '2026-02-26', '16:35:06', '17:35:06', NULL, 'ongoing', 'YgUBnfQVQxwT', '$2y$10$iaNqdTzhsrZ/nSisfwjwD.yOJvjTPUIgJYEYxC0VErWtQxlMOr/IC', 1, '2026-02-26 08:35:06', '2026-02-26 08:35:06', 'SCSJ2200057'),
(85, 'Meeting - Feb 26, 04:57 PM', '', 1, '2026-02-26', '16:57:39', '17:57:39', NULL, 'ongoing', 'HAvCdElmGc99', '$2y$10$ozEAGwQ/QmyYG/P1wv2HxeWtSGFyOjHnr7c/Ogc3fRZAwBDJKGdvO', 1, '2026-02-26 08:57:39', '2026-02-26 08:57:39', 'SCSJ2200858'),
(86, '123', '123', 5, '2026-02-26', '18:02:00', '19:02:00', NULL, 'scheduled', 'O8DCu3ClQz7a', '$2y$10$m6DW07Eihb6y8QYLc1spgu91kzFouKSdAYvtGu6vQPUnJ0ZWZqidy', 1, '2026-02-26 09:02:49', '2026-02-26 09:02:49', 'SCSJ2200057'),
(87, '123', '', 1, '2026-02-27', '18:08:00', '19:08:00', NULL, 'scheduled', 'IsP5ccYlAdk2', NULL, 0, '2026-02-26 09:09:02', '2026-02-26 09:09:23', 'SCSJ2200858'),
(88, 'Meeting - Feb 27, 11:10 AM', '', 5, '2026-02-27', '11:10:03', '12:10:03', NULL, 'ongoing', 'cv1xXtQDAgaR', '$2y$10$Vx3fggaFuybAL3vx91iXNOPuohkOQ0Jc7G9XM/aKl25H2xnglhFk6', 1, '2026-02-27 03:10:03', '2026-02-27 03:10:03', 'SCSJ2200057'),
(89, 'Meeting - Feb 27, 12:14 PM', '', 5, '2026-02-27', '12:14:59', '13:14:59', NULL, 'ongoing', 'yB4G9oZeXath', '$2y$10$88zmb0Mchtt44WKnsURVL.IvQUcjFKhUdoJ1y7tqGqNL7Ezpc1wR6', 1, '2026-02-27 04:14:59', '2026-02-27 04:14:59', 'SCSJ2200057'),
(90, 'Meeting - Feb 27, 01:26 PM', '', 5, '2026-02-27', '13:27:01', '14:27:01', NULL, 'ongoing', '4jSDKIX8uM7y', '$2y$10$5LlahYSh8RDU6CYZ2/nqU.R4MzfhEaFRcnGov0TyUGdIoG.WPupR2', 1, '2026-02-27 05:27:01', '2026-02-27 05:27:01', 'SCSJ2200057'),
(91, 'Meeting - Feb 27, 07:19 PM', '', 5, '2026-02-27', '19:20:03', '20:20:03', NULL, 'ongoing', 'jCWpBPDJlbWQ', '$2y$10$Fde9uf/Z38vCRBNu0P7bCuYOEf4/2PMOSPpC.7uy6VLdvOGc3Ielm', 1, '2026-02-27 11:20:03', '2026-02-27 11:20:03', 'SCSJ2200057'),
(93, 'Meeting - Feb 27, 07:39 PM', '', 1, '2026-02-27', '19:39:36', '20:39:36', NULL, 'ongoing', 'DLWtnGyfqlSL', '$2y$10$gv/VB7hqDSaphZjRbC1ui.yKvRKh3/jynWHYeiXyJgX6iRbxJOi.a', 1, '2026-02-27 11:39:36', '2026-02-27 11:39:36', 'SCSJ2200858'),
(94, 'Meeting - Feb 27, 08:42 PM', '', 1, '2026-02-27', '20:42:17', '21:42:17', NULL, 'ongoing', 'aZQNKMDVH9V8', NULL, 0, '2026-02-27 12:42:17', '2026-02-27 12:42:17', 'SCSJ2200858'),
(96, 'Meeting - Feb 28, 01:47 PM', '', 1, '2026-02-28', '13:47:32', '14:47:32', NULL, 'ongoing', 'ExPQYR3NESQe', NULL, 0, '2026-02-28 05:47:32', '2026-02-28 05:47:32', 'SCSJ2200858'),
(100, 'Meeting - Feb 28, 05:23 PM', '', 1, '2026-02-28', '17:23:25', '18:23:25', NULL, 'ongoing', 'ArRCYiRUj7Gk', NULL, 0, '2026-02-28 09:23:25', '2026-02-28 09:23:25', 'SCSJ2200858'),
(101, '123', '', 1, '2026-02-28', '18:40:00', '19:40:00', NULL, 'scheduled', 'Ooj3XIoK8Xlu', '$2y$10$CvKq8JkEVODesGu3m78tx.E6ndnMNcaKdF7cDxqUbcJkvXhF/iVxu', 1, '2026-02-28 09:40:32', '2026-02-28 09:40:32', 'SCSJ2200858'),
(103, 'Meeting - Feb 28, 06:26 PM', '', 1, '2026-02-28', '18:26:05', '19:26:05', NULL, 'ongoing', 'NdKb36wqySxJ', '$2y$10$0KjWS9Jw4wIf6.Ny3apNdOrKsaSQ68vM4MRa8oKgaGisInaG6fF9O', 1, '2026-02-28 10:26:05', '2026-02-28 10:26:05', 'SCSJ2200858'),
(106, 'Meeting - Feb 28, 07:00 PM', '', 5, '2026-02-28', '19:00:59', '20:00:59', NULL, 'ongoing', 'vpr4bcYigkIv', NULL, 0, '2026-02-28 11:00:59', '2026-02-28 11:00:59', 'SCSJ2200057'),
(108, '123', '', 1, '2026-02-28', '22:28:00', '23:28:00', NULL, 'scheduled', 'W4xXl16QiVSL', NULL, 0, '2026-02-28 13:28:17', '2026-02-28 13:28:17', 'SCSJ2200858'),
(110, 'Meeting - Mar 1, 02:44 AM', '', 5, '2026-03-01', '02:44:23', '03:44:23', NULL, 'ongoing', 's5slazxFBPh2', '$2y$10$lJne8QgVQEHloWZ8ojHtIuOe3FqAOUgO09VMlCmL6sLTqh3c0KUrO', 1, '2026-02-28 18:44:23', '2026-02-28 18:44:23', 'SCSJ2200057'),
(113, 'Meeting - Mar 4, 03:10 AM', '', 1, '2026-03-04', '03:10:44', '04:10:44', NULL, 'ongoing', 'Q8ofsUnaXoPP', '$2y$10$6zIP1n8hZF1FDhDt64d6u.WHPzuvuTBxSLGKNTxDTC6na9HZuDT6a', 1, '2026-03-03 19:10:44', '2026-03-03 19:10:44', 'SCSJ2200858'),
(114, 'Meeting - Mar 4, 03:47 AM', '', 5, '2026-03-04', '03:47:55', '04:47:55', NULL, 'ongoing', '2uB7BK8FRVlt', '$2y$10$LtznFDgXRdudeRai.PVAeelzd0fqdb7043Depq4TZmGG.KB.4ZEom', 1, '2026-03-03 19:47:55', '2026-03-03 19:47:55', 'SCSJ2200057'),
(115, 'Meeting - Mar 4, 10:19 AM', '', 1, '2026-03-04', '10:19:02', '11:19:02', NULL, 'ongoing', 'eT85mPTo3EL6', NULL, 0, '2026-03-04 02:19:02', '2026-03-04 02:19:02', 'SCSJ2200858'),
(116, 'Meeting - Mar 4, 02:11 PM', '', 1, '2026-03-04', '14:11:11', '15:11:11', NULL, 'ongoing', 'wFx8h3eWInbn', '$2y$10$9Jq/McVjp/UEYo/1UBKdRu3K8FRbp41/Ir/NUUn43Sf6q0w6gQUJ2', 1, '2026-03-04 06:11:11', '2026-03-04 06:11:11', 'SCSJ2200858'),
(118, 'Meeting - Mar 5, 01:03 AM', '', 1, '2026-03-05', '01:03:29', '02:03:29', NULL, 'ongoing', 'd9fq7iq9dhZc', NULL, 0, '2026-03-04 17:03:29', '2026-03-04 17:03:29', 'SCSJ2200858'),
(124, 'Meeting - Mar 5, 02:36 PM', '', 1, '2026-03-05', '14:37:03', '15:37:03', NULL, 'ongoing', 'ETjsNDYW118w', '$2y$10$GwAlPCmdSTtNTnircc/UIuSUjycNxmXcZm05/8dfoBqc8m.eqJI4u', 1, '2026-03-05 06:37:03', '2026-03-05 06:37:03', 'SCSJ2200858'),
(125, 'Meeting - Mar 5, 06:20 PM', '', 1, '2026-03-05', '18:20:57', '19:20:57', NULL, 'ongoing', '8p9ugTl2WlXa', NULL, 0, '2026-03-05 10:20:57', '2026-03-05 10:20:57', 'SCSJ2200858'),
(126, '123', '123', 1, '2026-03-05', '19:21:00', '20:21:00', NULL, 'scheduled', 'ECFL8fdfSQlm', '$2y$10$K2qIXaBQqqkUzZY3mzs6I.bLYOWPUGeP6CcnwHZPTm.l4waCRCIUW', 1, '2026-03-05 10:21:21', '2026-03-05 10:21:21', 'SCSJ2200858'),
(143, 'Meeting - Mar 7, 08:16 PM', '', 7, '2026-03-07', '20:16:57', '21:16:57', NULL, 'ongoing', 'yDIDj1XGluFP', NULL, 0, '2026-03-07 12:16:57', '2026-03-07 12:16:57', 'umie12'),
(144, '31321', '13213', 7, '2026-03-07', '21:52:00', '22:52:00', NULL, 'scheduled', 'e1hvQZ5Ywna5', NULL, 0, '2026-03-07 12:52:11', '2026-03-07 12:52:11', 'umie12'),
(145, '1333312', '11213132', 7, '2026-03-07', '21:52:00', '22:52:00', NULL, 'scheduled', '82W7jWYdLvBc', '$2y$10$SxraSwZZQ2tJQVCge8DzN.pWQjnzYtNpz5pNefE6al5swC6YN1OCC', 1, '2026-03-07 12:52:25', '2026-03-07 12:52:25', 'umie12'),
(146, 'Meeting - Mar 7, 09:21 PM', 'asd', 1, '2026-03-07', '21:21:10', '22:21:10', NULL, 'ongoing', '0pPNhlFBomJE', '$2y$10$eiOSyl8rjRTpmxxzQJSVXO8fEVQ1hC8JWGLmUlcZ0oFyNVtPkEHr.', 1, '2026-03-07 13:21:10', '2026-03-07 13:21:10', 'SCSJ2200858'),
(148, 'Meeting - Mar 7, 10:41 PM', 'asd', 7, '2026-03-07', '22:41:39', '23:41:39', NULL, 'ongoing', '4onMYeXUNKbL', '$2y$10$GqTIJqmQXEInugCViZZ3DOlAwiOg2Sa0IOBY6GUU6LTCbnGZmJ8pq', 1, '2026-03-07 14:41:39', '2026-03-07 14:41:39', 'umie12'),
(149, '13123', 'asda', 7, '2026-03-09', '20:03:00', '21:03:00', NULL, 'scheduled', 'lJIC9wjnCLJl', '$2y$10$KwBUEeDylDVmHL2sInqTy.XLke2BNL/85Fx9UZYaaSZ2WGxqFnkmO', 1, '2026-03-08 11:03:15', '2026-03-08 11:03:15', 'umie12'),
(153, 'Meeting - Mar 9, 07:33 PM', '', 1, '2026-03-09', '19:33:54', '20:33:54', NULL, 'ongoing', 'kbPspEkvAd4t', NULL, 0, '2026-03-09 11:33:54', '2026-03-09 11:33:54', 'SCSJ2200858'),
(154, 'Meeting - Mar 9, 08:01 PM', '', 5, '2026-03-09', '20:01:18', '21:01:18', NULL, 'ongoing', 'zpJORlHp8D4w', NULL, 0, '2026-03-09 12:01:18', '2026-03-09 12:01:18', 'SCSJ2200057'),
(156, 'Meeting - Mar 9, 09:04 PM', 'adsad', 1, '2026-03-09', '21:04:45', '22:04:45', NULL, 'ongoing', 'haCTAPJq3aG1', '$2y$10$rCtNZcbNCfvZa8gmRQ3rg.2kSF0rLSF0TS0XtTTQuXMRiQUXaLobq', 1, '2026-03-09 13:04:45', '2026-03-09 13:04:45', 'SCSJ2200858'),
(157, 'Meeting - Mar 9, 09:34 PM', '', 1, '2026-03-09', '21:34:40', '22:34:40', NULL, 'ongoing', '3YDO6vRrZiTG', NULL, 0, '2026-03-09 13:34:40', '2026-03-09 13:34:40', 'SCSJ2200858'),
(158, 'Meeting - Mar 9, 09:40 PM', '', 1, '2026-03-09', '21:40:55', '22:40:55', NULL, 'ongoing', 'eJmuKoxqcGd8', '$2y$10$Yx3FiL4GumySGbjgSGX4GOzuqWhJZT4OvXeZONDkKrnBx5A4xob2G', 1, '2026-03-09 13:40:55', '2026-03-09 13:40:55', 'SCSJ2200858'),
(161, 'Meeting - Mar 10, 10:35 AM', '', 7, '2026-03-10', '10:35:54', '11:35:54', NULL, 'ongoing', 'r1bIk0r71yLC', NULL, 0, '2026-03-10 02:35:54', '2026-03-10 02:35:54', 'umie12'),
(162, '1231', '1133', 7, '2026-03-11', '12:31:00', '13:31:00', NULL, 'scheduled', '2iPfXAsvwHLf', NULL, 0, '2026-03-10 03:31:06', '2026-03-10 03:31:06', 'umie12');

-- --------------------------------------------------------

--
-- Table structure for table `meeting_participants`
--

CREATE TABLE `meeting_participants` (
  `id` int(11) NOT NULL,
  `meeting_id` int(11) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `status` enum('pending','accepted','declined','online','offline','kicked') DEFAULT 'pending',
  `joined_at` timestamp NULL DEFAULT NULL,
  `left_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meeting_participants`
--

INSERT INTO `meeting_participants` (`id`, `meeting_id`, `participant_id`, `username`, `status`, `joined_at`, `left_at`) VALUES
(2527, 4, 5, 'SCSJ2200057', '', NULL, NULL),
(2529, 4, 1, 'SCSJ2200858', 'offline', '2026-02-27 03:29:10', '2026-03-07 14:42:02'),
(2530, 4, 5, 'SCSJ2200057', '', NULL, NULL),
(2579, 2, 5, 'SCSJ2200057', 'online', NULL, NULL),
(2580, 2, 1, 'SCSJ2200858', 'offline', '2026-02-27 03:29:10', '2026-03-03 19:48:30'),
(2581, 2, 5, 'SCSJ2200057', 'online', NULL, NULL),
(2592, 699, 5, 'SCSJ2200057', '', NULL, NULL),
(2593, 699, 5, 'SCSJ2200057', '', NULL, NULL),
(2599, 4, 5, 'SCSJ2200057', '', NULL, NULL),
(2627, 0, 5, 'SCSJ2200057', 'offline', NULL, '2026-03-05 06:44:16'),
(2630, 0, 1, 'SCSJ2200858', 'offline', NULL, '2026-03-09 03:27:04'),
(2631, 0, 5, 'SCSJ2200057', 'offline', NULL, '2026-03-05 06:44:16'),
(2632, 0, 1, 'SCSJ2200858', 'offline', NULL, '2026-03-09 03:27:04'),
(2633, 0, 5, 'SCSJ2200057', 'offline', NULL, '2026-03-05 06:44:16'),
(2634, 0, 1, 'SCSJ2200858', 'offline', NULL, '2026-03-09 03:27:04'),
(2635, 0, 1, 'SCSJ2200858', 'offline', NULL, '2026-03-09 03:27:04'),
(2636, 0, 1, 'SCSJ2200858', 'offline', NULL, '2026-03-09 03:27:04'),
(2637, 0, 5, 'SCSJ2200057', 'offline', NULL, '2026-03-05 06:44:16'),
(2638, 0, 1, 'SCSJ2200858', 'offline', NULL, '2026-03-09 03:27:04'),
(2639, 0, 5, 'SCSJ2200057', 'offline', NULL, '2026-03-05 06:44:16'),
(2640, 0, 1, 'SCSJ2200858', 'offline', NULL, '2026-03-09 03:27:04'),
(2641, 0, 1, 'SCSJ2200858', 'offline', NULL, '2026-03-09 03:27:04'),
(2642, 0, 5, 'SCSJ2200057', 'offline', NULL, '2026-03-05 06:44:16'),
(2643, 0, 1, 'SCSJ2200858', 'offline', NULL, '2026-03-09 03:27:04'),
(2644, 0, 1, 'SCSJ2200858', 'offline', NULL, '2026-03-09 03:27:04'),
(2645, 0, 1, 'SCSJ2200858', 'offline', NULL, '2026-03-09 03:27:04'),
(2646, 0, 1, 'SCSJ2200858', 'offline', NULL, '2026-03-09 03:27:04'),
(2647, 4, 1, 'SCSJ2200858', 'offline', NULL, '2026-03-07 14:42:02'),
(2648, 0, 5, 'SCSJ2200057', 'offline', NULL, '2026-03-05 06:44:16'),
(2649, 0, 5, 'SCSJ2200057', 'offline', NULL, '2026-03-05 06:44:16'),
(2650, 0, 5, 'SCSJ2200057', 'offline', NULL, '2026-03-05 06:44:16'),
(2651, 0, 1, 'SCSJ2200858', 'offline', NULL, '2026-03-09 03:27:04'),
(2652, 0, 5, 'SCSJ2200057', 'offline', NULL, '2026-03-05 06:44:16'),
(2653, 0, 5, 'SCSJ2200057', 'offline', NULL, '2026-03-05 06:44:16'),
(2654, 0, 1, 'SCSJ2200858', 'offline', NULL, '2026-03-09 03:27:04'),
(2655, 0, 1, 'SCSJ2200858', 'offline', NULL, '2026-03-09 03:27:04'),
(2656, 0, 1, 'SCSJ2200858', 'offline', NULL, '2026-03-09 03:27:04'),
(2657, 0, 5, 'SCSJ2200057', 'offline', NULL, '2026-03-05 06:44:16'),
(2658, 0, 1, 'SCSJ2200858', 'offline', NULL, '2026-03-09 03:27:04'),
(2659, 0, 5, 'SCSJ2200057', 'offline', NULL, '2026-03-05 06:44:16'),
(2660, 0, 1, 'SCSJ2200858', 'offline', NULL, '2026-03-09 03:27:04'),
(2661, 0, 5, 'SCSJ2200057', 'offline', NULL, '2026-03-05 06:44:16'),
(2662, 0, 1, 'SCSJ2200858', 'offline', NULL, '2026-03-09 03:27:04'),
(2663, 0, 5, 'SCSJ2200057', 'offline', NULL, '2026-03-05 06:44:16'),
(2664, 0, 1, 'SCSJ2200858', 'offline', NULL, '2026-03-09 03:27:04'),
(2665, 0, 1, 'SCSJ2200858', 'offline', NULL, '2026-03-09 03:27:04'),
(2666, 0, 1, 'SCSJ2200858', 'offline', NULL, '2026-03-09 03:27:04'),
(2667, 0, 1, 'SCSJ2200858', 'offline', NULL, '2026-03-09 03:27:04'),
(2668, 0, 4, 'Admin', 'online', NULL, NULL),
(2670, 0, 13, NULL, 'offline', NULL, '2026-03-09 14:25:36');

-- --------------------------------------------------------

--
-- Table structure for table `meeting_participant_settings`
--

CREATE TABLE `meeting_participant_settings` (
  `id` int(11) NOT NULL,
  `room_id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `device_type` varchar(20) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meeting_participant_settings`
--

INSERT INTO `meeting_participant_settings` (`id`, `room_id`, `user_id`, `device_type`, `status`, `updated_at`) VALUES
(1, 'CuPLzgU15q3V', 1, 'mic', 0, '2026-01-22 13:04:11'),
(2, 'CuPLzgU15q3V', 1, 'camera', 0, '2026-01-22 13:04:11'),
(14, 'kbeMVfuezJa9', 5, 'camera', 0, '2026-01-22 12:28:26'),
(15, 'kbeMVfuezJa9', 5, 'mic', 0, '2026-01-22 12:28:26'),
(34, 'mD03islnklbH', 1, 'mic', 0, '2026-01-23 16:31:12'),
(35, 'mD03islnklbH', 1, 'camera', 0, '2026-01-23 16:31:12'),
(248, 'UajR8yKyPHfj', 1, 'camera', 0, '2026-01-23 16:37:26'),
(249, 'UajR8yKyPHfj', 1, 'mic', 0, '2026-01-23 16:37:27'),
(250, '36', 1, 'mic', 0, '2026-01-24 13:36:21'),
(251, '36', 1, 'camera', 0, '2026-01-24 13:36:21'),
(252, 'QilBSl1kggzt', 1, 'camera', 0, '2026-01-24 13:52:07'),
(256, 'QilBSl1kggzt', 1, 'mic', 0, '2026-01-24 13:52:07'),
(287, 'QilBSl1kggzt', 5, 'mic', 1, '2026-01-24 13:50:32'),
(288, 'QilBSl1kggzt', 5, 'camera', 1, '2026-01-24 13:50:32'),
(319, 'QEgP42fCnJui', 5, 'mic', 0, '2026-01-24 14:55:58'),
(320, 'QEgP42fCnJui', 5, 'camera', 0, '2026-01-24 14:55:58'),
(328, 'QEgP42fCnJui', 1, 'mic', 0, '2026-01-24 14:56:22'),
(329, 'QEgP42fCnJui', 1, 'camera', 0, '2026-01-24 14:56:22'),
(347, 'F1OPEybAZY3k', 1, 'mic', 1, '2026-01-24 14:28:39'),
(348, 'F1OPEybAZY3k', 1, 'camera', 1, '2026-01-24 14:28:39'),
(349, 'RLRUVZl8le2e', 1, 'mic', 1, '2026-01-24 14:47:44'),
(350, 'RLRUVZl8le2e', 1, 'camera', 1, '2026-01-24 14:47:44'),
(373, 'RLRUVZl8le2e', 5, 'mic', 0, '2026-01-24 14:55:35'),
(374, 'RLRUVZl8le2e', 5, 'camera', 0, '2026-01-24 14:55:35'),
(545, 'cwmJAZpVMa1c', 1, 'camera', 0, '2026-01-26 07:04:14'),
(546, 'EeqSm83FqmV1', 1, 'mic', 1, '2026-01-28 02:33:21'),
(548, 'EeqSm83FqmV1', 1, 'camera', 1, '2026-01-28 02:33:21'),
(549, 'Wxvnn2aGZUUb', 1, 'mic', 1, '2026-01-31 05:09:06'),
(553, 'Wxvnn2aGZUUb', 1, 'camera', 1, '2026-01-31 05:09:06'),
(580, '1riJoxy7F71w', 1, 'mic', 1, '2026-01-31 05:09:32'),
(581, '1riJoxy7F71w', 1, 'camera', 0, '2026-01-31 05:09:32'),
(600, 'hs9yQbEZLBpE', 1, 'camera', 0, '2026-01-31 07:08:24'),
(601, 'hs9yQbEZLBpE', 1, 'mic', 0, '2026-01-31 07:08:24'),
(602, 'v7TqhBtWSYKM', 1, 'mic', 1, '2026-02-12 08:56:14'),
(603, 'v7TqhBtWSYKM', 1, 'camera', 1, '2026-02-12 08:56:14'),
(612, 'v7TqhBtWSYKM', 5, 'mic', 1, '2026-02-12 09:19:17'),
(613, 'v7TqhBtWSYKM', 5, 'camera', 0, '2026-02-12 09:19:17'),
(666, 'WmAATo35mDw7', 1, 'camera', 1, '2026-02-12 10:45:02'),
(667, 'WmAATo35mDw7', 1, 'mic', 1, '2026-02-12 10:45:02'),
(668, 'WmAATo35mDw7', 5, 'mic', 1, '2026-02-12 10:32:23'),
(669, 'WmAATo35mDw7', 5, 'camera', 1, '2026-02-12 10:32:23'),
(808, 'jApTxS4Oe9AZ', 1, 'camera', 0, '2026-02-13 10:06:08'),
(809, 'jApTxS4Oe9AZ', 1, 'mic', 0, '2026-02-13 10:06:08'),
(819, 'jApTxS4Oe9AZ', 5, 'mic', 0, '2026-02-13 10:04:07'),
(820, 'jApTxS4Oe9AZ', 5, 'camera', 0, '2026-02-13 10:04:07'),
(1235, 'hXe2q3PSnqAs', 5, 'mic', 1, '2026-02-13 10:56:38'),
(1236, 'hXe2q3PSnqAs', 5, 'camera', 1, '2026-02-13 10:56:38'),
(1241, '4QCK4IVyKF3D', 5, 'mic', 1, '2026-02-13 10:58:44'),
(1242, '4QCK4IVyKF3D', 5, 'camera', 1, '2026-02-13 10:58:44'),
(1269, '4QCK4IVyKF3D', 1, 'mic', 0, '2026-02-13 10:58:26'),
(1280, '7ZAbUId4aBJd', 1, 'camera', 0, '2026-02-14 06:58:49'),
(1281, '7ZAbUId4aBJd', 1, 'mic', 0, '2026-02-14 06:58:50'),
(1282, 'DJYa6btnUC2t', 1, 'mic', 1, '2026-02-14 08:45:17'),
(1283, 'DJYa6btnUC2t', 1, 'camera', 1, '2026-02-14 08:45:17'),
(1319, 'DJYa6btnUC2t', 5, 'mic', 1, '2026-02-14 08:46:40'),
(1320, 'DJYa6btnUC2t', 5, 'camera', 1, '2026-02-14 08:46:40'),
(1323, 'mzjzqqcG6cqu', 1, 'mic', 1, '2026-02-14 08:47:40'),
(1324, 'mzjzqqcG6cqu', 1, 'camera', 1, '2026-02-14 08:47:40'),
(1331, 'mzjzqqcG6cqu', 5, 'mic', 1, '2026-02-14 08:47:50'),
(1332, 'mzjzqqcG6cqu', 5, 'camera', 1, '2026-02-14 08:47:50'),
(1376, 'MAQSebXJ6eBC', 5, 'mic', 1, '2026-02-14 16:32:27'),
(1381, 'MAQSebXJ6eBC', 4, 'mic', 1, '2026-02-14 16:35:19'),
(1385, 'MAQSebXJ6eBC', 5, 'camera', 1, '2026-02-14 16:32:27'),
(1388, 'MAQSebXJ6eBC', 4, 'camera', 1, '2026-02-14 16:35:19'),
(1437, 'MAQSebXJ6eBC', 1, 'camera', 1, '2026-02-14 16:30:45'),
(1441, 'MAQSebXJ6eBC', 1, 'mic', 1, '2026-02-14 16:30:45'),
(1566, 'bpHx1GtmmOYJ', 1, 'camera', 0, '2026-02-14 16:50:27'),
(1567, 'bpHx1GtmmOYJ', 1, 'mic', 0, '2026-02-14 16:49:22'),
(1568, 'bpHx1GtmmOYJ', 4, 'camera', 0, '2026-02-14 16:50:00'),
(1574, 'bpHx1GtmmOYJ', 4, 'mic', 0, '2026-02-14 16:51:12'),
(1583, 'at55L2pMb7eF', 1, 'mic', 1, '2026-02-25 05:41:30'),
(1584, 'at55L2pMb7eF', 5, 'mic', 1, '2026-02-25 05:41:36'),
(1585, 'at55L2pMb7eF', 5, 'camera', 1, '2026-02-25 05:41:36'),
(1589, 'at55L2pMb7eF', 1, 'camera', 1, '2026-02-25 05:41:30'),
(1609, 'vxZEsHjvisEF', 5, 'camera', 1, '2026-02-25 05:56:27'),
(1610, 'vxZEsHjvisEF', 5, 'mic', 1, '2026-02-25 05:56:27'),
(1611, 'vxZEsHjvisEF', 1, 'mic', 1, '2026-02-25 05:59:03'),
(1612, 'vxZEsHjvisEF', 1, 'camera', 1, '2026-02-25 05:59:04'),
(1738, 'ePqTH6HkLFc0', 1, 'mic', 1, '2026-02-25 06:01:41'),
(1739, 'ePqTH6HkLFc0', 1, 'camera', 1, '2026-02-25 06:01:41'),
(1754, 'uBCL2afemBlx', 5, 'mic', 1, '2026-02-25 06:40:20'),
(1755, 'uBCL2afemBlx', 5, 'camera', 1, '2026-02-25 06:40:20'),
(1762, 'uBCL2afemBlx', 1, 'mic', 1, '2026-02-25 06:57:56'),
(1763, 'uBCL2afemBlx', 1, 'camera', 1, '2026-02-25 06:57:56'),
(1864, '2cL4paomSEdJ', 1, 'camera', 0, '2026-02-26 05:21:49'),
(1865, '2cL4paomSEdJ', 1, 'mic', 0, '2026-02-26 05:17:44'),
(1869, '2cL4paomSEdJ', 5, 'camera', 0, '2026-02-26 05:20:56'),
(1874, 'Tgez3blyGbsp', 1, 'camera', 1, '2026-02-26 08:27:10'),
(1877, 'Tgez3blyGbsp', 1, 'mic', 1, '2026-02-26 08:27:10'),
(1916, 'RL7Gk9tqO1hZ', 1, 'mic', 1, '2026-02-26 06:32:05'),
(1917, 'RL7Gk9tqO1hZ', 1, 'camera', 1, '2026-02-26 06:32:05'),
(1922, 'meet-699FEEF35047F-7288', 5, 'mic', 1, '2026-02-26 07:00:33'),
(1923, 'meet-699FEEF35047F-7288', 5, 'camera', 1, '2026-02-26 07:00:33'),
(1942, '699FEFB881E9A-4974', 5, 'mic', 1, '2026-02-26 07:01:33'),
(1943, '699FEFB881E9A-4974', 5, 'camera', 1, '2026-02-26 07:01:33'),
(1950, 'RxSApM4TL2Qy', 5, 'mic', 1, '2026-02-26 07:18:34'),
(1951, 'RxSApM4TL2Qy', 5, 'camera', 1, '2026-02-26 07:18:34'),
(1958, 'cq0UfAZOR196', 1, 'mic', 1, '2026-02-26 07:56:31'),
(1959, 'cq0UfAZOR196', 1, 'camera', 1, '2026-02-26 07:56:31'),
(1975, '4pBXyTPKiVZq', 5, 'mic', 1, '2026-02-26 07:53:43'),
(1976, '4pBXyTPKiVZq', 5, 'camera', 1, '2026-02-26 07:53:43'),
(1991, 'oizDBrMOMGeH', 5, 'mic', 1, '2026-02-26 08:12:03'),
(1992, 'oizDBrMOMGeH', 5, 'camera', 1, '2026-02-26 08:12:03'),
(1999, 'eRTpMdPhgJ3G', 1, 'mic', 1, '2026-02-26 08:33:51'),
(2000, 'eRTpMdPhgJ3G', 1, 'camera', 1, '2026-02-26 08:33:51'),
(2005, 'i35wHZgGsSIB', 1, 'mic', 1, '2026-02-26 08:10:35'),
(2006, 'i35wHZgGsSIB', 1, 'camera', 1, '2026-02-26 08:10:35'),
(2009, 'i35wHZgGsSIB', 5, 'mic', 1, '2026-02-26 08:10:32'),
(2010, 'i35wHZgGsSIB', 5, 'camera', 1, '2026-02-26 08:10:32'),
(2051, 'HAvCdElmGc99', 1, 'mic', 1, '2026-02-26 09:25:51'),
(2052, 'HAvCdElmGc99', 1, 'camera', 1, '2026-02-26 09:25:51'),
(2061, 'HAvCdElmGc99', 5, 'camera', 1, '2026-02-26 08:58:57'),
(2062, 'HAvCdElmGc99', 5, 'mic', 1, '2026-02-26 08:58:58'),
(2089, 'O8DCu3ClQz7a', 1, 'mic', 1, '2026-02-26 09:15:40'),
(2090, 'O8DCu3ClQz7a', 1, 'camera', 1, '2026-02-26 09:15:40'),
(2097, 'O8DCu3ClQz7a', 5, 'mic', 1, '2026-02-26 09:45:41'),
(2098, 'O8DCu3ClQz7a', 5, 'camera', 1, '2026-02-26 09:45:41'),
(2105, 'YgUBnfQVQxwT', 5, 'mic', 1, '2026-02-26 09:25:05'),
(2106, 'YgUBnfQVQxwT', 5, 'camera', 1, '2026-02-26 09:25:05'),
(2133, 'cv1xXtQDAgaR', 1, 'mic', 1, '2026-02-27 03:25:46'),
(2134, 'cv1xXtQDAgaR', 1, 'camera', 1, '2026-02-27 03:25:46'),
(2141, 'cv1xXtQDAgaR', 5, 'mic', 1, '2026-02-27 04:14:50'),
(2142, 'cv1xXtQDAgaR', 5, 'camera', 1, '2026-02-27 04:14:50'),
(2157, 'yB4G9oZeXath', 5, 'mic', 1, '2026-02-27 05:07:15'),
(2158, 'yB4G9oZeXath', 5, 'camera', 1, '2026-02-27 05:07:15'),
(2161, 'yB4G9oZeXath', 1, 'mic', 1, '2026-02-27 05:05:31'),
(2162, 'yB4G9oZeXath', 1, 'camera', 1, '2026-02-27 05:05:31'),
(2173, 'IsP5ccYlAdk2', 1, 'mic', 1, '2026-02-27 05:26:53'),
(2174, 'IsP5ccYlAdk2', 1, 'camera', 1, '2026-02-27 05:26:53'),
(2189, 'DLWtnGyfqlSL', 5, 'camera', 1, '2026-02-27 11:42:49'),
(2190, 'DLWtnGyfqlSL', 5, 'mic', 1, '2026-02-27 11:42:49'),
(2193, 'DLWtnGyfqlSL', 1, 'camera', 0, '2026-02-27 11:41:27'),
(2194, 'DLWtnGyfqlSL', 1, 'mic', 1, '2026-02-27 11:40:32'),
(2226, 'prRQL1XeSblh', 1, 'mic', 1, '2026-02-28 08:12:30'),
(2227, 'prRQL1XeSblh', 1, 'camera', 1, '2026-02-28 08:12:30'),
(2247, 'prRQL1XeSblh', 5, 'mic', 1, '2026-02-27 13:54:09'),
(2249, 'prRQL1XeSblh', 5, 'camera', 1, '2026-02-27 13:54:03'),
(2291, 'aZQNKMDVH9V8', 1, 'mic', 1, '2026-02-27 13:18:42'),
(2292, 'aZQNKMDVH9V8', 1, 'camera', 1, '2026-02-27 13:18:42'),
(2421, 'a07UErQrv84K', 1, 'mic', 1, '2026-02-28 09:04:59'),
(2422, 'a07UErQrv84K', 1, 'camera', 1, '2026-02-28 09:04:59'),
(2503, 'ArRCYiRUj7Gk', 1, 'camera', 1, '2026-02-28 10:09:03'),
(2504, 'ArRCYiRUj7Gk', 1, 'mic', 1, '2026-02-28 10:09:03'),
(2509, 'Ooj3XIoK8Xlu', 1, 'mic', 1, '2026-02-28 10:58:21'),
(2510, 'Ooj3XIoK8Xlu', 1, 'camera', 1, '2026-02-28 10:58:21'),
(2555, 'NdKb36wqySxJ', 1, 'mic', 1, '2026-02-28 10:58:40'),
(2556, 'NdKb36wqySxJ', 1, 'camera', 1, '2026-02-28 10:58:40'),
(2615, 'vp4M6N6N2m49', 5, 'mic', 1, '2026-02-28 11:00:35'),
(2616, 'vp4M6N6N2m49', 5, 'camera', 1, '2026-02-28 11:00:35'),
(2623, 'LtXbMIX9edto', 5, 'mic', 1, '2026-02-28 10:59:48'),
(2624, 'LtXbMIX9edto', 5, 'camera', 1, '2026-02-28 10:59:48'),
(2657, 'vpr4bcYigkIv', 5, 'mic', 1, '2026-02-28 11:01:26'),
(2658, 'vpr4bcYigkIv', 5, 'camera', 1, '2026-02-28 11:01:26'),
(2665, 'zpawomxt3EPf', 1, 'mic', 1, '2026-02-28 13:22:07'),
(2666, 'zpawomxt3EPf', 1, 'camera', 1, '2026-02-28 13:22:07'),
(2673, 's5slazxFBPh2', 1, 'mic', 0, '2026-02-28 18:51:25'),
(2684, 's5slazxFBPh2', 5, 'mic', 1, '2026-02-28 19:00:04'),
(2685, 's5slazxFBPh2', 5, 'camera', 1, '2026-02-28 19:00:04'),
(2704, 'Z78snbxCnPoK', 1, 'mic', 1, '2026-03-03 18:35:21'),
(2705, 'Z78snbxCnPoK', 1, 'camera', 1, '2026-03-03 18:35:21'),
(2708, 'Z78snbxCnPoK', 5, 'mic', 1, '2026-03-03 18:19:56'),
(2710, 'Z78snbxCnPoK', 5, 'camera', 1, '2026-03-03 18:20:23'),
(2846, 'Q8ofsUnaXoPP', 1, 'mic', 1, '2026-03-03 20:13:07'),
(2847, 'Q8ofsUnaXoPP', 1, 'camera', 1, '2026-03-03 20:13:07'),
(2850, 'Q8ofsUnaXoPP', 4, 'mic', 0, '2026-03-03 19:35:31'),
(2851, 'Q8ofsUnaXoPP', 4, 'camera', 1, '2026-03-03 19:35:07'),
(2868, '2uB7BK8FRVlt', 1, 'mic', 0, '2026-03-03 19:48:20'),
(2869, '2uB7BK8FRVlt', 5, 'camera', 0, '2026-03-03 19:54:05'),
(2882, 'RLfpIr3lS3MB', 7, 'mic', 1, '2026-03-05 05:00:51'),
(2883, 'RLfpIr3lS3MB', 7, 'camera', 1, '2026-03-05 05:00:51'),
(2908, 'RLfpIr3lS3MB', 1, 'mic', 0, '2026-03-04 07:28:10'),
(2909, 'RLfpIr3lS3MB', 1, 'camera', 1, '2026-03-04 07:27:20'),
(2941, 'd9fq7iq9dhZc', 1, 'mic', 1, '2026-03-04 17:08:17'),
(2942, 'd9fq7iq9dhZc', 1, 'camera', 1, '2026-03-04 17:08:17'),
(2949, 'sXKrsXowBwa5', 4, 'mic', 0, '2026-03-05 04:36:28'),
(2958, 'sXKrsXowBwa5', 7, 'mic', 1, '2026-03-05 05:01:06'),
(2959, 'sXKrsXowBwa5', 7, 'camera', 1, '2026-03-05 05:01:06'),
(2966, 'ETjsNDYW118w', 5, 'mic', 0, '2026-03-05 06:42:53'),
(2967, 'ETjsNDYW118w', 1, 'mic', 0, '2026-03-05 06:41:32'),
(2970, 'RIAuGiY0Osxm', 7, 'mic', 1, '2026-03-07 10:39:32'),
(2971, 'RIAuGiY0Osxm', 7, 'camera', 1, '2026-03-07 10:39:32'),
(2986, 'AJlWAUSNtqhk', 7, 'mic', 1, '2026-03-07 08:03:24'),
(2987, 'AJlWAUSNtqhk', 7, 'camera', 1, '2026-03-07 08:03:24'),
(3004, 'FcI5zvdkjqy6', 7, 'mic', 1, '2026-03-07 11:05:25'),
(3005, 'FcI5zvdkjqy6', 7, 'camera', 1, '2026-03-07 11:05:25'),
(3010, 'FcI5zvdkjqy6', 1, 'mic', 1, '2026-03-07 11:03:38'),
(3011, 'FcI5zvdkjqy6', 1, 'camera', 1, '2026-03-07 11:03:38'),
(3024, 'Zlek0kVJd3vS', 7, 'mic', 1, '2026-03-07 11:08:29'),
(3025, 'Zlek0kVJd3vS', 7, 'camera', 1, '2026-03-07 11:08:29'),
(3032, 'afpfIxu8aZZg', 7, 'mic', 1, '2026-03-07 11:43:56'),
(3033, 'afpfIxu8aZZg', 7, 'camera', 1, '2026-03-07 11:43:56'),
(3036, 'afpfIxu8aZZg', 1, 'mic', 1, '2026-03-07 11:38:37'),
(3038, 'afpfIxu8aZZg', 1, 'camera', 1, '2026-03-07 11:37:51'),
(3064, 'YmXZezbRZc6s', 1, 'mic', 1, '2026-03-07 13:20:52'),
(3065, 'YmXZezbRZc6s', 1, 'camera', 1, '2026-03-07 13:20:52'),
(3078, 'lI2y1gLmyi3O', 1, 'mic', 1, '2026-03-07 13:07:19'),
(3079, 'lI2y1gLmyi3O', 1, 'camera', 1, '2026-03-07 13:07:19'),
(3107, 'lI2y1gLmyi3O', 7, 'mic', 1, '2026-03-07 12:14:09'),
(3131, 'gCpSK3gEmVEn', 7, 'mic', 1, '2026-03-07 12:47:06'),
(3132, 'gCpSK3gEmVEn', 7, 'camera', 1, '2026-03-07 12:47:06'),
(3139, 'jb3Ulapkggum', 7, 'mic', 1, '2026-03-07 12:47:24'),
(3140, 'jb3Ulapkggum', 7, 'camera', 1, '2026-03-07 12:47:24'),
(3143, 'jb3Ulapkggum', 1, 'mic', 1, '2026-03-07 12:47:29'),
(3144, 'jb3Ulapkggum', 1, 'camera', 1, '2026-03-07 12:47:29'),
(3155, 'yDIDj1XGluFP', 7, 'mic', 1, '2026-03-07 12:51:52'),
(3156, 'yDIDj1XGluFP', 7, 'camera', 1, '2026-03-07 12:51:52'),
(3197, '82W7jWYdLvBc', 7, 'mic', 1, '2026-03-07 13:30:09'),
(3198, '82W7jWYdLvBc', 7, 'camera', 1, '2026-03-07 13:30:09'),
(3203, '82W7jWYdLvBc', 1, 'mic', 1, '2026-03-07 13:29:40'),
(3216, '82W7jWYdLvBc', 1, 'camera', 1, '2026-03-07 13:29:52'),
(3245, 'W5U9ysIurgJx', 1, 'mic', 1, '2026-03-07 13:41:29'),
(3246, 'W5U9ysIurgJx', 1, 'camera', 1, '2026-03-07 13:41:29'),
(3253, '0pPNhlFBomJE', 1, 'mic', 1, '2026-03-07 13:41:35'),
(3254, '0pPNhlFBomJE', 1, 'camera', 1, '2026-03-07 13:41:35'),
(3257, 'W5U9ysIurgJx', 7, 'mic', 1, '2026-03-07 14:41:23'),
(3258, 'W5U9ysIurgJx', 7, 'camera', 1, '2026-03-07 14:41:23'),
(3271, '4onMYeXUNKbL', 7, 'camera', 1, '2026-03-07 14:42:50'),
(3272, '4onMYeXUNKbL', 7, 'mic', 1, '2026-03-07 14:42:50'),
(3275, 'lJIC9wjnCLJl', 7, 'mic', 1, '2026-03-08 11:26:35'),
(3276, 'lJIC9wjnCLJl', 7, 'camera', 1, '2026-03-08 11:26:35'),
(3283, 'EQQrlOWFC7Vz', 13, 'mic', 1, '2026-03-09 03:15:55'),
(3284, 'EQQrlOWFC7Vz', 13, 'camera', 1, '2026-03-09 03:15:55'),
(3337, 'PfdtaBBB8F59', 13, 'mic', 1, '2026-03-09 03:27:15'),
(3338, 'PfdtaBBB8F59', 13, 'camera', 1, '2026-03-09 03:27:15'),
(3351, 'PfdtaBBB8F59', 1, 'mic', 1, '2026-03-09 03:35:01'),
(3362, 'PfdtaBBB8F59', 1, 'camera', 1, '2026-03-09 03:35:01'),
(3369, 'fl0qlwLV95Gv', 1, 'mic', 0, '2026-03-09 03:58:50'),
(3370, 'fl0qlwLV95Gv', 1, 'camera', 1, '2026-03-09 03:58:50'),
(3395, 'fl0qlwLV95Gv', 14, 'mic', 0, '2026-03-09 04:00:41'),
(3398, 'haCTAPJq3aG1', 1, 'mic', 1, '2026-03-09 13:40:37'),
(3399, 'haCTAPJq3aG1', 1, 'camera', 1, '2026-03-09 13:40:37'),
(3421, '3YDO6vRrZiTG', 1, 'mic', 1, '2026-03-09 13:39:48'),
(3422, '3YDO6vRrZiTG', 1, 'camera', 1, '2026-03-09 13:39:48'),
(3455, 'eJmuKoxqcGd8', 1, 'mic', 1, '2026-03-09 14:26:02'),
(3456, 'eJmuKoxqcGd8', 1, 'camera', 1, '2026-03-09 14:26:02');

-- --------------------------------------------------------

--
-- Table structure for table `meeting_tasks`
--

CREATE TABLE `meeting_tasks` (
  `id` int(11) NOT NULL,
  `room_id` varchar(50) NOT NULL,
  `task_text` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meeting_tasks`
--

INSERT INTO `meeting_tasks` (`id`, `room_id`, `task_text`, `created_by`, `is_completed`, `created_at`) VALUES
(1, 'xY4CUPLJYYE0', 'test', 1, 0, '2026-01-22 06:17:30'),
(2, 'xY4CUPLJYYE0', 'Test2', 4, 0, '2026-01-22 06:18:51'),
(6, 'lqSwUuaWQ0UN', 'Test', 1, 0, '2026-01-22 08:51:05'),
(7, 'aNf4n6YlIwKS', 'Test1', 1, 0, '2026-01-22 10:32:21'),
(9, 'CuPLzgU15q3V', 'Test1', 1, 1, '2026-01-22 10:53:49'),
(11, 'kbeMVfuezJa9', 'test1', 5, 0, '2026-01-22 12:28:42'),
(22, '4QCK4IVyKF3D', 'asd', 5, 0, '2026-02-13 10:30:45'),
(23, '4QCK4IVyKF3D', 'asd', 5, 0, '2026-02-13 10:30:48'),
(24, 'MAQSebXJ6eBC', '123', 5, 0, '2026-02-14 16:32:10'),
(25, 'MAQSebXJ6eBC', '123', 5, 0, '2026-02-14 16:32:11'),
(26, 'MAQSebXJ6eBC', '123', 5, 0, '2026-02-14 16:32:12'),
(27, 'MAQSebXJ6eBC', '123', 5, 0, '2026-02-14 16:32:14'),
(28, 'MAQSebXJ6eBC', '123', 5, 0, '2026-02-14 16:32:15'),
(29, 'MAQSebXJ6eBC', '123', 5, 0, '2026-02-14 16:32:16'),
(31, 'Q8ofsUnaXoPP', 'asd', 1, 0, '2026-03-03 20:10:27'),
(32, 'eT85mPTo3EL6', '1234', 1, 0, '2026-03-04 02:19:11'),
(34, 'RLfpIr3lS3MB', 'yth', 7, 0, '2026-03-04 06:40:39'),
(39, 'EQQrlOWFC7Vz', 'tutorial 1', 13, 0, '2026-03-09 03:08:46'),
(40, 'EQQrlOWFC7Vz', 'Tutrorial 2', 13, 0, '2026-03-09 03:08:54'),
(41, 'EQQrlOWFC7Vz', 'Tutorial 3', 13, 0, '2026-03-09 03:09:01');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `message`, `is_read`, `created_at`, `is_deleted`) VALUES
(1, 1, 4, 'hi', 1, '2026-01-16 16:36:23', 0),
(3, 1, 4, 'hello', 0, '2026-01-22 05:52:38', 0),
(4, 5, 1, 'hi', 1, '2026-01-22 09:02:49', 0),
(5, 1, 5, 'testing', 1, '2026-01-22 11:58:03', 0),
(6, 5, 1, 'Testing', 1, '2026-01-22 12:25:55', 0),
(8, 1, 5, 'how are you', 1, '2026-01-22 12:29:44', 0),
(9, 1, 5, 'hi', 1, '2026-01-22 12:30:07', 0),
(10, 1, 5, 'hi', 1, '2026-02-12 08:54:17', 0),
(11, 5, 1, 'test', 1, '2026-02-28 04:35:14', 0),
(12, 1, 5, 'hi', 1, '2026-02-28 04:35:24', 0),
(13, 5, 1, 'hi', 1, '2026-02-28 08:55:42', 0),
(14, 5, 1, 'hi', 1, '2026-02-28 09:28:44', 0),
(15, 5, 1, 'test', 1, '2026-02-28 09:28:58', 0),
(16, 1, 5, '', 1, '2026-03-01 10:36:56', 0),
(17, 6, 1, 'test', 1, '2026-03-03 18:28:44', 0),
(18, 1, 5, '', 1, '2026-03-04 16:06:00', 0),
(19, 7, 1, 'test', 1, '2026-03-04 16:30:04', 0),
(20, 7, 1, '', 1, '2026-03-04 16:30:13', 0),
(21, 1, 7, '', 1, '2026-03-04 16:30:48', 0),
(22, 1, 7, '', 1, '2026-03-05 11:29:57', 0),
(23, 1, 7, '', 1, '2026-03-05 11:55:21', 0),
(24, 1, 7, 'test', 1, '2026-03-06 10:03:25', 0),
(25, 7, 1, 'asd', 1, '2026-03-07 07:30:51', 0),
(26, 7, 1, '', 1, '2026-03-07 07:31:04', 0),
(27, 7, 1, '', 1, '2026-03-07 08:30:39', 0),
(28, 1, 9, 'hi', 0, '2026-03-16 11:18:02', 0),
(29, 1, 9, '', 0, '2026-03-16 11:18:56', 0);

-- --------------------------------------------------------

--
-- Table structure for table `message_files`
--

CREATE TABLE `message_files` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `message_files`
--

INSERT INTO `message_files` (`id`, `message_id`, `file_name`, `file_path`, `file_size`, `file_type`, `uploaded_at`) VALUES
(1, 16, 'Agile methodology.png', 'uploads/chat_files/69a416c81e086_Agile methodology.png', 27771, 'image/png', '2026-03-01 10:36:56'),
(2, 18, 'Tutorial 1 jan21.docx', 'uploads/chat_files/69a85868f02a4_Tutorial 1 jan21.docx', 363873, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '2026-03-04 16:06:00'),
(3, 20, 'Tutorial 1 jan21.docx', 'uploads/chat_files/69a85e15d93f9_Tutorial 1 jan21.docx', 363873, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '2026-03-04 16:30:13'),
(4, 21, 'flash.jpg', 'uploads/chat_files/69a85e3864e78_flash.jpg', 8266, 'image/jpeg', '2026-03-04 16:30:48'),
(5, 22, 'PRG4034-TUTORIAL1.docx', 'uploads/chat_files/69a96935a5ebc_PRG4034-TUTORIAL1.docx', 20901, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '2026-03-05 11:29:57'),
(6, 23, 'Video_conferencing_as_a_face-to-face_online_meetin.pdf', 'uploads/chat_files/69a96f296e93d_Video_conferencing_as_a_face-to-face_online_meetin.pdf', 745256, 'application/pdf', '2026-03-05 11:55:21'),
(7, 26, 'dog.jpg', 'uploads/chat_files/69abd4387d7e7_dog.jpg', 5759, 'image/jpeg', '2026-03-07 07:31:04'),
(8, 27, 'cat.jpg', 'uploads/chat_files/69abe22f9ce8f_cat.jpg', 5186, 'image/jpeg', '2026-03-07 08:30:39'),
(9, 29, 'FYP Poster.png', 'uploads/chat_files/69b7e7203b8b1_FYP Poster.png', 1607978, 'image/png', '2026-03-16 11:18:56');

-- --------------------------------------------------------

--
-- Table structure for table `muted_users`
--

CREATE TABLE `muted_users` (
  `id` int(11) NOT NULL,
  `room_id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `muted_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `muted_users`
--

INSERT INTO `muted_users` (`id`, `room_id`, `user_id`, `muted_by`, `created_at`) VALUES
(6, 'RLfpIr3lS3MB', 1, 7, '2026-03-04 07:28:10'),
(13, 'fl0qlwLV95Gv', 1, 14, '2026-03-09 03:59:02');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `expires_at`, `created_at`) VALUES
(1, 'jiancheng123@hotmail.co.uk', 'd23b8a97954936a79914951b1e0aaacd3738da6a4223c36c31d5292b47219ab5', '2026-01-16 19:34:41', '2026-01-16 17:34:41'),
(2, 'SCSJ2200858@segi4u.my', 'c0b618689cfd3b2e6c4f48aafdaec1bf7ea96dffa250a77ed2fd8a0df4275ac8', '2026-02-13 12:45:28', '2026-02-13 10:45:28'),
(4, 'SCSJ2200057@segi4u.my', 'e8b43bdb5f4c8e25e24954a9f9f538b07e30dc4355cfd05a6dc3b6b9cf7a958e', '2026-02-13 12:56:50', '2026-02-13 10:56:50'),
(6, 'admin@hotmail.com', '9c735f3df5e0a5c72975604d0b3a345ef1f354309608d3a7e12aea03b8a23888', '2026-02-15 08:43:40', '2026-02-15 06:43:40'),
(7, 'umie@g.com', 'f8be88ba41d5d84e6eae3fede8ed7e909c2f2b1978ffd3dda2534530ecf49484', '2026-03-04 16:53:17', '2026-03-04 14:53:17');

-- --------------------------------------------------------

--
-- Table structure for table `peer_connections`
--

CREATE TABLE `peer_connections` (
  `id` int(11) NOT NULL,
  `room_id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `peer_id` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `problem_reports`
--

CREATE TABLE `problem_reports` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `contact_email` varchar(255) NOT NULL,
  `status` enum('pending','reviewed','resolved') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `admin_username` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `problem_reports`
--

INSERT INTO `problem_reports` (`id`, `user_id`, `category`, `description`, `contact_email`, `status`, `admin_notes`, `admin_id`, `admin_username`, `created_at`, `updated_at`) VALUES
(1, 1, 'bug', 'testing', 'SCSJ2200858@segi4u.my', 'reviewed', '\n\nAdmin Note (2026-02-14 09:24): done\n\nAdmin Note (2026-02-14 09:39): test', NULL, NULL, '2026-02-14 07:07:55', '2026-02-14 08:40:06'),
(2, 1, 'feature', 'asd', 'SCSJ2200858@segi4u.my', 'reviewed', '\n\nQuick Update (2026-03-01 02:47): Quick update from dashboard', NULL, NULL, '2026-02-14 07:09:18', '2026-02-28 18:47:34'),
(3, 5, 'security', 'test', 'SCSJ2200057@segi4u.my', 'pending', 'Admin Note (2026-03-05 20:19): asdads\\n\\nAdmin Note (2026-03-05 20:19): dasd', 4, 'Admin', '2026-02-14 08:25:31', '2026-03-05 12:19:10'),
(4, 1, 'security', 'Someone hack inside my meeting room', 'SCSJ2200858@segi4u.my', 'reviewed', 'Admin Note (2026-03-05 19:32): asdasdaadasda\n\nAdmin Note (2026-03-06 17:58): terst', NULL, NULL, '2026-02-15 07:00:32', '2026-03-06 09:58:03'),
(5, 7, 'ui', 'fffffff', 'umie@g.com', 'resolved', '\n\nAdmin Note (2026-03-04 15:03): tttt', NULL, NULL, '2026-03-04 06:55:49', '2026-03-04 07:03:32'),
(6, 13, 'bug', 'blablablablablabla', 'mmastura@gmail.com', 'pending', NULL, NULL, NULL, '2026-03-09 03:20:00', '2026-03-09 03:20:00'),
(7, 14, 'bug', 'zxcvbhshhshshhshbsjhasdsdgshjdgasgdshad1234563213626352321321321321321321321321323213129399384845775', 'aaaaaaa@gmail.com', 'reviewed', '\n\nQuick Update (2026-03-10 00:42): Quick update from dashboard - reviewed', NULL, NULL, '2026-03-09 04:05:23', '2026-03-09 16:42:45'),
(8, 14, 'feature', 'hahahah啥叫啊哈哈杀害红色警戒卷和手机号', 'aaaaaaa@gmail.com', 'resolved', NULL, NULL, NULL, '2026-03-09 04:05:42', '2026-03-14 11:14:54');

-- --------------------------------------------------------

--
-- Table structure for table `search_history`
--

CREATE TABLE `search_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `search_query` varchar(255) NOT NULL,
  `search_type` enum('room_id','meeting','user') DEFAULT 'room_id',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `search_history`
--

INSERT INTO `search_history` (`id`, `user_id`, `search_query`, `search_type`, `created_at`) VALUES
(2, 1, 'yB4G9oZeXath', 'room_id', '2026-03-04 17:08:50'),
(5, 1, 'IsP5ccYlAdk2', 'room_id', '2026-03-04 17:08:45'),
(6, 5, 'DLWtnGyfqlSL', 'room_id', '2026-02-27 11:42:42'),
(11, 7, 'RLfpIr3lS3MB', 'room_id', '2026-03-04 06:43:07'),
(18, 1, 'wFx8h3eWInbn', 'room_id', '2026-03-04 17:03:23'),
(19, 1, 'd9fq7iq9dhZc', 'room_id', '2026-03-04 17:08:06'),
(24, 7, 'adsasds', 'room_id', '2026-03-05 06:35:21'),
(25, 5, 'ETjsNDYW118w', 'room_id', '2026-03-05 06:41:04'),
(26, 7, 'asdasd', 'room_id', '2026-03-07 07:22:11'),
(27, 1, 'FcI5zvdkjqy6', 'room_id', '2026-03-07 11:02:54'),
(28, 1, 'gCpSK3gEmVEn', 'room_id', '2026-03-07 12:46:39'),
(29, 1, 'jb3Ulapkggum', 'room_id', '2026-03-07 12:47:15'),
(33, 1, 'W5U9ysIurgJx', 'room_id', '2026-03-07 13:41:01'),
(34, 1, 'PfdtaBBB8F59', 'room_id', '2026-03-09 12:25:49'),
(35, 1, 'fl0qlwLV95Gv', 'room_id', '2026-03-09 12:25:52'),
(36, 1, 'kbPspEkvAd4t', 'room_id', '2026-03-09 11:34:05'),
(37, 7, 'ssssssssssss', 'room_id', '2026-03-12 07:17:51');

-- --------------------------------------------------------

--
-- Table structure for table `signaling`
--

CREATE TABLE `signaling` (
  `id` int(11) NOT NULL,
  `room_id` varchar(50) NOT NULL,
  `from_user_id` int(11) NOT NULL,
  `to_user_id` int(11) NOT NULL,
  `type` varchar(20) NOT NULL,
  `data` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `signaling`
--

INSERT INTO `signaling` (`id`, `room_id`, `from_user_id`, `to_user_id`, `type`, `data`, `created_at`) VALUES
(42, 'eJmuKoxqcGd8', 1, 13, 'kick', 'kicked', '2026-03-09 14:25:25');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `education_level` varchar(50) DEFAULT NULL,
  `program` varchar(100) DEFAULT NULL,
  `semester` varchar(20) DEFAULT NULL,
  `year` varchar(10) DEFAULT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_photo` varchar(255) DEFAULT NULL,
  `last_activity` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `education_level`, `program`, `semester`, `year`, `student_id`, `password`, `role`, `created_at`, `profile_photo`, `last_activity`) VALUES
(1, 'SCSJ2200858', 'SCSJ2200858@segi4u.my', 'Diploma', 'Information Technology', 'September', '2024', 'SCSJ2200858', '$2y$10$szc7IOZ90Omhcm51F3liQence0owkNqtN2CuM9O09aFj6Xk6iX0gO', 'user', '2026-01-13 11:35:00', 'user_1_1773069345.gif', '2026-03-16 11:21:21'),
(4, 'Admin', 'admin@hotmail.com', NULL, NULL, NULL, NULL, NULL, '$2y$10$MA6Okv4Vrs/IjF2oZZlWpeLmk1lc.rCsqsB/qQGfj8opYyGiFh97i', 'Admin', '2026-01-13 11:35:00', 'user_4_1773072981.jpg', '2026-03-14 11:18:55'),
(5, 'SCSJ2200057', 'SCSJ2200057@segi4u.my', NULL, 'Information Technology', 'May', '2024', 'SCSJ2200057', '$2y$10$be8UcGdYFzwoy2fV1Jd2tu.OLQNkd372Z3vMyJTwC7AErVfZv9Fxm', 'User', '2026-01-22 09:01:23', 'user_5_1770886197.jpg', '2026-03-10 07:30:05'),
(7, 'umie12', 'umie@gmail.com', 'Diploma', 'Data Science', 'January', '2020', 'SCSJ1234567', '$2y$10$aXWKLR5lUR1s0B0O5hUnMOcJ7Yw7uayc7eDf2TJQcO.YdeVheDOz2', 'User', '2026-03-04 06:21:15', 'user_7_1773111689.png', '2026-03-12 07:56:30'),
(9, 'SCSJ2200001', 'SCSJ2200001@segi4u.my', NULL, NULL, NULL, NULL, NULL, '$2y$10$1/Qn7UcmYF//S1.ASE2hGOYn4HTRliV5FvETQBXsyD5E2B9J.hwqS', 'User', '2026-03-05 10:25:13', NULL, '2026-03-16 11:13:20'),
(10, 'SCSJ2200002', 'SCSJ2200002@segi4u.my', NULL, NULL, NULL, NULL, NULL, '$2y$10$0ncniOq3GT1gMiJoNC4LUejp/tOmwROsZKWFMLqBjl7ySuRbZ36Rq', 'User', '2026-03-07 12:33:39', NULL, '2026-03-07 12:35:54'),
(11, 'SCSJ2200003', 'Alex@hotmail.com', NULL, NULL, NULL, NULL, NULL, '$2y$10$Aimh/jADWFdmiuL1IYMyZuDqVQP3D.S.9MsiwdT6.ExTezABIa4TC', 'User', '2026-03-07 12:34:28', NULL, '2026-03-07 12:36:08'),
(12, 'SCSJ2200004', 'SCSJ2200004@gmail.com', NULL, NULL, NULL, NULL, NULL, '$2y$10$8Woe75vcw1N/j2dWo7Th6.CnxwPs4m3.cLTQ1yNZ0U85vjEKd8Qq6', 'User', '2026-03-07 12:36:45', NULL, '2026-03-16 11:14:04'),
(13, 'mastura', 'mmastura@gmail.com', 'Master', 'Computer Science', 'January', '', 'SCSJ0000007', '$2y$10$.WgOsmRRs3/GZIWaVyX75.DrcQk20AzHJy94s5f54ayR1QBjL.sKG', 'User', '2026-03-09 02:55:21', 'user_13_1773067532.jpg', '2026-03-10 05:48:46'),
(14, 'chongyi', 'aaaaaaa@gmail.com', '', 'Information Technology', 'January', '2025', 'SCSJ2200858', '$2y$10$FiaeoztpVjjBRsfEzXExfO3pC67kJQBChGtRQXvaYAfPqFuhtCEc.', 'User', '2026-03-09 03:46:32', 'user_14_1773028319.jpg', '2026-03-09 04:05:48'),
(15, 'SCSJ2200008', 'SCSJ2200008@segi4u.my', 'Certificate', 'Information Technology', 'May', '2020', 'SCSJ2200008', '$2y$10$ZFeKM4swQ.jQQdovP/DEPeGJKUwCgitz3Pj3XEZIC4rGZXbqqbwkG', 'User', '2026-03-09 17:33:55', NULL, '2026-03-16 11:14:14'),
(16, 'SCSJ2200009', 'SCSJ2200009@segi4u.my', NULL, NULL, NULL, NULL, NULL, '$2y$10$qg8M7H0bNaLHzKnzI5g5f.nR63jWx5h/y1/wUe05oek.6ZT/xsewW', 'User', '2026-03-16 11:14:38', NULL, '2026-03-16 11:16:25');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room_id` (`room_id`);

--
-- Indexes for table `friends`
--
ALTER TABLE `friends`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_friendship` (`user_id`,`friend_id`),
  ADD KEY `friend_id` (`friend_id`);

--
-- Indexes for table `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `group_members`
--
ALTER TABLE `group_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_group_member` (`group_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `group_messages`
--
ALTER TABLE `group_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `sender_id` (`sender_id`);

--
-- Indexes for table `group_message_files`
--
ALTER TABLE `group_message_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `message_id` (`message_id`);

--
-- Indexes for table `host_actions`
--
ALTER TABLE `host_actions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room_id` (`room_id`),
  ADD KEY `idx_target` (`target_user_id`);

--
-- Indexes for table `meetings`
--
ALTER TABLE `meetings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `host_id` (`host_id`);

--
-- Indexes for table `meeting_participants`
--
ALTER TABLE `meeting_participants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `meeting_id` (`meeting_id`),
  ADD KEY `participant_id` (`participant_id`),
  ADD KEY `idx_meeting_participants` (`meeting_id`,`status`);

--
-- Indexes for table `meeting_participant_settings`
--
ALTER TABLE `meeting_participant_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_device` (`room_id`,`user_id`,`device_type`),
  ADD KEY `idx_room_user` (`room_id`,`user_id`);

--
-- Indexes for table `meeting_tasks`
--
ALTER TABLE `meeting_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room_id` (`room_id`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sender_receiver` (`sender_id`,`receiver_id`),
  ADD KEY `idx_receiver_read` (`receiver_id`,`is_read`);

--
-- Indexes for table `message_files`
--
ALTER TABLE `message_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `message_id` (`message_id`);

--
-- Indexes for table `muted_users`
--
ALTER TABLE `muted_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_room` (`room_id`,`user_id`),
  ADD KEY `idx_room_id` (`room_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_email` (`email`),
  ADD KEY `idx_token` (`token`);

--
-- Indexes for table `peer_connections`
--
ALTER TABLE `peer_connections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_room` (`room_id`,`user_id`),
  ADD KEY `idx_room_id` (`room_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `problem_reports`
--
ALTER TABLE `problem_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `search_history`
--
ALTER TABLE `search_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_search` (`user_id`,`created_at`);

--
-- Indexes for table `signaling`
--
ALTER TABLE `signaling`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room_to` (`room_id`,`to_user_id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_from_user` (`from_user_id`),
  ADD KEY `idx_to_user` (`to_user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=790;

--
-- AUTO_INCREMENT for table `friends`
--
ALTER TABLE `friends`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `groups`
--
ALTER TABLE `groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `group_members`
--
ALTER TABLE `group_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `group_messages`
--
ALTER TABLE `group_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `group_message_files`
--
ALTER TABLE `group_message_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `host_actions`
--
ALTER TABLE `host_actions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `meetings`
--
ALTER TABLE `meetings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=163;

--
-- AUTO_INCREMENT for table `meeting_participants`
--
ALTER TABLE `meeting_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2671;

--
-- AUTO_INCREMENT for table `meeting_participant_settings`
--
ALTER TABLE `meeting_participant_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3469;

--
-- AUTO_INCREMENT for table `meeting_tasks`
--
ALTER TABLE `meeting_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `message_files`
--
ALTER TABLE `message_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `muted_users`
--
ALTER TABLE `muted_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `peer_connections`
--
ALTER TABLE `peer_connections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `problem_reports`
--
ALTER TABLE `problem_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `search_history`
--
ALTER TABLE `search_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `signaling`
--
ALTER TABLE `signaling`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `group_message_files`
--
ALTER TABLE `group_message_files`
  ADD CONSTRAINT `group_message_files_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `group_messages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `meeting_tasks`
--
ALTER TABLE `meeting_tasks`
  ADD CONSTRAINT `meeting_tasks_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `peer_connections`
--
ALTER TABLE `peer_connections`
  ADD CONSTRAINT `peer_connections_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `problem_reports`
--
ALTER TABLE `problem_reports`
  ADD CONSTRAINT `problem_reports_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `search_history`
--
ALTER TABLE `search_history`
  ADD CONSTRAINT `search_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `signaling`
--
ALTER TABLE `signaling`
  ADD CONSTRAINT `signaling_ibfk_1` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `signaling_ibfk_2` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
