# Database hardening (PostgreSQL on Hetzner)

Response to the BSI/CERT-Bund abuse notice: **PostgreSQL was reachable from the
public internet on port 5432.** A DBMS should never be directly internet-facing.

This doc covers: generating a new password, teaching Postgres about it,
locking Postgres down (`postgresql.conf` / `pg_hba.conf`), and firewalling the
host. Do them roughly in this order.

> Replace `<APP_IP>` with the single IP the app connects **from** (see
> "The real fix" at the bottom) and `<YOUR_ADMIN_IP>` with your own IP for
> psql/admin access.

---

## 1. Generate a new password

The old password was the `postgres` **superuser** password and has been exposed,
so rotate it. Any of these produce a strong random secret:

```bash
# 32 URL-safe chars, no shell-awkward characters
openssl rand -base64 24 | tr -d '/+=' | cut -c1-32

# or, if you have pwgen
pwgen -sy 32 1

# or Python
python3 -c "import secrets; print(secrets.token_urlsafe(24))"
```

Copy the result. You'll paste it in step 2 and into `env.php` / Heroku (step 5).

## 2. Teach Postgres the new password

Postgres stores a **hash**, not the plaintext — you just hand it the plaintext
via `ALTER ROLE` and it hashes it using `password_encryption` (set to
`scram-sha-256` by `sql/create_app_role.sql`). Connect as a superuser on the DB
host and run:

```sql
-- the new least-privilege application role (created by sql/create_app_role.sql)
ALTER ROLE boldview_app WITH PASSWORD 'PASTE-GENERATED-PASSWORD-HERE';

-- also rotate the exposed superuser password to a DIFFERENT new secret
ALTER ROLE postgres WITH PASSWORD 'A-DIFFERENT-GENERATED-PASSWORD';
```

Verify it hashed as SCRAM (not md5):

```sql
SELECT rolname, substring(rolpassword for 11) AS hash_prefix
FROM pg_authid WHERE rolname IN ('boldview_app','postgres');
-- hash_prefix should read: SCRAM-SHA-2
```

That's the whole "how does Postgres recognise it" step — no restart needed.

## 3. Create the least-privilege role (once)

Run the companion script as a superuser, then set its password (step 2):

```bash
psql -U postgres -d postgres -f sql/create_app_role.sql
```

The app then logs in as `boldview_app` (read-only, 5 tables) instead of
`postgres`.

## 4. Lock down Postgres itself

### postgresql.conf
```conf
# Only listen where you actually need to. If you adopt the static-IP proxy
# below, the proxy still reaches the box over its private/public NIC, so keep
# the address it connects to — but do NOT leave this as '*' with 5432 open.
listen_addresses = 'localhost,<the NIC the app IP reaches>'

ssl = on
ssl_cert_file = '/etc/postgresql/ssl/server.crt'
ssl_key_file  = '/etc/postgresql/ssl/server.key'
password_encryption = 'scram-sha-256'
```

### pg_hba.conf
Order matters (first match wins). Require SSL + SCRAM, and allow **only** known
IPs. `hostssl` refuses non-TLS connections outright:

Once you have a static egress IP for the app (the paid "real fix"), lock both
roles to specific IPs:

```conf
# TYPE      DATABASE  USER          ADDRESS            METHOD
hostssl     postgres  boldview_app  <APP_IP>/32        scram-sha-256
hostssl     all       postgres      <YOUR_ADMIN_IP>/32 scram-sha-256
local       all       all                              scram-sha-256
# (no catch-all 0.0.0.0/0 line — anything not matched above is rejected)
```

**Interim (free) version — see §7.** Until you have that static IP, `boldview_app`
still needs to accept Heroku's dynamic IPs, but `postgres` does not — so lock the
superuser to your admin IP and leave only the read-only role internet-reachable.

Apply without downtime:
```bash
sudo systemctl reload postgresql   # or: SELECT pg_reload_conf();
```

