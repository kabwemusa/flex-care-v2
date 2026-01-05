<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::where('email', 'admin@flexcare.zm')->first();

if (!$user) {
    echo "User not found!\n";
    exit(1);
}

echo "User found: {$user->email}\n";
echo "Username: {$user->username}\n";
echo "Password hash: {$user->password}\n\n";

$testPassword = 'password';
$matches = Hash::check($testPassword, $user->password);

echo "Testing password '{$testPassword}': " . ($matches ? "✓ MATCHES" : "✗ DOES NOT MATCH") . "\n";

// Generate a new hash for comparison
$newHash = Hash::make($testPassword);
echo "\nNew hash for '{$testPassword}':\n{$newHash}\n";
