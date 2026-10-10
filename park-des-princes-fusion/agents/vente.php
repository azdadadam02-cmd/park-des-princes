<?php
// ============================================================
// NOUVELLE VENTE DE BILLETS
// - le montant est TOUJOURS recalculé côté serveur (le JS n'est qu'un aperçu)
// - COMMANDE + BILLET(s) + contient sont créés dans UNE transaction
// - un jeton à usage unique empêche le double envoi (F5, double clic)
// ============================================================
require_once __DIR__ . '/_garde.php';

$types = types_billets();

// Seuls les jeux « Disponible » peuvent être vendus (la disponibilité est revérifiée à l'enregistrement)
$jeux = $pdo->query(
    "SELECT id_jeu, nom, capacite, age_min FROM jeu
     WHERE etat = 'Disponible'
     ORDER BY nom"
)->fetchAll();
$ids_jeux_ok = array_map('intval', array_column($jeux, 'id_jeu'));

// Clients existants (sans le compte "visiteur de passage")
$st = $pdo->prepare(
    "SELECT c.email, u.nom, u.prenom, u.telephone
     FROM client c JOIN utilisateur u ON u.email = c.email
     WHERE c.email <> ? ORDER BY u.nom, u.prenom LIMIT 500"
);
$st->execute([EMAIL_VISITEUR]);
$clients = $st->fetchAll();

$erreurs = [];

// Valeurs du formulaire (pour le réafficher en cas d'erreur)
$f = [
    'mode_client'   => 'nouveau',
    'client_email'  => '',
    'n_nom'         => '',
    'n_prenom'      => '',
    'n_tel'         => '',
    'n_email'       => '',
    'age'           => '',
    'qte'           => [],
    'jeux'          => [],
];

