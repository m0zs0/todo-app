# 4. Adatmodell és migrációk

Ebben a fejezetben létrehozzuk a felhasználók és a feladatok kezeléséhez szükséges adatbázis-szerkezetet. Tanuláshoz először **fizikai törlést** használunk; a logikai törlés későbbi továbbfejlesztés lehet.

## 4.1 Mit szeretnénk megvalósítani?

A későbbi alkalmazásban:

- az **admin** kezelheti a felhasználókat és valamennyi feladatot, beleértve a törlést is;
- a normál **felhasználó** a saját feladatait kezelheti;
- egy felhasználó fizikai törlésekor a hozzá tartozó feladatok is törlődnek;
- egy feladat törlésekor csak az adott feladat törlődik.

**Fontos:** most az adatbázis szerkezetét készítjük el. A törlési API-végpontok, a jogosultságellenőrzés és a React felület törlés gombjai később készülnek el. A `role` mező önmagában még senkinek nem ad jogosultságot.

| Adatbázis-elem | Feladat |
|---|---|
| `users` | Meglévő felhasználói tábla, amelyet `role` mezővel bővítünk. |
| `todos` | A feladatokat tároló új tábla. |
| `admin_todos_view` | A feladatokat a tulajdonos nevével és e-mail-címével együtt megjelenítő nézet. |
| `personal_access_tokens` | A Sanctum tokenjeinek már létrehozott táblája. |

A `users` és a Sanctum tábláit korábban már létrehoztuk; nem kell őket újra létrehozni.

## 4.2 Mi az a migráció?

A korábbi PHP-s példában SQL-lel hoztunk létre táblát:

```sql
CREATE TABLE cards (...);
```

Laravelben az adatbázis szerkezetét rendszerint **migrációkban** írjuk le. A migráció egy PHP-fájl, amely változtatásokat végez az adatbázison.

- Az **`up()`** végrehajtja a változtatást, például létrehoz egy táblát.
- A **`down()`** leírja, hogyan lehet visszavonni azt, például hogyan kell törölni a táblát.

A Laravel nyilvántartja, melyik migráció futott már le. A migrációs fájlok GitHubra is felkerülnek, így egy másik gépen is létrehozható belőlük az adatbázis **szerkezete**. A táblákban tárolt felhasználók és feladatok ettől nem kerülnek GitHubra.

## 4.3 Backend terminál megnyitása

A Laravel-parancsokat a **`todo-backend` konténerben**, a `/var/www` mappában futtasd. Ha a Laravel szerver már fut egy terminálban, nyiss egy másik shellt a konténerhez.

Ellenőrzés:

```bash
pwd
```

A várt eredmény `/var/www`. Ha valamelyik migrációs fájl már létezik, ne hozd létre újra ugyanazzal a paranccsal: nyisd meg a meglévő fájlt.

## 4.4 A felhasználók szerepkörének hozzáadása

A meglévő `users` táblához új `role` mezőt adunk:

| Mező | Lehetséges értékek | Alapérték |
|---|---|---|
| `role` | `admin`, `user` | `user` |

### A migráció létrehozása

A backend konténer termináljában:

```bash
php artisan make:migration add_role_to_users_table --table=users
```

Ez egy időbélyeggel kezdődő fájlt hoz létre a `backend/database/migrations` mappában, például `2026_11_10_..._add_role_to_users_table.php` néven.

**Miért `--table=users`?** Ezzel kifejezetten megadjuk, hogy a meglévő `users` táblához készítünk migrációt. Ebben az esetben az opció elhagyható: a Laravel az `add_role_to_users_table` névből is felismeri a táblát. Az opció a generált migráció alapvázát befolyásolja; maga a mező csak a fájl kitöltése és a migráció futtatása után kerül az adatbázisba.

### A migráció tartalma

Nyisd meg a létrehozott fájlt, és a tartalma legyen:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'user'])
                ->default('user')
                ->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
