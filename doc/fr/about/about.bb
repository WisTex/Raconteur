[h3]Qu'est-ce que $Projectname ?[/h3]
Le nom du projet est un ensemble [b]libre et open source[/b] d'applications et de services web fonctionnant sur un type particulier de serveur web, appelé "hub" (nœud), qui peut se connecter à d'autres hub dans un réseau décentralisé que nous aimons appeler "la grille", fournissant des services sophistiqués de communication, d'identité et de contrôle d'accès qui fonctionnent ensemble de manière transparente à travers les domaines et les sites web indépendants. Il permet à n'importe qui de publier publiquement ou de [b]manière privée[/b] du contenu via des "canaux", qui sont les identités fondamentales, cryptographiquement sécurisées qui fournissent l'authentification indépendamment des hubs qui les hébergent. Cette libération révolutionnaire de l'identité en ligne à partir de serveurs et domaines individuels est appelée "identité nomade", et elle est alimentée par le protocole Zot, un nouveau cadre pour le contrôle d'accès décentralisé avec des permissions extensibles et à finement configurables. 

[h3]D'accord... alors qu'est-ce que $Projectname ?[/h3]
Du point de vue pratique des membres du hub qui utilisent le logiciel, $Projectname offre une variété d'applications et de services Web familiers et intégrés, y compris : 
[ul]
[li]fils de discussion sur les réseaux sociaux[/li]
[li]Stockage de fichiers dans le cloud[/li]
[li]calendrier et contacts (avec support CalDAV et CardDAV)[/li]
[li]Hébergement de pages web avec un système de gestion de contenu[/li]
[li]wiki[/li]
[li] et plus....[/li]
[/ul]
Alors que toutes ces applications et services peuvent être trouvés dans d'autres progiciels, seul $Projectname vous permet de définir des permissions pour des groupes et des individus qui n'ont peut-être même pas de comptes sur votre hub ! Dans les applications web typiques, si vous voulez partager des choses en privé sur Internet, les personnes avec qui vous partagez doivent avoir des comptes sur le serveur hébergeant vos données ; autrement, il n'y a pas de moyen robuste pour votre serveur [i]d'authentifier[/i] les visiteurs du site pour savoir s'ils doivent leur accorder l'accès. $Projectname résout ce problème avec un système avancé [i]d'authentification à distance[/i] qui valide l'identité des visiteurs en employant des techniques qui incluent la cryptographie à clé publique.

