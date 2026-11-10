# 5. Modellek, seeder, hitelesítés és API

Ebben a fejezetben az előző részben létrehozott adatbázisra építve elkészítjük a Laravel API-t. A normál felhasználó a saját feladatait kezelheti; az admin az összes feladatot és a felhasználókat is. A törlés fizikai: a törölt rekord nem állítható vissza az alkalmazásból.

Az Artisan-parancsokat a **`todo-backend` konténer `/var/www` mappájában** futtasd. A Git-parancsokhoz a gép helyi terminálját használjuk. Ha egy fájlt vagy osztályt már létrehoztál, ne generáld újra: ellenőrizd a meglévő tartalmát.

## 5.1 Az alkalmazás részei

| Elem | Feladat |
|---|---|
| Modell | Az adatbázis rekordjait és kapcsolatait képviseli a PHP-kódban. |
| Seeder | Fejlesztéshez példaadatokat hoz létre. |
| Auth és Sanctum | Regisztráció, bejelentkezés és tokenes hitelesítés. |
| Middleware | A HTTP-kérést ellenőrzi, mielőtt az eljut a controllerhez. |
| Policy | Egy modellhez tartozó jogosultsági szabályokat határoz meg. |
| Controller | Feldolgozza a kérést és választ ad. |
| Route | Az URL-t és a HTTP-metódust a controller műveletéhez rendeli. |

## 5.2 A `User` modell

A már meglévő `backend/app/Models/User.php` tartalma:

```php
<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
```

- A `HasApiTokens` lehetővé teszi Sanctum-token létrehozását.
- A `$fillable` felsorolja a tömegesen kitölthető mezőket. Bár a `role` itt szerepel, a regisztrációs controller nem fogad el szerepkört a kérésből: mindig `user` értéket állít be.
- A `$hidden` megakadályozza, hogy a jelszó és a `remember_token` a modell JSON-válaszában megjelenjen.
- A `todos()` egy felhasználó több feladatára mutató kapcsolat.
- Az `isAdmin()` akkor igaz, ha a szerepkör `admin`.

### Mire való a `casts()`?

A `casts()` megadja, hogyan kezelje az Eloquent a modell egyes mezőit. Nem nekünk kell közvetlenül meghívni: a Laravel a modell használatakor alkalmazza a szabályokat.

- Az `email_verified_at` dátumobjektumként kezelhető. A mező lehet `null`, ezért használat előtt ezt ellenőrizni kell.
- A `password` a modellbe beállításkor hash-elve tárolódik. A hash-ből nem fejthető vissza a jelszó; bejelentkezéskor a `Hash::check()` hasonlítja össze a megadott jelszót a tárolt hash-sel.

Az Eloquent-castok nem érvényesülnek automatikusan közvetlen SQL vagy `DB::table(...)` használatakor.

### UTC és magyar idő

A Laravel alkalmazás időzónája a `backend/config/app.php` fájlban alapértelmezés szerint `UTC`. A MySQL konténerünk is UTC-t használ, ezért Adminerben a tárolt idő télen egy, nyáron két órával korábbinak látszhat a magyar helyi időnél.

Az alkalmazás időzónája ugyan átállítható például `'timezone' => 'Europe/Budapest'` értékre, de általában célszerűbb **UTC-ben tárolni**, és a megjelenítéskor a kívánt időzónára váltani. Így nem keverednek a téli és nyári helyi időpontok. A projekten most nem állítjuk át az időzónát.

Példa Laravelben egy dátum magyar idő szerinti megjelenítésére, ha az érték nem `null`:

```php
$magyarIdo = $user->created_at->copy()->timezone('Europe/Budapest');
echo $magyarIdo->format('Y-m-d H:i:s');
```

Később a React felületen is a megjelenítéskor alakítjuk át az API-tól kapott időpontot.

## 5.3 A `Todo` modell

A modellt már létrehoztuk a migrációval együtt; ne futtasd újra a `make:model` parancsot. A `backend/app/Models/Todo.php` tartalma:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Todo extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'completed',
    ];

    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

A `user()` kapcsolat a feladat tulajdonosára mutat. A `completed` cast miatt a modellen keresztül PHP-ban logikai értéket kapunk: `true` vagy `false`.

