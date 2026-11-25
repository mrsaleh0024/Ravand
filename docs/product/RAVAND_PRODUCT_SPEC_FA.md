# سند مشخصات محصول و قرارداد توسعه

## RAVAND — باشگاه مشتریان و مدیریت ارتباط با مشتریان فروشندگان شبکه‌های اجتماعی

**نسخه سند:** 1.0  
**وضعیت:** مبنای توسعه MVP  
**زبان محصول:** فارسی  
**بازار هدف فعلی:** ایران  
**نام کاری پروژه:** `Ravand` / «رَوَند»  
**نوع محصول:** نرم‌افزار تحت وب، چندفروشگاهی (Multi-tenant)، قابل توسعه به SaaS  

> نام Ravand فعلاً نام کاری پروژه است؛ پیش از عرضه تجاری، نام تجاری، دامنه و تعارض احتمالی علامت تجاری جداگانه بررسی شود.

---

# 0. خلاصه اجرایی و تصمیم‌های قطعی

Ravand یک CRM سبک و باشگاه مشتریان برای فروشندگان خرد و فروشندگان فعال در شبکه‌های اجتماعی است. ثبت مشتری و سفارش در نسخه اولیه دستی انجام می‌شود. محصول در MVP هیچ قابلیت هوش مصنوعی داخلی، اتصال به مدل زبانی، تولید embedding، ارسال خودکار پیام یا اتصال خودکار به اینستاگرام/واتساپ ندارد.

بااین‌حال، ساختار داده، شناسه‌ها، فرهنگ داده و خروجی‌ها باید از ابتدا برای تحلیل ماشینی و استفاده آتی از AI آماده باشند؛ یعنی داده‌ها تمیز، دارای روابط روشن، نسخه‌بندی‌شده، قابل خروجی گرفتن و بدون ابهام در واحد پول، تاریخ و وضعیت‌ها باشند.

## تصمیم‌های محصول

| موضوع | تصمیم |
|---|---|
| نام کاری | Ravand |
| بازار | ایران؛ فارسی و RTL در MVP |
| ارز فروشگاه | تنظیم‌پذیر بین IRR (ریال) و IRT (تومان) |
| پایگاه داده | MySQL 8.4 LTS |
| بک‌اند | Laravel 13، در صورت شروع پروژه جدید و سازگاری محیط |
| PHP | نسخه پشتیبانی‌شده توسط Laravel انتخابی؛ برای Laravel 13 حداقل 8.3 |
| فرانت‌اند | Vue 3 + TypeScript + Inertia.js |
| UI kit | shadcn-vue با Tailwind CSS |
| ظاهر | مینیمال، سفید/مشکی، خلوت، الهام‌گرفته از کیفیت بصری z.ai؛ بدون کپی مستقیم برند یا رابط آن |
| تاریخ | ذخیره میلادی/UTC در دیتابیس؛ نمایش و ورود شمسی در رابط فارسی |
| نوع استقرار MVP | قابل اجرا با Docker Compose در محیط محلی؛ مستقل از ارائه‌دهنده هاست |
| الگوی معماری | Modular Monolith؛ نه Microservices |
| چندفروشگاهی | از ابتدا با tenant_id و جداسازی اجباری داده‌ها |
| کاربران اولیه | Owner؛ نقش‌های Manager و Operator برای توسعه بعدی آماده شوند |
| ثبت فروش | دستی |
| کانال فروش | Instagram، WhatsApp، Telegram، Direct، In-person، Other |
| پرداخت | پرداخت کامل حالت معمول؛ مدل داده پرداخت چندرکوردی و قابل توسعه باشد |
| محصولات | کالا، دسته‌بندی، تنوع رنگ/سایز/قواره و موجودی |
| خروجی داده | JSON/JSONL و CSV؛ بسته AI-ready نسخه‌بندی‌شده؛ XLSX در صورت توجیه |
| تست | در هر فاز فقط تست‌های سبک و smoke checks هدفمند؛ بدون تست‌نویسی گسترده در MVP |

## هدف‌های MVP

1. ثبت و جست‌وجوی مشتریان بدون وابستگی به شماره موبایل یکتا.
2. نگهداری چند شماره، چند شناسه اجتماعی و چند آدرس برای هر مشتری.
3. ثبت سلیقه، علاقه‌مندی، سایز/اندازه، تگ و یادداشت مشتری.
4. تعریف محصول و تنوع‌های آن، از جمله رنگ، سایز و قواره.
5. ثبت دستی سفارش، اقلام سفارش، پرداخت و وضعیت تحویل.
6. مدیریت موجودی پایه با گردش‌های قابل ردیابی.
7. مشاهده تاریخچه خرید و شاخص‌های مشتق‌شده مشتری.
8. باشگاه پایه با دفترکل امتیاز (Loyalty Ledger)، بدون ارسال پیام خودکار.
9. خروجی ساخت‌یافته و قابل فهم برای ابزارهای AI خارجی.
10. آماده بودن معماری برای چندفروشگاهی و نقش‌های بیشتر، بدون پیاده‌سازی صورتحساب SaaS در MVP.

## خارج از محدوده MVP

- تماس با هر API هوش مصنوعی یا استفاده از Laravel AI SDK، LLM، embeddings یا vector database.
- اتصال خودکار به Instagram، WhatsApp، Telegram یا شبکه‌های اجتماعی.
- ارسال خودکار SMS/WhatsApp/Email و کمپین خودکار.
- حسابداری کامل، دفتر کل مالی و مالیات.
- انبارداری چندشعبه‌ای و تأمین‌کنندگان پیشرفته.
- اپلیکیشن Native موبایل.
- Subscription Billing و پرداخت اشتراک فروشندگان.
- بازارگاه، چندارزی هم‌زمان در هر فروشگاه، یا چندکشوری.
- سیستم بازاریابی پیچیده و امتیازدهی مبتنی بر الگوریتم‌های AI.

---

# 1. قرارداد اجرای ابزار توسعه (مهم — برای هر فاز رعایت شود)

این بخش برای ابزار توسعه‌دهنده‌ای است که به مخزن GitHub دسترسی می‌گیرد. ابزار باید فقط فاز صریحاً درخواست‌شده را اجرا کند.

## 1.1. قبل از هر تغییر

1. مخزن و شاخه پیش‌فرض آن را شناسایی کن؛ فرض نکن شاخه اصلی حتماً `main` است.
2. فایل‌های `AGENTS.md`، `CLAUDE.md`، `README.md`، راهنمای مشارکت، اسکریپت‌های موجود، ساختار پروژه و تصمیم‌های معماری فعلی را بخوان.
3. وضعیت Git را بررسی کن. اگر تغییرات محلی/Commit نشده متعلق به کاربر وجود دارد، هیچ‌کدام را حذف، stash، reset یا overwrite نکن؛ توقف کن و وضعیت را گزارش بده.
4. اگر پروژه از قبل وجود دارد، ابتدا استک، نسخه‌ها، الگوها و قراردادهای آن را بررسی کن. بدون نیاز واقعی، فریم‌ورک یا ساختار موجود را بازنویسی نکن.
5. اگر مخزن خالی است، طبق همین سند پروژه را bootstrap کن.
6. قبل از کدنویسی، محدوده فاز انتخاب‌شده و معیارهای پذیرش همان فاز را مرور کن. قابلیت فازهای بعدی را زودتر پیاده‌سازی نکن.

## 1.2. ساخت شاخه

برای هر فاز یک شاخه مستقل بساز. فازها زنجیره وابستگی دارند؛ مبنای هر branch باید آخرین مبنای تأییدشده شامل فازهای قبلی باشد (جزئیات در قواعد زیر):

| فاز | نام شاخه |
|---|---|
| 00 — بررسی مخزن و زیرساخت پایه | `phase/00-foundation` |
| 01 — هویت، tenant و پوسته UI | `phase/01-tenancy-ui` |
| 02 — CRM و پروفایل مشتری | `phase/02-customer-crm` |
| 03 — کاتالوگ، سفارش، پرداخت و موجودی | `phase/03-sales-inventory` |
| 04 — باشگاه، وفاداری و داشبورد | `phase/04-loyalty-dashboard` |
| 05 — خروجی AI-ready، گزارش و سخت‌سازی | `phase/05-exports-hardening` |

قواعد شاخه:

- مبنای هر فاز باید آخرین شاخه تأییدشده‌ای باشد که تمام فازهای پیش‌نیاز را در خود دارد. برای فاز 00 این مبنا شاخه پیش‌فرض remote است. برای فازهای بعدی: اگر فاز قبلی merge شده، از شاخه پیش‌فرض به‌روز استفاده کن؛ اگر merge نشده اما تأیید شده و قرار است ادامه همان زنجیره باشد، از شاخه فاز قبلی branch بگیر. این وابستگی را در گزارش اعلام کن.
- اگر شاخه فاز از قبل وجود دارد، بدون بررسی و اجازه آن را حذف یا بازنویسی نکن؛ وضعیت را گزارش بده.
- هیچ‌گاه مستقیماً روی `main`، `master` یا شاخه پیش‌فرض کدنویسی نکن.
- هیچ‌گاه `git push --force`، `reset --hard` یا بازنویسی تاریخچه انجام نده.
- پس از اتمام موفق smoke checks، شاخه فاز را به remote push کن؛ Pull Request یا merge فقط در صورت درخواست صریح انجام شود.
- اگر push به دلیل دسترسی/شبکه ممکن نبود، commitهای محلی را حفظ و علت را گزارش کن.

## 1.3. امنیت توکن GitHub

- توکن را از ورودی امن ابزار یا secret/environment variable دریافت کن.
- توکن را در فایل، کد، `.env.example`، README، command history، log، screenshot یا commit قرار نده.
- توکن را داخل URL remote مانند `https://TOKEN@github.com/...` قرار نده.
- از credential manager یا روش امن تأییدشده محیط اجرا استفاده کن.
- فقط دسترسی حداقلی لازم برای مخزن فعلی درخواست/استفاده شود.
- هرگز توکن را در پاسخ نهایی چاپ نکن.

## 1.4. شیوه کامیت

