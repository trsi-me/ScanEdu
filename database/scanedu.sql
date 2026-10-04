-- قاعدة بيانات مشروع ScanEdu — ملف SQL واحد (إنشاء + بذور + أوامر اختيارية في آخر الملف)
-- تشغيل الملف من phpMyAdmin أو سطر أوامر MySQL لإنشاء الجداول

CREATE DATABASE IF NOT EXISTS scanedu_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE scanedu_db;

CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE terms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    start_date DATE NULL,
    end_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'doctor', 'student') NOT NULL,
    status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    student_id VARCHAR(20) NULL,
    department VARCHAR(100) NULL,
    department_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_id (student_id),
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(20) NOT NULL,
    doctor_id INT NOT NULL,
    semester VARCHAR(20) NOT NULL,
    term_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES users(id),
    FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE SET NULL
);

CREATE TABLE course_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    student_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id),
    FOREIGN KEY (student_id) REFERENCES users(id)
);

CREATE TABLE lectures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    week_number TINYINT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    lecture_date DATE NOT NULL,
    qr_token VARCHAR(255) UNIQUE NOT NULL,
    qr_expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id),
    UNIQUE KEY uq_course_week (course_id, week_number)
);

CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lecture_id INT NOT NULL,
    student_id INT NOT NULL,
    scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    UNIQUE KEY unique_attendance (lecture_id, student_id),
    FOREIGN KEY (lecture_id) REFERENCES lectures(id),
    FOREIGN KEY (student_id) REFERENCES users(id)
);

CREATE TABLE assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    qr_token VARCHAR(255) UNIQUE NOT NULL,
    due_date DATETIME NOT NULL,
    max_score INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id)
);

CREATE TABLE assignment_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    answer TEXT NOT NULL,
    file_path VARCHAR(255) NULL,
    score INT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_submission (assignment_id, student_id),
    FOREIGN KEY (assignment_id) REFERENCES assignments(id),
    FOREIGN KEY (student_id) REFERENCES users(id)
);

CREATE TABLE exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    qr_token VARCHAR(255) UNIQUE NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    duration_minutes INT NOT NULL,
    total_score INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id)
);

CREATE TABLE exam_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('mcq', 'true_false', 'essay') NOT NULL,
    options JSON NULL,
    correct_answer VARCHAR(255) NULL,
    score INT DEFAULT 10,
    FOREIGN KEY (exam_id) REFERENCES exams(id)
);

CREATE TABLE exam_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    answers JSON NOT NULL,
    total_score INT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_exam_submission (exam_id, student_id),
    FOREIGN KEY (exam_id) REFERENCES exams(id),
    FOREIGN KEY (student_id) REFERENCES users(id)
);

/* ------------------------------------------------------------------ */
/* بيانات افتراضية شاملة للتجربة — احذف هذا القسم في الإنتاج إن رغبت    */
/* كلمة المرور لجميع الحسابات أدناه: 12345678                          */
/* أدمن: admin@scanedu.local                                          */
/* دكتور: doctor@scanedu.local  |  doctor2@scanedu.local              */
/* طالبان: student@scanedu.local  |  student2@scanedu.local           */
/* التجزئة: bcrypt (PHP password_hash) — نفس الهاش لكل الحسابات         */
/* رموز QR هنا نصوص ثابتة؛ أعد «توليد QR» من الواجهة للاستخدام الفعلي  */
/* ------------------------------------------------------------------ */
/* إعادة البذرة على قاعدة موجودة: يفرّغ الجداول حتى لا يحدث #1062 Duplicate.
   لا تنفّذ هذا القسم إن كانت القاعدة تحتوي بيانات إنتاج تريد الإبقاء عليها.
   يُستخدم DELETE بدل TRUNCATE لتفادي #1701 (قيود المفاتيح الأجنبية في MySQL/MariaDB). */
SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM exam_submissions;
DELETE FROM exam_questions;
DELETE FROM exams;
DELETE FROM assignment_submissions;
DELETE FROM assignments;
DELETE FROM attendance;
DELETE FROM lectures;
DELETE FROM course_enrollments;
DELETE FROM courses;
DELETE FROM users;
DELETE FROM terms;
DELETE FROM departments;
SET FOREIGN_KEY_CHECKS = 1;
ALTER TABLE exam_submissions AUTO_INCREMENT = 1;
ALTER TABLE exam_questions AUTO_INCREMENT = 1;
ALTER TABLE exams AUTO_INCREMENT = 1;
ALTER TABLE assignment_submissions AUTO_INCREMENT = 1;
ALTER TABLE assignments AUTO_INCREMENT = 1;
ALTER TABLE attendance AUTO_INCREMENT = 1;
ALTER TABLE lectures AUTO_INCREMENT = 1;
ALTER TABLE course_enrollments AUTO_INCREMENT = 1;
ALTER TABLE courses AUTO_INCREMENT = 1;
ALTER TABLE users AUTO_INCREMENT = 1;
ALTER TABLE terms AUTO_INCREMENT = 1;
ALTER TABLE departments AUTO_INCREMENT = 1;

