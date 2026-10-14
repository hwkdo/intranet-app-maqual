<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual;

use Hwkdo\IntranetAppMaqual\Commands\FordereTeilnahmeAnCommand;
use Hwkdo\IntranetAppMaqual\Commands\ImportLegacyQualsCommand;
use Hwkdo\IntranetAppMaqual\Evaluierung\EvaluierungProvider;
use Hwkdo\IntranetAppMaqual\Evaluierung\InAppEvaluierungProvider;
use Hwkdo\IntranetAppMaqual\Models\Qual;
use Hwkdo\IntranetAppMaqual\Policies\QualPolicy;
use Hwkdo\IntranetAppMaqual\Services\QualFreigabeCardActionHandler;
use Hwkdo\IntranetAppMaqual\Support\RegistersMaqualUserRelations;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Volt;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class IntranetAppMaqualServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('intranet-app-maqual')
            ->hasConfigFile()
            ->hasViews()
            ->hasCommands([
                FordereTeilnahmeAnCommand::class,
                ImportLegacyQualsCommand::class,
            ])
            ->discoversMigrations()
            ->runsMigrations();
    }

    public function packageRegistered(): void
    {
        $this->app->bind(EvaluierungProvider::class, InAppEvaluierungProvider::class);
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Qual::class, QualPolicy::class);
        RegistersMaqualUserRelations::register();

        $this->app->booted(function (): void {
            Volt::mount(__DIR__.'/../resources/views/livewire');
        });

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->app->resolving(Schedule::class, function (): void {
            require __DIR__.'/../routes/console.php';
        });

        $this->registerTeamsAdaptiveCardActionHandlers();
    }

    protected function registerTeamsAdaptiveCardActionHandlers(): void
    {
        $handlers = config('intranet-app-teams-bot.adaptive_card_action_handlers', []);

        if (! is_array($handlers)) {
            $handlers = [];
        }

        $handlers[] = QualFreigabeCardActionHandler::class;

        config([
            'intranet-app-teams-bot.adaptive_card_action_handlers' => array_values(array_unique($handlers)),
        ]);
    }
}
