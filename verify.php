<?php
require 'ClassAutoLoad.php';
require_once __DIR__ . '/Proc/Verification.php';

$Verification = new Verification();
$Verification->handleVerification($conf, $FlashMessageObject);

$LayoutObject->head($conf);
$LayoutObject->header($conf);
?>

<div class="container mt-5">
    <h2>Email Verification</h2>

    <?php echo $FlashMessageObject->getMsg('msg'); ?>

    <form method="POST" class="mt-3">
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($_GET['email'] ?? ''); ?>">
        <div class="mb-3">
            <label for="code" class="form-label">Enter 6-digit code</label>
            <input type="text" class="form-control" id="code" name="code" maxlength="6" required>
        </div>
        <button type="submit" name="verify" class="btn btn-primary">Verify</button>
    </form>

    <p class="mt-3">Didn't get the code? <a href="resend_code.php?email=<?php echo urlencode($_GET['email'] ?? ''); ?>">Resend Code</a></p>
</div>

<?php
$LayoutObject->footer($conf);
