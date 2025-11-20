# Tattle

AI-powered Text-to-Speech portal with RAG (Retrieval-Augmented Generation) capabilities, featuring document embedding, user management, and multiple TTS voice options.

## Features

### 🎙️ Advanced Text-to-Speech (TTS)

-   **Edge TTS Integration**: Leverages Microsoft's Edge TTS engine for high-quality, natural-sounding voice synthesis
-   **544+ Voice Samples**: Extensive library of voice options across multiple languages, accents, and regions
-   **Multi-language Support**: Generate speech in dozens of languages including English, Spanish, French, German, Chinese, Japanese, Arabic, and many more
-   **Voice Customization**: Choose from different voice personas, genders, and speaking styles to match your needs
-   **Real-time Generation**: Fast audio generation with streaming support for immediate playback

### 🧠 RAG (Retrieval-Augmented Generation) System

-   **Document Embedding**: Upload and process documents (PDFs, text files) to create searchable embeddings
-   **Vector Database Integration**: Store and retrieve document embeddings using vector similarity search
-   **Context-Aware Responses**: AI responses are grounded in your uploaded documents, reducing hallucinations
-   **Semantic Search**: Find relevant information across your document collection using natural language queries
-   **Collection Management**: Organize documents into named collections for better organization and retrieval
-   **Top-K Retrieval**: Configure how many relevant documents to retrieve for each query

### 🤖 Multiple LLM Provider Support

-   **Google Gemini**: Integration with Google's Gemini models for powerful language understanding
-   **Mistral AI**: Support for Mistral's open-source and commercial language models
-   **Flexible Provider Selection**: Switch between different LLM providers based on your needs and API availability
-   **Streaming Responses**: Real-time token streaming for faster perceived response times
-   **Custom System Prompts**: Configure AI behavior with custom instructions and personas

### 👥 User Management & Authentication

-   **Multi-user Support**: Fully-featured user system with individual accounts and settings
-   **Secure Authentication**: Password hashing using industry-standard bcrypt encryption
-   **Role-based Access Control**: Differentiate between regular users and superadmins
-   **User-specific Settings**: Each user can configure their own preferences and API keys
-   **Session Management**: Secure PHP session handling with proper timeout and security measures
-   **Encrypted API Keys**: User API keys are encrypted using AES-256 encryption before storage

### ⚙️ Admin Panel & Management

-   **Superadmin Interface**: Dedicated admin panel for system management and oversight
-   **User Administration**: Add, remove, and manage user accounts
-   **System Configuration**: Global settings management for TTS and AI providers
-   **Model Refresh**: Update available AI models and voice options
-   **Monitoring Tools**: Debug and monitor system performance and usage

### 🔄 Real-time Communication

-   **WebSocket Support**: Bi-directional real-time communication for interactive experiences
-   **Server-Sent Events (SSE)**: Efficient streaming of AI responses and status updates
-   **Progress Tracking**: Real-time feedback during document processing and AI generation
-   **Async Processing**: Non-blocking operations for better performance and user experience

### 🔐 Security & Encryption

-   **AES-256 Encryption**: Military-grade encryption for sensitive API keys
-   **Environment Variables**: Secure configuration management with no hardcoded secrets
-   **SQL Injection Protection**: Prepared statements and parameterized queries throughout
-   **XSS Prevention**: Input sanitization and output encoding
-   **CORS Configuration**: Configurable cross-origin resource sharing for production security

## Technology Stack

### Backend

-   **Python 3.x** with FastAPI
-   **PHP 7.4+** for frontend and authentication
-   **MySQL** for database

### Key Libraries

-   FastAPI & Uvicorn (API server)
-   edge-tts (Text-to-Speech)
-   Mistral AI & Google Gemini (LLM providers)
-   httpx (HTTP client)
-   Cryptography (AES-256 encryption)

## Prerequisites

-   PHP 7.4 or higher
-   Python 3.8 or higher
-   MySQL 5.7 or higher
-   Composer (optional, for PHP dependencies)
-   pip (Python package manager)

## Important Note

**⚠️ Embedding API Requirement:**

This application requires a custom embedding and vector database API to function properly. The application uses an external embedding service for the RAG (Retrieval-Augmented Generation) functionality.

**You need to create your own embedding and vector database API** to make this work. The application expects an API endpoint that:

-   Accepts document embeddings for storage
-   Performs vector similarity search
-   Returns relevant context for RAG queries

The default API endpoint is configured as `https://embedding.2ai.dev`, but you must deploy your own instance or use a compatible embedding service.

**Alternative Options:**

