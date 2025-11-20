<?php
require_once 'auth_check.php';
require_once 'db_config.php';

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Get necessary data from database
$conn = getDBConnection();
$user_hash = '';

if ($conn) {
    // Get user hash and voice
    $stmt = $conn->prepare("SELECT hash FROM user WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user_data = $result->fetch_assoc();
        $user_hash = $user_data['hash'];
    }
    $stmt->close();

    closeDBConnection($conn);
}
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TTS Demo - Tattle</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/basecoat-css@0.3.3/dist/basecoat.cdn.min.css">
    <script src="https://cdn.jsdelivr.net/npm/basecoat-css@0.3.3/dist/js/all.min.js" defer></script>
    <style>
    body {
        background: #000000;
        color: #ffffff;
        min-height: 100vh;
    }

    .page-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 1.5rem;
    }

    .header {
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #1a1a1a;
    }

    .header-title {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 1rem;
    }

    .nav {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .nav-link {
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        text-decoration: none;
        color: #737373;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        border: 1px solid transparent;
    }

    .nav-link:hover {
        color: #ffffff;
        background: #0a0a0a;
    }

    .nav-link.active {
        color: #ffffff;
        background: #0a0a0a;
        border-color: #1a1a1a;
    }

    .content {
        margin-bottom: 2rem;
    }

    .page-title {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .page-description {
        color: #737373;
        font-size: 0.875rem;
        margin-bottom: 2rem;
    }

    .code-section {
        background: #0a0a0a;
        color: #737373;
        border: 1px solid #1a1a1a;
        border-radius: 0.75rem;
        padding: 1rem;
        margin-bottom: 1rem;
        height: 100%;
        overflow-x: auto;
    }

    .form-spacer {
        margin-bottom: 1.5rem;
    }

    .btn-submit {
        background: #ffffff;
        color: #000000;
        font-weight: 500;
        padding: 0.75rem 2rem;
        border: none;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.875rem;
    }

    .btn-submit:hover:not(:disabled) {
        background: #e5e5e5;
    }

    .btn-submit:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .response-section {
        background: #0a0a0a;
        border: 1px solid #1a1a1a;
        border-radius: 0.75rem;
        padding: 2rem;
        margin-bottom: 2rem;
        display: none;
    }

    .response-section.visible {
        display: block;
    }

    .response-header {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 1rem;
        color: #ffffff;
    }

    .response-text {
        background: #050505;
        border: 1px solid #1a1a1a;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 1rem;
        color: #e5e5e5;
        font-size: 0.875rem;
        line-height: 1.6;
        white-space: pre-wrap;
        word-wrap: break-word;
    }

    .audio-player {
        width: 100%;
        margin-top: 1rem;
    }

    .loading-spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid #404040;
        border-top: 2px solid #ffffff;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        margin-left: 0.5rem;
        vertical-align: middle;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    .alert {
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1.5rem;
        font-size: 0.875rem;
    }

    .alert-error {
        background: #2a1a1a;
        border: 1px solid #4a2a2a;
        color: #ff6b6b;
    }

    .alert-success {
        background: #1a2a1a;
        border: 1px solid #2a4a2a;
        color: #4ade80;
    }

    @media (max-width: 768px) {
        .page-container {
            padding: 1rem;
        }

        .code-section,
        .response-section {
            padding: 0.5rem;
        }
    }
    </style>
</head>

<body>
    <div class="page-container">
        <header class="header">
            <h1 class="header-title">Tattle</h1>
            <nav class="nav">
                <a href="index.php" class="nav-link">Dashboard</a>
                <a href="upload.php" class="nav-link">Data Upload</a>
                <a href="sts.php" class="nav-link active">STS Chatbot</a>
                <?php if ($user_type === 'superadmin'): ?>
                <a href="superadmin.php" class="nav-link">Superadmin</a>
                <?php endif; ?>
                <a href="user_settings.php" class="nav-link">Settings</a>
                <a href="logout.php" class="nav-link">Logout</a>
            </nav>
        </header>

        <main class="content">
            <h2 class="page-title">STS Chatbot Demo</h2>
            <p class="page-description">This is what it looks like on your website.</p>

            <div id="alertContainer"></div>
            <iframe src="frame.php?hash=<?php echo htmlspecialchars($user_hash); ?>"
                style="width:100%; height:600px; border:none; border-radius:0.75rem; box-shadow:0 4px 6px rgba(0,0,0,0.1);"></iframe>
            <br>
            <hr>
            <br>
            <h2 class="page-title">Implementation</h2>
            <p class="page-description">How to use the chatbot on your website.</p>
            <h4><b>1. Put on your page header</b></h4>
            <div class="code-section">
                <pre><code>&lt;link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/basecoat-css@0.3.3/dist/basecoat.cdn.min.css"&gt;</code></pre>
            </div>
            <h4><b>2. Put on your page body</b></h4>
            <div class="code-section">
                <pre><code>&lt;script src="https://cdn.jsdelivr.net/npm/basecoat-css@0.3.3/dist/js/all.min.js" defer&gt;&lt;/script&gt;</code></pre>
            </div>
            <h4><b>3. Put the chatbot container wherever you like to put at</b></h4>
            <div class="code-section">
                <pre><code>&lt;iframe src="https://tattle.2ai.dev/frame.php?hash=<?php echo $user_hash; ?>"&gt;&lt;/iframe&gt;</code></pre>
            </div>
        </main>
    </div>
</body>

</html>