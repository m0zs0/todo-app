# 7. React frontend

Ebben a fejezetben React és Vite segítségével frontend alkalmazást készítünk a Laravel API-hoz. A felhasználó a saját feladatait kezelheti; az admin az összes feladatot és a felhasználókat is. A parancsokat a megfelelő konténerben te adod ki.

## 7.1 A szolgáltatások címei

| Szolgáltatás | Konténeren belüli port | Cím a gépen |
|---|---:|---|
| Laravel API | 8000 | `http://localhost:8001/api` |
| React/Vite | 5173 | `http://localhost:5174` |
| Adminer | 8080 | `http://localhost:8081` |

A React-kód a böngészőben fut, ezért az API-t a gépről elérhető `localhost:8001` címen éri el, nem a Docker-szolgáltatás nevével. A Laravel szervert a **backend konténerben** kell indítani:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Ez fejlesztői szerver; a későbbi éles telepítéssel külön foglalkozunk.

## 7.2 Frontend terminál

A Docker konténerek futása mellett a VS Code Containers nézetében a `todo-frontend` konténerre jobb klikk, majd **Attach Shell**. Ellenőrzés:

```bash
pwd
```

A várt eredmény `/app`, amely a gépen az `E:\dev\todo-app\frontend` mappának felel meg. Az alábbi `npm` parancsokat a **frontend konténer termináljában** futtasd.

## 7.3 React projekt létrehozása

Az üres frontend mappában:

```bash
npm create vite@latest . -- --template react
```

- A `.` az aktuális `/app` mappába hozza létre a projektet.
- A `--template react` React JavaScript sablont választ.
- A `latest` miatt a telepítő kérdései és a választható verziók később eltérhetnek.

**Ha a telepítő lintert kérdez:** a „Which linter to use?” kérdésnél a nyílbillentyűvel válaszd az **ESLint** lehetőséget, majd nyomj Entert. Az Oxlint is linter, de ehhez az útmutatóhoz ESLintet választottunk.

**Ha azt kérdezi: „Install with npm and start now?”**, és a telepítést külön szeretnéd követni, válaszd a **No** lehetőséget, majd Enter. A függőségeket utána mi telepítjük, a szervert később külön indítjuk.

```bash
npm install
npm install axios react-router-dom
```

Az Axios az API-kérésekben, a React Router a frontend-útvonalak kezelésében segít. A `node_modules` letöltött függőségeket tartalmaz, ezért nem kerül Gitbe; a `package.json` és `package-lock.json` alapján a függőségek visszaállíthatók. Ha a telepítő Node-verzióhibát jelez, előbb a pontos üzenet alapján kell megvizsgálni a konténer verzióját.

## 7.4 API-cím: `.env`

A `frontend/.env` tartalma:

```dotenv
VITE_API_URL=http://localhost:8001/api
```

A megosztható `frontend/.env.example` fájlba ugyanaz a példaérték kerülhet. A helyi `.env` nem kerül Gitbe; az `.env.example` igen. A `VITE_` kezdetű változók értékei a böngészőhöz jutó kódban is elérhetők, ezért **jelszót, tokent vagy más titkot soha ne írj beléjük**.

Ha a Vite szerver **még nem fut**, nincs mit újraindítani: induláskor beolvassa a fájlt. Ha viszont futás közben hozod létre vagy módosítod a `.env` fájlt, indítsd újra a Vite szervert.

## 7.5 Fájlszerkezet

```text
frontend/
├─ .env
├─ .env.example
├─ package.json
├─ package-lock.json
└─ src/
   ├─ api/
   │  └─ axios.js
   ├─ App.jsx
   ├─ index.css
   └─ main.jsx
```

Ha a `src/api` mappa még nem létezik, a frontend konténerben létrehozható:

```bash
mkdir -p src/api
```

Az alábbi példák a fejezet elkészítésekor a projektben lévő forrásfájlokat mutatják. Ha már elkészítetted őket, nem kell őket újra létrehoznod.

## 7.6 Axios és API-kapcsolat

A `frontend/src/api/axios.js` teljes tartalma:

```javascript
import axios from 'axios';

const apiUrl = import.meta.env.VITE_API_URL;

if (!apiUrl) {
  throw new Error('Hiányzik a VITE_API_URL beállítás.');
}

const api = axios.create({
  baseURL: apiUrl,
  headers: {
    Accept: 'application/json',
  },
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('todo_token');

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

export default api;
```

Az `axios.create()` beállítja a közös alapcímet. Az interceptor kérésenként megnézi, van-e token a `localStorage` tárolóban, és ha igen, `Bearer` fejlécet ad hozzá. Hiányzó API-cím esetén a kód hibát jelez.

A böngésző `localStorage` tárolójához az oldalon futó JavaScript hozzáfér. Egy esetleges XSS-hiba veszélyeztetheti a tokent, ezért éles használat előtt a hitelesítés és a tokentárolás biztonságát külön át kell gondolni. Token soha ne kerüljön GitHubra.

## 7.7 A React alkalmazás

A `frontend/src/App.jsx` teljes tartalma:

```jsx
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
```

### A komponensek szerepe

| Komponens | Feladat |
|---|---|
| `AuthPage` | Bejelentkezés és regisztráció. |
| `TodoSection` | Feladatok listázása, létrehozása, módosítása és törlése. |
| `UserSection` | Admin felhasználókezelés. |
| `Dashboard` | Adatok betöltése és kijelentkezés. |
| `AppRoutes` | A hitelesítési állapot és az útvonalak kezelése. |
| `App` | A React Router elindítása. |

A Laravel sikeres bejelentkezéskor tokent ad; oldalfrissítéskor az alkalmazás az `/api/me` végponttól kéri le a felhasználót. Az admin saját törlés gombja le van tiltva, más felhasználó törlésekor figyelmeztetés jelenik meg a feladatok törléséről is.

A komponensek most tanulási céllal egy fájlban vannak; később külön fájlokba bonthatók. A felület ellenőrzése csak kényelmi réteg: a tényleges jogosultságellenőrzés a Laravel middleware-ében és policyjében történik.

## 7.8 Megjelenés és belépési pont

A `frontend/src/index.css` tartalma:

```css
:root {
  font-family: Arial, sans-serif;
  color: #222;
  background: #f5f5f5;
}

body {
  margin: 0;
}

main {
  max-width: 900px;
  margin: 2rem auto;
  padding: 1.5rem;
  background: white;
}

header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
}

section {
  margin-top: 2rem;
}

form {
  display: grid;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

label {
  display: grid;
  gap: 0.25rem;
}

input,
textarea,
select,
button {
  font: inherit;
  padding: 0.5rem;
}

button {
  margin-right: 0.5rem;
  cursor: pointer;
}

button:disabled {
  cursor: not-allowed;
}

li {
  margin-bottom: 1rem;
}

.error {
  color: #a00000;
}

.completed {
  text-decoration: line-through;
}
```

A `frontend/src/main.jsx` kapcsolja az `App` komponenst az oldal gyökéreleméhez:

```jsx
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import './index.css';
import App from './App.jsx';

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
```

## 7.9 CORS

Az `http://localhost:5174` frontend és az `http://localhost:8001` backend a böngésző számára külön origin. A CORS szabályozza, hogy a böngésző engedi-e a frontend kérését a másik originre.

A jelenlegi Laravel-telepítés alap CORS-beállítása az `api/*` útvonalakra kiterjed, ezért először külön módosítás nélkül próbáljuk ki. Ha a böngésző konzoljában CORS-hiba látható, a pontos hibaüzenet alapján vizsgáljuk meg. Éles használat előtt az engedélyezett origin beállításait külön át kell gondolni.

## 7.10 A frontend indítása

A **frontend konténerben**:

```bash
npm run dev -- --host 0.0.0.0 --port 5173 --strictPort
```

A `--host 0.0.0.0` a konténer hálózatán is elérhetővé teszi a szervert, a `--port 5173` a belső portot választja, a `--strictPort` pedig hibát jelez, ha az foglalt. A Docker a gép `5174` portjára kapcsolja.

Böngészőben: **http://localhost:5174**. A terminál futás közben foglalt; a szerver Ctrl+C-vel állítható le.

