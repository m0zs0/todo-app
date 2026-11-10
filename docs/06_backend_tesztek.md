# 6. Backend tesztek

Ebben a fejezetben automatikus tesztekkel ellenőrizzük a Laravel API működését. A Postmannel végzett kézi próbát ezek nem helyettesítik: a két módszer kiegészíti egymást. Az automatikus tesztek előnye, hogy a fontos ellenőrzéseket később egy paranccsal újra tudjuk futtatni.

## 6.1 Mit tesztelünk?

A jelenlegi API-tesztek többek között ellenőrzik:

- a regisztrációt, a bejelentkezést és a kijelentkezést;
- hogy token nélkül nem érhetők el a védett útvonalak;
- hogy a normál felhasználó csak a saját feladatait kezelheti;
- hogy az admin láthatja és kezelheti mások feladatait is;
- hogy a felhasználókezelést csak admin érheti el;
- hogy felhasználó törlésekor a feladatai és tokenjei is törlődnek;
- hogy az admin nem törölheti magát, és nem vonhatja meg a saját adminjogát.

### Unit és feature teszt

A **unit teszt** általában egy kisebb, önálló működést ellenőriz. A **feature teszt** több rész együttműködését vizsgálja. Itt feature teszteket írunk, mert az API-kérés több rétegen halad át:

```text
HTTP-kérés → route → middleware → controller → policy / modell → adatbázis
                                                                    ↓
                                                               HTTP-válasz
```

Ezek a kérések a Laravel tesztkörnyezetében futnak; a Postmant és a böngészőt nem használják.

## 6.2 Külön tesztadatbázis

| Adatbázis | Feladat |
|---|---|
| `todo_db` | A fejlesztői alkalmazás és a Postmannel felvitt adatok. |
| `todo_db_test` | Kizárólag az automatikus tesztek. |

**Figyelem:** a tesztfuttatás a `todo_db_test` adatbázisban táblákat és nézeteket töröl, majd újra létrehozza őket. Ne tárolj benne megőrzendő adatokat, és soha ne irányítsd a teszteket a fejlesztői `todo_db` adatbázisra.

A 2. fejezetben a `docker/mysql/init.sql` létrehozta a `todo_db_test` adatbázist. A tesztfelhasználót külön hozzuk létre: csak a tesztadatbázishoz kap jogosultságot. Ez további védelmet ad, ha tévesen a fejlesztői adatbázisra próbálna kapcsolódni.

## 6.3 Külön MySQL-felhasználó a tesztekhez

### Belépés Adminerbe

A helyi Adminer címe: `http://localhost:8081`.

Jelentkezz be a felhasználó létrehozásához:

| Mező | Érték |
|---|---|
| System | MySQL |
| Server | `db` |
| Username | `root` |
| Password | `root_secret` |
| Database | `todo_db_test` |

A root felhasználót itt csak az adminisztrációhoz használjuk, nem a tesztek futtatásához.

### A tesztfelhasználó létrehozása

Adminerben válaszd az **SQL command** lehetőséget, majd futtasd:

```sql
CREATE USER 'todo_test'@'%' IDENTIFIED BY 'todo_test_secret';

GRANT ALL PRIVILEGES ON todo_db_test.* TO 'todo_test'@'%';
```

A `CREATE USER` létrehozza a felhasználót; a `GRANT` a `todo_db_test` adatbázisra ad jogokat. A `todo_db` fejlesztői adatbázisra nem adunk jogot. A példajelszó csak a helyi Docker-környezetben használható; éles környezetben nem.

Ha a felhasználó már létezik, ne töröld és ne írd felül automatikusan: előbb ellenőrizd a jogosultságait. Új környezetben a `SHOW GRANTS FOR 'todo_test'@'%';` paranccsal is megvizsgálhatók a jogai.

### Hozzáférés ellenőrzése

