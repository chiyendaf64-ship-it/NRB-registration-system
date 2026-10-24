<?php
$title = 'Applicants';
require __DIR__ . '/../includes/header.php';
$pdo = db();
$q = trim((string)($_GET['q'] ?? ''));

$sql = "SELECT p.person_id, CONCAT(p.first_name,' ',COALESCE(CONCAT(p.other_names,' '),''),p.surname) AS full_name,
               p.date_of_birth, p.sex, p.district,
               (c.person_id IS NOT NULL) AS is_citizen, (rf.person_id IS NOT NULL) AS is_foreigner,
               c.citizen_id, r.registration_id, r.status, ctr.center_name,
               idc.card_no, idc.card_status
        FROM person p
        LEFT JOIN citizen c               ON c.person_id = p.person_id
        LEFT JOIN resident_foreigner rf   ON rf.person_id = p.person_id
        LEFT JOIN registration_office r   ON r.person_id = p.person_id
        LEFT JOIN registration_center ctr ON ctr.center_no = r.center_no
        LEFT JOIN id_card idc             ON idc.registration_id = r.registration_id";
$params = [];
if ($q !== '') {
    $sql .= " WHERE CONCAT(p.first_name,' ',p.surname) LIKE :q1 OR p.surname LIKE :q2 OR p.other_names LIKE :q3
              OR p.district LIKE :q4 OR c.citizen_id LIKE :q5 OR idc.card_no LIKE :q6 OR p.person_id = :q7
              OR EXISTS (SELECT 1 FROM passport pp WHERE pp.person_id = p.person_id AND pp.passport_no LIKE :q8)";
    $like = '%' . $q . '%';
    $params = ['q1'=>$like,'q2'=>$like,'q3'=>$like,'q4'=>$like,'q5'=>$like,'q6'=>$like,'q7'=>ctype_digit($q) ? (int)$q : 0,'q8'=>$like];
}
$sql .= ' ORDER BY p.surname, p.first_name, r.registration_id LIMIT 200';
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
?>
<h1>Applicants</h1>
<form class="search" method="get">
  <input name="q" placeholder="Search by name, person ID, citizen ID, passport no., card no. or district" value="<?= e($q) ?>">
  <button class="btn" type="submit">Search</button>
  <?php if ($q !== ''): ?><a class="btn gray" href="applicants.php">Clear</a><?php endif; ?>
</form>
<div class="card">
  <div class="table-wrap"><table>
    <tr><th>ID</th><th>Name</th><th>Category</th><th>DOB</th><th>Sex</th><th>District</th><th>Centre</th><th>Registration</th><th>ID card</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['person_id']) ?></td>
        <td><?= e($r['full_name']) ?><?= $r['citizen_id'] ? '<br><small>' . e($r['citizen_id']) . '</small>' : '' ?></td>
        <td><?= e(category_of($r)) ?></td>
        <td><?= e($r['date_of_birth']) ?></td>
        <td><?= e($r['sex']) ?></td>
        <td><?= e($r['district']) ?></td>
        <td><?= e($r['center_name'] ?? '-') ?></td>
        <td><?= $r['status'] ? '<span class="badge ' . e($r['status']) . '">' . e($r['status']) . '</span>' : '-' ?></td>
        <td><?= $r['card_no'] ? e($r['card_no']) . ' <span class="badge ' . e($r['card_status']) . '">' . e($r['card_status']) . '</span>' : '-' ?></td>
        <td class="actions">
          <a class="btn small" href="applicant_view.php?id=<?= e($r['person_id']) ?>">View</a>
          <a class="btn small gray" href="applicant_edit.php?id=<?= e($r['person_id']) ?>">Edit</a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="10">No applicants found.</td></tr><?php endif; ?>
  </table></div>
  <p><?= count($rows) ?> record(s) shown.</p>
</div>
<?php require __DIR__ . '/../includes/footer.php';
