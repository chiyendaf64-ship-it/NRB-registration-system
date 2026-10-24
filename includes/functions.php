<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/db.php';

const NAME_RE = "/^\p{L}[\p{L} '.\-]{0,48}$/u";

/** Task 3(d): escape every value printed into HTML. */
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Invalid or expired form token. Go back, refresh the page and try again.');
    }
}

function post(string $key): string
{
    return trim((string)($_POST[$key] ?? ''));
}

function valid_date(string $d): bool
{
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x !== false && $x->format('Y-m-d') === $d;
}

function person_input(): array
{
    $d = [];
    foreach (['first_name','other_names','surname','date_of_birth','sex','address_no','village','district'] as $k) {
        $d[$k] = post($k);
    }
    return $d;
}

/** Task 3(b): validate PERSON fields. Returns list of error messages. */
function validate_person(array $d): array
{
    $err = [];
    if (!preg_match(NAME_RE, $d['first_name'])) {
        $err[] = 'First name is required and may contain letters, spaces, hyphens and apostrophes only.';
    }
    if ($d['other_names'] !== '' && !preg_match(NAME_RE, $d['other_names'])) {
        $err[] = 'Other names may contain letters, spaces, hyphens and apostrophes only.';
    }
    if (!preg_match(NAME_RE, $d['surname'])) {
        $err[] = 'Surname is required and may contain letters, spaces, hyphens and apostrophes only.';
    }
    if (!valid_date($d['date_of_birth'])) {
        $err[] = 'Date of birth must be a valid date.';
    } else {
        $dob = new DateTime($d['date_of_birth']);
        $today = new DateTime('today');
        if ($dob > $today) {
            $err[] = 'Date of birth cannot be in the future.';
        } elseif ($dob->diff($today)->y < 16) {
            $err[] = 'Applicant must be at least 16 years old.';
        }
    }
    if (!in_array($d['sex'], ['Male', 'Female'], true)) {
        $err[] = 'Select a valid sex.';
    }
    if (!preg_match('/^[A-Za-z0-9\/\- ]{1,20}$/', $d['address_no'])) {
        $err[] = 'Address number is required (max 20 letters, digits, / or -).';
    }
    if (!preg_match(NAME_RE, $d['village']) && !preg_match('/^[\p{L}0-9][\p{L}0-9 \'.\-]{0,49}$/u', $d['village'])) {
        $err[] = 'Village is required (max 50 characters).';
    }
    if (!preg_match(NAME_RE, $d['district'])) {
        $err[] = 'District is required and must contain letters only.';
    }
    return $err;
}

function citizen_input(): array
{
    $d = [];
    foreach (['place_of_birth','nationality','citizenship_status','citizen_id'] as $k) {
        $d[$k] = post($k);
    }
    return $d;
}

function validate_citizen(array $d): array
{
    $err = [];
    if ($d['place_of_birth'] === '' || mb_strlen($d['place_of_birth']) > 50) {
        $err[] = 'Place of birth is required (max 50 characters).';
    }
    if (!preg_match(NAME_RE, $d['nationality'])) {
        $err[] = 'Nationality is required and must contain letters only.';
    }
    if (!in_array($d['citizenship_status'], ['By Birth','By Descent','Naturalised','Registered'], true)) {
        $err[] = 'Select a valid citizenship status.';
    }
    if ($d['citizen_id'] !== '' && !preg_match('/^[A-Za-z0-9\-]{5,30}$/', $d['citizen_id'])) {
        $err[] = 'Citizen ID may contain letters, digits and hyphens only (5-30 characters).';
    }
    return $err;
}

/** Task 3(c): turn DB constraint errors into readable messages. */
function db_error_message(PDOException $e): string
{
    $code = (int)($e->errorInfo[1] ?? 0);
    $msg  = $e->getMessage();
    if ($code === 1062) {
        if (stripos($msg, 'citizen_id') !== false) return 'That Citizen ID is already registered to another person.';
        if (stripos($msg, 'passport')   !== false) return 'That passport number already exists in the system.';
        if (stripos($msg, 'card_no') !== false || stripos($msg, 'id_card') !== false) return 'That ID card number has already been issued.';
        return 'A record with the same unique value already exists.';
    }
    if ($code === 1451) return 'This record cannot be removed or changed because other records depend on it (for example an issued ID card).';
    if ($code === 1452) return 'A selected related record (centre, officer or person) does not exist.';
    if ($code === 3819 || $code === 4025) return 'A value violates a database rule (check dates and status values).';
    error_log('DB error: ' . $msg);
    return 'A database error occurred. Please try again.';
}

function category_of(array $row): string
{
    return !empty($row['is_citizen']) ? 'Citizen' : (!empty($row['is_foreigner']) ? 'Resident Foreigner' : 'Unknown');
}

const REG_STATUSES  = ['Pending', 'Approved', 'Rejected'];
const CARD_STATUSES = ['Active', 'Lost', 'Expired', 'Cancelled'];
