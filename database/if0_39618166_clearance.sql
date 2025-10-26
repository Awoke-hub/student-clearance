-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql102.infinityfree.com
-- Generation Time: Oct 25, 2025 at 07:33 PM
-- Server version: 11.4.7-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_39618166_clearance`
--

-- --------------------------------------------------------

--
-- Table structure for table `academicstaff_clearance`
--

CREATE TABLE `academicstaff_clearance` (
  `id` int(11) NOT NULL,
  `student_id` varchar(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reject_reason` text DEFAULT NULL,
  `academic_year` int(11) NOT NULL DEFAULT year(curdate())
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` char(10) NOT NULL,
  `role` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `name`, `last_name`, `username`, `password`, `email`, `phone`, `role`) VALUES
(1, 'Aman', 'Baye', 'cafeteria', '$2y$10$YvFelcJKQLn.ZW5HRcZxWu3yZsojj9ar3Ixr3VUXLRiDrI/MGinAC', 'admin@university.edu', '0912345678', 'cafeteria_admin'),
(2, 'Azanaw', 'Nega', 'system', '$2y$10$nIWSWPzLoXwBlT4.xePNuOD7wyikIM4lUDDB58nLtDF.m9w5Z6YZ.', 'aza@gmail.com', '0986767676', 'system_admin'),
(3, 'Awoke', 'Derssie', 'registrar', '$2y$10$nr5jaHlx18dUgrbCjKe3z.B8XHvbDm5Cd3j6Kw32PpYorRJpn4UBG', 'tomasderese49@gmail.com', '0939013630', 'registrar_admin'),
(10, 'Amanuel', 'Neby', 'dormitory', '$2y$10$RvcEI1/AvazqTMxGg4WmX.kxlxmPnw53mlO3qDIobGJu.xaDPqKzW', 'tomasdere@gmail.com', '0939013630', 'dormitory_admin'),
(11, 'Aman', 'Adis', 'library', '$2y$10$2BI9RekmX2NdanbBcqWMouVzb7j3R4Nuns5bg5cEILNI8bT3kQ7h2', 'adsmin@university.edu', '0912345678', 'library_admin'),
(13, 'Tadele', 'Derso', 'department', '$2y$10$hneo0kNcPOr7L1teMuq/Y.XN.NGi7lAjUxijThaxbz7yDA7itIHga', 'adsvmin@university.edu', '0915166228', 'department_admin'),
(17, 'Abrham', 'Daniel', 'protector', '$2y$10$FUziIUpA.2BxmjaKzfuLI.8Abnr0b9W5qsIVRYiimC80Z8MPfEpOy', 'tilahunsitotaw418@gmail.com', '0939013639', 'personal_protector');

-- --------------------------------------------------------

--
-- Table structure for table `cafeteria_clearance`
--

CREATE TABLE `cafeteria_clearance` (
  `id` int(11) NOT NULL,
  `student_id` varchar(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reject_reason` text DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `academic_year` int(11) NOT NULL DEFAULT year(curdate())
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=unread, 1=read'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `message`, `submitted_at`, `is_read`) VALUES
(8, 'Tomas Derese', 'tomasderese49@gmail.com', 'Fac', '2025-08-07 06:58:18', 1),
(9, 'Awoke', 'tomasderese49@gmail.com', 'sent', '2025-10-25 21:57:38', 1),
(10, 'Awoke', 'tomasderese49@gmail.com', 'sent', '2025-10-25 22:11:36', 1);

-- --------------------------------------------------------

--
-- Table structure for table `department_clearance`
--

