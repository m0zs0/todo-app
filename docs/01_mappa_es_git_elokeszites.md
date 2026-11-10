# 1. Mappa és Git előkészítés

## 1.1 Miért így?
- **A projekt ne a Google Drive-on legyen.** A Drive szinkronizálja a `vendor/`, `node_modules/` és `.git` fájlokat. Ez lassú, és hibákat okozhat. Nálad a hely: `E:\dev\todo-app`.
- **Egy repo (monorepo)**, benne a `backend/`, a `frontend/` és a `docker/`. Egyetlen GitHub-repo egyszerűbb, és a későbbi tárhelyre telepítést is könnyebb ráépíteni.
- **Titkok nem kerülnek GitHubra.** A `.env` ignorálva van, helyette a `.env.example` megy fel.

## 1.2 Mappaszerkezet és Git repo létrehozása
```powershell
mkdir E:\dev\todo-app; cd E:\dev\todo-app
mkdir backend, frontend, docker\backend, docker\frontend, docker\mysql
git init -b main
```

**Mit csinál a `git init -b main`?**
- Létrehozza a rejtett `.git` mappát (az üres repót), és a kezdő ág nevét `main`-re állítja (`-b` = `--initial-branch`).
- A sima `git init` az ág nevét a Git beállításából veszi. Ez lehet `master` is, a GitHub viszont `main`-t használ. Eltérő nevek esetén a `git push -u origin main` hibát ad (`src refspec main does not match any`).
- Emiatt a GitHub által javasolt `git branch -M main` parancs kimaradhat. Ha mégis lefuttatod, nem árt.
- Ha sima `git init`-et futtattál, javítás: `git branch -M main`.
- Tartós megoldás: `git config --global init.defaultBranch main`.
- Ellenőrzés: `git branch --show-current`. Az első commit előtt ez üres lehet, mert az ág még nem jön létre.

Eredmény:
```
todo-app/
├─ .git
├─ docker/
│  ├─ backend/
│  ├─ frontend/
│  └─ mysql/
├─ backend/    (üres, ide jön a Laravel)
└─ frontend/   (üres, ide jön a React)
```
Üres mappát a Git nem követ. Ezek addig nem kerülnek be a repóba, amíg nincs bennük fájl. Ez nem gond.

## 1.3 `.gitignore` (a `todo-app/` gyökerében)
```gitignore
# Titkok és helyi környezet
.env
.env.*
!.env.example

# Függőségek
backend/vendor/
frontend/node_modules/

# Build kimenet
frontend/dist/

# Laravel futás közbeni fájlok
backend/storage/*.key
backend/storage/logs/*.log
backend/bootstrap/cache/*.php
backend/public/hot
backend/public/storage

# Szerkesztő és rendszer
.idea/
.vscode/
.DS_Store
Thumbs.db
*.log
```
- Az `!.env.example` kivétel kell: a `.env` nem megy fel, a `.env.example` viszont igen.
- A Laravel és a Vite is generál saját `.gitignore`-t a `backend/`, illetve a `frontend/` mappában. Ezek kiegészítik a gyökérbelit.

## 1.4 `.gitattributes` (ugyanott)
```gitattributes
* text=auto eol=lf

*.png binary
*.jpg binary
*.jpeg binary
*.gif binary
*.ico binary
*.woff binary
*.woff2 binary
```
- Az `eol=lf` miatt a fájlok LF sorvéggel kerülnek a munkamappába és a repóba. A Linux alapú konténerben (shell-szkriptek, `artisan`) a CRLF `bad interpreter` jellegű hibát okozna.
- A `binary` sorok megakadályozzák, hogy a Git a képeket és betűtípusokat szövegként kezelje.

**Létrehozás:**
```powershell
New-Item .gitignore, .gitattributes -ItemType File
```
Ezután illeszd be a tartalmat VS Code-ban. Ügyelj arra, hogy a fájlnév elején a pont megmaradjon, és ne legyen `.txt` kiterjesztés.

## 1.5 Első commit
```powershell
git status
git add .gitignore .gitattributes
git commit -m "Add gitignore and gitattributes"
```
- A `git status` ekkor csak a két új fájlt mutatja.
- **Figyelmeztetés:** `warning: in the working copy of '.gitignore', CRLF will be replaced by LF the next time Git touches it`. Ez nem hiba. A fájlokat Windowson CRLF sorvéggel mentette az editor, a Git pedig az `eol=lf` szabály miatt LF-re cseréli őket commitkor. Ez a kívánt működés, nincs teendő.
- Ha el akarod kerülni: VS Code jobb alsó sarkában kattints a `CRLF` feliratra, válaszd az `LF`-et, és mentsd a fájlt. Alapértelmezetten is beállíthatod a `settings.json`-ban: `"files.eol": "\n"`.

