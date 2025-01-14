# Hoeringsportal – GetOrganized

``` shell
docker compose pull
docker compose up --detach
docker compose exec phpfpm composer install
# For production (cf. https://symfony.com/doc/current/frontend/asset_mapper.html)
docker compose exec phpfpm php bin/console asset-map:compile
```

``` shell name=site-install
task site-install
```

``` shell name=assets-build
docker compose run --rm node yarn install
docker compose run --rm node yarn build
```

``` shell name=assets-watch
docker compose run --rm node yarn watch
```
