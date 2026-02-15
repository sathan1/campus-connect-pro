# Campus Connect Pro — Interactive Quiz & Events Platform

A full-stack event management and quiz platform for college campuses. Supports three event types — **Quizzes**, **Photography Competitions**, and **Presentation Contests** — with registration, live participation, leaderboards, winner selection, and certificate generation.

## Features

### 🎯 Event Management

- **Multiple Event Types** — Quiz, Photography, and Presentation competitions
- **Admin Dashboard** — Create events, manage registrations, select winners
- **Auto-Conclusion** — Past events are automatically marked as finished with results calculated

### 📝 Quiz System

- **Timed Quiz Taking** — Multiple-choice quizzes with automatic scoring
- **Bulk Question Upload** — Add questions individually or in bulk
- **Leaderboard** — Real-time score rankings per event
- **Prevention** — One attempt per user per quiz

### 📷 Photography Events

- **Photo Submissions** — Users upload photos with captions (configurable limit)
- **Gallery View** — Admin reviews all submissions in a gallery
- **Winner Selection** — Admin picks top 3 winners with position assignment

### 📊 Presentation Events

- **Team Registration** — Upload PPT files with team names
- **Live Presentation Mode** — Call teams in order, track presenting/completed status
- **Scoring System** — Admin assigns marks during live presentations

### 🎓 Certificates

- **Participation Certificates** — Auto-generated for all participants in finished events
- **Achievement Certificates** — Special certificates for 1st place winners
- **Downloadable HTML** — Print-ready certificate design with gold accents

### 👤 User System

- **Registration & Login** — Secure authentication with hashed passwords
- **Role-Based Access** — Admin and User roles
- **Event Registration** — Sign up for events, track participation

## Tech Stack

| Layer    | Technology                                            |
| -------- | ----------------------------------------------------- |
| Frontend | HTML5, CSS3 (Custom Properties), JavaScript (Vanilla) |
| Backend  | PHP 8                                                 |
| Database | MySQL                                                 |
| Design   | CSS Variables, Responsive Grid, Card-based UI         |

## Setup

### Requirements

- PHP 8.0+
- MySQL 5.7+
- XAMPP / WAMP / any PHP server

### Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/sathan1/campus-connect-pro.git
   ```
2. Place in your web server directory (e.g., `htdocs/`)
3. Create database and run migrations:
   ```bash
   mysql -u root -p campus_connect < database_migration_v2.sql
   ```
4. Update credentials in `config.php`
5. Open `http://localhost/campus-connect-pro/`

## Project Structure

```
├── index.php                 # Main events portal (hero, cards, auth)
├── config.php                # Database connection
├── auth.php                  # Login/Register API
├── admin_panel.php           # Admin dashboard & event creation
├── admin_registrations.php   # Registration management
├── admin_quiz_summary.php    # Quiz question management
├── admin_photo_event.php     # Photography event management
├── admin_ppt_event.php       # Presentation event management
├── certificate.php           # Certificate generation & download
├── get_quiz.php              # Quiz questions API
├── submit_quiz.php           # Quiz submission handler
├── get_leaderboard.php       # Leaderboard API
├── get_events.php            # Events listing API
├── get_event_details.php     # Event details API
├── register_event.php        # Event registration handler
├── api/
│   ├── create_event.php      # Create event API
│   ├── get_results.php       # Results API
│   ├── upload_photo.php      # Photo upload handler
│   └── upload_ppt.php        # PPT upload handler
├── uploads/                  # User-uploaded files
└── database_migration_v2.sql # Database schema
```

## License

This project is licensed under the MIT License — see the [LICENSE](LICENSE) file for details.

## Author

**Sathandhurkes D**

- GitHub: [@sathan1](https://github.com/sathan1)
- LinkedIn: [Sathandhurkes D](https://www.linkedin.com/in/sathandhurkes-d-90a66928b/)
