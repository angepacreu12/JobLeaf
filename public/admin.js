let userEnEdition = null;
let annonceEnEdition = null;
let entrepriseEnEdition = null;
let mapAnnonces = new Map(); // annonce_id -> { reference, titre }

async function init() {
    const moi = await chargerMoi();
    if (moi.role !== 'admin') {
        redirigerSelonRole(moi.role);
        return;
    }
    configurerOnglets();
    await chargerUtilisateurs();
}

function configurerOnglets() {
    const boutons = document.querySelectorAll('.tab-btn');
    const sections = document.querySelectorAll('.tab-content');

    for (const btn of boutons) {
        btn.addEventListener('click', async () => {
            for (const b of boutons) {
                b.className = 'tab-btn px-4 py-2 rounded-t bg-white text-vert-fonce hover:bg-vert-clair cursor-pointer';
            }
            for (const s of sections) {
                s.classList.add('hidden');
            }
            btn.className = 'tab-btn px-4 py-2 rounded-t bg-vert-jobleaf text-white cursor-pointer';
            const tabName = btn.dataset.onglet;
            document.getElementById(`tab-${tabName}`).classList.remove('hidden');

            if (tabName === 'utilisateurs') await chargerUtilisateurs();
            if (tabName === 'annonces') await chargerAnnoncesAdmin();
            if (tabName === 'candidatures') await chargerCandidaturesAdmin();
            if (tabName === 'entreprises') await chargerEntreprisesAdmin();
        });
    }
}

const strOuNull = (f, k) => {
    const v = (f.get(k) ?? '').trim();
    return v === '' ? null : v;
};

// ================= 1. UTILISATEURS (CRUD COMPLET) ================= //
const formUser = document.getElementById('form-admin-user');
const errUser = document.getElementById('err-form-user');

document.getElementById('btn-nouveau-user').addEventListener('click', () => {
    userEnEdition = null;
    formUser.reset();
    document.getElementById('titre-form-user').textContent = 'Créer un utilisateur';
    document.getElementById('bloc-mdp-user').classList.remove('hidden');
    formUser.elements.password.required = true;
    errUser.textContent = '';
    formUser.classList.remove('hidden');
});

formUser.elements.role.addEventListener('change', () => {
    const estEntr = formUser.elements.role.value === 'entreprise' && !userEnEdition;
    document.getElementById('bloc-nom-entr-user').classList.toggle('hidden', !estEntr);
});

document.getElementById('annuler-form-user').addEventListener('click', () => {
    formUser.classList.add('hidden');
    userEnEdition = null;
});

function ouvrirEditionUser(u) {
    userEnEdition = u;
    formUser.reset();
    document.getElementById('titre-form-user').textContent = `Modifier ${u.username}`;
    document.getElementById('bloc-mdp-user').classList.add('hidden');
    document.getElementById('bloc-nom-entr-user').classList.add('hidden');
    formUser.elements.password.required = false;

    formUser.elements.prenom.value = u.prenom ?? '';
    formUser.elements.nom.value = u.nom ?? '';
    formUser.elements.username.value = u.username ?? '';
    formUser.elements.email.value = u.email ?? '';
    formUser.elements.telephone.value = u.telephone ?? '';
    formUser.elements.role.value = u.role;

    errUser.textContent = '';
    formUser.classList.remove('hidden');
    formUser.scrollIntoView({ behavior: 'smooth' });
}

formUser.addEventListener('submit', async (e) => {
    e.preventDefault();
    errUser.textContent = '';
    const f = new FormData(formUser);

    try {
        if (!userEnEdition) {
            const corps = {
                prenom: f.get('prenom').trim(),
                nom: f.get('nom').trim(),
                username: f.get('username').trim(),
                email: f.get('email').trim(),
                telephone: strOuNull(f, 'telephone'),
                role: f.get('role'),
                password: f.get('password'),
            };
            if (corps.role === 'entreprise') {
                corps.nom_entreprise = f.get('nom_entreprise').trim();
            }
            await appelApi('/users', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(corps),
            });
        } else {
            const modifs = {};
            for (const cle of ['prenom', 'nom', 'username', 'email', 'telephone', 'role']) {
                const val = strOuNull(f, cle);
                if (val !== (userEnEdition[cle] ?? null)) {
                    modifs[cle] = val;
                }
            }
            if (Object.keys(modifs).length > 0) {
                await appelApi(`/users/${encodeURIComponent(userEnEdition.user_id)}`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(modifs),
                });
            }
        }
        formUser.classList.add('hidden');
        userEnEdition = null;
        await chargerUtilisateurs();
    } catch (err) {
        errUser.textContent = err.message;
    }
});

