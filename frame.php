<?php
require_once 'db_config.php';
require_once 'encrypt_helper.php';
require_once 'template_helper.php';

// Static user hash
$user_hash = $_GET['hash'];
if (!$user_hash) {
    die("User hash is required.");
}
// Get necessary data from database
$conn = getDBConnection();
$voice = 'alloy';
$language = 'English';
$api_key = '';
$collection_name = '';
$api_url = '';
$ai_name = '';
$ai_role = '';
$ai_communication_style = '';
$ai_introduction = '';
$auth_key = '';
$prompt_template = '';
// default model
$model = "gemma-3-4b-it";
$provider = "google";

if ($conn) {
    // Get user info by hash
    $stmt = $conn->prepare("SELECT id, voice, language FROM user WHERE hash = ?");
    $stmt->bind_param("s", $user_hash);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user_data = $result->fetch_assoc();
        $user_id = $user_data['id'];
        $voice = $user_data['voice'];
        $language = $user_data['language'];

        // Get global settings
        $global_result = $conn->query("SELECT google_api_key, mistral_api_key, zai_api_key, model, provider, collection_name, api_url, google_prompt, mistral_prompt, zai_prompt FROM global_setting WHERE id = 1");
        if ($global_result && $global_result->num_rows === 1) {
            $settings_data = $global_result->fetch_assoc();
            $api_key = $settings_data['google_api_key'];
            $mistral_api_key = $settings_data['mistral_api_key'];
            $zai_api_key = $settings_data['zai_api_key'];
            $collection_name = $settings_data['collection_name'];
            $model = $settings_data['model'];
            $provider = $settings_data['provider'];
            $api_url = $settings_data['api_url'];

            // Get the appropriate prompt template based on provider
            if ($provider === 'mistral') {
                $prompt_template = $settings_data['mistral_prompt'];
            } elseif ($provider === 'z.ai') {
                $prompt_template = $settings_data['zai_prompt'];
            } else {
                $prompt_template = $settings_data['google_prompt'];
            }
        }

        // Determine which API key to use based on provider
        if ($provider === 'mistral') {
            $selected_api_key = $mistral_api_key;
        } elseif ($provider === 'z.ai') {
            $selected_api_key = $zai_api_key;
        } else {
            $selected_api_key = $api_key;
        }

        // Check if API key contains comma and split into array
        $api_keys_array = [];
        if (strpos($selected_api_key, ',') !== false) {
            // Multiple keys found, split them
            $api_keys_array = array_map('trim', explode(',', $selected_api_key));
        } else {
            // Single key
            $api_keys_array = [$selected_api_key];
        }

        // Get AI instruction settings
        $ai_stmt = $conn->prepare("SELECT name, role, communication_style, introduction FROM ai_instruction WHERE user_id = ?");
        $ai_stmt->bind_param("i", $user_id);
        $ai_stmt->execute();
        $ai_result = $ai_stmt->get_result();

        if ($ai_result->num_rows === 1) {
            $ai_data = $ai_result->fetch_assoc();
            $ai_name = $ai_data['name'];
            $ai_role = $ai_data['role'];
            $ai_communication_style = $ai_data['communication_style'];
            $ai_introduction = $ai_data['introduction'];
        }
        $ai_stmt->close();
    }
    $stmt->close();
    closeDBConnection($conn);
}

