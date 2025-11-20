<?php
require_once 'auth_check.php';
require_once 'db_config.php';

// Import embedding helper functions
require_once 'embedding_helpers.php';

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn = getDBConnection();

    if ($conn) {
        // Check if document belongs to user (or if superadmin can delete any)
        if ($user_type === 'superadmin') {
            $stmt = $conn->prepare("SELECT file_path, user_id FROM document WHERE id = ?");
            $stmt->bind_param("i", $id);
        } else {
            $stmt = $conn->prepare("SELECT file_path, user_id FROM document WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $id, $user_id);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $doc = $result->fetch_assoc();

            // Delete file from filesystem
            if (!empty($doc['file_path']) && file_exists($doc['file_path'])) {
                unlink($doc['file_path']);
            }

            // Delete embeddings from Qdrant
            // Get the user_hash from session (for current user) or from user table (for superadmin deleting other's docs)
            if ($user_type === 'superadmin' && $doc['user_id'] != $user_id) {
                // Fetch the user_hash of the document owner
                $stmt2 = $conn->prepare("SELECT user_hash FROM user WHERE id = ?");
                $stmt2->bind_param("i", $doc['user_id']);
                $stmt2->execute();
                $user_result = $stmt2->get_result();
                if ($user_row = $user_result->fetch_assoc()) {
                    $target_user_hash = $user_row['user_hash'];
                } else {
                    $target_user_hash = null;
                }
                $stmt2->close();
            } else {
                $target_user_hash = $_SESSION['user_hash'] ?? null;
            }

            // Get collection name and delete from Qdrant
            if (!empty($target_user_hash)) {
                $collection_name = getCollectionName();
                if (!empty($collection_name)) {
                    $delete_result = deleteEmbedding($target_user_hash, $id, $collection_name);

                    // Log the result for debugging
                    error_log("Qdrant deletion attempt for doc_id=$id, user_hash=$target_user_hash, collection=$collection_name");
                    if ($delete_result['success']) {
                        error_log("Qdrant deletion successful");
                    } else {
                        error_log("Qdrant deletion failed: " . ($delete_result['error'] ?? 'Unknown error'));
                    }
                } else {
                    error_log("Cannot delete from Qdrant: collection_name is empty");
                }
            } else {
                error_log("Cannot delete from Qdrant: user_hash is empty");
            }

            // Delete from database
            $stmt->close();
            if ($user_type === 'superadmin') {
                $stmt = $conn->prepare("DELETE FROM document WHERE id = ?");
                $stmt->bind_param("i", $id);
            } else {
                $stmt = $conn->prepare("DELETE FROM document WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $id, $user_id);
            }
            $stmt->execute();
        }

        $stmt->close();
        closeDBConnection($conn);
    }

    header('Location: upload.php');
    exit;
}

// Load documents from database
$documents = [];
$conn = getDBConnection();

if ($conn) {
    // Fetch documents for current user (superadmin can see all)
    if ($user_type === 'superadmin') {
        $stmt = $conn->prepare("SELECT d.*, u.user_name FROM document d LEFT JOIN user u ON d.user_id = u.id ORDER BY d.created_at DESC");
    } else {
        $stmt = $conn->prepare("SELECT d.*, u.user_name FROM document d LEFT JOIN user u ON d.user_id = u.id WHERE d.user_id = ? ORDER BY d.created_at DESC");
        $stmt->bind_param("i", $user_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
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
    <title>Data Upload - Tattle</title>
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
        margin-bottom: 1.5rem;
    }

    .form-section {
        background: #0a0a0a;
        border: 1px solid #1a1a1a;
        border-radius: 0.75rem;
        padding: 1.5rem;
        margin-bottom: 2rem;
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

    .section-title {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
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

    .btn-delete {
        padding: 0.375rem 0.75rem;
        background: #262626;
        color: #ffffff;
        text-decoration: none;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        font-weight: 500;
        transition: all 0.2s ease;
        display: inline-block;
    }

    .btn-delete:hover {
        background: #404040;
    }

    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: #737373;
    }

    .upload-type-selector {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding: 0.5rem;
        background: #050505;
        border-radius: 0.5rem;
        border: 1px solid #1a1a1a;
    }

    .upload-type-option {
        flex: 1;
        padding: 0.75rem;
        background: transparent;
        border: 1px solid transparent;
        border-radius: 0.375rem;
        color: #737373;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        text-align: center;
    }

    .upload-type-option:hover {
        color: #ffffff;
        background: #0a0a0a;
    }

    .upload-type-option.active {
        color: #ffffff;
        background: #0a0a0a;
        border-color: #2a2a2a;
    }

    .upload-section {
        display: none;
    }

    .upload-section.active {
        display: block;
    }

    textarea.input {
        min-height: 200px;
        resize: vertical;
        font-family: inherit;
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

    .type-badge.file {
        background: #1a2a1a;
        border-color: #2a4a2a;
        color: #4ade80;
    }

    .type-badge.text {
        background: #1a1a2a;
        border-color: #2a2a4a;
        color: #60a5fa;
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

        .data-table th:nth-child(3),
        .data-table td:nth-child(3) {
            display: none;
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
                <a href="upload.php" class="nav-link active">Data Upload</a>
                <a href="sts.php" class="nav-link">STS Chatbot</a>
                <?php if ($user_type === 'superadmin'): ?>
                <a href="superadmin.php" class="nav-link">Superadmin</a>
                <?php endif; ?>
                <a href="user_settings.php" class="nav-link">Settings</a>
                <a href="logout.php" class="nav-link">Logout</a>
            </nav>
        </header>

        <main class="content">
            <h2 class="page-title">Data Upload</h2>

            <div id="alertContainer"></div>

            <div class="form-section">
                <form id="uploadForm">
                    <input type="hidden" name="upload_type" id="upload_type" value="file">
                    <input type="hidden" name="MAX_FILE_SIZE" value="5242880">

                    <div class="upload-type-selector">
                        <button type="button" class="upload-type-option active" data-type="file">
                            📄 Upload File
                        </button>
                        <button type="button" class="upload-type-option" data-type="text">
                            �� Raw Text
                        </button>
                    </div>

                    <div class="field form-spacer">
                        <label for="title" class="label">Context</label>
                        <input type="text" id="title" name="title" class="input" placeholder="Enter the data Context"
                            required>
                    </div>

                    <div class="upload-section active" id="file-section">
                        <div class="field form-spacer">
                            <label for="document" class="label">Document</label>
                            <input type="file" id="document" name="document" class="input"
                                accept=".txt,.pdf,.doc,.docx,.xls,.xlsx" required>
                            <small style="color: #737373; font-size: 0.75rem; margin-top: 0.25rem; display: block;">Max
                                file size: 5MB</small>
                        </div>
                    </div>

                    <div class="upload-section" id="text-section">
                        <div class="field form-spacer">
                            <label for="raw_text" class="label">Text Content</label>
                            <textarea id="raw_text" name="raw_text" class="input"
                                placeholder="Enter your text content here..."></textarea>
                        </div>
                    </div>

                    <div class="field form-spacer">
                        <label for="url" class="label">URL (Optional)</label>
                        <input type="url" id="url" name="url" class="input" placeholder="https://example.com">
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn">Upload Document</button>
                </form>
            </div>

            <h3 class="section-title">Uploaded Documents</h3>
            <div class="table-container">
                <?php if (empty($documents)): ?>
                <div class="empty-state">
                    <p>No documents uploaded yet</p>
                </div>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Context</th>
                            <th>Type</th>
                            <?php if ($user_type === 'superadmin'): ?>
                            <th>Owner</th>
                            <?php endif; ?>
                            <th>File Path</th>
                            <th>URL</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="documentsTableBody">
                        <?php foreach ($documents as $doc): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($doc['title']); ?></td>
                            <td>
                                <span class="type-badge <?php echo htmlspecialchars($doc['type'] ?? 'file'); ?>">
                                    <?php echo htmlspecialchars($doc['type'] ?? 'file'); ?>
                                </span>
                            </td>
                            <?php if ($user_type === 'superadmin'): ?>
                            <td><?php echo htmlspecialchars($doc['user_name'] ?? 'Unknown'); ?></td>
                            <?php endif; ?>
                            <td>
                                <?php if (!empty($doc['file_path'])): ?>
                                <code
                                    style="font-size: 0.75rem; color: #737373;"><?php echo htmlspecialchars($doc['file_path']); ?></code>
                                <?php else: ?>
                                <span style="color: #737373;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo !empty($doc['url']) ? htmlspecialchars($doc['url']) : '-'; ?></td>
                            <td>
                                <a href="?delete=<?php echo $doc['id']; ?>" class="btn-delete"
                                    onclick="return confirm('Are you sure you want to delete this data?')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
    // Elements
    const uploadForm = document.getElementById('uploadForm');
    const alertContainer = document.getElementById('alertContainer');
    const uploadTypeButtons = document.querySelectorAll('.upload-type-option');
    const uploadTypeInput = document.getElementById('upload_type');
    const fileSection = document.getElementById('file-section');
    const textSection = document.getElementById('text-section');
    const documentInput = document.getElementById('document');
    const rawTextInput = document.getElementById('raw_text');
    const submitBtn = document.getElementById('submitBtn');
    const documentsTableBody = document.getElementById('documentsTableBody');

    // Upload type switching
    uploadTypeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const type = this.dataset.type;

            // Update active states
            uploadTypeButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');

            // Update hidden input
            uploadTypeInput.value = type;

            // Show/hide sections
            if (type === 'file') {
                fileSection.classList.add('active');
                textSection.classList.remove('active');
                documentInput.required = true;
                rawTextInput.required = false;
                submitBtn.textContent = 'Upload Document';
            } else {
                fileSection.classList.remove('active');
                textSection.classList.add('active');
                documentInput.required = false;
                rawTextInput.required = true;
                submitBtn.textContent = 'Save Text Data';
            }
        });
    });

    // Form submission with AJAX
    uploadForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const type = uploadTypeInput.value;
        const titleValue = document.getElementById('title').value.trim();

        // Validation
        if (!titleValue) {
            showAlert('Please enter the context.', 'error');
            return false;
        }

        if (type === 'file') {
            if (!documentInput.files || documentInput.files.length === 0) {
                showAlert('Please select a file to upload.', 'error');
                return false;
            }

            // Check file size (5MB = 5 * 1024 * 1024 bytes)
            const maxSize = 5 * 1024 * 1024;
            const file = documentInput.files[0];

            if (file.size > maxSize) {
                showAlert('File size exceeds 5MB limit. Please choose a smaller file.', 'error');
                return false;
            }
        } else if (type === 'text') {
            if (!rawTextInput.value.trim()) {
                showAlert('Please enter some text content.', 'error');
                return false;
            }
        }

        // Disable submit button
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = type === 'file' ? 'Uploading...' : 'Saving...';

        // Clear previous alerts
        alertContainer.innerHTML = '';

        try {
            const formData = new FormData();
            formData.append('title', document.getElementById('title').value);
            formData.append('url', document.getElementById('url').value);
            formData.append('upload_type', type);

            if (type === 'file') {
                formData.append('document', documentInput.files[0]);
            } else {
                formData.append('raw_text', rawTextInput.value);
            }

            const response = await fetch('save_upload.php', {
                method: 'POST',
                body: formData
            });

            // Check if response is OK (status 200-299)
            if (!response.ok) {
                // Try to parse error message from JSON
                try {
                    const errorData = await response.json();
                    showAlert(errorData.message || 'Upload failed', 'error');
                } catch (e) {
                    showAlert('Upload failed with status: ' + response.status, 'error');
                }
                return;
            }

            const result = await response.json();

            if (result.success) {
                showAlert(result.message, 'success');

                // Reset form
                uploadForm.reset();

                // Reload page after 1 second to show updated list
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showAlert(result.message || 'Upload failed', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            // Check if there might be a timeout or network issue
            // In this case, still reload the page to check if upload succeeded
            showAlert('Request timed out or network error. Checking if upload succeeded...', 'error');
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });

    function showAlert(message, type) {
        const alertClass = type === 'success' ? 'alert-default' : 'alert-destructive';
        const alertHTML = `
            <div class="alert ${alertClass} form-spacer" role="alert" style="margin-bottom: 1.5rem; display: block">
                <div class="alert-description">${message}</div>
            </div>
        `;
        alertContainer.innerHTML = alertHTML;
    }

    function addDocumentRow(doc) {
        const row = document.createElement('tr');
        const typeBadgeClass = doc.type === 'file' ? 'file' : 'text';
        const filePath = doc.file_path ?
            `<code style="font-size: 0.75rem; color: #737373;">${escapeHtml(doc.file_path)}</code>` :
            '<span style="color: #737373;">-</span>';
        const url = doc.url ? escapeHtml(doc.url) : '-';

        row.innerHTML = `
            <td>${escapeHtml(doc.title)}</td>
            <td>
                <span class="type-badge ${typeBadgeClass}">
                    ${escapeHtml(doc.type)}
                </span>
            </td>
            <td>${filePath}</td>
            <td>${url}</td>
            <td>
                <a href="?delete=${doc.id}" class="btn-delete"
                    onclick="return confirm('Are you sure you want to delete this data?')">Delete</a>
            </td>
        `;

        documentsTableBody.insertBefore(row, documentsTableBody.firstChild);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    </script>
</body>

</html>