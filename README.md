# Bokningssystem för utrustning
för Friluftsfrämjandets lokalavdelningar

Ett system som ska göra det enklare att boka utrustningen som lokalavdelningarna har till sina ledare och grupper.

# Installera
* Installera [git](https://readwrite.com/2013/09/30/understanding-github-a-journey-for-beginners-part-1/) och [composer](https://getcomposer.org)
* Installera en LAMP stack (Apache, MariaDB, PHP)
* Installera phpMyAdmin eller annat databas-hanteringsverktyg.
* Skapa en databas-användare och databasen `ff-boka` och importera databasstrukturen (fråga på Slack efter senaste version)
* Öppna en terminal och gå till document root (ofta /var/www/html)
* klona det här förrådet med `git clone https://github.com/d-tamm/ff-boka.git`. Det ska skapa en mapp `ff-boka`. Byt till mappen med `cd ff-boka`.
* Alternativt kan du klona mappen från /var/www och sedan byta mappnamnet från `ff-boka` till `html`. Då hamnar installationen i document root.
* Se över rättigheterna, så att webbservern (och du) kommer åt alla filer.
* Kör sedan `composer install` för att installera alla beroenden (dependencies).
* Installera följande i undermappen inc:
  * JQueryUI (ladda ner från deras hemsida, packa upp arkivet och flytta mappen så att t.ex. jquery-ui.css ligger på inc/jquery-ui-1.12.1/jquery-ui.css)
  * FontAwesome: ladda ner från deras hemsida (host yourself), packa upp, skapa en mapp `fontawesome` i mappen `vendor`, och flytta dit mapparna `css` och `webfonts` från arkivet.
* Säkerställ att webbservern har läsrättigheter på alla mappar.
* Kopiera filen `inc/credentials.sample.php` till `inc/credentials.php` och anpassa innehållet.

# Engagera dig
All hjälp är välkommen! Vi behöver folk som ger inspel till önskad funktion, programmering, layout, tester...
Börja med att ta kontakt med oss: Vi finns på Slack, och om du har en friluftsframjandet.se-adress kan du själv [ansluta dig här](https://join.slack.com/t/ff-boka/signup). Eller skicka ett mejl till daniel.tamm(at)friluftsframjandet.se.

# Demo/testplattform
Under utvecklingsfasen finns en [installation med aktuell kod](https://boka.tamm-tamm.de).

# Licens
Projektet görs som [öppen källkod](LICENSE) för att komma till störst möjliga nytta.
