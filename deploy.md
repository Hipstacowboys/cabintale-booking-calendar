# Releasing to WordPress.org

The plugin lives in two places. GitHub is where the source is developed; WordPress.org
serves it to users out of a Subversion repository:

- SVN: <https://plugins.svn.wordpress.org/cabintale-booking-calendar>
- Listing: <https://wordpress.org/plugins/cabintale-booking-calendar>

That SVN repository has four directories, and they are not a branching model:

| Directory  | What it is |
|---|---|
| `trunk/`   | The current source. Not what users download. |
| `tags/`    | One frozen copy per released version. `tags/0.7.4/` is what users download when `Stable tag: 0.7.4`. |
| `assets/`  | The listing images — banners, icon, screenshots. Never ships in the plugin zip. |
| `branches/`| Unused here. |

Users get whatever is in `tags/<Stable tag>`. If the tag directory is missing, the
listing shows a version nobody can install, so the tag and the `Stable tag:` line in
`readme.txt` must always agree.

## Credentials

The SVN password is not your WordPress.org login password. Generate one at
<https://wordpress.org/support/users/cabintale/edit/account/> under *Account & Security*.
The username is `cabintale`, case sensitive.

Store it in your password manager. If it leaks, revoke it on that same page — it only
grants SVN commit access, but that is enough to publish code to every site running the
plugin.

## Before any release

1. Bump the version in **both** places, to the same number:
   - `Version:` in the header of `cabintale-booking-calendar.php`
   - `Stable tag:` in `readme.txt`
2. Add the changelog entry under `== Changelog ==` in `readme.txt`.
3. Bump `Tested up to:` in `readme.txt` if a newer WordPress has shipped since.
4. Run the readme through <https://wordpress.org/plugins/developers/readme-validator/>.

## Route A — release from GitHub (normal case)

[`.github/workflows/deploy.yml`](.github/workflows/deploy.yml) does the SVN work. Pushing
a bare version tag deploys `trunk`, creates `tags/<version>` and syncs `.wordpress-org/`
into SVN `assets/`. Files listed in [`.distignore`](.distignore) are left out.

One-time setup — add two repository secrets under
*Settings → Secrets and variables → Actions* in the GitHub repo:

| Secret | Value |
|---|---|
| `SVN_USERNAME` | `cabintale` |
| `SVN_PASSWORD` | the SVN password from above |

Then every release is:

```bash
git commit -am "Release 0.7.5"
git push
git tag 0.7.5
git push origin 0.7.5
```

The tag must be bare — `0.7.5`, not `v0.7.5`. The workflow only triggers on that shape,
and the tag name becomes the SVN tag name. The workflow fails before touching SVN if the
tag disagrees with either version number, so a mismatch costs you a re-tag, not a broken
listing.

Watch it under the *Actions* tab. It takes a couple of minutes.

## Route B — release by hand (no GitHub, or the workflow is broken)

You need an SVN client:

```bash
brew install svn
```

### First time only: check out the SVN repository

```bash
svn checkout https://plugins.svn.wordpress.org/cabintale-booking-calendar ~/Documents/Coding/cabintale-svn
```

This is a separate working copy from the git repo. Keep it around; you only check out once.

### Every release

Update the working copy, then mirror the plugin source into `trunk/`. The excludes are
`.distignore` plus `.svn` — without that last one, `--delete` would wipe SVN's own
metadata and break the working copy.

```bash
cd ~/Documents/Coding/cabintale-svn
svn update

rsync -a --delete \
  --exclude '.svn' --exclude '.git' --exclude '.github' --exclude '.gitignore' \
  --exclude '.distignore' --exclude 'README.md' --exclude 'deploy.md' \
  --exclude '.wordpress-org' --exclude 'languages' --exclude '*.zip' \
  ~/Documents/Coding/cabintale-booking-calendar/ \
  ~/Documents/Coding/cabintale-svn/trunk/

cp ~/Documents/Coding/cabintale-booking-calendar/.wordpress-org/*.png \
   ~/Documents/Coding/cabintale-booking-calendar/.wordpress-org/*.jpg \
   ~/Documents/Coding/cabintale-svn/assets/
```

`rsync` removes deleted files from the working copy but does not tell SVN about them, so
register the additions and the removals before committing:

```bash
svn add --force . --auto-props --parents --depth infinity -q
svn status | grep '^!' | awk '{print $2}' | xargs -r svn rm
svn status
```

Read that `svn status` output before going further. `A` is added, `M` modified, `D`
deleted, `?` means SVN still does not know about the file. Nothing from `.distignore`
should appear, and no `.zip`.

```bash
svn commit -m "Release 0.7.5" --username cabintale
```

### Tag the release

```bash
svn copy trunk tags/0.7.5
svn commit -m "Tag 0.7.5" --username cabintale
```

The listing updates within a few minutes. Search results can lag up to 72 hours.

## Changing only the listing, without releasing code

Screenshots, banners, icon and the description are read from `trunk/readme.txt` and
`assets/`, not from the tag. To fix a typo or swap a screenshot with no version bump,
commit just those:

```bash
cd ~/Documents/Coding/cabintale-svn
svn update
cp ~/Documents/Coding/cabintale-booking-calendar/readme.txt trunk/readme.txt
cp ~/Documents/Coding/cabintale-booking-calendar/.wordpress-org/*.png assets/
cp ~/Documents/Coding/cabintale-booking-calendar/.wordpress-org/*.jpg assets/
svn commit -m "Update listing assets" --username cabintale
```

Do not change `Stable tag:` this way. Leave the released tag alone.

## Things that go wrong

**Never edit a published `tags/` directory.** Sites that already downloaded that version
will not see the change, and WordPress.org treats a released tag as immutable. Ship a new
patch version instead.

**Translations must not be in the zip.** `languages/` is excluded on purpose —
WordPress.org compiles every locale from translate.wordpress.org. Shipping `.mo`, `.json`
or `.l10n.php` files is a review rejection. See [`languages/README.md`](languages/README.md).

**Screenshot numbers are positional.** `screenshot-3.jpg` is described by the third line
under `== Screenshots ==` in `readme.txt`. Reordering images without reordering captions
mislabels the listing. See [`.wordpress-org/README.md`](.wordpress-org/README.md).

**A listing that shows the wrong version** almost always means `Stable tag:` in
`trunk/readme.txt` points at a `tags/` directory that does not exist. Check
<https://plugins.svn.wordpress.org/cabintale-booking-calendar/tags/>.

**`svn: E170013` / authentication failures** mean the SVN password, not the wordpress.org
account password. Regenerate it at the Account & Security link above.
