<?php
// generate_pdf.php
// Requires fpdf.php (FPDF library) in same folder: https://www.fpdf.org/
// Usage: generate_pdf.php?code=ABC1234

define('FILE_TICKETS', __DIR__ . '/raffle_codes.txt');

$code = $_GET['code'] ?? '';
$code = preg_replace('/[^A-Z0-9]/', '', strtoupper($code));

// find the ticket
$found = null;
if ($code && file_exists(FILE_TICKETS)) {
    $lines = file(FILE_TICKETS, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = str_getcsv($line);
        if ($parts[0] === $code) {
            $found = ['code'=>$parts[0],'ip'=>$parts[1],'ts'=>$parts[2]];
            break;
        }
    }
}

if (!$found) {
    echo "Ticket not found.";
    exit;
}

// If FPDF is available, generate PDF, otherwise show a printable HTML fallback
if (file_exists(__DIR__ . '/fpdf.php')) {
    require_once(__DIR__ . '/fpdf.php');

    $pdf = new FPDF('P','mm',array(80,120)); // custom small ticket size
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(false);

    // Background box
    $pdf->SetFillColor(240,240,255);
    $pdf->Rect(5,5,70,110,'F');

    // Title
    $pdf->SetFont('Arial','B',14);
    $pdf->SetXY(10,10);
    $pdf->Cell(60,8,'RAFFLE TICKET',0,1,'C');

    // Code big
    $pdf->SetFont('Arial','B',28);
    $pdf->SetXY(10,30);
    $pdf->Cell(60,20,$found['code'],0,1,'C');

    // Meta
    $pdf->SetFont('Arial','',10);
    $pdf->SetXY(10,60);
    $pdf->Cell(60,6,'Issued: ' . date('Y-m-d H:i:s', $found['ts']),0,1,'C');
    $pdf->Cell(60,6,'IP: ' . $found['ip'],0,1,'C');

    // Footer small note
    $pdf->SetFont('Arial','I',8);
    $pdf->SetXY(10,95);
    $pdf->MultiCell(60,4,'Keep this ticket safe. Present it if your code is drawn in the raffle.',0,'C');

    $pdf->Output('D', 'raffle_ticket_' . $found['code'] . '.pdf');
    exit;
} else {
    // HTML fallback (open printable page)
    ?>
    <!doctype html>
    <html><head><meta charset="utf-8"><title>Ticket <?php echo htmlspecialchars($found['code']); ?></title>
      <style>
        body{font-family:Arial, sans-serif;padding:20px}
        .ticket{width:320px;border:2px dashed #446;background:#eef;padding:15px;margin:auto;text-align:center}
        h1{margin:8px 0}
        .code{font-size:36px;letter-spacing:6px;margin:10px 0}
        .meta{font-size:12px;color:#333}
        .print{margin-top:12px}
      </style>
    </head><body>
      <div class="ticket">
        <h1>Raffle Ticket</h1>
        <div class="code"><?php echo htmlspecialchars($found['code']); ?></div>
        <div class="meta">Issued: <?php echo date('Y-m-d H:i:s', $found['ts']); ?><br>IP: <?php echo htmlspecialchars($found['ip']); ?></div>
        <div class="print"><button onclick="window.print()">Print / Save as PDF</button></div>
      </div>
    </body></html>
    <?php
    exit;
}