-   Build your own embedding API using libraries like FAISS, Pinecone, Weaviate, or Qdrant
-   Use existing vector database services and adapt the API calls
-   Implement a custom solution using sentence-transformers or similar embedding models

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/gitrhs/tattle
cd tattle
```

### 2. Database Setup

Create a MySQL database:

```sql
CREATE DATABASE tattle CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Import the database schema (if provided) or create the necessary tables.

### 3. PHP Configuration

Copy the database configuration template:

```bash
cp db_config.example.php db_config.php
```

Edit `db_config.php` with your actual database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_NAME', 'tattle');
```

### 4. Environment Variables

Copy the environment template:

```bash
cp .env.example .env
```

Edit `.env` and set the required variables:

```bash
# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_USER=your_database_user
DB_PASS=your_database_password
DB_NAME=tattle

# Generate a secure 32-character key
ENCRYPTION_SECRET_KEY=your_32_character_secret_key_here

# Get your embedding API key from your provider
EMBEDDING_API_KEY=your_embedding_api_key_here

# CORS - set specific origins in production
ALLOWED_ORIGINS=http://localhost,https://yourdomain.com
```

**IMPORTANT:** Generate a secure 32-character encryption key:

```bash
# On Linux/Mac:
openssl rand -base64 24

# Or use any 32-character random string
```

### 5. Python Dependencies

Install Python packages:

```bash
pip install -r requirements.txt
```

Or using a virtual environment (recommended):

```bash
python -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate
pip install -r requirements.txt
```

### 6. Set Environment Variables

**For Python (FastAPI):**

On Linux/Mac:

```bash
export ENCRYPTION_SECRET_KEY="your_32_character_key"
export EMBEDDING_API_KEY="your_embedding_api_key"
export ALLOWED_ORIGINS="http://localhost"
```

On Windows:

```cmd
set ENCRYPTION_SECRET_KEY=your_32_character_key
set EMBEDDING_API_KEY=your_embedding_api_key
set ALLOWED_ORIGINS=http://localhost
```

**For PHP:**

Ensure your web server (Apache/Nginx) or PHP-FPM can access the environment variables. You may need to:

-   Add them to your `.htaccess` or web server configuration
-   Use `putenv()` in a configuration file
-   Add them to your system environment

## Running the Application

### Start the FastAPI Backend

```bash
# Make sure environment variables are set first!
python main.py
```

Or with custom host/port:

```bash
uvicorn main:app --host 0.0.0.0 --port 8001 --reload
```

The API will be available at `http://localhost:8001`

### Start the PHP Frontend

If using XAMPP, MAMP, or similar:

1. Place the project in your web server's document root
2. Access via browser: `http://localhost/tattle/`

If using PHP's built-in server:

```bash
php -S localhost:8000
```

## Project Structure

```
tattle/
├── main.py                 # FastAPI backend server
├── db_config.php          # Database configuration (not in Git)
├── encrypt_helper.php     # API key encryption utilities
├── login.php              # User authentication
├── index.php              # Main application interface
├── superadmin.php         # Admin panel
├── frame.php              # TTS interface frame
├── requirements.txt       # Python dependencies
├── .env                   # Environment variables (not in Git)
├── .gitignore             # Git ignore rules
├── uploads/               # User uploads (not in Git)
├── intro_voice/           # Generated audio (not in Git)
└── voice_samples/         # TTS voice samples (large directory)
```

## API Documentation

Once the FastAPI server is running, visit:

-   Swagger UI: `http://localhost:8001/docs`
-   ReDoc: `http://localhost:8001/redoc`

## Common Issues

### Issue: "ENCRYPTION_SECRET_KEY environment variable is required"

**Solution:** Ensure you've set the environment variable before starting the application.

### Issue: Database connection failed

**Solution:** Check `db_config.php` credentials and ensure MySQL is running.

### Issue: CORS errors in browser

**Solution:** Update `ALLOWED_ORIGINS` in `.env` to include your frontend URL.

### Issue: Python module not found

**Solution:** Install dependencies: `pip install -r requirements.txt`

## Development vs Production

### Development Setup

-   Use `.env` file with local values
-   CORS can be set to `*` for testing
-   Database can use localhost with simple credentials
-   Use `--reload` flag with uvicorn

### Production Setup

-   Use system environment variables (not `.env` file)
-   Set specific CORS origins
-   Use strong database credentials
-   Enable HTTPS/SSL
-   Use production-grade WSGI server (gunicorn + uvicorn workers)
-   Set up proper logging and monitoring

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is licensed under the MIT License - see below for details:

```
MIT License

Copyright (c) 2025 Rafi Daffa Ramadhani

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

## Support

For issues and questions:

-   Create an issue in the GitHub repository
-   Contact: [Your contact information]

## Acknowledgments

-   Edge TTS for text-to-speech functionality
-   Mistral AI and Google Gemini for LLM capabilities
-   FastAPI framework
