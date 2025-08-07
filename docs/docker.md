# Docker


## Dockerfile

```bash
docker run --name elasticsearch \
            -p 127.0.0.1:9200:9200 \
            -e "discovery.type=single-node" \
            docker.elastic.co/elasticsearch/elasticsearch-oss:7.10.2-amd64
```

## Docker Compose

```yaml
version: '3.4'
services:

  app:
    container_name: app # [tl! collapse:start]
    build:
      context: '.'
      target: base
    environment:
        XDEBUG_MODE: 'coverage'
        PORT: 8080
    ports:
        - '8080:8080'
    volumes:
        - ./:/var/www/app:cached # [tl! collapse:end]
    depends_on:
        - db
        - elasticsearch # [tl! ++]

  db: # [tl! collapse:start]
    container_name: db
    image: postgres:14
    environment:
      POSTGRES_DB: 'db'
      POSTGRES_USER: 'user'
      POSTGRES_PASSWORD: 'password'
      PGDATA: '/var/lib/postgresql/data/pgdata'
    ports:
      - "5432:5432"
    volumes:
      - db:/var/lib/postgresql/data/pgdata  # [tl! collapse:end]

  redis: # [tl! collapse:start]
    container_name: redis
    image: redis:5.0
    ports:
        - '6379:6379'
    volumes:
      - redis:/data # [tl! collapse:end]

  elasticsearch: # [tl! ++]
    container_name: elasticsearch # [tl! ++]
    image: docker.elastic.co/elasticsearch/elasticsearch-oss:7.10.2-amd64 # [tl! ++]
    environment: # [tl! ++]
      ES_JAVA_OPTS: -Xms512m -Xmx512m # [tl! ++]
      discovery.type: single-node # [tl! ++]
    volumes: # [tl! ++]
      - elasticsearch:/usr/share/elasticsearch/data:z # [tl! ++]
    ulimits: # [tl! ++]
      memlock: # [tl! ++]
        soft: -1 # [tl! ++]
        hard: -1 # [tl! ++]

volumes:
  db:
  redis:
  phonix:
  elasticsearch: # [tl! ++]
```
