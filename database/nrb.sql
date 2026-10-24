-- ============================================================
-- NRB National ID Registration System - Physical database
-- Target: MySQL 8.0.16+ / MariaDB 10.5+  (InnoDB, utf8mb4)
-- Import: mysql -u root -p < database/nrb.sql
-- ============================================================
DROP DATABASE IF EXISTS nrb_db;
CREATE DATABASE nrb_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nrb_db;

-- ---------- Task 1 (a, b): tables and constraints ----------

CREATE TABLE registration_center (
  center_no    INT AUTO_INCREMENT PRIMARY KEY,
  center_name  VARCHAR(80) NOT NULL,
  district     VARCHAR(50) NOT NULL,
  CONSTRAINT uq_center_name UNIQUE (center_name)
) ENGINE=InnoDB;

CREATE TABLE person (
  person_id      INT AUTO_INCREMENT PRIMARY KEY,
  first_name     VARCHAR(50) NOT NULL,
  other_names    VARCHAR(50) NULL,
  surname        VARCHAR(50) NOT NULL,
  date_of_birth  DATE NOT NULL,
  sex            VARCHAR(8) NOT NULL,
  address_no     VARCHAR(20) NOT NULL,
  village        VARCHAR(50) NOT NULL,
  district       VARCHAR(50) NOT NULL,
  CONSTRAINT chk_person_sex CHECK (sex IN ('Male','Female'))
) ENGINE=InnoDB;

