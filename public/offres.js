const dateCourte = new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });

const formAnnonce = document.getElementById('form-annonce');
const msgForm = document.getElementById('msg-form-annonce');
const titreForm = document.getElementById('titre-form-annonce');

let annonceEnEdition = null; 

async function init() {
    const moi = await chargerMoi();
    if (moi.role !== 'entreprise') {
        redirigerSelonRole(moi.role);
        return;
    }
    await chargerMesAnnonces();
}

document.getElementById('btn-nouvelle-annonce').addEventListener('click', () => {
    annonceEnEdition = null;
    formAnnonce.reset();
    titreForm.textContent = 'Nouvelle offre';
    msgForm.textContent = '';
    formAnnonce.classList.remove('hidden');
});

document.getElementById('btn-annuler-form').addEventListener('click', () => {
    formAnnonce.classList.add('hidden');
    formAnnonce.reset();
    annonceEnEdition = null;
});


formAnnonce.addEventListener('submit', async (e) => {
    e.preventDefault();
    msgForm.textContent = '';

    const statutChoisi = e.submitter?.dataset.statut ?? 'publiee';
    const f = new FormData(formAnnonce);

    const strOuNull = (k) => {
        const v = (f.get(k) ?? '').trim();
        return v === '' ? null : v;
    };
    const intOuNull = (k) => {
        const v = (f.get(k) ?? '').trim();
        return v === '' ? null : Number(v);
    };

    const donnees = {
        titre: f.get('titre').trim(),
        description: f.get('description').trim(),
        type_contrat: f.get('type_contrat'),
        category: strOuNull('category'),
        teletravail: strOuNull('teletravail'),
        ville: strOuNull('ville'),
        salaire_min: intOuNull('salaire_min'),
        salaire_max: intOuNull('salaire_max'),
        niveau_experience: strOuNull('niveau_experience'),
        date_expiration: strOuNull('date_expiration'),
        statut: statutChoisi,
    };

    try {
        if (!annonceEnEdition) {
            await appelApi('/annonces', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(donnees),
            });
        } else {
            
            const modifs = {};
            for (const [cle, val] of Object.entries(donnees)) {
                if (cle === 'date_expiration') {
                    const ancienneDate = annonceEnEdition.date_expiration
                        ? annonceEnEdition.date_expiration.slice(0, 10)
                        : null;
                    if (val !== ancienneDate) modifs[cle] = val;
                } else if (val !== (annonceEnEdition[cle] ?? null)) {
                    modifs[cle] = val;
                }
            }
            if (Object.keys(modifs).length > 0) {
                await appelApi(`/annonces/${encodeURIComponent(annonceEnEdition.annonce_id)}`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(modifs),
                });
            }
        }
        formAnnonce.classList.add('hidden');
        formAnnonce.reset();
        annonceEnEdition = null;
        await chargerMesAnnonces();
    } catch (err) {
        msgForm.textContent = err.message;
    }
});

async function ouvrirEdition(annonceId) {
    try {
        const detail = await appelApi(`/annonces/${encodeURIComponent(annonceId)}`);
        annonceEnEdition = detail;
        titreForm.textContent = `Modifier l'offre ${detail.reference}`;
        msgForm.textContent = '';

        formAnnonce.elements.titre.value = detail.titre ?? '';
        formAnnonce.elements.type_contrat.value = detail.type_contrat ?? 'CDI';
        formAnnonce.elements.category.value = detail.category ?? '';
        formAnnonce.elements.teletravail.value = detail.teletravail ?? '';
        formAnnonce.elements.ville.value = detail.ville ?? '';
        formAnnonce.elements.salaire_min.value = detail.salaire_min ?? '';
        formAnnonce.elements.salaire_max.value = detail.salaire_max ?? '';
        formAnnonce.elements.niveau_experience.value = detail.niveau_experience ?? '';
        formAnnonce.elements.date_expiration.value = detail.date_expiration ? detail.date_expiration.slice(0, 10) : '';
        formAnnonce.elements.description.value = detail.description ?? '';

        formAnnonce.classList.remove('hidden');
        formAnnonce.scrollIntoView({ behavior: 'smooth' });
    } catch (err) {
        alert(err.message);
    }
}