INSERT INTO departments (id, name, description) VALUES
(1, 'قسم علوم الحاسب والمعلومات', 'برامج علوم الحاسب وتقنية المعلومات');

INSERT INTO terms (id, name, is_current, start_date, end_date) VALUES
(1, 'ربيع 2026', 1, '2026-01-05', '2026-05-15'),
(2, 'خريف 2026', 0, '2026-09-01', '2026-12-20');

INSERT INTO users (id, name, email, password, role, status, student_id, department, department_id) VALUES
(1, 'مدير النظام', 'admin@scanedu.local', '$2y$10$U2Coq.wDegXSJOwM1PP6keswgbcgIrVSUhmLwxCITzhkFBPb5pKSa', 'admin', 'active', NULL, NULL, NULL),
(2, 'د. أحمد محمد العتيبي', 'doctor@scanedu.local', '$2y$10$U2Coq.wDegXSJOwM1PP6keswgbcgIrVSUhmLwxCITzhkFBPb5pKSa', 'doctor', 'active', NULL, 'قسم علوم الحاسب والمعلومات', 1),
(3, 'د. سارة خالد الشمري', 'doctor2@scanedu.local', '$2y$10$U2Coq.wDegXSJOwM1PP6keswgbcgIrVSUhmLwxCITzhkFBPb5pKSa', 'doctor', 'active', NULL, 'قسم علوم الحاسب والمعلومات', 1),
(4, 'فاطمة عبدالله السالم', 'student@scanedu.local', '$2y$10$U2Coq.wDegXSJOwM1PP6keswgbcgIrVSUhmLwxCITzhkFBPb5pKSa', 'student', 'active', '441234567', 'قسم علوم الحاسب والمعلومات', 1),
(5, 'خالد سعد القحطاني', 'student2@scanedu.local', '$2y$10$U2Coq.wDegXSJOwM1PP6keswgbcgIrVSUhmLwxCITzhkFBPb5pKSa', 'student', 'active', '441234568', 'قسم علوم الحاسب والمعلومات', 1);

INSERT INTO courses (id, name, code, doctor_id, semester, term_id) VALUES
(1, 'مقدمة في أنظمة المعلومات', 'IS101', 2, 'ربيع 2026', 1),
(2, 'البرمجة بلغة Java', 'CS201', 2, 'ربيع 2026', 1),
(3, 'مقدمة في شبكات الحاسب', 'NET301', 2, 'ربيع 2026', 1);

INSERT INTO course_enrollments (course_id, student_id) VALUES
(1, 4), (2, 4), (3, 4),
(1, 5), (2, 5);

INSERT INTO lectures (course_id, week_number, title, lecture_date, qr_token, qr_expires_at)
SELECT c.id, w.n,
    CONCAT('الأسبوع ', w.n),
    DATE_ADD('2026-01-05', INTERVAL (w.n - 1) WEEK),
    CONCAT('seed-lec-c', c.id, '-w', w.n),
    DATE_ADD(NOW(), INTERVAL 2 DAY)
FROM courses c
CROSS JOIN (
    SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
    UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10
    UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15
) w
WHERE c.id IN (1, 2, 3);

INSERT INTO attendance (lecture_id, student_id, scanned_at, ip_address)
SELECT l.id, 4, '2026-01-08 09:05:00', '127.0.0.1' FROM lectures l WHERE l.course_id = 1 AND l.week_number = 1 LIMIT 1;
INSERT INTO attendance (lecture_id, student_id, scanned_at, ip_address)
SELECT l.id, 4, '2026-01-15 09:02:00', '127.0.0.1' FROM lectures l WHERE l.course_id = 1 AND l.week_number = 2 LIMIT 1;
INSERT INTO attendance (lecture_id, student_id, scanned_at, ip_address)
SELECT l.id, 4, '2026-01-22 09:08:00', '127.0.0.1' FROM lectures l WHERE l.course_id = 1 AND l.week_number = 3 LIMIT 1;
INSERT INTO attendance (lecture_id, student_id, scanned_at, ip_address)
SELECT l.id, 4, '2026-01-10 10:01:00', '127.0.0.1' FROM lectures l WHERE l.course_id = 2 AND l.week_number = 1 LIMIT 1;
INSERT INTO attendance (lecture_id, student_id, scanned_at, ip_address)
SELECT l.id, 5, '2026-01-08 09:10:00', '127.0.0.1' FROM lectures l WHERE l.course_id = 1 AND l.week_number = 1 LIMIT 1;
INSERT INTO attendance (lecture_id, student_id, scanned_at, ip_address)
SELECT l.id, 5, '2026-01-10 10:05:00', '127.0.0.1' FROM lectures l WHERE l.course_id = 2 AND l.week_number = 1 LIMIT 1;

