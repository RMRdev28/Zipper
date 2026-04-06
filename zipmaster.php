<?php
// ============================================================
//  ZipMaster — single-file folder zipper
// ============================================================

$message   = '';
$msgType   = '';
$baseDir   = realpath('.');

// ---------- handle zip action ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'zip') {
    $selected = $_POST['items'] ?? [];
    $zipName  = trim($_POST['zip_name'] ?? '');

    if (empty($selected)) {
        $message = 'Please select at least one file or folder.';
        $msgType = 'error';
    } else {
        if ($zipName === '') {
            $zipName = 'archive_' . date('Ymd_His');
        }
        $zipName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $zipName);
        $zipName .= '.zip';
        $zipPath  = $baseDir . DIRECTORY_SEPARATOR . $zipName;

        if (!class_exists('ZipArchive')) {
            $message = 'ZipArchive extension is not available on this server.';
            $msgType = 'error';
        } else {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                foreach ($selected as $item) {
                    $fullPath = realpath($baseDir . DIRECTORY_SEPARATOR . $item);
                    // security: must stay inside base dir
                    if ($fullPath === false || strpos($fullPath, $baseDir) !== 0) continue;
                    if (is_dir($fullPath)) {
                        $iterator = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS),
                            RecursiveIteratorIterator::SELF_FIRST
                        );
                        foreach ($iterator as $file) {
                            $filePath = $file->getRealPath();
                            $relative = substr($filePath, strlen($baseDir) + 1);
                            if ($file->isDir()) {
                                $zip->addEmptyDir($relative);
                            } else {
                                $zip->addFile($filePath, $relative);
                            }
                        }
                    } elseif (is_file($fullPath)) {
                        $relative = substr($fullPath, strlen($baseDir) + 1);
                        $zip->addFile($fullPath, $relative);
                    }
                }
                $zip->close();
                $message = 'Archive created: <strong>' . htmlspecialchars($zipName) . '</strong> — <a href="?download=' . urlencode($zipName) . '">⬇ Download now</a>';
                $msgType = 'success';
            } else {
                $message = 'Could not create ZIP file. Check write permissions.';
                $msgType = 'error';
            }
        }
    }
}

