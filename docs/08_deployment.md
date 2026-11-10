# 8. Telepítés a tárhelyre – deployment

Ebben a fejezetben megtervezzük, hogyan kerülhet a helyben elkészült Laravel + React alkalmazás a Plesk tárhelyre.

| Szolgáltatás | Éles cím |
|---|---|
| React alkalmazás | `https://mozso.moriczcloud.hu/` |
| Laravel API | `https://mozso.moriczcloud.hu/api` |
| Bejelentkezési API | `https://mozso.moriczcloud.hu/api/login` |

Nem használunk `todo_` adatbázis-előtagot. A régi alkalmazás tábláit mentés és ellenőrzés után eltávolítjuk, az újakat Laravel-migrációkkal hozzuk létre.

> **Fontos:** ez telepítési útmutató, nem elvégzett telepítés. A pontos tárhelyes mappaútvonalakat, jogosultságokat és szerverbeállításokat a Pleskben és SSH-n ellenőrizni kell. Az útmutatóban szereplő szerverútvonal-példákat nem szabad ellenőrzés nélkül bemásolni.

## 8.1 A telepítés áttekintése

Az első telepítés tervezett sorrendje:

1. Helyben felkészítjük a Laravelt a React build kiszolgálására.
2. Helyben elkészítjük és ellenőrizzük az éles React buildet.
3. A forráskódot commitoljuk és feltöltjük GitHubra.
4. Mentjük a jelenlegi tárhelyfájlokat és adatbázist.
5. A Plesk Git segítségével külön mappába telepítjük az új projektet.
6. Beállítjuk a Laravel éles `.env` fájlját és a Composer-függőségeket.
7. Mentés és ellenőrzés után eltávolítjuk a régi adatbázis-objektumokat, majd futtatjuk az új migrációkat.
8. Létrehozzuk az első éles admin felhasználót.
9. Feltöltjük a React buildet.
10. A domain kezdőkönyvtárát átállítjuk az új Laravel `public` mappájára.
11. Ellenőrizzük a működést.

**Kieséssel kell számolni:** a régi alkalmazás már a régi táblák eltávolításától kezdve nem tud rendesen működni, akkor sem, ha a domain kezdőkönyvtárát még nem állítottuk át.

## 8.2 A javasolt tárhelyes mappaszerkezet

```text
tárhely/
├─ régi-projekt/
│  └─ backend/
│     └─ public/          ← jelenlegi domain-kezdőkönyvtár
│
└─ todo-app-projekt/      ← Gitből telepített teljes projekt
   ├─ backend/
   │  ├─ .env             ← éles beállítások, nem nyilvános
   │  ├─ app/
   │  ├─ vendor/
   │  ├─ storage/
   │  └─ public/          ← ez lesz az új domain-kezdőkönyvtár
   │     ├─ index.php
   │     ├─ .htaccess
   │     └─ spa/          ← ide kerül a React dist tartalma
   │        ├─ index.html
   │        └─ assets/
   └─ frontend/
```

Ez szemléltető szerkezet: a tárhely tényleges útvonala eltérhet. A Pleskben kizárólag az új projekt **`backend/public`** mappája legyen a domain kezdőkönyvtára, ne a teljes projekt vagy a `backend` mappa. Így a Laravel `.env`, `vendor` és alkalmazáskódja a webgyökéren kívül marad.

A React buildet külön `public/spa` almappába helyezzük. A Laravel `public/index.php` és `public/.htaccess` fájlja a helyén marad; a Laravel szolgálja ki a React `index.html` fájlját a frontend útvonalakon.

---

# I. Helyi előkészítés

## 8.3 Laravel: a React oldal kiszolgálása

A `backend/routes/web.php` gyökérútvonala jelenleg a Laravel üdvözlőoldalát jeleníti meg. A mostani React alkalmazás a `/` és a `/login` útvonalakat használja. Ezekhez helyben a következő útvonalat készítjük:

```php
<?php

use Illuminate\Support\Facades\Route;

Route::get('/{page?}', function () {
    return response()->file(
        public_path('spa/index.html'),
        ['Content-Type' => 'text/html; charset=UTF-8']
    );
})->where('page', 'login');
```

- A `/` és a `/login` útvonalon a React build HTML-fáját szolgálja ki.
- Az API útvonalai továbbra is a `routes/api.php` fájlban maradnak.
- Nem irányít minden ismeretlen URL-t a Reacthez. Ha később új frontend útvonalat adunk hozzá, ezt az útvonalat is bővíteni és tesztelni kell.

