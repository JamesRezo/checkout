#!/usr/bin/env php
<?php
/**
 * v 1.3.1
 * checkout --help
 * 
 * installation Windows: 
 *     . necessite le fichier checkout.bat dans le même dossier que checkout.php
 *     . déclarer ce dossier dans le PATH de la machine
 *     . à partir de là on peut utiliser la commande: `checkout ...`
 *     exemple: `checkout spip -bmaster mon_dossier`
 * 
 */

date_default_timezone_set("Europe/Paris");
define("_MAX_LOG_LENGTH",100);

/**
 * Possibilite de definir des mirroirs
 * supporte en git uniquement
 * les mirroirs sont ajoutes en remote, et sont donc recupere par le fetch --all :
 * meme si un des serveur ne reponds pas on peut recuperer la reference que l'on veut checkout sur un autre des serveurs
 */
$git_mirrors = [
	// 'https://www.git_origin.org/' => [ 'https://www.git_mirror1.org/', 'https://www.git_mirror2.org/',... ]
	// 'https://git.spip.net/' => [ 'https://git-mirror.spip.net/' ],
];

list($methode,$source,$dest,$options) = interprete_commande($argv);

if (!$methode and $source) {
	if ($methode = autodetermine_methode($source)) {
		echo "Checkout methode ". strtoupper($methode) . "\n";
	}
}

if ((count($argv)>1 and $argv[1]=="--help") or (!$methode and !$dest)) {
	checkout_help();
	exit;
}

if (getenv('FORCE_RM_AND_CHECKOUT_AGAIN_BAD_DEST')
	or isset($options['forcerm'])) {
	define('_FORCE_RM_AND_CHECKOUT_AGAIN_BAD_DEST', true);
}
else {
	define('_FORCE_RM_AND_CHECKOUT_AGAIN_BAD_DEST', false);
}

if (isset($options['read'])){
	echo read_source($dest,$options), "\n";
}
elseif (isset($options['logupdate'])){
	echo logupdate_source($dest,$options), "\n";
}
else {
	echo run_checkout($methode, $source, $dest, $options);
}

/**
 * Aide
 */
function checkout_help() {

	$command = basename($GLOBALS['argv'][0]);

	echo <<<help
Script de checkout multi-méthodes permettant une syntaxe unifiee git/svn/ftp

$command [methode] [-rRevision] [-bBranche] repoSource dirDest

Exemples :
# Checkout un repo :
  $command svn -r1234 svn://example.org/repo dest
  $command git -re1ad434 git://example.org/repo dest
  $command git -re1ad434 -bmaster git://example.org/repo dest
  $command ftp http://example.org/paquet.zip dest

# Checkout SPIP (core+externals) :
  $command spip
  $command spip -b3.2

# Recuperer la commande correspondant a un repo deja checkout
  $command --read dest

# Voir les logs des mises a jour disponibles pour un repertoire
  $command --logupdate dest

  Si le repo est en git et que le repo est DETACHED,
  indiquer une branche pour se limiter aux mises à jour disponibles sur la branche qui vous interesse :
  $command --logupdate -bmaster dest

help;

}

/**
 * Lancer un checkout
 *
 * @param string $methode
 * @param string $source
 * @param string $dest
 * @param array $options
 * @return string
 */
function run_checkout($methode, $source, $dest, $options) {
	if (!$checkout = get_checkout_function($methode)){
		return "Methode $methode inconnue pour checkout $source vers $dest\n";
	}
	else {
		$res = $checkout($source, $dest, $options);
		if (strncmp($res,'OK ', 3) === 0) {
			return ".";
		}
		else {
			return $res . "\n";
		}
	}
}

/**
 * Recuperer la fonction checkout
 * @param string $methode
 * @return string
 */
function get_checkout_function($methode){
	$checkout = $methode . "_checkout";
	if (function_exists($checkout)){
		return $checkout;
	}
	return "";
}

/**
 * Si aucune methode fournie, mais une source, on essaye de deviner
 * facile si le repo fini en .git ou est une URL en SSH
 * @param string $source
 * @return string
 */
