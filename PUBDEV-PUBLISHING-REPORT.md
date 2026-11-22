# pub.dev Publishing Configuration Report

## Files inspected

- `packages/flutter/pubspec.yaml`
- `packages/flutter/pubspec.lock`
- `packages/flutter/README.md`
- `packages/flutter/CHANGELOG.md`
- `packages/flutter/LICENSE`
- `packages/flutter/.gitignore`
- `packages/flutter/.pubignore`
- Existing `.github/workflows/` directory
- Git status, branch, remotes, tags, and recent history
- Official Dart automated publishing documentation
- Official `dart-lang/setup-dart/.github/workflows/publish.yml@v1`
- Public pub.dev package page for `proit365_multikey_auth`

## Inspection findings

- Package name: `proit365_multikey_auth`
- Package directory: `packages/flutter`
- Observed published version: `1.0.0`
- Repository metadata: `https://github.com/proit365/multikey-auth`
- Git remote: `https://github.com/proit365/multikey-auth.git`
- Current branch: `main`
- No existing GitHub workflow handled pub.dev publishing.
- No existing Git tags were found.
- No existing release-tag convention was established in local tags.
- The intended independent convention will be `flutter-v{{version}}`.
- The pub.dev admin page requires authenticated access and returned HTTP 401
  in this session, so automated publishing configuration could not be verified
  or changed without manual signed-in user action.

The `pubspec.yaml` modified-Git-state warning observed previously is explained
by the package description update in the working tree. It is not a pub.dev
configuration problem. The package also has an untracked generated `doc/`
directory; package inclusion must be checked before release.

## Files created

- `.github/workflows/publish-flutter.yml`
- `docs/PUBLISH-FLUTTER.md`
- `pubdev-publishing-progress.json`
- `PUBDEV-PUBLISHING-REPORT.md`
- `../update.bat` was updated with an optional, separately confirmed Flutter
  validation and `flutter-v{{version}}` tag-push stage.

No package or source code behavior was changed.

The package inclusion exclude list was updated in `packages/flutter/.pubignore`
to exclude generated `doc/` API output in addition to existing build and local
tool directories.

## Workflow

The workflow triggers only tags matching:

```text
flutter-v[0-9]+.[0-9]+.[0-9]+
```

It uses the official reusable workflow and requests `id-token: write` for OIDC:

```yaml
permissions:
  contents: read

jobs:
  publish:
    permissions:
      id-token: write
    uses: dart-lang/setup-dart/.github/workflows/publish.yml@v1
    with:
      environment: pub.dev
      working-directory: packages/flutter
```

The reusable workflow supports `environment`, `working-directory`, `sdk`,
`use-flutter`, and `test-run`. It uses Flutter by default and publishes the
package through temporary OIDC credentials rather than stored pub.dev secrets.

## Manual pub.dev configuration

The following operation cannot be completed by this session:

1. Sign in to pub.dev as an uploader or `proit365.com` publisher administrator.
2. Open
   <https://pub.dev/packages/proit365_multikey_auth/admin>.
3. Enable GitHub Actions automated publishing.
4. Set repository `proit365/multikey-auth`.
5. Set tag pattern `flutter-v{{version}}`.
6. If desired or required, require the `pub.dev` GitHub Actions environment.
7. Create the `pub.dev` environment in GitHub repository settings if needed.

No permanent pub.dev access token or Google credential was created.

## Validation status
Actual results:

- Workflow YAML parsed successfully with PyYAML.
- `flutter-v1.0.1` matches the package tag pattern.
- `flutter-v1.0.1-beta`, `flutter-v1.0`, `v1.0.1`, `1.0.1`,
  `composer-v1.0.1`, and `js-v1.0.1` do not match.
- `flutter pub get`: passed.
- `flutter analyze`: passed with no issues.
- `flutter test`: passed; both interoperability tests passed.
- `flutter pub publish --dry-run`: created and listed the archive, but exited
  with code 65 and a warning because `pubspec.yaml` is modified in git.
- The archive inclusion list contains only:
  `CHANGELOG.md`, `LICENSE`, `README.md`, `lib/`, `pubspec.yaml`, and `test/`.
- Generated `doc/` output was excluded through `.pubignore` and removed from
  the working tree.
- No secrets, `.dart_tool`, `build/`, `coverage/`, `pubspec.lock`, or `doc/`
  files appeared in the dry-run archive.

The modified-Git-state warning is caused by the existing package description
change in `pubspec.yaml` in the working tree, not an archive inclusion issue.
It should disappear after the release change is committed and the tag points
at that commit.

## update.bat integration

The release batch now provides a four-choice menu:

1. Full release: Composer validation, scoped commit, monorepo push, six
   Composer subtree pushes, and optional Flutter tagging.
2. Composer synchronization only.
3. Flutter-only release tagging.
4. Exit.

The batch stages an explicit allowlist instead of `git add .`, displays the
cached diff, and requires confirmation before commit. It separately confirms
the monorepo push and Composer subtree push. Flutter-only mode requires a clean
tree, verifies the semantic version and pub.dev API, verifies remote/local tags,
verifies the workflow on `origin/main`, and requires separate confirmation
before creating or pushing an annotated tag.

The parent `update.bat` now includes an optional Flutter release stage after
the existing Composer split pushes:

- Reads `version` from `packages/flutter/pubspec.yaml`.
- Refuses to republish `1.0.0`.
- Refuses tags that already exist locally or at `origin`.
- Runs `flutter pub get`, `flutter analyze`, `flutter test`, and
  `flutter pub publish --dry-run`.
- Asks for separate confirmation before creating or pushing the tag.
- Creates `flutter-v<version>` and pushes that tag to trigger GitHub Actions
  OIDC publication.

Static checks confirmed all required release gates and commands are present.
The batch was not executed because execution would stage, commit, push, or
create and push a release tag.