async function chargerUtilisateurs() {
    const tbody = document.getElementById('corps-table-users');
    tbody.innerHTML = '<tr><td colspan="6" class="p-3 text-center">Chargement...</td></tr>';
    const roleFiltre = document.getElementById('filtre-role-user').value;
    const url = roleFiltre ? `/users?role=${roleFiltre}&per_page=50` : '/users?per_page=50';

    try {
        const res = await appelApi(url);
        tbody.innerHTML = '';
        for (const u of res.items) {
            const tr = document.createElement('tr');
            const tdNom = document.createElement('td');
            tdNom.className = 'p-3 font-semibold';
            tdNom.textContent = `${u.prenom ?? ''} ${u.nom ?? ''}`.trim() || '(Sans nom)';

            const tdEmail = document.createElement('td');
            tdEmail.className = 'p-3';
            tdEmail.textContent = u.email ?? '';

            const tdUser = document.createElement('td');
            tdUser.className = 'p-3';
            tdUser.textContent = u.username ?? '';

            const tdTel = document.createElement('td');
            tdTel.className = 'p-3';
            tdTel.textContent = u.telephone ?? '—';

            const tdRole = document.createElement('td');
            tdRole.className = 'p-3';
            tdRole.innerHTML = `<span class="bg-beige-coton px-2 py-0.5 rounded font-bold">${u.role}</span>`;

            const tdActions = document.createElement('td');
            tdActions.className = 'p-3 text-right whitespace-nowrap';
            tdActions.innerHTML = `
                <button type="button" class="btn-edit bg-vert-clair text-vert-fonce px-2.5 py-1 rounded hover:bg-vert-jobleaf hover:text-white mr-1 cursor-pointer font-semibold">Modifier</button>
                <button type="button" class="btn-suppr bg-red-100 text-red-700 px-2.5 py-1 rounded hover:bg-red-200 cursor-pointer font-semibold">Supprimer</button>
            `;

            tdActions.querySelector('.btn-edit').addEventListener('click', () => ouvrirEditionUser(u));
            tdActions.querySelector('.btn-suppr').addEventListener('click', async () => {
                if (!confirm(`Supprimer définitivement le compte ${u.email} ?`)) return;
                await appelApi(`/users/${encodeURIComponent(u.user_id)}`, { method: 'DELETE' });
                await chargerUtilisateurs();
            });

            tr.append(tdNom, tdEmail, tdUser, tdTel, tdRole, tdActions);
            tbody.append(tr);
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="6" class="p-3 text-red-600">${err.message}</td></tr>`;
    }
}

document.getElementById('filtre-role-user').addEventListener('change', chargerUtilisateurs);

// ================= 2. ANNONCES (CRUD COMPLET) ================= //
const formAnnonceAdmin = document.getElementById('form-admin-annonce');
const errAnnonceAdmin = document.getElementById('err-form-annonce-admin');

async function peuplerSelectEntreprises() {
    const select = formAnnonceAdmin.elements.entreprise_id;
    select.innerHTML = '';
    const res = await appelApi('/entreprises?per_page=50');
    for (const e of res.items) {
        const opt = document.createElement('option');
        opt.value = e.user_id;
        opt.textContent = e.nom_entreprise;
        select.append(opt);
    }
}

document.getElementById('btn-nouvelle-annonce-admin').addEventListener('click', async () => {
    annonceEnEdition = null;
    formAnnonceAdmin.reset();
    await peuplerSelectEntreprises();
    document.getElementById('bloc-entreprise-annonce').classList.remove('hidden');
    document.getElementById('titre-form-annonce-admin').textContent = 'Créer une annonce';
    errAnnonceAdmin.textContent = '';
    formAnnonceAdmin.classList.remove('hidden');
});

document.getElementById('annuler-form-annonce-admin').addEventListener('click', () => {
    formAnnonceAdmin.classList.add('hidden');
    annonceEnEdition = null;
});

async function ouvrirEditionAnnonceAdmin(annonceId) {
    try {
        const d = await appelApi(`/annonces/${encodeURIComponent(annonceId)}`);
        annonceEnEdition = d;
        document.getElementById('bloc-entreprise-annonce').classList.add('hidden');
        document.getElementById('titre-form-annonce-admin').textContent = `Modifier ${d.reference} — ${d.titre}`;

        formAnnonceAdmin.elements.titre.value = d.titre ?? '';
        formAnnonceAdmin.elements.type_contrat.value = d.type_contrat ?? 'CDI';
        formAnnonceAdmin.elements.category.value = d.category ?? '';
        formAnnonceAdmin.elements.teletravail.value = d.teletravail ?? '';
        formAnnonceAdmin.elements.ville.value = d.ville ?? '';
        formAnnonceAdmin.elements.salaire_min.value = d.salaire_min ?? '';
        formAnnonceAdmin.elements.salaire_max.value = d.salaire_max ?? '';
        formAnnonceAdmin.elements.niveau_experience.value = d.niveau_experience ?? '';
        formAnnonceAdmin.elements.date_expiration.value = d.date_expiration ? d.date_expiration.slice(0, 10) : '';
        formAnnonceAdmin.elements.statut.value = d.statut ?? 'publiee';
        formAnnonceAdmin.elements.description.value = d.description ?? '';

        errAnnonceAdmin.textContent = '';
        formAnnonceAdmin.classList.remove('hidden');
        formAnnonceAdmin.scrollIntoView({ behavior: 'smooth' });
    } catch (err) {
        alert(err.message);
    }
}

formAnnonceAdmin.addEventListener('submit', async (e) => {
    e.preventDefault();
    errAnnonceAdmin.textContent = '';
    const f = new FormData(formAnnonceAdmin);
    const intOuNull = (k) => {
        const v = (f.get(k) ?? '').trim();
        return v === '' ? null : Number(v);
    };

    const donnees = {
        titre: f.get('titre').trim(),
        description: f.get('description').trim(),
        type_contrat: f.get('type_contrat'),
        category: strOuNull(f, 'category'),
        teletravail: strOuNull(f, 'teletravail'),
        ville: strOuNull(f, 'ville'),
        salaire_min: intOuNull('salaire_min'),
        salaire_max: intOuNull('salaire_max'),
        niveau_experience: strOuNull(f, 'niveau_experience'),
        date_expiration: strOuNull(f, 'date_expiration'),
        statut: f.get('statut'),
    };

    try {
        if (!annonceEnEdition) {
            donnees.entreprise_id = f.get('entreprise_id');
            await appelApi('/annonces', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(donnees),
            });
        } else {
            await appelApi(`/annonces/${encodeURIComponent(annonceEnEdition.annonce_id)}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(donnees),
            });
        }
        formAnnonceAdmin.classList.add('hidden');
        annonceEnEdition = null;
        await chargerAnnoncesAdmin();
    } catch (err) {
        errAnnonceAdmin.textContent = err.message;
    }
});

