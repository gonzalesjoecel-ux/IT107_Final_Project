-- Encryptica — IT 107 Final Laboratory Project
-- Complete SQL dump: schema + test data for all eight tables.
-- Tables: users, user_security_answers, otp_codes, activity_log,
--         security_flags, password_history, ip_blocks, items.
-- All sample IP addresses are from the documentation ranges
-- (192.0.2.0/24, 198.51.100.0/24, 203.0.113.0/24). No localhost address
-- is blocked, so the demo can still sign in.
-- No real passwords or SMTP secrets are stored in this file: only salted
-- one-way password hashes are included.
--
-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: encryptica
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_number` varchar(9) DEFAULT NULL,
  `fullname` varchar(100) NOT NULL,
  `first_name` varchar(25) DEFAULT NULL,
  `middle_name` varchar(25) DEFAULT NULL,
  `family_name` varchar(25) DEFAULT NULL,
  `name_extension` varchar(10) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` text NOT NULL,
  `failed_attempts` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `locked_until` datetime DEFAULT NULL,
  `role` varchar(10) NOT NULL DEFAULT 'user',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `reset_requested_at` datetime DEFAULT NULL,
  `house_street` varchar(150) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `municipality_city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `privacy_consent` tinyint(1) NOT NULL DEFAULT 0,
  `consent_timestamp` datetime DEFAULT NULL,
  `lockout_level` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `last_failed_at` datetime DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 1,
  `recovery_mobile` varchar(11) DEFAULT NULL,
  `account_type` varchar(20) DEFAULT NULL,
  `vault_display_name` varchar(30) DEFAULT NULL,
  `organization_name` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `id_number` (`id_number`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (6,'2026-0001','Karl Reyes','Karl','Santos','Reyes',NULL,'2002-03-14','karl@gmail.com','karl123','$2y$10$76cBv/.HGcRh40WX2PxB/.EqmUc9oODGJ3HsRL.Sc6I7M8LBhBD/q',0,'2026-09-15 11:39:52',NULL,'user',1,'2026-11-16 13:22:49',NULL,'12 Mabini Street','Barangay 1','Cagayan de Oro City','Misamis Oriental','Region X (Northern Mindanao)','9000',1,'2026-09-15 19:39:52',0,NULL,'Philippines',1,'09171234501','Personal','Karl\'s Vault',NULL),(9,'2026-0002','Dianne Rocaberte','Dianne','Cruz','Rocaberte',NULL,'2003-07-22','rocaberte@gmail.com','rocaberts','$2y$10$wUD7u9De19Bn4Vvv8yKcZuioPRR8.ygDVHRUc3s8cclO33Ue07hY.',0,'2026-09-15 12:31:34',NULL,'user',1,'2026-11-16 12:41:49',NULL,'45 Rizal Street','Barangay 5','Cagayan de Oro City','Misamis Oriental','Region X (Northern Mindanao)','9000',1,'2026-09-15 20:31:34',0,NULL,'Philippines',1,'09171234502','Student','Dianne\'s Vault',NULL),(10,'2026-0003','Andy Crasco','Andy','Dela Cruz','Crasco',NULL,'2001-11-05','crascoandy@csucc.edu.ph','crasco123','$2y$10$YFYtrQ3dhkPgHyMl/QaDJ.Ib4e1TXclAy8i1qI0iqPhIdDobYU6Dq',0,'2026-09-17 15:28:20',NULL,'user',1,'2026-11-16 12:00:49',NULL,'8 Luna Street','Barangay 12','Cagayan de Oro City','Misamis Oriental','Region X (Northern Mindanao)','9000',1,'2026-09-17 23:28:20',0,NULL,'Philippines',1,'09171234503','Professional','Andy\'s Vault',NULL),(11,'2026-0004','Xander Lim','Xander','Tan','Lim',NULL,'2002-06-18','xander@gmail.com','xanxan22','$2y$10$JnmF7I6KoaM/17kU.5kIfu8wxCd8QQUxYCVuWD/ucV9UAKmZreame',0,'2026-09-17 15:48:20',NULL,'user',1,'2026-11-16 11:19:49',NULL,'21 Del Pilar Street','Barangay 3','Cagayan de Oro City','Misamis Oriental','Region X (Northern Mindanao)','9000',1,'2026-09-17 23:48:20',0,NULL,'Philippines',1,'09171234504','Personal','Xander\'s Vault',NULL),(12,'2026-0005','Admin User','Admin','Encryptica','User',NULL,'2000-01-01','gonzalesjoecel@gmail.com','admin123','$2y$10$nNsL3pKQf5cELpPLiCPyB.me9YgzQpFTLtHZAZ7Uwof6LgW64iVya',0,'2026-09-17 16:26:16',NULL,'admin',1,'2026-11-18 11:13:20',NULL,'1 Encryptica Avenue','Barangay 10','Cagayan de Oro City','Misamis Oriental','Region X (Northern Mindanao)','9000',1,'2026-09-18 00:26:16',0,NULL,'Philippines',1,'09171234505','Organization','Encryptica Admin','Encryptica'),(13,'2026-0006','Joziel Gaonsl','Joziel','Reyes','Gaonsl',NULL,'2003-02-09','jozziel@gmail.com','joziel2121','$2y$10$UomWqfjmNGlCPzds3QY6me7USQr69wqo73xAENLWJBZJZfqJkQRJK',0,'2026-09-21 05:56:20',NULL,'user',1,'2026-11-16 09:57:49','2026-11-07 16:59:40','33 Mabini Street','Barangay 7','Cagayan de Oro City','Misamis Oriental','Region X (Northern Mindanao)','9000',1,'2026-09-21 13:56:20',0,NULL,'Philippines',1,'09171234506','Student','Joziel\'s Vault',NULL),(14,'2026-0007','Joecel Santos Gonzales','Joecel','Santos','Gonzales',NULL,'2002-12-03','joecel@gmail.com','joecel123','$2y$10$u01Z1Pyj.W5pEqdDcI.69uACY0vWqK0elof6p.55E3ZkOJ90QWke6',0,'2026-10-26 06:02:20',NULL,'user',1,'2026-11-16 09:16:49',NULL,'17 San Jose Street','Barangay 9','Cagayan de Oro City','Misamis Oriental','Region X (Northern Mindanao)','9000',1,'2026-10-26 14:02:20',0,NULL,'Philippines',1,'09171234507','Personal','Joecel\'s Vault',NULL),(17,'2026-0008','Yvonne Pormento','Yvonne','Dela Cruz','Pormento',NULL,'2001-09-27','yvonne@gmail.com','yvonnep11','$2y$10$9ipLrOMU87eMq9RSeC/n5ualX9H3tjoyf3GJciGvU6OtVrtm9pkbK',0,'2026-11-01 05:30:32',NULL,'user',1,'2026-11-16 08:35:49',NULL,'5 Acacia Street','Barangay 4','Cagayan de Oro City','Misamis Oriental','Region X (Northern Mindanao)','9000',1,'2026-11-01 13:30:32',0,NULL,'Philippines',1,'09171234508','Professional','Yvonne\'s Vault',NULL),(27,'2026-0009','Dexter Gonzales','Dexter','Lim','Gonzales',NULL,'2002-08-11','dexter@gmail.com','dext_er13','$2y$10$cCrfyFXOkObMbnYVOkJ83ekfN6bRh4Qhnve/9XEyyMX.vgHCQPUaS',0,'2026-11-07 09:01:17',NULL,'user',1,'2026-11-16 07:54:49',NULL,'29 Rizal Street','Barangay 6','Cagayan de Oro City','Misamis Oriental','Region X (Northern Mindanao)','9000',1,'2026-11-07 17:01:17',0,NULL,'Philippines',1,'09171234509','Student','Dexter\'s Vault',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_security_answers`