Ez **tervezett kódmódosítás**: a projektben külön el kell végezni. Utána a Laravel gyökérútvonala csak akkor ad React-oldalt, ha a `backend/public/spa/index.html` fájl is létezik. A fejlesztői React felületet továbbra is a Vite szolgálja ki a `localhost:5174` címen.

Az alap Laravel feature teszt jelenleg ellenőrzi a `/` útvonalat, ezért ez a változás érintheti a tesztet is. A megfelelő buildfájllal és tesztkörnyezettel helyben ellenőrizni kell; a hibát nem szabad egyszerűen figyelmen kívül hagyni.

## 8.4 Az éles frontend build elkészítése

A helyi fejlesztői `frontend/.env` értéke maradhat:

```dotenv
VITE_API_URL=http://localhost:8001/api
```

Az éles build az API-t ugyanazon a domainen, relatív `/api` címen fogja keresni. A **frontend konténer `/app` mappájában**:

```bash
VITE_API_URL=/api npm run build -- --base=/spa/
```

- A `VITE_API_URL=/api` erre az egy parancsra adja meg az éles API-címet.
- Az `npm run build` elkészíti a kiadható frontendfájlokat.
- A `--base=/spa/` a buildelt statikus fájlok URL-jét állítja be, például `/spa/assets/...`.

A felhasználó továbbra is a domain **gyökerét** nyitja meg; a `/spa/` az assetek útvonala. A build `frontend/dist` mappájának teljes tartalmára szükség van.

Ellenőrizd a `frontend/dist/index.html` fájlban, hogy a generált JavaScript- és CSS-útvonalak `/spa/`-val kezdődnek. A pontos fájlnevek buildenként változhatnak.

> A Vite a `VITE_API_URL` értékét buildeléskor építi be; utólag a szerver `.env` módosítása nem változtatja meg a már elkészült JavaScriptet. `VITE_` változóba ne kerüljön titok.

## 8.5 Helyi ellenőrzés és GitHub-feltöltés

Először ellenőrizd a Laravel útvonalát és a React build tényleges kiszolgálását, beleértve a `/login` oldal közvetlen megnyitását és az API elérését. A frontend konténerben futtasd:

```bash
npm run lint
```

Ha a backend is módosult, a backend teszteket is futtasd a külön tesztadatbázison. Az éles buildet a 8.4-es parancs szerint készítsd el.

A gép **helyi Command Promptjában**, a projekt gyökeréből:

```cmd
git status
git add backend\routes\web.php
git status
git diff --cached
```

Ha további, ehhez a kiadáshoz tartozó módosítások is vannak, azokat külön add hozzá. A teljes előkészített diff ellenőrzése után:

```cmd
git commit -m "Prepare application for deployment"
git push
```

A `frontend/dist` generált kimenet maradjon kizárva a Gitből: a buildet külön töltjük fel a tárhelyre. Ha a frontend forrása a build után változik, készíts új buildet; a feltöltött build ugyanahhoz a forrásállapothoz tartozzon, mint a GitHubra kerülő commit.

---

# II. Az első éles telepítés

## 8.6 A régi alkalmazás mentése

**Bármilyen táblatörlés előtt készíts teljes mentést.**

### Fájlok

A Plesk Fájlkezelőjével vagy a tárhely szolgáltatójának mentési lehetőségével mentsd a régi alkalmazást, különösen a konfigurációt és a feltöltött fájlokat. A Git nem feltétlenül tartalmazza a `.env` fájlt vagy a felhasználói feltöltéseket. A régi fájlokat egyelőre ne töröld.

### Adatbázis

A Plesk adatbáziskezelőjében vagy phpMyAdminban készíts **teljes exportot** a meglévő adatbázisról. A mentést weben nem elérhető helyen tárold, lehetőleg a saját gépedre is töltsd le, és ellenőrizd, hogy valóban visszaállítható. A mentés érzékeny adatokat tartalmazhat: ne kerüljön GitHubra.

### Mit ellenőrizz törlés előtt?

1. Valóban a lecserélendő alkalmazás adatbázisát választottad-e ki.
2. Más alkalmazás nem használja-e az adatbázist vagy valamelyik táblát.
3. Mi a domain jelenlegi Plesk-kezdőkönyvtára: jegyezd fel a visszaállításhoz.

Ha nem egyértelmű, hogy egy tábla vagy nézet kihez tartozik, **ne töröld**, amíg nem tisztázod.

## 8.7 A projekt elhelyezése Plesk Gittel

1. A Pleskben nyisd meg a **Git** menüpontot, és add hozzá a `todo-app` GitHub-repót.
2. A célmappa legyen egy **új, külön projektmappa**, ne a régi alkalmazás mappája és ne a jelenlegi webgyökér.
3. Válaszd a megfelelő ágat, például `main`.
4. Használd a Plesk által kínált **Fetch/Fogadás most**, majd szükség esetén **Deploy/Telepítés most** műveletet.