[h3]Pile logicielle[/h3].
La pile logicielle $Projectname est une application serveur web relativement standard écrite principalement en PHP/MySQL et [url=https://framagit.org/hubzilla/core/blob/master/install/INSTALL.txt] nécessitant un peu plus qu'un serveur web, une base de données compatible MySQL, et le langage de script PHP[/url]. Il est conçu pour être facilement installable par ceux qui ont des compétences de base en administration de sites Web sur des plates-formes d'hébergement mutualisé typiques avec une large gamme de matériel informatique. Il est aussi facilement extensible via des plugins et des thèmes et d'autres outils tiers. 

[h3]Glossaire[/h3].
[dl terms="b"]
[*= Hub] Une instance de ce logiciel s'exécutant sur un serveur web standard.

[*=Grille] Le réseau global de hubs qui échangent des informations entre eux en utilisant le protocole Zot.

[*=Canaux] L'identité fondamentale sur la grille. Un canal peut représenter une personne, un blog ou un forum pour n'en nommer que quelques-uns. Les canaux peuvent établir des connexions avec d'autres canaux pour partager des informations avec des permissions très détaillées.

[*=Clone] Les canaux peuvent avoir des clones associés à des comptes séparés et non liés sur des hubs indépendants. Les communications partagées avec un canal sont synchronisées entre les clones de canal, ce qui permet à un canal d'envoyer et de recevoir des messages et d'accéder au contenu partagé à partir de plusieurs concentrateurs. Cela fournit une résilience contre les pannes de réseau et de matériel, ce qui peut être un problème important pour les serveurs Web hébergés ou à ressources limitées. Le clonage vous permet de déplacer complètement un canal d'un hub à un autre, emportant avec vous vos données et vos connexions. Voir identité nomade.

[*=Identité nomade] La capacité d'authentifier et de migrer facilement une identité à travers des hubs indépendants et des domaines web. L'identité nomade fournit une véritable propriété d'une identité en ligne, car les identités des canaux contrôlés par un compte sur un hub ne sont pas liées au hub lui-même. Un hub est plus comme un "host" pour les chaînes. Avec Hubzilla, vous n'avez pas de "compte" sur un serveur comme vous le faites sur des sites web typiques ; vous possédez une identité que vous pouvez emporter avec vous à travers la grille en utilisant des clones.

[*= [url=[baseurl]/help/developer/zot_protocol]Zot[/url]] Le nouveau protocole basé sur JSON pour la mise en œuvre de communications et de services décentralisés sécurisés. Il diffère de nombreux autres protocoles de communication en construisant les communications sur un cadre décentralisé d'identité et d'authentification. Le composant d'authentification est similaire à OpenID sur le plan conceptuel, mais il est isolé des identités basées sur le DNS. Dans la mesure du possible, l'authentification à distance est silencieuse et invisible. Il s'agit d'un mécanisme discret de contrôle d'accès distribué à l'échelle de l'Internet.
[/dl]

[h3]Caractéristiques[/h3]
Cette page énumère quelques-unes des fonctionnalités de base de $Projectname qui sont regroupées avec la version officielle. Le nom du projet est une plate-forme hautement extensible, ce qui permet d'ajouter plus de fonctionnalités et de fonctionnalités via des thèmes et des plugins supplémentaires.

[h4]Curseur d'affinité[/h4]

Lors de l'ajout de connexions dans $Projectname, les membres ont la possibilité d'assigner des niveaux d'"affinité" (à quel point votre amitié est proche) à la nouvelle connexion.  Par exemple, lorsque vous ajoutez quelqu'un qui se trouve être une personne dont vous suivez le blog, vous pourriez assigner à son canal un niveau d'affinité de &quot;Connaissances&quot;.

D'autre part, lors de l'ajout du canal d'un ami, ils pourraient être placés sous le niveau d'affinité &quot;d'Amis&quot;.

A ce stade, l'outil $Projectname [i]curseur d'affinité[/i], qui apparaît généralement en haut de votre page &quot;Matrix&quot ;, ajuste le contenu de la page pour inclure ceux qui se situent dans la plage d'affinité désirée. Les canaux en dehors de cette plage ne seront pas affichés, à moins que vous n'ajustiez le curseur pour les inclure.

Le curseur d'affinité permet de filtrer instantanément de grandes quantités de contenu, regroupées par niveaux de proximité.

[h4]Filtrage de connexion[/h4]

Vous avez la possibilité de contrôler précisément ce qui apparaît dans votre flux en utilisant le "Filtre de connexion" en option. Lorsqu'il est activé, l'éditeur de connexion fournit des entrées pour sélectionner les critères qui doivent être appariés afin d'inclure ou d'exclure un message spécifique d'un canal spécifique. Une fois qu'un message a été autorisé, tous les commentaires à ce message sont autorisés, qu'ils correspondent ou non aux critères de sélection. Vous pouvez sélectionner des mots qui, s'ils sont présents, bloquent le message ou s'assurent qu'il est inclus dans votre flux. Les expressions régulières peuvent être utilisées pour un contrôle encore plus fin, ainsi que les hashtags ou même la langue détectée du message.  

[h4]Listes de contrôle d'accès[/h4]

Lors du partage de contenu, les membres ont l'option de restreindre la visibilité du contenu.  En cliquant sur le cadenas sous la boîte de partage, on peut choisir les destinataires désirés du message, en sélectionnant leur nom.

Une fois envoyé, le message ne sera visible que par l'expéditeur et les destinataires sélectionnés.  En d'autres termes, le message n'apparaîtra sur aucun mur public.

Les listes de contrôle d'accès peuvent être appliquées au contenu et aux messages, aux photos, aux événements, aux pages Web, aux salons de discussion et aux fichiers. 

[h4]Ouverture de session unique[/h4]

Les listes de contrôle d'accès fonctionnent pour tous les canaux de la grille grâce à notre technologie unique d'ouverture de session unique. La plupart des liens internes fournissent un jeton d'identité qui peut être vérifié sur d'autres sites $Projectname et sert à contrôler l'accès aux ressources privées. Vous vous connectez une seule fois à votre hub d'origine. Après cela, l'authentification à toutes les ressources $Projectname est "magique".

[h4]Stockage de fichiers avec WebDAV[/h4]

Les fichiers peuvent être téléchargés dans votre espace de stockage personnel à l'aide des utilitaires de votre système d'exploitation (glisser-déposer dans la plupart des cas). Vous pouvez protéger ces fichiers avec des listes de contrôle d'accès à n'importe quelle combinaison de membres $Projectname (y compris certains membres tiers du réseau) ou les rendre publics.

[h4]Albums de photos[/h4]

Stockez vos photos dans des albums. Toutes vos photos peuvent être protégées par des listes de contrôle d'accès.

[h4]Calendrier des événements[/h4]

Créez et gérez des événements et tâches, qui peuvent également être protégés par des listes de contrôle d'accès. Les événements peuvent être importés et exportés vers d'autres logiciels en utilisant le format standard de l'industrie vcalendar/iCal pour être partagés avec d'autres. Les anniversaires sont automatiquement ajoutés par vos amis et convertis en votre fuseau horaire correct afin que vous sachiez précisément quand l'anniversaire se produit - peu importe où vous vous trouvez dans le monde par rapport à la personne fêtée. Les événements sont normalement créés avec des compteurs de présence afin que vos amis et vos connexions puissent répondre instantanément.  

[h4]Salons de discussion[/h4]

Vous pouvez créer n'importe quel nombre de salles de chat personnelles et autoriser l'accès via des listes de contrôle d'accès. Ceux-ci sont généralement plus sûrs que XMPP, IRC et autres transports de messagerie instantanée, bien que nous autorisions également l'utilisation de ces autres services via des plugins.       

[h4]Création de pages Web[/h4]

$Projectname dispose de nombreux outils de création de " Gestion de contenu " pour la création de pages Web, y compris l'édition de mise en page, les menus, les blocs, les widgets et les régions de pages et de contenu. Tous ces éléments peuvent être contrôlés de façon à ce que les pages qui en résultent soient privées pour le public auquel elles sont destinées. 

[h4]Les applications[/h4]

Les applications peuvent être construites et distribuées par les membres. Ces applications sont différentes des applications " verrouillées par le fournisseur" traditionnelles parce qu'elles sont entièrement contrôlées par l'auteur - qui peut fournir un contrôle d'accès sur les pages de l'application de destination et facturer en conséquence pour cet accès. La plupart des applications de $Projectname sont gratuites et peuvent être créées facilement par ceux qui n'ont aucune connaissance en programmation. 

[h4]Mise en page[/h4]

La mise en page est basée sur un langage de description appelé Comanche. $Projectname est lui-même rédigé avec des mises en page Comanche que vous pouvez modifier. Cela permet un niveau de personnalisation que vous ne trouverez pas dans d'autres environnements dits "multi-utilisateurs".

[h4]Marque-pages[/h4]

Partagez, sauvegardez et gérez les marque-pages à partir des liens fournis dans les conversations.    
 
[h4]Cryptage des messages privés et protection de la vie privée[/h4].

Le courrier privé est stocké dans un format obscurci. Bien que cela ne soit pas à l'épreuve des balles, cela empêche généralement l'espionnage occasionnel par l'administrateur du site ou le fournisseur d'accès Internet.  

Chaque canal $Projectname possède son propre jeu unique de clés RSA 4096 bits privées et publiques associées, générées lors de la première création des canaux. Ceci est utilisé pour protéger les messages privés et les messages en transit.

De plus, les messages peuvent être créés en utilisant un "chiffrement de bout en bout" qui ne peut pas être lu par les opérateurs $Projectname ou les FAI ou toute personne ne connaissant pas le code d'accès. 

Les messages publics ne sont généralement pas cryptés en transit ou en stockage.  

Les messages privés peuvent être retirés (non expédiés) bien qu'il n'y ait aucune garantie que le destinataire ne l'a pas encore lu.

Posts et messages peuvent être créés avec une date d'expiration, date à laquelle ils seront supprimés/supprimés sur le site du destinataire.  

[h4]Fédération des services[/h4]

En plus d'ajouter des greffons de connexion à une variété de réseaux alternatifs, il existe un support natif pour l'importation de contenu à partir de flux RSS/Atom pour créer des canaux spéciaux. Des plugins sont également disponibles pour communiquer avec d'autres personnes en utilisant les protocoles Diaspora et GNU-Social (OStatus). Ces réseaux ne prennent pas en charge l'identité nomade ou le contrôle d'accès inter-domaines ; cependant, les communications de base sont prises en charge par Diaspora, Friendica, GNU-Social, Mastodon et d'autres fournisseurs qui utilisent ces protocoles.   

Il existe également un support expérimental pour l'authentification OpenID qui peut être utilisé dans les listes de contrôle d'accès. Il s'agit d'un travail en cours. Votre hub $Projectname peut être utilisé en tant que fournisseur OpenID pour vous authentifier auprès des services externes qui utilisent cette technologie. 

Les canaux peuvent avoir la permission de devenir des "canaux dérivés" lorsque deux ou plusieurs canaux existants se combinent pour créer un nouveau canal topique. 

[h4]Groupes de protection de la vie privée[/h4]

Notre mise en œuvre de groupes de protection de la vie privée est similaire à Google "Circles" et Diaspora "Aspects". Cela vous permet de filtrer votre flux entrant par groupes sélectionnés et de définir automatiquement la liste de contrôle d'accès sortant sur ceux de ce groupe de confidentialité lorsque vous postez un message. Vous pouvez l'annuler à tout moment (avant l'envoi du poste).  

[h4]Services d'annuaire[/h4]

Nous fournissons un accès facile à un annuaire des membres et des outils décentralisés capables de fournir des "suggestions" d'amis. Les répertoires sont des sites $Projectname normaux qui ont choisi d'accepter le rôle de serveur d'annuaire. Cela nécessite plus de ressources que la plupart des sites typiques et n'est donc pas la valeur par défaut. Les annuaires sont synchronisés et mis en miroir de sorte qu'ils contiennent tous des informations à jour sur l'ensemble du réseau (sous réserve des délais de propagation normaux).  

[h4]TLS/SSL[/h4]

Pour les hubs $Projectname qui utilisent TLS/SSL, les communications entre le client et le serveur sont cryptées via TLS/SSL.  Étant donné les récentes révélations dans les médias concernant la surveillance mondiale et le contournement du chiffrement par la NSA et le GCHQ, il est raisonnable de supposer que les communications protégées par HTTPS peuvent être compromises de diverses façons. Les communications privées sont donc cryptées à un niveau supérieur avant l'envoi hors site.

[h4]Réglages des canaux[/h4]

Lorsqu'un canal est créé, un rôle est choisi qui applique un certain nombre de paramètres de sécurité et de confidentialité préconfigurés. Celles-ci sont choisies pour les meilleures pratiques afin de maintenir la protection de la vie privée aux niveaux requis.  

Si vous choisissez un rôle de confidentialité "personnalisé", chaque canal permet de définir des permissions finement définies pour divers aspects de la communication.  Par exemple, sous la rubrique " Paramètres de sécurité et de confidentialité ", chaque aspect sur le côté gauche de la page comporte six (6) options de visualisation et d'accès possibles, qui peuvent être sélectionnées en cliquant sur le menu déroulant. Il existe également un certain nombre d'autres paramètres de confidentialité que vous pouvez modifier.  

Les options sont :

 - Personne d'autre que vous-même.
 - Seulement ceux que vous autorisez spécifiquement.
 - N'importe qui dans votre carnet d'adresses.
 - Toute personne sur ce site Web.
 - N'importe qui dans ce réseau.
 - Toute personne authentifiée.
 - N'importe qui sur Internet.

[h4]Forums publics et privés[/h4]

Les forums sont généralement des canaux qui peuvent être ouverts à la participation de plusieurs auteurs. Il existe actuellement deux mécanismes pour poster sur les forums : 1) les messages " mur à mur " et 2) via les balises forum @mention. Les forums peuvent être créés par n'importe qui et utilisés à n'importe quelle fin. Le répertoire contient une option pour rechercher des forums publics. Les forums privés ne peuvent être affichés et souvent vus que par les membres.

