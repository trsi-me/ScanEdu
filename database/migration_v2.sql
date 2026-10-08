-- ترقية ScanEdu v2: أقسام، ترمات، أدمن، حالة المستخدم
-- نفّذ على قاعدة موجودة من phpMyAdmin أو mysql CLI
USE scanedu_db;

CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS terms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    start_date DATE NULL,
    end_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- توسيع جدول users (تخطّ السطر إن كان موجوداً #1060)
ALTER TABLE users
    MODIFY COLUMN role ENUM('admin', 'doctor', 'student') NOT NULL;

ALTER TABLE users
    ADD COLUMN status ENUM('active', 'suspended') NOT NULL DEFAULT 'active' AFTER role;

ALTER TABLE users
    ADD COLUMN department_id INT NULL AFTER department;

ALTER TABLE users
    ADD CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;

ALTER TABLE courses
    ADD COLUMN term_id INT NULL AFTER semester;

ALTER TABLE courses
    ADD CONSTRAINT fk_courses_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE SET NULL;

-- بيانات أولية للأقسام والترمات
INSERT IGNORE INTO departments (id, name, description) VALUES
(1, 'قسم علوم الحاسب والمعلومات', 'برامج علوم الحاسب وتقنية المعلومات');

INSERT IGNORE INTO terms (id, name, is_current, start_date, end_date) VALUES
(1, 'ربيع 2026', 1, '2026-01-05', '2026-05-15'),
(2, 'خريف 2026', 0, '2026-09-01', '2026-12-20');

-- ربط المستخدمين الحاليين بالقسم
UPDATE users SET department_id = 1 WHERE department_id IS NULL AND department LIKE '%حاسب%';

-- حساب أدمن (كلمة المرور: )
INSERT IGNORE INTO users (name, email, password, role, status, department_id) VALUES
('مدير النظام', 'admin@scanedu.local', '', 'admin', 'active', NULL);

-- دكتور ثانٍ للتجربة (زملاء القسم)
INSERT IGNORE INTO users (name, email, password, role, status, department_id) VALUES
('د. سارة خالد الشمري', 'doctor2@scanedu.local', '', 'doctor', 'active', 1);

UPDATE courses SET term_id = 1 WHERE term_id IS NULL AND semester = 'ربيع 2026';
