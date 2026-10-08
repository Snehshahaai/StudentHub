-- ==========================================================================
-- Migration 001: add a unique username to students
-- For databases created before usernames existed. Keeps every existing row.
-- Run once:  mysql -u root < database/migrations/001_add_student_username.sql
-- (Fresh installs don't need this: schema.sql already has the column.)
-- ==========================================================================

USE studenthub;

ALTER TABLE students ADD COLUMN username VARCHAR(20) NULL AFTER enrollment_no;

-- Give existing students a username from their email: "Het.Shah12@x.com" -> "het.shah12"
UPDATE students
   SET username = LEFT(LOWER(REGEXP_REPLACE(SUBSTRING_INDEX(email, '@', 1), '[^A-Za-z0-9_.]', '')), 16);

-- Usernames must start with a letter and have at least 4 characters
UPDATE students SET username = LEFT(CONCAT('u', username), 16) WHERE username NOT REGEXP '^[a-z]';
UPDATE students SET username = CONCAT(username, '_', LPAD(student_id, 3, '0')) WHERE CHAR_LENGTH(username) < 4;

-- Two emails with the same local part: the oldest account keeps the name,
-- later ones get their student id added
UPDATE students s
  JOIN (SELECT username, MIN(student_id) AS keep_id
          FROM (SELECT username, student_id FROM students) AS t
         GROUP BY username HAVING COUNT(*) > 1) AS dup
    ON dup.username = s.username AND s.student_id <> dup.keep_id
   SET s.username = CONCAT(s.username, s.student_id);

ALTER TABLE students
    MODIFY username VARCHAR(20) NOT NULL,
    ADD UNIQUE KEY uq_students_username (username),
    ADD CONSTRAINT chk_students_username CHECK (username REGEXP '^[a-z][a-z0-9_.]{3,19}$');

-- The profile view now includes the username
CREATE OR REPLACE VIEW v_student_profile AS
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
