#!/bin/bash
# Simple development server script for the blog
# Usage: ./dev-server.sh [port]
# Default port: 8000

PORT=${1:-8000}
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

echo "Starting PHP development server..."
echo "Blog will be available at: http://localhost:$PORT"
echo "Admin area: http://localhost:$PORT/admin/login.php"
echo ""
echo "Press Ctrl+C to stop the server"
echo ""

cd "$DIR"
php -S localhost:$PORT



