const API = '/api';

async function appelApi(chemin, options = {}) {
    const reponse = await fetch(API + chemin, {
        credentials: 'same-origin',
        ...options,
    });
    let donnees = null;
    try {
        donnees = await reponse.json();
    } catch {
    }
    if (!reponse.ok) {
        const erreur = new Error(donnees?.error ?? `Erreur ${reponse.status}`);
        erreur.status = reponse.status;
        throw erreur;
    }
    return donnees;
}

const params = new URLSearchParams(window.location.search);
const annonceId = params.get('id');

const etatPage = document.getElementById('etat-page');
const blocAnnonce = document.getElementById('bloc-annonce');
const formCandidature = document.getElementById('form-candidature');
const retourCandidature = document.getElementById('retour-candidature');

function afficherBadge(id, texte) {
    const el = document.getElementById(id);
    if (texte) {
        el.textContent = texte;
    } else {
        el.classList.add('hidden');
    }
}

async function init() {
    if (!annonceId) {
        etatPage.textContent = "Annonce introuvable (identifiant manquant).";
        return;
    }

    try {
        const moi = await appelApi('/me');
        document.getElementById('photo-colonne').alt = `Profil de ${moi.prenom} ${moi.nom}`;

        // Seuls les candidats peuvent postuler
        if (moi.role !== 'candidat') {
            formCandidature.classList.add('hidden');
        }

        const a = await appelApi(`/annonces/${encodeURIComponent(annonceId)}`);

        document.getElementById('ann-titre').textContent = a.titre;
        document.getElementById('ann-entreprise').textContent = a.entreprise.nom_entreprise;
        document.getElementById('ann-ref').textContent = `Réf : ${a.reference}`;
        document.getElementById('ann-description').textContent = a.description;

        if (a.entreprise.site_web) {
            const lienSite = document.getElementById('ann-site');
            lienSite.href = a.entreprise.site_web;
            lienSite.classList.remove('hidden');
        }

        afficherBadge('ann-contrat', a.type_contrat);
        afficherBadge('ann-ville', a.ville);
        afficherBadge('ann-teletravail', a.teletravail);
        afficherBadge('ann-categorie', a.category);
        afficherBadge('ann-experience', a.niveau_experience);

        let salaire = null;
        if (a.salaire_min !== null && a.salaire_max !== null) {
            salaire = `${a.salaire_min} € - ${a.salaire_max} €`;
        } else if (a.salaire_min !== null) {
            salaire = `À partir de ${a.salaire_min} €`;
        } else if (a.salaire_max !== null) {
            salaire = `Jusqu'à ${a.salaire_max} €`;
        }
        afficherBadge('ann-salaire', salaire);

        etatPage.classList.add('hidden');
        blocAnnonce.classList.remove('hidden');
    } catch (erreur) {
        if (erreur.status === 401) {
            window.location.href = 'login_page.html';
            return;
        }
        etatPage.textContent = erreur.message;
    }
}

formCandidature.addEventListener('submit', async (evenement) => {
    evenement.preventDefault();
    retourCandidature.textContent = '';

    const bouton = formCandidature.querySelector('button[type="submit"]');
    bouton.disabled = true;

    try {
        await appelApi(`/annonces/${encodeURIComponent(annonceId)}/candidatures`, {
            method: 'POST',
            body: new FormData(formCandidature), // Envoie le fichier PDF + le message
        });
        retourCandidature.className = 'text-sm font-semibold text-vert-jobleaf';
        retourCandidature.textContent = 'Candidature envoyée avec succès !';
        formCandidature.reset();
    } catch (erreur) {
        retourCandidature.className = 'text-sm font-semibold text-red-600';
        retourCandidature.textContent = erreur.message;
    } finally {
        bouton.disabled = false;
    }
});

init();