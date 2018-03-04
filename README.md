# Data Seeder

In the app:

$ php artisan make:command SeedDatabase

In app/Console/Kernel.php:

    class Kernel extends ConsoleKernel
    {
        protected $commands = [
            ...
            \App\Console\Commands\SeedDatabase::class, // <== add this
            ...
        ];
