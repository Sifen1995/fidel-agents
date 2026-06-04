# Fidel Agents

Offline-first AI agent backend for [Fidel Academy](https://github.com/) — an AI-driven learning platform built with Laravel 12. A central **Brain** orchestrator receives requests, classifies intent, and routes them to specialized agents. Homework assistance is fully implemented; study recommendations, exam prep, and helpdesk agents are scaffolded for future work.

## Features

- **Homework Helper** — Step-by-step homework guidance with OCR support for photographed problems
- **Offline-first AI** — Prefers local [Ollama](https://ollama.com/) models; falls back to Google Gemini when online
- **Hybrid OCR** — Tesseract runs locally; low-confidence scans can be enhanced with Gemini Vision when connectivity is available
- **Intent routing** — Explicit tags, rule-based detection, and an AI classifier decide which agent handles each request
- **Web demo UI** — Interactive homework assistant at `/homework`
- **Request persistence** — Homework interactions are stored in PostgreSQL

## Architecture

```
Client (web / API)
       │
       ▼
  InputNormalizer ──► Brain (orchestrator)
                           │
         ┌─────────────────┼─────────────────┐
         ▼                 ▼                 ▼
  HomeworkHelper    StudyRecommender    ExamPrep / Helpdesk
         │
    OcrService ──► Tesseract (offline) / Gemini Vision (cloud)
         │
    Ollama (offline) / Gemini (cloud fallback)
```

### Intent resolution order

1. **Explicit** — `tags`, `intent`, `agent`, or `type` fields in the payload
2. **Rules** — Image uploads route to homework; exam/recommendation field keys route accordingly
3. **AI classifier** — `IntentClassifierAgent` chooses the best match when rules are inconclusive

### Supported intents

| Intent | Agent | Status |
|--------|-------|--------|
| `homework` | `HomeworkHelperAgent` | Fully implemented |
| `recommender` | `StudyRecommenderAgent` | Stub |
| `exam_prep` | `ExamPrepAgent` | Stub |
| `helpdesk` | `HelpdeskAgent` | Stub |

## Tech stack

| Layer | Technology |
|-------|------------|
| Framework | Laravel 12, PHP 8.4 |
| AI | [Laravel AI](https://laravel.com/docs/ai) (`laravel/ai`) |
| LLM (local) | Ollama on the host machine |
| LLM (cloud) | Google Gemini |
| OCR | Tesseract (+ Amharic language pack), Gemini Vision |
| Database | PostgreSQL with pgvector |
| Cache / queues / sessions | Redis |
| Containerization | Docker Compose (Nginx, PHP-FPM, Postgres, Redis, pgAdmin) |

## Project layout

All application code lives in the `fidel_agents/` directory:

```
fidel-agents/
├── README.md                 # This file
└── fidel_agents/             # Laravel application
    ├── app/Ai/
    │   ├── Agents/           # Homework, recommender, exam prep, helpdesk
    │   ├── Orchestrator/     # Brain — central request router
    │   ├── Prompts/          # LLM prompt templates
    │   └── Services/         # OCR, connectivity, intent classification, parsing
    ├── config/ai.php         # Provider and model configuration
    ├── docker/               # Dockerfile, Nginx, entrypoint
    ├── docker-compose.yml
    ├── routes/api.php        # /api/ai/* endpoints
    └── resources/views/homework/demo.blade.php
```

## Prerequisites

- [Docker](https://docs.docker.com/get-docker/) and Docker Compose
- [Ollama](https://ollama.com/) installed and running on the **host** (not inside Docker)
- A pulled Ollama model, e.g. `ollama pull qwen2.5:1.5b`
- (Optional) Google Gemini API key for cloud fallback and vision OCR

## Quick start (Docker)

```bash
cd fidel_agents

# Copy environment file and set your keys
cp .env.example .env

# Start services (app, postgres, redis, pgadmin, ollama-proxy)
docker compose up -d --build

# Install dependencies and run migrations inside the app container
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

The app is available at **http://localhost:8080**.

| Service | URL |
|---------|-----|
| Web / API | http://localhost:8080 |
| Homework demo | http://localhost:8080/homework |
| pgAdmin | http://localhost:5050 |
| Health check | http://localhost:8080/up |

### Ollama connectivity

The Docker stack runs an `ollama-proxy` (socat) so the app container can reach Ollama on the host at `http://host.docker.internal:11435`. Ensure Ollama is listening on port `11434` before starting the stack.

Check connectivity:

```bash
curl http://localhost:8080/api/ai/status
# {"online":true,"ollama_reachable":true}
```

## Local development (without Docker)

```bash
cd fidel_agents

composer setup   # install deps, copy .env, generate key, migrate, npm build
composer dev     # serve, queue worker, logs, and Vite concurrently
```

Requires local PostgreSQL, Redis, and Ollama. Adjust `.env` accordingly (see `.env.example`).

## Configuration

Key environment variables in `fidel_agents/.env`:

| Variable | Description | Default |
|----------|-------------|---------|
| `AI_DEFAULT_PROVIDER` | Primary AI provider | `ollama` |
| `OLLAMA_BASE_URL` | Ollama endpoint | `http://host.docker.internal:11434` |
| `OLLAMA_MODEL` | Text model for Ollama | — |
| `GEMINI_API_KEY` | Google Gemini API key (optional) | — |
| `GEMINI_MODEL` | Gemini text model | `gemini-3.1-flash-lite-preview` |
| `DB_*` | PostgreSQL connection | see `.env.example` |
| `REDIS_*` | Redis connection | see `.env.example` |

Provider and model settings are centralized in `config/ai.php`.

## API reference

### `POST /api/ai/ask`

Main orchestrator endpoint. Accepts JSON or `multipart/form-data`.

**Homework example (text):**

```bash
curl -X POST http://localhost:8080/api/ai/ask \
  -H "Content-Type: application/json" \
  -d '{
    "intent": "homework",
    "user_id": 1,
    "role_name": "student",
    "text": "Solve 2x + 5 = 15",
    "subject_hint": "Mathematics",
    "grade_hint": "8th grade"
  }'
```

**Homework example (image upload):**

```bash
curl -X POST http://localhost:8080/api/ai/ask \
  -F "intent=homework" \
  -F "user_id=1" \
  -F "role_name=student" \
  -F "image=@/path/to/homework.jpg"
```

**Homework response shape:**

```json
{
  "request_id": "uuid",
  "subject": "Mathematics",
  "grade_level": "8th grade",
  "problem": "Solve 2x + 5 = 15",
  "steps": ["Subtract 5 from both sides", "Divide both sides by 2"],
  "final_answer": "x = 5",
  "learning_tip": "Always perform the same operation on both sides.",
  "llm_provider": "ollama",
  "llm_model": "qwen2.5:1.5b",
  "processed_offline": true,
  "intent": "homework",
  "intent_source": "explicit"
}
```

**Other intents** — Pass `"intent": "recommender"`, `"exam_prep"`, or `"helpdesk"` with relevant payload fields, or let the Brain infer intent from the request content.

### Other endpoints

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/api/ai/health` | Service health check |
| `GET` | `/api/ai/status` | Online and Ollama reachability status |
| `GET` | `/homework` | Interactive homework demo UI |


Open it in the browser
URL	Route
http://localhost:8080/homework


## Testing

```bash
cd fidel_agents
composer test
# or
php artisan test
```

## License

MIT
