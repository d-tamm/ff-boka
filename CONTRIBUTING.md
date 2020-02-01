# Bidra till resursbokningssystemet

## Jag vill inte läsa mycket, jag har bara en fråga
Om du bara har en fråga, skicka inte in en buggrapport. Du kan istället ställa den på våra följande kanaler:

* Vi finns på Slack, och om du har en friluftsframjandet.se-adress kan du själv 
[ansluta dig här](https://join.slack.com/t/ff-boka/signup).
* Eller skicka ett mejl till daniel.tamm(at)friluftsframjandet.se.

## Hur kan jag bidra?

### Rapportera buggar
När du hittar fel i systemet är det av stor hjälp för utvecklarna att få en bra buggrapport. Här finns lite guidning.

* Innan du skickar in din buggrapport, gör en sökning på befintliga rapporterade buggar.
Kanske någon annan redan har rapporterat samma bugg?

* Ta kontakt med oss på Slack (se ovan) och diskutera ditt ärende med oss. Detta är jätteviktigt för
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
  installera en IDE, t.ex. Eclipse
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

### Kommentarer allmänt
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
* Det är OK att använda förkortade php-taggar (`<?= kod ?>`)

### Javascript

### HTML
* Skriv tags med små bokstäver (`<p>`, `<html>`).
* Vid långa block, kommentera gärna den avslutande taggen (`</div><!-- /main -->`)

### CSS
* Klassnamn skrivs alltid med bara små bokstäver och bindestreck (`div-med-svart-bakgrund`)

### SQL, databas

## Installera lokalt
ff-boka baseras på en så kallad LAMP stack (Linux Apache MariaDB PHP). För att installera systemet, följ stegen nedan.
Det går nog också att installera en WAMP stack (dvs på Windows), men det har vi inte testat.
Vissa av åtgärderna nedan behöver utföras som root eller med `sudo`.
* Installera [git](https://readwrite.com/2013/09/30/understanding-github-a-journey-for-beginners-part-1/) 
och [composer](https://getcomposer.org).
* Installera en LAMP stack (Apache, MariaDB, PHP). Kolla i dokumentationen för ditt system för detaljer.
* Installera phpMyAdmin eller annat databas-hanteringsverktyg.
* Skapa en databas-användare `ff-boka` och databasen `ff-boka` och ge användaren full behörighet för databasen.
* Om du vill installera systemet i din document root:
  * Öppna en terminal och gå till mappen som ligger över document root (ofta `/var/www`)
  * Klona det här förrådet med `git clone https://github.com/d-tamm/ff-boka.git`.
    Det ska skapa en mapp `ff-boka`.
  * Ta bort den gamla mappen `html` om den finns. OBS, det raderar allt innehåll i den!
  * Byt mappnamnet på den nya mappen från `ff-boka` till `html` (`mv ff-boka html`).
  * Byt till mappen med `cd html`
* Om du istället vill installera systemet i en undermapp:
  * Öppna en terminal och gå till document root (ofta `/var/www/html`)
  * Klona det här förrådet med `git clone https://github.com/d-tamm/ff-boka.git`.
    Det ska skapa en mapp `ff-boka`.
  * Byt till mappen med `cd ff-boka`.
* Kör `composer install` för att installera några beroenden (dependencies).
* Installera följande i undermappen inc:
  * JQueryUI (ladda ner från deras hemsida, packa upp arkivet och flytta mappen så att
    t.ex. jquery-ui.css ligger på inc/jquery-ui-1.12.1/jquery-ui.css)
  * FontAwesome: ladda ner från deras hemsida (host yourself), packa upp, skapa en mapp
    `fontawesome` i mappen `vendor`, och flytta dit mapparna `css` och `webfonts` från arkivet.
* Säkerställ att webbservern har läsrättigheter på alla mappar, och att du har skrivrättigheter.
* Kopiera filen `inc/config.sample.php` till `inc/config.php` och se över innehållet. För att få kopplingen
  till Friluftsfrämjandets API (för inloggningen), fråga på Slack. Vi vill inte lägga ut detaljerna här.
* Med webbläsaren, gå till din installations startsida, t.ex. http://localhost, för att installera
  databasen. Du behöver ladda om sidan några gånger tills allt är klart.

# Engagera dig
All hjälp är välkommen! Vi behöver folk som ger inspel till önskad funktion, programmering, layout, tester...
Börja med att ta kontakt med oss: Vi finns på Slack, och om du har en friluftsframjandet.se-adress kan du själv [ansluta dig här](https://join.slack.com/t/ff-boka/signup). Eller skicka ett mejl till daniel.tamm(at)friluftsframjandet.se.