Jelentkezz ki Adminerből, majd lépj be a `todo_test` felhasználóval, a `todo_test_secret` jelszóval, a `db` szerverre és a `todo_db_test` adatbázisba. Ellenőrizd, hogy eléred a tesztadatbázist, a fejlesztői `todo_db` adatbázishoz pedig nincs hozzáférésed.

> A tesztfelhasználó a jelenlegi MySQL-volume-ban jön létre. A `docker/mysql/init.sql` most **nem** hozza létre ezt a felhasználót: új volume esetén a fenti beállítást meg kell ismételni. Volume törlése előtt gondolj arra, hogy az a benne tárolt adatbázisokat is törli.

## 6.4 A PHPUnit beállítása

A `backend/phpunit.xml` fájlban a Laravel eredetileg SQLite memóriabeli adatbázist használ. A `<php>` blokkban az adatbázis-beállításokat a külön MySQL tesztadatbázisra állítjuk:

```xml
<env name="DB_CONNECTION" value="mysql" force="true"/>
<env name="DB_HOST" value="db" force="true"/>
<env name="DB_PORT" value="3306" force="true"/>
<env name="DB_DATABASE" value="todo_db_test" force="true"/>
<env name="DB_USERNAME" value="todo_test" force="true"/>
<env name="DB_PASSWORD" value="todo_test_secret" force="true"/>
<env name="DB_URL" value="" force="true"/>
```

Az eredeti SQLite-os `DB_CONNECTION` és `DB_DATABASE` sorokat, valamint a korábbi `DB_URL` sort helyettesítsd; ne legyenek duplikált DB-beállítások. A többi beállítás, például az `APP_ENV`, a `CACHE_STORE` és a `SESSION_DRIVER`, maradjon a helyén.

- A `db:3306` a MySQL címe a Docker belső hálózatán.
- A `DB_URL` üres, hogy egy másutt megadott adatbázis-URL ne irányítsa át a kapcsolatot.
- A `force="true"` a PHPUnit által beállított környezeti változóknál felülírja az azonos nevű, már meglévő környezeti értékeket.
- A fejlesztői `backend/.env` fájlban továbbra is `DB_DATABASE=todo_db` szerepeljen.

A `todo_test_secret` ismert, kizárólag helyi példaérték. Valódi jelszót ne tegyél verziókövetett fájlba.

### Konfigurációs cache törlése

A **backend konténer termináljában**:

```bash
php artisan config:clear
```

Ez eltávolítja az esetleg korábban elkészített konfigurációs cache-t, amely máskülönben megakadályozhatná az aktuális tesztbeállítások használatát.

## 6.5 A tesztfájl létrehozása

A backend konténer `/var/www` mappájában:

```bash
php artisan make:test Api/TodoApiTest
```

A generált fájl helye: [`backend/tests/Feature/Api/TodoApiTest.php`](../backend/tests/Feature/Api/TodoApiTest.php). Ha már létrehoztad, ne generáld újra. A projektben ez a fájl tartalmazza a tesztek teljes, aktuális kódját; az alábbiak a működését magyarázzák.

