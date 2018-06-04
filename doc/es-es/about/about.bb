[h3]¿Qué es $Projectname?[/h3]
$Projectname es un conjunto [b]gratuito y de código abierto[/b] de aplicaciones y servicios web que se ejecutan en un tipo especial de servidor web, llamado "hub", que puede conectarse a otros hubs en una red descentralizada que nos gusta llamar "la red", proporcionando sofisticados servicios de comunicaciones, identidad y control de acceso que funcionan juntos a la perfección a través de dominios y sitios web independientes. Permite a cualquiera publicar, pública o[b]privadamente[/b], contenidos a través de "canales", que son las identidades fundamentales, criptográficamente seguras, que proporcionan autenticación independientemente de los hubs que los alojan. Esta revolucionaria liberación de la identidad en línea a partir de servidores y dominios individuales se denomina "identidad nómada" y está impulsada por el protocolo Zot, un nuevo marco para el control de acceso descentralizado con permisos bien definidos y extensibles.

[h3] De acuerdo... pero, entonces, ¿qué es $Projectname?[/h3]
Desde la perspectiva práctica de los miembros del hub que utilizan el software, $Projectname ofrece una variedad de aplicaciones y servicios web familiares e integrados, incluyendo: 
[ul]
[li]hilos de discusión de redes sociales[/li]
[li]almacenamiento de archivos de nube[/li]
[li]Calendario y contactos (con soporte CalDAV y CardDAV)[/li]
[li]alojamiento de páginas web con un sistema de gestión de contenidos[/li]
[li]wiki[/li]
[li]y más...[/li][/ul]
Aunque todas estas aplicaciones y servicios se pueden encontrar en otros paquetes de software, sólo $Projectname le permite establecer permisos para grupos e individuos que pueden no tener cuentas en tu hub. En las aplicaciones web típicas, si desea compartir cosas en privado en Internet, las personas con las que comparte deben tener cuentas en el servidor que aloja sus datos; de lo contrario, no hay una forma sólida para que su servidor[i]autentifique[/i] a los visitantes del sitio para saber si les concede acceso. $Projectname resuelve este problema con un sistema avanzado de[i]autenticación remota[/i] que valida la identidad de los visitantes empleando técnicas que incluyen criptografía de clave pública.
 
 
[h3]El software[/h3]

$Projectname es, básicamente, una aplicación de servidor web relativamente estándar escrita principalmente en PHP/MySQL [url=https://github.com/redmatrix/hubzilla/blob/master/install/INSTALL.txt][/url], que requiere poco más que un servidor web, una base de datos compatible con MySQL y el lenguaje de scripting PHP. Está diseñado para ser fácilmente instalable por aquellos con habilidades básicas de administración de sitios web en plataformas típicas de alojamiento compartido con una amplia gama de hardware informático. También se puede extender fácilmente a través de plugins y temas y otras herramientas de terceros. 

[h3]Glosario[/h3]

[dl terms="b"]
Una instancia de este software ejecutándose en un servidor web estándar

[grid] Red global de hubs que intercambian información entre sí utilizando el protocolo Zot.

La identidad fundamental en la cuadrícula. Un canal puede representar a una persona, un blog o un foro, por nombrar algunos. Los canales pueden hacer conexiones con otros canales para compartir información con permisos muy detallados.

Los canales pueden tener clones asociados con cuentas separadas y otras cuentas no relacionadas en hubs independientes. Las comunicaciones compartidas con un canal se sincronizan entre los clones del canal, permitiendo que un canal envíe y reciba mensajes y acceda a contenido compartido desde múltiples hubs. Esto proporciona resistencia contra fallas en la red y en el hardware, lo que puede ser un problema significativo para los servidores web autohospedados o de recursos limitados. La clonación le permite mover completamente un canal de un hub a otro, llevando sus datos y conexiones con usted. Ver identidad nómada.

Identidad nómada] La capacidad de autenticar y migrar fácilmente una identidad a través de concentradores y dominios web independientes. La identidad nómada proporciona una verdadera propiedad de una identidad en línea, porque las identidades de los canales controlados por una cuenta en un hub no están vinculadas al propio hub. Un hub es más como un "host" para canales. Con Hubzilla, no tienes una "cuenta" en un servidor como lo haces en sitios web típicos; tienes una identidad que puedes llevarte a través de la rejilla usando clones.

