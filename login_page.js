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

document.querySelectorAll('.chiffres-seulement').forEach(champ => {
    champ.addEventListener('input', () => {
        champ.value = champ.value.replace(/[^0-9]/g, '');
    });
});

// Affichage du bloc employeur si case cochée //

const profil = document.getElementById('insc-profil');
const blocEmployeur = document.querySelector('.employeur');
const champsObligatoires = blocEmployeur.querySelectorAll('[required]');

function majBlocEmployeur() {
    const estEmployeur = profil.value === 'employeur';
    blocEmployeur.classList.toggle('visible', estEmployeur);

    champsObligatoires.forEach(champ => {
        champ.required = estEmployeur;
    });
}

profil.addEventListener('change', majBlocEmployeur);
majBlocEmployeur();