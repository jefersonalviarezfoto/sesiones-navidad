# Versión para hosting con cPanel (Spaceship)

Sube a la carpeta del subdominio `sesiones.alcaproducciones.com`:

- `index.html`, `terminos.html`, `privacidad.html`, `cancelaciones.html`, `logo.png` (de la raíz del repo)
- `.htaccess` y la carpeta `api/` (de esta carpeta `php/`)

La clave de Stripe va en `stripe-config.php`, FUERA de la carpeta pública
(por ejemplo `/home/TU_USUARIO/stripe-config.php`). Usa `stripe-config.example.php` como modelo.
Nunca subas ese archivo a GitHub.