## 1.6 Dokumentáció helye: `docs/` és `README.md`
```
todo-app/
├─ .git
├─ README.md          ← a GitHub ezt mutatja a repo főoldalán
├─ docs/              ← részletes útmutatók
│  ├─ 01_mappa_es_git_elokeszites.md
│  └─ ...
├─ docker/
├─ backend/
└─ frontend/
```
- A `docs/` a verziókövetett dokumentáció, így a kóddal együtt él. A GitHub rendereli a `.md` fájlokat, a relatív linkek is működnek.
- Fájlnevek: kisbetű, számozott előtag, ékezet és szóköz nélkül.
- A `README.md` legyen rövid: leírás, indítás, portok, linkek a `docs/` fájljaira.

Hozz létre egy induló `README.md`-t a gyökérben:
```markdown
# Todo App (Laravel + React, Docker)

Laravel REST API (Sanctum) + React (Vite) ToDo alkalmazás, Dockerben fejlesztve.

## Dokumentáció
- [1. Mappa és Git előkészítés](docs/01_mappa_es_git_elokeszites.md)
```
Ezt a további fejezetek elkészülésével bővítjük.

## 1.7 Feltöltés GitHubra

### 1.7.1 Üres repo létrehozása GitHubon
1. GitHub, jobb felül **+**, majd **New repository**.
2. **Repository name:** `todo-app`. **Visibility:** *Private* (ajánlott, amíg nem kész).
3. **Ne** pipáld be a *Add a README file*, *Add .gitignore* és *Choose a license* opciókat. Ezeket helyben már megírtuk, és ha a távoli repó nem üres, az első push ütközik.
4. **Create repository**. A következő oldalon megkapod a repo URL-jét: `https://github.com/<felhasznalo>/todo-app.git`.

### 1.7.2 Azonosítás
- Windowson a Git Credential Manager (a Git for Windows része) az első pushnál böngészős bejelentkezést kér. Jelentkezz be a GitHub-fiókoddal, és eltárolja a hitelesítést.
- Jelszóval nem lehet pusholni. Ha a `https` mellett tokent kérne: GitHub, Settings, Developer settings, *Personal access tokens*, jogosultság: `repo`.
- A commitok szerzőjéhez egyszer add meg a neved és az e-mailed (ha még nem tetted):
```powershell
git config --global user.name "Neved"
git config --global user.email "email@pelda.hu"
```

### 1.7.3 Commit és push
```powershell
cd E:\dev\todo-app
git add README.md docs
git commit -m "Add README and docs"
git remote add origin https://github.com/<felhasznalo>/todo-app.git
git remote -v
git push -u origin main
```
- `git remote add origin ...`: elnevezi a távoli repót `origin`-nek.
- `git remote -v`: ellenőrzés, hogy az URL helyes.
- `git push -u origin main`: feltölti a `main` ágat, és beállítja követésre. Ezután elég a `git push` és a `git pull`.
- Hibák: `remote origin already exists`, javítás: `git remote set-url origin <URL>`. `src refspec main does not match any`, javítás: nincs még commit, vagy az ág neve más (`git branch -M main`).

### 1.7.4 Ellenőrzés
- GitHubon frissítsd az oldalt: látszik a `README.md`, a `docs/`, a `.gitignore` és a `.gitattributes`.
- A `README.md` megjelenik a főoldalon.

## 1.8 Napi munkamenet (mintát innentől követjük)
Minden fázis végén, kis lépésekben:
```powershell
git status
git add .
git commit -m "Rövid, beszédes leírás"
git push
```
- Commitolás előtt mindig nézd meg a `git status` kimenetét: `.env`, `vendor/` és `node_modules/` nem szerepelhet benne.
- Ajánlott commit-pontok: Docker környezet kész, Laravel váz, migrációk, modellek és seeder, API, tesztek, React váz, nézetek.
- Fejlesztés `feature/...` ágon is mehet (`git switch -c feature/auth`), majd GitHubon Pull Request és merge a `main`-be. Egyedül dolgozva a közvetlenül `main`-re commitolás is elfogadható.

## 1.9 Ellenőrző lista
- [ ] A projekt nem a Google Drive-on van.
- [ ] `git branch --show-current` kiírja: `main`.
- [ ] A `.gitignore` és a `.gitattributes` commitolva van.
- [ ] A `README.md` és a `docs/` létezik és commitolva van.
- [ ] A GitHub-repo létezik (privát, üresen létrehozva).
- [ ] `git remote -v` a helyes URL-t mutatja.
- [ ] `git push -u origin main` sikeres, a fájlok látszanak GitHubon.
- [ ] `git status` tiszta (`nothing to commit, working tree clean`).
