/*
 * admin_dashboard.js — draws the chart and refreshes the dashboard every 60 seconds (handout 13.3).
 *
 * RULES THIS FILE FOLLOWS
 *   - No inline scripts: this file is loaded with <script src="...">.
 *   - The log contains what attackers typed (even "<script>alert(1)</script>").
 *     So every value is written with textContent. NEVER innerHTML.
 *   - The refresh asks   admin_dashboard.php?data=1   which checks for an admin on the server.
 */
(function () {
  'use strict';

  var root   = document.getElementById('dash-root');
  var canvas = document.getElementById('hourly-chart');
  if (!root || !canvas) { return; }

  var TONES = ['ok', 'bad', 'warn', 'info', 'muted'];     // only these color names are accepted
  var chart = null;

  function cssVar(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  }

  /* ---------- chart ---------- */

  function colors(values) {
    var max = Math.max.apply(null, values.concat([0]));
    return values.map(function (v) {
      return (v > 0 && v === max && max >= 3) ? cssVar('--danger') : cssVar('--teal');   // the peak hour is red
    });
  }

  function drawChart(labels, values) {
    if (typeof Chart === 'undefined') { return; }

    if (chart) {
      chart.data.labels = labels;
      chart.data.datasets[0].data = values;
      chart.data.datasets[0].backgroundColor = colors(values);
      chart.update();
      return;
    }

    chart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{ label: 'Failed log-ins', data: values, backgroundColor: colors(values), borderRadius: 4 }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false }, ticks: { color: cssVar('--muted'), maxTicksLimit: 8 } },
          y: { beginAtZero: true, grid: { color: cssVar('--line') }, ticks: { color: cssVar('--muted'), precision: 0 } }
        }
      }
    });
  }

  /* after the light/dark button is pressed, paint the chart again with the new colors */
  document.addEventListener('click', function (ev) {
    if (!chart || !ev.target.closest('[data-theme-toggle]')) { return; }
    setTimeout(function () {
      chart.options.scales.x.ticks.color = cssVar('--muted');
      chart.options.scales.y.ticks.color = cssVar('--muted');
      chart.options.scales.y.grid.color  = cssVar('--line');
      chart.data.datasets[0].backgroundColor = colors(chart.data.datasets[0].data);
      chart.update();
    }, 50);
  });

  /* ---------- small DOM helpers (textContent only) ---------- */

  function cell(text, className) {
    var td = document.createElement('td');
    if (className) { td.className = className; }
    td.textContent = text;
    return td;
  }

  function pill(text, tone) {
    var span = document.createElement('span');
    span.className = 'pill pill--' + (TONES.indexOf(tone) !== -1 ? tone : 'muted');
    span.textContent = text;
    return span;
  }

  function emptyRow(columns, text) {
    var tr = document.createElement('tr');
    var td = cell(text, 'muted');
    td.colSpan = columns;
    tr.appendChild(td);
    return tr;
  }

  function replaceChildren(parent, children) {
    while (parent.firstChild) { parent.removeChild(parent.firstChild); }
    children.forEach(function (c) { parent.appendChild(c); });
  }

  /* ---------- fill the page with new data ---------- */

  function apply(data) {
    // 1. summary cards
    var s = data.summary || {};
    root.querySelectorAll('[data-stat]').forEach(function (el) {
      var v = s[el.getAttribute('data-stat')];
      el.textContent = (v === null || v === undefined) ? '—' : String(v);
    });

    // 2. chart
    if (data.hourly) { drawChart(data.hourly.labels || [], data.hourly.values || []); }

    // 3. top IP addresses
    var ips = document.getElementById('top-ips');
    if (ips) {
      var rows = (data.top_ips || []).map(function (r) {
        var tr = document.createElement('tr');
        tr.appendChild(cell(r.ip, 'mono'));
        tr.appendChild(cell(String(r.failures)));
        var td = document.createElement('td');
        if (r.blocked) { td.appendChild(pill('BLOCKED', 'warn')); } else { td.textContent = '—'; }
        tr.appendChild(td);
        return tr;
      });
      replaceChildren(ips, rows.length ? rows : [emptyRow(3, 'No failed log-ins in the last 24 hours.')]);
    }

    // 4. IP blocks
    var blocks = document.getElementById('ip-blocks');
    if (blocks) {
      var blockRows = (data.blocks || []).map(function (r) {
        var tr = document.createElement('tr');
        tr.appendChild(cell(r.ip, 'mono'));
        tr.appendChild(cell(r.reason));
        tr.appendChild(cell(String(r.failures)));
        tr.appendChild(cell(r.blocked_at, 'nowrap'));
        tr.appendChild(cell(r.until, 'nowrap'));
        var td = document.createElement('td');
        td.appendChild(pill(r.status, r.status === 'ACTIVE' ? 'warn' : 'muted'));
        tr.appendChild(td);
        return tr;
      });
      replaceChildren(blocks, blockRows.length ? blockRows : [emptyRow(6, 'No IP blocks recorded.')]);
    }

    // 5. flags
    var flags = document.getElementById('flags');
    if (flags) {
      var items = (data.flags || []).map(function (r) {
        var li  = document.createElement('li');
        li.className = 'feed__item';

        var dot = document.createElement('span');
        dot.className = 'dot dot--' + (r.severity === 'HIGH' ? 'bad' : 'warn');

        var box   = document.createElement('div');
        var title = document.createElement('div');
        title.className = 'feed__text';
        var strong = document.createElement('strong');
        strong.textContent = r.title;
        var target = document.createElement('span');
        target.className = 'mono';
        target.textContent = r.target;
        title.appendChild(strong);
        title.appendChild(document.createTextNode(' — '));
        title.appendChild(target);

        var meta = document.createElement('div');
        meta.className = 'feed__meta';
        meta.textContent = r.details + ' · ' + r.status + ' · ' + r.date;

        box.appendChild(title);
        box.appendChild(meta);
        li.appendChild(dot);
        li.appendChild(box);
        return li;
      });
      if (!items.length) {
        var none = document.createElement('li');
        none.className = 'muted';
        none.textContent = 'No flags yet. Run the monitoring script to scan the log.';
        items = [none];
      }
      replaceChildren(flags, items);
    }

    // 6. recent events
    var events = document.getElementById('events');
    if (events) {
      var evRows = (data.events || []).map(function (r) {
        var tr = document.createElement('tr');
        tr.appendChild(cell(r.time, 'nowrap'));
        var td = document.createElement('td');
        td.appendChild(pill(r.event, r.tone));
        tr.appendChild(td);
        tr.appendChild(cell(r.username, 'mono'));
        tr.appendChild(cell(r.ip, 'mono'));
        tr.appendChild(cell(r.browser));
        return tr;
      });
      replaceChildren(events, evRows.length ? evRows : [emptyRow(5, 'No events match.')]);
    }

    var updated = document.getElementById('dash-updated');
    if (updated && data.updated) { updated.textContent = data.updated; }
  }

  /* ---------- refresh every 60 seconds ---------- */

  function refresh() {
    fetch(root.getAttribute('data-url'), {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'Accept': 'application/json' }
    })
      .then(function (r) {
        var type = r.headers.get('content-type') || '';
        if (!r.ok || type.indexOf('application/json') === -1) { throw new Error('not allowed'); }
        return r.json();
      })
      .then(apply)
      .catch(function () { /* keep showing the old numbers (for example after the session ended) */ });
  }

  drawChart(JSON.parse(canvas.getAttribute('data-labels') || '[]'),
            JSON.parse(canvas.getAttribute('data-values') || '[]'));

  setInterval(refresh, 60000);
})();