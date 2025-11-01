<?php
require 'ClassAutoLoad.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit;
}

$payment_id = (int)($_GET['payment_id'] ?? 0);

if ($payment_id <= 0) {
    $FlashMessageObject->setMsg('msg', 'Invalid payment request.', 'danger');
    header("Location: my_tickets.php");
    exit;
}

try {
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get payment details
    $stmt = $pdo->prepare("
        SELECT p.*, e.title as event_title, u.fullname
        FROM payments p
        JOIN events e ON p.event_id = e.id
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ? AND p.user_id = ? AND p.status = 'pending'
    ");
    $stmt->execute([$payment_id, $_SESSION['user_id']]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        $FlashMessageObject->setMsg('msg', 'Payment not found or already processed.', 'danger');
        header("Location: my_tickets.php");
        exit;
    }

    // Initiate real M-Pesa STK push on submit
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $phone = preg_replace('/\D+/', '', $_POST['msisdn'] ?? '');
        if (!preg_match('/^2547\d{8}$/', $phone)) {
            $FlashMessageObject->setMsg('msg', 'Enter a valid Safaricom number in format 2547XXXXXXXX.', 'danger');
        } else {
            try {
                require_once __DIR__ . '/mpesa_integration/MpesaService.php';
                $mpesa = new MpesaService();

                $base = rtrim($conf['site_url'] ?? '', '/');
                $callbackUrl = $base . '/mpesa_integration/mpesa_callback.php?payment_id=' . urlencode((string)$payment_id);
                // Keep AccountReference within 12 alphanumeric characters
                $accountReference = 'EVT' . (int)$payment['event_id'] . 'P' . (int)$payment_id;

                $response = $mpesa->stkPush((int)$payment['amount'], $phone, $callbackUrl, $accountReference, 'Ticket Purchase');

                if (!empty($response['CheckoutRequestID'])) {
                    $stmt = $pdo->prepare("UPDATE payments SET transaction_id = ? WHERE id = ?");
                    $stmt->execute([$response['CheckoutRequestID'], $payment_id]);
                }

                $FlashMessageObject->setMsg('msg', 'STK push sent. Check your phone to complete the payment.', 'success');
                header('Location: mpesa_process_payment.php?payment_id=' . $payment_id . '&sent=1');
                exit;
            } catch (Exception $e) {
                $FlashMessageObject->setMsg('msg', 'Failed to initiate payment: ' . htmlspecialchars($e->getMessage()), 'danger');
            }
        }
    }

} catch (PDOException $e) {
    $FlashMessageObject->setMsg('msg', 'Error processing payment: ' . $e->getMessage(), 'danger');
    header("Location: my_tickets.php");
    exit;
}

$LayoutObject->head($conf);
$LayoutObject->header($conf);
?>

