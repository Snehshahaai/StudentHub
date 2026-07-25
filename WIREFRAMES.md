# StudentHub Low-Fidelity Wireframes & Layout Specs

This document defines the **Low-Fidelity Blueprint Specifications** and page structural layouts for the **StudentHub** portal.

> [!NOTE]
> StudentHub includes a built-in **Interactive Wireframe Toggle** in the web interface header/footer (`Wireframe Mode`). Toggling Wireframe Mode switches the application's visual styling to a grayscale grid layout with handwritten-style typography, structural borders, element placeholder labels, and simplified box diagrams.

---

## 1. Low-Fidelity Design System Tokens

```
+-------------------------------------------------------------------+
|  TYPOGRAPHY : Monospace / Blueprint Sans ('Courier', 'Consolas')   |
|  COLOR SCHEME: Grayscale (#ffffff background, #000000 borders)    |
|  BORDER STYLE: 2px solid black / dashed grid boundaries            |
|  IMAGES      : Placeholder boxes with [X] cross-lines             |
|  BUTTONS     : Square outline boxes with uppercase labels          |
+-------------------------------------------------------------------+
```

---

## 2. Low-Fidelity Page Layout Blueprints

### 2.1 Student Dashboard Layout (`dashboard.html`)

```
+------------------------------------------------------------------------------+
| [LOGO] StudentHub    [Home] [Dashboard] [Courses] [Assignments] ... [PROFILE]|
+------------------------------------------------------------------------------+
| WELCOME BACK, ALEX (STUDENT)                     [ TOGGLE WIREFRAME MODE ]   |
| Semester: Fall 2026 | Major: Computer Science                          |
+------------------------------------------------------------------------------+
| +-------------------------+ +-------------------------+ +------------------+ |
| | ATTENDANCE: 92%         | | CURRENT GPA: 3.85       | | PENDING TASKS: 3 | |
| +-------------------------+ +-------------------------+ +------------------+ |
+------------------------------------------------------------------------------+
| ENROLLED COURSES                           | UPCOMING DEADLINES             |
| +----------------------------------------+ | +----------------------------+ |
| | [COURSE CARD 1: CS101 Web Dev]         | | | [!] Assignment 3: CSS Grid | |
| | Progress: 75% | Instructor: Dr. Roy    | | | Due: Oct 28 | CS101        | |
| +----------------------------------------+ | +----------------------------+ |
| | [COURSE CARD 2: CS202 Algorithms]      | | | [!] Midterm Exam Review    | |
| | Progress: 60% | Instructor: Prof. Chen | | | Due: Nov 02 | CS202        | |
| +----------------------------------------+ | +----------------------------+ |
+------------------------------------------------------------------------------+
| RECENT NOTICES & ANNOUNCEMENTS                                               |
| - [URGENT] Mid-Semester Exam Schedule Published                              |
| - [INFO] Hackathon Registration Open for Fall 2026                           |
+------------------------------------------------------------------------------+
| FOOTER: [Sitemap] [Helpdesk] [Privacy]             (C) 2026 StudentHub Project|
+------------------------------------------------------------------------------+
```

---

### 2.2 Course Directory Layout (`courses.html`)

```
+------------------------------------------------------------------------------+
| HEADER NAVIGATION                                                            |
+------------------------------------------------------------------------------+
| COURSES DIRECTORY                                                            |
| Search: [ Search course name... ]   Filter: [ Semester 1 | Semester 2 | All ] |
+------------------------------------------------------------------------------+
| +--------------------------+ +--------------------------+ +----------------+ |
| | [IMG PLACEHOLDER: CS101] | | [IMG PLACEHOLDER: CS202] | | [IMG: CS303]   | |
| | CS101: Web Development   | | CS202: Data Structures | | CS303: Database| |
| | Instructor: Dr. A. Roy   | | Instructor: Prof. Chen   | | Instructor: Lee| |
| | Credits: 4.0             | | Credits: 4.0             | | Credits: 3.0   | |
| | [ VIEW SYLLABUS ]        | | [ VIEW SYLLABUS ]        | | [ VIEW LABS ]  | |
| +--------------------------+ +--------------------------+ +----------------+ |
+------------------------------------------------------------------------------+
```

---

### 2.3 Assignment Hub & Submission (`assignments.html`)

```
+------------------------------------------------------------------------------+
| ASSIGNMENTS & SUBMISSIONS                                                    |
+------------------------------------------------------------------------------+
| ACTIVE ASSIGNMENTS (3 PENDING, 12 COMPLETED)                                 |
| +--------------------------------------------------------------------------+ |
| | Assignment Title: Project Wireframe & Architecture Specification          | |
| | Course: CS101 Web Development | Due Date: Oct 28, 2026 (In 2 days)      | |
| | Description: Submit project sitemap, wireframes, folder structure & code. | |
| | Status: [ PENDING ]                                                      | |
| | Action: [ UPLOAD FILE SIMULATOR ]   [ SUBMIT ASSIGNMENT ]                | |
| +--------------------------------------------------------------------------+ |
+------------------------------------------------------------------------------+
```

---

### 2.4 Digital Library & Resources (`resources.html`)

```
+------------------------------------------------------------------------------+
| DIGITAL RESOURCE HUB & PAST PAPERS                                           |
+------------------------------------------------------------------------------+
| Search: [ Enter subject keyword / topic... ]  Category: [ Notes | Exam Papers]|
+------------------------------------------------------------------------------+
| TITLE                        | CATEGORY    | UPLOADED BY  | ACTION           |
| -----------------------------+-------------+--------------+------------------|
| Midterm Exam 2025 Solved     | Exam Paper  | Dr. A. Roy   | [ DOWNLOAD PDF ] |
| CSS Grid & Flexbox Handbook  | Study Guide | Tech Club    | [ DOWNLOAD PDF ] |
| SQL Query Reference Sheet    | Cheatsheet  | Prof. Lee    | [ VIEW ONLINE ]  |
+------------------------------------------------------------------------------+
```

---

### 2.5 Admin & Faculty Control Portal (`admin.html`)

```
+------------------------------------------------------------------------------+
| INSTITUTIONAL ADMIN & FACULTY DASHBOARD                                      |
+------------------------------------------------------------------------------+
| STATS: [Total Students: 1,420] [Courses: 34] [Pending Approvals: 5]          |
+------------------------------------------------------------------------------+
| ACTIONS: [ + CREATE NEW COURSE ] [ + PUBLISH NOTICE ] [ + APPROVE EVENT ]    |
+------------------------------------------------------------------------------+
| PENDING APPROVAL QUEUE                                                       |
| - Student Club: Robotics Guild Event Request -> [ APPROVE ] [ REJECT ]       |
| - New Faculty: Dr. Sarah Vance Account Verification -> [ VERIFY ROLE ]       |
+------------------------------------------------------------------------------+
```
