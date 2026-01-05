/**
 * Scripts d'administració per Until WP
 */

(function($) {
	'use strict';
	
	// Objecte principal
	var UntilWP = {
		
		/**
		 * Inicialitzar
		 */
		init: function() {
			this.initTabs();
			this.initMetabox();
			this.initAdminPage();
			this.initSelectAll();
		},
		
		/**
		 * Inicialitzar les tabs
		 */
		initTabs: function() {
			$('.until-wp-tab-btn').on('click', function(e) {
				e.preventDefault();
				
				var $btn = $(this);
				var tab = $btn.data('tab');
				
				// Canviar botó actiu
				$btn.siblings().removeClass('active');
				$btn.addClass('active');
				
				// Canviar contingut actiu
				$btn.closest('.until-wp-time-tabs').find('.until-wp-tab-content').removeClass('active');
				$('#until-wp-tab-' + tab).addClass('active');
			});
		},
		
		/**
		 * Inicialitzar el meta box
		 */
		initMetabox: function() {
			// Botó per programar canvi
			$('#until-wp-add-change').on('click', function(e) {
				e.preventDefault();
				UntilWP.scheduleChange();
			});
			
			// Botons per cancel·lar canvis
			$('.until-wp-cancel-change').on('click', function(e) {
				e.preventDefault();
				var changeId = $(this).data('change-id');
				UntilWP.cancelChange(changeId, $(this));
			});
		},
		
		/**
		 * Inicialitzar la pàgina d'administració
		 */
		initAdminPage: function() {
			// Botons per cancel·lar canvis individuals
			$('.until-wp-cancel-single').on('click', function(e) {
				e.preventDefault();
				var changeId = $(this).data('change-id');
				UntilWP.cancelChangeAdmin(changeId, $(this));
			});
		},
		
		/**
		 * Inicialitzar el checkbox "Seleccionar tot"
		 */
		initSelectAll: function() {
			$('#cb-select-all').on('change', function() {
				var checked = $(this).prop('checked');
				$('input[name="change_ids[]"]').prop('checked', checked);
			});
		},
		
		/**
		 * Programar un canvi
		 */
		scheduleChange: function() {
			// Obtenir valors
			var postId = $('#until-wp-post-id').val();
			var changeTypeFull = $('#until-wp-change-type').val();
			var activeTab = $('.until-wp-tab-btn.active').data('tab');
			
			// Validar tipus de canvi
			if (!changeTypeFull) {
				this.showMessage('error', untilWP.i18n.select_change_type);
				return;
			}
			
			// Preparar dades
			var data = {
				action: 'until_wp_schedule_change',
				nonce: untilWP.nonce,
				post_id: postId,
				change_type: changeTypeFull,
				time_type: activeTab
			};
			
			// Afegir dades del temps segons la tab activa
			if (activeTab === 'relative') {
				var amount = parseInt($('#until-wp-relative-amount').val());
				var unit = $('#until-wp-relative-unit').val();
				
				if (!amount || amount <= 0) {
					this.showMessage('error', untilWP.i18n.invalid_amount);
					return;
				}
				
				data.relative_amount = amount;
				data.relative_unit = unit;
			} else if (activeTab === 'absolute') {
				var datetime = $('#until-wp-absolute-datetime').val();
				
				if (!datetime) {
					this.showMessage('error', untilWP.i18n.invalid_datetime);
					return;
				}
				
				data.absolute_datetime = datetime;
			}
			
			// Mostrar spinner
			this.showSpinner();
			
			// Fer la petició AJAX
			$.post(untilWP.ajax_url, data, function(response) {
				UntilWP.hideSpinner();
				
				if (response.success) {
					UntilWP.showMessage('success', response.data.message);
					
					// Afegir el nou canvi a la llista
					UntilWP.addChangeToList(response.data.change);
					
					// Netejar el formulari
					UntilWP.resetForm();
				} else {
					UntilWP.showMessage('error', response.data.message || untilWP.i18n.error);
				}
			}).fail(function() {
				UntilWP.hideSpinner();
				UntilWP.showMessage('error', untilWP.i18n.error);
			});
		},
		
		/**
		 * Cancel·lar un canvi (des del meta box)
		 */
		cancelChange: function(changeId, $button) {
			if (!confirm(untilWP.i18n.confirm_cancel)) {
				return;
			}
			
			var data = {
				action: 'until_wp_cancel_change',
				nonce: untilWP.nonce,
				change_id: changeId
			};
			
			$button.prop('disabled', true).text('...');
			
			$.post(untilWP.ajax_url, data, function(response) {
				if (response.success) {
					// Eliminar l'element de la llista
					$button.closest('li').fadeOut(function() {
						$(this).remove();
						
						// Si no hi ha més canvis, mostrar missatge
						if ($('.until-wp-scheduled-list li').length === 0) {
							$('.until-wp-scheduled-list').fadeOut();
							$('.until-wp-no-changes').fadeIn();
						}
					});
					
					UntilWP.showMessage('success', response.data.message);
				} else {
					$button.prop('disabled', false).text(untilWP.i18n.cancel || 'Cancel·lar');
					UntilWP.showMessage('error', response.data.message || untilWP.i18n.error);
				}
			}).fail(function() {
				$button.prop('disabled', false).text(untilWP.i18n.cancel || 'Cancel·lar');
				UntilWP.showMessage('error', untilWP.i18n.error);
			});
		},
		
		/**
		 * Cancel·lar un canvi (des de la pàgina d'admin)
		 */
		cancelChangeAdmin: function(changeId, $button) {
			if (!confirm(untilWP.i18n.confirm_cancel)) {
				return;
			}
			
			var data = {
				action: 'until_wp_admin_cancel_change',
				change_id: changeId
			};
			
			$button.prop('disabled', true).text('...');
			
			$.post(untilWP.ajax_url, data, function(response) {
				if (response.success) {
					// Recarregar la pàgina per actualitzar la llista
					location.reload();
				} else {
					$button.prop('disabled', false).text('Cancel·lar');
					alert(response.data.message || untilWP.i18n.error);
				}
			}).fail(function() {
				$button.prop('disabled', false).text('Cancel·lar');
				alert(untilWP.i18n.error);
			});
		},
		
		/**
		 * Afegir un canvi a la llista
		 */
		addChangeToList: function(change) {
			// Amagar el missatge "No hi ha canvis"
			$('.until-wp-no-changes').hide();
			
			// Si la llista no existeix, crear-la
			if ($('.until-wp-scheduled-list').length === 0) {
				$('.until-wp-metabox').append(
					'<div class="until-wp-scheduled-list">' +
					'<h4>Canvis Programats</h4>' +
					'<ul></ul>' +
					'</div>'
				);
			}
			
			// Crear el nou element
			var $newItem = $('<li data-change-id="' + change.id + '">' +
				'<div class="until-wp-change-item">' +
				'<div class="until-wp-change-info">' +
				'<strong>' + this.escapeHtml(change.label) + '</strong><br>' +
				'<span class="until-wp-change-time">' + this.escapeHtml(change.time) + '</span>' +
				'</div>' +
				'<div class="until-wp-change-actions">' +
				'<button type="button" class="button button-small until-wp-cancel-change" data-change-id="' + change.id + '">Cancel·lar</button>' +
				'</div>' +
				'</div>' +
				'</li>');
			
			// Afegir a la llista
			$('.until-wp-scheduled-list ul').prepend($newItem);
			
			// Inicialitzar el botó de cancel·lar
			$newItem.find('.until-wp-cancel-change').on('click', function(e) {
				e.preventDefault();
				var changeId = $(this).data('change-id');
				UntilWP.cancelChange(changeId, $(this));
			});
			
			// Mostrar la llista
			$('.until-wp-scheduled-list').show();
		},
		
		/**
		 * Netejar el formulari
		 */
		resetForm: function() {
			$('#until-wp-change-type').val('');
			$('#until-wp-relative-amount').val(1);
			$('#until-wp-relative-unit').val('hours');
			$('#until-wp-absolute-datetime').val('');
		},
		
		/**
		 * Mostrar un missatge
		 */
		showMessage: function(type, message) {
			var $messageBox = $('#until-wp-message');
			
			$messageBox
				.removeClass('success error info')
				.addClass(type)
				.html(message)
				.fadeIn();
			
			// Amagar automàticament després de 5 segons
			setTimeout(function() {
				$messageBox.fadeOut();
			}, 5000);
		},
		
		/**
		 * Mostrar spinner
		 */
		showSpinner: function() {
			$('.until-wp-spinner').show();
			$('#until-wp-add-change').prop('disabled', true);
		},
		
		/**
		 * Amagar spinner
		 */
		hideSpinner: function() {
			$('.until-wp-spinner').hide();
			$('#until-wp-add-change').prop('disabled', false);
		},
		
		/**
		 * Escapar HTML
		 */
		escapeHtml: function(text) {
			var map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return text.replace(/[&<>"']/g, function(m) { return map[m]; });
		}
	};
	
	// Inicialitzar quan el document estigui llest
	$(document).ready(function() {
		UntilWP.init();
	});
	
	// Fer accessible globalment per si cal
	window.UntilWP = UntilWP;
	
})(jQuery);

