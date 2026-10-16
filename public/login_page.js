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
        // le serveur n'a pas répondu en JSON
    }

    if (!reponse.ok) {
        throw new Error(donnees?.error ?? `Erreur ${reponse.status}`);
    }

    return donnees;
}

function redirigerSelonRole(role) {
    window.location.href = role === 'admin' ? 'admin.html' : 'annonces.html';
}

// Bouton s'inscrire - se connecter //

const boutons = document.querySelectorAll('[data-cible]');

boutons.forEach(bouton => {
    bouton.addEventListener('click', () => {
        const form = document.getElementById(bouton.dataset.cible);
        const ouvert = form.classList.toggle('ouvert');
        bouton.setAttribute('aria-expanded', ouvert);

        // Referme l'autre formulaire
        boutons.forEach(autre => {
            if (autre !== bouton) {
                document.getElementById(autre.dataset.cible).classList.remove('ouvert');
                autre.setAttribute('aria-expanded', false);
            }
        });
    });
});

// MDP conditions //

// --- Règles --- //
const regles = [
  { test: /.{12,}/,                   message: "12 caractères minimum" },
  { test: /[a-z]/,                    message: "au moins une minuscule" },
  { test: /[A-Z]/,                    message: "au moins une majuscule" },
  { test: /[0-9]/,                    message: "au moins un chiffre" },
  { test: /[^a-zA-Z0-9]/,             message: "au moins un caractère spécial" },
  { test: /^[\x21-\x7E]+$/,           message: "pas d'espace ni d'accent" },
];

// --- Fonction validation --- //
function validerMDP(motDePasse) {
  const erreurs = regles
    .filter((regle) => !regle.test.test(motDePasse))
    .map((regle) => regle.message);

  return {
    valide: erreurs.length === 0,
    erreurs: erreurs,
  };
}

// Inscription //

const formulaire = document.querySelector("#form-inscription");
const champMdp = document.querySelector("#insc-mdp");
const zoneErreurs = document.querySelector("#erreurs-mdp");

formulaire.addEventListener("submit", async (evenement) => {
    evenement.preventDefault();

    const resultat = validerMDP(champMdp.value);
    if (!resultat.valide) {
        zoneErreurs.textContent = "Il manque : " + resultat.erreurs.join(", ");
        return;
    }
    zoneErreurs.textContent = "";

    const bouton = formulaire.querySelector('button[type="submit"]');
    bouton.disabled = true;

    const champs = new FormData(formulaire);
    const telephone = champs.get("telephone").trim();

    const corps = {
        email: champs.get("email").trim(),
        password: champs.get("password"),
        role: champs.get("role"),
        username: champs.get("username").trim(),
        nom: champs.get("nom").trim(),
        prenom: champs.get("prenom").trim(),
        telephone: telephone === "" ? null : telephone,
    };

    if (corps.role === "entreprise") {
        corps.nom_entreprise = champs.get("nom_entreprise").trim();
        corps.siret = champs.get("siret");
        corps.ville = champs.get("siege");
        corps.site_web = champs.get("site")
        corps.description = champs.get("description");
        // TODO : siret / secteur / ville / site_web / description
        // ne sont pas acceptés par POST /api/users (voir PATCH /api/entreprises/{id}, encore en 🚧)
    }

    try {
        await appelApi("/users", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(corps),
        });

        // L'inscription ne connecte pas : on enchaîne avec le login
        const session = await appelApi("/login", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ email: corps.email, password: corps.password }),
        });

        redirigerSelonRole(session.role);
    } catch (erreur) {
        zoneErreurs.textContent = erreur.message;
    } finally {
        bouton.disabled = false;
    }
});

// Connexion //

const formConnexion = document.querySelector("#form-connexion");
const zoneErreursConnexion = document.querySelector("#erreurs-connexion");

formConnexion.addEventListener("submit", async (evenement) => {
    evenement.preventDefault();
    zoneErreursConnexion.textContent = "";

    const bouton = formConnexion.querySelector('button[type="submit"]');
    bouton.disabled = true;

    const champs = new FormData(formConnexion);

    try {
        const session = await appelApi("/login", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                email: champs.get("email").trim(),
                password: champs.get("password"),
            }),
        });

        redirigerSelonRole(session.role);
    } catch (erreur) {
        zoneErreursConnexion.textContent = erreur.message;
    } finally {
        bouton.disabled = false;
    }
});

// Numéro SIRET & téléphone conditions //

document.querySelectorAll('.chiffres-seulement').forEach(champ => {
    champ.addEventListener('input', () => {
        champ.value = champ.value.replace(/[^0-9]/g, '');
    });
});

// Affichage du bloc employeur si case cochée //

const profil = document.getElementById('insc-profil');
const blocEmployeur = document.querySelector('.entreprise');
const champsObligatoires = blocEmployeur.querySelectorAll('[required]');

function majBlocEmployeur() {
    const estEmployeur = profil.value === 'entreprise';
    blocEmployeur.classList.toggle('visible', estEmployeur);

    champsObligatoires.forEach(champ => {
        champ.required = estEmployeur;
    });
}

profil.addEventListener('change', majBlocEmployeur);
majBlocEmployeur();