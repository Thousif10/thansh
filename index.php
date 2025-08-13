<!-- save as: service-management.php -->
<?php
// No PHP logic here, just HTML/JS UI
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Service Management</title>
<style>
/* === your existing CSS unchanged === */
<?php include "service-management.css"; ?>
</style>
</head>
<body>
<header class="top">
  <div class="title">Service Management</div>
  <div class="actions">
    <button class="btn" id="btnAdd">Add</button>
    <button class="btn" id="btnManage">Manage</button>
  </div>
</header>

<main class="wrap">
  <section class="card">
    <div class="card-head">
      <h2>Service Details</h2><span class="small">Dashboard</span>
    </div>
    <div class="table">
      <div class="thead">
        <div>Service Name</div>
        <div>Display Service</div>
        <div>Description</div>
        <div>Status</div>
        <div>Enable</div>
        <div>Action</div>
      </div>
      <div id="rows"></div>
    </div>
  </section>
</main>

<!-- Logs modal -->
<div class="modal" id="logsModal" aria-hidden="true">
  <div class="modal-card">
    <div class="modal-head">
      <h3 id="logsTitle" style="margin:0">Logs</h3>
      <button class="close" aria-label="Close" onclick="closeModal('logsModal')">✕</button>
    </div>
    <pre id="logsBody">Loading…</pre>
  </div>
</div>

<!-- Analytics modal -->
<div class="modal" id="chartModal" aria-hidden="true">
  <div class="modal-card">
    <div class="modal-head">
      <h3 id="chartTitle" style="margin:0">Service Analytics</h3>
      <button class="close" aria-label="Close" onclick="closeModal('chartModal')">✕</button>
    </div>
    <div style="display:flex;gap:18px;align-items:center">
      <div id="bigDonut" class="donut" style="--p:70;width:90px;height:90px;"></div>
      <div>
        <div class="small">Uptime (last 24h)</div>
        <div id="uptimeLabel" style="font-size:28px;font-weight:700;margin-bottom:4px">70%</div>
        <div class="small">Incidents: <span id="incidents">2</span></div>
      </div>
    </div>
    <div style="margin-top:14px" class="small">Tip: uptime data is placeholder</div>
  </div>
</div>

<script>
let services = [];

async function loadServices(){
  const res = await fetch('service_api.php?fn=list');
  services = await res.json();
  render();
}

function statusDot(st){
  const cls = st==='active' ? 's-active' : (st==='failed' ? 's-failed' : 's-inactive');
  const label = st[0].toUpperCase()+st.slice(1);
  return `<span class="dot ${cls}" title="${label}"></span><span>${label}</span>`;
}

function toggleTemplate(on){ return `<div class="toggle ${on?'on':''}" role="switch" aria-checked="${on}" tabindex="0"></div>`; }

function iconButton(kind, title){
  const svg = {
    edit:'<svg viewBox="0 0 24 24" class="icon edit"><path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1.003 1.003 0 000-1.42l-2.34-2.34a1.003 1.003 0 00-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.82z"/></svg>',
    history:'<svg viewBox="0 0 24 24" class="icon history"><path fill="currentColor" d="M13 3a9 9 0 109 9h-2a7 7 0 11-7-7V3zm-1 5h2v5h-4v-2h2V8z"/></svg>',
    trash:'<svg viewBox="0 0 24 24" class="icon trash"><path fill="currentColor" d="M6 7h12l-1 14H7L6 7zm3-3h6l1 2H8l1-2z"/></svg>',
    donut:'<svg viewBox="0 0 24 24" class="icon donut-ico"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="3"/><path d="M12 12 L12 4 A8 8 0 0 1 20 12 Z" fill="currentColor"/></svg>'
  }[kind];
  return `<button class="icon-btn" title="${title}" data-action="${kind}">${svg}</button>`;
}

function roundBtn(kind, title){
  const cls = kind==='play' ? 'r-play' : kind==='stop' ? 'r-stop' : 'r-reload';
  const sym = kind==='play' ? '▶' : kind==='stop' ? '■' : '↻';
  return `<button class="round ${cls}" data-action="${kind}" title="${title}">${sym}</button>`;
}

function render(){
  const rowsEl = document.getElementById('rows');
  rowsEl.innerHTML = services.map(s => `
    <div class="row" data-unit="${s.unit}">
      <div class="cell"><span class="chev">▸</span><strong>${s.unit.toUpperCase()}</strong></div>
      <div class="cell">${s.display}</div>
      <div class="cell desc">${s.desc}</div>
      <div class="cell">${statusDot(s.status)}</div>
      <div class="cell">${toggleTemplate(s.enabled)}</div>
      <div class="cell toolbar" data-nocollapse="1">
        ${iconButton('edit','Edit service')}
        ${iconButton('history','History')}
        ${iconButton('trash','Delete')}
        ${iconButton('donut','Analytics')}
      </div>
      <div class="details" aria-hidden="true">
        <button class="btn-logs" data-nocollapse="1" data-logunit="${s.unit}">Show Logs</button>
        ${roundBtn('play','Start')}
        ${roundBtn('stop','Stop')}
        ${roundBtn('reload','Restart')}
      </div>
    </div>
  `).join('');
  bindHandlers();
}

function bindHandlers(){
  document.querySelectorAll('.row').forEach(row=>{
    row.addEventListener('click', e=>{
      if (e.target.closest('[data-nocollapse]') || e.target.closest('.toggle') || e.target.closest('.round')) return;
      row.classList.toggle('expanded');
      const chev = row.querySelector('.chev');
      chev.textContent = row.classList.contains('expanded') ? '▾' : '▸';
    });
  });

  document.querySelectorAll('.toggle').forEach(tg=>{
    tg.addEventListener('click', async ()=>{
      const unit = tg.closest('.row').dataset.unit;
      const enable = !tg.classList.contains('on');
      await fetch('service_api.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({fn: enable?'enable':'disable', unit})
      });
      loadServices();
    });
  });

  document.querySelectorAll('.round').forEach(b=>{
    b.addEventListener('click', async ()=>{
      const unit = b.closest('.row').dataset.unit;
      await fetch('service_api.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({fn: b.dataset.action, unit})
      });
      loadServices();
    });
  });

  document.querySelectorAll('.btn-logs').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const unit = btn.dataset.logunit;
      const res = await fetch(`service_api.php?fn=logs&unit=${unit}`);
      const data = await res.json();
      document.getElementById('logsTitle').textContent = `${unit} — Logs`;
      document.getElementById('logsBody').textContent = data.logs || 'No logs';
      document.getElementById('logsModal').classList.add('open');
    });
  });
}

function closeModal(id){ document.getElementById(id).classList.remove('open'); }
document.getElementById('logsModal').addEventListener('click', e=>{ if(e.target.id==='logsModal') closeModal('logsModal'); });
document.getElementById('chartModal').addEventListener('click', e=>{ if(e.target.id==='chartModal') closeModal('chartModal'); });

loadServices();
</script>
</body>
</html>
