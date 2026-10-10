<?php
require_once __DIR__ . '/_garde.php';

/* ---------- 1. Jeu (uniquement s'il est affecté à ce responsable) ---------- */
$id  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$jeu = $id ? jeu_du_responsable($pdo, $id, $email_resp) : null;

if (!$jeu) {
    http_response_code(403);
    die("Jeu introuvable ou non affecté à votre compte.");
}

/* ---------- 1b. Clôturer une panne : dépense + jeu de nouveau disponible ---------- */
$erreur_cloture = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cloturer') {
    $id_m_brut = $_POST['id_maintenance'] ?? null;
    $id_m = is_scalar($id_m_brut) ? filter_var($id_m_brut, FILTER_VALIDATE_INT) : false;
    $cout_brut = $_POST['cout'] ?? '';
    $cout = is_string($cout_brut) ? str_replace(',', '.', trim($cout_brut)) : '';
    $date_brute = $_POST['date_prochaine_maintenance'] ?? '';
    $date_prochaine = is_string($date_brute) ? trim($date_brute) : '';

    if (!csrf_ok('cloturer_maintenance')) {
        $erreur_cloture = "Le formulaire a expiré. Rechargez la page avant de réessayer.";
    } elseif (!$id_m || !is_numeric($cout) || (float)$cout <= 0) {
        $erreur_cloture = "Entrez un coût valide supérieur à 0.";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_prochaine)
        || !($date_prochaine_dt = DateTime::createFromFormat('!Y-m-d', $date_prochaine))
        || $date_prochaine_dt->format('Y-m-d') !== $date_prochaine
        || $date_prochaine < date('Y-m-d')) {
        $erreur_cloture = "Indiquez une date de prochaine maintenance valide, aujourd'hui ou ultérieure.";
    } else {
        $cout = (float)$cout;
        try {
            $pdo->beginTransaction();

            // L'intervention doit appartenir à CE jeu et ne pas être déjà terminée
            $st = $pdo->prepare(
                "SELECT description FROM MAINTENANCE
                 WHERE id_maintenance = ? AND id_jeu = ? AND COALESCE(etat_intervention,'') <> 'Terminée'
                 FOR UPDATE"
            );
            $st->execute([$id_m, $id]);
            $m_ouverte = $st->fetch();

            if (!$m_ouverte) {
                $pdo->rollBack();
                $erreur_cloture = "Cette intervention n'existe pas ou est déjà terminée.";
            } else {
                // 1) Intervention terminée, coût et prochaine date planifiée.
                $st = $pdo->prepare(
                    "UPDATE MAINTENANCE
                     SET etat_intervention = 'Terminée', cout = ?, date_prochaine_maintenance = ?
                     WHERE id_maintenance = ? AND id_jeu = ?"
                );
                $st->execute([$cout, $date_prochaine, $id_m, $id]);
                if ($st->rowCount() !== 1) {
                    throw new RuntimeException("L'intervention n'a pas pu être mise à jour.");
                }

                // 2) Dépense liée au jeu
                $pdo->prepare(
                    "INSERT INTO DEPENSE (categorie_depense, description_depense, montant_depense, date_depense, type_depense, email_utilisateur, id_jeu)
                     VALUES ('Maintenance', ?, ?, CURDATE(), 'Réparation', ?, ?)"
                )->execute([$m_ouverte['description'], $cout, $email_resp, $id]);

                // 3) Si plus aucune intervention ouverte -> le jeu redevient disponible
                $st = $pdo->prepare(
                    "SELECT COUNT(*) FROM MAINTENANCE
                     WHERE id_jeu = ? AND COALESCE(etat_intervention,'') <> 'Terminée'"
                );
                $st->execute([$id]);
                if ((int)$st->fetchColumn() === 0) {
                    $pdo->prepare("UPDATE JEU SET etat = 'disponible' WHERE id_jeu = ? AND email_responsable = ?")
                        ->execute([$id, $email_resp]);
                }

                $pdo->commit();
                header("Location: jeu.php?id=" . $id . "&ok=1");
                exit;
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('Erreur de clôture de maintenance : ' . $e->getMessage());
            $erreur_cloture = "Une erreur est survenue pendant la clôture. Aucune modification n'a été conservée.";
        }
    }
}

/* ---------- 2. 10 dernières interventions de maintenance ---------- */
$st = $pdo->prepare(
    "SELECT * FROM MAINTENANCE
     WHERE id_jeu = ?
     ORDER BY date_intervention DESC, id_maintenance DESC
     LIMIT 10"
);
$st->execute([$id]);
$maint = $st->fetchAll();