کامیت‌ها باید کوچک، مرتبط و قابل بازبینی باشند. از Conventional Commits استفاده کن:

- `feat(customers): add customer profile and contacts`
- `fix(orders): prevent stock deduction on cancelled order`
- `refactor(tenancy): centralize tenant scoping`
- `chore(deps): update compatible dependencies`
- `docs(architecture): document export schema`

قواعد:

- کامیت‌های متعدد و معنادار بهتر از یک کامیت بسیار بزرگ هستند.
- فایل‌های build، cache، log، `.env` و داده‌های واقعی مشتری هرگز commit نشوند.
- تغییرات فرمت‌دهی نامرتبط را با feature مخلوط نکن.
- Migrationها و کد مربوط به آن‌ها در یک فاز منسجم commit شوند.
- پیش از هر کامیت `git diff` و `git status` را بررسی کن.

## 1.5. چک‌های سبک پس از هر فاز

تست گسترده یا پوشش تست بالا در این MVP درخواست نشده است. بااین‌حال، حداقل چک‌های متناسب با فاز اجباری‌اند:

1. نصب/resolve dependencyها و build فرانت‌اند.
2. اجرای formatter/linter موجود، در صورت پیکربندی‌شدن.
3. اجرای migration روی دیتابیس آزمایشی/محلی خالی و بررسی rollback حداقلی در صورت امن بودن.
4. اجرای چند smoke test هدفمند برای مسیرهای اصلی فاز.
5. اجرای یک چک امنیتی tenant isolation و authorization در فازهایی که داده چندمستاجری را لمس می‌کنند.
6. چک دستی UI در اندازه desktop و mobile و راست‌به‌چپ بودن متن.

تست‌ها نباید به دیتابیس یا داده واقعی کاربر وصل شوند. هر خطای تست که از قبل در شاخه پایه وجود داشته ثبت و از خطاهای ایجادشده توسط فاز تفکیک شود.

## 1.6. گزارش پایان فاز

در پایان هر فاز گزارش بده:

- نام شاخه و commit hashها.
- خلاصه قابلیت‌های پیاده‌سازی‌شده.
- Migrationها و دستورات لازم برای اجرا.
- چک‌ها و smoke testهای اجراشده و نتیجه آن‌ها.
- فایل‌های مهم تغییرکرده.
- مواردی که عمداً به فاز بعد موکول شده‌اند.
- ریسک‌ها یا تصمیم‌های نیازمند تأیید صاحب محصول.
- وضعیت push به remote.

هیچ فازی را بدون گزارش محدودیت‌ها «کامل» اعلام نکن.

---

# 2. معماری کلان

## 2.1. Modular Monolith

اپلیکیشن یک Laravel application واحد است که منطق به ماژول‌های دامنه‌ای تقسیم می‌شود. Microservice، سرویس‌های جداگانه و ارتباط شبکه‌ای داخلی در MVP ممنوع/غیرضروری است.

ساختار پیشنهادی (در صورت سازگاری با ساختار موجود):

```text
app/
  Domain/
    Tenancy/
    Identity/
    Customers/
    Catalog/
    Inventory/
    Sales/
    Loyalty/
    Engagement/
    Analytics/
    Exports/
  Http/
    Controllers/
    Middleware/
    Requests/
    Resources/
  Policies/
  Support/
    Money/
    Dates/
    PersianText/
    Export/
    Security/
resources/
  js/
    components/
    layouts/
    pages/
    composables/
    lib/
    types/
  css/
database/
  migrations/
  seeders/
docs/
  architecture/
  data-dictionary/
```

این ساختار الزام به ایجاد تعداد زیادی کلاس/لایه صوری ایجاد نمی‌کند. فقط مسئولیت‌ها باید واضح باشند و Controllerها نازک بمانند.

## 2.2. Multi-Tenancy

Ravand از ابتدا multi-tenant طراحی می‌شود؛ حتی اگر در شروع فقط یک فروشگاه فعال باشد.

مدل پیشنهادی MVP: **Shared Database + Shared Schema + tenant_id**.

- جدول `tenants` نماینده فروشگاه است.
- جدول `users` حساب کاربری انسانی است.
- جدول `tenant_user` عضویت کاربر در فروشگاه و نقش او را نگه می‌دارد.
- تقریباً تمام جداول دامنه‌ای دارای `tenant_id` هستند.
- tenant جاری از session/membership معتبر استخراج شود، نه از `tenant_id` ارسالی دلخواه در request.
- هر Query، Policy، Route Model Binding و عملیات Export باید به tenant جاری محدود باشد.
- برای جداول مرتبط، در صورت پشتیبانی مناسب Migration، کلیدهای خارجی مرکب شامل `(tenant_id, related_id)` تعریف شوند تا اتصال رکورد متعلق به tenant دیگر در سطح دیتابیس نیز دشوار شود.
- هرگز به پنهان کردن دکمه در UI به‌عنوان کنترل دسترسی اکتفا نکن.
- شناسه ULID یا UUID به‌تنهایی جایگزین authorization نیست.

**نکته امنیتی بسیار مهم:** دسترسی tenant باید در سرور enforce شود. تست حداقلی بین‌مستاجری باید تلاش کند کاربر tenant A با دستکاری شناسه URL/Request به رکورد tenant B دسترسی پیدا کند و پاسخ 404/403 مناسب دریافت کند.

## 2.3. زبان و نرمال‌سازی متن فارسی

- متن اصلی واردشده توسط کاربر بدون تغییر برای نمایش نگهداری شود.
- برای جستجو، نسخه نرمال‌شده در صورت نیاز تولید شود؛ متن اصلی جایگزین نشود.
- نرمال‌سازی جستجوی فارسی شامل تبدیل `ي` به `ی`، `ك` به `ک`، مدیریت نیم‌فاصله، حذف فاصله‌های ابتدا/انتها، یکسان‌سازی ارقام فارسی/عربی/لاتین و فشرده‌سازی فاصله‌های متوالی باشد.
- منطق نرمال‌سازی در یک سرویس مشترک و دارای چند smoke case باشد، نه در چند Controller مختلف.
- ورودی تلفن باید ارقام فارسی/عربی را بپذیرد و به مقدار canonical قابل جستجو تبدیل کند. برای شماره ایران ترجیحاً استاندارد E.164 با پیش‌شماره `+98` ذخیره شود؛ مقدار نمایشی اصلی هم باقی بماند.
- شماره تلفن یکتای قطعی مشتری نیست؛ چند نفر ممکن است از یک شماره خانوادگی استفاده کنند. به‌جای unique constraint، duplicate warning ارائه شود.

---

# 3. استک فنی و زیرساخت

## 3.1. استک پیشنهادی

در صورت خالی بودن مخزن یا نیاز به bootstrap جدید:

| لایه | انتخاب |
|---|---|
| Backend | Laravel 13.x |
| Runtime | PHP 8.3 یا نسخه بالاتر که رسماً با نسخه Laravel و بسته‌ها سازگار باشد |
| Database | MySQL 8.4 LTS |
| Frontend | Vue 3 + TypeScript + Inertia.js |
| UI | shadcn-vue |
| CSS | Tailwind CSS |
| Icons | مجموعه آیکون سبک و یکپارچه مانند Lucide |
| Build | Vite |
| Local environment | Docker Compose |
| Queue/Cache در MVP | database driver یا driver ساده قابل اجرا؛ بدون الزام Redis |
| File storage | Laravel Storage abstraction؛ Local در توسعه، قابل تعویض در production |

نسخه‌های patch و lockfile باید در زمان اجرای فاز بررسی شوند. نسخه ناسازگار یا منسوخ صرفاً برای تطبیق با این سند نصب نشود. اگر مخزن از قبل استک سالمی دارد، مهاجرت major فقط با دلیل فنی روشن انجام شود.

## 3.2. نسخه‌های رسمی مبنا

- Laravel 13 حداقل PHP 8.3 می‌خواهد و بازه PHP پشتیبانی‌شده آن در مستندات رسمی درج شده است.
- Vue starter kit رسمی Laravel از Vue 3، TypeScript، Inertia 3، Tailwind CSS و shadcn-vue استفاده می‌کند.
- برای MySQL، شاخه 8.4 LTS انتخاب شود، نه Innovation release؛ مگر دلیل عملیاتی مشخصی وجود داشته باشد.

منابع رسمی در انتهای سند آمده‌اند؛ ابزار توسعه باید پیش از bootstrap نسخه‌های patch و سازگاری dependencyها را دوباره بررسی کند.

## 3.3. اجرای محلی و قابلیت انتقال

- پروژه باید با Docker Compose قابل اجرا باشد.
- سرویس MySQL به‌صورت persistent volume نگهداری شود.
- `.env.example` کامل و بدون secret ارائه شود.
- هیچ مسیر فایل، hostname یا provider خاصی در کد business logic hardcode نشود.
- فایل‌ها از Laravel Storage abstraction استفاده کنند.
- صف، cache، session و storage از طریق config قابل تعویض باشند.
- برای اجرای local از `localhost` استفاده شود؛ دسترسی از اینترنت عمومی نیازمند سرور همیشه‌روشن، HTTPS و پیکربندی امن است.
- Tunnel به رایانه شخصی فقط برای دسترسی آزمایشی کوتاه‌مدت است و جایگزین میزبانی production نیست.
- در MVP هیچ داده واقعی مشتری روی یک tunnel عمومی یا سرور شخصی ناامن قرار نگیرد.

## 3.4. نکته هزینه و دامنه

برای توسعه و استفاده روی همان رایانه، خرید دامنه/هاست لازم نیست. برای استفاده روی چند دستگاه در یک شبکه محلی، می‌توان برنامه را روی رایانه/سرور محلی اجرا کرد. برای دسترسی امن و دائمی از اینترنت، در نهایت به محیط میزبانی پایدار نیاز است؛ سند به یک شرکت هاستینگ خاص وابسته نمی‌شود.

---

# 4. اصول مدل داده و استانداردهای سراسری

## 4.1. شناسه‌ها