### `TodoApiTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class TodoApiTest extends TestCase
{
    use RefreshDatabase;

    protected $dropViews = true;

    protected function beforeRefreshingDatabase(): void
    {
        if (
            config('database.default') !== 'mysql'
            || config('database.connections.mysql.database') !== 'todo_db_test'
            || config('database.connections.mysql.username') !== 'todo_test'
            || config('database.connections.mysql.host') !== 'db'
        ) {
            throw new RuntimeException(
                'A teszt csak a külön todo_db_test adatbázison futhat.'
            );
        }

        $database = DB::connection()->selectOne('SELECT DATABASE() AS name');

        if ($database->name !== 'todo_db_test') {
            throw new RuntimeException(
                'A tényleges adatbázis nem a todo_db_test.'
            );
        }
    }

    private function createTodo(User $owner, string $title): Todo
    {
        return Todo::create([
            'user_id' => $owner->id,
            'title' => $title,
        ]);
    }

    public function test_guest_cannot_access_todos(): void
    {
        // Arrange: vendégként, hitelesítés nélkül küldjük a kérést.

        // Act
        $this->getJson('/api/todos')
            // Assert
            ->assertUnauthorized();
    }

    public function test_registration_always_creates_a_normal_user(): void
    {
        // Arrange: a kérés szándékosan admin szerepkört tartalmaz.

        // Act
        $response = $this->postJson('/api/register', [
            'name' => 'Teszt Felhasználó',
            'email' => 'regisztracio@example.com',
            'password' => 'tesztjelszo123',
            'password_confirmation' => 'tesztjelszo123',
            'role' => 'admin',
        ]);

        // Assert
        $response->assertCreated()
            ->assertJsonPath('user.role', 'user')
            ->assertJsonStructure(['user', 'token']);

        $this->assertDatabaseHas('users', [
            'email' => 'regisztracio@example.com',
            'role' => 'user',
        ]);

        $user = User::where('email', 'regisztracio@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('tesztjelszo123', $user->password));
    }

    public function test_login_returns_a_token_and_rejects_wrong_password(): void
    {
        // Arrange
        $user = User::factory()->create([
            'password' => 'tesztjelszo123',
        ]);

        // Act
        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'tesztjelszo123',
        ])
            // Assert
            ->assertOk()
            ->assertJsonStructure(['user', 'token']);

        // Act
        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'hibas-jelszo',
        ])
            // Assert
            ->assertUnauthorized();
    }

    public function test_logout_deletes_the_current_token(): void
    {
        // Arrange
        $user = User::factory()->create();
        $token = $user->createToken('test-token');

        // Act
        $this->withToken($token->plainTextToken)
            ->postJson('/api/logout')
            // Assert
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_user_can_create_a_todo_only_for_themselves(): void
    {
        // Arrange
        $user = User::factory()->create();
        $other = User::factory()->create();

        Sanctum::actingAs($user);

        // Act
        $this->postJson('/api/todos', [
            'title' => 'Saját feladat',
            'user_id' => $other->id,
        ])
            // Assert
            ->assertCreated()
            ->assertJsonPath('user_id', $user->id);

        $this->assertDatabaseHas('todos', [
            'title' => 'Saját feladat',
            'user_id' => $user->id,
        ]);
    }

    public function test_user_sees_only_their_own_todos(): void
    {
        // Arrange
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->createTodo($user, 'Saját feladat');
        $this->createTodo($other, 'Másik feladata');

        Sanctum::actingAs($user);

        // Act
        $this->getJson('/api/todos')
            // Assert
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['title' => 'Saját feladat'])
            ->assertJsonMissing(['title' => 'Másik feladata']);
    }

    public function test_user_can_update_and_delete_their_own_todo(): void
    {
        // Arrange
        $user = User::factory()->create();
        $todo = $this->createTodo($user, 'Gyakorlás');

        Sanctum::actingAs($user);

        // Act
        $this->patchJson("/api/todos/{$todo->id}", [
            'completed' => true,
        ])
            // Assert
            ->assertOk()
            ->assertJsonPath('completed', true);

        // Act
        $this->deleteJson("/api/todos/{$todo->id}")
            // Assert
            ->assertOk();

        $this->assertDatabaseMissing('todos', [
            'id' => $todo->id,
        ]);
    }

    public function test_user_cannot_access_or_change_another_users_todo(): void
    {
        // Arrange
        $user = User::factory()->create();
        $other = User::factory()->create();
        $todo = $this->createTodo($other, 'Másik feladata');

        Sanctum::actingAs($user);

        // Act
        $this->getJson("/api/todos/{$todo->id}")
            // Assert
            ->assertForbidden();

        // Act
        $this->patchJson("/api/todos/{$todo->id}", [
            'title' => 'Jogosulatlan módosítás',
        ])
            // Assert
            ->assertForbidden();

        // Act
        $this->deleteJson("/api/todos/{$todo->id}")
            // Assert
            ->assertForbidden();

        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'title' => 'Másik feladata',
            'user_id' => $other->id,
        ]);
    }

    public function test_user_cannot_change_todo_owner(): void
    {
        // Arrange
        $user = User::factory()->create();
        $other = User::factory()->create();
        $todo = $this->createTodo($user, 'Eredeti cím');

        Sanctum::actingAs($user);

        // Act
        $this->patchJson("/api/todos/{$todo->id}", [
            'title' => 'Új cím',
            'user_id' => $other->id,
        ])
            // Assert
            ->assertOk();

        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'title' => 'Új cím',
            'user_id' => $user->id,
        ]);
    }

    public function test_admin_sees_all_todos_with_owner_data(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);

        $user = User::factory()->create([
            'name' => 'Kovács János',
            'email' => 'janos@example.com',
        ]);

        $this->createTodo($admin, 'Admin feladat');
        $this->createTodo($user, 'Felhasználó feladata');

        Sanctum::actingAs($admin);

        // Act
        $this->getJson('/api/todos')
            // Assert
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment([
                'title' => 'Felhasználó feladata',
                'user_name' => 'Kovács János',
                'user_email' => 'janos@example.com',
                'completed' => false,
            ]);
    }

    public function test_admin_can_create_and_delete_another_users_todo(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        Sanctum::actingAs($admin);

        // Act
        $response = $this->postJson('/api/todos', [
            'title' => 'Másnak létrehozott feladat',
            'user_id' => $user->id,
        ])
            // Assert
            ->assertCreated()
            ->assertJsonPath('user_id', $user->id);

        // Arrange: a létrehozott feladat azonosítója kell a törléshez.
        $todoId = $response->json('id');

        // Act
        $this->deleteJson("/api/todos/{$todoId}")
            // Assert
            ->assertOk();

        $this->assertDatabaseMissing('todos', [
            'id' => $todoId,
        ]);
    }

    public function test_normal_user_cannot_manage_users(): void
    {
        // Arrange
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        // Act
        $this->getJson('/api/admin/users')
            // Assert
            ->assertForbidden();
    }

    public function test_admin_can_create_read_and_update_a_user(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        // Act
        $response = $this->postJson('/api/admin/users', [
            'name' => 'Új felhasználó',
            'email' => 'uj@example.com',
            'password' => 'tesztjelszo123',
            'role' => 'user',
        ])
            // Assert
            ->assertCreated();

        // Arrange: a létrehozott felhasználó azonosítója kell a további kérésekhez.
        $userId = $response->json('id');

        // Act
        $this->getJson("/api/admin/users/{$userId}")
            // Assert
            ->assertOk()
            ->assertJsonPath('email', 'uj@example.com');

        // Act
        $this->patchJson("/api/admin/users/{$userId}", [
            'name' => 'Módosított név',
        ])
            // Assert
            ->assertOk()
            ->assertJsonPath('name', 'Módosított név');

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'name' => 'Módosított név',
        ]);
    }

    public function test_deleting_a_user_deletes_their_todos_and_tokens(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $todo = $this->createTodo($user, 'Törlendő feladat');
        $token = $user->createToken('test-token');

        Sanctum::actingAs($admin);

        // Act
        $this->deleteJson("/api/admin/users/{$user->id}")
            // Assert
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('todos', ['id' => $todo->id]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_admin_cannot_delete_or_demote_themselves(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($admin);

        // Act
        $this->deleteJson("/api/admin/users/{$admin->id}")
            // Assert
            ->assertStatus(422);

        // Act
        $this->patchJson("/api/admin/users/{$admin->id}", [
            'role' => 'user',
        ])
            // Assert
            ->assertStatus(422);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'admin',
        ]);
    }

    public function test_todo_title_is_required(): void
    {
        // Arrange
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        // Act
        $this->postJson('/api/todos', [])
            // Assert
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }
}
```

### Factory és `createTodo()`

A tesztek a Laravel meglévő `User::factory()` segítségével hoznak létre felhasználókat. A feladatokhoz a tesztosztály saját segédmetódust használ:

```php
private function createTodo(User $owner, string $title): Todo
{
    return Todo::create([
        'user_id' => $owner->id,
        'title' => $title,
    ]);
}
```

Most ezért nem szükséges külön `TodoFactory`. Ez nem darabszám-korlát: a `createTodo()` többször is meghívható, és egy factoryval is létrehozható akár egyetlen rekord. Factory később akkor lehet hasznos, ha sokféle tesztben különböző alapadatokra vagy állapotokra van szükség.

A tesztek saját adataikat hozzák létre, nem a helyi példaseedereket használják.

## 6.6 Biztonsági ellenőrzések és `RefreshDatabase`

A `TodoApiTest` a `RefreshDatabase` traitet használja:

```php
use RefreshDatabase;

