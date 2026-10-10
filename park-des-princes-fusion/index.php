<?php
// ============================================================
// PAGE "MES JEUX" (responsables/index.php)
// Kat-affichi l-jeux li m3yenin l had responsable.
// ============================================================

// Kan-jibo _garde.php: howa li kay-verifié bli l-user mconnecté w role dyalo responsable,
// w kay3tina $pdo (connexion DB) w $email_resp (email dyal responsable).
require_once __DIR__ . '/_garde.php';

// Kan-heyyio requête SQL (prepare = sécurisé contre SQL injection).
// Kan-jibo l-jeux li email_responsable dyalhom = email dyal had responsable.
// JOIN CATEGORIE bach njibo smiya dyal catégorie (nom_categorie) b'7al "Manège".
$st = $pdo->prepare(
    "SELECT j.id_jeu, j.nom, j.etat, j.capacite, j.tarif, j.duree_session, c.nom_categorie
     FROM JEU j
     JOIN CATEGORIE c ON c.id_categorie = j.id_categorie
     WHERE j.email_responsable = ?
     ORDER BY j.nom"
);
// Kan-ba3tou l-email f blast l-point d'interrogation (?)
$st->execute([$email_resp]);
// Kan-jibo ga3 l-ntaij f tableau $jeux (kol élément = jeu wa7ed)
$jeux = $st->fetchAll();

// Kan-7sbo ch7al men jeu "actif": kan-filtriw $jeux w kan-3eddo
// ghir li classe_etat_jeu() radd lihom 'is-ok' (disponible / en fonctionnement)
$nb_actifs = count(array_filter($jeux, fn($j) => classe_etat_jeu($j['etat']) === 'is-ok'));

// Kan-affichiw l-header dyal l-page (logo + menu). false = ma-n3awdouch titre h1 (7it 3ndna titre mn te7t)
entete("Mes jeux", false);
?>

<!-- ===== En-tête : titre + statistiques ===== -->
<!-- .row = ster flex; align-center = tawsit 3amoudi; justify-between = bo3d bin l-3anasir -->
<div class="row align-center justify-between">
    <!-- Colonne dyal titre: 12/12 f mobile, kayakhod l-blasa li b9at f PC -->
    <div class="col-12 col-md">
        <h1 class="page-title">Gestion de vos attractions</h1>
        <p class="subtitle">Vue d'ensemble et état en temps réel des jeux sous votre responsabilité.</p>
    </div>

    <!-- Colonne dyal les statistiques (3ard 3la 7asab l-contenu f PC) -->
    <div class="col-12 col-md-auto">
        <div class="row">
            <!-- Stat 1: Total des jeux -->
            <div class="col-6 col-md-auto">
                <div class="stat">
                    <!-- count($jeux) = 3add l-jeux kamlin -->
                    <span class="stat-value"><?= count($jeux) ?></span>
                    <span class="stat-label">Total jeux</span>
                </div>
            </div>
            <!-- Stat 2: Jeux actifs (text-ok = lon khder) -->
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

    <!-- ===== Aucun jeu: ytban ghir ila $jeux khawi ===== -->
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
        <?php foreach ($jeux as $jeu): // Boucle: kol jeu = carte wa7da
            // $classe = couleur dyal l-état (is-ok khder / is-warn sfer / is-danger 7mer)
            $classe = classe_etat_jeu($jeu['etat']);
            // $texte = l-état b 7arf lwl kbir (ex: "disponible" -> "Disponible")
            $texte  = ucfirst_fr(mb_strtolower(trim($jeu['etat'])));
        ?>
        <!-- col-12 = 1 carte f mobile | col-md-6 = 2 f tablette | col-lg-4 = 3 f PC -->
        <div class="col-12 col-md-6 col-lg-4">
            <!-- card-hover = effet mni t7ett l-souris | h-100 = kol l-cartes nafs l-3ali -->
            <article class="card card-flush card-hover h-100">

                <!-- Partie 1 de la carte: badges (catégorie à gauche, état à droite) -->
                <div class="card-section">
                    <div class="row align-center justify-between">
                        <div class="col-auto">
                            <!-- h() = htmlspecialchars: kat-7mi mn XSS -->
                            <span class="badge badge--primary"><?= h($jeu['nom_categorie']) ?></span>
                        </div>
                        <div class="col-auto">
                            <!-- Badge l-état, l-couleur katbeddel 7asab $classe -->
                            <span class="status <?= $classe ?>"><?= h($texte) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Partie 2: nom du jeu + capacité + durée (grow = kat-akhod l-blasa l-ba9ya) -->
                <div class="card-section grow">
                    <h3 class="game-title"><?= h($jeu['nom']) ?></h3>
                    <div class="row">
                        <div class="col-6">
                            <span class="metric-label">Capacité</span>
                            <!-- (int) = n-ta2kdo bli rqm s7i7 -->
                            <span class="metric-value">👥 <?= (int)$jeu['capacite'] ?> pers.</span>
                        </div>
                        <div class="col-6">
                            <span class="metric-label">Durée</span>
                            <span class="metric-value">⏱️ <?= (int)$jeu['duree_session'] ?> min</span>
                        </div>
                    </div>
                </div>

                <!-- Partie 3: tarif + bouton -->
                <div class="card-section">
                    <div class="row align-center justify-between">
                        <div class="col-auto">
                            <!-- number_format(.., 2) = 2 arqam ba3d l-fasila (ex: 40.00) -->
                            <span class="price"><strong><?= number_format($jeu['tarif'], 2) ?></strong> DH</span>
                        </div>
                        <div class="col-auto">
                            <!-- Lien l jeu.php w kan-ba3tou id dyal jeu f l-URL (?id=...) -->
                            <a href="jeu.php?id=<?= (int)$jeu['id_jeu'] ?>" class="btn btn-outline btn-sm">Gérer &amp; Détails →</a>
                        </div>
                    </div>
                </div>

            </article>
        </div>
        <?php endforeach; // Nihayat l-boucle ?>
    </div>

<?php endif; // Nihayat if (!$jeux) ?>

<?php
// Kan-sedo l-page: </main></body></html> (pied() men _garde.php)
pied();
?>
