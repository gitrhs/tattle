from fastapi import FastAPI, HTTPException, WebSocket, WebSocketDisconnect
from fastapi.responses import FileResponse, JSONResponse, StreamingResponse
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import edge_tts
import asyncio
import os
import uuid
from typing import Optional, Dict
import uvicorn
import httpx
from mistralai import Mistral
from google import genai
import json
import base64
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
from cryptography.hazmat.backends import default_backend

app = FastAPI(title="EdgeTTS API", version="1.0.0")

# CORS Configuration
# Load allowed origins from environment variable
# Format: comma-separated list, e.g., "http://localhost,https://yourdomain.com"
ALLOWED_ORIGINS = os.getenv("ALLOWED_ORIGINS", "*")
if ALLOWED_ORIGINS == "*":
    origins = ["*"]
else:
    origins = [origin.strip() for origin in ALLOWED_ORIGINS.split(",")]

app.add_middleware(
    CORSMiddleware,
    allow_origins=origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Create temp directory for audio files
TEMP_DIR = "/tmp/tts_audio"
os.makedirs(TEMP_DIR, exist_ok=True)

# Secret key for encryption/decryption
# IMPORTANT: This must match the SECRET_KEY in your PHP frontend
# Load from environment variable (no default for security)
SECRET_KEY = os.getenv("ENCRYPTION_SECRET_KEY")
if not SECRET_KEY:
    raise ValueError("ENCRYPTION_SECRET_KEY environment variable is required")

# Embedding API configuration
EMBEDDING_API_KEY = os.getenv("EMBEDDING_API_KEY")
if not EMBEDDING_API_KEY:
    raise ValueError("EMBEDDING_API_KEY environment variable is required")
EMBEDDING_API_URL = os.getenv("EMBEDDING_API_URL", "https://embedding.2ai.dev")

# Helper function to decrypt auth_key
def decrypt_auth_key(encrypted_data: str) -> Dict[str, str]:
    """
    Decrypt the auth_key to extract embedding_api_key and llm_api_key

    Args:
        encrypted_data: Base64 encoded encrypted data from PHP

    Returns:
        Dictionary with 'embedding_api_key' and 'llm_api_key'
    """
    try:
        # Decode base64
        encrypted_bytes = base64.b64decode(encrypted_data)

        # Extract IV (first 16 bytes for AES)
        iv_length = 16  # AES block size
        iv = encrypted_bytes[:iv_length]
        encrypted_payload = encrypted_bytes[iv_length:]

        # Create cipher
        cipher = Cipher(
            algorithms.AES(SECRET_KEY.encode()[:32]),  # Ensure 32 bytes
            modes.CBC(iv),
            backend=default_backend()
        )

        # Decrypt
        decryptor = cipher.decryptor()
        decrypted_padded = decryptor.update(encrypted_payload) + decryptor.finalize()

        # Remove PKCS7 padding
        padding_length = decrypted_padded[-1]
        decrypted = decrypted_padded[:-padding_length]

        # Parse JSON
        keys_dict = json.loads(decrypted.decode('utf-8'))

        # Validate required keys
        if 'embedding_api_key' not in keys_dict or 'llm_api_key' not in keys_dict:
            raise ValueError("Invalid auth_key format: missing required keys")

        return keys_dict

    except Exception as e:
        print(f"❌ DECRYPTION ERROR: {str(e)}", flush=True)
        import traceback
        print(traceback.format_exc(), flush=True)
        raise HTTPException(
            status_code=400,
            detail=f"Failed to decrypt auth_key: {str(e)}"
        )

# Background task to delete file after delay
async def delete_file_after_delay(filepath: str, delay_seconds: int = 600):
    """Delete file after specified delay (default 10 minutes = 600 seconds)"""
    await asyncio.sleep(delay_seconds)
    try:
        if os.path.exists(filepath):
            os.remove(filepath)
            print(f"Deleted audio file: {filepath}")
    except Exception as e:
        print(f"Error deleting file {filepath}: {e}")

# Helper function to format SSE messages
def format_sse(data: dict, event: str = None) -> str:
    """Format data as Server-Sent Event"""
    message = ""
    if event:
        message += f"event: {event}\n"
    message += f"data: {json.dumps(data)}\n\n"
    return message

# Helper function to call LLM (non-streaming)
def call_llm_complete(provider: str, model: str, api_key: str, system_prompt: str, user_prompt: str) -> str:
    """Call LLM provider and return complete response"""
    if provider.lower() == "mistral":
        with Mistral(api_key=api_key) as mistral:
            chat_response = mistral.chat.complete(
                model=model,
                messages=[
                    {"role": "system", "content": system_prompt},
                    {"role": "user", "content": user_prompt}
                ]
            )
            return chat_response.choices[0].message.content

    elif provider.lower() == "google":
        client = genai.Client(api_key=api_key)
        response = client.models.generate_content(
            model=model,
            contents=[
                {"role": "user", "parts": [{"text": f"{system_prompt}\n\n{user_prompt}"}]}
            ]
        )
        return response.text

    elif provider.lower() == "z.ai":
        with httpx.Client() as client:
            response = client.post(
                "https://api.z.ai/api/paas/v4/chat/completions",
                headers={
                    "Authorization": f"Bearer {api_key}",
                    "Content-Type": "application/json"
                },
                json={
                    "model": model,
                    "thinking": {"type": "disabled"},
                    "messages": [
                        {"role": "system", "content": system_prompt},
                        {"role": "user", "content": user_prompt}
                    ]
                },
                timeout=30.0
            )
            response.raise_for_status()
            response_data = response.json()
            return response_data["choices"][0]["message"]["content"]

    else:
        raise ValueError(f"Unsupported provider: {provider}. Use 'mistral', 'google', or 'z.ai'")

# Helper function to call LLM (streaming)
def call_llm_stream(provider: str, model: str, api_key: str, system_prompt: str, user_prompt: str):
    """Call LLM provider and return streaming response"""
    if provider.lower() == "mistral":
        with Mistral(api_key=api_key) as mistral:
            chat_stream = mistral.chat.stream(
                model=model,
                messages=[
                    {"role": "system", "content": system_prompt},
                    {"role": "user", "content": user_prompt}
                ]
            )
            for chunk in chat_stream:
                if chunk.data.choices[0].delta.content:
                    yield chunk.data.choices[0].delta.content

    elif provider.lower() == "google":
        client = genai.Client(api_key=api_key)
        stream = client.models.generate_content_stream(
            model=model,
            contents=[
                {"role": "user", "parts": [{"text": f"{system_prompt}\n\n{user_prompt}"}]}
            ]
        )
        for chunk in stream:
            if hasattr(chunk, 'text') and chunk.text:
                yield chunk.text

    elif provider.lower() == "z.ai":
        with httpx.Client() as client:
            with client.stream(
                "POST",
                "https://api.z.ai/api/paas/v4/chat/completions",
                headers={
                    "Authorization": f"Bearer {api_key}",
                    "Content-Type": "application/json"
                },
                json={
                    "model": model,
                    "thinking": {"type": "disabled"},
                    "messages": [
                        {"role": "system", "content": system_prompt},
                        {"role": "user", "content": user_prompt}
                    ],
                    "stream": True
                },
                timeout=30.0
            ) as response:
                response.raise_for_status()
                for line in response.iter_lines():
                    if line.strip():
                        # Parse SSE format: "data: {json}"
                        if line.startswith("data: "):
                            data_str = line[6:]  # Remove "data: " prefix
                            if data_str.strip() == "[DONE]":
                                break
                            try:
                                chunk_data = json.loads(data_str)
                                if "choices" in chunk_data and len(chunk_data["choices"]) > 0:
                                    delta = chunk_data["choices"][0].get("delta", {})
                                    content = delta.get("content", "")
                                    if content:
                                        yield content
                            except json.JSONDecodeError:
                                continue

    else:
        raise ValueError(f"Unsupported provider: {provider}. Use 'mistral', 'google', or 'z.ai'")

class TTSRequest(BaseModel):
    text: str
    voice: str = "en-HK-SamNeural"
    rate: str = "+10%"
    pitch: str = "-18Hz"
    volume: str = "+0%"

class TTSWithRAGRequest(BaseModel):
    query: str
    user_hash: str
    instruct: str
    auth_key: str  # Encrypted auth key containing both embedding and LLM API keys
    collection_name: str
    top_k: int
    voice: str = "en-HK-SamNeural"
    # LLM provider settings
    provider: str  # "mistral", "google", or "z.ai"
    model: str  # e.g., "mistral-3b-latest", "gemma-2-9b-it"

class VoiceListResponse(BaseModel):
    voices: list

@app.get("/")
async def root():
    return {
        "message": "EdgeTTS API is running",
        "endpoints": {
            "POST /synthesize": "Convert text to speech",
            "POST /synthesize/stream": "Convert text to speech with SSE",
            "WS /ws/synthesize": "Stream audio chunks via WebSocket",
            "POST /tts": "TTS with RAG",
            "POST /tts/stream": "TTS with RAG using SSE",
            "WS /ws/tts": "Stream TTS with RAG via WebSocket",
            "GET /voices": "List available voices",
            "GET /health": "Health check"
        }
    }

@app.get("/health")
async def health_check():
    return {"status": "healthy"}

@app.post("/tts")
async def tts_with_rag(request: TTSWithRAGRequest):
    try:
        # Decrypt auth_key to get API keys
        keys = decrypt_auth_key(request.auth_key)
        embedding_api_key = keys['embedding_api_key']
        llm_api_key = keys['llm_api_key']

        # Step 1: Call the embedding search API
        async with httpx.AsyncClient() as client:
            search_response = await client.post(
                f"{EMBEDDING_API_URL}/search",
                json={
                    "api_key": EMBEDDING_API_KEY,
                    "query": request.query,
                    "user_hash": request.user_hash,
                    "collection_name": request.collection_name,
                    "top_k": request.top_k
                },
                timeout=30.0
            )
            search_response.raise_for_status()
            search_data = search_response.json()

        # Step 2: Call LLM with the instruction and search results
        ai_response = call_llm_complete(
            provider=request.provider,
            model=request.model,
            api_key=llm_api_key,
            system_prompt=request.instruct,
            user_prompt=f"Query: {request.query}\n\nSearch Results: {search_data}"
        )

        # Step 3: Synthesize speech from AI response
        filename = f"{uuid.uuid4()}.mp3"
        filepath = os.path.join(TEMP_DIR, filename)

        communicate = edge_tts.Communicate(
            text=ai_response,
            voice=request.voice,
            rate="+0%",
            pitch="+0Hz"
        )

        await communicate.save(filepath)

        # Schedule file deletion after 10 minutes
        asyncio.create_task(delete_file_after_delay(filepath, 600))

        # Generate audio URL (adjust base URL as needed)
        audio_url = f"/audio/{filename}"

        # Filter documents with score > 0.4
        document_urls = []
        if "results" in search_data:
            document_urls = [
                doc.get("metadata", {}).get("url", "")
                for doc in search_data["results"]
                if doc.get("score", 0) > 0.4 and doc.get("metadata", {}).get("url")
            ]

        return JSONResponse({
            "status": "success",
            "query": request.query,
            "ai_response": ai_response,
            "document_url": document_urls,
            "audio_url": audio_url
        })

    except httpx.HTTPError as e:
        raise HTTPException(status_code=500, detail=f"Error calling embedding API: {str(e)}")
    except Exception as e:
        import traceback
        print(f"ERROR: {str(e)}")
        print(traceback.format_exc())
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/tts/stream")
async def tts_with_rag_stream(request: TTSWithRAGRequest):
    """Stream TTS with RAG using Server-Sent Events"""
    # Decrypt auth_key to get API keys
    keys = decrypt_auth_key(request.auth_key)
    embedding_api_key = keys['embedding_api_key']
    llm_api_key = keys['llm_api_key']

    async def event_generator():
        try:
            # Step 1: Call the embedding search API
            yield format_sse({"status": "searching", "message": "Searching embeddings..."}, "progress")

            async with httpx.AsyncClient() as client:
                search_response = await client.post(
                    f"{EMBEDDING_API_URL}/search",
                    json={
                        "api_key": EMBEDDING_API_KEY,
                        "query": request.query,
                        "user_hash": request.user_hash,
                        "collection_name": request.collection_name,
                        "top_k": request.top_k
                    },
                    timeout=30.0
                )
                search_response.raise_for_status()
                search_data = search_response.json()

            yield format_sse({"status": "search_complete", "message": f"Found {len(search_data.get('results', []))} results"}, "progress")

            # Step 2: Call LLM with streaming
            yield format_sse({"status": "generating", "message": "Generating AI response..."}, "progress")

            ai_response = ""
            try:
                # Try streaming
                for content in call_llm_stream(
                    provider=request.provider,
                    model=request.model,
                    api_key=llm_api_key,
                    system_prompt=request.instruct,
                    user_prompt=f"Query: {request.query}\n\nSearch Results: {search_data}"
                ):
                    ai_response += content
                    # Stream AI response chunks
                    yield format_sse({"status": "ai_chunk", "content": content}, "ai_response")

            except Exception as stream_error:
                # Fallback to non-streaming if streaming fails
                print(f"Streaming failed, falling back to non-streaming: {stream_error}")
                ai_response = call_llm_complete(
                    provider=request.provider,
                    model=request.model,
                    api_key=llm_api_key,
                    system_prompt=request.instruct,
                    user_prompt=f"Query: {request.query}\n\nSearch Results: {search_data}"
                )
                yield format_sse({"status": "ai_complete", "content": ai_response}, "ai_response")

            yield format_sse({"status": "synthesizing", "message": "Converting response to speech..."}, "progress")

            # Step 3: Synthesize speech from AI response
            filename = f"{uuid.uuid4()}.mp3"
            filepath = os.path.join(TEMP_DIR, filename)

            communicate = edge_tts.Communicate(
                text=ai_response,
                voice=request.voice,
                rate="+0%",
                pitch="+0Hz"
            )

            await communicate.save(filepath)

            # Schedule file deletion after 10 minutes
            asyncio.create_task(delete_file_after_delay(filepath, 600))

            # Generate audio URL
            audio_url = f"/audio/{filename}"

            # Filter documents with score > 0.4
            document_urls = []
            if "results" in search_data:
                document_urls = [
                    doc.get("metadata", {}).get("url", "")
                    for doc in search_data["results"]
                    if doc.get("score", 0) > 0.4 and doc.get("metadata", {}).get("url")
                ]

            # Send final completion event
            yield format_sse({
                "status": "completed",
                "message": "All processing completed",
                "query": request.query,
                "ai_response": ai_response,
                "document_url": document_urls,
                "audio_url": audio_url
            }, "complete")

        except httpx.HTTPError as e:
            yield format_sse({"status": "error", "message": f"Error calling embedding API: {str(e)}"}, "error")
        except Exception as e:
            import traceback
            print(f"ERROR: {str(e)}")
            print(traceback.format_exc())
            yield format_sse({"status": "error", "message": str(e)}, "error")

    return StreamingResponse(
        event_generator(),
        media_type="text/event-stream",
        headers={
            "Cache-Control": "no-cache",
            "Connection": "keep-alive",
            "X-Accel-Buffering": "no"
        }
    )

@app.post("/synthesize")
async def synthesize_speech(request: TTSRequest):
    try:
        # Generate unique filename
        filename = f"{uuid.uuid4()}.mp3"
        filepath = os.path.join(TEMP_DIR, filename)

        # Create TTS
        communicate = edge_tts.Communicate(
            text=request.text,
            voice=request.voice,
            rate=request.rate,
            pitch=request.pitch,
            volume=request.volume
        )

        # Save audio file
        await communicate.save(filepath)

        # Schedule file deletion after 10 minutes
        asyncio.create_task(delete_file_after_delay(filepath, 600))

        # Return the audio file
        return FileResponse(
            filepath,
            media_type="audio/mpeg",
            filename=filename,
            headers={
                "Content-Disposition": f"attachment; filename={filename}"
            }
        )
    
    except Exception as e:
        import traceback
        print(f"ERROR: {str(e)}")
        print(traceback.format_exc())
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/synthesize/stream")
async def synthesize_speech_stream(request: TTSRequest):
    """Stream TTS generation progress using Server-Sent Events"""
    async def event_generator():
        try:
            # Send start event
            yield format_sse({"status": "started", "message": "Starting TTS synthesis"}, "progress")

            # Generate unique filename
            filename = f"{uuid.uuid4()}.mp3"
            filepath = os.path.join(TEMP_DIR, filename)

            yield format_sse({"status": "processing", "message": f"Generating speech for text (length: {len(request.text)} chars)"}, "progress")

            # Create TTS
            communicate = edge_tts.Communicate(
                text=request.text,
                voice=request.voice,
                rate=request.rate,
                pitch=request.pitch,
                volume=request.volume
            )

            yield format_sse({"status": "saving", "message": "Saving audio file"}, "progress")

            # Save audio file
            await communicate.save(filepath)

            # Schedule file deletion after 10 minutes
            asyncio.create_task(delete_file_after_delay(filepath, 600))

            # Generate audio URL
            audio_url = f"/audio/{filename}"

            # Send completion event with audio URL
            yield format_sse({
                "status": "completed",
                "message": "TTS synthesis completed",
                "audio_url": audio_url,
                "filename": filename
            }, "complete")

        except Exception as e:
            import traceback
            print(f"ERROR: {str(e)}")
            print(traceback.format_exc())
            yield format_sse({"status": "error", "message": str(e)}, "error")

    return StreamingResponse(
        event_generator(),
        media_type="text/event-stream",
        headers={
            "Cache-Control": "no-cache",
            "Connection": "keep-alive",
            "X-Accel-Buffering": "no"
        }
    )

@app.get("/voices")
async def list_voices():
    try:
        voices = await edge_tts.list_voices()
        return {"voices": voices}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/audio/{filename}")
async def get_audio(filename: str):
    """Serve the generated audio file"""
    try:
        filepath = os.path.join(TEMP_DIR, filename)
        if not os.path.exists(filepath):
            raise HTTPException(status_code=404, detail="Audio file not found")

        return FileResponse(
            filepath,
            media_type="audio/mpeg",
            filename=filename
        )
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.delete("/cleanup")
async def cleanup_temp_files():
    """Optional endpoint to clean up old temp files"""
    try:
        files = os.listdir(TEMP_DIR)
        for file in files:
            filepath = os.path.join(TEMP_DIR, file)
            if os.path.isfile(filepath):
                os.remove(filepath)
        return {"message": f"Cleaned up {len(files)} files"}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.websocket("/ws/synthesize")
async def websocket_synthesize(websocket: WebSocket):
    """Stream TTS audio chunks via WebSocket"""
    await websocket.accept()

    try:
        # Receive the TTS request data
        data = await websocket.receive_json()

        # Send start message
        await websocket.send_json({
            "type": "status",
            "status": "started",
            "message": "Starting TTS synthesis"
        })

        # Create TTS with parameters from request
        communicate = edge_tts.Communicate(
            text=data.get("text", ""),
            voice=data.get("voice", "en-HK-SamNeural"),
            rate=data.get("rate", "+10%"),
            pitch=data.get("pitch", "-18Hz"),
            volume=data.get("volume", "+0%")
        )

        await websocket.send_json({
            "type": "status",
            "status": "streaming",
            "message": "Streaming audio chunks..."
        })

        # Stream audio chunks
        async for chunk in communicate.stream():
            if chunk["type"] == "audio":
                # Send audio chunk as base64 (for compatibility)
                await websocket.send_json({
                    "type": "audio",
                    "data": base64.b64encode(chunk["data"]).decode("utf-8")
                })
            elif chunk["type"] == "WordBoundary":
                # Send word boundary info for synchronization
                await websocket.send_json({
                    "type": "word_boundary",
                    "offset": chunk.get("offset"),
                    "duration": chunk.get("duration"),
                    "text": chunk.get("text")
                })

        # Send completion message
        await websocket.send_json({
            "type": "status",
            "status": "completed",
            "message": "TTS synthesis completed"
        })

    except WebSocketDisconnect:
        print("WebSocket disconnected")
    except Exception as e:
        import traceback
        print(f"ERROR: {str(e)}")
        print(traceback.format_exc())
        try:
            await websocket.send_json({
                "type": "error",
                "message": str(e)
            })
        except:
            pass

@app.websocket("/ws/tts")
async def websocket_tts_with_rag(websocket: WebSocket):
    """Stream TTS with RAG via WebSocket"""
    await websocket.accept()

    try:
        # Receive the request data
        data = await websocket.receive_json()

        # Validate auth_key exists
        auth_key = data.get("auth_key")
        if not auth_key:
            error_msg = "Missing auth_key in request"
            await websocket.send_json({
                "type": "error",
                "message": error_msg
            })
            return

        # Decrypt auth_key to get API keys
        keys = decrypt_auth_key(auth_key)
        embedding_api_key = keys['embedding_api_key']
        llm_api_key = keys['llm_api_key']

        # Step 1: Search embeddings
        await websocket.send_json({
            "type": "status",
            "status": "searching",
            "message": "Searching embeddings..."
        })

        async with httpx.AsyncClient() as client:
            search_response = await client.post(
                f"{EMBEDDING_API_URL}/search",
                json={
                    "api_key": EMBEDDING_API_KEY,
                    "query": data.get("query"),
                    "user_hash": data.get("user_hash"),
                    "collection_name": data.get("collection_name"),
                    "top_k": data.get("top_k", 5)
                },
                timeout=30.0
            )
            search_response.raise_for_status()
            search_data = search_response.json()

        await websocket.send_json({
            "type": "status",
            "status": "search_complete",
            "message": f"Found {len(search_data.get('results', []))} results"
        })

        # Step 2: Generate AI response
        await websocket.send_json({
            "type": "status",
            "status": "generating",
            "message": "Generating AI response..."
        })

        ai_response = ""
        try:
            # Try streaming AI response
            for content in call_llm_stream(
                provider=data.get("provider"),
                model=data.get("model"),
                api_key=llm_api_key,
                system_prompt=data.get("instruct", ""),
                user_prompt=f"Query: {data.get('query')}\n\nSearch Results: {search_data}"
            ):
                ai_response += content
                # Stream AI response chunks
                await websocket.send_json({
                    "type": "ai_response",
                    "content": content
                })

        except Exception as stream_error:
            # Fallback to non-streaming
            print(f"Streaming failed, falling back to non-streaming: {stream_error}")
            ai_response = call_llm_complete(
                provider=data.get("provider"),
                model=data.get("model"),
                api_key=llm_api_key,
                system_prompt=data.get("instruct", ""),
                user_prompt=f"Query: {data.get('query')}\n\nSearch Results: {search_data}"
            )
            await websocket.send_json({
                "type": "ai_response",
                "content": ai_response
            })

        # Step 3: Synthesize and stream audio
        await websocket.send_json({
            "type": "status",
            "status": "synthesizing",
            "message": "Converting response to speech..."
        })

        communicate = edge_tts.Communicate(
            text=ai_response,
            voice=data.get("voice", "en-HK-SamNeural"),
            rate="+0%",
            pitch="+0Hz"
        )

        await websocket.send_json({
            "type": "status",
            "status": "streaming",
            "message": "Streaming audio chunks..."
        })

        # Stream audio chunks
        async for chunk in communicate.stream():
            if chunk["type"] == "audio":
                # Send audio chunk as base64
                await websocket.send_json({
                    "type": "audio",
                    "data": base64.b64encode(chunk["data"]).decode("utf-8")
                })
            elif chunk["type"] == "WordBoundary":
                await websocket.send_json({
                    "type": "word_boundary",
                    "offset": chunk.get("offset"),
                    "duration": chunk.get("duration"),
                    "text": chunk.get("text")
                })

        # Filter documents with score > 0.4
        document_urls = []
        if "results" in search_data:
            document_urls = [
                doc.get("metadata", {}).get("url", "")
                for doc in search_data["results"]
                if doc.get("score", 0) > 0.4 and doc.get("metadata", {}).get("url")
            ]

        # Send completion with metadata
        await websocket.send_json({
            "type": "status",
            "status": "completed",
            "message": "All processing completed",
            "query": data.get("query"),
            "ai_response": ai_response,
            "document_urls": document_urls
        })

    except WebSocketDisconnect:
        print("WebSocket disconnected")
    except httpx.HTTPError as e:
        try:
            await websocket.send_json({
                "type": "error",
                "message": f"Error calling embedding API: {str(e)}"
            })
        except:
            pass
    except Exception as e:
        import traceback
        print(f"ERROR: {str(e)}")
        print(traceback.format_exc())
        try:
            await websocket.send_json({
                "type": "error",
                "message": str(e)
            })
        except:
            pass

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8001)
