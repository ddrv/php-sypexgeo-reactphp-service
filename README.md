# ddrv/sypexgeo-reactphp-server

> Webserver for defining geo by IP address based on [SypexGeo](https://sypexgeo.net) and [reactPHP](https://reactphp.org/)

# Install

```text
cd /path/to
composer create-project ddrv/sypexgeo-reactphp-server project
```

# Run

```text
php /path/to/project 0.0.0.0:8080
```

The first time you start the database, it will be loaded automatically.
Database updates are requested automatically every hour.

For more stable operation, it is recommended to configure automatic server restart, 
for example, using the systemctl service
