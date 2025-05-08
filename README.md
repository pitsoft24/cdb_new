# NEW_DB_V1

A web-based database management system for device information.

## Features

- Device information management with a user-friendly web interface
- Real-time filtering for all columns
- Edit, copy, and delete functionality for device entries
- Export/Import functionality with timestamp
- Automatic backups when editing
- Duplicate detection using MD5 hashes
- Scroll position maintenance during operations

## Requirements

- PHP 7.0 or higher
- XAMPP (or similar web server with PHP support)
- Web browser with JavaScript enabled

## Installation

1. Clone this repository to your XAMPP htdocs directory:
   ```
   git clone https://github.com/yourusername/NEW_DB_V1.git
   ```

2. Place the files in your XAMPP htdocs directory:
   ```
   C:\xampp\htdocs\cdb_new\
   ```

3. Start XAMPP and ensure Apache is running

4. Access the application through your web browser:
   ```
   http://localhost/cdb_new/
   ```

## Usage

- **View**: The main table displays all device information
- **Filter**: Use the filter inputs above each column to filter data
- **Edit**: Click the "Edit" button to modify device information
- **Copy**: Click the "Copy" button to duplicate a device entry
- **Delete**: Click the "Delete" button to remove a device entry
- **Export**: Click "Export" in the menu to download the current database
- **Import**: Click "Import" in the menu to upload a database file
- **Save**: Click "Save" in the menu to create a backup

## File Structure

- `index.php`: Main application file
- `db.txt`: Database file containing device information
- `log/`: Directory for automatic backups
- `aktive/`: Directory for manual backups

## License

This project is licensed under the MIT License - see the LICENSE file for details. 