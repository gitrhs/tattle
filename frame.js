import { pipeline, env } from 'https://cdn.jsdelivr.net/npm/@huggingface/transformers@3.1.2';

// Configure environment
env.allowLocalModels = false;
env.allowRemoteModels = true;

/**
 * UIManager - Handles all UI updates and chat message management
 */
class UIManager {
    constructor() {
        this.loadingScreen = document.getElementById('loadingScreen');
        this.loadingText = document.getElementById('loadingText');
        this.mainContent = document.getElementById('mainContent');
        this.statusText = document.getElementById('statusText');
        this.chatContainer = document.getElementById('chatContainer');
        this.micBtn = document.getElementById('micBtn');
        this.glow = document.getElementById('glow');
    }

    updateLoadingText(text) {
        this.loadingText.textContent = text;
    }

    hideLoadingScreen() {
        setTimeout(() => {
            this.loadingScreen.style.display = 'none';
            this.mainContent.classList.add('loaded');
        }, 500);
    }

    showErrorLoading() {
        this.loadingText.textContent = 'Error loading model. Please refresh.';
    }

    updateStatus(text) {
        this.statusText.innerText = text;
    }

    setMicState(state) {
        switch (state) {
            case 'off':
                this.micBtn.classList.add('off');
                this.micBtn.classList.remove('recording');
                this.micBtn.querySelector('svg').style.fill = '#888';
                break;
            case 'listening':
                this.micBtn.classList.remove('off');
                this.micBtn.classList.remove('recording');
                this.micBtn.querySelector('svg').style.fill = '#2b6aff';
                break;
            case 'recording':
                this.micBtn.classList.add('recording');
                break;
        }
    }

