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


async function chargerUtilisateurs() {
    const tbody = document.getElementById('corps-table-users');
    tbody.innerHTML = '<tr><td colspan="5" class="p-3 text-center">Chargement...</td></tr>';
    const roleFiltre = document.getElementById('filtre-role-user').value;
    const url = roleFiltre ? `/users?role=${roleFiltre}` : '/users';

    try {
        const res = await appelApi(url);
        tbody.innerHTML = '';
        for (const u of res.items) {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="p-3 font-semibold">${u.prenom} ${u.nom}</td>
                <td class="p-3">${u.email}</td>
                <td class="p-3">${u.username}</td>
                <td class="p-3">
                    <select class="select-role border rounded p-1 bg-white">
                        <option value="candidat" ${u.role === 'candidat' ? 'selected' : ''}>candidat</option>
                        <option value="entreprise" ${u.role === 'entreprise' ? 'selected' : ''}>entreprise</option>
                        <option value="admin" ${u.role === 'admin' ? 'selected' : ''}>admin</option>
                    </select>
                </td>
                <td class="p-3 text-right">
                    <button type="button" class="btn-suppr bg-red-100 text-red-700 px-2.5 py-1 rounded hover:bg-red-200 cursor-pointer">Supprimer</button>
                </td>
            `;

            tr.querySelector('.select-role').addEventListener('change', async (e) => {
                try {
                    await appelApi(`/users/${encodeURIComponent(u.user_id)}`, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ role: e.target.value }),
                    });
                } catch (err) {
                    alert(err.message);
                    await chargerUtilisateurs();
                }
            });

            tr.querySelector('.btn-suppr').addEventListener('click', async () => {
                if (!confirm(`Supprimer définitivement l'utilisateur ${u.email} ?`)) return;
                try {
                    await appelApi(`/users/${encodeURIComponent(u.user_id)}`, { method: 'DELETE' });
                    await chargerUtilisateurs();
                } catch (err) {
                    alert(err.message);
                }
            });

            tbody.append(tr);
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="5" class="p-3 text-red-600">${err.message}</td></tr>`;
    }
}

document.getElementById('filtre-role-user').addEventListener('change', chargerUtilisateurs);


async function chargerAnnoncesAdmin() {
    const tbody = document.getElementById('corps-table-annonces');
    tbody.innerHTML = '<tr><td colspan="5" class="p-3 text-center">Chargement...</td></tr>';
    try {
        const res = await appelApi('/annonces?page=1&per_page=50');
        tbody.innerHTML = '';
        for (const a of res.items) {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="p-3 font-mono text-xs">${a.reference}</td>
                <td class="p-3 font-semibold">${a.titre}</td>
                <td class="p-3">${a.type_contrat}</td>
                <td class="p-3">Publiée</td>
                <td class="p-3 text-right">
                    <button type="button" class="btn-suppr bg-red-100 text-red-700 px-2.5 py-1 rounded hover:bg-red-200 cursor-pointer">Supprimer</button>
                </td>
            `;
            tr.querySelector('.btn-suppr').addEventListener('click', async () => {
                if (!confirm(`Supprimer l'annonce ${a.reference} ?`)) return;
                await appelApi(`/annonces/${encodeURIComponent(a.annonce_id)}`, { method: 'DELETE' });
                await chargerAnnoncesAdmin();
            });
            tbody.append(tr);
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="5" class="p-3 text-red-600">${err.message}</td></tr>`;
    }
}


async function chargerCandidaturesAdmin() {
    const tbody = document.getElementById('corps-table-candidatures');
    tbody.innerHTML = '<tr><td colspan="4" class="p-3 text-center">Chargement...</td></tr>';
    try {
        const res = await appelApi('/candidatures?page=1&per_page=50');
        tbody.innerHTML = '';
        for (const c of res.items) {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="p-3 font-semibold">${c.candidat.prenom} ${c.candidat.nom}</td>
                <td class="p-3">${c.candidature_id.slice(0, 8)}...</td>
                <td class="p-3"><span class="bg-beige-coton px-2 py-0.5 rounded font-bold">${c.statut}</span></td>
                <td class="p-3 text-right">
                    ${c.has_cv ? `<a href="/api/candidatures/${c.candidature_id}/cv" class="text-vert-jobleaf underline mr-2">CV</a>` : ''}
                    <button type="button" class="btn-suppr bg-red-100 text-red-700 px-2.5 py-1 rounded hover:bg-red-200 cursor-pointer">Supprimer</button>
                </td>
            `;
            tr.querySelector('.btn-suppr').addEventListener('click', async () => {
                if (!confirm('Supprimer cette candidature ?')) return;
                await appelApi(`/candidatures/${encodeURIComponent(c.candidature_id)}`, { method: 'DELETE' });
                await chargerCandidaturesAdmin();
            });
            tbody.append(tr);
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="4" class="p-3 text-red-600">${err.message}</td></tr>`;
    }
}


async function chargerEntreprisesAdmin() {
    const tbody = document.getElementById('corps-table-entreprises');
    tbody.innerHTML = '<tr><td colspan="5" class="p-3 text-center">Chargement...</td></tr>';
    try {
        const res = await appelApi('/entreprises?page=1&per_page=50');
        tbody.innerHTML = '';
        for (const e of res.items) {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="p-3 font-semibold">${e.nom_entreprise}</td>
                <td class="p-3 font-mono">${e.siret ?? 'N/A'}</td>
                <td class="p-3">${e.secteur ?? ''}</td>
                <td class="p-3">${e.ville ?? ''}</td>
                <td class="p-3 text-right"></td>
            `;
            tbody.append(tr);
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="5" class="p-3 text-red-600">${err.message}</td></tr>`;
    }
}

init();