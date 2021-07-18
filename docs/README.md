## Installation

Clone this repository, `cd` into the repository root and run:

Spin up the application 
```bash
docker-compose --project-name sigmie_app -f docker-compose.yml up --build
```

After the build process the application will be available at http://localhost:8080

## Hot Reload

When developing vue javascript components, using hot reload will speed up your development process.
To use it run the following on your **host**.

```bash
npm run hot
```
Running this on the **host** will speed up the reloading process.

The Hot reload server is running on **0.0.0.0:8081**.

**Use the `8080` port while developing, and NOT the `8081`**

## Dev Server 
To start the **Octane** dev server run:
```sh
/usr/bin/php -d variables_order=EGPCS /var/www/app/artisan octane:start --server=swoole --host=0.0.0.0 --port=8080 --watch --workers=4
```

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
