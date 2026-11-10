# 2. Docker környezet

Előfeltétel: Docker Desktop fut, VS Code-ban telepítve a **Container Tools** és a **Dev Containers** bővítmény. A mappa: `E:\dev\todo-app`.

## 2.1 Fájlok létrehozása

```
todo-app/
├─ docker-compose.yml
├─ docker/
│  ├─ backend/Dockerfile
│  ├─ frontend/Dockerfile
│  └─ mysql/init.sql
```

### `docker/backend/Dockerfile`
```dockerfile
FROM php:8.3-cli

RUN apt-get update && apt-get install -y git unzip libzip-dev \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
EXPOSE 8000
CMD ["tail", "-f", "/dev/null"]
```
- PHP 8.3 CLI, mert a Laravel saját szerverét (`artisan serve`) használjuk, nem Apache-ot.
- `pdo_mysql`: a MySQL-kapcsolat. `zip`, `unzip` és `git`: a Composer csomagtelepítéséhez.
- A `composer` binárist a hivatalos image-ből másoljuk be.
- A `tail -f /dev/null` életben tartja a konténert üres projekttel is, így a Laravelt már a konténerben tudod létrehozni.

### `docker/frontend/Dockerfile`
```dockerfile
FROM node:22-alpine
WORKDIR /app
EXPOSE 5173
CMD ["tail", "-f", "/dev/null"]
```
Ugyanaz az elv: a Vite szervert te indítod a konténer termináljából.

### `docker/mysql/init.sql`
```sql
CREATE DATABASE IF NOT EXISTS todo_db_test;
GRANT ALL PRIVILEGES ON todo_db_test.* TO 'todo'@'%';
FLUSH PRIVILEGES;
```
Létrehozza a teszt-adatbázist, hogy a tesztek ne írják felül a fejlesztői adatokat. **Csak a volume első létrehozásakor fut le.**

### `docker-compose.yml` (a gyökérben)
```yaml
services:
  db:
    image: mysql:8.0
    container_name: todo-db
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: todo_db
      MYSQL_USER: todo
      MYSQL_PASSWORD: todo_secret
      MYSQL_ROOT_PASSWORD: root_secret
    ports:
      - "3307:3306"
    volumes:
      - dbdata:/var/lib/mysql
      - ./docker/mysql/init.sql:/docker-entrypoint-initdb.d/init.sql:ro
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-proot_secret"]
      interval: 5s
      timeout: 5s
      retries: 20

  adminer:
    image: adminer
    container_name: todo-adminer
    restart: unless-stopped
    ports:
      - "8081:8080"
    depends_on:
      - db

  backend:
    build: ./docker/backend
    container_name: todo-backend
    restart: unless-stopped
    volumes:
      - ./backend:/var/www
    ports:
      - "8001:8000"
    depends_on:
      db:
        condition: service_healthy

  frontend:
    build: ./docker/frontend
    container_name: todo-frontend
    restart: unless-stopped
    volumes:
      - ./frontend:/app
    ports:
      - "5174:5173"
    environment:
      - CHOKIDAR_USEPOLLING=true
    depends_on:
      - backend

volumes:
  dbdata:
```

Magyarázat:
- **`db`**: MySQL 8.0. A `MYSQL_*` változók az első indításkor létrehozzák az adatbázist és a felhasználót. A hoston a `3307`-es port érhető el (a 3306 gyakran foglalt). A konténerek egymás között `db:3306`-on érik el. A `healthcheck` jelzi, mikor kész.
- **`adminer`**: webes adatbáziskezelő, `http://localhost:8081`. Mivel a `db` ugyanazon a hálózaton van, a szerver neve `db`.
- **`backend`**: a `./backend` mappa a konténer `/var/www`-jába van csatolva (bind mount), így a hoston szerkesztett fájlok azonnal látszanak. Megvárja, amíg a `db` healthy lesz.
- **`frontend`**: ugyanígy a `./frontend` az `/app`-ba. A `CHOKIDAR_USEPOLLING` a Windows bind mount fájlfigyelése miatt kell.
- **`dbdata`**: névvel ellátott volume, az adatbázis tartalma ebben marad meg a konténerek újraindítása után is.

