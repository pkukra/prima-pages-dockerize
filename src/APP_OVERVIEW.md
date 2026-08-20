# Dokumentasi Aplikasi Prima Pages

## 📋 Ringkasan Aplikasi

**Nama:** Prima Pages  
**Framework:** Laravel 11 + React/Inertia.js  
**Database:** MySQL  
**Bahasa:** PHP (Backend) + JavaScript/JSX (Frontend)  
**Tanggal Update:** Februari 2026

---

## 🏗️ Arsitektur Aplikasi

```
prima-pages/
├── app/                  # Logika aplikasi
│   ├── Http/            # Controllers, Requests, Middleware
│   ├── Models/          # Eloquent Models
│   ├── Repositories/    # Data access layer
│   ├── Services/        # Business logic
│   ├── Helpers/         # Helper functions
│   ├── Exports/         # Excel export classes
│   └── Policies/        # Authorization policies
├── routes/              # Route definitions
├── resources/           # Frontend assets
│   ├── js/             # React/JSX components
│   ├── css/            # Styling
│   └── views/          # Blade templates
├── database/           # Migrations, seeders, factories
├── config/             # Configuration files
└── storage/            # File uploads, cache, logs
```

---

## 🔐 Stack Teknologi

### Backend
- **Framework:** Laravel 11.31
- **ORM:** Eloquent
- **Authentication:** Sanctum
- **Authorization:** Custom middleware (CheckRole)
- **Excel Export:** Maatwebsite/Excel 3.1
- **File Upload:** Local storage

### Frontend
- **Library:** React + Inertia.js 2.0
- **UI Components:** Ant Design
- **HTTP Client:** Axios
- **Date Handling:** Day.js
- **Build Tool:** Vite
- **Styling:** Tailwind CSS

### Database
- **Type:** MySQL
- **Migrations:** Laravel migrations
- **Relationships:** Foreign keys dengan UUID/BigInt

---

## 👥 Sistem User & Role

### Models
- **User** - Model user dengan relasi role, Tilaka profile, dan authenticatable
- **Role** - Model role (superadmin, admin, dokter, perawat, etc.)

### Middleware
- **CheckRole** - Validasi user berdasarkan role
  - Jika superadmin → akses semua route
  - Jika role lain → cek whitelist roles
  - Jika tidak ada role → abort 403

---

## 📚 SEMUA MODELS & MIGRATIONS

### User Model & Migration
**File:** `app/Models/User.php` & `database/migrations/0001_01_01_000000_create_users_table.php`

```php
// Fillable fields:
- name (string)
- email (string, unique, 100 chars max)
- password (string, hashed)
- nik (string, 16 chars max, nullable) - added by migration 2025_02_18
- eklaim_key (string, nullable, unique) - added by migration 2025_02_18
- role_id (unsignedBigInteger, FK to roles, nullable) - added by migration 2025_04_14

// Relationships:
- role() → belongs to Role
- tilakaProfile() → has one TilakaProfile
```

**Database Table:**
```sql
id              bigint primary key
name            varchar(255)
email           varchar(100) unique
email_verified_at timestamp nullable
password        varchar(255)
remember_token  varchar(100) nullable
nik             varchar(16) nullable
eklaim_key      varchar(255) nullable unique
role_id         bigint FK to roles
created_at      timestamp
updated_at      timestamp
```

---

### Role Model & Migration
**File:** `app/Models/Role.php` & `database/migrations/2025_04_14_082515_create_roles_table.php`

```php
// Model properties:
- name (string, unique) - contoh: superadmin, admin, dokter, perawat

// Relationships:
- users() → has many User
```

**Database Table:**
```sql
id              bigint primary key
name            varchar(255) unique
created_at      timestamp
updated_at      timestamp
```

---

### Student Model & Migration
**File:** `app/Models/Student.php` & `database/migrations/2025_01_16_072932_create_students_table.php`

```php
// Model properties:
- $table = 'students'
- $primaryKey = 'student_id'
- $fillable = ['first_name', 'last_name', 'department', 'email']
```

**Database Table:**
```sql
student_id      increments primary key
first_name      varchar(255)
last_name       varchar(255)
department      varchar(255)
email           varchar(255) unique
created_at      timestamp
updated_at      timestamp
```

---

### Unit Model & Migration
**File:** `app/Models/Unit.php` & `database/migrations/2026_01_05_120000_create_units_table.php`

```php
// Fillable fields:
- code (string, unique)
- name (string)
- description (text, nullable)
- created_by (string, default 'system')
- updated_by (string, nullable)
```

**Database Table:**
```sql
id              bigint primary key
code            varchar(255) unique
name            varchar(255)
description     longtext nullable
created_by      varchar(255) default 'system'
updated_by      varchar(255) nullable
created_at      timestamp
updated_at      timestamp
```

---

### Disposition Model & Migration
**File:** `app/Models/Disposition.php` & `database/migrations/2026_01_05_130000_create_dispositions_table.php`

