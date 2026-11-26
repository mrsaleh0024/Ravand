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
    'nav.members': 'مدیریت اعضا',
    'nav.resetRequests': 'درخواست‌های رمز',
    'nav.tenants': 'فروشگاه‌ها',

    'common.settings': 'تنظیمات',
    'settings.description': 'حساب کاربری خود و تنظیمات فروشگاه را مدیریت کنید.',
    'common.logout': 'خروج از حساب',
    'common.save': 'ذخیره',
    'common.saving': 'در حال ذخیره…',
    'common.cancel': 'انصراف',
    'common.loading': 'در حال بارگذاری…',
    'common.actions': 'عملیات',
    'common.notAvailable': '—',
    'common.back': 'بازگشت',
    'common.confirm': 'تأیید',
    'common.status': 'وضعیت',
    'common.role': 'نقش',
    'common.name': 'نام',
    'common.phone': 'شماره موبایل',
    'common.copy': 'کپی',
    'common.copied': 'کپی شد',
    'common.live': 'زنده',
    'common.lastUpdate': 'آخرین به‌روزرسانی',
    'common.yes': 'فعال',
    'common.no': 'غیرفعال',
    'common.pending': 'در انتظار',
    'common.approved': 'تأیید شده',
    'common.rejected': 'رد شده',
    'common.suspended': 'معلق',
    'common.active': 'فعال',
    'common.revoked': 'لغو شده',

    'profile.deleteTitle': 'حذف حساب کاربری',
    'profile.deleteDescription': 'حساب کاربری و همه داده‌های وابسته به آن حذف می‌شود.',
    'profile.deleteWarning': 'هشدار',
    'profile.deleteWarningText': 'این عملیات بازگشت‌ناپذیر است؛ با دقت ادامه دهید.',
    'profile.deleteAction': 'حذف حساب کاربری',
    'profile.deleteConfirmTitle': 'از حذف حساب کاربری خود مطمئن هستید؟',
    'profile.deleteConfirmText':
        'پس از حذف حساب، همه داده‌های مرتبط به‌صورت دائمی حذف می‌شود. برای تأیید حذف دائمی حساب، رمز عبور خود را وارد کنید.',

    'auth.loginTitle': 'ورود به حساب کاربری',
    'auth.loginDescription': 'شماره موبایل و رمز عبور خود را وارد کنید.',
    'auth.phone': 'شماره موبایل',
    'auth.phonePlaceholder': '۰۹۱۲۰۰۰۰۰۰۰',
    'auth.phoneHint': 'شماره موبایل، شناسه ورود شماست.',
    'auth.password': 'رمز عبور',
    'auth.rememberMe': 'مرا به خاطر بسپار',
    'auth.login': 'ورود',
    'auth.forgotPassword': 'رمز عبور را فراموش کرده‌اید؟',
    'auth.forgotTitle': 'فراموشی رمز عبور',
    'auth.forgotDescription':
        'شماره موبایل خود را وارد کنید تا درخواست بازنشانی رمز برای مدیر ثبت شود.',
    'auth.forgotNotice':
        'پس از بررسی توسط مدیر، رمز موقت به شما اعلام می‌شود؛ سپس در ورود بعدی باید رمز جدید انتخاب کنید.',
    'auth.submitResetRequest': 'ثبت درخواست بازنشانی رمز',
    'auth.resetPasswordTitle': 'بازنشانی رمز عبور',
    'auth.resetPasswordDescription': 'رمز عبور جدید خود را انتخاب کنید.',
    'auth.newPassword': 'رمز عبور جدید',
    'auth.confirmPassword': 'تکرار رمز عبور',
    'auth.resetLink': 'بازنشانی رمز عبور',
    'auth.confirmTitle': 'تأیید رمز عبور',
    'auth.confirmDescription':
        'برای ادامه این بخش، رمز عبور خود را دوباره وارد کنید.',
    'auth.otpTitle': 'کد تایید دومرحله‌ای',
    'auth.otpDescription': 'کد ۶ رقمی ارسال‌شده در بله را وارد کنید.',
    'auth.otpSentTo': 'کد به حساب بله شما (مدیر سیستم) ارسال شد.',
    'auth.otpResend': 'ارسال مجدد کد',
    'auth.otpResendIn': 'ارسال مجدد پس از {seconds} ثانیه',
    'auth.otpVerify': 'تأیید و ورود',
    'auth.otpBackToLogin': 'بازگشت به صفحه ورود',
    'auth.forcePasswordTitle': 'تنظیم رمز عبور جدید',
    'auth.forcePasswordDescription':
        'برای امنیت حساب، پس از ورود با رمز موقت باید رمز عبور شخصی خود را تنظیم کنید.',
    'auth.forcePasswordSubmit': 'ثبت رمز عبور جدید',

    'dashboard.title': 'داشبورد',
    'dashboard.welcome': 'خوش آمدید',
    'dashboard.store': 'فروشگاه',
    'dashboard.yourRole': 'نقش شما',
    'dashboard.currency': 'ارز پیش‌فرض',
    'dashboard.timezone': 'منطقه زمانی',
    'dashboard.loginsToday': 'ورود امروز',
    'dashboard.failedToday': 'ورود ناموفق امروز',
    'dashboard.activeMembers': 'اعضای فعال',
    'dashboard.pendingRequests': 'درخواست‌های بازنشانی',
    'dashboard.loginChart': 'ورودها و ورودهای ناموفق در ۱۴ روز اخیر',
    'dashboard.eventsFeed': 'رخدادهای زنده',
    'dashboard.systemHealth': 'سلامت سیستم',
    'dashboard.accessControl': 'مدیریت دسترسی',
    'dashboard.byRole': 'اعضای فعال به تفکیک نقش',
    'dashboard.chart.logins': 'ورود موفق',
    'dashboard.chart.failed': 'ورود ناموفق',
    'dashboard.liveFeedHint': 'هر ۱۰ ثانیه به‌روزرسانی می‌شود.',
    'dashboard.system.php': 'نسخه PHP',
    'dashboard.system.laravel': 'نسخه لاراول',
    'dashboard.system.environment': 'محیط اجرا',
    'dashboard.system.debug': 'حالت اشکال‌زدایی',
    'dashboard.system.db': 'دیتابیس',
    'dashboard.system.cache': 'کش',
    'dashboard.system.queue': 'صف',
    'dashboard.system.session': 'نشست',
    'dashboard.system.baleCodeBot': 'ربات کد (۲FA)',
    'dashboard.system.baleAdminBot': 'ربات ادمین (درخواست‌ها)',
    'dashboard.system.owner2fa': 'ورود دومرحله‌ای مدیر ارشد',
    'dashboard.system.failedJobs': 'جاب‌های ناموفق',
    'dashboard.system.cacheOk': 'کش سالم',
    'dashboard.system.logs': 'آخرین لاگ‌های سیستم',
    'dashboard.system.logsEmpty': 'لاگی ثبت نشده است.',
    'dashboard.viewMembers': 'مدیریت اعضا',
    'dashboard.viewRequests': 'درخواست‌های رمز',
    'dashboard.noEvents': 'هنوز رخدادی ثبت نشده است.',

    'members.title': 'مدیریت اعضا',
    'members.description':
        'اعضای فروشگاه، نقش‌ها و وضعیت دسترسی آن‌ها را مدیریت کنید.',
    'members.addMember': 'افزودن عضو جدید',
    'members.addManager': 'تعریف مدیر جدید',
    'members.addOperator': 'تعریف اپراتور جدید',
    'members.roleHint.manager': 'فقط مدیر ارشد می‌تواند مدیر جدید تعریف کند.',
    'members.lastLogin': 'آخرین ورود',
    'members.mustChangePassword': 'رمز موقت فعال',
    'members.setTempPassword': 'تنظیم رمز موقت',
    'members.suspend': 'تعلیق',
    'members.activate': 'فعال‌سازی',
    'members.remove': 'حذف عضو',
    'members.tempPassword': 'رمز موقت (اختیاری)',
    'members.tempPasswordHint':
        'خالی بگذارید تا رمز موقت امن به‌صورت خودکار ساخته شود.',
    'members.removeConfirmTitle': 'حذف عضو فروشگاه؟',
    'members.removeConfirmText':
        'دسترسی این کاربر به فروشگاه لغو می‌شود. حساب کاربری او حفظ می‌شود.',
    'members.suspendConfirmTitle': 'تعلیق دسترسی عضو؟',
    'members.suspendConfirmText':
        'کاربر بلافاصله از پنل خارج می‌شود تا زمانی که فعال‌سازی شود.',
    'members.empty': 'هنوز عضوی ثبت نشده است.',
    'members.createTitle': 'تعریف عضو جدید',
    'members.createDescription':
        'حساب با رمز موقت ساخته می‌شود؛ کاربر در اولین ورود باید رمز شخصی خود را تنظیم کند.',

    'tempPassword.title': 'رمز موقت ساخته شد',
    'tempPassword.description':
        'این رمز فقط همین یک‌بار نمایش داده می‌شود. آن را به کاربر اعلام کنید تا در اولین ورود، رمز شخصی خود را تنظیم کند.',
    'tempPassword.user': 'کاربر',
    'tempPassword.phone': 'موبایل',
    'tempPassword.password': 'رمز موقت',

    'resetRequests.title': 'درخواست‌های بازنشانی رمز',
    'resetRequests.description':
        'درخواست‌های فراموشی رمز اعضا؛ با تأیید، رمز موقتی صادر می‌شود که باید به کاربر اعلام کنید.',
    'resetRequests.empty': 'درخواستی ثبت نشده است.',
    'resetRequests.requestedAt': 'زمان درخواست',
    'resetRequests.handledAt': 'زمان بررسی',
    'resetRequests.handledBy': 'بررسی‌کننده',
    'resetRequests.approve': 'تأیید و صدور رمز موقت',
    'resetRequests.reject': 'رد درخواست',
    'resetRequests.rejectConfirmTitle': 'رد درخواست بازنشانی؟',
    'resetRequests.rejectConfirmText':
        'کاربر می‌تواند دوباره درخواست بدهد یا از راه دیگر با مدیر تماس بگیرد.',

    'bale.title': 'ورود دومرحله‌ای با بله (مدیر ارشد)',
    'bale.description':
        'کد ۶ رقمی هر ورود به حساب بله شما (مدیر سیستم) ارسال می‌شود؛ هیچ کاربر دیگری پیامی از ربات دریافت نمی‌کند.',
    'bale.chatId': 'شناسه گفتگو (chat id) در بله',
    'bale.chatIdHint':
        'برای پیدا کردن chat_id، دستور «php artisan ravand:bale:poll» را اجرا کنید و به ربات پیام /start بدهید؛ شناسه در خروجی نمایش داده می‌شود.',
    'bale.saveChatId': 'ذخیره شناسه',
    'bale.testSend': 'ارسال پیام آزمایشی',
    'bale.status.codeBot': 'ربات کد',
    'bale.status.adminBot': 'ربات ادمین',
    'bale.status.configured': 'تنظیم شده',
    'bale.status.notConfigured': 'تنظیم نشده',
    'bale.status.testResult': 'نتیجه ارسال آزمایشی',

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
    'audit.action.auth.logout': 'خروج از حساب',
    'audit.action.auth.otp.issued': 'ارسال کد دومرحله‌ای',
    'audit.action.auth.otp.verified': 'تأیید کد دومرحله‌ای',
    'audit.action.auth.otp.invalid': 'کد دومرحله‌ای نامعتبر',
    'audit.action.auth.otp.send_failed': 'شکست در ارسال کد',
    'audit.action.auth.otp.skipped': 'گذشتن از کد دومرحله‌ای',
    'audit.action.auth.password_changed': 'ثبت رمز عبور جدید',
    'audit.action.auth.reset_requested': 'درخواست بازنشانی رمز',
    'audit.action.auth.reset_approved': 'تأیید بازنشانی رمز',
    'audit.action.auth.reset_rejected': 'رد درخواست بازنشانی',
    'audit.action.tenant.switched': 'تعویض فروشگاه',
    'audit.action.tenant.settings.updated': 'تغییر تنظیمات فروشگاه',
    'audit.action.owner.created': 'ایجاد مالک',
    'audit.action.member.added': 'افزودن عضو',
    'audit.action.member.created': 'تعریف عضو جدید',
    'audit.action.member.suspended': 'تعلیق عضو',
    'audit.action.member.activated': 'فعال‌سازی عضو',
    'audit.action.member.removed': 'حذف عضو',
    'audit.action.member.password.temp_set': 'صدور رمز موقت',
    'audit.action.settings.bale.updated': 'به‌روزرسانی تنظیمات بله',
    'audit.action.bale.test_sent': 'پیام آزمایشی بله',

    'role.owner': 'مدیر ارشد',
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

/** Translate with a simple {placeholder} replacement. */
export const tf = (key: string, values: Record<string, string | number>): string =>
    Object.entries(values).reduce(
        (text, [name, value]) => text.replaceAll(`{${name}}`, String(value)),
        t(key),
    );
