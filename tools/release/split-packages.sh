#!/usr/bin/env bash
set -euo pipefail

usage() {
    cat <<'EOF'
Usage:
  tools/release/split-packages.sh --package <name> [--package <name> ...] [--version <x.y.z>] [--push]
  tools/release/split-packages.sh --package all [--version <x.y.z>]

Packages: core, server, client-php, laravel-server, laravel-client, cli

Without --push, compute and validate local subtree commits only.
--push updates exactly one distribution repository and never force-pushes.
--version creates the matching remote v<version> tag when used with --push.
EOF
}

requested_packages=()
push_enabled=0
release_version=''

while (($#)); do
    case "$1" in
        --package|-p)
            if (($# < 2)); then
                echo "Missing package name after $1." >&2
                exit 2
            fi
            requested_packages+=("$2")
            shift 2
            ;;
        --push)
            push_enabled=1
            shift
            ;;
        --version)
            if (($# < 2)); then
                echo "Missing version after --version." >&2
                exit 2
            fi
            release_version="$2"
            shift 2
            ;;
        --help|-h)
            usage
            exit 0
            ;;
        *)
            echo "Unknown argument: $1" >&2
            usage >&2
            exit 2
            ;;
    esac
done

if [[ -n "$release_version" &&
      ! "$release_version" =~ ^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(-[0-9A-Za-z.-]+)?(\+[0-9A-Za-z.-]+)?$ ]]; then
    echo "Version must be a semantic version without the leading v." >&2
    exit 2
fi

if ((${#requested_packages[@]} == 0)); then
    usage >&2
    exit 2
fi

if [[ " ${requested_packages[*]} " == *" all "* ]]; then
    if ((${#requested_packages[@]} != 1)); then
        echo "Use 'all' by itself or select individual packages." >&2
        exit 2
    fi
    requested_packages=(core server client-php laravel-server laravel-client cli)
fi

package_prefix() {
    case "$1" in
        core) printf '%s' 'packages/core' ;;
        server) printf '%s' 'packages/server' ;;
        client-php) printf '%s' 'packages/client-php' ;;
        laravel-server) printf '%s' 'packages/laravel-server' ;;
        laravel-client) printf '%s' 'packages/laravel-client' ;;
        cli) printf '%s' 'packages/cli' ;;
        *) echo "Unknown package: $1" >&2; return 2 ;;
    esac
}

package_repository() {
    case "$1" in
        core) printf '%s' 'https://github.com/proit365/multikey-auth-core.git' ;;
        server) printf '%s' 'https://github.com/proit365/multikey-auth-server.git' ;;
        client-php) printf '%s' 'https://github.com/proit365/multikey-auth-php-client.git' ;;
        laravel-server) printf '%s' 'https://github.com/proit365/laravel-multikey-auth-server.git' ;;
        laravel-client) printf '%s' 'https://github.com/proit365/laravel-multikey-auth.git' ;;
        cli) printf '%s' 'https://github.com/proit365/multikey-auth-cli.git' ;;
        *) echo "Unknown package: $1" >&2; return 2 ;;
    esac
}