--

DROP TABLE IF EXISTS `user_security_answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_security_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `question_code` varchar(10) NOT NULL,
  `answer_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_question` (`user_id`,`question_code`),
  CONSTRAINT `fk_security_answers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_security_answers`
--

LOCK TABLES `user_security_answers` WRITE;
/*!40000 ALTER TABLE `user_security_answers` DISABLE KEYS */;
INSERT INTO `user_security_answers` VALUES (13,6,'Q1','$2y$10$sxchrBh/CNZt5XPyAyDFReEnIiJi6eFiQUHlgJg3XPlIjKJDHsPTq','2026-11-15 22:26:30'),(14,6,'Q4','$2y$10$LRi1K4AU9ChHnVfZ8YjiwOc5Gc8TOfsIBiXRmdSLzPHPSEnmAxtoS','2026-11-15 22:26:30'),(15,6,'Q7','$2y$10$7B536xPRrY.OT9h.XMINUeYbZl7RTLAvwx5usadrmf74/g4QgmxvW','2026-11-15 22:26:31'),(16,9,'Q1','$2y$10$VBlbswVr1qZxRa60YRKWuu2VK/NxxbShpxPFDvCJL/ebUUEKsHLYe','2026-11-15 22:26:31'),(17,9,'Q4','$2y$10$eVwN0/Y5C0rBXtQ.U2F9EucewNTY91M7YuZ5AcEQBxraRMEj6pSZ2','2026-11-15 22:26:31'),(18,9,'Q7','$2y$10$fC6QN26RZj7trY7sKtHyTO3tOfWQQ2MjXJwToVcaZURjEtSG9bTZW','2026-11-15 22:26:31'),(19,10,'Q1','$2y$10$mBAUNE35K0gVChby9asVB.dXC05dYHBdDK3gplF8Cc8LzrpjR5vQG','2026-11-15 22:26:31'),(20,10,'Q4','$2y$10$0O0rHzkzwcBPSV8mnW/L8eyDxeldmVH5slopapdTNOwC0VuxNdRcC','2026-11-15 22:26:31'),(21,10,'Q7','$2y$10$0IbcyYfTveng2AbdOb7zkuPqvyoDR240w/VyFBw0m6WRG1gzHXK7a','2026-11-15 22:26:31'),(22,11,'Q1','$2y$10$5envB6o8aFyrtqipJmbDo.iA.9KTItx7eGe9LAoVlFkb7aTYyYkqS','2026-11-15 22:26:31'),(23,11,'Q4','$2y$10$bOjrSz8qHwK0zOzRXHb0G.OOCGqmtudubFYDCKQbHUNTEcxK54uRm','2026-11-15 22:26:32'),(24,11,'Q7','$2y$10$0BrAS35ob3ADTH.YHbAIFeEhN9NJNmlgY13ZxaIYSANqpv6B1U.Re','2026-11-15 22:26:32'),(25,12,'Q1','$2y$10$XPnMndkb1Pkgy9StUmTyr.CpA/R.bn8IAwIfq8t/wPqeKLDjYzVoq','2026-11-15 22:26:32'),(26,12,'Q4','$2y$10$qNV0kFzQW3m06laOldBHT.RdmjaVoBWp2PyAx.8BSd9/J0rxUdBbS','2026-11-15 22:26:32'),(27,12,'Q7','$2y$10$oWBNqGp0lGPwUDJBvGIjTukKkRuvLNIIT69cSj0Bp2k5SrPCOWaA2','2026-11-15 22:26:32'),(28,13,'Q1','$2y$10$IXyzP2bUFLoxhrrIKXndEuIc2l2sOlYhWgNQA7AJeJ8/DMK/PlO4W','2026-11-15 22:26:32'),(29,13,'Q4','$2y$10$Fc3p8KAQVkPUmYAHdwf.PuBsnmw3gGVOcFkD8ry5OCWV0GVIxnvGO','2026-11-15 22:26:32'),(30,13,'Q7','$2y$10$N6BjpQVgU67cdOM8fACcMuBmI8vmckeC8hrtbLg81cBDD9.RjVBpa','2026-11-15 22:26:32'),(31,14,'Q1','$2y$10$TBkIkuFcGL2vGbCDid14/eE3PhmT1kIaQSHhj1XsF0QRGiXZua8/S','2026-11-15 22:26:32'),(32,14,'Q4','$2y$10$3emIrcc3MI7Zh7NPFAdXh.6fYm8ron1IaSAsf0pxcrWyL.KzWK6Xu','2026-11-15 22:26:32'),(33,14,'Q7','$2y$10$.j3QZBI7YxT.mPRQLDNXN.NuMKPZ4PJ1iEpOEfseKkMohUXQVysWa','2026-11-15 22:26:33'),(34,17,'Q1','$2y$10$cYphppbHICnKi3ChP7lEpuQniEhxh/6pjJ4pL2kp5uYE6axhNuQue','2026-11-15 22:26:33'),(35,17,'Q4','$2y$10$2wjRR/lTu5dJGlW/NWa.aO5h1FKM01mvofZt0YlFjeFH4eSU3d4mK','2026-11-15 22:26:33'),(36,17,'Q7','$2y$10$FgjBjIPgjJO0JpwV.R1S5eYkQLhtsA1bTXgA4cRPF5nOvJEQBIcSK','2026-11-15 22:26:33'),(37,27,'Q1','$2y$10$o2WFgN3z5LXKgyEUR/yZ3ubTPUNZ7sy8BlqnACSGnjTp5et7sqLIu','2026-11-15 22:26:33'),(38,27,'Q4','$2y$10$gOqSt7H1FAD4yldtQ9imjeamYkDO6mWJvB0bmiW1if/j.449Ud40y','2026-11-15 22:26:33'),(39,27,'Q7','$2y$10$hyOg/HQYT59C7U0ledmTh.hpSEAVWz4dM4HzDXRKiVBPL/hyVZvt6','2026-11-15 22:26:33');
/*!40000 ALTER TABLE `user_security_answers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `otp_codes`
--

