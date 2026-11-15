/* ephraitech/ci4-platform — platform behaviour shared by every page. */
(function () {
    'use strict';

    // Mobile sidebar ------------------------------------------------------
    var sidebar = document.getElementById('eos-sidebar');
    var backdrop = document.querySelector('.eos-backdrop');
    var toggle = document.querySelector('.eos-menu-toggle');

    function setSidebar(open) {
        if (!sidebar) { return; }
        sidebar.classList.toggle('is-open', open);
        if (backdrop) { backdrop.hidden = !open; }
        if (toggle) { toggle.setAttribute('aria-expanded', open ? 'true' : 'false'); }
        if (open) {
            var first = sidebar.querySelector('a');
            if (first) { first.focus(); }
        }
    }

    document.querySelectorAll('[data-eos-toggle="sidebar"]').forEach(function (el) {
        el.addEventListener('click', function () {
            setSidebar(!sidebar.classList.contains('is-open'));
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('is-open')) {
            setSidebar(false);
            if (toggle) { toggle.focus(); }
        }
    });

    // Collapsible sidebar (desktop) -----------------------------------------
    var collapseBtn = document.querySelector('[data-eos-collapse]');
    function syncCollapse() {
        var collapsed = document.documentElement.classList.contains('eos-collapsed');
        if (collapseBtn) {
            collapseBtn.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
            collapseBtn.setAttribute('aria-label', collapsed ? 'Expand menu' : 'Collapse menu');
        }
    }
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function () {
            var collapsed = document.documentElement.classList.toggle('eos-collapsed');
            try { localStorage.setItem('eos-sidebar', collapsed ? 'collapsed' : 'expanded'); } catch (e) {}
            syncCollapse();
        });
        syncCollapse();
    }

    // Global search with live results -------------------------------------
    var searchForm = document.querySelector('[data-eos-search]');
    if (searchForm) {
        var input = searchForm.querySelector('input[name="q"]');
        var box = searchForm.querySelector('.eos-search-results');
        var endpoint = searchForm.getAttribute('data-eos-search');
        var timer = null;
        var seq = 0;
        var activeIndex = -1;

        var escapeHtml = function (text) {
            return String(text).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        };

        var close = function () {
            box.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            activeIndex = -1;
        };

        var render = function (results, term) {
            var html = '';
            var lastType = null;

            results.forEach(function (r, i) {
                if (r.type !== lastType) {
                    html += '<p class="eos-search-group">' + escapeHtml(r.type) + '</p>';
                    lastType = r.type;
                }
                html += '<a class="eos-search-item" role="option" id="eos-sr-' + i + '" href="' + escapeHtml(r.url) + '">' +
                    '<i class="bi ' + escapeHtml(r.icon) + '" aria-hidden="true"></i>' +
                    '<span><span>' + escapeHtml(r.title) + '</span><small>' + escapeHtml(r.subtitle) + '</small></span></a>';
            });

            if (results.length === 0) {
                html = '<p class="eos-search-empty">No matches for “' + escapeHtml(term) + '”.</p>';
            }

            html += '<a class="eos-search-all" href="' + escapeHtml(searchForm.action) + '?q=' + encodeURIComponent(term) + '">See all results</a>';
            box.innerHTML = html;
            box.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            activeIndex = -1;
        };

        input.addEventListener('input', function () {
            var term = input.value.trim();
            clearTimeout(timer);
            if (term.length < 2) { close(); return; }

            timer = setTimeout(function () {
                var mine = ++seq;
                fetch(endpoint + '?q=' + encodeURIComponent(term), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : { results: [] }; })
                    .then(function (data) { if (mine === seq) { render(data.results || [], term); } })
                    .catch(function () { if (mine === seq) { close(); } });
            }, 220);
        });

        input.addEventListener('keydown', function (e) {
            var items = box.querySelectorAll('.eos-search-item');
            if (e.key === 'Escape') { close(); return; }
            if (box.hidden || items.length === 0) { return; }
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = e.key === 'ArrowDown' ? Math.min(activeIndex + 1, items.length - 1) : Math.max(activeIndex - 1, 0);
                items.forEach(function (el, i) { el.classList.toggle('is-active', i === activeIndex); });
                input.setAttribute('aria-activedescendant', items[activeIndex].id);
            } else if (e.key === 'Enter' && activeIndex >= 0) {
                e.preventDefault();
                window.location.href = items[activeIndex].href;
            }
        });

        document.addEventListener('click', function (e) {
            if (!searchForm.contains(e.target)) { close(); }
        });

        // "/" focuses search from anywhere except text fields.
        document.addEventListener('keydown', function (e) {
            var tag = (e.target.tagName || '').toLowerCase();
            if (e.key === '/' && !e.ctrlKey && !e.metaKey && tag !== 'input' && tag !== 'textarea' && tag !== 'select' && !e.target.isContentEditable) {
                e.preventDefault();
                input.focus();
            }
        });
    }

    // CSRF for AJAX -------------------------------------------------------
    // CodeIgniter regenerates the token; responses carrying the header
    // keep the meta tag current so the next request still validates.
    var headerMeta = document.querySelector('meta[name="csrf-header"]');
    var hashMeta = document.querySelector('meta[name="csrf-hash"]');

    window.EOS = window.EOS || {};

    window.EOS.csrfHeaders = function () {
        var headers = { 'X-Requested-With': 'XMLHttpRequest' };
        if (headerMeta && hashMeta) { headers[headerMeta.content] = hashMeta.content; }
        return headers;
    };

    window.EOS.refreshCsrf = function (response) {
        if (!headerMeta || !hashMeta || !response || !response.headers) { return; }
        var next = response.headers.get(headerMeta.content);
        if (next) { hashMeta.content = next; }
    };

    if (window.jQuery) {
        jQuery.ajaxSetup({
            beforeSend: function (xhr) {
                var headers = window.EOS.csrfHeaders();
                Object.keys(headers).forEach(function (name) { xhr.setRequestHeader(name, headers[name]); });
            },
            complete: function (xhr) {
                var next = headerMeta ? xhr.getResponseHeader(headerMeta.content) : null;
                if (next && hashMeta) { hashMeta.content = next; }
            }
        });
    }

    // Notifications -------------------------------------------------------
    if (window.toastr) {
        toastr.options = { positionClass: 'toast-bottom-right', timeOut: 4000, closeButton: true, newestOnTop: true };
    }

    window.EOS.notify = function (type, message) {
        if (window.toastr && typeof toastr[type] === 'function') {
            toastr[type](message);
        } else {
            window.alert(message);
        }
    };

    // Server-side DataTables -----------------------------------------------
    // EOS.table('#clients', '/crm/clients/data', [{orderable: false}, ...], function () {
    //     return { status: document.getElementById('f-status').value };
    // });
    window.EOS.table = function (selector, url, columns, extraParams) {
        if (!window.DataTable) { return null; }

        var options = {
            serverSide: true,
            processing: true,
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            order: [],
            ajax: {
                url: url,
                data: function (params) {
                    var extra = typeof extraParams === 'function' ? extraParams() : {};
                    Object.keys(extra).forEach(function (key) { params[key] = extra[key]; });
                    return params;
                }
            },
            language: {
                search: 'Search',
                searchPlaceholder: 'Name, code, phone…',
                emptyTable: 'Nothing here yet.',
                zeroRecords: 'No matches. Try another search or clear the filters.',
                processing: 'Loading…'
            }
        };

        if (columns) { options.columns = columns; }

        return new DataTable(selector, options);
    };

    // Re-run a table's query when a filter control changes.
    document.addEventListener('change', function (e) {
        var target = e.target.closest('[data-eos-filter]');
        if (!target || !window.DataTable) { return; }
        var table = document.querySelector(target.getAttribute('data-eos-filter'));
        if (table && window.jQuery && DataTable.isDataTable(table)) { jQuery(table).DataTable().ajax.reload(); }
    });

    // Confirm before destructive actions ----------------------------------
    // <form data-eos-confirm="Delete this client?" ...> or
    // <button data-eos-confirm="..." form="..."> asks before submitting.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var message = form.getAttribute('data-eos-confirm') ||
            (e.submitter && e.submitter.getAttribute('data-eos-confirm'));

        if (!message || form.dataset.eosConfirmed === '1') { return; }

        e.preventDefault();

        var proceed = function () {
            form.dataset.eosConfirmed = '1';
            if (e.submitter && e.submitter.name) {
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = e.submitter.name;
                hidden.value = e.submitter.value;
                form.appendChild(hidden);
            }
            form.submit();
        };

        if (window.Swal) {
            Swal.fire({
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: form.getAttribute('data-eos-confirm-label') || 'Yes, continue',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#032e88',
                reverseButtons: true,
                focusCancel: true
            }).then(function (result) { if (result.isConfirmed) { proceed(); } });
        } else if (window.confirm(message)) {
            proceed();
        }
    });
})();
