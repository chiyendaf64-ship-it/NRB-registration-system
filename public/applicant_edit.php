<?php
$title = 'Edit Applicant';
require_once __DIR__ . '/../includes/functions.php';
$pdo = db();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

$st = $pdo->prepare("SELECT p.*, c.place_of_birth, c.nationality, c.citizenship_status, c.citizen_id,
        (c.person_id IS NOT NULL) AS is_citizen
    FROM person p LEFT JOIN citizen c ON c.person_id = p.person_id WHERE p.person_id = :id");
$st->execute(['id' => $id ?: 0]);
$p = $st->fetch();
if (!$p) { flash('error', 'Applicant not found.'); redirect('applicants.php'); }

$centers  = $pdo->query('SELECT center_no, center_name FROM registration_center ORDER BY center_name')->fetchAll();
$officers = $pdo->query('SELECT officer_id, CONCAT(first_name," ",last_name) AS name, center_no FROM officer ORDER BY last_name')->fetchAll();
$rs = $pdo->prepare('SELECT * FROM registration_office WHERE person_id = :p ORDER BY registration_id DESC LIMIT 1');
$rs->execute(['p' => $p['person_id']]);
$reg = $rs->fetch() ?: null;

$d = $p + ['citizen_id' => (string)($p['citizen_id'] ?? '')];
if ($reg) $d += ['center_no' => $reg['center_no'], 'officer_id' => $reg['officer_id'], 'branch' => $reg['branch'], 'status' => $reg['status']];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = array_merge($d, person_input(), citizen_input());
    foreach (['center_no','officer_id','branch','status'] as $k) $d[$k] = post($k);
    $errors = validate_person($d);
    if ($p['is_citizen']) $errors = array_merge($errors, validate_citizen($d));
    if ($reg) {
        $ok = false;
        foreach ($officers as $o) if ((string)$o['officer_id'] === $d['officer_id'] && (string)$o['center_no'] === $d['center_no']) $ok = true;
        if (!$ok) $errors[] = 'Select a centre and an officer who works at that centre.';
        if ($d['branch'] === '' || mb_strlen($d['branch']) > 50) $errors[] = 'Branch is required (max 50 characters).';
        if (!in_array($d['status'], REG_STATUSES, true)) $errors[] = 'Invalid registration status.';
    }
    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE person SET first_name=:fn, other_names=:on, surname=:sn, date_of_birth=:dob, sex=:sex,
                           address_no=:a, village=:v, district=:di WHERE person_id=:id')
                ->execute(['fn'=>$d['first_name'],'on'=>$d['other_names'] ?: null,'sn'=>$d['surname'],'dob'=>$d['date_of_birth'],
                           'sex'=>$d['sex'],'a'=>$d['address_no'],'v'=>$d['village'],'di'=>$d['district'],'id'=>$p['person_id']]);
            if ($p['is_citizen']) {
                $pdo->prepare('UPDATE citizen SET place_of_birth=:pob, nationality=:n, citizenship_status=:cs, citizen_id=:cid WHERE person_id=:id')
                    ->execute(['pob'=>$d['place_of_birth'],'n'=>$d['nationality'],'cs'=>$d['citizenship_status'],
                               'cid'=>$d['citizen_id'] ?: null,'id'=>$p['person_id']]);
            }
            if ($reg) {
                $pdo->prepare('UPDATE registration_office SET center_no=:c, officer_id=:o, branch=:b, status=:s WHERE registration_id=:r')
                    ->execute(['c'=>(int)$d['center_no'],'o'=>(int)$d['officer_id'],'b'=>$d['branch'],'s'=>$d['status'],'r'=>$reg['registration_id']]);
            }
            $pdo->commit();
            flash('success', 'Applicant record updated successfully.');
            redirect('applicant_view.php?id=' . $p['person_id']);
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = db_error_message($ex);
        }
    }
}
require __DIR__ . '/../includes/header.php';
$v = fn(string $k) => e($d[$k] ?? '');
$sel = fn(string $k, string $val) => ((string)($d[$k] ?? '') === $val) ? 'selected' : '';
?>
<h1>Edit applicant #<?= e($p['person_id']) ?></h1>
<?php if ($errors): ?>
  <div class="alert error"><strong>Please fix the following:</strong><ul>
    <?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form method="post" novalidate>
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($p['person_id']) ?>">
  <div class="card"><h2>Personal details</h2><div class="grid">
    <div><label class="req">First name</label><input name="first_name" maxlength="50" value="<?= $v('first_name') ?>"></div>
    <div><label>Other names</label><input name="other_names" maxlength="50" value="<?= $v('other_names') ?>"></div>
    <div><label class="req">Surname</label><input name="surname" maxlength="50" value="<?= $v('surname') ?>"></div>
    <div><label class="req">Date of birth</label><input type="date" name="date_of_birth" value="<?= $v('date_of_birth') ?>"></div>
    <div><label class="req">Sex</label><select name="sex">
      <option <?= $sel('sex','Male') ?>>Male</option><option <?= $sel('sex','Female') ?>>Female</option></select></div>
    <div><label class="req">Address no.</label><input name="address_no" maxlength="20" value="<?= $v('address_no') ?>"></div>
    <div><label class="req">Village</label><input name="village" maxlength="50" value="<?= $v('village') ?>"></div>
    <div><label class="req">District</label><input name="district" maxlength="50" value="<?= $v('district') ?>"></div>
  </div></div>

  <?php if ($p['is_citizen']): ?>
  <div class="card"><h2>Citizen details</h2><div class="grid">
    <div><label class="req">Place of birth</label><input name="place_of_birth" maxlength="50" value="<?= $v('place_of_birth') ?>"></div>
    <div><label class="req">Nationality</label><input name="nationality" maxlength="50" value="<?= $v('nationality') ?>"></div>
    <div><label class="req">Citizenship status</label><select name="citizenship_status">
      <?php foreach (['By Birth','By Descent','Naturalised','Registered'] as $s): ?>
        <option <?= $sel('citizenship_status',$s) ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
    <div><label>Citizen ID</label><input name="citizen_id" maxlength="30" value="<?= $v('citizen_id') ?>"></div>
  </div></div>
  <?php endif; ?>

  <?php if ($reg): ?>
  <div class="card"><h2>Registration #<?= e($reg['registration_id']) ?></h2><div class="grid">
    <div><label class="req">Centre</label><select name="center_no">
      <?php foreach ($centers as $c): ?>
        <option value="<?= e($c['center_no']) ?>" <?= $sel('center_no',(string)$c['center_no']) ?>><?= e($c['center_name']) ?></option><?php endforeach; ?></select></div>
    <div><label class="req">Officer</label><select name="officer_id">
      <?php foreach ($officers as $o): ?>
        <option value="<?= e($o['officer_id']) ?>" <?= $sel('officer_id',(string)$o['officer_id']) ?>><?= e($o['name']) ?> (centre <?= e($o['center_no']) ?>)</option><?php endforeach; ?></select></div>
    <div><label class="req">Branch</label><input name="branch" maxlength="50" value="<?= $v('branch') ?>"></div>
    <div><label class="req">Status</label><select name="status">
      <?php foreach (REG_STATUSES as $s): ?><option <?= $sel('status',$s) ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
  </div></div>
  <?php endif; ?>
  <p><button class="btn" type="submit">Save changes</button>
     <a class="btn gray" href="applicant_view.php?id=<?= e($p['person_id']) ?>">Cancel</a></p>
</form>
<?php require __DIR__ . '/../includes/footer.php';
