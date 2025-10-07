<?php
class forms
{
    public function signup($conf, $FlashMessageObject)
    {
        $error = $FlashMessageObject->getMsg('errors');
        print $FlashMessageObject->getMsg('msg');

?>
        <h1>Sign Up</h1>
        <form method="POST" action="signup.php">
            <div class="mb-3">
                <label for="fullname" class="form-label">Name</label>
                <input type="text" class="form-control" id="fullname" name="fullname" aria-describedby="nameHelp" maxlength="30" value="<?php echo isset($_SESSION['fullname']) ? $_SESSION['fullname'] : ''; ?>" placeholder="Enter your fullname" required>
                <?php print(isset($error['nameFormat_error']) ? '<div id="nameHelp" class="alert alert-danger">' . $error['nameFormat_error'] . '</div>' : ''); ?>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="email" name="email" aria-describedby="emailHelp" maxlength="100" value="<?php echo isset($_SESSION['email']) ? $_SESSION['email'] : ''; ?>" placeholder="Enter your email" required>
                <?php print(isset($error['mailFormat_error']) ? '<div id="emailHelp" class="alert alert-danger">' . $error['mailFormat_error'] . '</div>' : ''); ?>
                <?php print(isset($error['emailDomain_error']) ? '<div id="nameHelp" class="alert alert-danger">' . $error['emailDomain_error'] . '</div>' : ''); ?>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" value="<?php echo isset($_SESSION['password']) ? $_SESSION['password'] : ''; ?>" placeholder="Enter your password" required>
                <?php print(isset($error['passwordLength_error']) ? '<div id="emailHelp" class="alert alert-danger">' . $error['passwordLength_error'] . '</div>' : ''); ?>
            </div>
            <?php $this->submit_button("Sign Up", "signup"); ?>
            <a href="signin.php">Already have an account? Log in</a>
        </form>
    <?php
    }

    private function submit_button($value, $name)
    {
    ?>
        <button type="submit" class="btn btn-primary" name="<?php echo $name; ?>" value="<?php echo $value; ?>"><?php echo $value; ?></button>
    <?php
    }

    public function signin($conf, $FlashMessageObject)
    {
    ?>
        <h1>Sign In</h1>
        <form method="POST" action="signin.php">
            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <?php $this->submit_button("Sign In", "signin"); ?>
            <div class="mt-3 text-center">
                <p style="color: #ffffffff;">
                    Forgot Password? 
                    <a href="forgot_password.php" class="text-decoration-none" style="color: #0d6efd;">
                        Reset it here.
                    </a>
                </p>
            </div>
            <div class="text-center mt-3">
                <p style="color: #ffffffff;">
                    Don’t have an account?
                    <a href="signup.php" style="color: #0d6efd; text-decoration: none; font-weight: 500;">
                        Sign Up
                    </a>
                </p>
            </div>
        </form>
<?php
        //  display flash messages if any
        if ($FlashMessageObject->hasMessages('msg')) {
            echo $FlashMessageObject->getMsg('msg');
        }
    }
}