[h4]Clonage de compte[/h4].

Les comptes en $Projectname sont désignés sous le nom de [i]identités nomades[/i], car l'identité d'un membre n'est pas liée à celle du hub où l'identité a été créée à l'origine.  Par exemple, lorsque vous créez un compte Facebook ou Gmail, il est lié à ces services.  Ils ne peuvent pas fonctionner sans Facebook.com ou Gmail.com.  

Par contre, disons que vous avez créé une identité $Projectname appelée [b]tina@$Projectnamehub.com[/b].  Vous pouvez le cloner dans un autre hub $Projectname en choisissant le même nom, ou un nom différent : [b]machin@quelquonque$ProjectnameHub.info[/b].

Les deux canaux sont maintenant synchronisés, ce qui signifie que tous vos contacts et préférences seront dupliqués sur votre clone.  Peu importe que vous envoyiez un courrier à partir de votre hub d'origine ou du nouveau hub.  Les messages seront reflétés sur les deux comptes.

C'est une caractéristique plutôt révolutionnaire, si l'on considère certains scénarios :

Que se passe-t-il si le hub où une identité est basée se déconnecte soudainement ?  Sans clonage, un membre ne pourra pas communiquer jusqu'à ce que ce hub revienne en ligne (sans aucun doute, beaucoup d'entre vous ont vu et maudit le Twitter "Fail Whale").  Avec le clonage, vous n'avez qu'à vous connecter à votre compte cloné, et la vie continue de manière joyeuse pour toujours. 

