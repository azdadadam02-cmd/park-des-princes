<?php
require_once __DIR__ . '/_garde.php';

$st = $pdo->prepare(
    "SELECT j.id_jeu, j.nom, j.etat, j.capacite, j.tarif, j.duree_session, c.nom_categorie, j.img_jeu
     FROM JEU j
     JOIN CATEGORIE c ON c.id_categorie = j.id_categorie
     WHERE j.email_responsable = ?
     ORDER BY j.nom"
);
$st->execute([$email_resp]);
$jeux = $st->fetchAll();

$nb_actifs = count(array_filter($jeux, fn($j) => classe_etat_jeu($j['etat']) === 'is-ok'));

entete("Mes jeux", false);
?>

<!-- ===== En-tête : titre + statistiques ===== -->
<div class="row align-center justify-between">
    <div class="col-12 col-md">
        <h1 class="page-title">Gestion de vos attractions</h1>
        <p class="subtitle">Vue d'ensemble et état en temps réel des jeux sous votre responsabilité.</p>
    </div>

    <div class="col-12 col-md-auto">
        <div class="row">
            <div class="col-6 col-md-auto">
                <div class="stat">
                    <span class="stat-value"><?= count($jeux) ?></span>
                    <span class="stat-label">Total jeux</span>
                </div>
            </div>
            <div class="col-6 col-md-auto">
                <div class="stat">
                    <span class="stat-value text-ok"><?= $nb_actifs ?></span>
                    <span class="stat-label">Actifs</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!$jeux): ?>

    <!-- ===== Aucun jeu ===== -->
    <div class="row">
        <div class="col-12">
            <div class="empty-state">
                <span class="empty-icon">🎡</span>
                <h3>Aucun jeu attribué</h3>
                <p>Vous n'avez actuellement aucun jeu affecté à votre compte.</p>
            </div>
        </div>
    </div>

<?php else: ?>

    <!-- ===== Liste des jeux : 1 / 2 / 3 cartes par ligne ===== -->
    <div class="row">
        <?php foreach ($jeux as $jeu):
            $classe = classe_etat_jeu($jeu['etat']);
            $texte  = ucfirst_fr(mb_strtolower(trim($jeu['etat'])));
        ?>
        <div class="col-12 col-md-6 col-lg-4">
            <article class="card card-flush card-hover h-100">

                <!-- Image du jeu (affichée seulement si img_jeu est rempli en base) -->
                <?php if (!empty($jeu['img_jeu'])): ?>
                    <img src="../<?= h($jeu['img_jeu']) ?>" alt="<?= h($jeu['nom']) ?>" class="game-img">
                <?php endif; ?>

                <div class="card-section">
                    <div class="row align-center justify-between">
                        <div class="col-auto">
                            <span class="badge badge--primary"><?= h($jeu['nom_categorie']) ?></span>
                        </div>
                        <div class="col-auto">
                            <span class="status <?= $classe ?>"><?= h($texte) ?></span>
                        </div>
                    </div>
                </div>

                <div class="card-section grow">
                    <h3 class="game-title"><?= h($jeu['nom']) ?></h3>
                    <div class="row">
                        <div class="col-6">
                            <span class="metric-label">Capacité</span>
                            <span class="metric-value">👥 <?= (int)$jeu['capacite'] ?> pers.</span>
                        </div>
                        <div class="col-6">
                            <span class="metric-label">Durée</span>
                            <span class="metric-value">⏱️ <?= (int)$jeu['duree_session'] ?> min</span>
                        </div>
                    </div>
                </div>

                <div class="card-section">
                    <div class="row align-center justify-between">
                        <div class="col-auto">
                            <span class="price"><strong><?= number_format($jeu['tarif'], 2) ?></strong> DH</span>
                        </div>
                        <div class="col-auto">
                            <a href="jeu.php?id=<?= (int)$jeu['id_jeu'] ?>" class="btn btn-outline btn-sm">Gérer &amp; Détails →</a>
                        </div>
                    </div>
                </div>

            </article>
        </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

<?php pied(); ?>
