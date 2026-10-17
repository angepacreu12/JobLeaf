const dateCourte = new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });

const STYLE_STATUT = {
    envoyee:   { texte: 'Envoyée',   classes: 'bg-beige-coton text-vert-fonce' },
    vue:       { texte: 'Vue',       classes: 'bg-blue-100 text-blue-800' },
    entretien: { texte: 'Entretien', classes: 'bg-amber-100 text-amber-800' },
    acceptee:  { texte: 'Acceptée',  classes: 'bg-vert-clair text-vert-fonce' },
    refusee:   { texte: 'Refusée',   classes: 'bg-red-100 text-red-800' },
};

async function init() {
    const moi = await chargerMoi();
    if (moi.role !== 'candidat') {
        redirigerSelonRole(moi.role);
        return;
    }
    await chargerCandidatures();
}

async function chargerCandidatures() {
    const liste = document.getElementById('liste-candidatures');
    const message = document.getElementById('message-candidatures');
    const modele = document.getElementById('modele-candidature');

    message.textContent = 'Chargement...';

    try {
        const resultat = await appelApi('/me/candidatures?page=1&per_page=50');
        liste.innerHTML = '';

        if (resultat.items.length === 0) {
            message.textContent = "Vous n'avez encore postulé à aucune offre.";
            return;
        }
        message.textContent = '';

        for (const cand of resultat.items) {
            const carte = modele.content.cloneNode(true);

            const btnDetail = carte.querySelector('[data-champ="btn-detail"]');
            btnDetail.textContent = cand.annonce.titre;

            carte.querySelector('[data-champ="entreprise"]').textContent = cand.annonce.entreprise;
            carte.querySelector('[data-champ="date"]').textContent =
                `Postulé le ${dateCourte.format(new Date(cand.date_candidature))}`;

            const badge = carte.querySelector('[data-champ="statut"]');
            const infoStatut = STYLE_STATUT[cand.statut] ?? { texte: cand.statut, classes: 'bg-gray-200' };
            badge.textContent = infoStatut.texte;
            badge.className = `px-3 py-1 rounded-full text-xs font-bold ${infoStatut.classes}`;

            if (cand.has_cv) {
                const lienCv = carte.querySelector('[data-champ="lien-cv"]');
                lienCv.href = `/api/candidatures/${encodeURIComponent(cand.candidature_id)}/cv`;
                lienCv.classList.remove('hidden');
            }

            
            carte.querySelector('[data-champ="btn-messages"]').addEventListener('click', () => {
                window.ouvrirMessages(cand.candidature_id, `${cand.annonce.entreprise} — ${cand.annonce.titre}`);
            });

            
            const blocDetail = carte.querySelector('[data-champ="bloc-detail-annonce"]');
            btnDetail.addEventListener('click', async () => {
                const ouvert = !blocDetail.classList.toggle('hidden');
                if (!ouvert || blocDetail.dataset.charge === '1') return;

                blocDetail.textContent = 'Chargement...';
                try {
                    const a = await appelApi(`/annonces/${encodeURIComponent(cand.annonce.annonce_id)}`);
                    blocDetail.textContent = '';
                    const pInfos = document.createElement('p');
                    pInfos.className = 'text-xs font-semibold mb-2 text-gray-600';
                    pInfos.textContent = [a.reference, a.type_contrat, a.ville, a.teletravail].filter(Boolean).join(' • ');

                    const pDesc = document.createElement('p');
                    pDesc.className = 'whitespace-pre-line';
                    pDesc.textContent = a.description;

                    blocDetail.append(pInfos, pDesc);
                    blocDetail.dataset.charge = '1';
                } catch (err) {
                    blocDetail.textContent = err.message;
                }
            });

            
            const blocConfirmer = carte.querySelector('[data-champ="bloc-confirmer-retrait"]');
            carte.querySelector('[data-champ="btn-demander-retrait"]').addEventListener('click', () => {
                blocConfirmer.classList.toggle('hidden');
            });
            carte.querySelector('[data-champ="btn-confirmer-non"]').addEventListener('click', () => {
                blocConfirmer.classList.add('hidden');
            });
            carte.querySelector('[data-champ="btn-confirmer-oui"]').addEventListener('click', async (e) => {
                e.target.disabled = true;
                try {
                    await appelApi(`/candidatures/${encodeURIComponent(cand.candidature_id)}`, {
                        method: 'DELETE',
                    });
                    await chargerCandidatures();
                } catch (err) {
                    alert(err.message);
                    e.target.disabled = false;
                }
            });

            liste.append(carte);
        }
    } catch (erreur) {
        liste.innerHTML = '';
        message.textContent = erreur.message;
    }
}

init();