## 5.4 Példaadatok külön seederekkel

A seederek kezdeti adatokat hoznak létre. A `DatabaseSeeder` már létezik; a két további seedert a backend konténerben hozd létre:

```bash
php artisan make:seeder UserSeeder
php artisan make:seeder TodoSeeder
```

Ha a fájlokat már létrehoztad, ne futtasd újra ezeket a parancsokat. A `UserSeeder` előbb létrehozza a felhasználókat, a `TodoSeeder` pedig e-mail-címük alapján megkeresi őket, és hozzájuk rendeli a feladatokat.

### `database/seeders/DatabaseSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException(
                'A példaadatokat csak helyi környezetben lehet betölteni.'
            );
        }

        DB::transaction(function () {
            $this->call([
                UserSeeder::class,
                TodoSeeder::class,
            ]);
        });
    }
}
```

A `$this->call()` a megadott sorrendben futtatja a seedereket. A közös tranzakció miatt ha a második seeder hibázik, az első által végzett adatbázis-változtatások is visszavonódnak.

### `database/seeders/UserSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException(
                'A példafelhasználókat csak helyi környezetben lehet létrehozni.'
            );
        }

        DB::transaction(function () {
            $emails = [
                'admin@example.com',
                'user@example.com',
            ];

            if (User::whereIn('email', $emails)->exists()) {
                throw new RuntimeException(
                    'Legalább az egyik példafelhasználó már létezik.'
                );
            }

            User::create([
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => 'adminpw',
                'role' => 'admin',
            ]);

            User::create([
                'name' => 'Sima User',
                'email' => 'user@example.com',
                'password' => 'userpw',
                'role' => 'user',
            ]);
        });
    }
}
```

A seeder ellenőrzi a `local` környezetet, és nem ír felül már létező példafelhasználót. A `User` modell `hashed` castja miatt a jelszavak hash-elve kerülnek az adatbázisba.

**Ezek nyilvános, gyenge gyakorló jelszavak:** kizárólag helyi környezetben használd őket. A bejelentkezés elfogadja őket, de új felhasználó API-n keresztüli létrehozásakor a jelszónak legalább 8 karakteresnek kell lennie.

### `database/seeders/TodoSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TodoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException(
                'A példafeladatokat csak helyi környezetben lehet létrehozni.'
            );
        }

        DB::transaction(function () {
            $admin = User::where('email', 'admin@example.com')->firstOrFail();
            $user = User::where('email', 'user@example.com')->firstOrFail();

            Todo::firstOrCreate([
                'user_id' => $admin->id,
                'title' => 'Admin feladat',
            ]);

            Todo::firstOrCreate([
                'user_id' => $user->id,
                'title' => 'Első feladat',
            ]);
        });
    }
}
```

A `firstOrFail()` hibát jelez, ha a felhasználó nem létezik. A `firstOrCreate()` az adott felhasználóhoz és címhez előbb megkeresi a feladatot, és csak akkor hozza létre, ha nem találja. A külön tranzakciók a seedereket önálló futtatás esetén is védik.

### Futtatás és ellenőrzés

```bash
php artisan db:seed
```

Adminerben ellenőrizd a `users` és `todos` táblákat. A teljes seeder újrafuttatása szándékosan hibával megáll, ha valamelyik példa e-mail már létezik; nem írja felül a meglévő felhasználót. A törlő `migrate:fresh` parancs nem szükséges ehhez.

## 5.5 Middleware és policy

Mindkettő segíthet a jogosultság ellenőrzésében, de a projektben más szinten használjuk őket:

- **Middleware:** a kérés útvonalát védi. Az `auth:sanctum` ellenőrzi a hitelesítést; az `EnsureAdmin` azt, hogy az admin útvonalra belépő felhasználó admin-e.
- **Policy:** egy adott modellen végzett műveletet ellenőriz. A `TodoPolicy` például megvizsgálja, hogy a felhasználó törölheti-e az adott feladatot.

| Helyzet | Ellenőrzés | Várt válasz |
|---|---|---|
| Token nélkül valaki feladatot törölne | `auth:sanctum` | 401 |
| Normál user más feladatát törölné | `TodoPolicy` | 403 |
| Admin más feladatát törölné | `TodoPolicy` | Engedélyezett |
| Normál user az admin felhasználólistát kérné | `EnsureAdmin` | 403 |

### Admin middleware

Létrehozás a backend konténerben:

```bash
php artisan make:middleware EnsureAdmin
```

A `backend/app/Http/Middleware/EnsureAdmin.php` tartalma:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Ehhez a művelethez admin jogosultság szükséges.',
            ], 403);
        }

        return $next($request);
    }
}
```

