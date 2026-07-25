/* ==========================================================================
   StudentHub — Data Repository & Mock State Database
   ========================================================================== */

const StudentHubData = {
  // Current active session state
  currentUser: {
    name: "Alex Mercer",
    role: "student", // 'student', 'faculty', 'admin'
    id: "STU-2026-8891",
    email: "alex.mercer@studenthub.edu",
    major: "Computer Science & Engineering",
    semester: "Semester 5 (Fall 2026)",
    gpa: "3.85",
    attendance: "92%",
    avatar: "AM"
  },

  // Courses Dataset
  courses: [
    {
      id: "CS101",
      title: "Full-Stack Web Development",
      code: "CS-501",
      instructor: "Dr. Alan Roy",
      credits: 4.0,
      semester: "Fall 2026",
      progress: 75,
      attendance: 95,
      nextClass: "Mon 10:00 AM",
      syllabus: ["HTML5 & CSS Grid", "JavaScript ES6+", "REST APIs", "Node.js & Express", "Database Integration"],
      description: "Comprehensive study of modern web application development, front-end design systems, responsive UI/UX, and server-side integration.",
      badge: "Core Requirement"
    },
    {
      id: "CS202",
      title: "Data Structures & Algorithms",
      code: "CS-502",
      instructor: "Prof. Clara Chen",
      credits: 4.0,
      semester: "Fall 2026",
      progress: 60,
      attendance: 88,
      nextClass: "Tue 02:00 PM",
      syllabus: ["Trees & Graphs", "Dynamic Programming", "Sorting & Searching", "Complexity Analysis"],
      description: "Advanced data structures, algorithmic design patterns, time-space complexity optimization, and problem-solving techniques.",
      badge: "Core Requirement"
    },
    {
      id: "CS303",
      title: "Database Management Systems",
      code: "CS-503",
      instructor: "Dr. Marcus Vance",
      credits: 3.0,
      semester: "Fall 2026",
      progress: 82,
      attendance: 94,
      nextClass: "Wed 11:30 AM",
      syllabus: ["Relational Algebra", "SQL Queries & Indexing", "Transactions & ACID", "NoSQL Foundations"],
      description: "Relational database architecture, normalized schema design, SQL query tuning, indexing strategies, and transaction management.",
      badge: "Elective"
    },
    {
      id: "CS404",
      title: "Computer Networks & Security",
      code: "CS-504",
      instructor: "Prof. Ethan Wright",
      credits: 3.5,
      semester: "Fall 2026",
      progress: 45,
      attendance: 90,
      nextClass: "Thu 09:00 AM",
      syllabus: ["OSI & TCP/IP Model", "IP Routing Protocols", "Socket Programming", "Cryptography Basics"],
      description: "Network layer protocols, packet routing, socket client-server programming, threat defense, and network security fundamentals.",
      badge: "Core Requirement"
    }
  ],

  // Assignments Dataset
  assignments: [
    {
      id: "ASG-101",
      courseId: "CS101",
      title: "StudentHub Portal Wireframes & Architecture",
      dueDate: "2026-10-28",
      daysLeft: 2,
      totalPoints: 100,
      status: "Pending", // 'Pending', 'Submitted', 'Graded'
      grade: null,
      description: "Create sitemap, low-fidelity wireframes, project directory structure, README documentation, and initial codebase for StudentHub.",
      feedback: ""
    },
    {
      id: "ASG-102",
      courseId: "CS202",
      title: "Binary Search Tree & Graph Traversal Implementation",
      dueDate: "2026-11-02",
      daysLeft: 7,
      totalPoints: 50,
      status: "Pending",
      grade: null,
      description: "Implement BFS, DFS, Dijkstra algorithm in C++ or Python with unit test validation.",
      feedback: ""
    },
    {
      id: "ASG-103",
      courseId: "CS303",
      title: "E-Commerce Database Schema Design & SQL Queries",
      dueDate: "2026-10-20",
      daysLeft: 0,
      totalPoints: 100,
      status: "Graded",
      grade: "96 / 100",
      description: "Design 3NF normalized schema for e-commerce store and execute complex JOIN queries.",
      feedback: "Excellent normalization and query optimization!"
    }
  ],

  // Resources Repository
  resources: [
    {
      id: "RES-1",
      title: "CS101 Midterm Past Exam Paper (2025)",
      course: "CS101 Web Development",
      category: "Exam Paper",
      fileType: "PDF",
      size: "2.4 MB",
      uploader: "Dr. Alan Roy",
      date: "2026-09-15"
    },
    {
      id: "RES-2",
      title: "CSS Grid & Flexbox Complete Architecture CheatSheet",
      course: "CS101 Web Development",
      category: "Study Guide",
      fileType: "PDF",
      size: "1.1 MB",
      uploader: "Tech Club Lead",
      date: "2026-10-01"
    },
    {
      id: "RES-3",
      title: "Data Structures - Graph Algorithms Reference Manual",
      course: "CS202 Algorithms",
      category: "Lecture Notes",
      fileType: "PDF",
      size: "4.8 MB",
      uploader: "Prof. Clara Chen",
      date: "2026-10-12"
    },
    {
      id: "RES-4",
      title: "SQL Query CheatSheet & Indexing Best Practices",
      course: "CS303 DBMS",
      category: "Cheatsheet",
      fileType: "PDF",
      size: "850 KB",
      uploader: "Dr. Marcus Vance",
      date: "2026-08-30"
    }
  ],

  // Events & Clubs
  events: [
    {
      id: "EVT-1",
      title: "Fall 2026 Hackathon & AI Showcase",
      organizer: "ACM Student Chapter",
      date: "Nov 12, 2026",
      time: "09:00 AM - 06:00 PM",
      location: "Main Auditorium & Innovation Lab",
      category: "Hackathon",
      rsvpd: true,
      attendees: 142,
      description: "24-hour coding challenge building AI-powered web solutions. Cash prizes, mentor sessions, and networking."
    },
    {
      id: "EVT-2",
      title: "Workshop: Building Scalable REST APIs with Node.js",
      organizer: "Web Dev Club",
      date: "Oct 30, 2026",
      time: "04:00 PM - 06:00 PM",
      location: "Lab 3B",
      category: "Workshop",
      rsvpd: false,
      attendees: 48,
      description: "Hands-on coding workshop covering API routing, middleware, JWT auth, and database connections."
    },
    {
      id: "EVT-3",
      title: "Annual Campus Cultural Festival 'Aura 2026'",
      organizer: "Student Council",
      date: "Dec 05, 2026",
      time: "10:00 AM - 10:00 PM",
      location: "Campus Grounds",
      category: "Cultural",
      rsvpd: true,
      attendees: 650,
      description: "Music performances, food stalls, drama competitions, and art exhibitions across campus."
    }
  ],

  // Q&A Forum Threads
  forumThreads: [
    {
      id: "TH-1",
      title: "How to fix CSS Grid auto-fit overflow issue on mobile devices?",
      author: "Alex Mercer",
      course: "CS101 Web Development",
      tags: ["CSS", "WebDev", "Responsive"],
      upvotes: 14,
      replies: 4,
      isSolved: true,
      date: "2 hours ago",
      content: "I am using `grid-template-columns: repeat(auto-fit, minmax(300px, 1fr))` but on small screens under 320px it creates a horizontal scrollbar. What is the standard fix?"
    },
    {
      id: "TH-2",
      title: "Difference between BFS and Dijkstra algorithm shortest path guarantee?",
      author: "Samantha Reed",
      course: "CS202 Algorithms",
      tags: ["Algorithms", "Graph", "C++"],
      upvotes: 9,
      replies: 2,
      isSolved: false,
      date: "1 day ago",
      content: "Does BFS guarantee shortest path only when all edge weights are equal to 1? Why can't we use BFS for weighted graphs?"
    }
  ],

  // Notice Board Announcements
  notices: [
    {
      id: "NOT-1",
      title: "Mid-Semester Examination Schedule Published for Fall 2026",
      category: "Exam",
      urgency: "Urgent",
      date: "2026-10-24",
      publisher: "Office of Academic Controller",
      content: "The mid-semester examination timetable for all undergraduate programs has been finalized. Midterms will commence from November 10, 2026."
    },
    {
      id: "NOT-2",
      title: "Campus Library Extended Hours During Exam Season",
      category: "Academic",
      urgency: "Info",
      date: "2026-10-22",
      publisher: "Central Library Administration",
      content: "The Central Library will remain open 24/7 starting November 1st through November 20th to assist student preparation."
    },
    {
      id: "NOT-3",
      title: "Submissions Open for Annual Student Research Journal",
      category: "Research",
      urgency: "Info",
      date: "2026-10-18",
      publisher: "Department of Computer Science",
      content: "Undergraduate and graduate research paper submissions are now open for the 2026 edition of the Campus Technical Journal."
    }
  ]
};