DROP TABLE IF EXISTS `otp_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `otp_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `identifier` varchar(150) NOT NULL,
  `purpose` enum('registration','login','forgot_password') NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_otp_identifier` (`identifier`),
  KEY `idx_otp_purpose` (`purpose`),
  KEY `idx_otp_expiry` (`expires_at`),
  KEY `fk_otp_user` (`user_id`),
  CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `otp_codes`
--

LOCK TABLES `otp_codes` WRITE;
/*!40000 ALTER TABLE `otp_codes` DISABLE KEYS */;
INSERT INTO `otp_codes` VALUES (20,12,'admin123','login','$2y$10$qUiLgmrUH1ztGhojglZsT.rx54jp/sQtKbIxfkjjgXNuyfaSQbIey',1,'2026-11-16 13:46:26','2026-11-16 13:51:26','2026-11-16 13:47:04','::1'),(21,12,'admin123','login','$2y$10$ykM/lWTobtRMiCU.AFSCG.4flQSRrhlA01ALMVtxR4.xuFnWRcuQm',1,'2026-11-16 14:15:30','2026-11-16 14:20:30','2026-11-16 14:15:48','::1'),(22,12,'admin123','login','$2y$10$dzXnHDPWBr1QLWen5M8Rp.HlI2BXtNn5uBO02QYjrfKANy2Xo0TOy',1,'2026-11-16 14:27:23','2026-11-16 14:32:23','2026-11-16 14:27:37','::1'),(23,12,'admin123','login','$2y$10$raXR0jQDtIgo2QQk9adOXewae4W0xuS/YfcupRpaDf.dWUF9/dAiu',0,'2026-11-18 11:10:00','2026-11-18 11:15:00','2026-11-18 11:12:57','::1'),(24,12,'admin123','login','$2y$10$ej9HLMT17VT7WZt7EHG0Wu.MHlNbPk7Rww2fs/tmVyqozNY.BnRDC',1,'2026-11-18 11:12:57','2026-11-18 11:17:57','2026-11-18 11:13:20','::1'),(25,12,'admin123','login','$2y$10$UK/vbO8JzoNuzxDrL0TFZOlSgqJ7N2mzKOWZKFjhtVAcjPc0k2TJ.',0,'2026-11-23 09:53:07','2026-11-23 09:58:07',NULL,'::1');
/*!40000 ALTER TABLE `otp_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `event` varchar(40) NOT NULL,
  `detail` varchar(255) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `username_typed` varchar(100) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_log_user` (`user_id`),
  KEY `idx_log_event` (`event`),
  KEY `idx_log_time` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=389 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_log`