Traducción y sincronización: El novedoso protocolo basado en JSON para la implementación de comunicaciones y servicios descentralizados seguros. Se diferencia de muchos otros protocolos de comunicación en que construye las comunicaciones sobre un marco de identidad y autenticación descentralizado. El componente de autenticación es similar a OpenID conceptualmente pero está aislado de las identidades basadas en DNS. Cuando es posible, la autenticación remota es silenciosa e invisible. Esto proporciona un mecanismo para el control de acceso distribuido a escala de Internet que es discreto.
[/dl]

[h3]Características[/h3]

Esta página enumera algunas de las características principales de $Projectname que se incluyen en la versión oficial. $Projectname es una plataforma altamente extensible, por lo que se pueden añadir más características y capacidades a través de temas y plugins adicionales.

[h4]Control deslizante de afinidad[/h4]

Cuando se añaden conexiones en $Projectname, los miembros tienen la opción de asignar niveles de "afinidad" (cuán cerca está su amigo).
Por otro lado, al añadir el canal de un amigo, se puede situar bajo el nivel de afinidad de "Amigos".

En este punto, la herramienta $Projectname [i]Control deslizante de afinidad[/i], que normalmente aparece en la parte superior de la página, ajusta su contenido para incluir aquellos contactos que están dentro del rango de afinidad deseado. Los canales fuera de ese rango no se mostrarán, a menos que ajuste el control deslizante para incluirlos.

El control deslizante de afinidad permite el filtrado instantáneo de grandes cantidades de contenido, agrupado por niveles de cercanía.

[h4]Filtrado de conexiones[/h4]

Usted tiene la capacidad de controlar con precisión lo que aparece en su flujo usando el "Filtro de conexión" opcional. Cuando está habilitado, el Editor de conexión proporciona entradas para seleccionar los criterios que deben coincidir para incluir o excluir un mensaje específico de un canal específico. Una vez que un mensaje ha sido permitido, todos los comentarios a ese mensaje son permitidos sin importar si coinciden o no con los criterios de selección. Puede seleccionar las palabras que, si están presentes, bloquean el mensaje o se aseguran de que esté incluido en su stream. Se pueden utilizar expresiones regulares para un control aún más preciso, así como hashtags o incluso el idioma detectado del mensaje.   

[h4]Listas de Control de Acceso[/h4]

Al compartir contenido, los miembros tienen la opción de restringir quién ve el contenido.  Al hacer clic en el candado debajo del cuadro de compartir, uno puede elegir los destinatarios deseados del mensaje, haciendo clic en sus nombres.

Una vez enviado, el mensaje sólo podrá ser visto por el remitente y los destinatarios seleccionados.  En otras palabras, el mensaje no aparecerá en ningún muro público.

Las listas de control de acceso se pueden aplicar a contenido y mensajes, fotos, eventos, páginas web, salas de chat y archivos. 

[h4]Inicio de sesión único[/h4]

Las listas de control de acceso funcionan para todos los canales de la red gracias a nuestra exclusiva tecnología de inicio de sesión único. La mayoría de los enlaces internos proporcionan un token de identidad que puede ser verificado en otros sitios de $Projectname y utilizado para controlar el acceso a recursos privados. Inicie sesión una vez en el hub de su casa. Después de eso, la autenticación de todos los recursos de $Projectname es "mágica".


[h4]Almacenamiento de Archivos habilitado para WebDAV[/h4]

Los ficheros se pueden cargar en su área de almacenamiento personal utilizando las utilidades de su sistema operativo (arrastrar y soltar en la mayoría de los casos). Usted puede proteger estos archivos con Listas de Control de Acceso a cualquier combinación de miembros de $Projectname (incluyendo algunos miembros de una red de terceros) o hacerlos públicos.

[h4]Álbumes de Fotos[/h4]

Almacenar fotos en álbumes. Todas sus fotos pueden estar protegidas por Listas de Control de Acceso.

[h4]Calendario de eventos[/h4]

