# VeloLedger — Multi-Währungs-Doppelbuchungs-Ledger-API

VeloLedger ist eine hochperformante, sichere Multi-Währungs-Doppelbuchungs-Anwendung. Sie verfügt über ein modernes, responsives Single-Page-Dashboard zur Wallet-Verwaltung, zur Erstellung von doppelten Ledger-Buchungen und zum asynchronen PDF-Kontoauszugs-Versand per E-Mail im Hintergrund.

---

## Technischer Stack
* **Backend-Framework:** PHP 8.4 & Symfony 7
* **Webserver:** FrankenPHP (Caddy-basiert, auf maximale Performance optimiert)
* **Datenbank:** PostgreSQL 16 mit **PgBouncer** Connection Pooling
* **Cache & Warteschlange:** Redis 7 (speichert Aufgaben für den Hintergrund-Worker)
* **PDF-Erstellung:** Dompdf (HTML-zu-PDF-Rendering für Finanzberichte)
* **Lokaler E-Mail-Catcher:** Mailpit (fängt in der Entwicklung alle E-Mails ab)
* **CI/CD:** GitHub Actions (Code-Stil, statische Analyse, Tests, Sicherheits-Audit)

---

## Schnellstart-Anleitung (Quick Start)

Geben Sie die folgenden Befehle nacheinander in Ihr Terminal ein, um das Projekt lokal auszuführen.

### 1. Docker-Container bauen und starten
Startet alle Microservices (Webserver, Datenbank, PgBouncer, Redis, Background-Worker, Mail-Catcher) im Hintergrund:
```bash
docker compose up -d --build
```

### 2. Composer-Abhängigkeiten installieren
Lädt die PHP-Bibliotheken im PHP-Container herunter:
```bash
docker compose exec php composer install
```

### 3. JWT-Sicherheitsschlüssel generieren
Erstellt das private und öffentliche Schlüsselpaar für die JWT-Authentifizierung:
```bash
docker compose exec php bin/console lexik:jwt:generate-keypair --skip-if-exists
```

### 4. Datenbank erstellen und Tabellen migrieren
Legt die Datenbank an und wendet die Migrationen an, um das Datenbankschema aufzubauen:
```bash
# Datenbank erstellen (falls nicht vorhanden)
docker compose exec php bin/console doctrine:database:create --if-not-exists

# Migrationen ausführen
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
```

> **Hinweis:** Falls noch keine Migrationen für die neuesten Schema-Änderungen generiert wurden, können Sie das Schema auch direkt synchronisieren:
> ```bash
> docker compose exec php bin/console doctrine:schema:update --force
> ```

### 5. Anwendungs-Cache leeren
Stellt sicher, dass alle Konfigurationen frisch geladen werden:
```bash
docker compose exec php bin/console cache:clear
```

