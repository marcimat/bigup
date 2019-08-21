/** Gérer le formulaire de logo avec Bigup */
function formulaires_logos_avec_bigup() {
	// trouver les input qui envoient des fichiers
	$(".formulaire_editer_logo form")
		.find(".editer_logo_on, .editer_logo_off")
		.find("label").hide().end()
		.find("input[type=file].bigup_logo")
		.not('.bigup_done')
		.bigup()
		.on('bigup.fileSuccess', function(event, file, description) {
			var bigup = file.bigup;
			var input = file.emplacement;

			var data = $.extend(bigup.getFormData(), {
				formulaire_action_verifier_json: true,
				bigup_reinjecter_uniquement: [description.bigup.identifiant],
			});

			// verifier les champs
			$.post(bigup.target, data, null, 'json')
				.done(function(erreurs) {
					var erreur = data.logo_on || data.logo_off || erreurs.message_erreur;
					if (erreur) {
						bigup.presenter_erreur(input, erreur);
					} else {
						delete data.formulaire_action_verifier_json;
						var conteneur = bigup.form.parents('.formulaire_editer_logo');
						conteneur.animateLoading();
						// Faire le traitement prévu, supposant qu'il n'y aura pas d'erreur...
						$.post(bigup.target, data)
							.done(function(html) {
								bigup.presenter_succes(input, _T('bigup:succes_logo_envoye'));
								bigup.form.parents('.formulaire_spip').parent().html(html);
							})
							.fail(function(data) {
								conteneur.endLoading();
								bigup.presenter_erreur(input, _T('bigup:erreur_probleme_survenu'));
							});
					}
				})
				.fail(function(data) {
					bigup.presenter_erreur(input, _T('bigup:erreur_probleme_survenu'));
				});
		})
		.closest('.editer').find('.dropfiletext').html(_T('bigup:deposer_le_logo_ici'));

	;
}

jQuery(function($) {
	formulaires_logos_avec_bigup();
	onAjaxLoad(formulaires_logos_avec_bigup);
});
