<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BudgetMaster CA - Récurrences</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        tr.fade-out { opacity: 0; transform: translateX(20px); transition: all 0.5s ease-in-out; }
        tr.marked-active { background-color: #effaf5 !important; }
        tr.marked-ignored { background-color: #feecf0 !important; }
    </style>
</head>
<body class="has-background-light">
    
    <nav class="navbar is-info">
        <div class="navbar-brand"><a class="navbar-item has-text-weight-bold" href="/">BudgetMaster CA</a></div>
        <div class="navbar-menu">
            <div class="navbar-start">
                <a href="/" class="navbar-item"><i class="fas fa-home mr-2"></i> Dashboard</a>
                <a href="/?action=recurrence" class="navbar-item is-active"><i class="fas fa-sync-alt mr-2"></i> Récurrences</a>
                <a href="/?action=settings" class="navbar-item"><i class="fas fa-cogs mr-2"></i> Paramètres</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        
        <!-- KPI -->
        <div class="columns">
            <div class="column is-full">
                <div class="box has-background-white-ter">
                    <nav class="level">
                        <div class="level-item has-text-centered">
                            <div>
                                <p class="heading">Charges Fixes Mensuelles</p>
                                <p class="title has-text-info"><?php echo number_format($totalMonthly, 2, ',', ' '); ?> €</p>
                            </div>
                        </div>
                        <div class="level-item has-text-centered">
                            <div>
                                <p class="heading">Projection Annuelle</p>
                                <p class="title"><?php echo number_format($totalYearly, 2, ',', ' '); ?> €</p>
                            </div>
                        </div>
                    </nav>
                </div>
            </div>
        </div>

        <div class="box">
            <div class="tabs is-boxed">
                <ul>
                    <li class="is-active" id="tab-detected" onclick="switchTab('detected')"><a><span class="icon is-small"><i class="fas fa-magic"></i></span> <span>Détectées</span></a></li>
                    <li id="tab-active" onclick="switchTab('active')"><a><span class="icon is-small has-text-success"><i class="fas fa-check-circle"></i></span> <span>Validées</span></a></li>
                    <li id="tab-ignored" onclick="switchTab('ignored')"><a><span class="icon is-small has-text-grey"><i class="fas fa-ban"></i></span> <span>Ignorées</span></a></li>
                </ul>
            </div>

            <!-- Liste : Détectées -->
            <div id="view-detected">
                <table class="table is-fullwidth is-hoverable">
                    <thead><tr><th>Libellé / Alias</th><th>Montant</th><th>Fréquence</th><th class="has-text-right">Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($recurrences as $rec): ?>
                            <?php if ($rec['status'] === 'DETECTED'): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($rec['signature']); ?></strong><br>
                                    <span class="is-size-7 has-text-grey"><?php echo htmlspecialchars($rec['category_name']); ?></span>
                                </td>
                                <td class="has-text-danger has-text-weight-bold"><?php echo number_format($rec['amount'], 2); ?> €</td>
                                <td><span class="tag is-light"><?php echo $rec['frequency']; ?></span></td>
                                <td class="has-text-right">
                                    <button class="button is-small is-success" onclick="setStatus(this, '<?php echo addslashes($rec['signature']); ?>', '<?php echo $rec['amount']; ?>', '<?php echo $rec['frequency']; ?>', 'ACTIVE')" title="Valider">
                                        <span class="icon"><i class="fas fa-check"></i></span>
                                    </button>
                                    <button class="button is-small is-light" onclick="setStatus(this, '<?php echo addslashes($rec['signature']); ?>', '<?php echo $rec['amount']; ?>', '<?php echo $rec['frequency']; ?>', 'IGNORED')" title="Ignorer">
                                        <span class="icon"><i class="fas fa-ban"></i></span>
                                    </button>
                                    <!-- NOUVEAU : Bouton Supprimer -->
                                    <button class="button is-small is-danger is-light" onclick="deleteRecurrence(this, '<?php echo addslashes($rec['signature']); ?>', '<?php echo $rec['amount']; ?>')" title="Supprimer définitivement">
                                        <span class="icon"><i class="fas fa-trash"></i></span>
                                    </button>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Liste : Validées -->
            <div id="view-active" style="display:none;">
                <table class="table is-fullwidth is-hoverable">
                    <thead><tr><th>Libellé</th><th>Montant</th><th>Fréquence</th><th class="has-text-right">Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($recurrences as $rec): ?>
                            <?php if ($rec['status'] === 'ACTIVE'): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($rec['signature']); ?></strong></td>
                                <td class="has-text-success has-text-weight-bold"><?php echo number_format($rec['amount'], 2); ?> €</td>
                                <td><span class="tag is-light"><?php echo $rec['frequency']; ?></span></td>
                                <td class="has-text-right">
                                    <button class="button is-small is-danger is-inverted" onclick="setStatus(this, '<?php echo addslashes($rec['signature']); ?>', '<?php echo $rec['amount']; ?>', '<?php echo $rec['frequency']; ?>', 'IGNORED')">
                                        <span class="icon"><i class="fas fa-ban"></i></span> Ignorer
                                    </button>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Liste : Ignorées -->
            <div id="view-ignored" style="display:none;">
                <table class="table is-fullwidth has-text-grey-light">
                    <thead><tr><th>Libellé</th><th>Montant</th><th class="has-text-right">Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($recurrences as $rec): ?>
                            <?php if ($rec['status'] === 'IGNORED'): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($rec['signature']); ?></td>
                                <td><?php echo number_format($rec['amount'], 2); ?> €</td>
                                <td class="has-text-right">
                                    <button class="button is-small is-info is-light" onclick="setStatus(this, '<?php echo addslashes($rec['signature']); ?>', '<?php echo $rec['amount']; ?>', '<?php echo $rec['frequency']; ?>', 'ACTIVE')" title="Réactiver">
                                        <span class="icon"><i class="fas fa-undo"></i></span>
                                    </button>
                                    <!-- NOUVEAU : Bouton Supprimer -->
                                    <button class="button is-small is-danger" onclick="deleteRecurrence(this, '<?php echo addslashes($rec['signature']); ?>', '<?php echo $rec['amount']; ?>')" title="Supprimer définitivement">
                                        <span class="icon"><i class="fas fa-trash"></i></span>
                                    </button>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tabs li').forEach(li => li.classList.remove('is-active'));
            document.getElementById('tab-' + tabName).classList.add('is-active');
            ['detected', 'active', 'ignored'].forEach(t => document.getElementById('view-' + t).style.display = 'none');
            document.getElementById('view-' + tabName).style.display = 'block';
        }

        function setStatus(btn, sig, amt, freq, status) {
            const row = btn.closest('tr');
            btn.classList.add('is-loading');
            fetch('/?action=recurrence_update', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ signature: sig, amount: amt, frequency: freq, status: status })
            }).then(r => r.json()).then(d => {
                btn.classList.remove('is-loading');
                if(d.success) {
                    row.classList.add(status === 'ACTIVE' ? 'marked-active' : 'marked-ignored');
                    setTimeout(() => { row.classList.add('fade-out'); setTimeout(() => row.remove(), 500); }, 300);
                }
            });
        }

        // NOUVEAU : Fonction Suppression
        function deleteRecurrence(btn, sig, amt) {
            if(!confirm("Supprimer définitivement ?\n(Elle réapparaîtra si l'analyse la détecte à nouveau plus tard)")) return;
            
            const row = btn.closest('tr');
            btn.classList.add('is-loading');
            
            fetch('/?action=recurrence_delete', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ signature: sig, amount: amt })
            }).then(r => r.json()).then(d => {
                btn.classList.remove('is-loading');
                if(d.success) {
                    row.style.backgroundColor = '#ffdddd';
                    setTimeout(() => { row.classList.add('fade-out'); setTimeout(() => row.remove(), 500); }, 300);
                } else {
                    alert('Erreur : ' + (d.message || 'Inconnue'));
                }
            });
        }
    </script>
</body>
</html>