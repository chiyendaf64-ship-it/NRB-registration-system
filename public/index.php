<?php
$title = 'Dashboard';
require __DIR__ . '/../includes/header.php';
$pdo = db();
$count = fn(string $sql) => (int)$pdo->query($sql)->fetchColumn();
$stats = [
    'Applicants'          => $count('SELECT COUNT(*) FROM person'),
    'Pending registrations' => $count("SELECT COUNT(*) FROM registration_office WHERE status='Pending'"),
    'Approved registrations' => $count("SELECT COUNT(*) FROM registration_office WHERE status='Approved'"),
    'ID cards issued'     => $count('SELECT COUNT(*) FROM id_card'),
];
$recent = $pdo->query(
    "SELECT r.registration_id, r.registration_date, r.status, p.person_id,
            CONCAT(p.first_name,' ',p.surname) AS applicant, ctr.center_name
     FROM registration_office r
     JOIN person p ON p.person_id = r.person_id
     JOIN registration_center ctr ON ctr.center_no = r.center_no
     ORDER BY r.registration_date DESC, r.registration_id DESC LIMIT 6"
)->fetchAll();
?>
<h1>Dashboard</h1>
<div class="stats">
  <?php foreach ($stats as $label => $n): ?>
    <div class="stat"><b><?= e($n) ?></b><?= e($label) ?></div>
  <?php endforeach; ?>
</div>
<div class="card">
  <h2>Recent registrations</h2>
  <div class="table-wrap"><table>
    <tr><th>Reg. ID</th><th>Applicant</th><th>Centre</th><th>Date</th><th>Status</th></tr>
    <?php foreach ($recent as $r): ?>
      <tr>
        <td><?= e($r['registration_id']) ?></td>
        <td><a href="applicant_view.php?id=<?= e($r['person_id']) ?>"><?= e($r['applicant']) ?></a></td>
        <td><?= e($r['center_name']) ?></td>
        <td><?= e($r['registration_date']) ?></td>
        <td><span class="badge <?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <p><a class="btn" href="register.php">Register new applicant</a>
     <a class="btn gray" href="applicants.php">View all applicants</a></p>
</div>
<?php require __DIR__ . '/../includes/footer.php';
