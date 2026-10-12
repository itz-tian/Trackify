-- MariaDB dump 10.19  Distrib 10.4.28-MariaDB, for osx10.10 (x86_64)
--
-- Host: localhost    Database: dms_db
-- ------------------------------------------------------
-- Server version	10.4.28-MariaDB

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
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `document_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `document_id` (`document_id`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `activity_logs_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=104 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,1,NULL,'login','User logged in','::1','2026-10-10 14:24:24'),(2,1,1,'upload','Uploaded document: ppt header','::1','2026-10-10 14:42:48'),(3,1,1,'view','Viewed document: ppt header','::1','2026-10-10 14:42:48'),(4,1,1,'view','Viewed document: ppt header','::1','2026-10-10 14:42:48'),(5,1,2,'upload','Uploaded document: SmartMart Case #2 (Revised) (1) pptx','::1','2026-10-10 15:24:11'),(6,1,2,'view','Viewed document: SmartMart Case #2 (Revised) (1) pptx','::1','2026-10-10 15:24:11'),(7,1,2,'view','Viewed document: SmartMart Case #2 (Revised) (1) pptx','::1','2026-10-10 15:25:04'),(8,1,2,'view','Viewed document: SmartMart Case #2 (Revised) (1) pptx','::1','2026-10-10 15:28:03'),(9,1,NULL,'logout','User logged out','::1','2026-10-10 15:30:14'),(10,5,NULL,'login','User logged in','::1','2026-10-10 15:30:20'),(11,5,3,'upload','Uploaded document: ppt last page','::1','2026-10-10 15:31:04'),(12,5,3,'view','Viewed document: ppt last page','::1','2026-10-10 15:31:04'),(13,5,3,'view','Viewed document: ppt last page','::1','2026-10-10 15:31:04'),(14,5,3,'view','Viewed document: ppt last page','::1','2026-10-10 15:31:33'),(15,5,3,'view','Viewed document: ppt last page','::1','2026-10-10 15:31:33'),(16,5,3,'view','Viewed document: ppt last page','::1','2026-10-10 15:51:57'),(17,5,3,'view','Viewed document: ppt last page','::1','2026-10-10 15:51:57'),(18,5,3,'view','Viewed document: ppt last page','::1','2026-10-10 15:59:47'),(19,5,3,'view','Viewed document: ppt last page','::1','2026-10-10 15:59:47'),(20,5,NULL,'logout','User logged out','::1','2026-10-10 16:00:44'),(21,8,NULL,'login','User logged in','::1','2026-10-10 16:00:52'),(22,8,NULL,'logout','User logged out','::1','2026-10-10 16:03:35'),(23,1,NULL,'login','User logged in','::1','2026-10-10 16:03:42'),(24,1,NULL,'logout','User logged out','::1','2026-10-10 16:04:01'),(25,8,NULL,'login','User logged in','::1','2026-10-10 16:04:07'),(26,8,NULL,'logout','User logged out','unknown','2026-10-10 16:17:28'),(27,8,NULL,'logout','User logged out','::1','2026-10-10 17:11:32'),(28,1,NULL,'login','User logged in','::1','2026-10-10 17:11:41'),(29,1,NULL,'logout','User logged out','::1','2026-10-10 17:12:28'),(30,5,NULL,'login','User logged in','::1','2026-10-10 17:12:36'),(31,5,NULL,'logout','User logged out','::1','2026-10-10 17:12:47'),(32,8,NULL,'login','User logged in','::1','2026-10-10 17:12:53'),(33,8,4,'upload','Uploaded document: diagram','::1','2026-10-10 17:29:00'),(34,8,4,'view','Viewed document: diagram','::1','2026-10-10 17:29:00'),(35,8,4,'view','Viewed document: diagram','::1','2026-10-10 17:29:00'),(36,8,NULL,'logout','User logged out','::1','2026-10-10 17:29:21'),(37,5,NULL,'login','User logged in','::1','2026-10-10 17:29:26'),(38,5,NULL,'logout','User logged out','::1','2026-10-10 17:29:34'),(39,1,NULL,'login','User logged in','::1','2026-10-10 17:29:41'),(40,1,NULL,'logout','User logged out','::1','2026-10-10 17:29:50'),(41,4,NULL,'login','User logged in','::1','2026-10-10 17:29:55'),(42,4,5,'upload','Uploaded document: SmartMart Case #2 (Revised) (1) pptx','::1','2026-10-10 17:31:58'),(43,4,5,'view','Viewed document: SmartMart Case #2 (Revised) (1) pptx','::1','2026-10-10 17:31:58'),(44,4,NULL,'logout','User logged out','::1','2026-10-10 17:32:21'),(45,3,NULL,'login','User logged in','::1','2026-10-10 17:33:06'),(46,3,6,'upload','Uploaded document: analytics','::1','2026-10-10 17:34:33'),(47,3,6,'view','Viewed document: analytics','::1','2026-10-10 17:34:33'),(48,3,6,'view','Viewed document: analytics','::1','2026-10-10 17:34:33'),(49,3,6,'view','Viewed document: analytics','::1','2026-10-10 17:34:41'),(50,3,6,'view','Viewed document: analytics','::1','2026-10-10 17:34:41'),(51,3,NULL,'logout','User logged out','::1','2026-10-10 17:35:21'),(52,6,NULL,'login','User logged in','::1','2026-10-10 17:35:29'),(53,6,NULL,'logout','User logged out','::1','2026-10-10 17:35:41'),(54,7,NULL,'login','User logged in','::1','2026-10-10 17:35:56'),(55,7,7,'upload','Uploaded document: CCS 111 PRELIM ACTIVITY 3 ','::1','2026-10-10 17:37:15'),(56,7,7,'view','Viewed document: CCS 111 PRELIM ACTIVITY 3 ','::1','2026-10-10 17:37:15'),(57,7,NULL,'logout','User logged out','::1','2026-10-10 17:37:35'),(58,9,NULL,'login','User logged in','::1','2026-10-10 17:38:02'),(59,9,8,'upload','Uploaded document: CityCenter Project Documentation','::1','2026-10-10 17:39:48'),(60,9,8,'view','Viewed document: CityCenter Project Documentation','::1','2026-10-10 17:39:48'),(61,9,NULL,'logout','User logged out','::1','2026-10-10 17:40:51'),(62,1,NULL,'login','User logged in','::1','2026-10-10 17:41:07'),(63,1,4,'status_change','Status changed to: Approved','::1','2026-10-10 17:41:38'),(64,1,6,'status_change','Status changed to: Rejected (Remarks: Rejected by approver.)','::1','2026-10-10 17:41:41'),(65,1,8,'status_change','Status changed to: Approved','::1','2026-10-10 17:41:43'),(66,1,5,'status_change','Status changed to: Rejected (Remarks: Rejected by approver.)','::1','2026-10-10 17:41:45'),(67,1,7,'status_change','Status changed to: Rejected (Remarks: Rejected by approver.)','::1','2026-10-10 17:41:46'),(68,1,3,'status_change','Status changed to: Approved','::1','2026-10-10 17:41:47'),(69,1,5,'view','Viewed document: SmartMart Case #2 (Revised) (1) pptx','::1','2026-10-10 17:42:09'),(70,1,5,'view','Viewed document: SmartMart Case #2 (Revised) (1) pptx','::1','2026-10-10 17:42:23'),(71,1,5,'status_change','Status changed to: Pending review','::1','2026-10-10 17:42:23'),(72,1,5,'view','Viewed document: SmartMart Case #2 (Revised) (1) pptx','::1','2026-10-10 17:42:23'),(73,1,7,'view','Viewed document: CCS 111 PRELIM ACTIVITY 3 ','::1','2026-10-10 17:42:39'),(74,1,7,'view','Viewed document: CCS 111 PRELIM ACTIVITY 3 ','::1','2026-10-10 17:42:44'),(75,1,7,'status_change','Status changed to: Draft','::1','2026-10-10 17:42:44'),(76,1,7,'view','Viewed document: CCS 111 PRELIM ACTIVITY 3 ','::1','2026-10-10 17:42:44'),(77,1,NULL,'logout','User logged out','unknown','2026-10-10 17:44:06'),(78,1,NULL,'logout','User logged out','::1','2026-10-10 17:57:04'),(79,1,NULL,'login','User logged in','::1','2026-10-10 17:57:11'),(80,1,NULL,'login','User logged in','::1','2026-10-10 18:00:21'),(81,1,NULL,'login','User logged in','::1','2026-10-10 18:00:36'),(82,1,NULL,'logout','User logged out','::1','2026-10-10 19:06:44'),(83,1,NULL,'login','User logged in','::1','2026-10-10 19:07:33'),(84,1,NULL,'logout','User logged out','::1','2026-10-10 20:07:58'),(85,1,NULL,'logout','User logged out','unknown','2026-10-10 22:35:33'),(86,1,5,'view','Viewed document: SmartMart Case #2 (Revised) (1) pptx','unknown','2026-10-10 22:35:38'),(87,1,5,'view','Viewed document: SmartMart Case #2 (Revised) (1) pptx','unknown','2026-10-10 22:36:55'),(88,1,5,'view','Viewed document: SmartMart Case #2 (Revised) (1) pptx','unknown','2026-10-10 22:37:00'),(89,1,9,'view','Viewed document: CCS109 Assessment Task #2 Presentation','unknown','2026-10-12 02:54:17'),(90,1,NULL,'login','User logged in','::1','2026-10-12 02:57:32'),(91,1,9,'view','Viewed document: CCS109 Assessment Task #2 Presentation','::1','2026-10-12 02:57:49'),(92,1,9,'view','Viewed document: CCS109 Assessment Task #2 Presentation','::1','2026-10-12 02:58:44'),(93,1,9,'view','Viewed document: CCS109 Assessment Task #2 Presentation','::1','2026-10-12 02:58:44'),(94,1,9,'view','Viewed document: CCS109 Assessment Task #2 Presentation','::1','2026-10-12 02:59:25'),(95,1,9,'view','Viewed document: CCS109 Assessment Task #2 Presentation','::1','2026-10-12 02:59:25'),(96,1,9,'view','Viewed document: CCS109 Assessment Task #2 Presentation','::1','2026-10-12 03:00:47'),(97,1,9,'view','Viewed document: CCS109 Assessment Task #2 Presentation','::1','2026-10-12 03:00:47'),(98,1,9,'view','Viewed document: CCS109 Assessment Task #2 Presentation','::1','2026-10-12 03:01:04'),(99,1,9,'view','Viewed document: CCS109 Assessment Task #2 Presentation','::1','2026-10-12 03:01:04'),(100,1,9,'view','Viewed document: CCS109 Assessment Task #2 Presentation','::1','2026-10-12 03:01:16'),(101,1,9,'view','Viewed document: CCS109 Assessment Task #2 Presentation','::1','2026-10-12 03:01:16'),(102,1,9,'view','Viewed document: CCS109 Assessment Task #2 Presentation','::1','2026-10-12 03:01:38'),(103,1,9,'view','Viewed document: CCS109 Assessment Task #2 Presentation','::1','2026-10-12 03:01:38');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `approval_workflows`
--

