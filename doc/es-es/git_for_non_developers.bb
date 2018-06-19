[b]Git para no desarrolladores[/b]

Así que está manejando una traducción, o está contribuyendo a un tema, y cada vez que hace un pull request tiene que hablar con uno de los desarrolladores antes de que sus cambios puedan ser fusionados?

Lo más probable es que no haya encontrado una forma rápida de explicar cómo mantener las cosas sincronizadas en su lado.  Es realmente muy fácil.

Después de crear un fork del repositorio (sólo tiene que hacer clic en "fork" en github), necesita clonar su propia copia.

Por ejemplo, asumiremos que está trabajando en un tema llamado redexample (que no existe).

[code]git clone https://github.com/username/red.git[/code]

Así que está manejando una traducción, o está contribuyendo a un tema, y cada vez que hace un pull request tiene que hablar con uno de los desarrolladores antes de que sus cambios puedan ser fusionados?

Lo más probable es que no haya encontrado una forma rápida de explicar cómo mantener las cosas sincronizadas en su lado.  Es realmente muy fácil.

Después de crear un fork del repositorio (sólo tiene que hacer clic en "fork" en github), necesita clonar su propia copia.

Por ejemplo, asumiremos que está trabajando en un tema llamado redexample (que no existe).

Una vez que lo haya hecho, cd en el directorio, y añadir un upstream.

[code]
cd red
git remote add upstream https://framagit.org/hubzilla/core/
[/code]

A partir de ahora, puede realizar cambios en el upstream con el comando
[code]git fetch upstream[/code]

Antes de que sus cambios puedan fusionarse automáticamente, a menudo necesitará fusionar los cambios anteriores.

[code]
git merge upstream/master
[/code]

Siempre debe fusionar upstream antes de subir cualquier cambio, y [i]debe[/i] fusionar upstream con cualquier pull request para que se fusionen automáticamente.

El 99% de las veces, todo irá bien.  La única vez que no lo hará es si alguien más ha estado editando los mismos ficheros que usted y, a menudo, sólo si ha estado editando las mismas líneas de los mismos archivos.  Si eso sucede, ese sería un buen momento para solicitar ayuda hasta que se acostumbre a manejar sus propios conflictos de fusión.

Entonces sólo necesitas añadir tus cambios [code]git añadir vista/tema/redexample/[/code]

Esto agregará todos los archivos en la vista/tema/redexample y cualquier subdirectorio.  Si sus archivos particulares se mezclan a través del código, usted debe agregar uno a la vez.  Trata de no hacer git add -a, ya que esto lo agregará todo, incluyendo archivos temporales (mayormente, pero no siempre atrapamos a aquellos con un.gitignore) y cualquier cambio local que tenga, pero que no intente confirmar.

Una vez que haya agregado todos los archivos que ha cambiado, necesita confirmarlos.  [code]git commit[/code]

Esto abrirá un editor donde podrá describir los cambios que ha realizado.  Guarde este archivo y salga del editor.

Finalmente, suba los cambios en su propio git
[code]git push[/code]

Y eso es todo, su repo está al día!

Todo lo que necesita hacer ahora es crear la petición pull.  Hay dos maneras de hacerlo.

La forma más fácil, si está utilizando Github, es simplemente hacer clic en el botón verde en la parte superior de su propia copia del repositorio, introducir una descripción de los cambios, y hacer clic en `crear pull request'. El repositorio principal, los temas y los complementos tienen su rama principal en Github, por lo que este método se puede utilizar la mayor parte del tiempo.

La mayoría de la gente puede parar aquí.

Algunos proyectos en la ecosfera extendida de RedMatrix no tienen presencia en Github, para un pull request, los pasos son un poco diferentes: usted tendrá que crear su pull request manualmente.  Afortunadamente, esto no es
mucho más difícil.

[code]git request-pull -p <start> <url>[/code]

Start es el nombre de un commit por el que empezar.  Esto debe existir en el upstream.  Normalmente, sólo querr'a la rama master.

URL es la URL de[i]su[/i] repo.

También se puede especificar <end>.  Este valor predeterminado es HEAD.

Ejemplo:
[código]
git request-pull master https://ejemplo.com/proyecto
[/código]

Y simplemente envíe la salida al mantenedor del proyecto.

#include doc/macros/main_footer.bb;
