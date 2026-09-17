# HRMS

**Version:** 1.1.0

Small PHP/SQLite human-resource management system with employee, department,
attendance, leave, payroll, onboarding, offboarding, and recruitment workflows.

## Run locally

1. SQLite is stored in `database/hrms.sqlite` and is initialized automatically on
   the first request. No database server is required. The full schema (and seed
   data) lives in `database/schema.sql`.
2. Serve the `public` directory with the front controller as the built-in-server
   router:

   ```text
   php -S 127.0.0.1:3333 -t public public/index.php
   ```

   Then open `http://127.0.0.1:3333/auth/login`.
3. Sign in with `admin / password123` from the seed data.

## Features

- **Dashboard** — headcount, department, attendance, and leave summaries.
- **Employees** — create, edit, and browse employee records, including ID number,
  ethnicity, household address, education, and emergency contacts. Age is derived
  automatically from the date of birth. A detailed profile page shows personal,
  work, attendance, and leave information.
- **Departments** — manage company departments.
- **Attendance** — daily check-in / check-out records with status tracking.
- **Leave** — request, approve, and reject leave applications.
- **Payroll** — generate and track monthly salary records.
- **Onboarding** — register new hires and manage their onboarding checklist;
  the list shows only in-progress onboardings.
- **QR-code onboarding** — a scannable QR code links new hires to a public,
  mobile-friendly registration form; submissions are listed for HR review and
  can be approved (which starts the onboarding workflow) or rejected.
- **Offboarding** — run exit clearance checklists, archive departed employees,
  and rehire them back into the active roster when needed.
- **Recruitment (ATS)** — job openings, candidates, pipeline stages, ratings,
  and notes, with one-click conversion into onboarding.

## Project layout

The application uses a small MVC layout:

- `app/` — controllers, models, and views (application code)
- `core/` — framework helpers (router, base controller/model, database)
- `config/` — configuration (`config/config.php`)
- `database/` — SQLite database file and schema
- `public/` — front controller and public assets (the only exposed directory)

## Versioning

Releases follow [Semantic Versioning](https://semver.org/). The current version is
defined by the `APP_VERSION` constant in `config/config.php` and shown in the
application footer. See [CHANGELOG.md](CHANGELOG.md) for release history.
