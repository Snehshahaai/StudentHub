-- ==========================================================================
-- Migration 002: "remember me" tokens for faculty / admin accounts too
-- A token now belongs to exactly one student OR one faculty member.
-- Run once:  mysql -u root < database/migrations/002_remember_tokens_for_faculty.sql
-- ==========================================================================

USE studenthub;

ALTER TABLE remember_tokens
    MODIFY student_id INT UNSIGNED NULL,
    ADD COLUMN faculty_id INT UNSIGNED NULL AFTER student_id,
    ADD CONSTRAINT fk_remember_tokens_faculty FOREIGN KEY (faculty_id)
        REFERENCES faculty (faculty_id) ON DELETE CASCADE,
    ADD CONSTRAINT chk_remember_tokens_owner CHECK ((student_id IS NULL) <> (faculty_id IS NULL));