function autodetermine_methode($source) {

	if (substr($source,-4) === '.git') {
		return "git";
	}
	if (strpos($source, "://") === false) {
		$host = explode(":", $source)[0];
		if (strpos($host, '@') !== false) {
			return 'git';
		}
	}
	else {
		$parts = parse_url($source);
		// git:// svn:// ftp:// -> easy
		if (isset($parts['scheme']) and get_checkout_function($parts['scheme'])) {
			return $parts['scheme'];
		}

		// checkout en https?://
		if (isset($parts['host']) and $host = $parts['host']) {
			// on reference quelques serveurs connus en git et en svn
			$known_hosts = [
				'svn' => [
					'zone.spip.org',
					'trac.rezo.net'
				],
				'git' => [
					'github.com',
					'bitbucket.org',
					'gitlab.com'
				],
			];
			// serveur git connu ou commencant par git. comme git.spip.net
			if (in_array($host, $known_hosts['git']) or strpos($host, "git.") === 0) {
				return 'git';
			}
			// serveur git connu ou commencant par svn. comme git.spip.net
			if (in_array($host, $known_hosts['svn'])) {
				return 'svn';
			}
		}
	}

	// si on est pas sur, on ne fait rien
	return '';
}

/**
 * Interpreter la ligne de commande
 * @param array $args
 * @return array
 */
function interprete_commande($args){
	$GLOBALS['script'] = array_shift($args); // inutile : le nom du script
	$methode = "";
	// peut etre pas de methode si on demande un --read dest
	if (!strncmp(reset($args),'-',1)==0
	  and !preg_match(",\W,", reset($args))
	  and get_checkout_function(reset($args))){
		$methode = array_shift($args);
	}

	// peut etre pas de dest si on fait un checkout avec un dest implicite
	$dest = '';
	if (!strncmp(end($args),'-',1)==0 and strpos(end($args),"://") === false and strpos(end($args), '@') === false){
		$dest = array_pop($args);
		$dest = rtrim($dest,'/');
	}

	$source = "";
	// peut etre pas de source si on demande un --read dest
	if (!strncmp(end($args),'-',1)==0){
		$source = array_pop($args);
		$source = rtrim($source,'/');
	}

	$options = array();
	foreach($args as $a){
		if (strncmp($a,'-r',2)==0)
			$options['revision'] = substr($a,2);
		elseif (strncmp($a,'--revision',10)==0)
			$options['revision'] = substr($a,10);
		elseif (strncmp($a,'--forcerm',6)==0)
			$options['forcerm'] = true;
		elseif (strncmp($a,'--read',6)==0)
			$options['read'] = true;
		elseif (strncmp($a,'--logupdate',6)==0)
			$options['logupdate'] = true;
		elseif (strncmp($a,'-b',2)==0)
			$options['branche'] = substr($a,2);
		else {
			if (!isset($options['literal'])) $options['literal'] = array();
			$options['literal'][] = $a;
		}
	}
	if (isset($options['literal']) AND count($options['literal']))
		$options['literal'] = implode(' ',$options['literal']);

	return array($methode,$source,$dest,$options);
}


/**
 * Retrouver la source d'un repertoire deja la
 * @param string $dest
 * @param array $options
 * @return string
 */
function read_source($dest,$options){
	$methodes = array("svn","git","ftp");
	foreach($methodes as $m){
		if (function_exists($f=$m."_read")
	  AND $res = $f($dest,$options))
			return $res;
	}
	return "# source de $dest inconnue";
}

/**
 * Logs des commits plus recents disponibles pour une mise a jour
 * @param string $dest
 * @param array $options
 * @return string
 */
