<!-- save as: service-management.php -->
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Service Management</title>
<style>
  :root{
    --bg:#f7f8fc;
    --ink:#0e1325;
    --muted:#6b7280;
    --brand:#4c1d95;       /* deep purple */
    --brand-2:#6d28d9;     /* accent purple */
    --ok:#16a34a;
    --warn:#f59e0b;
    --bad:#ef4444;
    --rail:#e5e7eb;
    --card:#ffffff;
    --ring:#c7c9d3;
  }
  *{box-sizing:border-box}
  body{
    margin:0;background:var(--bg);color:var(--ink);
    font:14px/1.4 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial;
  }
  /* Top bar */
  .top{
    display:flex;align-items:center;justify-content:space-between;
    padding:14px 18px;background:var(--brand);color:#fff;
    position:sticky;top:0;z-index:5;
  }
  .title{font-weight:700;letter-spacing:.2px}
  .actions{display:flex;gap:10px}
  .btn{
    padding:8px 14px;border:1px solid #ffffff40;border-radius:10px;background:#ffffff22;
    color:#fff;backdrop-filter:saturate(120%);cursor:pointer;transition:.2s;
  }
  .btn:hover{background:#ffffff33}

  /* Card widget */
  .wrap{max-width:1100px;margin:22px auto;padding:0 16px}
  .card{
    background:var(--card);border:2px solid var(--brand);border-radius:14px;
    box-shadow:0 8px 24px rgba(76,29,149,.07);overflow:hidden;
  }
  .card-head{display:flex;align-items:center;gap:10px;padding:12px 16px;border-bottom:1px solid var(--rail)}
  .card-head h2{margin:0;font-size:16px}

  /* Table */
  .table{width:100%;border-collapse:separate;border-spacing:0 10px;padding:12px}
  .thead, .row{
    /* Removed the "Active" column: 6 columns total */
    display:grid;grid-template-columns: 170px 200px 1fr 120px 90px 240px;
    gap:10px;align-items:center;padding:10px 12px;border-radius:12px;
  }
  .thead{color:#4b5563;font-weight:700;letter-spacing:.2px}
  .row{
    background:#f9f7ff;border:1px solid #e6e2ff;cursor:pointer;position:relative;
    transition:box-shadow .2s ease, background .2s ease;
  }
  .row:hover{box-shadow:0 6px 18px rgba(109,40,217,.08)}
  .row.expanded{background:#fbfbff}
  .cell{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .desc{color:var(--muted)}
  .chev{color:#a78bfa;margin-right:6px}

  /* Downward expand panel */
  .details{
    grid-column:1 / -1;
    display:none;
    background:#ffffff;border:1px solid #e6e2ff;border-radius:12px;
    padding:12px;margin-top:8px;
  }
  .row.expanded .details{display:flex;align-items:center;justify-content:flex-end;gap:12px}

  .btn-logs{
    background:var(--brand-2);color:#fff;border:none;border-radius:10px;
    padding:10px 16px;font-weight:700;letter-spacing:.3px;cursor:pointer;
  }
  .btn-logs:hover{filter:brightness(1.05)}

  /* Status dot */
  .dot{width:12px;height:12px;border-radius:50%;display:inline-block;margin-right:8px;vertical-align:-2px;border:1px solid #00000020}
  .s-active{background:var(--ok)}
  .s-inactive{background:#9ca3af}
  .s-failed{background:var(--bad)}

  /* Toggle */
  .toggle{
    --w:48px;--h:26px;--knob:20px;
    width:var(--w);height:var(--h);border-radius:var(--h);
    background:#d1d5db;position:relative;cursor:pointer;transition:.2s;border:1px solid #0000001a;
  }
  .toggle:after{
    content:"";position:absolute;top:50%;left:3px;transform:translateY(-50%);
    width:var(--knob);height:var(--knob);border-radius:50%;
    background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.1);transition:.2s;
  }
  .toggle.on{background:#a78bfa}
  .toggle.on:after{left:calc(100% - var(--knob) - 3px)}

  /* Icon buttons (row actions) */
  .icon-btn{
    width:34px;height:34px;border-radius:10px;border:1px solid var(--ring);
    background:#fff;display:inline-grid;place-items:center;margin-right:6px;cursor:pointer;transition:.15s;
  }
  .icon-btn:hover{transform:translateY(-1px);border-color:var(--brand-2);box-shadow:0 4px 10px rgba(109,40,217,.15)}
  .icon{width:18px;height:18px;display:block}
  .edit{color:#2563eb} .history{color:#7c3aed} .trash{color:#ef4444} .donut-ico{color:#0ea5e9}

  /* Round action buttons inside details (start/stop/restart) */
  .round{
    width:42px;height:42px;border-radius:50%;border:none;display:inline-grid;place-items:center;
    cursor:pointer;box-shadow:0 6px 14px rgba(0,0,0,.08);transition:.15s;font-size:16px;
  }
  .round:hover{transform:translateY(-1px)}
  .r-play{background:#e8fff0;color:#16a34a}
  .r-stop{background:#ffecec;color:#ef4444}
  .r-reload{background:#efe9ff;color:#6d28d9}

  /* Donut mini (quick glance) */
  .donut{
    --p:72; /* percent */
    width:28px;height:28px;border-radius:50%;
    background:conic-gradient(var(--ok) calc(var(--p)*1%), #e5e7eb 0);
    mask: radial-gradient(circle at 50% 50%, transparent 55%, #000 56%);
    display:inline-block;margin-left:4px;
  }

  /* Modals (logs & analytics) */
  .modal{position:fixed;inset:0;display:none;place-items:center;background:rgba(14,19,37,.45);z-index:50}
  .modal.open{display:grid}
  .modal-card{background:#fff;border-radius:14px;min-width:380px;max-width:720px;padding:18px;border:1px solid var(--rail)}
  .modal-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
  .close{cursor:pointer;border:none;background:transparent;font-size:18px}
  .small{color:var(--muted);font-size:12px}
  .toolbar .icon-btn{margin-right:8px}
  pre{background:#0b1220;color:#e5e7eb;padding:12px;border-radius:10px;max-height:360px;overflow:auto}
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
    <div style="margin-top:14px" class="small">Tip: wire this to your status_history to compute real uptime.</div>
  </div>
</div>

<script>
  // Demo data (replace with API results)
  const services = [
    { unit:"httpd",    display:"Apache HTTP (httpd)", desc:"Web server",              status:"failed",   enabled:true,  uptime:63, incidents:3 },
    { unit:"snmpd",    display:"SNMP Agent (snmpd)",  desc:"Network monitoring agent",status:"inactive", enabled:false, uptime:82, incidents:1 },
    { unit:"mariadb",  display:"MariaDB (mysqld)",    desc:"Database service",        status:"active",   enabled:true,  uptime:99, incidents:0 },
    { unit:"firewalld",display:"FirewallD",           desc:"Firewall manager",        status:"active",   enabled:true,  uptime:97, incidents:1 },
    { unit:"sshd",     display:"OpenSSH (sshd)",      desc:"Remote login service",    status:"active",   enabled:true,  uptime:100,incidents:0 },
  ];

  const sampleLogs = {
    httpd: `[ERROR] AH00072: make_sock: could not bind to address 0.0.0.0:80
AH00451: no listening sockets available, shutting down
systemd[1]: httpd.service: Failed with result 'exit-code'`,
    snmpd: `snmpd[1234]: Connection from 127.0.0.1
snmpd[1234]: Warning: community 'public' is world-readable (dev)`,
    mariadb: `InnoDB: Starting crash recovery...
mariadbd: ready for connections.`,
    firewalld: `firewalld: active (running)
zone=public: services=http,https,ssh`,
    sshd: `sshd[824]: Server listening on 0.0.0.0 port 22.
sshd[824]: Server listening on :: port 22.`
  };

  const rowsEl = document.getElementById('rows');

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
          ${iconButton('donut','Analytics (doughnut)')}
          <span class="donut" style="--p:${s.uptime}" title="Uptime ${s.uptime}%"></span>
        </div>

        <!-- DOWNWARD EXPANDED AREA -->
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
    // Expand/collapse rows (ignore clicks on controls)
    document.querySelectorAll('.row').forEach(row=>{
      row.addEventListener('click', e=>{
        if (e.target.closest('[data-nocollapse]') ||
            e.target.closest('.icon-btn') ||
            e.target.closest('.toggle') ||
            e.target.closest('.round') ||
            e.target.closest('.btn-logs')) return;

        row.classList.toggle('expanded');
        const chev = row.querySelector('.chev');
        if(chev) chev.textContent = row.classList.contains('expanded') ? '▾' : '▸';
        row.querySelector('.details')?.setAttribute('aria-hidden', String(!row.classList.contains('expanded')));
      });
    });

    // Enable toggle
    document.querySelectorAll('.toggle').forEach(tg=>{
      tg.setAttribute('data-nocollapse','1');
      tg.addEventListener('click', e=>{
        tg.classList.toggle('on');
        tg.setAttribute('aria-checked', tg.classList.contains('on'));
        const unit = tg.closest('.row').dataset.unit;
        console.log('Enable toggled', unit, tg.classList.contains('on'));
        // TODO: POST to backend: {fn:'action', action:'enable/disable', unit}
      });
    });

    // Row action icons (edit/history/delete/donut analytics)
    document.querySelectorAll('.row .icon-btn').forEach(btn=>{
      btn.setAttribute('data-nocollapse','1');
      btn.addEventListener('click', e=>{
        const action = btn.dataset.action;
        const row = btn.closest('.row');
        const unit = row.dataset.unit;
        switch(action){
          case 'edit':   alert(`Edit service: ${unit}`); break;
          case 'history':alert(`Show history for: ${unit}`); break;
          case 'trash':  if(confirm(`Delete ${unit}?`)){ row.remove(); } break;
          case 'donut':
            const uptime = Number(row.querySelector('.donut').style.getPropertyValue('--p'))||70;
            openAnalytics(unit, uptime, services.find(s=>s.unit===unit)?.incidents ?? 0);
            break;
        }
      });
    });

    // Details area buttons: start/stop/restart
    document.querySelectorAll('.round').forEach(b=>{
      b.setAttribute('data-nocollapse','1');
      b.addEventListener('click', e=>{
        const unit = b.closest('.row').dataset.unit;
        const action = b.dataset.action; // play/stop/reload
        console.log(`${action} → ${unit}`);
        // TODO: call backend: {fn:'action', unit, action:(start|stop|restart)}
      });
    });

    // Show Logs
    document.querySelectorAll('.btn-logs').forEach(btn=>{
      btn.setAttribute('data-nocollapse','1');
      btn.addEventListener('click', e=>{
        const unit = btn.dataset.logunit;
        openLogs(unit);
      });
    });
  }

  // Modals
  function closeModal(id){ document.getElementById(id).classList.remove('open'); }
  document.getElementById('logsModal').addEventListener('click', e=>{ if(e.target.id==='logsModal') closeModal('logsModal'); });
  document.getElementById('chartModal').addEventListener('click', e=>{ if(e.target.id==='chartModal') closeModal('chartModal'); });

  function openLogs(unit){
    document.getElementById('logsTitle').textContent = `${unit.toUpperCase()} — Logs (demo)`;
    document.getElementById('logsBody').textContent = sampleLogs[unit] || 'No logs available.';
    document.getElementById('logsModal').classList.add('open');
  }

  function openAnalytics(unit, percent, incidents){
    document.getElementById('chartTitle').textContent = `${unit.toUpperCase()} — Analytics`;
    const donut = document.getElementById('bigDonut');
    donut.style.setProperty('--p', percent);
    document.getElementById('uptimeLabel').textContent = `${percent}%`;
    document.getElementById('incidents').textContent = incidents;
    document.getElementById('chartModal').classList.add('open');
  }

  // Top buttons
  document.getElementById('btnAdd').onclick = ()=>alert('Add new service (open form)');
  document.getElementById('btnManage').onclick = ()=>alert('Manage services (open settings)');

  render();
</script>
</body>
</html>

