# ChatApp Backend (Laravel + Reverb)

Backend API + Realtime server for ChatApp built with **Laravel** and **Laravel Reverb**
(using the Pusher protocol).

## Features
- REST API (auth, users, conversations, messages)
- Realtime WebSocket via Reverb
- Presence channel: `presence-global`
- Private channel: `private-user.{id}`

## Requirements
- PHP >= 8.1
- Composer
- MySQL / MariaDB

## Installation
```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
```

## Reverb / WebSocket
Required `.env`:
```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=local
REVERB_APP_KEY=local
REVERB_APP_SECRET=local
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
```

## Run Backend + Reverb (Concurrent)
```powershell
concurrently `
  -n "APP,REVERB" `
  -c "blue,magenta" `
  "php artisan serve" `
  "php artisan reverb:start --debug"
```
