# Database Dump Folder

Folder ini dipakai untuk menyimpan dump database project.

## File yang diharapkan

- `database/magento2-laragon.sql`

## Cara generate dump dari Laragon

```powershell
powershell -ExecutionPolicy Bypass -File scripts/database/export-laragon-db.ps1
```

## Cara import ke MySQL Docker

```powershell
powershell -ExecutionPolicy Bypass -File scripts/database/import-docker-db.ps1
```
