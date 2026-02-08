# Gatherpress Develop

This plugin manages versioning, credits, and deployment tooling for the GatherPress project.

## Version Credits

Version credits are managed in `data/credits.php`. Each version is a key in the array with contributor data as values:

```php
'0.34.0-alpha.1' => array(
    'project-leaders' => array( 'mauteri', 'patricia70' ),
    'gatherpress-team' => array( 'hrmervin', 'jmarx75', ... ),
    'contributors' => array( 'kofimokome' ),
),
```

Usernames correspond to WordPress.org profile usernames. This data is used to generate `latest.json` for the credits display in the plugin settings.

## Versioning Scheme

GatherPress follows semantic versioning with pre-release stages:

| Stage | Format | Example |
|-------|--------|---------|
| Alpha | `X.Y.Z-alpha.N` | `0.34.0-alpha.1` |
| Beta | `X.Y.Z-beta.N` | `0.34.0-beta.1` |
| Release Candidate | `X.Y.Z-rc.N` | `0.34.0-rc.1` |
| Stable Release | `X.Y.Z` | `0.34.0` |
| Patch Release | `X.Y.Z` | `0.34.1` |

## Branch Strategy

### Main Branches

| Branch | Purpose |
|--------|---------|
| `main` | Current stable release. Reflects what is deployed to WordPress.org. |
| `develop` | Latest merged code. Unstable. All feature work merges here first. |

### Working Branches

| Branch Pattern | Purpose | Example |
|----------------|---------|---------|
| `version-X.Y.Z` | Release preparation branch | `version-0.34.1` |
| `merge-X.Y.Z` | Merge release changes back to develop | `merge-0.34.1` |

## Release Workflow

### Feature Release (e.g., 0.34.0)

1. **Prepare release branch**: Branch off `develop` with `version-X.Y.Z`
   ```bash
   git checkout develop
   git checkout -b version-0.34.0
   ```

2. **Update credits**: Add version entry to `data/credits.php` with contributors

3. **Test and finalize**: Make any release-specific changes, run tests

4. **Merge to main**: Once ready, merge `version-X.Y.Z` into `main`
   ```bash
   git checkout main
   git merge version-0.34.0
   ```

5. **Build plugin zip**: In the gatherpress directory, create the release artifact
   ```bash
   npm run plugin-zip
   ```
   This generates `gatherpress.zip` containing the built plugin.

6. **Create release via GitHub UI**:
   - Go to the repository's Releases page
   - Click "Create a new release"
   - Create a new tag (e.g., `0.34.0`) targeting `main`
   - Generate release notes
   - Attach the `gatherpress.zip` file to the release
   - Publish the release — this triggers deployment to WordPress.org

7. **Merge back to develop**: Create merge branch and sync changes
   ```bash
   git checkout main
   git pull origin main
   git checkout -b merge-0.34.0
   git checkout develop
   git merge merge-0.34.0
   ```

8. **Resolve conflicts**: Run the develop CLI to update version numbers and resolve any conflicts between `main` and `develop`

### Patch Release (e.g., 0.34.1)

Patches are created when a fix is needed for the stable release but `develop` has moved ahead.

1. **Branch off main**: Since `main` may be behind `develop`, branch from `main`
   ```bash
   git checkout main
   git checkout -b version-0.34.1
   ```

2. **Make patch changes**: Apply the fix, update credits in `data/credits.php`

3. **Test thoroughly**: Ensure the patch doesn't introduce regressions

4. **Merge to main**:
   ```bash
   git checkout main
   git merge version-0.34.1
   ```

5. **Build plugin zip**: In the gatherpress directory, create the release artifact
   ```bash
   npm run plugin-zip
   ```
   This generates `gatherpress.zip` containing the built plugin.

6. **Create release via GitHub UI**:
   - Go to the repository's Releases page
   - Click "Create a new release"
   - Create a new tag (e.g., `0.34.1`) targeting `main`
   - Add release notes describing the patch
   - Attach the `gatherpress.zip` file to the release
   - Publish the release — this triggers deployment to WordPress.org

7. **Merge back to develop**:
   ```bash
   git checkout main
   git pull origin main
   git checkout -b merge-0.34.1
   git checkout develop
   git merge merge-0.34.1
   ```

8. **Resolve conflicts**: Run the develop CLI to handle version conflicts. Additional manual conflict resolution may be needed depending on how far `main` and `develop` have diverged.

## Deployment

Publishing a release via the GitHub UI triggers automatic deployment to WordPress.org via GitHub Actions. Tags should only target the `main` branch after all changes have been merged and tested.

## Pre-release Testing

For alpha, beta, and RC releases:

1. Follow the same process as a feature release
2. Use the appropriate version format (e.g., `0.34.0-alpha.1`)
3. These versions can be deployed for testing but are not promoted as stable

## Common Tasks

### Adding a Contributor

Edit `data/credits.php` and add the WordPress.org username to the appropriate array for the current version:

```php
'0.34.0' => array(
    'contributors' => array( 'existing-user', 'new-contributor' ),
),
```

### Checking Current Stable Version

The `main` branch always reflects the current stable release deployed to WordPress.org.

### Finding Divergence Between Branches

```bash
git log main..develop --oneline  # Commits in develop not in main
git log develop..main --oneline  # Commits in main not in develop
```
