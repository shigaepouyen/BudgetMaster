<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BudgetMaster CA - Dashboard</title>
    <!-- Bulma CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Styles Catégories */
        .cat-badge-1 { background-color: #ffe08a; color: #000; }
        .cat-badge-2 { background-color: #b5e3ff; color: #000; }
        .cat-badge-6 { background-color: #48c774; color: #fff; }
        .cat-badge-8 { background-color: #e5e5e5; color: #7a7a7a; }
        [class*="cat-badge-"] { border: 1px solid #dbdbdb; } 
        
        .clickable-badge { cursor: pointer; transition: transform 0.1s; }
        .clickable-badge:hover { transform: scale(1.1); box-shadow: 0 2px 5px rgba(0,0,0,0.2); }

        .chart-container { position: relative; height: 250px; width: 100%; }
        .chart-center-text { 
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            text-align: center; pointer-events: none;
        }

        /* Barre d'action Bulk */
        #bulk-actions {
            position: fixed; bottom: 20px; right: 20px; z-index: 1000;
            display: none; box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
        }
        /* Style pour le message de sélection globale */
        #select-all-message { display: none; background-color: #eef6fc; color: #1e3a8a; text-align: center; padding: 10px; border-bottom: 1px solid #dbeafe; font-size: 0.9rem; }
        #select-all-message a { font-weight: bold; text-decoration: underline; cursor: pointer; }
        #select-all-confirm { display: none; background-color: #eef6fc; color: #1e3a8a; text-align: center; padding: 10px; border-bottom: 1px solid #dbeafe; font-size: 0.9rem; font-weight: bold; }
    </style>
</head>
<body class="has-background-light">
    
    <nav class="navbar is-info" role="navigation" aria-label="main navigation">
        <div class="navbar-brand">
            <a class="navbar-item has-text-weight-bold" href="/">
                BudgetMaster CA
            </a>
        </div>
        <div class="navbar-menu">
            <div class="navbar-start">
                <a href="/" class="navbar-item is-active">
                    <span class="icon"><i class="fas fa-home mr-2"></i></span>
                    <span>Dashboard</span>
                </a>
                <a href="/?action=budget" class="navbar-item"><i class="fas fa-chart-pie mr-2"></i> Budgets</a>
                <a href="/?action=recurrence" class="navbar-item">
                    <span class="icon"><i class="fas fa-sync-alt mr-2"></i></span>
                    <span>Récurrences</span>
                </a>
                <a href="/?action=settings" class="navbar-item">
                    <span class="icon"><i class="fas fa-cogs mr-2"></i></span>
                    <span>Paramètres</span>
                </a>
            </div>
            <div class="navbar-end">
                <div class="navbar-item">
                    <div class="buttons has-addons">
                        <a href="/?view=all" class="button <?php echo $currentView === 'all' ? 'is-primary is-selected' : ''; ?>">
                            <span class="icon"><i class="fas fa-users"></i></span><span>Famille</span>
                        </a>
                        <a href="/?view=mine" class="button <?php echo $currentView === 'mine' ? 'is-primary is-selected' : ''; ?>">
                            <span class="icon"><i class="fas fa-user"></i></span><span>Moi</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-5 mb-6">
        
        <?php if (!empty($message)): ?>
            <div class="notification <?php echo strpos($message, '❌') !== false ? 'is-danger' : 'is-success'; ?>">
                <button class="delete" onclick="this.parentElement.remove()"></button>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="columns">
            <!-- GAUCHE : Outils -->
            <div class="column is-one-quarter">
                
                <!-- Upload -->
                <div class="box mb-4">
                    <h1 class="title is-6"><i class="fas fa-file-import"></i> Import OFX</h1>
                    <form action="/" method="POST" enctype="multipart/form-data">
                        <div class="file has-name is-info is-small is-fullwidth mb-2">
                            <label class="file-label">
                                <input class="file-input" type="file" name="ofx_file" accept=".ofx">
                                <span class="file-cta"><i class="fas fa-upload"></i></span>
                                <span class="file-name">Fichier...</span>
                            </label>
                        </div>
                        <button type="submit" class="button is-primary is-small is-fullwidth">Importer</button>
                    </form>
                </div>

                <!-- Graphique -->
                <?php if (!empty($stats)): ?>
                <div class="box mb-4">
                    <h2 class="title is-6 mb-3">Dépenses : <?php echo htmlspecialchars($monthLabel); ?></h2>
                    <div class="chart-container">
                        <canvas id="expenseChart"></canvas>
                        <div class="chart-center-text">
                            <p class="is-size-4 has-text-weight-bold"><?php echo number_format(abs($totalExpenses), 0, ',', ' '); ?> €</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Configuration Comptes -->
                <div class="box mb-4">
                    <h2 class="title is-6"><i class="fas fa-cog"></i> Mes Comptes</h2>
                    <?php if (empty($accounts)): ?>
                        <p class="is-size-7 has-text-grey">Aucun compte.</p>
                    <?php else: ?>
                        <?php foreach ($accounts as $acc): ?>
                            <div class="mb-3 pb-3" style="border-bottom: 1px solid #eee;">
                                <div class="field">
                                    <label class="label is-size-7 mb-0">N° <?php echo $acc['account_number']; ?></label>
                                    <div class="control mb-1">
                                        <input class="input is-small" type="text" 
                                               id="acc-name-<?php echo $acc['id']; ?>" 
                                               value="<?php echo htmlspecialchars($acc['name']); ?>" 
                                               onchange="updateAccount(<?php echo $acc['id']; ?>)">
                                    </div>
                                    <div class="control">
                                        <div class="select is-small is-fullwidth">
                                            <select id="acc-owner-<?php echo $acc['id']; ?>" onchange="updateAccount(<?php echo $acc['id']; ?>)">
                                                <?php foreach ($users as $u): ?>
                                                    <option value="<?php echo $u['id']; ?>" <?php echo $acc['owner_id'] == $u['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($u['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- ZONE DANGER -->
                <div class="box has-background-danger-light">
                    <h2 class="title is-6 has-text-danger"><i class="fas fa-exclamation-triangle"></i> Zone Danger</h2>
                    <button class="button is-danger is-small is-fullwidth is-outlined" onclick="resetData()">
                        <span class="icon"><i class="fas fa-trash"></i></span>
                        <span>Tout supprimer</span>
                    </button>
                </div>
            </div>

            <!-- DROITE : Journal -->
            <div class="column">
                <div class="box">
                    <h2 class="title is-5">Journal des Opérations</h2>
                    
                    <!-- Formulaire Filtres -->
                    <div class="mb-4">
                        <form action="/" method="GET" id="filter-form">
                            <?php if($currentView !== 'all'): ?>
                                <input type="hidden" name="view" value="<?php echo htmlspecialchars($currentView); ?>">
                            <?php endif; ?>
                            
                            <!-- Ligne 1 : Recherche Standard -->
                            <div class="field has-addons mb-2">
                                <div class="control">
                                    <div class="select is-small">
                                        <select name="category" onchange="this.form.submit()">
                                            <option value="">Toutes catégories</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo $cat['id']; ?>" <?php echo (isset($categoryFilter) && $categoryFilter == $cat['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cat['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="control is-expanded has-icons-left">
                                    <input class="input is-small" type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Rechercher...">
                                    <span class="icon is-small is-left"><i class="fas fa-search"></i></span>
                                </div>
                                <div class="control">
                                    <button type="submit" class="button is-small is-info">Filtrer</button>
                                </div>
                                <div class="control">
                                    <button type="button" class="button is-small is-light" onclick="toggleAdvancedFilters()" title="Plus de filtres">
                                        <i class="fas fa-sliders-h"></i>
                                    </button>
                                </div>
                                <?php if(!empty($search) || !empty($categoryFilter) || !empty($dateStart) || !empty($dateEnd) || $amountMin !== '' || $amountMax !== ''): ?>
                                <div class="control">
                                    <a href="/?view=<?php echo $currentView; ?>" class="button is-small"><span class="icon"><i class="fas fa-times"></i></span></a>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Ligne 2 : Filtres Avancés (Cachés par défaut) -->
                            <div id="advanced-filters" class="field is-grouped is-grouped-multiline <?php echo (empty($dateStart) && empty($dateEnd) && $amountMin === '' && $amountMax === '') ? 'is-hidden' : ''; ?>" style="background: #f5f5f5; padding: 10px; border-radius: 4px;">
                                
                                <div class="control">
                                    <label class="label is-small">Du</label>
                                    <input class="input is-small" type="date" name="date_start" value="<?php echo htmlspecialchars($dateStart); ?>">
                                </div>
                                <div class="control">
                                    <label class="label is-small">Au</label>
                                    <input class="input is-small" type="date" name="date_end" value="<?php echo htmlspecialchars($dateEnd); ?>">
                                </div>

                                <div class="control">
                                    <label class="label is-small">Min (€)</label>
                                    <input class="input is-small" type="number" step="0.01" name="amount_min" value="<?php echo htmlspecialchars($amountMin); ?>" placeholder="-100">
                                </div>
                                <div class="control">
                                    <label class="label is-small">Max (€)</label>
                                    <input class="input is-small" type="number" step="0.01" name="amount_max" value="<?php echo htmlspecialchars($amountMax); ?>" placeholder="500">
                                </div>
                                
                                <div class="control">
                                    <label class="label is-small">&nbsp;</label>
                                    <button type="submit" class="button is-small is-link is-outlined">Appliquer</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Message "Sélectionner tout" (Global) -->
                    <div id="select-all-message">
                        Les <span id="count-page">0</span> lignes de cette page sont sélectionnées. 
                        <a onclick="selectAllAcrossPages()">Sélectionner les <span id="count-total">0</span> lignes correspondant à la recherche ?</a>
                    </div>
                    <div id="select-all-confirm">
                        Toutes les <span id="count-total-confirm">0</span> lignes sont sélectionnées.
                    </div>

                    <div class="table-container">
                        <table class="table is-striped is-fullwidth is-size-7">
                            <thead>
                                <tr>
                                    <th style="width:30px;"><input type="checkbox" id="select-all" onclick="toggleAll(this)"></th>
                                    <th>Date</th>
                                    <th>Compte</th>
                                    <th>Catégorie</th>
                                    <th>Libellé</th>
                                    <th class="has-text-right">Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentTransactions)): ?>
                                    <tr><td colspan="6" class="has-text-centered">Aucun résultat trouvé.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recentTransactions as $tx): ?>
                                        <tr>
                                            <td><input type="checkbox" class="tx-checkbox" value="<?php echo $tx['id']; ?>" onchange="updateBulkUI()"></td>
                                            
                                            <td style="width: 80px;"><?php echo date('d/m/y', strtotime($tx['date_booked'])); ?></td>
                                            <td><span class="tag is-white"><?php echo htmlspecialchars($tx['account_name']); ?></span></td>
                                            <td style="width: 130px;">
                                                <span 
                                                    class="tag clickable-badge cat-badge-<?php echo $tx['category_id'] ?? 8; ?> is-light"
                                                    onclick="openEditModal(<?php echo $tx['id']; ?>, <?php echo $tx['category_id'] ?? 8; ?>)"
                                                    id="badge-<?php echo $tx['id']; ?>"
                                                >
                                                    <?php echo htmlspecialchars($tx['category_name'] ?? 'Inconnu'); ?>
                                                </span>
                                            </td>
                                            
                                            <!-- Colonne Libellé avec support Custom Label -->
                                            <td class="col-label" title="<?php echo htmlspecialchars($tx['raw_label']); ?>">
                                                <?php if (!empty($tx['custom_comment'])): ?>
                                                    <strong><?php echo htmlspecialchars($tx['custom_comment']); ?></strong><br>
                                                    <span class="has-text-grey-light is-size-7"><?php echo htmlspecialchars(substr($tx['raw_label'], 0, 30)); ?>...</span>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars(substr($tx['raw_label'], 0, 40)); ?>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td class="has-text-right has-text-weight-bold <?php echo $tx['amount'] < 0 ? 'has-text-danger' : 'has-text-success'; ?>">
                                                <?php echo number_format($tx['amount'], 2, ',', ' '); ?> €
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <nav class="pagination is-centered is-small" role="navigation" aria-label="pagination">
                        <?php 
                            $qs = '&view=' . $currentView;
                            if(!empty($search)) $qs .= '&search=' . urlencode($search);
                            if(!empty($categoryFilter)) $qs .= '&category=' . urlencode($categoryFilter);
                            if(!empty($dateStart)) $qs .= '&date_start=' . urlencode($dateStart);
                            if(!empty($dateEnd)) $qs .= '&date_end=' . urlencode($dateEnd);
                            if($amountMin !== '') $qs .= '&amount_min=' . urlencode($amountMin);
                            if($amountMax !== '') $qs .= '&amount_max=' . urlencode($amountMax);
                        ?>
                        
                        <?php if ($page > 1): ?>
                            <a href="/?page=<?php echo $page - 1 . $qs; ?>" class="pagination-previous">Précédent</a>
                        <?php else: ?>
                            <a class="pagination-previous" disabled>Précédent</a>
                        <?php endif; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="/?page=<?php echo $page + 1 . $qs; ?>" class="pagination-next">Suivant</a>
                        <?php else: ?>
                            <a class="pagination-next" disabled>Suivant</a>
                        <?php endif; ?>

                        <ul class="pagination-list">
                            <li><span class="pagination-link is-current">Page <?php echo $page; ?> / <?php echo $totalPages; ?></span></li>
                        </ul>
                    </nav>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <!-- BULK ACTIONS -->
    <div id="bulk-actions" class="notification is-info is-light">
        <div class="level is-mobile mb-0">
            <div class="level-left">
                <span class="icon is-medium"><i class="fas fa-check-square"></i></span>
                <span class="has-text-weight-bold mr-2"><span id="selected-count">0</span> sélectionnés</span>
            </div>
            <div class="level-right">
                <div class="field has-addons mb-0">
                    <div class="control">
                        <div class="select is-small">
                            <select id="bulk-cat-select">
                                <option value="" disabled selected>Changer catégorie...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="control"><button class="button is-small is-primary" onclick="applyBulkUpdate()">Appliquer</button></div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODALE -->
    <div id="edit-category-modal" class="modal">
        <div class="modal-background" onclick="closeEditModal()"></div>
        <div class="modal-card">
            <header class="modal-card-head">
                <p class="modal-card-title">Changer la catégorie</p>
                <button class="delete" onclick="closeEditModal()"></button>
            </header>
            <section class="modal-card-body">
                <input type="hidden" id="edit-tx-id">
                <div class="field has-addons">
                    <div class="control is-expanded" id="select-control">
                        <div class="select is-fullwidth">
                            <select id="edit-cat-select">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="control is-expanded is-hidden" id="input-control">
                        <input class="input" type="text" id="new-cat-name" placeholder="Nom...">
                    </div>
                    <div class="control">
                        <button class="button is-info" id="btn-toggle-mode" onclick="toggleCreateMode()"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
            </section>
            <footer class="modal-card-foot">
                <button class="button is-success" onclick="handleSave()">Enregistrer</button>
            </footer>
        </div>
    </div>

    <script>
        const fileInput = document.querySelector('.file-input');
        if(fileInput) fileInput.onchange = () => { if(fileInput.files.length > 0) document.querySelector('.file-name').textContent = fileInput.files[0].name; }

        function toggleAdvancedFilters() {
            document.getElementById('advanced-filters').classList.toggle('is-hidden');
        }

        // --- CHART JS ---
        <?php if (!empty($stats)): ?>
        const ctx = document.getElementById('expenseChart').getContext('2d');
        const chartData = <?php echo json_encode($stats); ?>;
        const labels = chartData.map(i => i.name);
        const dataValues = chartData.map(i => Math.abs(parseFloat(i.total)));
        const colors = ['#ffe08a','#b5e3ff','#ffcccc','#cbb2fe','#ffafcc','#48c774','#00d1b2','#e5e5e5','#ffda9e','#c2f0c2'];
        new Chart(ctx, { 
            type: 'doughnut', 
            data: { labels: labels, datasets: [{ data: dataValues, backgroundColor: colors, borderWidth: 1 }] }, 
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } }, cutout: '60%' } 
        });
        <?php endif; ?>

        function updateAccount(id) {
            const name = document.getElementById('acc-name-'+id).value;
            const owner = document.getElementById('acc-owner-'+id).value;
            fetch('/?action=update_account', { method:'POST', body:JSON.stringify({id:id, name:name, owner_id:owner, type:'PERSONAL'}) })
            .then(r=>r.json()).then(d=>{ if(!d.success) alert(d.message); else location.reload(); });
        }

        function resetData() {
            if(!confirm("ATTENTION IRRÉVERSIBLE !\n\nVoulez-vous vraiment supprimer TOUS les comptes et TOUTES les transactions ?\n(Vos catégories et règles seront conservées)")) return;
            fetch('/?action=reset_data', { method:'POST' }).then(r=>r.json()).then(d=>{ if(d.success) location.reload(); else alert('Erreur : ' + d.message); });
        }

        // --- LOGIQUE SÉLECTION GLOBALE ---
        const totalTransactions = <?php echo $totalTransactions; ?>;
        const perPage = <?php echo $perPage; ?>;
        let selectAllActive = false; 

        function toggleAll(src) {
            const checkboxes = document.querySelectorAll('.tx-checkbox');
            checkboxes.forEach(cb => {
                const row = cb.closest('tr');
                if (row && row.style.display !== 'none') {
                    cb.checked = src.checked;
                }
            });
            
            // Si on coche tout et qu'il y a plus de résultats que sur la page
            if (src.checked && totalTransactions > perPage) {
                document.getElementById('select-all-message').style.display = 'block';
                document.getElementById('count-page').textContent = checkboxes.length;
                document.getElementById('count-total').textContent = totalTransactions;
            } else {
                cancelSelectAllAcross();
            }
            updateBulkUI();
        }

        function selectAllAcrossPages() {
            selectAllActive = true;
            document.getElementById('select-all-message').style.display = 'none';
            document.getElementById('select-all-confirm').style.display = 'block';
            document.getElementById('count-total-confirm').textContent = totalTransactions;
            updateBulkUI();
        }

        function cancelSelectAllAcross() {
            selectAllActive = false;
            document.getElementById('select-all-message').style.display = 'none';
            document.getElementById('select-all-confirm').style.display = 'none';
        }

        function updateBulkUI() {
            const count = document.querySelectorAll('.tx-checkbox:checked').length;
            const bar = document.getElementById('bulk-actions');
            
            if (count > 0 || selectAllActive) {
                bar.style.display = 'block';
                document.getElementById('selected-count').innerText = selectAllActive ? totalTransactions : count;
            } else {
                bar.style.display = 'none';
            }
        }

        function applyBulkUpdate() {
            const catId = document.getElementById('bulk-cat-select').value;
            if (!catId) return alert("Choisissez une catégorie");

            let payload = { category_id: catId };

            if (selectAllActive) {
                // MODE FILTRE GLOBAL
                payload.apply_to_all_filters = true;
                payload.filters = {
                    view: '<?php echo $currentView; ?>',
                    search: '<?php echo addslashes($search); ?>',
                    category: '<?php echo addslashes($categoryFilter); ?>',
                    date_start: '<?php echo $dateStart; ?>',
                    date_end: '<?php echo $dateEnd; ?>',
                    amount_min: '<?php echo $amountMin; ?>',
                    amount_max: '<?php echo $amountMax; ?>'
                };
            } else {
                // MODE IDs CLASSIQUE
                const ids = Array.from(document.querySelectorAll('.tx-checkbox:checked')).map(cb => cb.value);
                if (ids.length === 0) return;
                payload.transaction_ids = ids;
            }

            fetch('/?action=bulk_update', { 
                method:'POST', 
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload) 
            })
            .then(r=>r.json())
            .then(d=>{ 
                if(d.success) location.reload(); 
                else alert(d.message); 
            });
        }

        // --- MODALE ---
        const modal = document.getElementById('edit-category-modal'), select = document.getElementById('edit-cat-select'), input = document.getElementById('new-cat-name'), txIdInput = document.getElementById('edit-tx-id');
        let isCreateMode = false;

        function openEditModal(txId, catId) { txIdInput.value = txId; select.value = catId; isCreateMode=false; updateModalUI(); modal.classList.add('is-active'); }
        function closeEditModal() { modal.classList.remove('is-active'); }
        function toggleCreateMode() { isCreateMode = !isCreateMode; updateModalUI(); }
        
        function updateModalUI() {
            const sc=document.getElementById('select-control'), ic=document.getElementById('input-control'), btn=document.getElementById('btn-toggle-mode');
            if(isCreateMode) { 
                sc.classList.add('is-hidden'); ic.classList.remove('is-hidden'); 
                btn.classList.replace('is-info','is-danger'); btn.innerHTML='<i class="fas fa-times"></i>'; 
                input.focus(); 
            } else { 
                sc.classList.remove('is-hidden'); ic.classList.add('is-hidden'); 
                btn.classList.replace('is-danger','is-info'); btn.innerHTML='<i class="fas fa-plus"></i>'; 
            }
        }

        function handleSave() { if(isCreateMode) saveNew(); else updateTx(); }

        function saveNew() {
            if(!input.value.trim()) return;
            fetch('/?action=add_category', { method:'POST', body:JSON.stringify({name:input.value}) })
            .then(r=>r.json())
            .then(d=>{ 
                if(d.success) { 
                    const opt=document.createElement('option'); opt.value=d.category.id; opt.text=d.category.name; 
                    select.add(opt); select.value=d.category.id; 
                    isCreateMode=false; updateTx(); 
                } 
            });
        }

        function updateTx() {
            fetch('/?action=update_category', { 
                method:'POST', 
                body:JSON.stringify({transaction_id:txIdInput.value, category_id:select.value}) 
            })
            .then(r=>r.json())
            .then(d=>{ 
                if(d.success) { 
                    const b = document.getElementById('badge-'+txIdInput.value);
                    b.className = `tag clickable-badge cat-badge-${select.value} is-light`; 
                    b.textContent = select.options[select.selectedIndex].text; 
                    b.setAttribute('onclick', `openEditModal(${txIdInput.value}, ${select.value})`);
                    closeEditModal();
                } 
            });
        }
    </script>
</body>
</html>