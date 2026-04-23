# Magento 2.4.7 - Laragon + Docker Setup

Panduan ini mencakup instalasi lengkap project Magento untuk dua mode:

1. Mode Laragon (native Windows)
2. Mode Docker (service database + search engine)

## 1. Stack yang Dipakai

- Magento Open Source 2.4.7
- PHP 8.1/8.2/8.3 (sesuai composer.json)
- MySQL 8
- Elasticsearch 7.17
- Web server lokal Laragon (Apache/Nginx)

## 2. Struktur Tambahan di Repo

- docker-compose.yml: service Docker untuk MySQL + Elasticsearch (+ dashboard opsional)
- docker/.env.docker.example: template environment Docker
- scripts/database/export-laragon-db.ps1: export DB dari Laragon
- scripts/database/import-docker-db.ps1: import DB ke MySQL Docker

## 3. Prasyarat

### 3.1 Untuk Mode Laragon

- Laragon terinstal
- PHP CLI dan Composer tersedia
- MySQL Laragon aktif
- Project berada di c:\laragon\www\magento

### 3.2 Untuk Mode Docker

- Docker Desktop aktif
- Port berikut kosong: 3307, 9200, 5601

## 4. Instalasi Lengkap Mode Laragon

Jalankan di root project:

```powershell
cd c:\laragon\www\magento
```

Pastikan dependency tersedia:

```powershell
composer install
```

Jika database sudah ada, lanjut ke validasi:

```powershell
php bin/magento --version
php bin/magento indexer:status
php bin/magento cache:flush
php bin/magento cron:run
php bin/magento cron:run
```

Buka aplikasi:

- Frontend: http://localhost/magento/pub/
- Register: http://localhost/magento/pub/customer/account/create/
- Admin: http://localhost/magento/pub/admin_v6zjrur/

## 5. Export Database dari Laragon (Untuk Upload/Backup)

Script export:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/database/export-laragon-db.ps1
```

Output default:

- database/magento2-laragon.sql

Jika butuh nama database lain:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/database/export-laragon-db.ps1 -DatabaseName magento2
```

## 6. Menjalankan Service Docker

Salin file env Docker:

```powershell
Copy-Item docker/.env.docker.example docker/.env.docker -Force
```

Jalankan service:

```powershell
docker compose --env-file docker/.env.docker up -d
```

Cek status:

```powershell
docker compose --env-file docker/.env.docker ps
```

Endpoint Docker:

- MySQL: 127.0.0.1:3307
- Elasticsearch: http://127.0.0.1:9200
- OpenSearch Dashboards (opsional): http://127.0.0.1:5601

## 7. Import Dump DB ke MySQL Docker

Jika sudah punya file dump di database/magento2-laragon.sql:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/database/import-docker-db.ps1
```

Script akan membuat database jika belum ada lalu import isi dump.

## 8. Menyambungkan Magento ke MySQL Docker

Update app/etc/env.php bagian koneksi default:

- host: 127.0.0.1
- dbname: magento2
- username: magento
- password: magento

Lalu jalankan:

```powershell
php bin/magento cache:flush
php bin/magento indexer:reindex
```

## 9. Elasticsearch Task (VS Code)

Task yang tersedia:

- Search Engine: Start Elasticsearch
- Search Engine: Stop Elasticsearch
- Search Engine: Reindex Catalog Search

Catatan: Jika memakai Elasticsearch dari Docker, task start/stop lokal tidak wajib dipakai.

## 10. Operasional Harian

```powershell
php bin/magento indexer:status
php bin/magento cron:run
php bin/magento cache:flush
Get-Content var/log/exception.log -Tail 100
Get-Content var/log/system.log -Tail 100
```

## 11. Troubleshooting Singkat

Jika indexer invalid:

```powershell
php bin/magento indexer:reindex
php bin/magento cron:run
```

Jika form register tidak muncul:

```powershell
Get-Content var/log/exception.log -Tail 100
php bin/magento cache:flush
```

Jika akses admin ditolak karena permission:

- Pastikan user admin berada pada role Administrators
- Pastikan ACL Magento_Backend::all aktif
