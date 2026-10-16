// Lien API //

const API = '/api';

async function appelApi(chemin, options = {}) {
    const reponse = await fetch(API + chemin, {
        credentials: 'same-origin',   // envoie le cookie de session
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

function redirigerSelonRole(role) {
    window.location.href = role === 'admin' ? 'admin.html' : 'annonces.html';
}

// Vérification autorisation //

let moi = null;

async function chargerProfil() {
    try {
        moi = await appelApi('/me');
    } catch (erreur) {
        if (erreur.status === 401) {
            window.location.href = 'login_page.html';   // pas connecté //
            return;
        }
        throw erreur;
    }

    // Le back renvoie user_id, role, username, email, nom, prenom, telephone //
    const photo = document.getElementById('photo-colonne');
    photo.alt = `Profil de ${moi.prenom} ${moi.nom}`;
}

// Bulle chat & cadre chat //

const bulle = document.getElementById("bulle-messages");
const boutonOuvrir = document.getElementById("ouvrir-messages");
const boutonFermer = document.getElementById("fermer-messages");
const panneau = document.getElementById("panneau-messages");

const classesRepliee = ["left-4", "bottom-4", "w-20", "h-20", "rounded-full"];
const classesDepliee = ["left-0", "bottom-0", "w-full", "h-full", "rounded-none"];

function ouvrirMessages() {
    bulle.classList.remove(...classesRepliee);
    bulle.classList.add(...classesDepliee);
    boutonOuvrir.classList.add("hidden");
    boutonOuvrir.setAttribute("aria-expanded", "true");
    panneau.classList.remove("opacity-0", "invisible");
    boutonFermer.focus();

    chargeConversations();
}

function fermerMessages() {
    bulle.classList.remove(...classesDepliee);
    bulle.classList.add(...classesRepliee);
    panneau.classList.add("opacity-0", "invisible");
    boutonOuvrir.classList.remove("hidden");
    boutonOuvrir.setAttribute("aria-expanded", "false");
    boutonOuvrir.focus();
}

boutonOuvrir.addEventListener("click", ouvrirMessages);
boutonFermer.addEventListener("click", fermerMessages);

document.addEventListener("keydown", (evenement) => {
    if (evenement.key === "Escape" && boutonOuvrir.getAttribute("aria-expanded") === "true") {
        fermerMessages();
    }
});

async function chargeConversations() {
    const reponse = await fetch ("/api/conversations");
    const conversations = await reponse.json();

    const liste = document.getElementById("liste-conversations");
    const modele = document.getElementById("modele-conversation");
    liste.innerHTML = "";

    for (const conv of conversations) {
        const ligne = modele.content.cloneNode(true);

        ligne.querySelector('[data-champ="entreprise"]').textContent = conv.entreprise;
        ligne.querySelector('[data-champ="apercu"]').textContent = conv.dernier_message;
        ligne.querySelector('[data-champ="date"]').textContent =
            new Intl.DateTimeFormat("fr-FR", { day: "2-digit", month: "2-digit"})
                .format(new Date(conv.date));

        liste.append(ligne);
    }
}

// Barre de recherche //

const formRecherche = document.getElementById('form-search');

formRecherche.addEventListener('submit', (evenement) => {
    evenement.preventDefault();
    chargerAnnonces(new FormData(formRecherche).get('q'));
});

// Chargement de la page
chargerProfil().catch((erreur) => {
    console.error(erreur);
});
chargerAnnonces();

// Affichage des annonces 

const dateCourte = new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });

async function chargerAnnonces(recherche = '') {
    const liste = document.getElementById('liste-annonces');
    const message = document.getElementById('message-annonces');
    const modele = document.getElementById('modele-annonce');

    message.textContent = 'Chargement...';

    const parametres = new URLSearchParams({ page: '1', per_page: '5' });      // URLSearchParams encode les accents et les espaces
    if (recherche.trim() !== '') {
        parametres.set('q', recherche.trim());
    }

    try {
        const resultat = await appelApi('/annonces?' + parametres);

        liste.innerHTML = '';

        if (resultat.items.length === 0) {
            message.textContent = 'Aucune annonce ne correspond à ta recherche.';
            return;
        }
        message.textContent = '';

        for (const annonce of resultat.items) {
            const carte = modele.content.cloneNode(true);

            carte.querySelector('[data-champ="titre"]').textContent = annonce.titre;
            carte.querySelector('[data-champ="entreprise"]').textContent = annonce.entreprise.nom_entreprise;
            carte.querySelector('[data-champ="ville"]').textContent = annonce.ville ?? '';
            carte.querySelector('[data-champ="type_contrat"]').textContent = annonce.type_contrat;
            carte.querySelector('[data-champ="date"]').textContent = dateCourte.format(new Date(annonce.date_publication));
            carte.querySelector('[data-champ="lien"]').href = `annonce.html?id=${annonce.annonce_id}`;

            liste.append(carte);
        }
    } catch (erreur) {
        liste.innerHTML = '';
        message.textContent = erreur.message;
    }
}