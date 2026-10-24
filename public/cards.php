<?php
$title = 'ID Cards';
require_once __DIR__ . '/../includes/functions.php';
$pdo = db();
$errors = [];
$f = ['registration_id' => '', 'card_no' => '', 'issue_date' => date('Y-m-d'), 'expiry_date' => date('Y-m-d', strtotime('+10 years'))];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');

    if ($action === 'issue') {
        foreach ($f as $k => $_) $f[$k] = post($k);
        $f['card_no'] = strtoupper($f['card_no']);
        if (!ctype_digit($f['registration_id'])) $errors[] = 'Select an approved registration.';
        if (!preg_match('/^[A-Z0-9\-]{6,30}$/', $f['card_no'])) $errors[] = 'Card number must be 6-30 letters, digits or hyphens.';
        if (!valid_date($f['issue_date']) || !valid_date($f['expiry_date'])) {
            $errors[] = 'Issue and expiry dates must be valid dates.';
        } elseif ($f['expiry_date'] <= $f['issue_date']) {
            $errors[] = 'Expiry date must be after the issue date.';
        } elseif ($f['issue_date'] > date('Y-m-d')) {
            $errors[] = 'Issue date cannot be in the future.';
        }
        $nat = null;
        if (!$errors) {
            $s = $pdo->prepare("SELECT r.status, COALESCE(c.nationality, (SELECT pp.country FROM passport pp WHERE pp.person_id = r.person_id ORDER BY pp.expiry_date DESC LIMIT 1), 'Malawian') AS nat,
                       (SELECT COUNT(*) FROM id_card x WHERE x.registration_id = r.registration_id) AS cards
                FROM registration_office r LEFT JOIN citizen c ON c.person_id = r.person_id WHERE r.registration_id = :r");
            $s->execute(['r' => (int)$f['registration_id']]);
            $r = $s->fetch();
            if (!$r) $errors[] = 'Registration not found.';
            elseif ($r['status'] !== 'Approved') $errors[] = 'A card can only be issued for an Approved registration.';
            elseif ((int)$r['cards'] > 0) $errors[] = 'An ID card has already been issued for this registration.';
            else $nat = $r['nat'];
        }
        if (!$errors) {
            try {
                $pdo->prepare('INSERT INTO id_card (card_no, issue_date, expiry_date, nationality, card_status, registration_id)
                               VALUES (:n, :i, :x, :nat, \'Active\', :r)')
                    ->execute(['n'=>$f['card_no'],'i'=>$f['issue_date'],'x'=>$f['expiry_date'],'nat'=>$nat,'r'=>(int)$f['registration_id']]);
                flash('success', 'ID card ' . $f['card_no'] . ' issued successfully.');
                redirect('cards.php');
            } catch (PDOException $ex) {
                $errors[] = db_error_message($ex);
            }
        }
    } elseif ($action === 'status') {
        $card = post('card_no'); $status = post('card_status');
        if (!in_array($status, CARD_STATUSES, true)) {
            flash('error', 'Invalid card status.');
        } else {
            try {
                $st = $pdo->prepare('UPDATE id_card SET card_status = :s WHERE card_no = :c');
                $st->execute(['s' => $status, 'c' => $card]);
                flash('success', 'Card ' . $card . ' status set to ' . $status . '.');
            } catch (PDOException $ex) {
                flash('error', db_error_message($ex));
            }
        }
        redirect('cards.php');
    }
}

$eligible = $pdo->query("SELECT r.registration_id, CONCAT(p.first_name,' ',p.surname) AS applicant
    FROM registration_office r JOIN person p ON p.person_id = r.person_id
    LEFT JOIN id_card idc ON idc.registration_id = r.registration_id
    WHERE r.status = 'Approved' AND idc.card_no IS NULL ORDER BY p.surname")->fetchAll();

$q = trim((string)($_GET['q'] ?? ''));
$sql = "SELECT idc.*, p.person_id, CONCAT(p.first_name,' ',p.surname) AS applicant
        FROM id_card idc
        JOIN registration_office r ON r.registration_id = idc.registration_id
        JOIN person p ON p.person_id = r.person_id";
$params = [];
if ($q !== '') {
    $sql .= " WHERE idc.card_no LIKE :a OR CONCAT(p.first_name,' ',p.surname) LIKE :b";
    $params = ['a' => "%$q%", 'b' => "%$q%"];
}
$st = $pdo->prepare($sql . ' ORDER BY idc.issue_date DESC');
$st->execute($params);
$cards = $st->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<h1>National ID Cards</h1>
<?php if ($errors): ?>
  <div class="alert error"><strong>Please fix the following:</strong><ul>
    <?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card">
  <h2>Record card issuance</h2>
  <?php if (!$eligible): ?>
    <p>No approved registrations are waiting for a card.</p>
  <?php else: ?>
  <form method="post" novalidate>
    <?= csrf_field() ?><input type="hidden" name="action" value="issue">
    <div class="grid">
      <div><label class="req">Approved registration</label><select name="registration_id">
        <option value="">-- select --</option>
        <?php foreach ($eligible as $r): ?>
          <option value="<?= e($r['registration_id']) ?>" <?= $f['registration_id'] === (string)$r['registration_id'] ? 'selected' : '' ?>>
            #<?= e($r['registration_id']) ?> - <?= e($r['applicant']) ?></option><?php endforeach; ?></select></div>
      <div><label class="req">Card number</label><input name="card_no" maxlength="30" placeholder="NRB-2026-0004" value="<?= e($f['card_no']) ?>"></div>
      <div><label class="req">Issue date</label><input type="date" name="issue_date" value="<?= e($f['issue_date']) ?>"></div>
      <div><label class="req">Expiry date</label><input type="date" name="expiry_date" value="<?= e($f['expiry_date']) ?>"></div>
    </div>
    <p><button class="btn" type="submit">Issue card</button></p>
  </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Issued cards</h2>
  <form class="search" method="get">
    <input name="q" placeholder="Search by card number or applicant name" value="<?= e($q) ?>">
    <button class="btn" type="submit">Search</button>
  </form>
  <div class="table-wrap"><table>
    <tr><th>Card no.</th><th>Applicant</th><th>Nationality</th><th>Issued</th><th>Expires</th><th>Status</th><th>Change status</th></tr>
    <?php foreach ($cards as $c): ?>
      <tr>
        <td><?= e($c['card_no']) ?></td>
        <td><a href="applicant_view.php?id=<?= e($c['person_id']) ?>"><?= e($c['applicant']) ?></a></td>
        <td><?= e($c['nationality']) ?></td><td><?= e($c['issue_date']) ?></td><td><?= e($c['expiry_date']) ?></td>
        <td><span class="badge <?= e($c['card_status']) ?>"><?= e($c['card_status']) ?></span></td>
        <td><form method="post" class="actions">
          <?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="card_no" value="<?= e($c['card_no']) ?>">
          <select name="card_status" style="width:auto">
            <?php foreach (CARD_STATUSES as $s): ?><option <?= $c['card_status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select>
          <button class="btn small" type="submit">Update</button></form></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$cards): ?><tr><td colspan="7">No cards found.</td></tr><?php endif; ?>
  </table></div>
</div>
<?php require __DIR__ . '/../includes/footer.php';
