<?php
/* =========================
   RHEL Service Panel (single-file)
   - Whitelist the services you want to expose
   - Requires sudoers for apache to run: systemctl + journalctl
   ========================= */
$SERVICES = [
  'httpd'     => ['display' => 'Apache HTTP (httpd)', 'desc' => 'Web server'],
  'snmpd'     => ['display' => 'SNMP Agent (snmpd)',  'desc' => 'Network monitoring agent'],
  'mariadb'   => ['display' => 'MariaDB (mysqld)',    'desc' => 'Database service'],
  'firewalld' => ['display' => 'FirewallD',           'desc' => 'Firewall manager'],
  'sshd'      => ['display' => 'OpenSSH (sshd)',      'desc' => 'Remote login service'],
];

function allowed_unit(string $u): bool {
  global $SERVICES; return isset($SERVICES[$u]);
}
function run_cmd(string $cmd): array {
  $out=[]; $code=0; exec($cmd." 2>&1",$out,$code);
  return ['code'=>$code,'out'=>implode("\n",$out)];
}
function svc_status(string $unit): array {
  $active = trim(shell_exec('systemctl is-active '.escapeshellarg($unit).' 2>&1') ?? '');
  $enabledOut = trim(shell_exec('systemctl is-enabled '.escapeshellarg($unit).' 2>&1') ?? '');
  $enabled = ($enabledOut === 'enabled');
  // map to your UI statuses
  $status = in_array($active, ['active','activating']) ? 'active'
           : (in_array($active,['failed']) ? 'failed' : 'inactive');
  return ['unit'=>$unit,'status'=>$status,'enabled'=>$enabled];
}
function incidents_24h(string $unit): int {
  $cmd = "sudo journalctl -u ".escapeshellarg($unit)." --since '24 hours ago' --no-pager | grep -Ei 'failed|error|panic|segfault|oom|critical|emerg' | wc -l";
  $r = run_cmd($cmd); return max(0, intval(trim($r['out'])));
}
function uptime_estimate(string $unit): int {
  // simple/robust heuristic for now:
  $st = svc_status($unit)['status'];
  return $st==='active' ? 99 : 0;
}
function json_resp($data, int $code=200){
  header('Content-Type: application/json');
  header('Cache-Control: no-store');
  http_response_code($code);
  echo json_encode($data);
  exit;
}

