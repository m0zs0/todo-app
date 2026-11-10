# 3. Laravel a backend konténerben

Ebben a fejezetben létrehozzuk a Laravel alkalmazás alapvázát, beállítjuk a MySQL-kapcsolatot, telepítjük az API-támogatást, majd elindítjuk a fejlesztői szervert. A saját Todo-modell és a hozzá tartozó migrációk később következnek.

## 3.1 Előfeltételek

- A Docker Desktop és a 2. fejezetben létrehozott konténerek futnak.
- A `backend` mappa üres, még nem tartalmaz Laravel projektet.
- A backend portleképezése `8001:8000`: a gépünkön a `8001`, a konténeren belül a `8000` portot használjuk.

## 3.2 Terminál nyitása a backend konténerben

1. A VS Code **Containers** nézetében keresd meg a `todo-backend` konténert.
2. Jobb klikk a konténeren, majd **Attach Shell**.
3. A konténer termináljában ellenőrizd az aktuális mappát:

```bash
pwd
```

Az eredmény `/var/www`. Ez a konténeren belüli mappa a gépen az `E:\dev\todo-app\backend` mappának felel meg. A benne létrehozott fájlok a VS Code Explorerben is láthatók.

Az alábbi Laravel- és Composer-parancsokat ebben a **konténerterminálban** kell futtatni. A Git-parancsokat később a gép helyi termináljában adjuk ki.

## 3.3 A Laravel projekt létrehozása

Az üres `/var/www` mappában futtasd:

```bash
composer create-project --prefer-dist laravel/laravel . "^12.0"
```

### A parancs részei

- `composer create-project`: új projektet hoz létre egy Composer-csomag alapján.
- `laravel/laravel`: a Laravel alkalmazás alapváza.
- `.`: a projekt az aktuális mappába kerül.
- `"^12.0"`: a Laravel 12-es főverzióját választjuk, nem automatikusan egy későbbi főverziót.
- `--prefer-dist`: lehetőség szerint archívumként tölti le a csomagokat, majd kicsomagolja őket. Ez a jelenlegi Composer alapértelmezése is; itt az egyértelműség kedvéért adjuk meg.

A letöltést és a kicsomagolást a konténer végzi. Mivel a `backend` mappa csatolva van, a fájlokat a Windowsos mappában is látod. A sok kis fájl létrehozása ezen a csatoláson keresztül időbe telhet.

A telepítő létrehozza többek között az `artisan` fájlt, az `app`, a `routes` és a `vendor` mappát, valamint a helyi `.env` fájlt és az alkalmazás kulcsát. Az alaptelepítés során SQLite-adatbázis is létrejöhet; a továbbiakban azonban MySQL-t használunk.

Ellenőrizd a verziót:

```bash
php artisan --version
```

Az eredménynek Laravel 12-es verziót kell mutatnia. Az **Artisan** a Laravel parancssori eszköze: később ezzel hozunk létre modelleket, migrációkat és kontrollereket.

## 3.4 A MySQL-kapcsolat beállítása

Nyisd meg a `backend/.env` fájlt VS Code-ban, és állítsd be az alábbi értékeket:

```dotenv
APP_URL=http://localhost:8001

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=todo_db
DB_USERNAME=todo
DB_PASSWORD=todo_secret
```

Ha valamelyik szükséges sor előtt `#` van, távolítsd el: az ilyen sor megjegyzés, így a beállítás nem lép érvénybe. Ne szerepeljen ugyanaz a beállítás többször a fájlban.

| Beállítás | Jelentés |
|---|---|
| `APP_URL` | Az alkalmazás helyi címe; a gépen a `8001` portot használjuk. |
| `DB_CONNECTION` | Az adatbázis típusa: MySQL. |
| `DB_HOST` | A MySQL Compose-szolgáltatásának neve: `db`. |
| `DB_PORT` | A MySQL portja a Docker-hálózaton belül: `3306`. |
| `DB_DATABASE` | A fejlesztői adatbázis neve: `todo_db`. |
| `DB_USERNAME`, `DB_PASSWORD` | A Compose-ban megadott helyi fejlesztői felhasználó és jelszó. |

**Miért `db:3306`, ha a Compose-ban `3307:3306` szerepel?** A backend és a MySQL konténer a Docker belső hálózatán kommunikál, ahol a szolgáltatás neve `db`, a portja pedig `3306`. A `3307` a gép felől elérhető külső port.

A `.env` helyi konfiguráció, érzékeny értékeket is tartalmazhat: **ne kerüljön GitHubra**. Az `APP_KEY` értékét se tedd közzé. A `backend/.env.example` megosztható, de csak nem érzékeny példaértékek szerepeljenek benne.

## 3.5 API-támogatás és Sanctum telepítése

A backend konténer termináljában futtasd:

```bash
php artisan install:api
```