```php
// Model properties:
- $incrementing = false
- $keyType = 'string' (UUID)

// Fillable fields:
- id (UUID)
- incoming_mail_id (UUID, FK to incoming_mails)
- from_user_id (bigint FK to users, nullable)
- to_user_id (bigint FK to users, nullable)
- to_unit_id (bigint FK to units, nullable)
- instruction (text, nullable)
- due_date (date, nullable)
- status (string, default 'open')
- created_by (string)
- updated_by (string, nullable)

// Casts:
- due_date → date

// Relationships:
- mail() → belongs to IncomingMail
- fromUser() → belongs to User (from_user_id)
- toUser() → belongs to User (to_user_id)
- unit() → belongs to Unit (to_unit_id)
```

**Database Table:**
```sql
id              uuid primary key
incoming_mail_id uuid FK to incoming_mails (cascade delete)
from_user_id    bigint FK to users (nullable)
to_user_id      bigint FK to users (nullable)
to_unit_id      bigint FK to units (set null)
instruction     longtext nullable
due_date        date nullable
status          varchar(30) default 'open'
created_by      varchar(255)
updated_by      varchar(255) nullable
created_at      timestamp
updated_at      timestamp
```

---

### Document Models & Migrations

#### DocumentOwner Model
**File:** `app/Models/DocumentOwner.php` & `database/migrations/2025_10_02_091745_create_document_owners_table.php`

```php
// Basic model (minimal properties)
```

**Database Table:**
```sql
id              bigint primary key
name            varchar(255)
description     longtext nullable
created_by      varchar(255)
updated_by      varchar(255) nullable
created_at      timestamp
updated_at      timestamp
deleted_at      timestamp nullable (soft delete)
```

#### DocumentType Model
**File:** `app/Models/DocumentType.php` & `database/migrations/2025_10_02_091746_create_document_types_table.php`

```php
// Basic model (minimal properties)
```

**Database Table:**
```sql
id              bigint primary key
name            varchar(255) unique
description     longtext nullable
created_by      varchar(255)
updated_by      varchar(255) nullable
created_at      timestamp
updated_at      timestamp
deleted_at      timestamp nullable (soft delete)
```

#### DocumentCategory Model
**File:** `app/Models/DocumentCategory.php` & `database/migrations/2025_10_02_092810_create_document_categories_table.php`

```php
// Basic model (minimal properties)
```

**Database Table:**
```sql
id              bigint primary key
name            varchar(255) unique
created_by      varchar(255)
updated_by      varchar(255) nullable
created_at      timestamp
updated_at      timestamp
deleted_at      timestamp nullable (soft delete)
```

#### Document Model
**File:** `app/Models/Document.php` & `database/migrations/2025_10_02_091747_create_documents_table.php`

```php
// Model properties:
- use HasFactory, SoftDeletes

// Fillable fields:
- name (string, unique)
- description (text, nullable)
- file_path (string)
- owner_id (bigint FK to document_owners)
- type_id (bigint FK to document_types)
- created_by (string)
- updated_by (string, nullable)

// Relationships:
- owner() → belongs to DocumentOwner
- type() → belongs to DocumentType
```

**Database Table:**
```sql
id              bigint primary key
name            varchar(255) unique
description     longtext nullable
file_path       varchar(255)
owner_id        bigint FK to document_owners
type_id         bigint FK to document_types
created_by      varchar(255)
updated_by      varchar(255) nullable
created_at      timestamp
updated_at      timestamp
deleted_at      timestamp nullable (soft delete)
```

---

### Tilaka Models & Migrations

#### TilakaProfile Model
**File:** `app/Models/TilakaProfile.php` & `database/migrations/2026_01_19_000000_create_tilaka_profiles_table.php`

```php
// Model properties:
- $incrementing = false
- $keyType = 'string' (UUID)

// Fillable fields:
- id (UUID)
- user_id (bigint FK to users, unique)
- tilaka_uuid (UUID, nullable, unique) - from Tilaka service
- nik (string, 16 chars)
- full_name (string)
- email (string)
- phone (string, nullable)
- photo_ktp_path (string, nullable)
- selfie_path (string, nullable)
- verification_status (enum: draft, submitted, approved, rejected)
- rejection_reason (text, nullable)
- created_by (string)
- updated_by (string, nullable)

// Casts:
- created_at → datetime
- updated_at → datetime

// Relationships:
- user() → belongs to User

// Methods:
- canEdit() → bool (true jika status draft atau rejected)
- isSubmitted() → bool (assumed method)
```

**Database Table:**
```sql
id              uuid primary key
user_id         bigint FK to users unique (cascade delete)
tilaka_uuid     uuid nullable unique
nik             varchar(16)
full_name       varchar(255)
email           varchar(255)
phone           varchar(255) nullable
photo_ktp_path  varchar(255) nullable
selfie_path     varchar(255) nullable
verification_status enum('draft','submitted','approved','rejected') default 'draft'
rejection_reason longtext nullable
created_by      varchar(255)
updated_by      varchar(255) nullable
created_at      timestamp
updated_at      timestamp

Indexes:
- user_id
- verification_status
```

#### TilakaToken Model
**File:** `app/Models/TilakaToken.php` & `database/migrations/2026_01_19_100112_create_tilaka_tokens_table.php`

```php
// Fillable fields:
- access_token (text)
- refresh_token (text, nullable)
- expires_at (timestamp, nullable)
- token_type (string, default 'Bearer')

// Casts:
- expires_at → datetime

// Methods:
- isExpired() → bool (check if token expired, with 60-second buffer)
```

