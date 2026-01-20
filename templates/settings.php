<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BudgetMaster CA - Paramètres</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="has-background-light">
    
    <nav class="navbar is-info" role="navigation" aria-label="main navigation">
        <div class="navbar-brand"><a class="navbar-item has-text-weight-bold" href="/">BudgetMaster CA</a></div>
        <div class="navbar-menu">
            <div class="navbar-start">
                <a href="/" class="navbar-item"><span class="icon"><i class="fas fa-home mr-2"></i></span><span>Dashboard</span></a>
                <a href="/?action=recurrence" class="navbar-item"><span class="icon"><i class="fas fa-sync-alt mr-2"></i></span><span>Récurrences</span></a>
                <a href="/?action=settings" class="navbar-item is-active"><span class="icon"><i class="fas fa-cogs mr-2"></i></span><span>Paramètres</span></a>
            </div>
        </div>
    </nav>

    <div class="container mt-5 mb-6">
        <div class="columns">
            
            <!-- GAUCHE : Règles -->
            <div class="column is-two-thirds">
                <div class="box">
                    <h2 class="title is-5"><i class="fas fa-magic"></i> Règles d'Automatisation</h2>
                    <div class="notification is-light p-3">
                        <h6 class="title is-7 mb-2">Nouvelle Règle</h6>
                        <div class="field is-grouped is-grouped-multiline">
                            <div class="control is-expanded"><input class="input is-small" type="text" id="rule-pattern" placeholder="Si contient (ex: PAYPAL)..."></div>
                            <div class="control" style="width: 100px;"><input class="input is-small" type="number" step="0.01" id="rule-amount" placeholder="€ (Opt.)"></div>
                            <div class="control is-expanded"><input class="input is-small" type="text" id="rule-exclusion" placeholder="Sauf si..."></div>
                            <div class="control is-expanded"><input class="input is-small" type="text" id="rule-label" placeholder="Renommer en..."></div>
                            <div class="control"><div class="select is-small"><select id="rule-category">
                                <?php foreach ($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?>
                            </select></div></div>
                            <div class="control"><label class="checkbox is-size-7 mt-1"><input type="checkbox" id="rule-recurring"> Récurrent</label></div>
                            <div class="control"><button class="button is-small is-primary" onclick="addRule()"><i class="fas fa-plus"></i></button></div>
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="table is-striped is-fullwidth is-hoverable is-size-7">
                            <thead><tr><th>Pattern</th><th>Montant</th><th>Exclusion</th><th>Renommer</th><th>Catégorie</th><th>Rec.</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($rules as $rule): ?>
                                    <tr>
                                        <td class="has-text-weight-bold is-family-monospace"><?php echo htmlspecialchars($rule['pattern']); ?></td>
                                        <td class="has-text-info"><?php if(isset($rule['amount_match']) && $rule['amount_match']!==null) echo number_format((float)$rule['amount_match'],2); ?></td>
                                        <td class="has-text-danger"><?php if(!empty($rule['exclusion'])) echo htmlspecialchars($rule['exclusion']); ?></td>
                                        <td class="has-text-success"><?php if(!empty($rule['custom_label'])) echo htmlspecialchars($rule['custom_label']); ?></td>
                                        <td><span class="tag is-light"><?php echo htmlspecialchars($rule['category_name']); ?></span></td>
                                        <td class="has-text-centered"><?php if($rule['is_recurring']): ?><span class="icon has-text-info"><i class="fas fa-sync-alt"></i></span><?php endif; ?></td>
                                        <td class="has-text-right">
                                            <button class="button is-small is-info is-inverted p-1" onclick="openEditRuleModal(<?php echo $rule['id']; ?>, '<?php echo addslashes($rule['pattern']); ?>', '<?php echo addslashes($rule['exclusion']??''); ?>', '<?php echo $rule['amount_match']??''; ?>', '<?php echo addslashes($rule['custom_label']??''); ?>', <?php echo $rule['category_id']; ?>, <?php echo $rule['is_recurring']; ?>)"><i class="fas fa-pen"></i></button>
                                            <button class="button is-small is-danger is-inverted p-1" onclick="deleteRule(<?php echo $rule['id']; ?>)"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- DROITE : Catégories -->
            <div class="column">
                <div class="box">
                    <h2 class="title is-5"><i class="fas fa-tags"></i> Catégories</h2>
                    
                    <!-- Formulaire Ajout Catégorie avec Type -->
                    <div class="field has-addons mb-4">
                        <div class="control">
                            <span class="select is-small">
                                <select id="new-cat-type">
                                    <option value="EXPENSE">Dépense</option>
                                    <option value="INCOME">Revenu</option>
                                    <option value="TRANSFER">Virement</option>
                                </select>
                            </span>
                        </div>
                        <div class="control is-expanded">
                            <input class="input is-small" type="text" id="new-cat-name" placeholder="Nouvelle catégorie...">
                        </div>
                        <div class="control">
                            <button class="button is-small is-success" onclick="addCategory()">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>

                    <ul id="categories-list" style="max-height: 70vh; overflow-y: auto;">
                        <?php foreach ($categories as $cat): ?>
                            <li class="mb-2">
                                <div class="tags has-addons is-fullwidth" style="cursor: pointer;" onclick="openEditCategoryModal(<?php echo $cat['id']; ?>, '<?php echo addslashes($cat['name']); ?>', '<?php echo $cat['type']; ?>')">
                                    <!-- Badge Type -->
                                    <?php if($cat['type'] === 'INCOME'): ?>
                                        <span class="tag is-success"><i class="fas fa-arrow-up"></i></span>
                                    <?php elseif($cat['type'] === 'TRANSFER'): ?>
                                        <span class="tag is-info"><i class="fas fa-exchange-alt"></i></span>
                                    <?php else: ?>
                                        <span class="tag is-warning"><i class="fas fa-arrow-down"></i></span>
                                    <?php endif; ?>
                                    
                                    <!-- Nom -->
                                    <span class="tag is-light is-expanded" style="justify-content: flex-start;"><?php echo htmlspecialchars($cat['name']); ?></span>
                                    
                                    <!-- Icone Edit -->
                                    <span class="tag is-white"><i class="fas fa-pen is-size-7 has-text-grey-light"></i></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

        </div>
    </div>

    <!-- MODALE ÉDITION RÈGLE (Inchangée) -->
    <div id="edit-rule-modal" class="modal">
        <div class="modal-background" onclick="closeEditRuleModal()"></div>
        <div class="modal-card">
            <header class="modal-card-head"><p class="modal-card-title">Modifier la règle</p><button class="delete" onclick="closeEditRuleModal()"></button></header>
            <section class="modal-card-body">
                <input type="hidden" id="edit-rule-id">
                <div class="field"><label class="label is-small">Si contient</label><div class="control"><input class="input" type="text" id="edit-rule-pattern"></div></div>
                <div class="field"><label class="label is-small">Montant exact</label><div class="control"><input class="input" type="number" step="0.01" id="edit-rule-amount"></div></div>
                <div class="field"><label class="label is-small">Sauf si</label><div class="control"><input class="input" type="text" id="edit-rule-exclusion"></div></div>
                <div class="field"><label class="label is-small">Renommer</label><div class="control"><input class="input" type="text" id="edit-rule-label"></div></div>
                <div class="field"><label class="label is-small">Catégorie</label><div class="control"><div class="select is-fullwidth"><select id="edit-rule-category"><?php foreach ($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select></div></div></div>
                <div class="field"><div class="control"><label class="checkbox"><input type="checkbox" id="edit-rule-recurring"> Marquer comme récurrent</label></div></div>
            </section>
            <footer class="modal-card-foot"><button class="button is-success" onclick="saveEditedRule()">Enregistrer</button><button class="button" onclick="closeEditRuleModal()">Annuler</button></footer>
        </div>
    </div>

    <!-- NOUVEAU : MODALE ÉDITION CATÉGORIE -->
    <div id="edit-cat-modal" class="modal">
        <div class="modal-background" onclick="closeEditCatModal()"></div>
        <div class="modal-card">
            <header class="modal-card-head"><p class="modal-card-title">Modifier la catégorie</p><button class="delete" onclick="closeEditCatModal()"></button></header>
            <section class="modal-card-body">
                <input type="hidden" id="edit-cat-id">
                <div class="field">
                    <label class="label">Nom</label>
                    <div class="control">
                        <input class="input" type="text" id="edit-cat-name">
                    </div>
                </div>
                <div class="field">
                    <label class="label">Type</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select id="edit-cat-type">
                                <option value="EXPENSE">Dépense (Sortie)</option>
                                <option value="INCOME">Revenu (Entrée)</option>
                                <option value="TRANSFER">Virement / Neutre</option>
                            </select>
                        </div>
                    </div>
                </div>
            </section>
            <footer class="modal-card-foot">
                <button class="button is-success" onclick="saveEditedCategory()">Enregistrer</button>
                <button class="button" onclick="closeEditCatModal()">Annuler</button>
            </footer>
        </div>
    </div>

    <script>
        // --- RÈGLES ---
        function addRule() {
            const pattern = document.getElementById('rule-pattern').value.trim();
            const exclusion = document.getElementById('rule-exclusion').value.trim();
            const amount = document.getElementById('rule-amount').value;
            const label = document.getElementById('rule-label').value.trim();
            const catId = document.getElementById('rule-category').value;
            const isRecurring = document.getElementById('rule-recurring').checked ? 1 : 0;

            if (!pattern) return alert('Pattern vide !');

            fetch('/?action=add_rule', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pattern: pattern, exclusion: exclusion, amount_match: amount, custom_label: label, category_id: catId, is_recurring: isRecurring })
            }).then(r => r.json()).then(d => { if (d.success) location.reload(); else alert(d.message); });
        }

        function deleteRule(id) {
            if (confirm('Supprimer ?')) fetch('/?action=delete_rule', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id }) }).then(r => r.json()).then(d => location.reload());
        }

        // --- CATÉGORIES ---
        function addCategory() {
            const name = document.getElementById('new-cat-name').value.trim();
            const type = document.getElementById('new-cat-type').value;
            if (!name) return alert("Le nom est vide !");
            
            fetch('/?action=add_category', { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/json' }, 
                body: JSON.stringify({ name: name, type: type }) 
            })
            .then(res => res.json()).then(data => { if (data.success) location.reload(); else alert('Erreur : ' + data.message); });
        }

        // --- GESTION MODALE RÈGLE ---
        const editModal = document.getElementById('edit-rule-modal');
        function openEditRuleModal(id, pat, excl, amt, lbl, cat, rec) {
            document.getElementById('edit-rule-id').value = id;
            document.getElementById('edit-rule-pattern').value = pat;
            document.getElementById('edit-rule-exclusion').value = excl;
            document.getElementById('edit-rule-amount').value = amt;
            document.getElementById('edit-rule-label').value = lbl;
            document.getElementById('edit-rule-category').value = cat;
            document.getElementById('edit-rule-recurring').checked = (rec == 1);
            editModal.classList.add('is-active');
        }
        function closeEditRuleModal() { editModal.classList.remove('is-active'); }
        
        function saveEditedRule() {
            const id = document.getElementById('edit-rule-id').value;
            const pattern = document.getElementById('edit-rule-pattern').value.trim();
            const exclusion = document.getElementById('edit-rule-exclusion').value.trim();
            const amount = document.getElementById('edit-rule-amount').value;
            const label = document.getElementById('edit-rule-label').value.trim();
            const catId = document.getElementById('edit-rule-category').value;
            const isRecurring = document.getElementById('edit-rule-recurring').checked ? 1 : 0;

            fetch('/?action=update_rule', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, pattern: pattern, exclusion: exclusion, amount_match: amount, custom_label: label, category_id: catId, is_recurring: isRecurring })
            }).then(res => res.json()).then(d => { if (d.success) location.reload(); else alert(d.message); });
        }

        // --- GESTION MODALE CATÉGORIE ---
        const editCatModal = document.getElementById('edit-cat-modal');
        
        function openEditCategoryModal(id, name, type) {
            document.getElementById('edit-cat-id').value = id;
            document.getElementById('edit-cat-name').value = name;
            document.getElementById('edit-cat-type').value = type;
            editCatModal.classList.add('is-active');
        }
        
        function closeEditCatModal() { editCatModal.classList.remove('is-active'); }
        
        function saveEditedCategory() {
            const id = document.getElementById('edit-cat-id').value;
            const name = document.getElementById('edit-cat-name').value;
            const type = document.getElementById('edit-cat-type').value;
            
            if(!name) return alert('Nom vide');
            
            fetch('/?action=update_category_name', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, name: name, type: type })
            }).then(res => res.json()).then(d => { if (d.success) location.reload(); else alert(d.message); });
        }
    </script>
</body>
</html>