<?php
/**
 * Flux feedback — single drop-in endpoint + admin.
 * URL: https://www.fluxopenhome.com/fluxdashboard/feedback
 *   • POST (multipart, from the app)  -> stores a submission (+ screenshot)
 *   • GET  (browser)                  -> password-protected admin to read them
 *
 * Requires: PHP 8+ with the pdo_sqlite extension (standard on most shared hosts).
 * First login: admin / admin  — then change the password from the admin page.
 * Data (database + screenshots) lives in ./data/, blocked from the web by .htaccess.
 */

declare(strict_types=1);
session_start();

const MAX_TEXT   = 8000;          // cap per text field
const MAX_IMAGE  = 8 * 1024 * 1024; // 8 MB screenshot cap
$DATA = __DIR__ . '/data';
$DB   = $DATA . '/feedback.sqlite';
$UP   = $DATA . '/uploads';

if (!is_dir($DATA)) { @mkdir($DATA, 0700, true); }
if (!is_dir($UP))   { @mkdir($UP, 0700, true); }

// Keep the data directory un-servable even if .htaccess is missing.
@file_put_contents($DATA . '/.htaccess', "Require all denied\nDeny from all\n");

function db(): PDO {
    global $DB;
    $pdo = new PDO('sqlite:' . $DB, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("CREATE TABLE IF NOT EXISTS feedback (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        created_at TEXT NOT NULL,
        kind TEXT, title TEXT, details TEXT,
        name TEXT, email TEXT,
        app_version TEXT, build TEXT, platform TEXT, os TEXT,
        screenshot TEXT, status TEXT NOT NULL DEFAULT 'new', ip TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT)");
    // Seed the default admin password (admin) once.
    $has = $pdo->query("SELECT v FROM settings WHERE k='pw'")->fetchColumn();
    if ($has === false) {
        $pdo->prepare("INSERT INTO settings (k,v) VALUES ('pw',?)")
            ->execute([password_hash('admin', PASSWORD_DEFAULT)]);
        $pdo->prepare("INSERT OR REPLACE INTO settings (k,v) VALUES ('user',?)")->execute(['admin']);
    }
    return $pdo;
}

function s(string $key): string { return trim($_POST[$key] ?? ''); }
function clamp(string $v): string { return mb_substr($v, 0, MAX_TEXT); }
function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ----------------------------------------------------------------------------
 * 1) APP SUBMISSION  — public POST with kind+title, no admin session/action.
 * ------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])
    && (isset($_POST['kind']) || isset($_POST['title']))) {
    header('Content-Type: application/json');
    $title = clamp(s('title'));
    if ($title === '') { http_response_code(422); echo json_encode(['error' => 'title required']); exit; }

    $shot = null;
    if (!empty($_FILES['screenshot']['tmp_name']) && is_uploaded_file($_FILES['screenshot']['tmp_name'])) {
        if (($_FILES['screenshot']['size'] ?? 0) <= MAX_IMAGE) {
            $info = @getimagesize($_FILES['screenshot']['tmp_name']);
            $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/heic' => 'heic', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
            if ($ext) {
                $fname = bin2hex(random_bytes(16)) . '.' . $ext;
                if (@move_uploaded_file($_FILES['screenshot']['tmp_name'], $UP . '/' . $fname)) { $shot = $fname; }
            }
        }
    }

    $stmt = db()->prepare("INSERT INTO feedback
        (created_at,kind,title,details,name,email,app_version,build,platform,os,screenshot,ip)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        gmdate('c'),
        in_array(s('kind'), ['feature','bug'], true) ? s('kind') : 'feature',
        $title, clamp(s('details')),
        mb_substr(s('name'),0,200), mb_substr(s('email'),0,200),
        mb_substr(s('app_version'),0,40), mb_substr(s('build'),0,40),
        mb_substr(s('platform'),0,40), mb_substr(s('os'),0,80),
        $shot, $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

/* ----------------------------------------------------------------------------
 * 2) ADMIN  — everything else (GET, or POST with an action).
 * ------------------------------------------------------------------------- */
$pdo  = db();
$auth = !empty($_SESSION['flux_admin']);
$user = $pdo->query("SELECT v FROM settings WHERE k='user'")->fetchColumn() ?: 'admin';

function csrf(): string {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['csrf'];
}
function check_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); exit('Bad request'); }
}