**Database Table:**
```sql
id              bigint primary key
access_token    longtext
refresh_token   longtext nullable
expires_at      timestamp nullable
token_type      varchar(255) default 'Bearer'
created_at      timestamp
updated_at      timestamp
```

---

### External Data Models
**Note:** Koneksi ke external database (SQL Server)

#### CasemixRanap Model
**File:** `app/Models/CasemixRanap.php`

```php
// Model properties:
- $connection = 'sqlsrv'
- $table = 'CASEMIX_RANAP'
```

#### PasienRujukan Model
**File:** `app/Models/PasienRujukan.php`

```php
// Model properties:
- $connection = 'sqlsrv'
- $table = 'PASIEN_RUJUKAN'
```

---

### MailStatus Model & Migration
**File:** `app/Models/MailStatus.php` & `database/migrations/2026_01_02_111512_create_mail_statuses_table.php`

```php
// Model properties:
- $incrementing = false
- $keyType = 'string'

// Fillable fields:
- id (UUID)
- code (string, unique, 30 chars max) - e.g., 'new', 'registered', 'processed'
- name (string) - e.g., 'Baru', 'Dicatat', 'Diproses'
- type (string, default 'incoming') - incoming, outgoing, internal
- created_by (string)
- updated_by (string, nullable)
```

**Database Table:**
```sql
id              uuid primary key
code            varchar(30) unique
name            varchar(255)
type            varchar(20) default 'incoming'
created_by      varchar(255)
updated_by      varchar(255) nullable
created_at      timestamp
updated_at      timestamp
```

---

### IncomingMail Model & Migration
**File:** `app/Models/IncomingMail.php` & `database/migrations/2026_01_02_111906_create_incoming_mails_table.php`

Already documented in "FEATURE: INCOMING MAIL" section above.

---

## 📧 FEATURE: INCOMING MAIL (Surat Masuk)

### 🎯 Overview
Fitur untuk mengelola surat masuk ke organisasi dengan kemampuan:
- Tracking status baca oleh wakil direksi dan dirut
- Workflow approval menuju daftar Dirut
- Disposisi (penugasan) surat ke unit atau wakil direksi
- Manajemen dokumen surat

### 📊 Database Schema

#### Tabel: `incoming_mails`
```sql
id              UUID (primary key)
created_at      timestamp
updated_at      timestamp
created_by      string          (email user)
updated_by      string          (email user)
mail_number     string (unique) (nomor surat)
sender          string          (pengirim)
subject         string          (perihal/judul)
mail_date       date            (tanggal surat terbit)
received_date   date            (tanggal diterima)
summary         text            (ringkasan opsional)
file_path       string          (path ke file dokumen)
status_code     string (FK)     (status surat: NEW, READY_DIRUT, PROCESSED, ARCHIVED)
recipient_id    bigint (FK)     (user penerima)
```

#### Tabel: `mail_statuses`
```sql
id              uuid (primary key)
code            string (unique) (kode status)
name            string          (nama status)
type            string          (tipe: 'incoming')
created_by      string
updated_by      string nullable
created_at      timestamp
updated_at      timestamp
```

#### Tabel: `incoming_mail_reads` (NEW)
```sql
id              uuid (primary key)
incoming_mail_id uuid (FK)      (relasi ke incoming_mails, cascade delete)
user_id         bigint (FK)     (relasi ke users, cascade delete)
read_at         timestamp       (waktu user membaca)
created_at      timestamp
updated_at      timestamp

unique(incoming_mail_id, user_id)
```

#### Tabel: `wakil_direksis` (NEW)
```sql
id              uuid (primary key)
user_id         bigint (FK, unique) (relasi ke users, cascade delete)
created_at      timestamp
updated_at      timestamp
```

### 🔗 Relasi
```
IncomingMail
├─ status_code → MailStatus.code
├─ recipient_id → User.id
└─ reads ↔ IncomingMailRead (hasMany) [incoming_mail_id]

IncomingMailRead
├─ mail → IncomingMail (belongsTo)
└─ user → User (belongsTo)

WakilDireksi
└─ user → User (belongsTo)
```

---

## 🛣️ Routes Configuration

### Route File: `routes/incoming_mail.php`

**Prefix:** `/incoming-mails`  
**Middleware:** `['auth', CheckRole::class . ':superadmin']`  
**Akses:** Hanya untuk superadmin

#### Endpoints