function logupdate_source($dest,$options){
	$methodes = array("svn","git");
	foreach($methodes as $m){
		if (function_exists($f=$m."_read")
	  AND $res = $f($dest,array('format'=>'assoc'))
		AND function_exists($f=$m."_log")){
			$o = [
				"from"=>$res['revision']
			];
			if (isset($options['branche'])) {
				$o['branche'] = $options['branche'];
			}
			$log = $f($dest,$o);
			if (strlen($log))
				return "MAJ dispo pour ". $res['source'] . " :\n$log\n";
			else
				return "";//"# Aucune maj pour " .$res['source'];
		}
	}
	return "";//"# Pas de log disponible pour $dest";
}

/**
 * Message d'erreur si un repertoire existe
 * (ou suppression si variable environnement posee ET chemin safe)
 * @param string $erreur
 * @param string $dir
 * @param bool $delete
 */
function erreur_repertoire_existant($erreur, $dir, $delete = true) {
	echo "\n/!\ " . $erreur;
	if (_FORCE_RM_AND_CHECKOUT_AGAIN_BAD_DEST) {
		$dir = trim($dir);
		if (strpos($dir, "/")!==0
		  and strpos($dir, ".")!==0
		  and strpos($dir, "..")===false) {
			echo "...SUPPRESION $dir";
			if ($delete) {
				exec("rm -fR $dir");
			}
			return;
		}
	}
	echo "\nSupprimez le repertoire $dir ou choisissez une autre destination\n";
	exit(1);
}

/**
 * SPIP
 * C'est une fausse methode raccourci pour checkout SPIP complet
 */

/**
 * @param $source
 * @param $dest
 * @param $options
 */
function spip_checkout($source, $dest, $options) {

	$url_repo_base = "https://git.spip.net/SPIP/";
	if ($source and strpos($source, "git@git.spip.net") !== false) {
		$url_repo_base = "git@git.spip.net:SPIP/";
	}

	if (!$dest) $dest = 'spip';
	$branche = 'master';
	if (isset($options['branche'])) {
		$branche = $options['branche'];
		// Historique avant le 27 09 2020, les branches SPIP étaient 'spip-3.2'
		if (strpos($branche, 'spip-') === 0) {
			$branche = substr($branche, 5);
		}
	}

	$file_externals = '.gitsvnextmodules';
	$file_externals_master = "$dest/$file_externals";
	if (!file_exists($file_externals)) {
		if (!file_exists($file_externals_master)) {
			// on commence par checkout SPIP en master pour recuperer le file externals
			echo run_checkout('git', $url_repo_base . 'spip.git', $dest, ['branche' => 'master']);
			if (file_exists($file_externals_master)) {
				@copy($file_externals_master, $file_externals);
			}
		}
	}

	// on checkout SPIP sur la bonne branche
	echo run_checkout('git', $url_repo_base . 'spip.git', $dest, ['branche' => $branche]);
	if (file_exists($f = $file_externals_master) or file_exists($f = $file_externals)) {
		$externals = parse_ini_file($f, true);
		foreach ($externals as $external) {

			$e_methode = $external['remote'];
			$e_source = $external['url'];
			$e_dest = $dest . "/" . $external['path'];
			// Historique avant le 27 09 2020, les branches SPIP des plugins dist étaient '3.2'
			$e_branche = "spip-" . $branche;

			// remplacer les sources SVN _core_ par le git.spip.net si possible
			if ($e_methode == 'svn') {
				if (strpos($e_source, "svn://zone.spip.org/spip-zone/_core_/plugins/") === 0) {
					$e_source = explode("_core_/plugins/", $e_source);
					$e_source = $url_repo_base . end($e_source) . '.git';
					$e_methode = "git";
				}
				elseif (strpos($e_source, "svn://zone.spip.org/spip-zone/_core_/tags/") === 0) {
					// zone.spip.org/spip-zone/_core_/tags/spip-3.2.7/plugins/aide
					$e_source = explode("_core_/tags/", $e_source);
					$e_source = explode('/', end($e_source));
					$e_branche = array_shift($e_source);
					$e_branche = str_replace('-', '/', $e_branche);
					array_shift($e_source);
					$e_source = $url_repo_base . implode('/', $e_source) . '.git';
					$e_methode = "git";
				}
				elseif (strpos($e_source, "https://github.com/") === 0) {
					if (in_array($branche, ["spip-3.2", "spip-3.1", "spip-3.0"])) {
						continue;
					}
					$e_source = explode("//github.com/", $e_source);
					$e_source = explode("/", end($e_source));
					$user = array_shift($e_source);
					$repo = array_shift($e_source);
					$what = array_shift($e_source);
					switch ($what) {
						case 'branches':
							array_shift($e_source);
							$e_branche = reset($e_source);
							break;
						case 'trunk':
						default:
							$e_branche = 'master';
							break;
					}
					$e_source = "https://github.com/$user/$repo.git";
					// renommage a la volee
					$e_source = str_replace( ['https://github.com/marcimat/bigup'], [ $url_repo_base . 'bigup'], $e_source);
					$e_methode = "git";
				}
			}
			$d = dirname($e_dest);
			if (!is_dir($d)) {
				mkdir($d);
			}
			echo "checkout $e_methode -b{$e_branche} $e_source $e_dest\n";
			echo run_checkout($e_methode , $e_source, $e_dest, ['branche' => $e_branche]);
		}
	}
}