// --- Actions ---
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'login') {
        $hash = $pdo->query("SELECT v FROM settings WHERE k='pw'")->fetchColumn();
        if (s('username') === $user && password_verify(s('password'), (string)$hash)) {
            session_regenerate_id(true);
            $_SESSION['flux_admin'] = true;
            header('Location: ?'); exit;
        }
        $flash = 'Wrong username or password.';
    } elseif ($auth) {
        check_csrf();
        if ($action === 'logout') { session_destroy(); header('Location: ?'); exit; }
        if ($action === 'password') {
            if (strlen(s('new')) < 4) { $flash = 'Password too short.'; }
            else {
                $pdo->prepare("INSERT OR REPLACE INTO settings (k,v) VALUES ('pw',?)")
                    ->execute([password_hash(s('new'), PASSWORD_DEFAULT)]);
                if (s('newuser') !== '') $pdo->prepare("INSERT OR REPLACE INTO settings (k,v) VALUES ('user',?)")->execute([s('newuser')]);
                $flash = 'Password updated.';
                $user = $pdo->query("SELECT v FROM settings WHERE k='user'")->fetchColumn() ?: 'admin';
            }
        }
        if ($action === 'status' && ctype_digit($_POST['id'] ?? '')) {
            $pdo->prepare("UPDATE feedback SET status=? WHERE id=?")
                ->execute([in_array(s('status'),['new','planned','done','wontfix'],true)?s('status'):'new', (int)$_POST['id']]);
        }
        if ($action === 'delete' && ctype_digit($_POST['id'] ?? '')) {
            $row = $pdo->prepare("SELECT screenshot FROM feedback WHERE id=?"); $row->execute([(int)$_POST['id']]);
            if ($f = $row->fetchColumn()) { @unlink($UP . '/' . basename((string)$f)); }
            $pdo->prepare("DELETE FROM feedback WHERE id=?")->execute([(int)$_POST['id']]);
            $flash = 'Deleted.';
        }
    }
}

// --- Serve a screenshot (auth only) ---
if ($auth && isset($_GET['img']) && ctype_digit($_GET['img'])) {
    $row = $pdo->prepare("SELECT screenshot FROM feedback WHERE id=?"); $row->execute([(int)$_GET['img']]);
    $f = $row->fetchColumn();
    $path = $f ? $UP . '/' . basename((string)$f) : '';
    if ($f && is_file($path)) {
        $mime = ['jpg'=>'image/jpeg','png'=>'image/png','heic'=>'image/heic','webp'=>'image/webp'][pathinfo($path, PATHINFO_EXTENSION)] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime); header('Cache-Control: private, max-age=60');
        readfile($path); exit;
    }
    http_response_code(404); exit;
}