- برای رکوردهای عمومی و قابل تبادل از ULID استفاده شود، مگر ساختار فعلی پروژه دلیل روشن دیگری داشته باشد.
- شناسه داخلی هرگز به‌تنهایی مجوز دسترسی تلقی نشود.
- شماره نمایشی مانند `CUS-000125` و `ORD-000125` جدا از ID فنی باشد و در محدوده هر tenant یکتا باشد.

## 4.2. نام‌گذاری

- نام جدول‌ها plural و snake_case باشد.
- نام ستون‌ها snake_case باشد.
- نام enumها/کدهای ذخیره‌شده به انگلیسی پایدار باشد؛ عنوان فارسی در UI/Dictionary نگهداری شود.
- نام فیلدهای خروجی AI نیز انگلیسی و پایدار باشد.
- از نام‌های مبهم مانند `type`, `data`, `value` بدون context کافی پرهیز شود.

## 4.3. تاریخ و زمان

- تمام timestampهای دیتابیس به UTC ذخیره شوند؛ convention روشن و یکسان برای MySQL `DATETIME` رعایت شود.
- timezone پیش‌فرض tenant در MVP برابر `Asia/Tehran` باشد و در تنظیمات tenant قابل تغییر بماند.
- تاریخ‌های تقویمی مانند تاریخ تولد به‌صورت `DATE` میلادی ذخیره شوند.
- UI فارسی تاریخ را شمسی نمایش دهد و ورود تاریخ شمسی را به تاریخ میلادی معتبر تبدیل کند.
- تبدیل شمسی/میلادی فقط از طریق Date Adapter مرکزی انجام شود؛ هیچ منطق تبدیل در فرم‌های پراکنده نوشته نشود.
- تاریخ خروجی JSON به‌صورت ISO 8601 میلادی و timestamp به‌صورت UTC با `Z` صادر شود. در صورت نیاز می‌توان فیلد نمایشی شمسی جداگانه و صریح افزود؛ فیلد شمسی جایگزین تاریخ canonical نیست.
- بسته تاریخ شمسی باید از نظر نگهداری، سازگاری Vue/TypeScript، timezone و صحت leap year بررسی و در یک adapter محصور شود.

## 4.4. پول و ارز

- هر tenant یک ارز پیش‌فرض انتخاب می‌کند: `IRR` یا `IRT`.
- در MVP یک سفارش با یک ارز انجام شود؛ تغییر تنظیم ارز tenant نباید معنای سفارش‌های قدیمی را تغییر دهد.
- هر رکورد مالی، `currency_code` خود را به‌صورت snapshot ذخیره کند.
- مبلغ با دقت صحیح و بدون Float ذخیره شود؛ برای MySQL از `DECIMAL(20,0)` استفاده شود.
- در PHP/TypeScript مبلغ از طریق Money Value Object/utility مدیریت شود و به float تبدیل نشود.
- واحد مبلغ صریح باشد: `100000` در IRR با `100000` در IRT برابر نیست.
- در UI عنوان ارز و جداکننده هزارگان نشان داده شود؛ مبالغ قدیمی با currency snapshot خود نمایش یابند.
- کدهای ارز: `IRR` = ریال، `IRT` = تومان (کد محلی اپلیکیشن؛ IRT کد رسمی ISO 4217 نیست).

## 4.5. حذف، بایگانی و تاریخچه

- حذف نرم فقط جایی استفاده شود که بازیابی یا بایگانی ارزش واقعی دارد.
- سفارش، پرداخت، گردش موجودی و گردش امتیاز پس از ثبت نهایی نباید hard-delete شوند.
- اصلاح مالی/امتیازی با سند اصلاحی یا reversal انجام شود، نه تغییر بی‌ردپا در ledger.
- حذف مشتری به‌صورت پیش‌فرض Archive/Inactive باشد.
- برای درخواست حذف/ناشناس‌سازی، سیاست حفظ سوابق مالی و اطلاعات شخصی جدا باشد؛ اطلاعات لازم برای اسناد مالی می‌تواند طبق الزام قانونی حفظ شود و PII غیرضروری ناشناس شود.

## 4.6. JSON در دیتابیس

JSON برای metadata و snapshotهای تاریخی مناسب است، نه جایگزین رابطه‌های اصلی.

- داده‌های اصلی مشتری، سفارش، پرداخت، موجودی و امتیاز relational باشند.
- attributes منعطف محصول/تنوع می‌تواند در جدول‌های attribute-value یا JSON کنترل‌شده باشد.
- اگر فیلد JSON برای گزارش‌گیری/فیلتر پرتکرار شد، به ستون یا جدول نرمال‌شده منتقل شود.
- ساختار JSON دارای schema version یا تعریف معتبر باشد؛ هر شیء دلخواه و بدون قرارداد ذخیره نشود.

---

# 5. مدل دامنه و موجودیت‌های داده

این بخش مقصد معماری را تعریف می‌کند. لازم نیست تمام موجودیت‌های آینده در فاز اول پیاده‌سازی شوند. فازهای توسعه در بخش 10 محدوده اجرا را مشخص می‌کنند.

## 5.1. هویت و tenant

### `tenants`

فیلدهای پیشنهادی:

- `id` ULID, PK
- `name` نام فروشگاه
- `slug` شناسه URL داخلی یکتا
- `status` (`active`, `suspended`, `archived`)
- `default_currency` (`IRR`, `IRT`)
- `timezone` پیش‌فرض `Asia/Tehran`
- `locale` پیش‌فرض `fa-IR`
- `calendar` پیش‌فرض `jalali`
- `settings` JSON برای تنظیمات کم‌تکرار و نسخه‌بندی‌شده
- `created_at`, `updated_at`

### `users`

- `id` ULID
- `name`
- `email` nullable، در صورت استفاده برای ورود باید normalized و unique باشد
- `phone` nullable
- `password`
- `status`
- `email_verified_at` nullable
- `last_login_at` nullable
- timestamps

### `tenant_user`

- `tenant_id`
- `user_id`
- `role` (`owner`, `manager`, `operator`)
- `status`
- `joined_at`
- unique `(tenant_id, user_id)`

در MVP ثبت‌نام عمومی باز نباشد. ایجاد Owner اولیه با seed/command امن یا invitation داخلی انجام شود. رمز عبور پیش‌فرض ثابت در کد/seed وجود نداشته باشد.

## 5.2. مشتریان

### `customers`

فیلدهای MVP:

- `id` ULID
- `tenant_id`
- `customer_number` شناسه نمایشی tenant-level مانند `CUS-000001`
- `display_name` نام قابل نمایش؛ اجباری
- `first_name` nullable
- `last_name` nullable
- `gender` nullable (`female`, `male`, `other`, `not_specified`؛ بدون اجبار به جمع‌آوری)
- `birth_date` nullable، میلادی DATE
- `customer_status` (`active`, `inactive`, `blocked`, `archived`)
- `acquisition_channel` (`instagram`, `whatsapp`, `telegram`, `referral`, `in_person`, `advertisement`, `other`, `unknown`)
- `acquisition_note` nullable
- `preferred_contact_channel` nullable
- `preferred_language` default `fa`
- `created_by_user_id`
- timestamps، `deleted_at` فقط در صورت نیاز

قیود/ایندکس:

- unique `(tenant_id, customer_number)`
- index `(tenant_id, customer_status, created_at)`
- index `(tenant_id, display_name)` یا ستون normalized برای جستجوی نام
- عدم unique بودن تلفن در جدول مشتری

### `customer_contacts`

برای پشتیبانی از چند شماره و کانال تماس:

- `id`, `tenant_id`, `customer_id`
- `contact_type` (`mobile`, `landline`, `email`, `other`)
- `label` مثل «شخصی»، «همسر»، «کار»
- `display_value` مقدار خام/نمایشی
- `normalized_value` مقدار canonical برای جستجو
- `country_code` nullable
- `is_primary`
- `is_verified`
- `verified_at` nullable
- `notes` nullable
- timestamps

شماره تلفن/ایمیل ممکن است بین چند مشتری مشترک باشد؛ unique constraint سراسری یا tenant-level روی مقدار تماس نگذار. در فرم ثبت و ویرایش، تطبیق مشابه‌ها را به‌شکل warning نشان بده.

### `customer_social_accounts`

- `id`, `tenant_id`, `customer_id`
- `platform` (`instagram`, `telegram`, `whatsapp`, `other`)
- `username` / `handle`
- `normalized_handle`
- `profile_url` nullable
- `display_name` nullable
- `is_primary`
- `notes` nullable
- timestamps

یک مشتری می‌تواند چند حساب/شناسه اجتماعی داشته باشد؛ handle ممکن است تغییر کند، پس تاریخچه تغییر آن در صورت نیاز از audit log قابل پیگیری باشد.

### `customer_addresses`

- `id`, `tenant_id`, `customer_id`
- `title` مثل خانه/محل کار
- `recipient_name` nullable
- `recipient_phone` nullable
- `province` nullable
- `city` nullable
- `district` nullable
- `postal_code` nullable
- `address_line` nullable
- `delivery_notes` nullable
- `is_default`
- timestamps

آدرس کامل در صفحه لیست مشتریان نمایش داده نشود. UI فقط شهر/استان را در صورت نیاز نشان دهد؛ آدرس کامل در پروفایل با دسترسی مناسب دیده شود.

### `customer_consents`

برای نگهداری شفاف رضایت ارتباطی، حتی قبل از فعال‌شدن پیام‌رسانی:

- `id`, `tenant_id`, `customer_id`
- `channel` (`sms`, `whatsapp`, `instagram`, `telegram`, `email`, `phone`)
- `purpose` (`transactional`, `marketing`, `service`)
- `status` (`granted`, `denied`, `withdrawn`, `unknown`)
- `captured_at`
- `capture_method` (`manual`, `form`, `message`, `other`)
- `evidence_note` nullable
- `captured_by_user_id` nullable

پیش‌فرض رضایت بازاریابی `unknown` یا `denied` باشد؛ صرف ثبت شماره تلفن به معنی رضایت تبلیغاتی نیست.

### `customer_preferences`