INSERT INTO assignments (id, course_id, title, description, qr_token, due_date, max_score) VALUES
(1, 1, 'واجب 1 — ملخص دورة حياة النظم', 'اكتب نصف صفحة تلخص مراحل دورة حياة نظم المعلومات مع مثال لكل مرحلة.', 'seed-scanedu-asg-001-is101', DATE_ADD(NOW(), INTERVAL 10 DAY), 10),
(2, 1, 'واجب 2 — دراسة حالة (متأخر)', 'حلل نظاماً في الجامعة (التسجيل أو المكتبة) واذكر ثلاثة متطلبات وظيفية.', 'seed-scanedu-asg-002-is101', DATE_SUB(NOW(), INTERVAL 5 DAY), 15),
(3, 2, 'واجب 1 — برنامج Hello OOP', 'صف باختصار مفهوم التغليف والوراثة مع رسم بسيط نصي.', 'seed-scanedu-asg-003-cs201', DATE_ADD(NOW(), INTERVAL 7 DAY), 10),
(4, 2, 'واجب 2 — تمارين Constructors', 'اذكر الفرق بين constructor افتراضي ومعاملات، مع مثال.', 'seed-scanedu-asg-004-cs201', DATE_ADD(NOW(), INTERVAL 14 DAY), 10),
(5, 3, 'واجب 1 — طبقات OSI', 'رتّب طبقات OSI من 1 إلى 7 مع وظيفة سطر لكل طبقة.', 'seed-scanedu-asg-005-net301', DATE_ADD(NOW(), INTERVAL 12 DAY), 10);

INSERT INTO assignment_submissions (assignment_id, student_id, answer, file_path, score, submitted_at) VALUES
(1, 4, 'تتضمن دورة الحياة التخطيط، التحليل، التصميم، التنفيذ، الاختبار، الصيانة. مثال التخطيط: جمع احتياجات أصحاب المصلحة؛ التحليل: نمذجة حالات الاستخدام...', NULL, 9, '2026-01-16 14:30:00'),
(3, 4, 'التغليف يخفي التفاصيل الداخلية. الوراثة تنقل خصائص من فئة أب إلى فئة ابن.', NULL, NULL, '2026-01-18 11:00:00'),
(1, 5, 'المراحل: التخطيط، التحليل، التصميم، البرمجة، الاختبار، التشغيل والصيانة.', NULL, 8, '2026-01-17 09:00:00'),
(3, 5, 'التغليف: إخفاء البيانات. الوراثة: إعادة استخدام الكود.', NULL, NULL, '2026-01-19 16:20:00');

INSERT INTO exams (id, course_id, title, qr_token, start_time, end_time, duration_minutes, total_score) VALUES
(1, 1, 'اختبار منتصف الفصل — أنظمة المعلومات', 'seed-scanedu-exam-001-is101-mid', DATE_ADD(NOW(), INTERVAL 1 DAY), TIMESTAMPADD(MINUTE, 60, DATE_ADD(NOW(), INTERVAL 1 DAY)), 60, 30),
(2, 1, 'اختبار تمهيدي — منتهي (للعرض فقط)', 'seed-scanedu-exam-002-is101-done', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), 30, 20),
(3, 2, 'Quiz سريع — مفاهيم Java', 'seed-scanedu-exam-003-cs201-quiz', DATE_ADD(NOW(), INTERVAL 5 DAY), TIMESTAMPADD(MINUTE, 25, DATE_ADD(NOW(), INTERVAL 5 DAY)), 25, 15);