Cree y gestione eventos y tareas, que también pueden estar protegidos con Listas de Control de Acceso. Los eventos pueden importarse/exportarse a otro software utilizando el formato vcalendar/iCal estándar de la industria y compartirse en mensajes con otros. Los eventos de cumpleaños de tus amigos se añaden automáticamente  y se convierten a tu zona horaria correcta para que sepas exactamente cuándo ocurre el cumpleaños, sin importar en qué parte del mundo estés en relación con la persona que cumple años. Los eventos normalmente se crean con contadores de asistencia para que sus amigos y conexiones puedan confirmar su asistencia instantáneamente. 

[h4]Salas de chat[/h4]

Puede crear cualquier número de salas de chat personales y permitir el acceso a través de las listas de control de acceso. Éstas son típicamente más seguras que XMPP, IRC, y otros transportes de Mensajería Instantánea, aunque también permitimos el uso de estos otros servicios a través de plugins.       

[h4]Construcción de Páginas Web[/h4]

El nombre del proyecto tiene muchas herramientas de creación de "Gestión de contenidos" para construir páginas web, incluyendo edición de diseño, menús, bloques, widgets y regiones de página/contenido. Todo esto puede ser controladospara que las páginas resultantes sean privadas para la audiencia a la que están destinadas. 

[h4]Aplicaciones[/h4]

Las aplicaciones pueden ser construidas y distribuidas por los miembros. Éstas se diferencian de las aplicaciones tradicionales de "bloqueo de proveedores" porque están completamente controladas por el autor, que puede proporcionar control de acceso en las páginas de aplicaciones de destino y cobrar en consecuencia por este acceso. La mayoría de las aplicaciones en $Projectname son gratuitas y pueden ser creadas fácilmente por aquellos sin conocimientos de programación. 

