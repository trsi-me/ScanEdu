# ScanEdu

نظام ويب جامعي للتحضير والواجبات والاختبارات عبر رمز QR، مع لوحة إدارة للأقسام والترمات والمستخدمين. التوثيق السابق مؤرخ أبريل 2026 ويصف المنتج. هذه النسخة تثبت ما في `database/scanedu.sql` و`migration_v2.sql` وملفات `admin/` و`api/`.

## 1 ما هو المشروع

ثلاثة أدوار في `users.role`: `admin` و`doctor` و`student`. الدكتور يدير مقرراته وحضوره وواجباته واختباراته. الطالب يمسح QR أو يفتح الواجبات والاختبارات. المدير يدير الأقسام والترمات والمستخدمين من `admin/`.

التوثيق السابق يقول «طالب/دكتور» وثلاثة أنظمة (تحضير، واجبات، اختبارات). الكود الحالي يضيف الدور `admin` وصفحات الأقسام والزملاء وتسجيل المقرر بـ QR.

## 2 لماذا وُجد

التوثيق السابق: تقليل مناداة الأسماء وتوزيع الروابط. الكود يولّد رمزاً، والطالب يمسحه بعد دخول، والخادم يتحقق من الدور والتسجيل في المقرر والوقت وعدم التكرار.

## 3 المستخدمون

بذرة `scanedu.sql` (التعليق داخل الملف):

| البريد | الدور |
| --- | --- |
| `admin@scanedu.local` | admin |
| `doctor@scanedu.local` و`doctor2@scanedu.local` | doctor |
| `student@scanedu.local` و`student2@scanedu.local` | student |

التعليق يقول إن كلمة المرور واحدة لكل هذه الحسابات. فحص محلي بـ `password_verify` على الهاش المكرر في الملف طابق الكلمة المكتوبة في ذلك التعليق (`12345678`). التوثيق السابق يذكر الكلمة نفسها.

`register.php` يسمح بدور دكتور أو طالب فقط، ولا ينشئ admin.

## 4 القدرات

- دخول وتسجيل وخروج.
- تعليق الحساب: `status = suspended` يرفض الدخول برسالة «حسابك معلّق».
- مقررات مرتبطة بطبيب وقسم أكاديمي وترم (`term_id`) وأسبوع محاضرة (`week_number` من 1 إلى 15 حسب تعليق SQL).
- تسجيل طلاب بالبريد أو بمسح QR غرضه `course_enroll`، ونسخ تسجيلات، وتسجيل طلاب القسم النشطين عبر `enrollDepartmentStudents`.
- حضور: توليد QR، مسح، جدول يتحدث كل 10 ثوانٍ.
- واجبات مع تسليم ونص ومرفق وموعد.
- اختبارات MCQ وصح/خطأ ومقالي. الدرجة الآلية للموضوعي. المقالي لا يُجمع في `submit_exam.php` كما وصف التوثيق السابق.
- زملاء القسم في `doctor/colleagues.php`.
- إدارة أقسام وترمات ومستخدمين (إضافة، تعديل، إيقاف، حذف لغير admin).
- رسوم Chart.js في لوحة الدكتور.

## 5 كيف يعمل

`config/database.php` يفتح mysqli إلى `scanedu_db` بترميز `utf8mb4`. `includes/auth.php` يدير الجلسة وCSRF و`generateQRToken` (JSON ثم Base64 مع `salt` من `random_bytes`). `includes/functions.php` ترسل JSON وترفع الملفات وتنظف النص.

الرمز ليس توقيعاً بمفتاح سري. التوثيق السابق يصرح بذلك. التحقق يتم بمطابقة القيمة المخزنة في الجدول (`hash_equals` للتحضير) مع التسجيل في `course_enrollments` والأوقات.

صلاحية تحضير: التوثيق السابق يقول نحو 90 دقيقة من لحظة التوليد عبر `qr_expires_at`.

## 6 أمثلة من الكود والبيانات

تجزئة الحساب:

```php
password_hash($pass, PASSWORD_BCRYPT)
```

الدخول `password_verify` داخل `verifyPassword`.

حمولة QR كما يصف التوثيق السابق للطالب: JSON خارجي مثل `{"type":"attendance","token":"..."}` حيث `token` هو Base64 الداخلي.

جداول SQL الحالية تزيد على قائمة التوثيق السابق: `departments` و`terms`، وأعمدة `users.status` و`users.department_id`، و`courses.term_id`، و`lectures.week_number` مع قيد فريد `(course_id, week_number)`.

