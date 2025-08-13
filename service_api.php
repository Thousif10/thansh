<?php
// save as: service_api.php
// Purpose: backend for RHEL service control (systemctl + journalctl)
// Security: allowlist service units, sudo NOPASSWD required for apache user.

header('Content-Type: application/json');

// ---- CONFIG -----------------------------------------------------------------
$ALLOWLIST = [
  'httpd'     => ['display'=>'Apache HTTP (httpd)', 'desc'=>'Web server'],
  'sshd'      => ['display'=>'OpenSSH (sshd)',      'desc'=>'Remote login service'],
  'firewalld' => ['display'=>'FirewallD',           'desc'=>'Firewall manager'],
  'mariadb'   => ['display'=>'MariaDB (mysqld)',    'desc'=>'Database service'],
  'snmpd'     => ['display'=>'SNMP Agent (snmpd)',  'desc'=>'Network monitoring agent'],
];

// default list order returned by ?fn=list
$DEFAULT_ORDER = ['httpd','sshd','firewalld','mariadb','snmpd'];

// If SELinux blocks journalctl/systemctl, you may switch $USE_SUDO off and run PHP under a user with privileges.
// Recommended: keep true and set sudoers properly (see notes at bottom).
$USE_SUDO = true;

// ---- HELPERS ----------------------------------------------------------------
function json_fail($msg, $code=400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
function ok($data=[]){ echo json_encode(['ok'=>true] + $data); exit; }

function run($cmd){
  // capture output + exit code
  $desc = [1=>['pipe','w'], 2=>['pipe','w']];
  $proc = proc_open($cmd, $desc, $pipes);
  if (!is_resource($proc)) return ['code'=>127,'out'=>'proc_open failed'];
  $out = stream_get_contents($pipes[1]);
  $err = stream_get_contents($pipes[2]);
  foreach ($pipes as $p) fclose($p);
  $code = proc_close($proc);
  return ['code'=>$code, 'out'=>trim($out ?: $err)];
}
function sudo($bin){ return (PHP_OS_FAMILY === 'Linux' && posix_geteuid() !== 0 && $GLOBALS['USE_SUDO']) ? 'sudo '.$bin : $bin; }

function clean_unit($unit){
  if (!isset($_REQUEST['unit']) && $unit===null) return null;
  $u = $unit ?? $_REQUEST['unit'] ?? '';
  $u = trim($u);
  // Only bare service name (no .service, no spaces)
  $u = preg_replace('/\.service$/', '', $u);
  if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $u)) json_fail('invalid unit');
  if (!array_key_exists($u, $GLOBALS['ALLOWLIST'])) json_fail('unit not allowed');
  return $u;
}

function svc_is_active($u){
  $r = run(sudo('systemctl').' is-active '.escapeshellarg($u));
  return ($r['code']===0) ? trim($r['out']) : 'inactive';
}
function svc_is_enabled($u){
  $r = run(sudo('systemctl').' is-enabled '.escapeshellarg($u));
  return ($r['code']===0 && trim($r['out'])==='enabled');
}
function svc_status_text($u, $lines=30){
  $r = run(sudo('systemctl').' status '.escapeshellarg($u).' --no-pager -l');
  if ($r['code']!==0 && $r['out']==='') $r['out'] = 'status unavailable';
  // Return last $lines lines for brevity
  $arr = preg_split("/\r\n|\n|\r/", $r['out']);
  $arr = array_slice($arr, -abs((int)$lines));
  return implode("\n", $arr);
}
function svc_logs($u, $n=50){
  $cmd = sudo('journalctl').' -u '.escapeshellarg($u).'.service -n '.((int)$n).' --no-pager -o short-iso';
  $r = run($cmd);
  return $r['out'];
}
function list_services(){
  global $DEFAULT_ORDER, $ALLOWLIST;
  $list = [];
  foreach ($DEFAULT_ORDER as $u){
    $list[] = [
      'unit'    => $u,
      'display' => $ALLOWLIST[$u]['display'],
      'desc'    => $ALLOWLIST[$u]['desc'],
      'status'  => svc_is_active($u),   // active | inactive | failed | activating...
      'enabled' => svc_is_enabled($u),  // true/false
      // Frontend already has donut/analytics placeholder; keep extra fields if you want:
      'uptime'  => null,
      'incidents'=> null,
    ];
  }
  return $list;
}

