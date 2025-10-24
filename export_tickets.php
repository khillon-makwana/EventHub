<?php
require 'ClassAutoLoad.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit;
}

$event_id = (int)($_POST['event_id'] ?? $_GET['event_id'] ?? 0);
$format = $_POST['format'] ?? $_GET['format'] ?? 'csv';

try {
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verify event ownership
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND user_id = ?");
    $stmt->execute([$event_id, $_SESSION['user_id']]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        die("Access denied or event not found.");
    }

    // Get all tickets with user and payment information
    $stmt = $pdo->prepare("
        SELECT 
            t.ticket_code,
            t.status as ticket_status,
            t.purchase_date,
            u.fullname,
            u.email,
            p.transaction_id,
            p.mpesa_receipt_number,
            p.status as payment_status,
            ? as ticket_price
        FROM tickets t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN payment_tickets pt ON t.id = pt.ticket_id
        LEFT JOIN payments p ON pt.payment_id = p.id
        WHERE t.event_id = ?
        ORDER BY t.purchase_date DESC
    ");
    $stmt->execute([$event['ticket_price'], $event_id]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate filename
    $filename = 'tickets_' . preg_replace('/[^a-z0-9]/i', '_', $event['title']) . '_' . date('Y-m-d');

    switch ($format) {
        case 'csv':
            generateCSV($tickets, $event, $filename);
            break;
        case 'excel':
            generateExcel($tickets, $event, $filename);
            break;
        case 'pdf':
            generatePDF($tickets, $event, $filename);
            break;
        default:
            die("Invalid format specified.");
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

function generateCSV($tickets, $event, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Event header
    fputcsv($output, ['Event Report']);
    fputcsv($output, ['Event Name:', $event['title']]);
    fputcsv($output, ['Event Date:', date('F j, Y g:i A', strtotime($event['event_date']))]);
    fputcsv($output, ['Location:', $event['location']]);
    fputcsv($output, ['Ticket Price:', 'KSh ' . number_format($event['ticket_price'], 2)]);
    fputcsv($output, ['Generated:', date('F j, Y g:i A')]);
    fputcsv($output, []); // Empty row
    
    // Column headers
    fputcsv($output, [
        'Ticket Code',
        'Attendee Name',
        'Email',
        'Ticket Status',
        'Ticket Price',
        'Purchase Date',
        'Payment Status',
        'Transaction ID',
        'M-Pesa Receipt'
    ]);
    
    // Data rows
    foreach ($tickets as $ticket) {
        fputcsv($output, [
            $ticket['ticket_code'],
            $ticket['fullname'] ?? 'Guest',
            $ticket['email'] ?? 'N/A',
            ucfirst($ticket['ticket_status']),
            'KSh ' . number_format($ticket['ticket_price'], 2),
            date('M j, Y g:i A', strtotime($ticket['purchase_date'])),
            ucfirst($ticket['payment_status'] ?? 'N/A'),
            $ticket['transaction_id'] ?? 'N/A',
            $ticket['mpesa_receipt_number'] ?? 'N/A'
        ]);
    }
    
    // Summary
    fputcsv($output, []); // Empty row
    fputcsv($output, ['Summary']);
    fputcsv($output, ['Total Tickets:', count($tickets)]);
    
    $active = count(array_filter($tickets, fn($t) => $t['ticket_status'] == 'active'));
    $used = count(array_filter($tickets, fn($t) => $t['ticket_status'] == 'used'));
    $cancelled = count(array_filter($tickets, fn($t) => $t['ticket_status'] == 'cancelled'));
    
    fputcsv($output, ['Active Tickets:', $active]);
    fputcsv($output, ['Used Tickets:', $used]);
    fputcsv($output, ['Cancelled Tickets:', $cancelled]);
    fputcsv($output, ['Total Revenue:', 'KSh ' . number_format((count($tickets) - $cancelled) * $event['ticket_price'], 2)]);
    
    fclose($output);
    exit;
}

function generateExcel($tickets, $event, $filename) {
    // For Excel, we'll use CSV with .xls extension which Excel can open
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th { background-color: #667eea; color: white; font-weight: bold; padding: 10px; border: 1px solid #ddd; }';
    echo 'td { padding: 8px; border: 1px solid #ddd; }';
    echo 'tr:nth-child(even) { background-color: #f2f2f2; }';
    echo '.header { background-color: #764ba2; color: white; padding: 15px; margin-bottom: 20px; }';
    echo '.summary { background-color: #f8f9fa; padding: 15px; margin-top: 20px; font-weight: bold; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    // Event header
    echo '<div class="header">';
    echo '<h1>Ticket Report</h1>';
    echo '<p><strong>Event:</strong> ' . htmlspecialchars($event['title']) . '</p>';
    echo '<p><strong>Date:</strong> ' . date('F j, Y g:i A', strtotime($event['event_date'])) . '</p>';
    echo '<p><strong>Location:</strong> ' . htmlspecialchars($event['location']) . '</p>';
    echo '<p><strong>Ticket Price:</strong> KSh ' . number_format($event['ticket_price'], 2) . '</p>';
    echo '<p><strong>Generated:</strong> ' . date('F j, Y g:i A') . '</p>';
    echo '</div>';
    
    // Table
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Ticket Code</th>';
    echo '<th>Attendee Name</th>';
    echo '<th>Email</th>';
    echo '<th>Ticket Status</th>';
    echo '<th>Price</th>';
    echo '<th>Purchase Date</th>';
    echo '<th>Payment Status</th>';
    echo '<th>Transaction ID</th>';
    echo '<th>M-Pesa Receipt</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($tickets as $ticket) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($ticket['ticket_code']) . '</td>';
        echo '<td>' . htmlspecialchars($ticket['fullname'] ?? 'Guest') . '</td>';
        echo '<td>' . htmlspecialchars($ticket['email'] ?? 'N/A') . '</td>';
        echo '<td>' . ucfirst($ticket['ticket_status']) . '</td>';
        echo '<td>KSh ' . number_format($ticket['ticket_price'], 2) . '</td>';
        echo '<td>' . date('M j, Y g:i A', strtotime($ticket['purchase_date'])) . '</td>';
        echo '<td>' . ucfirst($ticket['payment_status'] ?? 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($ticket['transaction_id'] ?? 'N/A') . '</td>';
        echo '<td>' . htmlspecialchars($ticket['mpesa_receipt_number'] ?? 'N/A') . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    
    // Summary
    $active = count(array_filter($tickets, fn($t) => $t['ticket_status'] == 'active'));
    $used = count(array_filter($tickets, fn($t) => $t['ticket_status'] == 'used'));
    $cancelled = count(array_filter($tickets, fn($t) => $t['ticket_status'] == 'cancelled'));
    
    echo '<div class="summary">';
    echo '<h2>Summary</h2>';
    echo '<p>Total Tickets: ' . count($tickets) . '</p>';
    echo '<p>Active Tickets: ' . $active . '</p>';
    echo '<p>Used Tickets: ' . $used . '</p>';
    echo '<p>Cancelled Tickets: ' . $cancelled . '</p>';
    echo '<p>Total Revenue: KSh ' . number_format((count($tickets) - $cancelled) * $event['ticket_price'], 2) . '</p>';
    echo '</div>';
    
    echo '</body>';
    echo '</html>';
    exit;
}

function generatePDF($tickets, $event, $filename) {
    // Simple HTML to PDF conversion using browser print
    header('Content-Type: text/html; charset=utf-8');
    
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="utf-8">';
    echo '<title>' . htmlspecialchars($event['title']) . ' - Ticket Report</title>';
    echo '<style>';
    echo 'body { font-family: Arial, sans-serif; margin: 20px; }';
    echo '.header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; margin-bottom: 30px; border-radius: 10px; }';
    echo '.header h1 { margin: 0 0 15px 0; }';
    echo '.header p { margin: 5px 0; }';
    echo 'table { width: 100%; border-collapse: collapse; margin: 20px 0; }';
    echo 'th { background-color: #667eea; color: white; padding: 12px; text-align: left; font-weight: bold; }';
    echo 'td { padding: 10px; border-bottom: 1px solid #ddd; }';
    echo 'tr:nth-child(even) { background-color: #f9f9f9; }';
    echo '.summary { background-color: #f8f9fa; padding: 20px; margin-top: 30px; border-radius: 10px; border-left: 5px solid #667eea; }';
    echo '.summary h2 { margin-top: 0; color: #667eea; }';
    echo '.badge { padding: 5px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; }';
    echo '.badge-active { background-color: #28a745; color: white; }';
    echo '.badge-used { background-color: #ffc107; color: #000; }';
    echo '.badge-cancelled { background-color: #dc3545; color: white; }';
    echo '@media print { .no-print { display: none; } }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    // Print button
    echo '<div class="no-print" style="text-align: right; margin-bottom: 20px;">';
    echo '<button onclick="window.print()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: bold;">';
    echo '<span style="margin-right: 8px;">📄</span> Download as PDF';
    echo '</button>';
    echo '</div>';
    
    // Event header
    echo '<div class="header">';
    echo '<h1>Ticket Report</h1>';
    echo '<p><strong>Event:</strong> ' . htmlspecialchars($event['title']) . '</p>';
    echo '<p><strong>Date:</strong> ' . date('F j, Y g:i A', strtotime($event['event_date'])) . '</p>';
    echo '<p><strong>Location:</strong> ' . htmlspecialchars($event['location']) . '</p>';
    echo '<p><strong>Ticket Price:</strong> KSh ' . number_format($event['ticket_price'], 2) . '</p>';
    echo '<p><strong>Generated:</strong> ' . date('F j, Y g:i A') . '</p>';
    echo '</div>';
    
    // Table
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Ticket Code</th>';
    echo '<th>Attendee</th>';
    echo '<th>Email</th>';
    echo '<th>Status</th>';
    echo '<th>Price</th>';
    echo '<th>Purchase Date</th>';
    echo '<th>Payment Status</th>';
    echo '<th>Transaction ID</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($tickets as $ticket) {
        echo '<tr>';
        echo '<td><strong>' . htmlspecialchars($ticket['ticket_code']) . '</strong></td>';
        echo '<td>' . htmlspecialchars($ticket['fullname'] ?? 'Guest') . '</td>';
        echo '<td>' . htmlspecialchars($ticket['email'] ?? 'N/A') . '</td>';
        
        $statusClass = $ticket['ticket_status'] == 'active' ? 'badge-active' : 
                      ($ticket['ticket_status'] == 'used' ? 'badge-used' : 'badge-cancelled');
        echo '<td><span class="badge ' . $statusClass . '">' . ucfirst($ticket['ticket_status']) . '</span></td>';
        
        echo '<td>KSh ' . number_format($ticket['ticket_price'], 2) . '</td>';
        echo '<td>' . date('M j, Y g:i A', strtotime($ticket['purchase_date'])) . '</td>';
        echo '<td>' . ucfirst($ticket['payment_status'] ?? 'N/A') . '</td>';
        echo '<td><small>' . htmlspecialchars($ticket['transaction_id'] ?? 'N/A') . '</small></td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    
    // Summary
    $active = count(array_filter($tickets, fn($t) => $t['ticket_status'] == 'active'));
    $used = count(array_filter($tickets, fn($t) => $t['ticket_status'] == 'used'));
    $cancelled = count(array_filter($tickets, fn($t) => $t['ticket_status'] == 'cancelled'));
    
    echo '<div class="summary">';
    echo '<h2>Summary Statistics</h2>';
    echo '<p><strong>Total Tickets:</strong> ' . count($tickets) . '</p>';
    echo '<p><strong>Active Tickets:</strong> ' . $active . '</p>';
    echo '<p><strong>Used Tickets:</strong> ' . $used . '</p>';
    echo '<p><strong>Cancelled Tickets:</strong> ' . $cancelled . '</p>';
    echo '<p style="font-size: 18px; color: #667eea;"><strong>Total Revenue:</strong> KSh ' . number_format((count($tickets) - $cancelled) * $event['ticket_price'], 2) . '</p>';
    echo '</div>';
    
    echo '<script>';
    echo 'if (window.location.search.includes("auto=1")) { window.print(); }';
    echo '</script>';
    
    echo '</body>';
    echo '</html>';
    exit;
}
?>