# InsureDesk (Laravel)

ระบบค้นหาและเปรียบเทียบประกันรถยนต์ภายในทีม สร้างด้วย Laravel 13, Livewire 4 และ MySQL
แปลงมาจากโปรเจ็ค Next.js เดิม (`insuredesk`) โดยกฎธุรกิจ ข้อความ และหน้าจอเหมือนเดิม สเปกอยู่ใน `docs/SystemDesign.md` ของโปรเจ็คเดิม

## เริ่มใช้งาน

ต้องมี PHP 8.3+ (พร้อม `intl` และ `pdo_mysql`), Composer, Node.js 22+ และ MySQL 8.0.16+

```bash
composer install
```

```bash
npm install
```

```bash
cp .env.example .env
```

```bash
php artisan key:generate
```

สร้างฐานข้อมูล `insuredesk_laravel` แล้วแก้ค่า `DB_*` ใน `.env` (MAMP ใช้ `DB_SOCKET=/Applications/MAMP/tmp/mysql/mysql.sock`) จากนั้น:

```bash
php artisan migrate --seed
```

```bash
npm run build
```

```bash
php artisan serve
```

เปิด <http://localhost:8000> แล้วเลือกบัญชีจำลองในหน้าเข้าสู่ระบบ (เมื่อตั้ง `ENABLE_DEMO_AUTH=true`)

ถ้า `npm run build` แจ้ง `Cannot find native binding` ให้ติดตั้ง binding ของเครื่องเอง เช่น `npm i --no-save @rolldown/binding-darwin-x64`

## คำสั่งที่ใช้บ่อย

| คำสั่ง | หน้าที่ |
|---|---|
| `php artisan migrate` | สร้างตาราง |
| `php artisan db:seed` | ใส่ประเภทความคุ้มครอง 16 หมวด และข้อมูลตัวอย่าง (ข้อมูลตัวอย่างไม่ลงใน production และไม่ทำงานถ้ามีแพ็กเกจอยู่แล้ว) |
| `php artisan migrate:fresh --seed` | ลบทุกตารางแล้วสร้างใหม่พร้อมข้อมูลตัวอย่าง |
| `php artisan insuredesk:create-user` | สร้างบัญชีผู้ใช้จริง |
| `php artisan test` | รัน test |

สร้างบัญชีผู้ดูแลระบบคนแรก (รหัสผ่านอย่างน้อย 12 ตัวอักษร):

```bash
INSUREDESK_USER_PASSWORD='change-me-please' php artisan insuredesk:create-user --email admin@example.com --name "ชื่อผู้ดูแล" --role admin
```

## การทดสอบ

Test รันกับ MySQL จริงในฐานข้อมูล `insuredesk_laravel_test` (ตั้งใน `phpunit.xml`) ซึ่งถูกล้างและสร้างใหม่ทุกครั้ง ต้องสร้างฐานข้อมูลนี้ก่อน

```bash
php artisan test
```

## โครงสร้างโค้ด

| ส่วน | ที่อยู่ |
|---|---|
| ตารางและข้อมูลตั้งต้น | `database/migrations`, `database/seeders` |
| สิทธิ์ของแต่ละบทบาท | `config/insuredesk.php` (กลายเป็น Gate เช่น `canReview` ใน `AppServiceProvider`) |
| การบันทึกทั้งหมด + audit log | `app/Services/InsuranceStore.php` |
| ค้นหาและเปรียบเทียบ | `app/Services/SearchService.php` |
| ตรวจข้อมูลฟอร์มแพ็กเกจฝั่ง server | `app/Actions/SavePlan.php` |
| หน้าจอ | `app/Livewire`, `resources/views/livewire` |
| เปิดไฟล์เอกสาร | `app/Http/Controllers/DocumentController.php` |

## Production

- ตั้ง `APP_ENV=production`, `APP_DEBUG=false` และห้ามตั้ง `ENABLE_DEMO_AUTH=true`
- รัน `php artisan migrate --force` และ `php artisan db:seed --force` (ลงเฉพาะประเภทความคุ้มครอง) ก่อนเริ่มแอป
- เอกสารที่อัปโหลดเก็บใน `storage/app/private/documents` ต้องใช้ดิสก์ถาวรและสำรองคู่กับฐานข้อมูล
- `Dockerfile` สร้าง image ด้วย FrankenPHP: `docker build -t insuredesk .` แล้ว `docker run -p 8080:80 --env-file .env -v insuredesk-storage:/app/storage insuredesk`

## ความต่างจากระบบเดิม

- รหัสผ่านใช้ bcrypt ของ Laravel แทน scrypt บัญชีจริงจากระบบเดิมต้องสร้างใหม่ด้วย `insuredesk:create-user`
- ไม่ได้ย้ายข้อมูลจากฐานข้อมูลเดิม (schema ใหม่ ตาราง `users` ใช้คอลัมน์ `name`/`password` แทน `display_name`/`password_hash`)
- ลิงก์ไฟล์เอกสารเป็น `/documents/<ไฟล์>` แทน `/api/documents/<ไฟล์>` และที่เก็บไฟล์เป็น `documents/<ไฟล์>` บนดิสก์ `local`
- การล็อกเมื่อเข้าสู่ระบบผิดเกิน 10 ครั้งใน 15 นาที ใช้ `RateLimiter` (เก็บใน cache) แทนตาราง `login_attempts`
- ตะกร้าเปรียบเทียบเก็บใน session ของ Laravel แทน sessionStorage ของเบราว์เซอร์
# insuredesk-larevel
