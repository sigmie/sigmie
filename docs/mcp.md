---
title: MCP Server
short_description: Connect AI agents to Sigmie documentation via the Model Context Protocol
keywords: [mcp, ai, agent, claude, cursor, documentation, semantic search, tools]
category: Integrations
order: 3
related_pages: [laravel-ai, semantic-search, search, rag]
---

# MCP Server

Sigmie provides a remote MCP (Model Context Protocol) server that gives AI agents semantic search access to the full Sigmie documentation. Any MCP-compatible client — Claude Code, Cursor, Windsurf, or custom agents — can search, browse, and read docs without leaving the editor.

## Quick Start

Add the Sigmie docs MCP server to your project's `.mcp.json`:

```json
{
  "mcpServers": {
    "sigmie-docs": {
      "type": "http",
      "url": "https://sigmie.com/mcp"
    }
  }
}
```

Or add it globally in `~/.claude.json` under the `mcpServers` key to make it available in every project.

Restart your AI agent. You now have three tools available:

- **`search_docs`** — Semantic search across all documentation
- **`read_doc`** — Read the full content of a specific page
- **`list_docs`** — List all available documentation pages

## How It Works

The MCP server runs on `sigmie.com` and exposes documentation through the Streamable HTTP transport:

```
AI Agent (Claude Code, Cursor, etc.)
    │
    │  HTTPS (Streamable HTTP)
    │
    ▼
sigmie.com/mcp ──► Node.js MCP Server
                       │
          ┌────────────┼────────────┐
          │            │            │
    search_docs    read_doc    list_docs
          │            │            │
          ▼            ▼            ▼
    Elasticsearch   docs/*.md   docs/*.md
    semantic search (full page) (file list)
    (649 sections)
```

The `search_docs` tool queries an Elasticsearch index with hybrid search — combining keyword matching and vector similarity (384-dim embeddings) — to return the most relevant documentation sections.

## Available Tools

### search_docs

Semantic search across all Sigmie documentation. Returns the top 10 most relevant sections.

```
search_docs({ query: "how to configure semantic search" })
```

Returns matched sections with title, page slug, version, URL, and content. Results are ranked by a combination of keyword and vector similarity scores.

### read_doc

Read the full markdown content of a documentation page.

```
read_doc({ page: "search", version: "v2" })
```

Use this after finding a page via `search_docs` to get the complete content including code examples.

### list_docs

List all available documentation pages for a version.

```
list_docs({ version: "v2" })
```

Returns all page slugs. Useful for discovering what documentation is available.

## Use Cases

### AI-Assisted Development

When building with Sigmie, your AI coding assistant can look up the exact API you need:

```
"How do I add typo tolerance to my search?"
→ search_docs finds the relevant section with code examples
→ Agent applies the pattern to your code
```

### Onboarding

New team members can ask natural language questions and get relevant documentation sections without manually browsing:

```
"What's the difference between text and keyword fields?"
→ Returns the mappings documentation explaining field types
```

### Building Agents

When building AI agents that use Sigmie (via the [Laravel AI SDK](/docs/laravel-ai.md)), the MCP server helps the agent understand the Sigmie API itself — useful for self-configuring search tools.

## Configuration Options

### Claude Code

Project-level (`.mcp.json` in your repo root):

```json
{
  "mcpServers": {
    "sigmie-docs": {
      "type": "http",
      "url": "https://sigmie.com/mcp"
    }
  }
}
```

User-level (`~/.claude.json`, available in all projects):

```json
{
  "mcpServers": {
    "sigmie-docs": {
      "type": "http",
      "url": "https://sigmie.com/mcp"
    }
  }
}
```

### Cursor

Add to your Cursor MCP settings:

```json
{
  "mcpServers": {
    "sigmie-docs": {
      "url": "https://sigmie.com/mcp"
    }
  }
}
```

## Next Steps

- [Laravel AI SDK](/docs/laravel-ai.md) — Expose Sigmie indices as AI agent tools
- [Semantic Search](/docs/semantic-search.md) — Understand the search technology behind the MCP server
- [RAG](/docs/rag.md) — Build retrieval-augmented generation with Sigmie
