jQuery(function($){
	var formulaires_avec_bigup = function() {
		$.bigup_verifier_depots_etendus();
		// trouver les input qui envoient des fichiers, mais une fois l'upload en cours fini
		setTimeout(function(){$(".formulaire_spip form input[type=file].bigup").bigup();},10)
	}
	formulaires_avec_bigup();
	onAjaxLoad(formulaires_avec_bigup);
});