<?php
require_once 'superadmin_check.php';
require_once 'db_config.php';

// Load global settings from database
$conn = getDBConnection();
$global_settings = [
    'qdrant_url' => '',
    'qdrant_api_key' => '',
    'collection_name' => '',
    'api_url' => '',
    'google_api_key' => '',
    'mistral_api_key' => '',
    'zai_api_key' => '',
    'google_prompt' => '',
    'mistral_prompt' => '',
    'zai_prompt' => '',
    'provider' => '',
    'model' => ''
];

if ($conn) {
    $result = $conn->query("SELECT * FROM global_setting WHERE id = 1");
    if ($result && $result->num_rows === 1) {
        $settings_data = $result->fetch_assoc();
        $global_settings = [
            'qdrant_url' => $settings_data['qdrant_url'],
            'qdrant_api_key' => $settings_data['qdrant_api_key'],
            'collection_name' => $settings_data['collection_name'],
            'google_api_key' => $settings_data['google_api_key'],
            'mistral_api_key' => $settings_data['mistral_api_key'],
            'zai_api_key' => $settings_data['zai_api_key'],
            'google_prompt' => $settings_data['google_prompt'],
            'mistral_prompt' => $settings_data['mistral_prompt'],
            'zai_prompt' => $settings_data['zai_prompt'],
            'api_url' => $settings_data['api_url'],
            'provider' => $settings_data['provider'],
            'model' => $settings_data['model']

        ];
    }

    // Load users
    $users = [];
    $result = $conn->query("SELECT id, user_name, hash, language, voice, type, created_at FROM user ORDER BY created_at DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }

    closeDBConnection($conn);
}
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tattle</title>
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

    .page-subtitle {
        color: #737373;
        font-size: 0.875rem;
        margin-bottom: 2rem;
    }

    .form-section {
        background: #0a0a0a;
        border: 1px solid #1a1a1a;
        border-radius: 0.75rem;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .section-title {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #1a1a1a;
    }

    .form-spacer {
        margin-bottom: 1rem;
    }

    .btn-submit {
        background: #ffffff;
        color: #000000;
        font-weight: 500;
        padding: 0.625rem 1.5rem;
        border: none;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.875rem;
    }

    .btn-submit:hover {
        background: #e5e5e5;
    }

    .btn-primary {
        background: #2563eb;
        color: #ffffff;
        font-weight: 500;
        padding: 0.625rem 1.5rem;
        border: none;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.875rem;
    }

    .btn-primary:hover {
        background: #1d4ed8;
    }

    .btn-danger {
        padding: 0.375rem 0.75rem;
        background: #991b1b;
        color: #ffffff;
        border: none;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-danger:hover {
        background: #7f1d1d;
    }

    .table-container {
        background: #0a0a0a;
        border: 1px solid #1a1a1a;
        border-radius: 0.75rem;
        overflow: hidden;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table thead {
        background: #0a0a0a;
        border-bottom: 1px solid #1a1a1a;
    }

    .data-table th {
        text-align: left;
        padding: 1rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: #737373;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .data-table td {
        padding: 1rem;
        border-top: 1px solid #1a1a1a;
        font-size: 0.875rem;
    }

    .data-table tbody tr {
        transition: background 0.2s ease;
    }

    .data-table tbody tr:hover {
        background: #0f0f0f;
    }

    .type-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        background: #1a1a1a;
        border: 1px solid #2a2a2a;
        border-radius: 0.25rem;
        font-size: 0.625rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #737373;
    }

    .type-badge.superadmin {
        background: #1a1a2a;
        border-color: #2a2a4a;
        color: #60a5fa;
    }

    .type-badge.user {
        background: #1a2a1a;
        border-color: #2a4a2a;
        color: #4ade80;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal.active {
        display: flex;
    }

    .modal-content {
        background: #0a0a0a;
        border: 1px solid #1a1a1a;
        border-radius: 0.75rem;
        padding: 2rem;
        max-width: 500px;
        width: 90%;
    }

    .modal-title {
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
    }

    .modal-buttons {
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
        margin-top: 1.5rem;
    }

    .btn-secondary {
        background: #262626;
        color: #ffffff;
        font-weight: 500;
        padding: 0.625rem 1.5rem;
        border: none;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.875rem;
    }

    .btn-secondary:hover {
        background: #404040;
    }

    @media (max-width: 768px) {
        .page-container {
            padding: 1rem;
        }

        .form-section {
            padding: 1rem;
        }

        .data-table {
            font-size: 0.75rem;
        }

        .data-table th,
        .data-table td {
            padding: 0.75rem 0.5rem;
        }
    }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
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
                <a href="sts.php" class="nav-link">STS Chatbot</a>
                <a href="superadmin.php" class="nav-link active">Superadmin</a>
                <a href="user_settings.php" class="nav-link">Settings</a>
                <a href="logout.php" class="nav-link">Logout</a>
            </nav>
        </header>

        <main class="content">
            <h2 class="page-title">System Administration</h2>
            <p class="page-subtitle">Configure global settings and manage users</p>

            <div id="alertContainer"></div>

            <!-- Global Settings Section -->
            <div class="form-section">
                <h3 class="section-title">Global Settings</h3>
                <form id="globalSettingsForm">
                    <div class="field form-spacer">
                        <label for="qdrant_url" class="label">QDRANT_URL</label>
                        <input type="text" id="qdrant_url" name="qdrant_url" class="input"
                            value="<?php echo htmlspecialchars($global_settings['qdrant_url']); ?>"
                            placeholder="https://your-qdrant-instance.com" required>
                    </div>
                    <div class="field form-spacer">
                        <label for="qdrant_api_key" class="label">QDRANT_API_KEY</label>
                        <input type="text" id="qdrant_api_key" name="qdrant_api_key" class="input"
                            value="<?php echo htmlspecialchars($global_settings['qdrant_api_key']); ?>"
                            placeholder="Your Qdrant API key" required>
                    </div>
                    <div class="field form-spacer">
                        <label for="collection_name" class="label">COLLECTION_NAME</label>
                        <input type="text" id="collection_name" name="collection_name" class="input"
                            value="<?php echo htmlspecialchars($global_settings['collection_name']); ?>"
                            placeholder="Collection name" required>
                    </div>
                    <div class="field form-spacer">
                        <label for="api_url" class="label">API URL</label>
                        <input type="text" id="api_url" name="api_url" class="input"
                            value="<?php echo htmlspecialchars($global_settings['api_url']); ?>"
                            placeholder="https://api.example.com" required>
                    </div>
                    <div class="field form-spacer">
                        <label for="google_api_key" class="label">Google API Key</label>
                        <input type="text" id="google_api_key" name="google_api_key" class="input"
                            value="<?php echo htmlspecialchars($global_settings['google_api_key']); ?>"
                            placeholder="Your Google API key" required>
                    </div>
                    <div class="field form-spacer">
                        <label for="mistral_api_key" class="label">Mistral API Key</label>
                        <input type="text" id="mistral_api_key" name="mistral_api_key" class="input"
                            value="<?php echo htmlspecialchars($global_settings['mistral_api_key']); ?>"
                            placeholder="Your Mistral API key" required>
                    </div>
                    <div class="field form-spacer">
                        <label for="zai_api_key" class="label">Z.ai API Key</label>
                        <input type="text" id="zai_api_key" name="zai_api_key" class="input"
                            value="<?php echo htmlspecialchars($global_settings['zai_api_key']); ?>"
                            placeholder="Your Z.ai API key">
                    </div>
                    <div class="field form-spacer">
                        <label for="provider" class="label">LLM Provider</label>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <select id="provider" name="provider" class="input" required style="flex: 1;">
                                <option value="">Select Provider</option>
                                <option value="google"
                                    <?php echo ($global_settings['provider'] === 'google') ? 'selected' : ''; ?>>Google
                                </option>
                                <option value="mistral"
                                    <?php echo ($global_settings['provider'] === 'mistral') ? 'selected' : ''; ?>>
                                    Mistral
                                </option>
                                <option value="z.ai"
                                    <?php echo ($global_settings['provider'] === 'z.ai') ? 'selected' : ''; ?>>
                                    Z.ai
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="field form-spacer">
                        <label for="model" class="label">Model</label>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <select id="model" name="model" class="input" required style="flex: 1;">
                                <option value="">Select a provider first</option>
                            </select>
                            <button type="button" id="reloadModels" class="btn-secondary"
                                style="padding: 0.625rem; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"
                                title="Reload models from API">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path
                                        d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Prompt Templates Section -->
                    <div id="google_prompt_container" class="field form-spacer" style="margin-top: 2rem; display: none;">
                        <label for="google_prompt" class="label">Google Prompt Template (Optional)</label>
                        <textarea id="google_prompt" name="google_prompt" class="input" rows="8"
                            placeholder="Leave empty to use default template"><?php echo htmlspecialchars($global_settings['google_prompt'] ?? ''); ?></textarea>
                        <p style="font-size: 0.75rem; color: #737373; margin-top: 0.25rem;">
                            Supports placeholders: {{ai_name}}, {{ai_role}}, {{language}}, {{ai_communication_style}}
                        </p>
                    </div>

                    <div id="mistral_prompt_container" class="field form-spacer" style="margin-top: 2rem; display: none;">
                        <label for="mistral_prompt" class="label">Mistral Prompt Template (Optional)</label>
                        <textarea id="mistral_prompt" name="mistral_prompt" class="input" rows="8"
                            placeholder="Leave empty to use default template"><?php echo htmlspecialchars($global_settings['mistral_prompt'] ?? ''); ?></textarea>
                        <p style="font-size: 0.75rem; color: #737373; margin-top: 0.25rem;">
                            Supports placeholders: {{ai_name}}, {{ai_role}}, {{language}}, {{ai_communication_style}}
                        </p>
                    </div>

                    <div id="zai_prompt_container" class="field form-spacer" style="margin-top: 2rem; display: none;">
                        <label for="zai_prompt" class="label">Z.ai Prompt Template (Optional)</label>
                        <textarea id="zai_prompt" name="zai_prompt" class="input" rows="8"
                            placeholder="Leave empty to use default template"><?php echo htmlspecialchars($global_settings['zai_prompt'] ?? ''); ?></textarea>
                        <p style="font-size: 0.75rem; color: #737373; margin-top: 0.25rem;">
                            Supports placeholders: {{ai_name}}, {{ai_role}}, {{language}}, {{ai_communication_style}}
                        </p>
                    </div>

                    <button type="submit" class="btn-submit">Save Global Settings</button>
                </form>
            </div>

            <!-- User Management Section -->
            <div class="form-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h3 class="section-title" style="margin: 0; padding: 0; border: none;">User Management</h3>
                    <button type="button" class="btn-primary" onclick="openAddUserModal()">Add User</button>
                </div>

                <div class="table-container">
                    <?php if (empty($users)): ?>
                    <div style="text-align: center; padding: 3rem 1rem; color: #737373;">
                        <p>No users found</p>
                    </div>
                    <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Hash</th>
                                <th>Language</th>
                                <th>Type</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <?php foreach ($users as $user): ?>
                            <tr data-user-id="<?php echo $user['id']; ?>">
                                <td><?php echo htmlspecialchars($user['user_name']); ?></td>
                                <td><code
                                        style="font-size: 0.75rem; color: #737373;"><?php echo htmlspecialchars(substr($user['hash'], 0, 16)) . '...'; ?></code>
                                </td>
                                <td><?php echo htmlspecialchars($user['language'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="type-badge <?php echo htmlspecialchars($user['type']); ?>">
                                        <?php echo htmlspecialchars($user['type']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <button class="btn-danger"
                                        onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['user_name']); ?>')">Delete</button>
                                    <?php else: ?>
                                    <span style="color: #737373; font-size: 0.75rem;">Current User</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Add New User</h3>
            <form id="addUserForm">
                <div class="field form-spacer">
                    <label for="new_username" class="label">Username</label>
                    <input type="text" id="new_username" name="username" class="input" placeholder="Enter username"
                        required>
                </div>
                <div class="field form-spacer">
                    <label for="new_password" class="label">Password</label>
                    <input type="password" id="new_password" name="password" class="input" placeholder="Enter password"
                        required minlength="6">
                </div>
                <div class="field form-spacer">
                    <label for="new_user_type" class="label">User Type</label>
                    <select id="new_user_type" name="type" class="input" required>
                        <option value="user">User</option>
                        <option value="superadmin">Superadmin</option>
                    </select>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn-secondary" onclick="closeAddUserModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    const alertContainer = document.getElementById('alertContainer');

    // Global Settings Form
    document.getElementById('globalSettingsForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const submitBtn = this.querySelector('.btn-submit');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';
        alertContainer.innerHTML = '';

        const formData = {
            qdrant_url: document.getElementById('qdrant_url').value,
            qdrant_api_key: document.getElementById('qdrant_api_key').value,
            collection_name: document.getElementById('collection_name').value,
            api_url: document.getElementById('api_url').value,
            google_api_key: document.getElementById('google_api_key').value,
            mistral_api_key: document.getElementById('mistral_api_key').value,
            zai_api_key: document.getElementById('zai_api_key').value,
            google_prompt: document.getElementById('google_prompt').value,
            mistral_prompt: document.getElementById('mistral_prompt').value,
            zai_prompt: document.getElementById('zai_prompt').value,
            provider: document.getElementById('provider').value,
            model: document.getElementById('model').value
        };

        try {
            const response = await fetch('save_global_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();
            showAlert(result.message, result.success ? 'success' : 'error');
        } catch (error) {
            console.error('Error:', error);
            showAlert('Failed to save settings. Please try again.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });

    // Add User Modal Functions
    function openAddUserModal() {
        document.getElementById('addUserModal').classList.add('active');
    }

    function closeAddUserModal() {
        document.getElementById('addUserModal').classList.remove('active');
        document.getElementById('addUserForm').reset();
    }

    // Add User Form
    document.getElementById('addUserForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const submitBtn = this.querySelector('.btn-primary');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Adding...';

        const formData = {
            username: document.getElementById('new_username').value,
            password: document.getElementById('new_password').value,
            type: document.getElementById('new_user_type').value
        };

        try {
            const response = await fetch('add_user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                showAlert(result.message, 'success');
                closeAddUserModal();
                // Reload page after 1 second
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showAlert(result.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Failed to add user. Please try again.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });

    // Delete User Function
    async function deleteUser(userId, username) {
        if (!confirm(
                `Are you sure you want to delete user "${username}"? This will also delete all their documents.`)) {
            return;
        }

        try {
            const response = await fetch('delete_user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    user_id: userId
                })
            });

            const result = await response.json();

            if (result.success) {
                showAlert(result.message, 'success');
                // Remove row from table
                const row = document.querySelector(`tr[data-user-id="${userId}"]`);
                if (row) row.remove();
            } else {
                showAlert(result.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Failed to delete user. Please try again.', 'error');
        }
    }

    function showAlert(message, type) {
        const alertClass = type === 'success' ? 'alert-default' : 'alert-destructive';
        const alertHTML = `
            <div class="alert ${alertClass} form-spacer" role="alert" style="margin-bottom: 1.5rem;">
                <div class="alert-description">${message}</div>
            </div>
        `;
        alertContainer.innerHTML = alertHTML;
        setTimeout(() => alertContainer.innerHTML = '', 5000);
    }

    // Close modal when clicking outside
    document.getElementById('addUserModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeAddUserModal();
        }
    });

    // Dynamic Model Loading
    const providerSelect = document.getElementById('provider');
    const modelSelect = document.getElementById('model');
    const savedModel = '<?php echo htmlspecialchars($global_settings['model']); ?>';

    async function loadModels(provider, selectModel = null) {
        if (!provider) {
            modelSelect.innerHTML = '<option value="">Select a provider first</option>';
            return;
        }

        try {
            let jsonFile;
            if (provider === 'google') {
                jsonFile = 'google_model.json';
            } else if (provider === 'mistral') {
                jsonFile = 'mistral_model.json';
            } else if (provider === 'z.ai') {
                jsonFile = 'zai_model.json';
            }

            const response = await fetch(jsonFile);
            const data = await response.json();

            let models = [];
            if (provider === 'google') {
                models = data.models.map(m => ({
                    value: m.name.replace('models/', ''),
                    label: m.displayName || m.name
                }));
            } else {
                // For mistral and z.ai, both use data.data format
                models = data.data.map(m => ({
                    value: m.id,
                    label: m.name || m.id
                }));
            }

            modelSelect.innerHTML = '<option value="">Select a model</option>';
            models.forEach(model => {
                const option = document.createElement('option');
                option.value = model.value;
                option.textContent = model.label;
                if (selectModel && model.value === selectModel) {
                    option.selected = true;
                }
                modelSelect.appendChild(option);
            });
        } catch (error) {
            console.error('Error loading models:', error);
            modelSelect.innerHTML = '<option value="">Error loading models</option>';
        }
    }

    // Function to show/hide prompt templates based on provider
    function updatePromptVisibility(provider) {
        // Hide all prompt containers
        document.getElementById('google_prompt_container').style.display = 'none';
        document.getElementById('mistral_prompt_container').style.display = 'none';
        document.getElementById('zai_prompt_container').style.display = 'none';

        // Show the relevant prompt container
        if (provider === 'google') {
            document.getElementById('google_prompt_container').style.display = 'block';
        } else if (provider === 'mistral') {
            document.getElementById('mistral_prompt_container').style.display = 'block';
        } else if (provider === 'z.ai') {
            document.getElementById('zai_prompt_container').style.display = 'block';
        }
    }

    // Load models when provider changes
    providerSelect.addEventListener('change', function() {
        loadModels(this.value);
        updatePromptVisibility(this.value);
    });

    // Load models on page load if provider is already selected
    if (providerSelect.value) {
        loadModels(providerSelect.value, savedModel);
        updatePromptVisibility(providerSelect.value);
    }

    // Reload Models Button
    document.getElementById('reloadModels').addEventListener('click', async function() {
        const provider = providerSelect.value;

        if (!provider) {
            showAlert('Please select a provider first', 'error');
            return;
        }

        const btn = this;
        const svg = btn.querySelector('svg');
        btn.disabled = true;
        svg.style.animation = 'spin 1s linear infinite';

        try {
            const response = await fetch('refresh_models.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    provider: provider
                })
            });

            const result = await response.json();

            if (result.success) {
                showAlert(result.message + (result.count ? ` (${result.count} models loaded)` : ''),
                    'success');
                // Reload the models into the dropdown
                await loadModels(provider, modelSelect.value);
            } else {
                showAlert(result.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Failed to refresh models. Please try again.', 'error');
        } finally {
            btn.disabled = false;
            svg.style.animation = '';
        }
    });
    </script>
</body>

</html>