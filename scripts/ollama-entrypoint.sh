#!/bin/bash

# Start Ollama in the background
/bin/ollama serve &

# Wait for Ollama to be ready
echo "Waiting for Ollama to be ready..."
max_attempts=30
attempt=0

while [ $attempt -lt $max_attempts ]; do
    if ollama list > /dev/null 2>&1; then
        echo "Ollama is ready!"
        break
    fi
    sleep 2
    attempt=$((attempt + 1))
done

# Default to tinyllama (fast, lightweight)
MODEL="${OLLAMA_MODEL:-tinyllama}"

# Pull model if not already present
if ! ollama list | grep -q "$MODEL"; then
    echo "Pulling $MODEL model..."
    ollama pull "$MODEL"
    echo "$MODEL model ready!"
else
    echo "$MODEL model already present"
fi

# Keep the container running
wait