### Miért van két adatbázis (`todo_db` és `todo_db_test`)?

| Adatbázis | Ki hozza létre | Mire való |
|---|---|---|
| `todo_db` | a compose `MYSQL_DATABASE` változója | a fejlesztői adatok, ezt használja az alkalmazás (`.env`: `DB_DATABASE=todo_db`) |
| `todo_db_test` | az `init.sql` | az automatikus tesztek (`php artisan test`) |

- **Miért külön?** A Laravel tesztek általában `RefreshDatabase` használatával minden futásnál újraépítik a táblákat, és törlik az adatokat. Ugyanabban az adatbázisban minden tesztfuttatás kitörölné a fejlesztés közben felvitt adataidat.
- **Miért az `init.sql` hozza létre?** A `MYSQL_DATABASE` csak egy adatbázist tud létrehozni, és ahhoz automatikusan jogot ad a `MYSQL_USER`-nek. A másodikat az `init.sql` hozza létre, a `root` futtatja, ezért a `todo` felhasználónak a `GRANT` sor adja meg a jogot. Enélkül a tesztek hozzáférési hibát kapnának.
- **Hol használjuk?** A backend tesztek fázisában a `phpunit.xml`-ben a `DB_DATABASE` értéke `todo_db_test` lesz. Addig üresen áll.
- Ha tesztelést nem tervezel, az `init.sql` kihagyható, és később is pótolható (a volume törlésével, vagy kézi `CREATE DATABASE` és `GRANT` paranccsal `root`-ként).

## 2.2 Indítás VS Code-ból (docker parancs nélkül)
1. Nyisd meg a `todo-app` mappát VS Code-ban.
2. Explorer: jobb klikk a `docker-compose.yml`-on, majd **Compose Up**. Az első alkalommal image-eket tölt le és épít, ez pár percig tarthat.
3. Bal oldalon a **Containers** nézetben látnod kell: `todo-db`, `todo-adminer`, `todo-backend`, `todo-frontend`, mind futva.

## 2.3 Ellenőrzés
- **Adminer:** `http://localhost:8081`. Belépés: **System** MySQL, **Server** `db`, **Username** `todo`, **Password** `todo_secret`, **Database** `todo_db`. Be kell jutnod, a tábla még üres. A `root` / `root_secret` párossal a `todo_db_test` adatbázist is látod.
- **Terminál a konténerekben:** jobb klikk a konténeren, majd **Attach Shell**.
  - backend: `php -v` és `composer --version`
  - frontend: `node -v` és `npm -v`
- A `todo_db_test` az Adminerben a bal felső adatbázis-választóban ellenőrizhető.

## 2.4 Hibaelhárítás
- **`init.sql` nem futott, nincs `todo_db_test`:** az `init.sql` csak az első indításkor fut. Pótlás: töröld a `dbdata` volume-ot (Containers/Volumes nézet, vagy Compose Down a volume törlésével), majd Compose Up. Ez **törli az adatbázis tartalmát**.
- **Port foglalt (`8000`, `8081`, `5173`, `3307`):** írd át a compose-ban a bal oldali számot, pl. `"8082:8080"`.
- **Adminer nem enged be:** a Server mezőbe `db` kerüljön, nem `localhost`.
- **`db` nem lesz healthy:** nézd meg a logot (jobb klikk a konténeren, View Logs). Az első indítás 20-30 másodperc.
- **Dockerfile módosítása után** újraépítés kell: jobb klikk a compose fájlon, **Compose Up** (build), vagy **Compose Restart**.

## 2.5 Commit
```powershell
git status
git add docker-compose.yml docker docs
git commit -m "Add Docker environment with Adminer"
git push
```
A `git status`-ban csak ezeknek a fájloknak kell szerepelniük.

## 2.6 Ellenőrző lista
- [ ] Mind a 4 konténer fut (`todo-db`, `todo-adminer`, `todo-backend`, `todo-frontend`).
- [ ] Az Adminerbe be tudsz lépni a `todo` felhasználóval.
- [ ] A `todo_db_test` adatbázis létezik.
- [ ] A backend konténerben működik a `php` és a `composer`, a frontendben a `node` és az `npm`.
- [ ] A változások commitolva és pusholva vannak.
