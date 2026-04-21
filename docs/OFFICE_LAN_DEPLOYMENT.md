# ERMS — Office LAN Deployment (XAMPP / Windows)

Goal: **Isang PC ang “SERVER”**, tapos lahat ng office PCs (clients) ay **browser/app shortcut lang**.

## 0) Overview (Paano gumagana)

- **SERVER PC**
  - Naka-install ang **XAMPP** (Apache + MySQL)
  - Naka-copy ang folder ng system sa `C:\xampp\htdocs\ERMS\`
  - Siya ang nagho-host ng website + database
- **CLIENT PCs**
  - Walang i-install (browser lang)
  - Buksan lang ang URL: `http://ERMS-SERVER/ERMS/` (recommended) o `http://<SERVER_IP>/ERMS/`

Recommended: **LAN-only** ito. Huwag i-expose sa internet.

---

## 1) Prepare the SERVER PC (Name + Static IP)

### 1.1 Set the server computer name

Para memorable ang URL (server name), i-rename ang PC:

- Windows Settings → System → About → **Rename this PC**
- Suggested name: **ERMS-SERVER**
- Restart kapag nag-prompt.

### 1.2 Make the server IP stable (Static IP or DHCP Reservation)

Kailangan stable ang IP para hindi “nag-iiba” ang access.

**Option A (Best): DHCP Reservation sa router**
- Sa router admin page, i-reserve ang IP para sa MAC address ng server.

**Option B: Static IP sa Windows**
- Settings → Network & Internet → Advanced network settings → More network adapter options
- Right-click adapter (LAN/Wi‑Fi) → Properties → **Internet Protocol Version 4 (TCP/IPv4)**
- Use the following IP address:
  - IP: (hal. `192.168.254.134`)
  - Subnet mask: (often `255.255.255.0`)
  - Default gateway: (router IP, hal. `192.168.254.254`)
  - DNS: router IP or your DNS

---

## 2) Install and verify XAMPP on SERVER PC

1) Install XAMPP (same major PHP version you used during development if possible)
2) Open **XAMPP Control Panel (Run as Administrator)**
3) Start:
   - **Apache**
   - **MySQL**
4) Verify locally on the server PC:
   - Open browser → `http://localhost/ERMS/login.php`

---

## 3) Deploy the ERMS files to SERVER PC

### 3.1 Copy project folder

On the SERVER PC:

- Copy the whole `ERMS` folder into:
  - `C:\xampp\htdocs\ERMS\`

Quick check:
- Ensure `C:\xampp\htdocs\ERMS\login.php` exists.

### 3.2 Writable folders (uploads/exports)

The web server must be able to write to these folders:
- `uploads/`
- `storage/`
- `export_nuero/` (if used)

If you get “permission denied” errors:
- Right click the folder → Properties → Security → grant **Modify** to the account running Apache.
  - Note: if Apache is installed as a Windows Service, it may run under **Local System**.

---

## 4) Database setup on SERVER PC (MySQL)

Your PHP code uses these defaults:
- DB host: `127.0.0.1`
- DB name: `erms`
- DB user: `root`
- DB pass: empty

These are defined in `includes/db.php`.

### 4.1 Fresh install (new database)

1) Open `http://localhost/phpmyadmin`
2) Import:
   - File: `database/schema.sql`
3) (Optional) Seed data if you have scripts:
   - See `scripts/` folder (e.g., `seed_users.php`) — run only if you know what it does.

### 4.2 Move existing data from your current machine

If you already have office data on your current PC:

1) On CURRENT PC (old server):
   - phpMyAdmin → select DB `erms` → Export → Quick/Custom → download `.sql`
2) On NEW SERVER PC:
   - phpMyAdmin → Import → upload that `.sql`
3) Also copy file-based data if used:
   - `uploads/`
   - `storage/`
   - `export_nuero/`

---

## 5) Make it AUTO-RUN (No need to open XAMPP)

On SERVER PC:

1) Open XAMPP Control Panel as Admin
2) For **Apache** and **MySQL**:
   - Click **Svc** (install as Windows Service)
   - Start them
3) Open `services.msc`
   - Find Apache/MySQL services
   - Set Startup type to **Automatic**

After reboot, ERMS should be reachable without opening anything.

---

## 6) Allow other PCs to access (Windows Firewall)

On SERVER PC:

- Windows Defender Firewall → Advanced settings → Inbound Rules → New Rule
  - Type: Port
  - Protocol: TCP
  - Port: **80**
  - Action: Allow
  - Profile: Domain/Private (whichever matches your office network)

