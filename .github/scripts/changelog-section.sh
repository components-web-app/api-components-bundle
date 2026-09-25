#!/usr/bin/env bash
set -euo pipefail
tag="$1"
file="${2:-CHANGELOG.md}"
section="$(awk -v tag="$tag" '
  /^## / { if (found) exit; if (index($0, "## [" tag "]") == 1) { found = 1; next } }
  found { print }
' "$file")"
if [ -z "$(printf '%s' "$section" | tr -d '[:space:]')" ]; then
  echo "No CHANGELOG.md section for $tag. Rename Unreleased to ## [$tag](...) before tagging." >&2
  exit 1
fi
printf '%s\n' "$section" | sed -e '/./,$!d'
