-- ==========================================================================
-- StudentHub - Sample Data
-- Mirrors the content already shown on the pages (profile, dashboards,
-- attendance, assignments, materials, data/*.json).
-- Import after schema.sql:  mysql -u root studenthub < database/seed.sql
--
-- Demo logins (change them before going live):
--   Students : sneh.shah@university.edu / Student@123  (also rohan.v@, priya.p@)
--   Admin    : admin@university.edu     / Admin@123
--   Faculty  : a.mehta@university.edu   / Faculty@123
-- ==========================================================================

USE studenthub;
SET NAMES utf8mb4;
SET time_zone = '+05:30';

-- Departments
INSERT INTO departments (department_id, code, name, short_name) VALUES
    (1, 'CS', 'Computer Engineering', 'Computer Engg'),
    (2, 'IT', 'Information Technology', 'Information Tech'),
    (3, 'EC', 'Electronics & Communication', 'Electronics'),
    (4, 'ME', 'Mechanical Engineering', 'Mechanical'),
    (5, 'CV', 'Civil Engineering', 'Civil'),
    (6, 'CA', 'Computer Applications', 'Comp. Applications');

-- Courses (values of the registration form <select name="course">)
INSERT INTO courses (course_id, code, name, department_id, duration_years) VALUES
    (1, 'BTECH-CSE', 'B.Tech - Computer Science & Engineering', 1, 4),
    (2, 'BTECH-IT', 'B.Tech - Information Technology', 2, 4),
    (3, 'BTECH-EC', 'B.Tech - Electronics & Communication', 3, 4),
    (4, 'BTECH-ME', 'B.Tech - Mechanical Engineering', 4, 4),
    (5, 'BCA', 'BCA', 6, 3),
    (6, 'MCA', 'MCA', 6, 2);

-- Faculty & admin
INSERT INTO faculty (faculty_id, employee_code, full_name, email, phone, password_hash, department_id, designation, role) VALUES
    (1, 'FAC-CS-01', 'Dr. Anjali Mehta', 'a.mehta@university.edu', '9825011111', '$2y$12$IPknoW8oBRmouyRsjiQaoehpHaFdWGhgNNAExVz3GSZUbfkj9ipTq', 1, 'Associate Professor', 'faculty'),
    (2, 'FAC-CS-02', 'Prof. Rakesh Joshi', 'r.joshi@university.edu', '9825022222', '$2y$12$IPknoW8oBRmouyRsjiQaoehpHaFdWGhgNNAExVz3GSZUbfkj9ipTq', 1, 'Assistant Professor', 'faculty'),
    (3, 'FAC-IT-01', 'Prof. Neha Desai', 'n.desai@university.edu', '9825033333', '$2y$12$IPknoW8oBRmouyRsjiQaoehpHaFdWGhgNNAExVz3GSZUbfkj9ipTq', 2, 'Assistant Professor', 'faculty'),
    (4, 'ADM-001', 'Campus Administrator', 'admin@university.edu', '9825000000', '$2y$12$QO3kF4fXJyFjfkshVLp16OzESHg89cyB9rke9K7j3M/T/s1Xt1wI2', NULL, 'Registrar', 'admin');

-- Students (profile page + admin "Recent Student Enrollments")
INSERT INTO students (student_id, enrollment_no, full_name, email, mobile, password_hash, gender, course_id, year_of_study, semester, address, status, approved_by, approved_at, terms_accepted_at) VALUES
    (1, '2026CS108', 'Sneh Shah', 'sneh.shah@university.edu', '9876543210', '$2y$12$39uymJhLlgqJQpSrrXkhGeTjB3mV51dLSERVH1a7DkooG.rBNTx1y', 'Male', 1, 3, 6, 'Ahmedabad, Gujarat, India', 'active', 4, '2026-07-01 10:00:00', '2026-06-28 18:30:00'),
    (2, '2026CS109', 'Rohan Verma', 'rohan.v@university.edu', '9876501234', '$2y$12$39uymJhLlgqJQpSrrXkhGeTjB3mV51dLSERVH1a7DkooG.rBNTx1y', 'Male', 1, 3, 6, 'Surat, Gujarat, India', 'pending', NULL, NULL, '2026-10-05 11:15:00'),
    (3, '2026IT204', 'Priya Patel', 'priya.p@university.edu', '9898012345', '$2y$12$39uymJhLlgqJQpSrrXkhGeTjB3mV51dLSERVH1a7DkooG.rBNTx1y', 'Female', 2, 3, 6, 'Vadodara, Gujarat, India', 'active', 4, '2026-07-01 10:05:00', '2026-06-29 09:45:00');

-- Subjects (attendance page)
INSERT INTO subjects (subject_id, code, name, short_name, department_id, semester, credits, faculty_id) VALUES
    (1, 'CS-301', 'Web Development Tech', 'Web Tech', 1, 6, 4, 1),
    (2, 'CS-302', 'Java Programming', 'Java Programming', 1, 6, 4, 2),
    (3, 'CS-303', 'Database Management Systems', 'DBMS', 1, 6, 4, 1),
    (4, 'CS-304', 'Data Structures & Algorithms', 'DSA', 1, 6, 4, 2),
    (5, 'IT-301', 'Cloud Computing', 'Cloud', 2, 6, 3, 3);

-- Weekly timetable (dashboard "Today's Schedule")
INSERT INTO class_schedule (schedule_id, subject_id, faculty_id, day_of_week, start_time, end_time, room, session_type) VALUES
    (1, 1, 1, 4, '10:00:00', '12:00:00', 'Lab 3', 'Lab'),
    (2, 2, 2, 4, '13:00:00', '14:30:00', 'Hall A', 'Lecture'),
    (3, 3, 1, 4, '15:00:00', '16:30:00', 'Room 204', 'Lecture'),
    (4, 4, 2, 1, '10:00:00', '11:30:00', 'Room 204', 'Lecture'),
    (5, 1, 1, 2, '11:00:00', '12:00:00', 'Room 101', 'Lecture'),
    (6, 3, 1, 3, '10:00:00', '12:00:00', 'Lab 2', 'Lab');

-- Lectures conducted: 30 per CS subject (= "Total Conducted 30")
CREATE TEMPORARY TABLE tmp_numbers (n TINYINT UNSIGNED PRIMARY KEY);
INSERT INTO tmp_numbers (n) VALUES
    (1),(2),(3),(4),(5),(6),(7),(8),(9),(10),(11),(12),(13),(14),(15),
    (16),(17),(18),(19),(20),(21),(22),(23),(24),(25),(26),(27),(28),(29),(30);

INSERT INTO lectures (subject_id, faculty_id, lecture_date, start_time, topic)
SELECT sb.subject_id, sb.faculty_id,
       DATE_ADD('2026-07-06', INTERVAL (n.n - 1) * 3 DAY),
       '10:00:00',
       CONCAT(sb.short_name, ' - Session ', n.n)
FROM subjects sb
CROSS JOIN tmp_numbers n
WHERE sb.department_id = 1;

-- Attendance for Sneh Shah: present in the first N lectures of each subject
INSERT INTO attendance (lecture_id, student_id, status, marked_by)
SELECT l.lecture_id, 1,
       IF(DATEDIFF(l.lecture_date, '2026-07-06') / 3 + 1 <= CASE l.subject_id
              WHEN 1 THEN 28 WHEN 2 THEN 25 WHEN 3 THEN 26 ELSE 25 END,
          'present', 'absent'),
       l.faculty_id
FROM lectures l;

DROP TEMPORARY TABLE tmp_numbers;

-- Semester results (dashboard "Current CGPA 9.12")
INSERT INTO semester_results (student_id, semester, sgpa, cgpa, published_on) VALUES
    (1, 1, 8.9, 8.9, '2024-12-20'),
    (1, 2, 9.1, 9.0, '2025-06-15'),
    (1, 3, 9.2, 9.07, '2025-12-18'),
    (1, 4, 9.05, 9.06, '2026-06-12'),
    (1, 5, 9.36, 9.12, '2026-06-30'),
    (3, 5, 8.4, 8.25, '2026-06-30');

-- Assignments (assignments page)
INSERT INTO assignments (assignment_id, subject_id, title, description, problem_file_path, assigned_on, deadline, max_marks, created_by) VALUES
    (1, 1, 'HTML & CSS Responsive Layout', 'Build a responsive multi-page layout using HTML5, CSS3 and Bootstrap 5.', 'uploads/assignments/html-css-layout.pdf', '2026-08-11', '2026-08-25 23:59:00', 10, 1),
    (2, 2, 'Java OOP & Inheritance Systems', 'Model a library system using classes, inheritance and interfaces.', 'uploads/assignments/java-oop.pdf', '2026-08-14', '2026-08-28 23:59:00', 10, 2),
    (3, 3, 'Database Queries & Normalization', 'Normalize the given schema to 3NF and write the SQL queries.', 'uploads/assignments/dbms-normalization.pdf', '2026-07-27', '2026-08-10 23:59:00', 10, 1);

-- Submissions ("Completed" DBMS assignment)
INSERT INTO assignment_submissions (assignment_id, student_id, file_path, original_name, file_size_bytes, comments, submitted_at, status, marks_obtained, feedback, graded_by, graded_at) VALUES
    (3, 1, 'uploads/submissions/2026CS108-a3.pdf', 'dbms-normalization-sneh.pdf', 845312, 'Included ER diagram as an appendix.', '2026-08-09 21:14:00', 'graded', 9.5, 'Excellent normalization steps.', 1, '2026-08-12 11:00:00');

-- Study materials (materials page)
INSERT INTO study_materials (subject_id, title, material_type, file_path, original_name, file_size_bytes, uploaded_by_faculty, uploaded_by_student, is_approved, download_count, uploaded_at) VALUES
    (1, 'HTML5 & CSS3 Full Handbook', 'Lecture PDF', 'uploads/materials/html5-css3-handbook.pdf', 'html5-css3-handbook.pdf', 4404019, 1, NULL, TRUE, 132, '2026-08-12 09:00:00'),
    (2, 'Java Multi-Threading Code Examples', 'Source Code', 'uploads/materials/java-threads.zip', 'java-threads.zip', 1887437, 2, NULL, TRUE, 87, '2026-08-14 09:00:00'),
    (3, 'Relational Algebra & SQL Cheatsheet', 'Cheatsheet', 'uploads/materials/sql-cheatsheet.pdf', 'sql-cheatsheet.pdf', 2621440, 1, NULL, TRUE, 210, '2026-08-15 09:00:00'),
    (4, 'Data Structures Lab Solutions', 'Lab Manual', 'uploads/materials/dsa-lab.pdf', 'dsa-lab.pdf', 8493465, 2, NULL, TRUE, 64, '2026-08-16 09:00:00'),
    (3, 'Unit 3 Revision Notes', 'Student Notes', 'uploads/materials/unit3-notes-sneh.pdf', 'unit3-notes.pdf', 1048576, NULL, 1, FALSE, 0, '2026-09-02 19:30:00');

-- Notices (data/notices.json)
INSERT INTO notices (notice_id, title, category, priority, summary, details, posted_by, faculty_id, published_on) VALUES
    (1, 'Mid-Semester Exam Schedule', 'Exam', 'high', 'Mid-semester examinations commence on September 1st, 2026. Hall tickets will be issued via the portal on August 25th.', 'All students must maintain 75% attendance to be eligible for hall tickets. Seating arrangements will be displayed on StudentHub 2 days prior to each exam.', 'Exam Cell', 4, '2026-08-18'),
    (2, 'Campus Placement Registration', 'Placement', 'medium', 'Final year students must complete their T&P portal registration before August 30th for upcoming recruitment drives.', 'Prepare your resumes in standard PDF format. Mock interview sessions will be held in Auditorium 2 starting August 22nd.', 'T&P Office', 4, '2026-08-16'),
    (3, 'Annual Sports Festival 2026', 'Event', 'low', 'Registrations are now open for inter-departmental sports events. Contact student coordinators for team submissions.', 'Matches will take place at the main university sports complex. Refreshments and certificates are provided to all participants.', 'Sports Committee', 4, '2026-08-12'),
    (4, 'Hall Ticket Download Window', 'Exam', 'high', 'Hall tickets for the mid-semester exams are now available for download from the student dashboard.', 'Carry a printed copy of your hall ticket and college ID card to every exam. Students with attendance shortage should contact their HOD immediately.', 'Exam Cell', 4, '2026-08-25'),
    (5, 'Ganesh Chaturthi Holiday', 'Holiday', 'low', 'The college will remain closed on September 14th on account of Ganesh Chaturthi.', 'Lectures scheduled for the day will be rescheduled. Updated timetables will be shared by respective departments.', 'Administration', 4, '2026-09-10'),
    (6, 'Assignment Submission Deadline Extended', 'Academic', 'medium', 'The deadline for pending Unit 2 assignments has been extended to August 31st.', 'Late submissions after the extended deadline will not be accepted. Upload your work through the Assignments page in PDF, ZIP or DOCX format.', 'Dean Academics', 4, '2026-08-27'),
    (7, 'TCS Recruitment Drive', 'Placement', 'high', 'TCS will conduct an on-campus recruitment drive for 2027 graduating students on September 18th.', 'Eligibility: 60% throughout with no active backlogs. Online aptitude test followed by technical and HR interviews.', 'T&P Office', 4, '2026-09-03'),
    (8, 'Library Timing Change', 'Academic', 'low', 'The central library will stay open until 9 PM on weekdays during the exam season.', 'The extended timings apply from August 20th to September 15th. Reading hall seats are first-come, first-served.', 'Central Library', 4, '2026-08-08'),
    (9, 'Semester Fee Payment Reminder', 'Academic', 'high', 'The last date for paying the odd-semester fee without a late fine is September 20th.', 'Fees can be paid online through the university payment gateway. Keep the transaction receipt for future reference.', 'Accounts Office', 4, '2026-09-01'),
    (10, 'Hackathon: Code for Campus', 'Event', 'medium', 'A 24-hour hackathon for teams of up to four students. Exciting prizes and internship offers for winners.', 'Register your team on the Coding Club portal before September 15th. Problem statements will be released at the opening ceremony.', 'Coding Club', 1, '2026-09-05'),
    (11, 'Navratri Vacation', 'Holiday', 'medium', 'Navratri vacation will be observed from October 11th to October 20th.', 'Hostels will remain open for students who wish to stay. The academic calendar has been adjusted accordingly.', 'Administration', 4, '2026-09-22'),
    (12, 'Re-Exam Form Submission', 'Exam', 'medium', 'Students who missed a mid-semester exam can apply for a re-exam until September 24th.', 'Applications must include a valid medical certificate or official leave approval. Forms are available in the Exam Cell.', 'Exam Cell', 4, '2026-09-14'),
    (13, 'Infosys Pre-Placement Talk', 'Placement', 'low', 'Infosys will hold a pre-placement talk in the Seminar Hall on September 12th at 11 AM.', 'Attendance is mandatory for all students who wish to apply for the Infosys recruitment drive.', 'T&P Office', 4, '2026-09-08'),
    (14, 'Guest Lecture on Cloud Computing', 'Event', 'low', 'An industry expert from AWS will deliver a guest lecture on modern cloud architecture.', 'The session will be held in Auditorium 1 on September 6th. Certificates of participation will be issued.', 'CE Department', 1, '2026-08-29');

-- Alert subscriptions
INSERT INTO notice_subscriptions (student_id, channel) VALUES
    (1, 'email');

-- Events (data/events.json; featured ones appear in the home carousel)
INSERT INTO events (event_id, title, event_type, description, event_date, start_time, venue, organizer, seats, is_team_event, max_team_size, registration_deadline, image_path, is_featured, created_by) VALUES
    (1, 'Code for Campus Hackathon', 'Technical', 'A 24-hour hackathon for teams of up to four students with prizes and internship offers.', '2026-10-17', '09:00:00', 'Innovation Lab', 'Coding Club', 120, TRUE, 4, '2026-10-17 00:00:00', 'images/update2.jpg', TRUE, 4),
    (2, 'Inter-Department Cricket League', 'Sports', 'T20 matches between department teams across a week-long league.', '2026-10-24', '08:00:00', 'Main Ground', 'Sports Committee', 200, TRUE, 15, '2026-10-24 00:00:00', 'images/update3.jpg', TRUE, 4),
    (3, 'Garba Night 2026', 'Cultural', 'Celebrate Navratri with traditional garba, live music and prizes for the best dressed.', '2026-10-15', '19:00:00', 'Open Air Theatre', 'Cultural Committee', 800, FALSE, NULL, '2026-10-15 00:00:00', NULL, FALSE, 4),
    (4, 'Web Development Bootcamp', 'Workshop', 'Hands-on workshop covering HTML, CSS, JavaScript and the Fetch API.', '2026-10-19', '10:00:00', 'Lab 204', 'CE Department', 60, FALSE, NULL, '2026-10-19 00:00:00', 'images/update1.jpg', TRUE, 4),
    (5, 'Cloud Computing Guest Lecture', 'Seminar', 'An AWS solutions architect explains modern cloud architecture and career paths.', '2026-09-06', '11:00:00', 'Auditorium 1', 'CE Department', 300, FALSE, NULL, '2026-09-06 00:00:00', NULL, FALSE, 4),
    (6, 'Robotics Expo', 'Technical', 'Student-built robots on display with live demos and a line-follower competition.', '2026-11-07', '10:00:00', 'Mechanical Block', 'Robotics Club', 250, FALSE, NULL, '2026-11-07 00:00:00', NULL, FALSE, 4),
    (7, 'Annual Athletics Meet', 'Sports', 'Track and field events including 100m, relay, long jump and shot put.', '2026-11-21', '07:30:00', 'Sports Complex', 'Sports Committee', 400, FALSE, NULL, '2026-11-21 00:00:00', NULL, FALSE, 4),
    (8, 'Resume Building Workshop', 'Workshop', 'Learn how to write an ATS-friendly resume with feedback from HR professionals.', '2026-10-28', '14:00:00', 'Seminar Hall', 'T&P Office', 150, FALSE, NULL, '2026-10-28 00:00:00', NULL, FALSE, 4),
    (9, 'Freshers'' Welcome Party', 'Cultural', 'Performances, games and the Mr. and Ms. Fresher competition.', '2026-08-30', '17:00:00', 'Auditorium 2', 'Student Council', 500, FALSE, NULL, '2026-08-30 00:00:00', NULL, FALSE, 4),
    (10, 'AI in Healthcare Seminar', 'Seminar', 'Researchers discuss how machine learning is changing diagnosis and patient care.', '2026-11-14', '11:30:00', 'Auditorium 1', 'IT Department', 300, FALSE, NULL, '2026-11-14 00:00:00', NULL, FALSE, 4),
    (11, 'Photography Walk', 'Cultural', 'A guided sunrise photo walk around the campus and nearby lake.', '2026-10-31', '06:30:00', 'Campus Gate 1', 'Photography Club', 40, FALSE, NULL, '2026-10-31 00:00:00', NULL, FALSE, 4),
    (12, 'Git & GitHub Workshop', 'Workshop', 'Version control fundamentals: commits, branches, pull requests and collaboration.', '2026-12-05', '10:00:00', 'Lab 101', 'Coding Club', 80, FALSE, NULL, '2026-12-05 00:00:00', NULL, FALSE, 4);

-- Event registrations
INSERT INTO event_registrations (event_id, student_id, team_name, team_size, status, registered_at) VALUES
    (4, 1, NULL, NULL, 'registered', '2026-10-02 14:20:00'),
    (1, 1, 'Byte Bandits', 4, 'registered', '2026-10-03 09:10:00'),
    (1, 3, 'Cloud Nine', 3, 'registered', '2026-10-03 12:45:00'),
    (3, 3, NULL, NULL, 'registered', '2026-10-04 18:00:00'),
    (9, 1, NULL, NULL, 'attended', '2026-08-25 10:00:00');

-- FAQs (data/faqs.json)
INSERT INTO faqs (faq_id, category, icon, question, answer, link_href, link_label, sort_order) VALUES
    (1, 'Account', 'fa-key', 'How do I reset or change my StudentHub password?', 'On the Login page click "Forgot Password?" and enter your registered student email to receive a 6-digit reset code. If you are already logged in, you can change your password from your profile settings.', 'login.html', 'Go to Login', 1),
    (2, 'Attendance', 'fa-user-check', 'How is attendance calculated and updated?', 'Faculty mark attendance daily. Your percentage is (Lectures Attended / Total Lectures Conducted) x 100. A minimum of 75% is required to appear in semester examinations.', 'attendance.html', 'Check Attendance', 2),
    (3, 'Assignments', 'fa-file-upload', 'What are the guidelines for uploading assignment solutions?', 'Upload assignments from the Assignments page. Accepted formats are PDF, ZIP and DOCX up to 25MB. Submit before the posted deadline.', 'assignments.html', 'Open Assignments', 3),
    (4, 'Materials', 'fa-book-open', 'Where can I download subject study materials and PDFs?', 'The Materials page has lecture slides, lab manuals, syllabus PDFs and previous question papers uploaded by faculty.', 'material.html', 'Browse Materials', 4),
    (5, 'Portal', 'fa-moon', 'How does the Light/Dark theme switcher work?', 'Click the Sun/Moon toggle at the top right of any page. Your choice is saved in localStorage so it stays the same across pages and visits.', NULL, NULL, 5),
    (6, 'Portal', 'fa-bars', 'How do I use the mobile navigation menu?', 'On phones and tablets tap the three-bar icon at the top right. The menu slides down with all links, and tapping outside or choosing a link closes it.', NULL, NULL, 6),
    (7, 'Support', 'fa-headset', 'How do I report a bug or contact academic support?', 'Use the Contact page to send a message, or email the IT helpdesk at studenthub@gmail.com.', 'contact.html', 'Contact Support', 7),
    (8, 'Account', 'fa-user-plus', 'How do I create a new StudentHub account?', 'Open the Register page and fill in your name, email, mobile number, course and year. Your password must have at least 8 characters with upper and lower case letters, a digit and a special character.', 'register.html', 'Register Now', 8),
    (9, 'Attendance', 'fa-exclamation-triangle', 'What happens if my attendance falls below 75%?', 'You will not be issued a hall ticket for the semester exam. Submit medical certificates or leave approvals to your HOD for condonation before the exam form deadline.', NULL, NULL, 9),
    (10, 'Assignments', 'fa-clock', 'Can I submit an assignment after the deadline?', 'Late submissions are only accepted when the faculty extends the deadline. Extensions are announced on the Notices page.', 'notices.html', 'View Notices', 10),
    (11, 'Materials', 'fa-file-pdf', 'Are previous year question papers available?', 'Yes. Question papers from the last five years are listed on the Materials page, grouped by subject and semester.', NULL, NULL, 11),
    (12, 'Account', 'fa-id-card', 'How do I update my profile details?', 'Go to your Profile page and edit your contact details. Changes to your name or enrolment number must be requested through the administration office.', 'profile.html', 'Open Profile', 12),
    (13, 'Support', 'fa-calendar-check', 'What are the helpdesk working hours?', 'The IT helpdesk is available Monday to Saturday, 9 AM to 5 PM. Emails received outside these hours are answered the next working day.', NULL, NULL, 13),
    (14, 'Portal', 'fa-bell', 'How do I get notified about new notices and events?', 'Click "Subscribe to Alerts" on the Notices page to receive daily email notifications for new circulars and events.', NULL, NULL, 14);

-- Contact messages
INSERT INTO contact_messages (ticket_no, student_id, name, email, subject, message, status, submitted_at) VALUES
    ('MSG-20261006-a1b2c3', 1, 'Sneh Shah', 'sneh.shah@university.edu', 'Attendance Discrepancy', 'My DBMS attendance for 3rd October shows absent but I attended the lab session.', 'open', '2026-10-06 16:40:00');

-- Audit log
INSERT INTO audit_logs (actor_type, actor_id, action, entity_type, entity_id, details, created_at) VALUES
    ('admin', 4, 'student.approve', 'students', 1, 'Approved registration 2026CS108', '2026-07-01 10:00:00'),
    ('admin', 4, 'student.approve', 'students', 3, 'Approved registration 2026IT204', '2026-07-01 10:05:00'),
    ('admin', 4, 'notice.create', 'notices', 1, 'Posted: Mid-Semester Exam Schedule', '2026-08-18 09:30:00');
