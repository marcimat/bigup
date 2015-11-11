# Big up (big upload)

Plugin SPIP permettant de téléverser de gros fichiers dans SPIP dans des formulaires CVT.
Fournit une API (proche du plugin cvt_upload).


## Todo

- Générer un token pour chaque champ et le valider côté PHP
- Gérer un affichage d'erreur
- Ne pas pouvoir envoyer des fichiers plus gros que la taille maxi
-- côté PHP
-- côté JS
- Pouvoir restreindre par mime type
- Pouvoir restreindre par extension
- Afficher la progression
- Afficher un bouton pour annuler (JS)
- Lister les fichiers déjà chargés pour chaque champ
- Permettre de les supprimer

- Yaml des saisies
- Pouvoir intégrer une saisie de documents sur champs extras
  Cela questionne : qu'est-ce qu'on enregistre et où ?
  Crée t'on un champ dans la table (c'est la logique de champs extras)
  ou ajoute t'on des liens sur l'objet sur spip_documents_liens…


