<?php
require_once __DIR__ . '/_garde.php';

$message = '';
$type_message = 'error';

$id_billet_verifie = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok('billet')) {
        $message = "Le formulaire a expiré. Rechargez la page avant de réessayer.";
    } else {
        $saisie_brute = $_POST['code_billet'] ?? '';
        $saisie = is_string($saisie_brute) ? trim($saisie_brute) : '';
        $id_billet = id_depuis_code($saisie);

        if ($id_billet === null) {
            $message = "Saisissez un code de billet valide, par exemple PP-000042.";
        } else {
            $stmt = $pdo->prepare(
                "SELECT b.statut_billet, b.date_fin, c.statut_commande,
                        (b.date_creation <= NOW() AND b.date_fin >= NOW()) AS valide_aujourdhui
                 FROM billet b
                 JOIN commande c ON c.num_commande = b.num_commande
                 WHERE b.id_billet = ?"
            );
            $stmt->execute([$id_billet]);
            $billet = $stmt->fetch();

            if (!$billet) {
                $message = "Aucun billet trouvé avec ce numéro.";
            } elseif (mb_strtolower(trim((string)$billet['statut_billet'])) === 'utilisé') {
                $message = "Ce billet a déjà été utilisé.";
            } elseif (mb_strtolower(trim((string)$billet['statut_billet'])) !== 'valide') {
                $message = "Billet non valide. Statut actuel : " . (string)$billet['statut_billet'] . ".";
            } elseif (!in_array(mb_strtolower(trim((string)$billet['statut_commande'])), ['payée', 'confirmée'], true)) {
                $message = "Accès refusé : la commande associée n'est pas payée ou confirmée.";
            } elseif (!(int)$billet['valide_aujourdhui']) {
                $message = "Accès refusé : ce billet n'est pas valable aujourd'hui ou a expiré.";
            } else {
                $st = $pdo->prepare(
                    "UPDATE billet b
                     JOIN commande c ON c.num_commande = b.num_commande
                     SET b.statut_billet = 'utilisé'
                     WHERE b.id_billet = ?
                       AND b.statut_billet = 'valide'
                       AND b.date_creation <= NOW()
                       AND b.date_fin >= NOW()
                       AND c.statut_commande IN ('payée', 'confirmée')"
                );
                $st->execute([$id_billet]);

                if ($st->rowCount() === 1) {
                    $id_billet_verifie = $id_billet;
                    $message = "Billet PP-" . str_pad((string)$id_billet, 6, '0', STR_PAD_LEFT) . " valide. Entrée autorisée et billet marqué comme utilisé.";
                    $type_message = 'success';
                } else {
                    $message = "Le billet a changé d'état entre-temps. Vérifiez à nouveau son statut avant d'autoriser l'accès.";
                }
            }
        }
    }
}