async function chargerAnnoncesAdmin() {
    const tbody = document.getElementById('corps-table-annonces');
    tbody.innerHTML = '<tr><td colspan="7" class="p-3 text-center">Chargement...</td></tr>';
    try {
        const res = await appelApi('/annonces?all=1&page=1&per_page=50');
        tbody.innerHTML = '';
        mapAnnonces.clear();

        for (const a of res.items) {
            mapAnnonces.set(a.annonce_id, { reference: a.reference, titre: a.titre });

            const tr = document.createElement('tr');
            const tdRef = document.createElement('td');
            tdRef.className = 'p-3 font-mono font-bold whitespace-nowrap';
            tdRef.textContent = a.reference;

            const tdTitre = document.createElement('td');
            tdTitre.className = 'p-3 font-semibold';
            tdTitre.textContent = a.titre;

            const tdEntr = document.createElement('td');
            tdEntr.className = 'p-3';
            tdEntr.textContent = a.entreprise?.nom_entreprise ?? '';

            const tdContrat = document.createElement('td');
            tdContrat.className = 'p-3';
            tdContrat.textContent = a.type_contrat;

            const tdVille = document.createElement('td');
            tdVille.className = 'p-3';
            tdVille.textContent = a.ville ?? '—';

            const tdStatut = document.createElement('td');
            tdStatut.className = 'p-3';
            tdStatut.innerHTML = `<span class="bg-beige-coton px-2 py-0.5 rounded font-bold">${a.statut ?? 'publiee'}</span>`;

            const tdActions = document.createElement('td');
            tdActions.className = 'p-3 text-right whitespace-nowrap';
            tdActions.innerHTML = `
                <button type="button" class="btn-edit bg-vert-clair text-vert-fonce px-2.5 py-1 rounded hover:bg-vert-jobleaf hover:text-white mr-1 cursor-pointer font-semibold">Modifier</button>
                <button type="button" class="btn-suppr bg-red-100 text-red-700 px-2.5 py-1 rounded hover:bg-red-200 cursor-pointer font-semibold">Supprimer</button>
            `;

            tdActions.querySelector('.btn-edit').addEventListener('click', () => ouvrirEditionAnnonceAdmin(a.annonce_id));
            tdActions.querySelector('.btn-suppr').addEventListener('click', async () => {
                if (!confirm(`Supprimer l'annonce ${a.reference} ?`)) return;
                await appelApi(`/annonces/${encodeURIComponent(a.annonce_id)}`, { method: 'DELETE' });
                await chargerAnnoncesAdmin();
            });

            tr.append(tdRef, tdTitre, tdEntr, tdContrat, tdVille, tdStatut, tdActions);
            tbody.append(tr);
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="7" class="p-3 text-red-600">${err.message}</td></tr>`;
    }
}

// ================= 3. CANDIDATURES (CRUD COMPLET + REF ANNONCE) ================= //
async function chargerCandidaturesAdmin() {
    const tbody = document.getElementById('corps-table-candidatures');
    tbody.innerHTML = '<tr><td colspan="5" class="p-3 text-center">Chargement...</td></tr>';

    // S'assure d'avoir la liste des annonces pour le filtre et les références
    if (mapAnnonces.size === 0) {
        try {
            const resAnn = await appelApi('/annonces?all=1&page=1&per_page=50');
            const selectAnn = document.getElementById('filtre-annonce-cand');
            selectAnn.innerHTML = '<option value="">Toutes les annonces</option>';
            for (const a of resAnn.items) {
                mapAnnonces.set(a.annonce_id, { reference: a.reference, titre: a.titre });
                const opt = document.createElement('option');
                opt.value = a.annonce_id;
                opt.textContent = `${a.reference} — ${a.titre}`;
                selectAnn.append(opt);
            }
        } catch {
        }
    }

    const params = new URLSearchParams({ page: '1', per_page: '50' });
    const statut = document.getElementById('filtre-statut-cand').value;
    const annonceId = document.getElementById('filtre-annonce-cand').value;
    if (statut) params.set('statut', statut);
    if (annonceId) params.set('annonce_id', annonceId);

    try {
        const res = await appelApi('/candidatures?' + params);
        tbody.innerHTML = '';
        for (const c of res.items) {
            const tr = document.createElement('tr');

            const tdCand = document.createElement('td');
            tdCand.className = 'p-3';
            const nomCand = `${c.candidat.prenom ?? ''} ${c.candidat.nom ?? ''}`.trim() || '(Anonyme)';
            tdCand.innerHTML = `<div class="font-bold"></div><div class="text-gray-500"></div>`;
            tdCand.children[0].textContent = nomCand;
            tdCand.children[1].textContent = [c.candidat.email, c.candidat.telephone].filter(Boolean).join(' • ');

            // Affiche la référence complète + le titre de l'annonce
            const tdAnnonce = document.createElement('td');
            tdAnnonce.className = 'p-3';
            const ref = c.annonce?.reference ?? mapAnnonces.get(c.annonce?.annonce_id)?.reference ?? c.candidature_id;
            const titre = c.annonce?.titre ?? mapAnnonces.get(c.annonce?.annonce_id)?.titre ?? '';
            tdAnnonce.innerHTML = `<span class="font-mono font-bold bg-beige-coton px-2 py-0.5 rounded block w-fit"></span><span class="text-gray-700 block mt-0.5"></span>`;
            tdAnnonce.children[0].textContent = ref;
            tdAnnonce.children[1].textContent = titre;

            const tdMsg = document.createElement('td');
            tdMsg.className = 'p-3 max-w-xs truncate';
            tdMsg.title = c.message ?? '';
            tdMsg.textContent = c.message ?? '—';

            //Modification directe du statut de la candidature (PATCH /api/candidatures/{id})
            const tdStatut = document.createElement('td');
            tdStatut.className = 'p-3';
            const selectStatut = document.createElement('select');
            selectStatut.className = 'border border-vert-jobleaf rounded p-1 bg-white font-semibold';
            for (const st of ['envoyee', 'vue', 'entretien', 'acceptee', 'refusee']) {
                const opt = document.createElement('option');
                opt.value = st;
                opt.textContent = st;
                if (c.statut === st) opt.selected = true;
                selectStatut.append(opt);
            }
            selectStatut.addEventListener('change', async () => {
                try {
                    await appelApi(`/candidatures/${encodeURIComponent(c.candidature_id)}`, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ statut: selectStatut.value }),
                    });
                } catch (err) {
                    alert(err.message);
                }
            });
            tdStatut.append(selectStatut);

            const tdActions = document.createElement('td');
            tdActions.className = 'p-3 text-right whitespace-nowrap';
            if (c.has_cv) {
                const aCv = document.createElement('a');
                aCv.href = `/api/candidatures/${encodeURIComponent(c.candidature_id)}/cv`;
                aCv.className = 'bg-beige-coton text-vert-fonce px-2.5 py-1 rounded hover:bg-vert-clair mr-1 font-semibold';
                aCv.textContent = 'CV';
                tdActions.append(aCv);
            }
            const btnSuppr = document.createElement('button');
            btnSuppr.type = 'button';
            btnSuppr.className = 'bg-red-100 text-red-700 px-2.5 py-1 rounded hover:bg-red-200 cursor-pointer font-semibold';
            btnSuppr.textContent = 'Supprimer';
            btnSuppr.addEventListener('click', async () => {
                if (!confirm('Supprimer cette candidature ?')) return;
                await appelApi(`/candidatures/${encodeURIComponent(c.candidature_id)}`, { method: 'DELETE' });
                await chargerCandidaturesAdmin();
            });
            tdActions.append(btnSuppr);

            tr.append(tdCand, tdAnnonce, tdMsg, tdStatut, tdActions);
            tbody.append(tr);
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="5" class="p-3 text-red-600">${err.message}</td></tr>`;
    }
}

document.getElementById('filtre-statut-cand').addEventListener('change', chargerCandidaturesAdmin);
document.getElementById('filtre-annonce-cand').addEventListener('change', chargerCandidaturesAdmin);

// ================= 4. ENTREPRISES (CRUD COMPLET) ================= //
const formEntrAdmin = document.getElementById('form-admin-entreprise');
const errEntrAdmin = document.getElementById('err-form-entreprise-admin');

document.getElementById('annuler-form-entreprise-admin').addEventListener('click', () => {
    formEntrAdmin.classList.add('hidden');
    entrepriseEnEdition = null;
});

function ouvrirEditionEntrepriseAdmin(e) {
    entrepriseEnEdition = e;
    document.getElementById('titre-form-entreprise-admin').textContent = `Modifier ${e.nom_entreprise}`;
    formEntrAdmin.elements.nom_entreprise.value = e.nom_entreprise ?? '';
    formEntrAdmin.elements.siret.value = e.siret ?? '';
    formEntrAdmin.elements.secteur.value = e.secteur ?? '';
    formEntrAdmin.elements.ville.value = e.ville ?? '';
    formEntrAdmin.elements.site_web.value = e.site_web ?? '';
    formEntrAdmin.elements.description.value = e.description ?? '';
    errEntrAdmin.textContent = '';
    formEntrAdmin.classList.remove('hidden');
    formEntrAdmin.scrollIntoView({ behavior: 'smooth' });
}

formEntrAdmin.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    if (!entrepriseEnEdition) return;
    errEntrAdmin.textContent = '';
    const f = new FormData(formEntrAdmin);

    const corps = {
        nom_entreprise: f.get('nom_entreprise').trim(),
        siret: strOuNull(f, 'siret'),
        secteur: strOuNull(f, 'secteur'),
        ville: strOuNull(f, 'ville'),
        site_web: strOuNull(f, 'site_web'),
        description: strOuNull(f, 'description'),
    };

    try {
        await appelApi(`/entreprises/${encodeURIComponent(entrepriseEnEdition.user_id)}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(corps),
        });
        formEntrAdmin.classList.add('hidden');
        entrepriseEnEdition = null;
        await chargerEntreprisesAdmin();
    } catch (err) {
        errEntrAdmin.textContent = err.message;
    }
});

async function chargerEntreprisesAdmin() {
    const tbody = document.getElementById('corps-table-entreprises');
    tbody.innerHTML = '<tr><td colspan="6" class="p-3 text-center">Chargement...</td></tr>';
    try {
        const res = await appelApi('/entreprises?page=1&per_page=50');
        tbody.innerHTML = '';
        for (const e of res.items) {
            const tr = document.createElement('tr');

            const tdNom = document.createElement('td');
            tdNom.className = 'p-3 font-semibold';
            tdNom.textContent = e.nom_entreprise;

            const tdSiret = document.createElement('td');
            tdSiret.className = 'p-3 font-mono';
            tdSiret.textContent = e.siret ?? '—';

            const tdSecteur = document.createElement('td');
            tdSecteur.className = 'p-3';
            tdSecteur.textContent = e.secteur ?? '—';

            const tdVille = document.createElement('td');
            tdVille.className = 'p-3';
            tdVille.textContent = e.ville ?? '—';

            const tdSite = document.createElement('td');
            tdSite.className = 'p-3';
            tdSite.textContent = e.site_web ?? '—';

            const tdActions = document.createElement('td');
            tdActions.className = 'p-3 text-right whitespace-nowrap';
            tdActions.innerHTML = `
                <button type="button" class="btn-edit bg-vert-clair text-vert-fonce px-2.5 py-1 rounded hover:bg-vert-jobleaf hover:text-white mr-1 cursor-pointer font-semibold">Modifier</button>
                <button type="button" class="btn-suppr bg-red-100 text-red-700 px-2.5 py-1 rounded hover:bg-red-200 cursor-pointer font-semibold">Supprimer</button>
            `;

            tdActions.querySelector('.btn-edit').addEventListener('click', () => ouvrirEditionEntrepriseAdmin(e));
            tdActions.querySelector('.btn-suppr').addEventListener('click', async () => {
                if (!confirm(`Supprimer le compte entreprise ${e.nom_entreprise} et toutes ses offres ?`)) return;
                await appelApi(`/users/${encodeURIComponent(e.user_id)}`, { method: 'DELETE' });
                await chargerEntreprisesAdmin();
            });

            tr.append(tdNom, tdSiret, tdSecteur, tdVille, tdSite, tdActions);
            tbody.append(tr);
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="6" class="p-3 text-red-600">${err.message}</td></tr>`;
    }
}

init();