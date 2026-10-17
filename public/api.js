
const API = '/api';
let moiGlobal = null;
let pollTimer = null;
let candidatureActiveId = null;

async function appelApi(chemin, options = {}) {
    const reponse = await fetch(API + chemin, {
        credentials: 'same-origin',
        ...options,
    });
    if (reponse.status === 204) {
        return null;
    }
    let donnees = null;
    try {
        donnees = await reponse.json();
    } catch {
    }
    if (!reponse.ok) {
        if (reponse.status === 401 && !window.location.pathname.endsWith('login_page.html')) {
            window.location.href = 'login_page.html';
        }
        const erreur = new Error(donnees?.error ?? `Erreur ${reponse.status}`);
        erreur.status = reponse.status;
        throw erreur;
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

async function chargerMoi() {
    moiGlobal = await appelApi('/me');
    const photo = document.getElementById('photo-colonne');
    if (photo) {
        photo.alt = `Profil de ${moiGlobal.prenom} ${moiGlobal.nom}`;
    }
    configurerSidebar(moiGlobal);
    initialiserBulleMessages(moiGlobal);
    return moiGlobal;
}

function configurerSidebar(moi) {
    const nav = document.getElementById('nav-sidebar');
    if (!nav) return;
    nav.innerHTML = '';

    const liens = [
        { href: 'annonces.html', texte: 'Annonces', roles: ['candidat', 'entreprise', 'admin'] },
        { href: 'candidatures.html', texte: 'Mes candidatures', roles: ['candidat'] },
        { href: 'offres.html', texte: 'Mes offres', roles: ['entreprise'] },
        { href: 'admin.html', texte: 'Admin', roles: ['admin'] },
        { href: 'profil.html', texte: 'Mon profil', roles: ['candidat', 'entreprise', 'admin'] },
    ];

    for (const l of liens) {
        if (!l.roles.includes(moi.role)) continue;
        const a = document.createElement('a');
        a.href = l.href;
        a.textContent = l.texte;
        const actif = window.location.pathname.endsWith(l.href);
        a.className = `self-start pl-2 mt-3 text-vert-fonce hover:underline ${actif ? 'font-bold underline' : ''}`;
        nav.append(a);
    }

    
    const btnLogout = document.createElement('button');
    btnLogout.type = 'button';
    btnLogout.textContent = 'Déconnexion';
    btnLogout.className = 'self-start pl-2 mt-6 text-sm text-red-700 hover:underline cursor-pointer font-semibold';
    btnLogout.addEventListener('click', async () => {
        try {
            await appelApi('/logout', { method: 'POST' });
        } finally {
            window.location.href = 'login_page.html';
        }
    });
    nav.append(btnLogout);
}


function initialiserBulleMessages(moi) {
    const bulle = document.getElementById('bulle-messages');
    if (!bulle) return;

    
    if (moi.role === 'admin') {
        bulle.classList.add('hidden');
        return;
    }

    const boutonOuvrir = document.getElementById('ouvrir-messages');
    const boutonFermer = document.getElementById('fermer-messages');
    const panneau = document.getElementById('panneau-messages');

    
    let badge = document.getElementById('badge-non-lus');
    if (!badge && boutonOuvrir) {
        badge = document.createElement('span');
        badge.id = 'badge-non-lus';
        badge.className = 'hidden absolute top-2 right-2 bg-red-600 text-white text-xs font-bold rounded-full px-1.5 py-0.5';
        boutonOuvrir.append(badge);
    }

    let vueThread = document.getElementById('vue-thread');
    if (!vueThread && panneau) {
        vueThread = document.createElement('div');
        vueThread.id = 'vue-thread';
        vueThread.className = 'hidden flex-1 flex flex-col min-h-0';
        vueThread.innerHTML = `
            <div class="flex items-center gap-2 px-4 py-2 bg-vert-clair border-b border-vert-jobleaf">
                <button type="button" id="retour-conversations" class="text-sm font-bold text-vert-fonce hover:underline cursor-pointer">&larr; Retour</button>
                <span id="titre-thread" class="font-semibold text-sm text-vert-fonce truncate"></span>
            </div>
            <div id="liste-messages-thread" class="flex-1 overflow-y-auto p-4 flex flex-col gap-2"></div>
            <form id="form-envoi-message" class="flex gap-2 p-3 border-t border-vert-jobleaf bg-white">
                <input type="text" name="contenu" required minlength="1" maxlength="5000" placeholder="Écrire un message..."
                       class="flex-1 border border-vert-jobleaf rounded-full px-3 py-1.5 text-sm outline-none">
                <button type="submit" class="bg-vert-jobleaf text-white px-4 py-1.5 rounded-full text-sm font-semibold hover:bg-vert-fonce cursor-pointer">Envoyer</button>
            </form>
        `;
        panneau.append(vueThread);

        document.getElementById('retour-conversations').addEventListener('click', () => {
            arreterPolling();
            vueThread.classList.add('hidden');
            document.getElementById('liste-conversations').classList.remove('hidden');
            chargeConversations();
        });

        document.getElementById('form-envoi-message').addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!candidatureActiveId) return;
            const champ = e.target.elements.contenu;
            const texte = champ.value.trim();
            if (!texte) return;
            champ.disabled = true;
            try {
                const msg = await appelApi(`/candidatures/${encodeURIComponent(candidatureActiveId)}/messages`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ contenu: texte }),
                });
                champ.value = '';
                ajouterMessageAuDom(msg);
            } catch (err) {
                alert(err.message);
            } finally {
                champ.disabled = false;
                champ.focus();
            }
        });
    }

    const classesRepliee = ['left-4', 'bottom-4', 'w-20', 'h-20', 'rounded-full'];
    const classesDepliee = ['left-0', 'bottom-0', 'w-full', ' sm:w-96', 'h-full', 'sm:h-[500px]', 'rounded-none', 'sm:rounded-tr-2xl'];

    window.ouvrirMessages = function (candidatureId = null, titre = '') {
        bulle.classList.remove(...classesRepliee);
        bulle.classList.add('left-0', 'bottom-0', 'w-full', 'h-full', 'rounded-none');
        boutonOuvrir.classList.add('hidden');
        boutonOuvrir.setAttribute('aria-expanded', 'true');
        panneau.classList.remove('opacity-0', 'invisible');

        if (candidatureId) {
            ouvrirThread(candidatureId, titre);
        } else {
            arreterPolling();
            document.getElementById('vue-thread').classList.add('hidden');
            document.getElementById('liste-conversations').classList.remove('hidden');
            chargeConversations();
        }
    };

    window.fermerMessages = function () {
        arreterPolling();
        bulle.classList.remove('left-0', 'bottom-0', 'w-full', 'h-full', 'rounded-none');
        bulle.classList.add(...classesRepliee);
        panneau.classList.add('opacity-0', 'invisible');
        boutonOuvrir.classList.remove('hidden');
        boutonOuvrir.setAttribute('aria-expanded', 'false');
        chargeConversations(); 
    };

    boutonOuvrir.addEventListener('click', () => window.ouvrirMessages());
    boutonFermer.addEventListener('click', () => window.fermerMessages());

    
    chargeConversations();
}

function arreterPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
    candidatureActiveId = null;
}

async function chargeConversations() {
    const liste = document.getElementById('liste-conversations');
    const modele = document.getElementById('modele-conversation');
    if (!liste || !modele) return;

    try {
        const resultat = await appelApi('/conversations?page=1&per_page=50');
        liste.innerHTML = '';

        let totalNonLus = 0;
        for (const conv of resultat.items) {
            totalNonLus += conv.non_lus ?? 0;

            const ligne = modele.content.cloneNode(true);
            const lien = ligne.querySelector('a');
            const titreConv = `${conv.interlocuteur} — ${conv.annonce.titre}`;

            ligne.querySelector('[data-champ="entreprise"]').textContent = titreConv;
            ligne.querySelector('[data-champ="apercu"]').textContent =
                conv.dernier_message?.contenu ?? 'Aucun message';

            if (conv.dernier_message?.date_envoi) {
                ligne.querySelector('[data-champ="date"]').textContent =
                    new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: '2-digit' })
                        .format(new Date(conv.dernier_message.date_envoi));
            }

            if (conv.non_lus > 0) {
                ligne.querySelector('[data-champ="entreprise"]').classList.add(' underline');
            }

            lien.addEventListener('click', (e) => {
                e.preventDefault();
                ouvrirThread(conv.candidature_id, titreConv);
            });

            liste.append(ligne);
        }

        const badge = document.getElementById('badge-non-lus');
        if (badge) {
            if (totalNonLus > 0) {
                badge.textContent = String(totalNonLus);
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }
    } catch (e) {
        console.error(e);
    }
}

async function ouvrirThread(candidatureId, titre) {
    arreterPolling();
    candidatureActiveId = candidatureId;

    document.getElementById('liste-conversations').classList.add('hidden');
    const vueThread = document.getElementById('vue-thread');
    vueThread.classList.remove('hidden');
    document.getElementById('titre-thread').textContent = titre;

    await rafraichirMessagesThread();
    pollTimer = setInterval(rafraichirMessagesThread, 5000);
}

async function rafraichirMessagesThread() {
    if (!candidatureActiveId) return;
    try {
        const res = await appelApi(`/candidatures/${encodeURIComponent(candidatureActiveId)}/messages?page=1&per_page=50`);
        const conteneur = document.getElementById('liste-messages-thread');
        conteneur.innerHTML = '';
        for (const m of res.items) {
            ajouterMessageAuDom(m);
        }
    } catch (e) {
        console.error(e);
    }
}

function ajouterMessageAuDom(m) {
    const conteneur = document.getElementById('liste-messages-thread');
    const estMoi = m.auteur_id === moiGlobal?.user_id;

    const bulleMsg = document.createElement('div');
    bulleMsg.className = `max-w-[80%] rounded-lg px-3 py-2 text-sm ${
        estMoi ? 'self-end bg-vert-jobleaf text-white' : 'self-start bg-white text-vert-fonce border border-vert-clair'
    }`;

    const pTexte = document.createElement('p');
    pTexte.className = 'whitespace-pre-line';
    pTexte.textContent = m.contenu;

    const pDate = document.createElement('span');
    pDate.className = `block text-[10px] mt-1 ${estMoi ? 'text-vert-clair' : 'text-gray-400'}`;
    pDate.textContent = new Intl.DateTimeFormat('fr-FR', {
        day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit'
    }).format(new Date(m.date_envoi));

    bulleMsg.append(pTexte, pDate);
    conteneur.append(bulleMsg);
    conteneur.scrollTop = conteneur.scrollHeight;
}