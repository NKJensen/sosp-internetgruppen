// Frivilligbørsen: open/close the contact and delete-request panels and send them via AJAX
(function() {
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-toggle]');
        if (!btn) return;
        var panel = document.getElementById(btn.getAttribute('data-toggle'));
        if (!panel) return;
        panel.hidden = !panel.hidden;
        if (!panel.hidden) {
            var first = panel.querySelector('input:not([type=hidden]), textarea');
            if (first) first.focus();
        }
    });

    document.querySelectorAll('form[data-action]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var button = form.querySelector('button[type=submit]');
            var status = form.querySelector('.sosp-friv-status');
            var label  = button.textContent;
            button.disabled = true;
            button.textContent = 'Sender...';

            var data = new FormData(form);
            data.append('action', form.getAttribute('data-action'));

            function show(msg, ok) {
                status.hidden = false;
                status.className = 'sosp-friv-status ' + (ok ? 'is-ok' : 'is-error');
                status.textContent = msg;
            }

            fetch(sosp_friv.ajax_url, { method: 'POST', body: data })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    show(res.data.message, res.success);
                    if (res.success) {
                        button.hidden = true;
                    } else {
                        button.disabled = false;
                        button.textContent = label;
                    }
                })
                .catch(function() {
                    show('Noget gik galt. Prøv igen senere.', false);
                    button.disabled = false;
                    button.textContent = label;
                });
        });
    });
})();
