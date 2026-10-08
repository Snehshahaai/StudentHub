-- ==========================================================================
-- StudentHub - MySQL / MariaDB Database Schema
-- Covers every feature in the portal: accounts, academics (subjects,
-- timetable, attendance, results), assignments, study materials, notices,
-- events + registrations, FAQs, contact messages and admin audit logs.
--
-- Import:  mysql -u root < database/schema.sql   (then seed.sql)
--   or use phpMyAdmin > Import.
-- Requires MySQL 8.0+ or MariaDB 10.4+ (InnoDB, utf8mb4).
-- ==========================================================================

DROP DATABASE IF EXISTS studenthub;
CREATE DATABASE studenthub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE studenthub;

SET NAMES utf8mb4;
SET time_zone = '+05:30';

-- ==========================================================================
-- 1. ORGANISATION
-- ==========================================================================

-- Departments: "Computer Engineering", "Information Technology", ...
CREATE TABLE departments (
    department_id   TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code            VARCHAR(5)   NOT NULL,              -- used in enrollment no. e.g. 2026CS108
    name            VARCHAR(100) NOT NULL,
    short_name      VARCHAR(30)  NOT NULL,              -- "Computer Engg" (admin table)
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (department_id),
    UNIQUE KEY uq_departments_code (code),
    UNIQUE KEY uq_departments_name (name)
) ENGINE=InnoDB;

