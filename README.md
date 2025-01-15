# Hoeringsportal – GetOrganized

## Production

``` shell
docker compose pull
docker compose up --detach
docker compose exec phpfpm composer install
docker compose exec phpfpm bin/console doctrine:migrations:migrate --no-interaction
docker compose exec phpfpm php bin/console asset-map:compile
```

### Cron jobs

Update paths to match your actual setup.

``` shell
# Archive files every hour.
0 * * * * (cd … && docker compose exec phpfpm bin/console app:sharefile2getorganized:archive …) > /dev/null 2>&1
# Generate overviews daily at 0200
0 2 * * * (cd … && docker compose exec phpfpm bin/console app:overview:hearing …) > /dev/null 2>&1
# Combine PDFs daily at 0300
0 3 * * * (cd … && docker compose exec phpfpm bin/console app:pdf:cron …) > /dev/null 2>&1
```

## Development

``` shell name=site-install
task site-install
```

``` shell name=code-checks
task coding-standards:check
task code-analysis
```