### 6. Anwendung im Browser öffnen
* **Frontend Web-Dashboard:** [http://localhost:8000](http://localhost:8000)
* **Lokales Mailpit-Dashboard:** [http://localhost:8025](http://localhost:8025)

---

## Test- & Feature-Checkliste

### 1. Registrierung & Anmeldung (Auth)
Öffnen Sie [http://localhost:8000](http://localhost:8000), wechseln Sie das Formular auf **Registrieren**, erstellen Sie ein Konto mit einer beliebigen E-Mail-Adresse und melden Sie sich an. Das JWT-Token wird im Hintergrund automatisch generiert und regelmäßig rotiert.

### 2. Test-Wallets erstellen (1-Klick-Testdaten)
Klicken Sie unten links auf die Schaltfläche **Test-Wallets**, um sofort Testdaten zu generieren:
* Es werden **21 vordefinierte Wallets** (unter dem Kunden-ID-Präfix `test_liq_`) mit realistischen Salden in **EUR**, **USD** und **GBP** angelegt.
* Nach dem Erstellen wird der Button **Test-Wallets** gesperrt und **Test-Wallets löschen** wird aktiv.
* Auch nach dem Ab- und Anmelden bleiben die Wallets in der Liste sichtbar und können mit dem Button **Test-Wallets löschen** rückstandslos aus der Datenbank entfernt werden.

### 3. Ausgeglichenes Buchungsformular (Doppelte Buchführung)
Testen Sie die Transaktionserstellung:
* Die Summe aller **Soll-Buchungen (DR)** muss exakt der Summe aller **Haben-Buchungen (CR)** entsprechen.
* Die Auswahl verschiedener Währungen in einer Buchung löst einen **Währungsfehler** aus.
* Der Button **Transaktion buchen** wird erst freigeschaltet, wenn alle Zeilen dieselbe Währung nutzen und die Differenz exakt `0.00` beträgt.

### 4. PDF-Erstellung & E-Mail-Warteschlange
* Wählen Sie links ein Wallet aus und klicken Sie rechts auf **Kontoauszug PDF erstellen**.
* Geben Sie eine E-Mail-Adresse ein.
* Die PDF wird mit **Dompdf** als professionelles Finanzdokument erstellt und als Hintergrundaufgabe geplant.
* Der **Worker-Container** verarbeitet die Aufgabe asynchron und sendet den PDF-Anhang per E-Mail.

---

## Wartungsbefehle

### Abgelaufene Refresh-Tokens bereinigen
Im Laufe der Zeit sammeln sich widerrufene und abgelaufene Refresh-Tokens in der Datenbank an. Bereinigen Sie diese mit:
```bash
docker compose exec php bin/console app:tokens:purge
```

### Hintergrund-Worker neu starten
Wenn Sie Umgebungsvariablen ändern oder den Handler-Code aktualisieren, starten Sie die Worker neu, damit sie die neue Konfiguration laden:
```bash
docker compose exec php bin/console messenger:stop-workers
```

---

## CI/CD-Pipeline (GitHub Actions)

Das Projekt enthält einen GitHub-Actions-Workflow unter `.github/workflows/ci.yml`, der automatisch bei jedem Push oder Pull-Request auf `main` und `develop` ausgeführt wird. Die Pipeline besteht aus 4 Stufen:

| Stufe | Werkzeug | Was wird geprüft? |
|---|---|---|
| 🎨 **Code-Stil** | PHP CS Fixer | PSR-12-Konformität, Strict Types, Short-Array-Syntax |
| 🔍 **Statische Analyse** | PHPStan (Level 9) | Typsicherheit, Doctrine-/Symfony-Integration |
| 🧪 **Tests** | PHPUnit | Unit- & Funktionstests (SQLite) |
| 🔒 **Sicherheits-Audit** | `composer audit` | Bekannte CVEs in Abhängigkeiten |

Es ist keine zusätzliche Einrichtung erforderlich — die Pipeline wird automatisch aktiviert, sobald das Repository auf GitHub gepusht wird.

---

## E-Mail-System & Konfigurationen

### Entwicklungsumgebung (Mailpit Catcher)
Standardmäßig ist die Variable `MAILER_DSN` in der `docker-compose.yaml` auf `smtp://mailer:1025` gesetzt.
* E-Mails verlassen die lokale Umgebung nicht.
* Alle gesendeten Kontoauszüge landen im lokalen Postfach von **Mailpit** und können unter [http://localhost:8025](http://localhost:8025) eingesehen werden.

### Reale Umgebung (Echte E-Mails senden)
Wenn Sie PDFs an reale E-Mail-Adressen senden möchten:

1. Erstellen Sie eine Datei namens `.env.local` im Stammverzeichnis des Projekts.
2. Überschreiben Sie die Variable `MAILER_DSN` mit Ihren echten SMTP-Zugangsdaten:

   **Für Gmail (benötigt App-Passwort):**
   ```ini
   MAILER_DSN=gmail://DEINE_EMAIL@gmail.com:DEIN_GOOGLE_APP_PASSWORT@default
   ```
   *(Aktivieren Sie die Bestätigung in zwei Schritten in Ihrem Google-Konto und generieren Sie ein 16-stelliges App-Passwort).*

   **Für Brevo (ehemals Sendinblue):**
   ```ini
   MAILER_DSN=smtp://DEINE_BREVO_EMAIL:DEIN_BREVO_SMTP_KEY@smtp-relay.brevo.com:587
   ```

   **Für allgemeine Domain-SMTP-Server:**
   ```ini
   MAILER_DSN=smtp://username:password@smtp.deinedomain.de:587
   ```

3. **Docker-Container neu starten (WICHTIG):**
   Da der E-Mail-Worker dauerhaft im Hintergrund als CLI-Prozess läuft, merkt er sich die Umgebungsvariablen beim Start. Nach dem Erstellen/Ändern der `.env.local` müssen die Container zwingend neu gestartet werden:
   ```bash
   docker compose down
   # Neu starten
   docker compose up -d
   # Cache zur Sicherheit leeren
   docker compose exec php bin/console cache:clear
   ```
