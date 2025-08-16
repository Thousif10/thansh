<?php

$DEFAULTS = [
  'httpd'     => ['name'=>'HTTPD','display'=>'Apache HTTP (httpd)','desc'=>'Web server'],
  'snmpd'     => ['name'=>'SNMPD','display'=>'SNMP Agent (snmpd)','desc'=>'Network monitoring agent'],
  'mariadb'   => ['name'=>'MARIADB','display'=>'MariaDB (mysqld)','desc'=>'Database service'],
  'firewalld' => ['name'=>'FIREWALLD','display'=>'FirewallD','desc'=>'Firewall manager'],
  'sshd'      => ['name'=>'SSHD','display'=>'OpenSSH (sshd)','desc'=>'Remote login service'],
];

// TIP: change to a writable dir like /var/www/data/services.json on SELinux hosts.
$DATA_FILE = __DIR__ . '/services.json';

//print_r("hi");

function safe_unit($u){ return (bool)preg_match('/^[A-Za-z0-9_.@-]{1,64}$/', $u); }
function unit_arg($unit){ return escapeshellarg($unit); }
function sh_trim($cmd){ return trim(@shell_exec($cmd)); }

function load_store($DATA_FILE, $DEFAULTS){
  if (!file_exists($DATA_FILE)) {
    @file_put_contents($DATA_FILE, json_encode($DEFAULTS, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX);
    return $DEFAULTS;
  }
  $j = json_decode(@file_get_contents($DATA_FILE), true);
  if (!is_array($j)) $j = [];
  foreach ($DEFAULTS as $u=>$meta){
    if (!isset($j[$u])) $j[$u] = $meta;
  }
  return $j;
}
function save_store($DATA_FILE, $arr){
  @file_put_contents($DATA_FILE, json_encode($arr, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX);
}

if (isset($_GET['api'])) {
  header('Content-Type: application/json');
  $op   = $_GET['op']   ?? 'list';
  $unit = $_GET['unit'] ?? null;

  $store = load_store($DATA_FILE, $DEFAULTS);

  // IMPORTANT: don't require ?unit=... for meta_set (it comes in POST JSON)
  $needs_unit = in_array($op, ['status','do','enable','meta_get','delete'], true);
  if ($needs_unit && (!$unit || !isset($store[$unit]))) {
    http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Unknown or missing unit']); exit;
  }

  switch ($op) {
    case 'list': {
      $out = [];
      foreach ($store as $u=>$meta) {
        $status_raw = sh_trim("systemctl is-active ".unit_arg($u)." 2>/dev/null");
        $status = ($status_raw === 'active') ? 'active' : 'inactive';
        $enabled = sh_trim("systemctl is-enabled ".unit_arg($u)." 2>/dev/null") ?: 'disabled';
        $out[] = [
          'unit'=>$u,
          'name'=>$meta['name'] ?? strtoupper($u),
          'display'=>$meta['display'] ?? $u,
          'desc'=>$meta['desc'] ?? '',
          'status'=>$status,
          'enabled'=>($enabled==='enabled'),
          'uptime'=>95,
          'incidents'=>0
        ];
      }
      echo json_encode(['ok'=>true,'services'=>$out]); exit;
    }

    case 'status': {
      $lines = max(0, min(2000, intval($_GET['lines'] ?? 50)));
      $tail  = $lines>0? "-n {$lines}" : "";
      $txt   = @shell_exec("systemctl status ".unit_arg($unit)." --no-pager -l {$tail} 2>&1");
      echo json_encode(['ok'=>true,'unit'=>$unit,'status_text'=>$txt ?: '']); exit;
    }

    case 'do': {
      $cmd = $_GET['cmd'] ?? '';
      if (!in_array($cmd, ['start','stop','restart'], true)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid cmd']); exit; }
      $out  = @shell_exec("sudo systemctl {$cmd} ".unit_arg($unit)." 2>&1");
      $stat = sh_trim("systemctl is-active ".unit_arg($unit)." 2>/dev/null");
      echo json_encode(['ok'=>true,'unit'=>$unit,'result'=>$out,'status'=>$stat]); exit;
    }

    case 'enable': {
      $state = ($_GET['state'] ?? '1') === '1';
      $cmd   = $state ? 'enable' : 'disable';
      $out   = @shell_exec("sudo systemctl {$cmd} ".unit_arg($unit)." 2>&1");
      $en    = sh_trim("systemctl is-enabled ".unit_arg($unit)." 2>/dev/null");
      echo json_encode(['ok'=>true,'unit'=>$unit,'enabled'=>($en==='enabled'),'result'=>$out]); exit;
    }

    case 'meta_get': {
      echo json_encode(['ok'=>true,'unit'=>$unit,'meta'=>$store[$unit]]); exit;
    }

    case 'meta_set': {
      $raw = file_get_contents('php://input'); $j = json_decode($raw, true);
      if (!$j || !isset($j['unit']) || !isset($store[$j['unit']])) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid payload']); exit; }
      $u = $j['unit'];
      $store[$u]['name']    = substr(trim($j['name']    ?? ($store[$u]['name'] ?? strtoupper($u))), 0, 64);
      $store[$u]['display'] = substr(trim($j['display'] ?? ($store[$u]['display'] ?? $u)), 0, 120);
      $store[$u]['desc']    = substr(trim($j['desc']    ?? ($store[$u]['desc'] ?? '')), 0, 512);
      save_store($DATA_FILE, $store);
      echo json_encode(['ok'=>true,'unit'=>$u,'meta'=>$store[$u]]); exit;
    }

    case 'add': {
      $raw = file_get_contents('php://input'); $j = json_decode($raw, true);
      $u = trim($j['unit'] ?? '');
      if (!$u || !safe_unit($u)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid unit id']); exit; }
      if (isset($store[$u]))    { http_response_code(409); echo json_encode(['ok'=>false,'error'=>'Unit already exists']); exit; }
      $store[$u] = [
        'name'    => substr(trim($j['name']    ?? strtoupper($u)), 0, 64),
        'display' => substr(trim($j['display'] ?? $u), 0, 120),
        'desc'    => substr(trim($j['desc']    ?? ''), 0, 512),
      ];
      save_store($DATA_FILE, $store);
      echo json_encode(['ok'=>true,'unit'=>$u,'meta'=>$store[$u]]); exit;
    }

    case 'delete': {
      unset($store[$unit]); save_store($DATA_FILE, $store);
      echo json_encode(['ok'=>true,'unit'=>$unit]); exit;
    }

    default: echo json_encode(['ok'=>false,'error'=>'Unknown op']); exit;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Service Management</title>
<style>
  :root{ --bg:#f7f8fc; --ink:#0e1325; --muted:#6b7280; --brand:#4c1d95; --brand-2:#6d28d9; --ok:#16a34a; --bad:#ef4444; --rail:#e5e7eb; --card:#ffffff; --ring:#c7c9d3; }
  *{box-sizing:border-box}
  body{ margin:0;background:var(--bg);color:var(--ink);font:14px/1.4 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial;}
  .top{ display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:var(--brand);color:#fff;position:sticky;top:0;z-index:5;}
  .title{font-weight:700;letter-spacing:.2px}
  .actions{display:flex;gap:10px}
  .btn{ padding:8px 14px;border:1px solid #ffffff40;border-radius:10px;background:#ffffff22;color:#fff;cursor:pointer;transition:.2s;}
  .btn:hover{background:#ffffff33}
  .wrap{max-width:1100px;margin:22px auto;padding:0 16px}
  .card{ background:var(--card);border:2px solid var(--brand);border-radius:14px;box-shadow:0 8px 24px rgba(76,29,149,.07);overflow:hidden;}
  .card-head{display:flex;align-items:center;gap:10px;padding:12px 16px;border-bottom:1px solid var(--rail)}
  .card-head h2{margin:0;font-size:16px}
  .table{width:100%;border-collapse:separate;border-spacing:0 10px;padding:12px}
  .thead, .row{ display:grid;grid-template-columns: 170px 200px 1fr 120px 90px 240px;gap:10px;align-items:center;padding:10px 12px;border-radius:12px;}
  .thead{color:#4b5563;font-weight:700;letter-spacing:.2px}
  .row{ background:#f9f7ff;border:1px solid #e6e2ff;cursor:pointer;position:relative;transition:box-shadow .2s ease, background .2s ease;}
  .row:hover{box-shadow:0 6px 18px rgba(109,40,217,.08)}
  .row.expanded{background:#fbfbff}
  .cell{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .desc{color:var(--muted)}
  .chev{color:#a78bfa;margin-right:6px}
  .details{ grid-column:1 / -1; display:none; background:#ffffff;border:1px solid #e6e2ff;border-radius:12px; padding:12px;margin-top:8px;}
  .row.expanded .details{display:flex;align-items:center;justify-content:flex-end;gap:10px;flex-wrap:wrap}
  .btn-plain{ border:1px solid var(--ring); background:#fff; color:#0e1325; border-radius:10px; padding:8px 12px; cursor:pointer; transition:.15s; font-weight:600;}
  .btn-plain:hover{transform:translateY(-1px); border-color:var(--brand-2); box-shadow:0 4px 10px rgba(109,40,217,.12)}
  .btn-status{ background:var(--brand-2); color:#fff; border-color:transparent; }
  .dot{width:12px;height:12px;border-radius:50%;display:inline-block;margin-right:8px;vertical-align:-2px;border:1px solid #00000020}
  .s-active{background:var(--ok)} .s-inactive{background:var(--bad)} .s-failed{background:var(--bad)}
  .toggle{ --w:48px;--h:26px;--knob:20px;width:var(--w);height:var(--h);border-radius:var(--h); background:#d1d5db;position:relative;cursor:pointer;transition:.2s;border:1px solid #0000001a;}
  .toggle:after{ content:"";position:absolute;top:50%;left:3px;transform:translateY(-50%); width:var(--knob);height:var(--knob);border-radius:50%; background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.1);transition:.2s;}
  .toggle.on{background:#a78bfa} .toggle.on:after{left:calc(100% - var(--knob) - 3px)}
  .icon-btn{ width:34px;height:34px;border-radius:10px;border:1px solid var(--ring); background:#fff;display:inline-grid;place-items:center;margin-right:6px;cursor:pointer;transition:.15s;}
  .icon-btn:hover{transform:translateY(-1px);border-color:var(--brand-2);box-shadow:0 4px 10px rgba(109,40,217,.15)}
  .icon{width:18px;height:18px;display:block}
  .edit{color:#2563eb} .history{color:#7c3aed} .trash{color:#ef4444} .donut-ico{color:#0ea5e9}
  .donut{ --p:72;width:28px;height:28px;border-radius:50%; background:conic-gradient(var(--ok) calc(var(--p)*1%), #e5e7eb 0); mask: radial-gradient(circle at 50% 50%, transparent 55%, #000 56%); display:inline-block;margin-left:4px;}
  .modal{position:fixed;inset:0;display:none;place-items:center;background:rgba(14,19,37,.45);z-index:50}
  .modal.open{display:grid}
  .modal-card{background:#fff;border-radius:14px;min-width:380px;max-width:720px;padding:18px;border:1px solid var(--rail)}
  .modal-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
  .close{cursor:pointer;border:none;background:transparent;font-size:18px}
  .small{color:#6b7280;font-size:12px}
  pre{background:#0b1220;color:#e5e7eb;padding:12px;border-radius:10px;max-height:360px;overflow:auto}
  .form-row{display:grid;gap:8px;margin-bottom:10px}
  .form-row label{font-size:12px;color:#6b7280}
  .form-row input, .form-row textarea, .form-row select{ width:100%;padding:10px;border:1px solid var(--ring);border-radius:10px;background:#fff;outline:none;}
  .form-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:8px}
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

<!-- STATUS modal -->
<div class="modal" id="statusModal" aria-hidden="true">
  <div class="modal-card">
    <div class="modal-head">
      <h3 id="statusTitle" style="margin:0">Service Status</h3>
      <button class="close" aria-label="Close" onclick="closeModal('statusModal')">✕</button>
    </div>
    <pre id="statusBody">Loading…</pre>
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

<!-- EDIT (Save only) -->
<div class="modal" id="editModal" aria-hidden="true">
  <div class="modal-card" style="max-width:760px">
    <div class="modal-head">
      <h3 style="margin:0">Edit Service</h3>
      <button class="close" aria-label="Close" onclick="closeModal('editModal')">✕</button>
    </div>
    <div class="form-row">
      <label for="editUnit">Choose service</label>
      <select id="editUnit"></select>
    </div>
    <div class="form-row">
      <label for="editName">Service Name</label>
      <input id="editName" placeholder="e.g., WEB SERVER"/>
    </div>
    <div class="form-row">
      <label for="editDisplay">Display Service</label>
      <input id="editDisplay" placeholder="e.g., Apache HTTP (httpd)"/>
    </div>
    <div class="form-row">
      <label for="editDesc">Description</label>
      <textarea id="editDesc" rows="3" placeholder="Short description"></textarea>
    </div>
    <div class="form-actions">
      <button class="btn" id="saveMeta" type="button">Save</button>
    </div>
  </div>
</div>

<!-- ADD (button text is Save) -->
<div class="modal" id="addModal" aria-hidden="true">
  <div class="modal-card" style="max-width:760px">
    <div class="modal-head">
      <h3 style="margin:0">Add Service</h3>
      <button class="close" aria-label="Close" onclick="closeModal('addModal')">✕</button>
    </div>
    <div class="form-row">
      <label for="addUnit">Unit (systemd id)</label>
      <input id="addUnit" placeholder="e.g., nginx, redis, myapp.service"/>
      <span class="small">Allowed: letters, numbers, _ . @ -</span>
    </div>
    <div class="form-row">
      <label for="addName">Service Name</label>
      <input id="addName" placeholder="e.g., NGINX"/>
    </div>
    <div class="form-row">
      <label for="addDisplay">Display Service</label>
      <input id="addDisplay" placeholder="e.g., NGINX (nginx)"/>
    </div>
    <div class="form-row">
      <label for="addDesc">Description</label>
      <textarea id="addDesc" rows="3" placeholder="Short description"></textarea>
    </div>
    <div class="form-actions">
      <button class="btn" id="addSave" type="button">Save</button>
    </div>
  </div>
</div>

<script>
  let services = [];
  let currentEditUnit = null;

  async function loadServices(){
    try{
      const r = await fetch('?api=1&op=list');
      const j = await r.json();
      services = j.services || [];
      render();
    }catch(e){ console.error(e); }
  }

  const rowsEl = document.getElementById('rows');

  // Escape HTML before inserting via innerHTML (XSS-safe)
  const esc = s => String(s ?? '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;')
    .replace(/'/g,'&#39;');

  function statusDot(st){
    const cls = st==='active' ? 's-active' : 's-inactive';
    const label = st ? st[0].toUpperCase()+st.slice(1) : 'Unknown';
    return `<span class="dot ${cls}" title="${esc(label)}"></span><span>${esc(label)}</span>`;
  }
  function toggleTemplate(on){ return `<div class="toggle ${on?'on':''}" role="switch" aria-checked="${on}" tabindex="0"></div>`; }

  function iconButton(kind, title){
    const svg = {
      edit:'<svg viewBox="0 0 24 24" class="icon edit"><path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1.003 1.003 0 000-1.42l-2.34-2.34a1.003 1.003 0 00-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.82z"/></svg>',
      history:'<svg viewBox="0 0 24 24" class="icon history"><path fill="currentColor" d="M13 3a9 9 0 109 9h-2a7 7 0 11-7-7V3zm-1 5h2v5h-4v-2h2V8z"/></svg>',
      trash:'<svg viewBox="0 0 24 24" class="icon trash"><path fill="currentColor" d="M6 7h12l-1 14H7L6 7zm3-3h6l1 2H8l1-2z"/></svg>',
      donut:'<svg viewBox="0 0 24 24" class="icon donut-ico"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="3"/><path d="M12 12 L12 4 A8 8 0 0 1 20 12 Z" fill="currentColor"/></svg>'
    }[kind];
    return `<button class="icon-btn" title="${esc(title)}" data-action="${esc(kind)}">${svg}</button>`;
  }

  function render(){
    rowsEl.innerHTML = services.map(s => `
      <div class="row" data-unit="${esc(s.unit)}">
        <div class="cell name-cell"><span class="chev">▸</span><strong>${esc(s.name || s.unit?.toUpperCase())}</strong></div>
        <div class="cell display-cell">${esc(s.display)}</div>
        <div class="cell desc desc-cell">${esc(s.desc)}</div>
        <div class="cell">${statusDot(s.status)}</div>
        <div class="cell">${toggleTemplate(!!s.enabled)}</div>
        <div class="cell toolbar" data-nocollapse="1">
          ${iconButton('edit','Edit service')}
          ${iconButton('history','History')}
          ${iconButton('trash','Delete')}
          ${iconButton('donut','Analytics (doughnut)')}
          <span class="donut" style="--p:${Number(s.uptime)||0}" title="Uptime ${Number(s.uptime)||0}%"></span>
        </div>

        <!-- DOWNWARD EXPANDED AREA -->
        <div class="details" aria-hidden="true">
          <button class="btn-plain btn-status" data-nocollapse="1" data-unit="${esc(s.unit)}">Show Status</button>
          <button class="btn-plain" data-nocollapse="1" data-action="start">Start</button>
          <button class="btn-plain" data-nocollapse="1" data-action="stop">Stop</button>
          <button class="btn-plain" data-nocollapse="1" data-action="restart">Restart</button>
        </div>
      </div>
    `).join('');
    bindHandlers();
  }

  function collapseAllExcept(exceptRow){
    document.querySelectorAll('.row.expanded').forEach(r=>{
      if (r !== exceptRow){
        r.classList.remove('expanded');
        r.querySelector('.chev').textContent = '▸';
        r.querySelector('.details')?.setAttribute('aria-hidden','true');
      }
    });
  }

  function bindHandlers(){
    // Expand/collapse rows
    document.querySelectorAll('.row').forEach(row=>{
      row.addEventListener('click', e=>{
        if (e.target.closest('[data-nocollapse]') ||
            e.target.closest('.icon-btn') ||
            e.target.closest('.toggle') ||
            e.target.closest('.btn-plain')) return;

        const isExpanding = !row.classList.contains('expanded');
        if (isExpanding) collapseAllExcept(row);
        row.classList.toggle('expanded');
        const chev = row.querySelector('.chev');
        if(chev) chev.textContent = row.classList.contains('expanded') ? '▾' : '▸';
        row.querySelector('.details')?.setAttribute('aria-hidden', String(!row.classList.contains('expanded')));
      });
    });

    // Enable toggle -> API
    document.querySelectorAll('.toggle').forEach(tg=>{
      tg.setAttribute('data-nocollapse','1');
      tg.addEventListener('click', async e=>{
        tg.classList.toggle('on');
        const on = tg.classList.contains('on');
        tg.setAttribute('aria-checked', on);
        const unit = tg.closest('.row').dataset.unit;
        try{
          const res = await fetch(`?api=1&op=enable&unit=${encodeURIComponent(unit)}&state=${on?1:0}`);
          const j = await res.json();
          if (!res.ok || j.error) alert(j.error || 'Enable/Disable failed');
        }catch(err){ console.error(err); }
      });
    });

    // Row action icons (includes DELETE)
    document.querySelectorAll('.row .icon-btn').forEach(btn=>{
      btn.setAttribute('data-nocollapse','1');
      btn.addEventListener('click', async e=>{
        const action = btn.dataset.action;
        const row = btn.closest('.row');
        const unit = row.dataset.unit;
        switch(action){
          case 'edit':
            openEdit(unit);
            break;
          case 'history':
            alert(`History for: ${unit} (wire to your audit log)`);
            break;
          case 'trash': {
            if (!confirm(`Delete ${unit}? This removes it from your list.`)) return;

            // Optimistic UI: remove row immediately and update local state
            row.remove();
            services = services.filter(x => x.unit !== unit);

            try {
              const res = await fetch(`?api=1&op=delete&unit=${encodeURIComponent(unit)}`);
              const j = await res.json();
              if (!res.ok || !j.ok) {
                alert(j.error || 'Delete failed');
                await loadServices(); // resync from server on error
              }
            } catch (err) {
              console.error(err);
              await loadServices(); // resync on network error
            }
            break;
          }
          case 'donut':
            const s = services.find(x=>x.unit===unit);
            const uptime = s?.uptime ?? 70;
            openAnalytics(unit, uptime, s?.incidents ?? 0);
            break;
        }
      });
    });

    // Start/Stop/Restart
    document.querySelectorAll('.details .btn-plain[data-action]').forEach(b=>{
      b.addEventListener('click', async e=>{
        const unit = b.closest('.row').dataset.unit;
        const cmd  = b.dataset.action;
        try{
          const res = await fetch(`?api=1&op=do&unit=${encodeURIComponent(unit)}&cmd=${cmd}`);
          const j = await res.json();
          if (!res.ok || j.error) alert(j.error || (j.result || 'Operation failed'));
          await loadServices();
        }catch(err){ console.error(err); }
      });
    });

    // Show Status
    document.querySelectorAll('.btn-status').forEach(btn=>{
      btn.addEventListener('click', async e=>{
        const unit = btn.dataset.unit || btn.closest('.row').dataset.unit;
        await openStatus(unit);
      });
    });
  }

  // Modals helpers
  function closeModal(id){ document.getElementById(id).classList.remove('open'); }
  ['statusModal','chartModal','editModal','addModal'].forEach(mid=>{
    document.getElementById(mid).addEventListener('click', e=>{ if(e.target.id===mid) closeModal(mid); });
  });

  async function openStatus(unit){
    document.getElementById('statusTitle').textContent = `${unit.toUpperCase()} — Status`;
    document.getElementById('statusBody').textContent = 'Loading…';
    try{
      const r = await fetch(`?api=1&op=status&unit=${encodeURIComponent(unit)}&lines=50`);
      const j = await r.json();
      document.getElementById('statusBody').textContent = j.status_text || 'No status output.';
    }catch(err){
      document.getElementById('statusBody').textContent = 'Failed to load status.';
    }
    document.getElementById('statusModal').classList.add('open');
  }

  function openAnalytics(unit, percent, incidents){
    document.getElementById('chartTitle').textContent = `${unit.toUpperCase()} — Analytics`;
    const donut = document.getElementById('bigDonut');
    donut.style.setProperty('--p', percent);
    document.getElementById('uptimeLabel').textContent = `${percent}%`;
    document.getElementById('incidents').textContent = incidents;
    document.getElementById('chartModal').classList.add('open');
  }

  // -------- Edit modal (Save) --------
  async function openEdit(unit){
    const sel = document.getElementById('editUnit');
    sel.innerHTML = services.map(s=>`<option value="${esc(s.unit)}">${esc(s.unit)} — ${esc(s.display)}</option>`).join('');
    sel.value = unit || (services[0]?.unit || '');
    currentEditUnit = sel.value;

    await fillEditForm(currentEditUnit);
    document.getElementById('editModal').classList.add('open');

    sel.onchange = async ()=>{
      currentEditUnit = sel.value;
      await fillEditForm(currentEditUnit);
    };
  }

  async function fillEditForm(unit){
    try{
      const r = await fetch(`?api=1&op=meta_get&unit=${encodeURIComponent(unit)}`);
      const j = await r.json();
      const m = j.meta || {};
      document.getElementById('editName').value    = m.name    || unit.toUpperCase();
      document.getElementById('editDisplay').value = m.display || '';
      document.getElementById('editDesc').value    = m.desc    || '';
    }catch(e){ console.error(e); }
  }

  document.getElementById('saveMeta').onclick = async ()=>{
    if(!currentEditUnit) return;
    const payload = {
      unit: currentEditUnit,
      name: document.getElementById('editName').value.trim(),
      display: document.getElementById('editDisplay').value.trim(),
      desc: document.getElementById('editDesc').value.trim(),
    };
    try{
      const r = await fetch(`?api=1&op=meta_set`, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify(payload)
      });
      const j = await r.json();
      if(!r.ok || !j.ok){ alert(j.error || 'Save failed'); return; }

      // Update the visible row immediately, then refresh full list.
      const row = document.querySelector(`.row[data-unit="${CSS.escape(payload.unit)}"]`);
      if (row){
        row.querySelector('.name-cell strong').textContent = payload.name || payload.unit.toUpperCase();
        row.querySelector('.display-cell').textContent = payload.display || '';
        row.querySelector('.desc-cell').textContent = payload.desc || '';
      }
      await loadServices();
      closeModal('editModal');
    }catch(e){ console.error(e); }
  };

  // -------- Add modal (Save) --------
  document.getElementById('btnAdd').onclick = ()=>{
    document.getElementById('addUnit').value='';
    document.getElementById('addName').value='';
    document.getElementById('addDisplay').value='';
    document.getElementById('addDesc').value='';
    document.getElementById('addModal').classList.add('open');
  };

  document.getElementById('addSave').onclick = async ()=>{
    const unit = document.getElementById('addUnit').value.trim();
    const name = document.getElementById('addName').value.trim() || unit.toUpperCase();
    const display = document.getElementById('addDisplay').value.trim() || unit;
    const desc = document.getElementById('addDesc').value.trim();
    if(!unit){ alert('Unit is required'); return; }
    try{
      const r = await fetch(`?api=1&op=add`, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({unit,name,display,desc})
      });
      const j = await r.json();
      if(!r.ok || !j.ok){ alert(j.error || 'Save failed'); return; }
      closeModal('addModal');
      await loadServices(); // new service appears
    }catch(e){ console.error(e); }
  };

  // Manage opens editor for first service
  document.getElementById('btnManage').onclick = async ()=>{
    if (services.length === 0) { await loadServices(); }
    if (services.length > 0) openEdit(services[0].unit);
  };

  // bootstrap
  loadServices();
</script>
</body>
</html>
