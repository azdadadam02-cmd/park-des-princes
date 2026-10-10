<?php
// À inclure en première ligne de CHAQUE page de l'espace agent de vente.
session_start();
require_once __DIR__ . '/../db.php';

const ROLE_AGENT = 'agent';

// 1) Il faut être connecté avec le rôle "agent"
if (empty($_SESSION['email']) || ($_SESSION['role'] ?? '') !== ROLE_AGENT) {
    header('Location: ../login.php');
    exit;
}
$email_agent = $_SESSION['email'];

// 2) Le compte doit exister dans la table AGENT_DE_VENTE
$st = $pdo->prepare("SELECT 1 FROM agent_de_vente WHERE email = ?");
$st->execute([$email_agent]);
if (!$st->fetch()) {
    http_response_code(403);
    die("Accès refusé.");
}

/* =========================================================
   Constantes métier
   ========================================================= */
const MODES_PAIEMENT   = ['espèces', 'carte'];
const MAX_PAR_LIGNE    = 20;                    // billets max par type et par vente
const EMAIL_VISITEUR   = 'visiteur@parc.local'; // compte "client de passage"

/* =========================================================
   Helpers
   ========================================================= */

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function ucfirst_fr(string $s): string {
    return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
}

function dh($v): string { return number_format((float)$v, 2, ',', ' ') . ' DH'; }

/** Date au format jj/mm/aaaa (ou jj/mm/aaaa hh:mm). */
function date_fr(?string $d, bool $heure = false): string {
    if (!$d) return '—';
    $t = strtotime($d);
    return $t ? date($heure ? 'd/m/Y H:i' : 'd/m/Y', $t) : '—';
}

/** Vérifie une date "Y-m-d" stricte ; retourne '' si invalide. */
function date_valide(string $s): string {
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : '';
}

/** Couleur du statut d'une réservation. */
function classe_statut_reservation(?string $s): string {
    switch (mb_strtolower(trim((string)$s))) {
        case 'confirmée': return 'is-ok';
        case 'payée':     return 'is-info';
        case 'utilisée':  return 'is-muted';
        case 'annulée':   return 'is-danger';
        default:          return 'is-warn';   // en attente
    }
}

/**
 * Évalue la validité d'un billet (ligne issue de la requête de billets.php).
 * Retourne [valide(bool), libellé, classe CSS, explication].
 */
function evaluer_billet(array $b): array {
    $s = mb_strtolower(trim((string)$b['statut_billet']));
    if (mb_strtolower((string)$b['statut_commande']) !== 'payée') {
        return [false, 'Non payé', 'is-danger', "La commande associée n'est pas payée."];
    }
    if ($s === 'annulé') {
        return [false, 'Annulé', 'is-danger', 'Ce billet a été annulé.'];
    }
    if (!empty($b['date_fin']) && strtotime($b['date_fin']) < time()) {
        return [false, 'Expiré', 'is-danger', 'Ce billet a expiré le ' . date_fr($b['date_fin'], true) . '.'];
    }
    if ($s === 'utilisé') {
        return [false, 'Déjà utilisé', 'is-warn', 'Ce billet à usage unique a déjà été utilisé.'];
    }
    return [true, 'Valide', 'is-ok', "Billet valide, l'accès peut être autorisé."];
}

