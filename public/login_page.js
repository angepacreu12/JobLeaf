

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
        throw new Error(donnees?.error ?? `Erreur ${reponse.status}`);
    }

    return donnees;
}

function redirigerSelonRole(role) {
    if (role === 'admin') {
        window.location.href = 'admin.html';
    } else if (role === 'entreprise') {
        window.location.href = 'offres.html';
    } else {
        window.location.href = 'annonces.html';
    }
}


appelApi('/me')
    .then((moi) => redirigerSelonRole(moi.role))
    .catch(() => {});



const boutons = document.querySelectorAll('[data-cible]');

boutons.forEach(bouton => {
    bouton.addEventListener('click', () => {
        const form = document.getElementById(bouton.dataset.cible);
        const ouvert = form.classList.toggle('ouvert');
        bouton.setAttribute('aria-expanded', ouvert);

        
        boutons.forEach(autre => {
            if (autre !== bouton) {
                document.getElementById(autre.dataset.cible).classList.remove('ouvert');
                autre.setAttribute('aria-expanded', false);
            }
        });
    });
});




const regles = [
  { test: /.{12,}/,                   message: "12 caractères minimum" },
  { test: /[a-z]/,                    message: "au moins une minuscule" },
  { test: /[A-Z]/,                    message: "au moins une majuscule" },
  { test: /[0-9]/,                    message: "au moins un chiffre" },
  { test: /[^a-zA-Z0-9]/,             message: "au moins un caractère spécial" },
  { test: /^[\x21-\x7E]+$/,           message: "pas d'espace ni d'accent" },
];


function validerMDP(motDePasse) {
  const erreurs = regles
    .filter((regle) => !regle.test.test(motDePasse))
    .map((regle) => regle.message);

  return {
    valide: erreurs.length === 0,
    erreurs: erreurs,
  };
}



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
        const ouNull = (cle) => {
            const v = (champs.get(cle) ?? "").trim();
            return v === "" ? null : v;
        };
        corps.nom_entreprise = champs.get("nom_entreprise").trim();
        corps.siret = ouNull("siret");
        corps.secteur = ouNull("secteur");
        corps.ville = ouNull("siege");
        corps.site_web = ouNull("site");
        corps.description = ouNull("description");
    }

    try {
        await appelApi("/users", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(corps),
        });

        
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



document.querySelectorAll('.chiffres-seulement').forEach(champ => {
    champ.addEventListener('input', () => {
        champ.value = champ.value.replace(/[^0-9]/g, '');
    });
});



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