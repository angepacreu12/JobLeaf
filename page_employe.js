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