# IMPLEMENTASI FITUR WORKFLOW INCOMING MAIL

## 📋 Ringkasan
Implementasi fitur workflow untuk surat masuk dengan tracking baca, approval ke Dirut, dan disposisi berbasis role.

**Tanggal:** February 4, 2026  
**Status:** ✅ Complete

---

## 🎯 Alur Workflow

```
1. Admin upload surat → status = 'NEW'
                          ↓
2. Surat otomatis ter-track sebagai "Belum Dibaca" oleh Wakil Direksi & Dirut
                          ↓
3. Setiap Wakil Direksi buka detail surat → otomatis ter-mark "Sudah Dibaca"
   (Dirut juga bisa baca, status terpisah)
                          ↓
4. Admin melihat list dengan filter:
   - "Belum Dibaca" - surat belum dibaca user login
   - "Sudah Dibaca" - surat sudah dibaca user login
   - Badge "✓ Semua Wakil Direksi Baca" - jika semua wadir sudah baca
   - Badge "✓ Dirut Sudah Baca" - jika dirut sudah baca
                          ↓
5. Saat membuka detail surat, Admin melihat:
   - List Wakil Direksi yang belum baca
   - Button "Siapkan untuk Dirut" (enabled hanya jika semua wadir sudah baca)
                          ↓
6. Admin klik "Siapkan untuk Dirut" → status berubah 'READY_DIRUT'
                          ↓
7. Surat muncul di list Dirut (filter status = 'READY_DIRUT')
                          ↓
8. Dirut buka surat → otomatis ter-mark read
                          ↓
9. Dirut & Wakil Direksi bisa membuat disposisi ke unit
```

---

## 📁 File yang Dibuat/Diupdate

### A. Migrations (NEW)
1. **`database/migrations/2026_02_04_000000_create_wakil_direksis_table.php`**
   - Tabel `wakil_direksis` dengan kolom: id (uuid), user_id (unique FK), timestamps

2. **`database/migrations/2026_02_04_000001_create_incoming_mail_reads_table.php`**
   - Tabel `incoming_mail_reads` dengan kolom: id (uuid), incoming_mail_id (FK), user_id (FK), read_at (timestamp)
   - Unique constraint: (incoming_mail_id, user_id)

### B. Models (NEW)
1. **`app/Models/WakilDireksi.php`**
   - Properties: id (uuid), user_id
   - Relationship: `user()` belongs to User

2. **`app/Models/IncomingMailRead.php`**
   - Properties: id (uuid), incoming_mail_id, user_id, read_at (datetime)
   - Relationships: `mail()`, `user()`

### C. Helpers (NEW)
1. **`app/Helpers/IncomingMailHelper.php`** - Static helper class dengan methods:
   - `markAsRead(incomingMailId, userId): bool` - Catat pembacaan surat
   - `allWadirRead(incomingMailId): bool` - Check apakah semua wakil direksi sudah baca
   - `hasUserRead(incomingMailId, userId): bool` - Check apakah user spesifik sudah baca
   - `hasDirutRead(incomingMailId): bool` - Check apakah dirut sudah baca
   - `getUnreadWadir(incomingMailId): array` - Get list user ID wakil direksi yang belum baca

### D. Repository (UPDATED)
**`app/Repositories/IncomingMail/IncomingMailRepository.php`**

**Updated methods:**
- `list_incoming_mails($filter = null)` - Support filter 'read', 'unread', tambah field is_read, dirut_read, all_wadir_read

**New methods:**
- `markAsRead($id)` - Mark surat sebagai sudah dibaca user
- `setReadyForDirut($id)` - Set status READY_DIRUT dengan validasi semua wadir sudah baca
- `getUnreadWadir($id)` - Ambil list wadir yang belum baca surat

### E. Controller (UPDATED)
**`app/Http/Controllers/IncomingMailController.php`**

**Updated methods:**
- `list_incoming_mails(Request $request)` - Support filter parameter

**New methods:**
- `markAsRead($id)` - POST endpoint untuk mark read
- `setReadyForDirut($id)` - PATCH endpoint untuk set READY_DIRUT
- `getUnreadWadir($id)` - GET endpoint untuk fetch unread wadir list