protected $dropViews = true;
```

A Laravel ezzel a tesztfuttatás kezdetén újraépíti a tesztadatbázis szerkezetét, majd a teszteket adatbázis-tranzakciókkal különíti el. A `$dropViews = true` azért szükséges, mert a projektünkben az `admin_todos_view` nézetet is el kell távolítani az újbóli létrehozás előtt.

A tesztosztály `beforeRefreshingDatabase()` metódusa az újraépítés előtt ellenőrzi:

- a kapcsolat típusa `mysql`;
- az adatbázis neve `todo_db_test`;
- a felhasználó `todo_test`, a host pedig `db`;
- a ténylegesen kiválasztott adatbázis neve is `todo_db_test`.

Ha az ellenőrzés sikertelen, kivételt dob, és nem kezdi el a tesztadatbázis újraépítését. Ez **további védelem**, nem helyettesíti a korlátozott MySQL-jogosultságokat. A metódus ebben a tesztosztályban érvényes; más, adatbázist használó tesztosztályokat külön is biztonságosan kell beállítani.

## 6.7 AAA: Arrange, Act, Assert

A tesztesetekben megjegyzések jelzik az **AAA** minta részeit:

1. **Arrange** – a tesztadatok és körülmények előkészítése.
2. **Act** – a vizsgált művelet végrehajtása.
3. **Assert** – az eredmény ellenőrzése.

Példa a saját feladatok listázására:

```php
public function test_user_sees_only_their_own_todos(): void
{
    // Arrange
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->createTodo($user, 'Saját feladat');
    $this->createTodo($other, 'Másik feladata');

    Sanctum::actingAs($user);

    // Act
    $this->getJson('/api/todos')
        // Assert
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['title' => 'Saját feladat'])
        ->assertJsonMissing(['title' => 'Másik feladata']);
}
```

Az `Arrange` során két felhasználót és két feladatot hozunk létre, majd az első felhasználóként hitelesítünk. Az `Act` lekéri a feladatlistát; az `Assert` ellenőrzi a választ. Egyes tesztek több kérést is küldenek, ezért bennük az Act és Assert részek ismétlődnek.

### Hitelesítés a tesztekben

A `Sanctum::actingAs($user)` hitelesített felhasználót szimulál, ami a jogosultsági tesztekben kényelmes. Nem ugyanaz, mint egy valódi bejelentkezési kérés, ezért a bejelentkezést és a token törlését külön tesztesetek is vizsgálják.

### Válasz és adatbázis ellenőrzése

| Tesztmetódus | Jelentés |
|---|---|
| `assertOk()` | 200-as válasz |
| `assertCreated()` | 201-es válasz |
| `assertUnauthorized()` | 401-es válasz |
| `assertForbidden()` | 403-as válasz |
| `assertUnprocessable()` | 422-es válasz |
| `assertDatabaseHas(...)` | A megadott rekord megtalálható az adatbázisban. |
| `assertDatabaseMissing(...)` | A megadott rekord nem található az adatbázisban. |

Az adatbázis ellenőrzése különösen hasznos törlésnél: így nem csak a sikerüzenetre hagyatkozunk.

## 6.8 A tesztek futtatása

Futtatás előtt ellenőrizd:

1. A `todo_db_test` létezik, és nincs benne megőrzendő adat.
2. A `todo_test` felhasználó csak a tesztadatbázishoz kapott megfelelő hozzáférést.
3. A `phpunit.xml` a 6.4-es pont szerinti értékeket tartalmazza.
4. A konfigurációs cache-t törölted.
5. A `TodoApiTest` tartalmazza a 6.6-os pont szerinti biztonsági ellenőrzést.

Ezután a **backend konténer termináljában**:

```bash
php artisan test --filter=TodoApiTest
```

Ez a célzott API-teszteket futtatja. Sikeres tesztnél `PASS` látható; hibánál a kimenet megmutatja, melyik ellenőrzés nem sikerült.

Ha rendben van, a teljes tesztkészlet is futtatható:

```bash
php artisan test
```

A később hozzáadott tesztek működését is nézd át futtatás előtt: a teljes készlet olyan új teszteket is tartalmazhat, amelyekre a `TodoApiTest` saját adatbázis-ellenőrzése nem vonatkozik. A tesztekhez nem kell futnia az `artisan serve` fejlesztői szervernek, de a MySQL-konténernek igen.

## 6.9 Hibaelhárítás

### `Access denied`

Ellenőrizd, hogy a `todo_test` felhasználó létezik-e, a `phpunit.xml`-ben megadott jelszó egyezik-e, és a felhasználó rendelkezik-e a tesztadatbázisra adott jogokkal.

### `Unknown database 'todo_db_test'`

Adminerben rootként ellenőrizd, hogy létezik-e a tesztadatbázis. Nem kell emiatt törölni a Docker-volume-ot: az adatbázis külön is létrehozható.

### Az adatbázisra vonatkozó biztonsági ellenőrzés hibát jelez

Nézd át a `phpunit.xml` értékeit és a tesztfelhasználó jogosultságait, majd futtasd a `php artisan config:clear` parancsot. Ne távolítsd el az ellenőrzést azért, hogy a teszt továbbfusson.

### A nézet már létezik

Ellenőrizd, hogy a tesztosztályban szerepel-e a `protected $dropViews = true;` beállítás. Ha a hiba továbbra is fennáll, vizsgáld meg a pontos hibaüzenetet, mielőtt adatbázis-objektumot kézzel törölnél.

## 6.10 Git és GitHub

Ha a tesztek sikeresek, a **géped helyi Command Prompt termináljában**, az `E:\dev\todo-app` mappából:

```cmd
git status
git add backend\phpunit.xml backend\tests\Feature\Api\TodoApiTest.php docs\06_backend_tesztek.md
git status
git diff --cached
```

Nézd át az előkészített változásokat. A `.env`, valódi jelszavak és tokenek nem kerülhetnek a commitba. Ha minden rendben:

```cmd
git commit -m "Add backend API tests"
git push
```

## 6.11 Ellenőrző lista

- [ ] A `todo_db_test` létezik, és nincs benne megőrzendő adat.
- [ ] A `todo_test` felhasználó jogosultságait ellenőriztem.
- [ ] A `phpunit.xml` a külön MySQL tesztadatbázist használja.
- [ ] A konfigurációs cache törölve lett.
- [ ] A `TodoApiTest` tartalmazza az adatbázis-ellenőrzést és az AAA megjegyzéseket.
- [ ] A célzott és a teljes tesztfuttatás is sikeres.
- [ ] A kívánt fájlokat commitoltam és feltöltöttem.