// Encrypt each API key into auth_keys array
// embedding_api_key = $user_hash, llm_api_key = each key in array
$auth_keys = [];
if ($user_hash) {
    foreach ($api_keys_array as $key) {
        if (!empty($key)) {
            $auth_keys[] = APIKeyEncryptor::encryptKeys($user_hash, $key);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audio Reactive Interface with Whisper</title>
    <link rel="stylesheet" href="frame.css">
</head>

<body>
    <!-- Loading Screen -->
    <div id="loadingScreen">
        <div id="loadingText">Loading 0%</div>
    </div>

    <!-- Main Content -->
    <div id="mainContent">
        <div class="live-indicator" id="statusText">
            Tap Mic to Start
        </div>

        <div id="chatContainer">
            <div class="empty-state"></div>
        </div>

        <div class="ambient-glow" id="glow"></div>

        <div class="mic-container">
            <button class="btn-mic off" id="micBtn">
                <svg viewBox="0 0 24 24">
                    <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z" />
                    <path
                        d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z" />
                </svg>
            </button>
        </div>
    </div>

    <script type="module">
    // API Configuration from PHP
    const config = {
        userHash: <?php echo json_encode($user_hash); ?>,
        voice: <?php echo json_encode($voice); ?>,
        authKey: <?php echo json_encode($auth_keys); ?>,
        collectionName: <?php echo json_encode($collection_name); ?>,
        apiUrl: <?php echo json_encode($api_url ? $api_url . '/tts' : ''); ?>,
        baseApiUrl: <?php echo json_encode($api_url); ?>,
        language: <?php echo json_encode($language); ?>,
        provider: <?php echo json_encode($provider); ?>,
        model: <?php echo json_encode($model); ?>,
        introAudioUrl: <?php echo json_encode('intro_voice/' . $user_hash . '.mp3'); ?>,
        whisperLanguage: <?php
            // Map locale to Whisper language code
            $locale_prefix = strtolower(substr($language, 0, 2));
            $whisper_lang = 'english'; // default

            switch($locale_prefix) {
                case 'af': $whisper_lang = 'afrikaans'; break;
                case 'am': $whisper_lang = 'amharic'; break;
                case 'ar': $whisper_lang = 'arabic'; break;
                case 'as': $whisper_lang = 'assamese'; break;
                case 'az': $whisper_lang = 'azerbaijani'; break;
                case 'bg': $whisper_lang = 'bulgarian'; break;
                case 'bn': $whisper_lang = 'bengali'; break;
                case 'bs': $whisper_lang = 'bosnian'; break;
                case 'ca': $whisper_lang = 'catalan'; break;
                case 'cs': $whisper_lang = 'czech'; break;
                case 'cy': $whisper_lang = 'welsh'; break;
                case 'da': $whisper_lang = 'danish'; break;
                case 'de': $whisper_lang = 'german'; break;
                case 'el': $whisper_lang = 'greek'; break;
                case 'en': $whisper_lang = 'english'; break;
                case 'es': $whisper_lang = 'spanish'; break;
                case 'et': $whisper_lang = 'estonian'; break;
                case 'eu': $whisper_lang = 'basque'; break;
                case 'fa': $whisper_lang = 'persian'; break;
                case 'fi': $whisper_lang = 'finnish'; break;
                case 'fil': $whisper_lang = 'tagalog'; break;
                case 'fr': $whisper_lang = 'french'; break;
                case 'ga': $whisper_lang = 'irish'; break;
                case 'gl': $whisper_lang = 'galician'; break;
                case 'gu': $whisper_lang = 'gujarati'; break;
                case 'he': $whisper_lang = 'hebrew'; break;
                case 'hi': $whisper_lang = 'hindi'; break;
                case 'hr': $whisper_lang = 'croatian'; break;
                case 'hu': $whisper_lang = 'hungarian'; break;
                case 'hy': $whisper_lang = 'armenian'; break;
                case 'id': $whisper_lang = 'indonesian'; break;
                case 'is': $whisper_lang = 'icelandic'; break;
                case 'it': $whisper_lang = 'italian'; break;
                case 'ja': $whisper_lang = 'japanese'; break;
                case 'jv': $whisper_lang = 'javanese'; break;
                case 'ka': $whisper_lang = 'georgian'; break;
                case 'kk': $whisper_lang = 'kazakh'; break;
                case 'km': $whisper_lang = 'khmer'; break;
                case 'kn': $whisper_lang = 'kannada'; break;
                case 'ko': $whisper_lang = 'korean'; break;
                case 'lo': $whisper_lang = 'lao'; break;
                case 'lt': $whisper_lang = 'lithuanian'; break;
                case 'lv': $whisper_lang = 'latvian'; break;
                case 'mk': $whisper_lang = 'macedonian'; break;
                case 'ml': $whisper_lang = 'malayalam'; break;
                case 'mn': $whisper_lang = 'mongolian'; break;
                case 'mr': $whisper_lang = 'marathi'; break;
                case 'ms': $whisper_lang = 'malay'; break;
                case 'mt': $whisper_lang = 'maltese'; break;
                case 'my': $whisper_lang = 'myanmar'; break;
                case 'ne': $whisper_lang = 'nepali'; break;
                case 'nl': $whisper_lang = 'dutch'; break;
                case 'no': $whisper_lang = 'norwegian'; break;
                case 'pa': $whisper_lang = 'punjabi'; break;
                case 'pl': $whisper_lang = 'polish'; break;
                case 'ps': $whisper_lang = 'pashto'; break;
                case 'pt': $whisper_lang = 'portuguese'; break;
                case 'ro': $whisper_lang = 'romanian'; break;
                case 'ru': $whisper_lang = 'russian'; break;
                case 'si': $whisper_lang = 'sinhala'; break;
                case 'sk': $whisper_lang = 'slovak'; break;
                case 'sl': $whisper_lang = 'slovenian'; break;
                case 'so': $whisper_lang = 'somali'; break;
                case 'sq': $whisper_lang = 'albanian'; break;
                case 'sr': $whisper_lang = 'serbian'; break;
                case 'su': $whisper_lang = 'sundanese'; break;
                case 'sv': $whisper_lang = 'swedish'; break;
                case 'sw': $whisper_lang = 'swahili'; break;
                case 'ta': $whisper_lang = 'tamil'; break;
                case 'te': $whisper_lang = 'telugu'; break;
                case 'th': $whisper_lang = 'thai'; break;
                case 'tr': $whisper_lang = 'turkish'; break;
                case 'uk': $whisper_lang = 'ukrainian'; break;
                case 'ur': $whisper_lang = 'urdu'; break;
                case 'uz': $whisper_lang = 'uzbek'; break;
                case 'vi': $whisper_lang = 'vietnamese'; break;
                case 'zh': $whisper_lang = 'chinese'; break;
                case 'zu': $whisper_lang = 'zulu'; break;
                default: $whisper_lang = 'english'; break;
            }
            echo json_encode($whisper_lang);
        ?>,
        instruct: <?php
            // Render template with actual values
            $rendered_instruct = renderTemplate($prompt_template, [
                'language' => $language,
                'ai_name' => $ai_name,
                'ai_role' => $ai_role,
                'ai_communication_style' => $ai_communication_style,
                'ai_introduction' => $ai_introduction
            ], false); // Don't escape since we're using json_encode

            echo json_encode($rendered_instruct);
        ?>
    };

    // Initialize the application with the config
    import('./frame.js').then(module => {
        console.log('Module loaded:', module);
        console.log('Config:', config);
        const app = new module.AudioChatInterface(config);
        console.log('App instance created:', app);
        app.init();

        // Wait for loading screen to finish, then play intro audio
        // The loading screen typically takes around 500ms to hide
        setTimeout(() => {
            playIntroAudio();
        }, 600);
    }).catch(err => {
        console.error('Failed to load module:', err);
    });

    // Function to play intro audio with visualization
    function playIntroAudio() {
        const micBtn = document.getElementById('micBtn');
        const statusText = document.getElementById('statusText');
        const glowElement = document.getElementById('glow');
        // Add cache-busting parameter to force browser to load fresh audio
        const timestamp = new Date().getTime();
        const introAudio = new Audio(config.introAudioUrl + '?v=' + timestamp);

        // Disable mic button initially
        micBtn.disabled = true;
        micBtn.style.opacity = '0.5';
        micBtn.style.cursor = 'not-allowed';
        statusText.textContent = 'Playing introduction...';

        // Set up audio context and analyser for visualization
        let audioContext = null;
        let analyser = null;
        let dataArray = null;
        let animationId = null;

        // Function to enable mic button
        function enableMicButton() {
            micBtn.disabled = false;
            micBtn.style.opacity = '1';
            micBtn.style.cursor = 'pointer';
            statusText.textContent = 'Tap Mic to Start';
            stopVisualization();
        }

        // Function to stop visualization
        function stopVisualization() {
            if (animationId) {
                cancelAnimationFrame(animationId);
                animationId = null;
            }
            // Reset glow to default
            glowElement.style.height = '300px';
            glowElement.style.opacity = '0.5';
        }

        // Function to visualize audio
        function visualizeAudio() {
            if (!analyser || !dataArray || introAudio.paused || introAudio.ended) {
                return;
            }

            animationId = requestAnimationFrame(visualizeAudio);

            analyser.getByteFrequencyData(dataArray);

            let sum = 0;
            for (let i = 0; i < dataArray.length; i++) {
                sum += dataArray[i];
            }
            let averageVolume = sum / dataArray.length;

            const MIN_HEIGHT = 300;
            const MAX_HEIGHT = 600;
            const SENSITIVITY = 2;

            let targetHeight = MIN_HEIGHT;
            let targetOpacity = 0.5;

            if (averageVolume > 5) {
                const extraHeight = averageVolume * SENSITIVITY * 3;
                targetHeight = MIN_HEIGHT + extraHeight;
                if (targetHeight > MAX_HEIGHT) targetHeight = MAX_HEIGHT;

                targetOpacity = 0.5 + (averageVolume / 100);
                if (targetOpacity > 1) targetOpacity = 1;
            }

            glowElement.style.height = `${targetHeight}px`;
            glowElement.style.opacity = targetOpacity;
        }

        // Set up visualization when audio can play
        introAudio.addEventListener('canplay', () => {
            try {
                audioContext = new(window.AudioContext || window.webkitAudioContext)();
                const source = audioContext.createMediaElementSource(introAudio);
                analyser = audioContext.createAnalyser();
                analyser.fftSize = 256;
                const bufferLength = analyser.frequencyBinCount;
                dataArray = new Uint8Array(bufferLength);

                source.connect(analyser);
                analyser.connect(audioContext.destination);

                console.log('Intro audio visualization set up');
            } catch (error) {
                console.error('Error setting up intro visualization:', error);
            }
        }, {
            once: true
        });

        // Start visualization when audio plays
        introAudio.addEventListener('play', () => {
            console.log('Intro audio playing');
            if (analyser && dataArray) {
                visualizeAudio();
            }
        });

        // Play the intro audio
        introAudio.play().then(() => {
            console.log('Intro audio playback started');
        }).catch(error => {
            console.log('Intro audio not available or autoplay blocked:', error);
            // If audio fails to play, enable mic button immediately
            enableMicButton();
        });

        // Enable mic button when audio ends
        introAudio.addEventListener('ended', () => {
            console.log('Intro audio ended');
            enableMicButton();
        });

        // Handle audio errors
        introAudio.addEventListener('error', (e) => {
            console.log('Intro audio error (file may not exist):', e);
            // Enable mic button even if audio fails
            enableMicButton();
        });
    }
    </script>
</body>

</html>