### F. Routes (UPDATED)
**`routes/incoming_mail.php`**

Added routes:
```php
Route::post('/{id}/read', [IncomingMailController::class, 'markAsRead'])->name('incoming.read');
Route::get('/{id}/unread-wadir', [IncomingMailController::class, 'getUnreadWadir'])->name('incoming.unread_wadir');
Route::patch('/{id}/ready-dirut', [IncomingMailController::class, 'setReadyForDirut'])->name('incoming.ready_dirut');
```

### G. Frontend (UPDATED)

1. **`resources/js/Pages/IncomingMail/Index.jsx`** - List page
   - Added filter select: "Sudah Dibaca" / "Belum Dibaca"
   - Added Status column dengan badges:
     - "Sudah Dibaca" (green) / "Belum Dibaca" (orange)
     - "✓ Dirut Sudah Baca" (blue) jika dirut read
     - "✓ Semua Wakil Direksi Baca" (cyan) jika all wadir read
   - Filter state management dengan refetch saat filter berubah

2. **`resources/js/Pages/IncomingMail/View.jsx`** - Detail page
   - Auto-mark as read: useEffect hook memanggil `POST /incoming-mails/{id}/read` saat component mount
   - New state: `unreadWadir`, `loadingWadir`, `readyForDirutLoading`
   - New section "Wakil Direksi yang Belum Membaca" dengan:
     - Fetch list dari `GET /incoming-mails/{id}/unread-wadir`
     - Display nama & email wakil direksi yang belum baca
     - Show "✓ Semua wakil direksi sudah membaca" jika kosong
   - New button "Siapkan untuk Dirut":
     - Disabled jika ada wakil direksi yang belum baca
     - Call `PATCH /incoming-mails/{id}/ready-dirut` saat diklik
     - Enabled hanya untuk superadmin
     - Show warning "Tunggu hingga semua wakil direksi membaca..."

---

## 🔄 Data Flow

### 1. Mark as Read Flow
```
View.jsx mount
    ↓
useEffect hook trigger
    ↓
axios.post(route('incoming.read', {id}))
    ↓
IncomingMailController->markAsRead($id)
    ↓
IncomingMailRepository->markAsRead($id)
    ↓
IncomingMailHelper::markAsRead($id, userId)
    ↓
Check IncomingMailRead (incoming_mail_id, user_id)
    ↓
Jika belum ada → Insert new record
    ↓
Success (no redirect/page refresh needed)
```

### 2. Filter List Flow
```
Index.jsx
    ↓
Select filter change → handleFilterChange()
    ↓
Update state: setFilterRead(value)
    ↓
User click Refresh button → fetchList()
    ↓
axios.get(route('incoming.list_incoming_mails'), { params: { filter } })
    ↓
IncomingMailController->list_incoming_mails($filter)
    ↓
IncomingMailRepository->list_incoming_mails($filter)
    ↓
Eloquent query dengan filter:
   - filter='read': whereHas('reads', user_id = current_user)
   - filter='unread': whereDoesntHave('reads', user_id = current_user)
    ↓
Map results + tambah field: is_read, dirut_read, all_wadir_read
    ↓
Return JSON dengan status badges
    ↓
Display dengan colored tags
```

### 3. Ready for Dirut Flow
```
View.jsx - Unread Wadir Section
    ↓
useEffect hook trigger
    ↓
axios.get(route('incoming.unread_wadir', {id}))
    ↓
IncomingMailController->getUnreadWadir($id)
    ↓
IncomingMailRepository->getUnreadWadir($id)
    ↓
IncomingMailHelper::getUnreadWadir($id)
    ↓
Get semua wakil_direksis user_id
    ↓
Check incoming_mail_reads untuk setiap
    ↓
Return array dengan unread user_id
    ↓
Fetch User records (id, name, email)
    ↓
Display di UI dengan disable button jika ada unread
    ↓
User click "Siapkan untuk Dirut"
    ↓
axios.patch(route('incoming.ready_dirut', {id}))
    ↓
IncomingMailController->setReadyForDirut($id)
    ↓
IncomingMailRepository->setReadyForDirut($id)
    ↓
Check IncomingMailHelper::allWadirRead($id)
    ↓
Jika false → return error 422
Jika true → Update status_code = 'READY_DIRUT'
    ↓
Success message + refetch detail
```

