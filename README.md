# otodo

A simple PHP online todo list application with user authentication and SQLite storage. The interface now uses custom vanilla CSS and JavaScript for its responsive layout.

## Features

- User registration and login
- Add, complete, and delete tasks
- Tasks stored per user in an SQLite database
- Responsive design for desktop and mobile browsers
- Optional due dates and descriptions for tasks
- Task priorities with color-coded badges
- Tasks default to today's date
- User-configurable location (timezone) for date calculations with browser auto-detection and a searchable list
- Service worker caching for faster repeat visits

## Getting Started

1. Ensure PHP 8+ with SQLite support is installed.
2. Start the built-in web server:
   ```bash
   php -S localhost:8000
   ```
3. Open <http://localhost:8000/login.php> in your browser to register and start using the app.

The application will create a hidden `.database.sqlite` file in the project directory on first run.

## Automated Deployment with GitHub Actions

Use the included `.github/workflows/deploy.yml` workflow to deploy automatically whenever `prod` is updated.

1. In your repository on GitHub, go to **Settings → Secrets and variables → Actions** and add:
   - `FTP_HOST` – the hostname of your server.
   - `FTP_USER` – the FTP/SFTP username.
   - `FTP_PASS` – the FTP/SFTP password.
   - `FTP_REMOTE_PATH` (optional) – destination path on the server. Defaults to `/var/www/html` if not set.
2. Confirm your deployment branch is named `prod` or update `branches` in `.github/workflows/deploy.yml` to match your deploy branch.
3. Commit and push changes. The workflow will install `lftp`, sync the repository to the remote path (excluding `.git`, `.github`, `node_modules`, tests, logs, and Markdown files), and run automatically on pushes to `prod`.
4. For manual runs (e.g., urgent hotfixes), open **Actions → Deploy via lftp → Run workflow** to trigger `workflow_dispatch`.

If you prefer SSH-based deployment instead of FTP/SFTP mirroring, you can adapt the deploy step to use `ssh` and run your server-side deploy script with the same secrets approach.