If you don't yet have a certificate, a self-signed one is enough to encrypt the
channel (use `sslmode=require` on the client, which the app now does):
```bash
sudo mkdir -p /etc/postgresql/ssl && cd /etc/postgresql/ssl
sudo openssl req -new -x509 -days 3650 -nodes -text \
  -out server.crt -keyout server.key -subj "/CN=boldview-db"
sudo chown postgres:postgres server.key && sudo chmod 600 server.key
```

## 5. Point the app at the new role + password

- **Local**: edit `env.php` → `POSTGRES_USERNAME=boldview_app`,
  `POSTGRES_PASSWORD=<new password>`, `POSTGRES_SSLMODE=require`.
- **Heroku**: `heroku config:set POSTGRES_USERNAME=boldview_app POSTGRES_PASSWORD='<new password>' POSTGRES_SSLMODE=require -a <app>`
  (config vars, never commit them). `pg.php` already reads these.

## 6. Firewall the host (this is what clears the BSI finding)

Even with pg_hba locked down, close the port at the network edge so scanners
can't even reach it. Two layers:

### Hetzner Cloud Firewall (preferred — outside the OS)
In the Hetzner console, attach a firewall to the server with an inbound rule
for TCP **5432** whose source is **only** `<APP_IP>/32` (and optionally
`<YOUR_ADMIN_IP>/32`). Everything else on 5432 is dropped. Keep 22/80/443 as
you need them.

### Host firewall (defence in depth, on the box)
```bash
sudo ufw default deny incoming
sudo ufw allow 22/tcp
sudo ufw allow from <APP_IP> to any port 5432 proto tcp
sudo ufw enable
```

### Verify from an outside machine
```bash
nc -zv 135.181.33.184 5432    # should now TIME OUT / be refused
```

---

## 7. Interim hardening — free, do this now

Fully closing port 5432 needs a **stable app egress IP to allowlist, which costs
money** (§ "The real fix"). But the highest-risk hole — the `postgres`
**superuser** being reachable from the open internet — can be closed for **£0**,
because since the app switched to `boldview_app`, the superuser no longer needs
to connect from Heroku at all. Only your admin machine does.

So split the two roles in `pg_hba.conf`: pin `postgres` to your admin IP, and
leave only the read-only `boldview_app` internet-reachable.

```conf
# TYPE      DATABASE  USER          ADDRESS             METHOD
hostssl     all       postgres      <YOUR_ADMIN_IP>/32  scram-sha-256   # superuser: you only
hostssl     postgres  boldview_app  0.0.0.0/0           scram-sha-256   # app: read-only, still internet-reachable
local       all       all                               scram-sha-256
# no catch-all — postgres from anywhere else is now rejected
```

```bash
sudo systemctl reload postgresql   # no downtime
```

Cost: nothing, no downtime. After this, even if the old (unrotated) superuser
password leaked, it's unusable from the internet — `postgres` only answers to
your admin IP. Rotating the superuser password (§2) then becomes optional, though
it's still a single free command.

**What this does and doesn't do:**
- ✅ Closes the superuser exposure (the part that could compromise the whole box).
- ✅ Residual internet-reachable surface = `boldview_app` only: read-only, 5
  tables, over TLS + SCRAM. Defensible for a research app.
- ⚠️ A BSI rescan will **still** see 5432 answering (for `boldview_app`), so the
  finding won't formally clear until the full firewall (§6 + real fix) is in
  place. Their notice said no response is required, so this is an acceptable
  trade for £0.

---

## The real fix (why it was exposed at all)

The DB faces the internet because the **Heroku app has no fixed egress IP**, so
there's no single `<APP_IP>` to allowlist. Pick one:

1. **Static-IP egress proxy on Heroku** (QuotaGuard Static / Fixie). Route DB
   traffic through it, then `<APP_IP>` = that proxy's IP. Keeps the current
   architecture; smallest change. **Recommended.**
2. **Migrate to Heroku Postgres** (managed; not exposed this way). Cleanest	
   long-term, but a data migration.
3. **WireGuard/VPN tunnel** between Heroku and Hetzner. Most control, most work.

Until one of these is in place you cannot fully close 5432 without breaking the
app, so the interim mitigation is: least-privilege role + SCRAM + enforced TLS +
strong rotated password + firewall allowing the narrowest source set you can.