```

- A `Schema::table('users', ...)` a meglévő táblát módosítja.
- Az `enum` a megadott két szerepkört engedi meg.
- A `default('user')` az alapértelmezett érték. A meglévő felhasználók is ezt kapják; admin felhasználót később hozunk létre.
- Az `after('password')` az új oszlopot a MySQL táblaszerkezetében a `password` oszlop után helyezi el. Ez csak a megjelenítési sorrendet befolyásolja, a jogosultságokat nem; elhagyható.
- A `dropColumn('role')` visszavonáskor eltávolítja az oszlopot és a benne tárolt adatokat.

### Kell most migrálni?

Nem kötelező rögtön: a fájl létrehozása és kitöltése önmagában még nem módosítja az adatbázist. Két lehetőség van:

1. **Lépésenként:** a fájl mentése után futtasd a `php artisan migrate` parancsot, majd nézd meg a `users` táblát Adminerben. A későbbi migrációkat újabb futtatással alkalmazhatod.
2. **Egyszerre:** előbb készítsd el mindhárom migrációt, majd a 4.8-as pontban futtasd őket együtt.

Tanulásnál a lépésenkénti módszer jól megmutatja, mi változott. A `php artisan migrate` mindig az **összes addig létrehozott, még nem futtatott** migrációt alkalmazza, nem csak egy kiválasztott fájlt.

A külön futtatások külön migrációs csoportokat, úgynevezett *batch*-eket hozhatnak létre. Ez visszavonáskor számít: a `php artisan migrate:rollback` alapértelmezés szerint az utolsó batch-et vonja vissza. Visszavonás előtt mindig gondold át az adatvesztés és a függő objektumok következményeit.

## 4.5 A `Todo` modell és a feladatok táblája

### Modell és migráció létrehozása

```bash
php artisan make:model Todo -m
```

A parancs létrehozza a `backend/app/Models/Todo.php` modell alapvázát és a `backend/database/migrations/..._create_todos_table.php` migrációt. A `-m` azt jelenti, hogy a modell mellé migrációt is kérünk. A modell részletes beállításait később készítjük el.

### A migráció tartalma

Nyisd meg a létrehozott `..._create_todos_table.php` fájlt, és a tartalma legyen:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('todos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('completed')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todos');
    }
};
```

| Mező | Jelentés |
|---|---|
| `id` | A feladat automatikusan növekvő, egyedi azonosítója. |
| `user_id` | A tulajdonos felhasználó azonosítója. |
| `title` | A feladat címe; alapértelmezetten legfeljebb 255 karakter. |
| `description` | Leírás; a `nullable()` miatt `NULL` is lehet. |
| `completed` | Elkészült-e a feladat; alapértéke `false`. |
| `created_at`, `updated_at` | Létrehozás és utolsó módosítás időpontja; a `timestamps()` hozza létre. |

### A felhasználó és a feladat kapcsolata

```php
$table->foreignId('user_id')
    ->constrained()
    ->cascadeOnDelete();
```

A `foreignId` létrehozza a tulajdonos azonosítóját tároló mezőt, a `constrained()` idegen kulcsot hoz létre a `users.id` mezőre. Egy felhasználónak több feladata lehet, de egy feladathoz egy felhasználó tartozik.

A `cascadeOnDelete()` azt jelenti, hogy felhasználó **fizikai törlésekor** az adatbázis a hozzá tartozó feladatokat is fizikailag törli. Egyetlen feladat törlése viszont nem törli a felhasználót és a többi feladatát.

Például, ha Kiss Péternek három feladata van, Kiss Péter törlése mindhárom feladatot törli. Emiatt a későbbi React felületen felhasználó törlése előtt egyértelmű figyelmeztetést és megerősítést kell kérni.

## 4.6 Az adminisztrátori adatbázisnézet

Az adminnak később szüksége lesz egy listára, amelyben a feladat mellett a tulajdonos neve és e-mail-címe is látható. Ehhez **adatbázisnézetet**, angolul *view*-t hozunk létre. A nézet eltárolt SQL-lekérdezés, nem tárolja külön a feladatok másolatát.

### Migráció létrehozása

A view a `todos` táblára épül, ezért a migrációjának a tábla migrációja **után** kell következnie. A `Todo` modell létrehozása után várj legalább egy másodpercet, majd:

```bash
php artisan make:migration create_admin_todos_view
```

