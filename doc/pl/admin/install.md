## Instalacja oprogramowania

Bardzo się staraliśmy, aby to oprogramowanie działało na popularnych platformach hostingowych - takich jakie sie używa do hostowania blogów Wordpressa czy witryn opartych na Drupal. $Projectname będzie działać na większości systemów VPS opartych na Linux. Platformy Windows LAMP, takie jak XAMPP i WAMP nie są obecnie oficjalnie obsługiwane - jednak z zadowoleniem przyjmujemy łatki, jeśli uda ci się to uruchomić.

Należy pamiętać, że to oprogramowanie to coś więcej niż prosta aplikacja internetowa. Jest to złożony system komunikacji i zarządzania treścią, który bardziej przypomina serwer poczty internetowej niż serwer WWW. Aby zapewnić niezawodność i wydajność, komunikaty są dostarczane w tle i umieszczane w kolejce do późniejszego dostarczenia, gdy lokacje są niedostępne. Ten rodzaj funkcjonalności wymaga nieco więcej od systemu hosta niż typowy blog. Nie każdy dostawca hostingu PHP/MySQL będzie w stanie spełnić te wymagania. Wielu to zapewnia - ale lepiej jest zapoznać się z wymaganiami i potwierdzić je u dostawcy usług hostingowych jeszcze przed instalacją (a w szczególności przed zawarciem długoterminowej umowy).

