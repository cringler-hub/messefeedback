# Messefeedback Innotrans – Tages-Debriefing

Tägliches Feedback-Formular für Mitarbeiter auf der Innotrans, daraus
per Claude API EIN gemeinsames Team-Debriefing + Motivationsspruch für
den ganzen Tag, das morgens um 6:30 Uhr identisch an alle aktiven
Mitarbeiter per Mail rausgeht (kein individuelles Debriefing pro
Person). Um 17:45 Uhr gibt es eine Erinnerungsmail an alle, die noch
kein Feedback für den Tag abgegeben haben.

Reines PHP + MySQL, läuft auf IONOS Shared Hosting ohne Node/Build-Schritt.

## Aufbau

```
index.php              Formular (7 Tap-Fragen + Freitext)
submit.php              Formular-Handling, Speichern in DB
danke.php               Danke-Seite nach Absenden
assets/style.css        Styling
config.example.php      Vorlage – auf dem Server nach config.php kopieren
lib/                     PHP-Hilfsklassen (DB, Mail, Claude API, Fragenkatalog)
cron/reminder_18h.php   17:45-Erinnerungsmail
cron/debriefing_0630.php 06:30-Debriefing-Mail
db/schema.sql           Tabellen
db/seed_employees.sql   Mitarbeiterliste zum Import
```

`lib/`, `db/` und `config.php` sind per `.htaccess` gegen direkten
Web-Zugriff gesperrt.

## Einrichtung auf IONOS

1. **Datenbank anlegen**: Im IONOS-Kundenportal eine MySQL-Datenbank
   erstellen (falls noch nicht vorhanden) und Zugangsdaten (Host,
   DB-Name, User, Passwort) notieren.
2. **Schema importieren**: `db/schema.sql` und danach `db/seed_employees.sql`
   (vorher Mitarbeiterliste ergänzen) über phpMyAdmin oder
   `mysql -h HOST -u USER -p DBNAME < db/schema.sql` importieren.
   Falls die Datenbank schon vorher existierte (vor der Umstellung auf
   mehrfaches Feedback pro Tag): zusätzlich einmalig
   `db/migration_allow_multiple_submissions.sql` ausführen.
3. **Zielordner anlegen**: Einmalig per FTP-Client oder Datei-Manager
   einen leeren Ordner `/messefeedback/` im Webspace anlegen (die
   automatische Deployment-Action unten legt ihn nicht selbst an).
4. **Deployment einrichten** (empfohlen, automatisch bei jedem Push
   – siehe nächster Abschnitt) **oder** manuell: `config.example.php`
   nach `config.php` kopieren, ausfüllen und den kompletten Repo-Inhalt
   per SFTP nach `/messefeedback/` hochladen. `config.php` dabei
   **nicht** ins Git-Repo committen (steht in `.gitignore`).
5. **Testen**: Formular im Browser öffnen, einmal ausfüllen, danach in
   der DB prüfen, ob `feedback_submissions`/`feedback_answers` befüllt
   wurden.

## Automatisches Deployment (GitHub Actions)

`.github/workflows/deploy.yml` deployt bei jedem Push auf `main`
automatisch per SFTP nach `/messefeedback/`. Die `config.php` wird
dabei bei jedem Lauf frisch aus **verschlüsselten GitHub Secrets**
erzeugt – die Zugangsdaten landen dadurch nie im Git-Repo/der Historie,
nur auf dem GitHub-Runner (temporär) und auf dem Server.

**Einmalig einrichten**: Im Repo unter *Settings → Secrets and
variables → Actions → New repository secret* folgende Secrets anlegen:

| Secret               | Beispielwert                                    |
|----------------------|--------------------------------------------------|
| `SFTP_HOST`          | dein SFTP-Hostname (IONOS-Kundenportal)           |
| `SFTP_USERNAME`      | dein SFTP-Benutzername                            |
| `SFTP_PASSWORD`      | dein SFTP-Passwort                                |
| `DB_HOST`            | dein DB-Hostname (IONOS-Kundenportal)             |
| `DB_NAME`            | dein DB-Name                                      |
| `DB_USER`            | dein DB-Benutzername                              |
| `DB_PASS`            | dein DB-Passwort                                  |
| `MAIL_FROM_ADDRESS`  | `c.ringler@ringler-online.com`                    |
| `MAIL_FROM_NAME`     | `Innotrans Messefeedback`                         |
| `SMTP_HOST`          | `smtp.ionos.de`                                   |
| `SMTP_PORT`          | `587`                                             |
| `SMTP_USERNAME`      | `c.ringler@ringler-online.com`                    |
| `SMTP_PASSWORD`      | Passwort des E-Mail-Postfachs                     |
| `CLAUDE_API_KEY`     | dein Claude-API-Key                               |
| `CLAUDE_MODEL`       | `claude-haiku-4-5-20251001`                       |
| `APP_BASE_URL`       | `https://www.ringler-online.com/messefeedback`   |
| `CRON_SECRET`        | langer zufälliger String (siehe oben)             |