Az elérhető beállítások a Plesk és a tárhely konfigurációjától függenek. Privát repóhoz GitHub-hozzáférés is kellhet; hozzáférési kulcsot vagy tokent ne tegyél a repóba.

A célmappában várhatóan megjelenik a `backend`, a `frontend` és a `docker-compose.yml`. Az ignorált `backend/.env`, `backend/vendor` és `frontend/dist` nem kerülnek automatikusan a Gitből a szerverre.

## 8.8 PHP és Composer ellenőrzése

A Pleskben látható PHP 8.3 jó kiindulás a Laravel 12-höz, de a **webes PHP** és az **SSH-ban használt PHP** eltérhet.

SSH-n:

```bash
php -v
composer --version
```

Ha a CLI-verzió eltér, a tárhelyszolgáltató által megadott megfelelő PHP CLI-t kell használni. A Plesk PHP Composer felülete is használható, ha a tárhelyeden megfelelően működik.

A backend függőségeinek telepítése után ellenőrizd a platformkövetelményeket:

```bash
composer check-platform-reqs --no-dev
```

Ne kerüld meg a hiányzó PHP-bővítményeket az `--ignore-platform-reqs` kapcsolóval; a hibát a Plesk PHP-beállításainál vagy a szolgáltatónál kell rendezni. A webes PHP bővítményeit külön is ellenőrizni kell, ha az eltér a CLI-től.

## 8.9 Az éles Laravel `.env`

A szerveren az új projekt `backend` mappájában készíts `.env` fájlt a `.env.example` alapján. **Csak akkor**, ha az éles `.env` még nem létezik:

```bash
cp .env.example .env
```

A fontos részek például:

```dotenv
APP_NAME="Todo App"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://mozso.moriczcloud.hu

DB_CONNECTION=mysql
DB_HOST=IDE_A_TARHELY_ADATBAZIS_HOSTJA
DB_PORT=3306
DB_DATABASE=IDE_A_TARHELY_ADATBAZIS_NEVE
DB_USERNAME=IDE_A_TARHELY_ADATBAZIS_FELHASZNALOJA
DB_PASSWORD=IDE_A_TARHELY_ADATBAZIS_JELSZAVA
```

A `db` hostnév a helyi Docker Compose-ra vonatkozik; az éles adatbázis hostja ettől eltérhet. Az adatokat a Pleskben vagy a szolgáltatótól kapott tájékoztatóban keresd meg.

- Az éles `.env`, az adatbázisjelszó és az `APP_KEY` ne kerüljön GitHubra vagy nyilvános helyre.
- Az éles adatbázishoz erős jelszót használj.
- A helyi `adminpw` és `userpw` példajelszavak nem éles használatra valók.
- `APP_DEBUG=false` mellett a részletes hibák ne jelenjenek meg a látogatóknak.

## 8.10 Backend-függőségek és alkalmazáskulcs

SSH-n lépj az új projekt **tényleges** `backend` mappájába. Az alábbi csak szemléltetés:

```bash
cd /a/tarhelyen/megadott/todo-app-projekt/backend
```

Ezután:

```bash
composer install --no-dev --optimize-autoloader
composer check-platform-reqs --no-dev
```

A Composer a `composer.lock` alapján telepíti az éles függőségeket. Ha hibát jelez, azt előbb rendezni kell.

Az **első telepítéskor**, ha az új `.env` fájlban az `APP_KEY` üres:

```bash
php artisan key:generate
```

Későbbi frissítéskor **ne generáld újra** a kulcsot, és ne írd felül az éles `.env`-et. A Laravel `storage` és `bootstrap/cache` mappájának a megfelelő szerverfelhasználó számára írhatónak kell lennie. A jogosultságok a tárhelytől függenek; ne adj automatikusan mindenkinek teljes írási jogot.

## 8.11 Régi táblák eltávolítása és migráció

**Ez a rész adatvesztéssel jár.** Csak akkor folytasd, ha elkészült és ellenőrzött a mentés, biztosan a megfelelő adatbázisban vagy, más alkalmazás nem használja a törlendő objektumokat, és vállalod a kiesést.

1. Plesk → **Adatbázisok** → a megfelelő adatbázis kezelése.
2. Nézd át a táblákat és esetleges nézeteket. A régi `users`, `sessions`, `migrations` és `personal_access_tokens` például ütközne az új alkalmazással.
3. A régi alkalmazáshoz tartozó objektumokat csak a mentés és azonosítás után távolítsd el. Idegen kulcsoknál a törlés sorrendje számíthat; függőségi hiba esetén ne próbálgasd vakon.