// Derniers billets vendus par l'agent connecté.
$stmtDerniers = $pdo->prepare("
    SELECT id_billet, type, statut_billet, date_creation, date_fin
    FROM billet
    WHERE email_agent = ?
    ORDER BY id_billet DESC 
    LIMIT 5
");
$stmtDerniers->execute([$email_agent]);
$derniers_billets = $stmtDerniers->fetchAll();

// =========================================================================
// 3. AFFICHAGE HTML (Même design d'origine)
// =========================================================================
entete("Vérifier un billet", false);
?>

<!-- Style CSS intégré pour respecter le thème existant. -->
<style>
    body {
        background-color: #080710; /* Couleur de fond sombre globale */
        color: #ffffff;
        font-family: Arial, sans-serif;
    }
    .page-title {
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 5px;
    }
    .page-subtitle {
        color: #a3a3c2;
        font-size: 14px;
        margin-bottom: 20px;
    }
    .search-box {
        background-color: #15142b;
        border-radius: 12px;
        padding: 20px;
        display: flex;
        gap: 15px;
        align-items: center;
        margin-bottom: 40px;
    }
    .search-input {
        flex: 1;
        background-color: #0b0a1a;
        border: 1px solid #32325d;
        color: #ffffff;
        padding: 15px 20px;
        border-radius: 8px;
        font-size: 16px;
    }
    .search-input:focus {
        outline: none;
        border-color: #5e5ce6;
    }
    .btn-verifier {
        background-color: #5e5ce6;
        color: white;
        border: none;
        padding: 15px 30px;
        border-radius: 8px;
        font-weight: bold;
        cursor: pointer;
        font-size: 16px;
        transition: 0.3s;
    }
    .btn-verifier:hover {
        background-color: #4a48c4;
    }
    .table-container {
        background-color: #15142b;
        border-radius: 12px;
        overflow: hidden;
    }
    .custom-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }
    .custom-table th {
        background-color: #15142b;
        color: #a3a3c2;
        font-size: 12px;
        text-transform: uppercase;
        padding: 15px 20px;
        border-bottom: 1px solid #2a2a4a;
    }
    .custom-table td {
        padding: 15px 20px;
        border-bottom: 1px solid #2a2a4a;
        color: #ffffff;
        font-size: 14px;
    }
    .custom-table tr:last-child td {
        border-bottom: none;
    }
    .btn-outline {
        background: transparent;
        border: 1px solid #5e5ce6;
        color: #a3a3c2;
        padding: 6px 15px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
    }
    .btn-outline:hover {
        background: #5e5ce6;
        color: white;
    }
    .alert-box {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-weight: bold;
    }
    .alert-success { background-color: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }
    .alert-error { background-color: rgba(220, 53, 69, 0.2); color: #dc3545; border: 1px solid #dc3545; }
</style>

<div class="row">
    <div class="col-12" style="padding: 20px;">
        
        <h1 class="page-title">Vérifier un billet</h1>
        <p class="page-subtitle">Saisissez le code du billet (ex. PP-000042) ou simplement son numéro.</p>

        <?php if ($message): ?>
            <div class="alert-box <?= $type_message === 'success' ? 'alert-success' : 'alert-error' ?>">
                <?= h($message) ?>
            </div>
        <?php endif; ?>

        <!-- Vérification et consommation du billet. -->
        <form method="POST" action="" class="search-box">
            <?= csrf_champ('billet') ?>
            <input type="text" name="code_billet" class="search-input" placeholder="PP-000042"
                   value="<?= $id_billet_verifie ? h((string)$id_billet_verifie) : '' ?>" required autofocus>
            <button type="submit" class="btn-verifier">Vérifier</button>
        </form>

        <h2 class="page-title" style="margin-bottom: 20px;">Derniers billets que j'ai vendus</h2>
        
        <!-- Tableau des derniers billets -->
        <div class="table-container">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Code Billet</th>
                        <th>Type</th>
                        <th>Vendu le</th>
                        <th>Valable jusqu'au</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($derniers_billets)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: #a3a3c2;">Aucun billet vendu récemment.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($derniers_billets as $b): 
                            $code_formate = "PP-" . str_pad($b['id_billet'], 6, "0", STR_PAD_LEFT);
                        ?>
                        <tr>
                            <td style="color: #a0a2d8; font-family: monospace; font-size: 15px;"><?= h($code_formate) ?></td>
                            <td><?= h($b['type']) ?></td>
                            <td><?= h(date_fr($b['date_creation'], true)) ?></td>
                            <td><?= h(date_fr($b['date_fin'], true)) ?></td>
                            <td><?= h(ucfirst_fr((string)$b['statut_billet'])) ?></td>
                            <td>
                                <form method="POST" action="" style="margin: 0;">
                                    <?= csrf_champ('billet') ?>
                                    <input type="hidden" name="code_billet" value="<?= $b['id_billet'] ?>">
                                    <button type="submit" class="btn-outline">Vérifier</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<?php pied(); ?>