---

## 🔐 Security & Validation

1. **Authentication:** Semua route memerlukan middleware `auth`
2. **Authorization:** 
   - List/View: superadmin only (via CheckRole middleware)
   - Mark as read: otomatis untuk user login yang membuka detail
   - Set Ready for Dirut: superadmin only
   - Disposisi: dirut, wakil direksi per policy

3. **Validation:**
   - Unique constraint (incoming_mail_id, user_id) di database
   - Filter parameter validation di controller
   - Status code existence check untuk status update

4. **Data Integrity:**
   - Cascade delete: IncomingMailRead saat IncomingMail dihapus
   - Cascade delete: WakilDireksi saat User dihapus
   - Transaction safety: tidak digunakan untuk read marking (atomic operation)

---

## 📊 Database Queries

### Checking all Wakil Direksi read:
```sql
-- Get all wakil direksi user ids
SELECT user_id FROM wakil_direksis;

-- Check how many have read a specific mail
SELECT COUNT(DISTINCT user_id) 
FROM incoming_mail_reads 
WHERE incoming_mail_id = '{mail_id}' 
  AND user_id IN (SELECT user_id FROM wakil_direksis);

-- If count == total wadir count → all read
```

### Getting unread Wakil Direksi:
```sql
SELECT DISTINCT wd.user_id
FROM wakil_direksis wd
LEFT JOIN incoming_mail_reads imr 
  ON wd.user_id = imr.user_id 
  AND imr.incoming_mail_id = '{mail_id}'
WHERE imr.id IS NULL;
```

### Filtering read/unread mails:
```sql
-- Unread by user
SELECT * FROM incoming_mails im
WHERE NOT EXISTS (
  SELECT 1 FROM incoming_mail_reads imr
  WHERE imr.incoming_mail_id = im.id
    AND imr.user_id = {current_user_id}
);

-- Read by user
SELECT * FROM incoming_mails im
WHERE EXISTS (
  SELECT 1 FROM incoming_mail_reads imr
  WHERE imr.incoming_mail_id = im.id
    AND imr.user_id = {current_user_id}
);
```

---

## 🚀 Migration & Setup

Untuk run migrations:
```bash
php artisan migrate
```

Untuk seeding wakil direksi (manual insert ke `wakil_direksis` table):
```php
// Di seeder atau UI admin
WakilDireksi::create([
    'id' => Str::uuid(),
    'user_id' => $userId,
]);
```

---

## ✅ Testing Checklist

- [ ] Migrations run successfully
- [ ] WakilDireksi & IncomingMailRead models work
- [ ] Admin can upload surat
- [ ] Wakil direksi view surat → auto-marked as read
- [ ] Dirut view surat → auto-marked as read separately
- [ ] List filter "Belum Dibaca" shows unread mails
- [ ] List filter "Sudah Dibaca" shows read mails
- [ ] Badge "Dirut Sudah Baca" appears correctly
- [ ] Badge "Semua Wakil Direksi Baca" appears correctly
- [ ] Detail page shows unread wadir list
- [ ] "Siapkan untuk Dirut" disabled when wadir belum baca
- [ ] "Siapkan untuk Dirut" updates status to READY_DIRUT
- [ ] Dirut can see READY_DIRUT mails in their list
- [ ] Dispositions work for READY_DIRUT mails

---

## 📝 Notes

1. **Helper Location:** Sudah ditambahkan ke autoload files di composer.json jika perlu
2. **Status Codes:** Harus ada 'READY_DIRUT' di MailStatus table sebelum workflow ini bisa berjalan
3. **Frontend Dependencies:** Menggunakan Ant Design (Tag, Badge, Select, Space components)
4. **Performance:** Untuk load Wakil Direksi besar, bisa optimize dengan pagination/caching

---

## 📚 Related Documentation

Lihat [APP_OVERVIEW.md](APP_OVERVIEW.md) untuk dokumentasi lengkap tentang:
- Semua models
- Database schema
- API endpoints
- Frontend components

---

**End of Implementation Guide**