ترجیحات به‌صورت چند رکورد ساخت‌یافته ثبت شوند:

- `id`, `tenant_id`, `customer_id`
- `preference_category` (`product_category`, `color`, `fabric`, `style`, `occasion`, `price_sensitivity`, `budget_level`, `brand`, `other`)
- `value_code` کد پایدار مانند `black`, `cream`, `black_chador`
- `value_label` عنوان فارسی snapshot
- `preference_level` اختیاری (`like`, `dislike`, `neutral`)
- `source` (`customer_stated`, `purchase_inferred`, `staff_observed`)
- `confidence` nullable؛ فقط در صورت تعریف معنای روشن
- `notes` nullable
- timestamps

علایق استنباط‌شده از خرید باید از علایق اعلام‌شده توسط مشتری قابل تشخیص باشند.

### `customer_measurements`

- `id`, `tenant_id`, `customer_id`
- `measurement_code` مثل `manteau_size`, `trouser_size`, `scarf_size`, `chador_qovare`, `preferred_manteau_length`
- `value_text` nullable
- `value_number` nullable
- `unit` nullable (`cm`, `size`, `qovare` و غیره)
- `recorded_at`
- `source` (`customer_stated`, `staff_measured`, `purchase_inferred`)
- `notes` nullable

سایزها و اندازه‌ها در ستون‌های واحد مشتری ذخیره نشوند؛ امکان تغییر در طول زمان حفظ شود. اطلاعات اندازه‌گیری فقط در صورت نیاز کسب‌وکار جمع‌آوری شود.

### `tags`, `customer_tag`

- `tags`: `id`, `tenant_id`, `name`, `slug`, `color_token` اختیاری، timestamps
- `customer_tag`: `tenant_id`, `customer_id`, `tag_id`, `created_by_user_id`, `created_at`

تگ نمونه: «وفادار»، «حساس به قیمت»، «علاقه‌مند به چادر مشکی»، «نیازمند پیگیری». تگ نباید جایگزین وضعیت یا داده تراکنشی شود.

### `customer_notes`

- `id`, `tenant_id`, `customer_id`
- `body` متن ساده؛ HTML خام نپذیر
- `visibility` (`private`, `team`؛ در MVP اگر فقط owner وجود دارد private کافی است)
- `created_by_user_id`
- timestamps

یادداشت خصوصی و آدرس دقیق به‌صورت پیش‌فرض در AI export قرار نگیرند.

### `customer_interactions`

- `id`, `tenant_id`, `customer_id`
- `interaction_type` (`message`, `call`, `follow_up`, `complaint`, `question`, `purchase_related`, `other`)
- `channel` (`instagram`, `whatsapp`, `telegram`, `phone`, `in_person`, `other`)
- `subject` nullable
- `summary` متن خلاصه؛ HTML نپذیر
- `outcome` nullable (`purchase`, `no_purchase`, `waiting`, `resolved`, `unresolved`, `other`)
- `occurred_at` timestamp
- `follow_up_at` nullable
- `follow_up_status` nullable (`pending`, `done`, `cancelled`)
- `related_order_id` nullable
- `created_by_user_id`
- timestamps

پیام کامل شبکه اجتماعی در MVP ذخیره/آرشیو نشود؛ صرفاً خلاصه‌ای که فروشنده وارد می‌کند ثبت شود.

## 5.3. کاتالوگ محصول

### `product_categories`

- `id`, `tenant_id`
- `parent_id` nullable
- `name`
- `slug`
- `description` nullable
- `sort_order`
- `is_active`
- timestamps

ساختار والد/فرزند برای دسته‌هایی مانند چادر، چادر مشکی، چادر رنگی، روسری، مانتو و لگ پشتیبانی شود.

### `products`

- `id`, `tenant_id`, `category_id`
- `name`
- `description` nullable
- `brand` nullable
- `product_type` (`physical` در MVP؛ توسعه‌پذیر)
- `is_active`
- `track_inventory`
- timestamps

### `product_variants`

هر محصول حداقل یک variant پیش‌فرض داشته باشد تا قیمت و SKU در سفارش قابل snapshot شدن باشد.

- `id`, `tenant_id`, `product_id`
- `sku` nullable، unique در محدوده tenant در صورت مقداردهی
- `variant_name` nullable
- `attributes` JSON معتبر و schema-controlled برای ویژگی‌هایی مانند رنگ، سایز، قواره، قد، جنس پارچه
- `sale_price` DECIMAL(20,0)
- `cost_price` nullable DECIMAL(20,0)؛ فقط برای گزارش سود آینده و با دسترسی محدود
- `currency_code` (`IRR`/`IRT`)
- `is_active`
- timestamps

ویژگی‌های شناخته‌شده در UI باید فیلدهای فرم ساخت‌یافته باشند، نه JSON آزاد. برای آینده، تعریف attributeهای tenant-level و جداول نرمال‌شده قابل اضافه شدن بماند. مقدار ویژگی در سفارش snapshot شود تا تغییر variant، تاریخچه خرید را عوض نکند.

نمونه attributeها:

```json
{
  "color": { "code": "black", "label": "مشکی" },
  "size": { "code": "40", "label": "۴۰" },
  "chador_qovare": { "code": "120", "label": "۱۲۰" },
  "fabric": { "code": "crepe", "label": "کرپ" }
}
```

## 5.4. موجودی

### `inventory_movements`

منبع حقیقت گردش موجودی باشد؛ موجودی صرفاً یک عدد دستی قابل تغییر بدون تاریخچه نباشد.

- `id`, `tenant_id`, `product_variant_id`
- `idempotency_key` nullable؛ برای جلوگیری از ثبت دوباره حرکت مرتبط با یک عملیات
- `movement_type` (`opening`, `purchase_in`, `sale_out`, `return_in`, `adjustment_in`, `adjustment_out`, `cancellation_restore`)
- `quantity_delta` عدد صحیح signed
- `reference_type`, `reference_id` nullable
- `reason` nullable
- `occurred_at`
- `created_by_user_id`
- `created_at`

برای سادگی MVP می‌توان یک انبار پیش‌فرض برای هر tenant در نظر گرفت؛ معماری table/location بعداً قابل اضافه شدن باشد. اگر `stock_balance` به‌عنوان cache نگهداری می‌شود، منبع حقیقت همچنان movementها هستند و هر تغییر در یک DB transaction انجام شود.

قواعد:

- موجودی منفی به‌صورت پیش‌فرض ممنوع باشد؛ امکان تنظیم backorder فقط در آینده.
- سفارش پیش‌نویس موجودی را کم نکند.
- کاهش موجودی در نقطه مشخص و مستند چرخه سفارش انجام شود؛ در MVP ترجیحاً هنگام ثبت نهایی/تأیید سفارش و با کنترل transaction/row lock.
- لغو سفارش قبل از تحویل، movement معکوس ایجاد کند؛ movement قبلی حذف/ویرایش نشود.
- مرجوعی فقط پس از ثبت وضعیت و تعیین سالم/غیرقابل فروش بودن کالا به موجودی اضافه شود. در MVP فقط مرجوعی کامل سفارش یا ثبت وضعیت کلی سفارش پشتیبانی شود؛ مرجوعی جزئی اقلام و فرآیند تعویض پیشرفته به roadmap موکول است.

## 5.5. سفارش و پرداخت

### `orders`

- `id`, `tenant_id`, `customer_id`
- `idempotency_key` nullable و unique در محدوده tenant برای جلوگیری از ساخت سفارش تکراری در retry/double-submit
- `order_number` یکتا در tenant
- `sales_channel` (`instagram`, `whatsapp`, `telegram`, `direct`, `in_person`, `other`)
- `fulfillment_method` (`shipping`, `pickup`, `in_person`)
- `status` (`draft`, `confirmed`, `processing`, `shipped`, `delivered`, `completed`, `cancelled`, `returned`)
- `returned_at` nullable و `return_note` nullable برای ثبت مرجوعی کامل MVP
- `placed_at`
- `completed_at` nullable
- `subtotal_amount` DECIMAL(20,0)
- `discount_amount` DECIMAL(20,0) default 0
- `shipping_amount` DECIMAL(20,0) default 0
- `total_amount` DECIMAL(20,0)
- `currency_code` snapshot
- `customer_note` nullable
- `internal_note` nullable
- `shipping_address_snapshot` JSON nullable، برای حفظ آدرس زمان سفارش
- `created_by_user_id`
- timestamps

جمع کل از محاسبات سمت سرور محاسبه شود؛ به مبلغ total ارسال‌شده از مرورگر اعتماد نشود.

### `order_items`

- `id`, `tenant_id`, `order_id`
- `product_id` nullable برای حفظ رکورد در صورت archive محصول
- `product_variant_id` nullable
- `product_name_snapshot`
- `variant_name_snapshot` nullable
- `variant_attributes_snapshot` JSON nullable
- `sku_snapshot` nullable
- `quantity` عدد صحیح مثبت
- `unit_price` DECIMAL(20,0)
- `discount_amount` DECIMAL(20,0)
- `line_total` DECIMAL(20,0)
- `cost_price_snapshot` nullable
- timestamps

### `payments`

- `id`, `tenant_id`, `order_id`
- `idempotency_key` nullable و unique در محدوده tenant برای جلوگیری از ثبت پرداخت تکراری
- `amount` DECIMAL(20,0)
- `currency_code`
- `payment_method` (`cash`, `card_to_card`, `pos`, `online`, `bank_transfer`, `other`)
- `status` (`recorded`, `refunded`, `voided`)
- `paid_at`
- `reference_code` nullable
- `note` nullable
- `created_by_user_id`
- timestamps

در UI حالت پیش‌فرض ثبت یک پرداخت کامل باشد. مدل چند پرداختی امکان ثبت بیعانه/تسویه بعدی را بدون بازطراحی فراهم کند، اما پیچیدگی پرداخت اعتباری در MVP لازم نیست.

قواعد مالی:

