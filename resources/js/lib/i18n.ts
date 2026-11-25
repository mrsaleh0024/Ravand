/**
 * Minimal typed i18n layer for the Persian UI (phase 01).
 *
 * Persian is the MVP product language (RTL-first). UI texts must stay
 * translatable and must never be hardcoded inside business logic (spec 6.2).
 * Backend validation/auth messages come from `lang/fa/*`; this dictionary
 * covers frontend-only strings.
 */

const fa = {
    'nav.dashboard': 'داشبورد',
    'nav.storeSettings': 'تنظیمات فروشگاه',
    'nav.auditLog': 'لاگ حسابرسی',
    'nav.profile': 'پروفایل',
    'nav.security': 'امنیت',
    'nav.appearance': 'ظاهر',

    'common.settings': 'تنظیمات',
    'settings.description': 'حساب کاربری خود و تنظیمات فروشگاه را مدیریت کنید.',
    'common.logout': 'خروج از حساب',
    'common.save': 'ذخیره',
    'common.saving': 'در حال ذخیره…',
    'common.cancel': 'انصراف',
    'common.loading': 'در حال بارگذاری…',
    'common.actions': 'عملیات',
    'common.notAvailable': '—',

    'auth.loginTitle': 'ورود به حساب کاربری',
    'auth.loginDescription': 'ایمیل و رمز عبور خود را وارد کنید.',
    'auth.email': 'نشانی ایمیل',
    'auth.password': 'رمز عبور',
    'auth.rememberMe': 'مرا به خاطر بسپار',
    'auth.login': 'ورود',
    'auth.forgotPassword': 'رمز عبور را فراموش کرده‌اید؟',
    'auth.resetPasswordTitle': 'بازنشانی رمز عبور',
    'auth.resetPasswordDescription': 'رمز عبور جدید خود را انتخاب کنید.',
    'auth.forgotTitle': 'فراموشی رمز عبور',
    'auth.forgotDescription':
        'ایمیل خود را وارد کنید تا لینک بازنشانی ارسال شود.',
    'auth.sendResetLink': 'ارسال لینک بازنشانی',
    'auth.newPassword': 'رمز عبور جدید',
    'auth.confirmPassword': 'تکرار رمز عبور',
    'auth.resetLink': 'بازنشانی رمز عبور',
    'auth.confirmTitle': 'تأیید رمز عبور',
    'auth.confirmDescription':
        'برای ادامه این بخش، رمز عبور خود را دوباره وارد کنید.',
    'auth.twoFactorTitle': 'چالش ورود دومرحله‌ای',
    'auth.twoFactorDescription': 'کد یا کد بازیابی را وارد کنید.',
    'auth.withPasskey': 'ورود با Passkey',

    'dashboard.title': 'داشبورد',
    'dashboard.welcome': 'خوش آمدید',
    'dashboard.store': 'فروشگاه',
    'dashboard.yourRole': 'نقش شما',
    'dashboard.currency': 'ارز پیش‌فرض',
    'dashboard.timezone': 'منطقه زمانی',
    'dashboard.placeholder':
        'آمار مشتریان، سفارش‌ها و فروش در فازهای بعدی (فاز ۰۳ و ۰۴) به این صفحه اضافه می‌شود. هیچ داده نمایشی جعلی نشان داده نمی‌شود.',

    'tenant.selectTitle': 'انتخاب فروشگاه',
    'tenant.selectDescription': 'فروشگاه مورد نظر خود را انتخاب کنید.',
    'tenant.enter': 'ورود به فروشگاه',
    'tenant.noAccess': 'به هیچ فروشگاهی دسترسی ندارید.',
    'tenant.noAccessHint':
        'برای ایجاد فروشگاه، مالک اول باید با دستور امن `ravand:create-owner` روی سرور ساخته شود.',
    'tenant.currentRole': 'نقش',

    'tenantSettings.title': 'تنظیمات فروشگاه',
    'tenantSettings.description':
        'نام، ارز، منطقه زمانی و تقویم فروشگاه را مدیریت کنید.',
    'tenantSettings.storeName': 'نام فروشگاه',
    'tenantSettings.slug': 'شناسه اینترنتی (اسلاگ)',
    'tenantSettings.slugHint':
        'اسلاگ تغییرناپذیر است و در آینده در خروجی‌ها استفاده می‌شود.',
    'tenantSettings.currency': 'ارز پیش‌فرض',
    'tenantSettings.currencyHint':
        'تغییر ارز فقط روی رکوردهای جدید اثر می‌گذارد؛ اسناد قبلی ارز خودشان را نگه می‌دارند.',
    'tenantSettings.timezone': 'منطقه زمانی',
    'tenantSettings.locale': 'زبان رابط',
    'tenantSettings.calendar': 'تقویم',
    'tenantSettings.readOnlyNotice':
        'شما اجازه ویرایش تنظیمات فروشگاه را ندارید؛ فقط می‌توانید آن‌ها را ببینید.',
    'tenantSettings.currency.IRR': 'ریال (IRR)',
    'tenantSettings.currency.IRT': 'تومان (IRT)',
    'tenantSettings.calendar.jalali': 'شمسی (جلالی)',
    'tenantSettings.calendar.gregorian': 'میلادی (گریگوری)',
    'tenantSettings.locale.fa-IR': 'فارسی — ایران',

    'audit.title': 'لاگ حسابرسی',
    'audit.description':
        'رخدادهای امنیتی و تغییرات حساس این فروشگاه (فقط خواندنی).',
    'audit.time': 'زمان',
    'audit.actor': 'کاربر',
    'audit.action': 'رخداد',
    'audit.ip': 'IP',
    'audit.empty': 'هنوز رخدادی ثبت نشده است.',

    'audit.action.auth.login': 'ورود موفق',
    'audit.action.auth.login_failed': 'ورود ناموفق',
    'audit.action.tenant.switched': 'تعویض فروشگاه',
    'audit.action.tenant.settings.updated': 'تغییر تنظیمات فروشگاه',
    'audit.action.owner.created': 'ایجاد مالک',
    'audit.action.member.added': 'افزودن عضو',

    'role.owner': 'مالک',
    'role.manager': 'مدیر',
    'role.operator': 'اپراتور',
} as const;

const dictionaries = { fa };

export type Locale = keyof typeof dictionaries;

export type MessageKey = keyof typeof fa;

/**
 * Translate a key with fallback to the key itself, so unknown audit actions
 * or new codes never render an empty string in the UI.
 */
export const t = (key: string): string =>
    (fa as Record<string, string>)[key] ?? key;
