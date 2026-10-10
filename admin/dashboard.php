<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$stmtJeux =
    $pdo->query("
        SELECT COUNT(*)
        FROM jeu
    ");
$totalJeux =
    $stmtJeux->fetchColumn();





$stmtReservations = $pdo->query("
    SELECT COUNT(*)
    FROM reservation
");

$totalReservations =
    $stmtReservations->fetchColumn();




$stmtBillets = $pdo->query("
    SELECT COALESCE(SUM(quantite), 0)
    FROM billet
");

$totalBillets =
    $stmtBillets->fetchColumn();




$stmtDepenses = $pdo->query("
    SELECT
        COALESCE(
            SUM(montant_depense),
            0
        )
    FROM depense
");

$totalDepenses =
    $stmtDepenses->fetchColumn();

// ===============================
// Réservations par mois
// ===============================

$sqlReservations = "
    SELECT
        DATE_FORMAT(date_creation, '%Y-%m') AS mois,
        COUNT(*) AS total
    FROM reservation
    GROUP BY DATE_FORMAT(date_creation, '%Y-%m')
    ORDER BY mois
";

$stmtReservations = $pdo->query($sqlReservations);

$reservationsData = $stmtReservations->fetchAll(PDO::FETCH_ASSOC);


// ===============================
// Recettes et dépenses par mois
// ===============================

$sqlFinance = "
    SELECT
        mois,
        SUM(recettes) AS recettes,
        SUM(depenses) AS depenses
    FROM (

        SELECT
            DATE_FORMAT(date_commande, '%Y-%m') AS mois,
            SUM(montant_total) AS recettes,
            0 AS depenses
        FROM commande
        GROUP BY DATE_FORMAT(date_commande, '%Y-%m')

        UNION ALL

        SELECT
            DATE_FORMAT(date_depense, '%Y-%m') AS mois,
            0 AS recettes,
            SUM(montant_depense) AS depenses
        FROM depense
        GROUP BY DATE_FORMAT(date_depense, '%Y-%m')

    ) AS finance

    GROUP BY mois
    ORDER BY mois
";

$stmtFinance = $pdo->query($sqlFinance);

$financeData = $stmtFinance->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Admin</title>

    <link
        rel="stylesheet"
        href="../assets/css/admin.css"
    >
</head>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>

const reservationsData =
    <?= json_encode($reservationsData) ?>;

const financeData =
    <?= json_encode($financeData) ?>;


// ========================================
// Réservations par mois
// ========================================

const reservationLabels =
    reservationsData.map(item => item.mois);

const reservationValues =
    reservationsData.map(item => Number(item.total));


new Chart(
    document.getElementById('reservationsChart'),
    {
        type: 'line',

        data: {
            labels: reservationLabels,

            datasets: [{
                label: 'Réservations',

                data: reservationValues,

                borderColor: '#8b5cf6',

                backgroundColor:
                    'rgba(139, 92, 246, 0.12)',

                fill: true,

                tension: 0.4,

                borderWidth: 3,

                pointRadius: 4,

                pointBackgroundColor: '#ffffff'
            }]
        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            plugins: {

                legend: {
                    labels: {
                        color: '#d1d5db'
                    }
                }

            },

            scales: {

                x: {

                    ticks: {
                        color: '#94a3b8'
                    },

                    grid: {
                        color:
                            'rgba(255,255,255,0.05)'
                    }

                },

                y: {

                    beginAtZero: true,

                    ticks: {
                        color: '#94a3b8'
                    },

                    grid: {
                        color:
                            'rgba(255,255,255,0.05)'
                    }

                }

            }

        }

    }
);


// ========================================
// Recettes vs Dépenses
// ========================================

const financeLabels =
    financeData.map(item => item.mois);

const recettes =
    financeData.map(item => Number(item.recettes));

const depenses =
    financeData.map(item => Number(item.depenses));


new Chart(
    document.getElementById('financeChart'),
    {
        type: 'bar',

        data: {

            labels: financeLabels,

            datasets: [

                {
                    label: 'Recettes',

                    data: recettes,

                    backgroundColor:
                        'rgba(34,197,94,0.65)',

                    borderRadius: 8
                },

                {
                    label: 'Dépenses',

                    data: depenses,

                    backgroundColor:
                        'rgba(239,68,68,0.65)',

                    borderRadius: 8
                }

            ]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            plugins: {

                legend: {

                    labels: {
                        color: '#d1d5db'
                    }

                }

            },

            scales: {

                x: {

                    ticks: {
                        color: '#94a3b8'
                    },

                    grid: {
                        display: false
                    }

                },

                y: {

                    beginAtZero: true,

                    ticks: {

                        color: '#94a3b8',

                        callback: function(value) {
                            return value + ' DH';
                        }

                    },

                    grid: {
                        color:
                            'rgba(255,255,255,0.05)'
                    }

                }

            }

        }

    }
);

</script>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<main>

    <div class="topbar">

    <h1>
        Bonjour
        <?= htmlspecialchars($_SESSION['admin_nom'] ?? 'Admin') ?>
    </h1>

    <div class="topbar-right">

        <div class="profile-box">

            <div class="profile-circle">
                <?= strtoupper(substr($_SESSION['admin_email'] ?? 'A', 0, 1)) ?>
            </div>

            <span class="profile-email">
                <?= htmlspecialchars($_SESSION['admin_email'] ?? '') ?>
            </span>

        </div>

        <a href="logout.php" class="logout-btn">
            Déconnexion
        </a>

    </div>

</div>

<div class="cards-row">

    <div class="card">
        <h3>Jeux</h3>
        <p><?= $totalJeux ?></p>
    </div>

    <div class="card">
        <h3>Réservations</h3>
        <p><?= $totalReservations ?></p>
    </div>

    <div class="card">
        <h3>Billets vendus</h3>
        <p><?= $totalBillets ?></p>
    </div>

</div>


<div class="cards-row cards-bottom">

    <div class="card">
        <h3>Dépenses</h3>

        <p>
            <?= number_format($totalDepenses, 2, ',', ' ') ?> DH
        </p>
    </div>

    <div class="card">
        <h3>Recettes</h3>
        <p>0 DH</p>
    </div>

</div>

    <div class="section-box">

        <h2>Activité récente</h2>

        <p>
            Ici tu peux afficher les dernières réservations ou commandes.
        </p>

    </div>

    <div class="charts-grid">

    <div class="chart-card">

        <div class="chart-header">
            <h2>Réservations par mois</h2>
            <span>Évolution des réservations</span>
        </div>

        <div class="chart-container">
            <canvas id="reservationsChart"></canvas>
        </div>

    </div>


    <div class="chart-card">

        <div class="chart-header">
            <h2>Recettes & Dépenses</h2>
            <span>Analyse financière mensuelle</span>
        </div>

        <div class="chart-container">
            <canvas id="financeChart"></canvas>
        </div>

        </div>

    </div>



</main>

</body>
</html>