L'administrateur de votre hub n'a plus les moyens de payer son hub gratuit et public $Projectname. Il annonce que le hub sera fermé dans deux semaines.  Cela vous donne amplement le temps de cloner votre (vos) identité(s) et de préserver vos relations $Projectname, vos amis et le contenu.

Que faire si votre identité est soumise à la censure du gouvernement ?  Votre fournisseur de concentrateur peut être contraint de supprimer votre compte, ainsi que les identités et les données associées.  Avec le clonage, $Projectname offre [b]une résistance à la censure[/b].  Vous pouvez avoir des centaines de clones, si vous le souhaitez, tous nommés différemment, et existant sur de nombreux hubs différents, éparpillés sur Internet.  

Le nom du projet offre de nouvelles possibilités intéressantes pour la protection de la vie privée. Vous pouvez en lire plus à la page &lt;Meilleures pratiques en communications privées&gt;.

Certaines mises en garde s'appliquent. Pour une explication complète du clonage d'identité, lisez la page &lt;Comment cloner mon identité&gt;.

[h4]Sauvegarde de compte[/h4]

Hubzilla offre une simple sauvegarde de votre compte en un seul clic, où vous pouvez télécharger une sauvegarde complète de votre (vos) profil(s). Les sauvegardes peuvent ensuite être utilisées pour cloner ou restaurer un profil.