    addMessage(text, type) {
        const emptyState = this.chatContainer.querySelector('.empty-state');
        if (emptyState) {
            emptyState.remove();
        }

        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}`;

        const bubbleDiv = document.createElement('div');
        bubbleDiv.className = 'message-bubble';
        bubbleDiv.textContent = text;

        messageDiv.appendChild(bubbleDiv);
        this.chatContainer.appendChild(messageDiv);

        this.chatContainer.scrollTop = this.chatContainer.scrollHeight;
    }

    createAIMessageBubble() {
        const emptyState = this.chatContainer.querySelector('.empty-state');
        if (emptyState) {
            emptyState.remove();
        }

        const messageDiv = document.createElement('div');
        messageDiv.className = 'message ai';

        const bubbleDiv = document.createElement('div');
        bubbleDiv.className = 'message-bubble';
        bubbleDiv.textContent = '';

        messageDiv.appendChild(bubbleDiv);
        this.chatContainer.appendChild(messageDiv);

        this.chatContainer.scrollTop = this.chatContainer.scrollHeight;

        return bubbleDiv;
    }

    updateAIMessage(bubble, text) {
        bubble.textContent = text;
        this.chatContainer.scrollTop = this.chatContainer.scrollHeight;
    }

    resetGlow(minHeight) {
        this.glow.style.height = `${minHeight}px`;
        this.glow.style.opacity = "0.5";
    }
}

/**
 * AudioVisualizer - Handles audio visualization for both recording and playback
 */
class AudioVisualizer {
    constructor(glowElement, config) {
        this.glow = glowElement;
        this.minHeight = config.MIN_HEIGHT;
        this.maxHeight = config.MAX_HEIGHT;
        this.sensitivity = config.SENSITIVITY;
        this.analyser = null;
        this.dataArray = null;
        this.animationId = null;
        this.isActive = false;
    }

    setup(analyser, dataArray) {
        this.analyser = analyser;
        this.dataArray = dataArray;
    }

    start(shouldBlock = null) {
        this.isActive = true;
        this.animate(shouldBlock);
    }

    stop() {
        this.isActive = false;
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
        }
        this.reset();
    }

    animate(shouldBlock = null) {
        if (!this.isActive) return;

        this.animationId = requestAnimationFrame(() => this.animate(shouldBlock));

        // Don't visualize when blocked
        if (shouldBlock && shouldBlock()) {
            this.reset();
            return;
        }

        if (!this.analyser || !this.dataArray) return;

        this.analyser.getByteFrequencyData(this.dataArray);

        let sum = 0;
        for (let i = 0; i < this.dataArray.length; i++) {
            sum += this.dataArray[i];
        }
        let averageVolume = sum / this.dataArray.length;

        let targetHeight = this.minHeight;
        let targetOpacity = 0.5;

        if (averageVolume > 5) {
            const extraHeight = averageVolume * this.sensitivity * 3;
            targetHeight = this.minHeight + extraHeight;
            if (targetHeight > this.maxHeight) targetHeight = this.maxHeight;

            targetOpacity = 0.5 + (averageVolume / 100);
            if (targetOpacity > 1) targetOpacity = 1;
        }

        this.glow.style.height = `${targetHeight}px`;
        this.glow.style.opacity = targetOpacity;
    }

    reset() {
        this.glow.style.height = `${this.minHeight}px`;
        this.glow.style.opacity = "0.5";
    }
}

/**
 * AudioRecorder - Handles audio recording
 */
class AudioRecorder {
    constructor() {
        this.mediaRecorder = null;
        this.audioChunks = [];
        this.stream = null;
    }

    setStream(stream) {
        this.stream = stream;
    }

    start(onStop) {
        this.audioChunks = [];
        this.mediaRecorder = new MediaRecorder(this.stream);

        this.mediaRecorder.ondataavailable = (event) => {
            this.audioChunks.push(event.data);
        };

        this.mediaRecorder.onstop = async () => {
            if (this.audioChunks.length > 0) {
                const audioBlob = new Blob(this.audioChunks, { type: 'audio/wav' });
                if (audioBlob.size > 1000) {
                    await onStop(audioBlob);
                } else {
                    console.log('Audio too short, skipping transcription');
                }
            }
        };

        this.mediaRecorder.start();
    }

    stop() {
        if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
            this.mediaRecorder.stop();
            return true;
        }
        return false;
    }

    isRecording() {
        return this.mediaRecorder && this.mediaRecorder.state !== 'inactive';
    }

    cleanup() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
        }
    }
}

/**
 * VoiceActivityDetector - Detects when user is speaking
 */
class VoiceActivityDetector {
    constructor(config, callbacks) {
        this.silenceThreshold = config.SILENCE_THRESHOLD;
        this.silenceFramesRequired = config.SILENCE_FRAMES_REQUIRED;
        this.bufferDelay = config.BUFFER_DELAY;
        this.callbacks = callbacks;
        this.vadAudioContext = null;
        this.scriptProcessor = null;
    }

    start(stream) {
        this.stop();

        this.vadAudioContext = new AudioContext();
        const vadAnalyser = this.vadAudioContext.createAnalyser();
        const microphone = this.vadAudioContext.createMediaStreamSource(stream);
        this.scriptProcessor = this.vadAudioContext.createScriptProcessor(2048, 1, 1);

        vadAnalyser.smoothingTimeConstant = 0.8;
        vadAnalyser.fftSize = 1024;

        microphone.connect(vadAnalyser);
        vadAnalyser.connect(this.scriptProcessor);
        this.scriptProcessor.connect(this.vadAudioContext.destination);

        let consecutiveSilenceFrames = 0;
        let recordingStartTime = 0;

        this.scriptProcessor.onaudioprocess = () => {
            if (!this.callbacks.shouldProcess()) {
                return;
            }

            const array = new Uint8Array(vadAnalyser.frequencyBinCount);
            vadAnalyser.getByteFrequencyData(array);

            let values = 0;
            for (let i = 0; i < array.length; i++) {
                values += array[i];
            }
            const volume = Math.round(values / array.length);

            const dB = volume === 0 ? -100 : 20 * Math.log10(volume / 255);

            if (dB > this.silenceThreshold) {
                if (this.callbacks.shouldStartRecording()) {
                    console.log('Speech detected, starting recording');
                    this.callbacks.onSpeechStart();
                    recordingStartTime = Date.now();
                }
                consecutiveSilenceFrames = 0;
            } else {
                if (this.callbacks.isSpeaking() && Date.now() - recordingStartTime > this.bufferDelay) {
                    consecutiveSilenceFrames++;

                    if (consecutiveSilenceFrames >= this.silenceFramesRequired) {
                        console.log('Silence detected, stopping recording');
                        this.callbacks.onSpeechEnd();
                        consecutiveSilenceFrames = 0;
                    }
                }
            }
        };
    }

    stop() {
        try {
            if (this.scriptProcessor) {
                this.scriptProcessor.disconnect();
            }
            if (this.vadAudioContext && this.vadAudioContext.state !== 'closed') {
                this.vadAudioContext.close();
            }
        } catch (e) {
            console.error('Error stopping VAD detector:', e);
        }
    }
}

/**
 * TranscriptionService - Handles Whisper transcription
 */
class TranscriptionService {
    constructor(config) {
        this.transcriber = null;
        this.config = config;
    }

    async loadModel(onProgress) {
        try {
            this.transcriber = await pipeline('automatic-speech-recognition', 'Xenova/whisper-base', {
                progress_callback: onProgress
            });
        } catch (err) {
            console.error('Error loading model:', err);
            throw err;
        }
    }

    async transcribe(audioBlob) {
        const arrayBuffer = await audioBlob.arrayBuffer();

        const transcribeAudioContext = new AudioContext({ sampleRate: 16000 });
        const audioBuffer = await transcribeAudioContext.decodeAudioData(arrayBuffer);

        let audio;
        if (audioBuffer.numberOfChannels === 2) {
            const left = audioBuffer.getChannelData(0);
            const right = audioBuffer.getChannelData(1);
            audio = new Float32Array(left.length);
            for (let i = 0; i < left.length; i++) {
                audio[i] = (left[i] + right[i]) / 2;
            }
        } else {
            audio = audioBuffer.getChannelData(0);
        }

        const result = await this.transcriber(audio, {
            language: this.config.whisperLanguage,
            task: 'transcribe',
        });

        if (result && result.text) {
            const transcribedText = result.text.trim();
            if (transcribedText && transcribedText !== '[BLANK_AUDIO]' && transcribedText.length > 0) {
                return transcribedText;
            }
        }

        return null;
    }
}

/**
 * APIService - Handles WebSocket communication with backend
 */
class APIService {
    constructor(config, callbacks) {
        this.config = config;
        this.callbacks = callbacks;
        this.ws = null;
    }

    async sendQuery(query) {
        return new Promise((resolve, reject) => {
            try {
                if (!this.config.baseApiUrl) {
                    throw new Error('API URL is not configured');
                }

                const wsUrl = this.config.baseApiUrl.replace('http://', 'ws://').replace('https://', 'wss://') + '/ws/tts';
                console.log('Connecting to WebSocket:', wsUrl);

                this.ws = new WebSocket(wsUrl);

                let audioChunksData = [];
                let aiResponseText = '';

                this.ws.onopen = () => {
                    console.log('✓ WebSocket connected successfully');
                    this.callbacks.onConnected();

                    // Randomly select an auth key from the array
                    let selectedAuthKey;
                    if (Array.isArray(this.config.authKey) && this.config.authKey.length > 0) {
                        const randomIndex = Math.floor(Math.random() * this.config.authKey.length);
                        selectedAuthKey = this.config.authKey[randomIndex];
                        console.log(`Using auth key ${randomIndex + 1}/${this.config.authKey.length}`);
                    } else {
                        // Fallback to single key (backward compatibility)
                        selectedAuthKey = this.config.authKey;
                    }

                    const requestData = {
                        query: query,
                        user_hash: this.config.userHash,
                        instruct: this.config.instruct,
                        auth_key: selectedAuthKey,
                        voice: this.config.voice,
                        collection_name: this.config.collectionName,
                        top_k: 5,
                        provider: this.config.provider,
                        model: this.config.model
                    };

                    console.log('Sending request:', requestData);
                    this.ws.send(JSON.stringify(requestData));
                };

                this.ws.onmessage = async (event) => {
                    try {
                        const data = JSON.parse(event.data);
                        console.log('Received message type:', data.type, data);

                        if (data.type === 'status') {
                            const message = data.message || data.status;
                            this.callbacks.onStatus(message);

                            if (data.status === 'completed') {
                                console.log('Processing completed');
                                this.callbacks.onComplete();
                                this.ws.close();
                            }
                        } else if (data.type === 'ai_response') {
                            aiResponseText += data.content;
                            console.log('AI response chunk received, total length:', aiResponseText.length);
                            this.callbacks.onAIResponse(aiResponseText);
                        } else if (data.type === 'audio') {
                            console.log('Audio chunk received, size:', data.data.length);
                            audioChunksData.push(data.data);
                            this.callbacks.onAudioChunk(audioChunksData);
                        } else if (data.type === 'word_boundary') {
                            console.log('Word boundary:', data.text);
                        }
                    } catch (parseError) {
                        console.error('Error parsing WebSocket message:', parseError, event.data);
                    }
                };

                this.ws.onclose = () => {
                    console.log('WebSocket closed');
                    console.log('Final check - audio chunks:', audioChunksData.length);
                    resolve();
                };

                this.ws.onerror = (error) => {
                    console.error('WebSocket error:', error);
                    this.callbacks.onError();
                    reject(error);
                };
            } catch (err) {
                console.error('API error:', err);
                this.callbacks.onError();
                reject(err);
            }
        });
    }

    close() {
        if (this.ws) {
            this.ws.close();
        }
    }
}

/**
 * AudioPlayer - Handles progressive audio playback
 */
class AudioPlayer {
    constructor(config, visualizer, callbacks) {
        this.config = config;
        this.visualizer = visualizer;
        this.callbacks = callbacks;
        this.currentAudio = null;
        this.visualAudioContext = null;
        this.visualAnalyser = null;
        this.visualDataArray = null;
        this.visualAnimationId = null;
        this.updateInterval = null;
        this.audioChunksData = [];
        this.isComplete = false;
        this.audioStarted = false;
    }

    addAudioChunk(chunksData) {
        this.audioChunksData = chunksData;

        const BUFFER_CHUNKS = 10;
        if (!this.audioStarted && this.audioChunksData.length >= BUFFER_CHUNKS) {
            console.log(`Starting audio playback with ${this.audioChunksData.length} chunks buffered`);
            this.audioStarted = true;
            this.startPlayback();
        }
    }

    markComplete() {
        this.isComplete = true;
    }

    startPlayback() {
        console.log('Starting progressive audio playback');
        this.callbacks.onPlaybackStart();

        let lastChunkCount = 0;

        const updateAudio = () => {
            if (this.audioChunksData.length === lastChunkCount && !this.isComplete) {
                return;
            }

            lastChunkCount = this.audioChunksData.length;
            console.log('Updating audio with', this.audioChunksData.length, 'chunks');

            try {
                const currentTime = this.currentAudio ? this.currentAudio.currentTime : 0;

                if (this.currentAudio) {
                    const oldUrl = this.currentAudio.src;
                    this.currentAudio.pause();
                    this.currentAudio = null;
                    URL.revokeObjectURL(oldUrl);
                }

                const concatenatedBase64 = this.audioChunksData.join('');
                const binaryString = atob(concatenatedBase64);
                const bytes = new Uint8Array(binaryString.length);
                for (let i = 0; i < binaryString.length; i++) {
                    bytes[i] = binaryString.charCodeAt(i);
                }

                const audioBlob = new Blob([bytes], { type: 'audio/mpeg' });
                const audioUrl = URL.createObjectURL(audioBlob);

                this.currentAudio = new Audio(audioUrl);
                this.currentAudio.currentTime = currentTime;

                this.setupVisualization();

                this.currentAudio.onplay = () => {
                    console.log('Audio playing');
                    this.callbacks.onPlay();
                    if (this.visualAnalyser && this.visualDataArray) {
                        this.visualizePlayback();
                    }
                };

                this.currentAudio.onended = () => {
                    console.log('Audio ended');

                    if (this.isComplete) {
                        console.log('Audio playback fully completed');
                        this.cleanup();
                        this.callbacks.onPlaybackEnd();
                    } else {
                        console.log('Waiting for more chunks...');
                        setTimeout(updateAudio, 100);
                    }
                };

                this.currentAudio.onerror = (e) => {
                    console.error('Audio playback error:', e);
                    this.callbacks.onError();
                    this.cleanup();
                };

                this.currentAudio.play().catch(err => {
                    console.error('Play error:', err);
                });

            } catch (error) {
                console.error('Error updating audio:', error);
            }
        };

        updateAudio();

        this.updateInterval = setInterval(() => {
            if (this.isComplete && this.audioChunksData.length === lastChunkCount) {
                clearInterval(this.updateInterval);
                return;
            }
            updateAudio();
        }, 500);
    }

    setupVisualization() {
        try {
            if (!this.visualAudioContext) {
                this.visualAudioContext = new (window.AudioContext || window.webkitAudioContext)();
            }

            const source = this.visualAudioContext.createMediaElementSource(this.currentAudio);

            if (!this.visualAnalyser) {
                this.visualAnalyser = this.visualAudioContext.createAnalyser();
                this.visualAnalyser.fftSize = 256;
                const bufferLength = this.visualAnalyser.frequencyBinCount;
                this.visualDataArray = new Uint8Array(bufferLength);
            }

            source.connect(this.visualAnalyser);
            this.visualAnalyser.connect(this.visualAudioContext.destination);

            console.log('Audio visualization connected');
        } catch (vizError) {
            console.error('Error setting up visualization:', vizError);
        }
    }

    visualizePlayback() {
        if (!this.visualAnalyser || !this.visualDataArray || !this.currentAudio ||
            this.currentAudio.paused || this.currentAudio.ended) {
            return;
        }

        this.visualAnimationId = requestAnimationFrame(() => this.visualizePlayback());

        this.visualAnalyser.getByteFrequencyData(this.visualDataArray);

        let sum = 0;
        for (let i = 0; i < this.visualDataArray.length; i++) {
            sum += this.visualDataArray[i];
        }
        let averageVolume = sum / this.visualDataArray.length;

        let targetHeight = this.config.MIN_HEIGHT;
        let targetOpacity = 0.5;

        if (averageVolume > 5) {
            const extraHeight = averageVolume * this.config.SENSITIVITY * 3;
            targetHeight = this.config.MIN_HEIGHT + extraHeight;
            if (targetHeight > this.config.MAX_HEIGHT) targetHeight = this.config.MAX_HEIGHT;

            targetOpacity = 0.5 + (averageVolume / 100);
            if (targetOpacity > 1) targetOpacity = 1;
        }

        this.visualizer.glow.style.height = `${targetHeight}px`;
        this.visualizer.glow.style.opacity = targetOpacity;
    }

    cleanup() {
        if (this.visualAnimationId) {
            cancelAnimationFrame(this.visualAnimationId);
        }
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
        if (this.visualAudioContext && this.visualAudioContext.state !== 'closed') {
            this.visualAudioContext.close();
        }
    }

    reset() {
        this.cleanup();
        this.audioChunksData = [];
        this.isComplete = false;
        this.audioStarted = false;
    }
}

/**
 * AudioChatInterface - Main orchestrator class
 */
class AudioChatInterface {
    constructor(config) {
        this.config = config;

        // State flags
        this.isListening = false;
        this.isSpeaking = false;
        this.isProcessing = false;
        this.isAIPlaying = false;

        // Initialize managers
        this.ui = new UIManager();
        this.visualizer = new AudioVisualizer(this.ui.glow, {
            MIN_HEIGHT: 80,
            MAX_HEIGHT: 500,
            SENSITIVITY: 2
        });
        this.recorder = new AudioRecorder();
        this.transcriptionService = new TranscriptionService(config);
        this.audioPlayer = null;
        this.vad = null;

        // Audio context
        this.audioContext = null;
        this.analyser = null;
        this.dataArray = null;

        // Bind event handlers
        this.ui.micBtn.addEventListener('click', () => this.handleMicClick());

        // Current AI message bubble for live updates
        this.currentAIBubble = null;
    }

    async init() {
        await this.loadModel();
    }

    async loadModel() {
        try {
            await this.transcriptionService.loadModel((progress) => {
                if (progress.status === 'downloading') {
                    const percent = Math.round(progress.progress);
                    this.ui.updateLoadingText(`Loading ${percent}%`);
                } else if (progress.status === 'loading') {
                    this.ui.updateLoadingText('Loading model...');
                } else if (progress.status === 'ready') {
                    this.ui.updateLoadingText('Loading 100%');
                }
            });

            this.ui.hideLoadingScreen();
        } catch (err) {
            console.error('Error loading model:', err);
            this.ui.showErrorLoading();
        }
    }

    async handleMicClick() {
        if (!this.isListening) {
            await this.startListening();
        } else {
            this.stopListening();
        }
    }

    async startListening() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });

            this.recorder.setStream(stream);
            this.startAudioProcessing(stream);

            this.isListening = true;
            this.ui.setMicState('listening');
            this.ui.updateStatus("Listening...");

            this.startVAD(stream);
        } catch (err) {
            console.error("Error accessing mic:", err);
            alert("Microphone access denied or not supported on this browser context.");
        }
    }

    stopListening() {
        this.isListening = false;
        this.isSpeaking = false;

        if (this.vad) {
            this.vad.stop();
            this.vad = null;
        }

        this.recorder.stop();
        this.recorder.cleanup();

        if (this.audioContext) {
            this.audioContext.close();
        }

        this.visualizer.stop();

        this.ui.setMicState('off');
        if (!this.isProcessing) {
            this.ui.updateStatus("Tap Mic to Start");
        }
        this.ui.resetGlow(80);
    }

    startAudioProcessing(stream) {
        this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const source = this.audioContext.createMediaStreamSource(stream);

        this.analyser = this.audioContext.createAnalyser();
        this.analyser.fftSize = 256;
        source.connect(this.analyser);

        const bufferLength = this.analyser.frequencyBinCount;
        this.dataArray = new Uint8Array(bufferLength);

        this.visualizer.setup(this.analyser, this.dataArray);
        this.visualizer.start(() => this.isProcessing || this.isAIPlaying);
    }

    startVAD(stream) {
        this.vad = new VoiceActivityDetector(
            {
                SILENCE_THRESHOLD: -30,
                SILENCE_FRAMES_REQUIRED: 15,
                BUFFER_DELAY: 1000
            },
            {
                shouldProcess: () => !this.isProcessing && !this.isAIPlaying && this.isListening,
                shouldStartRecording: () => !this.isProcessing && !this.isSpeaking,
                isSpeaking: () => this.isSpeaking,
                onSpeechStart: () => {
                    this.isSpeaking = true;
                    this.startRecording();
                },
                onSpeechEnd: () => {
                    this.stopRecording();
                }
            }
        );

        this.vad.start(stream);
    }

    startRecording() {
        if (this.isProcessing) {
            console.log('Cannot start recording while processing');
            return;
        }

        this.ui.updateStatus("Recording...");
        this.ui.setMicState('recording');

        this.recorder.start(async (audioBlob) => {
            if (audioBlob) {
                this.isProcessing = true;
                this.isSpeaking = false;
                await this.handleTranscription(audioBlob);
            } else {
                this.isSpeaking = false;
                if (this.isListening) {
                    this.ui.updateStatus("Listening...");
                }
            }
        });
    }

    stopRecording() {
        if (this.recorder.stop()) {
            this.ui.setMicState('listening');
        }
    }

    async handleTranscription(audioBlob) {
        try {
            this.ui.updateStatus("Transcribing...");

            const transcribedText = await this.transcriptionService.transcribe(audioBlob);

            if (transcribedText) {
                this.ui.addMessage(transcribedText, 'user');
                await this.sendQueryToAPI(transcribedText);
            } else {
                console.log('Blank or invalid transcription, skipping');
            }

            this.isProcessing = false;

            if (this.isListening) {
                this.ui.updateStatus("Listening...");
            } else {
                this.ui.updateStatus("Tap Mic to Start");
            }
        } catch (err) {
            console.error('Transcription error:', err);
            this.isProcessing = false;
            this.ui.updateStatus(this.isListening ? "Listening..." : "Error - Tap to Retry");
        }
    }

    async sendQueryToAPI(query) {
        this.currentAIBubble = null;

        this.audioPlayer = new AudioPlayer(
            { MIN_HEIGHT: 80, MAX_HEIGHT: 500, SENSITIVITY: 2 },
            this.visualizer,
            {
                onPlaybackStart: () => {
                    this.ui.updateStatus("Playing response...");
                },
                onPlay: () => {
                    this.isAIPlaying = true;
                },
                onPlaybackEnd: () => {
                    this.isAIPlaying = false;
                    if (this.isListening) {
                        this.ui.updateStatus("Listening...");
                    } else {
                        this.ui.updateStatus("Tap Mic to Start");
                    }
                    this.ui.resetGlow(80);
                },
                onError: () => {
                    this.isAIPlaying = false;
                    this.ui.updateStatus("Audio error");
                    this.ui.resetGlow(80);
                }
            }
        );

        const apiService = new APIService(this.config, {
            onConnected: () => {
                this.ui.updateStatus("Connected");
            },
            onStatus: (message) => {
                // Replace backend messages with custom frontend text
                const displayMessage = message.replace(/Searching embeddings\.\.\./gi, 'Thinking...');
                this.ui.updateStatus(displayMessage);
            },
            onAIResponse: (text) => {
                if (!this.currentAIBubble) {
                    this.currentAIBubble = this.ui.createAIMessageBubble();
                }
                this.ui.updateAIMessage(this.currentAIBubble, text);
            },
            onAudioChunk: (chunksData) => {
                this.audioPlayer.addAudioChunk(chunksData);
            },
            onComplete: () => {
                this.audioPlayer.markComplete();
            },
            onError: () => {
                this.ui.updateStatus("Connection error");
                this.isAIPlaying = false;
            }
        });

        try {
            this.ui.updateStatus("Connecting...");
            await apiService.sendQuery(query);
        } catch (err) {
            console.error('API error:', err);
            this.ui.updateStatus("Error - Tap to Retry");
            this.isAIPlaying = false;
        }
    }
}

// Export for use
export { AudioChatInterface };
