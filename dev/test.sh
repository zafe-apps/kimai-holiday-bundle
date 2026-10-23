#!/usr/bin/env bash
# Plugin unit tests inside the Kimai container (no PHP needed on the host).
set -euo pipefail
cd "$(dirname "$0")"
docker compose -f docker-compose.yml run --rm -T kimai \
  vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
  --colors=always var/plugins/HolidayBundle/Tests "$@"
