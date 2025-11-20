# Rafidaffa Portal

AI-powered Text-to-Speech portal with RAG (Retrieval-Augmented Generation) capabilities, featuring document embedding, user management, and multiple TTS voice options.

## Features

- **Text-to-Speech (TTS)**: Edge TTS integration with 544+ voice samples across multiple languages
- **RAG System**: Document embedding and retrieval for context-aware AI responses
- **Multiple LLM Providers**: Support for Google Gemini, Mistral AI, and other providers
- **User Management**: Multi-user support with authentication and role-based access
- **Admin Panel**: Superadmin interface for user and system management
- **Real-time Streaming**: WebSocket and SSE support for streaming responses

## Technology Stack

### Backend
- **Python 3.x** with FastAPI
- **PHP 7.4+** for frontend and authentication
- **MySQL** for database

### Key Libraries
- FastAPI & Uvicorn (API server)
- edge-tts (Text-to-Speech)
- Mistral AI & Google Gemini (LLM providers)
- httpx (HTTP client)
- Cryptography (AES-256 encryption)

## Prerequisites

- PHP 7.4 or higher
- Python 3.8 or higher
- MySQL 5.7 or higher
- Composer (optional, for PHP dependencies)
- pip (Python package manager)

## Installation

### 1. Clone the Repository

```bash
git clone <repository-url>
cd "Rafidaffa portal"
```

### 2. Database Setup

Create a MySQL database:

```sql
CREATE DATABASE rafidaffa_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
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
define('DB_NAME', 'rafidaffa_portal');
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
DB_NAME=rafidaffa_portal

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
- Add them to your `.htaccess` or web server configuration
- Use `putenv()` in a configuration file
- Add them to your system environment

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
2. Access via browser: `http://localhost/Rafidaffa%20portal/`

If using PHP's built-in server:
```bash
php -S localhost:8000
```

## Project Structure

```
Rafidaffa portal/
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

## Security Considerations

### Critical Security Measures

1. **Never commit sensitive files:**
   - `.env` (contains secrets)
   - `db_config.php` (contains credentials)
   - `uploads/` (user data)

2. **Encryption Key:**
   - The `ENCRYPTION_SECRET_KEY` must be exactly 32 characters
   - Must be identical in both PHP and Python environments
   - Rotate this key if it's ever compromised

3. **API Keys:**
   - Store all API keys in environment variables only
   - Never hardcode API keys in source code
   - Rotate keys if exposed

4. **CORS Settings:**
   - In production, set specific allowed origins
   - Never use `ALLOWED_ORIGINS=*` in production

5. **Database:**
   - Use strong database passwords
   - Limit database user privileges
   - Enable SSL/TLS for database connections in production

### Recommended Production Setup

1. Use HTTPS for all connections
2. Enable PHP session security settings
3. Set up rate limiting on API endpoints
4. Regular security audits and dependency updates
5. Implement logging and monitoring
6. Use a reverse proxy (Nginx) in front of FastAPI

## API Documentation

Once the FastAPI server is running, visit:
- Swagger UI: `http://localhost:8001/docs`
- ReDoc: `http://localhost:8001/redoc`

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
- Use `.env` file with local values
- CORS can be set to `*` for testing
- Database can use localhost with simple credentials
- Use `--reload` flag with uvicorn

### Production Setup
- Use system environment variables (not `.env` file)
- Set specific CORS origins
- Use strong database credentials
- Enable HTTPS/SSL
- Use production-grade WSGI server (gunicorn + uvicorn workers)
- Set up proper logging and monitoring

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

[Specify your license here]

## Support

For issues and questions:
- Create an issue in the GitHub repository
- Contact: [Your contact information]

## Acknowledgments

- Edge TTS for text-to-speech functionality
- Mistral AI and Google Gemini for LLM capabilities
- FastAPI framework