/* =========================================================
   Traitement du formulaire
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $f['mode_client']  = in_array($_POST['mode_client'] ?? '', ['existant', 'nouveau'], true)
                         ? $_POST['mode_client'] : 'nouveau';
    $f['client_email'] = trim((string)($_POST['client_email'] ?? ''));
    $f['n_nom']        = trim((string)($_POST['n_nom'] ?? ''));
    $f['n_prenom']     = trim((string)($_POST['n_prenom'] ?? ''));
    $f['n_tel']        = trim((string)($_POST['n_tel'] ?? ''));
    $f['n_email']      = trim((string)($_POST['n_email'] ?? ''));
    $f['age']          = trim((string)($_POST['age'] ?? ''));

    if (!csrf_ok('vente')) {
        $erreurs[] = "Ce formulaire a expiré ou a déjà été envoyé. Vérifiez la liste des ventes avant de recommencer.";
    } else {

        /* ----- 1. Billets demandés ----- */
        $qtes = is_array($_POST['qte'] ?? null) ? $_POST['qte'] : [];
        $lignes = [];   // [ [type, quantité], ... ]
        foreach ($types as $t) {
            $brut = trim((string)($qtes[$t['id_type']] ?? ''));
            $f['qte'][$t['id_type']] = $brut;
            if ($brut === '') continue;
            $q = filter_var($brut, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => MAX_PAR_LIGNE]]);
            if ($q === false) {
                $erreurs[] = "Quantité invalide pour « {$t['libelle']} » (0 à " . MAX_PAR_LIGNE . ").";
            } elseif ($q > 0) {
                $lignes[] = [$t, $q];
            }
        }
        if (!$lignes && !$erreurs) {
            $erreurs[] = "Ajoutez au moins un billet à la vente.";
        }

        /* ----- 3. Jeux (obligatoire, doivent être « Disponible ») ----- */
        $ids_jeux = [];
        foreach ((array)($_POST['jeux'] ?? []) as $id) {
            $id = filter_var($id, FILTER_VALIDATE_INT);
            if ($id !== false && in_array($id, $ids_jeux_ok, true)) $ids_jeux[$id] = $id;
        }
        $ids_jeux = array_values($ids_jeux);
        $f['jeux'] = $ids_jeux;
        if (!$ids_jeux) $erreurs[] = "Sélectionnez au moins un jeu disponible.";

        /* ----- 3b. Nombre de joueurs et âge par rapport aux jeux choisis ----- */
        $nb_joueurs = 0;
        foreach ($lignes as [$t, $q]) $nb_joueurs += $q;
        $age = filter_var($f['age'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 120]]);
        if ($age === false) $erreurs[] = "Indiquez l'âge du plus jeune joueur.";
        foreach ($jeux as $j) {
            if (!in_array((int)$j['id_jeu'], $ids_jeux, true)) continue;
            if ($j['capacite'] !== null && $nb_joueurs > (int)$j['capacite']) {
                $erreurs[] = "« {$j['nom']} » : {$j['capacite']} joueurs maximum (vous en avez saisi $nb_joueurs).";
            }
            if ($age !== false && $j['age_min'] !== null && $age < (int)$j['age_min']) {
                $erreurs[] = "« {$j['nom']} » : âge minimum {$j['age_min']} ans.";
            }
        }

        /* ----- 4. Client (nom, prénom et téléphone à 10 chiffres obligatoires) ----- */
        $email_client = null;
        $creer_client = false;

        if ($f['mode_client'] === 'existant') {
            $st = $pdo->prepare("SELECT u.nom, u.prenom, u.telephone, u.email FROM client c JOIN utilisateur u ON u.email = c.email WHERE c.email = ?");
            $st->execute([$f['client_email']]);
            $ex = $f['client_email'] === '' ? false : $st->fetch();
            if (!$ex) {
                $erreurs[] = "Client introuvable. Sélectionnez un client dans la liste ou créez-en un.";
            } else {
                $err_ex = [];
                if (trim($ex['nom']) === '' || trim($ex['prenom']) === '') $err_ex[] = 'nom ou prénom vide';
                if (telephone_valide($ex['telephone']) === '') $err_ex[] = 'téléphone invalide';
                if ($err_ex) {
                    $erreurs[] = "Fiche du client incomplète (" . implode(', ', $err_ex) . ") : créez un nouveau client avec les bonnes informations.";
                } else {
                    $email_client = $f['client_email'];
                }
            }

        } else {
            if ($f['n_nom'] === '' || $f['n_prenom'] === '') {
                $erreurs[] = "Le nom et le prénom du client sont obligatoires.";
            } elseif (!preg_match("/^[a-zA-ZÀ-ÿ\s'-]{2,50}$/u", $f['n_nom'])
                   || !preg_match("/^[a-zA-ZÀ-ÿ\s'-]{2,50}$/u", $f['n_prenom'])) {
                $erreurs[] = "Nom et prénom : 2 à 50 lettres uniquement.";
            }
            if (telephone_valide($f['n_tel']) === '') {
                $erreurs[] = "Numéro de téléphone invalide : 10 chiffres attendus.";
            }
            if ($f['n_email'] !== '') {
                if (!filter_var($f['n_email'], FILTER_VALIDATE_EMAIL) || strlen($f['n_email']) > 100) {
                    $erreurs[] = "Adresse e-mail invalide.";
                } else {
                    $st = $pdo->prepare("SELECT 1 FROM utilisateur WHERE email = ?");
                    $st->execute([$f['n_email']]);
                    if ($st->fetch()) $erreurs[] = "Cet e-mail existe déjà : choisissez « Client existant ».";
                }
            }
            if (!$erreurs) {
                // Sans e-mail : on génère une adresse interne (le client ne pourra pas se connecter)
                $email_client = $f['n_email'] !== '' ? $f['n_email'] : 'client.' . bin2hex(random_bytes(5)) . '@parc.local';
                $creer_client = true;
            }
        }

        /* ----- 5. Enregistrement (transaction) ----- */
        if (!$erreurs) {
            try {
                $pdo->beginTransaction();

                // 5a. Disponibilité des jeux, revérifiée avec verrou au moment de l'enregistrement
                $marques = implode(',', array_fill(0, count($ids_jeux), '?'));
                $st = $pdo->prepare("SELECT id_jeu, nom FROM jeu WHERE id_jeu IN ($marques) AND etat = 'Disponible' FOR UPDATE");
                $st->execute($ids_jeux);
                if (count($st->fetchAll()) !== count($ids_jeux)) {
                    throw new DomainException("Un des jeux sélectionnés n'est plus disponible. Rechargez la page.");
                }

                // 5b. Client
                if ($creer_client) {
                    $pdo->prepare("INSERT INTO utilisateur (email, mot_passe, nom, prenom, telephone, role)
                                   +'CLIENT')")
                        ->execute([$email_client,
                                   password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT),
                                   $f['n_nom'], $f['n_prenom'], telephone_valide($f['n_tel'])]);
                    $pdo->prepare("INSERT INTO client (email, date_inscription) VALUES (?, CURDATE())")
                        ->execute([$email_client]);
                }

                // 5c. Montant total (en centimes pour éviter les erreurs d'arrondi)
                $total_cts = 0;
                foreach ($lignes as [$t, $q]) {
                    $total_cts += (int)round($t['prix'] * 100) * $q;
                }
                $total = $total_cts / 100;
                $paye  = true;   // la validation de la vente vaut paiement (pas de choix du mode)

                // 5d. Réservation du jour liée à la vente directe
                $pdo->prepare(
                    "INSERT INTO reservation
                        (date_visite, nb_personnes, montant, statut_reservation, email_client, email_agent, origine)
                     VALUES (NOW(), ?, ?, ?, ?, ?, 'guichet')"
                )->execute([$nb_joueurs, $total, $paye ? 'payée' : 'confirmée', $email_client, $email_agent]);
                $num_reservation = (int)$pdo->lastInsertId();

                $ins_choisir = $pdo->prepare("INSERT INTO choisir (num_reservation, id_jeu) VALUES (?, ?)");
                foreach ($ids_jeux as $id_jeu) {
                    $ins_choisir->execute([$num_reservation, $id_jeu]);
                }

                // 5e. Commande liée à la réservation
                $pdo->prepare("INSERT INTO commande
                                   (montant_total, statut_commande, email_client, email_agent, date_paiment, num_reservation)
                               VALUES (?, 'payée', ?, ?, NOW(), ?)")
                    ->execute([$total, $email_client, $email_agent, $num_reservation]);
                $num_commande = (int)$pdo->lastInsertId();

                // 5f. Un billet par personne (id_billet = identifiant unique du billet)
                $statut_billet = $paye ? 'valide' : 'en attente';
                $ins_billet = $pdo->prepare(
                    "INSERT INTO billet (date_fin, type, statut_billet, quantite, prix_unitaire, num_commande, email_agent)
                     VALUES (?, ?, ?, 1, ?, ?, ?)"
                );
                $ins_contient = $pdo->prepare("INSERT INTO contient (id_billet, id_jeu) VALUES (?, ?)");

                foreach ($lignes as [$t, $q]) {
                    $fin = (new DateTime('today'))
                            ->modify('+' . max(0, (int)$t['validite_jours'] - 1) . ' days')
                            ->setTime(23, 59, 59)->format('Y-m-d H:i:s');
                    for ($i = 0; $i < $q; $i++) {
                        $ins_billet->execute([$fin, $t['libelle'], $statut_billet, $t['prix'], $num_commande, $email_agent]);
                        $id_billet = (int)$pdo->lastInsertId();
                        foreach ($ids_jeux as $id_jeu) {
                            $ins_contient->execute([$id_billet, $id_jeu]);
                        }
                    }
                }

                $pdo->commit();

                csrf_renouveler('vente');   // le formulaire ne peut plus être renvoyé
                header('Location: ' . ($paye ? 'Billet.php?id=' . $num_commande . '&ok=1' : 'commandes.php'));
                exit;

            } catch (DomainException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $erreurs[] = $e->getMessage();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('vente : ' . $e->getMessage());
                $erreurs[] = "La vente n'a pas pu être enregistrée (aucune donnée n'a été sauvegardée). Réessayez.";
            }
        }
    }
}

