#!/bin/bash

while getopts bpre flag
do
    case "${flag}" in
        b) skip_backup=1;;
        pre) pre=1;;
    esac
done

if [ -z "$(git status --untracked-files=no --porcelain)" ]; then
    # 1. Make a backup of central and tenants
    if [ "$skip_backup" == 1 ]; then
        echo "Skipping backup"
    else
        php artisan backup
    fi

    # 2. set the site down
    php artisan down

    # 3. git
    git pull

    # 4. Composer
    if [ "$pre" != 1 ]; then
      php composer install --no-dev
    else
      php composer install
    fi

    # 5. Migrate
    php artisan migrate --force

    # 6. Cache - clear and re-do in pro
    php artisan optimize:clear
    if [ "$pre" != 1 ]; then
      php artisan optimize
    fi

    # 7. set the site up
    php artisan up
else
  echo "======================================"
  echo "$(tput setaf 1) ------------ Uncommitted changes!$(tput sgr0)"
  echo "======================================"
fi

