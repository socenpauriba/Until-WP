<?php
/**
 * Classe per gestionar la programació i execució de canvis
 *
 * @package Until_WP
 */

// Si aquest fitxer s'accedeix directament, avortar
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Until_WP_Scheduler {
	
	/**
	 * Instància de la classe de base de dades
	 */
	private $database;
	
	/**
	 * Constructor
	 *
	 * @param Until_WP_Database $database Instància de la base de dades
	 */
	public function __construct( $database ) {
		$this->database = $database;
		$this->init_hooks();
	}
	
	/**
	 * Inicialitzar els hooks
	 */
	private function init_hooks() {
		// Hook per executar els canvis programats
		add_action( 'until_wp_check_scheduled_changes', array( $this, 'process_scheduled_changes' ) );
	}
	
	/**
	 * Processar els canvis programats pendents
	 */
	public function process_scheduled_changes() {
		// Obtenir els canvis pendents que hagin arribat al seu temps programat
		$pending_changes = $this->database->get_pending_changes( 50 );
		
		if ( empty( $pending_changes ) ) {
			return;
		}
		
		foreach ( $pending_changes as $change ) {
			$this->execute_change( $change );
		}
	}
	
	/**
	 * Executar un canvi programat
	 *
	 * @param object $change Objecte del canvi a executar
	 * @return bool True si s'ha executat correctament, false si no
	 */
	private function execute_change( $change ) {
		// Verificar que el post existeix
		$post = get_post( $change->post_id );
		
		if ( ! $post ) {
			// El post no existeix, cancel·lar el canvi
			$this->database->cancel_scheduled_change( $change->id );
			return false;
		}
		
		$old_value = '';
		$success = false;
		
		// Executar el canvi segons el tipus
		switch ( $change->change_type ) {
			case 'post_status':
				$old_value = $post->post_status;
				$success = $this->change_post_status( $change->post_id, $change->new_value );
				break;
				
			case 'sticky':
				$old_value = is_sticky( $change->post_id ) ? 'yes' : 'no';
				$success = $this->change_sticky_status( $change->post_id, $change->new_value );
				break;
				
			default:
				// Tipus de canvi desconegut
				$this->database->cancel_scheduled_change( $change->id );
				return false;
		}
		
		if ( $success ) {
			// Marcar com a executat i moure a l'historial
			$this->database->mark_as_executed( $change->id, $old_value, 0 );
			
			// Registrar la notificació
			$this->add_notification( $change, $old_value );
			
			// Disparar acció personalitzada per altres plugins
			do_action( 'until_wp_change_executed', $change, $old_value );
			
			return true;
		}
		
		return false;
	}
	
	/**
	 * Canviar l'estat d'un post
	 *
	 * @param int $post_id ID del post
	 * @param string $new_status Nou estat
	 * @return bool True si s'ha canviat, false si no
	 */
	private function change_post_status( $post_id, $new_status ) {
		// Verificar que l'estat és vàlid
		$valid_statuses = get_post_statuses();
		$valid_statuses['trash'] = __( 'Paperera', 'until-wp' );
		
		if ( ! array_key_exists( $new_status, $valid_statuses ) ) {
			return false;
		}
		
		// Actualitzar l'estat del post
		$result = wp_update_post(
			array(
				'ID' => $post_id,
				'post_status' => $new_status
			),
			true
		);
		
		return ! is_wp_error( $result );
	}
	
	/**
	 * Canviar l'estat de fixat d'un post
	 *
	 * @param int $post_id ID del post
	 * @param string $new_value 'yes' per fixar, 'no' per desfixar
	 * @return bool True si s'ha canviat, false si no
	 */
	private function change_sticky_status( $post_id, $new_value ) {
		if ( $new_value === 'yes' ) {
			stick_post( $post_id );
		} else {
			unstick_post( $post_id );
		}
		
		// Verificar que el canvi s'ha aplicat
		$is_sticky = is_sticky( $post_id );
		$expected = ( $new_value === 'yes' );
		
		return $is_sticky === $expected;
	}
	
	/**
	 * Afegir una notificació per un canvi executat
	 *
	 * @param object $change Objecte del canvi executat
	 * @param string $old_value Valor antic
	 */
	private function add_notification( $change, $old_value ) {
		$notifications = get_option( 'until_wp_notifications', array() );
		
		$notification = array(
			'id' => 'change_' . $change->id . '_' . time(),
			'post_id' => $change->post_id,
			'change_type' => $change->change_type,
			'old_value' => $old_value,
			'new_value' => $change->new_value,
			'executed_time' => current_time( 'mysql' ),
			'dismissed' => false
		);
		
		$notifications[] = $notification;
		
		// Mantenir només les últimes 50 notificacions
		if ( count( $notifications ) > 50 ) {
			$notifications = array_slice( $notifications, -50 );
		}
		
		update_option( 'until_wp_notifications', $notifications );
	}
	
	/**
	 * Programar un canvi de forma senzilla
	 *
	 * @param int $post_id ID del post
	 * @param string $change_type Tipus de canvi
	 * @param string $new_value Nou valor
	 * @param string $time_type 'relative' o 'absolute'
	 * @param array $time_data Dades del temps (segons el tipus)
	 * @return int|array ID del canvi programat o array amb error
	 */
	public function schedule_change( $post_id, $change_type, $new_value, $time_type, $time_data ) {
		// Validar el post
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'error' => __( 'El post no existeix.', 'until-wp' ) );
		}
		
		// Validar el tipus de canvi
		$valid_change_types = array( 'post_status', 'sticky' );
		if ( ! in_array( $change_type, $valid_change_types ) ) {
			return array( 'error' => __( 'Tipus de canvi invàlid.', 'until-wp' ) );
		}
		
		// Calcular el temps programat
		$scheduled_time = false;
		if ( $time_type === 'relative' ) {
			$scheduled_time = $this->calculate_relative_time( $time_data );
			if ( ! $scheduled_time ) {
				return array( 'error' => __( 'Error al calcular el temps relatiu.', 'until-wp' ) );
			}
		} elseif ( $time_type === 'absolute' ) {
			if ( ! isset( $time_data['datetime'] ) || empty( $time_data['datetime'] ) ) {
				return array( 'error' => __( 'No s\'ha proporcionat una data vàlida.', 'until-wp' ) );
			}
			$scheduled_time = $time_data['datetime'];
		} else {
			return array( 'error' => __( 'Tipus de temps invàlid.', 'until-wp' ) );
		}
		
		if ( ! $scheduled_time ) {
			return array( 'error' => __( 'Error al processar el temps programat.', 'until-wp' ) );
		}
		
		// Obtenir l'usuari actual
		$current_user_id = get_current_user_id();
		if ( ! $current_user_id ) {
			$current_user_id = 1; // Fallback a admin
		}
		
		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Until WP - Guardant a BD: post_id=' . $post_id . ', change_type=' . $change_type . ', new_value=' . $new_value . ', scheduled_time=' . $scheduled_time . ', user_id=' . $current_user_id );
		}
		
		// Afegir el canvi a la base de dades
		$change_id = $this->database->add_scheduled_change(
			$post_id,
			$change_type,
			$new_value,
			$scheduled_time,
			$current_user_id
		);
		
		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Until WP - Resultat de BD: ' . ( $change_id ? 'ID=' . $change_id : 'FALSE' ) );
			global $wpdb;
			if ( $wpdb->last_error ) {
				error_log( 'Until WP - Error MySQL: ' . $wpdb->last_error );
			}
		}
		
		if ( ! $change_id ) {
			global $wpdb;
			$error_msg = __( 'Error al guardar el canvi a la base de dades.', 'until-wp' );
			if ( $wpdb->last_error ) {
				$error_msg .= ' MySQL: ' . $wpdb->last_error;
			}
			return array( 'error' => $error_msg );
		}
		
		return $change_id;
	}
	
	/**
	 * Calcular el temps relatiu
	 *
	 * @param array $time_data Dades del temps relatiu
	 * @return string|false Temps programat en format MySQL o false si error
	 */
	private function calculate_relative_time( $time_data ) {
		if ( ! isset( $time_data['amount'] ) || ! isset( $time_data['unit'] ) ) {
			return false;
		}
		
		$amount = absint( $time_data['amount'] );
		$unit = $time_data['unit'];
		
		if ( $amount <= 0 ) {
			return false;
		}
		
		// Convertir a segons
		$seconds = 0;
		switch ( $unit ) {
			case 'minutes':
				$seconds = $amount * 60;
				break;
			case 'hours':
				$seconds = $amount * 3600;
				break;
			case 'days':
				$seconds = $amount * 86400;
				break;
			case 'weeks':
				$seconds = $amount * 604800;
				break;
			default:
				return false;
		}
		
		// Calcular el timestamp utilitzant el temps actual de WordPress
		$current_timestamp = current_time( 'timestamp' );
		$scheduled_timestamp = $current_timestamp + $seconds;
		
		// Convertir a format MySQL utilitzant el timezone de WordPress
		return date( 'Y-m-d H:i:s', $scheduled_timestamp );
	}
	
	/**
	 * Cancel·lar un canvi programat
	 *
	 * @param int $change_id ID del canvi
	 * @return bool True si s'ha cancel·lat, false si no
	 */
	public function cancel_change( $change_id ) {
		// Verificar que l'usuari té permisos
		if ( ! current_user_can( 'edit_posts' ) ) {
			return false;
		}
		
		$change = $this->database->get_scheduled_change( $change_id );
		
		if ( ! $change ) {
			return false;
		}
		
		// Verificar que l'usuari pot editar aquest post o és administrador
		if ( ! current_user_can( 'edit_post', $change->post_id ) && ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		
		return $this->database->cancel_scheduled_change( $change_id );
	}
	
	/**
	 * Obtenir el text descriptiu d'un canvi
	 *
	 * @param object $change Objecte del canvi
	 * @return string Text descriptiu
	 */
	public function get_change_description( $change ) {
		$post = get_post( $change->post_id );
		$post_title = $post ? $post->post_title : __( 'Post eliminat', 'until-wp' );
		
		switch ( $change->change_type ) {
			case 'post_status':
				$statuses = get_post_statuses();
				$status_name = isset( $statuses[ $change->new_value ] ) ? $statuses[ $change->new_value ] : $change->new_value;
				return sprintf(
					/* translators: 1: Post title, 2: New status */
					__( 'Canviar "%1$s" a %2$s', 'until-wp' ),
					$post_title,
					$status_name
				);
				
			case 'sticky':
				$action = ( $change->new_value === 'yes' ) ? __( 'fixar', 'until-wp' ) : __( 'desfixar', 'until-wp' );
				return sprintf(
					/* translators: 1: Action (fixar/desfixar), 2: Post title */
					__( '%1$s "%2$s"', 'until-wp' ),
					ucfirst( $action ),
					$post_title
				);
				
			default:
				return __( 'Canvi desconegut', 'until-wp' );
		}
	}
}