CREATE TABLE `department_clearance` (
  `id` int(11) NOT NULL,
  `student_id` varchar(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reject_reason` text DEFAULT NULL,
  `academic_year` int(11) NOT NULL DEFAULT year(curdate())
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dormitory_clearance`
--

CREATE TABLE `dormitory_clearance` (
  `id` int(11) NOT NULL,
  `student_id` varchar(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reject_reason` text DEFAULT NULL,
  `academic_year` int(11) NOT NULL DEFAULT year(curdate())
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_notifications`
--

CREATE TABLE `email_notifications` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `status` enum('approved','rejected') NOT NULL,
  `sent_status` enum('sent','failed') NOT NULL,
  `sent_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `email_notifications`
--

INSERT INTO `email_notifications` (`id`, `student_id`, `status`, `sent_status`, `sent_at`) VALUES
(1, 'DBU003', 'rejected', 'sent', '2025-10-22 10:11:33'),
(2, 'DBU003', 'approved', 'sent', '2025-10-22 10:11:43'),
(3, 'DBU001', 'rejected', 'sent', '2025-10-22 10:11:58'),
(4, 'DBU001', 'approved', 'sent', '2025-10-22 10:12:00');

-- --------------------------------------------------------

--
-- Table structure for table `final_clearance`
--

CREATE TABLE `final_clearance` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `year` varchar(10) NOT NULL,
  `status` enum('pending','approved','rejected','finalized') DEFAULT 'pending',
  `reject_reason` varchar(255) DEFAULT NULL,
  `date_sent` timestamp NOT NULL DEFAULT current_timestamp(),
  `department` varchar(100) DEFAULT NULL,
  `academic_year` int(11) NOT NULL DEFAULT year(curdate()),
  `is_read` tinyint(4) DEFAULT 0,
  `email_sent` tinyint(1) DEFAULT 0,
  `email_sent_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `library_clearance`
--

CREATE TABLE `library_clearance` (
  `id` int(11) NOT NULL,
  `student_id` varchar(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reject_reason` text DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `academic_year` int(11) NOT NULL DEFAULT year(curdate())
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(4) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `password_reset_tokens`
--

INSERT INTO `password_reset_tokens` (`id`, `student_id`, `token`, `expires_at`, `used`, `created_at`) VALUES
(10, 56, '6c40df5e4099688672d7d03fc1f647c4a596ba9f0233ee47b157625856a34789f3f4182aeff3f111019e75167ff5a1283c14', '2025-10-23 16:04:55', 0, '2025-10-23 19:04:55'),
(7, 69, 'ef5439e8eb0901589f32bdd977e19e707f9515f27ba4f69b577e595ff16f46d3e0202a555353fb8abf81808aa0b815e4b6d5', '2025-10-23 16:02:45', 0, '2025-10-23 19:02:45');

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `id` int(11) NOT NULL,
  `student_id` varchar(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` char(10) NOT NULL,
  `email` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `year` varchar(20) DEFAULT '1st Year',
  `semester` varchar(10) NOT NULL DEFAULT '1',
  `profile_picture` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `student`
--

INSERT INTO `student` (`id`, `student_id`, `name`, `last_name`, `phone`, `email`, `department`, `username`, `password`, `year`, `semester`, `profile_picture`, `status`) VALUES
(82, 'DBU001', 'Awoke', 'Derssie', '0939013639', 'tomasderese49@gmail.com', 'Information technology', 'awoke123', '$2y$10$tACf1USmLxE9vdx4ZI8R0.u/srbWzj0Bi1W7psTCBNEB/IhbBdJsO', '3', '2', 'uploads/profile_pictures/profile_DBU001_1761435090.jpg', 'active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academicstaff_clearance`
--
ALTER TABLE `academicstaff_clearance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_academicstaff_clearance_student` (`student_id`);

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `cafeteria_clearance`
--
ALTER TABLE `cafeteria_clearance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cafeteria_clearance_student` (`student_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `department_clearance`
--
ALTER TABLE `department_clearance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_department_clearance_student` (`student_id`);

--
-- Indexes for table `dormitory_clearance`
--
ALTER TABLE `dormitory_clearance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_dormitory_clearance_student` (`student_id`);

--
-- Indexes for table `email_notifications`
--
ALTER TABLE `email_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_sent_at` (`sent_at`);

--
-- Indexes for table `final_clearance`
--
ALTER TABLE `final_clearance`
  ADD KEY `fk_final_clearance_student` (`student_id`);

--
-- Indexes for table `library_clearance`
--
ALTER TABLE `library_clearance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_library_clearance_student` (`student_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `unique_id` (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academicstaff_clearance`
--
ALTER TABLE `academicstaff_clearance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `cafeteria_clearance`
--
ALTER TABLE `cafeteria_clearance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `department_clearance`
--
ALTER TABLE `department_clearance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `dormitory_clearance`
--
ALTER TABLE `dormitory_clearance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `email_notifications`
--
ALTER TABLE `email_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `library_clearance`
--
ALTER TABLE `library_clearance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `student`
--
ALTER TABLE `student`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `academicstaff_clearance`
--
ALTER TABLE `academicstaff_clearance`
  ADD CONSTRAINT `fk_academicstaff_clearance_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `cafeteria_clearance`
--
ALTER TABLE `cafeteria_clearance`
  ADD CONSTRAINT `fk_cafeteria_clearance_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `department_clearance`
--
ALTER TABLE `department_clearance`
  ADD CONSTRAINT `fk_department_clearance_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `dormitory_clearance`
--
ALTER TABLE `dormitory_clearance`
  ADD CONSTRAINT `fk_dormitory_clearance_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `final_clearance`
--
ALTER TABLE `final_clearance`
  ADD CONSTRAINT `fk_final_clearance_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `library_clearance`
--
ALTER TABLE `library_clearance`
  ADD CONSTRAINT `fk_library_clearance_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
