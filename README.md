npm run dev
php artisan migrate:fresh
php artisan serve
php artisan queue:work --queue=imports,emails
