#!/usr/bin/env bash
# Reads git tags from stdin and prints the next version: vMAJOR.MINOR.PATCH, or
# vMAJOR.MINOR.PATCH-rc.N with --rc.
# The bump is applied to the latest stable tag (vX.Y.Z); starts from v0.0.0 if none exist.
# RC numbers continue from existing vX.Y.Z-rc.N tags of the resulting version.
#
# Usage: git tag --list 'v*' | next-version.sh <patch|minor|major> [--rc]
set -euo pipefail

usage() {
  echo "Usage: $0 <patch|minor|major> [--rc]" >&2
  exit 1
}

[[ $# -ge 1 && $# -le 2 ]] || usage
bump="$1"
rc="${2:-}"
case "$bump" in
  patch | minor | major) ;;
  *) usage ;;
esac
case "$rc" in
  "" | --rc) ;;
  *) usage ;;
esac

tags="$(cat)"

latest="$(grep -E '^v[0-9]+\.[0-9]+\.[0-9]+$' <<< "$tags" | sed 's/^v//' | sort -t. -k1,1n -k2,2n -k3,3n | tail -n 1 || true)"
IFS=. read -r major minor patch <<< "${latest:-0.0.0}"

case "$bump" in
  major) major=$((major + 1)); minor=0; patch=0 ;;
  minor) minor=$((minor + 1)); patch=0 ;;
  patch) patch=$((patch + 1)) ;;
esac

version="v${major}.${minor}.${patch}"

if [[ -n "$rc" ]]; then
  last_rc="$(grep -E "^${version//./\\.}-rc\.[0-9]+$" <<< "$tags" | sed 's/.*-rc\.//' | sort -n | tail -n 1 || true)"
  version="${version}-rc.$((10#${last_rc:-0} + 1))"
fi

echo "$version"