| Method | Path | Controller Method | Name | Deskripsi |
|--------|------|------------------|------|-----------|
| GET | `/` | `index()` | `incoming.index` | Render halaman list (Inertia) |
| GET | `/list` | `list_incoming_mails()` | `incoming.list_incoming_mails` | API: Daftar surat (with filter) |
| GET | `/statuses` | `list_statuses()` | `incoming.statuses` | API: Daftar status |
| GET | `/add` | `add()` | `incoming.add` | Render form tambah (Inertia) |
| POST | `/store` | `store()` | `incoming.store` | Simpan surat baru |
| GET | `/view/{id}` | `viewPage()` | `incoming.viewPage` | Render halaman detail (Inertia) |
| GET | `/show/{id}` | `show()` | `incoming.show` | API: Detail surat (JSON) |
| PATCH | `/update/{id}` | `update()` | `incoming.update` | Update info surat |
| POST | `/replace/{id}` | `replace_document()` | `incoming.replace` | Ganti file dokumen |
| PATCH | `/edit-document/{id}` | `edit_document()` | `incoming.edit_document` | Update summary dokumen |
| GET | `/preview/{id}` | `preview()` | `incoming.preview` | Download/preview file |
| POST | `/{id}/read` | `markAsRead()` | `incoming.read` | **NEW** Mark user as read surat |
| GET | `/{id}/unread-wadir` | `getUnreadWadir()` | `incoming.unread_wadir` | **NEW** Get unread wakil direksi |
| PATCH | `/{id}/ready-dirut` | `setReadyForDirut()` | `incoming.ready_dirut` | **NEW** Set status to READY_DIRUT |
| GET | `/{id}/dispositions` | `DispositionController@index()` | `incoming.dispositions.index` | Daftar disposisi |
| POST | `/{id}/dispositions` | `DispositionController@store()` | `incoming.dispositions.store` | Buat disposisi baru |
| PATCH | `/{id}/dispositions/{disposition_id}` | `DispositionController@update()` | `incoming.dispositions.update` | Update disposisi |
| DELETE | `/{id}/dispositions/{disposition_id}` | `DispositionController@destroy()` | `incoming.dispositions.destroy` | Hapus disposisi |

---

## 🎮 Controller: IncomingMailController

**Location:** `app/Http/Controllers/IncomingMailController.php`  
**Dependencies:** `IncomingMailRepository`, `IncomingMailHelper`

### Methods

#### `index()`
- **HTTP Method:** GET
- **Returns:** Inertia render untuk halaman list
- **Props:** Kosong
- **Render:** `IncomingMail/Index`

#### `list_incoming_mails(Request $request)` - **UPDATED**
- **HTTP Method:** GET
- **Query Parameters:**
  ```
  filter: 'read' | 'unread' | null (default)
  ```
- **Logic:** Repository → `list_incoming_mails($filter)`
- **Returns:** JSON API Response dengan field tambahan:
  ```
  is_read: boolean (apakah user ini sudah baca)
  dirut_read: boolean (apakah dirut sudah baca)
  all_wadir_read: boolean (apakah semua wakil direksi sudah baca)
  ```

#### `list_statuses()`
- **HTTP Method:** GET
- **Logic:** Repository → `statuses()`
- **Returns:** JSON API Response
- **Data:** MailStatus objects dimana type = 'incoming'

#### `viewPage($id)`
- **HTTP Method:** GET
- **Returns:** Inertia render untuk halaman detail
- **Props Sent:**
  ```javascript
  {
    id: string (UUID),
    mail: IncomingMail object,
    statuses: array of MailStatus
  }
  ```
- **Error:** Abort 404 jika mail tidak ditemukan

#### `show($id)`
- **HTTP Method:** GET
- **Returns:** JSON API Response
- **Data:** Single IncomingMail object

#### `update(Request $request, $id)`
- **HTTP Method:** PATCH
- **Validation:**
  ```
  mail_number: required, unique (ignore current id)
  sender: required, string
  subject: required, string
  mail_date: required, date
  received_date: required, date
  status_code: required, exists in mail_statuses
  ```
- **Logic:** Repository → `update()`
- **Returns:** Updated IncomingMail object
- **Updates:** `updated_by` ke email user

#### `replace_document(Request $request, $id)`
- **HTTP Method:** POST
- **Validation:**
  ```
  file: required, file, max:10240 (10MB)
  ```
- **Logic:** Repository → `replace_document()`
- **Process:**
  1. Upload file ke storage `incoming_mails/` folder
  2. Hapus file lama jika ada
  3. Update `file_path` di database
- **Returns:** Updated IncomingMail object
- **Storage:** `storage/app/incoming_mails/`

#### `edit_document(Request $request, $id)`
- **HTTP Method:** PATCH
- **Validation:**
  ```
  summary: nullable, string
  ```
- **Logic:** Repository → `edit_document()`
- **Updates:** Hanya field `summary` dan `updated_by`

#### `preview($id)`
- **HTTP Method:** GET
- **Returns:** File download/stream
- **Process:** Get file dari storage dan return dengan response()->file()

#### `add()`
- **HTTP Method:** GET
- **Returns:** Inertia render untuk form tambah
- **Render:** `IncomingMail/Add`

#### `store(Request $request)`
- **HTTP Method:** POST
- **Validation:**
  ```
  mail_number: required, unique
  sender: required, string
  subject: required, string
  mail_date: required, date
  received_date: required, date
  file: nullable, file, max:10240
  status_code: required, exists in mail_statuses
  ```
- **Logic:** Repository → `store()`
- **Returns:** Created IncomingMail object + 201 status
- **Generates:** UUID untuk id, set created_by dari Auth::user()

#### `markAsRead($id)` - **NEW**
- **HTTP Method:** POST
- **Route:** `POST /incoming-mails/{id}/read`
- **Logic:** IncomingMailHelper::markAsRead($id, userId)
- **Process:**
  1. Check IncomingMailRead table untuk kombinasi (incoming_mail_id, user_id)
  2. Jika belum ada, buat record baru dengan read_at = now()
