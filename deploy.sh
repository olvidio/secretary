#!/usr/bin/env bash
# Actualiza este clone al último tag v* de origin (producción en el CT).
# Pensado para cron. No hace falta GitHub Actions.
#
#   ./deploy.sh                 # despliega si hay tag más nuevo
#   DEPLOY_TAG=v1.0.0 ./deploy.sh
#   ./deploy.sh --dry-run
#
# El directorio del compose (docker-compose.yml) se autodetecta; si no,
# exporta COMPOSE_DIR. El servicio PHP se llama php-fpm (PHP_SERVICE).
#
# Cron, como el usuario dueño del clone (no root):
#   */5 * * * * /ruta/secretary/deploy.sh >> /var/log/secretary-deploy.log 2>&1
#
# Repo privado: clave de despliegue en el CT, p. ej.
#   GIT_SSH_COMMAND="ssh -i ~/.ssh/secretary_deploy -o IdentitiesOnly=yes"
set -euo pipefail

DRY_RUN=0
if [[ "${1:-}" == "--dry-run" ]]; then
    DRY_RUN=1
fi

log() {
    echo "[$(date -Is)] $*"
}

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$APP_DIR"

PHP_SERVICE="${PHP_SERVICE:-php-fpm}"
COMPOSE_CMD="${COMPOSE_CMD:-docker compose}"
LOCK="${LOCK:-$APP_DIR/var/deploy.lock}"

buscar_compose() {
    local d
    if [[ -n "${COMPOSE_DIR:-}" ]]; then
        echo "$COMPOSE_DIR"
        return
    fi
    for d in \
        "$APP_DIR" \
        "$APP_DIR/.." \
        "$APP_DIR/../docker" \
        /home/dani/docker_images/secretary
    do
        if [[ -f "$d/docker-compose.yml" || -f "$d/compose.yml" ]]; then
            cd "$d" && pwd
            return
        fi
    done
    return 1
}

COMPOSE_DIR="$(buscar_compose)" || {
    log "No encuentro docker-compose.yml. Exporta COMPOSE_DIR=/ruta/al/compose"
    exit 1
}

compose() {
    (cd "$COMPOSE_DIR" && $COMPOSE_CMD "$@")
}

en_php() {
    compose exec -T \
        --user "$(id -u):$(id -g)" \
        -e COMPOSER_HOME=/tmp/composer \
        "$PHP_SERVICE" "$@"
}

mkdir -p "$APP_DIR/var"
exec 9>"$LOCK"
if ! flock -n 9; then
    log "Ya hay un deploy en curso; salgo"
    exit 0
fi

if [[ ! -d "$APP_DIR/.git" ]]; then
    log "Esto no es un clone git: $APP_DIR"
    exit 1
fi

git fetch --tags --prune origin

if [[ -n "${DEPLOY_TAG:-}" ]]; then
    DESTINO="$DEPLOY_TAG"
else
    DESTINO="$(git tag --list 'v*' --sort=-v:refname | head -n 1 || true)"
fi

if [[ -z "$DESTINO" ]]; then
    log "No hay tags v* en origin. Crea uno (git tag v1.0.0 && git push origin v1.0.0) y no hago nada."
    exit 0
fi

if ! git rev-parse "refs/tags/$DESTINO" >/dev/null 2>&1; then
    log "El tag $DESTINO no existe (¿olvidaste git push origin $DESTINO?)"
    exit 1
fi

DESTINO_SHA="$(git rev-parse "$DESTINO^{commit}")"
HEAD_SHA="$(git rev-parse HEAD)"
ACTUAL_TAG="$(git describe --tags --exact-match HEAD 2>/dev/null || true)"

if [[ "$HEAD_SHA" == "$DESTINO_SHA" ]]; then
    log "Ya está en $DESTINO ($DESTINO_SHA). Nada que hacer."
    exit 0
fi

if [[ -n "$ACTUAL_TAG" && -z "${DEPLOY_TAG:-}" && -z "${FORCE:-}" ]]; then
    MAS_NUEVO="$(printf '%s\n%s\n' "$ACTUAL_TAG" "$DESTINO" | sort -V | tail -n 1)"
    if [[ "$MAS_NUEVO" == "$ACTUAL_TAG" ]]; then
        log "El clone está en $ACTUAL_TAG, más nuevo o igual que $DESTINO. No bajo de versión (FORCE=1 para forzar)."
        exit 0
    fi
fi

if [[ -n "$(git diff --name-only)" || -n "$(git diff --cached --name-only)" ]]; then
    log "El árbol de git tiene cambios locales. No despliego para no pisarlos."
    git status --short --untracked-files=no
    exit 1
fi

log "Despliegue $ACTUAL_TAG ($HEAD_SHA) → $DESTINO ($DESTINO_SHA)"
if [[ "$DRY_RUN" -eq 1 ]]; then
    log "(dry-run: no toco nada)"
    exit 0
fi

PREV_SHA="$HEAD_SHA"
DID_CHECKOUT=0

rollback() {
    local codigo=$?
    if [[ "$DID_CHECKOUT" -eq 1 && -n "$PREV_SHA" ]]; then
        log "Fallo (código $codigo). Vuelvo a $PREV_SHA"
        git checkout --detach "$PREV_SHA" || true
        en_php composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader || true
        compose restart "$PHP_SERVICE" || true
    fi
    exit "$codigo"
}
trap rollback ERR

log "Copia de seguridad de la base (código actual)"
en_php php bin/console.php db:backup

log "Checkout $DESTINO"
git checkout --detach "$DESTINO"
DID_CHECKOUT=1

log "composer install --no-dev"
en_php composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

log "db:migrate"
en_php php bin/console.php db:migrate

log "Reinicio $PHP_SERVICE (opcache)"
compose restart "$PHP_SERVICE"

trap - ERR
log "Listo: $DESTINO"
