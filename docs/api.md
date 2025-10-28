---
title: REST API
short_description: REST API endpoints for Sigmie Workbench application
keywords: [api, rest api, endpoints, workbench, http]
category: Reference
order: 1
related_pages: [api-reference]
---

# API Documentation

This document describes the REST API endpoints available in the Sigmie Workbench application.

## Base URL

All API endpoints are prefixed with `/api`.

## Authentication

Currently, the API endpoints don't require authentication, but this may change in future versions.

## Endpoints

### Connections

#### Get All Connections

Retrieves a list of all Elasticsearch connections.

```http
GET /api/connections
```

**Response:**
```json
{
  "connections": [
    {
      "id": "string",
      "name": "string",
      "host": "string",
      "port": "integer"
    }
  ]
}
```

### Indices

#### Get Indices for Connection

Retrieves all indices for a specific Elasticsearch connection.

```http
GET /api/connections/{connectionId}/indices
```

**Parameters:**
- `connectionId` (string, required): The ID of the Elasticsearch connection

**Response:**
```json
{
  "indices": [
    {
      "name": "string",
      "document_count": "integer",
      "size": "string"
    }
  ]
}
```

#### Get Index Structure

Retrieves the mapping structure for a specific index.

```http
GET /api/connections/{connectionId}/indices/{indexName}
```

**Parameters:**
- `connectionId` (string, required): The ID of the Elasticsearch connection
- `indexName` (string, required): The name of the index

**Response:**
```json
{
  "index": "string",
  "mappings": {
    "properties": {
      "field_name": {
        "type": "string"
      }
    }
  }
}
```

### Documents

#### Get Random Documents

Fetches random documents from a specified Elasticsearch index.

```http
GET /api/connections/{connectionId}/random-documents
```

**Parameters:**
- `connectionId` (string, required): The ID of the Elasticsearch connection

**Query Parameters:**
- `index` (string, required): The name of the index to fetch documents from
- `count` (integer, optional): Number of documents to retrieve (min: 1, max: 100, default: 10)

**Response:**
```json
{
  "index": "string",
  "count": "integer",
  "documents": [
    {
      "_id": "string",
      "_source": {
        "field1": "value1",
        "field2": "value2"
      }
    }
  ],
  "message": "string"
}
```

**Example Request:**
```bash
curl -X GET "http://localhost:8000/api/connections/conn-123/random-documents?index=movies&count=5"
```

**Example Response:**
```json
{
  "index": "movies",
  "count": 5,
  "documents": [
    {
      "_id": "1",
      "_source": {
        "title": "The Matrix",
        "year": 1999,
        "genre": "Sci-Fi"
      }
    },
    {
      "_id": "2",
      "_source": {
        "title": "Inception",
        "year": 2010,
        "genre": "Sci-Fi"
      }
    }
  ],
  "message": "Successfully retrieved random documents"
}
```

## Error Responses

All endpoints may return the following error responses:

### 400 Bad Request
```json
{
  "error": "Bad request",
  "message": "Invalid request parameters"
}
```

### 404 Not Found
```json
{
  "error": "Not found",
  "message": "Resource not found"
}
```

### 422 Validation Error
```json
{
  "error": "Validation failed",
  "errors": {
    "field_name": [
      "Validation error message"
    ]
  }
}
```

### 500 Internal Server Error
```json
{
  "error": "Internal server error",
  "message": "An unexpected error occurred"
}
```

## Usage Examples

### Getting Random Documents with Different Counts

```bash
# Get 1 random document
curl -X GET "http://localhost:8000/api/connections/conn-123/random-documents?index=products&count=1"

# Get 50 random documents
curl -X GET "http://localhost:8000/api/connections/conn-123/random-documents?index=logs&count=50"

# Get default number of documents (10)
curl -X GET "http://localhost:8000/api/connections/conn-123/random-documents?index=users"
```

### Working with Different Index Types

```bash
# Fetch from a user index
curl -X GET "http://localhost:8000/api/connections/conn-123/random-documents?index=users&count=10"

# Fetch from a product catalog
curl -X GET "http://localhost:8000/api/connections/conn-123/random-documents?index=product_catalog&count=25"
```

## Rate Limiting

Currently, there are no rate limits implemented, but it's recommended to implement them in production environments.

## Notes

- The random documents endpoint uses Elasticsearch's native random sampling capabilities
- Document structure will vary based on the index mapping and the actual documents stored
- The `count` parameter is capped at 100 to prevent performance issues
- All endpoints return JSON responses with appropriate HTTP status codes