<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\ServiceOrder;
use App\Policies\ClientPolicy;
use App\Policies\ServiceOrderPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Mapeamento de Models para Policies.
     */
    protected array $policies = [
        Client::class       => ClientPolicy::class,
        ServiceOrder::class => ServiceOrderPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Registrar policies
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Superadmin bypass — admin sempre pode tudo
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('admin')) {
                return true;
            }
        });

        // Configurar paginação para usar Bootstrap/CSS puro simples
        \Illuminate\Pagination\Paginator::useBootstrapFive();

        if (config('app.env') === 'production') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('companies')) {
                \Illuminate\Support\Facades\View::share('tenantCompany', \App\Models\Company::first());
            }
        } catch (\Throwable $e) {
            // Ignorar falhas de conexão/migração durante bootstrap/CLI
        }
    }
}
