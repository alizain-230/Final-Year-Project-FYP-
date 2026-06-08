-- ============================================================
-- School Management System - Complete Database
-- Student: Ali Zain | Reg: 2022-ag-6178 | UAF TTS
-- ============================================================

CREATE DATABASE IF NOT EXISTS sms_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sms_db;

SET FOREIGN_KEY_CHECKS = 0;

-- TABLE 1: users (login accounts for all roles)
DROP TABLE IF EXISTS users;
CREATE TABLE users (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(150) NOT NULL,
  email      VARCHAR(150) NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  role       ENUM('admin','teacher','student','parent') NOT NULL,
  is_active  TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- TABLE 2: classes
DROP TABLE IF EXISTS classes;
CREATE TABLE classes (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(50) NOT NULL,
  section    VARCHAR(10) NOT NULL,
  room       VARCHAR(20) DEFAULT NULL,
  capacity   INT DEFAULT 40,
  UNIQUE KEY uq_class (name, section)
);

-- TABLE 3: subjects
DROP TABLE IF EXISTS subjects;
CREATE TABLE subjects (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(100) NOT NULL,
  code         VARCHAR(20) NOT NULL UNIQUE,
  class_id     INT NOT NULL,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
);

-- TABLE 4: teachers
DROP TABLE IF EXISTS teachers;
CREATE TABLE teachers (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL UNIQUE,
  emp_code      VARCHAR(30) NOT NULL UNIQUE,
  qualification VARCHAR(100),
  phone         VARCHAR(20),
  joined_date   DATE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- TABLE 5: parents
DROP TABLE IF EXISTS parents;
CREATE TABLE parents (
  id      INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  phone   VARCHAR(20),
  address TEXT,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- TABLE 6: students
DROP TABLE IF EXISTS students;
CREATE TABLE students (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL UNIQUE,
  roll_no    VARCHAR(30) NOT NULL UNIQUE,
  class_id   INT,
  parent_id  INT,
  dob        DATE,
  gender     ENUM('Male','Female','Other'),
  phone      VARCHAR(20),
  address    TEXT,
  fee_status ENUM('Paid','Pending','Partial') DEFAULT 'Pending',
  joined     DATE DEFAULT (CURRENT_DATE),
  FOREIGN KEY (user_id)  REFERENCES users(id)   ON DELETE CASCADE,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
  FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE SET NULL
);

-- TABLE 7: teacher_subjects (which teacher teaches what)
DROP TABLE IF EXISTS teacher_subjects;
CREATE TABLE teacher_subjects (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  subject_id INT NOT NULL,
  class_id   INT NOT NULL,
  UNIQUE KEY uq_ts (teacher_id, subject_id, class_id),
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE CASCADE
);

-- TABLE 8: attendance
DROP TABLE IF EXISTS attendance;
CREATE TABLE attendance (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  class_id   INT NOT NULL,
  date       DATE NOT NULL,
  status     ENUM('Present','Absent','Late','Leave') DEFAULT 'Present',
  marked_by  INT NOT NULL,
  UNIQUE KEY uq_att (student_id, date),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE CASCADE,
  FOREIGN KEY (marked_by)  REFERENCES users(id)
);

-- TABLE 9: exams
DROP TABLE IF EXISTS exams;
CREATE TABLE exams (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  type        ENUM('Quiz','Midterm','Final','Assignment','Practical') DEFAULT 'Midterm',
  class_id    INT NOT NULL,
  subject_id  INT NOT NULL,
  total_marks INT NOT NULL DEFAULT 100,
  pass_marks  INT NOT NULL DEFAULT 40,
  exam_date   DATE,
  created_by  INT NOT NULL,
  FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id)
);

-- TABLE 10: marks
DROP TABLE IF EXISTS marks;
CREATE TABLE marks (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  exam_id    INT NOT NULL,
  obtained   DECIMAL(6,2) DEFAULT 0,
  grade      VARCHAR(3),
  entered_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_marks (student_id, exam_id),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (exam_id)    REFERENCES exams(id)    ON DELETE CASCADE,
  FOREIGN KEY (entered_by) REFERENCES users(id)
);

-- TABLE 11: timetable
DROP TABLE IF EXISTS timetable;
CREATE TABLE timetable (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  class_id   INT NOT NULL,
  subject_id INT NOT NULL,
  teacher_id INT NOT NULL,
  day        ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  period     TINYINT NOT NULL,
  start_time TIME NOT NULL,
  end_time   TIME NOT NULL,
  room       VARCHAR(20),
  UNIQUE KEY uq_slot (class_id, day, period),
  FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
);

-- TABLE 12: assignments
DROP TABLE IF EXISTS assignments;
CREATE TABLE assignments (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(200) NOT NULL,
  description TEXT,
  teacher_id  INT NOT NULL,
  class_id    INT NOT NULL,
  subject_id  INT NOT NULL,
  file_name   VARCHAR(300),
  total_marks INT DEFAULT 10,
  due_date    DATE,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
  FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

-- TABLE 13: submissions
DROP TABLE IF EXISTS submissions;
CREATE TABLE submissions (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT NOT NULL,
  student_id    INT NOT NULL,
  file_name     VARCHAR(300),
  submitted_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status        ENUM('Submitted','Graded','Late') DEFAULT 'Submitted',
  obtained      DECIMAL(5,2),
  feedback      TEXT,
  UNIQUE KEY uq_sub (assignment_id, student_id),
  FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id)    REFERENCES students(id)    ON DELETE CASCADE
);

-- TABLE 14: notifications
DROP TABLE IF EXISTS notifications;
CREATE TABLE notifications (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  from_id   INT,
  to_id     INT NOT NULL,
  title     VARCHAR(200),
  message   TEXT NOT NULL,
  type      ENUM('info','success','warning','danger') DEFAULT 'info',
  is_read   TINYINT(1) DEFAULT 0,
  sent_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (to_id) REFERENCES users(id) ON DELETE CASCADE
);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SAMPLE DATA
-- All passwords = "password123"
-- Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- ============================================================

-- Admin
INSERT INTO users (name, email, password, role) VALUES
('Administrator', 'admin@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Classes
INSERT INTO classes (name, section, room, capacity) VALUES
('Grade 6', 'A', 'R-101', 40),
('Grade 7', 'A', 'R-102', 40),
('Grade 8', 'A', 'R-103', 40),
('Grade 9', 'A', 'R-104', 40),
('Grade 10','A', 'R-105', 40);

-- Subjects
INSERT INTO subjects (name, code, class_id) VALUES
('Mathematics', 'MATH-9', 4),
('Physics',     'PHY-9',  4),
('Chemistry',   'CHEM-9', 4),
('English',     'ENG-9',  4),
('Computer',    'COMP-9', 4),
('Mathematics', 'MATH-10',5),
('Physics',     'PHY-10', 5),
('Chemistry',   'CHEM-10',5),
('English',     'ENG-10', 5);

-- Teacher users
INSERT INTO users (name, email, password, role) VALUES
('Ahmed Khan',   'ahmed@school.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher'),
('Sara Malik',   'sara@school.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher'),
('Usman Ali',    'usman@school.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher');

INSERT INTO teachers (user_id, emp_code, qualification, phone, joined_date) VALUES
(2, 'EMP-001', 'M.Sc Mathematics', '0300-1234567', '2020-01-15'),
(3, 'EMP-002', 'M.Sc Physics',     '0301-2345678', '2019-08-01'),
(4, 'EMP-003', 'M.Sc Computer',    '0302-3456789', '2021-03-10');

-- Parent users
INSERT INTO users (name, email, password, role) VALUES
('Khalid Hussain', 'khalid@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'parent'),
('Rashid Ahmed',   'rashid@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'parent');

INSERT INTO parents (user_id, phone, address) VALUES
(5, '0311-1111111', 'House 12, Street 4, Faisalabad'),
(6, '0312-2222222', 'House 34, Block B, Toba Tek Singh');

-- Student users
INSERT INTO users (name, email, password, role) VALUES
('Ali Hassan',    'ali@school.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
('Fatima Malik',  'fatima@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
('Omar Sheikh',   'omar@school.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
('Zara Ahmed',    'zara@school.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student');

INSERT INTO students (user_id, roll_no, class_id, parent_id, dob, gender, phone, fee_status) VALUES
(7, '2024-001', 4, 1, '2009-05-10', 'Male',   '0320-1111111', 'Paid'),
(8, '2024-002', 4, 2, '2009-08-22', 'Female', '0321-2222222', 'Pending'),
(9, '2024-003', 4, 1, '2009-03-15', 'Male',   '0322-3333333', 'Paid'),
(10,'2024-004', 5, 2, '2008-11-30', 'Female', '0323-4444444', 'Partial');

-- Teacher subject assignments
INSERT INTO teacher_subjects (teacher_id, subject_id, class_id) VALUES
(1, 1, 4), (1, 6, 5),
(2, 2, 4), (2, 7, 5),
(3, 5, 4);

-- Timetable for Grade 9-A
INSERT INTO timetable (class_id, subject_id, teacher_id, day, period, start_time, end_time, room) VALUES
(4,1,1,'Monday',   1,'08:00:00','08:45:00','R-104'),
(4,2,2,'Monday',   2,'08:45:00','09:30:00','R-104'),
(4,5,3,'Monday',   3,'09:30:00','10:15:00','R-104'),
(4,1,1,'Tuesday',  1,'08:00:00','08:45:00','R-104'),
(4,3,2,'Tuesday',  2,'08:45:00','09:30:00','R-104'),
(4,4,1,'Wednesday',1,'08:00:00','08:45:00','R-104'),
(4,5,3,'Wednesday',2,'08:45:00','09:30:00','R-104'),
(4,2,2,'Thursday', 1,'08:00:00','08:45:00','R-104'),
(4,1,1,'Friday',   1,'08:00:00','08:45:00','R-104');

-- Exams
INSERT INTO exams (name, type, class_id, subject_id, total_marks, pass_marks, exam_date, created_by) VALUES
('Mid Term Mathematics', 'Midterm', 4, 1, 100, 40, '2026-03-15', 1),
('Mid Term Physics',     'Midterm', 4, 2, 100, 40, '2026-03-16', 1),
('Mid Term Computer',    'Midterm', 4, 5, 100, 40, '2026-03-17', 1),
('Quiz 1 Mathematics',   'Quiz',    4, 1,  20, 10, '2026-02-10', 1);

-- Sample marks
INSERT INTO marks (student_id, exam_id, obtained, grade, entered_by) VALUES
(1,1,85,'A', 2),(1,2,78,'B',3),(1,3,92,'A+',4),(1,4,17,'A+',2),
(2,1,55,'D', 2),(2,2,62,'C',3),(2,3,70,'B', 4),(2,4,12,'C', 2),
(3,1,91,'A+',2),(3,2,88,'A',3),(3,3,76,'B',4),(3,4,19,'A+',2);

-- Sample attendance (last 7 days)
INSERT INTO attendance (student_id, class_id, date, status, marked_by) VALUES
(1,4,DATE_SUB(CURDATE(),INTERVAL 6 DAY),'Present',2),
(2,4,DATE_SUB(CURDATE(),INTERVAL 6 DAY),'Present',2),
(3,4,DATE_SUB(CURDATE(),INTERVAL 6 DAY),'Absent', 2),
(1,4,DATE_SUB(CURDATE(),INTERVAL 5 DAY),'Present',2),
(2,4,DATE_SUB(CURDATE(),INTERVAL 5 DAY),'Absent', 2),
(3,4,DATE_SUB(CURDATE(),INTERVAL 5 DAY),'Present',2),
(1,4,DATE_SUB(CURDATE(),INTERVAL 4 DAY),'Present',2),
(2,4,DATE_SUB(CURDATE(),INTERVAL 4 DAY),'Late',   2),
(3,4,DATE_SUB(CURDATE(),INTERVAL 4 DAY),'Present',2),
(1,4,DATE_SUB(CURDATE(),INTERVAL 3 DAY),'Present',2),
(2,4,DATE_SUB(CURDATE(),INTERVAL 3 DAY),'Present',2),
(3,4,DATE_SUB(CURDATE(),INTERVAL 3 DAY),'Present',2),
(1,4,DATE_SUB(CURDATE(),INTERVAL 2 DAY),'Absent', 2),
(2,4,DATE_SUB(CURDATE(),INTERVAL 2 DAY),'Present',2),
(3,4,DATE_SUB(CURDATE(),INTERVAL 2 DAY),'Present',2),
(1,4,DATE_SUB(CURDATE(),INTERVAL 1 DAY),'Present',2),
(2,4,DATE_SUB(CURDATE(),INTERVAL 1 DAY),'Present',2),
(3,4,DATE_SUB(CURDATE(),INTERVAL 1 DAY),'Late',   2);

-- Notifications
INSERT INTO notifications (from_id, to_id, title, message, type) VALUES
(1,7,'Welcome','Welcome to School Management System. Your account is ready.','success'),
(1,8,'Fee Reminder','Your fee is pending. Please pay at accounts office.','warning'),
(1,5,'Result Published','Mid term results have been published. Login to check.','info');
