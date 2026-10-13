/* OIL-PMS shell behaviour for the server-rendered Laravel UI (no framework).
   Page content is Blade. This file handles shell interactions only:
   sidebar, dropdown menus, theme, toasts (from session flash), dialogs (data-open / data-fill),
   confirm prompts (form[data-confirm]), auto-submitting forms/selects, [data-href] rows, tab snapshots. */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
  var closeMenus = function () { $$('.menu').forEach(function (m) { m.hidden = true; }); };

  /* ---------- toasts ---------- */
  function toast(t) {
    var host = $('#toasts'); if (!host || !t) return;
    var el = document.createElement('div');
    el.className = 'toast ' + (t.kind || '');
    el.textContent = t.msg;
    if (t.action && t.action.url) {
      var a = document.createElement('a'); a.className = 'btn sm'; a.style.marginLeft = '10px'; a.href = t.action.url; a.textContent = t.action.label || 'Open'; el.appendChild(a);
    }
    host.appendChild(el);
    setTimeout(function () { el.remove(); }, t.action ? 7000 : 4500);
  }
  window.OILPMS_toast = toast;
  (window.OILPMS_TOASTS || []).forEach(toast);
  var toastHost = $('#toasts'); if (toastHost) toastHost.style.display = 'flex';

  /* ---------- dialogs ---------- */
  function openDlg(id, fill) {
    var d = document.getElementById('dlg-' + id); if (!d) return;
    if (fill) Object.keys(fill).forEach(function (k) {
      $$('[name="' + k + '"]', d).forEach(function (f) {
        if (f.type === 'checkbox') f.checked = !!fill[k]; else f.value = fill[k];
      });
      var t = $('[data-fill-text="' + k + '"]', d); if (t) t.textContent = fill[k];
    });
    d.hidden = false;
    var f = $('input:not([type=hidden]):not([readonly]),select,textarea', d); if (f) setTimeout(function () { f.focus(); }, 30);
  }
  function closeDlg(el) { var d = el.closest('[data-dialog]'); if (d) d.hidden = true; }
  $$('[data-dialog]:not([hidden])').forEach(function (d) { var f = $('input:not([type=hidden]),select,textarea', d); if (f) f.focus(); });

  document.addEventListener('click', function (e) {
    var t = e.target;
    if (!t.closest('.rel')) closeMenus();

    var open = t.closest('[data-open]');
    if (open) {
      var fill = null; try { fill = open.getAttribute('data-fill') ? JSON.parse(open.getAttribute('data-fill')) : null; } catch (x) {}
      openDlg(open.getAttribute('data-open'), fill); return;
    }
    if (t.closest('[data-close]')) { closeDlg(t); return; }
    if (t.hasAttribute('data-dialog') && t === e.target) { t.hidden = true; return; }   // click on the backdrop

    var snapBtn = t.closest('[data-snap-target]');
    if (snapBtn) {
      var wrap = snapBtn.closest('[data-snaps]');
      if (wrap) {
        var k = snapBtn.getAttribute('data-snap-target');
        $$('[data-snap]', wrap).forEach(function (s) { s.hidden = s.getAttribute('data-snap') !== k; });
        var u = new URL(location.href); u.searchParams.set('tab', k); history.replaceState(null, '', u);
      }
      return;
    }

    var row = t.closest('[data-href]');
    if (row && !t.closest('a,button,input,select,textarea,label,form')) { location.href = row.getAttribute('data-href'); return; }

    var act = t.closest('[data-act]');
    if (!act) return;
    switch (act.getAttribute('data-act')) {
      case 'sidetoggle': $('.side').classList.toggle('col'); break;
      case 'mobnav': $('.side').classList.toggle('openm'); break;
      case 'ngtoggle': act.classList.toggle('open'); break;
      case 'menu': var m = $('#menu-' + act.getAttribute('data-m')); var was = m.hidden; closeMenus(); m.hidden = !was; break;
      case 'theme':
        var r = document.documentElement;
        var dark = r.dataset.theme === 'dark' || (!r.dataset.theme && matchMedia('(prefers-color-scheme: dark)').matches);
        r.dataset.theme = dark ? 'light' : 'dark';
        try { localStorage.setItem('oilpms.theme', r.dataset.theme); } catch (x) {}
        break;
      default: break;
    }
  });

  /* confirm prompt before one-click actions */
  document.addEventListener('submit', function (e) {
    var f = e.target.closest('form[data-confirm]');
    if (f && !window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
  });

  /* filters / selectors that submit on change */
  document.addEventListener('change', function (e) {
    var f = e.target.closest('form[data-autosubmit]');
    if (f && e.target.matches('select,input[type=checkbox]')) f.submit();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeMenus(); $$('[data-dialog]').forEach(function (d) { d.hidden = true; }); }
  });

  try { var th = localStorage.getItem('oilpms.theme'); if (th) document.documentElement.dataset.theme = th; } catch (x) {}

  /* tab snapshots (static pages) deep link ?tab=<key> */
  var want = new URLSearchParams(location.search).get('tab');
  if (want) $$('[data-snaps]').forEach(function (w) {
    var hit = $$('[data-snap]', w).some(function (s) { return s.getAttribute('data-snap') === want; });
    if (hit) $$('[data-snap]', w).forEach(function (s) { s.hidden = s.getAttribute('data-snap') !== want; });
  });

  /* ?new=1 opens the dialog marked data-open-on-new */
  if (new URLSearchParams(location.search).get('new')) {
    var nd = $('[data-dialog][data-open-on-new]'); if (nd) nd.hidden = false;
  }
  /* global search dropdown (top bar) */
  (function () {
    var inp = $('#gsin'), box = $('#gsr'); if (!inp || !box) return;
    var url = inp.getAttribute('data-gs'), tm = null;
    function esc(x) { return String(x).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    inp.addEventListener('input', function () {
      clearTimeout(tm); var q = inp.value.trim();
      if (!q) { box.hidden = true; return; }
      tm = setTimeout(function () {
        fetch(url + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
          .then(function (r) { return r.json(); }).then(function (d) {
            box.innerHTML = d.groups.length ? d.groups.map(function (g) {
              return '<h5>' + esc(g.title) + '</h5>' + g.items.map(function (i) { return '<a href="' + esc(i.u) + '"><b>' + esc(i.t) + '</b>' + (i.s ? '<small>' + esc(i.s) + '</small>' : '') + '</a>'; }).join('');
            }).join('') : '<div class="empty"><b>No results for “' + esc(q) + '”</b>Try a PO number, supplier or material.</div>';
            box.hidden = false;
          }).catch(function () {});
      }, 150);
    });
    document.addEventListener('click', function (e) { if (!e.target.closest('.gs')) box.hidden = true; });
  })();
})();