Jeśli napotkasz problemy z instalacją, prosimy o informację o tym, za pośrednictwem [systemu śledzenia spraw projektu](https://github.com/isfera/social), skąd pobrałeś oprogramowanie. Podaj jak najwięcej informacji o swoim środowisku operacyjnym i jak najwięcej szczegółów na temat wszelkich komunikatów o błędach, które możesz zobaczyć, abyśmy mogli zapobiec temu w przyszłości. Ze względu na dużą różnorodność działania istniejących systemów i platform PHP, możemy mieć tylko ograniczone możliwości debugowania instalację PHP lub pozyskania brakujących modułów - ale zrobimy to, starając się rozwiązywać ogólne problemy z kodem.

### Zanim zaczniesz 

Wybierz nazwę domeny i ewentualnie poddomeny dla swojego serwera.

Oprogramowanie można zainstalować tylko w katalogu głównym domeny lub poddomeny i nie może ono działać na alternatywnych portach TCP.

Wymagane jest szyfrowanie SSL komunikacji z serwerem WWW a stosowany certyfikat SSL musi buć "prawidłowy dla przeglądarki". Nie można uzywać certyfikatów z podpisem własnym!

Przetestuj swój certyfikat przed instalacją. Narzędzie internetowe do testowania certyfikatu jest dostępne pod adresem "http://www.digicert.com/help/". Odwiedzając witrynę po raz pierwszy, użyj adresu URL SSL („https://”). Pozwoli to uniknąć późniejszych problemów. 

Bezpłatne certyfikaty zgodne z przeglądarkami są dostępne od dostawców, takich jak StartSSL i LetsEncrypt. 

Jeśli stosujesz LetsEncrypt do dostarczania certyfikatów i tworzenia pliku w ramach usługi "well-known" lub "acme-challenge", tak aby LetsEncrypt mógł zweryfikować własność domeny, usuń lub zmień nazwę katalogu `.well-known`, gdy tylko plik
certyfikatu zostanie wygenerowany. Oprogramowanie zapewnia własny program obsługi usługu "well-known", gdy jest instalowany a  istnienie tego katalogu podczas dalszego działania serwera może uniemożliwić poprawne działanie niektórych usług. To nie powinno być problemem w Apache, ale może być problemem z nginx lub innym serwerze WWW.

### Instalacja

#### Wymagania

- Apache z włączoną obsługą mod-rewrite i dyrektywą "AllowOverride All", więc możesz użyć lokalnego pliku .htaccess. Niektórzy z powodzeniem używali nginx i lighttpd. Przykładowe skrypty konfiguracyjne są dostępne dla tych platform w katalogu instalacyjnym. Największe wsparcia ma Apache i Nginx. 

- PHP 7.2 lub wersja późniejsza (z wersją 8.0 włącznie). 

- Dostęp do *wiersza poleceń* PHP z ustawionym na `true` argumentem `register_argc_argv` w pliku php.ini - oraz bez ograniczeń w stosowaniu funkcji exec() i proc_open(), które często nakładają operatorzy hostingu. 

- Rozszerzenia curl, gd (z obsługą co najmniej jpeg i png), mysqli, mbstring, xml, xmlreader (FreeBSD), zip i openssl. Zamiast biblioteki gd, można używać rozszerzenia imagick ale nie jest ono wymagane i MOŻE być również wyłączone za pomocą opcji konfiguracyjnej. 

- Jakaś forma serwera pocztowego lub bramy poczty elektronicznej na której działa funkcja mail() PHP.

- MySQL 5.5.3 lub w wersji nowszej lub serwer MariaDB lub Postgres. Wyszukiwanie bez rozróżniania wielkości liter nie jest obsługiwane w Postgres. Nie jest to szkodliwe, ale węzły z Postgres raczej nie powinny być używane jako serwery katalogów z powodu tego ograniczenia. 
    
- Możliwość planowania zadań przy użyciu crona.

- Wymagana jest instalacja w głównym katalogu domeny lub poddomeny (bez składnika katalog/ścieżka w adresie URL).

### Procedura instalacyjna

**1. Rozpakuj pliki projektu do katalogu głównego obszaru dokumentów serwera WWW.**
    
Jeśli kopiujesz drzewo katalogów na swój serwer WWW, upewnij się, że kopiujesz również `.htaccess` - ponieważ pliki "z kropką" są często ukryte i normalnie nie są kopiowane.

Jeśli możesz to zrobić, zalecamy użycie git do sklonowania repozytorium źródłowego, zamiast używania spakowanego pliku tar lub zip. To znacznie ułatwia aktualizację oprogramowania. Polecenie Linuksa do sklonowania repozytorium do katalogu `mywebsite` jest następujące 

        git clone https://github.com/isfera/social.git mywebsite
        cd mywebsite

a następnie w dowolnym momencie możesz pobrać najnowsze zmiany za pomocą polecenia

        git pull

Utwórz foldery `cache/smarty3` i `store`, jeśli nie istnieją i upewnij się, że są możliwe do zapisu przez serwer internetowy.

		mkdir -p store
       mkdir -p cache/smarty3
       		
       chmod -R 775 store cache

Tu zakładamy, że katalog instalacyjny $Projectname (lub wirtualnego hosta) jest skonfigurowany tak, że prawa własności ma administrator serwera WWW i grupa do której należy zarówno administrator serwera jak i właściciel procesu serwera WWW. Właściciel procesu serwera WWW (np. www-data) nie musi a nawet nie powinien mieć uprawnień zapisu do pozostałej części instalacji (tylko odczyt).
 
**2. Zainstaluj dodatki.**

Następnie należy sklonować repozytorium dodatków (osobno). Dla przykładu nadamy temu repozytorium nazwę `zaddons` (w Twoim przypadku może to być inna nazwa, ale uwzglednij to w poleceniach).

		util/add_addon_repo https://github.com/isfera/social-addons.git zaddons

Aby aktualizować drzewo dodatków trzeba znajdować się w głównym katalogu instalacji $Projectname i wydać polecenie aktualizacji dla tego repozytorium.

       cd mywebsite
       util/update_addon_repo zaddons

**3. Utwórz pustą bazę danych ustaw uprawnienia dostępu.**

Utwórz pustą bazę danych i zanotuj szczegóły dostępu (nazwa hosta, nazwa użytkownika, hasło, nazwa bazy danych). Biblioteki bazy danych PDO powrócą do komunikacji przez gniazdo, jeśli nazwa hosta to "localhost", ale mogą wystąpić z tym problemy. Użyj tego, jeśli Twoje wymagania na to pozwalają. W przeciwnym razie, jeśli baza danych jest udostępniana na serwerze lokalnym, jako nazwę hosta wpisz "127.0.0.1". Korzystając z MySQL lub MariaDB, ustaw kodowanie znaków bazy danych na utf8mb4, aby uniknąć problemów z kodowaniem za pomocą emoji. Wszystkie tabele wewnętrzne są tworzone z kodowaniem utf8mb4_general_ci, więc jeśli ustawisz kodowania na utf8 a nie na utf8mb4, mogą wystąpić problemy.   

Wewnętrznie używamy teraz biblioteki PDO do połączeń z bazami danych. Jeśli napotkasz konfigurację bazy danych, której nie można wyrazić w formularzu konfiguracyjnym (na przykład przy użyciu MySQL z nietypową lokalizacją gniazda), możesz dostarczyć
ciąg połączenia PDO jako nazwę hosta bazy danych. Na przykład:
	
	mysql:unix_socket=/my/special/socket_path

W razie potrzeby nadal należy wypełnić wszystkie inne obowiązujące wartości formularza.  

**4. Utwórz pusty plik konfuguracyjnego .htconfig.php**

Jeśli serwer WWW nie będzie mógł dokonywać zapisów do plików innych niż te podane w pkt. 2 (a nie powinien), trzeba ręcznie utworzyć plik o nazwie .htconfig.php (zlokalizowany w katalogu głównym instalacji) i uczynić go zapisywalnym przez serwer internetowy.

**5. Odwiedź swoją witrynę internetową** 

Odwiedź swoją witrynę za pomocą przeglądarki internetowej i postępuj zgodnie z instrukcjami. Zanotuj wszelkie komunikaty o błędach i popraw je przed kontynuowaniem. Jeśli korzystasz z protokołu SSL z uznanym certyfikatem, użyj schematu https:// w adresie URL swojej witryny internetowej.

**6. Jeżeli automatyczna instalacja nie powiedzie się.**

Jeżeli automatyczna instalacja nie powiedzie się z jakichś powodów, sprawdź co następuje:

- Czy istnieje ".htconfig.php"? If not, edit htconfig.php and change system settings. Rename to .htconfig.php
- Czy wypełniona została baza danych? Jeśli nie, zaimportuj zawartość pliku "install/schema_xxxxx.sql" w phpmyadmin lub w wierszu poleceń mysql (zamieniając 'xxxxx' na właściwy typ bazy danych).

**7. Zarejestruj swoje osobiste konto.**

W tym momencie ponownie odwiedź swoją witrynę i zarejestruj swoje konto osobiste. Wszystkie błędy rejestracji powinny zostać automatycznie naprawione. Jeśli w tym momencie wystąpi jakakolwiek *krytyczna* awaria, to przeważnie oznacza, że baza danych nie została poprawnie zainstalowana. Możesz przenieść lub zmienić nazwę .htconfig.php na inną nazwę i opróżnić bazę danych, dzięki czemu możesz zacząć instalację od nowa.

Twoje konto jeśli ma mieć dostęp administratorski, powinno być pierwszym utworzonym kontem, a adres e-mail podany podczas rejestracji musi być zgodny z adresem "administratora" podanym podczas instalacji. W przeciwnym razie, aby nadać dostęp administratora do jakiegoś konta, trzeba dodać wartość 4096 w polu account_roles w tabeli account bazy danych.

Ze względu na bezpieczeństwo witryny nie ma możliwości zapewnienia dostępu administratora za pomocą formularzy internetowych.

**8. Ustawienie zadań crona**

Skonfiguruj zadanie crona lub zaplanowane zadanie, aby uruchamiać menedżera crona co 10-15 minut do wykonywania w tle zadań przetwarzania i konserwacji. Przykład:

	cd /base/directory; /path/to/php Zotlabs/Daemon/Run.php Cron

Zmień "/base/directory" i "/path/to/php" na swoje rzeczywiste ścieżki.

Jeśli używasz serwera Linux, uruchom "crontab -e" i dodaj linię podobną do pokazanej niżej, zastępując ścieżki i ustawienia swoimi danymi:

	*/10 * * * *	cd /home/myname/mywebsite; /usr/bin/php Zotlabs/Daemon/Run.php Cron > /dev/null 2>&1

Na ogół możesz znaleźć lokalizację PHP, wykonując "which php". Jeśli masz problemy z tą sekcją, skontaktuj się z dostawcą usług hostingowych w celu uzyskania pomocy. Oprogramowanie nie będzie działać poprawnie, jeśli nie możesz wykonać tego kroku.

Trzeba również upewnić się, że ustawiona jest poprawnie opcja `App::$config['system']['php_path']` w pliku .htconfig.php:

	App::$config['system']['php_path'] = '/usr/local/php72/bin/php';

Oczywiście trzeba podać swoją rzeczywistą ścieżkę do katalogu php. 

### Jeżeli rzeczy nie działają tak jak powinny

##### Jeśli wyświetla się komunikat "System is currently unavailable. Please try again later"
	
Sprawdź ustawienia bazy danych. Zwykle oznacza to, że baza danych nie może być otwarte lub dostępne. Jeśli baza danych znajduje się na tym samym komputerze, sprawdź, czy
nazwa serwera bazy danych to "127.0.0.1" lub "localhost".

##### Błąd wewnętrzny 500

Może to być wynikiem braku jednej z wymaganych dyrektyw Apache na Twojej wersji Apache. Sprawdź swoje logi serwera Apache. Sprawdź również swoje uprawnienia do plików. Twoja strona internetowa i wszystkie treści muszą być możliwe do odczytu.

Możliwe, że Twój serwer sieciowy zgłosił źródło problemu w swoich plikach dziennika błędów. Przejrzyj te systemowe dzienniki błędów, aby określić przyczynę problemu. Często będzie to wymagało rozwiązania u dostawcy usług hostingowych lub (w przypadku samodzielnego hostowania) konfiguracji serwera WWW.

##### Błędy 400 i 4xx "File not found"

Najpierw sprawdź swoje uprawnienia do plików. Wszystkie katalogi i pliki portalu i wszystkie treści muszą być możliwe do odczytu przez wszystkich.

Upewnij się, że moduł mod-rewrite jest zainstalowany i działa i uzywany jest plik .htaccess. Aby zweryfikować to drugie, utwórz plik test.out zawierający słowo "test" w górnym katalogu sieciowym, uczyń go czytelnym dla wszystkich i skieruj przeglądarkę na adres

http://yoursitenamehere.com/test.out

Ten plik powinien być blokowany i powinien zostać wyświetlony komunikat o odmowie dostępu.

Jeśli przeglądarka wyświetla strone ze słowem "test", to konfiguracja Apache nie zezwala na użycie pliku .htaccess (w tym pliku znajdują się reguły blokujące dostęp do dowolnego pliku z rozszerzeniem .out na końcu, ponieważ są one zwykle używane do dzienników systemowych) .

Upewnij się, że plik .htaccess istnieje i jest czytelny dla wszystkich, a następnie sprawdź, czy w konfiguracji serwera Apache (witualnego hosta) występuje reguła "AllowOverride None". Należy to zmienić na "AllowOverride All".

Jeśli nie widzisz dokumentu ze słowem "test", Twój plik .htaccess działa, ale prawdopodobnie moduł mod-rewrite nie jest zainstalowany na serwerze internetowym lub nie działa. Aby go włączyć, na większości dystrybucji Linux użyj poleceń:

	% a2enmod rewrite
	% service apache2 restart

Skonsultuj się z dostawcą usług hostingowych, ekspertami od konkretnej dystrybucji systemu Linux lub (jeśli to Windows) dostawcą oprogramowania serwera Apache, jeśli musisz zmienić jedno z tych ustawień i nie możesz dowiedzieć się, jak to zrobić. W sieci jest dużo pomocy. Wygugluj "mod-rewrite" wraz z nazwą dystrybucji systemu operacyjnego lub pakietu Apache.
  
##### Jeśli przy konfiguracji bazy danych pojawi się błąd niepowodzenia wyszukania DNS

Jest to znany problem w niektórych wersjach FreeBSD, ponieważ dns_get_record()
kończy się niepowodzeniem dla niektórych wyszukiwań. Utwórz plik w głównym folderze
serwera WWW o nazwie ".htpreconfig.php" i umieść w nim następującą treść:

<?php
App::$config['system']['do_not_check_dns'] = 1;

Powinno to umożliwić kontynuację instalacji. Po zainstalowaniu bazy danych dodaj
tę samą instrukcję config (ale bez linii '<?php') do pliku .htconfig.php, który
został utworzony podczas instalacji.

##### Jeśli nie można zapisywać do pliku .htconfig.php podczas instalacji z powodu problemów z uprawnieniami

Utwórz pusty plik o tej nazwie i nadaj nu uprawnienie do zapisu przez kogokolwiek.
Dla systemów linuksowych:
	
	% touch .htconfig.php
	% chmod 777 .htconfig.php

Ponów instalację. Jak tylko baza danych zostanie utworzona,

******* to bardzo ważne *********
	
	% chmod 755 .htconfig.php

##### Procesy Apache zwiększają się zużywając coraz więcej zasobów CPU

Wydaje się, że zdarza się to czasami, jeśli używasz mpm_prefork a proces PHP uruchomiony przez Apache nie może uzyskać dostępu do bazy danych.

Rozważ następujące ustawienia:

W /etc/apache2/mods-enabled/mpm_prefork.conf (Debian, ścieżka i nazwa pliku są różne w różnych systemach operacyjnych), ustaw

	GracefulShutdownTimeout 300

Daje to pewność, że działające dzikie procesy Apache nie będą robić tego w nieskończoność, ale zostaną zabite, jeśli nie zatrzymają się pięć minut po
poleceniu zamknięcia, które zostało wysłane do procesu.

Jeśli spodziewasz się dużego obciążenia swojego serwera (np. w przypadku serwera publicznego), również upewnij się, że Apache nie wygeneruje więcej procesów niż MySQL zaakceptuje połączeń.

W pluku /etc/apache2/mods-enabled/mpm_prefork.conf (Debian) ustaw maksymalną liczbę workerów na 150:

	MaxRequestWorkers 150

Jednak gdy w /etc/mysql/my.cnf maksymalna liczba połaczeń jest ustawiona na 100:

	max_connections = 100

to liczba 150 workerów to dużo i prawdopodobnie za dużo dla małych serwerów. Jakkolwiek ustawisz te wartości, upewnij się, że liczba workerów Apache jest mniejsza niż liczba połączeń akceptowanych przez MySQL, pozostawiając miejsce na inne elementy na twoim serwerze, które mogą uzyskać dostęp do MySQL, a także sondę komunikacyjną, która również potrzebuje dostępu do MySQL. Dobrym ustawieniem dla portalu średniej wielkości może być utrzymanie `max_connections` MySQL na 100 i ustawienie `maxRequestWorkers` w mpm_prefork do 70.

Tutaj możesz przeczytać więcej o dostrajaniu wydajności Apache: https://httpd.apache.org/docs/2.4/misc/perf-tuning.html

Istnieje mnóstwo skryptów, które pomogą Ci dostroić instalację Apache. Po prostu wyszukaj za pomocą Google właściwy dla siebie skryptu dostrajający  Apache.
