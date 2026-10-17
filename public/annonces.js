const dateCourte = new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
const cacheDetails = new Map(); 

let pageCourante = 1;
const parPage = 20;
let moi = null;

async function init() {
    moi = await chargerMoi();
    await chargerAnnonces();
}

async function chargerAnnonces() {
    const liste = document.getElementById('liste-annonces');
    const message = document.getElementById('message-annonces');
    const modele = document.getElementById('modele-annonce');
    const blocPagination = document.getElementById('pagination-annonces');

    message.textContent = 'Chargement...';

    const form = document.getElementById('form-search');
    const donneesForm = new FormData(form);
    const params = new URLSearchParams({
        page: String(pageCourante),
        per_page: String(parPage),
    });

    for (const [cle, val] of donneesForm.entries()) {
        if (val.trim() !== '') {
            params.set(cle, val.trim());
        }
    }

    try {
        const resultat = await appelApi('/annonces?' + params);
        liste.innerHTML = '';

        if (resultat.items.length === 0) {
            message.textContent = 'Aucune annonce ne correspond à ta recherche.';
            blocPagination.classList.add('hidden');
            return;
        }
        message.textContent = '';

        for (const annonce of resultat.items) {
            const carte = modele.content.cloneNode(true);

            carte.querySelector('[data-champ="titre"]').textContent = annonce.titre;
            const lienEntr = carte.querySelector('[data-champ="entreprise"]');
            lienEntr.textContent = annonce.entreprise.nom_entreprise;
            lienEntr.href = `entreprise.html?id=${encodeURIComponent(annonce.entreprise.user_id)}`;

            carte.querySelector('[data-champ="reference"]').textContent = annonce.reference;
            carte.querySelector('[data-champ="type_contrat"]').textContent = annonce.type_contrat;
            carte.querySelector('[data-champ="category"]').textContent = annonce.category ?? '';
            carte.querySelector('[data-champ="ville"]').textContent = annonce.ville ?? '';
            carte.querySelector('[data-champ="date"]').textContent = dateCourte.format(new Date(annonce.date_publication));
            carte.querySelector('[data-champ="extrait"]').textContent = annonce.extrait + '…';

            const btnPlus = carte.querySelector('[data-champ="btn-savoir-plus"]');
            const blocDetail = carte.querySelector('[data-champ="bloc-detail"]');
            const badgesDetail = carte.querySelector('[data-champ="badges-detail"]');
            const descComplete = carte.querySelector('[data-champ="description-complete"]');
            const infosEntr = carte.querySelector('[data-champ="infos-entreprise"]');
            const blocDejaPostule = carte.querySelector('[data-champ="deja-postule"]');
            const btnOuvrirPostuler = carte.querySelector('[data-champ="btn-ouvrir-postuler"]');
            const formPostuler = carte.querySelector('[data-champ="form-postuler"]');
            const identiteCandidat = carte.querySelector('[data-champ="identite-candidat"]');
            const erreurPostuler = carte.querySelector('[data-champ="erreur-postuler"]');

            btnPlus.addEventListener('click', async () => {
                const ouvert = !blocDetail.classList.toggle('hidden');
                btnPlus.textContent = ouvert ? 'Réduire ↑' : 'En savoir plus ↓';
                if (!ouvert) return;

                try {
                    if (!cacheDetails.has(annonce.annonce_id)) {
                        const detail = await appelApi(`/annonces/${encodeURIComponent(annonce.annonce_id)}`);
                        cacheDetails.set(annonce.annonce_id, detail);
                    }
                    const d = cacheDetails.get(annonce.annonce_id);

                    descComplete.textContent = d.description;
                    badgesDetail.innerHTML = '';

                    const puces = [
                        d.teletravail,
                        d.niveau_experience ? `Exp : ${d.niveau_experience}` : null,
                        d.salaire_min !== null || d.salaire_max !== null
                            ? `Salaire : ${d.salaire_min ?? '?'} € - ${d.salaire_max ?? '?'} €`
                            : null,
                        d.date_expiration ? `Expire le ${dateCourte.format(new Date(d.date_expiration))}` : null,
                    ].filter(Boolean);

                    for (const p of puces) {
                        const span = document.createElement('span');
                        span.className = 'bg-beige-coton px-2.5 py-0.5 rounded-full font-semibold';
                        span.textContent = p;
                        badgesDetail.append(span);
                    }

                    const infos = [
                        d.entreprise.ville ? `Siège : ${d.entreprise.ville}` : null,
                        d.entreprise.site_web ? `Site : ${d.entreprise.site_web}` : null,
                    ].filter(Boolean);
                    infosEntr.textContent = infos.join(' • ');

                    if (d.deja_postule) {
                        blocDejaPostule.classList.remove('hidden');
                        btnOuvrirPostuler.classList.add('hidden');
                    } else if (moi.role === 'candidat') {
                        btnOuvrirPostuler.classList.remove('hidden');
                    }
                } catch (err) {
                    descComplete.textContent = err.message;
                }
            });

            btnOuvrirPostuler.addEventListener('click', () => {
                formPostuler.classList.toggle('hidden');
                identiteCandidat.textContent = `Candidature au nom de : ${moi.prenom} ${moi.nom} (${moi.email})`;
            });

            formPostuler.addEventListener('submit', async (e) => {
                e.preventDefault();
                erreurPostuler.textContent = '';

                const fichier = formPostuler.elements.cv.files[0];
                if (!fichier || fichier.type !== 'application/pdf') {
                    erreurPostuler.textContent = 'Le CV doit être un fichier PDF.';
                    return;
                }
                if (fichier.size > 2 * 1024 * 1024) {
                    erreurPostuler.textContent = 'Le fichier dépasse la taille maximale de 2 Mo.';
                    return;
                }

                try {
                    await appelApi(`/annonces/${encodeURIComponent(annonce.annonce_id)}/candidatures`, {
                        method: 'POST',
                        body: new FormData(formPostuler),
                    });
                    formPostuler.classList.add('hidden');
                    btnOuvrirPostuler.classList.add('hidden');
                    blocDejaPostule.textContent = 'Candidature envoyée avec succès ! Retrouvez votre message dans vos conversations.';
                    blocDejaPostule.classList.remove('hidden');
                    if (cacheDetails.has(annonce.annonce_id)) {
                        cacheDetails.get(annonce.annonce_id).deja_postule = true;
                    }
                } catch (err) {
                    if (err.status === 409) {
                        erreurPostuler.textContent = 'Vous avez déjà postulé à cette annonce.';
                    } else {
                        erreurPostuler.textContent = err.message;
                    }
                }
            });

            liste.append(carte);
        }

        
        const totalPages = Math.max(1, Math.ceil(resultat.total / parPage));
        blocPagination.classList.remove('hidden');
        document.getElementById('page-info').textContent = `Page ${pageCourante} / ${totalPages} (${resultat.total} annonces)`;
        document.getElementById('page-prec').disabled = pageCourante <= 1;
        document.getElementById('page-suiv').disabled = pageCourante >= totalPages;
    } catch (erreur) {
        liste.innerHTML = '';
        message.textContent = erreur.message;
    }
}

document.getElementById('form-search').addEventListener('submit', (e) => {
    e.preventDefault();
    pageCourante = 1;
    chargerAnnonces();
});

document.getElementById('page-prec').addEventListener('click', () => {
    if (pageCourante > 1) {
        pageCourante--;
        chargerAnnonces();
    }
});

document.getElementById('page-suiv').addEventListener('click', () => {
    pageCourante++;
    chargerAnnonces();
});

init();