<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $modulesPath = base_path('Modules');

        // 1. Get all module directories
        if (!File::exists($modulesPath)) {
            return;
        }
        $modules = File::directories($modulesPath);

        foreach ($modules as $module) {
            $moduleName = basename($module);
            
            // 2. Check if module is enabled in config
            if (!config("modules.active.$moduleName", false)) {
                continue;
            }

            // 3. Load Routes
            if (File::exists("$module/Routes/api.php")) {
                Route::prefix('api')
                    ->middleware('api')
                    ->group("$module/Routes/api.php");
            }

            // 4. Load Migrations
            if (File::exists("$module/Migrations")) {
                $this->loadMigrationsFrom("$module/Migrations");
            }
        }
    }
}
