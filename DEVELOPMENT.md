# Development

```bash
git clone git@github.com:mysticeragames/MakeItStatic-CMS.git

cd MakeItStatic-CMS

composer install
```

```bash
# Build + test container locally:
.cmd/build-and-test.sh
```

```bash
# Test:
vendor/bin/phpunit
vendor/bin/phpcs
vendor/bin/phpcs --warning-severity=0  # show only errors
# Autofix:
vendor/bin/phpcbf

# Start new branch
git checkout main
git pull
git checkout -b my-branch

# Make sure this branch keeps up-to-date with the main branch
git fetch origin main
git rebase main

# Push changes
git add .
git commit -m "updates"
git push -u origin my-branch
# Make pull request on github.com -> After merge the branch is automatically removed
https://github.com/mysticeragames/MakeItStatic-CMS/compare/main...my-branch

# Switch back to main branch,
# + Fetch remote branches, and remove non-existing remote branches locally
git checkout main
git pull

# When message: "hint: You have divergent branches and need to specify how to reconcile them."
git reset --hard origin/main # NOTE: THIS WILL REMOVE ALL LOCAL CHANGES!

git fetch --prune
git branch -a
git branch -D my-branch
```

## TODO LIST

- Create base docker image in other repo and use that instead of installing php etc for every test:
  - mysticeragames/makeitstatic-cms-base:1.0.0 (for cms + generator)
  - mysticeragames/makeitstatic-generator-base:1.0.0 (for only generator console app)
  - mysticeragames/makeitstatic-serve:1.0.0 (nginx to serve static files + assets)
- Allow pages to be created
- Allow pages to be edited
- Allow pages to be removed
- Allow npm to be called from the user content (npm install ci && npm run build)
- Add option to minify the HTML/css/js output
- Add option to set a maximum filesize before submitting to a git repository, to avoid uploading many videos to a content-repository for example while they should have been uploaded to an S3 bucket or something + also the allowed extensions to commit (to keep the repository clean). If you want to save content, it should check this, and abort the commit if you have a large video file in there for example. Then you can move the video file to an external storage first, and then commit the content.
    ```yaml
    # /content/repo1/sites/site1/config.md:
    ---
    gitContentMaxSizePerFile: 1MB # case insensitive, spaces are ignored: '5 kb', '6KB', '25MB', '2GB'
    
    gitContentAllowedFilesOnCommit:
        # (regex)
        - ^.htaccess$ # only works in rootdir on apache
        - .ico$
        - .scss$
        - .css$
        - .js$
        - .md$
        - .txt$
        - .xml$
        - .html$
        - .gif$
        - .jpg$ # note: only small files;
        - .png$ # it will still look at 'max-size-per-file'

    gitContentEnableLFS: false # (perhaps look at GIT LFS in the future, to just allow to push everything to the content directory!...)
    ---
    ```
- Allow multiple sites: /content/repo1/sites/site1/index.md
- Make the root path redirect to /cms (this package should not be used in the cloud serving files directly, so it has no use to keep the '/---cms' url, instead keep it simple, and make the CMS the default when you visit.)
- Make all render urls like this: /render/site1/index
- Add posibility to make reusable themes: /content/themes/my-dark-theme ( /content/repo1/themes/* /content/repo2/themes/*, are all used by the system. First one found is the one that is used if the names are the same. )
- Use theme:
    ```yaml
    # entire site: /content/repo1/sites/site1/config.md:
    ---
    theme: my-dark-theme
    ---

    # or just specific pages: /content/repo1/sites/site1/games/reign-of-cats/index.md
    ---
    title: Reign of Cats
    theme: game-reign-of-cats-theme
    ---

    # perhaps also allow to set directories to have a custom theme: /content/repo1/sites/site1/config.md:
    ---
    theme:
          # Default for the website (no path, or empty path)
        - my-dark-theme

          # Game-theme for /games/reign-of-cats/*
        - game-reign-of-cats-theme
          path: games/reign-of-cats

          # Game-theme for /games/age-of-jura
        - game-age-of-jura-theme
          path: games/age-of-jura
    ---

    ```
- Create proper test suite.
- Separate render class (make own package, usable in custom docker image, for CI purposes: then you can even edit files directly in Gitlab/Github, and the CI will generate the files and a next job can deploy them somewhere...)
- Allow the content folder to be just 'there', mount git repositories elsewhere: '/mounts/content/my-repo-1', then you also can just do a 'git pull', to get the latest state, then remove all files in the working dir, then copy all files from /content/ to the git working dir, and then 'add .' and 'commit' and 'push', to avoid nasty merge conflicts. If a merge conflict appears, just reset the state, pull the latest changes again, and try it again...
- Then you could also connect many git repositories if you want to edit multiple sites within the CMS, but want to keep the repositories separated from each other.
- Remove strict GIT dependency for generated directory:
    - Git repository (git-submodule) (recommended)
    - Just a folder: /mounts/generated/ (Mountable by docker volume for example, so generated files are created on the host)
- quick-save content in a separate branch: (for example 'concept', or select your own branch), and only when pressing 'publish', save it in the main branch (overwriting all files in the main branch!)

## Commands

```bash
# Make sure no errors are found
php bin/console cache:clear

# Update
composer update

# Test
php bin/phpunit

# Make sure route priorities are correct (wildcards on the bottom)
php bin/console debug:router


# Copy all files to test repo
git clone git@github.com:mysticeragames/makeitstatic-cms.git
git clone git@github.com:mysticeragames/test-makeitstatic-cms.git
rsync -av makeitstatic-cms/ test-makeitstatic-cms/ --exclude=.git

# delete all other files from target
rsync -av makeitstatic-cms/ Test-makeitstatic-cms/ --exclude=.git --delete

```

## Locations

This project
- https://github.com/mysticeragames/makeitstatic-cms

Packagist (for 'composer create-project ...')
- https://packagist.org/packages/mysticeragames/makeitstatic-cms

Docker (TODO: create image with all dependencies)
- https://hub.docker.com/u/mysticeragames

## Resources

Markdown
- https://commonmark.thephpleague.com

Route priority
- https://symfony.com/doc/current/routing.html#priority-parameter

Testing
- https://symfony.com/doc/current/testing.html

Image processing

- https://glide.thephpleague.com
- https://imagemagick.org

FFMPEG

- https://www.ffmpeg.org

