import { useCallback, useEffect, useState } from 'react';
import { BrowserRouter, Navigate, Route, Routes, useNavigate } from 'react-router-dom';
import api from './api/axios';

function errorMessage(error) {
  const errors = error.response?.data?.errors;

  if (errors) {
    return Object.values(errors).flat().join(' ');
  }

  return error.response?.data?.message || 'A művelet nem sikerült. Ellenőrizd a kapcsolatot.';
}

function AuthPage({ onAuthenticated }) {
  const navigate = useNavigate();

  const [registering, setRegistering] = useState(false);
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  async function submit(event) {
    event.preventDefault();
    setError('');
    setBusy(true);

    try {
      const endpoint = registering ? '/register' : '/login';

      const body = registering
        ? {
            name,
            email,
            password,
            password_confirmation: passwordConfirmation,
          }
        : { email, password };

      const { data } = await api.post(endpoint, body);

      onAuthenticated(data);
      navigate('/', { replace: true });
    } catch (requestError) {
      setError(errorMessage(requestError));
    } finally {
      setBusy(false);
    }
  }

  function switchMode() {
    setError('');
    setRegistering(!registering);
  }

  return (
    <main>
      <h1>{registering ? 'Regisztráció' : 'Bejelentkezés'}</h1>

      <form onSubmit={submit}>
        {registering && (
          <label>
            Név
            <input
              value={name}
              onChange={(event) => setName(event.target.value)}
              maxLength={255}
              required
            />
          </label>
        )}

        <label>
          E-mail
          <input
            type="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            required
          />
        </label>

        <label>
          Jelszó
          <input
            type="password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            minLength={registering ? 8 : undefined}
            required
          />
        </label>

        {registering && (
          <label>
            Jelszó megerősítése
            <input
              type="password"
              value={passwordConfirmation}
              onChange={(event) => setPasswordConfirmation(event.target.value)}
              minLength={8}
              required
            />
          </label>
        )}

        {error && <p className="error" role="alert">{error}</p>}

        <button type="submit" disabled={busy}>
          {busy ? 'Folyamatban...' : registering ? 'Regisztráció' : 'Belépés'}
        </button>
      </form>

      <button type="button" onClick={switchMode} disabled={busy}>
        {registering ? 'Van már fiókom' : 'Új fiókot szeretnék'}
      </button>
    </main>
  );
}

