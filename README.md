# Hoeringsportal – GetOrganized

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