- **Returns:** Success message
- **Notes:** Otomatis dipanggil di frontend View component saat user membuka detail

#### `setReadyForDirut($id)` - **NEW**
- **HTTP Method:** PATCH
- **Route:** `PATCH /incoming-mails/{id}/ready-dirut`
- **Access:** Admin/superadmin only
- **Validation:**
  ```
  Pastikan semua wakil direksi sudah baca (via IncomingMailHelper::allWadirRead)
  ```
- **Logic:** Repository → `setReadyForDirut($id)`
- **Process:**
  1. Check IncomingMailHelper::allWadirRead($id)
  2. Jika tidak semua baca → return error 422
  3. Update status_code = 'READY_DIRUT'
- **Returns:** Updated IncomingMail object atau error message
- **Status Code:**
  - 200: Sukses
  - 422: Validasi gagal (belum semua wakil direksi baca)
  - 500: Server error

#### `getUnreadWadir($id)` - **NEW**
- **HTTP Method:** GET
- **Route:** `GET /incoming-mails/{id}/unread-wadir`
- **Logic:** Repository → `getUnreadWadir($id)`
- **Returns:** Array of unread wakil direksi users
  ```json
  {
    "status": true,
    "data": [
      { "id": 1, "name": "Wakil Direksi 1", "email": "wadir1@example.com" },
      { "id": 2, "name": "Wakil Direksi 2", "email": "wadir2@example.com" }
    ]
  }
  ```

---

## 📚 Repository: IncomingMailRepository

**Location:** `app/Repositories/IncomingMail/IncomingMailRepository.php`

### Methods Detail

#### `list_incoming_mails($filter = null)` - **UPDATED**
```php
Eloquent: IncomingMail::orderBy('created_at', 'desc')
          dengan filter:
          - filter='read': whereHas('reads', user_id = current_user)
          - filter='unread': whereDoesntHave('reads', user_id = current_user)
          
Returns: Mails dengan field tambahan:
         - is_read: boolean
         - dirut_read: boolean  
         - all_wadir_read: boolean
```

#### `statuses()`
```php
Eloquent: MailStatus::select('code', 'name')
                    ->where('type', 'incoming')
                    ->get()
Returns: RepoResponse::success($data)
```

#### `show($id)`
```php
Logic:
  1. Cari IncomingMail by id
  2. Jika tidak ada → RepoResponse::error('Surat tidak ditemukan')
  3. Return RepoResponse::success($mail)
```

#### `update($id, $request)`
```php
Logic:
  1. Cari IncomingMail by id
  2. Update field (gunakan ?? untuk handle null)
  3. Set updated_by ke Auth::user()->email
  4. Save dan return updated object
Error Handling: Try-catch, return error message
```

**Fields yang bisa di-update:**
- mail_number
- sender
- subject
- mail_date
- received_date
- summary
- status_code
- recipient_id
- updated_by

#### `replace_document($id, $request)`
```php
Logic (dengan DB Transaction):
  1. Cari IncomingMail by id
  2. Validasi file ada di request
  3. Generate nama file: Str::random(8) . '_' . original_name
  4. Store ke 'incoming_mails/' folder
  5. Hapus file lama jika ada
  6. Update file_path di DB
  7. Set updated_by
  8. Commit transaction
Error Handling: Rollback, delete file jika error
```

#### `edit_document($id, $request)`
```php
Logic:
  1. Cari IncomingMail by id
  2. Update field summary (nullable)
  3. Set updated_by
  4. Save dan return
Error Handling: Try-catch, return error message
```

#### `preview($id)`
```php
Logic:
  1. Cari IncomingMail by id
  2. Cek file_path ada
  3. Cek file exist di storage
  4. Return response()->file($fullPath)
Error Handling: Abort 404 jika file tidak ditemukan
```

#### `store($request)`
```php
Logic (dengan DB Transaction):
  1. Generate UUID untuk id: Str::uuid()->toString()
  2. Jika ada file: upload ke 'incoming_mails/'
  3. Create IncomingMail dengan field:
     - id (generated UUID)
     - created_by (from Auth)
     - updated_by (from Auth)
     - mail_number
     - sender
     - subject
     - mail_date
     - received_date
     - summary (nullable)
     - file_path (nullable)
     - status_code (default 'NEW')
     - recipient_id (nullable)
  4. Commit transaction
  5. Return success response
Error Handling: Rollback, delete file, return error
```

#### `markAsRead($id)` - **NEW**
```php
Logic:
  1. Cari IncomingMail by id
  2. Call IncomingMailHelper::markAsRead($id, Auth::user()->id)
  3. Return success response
Error Handling: Try-catch, return error message
```

#### `setReadyForDirut($id)` - **NEW**
```php
Logic:
  1. Cari IncomingMail by id
  2. Check IncomingMailHelper::allWadirRead($id)
  3. Jika false → return RepoResponse::error('Belum semua...', null, 422)
  4. Update status_code = 'READY_DIRUT'
  5. Set updated_by
  6. Save dan return updated object
Error Handling: Try-catch, return error message
```

#### `getUnreadWadir($id)` - **NEW**
```php
Logic:
  1. Cari IncomingMail by id
  2. Call IncomingMailHelper::getUnreadWadir($id)
  3. Fetch User records untuk unread user ids
  4. Return dengan field: id, name, email
Error Handling: Try-catch, return error message
```

