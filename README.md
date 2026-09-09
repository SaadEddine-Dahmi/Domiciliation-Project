# DomPro

DomPro is a domiciliation management project with a Laravel API backend, a Nuxt frontend, and a Flutter mobile client.

## Project Structure

```text
backend/                  Laravel API
frontend/                 Nuxt web app
mobile/                   Flutter mobile app
docker/                   Production proxy/config files
jenkins/                  Custom Jenkins image
docker-compose.yml        Local Docker stack
docker-compose.prod.yml   Production Docker stack with Caddy
docker-compose.ci.yml     CI test services
docker-compose.jenkins.yml Jenkins service
Jenkinsfile               Jenkins pipeline
```

## Requirements

- PHP 8.2+
- Composer
- Node.js and npm
- Flutter SDK
- Docker Desktop or Docker Engine with Docker Compose
- PostgreSQL, if running without Docker

## Run With Docker

From the project root:

```bash
cp backend/.env.example backend/.env
docker compose up -d --build
```

The default local services are:

- Frontend: `http://localhost:3000`
- Backend API: `http://localhost:8000`
- PostgreSQL: `localhost:5432`

Run Laravel migrations inside the backend container:

```bash
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate --force
```

Useful Docker commands:

```bash
docker compose ps
docker compose logs -f
docker compose logs -f backend
docker compose logs -f frontend
docker compose down
docker compose down -v
```

Use `docker compose down -v` only when you want to remove Docker volumes, including the local database data.

## Run Locally Without Docker

### Backend

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

The Laravel API runs by default on:

```text
http://localhost:8000
```

### Frontend

In a second terminal:

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
```

The Nuxt app runs by default on:

```text
http://localhost:3000
```

Make sure `frontend/.env` points to the backend API, for example:

```env
NUXT_PUBLIC_API_BASE=http://localhost:8000
NUXT_PUBLIC_APP_NAME=DomPro
```

### Mobile

In another terminal:

```bash
cd mobile
flutter pub get
flutter run
```

For Flutter web:

```bash
flutter run -d chrome
```

If you run the app on an Android emulator, `localhost` points to the emulator itself. Use `10.0.2.2` for the host machine backend when needed.

## Production Docker

Create a production env file from the example:

```bash
cp .env.production.example .env.production
```

Edit `.env.production`:

```env
APP_NAME=DomPro
APP_DOMAIN=your-domain.com
ACME_EMAIL=admin@your-domain.com
APP_KEY=base64:replace-with-your-laravel-key
DB_DATABASE=domiciliation
DB_USERNAME=postgres
DB_PASSWORD=change-this-password
```

Generate a Laravel key if needed:

```bash
cd backend
php artisan key:generate --show
```

Start production services:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
```

Run migrations:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml exec backend php artisan migrate --force
```

Caddy serves the app on ports `80` and `443` and handles HTTPS for `APP_DOMAIN`.

## Jenkins

Start Jenkins:

```bash
docker compose -f docker-compose.jenkins.yml up -d --build
```

Open Jenkins:

```text
http://localhost:8080
```

Get the initial admin password:

```bash
docker compose -f docker-compose.jenkins.yml exec jenkins cat /var/jenkins_home/secrets/initialAdminPassword
```

The included `Jenkinsfile` runs these stages:

- Verify Docker and Docker Compose
- Build the backend CI image
- Start the CI PostgreSQL service
- Run Laravel migrations and tests
- Build production backend and frontend images

For the pipeline to work, Jenkins must be able to access Docker. The Jenkins compose file mounts:

```text
/var/run/docker.sock:/var/run/docker.sock
```

On Windows, this is easiest from WSL2 or Docker Desktop with Linux containers enabled.

## Tests And Builds

Backend tests:

```bash
cd backend
php artisan test
```

Frontend tests:

```bash
cd frontend
npm test
```

Frontend production build:

```bash
cd frontend
npm run build
```

Mobile analysis:

```bash
cd mobile
flutter analyze
```

Mobile tests:

```bash
cd mobile
flutter test
```

## Notes

- Keep secrets in `.env` files and do not commit them.
- The Docker backend service reads `backend/.env` in local development.
- The production stack expects values from `.env.production`.
- Logo assets are served from `frontend/public/brand` and `mobile/web/brand`.
