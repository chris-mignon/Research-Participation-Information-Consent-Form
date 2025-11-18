<?php
// admin.php - simple admin dashboard (HTTP Basic auth)
define('FILE_TICKETS', __DIR__ . '/raffle_codes.txt');

// --- CONFIG: change these to secure values ---
$ADMIN_USER = 'admin';
$ADMIN_PASS = 'ChangeMeStrongPassword!';
// ------------------------------------------------

if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="Raffle Admin"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Unauthorized';
    exit;
} else {
    if ($_SERVER['PHP_AUTH_USER'] !== $ADMIN_USER || $_SERVER['PHP_AUTH_PW'] !== $ADMIN_PASS) {
        header('WWW-Authenticate: Basic realm="Raffle Admin"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Unauthorized';
        exit;
    }
}

// Load tickets
$tickets = [];
if (file_exists(FILE_TICKETS)) {
    $lines = file(FILE_TICKETS, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = str_getcsv($line);
        if (count($parts) >= 3) {
            $tickets[] = ['code'=>$parts[0], 'ip'=>$parts[1], 'ts'=>$parts[2]];
        }
    }
}

// Helper to format time
function fmt($t) {
    return date('Y-m-d H:i:s', intval($t));
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<title>Raffle Admin</title>
<style>
  body{font-family:Arial, sans-serif; padding:20px; background:#f7f7f7}
  table{border-collapse:collapse;width:100%; background:white}
  th,td{padding:8px;border:1px solid #ddd;text-align:left}
  th{background:#eee}
  .controls{margin-bottom:10px}
</style>
</head>
<body>
  <h2>Raffle Admin Dashboard</h2>

  <div class="controls">
    <form method="get" style="display:inline;">
      <button type="submit" name="download" value="csv">Download CSV</button>
    </form>

    <?php if (file_exists(__DIR__.'/fpdf.php')): ?>
      <span style="margin-left:10px;color:green">FPDF available — PDF generation enabled.</span>
    <?php else: ?>
      <span style="margin-left:10px;color:orange">FPDF missing. Install <code>fpdf.php</code> in this folder to enable PDF generation.</span>
    <?php endif; ?>
  </div>

  <?php
  if (isset($_GET['download']) && $_GET['download'] === 'csv') {
      header('Content-Type: text/csv');
      header('Content-Disposition: attachment; filename="raffle_codes_export.csv"');
      $out = fopen('php://output', 'w');
      fputcsv($out, ['code','ip','timestamp','datetime']);
      foreach ($tickets as $t) {
          fputcsv($out, [$t['code'],$t['ip'],$t['ts'],date('Y-m-d H:i:s', $t['ts'])]);
      }
      fclose($out);
      exit;
  }
  ?>

  <table>
    <thead>
      <tr><th>#</th><th>Code</th><th>IP</th><th>Created</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php $i=1; foreach ($tickets as $t): ?>
        <tr>
          <td><?php echo $i++; ?></td>
          <td><?php echo htmlspecialchars($t['code']); ?></td>
          <td><?php echo htmlspecialchars($t['ip']); ?></td>
          <td><?php echo htmlspecialchars(fmt($t['ts'])); ?></td>
          <td>
            <?php if (file_exists(__DIR__.'/fpdf.php')): ?>
              <a href="generate_pdf.php?code=<?php echo urlencode($t['code']); ?>" target="_blank">Download PDF</a>
            <?php else: ?>
              <em>PDF unavailable</em>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
