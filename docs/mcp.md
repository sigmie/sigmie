---
title: MCP Server
short_description: Connect AI agents to the Sigmie documentation
keywords: [mcp, ai, agent, claude, cursor, documentation, semantic search]
category: Integrations
order: 3
related_pages: [laravel-ai, semantic-search, search, rag]
---

# MCP Server

Sigmie runs a remote [MCP (Model Context Protocol)](https://modelcontextprotocol.io) server that gives AI agents semantic search across the full Sigmie documentation. Any MCP-compatible client — Claude Code, Cursor, Windsurf, or a custom agent — can search, browse, and read these docs without leaving the editor.

## Add the server

In your project's `.mcp.json`:

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

Or add it globally in `~/.claude.json` so it's available in every project.

Restart your agent. Three tools become available:

- **`search_docs`** — semantic search across all documentation.
- **`read_doc`** — read a specific page in full.
- **`list_docs`** — list every available page.

## How it works

```
AI agent (Claude Code, Cursor, etc.)
   │
   │  HTTPS (Streamable HTTP)
   ▼
sigmie.com/mcp ──► Node.js MCP server
                       │
          ┌────────────┼────────────┐
          │            │            │
    search_docs   read_doc     list_docs
          │            │            │
          ▼            ▼            ▼
    Elasticsearch    docs/*.md    docs/*.md
    hybrid search    (full page)  (file list)
    (649 sections)
```

`search_docs` runs a hybrid query — keyword + 384-dim vectors — against an Elasticsearch index built from the docs.

## Available tools

### `search_docs`

```
search_docs({ query: "how to configure semantic search" })
```

Returns the top 10 matching sections with title, page slug, version, URL, and content. Ranked by a combination of keyword and vector scores.

### `read_doc`

```
read_doc({ page: "search", version: "v2" })
```

Returns the full Markdown of a page. Use after `search_docs` to pull complete context — including code examples — into the agent's working memory.

### `list_docs`

```
list_docs({ version: "v2" })
```

Returns every page slug for a version. Useful for the agent to discover what's available.

## Use cases

### AI-assisted development

Your coding assistant can look up the exact API while writing code:

```
"How do I add typo tolerance?"
→ search_docs returns the relevant section with code
→ The agent adapts the example to your codebase
```

### Onboarding

New developers ask natural-language questions and get the right doc section back instead of browsing the table of contents.

### Self-configuring agents

If you build an AI agent on top of Sigmie (using the [Laravel AI SDK](laravel-ai.md)), the MCP server helps the agent understand its own search tool.

## Client configuration

### Claude Code (project)

`.mcp.json` in the project root:

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

### Claude Code (global)

`~/.claude.json`:

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

In Cursor's MCP settings:

```json
{
  "mcpServers": {
    "sigmie-docs": {
      "url": "https://sigmie.com/mcp"
    }
  }
}
```

## See also

- [Laravel AI SDK](laravel-ai.md) — expose your own Sigmie indices as agent tools.
- [Semantic Search](semantic-search.md) — the same technology powers the MCP server.
- [Retrieval and Agents](rag.md) — build retrieval-augmented generation with Sigmie.
