# Changelog

Tots els canvis notables d'aquest projecte es documentaran en aquest fitxer.

## [1.1.0] - 2026-01-05

### ✨ Nova Funcionalitat

#### Funcions Personalitzades
- **Execució de funcions personalitzades programades**: Ara pots programar l'execució de qualsevol funció pròpia del teu tema o plugin
- **Nou camp al meta box**: Camp per introduir el nom de la funció a executar amb validació en temps real
- **Validació d'existència**: El sistema comprova que la funció existeix abans de programar-la i abans d'executar-la
- **Suport per WP_Error**: Les funcions poden retornar `WP_Error` amb missatges descriptius
- **Captura d'excepcions**: Gestió segura d'excepcions PHP per evitar errors fatals
- **Paràmetre automàtic**: Les funcions reben el `post_id` com a paràmetre automàticament
- **Filtre extensible**: Hook `until_wp_custom_function_params` per afegir paràmetres personalitzats a les funcions
- **Integració completa**: Les funcions personalitzades apareixen a filtres, historial i notificacions

### 📚 Documentació

- **Guia completa**: Nova documentació a `docs/CUSTOM_FUNCTIONS.md` amb exemples pràctics
- **Exemples reals**: Integració amb APIs externes, enviar emails, actualitzar metadades automàticament
- **Bones pràctiques**: Guia de debug, seguretat i gestió d'errors
- **Reorganització**: Tota la documentació addicional ara està organitzada a la carpeta `docs/`

### 🔧 Millores

- **Botó de recreació de taules**: Nova opció a la pàgina d'administració per recrear les taules de BD manualment
- **Missatges d'error més detallats**: Informació específica sobre què ha fallat (funció no trobada, error de BD, etc.)
- **Gestió de timezone millorada**: Ús correcte de `current_time()` per evitar problemes amb zones horàries
- **Validació de dades reforçada**: Comprovacions més estrictes abans de guardar canvis

### 🐛 Correccions

- Correcció d'error quan les taules de BD no existeixen després d'actualitzar
- Millora en el càlcul de temps relatiu per evitar desfasaments de timezone
- Validació de format de data/hora en temps absolut
- **Millora en visualització de temps**: Ara mostra temps més precís (ex: "45 minuts" en lloc d'"1 hora" per 60 minuts)

---

## [1.0.0] - 2026-01-05

### 🎉 Llançament Inicial

Primera versió estable d'Until WP, el plugin de WordPress per programar canvis automàtics en posts.

### ✨ Característiques Principals

#### Programació de Canvis
- **Canvis d'estat de posts**: Programa la transició automàtica entre estats (Publicat, Esborrany, Pendent de revisió, Privat)
- **Gestió d'entrades fixades**: Programa quan fixar o desfixar entrades automàticament
- **Temps flexible**: Defineix canvis de forma relativa (d'aquí a X minuts/hores/dies/setmanes) o amb data i hora absolutes
- **Múltiples canvis per post**: Programa diversos canvis futurs per al mateix post

#### Interfície d'Usuari
- **Meta Box integrat**: Interfície intuïtiva a la barra lateral de l'editor de posts
- **Pàgina d'administració**: Visualització completa de tots els canvis programats amb pestanyes separades
- **Dashboard Widget**: Veu els propers canvis i l'historial recent directament des del dashboard
- **Columna a la llista de posts**: Indicador visual dels canvis programats per cada post
- **Disseny responsive**: Interfície adaptable a tots els dispositius
- **Suport dark mode**: Compatible amb el mode fosc de WordPress

#### Sistema de Notificacions
- **Notificacions d'admin**: Avisos automàtics quan s'executen canvis programats
- **Badge de comptador**: Indicador visual al menú d'Eines amb el nombre de notificacions pendents
- **Gestió de notificacions**: Descarta notificacions individualment o totes a la vegada
- **Neteja automàtica**: Les notificacions s'eliminen automàticament després de 30 dies

#### Historial i Auditoria
- **Registre complet**: Tots els canvis executats es guarden amb informació detallada
- **Consulta d'historial**: Visualitza qui, quan i què s'ha canviat en cada post
- **Filtres avançats**: Filtra per tipus de canvi, post, data, etc.
- **Estadístiques**: Informació sobre els canvis més freqüents i posts més modificats
- **Neteja automàtica**: L'historial es neteja automàticament després de 90 dies

#### Sistema Tècnic
- **WP-Cron optimitzat**: Utilitza el sistema de cron de WordPress amb comprovacions cada minut
- **Base de dades eficient**: Dues taules optimitzades per canvis programats i historial
- **Creació automàtica de taules**: Les taules es creen automàticament si no existeixen
- **Gestió d'errors robusta**: Sistema complet de logging i missatges d'error detallats
- **Debug mode**: Registre detallat d'operacions quan WP_DEBUG està activat

#### Seguretat
- **Verificació de permisos**: Control d'accés basat en capacitats de WordPress
- **Protecció CSRF**: Nonces en tots els formularis i peticions AJAX
- **Sanitització de dades**: Validació i neteja de totes les entrades d'usuari
- **Auditoria completa**: Registre de qui ha creat i executat cada canvi

#### Internacionalització
- **Text domain**: Tots els textos preparats per traducció (`until-wp`)
- **Fitxer POT inclòs**: Plantilla de traducció completa per facilitar traduccions
- **Idioma per defecte**: Interfície en català

#### Compatibilitat
- **WordPress**: 5.0 o superior
- **PHP**: 7.4 o superior
- **Editors**: Compatible amb Gutenberg i Classic Editor
- **Multisite**: Suport complet per instal·lacions multisite
- **Tipus de posts**: Funciona amb tots els post types públics

### 🔧 Tècnic

#### Arquitectura
- Estructura modular amb classes separades per cada funcionalitat
- Patró Singleton per a la classe principal
- Sistema d'hooks i filtres extensible per desenvolupadors
- Separació clara entre backend i frontend

#### Fitxers Principals
- `until-wp.php`: Fitxer principal del plugin
- `includes/class-until-wp-database.php`: Gestió de base de dades
- `includes/class-until-wp-scheduler.php`: Motor de programació i execució
- `includes/class-until-wp-metabox.php`: Interfície del meta box
- `includes/class-until-wp-admin.php`: Pàgina d'administració
- `includes/class-until-wp-notifications.php`: Sistema de notificacions
- `includes/class-until-wp-history.php`: Gestió d'historial i estadístiques
- `uninstall.php`: Neteja completa en desinstal·lar

#### Assets
- CSS modular amb suport per dark mode
- JavaScript vanilla amb jQuery per compatibilitat
- AJAX per totes les operacions sense recarregar pàgina

### 📦 Instal·lació i Actualitzacions

- Sistema d'actualitzacions automàtiques des de GitHub
- Descripció enriquida en la pàgina de detalls del plugin

### 🙏 Agraïments

Desenvolupat amb ♥️ per **Nuvol.cat**

---

Per més informació, visita [https://nuvol.cat](https://nuvol.cat) o consulta el repositori a [GitHub](https://github.com/socenpauriba/Until-WP).