DROP TABLE IF EXISTS `approval_workflows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `approval_workflows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `approver_id` int(11) NOT NULL,
  `step_order` int(11) NOT NULL DEFAULT 1,
  `status` enum('pending','approved','rejected','skipped') DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `acted_at` timestamp NULL DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`),
  KEY `approver_id` (`approver_id`),
  CONSTRAINT `approval_workflows_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `approval_workflows_ibfk_2` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `approval_workflows`
--

LOCK TABLES `approval_workflows` WRITE;
/*!40000 ALTER TABLE `approval_workflows` DISABLE KEYS */;
INSERT INTO `approval_workflows` VALUES (1,3,1,1,'approved','','2026-10-10 17:41:47',NULL,'2026-10-10 15:31:04'),(2,3,2,1,'skipped','Approval concluded (Approved by System Admin)','2026-10-10 17:41:47',NULL,'2026-10-10 15:31:04'),(3,4,1,1,'approved','','2026-10-10 17:41:38',NULL,'2026-10-10 17:29:00'),(4,4,2,1,'skipped','Approval concluded (Approved by System Admin)','2026-10-10 17:41:38',NULL,'2026-10-10 17:29:00'),(5,4,5,1,'skipped','Approval concluded (Approved by System Admin)','2026-10-10 17:41:38',NULL,'2026-10-10 17:29:00'),(6,5,1,1,'rejected','Rejected by approver.','2026-10-10 17:41:45',NULL,'2026-10-10 17:31:58'),(7,5,2,1,'skipped','Workflow concluded (Rejected by System Admin)','2026-10-10 17:41:45',NULL,'2026-10-10 17:31:58'),(8,6,1,1,'rejected','Rejected by approver.','2026-10-10 17:41:41',NULL,'2026-10-10 17:34:33'),(9,6,2,1,'skipped','Workflow concluded (Rejected by System Admin)','2026-10-10 17:41:41',NULL,'2026-10-10 17:34:33'),(10,7,1,1,'rejected','Rejected by approver.','2026-10-10 17:41:46',NULL,'2026-10-10 17:37:15'),(11,7,2,1,'skipped','Workflow concluded (Rejected by System Admin)','2026-10-10 17:41:46',NULL,'2026-10-10 17:37:15'),(12,7,4,1,'skipped','Workflow concluded (Rejected by System Admin)','2026-10-10 17:41:46',NULL,'2026-10-10 17:37:15'),(13,8,1,1,'approved','','2026-10-10 17:41:43',NULL,'2026-10-10 17:39:48'),(14,8,2,1,'skipped','Approval concluded (Approved by System Admin)','2026-10-10 17:41:43',NULL,'2026-10-10 17:39:48');
/*!40000 ALTER TABLE `approval_workflows` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `calendar_tasks`
--

DROP TABLE IF EXISTS `calendar_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `calendar_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` date NOT NULL,
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `visibility` enum('private','department') NOT NULL DEFAULT 'private',
  `department_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ct_due` (`due_date`),
  KEY `idx_ct_user` (`created_by`),
  KEY `idx_ct_dept` (`department_id`),
  CONSTRAINT `calendar_tasks_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `calendar_tasks_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calendar_tasks`
--

LOCK TABLES `calendar_tasks` WRITE;
/*!40000 ALTER TABLE `calendar_tasks` DISABLE KEYS */;
/*!40000 ALTER TABLE `calendar_tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#3b82f6',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_category_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Memo','Internal memos and circulars','#3b82f6','2026-10-10 14:23:28'),(2,'Letter','Formal letters','#10b981','2026-10-10 14:23:28'),(3,'Report','Reports and summaries','#f59e0b','2026-10-10 14:23:28'),(4,'Contract','Legal contracts and agreements','#ef4444','2026-10-10 14:23:28'),(5,'Form','Official forms and requests','#8b5cf6','2026-10-10 14:23:28'),(6,'Policy','Company policies and guidelines','#06b6d4','2026-10-10 14:23:28'),(7,'Invoice','Billing and invoices','#f97316','2026-10-10 14:23:28'),(8,'Others','Miscellaneous documents','#6b7280','2026-10-10 14:23:28');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comments`
--

DROP TABLE IF EXISTS `comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comments`
--

LOCK TABLES `comments` WRITE;
/*!40000 ALTER TABLE `comments` DISABLE KEYS */;
INSERT INTO `comments` VALUES (1,6,1,'[Status Changed to Rejected] Rejected by approver.','2026-10-10 17:41:41','2026-10-10 17:41:41'),(2,5,1,'[Status Changed to Rejected] Rejected by approver.','2026-10-10 17:41:45','2026-10-10 17:41:45'),(3,7,1,'[Status Changed to Rejected] Rejected by approver.','2026-10-10 17:41:46','2026-10-10 17:41:46');
/*!40000 ALTER TABLE `comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_department_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES (1,'Administration','General office administration','2026-10-10 14:23:28'),(2,'Human Resources','HR and personnel management','2026-10-10 14:23:28'),(3,'Finance','Accounting and finance','2026-10-10 14:23:28'),(4,'IT Department','Information technology','2026-10-10 14:23:28'),(5,'Operations','Day-to-day operations','2026-10-10 14:23:28');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_versions`
--

DROP TABLE IF EXISTS `document_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_versions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `version` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `document_versions_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `document_versions_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_versions`
--

LOCK TABLES `document_versions` WRITE;
/*!40000 ALTER TABLE `document_versions` DISABLE KEYS */;
INSERT INTO `document_versions` VALUES (1,1,1,'1.jpg','uploads/2026/10/bcf385269f57096268e4c5ce94bea4aa.jpg',97569,'Initial upload',1,'2026-10-10 14:42:48'),(2,2,1,'SmartMart Case #2 (Revised) (1)_pptx.zip','uploads/2026/10/ce236f8fba9c9ca046909446e69b5209.zip',1208211,'Initial upload',1,'2026-10-10 15:24:11'),(3,3,1,'17.jpg','uploads/2026/10/7702a22fd736cdd4c8ab5762289e4c82.jpg',103876,'Initial upload',5,'2026-10-10 15:31:04'),(4,4,1,'9.jpg','uploads/2026/10/4e9a85e44f6cc3be8251132b9ad087fe.jpg',141164,'Initial upload',8,'2026-10-10 17:29:00'),(5,5,1,'SmartMart Case #2 (Revised) (1)_pptx.zip','uploads/2026/10/00bbed1e89455f2097db2e316d3d70c8.zip',1208211,'Initial upload',4,'2026-10-10 17:31:58'),(6,6,1,'13.jpg','uploads/2026/10/e2ae9f54841010589931b865066e4dc8.jpg',93854,'Initial upload',3,'2026-10-10 17:34:33'),(7,7,1,'CCS 113 PRELIM ACTIVITY 3 .docx','uploads/2026/10/e58583c1f86f2a6b306a33659d208359.docx',102578,'Initial upload',7,'2026-10-10 17:37:15'),(8,8,1,'CityCenter-Project-Documentation (1).docx','uploads/2026/10/03fa2d1bf817cbd54b79a8f126c17d12.docx',338960,'Initial upload',9,'2026-10-10 17:39:48');
/*!40000 ALTER TABLE `document_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `tags` varchar(500) DEFAULT NULL,
  `status` enum('draft','pending_review','under_review','approved','rejected','archived') DEFAULT 'draft',
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `version` int(11) DEFAULT 1,
  `parent_document_id` int(11) DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_number` (`reference_number`),
  KEY `category_id` (`category_id`),
  KEY `department_id` (`department_id`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `parent_document_id` (`parent_document_id`),
  KEY `deleted_by` (`deleted_by`),
  KEY `idx_documents_deleted_at` (`deleted_at`),
  KEY `idx_documents_expires` (`expires_at`),
  CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `documents_ibfk_3` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `documents_ibfk_4` FOREIGN KEY (`parent_document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `documents_ibfk_5` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
INSERT INTO `documents` VALUES (1,'ppt header','',5,4,1,'1.jpg','uploads/2026/10/bcf385269f57096268e4c5ce94bea4aa.jpg',97569,'JPG','ppt, header','approved','normal',1,NULL,'2026-10-31','DOC-2026-000001',NULL,NULL,'2026-10-10 14:42:48','2026-10-10 14:42:48'),(2,'SmartMart Case #2 (Revised) (1) pptx','',5,2,1,'SmartMart Case #2 (Revised) (1)_pptx.zip','uploads/2026/10/ce236f8fba9c9ca046909446e69b5209.zip',1208211,'ZIP','ppt','approved','normal',1,NULL,'2026-10-31','DOC-2026-000002',NULL,NULL,'2026-10-10 15:24:11','2026-10-10 15:24:11'),(3,'ppt last page','',4,4,5,'17.jpg','uploads/2026/10/7702a22fd736cdd4c8ab5762289e4c82.jpg',103876,'JPG','ppt','approved','normal',1,NULL,'2026-11-07','DOC-2026-000003',NULL,NULL,'2026-10-10 15:31:04','2026-10-10 17:41:47'),(4,'diagram','',5,4,8,'9.jpg','uploads/2026/10/4e9a85e44f6cc3be8251132b9ad087fe.jpg',141164,'JPG','ppt','approved','urgent',1,NULL,'2026-10-12','DOC-2026-000004',NULL,NULL,'2026-10-10 17:29:00','2026-10-10 17:41:38'),(5,'SmartMart Case #2 (Revised) (1) pptx','',8,3,4,'SmartMart Case #2 (Revised) (1)_pptx.zip','uploads/2026/10/00bbed1e89455f2097db2e316d3d70c8.zip',1208211,'ZIP','ppt','rejected','high',1,NULL,'2026-10-12','DOC-2026-000005',NULL,NULL,'2026-10-10 17:31:58','2026-10-11 18:29:59'),(6,'analytics','',7,2,3,'13.jpg','uploads/2026/10/e2ae9f54841010589931b865066e4dc8.jpg',93854,'JPG','analytics, ppt','rejected','low',1,NULL,'2026-10-30','DOC-2026-000006',NULL,NULL,'2026-10-10 17:34:33','2026-10-10 17:41:41'),(7,'CCS 111 PRELIM ACTIVITY 3 ','',5,3,7,'CCS 113 PRELIM ACTIVITY 3 .docx','uploads/2026/10/e58583c1f86f2a6b306a33659d208359.docx',102578,'DOCX','CCS111, prelim, activity 3','draft','normal',1,NULL,'2026-10-31','DOC-2026-000007',NULL,NULL,'2026-10-10 17:37:15','2026-10-10 17:42:44'),(8,'CityCenter Project Documentation','',4,5,9,'CityCenter-Project-Documentation (1).docx','uploads/2026/10/03fa2d1bf817cbd54b79a8f126c17d12.docx',338960,'DOCX','project, CityCenter, documentation','approved','normal',1,NULL,'2026-10-29','DOC-2026-000008',NULL,NULL,'2026-10-10 17:39:48','2026-10-10 17:41:43'),(9,'CCS109 Assessment Task #2 Presentation','Printing Shop Management and Online Printing Reservation System Presentation',1,1,1,'CCS109 Assessment Task 2.pptx','uploads/2026/10/04194939daabb19bf48bcb5f1ff8ae37.pptx',438640,'PPTX','presentation, slides, ccs109','approved','normal',1,NULL,'2026-12-31','DOC-2026-PPTX01',NULL,NULL,'2026-10-12 02:53:56','2026-10-12 02:53:56');
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_attempted_at` (`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `document_id` int(11) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `document_id` (`document_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,1,3,'review_request','Document Pending Review','Document \'ppt last page\' requires your review.',1,'2026-10-10 15:31:04'),(2,2,3,'review_request','Document Pending Review','Document \'ppt last page\' requires your review.',1,'2026-10-10 15:31:04'),(3,1,4,'review_request','Document Pending Review','Document \'diagram\' requires your review.',1,'2026-10-10 17:29:00'),(4,2,4,'review_request','Document Pending Review','Document \'diagram\' requires your review.',1,'2026-10-10 17:29:00'),(5,5,4,'review_request','Document Pending Review','Document \'diagram\' requires your review.',1,'2026-10-10 17:29:00'),(6,1,5,'review_request','Document Pending Review','Document \'SmartMart Case #2 (Revised) (1) pptx\' requires your review.',1,'2026-10-10 17:31:58'),(7,2,5,'review_request','Document Pending Review','Document \'SmartMart Case #2 (Revised) (1) pptx\' requires your review.',1,'2026-10-10 17:31:58'),(8,1,6,'review_request','Document Pending Review','Document \'analytics\' requires your review.',1,'2026-10-10 17:34:33'),(9,2,6,'review_request','Document Pending Review','Document \'analytics\' requires your review.',1,'2026-10-10 17:34:33'),(10,1,7,'review_request','Document Pending Review','Document \'CCS 111 PRELIM ACTIVITY 3 \' requires your review.',1,'2026-10-10 17:37:15'),(11,2,7,'review_request','Document Pending Review','Document \'CCS 111 PRELIM ACTIVITY 3 \' requires your review.',1,'2026-10-10 17:37:15'),(12,4,7,'review_request','Document Pending Review','Document \'CCS 111 PRELIM ACTIVITY 3 \' requires your review.',1,'2026-10-10 17:37:15'),(13,1,8,'review_request','Document Pending Review','Document \'CityCenter Project Documentation\' requires your review.',1,'2026-10-10 17:39:48'),(14,2,8,'review_request','Document Pending Review','Document \'CityCenter Project Documentation\' requires your review.',1,'2026-10-10 17:39:48'),(15,8,4,'status_update','Document Status Update','Your document \'diagram\' has been approved.',0,'2026-10-10 17:41:38'),(16,3,6,'status_update','Document Status Update','Your document \'analytics\' was rejected. Reason: Rejected by approver.',0,'2026-10-10 17:41:41'),(17,9,8,'status_update','Document Status Update','Your document \'CityCenter Project Documentation\' has been approved.',0,'2026-10-10 17:41:43'),(18,4,5,'status_update','Document Status Update','Your document \'SmartMart Case #2 (Revised) (1) pptx\' was rejected. Reason: Rejected by approver.',0,'2026-10-10 17:41:45'),(19,7,7,'status_update','Document Status Update','Your document \'CCS 111 PRELIM ACTIVITY 3 \' was rejected. Reason: Rejected by approver.',0,'2026-10-10 17:41:46'),(20,5,3,'status_update','Document Status Update','Your document \'ppt last page\' has been approved.',0,'2026-10-10 17:41:47'),(21,4,5,'status_update','Document Status Update','Your document \'SmartMart Case #2 (Revised) (1) pptx\' status changed to: pending_review.',0,'2026-10-10 17:42:23'),(22,7,7,'status_update','Document Status Update','Your document \'CCS 111 PRELIM ACTIVITY 3 \' status changed to: draft.',0,'2026-10-10 17:42:44');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','department_head','staff','viewer') DEFAULT 'staff',
  `avatar` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,1,'System','Admin','admin@dms.local','$2y$12$tGUKojk.yq47y52E5fx1ReOuE1YLlPoq5HReeTWh1iOrRZn//yEXK','admin',NULL,1,'2026-10-10 14:23:28','2026-10-12 02:57:25'),(2,1,'Anna','Santos','admin2@dms.local','$2y$12$60lrl26YCj4Q84C64VmLkuqPJ1UCfBiGquIxGQ.UaDUj3/GPwMKJO','admin',NULL,1,'2026-10-10 14:23:28','2026-10-10 19:01:47'),(3,2,'Mark','Reyes','hr.head@dms.local','$2y$12$Fkdnk62MFTR.anwPESICUuWsTMyn7.VKJQsbqIhAPufV0R1Fiy2MC','department_head',NULL,1,'2026-10-10 14:23:28','2026-10-10 19:01:47'),(4,3,'Liza','Cruz','finance.head@dms.local','$2y$12$mCG9X39sHvOebSs4z9EJHemGK86VeiL9cD49OgB1SHPMnmpzs6Gam','department_head',NULL,1,'2026-10-10 14:23:28','2026-10-10 19:01:47'),(5,4,'Carlo','Mendoza','it.head@dms.local','$2y$12$L38l/N3pcUSMDs.ysAkr/eLL0C214ivGB59QTHdgWM.GFSXwReaom','department_head',NULL,1,'2026-10-10 14:23:28','2026-10-10 19:01:47'),(6,2,'Maria','Bautista','staff1@dms.local','$2y$12$178mxKqEUEBsVNOp5O0PIOarfyy5lijOaOOFGW./5kjZq6Ne9AXAa','staff',NULL,1,'2026-10-10 14:23:28','2026-10-10 19:01:47'),(7,3,'Jose','Garcia','staff2@dms.local','$2y$12$DMDZKNX9OoT6S9/H1ghbt.1N9BNFPkV2Nk5JVsOatI.a3eEl4.aIq','staff',NULL,1,'2026-10-10 14:23:28','2026-10-10 19:01:47'),(8,4,'Rosa','Dela Cruz','staff3@dms.local','$2y$12$Vdhcznh3MVLhYNpNhksO3edLQJywE9l4ctfzNjkt5Hf5/jeSy3L2i','staff',NULL,1,'2026-10-10 14:23:28','2026-10-10 19:01:47'),(9,5,'Pedro','Ramos','staff4@dms.local','$2y$12$I6I9r0BNTwlHMKHDUqD/HOnWZfVfTF6cFu7zQNFVBV4qMmXT32AYW','staff',NULL,1,'2026-10-10 14:23:28','2026-10-10 19:01:47'),(23,5,'Roberto','Morales','operations.head@dms.local','$2y$12$JpIiZJUOKHFciP6L.Fe0Le6N0UsUPlR/R1/d6sFyV2GMVWfrLSn.u','department_head',NULL,1,'2026-10-10 17:41:15','2026-10-10 19:01:47');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-12 11:02:30
