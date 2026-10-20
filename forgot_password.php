<?php
require_once 'db_connect.php';
require_once 'includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!emailExists($email)) {
        $error = 'No account found with that email address.';
    } else {
        // Generate reset token
        $token = generateResetToken();
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $stmt = mysqli_prepare($conn, 
            "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "sss", $token, $expiry, $email);
        
        if (mysqli_stmt_execute($stmt)) {
            // In production, send email with reset link
            // For demo, show the token
            $reset_link = "http://localhost/smartecotrack/reset_password.php?token=" . $token;
            $success = "Password reset link has been generated. (Demo mode)<br>
                       <a href='$reset_link'>Click here to reset password</a>";
        } else {
            $error = 'Failed to process request. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - SmartEcoTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #1B4D3E 0%, #2D6A4F 50%, #1B5E20 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header text-center">
                        <i class="fas fa-lock" style="font-size: 3rem;"></i>
                        <h4>Forgot Password</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <p class="text-muted">Enter your email address and we'll send you a link to reset your password.</p>
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" 
                                       placeholder="Enter your email" required>
                            </div>
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-paper-plane"></i> Send Reset Link
                            </button>
                        </form>
                        <hr>
                        <div class="text-center">
                            <a href="login.php" class="text-success">Back to Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>