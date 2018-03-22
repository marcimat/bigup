jQuery(function($){
	var formulaires_avec_bigup = function() {
		// trouver les input qui envoient des fichiers
		$(".formulaire_spip form input[type=file].bigup").bigup();
	}
	formulaires_avec_bigup();
	onAjaxLoad(formulaires_avec_bigup);
});