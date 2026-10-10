<?php
require_once __DIR__ . '/_garde.php';

$success = false;
$error   = null;

$mes_jeux = mes_jeux($pdo, $email_resp);
$id_jeu_form = '';
$description_form = '';
$urgence_form = 'Moyenne';
$date_prochaine_form = '';

/* ---------- Traitement du formulaire ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_jeu_brut = $_POST['id_jeu'] ?? '';
    $id_jeu_form = is_scalar($id_jeu_brut) ? (string)$id_jeu_brut : '';
    $id_jeu = filter_var($id_jeu_form, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $description_brute = $_POST['description'] ?? '';
    $description_form = is_string($description_brute) ? trim($description_brute) : '';
    $urgence_brute = $_POST['degre_urgence'] ?? '';
    $urgence_form = is_string($urgence_brute) ? $urgence_brute : '';
    $date_brute = $_POST['date_prochaine_maintenance'] ?? '';
    $date_prochaine_form = is_string($date_brute) ? trim($date_brute) : '';

    if (!csrf_ok('signaler_panne')) {
        $error = "Le formulaire a expiré. Rechargez la page avant de réessayer.";
    } elseif ($id_jeu === false || $description_form === '') {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif (!in_array($urgence_form, ['Faible', 'Moyenne', 'Élevée'], true)) {
        $error = "Le niveau d'urgence sélectionné est invalide.";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_prochaine_form)
        || !($date_prochaine = DateTime::createFromFormat('!Y-m-d', $date_prochaine_form))
        || $date_prochaine->format('Y-m-d') !== $date_prochaine_form
        || $date_prochaine_form < date('Y-m-d')) {
        $error = "Indiquez une date de prochaine maintenance valide, aujourd'hui ou ultérieure.";
    } elseif (!jeu_du_responsable($pdo, $id_jeu, $email_resp)) {
        $error = "Ce jeu n'est pas affecté à votre compte.";
    } else {
        try {
            $pdo->beginTransaction();
            $st_verif = $pdo->prepare(
                "SELECT id_jeu FROM JEU
                 WHERE id_jeu = ? AND email_responsable = ?
                 FOR UPDATE"
            );
            $st_verif->execute([$id_jeu, $email_resp]);
            if (!$st_verif->fetch()) {
                throw new RuntimeException("Ce jeu n'est plus affecté à votre compte.");
            }

            $st_insert = $pdo->prepare(
                "INSERT INTO MAINTENANCE
                    (type_intervention, description, date_intervention, date_prochaine_maintenance, etat_intervention, id_jeu, email_responsable)
                 VALUES (?, ?, CURDATE(), ?, 'En attente', ?, ?)"
            );
            $st_insert->execute(["Panne - urgence " . $urgence_form, $description_form, $date_prochaine_form, $id_jeu, $email_resp]);

            $st_update = $pdo->prepare("UPDATE JEU SET etat = 'En maintenance' WHERE id_jeu = ? AND email_responsable = ?");
            $st_update->execute([$id_jeu, $email_resp]);

            $pdo->commit();
            $success = true;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Erreur de signalement de panne : ' . $e->getMessage());
            $error = "Une erreur est survenue lors de l'enregistrement du signalement.";
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

/* ---------- Niveaux d'urgence : valeur => [id, classe couleur, libellé] ---------- */
$urgences = [
    'Faible'  => ['u_faible',  'is-ok',     '🟢 Faible'],
    'Moyenne' => ['u_moyenne', 'is-warn',   '🟡 Moyenne'],
    'Élevée'  => ['u_elevee',  'is-danger', '🔴 Élevée'],
];

entete("Signaler une panne");
?>

<div class="row justify-center">
    <div class=" col-lg-7">
        <div class="card card-lg">

            <!-- En-tête de la carte -->
            <div class="row">
                <div class="col-12 text-center">
                    <h2>Signaler un incident technique</h2>
                    <p class="subtitle">Déclarez une panne sur l'une de vos attractions pour informer la maintenance.</p>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($success): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-success">✅ La panne a été signalée avec succès. L'attraction est passée en maintenance.</div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-danger">❌ <?= h($error) ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Formulaire -->
            <form action="" method="post">
                <?= csrf_champ('signaler_panne') ?>

                <div class="row">
                    <div class="col-12">
                        <label class="form-label" for="id_jeu">Attraction concernée *</label>
                        <select name="id_jeu" id="id_jeu" class="form-control" required>
                            <option value="" disabled <?= $id_jeu_form === '' ? 'selected' : '' ?>>Sélectionnez une attraction</option>
                            <?php foreach ($mes_jeux as $j): ?>
                                <option value="<?= (int)$j['id_jeu'] ?>" <?= (string)$j['id_jeu'] === $id_jeu_form ? 'selected' : '' ?>><?= h($j['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row" role="radiogroup" aria-labelledby="lbl-urgence">
                    <div class="col-12">
                        <span class="form-label" id="lbl-urgence">Niveau d'urgence</span>
                    </div>
                    <?php foreach ($urgences as $valeur => [$id_radio, $classe, $libelle]): ?>
                        <div class="col-4">
                            <div class="choice <?= $classe ?>">
                                <input type="radio" name="degre_urgence" id="<?= $id_radio ?>"
                                       value="<?= h($valeur) ?>" <?= $valeur === $urgence_form ? 'checked' : '' ?>>
                                <label for="<?= $id_radio ?>" class="choice-label"><?= h($libelle) ?></label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="row">
                    <div class="col-12">
                        <label class="form-label" for="description">Description du problème *</label>
                        <textarea name="description" id="description" class="form-control"
                                  placeholder="Décrivez le problème..." required><?= h($description_form) ?></textarea>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <label class="form-label" for="date_prochaine_maintenance">Date de prochaine maintenance *</label>
                        <input type="date" name="date_prochaine_maintenance" id="date_prochaine_maintenance"
                               class="form-control" min="<?= h(date('Y-m-d')) ?>"
                               value="<?= h($date_prochaine_form) ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-block"> Envoyer le signalement</button>
                    </div>
                </div>

            </form>

        </div>
    </div>
</div>

<?php pied(); ?>