/**
 * SVN
 */


/**
 * Deployer un repo SVN depuis source et revision donnees
 * @param string $source
 * @param string $dest
 * @param array $options
 * @return string
 */
function svn_checkout($source,$dest,$options){
	$user = $pass = '';
	$parts = parse_url($source);
	if (!empty($parts['user']) and !empty($parts['pass'])) {
		$user = $parts['user'];
		$pass = $parts['pass'];
		$source = str_replace("://$user:$pass@", '://', $source);
	}

	$checkout_needed = false;

	if (is_dir($dest)){
		$infos = svn_read($dest,array('format'=>'assoc'));
		if (!$infos){
			erreur_repertoire_existant("$dest n'est pas au format SVN", $dest, false);
			$checkout_needed = true;
		}
		elseif ($infos['source']!==$source) {
			// gerer le cas particulier ou le repo a mv dans un sous dossier trunk ou branches/.. mais on pointe sur une revision anterieure
			// du coup le svn info renvoi toujours l'ancien dossier :(
			$checkout_needed = true;
			if (strpos($source, $infos['source'])===0) {
				$subfolder = ltrim(substr($source, strlen($infos['source'])), DIRECTORY_SEPARATOR);
				if (strpos($subfolder,'branches/')!== false or $subfolder==='trunk') {
					if (!file_exists($dest . DIRECTORY_SEPARATOR . $subfolder)) {
						if (isset($options['revision']) and $options['revision']==$infos['revision']) {
							$checkout_needed = false;
							$command = "$dest sur $source Revision ".$options['revision']. " (avant passage en $subfolder)";
						}
					}
				}
			}
			if ($checkout_needed) {
				erreur_repertoire_existant("$dest n'est pas sur le bon repository SVN", $dest, false);
			}
		}
		elseif (!isset($options['revision'])
		  OR $options['revision']!=$infos['revision']){
			$command = "svn up ";
			if (isset($options['revision']))
				$command .= "-r".$options['revision']." ";
			if (isset($options['literal']))
				$command .= $options['literal']." ";

			$command .= "$dest";
			echo "\n$command\n";
			passthru($command);
			echo "\n";
		}
		else {
			$command = "$dest deja sur $source Revision ".$options['revision'];
		}
	}
	else {
		$checkout_needed = true;
	}
	clearstatcache();

	if ($checkout_needed){
		$dest_co = $dest;
		while (is_dir($dest_co)) {
			$dest_co .= '_';
		}
		$command = "svn co ";
		if (isset($options['revision']))
			$command .= "-r".$options['revision']." ";
		if (isset($options['literal']))
			$command .= $options['literal']." ";
		if ($user and $pass) {
			$command .= "--username $user --password $pass ";
		}

		$command .= "$source $dest_co";
		echo "\n$command\n";
		passthru($command);
		if ($dest_co !== $dest) {
			$command = "mv $dest {$dest_co}.old && mv $dest_co $dest && rm -fR {$dest_co}.old";
			echo "$command\n";
			passthru($command);
		}
		echo "\n";
	}

	return "OK $command";
}