// ---- ROUTER -----------------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'];
$fn = $_GET['fn'] ?? null;
if ($method === 'POST') {
  $raw = file_get_contents('php://input');
  if ($raw) {
    $json = json_decode($raw, true);
    if (is_array($json)) $_REQUEST = $json + $_REQUEST;
  }
  $fn = $_REQUEST['fn'] ?? $fn;
}

if (!$fn) json_fail('missing fn');

switch ($fn) {
  case 'list':              // GET
    ok(['services'=> list_services() ]);
    break;

  case 'logs':              // GET ?unit=...
    $u = clean_unit(null);
    ok(['unit'=>$u, 'logs'=> svc_logs($u, (int)($_GET['n'] ?? 50)) ]);
    break;

  case 'status':            // GET ?unit=...
    $u = clean_unit(null);
    ok(['unit'=>$u, 'status'=> svc_is_active($u), 'detail'=> svc_status_text($u, (int)($_GET['lines'] ?? 40)) ]);
    break;

  case 'play':              // POST {unit}
  case 'start':
    $u = clean_unit(null);
    $res = run(sudo('systemctl').' start '.escapeshellarg($u));
    ok(['unit'=>$u, 'action'=>'start', 'code'=>$res['code'], 'output'=>$res['out']]);
    break;

  case 'stop':              // POST {unit}
    $u = clean_unit(null);
    $res = run(sudo('systemctl').' stop '.escapeshellarg($u));
    ok(['unit'=>$u, 'action'=>'stop', 'code'=>$res['code'], 'output'=>$res['out']]);
    break;

  case 'reload':            // POST {unit}  (restart in your UI)
  case 'restart':
    $u = clean_unit(null);
    $res = run(sudo('systemctl').' restart '.escapeshellarg($u));
    ok(['unit'=>$u, 'action'=>'restart', 'code'=>$res['code'], 'output'=>$res['out']]);
    break;

  case 'enable':            // POST {unit}
    $u = clean_unit(null);
    $res = run(sudo('systemctl').' enable '.escapeshellarg($u));
    ok(['unit'=>$u, 'action'=>'enable', 'code'=>$res['code'], 'output'=>$res['out']]);
    break;

  case 'disable':           // POST {unit}
    $u = clean_unit(null);
    $res = run(sudo('systemctl').' disable '.escapeshellarg($u));
    ok(['unit'=>$u, 'action'=>'disable', 'code'=>$res['code'], 'output'=>$res['out']]);
    break;

  default:
    json_fail('unknown fn');
}

// ---- NOTES (readme):
/*
1) SUDOERS (RHEL/Alma/Rocky/CentOS Stream):
   sudo visudo
   # Allow apache (or nginx/php-fpm user) to run systemctl & journalctl without password
   apache ALL=(root) NOPASSWD: /bin/systemctl, /usr/bin/systemd-analyze, /usr/bin/journalctl
   Defaults:apache !requiretty

   If using php-fpm under 'nginx':
   nginx  ALL=(root) NOPASSWD: /bin/systemctl, /usr/bin/systemd-analyze, /usr/bin/journalctl
   Defaults:nginx !requiretty

2) SELinux:
   setsebool -P httpd_can_network_connect 1
   setsebool -P httpd_execmem 1
   # If still blocked, audit logs (ausearch -m avc -ts recent | audit2allow)

3) PHP hardening:
   - Keep ALLOWLIST tight (add/remove units you actually manage).
   - We use escapeshellarg and strict unit pattern; no arbitrary commands.

4) Frontend hookup:
   - Your UI is unchanged. Wherever your JS triggers actions, call:
       GET  service_api.php?fn=list
       GET  service_api.php?fn=logs&unit=httpd
       GET  service_api.php?fn=status&unit=httpd
       POST service_api.php  body: {"fn":"play","unit":"httpd"}
     Responses shape:
       { "ok":true, ... }
*/
