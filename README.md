# 🚀 InfiniDM — Instagram Automation & Direct Message Marketing Suite

<p align="center">
  <img src="public/extensions/instagram/logo.png" alt="InfiniDM Banner" width="120" onError="this.style.display='none'"/>
</p>

<p align="center">
  <b>InfiniDM</b> is an enterprise-grade, high-performance Instagram Direct Message (DM) automation, visual flow builder, and lead generation engine.
</p>

<p align="center">
  <i>Developed and Maintained by <b>Infiniforge Technologies</b></i>
</p>

---

## 🌟 Key Features

- ⚡ **Visual Flow Builder**: Drag-and-drop DAG execution engine for automated conversational DM funnels.
- 💬 **Keyword & Comment Auto-Responders**: Instant replies to DM keywords, post comments, and story mentions.
- 👥 **Lead Capture & Contact Management**: Auto-collect emails, phone numbers, and custom lead attributes directly in chat.
- 📢 **Broadcast Messaging**: Send bulk marketing messages and sequences to segmented contacts.
- 🗓️ **Scheduled Posts & Reels Autopilot**: Schedule content publishing automatically.
- 📊 **Real-time Analytics**: Track conversation volume, conversion rates, and engagement performance.
- ⚡ **Dual Architecture Engine**: Built on **Laravel 12** (Web Dashboard & API) + **Node.js** (High-concurrency Meta webhook & flow execution runtime).

---

## 🏗️ System Architecture

```
                       +-----------------------------------+
                       |        Meta Instagram API         |
                       +-----------------------------------+
                                         |
                                         v (Webhooks)
+---------------------------------------------------------------------------------+
|                                  InfiniDM Server                                |
|                                                                                 |
|  +-----------------------------------+    +----------------------------------+  |
|  |     Laravel 12 Control Panel      |    |      Node.js Flow Engine         |  |
|  |     (PHP 8.2+ / MySQL / Nginx)    |<==>|      (Express / Port 3100)       |  |
|  |     Web UI, Admin & DB Models     |    |      Webhook & DAG Processing    |  |
|  +-----------------------------------+    +----------------------------------+  |
+---------------------------------------------------------------------------------+
```

---

## 📋 System Requirements

| Component | Minimum Requirement | Recommended |
| :--- | :--- | :--- |
| **OS** | Ubuntu 22.04 LTS / 24.04 LTS | Ubuntu 24.04 LTS |
| **PHP** | PHP 8.2+ | PHP 8.3 |
| **Node.js** | Node.js v20.x+ | Node.js v22.x LTS |
| **Database** | MySQL 8.0+ / MariaDB 10.4+ | MySQL 8.0 |
| **Web Server** | Nginx | Nginx + Certbot SSL |
| **Process Manager**| PM2 (for Node) & Systemd (for Laravel) | PM2 & Systemd |

---

## 🛠️ Local Development Quickstart

### 1. Clone Repository
```bash
git clone https://github.com/Infiniforge-Technologies/InfiniDM.git
cd InfiniDM
```

### 2. Install Dependencies
```bash
# Install PHP dependencies
composer install

# Install Front-end & Node engine dependencies
npm install
cd node && npm install && cd ..
```

### 3. Environment Setup
```bash
cp .env.example .env
cp node/.env.example node/.env
```

### 4. Start Local Development Servers
```bash
# Terminal 1: Start Laravel App Server
php artisan serve --port=8000

# Terminal 2: Start Node Standalone Engine
cd node && node index.js
```

### 5. Launch Setup Wizard
Open your browser and navigate to:
```text
http://localhost:8000/install
```

---

## 🌐 Production VPS Deployment Guide (Ubuntu 22.04 / 24.04 LTS)

Follow these step-by-step commands to deploy **InfiniDM by Infiniforge Technologies** on a fresh VPS.

---

### Step 1: System Update & Package Installation