normalize_repository_url() {
    local url="$1"
    url="${url#git@github.com:}"
    if [[ "$1" == git@github.com:* ]]; then
        url="https://github.com/$url"
    fi
    url="${url#ssh://git@github.com/}"
    if [[ "$1" == ssh://git@github.com/* ]]; then
        url="https://github.com/$url"
    fi
    url="${url%.git}"
    printf '%s' "$url" | tr '[:upper:]' '[:lower:]'
}

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
repository_root="$(cd -- "$script_dir/../.." && pwd -P)"
git_root="$(git -C "$repository_root" rev-parse --show-toplevel)"
git_root="$(cd -- "$git_root" && pwd -P)"
if [[ "$git_root" != "$repository_root" ]]; then
    echo "Expected repository root '$repository_root', found '$git_root'." >&2
    exit 1
fi

branch="$(git -C "$repository_root" branch --show-current)"
if [[ "$branch" != main ]]; then
    echo "Splits must be prepared from main; current branch is '$branch'." >&2
    exit 1
fi

status="$(git -C "$repository_root" status --porcelain --untracked-files=all)"
if [[ -n "$status" ]]; then
    echo "The worktree and index must be clean. Commit or resolve existing changes before splitting." >&2
    exit 1
fi

head_commit="$(git -C "$repository_root" rev-parse 'HEAD^{commit}')"
main_commit="$(git -C "$repository_root" rev-parse 'main^{commit}')"
if [[ "$head_commit" != "$main_commit" ]]; then
    echo "HEAD must be the current main commit." >&2
    exit 1
fi

origin_fetch="$(git -C "$repository_root" remote get-url origin)"
origin_push="$(git -C "$repository_root" remote get-url --push origin)"

selected_packages=()
for candidate in core server client-php laravel-server laravel-client cli; do
    for requested in "${requested_packages[@]}"; do
        if [[ "$candidate" == "$requested" ]]; then
            selected_packages+=("$candidate")
            break
        fi
    done
done
if ((${#selected_packages[@]} != ${#requested_packages[@]})); then
    echo "A package name is invalid or was requested more than once." >&2
    exit 2
fi
if ((push_enabled)) && ((${#selected_packages[@]} != 1)); then
    echo "Push one package per invocation. Prepare and validate dependencies before dependents." >&2
    exit 2
fi

blocked_path_pattern='(^|/)(vendor|node_modules|\.dart_tool|build|exports)(/|$)|(^|/)(composer(\.dev)?\.lock|\.phpunit\.result\.cache)$|\.(sqlite|sqlite3|db)(-(wal|shm))?$|\.(pem|p12|pfx|key|p8|jks|keystore)$|(^|/)(PUBLISHING_ARCHITECTURE_PROGRESS|REPOSITORY_TECHNICAL_AUDIT|PACKAGING-AUDIT-REPORT)\.md$|(^|/)packaging-audit-progress\.json$|client[-_]export|master-key|(^|/)[^/]+\.local\.(php|json|ya?ml|toml)$'
secret_content_pattern='-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----|AKIA[0-9A-Z]{16}|gh[pousr]_[A-Za-z0-9_]{20,}'

for package in "${selected_packages[@]}"; do
    prefix="$(package_prefix "$package")"
    target_repository="$(package_repository "$package")"
    normalized_target="$(normalize_repository_url "$target_repository")"

    if [[ "$normalized_target" == "$(normalize_repository_url "$origin_fetch")" ||
          "$normalized_target" == "$(normalize_repository_url "$origin_push")" ]]; then
        if ((push_enabled)); then
            echo "Distribution target for '$package' is the authoritative origin. Push is blocked." >&2
            exit 1
        fi
        echo "Warning: '$package' targets origin; its split can be inspected but not pushed." >&2
    fi

    if ! split_output="$(git -C "$repository_root" subtree split --prefix="$prefix" "$head_commit" 2>&1)"; then
        echo "git subtree split failed for '$package': $split_output" >&2
        exit 1
    fi
    split_commit="$(printf '%s\n' "$split_output" | grep -Eo '[0-9a-f]{40,64}' | tail -n 1 || true)"
    if [[ ! "$split_commit" =~ ^[0-9a-f]{40,64}$ ]]; then
        echo "No split commit hash was returned for '$package'." >&2
        exit 1
    fi

    source_tree="$(git -C "$repository_root" rev-parse "$head_commit:$prefix")"
    split_tree="$(git -C "$repository_root" rev-parse "$split_commit^{tree}")"
    if [[ "$source_tree" != "$split_tree" ]]; then
        echo "The subtree for '$package' does not exactly match '$prefix'." >&2
        exit 1
    fi

    split_paths="$(git -C "$repository_root" ls-tree -r --name-only "$split_commit")"
    if [[ -z "$split_paths" ]]; then
        echo "The split tree for '$package' is empty." >&2
        exit 1
    fi

    while IFS= read -r path; do
        case "$path" in
            .env|.env.*|*/.env|*/.env.*)
                if [[ "$path" != .env.example && "$path" != */.env.example ]]; then
                    echo "Unsafe local configuration in '$package' split: $path" >&2
                    exit 1
                fi
                ;;
        esac
        if [[ "$path" =~ $blocked_path_pattern ]]; then
            echo "Unsafe generated, private, or local file in '$package' split: $path" >&2
            exit 1
        fi
    done <<< "$split_paths"

    if secret_matches="$(git -C "$repository_root" grep -I -n -E -e "$secret_content_pattern" "$split_commit" -- 2>&1)"; then
        echo "Potential private credential material found in '$package' split:" >&2
        echo "$secret_matches" >&2
        exit 1
    else
        grep_status=$?
        if ((grep_status != 1)); then
            echo "Credential scan failed for '$package': $secret_matches" >&2
            exit 1
        fi
    fi

    printf '%s (%s) => %s\n' "$package" "$prefix" "$split_commit"
    if [[ -n "$release_version" ]]; then
        printf 'Version tag: v%s%s\n' "$release_version" "$([[ "$push_enabled" -eq 1 ]] || printf ' (preview only)')"
    fi

    if ((push_enabled)); then
        remote_tag_hash=''
        if [[ -n "$release_version" ]]; then
            tag_ref="refs/tags/v$release_version"
            if ! remote_tag_info="$(git -C "$repository_root" ls-remote --tags "$target_repository" "$tag_ref" "$tag_ref^{}" 2>&1)"; then
                echo "Unable to inspect remote tag '$tag_ref': $remote_tag_info" >&2
                exit 1
            fi
            remote_tag_commit=''
            remote_peeled_commit=''
            while IFS=$'\t' read -r remote_hash remote_ref; do
                case "$remote_ref" in
                    "$tag_ref") remote_tag_commit="$remote_hash" ;;
                    "$tag_ref^{}") remote_peeled_commit="$remote_hash" ;;
                esac
            done <<< "$remote_tag_info"
            remote_tag_hash="${remote_peeled_commit:-$remote_tag_commit}"
            if [[ -n "$remote_tag_hash" && "$remote_tag_hash" != "$split_commit" ]]; then
                echo "Remote tag 'v$release_version' already points to a different commit; refusing to overwrite it." >&2
                exit 1
            fi
        fi

        git -C "$repository_root" push "$target_repository" "$split_commit:refs/heads/main"
        if [[ -n "$release_version" && -z "$remote_tag_hash" ]]; then
            git -C "$repository_root" push "$target_repository" "$split_commit:refs/tags/v$release_version"
        elif [[ -n "$release_version" ]]; then
            printf "Remote tag 'v%s' already points to this split commit.\n" "$release_version"
        fi
        printf "Pushed '%s' to main.\n" "$package"
    fi
done
