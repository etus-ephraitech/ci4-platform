/*
 * ephraitech/ci4-platform: small helpers for the sign-in pages.
 *  - show / hide password            [data-eos-reveal="<input id>"]
 *  - Caps Lock warning                [data-eos-caps="<input id>"]
 *  - "passwords don't match" hint     input[data-eos-match="<other id>"] + [data-eos-mismatch="<input id>"]
 *  - one submit only, with busy text  form[data-eos-auth-form] button[data-eos-busy]
 *  - digits only in code boxes        input.eos-auth-code
 *  - resend countdown                 [data-eos-countdown="<seconds>"] + button[data-eos-countdown-target]
 * Everything works without this file; it only makes the pages easier to use.
 */
(function () {
    'use strict';

    // Show / hide password --------------------------------------------------
    document.querySelectorAll('[data-eos-reveal]').forEach(function (button) {
        var input = document.getElementById(button.getAttribute('data-eos-reveal'));
        if (!input) { return; }

        button.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            var icon = button.querySelector('i');
            if (icon) { icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye'; }
            input.focus();
        });
    });

    // Caps Lock warning -----------------------------------------------------
    document.querySelectorAll('[data-eos-caps]').forEach(function (hint) {
        var input = document.getElementById(hint.getAttribute('data-eos-caps'));
        if (!input) { return; }

        var check = function (e) {
            if (typeof e.getModifierState === 'function') {
                hint.hidden = !e.getModifierState('CapsLock');
            }
        };
        input.addEventListener('keydown', check);
        input.addEventListener('keyup', check);
        input.addEventListener('blur', function () { hint.hidden = true; });
    });

    // Passwords match -------------------------------------------------------
    document.querySelectorAll('input[data-eos-match]').forEach(function (confirm) {
        var original = document.getElementById(confirm.getAttribute('data-eos-match'));
        var hint = document.querySelector('[data-eos-mismatch="' + confirm.id + '"]');
        if (!original || !hint) { return; }

        var check = function () {
            var mismatch = confirm.value !== '' && confirm.value !== original.value;
            hint.hidden = !mismatch;
            confirm.classList.toggle('is-invalid', mismatch);
        };
        confirm.addEventListener('input', check);
        original.addEventListener('input', function () { if (confirm.value !== '') { check(); } });
    });

    // Digits only in code boxes ---------------------------------------------
    document.querySelectorAll('input.eos-auth-code').forEach(function (input) {
        input.addEventListener('input', function () {
            var digits = input.value.replace(/\D+/g, '');
            if (digits !== input.value) { input.value = digits; }
        });
    });

    // One submit only -------------------------------------------------------
    document.querySelectorAll('form[data-eos-auth-form]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.getAttribute('data-submitting') === '1') {
                e.preventDefault();
                return;
            }
            form.setAttribute('data-submitting', '1');

            var button = form.querySelector('[data-eos-busy]');
            if (button) {
                button.setAttribute('aria-busy', 'true');
                button.classList.add('is-busy');
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>'
                    + button.getAttribute('data-eos-busy');
            }
        });
    });

    // Page shown again from the back/forward cache: undo the busy state.
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) { return; }
        document.querySelectorAll('form[data-eos-auth-form][data-submitting]').forEach(function (form) {
            form.removeAttribute('data-submitting');
            var button = form.querySelector('[data-eos-busy]');
            if (button && button.hasAttribute('data-label')) {
                button.textContent = button.getAttribute('data-label');
                button.classList.remove('is-busy');
                button.removeAttribute('aria-busy');
            }
        });
    });
    document.querySelectorAll('[data-eos-busy]').forEach(function (button) {
        button.setAttribute('data-label', button.textContent.trim());
    });

    // Resend countdown ------------------------------------------------------
    document.querySelectorAll('[data-eos-countdown]').forEach(function (note) {
        var left = parseInt(note.getAttribute('data-eos-countdown'), 10) || 0;
        var counter = note.querySelector('b');
        var button = note.parentNode.querySelector('[data-eos-countdown-target]');
        if (!button || left <= 0) { return; }

        var timer = window.setInterval(function () {
            left -= 1;
            if (counter) { counter.textContent = String(Math.max(left, 0)); }
            if (left <= 0) {
                window.clearInterval(timer);
                note.textContent = "Didn't get it? ";
                button.disabled = false;
            }
        }, 1000);
    });
})();