/**
 * Lire source et revision d'un repertoire SVN
 * et reconstruire la ligne de commande
 * @param $dest
 * @param $options
 * @return string|array
 */
function svn_read($dest,$options){

	if (!is_dir("$dest/.svn"))
		return "";

	// si --read on veut lire ce qui est actuellement deploye
	// et reconstituer la ligne de commande pour le deployer
	exec("svn info $dest",$output);
	$output = implode("\n",$output);

	// URL
	// URL: svn://trac.rezo.net/spip/spip
	if (!preg_match(",^URL[^:\w]*:\s+(.*)$,Uims",$output,$m))
		return "";
	$source = $m[1];

	// Revision
	// Revision: 18763
	if (!preg_match(",^R..?vision[^:\w]*:\s+(\d+)$,Uims",$output,$m))
		return "";

	$revision = $m[1];

	if (isset($options['format'])
	  AND $options['format']=='assoc')
		return array(
			'source' => $source,
			'revision' => $revision,
			'dest' => $dest
		);

	return $GLOBALS['script']." svn -r$revision $source $dest";
}

/**
 * Loger les modif d'une source, optionnellement entre 2 revisions
 * @param string $source
 * @param array $options
 *   from : revision de depart, non inclue
 *   to : revision de fin
 * @return string
 */
function svn_log($source,$options){

	$r = "";
	if (isset($options['from']) OR isset($options['to'])){
		$from = 0;
		$to = "HEAD";
		if (isset($options['from']))
			$from = ($options['from']+1);
		if (isset($options['to']))
			$to = $options['to'];

		$r = " -r$from:$to";
	}

	exec("svn log$r $source",$res);


	$output = "";
	$comm = "";
	foreach ($res as $line){
		if (strncmp($line,"---------------",15)==0
		  OR !trim($line)){

		}
		elseif(preg_match(",^r\d+,i",$line) AND count(explode("|",$line))>3){

			if (strlen($comm)>_MAX_LOG_LENGTH)
				$comm = substr($comm,0,_MAX_LOG_LENGTH)."...";

			$line = explode("|",$line);
			$date = explode("(",$line[2]);
			$date = reset($date);
			$date = strtotime($date);
			$output.=
				$comm
				. "\n"
				. $line[0]
				. "|"
				. $line[1]
				. "| "
				. date('Y-m-d H:i:s',$date)
				. " |";
			$comm = "";
		}
		else {
			$comm .= " $line";
		}
	}
	if (strlen($comm)>_MAX_LOG_LENGTH)
		$comm = substr($comm,0,_MAX_LOG_LENGTH)."...";
	$output .= $comm;

	// reclasser le commit le plus recent en premier, git-style
	$output = explode("\n",$output);
	$output = array_reverse($output);
	$output = implode("\n",$output);

	return trim($output);
}


/**
 * GIT
 */



/**
 * Deployer un repo GIT depuis source et revision donnees
 * @param string $source
 * @param string $dest
 * @param array $options
 * @return string
 */
