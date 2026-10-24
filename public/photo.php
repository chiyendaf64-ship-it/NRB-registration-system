<?php
require_once __DIR__ . '/../includes/functions.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(404); exit; }
$st = db()->prepare('SELECT photo FROM biometric WHERE person_id = :p AND photo IS NOT NULL ORDER BY capture_date DESC LIMIT 1');
$st->execute(['p' => $id]);
$photo = $st->fetchColumn();
if (!$photo) { http_response_code(404); exit; }
$mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($photo);
if (!in_array($mime, ['image/jpeg', 'image/png'], true)) { http_response_code(404); exit; }
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
echo $photo;
