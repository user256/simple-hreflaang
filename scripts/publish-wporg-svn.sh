#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
PLUGIN_SLUG="cannyforge-hreflang"
SVN_URL="https://plugins.svn.wordpress.org/${PLUGIN_SLUG}"
PLUGIN_DIR="${REPO_ROOT}/${PLUGIN_SLUG}"
PLUGIN_FILE="${PLUGIN_DIR}/${PLUGIN_SLUG}.php"
README_FILE="${PLUGIN_DIR}/README.txt"
WPORG_ASSETS_DIR="${REPO_ROOT}/assets/wporg"
STAGING_DIR="/tmp/${PLUGIN_SLUG}-svn"
COMMIT_MESSAGE=""
DO_COMMIT=0

usage() {
  cat <<EOF
Usage: $(basename "$0") [options]

Prepare a WordPress.org plugin SVN working copy from this repository layout.

Options:
  --staging-dir PATH   SVN working copy location (default: ${STAGING_DIR})
  --message TEXT       Commit message to use with --commit
  --commit             Run svn commit after staging files
  -h, --help           Show this help
EOF
}

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Required command not found: $1" >&2
    exit 1
  fi
}

extract_plugin_version() {
  sed -n 's/^ \{0,\}\* Version: //p' "${PLUGIN_FILE}" | head -n 1
}

extract_stable_tag() {
  sed -n 's/^Stable tag: //p' "${README_FILE}" | head -n 1
}

svn_remove_missing() {
  local status_line status path

  while IFS= read -r status_line; do
    status="${status_line:0:1}"
    path="${status_line:8}"

    if [[ "${status}" == "!" ]]; then
      svn rm --force "${path}"
    fi
  done < <(cd "${STAGING_DIR}" && svn status)
}

sync_tree() {
  local src="$1"
  local dest="$2"

  mkdir -p "${dest}"
  rsync -a --delete "${src}/" "${dest}/"
}

stage_svn_tree() {
  local version="$1"

  mkdir -p "${STAGING_DIR}/trunk" "${STAGING_DIR}/assets" "${STAGING_DIR}/tags"

  sync_tree "${PLUGIN_DIR}" "${STAGING_DIR}/trunk"

  if [[ -d "${WPORG_ASSETS_DIR}" ]]; then
    sync_tree "${WPORG_ASSETS_DIR}" "${STAGING_DIR}/assets"
  else
    mkdir -p "${STAGING_DIR}/assets"
    find "${STAGING_DIR}/assets" -mindepth 1 -delete
  fi

  svn add --force "${STAGING_DIR}/trunk" "${STAGING_DIR}/assets" "${STAGING_DIR}/tags" >/dev/null
  svn_remove_missing

  if svn info "${STAGING_DIR}/tags/${version}" >/dev/null 2>&1; then
    echo "Tag ${version} already exists in SVN working copy; skipping tag creation."
  else
    svn copy "${STAGING_DIR}/trunk" "${STAGING_DIR}/tags/${version}" >/dev/null
  fi
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --staging-dir)
      STAGING_DIR="$2"
      shift 2
      ;;
    --message)
      COMMIT_MESSAGE="$2"
      shift 2
      ;;
    --commit)
      DO_COMMIT=1
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      usage >&2
      exit 1
      ;;
  esac
done

require_cmd svn
require_cmd rsync
require_cmd sed

if [[ ! -f "${PLUGIN_FILE}" ]]; then
  echo "Plugin entry file not found at ${PLUGIN_FILE}" >&2
  exit 1
fi

if [[ ! -f "${README_FILE}" ]]; then
  echo "Plugin readme not found at ${README_FILE}" >&2
  exit 1
fi

PLUGIN_VERSION="$(extract_plugin_version)"
STABLE_TAG="$(extract_stable_tag)"

if [[ -z "${PLUGIN_VERSION}" ]]; then
  echo "Could not parse plugin Version from ${PLUGIN_FILE}" >&2
  exit 1
fi

if [[ -z "${STABLE_TAG}" ]]; then
  echo "Could not parse Stable tag from ${README_FILE}" >&2
  exit 1
fi

if [[ "${PLUGIN_VERSION}" != "${STABLE_TAG}" ]]; then
  echo "Version mismatch: plugin header is ${PLUGIN_VERSION}, readme Stable tag is ${STABLE_TAG}" >&2
  exit 1
fi

if [[ -d "${STAGING_DIR}/.svn" ]]; then
  echo "Updating existing SVN working copy at ${STAGING_DIR}"
  svn update "${STAGING_DIR}"
else
  rm -rf "${STAGING_DIR}"
  echo "Checking out SVN working copy to ${STAGING_DIR}"
  svn checkout "${SVN_URL}" "${STAGING_DIR}"
fi

stage_svn_tree "${PLUGIN_VERSION}"

echo
echo "Prepared SVN working copy for version ${PLUGIN_VERSION}:"
echo "  ${STAGING_DIR}"
echo
( cd "${STAGING_DIR}" && svn status )

if [[ "${DO_COMMIT}" -eq 1 ]]; then
  if [[ -z "${COMMIT_MESSAGE}" ]]; then
    COMMIT_MESSAGE="Release ${PLUGIN_VERSION}"
  fi

  echo
  echo "Committing with message: ${COMMIT_MESSAGE}"
  svn commit -m "${COMMIT_MESSAGE}" "${STAGING_DIR}"
else
  echo
  echo "Review the working copy, then commit manually:"
  echo "  svn commit -m \"Release ${PLUGIN_VERSION}\" \"${STAGING_DIR}\""
fi