```bash
# Update Ubuntu system repositories
sudo apt update && sudo apt upgrade -y

# Install essential software
sudo apt install -y software-properties-common curl git unzip zip nginx ufw certbot python3-certbot-nginx

# Add PHP 8.3 repository
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP 8.3 & required extensions
sudo apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring \
    php8.3-xml php8.3-curl php8.3-gd php8.3-zip php8.3-bcmath php8.3-sqlite3

# Install Composer globally
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js (v20 LTS) & PM2 globally
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
sudo npm install -g pm2
```

---

### Step 2: Install & Configure MySQL Database

```bash
# Install MySQL Server
sudo apt install -y mysql-server

# Secure MySQL installation
sudo mysql_secure_installation

# Create Database and User
sudo mysql -u root -p -e "
CREATE DATABASE infinidm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'infinidm_user'@'localhost' IDENTIFIED BY 'YourStrongPasswordHere';
GRANT ALL PRIVILEGES ON infinidm.* TO 'infinidm_user'@'localhost';
FLUSH PRIVILEGES;
"
```

---

### Step 3: Clone & Configure Application

```bash
# Navigate to web root
cd /var/www

# Clone InfiniDM repository
sudo git clone https://github.com/Infiniforge-Technologies/InfiniDM.git infinidm
cd infinidm

# Set correct directory ownership
sudo chown -R www-data:www-data /var/www/infinidm
sudo chmod -R 775 /var/www/infinidm/storage /var/www/infinidm/bootstrap/cache

# Install PHP production dependencies
sudo -u www-data composer install --no-dev --optimize-autoloader

# Install Node engine dependencies
cd node
sudo -u www-data npm install --omit=dev
cd ..

# Copy and configure production .env
sudo -u www-data cp .env.example .env
sudo -u www-data cp node/.env.example node/.env
```

---

### Step 4: Run Application Installer / Setup

```bash
# Generate Laravel application key
sudo -u www-data php artisan key:generate --force

# Run Web Installer at http://yourdomain.com/install
# Or complete via CLI:
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan db:seed --force
sudo -u www-data php artisan storage:link
```

---

### Step 5: Start Node.js Engine with PM2

```bash
cd /var/www/infinidm/node

# Start Node engine with PM2
sudo -u www-data pm2 start index.js --name "infinidm-node"

# Enable PM2 startup on system boot
sudo pm2 startup systemd -u www-data --hp /var/www
sudo pm2 save
```

---

### Step 6: Configure Nginx Web Server & SSL

Create Nginx site configuration:
```bash
sudo nano /etc/nginx/sites-available/infinidm
```

Paste the following Nginx server block (replace `yourdomain.com` with your real domain):

```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/infinidm/public;

    index index.php index.html;
    charset utf-8;

    # Dynamic Routing to Laravel
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Proxy Webhooks to Node Engine
    location /api/instagram-flow/ {
        proxy_pass http://127.0.0.1:3100;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }

    # FastCGI PHP Execution
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

Enable site and restart Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/infinidm /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

Obtain Let's Encrypt Free SSL Certificate:
```bash
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

---

### Step 7: Set up Laravel Queue Worker (Systemd)

Create systemd service for queue worker:
```bash
sudo nano /etc/systemd/system/infinidm-worker.service
```

Paste:
```ini
[Unit]
Description=InfiniDM Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/infinidm
ExecStart=/usr/bin/php /var/www/infinidm/artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Enable and start queue worker:
```bash
sudo systemctl daemon-reload
sudo systemctl enable --now infinidm-worker
```

---

## 🚀 Push to GitHub (Infiniforge Technologies)

Follow these terminal commands to initialize Git and push **InfiniDM** to GitHub:

```bash
# Initialize git repository
git init

# Add all files
git add .

# Initial commit
git commit -m "feat: initial release of InfiniDM by Infiniforge Technologies"

# Set main branch
git branch -M main

# Add GitHub Remote Repository
git remote add origin https://github.com/Infiniforge-Technologies/InfiniDM.git

# Push code to GitHub
git push -u origin main
```

---

## 📄 License & Ownership

Developed and Maintained exclusively by **Infiniforge Technologies**.  
All rights reserved.  

For enterprise licensing, custom integration, or support:
- 🌐 **Website**: [infiniforge.com](https://infiniforge.com)
- 📧 **Email**: support@infiniforge.com
