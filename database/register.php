<?php
$title = 'Register Applicant';
require_once __DIR__ . '/../includes/functions.php';
$pdo = db();

$centers  = $pdo->query('SELECT center_no, center_name FROM registration_center ORDER BY center_name')->fetchAll();
$officers = $pdo->query('SELECT officer_id, CONCAT(first_name," ",last_name) AS name, center_no FROM officer ORDER BY last_name')->fetchAll();

$errors = [];
$d = ['first_name'=>'','other_names'=>'','surname'=>'','date_of_birth'=>'','sex'=>'','address_no'=>'','village'=>'','district'=>'',
      'category'=>'citizen','place_of_birth'=>'','nationality'=>'Malawian','citizenship_status'=>'','citizen_id'=>'',
      'passport_no'=>'','passport_issue'=>'','passport_expiry'=>'','passport_country'=>'',
      'center_no'=>'','officer_id'=>'','branch'=>'','status'=>'Pending'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = array_merge($d, person_input(), citizen_input());
    foreach (['category','passport_no','passport_issue','passport_expiry','passport_country','center_no','officer_id','branch','status'] as $k) {
        $d[$k] = post($k);
    }
    $errors = validate_person($d);

    if ($d['category'] === 'citizen') {
        $errors = array_merge($errors, validate_citizen($d));
    } elseif ($d['category'] === 'foreigner') {
        if (!preg_match('/^[A-Za-z0-9]{6,30}$/', $d['passport_no'])) $errors[] = 'Passport number is required (6-30 letters/digits).';
        if (!valid_date($d['passport_issue']) || !valid_date($d['passport_expiry'])) {
            $errors[] = 'Passport issue and expiry dates must be valid dates.';
        } elseif ($d['passport_expiry'] <= $d['passport_issue']) {
            $errors[] = 'Passport expiry date must be after the issue date.';
        } elseif ($d['passport_expiry'] < date('Y-m-d')) {
            $errors[] = 'The passport has already expired.';
        }
        if (!preg_match(NAME_RE, $d['passport_country'])) $errors[] = 'Passport issuing country is required.';
    } else {
        $errors[] = 'Select an applicant category.';
    }

    // Registration details
    $officerOk = false;
    foreach ($officers as $o) {
        if ((string)$o['officer_id'] === $d['officer_id'] && (string)$o['center_no'] === $d['center_no']) $officerOk = true;
    }
    if (!ctype_digit($d['center_no']) || !ctype_digit($d['officer_id'])) {
        $errors[] = 'Select a registration centre and officer.';
    } elseif (!$officerOk) {
        $errors[] = 'The selected officer does not work at the selected centre.';
    }
    if ($d['branch'] === '' || mb_strlen($d['branch']) > 50) $errors[] = 'Branch is required (max 50 characters).';
    if (!in_array($d['status'], ['Pending','Approved'], true)) $errors[] = 'Invalid registration status.';

    // Optional photo -> BIOMETRIC.photo
    $photo = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK || $_FILES['photo']['size'] > 1048576) {
            $errors[] = 'Photo upload failed or is larger than 1 MB.';
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['photo']['tmp_name']);
            if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
                $errors[] = 'Photo must be a JPEG or PNG image.';
            } else {
                $photo = file_get_contents($_FILES['photo']['tmp_name']);
            }
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO person (first_name, other_names, surname, date_of_birth, sex, address_no, village, district)
                           VALUES (:fn, :on, :sn, :dob, :sex, :addr, :vil, :dis)')
                ->execute(['fn'=>$d['first_name'], 'on'=>$d['other_names'] ?: null, 'sn'=>$d['surname'], 'dob'=>$d['date_of_birth'],
                           'sex'=>$d['sex'], 'addr'=>$d['address_no'], 'vil'=>$d['village'], 'dis'=>$d['district']]);
            $pid = (int)$pdo->lastInsertId();

            if ($d['category'] === 'citizen') {
                $pdo->prepare('INSERT INTO citizen (person_id, place_of_birth, nationality, citizenship_status, citizen_id)
                               VALUES (:p, :pob, :nat, :cs, :cid)')
                    ->execute(['p'=>$pid, 'pob'=>$d['place_of_birth'], 'nat'=>$d['nationality'],
                               'cs'=>$d['citizenship_status'], 'cid'=>$d['citizen_id'] ?: null]);
            } else {
                $pdo->prepare('INSERT INTO resident_foreigner (person_id) VALUES (:p)')->execute(['p'=>$pid]);
                $pdo->prepare('INSERT INTO passport (passport_no, issue_date, expiry_date, country, person_id)
                               VALUES (:no, :i, :x, :c, :p)')
                    ->execute(['no'=>strtoupper($d['passport_no']), 'i'=>$d['passport_issue'], 'x'=>$d['passport_expiry'],
                               'c'=>$d['passport_country'], 'p'=>$pid]);
            }

            $pdo->prepare('INSERT INTO registration_office (registration_date, branch, status, person_id, center_no, officer_id)
                           VALUES (CURDATE(), :b, :s, :p, :c, :o)')
                ->execute(['b'=>$d['branch'], 's'=>$d['status'], 'p'=>$pid, 'c'=>(int)$d['center_no'], 'o'=>(int)$d['officer_id']]);

            if ($photo !== null) {
                $st = $pdo->prepare('INSERT INTO biometric (photo, capture_date, person_id) VALUES (:ph, NOW(), :p)');
                $st->bindValue(':ph', $photo, PDO::PARAM_LOB);
                $st->bindValue(':p', $pid, PDO::PARAM_INT);
                $st->execute();
            }
            $pdo->commit();
            flash('success', 'Applicant registered successfully (Person ID ' . $pid . ').');
            redirect('applicant_view.php?id=' . $pid);
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = db_error_message($ex);
        }
    }
}

