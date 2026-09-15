# HRMS

Small PHP/MySQL human-resource management system with employee, department, attendance, leave, and payroll workflows.

## Run locally

1. Create a MySQL database and run `database/schema.sql`.
2. SQLite is stored in `database/hrms.sqlite` and is initialized automatically on first request. No MySQL server is required.
3. Serve the `public` directory with the front controller as the built-in-server router:

   ```text
   php -S 127.0.0.1:3333 -t public public/index.php
   ```

   Then open `http://127.0.0.1:3333/auth/login`.
4. Sign in with `admin / password123` from the seed data.

The application uses a small MVC layout: application code is under `app/`, framework helpers under `core/`, configuration under `config/`, SQL under `database/`, and only the front controller and public assets are exposed from `public/`.