Éles adatbázison ne használd a `migrate:fresh` vagy `db:wipe` parancsot: a kiválasztott adatbázis több objektumát is törölhetik.

A szerveren, az új projekt backend mappájában:

```bash
php artisan migrate:status
```

Üres adatbázisban jelezheti, hogy a migrációs tábla még nem létezik. Ezután:

```bash
php artisan migrate
```

Éles környezetben a Laravel megerősítést kérhet. Csak akkor hagyd jóvá, ha biztosan a megfelelő adatbázisra mutat az éles `.env`. Ellenőrzés:

```bash
php artisan migrate:status
```

Az adatbáziskezelőben is vizsgáld meg a `users`, `todos`, `personal_access_tokens`, `sessions`, `migrations` táblákat és az `admin_todos_view` nézetet. Az adatbázis-felhasználónak a view létrehozásához szükséges jogosultságokra is szüksége van.

**Ne futtasd az 5. fejezet helyi seedereit élesben:** ismert gyakorló jelszavakat tartalmaznak és `local` környezetre vannak korlátozva.

## 8.12 Az első éles admin

Az üres adatbázisban még nincs admin. Az első admint külön, biztonságosan kell létrehozni. A javasolt megoldás egy egyszer futtatható Artisan-parancs, amely:

- bekéri az admin nevét és e-mail-címét;
- rejtett bevitellel kéri be a jelszót;
- ellenőrzi, hogy a felhasználó nem létezik már;
- `admin` szerepkörrel hozza létre;
- nem tartalmaz beégetett jelszót, és nem írja ki azt.

Ezt a parancsot **még külön el kell készíteni és helyben tesztelni**, majd GitHubon keresztül feltölteni. A parancsot SSH-n egyszer futtatod, az adatokat te adod meg. Ne add meg a jelszót sima szövegként egy terminálparancs részeként, mert bekerülhet a parancselőzményekbe. Az admin létrehozása előtt az éles felületet lehetőleg még ne tedd nyilvánosan elérhetővé.

## 8.13 A React build feltöltése

A helyi `frontend/dist` **teljes tartalma** a szerveren az új projekt `backend/public/spa` mappájába kerül:

```text
helyben:    frontend/dist/index.html
szerveren:  backend/public/spa/index.html

helyben:    frontend/dist/assets/
szerveren:  backend/public/spa/assets/
```

### Plesk Fájlkezelővel

1. A helyi gépen készíts ZIP-et a `dist` mappa **tartalmából**.
2. A Plesk Fájlkezelőjében navigálj az **új projekt** `backend/public` mappájába.
3. Hozz létre egy `spa` mappát.
4. Töltsd fel és bontsd ki a ZIP-et úgy, hogy az `index.html` közvetlenül a `spa` mappában legyen.

A végeredmény `backend/public/spa/index.html`, nem `backend/public/spa/dist/index.html` és nem `backend/public/index.html`. A Laravel `public/index.php` és `.htaccess` fájlját **ne írd felül vagy töröld**.

A kibontás után a feltöltött ZIP-et ne hagyd nyilvánosan elérhető helyen: a konkrét archívumot távolítsd el, vagy tárold a webgyökéren kívül.

## 8.14 A Plesk kezdőkönyvtárának átállítása

Ha az új alkalmazás konfigurációja, migrációi, adminja és React buildje elkészült:

1. A Pleskben nyisd meg a domain **Üzemeltetés és DNS** beállításait.
2. A **Kezdőkönyvtár** értékét állítsd az új projekt `backend/public` mappájára. A pontos útvonalat a tényleges Plesk-célmappa alapján válaszd ki.
3. Mentsd a beállítást. Az SSL/TLS-tanúsítvány és a HTTPS-átirányítás maradjon bekapcsolva.

Ettől a domain az új alkalmazást szolgálja ki. A régi projekt mappáját ekkor még ne töröld: szükség lehet rá a visszaállításhoz.

## 8.15 Éles ellenőrzés

Nyisd meg a `https://mozso.moriczcloud.hu/` címet, és ellenőrizd:

1. Megjelenik a React bejelentkezési oldal.
2. A `/login` közvetlen megnyitása és frissítése működik.
3. Az éles adminnal be tudsz lépni.
4. A feladatok és felhasználók tervezett CRUD-műveletei működnek.
5. A normál felhasználó nem fér hozzá az adminműveletekhez.
6. A kijelentkezés működik, és a naplókban nincs releváns hiba.