`migration_v2.sql` يضيف الأقسام والترمات ويوسّع `role` ليشمل `admin` ويضيف `status`. `scanedu.sql` ينشئ الشكل الكامل من الصفر. التوثيق السابق يقول إن `scanedu.sql` المصدر الوحيد. الملفان موجودان معاً.

## 7 رحلة المستخدم

دكتور: دخول، لوحة، مقرر، طلاب، محاضرة، توليد QR، واجب، اختبار.

طالب: دخول، التأكد من التسجيل في المقرر، `student/scan.php`، تسليم أو اختبار.

مدير: `admin/dashboard.php` ثم أقسام أو ترمات أو مستخدمون.

`index.php` يوجّه حسب `dashboardPathForRole`.

## 8 الوحدات

| المسار | الوظيفة |
| --- | --- |
| `login.php`, `register.php`, `index.php` | بوابة |
| `doctor/` | لوحة، حضور، واجبات، اختبارات، مقررات، زملاء |
| `student/` | لوحة، مسح، واجبات، اختبارات |
| `admin/` | لوحة، مستخدمون، أقسام، ترمات |
| `api/login.php`, `register.php`, `logout.php` | حساب |
| `api/attendance/` | توليد ومسح وقائمة |
| `api/assignments/` | إنشاء وتسليم وجلب |
| `api/exams/` | إنشاء وجلب وتسليم ونتائج |
| `api/courses/` | `generate_enroll_qr.php`, `enroll_scan.php` |
| `includes/` | auth, functions, header |
| `database/scanedu.sql`, `migration_v2.sql` | مخطط |
| `assets/` | CSS وJS وخطوط IBM Plex Sans Arabic |

## 9 الكيانات

| الجدول | أعمدة تميّزه |
| --- | --- |
| departments | اسم فريد، وصف، `is_active` |
| terms | اسم فريد، `is_current`, تواريخ، `is_active` |
| users | دور، حالة، رقم طالب فريد، قسم نصي و`department_id` |
| courses | طبيب، رمز، فصل، `term_id` |
| course_enrollments | مقرر وطالب |
| lectures | أسبوع، عنوان، تاريخ، `qr_token` فريد، انتهاء |
| attendance | فريد `(lecture_id, student_id)`، `ip_address` |
| assignments | توكن، موعد، `max_score` |
| assignment_submissions | إجابة، ملف، درجة، فريد لكل طالب |
| exams | بداية ونهاية ومدة ودرجة كلية وتوكن |
| exam_questions | نوع، `options` JSON، إجابة، درجة |
| exam_submissions | `answers` JSON، `total_score`، فريد لكل طالب |

## 10 الصلاحيات

`requireRole` يوجّه المخالف إلى لوحة دوره. API تتحقق من `$_SESSION['role']`.

| الدور | الصفحات |
| --- | --- |
| admin | `admin/*` |
| doctor | `doctor/*` |
| student | `student/*` والمسح والتسليم |

التسجيل العام لا يمنح admin. المدير في `admin/users.php` لا يُحذف ولا يُعلَّق عبر الشروط `role != 'admin'`.

## 11 الأتمتة

تحديث جدول الحضور كل 10 ثوانٍ في `assets/js/attendance.js`. مؤقت الاختبار في الواجهة (`startExamCountdown`) يحوّل اللون في آخر 5 دقائق حسب التوثيق السابق، والخادم يرفض التسليم بعد `end_time`.

غير موجود: cron.

## 12 التكامل بين الوحدات

المقرر يربط الحضور والواجب والاختبار. التسجيل في `course_enrollments` شرط المسح والتسليم. القسم يربط الأطباء في صفحة الزملاء ويمكنه تسجيل طلاب القسم دفعة واحدة. الترم يرتبط بالمقرر. QR يولَّد في PHP ويُرسم في المتصفح بمكتبة qrcode.js.

## 13 المصطلحات

| المصطلح | هنا |
| --- | --- |
| QR | نص JSON يقرأه html5-qrcode |
| Base64 | ترميز حمولة التوكن، وليس تشفيراً بمفتاح |
| CSRF | رمز جلسة في النماذج و`meta` |
| الأسبوع | `week_number` الفريد داخل المقرر |

التوثيق السابق يذكر أن JWT غير مستخدم. هذا ما زال صحيحاً: الجلسة وتوكن QR.

## 14 الأسئلة الشائعة

من التوثيق السابق وما زال يطابق الكود:

- الكاميرا تحتاج `localhost` أو HTTPS (`qr-scanner.js`).
- فشل الاتصال يظهر من `config/database.php` كـ JSON 500.
- الطالب غير المسجّل في المقرر يُرفض.
- إعادة توليد QR تحدّث التوكن والانتهاء.
- انحراف ساعة الخادم يؤثر على الاختبار.