---

## ⚙️ Models

### IncomingMail Model
```php
namespace: App\Models

Properties:
- $incrementing = false      (UUID, bukan auto increment)
- $keyType = 'string'        (UUID is string)

$fillable = [
    'id', 'created_by', 'updated_by', 'mail_number',
    'sender', 'subject', 'mail_date', 'received_date',
    'summary', 'file_path', 'status_code', 'recipient_id'
]

$casts = [
    'mail_date' => 'date',
    'received_date' => 'date'
]

Relationships: (Not defined in model, tapi ada di DB)
- status_code: foreign key to MailStatus.code
- recipient_id: foreign key to User.id
```

### MailStatus Model
```php
namespace: App\Models

Properties:
- $incrementing = false
- $keyType = 'string'

$fillable = [
    'id', 'created_by', 'updated_by', 'code',
    'name', 'type'
]
```

---

## 🎨 Frontend Components

### Pages

#### 1. **IncomingMail/Index.jsx** (List Page)
- **Route:** `/incoming-mails` (GET)
- **Accessed From:** Menu "Surat Masuk" → "List Surat"

**Features:**
- Tabel dengan columns: No Surat, Pengirim, Perihal, Tgl Surat, Tgl Diterima, Dibuat Oleh
- Pagination (8 items per page)
- Search fields (belum fully implemented): No Surat, Pengirim
- Button "Cari" dan "Tambah"
- Setiap row ada link "Detail" → `incoming.viewPage`

**State:**
```javascript
mails: array
loading: boolean
```

**API Calls:**
- `GET /incoming-mails/list` → fetch list_incoming_mails

#### 2. **IncomingMail/Add.jsx** (Form Tambah)
- **Route:** `/incoming-mails/add` (GET)
- **Access:** Menu "Surat Masuk" → "Tambah Surat"

**Form Fields:**
- mail_number (text) - required
- sender (text) - required
- subject (text) - required
- mail_date (date picker) - required
- received_date (date picker) - required
- status_code (select) - required
- summary (textarea) - optional
- file (upload) - optional

**Features:**
- Fetch status options dari API
- Form validation before submit
- Upload file dengan FormData
- Error handling dengan message modal

**API Calls:**
- `GET /incoming-mails/statuses` → fetch available statuses
- `POST /incoming-mails/store` → submit form dengan file (multipart)

#### 3. **IncomingMail/View.jsx** (Detail Page)
- **Route:** `/incoming-mails/view/{id}` (GET)
- **Accessed From:** Click "Detail" button di Index

**Sections:**

##### a) Mail Info Display
- Descriptions component showing:
  - Mail Number, Sender, Subject
  - Mail Date, Received Date
  - Summary, Created By, Updated By
  - Status
- Button: "Edit IncomingMail"

##### b) Edit Modal
- Modal form untuk edit mail info
- Fields: mail_number, sender, subject, mail_date, received_date, status_code, summary
- API: `PATCH /incoming-mails/update/{id}`

##### c) Document Section
- Display file preview di iframe
- Buttons:
  - "Edit Metadata" → docEditVisible modal
  - "Replace Document" → replaceVisible modal
  - "Download"

##### d) Document Edit Modal
- Edit summary field
- API: `PATCH /incoming-mails/edit-document/{id}`

##### e) Replace Document Modal
- Upload file baru
- API: `POST /incoming-mails/replace/{id}`

##### f) Dispositions Section (Penugasan)
- Table dengan columns: Target Unit, Assigned To, Status, Notes, Actions
- Buttons:
  - "Tambah Disposisi" → dispModalVisible modal
  - Edit/Delete untuk setiap row
- Nested route under incoming mail

**API Calls:**
- `GET /incoming-mails/view/{id}` → render page dengan mail + statuses props
- `GET /incoming-mails/show/{id}` → fetch updated mail data
- `PATCH /incoming-mails/update/{id}` → submit edit form
- `POST /incoming-mails/replace/{id}` → upload file baru
- `PATCH /incoming-mails/edit-document/{id}` → update summary
- `GET /incoming-mails/{id}/dispositions` → fetch dispositions list
- `POST /incoming-mails/{id}/dispositions` → create disposition
- `PATCH /incoming-mails/{id}/dispositions/{disposition_id}` → update disposition
- `DELETE /incoming-mails/{id}/dispositions/{disposition_id}` → delete disposition
- `GET /units/list` → fetch units untuk disposition target

---

## 🔄 Data Flow

### Create Incoming Mail Flow
```
Add.jsx Form
    ↓
handleSubmit() validation
    ↓
axios POST /incoming-mails/store
    ↓
IncomingMailController->store()
    ↓
Validation (422 if fail)
    ↓
IncomingMailRepository->store()
    ↓
[Upload File] → storage/app/incoming_mails/
[Generate UUID]
[Create Record] → DB
[Commit Transaction]
    ↓
Return 201 + IncomingMail object
    ↓
Show success message
    ↓
Clear form & redirect (optional)
```