header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Flux Feedback — Admin</title>
<link rel="stylesheet" href="../style.css">
<style>
  .bar { display:flex; align-items:center; gap:12px; margin:18px 0; flex-wrap:wrap; }
  .pill { background:var(--surface-2); border:1px solid var(--line); border-radius:999px; padding:5px 12px; font-size:14px; color:var(--muted); }
  .item { border:1px solid var(--line); border-radius:14px; padding:16px 18px; margin:14px 0; background:var(--surface); }
  .item h3 { margin:0 0 4px; }
  .meta { color:var(--muted); font-size:13px; }
  .tag { font-size:12px; font-weight:700; padding:2px 9px; border-radius:999px; }
  .tag.feature{background:rgba(59,157,255,.18); color:#9fd0ff;} .tag.bug{background:rgba(255,120,120,.18); color:#ffb0b0;}
  .tag.new{background:rgba(255,180,84,.18);color:#ffcf9a;} .tag.planned{background:rgba(59,157,255,.18);color:#9fd0ff;}
  .tag.done{background:rgba(67,209,138,.18);color:#9fe9c4;} .tag.wontfix{background:var(--surface-2);color:var(--muted);}
  input,select,textarea,button { font:inherit; }
  input[type=text],input[type=password]{ background:var(--surface-2); border:1px solid var(--line); color:var(--text); border-radius:9px; padding:9px 11px; }
  button { cursor:pointer; background:var(--accent); color:#04121f; border:0; border-radius:9px; padding:8px 14px; font-weight:700; }
  button.ghost { background:var(--surface-2); color:var(--text); border:1px solid var(--line); }
  form.inline{ display:inline; }
  details summary{ cursor:pointer; color:var(--accent); }
  img.shot{ max-width:100%; border-radius:10px; margin-top:10px; border:1px solid var(--line); }
  .flash{ background:var(--accent-soft); border:1px solid var(--line); border-radius:10px; padding:10px 14px; margin:14px 0; }
</style></head><body>
<div class="topbar"><div class="inner"><a class="brand" href="/fluxdashboard/"><span class="dot"></span> Flux Feedback</a>
<?php if ($auth): ?><form class="inline" method="post" style="margin-left:auto"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="logout"><button class="ghost">Log out</button></form><?php endif; ?>
</div></div>
<div class="wrap">

<?php if ($flash): ?><div class="flash"><?=e($flash)?></div><?php endif; ?>

<?php if (!$auth): ?>
  <div class="hero"><div class="eyebrow">Admin</div><h1>Sign in</h1><p class="lede">View feature requests and bug reports.</p></div>
  <form method="post" class="card" style="max-width:380px">
    <input type="hidden" name="action" value="login">
    <p><input type="text" name="username" placeholder="Username" autocomplete="username" style="width:100%"></p>
    <p><input type="password" name="password" placeholder="Password" autocomplete="current-password" style="width:100%"></p>
    <button type="submit">Sign in</button>
    <p class="meta" style="margin-top:12px">Default: <code>admin</code> / <code>admin</code> — change it once you're in.</p>
  </form>
<?php else:
  $filter = in_array($_GET['f'] ?? '', ['feature','bug','new','planned','done','wontfix'], true) ? $_GET['f'] : '';
  $where  = ''; $params = [];
  if (in_array($filter, ['feature','bug'], true)) { $where = "WHERE kind=?"; $params=[$filter]; }
  elseif ($filter !== '') { $where = "WHERE status=?"; $params=[$filter]; }
  $rows = $pdo->prepare("SELECT * FROM feedback $where ORDER BY id DESC"); $rows->execute($params);
  $rows = $rows->fetchAll();
  $counts = $pdo->query("SELECT status, COUNT(*) c FROM feedback GROUP BY status")->fetchAll();
  $total = $pdo->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
?>
  <div class="hero"><div class="eyebrow">Admin</div><h1>Feedback</h1><p class="lede"><?=$total?> total submission<?=$total==1?'':'s'?>.</p></div>

  <div class="bar">
    <a class="pill" href="?">All</a>
    <a class="pill" href="?f=new">New</a>
    <a class="pill" href="?f=planned">Planned</a>
    <a class="pill" href="?f=done">Done</a>
    <a class="pill" href="?f=feature">Features</a>
    <a class="pill" href="?f=bug">Bugs</a>
    <details style="margin-left:auto"><summary>Change password</summary>
      <form method="post" class="card" style="margin-top:10px">
        <input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="password">
        <p><input type="text" name="newuser" placeholder="New username (optional, current: <?=e($user)?>)" style="width:100%"></p>
        <p><input type="password" name="new" placeholder="New password" autocomplete="new-password" style="width:100%"></p>
        <button type="submit">Update</button>
      </form>
    </details>
  </div>

  <?php if (!$rows): ?><p class="meta">Nothing here yet.</p><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <div class="item">
      <div class="bar" style="margin:0 0 6px">
        <span class="tag <?=e($r['kind'])?>"><?=e($r['kind']==='bug'?'Bug':'Feature')?></span>
        <span class="tag <?=e($r['status'])?>"><?=e(ucfirst($r['status']))?></span>
        <span class="meta" style="margin-left:auto">#<?=$r['id']?> · <?=e(substr($r['created_at'],0,10))?></span>
      </div>
      <h3><?=e($r['title'])?></h3>
      <?php if (trim((string)$r['details'])!==''): ?><p><?=nl2br(e($r['details']))?></p><?php endif; ?>
      <p class="meta">
        <?php if (trim((string)$r['name'])!==''): ?>👤 <?=e($r['name'])?> · <?php endif; ?>
        <?php if (trim((string)$r['email'])!==''): ?><a href="mailto:<?=e($r['email'])?>"><?=e($r['email'])?></a> · <?php else: ?>Anonymous · <?php endif; ?>
        <?=e($r['platform'])?> · <?=e($r['os'])?> · v<?=e($r['app_version'])?> (<?=e($r['build'])?>)
      </p>
      <?php if ($r['screenshot']): ?><details><summary>Screenshot</summary><img class="shot" src="?img=<?=$r['id']?>" alt=""></details><?php endif; ?>
      <div class="bar" style="margin:10px 0 0">
        <form class="inline" method="post">
          <input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=$r['id']?>">
          <select name="status" onchange="this.form.submit()">
            <?php foreach (['new'=>'New','planned'=>'Planned','done'=>'Done','wontfix'=>"Won't fix"] as $k=>$lab): ?>
              <option value="<?=$k?>" <?=$r['status']===$k?'selected':''?>><?=$lab?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <form class="inline" method="post" onsubmit="return confirm('Delete this submission?')">
          <input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>">
          <button class="ghost">Delete</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

  <footer>Flux — fluxopenhome.com</footer>
</div></body></html>
