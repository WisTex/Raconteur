<?php

// Ustaw następujące parametry dla instalacji bazy danych
// Skopiuj lub zmień nazwę tego pliku na .htconfig.php

$db_host = '{{$dbhost}}';
$db_port = '{{$dbport}}';
$db_user = '{{$dbuser}}';
$db_pass = '{{$dbpass}}';
$db_data = '{{$dbdata}}';
$db_type = '{{$dbtype}}'; // an integer. 0 or unset for mysql, 1 for postgres

{{$servertype}}

/*
 * Uwaga: wiele z poniższych ustawień będzie dostępnych w panelu administracyjnym
 * po udanej instalacji witryny. Gdy zostaną ustawione w panelu administracyjnym,
 * są przechowywane w bazie danych - a ustawienie bazy danych zastąpią wszelkie
 * odpowiadające im ustawienie w tym pliku
 *
 * Narzędzie wiersza poleceń util/config może wyszukiwać i ustawiać bezpośrednio
 * elementy bazy danych, jeśli z jakiegoś powodu panel administracyjny nie jest
 * dostępny a ustawienie systemu wymaga modyfikacji.
 *
 */ 


// Wybierz poprawną strefę czasową. Jeśli nie masz pewności, użyj "Europe/Warsaw".
// Można to bedzie później zmienić i dotyczy to tylko sygnatur czasowych aninimowych oglądających

App::$config['system']['timezone'] = '{{$timezone}}';

// Jaki jest URL twojego portalu? NE DODAWAJ KOŃCOWEGO UKOŚNIKA

App::$config['system']['baseurl'] = '{{$siteurl}}';
App::$config['system']['sitename'] = '{{$platform}}';
App::$config['system']['location_hash'] = '{{$site_id}}';

// Te wiersze ustawiają dodatkowe nagłówki bezpieczeństwa, które mają być wysyłane
// ze wszystkimi odpowiedziami. Możesz ustawić transport_security_header na 0,
// jeśli twój serwer już wysyła ten nagłówek. Opcja content_security_policy może
// wymagać wyłączenia, jeśli chcesz uruchomić wtyczkę analityczną piwik lub umieścić
// na stronie inne zasoby poza witryną.

App::$config['system']['transport_security_header'] = 1;
App::$config['system']['content_security_policy'] = 1;
App::$config['system']['ssl_cookie_protection'] = 1;

// Masz do wyboru REGISTER_OPEN, REGISTER_APPROVE lub REGISTER_CLOSED.
// Pamiętaj, aby utworzyć własne konto osobiste przed ustawieniem
// REGISTE_CLOSED. Opcka 'register_text' (jeśli jest ustawionya) spowoduje
// wyświetlanie tego tekstu w widocznym miejscu na stronie rejestracji.
// REGISTER_APPROVE wymaga ustawienia 'admin_email' na adres e-mail już
// zarejestrowanej osoby, która może autoryzować i/lub zatwierdź/odrzuć wniosek.

App::$config['system']['register_policy'] = REGISTER_OPEN;
App::$config['system']['register_text'] = '';
App::$config['system']['admin_email'] = '{{$adminmail}}';

// Zalecamy pozostawienie tego ustawienia na 1. Ustaw na 0, aby umożliwić
// rejestrowanie się bez konieczności potwierdzania rejestracji w wiadomości
// e-mail wysyłanej na podany adres e-mail.

App::$config['system']['verify_email'] = 1;

// Ograniczenia dostępu do portalu. Domyślnie tworzone są  portale prywatne.
// Masz do wyboru ACCESS_PRIVATE, ACCESS_PAID, ACCESS_TIERED i ACCESS_FREE.
// Jeśli pozostawisz ustawienie REGISTER_OPEN powyżej, każdy bedzie się mógł
// zarejestrować na Twoim portalu, jednak portal ten nie będzie nigdzie
// wyświetlany jako witryna z otwartą resjestracją.
// Używamy polityki dostępu do systemu (poniżej) aby określić, czy portal ma być
// umieszczony w katalogu jako portal otwarty, w którym każdy może tworzyć konta.
// Twój inny wybór to: paid, tiered lub free.

App::$config['system']['access_policy'] = ACCESS_PRIVATE;

// Jeśli prowadzisz portal publiczny, możesz zezwolić, aby osoby były kierowane
// do "strony sprzedaży", na której można szczegółowo opisać funkcje, zasady lub
// plany usług. To musi być bezwzględny adres URL zaczynający się od http:// lub
// https: //.

App::$config['system']['sellpage'] = '';

// Maksymalny rozmiar importowanej wiadomości, 0 to brak ograniczeń

App::$config['system']['max_import_size'] = 200000;

// Lokalizacja procesora wiersza poleceń PHP (CLI PHP)

App::$config['system']['php_path'] = '{{$phpath}}';

// Skonfiguruj sposób komunikacji z serwerami katalogowymi.
// DIRECTORY_MODE_NORMAL = klient katalogu, znajdziemy katalog
// DIRECTORY_MODE_SECONDARY = buforowanie katalogu lub kopii lustrzanej
// DIRECTORY_MODE_PRIMARY = główny serwer katalogów - jeden na dziedzinę
// DIRECTORY_MODE_STANDALONE = "poza siecią" lub prywatne usługi katalogowe

App::$config['system']['directory_mode']  = DIRECTORY_MODE_NORMAL;

// domyślny motyw systemowy

App::$config['system']['theme'] = 'redbasic';


// Konfiguracja rejstracji błędów PHP.
// Zanim to zrobisz, upewnij się, że serwer WWW ma uprawnienia
// tworzenie i zapisywanie php.out w katalogu WWW najwyższego poziomu,
// lub zmień nazwę (poniżej) na plik lub ścieżkę, jeśli jest to dozwolone.

ini_set('display_errors', '0');

// Odkomentuj poniższe linie, aby włączyć rejestrację błędów PHP.
//error_reporting(E_ERROR | E_PARSE ); 
//ini_set('error_log','php.out'); 
//ini_set('log_errors','1'); 