[h4]Diseño[/h4

El diseño de la página se basa en un lenguaje de descripción llamado comanche. El propio nombre del proyecto está escrito en diseños comanches que se pueden cambiar. Esto permite un nivel de personalización que no se encuentra normalmente en los llamados "entornos multiusuario".

[h4]Marcadores[/h4]

Comparta y guarde/maneje los marcadores de los enlaces proporcionados en las conversaciones.    
 
[h4]Cifrado privado de mensajes y cuestiones de privacidad[/h4]

El correo privado se almacena en un formato "oscuro". Aunque este no es a prueba de balas, por lo general evita que el administrador del sitio o el ISP husmeen ocasionalmente.  

Cada canal $Projectname tiene su propio conjunto único de claves RSA 4096-bit privadas y públicas asociadas, generadas cuando se crean los canales por primera vez. Se utiliza para proteger mensajes privados y mensajes en tránsito.

Además, los mensajes pueden crearse utilizando "encriptación de extremo a extremo" que no puede ser leída por los operadores de nombres de proyecto o ISPs o cualquier persona que no conozca el código de acceso. 

Por lo general, los mensajes públicos no se cifran durante el transporte ni durante el almacenamiento.  

Los mensajes privados pueden ser revocados (no enviados) aunque no hay garantía de que el destinatario no lo haya leído todavía.

Los mensajes se pueden crear con una fecha de caducidad, en la que se borrarán/quitarán en el sitio del destinatario.  


[h4]Federación de Servicios[/h4]

Además de añadir "conectores de publicación cruzada" a una variedad de redes alternativas, hay soporte nativo para la importación de contenido desde RSS/Atom feeds y puede utilizarlo para crear canales especiales. Los plugins también están disponibles para comunicarse con otros usando los protocolos Diáspora, GNU-Social (OStatus) o Mastodon (ActivityPub). Estas redes no soportan la identidad nómada ni el control de acceso entre dominios; sin embargo, las comunicaciones básicas son soportadas desde/hacia la diáspora, Friendica, GNU-Social, Mastodon y otros proveedores que utilizan estos protocolos.   

También existe soporte experimental para la autenticación OpenID que puede utilizarse en las listas de control de acceso. Este es un trabajo en progreso. Su hub $Projectname puede ser utilizado como un proveedor de OpenID para autenticarle en servicios externos que utilizan esta tecnología. 

Los canales pueden tener permisos para convertirse en "canales derivados" cuando dos o más canales existentes se combinan para crear un nuevo canal temático. 

[h4]Grupos de Privacidad[/h4]

Nuestra implementación de grupos de privacidad es similar a la de Google "Círculos" y "Aspectos" de la Diáspora. Esto le permite filtrar su flujo entrante por grupos seleccionados y establecer automáticamente la Lista de control de acceso saliente sólo para aquellos que se encuentren en ese grupo de privacidad cuando publique. Usted puede anular esto en cualquier momento (antes de enviar el correo).  


[h4]Servicios de directorio[/h4]

Proporcionamos un acceso fácil a un directorio de miembros y proporcionamos herramientas descentralizadas capaces de proporcionar "sugerencias" de amigos. Los directorios son sitios normales de $Projectname que han elegido aceptar el rol de servidor de directorio. Esto requiere más recursos que la mayoría de los sitios típicos, por lo que no es un servicio predeterminado. Los directorios están sincronizados y duplicados de forma que todos contengan información actualizada sobre toda la red (sujeta a los retardos normales de propagación).  
 

[h4]TLS/SSL[/h4]

En el caso de los concentradores de nombres de proyecto que utilizan TLS/SSL, las comunicaciones de cliente a servidor se cifran mediante TLS/SSL.  Dadas las recientes revelaciones en los medios de comunicación con respecto a la vigilancia global generalizada y la elusión del cifrado por parte de NSA y GCHQ, es razonable asumir que las comunicaciones protegidas por HTTPS pueden verse comprometidas de varias maneras. Por consiguiente, las comunicaciones privadas se cifran a un nivel superior antes de enviarlas fuera del sitio.

[h4]Ajustes de canal[/h4]

Cuando se crea un canal, se elige una función que aplica una serie de configuraciones de seguridad y privacidad preconfiguradas. Éstos se eligen en función de las mejores prácticas para mantener la privacidad en los niveles solicitados.  

Si elige una función de privacidad "personalizada", cada canal permite establecer permisos precisos para varios aspectos de la comunicación.  Por ejemplo, bajo el encabezado "Ajustes de seguridad y privacidad", cada aspecto del lado izquierdo de la página tiene seis (6) opciones de visualización/acceso posibles, que pueden seleccionarse haciendo clic en el menú desplegable. También hay otras configuraciones de privacidad que puedes editar.  

Las opciones son:

 Nadie excepto usted mismo.
 Sólo aquellos que usted permita específicamente.
 Cualquiera en sus conexiones aprobadas.
 Cualquiera en este sitio web.
 Cualquiera en esta red.
 Cualquiera autentificado.
 Cualquiera en Internet.


[h4]Foros Públicos y Privados[/h4]

Los foros son típicamente canales que pueden estar abiertos a la participación de múltiples autores. Actualmente existen dos mecanismos para enviar mensajes a los foros: 1) mensajes de "muro a muro" y 2) a través de las etiquetas @mención del foro. Los foros pueden ser creados por cualquier persona y utilizados para cualquier propósito. El directorio contiene una opción para buscar foros públicos. Los foros privados sólo pueden ser publicados y, a menudo, sólo pueden ser vistos por los miembros.

[h4]Clonación de cuentas[/h4]

Las cuentas en $Projectname se denominan [i]identidades nómadas[/i], porque la identidad de un miembro no está vinculada al hub donde se creó la identidad originalmente.  Por ejemplo, cuando creas una cuenta de Facebook o Gmail, está vinculada a esos servicios.  No pueden funcionar sin Facebook.com o Gmail.com.  

Por el contrario, digamos que ha creado una identidad $Projectname llamada[b]tina@$Projectnamehub.com[/b].  Puede clonarlo a otro hub $Projectname eligiendo el mismo o un nombre diferente:[b]vivoParasiempre@algún$ProjectnameHub.info[/b]

Ahora ambos canales están sincronizados, lo que significa que todos sus contactos y preferencias se duplicarán en su clon.  No importa si envías un mensaje desde su hub original o desde el nuevo hub.  Los mensajes se reflejarán en ambas cuentas.

