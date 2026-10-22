---
title: MCP Server
short_description: Connect Claude Code, Cursor, or any MCP client to the Sigmie docs server. Your coding assistant then searches, lists, and reads Sigmie documentation pages.
keywords: [mcp, ai, agent, claude, cursor, documentation, semantic search]
category: Integrations
order: 3
related_pages: [laravel-ai, semantic-search, search, rag]
---

# MCP Server

Sigmie runs a remote [Model Context Protocol (MCP)](https://modelcontextprotocol.io) server at `https://sigmie.com/mcp`. Connect your coding assistant to it, and the assistant searches and reads the Sigmie documentation while it writes code. The server uses the Streamable HTTP transport.

To expose your own indices to an agent, use the [Laravel AI SDK](laravel-ai.md) tools instead.

## Add the server to Claude Code

To add the server to one project, create `.mcp.json` in the project root:

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

To add the server to every project, run:

```bash
claude mcp add --transport http --scope user sigmie-docs https://sigmie.com/mcp
```

The command writes the same entry to `~/.claude.json`.

## Add the server to Cursor

Add this entry to Cursor's MCP settings:

```json
{
  "mcpServers": {
    "sigmie-docs": {
      "url": "https://sigmie.com/mcp"
    }
  }
}
```

For another MCP client, point an HTTP server entry at `https://sigmie.com/mcp`.

## Check the connection

Restart the assistant. It now lists three tools from `sigmie-docs`:

| Tool | Call | Returns |
|------|------|---------|
| `search_docs` | `search_docs({ query: "how to configure semantic search" })` | The 10 best matching sections, each with title, page slug, version, URL, and content. |
| `read_doc` | `read_doc({ page: "search", version: "v2" })` | The full Markdown of one page, including its code examples. |
| `list_docs` | `list_docs({ version: "v2" })` | Every page slug for a version. |

`search_docs` runs a hybrid query, keyword and vector, against an Elasticsearch index of the documentation sections. The assistant usually calls `search_docs` first, then `read_doc` on the page it needs.

## See also

- [Laravel AI SDK](laravel-ai.md) to expose your own Sigmie indices as agent tools.
- [Semantic search](semantic-search.md) for the vector search the docs server uses.
- [Retrieval and Agents](rag.md) to build retrieval-augmented generation with Sigmie.
