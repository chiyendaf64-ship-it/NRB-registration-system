-- Task 1 (d) and (e): demonstration queries.
USE nrb_db;

-- d) Retrieve all applicants
SELECT * FROM person ORDER BY surname, first_name;

-- d) Search by identifier / name
SELECT p.person_id, p.first_name, p.surname, c.citizen_id
FROM person p JOIN citizen c ON c.person_id = p.person_id
WHERE c.citizen_id = 'MW-1998-0001';

SELECT person_id, first_name, surname, district
FROM person WHERE surname LIKE '%Ban%' OR first_name LIKE '%Ban%';

-- d) JOIN: applicant + category + registration + centre + officer + card
SELECT p.person_id,
       CONCAT(p.first_name,' ',p.surname) AS applicant,
       CASE WHEN c.person_id IS NOT NULL THEN 'Citizen' ELSE 'Resident Foreigner' END AS category,
       r.registration_id, r.registration_date, r.status,
       ctr.center_name,
       CONCAT(o.first_name,' ',o.last_name) AS officer,
       idc.card_no, idc.card_status
FROM person p
LEFT JOIN citizen c               ON c.person_id = p.person_id
LEFT JOIN registration_office r   ON r.person_id = p.person_id
LEFT JOIN registration_center ctr ON ctr.center_no = r.center_no
LEFT JOIN officer o               ON o.officer_id = r.officer_id
LEFT JOIN id_card idc             ON idc.registration_id = r.registration_id
ORDER BY p.person_id;

-- d) JOIN: resident foreigners with passports
SELECT p.person_id, CONCAT(p.first_name,' ',p.surname) AS foreigner,
       pp.passport_no, pp.country, pp.expiry_date
FROM resident_foreigner rf
JOIN person p    ON p.person_id = rf.person_id
JOIN passport pp ON pp.person_id = rf.person_id;

-- d) JOIN: documents and their witnesses (many-to-many)
SELECT d.document_no, d.document_type, w.name AS witness
FROM document d
JOIN document_witness dw ON dw.document_no = d.document_no
JOIN witness w           ON w.witness_id = dw.witness_id
ORDER BY d.document_no;

-- d) Approved registrations that still have no ID card
SELECT r.registration_id, p.first_name, p.surname
FROM registration_office r
JOIN person p ON p.person_id = r.person_id
LEFT JOIN id_card idc ON idc.registration_id = r.registration_id
WHERE r.status = 'Approved' AND idc.card_no IS NULL;

-- e) UPDATE
UPDATE registration_office SET status = 'Approved' WHERE registration_id = 4;
UPDATE person SET village = 'Area 18', address_no = 'LL-999' WHERE person_id = 3;
UPDATE id_card SET card_status = 'Lost' WHERE card_no = 'NRB-2026-0002';

-- e) DELETE: add a throw-away applicant, then delete it (cascades to its registration)
INSERT INTO person (first_name, surname, date_of_birth, sex, address_no, village, district)
VALUES ('Test', 'Delete', '2000-01-01', 'Male', 'X-1', 'Test Village', 'Lilongwe');
SET @pid = LAST_INSERT_ID();
INSERT INTO registration_office (registration_date, branch, status, person_id, center_no, officer_id)
VALUES (CURDATE(), 'Lilongwe Main', 'Pending', @pid, 1, 1);
DELETE FROM person WHERE person_id = @pid;

-- e) Constraint demonstration: this FAILS (registration 1 has an issued ID card)
-- DELETE FROM registration_office WHERE registration_id = 1;
