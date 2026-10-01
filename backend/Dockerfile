# Symfony app, dev/debug image (APP_ENV=dev, APP_DEBUG=1).
# This container is the authoritative PHP 8.5 runtime for the project:
# composer.json pins config.platform.php=8.5.0 so dependency resolution
# always targets PHP 8.5 even when composer runs on an older host PHP.
FROM dunglas/frankenphp:1.12-php8.5

RUN install-php-extensions pdo_pgsql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl git unzip \
    && rm -rf /var/lib/apt/lists/*

# Serve plain HTTP only; the compose stack publishes it on host port 8080.
ENV SERVER_NAME=:80

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-progress --no-interaction

COPY . .

# Dev defaults mirroring backend/.env.example. backend/.env is gitignored,
# so a clean checkout has none: APP_RUNTIME_OPTIONS stops the runtime from
# booting Dotenv (which throws when the file is missing) and real env vars
# drive everything. APP_SECRET is a fixed dev-only constant, not a real
# secret — set a real one for any non-dev deployment.
ENV APP_ENV=dev \
    APP_DEBUG=1 \
    APP_SECRET=dev-only-secret-not-production \
    APP_RUNTIME_OPTIONS='{"disable_dotenv":true}' \
    DEFAULT_URI=http://localhost:8080 \
    DATABASE_URL=postgresql://app:app@db:5432/app?serverVersion=18&charset=utf8
