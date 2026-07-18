// Rafraichissement des messages par interrogation periodique de
// messages.php. On ne demande que les messages posterieurs au dernier id
// connu, donc chaque tour ne transporte que la nouveaute.

(function () {
    'use strict';

    var POLL_MS = 3000;
    // Palier d'attente apres echec reseau : on ralentit au lieu de marteler
    // un serveur qui ne repond plus, puis on revient a la normale.
    var BACKOFF_MS = [5000, 10000, 30000];

    var list = document.querySelector('#main ul');
    var pane = document.querySelector('#main');
    if (!list || !pane) {
        return;
    }

    var lastId = 0;
    var failures = 0;
    var timer = null;

    // Point de depart : le dernier message rendu par PHP au chargement.
    var rendered = list.querySelectorAll('li[data-id]');
    if (rendered.length) {
        lastId = parseInt(rendered[rendered.length - 1].getAttribute('data-id'), 10) || 0;
    }

    // L'utilisateur est-il en bas de la conversation ? Si oui on suivra les
    // nouveaux messages ; s'il est remonte lire l'historique, on le laisse
    // ou il est plutot que de le ramener de force en bas.
    function isAtBottom() {
        return pane.scrollHeight - pane.scrollTop - pane.clientHeight < 40;
    }

    // Construction par noeuds de texte, jamais par innerHTML : le contenu
    // vient d'autres utilisateurs, l'injecter en HTML rouvrirait la faille
    // XSS corrigee cote PHP.
    function renderMessage(msg) {
        var li = document.createElement('li');
        li.className = 'messange';
        li.setAttribute('data-id', msg.id);

        var time = document.createElement('span');
        time.textContent = msg.time + ' -';

        var author = document.createElement('b');
        author.textContent = msg.author;

        li.appendChild(time);
        li.appendChild(author);
        li.appendChild(document.createTextNode('  :  ' + msg.message));
        return li;
    }

    function append(messages) {
        var stick = isAtBottom();
        var fragment = document.createDocumentFragment();

        messages.forEach(function (msg) {
            if (msg.id > lastId) {
                lastId = msg.id;
            }
            fragment.appendChild(renderMessage(msg));
        });

        list.appendChild(fragment);
        if (stick) {
            pane.scrollTop = pane.scrollHeight;
        }
    }

    function schedule(delay) {
        clearTimeout(timer);
        timer = setTimeout(poll, delay);
    }

    function poll() {
        // Onglet en arriere-plan : inutile d'interroger le serveur, on
        // reprendra au retour via visibilitychange.
        if (document.hidden) {
            schedule(POLL_MS);
            return;
        }

        fetch('messages.php?after=' + encodeURIComponent(lastId), {
            credentials: 'same-origin'
        })
            .then(function (response) {
                // Session expiree : le formulaire de la page ne servirait
                // plus a rien, on renvoie vers le login.
                if (response.status === 401) {
                    window.location.href = 'login.php';
                    return null;
                }
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                if (!data || !data.messages) {
                    return;
                }
                failures = 0;
                if (data.messages.length) {
                    append(data.messages);
                }
                // Lot plein : d'autres messages attendent probablement,
                // on enchaine sans laisser passer l'intervalle complet.
                var full = data.page_size && data.messages.length >= data.page_size;
                schedule(full ? 0 : POLL_MS);
            })
            .catch(function (err) {
                console.error('Polling failed:', err);
                var wait = BACKOFF_MS[Math.min(failures, BACKOFF_MS.length - 1)];
                failures += 1;
                schedule(wait);
            });
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            schedule(0);
        }
    });

    pane.scrollTop = pane.scrollHeight;
    schedule(POLL_MS);
})();