- `subtotal - discount + shipping = total` با rounding مشخص و تست smoke.
- مجموع پرداخت‌های ثبت‌شده و برگشت‌نخورده وضعیت پرداخت را تعیین کند (`unpaid`, `partially_paid`, `paid`, `refunded`).
- سفارش کامل‌شده و پرداخت‌نشده نباید در UI بی‌هشدار پذیرفته شود؛ اگر فروش اعتباری بعداً لازم شد سیاست جدا تعریف شود.
- مبلغ order item در زمان خرید snapshot شود؛ تغییر قیمت محصول سفارش قدیمی را تغییر ندهد.
- مبالغ همه اقلام یک سفارش از یک currency_code استفاده کنند.

## 5.6. وفاداری

### `loyalty_settings`

یک تنظیم به ازای tenant:

- `tenant_id`
- `enabled`
- `earn_amount_step` DECIMAL(20,0): مبلغ لازم برای هر گام امتیاز در ارز tenant
- `points_per_step` عدد صحیح
- `currency_code`
- `redemption_enabled` (در MVP می‌تواند false باشد تا سیاست تبدیل به تخفیف تأیید شود)

### `loyalty_transactions`

دفترکل غیرقابل ویرایش:

- `id`, `tenant_id`, `customer_id`
- `idempotency_key` nullable و unique در محدوده tenant در صورت مقداردهی
- `transaction_type` (`earned`, `redeemed`, `manual_adjustment`, `reversal`, `expired`)
- `points_delta` عدد صحیح signed
- `source_type`, `source_id` nullable
- `reason` nullable و برای adjustment اجباری
- `created_by_user_id` nullable
- `created_at`

امتیاز جاری از جمع `points_delta` به دست می‌آید؛ در صورت cache شدن balance، با ledger در transaction همگام شود.

قواعد:

- امتیاز فقط در نقطه تکمیل سفارش یا نقطه‌ای صریح و مستند تعلق بگیرد.
- لغو/مرجوعی سفارش باعث تراکنش reversal شود.
- تراکنش قبلی ویرایش یا حذف نشود.
- امتیاز منفی، اگر لازم شد، با ثبت علت و کنترل نقش مجاز باشد.
- تاریخ انقضای امتیاز، چند سطح پیچیده، مأموریت و referral reward خارج از MVP است.

## 5.7. داده‌های مشتق‌شده مشتری

شاخص‌های زیر در MVP از سفارش‌ها و ledger محاسبه شوند؛ از ذخیره دستی آن‌ها خودداری شود:

- `orders_count`
- `completed_orders_count`
- `total_spent`
- `average_order_value`
- `first_order_at`
- `last_order_at`
- `days_since_last_order`
- `favorite_categories`
- `available_points`
- `returns_count`

برای MVP با حدود ۵۰۰۰ مشتری، Queryهای ایندکس‌شده و aggregateهای مناسب کافی‌اند. اگر بعداً performance نیاز داشت، summary/cache table و job بازسازی‌پذیر اضافه شود. هیچ cached aggregate نباید تنها منبع حقیقت باشد.

---

# 6. تجربه کاربری و UI/UX

UI/UX یکی از معیارهای اصلی کیفیت محصول است. از الگوهای عمومی admin dashboard شلوغ و قالب‌های آماده با عناصر زیاد استفاده نکن.

## 6.1. جهت هنری

- سفید و مشکی، مینیمال، مرتب و با فضای تنفس زیاد.
- پس‌زمینه اصلی سفید؛ متن اصلی نزدیک به مشکی؛ خطوط و سطوح فرعی خاکستری روشن.
- رنگ accent محدود باشد؛ رنگ‌ها بیشتر برای وضعیت موفق/هشدار/خطا استفاده شوند.
- بدون gradient، glow، glassmorphism، سایه‌های سنگین و کارت‌های زیاد.
- radius کنترل‌شده؛ گوشه‌های حدود 8 تا 12 پیکسل.
- border ظریف و typography واضح.
- انیمیشن کوتاه و کاربردی؛ نه تزئینی و سنگین.
- کیفیت بصری نزدیک به محصولات SaaS مدرن و خلوت؛ از برند z.ai کپی نکن.

توکن‌های رنگ پیشنهادی:

```css
:root {
  --background: #ffffff;
  --foreground: #111111;
  --surface: #fafafa;
  --surface-muted: #f5f5f5;
  --border: #e5e5e5;
  --muted-foreground: #737373;
  --primary: #111111;
  --primary-foreground: #ffffff;
  --destructive: #dc2626;
  --success: #15803d;
  --warning: #a16207;
}
```

این مقادیر باید به design token تبدیل شوند و مستقیم در ده‌ها فایل تکرار نشوند.

## 6.2. فونت و فارسی

- رابط به‌صورت RTL-first طراحی شود.
- فونت فارسی خوانا مانند Vazirmatn، ترجیحاً self-hosted و مطابق مجوز آن، استفاده شود.
- برای اعداد، شماره موبایل، قیمت و کد سفارش، نمایش خوانا و یکدست انتخاب شود؛ ترتیب ارقام/متن در RTL به‌دقت بررسی شود.
- ورودی‌های شماره موبایل، ایمیل، URL و کد سفارش در صورت نیاز از جهت LTR داخلی استفاده کنند.
- متن‌های UI ترجمه‌پذیر باشند؛ متن فارسی در منطق business code hardcode نشود.
- تمام صفحات شامل empty state، loading، error، success و حالت بدون مجوز باشند.

## 6.3. Layout پایه

- Sidebar ثابت/قابل جمع‌شدن در desktop.
- Header جمع‌وجور با عنوان صفحه، جستجوی سریع و منوی کاربر.
- محتوای اصلی با max-width منطقی و spacing consistent.
- در موبایل sidebar به drawer تبدیل شود.
- جداول در موبایل به نمایش کارت‌مانند یا scroll قابل استفاده تبدیل شوند؛ صفحه از viewport بیرون نزند.
- فرم‌های طولانی به sectionهای واضح تقسیم شوند.

## 6.4. Navigation پیشنهادی

1. داشبورد
2. مشتریان
3. سفارش‌ها
4. محصولات
5. موجودی
6. باشگاه مشتریان
7. خروجی و گزارش‌ها
8. تنظیمات

در فازهایی که ماژول هنوز توسعه نیافته، لینک غیرواقعی یا دکمه بدون عملکرد نشان داده نشود.

## 6.5. صفحات حیاتی

### فهرست مشتریان

- جستجوی سریع بر اساس نام، تلفن‌های ثبت‌شده و handle اجتماعی.
- فیلتر بر اساس وضعیت، تگ، شهر، کانال جذب، آخرین خرید و دسته مورد علاقه.
- ستون‌های پیشنهادی: نام، تماس اصلی، شهر، تعداد خرید، مجموع خرید، آخرین خرید، تگ‌ها.
- نمایش مبلغ و تعداد خرید از داده محاسبه‌شده؛ نه مقدار دستی.
- ایجاد مشتری با کمترین کلیک.
- هشدار duplicate احتمالی، بدون جلوگیری قطعی به خاطر شماره مشترک خانوادگی.
- pagination سروری.

### فرم مشتری

فرم اولیه خلوت باشد:

- نام نمایشی (الزامی)
- شماره موبایل (اختیاری؛ امکان چند شماره)
- آیدی شبکه اجتماعی
- کانال جذب
- شهر
- یادداشت اولیه

بخش‌های تکمیلی در accordion/tab یا پس از ذخیره در پروفایل قابل تکمیل باشند: آدرس‌ها، ترجیحات، اندازه‌ها، رضایت‌ها و اطلاعات تکمیلی. تمام فیلدهای ممکن را در فرم اولیه نریز.

### پروفایل مشتری

یک نمای واحد با بخش‌های:

- خلاصه و شاخص‌ها
- اطلاعات و راه‌های تماس
- سفارش‌ها و اقلام خریداری‌شده
- علایق و اندازه‌ها
- تگ‌ها و یادداشت‌ها
- تعاملات و پیگیری‌ها
- امتیاز و وفاداری

### ثبت سفارش

- جستجو/انتخاب مشتری سریع.
- انتخاب محصول و variant.
- انتخاب رنگ/سایز/قواره بر اساس ویژگی‌های تعریف‌شده.
- تعداد، قیمت، تخفیف و هزینه ارسال.
- کانال فروش و روش تحویل.
- ثبت پرداخت کامل به‌صورت پیش‌فرض.
- خلاصه مبلغ واضح و محاسبه نهایی سمت سرور.
- کنترل موجودی و جلوگیری از double-submit.
- فرم مناسب موبایل؛ فروشنده ممکن است هنگام گفت‌وگو با مشتری با گوشی سفارش ثبت کند.

## 6.6. دسترس‌پذیری

- contrast قابل قبول.
- focus visible برای کیبورد.
- label واقعی برای تمام inputها.
- خطاها کنار فیلد و با متن قابل فهم.
- وضعیت فقط با رنگ منتقل نشود؛ متن/آیکون نیز داشته باشد.
- modalها keyboard accessible باشند.

---

# 7. جستجو، duplicate detection و عملکرد

## 7.1. جستجوی مشتری

- جستجو باید در tenant جاری محدود شود.
- در MVP MySQL کافی است؛ Elasticsearch/Meilisearch لازم نیست.
- نام با ستون نرمال‌شده یا روش جستجوی مقاوم به تفاوت ک/ک، ی/ي و ارقام جستجو شود.
- تلفن با normalized_value و index جستجو شود.
- social handle با normalized_handle جستجو شود.
- در ۵۰۰۰ مشتری، پاسخ‌دهی سریع با index و pagination باید به‌راحتی قابل دستیابی باشد.
- از queryهای `%term%` روی تمام ستون‌ها بدون بررسی performance استفاده نشود.

## 7.2. تشخیص مشتری تکراری

هنگام ایجاد مشتری، matchهای احتمالی بر اساس این موارد نمایش داده شوند:

- شماره canonical یکسان
- handle اجتماعی یکسان
- نام نرمال‌شده مشابه همراه با شهر/تلفن هم‌خوان

این قابلیت **warning** است، نه unique validation؛ ممکن است اعضای خانواده شماره مشترک داشته باشند. کاربر می‌تواند با ثبت دلیل/تأیید آگاهانه مشتری جدید بسازد.

