# Keyshop
„Eneba“ praktikos užduoties demonstracinė žaidimų raktų parduotuvė. Sąsaja anglų kalba, kainos eurais. Šešių žaidimų katalogas ir pardavėjų pasiūlymai yra demonstraciniai. Viršeliai ir pagrindinė „Hades“ iliustracija iš „Steam“ CDN, teisės priklauso žaidimų leidėjams.

## Paleidimas
Šiame „Windows“ kompiuteryje atidarykite „PowerShell“ projekto aplanke ir paleiskite:
~~~powershell
.\start.ps1
~~~
Atverkite http://127.0.0.1:8080. Kitame kompiuteryje reikia PHP 8.2 ar naujesnės versijos su PDO SQLite. Iš projekto aplanko galima tiesiog leisti `php -S localhost:8080 -t public`. Duomenų bazė atsiras aplanke server pirmos API užklausos metu. Sukompiliuota sąsaja jau pridėta. Pakeitus app.jsx paleiskite `npm ci` ir `npm run build`.

Docker alternatyva:
~~~sh
docker build -t keyshop .
docker run --rm -p 8080:8080 -v keyshop-data:/shop/server keyshop
~~~

## Ką išbandyti
1. Į paiešką įrašykite „Hads“. Turėtumėte rasti „Hades“. Su „hol“ rasite „Hollow Knight“.
2. Atverkite Game store, pasirinkite Xbox ir rikiavimą pagal mažiausią kainą. Pirmoji turėtų būti Terraria.
3. Atverkite žaidimą ir palyginkite dviejų pardavėjų pasiūlymus. Pasirinktą pasiūlymą įdėkite į krepšelį.
4. Sukurkite bandomąją paskyrą. Po registracijos grįšite į krepšelį. Patvirtinkite imituojamą mokėjimą ir patikrinkite raktą užsakymų istorijoje.
5. Atsijunkite. Prisijungę prie tos pačios paskyros vėl matysite savo raktą.

## Kaip keliauja duomenys
- **Pagrindinis puslapis.** React siunčia atskiras užklausas reklamjuostei ir žaidimų lentynoms. PHP parenka tekstus ir produktus iš SQLite lentelių. Katalogo iliustracijos susiejamos pagal žaidimo ID.
- **Paieška.** PHP tikrina, ar pavadinime yra įvesto teksto dalis. Jei jos neranda, ilgesniems žodžiams leidžia vieną arba du pakeitimus naudodamas Levenšteino atstumą. Mažą rezultatų sąrašą filtruoja ir rikiuoja naršyklė.
- **Krepšelis.** Kiekviena eilutė nurodo konkretų pardavėjo pasiūlymą. Krepšelis saugomas naršyklėje, todėl prisijungus jo pildyti iš naujo nereikia.
- **Apmokėjimas.** PHP patikrina sesiją, pasiūlymų kainas perskaito iš bazės ir vienoje operacijoje išsaugo užsakymą bei raktus. Jei pasiūlymas dingsta, visa operacija atšaukiama. Užsakymo žymuo leidžia saugiai pakartoti tą pačią užklausą nutrūkus ryšiui. Kainos saugomos centais.
- **Paskyra.** PHP išsaugo slaptažodžio maišą. Užsakymų istoriją atrenka pagal sesijos naudotoją. DEMO raktai netinka žaidimams aktyvinti.

## Diegimas
Dockerfile naudoja prieglobos suteiktą PORT. SQLite duomenims išlaikyti tarp diegimų reikia nuolatinio disko ir DB_PATH, nukreipto į rašomą failą tame diske. Laikinoje failų sistemoje perleidus konteinerį paskyros ir užsakymai gali dingti.

## Vercel ir Turso
1. Sukurkite „Turso Cloud“ libSQL duomenų bazę ir jos skaitymo bei rašymo prieigos raktą.
2. „Vercel“ importuokite „Mantas-eng/eneba-keyshop“. Projekto šaknį palikite numatytąją. Kompiliavimo ir funkcijos nustatymai įrašyti vercel.json.
3. „Environment Variables“ pridėkite TURSO_DATABASE_URL ir TURSO_AUTH_TOKEN. Pasirinkite Production aplinką. Jei bandysite Preview diegimus, jiems naudokite atskirą bandomąją bazę.
4. Paleiskite Deploy arba, jei projektas jau įdiegtas, Redeploy. Pirmoji API užklausa sukuria lenteles ir užpildo katalogą.
5. Atverkite /api.php?r=catalog, tada atlikite bandomąjį pirkimą. Po pakartotinio diegimo prisijungus prie tos pačios paskyros užsakymas turi išlikti.

Slapukas saugo atsitiktinį sesijos raktą. Bazėje laikoma jo SHA-256 maiša, vartotojo ID ir galiojimo pabaiga. Atsijungimas ištrina sesiją.

Parduotuvės adresas: https://eneba-keyshop.vercel.app/.
