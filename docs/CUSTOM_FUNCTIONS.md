# Funcions Personalitzades amb Until WP

A partir de la versió 1.1.0, Until WP suporta l'execució de funcions personalitzades programades. Això et permet executar qualsevol funció pròpia al teu tema o plugin quan arribi el moment programat.

## Com Funciona

Quan programes una funció personalitzada:
1. La funció rebrà el `post_id` com a paràmetre
2. S'executarà al moment programat pel WP-Cron
3. El resultat es registrarà a l'historial

## Exemple Bàsic

### 1. Definir la Funció al teu `functions.php`

```php
/**
 * Sincronitzar amb servei extern
 */
function sincronitzar_amb_api_externa( $post_id ) {
    $post = get_post( $post_id );
    
    if ( ! $post || $post->post_status !== 'publish' ) {
        return new WP_Error( 'invalid_post', 'El post ha de estar publicat' );
    }
    
    $api_url = 'https://api.exemple.com/sync';
    $api_key = get_option( 'api_externa_key' );
    
    $response = wp_remote_post( $api_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ),
        'body' => wp_json_encode( array(
            'post_id' => $post_id,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'url' => get_permalink( $post_id ),
        ) ),
        'timeout' => 30,
    ) );
    
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    
    $code = wp_remote_retrieve_response_code( $response );
    
    if ( $code !== 200 ) {
        return new WP_Error( 'api_error', 'Error en la resposta de l\'API: ' . $code );
    }
    
    return true;
}
```

### 2. Programar l'Execució

1. Obre el post a l'editor
2. Al meta box "Programar Canvis", selecciona "Executar funció personalitzada"
3. Introdueix el nom de la funció: `sincronitzar_amb_api_externa`
4. Selecciona quan s'ha d'executar (relatiu o absolut)
5. Fes clic a "Programar Canvi"

## Valors de Retorn

La teva funció hauria de retornar:

- **`true`**: Èxit (es registrarà com a executat correctament)
- **`false`**: Error (es registrarà com a fallat)
- **`WP_Error`**: Error amb missatge (es registrarà el missatge al log)
- **Qualsevol altre valor**: Es considera èxit

## Paràmetres Personalitzats (Avançat)

Si necessites passar més paràmetres a la teva funció, pots utilitzar el filtre `until_wp_custom_function_params`:

```php
/**
 * Afegir paràmetres addicionals a la funció
 */
add_filter( 'until_wp_custom_function_params', function( $params, $function_name, $post_id ) {
    if ( $function_name === 'la_meva_funcio_especial' ) {
        // Afegir més paràmetres
        $params[] = get_option( 'alguna_opcio' );
        $params[] = array( 'extra' => 'data' );
    }
    return $params;
}, 10, 3 );

/**
 * La funció rebrà els paràmetres addicionals
 */
function la_meva_funcio_especial( $post_id, $opcio, $extra_data ) {
    // Fer alguna cosa amb tots els paràmetres
    return true;
}
```

## Bones Pràctiques

1. **Sempre valida l'entrada**: Comprova que el post existeix i és vàlid
2. **Gestiona errors**: Retorna `WP_Error` amb missatges descriptius
3. **Verifica permisos**: Encara que Until WP ja ho fa, és bona pràctica
4. **Usa timeouts**: Si fas peticions externes, defineix timeouts raonables
5. **Registra al log**: Usa `error_log()` en mode debug per facilitar el debugging
6. **Testeja primer**: Executa la funció manualment abans de programar-la

## Debug

Per veure què està passant amb les teves funcions personalitzades, activa el mode debug de WordPress:

```php
// wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Després revisa el fitxer `wp-content/debug.log` per veure els missatges.

## Seguretat

Until WP valida que:
- La funció existeix abans de programar-la
- La funció existeix abans d'executar-la
- Captura excepcions per evitar errors fatals
- Registra tots els errors al log si WP_DEBUG està activat

## Suport

Si tens problemes amb funcions personalitzades:
1. Revisa el log de debug
2. Testeja la funció manualment primer
3. Consulta l'historial de canvis a Until WP
4. Obre un issue a GitHub amb detalls complets

---

Desenvolupat amb ♥️ per [Nuvol.cat](https://nuvol.cat)