## 7.11 Kézi ellenőrzés böngészőben

Az 5. fejezet seederei által létrehozott **kizárólag helyi gyakorló** felhasználókkal:

| Felhasználó | E-mail | Jelszó |
|---|---|---|
| Normál felhasználó | `user@example.com` | `userpw` |
| Admin | `admin@example.com` | `adminpw` |

Ellenőrizd a normál felhasználónál, hogy csak a saját feladatai látszanak, és a létrehozás, szerkesztés, állapotváltás, törlés működik. Adminnal ellenőrizd a teljes feladatlistát, más nevében feladat létrehozását és a felhasználók kezelését.

Felhasználó törlése végleges, és a feladatait is törli. Kipróbáláshoz külön tesztfelhasználót hozz létre, és ellenőrizd a figyelmeztetést. Próbáld ki a regisztrációt, az oldalfrissítést és a kijelentkezést is. A regisztráció jelszava legalább nyolc karakteres legyen.

## 7.12 Hibaelhárítás

- **A frontend nem nyílik meg:** fut-e a frontend konténer és benne a Vite, valamint a gépen a `5174` portot használod-e?
- **Nem sikerül bejelentkezni:** fut-e a Laravel szerver, helyes-e a `VITE_API_URL`, létrejöttek-e a példafelhasználók? A `.env` változtatása után indítsd újra a futó Vite szervert.
- **A fájlmódosítások nem látszanak:** a Compose-ban szereplő `CHOKIDAR_USEPOLLING=true` segítheti a Windowsos fájlfigyelést. Ellenőrizd a Vite terminálját.
- **401:** hiányzó vagy érvénytelen hitelesítés. **403:** a felhasználónak nincs joga a kért művelethez.

## 7.13 Build és linter

A frontend konténerben:

```bash
npm run build
```

A build elkészíti a generált `frontend/dist` kimenetet; ez nem kerül Gitbe. A build sikeressége önmagában még nem jelenti azt, hogy az alkalmazás készen áll a távoli tárhelyen történő kiszolgálásra.

### Mire jó a linter?

A **linter** a forráskódot ellenőrzi, és figyelmeztethet problémás mintákra, például egy nem használt változóra vagy egyes hibás React Hook-használatokra. Ebben a projektben az **ESLintet** választottuk.

```bash
npm run lint
```

Az ESLint a konfigurált szabályok szerint kiírja a problémás fájlt, sort és szabályt. A linter nem ugyanaz, mint egy teszt: a linter a kód mintáit vizsgálja, a teszt egy működés elvárt eredményét ellenőrzi, a böngészős próba pedig a felület tényleges működését és megjelenését mutatja meg. A sikeres lint önmagában nem bizonyítja, hogy az alkalmazás hibátlan.

Ha a build vagy lint hibát jelez, a pontos kimenet alapján javítsd, majd futtasd újra.

## 7.14 Git és GitHub

Ha a böngészős próba, a build és a lint sikeres, a gép **helyi Command Prompt termináljában**, az `E:\dev\todo-app` mappából:

```cmd
git status
git add frontend docs\07_react_frontend.md
git status
git diff --cached
```

Ellenőrizd a teljes előkészített listát és diffet: a `.env`, a `node_modules`, a `dist`, a tokenek és a valódi titkok ne kerüljenek a commitba. Az `.env.example`, a `package.json` és a `package-lock.json` a projekt része.

```cmd
git commit -m "Add React frontend"
git push
```

## 7.15 Ellenőrző lista

- [ ] A frontend API-címe `http://localhost:8001/api`.
- [ ] A felület megnyílik a `http://localhost:5174` címen.
- [ ] A regisztráció és a bejelentkezés működik.
- [ ] A normál felhasználó csak a saját feladatait látja.
- [ ] Az admin a felhasználókat és valamennyi feladatot kezelheti.
- [ ] A törlés előtt a megfelelő megerősítés jelenik meg.
- [ ] A kijelentkezés működik.
- [ ] A build és a lint sikeres.
- [ ] A kívánt fájlokat átnéztem, commitoltam és feltöltöttem.
