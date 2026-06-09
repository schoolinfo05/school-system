1st:
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve --host=0.0.0.0 --port=8000

2nd:
cd C:\xampp\htdocs\school-systems\school-app
npm install
npx expo start --clear