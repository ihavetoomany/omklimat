<?php
/**
 * Password Hash Generator
 * Run this script to generate a password hash for Rosenberg9
 * Usage: php generate_password_hash.php
 */

$password = 'Rosenberg9';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Password: $password\n";
echo "Hash: $hash\n\n";
echo "Copy this hash to config.php:\n";
echo "define('ADMIN_PASSWORD_HASH', '$hash');\n";