function TodoSection({ user, todos, users, onChanged, onError }) {
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [ownerId, setOwnerId] = useState('');
  const [editingId, setEditingId] = useState(null);

  const isAdmin = user.role === 'admin';

  function resetForm() {
    setTitle('');
    setDescription('');
    setOwnerId('');
    setEditingId(null);
  }

  async function submit(event) {
    event.preventDefault();

    try {
      const body = {
        title,
        description: description || null,
      };

      if (editingId !== null) {
        await api.patch(`/todos/${editingId}`, body);
      } else {
        if (isAdmin && ownerId) {
          body.user_id = Number(ownerId);
        }

        await api.post('/todos', body);
      }

      resetForm();
      await onChanged();
    } catch (requestError) {
      onError(requestError);
    }
  }

  function editTodo(todo) {
    setEditingId(todo.id);
    setTitle(todo.title);
    setDescription(todo.description || '');
  }

  async function toggleTodo(todo) {
    try {
      await api.patch(`/todos/${todo.id}`, {
        completed: !todo.completed,
      });

      await onChanged();
    } catch (requestError) {
      onError(requestError);
    }
  }

  async function deleteTodo(todo) {
    const confirmed = window.confirm(
      `Biztosan véglegesen törlöd ezt a feladatot: ${todo.title}?`,
    );

    if (!confirmed) return;

    try {
      await api.delete(`/todos/${todo.id}`);

      if (editingId === todo.id) {
        resetForm();
      }

      await onChanged();
    } catch (requestError) {
      onError(requestError);
    }
  }

  return (
    <section>
      <h2>{isAdmin ? 'Minden feladat' : 'Saját feladatok'}</h2>

      <form onSubmit={submit}>
        <label>
          Cím
          <input
            value={title}
            onChange={(event) => setTitle(event.target.value)}
            maxLength={255}
            required
          />
        </label>

        <label>
          Leírás
          <textarea
            value={description}
            onChange={(event) => setDescription(event.target.value)}
          />
        </label>

        {isAdmin && editingId === null && (
          <label>
            Tulajdonos
            <select
              value={ownerId}
              onChange={(event) => setOwnerId(event.target.value)}
            >
              <option value="">Saját feladat</option>

              {users.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.name} ({item.email})
                </option>
              ))}
            </select>
          </label>
        )}

        <button type="submit">
          {editingId === null ? 'Feladat hozzáadása' : 'Módosítás mentése'}
        </button>

        {editingId !== null && (
          <button type="button" onClick={resetForm}>
            Mégse
          </button>
        )}
      </form>

      {todos.length === 0 ? (
        <p>Nincs megjeleníthető feladat.</p>
      ) : (
        <ul>
          {todos.map((todo) => (
            <li key={todo.id}>
              <strong className={todo.completed ? 'completed' : ''}>
                {todo.title}
              </strong>

              {todo.description && <p>{todo.description}</p>}

              {isAdmin && (
                <p>
                  Tulajdonos: {todo.user_name} ({todo.user_email})
                </p>
              )}

              <p>Állapot: {todo.completed ? 'Kész' : 'Nincs kész'}</p>

              <button type="button" onClick={() => toggleTodo(todo)}>
                {todo.completed ? 'Megnyitás' : 'Kész'}
              </button>

              <button type="button" onClick={() => editTodo(todo)}>
                Szerkesztés
              </button>

              <button type="button" onClick={() => deleteTodo(todo)}>
                Törlés
              </button>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

const emptyUser = {
  name: '',
  email: '',
  password: '',
  role: 'user',
};

function UserSection({ currentUser, users, onChanged, onError }) {
  const [form, setForm] = useState(emptyUser);
  const [editingId, setEditingId] = useState(null);

  function updateField(event) {
    setForm({
      ...form,
      [event.target.name]: event.target.value,
    });
  }

  function resetForm() {
    setForm(emptyUser);
    setEditingId(null);
  }

  function editUser(user) {
    setEditingId(user.id);
    setForm({
      name: user.name,
      email: user.email,
      password: '',
      role: user.role,
    });
  }

  async function submit(event) {
    event.preventDefault();

    try {
      const body = { ...form };

      if (editingId !== null) {
        if (!body.password) {
          delete body.password;
        }

        await api.patch(`/admin/users/${editingId}`, body);
      } else {
        await api.post('/admin/users', body);
      }

      resetForm();
      await onChanged();
    } catch (requestError) {
      onError(requestError);
    }
  }

  async function deleteUser(user) {
    const confirmed = window.confirm(
      `Biztosan véglegesen törlöd ${user.name} felhasználót? ` +
      'A hozzá tartozó feladatok is törlődnek.',
    );

    if (!confirmed) return;

    try {
      await api.delete(`/admin/users/${user.id}`);

      if (editingId === user.id) {
        resetForm();
      }

      await onChanged();
    } catch (requestError) {
      onError(requestError);
    }
  }

  return (
    <section>
      <h2>Felhasználók</h2>

      <form onSubmit={submit}>
        <label>
          Név
          <input
            name="name"
            value={form.name}
            onChange={updateField}
            maxLength={255}
            required
          />
        </label>

        <label>
          E-mail
          <input
            name="email"
            type="email"
            value={form.email}
            onChange={updateField}
            required
          />
        </label>

        <label>
          {editingId === null ? 'Jelszó' : 'Új jelszó (üresen nem változik)'}
          <input
            name="password"
            type="password"
            value={form.password}
            onChange={updateField}
            minLength={8}
            required={editingId === null}
          />
        </label>

        <label>
          Szerepkör
          <select name="role" value={form.role} onChange={updateField}>
            <option value="user">user</option>
            <option value="admin">admin</option>
          </select>
        </label>

        <button type="submit">
          {editingId === null ? 'Felhasználó létrehozása' : 'Módosítás mentése'}
        </button>

        {editingId !== null && (
          <button type="button" onClick={resetForm}>Mégse</button>
        )}
      </form>

      <ul>
        {users.map((user) => (
          <li key={user.id}>
            <strong>{user.name}</strong> — {user.email} — {user.role}
            {' '}— feladatok száma: {user.todos_count}

            <div>
              <button type="button" onClick={() => editUser(user)}>
                Szerkesztés
              </button>

              <button
                type="button"
                onClick={() => deleteUser(user)}
                disabled={user.id === currentUser.id}
              >
                Törlés
              </button>
            </div>
          </li>
        ))}
      </ul>
    </section>
  );
}

function Dashboard({ user, onSignedOut }) {
  const [todos, setTodos] = useState([]);
  const [users, setUsers] = useState([]);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    const todosResponse = await api.get('/todos');

    let nextUsers = [];

    if (user.role === 'admin') {
      const usersResponse = await api.get('/admin/users');
      nextUsers = usersResponse.data;
    }

    setTodos(todosResponse.data);
    setUsers(nextUsers);
    setError('');
  }, [user.role]);

  useEffect(() => {
    let active = true;

    async function initialLoad() {
      try {
        await loadData();
      } catch (requestError) {
        if (active) setError(errorMessage(requestError));
      } finally {
        if (active) setLoading(false);
      }
    }

    initialLoad();

    return () => {
      active = false;
    };
  }, [loadData]);

  async function refresh() {
    await loadData();
  }

  async function logout() {
    try {
      await api.post('/logout');
      onSignedOut();
    } catch (requestError) {
      if (requestError.response?.status === 401) {
        onSignedOut();
      } else {
        setError(
          'A kijelentkezés nem sikerült. Ellenőrizd a kapcsolatot, és próbáld újra.',
        );
      }
    }
  }

  function handleError(requestError) {
    if (requestError.response?.status === 401) {
      onSignedOut();
      return;
    }

    setError(errorMessage(requestError));
  }

  return (
    <main>
      <header>
        <h1>Üdv, {user.name}!</h1>
        <button type="button" onClick={logout}>Kijelentkezés</button>
      </header>

      {error && <p className="error" role="alert">{error}</p>}

      {loading ? (
        <p>Adatok betöltése...</p>
      ) : (
        <>
          {user.role === 'admin' && (
            <UserSection
              currentUser={user}
              users={users}
              onChanged={refresh}
              onError={handleError}
            />
          )}

          <TodoSection
            user={user}
            todos={todos}
            users={users}
            onChanged={refresh}
            onError={handleError}
          />
        </>
      )}
    </main>
  );
}

function AppRoutes() {
  const [user, setUser] = useState(null);
  const [checking, setChecking] = useState(
    () => Boolean(localStorage.getItem('todo_token')),
  );
  const [startupError, setStartupError] = useState('');
  const [retry, setRetry] = useState(0);

  const signOutLocally = useCallback(() => {
    localStorage.removeItem('todo_token');
    setUser(null);
    setChecking(false);
  }, []);

  useEffect(() => {
    const token = localStorage.getItem('todo_token');

    if (!token) return undefined;

    let active = true;

    async function loadCurrentUser() {
      try {
        const { data } = await api.get('/me');

        if (active) {
          setUser(data);
          setStartupError('');
        }
      } catch (requestError) {
        if (!active) return;

        if (requestError.response?.status === 401) {
          signOutLocally();
        } else {
          setStartupError(
            'A felhasználó ellenőrzése nem sikerült. Ellenőrizd a kapcsolatot.',
          );
        }
      } finally {
        if (active) setChecking(false);
      }
    }

    loadCurrentUser();

    return () => {
      active = false;
    };
  }, [retry, signOutLocally]);

  function authenticated(data) {
    localStorage.setItem('todo_token', data.token);
    setUser(data.user);
    setStartupError('');
  }

  if (checking) {
    return <main><p>Bejelentkezés ellenőrzése...</p></main>;
  }

  if (startupError) {
    return (
      <main>
        <p className="error" role="alert">{startupError}</p>
        <button
          type="button"
          onClick={() => {
            setChecking(true);
            setRetry((value) => value + 1);
          }}
        >
          Újrapróbálás
        </button>
      </main>
    );
  }

  return (
    <Routes>
      <Route
        path="/login"
        element={
          user
            ? <Navigate to="/" replace />
            : <AuthPage onAuthenticated={authenticated} />
        }
      />

      <Route
        path="/"
        element={
          user
            ? <Dashboard user={user} onSignedOut={signOutLocally} />
            : <Navigate to="/login" replace />
        }
      />

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}

export default function App() {
  return (
    <BrowserRouter>
      <AppRoutes />
    </BrowserRouter>
  );
}