Nyisd meg az új `..._create_admin_todos_view.php` fájlt, és a tartalma legyen:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            CREATE VIEW admin_todos_view AS
            SELECT
                t.id,
                t.title,
                t.description,
                t.completed,
                t.created_at,
                t.updated_at,
                u.id AS user_id,
                u.name AS user_name,
                u.email AS user_email
            FROM todos AS t
            JOIN users AS u ON u.id = t.user_id
        ');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS admin_todos_view');
    }
};
```

- A `FROM todos AS t` a feladatokból indul ki.
- A `JOIN users AS u ON u.id = t.user_id` hozzákapcsolja a tulajdonost.
- Az `AS user_name` és az `AS user_email` nevet ad a nézet megfelelő oszlopainak.

Példa egy későbbi eredményre:

| id | title | user_name | user_email |
|---|---|---|---|
| 1 | Bevásárlás | Kiss Péter | peter@example.com |

Ebben az alkalmazásban a nézetet **listázásra** használjuk; módosítás és törlés a `todos` táblán keresztül történik majd. A nézet önmagában nem korlátozza az adminisztrátori hozzáférést: ezt később az API-ban kell megoldanunk.

## 4.7 Ellenőrzés futtatás előtt

A `backend/database/migrations` mappában három új fájlnak kell lennie:

```text
..._add_role_to_users_table.php
..._create_todos_table.php
..._create_admin_todos_view.php
```

A fájlnevek időbélyeggel kezdődnek. A **view migrációjának a todos migráció után** kell következnie. Az első kettő egymáshoz viszonyított sorrendje itt nem lényeges, mert az alap `users` tábla már létezik.

Ellenőrizd, hogy mindhárom fájlt elmentetted. Ha valamelyik migrációt már futtattad, ne módosítsd utólag úgy, mintha újra lefutna: a Laravel azt nyilvántartja. Későbbi sémamódosításhoz új migrációt készítünk.

## 4.8 A migrációk futtatása

A backend konténer termináljában:

```bash
php artisan migrate
```

A Laravel a még nem futtatott migrációkat alkalmazza: hozzáadja a `role` mezőt, létrehozza a `todos` táblát, majd a nézetet. A korábban már lefutott migrációkat nem futtatja újra.

Ellenőrzés:

```bash
php artisan migrate:status
```

A három új migráció mellett `Ran` állapotnak kell látszania. Ha korábban egyesével is futtattad őket, különböző batch-számot láthatsz; ez rendben van.

**Ne** a `php artisan migrate:fresh` parancsot használd erre: az törli a meglévő táblákat és a bennük lévő adatokat.

## 4.9 Ellenőrzés Adminerben

Nyisd meg a **http://localhost:8081** címet.

| Mező | Érték |
|---|---|
| System | MySQL |
| Server | `db` |
| Username | `todo` |
| Password | `todo_secret` |
| Database | `todo_db` |

Ellenőrizd:

1. A `users` táblában megjelent a `role` mező.
2. Létrejött a `todos` tábla.
3. A `todos.user_id` idegen kulcsként a `users.id` mezőre mutat, a törlési szabály pedig `CASCADE`.
4. Megjelent az `admin_todos_view` nézet.

A `todos` tábla és a nézet még üres lehet: most csak a szerkezetet hoztuk létre. A kaszkád törlést később, tesztadatokkal próbáljuk ki; most ne törölj meglévő adatokat csak az ellenőrzés kedvéért.

## 4.10 Mit készítettünk el, és mi maradt későbbre?

| Tevékenység | Ebben a fejezetben | Később |
|---|---|---|
| `users` bővítése szerepkörrel | ✓ | |
| `todos` tábla és adminnézet létrehozása | ✓ | |
| Felhasználó törlésekor a feladatok adatbázis-szintű törlése | ✓ | Tesztelés |
| Admin felhasználó létrehozása | | ✓ |
| Felhasználók és feladatok CRUD API-végpontjai | | ✓ |
| Jogosultságok ellenőrzése | | ✓ |
| React törlés gombok és megerősítés | | ✓ |
| Logikai törlés és visszaállítás | | Esetleges továbbfejlesztés |

Felhasználó törlésekor később a kapcsolódó **Sanctum-tokenek kezelését** is ellenőriznünk kell: a feladatokra vonatkozó adatbázis-kaszkád nem helyettesíti ezt.

## 4.11 Commit és GitHub-feltöltés

Ha a migrációk sikeresen lefutottak, a **géped helyi termináljában**, az `E:\dev\todo-app` mappából:

```powershell
git status
git add backend\database\migrations backend\app\Models\Todo.php
git status
```

Nézd át az előkészített fájlokat, majd ha minden rendben:

```powershell
git commit -m "Add todo database schema"
git push
```

Ez a `git add` csak a megadott migrációkat és modellt adja hozzá. Ha ezt vagy egy másik dokumentumot is szeretnéd feltölteni, azt külön kell hozzáadni.

## 4.12 Ellenőrző lista

- [ ] A `users` táblában megjelent a `role` mező.
- [ ] Létrejött a `todos` tábla és a `users.id` mezőre mutató idegen kulcs.
- [ ] Az idegen kulcs törlési szabálya `CASCADE`.
- [ ] Létrejött az `admin_todos_view` nézet.
- [ ] A három új migráció `Ran` állapotú.
- [ ] Létrejött a `Todo` modell alapfájlja.
- [ ] Az új fájlokat commitoltad és feltöltötted.

A következő részben a modellek kapcsolatait és a tesztadatokat készítjük el; utána következhetnek a CRUD-végpontok és a törlési jogosultságok.
