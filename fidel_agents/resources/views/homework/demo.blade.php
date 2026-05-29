<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — Homework Assistant</title>
    <style>
        :root {
            --bg: #0f1419;
            --surface: #1a2332;
            --border: #2d3a4f;
            --text: #e8eef7;
            --muted: #8b9cb3;
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --success: #22c55e;
            --warn: #f59e0b;
            --offline: #6366f1;
            --online: #14b8a6;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            line-height: 1.5;
        }
        .wrap { max-width: 960px; margin: 0 auto; padding: 2rem 1.25rem 4rem; }
        header { margin-bottom: 2rem; }
        h1 { font-size: 1.75rem; font-weight: 700; letter-spacing: -0.02em; }
        .subtitle { color: var(--muted); margin-top: 0.35rem; font-size: 0.95rem; }
        .badges { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; }
        .badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.65rem;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: var(--surface);
        }
        .badge.ok { border-color: #166534; color: #86efac; }
        .badge.warn { border-color: #854d0e; color: #fcd34d; }
        .grid { display: grid; gap: 1.5rem; }
        @media (min-width: 768px) { .grid { grid-template-columns: 1fr 1fr; } }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem;
        }
        .card h2 { font-size: 1rem; margin-bottom: 1rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.06em; }
        label { display: block; font-size: 0.85rem; color: var(--muted); margin-bottom: 0.35rem; }
        input, textarea, select {
            width: 100%;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            padding: 0.6rem 0.75rem;
            font-size: 0.95rem;
            margin-bottom: 0.85rem;
        }
        textarea { min-height: 120px; resize: vertical; }
        input[type="file"] { padding: 0.45rem; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
        .tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
        .tab {
            flex: 1;
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: transparent;
            color: var(--muted);
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .tab.active { background: var(--accent); border-color: var(--accent); color: #fff; }
        .hidden { display: none !important; }
        button.submit {
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
        }
        button.submit:hover { background: var(--accent-hover); }
        button.submit:disabled { opacity: 0.5; cursor: not-allowed; }
        .result-card { grid-column: 1 / -1; }
        .result-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .pill {
            font-size: 0.72rem;
            padding: 0.2rem 0.55rem;
            border-radius: 6px;
            background: var(--bg);
            border: 1px solid var(--border);
        }
        .pill.offline { border-color: var(--offline); color: #a5b4fc; }
        .pill.online { border-color: var(--online); color: #5eead4; }
        .problem { font-size: 1.05rem; font-weight: 600; margin-bottom: 0.75rem; }
        ol.steps { padding-left: 1.25rem; }
        ol.steps li { margin-bottom: 0.5rem; }
        .answer {
            margin-top: 1rem;
            padding: 0.75rem 1rem;
            background: #0d2818;
            border: 1px solid #166534;
            border-radius: 8px;
            color: #86efac;
        }
        .tip {
            margin-top: 1rem;
            padding: 0.75rem 1rem;
            border-left: 3px solid var(--warn);
            background: rgba(245, 158, 11, 0.08);
            border-radius: 0 8px 8px 0;
            font-size: 0.9rem;
        }
        .error {
            color: #fca5a5;
            background: #450a0a;
            border: 1px solid #991b1b;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            white-space: pre-wrap;
            font-size: 0.85rem;
        }
        .loading { color: var(--muted); font-style: italic; }
        .preview { max-width: 100%; max-height: 160px; border-radius: 8px; margin-bottom: 0.85rem; border: 1px solid var(--border); }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <h1>Fidel Academy — Homework Assistant</h1>
        <p class="subtitle">Offline-first AI tutor (Ollama + Tesseract) with cloud fallback (Gemini) for images and connectivity.</p>
        <div class="badges" id="status-badges">
            <span class="badge">Checking services…</span>
        </div>
    </header>

    <form id="homework-form" class="grid">
        <div class="card">
            <h2>Your request</h2>
            <div class="tabs">
                <button type="button" class="tab active" data-mode="text">Text problem</button>
                <button type="button" class="tab" data-mode="image">Photo / image</button>
            </div>

            <div id="panel-text">
                <label for="text">Problem (type or paste)</label>
                <textarea id="text" name="text" placeholder="e.g. Solve: 2x + 5 = 15. Show all steps."></textarea>
            </div>

            <div id="panel-image" class="hidden">
                <label for="homework_image">Homework image</label>
                <input type="file" id="homework_image" name="homework_image" accept="image/*">
                <img id="image-preview" class="preview hidden" alt="Preview">
                <label for="text-extra">Extra notes (optional)</label>
                <textarea id="text-extra" placeholder="Optional context for the tutor…"></textarea>
            </div>

            <div class="row">
                <div>
                    <label for="subject_hint">Subject</label>
                    <input id="subject_hint" name="subject_hint" value="Mathematics">
                </div>
                <div>
                    <label for="grade_hint">Grade</label>
                    <input id="grade_hint" name="grade_hint" value="9th grade">
                </div>
            </div>

            <input type="hidden" name="intent" value="homework">
            <input type="hidden" name="user_id" value="1">
            <input type="hidden" name="role_name" value="student">

            <button type="submit" class="submit" id="submit-btn">Get step-by-step help</button>
        </div>

        <div class="card">
            <h2>How it works</h2>
            <ul style="color: var(--muted); font-size: 0.9rem; padding-left: 1.1rem;">
                <li style="margin-bottom: 0.5rem;"><strong style="color: var(--text);">Text</strong> — processed locally with Ollama when available (<span class="pill offline">offline</span>).</li>
                <li style="margin-bottom: 0.5rem;"><strong style="color: var(--text);">Images</strong> — OCR via Tesseract; cloud Gemini when online (<span class="pill online">online</span>).</li>
                <li>Answers include steps, final answer, and a learning tip — never answer-only.</li>
            </ul>
        </div>

        <div class="card result-card hidden" id="result-panel">
            <h2>Solution</h2>
            <div id="result-content"></div>
        </div>
    </form>
</div>

<script>
(function () {
    // Relative URLs so fetch always uses the same host:port as this page (avoids missing :8080).
    const apiUrl = '/api/ai/ask';
    const statusUrl = '/api/ai/status';
    const form = document.getElementById('homework-form');
    const submitBtn = document.getElementById('submit-btn');
    const resultPanel = document.getElementById('result-panel');
    const resultContent = document.getElementById('result-content');
    const statusBadges = document.getElementById('status-badges');
    const panelText = document.getElementById('panel-text');
    const panelImage = document.getElementById('panel-image');
    const imageInput = document.getElementById('homework_image');
    const imagePreview = document.getElementById('image-preview');
    let mode = 'text';

    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', () => {
            mode = tab.dataset.mode;
            document.querySelectorAll('.tab').forEach(t => t.classList.toggle('active', t === tab));
            panelText.classList.toggle('hidden', mode !== 'text');
            panelImage.classList.toggle('hidden', mode !== 'image');
        });
    });

    imageInput.addEventListener('change', () => {
        const file = imageInput.files[0];
        if (!file) {
            imagePreview.classList.add('hidden');
            return;
        }
        imagePreview.src = URL.createObjectURL(file);
        imagePreview.classList.remove('hidden');
    });

    async function refreshStatus() {
        try {
            const res = await fetch(statusUrl);
            const data = await res.json();
            statusBadges.innerHTML = [
                data.online
                    ? '<span class="badge ok">Internet: online</span>'
                    : '<span class="badge warn">Internet: offline</span>',
                data.ollama_reachable
                    ? '<span class="badge ok">Ollama: running</span>'
                    : '<span class="badge warn">Ollama: unavailable</span>',
            ].join('');
        } catch {
            statusBadges.innerHTML = '<span class="badge warn">Could not reach API</span>';
        }
    }

    function renderResult(data) {
        const offline = data.processed_offline === true;
        const meta = [
            `<span class="pill ${offline ? 'offline' : 'online'}">${offline ? 'Processed offline' : 'Cloud-assisted'}</span>`,
            `<span class="pill">LLM: ${data.llm_provider || '—'} (${data.llm_model || '—'})</span>`,
        ];
        if (data.ocr_provider && data.ocr_provider !== 'unknown') {
            meta.push(`<span class="pill">OCR: ${data.ocr_provider} (${data.ocr_mode || '—'})</span>`);
        }
        const steps = (data.steps || []).map(s => `<li>${escapeHtml(String(s))}</li>`).join('');
        resultContent.innerHTML = `
            <div class="result-meta">${meta.join('')}</div>
            <p class="problem">${escapeHtml(data.problem || '')}</p>
            <p style="color: var(--muted); font-size: 0.85rem; margin-bottom: 0.5rem;">${escapeHtml(data.subject || '')} · ${escapeHtml(data.grade_level || '')}</p>
            <ol class="steps">${steps}</ol>
            <div class="answer"><strong>Final answer:</strong> ${escapeHtml(data.final_answer || '')}</div>
            <div class="tip"><strong>Learning tip:</strong> ${escapeHtml(data.learning_tip || '')}</div>
        `;
        resultPanel.classList.remove('hidden');
    }

    function renderError(err) {
        resultContent.innerHTML = `<div class="error">${escapeHtml(err)}</div>`;
        resultPanel.classList.remove('hidden');
    }

    function escapeHtml(str) {
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        submitBtn.disabled = true;
        submitBtn.textContent = 'Thinking… (may take up to 90s offline)';
        resultPanel.classList.remove('hidden');
        resultContent.innerHTML = '<p class="loading">Sending to Fidel Brain…</p>';

        const body = new FormData();
        body.append('intent', 'homework');
        body.append('user_id', '1');
        body.append('role_name', 'student');
        body.append('subject_hint', document.getElementById('subject_hint').value);
        body.append('grade_hint', document.getElementById('grade_hint').value);

        if (mode === 'text') {
            const text = document.getElementById('text').value.trim();
            if (!text) {
                renderError('Please enter a problem.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Get step-by-step help';
                return;
            }
            body.append('text', text);
        } else {
            const file = imageInput.files[0];
            if (!file) {
                renderError('Please choose an image.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Get step-by-step help';
                return;
            }
            body.append('homework_image', file);
            const extra = document.getElementById('text-extra').value.trim();
            if (extra) body.append('text', extra);
        }

        try {
            const res = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body,
            });
            const data = await res.json();
            if (!res.ok) {
                const msg = data.errors
                    ? Object.entries(data.errors).map(([k, v]) => `${k}: ${Array.isArray(v) ? v.join(', ') : v}`).join('\n')
                    : (data.message || JSON.stringify(data, null, 2));
                renderError(msg);
            } else {
                renderResult(data);
            }
        } catch (err) {
            renderError(err.message || 'Network error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Get step-by-step help';
        }
    });

    refreshStatus();
    setInterval(refreshStatus, 30000);
})();
</script>
</body>
</html>