<style>
    .payment-container {
        min-height: 80vh;
        display: flex;
        align-items: center;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 3rem 0;
    }
    .payment-card { border: none; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
    .payment-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 1.5rem; display:flex; align-items:center; gap:1rem; }
    .mpesa-logo { width:48px; height:48px; background: rgba(255,255,255,0.2); border-radius: 12px; display:flex; align-items:center; justify-content:center; font-size: 1.5rem; }
    .payment-body { padding: 2rem; background: #fff; }
    .info-banner { background: #f8f9ff; border: 1px solid #e2e8ff; border-radius: 12px; padding: 1rem 1.25rem; margin-bottom:1rem; }
    .details-section { border-top: 1px dashed #eaeaea; padding-top: 1rem; margin-top: 1rem; }
    .detail-row { display:flex; align-items:center; justify-content:space-between; padding: .5rem 0; }
    .detail-label { color:#6b7280; }
    .amount-highlight { font-weight: 700; color: #10b981; }
    .action-buttons { margin-top: 1rem; }
    .btn-action { border-radius: 12px; padding:.75rem 1rem; font-weight:600; }
    .btn-success-custom { background: #10b981; border-color:#10b981; }
    .btn-secondary-custom { background: #e5e7eb; border-color:#e5e7eb; color:#111827; }
    .security-notice { margin-top: 1rem; color:#6b7280; }
</style>

<div class="payment-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card payment-card">
                    <div class="payment-header">
                        <div class="mpesa-logo">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h4>M-Pesa Payment</h4>
                    </div>
                    
                    <div class="payment-body">
                        <?php echo $FlashMessageObject->getMsg('msg'); ?>
                        
                        <div class="info-banner">
                            <h5>
                                <i class="fas fa-info-circle me-2"></i>
                                M-Pesa STK Push
                            </h5>
                            <p>Enter your M-Pesa phone number to receive an STK push and authorize the payment on your device.</p>
                        </div>
                        
                        <div class="details-section">
                            <h6>
                                <i class="fas fa-receipt"></i>
                                Payment Summary
                            </h6>
                            
                            <div class="detail-row">
                                <span class="detail-label">Event</span>
                                <span class="detail-value"><?php echo htmlspecialchars($payment['event_title']); ?></span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="detail-label">Amount</span>
                                <span class="detail-value amount-highlight">KSh <?php echo number_format($payment['amount'], 2); ?></span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="detail-label">Quantity</span>
                                <span class="detail-value"><?php echo (int)$payment['quantity']; ?> ticket(s)</span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="detail-label">Customer</span>
                                <span class="detail-value"><?php echo htmlspecialchars($payment['fullname']); ?></span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="detail-label">Checkout Ref</span>
                                <span class="detail-value">
                                    <span class="transaction-code"><?php echo htmlspecialchars($payment['transaction_id'] ?? 'Pending'); ?></span>
                                </span>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <h6>Initiate Payment</h6>

                            <form method="POST" class="mb-3" autocomplete="off">
                                <div class="mb-3">
                                    <label for="msisdn" class="form-label">M-Pesa Phone Number (2547XXXXXXX)</label>
                                    <input type="text" class="form-control" id="msisdn" name="msisdn" placeholder="2547XXXXXXXX" required inputmode="numeric" pattern="2547\d{8}">
                                </div>
                                <button type="submit" class="btn btn-action btn-success-custom w-100">
                                    <i class="fas fa-mobile-alt me-2"></i>
                                    Pay Now
                                </button>
                            </form>

                            <?php if (!empty($_GET['sent'])): ?>
                                <div class="text-center mb-3" id="awaitingBox">
                                    <div class="mb-2">
                                        <div class="spinner-border text-success" role="status" style="width: 1.5rem; height: 1.5rem;"></div>
                                    </div>
                                    <div>Awaiting payment confirmation...</div>
                                    <small>Once approved on your phone, tickets will be issued automatically.</small>
                                    <div class="mt-3">
                                        <button type="button" id="refreshStatusBtn" class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-sync-alt me-1"></i> Refresh Status Now
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="text-center">
                                <a href="event_details.php?id=<?php echo (int)$payment['event_id']; ?>" class="btn btn-secondary-custom">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    Cancel & Return
                                </a>
                            </div>
                        </div>
                        
                        <div class="security-notice">
                            <small>
                                <i class="fas fa-shield-alt"></i>
                                Transactions use official M-Pesa APIs. Do not share your PIN with anyone.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$LayoutObject->footer($conf);
?>

<script>
(function() {
  const params = new URLSearchParams(window.location.search);
  const sent = params.get('sent');
  const paymentId = params.get('payment_id');
  if (!sent || !paymentId) return;

  const check = () => {
    fetch('payment_status_api.php?payment_id=' + encodeURIComponent(paymentId), {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'completed' && data.ticket_id) {
        window.location.href = 'view_ticket.php?ticket_id=' + data.ticket_id + '&paid=1';
      } else if (data.status === 'failed' && data.event_id) {
        window.location.href = 'event_details.php?id=' + data.event_id;
      } else {
        // still pending, recheck
        setTimeout(check, 3000);
      }
    })
    .catch(() => setTimeout(check, 4000));
  };

  // Start polling a few seconds after page load
  setTimeout(check, 2500);

  // Manual refresh button
  const btn = document.getElementById('refreshStatusBtn');
  if (btn) {
    btn.addEventListener('click', function() {
      btn.disabled = true;
      fetch('mpesa_query_status.php', {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        body: 'payment_id=' + encodeURIComponent(paymentId)
      })
      .then(r => r.json())
      .then(data => {
        if (data.status === 'completed' && data.ticket_id) {
          window.location.href = 'view_ticket.php?ticket_id=' + data.ticket_id + '&paid=1';
        } else {
          btn.disabled = false;
        }
      })
      .catch(() => { btn.disabled = false; });
    });
  }
})();
</script>
