# Manual Test Checklist

After each relevant refactoring step:

1. Open Moodle dashboard.
2. Open AI Skill Navigator index.
3. Open General AI Tutor.
4. Open Course AI Tutor.
5. Open AI Quiz Generator.
6. Open AI Mind Map Generator.
7. Open AI XR Scenario Generator.
8. Check that no PHP error page is displayed.
9. Run the PHP lint script.
10. Purge Moodle cache after deployment.

## AI provider factory selection and fallback

Use this checklist when changing provider selection or provider defaults.

Before testing:

1. Open `Site administration > Plugins > Local plugins > AI Skill Navigator`.
2. Do not add real production API keys for fallback tests.
3. After each setting change, save the plugin settings and purge Moodle caches.
4. Open the AI Tutor page and submit a short prompt such as `Say hello`.
5. Confirm that the page renders a response or a controlled error, not a PHP
   fatal error page.

Provider cases:

The settings dropdown currently exposes `ollama`, `openrouter` and `openai`.
Use Moodle CLI/admin tooling, a database fixture or a temporary local test
setup for supported factory values that are not visible in the dropdown.

1. Prototype provider:
   - Direct test config: provider `prototype`, `mock`, or `demo`.
   - Expected: the demo/prototype response is returned without external API
     access.
2. Ollama defaults:
   - Settings: provider `ollama`, empty endpoint, empty model.
   - Expected: the factory uses `http://host.docker.internal:11434` and
     `qwen2.5:3b`.
3. OpenAI-compatible fallback:
   - Settings: provider `openai`, `openai_compatible`, `openrouter`, or
     `groq`; empty endpoint.
   - Expected: the factory uses the prototype provider instead of calling an
     empty URL.
4. OpenAI-compatible configured endpoint:
   - Settings: provider `openai`, `openai_compatible`, `openrouter`, or
     `groq`; endpoint set; model empty.
   - Expected: the factory uses the configured endpoint with model `default`.
5. DeepSeek defaults:
   - Direct test config: provider `deepseek`, empty endpoint, empty model.
   - Expected: the factory uses `https://api.deepseek.com` and `deepseek-chat`.
6. Unknown provider:
   - Direct test config: unsupported provider value stored in config.
   - Expected: the factory falls back to the prototype provider.

When a real external provider is tested, use a non-production test credential
configured through Moodle settings and verify that the plugin does not store the
credential in repository files.
