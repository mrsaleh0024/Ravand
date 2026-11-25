# Ravand — رَوَند

باشگاه مشتریان و مدیریت ارتباط با مشتریان (CRM) سبک برای فروشندگان شبکه‌های اجتماعی.

> وضعیت: MVP در حال ساخت — فاز ۰۰ (زیرساخت پایه)

| لایه         | فناوری                                                                 |
| ----------- | ---------------------------------------------------------------------- |
| بک‌اند       | Laravel 13 · PHP 8.3+                                                  |
| دیتابیس     | MySQL 8.4 LTS (محیط توسعه فعلی: MariaDB 10.4 در XAMPP)                 |
| فرانت‌اند    | Vue 3 · TypeScript · Inertia.js 3                                      |
| رابط کاربری | shadcn-vue · Tailwind CSS 4 · Lucide                                   |
| Build       | Vite (Vite+) · npm                                                     |
| محیط محلی   | XAMPP (MySQL) + `php artisan serve` — مسیر Docker Compose هم موجود است |

---

## پیش‌نیازها

- **PHP 8.3 یا بالاتر** با افزونه‌های `pdo_mysql`, `mbstring`, `intl`, `bcmath`, `zip`
- **Composer 2**
- **Node.js 22+** و npm
- **MySQL 8.4** یا MariaDB (در این پروژه با MySQL XAMPP تست می‌شود)

> ⚠️ PHP داخلی XAMPP نسخه ۸.۲ است و برای Laravel 13 کافی نیست. از MySQL همان XAMPP استفاده کنید، اما برنامه را با PHP 8.4 سیستم (مثلاً `C:\php\php.exe`) اجرا کنید.

---

## شروع سریع (هر بار)

```powershell
cd C:\Users\Lenovo\Desktop\Ravand
.\dev.ps1
```

این اسکریپت به‌ترتیب:

1. نسخه PHP را بررسی می‌کند (حداقل 8.3)
2. در صورت نبود `.env` آن را می‌سازد و `APP_KEY` تولید می‌کند
3. MySQL داخل XAMPP را در صورت خاموش بودن روشن می‌کند
4. دیتابیس و migrationها را آماده می‌کند
5. در صورت نبود، `composer install` و `npm install` را اجرا می‌کند
6. هم‌زمان سه پروسه را بالا می‌آورد: وب‌سرور، صف و Vite

با **Ctrl + C** هر سه پروسه با هم بسته می‌شوند.

> روی برخی سیستم‌ها اجرای اسکریپت با خطای Execution Policy متوقف می‌شود. در این حالت `.\dev.cmd` را اجرا کنید (همان کار را با دور زدن policy انجام می‌دهد) یا یک‌بار `Set-ExecutionPolicy -Scope CurrentUser RemoteSigned` را اجرا کنید.

---

## اجرای دستی (بدون اسکریپت)

```powershell
cd C:\Users\Lenovo\Desktop\Ravand
composer dev
```

معادل سه پروسه بالا بدون بررسی‌های خودکار. برای اجرای فقط وب‌سرور: `php artisan serve`.

---

## اجرای محلی روی ویندوز (XAMPP)

```powershell
# 1) MySQL را روشن کنید (XAMPP Control Panel → MySQL → Start)

# 2) دیتابیس بسازید (یک‌بار)
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS ravand CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 3) تنظیمات محیط
cd C:\Users\Lenovo\Desktop\Ravand
Copy-Item .env.example .env
php artisan key:generate

# 4) وابستگی‌ها و دارایی‌های فرانت
composer install
npm install
npm run build

# 5) ساخت جدول‌ها
php artisan migrate

# 6) اجرا
php artisan serve
```

سپس http://localhost:8000 را باز کنید.

> در `.env` محلی می‌توانید `APP_DEBUG=true` بگذارید؛ اما `.env.example` و محیط production باید `APP_DEBUG=false` بمانند.

## اجرای محلی با Docker (مسیر استاندارد سند)

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app npm install
docker compose exec app npm run build
docker compose exec app php artisan migrate
```

- دیتابیس در volume ماندگار `mysql-data` نگهداری می‌شود.
- پورت MySQL روی هاست `3307` است تا با MySQL محلی تعارض نداشته باشد.
- در `.env` مقدار `DB_HOST` برای سرویس app به `mysql` تنظیم می‌شود (در compose انجام شده است).

---

## دستورات رایج

| کار                     | دستور                                               |
| ----------------------- | --------------------------------------------------- |
| اجرای dev (سرور + Vite) | `composer dev`                                      |
| build فرانت             | `npm run build`                                     |
| فرمت PHP                | `composer lint`                                     |
| بررسی فرمت PHP          | `composer lint:check`                               |
| بررسی کد فرانت          | `npm run check`                                     |
| بررسی نوع‌ها             | `npm run types:check`                               |
| تست‌ها                   | `composer test`                                     |
| بازنشانی دیتابیس        | `php artisan migrate:fresh --seed` (فقط محیط توسعه) |

---

## ساختار پروژه

```text
app/                 کد دامنه و HTTP
  Http/              کنترلرها، میدل‌ور، فرم‌ریکوئست‌ها
config/              تنظیمات (timezone=UTC، locale=fa، utf8mb4)
database/            migrationها و seederها
docs/architecture/   تصمیم‌های معماری (ADR)
resources/js/        اپلیکیشن Vue: pages، layouts، components، lib، types
routes/              مسیرهای وب و auth
docker/              تصویر توسعه PHP
```

قراردادهای امنیتی و داده در `docs/architecture/decisions.md` ثبت می‌شوند.

---

## قواعد مهم توسعه

- هیچ‌وقت مستقیم روی `main` کد نزنید؛ هر فاز شاخه خودش را دارد (`phase/NN-...`).
- `.env`، `vendor/`، `node_modules/` و داده واقعی مشتری commit نمی‌شوند.
- تاریخ در دیتابیس میلادی/UTC و نمایش در UI شمسی است.
- مبالغ با `DECIMAL(20,0)` و `currency_code` ذخیره می‌شوند (هرگز float).
- هر Query دامنه‌ای باید به tenant جاری محدود باشد.

---

## English (short)

Ravand is a lightweight CRM and customer club for social-media sellers in Iran. This repository is the MVP monolith: Laravel 13 + Inertia 3 + Vue 3/TypeScript + shadcn-vue + Tailwind 4, targeting MySQL 8.4 (XAMPP/MariaDB currently used for local development).

```bash
cp .env.example .env && php artisan key:generate
composer install && npm install && npm run build
php artisan migrate && php artisan serve
```

Architecture decisions live in `docs/architecture/decisions.md`. Never commit directly to `main`; each delivery phase gets its own `phase/NN-*` branch.