إضافة من الكود الحالي:

- الحساب المعلّق لا يدخل.
- المدير مسار مستقل عن الدكتور.
- استيراد `scanedu.sql` على قاعدة قديمة: الملف يحذف صفوف البذرة بـ `DELETE` بعد إطفاء فحص المفاتيح. قسم الترقية في آخر الملف مخصص لقواعد أقدم (مثل عمود `week_number` الناقص).

## 15 مخطط المعمارية

```
متصفح
  login/register  doctor/*  student/*  admin/*
       |             |          |         |
       v             v          v         v
     api/*.php  (JSON + CSRF + دور)
       |
       v
  includes/auth.php + functions.php
       |
       v
  mysqli scanedu_db
```

## 16 التقنيات المستخدمة

HTML، CSS خاص، JavaScript، PHP، MySQL. مكتبات من CDN ظاهرة في الصفحات:

| المكتبة | أين |
| --- | --- |
| Font Awesome 6.5.1 | `includes/header.php` عبر cdnjs |
| qrcode 1.4.4 | صفحات الدكتور التي تعرض رمزاً |
| html5-qrcode 2.3.8 | `student/scan.php` عبر unpkg |
| Chart.js 4.4.1 | `doctor/dashboard.php` |

التوثيق السابق يقول لا Laravel ولا Bootstrap. هذا صحيح. التوثيق السابق لا يذكر Font Awesome رغم وجود الرابط في الرأس.

خطوط IBM Plex Sans Arabic محلية تحت `assets/fonts/IBMPlexSansArabic/`.

## 17 شجرة الملفات

```
ScanEdu/
├── index.php login.php register.php
├── config/database.php
├── includes/auth.php functions.php header.php
├── admin/dashboard.php users.php departments.php terms.php
├── doctor/dashboard.php attendance.php assignments.php exams.php courses.php colleagues.php
├── student/dashboard.php scan.php assignments.php exams.php
├── api/...
├── database/scanedu.sql
├── database/migration_v2.sql
├── assets/css assets/js assets/fonts
└── README.md
```

`uploads/assignments/` مذكور في التوثيق السابق كمسار رفع. المجلد غير ظاهر ضمن قائمة الملفات الحالية (يُنشأ عند الرفع إن كتب الكود المسار).

## 18 الواجهة

`dir="rtl"` ونصوص عربية. ألوان التوثيق السابق: `#1565C0` و`#2E7D32` و`#1B2A4A`. الرأس يتوقع `$pageTitle` و`$activeNav` و`$role`.

شعاران متوقعان: `assets/images/Logo.png` و`Logo2.png`. التوثيق السابق يقول إن غيابهما يكسر الصورة فقط. الملفان غير موجودين في الشجرة الحالية.

أيقونة تبويب `rel="icon"` غير موجودة في الملفات الحالية.

## 19 الخادم

صفحات HTML للوحات، وJSON للـ API عبر `sendJSON`. الأخطاء للمستخدم عامة في `api/login.php` (`خطأ في الخادم`) بينما فشل الاتصال في `config/database.php` يعيد JSON ثابتاً «فشل الاتصال بقاعدة البيانات».

`scaneduBaseUrl` يحسب المسار من `DOCUMENT_ROOT` حتى تعمل الروابط تحت مجلد فرعي أو جذر.

## 20 مسار الطلب

دخول: POST إلى `api/login.php` مع البريد وكلمة السر و`csrf_token`. النجاح يعيد `data.redirect`.

حضور: الدكتور POST `generate_qr.php`. الطالب POST `scan_attendance.php` بالتوكن الداخلي. الدكتور GET `get_attendance.php?lecture_id=`.

واجب: إنشاء ثم تسليم multipart عند وجود ملف.

اختبار: إنشاء داخل معاملة (`begin_transaction` في `create_exam.php`). الجلب للطالب مع `take=1` يخفي `correct_answer`. التسليم يرفض بعد النهاية والتسليم المكرر.

تسجيل مقرر بالمسح: `enroll_scan.php` يتحقق من `purpose = course_enroll`.

## 21 قاعدة البيانات

الاسم `scanedu_db` والترميز `utf8mb4_unicode_ci`. البذرة تشمل مقررات ومحاضرات وحضوراً وواجبات واختبارات. التوثيق السابق يقول إن رموز QR في البذرة نصوص ثابتة وإن التوليد من الواجهة مطلوب للاستخدام الفعلي. هذا التعليق ما زال في رأس قسم البذرة.

## 22 نقاط الدخول

