# Step 112 — Provision Ubuntu VPS

[← Deploy runbook](../README.md) · [Deployment](../../docs/operations/deployment.md)

Provision a **fresh Ubuntu 22.04+ LTS** VPS with Docker and a deploy user. **SSH hardening, firewall, and TLS are Step 113.** Application deploy is **Step 114**.

---

## 1. Choose a VPS

| Requirement | Minimum |
|-------------|---------|
| CPU | 2 vCPU |
| RAM | 4 GB |
| Disk | 40 GB SSD |
| OS | **Ubuntu 22.04 or 24.04 LTS** |
| Region | Close to users (e.g. same region as WhatsApp/Meta if relevant) |

Any provider works (DigitalOcean, Hetzner, OVH, AWS Lightsail, etc.) as long as you get root or sudo access on Ubuntu.

Create the VM with:

- Your **SSH public key** attached at creation (recommended), or
- Root password for first login (change immediately)

Note the **public IP** — e.g. `203.0.113.10`.

---

## 2. First login

From your laptop:

```bash
ssh root@203.0.113.10
# or: ssh ubuntu@203.0.113.10  (some clouds use ubuntu user — then sudo -i)
```

---

## 3. Bootstrap the server (automated)

On the VPS as **root**, either clone the repo first or copy the bootstrap script.

### Option A — clone repo, then bootstrap

```bash
apt-get update && apt-get install -y git
mkdir -p /var/www
git clone https://github.com/YOUR_ORG/Cashflow-Summary.git /var/www/cashflow-summary
cd /var/www/cashflow-summary
bash deploy/vps/bootstrap-server.sh
```

### Option B — curl script from your machine (before repo is on server)

Copy `deploy/vps/bootstrap-server.sh` to the server and run:

```bash
sudo bash bootstrap-server.sh
```

Environment overrides (optional):

```bash
DEPLOY_USER=deploy APP_DIR=/var/www/cashflow-summary sudo -E bash deploy/vps/bootstrap-server.sh
```

The script installs:

- Docker Engine + Compose plugin
- `git`, unattended security updates
- User **`deploy`** in the **`docker`** group
- Directory **`/var/www/cashflow-summary`** owned by `deploy`

---

## 4. Deploy user SSH access

As **root**, install your public key for the deploy user:

```bash
mkdir -p /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
nano /home/deploy/.ssh/authorized_keys   # paste your public key
chmod 600 /home/deploy/.ssh/authorized_keys
chown -R deploy:deploy /home/deploy/.ssh
```

Test from your laptop:

```bash
ssh deploy@203.0.113.10
```

---

## 5. Clone application (if not done in step 3)

As **`deploy`**:

```bash
cd /var/www/cashflow-summary
# if empty:
git clone https://github.com/YOUR_ORG/Cashflow-Summary.git .
git checkout main
```

---

## 6. Prepare production env file (secrets only on VPS)

As **`deploy`** in the repo root:

```bash
cp deploy/env/production.env.example deploy/env/production.env
nano deploy/env/production.env
```

Set at minimum:

- `APP_URL=https://your-domain.example` (domain configured in Step 113)
- `APP_KEY` — generate after first build (Step 114) or `php artisan key:generate` in container
- `DB_PASSWORD` / `DB_ROOT_PASSWORD` — strong unique values
- Remove or leave blank any `SEED_*` lines

**Do not** copy `deploy/env/docker.env` or your laptop `.env` to the VPS.

---

## 7. Verify Step 112

On the VPS, from repo root:

```bash
chmod +x deploy/vps/verify-provision.sh
bash deploy/vps/verify-provision.sh
```

Expected ending: **`Step 112 provision checks passed.`**

---

## 8. Step 112 checklist

- [ ] Ubuntu 22.04+ VPS created (≥2 vCPU, 4 GB RAM, 40 GB disk)
- [ ] `bootstrap-server.sh` completed without errors
- [ ] `deploy` user can SSH with key
- [ ] `docker` and `docker compose` work for `deploy`
- [ ] Repo cloned to `/var/www/cashflow-summary`
- [ ] `deploy/env/production.env` created (not committed)
- [ ] `verify-provision.sh` passes

---

## Environment isolation (reminder)

| Machine | Config file |
|---------|-------------|
| Your laptop (local dev) | `.env` |
| Your laptop (Docker) | `deploy/env/docker.env` |
| **VPS** | `deploy/env/production.env` |

---

## Next steps

| Step | Task |
|------|------|
| **113** | SSH hardening, UFW (22/80/443), TLS (Certbot/Caddy) |
| **114** | `./deploy/build.sh`, migrate, `compose-production.sh up`, smoke tests |

---

## Related

- [deployment.md](../../docs/operations/deployment.md) — VPS requirements
- [volumes.md](../volumes.md) — Docker data volumes
- [backup-monitoring.md](../../docs/operations/backup-monitoring.md) — Step 115+
