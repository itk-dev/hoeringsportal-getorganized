# Hoeringsportal – GetOrganized

```sh
docker compose up --detach
# We use kapersoft/sharefile-api which does not officially support PHP 8.1 (hence --ignore-platform-req=php)
docker compose exec phpfpm composer install --ignore-platform-req=php
```

```sh
docker compose run node yarn install
docker compose run node yarn build

docker compose run node yarn watch
```
