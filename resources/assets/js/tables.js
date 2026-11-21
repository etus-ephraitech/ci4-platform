/*
 * ephraitech/ci4-platform: tables that read well on phones and tablets.
 *
 * Every table.eos-table gets a data-label on each cell, copied from its
 * column heading. On phones (CSS, under 768px) each row then shows as a
 * card: the first cell as its title and the rest as "Heading: value"
 * lines. On tablets the table keeps its columns, scrolls sideways with a
 * shadow at the cut edge and keeps the first column in view.
 *
 * Rows added later (DataTables pages, filters, AJAX) are labelled as they
 * arrive. Add class "eos-table-keep" to a table that must stay a grid on
 * phones (the role-permission matrix, for example).
 */
(function () {
    'use strict';

    var SELECTOR = 'table.eos-table';

    function headingLabels(table) {
        var head = table.tHead;
        if (!head || head.rows.length === 0) { return null; }

        // Use the last heading row: it holds the leaf columns.
        var labels = [];
        Array.prototype.forEach.call(head.rows[head.rows.length - 1].cells, function (th) {
            var text = (th.getAttribute('data-label') || th.textContent || '').replace(/\s+/g, ' ').trim();
            for (var i = 0; i < (th.colSpan || 1); i++) { labels.push(text); }
        });

        return labels;
    }

    // On a phone card the status sits beside the title and the buttons
    // sit together at the bottom (platform.css).
    var STATUS = /^(status|state)$/i;

    function onlyControls(cell) {
        if (!cell.querySelector('a.btn, button, .btn')) { return false; }
        var copy = cell.cloneNode(true);
        copy.querySelectorAll('a.btn, button, .btn, input, select, form > [type=hidden], .visually-hidden').forEach(function (el) { el.remove(); });

        return copy.textContent.replace(/\s+/g, '') === '';
    }

    function markRole(cell, label) {
        if (STATUS.test(label.trim()) && cell.querySelector('.eos-pill, .badge')) {
            cell.classList.add('eos-cell-status');
        } else if (onlyControls(cell)) {
            cell.classList.add('eos-cell-actions');
        }
    }

    function labelRow(row, labels) {
        var index = 0;
        Array.prototype.forEach.call(row.cells, function (cell) {
            var span = cell.colSpan || 1;

            if (span >= labels.length && labels.length > 1) {
                // An empty-state or group row that spans the table.
                cell.classList.add('eos-cell-full');
                cell.setAttribute('data-label', '');
            } else if (!cell.hasAttribute('data-label-fixed')) {
                cell.setAttribute('data-label', labels[index] || '');
            }

            if (!cell.classList.contains('eos-cell-full') && index > 0) {
                markRole(cell, labels[index] || '');
            }

            index += span;
        });
    }

    function label(table) {
        if (table.classList.contains('eos-table-keep')) { return; }

        var labels = headingLabels(table);
        if (!labels || labels.length === 0) { return; }

        table.classList.add('eos-table-stack');

        Array.prototype.forEach.call(table.tBodies, function (body) {
            Array.prototype.forEach.call(body.rows, function (row) { labelRow(row, labels); });
        });
        if (table.tFoot) {
            Array.prototype.forEach.call(table.tFoot.rows, function (row) { labelRow(row, labels); });
        }
    }

    function labelAll(root) {
        (root || document).querySelectorAll(SELECTOR).forEach(label);
    }

    // Relabel when rows change (DataTables draws, filters, AJAX panels).
    var pending = false;
    function schedule() {
        if (pending) { return; }
        pending = true;
        window.requestAnimationFrame(function () {
            pending = false;
            labelAll(document);
        });
    }

    function start() {
        labelAll(document);

        if ('MutationObserver' in window) {
            new MutationObserver(function (mutations) {
                for (var i = 0; i < mutations.length; i++) {
                    var target = mutations[i].target;
                    if (target.nodeType === 1 && (target.closest('table') || target.querySelector && target.querySelector('table'))) {
                        schedule();
                        return;
                    }
                }
            }).observe(document.body, { childList: true, subtree: true });
        }

        // Shadow at the cut edge of sideways-scrolling tables.
        document.querySelectorAll('.table-responsive').forEach(function (wrap) {
            var update = function () {
                var max = wrap.scrollWidth - wrap.clientWidth;
                wrap.classList.toggle('is-scrollable', max > 2);
                wrap.classList.toggle('is-at-start', wrap.scrollLeft <= 2);
                wrap.classList.toggle('is-at-end', wrap.scrollLeft >= max - 2);
            };
            wrap.addEventListener('scroll', update, { passive: true });
            window.addEventListener('resize', update);
            if ('ResizeObserver' in window) { new ResizeObserver(update).observe(wrap); }
            update();
        });
    }

    window.EOS = window.EOS || {};
    window.EOS.labelTables = labelAll;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