function git_checkout($source,$dest,$options){

	$checkout_needed = false;

	$curdir = getcwd();
	$branche = (isset($options['branche'])?$options['branche']:'master');
	if (is_dir($dest)){
		$infos = git_read($dest,array('format'=>'assoc'));
		if (!$infos){
			erreur_repertoire_existant("$dest n'est pas au format GIT", $dest, false);
			$checkout_needed = true;
		}
		elseif (strtolower($infos['source']) !== strtolower($source)) {
			erreur_repertoire_existant("$dest n'est pas sur le bon repository GIT", $dest, false);
			$checkout_needed = true;
		}
		elseif (!isset($options['revision']) or !isset($infos['revision'])
		  or git_compare_revisions($options['revision'], $infos['revision']) !== 0){
			git_check_mirrors($dest, $source);
			chdir($dest);
			//$command = "git checkout $branche";
			//passthru($command);
			$command = "git fetch --all";
			passthru($command);

			if (isset($options['revision'])){
				$command = "git checkout --detach ".$options['revision'];
				echo "\n$command\n";
				passthru($command);
				echo "\n";
			}
			else {
				$command = "git pull --rebase";
				if ($infos['modified']) {
					$command = "git stash && $command && git stash pop";
				}
				if (!isset($infos['branche']) or $infos['branche'] !== $branche) {
					$command = "git checkout $branche && $command";
				}
				echo "\n$command\n";
				passthru($command);
				echo "\n";
			}
			chdir($curdir);
		}
		else {
			$command = "$dest deja sur $source Revision ".$options['revision'];
		}
	}
	else {
		$checkout_needed = true;
	}
	clearstatcache();

	if ($checkout_needed){
		$dest_co = $dest;
		while (is_dir($dest_co)) {
			$dest_co .= '_';
		}
		$command = "git clone ";
		$command .= "$source $dest_co";
		echo "\n$command\n";
		passthru($command, $error);
		if (!is_dir($dest_co) or $error) {
			if ($urls_alt = git_get_urls_mirrors($source)) {
				foreach ($urls_alt as $source_alt) {
					$command = "git clone ";
					$command .= "$source_alt $dest_co";
					echo "\n$command\n";
					passthru($command, $error);
					if (is_dir($dest_co) and !$error) {
						break;
					}
				}
				if (is_dir($dest_co)) {
					$command = "git remote rename origin mirror";
					echo "\n$command\n";
					passthru("cd $dest_co && $command");
					$command = "git remote add origin $source";
					echo "\n$command\n";
					passthru("cd $dest_co && $command");
				}
			}
		}
		if (is_dir($dest_co)) {
			git_check_mirrors($dest_co, $source);
			if (isset($options['revision'])){
				chdir($dest_co);
				$command = "git checkout --detach ".$options['revision'];
				echo "$command\n";
				passthru($command);
				chdir($curdir);
			}
			elseif ($branche !== 'master') {
				chdir($dest_co);
				$command = "git checkout $branche";
				echo "$command\n";
				passthru($command);
				chdir($curdir);
			}
			if ($dest_co !== $dest) {
				$command = "mv $dest {$dest_co}.old && mv $dest_co $dest && rm -fR {$dest_co}.old";
				echo "$command\n";
				passthru($command);
			}
		}
		echo "\n";
	}

	return "OK $command";
}

/**
 * @param string $rev1
 * @param string $rev2
 * @return int
 */
function git_compare_revisions($rev1, $rev2) {
	$len = min(strlen($rev1), strlen($rev2));
	$len = max($len, 7);

	return strncmp($rev1, $rev2, $len);
}


/**
 * @param string $dest
 * @param array $options
 * @return string|array
 */
function git_read($dest, $options){
	if (!is_dir("$dest/.git"))
		return "";

	$remotes = git_get_remotes($dest);
	if (!$remotes){
		return "";
	}

	$curdir = getcwd();
	chdir($dest);

	if (isset($remotes['origin'])) {
		$source = $remotes['origin'];
	}
	else {
		$source = reset($remotes);
	}

	$modifed = false;
	$branche = false;
	exec("git status -b -s",$output);
	if (count($output) > 1) {
		$full = implode("|\n", $output);
		if (strpos($full,"|\n M") !== false or strpos($full,"|\nM") !== false) {
			$modifed = true;
		}
	}
	// ## master...origin/master
	$output = reset($output);
	if (strpos($output, '...') !== false) {
		$branche = trim(substr($output,2));
		$branche = explode('...', $branche);
		$branche = reset($branche);
	}

	// qu'on soit sur une branche ou non, on veut la revision courante
	exec("git log -1",$output);
	$hash = explode(" ",reset($output));
	$hash = end($hash);

	chdir($curdir);

	if (isset($options['format'])
	  AND $options['format']=='assoc') {
		$res = array(
					'source' => $source,
					'dest' => $dest,
					'modified' => $modifed,
				);
		if ($branche) {
			$res['branche'] = $branche;
		}
		if ($hash) {
			$res['revision'] = $hash;
		}
		return $res;
	}

	$opt = "";
	if ($hash) {
		$opt .= " -r".substr($hash,0,7);
	}
	if ($branche) {
		$opt .= " -b{$branche}";
	}
	return $GLOBALS['script']." git{$opt} $source $dest ";
}

