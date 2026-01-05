<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::where('email', 'admin@flexcare.zm')->first();

if (!$user) {
    echo "❌ User not found!\n";
    exit(1);
}

// Update password
$user->password = Hash::make('password');
$user->save();

echo "✅ Password updated successfully for {$user->email}\n";
echo "   Username: {$user->username}\n";
echo "   Password: password\n";

// Verify it works
if (Hash::check('password', $user->password)) {
    echo "✅ Password verification successful!\n";
} else {
    echo "❌ Password verification failed!\n";
}
