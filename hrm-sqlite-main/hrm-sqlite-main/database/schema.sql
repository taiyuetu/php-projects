-- SQLite schema and seed data for HRMS.

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS departments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS employees (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_code TEXT NOT NULL UNIQUE,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    phone TEXT,
    gender TEXT CHECK (gender IN ('Male', 'Female', 'Other') OR gender IS NULL),
    dob TEXT,
    address TEXT,
    department_id INTEGER,
    designation TEXT,
    hire_date TEXT,
    salary NUMERIC NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'Active' CHECK (status IN ('Active', 'Inactive', 'Terminated')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'employee' CHECK (role IN ('admin', 'hr', 'employee')),
    status TEXT NOT NULL DEFAULT 'Active' CHECK (status IN ('Active', 'Inactive')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS attendance (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL,
    attendance_date TEXT NOT NULL,
    check_in TEXT,
    check_out TEXT,
    status TEXT NOT NULL DEFAULT 'Present' CHECK (status IN ('Present', 'Absent', 'Half Day', 'Leave')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (employee_id, attendance_date),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS leave_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL,
    leave_type TEXT NOT NULL DEFAULT 'Casual' CHECK (leave_type IN ('Sick', 'Casual', 'Annual', 'Unpaid', 'Other')),
    start_date TEXT NOT NULL,
    end_date TEXT NOT NULL,
    reason TEXT,
    status TEXT NOT NULL DEFAULT 'Pending' CHECK (status IN ('Pending', 'Approved', 'Rejected')),
    approved_by INTEGER,
    applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS payroll (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL,
    month INTEGER NOT NULL CHECK (month BETWEEN 1 AND 12),
    year INTEGER NOT NULL,
    basic_salary NUMERIC NOT NULL DEFAULT 0,
    allowances NUMERIC NOT NULL DEFAULT 0,
    deductions NUMERIC NOT NULL DEFAULT 0,
    net_salary NUMERIC NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'Pending' CHECK (status IN ('Pending', 'Paid')),
    generated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (employee_id, month, year),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS onboarding (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL,
    start_date TEXT NOT NULL,
    target_completion_date TEXT,
    status TEXT NOT NULL DEFAULT 'In Progress' CHECK (status IN ('Pending', 'In Progress', 'Completed')),
    notes TEXT,
    completed_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS onboarding_tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    onboarding_id INTEGER NOT NULL,
    task_name TEXT NOT NULL,
    description TEXT,
    is_completed INTEGER NOT NULL DEFAULT 0 CHECK (is_completed IN (0, 1)),
    completed_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (onboarding_id) REFERENCES onboarding(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS offboarding (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL,
    exit_date TEXT NOT NULL,
    reason TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'In Progress' CHECK (status IN ('Pending', 'In Progress', 'Completed')),
    notes TEXT,
    completed_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS offboarding_tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    offboarding_id INTEGER NOT NULL,
    task_name TEXT NOT NULL,
    description TEXT,
    is_completed INTEGER NOT NULL DEFAULT 0 CHECK (is_completed IN (0, 1)),
    completed_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (offboarding_id) REFERENCES offboarding(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS offboarding_employees (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER,
    employee_code TEXT NOT NULL,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    email TEXT NOT NULL,
    phone TEXT,
    department_name TEXT,
    designation TEXT,
    hire_date TEXT,
    exit_date TEXT NOT NULL,
    reason TEXT NOT NULL,
    final_settlement_status TEXT DEFAULT 'Completed',
    notes TEXT,
    completed_tasks_summary TEXT,
    offboarded_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    offboarded_by INTEGER,
    FOREIGN KEY (offboarded_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS job_openings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    department_id INTEGER,
    employment_type TEXT NOT NULL DEFAULT 'Full-Time' CHECK (employment_type IN ('Full-Time', 'Part-Time', 'Contract', 'Internship', 'Remote')),
    openings_count INTEGER NOT NULL DEFAULT 1,
    location TEXT DEFAULT 'Singapore',
    salary_range TEXT,
    description TEXT,
    requirements TEXT,
    status TEXT NOT NULL DEFAULT 'Open' CHECK (status IN ('Open', 'Paused', 'Closed')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS candidates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    job_id INTEGER NOT NULL,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    email TEXT NOT NULL,
    phone TEXT,
    resume_url TEXT,
    linkedin_url TEXT,
    experience_years NUMERIC DEFAULT 0,
    current_company TEXT,
    stage TEXT NOT NULL DEFAULT 'Applied' CHECK (stage IN ('Applied', 'Screening', 'Interview', 'Offered', 'Hired', 'Rejected')),
    rating INTEGER DEFAULT 0 CHECK (rating BETWEEN 0 AND 5),
    notes TEXT,
    applied_date TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    hired_employee_id INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES job_openings(id) ON DELETE CASCADE,
    FOREIGN KEY (hired_employee_id) REFERENCES employees(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS candidate_notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    candidate_id INTEGER NOT NULL,
    user_id INTEGER,
    stage TEXT NOT NULL,
    note TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO departments (name, description) VALUES
('Human Resources', 'Handles recruitment, onboarding and staff welfare'),
('Engineering', 'Product development and technical operations'),
('Finance', 'Accounting, budgeting and payroll'),
('Sales', 'Business development and client relations');

INSERT INTO employees (employee_code, first_name, last_name, email, phone, gender, dob, address, department_id, designation, hire_date, salary, status) VALUES
('EMP-0001', 'Alice', 'Tan', 'alice.tan@example.com', '+65 8123 4567', 'Female', '1990-05-14', '12 Orchard Rd, Singapore', 1, 'HR Manager', '2021-01-10', 6500.00, 'Active'),
('EMP-0002', 'Brandon', 'Lee', 'brandon.lee@example.com', '+65 8123 4568', 'Male', '1993-08-22', '5 Marina Blvd, Singapore', 2, 'Software Engineer', '2022-03-01', 5800.00, 'Active'),
('EMP-0003', 'Chloe', 'Ng', 'chloe.ng@example.com', '+65 8123 4569', 'Female', '1995-11-02', '88 Tampines Ave, Singapore', 3, 'Accountant', '2020-07-19', 5200.00, 'Active');

-- Password for all seeded accounts: password123
INSERT INTO users (employee_id, username, password, role) VALUES
(NULL, 'admin', '$2y$10$lwm6xguPOh1MYRJROx/obOFVE.BaMdsVbvXh.M9ttiyoTYvf1LLvu', 'admin'),
(1, 'alice.tan', '$2y$10$lwm6xguPOh1MYRJROx/obOFVE.BaMdsVbvXh.M9ttiyoTYvf1LLvu', 'hr'),
(2, 'brandon.lee', '$2y$10$lwm6xguPOh1MYRJROx/obOFVE.BaMdsVbvXh.M9ttiyoTYvf1LLvu', 'employee');

INSERT INTO job_openings (id, title, department_id, employment_type, openings_count, location, salary_range, description, requirements, status) VALUES
(1, 'Senior Full Stack Developer', 2, 'Full-Time', 2, 'Singapore (Hybrid)', '$7,000 - $9,500', 'Lead the development of scalable web platforms, APIs, and enterprise cloud solutions.', '5+ years experience with PHP/Laravel, modern JavaScript (Vue/React), SQLite/MySQL, and Docker.', 'Open'),
(2, 'Talent Acquisition Specialist', 1, 'Full-Time', 1, 'Singapore', '$4,500 - $6,000', 'Oversee end-to-end recruitment lifecycle and candidate experience across APAC.', '3+ years HR & recruitment experience in tech or corporate staffing.', 'Open'),
(3, 'Financial Analyst', 3, 'Full-Time', 1, 'Singapore', '$5,000 - $7,000', 'Conduct corporate financial modeling, budget forecasting, and monthly reconciliations.', 'Degree in Finance/Accountancy, CPA or CFA preferred, advanced Excel skills.', 'Open');

INSERT INTO candidates (id, job_id, first_name, last_name, email, phone, resume_url, linkedin_url, experience_years, current_company, stage, rating, notes, applied_date) VALUES
(1, 1, 'Lucas', 'Vance', 'lucas.vance@example.com', '+65 9123 7788', 'https://example.com/resumes/lucas-vance.pdf', 'https://linkedin.com/in/lucasvance', 6, 'CloudMatrix Pte Ltd', 'Interview', 4, 'Strong architectural fundamentals, passed technical coding challenge with flying colors.', '2026-09-10 09:30:00'),
(2, 1, 'Seraphina', 'Lin', 'seraphina.lin@example.com', '+65 9234 5566', 'https://example.com/resumes/seraphina-lin.pdf', 'https://linkedin.com/in/seraphinalin', 4.5, 'Nexus Systems', 'Screening', 3, 'Good backend background, screening call scheduled for Thursday.', '2026-09-12 14:15:00'),
(3, 2, 'Marcus', 'Goh', 'marcus.goh@example.com', '+65 8345 6677', 'https://example.com/resumes/marcus-goh.pdf', 'https://linkedin.com/in/marcusgoh', 5, 'TalentBridge APAC', 'Offered', 5, 'Outstanding recruitment track record, offer letter prepared.', '2026-09-08 11:00:00');

INSERT INTO candidate_notes (candidate_id, user_id, stage, note, created_at) VALUES
(1, 1, 'Applied', 'Candidate applied via career portal.', '2026-09-10 09:30:00'),
(1, 1, 'Interview', 'Completed technical round with Engineering Lead. Recommended for cultural fit round.', '2026-09-12 16:00:00'),
(3, 1, 'Applied', 'Profile received via referral.', '2026-09-08 11:00:00'),
(3, 1, 'Offered', 'Offer approved by management ($5,800/mo). Awaiting candidate acceptance.', '2026-09-14 15:30:00');
