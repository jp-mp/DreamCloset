<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Probar DreamCloset con fotos y recomendaciones

Desde la raíz del repositorio, aplica la migración de fotos y arranca Laravel. Deja Vite abierto en una segunda terminal con `npm run dev` y abre la dirección que imprime Artisan.

```bash
php artisan migrate
php artisan serve
```

- En **Mi clóset → Agregar prenda**, carga una foto JPG, PNG o WebP. **Quitar fondo** prepara un recorte que puedes revisar antes de guardar. Usa **Editar** para recortar, reemplazar o quitar la foto de una prenda existente, y **Eliminar** para borrar la prenda con su foto. Las fotos se almacenan en el disco privado de Laravel y solo se sirven al dueño de la prenda. Para archivos de más de 2 MB, revisa el `php.ini` indicado por `php --ini` y configura `upload_max_filesize = 5M` y `post_max_size = 8M` si tu servidor tiene los límites predeterminados.
- Guarda tu ubicación en **Mi cuenta**. **Inicio** muestra automáticamente un outfit con tus prendas y el pronóstico de Open-Meteo. Puedes pedir un estilo casual, formal o deportivo. Usa **Estilo** en las tarjetas de **Mi clóset** para etiquetar prendas que ya guardaste. Las solicitudes manuales usan Groq cuando está disponible y recurren a reglas de clima y estilo cuando no responde.
- **Inicio** y **Mi semana** muestran el outfit como una imagen descargable en PNG. Cada recorte ocupa su lugar según la categoría sobre un fondo neutro que se elige a partir de los colores de las fotos. Si faltan recortes, la imagen muestra los que ya están listos y **Fotos por preparar** enlaza a las prendas pendientes.

### Activar el recorte local

Instala rembg en un entorno virtual de Python 3.11–3.13. En Ubuntu, inicia el servicio de usuario con un límite de memoria. La primera solicitud descarga el modelo.

```bash
python3 -m venv storage/app/rembg-venv
storage/app/rembg-venv/bin/pip install 'rembg[cpu,cli]==2.0.87'
systemd-run --user --unit=dreamcloset-rembg -p MemoryMax=1G -p MemorySwapMax=128M \
  env BROWSER=/bin/true OMP_NUM_THREADS=2 U2NET_HOME="$PWD/storage/app/rembg-models" \
  "$PWD/storage/app/rembg-venv/bin/rembg" \
  s --host 127.0.0.1 --port 7000 --no-ui --threads 2 --max-sessions 1
```

Comprueba si el proceso está activo con `systemctl --user status dreamcloset-rembg`. Si reinicias la sesión de usuario y el servicio se detiene, vuelve a ejecutar el comando `systemd-run`.

El recorte usa `u2netp` por defecto para reducir el consumo de memoria. En un servidor con más memoria puedes elegir BiRefNet con `REMBG_MODEL=birefnet-general-lite`. Si rembg corre en otro equipo, configura `REMBG_URL` con la dirección accesible desde Laravel. Si el recorte falla, puedes guardar la foto original. [Documentación de rembg](https://github.com/danielgatis/rembg#usage-as-a-server)

### Activar Groq

Añade tu clave al archivo `.env` de Laravel, ejecuta `php artisan config:clear` y reinicia el servidor.

```dotenv
GROQ_API_KEY=
GROQ_MODEL=openai/gpt-oss-20b
```

Groq recibe la previsión resumida, el estilo elegido y los datos descriptivos de las prendas candidatas. Laravel valida los identificadores devueltos contra el clóset propio antes de mostrar el resultado. [Salidas estructuradas de Groq](https://console.groq.com/docs/structured-outputs)

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