[h4]Suppression de compte[/h4]

Les comptes peuvent être immédiatement supprimés en cliquant sur un lien. Voilà, c'est tout.  Tout le contenu associé est ensuite supprimé de la grille (y compris les messages et tout autre contenu produit par le profil supprimé). Selon le nombre de connexions que vous avez, le processus de suppression du contenu distant peut prendre un certain temps, mais il est programmé pour se produire aussi rapidement que possible.

[h4]Suppression de contenu[/h4]

Tout contenu créé dans Hubzilla reste sous le contrôle du membre (ou du canal) qui l'a créé à l'origine.  A tout moment, un membre peut supprimer un message ou une série de messages.  Le processus de suppression garantit que le contenu est supprimé, qu'il ait été posté sur le hub d'origine d'un canal ou sur un autre hub, où le canal a été authentifié à distance via Zot (protocole de communication et d'authentification Hubzilla).

[h4]Médias[/h4]

Semblable à tout autre système moderne de blogging, réseau social ou service de micro-blogging, Hubzilla prend en charge le téléchargement de fichiers, l'intégration de vidéos, l'intégration de liens vers des pages web.

[h4]Prévisualisation/Édition[/h4]

Les messages et commentaires peuvent être prévisualisés avant l'envoi et édités après l'envoi.

[h4]Vote/consensus[/h4]
Les messages peuvent être transformés en éléments de "consensus", ce qui permet aux lecteurs d'offrir un retour d'information, qui est rassemblé en compteurs "d'accord", "en désaccord" et "abstention". Cela vous permet de mesurer l'intérêt pour les idées et de créer des sondages informels.

[h4]Extension de Hubzilla[/h4]

Hubzilla peut être étendu de plusieurs façons, par la personnalisation du site, la personnalisation personnelle, la définition d'options, de thèmes et dde greffons.

[h4]API[/h4]

Une API est disponible pour l'utilisation par des services tiers. Un plugin fournit également une implémentation de base de l'API Twitter (pour laquelle il existe des centaines d'outils tiers). L'accès peut être fourni par login/mot de passe ou OAuth, et l'enregistrement client des applications OAuth est fourni.
