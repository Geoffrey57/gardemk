<?php
require_once __DIR__ . '/../src/inc/auth.php';
require_once __DIR__ . '/../src/inc/helpers.php';
require_login();
$user = current_user();
$pdo = $GLOBALS['pdo'];

// GET: show form allowing selection of up to 7 past gardes (or preselect one via garde_id)
$preselect = isset($_GET['garde_id']) ? (int)$_GET['garde_id'] : null;

// fetch past gardes for this user
$stmt = $pdo->prepare('SELECT * FROM gardes WHERE masseur_id = ? AND garde_date < CURDATE() AND status = "planned" ORDER BY garde_date DESC LIMIT 60');
$stmt->execute([$user['id']]);
$past = $stmt->fetchAll();

$errors = [];
$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        $errors[] = 'Erreur CSRF.';
    }

    $selected = $_POST['garde_ids'] ?? [];
    if (empty($selected) || !is_array($selected)) {
        $errors[] = 'Sélectionnez au moins une date de garde.';
    }
    if (is_array($selected) && count($selected) > 7) $errors[] = 'Maximum 7 dates.';

    $has_patients = (isset($_POST['has_patients']) && $_POST['has_patients'] === '1');

    // required questions depend on whether patients were seen
    if ($has_patients) {
        $required_keys = ['fiche_liaison','rdv_non_honores','incidents_reglement','agression','suspicion_maltraitance','pec_hors_kine','pec_exterieur'];
    } else {
        $required_keys = ['pec_hors_kine','pec_exterieur'];
    }

    $answers = [];
    foreach ($required_keys as $k) {
        if (!isset($_POST[$k]) || $_POST[$k] === '') {
            $errors[] = "La question $k est obligatoire.";
        } else {
            $answers[$k] = $_POST[$k] === '1' ? 1 : 0;
        }
    }

    // if has patients, require at least one patient entry
    $has_any_patient = false;
    if ($has_patients) {
        if (!empty($_POST['patients']) && is_array($_POST['patients'])) {
            foreach ($_POST['patients'] as $date => $plist) {
                if (!empty($plist) && is_array($plist)) { $has_any_patient = true; break; }
            }
        }
        if (!$has_any_patient) $errors[] = 'Vous avez indiqué avoir des patients : veuillez en ajouter au moins un.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO garde_saisies (masseur_id, notes, validated_at) VALUES (?, ?, NOW())');
            $stmt->execute([$user['id'], $_POST['notes'] ?? null]);
            $saisie_id = $pdo->lastInsertId();

            $insert_date = $pdo->prepare('INSERT INTO garde_saisie_dates (garde_saisie_id, garde_id, garde_date) VALUES (?, ?, ?);');
            $update_garde = $pdo->prepare('UPDATE gardes SET status = "saisie" WHERE id = ?;');

            foreach ($selected as $gardeId) {
                // fetch garde date
                $gstmt = $pdo->prepare('SELECT garde_date FROM gardes WHERE id = ? AND masseur_id = ? LIMIT 1');
                $gstmt->execute([$gardeId, $user['id']]);
                $g = $gstmt->fetch();
                if (!$g) continue;
                $insert_date->execute([$saisie_id, $gardeId, $g['garde_date']]);
                $update_garde->execute([$gardeId]);
            }

            // save answers
            $ans_stmt = $pdo->prepare('INSERT INTO garde_saisie_answers (garde_saisie_id, question_key, answer, extra_text, extra_number) VALUES (?, ?, ?, ?, ?);');
            foreach ($answers as $k => $v) {
                $extra_text = $_POST[$k . '_text'] ?? null;
                $extra_number = isset($_POST[$k . '_number']) ? (int)$_POST[$k . '_number'] : null;
                $ans_stmt->execute([$saisie_id, $k, $v, $extra_text, $extra_number]);
            }

            // patients: structure in POST: patients[date][] ...
            if (!empty($_POST['patients']) && is_array($_POST['patients'])) {
                $get_saisie_dates = $pdo->prepare('SELECT id, garde_id, garde_date FROM garde_saisie_dates WHERE garde_saisie_id = ?');
                $get_saisie_dates->execute([$saisie_id]);
                $saisie_dates = $get_saisie_dates->fetchAll();
                // map by garde_date for simplicity
                $map = [];
                foreach ($saisie_dates as $sd) $map[$sd['garde_date']] = $sd['id'];

                $pstmt = $pdo->prepare('INSERT INTO garde_patients (garde_saisie_date_id, age_months, age_years, commune, provenance, provenance_autre, orientation, orientation_autre) VALUES (?, ?, ?, ?, ?, ?, ?, ?);
                foreach ($_POST['patients'] as $date => $patients_for_date) {
                    if (!isset($map[$date])) continue;
                    $garde_saisie_date_id = $map[$date];
                    foreach ($patients_for_date as $p) {
                        $age_type = $p['age_type'] ?? '';
                        $age_value = isset($p['age_value']) ? (int)$p['age_value'] : null;
                        $age_months = null; $age_years = null;
                        if ($age_type === 'mois') $age_months = $age_value;
                        else if ($age_type === 'ans') $age_years = $age_value;
                        $pstmt->execute([$garde_saisie_date_id, $age_months, $age_years, $p['commune'] ?? null, $p['provenance'] ?? 'autre', $p['provenance_autre'] ?? null, $p['orientation'] ?? 'retour_domicile', $p['orientation_autre'] ?? null]);
                    }
                }
            }

            $pdo->commit();
            $success = 'Saisie enregistrée et gardes marquées comme saisies.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Erreur serveur: ' . $e->getMessage();
        }
    }
}

