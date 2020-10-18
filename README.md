# Script de checkout multi-méthodes permettant une syntaxe unifiée git/svn/ftp**

Le script `checkout` permet de télécharger ou mettre à jour des sources Git ou Svn (ou Zip), et propose également une méthode pour télécharger les sources de SPIP.

## syntaxe type

```bash
checkout [methode] [-rRevision] [-bBranche] repoSource dirDest
```

## Installation

### Linux / MacOS

- cloner/télécharger le dossier *checkout* dans un répertoire de la machine, par exemple */home/toto/checkout* (Linux) ou */Users/toto/checkout* (MacOS)
- intégrer le chemin du fichier *checkout* dans le *$PATH* de la machine ce qui peut être fait, par exemple, avec un lien symbolique dans */usr/bin* par la commande :

```bash
sudo ln -s /home/toto/checkout/checkout /usr/bin/checkout
```

### Windows

- s'assurer que GitBash est bien installé sur la machine (voir par exemple [Git for Windows](https://gitforwindows.org/)).
  NB : Toutes les commandes `checkout...` ci-dessous seront saisies dans une invite de commande *GitBash* et non pas dans l'interpréteur de commande de Windows (cmd.exe)
- cloner/télécharger le dossier *checkout* dans un répertoire de la machine, par exemple *c:\laragon\bin\checkout* ou *c:\xamp\checkout*
- intégrer le chemin du dossier checkout dans le PATH de la machine : Panneau de configuration > Système > Avancé > Variables d'environnement > variables système : Variable Path ⇒ Modifier > ajouter en fin de chaîne `;c:\laragon\bin\checkout`

### Docker

Cet outils est inclus dans l'image docker [spip/tools](https://hub.docker.com/r/spip/tools).

Si vous souhaitez l'intégrer à vos propres images docker, il est recommandé de procéder comme suit dans le fichier
`Dockerfile` de votre image.

```Dockerfile
COPY --from=spipremix/checkout /checkout /usr/local/bin/checkout
```

N'utilisez pas l'image `spipremix/checkout` telle quelle, elle n'est pas prévue pour cela.

### Tous les OS

Tester que l'utilitaire checkout est opérationnel :

```bash
checkout --help
```

doit retourner le *help* de l'utilitaire

## Exemples

### Checkout SPIP (core+externals)

#### Version dev

Installer la version de dev de SPIP + tous les plugins-dist + squelettes-dist dans un répertoire *dossier_destination* (ne doit pas exister):

Ouvrir une invite de commande dans le dossier où doit être installé le SPIP et appeler le script checkout avec l'option `spip`

```bash
checkout spip dossier_destination
```

#### Branche spécifique

Installer la version 3.2 de SPIP + tous les plugins-dist + squelettes-dist :

```bash
checkout spip -b3.2 dossier_destination
```

#### Installation SSH

Installation en SSH pour les commandes git à la place de HTTPS (permet de faciliter les commits à partir de cette installation) :

```bash
checkout spip -bmaster git@git.spip.net dossier_destination
```

*NB :* sous Windows les utilisateurs de Putty devront d'abord faire une tentative de connexion sur `git@git.spip.net:spip/spip.git` afin d'intégrer "l'empreinte ssh" de git.spip.net aux serveurs autorisés.

### Mise à jour d'un SPIP installé avec checkout

En général il suffit de rejouer la commande d'installation, par exemple :

```bash
checkout spip dossier_destination
```

### montée de version d'un SPIP installé avec checkout

Il suffit de jouer la commande en indiquant la nouvelle version dans le paramètre *-b* :
par exemple pour passer un SPIP en version de dev (branche *master* donc)

```bash
checkout spip -bmaster dossier_destination
```

Le script va faire la mise à jour des fichiers puis passer sur la version master non seulement pour le core mais aussi pour squelettes-dist et tous les plugins-dist

### Checkout un repo

```bash
# à une révision SVN
checkout svn -r1234 svn://example.org/repo dossier_destination
# à une révision GIT
checkout git -re1ad434 git://example.org/repo dossier_destination
# à une révision et branche Git
checkout git -re1ad434 -bmaster git://example.org/repo dossier_destination
# un zip quelconque
checkout ftp http://example.org/paquet.zip dossier_destination
```

### Recuperer la commande correspondant à un repo déjà checkout

```bash
checkout --read dossier_destination
```

### Voir les logs des mises à jour disponibles pour un répertoire

```bash
checkout --logupdate dossier_destination
```

Si le repo est en git et que le repo est DETACHED,
indiquer une branche pour se limiter aux mises à jour disponibles sur la branche qui vous intéresse :

```bash
checkout --logupdate -bmaster dossier_destination
```

## Références

Récapitulatif sur les outils d'installation en ligne de commande de SPIP : [Installer un SPIP en Git](https://blog.smellup.net/spip.php?article117)