if (isset($_GET['api'])) {
  $api = $_GET['api'];
  $in = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];

  if ($api === 'list') {
    global $SERVICES;
    $rows = [];
    foreach ($SERVICES as $u=>$meta) {
      $st = svc_status($u);
      $rows[] = [
        'unit'=>$u,
        'display'=>$meta['display'],
        'desc'=>$meta['desc'],
        'status'=>$st['status'],
        'enabled'=>$st['enabled'],
        'uptime'=>uptime_estimate($u),
        'incidents'=>incidents_24h($u)
      ];
    }
    json_resp(['services'=>$rows]);
  }

  if ($api === 'toggle') {
    $unit = $in['unit'] ?? '';
    $enable = (bool)($in['enable'] ?? false);
    if (!allowed_unit($unit)) json_resp(['ok'=>false,'error'=>'unit_not_allowed'],403);
    $cmd = $enable
      ? "sudo systemctl enable --now ".escapeshellarg($unit)
      : "sudo systemctl disable --now ".escapeshellarg($unit);
    $r = run_cmd($cmd);
    $st = svc_status($unit);
    json_resp(['ok'=>$r['code']===0, 'exec'=>$r, 'status'=>$st]);
  }

  if ($api === 'action') {
    $unit = $in['unit'] ?? ''; $action = $in['action'] ?? '';
    if (!allowed_unit($unit)) json_resp(['ok'=>false,'error'=>'unit_not_allowed'],403);
    if (!in_array($action,['start','stop','restart'])) json_resp(['ok'=>false,'error'=>'bad_action'],400);
    $r = run_cmd("sudo systemctl $action ".escapeshellarg($unit));
    $st = svc_status($unit);
    json_resp(['ok'=>$r['code']===0, 'exec'=>$r, 'status'=>$st]);
  }

  if ($api === 'logs') {
    $unit = $in['unit'] ?? ''; $lines = max(10, min(500, intval($in['lines'] ?? 200)));
    if (!allowed_unit($unit)) json_resp(['ok'=>false,'error'=>'unit_not_allowed'],403);
    $r = run_cmd("sudo journalctl -u ".escapeshellarg($unit)." -n $lines --no-pager --output short-iso");
    json_resp(['ok'=>true,'title'=>$unit.' — Logs','body'=>$r['out']]);
  }

  if ($api === 'history') {
    $unit = $in['unit'] ?? '';
    if (!allowed_unit($unit)) json_resp(['ok'=>false,'error'=>'unit_not_allowed'],403);
    $r = run_cmd("systemctl status ".escapeshellarg($unit)." --no-pager");
    json_resp(['ok'=>true,'title'=>$unit.' — History','body'=>$r['out']]);
  }

  if ($api === 'analytics') {
    $unit = $in['unit'] ?? '';
    if (!allowed_unit($unit)) json_resp(['ok'=>false,'error'=>'unit_not_allowed'],403);
    json_resp(['ok'=>true,'uptime'=>uptime_estimate($unit),'incidents'=>incidents_24h($unit)]);
  }

  json_resp(['ok'=>false,'error'=>'unknown_api'],404);
}
?>
<!-- keep your original UI exactly; only JS changed to call the PHP api above -->
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Service Management</title>
<style>
  :root{
    --bg:#f7f8fc; --ink:#0e1325; --muted:#6b7280; --brand:#4c1d95; --brand-2:#6d28d9;
    --ok:#16a34a; --warn:#f59e0b; --bad:#ef4444; --rail:#e5e7eb; --card:#ffffff; --ring:#c7c9d3;
  }
  *{box-sizing:border-box}
  body{ margin:0;background:var(--bg);color:var(--ink);
    font:14px/1.4 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial;}
  .top{ display:flex;align-items:center;justify-content:space-between;
    padding:14px 18px;background:var(--brand);color:#fff; position:sticky;top:0;z-index:5;}
  .title{font-weight:700;letter-spacing:.2px}
  .actions{display:flex;gap:10px}
  .btn{ padding:8px 14px;border:1px solid #ffffff40;border-radius:10px;background:#ffffff22;
    color:#fff;backdrop-filter:saturate(120%);cursor:pointer;transition:.2s;}
  .btn:hover{background:#ffffff33}
  .wrap{max-width:1100px;margin:22px auto;padding:0 16px}
  .card{ background:var(--card);border:2px solid var(--brand);border-radius:14px;
    box-shadow:0 8px 24px rgba(76,29,149,.07);overflow:hidden;}
  .card-head{display:flex;align-items:center;gap:10px;padding:12px 16px;border-bottom:1px solid var(--rail)}
  .card-head h2{margin:0;font-size:16px}
  .table{width:100%;border-collapse:separate;border-spacing:0 10px;padding:12px}
  .thead, .row{ display:grid;grid-template-columns: 170px 200px 1fr 120px 90px 240px;
    gap:10px;align-items:center;padding:10px 12px;border-radius:12px;}
  .thead{color:#4b5563;font-weight:700;letter-spacing:.2px}
  .row{ background:#f9f7ff;border:1px solid #e6e2ff;cursor:pointer;position:relative;
    transition:box-shadow .2s ease, background .2s ease;}
  .row:hover{box-shadow:0 6px 18px rgba(109,40,217,.08)}
  .row.expanded{background:#fbfbff}
  .cell{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .desc{color:var(--muted)}
  .chev{color:#a78bfa;margin-right:6px}
  .details{ grid-column:1 / -1; display:none; background:#ffffff;border:1px solid #e6e2ff;border-radius:12px;
    padding:12px;margin-top:8px;}
  .row.expanded .details{display:flex;align-items:center;justify-content:flex-end;gap:12px}
  .btn-logs{ background:var(--brand-2);color:#fff;border:none;border-radius:10px;
    padding:10px 16px;font-weight:700;letter-spacing:.3px;cursor:pointer;}
  .btn-logs:hover{filter:brightness(1.05)}
  .dot{width:12px;height:12px;border-radius:50%;display:inline-block;margin-right:8px;vertical-align:-2px;border:1px solid #00000020}
  .s-active{background:var(--ok)} .s-inactive{background:#9ca3af} .s-failed{background:var(--bad)}
  .toggle{ --w:48px;--h:26px;--knob:20px; width:var(--w);height:var(--h);border-radius:var(--h);
    background:#d1d5db;position:relative;cursor:pointer;transition:.2s;border:1px solid #0000001a;}
  .toggle:after{content:"";position:absolute;top:50%;left:3px;transform:translateY(-50%);
    width:var(--knob);height:var(--knob);border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.1);transition:.2s;}
  .toggle.on{background:#a78bfa}
  .toggle.on:after{left:calc(100% - var(--knob) - 3px)}
  .icon-btn{ width:34px;height:34px;border-radius:10px;border:1px solid var(--ring);
    background:#fff;display:inline-grid;place-items:center;margin-right:6px;cursor:pointer;transition:.15s;}
  .icon-btn:hover{transform:translateY(-1px);border-color:var(--brand-2);box-shadow:0 4px 10px rgba(109,40,217,.15)}
  .icon{width:18px;height:18px;display:block}
  .edit{color:#2563eb} .history{color:#7c3aed} .trash{color:#ef4444} .donut-ico{color:#0ea5e9}
  .round{ width:42px;height:42px;border-radius:50%;border:none;display:inline-grid;place-items:center;
    cursor:pointer;box-shadow:0 6px 14px rgba(0,0,0,.08);transition:.15s;font-size:16px;}
  .round:hover{transform:translateY(-1px)}
  .r-play{background:#e8fff0;color:#16a34a} .r-stop{background:#ffecec;color:#ef4444} .r-reload{background:#efe9ff;color:#6d28d9}
  .donut{ --p:72; width:28px;height:28px;border-radius:50%;
    background:conic-gradient(var(--ok) calc(var(--p)*1%), #e5e7eb 0);
    mask: radial-gradient(circle at 50% 50%, transparent 55%, #000 56%); display:inline-block;margin-left:4px;}
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
  // Backend API base (self)
  const API = window.location.pathname;

  // Data from backend
  let services = [];

  // Helpers
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
  async function api(name, payload={}){
    const r = await fetch(`${API}?api=${encodeURIComponent(name)}`, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    if(!r.ok){ throw new Error(`HTTP ${r.status}`); }
    return r.json();
  }

  // Render
  const rowsEl = document.getElementById('rows');
  function render(){
    rowsEl.innerHTML = services.map(s => `
      <div class="row" data-unit="${s.unit}">
        <div class="cell"><span class="chev">▸</span><strong>${s.unit.toUpperCase()}</strong></div>
        <div class="cell">${s.display}</div>
        <div class="cell desc">${s.desc}</div>
        <div class="cell status-cell">${statusDot(s.status)}</div>
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

  // Bind handlers
  function bindHandlers(){
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

    // Enable toggle (enable --now / disable --now)
    document.querySelectorAll('.toggle').forEach(tg=>{
      tg.setAttribute('data-nocollapse','1');
      tg.addEventListener('click', async e=>{
        const row = tg.closest('.row');
        const unit = row.dataset.unit;
        const willEnable = !tg.classList.contains('on');
        tg.classList.toggle('on'); // optimistic
        tg.setAttribute('aria-checked', tg.classList.contains('on'));
        try{
          const res = await api('toggle', {unit, enable: willEnable});
          updateRow(row, res.status, res.status.enabled ? 99 : 0);
        }catch(err){
          // revert on error
          tg.classList.toggle('on');
          tg.setAttribute('aria-checked', tg.classList.contains('on'));
          alert(`Toggle failed for ${unit}: ${err.message}`);
        }
      });
    });

    // Row action icons
    document.querySelectorAll('.row .icon-btn').forEach(btn=>{
      btn.setAttribute('data-nocollapse','1');
      btn.addEventListener('click', async e=>{
        const action = btn.dataset.action;
        const row = btn.closest('.row');
        const unit = row.dataset.unit;
        try{
          switch(action){
            case 'edit':   alert(`Edit service: ${unit}`); break;
            case 'history':
              const h = await api('history', {unit});
              document.getElementById('logsTitle').textContent = h.title;
              document.getElementById('logsBody').textContent = h.body || 'No history.';
              document.getElementById('logsModal').classList.add('open');
              break;
            case 'trash':  if(confirm(`Delete ${unit} from view?`)){ row.remove(); } break;
            case 'donut':
              const a = await api('analytics', {unit});
              openAnalytics(unit, a.uptime ?? 70, a.incidents ?? 0);
              break;
          }
        }catch(err){
          alert(`${action} failed for ${unit}: ${err.message}`);
        }
      });
    });

    // Details area buttons: start/stop/restart
    document.querySelectorAll('.round').forEach(b=>{
      b.setAttribute('data-nocollapse','1');
      b.addEventListener('click', async e=>{
        const unit = b.closest('.row').dataset.unit;
        const action = b.dataset.action === 'reload' ? 'restart' : b.dataset.action;
        try{
          const res = await api('action', {unit, action});
          const row = b.closest('.row');
          updateRow(row, res.status);
        }catch(err){
          alert(`${action} failed for ${unit}: ${err.message}`);
        }
      });
    });

    // Show Logs
    document.querySelectorAll('.btn-logs').forEach(btn=>{
      btn.setAttribute('data-nocollapse','1');
      btn.addEventListener('click', async e=>{
        const unit = btn.dataset.logunit;
        try{
          const r = await api('logs', {unit, lines: 200});
          document.getElementById('logsTitle').textContent = r.title;
          document.getElementById('logsBody').textContent = r.body || 'No logs available.';
          document.getElementById('logsModal').classList.add('open');
        }catch(err){
          alert(`Log fetch failed for ${unit}: ${err.message}`);
        }
      });
    });
  }

  function updateRow(row, statusObj){
    // status cell
    const st = statusObj.status || 'inactive';
    row.querySelector('.status-cell').innerHTML = statusDot(st);
    // toggle reflects "enabled"
    const tg = row.querySelector('.toggle');
    if (tg) {
      tg.classList.toggle('on', !!statusObj.enabled);
      tg.setAttribute('aria-checked', !!statusObj.enabled);
    }
  }

  // Modals
  function closeModal(id){ document.getElementById(id).classList.remove('open'); }
  document.getElementById('logsModal').addEventListener('click', e=>{ if(e.target.id==='logsModal') closeModal('logsModal'); });
  document.getElementById('chartModal').addEventListener('click', e=>{ if(e.target.id==='chartModal') closeModal('chartModal'); });

  function openAnalytics(unit, percent, incidents){
    document.getElementById('chartTitle').textContent = `${unit.toUpperCase()} — Analytics`;
    const donut = document.getElementById('bigDonut');
    donut.style.setProperty('--p', percent);
    document.getElementById('uptimeLabel').textContent = `${percent}%`;
    document.getElementById('incidents').textContent = incidents;
    document.getElementById('chartModal').classList.add('open');
  }

  // Top buttons (placeholder)
  document.getElementById('btnAdd').onclick = ()=>alert('Add new service (open form)');
  document.getElementById('btnManage').onclick = ()=>alert('Manage services (open settings)');

  // Initial load from backend
  (async ()=>{
    try{
      const data = await api('list');
      services = data.services || [];
      render();
    }catch(err){
      document.getElementById('rows').innerHTML = `<div class="cell" style="grid-column:1/-1;color:#ef4444">API error: ${err.message}</div>`;
    }
  })();
</script>
</body>
</html>
