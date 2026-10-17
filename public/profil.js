let profilInitial = null;
let entrepriseInitiale = null;

async function init() {
    const moi = await chargerMoi();

    try {
        profilInitial = await appelApi('/me');
        const fP = document.getElementById('form-profil');
        fP.elements.prenom.value = profilInitial.prenom ?? '';
        fP.elements.nom.value = profilInitial.nom ?? '';
        fP.elements.username.value = profilInitial.username ?? '';
        fP.elements.email.value = profilInitial.email ?? '';
        fP.elements.telephone.value = profilInitial.telephone ?? '';
    } catch (err) {
        alert(err.message);
    }

    if (moi.role === 'entreprise') {
        const fE = document.getElementById('form-entreprise');
        fE.classList.remove('hidden');
        try {
            entrepriseInitiale = await appelApi(`/entreprises/${encodeURIComponent(moi.user_id)}`);
            fE.elements.nom_entreprise.value = entrepriseInitiale.nom_entreprise ?? '';
            fE.elements.siret.value = entrepriseInitiale.siret ?? '';
            fE.elements.secteur.value = entrepriseInitiale.secteur ?? '';
            fE.elements.ville.value = entrepriseInitiale.ville ?? '';
            fE.elements.site_web.value = entrepriseInitiale.site_web ?? '';
            fE.elements.description.value = entrepriseInitiale.description ?? '';
        } catch (err) {
            document.getElementById('msg-entreprise').textContent = err.message;
            document.getElementById('msg-entreprise').className = 'text-red-600 font-semibold';
        }
    }
}


document.getElementById('form-profil').addEventListener('submit', async (e) => {
    e.preventDefault();
    const msg = document.getElementById('msg-profil');
    msg.textContent = '';

    const f = new FormData(e.target);
    const modifs = {};

    const chk = (cle) => {
        const val = (f.get(cle) ?? '').trim();
        const finalVal = val === '' ? null : val;
        if (finalVal !== (profilInitial[cle] ?? null)) {
            modifs[cle] = finalVal;
        }
    };

    chk('prenom');
    chk('nom');
    chk('username');
    chk('email');
    chk('telephone');

    if (Object.keys(modifs).length === 0) {
        msg.textContent = 'Aucune modification détectée.';
        msg.className = 'text-gray-600 font-semibold';
        return;
    }

    try {
        profilInitial = await appelApi('/me', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(modifs),
        });
        msg.textContent = 'Profil mis à jour avec succès !';
        msg.className = 'text-vert-jobleaf font-semibold';
    } catch (err) {
        msg.textContent = err.message;
        msg.className = 'text-red-600 font-semibold';
    }
});


const formEntr = document.getElementById('form-entreprise');
formEntr.addEventListener('submit', async (e) => {
    e.preventDefault();
    const msg = document.getElementById('msg-entreprise');
    msg.textContent = '';

    const f = new FormData(formEntr);
    const modifs = {};
    const chkE = (cle) => {
        const val = (f.get(cle) ?? '').trim();
        const finalVal = val === '' ? null : val;
        if (finalVal !== (entrepriseInitiale[cle] ?? null)) {
            modifs[cle] = finalVal;
        }
    };

    chkE('nom_entreprise');
    chkE('siret');
    chkE('secteur');
    chkE('ville');
    chkE('site_web');
    chkE('description');

    if (Object.keys(modifs).length === 0) {
        msg.textContent = 'Aucune modification entreprise détectée.';
        msg.className = 'text-gray-600 font-semibold';
        return;
    }

    try {
        entrepriseInitiale = await appelApi(`/entreprises/${encodeURIComponent(profilInitial.user_id)}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(modifs),
        });
        msg.textContent = 'Informations entreprise mises à jour !';
        msg.className = 'text-vert-jobleaf font-semibold';
    } catch (err) {
        msg.textContent = err.message;
        msg.className = 'text-red-600 font-semibold';
    }
});


const formMdp = document.getElementById('form-mdp');
formMdp.addEventListener('submit', async (e) => {
    e.preventDefault();
    const msg = document.getElementById('msg-mdp');
    msg.textContent = '';
    const f = new FormData(formMdp);

    try {
        await appelApi('/me/password', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                old_password: f.get('old_password'),
                new_password: f.get('new_password'),
            }),
        });
        msg.textContent = 'Mot de passe modifié avec succès !';
        msg.className = 'text-vert-jobleaf font-semibold';
        formMdp.reset();
    } catch (err) {
        msg.textContent = err.message;
        msg.className = 'text-red-600 font-semibold';
    }
});


const formSupp = document.getElementById('form-suppression');
formSupp.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!confirm('Êtes-vous absolument sûr de vouloir supprimer définitivement votre compte ?')) return;

    const msg = document.getElementById('msg-suppression');
    msg.textContent = '';
    const f = new FormData(formSupp);

    try {
        await appelApi('/me', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ password: f.get('password') }),
        });
        window.location.href = 'login_page.html';
    } catch (err) {
        msg.textContent = err.message;
    }
});

init();