--

LOCK TABLES `activity_log` WRITE;
/*!40000 ALTER TABLE `activity_log` DISABLE KEYS */;
INSERT INTO `activity_log` VALUES (254,6,NULL,'register','account created','127.0.0.1','2026-09-15 19:39:52',NULL,NULL),(255,9,NULL,'register','account created','127.0.0.1','2026-09-15 20:31:34',NULL,NULL),(256,10,NULL,'register','account created','127.0.0.1','2026-09-17 23:28:20',NULL,NULL),(257,11,NULL,'register','account created','127.0.0.1','2026-09-17 23:48:20',NULL,NULL),(258,12,NULL,'register','account created','127.0.0.1','2026-09-18 00:26:16',NULL,NULL),(259,13,NULL,'register','account created','127.0.0.1','2026-09-21 13:56:20',NULL,NULL),(260,14,NULL,'register','account created','127.0.0.1','2026-10-26 14:02:20',NULL,NULL),(261,17,NULL,'register','account created','127.0.0.1','2026-11-01 13:30:32',NULL,NULL),(262,27,NULL,'register','account created','127.0.0.1','2026-11-07 17:01:17',NULL,NULL),(263,17,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-10 09:52:00',NULL,NULL),(264,17,NULL,'logout',NULL,'127.0.0.1','2026-11-10 10:44:00',NULL,NULL),(265,9,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-10 12:55:00',NULL,NULL),(266,9,NULL,'logout',NULL,'127.0.0.1','2026-11-10 14:00:00',NULL,NULL),(267,27,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-10 15:04:00',NULL,NULL),(268,27,NULL,'logout',NULL,'127.0.0.1','2026-11-10 15:38:00',NULL,NULL),(269,14,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-10 16:04:00',NULL,NULL),(270,11,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-10 16:11:00',NULL,NULL),(271,11,NULL,'logout',NULL,'127.0.0.1','2026-11-10 16:52:00',NULL,NULL),(272,14,NULL,'logout',NULL,'127.0.0.1','2026-11-10 17:08:00',NULL,NULL),(273,10,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-10 18:06:00',NULL,NULL),(274,6,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-10 18:12:00',NULL,NULL),(275,10,NULL,'profile_update','updated contact details','127.0.0.1','2026-11-10 18:27:00',NULL,NULL),(276,10,NULL,'logout',NULL,'127.0.0.1','2026-11-10 18:32:00',NULL,NULL),(277,6,NULL,'logout',NULL,'127.0.0.1','2026-11-10 18:54:00',NULL,NULL),(278,11,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-11 08:21:00',NULL,NULL),(279,27,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-11 08:34:00',NULL,NULL),(280,27,NULL,'profile_update','updated contact details','127.0.0.1','2026-11-11 08:43:00',NULL,NULL),(281,11,NULL,'logout',NULL,'127.0.0.1','2026-11-11 09:02:00',NULL,NULL),(282,27,NULL,'logout',NULL,'127.0.0.1','2026-11-11 09:08:00',NULL,NULL),(283,14,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-11 09:22:00',NULL,NULL),(284,14,NULL,'profile_update','updated contact details','127.0.0.1','2026-11-11 09:39:00',NULL,NULL),(285,14,NULL,'logout',NULL,'127.0.0.1','2026-11-11 09:44:00',NULL,NULL),(286,6,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-11 12:34:00',NULL,NULL),(287,6,NULL,'profile_update','updated contact details','127.0.0.1','2026-11-11 12:43:00',NULL,NULL),(288,6,NULL,'logout',NULL,'127.0.0.1','2026-11-11 13:08:00',NULL,NULL),(289,12,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-11 16:13:00',NULL,NULL),(290,12,NULL,'logout',NULL,'127.0.0.1','2026-11-11 17:16:00',NULL,NULL),(291,17,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-12 08:32:00',NULL,NULL),(292,17,NULL,'logout',NULL,'127.0.0.1','2026-11-12 09:14:00',NULL,NULL),(293,6,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-12 10:24:00',NULL,NULL),(294,11,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-12 10:35:00',NULL,NULL),(295,6,NULL,'logout',NULL,'127.0.0.1','2026-11-12 11:08:00',NULL,NULL),(296,11,NULL,'logout',NULL,'127.0.0.1','2026-11-12 11:20:00',NULL,NULL),(297,10,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-12 12:34:00',NULL,NULL),(298,10,NULL,'profile_update','updated contact details','127.0.0.1','2026-11-12 12:43:00',NULL,NULL),(299,10,NULL,'logout',NULL,'127.0.0.1','2026-11-12 13:18:00',NULL,NULL),(300,14,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-12 15:52:00',NULL,NULL),(301,14,NULL,'logout',NULL,'127.0.0.1','2026-11-12 16:14:00',NULL,NULL),(302,13,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-12 17:41:00',NULL,NULL),(303,13,NULL,'logout',NULL,'127.0.0.1','2026-11-12 18:42:00',NULL,NULL),(304,14,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-13 08:11:00',NULL,NULL),(305,14,NULL,'logout',NULL,'127.0.0.1','2026-11-13 08:42:00',NULL,NULL),(306,27,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-13 11:15:00',NULL,NULL),(307,27,NULL,'logout',NULL,'127.0.0.1','2026-11-13 12:00:00',NULL,NULL),(308,11,NULL,'login_fail','wrong password','127.0.0.1','2026-11-13 12:28:00',NULL,NULL),(309,11,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-13 12:36:00',NULL,NULL),(310,11,NULL,'logout',NULL,'127.0.0.1','2026-11-13 13:12:00',NULL,NULL),(311,9,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-13 13:40:00',NULL,NULL),(312,12,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-13 13:44:00',NULL,NULL),(313,9,NULL,'logout',NULL,'127.0.0.1','2026-11-13 14:20:00',NULL,NULL),(314,12,NULL,'logout',NULL,'127.0.0.1','2026-11-13 14:38:00',NULL,NULL),(315,13,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-13 15:30:00',NULL,NULL),(316,13,NULL,'profile_update','updated contact details','127.0.0.1','2026-11-13 15:35:00',NULL,NULL),(317,13,NULL,'logout',NULL,'127.0.0.1','2026-11-13 15:50:00',NULL,NULL),(318,6,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-14 10:33:00',NULL,NULL),(319,6,NULL,'password_change','from profile','127.0.0.1','2026-11-14 10:51:00',NULL,NULL),(320,6,NULL,'logout',NULL,'127.0.0.1','2026-11-14 11:26:00',NULL,NULL),(321,11,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-14 14:02:00',NULL,NULL),(322,11,NULL,'profile_update','updated contact details','127.0.0.1','2026-11-14 14:19:00',NULL,NULL),(323,11,NULL,'logout',NULL,'127.0.0.1','2026-11-14 14:24:00',NULL,NULL),(324,9,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-14 18:02:00',NULL,NULL),(325,9,NULL,'profile_update','updated contact details','127.0.0.1','2026-11-14 18:19:00',NULL,NULL),(326,27,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-14 18:25:00',NULL,NULL),(327,9,NULL,'logout',NULL,'127.0.0.1','2026-11-14 18:34:00',NULL,NULL),(328,27,NULL,'logout',NULL,'127.0.0.1','2026-11-14 19:30:00',NULL,NULL),(329,6,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-15 07:51:00',NULL,NULL),(330,6,NULL,'password_change','from profile','127.0.0.1','2026-11-15 08:02:00',NULL,NULL),(331,6,NULL,'logout',NULL,'127.0.0.1','2026-11-15 08:12:00',NULL,NULL),(332,12,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-15 08:16:00',NULL,NULL),(333,12,NULL,'logout',NULL,'127.0.0.1','2026-11-15 09:22:00',NULL,NULL),(334,13,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-15 09:22:00',NULL,NULL),(335,13,NULL,'profile_update','updated contact details','127.0.0.1','2026-11-15 09:39:00',NULL,NULL),(336,13,NULL,'logout',NULL,'127.0.0.1','2026-11-15 09:54:00',NULL,NULL),(337,9,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-15 10:20:00',NULL,NULL),(338,27,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-15 10:31:00',NULL,NULL),(339,9,NULL,'logout',NULL,'127.0.0.1','2026-11-15 11:00:00',NULL,NULL),(340,27,NULL,'logout',NULL,'127.0.0.1','2026-11-15 11:22:00',NULL,NULL),(341,14,NULL,'login_fail','wrong password','127.0.0.1','2026-11-15 14:55:00',NULL,NULL),(342,14,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-15 15:03:00',NULL,NULL),(343,14,NULL,'password_change','from profile','127.0.0.1','2026-11-15 15:36:00',NULL,NULL),(344,14,NULL,'logout',NULL,'127.0.0.1','2026-11-15 15:46:00',NULL,NULL),(345,11,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-15 16:20:00',NULL,NULL),(346,11,NULL,'logout',NULL,'127.0.0.1','2026-11-15 17:00:00',NULL,NULL),(347,17,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-15 18:15:00',NULL,NULL),(348,10,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-15 18:21:00',NULL,NULL),(349,10,NULL,'password_change','from profile','127.0.0.1','2026-11-15 18:52:00',NULL,NULL),(350,17,NULL,'logout',NULL,'127.0.0.1','2026-11-15 19:00:00',NULL,NULL),(351,10,NULL,'logout',NULL,'127.0.0.1','2026-11-15 19:02:00',NULL,NULL),(352,27,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-16 07:54:49',NULL,NULL),(353,27,NULL,'logout',NULL,'127.0.0.1','2026-11-16 08:31:49',NULL,NULL),(354,17,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-16 08:35:49',NULL,NULL),(355,17,NULL,'logout',NULL,'127.0.0.1','2026-11-16 09:00:49',NULL,NULL),(356,14,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-16 09:16:49',NULL,NULL),(357,13,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-16 09:57:49',NULL,NULL),(358,14,NULL,'logout',NULL,'127.0.0.1','2026-11-16 10:17:49',NULL,NULL),(359,12,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-16 10:38:49',NULL,NULL),(360,13,NULL,'logout',NULL,'127.0.0.1','2026-11-16 11:01:49',NULL,NULL),(361,12,NULL,'logout',NULL,'127.0.0.1','2026-11-16 11:10:49',NULL,NULL),(362,11,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-16 11:19:49',NULL,NULL),(363,10,NULL,'login_fail','wrong password','127.0.0.1','2026-11-16 11:48:49',NULL,NULL),(364,11,NULL,'logout',NULL,'127.0.0.1','2026-11-16 11:51:49',NULL,NULL),(365,10,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-16 12:00:49',NULL,NULL),(366,9,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-16 12:41:49',NULL,NULL),(367,10,NULL,'logout',NULL,'127.0.0.1','2026-11-16 12:51:49',NULL,NULL),(368,6,NULL,'login_fail','wrong password','127.0.0.1','2026-11-16 13:10:49',NULL,NULL),(369,6,NULL,'login_ok','password + OTP','127.0.0.1','2026-11-16 13:22:49',NULL,NULL),(370,6,NULL,'logout',NULL,'127.0.0.1','2026-11-16 13:27:49',NULL,NULL),(371,9,NULL,'logout',NULL,'127.0.0.1','2026-11-16 13:27:49',NULL,NULL),(372,12,NULL,'logout',NULL,'::1','2026-11-16 13:46:09',NULL,NULL),(373,12,NULL,'otp_sent','login code sent via smtp','::1','2026-11-16 13:46:26',NULL,NULL),(374,12,NULL,'login_ok',NULL,'::1','2026-11-16 13:47:04',NULL,NULL),(375,12,NULL,'logout',NULL,'::1','2026-11-16 13:50:27',NULL,NULL),(376,12,NULL,'otp_sent','login code sent via smtp','::1','2026-11-16 14:15:30',NULL,NULL),(377,12,NULL,'login_ok',NULL,'::1','2026-11-16 14:15:48',NULL,NULL),(378,12,NULL,'item_add','with file','::1','2026-11-16 14:18:21',NULL,NULL),(379,12,NULL,'logout',NULL,'::1','2026-11-16 14:23:20',NULL,NULL),(380,12,NULL,'otp_sent','login code sent via smtp','::1','2026-11-16 14:27:23',NULL,NULL),(381,12,NULL,'login_ok',NULL,'127.0.0.1','2026-11-16 14:27:37',NULL,NULL),(382,12,NULL,'logout',NULL,'::1','2026-11-16 14:30:48',NULL,NULL),(383,12,NULL,'otp_sent','login code sent via smtp','::1','2026-11-18 11:10:06',NULL,NULL),(384,12,NULL,'otp_sent','login code sent via smtp','::1','2026-11-18 11:13:02',NULL,NULL),(385,12,NULL,'login_ok',NULL,'::1','2026-11-18 11:13:20',NULL,NULL),(387,12,NULL,'logout',NULL,'::1','2026-11-18 11:17:30',NULL,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36'),(388,12,NULL,'otp_sent','login code sent via smtp','::1','2026-11-23 09:53:12','admin123','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36');
/*!40000 ALTER TABLE `activity_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `security_flags`
--

DROP TABLE IF EXISTS `security_flags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `security_flags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pattern_type` varchar(100) NOT NULL,
  `target_type` varchar(50) NOT NULL,
  `target_value` varchar(150) NOT NULL,
  `details` text DEFAULT NULL,
  `detected_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) NOT NULL DEFAULT 'OPEN',
  PRIMARY KEY (`id`),
  KEY `idx_flag_pattern` (`pattern_type`),
  KEY `idx_flag_target` (`target_type`,`target_value`),
  KEY `idx_flag_detected` (`detected_at`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `security_flags`
--

LOCK TABLES `security_flags` WRITE;
/*!40000 ALTER TABLE `security_flags` DISABLE KEYS */;
INSERT INTO `security_flags` VALUES (1,'repeated_failed_login','ip','192.0.2.41','10 failed sign-ins in 10 minutes from one address','2026-11-23 07:53:02','OPEN'),(2,'ip_block_created','ip','192.0.2.41','Address blocked for 10 minutes after the failure threshold','2026-11-23 07:54:42','REVIEWED'),(3,'credential_stuffing','ip','198.51.100.83','Several usernames tried from one address','2026-11-23 04:53:02','OPEN'),(4,'otp_brute_force','user','test_user_02','Five incorrect OTP attempts before the code expired','2026-11-22 09:53:02','REVIEWED'),(5,'lockout_tier_3','user','test_user_01','Account reached the third lockout tier (60 seconds)','2026-11-22 06:06:22','REVIEWED'),(6,'repeated_failed_login','ip','203.0.113.17','8 failed sign-ins across three accounts','2026-11-21 09:53:02','OPEN');
/*!40000 ALTER TABLE `security_flags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_history`
--

DROP TABLE IF EXISTS `password_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_password_history_user` (`user_id`,`created_at`),
  CONSTRAINT `fk_password_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_history`
--

LOCK TABLES `password_history` WRITE;
/*!40000 ALTER TABLE `password_history` DISABLE KEYS */;
INSERT INTO `password_history` VALUES (7,6,'$2y$10$TuacUx63V/XDvzDQ4AC1rOA6dnX.s6jsjiO6/IdJdlFhtvLAz8YhO','2026-10-14 01:53:02'),(8,9,'$2y$10$.5FBmMt6RVCrYTjtfMeheOJOPAaKrmdvwIX1WIIIYHgJjgdBluThK','2026-10-21 01:53:02'),(9,10,'$2y$10$0rZxhHbD5U0b2Kc2dEVCUeUWhWd9VLhg2v6D2uGsTgkH9P2i1sw8C','2026-10-28 01:53:02'),(10,11,'$2y$10$4ZMzwSjZU9DfIoE3.NpDquXxA2x/zTu51qUqGHjwN5AOwuWyPREAG','2026-11-04 01:53:02'),(11,27,'$2y$10$rNKkSUhKRRPPGnyREB4zYeFIaU.d0KmQW8jdWMHa42ejPszwIJ4Ky','2026-11-11 01:53:02'),(12,6,'$2y$10$XBjCcOn82VXhZ63L90kLHuOtWi6Pr.I3Qj6HKZQfczQ.k53xtfVLe','2026-11-18 01:53:02');
/*!40000 ALTER TABLE `password_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ip_blocks`
--

DROP TABLE IF EXISTS `ip_blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ip_blocks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `failed_attempts` int(11) NOT NULL DEFAULT 0,
  `blocked_at` datetime NOT NULL DEFAULT current_timestamp(),
  `blocked_until` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_ip_blocked_until` (`blocked_until`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ip_blocks`
--

LOCK TABLES `ip_blocks` WRITE;
/*!40000 ALTER TABLE `ip_blocks` DISABLE KEYS */;
INSERT INTO `ip_blocks` VALUES (2,'192.0.2.41','Too many sign-in failures across accounts',12,'2026-11-23 09:51:03','2026-11-23 10:01:03'),(3,'198.51.100.83','Too many sign-in failures across accounts',10,'2026-11-23 09:48:03','2026-11-23 09:58:03'),(4,'203.0.113.17','Automated guessing pattern detected',14,'2026-11-23 09:52:03','2026-11-23 10:02:03'),(5,'198.51.100.24','Too many sign-in failures across accounts',11,'2026-11-23 08:53:03','2026-11-23 09:23:03'),(6,'203.0.113.88','Too many sign-in failures across accounts',10,'2026-11-23 07:53:03','2026-11-23 08:23:03'),(7,'192.0.2.190','Repeated OTP verification failures',10,'2026-11-22 09:53:03','2026-11-22 10:23:03');
/*!40000 ALTER TABLE `ip_blocks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `items`
--

DROP TABLE IF EXISTS `items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(120) NOT NULL,
  `content_enc` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `file_name` varchar(255) DEFAULT NULL,
  `file_stored` varchar(80) DEFAULT NULL,
  `file_mime` varchar(100) DEFAULT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_items_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `items`
--

LOCK TABLES `items` WRITE;
/*!40000 ALTER TABLE `items` DISABLE KEYS */;
INSERT INTO `items` VALUES (1,12,'ambot','nGVXgqA0p2gRId56YmNRVkjqOI8Z5WU8z1PW1FIwa2PzDcOoXQ6FCeIV','2026-11-01 03:09:21','Screenshot 2025-12-14 223908.png','b0eb2d8f378f11bb9686e2ffb6e9cf44.enc','image/png',30092),(4,17,'Ang code','dcaaqSfrmXkJPoOiVfdX7Zw0oVMTs4NIay4Q24ILxQAHDkyYGD1d','2026-11-01 05:31:52','Screenshot 2026-09-05 195701.png','5be723c5e7e36eb37ba5aca11ca4fdfc.enc','image/png',1187797),(5,12,'wawayp','SH6w0XQXr8sUq1MEvV2MguhGFW8d0+1ELVw2M40ch/URhOij','2026-11-16 06:18:21','Chapter1_FruitVeg.docx','7c3f87857079fbca1b25903e8d94ac30.enc','application/octet-stream',28053);
/*!40000 ALTER TABLE `items` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-23  9:54:10
