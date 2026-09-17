# Changelog

All notable changes to the HRMS project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-09-17

### Added
- **Onboarding**
  - QR-code onboarding flow: the "入职二维码" (onboarding QR) page shows a
    scannable QR code that links to a public, mobile-friendly onboarding form
    (`/apply/form/{token}`) so new hires can register themselves by phone.
  - Invite tokens (`onboarding_invites`) back the QR link; only one invite is
    active at a time and admins can regenerate it, instantly invalidating old
    QR codes.
  - "入职申请" (onboarding applications) admin page listing submitted forms
    with status tabs (pending / approved / rejected) and a detail view showing
    all submitted personal, contact, address, emergency-contact, and job-intent
    fields.
  - Approve action creates the Employee record, starts the onboarding workflow
    with the default checklist, and marks the application approved; reject
    action records an optional comment.
  - Duplicate-submission guard: the public form rejects emails that already
    belong to an employee or a pending application.

## [1.1.0] - 2026-09-17

### Added
- **Employee management**
  - Manual "新增员工" (Add New Employee) button on the employee list page with a
    dedicated create form (previously only reachable through the onboarding flow).
  - Extended employee profile fields: `id_number` (身份证号), `ethnicity` (民族),
    `household_address` (户籍地址), `education` (学历), and emergency contact
    details (`emergency_contact`, `emergency_relation`, `emergency_phone`).
  - Automatic age calculation derived from the date of birth (front-end live
    update plus server-side `Employee::calculateAge()`).
  - Redesigned single employee profile page with profile card, contact panel,
    personal/work information sections, quick actions, and recent attendance and
    leave history.
  - Phone column shown instead of email on the employee list page.

- **Offboarding**
  - "复职" (Rehire) action on the terminated-employee archive that restores the
    employee to the active roster (`status = Active`) and removes the archive
    record.

### Changed
- Employee names are now displayed in Chinese order (`last_name + first_name`,
  no separating space) across every page and view.
- Onboarding list now only shows employees whose onboarding is still in
  progress; completed onboardings are hidden.
- Terminated employees no longer appear in the employee management list.

### Fixed
- Employee list and search queries exclude `Terminated` records to avoid
  duplication with the offboarded-employee archive.

## [1.0.0] - 2025-07-01

### Added
- Initial HRMS release: authentication, dashboard, employees, departments,
  attendance, leave, payroll, onboarding, offboarding, and recruitment (ATS)
  modules backed by SQLite.
