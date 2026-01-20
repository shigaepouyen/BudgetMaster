-- Fichier: database.sql
-- Description: Structure complète pour BudgetMaster CA (v1.0)
-- Inclus: Multi-comptes, Règles avancées, Récurrences, Budgets

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- 1. TABLE : UTILISATEURS
-- Gère les membres du foyer ou les entités comptables
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `color` varchar(7) DEFAULT '#CCCCCC',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Données initiales (Seed)
INSERT IGNORE INTO `users` (`id`, `name`, `color`) VALUES
(1, 'Moi', '#3298dc'),
(2, 'Compte-Joint', '#00d1b2');

-- --------------------------------------------------------
-- 2. TABLE : COMPTES BANCAIRES
-- Stocke les numéros de comptes extraits des fichiers OFX
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_number` varchar(30) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('PERSONAL','JOINT') NOT NULL DEFAULT 'PERSONAL',
  `owner_id` int(11) DEFAULT NULL,
  `current_balance` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_number` (`account_number`),
  KEY `owner_id` (`owner_id`),
  CONSTRAINT `accounts_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. TABLE : CATÉGORIES
-- Typologie des dépenses et revenus
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `type` enum('EXPENSE','INCOME','TRANSFER') NOT NULL DEFAULT 'EXPENSE',
  `parent_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Données initiales (Seed)
INSERT IGNORE INTO `categories` (`id`, `name`, `type`) VALUES
(1, 'Alimentation', 'EXPENSE'),
(2, 'Logement', 'EXPENSE'),
(3, 'Transport', 'EXPENSE'),
(4, 'Loisirs', 'EXPENSE'),
(5, 'Santé', 'EXPENSE'),
(6, 'Salaire', 'INCOME'),
(7, 'Virements Internes', 'TRANSFER'),
(8, 'Inconnu', 'EXPENSE'),
(9, 'Épargne', 'EXPENSE'),
(10, 'Abonnements', 'EXPENSE');

-- --------------------------------------------------------
-- 4. TABLE : RÈGLES DE CATÉGORISATION (Avancées)
-- Le cerveau de l'automatisation
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `category_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pattern` varchar(100) NOT NULL,          -- Mot clé déclencheur
  `exclusion` varchar(100) DEFAULT NULL,    -- Sauf si contient ceci
  `amount_match` decimal(10,2) DEFAULT NULL, -- Et montant exact
  `custom_label` varchar(100) DEFAULT NULL, -- Renommer en (Alias)
  `category_id` int(11) NOT NULL,           -- Catégorie à appliquer
  `is_recurring` tinyint(1) NOT NULL DEFAULT 0, -- Est-ce un abonnement ?
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `category_rules_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. TABLE : TRANSACTIONS
-- Données brutes importées de la banque
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `fitid` varchar(255) DEFAULT NULL, -- ID Unique OFX
  `date_booked` date NOT NULL,
  `raw_label` text NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `fitid` (`fitid`),
  KEY `account_id` (`account_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. TABLE : RÉPARTITION (SPLITS)
-- Données enrichies (Qui, Quoi, Alias)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transaction_splits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `comment` varchar(100) DEFAULT NULL, -- Alias / Libellé Perso
  PRIMARY KEY (`id`),
  KEY `transaction_id` (`transaction_id`),
  KEY `user_id` (`user_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `transaction_splits_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transaction_splits_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `transaction_splits_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 7. TABLE : RÉCURRENCES (Suivi)
-- Stocke l'état des abonnements détectés ou définis
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `recurrences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `signature` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `frequency` enum('Mensuel','Annuel','Trimestriel') NOT NULL,
  `status` enum('ACTIVE','IGNORED') NOT NULL DEFAULT 'ACTIVE',
  `category_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_rec` (`signature`,`amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 8. TABLE : BUDGETS
-- Objectifs mensuels par catégorie et par utilisateur
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `budgets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_cat` (`user_id`,`category_id`),
  KEY `category_id` (`category_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `budgets_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `budgets_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;