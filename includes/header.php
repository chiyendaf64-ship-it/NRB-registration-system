<?php
if (session_status() === PHP_SESSION_NONE){ session_start();}
require_once __DIR__ . '/functions.php';
$page = basename($_SERVER['SCRIPT_NAME']);
$nav = [
    'index.php'      => 'Dashboard',
    'register.php'   => 'Register Applicant',
    'applicants.php' => 'Applicants',
    'cards.php'      => 'ID Cards',
];
if (isset($_SESSION['logged_in'])){
  $nav['logout.php'] = 'logout (' .($_SESSION['officer_name'] ?? 'officer') . ')';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'NRB') ?> | NRB National ID System</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
  <div class="brand">National Registration Bureau <span>ID Registration System</span></div>
  <nav>
    <?php foreach ($nav as $file => $label): ?>
      <a href="<?= e($file) ?>" class="<?= $page === $file ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
</header>
<main class="container">
<?php
foreach ($_SESSION['flash'] ?? [] as $f) {
    echo '<div class="alert ' . e($f['type']) . '">' . e($f['msg']) . '</div>';
}
$_SESSION['flash'] = [];
?>
