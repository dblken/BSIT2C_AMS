-- Create the database
DROP DATABASE IF EXISTS attendance_system;
CREATE DATABASE attendance_system;
USE attendance_system;

-- Drop existing tables if they exist
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS student_subjects;
DROP TABLE IF EXISTS timetable;
DROP TABLE IF EXISTS subjects;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS teachers;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS attendance_logs;
SET FOREIGN_KEY_CHECKS = 1;

-- Create users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create admins table
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    admin_id VARCHAR(20) UNIQUE NOT NULL COMMENT 'Employee ID number',
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    date_of_birth DATE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone_number VARCHAR(20),
    address TEXT,
    department VARCHAR(100) NOT NULL,
    designation VARCHAR(100) NOT NULL,
    profile_picture VARCHAR(255),
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create teachers table
CREATE TABLE teachers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    teacher_id VARCHAR(20) UNIQUE NOT NULL COMMENT 'Employee ID number',
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    date_of_birth DATE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone_number VARCHAR(20),
    address TEXT,
    department VARCHAR(100) NOT NULL,
    designation ENUM('Full-time', 'Part-time') NOT NULL,
    profile_picture VARCHAR(255),
    status ENUM('Active', 'Inactive', 'On Leave') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create students table
CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    student_id VARCHAR(20) UNIQUE NOT NULL COMMENT 'School ID number',
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    date_of_birth DATE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone_number VARCHAR(20),
    address TEXT,
    program VARCHAR(50) NOT NULL DEFAULT 'BSIT',
    year_level INT NOT NULL,
    section VARCHAR(10) DEFAULT 'Block C',
    profile_picture VARCHAR(255),
    status ENUM('Active', 'Inactive', 'Graduate', 'LOA') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create subjects table
CREATE TABLE subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject_code VARCHAR(20) NOT NULL UNIQUE,
    subject_name VARCHAR(100) NOT NULL,
    description TEXT,
    units INT NOT NULL,
    semester ENUM('First', 'Second', 'Summer') NOT NULL,
    school_year VARCHAR(9) NOT NULL COMMENT 'Format: 2023-2024',
    teacher_id INT,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
);

-- Create student_subjects table
CREATE TABLE student_subjects (
    student_id INT,
    subject_id INT,
    enrollment_date DATE DEFAULT (CURRENT_DATE),
    status ENUM('Enrolled', 'Dropped', 'Completed') DEFAULT 'Enrolled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id, subject_id),
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
);

-- Create attendance table
CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    subject_id INT,
    date DATE NOT NULL,
    time_in TIME,
    time_out TIME,
    status ENUM('Present', 'Absent', 'Late', 'Excused') NOT NULL,
    remarks TEXT,
    marked_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    FOREIGN KEY (marked_by) REFERENCES teachers(id)
);

-- Create attendance_logs table
CREATE TABLE attendance_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    attendance_id INT NOT NULL,
    log_type ENUM('override', 'status_update', 'modification') NOT NULL,
    log_message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attendance_id) REFERENCES attendance(id) ON DELETE CASCADE
);

-- Create timetable table
CREATE TABLE timetable (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject_id INT,
    teacher_id INT,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(50) NOT NULL,
    status ENUM('Active', 'Cancelled', 'Rescheduled') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
);

-- Create indexes for better performance
CREATE INDEX idx_student_id ON students(student_id);
CREATE INDEX idx_teacher_id ON teachers(teacher_id);
CREATE INDEX idx_subject_code ON subjects(subject_code);
CREATE INDEX idx_attendance_date ON attendance(date);
CREATE INDEX idx_timetable_day ON timetable(day_of_week);

-- Insert initial admin account (password: admin123)
INSERT INTO users (username, password) 
VALUES ('admin', '$2y$10$8s1K0QDZ9PKXaF6AD3FE3.PXfHaZwZqOHqvQHq8THt50.wlrqm2Uy');

-- Insert admin details
INSERT INTO admins (
    user_id, 
    admin_id, 
    first_name, 
    middle_name, 
    last_name, 
    gender, 
    date_of_birth, 
    email, 
    phone_number, 
    department, 
    designation
) VALUES (
    1,
    'A001',
    'System',
    'Super',
    'Admin',
    'Male',
    '1990-01-01',
    'admin@bsit2c.com',
    '9876543210',
    'IT Department',
    'System Administrator'
);

-- Insert sample teacher account (password: teacher123)
INSERT INTO users (username, password) 
VALUES ('teacher1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Insert teacher details
INSERT INTO teachers (
    user_id, 
    teacher_id, 
    first_name, 
    middle_name, 
    last_name, 
    gender, 
    date_of_birth, 
    email, 
    phone_number, 
    department, 
    designation
) VALUES (
    2,
    'T001',
    'John',
    'M',
    'Doe',
    'Male',
    '1980-01-01',
    'john.doe@email.com',
    '1234567890',
    'IT Department',
    'Full-time'
);

-- Insert sample student account (password: student123)
INSERT INTO users (username, password) 
VALUES ('student1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Insert student details
INSERT INTO students (
    user_id, 
    student_id, 
    first_name, 
    middle_name, 
    last_name, 
    gender, 
    date_of_birth, 
    email, 
    phone_number, 
    program, 
    year_level
) VALUES (
    3,
    '2021-0001',
    'Jane',
    'A',
    'Smith',
    'Female',
    '2000-03-15',
    'jane.smith@email.com',
    '1112223333',
    'BSIT',
    2
);

-- Insert sample subject
INSERT INTO subjects (
    subject_code, 
    subject_name, 
    description, 
    units, 
    semester, 
    school_year, 
    teacher_id
) VALUES (
    'IT101',
    'Introduction to Programming',
    'Basic programming concepts and problem-solving techniques',
    3,
    'First',
    '2023-2024',
    1
);

-- Enroll student in subject
INSERT INTO student_subjects (student_id, subject_id) 
VALUES (1, 1);

-- Insert sample timetable
INSERT INTO timetable (
    subject_id, 
    teacher_id, 
    day_of_week, 
    start_time, 
    end_time, 
    room
) VALUES (
    1,
    1,
    'Monday',
    '08:00:00',
    '09:30:00',
    'Room 401'
); 