### `TodoPolicy`

```bash
php artisan make:policy TodoPolicy --model=Todo
```

A `backend/app/Policies/TodoPolicy.php` tartalma:

```php
<?php

namespace App\Policies;

use App\Models\Todo;
use App\Models\User;

class TodoPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, Todo $todo): bool
    {
        return $user->id === $todo->user_id;
    }

    public function update(User $user, Todo $todo): bool
    {
        return $user->id === $todo->user_id;
    }

    public function delete(User $user, Todo $todo): bool
    {
        return $user->id === $todo->user_id;
    }
}
```

A `before()` engedi az adminnak a policyben meghatározott műveleteket. Normál felhasználónál a konkrét metódus dönt: csak saját feladat megtekintését, módosítását vagy törlését engedi. A listázás külön szűrést igényel a controllerben.

## 5.6 Middleware alias és JSON-hibák

A `backend/bootstrap/app.php` tartalma:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn ($request, $exception) =>
                $request->is('api/*') || $request->expectsJson()
        );
    })->create();
```

Az `admin` alias az útvonalakban használható rövid név. Az API-hibákat JSON-ként jelenítjük meg. A tisztán PHP-t tartalmazó fájl végére nem szükséges `?>`.

## 5.7 Controllerek létrehozása

Ha még nem léteznek, a backend konténerben futtasd:

```bash
php artisan make:controller Api/AuthController
php artisan make:controller Api/TodoController --api
php artisan make:controller Api/UserController --api
```

Az `--api` API-műveletekhez megfelelő alapvázat hoz létre. A fájlok az `app/Http/Controllers/Api` mappába kerülnek.

## 5.8 `AuthController`: hitelesítés

A `backend/app/Http/Controllers/Api/AuthController.php` tartalma:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'user',
        ]);

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('api')->plainTextToken,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Hibás e-mail-cím vagy jelszó.',
            ], 401);
        }

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('api')->plainTextToken,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return response()->json([
                'message' => 'Ehhez a kijelentkezéshez API-token szükséges.',
            ], 400);
        }

        $token->delete();

        return response()->json([
            'message' => 'Sikeres kijelentkezés.',
        ]);
    }
}
```

A `validate()` ellenőrzi a bemenetet; a `confirmed` szabályhoz a `password_confirmation` mező is szükséges. A regisztrált felhasználó mindig `user` szerepkört kap. A kijelentkezés az aktuális API-tokent törli, másik eszköz tokenjét nem. A token titok: ne tedd GitHubra vagy nyilvános helyre.

## 5.9 `TodoController`: feladatok CRUD műveletei

