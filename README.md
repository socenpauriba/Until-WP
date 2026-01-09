# Until WP - Programar Canvis en Posts de WordPress

**Version:** 1.1.1  
**Requereix:** WordPress 5.0+  
**Requereix PHP:** 7.4+  
**Llicència:** GPL v2 or later

## Descripció

Until WP és un plugin de WordPress que et permet programar canvis automàtics en els teus posts. Pots configurar canvis d'estat, fixar o desfixar entrades a una data i hora específiques o de forma relativa (d'aquí a X hores/dies).

### Característiques Principals

- **Programar canvis d'estat**: Canvia posts de publicat a esborrany, de pendent a publicat, etc.
- **Fixar/Desfixar entrades**: Programa quan una entrada s'ha de fixar o desfixar
- **Funcions personalitzades**: Executa les teves pròpies funcions al moment programat
- **Programació flexible**: Defineix canvis de forma relativa (d'aquí a 2 dies) o absoluta (data específica)
- **Interfície integrada**: Meta box a l'editor de posts per una gestió fàcil
- **Pàgina d'administració**: Visualitza tots els canvis programats i l'historial
- **Notificacions**: Rep notificacions quan s'executen canvis programats
- **Widget al dashboard**: Veu els propers canvis i l'historial recent
- **Historial complet**: Seguiment de tots els canvis executats
- **Compatible amb Gutenberg i Classic Editor**

## Instal·lació

1. Puja la carpeta `until-wp` al directori `/wp-content/plugins/`
2. Activa el plugin des del menú 'Plugins' de WordPress
3. El plugin crearà automàticament les taules necessàries i programarà els eventos de cron

## Ús

### Programar un canvi des de l'editor de posts

1. Obre un post per editar-lo
2. Busca el meta box "Programar Canvis" a la barra lateral dreta
3. Selecciona el tipus de canvi que vols programar:
   - Canviar estat (Publicat, Esborrany, Pendent, Privat)
   - Fixar entrada
   - Desfixar entrada
4. Defineix quan s'ha d'executar:
   - **Relatiu**: D'aquí a X minuts/hores/dies/setmanes
   - **Absolut**: Data i hora específiques
5. Fes clic a "Programar Canvi"

### Gestionar canvis programats

1. Ves a **Eines > Canvis Programats**
2. A la pestanya "Programats":
   - Veu tots els canvis pendents
   - Filtra per tipus de canvi
   - Cancel·la canvis individuals o en massa
3. A la pestanya "Historial":
   - Consulta tots els canvis executats
   - Filtra per data i tipus

### Dashboard Widget

Al dashboard de WordPress, trobaràs un widget que mostra:
- Els propers 5 canvis programats
- Els últims 5 canvis executats

### Funcions Personalitzades

A més dels canvis predefinits, pots executar les teves pròpies funcions:

1. Defineix una funció al `functions.php` del teu tema
2. Al meta box, selecciona "Executar funció personalitzada"
3. Introdueix el nom de la funció (ex: `processar_post_automaticament`)
4. Programa quan s'ha d'executar

**La funció rebrà el `post_id` com a paràmetre automàticament.**

**Exemple:**
```php
function processar_post_automaticament( $post_id ) {
    // La teva lògica aquí
    $post = get_post( $post_id );
    // ... fer alguna cosa amb el post
    return true; // o false si hi ha error
}
```

📖 **Documentació completa**: Consulta [docs/CUSTOM_FUNCTIONS.md](docs/CUSTOM_FUNCTIONS.md) per exemples detallats i bones pràctiques.

## Compatibilitat

- **WordPress**: 5.0 o superior
- **PHP**: 7.4 o superior
- **Editors**: Compatible amb Gutenberg i Classic Editor
- **Multisite**: Totalment compatible

## Desenvolupament

### Estructura del Plugin

```
until-wp/
├── until-wp.php                          # Fitxer principal
├── includes/                             # Classes PHP
│   ├── class-until-wp-database.php       # Gestió de BD
│   ├── class-until-wp-scheduler.php      # Programació i execució
│   ├── class-until-wp-metabox.php        # Meta box
│   ├── class-until-wp-admin.php          # Pàgina d'admin
│   ├── class-until-wp-notifications.php  # Notificacions
│   └── class-until-wp-history.php        # Historial
├── assets/                               # Recursos
│   ├── css/
│   │   └── admin-styles.css             # Estils
│   └── js/
│       └── admin-scripts.js             # Scripts
├── languages/                            # Traduccions
│   └── until-wp.pot                     # Plantilla de traducció
└── uninstall.php                        # Script de desinstal·lació
```

### Hooks Disponibles

El plugin proporciona hooks per a desenvolupadors:

```php
// Acció que s'executa quan un canvi programat s'aplica
do_action( 'until_wp_change_executed', $change, $old_value );
```

## Documentació

### Documentació Addicional

- 📖 **[Funcions Personalitzades](docs/CUSTOM_FUNCTIONS.md)** - Guia completa per utilitzar funcions personalitzades
- 📋 **[CHANGELOG.md](CHANGELOG.md)** - Historial complet de canvis

Tota la documentació addicional es troba a la carpeta [`docs/`](docs/).

## Contribuir

Les contribucions són benvingudes! Si vols contribuir:
1. Fork el repositori
2. Crea una branca per la teva funcionalitat
3. Fes els teus canvis
4. Envia un Pull Request

## Suport

Per informar d'errors o sol·licitar funcionalitats:
- GitHub Issues: https://github.com/socenpauriba/until-wp/issues

## Llicència

Aquest plugin està llicenciat sota GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

