<?php
$title = 'Applicant Details';
require_once __DIR__ . '/../includes/functions.php';
$pdo = db();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$st = $pdo->prepare("SELECT p.*, c.place_of_birth, c.nationality, c.citizenship_status, c.citizen_id,
        (c.person_id IS NOT NULL) AS is_citizen, (rf.person_id IS NOT NULL) AS is_foreigner
    FROM person p
    LEFT JOIN citizen c ON c.person_id = p.person_id
    LEFT JOIN resident_foreigner rf ON rf.person_id = p.person_id
    WHERE p.person_id = :id");
$st->execute(['id' => $id ?: 0]);
$p = $st->fetch();
if (!$p) {
    flash('error', 'Applicant not found.');
    redirect('applicants.php');
}
$q = function (string $sql) use ($pdo, $p) { $s = $pdo->prepare($sql); $s->execute(['p' => $p['person_id']]); return $s->fetchAll(); };

$regs = $q("SELECT r.*, ctr.center_name, CONCAT(o.first_name,' ',o.last_name) AS officer, idc.card_no, idc.card_status, idc.issue_date AS card_issue, idc.expiry_date AS card_expiry
            FROM registration_office r
            JOIN registration_center ctr ON ctr.center_no = r.center_no
            JOIN officer o ON o.officer_id = r.officer_id
            LEFT JOIN id_card idc ON idc.registration_id = r.registration_id
            WHERE r.person_id = :p ORDER BY r.registration_id");
$passports = $q('SELECT * FROM passport WHERE person_id = :p ORDER BY expiry_date DESC');
$docs = $q("SELECT d.document_no, d.document_type, d.issue_date, r.registration_id,
                   GROUP_CONCAT(w.name SEPARATOR ', ') AS witnesses
            FROM document d
            JOIN registration_office r ON r.registration_id = d.registration_id
            LEFT JOIN document_witness dw ON dw.document_no = d.document_no
            LEFT JOIN witness w ON w.witness_id = dw.witness_id
            WHERE r.person_id = :p GROUP BY d.document_no, d.document_type, d.issue_date, r.registration_id");
$bio = $q('SELECT biometric_id, capture_date, (photo IS NOT NULL) AS has_photo, (fingerprint IS NOT NULL) AS has_fp FROM biometric WHERE person_id = :p');
$hasPhoto = array_filter($bio, fn($b) => $b['has_photo']);
require __DIR__ . '/../includes/header.php';
?>
<h1><?= e($p['first_name'] . ' ' . $p['surname']) ?></h1>
<div class="card">
  <h2>Personal details</h2>
  <div style="display:flex;gap:24px;flex-wrap:wrap">
    <?php if ($hasPhoto): ?><img class="photo" src="photo.php?id=<?= e($p['person_id']) ?>" alt="Applicant photo"><?php endif; ?>
    <dl style="flex:1;min-width:280px">
      <dt>Person ID</dt><dd><?= e($p['person_id']) ?></dd>
      <dt>Full name</dt><dd><?= e(trim($p['first_name'] . ' ' . $p['other_names'] . ' ' . $p['surname'])) ?></dd>
      <dt>Date of birth</dt><dd><?= e($p['date_of_birth']) ?></dd>
      <dt>Sex</dt><dd><?= e($p['sex']) ?></dd>
      <dt>Address</dt><dd><?= e($p['address_no'] . ', ' . $p['village'] . ', ' . $p['district']) ?></dd>
      <dt>Category</dt><dd><?= e(category_of($p)) ?></dd>
      <?php if ($p['is_citizen']): ?>
        <dt>Place of birth</dt><dd><?= e($p['place_of_birth']) ?></dd>
        <dt>Nationality</dt><dd><?= e($p['nationality']) ?></dd>
        <dt>Citizenship</dt><dd><?= e($p['citizenship_status']) ?></dd>
        <dt>Citizen ID</dt><dd><?= e($p['citizen_id'] ?? '-') ?></dd>
      <?php endif; ?>
      <dt>Biometric records</dt><dd><?= count($bio) ?></dd>
    </dl>
  </div>
  <div class="actions">
    <a class="btn" href="applicant_edit.php?id=<?= e($p['person_id']) ?>">Edit</a>
    <a class="btn gray" href="applicants.php">Back to list</a>
    <form class="inline" method="post" action="applicant_delete.php"
          onsubmit="return confirm('Delete this applicant and all linked records? This cannot be undone.');">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($p['person_id']) ?>">
      <button class="btn danger" type="submit">Delete applicant</button>
    </form>
  </div>
</div>

<?php if ($passports): ?>
<div class="card"><h2>Passports</h2><div class="table-wrap"><table>
  <tr><th>Passport no.</th><th>Country</th><th>Issued</th><th>Expires</th></tr>
  <?php foreach ($passports as $pp): ?>
    <tr><td><?= e($pp['passport_no']) ?></td><td><?= e($pp['country']) ?></td><td><?= e($pp['issue_date']) ?></td><td><?= e($pp['expiry_date']) ?></td></tr>
  <?php endforeach; ?></table></div></div>
<?php endif; ?>

<div class="card"><h2>Registrations and ID cards</h2><div class="table-wrap"><table>
  <tr><th>Reg. ID</th><th>Date</th><th>Centre / branch</th><th>Officer</th><th>Status</th><th>ID card</th></tr>
  <?php foreach ($regs as $r): ?>
    <tr>
      <td><?= e($r['registration_id']) ?></td><td><?= e($r['registration_date']) ?></td>
      <td><?= e($r['center_name'] . ' / ' . $r['branch']) ?></td><td><?= e($r['officer']) ?></td>
      <td><span class="badge <?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
      <td><?php if ($r['card_no']): ?>
            <?= e($r['card_no']) ?> <span class="badge <?= e($r['card_status']) ?>"><?= e($r['card_status']) ?></span><br>
            <small><?= e($r['card_issue']) ?> to <?= e($r['card_expiry']) ?></small>
          <?php elseif ($r['status'] === 'Approved'): ?><a class="btn small" href="cards.php">Issue card</a>
          <?php else: ?>-<?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$regs): ?><tr><td colspan="6">No registrations.</td></tr><?php endif; ?>
</table></div></div>

<div class="card"><h2>Supporting documents</h2><div class="table-wrap"><table>
  <tr><th>Document no.</th><th>Type</th><th>Issued</th><th>Reg. ID</th><th>Witnesses</th></tr>
  <?php foreach ($docs as $d): ?>
    <tr><td><?= e($d['document_no']) ?></td><td><?= e($d['document_type']) ?></td><td><?= e($d['issue_date'] ?? '-') ?></td>
        <td><?= e($d['registration_id']) ?></td><td><?= e($d['witnesses'] ?? '-') ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$docs): ?><tr><td colspan="5">No documents recorded.</td></tr><?php endif; ?>
</table></div></div>
<?php require __DIR__ . '/../includes/footer.php';
