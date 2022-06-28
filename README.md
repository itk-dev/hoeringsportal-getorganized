# Hoeringsportal – GetOrganized

```sh
docker-compose up -d
# We use kapersoft/sharefile-api which does not officially support PHP 8.1 (hence --ignore-platform-req=php)
docker-compose exec phpfpm composer install --ignore-platform-req=php
```

```sh
docker-compose run --rm node yarn --cwd /app install
docker-compose run --rm node yarn --cwd /app build

docker-compose run --rm node yarn --cwd /app watch
```
