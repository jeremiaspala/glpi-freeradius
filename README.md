# GLPI FreeRADIUS Plugin

Plugin para GLPI 11 que integra la gestión de dispositivos autenticados por MAC Address (MAB) en FreeRADIUS directamente desde el inventario.

Si alguna vez administraste una red con autenticación 802.1X basada en MAC y tuviste que mantener el archivo `authorize` de FreeRADIUS a mano, sabés lo tedioso que es. Este plugin resuelve exactamente eso: te permite gestionar todos tus dispositivos desde GLPI, vincularlos con el inventario, y sincronizar los cambios con FreeRADIUS con un solo clic.

---
<img width="1904" height="802" alt="Captura de pantalla_20260603_133240" src="https://github.com/user-attachments/assets/8fd3a12a-59a7-436a-a9e5-713ebbd278b2" />
## ¿Qué hace?

FreeRADIUS permite autenticar dispositivos en la red usando su MAC address como credencial (técnica conocida como MAC Authentication Bypass o MAB). Cada dispositivo autorizado queda registrado en un archivo de texto (`authorize`) junto con la VLAN que debe recibir. Mantener ese archivo actualizado cuando tenés cientos de equipos es un dolor.

El plugin hace esto:

- **Importa** todos los dispositivos del archivo `authorize` de FreeRADIUS a una base de datos en GLPI
- **Identifica** automáticamente el fabricante y tipo de dispositivo a partir de los primeros 3 octetos de la MAC (OUI lookup)
- **Vincula** cada dispositivo con su ítem correspondiente en el inventario de GLPI, buscando por coincidencia de MAC o nombre
- **Permite agregar** dispositivos nuevos desde el inventario existente: buscás el equipo, elegís cuál de sus MACs usar, y listo
- **Exporta** los cambios de vuelta a FreeRADIUS, regenerando el archivo `authorize` y recargando el servicio automáticamente
- **Merge**: una pantalla dedicada para reconciliar diferencias entre lo que hay en FreeRADIUS y lo que hay en el inventario de GLPI
- Gestión de **VLANs** con nombre, descripción y color
- **Eliminación masiva** de dispositivos con checkboxes

Todo esto dentro del sistema de menús, sesiones y permisos de GLPI 11.

<img width="1908" height="864" alt="freeradius_2" src="https://github.com/user-attachments/assets/04dd7414-55d2-4cb1-a1c3-6f4b181ea8a0" />
<img width="1657" height="634" alt="freeradius_3" src="https://github.com/user-attachments/assets/26793677-1522-4500-91d7-e14b2e0e7336" />
<img width="238" height="310" alt="menu" src="https://github.com/user-attachments/assets/75f0cf1b-355c-4195-9d42-dc61ec989c74" />


---

## Requisitos

- **GLPI** 11.0.x
- **FreeRADIUS** 3.x con archivo `authorize` de tipo `files`
- **PHP** 8.2+
- Conectividad SSH desde el servidor GLPI hacia el servidor FreeRADIUS
- El usuario SSH en el servidor FreeRADIUS debe poder ejecutar `sudo -n /usr/local/bin/freeradius-reload` (se configura durante la instalación)

---

## Instalación

### 1. Copiar el plugin

```bash
cp -r glpi_freeradius /var/www/html/glpi/plugins/freeradius
chown -R www-data:www-data /var/www/html/glpi/plugins/freeradius
```

### 2. Configurar la clave SSH

El servidor GLPI necesita poder conectarse al servidor FreeRADIUS sin contraseña. Generás una clave para el usuario `www-data`:

```bash
mkdir -p /var/www/.ssh
ssh-keygen -t rsa -b 4096 -f /var/www/.ssh/id_rsa -N ""
chown -R www-data:www-data /var/www/.ssh
chmod 700 /var/www/.ssh
chmod 600 /var/www/.ssh/id_rsa
```

Copiás la clave pública al servidor FreeRADIUS:

```bash
cat /var/www/.ssh/id_rsa.pub
# Pegás el contenido en ~/.ssh/authorized_keys del usuario operador en FreeRADIUS
```

### 3. Configurar el wrapper de recarga en FreeRADIUS

En el servidor FreeRADIUS, creás un script que permite recargar el servicio sin necesidad de contraseña de root:

```bash
cat > /usr/local/bin/freeradius-reload << 'EOF'
#!/bin/bash
PID=$(pidof /usr/sbin/freeradius 2>/dev/null || pgrep -f /usr/sbin/freeradius 2>/dev/null)
if [ -z "$PID" ]; then
  echo "ERROR: proceso freeradius no encontrado"
  exit 1
fi
kill -HUP $PID && echo "OK: FreeRADIUS recargado (PID $PID)" || echo "ERROR: fallo al enviar HUP"
EOF

chmod 755 /usr/local/bin/freeradius-reload
```

Agregás la regla de sudo para que el usuario operador pueda ejecutarlo:

```bash
cat > /etc/sudoers.d/freeradius-reload << 'EOF'
Defaults:operador !requiretty
operador ALL=(root) NOPASSWD: /usr/local/bin/freeradius-reload
EOF
chmod 440 /etc/sudoers.d/freeradius-reload
```

### 4. Instalar y activar el plugin en GLPI

```bash
cd /var/www/html/glpi
su -s /bin/bash www-data -c "php bin/console glpi:plugin:install --username=glpi freeradius"
su -s /bin/bash www-data -c "php bin/console glpi:plugin:activate freeradius"
```

### 5. Configurar el plugin

Entrás a GLPI → **FreeRADIUS → Configuración** y completás:

