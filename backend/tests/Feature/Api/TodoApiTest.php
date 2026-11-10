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