<?php
require_once 'auth_check.php';
$user_name = $_SESSION['user_name'];
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Tattle</title>
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
        font-size: 2rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .page-description {
        color: #737373;
        font-size: 0.875rem;
        margin-bottom: 2rem;
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1rem;
    }

    .dashboard-card {
        background: #0a0a0a;
        border: 1px solid #1a1a1a;
        border-radius: 0.75rem;
        padding: 1.5rem;
        transition: all 0.2s ease;
    }

    .dashboard-card:hover {
        border-color: #262626;
    }

    .card-icon {
        width: 40px;
        height: 40px;
        background: #ffffff;
        border-radius: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
    }

    .card-icon svg {
        width: 20px;
        height: 20px;
        color: #000000;
    }

    .card-title {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .card-description {
        color: #737373;
        font-size: 0.875rem;
        margin-bottom: 1rem;
        line-height: 1.5;
    }

    .card-button {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        background: #ffffff;
        color: #000000;
        text-decoration: none;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .card-button:hover {
        background: #e5e5e5;
    }

    @media (max-width: 768px) {
        .page-container {
            padding: 1rem;
        }

        .page-title {
            font-size: 1.5rem;
        }

        .dashboard-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
</head>

<body>
    <div class="page-container">
        <header class="header">
            <h1 class="header-title">Tattle</h1>
            <nav class="nav">
                <a href="index.php" class="nav-link active">Dashboard</a>
                <a href="upload.php" class="nav-link">Data Upload</a>
                <a href="sts.php" class="nav-link">STS Chatbot</a>
                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'superadmin'): ?>
                <a href="superadmin.php" class="nav-link">Superadmin</a>
                <?php endif; ?>
                <a href="user_settings.php" class="nav-link">Settings</a>
                <a href="logout.php" class="nav-link">Logout</a>
            </nav>
        </header>

        <main class="content">
            <h2 class="page-title">Welcome Back <?php echo ucfirst($user_name); ?></h2>
            <p class="page-description">Manage your audio-to-audio chatbot settings</p>

            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="card-title">Data Management</h3>
                    <p class="card-description">Upload documents or raw text for your chatbot knowledge base</p>
                    <a href="upload.php" class="card-button">Go to Upload</a>
                </div>

                <div class="dashboard-card">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <h3 class="card-title">Settings</h3>
                    <p class="card-description">Configure Qdrant and API settings for your chatbot</p>
                    <a href="settings.php" class="card-button">Go to Settings</a>
                </div>
            </div>
        </main>
    </div>
</body>

</html>