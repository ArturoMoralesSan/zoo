#!/usr/bin/env bash
#
# Revisa solo los archivos que cambian respecto a una rama base: pint, eslint,
# prettier y vue-tsc. El repositorio tiene deuda de estilo y tipos, así que
# CI no los corre sobre todo el código; cada cambio deja limpios los archivos
# que toca. PHPStan sí corre completo, con phpstan-baseline.neon.
#
# Uso: bash .github/scripts/check-changed-files.sh [rama-base]   (origin/master por omisión)

set -uo pipefail

base="${1:-origin/master}"
status=0

mapfile -t changed < <(git diff --name-only --diff-filter=ACMR "${base}...HEAD")

php_files=()
js_files=()

for file in "${changed[@]}"; do
    case "$file" in
        *.php) php_files+=("$file") ;;
        resources/*.vue | resources/*.ts | resources/*.js) js_files+=("$file") ;;
    esac
done

echo "Archivos PHP cambiados: ${#php_files[@]}; archivos JS/TS/Vue cambiados: ${#js_files[@]}"

if ((${#php_files[@]})); then
    echo "::group::pint"
    vendor/bin/pint --test "${php_files[@]}" || status=1
    echo "::endgroup::"
fi

if ((${#js_files[@]})); then
    echo "::group::eslint"
    npx eslint --no-warn-ignored "${js_files[@]}" || status=1
    echo "::endgroup::"

    echo "::group::prettier"
    npx prettier --check "${js_files[@]}" || status=1
    echo "::endgroup::"

    # vue-tsc no revisa archivos sueltos: se corre completo y se filtran los
    # errores de los archivos cambiados.
    echo "::group::vue-tsc"
    tsc_output="$(npx vue-tsc --noEmit 2>&1)"
    tsc_errors=""

    for file in "${js_files[@]}"; do
        tsc_errors+="$(grep -F "${file}(" <<<"$tsc_output" || true)"
    done

    if [[ -n "$tsc_errors" ]]; then
        echo "$tsc_errors"
        status=1
    else
        echo "Sin errores de tipos en los archivos cambiados."
    fi
    echo "::endgroup::"
fi

exit "$status"
