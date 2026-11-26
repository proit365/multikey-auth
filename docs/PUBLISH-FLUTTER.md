# Publish the Flutter SDK

The Flutter/Dart SDK is published from the monorepo. The package remains at
`packages/flutter` and is published as `proit365_multikey_auth`.

Automated publishing uses GitHub Actions OIDC and the official
`dart-lang/setup-dart/.github/workflows/publish.yml@v1` reusable workflow. It
does not use a permanent pub.dev token or Google credentials.

## One-time pub.dev configuration

In the pub.dev package administration page:

<https://pub.dev/packages/proit365_multikey_auth/admin>

The package uploader or a publisher administrator must:

1. Open **Automated publishing**.
2. Enable publishing from GitHub Actions.
3. Set the repository to `proit365/multikey-auth`.
4. Set the tag pattern to `flutter-v{{version}}`.
5. Optionally require the GitHub Actions environment `pub.dev`.

If the environment is required on pub.dev, create a GitHub repository
environment named `pub.dev` under **Settings > Environments**. The workflow
already passes `environment: pub.dev`.

The repository cannot confirm the registry-side automated-publishing settings
or GitHub environment protection. Verify them while signed in before creating
a release tag.

Tag protection rules can additionally restrict who can create matching release
tags. This is recommended because any GitHub account allowed to push a matching
tag can trigger an authorized publish.

## Release procedure

The latest published package version verified against the pub.dev API on
2026-11-26 was `1.0.4` (after a `1.0.3` release published 2026-11-23). The registry history lists
`1.0.0`, `1.0.1`, `1.0.3`, and `1.0.4`; there is no `1.0.2` release. The package
metadata leaves `publish_to` unset, so the default registry is pub.dev.

`flutter pub publish --dry-run` validated the current `1.0.3` archive with no
warnings and one non-fatal version-increment hint: pub.dev identifies `1.0.1`
as the prior version because `1.0.2` was skipped. No package was published.

Replace `1.0.5` below with the next owner-approved version. Do not reuse or
republish an existing version.

The standalone instructions below are the supported repository workflow.
Optional local workspace release helpers are not part of this repository and
are not required.

### 1. Update and validate

Set the matching version in `packages/flutter/pubspec.yaml`:

```yaml
version: 1.0.5
```

Update `packages/flutter/CHANGELOG.md`, then run from the monorepo root:

```powershell
cd packages/flutter

flutter pub get
flutter analyze
flutter test
flutter pub publish --dry-run
```

### 2. Commit the release

Review the generated package file list and ensure no secrets or build artifacts
are included.

```powershell
git add packages/flutter/pubspec.yaml
git add packages/flutter/CHANGELOG.md
git add .github/workflows/publish-flutter.yml
git add docs/PUBLISH-FLUTTER.md

git commit -m "Prepare Flutter SDK 1.0.5 release"
git push origin main
```

Add only release-related package files when changing additional package source
or documentation.

### 3. Create and push the release tag

The tag must point to the commit whose `packages/flutter/pubspec.yaml`
contains the matching version:

```powershell
git tag -a flutter-v1.0.5 -m "Flutter SDK 1.0.5"
git push origin flutter-v1.0.5
```

### 4. Verify

- Check the run at <https://github.com/proit365/multikey-auth/actions>.
- Check the package at <https://pub.dev/packages/proit365_multikey_auth>.
- Confirm that pub.dev lists the approved version `1.0.5`.

Do not publish manually unless automated publishing is unavailable and the
package remains unchanged except for the required future version.
