# Program Manager — Full Stack Application

A full-stack Program Manager application built using Angular, PHP, MySQL and Docker.

## Technologies Used

- **Frontend:** Angular, TypeScript, HTML, CSS
- **Backend:** PHP REST API
- **Database:** MySQL 8.0
- **Containerization:** Docker and Docker Compose
- **Web Servers:** Nginx and Apache
- **Cloud Deployment:** AWS EC2
- **Version Control:** Git and GitHub

## Docker Architecture

The application uses three Docker containers:

1. **Angular:** Serves the frontend through Nginx.
2. **PHP:** Runs the backend API using Apache.
3. **MySQL:** Stores application data.

## Running Locally with Docker

### 1. Clone the repository

```bash
git clone https://github.com/ThabangRantoka/program-manager-php.git
cd program-manager-php
```

### 2. Configure environment variables

Create a `.env` file in the project root:

```env
MYSQL_ROOT_PASSWORD=your_root_password
MYSQL_DATABASE=program_manager
MYSQL_USER=program_app
MYSQL_PASSWORD=your_app_password
```

### 3. Build the Angular frontend

```bash
cd program-manager-angular
npm ci
npm run build -- --configuration production --base-href /app/
cd ..
```

### 4. Start Docker containers

```bash
docker compose up -d --build
```

### 5. Open the application

- Angular: http://localhost:4200/app/
- PHP: http://localhost:8080/

**Note:** The MySQL database must be initialized with the application's schema before using database-dependent features. Database backups and `.env` files are not included in this repository.

## Features

- User authentication
- Program management
- REST API integration
- MySQL database storage
- Docker containerization
- AWS cloud deployment

## Security

Environment variables are used for database credentials. Private `.env` files and database backups are excluded from Git.

## Author

**Thabang Rantoka**

Full Stack Developer | Computer Systems Engineering

[GitHub](https://github.com/ThabangRantoka) | [LinkedIn](https://www.linkedin.com/in/moyahabo-thabang-rantoka-95a036275)