?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Saisie de garde - GardeMK</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="/assets/app.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>
<div class="container py-4">
  <h1>Saisie de garde</h1>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="alert alert-danger"><ul><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div>
  <?php endif; ?>

  <form method="post" id="saisieForm">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

    <div class="mb-3">
      <label class="form-label">Sélectionnez les dates de garde (max 7)</label>
      <div class="row">
        <?php foreach ($past as $p): ?>
          <div class="col-6 col-md-4 mb-2">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="garde_ids[]" value="<?= e($p['id']) ?>" id="garde-<?= e($p['id']) ?>">
              <label class="form-check-label" for="garde-<?= e($p['id']) ?>"><?= e($p['garde_date']) ?></label>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Avez-vous des patients ?</label>
      <select id="hasPatients" class="form-select" name="has_patients">
        <option value="">-- Sélectionner --</option>
        <option value="0">Non</option>
        <option value="1">Oui</option>
      </select>
    </div>

    <div id="patientsContainer" style="display:none;">
      <p class="text-muted">Ajoutez les patients par date. Utilisez le bouton correspondant à chaque date sélectionnée.</p>
      <div id="dynamicPatients"></div>
    </div>

    <hr>
    <h5>Questions</h5>
    <?php
    $qLabels = [
      'fiche_liaison' => 'Avez-vous eu une fiche de liaison pour tous les suivis de soins ?',
      'rdv_non_honores' => 'Avez-vous eu des rendez-vous non honorés ?',
      'incidents_reglement' => 'Avez-vous eu des incidents de règlement ?',
      'agression' => 'Avez vous été victime d\'agression, verbale ou physique ?',
      'suspicion_maltraitance' => 'Avez-vous constaté une suspicion de maltraitance sur un bébé ?',
      'pec_hors_kine' => 'Avez vous été sollicité pour une PEC hors kiné respiratoire au cabinet ?',
      'pec_exterieur' => 'Avez vous été sollicité pour une ou plusieurs PEC à domicile, en EHPAD, en milieu hospitalier etc... ?'
    ];
    foreach ($qLabels as $key => $label): ?>
      <div class="mb-3 question-block" data-key="<?= e($key) ?>">
        <label class="form-label"><?= e($label) ?></label>
        <select name="<?= e($key) ?>" class="form-select question-select">
          <option value="">-- Sélectionner --</option>
          <option value="1">Oui</option>
          <option value="0">Non</option>
        </select>
        <input type="text" name="<?= e($key) ?>_text" class="form-control mt-2 extra-text" placeholder="Précisions (optionnel)" style="display:none;">
        <input type="number" name="<?= e($key) ?>_number" class="form-control mt-2 extra-number" placeholder="Nombre (optionnel)" style="display:none;">
      </div>
    <?php endforeach; ?>

    <div class="mb-3">
      <label class="form-label">Notes (optionnel)</label>
      <textarea name="notes" class="form-control" rows="3"></textarea>
    </div>

    <button class="btn btn-success" type="submit">Valider la saisie</button>
  </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="/assets/app.js"></script>
</body>
</html>