### Update Mail Info Flow
```
View.jsx (Edit Button)
    ↓
Form modal popup
    ↓
submitEdit() → axios PATCH /incoming-mails/update/{id}
    ↓
IncomingMailController->update()
    ↓
Validation (422 if fail)
    ↓
IncomingMailRepository->update()
    ↓
[Fetch fresh data] → axios GET /incoming-mails/show/{id}
[Update state]
    ↓
Close modal
    ↓
Show success message
```

### Replace Document Flow
```
View.jsx (Replace Button)
    ↓
Modal dengan file upload
    ↓
submitReplace() → axios POST /incoming-mails/replace/{id}
    ↓
IncomingMailController->replace_document()
    ↓
IncomingMailRepository->replace_document()
    ↓
[File Upload]
[Delete Old File] if exists
[Update DB]
[Commit Transaction]
    ↓
Return 200 + updated object
    ↓
Close modal
    ↓
Show success message
```

---

## 🔐 Security Features

1. **Authentication:** Middleware `auth` - semua route incoming mail memerlukan login
2. **Authorization:** Middleware `CheckRole:superadmin` - hanya superadmin yang bisa akses
3. **Validation:** Input validation di controller & repository
4. **File Handling:** 
   - File size limit: 10MB
   - Random filename untuk prevent path traversal
   - Storage di folder `incoming_mails/` dengan ACL local
5. **Database Transactions:** Untuk operasi yang involve file upload
6. **CSRF Protection:** Laravel default (built-in)

---

## 📝 Validation Rules

### Store (POST /incoming-mails/store)
```
mail_number: required, unique('incoming_mails')
sender: required, string
subject: required, string
mail_date: required, date
received_date: required, date
file: nullable, file, max:10240
status_code: required, exists('mail_statuses','code')
```

### Update (PATCH /incoming-mails/update/{id})
```
mail_number: required, unique('incoming_mails', 'mail_number')->ignore($id)
sender: required, string
subject: required, string
mail_date: required, date
received_date: required, date
status_code: required, exists('mail_statuses','code')
```

### Replace Document (POST /incoming-mails/replace/{id})
```
file: required, file, max:10240
```

### Edit Document (PATCH /incoming-mails/edit-document/{id})
```
summary: nullable, string
```

---

## 🎯 Related Features

### 1. Dispositions (Penugasan Surat)
- Linked to IncomingMail via `incoming_mail_id` (assumed)
- Handles assignment of mails to units/departments
- Tracks: Target unit, assigned user, status, notes
- Nested routes under `/incoming-mails/{id}/dispositions`
- Controlled by `DispositionController`

### 2. Units Management
- Available at route `units.list`
- Used to populate unit selection in dispositions
- Managed by `UnitController`

---

## 📊 Response Format

### Success Response (ApiResponse::success)
```json
{
  "status": true,
  "message": "Success message",
  "data": { ... } | [ ... ]
}
```

### Error Response (ApiResponse::error)
```json
{
  "status": false,
  "message": "Error message",
  "errors": { ... } | null
}
```

### HTTP Status Codes
- 200 OK - Successful GET/PATCH
- 201 Created - Successful POST (store)
- 404 Not Found - Resource not found
- 422 Unprocessable Entity - Validation error
- 500 Internal Server Error - Server error
- 403 Forbidden - Authorization failed

---

## 🗂️ File Structure Summary

```
app/
├── Http/Controllers/
│   └── IncomingMailController.php
├── Models/
│   ├── IncomingMail.php
│   └── MailStatus.php
├── Repositories/IncomingMail/
│   └── IncomingMailRepository.php
└── Http/Middleware/
    └── CheckRole.php

routes/
└── incoming_mail.php

resources/js/Pages/IncomingMail/
├── Index.jsx
├── Add.jsx
└── View.jsx

database/migrations/
└── 2026_01_02_111906_create_incoming_mails_table.php

storage/app/
└── incoming_mails/        (uploaded files)
```

---

## 🚀 Key Technologies Used

| Component | Technology |
|-----------|-----------|
| Backend Framework | Laravel 11 |
| Frontend Library | React + Inertia.js |
| UI Components | Ant Design (antd) |
| HTTP Client | Axios |
| Date Library | Day.js |
| File Storage | Local filesystem |
| Database | MySQL |
| Build Tool | Vite |
| Styling | Tailwind CSS |
| Authentication | Laravel Sanctum |

---

## 💡 Important Notes

1. **UUID Usage:** IncomingMail menggunakan UUID sebagai primary key (bukan auto-increment)
2. **Status Codes:** Status surat disimpan sebagai string code (bukan ID), relationship via foreign key
3. **File Storage:** Files disimpan di `storage/app/incoming_mails/` dengan nama random
4. **Transaction Safety:** Store dan replace operations menggunakan DB transaction
5. **Date Formatting:** Frontend menggunakan Day.js, backend cast ke date
6. **Role-based Access:** Hanya superadmin yang bisa mengakses incoming mail routes
7. **Audit Trail:** Setiap record track `created_by` dan `updated_by` (email)

---

---

## 🔗 Related Routes (Other Features)

```
/docu.php          - Document management
/units.php         - Unit/Department management
/dispositions.php  - Disposition assignments
/tilaka.php        - Digital signature/verification
/auth.php          - Authentication routes
/rm.php            - Record management
```

---

## 🗺️ DATA MODEL RELATIONSHIPS DIAGRAM

