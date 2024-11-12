#!/bin/bash
set -Eeo pipefail
set -o errexit    # Used to exit upon error, avoiding cascading errors

IFS=$'\n\t'

cd /var/www

if [ -d vendor ]; then
    echo "vendor ok!"
else
    composer install
fi

echo "run on '$DOCKER_ENV' environment!"
echo "run with '$DOCKER_WORKERS' workers!"

# Check if `octane` command installed`
if [ php artisan | grep "octane" &> /dev/null ]; then
    if [ $DOCKER_ENV == "production" ]; then
        php artisan octane:start --host=0.0.0.0 --port=80 --workers=$DOCKER_WORKERS --task-workers=$DOCKER_WORKERS
    else
        if [ -d node_modules ]; then
            echo "node_modules ok!"
        else
            npm install
        fi

        php artisan octane:start --host=0.0.0.0 --watch --port=80 --workers=$DOCKER_WORKERS --task-workers=$DOCKER_WORKERS
    fi
else
    if [ $DOCKER_ENV == "production" ]; then
        echo "artisan serve is not running in APP_ENV=production"
        exit 1
    else
        if [ -d node_modules ]; then
            echo "node_modules ok!"
        else
            npm install
        fi

        php artisan serve --host=0.0.0.0 --port=80
    fi
fi