- IP o hostname del servidor FreeRADIUS
- Usuario SSH y ruta de la clave privada (`/var/www/.ssh/id_rsa`)
- Contraseña de `su root` (necesaria para leer/escribir el archivo `authorize`)
- Ruta del archivo `authorize` (por defecto `/etc/freeradius/3.0/mods-config/files/authorize`)
- Credenciales de la base de datos MySQL de FreeRADIUS (opcional, para sincronización adicional)

Desde la pantalla de **Sincronización** podés probar la conexión SSH con el botón "Probar conexión" antes de hacer nada.

---

## Uso

### Primera sincronización

Entrás a **FreeRADIUS → Sincronizar → Importar desde RADIUS**. El plugin lee el archivo `authorize`, importa todos los dispositivos y los vincula automáticamente con los ítems del inventario que tengan la misma MAC registrada en sus puertos de red.

### Agregar un dispositivo nuevo

Desde **FreeRADIUS → Dispositivos → Nuevo dispositivo** tenés dos modos:

**Desde el inventario**: si el equipo ya existe en GLPI, lo buscás por nombre, elegís cuál de sus MACs usar, asignás la VLAN y guardás. El plugin crea el registro y lo mantiene vinculado al ítem del inventario.

**Equipo nuevo**: si el equipo no existe en GLPI todavía, completás la MAC manualmente. El plugin detecta el fabricante automáticamente a partir del OUI y te sugiere el tipo de dispositivo.

En ambos casos podés vincular el dispositivo con un usuario de GLPI como responsable.

### Sincronizar cambios hacia FreeRADIUS

Cuando terminaste de hacer cambios (agregar, modificar o eliminar dispositivos), vas a **Sincronizar → Exportar y recargar**. El plugin genera el archivo `authorize` completo con todos los dispositivos autorizados y lo escribe en el servidor FreeRADIUS vía SSH. Si configuraste la recarga automática, FreeRADIUS recibe una señal HUP y aplica los cambios sin interrumpir el servicio.

### Pantalla de Merge

En **FreeRADIUS → Merge** encontrás tres pestañas:

- **Sugerencias automáticas**: dispositivos en FreeRADIUS que tienen un ítem GLPI con nombre similar (≥70% de coincidencia). Podés confirmar la vinculación con un clic.
- **RADIUS sin GLPI**: dispositivos autorizados que no tienen ítem vinculado en el inventario.
- **GLPI sin RADIUS**: ítems del inventario con MACs registradas que todavía no están en FreeRADIUS. Podés agregarlos directamente desde acá eligiendo la VLAN destino.

---

## Identificación de dispositivos por OUI

El plugin incluye una tabla de OUIs con los fabricantes más comunes. A partir de los primeros 3 octetos de la MAC determina el fabricante y sugiere el tipo de dispositivo:

| Fabricantes reconocidos | Tipo asignado |
|---|---|
| Lenovo (ThinkPad/IdeaPad) | Computadora |
| Motorola Mobility / Lenovo Mobile | Teléfono |
| Samsung Electronics | Teléfono |
| HP / Dell / Asus | Computadora |
| Apple | Teléfono / Computadora |
| Huawei / Xiaomi | Teléfono |
| TP-Link / Cisco | Equipo de red |
| ZTE / Zebra / Symbol | Teléfono / Periférico |

Si el OUI no está en la tabla, el plugin intenta inferir el tipo a partir del nombre del dispositivo: un dispositivo que se llama `cel-pepito` o `HH001` se clasifica automáticamente como teléfono.

---

## Estructura del proyecto

```
freeradius/
├── setup.php               # Registro del plugin en GLPI
├── hook.php                # Instalación / desinstalación (tablas SQL)
├── inc/
│   ├── device.class.php    # Modelo de dispositivo
│   ├── config.class.php    # Configuración del plugin
│   ├── vlan.class.php      # Gestión de VLANs
│   ├── oui.class.php       # Lookup de fabricantes por MAC
│   └── radiussync.class.php # Comunicación SSH con FreeRADIUS
└── front/
    ├── dashboard.php       # Panel principal con estadísticas
    ├── device.php          # Lista de dispositivos con filtros y bulk delete
    ├── device.form.php     # Formulario de dispositivo (nuevo/editar)
    ├── merge.php           # Pantalla de reconciliación GLPI ↔ FreeRADIUS
    ├── sync.php            # Importar / exportar con FreeRADIUS
    ├── vlan.php            # Gestión de VLANs
    ├── config.php          # Configuración del plugin
    └── inventory_search.php # Endpoint AJAX para búsqueda de inventario y usuarios
```

---

## Tablas en la base de datos

El plugin crea tres tablas en la base de datos de GLPI:

**`glpi_plugin_freeradius_devices`**: el corazón del plugin. Almacena cada dispositivo con su MAC, VLAN asignada, fabricante detectado, tipo de dispositivo, estado (autorizado/bloqueado), y la referencia al ítem del inventario GLPI al que está vinculado.

**`glpi_plugin_freeradius_vlans`**: catálogo de VLANs con nombre, descripción y color para la UI.

**`glpi_plugin_freeradius_config`**: configuración de conexión al servidor FreeRADIUS (SSH, MySQL, rutas).

---

## Formato del archivo authorize

El plugin genera un archivo `authorize` compatible con FreeRADIUS 3.x en este formato:

```
# NombreDelDispositivo - usuario
aa:bb:cc:dd:ee:ff    Cleartext-Password := "aa:bb:cc:dd:ee:ff"
            Tunnel-Type = VLAN,
            Tunnel-Medium-Type = 6,
            Tunnel-Private-Group-Id = 45

DEFAULT    Auth-Type := Reject
```

La última línea rechaza cualquier dispositivo que no esté en la lista.

---

## Licencia

GPL v2+

---

*Desarrollado por [Jeremías Palazzesi](https://nerdadas.com)*
