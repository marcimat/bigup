/** Gérer le formulaire de documents avec Bigup */
function formulaires_documents_avec_bigup () {
	// trouver les input qui envoient des fichiers
	$(".formulaire_joindre_document form .editer_fichier_upload")
		.find("label").hide().end()
		.find("input[type=file].bigup")
		.not('.bigup_document')
		.addClass('bigup_document')
		.on('bigup.fileSuccess', function(event, file, description) {
			var bigup = file.bigup;
			var input = file.emplacement;

			var data = $.extend(bigup.getFormData(), {
				joindre_upload: true,
				joindre_zip: true, // les zips sont conservés zippés systématiquement.
				formulaire_action_verifier_json: true,
				bigup_reinjecter_uniquement: [description.bigup.identifiant],
			});

			// verifier les champs
			$.post(bigup.target, data, null, 'json')
				.done(function(erreurs) {
					var erreur = data.fichier_upload || erreurs.message_erreur;
					if (erreur) {
						bigup.presenter_erreur(input, erreur);
					} else {
						delete data.formulaire_action_verifier_json;
						// Faire le traitement prévu, supposant qu'il n'y aura pas d'erreur...
						$.post(bigup.target, data)
							.done(function(html) {
								var message = $(html).find('.reponse_formulaire').html();
								if (message) {
									bigup.presenter_succes(input, message);
								} else {
									bigup.presenter_erreur(input, _T('bigup:erreur_probleme_survenu'));
								}
								input.addClass('remove').animate({opacity: "0.0"}, 'fast', function(){
									// autoriser de mettre une seconde fois le fichier
									file.bigup_deleted = true;
									file.cancel();
									input.remove();
								});
							})
							.fail(function(data) {
								bigup.presenter_erreur(input, _T('bigup:erreur_probleme_survenu'));
							});
					}
				})
				.fail(function(data) {
					bigup.presenter_erreur(input, _T('bigup:erreur_probleme_survenu'));
				});
		});
	$("input[name='joindre_upload']").hide();
}
jQuery(function($) {
	formulaires_documents_avec_bigup();
	onAjaxLoad(formulaires_documents_avec_bigup);
});
