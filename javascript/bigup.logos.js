(function($) {
	$(document).ready(function () {
		var formulaires_logos_avec_bigup = function () {

			// trouver les input qui envoient des fichiers
			$(".formulaire_editer_logo form")
				.find(".editer_logo_on, .editer_logo_off")
				.find("label").hide().end()
				.find("input[type=file].bigup")
				.not('.bigup_logo')
				.addClass('bigup_logo')
				.on('bigup.fileSuccess', function(event, file, description) {
					var bigup = file.bigup;
					var input = file.emplacement;

					var data =  bigup.getFormData();
					var data = $.extend(data, {
						formulaire_action_verifier_json: true,
						bigup_reinjecter_uniquement: [description.identifiant],
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
										bigup.presenter_succes(input, "Le logo a été envoyé"); // [TODO] Traduction
										bigup.form.parents('.formulaire_spip').parent().html(html);
									})
									.fail(function(data) {
										conteneur.endLoading();
										bigup.presenter_erreur(input, "Un problème est survenu…"); // [TODO] Traduction
									});
							}
						})
						.fail(function(data) {
							bigup.presenter_erreur(input, "Un problème est survenu…"); // [TODO] Traduction
						});

				});
		}
		formulaires_logos_avec_bigup();
		onAjaxLoad(formulaires_logos_avec_bigup);
	});
})(jQuery);