# otodo

A simple PHP online todo list application with user authentication and SQLite storage. The interface uses [Bootstrap](https://getbootstrap.com/) for a mobile‑responsive layout.

## Features

- User registration and login
- Add, complete, and delete tasks
- Tasks stored per user in an SQLite database
- Responsive design for desktop and mobile browsers
- Optional due dates and descriptions for tasks

## Getting Started

1. Ensure PHP 8+ with SQLite support is installed.
2. Start the built-in web server:
   ```bash
   php -S localhost:8000
   ```
3. Open <http://localhost:8000/login.php> in your browser to register and start using the app.

The application will create `database.sqlite` in the project directory on first run.
