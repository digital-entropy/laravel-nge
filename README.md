<div>
    <h3 align="center">Laravel Nge (en-ji)</h3>
    <p align="center">Get onboard faster with Docker for Laravel.</p>
</div>

## Introduction

Nge is boosting your experience to onboard and deploy Laravel project. Nge is compatible with macOS, Windows (WSL2), and Linux. Nge includes our pre-built image from [DokarPHP](https://github.com/digital-entropy/dokar-php) so you can focus on your software development instead of wasting your time on waiting, or building your own image.

#### Inspiration

Laravel Nge is inspired by and derived from [Laravel Sail](https://github.com/shipping-docker/vessel).

## Prerequisites

- [Docker](https://docs.docker.com/engine/install/) installed
- [Composer](http://getcomposer.org) Package Manager

## How to

1. `composer require dentro/nge:{version}`
2. Run `php artisan nge:install` and choose containers. This command generate a `docker-compose.yml`.
4. Run `./vendor/bin/nge up -d` to start your containers
5. Run `./vendor/bin/nge artisan migrate` to migrate your database
6. Access site via `http://localhost:80` by default.

### Run Container's Command

You can rnu commands via container such as `artisan`, `composer`, `npm`, `yarn`, `expose` and many more. You can run `./vendor/bin/nge --help|-h` to learn more about the available commands.

**Examples**

- `./vendor/bin/nge artisan make:controller` to make a controller
- `./vendor/bin/nge composer require some/package` to require some/package into your `composer.json`
- `./vendor/bin/nge yarn watch` to run host reload with `yarn` command. You can use `yarn`, `npm`, or `pnpm`.

## Contributing

Feel free to contribute by reporting new issues or make PRs.

## License

Laravel Nge is open-sourced software under the [MIT license](LICENSE.md).