/**
 * @param $dir_repo
 * @return array
 */
function git_get_remotes($dir_repo){
	// recuperer les remote (fetch) du dossier
	$ouput = [];
	exec("cd $dir_repo && git remote -v", $output);
	$remotes = [];
	foreach ($output as $o){
		if (preg_match(",(\w+://.*|\w+@[\w\.-]+:.*)\s+\(fetch\)$,Uis",$o,$m)){
			$o = preg_replace(",\s+,", " ", $o);
			$o = explode(' ', $o);
			$remote_name = array_shift($o);
			$remote_url = array_shift($o);

			$remotes[$remote_name] = $remote_url;
		}
	}
	return $remotes;
}

function git_get_urls_mirrors($url_source) {
	$url_mirrors = [];
	foreach ($GLOBALS['git_mirrors'] as $url_git => $mirrors) {
		// si on a un mirroir connu pour cette source, on verifie les remotes
		if (strpos($url_source, $url_git) === 0) {
			foreach ($mirrors as $mirror) {
				$url_mirrors[] = $mirror . substr($url_source, strlen($url_git));
			}
		}
	}
	return $url_mirrors;
}

function git_check_mirrors($dir_repo, $url_source) {
	if ($url_mirrors = git_get_urls_mirrors($url_source)) {
		$remotes = git_get_remotes($dir_repo);
		$remote_name = "mirror";
		$remote_cpt = '';
		foreach ($url_mirrors as $url_mirror) {
			if (!in_array($url_mirror, $remotes)) {
				// on ajoute le mirroir en remote
				while(!empty($remotes[$remote_name . $remote_cpt])) {
					$remote_cpt = intval($remote_cpt) + 1;
				}
				$command = "git remote add {$remote_name}{$remote_cpt} $url_mirror";
				echo "\n$command\n";
				passthru("cd $dir_repo && $command");
			}
		}
	}
}


/**
 * Loger les modif d'une source, optionnellement entre 2 revisions
 * @param string $dest
 * @param array $options
 *   from : revision de depart
 *   to : revision de fin
 * @return string
 */
function git_log($dest,$options){
	if (!is_dir("$dest/.git"))
		return "";

	$curdir = getcwd();
	chdir($dest);

	$r = "";
	if (isset($options['from']) OR isset($options['to'])){
		$from = "";
		$to = "";
		if (isset($options['from'])){
			$from = $options['from'];
			$output = array();
			exec("git log -1 -c $from --pretty=tformat:'%ct'",$output);
			$t = intval(reset($output));
			if ($t) {
				//echo date('Y-m-d H:i:s',$t)."\n";
				$from="--since=$t $from";
			}
		}
		if (isset($options['to']))
			$to = $options['to'];

		$r = " $from..$to";
	}

	//exec("git log$r --graph --pretty=tformat:'%Cred%h%Creset -%C(yellow)%d%Creset %s %Cgreen(%an %cr)%Creset' --abbrev-commit --date=relative master",$output);
	$output = array();
	exec("git fetch --all 2>&1",$output);
	$output = array();
	$branche = "--all";
	if (isset($options['branche'])) {
		$branche = $options['branche'];
		if (strpos($branche, 'origin/') !== 0) {
			$branche = 'origin/' . $branche;
		}
	}
	exec("git log$r --pretty=tformat:'%h | %an | %ae | %ct | %d %s ' $branche",$output);
	foreach($output as $k=>$line){
		$line = explode("|",ltrim($line,"*"));
		$revision = trim(array_shift($line));
		$comitter_name = trim(array_shift($line));
		$comitter_email = trim(array_shift($line));
		$comitter = ($comitter_email ? $comitter_email : $comitter_name);
		$date = date('Y-m-d H:i:s',trim(array_shift($line)));
		$comm = trim(implode("|",$line));
		if (strlen($comm)>_MAX_LOG_LENGTH)
			$comm = substr($comm,0,_MAX_LOG_LENGTH)."...";
		$output[$k] = "$revision | $comitter | $date | $comm";
	}
	$output = implode("\n",$output);


	chdir($curdir);

	return trim($output);
}

