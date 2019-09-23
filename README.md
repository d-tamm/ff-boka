# Bokningssystem för utrustning
för Friluftsfrämjandets lokalavdelningar

## Bakgrund

Många lokalavdelningar inom FF äger friluftsutrustning (såsom kanoter, tält mm) och vill att den ska komma till användning, vilket underlättas genom någon sorts bokningshantering. Ofta används boka.se eller liknande plattformar för att hantera bokningarna. Vi på LA Mölndal upplever dessa system som trubbiga och oflexibla. Om man t.ex. har ett antal kajaker och vill att dessa ska kunna bokas på ett flexibelt sätt på boka.se, så måste man skapa separata kalendrar för varje kajak, och varje kajak måste bokas separat. Läggs alla kajaker i samma kalender kan det bara finnas en bokning åt gången, även om någon bara behöver några få kajaker och de andra egentligen är lediga.

Några LA (t.ex. Ljungkile) har tidigare utvecklat egna bokningssystem som är skräddarsydda för deras verksamhet, och som inte heller upplevs som särskilt lättanvända.

Det finns även en del befintliga system som har utvecklats inom delningsekonomin, men inget system är känt som väl passar behoven inom FF (språk, funktionalitet etc).

## Upplägg

Vi vill utveckla ett smidigare bokningssystem, där ett flertal utrustningsartiklar på ett snabbt och enkelt, men samtidigt flexibelt sätt kan bokas. För bäst resultat och bra livslängd ligger koden på Github som underlättar samarbete mellan olika intresserade lokalavdelningar och även möjliggör versionskontroll. Systemet utvecklas som öppen källkod under GPL-licensen och kan därmed komma till nytta även på annat håll.

Systemet programmeras i PHP med MariaDB-databas som backend. På sikt är det tänkt att lägga det färdiga systemet på en underdomän till friluftsframjandet.se, t.ex. boka.friluftsframjandet.se. En grundläggande koppling görs till aktivitetshanterarens API för att kunna använda befintliga inloggningar och slussa användarna till rätt lokalavdelning.

## Tänkt grundfunktionalitet

- Användarkonto: Knyts till aktivitetshanteraren. Autentiseringen sker mot aktivitetshanterarens API. En admin bör kunna lägga till ytterligare lokalavdelningar, så att man vid behov även kan boka utrustning i annan LA.
- Behörigheter: Admin, LA-admin, user
- Artiklar (tält, kanoter, etc) läggs upp av behörig användare i respektive lokalavdelning (”LA-admin”) och knyts till en lokalavdelning. Kan vara lämpligt att ordna artiklarna i kategorier för enklare bokning.
- För att skapa en bokning:
  1. Välj lokalavdelning vid inloggning, och specificera start- och sluttid.
  2. Då får man upp en lista på artiklar som är bokningsbara under den valda tiden (ev uppdelat på kategorier). Med ett klick läggs artiklar in i bokningen.
  3. Bokningen avslutas, och ett mejl med bekräftelselänk skickas till användaren.
  4. Användaren klickar på länken i mejlet för att bekräfta bokningen, vilket säkerställer att kontaktuppgiften (epostadressen) stämmer så att man kan få tag i folk.
- För att underlätta val av lämpligt datum kan det vara bra med en separat vy som visar tillgängligheten av artiklarna i en vald kategori på en tidslinje.
