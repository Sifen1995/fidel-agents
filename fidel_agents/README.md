# Fidel Academy — AI Agents

Laravel 12 application for Fidel Academy's offline-first AI agent backend.

For full documentation — setup, architecture, API reference, and configuration — see the [project README](../README.md).

## Quick commands

```bash
# Docker
docker compose up -d --build
docker compose exec app php artisan migrate

# Local dev
composer setup
composer dev

# Tests
composer test
```

## Key paths

- `app/Ai/Orchestrator/Brain.php` — request orchestrator
- `app/Ai/Agents/` — agent implementations
- `config/ai.php` — AI provider configuration
- `routes/api.php` — `/api/ai/*` routes
- `resources/views/homework/demo.blade.php` — web demo UI