entete("Nouvelle vente", false);
?>

<div class="row">
    <div class="col-12">
        <h1 class="page-title">Nouvelle vente de billets</h1>
        <p class="subtitle">Saisissez le client, les billets, les jeux disponibles. Les champs marqués * sont obligatoires ; le prix est calculé automatiquement et la validation de la commande génère le billet.</p>
    </div>
</div>

<?php if (!$types): ?>
    <div class="row"><div class="col-12">
        <div class="alert alert-warning">Aucun type de billet n'est configuré. Exécutez <code>migration_agent.sql</code> ou demandez à l'administrateur d'ajouter des tarifs.</div>
    </div></div>
<?php else: ?>

<?php if ($erreurs): ?>
<div class="row"><div class="col-12">
    <div class="alert alert-danger">
        <?php foreach ($erreurs as $e): ?><div>❌ <?= h($e) ?></div><?php endforeach; ?>
    </div>
</div></div>
<?php endif; ?>

<form method="post" id="formVente" novalidate>
    <?= csrf_champ('vente') ?>

    <div class="row">

        <!-- ================= Colonne gauche ================= -->
        <div class="col-12 col-lg-8">

            <!-- ---- Client ---- -->
            <div class="card">
                <h3 class="card-title">1. Client</h3>

                <div class="row">
                    <div class="col-12 col-md-6"><label class="choice"><input type="radio" name="mode_client" value="nouveau" <?= $f['mode_client'] === 'nouveau' ? 'checked' : '' ?>><span class="choice-label">➕ Nouveau client</span></label></div>
                    <div class="col-12 col-md-6"><label class="choice"><input type="radio" name="mode_client" value="existant" <?= $f['mode_client'] === 'existant' ? 'checked' : '' ?>><span class="choice-label">🔎 Client existant</span></label></div>
                </div>

                <div class="client-panel mt-1" data-mode="existant" hidden>
                    <label class="form-label" for="client_email">Rechercher (nom ou e-mail)</label>
                    <input type="text" name="client_email" id="client_email" class="form-control" list="liste-clients"
                           value="<?= h($f['client_email']) ?>" placeholder="Tapez pour rechercher…" autocomplete="off">
                    <datalist id="liste-clients">
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= h($c['email']) ?>"><?= h($c['nom'] . ' ' . $c['prenom'] . ($c['telephone'] ? ' — ' . $c['telephone'] : '')) ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="client-panel mt-1" data-mode="nouveau" hidden>
                    <div class="row">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="n_nom">Nom *</label>
                            <input type="text" name="n_nom" id="n_nom" class="form-control" maxlength="50" value="<?= h($f['n_nom']) ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="n_prenom">Prénom *</label>
                            <input type="text" name="n_prenom" id="n_prenom" class="form-control" maxlength="50" value="<?= h($f['n_prenom']) ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="n_tel">Téléphone (10 chiffres) *</label>
                            <input type="tel" name="n_tel" id="n_tel" class="form-control" maxlength="20" inputmode="numeric" placeholder="0612345678" value="<?= h($f['n_tel']) ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="n_email">E-mail (facultatif)</label>
                            <input type="email" name="n_email" id="n_email" class="form-control" maxlength="100" value="<?= h($f['n_email']) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ---- Billets ---- -->
            <div class="card mt-1">
                <h3 class="card-title">2. Billets</h3>
                <?php foreach ($types as $t):
                    $info = ((int)$t['validite_jours'] > 1 ? 'Valable ' . (int)$t['validite_jours'] . ' jours' : 'Valable le jour de la vente')
                          . ($t['usage_unique'] ? ' · entrée unique' : ' · entrées multiples');
                ?>
                <div class="ticket-line">
                    <div>
                        <span class="ticket-name"><?= h($t['libelle']) ?> — <?= dh($t['prix']) ?></span>
                        <span class="ticket-info"><?= h($info) ?></span>
                    </div>
                    <div class="ticket-qty">
                        <input type="number" class="form-control qte" name="qte[<?= h($t['id_type']) ?>]"
                               min="0" max="<?= MAX_PAR_LIGNE ?>" step="1" inputmode="numeric"
                               data-prix="<?= h($t['prix']) ?>" placeholder="0"
                               value="<?= h($f['qte'][$t['id_type']] ?? '') ?>" aria-label="Quantité <?= h($t['libelle']) ?>">
                        <span class="ticket-sub">0,00 DH</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ---- Jeux ---- -->
            <div class="card mt-1">
                <h3 class="card-title">3. Jeux et joueurs</h3>
                <?php if ($jeux): ?>
                <div class="check-grid">
                    <?php foreach ($jeux as $j): ?>
                        <label class="check-item">
                            <input type="checkbox" name="jeux[]" value="<?= (int)$j['id_jeu'] ?>" <?= in_array((int)$j['id_jeu'], $f['jeux'], true) ? 'checked' : '' ?>>
                            <?= h($j['nom']) ?>
                            <small class="text-muted">(<?= $j['capacite'] !== null ? (int)$j['capacite'] . ' max' : 'illimité' ?><?= $j['age_min'] ? ', ' . (int)$j['age_min'] . '+ ans' : '' ?>)</small>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                    <div class="alert alert-warning">Aucun jeu n'est actuellement « Disponible » : la vente est impossible.</div>
                <?php endif; ?>
                <div class="row mt-1">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="age">Âge du plus jeune joueur *</label>
                        <input type="number" name="age" id="age" class="form-control" min="0" max="120" step="1" value="<?= h($f['age']) ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= Colonne droite : récapitulatif ================= -->
        <div class="col-12 col-lg-4">
            <div class="card">
                <h3 class="card-title">Récapitulatif</h3>

                <div class="total-box">
                    <span class="total-label">Total à payer</span>
                    <span class="total-value" id="total">0,00 DH</span>
                    <span class="ticket-info" id="nbBillets">0 billet</span>
                </div>

                <button type="submit" class="btn btn-primary btn-block mt-1" id="btnValider" <?= $jeux ? '' : 'disabled' ?>>Valider la commande</button>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
  const form  = document.getElementById('formVente');
  const qtes  = form.querySelectorAll('.qte');
  const MAX   = <?= MAX_PAR_LIGNE ?>;
  const fmt   = v => v.toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' DH';

  // ----- Total en direct (aperçu : le serveur recalcule toujours) -----
  function recalculer() {
    let total = 0, nb = 0;
    qtes.forEach(inp => {
      let q = parseInt(inp.value, 10);
      if (isNaN(q) || q < 0) q = 0;
      if (q > MAX) q = MAX;
      const sous = q * parseFloat(inp.dataset.prix);
      inp.closest('.ticket-line').querySelector('.ticket-sub').textContent = fmt(sous);
      total += sous; nb += q;
    });
    document.getElementById('total').textContent = fmt(total);
    document.getElementById('nbBillets').textContent = nb + (nb > 1 ? ' billets' : ' billet');
    return nb;
  }
  qtes.forEach(i => i.addEventListener('input', recalculer));
  recalculer();

  // ----- Affichage selon le type de client -----
  function modeClient() { return form.querySelector('input[name="mode_client"]:checked').value; }
  function majClient() {
    const m = modeClient();
    form.querySelectorAll('.client-panel').forEach(p => p.hidden = (p.dataset.mode !== m));
  }
  form.querySelectorAll('input[name="mode_client"]').forEach(r => r.addEventListener('change', majClient));
  majClient();

  // ----- Contrôles avant envoi + anti double clic -----
  form.addEventListener('submit', function (e) {
    const msgs = [];
    if (recalculer() === 0) msgs.push("Ajoutez au moins un billet.");
    if (!form.querySelector('input[name="jeux[]"]:checked')) msgs.push("Sélectionnez au moins un jeu.");
    if (form.age.value === '') msgs.push("Indiquez l'âge du plus jeune joueur.");
    const m = modeClient();
    if (m === 'existant' && !form.client_email.value.trim()) msgs.push("Sélectionnez un client.");
    if (m === 'nouveau') {
      if (!form.n_nom.value.trim() || !form.n_prenom.value.trim()) msgs.push("Nom et prénom obligatoires.");
      if (!/^[0-9]{10}$/.test(form.n_tel.value.replace(/[\s.\-]/g, ''))) msgs.push("Téléphone : 10 chiffres attendus.");
    }
    if (msgs.length) { e.preventDefault(); alert(msgs.join("\n")); return; }
    const b = document.getElementById('btnValider');
    b.disabled = true; b.textContent = 'Enregistrement…';
  });
})();
</script>

<?php endif; ?>

<?php pied(); ?>
