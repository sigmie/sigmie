# LLM Service Setup

The project uses Ollama for local LLM testing with **tinyllama** as the default model.

## Default Setup
Uses **tinyllama** (600MB) - fast, lightweight, perfect for testing
```bash
docker compose up llm
```

## Custom Models
To use a different model:
```bash
OLLAMA_MODEL=phi3 docker compose up llm
```

## Available Models
- `tinyllama` - 600MB, fast (default)
- `phi3` - 3.8GB, good quality
- `llama2` - 3.8GB, good quality
- `mistral` - 4.1GB, excellent quality

See [Ollama models](https://ollama.com/library) for more options.

## Memory Requirements
- tinyllama: ~1GB RAM
- phi3/llama2: ~4GB RAM
- mistral: ~5GB RAM
