<?php
/**
 * Admin login page
 */
require_once '../config.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// Rate limiting - max 5 attempts per 15 minutes
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_attempt_time'] = time();
}

// Reset attempts after 15 minutes
if (time() - $_SESSION['login_attempt_time'] > 900) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_attempt_time'] = time();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if too many attempts
    if ($_SESSION['login_attempts'] >= 5) {
        $error = 'För många inloggningsförsök. Försök igen om 15 minuter.';
    } else {
        $password = $_POST['password'] ?? '';
        
        // Verify password
        if (password_verify($password, ADMIN_PASSWORD_HASH)) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['login_attempts'] = 0; // Reset on success
            $_SESSION['last_activity'] = time();
            header('Location: dashboard.php');
            exit;
        } else {
            $_SESSION['login_attempts']++;
            $error = 'Felaktigt lösenord. Försök igen.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admininloggning - Blogg</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .login-container h2 {
            margin-top: 0;
            text-align: center;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            font-size: 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn-primary {
            width: 100%;
            padding: 0.75rem;
            font-size: 1rem;
            background: #333;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-primary:hover {
            background: #555;
        }
        .error {
            color: #d32f2f;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: #ffebee;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Admininloggning</h2>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="password">Lösenord</label>
                <input type="password" id="password" name="password" required autofocus>
            </div>
            
            <button type="submit" class="btn-primary">Logga in</button>
        </form>
        
        <p style="text-align: center; margin-top: 1.5rem;">
            <a href="../index.php">← Tillbaka till bloggen</a>
        </p>
    </div>
</body>
</html>



