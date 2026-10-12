
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
                .format(new Date(conv.date));;

        liste.append(ligne);
    }
}

// Cadre annonce //