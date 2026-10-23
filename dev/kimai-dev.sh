#!/usr/bin/env bash
# Starts Kimai with the plugin at http://localhost:8001 (admin/admin1234). Safe to run again.
set -euo pipefail
cd "$(dirname "$0")/.."

ADMIN_PASS=admin1234
compose() { docker compose -f dev/docker-compose.yml "$@"; }
console() { compose exec -T kimai php -d memory_limit=-1 bin/console --no-interaction "$@"; }
sql() { compose exec -T db mariadb -ukimai -pkimai kimai -N -e "$1"; }

compose up -d --build

echo "waiting for the database"
for _ in $(seq 1 60); do
  sql 'SELECT 1' > /dev/null 2>&1 && break
  sleep 2
done
sql 'SELECT 1' > /dev/null

if [[ -z "$(sql "SHOW TABLES LIKE 'kimai2_users'")" ]]; then
  console kimai:install
else
  echo "Kimai is installed already, skipping install"
fi

# each step checks its own state, so a run that stopped half way can be repeated
if [[ -z "$(sql "SELECT id FROM kimai2_users WHERE username = 'admin'")" ]]; then
  # Kimai requires at least 8 characters
  console kimai:user:create admin admin@example.test ROLE_SUPER_ADMIN "$ADMIN_PASS"
  # onboarding done, otherwise every page redirects to /wizard/intro (as in dev/ci.sh)
  sql "INSERT INTO kimai2_user_preferences (user_id, name, value) SELECT id, '__wizards__', 'intro,profile' FROM kimai2_users WHERE username = 'admin'"
fi

console kimai:reload
console kimai:bundle:holiday:install

echo "http://localhost:8001  (login admin / $ADMIN_PASS)"