/* ----- Jeton anti-CSRF partagé par les formulaires d'action de la session ----- */
function csrf_token(string $nom): string {
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_champ(string $nom): string {
    return '<input type="hidden" name="csrf" value="' . h(csrf_token($nom)) . '">';
}
function csrf_ok(string $nom): bool {
    return isset($_POST['csrf'], $_SESSION['csrf_token'])
        && is_string($_POST['csrf'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf']);
}
function csrf_renouveler(string $nom): void {
    unset($_SESSION['csrf_token']);
}

/** Types de billets et tarifs (DH). Définis ici car la base ne contient pas de table de tarifs. */
const TYPES_BILLETS = [
    'enfant'  => ['libelle' => 'Enfant',       'prix' =>  50.00, 'validite_jours' => 1],
    'adulte'  => ['libelle' => 'Adulte',       'prix' => 100.00, 'validite_jours' => 1],
    'famille' => ['libelle' => 'Famille',      'prix' => 300.00, 'validite_jours' => 1],
    'pass'    => ['libelle' => 'Pass journée', 'prix' => 150.00, 'validite_jours' => 1],
];

/** Liste des types de billets (id_type = clé du tableau ci-dessus). */
function types_billets(): array {
    $out = [];
    foreach (TYPES_BILLETS as $id => $t) {
        $out[] = ['id_type' => $id, 'usage_unique' => 1] + $t;
    }
    return $out;
}

/** Code affiché d'un billet, dérivé de son identifiant unique (id_billet). Ex. 42 -> PP-000042 */
function code_billet(int $id_billet): string {
    return 'PP-' . str_pad((string)$id_billet, 6, '0', STR_PAD_LEFT);
}

/** Lit un code saisi ("PP-000042", "pp42" ou "42") et retourne l'id du billet, ou null. */
function id_depuis_code(string $code): ?int {
    if (!preg_match('/^(?:PP-?)?0*([0-9]{1,9})$/i', trim($code), $m)) return null;
    return (int)$m[1] ?: null;
}

/* =========================================================
   Règles métier : état des jeux, échéances, validations
   ========================================================= */

const ETAT_DISPONIBLE   = 'Disponible';
const ETAT_INDISPONIBLE = 'Indisponible';

/**
 * Maintenance automatique (exécutée à chaque page agent) :
 *  1. commandes en attente dont l'échéance est dépassée => Annulée (+ réservation et billets)
 *  2. réservations dont la date de visite est dépassée   => Terminée (statut « utilisée »)
 *  3. jeux « Indisponible » plus couverts par aucune réservation en ligne active => Disponible
 */
function maj_automatique(PDO $pdo): void {
    try {
        $echue = "c.statut_commande = 'en attente'
                  AND COALESCE(c.date_limite_paiement, DATE_ADD(DATE(r.date_visite), INTERVAL 1 DAY)) <= NOW()";

        $pdo->exec("UPDATE billet b JOIN commande c ON c.num_commande = b.num_commande
                    LEFT JOIN reservation r ON r.num_reservation = c.num_reservation
                    SET b.statut_billet = 'annulé'
                    WHERE b.statut_billet = 'en attente' AND $echue");
        $pdo->exec("UPDATE reservation r JOIN commande c ON c.num_reservation = r.num_reservation
                    SET r.statut_reservation = 'annulée'
                    WHERE r.statut_reservation IN ('en attente', 'confirmée') AND $echue");
        $pdo->exec("UPDATE commande c LEFT JOIN reservation r ON r.num_reservation = c.num_reservation
                    SET c.statut_commande = 'annulée'
                    WHERE $echue");
        $pdo->exec("UPDATE reservation SET statut_reservation = 'utilisée'
                    WHERE statut_reservation IN ('confirmée', 'payée') AND date_visite < CURDATE()");
        liberer_jeux($pdo);
    } catch (Throwable $e) {
        error_log('maj_automatique : ' . $e->getMessage());
    }
}

/** Repasse à Disponible les jeux Indisponible qui ne sont plus réservés (visite incluse). */
function liberer_jeux(PDO $pdo): void {
    $pdo->prepare(
        "UPDATE jeu j SET j.etat = ?
         WHERE j.etat = ?
           AND NOT EXISTS (
               SELECT 1 FROM choisir ch JOIN reservation r ON r.num_reservation = ch.num_reservation
               WHERE ch.id_jeu = j.id_jeu AND r.origine = 'en ligne'
                 AND r.statut_reservation IN ('confirmée', 'payée') AND r.date_visite >= CURDATE())"
    )->execute([ETAT_DISPONIBLE, ETAT_INDISPONIBLE]);
}

/** Téléphone marocain/standard : 10 chiffres (espaces, points et tirets tolérés). Retourne '' si invalide. */
function telephone_valide(?string $tel): string {
    $t = preg_replace('/[\s.\-]/', '', (string)$tel);
    return preg_match('/^[0-9]{10}$/', $t) ? $t : '';
}

/** Contrôle des informations d'un client (nom, prénom, téléphone, e-mail). Retourne la liste des erreurs. */
function erreurs_infos_client(array $u): array {
    $err = [];
    if (trim((string)($u['nom'] ?? '')) === '' || trim((string)($u['prenom'] ?? '')) === '') {
        $err[] = "nom ou prénom vide";
    }
    if (telephone_valide($u['telephone'] ?? '') === '') {
        $err[] = "téléphone invalide (10 chiffres attendus)";
    }
    if (!filter_var((string)($u['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
        $err[] = "e-mail invalide";
    }
    return $err;
}

/** Fin de journée (23:59:59) d'une date/heure SQL. */
function fin_de_journee(string $dt): string {
    return date('Y-m-d 23:59:59', strtotime($dt));
}

/** Valide une réservation en ligne : jeux Indisponible, commande « en attente » créée. [ok, message] */
function valider_reservation(PDO $pdo, int $num, string $agent): array {
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare(
            "SELECT r.*, u.nom, u.prenom, u.telephone, u.email
             FROM reservation r JOIN utilisateur u ON u.email = r.email_client
             WHERE r.num_reservation = ? AND r.origine = 'en ligne' FOR UPDATE"
        );
        $st->execute([$num]);
        $r = $st->fetch();

        if (!$r)                                           return _echec($pdo, "Réservation introuvable.");
        if ($r['statut_reservation'] !== 'en attente')     return _echec($pdo, "Cette réservation n'est plus en attente.");
        if (strtotime($r['date_visite']) < strtotime('today')) return _echec($pdo, "La date de visite est dépassée.");
        if ($e = erreurs_infos_client($r))                 return _echec($pdo, "Informations client invalides : " . implode(', ', $e) . ".");

        $st = $pdo->prepare(
            "SELECT j.id_jeu, j.nom, j.etat, j.capacite FROM choisir ch
             JOIN jeu j ON j.id_jeu = ch.id_jeu WHERE ch.num_reservation = ? FOR UPDATE"
        );
        $st->execute([$num]);
        $jeux = $st->fetchAll();
        if (!$jeux) return _echec($pdo, "Aucun jeu n'est associé à cette réservation.");

        foreach ($jeux as $j) {
            if (mb_strtolower($j['etat']) !== mb_strtolower(ETAT_DISPONIBLE)) {
                return _echec($pdo, "Le jeu « {$j['nom']} » n'est pas disponible (état : {$j['etat']}).");
            }
            if ($j['capacite'] !== null && (int)$r['nb_personnes'] > (int)$j['capacite']) {
                return _echec($pdo, "Le jeu « {$j['nom']} » accepte au maximum " . (int)$j['capacite'] . " joueurs.");
            }
        }

        $st = $pdo->prepare("SELECT 1 FROM commande WHERE num_reservation = ? AND statut_commande <> 'annulée'");
        $st->execute([$num]);
        if ($st->fetch()) return _echec($pdo, "Une commande existe déjà pour cette réservation.");

        $pdo->prepare("UPDATE reservation SET statut_reservation = 'confirmée', email_agent = ? WHERE num_reservation = ?")
            ->execute([$agent, $num]);
        $maj = $pdo->prepare("UPDATE jeu SET etat = ? WHERE id_jeu = ?");
        foreach ($jeux as $j) $maj->execute([ETAT_INDISPONIBLE, $j['id_jeu']]);

        $pdo->prepare(
            "INSERT INTO commande (montant_total, statut_commande, email_client, email_agent, num_reservation, date_limite_paiement)
             VALUES (?, 'en attente', ?, ?, ?, ?)"
        )->execute([$r['montant'], $r['email_client'], $agent, $num, fin_de_journee($r['date_visite'])]);
        $num_commande = (int)$pdo->lastInsertId();

        $pdo->commit();
        return [true, "Réservation #$num validée : commande #$num_commande créée, jeux indisponibles jusqu'au " . date_fr($r['date_visite']) . "."];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('valider_reservation : ' . $e->getMessage());
        return [false, "Erreur technique : aucune modification n'a été enregistrée."];
    }
}

/** Annule une réservation en ligne non payée et libère les jeux. [ok, message] */
function annuler_reservation(PDO $pdo, int $num): array {
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT statut_reservation FROM reservation WHERE num_reservation = ? AND origine = 'en ligne' FOR UPDATE");
        $st->execute([$num]);
        $s = $st->fetchColumn();
        if ($s === false)                              return _echec($pdo, "Réservation introuvable.");
        if (!in_array($s, ['en attente', 'confirmée'], true)) return _echec($pdo, "Cette réservation ne peut plus être annulée (statut : $s).");

        $st = $pdo->prepare("SELECT 1 FROM commande WHERE num_reservation = ? AND statut_commande = 'payée'");
        $st->execute([$num]);
        if ($st->fetch()) return _echec($pdo, "La commande est déjà payée : annulation impossible.");

        $pdo->prepare("UPDATE billet b JOIN commande c ON c.num_commande = b.num_commande
                       SET b.statut_billet = 'annulé' WHERE c.num_reservation = ? AND c.statut_commande = 'en attente'")->execute([$num]);
        $pdo->prepare("UPDATE commande SET statut_commande = 'annulée' WHERE num_reservation = ? AND statut_commande = 'en attente'")->execute([$num]);
        $pdo->prepare("UPDATE reservation SET statut_reservation = 'annulée' WHERE num_reservation = ?")->execute([$num]);
        liberer_jeux($pdo);

        $pdo->commit();
        return [true, "Réservation #$num annulée, jeux libérés."];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('annuler_reservation : ' . $e->getMessage());
        return [false, "Erreur technique : aucune modification n'a été enregistrée."];
    }
}

/** Encaisse une commande « en attente » (génère les billets si besoin). [ok, message, num_commande] */
function encaisser_commande(PDO $pdo, int $num_commande, string $agent): array {
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare(
            "SELECT c.num_commande, c.montant_total, c.num_reservation, c.date_limite_paiement,
                    r.date_visite, r.nb_personnes
             FROM commande c LEFT JOIN reservation r ON r.num_reservation = c.num_reservation
             WHERE c.num_commande = ? AND c.statut_commande = 'en attente' FOR UPDATE"
        );
        $st->execute([$num_commande]);
        $c = $st->fetch();
        if (!$c) return _echec($pdo, "Commande introuvable ou déjà traitée.", $num_commande);

        $limite = $c['date_limite_paiement'] ?: ($c['date_visite'] ? fin_de_journee($c['date_visite']) : null);
        if ($limite && strtotime($limite) < time()) {
            return _echec($pdo, "La date limite de paiement est dépassée : la commande va être annulée.", $num_commande);
        }

        $pdo->prepare("UPDATE commande SET statut_commande = 'payée', email_agent = ?, date_paiment = NOW()
                       WHERE num_commande = ? AND statut_commande = 'en attente'")
            ->execute([$agent, $num_commande]);

        $st = $pdo->prepare("SELECT COUNT(*) FROM billet WHERE num_commande = ?");
        $st->execute([$num_commande]);
        if ((int)$st->fetchColumn() === 0) {
            // Réservation en ligne : un billet « Pass journée » par personne, valable le jour de visite
            $nb   = max(1, (int)($c['nb_personnes'] ?? 1));
            $prix = round((float)$c['montant_total'] / $nb, 2);
            $debut = date('Y-m-d 00:00:00', strtotime($c['date_visite'] ?? 'today'));
            $fin   = fin_de_journee($c['date_visite'] ?? 'today');
            $ins = $pdo->prepare("INSERT INTO billet (date_creation, date_fin, type, statut_billet, quantite, prix_unitaire, num_commande, email_agent)
                                  VALUES (?, ?, 'Pass journée', 'valide', 1, ?, ?, ?)");
            $lien = $pdo->prepare("INSERT INTO contient (id_billet, id_jeu) SELECT ?, id_jeu FROM choisir WHERE num_reservation = ?");
            for ($i = 0; $i < $nb; $i++) {
                $ins->execute([$debut, $fin, $prix, $num_commande, $agent]);
                if ($c['num_reservation']) $lien->execute([(int)$pdo->lastInsertId(), $c['num_reservation']]);
            }
        } else {
            $pdo->prepare("UPDATE billet SET statut_billet = 'valide', email_agent = ? WHERE num_commande = ? AND statut_billet = 'en attente'")
                ->execute([$agent, $num_commande]);
        }

        if ($c['num_reservation']) {
            $pdo->prepare("UPDATE reservation SET statut_reservation = 'payée' WHERE num_reservation = ? AND statut_reservation IN ('en attente','confirmée')")
                ->execute([$c['num_reservation']]);
        }
        $pdo->commit();
        return [true, "Commande #$num_commande payée.", $num_commande];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('encaisser_commande : ' . $e->getMessage());
        return [false, "Erreur technique : aucun paiement n'a été enregistré.", $num_commande];
    }
}

function _echec(PDO $pdo, string $msg, ?int $id = null): array {
    if ($pdo->inTransaction()) $pdo->rollBack();
    return $id === null ? [false, $msg] : [false, $msg, $id];
}

maj_automatique($pdo);

/* =========================================================
   Squelette HTML commun
   ========================================================= */

function entete(string $titre, bool $afficherTitre = true): void {
    $page = basename($_SERVER['SCRIPT_NAME']);
    if (in_array($page, ['recu.php', 'Billet.php', 'vente.php', 'paiements.php'], true)) $page = 'commandes.php';
    if (in_array($page, ['tous_billets.php', 'billets.php'], true)) $page = 'index.php';

    $liens = [
        'index.php'        => 'Dashboard',
        'reservations.php' => 'Réservations',
        'commandes.php'    => 'Commandes',
    ];
    $email = $_SESSION['email'] ?? 'Compte';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($titre) ?> - Parc des Princes</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/agent.css">
</head>
<body>

<header class="site-header agent-header">
    <div class="container agent-header-inner">

        <div class="agent-side">
            <img src="../assets/logo.png" alt="Parc des Princes" class="site-logo">
        </div>

        <nav class="nav-pill">
            <?php foreach ($liens as $url => $nom): ?>
                <a href="<?= $url ?>" class="<?= $page === $url ? 'active' : '' ?>"><?= h($nom) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="agent-side agent-side--end">
            <a href="../logout.php" class="user-badge" title="Se déconnecter">👤 <?= h($email) ?></a>
        </div>

    </div>
</header>

<main class="container">

<?php if ($afficherTitre): ?>
    <div class="row">
        <div class="col-12">
            <h1 class="page-title"><?= h($titre) ?></h1>
        </div>
    </div>
<?php endif; ?>

<?php
}

function pied(): void {
?>

</main>
</body>
</html>
<?php
}