Esta es una característica bastante revolucionaria, si consideramos algunos escenarios:

 ¿Qué ocurre si el hub en el que se basa una identidad se desconecta de repente?  Sin clonación, un miembro no podrá comunicarse hasta que el hub vuelva a estar en línea (sin duda muchos de ustedes han visto y maldecido el Twitter "Fail Whale").  Con la clonación, sólo tienesque iniciar sesión en su cuenta clonada y la vida continúa feliz para siempre. 

 El administrador de su hub ya no puede permitirse el lujo de pagar por su hub gratuito y público $Projectname. Anuncia que el centro cerrará en dos semanas.  Esto le da tiempo suficiente para clonar su(s) identidad(es) y preservar las relaciones, amigos y contenido de su $Projectname.

 ¿Qué sucede si su identidad está sujeta a la censura del gobierno?  Es posible que su proveedor de hubs se vea obligado a eliminar su cuenta, junto con las identidades y los datos asociados.  Con la clonación, $Projectname ofrece [b]resistencia a la censura[/b].  Usted puede tener cientos de clones, si lo desea, todos con diferentes nombres, y que existen en muchos hubs diferentes, esparcidos por todo el Internet.  

$Projectname ofrece nuevas e interesantes posibilidades de privacidad. Puede leer más en la página <<Buenas Prácticas de Comunicación Privada>>.

Se aplican algunas advertencias. Para una explicación completa de la clonación de identidad, lea el documento <HOW TO CLONE MY IDENTITY>.

[h4]Perfiles Múltiples[/h4]

Se puede crear cualquier número de perfiles que contengan información diferente y éstos pueden hacerse visibles para algunas de sus conexiones/amigos. Un perfil "predeterminado" puede ser visto por cualquiera y puede contener información limitada, con más información disponible para seleccionar grupos o personas. Esto significa que el perfil (y el contenido del sitio) que ven sus compañeros de trabajo puede ser diferente de lo que ven sus compañeros de trabajo, y también completamente diferente de lo que es visible para el público en general. 

[h4]Copia de seguridad de la cuenta[/h4]

$Projectname ofrece una sencilla copia de seguridad de la cuenta con un solo clic, en la que puede descargar una copia de seguridad completa de su(s) perfil(es). Las copias de seguridad se pueden utilizar para clonar o restaurar un perfil.

[h4]Borrado de cuenta[/h4]

Las cuentas se pueden eliminar inmediatamente haciendo clic en un enlace. Eso es todo.  Todo el contenido asociado se elimina de la rejilla (esto incluye los mensajes y cualquier otro contenido producido por el perfil eliminado). Dependiendo del número de conexiones que tenga, el proceso de eliminación de contenido remoto podría llevar algún tiempo, pero está previsto que ocurra tan rápido como sea posible.

[h4]Supresión de contenido[/h4]
Cualquier contenido creado en $Projectname permanece bajo el control del miembro (o canal) que lo creó originalmente.  En cualquier momento, un miembro puede borrar un mensaje o un rango de mensajes.  El proceso de eliminación garantiza que el contenido se elimine, independientemente de si se publicó en el concentrador principal (doméstico) de un canal o en otro concentrador, donde el canal se autenticó de forma remota a través de Zot (protocolo de autenticación y comunicación de $Projectname).

[h4]Medios[/h4]

Al igual que cualquier otro sistema moderno de blogging, red social, o un servicio de micro-blogging, $Projectname soporta la carga de archivos, incrustación de video y vinculación de páginas web.

[h4]Previsualización/Edición[/h4] 
Los mensajes y comentarios se pueden previsualizar antes de enviarlos y editarlos después de enviarlos.

[h4]Votación/Consenso[/h4]

Los mensajes pueden convertirse en elementos de "consenso" que permiten a los lectores ofrecer retroalimentación, que se recopila en contadores de "acuerdo", "desacuerdo" y "abstención". Esto le permite medir el interés por las ideas y crear encuestas informales. 

[h4]Extendiendo $Nombre del proyecto[/h4]

El nombre del proyecto se puede ampliar de varias maneras, a través de la personalización del sitio, personalización, configuración de opciones, temas y complementos/plugins. 

[h4]API[/h4]


Una API está disponible para su uso por parte de servicios de terceros. Un plugin también proporciona una implementación básica de Twitter (para los que existen cientos de herramientas de terceros). El acceso puede ser proporcionado por login/contraseña o OAuth, y el registro del cliente de las aplicaciones de OAuth es proporcionado.