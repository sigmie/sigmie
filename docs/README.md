## Installation

Clone this repository, `cd` into the repository root and run:

1. Spin up the containers
```bash
docker-compose --project-name app_devcontainer -f .devcontainer/docker-compose.yml up --build
```

2. Install the php dependencies
```
$ docker exec -it app composer install
```

3. Run migrations
```
$ docker exec -it app php artisan migrate
```

After the build process the application will be available at http://localhost:8080

## Domains

* Auth
* Subscription
* Notifications
* Project
	* Setting
* Cluster
* Token
* User
* Newsletter subscription
* Proxy