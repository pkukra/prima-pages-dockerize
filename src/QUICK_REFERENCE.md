# QUICK REFERENCE - INCOMING MAIL WORKFLOW

## 🎯 Fitur yang Diimplementasikan

### A. Tabel Wakil Direksi ✅
- File: `database/migrations/2026_02_04_000000_create_wakil_direksis_table.php`
- Model: `app/Models/WakilDireksi.php`
- Struktur:
  ```
  id (uuid, PK)
  user_id (bigint, FK, unique)
  created_at, updated_at
  ```

### B. Tracking Baca Surat ✅
- File: `database/migrations/2026_02_04_000001_create_incoming_mail_reads_table.php`
- Model: `app/Models/IncomingMailRead.php`
- Struktur:
  ```
  id (uuid, PK)
  incoming_mail_id (uuid, FK)
  user_id (bigint, FK)
  read_at (timestamp)
  created_at, updated_at
  unique(incoming_mail_id, user_id)
  ```
- Auto-track: Saat user buka halaman detail surat

### C. Filter List Surat ✅
- Frontend: `resources/js/Pages/IncomingMail/Index.jsx`
- Filter options:
  - "Sudah Dibaca" - mails yang sudah user baca
  - "Belum Dibaca" - mails yang belum user baca
  - Null/All - semua mails
- Status badges:
  - Green: "Sudah Dibaca"
  - Orange: "Belum Dibaca"
  - Blue: "✓ Dirut Sudah Baca"
  - Cyan: "✓ Semua Wakil Direksi Baca"

### D. Workflow Menuju List Dirut ✅
- Helper: `app/Helpers/IncomingMailHelper.php`
- Method: `allWadirRead($incomingMailId)` - check semua wakil direksi sudah baca
- Endpoint: `PATCH /incoming-mails/{id}/ready-dirut`
- Status flow: NEW → READY_DIRUT
- Validasi: Admin hanya bisa set READY_DIRUT jika semua wadir sudah baca
- UI: Button "Siapkan untuk Dirut" di detail page
  - Disabled jika ada wadir yang belum baca
  - Show list unread wadir direksi

### E. Disposisi berbasis Role ✅
- Existing structure di: `Disposition.php` & `DispositionController.php`
- Untuk READY_DIRUT mails:
  - Dirut: bisa buat disposisi ke unit atau wakil direksi
  - Wakil direksi: bisa buat disposisi ke unit
  - Via nested routes: POST/PATCH/DELETE `/{id}/dispositions`

### F. Routing & Controller ✅
Endpoints baru:
```
POST   /incoming-mails/{id}/read          → markAsRead()
GET    /incoming-mails/{id}/unread-wadir  → getUnreadWadir()
PATCH  /incoming-mails/{id}/ready-dirut   → setReadyForDirut()
```

Updated endpoint:
```
GET    /incoming-mails/list (query: ?filter=read|unread)
```

---

## 📋 Implementation Checklist

### Database
- [x] Create wakil_direksis migration
- [x] Create incoming_mail_reads migration
- [x] Create WakilDireksi model
- [x] Create IncomingMailRead model
- [x] Add relationships to models

### Backend
- [x] Create IncomingMailHelper class
- [x] Update IncomingMailRepository
  - [x] list_incoming_mails($filter)
  - [x] markAsRead($id)
  - [x] setReadyForDirut($id)
  - [x] getUnreadWadir($id)
- [x] Update IncomingMailController
  - [x] list_incoming_mails() with filter
  - [x] markAsRead($id)
  - [x] setReadyForDirut($id)
  - [x] getUnreadWadir($id)
- [x] Update routes/incoming_mail.php

### Frontend
- [x] Update Index.jsx
  - [x] Add filter select
  - [x] Add status column dengan badges
  - [x] Refetch saat filter berubah
- [x] Update View.jsx
  - [x] Auto-mark as read on mount
  - [x] Fetch & display unread wadir list
  - [x] Button "Siapkan untuk Dirut"
  - [x] Disable button logic

### Documentation
- [x] Update APP_OVERVIEW.md
- [x] Create WORKFLOW_IMPLEMENTATION.md

---

## 🔐 Security Points

1. **Authentication:** Middleware `auth` on all routes
2. **Authorization:** 
   - CheckRole:superadmin untuk list/view/ready-dirut
   - Per-user read tracking
3. **Validation:**
   - Filter parameter validation
   - Unique constraint di DB
   - Status code validation
4. **Data Integrity:**
   - Cascade delete on FK
   - Atomic operations

---

## 🧪 Quick Test Commands

```bash
# Run migrations
php artisan migrate

# View in browser
# 1. Admin upload surat: /incoming-mails/add
# 2. User roles wadir buka: /incoming-mails/view/{id} (auto-mark read)
# 3. Admin list dengan filter: /incoming-mails (select filter)
# 4. Admin siapkan dirut: click "Siapkan untuk Dirut" button

# Check database
# SELECT * FROM wakil_direksis;
# SELECT * FROM incoming_mail_reads;
# SELECT * FROM incoming_mails WHERE status_code = 'READY_DIRUT';
```

---

## 📁 Files Modified/Created

```
Created:
├── database/migrations/2026_02_04_000000_create_wakil_direksis_table.php
├── database/migrations/2026_02_04_000001_create_incoming_mail_reads_table.php
├── app/Models/WakilDireksi.php
├── app/Models/IncomingMailRead.php
├── app/Helpers/IncomingMailHelper.php
└── WORKFLOW_IMPLEMENTATION.md

Modified:
├── app/Repositories/IncomingMail/IncomingMailRepository.php
├── app/Http/Controllers/IncomingMailController.php
├── routes/incoming_mail.php
├── resources/js/Pages/IncomingMail/Index.jsx
├── resources/js/Pages/IncomingMail/View.jsx
└── APP_OVERVIEW.md
```

---

## 🚀 Next Steps (Optional)

1. **Seeding Wakil Direksi:**
   - Buat seeder atau UI admin untuk menambah wakil direksi ke tabel

2. **Status Codes:**
   - Pastikan 'READY_DIRUT' sudah ada di `mail_statuses` table

3. **List Dirut:**
   - Filter/view untuk mails dengan status = 'READY_DIRUT' (existing dispositions list)

4. **Notifications:**
   - Notify wadir ketika ada surat baru untuk dibaca
   - Notify dirut ketika surat siap untuk disposisi

5. **Dashboard:**
   - Counter untuk unread mails per user
   - Pending approvals untuk admin

---

## 📞 Support

Lihat dokumentasi lengkap di:
- `APP_OVERVIEW.md` - Full API & Model documentation
- `WORKFLOW_IMPLEMENTATION.md` - Detailed implementation guide
- Code comments di class files untuk detail logic

---

**Last Updated: February 4, 2026**
