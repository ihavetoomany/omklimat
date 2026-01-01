# Simple PHP Blog

A minimal blog application for Hostinger hosting (PHP 8 + SQLite).

## Local Development with MAMP

### Setup Steps:

1. **Start MAMP**
   - Open MAMP application
   - Click "Start Servers" (Apache and MySQL will start)
   - Note the port numbers (usually Apache: 8888, MySQL: 8889)

2. **Set Document Root** (choose one method):

   **Method A: Set custom document root in MAMP (Recommended)**
   - In MAMP, go to Preferences → Web Server
   - Set "Document Root" to: `/Users/R01501/Desktop/ResursApps/omklimat`
   - Click OK

   **Method B: Use MAMP's htdocs folder**
   - Copy all files from `omklimat` folder to: `/Applications/MAMP/htdocs/omklimat/`
   - (You'll need to update paths if you do this)

3. **Ensure PHP 8+ is selected**
   - In MAMP, go to Preferences → PHP
   - Select PHP 8.0 or higher (SQLite is included by default)

4. **Access your blog**
   - Open browser and go to: **http://localhost:8888** (or http://localhost if using port 80)
   - Admin area: **http://localhost:8888/admin/login.php**
   - Default password: `admin`

### Troubleshooting:

- **If you get a 404 error**: Make sure the document root is set correctly
- **If database errors**: Ensure the `data/` folder exists and is writable
- **Port conflicts**: Change Apache port in MAMP preferences if 8888 is in use

### Alternative: PHP Built-in Server

If you prefer PHP's built-in server instead:

```bash
cd /Users/R01501/Desktop/ResursApps/omklimat
./dev-server.sh
```

Then access: http://localhost:8000

## Production Deployment

1. Upload all files to your Hostinger webhost
2. Ensure `data/` folder is writable (chmod 755)
3. Change admin password in `config.php`
4. Access via your domain

## Default Login

- URL: `/admin/login.php`
- Password: `admin` (CHANGE THIS in production!)

## File Structure

```
├── config.php          # Configuration & database
├── index.php           # Public home page
├── post.php            # Single post view
├── styles.css          # Stylesheet
├── admin/
│   ├── login.php      # Admin login
│   ├── dashboard.php  # Post management
│   └── edit.php       # Create/edit posts
└── data/              # SQLite database (auto-created)
```

