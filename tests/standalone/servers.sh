#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "$0")/../.." && pwd)"
version="$(sed -n 's/^version=//p' "${root}/beacon/version.properties")"
artifacts="$(mktemp -d)"
fpm="beacon-fpm-test-$$"
apache="beacon-apache-test-$$"
cleanup() {
  docker logs "${fpm}" 2>&1 || true
  docker logs "${apache}" 2>&1 || true
  docker rm -f "${fpm}" "${apache}" >/dev/null 2>&1 || true
  rm -rf "${artifacts}"
}
trap cleanup EXIT
cp "${root}/dist/beacon-php-runtime-${version}.tar.gz" "${artifacts}/"
for php_version in 8.3 8.4; do
  php "${root}/tests/standalone/native-asset.php" "${artifacts}" "${php_version}" >/dev/null
done
endpoint="${1:-http://host.docker.internal:4318}"
network_args=(--add-host=host.docker.internal:host-gateway)
if [[ -n "${BEACON_TEST_DOCKER_NETWORK:-}" ]]; then
  network_args+=(--network "${BEACON_TEST_DOCKER_NETWORK}")
fi
common=(--detach "${network_args[@]}" --volume "${root}:/app:ro" --volume "${artifacts}:/artifacts:ro" --workdir /app)
docker run "${common[@]}" --name "${fpm}" --publish 127.0.0.1::9000 \
  php@sha256:1b6bb4f3d32dfbcd1e159d7bd47caa8852507f61666db7731d73f34acec781b6 \
  sh -c 'php dist/beacon-setup.php --php-bin=all --offline-dir=/artifacts --service-name=beacon-standalone-fpm-validation --otlp-endpoint="$1" && exec php-fpm -F' sh "${endpoint}" >/dev/null
docker run "${common[@]}" --name "${apache}" --publish 127.0.0.1::80 \
  php@sha256:cf2be6b00bfbf09563001476d80f8629ba196dd94a1bb4b2a057acc689645583 \
  sh -c 'php dist/beacon-setup.php --php-bin=all --offline-dir=/artifacts --service-name=beacon-standalone-apache-validation --otlp-endpoint="$1" && cp tests/standalone/fixtures/server.php /var/www/html/index.php && exec apache2-foreground' sh "${endpoint}" >/dev/null
fpm_port="$(docker port "${fpm}" 9000 | cut -d: -f2)"
apache_port="$(docker port "${apache}" 80 | cut -d: -f2)"
for attempt in {1..60}; do
  if docker logs "${fpm}" 2>&1 | grep -q 'ready to handle connections' && curl --fail --silent "http://127.0.0.1:${apache_port}/index.php" > "${artifacts}/response"; then
    break
  fi
  test "${attempt}" -lt 60
  sleep 1
done
grep -Fx SERVER_OK "${artifacts}/response"
python3 "${root}/tests/standalone/fcgi.py" "${fpm_port}" /app/tests/standalone/fixtures/server.php
