<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Modules\Medical\Services\Fraud\FraudDetectionService;
use Modules\Medical\Services\Fraud\FraudRuleEngineService;
use Modules\Medical\Services\Fraud\FraudScoringService;
use Modules\Medical\Services\Fraud\FraudAlertService;
use Modules\Medical\Services\Notifications\RealTimeBroadcastService;
use Modules\Medical\Listeners\MedicalEventSubscriber;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register fraud detection services as singletons for performance
        $this->app->singleton(FraudRuleEngineService::class);
        $this->app->singleton(FraudScoringService::class);
        $this->app->singleton(FraudAlertService::class);
        $this->app->singleton(FraudDetectionService::class);

        // Register notification services
        $this->app->singleton(RealTimeBroadcastService::class);
    }

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

            // 3. Load Config
            $configPath = "$module/Config/" . strtolower($moduleName) . ".php";
            if (File::exists($configPath)) {
                $this->mergeConfigFrom($configPath, strtolower($moduleName));
            }

            // 4. Load Routes
            if (File::exists("$module/Routes/api.php")) {
                Route::prefix('api')
                    ->middleware('api')
                    ->group("$module/Routes/api.php");
            }

            // 5. Load Migrations
            if (File::exists("$module/Database/Migrations")) {
                $this->loadMigrationsFrom("$module/Database/Migrations");
            }

            // 6. Load Broadcast Channels
            if (File::exists("$module/Routes/channels.php")) {
                require "$module/Routes/channels.php";
            }
        }

        // Register Medical module event subscribers
        Event::subscribe(MedicalEventSubscriber::class);
    }
}
