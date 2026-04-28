#!/usr/bin/env bash
set -euo pipefail

CONTAINER="${OLLAMA_CONTAINER:-llm_yaprofi_ollama}"
LIMIT_CPUS="${OLLAMA_LIMIT_CPUS:-8}"
TZ_NAME="${OLLAMA_LIMIT_TZ:-Europe/Kyiv}"
DRY_RUN="${OLLAMA_LIMIT_DRY_RUN:-0}"
FORCE_HOUR="${OLLAMA_LIMIT_FORCE_HOUR:-}"
LOG_TAG="ollama-cpu-window"

if [[ -n "$FORCE_HOUR" ]]; then
    HOUR="$FORCE_HOUR"
else
    HOUR="$(TZ="$TZ_NAME" date +%H)"
fi

if (( 10#$HOUR >= 6 && 10#$HOUR < 19 )); then
    MODE="limited"
    TARGET_CPUS="$LIMIT_CPUS"
    TARGET_NANO="$(awk -v cpus="$LIMIT_CPUS" 'BEGIN { printf "%.0f", cpus * 1000000000 }')"
else
    MODE="unlimited"
    TARGET_CPUS="0"
    TARGET_NANO="0"
fi

CURRENT_NANO="$(docker inspect --format '{{.HostConfig.NanoCpus}}' "$CONTAINER" 2>/dev/null || true)"
NOW_KYIV="$(TZ="$TZ_NAME" date '+%F %T %Z')"

if [[ -z "$CURRENT_NANO" ]]; then
    logger -t "$LOG_TAG" "container $CONTAINER not found; mode=$MODE kyiv_time=$NOW_KYIV"
    if [[ "$DRY_RUN" == "1" ]]; then
        echo "container=$CONTAINER mode=$MODE target_cpus=$TARGET_CPUS current=missing kyiv_time=$NOW_KYIV"
    fi
    exit 0
fi

if [[ "$CURRENT_NANO" == "$TARGET_NANO" ]]; then
    if [[ "$DRY_RUN" == "1" ]]; then
        echo "container=$CONTAINER mode=$MODE target_cpus=$TARGET_CPUS current_nano=$CURRENT_NANO unchanged kyiv_time=$NOW_KYIV"
    fi
    exit 0
fi

if [[ "$DRY_RUN" == "1" ]]; then
    echo "container=$CONTAINER mode=$MODE target_cpus=$TARGET_CPUS current_nano=$CURRENT_NANO would_update kyiv_time=$NOW_KYIV"
    exit 0
fi

docker update --cpus "$TARGET_CPUS" "$CONTAINER" >/dev/null
logger -t "$LOG_TAG" "set $CONTAINER cpus=$TARGET_CPUS mode=$MODE kyiv_time=$NOW_KYIV"
