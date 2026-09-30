(function () {
  'use strict';

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
  }

  function parseDate(value) {
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
    if (!m) return null;
    return Date.UTC(+m[1], +m[2] - 1, +m[3]);
  }

  function formatDate(ms) {
    var d = new Date(ms);
    var mm = String(d.getUTCMonth() + 1).padStart(2, '0');
    var dd = String(d.getUTCDate()).padStart(2, '0');
    return d.getUTCFullYear() + '-' + mm + '-' + dd;
  }

  function prettyDate(value) {
    var ms = parseDate(value);
    if (ms === null) return value;
    return new Date(ms).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' });
  }

  function initSidebar() {
    var toggle = $('#menu-toggle');
    var sidebar = $('#sidebar');
    var scrim = $('#scrim');
    if (!toggle || !sidebar || !scrim) return;

    function setOpen(open) {
      sidebar.classList.toggle('is-open', open);
      scrim.hidden = !open;
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
      document.documentElement.classList.toggle('no-scroll', open);
      if (open) {
        var first = $('.nav-link', sidebar);
        if (first) first.focus();
      }
    }

    toggle.addEventListener('click', function () {
      setOpen(!sidebar.classList.contains('is-open'));
    });
    scrim.addEventListener('click', function () { setOpen(false); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && sidebar.classList.contains('is-open')) {
        setOpen(false);
        toggle.focus();
      }
    });
    var wide = window.matchMedia('(min-width: 901px)');
    var onChange = function () { if (wide.matches) setOpen(false); };
    if (wide.addEventListener) wide.addEventListener('change', onChange);
  }

  function dismissNotice(notice) {
    if (!notice || notice.classList.contains('is-leaving')) return;
    notice.classList.add('is-leaving');
    setTimeout(function () { if (notice.parentNode) notice.parentNode.removeChild(notice); }, 260);
  }

  function initNotices() {
    $$('.notice').forEach(function (notice) {
      var close = $('.notice-close', notice);
      if (close) close.addEventListener('click', function () { dismissNotice(notice); });
      if (notice.hasAttribute('data-autodismiss')) {
        setTimeout(function () { dismissNotice(notice); }, 6000);
      }
    });
  }

  function initConfirm() {
    var dialog = $('#confirm-dialog');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    var title = $('#confirm-title');
    var body = $('#confirm-body');
    var accept = $('#confirm-accept');
    var pending = null;

    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) return;
      e.preventDefault();
      pending = form;
      title.textContent = form.getAttribute('data-confirm-title') || 'Are you sure?';
      body.textContent = form.getAttribute('data-confirm');
      accept.textContent = form.getAttribute('data-confirm-label') || 'Confirm';
      dialog.returnValue = '';
      dialog.showModal();
    });

    dialog.addEventListener('close', function () {
      var form = pending;
      pending = null;
      if (form && dialog.returnValue === 'confirm') form.submit();
    });
  }

  function initDialogs() {
    $$('dialog').forEach(function (dialog) {
      dialog.addEventListener('click', function (e) {
        if (e.target === dialog) dialog.close();
      });
      $$('[data-dialog-close]', dialog).forEach(function (btn) {
        btn.addEventListener('click', function () { dialog.close(); });
      });
    });
  }

  function initPickers() {
    $$('[data-picker]').forEach(function (root) {
      var endpoint = root.getAttribute('data-endpoint');
      var searchBox = $('[data-picker-search]', root);
      var input = $('[data-picker-input]', root);
      var list = $('[data-picker-list]', root);
      var hidden = $('[data-picker-value]', root);
      var selected = $('[data-picker-selected]', root);
      var status = $('[data-picker-status]', root);
      var error = $('[data-picker-error]', root);
      var changeBtn = $('[data-picker-change]', root);
      var results = [];
      var active = -1;
      var timer = null;
      var seq = 0;

      function announce(text) { if (status) status.textContent = text; }

      function closeList() {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
      }

      function setActive(index) {
        var options = $$('.picker-option', list);
        options.forEach(function (o, i) { o.setAttribute('aria-selected', i === index ? 'true' : 'false'); });
        active = index;
        if (index >= 0 && options[index]) {
          input.setAttribute('aria-activedescendant', options[index].id);
          options[index].scrollIntoView({ block: 'nearest' });
        } else {
          input.removeAttribute('aria-activedescendant');
        }
      }

      function choose(item) {
        if (!item || item.disabled) return;
        hidden.value = item.id;
        $('[data-picker-code]', selected).textContent = item.code || '';
        $('[data-picker-title]', selected).textContent = item.title || '';
        $('[data-picker-meta]', selected).textContent = item.meta || '';
        selected.hidden = false;
        searchBox.hidden = true;
        if (error) error.hidden = true;
        closeList();
        announce((item.title || 'Item') + ' selected.');
        if (changeBtn) changeBtn.focus();
        root.dispatchEvent(new CustomEvent('picker:change', { bubbles: true, detail: item }));
      }

      function clear() {
        hidden.value = '';
        selected.hidden = true;
        searchBox.hidden = false;
        input.value = '';
        input.focus();
        root.dispatchEvent(new CustomEvent('picker:change', { bubbles: true, detail: null }));
      }

      function render(items, message) {
        list.textContent = '';
        results = items;
        if (!items.length) {
          list.appendChild(el('li', 'picker-empty', message || 'No matches.'));
        } else {
          items.forEach(function (item, i) {
            var li = el('li', 'picker-option');
            li.id = list.id + '-' + i;
            li.setAttribute('role', 'option');
            li.setAttribute('aria-selected', 'false');
            if (item.disabled) li.setAttribute('aria-disabled', 'true');
            li.appendChild(el('span', 'picker-code', item.code || ''));
            var text = el('span', 'picker-text');
            text.appendChild(el('span', 'picker-title', item.title));
            if (item.meta) text.appendChild(el('span', 'picker-meta', item.meta));
            li.appendChild(text);
            if (item.note) li.appendChild(el('span', 'picker-note', item.note));
            li.addEventListener('mousedown', function (e) { e.preventDefault(); });
            li.addEventListener('click', function () { choose(items[i]); });
            list.appendChild(li);
          });
        }
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        active = -1;
        announce(items.length ? items.length + (items.length === 1 ? ' result' : ' results') + ' available.' : (message || 'No matches.'));
      }

      function search(q) {
        var mine = ++seq;
        fetch(endpoint + encodeURIComponent(q), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
          .then(function (r) {
            if (!r.ok) throw new Error('bad status');
            return r.json();
          })
          .then(function (data) {
            if (mine !== seq) return;
            render(data.results || []);
          })
          .catch(function () {
            if (mine !== seq) return;
            render([], 'Search failed. Check your connection and try again.');
          });
      }

      input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 2) {
          seq++;
          closeList();
          return;
        }
        timer = setTimeout(function () { search(q); }, 200);
      });

      input.addEventListener('keydown', function (e) {
        var options = $$('.picker-option', list);
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          if (list.hidden) return;
          setActive(Math.min(options.length - 1, active + 1));
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          if (list.hidden) return;
          setActive(Math.max(0, active - 1));
        } else if (e.key === 'Enter') {
          e.preventDefault();
          if (!list.hidden) {
            if (active < 0) {
              var firstOpen = results.findIndex(function (r) { return !r.disabled; });
              if (firstOpen >= 0) choose(results[firstOpen]);
            } else {
              choose(results[active]);
            }
          }
        } else if (e.key === 'Escape' && !list.hidden) {
          e.preventDefault();
          e.stopPropagation();
          closeList();
        }
      });

      input.addEventListener('blur', function () { setTimeout(closeList, 120); });
      if (changeBtn) changeBtn.addEventListener('click', clear);
    });

    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!(form instanceof HTMLFormElement)) return;
      var blocked = null;
      $$('[data-picker]', form).forEach(function (root) {
        if (!root.hasAttribute('data-required')) return;
        var value = $('[data-picker-value]', root);
        if (value && !value.value) {
          var err = $('[data-picker-error]', root);
          if (err) err.hidden = false;
          if (!blocked) blocked = $('[data-picker-input]', root);
        }
      });
      if (blocked) {
        e.preventDefault();
        blocked.focus();
      }
    });
  }

  function initSubmitLock() {
    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (e.defaultPrevented || !(form instanceof HTMLFormElement)) return;
      if ((form.method || '').toLowerCase() !== 'post') return;
      if (form.hasAttribute('data-no-lock')) return;
      if (form.dataset.submitting === '1') {
        e.preventDefault();
        return;
      }
      form.dataset.submitting = '1';
      $$('button[type="submit"]', form).forEach(function (b) { b.classList.add('is-busy'); });
    });
    window.addEventListener('pageshow', function (e) {
      if (!e.persisted) return;
      $$('form').forEach(function (form) {
        delete form.dataset.submitting;
        $$('.is-busy', form).forEach(function (b) { b.classList.remove('is-busy'); });
      });
    });
  }

  function initTableFilter() {
    $$('[data-filter]').forEach(function (input) {
      var table = document.getElementById(input.getAttribute('data-filter'));
      if (!table || !table.tBodies.length) return;
      var rows = $$('tbody tr[data-row]', table);
      var none = $('[data-no-match]', table);
      var counter = $('[data-filter-count]');
      var total = rows.length;

      input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase();
        var shown = 0;
        rows.forEach(function (row) {
          var hit = q === '' || row.textContent.toLowerCase().indexOf(q) !== -1;
          row.hidden = !hit;
          if (hit) shown++;
        });
        if (none) none.hidden = shown !== 0 || total === 0;
        if (counter) counter.textContent = q === '' ? total + (total === 1 ? ' loan' : ' loans') : shown + ' of ' + total;
      });

      if (input.value.trim() !== '') input.dispatchEvent(new Event('input'));
    });
  }

  function initReturnDialog() {
    var dialog = $('#return-dialog');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    var rate = parseFloat(dialog.getAttribute('data-rate')) || 0;
    var currency = dialog.getAttribute('data-currency') || '';
    var fields = {
      tx: $('[data-ret-tx]', dialog),
      book: $('[data-ret-book]', dialog),
      borrower: $('[data-ret-borrower]', dialog),
      due: $('[data-ret-due]', dialog),
      date: $('[data-ret-date]', dialog),
      fine: $('[data-ret-fine]', dialog),
      fineText: $('[data-ret-fine-text]', dialog),
      paid: $('[data-ret-paid]', dialog),
      paidInput: $('[data-ret-paid] input', dialog)
    };
    var dueValue = '';

    function recompute() {
      var due = parseDate(dueValue);
      var ret = parseDate(fields.date.value);
      var days = due !== null && ret !== null ? Math.max(0, Math.round((ret - due) / 86400000)) : 0;
      if (days > 0) {
        var fine = days * rate;
        fields.fineText.textContent = days + (days === 1 ? ' day' : ' days') + ' overdue. Fine: ' + currency + fine.toFixed(2) + '.';
        fields.fine.hidden = false;
        fields.paid.hidden = false;
      } else {
        fields.fine.hidden = true;
        fields.paid.hidden = true;
        fields.paidInput.checked = false;
      }
    }

    $$('[data-return-open]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        dueValue = btn.getAttribute('data-due');
        fields.tx.value = btn.getAttribute('data-tx');
        fields.book.textContent = btn.getAttribute('data-book');
        fields.borrower.textContent = btn.getAttribute('data-borrower');
        fields.due.textContent = prettyDate(dueValue);
        fields.date.min = btn.getAttribute('data-borrowed');
        fields.date.value = fields.date.max;
        recompute();
        dialog.showModal();
      });
    });

    fields.date.addEventListener('input', recompute);
  }

  function initDueDate() {
    var borrow = $('input[name="borrow_date"]');
    var due = $('input[name="due_date"]');
    if (!borrow || !due) return;
    var days = parseInt(borrow.getAttribute('data-days'), 10) || 14;
    due.addEventListener('input', function () { due.dataset.touched = '1'; });
    borrow.addEventListener('change', function () {
      var start = parseDate(borrow.value);
      if (start === null) return;
      due.min = borrow.value;
      if (!due.dataset.touched) due.value = formatDate(start + days * 86400000);
    });
  }

  function initCodeInputs() {
    $$('input.is-code').forEach(function (input) {
      input.addEventListener('input', function () {
        var pos = input.selectionStart;
        input.value = input.value.toUpperCase();
        input.setSelectionRange(pos, pos);
      });
    });
  }

  function initPrint() {
    $$('[data-print]').forEach(function (btn) {
      btn.addEventListener('click', function () { window.print(); });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initSidebar();
    initNotices();
    initDialogs();
    initPickers();
    initConfirm();
    initSubmitLock();
    initTableFilter();
    initReturnDialog();
    initDueDate();
    initCodeInputs();
    initPrint();
  });
}());
