# Magento 2.4.7 (Windows + Laragon)

Panduan ini berisi langkah menjalankan project Magento dari nol sampai siap dipresentasikan.

## 1. Ringkasan

- Framework: Magento Open Source 2.4.7
- PHP: 8.1/8.2/8.3 (sesuai `composer.json`)
- Database: MySQL (`magento2`)
- Search Engine: Elasticsearch 7 (`localhost:9200`)
- Root URL: `http://localhost/magento/pub/`
- Admin URL: `http://localhost/magento/pub/admin_v6zjrur/`

## 2. Prasyarat

- Windows + Laragon aktif (Apache/Nginx + MySQL)
- PHP CLI tersedia di terminal
- Composer tersedia
- Database `magento2` sudah ada
- Elasticsearch berjalan di `http://localhost:9200`

## 3. Menjalankan Project

Jalankan dari root project:

```powershell
cd c:\laragon\www\magento
```

### 3.1 Pastikan Elasticsearch hidup

Opsi VS Code Task:

- `Search Engine: Start Elasticsearch`

Atau cek manual:

```powershell
try { (Invoke-WebRequest -Uri http://localhost:9200 -UseBasicParsing).StatusCode } catch { $_.Exception.Message }
```

Jika sukses, akan keluar `200`.

### 3.2 Jalankan validasi dasar Magento

```powershell
php bin/magento --version
php bin/magento indexer:status
```

Jika ada indexer `Reindex required`, jalankan:

```powershell
php bin/magento indexer:reindex
```

### 3.3 Jalankan cron manual (untuk update job terjadwal)

```powershell
php bin/magento cron:run
php bin/magento cron:run
```

### 3.4 Flush cache

```powershell
php bin/magento cache:flush
```

### 3.5 Buka aplikasi

- Frontend: `http://localhost/magento/pub/`
- Register customer: `http://localhost/magento/pub/customer/account/create/`
- Admin: `http://localhost/magento/pub/admin_v6zjrur/`

## 4. Login Admin

Credential yang saat ini dipakai lokal:

- Username: `admin`
- Password: `Admin@12345`

Disarankan ganti password setelah login pertama.

## 5. Command Harian yang Sering Dipakai

```powershell
# Cek kesehatan indexer
php bin/magento indexer:status

# Reindex search saja
php bin/magento indexer:reindex catalogsearch_fulltext

# Jalankan cron
php bin/magento cron:run

# Flush cache
php bin/magento cache:flush

# Cek log error
Get-Content var/log/exception.log -Tail 100
Get-Content var/log/system.log -Tail 100
```

## 6. Troubleshooting Cepat

### 6.1 Dashboard admin menampilkan "One or more indexers are invalid"

```powershell
php bin/magento indexer:reindex
php bin/magento cron:run
```

### 6.2 Halaman create account tampil tanpa form

- Cek `var/log/exception.log`
- Lalu flush cache:

```powershell
php bin/magento cache:flush
```

### 6.3 Muncul pesan "More permissions are needed to access this"

- Biasanya karena ACL admin tidak sinkron.
- Pastikan user admin terhubung ke role `Administrators` dan punya rule `Magento_Backend::all`.

## 7. Catatan Penting Project Ini

- Instance ini sudah berjalan untuk frontend, register, admin, indexing, dan cron.
- Beberapa command setup/module standar Magento tidak tersedia pada build ini, jadi fokus demo pada flow aplikasi yang berjalan.

## 8. Checklist Presentasi (H-1 / H-0)

Jalankan ini sebelum presentasi:

```powershell
cd c:\laragon\www\magento
php bin/magento cache:flush
php bin/magento indexer:status
php bin/magento cron:run
```

Checklist:

- Frontend homepage terbuka
- Halaman register customer menampilkan form lengkap
- Login admin berhasil
- Dashboard admin terbuka tanpa error popup
- Tidak ada error baru di `var/log/exception.log`