/**
 * FTP
 */

/**
 * Pas de notion de revision en FTP, donc c'est l'url qui fait foi
 * si on a la bonne URL source, on ne met pas a jour
 * et on met a jour quand l'url change
 * @param string $source
 * @param string $dest
 * @param array $options
 * @return string
 */
function ftp_checkout($source,$dest,$options){
	$checkout_needed = false;

	if (is_dir($dest)){
		$infos = ftp_read($dest,array('format'=>'assoc'));
		if (!$infos){
			erreur_repertoire_existant("$dest n'est pas un download FTP", $dest, false);
			$checkout_needed = true;
			clearstatcache();
		}
		elseif ($infos['source']!==$source) {
			erreur_repertoire_existant("n'est pas un download de $source", $dest, false);
			$checkout_needed = true;
			clearstatcache();
		}
	}
	else {
		$checkout_needed = true;
	}
	clearstatcache();


	if ($checkout_needed){
		$dest_co = $dest;
		while (is_dir($dest_co)) {
			$dest_co .= '_';
		}
		$dirdl = dirname($dest_co)."/";

		passthru("mkdir -p $dirdl");
		$d = $dirdl . md5(basename($dest_co)).".tmp";

		// recuperer le fichier
		$command = "curl --silent -L \"$source\" > $d";
		echo "\n$command\n";
		passthru($command);
		echo "\n";

		if (!file_exists($d) OR !filesize($d)){
			// essayer wget si curl foire
			$command = "wget $source -O $d";
			echo "$command\n";
			passthru($command);
			echo "\n";
		}
		if (!file_exists($d)){
			return "Echec $command";
		}

		$md5 = md5_file($d);

		if (!isset($options['format'])) $options['format']='zip';
		switch($options['format']){
			case 'zip':
			default:
				$tempdir = "{$d}d";
				$command = "unzip -o $d -d $tempdir";
				echo "\n$command\n";
				passthru($command);
				echo "\n";
				$deplace = $tempdir;
				$sous = glob("$deplace/"."*");
				if (count($sous)==1 and $sd = reset($sous) and is_dir($sd)) {
					$deplace = $sd;
				}
				$command = "mv $deplace $dest_co";
				echo "\n$command\n";
				passthru($command);
				if ($dest_co !== $dest) {
					$command = "mv $dest {$dest_co}.old && mv $dest_co $dest && rm -fR {$dest_co}.old";
					echo "$command\n";
					passthru($command);
				}
				echo "\n";
				if (is_dir($tempdir)) {
					passthru("rmdir $tempdir");
				}
				break;
		}
		passthru("rm $d");
	}

	if (is_dir($dest) and isset($md5))
		file_put_contents("$dest/.ftpsource","$source\n$md5");

	$command = ftp_read($dest,array());
	return "OK $command";
}

/**
 * @param string $dest
 * @param array $options
 * @return string|array
 */
function ftp_read($dest, $options){

	if (!file_exists($f="$dest/.ftpsource"))
		return "";

	$source = file_get_contents($f);
	$source = explode("\n",$source);

	$md5 = end($source);
	$source = reset($source);

	if (isset($options['format'])
	  AND $options['format']=='assoc')
		return array(
			'source' => $source,
			'revision' => $md5,
			'dest' => $dest
		);

	return $GLOBALS['script']." ftp $source $dest";
}