If you changed Apache to `8080`, allow port **8080** instead.

---

## 7) Make a “Server Name” URL (ERMS-SERVER) work

### 7.1 Preferred: Router DNS / Local DNS

If your router supports local DNS entries:
- Map `ERMS-SERVER` → the server’s static IP

Then clients can use:
- `http://ERMS-SERVER/ERMS/`

### 7.2 Simple + guaranteed: Hosts file on each CLIENT PC

If you can’t set router DNS, do this per client PC (requires admin):

Important:
- This must be done on **each CLIENT PC** (the same PC where you open the browser).
- The hostname you type in the browser must match what you add in `hosts`.

1) Open Notepad as Administrator
2) Open file:
   - `C:\Windows\System32\drivers\etc\hosts`
3) Add line (example):

Make sure it is **NOT** commented out. If the line starts with `#`, Windows will ignore it.

```
192.168.254.134   ERMS-SERVER
```

4) Save
    - Make sure you did **NOT** create `hosts.txt`.
       - In the `etc` folder, the file must be named exactly `hosts` (no extension).
5) Test in browser:
- `http://ERMS-SERVER/ERMS/`

If it still says “can’t reach / NXDOMAIN”, do these on the CLIENT PC:
1) Open Command Prompt and run:

```
ipconfig /flushdns
ping ERMS-SERVER
```

Expected:
- `ping ERMS-SERVER` should show `Pinging ERMS-SERVER [192.168.254.134] ...`
- If it still can’t resolve, re-check the `hosts` file line and save location.

Fallback (always works):
- `http://192.168.254.134/ERMS/`

---

## 8) Client PC setup (One-click “App”)

### 8.1 Install as App (recommended)

**Microsoft Edge**
1) Open: `http://ERMS-SERVER/ERMS/` (or IP fallback)
2) Click the **three dots** (`...`) on the top-right
3) Click **Apps**
4) Click **Install this site as an app**
    - If you don’t see “Apps”, try:
       - `...` → **More tools** → **Pin to Start** / **Pin to taskbar** (less app-like), OR
       - Look for an **Install** icon near the address bar (some Edge versions show this)
5) Click **Install**
6) (Optional) Right-click the created shortcut → **Pin to taskbar**

**Google Chrome**
1) Open: `http://ERMS-SERVER/ERMS/` (or IP fallback)
2) Click the **three dots** (`...`) on the top-right
3) Click **Save and share** → **Create shortcut**
    - If you don’t see “Save and share”, try:
       - `...` → **More tools** → **Create shortcut**
4) Check **Open as window**
5) Click **Create**
6) (Optional) Right-click the created shortcut → **Pin to taskbar**

### 8.2 Pin to Desktop/Taskbar
- Right-click the created shortcut → Pin to taskbar

---

## 9) Recommended entry URL

Use these (in order):
1) `http://ERMS-SERVER/ERMS/`
2) `http://ERMS-SERVER/ERMS/login.php`
3) `http://<SERVER_IP>/ERMS/`

---

## 10) Quick Troubleshooting

- **Client can’t open the site**
  - Check same Wi‑Fi/LAN
   - If you’re on **Guest Wi‑Fi**, switch to the main Wi‑Fi (guest networks often block LAN access)
   - Some routers have **AP/Client Isolation** enabled — disable it for the office Wi‑Fi
  - Ping server: `ping 192.168.254.134`
   - Check if port 80 is reachable (Client PC PowerShell):

```
Test-NetConnection 192.168.254.134 -Port 80
```

  - Check firewall rule for port 80
  - Make sure Apache is running

- **Site opens on server but not on clients**
  - Firewall or wrong IP
  - IP changed → use static IP / DHCP reservation

- **Works via IP but not via ERMS-SERVER / erms-server name**
   - This is a **name/DNS/hosts file** issue (not Apache)
   - Use: `http://<SERVER_IP>/ERMS/` or set Router DNS / Windows `hosts` file

- **Can’t ping the server IP**
   - Not same network/subnet, or blocked by router settings (guest Wi‑Fi / isolation)
   - Confirm both devices are on the same office router and not on mobile data

- **403 / directory listing / wrong landing page**
  - Use `http://.../ERMS/login.php`
  - (Optional) add an `index.php` redirect in the ERMS root

---

## 11) Backup (minimum)

- Database: export `erms` via phpMyAdmin (daily/weekly)
- Files: back up `uploads/`, `storage/`, `export_nuero/`

---

If you want, I can also add a tiny `index.php` redirect so `http://ERMS-SERVER/ERMS/` always lands on the login page (and avoids directory listing).
