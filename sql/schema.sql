-- Schéma initial pour l'application de planning / saisie de garde
-- MySQL / MariaDB (UTF8MB4)

CREATE DATABASE IF NOT EXISTS garde_app CHARACTER SET = 'utf8mb4' COLLATE = 'utf8mb4_unicode_ci';
USE garde_app;

CREATE TABLE masseurskines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  prenom VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  telephone VARCHAR(50),
  numero_rue VARCHAR(50),
  adresse TEXT,
  code_postal VARCHAR(20),
  ville VARCHAR(100),
  rib_provided TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE gardes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  masseur_id BIGINT UNSIGNED NOT NULL,
  garde_date DATE NOT NULL,
  status ENUM('planned','saisie') DEFAULT 'planned',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (masseur_id) REFERENCES masseurskines(id) ON DELETE CASCADE,
  UNIQUE KEY uq_masseur_date (masseur_id, garde_date)
);

CREATE TABLE garde_saisies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  masseur_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  validated_at DATETIME DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  FOREIGN KEY (masseur_id) REFERENCES masseurskines(id) ON DELETE CASCADE
);

CREATE TABLE garde_saisie_dates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  garde_saisie_id BIGINT UNSIGNED NOT NULL,
  garde_id BIGINT UNSIGNED NOT NULL,
  garde_date DATE NOT NULL,
  FOREIGN KEY (garde_saisie_id) REFERENCES garde_saisies(id) ON DELETE CASCADE,
  FOREIGN KEY (garde_id) REFERENCES gardes(id) ON DELETE CASCADE
);

CREATE TABLE garde_patients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  garde_saisie_date_id BIGINT UNSIGNED NOT NULL,
  age_months INT DEFAULT NULL,
  age_years INT DEFAULT NULL,
  commune VARCHAR(150),
  provenance ENUM('medecin_generaliste','pediatre','service_urgence','centre_soins','samu','autre') DEFAULT 'autre',
  provenance_autre VARCHAR(255) DEFAULT NULL,
  orientation ENUM('retour_domicile','consultation_medicale','service_urgence','autre') DEFAULT 'retour_domicile',
  orientation_autre VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (garde_saisie_date_id) REFERENCES garde_saisie_dates(id) ON DELETE CASCADE
);

CREATE TABLE garde_saisie_answers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  garde_saisie_id BIGINT UNSIGNED NOT NULL,
  question_key VARCHAR(100) NOT NULL,
  answer TINYINT(1) NOT NULL,
  extra_text TEXT DEFAULT NULL,
  extra_number INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (garde_saisie_id) REFERENCES garde_saisies(id) ON DELETE CASCADE
);

CREATE INDEX idx_garde_date ON gardes (garde_date);
CREATE INDEX idx_saisie_masseur ON garde_saisies (masseur_id);
