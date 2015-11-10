(function($){
$(document).ready(function(){
	// trouver les input qui envoient des fichiers
	$bigup = $(".formulaire_spip form input[type=file].bigup");
	$bigup.each(function() {
		// trouver les paramètres ciblant le formulaire
		var name = $(this).attr('name');
		var token = $(this).data('token');
		var $form = $(this).parents('form');
		var target = $form.attr('action');
		var formulaire_action = $form.find('input[name=formulaire_action]').val();
		var formulaire_action_args = $form.find('input[name=formulaire_action_args]').val();

		// La librairie d'upload
		var flow = new Flow({
			target: target, 
			query: {
				token: token,
				formulaire_action: formulaire_action,
				formulaire_action_args: formulaire_action_args
			}
		});

		// Si l'upload html5 n'est pas supporté,
		// on laisse la gestion au formulaire… comme au bon vieux temps
		if (!flow.support) {
			return false;
		}

		$(this).hide();
		var $zone = $form.find(".dropfile_" + name).show();

		flow.assignBrowse($zone.find('.btn.televerser'));
		flow.assignDrop($zone);

		flow.on('fileAdded', function(file, event){
			console.log(file, event);
		});
		flow.on('fileSuccess', function(file, message){
			console.log(file, message);
		});
		flow.on('fileError', function(file, message){
			console.log(file, message);
		});

		return true;
	});
});
})(jQuery);
