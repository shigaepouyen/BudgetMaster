<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BudgetMaster CA - Budgets</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="has-background-light">
    
    <nav class="navbar is-info">
        <div class="navbar-brand"><a class="navbar-item has-text-weight-bold" href="/">BudgetMaster CA</a></div>
        <div class="navbar-menu">
            <div class="navbar-start">
                <a href="/" class="navbar-item"><i class="fas fa-home mr-2"></i> Dashboard</a>
                <a href="/?action=budget" class="navbar-item is-active"><i class="fas fa-chart-pie mr-2"></i> Budgets</a>
                <a href="/?action=recurrence" class="navbar-item"><i class="fas fa-sync-alt mr-2"></i> Récurrences</a>
                <a href="/?action=settings" class="navbar-item"><i class="fas fa-cogs mr-2"></i> Paramètres</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        
        <!-- Navigation Mois -->
        <div class="level box">
            <div class="level-left">
                <a href="/?action=budget&month=<?php echo $prevDate->format('m'); ?>&year=<?php echo $prevDate->format('Y'); ?>" class="button is-small">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </div>
            <div class="level-item has-text-centered">
                <div>
                    <p class="heading">Suivi Budgétaire</p>
                    <p class="title is-4"><?php echo ucfirst(IntlDateFormatter::create('fr_FR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'MMMM yyyy')->format($currentDate)); ?></p>
                </div>
            </div>
            <div class="level-right">
                <a href="/?action=budget&month=<?php echo $nextDate->format('m'); ?>&year=<?php echo $nextDate->format('Y'); ?>" class="button is-small">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>

        <!-- KPI Global -->
        <?php 
            $globalPercent = ($totals['goal'] > 0) ? ($totals['spent'] / $totals['goal']) * 100 : 0;
            $globalColor = ($globalPercent > 100) ? 'is-danger' : (($globalPercent > 85) ? 'is-warning' : 'is-success');
        ?>
        <div class="box has-background-white-ter mb-5">
            <div class="columns is-vcentered">
                <div class="column is-3 has-text-centered">
                    <p class="heading">Dépensé</p>
                    <p class="title has-text-weight-bold <?php echo $globalColor === 'is-danger' ? 'has-text-danger' : ''; ?>">
                        <?php echo number_format($totals['spent'], 0, ',', ' '); ?> €
                    </p>
                </div>
                <div class="column is-6">
                    <progress class="progress <?php echo $globalColor; ?> is-large" value="<?php echo min(100, $globalPercent); ?>" max="100">
                        <?php echo round($globalPercent); ?>%
                    </progress>
                </div>
                <div class="column is-3 has-text-centered">
                    <p class="heading">Objectif Total</p>
                    <p class="title has-text-grey">
                        <?php echo number_format($totals['goal'], 0, ',', ' '); ?> €
                    </p>
                </div>
            </div>
        </div>

        <!-- Tableau des Budgets -->
        <div class="box">
            <table class="table is-fullwidth is-hoverable">
                <thead>
                    <tr>
                        <th style="width: 20%;">Catégorie</th>
                        <th style="width: 40%;">Progression</th>
                        <th style="width: 15%;" class="has-text-right">Dépensé</th>
                        <th style="width: 15%;" class="has-text-right">Objectif</th>
                        <th style="width: 10%;" class="has-text-right">Reste</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($budgets as $b): ?>
                    <tr>
                        <td class="has-text-weight-bold"><?php echo htmlspecialchars($b['name']); ?></td>
                        <td class="is-vcentered">
                            <div class="is-flex is-align-items-center">
                                <progress class="progress <?php echo $b['color']; ?> is-small mb-0 mr-2" value="<?php echo $b['percent']; ?>" max="100" style="width: 100%;"></progress>
                                <span class="is-size-7 has-text-grey" style="min-width: 35px;"><?php echo round($b['raw_percent']); ?>%</span>
                            </div>
                        </td>
                        <td class="has-text-right has-text-weight-semibold">
                            <?php echo number_format($b['spent'], 0, ',', ' '); ?> €
                        </td>
                        <td class="has-text-right">
                            <div class="field has-addons has-addons-right">
                                <div class="control">
                                    <!-- Input pour modifier le budget en direct -->
                                    <input 
                                        type="number" 
                                        class="input is-small has-text-right" 
                                        style="width: 80px; border: none; border-bottom: 1px dashed #ccc; box-shadow: none;"
                                        value="<?php echo $b['goal'] > 0 ? $b['goal'] : ''; ?>" 
                                        placeholder="-"
                                        onchange="updateBudget(<?php echo $b['id']; ?>, this.value)"
                                    >
                                </div>
                                <div class="control">
                                    <a class="button is-static is-small is-white">€</a>
                                </div>
                            </div>
                        </td>
                        <td class="has-text-right <?php echo $b['remaining'] == 0 && $b['goal'] > 0 ? 'has-text-danger' : 'has-text-success'; ?>">
                            <?php echo ($b['goal'] > 0) ? number_format($b['goal'] - $b['spent'], 0, ',', ' ') . ' €' : '-'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function updateBudget(catId, amount) {
            if (amount === '') amount = 0;
            
            // Petit effet visuel pour dire "ça sauvegarde"
            const input = document.activeElement;
            input.classList.add('is-loading');

            fetch('/?action=budget_update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ category_id: catId, amount: amount })
            })
            .then(r => r.json())
            .then(d => {
                if(d.success) {
                    location.reload();
                } else {
                    alert('Erreur sauvegarde');
                }
            });
        }
    </script>
</body>
</html>