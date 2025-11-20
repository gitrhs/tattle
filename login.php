<?php
session_start();
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $conn = getDBConnection();

        if ($conn) {
            // Prepare statement to prevent SQL injection
            $stmt = $conn->prepare("SELECT id, user_name, password, hash, language, voice, type FROM user WHERE user_name = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Set session variables
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['user_name'];
                    $_SESSION['user_hash'] = $user['hash'];
                    $_SESSION['user_type'] = $user['type'];
                    $_SESSION['language'] = $user['language'];
                    $_SESSION['voice'] = $user['voice'];

                    $stmt->close();
                    closeDBConnection($conn);

                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Invalid credentials';
                }
            } else {
                $error = 'Invalid credentials';
            }

            $stmt->close();
            closeDBConnection($conn);
        } else {
            $error = 'Database connection failed. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Tattle</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/basecoat-css@0.3.3/dist/basecoat.cdn.min.css">
    <script src="https://cdn.jsdelivr.net/npm/basecoat-css@0.3.3/dist/js/all.min.js" defer></script>
    <style>
    body {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #000000;
        padding: 1rem;
    }

    .login-container {
        width: 100%;
        max-width: 400px;
    }

    .login-card {
        background: #0a0a0a;
        border: 1px solid #1a1a1a;
        border-radius: 0.75rem;
        padding: 2rem;
    }

    .logo-section {
        text-align: center;
        margin-bottom: 2rem;
    }

    .logo-icon {
        width: 48px;
        height: 48px;
        background: #ffffff;
        border-radius: 0.5rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
    }

    .logo-icon svg {
        width: 24px;
        height: 24px;
        color: #000000;
    }

    .login-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #ffffff;
        margin-bottom: 0.5rem;
    }

    .login-subtitle {
        font-size: 0.875rem;
        color: #737373;
    }

    .form-spacer {
        margin-bottom: 1rem;
    }

    .btn-login {
        width: 100%;
        background: #ffffff;
        color: #000000;
        font-weight: 500;
        padding: 0.625rem 1rem;
        border: none;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.875rem;
    }

    .btn-login:hover {
        background: #e5e5e5;
    }

    .btn-login:active {
        background: #d4d4d4;
    }

    .footer-text {
        text-align: center;
        margin-top: 1.5rem;
        font-size: 0.75rem;
        color: #525252;
    }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo-section">
                <div class="logo-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <h2 class="login-title">Welcome Back</h2>
                <p class="login-subtitle">Sign in to your admin portal</p>
            </div>

            <?php if (isset($error)): ?>
            <div class="alert alert-destructive form-spacer" role="alert" style="display: block;">
                <div class="alert-description"><?php echo htmlspecialchars($error); ?></div>
            </div>
            <?php endif; ?>

            <form method="post">
                <div class="field form-spacer">
                    <label for="username" class="label">Username</label>
                    <input type="text" id="username" name="username" class="input" placeholder="Enter username" required
                        autofocus>
                </div>

                <div class="field form-spacer">
                    <label for="password" class="label">Password</label>
                    <input type="password" id="password" name="password" class="input" placeholder="Enter password"
                        required>
                </div>

                <button type="submit" class="btn-login">
                    Sign In
                </button>
            </form>
        </div>

        <p class="footer-text">
            Tattle
        </p>
    </div>
</body>

</html>