الاستجابة الشائعة: `{ success, message, data }`.

رموز يذكرها التوثيق السابق وما زالت في الملفات المقروءة: 200 و400 و403 و404 و409 و410 و405 و500. الدخول الفاشل في `api/login.php` يستخدم 401 أيضاً.

| المجموعة | الملفات |
| --- | --- |
| حساب | `api/login.php` POST، `api/register.php` POST، `api/logout.php` |
| حضور | `generate_qr.php`, `scan_attendance.php`, `get_attendance.php` |
| واجبات | `create_assignment.php`, `submit_assignment.php`, `get_assignments.php` |
| اختبارات | `create_exam.php`, `get_exam.php`, `submit_exam.php`, `get_results.php` |
| مقررات | `generate_enroll_qr.php`, `enroll_scan.php` |

`csrf_token` مطلوب في POST الحساسة كما في التوثيق السابق.

## 23 المصادقة

جلسة: `user_id`, `role`, `user_name`. الكوكي `httponly` و`samesite=Lax` و`secure` عندما `HTTPS` يعمل. `session.use_strict_mode`. `session_regenerate_id(true)` بعد دخول ناجح.

CSRF: 32 بايت hex في الجلسة و`hash_equals`.

## 24 الأمان الموجود فعلياً

- bcrypt عبر `PASSWORD_BCRYPT`.
- CSRF.
- فحص الدور في الصفحات وAPI.
- تهريب عرض `htmlspecialchars` في القوالب، و`sanitize` / `strip_tags` في مسارات يذكرها التوثيق السابق.
- قيود فريدة تمنع تكرار الحضور والتسليم.
- رفع: التوثيق السابق يقول فحص MIME بـ `finfo` واسماً عشوائياً. الدالة `uploadFile` موجودة في `includes/functions.php`.
- حالة suspended.

استثناء ظاهر: `admin/users.php` يدمج دور الفلتر في SQL كنص بعد حصر القيمة في `doctor` أو `student`:

```php
$sql .= ' AND u.role = \'' . ($filterRole === 'doctor' ? 'doctor' : 'student') . '\'';
```

القيمة ليست إدخال المستخدم الحر، والبناء ليس `bind_param` لهذا الجزء. التوثيق السابق يقول إن كل SQL بمعاملات. هذا السطر يخالف التعميم مع بقاء القيمة ضمن خيارين ثابتين.

توكن QR غير موقّع. حدود التوثيق السابق ما زالت صحيحة.

لا أيقونة موقع.

## 25 الإعدادات

`config/database.php`:

| الثابت | القيمة |
| --- | --- |
| `DB_HOST` | `localhost` |
| `DB_USER` | `root` |
| `DB_PASS` | فارغة |
| `DB_NAME` | `scanedu_db` |

دالة `scanedu_asset_v` في الملف نفسه لإصدار الأصول.

## 26 التكاملات الخارجية

CDN: Font Awesome وqrcode وhtml5-qrcode وChart.js. لا بريد ولا دفع. الكاميرا واجهة المتصفح.

## 27 المهام المجدولة

غير موجود في الملفات الحالية. التحديث الدوري من `setInterval` في المتصفح.

## 28 الملفات والمرفقات

مرفقات الواجبات تحت مسار نسبي يخزَّن في `assignment_submissions.file_path`. التوثيق السابق يحدد أنواعاً (PDF وصور وWord) داخل كود التسليم.

شعارات `Logo.png` و`Logo2.png` غير موجودة حالياً. الخطوط TTF موجودة.

## 29 السجلات

غير موجود مجلد logs. أخطاء API تُعاد JSON. لا تتبع تدقيق منفصل.

## 30 التثبيت

كما التوثيق السابق مع تصحيح المسار:

1. Apache وMySQL (XAMPP). PHP 8 مناسب لأن `auth.php` يستخدم `str_starts_with`.
2. انسخ المشروع أو وجّه الجذر إليه.
3. استورد `database/scanedu.sql` لقاعدة جديدة. لقاعدة قديمة بلا أقسام استخدم `migration_v2.sql` ثم راجع قسم الترقية أسفل `scanedu.sql` إن نقص `week_number`.
4. طابق `config/database.php`.
5. افتح `login.php`. الحسابات أعلاه. الكلمة في تعليق SQL وفي التوثيق السابق: `12345678`.
6. ضع الشعارين إن أردت الصورة. الكاميرا على localhost أو HTTPS.

## 31 دليل التطوير

أضف endpoint تحت `api/` واستدعِ `sendJSON`. صفحات الدور تبدأ بـ `requireRole`. روابط fetch من `doctor/` و`student/` نسبية `../api/`. غيّر هيكل المجلدات يغيّر هذه المسارات، كما نبّه التوثيق السابق.

