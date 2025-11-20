<?php
require_once 'auth_check.php';
require_once 'db_config.php';
$user_type = $_SESSION['user_type'];
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get user settings from database
$conn = getDBConnection();
$user_language = '';
$user_voice = '';
$ai_name = '';
$ai_role = '';
$ai_communication_style = '';
$ai_introduction = '';

if ($conn) {
    // Get user voice settings
    $stmt = $conn->prepare("SELECT language, voice FROM user WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user_data = $result->fetch_assoc();
        $user_language = $user_data['language'];
        $user_voice = $user_data['voice'];
    }

    $stmt->close();

    // Get AI instruction settings
    $stmt = $conn->prepare("SELECT name, role, communication_style, introduction FROM ai_instruction WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $ai_data = $result->fetch_assoc();
        $ai_name = $ai_data['name'];
        $ai_role = $ai_data['role'];
        $ai_communication_style = $ai_data['communication_style'];
        $ai_introduction = $ai_data['introduction'];
    }

    $stmt->close();
    closeDBConnection($conn);
}

// Load voices from JSON
$voices_data = json_decode(file_get_contents('voices.json'), true);
$voices = $voices_data['voices'] ?? [];

// Extract unique languages for the language filter
$languages = [];
foreach ($voices as $voice) {
    $locale = $voice['Locale'] ?? '';
    $localeName = $voice['LocaleName'] ?? '';
    if ($locale && $localeName && !isset($languages[$locale])) {
        $languages[$locale] = $localeName;
    }
}
// Sort languages by name
asort($languages);
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Settings - Tattle</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/basecoat-css@0.3.3/dist/basecoat.cdn.min.css">
    <script src="https://cdn.jsdelivr.net/npm/basecoat-css@0.3.3/dist/js/all.min.js" defer></script>
    <style>
    body {
        background: #000000;
        color: #ffffff;
        min-height: 100vh;
    }

    .page-container {
        max-width: 800px;
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
        margin-bottom: 1.5rem;
    }

    .section-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 1.25rem;
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

    .voice-selector {
        display: flex;
        gap: 0.5rem;
        align-items: flex-start;
    }

    .voice-selector select {
        flex: 1;
    }

    .btn-play {
        background: #1a1a1a;
        color: #ffffff;
        border: 1px solid #2a2a2a;
        padding: 0.625rem 1rem;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.875rem;
        white-space: nowrap;
        min-width: 100px;
    }

    .btn-play:hover:not(:disabled) {
        background: #2a2a2a;
        border-color: #3a3a3a;
    }

    .btn-play:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .btn-play.playing {
        background: #2563eb;
        border-color: #3b82f6;
    }

    #voice option {
        background: #0a0a0a;
        color: #ffffff;
    }

    textarea.input {
        min-height: 120px;
        resize: vertical;
        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', monospace;
        font-size: 0.875rem;
        line-height: 1.6;
    }

    @media (max-width: 768px) {
        .page-container {
            padding: 1rem;
        }

        .form-section {
            padding: 1rem;
        }

        .voice-selector {
            flex-direction: column;
        }

        .btn-play {
            width: 100%;
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
                <?php if ($user_type === 'superadmin'): ?>
                <a href="superadmin.php" class="nav-link">Superadmin</a>
                <?php endif; ?>
                <a href="user_settings.php" class="nav-link active">Settings</a>
                <a href="logout.php" class="nav-link">Logout</a>
            </nav>
        </header>

        <main class="content">
            <h2 class="page-title">My Settings</h2>
            <p style="color: #737373; margin-bottom: 1.5rem;">Logged in as:
                <strong><?php echo htmlspecialchars($user_name); ?></strong>
            </p>

            <div id="alertContainer" style="display: block;"></div>

            <form id="settingsForm">
                <div class="form-section">
                    <h3 class="section-title">Voice & Language Preferences</h3>
                    <div class="field form-spacer">
                        <label for="language_filter" class="label">Language Filter</label>
                        <select id="language_filter" class="input">
                            <option value="">All Languages</option>
                            <?php foreach ($languages as $locale => $localeName): ?>
                            <option value="<?php echo htmlspecialchars($locale); ?>"
                                <?php echo $user_language === $locale ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($localeName); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="voice" class="label">Voice</label>
                        <div class="voice-selector">
                            <select id="voice" name="voice" class="input">
                                <option value="">Select a voice</option>
                                <?php foreach ($voices as $voice): ?>
                                <option value="<?php echo htmlspecialchars($voice['ShortName']); ?>"
                                    data-locale="<?php echo htmlspecialchars($voice['Locale']); ?>"
                                    data-gender="<?php echo htmlspecialchars($voice['Gender']); ?>"
                                    <?php echo $user_voice === $voice['ShortName'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($voice['DisplayName'] . ' (' . $voice['Gender'] . ') - ' . $voice['LocaleName']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" id="playVoice" class="btn-play" disabled>Play Sample</button>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">AI Instruction</h3>
                    <p style="color: #737373; font-size: 0.875rem; margin-bottom: 1.5rem;">
                        Configure how your AI assistant should behave and communicate.
                    </p>

                    <div class="field form-spacer">
                        <label for="ai_name" class="label">Name</label>
                        <input type="text" id="ai_name" name="ai_name" class="input"
                            placeholder="e.g., Rava (Rafi Avatar)" value="<?php echo htmlspecialchars($ai_name); ?>">
                    </div>

                    <div class="field form-spacer">
                        <label for="ai_role" class="label">Role</label>
                        <textarea id="ai_role" name="ai_role" class="input"
                            placeholder="Define the AI's role and responsibilities (e.g., - Speak in first person as Rafi&#10;- Answer questions using provided context&#10;- Be conversational and technical)"><?php echo htmlspecialchars($ai_role); ?></textarea>
                        <small style="color: #737373; font-size: 0.75rem; margin-top: 0.25rem; display: block;">
                            Use bullet points with "-" for better formatting
                        </small>
                    </div>

                    <div class="field form-spacer">
                        <label for="ai_communication_style" class="label">Communication Style</label>
                        <textarea id="ai_communication_style" name="ai_communication_style" class="input"
                            placeholder="Describe communication preferences (e.g., - Professional but approachable&#10;- Concise and actionable&#10;- Technical when appropriate)"><?php echo htmlspecialchars($ai_communication_style); ?></textarea>
                        <small style="color: #737373; font-size: 0.75rem; margin-top: 0.25rem; display: block;">
                            Use bullet points with "-" for better formatting
                        </small>
                    </div>

                    <div class="field form-spacer">
                        <label for="ai_introduction" class="label">Introduction</label>
                        <textarea id="ai_introduction" name="ai_introduction" class="input" placeholder='e.g., "Hi, I'
                            \''m Rafi - well, RAVA, Rafi'\''s digital avatar. I'\''m here to chat about my work,
                            projects, and experience. What would you like to know?"'
                            style="min-height: 80px;"><?php echo htmlspecialchars($ai_introduction); ?></textarea>
                        <small style="color: #737373; font-size: 0.75rem; margin-top: 0.25rem; display: block;">
                            The greeting message your AI will use
                        </small>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Save Settings</button>
            </form>
        </main>
    </div>

    <script>
    // Form submission handler
    const settingsForm = document.getElementById('settingsForm');
    const alertContainer = document.getElementById('alertContainer');

    // Store original values to detect changes
    let originalVoice = document.getElementById('voice').value;
    let originalIntroduction = document.getElementById('ai_introduction').value;

    settingsForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const submitBtn = this.querySelector('.btn-submit');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        // Clear previous alerts
        alertContainer.innerHTML = '';

        // Gather form data
        const formData = {
            voice: document.getElementById('voice').value,
            ai_name: document.getElementById('ai_name').value,
            ai_role: document.getElementById('ai_role').value,
            ai_communication_style: document.getElementById('ai_communication_style').value,
            ai_introduction: document.getElementById('ai_introduction').value
        };

        // Check if voice or introduction changed
        const voiceChanged = formData.voice !== originalVoice;
        const introChanged = formData.ai_introduction !== originalIntroduction;

        try {
            const response = await fetch('save_user_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                let message = result.message;

                // Add extra message if voice or intro changed
                if ((voiceChanged || introChanged) && formData.ai_introduction && formData.voice) {
                    message += ' Your introduction audio is being generated with the selected voice and language.';
                }

                showAlert(message, 'success');

                // Update original values after successful save
                originalVoice = formData.voice;
                originalIntroduction = formData.ai_introduction;
            } else {
                showAlert(result.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Failed to save settings. Please try again.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });

    function showAlert(message, type) {
        const alertClass = type === 'success' ? 'alert-default' : 'alert-destructive';
        const alertHTML = `
            <div class="alert ${alertClass} form-spacer" role="alert" style="margin-bottom: 1.5rem;">
                <div class="alert-description">${message}</div>
            </div>
        `;
        alertContainer.innerHTML = alertHTML;

        // Auto-hide after 5 seconds
        setTimeout(() => {
            alertContainer.innerHTML = '';
        }, 5000);
    }

    // Elements
    const languageFilter = document.getElementById('language_filter');
    const voiceSelect = document.getElementById('voice');
    const playButton = document.getElementById('playVoice');

    // Store all options for filtering
    const allOptions = Array.from(voiceSelect.options);

    // Current audio element
    let currentAudio = null;

    // Filter voices by language
    function filterVoices() {
        const selectedLocale = languageFilter.value;
        const currentVoice = voiceSelect.value;

        // Clear current options except the first one
        voiceSelect.innerHTML = '<option value="">Select a voice</option>';

        // Filter and add options
        allOptions.slice(1).forEach(option => {
            if (!selectedLocale || option.dataset.locale === selectedLocale) {
                voiceSelect.appendChild(option.cloneNode(true));
            }
        });

        // Restore selection if still available
        if (currentVoice) {
            const matchingOption = Array.from(voiceSelect.options).find(opt => opt.value === currentVoice);
            if (matchingOption) {
                voiceSelect.value = currentVoice;
            }
        }

        // Update play button state
        updatePlayButton();
    }

    // Event listener for language filter change
    languageFilter.addEventListener('change', filterVoices);

    // Update play button when voice selection changes
    voiceSelect.addEventListener('change', updatePlayButton);

    // Play voice sample
    playButton.addEventListener('click', function() {
        const selectedVoice = voiceSelect.value;
        if (!selectedVoice) return;

        // Stop any currently playing audio
        if (currentAudio) {
            currentAudio.pause();
            currentAudio = null;
        }

        // Disable button and show loading state
        playButton.disabled = true;
        playButton.classList.add('playing');
        const originalText = playButton.textContent;
        playButton.textContent = 'Loading...';

        // Construct audio file path
        const audioUrl = `voice_samples/${selectedVoice}.mp3`;

        // Create and play audio
        currentAudio = new Audio(audioUrl);

        currentAudio.addEventListener('loadeddata', function() {
            playButton.textContent = 'Playing...';
        });

        currentAudio.addEventListener('playing', function() {
            playButton.textContent = 'Playing...';
        });

        currentAudio.addEventListener('ended', function() {
            playButton.disabled = false;
            playButton.classList.remove('playing');
            playButton.textContent = originalText;
            currentAudio = null;
        });

        currentAudio.addEventListener('error', function() {
            playButton.disabled = false;
            playButton.classList.remove('playing');
            playButton.textContent = originalText;
            alert('Audio file not found. Please generate voice samples first.');
            currentAudio = null;
        });

        currentAudio.play().catch(error => {
            console.error('Error playing audio:', error);
            playButton.disabled = false;
            playButton.classList.remove('playing');
            playButton.textContent = originalText;
            alert('Failed to play audio. Please try again.');
            currentAudio = null;
        });
    });

    // Update play button enabled state
    function updatePlayButton() {
        playButton.disabled = !voiceSelect.value;
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        filterVoices();
        updatePlayButton();
    });
    </script>
</body>

</html>