(function($) {
	$(document).ready(function () {
		var formulaires_documents_avec_bigup = function () {
			// trouver les input qui envoient des fichiers
			$(".formulaire_joindre_document form input[type=file].bigup_documents")
				.bigup()
				.on('bigup.fileSuccess', function(event, file, description) {
					var bigup = file.bigup;
					var data  = bigup.getFormData();
					var input = file.emplacement;

					input.css('opacity', "0.5");

					$.post(bigup.target, data)
						.done(function() {
							input.addClass('remove').animate({opacity: "0.0"}, 'fast', function(){
								input.removeClass('remove');
								ajaxReload('documents');
								input.remove();
							});
						})
						.fail(function() {
							input.css('opacity', 1);
							bigup.presenter_erreur(input, "Un problème est survenu…"); // [TODO] Traduction
						});


				});
		}
		formulaires_documents_avec_bigup();
		onAjaxLoad(formulaires_documents_avec_bigup);
	});
})(jQuery);