A parancs telepíti az API-támogatást és a **Sanctumot**, valamint létrehozza az API-útvonalak és a tokenek tárolásához szükséges fájlokat. A Sanctumot később a bejelentkezésnél és a tokenes hitelesítésnél használjuk.

Ha a parancs megkérdezi, hogy futtassa-e most a migrációkat, választhatod a **No** választ: a következő pontban magunk indítjuk el őket. Ha már lefuttattad, nem probléma: az alábbi `migrate` csak a még nem futtatott migrációkat alkalmazza.

Az API-útvonalakat ezzel listázhatod:

```bash
php artisan route:list --path=api
```

Ez még nem a kész Todo API; a saját végpontokat később készítjük el.

## 3.6 Az alap migrációk futtatása

A **migrációk** az adatbázis szerkezetét írják le PHP-ban. A Laravel ezek alapján hozza létre a táblákat, és nyilvántartja, melyik migrációt futtatta már.

Az alap Laravel-beállítás adatbázisban tárolja a munkameneteket, ezért a kezdőoldalhoz is szükséges lehet a `sessions` tábla. A backend konténerben futtasd:

```bash
php artisan migrate
```

Ez létrehozza a még hiányzó táblákat az aktuálisan beállított `todo_db` adatbázisban, köztük a `sessions` táblát. Ha a Sanctumot már telepítetted, annak tokeneket tároló migrációja is lefut.

Ellenőrzés:

```bash
php artisan migrate:status
```

A lefutott migrációknál `Ran` állapot látható. Az Adminerben is megnyithatod a `todo_db` adatbázist, és megtekintheted a táblákat.

**Ne használd erre a `migrate:fresh` parancsot:** az törli a meglévő táblákat és az adataikat. A később létrehozott migrációkat ismét a `php artisan migrate` paranccsal fogjuk alkalmazni.

## 3.7 A fejlesztői szerver indítása

A backend konténerben:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

- A `--host=0.0.0.0` lehetővé teszi, hogy a szerver a konténer hálózatán is elérhető legyen.
- A `--port=8000` a konténeren belüli port. Ezt a Compose a gép `8001` portjára kapcsolja.

Nyisd meg a géped böngészőjében:

**http://localhost:8001**

Ha minden rendben, megjelenik a Laravel kezdőoldala. A szerver terminálja addig foglalt marad, amíg fut; leállítani **Ctrl+C**-vel tudod. Ha közben más Artisan-parancsot szeretnél használni, nyiss egy másik shellt a `todo-backend` konténerhez.

A konténer indítása önmagában nem indítja el a Laravel szervert: azt külön kell elindítani. Az itt használt szerver fejlesztésre való.

## 3.8 Hibaelhárítás

### Hiányzó `sessions` tábla

Ha a böngészőben ezt látod:

```text
Table 'todo_db.sessions' doesn't exist
```

Az alkalmazás eléri a MySQL-adatbázist, de a munkamenetekhez szükséges tábla hiányzik. Egy másik backend shellben futtasd a `php artisan migrate` parancsot, majd frissítsd az oldalt.

### Adatbázis-kapcsolati hiba

`Connection refused`, `Access denied` vagy `Unknown database` esetén ellenőrizd, hogy a DB-konténer fut-e, és a `.env` értékei megfelelnek-e a 3.4-es pontnak.

Ha korábban konfigurációs cache készült, a `.env` módosítása után töröld:

```bash
php artisan config:clear
```

Ezután próbáld újra a `php artisan migrate:status` vagy a `php artisan migrate` parancsot.

## 3.9 Commit és feltöltés GitHubra

Ha az ellenőrzések sikeresek, a **géped helyi termináljában**, az `E:\dev\todo-app` mappából:

```powershell
git status
git add backend
git status
```

Nézd át a hozzáadott fájlokat: a `backend/.env` és a `backend/vendor` nem kerülhet a commitba. A `backend/.env.example` és a `backend/composer.lock` viszont a projekt része, ezeket érdemes verziókövetni.

Ha a lista rendben van:

```powershell
git commit -m "Add Laravel skeleton"
git push
```

A `git add backend` csak a backend változásait készíti elő. A Docker-fájlokat és a dokumentációt külön is hozzá kell adni, ha még nem voltak commitolva.

## 3.10 Ellenőrző lista

- [ ] A Laravel projekt fájljai megjelentek a `backend` mappában.
- [ ] A `php artisan --version` Laravel 12-es verziót mutat.
- [ ] A `.env` MySQL-t, `db` hostot és `todo_db` adatbázist tartalmaz.
- [ ] Az API-támogatás és a Sanctum telepítése lefutott.
- [ ] A `php artisan migrate` létrehozta az alap táblákat.
- [ ] A `http://localhost:8001` címen megjelenik a Laravel kezdőoldala.
- [ ] A `.env` és a `vendor` nem került Gitbe.
- [ ] A Laravel alapváza commitolva és feltöltve van.
