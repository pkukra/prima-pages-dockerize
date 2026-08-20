# PrimaPages

## Requirements

* Docker
* Git
* Code Editor

## Tech Stack

* Laravel 12
* PHP 8.3 FPM
* Nginx Stable Alpine
* Inertia.js
* React
* Vite
* SQL Server
* Composer

## Docker Installation

1. Clone repository

```bash
git clone <repository-url>
cd primapages-dockerize
```

2. Build dan jalankan container

```bash
docker compose up -d --build
```

3. Install PHP dependencies

```bash
docker compose run --rm composer install
```

4. Copy environment

```bash
cp src/.env.example src/.env
```

5. Generate application key

```bash
docker compose run --rm artisan key:generate
```

6. Sesuaikan konfigurasi SQL Server pada `src/.env`

```env
DB_CONNECTION=sqlsrv
DB_HOST=...
DB_PORT=1433
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
```

7. Install dan build frontend

```bash
docker compose run --rm node npm install
docker compose run --rm node npm run build
```

8. Jalankan migration jika diperlukan

```bash
docker compose run --rm artisan migrate
```

9. Buat storage link jika diperlukan

```bash
docker compose run --rm artisan storage:link
```

## Access

```text
http://localhost:8888
```

## Docker Services

| Service    | Port     |
| ---------- | -------- |
| Nginx      | 8888     |
| PHP-FPM    | 9000     |
| SQL Server | External |

## Artisan

```bash
docker compose run --rm artisan <command>
```

Contoh:

```bash
docker compose run --rm artisan migrate
docker compose run --rm artisan db:seed
docker compose run --rm artisan optimize
```

## Composer

```bash
docker compose run --rm composer <command>
```

Contoh:

```bash
docker compose run --rm composer install
docker compose run --rm composer require <package>
```

## Frontend

```bash
docker compose run --rm node npm install
docker compose run --rm node npm run build
```

Hasil production Vite berada di:

```text
src/public/build
```
