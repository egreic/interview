#!/usr/bin/env bash
# Detects the stack of an API project and prints a small JSON to stdout.
#
# Usage:   detect_stack.sh [project_root]
# Default project_root is the current directory.
#
# Output (single-line JSON):
#   {
#     "stack": "symfony" | "fastapi" | "unknown",
#     "language": "php" | "python" | "unknown",
#     "php_version": "7.1.3" | null,
#     "symfony_version": "4.0" | null,
#     "nelmio_version": "3.2" | null,
#     "swagger_php_present": true | false,
#     "fosrest_present": true | false,
#     "fastapi_version": "0.110.0" | null,
#     "syntax": "nelmio-annotations" | "nelmio-attributes" | "fastapi" | null,
#     "reference_to_load": "<absolute path to references/*.md>"
#   }
#
# The "syntax" field is the decision the skill needs:
#   - PHP < 8.0  OR  Nelmio < 4.0  →  nelmio-annotations
#   - PHP >= 8.0 AND Nelmio >= 4.0 →  nelmio-attributes
#   - FastAPI present              →  fastapi
#
# Returns 0 if a stack was identified, 2 if unknown.

set -u

ROOT="${1:-.}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SKILL_DIR="$(dirname "$SCRIPT_DIR")"
REFS_DIR="$SKILL_DIR/references"

# Parse the FIRST captured group of a require/dependency line. Strips ^, ~, >=, *, etc.
strip_constraint() {
    sed -E 's/^[\^~>=<*[:space:]]+//' | sed -E 's/[[:space:]].*$//' | sed -E 's/[*].*$//'
}

# Extract a require version from composer.json. Argument: package name.
# Uses a non-slash delimiter for sed because package names contain "/".
composer_require_version() {
    local pkg="$1"
    [ -f "$ROOT/composer.json" ] || { echo ""; return; }
    grep -E "\"$pkg\"[[:space:]]*:[[:space:]]*\"" "$ROOT/composer.json" \
        | head -n1 \
        | sed -E "s|.*\"$pkg\"[[:space:]]*:[[:space:]]*\"([^\"]+)\".*|\1|" \
        | strip_constraint
}

# Extract version of a python dep from pyproject.toml or requirements.txt. Argument: dep name.
# Handles three common shapes:
#   PEP 621 list:        "fastapi>=0.110.0"
#   Poetry table line:   fastapi = "^0.110.0"
#   requirements.txt:    fastapi==0.110.0
python_dep_version() {
    local pkg="$1"
    local line=""
    if [ -f "$ROOT/pyproject.toml" ]; then
        line=$(grep -iE "[\"']?${pkg}[\"']?[[:space:]]*[=~^><]" "$ROOT/pyproject.toml" | head -n1)
    fi
    if [ -z "$line" ] && [ -f "$ROOT/requirements.txt" ]; then
        line=$(grep -iE "^[[:space:]]*${pkg}([[:space:]]|[=~<>])" "$ROOT/requirements.txt" | head -n1)
    fi
    if [ -n "$line" ]; then
        # First semver-ish token on the line. Works for ">=0.110.0", "^0.115.0", "==0.110.0".
        local v
        v=$(echo "$line" | grep -oE "[0-9]+\.[0-9]+(\.[0-9]+)?" | head -n1)
        if [ -n "$v" ]; then
            echo "$v"
        else
            # Package present but unpinned.
            echo "unpinned"
        fi
        return
    fi
    # Truly absent.
    if [ -f "$ROOT/pyproject.toml" ] && grep -iE "[\"']?${pkg}[\"']?" "$ROOT/pyproject.toml" >/dev/null 2>&1; then
        echo "unpinned"
    fi
}

# Compare two dotted versions: returns 0 if A >= B, else 1. Pads to 3 segments.
ver_ge() {
    local a="$1" b="$2"
    awk -v a="$a" -v b="$b" 'BEGIN {
        n = split(a, A, ".");
        m = split(b, B, ".");
        for (i = 1; i <= 3; i++) {
            x = (i <= n) ? A[i] + 0 : 0;
            y = (i <= m) ? B[i] + 0 : 0;
            if (x > y) exit 0;
            if (x < y) exit 1;
        }
        exit 0;
    }'
}

PHP_VERSION=""
SYMFONY_VERSION=""
NELMIO_VERSION=""
SWAGGER_PHP="false"
FOSREST="false"
FASTAPI_VERSION=""
STACK="unknown"
LANGUAGE="unknown"
SYNTAX=""
REFERENCE=""

# --- PHP / Symfony detection
if [ -f "$ROOT/composer.json" ]; then
    LANGUAGE="php"
    PHP_VERSION=$(composer_require_version "php")
    SYMFONY_VERSION=$(composer_require_version "symfony/framework-bundle")
    NELMIO_VERSION=$(composer_require_version "nelmio/api-doc-bundle")
    if [ -n "$(composer_require_version 'zircote/swagger-php')" ]; then
        SWAGGER_PHP="true"
    fi
    if [ -n "$(composer_require_version 'friendsofsymfony/rest-bundle')" ]; then
        FOSREST="true"
    fi

    if [ -n "$SYMFONY_VERSION" ] || [ -n "$NELMIO_VERSION" ] || [ "$SWAGGER_PHP" = "true" ]; then
        STACK="symfony"
        # Decide syntax. Nelmio attributes need PHP 8 AND Nelmio 4.
        if [ -n "$PHP_VERSION" ] && [ -n "$NELMIO_VERSION" ] \
            && ver_ge "$PHP_VERSION" "8.0" \
            && ver_ge "$NELMIO_VERSION" "4.0"; then
            SYNTAX="nelmio-attributes"
            REFERENCE="$REFS_DIR/nelmio-attributes.md"
        else
            SYNTAX="nelmio-annotations"
            REFERENCE="$REFS_DIR/nelmio-annotations.md"
        fi
    fi
fi

# --- FastAPI detection (run even if PHP was found — a repo could be polyglot, but FastAPI wins for FastAPI files)
if [ -f "$ROOT/pyproject.toml" ] || [ -f "$ROOT/requirements.txt" ]; then
    FASTAPI_VERSION=$(python_dep_version "fastapi")
    if [ -n "$FASTAPI_VERSION" ]; then
        if [ "$STACK" = "unknown" ]; then
            STACK="fastapi"
            LANGUAGE="python"
        fi
        if [ -z "$SYNTAX" ]; then
            SYNTAX="fastapi"
            REFERENCE="$REFS_DIR/fastapi.md"
        fi
    fi
fi

# Helpers for null-or-string JSON encoding
json_str_or_null() {
    if [ -z "$1" ]; then echo "null"; else printf '"%s"' "$1"; fi
}

cat <<EOF
{"stack":"$STACK","language":"$LANGUAGE","php_version":$(json_str_or_null "$PHP_VERSION"),"symfony_version":$(json_str_or_null "$SYMFONY_VERSION"),"nelmio_version":$(json_str_or_null "$NELMIO_VERSION"),"swagger_php_present":$SWAGGER_PHP,"fosrest_present":$FOSREST,"fastapi_version":$(json_str_or_null "$FASTAPI_VERSION"),"syntax":$(json_str_or_null "$SYNTAX"),"reference_to_load":$(json_str_or_null "$REFERENCE")}
EOF

[ "$STACK" = "unknown" ] && exit 2
exit 0