A `backend/app/Http/Controllers/Api/TodoController.php` tartalma:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Todo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TodoController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            $todos = DB::table('admin_todos_view')
                ->orderByDesc('id')
                ->get()
                ->map(function ($todo) {
                    $todo->completed = (bool) $todo->completed;

                    return $todo;
                });

            return response()->json($todos);
        }

        return response()->json(
            $user->todos()->latest('id')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'user_id' => 'sometimes|required|integer|exists:users,id',
        ]);

        $ownerId = $request->user()->isAdmin()
            ? ($data['user_id'] ?? $request->user()->id)
            : $request->user()->id;

        $todo = Todo::create([
            'user_id' => $ownerId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
        ]);

        return response()->json($todo, 201);
    }

    public function show(Todo $todo)
    {
        Gate::authorize('view', $todo);

        return response()->json($todo);
    }

    public function update(Request $request, Todo $todo)
    {
        Gate::authorize('update', $todo);

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'completed' => 'sometimes|boolean',
        ]);

        $todo->update($data);

        return response()->json($todo);
    }

    public function destroy(Todo $todo)
    {
        Gate::authorize('delete', $todo);

        $todo->delete();

        return response()->json([
            'message' => 'A feladat törölve.',
        ]);
    }
}
```

Az `index` listáz, a `store` létrehoz, a `show` megtekint, az `update` módosít, a `destroy` fizikailag töröl. Az admin az adatbázisnézetből látja az összes feladatot; a normál felhasználó csak a sajátjait. Az admin létrehozáskor tulajdonost is megadhat; normál felhasználónál a tulajdonos mindig ő maga. A `Gate::authorize()` a policy alapján ellenőrzi az egyes feladatokhoz való jogot.

Az adminnézet `DB::table()` segítségével érkezik, ezért a modell castja nem fut le; a `completed` mezőt itt külön alakítjuk logikai értékké.

## 5.10 `UserController`: admin felhasználókezelés

A `backend/app/Http/Controllers/Api/UserController.php` tartalma:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return response()->json(
            User::withCount('todos')->orderBy('id')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(['admin', 'user'])],
        ]);

        $user = User::create($data);

        return response()->json($user, 201);
    }

    public function show(User $user)
    {
        return response()->json($user->load('todos'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'password' => 'sometimes|nullable|string|min:8',
            'role' => ['sometimes', 'required', Rule::in(['admin', 'user'])],
        ]);

        if (array_key_exists('password', $data) && $data['password'] === null) {
            unset($data['password']);
        }

        if (
            $user->id === $request->user()->id
            && isset($data['role'])
            && $data['role'] !== 'admin'
        ) {
            return response()->json([
                'message' => 'A saját admin jogosultságodat nem veheted el.',
            ], 422);
        }

        $user->update($data);

        return response()->json($user);
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'Saját magadat nem törölheted.',
            ], 422);
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->json([
            'message' => 'A felhasználó és a kapcsolódó feladatai törölve.',
        ]);
    }
}
```

Ezeket a műveleteket az admin middleware védi. Az admin nem törölheti saját magát, és nem vonhatja meg a saját adminjogát. Másik felhasználó törlésekor a controller törli a Sanctum-tokeneket és a felhasználót; a kapcsolódó feladatokat a MySQL `ON DELETE CASCADE` szabálya törli. A művelet végleges, ezért a későbbi felületen megerősítést kell kérni. Ha másutt is létrehozunk felhasználótörlést, ott is gondoskodni kell a tokenekről.

## 5.11 API-útvonalak

A `backend/routes/api.php` tartalma:

```php
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TodoController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:10,1');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('todos', TodoController::class);

    Route::middleware('admin')
        ->prefix('admin')
        ->group(function () {
            Route::apiResource('users', UserController::class);
        });
});
```

A korábbi alapértelmezett `/api/user` útvonal helyett az `/api/me` adja vissza a bejelentkezett felhasználót. A `throttle:10,1` korlátozza a regisztrációs és bejelentkezési kéréseket; az `auth:sanctum` hitelesítést, az `admin` további adminjogot követel meg. Az `apiResource` létrehozza a CRUD útvonalakat.

| Metódus | URL | Művelet |
|---|---|---|
| POST | `/api/register`, `/api/login` | Regisztráció, bejelentkezés |
| GET, POST | `/api/me`, `/api/logout` | Saját adatok, kijelentkezés |
| GET, POST | `/api/todos` | Feladatlista, feladat létrehozása |
| GET, PUT/PATCH, DELETE | `/api/todos/{id}` | Feladat megtekintése, módosítása, törlése |
| GET, POST | `/api/admin/users` | Felhasználólista, létrehozás |
| GET, PUT/PATCH, DELETE | `/api/admin/users/{id}` | Felhasználó megtekintése, módosítása, törlése |

Ellenőrzés a backend konténerben:

```bash
php artisan route:list --path=api
```

## 5.12 A kézi próbához szükséges szerver

A backend konténerben indítsd el a fejlesztői szervert, ha még nem fut:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Ez a terminál foglalt marad; más Artisan-parancsokhoz nyiss új shellt. A gépről az API alapcíme `http://localhost:8001/api`: a `8001` a gép, a `8000` a konténer portja.