```
┌─────────────────────────────────────────────────────────────────┐
│                      USER MANAGEMENT LAYER                       │
├─────────────────────────────────────────────────────────────────┤
│  User (id, email, name, password, nik, eklaim_key, role_id)     │
│    ├── belongs to Role                                           │
│    └── has one TilakaProfile (user_id)                          │
│                                                                   │
│  Role (id, name) [superadmin, admin, dokter, perawat, ...]     │
│    └── has many User                                             │
│                                                                   │
│  TilakaProfile (id, user_id, nik, full_name, email, phone)     │
│    ├── belongs to User                                           │
│    └── has many TilakaToken                                      │
│                                                                   │
│  TilakaToken (id, access_token, refresh_token, expires_at)     │
│    └── (Tilaka service authentication tokens)                    │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                    MAIL MANAGEMENT LAYER                         │
├─────────────────────────────────────────────────────────────────┤
│  IncomingMail (id, mail_number, sender, subject, ...)           │
│    ├── has many Disposition (incoming_mail_id) [CASCADE]        │
│    └── belongs to MailStatus (status_code)                      │
│                                                                   │
│  MailStatus (id, code, name, type)                              │
│    └── has many IncomingMail                                     │
│                                                                   │
│  Disposition (id, incoming_mail_id, from_user_id, to_user_id)  │
│    ├── belongs to IncomingMail                                   │
│    ├── belongs to User (from_user_id) - pengirim disposisi      │
│    ├── belongs to User (to_user_id) - penerima disposisi        │
│    ├── belongs to Unit (to_unit_id) - target unit/departemen    │
│    └── status: open, in_progress, closed                         │
│                                                                   │
│  Unit (id, code, name, description)                             │
│    └── has many Disposition                                      │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                   DOCUMENT MANAGEMENT LAYER                      │
├─────────────────────────────────────────────────────────────────┤
│  Document (id, name, file_path, owner_id, type_id) [SoftDelete] │
│    ├── belongs to DocumentOwner                                  │
│    └── belongs to DocumentType                                   │
│                                                                   │
│  DocumentOwner (id, name, description) [SoftDelete]             │
│    └── has many Document                                         │
│                                                                   │
│  DocumentType (id, name, description) [SoftDelete, Unique]      │
│    └── has many Document                                         │
│                                                                   │
│  DocumentCategory (id, name) [SoftDelete, Unique]               │
│    (Currently not linked to Document in current schema)          │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                    EDUCATIONAL LAYER                             │
├─────────────────────────────────────────────────────────────────┤
│  Student (student_id, first_name, last_name, department, email) │
│    └── Standalone (no relations)                                 │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│               EXTERNAL DATABASE CONNECTIONS (SQLSRV)            │
├─────────────────────────────────────────────────────────────────┤
│  CasemixRanap (table: CASEMIX_RANAP)                            │
│    └── Hospital case mix data (read-only reference)             │
│                                                                   │
│  PasienRujukan (table: PASIEN_RUJUKAN)                          │
│    └── Patient referral data (read-only reference)              │
└─────────────────────────────────────────────────────────────────┘
```

---

## 📊 Database Connection Types

| Model | Connection | Purpose |
|-------|-----------|---------|
| User, Role, Student, Unit, etc. | MySQL (default) | Main application data |
| Document, DocumentOwner, DocumentType | MySQL (default) | Document management |
| IncomingMail, MailStatus, Disposition | MySQL (default) | Mail management system |
| TilakaProfile, TilakaToken | MySQL (default) | Digital signature integration |
| CasemixRanap, PasienRujukan | SQL Server (sqlsrv) | External hospital system |

---

## 🔐 Audit Trail Fields

Semua models (kecuali yang minimal) memiliki audit trail:

```
created_by  varchar(255)  - Email/username yang membuat
updated_by  varchar(255)  - Email/username yang update terakhir (nullable)
created_at  timestamp     - Created timestamp
updated_at  timestamp     - Updated timestamp
deleted_at  timestamp     - Soft delete timestamp (untuk SoftDelete models)
```

**Models dengan SoftDelete:**
- Document
- DocumentOwner
- DocumentType
- DocumentCategory

---

## 💼 Field Type Conventions

| Type | Usage | Examples |
|------|-------|----------|
| UUID String | Primary keys yang perlu distributed | IncomingMail.id, Disposition.id, MailStatus.id |
| BigInt Auto | Primary keys standard | User.id, Unit.id, Document.id, Student.student_id |
| String(255) | Standard text fields | names, emails, codes |
| String(16) | NIK, fixed-length codes | User.nik, TilakaProfile.nik |
| String(30) | Status codes, type codes | MailStatus.code, Disposition.status |
| Text/LongText | Long content | description, instruction, rejection_reason |
| Date | Date only (no time) | IncomingMail.mail_date, Disposition.due_date |
| Timestamp | Date + time | created_at, updated_at, expires_at |
| Enum | Fixed options | TilakaProfile.verification_status, Disposition.status |

---

## 🔗 Related Routes (Other Features)

```
/docu.php          - Document management
/units.php         - Unit/Department management
/dispositions.php  - Disposition assignments
/tilaka.php        - Digital signature/verification
/auth.php          - Authentication routes
/rm.php            - Record management
```

---

**End of Documentation**  
Generated: February 4, 2026
