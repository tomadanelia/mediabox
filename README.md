# IPTV & მედია მართვის სისტემა — სრული ტექნიკური დოკუმენტაცია

Laravel 12-ზე დაფუძნებული IPTV backend სისტემის სრული ტექნიკური სახელმძღვანელო. დოკუმენტი მოიცავს არქიტექტურას, ინფრასტრუქტურის მოთხოვნებს, Redis და Node.js-ის საშუალებით სოკეტების ინტეგრაციას, წარმოების გამკვრივებას, Nginx კონფიგურაციას, ფონური პროცესებს და საჭირო ადმინისტრაციულ ბრძანებებს.

---

## შინაარსი

1. [სისტემის არქიტექტურის მიმოხილვა](#1-სისტემის-არქიტექტურის-მიმოხილვა)
2. [ინფრასტრუქტურის მოთხოვნები](#2-ინფრასტრუქტურის-მოთხოვნები)
3. [Laravel-ის წარმოების გამკვრივება](#3-laravel-ის-წარმოების-გამკვრივება)
4. [Node.js Socket სერვერის განლაგება](#4-nodejs-socket-სერვერის-განლაგება)
5. [Nginx Reverse Proxy კონფიგურაცია](#5-nginx-reverse-proxy-კონფიგურაცია)
6. [რეალური დროის ინტეგრაცია: Redis & Node.js](#6-რეალური-დროის-ინტეგრაცია-redis--nodejs)
7. [ფონური პროცესები (რიგები) და მდგრადობა](#7-ფონური-პროცესები-რიგები-და-მდგრადობა)
8. [დაგეგმილი დავალებები (Cron Jobs)](#8-დაგეგმილი-დავალებები-cron-jobs)
9. [Artisan ბრძანებები (მოვლა და სინქრონიზაცია)](#9-artisan-ბრძანებები-მოვლა-და-სინქრონიზაცია)
10. [ძირითადი ფუნქციების ლოგიკა](#10-ძირითადი-ფუნქციების-ლოგიკა)
11. [მონიტორინგი და მოვლა](#11-მონიტორინგი-და-მოვლა)
12. [უსაფრთხოება](#12-უსაფრთხოება)
13. [დაყენების სია](#13-დაყენების-სია)

---

## 1. სისტემის არქიტექტურის მიმოხილვა

აპლიკაცია წარმოადგენს ცენტრალურ კვანძს ძველი MediaBox API-ს, საბოლოო მომხმარებლის კლიენტებს (Web SPA, Android TV APK, მობილური) და გარე გადახდის პროვაიდერებს (InterPay) შორის.

სისტემა მუშაობს როგორც განაწილებული არქიტექტურა:

- **Laravel 12 API** — ამუშავებს ბიზნეს-ლოგიკას, ავთენტიფიკაციას, გადახდებს (InterPay) და EPG/Stream მონაცემების მოთხოვნებს ძველი პროვაიდერებისგან.
- **Node.js Socket სერვერი** — მართავს მუდმივ WebSocket კავშირებს Android TV-სა და Web SPA-სთვის.
- **Redis** — გამოიყენება როგორც საერთო მონაცემთა სატრანსპორტო ფენა (Common Data Bus).
- **საერთო საიდუმლო (Shared Secret)** — ორივე გარემომ უნდა გამოიყენოს ერთი და იგივე `JWT_SOCKET_SECRET`.
- **Pub/Sub** — Laravel აქვეყნებს მოვლენებს; Node.js გამოიწერს და გადასცემს სოკეტებზე.

### ძირითადი ტექნოლოგიური სტეკი

| ფენა | ტექნოლოგია |
|---|---|
| **Backend** | PHP 8.3.29 / Laravel 12 |
| **მონაცემთა ბაზა** | MySQL (მთავარი საცავი) |
| **ქეშირება და რეალური დრო** | Redis |
| **ავთენტიფიკაცია** | Laravel Sanctum (მობილური/TV) და სესიაზე დაფუძნებული (Web SPA) |
| **რეალური დროის ძრავა** | Node.js Socket Server (ინტეგრაცია Redis Pub/Sub-ის საშუალებით) |

---

## 2. ინფრასტრუქტურის მოთხოვნები

| კომპონენტი | ვერსია / დეტალი |
|---|---|
| **PHP** | 8.3.29 (საჭირო გაფართოებები: `bcmath`, `ctype`, `fileinfo`, `pdo_mysql`, `redis`) |
| **Node.js** | 18.x+ (LTS) |
| **Redis** | 6.0+ (საჭიროების შემთხვევაში გაითვალისწინეთ `notify-keyspace-events` TTL ლოგიკისთვის) |
| **მონაცემთა ბაზა** | MySQL 8.0+ ან MariaDB 10.6+ |
| **პროცესის მართვა** | PM2 (Node) და Systemd ან Supervisor (Laravel Workers) |

---

## 3. Laravel-ის წარმოების გამკვრივება

### გარემოს ცვლადები (.env)

დარწმუნდით, რომ ეს პარამეტრები დაყენებულია წარმოებისთვის:

```env
APP_ENV=production
APP_DEBUG=false
DEBUGBAR_ENABLED=false

# Socket/API handshake-ისთვის კრიტიკული
JWT_SOCKET_SECRET=SAME_AS_NODE_SERVER
JWT_ALGO=HS256

# ოპტიმიზაცია
CACHE_STORE=redis
SESSION_DRIVER=redis  # საერთო სესიის მდგომარეობა მრავალი web node-ის შემთხვევაში
QUEUE_CONNECTION=redis
```

### განლაგების ბრძანებები

გაუშვით CI/CD პროცედურის ნაწილად:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize  # ინახავს კეშში კონფიგურაციას, მარშრუტებსა და ფაილებს
php artisan view:cache
```

### განლაგების შემდგომი მონაცემთა სინქრონიზაცია

გაუშვით კონტენტის კატალოგის ინიციალიზაცია ძველი MediaBox API-დან:

```bash
# კონტენტის პირველადი ჩატვირთვა
php artisan app:sync-channels
php artisan app:sync-radio

# სისტემის cron-ის რეგისტრაცია (გამოწერების განახლებისთვის სავალდებულო)
# * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 4. Node.js Socket სერვერის განლაგება

Socket სერვერი იყენებს `cluster` მოდულს CPU-ის ბირთვებზე ჰორიზონტალური მასშტაბირებისთვის.

**აგება:** `npm run build` (TypeScript-ს `dist/`-ში გადაიყვანს)

**პროცესის მართვა:** გამოიყენეთ PM2, რათა მთავარი პროცესი ავტომატურად გადაიტვირთოს წარუმატებლობის შემთხვევაში.

```bash
pm2 start dist/index.js --name "mediabox-sockets"
```

### ქსელი

- **შიდა პორტი:** ნაგულისხმევი არის `3000`.
- **JWT ვალიდაცია:** Node.js ამოწმებს ყველა კავშირს და ადასტურებს `sub` (მომხმარებლის ID) საერთო საიდუმლოს მეშვეობით. ავთენტიფიკაციის წარუმატებლობისას სოკეტი ნებისმიერ ოთახში შეერთებამდე გათიშვას ექვემდებარება.

---

## 5. Nginx Reverse Proxy კონფიგურაცია

წარმოებაში Nginx-ს ევალება SSL-ის დასრულება და ტრაფიკის გადამისამართება PHP-FPM-სა და Node.js-ს შორის.

```nginx
# Laravel API
location /api {
    try_files $uri $uri/ /index.php?$query_string;
}

# Node.js Sockets
server {
    listen 80;
    server_name tv-api.telecomm1.com;

    # 1. Real IP Configuration (Cloudflare)
    real_ip_header CF-Connecting-IP;
    set_real_ip_from 103.21.244.0/22;
    set_real_ip_from 103.22.200.0/22;
    set_real_ip_from 103.31.4.0/22;
    set_real_ip_from 141.101.64.0/18;
    set_real_ip_from 108.162.192.0/18;
    set_real_ip_from 190.93.240.0/20;
    set_real_ip_from 188.114.96.0/20;
    set_real_ip_from 197.234.240.0/22;
    set_real_ip_from 198.41.128.0/17;
    set_real_ip_from 162.158.0.0/15;
    set_real_ip_from 104.16.0.0/13;
    set_real_ip_from 104.24.0.0/14;
    set_real_ip_from 172.64.0.0/13;
    set_real_ip_from 131.0.72.0/22;

    # Default root for Laravel
    root /var/www/mediabox/mediabox_back/public;
    index index.php index.html;

    # --------------------------------------------------------
    # 2. PRIORITY BLOCKS (Use ^~ to override Regex)
    # --------------------------------------------------------
    # Handle Laravel Reverb WebSockets
    location ^~ /app/ {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header Scheme $scheme;
        proxy_set_header SERVER_PORT $server_port;
        proxy_set_header REMOTE_ADDR $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";

        proxy_pass http://127.0.0.1:8080;
    }
    # Handle Node.js WebSockets
    location ^~ /socket.io/ {
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header Host $host;
    proxy_pass http://127.0.0.1:3000;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    }
    # Handle Laravel API
    location ^~ /api/ {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Handle Sanctum
    location ^~ /sanctum/ {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Handle Laravel Storage (SVG/Images)
    location ^~ /storage/ {
        # Using root here is fine because 'storage' exists inside 'public'
        root /var/www/mediabox/mediabox_back/public;
        try_files $uri =404;
        access_log off;
        add_header 'Access-Control-Allow-Origin' '*' always;
    }

    # --------------------------------------------------------
    # 3. REGEX & CATCH-ALL
    # --------------------------------------------------------

    # Handle PHP-FPM
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Static Assets (Frontend)
    location ~* \.(?:js|css|svg|png|jpg|jpeg|gif|ico|webp)$ {
        root /var/www/mediabox/mediabox_front;
        access_log off;
        expires max;
        # Use try_files to avoid hijacking the /storage/ svgs
        try_files $uri =404;
    }

    # React SPA Catch-all
    location / {
        root /var/www/mediabox/mediabox_front;
        try_files $uri $uri/ /index.html;
    }
}


```

---

## 6. sockets ინტეგრაცია: Redis & Node.js

სისტემა იყენებს **სოკეტების არქიტექტურას**. Laravel არ ამუშავებს WebSocket კავშირებს პირდაპირ — სამაგიეროდ, ის Redis Pub/Sub-ის საშუალებით ურთიერთობს დამოუკიდებელ Node.js სერვერთან.

### Pub/Sub ნაკადი

1. **მოვლენის გამოძახება** — Laravel-ში ხდება მოქმედება (მაგ., TV-ის დაწყვილების კოდი სკანირდება, ან ადმინი აგზავნის გლობალურ შეტყობინებას).
2. **გამოქვეყნება** — Laravel აქვეყნებს მოვლენას `Redis::publish('channel_name', json_payload)`-ის გამოყენებით.
3. **გამოწერა** — Node.js სერვერი უსმენს შესაბამის Redis არხებს.
4. **გაგზავნა** — Node.js სერვერი იღებს მონაცემებს და WebSocket-ის საშუალებით უგზავნის მათ შესაბამის კლიენტ(ებ)ს.

### შეტყობინების ნაკადი

```
Laravel  →  Redis::publish('broadcast_notifications', json_encode([...]))
Node Primary  →  subscribe('*broadcast_notifications')
Node Worker  →  io.to('user_UUID').emit(...)
```

### TV-ის დაწყვილების ნაკადი

```
TV            →  უერთდება ოთახს: device_room_pairing_{CODE}
მობილური აპი  →  ადასტურებს დაწყვილების კოდს Laravel-ში
Laravel       →  Redis-ში აქვეყნებს claim_token-ს
Node          →  TV-ს გადასცემს ტოკენს დაწყვილების ოთახის საშუალებით
TV            →  ცვლის claim_token-ს მუდმივ API ტოკენზე: POST /api/tv/claim
```

### ძირითადი Redis არხები

| არხი | დანიშნულება |
|---|---|
| `pairing_events` | TV-ის დაწყვილება — გადასცემს `claim_token`-ს მას შემდეგ, რაც მომხმარებელი მობილური მოწყობილობიდან TV-ს ავტორიზებს. |
| `broadcast_notifications` | გლობალური განცხადებები და მომხმარებელზე მორგებული შეტყობინებები. |
| `tv_session_ready` | მართავს დისტანციური მართვის სესიებს ტელეფონსა და TV-ს შორის. |

---

## 7. ფონური პროცესები (რიგები) და მდგრადობა

შრომატევადი დავალებები გადაიტვირთება Laravel-ის queue სისტემაში, რათა API-ს პასუხის დაყოვნება მინიმალური იყოს. შემდეგი სერვისები სავალდებულოდ უნდა იმართებოდეს პროცესების მართვის სისტემით (Supervisor).

### 1. Laravel Queue Worker

SMS/Email-ის გასაგზავნად საჭიროა.

**კონფიგურაცია (.ini):**

```ini
[program:laravel-worker]
command=php /path-to-project/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
```

**გაშვება:**

```bash
php artisan queue:work --tries=3
```

### ძირითადი Queue დავალებები

- **`SendSmsJob`** — ამუშავებს გამავალ SMS დადასტურების კოდებს Telecom1 პროვაიდერის საშუალებით. გამოყოფილია მოთხოვნის ციკლისგან, რათა თავიდან ავიცილოთ გარე SMS შლუზის გამო დაყოვნება.
- **`VerificationCodeMail`** — რიგში ჩასმული ელ-ფოსტაზე დაფუძნებული OTP დადასტურებისთვის.

### 2. Node.js სერვერი

მართავს PM2.

```bash
pm2 start dist/index.js --name "mediabox-sockets"
```

---

## 8. დაგეგმილი დავალებები (Cron Jobs)

ავტომატური მოვლის დავალებები განსაზღვრულია `routes/console.php`-ში.

### საჭირო Cron ჩანაწერი

Laravel-ის დაგეგმვის გასააქტიურებლად სერვერის crontab-ში დაამატეთ:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

### განრიგი

| ბრძანება | სიხშირე | აღწერა |
|---|---|---|
| `app:process-renewals` | ყოველდღიურად 19:00-ზე | ამოწმებს `auto_renew` ჩართული ვადამოსული გამოწერებს, ახდენს ბალანსის ჩამოჭრას და ახანგრძლივებს წვდომას. |
| `model:prune` | განსაზღვრეთ სიხშირე | `TvPairing` მოდელი იყენებს `Prunable` trait-ს — დარწმუნდით, რომ `php artisan model:prune` დაგეგმილია ვადამოსული დაწყვილების კოდების გასასუფთავებლად. |

---

## 9. Artisan ბრძანებები (მოვლა და სინქრონიზაცია)

გამოიყენეთ ეს ბრძანებები ძველი MediaBox API-დან მონაცემების სინქრონიზაციისთვის ან სისტემის მდგომარეობის შენარჩუნებისთვის.

### არხების სინქრონიზაცია

გაუშვით პირველადი დაყენებისას და პერიოდულად კონტენტის კატალოგის განახლებისთვის.

```bash
# TV არხების სინქრონიზაცია
# იღებს UID-ებს, არხის ნომრებს და ლოგოებს.
# ავტომატურად ანიჭებს "Standard Package"-ს ფასიან არხებს.
php artisan app:sync-channels

# რადიო არხების სინქრონიზაცია
# იღებს რადიოს stream URL-ებს და მეტამონაცემებს.
php artisan app:sync-radio
```

---

## 10. ძირითადი ფუნქციების ლოგიკა

### TV-ის დაწყვილების სისტემა

სისტემა იყენებს claim-ზე დაფუძნებულ დაწყვილების ნაკადს:

1. TV მიმართავს `POST /api/tv/init`-ს, იღებს 6-ნიშნა კოდს და იწყებს WebSocket მოვლენის მოლოდინს.
2. მობილური აპი მიმართავს `POST /api/tv/pair`-ს კოდით.
3. Laravel ქმნის `claim_token`-ს, აქვეყნებს მას Redis-ში და TV იღებს მას WebSocket-ის საშუალებით.
4. TV მიმართავს `POST /api/tv/claim`-ს ტოკენის გამოყენებით, რათა მიიღოს მუდმივი `tv_apk` Sanctum ტოკენი.

### გამოწერა და მრავალი TV-ის ლიმიტი

- **TV ლიმიტი** — მომხმარებლებს აქვთ `tv_limit` ატრიბუტი (ნაგულისხმევი: `1`).
- **აღსრულება** — `User::enforceTvLimit()` გამოიძახება შესვლისა და დაწყვილების დროს. თუ მომხმარებელი გადააჭარბებს ლიმიტს, ყველაზე ძველი TV სესია ავტომატურად გაუქმდება.
- **განახლება** — მომხმარებლებს შეუძლიათ შეიძინონ დამატებითი TV სლოტები `SubscriptionService`-ის საშუალებით.

### გადახდის შლუზი (InterPay)

სისტემა მხარს უჭერს ორსაფეხურიან გადახდის დამუშავებას:

| საფეხური | ოპერაცია | აღწერა |
|---|---|---|
| 1 | `OP=debt` | ამოწმებს მომხმარებლის არსებობას `numeric_id`-ით (მომხმარებლის ID) და აბრუნებს მიმდინარე ბალანსს. |
| 2 | `OP=paysuccess` | ატომარულად ზრდის ანგარიშის ბალანსს და ინახავს ტრანზაქციას. |

---

## 11. მონიტორინგი და მოვლა

### რეალური დროის მეტრიკა

`/api/metrics/realtime` endpoint-ი გვაწვდის JSON სნაფშოტს მიმდინარე მაყურებლების რაოდენობის შესახებ თითოეული არხისთვის, Redis-დან.

### ჟურნალები (Logs)

| კომპონენტი | ჟურნალის ადგილმდებარეობა |
|---|---|
| **Laravel** | `storage/logs/laravel.log` |
| **Node.js** | `pm2 logs mediabox-sockets` |

### TvPairing-ის გასუფთავება

`TvPairing` მოდელი იყენებს `Prunable` trait-ს. დარწმუნდით, რომ `php artisan model:prune` დაგეგმილია, რათა პერიოდულად წაიშალოს ვადამოსული დაწყვილების კოდები.

### Heartbeat სისტემა

აქტიური მაყურებლები თვალყურს ედევნებიან Redis-ის დალაგებულ სეტებში `active_viewers:{channelId}` გასაღების პატერნით. ეს უზრუნველყოფს რეალური დროის კონკურენტულობის მონაცემებს Zabbix ან Prometheus-ის მსგავსი მონიტორინგის ინსტრუმენტებისთვის.

---

## 12. უსაფრთხოება

### IP-ების თეთრი სია

`/api/metrics/realtime` და InterPay endpoint-ები დაცულია `IpWhiteList` middleware-ით. დაშვებული IP-ები განსაზღვრული უნდა იყოს `.env`-ში:

```env
ALLOWED_STATS_AND_INTERPAY_IPS=1.2.3.4,5.6.7.8
```

### Sanctum ტოკენის ქეშირება

`CachedPersonalAccessToken` გამოიყენება მონაცემთა ბაზაზე დატვირთვის შესამცირებლად. ტოკენები ქეშირდება Redis-ში **15 წუთის** განმავლობაში, რათა დააჩქაროს ავთენტიფიკაცია მაღალი სიხშირის heartbeat მოთხოვნებისთვის.

### JWT Socket ავთენტიფიკაცია

Node.js ამოწმებს ყველა შემომავალ Socket კავშირს. `JWT_SOCKET_SECRET` სავალდებულოდ უნდა ემთხვეოდეს Laravel-სა და Node.js-ის `.env` ფაილებში.

```env
JWT_SOCKET_SECRET=SAME_AS_NODE_SERVER
JWT_ALGO=HS256
```

---

## 13. დაყენების სია

- [ ] **გარემო** — დააკონფიგურირეთ `.env` MySQL-ის, Redis-ისა და ფოსტის მონაცემებით.
- [ ] **მონაცემთა ბაზა** — გაუშვით `php artisan migrate --seed`.
- [ ] **Laravel-ის ოპტიმიზაცია** — გაუშვით `php artisan optimize` და `php artisan view:cache`.
- [ ] **კონტენტის სინქრონიზაცია:**
  ```bash
  php artisan app:sync-channels
  php artisan app:sync-radio
  ```
- [ ] **Node.js სერვერი** — დარწმუნდით, რომ socket სერვერი გაშვებულია და დაკავშირებულია იმავე Redis-ის ინსტანციასთან.
- [ ] **PM2** — გაუშვით `pm2 start dist/index.js --name "mediabox-sockets"`.
- [ ] **რიგის პროცესი** — Supervisor-ის კონფიგურაციით გაუშვით `php artisan queue:work`.
- [ ] **განმსაზღვრელი (Cron)** — შეამოწმეთ, რომ სისტემის crontab ჩანაწერი აქტიურია.
- [ ] **Nginx** — დააკონფიგურირეთ reverse proxy `/api` და `/socket.io/` მარშრუტებისთვის SSL-ის ჩათვლით.
- [ ] **IP თეთრი სია** — დააყენეთ `ALLOWED_STATS_AND_INTERPAY_IPS` `.env`-ში.
- [ ] **model:prune** — დაამატეთ `php artisan model:prune` cron-ში ვადამოსული `TvPairing` ჩანაწერების გასასუფთავებლად.
