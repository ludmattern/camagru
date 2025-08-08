# Camagru - 42 School Project

## Description

Camagru is an Instagram-like web application where users can create, edit, and share photos with filters and effects. This project is part of the 42 School web development curriculum.

## Features

### Core Features
- ✅ User registration and authentication with email verification
- ✅ Password reset functionality
- ✅ Photo capture using webcam
- ✅ Photo upload (fallback if no webcam)
- ✅ Apply filters/stickers to photos
- ✅ Public gallery with pagination
- ✅ Like and comment system
- ✅ Email notifications for new comments
- ✅ User profile management
- ✅ Responsive design

### Security Features
- ✅ Password hashing (bcrypt)
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection (input sanitization and output escaping)
- ✅ CSRF protection
- ✅ Form validation (client and server-side)
- ✅ File upload validation
- ✅ Session management
- ✅ Rate limiting

### Bonus Features
- ⏳ AJAX for likes, comments, and pagination
- ⏳ Live preview of photo montage
- ⏳ Infinite scroll pagination
- ⏳ Social sharing buttons
- ⏳ GIF generation from multiple photos

## Technology Stack

- **Backend**: PHP 8.1
- **Database**: MySQL 8.0
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Styling**: Bootstrap 5
- **Containerization**: Docker & Docker Compose
- **Web Server**: Apache

## Project Structure

```
camagru/
├── backend/
│   ├── classes/           # Core PHP classes
│   ├── controllers/       # Request handlers
│   ├── middleware/        # Authentication middleware
│   ├── utils/            # Utility classes
│   ├── templates/        # Email templates
│   └── api/              # API endpoints
├── public/               # Public web files
├── frontend/             # Frontend assets
├── uploads/              # User uploaded images
├── filters/              # Filter/sticker images
├── db/                   # Database schema and migrations
├── config/               # Configuration files
└── docker-compose.yml    # Docker configuration
```

## Installation & Setup

### Prerequisites
- Docker and Docker Compose
- Git

### Quick Start

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd camagru
   ```

2. **Create environment file**
   ```bash
   cp .env.example .env
   # Edit .env with your configuration
   ```

3. **Start with Docker**
   ```bash
   docker-compose up -d
   ```

4. **Access the application**
   - Application: http://localhost:8080
   - phpMyAdmin: http://localhost:8081

### Manual Setup (without Docker)

1. **Database Setup**
   - Create MySQL database
   - Import `db/init.sql`

2. **Web Server Configuration**
   - Configure Apache/Nginx to serve from `public/` directory
   - Enable PHP and required extensions

3. **Dependencies**
   - Ensure PHP 8.1+ with extensions: pdo_mysql, gd, mbstring, zip

## Configuration

### Environment Variables (.env)

```env
# Database
DB_HOST=localhost
DB_NAME=camagru
DB_USER=your_user
DB_PASSWORD=your_password

# Email (for notifications)
MAIL_HOST=your_smtp_host
MAIL_PORT=587
MAIL_USERNAME=your_email
MAIL_PASSWORD=your_password
MAIL_FROM=noreply@camagru.com

# Application
APP_URL=http://localhost:8080
SECRET_KEY=your_secret_key
```

## Usage

### For Users

1. **Registration**: Create account with email verification
2. **Login**: Access your account
3. **Create Photos**: Use webcam or upload images, apply filters
4. **Gallery**: Browse and interact with public photos
5. **Profile**: Manage account settings and preferences

### For Developers

#### API Endpoints

- `POST /api/auth.php` - Authentication
- `GET/POST /api/images.php` - Image management
- `GET/POST /api/comments.php` - Comment system
- `POST /api/likes.php` - Like system
- `GET /api/filters.php` - Available filters

#### Database Schema

- `users` - User accounts and authentication
- `images` - Photo metadata and relationships
- `likes` - Like relationships
- `comments` - Photo comments
- `filters` - Available filters/stickers
- `sessions` - Session management
- `email_queue` - Async email processing

## Development

### Adding New Features

1. **Backend**: Add classes in `backend/classes/`
2. **API**: Create endpoints in `backend/api/`
3. **Frontend**: Add pages in `public/`
4. **Database**: Update schema in `db/`

### Security Considerations

- All user inputs are sanitized
- Prepared statements prevent SQL injection
- CSRF tokens protect forms
- File uploads are validated
- Sessions are properly managed
- Rate limiting prevents abuse

## Testing

### Browser Compatibility
- Firefox >= 41
- Chrome >= 46
- Safari >= 10
- Edge >= 79

### Testing Features
- User registration and verification
- Photo upload and processing
- Filter application
- Social features (likes, comments)
- Responsive design on mobile/desktop

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check MySQL container is running
   - Verify database credentials in .env

2. **File Upload Errors**
   - Check upload directory permissions
   - Verify PHP upload limits

3. **Email Not Sending**
   - Configure SMTP settings in .env
   - Check email service logs

4. **Permission Denied**
   - Ensure proper file permissions for uploads/
   - Check Apache/PHP user permissions

## Contributing

This is a 42 School project. External contributions are not accepted, but feedback and suggestions are welcome.

## License

This project is created for educational purposes as part of the 42 School curriculum.

## Author

Created by [Your Name] for 42 School

---

**Note**: This project is designed to meet 42 School project requirements and follows their coding standards and security practices.