-- Courses offered on the registration form (BTECH-CSE, BCA, MCA, ...)
CREATE TABLE courses (
    course_id       TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code            VARCHAR(20)  NOT NULL,              -- value of <select name="course">
    name            VARCHAR(120) NOT NULL,
    department_id   TINYINT UNSIGNED NOT NULL,
    duration_years  TINYINT UNSIGNED NOT NULL DEFAULT 4,
    PRIMARY KEY (course_id),
    UNIQUE KEY uq_courses_code (code),
    CONSTRAINT fk_courses_department FOREIGN KEY (department_id)
        REFERENCES departments (department_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_courses_duration CHECK (duration_years BETWEEN 1 AND 6)
) ENGINE=InnoDB;

-- ==========================================================================
-- 2. ACCOUNTS
-- ==========================================================================

-- Faculty and administrators (admin dashboard, notice posting, grading)
CREATE TABLE faculty (
    faculty_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_code   VARCHAR(20)  NOT NULL,
    full_name       VARCHAR(100) NOT NULL,
    email           VARCHAR(100) NOT NULL,
    phone           VARCHAR(15)  NULL,
    password_hash   VARCHAR(255) NOT NULL,
    department_id   TINYINT UNSIGNED NULL,
    designation     VARCHAR(60)  NOT NULL DEFAULT 'Assistant Professor',
    role            ENUM('faculty', 'admin') NOT NULL DEFAULT 'faculty',
    status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    last_login_at   DATETIME     NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (faculty_id),
    UNIQUE KEY uq_faculty_code (employee_code),
    UNIQUE KEY uq_faculty_email (email),
    CONSTRAINT fk_faculty_department FOREIGN KEY (department_id)
        REFERENCES departments (department_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- Students: registration form + profile page + admin enrollments table
CREATE TABLE students (
    student_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    enrollment_no     VARCHAR(20)  NOT NULL,            -- "2026CS108" (Roll No / Student ID)
    username          VARCHAR(20)  NOT NULL,            -- register: username (login id, lowercase)
    full_name         VARCHAR(50)  NOT NULL,            -- register: fullName
    email             VARCHAR(100) NOT NULL,            -- register: email (login id)
    mobile            CHAR(10)     NOT NULL,            -- register: mobile (10 digits, 6-9 start)
    password_hash     VARCHAR(255) NOT NULL,            -- register: password (password_hash())
    gender            ENUM('Male', 'Female', 'Other') NOT NULL,
    course_id         TINYINT UNSIGNED NOT NULL,        -- register: course
    year_of_study     TINYINT UNSIGNED NOT NULL,        -- register: year (1-4)
    semester          TINYINT UNSIGNED NOT NULL,        -- profile: "Sem 6"
    address           VARCHAR(255) NULL,                -- profile: address
    profile_photo     VARCHAR(255) NULL,                -- profile: avatar image path
    theme_preference  ENUM('dark', 'light') NOT NULL DEFAULT 'dark',
    status            ENUM('pending', 'active', 'inactive', 'suspended') NOT NULL DEFAULT 'pending',
    approved_by       INT UNSIGNED NULL,                -- admin "Approve" button
    approved_at       DATETIME     NULL,
    terms_accepted_at DATETIME     NOT NULL,            -- register: terms checkbox
    last_login_at     DATETIME     NULL,
    created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id),
    UNIQUE KEY uq_students_enrollment (enrollment_no),
    UNIQUE KEY uq_students_username (username),
    UNIQUE KEY uq_students_email (email),
    UNIQUE KEY uq_students_mobile (mobile),
    KEY idx_students_status (status),
    KEY idx_students_course_sem (course_id, semester),
    CONSTRAINT fk_students_course FOREIGN KEY (course_id)
        REFERENCES courses (course_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_students_approved_by FOREIGN KEY (approved_by)
        REFERENCES faculty (faculty_id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT chk_students_username CHECK (username REGEXP '^[a-z][a-z0-9_.]{3,19}$'),
    CONSTRAINT chk_students_mobile CHECK (mobile REGEXP '^[6-9][0-9]{9}$'),
    CONSTRAINT chk_students_year CHECK (year_of_study BETWEEN 1 AND 6),
    CONSTRAINT chk_students_semester CHECK (semester BETWEEN 1 AND 12)
) ENGINE=InnoDB;

-- "Forgot Password?" modal: 6-digit reset code (stored hashed)
CREATE TABLE password_resets (
    reset_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id      INT UNSIGNED NOT NULL,
    code_hash       VARCHAR(255) NOT NULL,
    attempts        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at      DATETIME     NOT NULL,
    used_at         DATETIME     NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (reset_id),
    KEY idx_password_resets_student (student_id, expires_at),
    CONSTRAINT fk_password_resets_student FOREIGN KEY (student_id)
        REFERENCES students (student_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- "Remember login state" checkbox (selector + hashed validator pattern).
-- Belongs to exactly one student or one faculty/admin account.
CREATE TABLE remember_tokens (
    token_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id      INT UNSIGNED NULL,
    faculty_id      INT UNSIGNED NULL,
    selector        CHAR(24)     NOT NULL,
    validator_hash  CHAR(64)     NOT NULL,              -- sha256 of the cookie secret
    user_agent      VARCHAR(255) NULL,
    expires_at      DATETIME     NOT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (token_id),
    UNIQUE KEY uq_remember_selector (selector),
    CONSTRAINT fk_remember_tokens_student FOREIGN KEY (student_id)
        REFERENCES students (student_id) ON DELETE CASCADE,
    CONSTRAINT fk_remember_tokens_faculty FOREIGN KEY (faculty_id)
        REFERENCES faculty (faculty_id) ON DELETE CASCADE,
    CONSTRAINT chk_remember_tokens_owner CHECK ((student_id IS NULL) <> (faculty_id IS NULL))
) ENGINE=InnoDB;

-- ==========================================================================
-- 3. ACADEMICS
-- ==========================================================================

-- Subjects: "CS-301 Web Development Tech" (attendance, assignments, materials)
CREATE TABLE subjects (
    subject_id      SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code            VARCHAR(10)  NOT NULL,              -- "CS-301"
    name            VARCHAR(100) NOT NULL,              -- "Web Development Tech"
    short_name      VARCHAR(30)  NOT NULL,              -- "Web Tech", "DBMS", "DSA"
    department_id   TINYINT UNSIGNED NOT NULL,
    semester        TINYINT UNSIGNED NOT NULL,
    credits         TINYINT UNSIGNED NOT NULL DEFAULT 4,
    faculty_id      INT UNSIGNED NULL,                  -- subject coordinator
    PRIMARY KEY (subject_id),
    UNIQUE KEY uq_subjects_code (code),
    KEY idx_subjects_dept_sem (department_id, semester),
    CONSTRAINT fk_subjects_department FOREIGN KEY (department_id)
        REFERENCES departments (department_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_subjects_faculty FOREIGN KEY (faculty_id)
        REFERENCES faculty (faculty_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- Weekly timetable: dashboard "Today's Schedule"
CREATE TABLE class_schedule (
    schedule_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    subject_id      SMALLINT UNSIGNED NOT NULL,
    faculty_id      INT UNSIGNED NULL,
    day_of_week     TINYINT UNSIGNED NOT NULL,          -- 1 = Monday ... 7 = Sunday
    start_time      TIME         NOT NULL,
    end_time        TIME         NOT NULL,
    room            VARCHAR(30)  NOT NULL,              -- "Lab 3", "Hall A", "Room 204"
    session_type    ENUM('Lecture', 'Lab', 'Tutorial') NOT NULL DEFAULT 'Lecture',
    PRIMARY KEY (schedule_id),
    KEY idx_schedule_day (day_of_week, start_time),
    CONSTRAINT fk_schedule_subject FOREIGN KEY (subject_id)
        REFERENCES subjects (subject_id) ON DELETE CASCADE,
    CONSTRAINT fk_schedule_faculty FOREIGN KEY (faculty_id)
        REFERENCES faculty (faculty_id) ON DELETE SET NULL,
    CONSTRAINT chk_schedule_day CHECK (day_of_week BETWEEN 1 AND 7),
    CONSTRAINT chk_schedule_time CHECK (end_time > start_time)
) ENGINE=InnoDB;

-- Every lecture actually conducted ("Total Conducted Lectures")
CREATE TABLE lectures (
    lecture_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    subject_id      SMALLINT UNSIGNED NOT NULL,
    schedule_id     INT UNSIGNED NULL,
    faculty_id      INT UNSIGNED NULL,
    lecture_date    DATE         NOT NULL,
    start_time      TIME         NOT NULL,
    topic           VARCHAR(150) NULL,
    PRIMARY KEY (lecture_id),
    UNIQUE KEY uq_lectures_slot (subject_id, lecture_date, start_time),
    CONSTRAINT fk_lectures_subject FOREIGN KEY (subject_id)
        REFERENCES subjects (subject_id) ON DELETE CASCADE,
    CONSTRAINT fk_lectures_schedule FOREIGN KEY (schedule_id)
        REFERENCES class_schedule (schedule_id) ON DELETE SET NULL,
    CONSTRAINT fk_lectures_faculty FOREIGN KEY (faculty_id)
        REFERENCES faculty (faculty_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- One row per student per lecture ("Lectures Attended", absence logs)
CREATE TABLE attendance (
    attendance_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lecture_id      INT UNSIGNED NOT NULL,
    student_id      INT UNSIGNED NOT NULL,
    status          ENUM('present', 'absent', 'leave') NOT NULL DEFAULT 'present',
    marked_by       INT UNSIGNED NULL,
    marked_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (attendance_id),
    UNIQUE KEY uq_attendance_lecture_student (lecture_id, student_id),
    KEY idx_attendance_student (student_id),
    CONSTRAINT fk_attendance_lecture FOREIGN KEY (lecture_id)
        REFERENCES lectures (lecture_id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_student FOREIGN KEY (student_id)
        REFERENCES students (student_id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_marked_by FOREIGN KEY (marked_by)
        REFERENCES faculty (faculty_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Semester results: dashboard "Current CGPA"
CREATE TABLE semester_results (
    result_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id      INT UNSIGNED NOT NULL,
    semester        TINYINT UNSIGNED NOT NULL,
    sgpa            DECIMAL(4, 2) NOT NULL,
    cgpa            DECIMAL(4, 2) NOT NULL,
    result_status   ENUM('pass', 'fail', 'withheld') NOT NULL DEFAULT 'pass',
    published_on    DATE         NOT NULL,
    PRIMARY KEY (result_id),
    UNIQUE KEY uq_results_student_sem (student_id, semester),
    CONSTRAINT fk_results_student FOREIGN KEY (student_id)
        REFERENCES students (student_id) ON DELETE CASCADE,
    CONSTRAINT chk_results_sgpa CHECK (sgpa BETWEEN 0 AND 10),
    CONSTRAINT chk_results_cgpa CHECK (cgpa BETWEEN 0 AND 10)
) ENGINE=InnoDB;

-- ==========================================================================
-- 4. ASSIGNMENTS
-- ==========================================================================

-- Assignments page: title, subject, deadline, problem statement download
CREATE TABLE assignments (
    assignment_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    subject_id        SMALLINT UNSIGNED NOT NULL,
    title             VARCHAR(150) NOT NULL,
    description       TEXT         NULL,
    problem_file_path VARCHAR(255) NULL,                -- "Problem Statement" download
    assigned_on       DATE         NOT NULL,
    deadline          DATETIME     NOT NULL,
    max_marks         SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    allowed_formats   VARCHAR(100) NOT NULL DEFAULT 'pdf,zip,docx',
    max_file_mb       TINYINT UNSIGNED NOT NULL DEFAULT 25,
    created_by        INT UNSIGNED NULL,
    created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (assignment_id),
    KEY idx_assignments_deadline (deadline),
    CONSTRAINT fk_assignments_subject FOREIGN KEY (subject_id)
        REFERENCES subjects (subject_id) ON DELETE CASCADE,
    CONSTRAINT fk_assignments_created_by FOREIGN KEY (created_by)
        REFERENCES faculty (faculty_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- "Submit Assignment" modal: file + comments; grading by faculty
CREATE TABLE assignment_submissions (
    submission_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    assignment_id   INT UNSIGNED NOT NULL,
    student_id      INT UNSIGNED NOT NULL,
    file_path       VARCHAR(255) NOT NULL,
    original_name   VARCHAR(255) NOT NULL,
    file_size_bytes INT UNSIGNED NOT NULL,
    comments        VARCHAR(1000) NULL,                 -- "Comments / Notes"
    submitted_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_late         BOOLEAN      NOT NULL DEFAULT FALSE,
    status          ENUM('submitted', 'graded', 'returned') NOT NULL DEFAULT 'submitted',
    marks_obtained  DECIMAL(5, 2) NULL,
    feedback        VARCHAR(1000) NULL,
    graded_by       INT UNSIGNED NULL,
    graded_at       DATETIME     NULL,
    PRIMARY KEY (submission_id),
    UNIQUE KEY uq_submissions_assignment_student (assignment_id, student_id),
    KEY idx_submissions_student (student_id),
    KEY idx_submissions_date (submitted_at),
    CONSTRAINT fk_submissions_assignment FOREIGN KEY (assignment_id)
        REFERENCES assignments (assignment_id) ON DELETE CASCADE,
    CONSTRAINT fk_submissions_student FOREIGN KEY (student_id)
        REFERENCES students (student_id) ON DELETE CASCADE,
    CONSTRAINT fk_submissions_graded_by FOREIGN KEY (graded_by)
        REFERENCES faculty (faculty_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==========================================================================
-- 5. STUDY MATERIALS
-- ==========================================================================

-- Materials page: resource name, subject, type, size, uploaded date.
-- Uploaded by faculty, or by students via "Upload Student Notes" (needs approval).
CREATE TABLE study_materials (
    material_id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    subject_id          SMALLINT UNSIGNED NOT NULL,
    title               VARCHAR(150) NOT NULL,
    material_type       ENUM('Lecture PDF', 'Source Code', 'Cheatsheet', 'Lab Manual',
                             'Question Paper', 'Syllabus', 'Student Notes') NOT NULL,
    file_path           VARCHAR(255) NOT NULL,
    original_name       VARCHAR(255) NOT NULL,
    file_size_bytes     INT UNSIGNED NOT NULL,
    uploaded_by_faculty INT UNSIGNED NULL,
    uploaded_by_student INT UNSIGNED NULL,
    is_approved         BOOLEAN      NOT NULL DEFAULT TRUE,
    download_count      INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (material_id),
    KEY idx_materials_subject (subject_id, material_type),
    CONSTRAINT fk_materials_subject FOREIGN KEY (subject_id)
        REFERENCES subjects (subject_id) ON DELETE CASCADE,
    CONSTRAINT fk_materials_faculty FOREIGN KEY (uploaded_by_faculty)
        REFERENCES faculty (faculty_id) ON DELETE SET NULL,
    CONSTRAINT fk_materials_student FOREIGN KEY (uploaded_by_student)
        REFERENCES students (student_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==========================================================================
-- 6. NOTICES
-- ==========================================================================

-- Notice board (data/notices.json) + admin "Post Campus Notice" modal
CREATE TABLE notices (
    notice_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title           VARCHAR(150) NOT NULL,              -- "Notice Headline"
    category        ENUM('Exam', 'Placement', 'Event', 'Academic', 'Holiday') NOT NULL,
    priority        ENUM('high', 'medium', 'low') NOT NULL DEFAULT 'medium',
    summary         VARCHAR(500) NOT NULL,
    details         TEXT         NULL,                  -- "Notice Content" / modal text
    posted_by       VARCHAR(60)  NOT NULL,              -- "Exam Cell", "T&P Office"
    faculty_id      INT UNSIGNED NULL,                  -- account that published it
    published_on    DATE         NOT NULL,
    expires_on      DATE         NULL,
    is_published    BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (notice_id),
    KEY idx_notices_published (is_published, published_on),
    KEY idx_notices_category (category),
    FULLTEXT KEY ft_notices_search (title, summary, details),
    CONSTRAINT fk_notices_faculty FOREIGN KEY (faculty_id)
        REFERENCES faculty (faculty_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- "Subscribe to Alerts" button
CREATE TABLE notice_subscriptions (
    subscription_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id      INT UNSIGNED NOT NULL,
    channel         ENUM('email', 'sms') NOT NULL DEFAULT 'email',
    categories      SET('Exam', 'Placement', 'Event', 'Academic', 'Holiday')
                        NOT NULL DEFAULT 'Exam,Placement,Event,Academic,Holiday',
    is_active       BOOLEAN      NOT NULL DEFAULT TRUE,
    subscribed_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (subscription_id),
    UNIQUE KEY uq_subscriptions_student_channel (student_id, channel),
    CONSTRAINT fk_subscriptions_student FOREIGN KEY (student_id)
        REFERENCES students (student_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==========================================================================
-- 7. EVENTS & REGISTRATIONS
-- ==========================================================================

-- Campus events (data/events.json) + home page "Featured Events" carousel
CREATE TABLE events (
    event_id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title                 VARCHAR(150) NOT NULL,
    event_type            ENUM('Technical', 'Cultural', 'Sports', 'Workshop', 'Seminar', 'Placement') NOT NULL,
    description           TEXT         NOT NULL,
    event_date            DATE         NOT NULL,
    start_time            TIME         NOT NULL,
    end_time              TIME         NULL,
    venue                 VARCHAR(100) NOT NULL,
    organizer             VARCHAR(100) NOT NULL,
    seats                 SMALLINT UNSIGNED NOT NULL,   -- capacity
    is_team_event         BOOLEAN      NOT NULL DEFAULT FALSE,
    max_team_size         TINYINT UNSIGNED NULL,
    registration_deadline DATETIME     NULL,
    image_path            VARCHAR(255) NULL,            -- carousel slide image
    is_featured           BOOLEAN      NOT NULL DEFAULT FALSE,
    status                ENUM('scheduled', 'cancelled', 'completed') NOT NULL DEFAULT 'scheduled',
    created_by            INT UNSIGNED NULL,
    created_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id),
    KEY idx_events_date (event_date),
    KEY idx_events_type (event_type),
    CONSTRAINT fk_events_created_by FOREIGN KEY (created_by)
        REFERENCES faculty (faculty_id) ON DELETE SET NULL,
    CONSTRAINT chk_events_seats CHECK (seats > 0)
) ENGINE=InnoDB;

-- Event sign-ups: "Web Bootcamp Registration" / "Annual Sports Tournament" modals
CREATE TABLE event_registrations (
    registration_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id        INT UNSIGNED NOT NULL,
    student_id      INT UNSIGNED NOT NULL,              -- name, enrollment ID, department come from here
    team_name       VARCHAR(60)  NULL,                  -- sports / hackathon teams
    team_size       TINYINT UNSIGNED NULL,
    status          ENUM('registered', 'waitlisted', 'cancelled', 'attended') NOT NULL DEFAULT 'registered',
    registered_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cancelled_at    DATETIME     NULL,
    PRIMARY KEY (registration_id),
    UNIQUE KEY uq_event_registrations (event_id, student_id),
    KEY idx_event_registrations_student (student_id),
    CONSTRAINT fk_event_reg_event FOREIGN KEY (event_id)
        REFERENCES events (event_id) ON DELETE CASCADE,
    CONSTRAINT fk_event_reg_student FOREIGN KEY (student_id)
        REFERENCES students (student_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==========================================================================
-- 8. HELP CENTER
-- ==========================================================================

-- FAQ page (data/faqs.json)
CREATE TABLE faqs (
    faq_id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category        ENUM('Account', 'Attendance', 'Assignments', 'Materials', 'Portal', 'Support') NOT NULL,
    icon            VARCHAR(40)  NOT NULL DEFAULT 'fa-question-circle',
    question        VARCHAR(255) NOT NULL,
    answer          TEXT         NOT NULL,
    link_href       VARCHAR(255) NULL,
    link_label      VARCHAR(60)  NULL,
    sort_order      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_published    BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (faq_id),
    KEY idx_faqs_category (category, sort_order),
    FULLTEXT KEY ft_faqs_search (question, answer)
) ENGINE=InnoDB;

-- Contact page form (php/contact.php)
CREATE TABLE contact_messages (
    message_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_no       VARCHAR(25)  NOT NULL,              -- "MSG-20261008-9bb794"
    student_id      INT UNSIGNED NULL,                  -- set when a logged-in student writes
    name            VARCHAR(50)  NOT NULL,
    email           VARCHAR(100) NOT NULL,
    subject         ENUM('General Inquiry', 'Technical Portal Bug',
                         'Attendance Discrepancy', 'Assignment Portal Issue') NOT NULL,
    message         TEXT         NOT NULL,
    status          ENUM('open', 'in_progress', 'resolved', 'closed') NOT NULL DEFAULT 'open',
    ip_address      VARCHAR(45)  NULL,
    submitted_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_by     INT UNSIGNED NULL,
    resolved_at     DATETIME     NULL,
    PRIMARY KEY (message_id),
    UNIQUE KEY uq_contact_ticket (ticket_no),
    KEY idx_contact_status (status, submitted_at),
    CONSTRAINT fk_contact_student FOREIGN KEY (student_id)
        REFERENCES students (student_id) ON DELETE SET NULL,
    CONSTRAINT fk_contact_resolved_by FOREIGN KEY (resolved_by)
        REFERENCES faculty (faculty_id) ON DELETE SET NULL,
    CONSTRAINT chk_contact_message_length CHECK (CHAR_LENGTH(message) BETWEEN 10 AND 1000)
) ENGINE=InnoDB;

-- ==========================================================================
-- 9. ADMIN
-- ==========================================================================

-- Admin dashboard "Audit Logs" button
CREATE TABLE audit_logs (
    log_id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_type      ENUM('student', 'faculty', 'admin', 'system') NOT NULL,
    actor_id        INT UNSIGNED NULL,
    action          VARCHAR(60)  NOT NULL,              -- "student.approve", "notice.create"
    entity_type     VARCHAR(40)  NULL,
    entity_id       INT UNSIGNED NULL,
    details         TEXT         NULL,
    ip_address      VARCHAR(45)  NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id),
    KEY idx_audit_created (created_at),
    KEY idx_audit_actor (actor_type, actor_id)
) ENGINE=InnoDB;

-- ==========================================================================
-- 10. VIEWS (numbers shown on the dashboards)
-- ==========================================================================

-- Attendance page table: attended / conducted / percentage / status per subject
CREATE VIEW v_attendance_summary AS
SELECT
    a.student_id,
    s.subject_id,
    s.code                                   AS subject_code,
    s.name                                   AS subject_name,
    SUM(a.status = 'present')                AS lectures_attended,
    COUNT(*)                                 AS total_conducted,
    ROUND(SUM(a.status = 'present') * 100 / COUNT(*), 1) AS percentage,
    CASE WHEN SUM(a.status = 'present') * 100 / COUNT(*) >= 75 THEN 'Good' ELSE 'Shortage' END AS status
FROM attendance a
JOIN lectures l ON l.lecture_id = a.lecture_id
JOIN subjects s ON s.subject_id = l.subject_id
GROUP BY a.student_id, s.subject_id, s.code, s.name;

-- Student dashboard / profile header in one row
CREATE VIEW v_student_profile AS
SELECT
    st.student_id,
    st.enrollment_no,
    st.username,
    st.full_name,
    st.email,
    st.mobile,
    st.gender,
    st.address,
    st.year_of_study,
    st.semester,
    st.status,
    c.code       AS course_code,
    c.name       AS course_name,
    d.name       AS department,
    d.short_name AS department_short,
    (SELECT r.cgpa FROM semester_results r
      WHERE r.student_id = st.student_id
      ORDER BY r.semester DESC LIMIT 1) AS current_cgpa,
    st.created_at AS registered_at
FROM students st
JOIN courses c     ON c.course_id = st.course_id
JOIN departments d ON d.department_id = c.department_id;

-- Seats left for every event
CREATE VIEW v_event_seats AS
SELECT
    e.event_id,
    e.title,
    e.event_date,
    e.seats,
    COUNT(r.registration_id)            AS registered,
    e.seats - COUNT(r.registration_id)  AS seats_left
FROM events e
LEFT JOIN event_registrations r
       ON r.event_id = e.event_id AND r.status IN ('registered', 'attended')
GROUP BY e.event_id, e.title, e.event_date, e.seats;

-- Admin dashboard stat cards
CREATE VIEW v_admin_stats AS
SELECT
    (SELECT COUNT(*) FROM students WHERE status = 'active')   AS total_students,
    (SELECT COUNT(*) FROM students WHERE status = 'pending')  AS pending_approvals,
    (SELECT COUNT(*) FROM faculty  WHERE status = 'active')   AS active_faculty,
    (SELECT COUNT(*) FROM assignment_submissions
      WHERE DATE(submitted_at) = CURRENT_DATE)                 AS submissions_today,
    (SELECT COUNT(*) FROM contact_messages WHERE status = 'open') AS open_tickets;