/* ---------- 3. Badge d'état ---------- */
$etat_brut   = trim((string)($jeu['etat'] ?? ''));
$etat_classe = classe_etat_jeu($etat_brut);
$etat_texte  = $etat_brut !== '' ? ucfirst_fr(mb_strtolower($etat_brut)) : 'Non défini';

/* ---------- 4. Les 6 boîtes d'information ---------- */
$age = $jeu['age_min'] ?? null;
$infos = [
    'Catégorie'       => '🎪 ' . ($jeu['nom_categorie'] ?? 'N/A'),
    'Âge minimum'     => '🔞 ' . (($age !== null && $age !== '') ? $age . ' ans' : 'Tous âges'),
    'Capacité'        => '👥 ' . $jeu['capacite'] . ' pers.',
    'Durée session'   => '⏱️ ' . $jeu['duree_session'] . ' min',
    'Tarif'           => '💳 ' . number_format($jeu['tarif'], 2) . ' DH',
    'Mise en service' => '📅 ' . ($jeu['date_mise_service'] ?? 'N/A'),
];

entete("Détails - " . $jeu['nom']);
?>

<!-- ===== Retour ===== -->
<div class="row">
    <div class="col-12">
        <a href="index.php" class="btn btn-ghost btn-back">← Retour à mes jeux</a>
    </div>
</div>

<!-- ===== Fiche du jeu ===== -->
<div class="row">
    <div class="col-12">
        <article class="card card-lg">

            <!-- Titre + état -->
            <div class="sep-bottom">
                <div class="row align-center justify-between">
                    <div class="col-12 col-sm">
                        <h2 class="jeu-title"><?= h($jeu['nom']) ?></h2>
                    </div>
                    <div class="col-12 col-sm-auto">
                        <span class="badge badge--dot <?= $etat_classe ?>"><?= h($etat_texte) ?></span>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div class="row">
                <div class="col-12">
                    <div class="box">
                        <span class="box-label">Description</span>
                        <p class="box-text"><?= nl2br(h($jeu['description'] ?? 'Aucune description disponible.')) ?></p>
                    </div>
                </div>
            </div>

            <!-- Infos : 1 / 2 / 3 boîtes par ligne -->
            <div class="row">
                <?php foreach ($infos as $label => $valeur): ?>
                    <div class="col-12 col-sm-6 col-md-4">
                        <div class="box">
                            <span class="box-label"><?= h($label) ?></span>
                            <span class="box-value"><?= h($valeur) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Actions -->
            

        </article>
    </div>
</div>

<!-- ===== Dernières interventions ===== -->
<div class="row">
    <div class="col-12">
        <section class="card">
            <h2 class="card-title">Dernières interventions</h2>

            <?php if (isset($_GET['ok'])): ?>
                <div class="alert alert-success">✅ Intervention terminée, dépense enregistrée.</div>
            <?php endif; ?>
            <?php if ($erreur_cloture): ?>
                <div class="alert alert-danger">❌ <?= h($erreur_cloture) ?></div>
            <?php endif; ?>

            <div class="table-wrap is-bare">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>État</th>
                            <th>Description</th>
                            <th>Prochaine</th>
                            <th>Coût / Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($maint as $m): ?>
                            <tr>
                                <td><?= h($m['date_intervention']) ?></td>
                                <td><?= h($m['type_intervention']) ?></td>
                                <td><?= h($m['etat_intervention']) ?></td>
                                <td><?= h($m['description']) ?></td>
                                <td><?= h($m['date_prochaine_maintenance'] ?? '-') ?></td>
                                <td>
                                    <?php if (mb_strtolower(trim((string)$m['etat_intervention'])) !== 'terminée'): ?>
                                        <!-- Panne encore ouverte : on saisit le coût -> dépense + jeu disponible -->
                                        <form method="post">
                                            <?= csrf_champ('cloturer_maintenance') ?>
                                            <input type="hidden" name="action" value="cloturer">
                                            <input type="hidden" name="id_maintenance" value="<?= (int)$m['id_maintenance'] ?>">
                                            <input type="number" name="cout" class="form-control" style="width:110px;display:inline-block" min="0.01" step="0.01" placeholder="Coût DH" required>
                                            <input type="date" name="date_prochaine_maintenance" class="form-control" style="width:180px;display:inline-block" min="<?= h(date('Y-m-d')) ?>" value="<?= h((string)($m['date_prochaine_maintenance'] ?? '')) ?>" required aria-label="Date de prochaine maintenance">
                                            <button type="submit" class="btn btn-primary btn-sm">✅ Terminer</button>
                                        </form>
                                    <?php else: ?>
                                        <?= number_format((float)$m['cout'], 2) ?> DH
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$maint): ?>
                            <tr><td colspan="6" class="empty">Aucune intervention enregistrée.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<?php pied(); ?>
