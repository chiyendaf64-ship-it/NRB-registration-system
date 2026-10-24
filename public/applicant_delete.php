<?php
require_once __DIR__ . '/../includes/functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('applicants.php'); }
csrf_check();
$pdo = db();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) { flash('error', 'Invalid applicant.'); redirect('applicants.php'); }

// Controlled deletion: applicants whose registrations already have an ID card are protected.
$chk = $pdo->prepare('SELECT COUNT(*) FROM id_card idc JOIN registration_office r ON r.registration_id = idc.registration_id WHERE r.person_id = :p');
$chk->execute(['p' => $id]);
if ((int)$chk->fetchColumn() > 0) {
    flash('error', 'This applicant cannot be deleted because an ID card has been issued. Cancel the card instead.');
    redirect('applicant_view.php?id=' . $id);
}
try {
    // ON DELETE CASCADE removes the subtype, passports, registrations, documents and biometrics.
    $st = $pdo->prepare('DELETE FROM person WHERE person_id = :p');
    $st->execute(['p' => $id]);
    flash($st->rowCount() ? 'success' : 'error', $st->rowCount() ? 'Applicant and linked records deleted.' : 'Applicant not found.');
} catch (PDOException $ex) {
    flash('error', db_error_message($ex));
    redirect('applicant_view.php?id=' . $id);
}
redirect('applicants.php');
