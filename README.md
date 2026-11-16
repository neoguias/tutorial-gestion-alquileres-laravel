# Gestión de alquileres en un CRM inmobiliario con Laravel

El CRM inmobiliario de Atalaya Inmobiliaria, una agencia inventada de Valencia, con un módulo nuevo para llevar los alquileres. Guarda los contratos con su renta, su fianza y su día de pago, emite un recibo cada mes, anota los cobros, imprime los recibos cobrados y avisa por email a los inquilinos que tienen recibos vencidos sin pagar.

Está hecho con Laravel 13 y Livewire 4, sobre el CRM del tutorial anterior, que usa los paquetes [Wiretables](https://github.com/edulazaro/wiretables), [Wiremodal](https://github.com/edulazaro/wiremodal), [Wirepicker](https://github.com/edulazaro/wirepicker), [Wiretoast](https://github.com/edulazaro/wiretoast) y [Larasearch](https://github.com/edulazaro/larasearch).

El tutorial completo está en Neoguias: [Cómo gestionar alquileres en un CRM inmobiliario con Laravel](https://www.neoguias.com/gestionar-alquileres-laravel/). Hay una versión con Next.js en [este otro repositorio](https://github.com/neoguias/tutorial-gestion-alquileres-nextjs).

Los tutoriales anteriores de la serie:

- [Cómo crear un CRM inmobiliario con Laravel](https://www.neoguias.com/crear-crm-inmobiliario-laravel/), con [su repositorio](https://github.com/neoguias/tutorial-crm-inmobiliario-laravel).
- [Cómo crear un portal inmobiliario como Idealista con Laravel](https://www.neoguias.com/crear-portal-inmobiliario-laravel/), con [su repositorio](https://github.com/neoguias/tutorial-portal-inmobiliario-laravel).
- [Cómo valorar un piso por comparables en un CRM inmobiliario con Laravel](https://www.neoguias.com/valorar-inmuebles-comparables-laravel/), con [su repositorio](https://github.com/neoguias/tutorial-valoracion-inmobiliaria-laravel).

## Cómo arrancarlo

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm install && npm run build
php artisan serve
```

Abre `http://localhost:8000`. El seeder carga diez inmuebles con una foto cada uno, seis clientes, dos visitas y tres contratos de alquiler firmados hace unos meses con todos sus recibos. Uno de los inquilinos debe los dos últimos. Los alquileres están en `http://localhost:8000/alquileres`. Las fotos de ejemplo están generadas con IA y son de uso libre.

Los recibos se emiten el día 1 de cada mes y los avisos de impago salen cada mañana, con el programador de tareas de Laravel. En local se arranca con `php artisan schedule:work`, y en un servidor con una entrada en el cron que ejecute `php artisan schedule:run` cada minuto. También se pueden lanzar a mano:

```bash
php artisan receipts:issue
php artisan receipts:remind
```

Los emails van al log (`storage/logs/laravel.log`) mientras `MAIL_MAILER` sea `log`.

Los tests usan una base de datos SQLite en memoria:

```bash
php artisan test
```

Requiere PHP 8.3 y Node 20 o superior.

## Dónde está cada cosa

- [`app/Models/Property.php`](app/Models/Property.php) y [`app/Models/Demand.php`](app/Models/Demand.php): los inmuebles y lo que busca cada cliente. El scope `matching` de cada uno hace el cruce, en un sentido y en el otro.
- [`app/Models/Lease.php`](app/Models/Lease.php) y [`app/Models/Receipt.php`](app/Models/Receipt.php): los contratos, con la fianza que marca la ley y la emisión del recibo de cada mes, y los recibos, con su estado.
- [`app/Console/Commands`](app/Console/Commands): los comandos que emiten los recibos y avisan de los impagos, programados en [`routes/console.php`](routes/console.php).
- [`app/Notifications/RentOverdue.php`](app/Notifications/RentOverdue.php): el email que recibe el inquilino con sus recibos pendientes.
- [`resources/views/receipt.blade.php`](resources/views/receipt.blade.php): el recibo listo para imprimir.
- [`app/Models/PropertyPhoto.php`](app/Models/PropertyPhoto.php): las fotos de cada inmueble, guardadas en el disco `public`. La primera es la portada.
- [`app/Enums`](app/Enums): operación, tipo de inmueble, estado, resultado de una visita y estado de un recibo, con sus textos en español.
- [`app/Livewire/Forms`](app/Livewire/Forms): los formularios de alta de inmuebles, clientes y contratos, con su validación.
- [`resources/views/pages`](resources/views/pages): las pantallas del CRM, que son los inmuebles, la ficha de un inmueble, los clientes, las visitas, los alquileres y cada contrato con sus recibos.
- [`resources/css/app.css`](resources/css/app.css) y [`resources/js/app.js`](resources/js/app.js): los estilos y scripts de los paquetes de la interfaz.
- [`config/crm.php`](config/crm.php): el nombre de la agencia y las zonas en las que trabaja.
- [`tests/Feature/RentalsTest.php`](tests/Feature/RentalsTest.php): los recibos de cada mes, la fianza, los avisos de impago, los cobros y el alta de contratos.
- [`tests/Feature/CrmTest.php`](tests/Feature/CrmTest.php): el cruce, los formularios, los buscadores, el orden por precio y la agenda.

## Autor

Creado por [Edu Lázaro](https://edulazaro.com) para [Neoguias](https://www.neoguias.com).

## Licencia

Este ejemplo es software libre con [licencia MIT](LICENSE).
