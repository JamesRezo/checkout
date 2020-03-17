# checkout : script de checkout multi-méthodes permettant une syntaxe unifiee git/svn/ftp

## syntaxe type : 
```
checkout.php [methode] [-rRevision] [-bBranche] repoSource dirDest
```

## Installation : 
- Linux / MacOS : 
	- cloner/télécharger le dossier *checkout* dans un répertoire de la machine, par exemple */home/toto/checkout* (Linux) ou */Users/toto/checkout* (MacOS)
	- intégrer le chemin du fichier *checkout.php* dans le *$PATH* de la machine ce qui peut être fait, par exemple, avec un lien symbolique dans */usr/bin* par la commande :
```
sudo ln -s /home/toto/checkout/checkout.php /usr/bin/checkout.php
```
- Windows : 
	- s'assurer que GitBash est bien installé sur la machine (voir par exemple https://gitforwindows.org/).
	NB : Toutes les commandes ```checkout...``` ci-dessous seront saisie dans une invite de commande *GitBash* et non pas dans l'interpréteur de commande de Windows (cmd.exe)
	- cloner/télécharger le dossier *checkout* dans un répertoire de la machine, par exemple *c:\laragon\bin\checkout* ou *c:\xamp\checkout*
	- intégrer le chemin du dossier checkout dans le PATH de la machine : 
	Panneau de configuration > Système > Avancé > Variables d'environnement > variables système : Variable Path ⇒ Modifier > ajouter en fin de chaîne ```;c:\laragon\bin\checkout```
- Tous les OS : tester que l'utilitaire checkout.php est opérationnel : 
```
checkout.php --help
```
doit retourner le *help* de l'utilitaire

## Exemples :
### Checkout SPIP (core+externals) :
- installer la version de dev de SPIP + tous les plugins-dist + squelettes-dist dans un répertoire *dossier_destination* (ne doit pas exister):
	- ouvrir une invite de commande dans le dossier où doit être installé le SPIP et appeler le script checkout.php avec l'option `spip`
```
checkout.php spip dossier_destination
```
- installer la version 3.2 de SPIP + tous les plugins-dist + squelettes-dist :
```
checkout.php spip -bspip-3.2 dossier_destination
```
### Mise à jour d'un SPIP installé avec checkout.php:
- en général il suffit de rejouer la commande d'installation : par exemple
```
checkout.php spip dossier_destination
```

### Checkout un repo :
```
checkout.php svn -r1234 svn://example.org/repo dossier_destination
```
```
checkout.php git -re1ad434 git://example.org/repo dossier_destination
```
```
checkout.php git -re1ad434 -bmaster git://example.org/repo dossier_destination
```
```
checkout.php ftp http://example.org/paquet.zip dossier_destination
```

### Recuperer la commande correspondant a un repo deja checkout
```
checkout.php --read dossier_destination
```

### Voir les logs des mises a jour disponibles pour un repertoire
```
checkout.php --logupdate dossier_destination
```

Si le repo est en git et que le repo est DETACHED,
indiquer une branche pour se limiter aux mises à jour disponibles sur la branche qui vous interesse :
```
checkout.php --logupdate -bmaster dossier_destination
```