## 5.13 Gyors teszt a Postmannel

A Postman HTTP-kérések küldésére és a válaszok megtekintésére szolgál. Az alábbi próba a **helyi fejlesztői adatbázist módosítja**. Előfeltétel a futó Laravel szerver, a lefutott migrációk és seederek, valamint a kész controllerek és útvonalak. A böngészős Postmanhez a `localhost` eléréséhez Desktop Agent lehet szükséges.

### 5.13.1 Environment és collection

1. Az **Environments** résznél hozz létre egy `todo-app local` nevű environmentet.
2. Vegyél fel egy `url` változót a `http://localhost:8001/api` értékkel. A végére ne tegyél `/` jelet.
3. Mentsd el, majd a Postman környezetválasztójában válaszd ki ezt az environmentet.
4. Hozz létre egy `todo-app` nevű collectiont; ebbe mentheted a kéréseket.

A kérések címeiben ezután használhatod például a `{{url}}/login` és `{{url}}/todos` értékeket. A `{{token}}` változót a bejelentkezési script tölti fel.

A kérések **Headers** fülén add meg az `Accept: application/json` fejlécet. JSON törzsnél válaszd a **Body → raw → JSON** lehetőséget.

### 5.13.2 Bejelentkezés és automatikus tokenmentés

Készíts egy `POST` kérést a `{{url}}/login` címre, `No Auth` beállítással. A body:

```json
{
  "email": "admin@example.com",
  "password": "adminpw"
}
```

A kérés **Scripts → Post-response** részébe (régebbi Postmanben **Tests**) írd:

```javascript
pm.environment.unset("token");

pm.test("Sikeres bejelentkezés és token a válaszban", function () {
    pm.response.to.have.status(200);
    const response = pm.response.json();
    pm.expect(response.token).to.be.a("string").and.not.empty;
    pm.environment.set("token", response.token);
});
```

Mentsd a kérést, majd küldd el. Sikeres bejelentkezéskor `200 OK`, valamint `user` és `token` mező érkezik. A script előbb eltávolítja a korábbi tokent, majd siker esetén elmenti az újat; az eredmény a **Test Results** részen látható.

A token érzékeny adat. Postmanben is ügyelj a szinkronizálási és megosztási beállításokra, és exportáláskor se kerüljön a repóba. A második bejelentkezés ugyanebben az environmentben felülírja a `token` változót; ez önmagában nem törli a korábbi tokent a szerveren.

### 5.13.3 Védett kérések

Minden alábbi védett kérésnél az **Authorization → Bearer Token** mező értéke:

```text
{{token}}
```

A Postman az `Authorization: Bearer ...` fejlécbe helyettesíti be a tokent.

| Kérés | Várt eredmény |
|---|---|
| `GET {{url}}/me` | 200: a bejelentkezett admin adatai, jelszó nélkül. |
| `GET {{url}}/todos` | 200: minden feladat, az adminnézet tulajdonosi adataival. |
| `GET {{url}}/admin/users` | 200: felhasználók és a `todos_count` érték. |

### 5.13.4 Feladat létrehozása, módosítása, törlése

Admin tokennel küldj `POST {{url}}/todos` kérést:

```json
{
  "title": "Postman tesztfeladat",
  "description": "Ezt a feladatot a Postmanből hoztam létre."
}
```

A várt válasz `201 Created`. Mivel nem adtunk meg `user_id` mezőt, a feladat az adminhoz kerül. Jegyezd fel a válasz `id` értékét.

Ezután `PATCH {{url}}/todos/{id}` kérésben a `{id}` helyére a kapott azonosító kerüljön. A body:

```json
{
  "completed": true
}
```

A várt válasz `200 OK`, benne `completed: true`. Végül `DELETE {{url}}/todos/{id}` kéréssel töröld **csak az imént létrehozott** tesztfeladatot. A várt válasz `200 OK`; a fizikai törlés Adminerben is ellenőrizhető. Törlés előtt mindig ellenőrizd az azonosítót.

### 5.13.5 Normál felhasználó és kijelentkezés

A login kérés bodyjában használd:

```json
{
  "email": "user@example.com",
  "password": "userpw"
}
```