-- Subtype: CITIZEN (PK is also FK to PERSON)
CREATE TABLE citizen (
  person_id          INT PRIMARY KEY,
  place_of_birth     VARCHAR(50) NOT NULL,
  nationality        VARCHAR(50) NOT NULL DEFAULT 'Malawian',
  citizenship_status VARCHAR(20) NOT NULL,
  citizen_id         VARCHAR(30) NULL,
  CONSTRAINT uq_citizen_citizen_id UNIQUE (citizen_id),
  CONSTRAINT fk_citizen_person FOREIGN KEY (person_id)
    REFERENCES person(person_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_citizenship_status CHECK
    (citizenship_status IN ('By Birth','By Descent','Naturalised','Registered'))
) ENGINE=InnoDB;

-- Subtype: RESIDENT_FOREIGNER
CREATE TABLE resident_foreigner (
  person_id INT PRIMARY KEY,
  CONSTRAINT fk_foreigner_person FOREIGN KEY (person_id)
    REFERENCES person(person_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE passport (
  passport_no  VARCHAR(30) PRIMARY KEY,
  issue_date   DATE NOT NULL,
  expiry_date  DATE NOT NULL,
  country      VARCHAR(50) NOT NULL,
  person_id    INT NOT NULL,
  CONSTRAINT fk_passport_foreigner FOREIGN KEY (person_id)
    REFERENCES resident_foreigner(person_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_passport_dates CHECK (expiry_date > issue_date)
) ENGINE=InnoDB;

CREATE TABLE officer (
  officer_id  INT AUTO_INCREMENT PRIMARY KEY,
  first_name  VARCHAR(50) NOT NULL,
  last_name   VARCHAR(50) NOT NULL,
  position    VARCHAR(30) NOT NULL,
  center_no   INT NOT NULL,
  CONSTRAINT fk_officer_center FOREIGN KEY (center_no)
    REFERENCES registration_center(center_no) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- "REGISTRATION OFFICE" entity of the logical model
CREATE TABLE registration_office (
  registration_id    INT AUTO_INCREMENT PRIMARY KEY,
  registration_date  DATE NOT NULL,
  branch             VARCHAR(50) NOT NULL,
  status             VARCHAR(20) NOT NULL DEFAULT 'Pending',
  person_id          INT NOT NULL,
  center_no          INT NOT NULL,
  officer_id         INT NOT NULL,
  CONSTRAINT fk_reg_person  FOREIGN KEY (person_id)
    REFERENCES person(person_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_reg_center  FOREIGN KEY (center_no)
    REFERENCES registration_center(center_no) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_reg_officer FOREIGN KEY (officer_id)
    REFERENCES officer(officer_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_reg_status CHECK (status IN ('Pending','Approved','Rejected'))
) ENGINE=InnoDB;

-- RESTRICT: a registration with an issued card cannot be deleted by accident
CREATE TABLE id_card (
  card_no          VARCHAR(30) PRIMARY KEY,
  issue_date       DATE NOT NULL,
  expiry_date      DATE NOT NULL,
  nationality      VARCHAR(50) NOT NULL,
  card_status      VARCHAR(20) NOT NULL DEFAULT 'Active',
  registration_id  INT NOT NULL,
  CONSTRAINT fk_card_registration FOREIGN KEY (registration_id)
    REFERENCES registration_office(registration_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_card_dates  CHECK (expiry_date > issue_date),
  CONSTRAINT chk_card_status CHECK (card_status IN ('Active','Lost','Expired','Cancelled'))
) ENGINE=InnoDB;

CREATE TABLE document (
  document_no      VARCHAR(30) PRIMARY KEY,
  document_type    VARCHAR(30) NOT NULL,
  issue_date       DATE NULL,
  registration_id  INT NOT NULL,
  CONSTRAINT fk_document_registration FOREIGN KEY (registration_id)
    REFERENCES registration_office(registration_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE witness (
  witness_id   INT AUTO_INCREMENT PRIMARY KEY,
  national_id  VARCHAR(30) NOT NULL,
  name         VARCHAR(100) NOT NULL,
  CONSTRAINT uq_witness_national_id UNIQUE (national_id)
) ENGINE=InnoDB;

-- Many-to-many link table (Step 6)
CREATE TABLE document_witness (
  document_no  VARCHAR(30) NOT NULL,
  witness_id   INT NOT NULL,
  PRIMARY KEY (document_no, witness_id),
  CONSTRAINT fk_dw_document FOREIGN KEY (document_no)
    REFERENCES document(document_no) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_dw_witness FOREIGN KEY (witness_id)
    REFERENCES witness(witness_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE biometric (
  biometric_id  INT AUTO_INCREMENT PRIMARY KEY,
  fingerprint   LONGBLOB NULL,
  photo         MEDIUMBLOB NULL,
  capture_date  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  person_id     INT NOT NULL,
  CONSTRAINT fk_biometric_person FOREIGN KEY (person_id)
    REFERENCES person(person_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_person_surname ON person(surname, first_name);
CREATE INDEX idx_reg_person ON registration_office(person_id);

-- ---------- Task 1 (c): fictional sample data ----------

INSERT INTO registration_center (center_name, district) VALUES
 ('Lilongwe City Centre Office', 'Lilongwe'),
 ('Blantyre Regional Office',    'Blantyre'),
 ('Mzuzu Regional Office',       'Mzimba');

INSERT INTO officer (first_name, last_name, position, center_no) VALUES
 ('Grace',  'Msiska',  'Registration Clerk',  1),
 ('Peter',  'Nkhoma',  'Senior Officer',      1),
 ('Esther', 'Chirwa',  'Registration Clerk',  2),
 ('Daniel', 'Gondwe',  'Supervisor',          3);

INSERT INTO person (first_name, other_names, surname, date_of_birth, sex, address_no, village, district) VALUES
 ('Chikondi',  'Alinafe', 'Banda',   '1998-03-14', 'Female', 'LL-102', 'Kauma',      'Lilongwe'),
 ('Tiyamike',  NULL,      'Phiri',   '1990-11-02', 'Male',   'BT-45',  'Ndirande',   'Blantyre'),
 ('Mercy',     'Chisomo', 'Kachale', '2001-07-21', 'Female', 'LL-310', 'Area 25',    'Lilongwe'),
 ('Yamikani',  NULL,      'Mwale',   '1985-01-30', 'Male',   'MZ-77',  'Katoto',     'Mzimba'),
 ('Thomas',    'Peter',   'Okello',  '1982-09-09', 'Male',   'LL-215', 'Area 47',    'Lilongwe'),
 ('Priya',     NULL,      'Nair',    '1993-05-18', 'Female', 'BT-12',  'Chichiri',   'Blantyre');

INSERT INTO citizen (person_id, place_of_birth, nationality, citizenship_status, citizen_id) VALUES
 (1, 'Kamuzu Central Hospital', 'Malawian', 'By Birth',    'MW-1998-0001'),
 (2, 'Queen Elizabeth Hospital','Malawian', 'By Birth',    'MW-1990-0002'),
 (3, 'Bwaila Hospital',         'Malawian', 'By Descent',  'MW-2001-0003'),
 (4, 'Mzuzu Central Hospital',  'Malawian', 'Naturalised', NULL);

INSERT INTO resident_foreigner (person_id) VALUES (5), (6);

INSERT INTO passport (passport_no, issue_date, expiry_date, country, person_id) VALUES
 ('UG1234567', '2021-02-10', '2031-02-09', 'Uganda', 5),
 ('IN7654321', '2022-06-01', '2032-05-31', 'India',  6),
 ('UG7654001', '2015-02-10', '2021-02-09', 'Uganda', 5);

INSERT INTO registration_office (registration_date, branch, status, person_id, center_no, officer_id) VALUES
 ('2026-08-03', 'Lilongwe Main', 'Approved', 1, 1, 1),
 ('2026-08-05', 'Blantyre Main', 'Approved', 2, 2, 3),
 ('2026-08-10', 'Lilongwe Main', 'Approved', 3, 1, 2),
 ('2026-09-01', 'Mzuzu Main',    'Pending',  4, 3, 4),
 ('2026-09-02', 'Lilongwe Main', 'Approved', 5, 1, 1),
 ('2026-09-15', 'Blantyre Main', 'Pending',  6, 2, 3);

INSERT INTO id_card (card_no, issue_date, expiry_date, nationality, card_status, registration_id) VALUES
 ('NRB-2026-0001', '2026-08-20', '2036-08-19', 'Malawian', 'Active', 1),
 ('NRB-2026-0002', '2026-08-22', '2036-08-21', 'Malawian', 'Active', 2),
 ('NRB-2026-0003', '2026-09-20', '2031-09-19', 'Ugandan',  'Active', 5);

INSERT INTO document (document_no, document_type, issue_date, registration_id) VALUES
 ('DOC-1001', 'Birth Certificate', '1998-03-20', 1),
 ('DOC-1002', 'Birth Certificate', '1990-11-10', 2),
 ('DOC-1003', 'Chief''s Letter',   '2026-08-08', 3),
 ('DOC-1004', 'Residence Permit',  '2025-01-15', 5);

INSERT INTO witness (national_id, name) VALUES
 ('MW-1975-0101', 'Alick Mbewe'),
 ('MW-1980-0202', 'Ruth Kamanga'),
 ('MW-1970-0303', 'Group Village Head Mkandawire');

INSERT INTO document_witness (document_no, witness_id) VALUES
 ('DOC-1001', 1), ('DOC-1001', 2), ('DOC-1003', 2), ('DOC-1003', 3);

INSERT INTO biometric (fingerprint, photo, capture_date, person_id) VALUES
 (UNHEX('89504E470D0A1A0A'), NULL, '2026-08-03 09:15:00', 1),
 (UNHEX('89504E470D0A1A0B'), NULL, '2026-08-05 10:30:00', 2);