## 32 النشر

غير موثق كمنصة محددة. التوثيق السابق يطلب HTTPS للكاميرا، ونسخ `config/database.php`، ومساراً يبقى معه `../api` صحيحاً. عطّل عرض الأخطاء على الإنتاج. لا ملف Docker.

## 33 النسخ الاحتياطي

التوثيق السابق يطلب نسخة من `scanedu_db` قبل التجارب التي تحذف البذرة، ونسخ مجلد الرفع. `scanedu.sql` نفسه ينفّذ `DELETE` على الجداول عند إعادة الاستيراد بعد قسم البذرة.

## 34 استكشاف الأخطاء

| العرض | المصدر |
| --- | --- |
| فشل الاتصال | MySQL أو اسم القاعدة |
| كاميرا | ليس localhost ولا https |
| رمز قديم | أُعيد التوليد |
| معلّق | `users.status` |
| صور مكسورة | الشعارات غير موجودة |
| خط مكسور | مسار TTF من `assets/css` |

## 35 الاعتماديات

PHP 8 (الدوال المستخدمة)، mysqli، MySQL، متصفح، وإنترنت لأول تحميل CDN. خط محلي للعمل بلا إنترنت بعد ذلك، بينما QR وChart وFont Awesome تبقى على CDN ما لم تُستبدل.

## 36 القيود

- المقالي بلا درجة آلية.
- QR غير موقّع.
- التسجيل العام بلا دور admin.
- الشعارات غير مرفقة.
- فلتر المستخدمين يبني جزءاً من SQL نصياً.
- توثيق أبريل 2026 لا يعدد لوحة الإدارة.

## 37 الحالة الحالية

نظام ثلاث أدوار مع بذرة قابلة للدخول بالكلمة الموثقة، ومسار QR للحضور والواجبات والاختبارات وتسجيل المقرر، ولوحة مدير للأقسام والترمات والمستخدمين.

## 38 قرارات معمارية

- جلسات لا JWT.
- توكن QR = JSON + Base64 + ملح، والثقة من مطابقة القاعدة.
- bcrypt.
- CSRF.
- mysqli بمعاملات في API.
- فصل مجلدات doctor وstudent وadmin.
- أسبوع واحد لكل محاضرة داخل المقرر.

## 39 سجل التغييرات

لا ملف CHANGELOG. التوثيق السابق يختم بعبارة آخر تحديث أبريل 2026. `migration_v2.sql` يوثق ترقية أقسام وترمات وأدمن وحالة مستخدم فوق مخطط أقدم.

## System Overview

منصة حضور وواجبات واختبارات مربوطة بـ QR، مع إدارة أقسام وترمات. القاعدة `scanedu_db`. الأدوار admin وdoctor وstudent. الحسابات التجريبية كلمة واحدة موثقة `12345678` وهاش bcrypt مطابق لها.

## Quick Reference

| البند | القيمة |
| --- | --- |
| القاعدة | `scanedu_db` |
| الدخول | `login.php` |
| أدمن | `admin@scanedu.local` |
| دكتور | `doctor@scanedu.local` |
| طالب | `student@scanedu.local` |
| الكلمة | `12345678` |
| مخطط جديد | `database/scanedu.sql` |
| ترقية قديمة | `database/migration_v2.sql` |

## Quick Start

1. شغّل MySQL واستورد `database/scanedu.sql`.
2. أبقِ إعداد XAMPP الافتراضي في `config/database.php`.
3. افتح `login.php`.
4. ادخل بحساب الدكتور، أنشئ أو استخدم مقرراً من البذرة، ثم ولّد QR من صفحة التحضير.
5. ادخل كطالب مسجّل في المقرر وافتح `student/scan.php` على localhost.

## For Non-Technical Users

الدكتور يعرض رمزاً على الشاشة. الطالب يمسحه بهاتفه بعد تسجيل الدخول. الحضور يُسجل مرة واحدة. الواجب يُسلّم مرة واحدة قبل الموعد. الاختبار يفتح داخل وقته. المدير يضيف الأقسام والمستخدمين من لوحته.

## For Developers

اقرأ `includes/auth.php` ثم `api/attendance/scan_attendance.php`. عند توسيع الأدوار حدّث `dashboardPathForRole` و`isLoggedIn`. لا تعتمد توثيق أبريل 2026 وحده: لوحة `admin/` و`colleagues.php` و`api/courses/*` جزء من الشجرة الحالية. Font Awesome وChart وQR من CDN.