async function chargerMesAnnonces() {
    const liste = document.getElementById('liste-mes-annonces');
    const message = document.getElementById('message-annonces');
    const modele = document.getElementById('modele-mon-annonce');

    message.textContent = 'Chargement...';

    try {
        const resultat = await appelApi('/me/annonces?page=1&per_page=50');
        liste.innerHTML = '';

        if (resultat.items.length === 0) {
            message.textContent = "Vous n'avez créé aucune offre pour le moment.";
            return;
        }
        message.textContent = '';

        for (const a of resultat.items) {
            const carte = modele.content.cloneNode(true);

            carte.querySelector('[data-champ="reference"]').textContent = a.reference;
            carte.querySelector('[data-champ="titre"]').textContent = a.titre;
            carte.querySelector('[data-champ="infos"]').textContent =
                `${a.type_contrat} • ${a.ville ?? 'Lieu non précisé'}`;

            const chip = carte.querySelector('[data-champ="statut-chip"]');
            chip.textContent = a.statut;
            chip.className = `text-xs px-2.5 py-0.5 rounded-full font-bold ${
                a.statut === 'publiee' ? 'bg-vert-clair text-vert-fonce' : 'bg-gray-200 text-gray-700'
            }`;

            
            const btnPublier = carte.querySelector('[data-champ="btn-publier"]');
            if (a.statut !== 'publiee') {
                btnPublier.classList.remove('hidden');
                btnPublier.addEventListener('click', async () => {
                    await changerStatutAnnonce(a.annonce_id, 'publiee');
                });
            }

            const btnPourvue = carte.querySelector('[data-champ="btn-pourvue"]');
            if (a.statut === 'publiee') {
                btnPourvue.classList.remove('hidden');
                btnPourvue.addEventListener('click', async () => {
                    await changerStatutAnnonce(a.annonce_id, 'pourvue');
                });
            }

            carte.querySelector('[data-champ="btn-editer"]').addEventListener('click', () => {
                ouvrirEdition(a.annonce_id);
            });

            
            const btnCand = carte.querySelector('[data-champ="btn-candidatures"]');
            const blocCand = carte.querySelector('[data-champ="bloc-candidatures"]');
            const msgCand = carte.querySelector('[data-champ="msg-candidatures"]');
            const listeCand = carte.querySelector('[data-champ="liste-candidats"]');

            btnCand.textContent = `Candidatures (${a.nb_candidatures})`;
            btnCand.addEventListener('click', async () => {
                const ouvert = !blocCand.classList.toggle('hidden');
                if (ouvert) {
                    await chargerCandidats(a.annonce_id, a.titre, msgCand, listeCand);
                }
            });

            
            const blocSuppr = carte.querySelector('[data-champ="bloc-confirmer-suppr"]');
            carte.querySelector('[data-champ="texte-confirmer-suppr"]').textContent =
                `Supprimer cette offre effacera également ses ${a.nb_candidatures} candidature(s) et leurs messages. Confirmer ?`;

            carte.querySelector('[data-champ="btn-demander-suppr"]').addEventListener('click', () => {
                blocSuppr.classList.toggle('hidden');
            });
            carte.querySelector('[data-champ="btn-suppr-non"]').addEventListener('click', () => {
                blocSuppr.classList.add('hidden');
            });
            carte.querySelector('[data-champ="btn-suppr-oui"]').addEventListener('click', async () => {
                await appelApi(`/annonces/${encodeURIComponent(a.annonce_id)}`, { method: 'DELETE' });
                await chargerMesAnnonces();
            });

            liste.append(carte);
        }
    } catch (err) {
        liste.innerHTML = '';
        message.textContent = err.message;
    }
}

async function changerStatutAnnonce(annonceId, statut) {
    try {
        await appelApi(`/annonces/${encodeURIComponent(annonceId)}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ statut }),
        });
        await chargerMesAnnonces();
    } catch (err) {
        alert(err.message);
    }
}

async function chargerCandidats(annonceId, titreAnnonce, msgCand, listeCand) {
    const modele = document.getElementById('modele-candidat-recu');
    msgCand.textContent = 'Chargement...';
    listeCand.innerHTML = '';

    try {
        const res = await appelApi(`/annonces/${encodeURIComponent(annonceId)}/candidatures?page=1&per_page=50`);
        if (res.items.length === 0) {
            msgCand.textContent = 'Aucune candidature reçue.';
            return;
        }
        msgCand.textContent = '';

        for (const cand of res.items) {
            const ligne = modele.content.cloneNode(true);
            const c = cand.candidat;
            const nomComplet = `${c.prenom} ${c.nom}`;

            ligne.querySelector('[data-champ="nom-complet"]').textContent = nomComplet;
            ligne.querySelector('[data-champ="date"]').textContent =
                dateCourte.format(new Date(cand.date_candidature));
            ligne.querySelector('[data-champ="contact"]').textContent =
                `${c.email}${c.telephone ? ' • ' + c.telephone : ''}`;
            ligne.querySelector('[data-champ="message"]').textContent = cand.message ?? '';

            const chipStatut = ligne.querySelector('[data-champ="chip-statut"]');
            chipStatut.textContent = cand.statut;

            if (cand.has_cv) {
                const lienCv = ligne.querySelector('[data-champ="lien-cv"]');
                lienCv.href = `/api/candidatures/${encodeURIComponent(cand.candidature_id)}/cv`;
                lienCv.classList.remove('hidden');
            }

            const select = ligne.querySelector('[data-champ="statut-candidature"]');
            select.value = cand.statut;
            select.addEventListener('change', async () => {
                try {
                    const maj = await appelApi(`/candidatures/${encodeURIComponent(cand.candidature_id)}`, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ statut: select.value }),
                    });
                    chipStatut.textContent = maj.statut;
                } catch (err) {
                    alert(err.message);
                }
            });

            
            ligne.querySelector('[data-champ="btn-repondre"]').addEventListener('click', () => {
                window.ouvrirMessages(cand.candidature_id, `${nomComplet} — ${titreAnnonce}`);
            });

            listeCand.append(ligne);
        }
    } catch (err) {
        msgCand.textContent = err.message;
    }
}

init();