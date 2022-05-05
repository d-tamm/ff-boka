# Bidra till resursbokningssystemet

## Innehåll
* [Hur kan jag bidra?](#hur-kan-jag-bidra)
  * [Rapportera buggar](#rapportera-buggar)
  * [Föreslå förbättringar](#föreslå-förbättringar)
  * [Ditt första bidrag](#ditt-första-bidrag)
* [Riktlinjer](#riktlinjer)
  * [Commit-meddelanden](#commit-meddelanden)
  * [Kommentarer](#kommentarer)
  * [PHP](#php)
  * [Javascript](#javascript)
  * [HTML](#html)
  * [CSS](#css)
  * [SQL, databas](#sql-databas)
* [Installera lokalt](#installera-lokalt)
* [Engagera dig](#engagera-dig)

## Jag vill inte läsa mycket, jag har bara en fråga
Om du bara har en fråga, skicka inte in en buggrapport. Du kan istället ställa den på våra följande kanaler:

* Vi finns på Teams. Leta efter teamet Resursbokning. Du behöver ha en friluftsframjandet.se-adress som alla ledare kan få.
* Eller skicka ett mejl till daniel.tamm(at)friluftsframjandet.se.

## Hur kan jag bidra?

### Rapportera buggar
När du hittar fel i systemet är det av stor hjälp för utvecklarna att få en bra buggrapport. Här finns lite guidning.

* Innan du skickar in din buggrapport, gör en sökning på befintliga rapporterade buggar.
Kanske någon annan redan har rapporterat samma bugg?

* Ta kontakt med oss på Teams (se ovan) och diskutera ditt ärende med oss. Detta är viktigt för
  att du ska hamna rätt och ökar chanserna avsevärt att ditt bidrag kommer att implementeras.

* Vi använder [GitHubs Issues](https://github.com/d-tamm/ff-boka/issues) för att hantera buggar.
Gå dit och klicka på New Issue för att skapa en ny buggrapport.

* Du kommer till en mall som ställer en del frågor. Försök att svara på frågorna så bra du kan.
Ju bättre information du ger oss, desto lättare blir det för oss att reproducera felet och åtgärda det.

* Bifoga skärmdumpar om det kan hjälpa förstå vad som händer.

* Du kan skriva din buggrapport på svenska eller engelska.

### Föreslå förbättringar
När systemet beter sig på ett oväntat sätt eller du har förslag på nya funktioner eller andra förbättringar
kan du skicka in det på samma sätt som en buggrapport. Enda skillnaden är att du väljer `Feature request`
istället för `Bug report`. För övrigt följer du samma lista som för att rapportera buggar (se ovan).

### Ditt första bidrag
Vi blir jätteglada om du vill bidra med utvecklingen av systemet! All hjälp kan behövas!
Här kommer några tipps för att komma igång:
* Sätt upp en egen utvecklingsmiljö. Se [Installera lokalt](#installera-lokalt).
* Optionalt, men bra att ha för att underlätta och undvika onödiga fel när du knackar kod:
  installera en IDE, t.ex. Eclipse eller Visual Studio Code.
* Kolla i bugglistan efter buggar med flaggan `Good first issue` som borde kunna lösas med några få raders kod.
* Lär dig grunderna i Git. I princip innebär ett bidrag följande steg:
  * Klona förrådet.
  * Gör en egen gren (branch) för den aktuella ändringen.
  * Knacka kod i den nya grenen.
  * [`Commit` ändringarna](#commit-meddelanden).
  * `Push` för att skicka ändringarna till GitHub.
  * Gör en Pull Request.
  * Seden kommer en av utvecklarna titta på ditt förslag och antingen acceptera det eller föreslå justeringar.

## Riktlinjer
### Commit-meddelanden
* Skriv vad ändringen gör ("Implementerar xyz"), inte vad du har gjort ("Har implementerat xyz").
* Använd inte mer än 72 tecken på första raden.

### Kommentarer
* Använd alltid engelska i alla kommentarer.

### PHP
* Använd 4 mellanslag eller 1 tabb för indragningar.
* Skriv kod som är självförklarande - hellre några fler rader som går att förstå än en kompakt rad som gör allt samtidigt.
* Använd $camelCase för variabler och funktioner.
* Använd även camelCase i arrays och objektegenskaper (`$some['userPassword']`, `$objekt->gulBakgrund`).
* Använd självförklarande namn för variabler och funktioner.
* Försök använda objekt istället för procedurer.
* Skriv in kommentarer i koden om den inte är tillräckligt självförklarande.
* Använd Type hinting i funktionsdeklarationer (dvs skriv int|string|bool... framför parametrarna) om möjligt.
  Det underlättar göra rätt vid anrop av funktionerna, och visas oftast i din IDE.
* Använd alltid `Doc Blocks` inför funktioner för att ge information om ingångsparametrar, returvärden mm.
  Det kan vara till stor hjälp vid kodningen och används av de flesta IDE för att ge dig stöd.
  Kan även användas i klasser inför deklarationen av variabler och konstanter.
* Använd inte `global` om det går att undvika. Koden blir renare och mer återanvändbar
  när du istället använder Dependency Injection, dvs vid anrop av funktioner skicka med
  den information som behövs i form av parametrar.
* Det är OK att använda förkortade php-taggar (`<?= kod ?>`).
* Innehåll från användarna skall alltid gå genom `html_specialchars()` innan visning på skärmen.

### Javascript

### HTML
* Skriv tags med små bokstäver (`<p>`, `<html>`).
* Vid långa block, kommentera gärna den avslutande taggen (`</div><!-- /main -->`)
* Klassnamn och id:n skrivs alltid med bara små bokstäver och bindestreck (`<div class='svart-bakgrund'>`)

### CSS
* Klassnamn och id:n skrivs alltid med bara små bokstäver och bindestreck (`div.svart-bakgrund`)
* Undvik användning av `!important`. Använd om möjligt högre specificitet.

### SQL, databas
* Tabellnamn skrivs med understreck (`cat_admins`).
* Fältnamn skrivs med camelCase (`adminId`).
* Primärindex bör användas och namnges `xxxId`, inte bara `ID`.
* Använd beroenden med cascading mellan tabeller så att vi slipper hålla koll på databas-integriteten manuellt.

## Installera i Docker
* Installera [git](https://readwrite.com/2013/09/30/understanding-github-a-journey-for-beginners-part-1/) 
och [composer](https://getcomposer.org).
* Öppna en terminal och skapa en mapp till projektet, t.ex. `mkdir ~/boka && cd ~/boka`. Detta blir DocumentRoot.
* Klona det här förrådet med `git clone https://github.com/d-tamm/ff-boka.git .`.
* Kör `composer install` för att installera några beroenden.
* Webbservern behöver kunna skapa undermappar i DocumentRoot, t.ex. genom att ändra gruppen (`chgrp -R www-data .` eller liknande) och ge gruppen skrivrättigheter (`chmod -R g+rw .`).
* Kopiera filen `inc/config.sample.php` till `inc/config.php` och se över innehållet. Som dbhost, använd "mariadb". För att få kopplingen till Friluftsfrämjandets API (för inloggningen) att fungera, fråga på Teams. Vi vill inte lägga ut detaljerna här.
* Installera docker och docker-compose, och starta docker som tjänst.
* Kopiera filen `docker/.env.sample` till `docker/.env` och skriv in samma lösenord till databasen som i `config.php`. OBS, eventuellt behöver du maskera tecken då lösenordet i `.env` tolkas av ett shell.
* Starta containrarna: `docker-compose up -d --build`
* Med webbläsaren, gå till http://localhost. Om allt fungerar möts du av dialogen som installerar databasen. Du behöver ladda om sidan några gånger tills allt är klart.

## Installera lokalt
ff-boka baseras på en så kallad LAMP stack (Linux Apache MariaDB PHP). För att installera systemet, följ stegen nedan.
Det går nog också att installera en WAMP stack (dvs på Windows), men det har vi inte testat.
Vissa av åtgärderna nedan behöver utföras som root eller med `sudo`.
* Installera [git](https://readwrite.com/2013/09/30/understanding-github-a-journey-for-beginners-part-1/) 
och [composer](https://getcomposer.org).
* Installera en LAMP stack (Apache, MariaDB, PHP). Kolla i dokumentationen för ditt system för detaljer.
* Installera phpMyAdmin eller annat databas-hanteringsverktyg.
* Skapa en databas-användare `ff-boka` och databasen `ff-boka` och ge användaren full behörighet för databasen.
* Öppna en terminal och gå till DocumentRoot (ofta `/var/www/html`).
* Om du vill installera systemet i DocumentRoot:
  * Se till att DocumentRoot är tom.
  * Klona det här förrådet med `git clone https://github.com/d-tamm/ff-boka.git .`.
* Om du istället vill installera systemet i en undermapp:
  * Klona det här förrådet med `git clone https://github.com/d-tamm/ff-boka.git xxx` (där xxx är namnet på undermappen).
  * Byt till undermappen `cd xxx`.
* Kör `composer install` för att installera några beroenden (dependencies).
* Installera följande i undermappen vendor:
  * JQueryUI (ladda ner från deras hemsida, packa upp arkivet och flytta mappen så att
    t.ex. jquery-ui.css ligger på vendor/jquery-ui-1.12.1/jquery-ui.css)
  * FontAwesome: ladda ner från deras hemsida (host yourself), packa upp, skapa en mapp
    `fontawesome` i mappen `vendor`, och flytta dit mapparna `css` och `webfonts` från arkivet.
* Säkerställ att webbservern har läsrättigheter på alla mappar, och att du har skrivrättigheter.
  Webbservern behöver även rättigheter att skapa undermappar.
* Kopiera filen `inc/config.sample.php` till `inc/config.php` och se över innehållet. För att få kopplingen
  till Friluftsfrämjandets API (för inloggningen), fråga på Slack. Vi vill inte lägga ut detaljerna här.
* Med webbläsaren, gå till din installations startsida, t.ex. http://localhost, för att installera
  databasen. Du behöver ladda om sidan några gånger tills allt är klart.
* Du behöver också ställa in t.ex. cron för att regelbundet anropa skriptet cron.php. Annars skickas inte
  några mejl ut från systemet. Använd till exempel cron med följande rad: `*/10 * * * * wget -O http://localhost/cron.php`
  för att anropa skriptet var 10:e minut.

# Engagera dig
All hjälp är välkommen! Vi behöver folk som ger inspel till önskad funktion, programmering, layout, tester...
Börja med att ta kontakt med oss: Vi finns på Teams (Resursbokning, se ovan). Eller skicka ett mejl till daniel.tamm(at)friluftsframjandet.se.