// ---------- handle download ----------
if (isset($_GET['download'])) {
    $file = basename($_GET['download']);
    $path = $baseDir . DIRECTORY_SEPARATOR . $file;
    if (file_exists($path) && pathinfo($path, PATHINFO_EXTENSION) === 'zip') {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}

// ---------- handle delete zip ----------
if (isset($_GET['delete'])) {
    $file = basename($_GET['delete']);
    $path = $baseDir . DIRECTORY_SEPARATOR . $file;
    if (file_exists($path) && pathinfo($path, PATHINFO_EXTENSION) === 'zip') {
        unlink($path);
        $message = 'Deleted: ' . htmlspecialchars($file);
        $msgType = 'info';
    }
}

// ---------- scan directory ----------
$items = [];
$rawList = scandir($baseDir);
foreach ($rawList as $entry) {
    if ($entry === '.' || $entry === '..' || $entry === basename(__FILE__)) continue;
    $fullPath = $baseDir . DIRECTORY_SEPARATOR . $entry;
    $isDir    = is_dir($fullPath);
    $isZip    = (!$isDir && pathinfo($entry, PATHINFO_EXTENSION) === 'zip');
    $size     = $isDir ? null : filesize($fullPath);
    $mtime    = filemtime($fullPath);
    $items[]  = [
        'name'  => $entry,
        'isDir' => $isDir,
        'isZip' => $isZip,
        'size'  => $size,
        'mtime' => $mtime,
    ];
}

// sort: folders first, then files
usort($items, fn($a, $b) => $b['isDir'] <=> $a['isDir'] ?: strcmp($a['name'], $b['name']));

function formatSize($bytes) {
    if ($bytes === null) return '—';
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576, 2)    . ' MB';
    if ($bytes >= 1024)       return round($bytes / 1024, 2)       . ' KB';
    return $bytes . ' B';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ZipMaster</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Syne:wght@400;700;800&display=swap" rel="stylesheet">
<style>
  :root {
    --bg:        #0d0f14;
    --surface:   #13161e;
    --border:    #1f2433;
    --accent:    #f0c040;
    --accent2:   #3dd68c;
    --danger:    #ff5f5f;
    --info:      #5bc4f5;
    --text:      #e8eaf0;
    --muted:     #6b7280;
    --folder:    #f0a030;
    --zip:       #a78bfa;
    --file:      #94a3b8;
    --radius:    10px;
    --mono:      'JetBrains Mono', monospace;
    --display:   'Syne', sans-serif;
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    background: var(--bg);
    color: var(--text);
    font-family: var(--mono);
    min-height: 100vh;
    padding: 0;
  }

  /* ---- header ---- */
  .header {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 18px 32px;
    display: flex;
    align-items: center;
    gap: 16px;
    position: sticky;
    top: 0;
    z-index: 100;
  }
  .header-logo {
    width: 36px; height: 36px;
    background: var(--accent);
    border-radius: 8px;
    display: grid; place-items: center;
    font-size: 18px;
  }
  .header-title {
    font-family: var(--display);
    font-size: 22px;
    font-weight: 800;
    letter-spacing: -0.5px;
  }
  .header-title span { color: var(--accent); }
  .header-path {
    margin-left: auto;
    font-size: 11px;
    color: var(--muted);
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 5px 10px;
    max-width: 400px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  /* ---- layout ---- */
  .container {
    max-width: 1100px;
    margin: 0 auto;
    padding: 28px 24px 60px;
  }

  /* ---- message ---- */
  .msg {
    border-radius: var(--radius);
    padding: 14px 18px;
    margin-bottom: 22px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: fadeIn .3s ease;
  }
  .msg.success { background: #0d2b1a; border: 1px solid var(--accent2); color: var(--accent2); }
  .msg.error   { background: #2b0d0d; border: 1px solid var(--danger);  color: var(--danger);  }
  .msg.info    { background: #0d1b2b; border: 1px solid var(--info);    color: var(--info);    }
  .msg a       { color: inherit; font-weight: 700; text-decoration: underline; }

  /* ---- toolbar ---- */
  .toolbar {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
    flex-wrap: wrap;
  }
  .toolbar-left { display: flex; align-items: center; gap: 8px; flex: 1; }
  .btn-ghost {
    background: none;
    border: 1px solid var(--border);
    color: var(--muted);
    padding: 6px 12px;
    border-radius: 6px;
    font-family: var(--mono);
    font-size: 12px;
    cursor: pointer;
    transition: all .15s;
  }
  .btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
  .counter {
    font-size: 11px;
    color: var(--muted);
    margin-left: 4px;
  }
  .counter span { color: var(--accent); font-weight: 700; }

  /* ---- file table ---- */
  .file-table {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    margin-bottom: 24px;
  }
  .ft-head {
    display: grid;
    grid-template-columns: 40px 1fr 90px 140px 80px;
    padding: 10px 16px;
    border-bottom: 1px solid var(--border);
    font-size: 10px;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 1px;
    user-select: none;
  }
  .ft-row {
    display: grid;
    grid-template-columns: 40px 1fr 90px 140px 80px;
    padding: 0;
    border-bottom: 1px solid var(--border);
    align-items: center;
    transition: background .12s;
    cursor: pointer;
  }
  .ft-row:last-child { border-bottom: none; }
  .ft-row:hover { background: rgba(240,192,64,.04); }
  .ft-row.selected { background: rgba(240,192,64,.08); }
  .ft-row.selected .ft-name { color: var(--accent); }

  .ft-cell { padding: 10px 8px; font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .ft-cell-check { padding: 10px 8px 10px 16px; display: flex; align-items: center; }

  input[type="checkbox"] {
    width: 15px; height: 15px;
    accent-color: var(--accent);
    cursor: pointer;
  }

  .ft-icon { margin-right: 8px; font-size: 14px; }
  .ft-name { display: flex; align-items: center; }
  .ft-size, .ft-date { color: var(--muted); font-size: 11px; }
  .ft-actions { display: flex; gap: 6px; justify-content: flex-end; padding-right: 12px; }

  .btn-dl {
    background: none;
    border: 1px solid var(--zip);
    color: var(--zip);
    padding: 3px 9px;
    border-radius: 5px;
    font-size: 10px;
    cursor: pointer;
    text-decoration: none;
    font-family: var(--mono);
    transition: all .15s;
    white-space: nowrap;
  }
  .btn-dl:hover { background: var(--zip); color: #fff; }
  .btn-del {
    background: none;
    border: 1px solid var(--danger);
    color: var(--danger);
    padding: 3px 9px;
    border-radius: 5px;
    font-size: 10px;
    cursor: pointer;
    text-decoration: none;
    font-family: var(--mono);
    transition: all .15s;
    white-space: nowrap;
  }
  .btn-del:hover { background: var(--danger); color: #fff; }

  /* ---- zip panel ---- */
  .zip-panel {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 22px 24px;
    display: flex;
    gap: 14px;
    align-items: flex-end;
    flex-wrap: wrap;
  }
  .zip-panel label {
    display: block;
    font-size: 10px;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 6px;
  }
  .zip-name-wrap { flex: 1; min-width: 200px; }
  .zip-input {
    width: 100%;
    background: var(--bg);
    border: 1px solid var(--border);
    color: var(--text);
    padding: 10px 14px;
    border-radius: 7px;
    font-family: var(--mono);
    font-size: 13px;
    outline: none;
    transition: border-color .15s;
  }
  .zip-input:focus { border-color: var(--accent); }
  .zip-input::placeholder { color: var(--muted); }

  .btn-zip {
    background: var(--accent);
    color: #0d0f14;
    border: none;
    padding: 10px 24px;
    border-radius: 7px;
    font-family: var(--display);
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    transition: all .15s;
    white-space: nowrap;
    letter-spacing: .3px;
  }
  .btn-zip:hover { background: #ffd060; transform: translateY(-1px); box-shadow: 0 4px 16px rgba(240,192,64,.3); }
  .btn-zip:active { transform: translateY(0); }
  .btn-zip:disabled { background: var(--border); color: var(--muted); cursor: not-allowed; transform: none; box-shadow: none; }

  /* ---- empty ---- */
  .empty {
    text-align: center;
    padding: 48px 20px;
    color: var(--muted);
    font-size: 13px;
  }

  @keyframes fadeIn { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }

  @media (max-width: 680px) {
    .ft-head, .ft-row { grid-template-columns: 40px 1fr 80px; }
    .ft-size, .ft-date { display: none; }
    .header-path { display: none; }
  }
</style>
</head>
<body>

<div class="header">
  <div class="header-logo">🗜</div>
  <div class="header-title">Zip<span>Master</span></div>
  <div class="header-path">📂 <?= htmlspecialchars($baseDir) ?></div>
</div>

<div class="container">

  <?php if ($message): ?>
  <div class="msg <?= $msgType ?>"><?= $message ?></div>
  <?php endif; ?>

  <form method="POST" id="mainForm">
    <input type="hidden" name="action" value="zip">

    <!-- toolbar -->
    <div class="toolbar">
      <div class="toolbar-left">
        <button type="button" class="btn-ghost" onclick="selectAll()">Select All</button>
        <button type="button" class="btn-ghost" onclick="selectNone()">Deselect All</button>
        <button type="button" class="btn-ghost" onclick="invertSel()">Invert</button>
        <div class="counter">Selected: <span id="selCount">0</span></div>
      </div>
    </div>

    <!-- file list -->
    <div class="file-table">
      <div class="ft-head">
        <div></div>
        <div>Name</div>
        <div>Size</div>
        <div>Modified</div>
        <div></div>
      </div>

      <?php if (empty($items)): ?>
      <div class="empty">This folder is empty.</div>
      <?php else: ?>
        <?php foreach ($items as $item):
          $name  = $item['name'];
          $isDir = $item['isDir'];
          $isZip = $item['isZip'];
          $icon  = $isDir ? '📁' : ($isZip ? '🗜' : '📄');
          $iconColor = $isDir ? 'var(--folder)' : ($isZip ? 'var(--zip)' : 'var(--file)');
          $date  = date('d M Y, H:i', $item['mtime']);
          $size  = formatSize($item['size']);
        ?>
        <div class="ft-row" onclick="toggleRow(this)">
          <div class="ft-cell-check">
            <input type="checkbox" name="items[]" value="<?= htmlspecialchars($name) ?>" onclick="event.stopPropagation(); updateCount();">
          </div>
          <div class="ft-cell ft-name">
            <span class="ft-icon" style="color:<?= $iconColor ?>"><?= $icon ?></span>
            <?= htmlspecialchars($name) ?>
            <?php if ($isDir): ?><span style="font-size:10px;color:var(--muted);margin-left:6px;">(folder)</span><?php endif; ?>
          </div>
          <div class="ft-cell ft-size"><?= $size ?></div>
          <div class="ft-cell ft-date"><?= $date ?></div>
          <div class="ft-cell ft-actions">
            <?php if ($isZip): ?>
              <a href="?download=<?= urlencode($name) ?>" class="btn-dl" onclick="event.stopPropagation();">⬇ DL</a>
              <a href="?delete=<?= urlencode($name) ?>" class="btn-del" onclick="event.stopPropagation(); return confirm('Delete <?= htmlspecialchars($name) ?>?');">✕</a>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- zip panel -->
    <div class="zip-panel">
      <div class="zip-name-wrap">
        <label for="zip_name">Archive name (optional)</label>
        <input type="text" id="zip_name" name="zip_name" class="zip-input" placeholder="e.g. my_backup">
      </div>
      <button type="submit" class="btn-zip" id="zipBtn" disabled>🗜 Create ZIP</button>
    </div>

  </form>
</div>

<script>
  function updateCount() {
    const checked = document.querySelectorAll('input[name="items[]"]:checked').length;
    document.getElementById('selCount').textContent = checked;
    document.getElementById('zipBtn').disabled = checked === 0;
    document.querySelectorAll('.ft-row').forEach(row => {
      const cb = row.querySelector('input[type="checkbox"]');
      if (cb) row.classList.toggle('selected', cb.checked);
    });
  }

  function toggleRow(row) {
    const cb = row.querySelector('input[type="checkbox"]');
    if (cb) { cb.checked = !cb.checked; updateCount(); }
  }

  function selectAll() {
    document.querySelectorAll('input[name="items[]"]').forEach(cb => cb.checked = true);
    updateCount();
  }
  function selectNone() {
    document.querySelectorAll('input[name="items[]"]').forEach(cb => cb.checked = false);
    updateCount();
  }
  function invertSel() {
    document.querySelectorAll('input[name="items[]"]').forEach(cb => cb.checked = !cb.checked);
    updateCount();
  }
</script>
</body>
</html>
