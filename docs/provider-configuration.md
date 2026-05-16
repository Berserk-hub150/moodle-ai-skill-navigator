# Provider Configuration

The current demo setup uses the DeepSeek API.

DeepSeek is configured as a dedicated provider name, but internally it uses the OpenAI-compatible chat completions strategy.

## Current demo provider

| Setting | Value |
|---|---|
| Provider | `deepseek` |
| Endpoint | `https://api.deepseek.com` |
| Model | `deepseek-chat` |
| API key | Configured in Moodle/plugin settings, not stored in the repository |

## Supported provider names

- `deepseek`
- `openai`
- `openai_compatible`
- `openrouter`
- `groq`
- `ollama`
- `prototype`
- `mock`
- `demo`

## Factory fallback reference

The provider factory is intentionally tolerant of incomplete demo settings.
Use this reference when reviewing provider selection changes or running manual
tests:

The current settings page exposes `ollama`, `openrouter` and `openai` in the
provider dropdown. Other supported factory values can still be reviewed through
direct test configuration, such as Moodle CLI/admin tooling, a database fixture
or a temporary local test setup.

- Empty provider name: uses `deepseek` as the code-level fallback provider.
- `deepseek` with an empty endpoint: uses `https://api.deepseek.com`.
- `deepseek` with an empty model: uses `deepseek-chat`.
- `openai`, `openai_compatible`, `openrouter`, or `groq` with an empty
  endpoint: falls back to the prototype provider instead of calling an empty
  URL.
- `openai`, `openai_compatible`, `openrouter`, or `groq` with a configured
  endpoint and empty model: uses `default`.
- `ollama` with an empty endpoint: uses `http://host.docker.internal:11434`.
- `ollama` with an empty model: uses `qwen2.5:3b`.
- `prototype`, `mock`, or `demo`: uses the prototype provider.
- Unknown provider name: falls back to the prototype provider.

For manual tests, prefer `prototype`, `mock`, `demo`, or an intentionally empty
OpenAI-compatible endpoint when you want to verify fallback behavior without
making a real external API request.

## Architecture note

The plugin uses the Strategy Pattern for AI providers.

The DeepSeek provider is a named strategy built on top of the OpenAI-compatible provider implementation.

Ollama remains available as an optional local provider, but it is not the default provider of the current demo.