Siker után a script a normál felhasználó tokenjére cseréli a `{{token}}` értékét. Az ezzel küldött `GET {{url}}/admin/users` várt válasza **403**, a `GET {{url}}/todos` pedig **200**, csak a saját feladatokkal.

A `POST {{url}}/logout` kérés az aktuális tokent törli; utána ugyanazzal a tokennel a `GET {{url}}/me` várt válasza **401**. Az admin tokenje ettől még nem törlődik: ha azt is érvényteleníteni szeretnéd, azzal is küldj logout kérést.

### 5.13.6 Gyakoribb válaszok

| Kód | Jelentés |
|---|---|
| 200 | Sikeres kérés. |
| 201 | Új rekord jött létre. |
| 401 | Hiányzó vagy érvénytelen hitelesítés, illetve hibás bejelentkezés. |
| 403 | Nincs jogosultság a művelethez. |
| 404 | Az útvonal vagy rekord nem található. |
| 422 | Hibás bemeneti adat vagy üzleti szabályba ütköző kérés. |
| 429 | Túl sok kérés rövid idő alatt. |
| 500 | Szerveroldali hiba; vizsgáld meg a Laravel naplóját. |

Kapcsolódási hiba esetén ellenőrizd, hogy a szerver fut-e, és az environment `url` értékében `8001` szerepel-e.

### 5.13.7 Collection és environment exportálása

Az exportált segédanyagoknak létrehozhatsz egy `docs/postman/` mappát.

1. A `todo-app` collection mellett a **… → More → Export Collection** menüt válaszd, majd a **Store in a Git repo** lehetőséget. A Postman verziójától függően további beállításokat ajánlhat fel; mentsd a collection JSON-fájlját a `docs/postman/` mappába.
2. Az **Environments** részen a `todo-app local` environment **… → Export** lehetőségével külön exportáld a környezetet ugyanide. A menüpontok verziónként eltérhetnek.
3. Importáláshoz a Postman **Import** funkciójával válaszd ki a JSON-fájlt, majd a környezetet külön válaszd ki.

**Gitbe helyezés előtt mindkét exportot nyisd meg és ellenőrizd:** a `token` értéke legyen üres, érvényes token, valódi jelszó és más titok ne szerepeljen sem változóban, sem fejlécben, sem bodyban, sem scriptben. A `localhost` cím és a tudatosan nyilvános helyi gyakorló jelszavak a példában maradhatnak. A saját, tokeneket tartalmazó exportot ne commitold.

## 5.14 Commit és GitHub

A gép **helyi termináljában**, az `E:\dev\todo-app` mappában:

```powershell
git status
```

Nézd át a módosított fájlokat és a Postman-exportokat. Ha a jelen fejezet változásait és dokumentációját szeretnéd előkészíteni:

```powershell
git add backend docs
git status
git diff --cached
```

A parancs a `backend` és `docs` alatti egyéb, még nem követett változásokat is előkészítheti: a commit előtt ellenőrizd a teljes listát és diffet. A `.env`, a `vendor`, érvényes tokenek és valódi jelszavak nem kerülhetnek a commitba.

Ha minden rendben:

```powershell
git commit -m "Add authentication and CRUD API"
git push
```

## 5.15 Ellenőrző lista

- [ ] A `User` és `Todo` modellek és kapcsolataik elkészültek.
- [ ] A helyi seederek létrehozták a példafelhasználókat és -feladatokat.
- [ ] Az admin middleware és a `TodoPolicy` elkészült.
- [ ] A controllerek és API-útvonalak elkészültek; a `route:list` listázza őket.
- [ ] A Postman environmentben beállítottad az `url` változót, és a login script menti a tokent.
- [ ] Kipróbáltad a listázást, feladat létrehozását, módosítását, törlését és a jogosultsági ellenőrzést.
- [ ] A Postman-exportokat titkok szempontjából átnézted.
- [ ] A kívánt fájlokat commitoltad és feltöltötted.

Az automatikus tesztelés a következő fejezet témája. A tesztadatbázis létezése önmagában nem biztosítja, hogy a tesztek azt használják; a konfigurációt külön be kell állítani és ellenőrizni.