## 7.3. عملکرد و مقیاس

- pagination سروری.
- eager loading روابط نمایش‌داده‌شده برای جلوگیری از N+1.
- index روی tenant_id و ستون‌های پرتکرار جستجو/فیلتر.
- exportهای بزرگ به‌صورت chunk/stream یا job اجرا شوند.
- تصاویر در MVP اختیاری و با محدودیت حجم/نوع باشند.
- برای ۵۰۰۰ مشتری از microservices یا search cluster استفاده نشود.

---

# 8. امنیت، حریم خصوصی و حفاظت از داده

این بخش در تمام فازها الزام است؛ امنیت به فاز آخر موکول نمی‌شود.

## 8.1. احراز هویت و نشست

- استفاده از auth استاندارد Laravel و password hashing امن.
- rate limit برای login، reset password و endpointهای حساس.
- CSRF protection فعال.
- session cookie با `HttpOnly`, `Secure` در HTTPS و `SameSite` مناسب.
- session regeneration پس از login.
- logout تمام نشست جاری را invalidate کند.
- registration عمومی تا زمانی که محصول SaaS واقعاً آماده نشده خاموش باشد.
- امکان افزودن 2FA در roadmap؛ در MVP حداقل login rate limit و password policy مناسب.

## 8.2. مجوزها

- استفاده از Policies/Gates یا لایه authorization معادل.
- نقش‌ها در server-side بررسی شوند.
- Owner کامل؛ Manager مدیریت عملیات؛ Operator محدود به ثبت/ویرایش موردنیاز.
- Operator در آینده به داده مالی، export کامل و تنظیمات حساس دسترسی پیش‌فرض نداشته باشد.
- تمام عملیات با tenant جاری scope شوند.

## 8.3. ورودی و خروجی

- validation سمت سرور برای همه ورودی‌ها.
- از Eloquent/Query Builder و binding استفاده شود؛ SQL پویا از ورودی مستقیم ساخته نشود.
- متن یادداشت‌ها plain text باشد؛ HTML خام رندر نشود.
- در صورت نیاز به rich text در آینده، sanitizer معتبر و allowlist استفاده شود.
- فایل upload با allowlist MIME، سقف حجم، نام تصادفی و storage خصوصی انجام شود.
- خطاهای production شامل stack trace یا اطلاعات دیتابیس به کاربر نمایش داده نشود.

## 8.4. اسرار و پیکربندی

- `.env` در Git نباشد.
- `APP_DEBUG=false` در production.
- `APP_KEY` واقعی هر محیط مستقل باشد و هرگز تغییر/commit تصادفی نشود.
- secretها در secret manager/متغیر محیطی قرار گیرند.
- logها token، password، session، اطلاعات کارت یا PII غیرضروری را ثبت نکنند.
- دیتابیس با کاربر محدود و بدون مجوزهای مدیریتی غیرضروری اجرا شود.

## 8.5. حریم خصوصی مشتری

- فقط اطلاعاتی جمع‌آوری شود که برای فروش، ارسال یا خدمات مشتری لازم است.
- تاریخ تولد، جنسیت، اندازه‌ها و آدرس‌های دقیق اختیاری باشند.
- رضایت بازاریابی مستقل از ثبت مشتری ذخیره شود.
- در MVP پیام تبلیغاتی خودکار وجود ندارد؛ بااین‌حال consent model از ابتدا باشد.
- دسترسی به آدرس دقیق و یادداشت خصوصی محدود شود.
- قابلیت archive و فرآیند ناشناس‌سازی در roadmap مستند باشد.
- خروجی‌های حاوی PII باید صریحاً مشخص و فقط توسط نقش مجاز دریافت شوند.

## 8.6. Audit Log

حداقل این رخدادها ثبت شوند:

- login موفق/ناموفق (بدون password)
- ایجاد/ویرایش/بایگانی مشتری
- ایجاد/تغییر سفارش و وضعیت آن
- ثبت/برگشت پرداخت
- تغییر موجودی
- تغییر امتیاز دستی
- تغییر نقش/عضویت
- ایجاد و دانلود export، به‌ویژه export دارای PII

جدول پیشنهادی `audit_logs`: `id`, `tenant_id`, `actor_user_id` nullable، `action`, `entity_type`, `entity_id`, `occurred_at`, `ip_address` با retention محدود و `metadata` کم‌حجم. Audit log برای کاربر عادی قابل ویرایش/حذف نباشد.

Audit log شامل actor، tenant، action، entity type/id، timestamp و metadata محدود باشد. از ذخیره مقدار کامل حساس قبل/بعد خودداری شود؛ فقط diff غیرحساس یا فیلدهای انتخاب‌شده ثبت شوند.

## 8.7. بکاپ و بازیابی

- بکاپ دیتابیس به‌صورت منظم و خارج از ماشین اصلی نگهداری شود.
- فایل بکاپ رمزنگاری شود.
- restore حداقل به‌صورت دوره‌ای آزمایش شود؛ داشتن بکاپ بدون آزمون بازیابی کافی نیست.
- retention بکاپ مشخص باشد.
- قبل از migrationهای مخرب بکاپ قابل بازیابی گرفته شود.

---

# 9. استاندارد خروجی AI-ready (قابلیت کلیدی محصول)

Ravand هیچ AI داخلی ندارد. این بخش صرفاً قرارداد داده و export است تا خروجی به هر ابزار خارجی قابل ارائه باشد.

## 9.1. انواع خروجی

1. **Customer Profile JSON:** خروجی یک مشتری با داده‌های مرتبط.
2. **JSONL Bundle:** خروجی مجموعه‌ای مناسب تحلیل و datasetهای بزرگ.
3. **CSV Bundle:** فایل CSV جدا برای هر موجودیت/جدول.
4. **XLSX:** فقط برای مرور دستی و در صورت نیاز؛ فرمت اصلی تبادل داده نیست.

## 9.2. ساختار بسته AI-ready

خروجی کامل به‌صورت ZIP:

```text
ravand-export-<tenant-slug>-<date>.zip
  manifest.json
  README.fa.md
  ANALYSIS_CONTEXT.fa.md
  DATA_DICTIONARY.fa.md
  schema/
    customers.schema.json
    orders.schema.json
    order_items.schema.json
    products.schema.json
    product_variants.schema.json
    payments.schema.json
    interactions.schema.json
    loyalty_transactions.schema.json
  data/
    customers.jsonl
    customer_contacts.jsonl
    customer_social_accounts.jsonl
    customer_preferences.jsonl
    customer_measurements.jsonl
    customer_tags.jsonl
    orders.jsonl
    order_items.jsonl
    payments.jsonl
    products.jsonl
    product_variants.jsonl
    interactions.jsonl
    loyalty_transactions.jsonl
```

فایل‌های بدون داده حذف یا با صفر رکورد صادر شوند؛ رفتار ثابت و مستند باشد.

## 9.3. قرارداد داده خروجی

- `schema_version` اجباری؛ مثال `1.0.0`.
- هر رکورد دارای `id` پایدار و `tenant_ref` ناشناس/غیرحساس در صورت نیاز باشد.
- روابط با ID حفظ شوند؛ از تکرار اطلاعات و نام‌های مبهم پرهیز شود.
- نام فیلدها انگلیسی snake_case؛ مقادیر متنی می‌توانند فارسی باشند.
- enumها با کد ثابت انگلیسی صادر شوند.
- dictionary برای هر کد، عنوان فارسی، تعریف، نوع و مثال ارائه کند.
- تاریخ‌ها Gregorian و ISO 8601؛ timestampها UTC.
- مبلغ‌ها به شکل integer string/decimal string صادر شوند تا precision در JavaScript از دست نرود؛ همراه `currency_code`.
- فیلد خالی به‌صورت `null` صادر شود؛ رشته خالی و null معنای یکسان نداشته باشند.
- ترتیب رکوردها تضمین نشود مگر صریحاً بر اساس `id`/تاریخ مرتب شده باشد.
- `generated_at`, `tenant_timezone`, `default_currency`, `filters`, `included_entities`, `redaction_mode` در manifest ثبت شوند.
- داده‌های مشتق‌شده با برچسب `derived` و روش محاسبه مستند شوند.

نمونه `manifest.json`:

```json
{
  "product": "Ravand",
  "schema_version": "1.0.0",
  "generated_at": "2026-11-25T18:30:00Z",
  "tenant_timezone": "Asia/Tehran",
  "default_currency": "IRT",
  "date_calendar_storage": "gregorian",
  "redaction_mode": "anonymized",
  "included_entities": ["customers", "orders", "order_items", "products"],
  "record_counts": {
    "customers": 120,
    "orders": 340
  }
}
```

## 9.4. حالت‌های حریم خصوصی خروجی

### حالت پیش‌فرض: `anonymized`

- نام/نام خانوادگی و نام نمایشی به شناسه pseudonymous تبدیل یا حذف شوند.
- موبایل، ایمیل، آیدی شبکه اجتماعی و آدرس صادر نشوند.
- یادداشت خصوصی و شواهد رضایت صادر نشوند.
- IDهای پایدار مخصوص همان export حفظ شوند تا رابطه سفارش/مشتری قابل تحلیل باشد.
- فیلدهای لازم برای تحلیل خرید، دسته محصول، مبلغ، فاصله خرید و ترجیحات غیرحساس حفظ شوند.

### حالت `full_pii`

- فقط Owner مجاز باشد.
- UI هشدار واضح دهد که فایل حاوی داده شخصی است.
- دلیل/تأیید صریح قبل از تولید ثبت شود.
- رویداد export در audit log ذخیره شود.
- فایل در storage خصوصی باشد، URL عمومی دائمی نداشته باشد و پس از مدت کوتاه (مثلاً 24 ساعت) حذف شود.

## 9.5. توضیح زمینه کسب‌وکار

`ANALYSIS_CONTEXT.fa.md` باید شامل موارد زیر باشد:

- شرح کسب‌وکار و دامنه محصولات.
- تعریف هر entity و رابطه آن.
- واحد پول و معنای IRR/IRT.
- timezone و شیوه تاریخ.
- معنای هر status و channel.
- شیوه محاسبه شاخص‌ها مانند AOV، Recency و Total Spend.
- تفاوت داده اظهارشده مشتری با داده استنباط‌شده از خرید.
- محدودیت‌های داده و فیلدهای اختیاری.
- سیاست حذف/ناشناس‌سازی خروجی.
- مثال queryهای تحلیلی به زبان طبیعی (بدون اتصال AI از داخل برنامه).

## 9.6. نسخه‌بندی schema

- تغییر ناسازگار در schema خروجی نیازمند افزایش major version است.
- افزودن فیلد اختیاری معمولاً minor version.
- اصلاح مستندات/برچسب بدون تغییر ساختار patch version.
- هر export باید version schema خود را در manifest اعلام کند.
- فایل dictionary و JSON Schema با همان نسخه همراه export تولید شود.

## 9.7. امنیت و عملکرد export

- export فقط داده tenant جاری را شامل شود.
- فیلترها سمت سرور validate شوند.
- خروجی از queryهای chunk شده/stream ساخته شود.
- فایل‌های حجیم به queue منتقل شوند؛ وضعیت job قابل مشاهده باشد.
- فایل در private disk ذخیره و با route احراز هویت‌شده و مجوز کنترل‌شده دانلود شود.
- مسیر فایل یا URL قابل حدس به تنهایی دسترسی ندهد.
- محدودیت حجم/تعداد export و مدت نگهداری مشخص شود.
- خروجی کامل شامل داده کاربران/تنظیمات داخلی/توکن‌ها/لاگ امنیتی نباشد.

---

# 10. فازهای توسعه و معیار پذیرش

هر فاز باید به‌تنهایی قابل اجرا و قابل بازبینی باشد. فازها به ترتیب پیشنهاد شده‌اند. ابزار فقط فازی را بسازد که کاربر صریحاً درخواست کرده است.

## فاز 00 — بررسی مخزن و زیرساخت پایه

**Branch:** `phase/00-foundation`

### محدوده

- بررسی repo و دستورالعمل‌های موجود.
- اگر خالی است: ایجاد Laravel + Vue starter setup با نسخه‌های سازگار.
- تنظیم Docker Compose برای app و MySQL 8.4.
- ایجاد `.env.example`، README فارسی/انگلیسی کوتاه برای run محلی و دستورات اصلی.
- تنظیم lint/formatter و build موجود.
- ایجاد `docs/architecture/decisions.md` برای ثبت تصمیم‌های مهم.
- تنظیم timezone، locale، charset `utf8mb4` و collation مناسب MySQL.
- ایجاد صفحه اولیه ساده و responsive، فقط در حد shell.

### پذیرش

- پروژه طبق README در محیط محلی اجرا شود.
- MySQL persistent volume داشته باشد.
- secrets در repository نباشند.
- `APP_DEBUG` در config production false باشد.
- build فرانت‌اند موفق باشد.
- migration پایه بدون خطا اجرا شود.

### Smoke checks

- containerها بالا می‌آیند.
- health/root page پاسخ می‌دهد.
- اتصال Laravel به MySQL برقرار است.
- asset build موفق است.

## فاز 01 — هویت، tenant، دسترسی و پوسته UI

**Branch:** `phase/01-tenancy-ui`

### محدوده

- پیاده‌سازی `tenants`, `users`, `tenant_user`.
- Owner setup امن؛ بدون ثبت‌نام عمومی.
- login/logout و password reset در صورت وجود mail configuration.
- middleware تعیین tenant از membership معتبر.
- نقش‌های Owner، Manager و Operator.
- Policyهای پایه.
- tenant settings: نام فروشگاه، ارز IRR/IRT، timezone، locale و calendar.
- پوسته اصلی RTL: sidebar، header، navigation، page container، responsive states.
- design tokens سفید/مشکی و component پایه Button/Input/Select/Badge/Table/Modal/Toast.
- audit log پایه برای ورود و تغییر نقش/تنظیمات.

### پذیرش

- کاربر غیرمجاز به پنل دسترسی ندارد.
- کاربر فقط tenant عضو خود را می‌بیند.
- تغییر tenant_id در request باعث دسترسی بین tenantها نمی‌شود.
- نقش Operator از endpointهای Owner-only منع شود.
- تغییر ارز tenant فقط سفارش‌های جدید را تحت تأثیر قرار دهد؛ هنوز سفارش نداریم، ولی settings snapshot design آماده باشد.
- رابط در desktop/mobile و RTL درست باشد.

### Smoke checks

- login/logout.
- ایجاد/انتخاب tenant با owner.
- مسیر مجاز/غیرمجاز برای نقش‌ها.
- تست دستی isolation دو tenant نمونه.
- بررسی بصری RTL و responsive.

## فاز 02 — CRM و پروفایل مشتری

**Branch:** `phase/02-customer-crm`

### محدوده

- `customers`, `customer_contacts`, `customer_social_accounts`, `customer_addresses`.
- `customer_consents`, `customer_preferences`, `customer_measurements`.
- `tags`, `customer_tag`, `customer_notes`, `customer_interactions`.
- فرم ایجاد/ویرایش مشتری با فیلدهای پایه کوتاه و بخش‌های تکمیلی.
- فهرست مشتریان با pagination سروری، جستجو و فیلتر.
- duplicate warning غیرمسدودکننده.
- پروفایل 360 درجه مشتری با تب‌های اطلاعات، تماس، علایق، یادداشت‌ها و تعاملات.
- normalize Persian text و phone در سرویس مشترک.
- نمایش تاریخ شمسی و ذخیره میلادی.
- audit log تغییر اطلاعات پایه و آرشیو مشتری.

### پذیرش

- مشتری بدون شماره موبایل هم در صورت داشتن نام/شناسه قابل ثبت باشد.
- مشتری بتواند چند شماره، social account و آدرس داشته باشد.
- شماره موبایل مشترک باعث خطای unique نشود.
- شماره و متن فارسی با ارقام/حروف عربی و فارسی به‌صورت قابل جستجو normalize شوند.
- رضایت تبلیغاتی به‌صورت پیش‌فرض granted نباشد.
- اطلاعات خصوصی در لیست عمومی مشتری نمایش داده نشود.
- جستجو/فیلتر فقط در tenant جاری باشد.

### Smoke checks

- ایجاد، ویرایش و archive یک مشتری.
- ثبت دو شماره برای یک مشتری.
- ثبت یک شماره مشترک برای دو مشتری و مشاهده warning.
- جستجو با ارقام فارسی و لاتین.
- اضافه/حذف تگ و ثبت note/interaction.
- بررسی عدم دسترسی tenant دیگر به ID مشتری.

## فاز 03 — محصولات، تنوع، سفارش، پرداخت و موجودی

**Branch:** `phase/03-sales-inventory`

### محدوده

- `product_categories`, `products`, `product_variants`.
- ویژگی‌های تنوع: رنگ، سایز، قواره، جنس پارچه و موارد قابل تنظیم.
- `inventory_movements` و موجودی پایه برای هر variant.
- `orders`, `order_items`, `payments`.
- فرم ثبت سفارش دستی mobile-friendly.
- کانال فروش و روش تحویل.
- snapshot محصول/variant/قیمت/آدرس در سفارش.
- وضعیت سفارش و پرداخت.
- ثبت/لغو و movementهای موجودی در transaction.
- تاریخچه سفارش در پروفایل مشتری.
- جمع مبلغ و وضعیت پرداخت فقط از منطق سمت سرور.

### پذیرش

- هر سفارش متعلق به یک مشتری و یک tenant باشد.
- هر سفارش حداقل یک قلم داشته باشد.
- هر item قیمت و مشخصات variant زمان خرید را snapshot کند.
- تغییر قیمت یا نام محصول، سفارش تاریخی را تغییر ندهد.
- مبلغ‌ها با DECIMAL و بدون float مدیریت شوند.
- هر سفارش یک currency_code ثابت داشته باشد.
- double-submit باعث سفارش/پرداخت تکراری نشود.
- موجودی منفی به‌صورت پیش‌فرض مجاز نباشد.
- cancel باعث ثبت حرکت معکوس موجودی شود، نه حذف movement قبلی.
- paymentهای سفارش مجموع وضعیت پرداخت را درست نشان دهند.
- `idempotency_key` یا راهکار معادل، از ایجاد سفارش/پرداخت/حرکت موجودی تکراری بر اثر retry یا double-submit جلوگیری کند.

### Smoke checks

- تعریف دسته، محصول و دو variant.
- ثبت موجودی اولیه.
- ثبت یک سفارش چندقلمی با پرداخت کامل.
- بررسی snapshot و total.
- ثبت cancel و بررسی برگشت موجودی.
- تلاش برای خرید بیش از موجودی و دریافت خطای قابل فهم.
- بررسی snapshot currency پس از تغییر تنظیم ارز tenant.

## فاز 04 — باشگاه، وفاداری و داشبورد

**Branch:** `phase/04-loyalty-dashboard`

### محدوده

- `loyalty_settings`, `loyalty_transactions`.
- فعال/غیرفعال کردن امتیازدهی برای tenant.
- تعریف نرخ ساده: به‌ازای هر گام مبلغی، تعداد امتیاز مشخص.
- اعطای امتیاز در زمان تکمیل سفارش.
- reversal امتیاز هنگام لغو/مرجوعی مرتبط.
- ثبت adjustment دستی فقط با reason و نقش مجاز.
- نمایش موجودی امتیاز، تاریخچه تراکنش و خلاصه وفاداری پروفایل مشتری.
- نمایش KPI پایه در داشبورد: تعداد مشتری، سفارش، فروش تکمیل‌شده، میانگین سفارش، مشتری جدید در بازه.
- گزارش مشتریان بر اساس آخرین خرید/تعداد خرید/مبلغ خرید و تگ.
- محاسبات مشتق‌شده از source data؛ cache فقط در صورت ضرورت.

### پذیرش