Danach reicht ein `git push` auf `main` – der Workflow lädt den
kompletten Stand automatisch hoch. Fortschritt/Fehler siehe Reiter
**Actions** im Repo.

Falls der SFTP-Account bei IONOS nur reines SFTP (kein SSH-Shell)
erlaubt, ist das über `sftp_only: true` in der Workflow-Datei bereits
berücksichtigt.

## Cronjobs einrichten

Die Zeitsteuerung läuft über **n8n** (Schedule Trigger + HTTP Request
Node), das täglich zur exakten Uhrzeit die token-geschützten Cron-URLs
aufruft. Ausprobiert wurden vorher sowohl IONOS' eigene Cronjob-Funktion
(kennt je nach Tarif nur grobe 6-Stunden-Zeitfenster wie "morgens 6-12
Uhr", keine exakte Uhrzeit) als auch GitHub Actions' `schedule`-Trigger
(in der Praxis um mehrere Stunden verspätet oder zur falschen Zeit
ausgelöst) – beides ungeeignet für "Punkt 6:30 Uhr".

**Einrichtung in n8n** (zwei Workflows):

1. Neuen Workflow anlegen, Node **"Schedule Trigger"** hinzufügen:
   Days-Interval, Uhrzeit `06:30`, Zeitzone `Europe/Berlin` (übernimmt
   automatisch Sommer-/Winterzeit).
2. Node **"HTTP Request"** anhängen (Methode `GET`), URL:
   ```
   https://www.ringler-online.com/messefeedback/cron/debriefing_0630.php?token=DEIN_CRON_SECRET
   ```
3. Workflow aktivieren.
4. Zweiten Workflow analog für `17:45` Uhr mit:
   ```
   https://www.ringler-online.com/messefeedback/cron/reminder_18h.php?token=DEIN_CRON_SECRET
   ```

`.github/workflows/cron-triggers.yml` ruft dieselben zwei Endpunkte nur
noch **manuell** auf (Actions-Tab → "Cron Triggers" → "Run workflow"),
nützlich zum Testen ohne auf die Uhrzeit zu warten.

Falls in IONOS bereits eigene, ungenaue Cronjobs für diese URLs
angelegt wurden, sollten die **deaktiviert/gelöscht** werden, damit es
nicht zu unerwünschten Erinnerungsmails zur falschen Tageszeit kommt
(das Debriefing selbst ist harmlos doppelt aufrufbar, die Erinnerung
zur falschen Zeit aber nicht).

Beide Skripte sind zusätzlich idempotent (Unique-Keys in
`reminder_log` bzw. `debriefings`): Ein versehentlicher Doppelaufruf
verschickt keine Mails doppelt.

Zum direkten Testen bleiben die URLs auch weiterhin manuell im
Browser aufrufbar:
```
https://www.ringler-online.com/messefeedback/cron/reminder_18h.php?token=DEIN_CRON_SECRET
https://www.ringler-online.com/messefeedback/cron/debriefing_0630.php?token=DEIN_CRON_SECRET
```

## Mitarbeiterliste pflegen

Mitarbeiter jederzeit in der Tabelle `employees` ergänzen/deaktivieren
(`active = 0` statt löschen, damit die Historie erhalten bleibt) –
kein Redeploy nötig.

## Mehrfaches Feedback pro Tag

Mitarbeiter können das Formular am selben Tag beliebig oft ausfüllen
(kein Unique-Key auf employee+Tag in `feedback_submissions`). Das
06:30-Debriefing fasst am nächsten Morgen automatisch **alle**
Einreichungen des Vortags je Mitarbeiter zu einem Team-Debriefing
zusammen.

Die 17:45-Erinnerung wird bei jedem Aufruf erneut an alle Mitarbeiter
ohne Feedback für den Tag verschickt – auch wenn an dem Tag schon
einmal erinnert wurde (`reminder_log` ist nur noch Verlauf, keine
Sperre mehr). Sobald jemand Feedback abgegeben hat, bekommt er/sie
keine weitere Erinnerung mehr an dem Tag.

Bei einer schon bestehenden Datenbank zusätzlich einmalig
`db/migration_allow_repeated_reminders.sql` ausführen (siehe
Einrichtung oben).

## E-Mail-Versand

`lib/mailer.php` verschickt Mails per echtem SMTP-Login über das
Postfach (PHP `mail()` ist auf IONOS Shared Hosting nicht zuverlässig
und wurde deshalb nicht verwendet). Zugangsdaten kommen aus den
`SMTP_*`-Secrets oben – `SMTP_USERNAME`/`SMTP_PASSWORD` sind Login und
Passwort des Postfachs, nicht die Absenderadresse.

## Fragenkatalog erweitern

Fragen sind zentral in `lib/questions.php` definiert und werden von
Formular, Validierung und KI-Prompt gemeinsam genutzt. Antworten
werden als Key/Value-Zeilen gespeichert (`feedback_answers`), neue
Fragen erfordern daher **keine** Schemaänderung – einfach einen neuen
Eintrag im Katalog ergänzen.
