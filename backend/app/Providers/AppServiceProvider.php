<?php

namespace App\Providers;

use App\Models\ApprovalWorkflow;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Modules\Medical\Models\Application;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register polymorphic morph map for approval workflow entities
        Relation::morphMap([
            ApprovalWorkflow::ENTITY_APPLICATION => Application::class,
            // Add other entity types as they are implemented:
            // ApprovalWorkflow::ENTITY_CLAIM => \Modules\Medical\Models\Claim::class,
            // ApprovalWorkflow::ENTITY_POLICY_ACTIVATION => \Modules\Medical\Models\Policy::class,
            // ApprovalWorkflow::ENTITY_ENDORSEMENT => \Modules\Medical\Models\Endorsement::class,
        ]);
    }
}
