# FAMJIS BANK: Instructor Notes

Not for students. This explains how the lab is built, how flags work, and how to
grade.

## How per-student flags work

Each flag is derived deterministically:

```
flag = "ICDFA{" + bug + "_" + first6( sha256( lower(STUDENT_ID) + "|" + bug + "|" + SECRET_SALT ) ) + "}"
```

`bug` is one of `idor`, `jwt`, `xss`, `upload`. The salt is set in
`docker-compose.yml` (`SECRET_SALT`). Because the student seeds their container
with `STUDENT_ID=<their matric>`, every student gets different flags, and a
copied flag will not match the submitter.

### Verifying a submission without running their container

Any language works. Example in PHP (or reuse `make_flag` from `web/src/lib.php`):

```php
function flag($sid, $bug, $salt) {
  $h = substr(hash('sha256', strtolower(trim($sid)).'|'.$bug.'|'.$salt), 0, 6);
  return "ICDFA{{$bug}_{$h}}";
}
// flag("2025/INT/11577", "idor", "icdfa_int406_salt_change_me");
```

Keep `SECRET_SALT` private. If it leaks, students could pre-compute flags for
any matric number. Change it before the cohort starts and it becomes the master
key for the whole batch.

## Where each flag lives (answer key)

| Bug | Path to the flag | Intended attack |
|-----|------------------|-----------------|
| IDOR | `note` field of account **1024** (`j.okafor`) | call `/api/user/1024` with any valid token |
| JWT | returned by `/api/admin` | forge a token with `role: admin` (alg:none or crack `secret123`) |
| XSS | `/vault.php`, admin session only | stored XSS steals the non-HttpOnly admin `session` cookie, replay it |
| upload | `/var/www/private/upload_flag.txt` | upload bypass to RCE, read the file |

## Design notes

- **Two auth systems on purpose.** The browser UI uses a PHP **session cookie**;
  the API uses a **JWT**. This keeps the JWT and XSS tasks independent: forging a
  JWT gives API admin (JWT flag) but not a browser session, and stealing the
  session cookie gives UI admin (`/vault.php`, XSS flag) but not the API. A
  student cannot solve one by doing the other.
- **The admin bot** (`adminbot/`) is a headless Chromium that logs in as admin
  and views `messages.php` every 30s, so stored payloads fire in an admin
  context. The admin password is in compose and in the bot env only; students
  never see it. Tune the cadence with `INTERVAL_MS`.
- **Session lifetime** is extended (`session.gc_maxlifetime=86400`) so a stolen
  session stays valid long enough to replay.
- **Upload execution** is enabled by `web/lab.conf`, which runs any file whose
  name contains `.php` / `.phtml` / `.php5` in the uploads dir (note: no
  trailing `$`), which is what makes `shell.php.jpg` execute.

## Grading

Suggested split (also in the student brief): flags 40%, findings 50%, report
quality 10%. The writeup carries most of the weight: a correct flag with no
reproducible steps should not pass that task. Require a screenshot per finding
showing the student's own id or flag in the request or response.

## Deployment reminder

Run this only in the isolated per-student container model. Do **not** host it on
`faisalauchan.ch` or any shared or public server: the upload task ends in remote
code execution by design, and the hosting account's terms almost certainly
forbid an intentionally vulnerable app. Keep `faisalauchan.ch` as the portal for
the brief, the lab download, and submissions only.
