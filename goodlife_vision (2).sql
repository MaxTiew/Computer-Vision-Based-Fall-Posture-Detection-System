-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 24, 2026 at 09:17 AM
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
-- Database: `goodlife_vision`
--

-- --------------------------------------------------------

--
-- Table structure for table `caregiver`
--

CREATE TABLE `caregiver` (
  `caregiverID` varchar(50) NOT NULL,
  `NAME` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `PASSWORD` varchar(255) NOT NULL,
  `phoneNumber` varchar(20) NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `elderprofile`
--

CREATE TABLE `elderprofile` (
  `elderID` varchar(50) NOT NULL,
  `caregiverID` varchar(50) DEFAULT NULL,
  `NAME` varchar(100) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `medicalNotes` varchar(255) DEFAULT NULL,
  `profilePhoto` varchar(255) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `emergencycontact`
--

CREATE TABLE `emergencycontact` (
  `contactID` varchar(50) NOT NULL,
  `elderID` varchar(50) DEFAULT NULL,
  `NAME` varchar(100) NOT NULL,
  `relationship` varchar(50) DEFAULT NULL,
  `phone` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `eventlog`
--

CREATE TABLE `eventlog` (
  `eventID` int(11) NOT NULL,
  `elderID` varchar(50) DEFAULT NULL,
  `eventType` varchar(50) NOT NULL,
  `TIMESTAMP` datetime NOT NULL DEFAULT current_timestamp(),
  `STATUS` varchar(20) NOT NULL DEFAULT 'Pending',
  `videoPath` varchar(255) DEFAULT NULL,
  `videoPath2` varchar(255) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `caregiverMessage` varchar(255) DEFAULT NULL,
  `stationID` varchar(32) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `monitoringsession`
--

CREATE TABLE `monitoringsession` (
  `sessionID` int(11) NOT NULL,
  `elderID` varchar(50) DEFAULT NULL,
  `startTime` datetime DEFAULT current_timestamp(),
  `endTime` datetime DEFAULT NULL,
  `STATUS` varchar(20) DEFAULT NULL,
  `videoPath` varchar(255) DEFAULT NULL,
  `videoPath2` varchar(255) DEFAULT NULL,
  `stationID` varchar(32) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `monitorstation`
--

CREATE TABLE `monitorstation` (
  `stationID` varchar(32) NOT NULL,
  `caregiverID` varchar(32) NOT NULL,
  `elderID` varchar(32) DEFAULT NULL,
  `stationName` varchar(120) NOT NULL,
  `loginEmail` varchar(255) NOT NULL,
  `passwordHash` varchar(255) NOT NULL,
  `deviceToken` varchar(128) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'offline',
  `currentCommand` varchar(50) DEFAULT NULL,
  `commandPayload` longtext DEFAULT NULL,
  `lastCommandStatus` longtext DEFAULT NULL,
  `lastHeartbeat` datetime DEFAULT NULL,
  `lastSnapshotPath` varchar(255) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `systemconfiguration`
--

CREATE TABLE `systemconfiguration` (
  `configID` varchar(50) NOT NULL,
  `elderID` varchar(50) DEFAULT NULL,
  `sensitivityThreshold` varchar(20) NOT NULL DEFAULT 'Medium',
  `alertChannel` varchar(50) NOT NULL DEFAULT 'App',
  `timeoutResponse` int(11) DEFAULT 30,
  `silentEmergencyEnabled` tinyint(1) DEFAULT 1,
  `createdAt` datetime DEFAULT current_timestamp(),
  `updatedAt` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `caregiver`
--
ALTER TABLE `caregiver`
  ADD PRIMARY KEY (`caregiverID`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `elderprofile`
--
ALTER TABLE `elderprofile`
  ADD PRIMARY KEY (`elderID`),
  ADD KEY `caregiverID` (`caregiverID`);

--
-- Indexes for table `emergencycontact`
--
ALTER TABLE `emergencycontact`
  ADD PRIMARY KEY (`contactID`),
  ADD KEY `elderID` (`elderID`);

--
-- Indexes for table `eventlog`
--
ALTER TABLE `eventlog`
  ADD PRIMARY KEY (`eventID`),
  ADD KEY `elderID` (`elderID`);

--
-- Indexes for table `monitoringsession`
--
ALTER TABLE `monitoringsession`
  ADD PRIMARY KEY (`sessionID`),
  ADD KEY `elderID` (`elderID`);

--
-- Indexes for table `monitorstation`
--
ALTER TABLE `monitorstation`
  ADD PRIMARY KEY (`stationID`);

--
-- Indexes for table `systemconfiguration`
--
ALTER TABLE `systemconfiguration`
  ADD PRIMARY KEY (`configID`),
  ADD KEY `elderID` (`elderID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `eventlog`
--
ALTER TABLE `eventlog`
  MODIFY `eventID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- AUTO_INCREMENT for table `monitoringsession`
--
ALTER TABLE `monitoringsession`
  MODIFY `sessionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=222;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `elderprofile`
--
ALTER TABLE `elderprofile`
  ADD CONSTRAINT `elderprofile_ibfk_1` FOREIGN KEY (`caregiverID`) REFERENCES `caregiver` (`caregiverID`) ON DELETE CASCADE;

--
-- Constraints for table `emergencycontact`
--
ALTER TABLE `emergencycontact`
  ADD CONSTRAINT `emergencycontact_ibfk_1` FOREIGN KEY (`elderID`) REFERENCES `elderprofile` (`elderID`) ON DELETE CASCADE;

--
-- Constraints for table `eventlog`
--
ALTER TABLE `eventlog`
  ADD CONSTRAINT `eventlog_ibfk_1` FOREIGN KEY (`elderID`) REFERENCES `elderprofile` (`elderID`) ON DELETE CASCADE;

--
-- Constraints for table `monitoringsession`
--
ALTER TABLE `monitoringsession`
  ADD CONSTRAINT `monitoringsession_ibfk_1` FOREIGN KEY (`elderID`) REFERENCES `elderprofile` (`elderID`) ON DELETE CASCADE;

--
-- Constraints for table `systemconfiguration`
--
ALTER TABLE `systemconfiguration`
  ADD CONSTRAINT `systemconfiguration_ibfk_1` FOREIGN KEY (`elderID`) REFERENCES `elderprofile` (`elderID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