INSERT INTO exam_questions (id, exam_id, question_text, question_type, options, correct_answer, score) VALUES
(1, 1, 'ما الهدف الرئيسي لنظام معلومات إداري متكامل؟', 'mcq', '["تخزين البريد فقط", "دعم اتخاذ القرار وتنسيق العمليات", "استبدال الموظفين بالروبوتات"]', 'دعم اتخاذ القرار وتنسيق العمليات', 10),
(2, 1, 'تحليل المتطلبات يسبق تصميم قاعدة البيانات في المشاريع المنظمة.', 'true_false', '["صح", "خطأ"]', 'صح', 5),
(3, 1, 'اذكر فرقاً واحداً بين المتطلب الوظيفي وغير الوظيفي.', 'essay', NULL, NULL, 5),
(4, 1, 'أي طبقة من طبقات النموذج الطبقي تتعامل مباشرة مع قواعد البيانات؟', 'mcq', '["العرض", "المنطق", "البيانات"]', 'البيانات', 10),
(5, 2, 'ما المقصود بنظام المعلومات؟', 'mcq', '["مجموعة مترابطة من العناصر تجمع وتعالج وتخزن وتوزع المعلومات", "برنامج واحد للطباعة", "شبكة إنترنت فقط"]', 'مجموعة مترابطة من العناصر تجمع وتعالج وتخزن وتوزع المعلومات', 10),
(6, 2, 'وثيقة مواصفات المتطلبات يجب أن تكون ثابتة ولا تُحدَّث أبداً.', 'true_false', '["صح", "خطأ"]', 'خطأ', 5),
(7, 2, 'لماذا نستخدم نماذج Use Case في التحليل؟', 'essay', NULL, NULL, 5),
(8, 3, 'مخرجات برنامج Java تمر عادةً عبر:', 'mcq', '["المترجم فقط", "JVM بعد الترجمة إلى bytecode", "محرر النصوص"]', 'JVM بعد الترجمة إلى bytecode', 8),
(9, 3, 'الكلمة المفتاحية class تُستخدم لتعريف فئة في Java.', 'true_false', '["صح", "خطأ"]', 'صح', 7);

INSERT INTO exam_submissions (exam_id, student_id, answers, total_score, submitted_at) VALUES
(2, 4, '{"5":"مجموعة مترابطة من العناصر تجمع وتعالج وتخزن وتوزع المعلومات","6":"خطأ","7":"لوصف تفاعلات المستخدم مع النظام وتحديد النطاق بوضوح."}', 15, TIMESTAMPADD(MINUTE, 20, DATE_SUB(NOW(), INTERVAL 2 DAY)));

/* ضبط العدادات بعد الإدخالات الصريحة */
ALTER TABLE users AUTO_INCREMENT = 6;
ALTER TABLE courses AUTO_INCREMENT = 4;
ALTER TABLE lectures AUTO_INCREMENT = 100;
ALTER TABLE assignments AUTO_INCREMENT = 6;
ALTER TABLE exams AUTO_INCREMENT = 4;
ALTER TABLE exam_questions AUTO_INCREMENT = 10;

/* ==================================================================
   ما يلي اختياري — لا يُنفَّذ تلقائياً (كل كتلة بين شرطتين مائلتين مع نجمة)
   انسخ القسم الذي تحتاجه إلى phpMyAdmin بعد إزالة التعليق عنه فقط.
   ================================================================== */

/*
-- ------------------------------------------------------------------
-- (أ) حذف جميع الجداول من القاعدة الحالية (نفس USE في أول الملف)
-- ------------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS exam_submissions;
DROP TABLE IF EXISTS exam_questions;
DROP TABLE IF EXISTS exams;
DROP TABLE IF EXISTS assignment_submissions;
DROP TABLE IF EXISTS assignments;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS lectures;
DROP TABLE IF EXISTS course_enrollments;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;
*/

/*
-- ------------------------------------------------------------------
-- (ب) حذف القاعدة بالكامل ثم إنشاؤها من جديد (يحذف كل شيء)
-- غيّر scanedu_db إلى اسم قاعدتك إن اختلف
-- بعد التنفيذ أعد تشغيل أوامر CREATE TABLE من بداية هذا الملف
-- ------------------------------------------------------------------
DROP DATABASE IF EXISTS scanedu_db;
CREATE DATABASE scanedu_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE scanedu_db;
*/

/*
-- ------------------------------------------------------------------
-- (ج) ترقية قديمة: جدول lectures بدون عمود week_number
-- إن كان العمود موجوداً (#1060) احذف سطر ADD COLUMN ونفّذ الباقي إن لزم
-- أو احذف attendance ثم lectures ثم أعد إنشاء الجدول من CREATE في الأعلى
-- ------------------------------------------------------------------
ALTER TABLE lectures
    ADD COLUMN week_number TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'أسبوع الترم 1-15' AFTER course_id;

ALTER TABLE lectures
    ADD UNIQUE KEY uq_course_week (course_id, week_number);
*/