require __DIR__ . '/../includes/header.php';
$v = fn(string $k) => e($d[$k] ?? '');
$sel = fn(string $k, string $val) => (($d[$k] ?? '') === $val) ? 'selected' : '';
?>
<h1>Register Applicant</h1>
<?php if ($errors): ?>
  <div class="alert error"><strong>Please fix the following:</strong><ul>
    <?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
  </ul></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?>
  <div class="card">
    <h2>Personal details</h2>
    <div class="grid">
      <div><label class="req">First name</label><input name="first_name" maxlength="50" value="<?= $v('first_name') ?>" required></div>
      <div><label>Other names</label><input name="other_names" maxlength="50" value="<?= $v('other_names') ?>"></div>
      <div><label class="req">Surname</label><input name="surname" maxlength="50" value="<?= $v('surname') ?>" required></div>
      <div><label class="req">Date of birth</label><input type="date" name="date_of_birth" max="<?= date('Y-m-d') ?>" value="<?= $v('date_of_birth') ?>" required></div>
      <div><label class="req">Sex</label>
        <select name="sex" required><option value="">-- select --</option>
          <option <?= $sel('sex','Male') ?>>Male</option><option <?= $sel('sex','Female') ?>>Female</option></select></div>
      <div><label class="req">Address no.</label><input name="address_no" maxlength="20" value="<?= $v('address_no') ?>" required></div>
      <div><label class="req">Village</label><input name="village" maxlength="50" value="<?= $v('village') ?>" required></div>
      <div><label class="req">District</label><input name="district" maxlength="50" value="<?= $v('district') ?>" required></div>
      <div><label>Photo (JPEG/PNG, max 1 MB)</label><input type="file" name="photo" accept="image/jpeg,image/png"></div>
    </div>
  </div>

  <div class="card">
    <h2>Applicant category</h2>
    <div class="grid">
      <div><label class="req">Category</label>
        <select name="category" id="category">
          <option value="citizen" <?= $sel('category','citizen') ?>>Citizen</option>
          <option value="foreigner" <?= $sel('category','foreigner') ?>>Resident foreigner</option>
        </select></div>
    </div>
    <div class="grid" id="citizenBox" style="margin-top:14px">
      <div><label class="req">Place of birth</label><input name="place_of_birth" maxlength="50" value="<?= $v('place_of_birth') ?>"></div>
      <div><label class="req">Nationality</label><input name="nationality" maxlength="50" value="<?= $v('nationality') ?>"></div>
      <div><label class="req">Citizenship status</label>
        <select name="citizenship_status"><option value="">-- select --</option>
          <?php foreach (['By Birth','By Descent','Naturalised','Registered'] as $s): ?>
            <option <?= $sel('citizenship_status',$s) ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
      <div><label>Citizen ID (unique, optional)</label><input name="citizen_id" maxlength="30" value="<?= $v('citizen_id') ?>"></div>
    </div>
    <div class="grid" id="foreignerBox" style="margin-top:14px">
      <div><label class="req">Passport no.</label><input name="passport_no" maxlength="30" value="<?= $v('passport_no') ?>"></div>
      <div><label class="req">Issue date</label><input type="date" name="passport_issue" value="<?= $v('passport_issue') ?>"></div>
      <div><label class="req">Expiry date</label><input type="date" name="passport_expiry" value="<?= $v('passport_expiry') ?>"></div>
      <div><label class="req">Issuing country</label><input name="passport_country" maxlength="50" value="<?= $v('passport_country') ?>"></div>
    </div>
  </div>

  <div class="card">
    <h2>Registration details</h2>
    <div class="grid">
      <div><label class="req">Registration centre</label>
        <select name="center_no" id="center_no"><option value="">-- select --</option>
          <?php foreach ($centers as $c): ?>
            <option value="<?= e($c['center_no']) ?>" <?= $sel('center_no',(string)$c['center_no']) ?>><?= e($c['center_name']) ?></option>
          <?php endforeach; ?></select></div>
      <div><label class="req">Processing officer</label>
        <select name="officer_id" id="officer_id"><option value="">-- select --</option>
          <?php foreach ($officers as $o): ?>
            <option value="<?= e($o['officer_id']) ?>" data-center="<?= e($o['center_no']) ?>" <?= $sel('officer_id',(string)$o['officer_id']) ?>><?= e($o['name']) ?></option>
          <?php endforeach; ?></select></div>
      <div><label class="req">Branch</label><input name="branch" maxlength="50" value="<?= $v('branch') ?>"></div>
      <div><label class="req">Status</label>
        <select name="status"><option <?= $sel('status','Pending') ?>>Pending</option><option <?= $sel('status','Approved') ?>>Approved</option></select></div>
    </div>
    <p><button class="btn" type="submit">Save applicant</button> <a class="btn gray" href="applicants.php">Cancel</a></p>
  </div>
</form>

<script>
const cat = document.getElementById('category');
function toggleCat(){
  const f = cat.value === 'foreigner';
  document.getElementById('foreignerBox').style.display = f ? 'grid' : 'none';
  document.getElementById('citizenBox').style.display = f ? 'none' : 'grid';
}
cat.addEventListener('change', toggleCat); toggleCat();
const center = document.getElementById('center_no'), officer = document.getElementById('officer_id');
function filterOfficers(){
  for (const o of officer.options) {
    if (!o.value) continue;
    const ok = !center.value || o.dataset.center === center.value;
    o.hidden = !ok; if (!ok && o.selected) officer.value = '';
  }
}
center.addEventListener('change', filterOfficers); filterOfficers();
</script>
<?php require __DIR__ . '/../includes/footer.php';