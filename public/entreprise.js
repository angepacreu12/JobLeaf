const params = new URLSearchParams(window.location.search);
const entrepriseId = params.get('id');

async function init() {
    await chargerMoi();

    const etat = document.getElementById('etat-entreprise');
    if (!entrepriseId) {
        etat.textContent = 'Identifiant entreprise manquant.';
        return;
    }

    try {
        const e = await appelApi(`/entreprises/${encodeURIComponent(entrepriseId)}`);
        document.getElementById('entr-nom').textContent = e.nom_entreprise;
        document.getElementById('entr-meta').textContent =
            [e.secteur, e.ville, e.siret ? `SIRET : ${e.siret}` : null].filter(Boolean).join(' • ');
        document.getElementById('entr-desc').textContent = e.description ?? 'Aucune description renseignée.';

        if (e.site_web) {
            const lien = document.getElementById('entr-site');
            lien.href = e.site_web;
            lien.classList.remove('hidden');
        }

        etat.classList.add('hidden');
        document.getElementById('fiche-entreprise').classList.remove('hidden');

        const annonces = await appelApi(`/entreprises/${encodeURIComponent(entrepriseId)}/annonces?page=1&per_page=50`);
        const liste = document.getElementById('liste-annonces-entreprise');
        const modele = document.getElementById('modele-annonce-entr');

        if (annonces.items.length === 0) {
            liste.textContent = 'Aucune offre publiée actuellement.';
            return;
        }

        for (const a of annonces.items) {
            const carte = modele.content.cloneNode(true);
            carte.querySelector('[data-champ="titre"]').textContent = a.titre;
            carte.querySelector('[data-champ="reference"]').textContent = a.reference;
            carte.querySelector('[data-champ="type_contrat"]').textContent = a.type_contrat;
            carte.querySelector('[data-champ="ville"]').textContent = a.ville ?? '';
            carte.querySelector('[data-champ="extrait"]').textContent = a.extrait + '…';
            liste.append(carte);
        }
    } catch (err) {
        etat.textContent = err.message;
    }
}

init();