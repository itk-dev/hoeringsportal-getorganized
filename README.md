# Hoeringsportal – GetOrganized

``` shell name=site-install
docker compose pull
docker compose up --detach
docker compose exec phpfpm composer install
```

``` shell name=assets-build
docker compose run --rm node yarn install
docker compose run --rm node yarn build

```

``` shell name=assets-watch
docker compose run --rm node yarn watch
```
