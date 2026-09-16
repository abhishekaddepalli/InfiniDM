# 🚀 Coolify Deployment Guide — InfiniDM

This guide explains how to deploy **InfiniDM by Infiniforge Technologies** on your **Coolify** server directly from your GitHub repository.

---

## 📋 Prerequisites

1. A running **Coolify** instance (v4+).
2. GitHub App or GitHub Personal Access Token connected in your Coolify dashboard.
3. Access to GitHub Repository: [`https://github.com/abhishekaddepalli/InfiniDM`](https://github.com/abhishekaddepalli/InfiniDM)

---

## ⚙️ Deployment Options in Coolify

InfiniDM ships with a pre-configured **`docker-compose.yml`** that sets up:
- **`web`**: Laravel 12 Web Dashboard & API (PHP 8.3 FPM + Nginx + Queue Worker)
- **`node-engine`**: High-performance Node.js Standalone Flow Engine (Port 3100)
- **`db`**: MySQL 8.0 Database Server

---

## 🛠️ Step-by-Step Installation on Coolify

### 1. Add New Project in Coolify
1. Log into your **Coolify Dashboard**.
2. Go to **Projects** $\rightarrow$ Click **+ Add**.
3. Select your target **Environment** (e.g. `production`).

---

### 2. Connect GitHub Repository
1. Click **+ Add New Resource**.
2. Select **Docker Compose** (or **Public / Private Repository** $\rightarrow$ **Docker Compose**).
3. Select your **GitHub Source**.
4. Choose Repository: `abhishekaddepalli/InfiniDM`
5. Set Branch: `main`
6. Build Pack: Select **Docker Compose**.

---

### 3. Configure Environment Variables in Coolify

In the **Environment Variables** section of your Coolify resource, add the following variables:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain-or-coolify-fqdn.com
APP_KEY=base64:YOUR_GENERATED_LARAVEL_APP_KEY

DB_DATABASE=infinidm
DB_USERNAME=infinidm_user
DB_PASSWORD=YOUR_SECURE_DB_PASSWORD
DB_ROOT_PASSWORD=YOUR_SECURE_ROOT_PASSWORD

NODE_WEBHOOK_TOKEN=YOUR_SECRET_NODE_SHARED_TOKEN
```

> 💡 *Note*: You can generate a random 32-character string for `NODE_WEBHOOK_TOKEN` or use `openssl rand -hex 16`.

---

### 4. Deploy Application
1. Click **Deploy**.
2. Coolify will pull the code from GitHub, build the container images using `Dockerfile.web` and `Dockerfile.node`, and spin up the services.
3. Once deployed, navigate to:
   ```text
   https://your-domain-or-coolify-fqdn.com/install
   ```
4. Complete the web wizard to finalize database seeding and administrator account creation.

---

## 🔒 Domains & SSL Configuration in Coolify

Under the **FQDN / Domains** setting for the `web` service in Coolify:
- Enter your production domain name (e.g. `https://infinidm.yourdomain.com`).
- Coolify's built-in Traefik / Caddy proxy will automatically issue a **free Let's Encrypt SSL certificate**.

---

## 🔁 Webhook Routing for Instagram

In your Meta Developer Console, set your Instagram Webhook URL to:
```text
https://infinidm.yourdomain.com/api/instagram-flow/inbound
```
Coolify routes webhooks seamlessly through Traefik / Nginx directly to the Node Engine.