Ellenőrizd, hogy a `https://mozso.moriczcloud.hu/.env`, a `/vendor/`, a Laravel forráskódja és a mentések **nem érhetők el nyilvánosan**. Egy 404-es válasz értelmezésekor ügyelj arra is, hogy az oldal valóban nem szolgáltatja ki a védett tartalmat.

> A jelenlegi alkalmazásban nyilvános regisztráció van. Élesítés előtt döntsd el, hogy bárki regisztrálhasson-e. A böngészős `localStorage`-os tokentárolásnak is vannak XSS-kockázatai; éles használat előtt érdemes külön átnézni a biztonságot.

### Ha valami nem működik

- **500-as hiba:** nézd meg a Plesk és Laravel naplóit; élesben ne kapcsold be tartósan az `APP_DEBUG`-ot.
- **Üres oldal:** a böngésző fejlesztői eszközeiben ellenőrizd, betöltődnek-e a `/spa/assets/...` fájlok.
- **API-hiba:** a kérés valóban a `https://mozso.moriczcloud.hu/api/...` címre megy-e?
- **`/login` frissítése 404:** ellenőrizd a Laravel útvonalát és a webkiszolgáló átirányítását.
- **Adatbázishiba:** ellenőrizd az éles `.env`-et és az adatbázis-felhasználó jogosultságait.

---

# III. Későbbi frissítések

## 8.16 Forráskód és React build frissítése

### 1. Helyi ellenőrzés és build

A frontend konténerben:

```bash
npm run lint
VITE_API_URL=/api npm run build -- --base=/spa/
```

Ha a backend is módosult, a backend teszteket a külön helyi tesztadatbázison futtasd. Az éles buildet a kiadandó forrásállapotból készítsd.

### 2. Commit és push

A gép helyi Command Promptjában:

```cmd
git status
git add .
git status
git diff --cached
git commit -m "Update todo application"
git push
```

A `git add .` minden nem ignorált változást előkészíthet; ellenőrizd a teljes listát és diffet. Éles `.env`, token és valódi jelszó nem kerülhet a commitba.

### 3. Plesk Git

A megfelelő projekt Git menüpontjában válaszd a **Fetch/Fogadás most**, majd ha külön szükséges, a **Deploy/Telepítés most** műveletet. Ellenőrizd, hogy a kívánt commit került-e ki. Az ignorált React `dist` mappát a Git nem hozza magával.

### 4. Backendfüggőségek és migrációk

Ha a backend függőségei változtak, a szerver backend mappájában:

```bash
composer install --no-dev --optimize-autoloader
```

Ha új migráció van, előtte készíts mentést, majd:

```bash
php artisan migrate
```

Ne írd felül az éles `.env` fájlt, és ne generálj új `APP_KEY`-t.

### 5. A React build frissítése

A friss `frontend/dist` tartalmát töltsd fel a szerveren a `backend/public/spa` mappába. Az új asseteket célszerű előbb feltölteni, és csak utána lecserélni az `index.html`-t. A régi asseteket ne töröld rögtön: egy már megnyitott vagy gyorsítótárazott oldal még hivatkozhat rájuk. A megszakításmentes csere automatizálását később külön is megtervezhetjük.

### 6. Ellenőrzés

Nyisd meg az éles oldalt, próbáld ki az érintett funkciókat, szükség esetén használj `Ctrl+F5`-öt, és nézd meg a böngésző és a Laravel naplóit.

## 8.17 Visszaállási lehetőség

Ha az új alkalmazás nem működik, a Plesk kezdőkönyvtára visszaállítható a korábbi értékre. **Ez önmagában nem elég**, ha a régi adatbázist már töröltük: azt a mentésből is vissza kell állítani. Előbb gondold át, keletkeztek-e időközben új todo adatok, amelyeket szintén menteni kell.

## 8.18 A tényleges telepítés előtti nyitott feladatok

- [ ] A Laravel React-oldalt kiszolgáló útvonalát és a `/spa/` buildet helyben ki kell alakítani és kipróbálni.
- [ ] A gyökérútvonalat ellenőrző tesztet az új működéshez igazítva ellenőrizni kell.
- [ ] Az első éles adminhoz szükséges Artisan-parancsot külön el kell készíteni és tesztelni.
- [ ] A Plesk tényleges projekt- és webgyökérútvonalát, PHP CLI-jét és adatbázis-jogosultságait ellenőrizni kell.
- [ ] A táblák törlése előtt ellenőrzött mentés szükséges.
- [ ] A build, a statikus fájlok, a közvetlen `/login` kérés és az API éles működését ellenőrizni kell.