- ledger امتیاز append-only باشد.
- امتیاز سفارش دوبار اعطا نشود؛ عملیات idempotent باشد.
- reversal از تراکنش اصلی قابل ردیابی باشد.
- total spent فقط سفارش‌های معتبر/تکمیل‌شده را طبق تعریف مستند محاسبه کند.
- اعداد داشبورد با سفارش‌های seed/test قابل تطبیق باشند.
- هر KPI تعریف و بازه زمانی روشن داشته باشد.

### Smoke checks

- فعال کردن امتیاز و تعریف rule.
- تکمیل سفارش و مشاهده امتیاز.
- اجرای دوباره event و اطمینان از عدم اعطای duplicate.
- لغو/مرجوعی و مشاهده reversal.
- ثبت adjustment با reason.
- تطبیق KPI داشبورد با چند سفارش نمونه.

## فاز 05 — خروجی AI-ready، گزارش و سخت‌سازی MVP

**Branch:** `phase/05-exports-hardening`

### محدوده

- Export پروفایل یک مشتری به JSON.
- Export مجموعه داده به ZIP شامل JSONL + manifest + schema + data dictionary + analysis context.
- CSV bundle.
- XLSX فقط در صورت سبک و قابل اتکا بودن پیاده‌سازی؛ اگر پیچیدگی/وابستگی غیرضروری دارد به بعد موکول شود.
- فیلتر دامنه export: customers، orders، catalog، loyalty، full dataset.
- حالت anonymized پیش‌فرض و full_pii فقط برای Owner با تأیید صریح.
- export history، audit event، private storage و expiry.
- محدودیت تعداد/حجم و chunking/queue برای export بزرگ.
- مستندات schema version و نمونه خروجی.
- بهبود خطاها، empty stateها و دسترس‌پذیری.
- بررسی queryهای N+1 و indexهای پرتکرار.
- چک نهایی security/tenant boundaries و backup/restore notes.

### پذیرش

- خروجی با JSON parser معتبر باشد.
- هر رکورد به ID پایدار و روابط صحیح متصل باشد.
- dictionary تمام فیلدها و کدهای مهم را توضیح دهد.
- مبلغ و currency، timezone، timestamp و calendar صریح باشند.
- حالت anonymized PII پیش‌فرض را حذف کند.
- full_pii فقط Owner و با audit log در دسترس باشد.
- فایل export از URL عمومی/قابل حدس قابل دریافت نباشد.
- export tenant A هیچ داده‌ای از tenant B نداشته باشد.
- schema version در manifest وجود داشته باشد.

### Smoke checks

- export یک مشتری به JSON و parse آن.
- export dataset نمونه به ZIP و parse چند رکورد JSONL.
- بررسی وجود manifest/dictionary/context.
- بررسی anonymized و full_pii با کاربر مجاز/غیرمجاز.
- تلاش برای دسترسی به export tenant دیگر.
- انقضای فایل و عدم دسترسی مجدد.

---

# 11. Roadmap بعد از MVP (در این فازها پیاده‌سازی نشود)

- فیلدهای سفارشی (Custom Fields) قابل تعریف برای هر tenant و entity.
- segmentهای ذخیره‌شده بر اساس فیلترهای قابل ترکیب.
- سطوح وفاداری Bronze/Silver/Gold و rule engine قابل پیکربندی.
- بازخرید امتیاز و تبدیل به کوپن/تخفیف با سیاست مالی مشخص.
- تاریخ انقضای امتیاز و campaignهای وفاداری.
- referral program و کد معرفی.
- پیامک و پیام‌رسان با ثبت consent و opt-out.
- task/reminder و follow-up queue.
- import از Excel/CSV با preview، mapping و duplicate resolution.
- API عمومی versioned و webhook.
- مدیریت چندانبار/چند شعبه.
- PWA و تجربه موبایل پیشرفته.
- subscription plans و billing.
- super admin برای پلتفرم.
- observability، alerting و backup policy production.
- ابزار تحلیل/AI خارجی از طریق export یا API؛ نه الزاماً AI داخلی.

---

# 12. اصول کیفیت کد و توسعه

- Controller فقط orchestration و پاسخ HTTP باشد؛ قوانین دامنه در Action/Service/Domain layer.
- Form Request یا validation layer مشخص برای ورودی‌ها.
- API/ Inertia props فقط داده لازم را ارسال کنند؛ مدل کامل و فیلد خصوصی را serialize نکنند.
- Eloquent relationshipها واضح و tenant-safe باشند.
- برای عملیات چندمرحله‌ای سفارش/پرداخت/موجودی/امتیاز DB transaction استفاده شود.
- عملیات حساس idempotent باشد؛ به‌ویژه ثبت سفارش، پرداخت و اعطای امتیاز.
- منطق مبلغ در یک Money helper/value object باشد؛ محاسبات تکراری در Vue و PHP نداشته باش.
- business statusها با constant/enum مرکزی مدیریت شوند، نه string پراکنده.
- UI componentها reusable باشند؛ از abstraction بیش از حد پرهیز شود.
- از package جدید فقط با توجیه فنی استفاده شود؛ dependencyهای سنگین و رهاشده ممنوع.
- هر package حساس (auth, date, export, permission) از نظر سازگاری نسخه، نگهداری و مجوز بررسی شود.
- فایل‌های بزرگ به chunk/stream پردازش شوند.
- برای داده‌های مرتبط از eager loading استفاده شود.
- هیچ AI API key، model SDK یا اتصال AI در MVP اضافه نشود.

---

# 13. Definition of Done برای کل MVP

MVP زمانی قابل استفاده تلقی می‌شود که همه موارد زیر برقرار باشد:

- [ ] برنامه با Docker Compose قابل اجرا و README آن کافی باشد.
- [ ] MySQL 8.4 با utf8mb4 استفاده شود.
- [ ] owner بتواند وارد شود و tenant اولیه را مدیریت کند.
- [ ] جداسازی tenant در تمام Queryها/Policyها/Exportها enforce شود.
- [ ] UI فارسی، RTL، مینیمال و responsive باشد.
- [ ] مشتری با چند شماره و شناسه اجتماعی قابل ثبت باشد.
- [ ] شماره مشترک خانوادگی مجاز و duplicate warning غیرمسدودکننده باشد.
- [ ] آدرس، علاقه‌مندی، اندازه، تگ، یادداشت و interaction پشتیبانی شوند.
- [ ] محصول، دسته‌بندی، variant، ویژگی رنگ/سایز/قواره و موجودی پایه قابل ثبت باشد.
- [ ] سفارش دستی، item snapshot، پرداخت و وضعیت تحویل قابل ثبت باشد.
- [ ] currency IRR/IRT در tenant قابل تنظیم و روی سند مالی snapshot باشد.
- [ ] تاریخ در DB میلادی و در UI شمسی باشد.
- [ ] loyalty ledger append-only و قابل ردیابی باشد.
- [ ] داشبورد KPI پایه با تعریف روشن داشته باشد.
- [ ] خروجی AI-ready anonymized به‌صورت پیش‌فرض در دسترس Owner باشد.
- [ ] خروجی full_pii با کنترل دسترسی، هشدار و audit log محافظت شود.
- [ ] هیچ AI داخلی/اتصال به LLM اضافه نشده باشد.
- [ ] `.env` و secretها در repo نباشند.
- [ ] smoke checks فازها اجرا و نتایج ثبت شده باشند.
- [ ] بکاپ و روش restore مستند شده باشد.

---

# 14. Prompt آماده برای ابزار توسعه

برای هر فاز، متن زیر را به ابزار توسعه بده و شماره/نام فاز را جایگزین کن:

```text
این مخزن را مطابق سند RAVAND_PRODUCT_SPEC_FA.md توسعه بده.

فقط فاز [شماره و نام دقیق فاز] را اجرا کن؛ قابلیت فازهای بعدی را پیاده‌سازی نکن.

قبل از شروع:
1. کل سند را بخوان.
2. AGENTS.md، README و دستورالعمل‌های موجود repo را بخوان.
3. وضعیت Git و شاخه پیش‌فرض را بررسی کن.
4. اگر تغییر محلی commit نشده وجود دارد، آن را دست‌کاری نکن و گزارش بده.
5. از آخرین وضعیت شاخه پیش‌فرض، شاخه دقیق همان فاز را طبق سند بساز.
6. توکن GitHub را فقط از secret/env امن دریافت کن؛ آن را در فایل، URL، log یا commit نگذار.

هنگام توسعه:
- قراردادهای امنیتی، tenant isolation، UI/UX و مدل داده سند را رعایت کن.
- الگوهای موجود repo را حفظ کن و major upgrade بی‌دلیل انجام نده.
- migrationها را backward-conscious بنویس؛ داده موجود را حذف یا reset نکن.
- فقط تست‌های سبک و smoke checks تعریف‌شده برای همان فاز را اجرا کن؛ تست گسترده لازم نیست.
- کامیت‌های کوچک با Conventional Commits بگذار.
- هیچ‌گاه روی شاخه پیش‌فرض commit نکن و force push نزن.

در پایان:
- نتیجه smoke checks را گزارش کن.
- commit hashها و نام branch را اعلام کن.
- اگر همه checks موفق بود branch را به remote push کن؛ merge/PR فقط با درخواست من.
- موارد انجام‌نشده، فرض‌ها و ریسک‌ها را شفاف فهرست کن.
- هیچ secret یا داده واقعی مشتری را در خروجی یا commit نمایش نده.
```

---

# 15. منابع رسمی برای بررسی نسخه‌ها

ابزار توسعه در زمان اجرا باید نسخه‌های patch و سازگاری packageها را از منابع رسمی دوباره بررسی کند:

- Laravel Release Notes: https://laravel.com/docs/releases
- Laravel Starter Kits: https://laravel.com/starter-kits
- Laravel Vue Starter Kit: https://github.com/laravel/vue-starter-kit
- MySQL 8.4 Reference Manual — انتخاب نسخه: https://dev.mysql.com/doc/refman/8.4/en/which-version.html
- MySQL 8.4 Reference Manual: https://dev.mysql.com/doc/refman/8.4/en/

---

**پایان سند — Ravand Product Specification v1.0**
