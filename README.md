# LIENZO URBANO — Guía de despliegue en Elastic Cloud (Jelastic)

## Qué incluye este paquete

```
lienzo-urbano/
├── index.html          → landing completa (HTML+CSS+JS en un solo archivo)
├── api/
│   └── submit.php       → guarda cada mail en data/suscriptores.txt
└── data/
    ├── suscriptores.txt → se crea solo al primer registro
    └── .htaccess         → bloquea el acceso HTTP directo (solo Apache)
```

No hay build step ni dependencias de Node: es HTML/PHP puro, pensado para subir directo.

---

## Paso 1 — Elegir el stack (esto define el costo base)

En el panel de Elastic Cloud, al crear el entorno elegí una topología de **un solo nodo**:

- **Apache + PHP** (recomendado) — combina servidor web y motor PHP en un mismo nodo. Es la opción más económica porque no paga cloudlets de un balanceador NGINX separado.
- Evitá plantillas que agregan NGINX como balanceador delante de Apache/PHP-FPM a menos que ya sepas que vas a tener tráfico alto. Para una landing de pre-lanzamiento, un solo nodo alcanza sobrado.
- **No agregues nodo de base de datos.** Guardar los mails en `.txt` es justamente lo que te permite no pagar un nodo SQL completo.

Si ya tenés un entorno de Elastic Cloud corriendo con otra topología (por ejemplo NGINX+PHP para otro proyecto), podés reusarlo: solo asegurate de que el nodo tenga PHP habilitado y de subir `data/.htaccess` — si el balanceador es NGINX, esa regla no aplica y hay que agregar el bloque `deny` en la config del vhost (pedíselo a soporte de Mi Nube si no tenés acceso a esa config).

## Paso 2 — Dimensionar cloudlets (esto define el costo variable)

Para una landing estática + un formulario de bajo tráfico:

- **Cloudlets fijos:** 2–4 alcanza para arrancar.
- **Escalado automático:** dejalo activado pero con un **tope máximo bajo** (por ejemplo 4–8 cloudlets). Esto te protege de un pico inesperado sin dejar la cuenta abierta a un consumo grande.
- **Nodos:** 1 (sin alta disponibilidad). No la necesitás para una página de captura de mails.
- Si el portal de Mi Nube ofrece "auto-stop" por inactividad, activalo mientras estás en fase de pruebas previas al 30/7 — así no pagás cloudlets mientras no hay visitas.

## Paso 3 — Subir los archivos

Cualquiera de estas tres funciona:

1. **File Manager del panel** (más simple): comprimí la carpeta `lienzo-urbano/` en un `.zip`, subilo y extraelo directamente en el webroot del nodo (normalmente `/var/www/webapps/ROOT` o similar).
2. **Git**: si tenés el proyecto en un repo, usá la opción "Importar desde Git" del entorno.
3. **FTP/SFTP**: con las credenciales que te da el nodo.

Verificá que la estructura quede así en el webroot:
```
ROOT/
├── index.html
├── api/submit.php
└── data/.htaccess
```

## Paso 4 — Dominio y HTTPS

- Asociá tu dominio (o subdominio) a la IP pública del entorno, o apuntá un CNAME si Mi Nube te da un hostname.
- Activá el add-on de **Let's Encrypt** desde el marketplace del entorno — es gratis y evita pagar un certificado aparte.

## Paso 5 — Probar el formulario

1. Entrá al sitio, firmá con un mail de prueba.
2. Confirmá que aparece el mensaje "Quedaste firmado...".
3. Abrí `data/suscriptores.txt` desde el File Manager y verificá que se agregó la línea `mail,timestamp`.
4. Probá el mismo mail dos veces: la segunda vez debería decir "Ya estabas en la lista" sin duplicar la línea.

## Paso 6 — Bajar tus datos

Como es un `.txt` y no una base de datos, no hay backup automático. Descargá `suscriptores.txt` por File Manager cada tanto (por ejemplo, antes del 30/7) y guardalo aparte.

---

## Puntos débiles que conviene tener presentes

- **Persistencia ante redeploys:** si en algún momento hacés un "Redeploy" o "reset" del entorno en vez de solo actualizar archivos, algunas plantillas de Jelastic pisan el webroot completo. Si eso pasa, `suscriptores.txt` se pierde. Antes de cualquier redeploy, descargá el archivo.
- **Protección de datos personales:** el mail es un dato personal. El `.htaccess` bloquea el acceso HTTP directo, pero cualquiera con acceso al File Manager o SSH del entorno puede leer el archivo en texto plano. Si vas a juntar más que el mail (nombre, teléfono), conviene pasar a algo con permisos más finos.
- **Concurrencia a escala:** el `flock()` en `submit.php` evita que dos escrituras simultáneas se corrompan, pero un archivo de texto no escala bien más allá de unos pocos miles de filas ni permite consultas (¿cuántos firmaron esta semana?). Para una landing de lanzamiento está bien; si el archivo empieza a crecer mucho o querés segmentar, ahí sí conviene una base de datos liviana (sigue siendo barata en Jelastic con SQLite embebido, sin nodo aparte).

¿Querés que prepare también un mail de confirmación automático para quien se firma, o lo dejamos solo en la lista por ahora?
