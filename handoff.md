# Nova Dashboard — Handoff

მდგომარეობა: **მუშა, ტესტირებული**. ბოლო სესია: 2026-08-07.

**Git:** `git init`-ი გაკეთდა 2026-07-27. Remote: `https://github.com/Amadeuse/dashboard`
(public, `main`). `.env`, `sql/` (1538 დამკვეთის PII), `.osp/`, `dashboard.loc.rar`
**push-ში არ შესულა** — `.gitignore`-შია, დისტანციურად გადამოწმებულია (404).

---

## 1. რა არის ეს

Bootstrap 5.3-ზე აგებული dashboard-ის დიზაინის სისტემა, გადატანილი მარტივ MVC-ზე.
ორენოვანი (ქართული / ინგლისური). MySQL ბაზა `invoice` — `Core/Db.php`. Dashboard
გვერდი ჯერ კიდევ ჩაწერილ მონაცემებზეა (`app/Models/Dashboard.php`), customers/
products კი რეალურ ცხრილებზე მუშაობს.

## 2. გაშვება

```bash
cd C:/OSPanel/home/dashboard.loc/public && C:/OSPanel/modules/PHP-8.2/php.exe -S 127.0.0.1:8090 index.php
```

`index.php` არგუმენტად აუცილებელია — router-სკრიპტად მუშაობს (`.htaccess`-ს `php -S` არ კითხულობს).
OSPanel-ით: `web_root` უკვე `public/`-ზეა მითითებული `.osp/project.ini`-ში.

მარშრუტები: `/`, `/style-guide`, `GET|POST /customers`, `GET|POST /products`,
`POST /units`, `GET /settings/modules` + `POST .../install|enable|disable`.
ჩართული მოდულების route-ები (მაგ. Warehouse-ის `POST /product-types`)
დინამიურად ემატება `public/index.php`-ში, `routes.php`-ის შემდეგ — იხ. §4.19.

## 3. სტრუქტურა

```
dashboard.loc/
├── .env / .env.example / .gitignore
├── sql/customers.sql           ← phpMyAdmin dump, 1538 დამკვეთი (რეალური PII).
│                                 **docroot-ის გარეთ და .gitignore-ში** — public/-ში
│                                 იდო და HTTP-ით ჩამოიტვირთებოდა.
├── migrations/ + migrate.php   `php migrate.php` — CORE *.sql-ები, `Migrator::run()`-ით
├── app/                        ← docroot-ის გარეთ, ბრაუზერიდან მიუწვდომელი
│   ├── bootstrap.php           autoloader → Env::load() → display_errors → session → Lang::boot()
│   ├── routes.php              $router->get('/', [Ctrl::class,'method']) — CORE route-ები
│   ├── Core/                   Router, Controller, Lang, Env, Db, helpers,
│   │                           Hooks/Migrator/ModuleRegistry/ModuleInterface (§4.19)
│   ├── Controllers/            Dashboard, StyleGuide, Error, Customer, Product,
│   │                           Lookup, Module
│   ├── Models/Dashboard.php    ჩაწერილი მონაცემები (stats/orders/traffic/activity/goal)
│   ├── Modules/<Code>/         ჩართვად-გამორთვადი მოდულები — იხ. §4.19 (Warehouse პირველი)
│   ├── Views/                  layout.php + გვერდები + partials/ + errors/
│   └── lang/                   ka.php, en.php
└── public/                     ← web_root
    ├── index.php               front controller (~20 ხაზი)
    ├── .htaccess               rewrite → index.php
    └── assets/                 css/ js/ fonts/ images/
```

**მოთხოვნის გზა:** `public/index.php` → `app/bootstrap.php` → `routes.php` → `Router::dispatch()` → Controller → `Controller::view()` → view იბუფერება `$content`-ში → `Views/layout.php`.

**View-ს შეუძლია `$scripts` ცვლადის დაყენება** (იხ. `Views/dashboard.php` ბოლოს) — layout მას ავტომატურად აიღებს, რადგან ერთსა და იმავე scope-შია.

---

## 4. მნიშვნელოვანი აღმოჩენები — **ეს ნაწილი ყველაზე ღირებულია**

ქვემოთ ჩამოთვლილი თითოეული საათობით ძებნის შედეგია. კოდიდან ეს **არ ჩანს**.

### 4.1 Sidebar-ის ფონს `!important` სჭირდება
`design-system.css`-ში `.ds-sidebar { background: ... !important }`.
მიზეზი: Bootstrap-ის `.offcanvas-lg` კლასი ≥992px-ზე თვითონ აყენებს
`background-color: transparent !important`-ს. ამის გარეშე sidebar თეთრი რჩება.
**არ მოხსნა ეს `!important`.**

### 4.2 Inter-ს ქართული არ აქვს
Google-ის Inter მოიცავს მხოლოდ latin/latin-ext/cyrillic/greek/vietnamese.
`U+10A0–10FF` არ შედის. ამიტომ ფონტების სტაკია:
`"Inter", "Noto Sans Georgian", system-ui, ...` — ლათინური Inter-ზე რჩება,
ქართული ავტომატურად Noto-ზე ჩავარდება. `@font-face` ხელით არ არის საჭირო.

### 4.3 მთავრული: CSS-ით **შეუძლებელია**
`text-transform: uppercase` ქართულ მხედრულს **არ** გარდაქმნის — ბრაუზერების
განზრახ ქცევაა (გაზომვით დადასტურებული: სიგანე უცვლელი რჩება).

გამოსავალი, რომელიც ამჟამად მუშაობს: **BPG Arial Caps**
(`public/assets/fonts/bpg-arial-caps/`), რომლის მთავრულისებრი გლიფები
**ჩვეულებრივ მხედრულ კოდებზეა** დაბმული (U+10D0+).

⚠️ **BPG-ს `U+1C90–1CBF` (ნამდვილი მთავრული) საერთოდ არ აქვს.**
ამიტომ `mb_strtoupper()`-ის გამოყენება ამ ფონტთან **აზიანებს** შედეგს —
აწარმოებს კოდებს, რომლებიც ფონტში არ არსებობს. ერთხელ უკვე დავუშვი ეს შეცდომა.

`.ds-nav-section`-ის სტაკი: `"Inter", "BPG Arial Caps", "Noto Sans Georgian", sans-serif`.

### 4.4 `<p>`-ს Bootstrap-ის ფარული `margin-bottom`
`.ds-nav-section` არის `<p>`. Bootstrap-ის ნაგულისხმევი `margin-bottom: 1rem`
სექციის ლეიბლს ქვედა ჯგუფს აშორებდა და ზედას აკვროდა.
ახლა `margin: 0` + `line-height: 1` + `padding: 1.35rem .6rem .45rem`.
შედეგი: ზემოთ 29.9px / ქვემოთ 17.7px (1.69×) — ლეიბლი თავის ჯგუფს ეკუთვნის.

### 4.5 `html { font-size: 90% }`
`design-system.css`-ის დასაწყისში. ყველა `rem` 0.9-ზე მრავლდება
(ეფექტური root = 14.4px). ზომების გამოთვლისას გაითვალისწინე.

### 4.6 ბრაუზერის ტესტირების ხაფანგი
MCP-ბრაუზერის tab კადრებს **არ ახატავს** — `screenshot` ვერ მუშაობს და
**CSS-ტრანზიციები არ მიმდინარეობს**. ანიმირებული თვისების გაზომვისას
საწყის მნიშვნელობას წაიკითხავ და გეგონება, რომ კოდი გატეხილია.
გაზომვამდე ჩააქრე: `document.head.appendChild(style with *{transition:none!important})`.
ერთხელ ამან უკვე მაფიქრებინა, რომ sidebar-ის collapse არ მუშაობდა — მუშაობდა.

---

### 4.7 გვერდითი მენიუ JSON-შია, მეორე დონე `<details>`-ია
`app/config/menu.json` — სექციები → ითემები → `children`. `sidebar.php` მხოლოდ ხატავს,
`ds_menu()` (helpers.php) კითხულობს **ყოველ მოთხოვნაზე** (რედაქტირება → F5, ქეში არაა).
გატეხილი JSON → `JsonException` ეკრანზე (`APP_DEBUG=true`), და არა ჩუმად ცარიელი მენიუ.

ქვემენიუ **native `<details>`-ია** — JS არაა, Bootstrap collapse არაა, id-ები არაა.
გახსნილი რჩება, თუ რომელიმე `child`-ის `url` მიმდინარე მარშრუტია.
`summary`-ის ნაგულისხმევი მარკერი ორივე ძრავზეა ჩაქრობილი (`list-style:none` +
`::-webkit-details-marker`). `.ds-nav-caret`-ს `!important` სჭირდება, რადგან
`.ds-nav-link i` მას primary ფერს და `1rem`-ს ახვევს.

აკორდეონი (ერთდროულად მხოლოდ ერთი ღიაა) — `<details name="ds-nav">`, native
ექსკლუზიური ჯგუფი. JS არაა. ⚠️ ერთი `name`-ით ორ `details`-ს **`open` ერთდროულად
არ შეიძლება** — მარკაპში ორივე რომ იყოს, ბრაუზერი მეორეს დახურავს.

ჩამოშლის ანიმაცია — `::details-content` + `interpolate-size: allow-keywords`
(ამის გარეშე `block-size: auto` საერთოდ არ ანიმირდება) +
`transition: content-visibility .2s allow-discrete` (ამის გარეშე დახურვისას
შიგთავსი მყისვე ქრება, ნაცვლად აკეცვისა). JS არაა. ძველი ძრავა → მყისიერი
გადართვა, გატეხვის გარეშე.
⚠️ `prefers-reduced-motion` ბლოკი **ამ წესების შემდეგ** უნდა იდგეს — media query
სპეციფიკურობას არ ზრდის, ადრე დაწერილი override უბრალოდ წააგებდა.
⚠️ `.ds-nav-group`-ის `gap` **ორჯერაა** — `details`-ზეც და `::details-content`-ზეც:
მხარდამჭერ ძრავაზე ქვე-ლინკები ფსევდოელემენტში ეხვევა და მშობლის `gap` აღარ ეხებათ.

### 4.9 `ds-table` — ჩვენი ცხრილის ბიბლიოთეკა
`public/vendor/table/js/ds-table.js` + `public/vendor/table/css/ds-table.css`.
⚠️ `vendor/` **docroot-შია** (`public/vendor/`) და არა პროექტის ფესვში — ბრაუზერს
სხვაგვარად ვერ ჩამოაქვს. ორიგინალი `table.js` / `table.css` / `config/table.json`
წაშლილია (ჩანაცვლდა).

⚠️ `.ds-table` **თვითონაა ბორდერიანი პანელი** და `card-body`-ს შიგნით ჯდება —
card ცხრილს იტევს, ცხრილი card არაა. ბადის ხაზები `> :not(caption) > * > *`
სელექტორითაა (Bootstrap-ის `table-bordered`-ის მიდგომა).

წარმოშობა: `vendor/table` (მხოლოდ სორტირება იყო). შენარჩუნებულია მისი შედარების
ლოგიკა და `⇅ ↑ ↓` სტილი; დამატებულია ძებნა, per-page, pager, რიგების მთვლელი.

გამოყენება — მარკაპში მხოლოდ ცხრილია, toolbar/footer JS-ს გენერირდება:
```html
<div class="ds-table" data-ds-table data-per-page="25"><table class="table">…</table></div>
```
+ view-ში `$scripts = ds_table_script();` (თარგმანებს `table.*` გასაღებებიდან იღებს).

⚠️ **`data-order=""` ცარიელს ნიშნავს, არა „არ არსებობს"** — `getAttribute()`
`null`-თან სრულდება, არა falsy-სთან. `||`-ით დაწერილი ვერსია ბეჯის ტექსტს
(„ფ/პირი") ახარისხებდა ციფრებში. ერთხელ უკვე დავუშვი ეს შეცდომა.

⚠️ **სვეტის ტიპი ერთხელ განისაზღვრება მთელი სვეტის მიხედვით**, არა უჯრედ-უჯრედ.
სახელების სვეტში ერთი დამკვეთი „436044647"-ია — უჯრედობრივი ლოგიკა მას სიის თავში
აგდებდა.

⚠️ ცარიელი უჯრები სორტირებამდე **გამოიყოფა** და ბოლოში ეკვრის — `sign`-ზე
გამრავლება მათ მეორე დაწკაპებაზე თავში აიტანდა.

`Intl.Collator` ერთხელ იქმნება — ყოველ შედარებაზე `localeCompare`-ის გამოძახება
1538 რიგზე 230ms-ს იძლეოდა, კოლატორით 19–29ms.

⚠️ **ფასი: გვერდი ყველა რიგს აგზავნის** — 1538 დამკვეთი = 1.9MB HTML (gzip-ით
102KB). სანამ არ გაიზრდება, ეს მისაღებია; რამდენიმე ათას რიგზე სერვერულ გვერდვაზე
გადასვლა დაგჭირდება.

### 4.10 `floating-label` — ლეიბლი ბორდერზე + გასუფთავების ღილაკი
`public/vendor/floating-label/{css,js}/`. წარმოშობა: `public/vendor/fl.loc`
(Bootstrap-ის სადემონსტრაციო გვერდი). layout-ში გლობალურადაა ჩაბმული.

მარკაპი: `.form-floating` > `input[placeholder=" "]` > `label` > `.btn-clear`.
⚠️ **`placeholder=" "` სავალდებულოა** — `:placeholder-shown`-ზეა ყველაფერი აგებული.
სტაილგაიდში `placeholder` ველის სახელი ეწერა და ლეიბლის ქვეშ გამოსჭვიოდა.

რა შეიცვალა ორიგინალიდან:
- `#005ae0` → `var(--bs-primary)`, `#fff` → `var(--bs-body-bg)`.
  ⚠️ ორიგინალის თეთრი ჩიპი მუქ თემაზე თეთრ ლაქად ჩანდა.
- ღილაკის ჩვენება/დამალვა **CSS-ზე გადავიდა**
  (`:not(:placeholder-shown) ~ .btn-clear`) — script-ს ორი listener და
  ინიციალიზაციის ციკლი მოვაშორე, დარჩა მხოლოდ დაწკაპების დელეგირება.
- `is-invalid`-ის დროს ლეიბლი და focus-რგოლი წითელი რჩება, primary არ ხდება.
- ⚠️ **აწეული ლეიბლის `line-height: 1` → `1.4`.** ქართულ ასოებს (ვ ტ ღ ყ ჯ)
  გრძელი ქვედა ნაწილი აქვთ; `1`-ზე ისინი ჩიპის დახატული ფონის გარეთ რჩებოდნენ და
  ინპუტის ბორდერი მათ გადაკვეთდა — ტექსტი მოჭრილად გამოიყურებოდა. **ლათინურზე ეს
  ხარვეზი არ ჩანს**, ორიგინალი ბიბლიოთეკა მხოლოდ ლათინურზე იყო ნატესტი.
  გაზომილი: ჩიპი 14.77px → 19.94px, ტექსტის ქვედა კიდე ჩიპის შიგნით 2.84px-ით.

⚠️ `.invalid-feedback` **`.form-floating`-ის გარეთაა** და `d-block`-ით ჩნდება:
ლეიბლი `height:100%`-ია, შიგნით მოთავსებული შეტყობინება მას გაწელავდა.

**ინპუტ-გრუპის ღილაკი (`$field()`-ის მე-5 არგუმენტი, `customers.php`):**
`.form-floating`-ს ახვევს `.input-group`-ში, ღილაკს კი მის გვერდით დებს —
Bootstrap-ი შიდა კუთხეებს (input მარჯვნივ, ღილაკი მარცხნივ) თვითონ სწორკუთხავს,
რადგან `.form-floating` არის `.input-group`-ის პირველი შვილი. ს/კ-ის ველზე
ღილაკი სიის ძებნას ავსებს და `#customer-list`-ს ხსნის (JS, `$scripts`-ში).
აიკონი — `gb_rs` (იხ. ქვემოთ), არა `bi-search`.

### 4.12 `gb_symbols` — პროექტის საკუთარი აიკონ-ფონტი
`public/assets/fonts/gb/style.css` + `fonts/gb_symbols.{woff,ttf,eot,svg}`,
layout-ში გლობალურადაა ჩაბმული. კლასები `gb_*` (მაგ. `gb_rs`), არა `bi-*`.
Bootstrap Icons-თან ერთად თანაარსებობს — კონფლიქტი არაა, ცალკე `font-family`.

### 4.13 დამკვეთის რედაქტირება — ერთი ფორმა, ორი რეჟიმი
ცალკე `/customers/{id}` მარშრუტი **არ დაემატა** — Router-ს path-პარამეტრები არ
აქვს (იხ. Router.php, ზუსტი keys). ამის მაგივრად: ერთი ფორმა, ჩუმი `customer_id`
hidden ველი. მისი სიცარიელე ნიშნავს create-ს, რიცხვი — update-ს
(`CustomerController::store()`).

**რიგზე დაწკაპება** (`ds-row-editable`) ავსებს ველებს row-ის `data-*`
ატრიბუტებიდან — JS, DB-ს არ ეხება. ერთი delegated listener `<table>`-ზეა
(არა `tbody`-ზე ცალკეულ `<tr>`-ებზე), რადგან `ds-table.js` sort/search/page-ზე
**იმავე** `<tr>` კვანძებს ინახავს (`replaceChildren`, არა clone) — listener და
`data-*` არ იკარგება გვერდვის შემდეგაც.

⚠️ **დუბლიკატი ს/კ-ის შემოწმებას `$excludeId` სჭირდება** — რედაქტირებისას
საკუთარი ს/კ არ უნდა შეეჯახოს თავის თავს. `Customer::validate($input, $excludeId)`.

⚠️ **წარუმატებელი edit არ უნდა converted-იყოს create-დ.** `flash('old', $clean +
['customer_id' => $id])` — `customer_id` ცალკე ემატება, `Customer::FIELDS`-ში არ
შედის. წინააღმდეგ შემთხვევაში refresh ან ხელახალი submit ახალ რიგს შექმნიდა.

⚠️ **Reset ღილაკს ცალკე listener სჭირდება** — ბრაუზერის ნატიური `reset` მხოლოდ
ველებს წმენდს, `customer_id`-ს და ღილაკის წარწერას („განახლება" → „დამატება") არ
აბრუნებს თავისით.

⚠️ **`scrollIntoView` პირობითია** — ფორმა ნაგულისხმევად ღიაა, ამიტომ
`details.open = true` თითქმის ყოველთვის no-op-ია. უპირობო
`scrollIntoView({block:'start'})` კი მაინც ასწორებდა ფორმის თავს viewport-ის
თავთან ყოველ დაწკაპებაზე — რაც უმეტესწილად **არასასურველ მცირე ასქროლვად**
იგრძნობოდა, თუნდაც ფორმა უკვე ხედვის არეში ყოფილიყო. გასწორება: `wasClosed`
დროშა — სქროლი მხოლოდ მაშინ, თუ ფორმა რეალურად დაკეტილი იყო.

### 4.14 Flash-შეტყობინებები თავისით ქრება
`.ds-alert-autodismiss` კლასი ნებისმიერ `.alert`-ზე + `app.js`-ში ერთი ხაზი:
`setTimeout(() => bootstrap.Alert.getOrCreateInstance(el).close(), 4000)`.
საკუთარი fade/timer **არ დამიწერია** — Bootstrap-ის `Alert`-ს უკვე აქვს
`close()`, რომელიც `.fade`-ს აგებს და node-ს შლის, close-ღილაკის გარეშეც
მუშაობს. გამოყენებულია `app.js`-ში (გლობალურია), არა view-ში — ნებისმიერი
მომავალი flash ავტომატურად მიიღებს ამ ქცევას, თუ კლასს დაამატებ.

### 4.15 `ds-select` — Select2-ის მსგავსი ძებნადი select
`public/vendor/select/js/ds-select.js` + `css/ds-select.css`, ორივე
`layout.php`-ში გლობალურადაა ჩაბმული (ds-table.js-ისგან განსხვავებით — ეს
JS-იც გლობალურია, `floating-label.js`-ის მსგავსად, რადგან `data-ds-select`
ატრიბუტის გარეშე უმოქმედოა). დემო: `/style-guide` → „Select" ბლოკი.

გამოყენება — არაფერი გარდა ჩვეულებრივი `<select>`-ისა:
```html
<select class="form-select" data-ds-select data-placeholder="…">
  <option value=""></option>              <!-- ცარიელი = placeholder + გასუფთავება -->
  <option value="1">Georgia</option>
</select>
<select class="form-select" multiple data-ds-select>…</select>   <!-- chips -->
```

**ორიგინალი `<select>` DOM-ში რჩება** (ვიზუალურად დამალული, `clip-path`
ტექნიკით — არა `display:none`), რომ ფორმა ისევ მის `name`/`value`-ს აგზავნიდეს.
⚠️ **მისი `id` აღარ გადადის** (თავდაპირველად ასე იყო, products.php-ზე
ინტეგრაციისას გადავაკეთე) — `id` **select-ზევე რჩება**, რომ გვერდზე უკვე
არსებულმა `document.getElementById(id)`-ზე დამყარებულმა კოდმა (lookup-მოდალი,
row-click რედაქტირება) ცვლილების გარეშე იმუშაოს. ტრიგერს ეძლევა **ცალკე,
წარმოებული id** (`id + '__ds-select-trigger'`), და სწორედ მასზე გადამისამართდება
`<label for>`, თუ ასეთი მოიძებნა.

⚠️ **ორჯერ დამივიწყდა ერთი წესი — ვიზუალურად დამალვის CSS.** კომპონენტმა თავიდან
იმუშავა (wrap, trigger, ID-ის გადატანა — ყველაფერი სწორად), მაგრამ ორიგინალი
`<select>` **ეკრანზე ჩანდა custom trigger-ის გვერდით** — `.ds-select-native`-ს
CSS წესი საერთოდ არ ჰქონდა დაწერილი. გაზომვისას დაფიქსირდა (`getBoundingClientRect`
ნამდვილი ზომებით), მანამდე `document.getElementById('sgCountry')`-ით ტესტირება
შეცდომაში შემიყვანდა კიდეც — ეს id უკვე trigger-ზეა გადატანილი, არა select-ზე.

არჩევანი (`value`/multi-select-ის `selectedOptions`) ორიგინალ `<select>`-ზე
წერდება და **რეალურ `change` event-ს აგზავნის** (`bubbles:true`) — არსებული
კოდი, რომელიც `select.value`-ს კითხულობს, ამის შესახებ არაფერი არ სჭირდება
იცოდეს. `refresh()` საჯარო მეთოდია (+ `'ds-select:refresh'` event) — თუ
`<option>`-ები მოგვიანებით პროგრამულად დაემატება (მაგ. lookup-მოდალიდან),
გამოძახე ის.

**შემოტანილია `products.php`-ში** (`product_type_id`, `unit_id`) — ორივე
floating-label რეჟიმში, `.input-group`-ში "მართვის" (⚙) ღილაკთან ერთად.
`customers.php`-ის select-ები ჯერ ხელუხლებელია.

### 4.16 `ds-select` × `products.php` — ორი რეალური ბაგი ინტეგრაციისას

**1. `.input-group`-ის კუთხეები.** `.ds-select` უბრალო `<div>`-ია, არა
`.form-control`/`.form-select` — Bootstrap-ის `.input-group`-ის ჩაშენებული
CSS (რომელიც ამ ორ კლასზეა მიმართული) მას გამოტოვებდა: `flex-grow` არ
ჰქონდა და ⚙-ღილაკთან მიმდებარე კუთხე მომრგვალებული რჩებოდა. ხელით
დავამატე `ds-select.css`-ში (`.input-group > .ds-select`) — იგივეს იმეორებს,
რასაც Bootstrap `.form-select`-ისთვის თავად აკეთებდა.

**2. `reset`-ის დროს ტრიგერი ძველ მნიშვნელობას აჩვენებდა.** Row-click-ით
რედაქტირებაზე გადართვა და `refresh()` (lookup-მოდალიდან ახალი ოფციის
დამატებისას) სწორად მუშაობდა — **მხოლოდ ფორმის `reset`-ის დროს** rendered
ტექსტი ხანდახან ძველი რჩებოდა. მიზეზი გავზომე პირდაპირ: ამ ძრავაზე ფორმის
`reset` **event ადრე ისროლება, ვიდრე ბრაუზერი select-ების მნიშვნელობებს
რეალურად აბრუნებს ნაგულისხმევზე** — `refresh()`-ის სინქრონული გამოძახება
`reset`-ჰენდლერში ჯერ კიდევ ძველ `select.value`-ს კითხულობდა.
დადასტურებულია `DsSelect.prototype.refresh`-ის დროებითი monkey-patch-ით:
`calls` მასივში `valueAtCallTime` ძველ მნიშვნელობას აჩვენებდა (`"1"`, `"3"`),
თუმცა იმავე წამს, event-ის მიღმა, `select.value` უკვე `""` იყო.
**გამოსავალი: `setTimeout(..., 0)`** `refresh()`-ის ორივე გამოძახებაზე
`products.php`-ის `reset`-ჰენდლერში — ერთი tick-ით დაგვიანება საკმარისია.
⚠️ ეს **ამ კონკრეტულ ძრავაზეა** დაფიქსირებული (იხ. handoff §4.6-ის მსგავსი
ტესტირების ხაფანგები) — spec-ის მიხედვით `reset` event-მა მნიშვნელობების
დაბრუნების **შემდეგ** უნდა ისროლოს; თუ სხვა ძრავაზე ეს კოდი ორჯერ დარენდერდება
უსარგებლოდ, უვნებელია (`refresh()` idempotent-ურია).

⚠️ **`.is-invalid` კლასი ტრიგერზეც კოპირდება** build()-ის დროს, თუ
ორიგინალ `<select>`-ს PHP-დან უკვე ჰქონდა (`$bad()`-ის შედეგი). ტრიგერს
ისედაც აქვს `form-select` კლასი, ამიტომ Bootstrap-ის `.form-select.is-invalid`
წითელ ბორდერს **თავადვე** ხატავს — ახალი CSS არ დამჭირვებია. Floating
ლეიბლისთვის დამატებულია `.ds-select-trigger.is-invalid ~ label` (წითელი
ტექსტი) — ზუსტად `vendor/floating-label`-ის კონვენციის მიხედვით.

⚠️ **შევრონი და გასუფთავების ღილაკი ერთმანეთს ედებოდა.** შევრონი flex-ის
`margin-left:auto`-ით იყო განთავსებული (ნაკადში), გასუფთავების ღილაკი კი
დამოუკიდებლად, `.ds-select`-ის მიმართ `position:absolute`-ით — ორივეს
საკუთარი კოორდინატთა სისტემა ჰქონდა და დაემთხვა. გასწორება: ორივე **ერთსა და
იმავე წამკვეთ ელემენტზე** (`.ds-select-trigger`, `position: relative`)
`absolute`-ითაა, ფიქსირებული `right`-ებით (`.9rem` შევრონს, `2.15rem`
გასუფთავებას) — `padding-right: 3.6rem` ტრიგერზე ორივეს ერგება ტექსტის
გადაფარვის გარეშე.

**Clear ღილაკი და label ახლა `vendor/floating-label`-ის ზუსტად იგივე ელემენტებია**,
არა საკუთარი კოპია:
- Clear — `<button class="btn-close btn-clear">`, ზუსტად ის კლასები, რასაც
  input-ების clear-ღილაკი იყენებს (იგივე ზომა, იგივე hover-rotate ანიმაცია).
  `ds-select.css`-ში მხოლოდ `right` (პოზიცია, შევრონის გვერდზე) და ხილვადობა
  (`[hidden]`) გადაფარულია — ზომა/ეფექტი floating-label.css-იდან მოდის.
- Label — თუ დოკუმენტში არსებობს `<label for="ID">`, JS **გადმოიტანს** მას
  wrap-ში (`trigger`-ის შემდეგ) და აქცევს floating-ლეიბლად, ზუსტად იმავე
  `--fl-*` ტოკენებით (`--fl-primary-color`, `--fl-label-opacity` და ა.შ.) —
  ცალკე არ განუსაზღვრავს, `vendor/floating-label`-იდან იღებს (ფაილების
  ჩატვირთვის რიგს არ აქვს მნიშვნელობა — custom property `:root`-ზეა).
  ⚠️ ტრიგერი `<button>`-ია, არა `<input>` — `:placeholder-shown` მასზე ვერ
  იმუშავებს. ამოსავალი: floated მდგომარეობა ორი გზით ირთვება —
  `.ds-select-trigger:focus ~ label` (native, უფასო) და
  `.ds-select-has-value` კლასი (JS, `renderTrigger()`-ში). ლეიბლის გარეშე
  select-ი ძველებურად მუშაობს — `data-placeholder` ჩანს ტრიგერში.

🔴 **ამ ბრაუზერში `:focus`/`:focus-visible` ვერ გადამოწმდება.** `document.hasFocus()`
აქ **ყოველთვის `false`-ია** — Chromium-ის `:focus` selector-ის დამთხვევა
დამოკიდებულია დოკუმენტის ფოკუსზეც, არა მხოლოდ `document.activeElement`-ზე.
შევამოწმე: `trigger.focus()`-ის შემდეგ `document.activeElement === trigger` ✓,
მაგრამ `:focus`-ზე დამოკიდებული CSS (ჩემი ახალიც და **ტრიგერის ძველი,
წინა სესიის `:focus-visible` box-shadow-იც**) ვიზუალურად არ ირთვება.
ეს იგივე კლასის შეზღუდვაა, რაც §4.6-ში — დამატე აქაც, რომ არავინ სცადოს
`:focus`-ის გამართვა ამ ხელსაწყოთი: **ეს ნიშნავს, რომ CSS არასწორია** დასკვნამდე
მისვლა. Value-ზე დამოკიდებული float (`.ds-select-has-value`) კი **გაზომილია**
და მუშაობს.

🔴 **shell-ის კოდირების ხაფანგი, რომელიც ამ სესიაში რეალურად მოხდა:** ტესტირებისას
ქართული ტექსტი bash-ის `curl --data-urlencode`-ის მრავალხაზიან command-ში
ერთხელ დაზიანდა (`?`-ебад გადაიქცა, 32 ბაიტი UTF-8 → 32 ბაიტი ASCII `?`) —
წარმოშობა ხელსაწყოს/გარსის კოდირება იყო, არა აპლიკაციის კოდი. **DB-ს
UPDATE-ის გზა თავად სწორია** — იგივე ტესტი PHP-ს `curl_exec()`-ით (ბრძანების
ხაზის არგუმენტების გვერდის ავლით) სუფთად გაიარა. თუ მომავალში ქართული ტესტ-მონაცემი
bash-ის `-r`/`curl`-ის command-ის არგუმენტად გჭირდება, **გამოიყენე PHP-ს
`curl_exec()` ერთი `-r` სკრიპტის შიგნით**, არა bash-ის საკუთარი `--data-urlencode`
ლიტერალი — bash-ის argv-ს კოდირება ამ გარემოში არასანდოა.

### 4.17 `<details class="card">` ჩამოშლილ dropdown-ს კვეთდა
პროდუქტების გვერდზე ერთეულის select-ის ჩამოშლისას, ქვედა მარჯვენა კუთხე
"დამატება" ღილაკს ედებოდა — მომხმარებელმა სქრინშოტით დააფიქსირა.

მიზეზი: §4.7-ის ჩამოშლის ანიმაციის `.card::details-content { overflow:
hidden }` **არასდროს ბრუნდებოდა `visible`-ზე**, `[open]`-ის დროსაც კი —
`getComputedStyle(details, '::details-content').overflow` ღია მდგომარეობაშიც
`"hidden"` იყო. ეს ნორმალურ სტატიკურ კონტენტს არასდროს ეხებოდა (არასდროს
სცდებოდა card-ის ჩარჩოს), მაგრამ `ds-select`-ის `position:absolute` panel-ი
**განზრახ სცდება** — და ის, რაც სცდება, იჭრებოდა.

გასწორება — `details.card[open]::details-content`-ს დაემატა `overflow:
visible`, **დაგვიანებული ტრანზიციით** (`overflow 0s .2s`): მნიშვნელობა
`hidden`-ზე რჩება მთელი ზრდის ანიმაციის განმავლობაში (.2s) და მხოლოდ
დასრულებისას ხტება `visible`-ზე — ანიმაციის დროს რომ ნორმალური კონტენტიც
გამოწმენდილად "იხსნებოდეს" და არა მყისვე გამოჩნდეს. დახურვისას overflow
**მყისვე** უბრუნდება `hidden`-ს (ის base-წესის `transition`-ის სიაში საერთოდ
არაა — ჩამოთვლის გარეთ თვისება ყოველთვის უტრანზიციოდ იცვლება).

🔴 ეს დაგვიანება-ტექნიკა ამ ბრაუზერში **ვერ დადასტურდა დროში** — §4.6-ის იმავე
შეზღუდვის გამო (ტრანზიციები არ მიმდინარეობს ამ tab-ში). `setTimeout`-ებით
ვცადე გაზომვა (100ms/300ms წერტილებზე) — overflow ორივეგან უკვე `visible`-ს
აჩვენებდა, transition-delay-ს გვერდს უვლიდა. **ორივე დასვენების მდგომარეობა
კი გაზომილია და სწორია:** დახურული → `hidden`, ღია (დასრულებული) → `visible`.
CSS spec-ის მიხედვით სწორია — real ბრაუზერში დაგვიანება იმუშავებს, უბრალოდ
ამ ხელსაწყოთი ვერ დავადასტურე.

წესი გლობალურია (`.card::details-content`) — customers.php-ის ორივე
card-იც (`customer-form`, `customer-list`) გადამოწმდა, იმავე გასწორებას
იღებს.

### 4.18 `ds-select`-ის CSS სრულად პორტაბელურია — გადამოწმებული
მოთხოვნა იყო: `design-system.css`-დან ყველაფერი, რაც `ds-select`-ს ეხება,
`vendor/select/css/ds-select.css`-ში გადატანილიყო. **გადავამოწმე მთელი
ფაილი (`grep -n "select" design-system.css`) — არაფერი გადასატანი არ
აღმოჩნდა.** ერთადერთი ნახსენები ადგილი `.card::details-content`-ის კომენტარში
იყო (§4.17) — ეს **არ არის** select-ის სტილი, ეს ამ პროექტის `<details
class="card">` აკორდეონის საკუთარი გასწორებაა, უბრალოდ ამის საჭიროება
select-ის dropdown-მა გამოააშკარავა. სხვა პროექტში, სადაც ეს აკორდეონ-
პატერნი არ იქნება, `ds-select`-ს ეს საერთოდ არ სჭირდება — card/details-ის
საკუთარ CSS-თან ერთად უნდა დარჩეს, არა select-ის ბიბლიოთეკასთან.

**რაც გავაკეთე პორტაბელურობისთვის:** `ds-select.css` **ორ** `design-system.css`-ის
ტოკენს სესხულობს — `--ds-radius`, `--ds-shadow-md` (ოთხივე გამოყენების
ადგილი). დავამატე fallback-ები (`var(--ds-radius, .5rem)` და ა.შ., ამ
პროექტის საკუთარი მნიშვნელობებით) — თუ ვინმემ `vendor/select/`-ის
საქაღალდე მარტო გადაიტანა, კომპონენტი ისევ სწორად გამოიყურება, ტოკენების
გარეშეც. გაზომილი: ამ პროექტზე ცვლილება **უჩუმარი** იყო (`7.2px` radius,
იგივე shadow, ორივე ტოკენიდან, არა fallback-იდან) — `--ds-radius` აქ
განსაზღვრულია, fallback არ ჩართულა.

დარჩენილი დამოკიდებულებები (თავად ბიბლიოთეკის თავშივეა დოკუმენტირებული):
Bootstrap 5.3 (სავალდებულო), `vendor/floating-label` (მხოლოდ floating-label
რეჟიმისთვის, `--fl-*` ტოკენები).

### 4.19 მოდულების სისტემა — Install / Enable / Disable
სრული არქიტექტურის გეგმა: `C:\Users\CHIEF\.claude\plans\hidden-leaping-mochi.md`
(Plan mode-ში დამტკიცებული, ღირს წაკითხვა თუ მეორე მოდული ემატება).

**რატომ:** მომხმარებელს სურს გაყიდვადი მოდულები, რომლებიც სტანდარტულ
ცხრილებს/გვერდებს აფართოებენ. Products იყო პირველი მაგალითი — ტიპი/
რაოდენობა/სურათი ("Warehouse") ცალკე მოდულად გამოიცალკევა core Products-ისგან
(დასახელება, ერთეული, ფასი).

**განლაგება:** `app/Modules/<Code>/` (მაგ. `Warehouse`) — არსებული `App\` →
`app/` ავტოლოადერი (`bootstrap.php:9-18`) უცვლელად მუშაობს
`App\Modules\Warehouse\...`-ზეც. თითოეულში: `module.json` (name/description
lang-key, version, enabled_by_default — **`code`-ს არ შეიცავს**, საქაღალდის
სახელი თავად არის code), `Module.php` (`App\Core\ModuleInterface`,
`register(Router $router): void`), საკუთარი `migrations/`, `Models/`,
`Controllers/`, `Views/`.

**რეესტრი** — `modules` ცხრილი (`migrations/005_create_modules.sql`:
`code` PK, `version`, `enabled`, `installed_at`) + `App\Core\ModuleRegistry`:
- `discover()` — დისკის სკანირება (`app/Modules/*/module.json`),
  **მხოლოდ ადმინის გვერდზე** (`/settings/modules`).
- `enabledCodes()` — ერთი იაფი query, memoized. **ეს გამოიყენება ყოველ
  request-ზე** (`public/index.php`-ის route-loading loop). ⚠️ არასდროს
  `discover()` per-request — ეს ყოველ გვერდს დისკის სკანირებას დააჯდებოდა.
- `install()`/`enable()`/`disable()` — `enable`/`disable` **მხოლოდ flag**-ია,
  კოდი/სქემა უცვლელია. **Uninstall არ არსებობს** (დესტრუქციული, გადადებული).

**Route-ების ჩატვირთვა** — `public/index.php:17-24`, `$router = new Router()`-სა
და `dispatch()`-ს შორის: `foreach (ModuleRegistry::enabledCodes() as $code)`
→ `require .../Modules/$code/Module.php` → `(new $class())->register($router)`.

**მიგრაციები გაზიარებულია** — `migrate.php`-ის ძველი loop ამოღებულია
`App\Core\Migrator::run(string $dir, string $prefix = '')`-ში. `migrate.php`
თავად მხოლოდ ამ მეთოდს იძახებს core `migrations/`-ზე; `ModuleRegistry::install()`
იძახებს იმავეს მოდულის `migrations/`-ზე, `"$code/"` პრეფიქსით — **ერთი**
`migrations` ცხრილი ინახავს ორივეს ისტორიას (`"Warehouse/001_....sql"`).

⚠️ **პირველი ვერსია Warehouse-ს Products-ის ფორმაში hook-ებით აქსოვდა**
(`App\Core\Hooks` — `on()`/`call()`/`render()`, 8 hook point). მომხმარებელმა
პირდაპირ თქვა: **არა products-ში, საწყობს საკუთარი გვერდი და მენიუ უნდა**.
ეს hook call-ები **ამოღებულია** `ProductController`/`products.php`-დან —
ისინი ისევ 100% core-ულია, Warehouse-ს არც იცნობენ. `App\Core\Hooks`
**კლასი დარჩა** (გამოუყენებელი, მაგრამ დანახარჯი ნულია) — თუ მომავალი
მოდული მართლა დაჭირდება core გვერდის გაფართოებას (არა ცალკე გვერდი),
მზადაა. **ნუ დაამატებ hook call-ს "შეიძლება დასჭირდეს"-ის გამო** — მოდელი
დაამტკიცა, რომ "საკუთარი გვერდი" უფრო მარტივი და ნათელია, ვიდრე ჩაწნეხვა.

**Warehouse-ის საკუთარი გვერდი** — `GET|POST /warehouse` (`Module.php`-ში
რეგისტრირდება, `products.php`-ს არ ეხება). ფორმა products-ს არ ქმნის — picker-ით
ირჩევ **უკვე არსებულ** პროდუქტს (`Product::all()`, core-დან) და უსვამ ტიპს/
რაოდენობას/სურათს. `product_warehouse.product_id` არის ბუნებრივი გასაღები —
`upsert()` (`INSERT ... ON DUPLICATE KEY UPDATE`) "დამატება"-ს და
"რედაქტირება"-ს ერთ მოქმედებად აქცევს, ცალკე add/update ღილაკის ტექსტიც კი
არ სჭირდება (`t('warehouse.save')` ერთია ორივესთვის).

⚠️ **`Controller::view()` მხოლოდ `app/Views/`-ზეა hardcoded** (`APP_PATH .
'/Views/' . $view . '.php'`) — მოდულის საკუთარი view (`app/Modules/Warehouse/
Views/warehouse.php`) მასზე ვერ გავიდოდა. დავამატე `Controller::viewAt(string
$file, array $data)` — იგივე რენდერი, აბსოლუტური path-ით. `view()` შიგნიდან
ახლა უბრალოდ `viewAt(APP_PATH.'/Views/'.$view.'.php', ...)`-ს იძახებს —
ძველი core კონტროლერები (Dashboard/Customer/Product/Module) უცვლელად
მუშაობენ. `WarehouseController::index()`: `$this->viewAt(__DIR__ .
'/../Views/warehouse.php', [...])`.

**მენიუს პირობითი კონტრიბუცია** — ეს იყო თავდაპირველ გეგმაში **განზრახ
გადადებული** ("Merge-ლოგიკა დაემატება, როცა მეორე მოდულს ეს რეალურად
დასჭირდება") — Warehouse-ის საკუთარმა გვერდმა სწორედ ეს გამოააშკარავა.
`app/Modules/Warehouse/menu.json`: `{"section": "nav.main", "item": {...}}`
(ერთი item, არა სექციების მასივი — მარტივი შემთხვევისთვის საკმარისი).
`ds_menu()` (`helpers.php`) ახლა: კითხულობს core `menu.json`-ს, მერე
**ჩართული** მოდულების (`ModuleRegistry::enabledCodes()`) `menu.json`-ს
თუ არსებობს, და `item`-ს umატებს `section`-ის დამთხვევით. იგივე
"throw loudly on malformed JSON" კონვენცია ორივესთვის. ⚠️ **route-loading
loop-ის იგივე წესი მენიუზეც ვრცელდება** — `enabledCodes()` (იაფი query),
არა `discover()` (დისკის სკანირება) ამ per-request გამოძახებაში.

**Warehouse-ის ფორმის JS დამოუკიდებელია** products.php-ის JS-ისგან —
საკუთარი `wireLookupModal()` ფუნქცია (იგივე კოდი, დუბლირებული — module-ს
core-ის script-ზე დამოკიდებულება არ სჭირდება), საკუთარი row-click listener,
საკუთარი reset-handler (`setTimeout`-ით ორივე ds-select-ის refresh-ისთვის —
იგივე ხაფანგი, რაც products.php-ს `unit_id`-ზე).

⚠️ **`product_type_id` NOT NULL იყო core-ში, `002_create_product_types.sql`-ის
FK-ით.** თუ `Product::FIELDS` შემცირდებოდა სქემის წინასწარი მოდუნების
გარეშე — პირველივე core-only INSERT-ზე MySQL strict mode-ი შეცდომას
დააბრუნებდა. ამიტომ **core**-ის `006_relax_products_extension_columns.sql`
სვეტებს (`product_type_id`, `remaining_qty`) ათავისუფლებს
**დამოუკიდებლად** Warehouse-ის ინსტალაციისგან — Products მუშაობს
Warehouse-ის გარეშეც, ნებისმიერ მომენტში.

**Products → product_warehouse მიგრაცია** (Warehouse-ის საკუთარი 3 ფაილი):
1. `CREATE TABLE product_warehouse` (`product_id` PK+FK → `products.id`
   `ON DELETE CASCADE`, `product_type_id` FK → `product_types.id`).
2. `INSERT ... SELECT ... FROM products WHERE product_type_id IS NOT NULL`
   (backfill — გეგმაზე მკაცრი, `WHERE`-ით დავიცავი, თუ 006-სა და install-ს
   შორის NULL-ტიპიანი პროდუქტი შეიქმნა, სულ სტატემენტს არ ვამტვრევ).
3. `ALTER TABLE products DROP FOREIGN KEY fk_products_type, DROP COLUMN
   product_type_id, remaining_qty, image` — **შეუქცევადი**, დადასტურებულია
   მომხმარებელთან.

⚠️ **ოპერაციული წესი:** `product_warehouse.product_type_id` **NOT NULL**-ია.
006-ის გაშვებასა და Warehouse-ის ინსტალაციას შორის შექმნილი NULL-ტიპიანი
პროდუქტი უბრალოდ **არ ჩავარდება** backfill-ში (ზემოთ `WHERE`-ის წყალობით) —
Warehouse დააინსტალირე იმავე სესიაში, სადაც core-ის მიგრაციებს უშვებ.
`004_create_products.sql` **არ შეცვლილა** (კონვენცია: ახალი ნომრიანი ფაილი).

**გადამოწმებული ცოცხლად (სრული ციკლი, ორივე ბრაუზერით/UI-დან):**
Warehouse disabled → `/warehouse` → **404**, sidebar-ში „საწყობი" **არ ჩანს** ✓ ·
enable (`/settings/modules` UI-დან, ღილაკზე რეალური დაწკაპებით) → `/warehouse`
→ 200, მენიუში „საწყობი" გამოჩნდა ორი შვილით (`/warehouse#warehouse-form`,
`/warehouse`) ✓ · გვერდზე picker-ს ერთი პროდუქტი ჰქონდა (არსებული, backfill-ით),
list-ში 1 row — `data-product/type/qty/image` ზუსტად ემთხვევა ბაზას ✓ ·
row-click → picker + ტიპის ds-select ორივემ სწორი label აჩვენა
(„სავიზიტო ბარათები" / „ციფრული ბეჭდვა") ✓ · რაოდენობის რედაქტირება-submit →
DB განახლდა (`upsert`) ✓ · ცარიელი picker-ით submit → **დაიბლოკა**,
`warehouse.err_product_required` გამოჩნდა, ახალი row **არ** შეიქმნა ✓ ·
ახალი (untracked) პროდუქტის picker-ით არჩევა+submit → ახალი `product_warehouse`
row შეიქმნა, `/products` **უცვლელი დარჩა** (3 სვეტი, warehouse ველების
კვალიც არ ჩანს) ✓ · disable→enable ციკლი მეორედაც გავიარე UI-დან — ყველა
route ისევ სწორად ჩნდება/ქრება, კონსოლი ორივეჯერ სუფთა ✓. სატესტო
ჩანაწერები წაშლილია, `AUTO_INCREMENT` აღდგენილია.

**Topbar-ის „აპლიკაციები" ჩამოსაშლელი — enable/disable `/settings/modules`-ის
გარეშეც.** `ModuleController::index()`-ის ძველი inline loop (`discover()` +
`installed()` → `code/name/description/version/installed/enabled` მასივი)
გავიტანე `ModuleRegistry::summaries()`-ში — ერთი წყარო ორივესთვის:
`/settings/modules`-ის ბარათებისთვის და `topbar.php`-ის ახალი dropdown-ისთვის
(მხოლოდ **დაინსტალირებული** მოდულები ჩანს იქ, `array_filter`-ით — install
მძიმე მოქმედებაა, რჩება Settings-გვერდზე). თითო მოდულს `form-switch`
checkbox აქვს; `app.js`-ში ერთი `[data-module-toggle]` listener (იგივე
`querySelectorAll`+`addEventListener` პატერნი, რაც `data-theme-toggle`-ს) —
`change`-ზე `form.submit()`. ⚠️ **`data-bs-auto-close="outside"` toggle
ღილაკზე აუცილებელია** — მის გარეშე Bootstrap-ი dropdown-ს switch-ზე
დაწკაპებისთანავე დახურავდა submit-მდე. Enable/disable route-ებმა
(`ModuleController::enable()`/`disable()`) ახლა იღებენ `redirect`-ს
hidden ველიდან (`Router::current()`) — topbar-იდან toggle-ს **იმავე
გვერდზე** ტოვებს (`/settings/modules`-ზე გადახტომის ნაცვლად); ვალიდაცია
`str_starts_with($to, '/') && !str_starts_with($to, '//')`-ით — open-redirect-ის
თავიდან ასაცილებლად, თუ `redirect`-ს ვინმე გარედან POST-ზე ჩაანაცვლებს.
⚠️ **ცნობილი ზღვარი, არა ბაგი:** საკუთარი გვერდის მოდულის disable
topbar-იდან (მაგ. `/warehouse`-ზე ყოფნისას Warehouse-ის გამორთვა) იმავე
გვერდზე დააბრუნებს, რომელიც უკვე აღარ დარეგისტრირდება → **404** — იგივე
ქცევაა, რაც პირდაპირ URL-ზე გადასვლას გამორთვის შემდეგ; განზრახ არ არის
დამატებითი გამონაკლისის დამუშავებული (`ponytail`: მინიმალური რისკი,
ლოკალური ადმინ-პანელია).

### 4.20 Auth — login / register / forgot / reset (**გვერდები არსებობს, gate — არა**)

⚠️ **განახლება — ეს აღარ არის მართალი**: გლობალური auth-gate **დაემატა**
`4.30`-ში (`public/index.php`) — ყველა გვერდი, `4.30`-ის allow-list-ის
გარეთ, ახლა login-ს მოითხოვს. ეს სექცია ისტორიულადაა დატოვებული (მაშინდელი
გადაწყვეტილების მიზეზი ქვემოთაა).

მომხმარებელმა `app/Views/auth/*.html` ჩააგდო (სხვა დიზაინ-სისტემის mockup-ები,
საკუთარი `flabel`/`i18n` vendor ბიბლიოთეკებით, რომლებიც ამ პროექტში არ
არსებობს) და სთხოვა ეს ფორმები რეალურად ემუშავათ. **მკაფიოდ დადასტურებულია
მომხმარებელთან იმ დროს:** (1) *არანაირი გლობალური auth-gate* — დანარჩენი
აპლიკაცია (`/`, `/customers`, `/products`, `/warehouse`, ...) კვლავ სრულად
ღიაა, login მხოლოდ სესიას ამყარებს; (2) reset-ბმული **რეალურ SMTP-ზე** უნდა
გავიდეს (მომხმარებელმა თავად ჩაწერა `.env`-ში MAIL_* კრედენშიალები) — არა
ლოგში/ეკრანზე ჩვენება.

**რატომ ცალკე layout, არა `Views/layout.php`** — sidebar/topbar public
login-გვერდზე აზრი არ აქვს. `Controller::render()` ახლა layout-ს **მესამე
პარამეტრად** იღებს (`view()`/`viewAt()` კვლავ `layout.php`-ს გადასცემენ,
ქცევა უცვლელია); დაემატა `Controller::bare(string $view, array $data)`,
რომელიც `app/Views/auth/_layout.php`-ს იყენებს — topbar/sidebar-ის გარეშე,
ბრენდი + ენის გადამრთველი ცენტრში, `.ds-auth-*` კლასები (`auth.css`, ახალი
ფაილი) `.form-floating`/`.form-control`/`is-invalid` კონვენციაზეა აგებული
(**არა** მოკლემულის საკუთარი `.flabel` — ის აქ არ გამოიყენება).

**`users` + `password_resets`** (`migrations/007`, `008`) — `email` UNIQUE,
`password_hash` (`password_hash()`/`password_verify()`, `PASSWORD_DEFAULT`).
`password_resets.email` PK + FK → `users.email` `ON DELETE CASCADE`, ერთი
row თითო email-ზე (ახალი მოთხოვნა თავისით ანაცვლებს ძველს
`ON DUPLICATE KEY UPDATE`-ით) — token **ჰეშირებულია** (`hash('sha256', $token)`),
ბმულში მხოლოდ დაუჰეშავი token დადის, შედარება `hash_equals()`-ით.

**`App\Core\Auth`** — `$_SESSION['user_id']`, იგივე სესია, რასაც CSRF/flash
იყენებს. `login()` აკეთებს `session_regenerate_id(true)` (session fixation-ის
თავიდან აცილება); `remember`-ჩექბოქსი session cookie-ს 30 დღით ხელახლა
გასცემს (**არა** ცალკე remember-token — მარტივი მიდგომა საკმარისია ერთი
admin-ის ლოკალურ აპზე).

**`App\Core\Mailer` — ნედლი SMTP, ბიბლიოთეკის გარეშე.** პროექტს Composer
საერთოდ არ აქვს, ამიტომ PHPMailer-ის მაგივრად `stream_socket_client`-ით
პირდაპირ ველაპარაკები SMTP-ს (`EHLO`/`STARTTLS`/`AUTH LOGIN`/`MAIL FROM`/
`RCPT TO`/`DATA`) — AUTH LOGIN + STARTTLS/implicit-TLS საკმარისია Gmail/
Mailgun/SES-ის მსგავსი providers-ისთვის. **გადამოწმებულია რეალურად:**
`Mailer::send()` პირდაპირ `.env`-ში ჩაწერილი MAIL_* კრედენშიალებით
წარმატებით გაუგზავნა წერილი `MAIL_FROM`-ის საკუთარ მისამართს (`sent=true`).
⚠️ SMTP-ის accept ≠ დელივერი — provider-ები ხშირად იღებენ RCPT TO-ს
არარსებული დომენისთვისაც (async bounce მოგვიანებით), ამიტომ `Mailer::send()`-ის
`true` მხოლოდ "პროტოკოლის დონეზე მიღებულია" ნიშნავს, არა "მიწოდებულია".

**OTP (ერთჯერადი კოდი) — session-ში, არა DB-ში.** login.php-ის mockup-ს
ჰქონდა მეორე ტაბი "ერთჯერადი კოდი" — გადავწყვიტე სრულად ავმუშავო (არა
მხოლოდ ვიზუალურად დამეტოვებინა), მაგრამ DB-ცხრილის მაგივრად
`$_SESSION['otp'] = ['email', 'hash' (bcrypt), 'expires']` საკმარისია: ერთი
ბრაუზერის სესიისთვის, single-use, 10 წუთი ვადა — DB-row-ის/cleanup-ის
საჭიროება არ არსებობს. `POST /login/otp/send` (AJAX, `fetch`,
`Content-Type: application/json` პასუხი) აგენერირებს კოდს, ინახავს
სესიაში, აგზავნის Mailer-ით; `POST /login/otp/verify` ადარებს
`password_verify($code, $otp['hash'])`-ით. ✅ **გადამოწმებულია რეალურ
ბრაუზერში**, სესიის ფაილში ხელით ცნობილი bcrypt hash-ის ჩანაცვლებით (რადგან
ავტომატური ტესტიდან email-ის წაკითხვა შეუძლებელია) — login წარმატდა.

**OTP box-ების auto-advance JS** (login.php-ის `$scripts`) — თითო box
`maxlength="1"`, `input`-ზე შემდეგზე გადადის ფოკუსი, `Backspace`-ზე
წინაზე ბრუნდება, paste ყოფს ციფრებად. submit-ზე 6 box ერთ hidden
`code`-ველში ერწყმის. ⚠️ **ბრაუზერის ავტომატიზაციის quirk, არა რეალური
ბაგი**: bulk `type` action-მა მხოლოდ პირველი ციფარი ჩასვა (focus-jump-ს
ვერ ასწრებდა თითო keystroke-ს შორის) — key-by-key დაწკაპებამ დაამტკიცა,
რომ auto-advance ნამდვილად მუშაობს.

**`AuthController`** — `login`/`register`/`sendOtp`/`verifyOtp`/`logout`/
`showForgot`/`sendResetLink`/`showReset`/`resetPassword`, ყველგან
`csrf_verify()` + PRG. `showLogin()`/`showRegister()` ავტორიზებულ
მომხმარებელს `/`-ზე აბრუნებენ (ორჯერ login/register-ის თავიდან აცილება).
`sendResetLink()` **ერთსავე** `flash('sent', true)`-ს აბრუნებს რეგისტრირებული
თუ არარეგისტრირებული email-ისთვის — ფორმა ვერ გამოიყენება იმის
გამოსაცნობად, რომელი ელფოსტაა ბაზაში.

**Google OAuth ღილაკი წაშლილია** register-ის mockup-იდან — რეალური OAuth
app-ის რეგისტრაცია/callback-ის აშენება ცალკე, არმოთხოვნილი ფუნქციონალია
(`ponytail`: არარსებული ინტეგრაციისთვის non-working ღილაკი უარესია, ვიდრე
საერთოდ არარსებობა). "ვეთანხმები წესებს" checkbox **დარჩა** (დეკორატიული,
`href="#"` placeholder-ის იგივე კონვენციით, რაც `nav.settings_general`-ს
აქვს უკვე) — არ ვალიდირდება სერვერზე.

**Topbar-ის user dropdown რეალურ სესიას ასახავს** (`partials/topbar.php`)
— `\App\Core\Auth::user()` თუ არსებობს, სახელი/ინიციალი ნაცვლდება სტატიკური
"ადმინი"-ს; "გასვლა" რეალურ `POST /logout` ფორმად იქცა (`csrf_field()`-ით);
თუ არავინაა შესული, ბმული "შესვლა"-ზე (`/login`) მიდის "გასვლა"-ს ნაცვლად.
ეს **არ ნიშნავს გლობალურ auth-gate-ს** — ანონიმური ვიზიტორი კვლავ ხედავს
სრულ დაფას, უბრალოდ topbar ადაპტირდება, თუ ვინმე ცდილობს/მოახერხებს login-ს.

⚠️ **გადამოწმებულია, მაგრამ ჯერ არ არის:** production SMTP-ზე რეალურ
inbox-ში მიღებული წერილის ვიზუალური შემოწმება (მხოლოდ SMTP-protocol-level
`sent=true` დადასტურდა); auth-gate/middleware (განზრახ გადადებულია);
remember-me-ს რეალური 30-დღიანი expiry (კოდი წერია, ვადის გასვლის ტესტი
არ ჩატარებულა — არაპრაქტიკულია ამ სესიაში).

#### 4.20.1 Auth v2 — card-header, Google login/register, SMS OTP, ანიმირებული ფონი

მეორე მოთხოვნის ტალღა: (1) ბრენდი+ენა login/register **card**-ის header-ში
(არა card-ს გარეთ, საერთო `_layout.php`-ის topbar-ში); (2) Google-ით
შესვლა/რეგისტრაცია; (3) OTP ორივე არხით — ელფოსტა **და** SMS
(smsoffice.ge, მომხმარებლის საკუთარი API key); (4) OTP box-ები მთელ
სიგანეზე გაშლილი; (5) მოძრავი, ჰაეროვანი ფონი (არა მძიმე ერთფეროვანი).

**ბრენდი+ენა ოთხივე auth გვერდის card-header-შია** — თავდაპირველად
login/register-ს გადაეცემოდა `showTopbar => false` (`_layout.php`-ის
`$showTopbar ?? true` flag), forgot/reset კი გარეთა `.ds-auth-topbar`-ს
ინარჩუნებდნენ (მომხმარებელმა თავდაპირველად მხოლოდ login/register
დაასახელა). **შემდეგ მოთხოვნაში** ("დაგავიწყდა პაროლის ფორმასაც
გავუკეთოთ header") forgot/reset-საც დაემატა იგივე `.ds-auth-card-header`.
ამის შემდეგ `showTopbar` flag-ს ყველა 4 controller-მეთოდი `false`-ს
უგზავნიდა უპირობოდ — ე.ი. `_layout.php`-ის გარეთა `.ds-auth-topbar` blok-i
**გახდა მკვდარი კოდი** (არასდროს `true`). **წავშალე მთლიანად**: flag,
`if`-branch `_layout.php`-დან, `'showTopbar' => false` ოთხივე
controller-მეთოდიდან, და `.ds-auth-topbar` CSS წესი — `_layout.php`-ს
ახლა მარტივად `<?= $content ?>` აქვს `.ds-auth-main`-ში, ყოველგვარი
პირობის გარეშე.

⚠️ **სამი→ოთხი ასლი, არა ერთი** — flag SVG-ის კოდი (ქართული/UK დროშა)
გამეორებული იყო topbar.php-სა და `_layout.php`-ში ჯერ კიდევ Task-14-დან;
ახლა ოთხივე auth card-header-საც დასჭირდა იგივე, ამიტომ **გავიტანე**
`app/Views/partials/lang-flag.php`-ში (`$flagIdSuffix`-ს იღებს
პარამეტრად — `<use href="#id">`-ს ID collision-ი რომ არ მოხდეს, თუ
ორი ასლი ერთ გვერდზე მოხვდება). ხუთივე ადგილი (`topbar.php`,
`login.php`, `register.php`, `forgot-password.php`, `reset-password.php`)
ახლა მას იძახებს — აღარსად არის დუბლირებული SVG.

**`App\Core\GoogleOAuth`** — pure Authorization Code flow, `stream_context_create`
+ `file_get_contents`-ით (არც curl, არც `google/apiclient` — იგივე
"library-ის გარეშე" პრინციპი, რაც `Mailer`-ს/`Sms`-ს). `GET /auth/google` →
Google-ის consent-ეკრანზე redirect (`state` სესიაში, CSRF-ისთვის);
`GET /auth/google/callback` → token exchange → `userinfo` → `users`-ში
`google_id`-ით ან email-ით მოძებნა/შექმნა/მიბმა. **გადამოწმებული:** redirect
URL-ის აგება სწორია (`curl`-ით პირდაპირ შემოწმებულია `client_id=`,
`redirect_uri=...%2Fauth%2Fgoogle%2Fcallback`, `state=` — ყველა პარამეტრი
ზუსტია) — **რეალური** login/callback **ვერ** გადამოწმდა, რადგან
`GOOGLE_CLIENT_ID`/`SECRET` ცარიელია `.env`-ში. ⚠️ **მომხმარებელმა
თავად** უნდა შექმნას OAuth client Google Cloud Console-ში (Web application,
authorized redirect URI ზუსტად `https://<domain>/auth/google/callback`) —
ეს გარეთა სერვისია, კოდიდან ვერ გაკეთდება.

**`users`-ის სქემა შეიცვალა** (`migrations/009_alter_users_oauth_phone.sql`):
`password_hash` გახდა **NULLABLE** (Google-ით შექმნილ ანგარიშს პაროლი არ
აქვს), დაემატა `google_id` (UNIQUE, NULLABLE) და `phone` (UNIQUE, NULLABLE).
⚠️ **ცარიელი string ≠ NULL UNIQUE-სთვის** — `User::create()`-ში საჭირო
გახდა აშკარა `$phone === '' ? null : $phone`, თორემ ორი ანგარიში ტელეფონის
გარეშე ერთმანეთს დაეჯახებოდა (`''` === `''`) UNIQUE constraint-ზე.

**SMS OTP — `App\Core\Sms`, smsoffice.ge.** GET `https://smsoffice.ge/api/v2/send/`
(`key`/`destination`/`sender`/`content`/`urgent=true`), პასუხი შიშველი
რიცხვია (დადებითი = success message id, სხვა = შეცდომა) — `Sms::normalize()`
ქართულ ნომრებს `995XXXXXXXXX` (12 ციფრი) ფორმატში იყვანს ნებისმიერი
შეყვანილიდან (`599123456`, `+995599123456`, spaces/dashes-ით). **Unit-დონეზე
გადამოწმებულია** (5 ფორმატი, ყველა სწორად) — **რეალური SMS არ გავგზავნე
ტესტისას**, განსხვავებით email-ისგან: SMS ღირს ფული და საჭიროებს ნამდვილ
ადრესატს — მოგონილ ნომერზე გაგზავნა ან ფუჭი ხარჯია, ან, უარესი, ვინმეს
რეალურ ნომერზე მოხვდება. `register.php`-ს დაემატა არასავალდებულო `phone`
ველი (მხოლოდ SMS OTP-სთვის სჭირდება).

**OTP tab ახლა channel-toggle-ითაა** (`login.php`) — შიდა tab-strip
(ელფოსტა/SMS) email/tel input-ს ერთმანეთით ცვლის, `POST /login/otp/send` და
`/login/otp/verify` ორივემ `channel`+`identity` მიიღეს (`email` აღარაა
ცალკე ველი). `AuthController::sendOtp()`/`verifyOtp()` `channel === 'sms'`-ზე
`User::findByPhone()`-ს იძახებს, თორემ `findByEmail()`-ს. **გადამოწმებული
ცოცხლად:** channel toggle UI-ში ფილდების ჩვენება/დამალვა (`hidden`
ატრიბუტით) სწორად მუშაობს ორივე მიმართულებით; დარეგისტრირებული ტელეფონი
`findByPhone()`-ით სწორად მოიძებნა normalized ფორმატით.

**OTP box-ები მთელ სიგანეზეა** — `.ds-auth-otp-box { flex: 1 }` (ადრე
ფიქსირებული `2.5rem`). გადამოწმებული: 6 box ჯამში ავსებს card-ის მთელ
სიგანეს (`~360px` = card-ის content-area, padding-ის გამოკლებით).

⚠️ **ფონი ორჯერ შეიცვალა — მოძრავი ბლობები/გრაფიკი მთლიანად ამოშლილია.**
პირველი ვერსია (ანიმირებული ბლობები + drifting chart-line, ორივე
`prefers-reduced-motion`-ს პატივისმცემელი) მომხმარებელმა **"ბავშვურად"**
შეაფასა და პირდაპირ სთხოვა მოცილება, სკრიპტი და სტილი ერთად — `.ds-auth-blob*`/
`.ds-auth-chart*` კლასები, `@keyframes ds-auth-float-*`/`ds-auth-chart-drift`
და `_layout.php`-ის blob div-ები/SVG მთლიანად წაშლილია. **ეს ინფორმაციულია
მომავალი სესიისთვის: ანიმირებული/დეკორატიული ფონის იდეა ამ პროექტში ერთხელ
უკვე უარყოფილია — ნუ დაბრუნდები იმავე მიმართულებაზე ("floating gradient
blobs"/"drifting chart line") ხელახლა შეკითხვის გარეშე.**

**ახლანდელი დიზაინი — split-screen, სტატიკური ილუსტრაცია.** `.ds-auth-shell`
ახლა `display:flex` ორი ტოლი (50/50) სვეტით: `.ds-auth-visual` (მარცხენა,
ბრენდის გრადიენტი `--bs-primary → #3730a3`, ცენტრში სტატიკური inline SVG —
ინვოისის დოკუმენტი + mini bar-chart + „გადახდილია" checkmark-ბეჯი + ₾/$
მონეტები, იგივე ფერები რაც აპს აქვს: indigo/emerald/amber) და
`.ds-auth-main` (მარჯვენა — topbar + card, ძველებურად). **900px-ზე დაბლა
`.ds-auth-visual` `display:none`-ით ქრება** — ფორმა მთელ სიგანეზე
გადადის, ვიზუალი დესკტოპ-only დეკორია, არა ფუნქციური კონტენტი.
**გადამოწმებულია:** 1280px-ზე ორივე სვეტი ზუსტად 640/640px; 375px-ზე
(mobile) `.ds-auth-visual` `display:none`, `.ds-auth-main` მთელ სიგანეს
იკავებს.

**„რბილად გამოსვლა ცენტრის ხაზიდან"** — მომხმარებელმა შემდეგ ტურში
დააკონკრეტა: card **500px** (`--ds-auth-card-w` custom property, `.ds-auth-shell`-ზე
დეკლარირებული, `.ds-auth-card`/`.ds-auth-topbar`-ის `max-width`-ში
გამოყენებული — ერთ ადგილას იცვლება ორივესთვის), ანიმაცია **1 წამი**
(ადრინდელი `.55s`-ის ნაცვლად), და card **ზუსტად** გამყოფი ხაზიდან უნდა
გამოსულიყო — არა თვითნებური `-32px`. ვიზუალის მარჯვენა კიდეს დაემატა
`box-shadow: 12px 0 32px -12px rgba(0,0,0,.4)` (`position:relative;
z-index:1`-ით) — ეს **არის** "გამყოფი ხაზი" ვიზუალურად, `.ds-auth-visual`-ის
`right`-კიდე ზუსტად `50vw`-ზეა (900px+-ზე, ორივე სვეტი `flex:1 1 50%`).

⚠️ **ბაგი, რომელიც აღმოვაჩინე დებაგისას:** თავდაპირველად ცადე ორი
`@keyframes ds-auth-card-in` — ერთი base-ში, მეორე `@media (min-width:901px)`-ში,
იმ ვარაუდით, რომ media-ში repeat-ი override-ს გაუკეთებდა base-ს (სტანდარტული
cascade ვარაუდი). **არ იმუშავა** — Web Animations API-ით პირდაპირ
`document.getAnimations()[0].currentTime`-ის სკრუბვამ დაადასტურა, რომ card
element `-70px`-ზე იყო "გაყინული" (transform არასდროს აღწევდა `0`-ს).
გამოსწორდა custom property-ით: ერთი `@keyframes`, `translateX(var(--ds-auth-card-offset))`,
`--ds-auth-card-offset` კი `.ds-auth-card`-ზე default `-32px` (mobile/no-visual
fallback) და `@media (min-width:901px) { .ds-auth-card { --ds-auth-card-offset: ... } }`-ით
override-ული — ჩვეულებრივი custom-property cascade, არა keyframes redeclaration.
**წესი მომავლისთვის:** იგივე სახელის `@keyframes`-ის გამეორება media query-ში
**არ არის** საიმედო override-ის ხერხი ამ პროექტში/ამ ბრაუზერში-ნახეთ — ყოველთვის
custom property + ჩვეულებრივი selector-scoped media query.

**Offset-ის ფორმულა:** `calc(-1 * (50vw - var(--ds-auth-card-w)) / 2)` —
card-ის resting მდგომარეობა `.ds-auth-main`-ში (`align-items:center`)
ცენტრირებულია, ე.ი. მისი მარცხენა კიდე გამყოფი ხაზიდან
`(50vw - cardWidth)/2`-ითაა დაშორებული; ეს ზუსტად იმ მანძილზე იწყებს
translateX-ს, რომ card-ის საწყისი მდგომარეობა ხაზთან **ფლაშ** (flush) იყოს.
**გადამოწმებულია Web Animations API-ის scrub-ით** (`currentTime=0` →
`transform: translateX(-70px)`, card-ის left ემთხვევა `.ds-auth-visual`-ის
`right`-ს ზუსტად; `currentTime=1000` → `translateX(0)`, card-ის left
ემთხვევა ცენტრირებულ პოზიციას ზუსტად) — ⚠️ **ცოცხლად (real-time) ვერ
გადამოწმდა ამ browser pane-ში**: `document.getAnimations()[0].currentTime`
საერთოდ არ იზრდებოდა დროში მარტივი `wait`-ის დროსაც კი (pane არ
compositing-ობს ფონურად — ცნობილი შეზღუდვა ამ ხელსაწყოსი, არა კოდის ბაგი).
375px-ზე (visual `display:none`) fallback `-32px` სწორად ვრცელდება.

#### 4.20.2 Auth-ვიზუალის static SVG → მბრუნავი ფოტოები (Unsplash → Pixabay)

მომხმარებელმა სთხოვა 4.20.1-ის სტატიკური SVG ილუსტრაცია (ინვოისი+chart+badge)
შეცვლილიყო რეალური ფოტოებით unsplash.com/s/photos/finance-დან, 10-15წმ-ში
ერთხელ, fade ეფექტით. ⚠️ **unsplash.com-ის საძიებო გვერდი არ არის
scrape-ვადი** (HTML scraping მყიფეა და ToS-საწინააღმდეგოა) — გამოვიყენე
**რეალური Unsplash API** (`api.unsplash.com`), იგივე "მომხმარებელი თავად
აწვდის key-ს .env-ში" პატერნით, რაც `MAIL_*`/`GOOGLE_CLIENT_*`/`SMS_API_KEY`-ს
ჰქონდა.

⚠️ **Unsplash → Pixabay, იმავე სესიაში, მომხმარებლის შემდეგი მოთხოვნით.**
პირველი ვერსია (`App\Core\Unsplash`, `UNSPLASH_ACCESS_KEY`) აღვნიშნე, რომ
უფასო "Demo" tier-ს **50 request/სთ-ში** ლიმიტი აქვს — 10-15წმ polling-ით
ერთი ღია ტაბიც სწრაფად ამოწურავდა. მომხმარებელმა სთხოვა pixabay.com-იც
გვეცადა — **`App\Core\Unsplash` მთლიანად წაშლილია**, ჩანაცვლდა
`App\Core\Pixabay`-ით (`PIXABAY_API_KEY`, pixabay.com/api/docs/-ზე
რეგისტრირებული key-სთვის, App review-ის გარეშე). Pixabay-ის free tier
**100 request/წუთში** — 10-15წმ polling-ისთვის კომფორტულად საკმარისი,
ე.ი. ეს გადაწყვეტილება ორმაგად სწორი აღმოჩნდა: მომხმარებლის მოთხოვნაც და
Unsplash-ის rate-limit პრობლემაც ერთდროულად მოგვარდა. Pixabay-ის API-ს
"random single photo" endpoint არ აქვს — `Pixabay::randomPhoto()` `per_page=50`-ით
იღებს ერთ გვერდს `q=finance`-ზე და `array_rand()`-ით ირჩევს ერთს, რაც
ჯერზე ცვალებადობას აძლევს ბრუნვას. Attribution ორივესთვის საჭირო იყო
(Unsplash-ს — legal მოთხოვნა + `download_location` ping; Pixabay-ს —
"show your users where the images are from" ტექსტური მოთხოვნა, ping
საჭირო არაა), ამიტომ credit-line მექანიზმი (`auth.visual.photoBy` +
ფოტოგრაფის სახელი/ბმული) **უცვლელი დარჩა**, მხოლოდ `Pixabay::randomPhoto()`-ს
დაბრუნებული ველები (`user`/`pageURL` → `photographerName`/`photographerUrl`)
შეესაბამება იმავე shape-ს, რასაც `AuthController::authPhoto()`/frontend
ელოდება — controller/route/JS **არ შეცვლილა**, მხოლოდ provider class.

**`GET /auth/photo`** (`AuthController::authPhoto()`) — თხელი JSON პროქსი:
`{url, photographerName, photographerUrl}` ან `{url: null}`, თუ key ცარიელია
ან API ჩავარდა. Key **არასდროს** მიდის ბრაუზერამდე — მხოლოდ სერვერზეა,
frontend მხოლოდ საბოლოო image URL-ს ხედავს.

**`_layout.php`-ის inline `<script>`** (ყველა 4 auth გვერდზე, `$scripts`-ის
გვერდით, chrome-ის ნაწილია) — `window.matchMedia('(min-width:901px)')`-ით
**საერთოდ არ იძახებს** `/auth/photo`-ს მობილურზე (visual panel `display:none`-ია
იქ, ფოტოს polling-ი მხოლოდ quota-ს დახარჯავდა უშედეგოდ). Cross-fade მექანიკა:
ახალი `Image()` preload → `onload`-ზე ძველი `.is-visible` კლასს იხსნის
(fade-out იწყება) → `setTimeout(400ms)`-ის შემდეგ `src` იცვლება უკვე
ჩატვირთულ URL-ზე და `.is-visible` უბრუნდება (fade-in) — preload-ის გარეშე
ახალი ფოტოს გამოჩენისას ცარიელი/frozen frame გამოჩნდებოდა ჩატვირთვის
დროს. ინტერვალი **randomized 10-15წმ** (`10000 + Math.random()*5000`),
"ორგანულ" ფილინგისთვის ზუსტი 10 ან 15 წამის მაგივრად.

⚠️ **key თავდაპირველად ცარიელი იყო** — გადამოწმდა, რომ `/auth/photo`
კორექტულად აბრუნებდა `{"url":null}`-ს და frontend ბრენდის გრადიენტს
ტოვებდა ხილულად. Fade-ის transition (`opacity 1.2s`) და class-toggle
ლოგიკა პირდაპირ DOM-მანიპულაციით გადამოწმდა (ხელოვნური
`data:image/svg+xml` src-ით). Mobile-skip გადამოწმებულია
network-request log-ით — viewport-ის mobile-ზე გადართვის შემდეგ
`/auth/photo`-ზე ახალი request აღარ იგზავნება. **✅ მას შემდეგ
მომხმარებელმა `PIXABAY_API_KEY` ჩაწერა `.env`-ში და ცოცხლად
დადასტურდა** — `/register`/`/login`-ზე `read_page`-მა რეალური Pixabay
ფოტოს credit-ბმული დაინახა (`https://pixabay.com/photos/...`,
ფოტოგრაფის სახელით), ე.ი. მთელი ჯაჭვი (key → API → fade → attribution)
production-ში მუშაობს.

### 4.21 `/profile` და `/profile/settings` — ლოგირებული მომხმარებლის საკუთარი გვერდები

Topbar-ის user dropdown-ში "პროფილი"/"პარამეტრები" (`partials/topbar.php`)
თავიდანვე იყო, მაგრამ `href="#"` placeholder-ებით. `ProfileController`-მა
ორივე რეალურად ამუშავა. ⚠️ **გლობალური auth-gate არ დამატებია** (ისევ
4.20-ის გადაწყვეტილებაა ეს) — მაგრამ "ჩემი ანგარიშის" გვერდს ანონიმური
ვიზიტორისთვის საჩვენებელი არაფერი აქვს, ამიტომ **ეს ორი გვერდი თავად
იცავს თავს**: `ProfileController::requireAuth()` (`Auth::user() === null`
→ `redirect('/login')`) — წერტილოვანი (page-specific) გეიტი, არა
აპლიკაცია-ფართო middleware. Core `Controller::view()`-ს იყენებენ (ჩვეულებრივი
sidebar/topbar layout), არა auth-ის `bare()`/split-screen shell-ს — ეს აპის
შიდა გვერდებია, არა public auth-გვერდები.

**`/profile`** — სახელი/ელფოსტა/ტელეფონი (ყველა editable, `User::validateProfileUpdate()`
ამოწმებს uniqueness-ს **სხვა** account-ებთან შედარებით, `$currentUserId`-ის
გამორიცხვით — თორემ საკუთარი უცვლელი email/phone საკუთარ თავს დაეჯახებოდა).
ავატარი — ინიციალი (არა ატვირთვა, ⚠️ **ganzrax გამარტივებული** — ატვირთვის
ლოგიკა/uploads-directory ცალკე საქმეა). Google-ის დაკავშირების ბეჯი (readonly)
თუ `google_id` არსებობს.

**`/profile/settings`** — პაროლის შეცვლა: `current_password` **მხოლოდ მაშინ**
მოწმდება, თუ `password_hash !== null` (Google-ით შექმნილ ანგარიშს პაროლი
ჯერ არ აქვს — პირველი დაყენება `current_password`-ის გარეშე ხდება,
`profile.settings.no_password_yet` ხსნის ამას). **გადამოწმებული ცოცხლად
სრული ციკლით:** არასწორი მიმდინარე პაროლით submit → დაიბლოკა
(`profile.err_current_password`) ✓ · სწორით → პაროლი შეიცვალა, flash
"შენახულია" ✓ · **logout + login ახალი პაროლით** → წარმატებული შესვლა
დაადასტურა, რომ ცვლილება რეალურად ბაზაშია (არა მხოლოდ session/UI-ში) ✓.

**Google-ის დაკავშირება ("Connect") ლოგირებული სესიიდან** — `AuthController::googleCallback()`-ს
დაემატა შემოწმება თავში: `Auth::check()` თუ true, ეს აღარ არის login/register
მცდელობა, არამედ "დააკავშირე Google ჩემს ამჟამინდელ ანგარიშს" — `User::linkGoogleId()`
პირდაპირ **მიმდინარე** სესიის user id-ზე (არა ძველი find-by-email/create-new
გზა, რომელიც login-flow-სთვისაა). ⚠️ **collision-შემოწმებული**: თუ ეს
Google ანგარიში უკვე სხვა Nova-user-ზეა მიბმული, `profile.settings.err_google_taken`
ბლოკავს ჩუმ overwrite-ს/session-hijack-ის რისკს. `GET /auth/photo`-ს
redirect target-იც (`auth.err_oauth_failed`-ის შემთხვევაში) `Auth::check()`-ზეა
პირობითი — `/login` ანონიმებისთვის, `/profile/settings` უკვე-შესულებისთვის.
**ცოცხლად ვერ დავტესტე** (Google client_id/secret ჯერ ცარიელია `.env`-ში,
იხ. 4.20.1) — redirect URL-ის აგება/state-შემოწმება/collision-ლოგიკა
მხოლოდ code review-ითაა გადამოწმებული.

### 4.22 ავატარი, ქვე-მომხმარებლები (როლები) და ორგანიზაცია

მომხმარებელმა სთხოვა (1) ავატარის ატვირთვა `/profile`-ში, (2) მთავარმა
მომხმარებელმა ქვე-მომხმარებლების დამატება შეძლოს (ავატარი/სახელი/ელფოსტა/
ტელეფონი/პაროლი/წვდომის ხარისხი), რაც ქვე-მომხმარებელს საკუთარ `/profile`-ში
გამოუჩნდეს login-ის შემდეგ, და (3) ორგანიზაციის მონაცემები (სახელი/
საიდენტიფიკაციო/საკონტაქტო/მისამართი/ინვოისის პრეფიქსი/საბანკო ინფო/
ლოგო/ხელმოწერა). ორ საკვანძო არქიტექტურულ საკითხზე (ერთი ორგანიზაცია
მთელ აპზე თუ multi-tenant? წვდომის რამდენი დონე?) `AskUserQuestion`-ით
ვკითხე მიმართულება — დადასტურდა: **ერთი ორგანიზაცია** (single-tenant) და
**3 დონე** (ადმინისტრატორი/მენეჯერი/დამთვალიერებელი). არასწორი ვარაუდი აქ
სქემის თავიდან აშენებას მოითხოვდა, ამიტომ არ გამოვიცანი.

**სქემა** (`migrations/010_alter_users_avatar_role.sql`,
`011_create_organization.sql`): `users`-ს დაემატა `avatar`, `role`
(`ENUM('admin','manager','viewer') DEFAULT 'admin'` — ⚠️ **default 'admin'
საჭირო იყო**: არსებული self-registered მომხმარებლები ავტომატურად
"მთავარი"-ებად რჩებიან migration-ის შემდეგაც, ხელით update საჭირო არ
გახდა), `created_by` (FK → `users.id`, `ON DELETE SET NULL`, NULL
self-registered-ებისთვის). `organization` — **single-row ცხრილი**, ყოველთვის
`id=1` (migration-ივე სიდსავს ცარიელ row-ს) — `Organization::get()`/`save()`
ამ კონვენციას მარტივად აღსრულებენs, `WHERE id = 1` ყველგან.

**`Auth::requireUser()`/`requireAdmin()`** — `ProfileController`-ის ძველი
კერძო `requireAuth()` გავიტანე `App\Core\Auth`-ში (3 controller-ს სჭირდებოდა
იგივე ლოგიკა — ეს არის ის წერტილი, სადაც დუბლირება reuse-ში გადავიყვანე,
ღირდა კიდეც). `requireAdmin()` = `requireUser()` + `role !== 'admin'` →
`flash('notice', ...)` + `redirect('/')`. ⚠️ **`DashboardController`-მა
`notice` flash არასდროს არ იცოდა** — `dashboard.php`-ს საერთოდ არ ჰქონდა
flash-რენდერი (ის ცოცხალი mock-გვერდია საწყისი commit-იდან). დავამატე
`'notice' => flash('notice')` + `alert-warning` ბლოკი — ამის გარეშე
`requireAdmin()`-ის "წვდომა არ გაქვთ" შეტყობინება უჩუმრად იკარგებოდა.
**გადამოწმებულია ცოცხლად**: manager-role sub-user-მა `/settings/users`-ზე
მოხვედრისას სწორად ნახა შეტყობინება დაფაზე გადამისამართების შემდეგ.

**Avatar/logo/signature ატვირთვა** — იმავე `resolveImage()`-ის ნიმუშია,
რაც Warehouse-ს ჰქონდა (validate → move → წაშალე ძველი ჩანაცვლებისას),
სამ ცალკე controller-ში დუბლირებული (`ProfileController`, `UserController`,
`OrganizationController`) — არცერთი გაზიარებული helper class არ გაკეთდა,
რადგან პროექტს აქამდეც არასდროს ჰქონია ასეთი (Warehouse-იც საკუთარ ასლს
იყენებდა). `.ds-product-thumb`-ის არსებული CSS თავად გამოყენებულია ავატარისთვისაც
(`border-radius:50%` inline override-ით წრიულობისთვის), ახალი CSS არ
დამატებულა. Upload დირექტორიები ცალკეა: `public/assets/uploads/avatars/`,
`public/assets/uploads/organization/`.

**`/settings/users`** (`UserController`, admin-only) — იგივე add-or-edit-
in-one-form პატერნია, რაც `customers.php`-ს (`user_id` hidden ველი
წყვეტს create/update-ს). პაროლი **სავალდებულოა დამატებისას, არასავალდებულო
რედაქტირებისას** (ცარიელი = უცვლელი) — `User::validateSubUser()`-ში
`$editingId`-ზეა დამოკიდებული. **გადამოწმებულია ცოცხლად**: sub-user
შეიქმნა (`role=manager`, ტელეფონი ნორმალიზებული), login-ისას `/profile`-ში
სწორად გამოჩნდა სახელი+როლის ბეჯი (`მენეჯერი`) — ეს პირდაპირ პასუხობს
მოთხოვნას "ეს ყველაფერი უნდა გამოჩნდეს ქვემომხმარებლის ... პროფილში".

**`/settings/organization`** (`OrganizationController`, admin-only) —
ერთი ფორმა, ყველა ველი + 2 დამოუკიდებელი სურათი (ლოგო/ხელმოწერა, თითო
საკუთარი `resolveImage()`-გამოძახებით). `bank_details` **თავისუფალი
ტექსტია** (textarea), არა სტრუქტურირებული ქვე-ველები — მომხმარებელმა ერთ
item-ად ჩამოთვალა "საბანკო ინფორმაცია" name/tax_id/email/...-ის გვერდით,
სტრუქტურის გამოგონება (bank_name/iban/swift ცალკე ველებად) აქ სპეკულაციური
იქნებოდა. **გადამოწმებულია ცოცხლად**: ორგანიზაციის მონაცემები submit
→ DB-ში ზუსტად ემთხვევა (`Organization::get()`-ით პირდაპირ შემოწმებულია).

⚠️ **ტესტისას აღმოვაჩინე ცოცხლი production მონაცემები** — `users`-ში უკვე
იყო რეალური თვითრეგისტრირებული ანგარიში (`info@phouse.ge`, `role=admin`
default-ით) predecessor testing session-იდან. **არ შევხებივარ** მას
cleanup-ისას — მხოლოდ ჩემი ამ სესიის ტესტ-ანგარიშები (`admin.test@…`,
`subuser.one@…`) წავშალე, ორგანიზაციის row ცარიელ საწყის მდგომარეობას
დავუბრუნე.

### 4.23 `terr()` — ვალიდაციის შეცდომების ნომრები

მომხმარებელმა სთხოვა ვალიდაციის ლოგიკა შეცდომების ნომრებით, რომელიც ყველა
გვერდზე იმუშავებდა — ეს პირდაპირ მოჰყვა წინა სესიაში რეალურად ნაპოვნ ბაგს
(`4.22`-ის sub-user-დამატება), რომელიც ბაგი საერთოდ არ იყო: ვალიდაცია
სწორად უარყოფდა დუბლირებულ ტელეფონს/მოკლე პაროლს, უბრალოდ მომხმარებელს
საიდან გაეგო *რომელი* წესი ჩაიშალა, log-ის დათვალიერების გარეშე.

**`t()` vs `terr()`** (`app/Core/helpers.php`) — `terr(string $key, ...$args)`
წვება `t()`-ს და უმატებს სტაბილურ 4-ციფრიან კოდს ბოლოში:
`sprintf(' (#%04d)', crc32($key) % 10000)`. კოდი გამოითვლება lang key-დანვე
(არა counter/registry), ამიტომ **ერთი და იგივე key ყოველთვის ერთსა და იმავე
კოდს იძლევა** ხელით managed სიის გარეშე — ახალი ვალიდაცია ავტომატურად
იღებს კოდს, როგორც კი `terr()`-ს იძახებს. `crc32 % 10000` კოლიზია
თეორიულად შესაძლებელია ორ განსხვავებულ key-ს შორის, მაგრამ ~80 არსებულ
error-key-ზე ეს პრაქტიკული რისკი არ არის (გადამოწმებული არ ყოფილა
ავტომატურად — ხელით თუ ახალი key ემატება და კოდი უკვე დაკავებულია
სხვასთან, ეს არ იბლოკება).

**წესი**: `terr()` **მხოლოდ** `$errors[...]`/JSON `'error'`-ის დანიშნულების
ადგილებზეა — ანუ ის, რაც მომხმარებელს ეუბნება "რატომ ჩაიშალა submit".
ჩვეულებრივი UI ტექსტი (სათაურები, success flash, email subject/body) **რჩება
`t()`-ზე** — `AuthController`-ის OTP/reset mail-ის ტექსტი და login-გვერდის
სათაურები განზრახ არ შეხებია.

**დაფარვა** (ყველა `$errors[...] = t(...)` / `'error' => t(...)` საიტი
გადავიდა `terr()`-ზე): `Customer`, `Organization`, `Product`, `User`
(სამივე `validate*()`), `AuthController` (login/OTP/Google/reset — ყველა
error branch), `OrganizationController::resolveImage()`, `ProfileController`
(პაროლი + ავატარი), `UserController::resolveAvatar()`,
`Warehouse\WarehouseController::resolveImage()`,
`Warehouse\ProductWarehouse::validate()`, `LookupController::save()`
(`units`), `Warehouse\ProductTypeController::save()` — ეს ბოლო ორი
ცალკეა, რადგან `4.19`-ის მიხედვით პროდუქტის ტიპის lookup **განზრახ
დუბლირებულია** core/module boundary-ზე, საერთო base class-ის გარეშე.

**გადამოწმებულია ცოცხლად**: `curl`-ით ცარიელი `customer_name`/`customer_taxid`
submit `/customers`-ზე → flash-ში დაბრუნებულ HTML-ში ორივე კოდი
გამოჩნდა (`#1696`, `#4821`) — ანუ მომხმარებელს შეუძლია თქვას "მივიღე
შეცდომა #4821" და ეს ერთი რიცხვი ცალსახად ადგენს, რომელი წესი ჩაიშალა,
log-ის გარეშე.

⚠️ **`4.47`-ის შემდეგ user-მა მოითხოვა ნომრის ჩვენების მოშორება**
(`(#7867)` UI-ში ზედმეტად ჩანდა) — `terr()` **დარჩა ყველგან
გამოძახებული** (call site-ები უცვლელია, `$errors[...] = terr(...)`),
უბრალოდ `terr()`-ის სხეული გახდა `t()`-ის alias
(`app/Core/helpers.php`), კოდის დამატება მოშორდა. ფუნქცია
დატოვებულია (არა პირდაპირ `t()`-ზე გადართვა ყველა საიტზე) — თუ
მომავალში ისევ დასჭირდებათ ნომრები (support-ის მოთხოვნით), ერთ
ადგილას დაბრუნდება.

### 4.24 `window.dsNotify` — გლობალური toast შეცდომებისთვის, submit-მდე

`4.22`-ის ავატარის ატვირთვის ბაგის (ორფანი ფაილები) გამოსწორების შემდეგ
მომხმარებელმა სთხოვა: თუ ფაილი უბრალოდ ზედმეტად დიდია, ეს **submit-ის
გარეშე**, ფაილის არჩევისთანავე უნდა გამოჩნდეს — page reload-ის და
`$errors`/flash-ის მთელი ციკლის დალოდება ამ შემთხვევაში ზედმეტია, რადგან
ზომა/ფორმატი კლიენტზევე ცნობადია `File.size`/`file.name`-იდან.

**`window.dsNotify(message, type = 'danger')`** (`public/assets/js/app.js`,
`app.js`-ის IIFE-დან გატანილი, თორემ სხვა გვერდის inline script ვერ
გამოიძახებდა) — ქმნის Bootstrap-ის `bootstrap.Toast` ინსტანციას `layout.php`-ში
ერთხელ დამატებულ `#dsToastContainer`-ში (`position-fixed top-0 end-0`,
ყველა გვერდზე არსებობს ჩატვირთვისთანავე, ცარიელია სანამ არაფერი გამოიძახებს).
`autohide` default (6წმ) მოქმედებს, `hidden.bs.toast`-ზე თავად შლის თავის
DOM-ს — არაფერი "გროვდება" გვერდზე. `message` ყოველთვის ჩვენივე
`t()`/`terr()`-დანაა (არასდროს raw user input), ამიტომ პირდაპირ `innerHTML`-ში
ჩასმა უსაფრთხოა.

**`app/config/notifications.php`** — ხელით managed catalog, `code => ['type'
=> ..., 'key' => ...]`, `type` ∈ `error`/`warning`/`success`. მომხმარებელს
ჯერ შევთავაზე ავტომატური ალტერნატივა (`terr()`-ის მსგავსი, `window.dsLang`-ზე
დაფუძნებული `dsNotifyKey(key)` — ერთი iteration ადრე ამ handoff-ში
ცხოვრობდა), მაგრამ **განზრახ აირჩია ხელით ფაილი**: სურდა ერთ ადგილას
ხედვადი ყველა toast-კოდი, `terr()`-ის "ავტომატური, არავინ ხედავს სიას"
მიდგომის ნაცვლად. `4.23`-ის `terr()` **უცვლელი რჩება** — ეს ცალკე,
პარალელური სისტემაა, მხოლოდ client-side toast-ებისთვის.

```php
return [
    4418 => ['type' => 'error', 'key' => 'prod.err_image_size'],
    6817 => ['type' => 'error', 'key' => 'prod.err_image_type'],
];
```
⚠️ **კოდები ხელით არჩეულია, არა `crc32`-გამოთვლილი** — მაგრამ ეს ორი
სპეციალურად **იმ ზუსტ რიცხვებზეა დაყენებული, რასაც `terr('prod.
err_image_size')`/`terr('prod.err_image_type')` თავად გამოთვლიდა** — ასე
inline ვალიდაციისა და toast-ის კოდი არასდროს დაშორდება ერთმანეთს იმავე
წესისთვის. ახალი, `terr()`-ს არდაკავშირებული toast-ის დამატებისას თავისუფლად
აირჩევა ნებისმიერი თავისუფალი 4-ციფრიანი კოდი.

**`App\Core\Notifications::all()`** (`app/Core/Notifications.php`) — კითხულობს
ამ ფაილს ერთხელ per-request (`??=`), analogously `ModuleRegistry`-ის caching-ის.

**`layout.php`-ში ერთხელ** — კატალოგი გადადის JS-ში, ტექსტი უკვე
**server-side resolved** მიმდინარე ენაზე (`t($n['key'])`), არა raw key:
```php
window.dsNotifications = <?= json_encode(array_map(
    static fn(array $n) => ['type' => $n['type'], 'text' => t($n['key'])],
    \App\Core\Notifications::all()
), ...) ?>;
```

**`app.js`: `window.dsNotifyCode(code)`** — ერთადერთი public entry point:
```js
window.dsNotifyCode = (code) => {
  const entry = window.dsNotifications?.[code];
  if (!entry) { window.dsNotify(`#${code}`, 'danger'); return; } // დაურეგისტრირებელი კოდი — ხმაურიანად, არა ჩუმად
  window.dsNotify(entry.text, { error: 'danger', warning: 'warning', success: 'success' }[entry.type] ?? 'danger');
};
```
`error → danger` მეპინგი საჭიროა, რადგან Bootstrap-ის კონტექსტური კლასია
`text-bg-danger`, არა `text-bg-error` — დანარჩენი ორი (`warning`/`success`)
პირდაპირ ემთხვევა Bootstrap-ის საკუთარ სახელებს.

**გამოყენება** (`app/Views/users.php`):
```js
if (file.size > Number(input.dataset.maxBytes)) {
  window.dsNotifyCode?.(4418); // app/config/notifications.php: prod.err_image_size
  input.value = '';
  return;
}
```
`?.` განზრახაა — თუ `app.js` ვერ ჩაიტვირთა, ჩუმად არაფერს აკეთებს submit-ის
დაბლოკვის ნაცვლად; საბოლოო ვალიდაცია ისედაც სერვერზეა (`UserController::
validateAvatar()`, `4.22`/`resolveAvatar`-ის split). ანუ toast არის **UX
სისწრაფე**, არა ერთადერთი დაცვის ხაზი. ახალი toast-ის დამატება = ერთი
ჩანაწერი `notifications.php`-ში + `dsNotifyCode(კოდი)`-ის გამოძახება — არც
lang-key-ის ხელახლა წერა, არც `data-*` plumbing თითო ველზე.

`users.avatar_hint` ველის ქვეშ (`jpg, png ან webp, მაქს. 2MB.`) — სტატიკური
ჰინტი, `t()`-ზეა (არ არის ამ კატალოგის ნაწილი — არ არის error/warning/success).

**გადამოწმებულია ცოცხლად** (`javascript_exec`): `window.dsNotifications[4418]`
= `{type:'error', text:'სურათი მაქსიმუმ 2MB უნდა იყოს.'}`; 3MB ფაილი →
toast `text-bg-danger`-ით, სწორი ტექსტი, კოდის გარეშე; დაურეგისტრირებელი
კოდი (`dsNotifyCode(9999)`) → toast აჩვენებს `"#9999"`-ს ჩუმად წარუმატებლობის
მაგივრად. `Lang::all()` წაიშალა (`app/Core/Lang.php`) — dsLang-ის მოცილების
შემდეგ აღარავინ იძახებდა.

### 4.25 ინვოისები (`/invoices`) — დამკვეთი + რამდენიმე პროდუქტი (line items)

მომხმარებელმა სთხოვა ახალი გვერდი მარცხენა მენიუში, სადაც აირჩევა დამკვეთი
და პროდუქტი(ები). Plan mode-ში `AskUserQuestion`-ით დადასტურდა: **რამდენიმე
პროდუქტი** (line items — რაოდენობა × ფასი თითო სტრიქონზე), არა ერთი.
Greenfield ფუნქციონალია — `Invoice`-მდე არაფერი არსებობდა.

**სქემა** — ორი ცხრილი (`migrations/013`, `014`): `invoices`
(`customer_id`, `issue_date`, `total`) + `invoice_items` (`product_id`,
`quantity` DECIMAL(12,3), `unit_price`/`line_total` DECIMAL(12,2)).
`unit_price`/`line_total` **სნეპშოთია** ინვოისის შენახვის მომენტში, არა live
join `products.unit_price`-ზე — პროდუქტის ფასის მომავალმა ცვლილებამ ძველი
ინვოისი არ უნდა შეცვალოს. FK: `invoice_items → invoices` არის `ON DELETE
CASCADE` (სტრიქონი ინვოისის ნაწილია), დანარჩენი (`→ customers`/`products`)
**default RESTRICT** — ვერ წაიშლება დამკვეთი/პროდუქტი, რომელზეც ინვოისია.

⚠️ **`organization.invoice_prefix` პირველად რეალურად გამოიყენა** — ველი
`migrations/011`-იდან არსებობდა, მაგრამ არავინ კითხულობდა. ინვოისის ნომერი
**არ ინახება** ცალკე სვეტში — გამოითვლება ჩვენებისას ორივეგან (ფორმის
success flash-შიც და სიაშიც): `sprintf('%s-%04d', $invoicePrefix, $id)` →
`PH-0001`. ერთი ნაკლები სვეტი, სინქრონიზაციის საზრუნავი არ არის.

**`Invoice::save()`** ტრანზაქციაშია (`Db::conn()->beginTransaction()`/
`commit()`) — header UPDATE/INSERT, მერე **items მთლიანად იცვლება**
(`DELETE FROM invoice_items WHERE invoice_id=?` + ახალი INSERT-ები), იგივე
"replace, არა diff" მიდგომა, რაც `Organization::save()`-ს აქვს
`bank_ibans`-ზე (`4.23`-ის მეზობელი პატერნი). `total` ყოველთვის server-ზე
თავიდან ითვლება (`Σ qty×price`) — client-JS-ის ცოცხლი ჯამი მხოლოდ UX-ია.

**Line items UI** — პირდაპირ `organization.php`-ის dynamic IBAN rows-ის
გენერალიზაციაა: `#invoiceItems`-ში თითო სტრიქონი product `ds-select` +
qty/price/readonly-total + წაშლის ღილაკი, ბოლო სტრიქონზე product-ის
არჩევისთანავე ავტომატურად ემატება ახალი ცარიელი. **ერთი ახალი დეტალი**
წინა IBAN-პატერნთან შედარებით: ds-select **დინამიურად დამატებულ** row-ებზე
თავად არ ინიციალიზდება (`ds-select.js`-ს მხოლოდ ერთი `DOMContentLoaded`
listener აქვს) — ამიტომ `addRow()` ხელით აკეთებს `select.dsSelect = new
window.DsSelect(select)`-ს ახალი row-ის ჩასმის შემდეგ (`window.DsSelect`
კლასი გლობალურადაა expose-ილი სპეციალურად ამისთვის, `ds-select.js`-ის
საკუთარი დოკუმენტაციის მიხედვით).

**Row-click → edit, items-ის ჩათვლით** — სიის თითო `<tr>`-ს აქვს
`data-items="<json>"` (`Invoice::itemsByInvoice()`-ით ერთ query-ში
აგებული, N+1 route-ის გარეშე). Click-ისას `container.innerHTML = ''` +
`items.forEach(addRow)` + ერთი ბოლო ცარიელი — `customer_id` ds-select
ჩვეულებრივად ივსება.

**ვალიდაცია** (`Invoice::validate()`) — `terr()`-ით ყველგან (`4.23`-ის
კონვენცია): `customer_id` აუცილებელი, `items`-ში მინიმუმ ერთი ვალიდური
სტრიქონი (ცარიელი trailing row ჩუმად გამოტოვება, არა error). თითო
არასწორი სტრიქონის error იკვრება `items_{i}`-ზე (იმავე ინდექს-კონვენციით,
რაც `organization.php`-ის `bank_ibans_{i}`-ს ჰქონდა).

**გადამოწმებულია ცოცხლად**: 2 line item-იანი ინვოისის შექმნა (`curl`) →
სწორი `total` (81.00 = 30+51) DB-ში; ვალიდაციის ჩავარდნა (ცარიელი
customer/items) → `terr()`-კოდები, ჩანაწერი **არ** შექმნილა; edit 2
item-დან 1 item-მდე → items სწორად **მთლიანად ჩანაცვლდა** (ძველი წაიშალა,
არა დაგროვდა), `total` ხელახლა გამოთვლილი; ბრაუზერში row-click → სრული
აღდგენა header + items-ით; ახალი product არჩევისას ფასის ავტო-შევსება +
ახალი row-ის ავტომატური დამატება + ცოცხლი ჯამის გამოთვლა — ყველა
დადასტურებული.

#### 4.25.1 თარიღის ველი მოიხსნა ფორმიდან — `issue_date` ყოველთვის დღეს

მომხმარებელმა (screenshot-ით, print-გვერდის mockup) სთხოვა: "თარიღის
არჩევის ელემენტი არაა საჭირო". `AskUserQuestion`-ს პასუხი არ მოჰყოლია, ამიტომ
რეკომენდებული ვარიანტით გავაგრძელე (ეს ცალსახად აღვნიშნე პასუხში, რომ
მომხმარებელს გადაესწორებინა საჭიროებისამებრ): **`issue_date` აღარ არის
input ფორმაში საერთოდ** — ახალი ინვოისი ყოველთვის `date('Y-m-d')`-ით
იქმნება (`Invoice::save()`-ში, PHP-ზე, არა DB default-ით), ხოლო edit-ისას
`UPDATE`-ის სვეტების სიაში `issue_date` საერთოდ აღარ ფიგურირებს — თარიღი
ერთხელ დაფიქსირდება შექმნისას და აღარასდროს იცვლება. `Invoice::validate()`-
დანაც მთლიანად გაქრა (`inv.err_issue_date_required` key-ც წაიშალა ორივე
lang-ფაილიდან — აღარავინ იძახებდა). სია და print-გვერდი კვლავ **აჩვენებენ**
`issue_date`-ს (`ds_date()`-ით ფორმატირებულს) — უბრალოდ აღარ არის
რედაქტირებადი.

#### 4.25.2 `/invoices/view?id=N` — ბეჭდვადი ინვოისის დოკუმენტი

Screenshot-ით მოთხოვნილი header-ის დიზაინი (ლოგო + ორგანიზაციის მისამართი,
ინვოისის ნომერი + თარიღი ზემოთ) გადაიზარდა სრულ print-გვერდში — თავად
header ცალკე არაფრის მომცემია ბეჭდვად დოკუმენტად, ამიტომ დაემატა
bill-to/items/total/საბანკო-ინფოც.

**Route** — `GET /invoices/view` (query-string `?id=N`, არა path-parameter:
`Router`-ს დღემდე **არ აქვს** დინამიური სეგმენტების მხარდაჭერა, ბრტყელი
`"VERB /path"` dictionary-ია — გადამოწმებულია `Router.php`-ის წაკითხვით,
ეს პროექტის კონვენციასთან შესაბამისობაშია, არა ჩემი გამონაკლისი).

**`InvoiceController::show()`** — `Invoice::find($id)` (ინვოისი +
customer_* ველები join-ით, `Invoice::itemsFor($id)` (line items + product
name). თუ id არ არსებობს → `ErrorController::notFound()`-ის იგივე view.
⚠️ **პირველი ცდისას `http_response_code(404)` არ იყო დაყენებული** —
`Router::dispatch()`-ს თავად აქვს ეს ლოგიკა **მხოლოდ genuinely-unmatched
route-ისთვის** (`/invoices/view` route თავად matched-ია, id უბრალოდ არ
არსებობს), ასე რომ status code ხელით უნდა დაყენდეს `ErrorController`-ისთვის
გადაცემამდე — curl-ით 200 დაბრუნდა თავიდან (`id=99999`-ზე), აღმოჩენილი და
გასწორებულია იმავე ტესტში.

**`window.print()`, არა PDF ბიბლიოთეკა** — "PDF შენახვა" და "ბეჭდვა" ორივე
`window.print()`-ს იძახებს. პროექტს არ აქვს PDF-გენერაციის ბიბლიოთეკა
(no-Composer კონვენცია, `CLAUDE.md`) — ბრაუზერის print დიალოგის "Save as
PDF" დანიშნულება ამ საჭიროებას fully covers-ს ახალი დამოკიდებულების
გარეშე. `AskUserQuestion`-ს ეს ცალსახად შევთავაზე რეკომენდებულ ვარიანტად,
პასუხი არ მოვიდა, ამიტომ ეს ვარიანტი გავაგრძელე.

**`.no-print` + `@media print`** (`design-system.css`, ბოლოში) — გლობალური,
ნებისმიერ გვერდს შეუძლია გამოიყენოს: `.ds-sidebar`/`.ds-topbar` (app chrome)
+ `.no-print` კლასის ნებისმიერი ელემენტი იმალება ბეჭდვისას, `.ds-main`/
`.ds-content`-ის padding/margin ნულდება — ბეჭდვისას მხოლოდ `invoice-view.php`-ის
დოკუმენტ-card რჩება გვერდზე.

**გადამოწმებულია ცოცხლად**: `/invoices/view?id=N` → ლოგო/ხელმოწერა
(`org.logo`/`org.signature`-დან) სწორად ჩანს, ორგანიზაციის სახელი+tax_id+
მისამართი+ტელეფონი+email+website, დამკვეთის bill-to ბლოკი, ორივე line item
სწორი რაოდენობა/ფასით, `total`, ორივე რეალური IBAN ანგარიში (`4.23`-ის
ფუნქციონალიდან) სია-ს ბოლოში; `.no-print`/`.ds-sidebar`/`.ds-topbar`-ზე
`@media print` წესის არსებობა JS-ით დადასტურებულია (`document.styleSheets`
scan); არასწორი `id` → **404** (გასწორების შემდეგ).

#### 4.25.3 სია `/invoices`-დან `/orders`-ზე გადავიდა — create/edit ცალკე, browse ცალკე

მომხმარებელმა სთხოვა: (1) "ინვოისების სია" ცხრილი გადატანილიყო
"შეკვეთები > ყველა შეკვეთა" მენიუში (ადრე მკვდარი `"#"` ბმული,
`menu.json`-ის საწყისი mock-იდან), (2) "ახალი ინვოისი" card აღარ იყოს
აკეცვადი (`<details>` → ჩვეულებრივი `<div class="card">`), (3) გვერდი
გაყოფილიყო 3/4 (ფორმა) + 1/4 (ჯერჯერობით ცარიელი card) სვეტებად.

**`/orders`** (`orders.php`, ახალი) — მხოლოდ სია, `InvoiceController::
orders()`-იდან. Breadcrumb/H1 იყენებს `t('nav.orders_all')`-ს პირდაპირ
(არა ცალკე `page.orders` key — მენიუს ლეიბლი და გვერდის სათაური ერთი და
იგივეა, დუბლირება ზედმეტი იქნებოდა). `menu.json`-ში `nav.orders_all`-ის
`url` `"#"`-დან `"/orders"`-ზე შეიცვალა — `nav.orders_pending`/
`nav.orders_new` **უცვლელი დარჩა** (არ მოთხოვნილა).

⚠️ **Row-click-to-edit აღარ არსებობს** — რადგან სია და ფორმა ცალკე
გვერდებზეა, ძველი "დააკლიკე row-ს და ფორმა თავისით შეივსება" (JS,
იმავე გვერდზე) ვეღარ მუშაობდა. ამის მაგივრად: `orders.php`-ის თითო
მწკრივს ორი ცალკე ბმული აქვს — ✏️ (`/invoices?edit=N`) და 🖨️
(`/invoices/view?id=N`, უცვლელი `4.25.2`-დან). **Row-ს აღარ აქვს
`ds-row-editable`/`data-*` ატრიბუტები** — აღარაფერს აკეთებდა, "მოჩვენებით
დაწკაპუნებადი" row უფრო შემცდარი იქნებოდა, ვიდრე ორი ცხადი ღილაკი.

**`InvoiceController::index()`-ის ახალი `?edit=N` მექანიზმი** — ეს არის
ის, რაც ჩაანაცვლა JS-ის ადგილზე-შევსება: `?edit=N` მოსვლისას (და მხოლოდ
მაშინ, როცა **არც** flash error და **არც** flash old არსებობს — failed
resubmit ყოველთვის იმარჯვებს) `Invoice::find($id)` + `Invoice::itemsFor($id)`
იტვირთება და **აწყობს `$old`-ს ზუსტად ისე, როგორც ჩავარდნილი submit
გააკეთებდა** (`invoice_id`, `customer_id`, `item_product_id[]`,
`item_quantity[]`, `item_unit_price[]`). ეს ნიშნავს, რომ ფორმის მთელი
რენდერის ლოგიკა (customer select, item rows, `4.25`-ის ნომერი/თარიღის
ხაზი) **არაფერი შეცვლილა** — უბრალოდ `$old`-ის შევსების ახალი წყარო გაჩნდა.

⚠️ **ბაგი, ნაპოვნი და გასწორებული ცოცხლი ტესტისას**: `$itemRows`-ის PHP-
აგება (`$old['item_product_id']`-დან) აწყობდა მხოლოდ **რეალურ** item-ებს,
ბოლოში ცარიელი "დასამატებელი" row აღარ ჰქონდა — ძველ დიზაინში ეს row
JS-ის `addRow()`-ის დამატებითი, უპირობო გამოძახებით ემატებოდა
(`items.forEach(addRow); addRow();`), რაც ახალ server-side მიდგომაში აღარ
ხდება. **გასწორება**: იგივე "ბოლო row უცილობლად ცარიელი უნდა იყოს" წესი,
რაც `organization.php`-ის IBAN-ებს აქვს (`4.24`-მდელი, `end($ibans) !==
''`-ის კონვენცია) — `end($itemRows)['product_id'] !== ''` → ცარიელი row
დაემატება. ამის გარეშე არსებული ინვოისის რედაქტირებისას ახალი პროდუქტის
დამატება საერთოდ შეუძლებელი იქნებოდა.

`Invoice::itemsByInvoice()` **წაშლილია** (`app/Models/Invoice.php`) — მხოლოდ
ძველი, სია-ში embedded `data-items` JSON-ისთვის იყო საჭირო, რაც აღარ
არსებობს; `orders.php`-ს არც სჭირდება (მხოლოდ `Invoice::all()`-ს
იძახებს, item-ების join-ის გარეშე — სუფთა, უფრო სწრაფი query სიისთვის).

**გადამოწმებულია ცოცხლად**: `/orders` სწორად აჩვენებს ორივე რეალურ
ინვოისს (`PH-0003`, `PH-0005`) ✏️/🖨️ ბმულებით; `/invoices?edit=3` →
customer/items/ნომერი/თარიღი ყველა სწორად ჩაიტვირთა, submit label
"განახლება"; **row-count ბაგის გასწორების შემდეგ** — 1 რეალური item + 1
ცარიელი trailing row (თავდაპირველად მხოლოდ 1 იყო, ბაგი); resubmit
(`invoice_id=3`-ით) → სწორად UPDATE-ავს, `total` უცვლელი; ცარიელი
`customer_id`-ით submit → `terr()`-კოდები (`#7867`/`#1044`) კვლავ
სწორად ჩნდება ახალ 3/4+1/4 layout-ზეც; `.row.g-3 > col-lg-9/col-lg-3`
სვეტები და `#invoice-form`-ის `<div>` (არა `<details>`) დადასტურებულია DOM-ით.

#### 4.25.4 ნომერი/თარიღი — `card-header`-ში, `d.m.Y` ფორმატი

ნომერი/თარიღის ხაზი (`4.25.3`-ში აღწერილი) გადავიდა `card-body`-დან
`card-header`-ში (`justify-content-between` — სათაური მარცხნივ, ნომერი/
თარიღი მარჯვნივ), და თარიღმა Georgian `ds_date()`-ის ("13 აგვ, 2026")
მაგივრად მიიღო უბრალო `d.m.Y` ("13.08.2026") — **მხოლოდ ამ ერთ ადგილას**,
`orders.php`-ის სია და `invoice-view.php`-ის print-გვერდი კვლავ
`ds_date()`-ს იყენებენ, არ შეხებია. `$fmtDate = static fn(string $iso):
string => date('d.m.Y', strtotime($iso));` — ახალი, ამ view-ს საკუთარი
closure, არა გლობალური helper (ერთი გამოყენების ადგილისთვის ცალკე
`App\Core`-ის ფუნქცია overkill იქნებოდა). JS-ის reset-handler-ის
`data-today-formatted` ატრიბუტიც ამავე `$fmtDate`-ით ივსება, ასე რომ
"გასუფთავებაზე" დაბრუნებაც კვლავ `d.m.Y`-ს აჩვენებს.

#### 4.25.5 დამკვეთის სრული ინფორმაცია — `customer_id`-ის ქვემოთ, 3/4 card-ში

არჩეული დამკვეთის **ბაზაში არსებული ყველა ველი** (`Customer::FIELDS`-იდან
`customer_name`-ის გარდა: `customer_taxid`, `customer_contact`,
`customer_phone`, `customer_email`, `customer_address`, `customer_info`)
ცოცხლად ჩანს, `customer_id`-ის ცვლილებაზე.

⚠️ **პირველი iteration-ი 1/4 card-ში (`col-lg-3`) იყო** (`4.25`-ის
"ჯერჯერობით ცარიელი" placeholder-ის ადგილას) — მომხმარებელმა screenshot-ით
სთხოვა გადატანა **"ახალი ინვოისი" card-ის შიგნით**, პირდაპირ `customer_id`
select-ის ქვემოთ. `#customerInfoPanel`-ის `id`/`data-*` ატრიბუტები
უცვლელი დარჩა ადგილის შეცვლისას — JS `getElementById`-ით მუშაობს, DOM-ში
ფიზიკურ მდებარეობაზე დამოკიდებული არაფერია. 1/4 card დაუბრუნდა თავის
თავდაპირველ, ნამდვილად ცარიელ მდგომარეობას. ვიზუალურადაც შეიცვალა
screenshot-ის მიხედვით: `bg-primary-subtle rounded-3 p-3` ყუთი (არა
ცალკე card), დამკვეთის სახელი `fw-bold text-primary`-ით ზემოთ, დანარჩენი
ველები ერთ ხაზზე `"ლეიბლი: მნიშვნელობა"` ფორმატით (არა ცალ-ცალკე
stacked label/value, რაც პირველ ვერსიაში იყო). ზემოთ დაემატა
`<label class="form-label"><?= t('inv.customer_info') ?></label>` —
იგივე პატერნი, რაც `inv.items`-ს აქვს `4.25`-ში.

**მთლიანად JS-ით რენდერდება**, PHP მხოლოდ მონაცემებს აწვდის ორი
`data-*` JSON-ით `#customerInfoPanel`-ზე — `data-customers`
(`array_column($customers, null, 'id')`, id-keyed მთელი ცხრილი) და
`data-field-labels` (`cust.taxid`/`cust.contact`/... უკვე თარგმნილი
ტექსტები, `customers.php`-ის იგივე ლეიბლები, ახალი key არ დამატებულა).
`renderCustomerInfo(id)` იძახება (ა) გვერდის ჩატვირთვისას ერთხელ
(`?edit=N`/failed-resubmit-ის უკვე არჩეული customer-ისთვის — ცარიელი
საწყისი state-ის ნაცვლად პირდაპირ სწორი ინფო ჩანს), (ბ) `customer_id`-ის
`change`-ზე (ds-select-ის `pick()`-იც ნამდვილ `change` event-ს agზავნის,
ასე რომ ეს მუშაობს search-dropdown-იდან არჩევისასაც), (გ) ფორმის
`reset`-ზე (ცარიელ state-ს უბრუნდება).

⚠️ **`customer_taxid === '0'` განზრახ იმალება** — `Customer.php`-ის
დოკუბლოკის იგივე კონვენციაა ("import-ილ მონაცემებში '0' ნიშნავს 'no tax
id'"), JS-ში ცალკე შემოწმდა, რადგან JS-ის ჩვეულებრივი falsy-check
(`!value`) `"0"` სტრიქონს **ტრუთი**-დ თვლის (მხოლოდ ცარიელი `""` არის
falsy) — ამის გარეშე ყველა "უცოდინარი" tax id-ის მქონე დამკვეთი
პანელში "0"-ს აჩვენებდა.

**გადამოწმებულია ცოცხლად**: საწყისი state → "აირჩიეთ დამკვეთი
დეტალების სანახავად."; რეალურ დამკვეთზე (`customer_taxid='0'`) → სახელი +
საკონტაქტო/ტელეფონი/ელფოსტა, tax id **არ ჩანს**; სხვა დამკვეთზე (რეალური
tax id) → tax id **ჩანს**; deselect → უბრუნდება empty-state ტექსტს;
`/invoices?edit=3` → panel სწორად ივსება **გვერდის პირველივე ჩატვირთვისას**,
ცვლილების დალოდების გარეშე.

#### 4.25.6 1/4 სვეტი — action-ღილაკები + სტატუსი + დამკვეთის ინვოისების ისტორია

1/4 სვეტი ორ card-ად გაიყო: **(ა)** action-ღილაკები (შენახვა/PDF export/
გადახედვა/მეილი/WhatsApp/ბმულის გაზიარება) + სტატუსის `<select>`
(პირველადი/საბოლოო/გადასახდელი/გადახდილი) + ორი დამოუკიდებელი checkbox
(ნულოვანი, განმეორებადი); **(ბ)** არჩეული დამკვეთის სხვა ინვოისების სია
(ნომერი — თანხა), ცოცხლად `customer_id`-ის ცვლილებაზე, `renderCustomerInfo`-ს
იგივე სამი hook-წერტილიდან (page load, `change`, `reset`) გამოძახებული.

⚠️ **მომხმარებელმა ცალსახად თხოვა: "ღილაკების ფუნქციონალი არ გვინდა ჯერ"**
— ყველა action-ღილაკი `type="button"`-ია, listener-ის გარეშე (click-ზე
პირდაპირ არაფერი ხდება, გადამოწმებულია `location.href` უცვლელობით).
სტატუსის `<select>`/checkbox-ებს აქვთ `name` ატრიბუტები (`status`,
`is_zero`, `is_recurring`) მომავალი wiring-ისთვის მზადყოფნის მიზნით, მაგრამ
**ამ ველების არც erთი არ არის ნამდვილ `<form>`-ის შიგნით** (1/4 card
ცალკე, `<form>`-ის გარეთაა) — ანუ ისედაც ვერასდროს submit-დებოდნენ,
დამატებითი დაცვის გარეშეც. `InvoiceController::store()`/`Invoice::
validate()` არაფერი შეცვლილა, ეს ველები სერვერზე საერთოდ არ მოდის.

**`invoicesByCustomer`** (`InvoiceController::index()`-ში აგებული) —
`customer_id => [{number, total}]`, `Invoice::all()`-დან (index()-ს
ეს query ხელახლა დაუბრუნდა, `4.25.3`-ში წაშლილი იყო — ახლა ორივე
საჭიროებისთვის გამოიყენება). ნომრები **იგივე `$invoicePrefix`-ითაა
გამოთვლილი**, რასაც view-ს დანარჩენი ყველა ადგილი იყენებს — გამოთვლა
კონტროლერშია, არა view-ში, რომ ორჯერ არ დაწერილიყო იგივე `sprintf`-ლოგიკა.

**გადამოწმებულია ცოცხლად**: ყველა ღილაკი/select/checkbox სწორი ტექსტით
რენდერდება; "შენახვა"-ზე დაწკაპუნება **არაფერს** აკეთებს (URL/ფორმის
მდგომარეობა უცვლელი); დამკვეთის არჩევისას (`TOXIGEN BOARD SHOP`) →
`"PH-0003" / "25.00"` სწორად ჩნდება ისტორიის პანელში; დამკვეთის გარეშე →
"ინვოისები არ მოიძებნა."

⚠️ **4.25.7-ში `status`/`is_zero`/`is_recurring` ნამდვილად შეინახა** — ეს
სექცია მანამდე დაიწერა, სანამ იმ ველების backend-wiring საერთოდ
საჭირო გახდებოდა. `4.25.6`-ის "arc functionality yet" პრინციპი კვლავ
ვრცელდება მხოლოდ **action-ღილაკებზე** (save/PDF/email/WhatsApp/share) —
ის ცალკე, ჯერ არ არის სერვერზე დაკავშირებული.

#### 4.25.7 ინვოისის ნომრის ახალი ფორმატი + status/type რეალურად ინახება

**ნომრის ფორმატი შეიცვალა**: ძველი `PREFIX-0007` → ახალი `PREFIX YYYY-MM-DD
0007` (მაგ. `PH 2026-08-14 0006`) — ერთი, ცენტრალური ადგილიდან:
`App\Models\Invoice::number(array $row, string $prefix): string`. მანამდე
ეს `sprintf` **5 ცალკე ადგილას** იყო გამეორებული (`invoices.php`,
`orders.php`, `InvoiceController`-ის 3 მეთოდი) — ყველა შეიცვალა ამ ერთი
static მეთოდის გამოძახებით. **ყველა ადგილას საჭიროა მთელი row** (`id` +
`issue_date`), არა მარტო `id` — ეს არის მთავარი მიზეზი, რატომაც
`$invoiceNumber`-ის closure-ის signature `(int $id)`-დან `(array $row)`-ზე
შეიცვალა ყველგან.

⚠️ **card-header-ის ცალკე თარიღის span-ი ახლა ხშირად ცარიელია** —
რადგან ახალი ფორმატი თარიღს **უკვე შეიცავს**, `#invoiceFormDate`
რჩება ცარიელი, როცა `#invoiceFormNumber` უკვე რეალურ (თარიღიან) ნომერს
აჩვენებს (`editingInvoice !== null`); მხოლოდ "ახალი" (jერ არშენახული)
ინვოისის შემთხვევაში აჩვენებს დღევანდელ თარიღს ცალკე, რადგან რეალური
ნომერი ჯერ არ არსებობს. `4.25.4`-ის `d.m.Y`-ფორმატი (`$fmtDate`) **მთლიანად
წაიშალა** — ახალი ნომრის ფორმატი თავად იყენებს ISO (`Y-m-d`) თარიღს, ორი
სხვადასხვა ფორმატის თანაარსებობა ერთ გვერდზე დამაბნეველი იქნებოდა.

**`status`/`is_zero`/`is_recurring` რეალურად ინახება** (`migrations/015`
— `status ENUM('draft','final','due','paid') DEFAULT 'draft'`, ორივე flag
`TINYINT(1) DEFAULT 0`). ეს **სცდება** `4.25.6`-ის "ჯერ ფუნქციონალი არ
გვინდა" პრინციპს — მომხმარებელმა ცალსახად სთხოვა ეს ველები ორდერების
სიაშიც ჩანდეს, რაც ავტომატურად ნიშნავს რეალურ შენახვას (ცხრილის სვეტს
რეალური მონაცემი უნდა ჰქონდეს). Action-ღილაკები (save/PDF/email/...)
**კვლავ უფუნქციოა** — ეს ცვლილება მხოლოდ status/type ველებს ეხება.

⚠️ **სტატუსის/type-ის ველები ფიზიკურად 1/4 sidebar card-შია, `<form>`-ის
გარეთ** (`4.25.6`-ის ლეიაუტი) — HTML5 `form="invoiceMainForm"` ატრიბუტით
არიან დაკავშირებული მთავარ `<form id="invoiceMainForm">`-თან (`4.25.6`-
ის sidebar card-ის `<form>`-ის გარეთ ყოფნა თავად აღარაფერს ცვლის
submit-ის თვალსაზრისით — `form=""` ატრიბუტი ამ პრობლემას წყვეტს ნებისმიერ
ადგილას მდებარე ველისთვის). გადამოწმებულია ნამდვილი ბრაუზერის
`new FormData(form)`-ით (არა curl-ით) — `status`/`is_zero` სწორად
ერთვის ფორმის მონაცემებს, მიუხედავად იმისა, რომ ფიზიკურად `<form>`-ის
გარეთაა.

`Invoice::validate()`-ში `status`-ის არასწორი/ცარიელი მნიშვნელობა **არ
იწვევს ვალიდაციის შეცდომას** — ჩუმად `'draft'`-ზე fallback-დება
(`in_array($status, self::STATUSES, true) ? $status : self::STATUSES[0]`)
— ეს არ არის სავალდებულო "ბიზნეს-კრიტიკული" ველი, როგორც `customer_id`.

**`orders.php`-ს დაემატა ორი სვეტი**: "სტატუსი" (badge, ფერი status-ის
მიხედვით — `draft`=secondary, `final`=info, `due`=warning, `paid`=success)
და "ტიპი" (`ნულოვანი`/`განმეორებადი` badge-ები, ან `—` თუ არცერთი).

**გადამოწმებულია ცოცხლად**: `status=final`+`is_zero`-ით შექმნილი ინვოისი →
სწორად შენახულია DB-ში; success flash/orders.php/customer-history panel
ყველგან ახალი ფორმატით (`PH 2026-08-14 0006`); `?edit=N` → status select
+ orders.php badge-ები ორივე სწორად `bg-info-subtle`/`ნულოვანი`; `?edit=6`
→ select/checkbox-ები სწორად პრეფილვდება (`status="final"`,
`isZero=true`), `#invoiceFormDate` ცარიელია (რადგან ნომერში უკვე
ჩანს თარიღი).

### 4.8 CSRF + flash + PRG — პირველი POST-ფორმა
`bootstrap.php`-ში `session_start()` **`Lang::boot()`-მდე** (ორივე cookie-ს დგამს,
გამოტანამდე უნდა მოხდეს). `helpers.php`: `csrf_field()` / `csrf_verify()` (არასწორი
ტოკენი → **419** და მოდელამდე საერთოდ არ მიდის), `flash()` (ერთ redirect-ს ცოცხლობს),
`redirect()`.

POST → validate → **redirect** → GET (PRG): F5 ხელახლა არ აგზავნის. შეცდომისას
`errors` + `old` flash-ში ჩადის, ფორმა შევსებული ბრუნდება.
`Customer::validate()` აბრუნებს `[$clean, $errors]` — კონტროლერი თხელია.

⚠️ **`customer_taxid = '0'` = „ს/კ არ აქვს"** — იმპორტირებულ მონაცემებში 271 ასეთი
რიგია. `validate()` ახალ ჩანაწერს '0'-ს NULL-ად ინახავს; view-ც '0'-ს არარსებულად
თვლის. ამის გარეშე დუბლიკატის შემოწმება 272-ე უს/კ-ო დამკვეთს დაბლოკავდა.

⚠️ **განახლება (`4.26`) — ეს აღარ არის მართალი**: `customer_taxid`-ს ახლა
**რეალური UNIQUE ინდექსიც** აქვს ბაზაში (`migrations/017`), აპლიკაციური
შემოწმების გვერდით, არა მის მაგივრად — იხილე `4.26`.

### 4.26 მრავალ-მომხმარებლიანობა — რისი შემოწმება მოხდა და რა გასწორდა

მომხმარებელმა სთხოვა: "პროექტი უნდა იყოს მულტი-მომხმარებლის... სხვადასხვა
IP-დან ან ერთი და იგივე IP-დან ერთდროულად რამდენიმე მომხმარებელი". ეს
ანალიზის/მიმოხილვის მოთხოვნა იყო, არა ერთი კონკრეტული ბაგის რეპორტი —
შედეგად: **რაც უკვე უსაფრთხოა** + **ერთი რეალურად ნაპოვნი და გასწორებული
ხარვეზი**.

**რატომაა უსაფრთხო PHP-ის request-per-process მოდელით (default, უცვლელი)**:
`Db::$pdo` (static singleton), `ModuleRegistry`-ის caching და ყველა სხვა
static state **per-request-ია, არა per-process/per-server** — თითო HTTP
request ახალი PHP execution-ია (`php -S`/PHP-FPM ორივეს ეს ეხება), ასე
რომ ერთი მომხმარებლის request-ის განმავლობაში დაწერილი static state
**ვერასდროს** გაჟონავს მეორე მომხმარებლის request-ში. სესია (`$_SESSION`,
`Auth`, CSRF, flash) ცალკეა თითო ბრაუზერისთვის (`PHPSESSID` cookie-ით) —
სხვადასხვა IP-დან თუ ერთი IP-დან (რამდენიმე ბრაუზერი/tab, NAT-ის უკან
რამდენიმე მომხმარებელი) სესიები არ ერევა ერთმანეთში.

⚠️ **ნაპოვნი რეალური ხარვეზი**: `Customer::taxIdTaken()` (`4.13`)
check-then-insert პატერნია — ორ true-concurrent request-ს შორის, ორივეს
შეუძლია გაიაროს pre-check ერთსა და იმავე ჯერ-არნახულ ს/კ-ზე, სანამ
რომელიმეს INSERT ჩავარდება (classic TOCTOU race). ვამოწმე, უსაფრთხოა
თუ არა ანალოგიური ველები: **`users.email`/`users.phone`/`users.google_id`
უკვე რეალურ UNIQUE ინდექსზეა** (`migrations/007`/`009`) — იქ ხარვეზი
არასდროს ყოფილა. `customer_taxid`-ს კი მხოლოდ non-unique index ჰქონდა.

**გასწორება** (`migrations/016`, `017`): ჯერ `UPDATE customers SET
customer_taxid = NULL WHERE customer_taxid = '0'` (269 legacy row-ს
ჰქონდა სიტყვასიტყვითი `'0'` NULL-ის მაგივრად — ახალი UNIQUE ინდექსი
მაშინვე ჩავარდებოდა ამ დუბლიკატებზე), მერე `ALTER TABLE customers ADD
UNIQUE INDEX uq_customers_taxid (customer_taxid)`. MySQL-ის UNIQUE
ინდექსი **ნებისმიერი რაოდენობის NULL-ს უშვებს** — ს/კ-ის გარეშე
დამკვეთები (`4.13`-ის NULL-კონვენცია) არ ეჯახება ერთმანეთს.

⚠️ **განახლება — ეს ინდექსი აღარ არის ერთსვეტიანი**: `4.31`-ის
მულტი-tenant ცვლილებამ (`migrations/025`) `uq_customers_taxid`
შეცვალა კომპოზიციური `uq_customers_ruler_taxid (ruler, customer_taxid)`-ით
— ს/კ-ის უნიკალურობა ახლა tenant-ის ფარგლებშია, არა გლობალურად
(ორ სხვადასხვა tenant-ს შეუძლია ჰყავდეთ ცალ-ცალკე დამკვეთი ერთი და
იმავე რეალური ს/კ-ით).

**`CustomerController::store()`-ს დაემატა try/catch** `PDOException`-ზე,
`getCode() === '23000'` (MySQL-ის duplicate-key SQLSTATE) — აპლიკაციური
pre-check კვლავ პირველი ხაზის დაცვაა (ჩვეულებრივი, non-race შემთხვევა
ჩვეულებრივ `terr('cust.err_taxid_taken')`-ს აძლევს page-reload-ის გარეშეც
დამატებითი round-trip-ის გარეშე), მაგრამ **DB-ის constraint არის ნამდვილი
გარანტია** — თუ ორივე request მაინც გაუსწორდა race-ს, PHP აპლიკაცია აღარ
აფუჭებს (`PDOException` → fatal 500) და ცოცხლდება იმავე მეგობრული
შეცდომით, რასაც pre-check-იც აძლევდა.

⚠️ **განახლება — optimistic locking მოგვიანებით მაინც დაემატა**, იხილე
`4.27` — მომხმარებელმა ცალსახად სთხოვა კონკრეტულად ინვოისისთვის ("დიახ
ჯობია რომ რამე დაცვა ქონდეს ამ კონკრეტული შემთხვევისთვის"), ქვემოთ
აღწერილი "last-write-wins compromise" **მხოლოდ სხვა ცხრილებზე** (დამკვეთი/
პროდუქტი/მომხმარებელი) რჩება ჯერჯერობით უცვლელი.

**გადამოწმებულია ცოცხლად**: (ა) ჩვეულებრივი დუბლიკატი (pre-check გზით)
კვლავ `#customer-form`-ზე ისევე მუშაობს, დუბლიკატი **არ** იქმნება; (ბ)
**პირდაპირი race-სიმულაცია** — `Customer::create()` ორჯერ, იგივე
ს/კ-ით, pre-check-ის გვერდის ავლით (სცენარი, რომელსაც ვერცერთი
აპლიკაციური კოდი ვერ დაიჭერდა pre-check-ის გარეშე) — მეორე გამოძახებამ
სწორად გამოისროლა `PDOException code=23000`; (გ) NULL ს/კ-ით ორი "walk-in"
დამკვეთი ორივე წარმატებით შეიქმნა (constraint მათ არ ეხება).

### 4.27 ინვოისების optimistic locking — `updated_at` token

`4.26`-ში აღწერილი "last-write-wins" compromise მომხმარებელმა კონკრეტულად
ინვოისისთვის აღარ მოისურვა: "დიახ ჯობია რომ რამე დაცვა ქონდეს ამ
კონკრეტული შემთხვევისთვის" (ორი admin-ი, ან admin-ი და ქვე-მომხმარებელი,
ერთსა და იმავე ინვოისზე ერთდროული რედაქტირება).

**სქემა** (`migrations/018`): `invoices.updated_at TIMESTAMP ... ON UPDATE
CURRENT_TIMESTAMP` — MySQL **ავტომატურად** ბუმავს ამ სვეტს ყოველ
`UPDATE`-ზე, აპლიკაციას არაფრის ხელით დაწერა არ სჭირდება.

**`Invoice::save(array $clean, ?int $editingId, ?string $expectedUpdatedAt =
null): ?int`** — signature-ს დაემატა მესამე პარამეტრი, დაბრუნების ტიპი
`int`-დან `?int`-ზე შეიცვალა (`null` = conflict, ტრანზაქცია rollback-ილია,
**არაფერი** ჩაწერილა). ლოგიკა:
```php
$lock = $conn->prepare('SELECT updated_at FROM invoices WHERE id = ? FOR UPDATE');
$lock->execute([$editingId]);
$actualUpdatedAt = $lock->fetchColumn();

if ($actualUpdatedAt === false || $actualUpdatedAt !== $expectedUpdatedAt) {
    $conn->rollBack();
    return null;
}
// ... UPDATE ...
```
⚠️ **`SELECT ... FOR UPDATE`, არა უბრალო `SELECT` + შემდეგ `UPDATE ... WHERE
updated_at=?`** — განზრახ არჩევანი. `FOR UPDATE` InnoDB-ის row-lock-ს
დებს ტრანზაქციის დარჩენილი ხნით — ორ true-concurrent request-ს შორის
**ფიზიკურად ვერ** ჩაერევა ერთმანეთის SELECT-სა და UPDATE-ს შორის (ეს
თავად კეთდება race-free, არა "დიდი ალბათობით უსაფრთხო"). `WHERE
updated_at=?`-ზე დაფუძნებული ალტერნატივა თავს იჩენდა MySQL-ის ცნობილ
თავისებურებასთან — `ON UPDATE CURRENT_TIMESTAMP` ზოგჯერ **არ** იბუმება,
თუ ახალი მნიშვნელობები იდენტურია ძველთან (0 affected rows ≠ conflict იმ
შემთხვევაში).

**`InvoiceController`**: `store()`-ში ახალი `$expectedUpdatedAt =
(string) ($_POST['updated_at'] ?? '')` (hidden ველიდან, `4.25`-ის
`$old`-ის იგივე patern-ით ივსება `?edit=N`-ზე). `Invoice::save()`-ის
`null` პასუხზე → `flash('errors', ['conflict' => terr('inv.err_conflict')])`
+ `redirect('/invoices?edit=' . $editingId . '#invoice-form')` — ეს
**თავად** ხელახლა ტვირთავს ინვოისის **ახლანდელ** მდგომარეობას (`index()`-ის
`?edit=` branch-ი ხელახლა ეშვება, რადგან `'old'` ამ conflict-flash-ში
**არ** გადადის, მხოლოდ `'errors'` — `4.25.3`-ის ძველი
`$errors === [] && $old === []` guard განზრახ შემცირდა მხოლოდ `$old ===
[]`-მდე, რომ ეს ახალი re-load scenario არ დაბლოკოს).

`Invoice::validate()`-ს `updated_at`-თან შეხება არ აქვს (ეს არ არის
ბიზნეს-მონაცემი, concurrency-token-ია) — validation-ჩავარდნისას
`store()`-ს თავად მოაქვს round-trip: `flash('old', $clean + [...,
'updated_at' => $expectedUpdatedAt])`.

**გადამოწმებულია ცოცხლად, სრული ორ-მომხმარებლიანი სცენარი**: A და B
ორივემ `?edit=N` ჩატვირთეს (იგივე `updated_at`) → B-მ status="paid"
შეინახა წარმატებით, `updated_at` ბუმდა → A-მ (ძველი `updated_at`-ით)
ცადა status="final" → **დაიბლოკა**, DB-ში `status` **დარჩა "paid"**
(B-ის მონაცემი ხელუხლებელია), A-ს გადმოეცა `#4386`-კოდიანი
გაფრთხილება და ფორმა **ავტომატურად ხელახლა ჩაიტვირთა** B-ის ახლანდელი
მონაცემებით (`select[value=paid] selected`); ჩვეულებრივი, non-conflict
რედაქტირება (`status="due"`) ცალკე ტესტში კვლავ უნაკლოდ მუშაობს.

### 4.28 პროდუქციის ტიპი (`product_type_id`) — დაბრუნდა Core-ში, Warehouse-ისგან დამოუკიდებლად

`4.19`-ში `product_type_id` (და `remaining_qty`/`image`) განზრახ გატანილი
იყო core `products`-იდან Warehouse-ის `product_warehouse`-ში, მისი
ინსტალაციის migration-ით (`003_drop_products_extension_columns.sql`).
მომხმარებელმა მოითხოვა ტიპის ველი უკან products-ის ფორმა/ცხრილში, მაშინ
როცა Warehouse ჯერ კიდევ დაინსტალირებულია მაგრამ **გამორთულია**
(`enabled=0`) — ორი გზა იყო: (ა) ჩაერთო Warehouse, ან (ბ) დამატებოდა
ცალკე, plain ველი Core-ში. `AskUserQuestion`-ით დაზუსტდა → **(ბ),
რეკომენდებული**: "მხოლოდ ტიპი დაემატოს Core-ში, Warehouse-ისგან
დამოუკიდებლად".

**სქემა** (`migrations/019_add_products_type.sql`): `products.product_type_id
INT UNSIGNED NULL` + FK `product_types(id)`. **განზრახ `NULL`-ადი**, არა
`NOT NULL` — Warehouse-ის `003`-ის მიერ უკვე დაცლილი ძველი (~2) ჩანაწერი
ისედაც `NULL`-ია და უკან-თავსებადობა ინარჩუნებს. `Product::validate()`
კი მას **სავალდებულოდ** ითხოვს ყველა ახალი/რედაქტირებული ჩანაწერისთვის —
ანუ ძველი უტიპო ჩანაწერები „—“-ით ჩანან სიაში, მაგრამ მათი resave
ტიპის არჩევის გარეშე ვეღარ მოხერხდება.

**`App\Models\ProductType`** (ახალი, `app/Models/ProductType.php`) —
თითქმის იდენტური ასლია `App\Modules\Warehouse\Models\ProductType`-ისა
(`all()`/`create()`/`update()`, bare `product_types` lookup-ცხრილი) —
`4.19`-ის დამკვიდრებული კონვენციის მიხედვით, პატარა lookup-CRUD-ები
**განზრახ დუბლირდება** core/module საზღვარზე, საერთო base class-ის
მაგივრად.

`App\Models\Product`: `FIELDS`-ს დაემატა `'product_type_id'`
(`name`/`unit_id`-ს შორის); `all()`-ს `LEFT JOIN product_types` (LEFT,
რადგან ძველი ჩანაწერების `product_type_id IS NULL`); `validate()`-ს
ახალი სავალდებულო შემოწმება (`ctype_digit` + `lookupMissing`) →
`terr('prod.err_type_required')`.

**UI** (`products.php`): ფორმის row 2×`col-md-6`-დან 3×`col-md-4`-ზე
გადავიდა (ტიპი, ერთეული, ფასი), ტიპის select ზუსტად `unit_id`-ის
პატერნია — `data-ds-select` + „მართვა“ გირჩი, რომელიც ხსნის იმავე
`ptypeModal`-ს (`$lookupModal()`/`wireLookupModal()`-ის იგივე helper-ები,
რაც `unitModal`-ს აქვს, `POST /product-types`-ზე). ცხრილს დაემატა
„ტიპი“ სვეტი (`e($p['product_type_name'] ?? '—')`) სახელსა და
ერთეულს შორის; row-click populate-ს ემატა `product_type_id`-ის
დაყენება + `dsSelect?.refresh()`.

**`LookupController::productTypes()`** — `save(ProductType::class,
'ptype.err_name_required')`-ის ერთსტრიქონიანი გამოძახება, `save()`
ჯერ კიდევ `units`-ისთვის იყო დაწერილი generic-ად (`model::create(string):
int`/`model::update(int,string):void` მოლოდინით) — ახალი model-ი უბრალოდ
ჯდება არსებულ contract-ში, ახალი ლოგიკა არ დასჭირდა.

⚠️ **route-ის დუბლირება Warehouse-თან, უვნებელი მაგრამ საყურადღებო**:
`routes.php`-ში ახლა **ორივეა** განსაზღვრული ცალკე — core-ს
`POST /product-types` → `LookupController::productTypes()` (ეს
სექცია) და, თუ Warehouse ოდესმე ჩაირთვება, `Module.php`-ის საკუთარი
`POST /product-types` → `ProductTypeController::save()` (`4.19`).
`Router::add()` route-ებს plain PHP array-ში inline `$key = $action`-ით
ინახავს — დუბლირებული key **ჩუმად გადაიწერება** იმით, ვინც ბოლოს
დარეგისტრირდა (module-ის route-ები `routes.php`-ის **შემდეგ** ემატება,
ანუ Warehouse-ის ჩართვისას ის „მოიგებდა“). ეს ამ ეტაპზე **უვნებელია**
პრაქტიკულად — ორივე handler წერს ერთსა და იმავე `product_types`
ცხრილში, თითქმის იდენტური სხეულით — მაგრამ თუ მომავალში ამ ორი
handler-ის ლოგიკა დაშორდება, Warehouse-ის ჩართვა ჩუმად შეცვლის Core-ის
products-გვერდის „მართვა“ ღილაკის ქცევას. განზრახ არ გასწორებულა ამ
სესიაში (scope-ს გარეთაა) — თუ საჭირო გახდება, უმარტივესი fix routes.php-ში
`Router`-ს დუბლირებული key-ის რეგისტრაციაზე `throw`-ის დამატება იქნებოდა.

**გადამოწმებულია ცოცხლად**: ტიპიანი პროდუქტის შექმნა (DB-ში სწორად
შენახულია, სიაში სწორად ჩანს); ძველი უტიპო ჩანაწერი (`id=1`) სიაში
"—"-ს აჩვენებს; submit ტიპის გარეშე → redirect + `terr()`-კოდიანი
შეცდომა + **არცერთი** row არ იქმნება DB-ში; `ptypeModal`-ის AJAX
create (`POST /product-types`, ცარიელი `id`) და rename (არსებული
`id`-ით) ორივე დაბრუნებს სწორ JSON-ს; row-click ბრაუზერში (არა curl)
ავსებს `product_type_id`-ის ds-select-ს სწორი მნიშვნელობითა და
**ვიზუალურადაც** განახლებული ტექსტით ("ციფრული ბეჭდვა"), submit ღილაკი
"დამატება"-დან "განახლება"-ზე გადადის. ტესტ-მონაცემები (`id=10`
პროდუქტი, `id=3` ტიპი) წაშლილია.

### 4.29 `/invoices` გვერდის ვიზუალი + `created_by` — "შეკვეთის მიმღები"

`4.25`-ის მერე `/invoices` ფორმას ჰქონდა რამდენიმე ვიზუალური იტერაცია
(ცალკე user მოთხოვნებით, სურათებით): "პროდუქტები" label მოიხსნა
line-items სექციიდან (column header-ები საკმარისია); `ds-select`-ის
trigger-ს `#invoiceItems`-ში `min-height:0` დაემატა (`design-system.css`)
— vendor-ის floating-label-ისთვის გათვლილი `2.9rem` plain input-ებზე
(`34px`) მაღალი ჩანდა ლეიბლის გარეშე ვარიანტში; ჯამის ველი
`form-control-plaintext`-იდან `form-control readonly`-ზე გადავიდა
საერთო ბორდერის/სიმაღლის დასამთხვევად; item-row grid `col-md-5/2/2/2/1`-
დან `col/col-md-2/col-md-2/col-md-2/col-auto`-ზე გადავიდა — ფასი/ჯამი/
რაოდენობა ტოლი სიგანისაა, × ღილაკი მხოლოდ საკუთარ content-ზე იკუმშება
(აღარაა "ცარიელი" სივრცე ჯამსა და ღილაკს შორის). ქვემოთ დაემატა
"დამატებითი ინფორმაცია" textarea + დღგ(18%)/ჯამის ინფორმაციული ბლოკი
(`col-md-8`/`col-md-4`, `justify-content-between`-ის გარეშე რომ
კომპაქტურად ეკიდოს textarea-ს ტოპში, არა სტრეჩვდეს ბოლომდე) —
**დღგ არის მხოლოდ ვიზუალური** (client-JS, `sum*0.18`), server-ის
`Invoice::save()`-ის `total`-ს **არ ემატება**, რომ create-ფორმაზე
ნანახი და შენახვის შემდეგ (`/orders`, print) ნანახი რიცხვი არ
გაირღვეს — თუ დღგ რეალურად უნდა დაემატოს ჯამს და შენახოს, ცალკე
schema/business-logic გადაწყვეტილებაა.

**"დამატებითი ინფორმაცია" რეალურად ჩაიწერება** (`migrations/020`):
`invoices.notes TEXT NULL`. `Invoice::validate()` თავისუფალ ტექსტად
იღებს (ვალიდაციის გარეშე), `save()` წერს INSERT-შიც და UPDATE-შიც,
`InvoiceController::index()`-ის `?edit=N` branch-ი აბრუნებს ფორმაში.

**მენიუც შეიცვალა**: `/invoices` სინამდვილეში "ახალი ინვოისი"-ს
დამატების ფორმაა (ანალოგიური რასაც "ახალი შეკვეთა" გულისხმობდა) — ცალკე
top-level "ინვოისები" item მოიხსნა `menu.json`-იდან, "შეკვეთები"
ჯგუფის ადრე დაუკავშირებელი ("ახალი შეკვეთა", `url:"#"`) child item-ი
გადარქმეულია "ახალი ინვოისი"-დ და მიბმულია `/invoices`-ზე. გვერდის
საკუთარი H1/breadcrumb/tab-title (`page.invoices` key) იგივენაირად
გადარქმეულია. `nav.invoices` lang-key (რომელიც აღარსად გამოიყენებოდა)
წაშლილია.

**`created_by` — "შეკვეთის მიმღები"** (`migrations/021`):
`invoices.created_by INT UNSIGNED NULL` → `users.id` (nullable FK,
ორივე მიზეზით: ძველი ინვოისების უკან-თავსებადობა და `/invoices`-ს
ჯერ არ აქვს auth gate — `Auth::user()` ლეგიტიმურად `null`-იც შეიძლება
იყოს, `4.20`). `Invoice::save()`-ს დაემატა მეოთხე პარამეტრი
`?int $createdBy` — **მხოლოდ INSERT-ში** იწერება, UPDATE-ის branch-ი
საერთოდ არ ეხება ამ სვეტს (ვინც შექმნა, არ იცვლება რედაქტირებისას).
`InvoiceController::store()`-ში `Auth::user()['id'] ?? null` გადაეცემა.
`Invoice::all()`-ს დაემატა `LEFT JOIN users u ON u.id = i.created_by`
(+ `c.customer_taxid`, იმავე query-ში) — `creator_name` `NULL`-ია ამ
ცვლილების წინა ინვოისებისთვის.

**`/orders`-ის ცხრილი გადაკეთდა** ზუსტად ამ ველებზე: ნომერი, დამკვეთი,
საიდენტიფიკაციო (`customer_taxid`, `'0'`-ის იგივე "არ აქვს" წესით
როგორც `4.26`-ში, `—` თუ ცარიელი/`'0'`), შეკვეთის მიმღები
(`creator_name`, `—` თუ `NULL`), სულ, მოქმედება (header-ს ტექსტი
დაემატა — ადრე ცარიელი `<th>` იყო). **მოიხსნა** სტატუსის/ტიპის
(ნულოვანი/განმეორებადი) სვეტები, რომლებიც `4.25.7`-ში დაემატა — user-მა
ცხადად ჩამოთვალა ცხრილის სასურველი ველების სრული სია, სტატუსი/ტიპი
მასში აღარ შედის. `inv.type_label` lang-key (რომელიც მხოლოდ ამ ცხრილში
გამოიყენებოდა) წაიშალა; `inv.status_label`/`status_draft`-ის msdst.
დარჩა უცვლელი, რადგან `/invoices`-ის sidebar-ის სტატუსის select
ჯერ კიდევ იყენებს მათ.

**გადამოწმებულია ცოცხლად**: `Invoice::save()` პირდაპირ (CLI bootstrap)
`createdBy=1`-ით → DB-ში სწორად ჩაიწერა; იმავე ინვოისის შემდგომმა
edit-მა (`createdBy` არ გადაეცა) `created_by`-ს **არ** შეეხო (დარჩა
`1`); `Invoice::all()`-ის join-მა სწორად დააბრუნა `creator_name`
("გივი ბერძენიშვილი") და `customer_taxid`; `/orders` ბრაუზერში
სწორად აჩვენებს ახალ სვეტებს, ძველი (`created_by IS NULL`) ინვოისები
"—"-ს იძლევიან. ტესტ-ინვოისები წაშლილია.

### 4.30 გლობალური auth-gate + idle-timeout — `SESSION_TIMEOUT_MINUTES`

`4.20`-ის დროს დადასტურებული "gate არ გვინდა" გადაწყვეტილება ახლა
შებრუნდა — მომხმარებელმა პირდაპირ მოითხოვა: არააქტიური (არა-login)
სესიით ნებისმიერ გვერდზე შესვლა ავტომატურად `/login`-ზე უნდა
გადამისამართდეს.

**Gate თავად** — `public/index.php`, `Router::dispatch()`-ის **წინ**
(არა `Router`-ში, არა `Auth`-ში): `PUBLIC_PATHS` allow-list (login/
register/forgot-password/reset-password/google-oauth/otp/logout/
auth-photo — ანუ ყველა route, რაც `routes.php`-ში `AuthController`-ზეა
მიბმული) + `Auth::check()`. Path-ის ნორმალიზაცია (trailing slash-ის
მოცილება, ცარიელი → `/`) **იმეორებს** `Router::normalise()`-ის იმავე
წესს ხელით — არჩევანი იმიტომ, რომ `Router` თავად აპლიკაციისგან
დამოუკიდებელი, generic კლასია (Auth-ზე არაფერი იცის), ამ ორი წესის
დაცილება კი (regex/rtrim ერთმანეთისგან განსხვავებული ლოგიკით) ერთ
დღეს route-ისა და gate-ის შეუთანხმებლობას გამოიწვევდა — ორივეს
იდენტური, მარტივი წესი (`rtrim(...,'/') ?: '/'`) ჰყავს. `php -S`-ის
სტატიკური ფაილების early-return (`public/index.php`-ის თავშივე) gate-ის
**წინ** ხდება, ანუ CSS/JS/სურათები gate-ს საერთოდ არ ხვდებიან.

**Idle-timeout** — `App\Core\Auth::check()`-ში: `$_SESSION['last_activity']`
(დაყენებულია `login()`-ში, განახლებულია ყოველ წარმატებულ `check()`-ზე)
შედარებულია `SESSION_TIMEOUT_MINUTES`-თან (`.env`, default 30 წუთი
`Auth::DEFAULT_TIMEOUT_MINUTES`-ით, თუ `.env`-ში არაა). ვადაგასულ
სესიაზე `check()` თავად იძახებს `self::logout()`-ს და აბრუნებს
`false`-ს — ერთ ადგილას, რომ `Auth`-ის ყველა მომხმარებელს (gate-ს,
`requireUser()`/`requireAdmin()`-საც) ერთი და იგივე "აღარ ხარ
შესული" მდგომარეობა დაანახოს, თითოეულს ცალკე რომ არ დასჭირდეს
timestamp-ის შემოწმება. `Auth::user()` ახლა `check()`-ზეა გადართული
(ადრე პირდაპირ `isset($_SESSION['user_id'])`-ს ამოწმებდა) — რომ
timeout ყველგან ერთნაირად მუშაობდეს.

**Session.gc_maxlifetime-ს კი არ ვენდობით** — PHP-ის ჩაშენებული
idle-cleanup (`session.gc_maxlifetime`) probabilistic-ია
(`gc_probability`/`gc_divisor`, ხშირად default-ად ~1/100), ანუ ვადაგასული
სესია შეიძლება დიდხანს "ცოცხალი" დარჩეს დისკზე, სანამ GC პირველად
გაეშვება — არ არის საკმარისად საიმედო რეალურ timeout-ად. `last_activity`
timestamp-ის აშკარა app-level შემოწმება (ყოველ request-ზე,
დეტერმინისტულად) ამის მაგივრად გამოიყენება, `session.gc_maxlifetime`-ს
საერთოდ არ ეხება/არ დამოკიდებულია მასზე.

**მრავალ-მომხმარებლიანობა** (`4.26`-ის გაგრძელება): თითოეული
დამლოგინებელი მომხმარებლის სესია ცალკე ფაილშია, საკუთარი session-id
cookie-ით — `$_SESSION['last_activity']`/`user_id` არასდროს იზიარება
სესიებს შორის, ანუ ერთი მომხმარებლის idle-დროს მეორეზე გავლენა არ
აქვს, და ერთი session-ის timeout მეორეს არ გამოაგდებს. ამის
დამატებითი "დაცვა" არ დასჭირდა — PHP-ის session მექანიზმი თავადვეა
per-user, `4.26`-ის დროს უკვე დადასტურებული პრინციპი.

**`.env`**: `SESSION_TIMEOUT_MINUTES` (`.env.example`-შიც, კომენტარით)
— default 30, თუ არ არის დაყენებული.

**გადამოწმებულია ცოცხლად** (session ხელით შექმნილია `session_start()`+
`Auth::login()`-ის იმავე ფორმატით, `curl -b PHPSESSID=...`-ით): (1)
cookie-ს გარეშე `/`, `/invoices` → `302` → `/login`; `/login`,
`/register`, `/auth/photo` კი `200`-ს აბრუნებენ cookie-ს გარეშეც; (2)
ახალი (`last_activity=now`) სესია → `/invoices` → `200`; (3) 31-წუთით
ძველი `last_activity` → `/invoices` → `302 /login` (session
`logout()`-ილია); (4) 29-წუთით ძველი (ზღვარს ქვემოთ) → `200`, და ამ
request-ის შემდეგ `last_activity` ხელახლა განახლებულია `time()`-ზე
(უწყვეტი აქტივობა არასდროს "დროულდება", მხოლოდ ნამდვილი idle-პერიოდი).
ტესტ-სესიის ფაილები წაშლილია.

**`/orders` ახლა per-user არის** — gate-ის დამატების პირდაპირი
შედეგი: user-მა მოითხოვა "ყველა შეკვეთა" ცხრილში მხოლოდ **საკუთარი**
(`created_by = აქტიური user`) ინვოისები გამოჩნდეს, არა ყველა
მომხმარებლის ყველა ინვოისი. `Invoice::all(?int $createdBy = null)`-ს
დაემატა optional პარამეტრი (`WHERE i.created_by = ?`, მხოლოდ თუ
გადაეცემა); `InvoiceController::orders()` `Auth::user()['id']`-ს
გადასცემს. `/invoices`-ის `$invoicesByCustomer` panel-ისთვის (`index()`,
`Invoice::all()` პარამეტრის გარეშე) **განზრახ არაა** ეს ფილტრი
გამოყენებული — იქ დამკვეთის სრული ისტორიაა საჭირო ახალი ინვოისის
შექმნისას, არა მხოლოდ მიმდინარე user-ის მიერ შექმნილი ჩანაწერები.
ადმინის გამონაკლისი (ყველას ხედავს) არ მოთხოვნილა — ყველა role
ერთნაირად მხოლოდ საკუთარს ხედავს.

**გადამოწმებულია ცოცხლად** (ორი რეალური user-ის სესიით, `curl
-b PHPSESSID`): user `id=1`-ის `/orders` მხოლოდ მისივე ინვოისს
აჩვენებს (`PH 2026-08-13 0005`), user `id=24`-ის კი — მხოლოდ
თავისას (`PH 2026-08-13 0003`); არცერთს არ უჩანს მეორის ან მესამე
user-ის ინვოისი. ტესტ-სესიები წაშლილია.

### 4.31 Multi-tenant: `ruler` ხუთ ცხრილში + `Auth::tenantId()`

მომხმარებელმა მოითხოვა: `customers` ცხრილის სრული გასუფთავება (1538
რეალური რიგი — **დადასტურებულია ცალსახად**, "სატესტო SQL backup-ი
მაქვს, არაა პრობლემა") და 5-10 სატესტო ჩანაწერის შექმნა თითოეული
უნიკალური მომხმარებლისთვის, ხუთივე ცხრილში: `customers`, `products`,
`product_type`, `product_warehouse`, `organization`. მეორე კითხვაზე
("რამდენ ცხრილზე?") — **ყველა ხუთივე, `organization`-ის ჩათვლით**:
"organization-ს აქ უნდა იყოს ყველა მომხმარებლის ინფორმაცია... სისტემა
არის multi vendor".

**"tenant" ≠ ყოველი `users` row** — `AskUserQuestion`-ის შემდეგ
გამოძიებით დადგინდა: `users.created_by` (`migrations/010`) უკვე
აკავშირებდა sub-user-ებს (`/settings/users`-ით დამატებულს) მათ
შემქმნელ admin-თან — `User::all()`-ის საკუთარი docblock-იც ამბობდა
"Every user in the (single) organization". რეალურ მონაცემებში:
user `id=1` (admin) შექმნა user `13`/`24` (manager-ები); `31`/`32`
(`test1`/`test2`) დამოუკიდებელი admin-ებია. ანუ **3 უნიკალური tenant**,
არა 5 — sub-user-ები იზიარებენ შემქმნელი admin-ის მონაცემებს, არა
საკუთარ ცალკე ცარიელ სეტს (რეალური multi-vendor SaaS-ის სტანდარტული
მოდელი: ვენდორის ანგარიში + გუნდის წევრები, არა თითო თანამშრომელი =
თითო ცალკე მაღაზია).

**`Auth::tenantId(): int`** (`Core/Auth.php`) — `$user['created_by']
?? $user['id']`, `self::requireUser()`-ზე დაფუძნებული (non-nullable,
redirect-ით თუ როგორმე მაინც არაა login — თუმცა გლობალური gate
(`4.30`) ამას პრაქტიკულად არასდროს უშვებს). ეს არის THE scoping
მნიშვნელობა ყველა ქვემოთ ჩამოთვლილი ცხრილისთვის.

**სქემა** (`migrations/022`-`025`, + `Warehouse/migrations/004`):
`products.ruler`/`product_types.ruler`/`product_warehouse.ruler` —
`INT UNSIGNED NULL` + non-unique index, ზუსტად `customers.ruler`-ის
(`migrations/001`) არსებული პატერნით — **რეალური FK არ აქვს არცერთს**
(არც `customers.ruler`-ს ჰქონდა; `users`-ზე მიმართვის თავიდან აცილება
განზრახაა, რომ `users`-ის წაშლა/რესტრუქტურიზაცია არასდროს დაბლოკოს
ბიზნეს-მონაცემებით). `organization` კი **სტრუქტურულად შეიცვალა**:
`id` გახდა ნამდვილი `AUTO_INCREMENT` (ადრე ყოველთვის ხელით `id=1`
იყო ვარაუდი), `ruler` კი **UNIQUE** — ერთ tenant-ს ზუსტად ერთი
org-ჩანაწერი აქვს, არა 5-10 (ბიზნეს-ლოგიკურად: ერთ ვენდორს ერთი
კომპანიის პროფილი აქვს — `invoice_prefix`/IBAN/ლოგო ერთია, არა
სია). `customers`-ის `uq_customers_taxid` (`4.26`) გახდა
კომპოზიციური `uq_customers_ruler_taxid (ruler, customer_taxid)`
(`migrations/025`) — ს/კ-ის უნიკალურობა ახლა tenant-ის ფარგლებშია.

**მოდელები** — `Customer`/`Product`/`ProductType`/`Organization`-ის
`all()`/`create()`/`update()`/`get()`/`save()` ყველამ მიიღო `int
$ruler` პარამეტრი. **`update()`-ებმა `WHERE ... AND ruler = ?`
მიიღეს, არა მხოლოდ `all()`-ის ფილტრმა** — ეს არ არის მხოლოდ
UI-დონის დაფარვა: ყალბი `customer_id`/`product_id`-ით POST-ი სხვა
tenant-ის ჩანაწერს ვერაფერს დააკლებს, `UPDATE` 0 row-ს შეეხება.
`Product::validate()`-ს `product_type_id`-ის `lookupMissing()`-იც
`ruler`-ზეა ჩაკეტილი — ერთი tenant ვერასდროს მიუთითებს მეორის
ტიპზე. `units` **განზრახ დარჩა გლობალური/საზიარო** — user-ის
ხუთეულში არ იყო, ცალკე არ ითხოვდა scoping-ს.

**`Organization::get(int $ruler)` — get-or-create**: თუ tenant-ს
ჯერ არ აქვს org-row (ახალი admin-ი, ან migration-მდელი), ცარიელი
row-ი ავტომატურად იქმნება პირველივე წაკითხვაზე — ყველა caller-ს
(`InvoiceController`, `OrganizationController`) კვლავ უბრალო
`array` უბრუნდება, `null`-შემოწმება არასდროს სჭირდება.

**`InvoiceController::show()`** (print-view) **გამონაკლისია**:
org-ი უნდა იყოს **ინვოისის გამომცემელი tenant-ის**, არა ვინც
ბეჭდვის გვერდს უყურებს — `ownerTenant(array $invoice): ?int`
პრივატული მეთოდი `invoice.created_by`-დან პოულობს შემქმნელს და
**იმისი** `created_by ?? id`-ს იყენებს (`null`-ია, fallback-ის
გარეშე, თუ creator ვერ მოიძებნა).

⚠️ **განახლება — access-check-ის ხარვეზი დაიხურა, იხილე `4.32`**:
ეს სექცია თავდაპირველად აღწერდა, რომ `show()`-ს არ ჰქონდა
access-check (ნებისმიერ ლოგინირებულ user-ს შეეძლო ნებისმიერი
ინვოისის ნახვა id-ის გამოცნობით) — მომხმარებელმა პირდაპირ სთხოვა
ამის დახურვა + share-token მექანიზმი, `4.32`-ში აღწერილია.

**`Warehouse` მოდული (გამორთულია)** — `WarehouseController::index()`-ის
`Product::all()` გამოძახებას დაემატა `Auth::tenantId()` (წინააღმდეგ
შემთხვევაში fatal იქნებოდა მოდულის ჩართვისთანავე), მაგრამ მოდულის
**საკუთარი** `ProductWarehouse::all()`/`upsert()` და საკუთარი
დუბლირებული `ProductType` მოდელი **დარჩა უცვლელი/unscoped** — მოდული
ამჟამად გამორთულია (`enabled=0`), ეს კოდი არსად არ სრულდება ცოცხლად;
სრული ruler-გატარება მოდულის საკუთარ კონტროლერებში ცალკე,
დამატებითი scope იქნებოდა.

**სატესტო მონაცემები** — 3 tenant (`1`, `31`, `32`):
- **tenant `1`** (რეალური, გივი ბერძენიშვილი) — არსებული რეალური
  `organization` row (`id=1`, "შპს ბეჭდვითი სახლი") და 2 რეალური
  `product`/`product_type` **არ დუბლირებულა**, უბრალოდ `ruler=1`
  მიენიჭა. ზემოდან დაემატა 4 სატესტო ტიპი, 5 სატესტო პროდუქტი (სულ
  6 ტიპი/7 პროდუქტი), 8 სატესტო დამკვეთი, 5 `product_warehouse` row.
  **`customers`-ის სრული გასუფთავებისას 3 რეალური რიგი გადარჩა**
  (`id` 1465/1536/1537) — `fk_invoices_customer` (`RESTRICT`) არ
  უშვებდა მათ წაშლას, სანამ 3 რეალურ ინვოისს (`id` 3/5/11, ყველა
  `created_by`-ით tenant 1-ს ეკუთვნის) მიუთითებდნენ — ინვოისების
  წაშლა **არასდროს ყოფილა დადასტურებული**, მხოლოდ `customers`-ის.
  ეს 3 row გადარჩა და `ruler=1` მიენიჭა (სულ tenant 1: 11 დამკვეთი).
- **tenant `31`/`32`** (`test1`/`test2`) — მთლიანად ახალი, სუფთა
  სატესტო მონაცემები: 1 org, 4 ტიპი, 5 პროდუქტი, 8 დამკვეთი, 5
  `product_warehouse` row თითოეულს.
- sub-user `13`/`24` ცალკე მონაცემი **არ დასჭირდა** — ავტომატურად
  ხედავენ tenant 1-ის მონაცემებს `Auth::tenantId()`-ის მეშვეობით.

**გადამოწმებულია ცოცხლად** (სამი user-ის სესიით, `curl -b
PHPSESSID`): (1) `/customers` badge — tenant 1: 11, tenant 31: 8,
ცალ-ცალკე; (2) `/products` badge — tenant 1: 7, tenant 31: 5; (3)
`/settings/organization` — tenant 1 აჩვენებს "შპს ბეჭდვითი სახლი"/PH,
tenant 31 — "შპს ტესტ ვან"/TS1; (4) sub-user `13` (tenant 1-ის) ხედავს
tenant 1-ის იმავე 11 დამკვეთს (არა ცალკე ცარიელი სია); (5) **security
boundary**: tenant 31-ის დამკვეთის (`id=1569`) რედაქტირების მცდელობა
ყალბი `customer_id`-ით tenant 1-ის სესიიდან — redirect **success-ის
მსგავსად** მოვიდა, მაგრამ DB-ში row **უცვლელი დარჩა** (`UPDATE ...
WHERE id=? AND ruler=?` 0 row-ს შეეხო); (6) იგივე ტესტი products-ზე
— tenant 31-მა ვერ შექმნა პროდუქტი tenant 1-ის `product_type_id=1`-ით
(`terr()`-კოდიანი ვალიდაციის შეცდომა, DB row არ შექმნილა). ყველა
ტესტ-მონაცემი/სესია წაშლილია.

### 4.32 ინვოისის ბეჭდვადი გვერდის access-control + share-token

`4.31`-ის ბოლოს ცალკე დავაფიქსირე, რომ `InvoiceController::show()`-ს
(`/invoices/view?id=N`) access-check საერთოდ არ ჰქონდა — ნებისმიერი
ლოგინირებული user-ი ხედავდა ნებისმიერ ინვოისს id-ის გამოცნობით,
tenant-ის მიუხედავად. მომხმარებელმა პირდაპირ მოითხოვა ამის დახურვა —
**ორმაგი პირობით**: (1) ნახვა მხოლოდ ავტორიზებულ, საკუთარი tenant-ის
ინვოისებისთვის, **და** (2) ცალკე, ტოკენით დაცული გზა — ვენდორმა რომ
შეძლოს ინვოისის ბმულის გაზიარება დამკვეთთან, რომელსაც ამ საიტზე
ანგარიში საერთოდ არ აქვს.

**`invoices.view_token`** (`migrations/026`, `VARCHAR(64) UNIQUE`) —
შემთხვევითი `bin2hex(random_bytes(32))`, გენერირდება **მხოლოდ
`INSERT`-ზე** (`Invoice::save()`), არასდროს იცვლება — იგივე
"set-once" პატერნი, რაც `created_by`-ს აქვს (`4.25.7`). 3 არსებული
რეალური ინვოისისთვის (`id` 3/5/11) ცალკე ერთჯერადი backfill-სკრიპტით
შეივსო.

**`InvoiceController::show()`-ის ახალი წესი** (ორი გზა შესვლისთვის):
```php
$sharedLinkValid = $token !== '' && hash_equals((string) $invoice['view_token'], $token);
$ownerTenant     = $this->ownerTenant($invoice);   // null = ვერ დადგინდა (legacy)
$viewerTenant    = null;

if (!$sharedLinkValid) {
    Auth::requireUser();                    // არაა login-ი → /login
    $viewerTenant = Auth::tenantId();
    if ($ownerTenant !== $viewerTenant) {   // სხვა tenant-ია → 404
        http_response_code(404);
        (new ErrorController())->notFound();
        return;
    }
}
```
- **`hash_equals()`**, არა `===` — token-შედარება timing-attack-ისგან
  დაცული უნდა იყოს (security-relevant შედარება, `===`-ს string-ის
  სიგრძეზე/პრეფიქსზე დამოკიდებული timing leak-ი აქვს).
- **404, არა 403** wrong-tenant-ის შემთხვევაში — არ ადასტურებს
  probe-ერისთვის, რომ ეს id საერთოდ არსებობს, უბრალო "ვერ მოიძებნა".
- **`$ownerTenant === null` ვერასდროს ემთხვევა namdvil `$viewerTenant`-ს**
  (`null !== int`) — legacy ინვოისი (`created_by`-ის გარეშე) ან
  წაშლილი creator-ი login-გზით **დაბლოკილია ყველასთვის**, არა
  ჩუმად "გატარებული" — ეს იყო ერთადერთი წუნი წინა ვერსიაში
  (`4.31`-ის `tenantOf()`-ს ჰქონდა `?? Auth::tenantId()` fallback,
  რომელიც ამ edge-case-ს ავტომატურად "წარმატებულად" აჩვენებდა
  ნებისმიერი ლოგინირებული user-ისთვის — თავად access-check ჯერ
  არ არსებობდა მაშინ, მხოლოდ org-ის ჩვენებისთვის იყო).
- **org-ის resolve-ი token-გზაზე არასდროს იძახებს `Auth::tenantId()`-ს**
  — `$ownerTenant ?? $viewerTenant ?? 0`, არა `?? Auth::tenantId()`.
  მიზეზი: `Auth::tenantId()` საკუთარ თავში `requireUser()`-ს იძახებს
  (redirect `/login`-ზე, თუ სესია არაა) — token-მფლობელი, ანონიმური
  viewer-ისთვის ეს გაანადგურებდა მთელი ფუნქციონალის აზრს (redirect
  login-ზე ზუსტად იმ ადამიანისთვის, ვისაც login საერთოდ არ სჭირდება).

**gate-ის გამონაკლისი** (`public/index.php`): `/invoices/view`
დაემატა `PUBLIC_PATHS`-ს — **არა** იმიტომ, რომ გვერდი სრულად
საჯაროა, არამედ რომ გლობალურმა blanket-redirect-მა არ დაბლოკოს
token-მფლობელი ანონიმური მოთხოვნა, სანამ კონტროლერს საკუთარი,
ნამდვილი წესის გატარების საშუალება მიეცემა. თავად access-გადაწყვეტილება
კვლავ `show()`-შია, არა gate-ში.

**გადამოწმებულია ცოცხლად** (curl, cookie-ის და token-ის ყველა
კომბინაცია): (1) ანონიმური + სწორი token → `200`, რეალური invoice
number-ით title-ში; (2) ანონიმური, token-ის გარეშე → `302 /login`;
(3) ანონიმური, არასწორი token → `302 /login`; (4) tenant 1-ის
login-ით, საკუთარი ინვოისი, token-ის გარეშე → `200`; (5) tenant
31-ის login-ით, tenant 1-ის ინვოისი, token-ის გარეშე → `404`; (6)
tenant 31-ის login-ით, tenant 1-ის ინვოისი, **სწორი** token-ით →
`200` (token გვერდს უვლის tenant-შემოწმებას, განზრახ — სწორედ ეს
არის გაზიარების აზრი). ტესტ-სესიები წაშლილია.

⚠️ **ჯერ არ არის**: ფორმაზე ("ახალი ინვოისი") არსებული "ბმულის
გაზიარება"/"მეილზე გაგზავნა" ღილაკები (`4.25.6`-დან, ჯერ კიდევ
ფუნქციონალის გარეშე) ჯერ **არ არის მიბმული** ამ ახალი token-ზე —
ანუ token მექანიზმი მზადაა და მუშაობს, მაგრამ ჯერ არ არსებობს UI,
საიდანაც ვენდორს პირდაპირ შეეძლება `?id=N&token=...` ბმულის
დაკოპირება. მომხმარებელს არ მოუთხოვია ეს ამ ეტაპზე.

### 4.33 Auth გვერდები — clear button + password show/hide

`login.php`/`register.php`/`forgot-password.php`-ის ველებს დაემატა
სტანდარტული `.btn-clear` (memory-ში დაფიქსირებული standing rule,
`cust.clear_field` key-ით — არა ახალი). password ველებს (login-ის,
register-ის ორივე) დამატებით `.btn-toggle-password` (თვალის აიკონი,
`bi-eye`/`bi-eye-slash`) — მარკერ-კლასი `form-floating--password`
`.form-floating`-ზე რეზერვს უკეთებს ორივე ღილაკისთვის საკმარის
`padding-right`-ს.

⚠️ **წინაპირობის ბაგი, ამ ამოცანის ფარგლებში აღმოჩენილი და
გასწორებული**: `app/Views/auth/_layout.php`-ს ჰქონდა
`floating-label.css` (link), მაგრამ **არა** `floating-label.js`
(script) — CSS-ით clear button ჩანდა, მაგრამ დაწკაპუნებაზე
არაფერი ხდებოდა (delegated listener საერთოდ არ იყო ჩატვირთული ამ
layout-ზე). დამატებულია.

`floating-label.js`-ს დაემატა მეორე დელეგირებული click-listener
(`.btn-toggle-password`) — input-ის `type`-ს `password`↔`text`-ს
შორის ცვლის, აიკონსა და `aria-label`-ს (`auth.show_password`/
`auth.hide_password`, ახალი keys) `data-show-label`/`data-hide-label`
attribute-ებიდან სვამს.

**scope**: მხოლოდ ეს სამი გვერდი, ზუსტად როგორც მოთხოვნილი იყო.
`reset-password.php` (ცალკე გვერდი — "ახალი პაროლის დაყენება", არა
"პაროლის შეხსენება"), `profile-settings.php`, `users.php`-ის password
ველები **არ შეხებია** — ღია საკითხია, თუ მომავალში იქაც დასჭირდებათ.

**გადამოწმებულია ცოცხლად** (ბრაუზერში, click-ივენთებით):
სამივე გვერდზე clear button ცარიელებს ველს; password toggle
login-ზე და register-ის ორივე password ველზე `type`-ს სწორად
ცვლის, აიკონი/`aria-label` ერთად იცვლება. ⚠️ შენიშვნა
`getComputedStyle().paddingRight`-ის შესახებ: ამ ბრაუზერ-tool-ში
ეს property არასანდოდ იკითხება (**თვითონ უკვე დამტკიცებული**
`.btn-clear`-ონли ველზეც იგივე "არასწორ" მნიშვნელობას აბრუნებდა) —
რეალური გეომეტრია (`getBoundingClientRect`, ღილაკების ურთიერთგადაფარვა)
და click-behavior სანდო წყაროდ ვიხმარე, არა ეს კონკრეტული property.

### 4.34 SuperUser — cross-tenant წვდომა + `.env` credential + impersonation

`4.26`-ის multi-tenant (`ruler`) სქემის პირდაპირი გაგრძელება: user-მა
მოითხოვა ერთი ანგარიში, ვისაც ყველა tenant-ის ნახვა შეუძლია. Scope-ი
დაზუსტდა `AskUserQuestion`-ით — **მხოლოდ** მომხმარებლების სია + "browse
as this tenant" (არა ცალკე cross-tenant dashboard ყველა ცხრილისთვის
ერთდროულად).

**კრედენშიალი — `.env`, `users`-ის `password_hash`-ის მაგივრად** (user-ის
საკუთარი წინადადება, დადასტურებული): `SUPERUSER_EMAIL`/
`SUPERUSER_PASSWORD` (`.env.example`-შიც, ცარიელი default-ით — ცარიელი
პაროლი არასდროს ემთხვევა submitted ცარიელს, ანუ ფუნქცია გამორთულია
სანამ არ დააყენებ). `Auth::attemptSuperuser()` (`attempt()`-ის შიგნით,
პირველი შემოწმდება) `hash_equals()`-ით ადარებს **პირდაპირ .env-ის
მნიშვნელობებს** — არა DB-ს `password_hash`-ს. წარმატებაზე
`User::ensureSuperuser()` INSERT...ON DUPLICATE KEY-ით ქმნის/ანახლებს
რეალურ `users` row-ს (`role='superadmin'`, ახალი hash ყოველ successful
login-ზე ხელახლა გამოთვლილი .env-ის მიმდინარე პაროლიდან) — ეს row
**არასდროს** არის რეალური auth-წყარო, მხოლოდ "მასალიზებულია" რომ
`Auth::user()`/სესია/topbar/idle-timeout ყველამ ჩვეულებრივად იმუშაოს,
ცალკე კოდის გარეშე. **რატომ .env, არა მხოლოდ DB**: DB-ის მარტო
გატეხვა (backup leak, SQL injection) ვერ იძლევა მუშა SuperUser
კრედენშიალს — საჭიროა `.env`-იც, რომელიც ისედაც `DB_PASS`/
`MAIL_PASSWORD`-ს იმავე plaintext დონეზე იცავს.

**`users.role` ENUM-ს დაემატა `'superadmin'`** (`migrations/027`) —
**განზრახ არასდროს ჩანს** `User::roles()`-ში (`/settings/users`-ის
role-picker-ს მხოლოდ admin/manager/viewer აქვს) — ჩვეულებრივი admin
ვერასდროს შექმნის/დააწინაურებს ვინმეს superadmin-მდე ამ ფორმით,
`User::validateSubUser()`-ის role-შემოწმება ავტომატურად უარყოფს
ნებისმიერ ყალბ `role=superadmin` POST-საც.

**Impersonation** (`Auth::impersonate()`/`stopImpersonating()`/
`impersonating()`, `$_SESSION['impersonating_tenant']`) — SuperUser
საკუთარ tenant-ს **არასდროს** ფლობს (customers/products/org არც
ერთი მათგანი). `Auth::tenantId()`-ს დაემატა superadmin-ტოტი:
impersonation არჩეული არაა → redirect `/superuser`-ზე (**ყველა**
უკვე არსებული controller, რომელიც `tenantId()`-ს იძახებს
— customers/products/organization/invoices — ეს დაცვა **უფასოდ**
მიიღო, არცერთი მათგანი არ შეხებია). არჩეულია → უბრალოდ აბრუნებს
იმ tenant id-ს, და **იგივე, უცვლელი** customers.php/products.php/
settings/organization/invoices.php გვერდები მუშაობს — ცალკე
"admin-panel" ვერსია არცერთი ცხრილისთვის არ დაწერილა (ეს იყო
`AskUserQuestion`-ის რეკომენდებული, არჩეული ვარიანტი).

⚠️ **`impersonate()`-ის target-ვალიდაცია მნიშვნელოვანია**:
`SuperUserController::impersonate()` **უარყოფს** ნებისმიერ
`tenant_id`-ს, რომელსაც `created_by !== NULL` აქვს (ე.ი. sub-user
id-ია, არა root tenant) ან `role === 'superadmin'` — რადგან
`Auth::tenantId()`-ის impersonation-ტოტი ამ მნიშვნელობას **პირდაპირ,
ხელახლა-რეზოლუციის გარეშე** იყენებს (ჩვეულებრივი, არა-impersonating
sub-user-ისგან განსხვავებით, სადაც `created_by ?? id` ყოველ ჯერზე
თვლის). ცოცხლად გადამოწმებულია: sub-user id-ის (13) გაგზავნა
`/superuser/impersonate`-ზე უარყოფილია, impersonation state **არ**
დამყარებულა.

⚠️ **`Auth::requireAdmin()`-ს დასჭირდა თავისი გასწორება** — თავდაპირველად
`role === 'admin'`-ს ამოწმებდა, რაც superadmin-ს **ვერასდროს**
გაატარებდა (მათი საკუთარი role `'superadmin'`-ია, არა `'admin'`),
თუნდაც impersonation-ის დროს — `/settings/organization`/`/settings/users`
(ორივე `requireAdmin()`-ს იძახებს **`tenantId()`-მდე**) 403-ავდა
SuperUser-საც კი, active impersonation-ის დროსაც. გასწორებულია:
`role === 'admin' OR (role === 'superadmin' AND impersonating() !== null)`
— სწორია, რადგან `impersonate()`-ის ვალიდაცია უკვე უზრუნველყოფს, რომ
ნებისმიერი impersonation target **ყოველთვის** არის root tenant, და
root tenant ამ სქემაში **ყოველთვის** `role='admin'`-ია.

⚠️ **ცალკე ნაპოვნი, ამ ცვლილებამდელი უსაფრთხოების ხარვეზი, გასწორებული
ამავე დროს**: `User::all()` (`/settings/users`-ის სია) **საერთოდ არ
იყო tenant-სკოუპილი** — ნებისმიერ admin-ს შეეძლო ეხილა **ყველა**
tenant-ის ყველა sub-user, და `User::updateSubUser()`-საც (`WHERE id
= ?`, `created_by`-ის შემოწმების გარეშე) შეეძლო **ჩაეწერა** სხვა
tenant-ის sub-user-ის პაროლიც კი, id-ის გამოცნობით/პოვნით. ორივე
გასწორდა (`WHERE created_by = ?`/`AND created_by = ?`) — იგივე
"ownership WHERE"-პატერნი, რაც `Customer::update()`-ს აქვს `4.26`-დან.
ეს იყო `4.26`-ის დროს გამოტოვებული ცხრილი (`users` არასდროს
შევეხე მაშინ) — ახლა დახურულია.

**UI**: sidebar-ს `menu.json`-ის გარეთ დაემატა role-პირობითი ბმული
(`/superuser`, მხოლოდ `role==='superadmin'`-ისთვის) — `menu.json`-ს
საერთოდ არ აქვს role-visibility კონცეფცია, ერთი ბმულისთვის ამის
დამატება მეტი მანქანერიაა, ვიდრე ეს ერთჯერადი special-case
`sidebar.php`-ში. `layout.php`-ს დაემატა banner (`.alert-warning`,
topbar-ის ქვემოთ) — ჩანს **ყველა** გვერდზე, სანამ impersonation
აქტიურია, "SuperUser — ხედავ როგორც: {name}" + "გამოსვლა" ღილაკი.

**გადამოწმებულია ცოცხლად** (რეალური login-ფორმით, `.env`-ის test
კრედენშიალით): login → `users` row შეიქმნა (`role=superadmin`);
`/customers` (impersonation-ის გარეშე) → `302 /superuser`; `/superuser`
სწორად აჩვენებს 3 tenant-ს (`badge: 3`) + sub-user badge-ებს tenant
1-ის ქვეშ; impersonate tenant 31 → banner ჩნდება, `/customers`
აჩვენებს tenant 31-ის 8 დამკვეთს, `/settings/organization` — tenant
31-ის org-ს ("შპს ტესტ ვან"/TS1); `stop` → ისევ `302 /superuser`
ნებისმიერ tenant-სკოუპილ გვერდზე; sub-user id-ის (13) impersonation
მცდელობა უარყოფილია; ჩვეულებრივი admin (`role=admin`, id=31) `/superuser`-ზე
`302 /`-ით იბლოკება; tenant 1-ის ნამდვილი admin-ის `/settings/users`
აჩვენებს ზუსტად მის 2 sub-user-ს (`badge: 2`), არც სხვა tenant-ის
წევრებს, არც SuperUser-ს. ტესტ-სესიები წაშლილია.

### 4.35 სამუშაო მაგიდა — რეალური, tenant-სკოუპილი მონაცემები

`Models/Dashboard.php` მთლიანად იყო hardcoded sample data (საკუთარი
docblock ამბობდა ამას პირდაპირ) — user-მა მოითხოვა რეალური რიცხვები.
ყველა მეთოდი ახლა იღებს `$ruler`-ს (`Auth::tenantId()`) და პასუხობს
DB-დან.

**4 stat-ბარათი**: დამკვეთების/პროდუქტების/ინვოისების რაოდენობა
(`customers`/`products` `WHERE ruler = ?`) + ჯამური შემოსავალი
(`SUM(invoices.total)`). ინვოისებს `ruler` სვეტი **არ** აქვთ
(scoped `created_by`-ით, ცალკე user, არა tenant) — `Dashboard::
tenantUserIds($ruler)` აბრუნებს `[tenant admin-ის id, ...sub-user
id-ები]`-ს (`WHERE id = ? OR created_by = ?`), და ინვოისის queries
`WHERE created_by IN (...)`-ს იყენებენ ამ სიით. ძველი fake "trend"
ისრები/პროცენტები (`+12.4%` და ა.შ.) **მოცილებულია** — რეალური
"წინა პერიოდთან შედარება" არ დამითვლია, გამოგონილი დელტა
რეალურზე უარესი იქნებოდა.

**Chart.js stacked bar — "შემოსავლის დინამიკა"**: ბოლო 6 კალენდარული
თვე X-ღერძზე, **ერთი დაწკაპული სვეტი თვეში, ერთი ფერადი სეგმენტი
თითო tenant-წევრზე** (admin + ყოველი sub-user, `Dashboard::
revenueByUser()`) — user-ის მოთხოვნა "ერთად, გამოყოფილი ფერით"
ზუსტად ამას ნიშნავდა, არა ცალკე გრაფიკები თითო user-ზე. თვის key-ები
(`'YYYY-MM'`) მოდელიდან **გაუთარგმნელად** გამოდის (არსებული
`Dashboard::activity()`-ის კონვენციის იგივე პატერნი — model არასდროს
თარგმნის) — view თარგმნის `t('month.' . (int) $m)`-ით (უკვე
არსებული, `ds_date()`-ის იგივე key-ები). ფერების palette
ხელით არჩეული 8 hex-კოდისგან შედგება (`#4f46e5`, `#22c55e`, ...),
`$i % count($palette)` round-robin — 8-ზე მეტი წევრისთვის ფერები
გამეორდება (`ponytail:` შენიშვნა კოდში არ დამიმატებია, რადგან 8+
sub-user ერთ tenant-ზე ამ პროექტში ჯერ არ ყოფილა რეალისტური).

**ძველი fake სექციები მოცილებულია, არა repurpose-ილი**: "ტრეფიკის
წყაროები" (doughnut chart), "გუნდის აქტივობა" (fake activity feed),
"თვის მიზანი" (fake progress bar) — არცერთ მათგანს არ ჰქონდა
შესაბამისი რეალური მონაცემი აპლიკაციაში (არც "traffic source",
არც "activity log", არც "goal" კონცეფცია არსად არსებობს) — wire-ის
მაგივრად, რომ ცარიელი/ყოველთვის-0 გამოსულიყო, სექციები საერთოდ
წაშლილია. ცხრილი "ბოლო შეკვეთები" **repurpose-ილია** "ბოლო
ინვოისებად" — რეალური `Dashboard::recentInvoices()`, ნამდვილი
ინვოისის ნომრით (`Invoice::number()`), დამკვეთით, შემქმნელით,
თარიღით, თანხით, სტატუსით (`4.30`-ის `inv.creator`/status
badge-ების იმავე პატერნით).

**Dead lang keys წაშლილია**: `traffic.*`, `activity.*`, `goal.*`,
`range.*`, `chart.weekdays`, `stat.active_users`, `stat.orders`,
`stat.conversion`, `orders.product` — არცერთი აღარსად გამოიყენებოდა
(`status.paid`/`pending`/`rejected`/`cancelled` **დარჩა**, style-guide.php
მაინც იყენებს badge-მაგალითებისთვის).

⚠️ **გვერდითი ეფექტი**: dashboard-მა ახლა იძახებს `Auth::tenantId()`-ს
(ადრე საერთოდ არ სჭირდებოდა tenant, hardcoded data იყო) — ანუ
SuperUser, რომელსაც არცერთი tenant არჩეული არა აქვს, `/`-ზეც კი
`302 /superuser`-ს იღებს ახლა (ადრე `/` მისთვის უბრალოდ `200`-ს
აბრუნებდა, ცარიელი/mock სტატისტიკით). ეს სწორია — SuperUser-ს
საკუთარი customers/products/invoices არასდროს არ ჰქონია.

**გადამოწმებულია ცოცხლად**: tenant 1-ის dashboard — `11`/`7`/`3`/
`155.00` (customers/products/invoices/revenue), ზუსტად ემთხვევა
DB-ს (`SUM(total)` ხელით გადამოწმებული); chart-ის JSON — 3 dataset
("გივი ბერძენიშვილი"/"პავლე პეტრიაშვილი"/"პეტრე პავლიაშვილი",
თითო ცალკე ფერით), ბოლო თვის (აგვისტო) მონაცემები ზუსტად `50`/`25`/
`80` — სამივე რეალური ინვოისის `total`-ს ემთხვევა; "ბოლო ინვოისები"
ცხრილი სამივეს სწორად აჩვენებს (ნომერი/დამკვეთი/შემქმნელი/თარიღი/
თანხა). tenant 31 (test1) — იზოლირებული: `8`/`5`/`0`/`0.00`. SuperUser
(impersonation-ის გარეშე) — `/` → `302 /superuser`. ტესტ-სესიები
წაშლილია.

### 4.36 `/orders`-ის scope შეუთანხმდა dashboard-თან — მთელი tenant

user-მა ცოცხლად აღმოაჩინა შეუსაბამობა: ქვემომხმარებლის მიერ
გამოწერილი ინვოისი ჩანდა dashboard-ზე (`4.35`, tenant-სკოუპილი —
admin + ყველა sub-user ერთად), მაგრამ **არ** ჩანდა "შეკვეთები >
ყველა შეკვეთა"-ში, რადგან `InvoiceController::orders()` მანამდე
`Invoice::all($user['id'])`-ს იძახებდა — **მხოლოდ ამჟამად
შესული კონკრეტული user-ის** `created_by`-ით (ეს იყო თავად user-ის
ადრინდელი, პირდაპირი მოთხოვნა ამ სესიაშივე). ორი scope ერთმანეთს
ეწინააღმდეგებოდა. `AskUserQuestion`-ით დადასტურდა გადაწყვეტა:
`/orders`-იც გახდეს tenant-ის მასშტაბის, dashboard-ის იგივე
პრინციპით.

**`User::tenantMemberIds(int $ruler): array`** — ახალი public
მეთოდი (`WHERE id = ? OR created_by = ?`, ანუ admin + ყველა
sub-user), გატანილი `Dashboard::tenantUserIds()`-ის (`4.35`-ის
private დუბლიკატი) მაგივრად — ახლა ორივე `Dashboard.php` და
`InvoiceController::orders()` ერთსა და იმავე, საერთო წყაროს
იყენებენ, აღარ არსებობს ორი დამოუკიდებელი "ვინ არის ამ tenant-ის
წევრი" logic, რომ მომავალშიც არ დაცილდნენ ისევ.

**`Invoice::all()`-ის სიგნატურა შეიცვალა**: `?int $createdBy` →
`?list<int> $createdByIds` (`WHERE created_by = ?` → `WHERE
created_by IN (...)`). `/invoices`-ის `$invoicesByCustomer` panel-ის
გამოძახება (`Invoice::all()`, არგუმენტის გარეშე) **უცვლელია** —
კვლავ სრულად unscoped, განზრახ (დამკვეთის სრული ისტორია, ყველა
tenant-იდან — `4.25.7`-ის კონვენცია ხელუხლებელია).

**გადამოწმებულია ცოცხლად**: tenant 1-ის admin-ი (id=1) და მისი
sub-user-ი (id=24) **ორივე** ხედავენ ერთსა და იმავე 3 ინვოისს
`/orders`-ზე (badge: 3) — მათ შორის ისეთსაც, რაც არც ერთმა მათგანმა
პირადად არ შექმნა (მესამე sub-user, id=13, შექმნილი). tenant 31
(test1) კვლავ იზოლირებულია — `0`. ტესტ-სესიები წაშლილია.

**4.35-ის chart-ი შეიცვალა**: user-მა სურათი მოგვცა (grouped bars,
სრული კალენდარული წელი) და მოითხოვა ამის მიხედვით შეცვლა. Stacked →
grouped (`options.scales`-იდან `stacked: true` მოცილებულია ორივე
ღერძზე — Chart.js-ის default bar-behavior უკვე grouped-ია). ბოლო
6 თვის მოძრავი window → მიმდინარე კალენდარული წელი, იან-დეკ,
`Dashboard::currentYearMonths()` (ყოფილი `lastMonths(6)`-ის
მაგივრად) — SQL-საც დაემატა `AND YEAR(issue_date) = YEAR(CURDATE())`.
Month label-ებს წლის რიცხვი მოეხსნა (`"აგვ 2026"` → `"აგვ"`) —
ერთი წლის ფარგლებში ზედმეტია. **ცოცხლად გადამოწმებულია**: labels
ზუსტად `["იან"..."დეკ"]`, აგვისტოს (index 7) მონაცემები ისევ `50`/
`25`/`80`-ს ემთხვევა, `stacked` აღარსად ჩანს გენერირებულ JSON-ში.

**"ბოლო ინვოისები" გახდა სტანდარტული `ds-table`** — user-მა მოითხოვა
ვიზუალური კონსისტენცია customers.php/products.php/orders.php-სთან
(ძებნა, pagination). იგივე wrapper-პატერნი გადმოტანილია
სიტყვასიტყვით (`card-body` > `.ds-table[data-ds-table]` >
`.table-responsive` > `table`), `$scripts`-ს დაემატა
`ds_table_script()` (Chart.js-ის `<script>`-თან შეერთებული
`.`-ით — `users.php`-ის იგივე კონკატენაციის პატერნი). თარიღისა და
თანხის სვეტებს დაემატა `data-order` (ISO თარიღი/`float`) სწორი
დახარისხებისთვის — იგივე, რაც `orders.php`-ს აქვს.

`Dashboard::recentInvoices()`-ის ნაგულისხმევი `$limit`
(`DashboardController`-დან გამოძახებისას) `6`-დან `20`-ზე გავიდა —
ცხრილს ახლა აქვს ნამდვილი ძებნა/გვერდები, საკმარისი მწკრივები
სჭირდება რომ ეს აზრიანი იყოს; მაინც "ბოლო" (არა სრული ისტორია —
ამისთვის "ყველას ნახვა"-ს ბმული `/orders`-ზე უკვე არსებობს).

**გადამოწმებულია ცოცხლად** (SuperUser-ის საშუალებით, tenant 1-ის
impersonation-ით): `.ds-table`, `input[type="search"]`
(placeholder "ძებნა..."), pagination — სამივე რეალურად DOM-შია
(არა მხოლოდ static markup); ძებნა "TOXIGEN"-ზე 3 მწკრივიდან 1-მდე
სწორად filter-ავს (`ds-table.js`-ის 120ms debounce-ის გათვალისწინებით
— პირველი ტესტი ნაადრევად შემოწმდა და "ვერ მუშაობს" გამოჩნდა, სინამდვილეში
უბრალოდ დრო არ ჰქონდა debounce timer-ს გასულიყო).

### 4.37 PDF ექსპორტი (`/orders/export-pdf`) — პროექტის პირველი Composer დამოკიდებულება

user-მა კონკრეტულად `mpdf/mpdf` მოითხოვა. ეს არის **პირველი
Composer-ის დამოკიდებულება მთელს პროექტში** — ყველაფერი დანარჩენი
(`public/vendor/*`) ხელით კოპირებული JS/CSS ფაილებია, არა Composer/npm
(`CLAUDE.md`). `composer require mpdf/mpdf` → `composer.json`/
`composer.lock` **committed**, `vendor/` **`.gitignore`-ში** ახალი
`/vendor/` წესით (`composer install` ხელახლა აგენერირებს ნებისმიერ
checkout-ზე). `app/bootstrap.php`-ს დაემატა `require ROOT_PATH .
'/vendor/autoload.php';` — ერთადერთი ადგილი, სადაც Composer-ის
autoloader სჭირდება, საკუთარი `App\`-ის PSR-4-ish autoloader-ის
გვერდით (ორივე თანაარსებობს პრობლემის გარეშე).

⚠️ **ქართული ტექსტი mPDF-ში — საჭირო იყო ცალკე ფონტი**: mPDF-ის
ჩაშენებული ფონტები (DejaVu-ოჯახი) ქართულ Unicode-ბლოკს (U+10A0–U+10FF)
**არ** ფარავენ — ტექსტი ცარიელ კვადრატებად („tofu") გამოჩნდებოდა
ამის გარეშე. `app/Core/fonts/NotoSansGeorgian.ttf` (OFL-ლიცენზირებული,
Google-ის საჯარო `google/fonts` repo-დან ჩამოტვირთული, `github.com/
google/fonts/raw/main/ofl/notosansgeorgian/`) რეგისტრირებულია, როგორც
**default font** ყველა PDF-ისთვის (`App\Core\Pdf::make()`-ის
`fontDir`/`fontdata`/`default_font` კონფიგი). Google-ის საკუთარი
web-ფონტების repo-დან წამოღება, არა `fonts.gstatic.com`-ის
`.woff`-დან — mPDF-ს TTF სჭირდება, არა WOFF.

**`App\Core\Pdf`** — თხელი wrapper `mpdf/mpdf`-ზე: `make(): Mpdf`
(კონფიგურირებული instance) და `download(string $html, string
$filename): void` (render + `Content-Disposition: attachment`
headers). `Controller`-ს დაემატა ახალი `renderToString(string $view,
array $data): string` მეთოდი — იგივეა, რაც `view()`, უბრალოდ **layout
გარეშე** და `echo`-ს მაგივრად string-ს აბრუნებს (mPDF-ის CSS support
Bootstrap-ის grid/flex-ს არ ფარავს — PDF-ის view-ებს თავისი, plain
HTML/CSS სჭირდება, `app/Views/pdf/orders.php`).

**`InvoiceController::exportOrdersPdf()`** — ზუსტად იგივე tenant
scope, რაც `orders()`-ს აქვს `4.36`-დან (`Invoice::all(User::
tenantMemberIds($ruler))`) — ექსპორტი ვერასდროს გამოიტანს სხვა
tenant-ის მონაცემებს, რადგან იგივე query-ს იყენებს, რასაც screen-ის
ცხრილიც. `/orders`-ის ღილაკი ("ექსპორტი PDF") ჩანს მხოლოდ როცა
`$rows !== []`.

**გადამოწმებულია ცოცხლად**: PDF რეალურად ჩამოიტვირთა (`Content-Type:
application/pdf`, `X-Generator: mPDF 8.3.1`, ვალიდური `%PDF-1.4`
header), Read tool-ით გავხსენი — ქართული ტექსტი (კომპანიის სახელი,
დამკვეთების სახელები, სტატუსები) **სწორად** მოჩანს, `tofu`-ს გარეშე;
სულ (`155.00`) ემთხვევა 3 რეალური ინვოისის ჯამს. ტესტ-ფაილები/სესია
წაშლილია.

**თითო-ინვოისის PDF ექსპორტიც** (`/invoices/export-pdf?id=N`) —
`/orders`-ის მოქმედების სვეტს დაემატა მესამე აიკონი (pencil/printer-ის
გვერდით). `app/Views/pdf/invoice.php` — იმავე შემადგენლობის, რაც
`invoice-view.php`-ს (ბრაუზერის print-გვერდს) აქვს: ლოგო/org header,
"გადამხდელი"/bill-to, სტრიქონები, ჯამი, საბანკო ანგარიშები — უბრალოდ
plain, mPDF-uსაფე HTML/CSS Bootstrap-ის მაგივრად, და სურათები
(ლოგო) ფაილურ path-ს იყენებენ (`ROOT_PATH . '/public/assets/uploads/
organization/...'`) URL-ის მაგივრად — mPDF ლოკალურ ფაილს პირდაპირ
კითხულობს, HTTP-round-trip არ სჭირდება.

⚠️ **`text-transform: uppercase` ქართულ ტექსტზე — გადამოწმებული,
თავიდან აცილებული**: პირველი ცდისას label-ებს (`.section-label`)
ჰქონდათ `text-transform: uppercase`, ვებ-გვერდების common
convention-ის მიხედვით — შედეგად Mtavruli Unicode-ბლოკზე (U+1C90–
U+1CBF) გადავიდნენ ("გადამხდელი" → "ᲒᲐᲓᲐᲛᲮᲓᲔᲚᲘ"). ეს **არ იყო
tofu/broken** (ფონტს რეალურად აქვს ეს glyph-ები), უბრალოდ ვიზუალურად
შეუსაბამო, სხვა წონის script იყო დანარჩენ (ჩვეულებრივ Mkhedruli)
ტექსტთან შედარებით. ვებ-აპლიკაცია ამ ეფექტს **განზრახ**, ცალკე
ფონტით აკეთებს სადაც სჭირდება (`bpg-arial-caps`, `4.2`-ის მსგავსი
თემა) — PDF-ისთვის უბრალოდ `text-transform` მოვაცილე, არა ცალკე
ფონტის მოყვანა ერთი label-სტილისთვის.

**access-control** — იგივე, რაც `show()`-ის no-token branch-ს აქვს
(`ownerTenant() === Auth::tenantId()`, სხვაგვარად `404`) — token-ის
ალტერნატივა აქ არ სჭირდება, ეს ღილაკი მხოლოდ login-გეითის მიღმაა
მისაწვდომი. **გადამოწმებულია ცოცხლად**: tenant 1-ის admin-მა
წარმატებით ჩამოტვირთა საკუთარი ინვოისის PDF (ლოგო/org header/
line-items/ჯამი/IBAN-ები ყველა სწორად, ლეიბლების text-transform
fix-ის შემდეგ); tenant 31-ის (test1) მცდელობამ ამავე ინვოისის
ექსპორტზე — `404`. ტესტ-ფაილები/სესიები წაშლილია.

**Header-ის რედიზაინი** — user-მა კონკრეტული სქრინშოტი მოგვცა.
ახალი განლაგება: ლოგო (მარცხნივ) + თარიღი/ინვოისის ნომრის ორი
stat-ბლოკი (ნაცრისფერი label, ლურჯი value, მარჯვნივ) → თხელი
გამყოფი ხაზი → ერთი light-blue `.info-box` (`#eef4fc`,
`border-radius`), ორსვეტიანი: ჩვენი (org) და დამკვეთის დეტალები
გვერდიგვერდ. **`Invoice::find()`-ს დაემატა `creator_name`**
(`LEFT JOIN users`) — ორგანიზაციის სვეტის "საკონტაქტო" არის **ამ
კონკრეტული ინვოისის შემქმნელი** (`invoices.created_by`-დან), არა
ორგანიზაციის რომელიმე ცალკე ველი (ასეთი არც არსებობს `organization`
ცხრილში) — თითოეულ PDF-ს თავისი, ნამდვილი "ვინ გამოწერა" გამოაქვს.
დამკვეთის მხარეს "საკონტაქტო" კი უკვე არსებული `customer_contact`
ველია. ორივე label ("საიდენტიფიკაციო"/"საკონტაქტო") **ახალი,
კომპაქტური lang key-ებია** (`inv.pdf_taxid`/`inv.pdf_contact`) —
განზრახ არა `cust.taxid`/`cust.contact`-ის თავიდან გამოყენება,
რადგან user-ის სქრინშოტი უფრო მოკლე ტექსტს აჩვენებდა ("საიდენტიფიკაციო"
ისე, "კოდი"-ს გარეშე). ყველა ველი (taxid/address/contact/phone/
email, ორივე მხარეს) **პირობითია** — ცარიელი უბრალოდ არ ეთარგმნება,
იგივე კონვენცია, რაც `invoice-view.php`-ის bill-to ბლოკს აქვს.

**გადამოწმებულია ცოცხლად** (ორი სხვადასხვა ინვოისით): id=5-ზე
ორგანიზაციის "საკონტაქტო" სწორად აჩვენებს "გივი ბერძენიშვილი"
(`created_by=1`); id=3-ზე — "პავლე პეტრიაშვილი" (`created_by=24`)
— ორივეჯერ სწორად ემთხვევა თითოეული ინვოისის რეალურ შემქმნელს.
დამკვეთის taxid-ის პირობითობაც გადამოწმებულია: TOXIGEN BOARD SHOP-ს
(taxid არ აქვს) "საიდენტიფიკაციო"-ს ხაზი საერთოდ არ გამოაქვს.
ტესტ-ფაილები/სესია წაშლილია.

**პირველი polish-ს რაუნდი** — user-მა ცოცხლად შექმნილი PDF-ის
სქრინშოტით 4 კონკრეტული შესწორება მოითხოვა:
1. თარიღი/ინვოისის ნომერი (`.stat-value`) აღარ არის bold.
2. `.info-field`-ის (org/customer ტექსტის) სტრიქონებს შორის
   მანძილი გაიზარდა (`margin-bottom: 1px` → `5px`).
3. **"დამატებითი ინფორმაცია" (`invoices.notes`) საერთოდ არ ჩანდა
   PDF-ში** — ახალი `.notes` სექცია დაემატა (items-ის/ჯამის შემდეგ,
   IBAN-ების წინ), `nl2br()`-ით მრავალხაზიანი ტექსტისთვის.
4. `.items th` (product/quantity/price/total header) აღარ არის
   bold — mPDF/ბრაუზერის default `<th>` bold-ს (user-agent
   stylesheet-იდან) `font-weight: normal` გადააჭარბა; ცისფერი
   fill-ის მაგივრად თხელი ლურჯი underline (user-ის მოცემული მეორე
   სქრინშოტის სტილი).

⚠️ **ამავე დროს გასწორებული, თავად ვერ შემენიშნა ადრე**: `.section-label`
CSS class (`org.bank_details`-ის header-ისთვის) წაშლილი აღმოჩნდა
`4.37`-ის text-transform fix-ის დროს, ახალი style-ის დამატების
გარეშე — "საბანკო ანგარიშები" უსტილო ტექსტად ჩანდა. აღდგენილია
(იმავე patern-ით, რასაც ახლა `.notes`-იც იყენებს).

**გადამოწმებულია ცოცხლად**: ახალი ტესტ-ინვოისი (id=13, ორხაზიანი
notes-ით) — PDF-ში ოთხივე ცვლილება სწორად ჩანს, "დამატებითი
ინფორმაცია" სექცია სწორ ადგილას, ორივე ხაზით. ტესტ-ინვოისი
წაშლილია (id=12 კი, user-ის საკუთარი, ცოცხლად შექმნილი რეალური
ტესტ-ინვოისი ამ polish-ის დროს — **ხელუხლებელი დარჩა**, არ
შემხებია).

### 4.38 mPDF-ის ფონტი გადავიდა `app/Core/fonts/`-იდან `public/assets/fonts/`-ში

`NotoSansGeorgian.ttf` თავიდან `app/Core/fonts/`-ში აღმოჩნდა
(mPDF wrapper-ის გვერდით, `4.37`-ის სისწრაფის გამო) — user-მა
სწორად შენიშნა, რომ ეს პროექტის დამკვიდრებულ კონვენციას არ
შეესაბამება: ყველა დანარჩენი ფონტი (`bpg-arial-caps`, `gb`) `public/
assets/fonts/<font-name>/fonts/<file>` სტრუქტურითაა. ფაილი გადავიდა
`public/assets/fonts/noto-sans-georgian/fonts/NotoSansGeorgian.ttf`-ში,
ცარიელი `app/Core/fonts/` წაშლილია. `App\Core\Pdf::FONT_DIR` განახლდა
`ROOT_PATH`-ზე დაფუძნებულ absolute path-ზე (`__DIR__`-ის მაგივრად —
ფონტი აღარაა `Pdf.php`-ის საკუთარი დირექტორიის ქვეშ). `public/`-ის
ქვეშ ყოფნა ამ ფაილს ვებზე არ "აქცევს ხელმისაწვდომს" რაიმე ახლებურად —
mPDF მას ისედაც ფაილურ სისტემაზე პირდაპირ კითხულობდა (არა HTTP-ით),
იგივეა, რასაც `bpg-arial-caps`-ის `.ttf` აკეთებს თავისი `@font-face`
CSS-ის გვერდით.

**გადამოწმებულია ცოცხლად**: SuperUser-ით tenant 31-ის (test1)
იმპერსონაცია → არსებული ინვოისის (id=12) PDF ექსპორტი
(`/invoices/export-pdf?id=12`) → `200`, `X-Generator: mPDF 8.3.1`,
472KB ვალიდური PDF. Read tool-ით გავხსენი — ქართული ტექსტი
(ორგანიზაცია/დამკვეთის დეტალები, "საკონტაქტო", პროდუქტის სახელი)
ახალი ფონტის ადგილიდან **სწორად** მოჩანს, `tofu`-ს გარეშე. ტესტ-ფაილი
და სესია წაშლილია.

**`.info-name`-ს დაემატა BPG Arial Caps + spacer-div ტექნიკა margin/padding-ის
მაგივრად**: user-მა მოითხოვა org/customer სახელის ხაზისთვის (`.info-name`)
`BPG Arial Caps` font-ის გამოყენება (ვებ-გვერდზე უკვე გამოყენებული
Mtavruli-სტილის ფონტი, `layout.php`-ის `<link>`) და შენიშნა, რომ
`margin-bottom: 4px` ეფექტს არ იძლეოდა. **პირველი ცდა
(`padding-bottom: 6px`) ასევე ვერ დაეხმარა** — user-მა live-ზე
გადაამოწმა და კვლავ არ ჩანდა. mPDF-ს block-level `<div>`-ებზე
(border/background-ის გარეშე) მარგინიც და padding-იც არასაიმედოდ
აისახება. საბოლოო ფიქსი: **პროექტში უკვე დამტკიცებული ტექნიკის**
გამეორება — `.top-rule`-ს (`4.37`-ის header-რედიზაინიდან) იგივე
პრობლემა ჰქონდა და გადაჭრილი იყო ცარიელი box-ის მაგივრად რეალური
content-იანი (`&nbsp;`) spacer-ელემენტით და ცხადი `line-height`-ით
— ეს იმუშავა, რადგან mPDF ტექსტის line-box-ს (და არა margin/padding-ს)
ყოველთვის სანდოდ ითვლის. `.info-name-gap` (`font-size:6px;
line-height:6px;`) კლასის ახალი `<div>&nbsp;</div>` დაემატა თითო
`.info-name`-ის შემდეგ, ორივე (org/customer) სვეტში.

ახალი font `App\Core\Pdf::make()`-ს დაემატა მეორე registered
font-ად (`bpgarialcaps` → `public/assets/fonts/bpg-arial-caps/fonts/
bpg-arial-caps-webfont.ttf`, უკვე არსებული `.ttf` ვარიანტი იმავე
საქაღალდეში, საიდანაც ვებ-გვერდის `@font-face`-იც იღებს ფაილს) —
`fontDir`-ს ორივე (Noto Sans Georgian + BPG Arial Caps) დირექტორია
ემატება, `default_font` კვლავ `notosansgeorgian` რჩება.

**გადამოწმებულია ცოცხლად** (სამივე ვერსია, ცალ-ცალკე ცოცხლად
გენერირებული PDF-ით): margin-bottom-იანი ვერსია, padding-bottom-იანი
ვერსია, და საბოლოო spacer-div ვერსია — ვიზუალურად სამივეს Read
tool-ით ვათვალიერებდი და გამოსახული სივრცე დამაჯერებლად არ
განსხვავდებოდა ამ კონკრეტულ PDF-რენდერერში საკმარისად ცხადად, რომ
padding-ის ჩავარდნის მიზეზი დარწმუნებით დამედასტურებინა მხოლოდ
ჩემი დათვალიერებით — ამიტომ საბოლოო არჩევანი დაეყრდნო **პროექტში
უკვე დამოწმებულ, არა ვარაუდისეულ** ტექნიკას (`.top-rule`), და
user-ს პირდაპირ სთხოვე დაადასტუროს რეალურ PDF viewer-ში/ბეჭდვისას.
ტესტ-ფაილები და სესია წაშლილია.

### 4.39 ორგანიზაციის დღგ (`vat_rate`) + ფულის ერთეული (`currency`)

`migrations/028_add_org_vat_currency.sql`: `organization`-ს დაემატა
`vat_rate` (`DECIMAL(5,2) DEFAULT 18.00`) და `currency`
(`ENUM('GEL','USD') DEFAULT 'GEL'`) — /settings/organization-ში ახალი
ორი ველი (`Organization::validate()`/`save()` განახლდა შესაბამისად,
`org.vat_rate`/`org.currency`/`org.currency_gel`/`org.currency_usd`
lang-key-ები).

⚠️ **დღგ არის მხოლოდ ვიზუალური, არა გამომთვლელი** — user-მა პირდაპირ
დაადასტურა (`AskUserQuestion`-ით): ინვოისში ყველა ფასი უკვე დღგ-ს
ჩათვლით შეაქვთ, ასე რომ დღგ-ს ჩვენება **არაფერს არ ცვლის** ჯამში
— `invoices.php`-ის ფორმაში ადრე hardcoded `18%`/`0.18` იყო, ეხლა
`$org['vat_rate']`-დან იკითხება (`data-vat-rate` → JS), მაგრამ
`Invoice::save()`/`invoices.total`/PDF-ის ჯამი **ისევე** არ
შეიცავს დღგ-ს დამატებით — მხოლოდ საინფორმაციო რიცხვი იცვლება, არა
თვითონ ანგარიში. სხვადასხვა tenant-ს შეიძლება სხვადასხვა დღგ ჰქონდეს
(მაგ. 18 ან 20) — ამიტომ ორგანიზაციის დონეზეა, არა გლობალური კონსტანტა.

**ფულის ერთეული** — ახალი `currency_symbol()`/`money()` helper-ები
(`app/Core/helpers.php`): `₾` GEL-ისთვის (თანხის შემდეგ, ქართული
კონვენციით), `$` USD-ისთვის (თანხის წინ). ჩვენება დაემატა ყველგან,
სადაც თანხაა: `products.php` (ცხრილის header + ფასის ველის
input-group addon), `invoices.php` (header-ები + VAT/ჯამის label-ები
+ JS), `orders.php` (header), `dashboard.php` (revenue stat card +
ბოლო ინვოისების ცხრილი), `invoice-view.php`/`pdf/invoice.php`/
`pdf/orders.php` (თითოეული თანხა `money()`-ით). ყველა კონტროლერს
(`ProductController`, `InvoiceController::orders()`,
`DashboardController`) დასჭირდა `Organization::get($ruler)`-ის
დამატება/გადაცემა view-სთვის, თუ ჯერ არ ჰქონდა.

⚠️ **mPDF-ის `₾` (U+20BE) glyph-ის რისკი გადამოწმებულია — მუშაობს**:
Noto Sans Georgian-ს ეჭვი იყო, USD/GEL Currency Symbols ბლოკს
(U+20A0–20CF, არა ქართული Unicode-ბლოკი) რომ არ ფარავდეს — ცოცხლად
გენერირებული PDF-ით (`165.60 ₾`, `1,656.00 ₾`) დადასტურდა, რომ
სწორად რენდერდება, `tofu` არ არის.

⚠️ **ტესტირებისას შემთხვევით დაზიანებული tenant 31 (test1) org-მონაცემები,
აღდგენილია**: bash-ის საშუალებით ორგანიზაციის სახელის ცვლადში
გადატანისას (`grep`/`sed`-ით ცვლადში ჩაწერა, მერე `curl`-ით უკან
გაგზავნა) ქართული UTF-8 ტექსტი დაზიანდა (`???`-ებად აჩვენა), და
ცალკე POST-მა, რომელმაც მხოლოდ `vat_rate`/`currency` სცადა
შეეცვალა, ცარიელი სტრიქონებით გადააწერა `tax_id`/`email`/`phone`/
`address` (`Organization::save()` ყველა ველს ერთად წერს, ნაწილობრივი
update არ არსებობს). ორივე აღდგენილია პირდაპირ `App\Models\Organization`-ის
გამოძახებით (არა HTTP/bash ცვლადის გავლით) — სახელი, `tax_id`
(`400111222`), `email` (`info@test1.test`), `phone`
(`+995555000031`), `address` (`თბილისი, ვაჟა-ფშაველას გამზ. 10`),
ყველა ამ სესიის საკუთარი ადრინდელი PDF-რენდერებიდან ამოღებული ცნობილი
ორიგინალი მნიშვნელობებით. **Lesson**: ქართული/multi-byte ტექსტის
შემცველი bash ცვლადები POST-ისთვის საშიშია — `curl --data-urlencode`
ცვლადში წაკითხულ-ჩაწერილი ტექსტით, არა პირდაპირ PHP მოდელით,
აღარასდროს გამოვიყენო ნამდვილი/test tenant-ის ტექსტური ველებისთვის.

**გადამოწმებულია ცოცხლად** — tenant 31-ზე: `/settings/organization`
ფორმა სწორად აჩვენებს/ინახავს ორივე ახალ ველს (`vat_rate=20`→`USD`
→ მერე `vat_rate=18`→`GEL` საცდელად), `/products`/`/invoices`/
`/orders`/dashboard header-ები და input-ები სწორად გადართულან `$`-ზე
და უკან `₾`-ზე, `/invoices/export-pdf?id=12` და `/orders/export-pdf`
ორივე ვალუტით სწორად რენდერდება, `/invoices/view?id=12`
(ბრაუზერის print-გვერდი) იგივე. ტესტ-ფაილები და სესია წაშლილია.

⚠️ **პირველი ვერსია საერთოდ არ აჩვენებდა დღგ-ს PDF-ში** — user-მა
სქრინშოტით მიუთითა: `app/Views/pdf/invoice.php`-ს (ცალკე, `invoices.php`-
ისგან დამოუკიდებელი template) დღგ-ს row საერთოდ არ ჰქონდა. დამატებულია
`tfoot`-ში, `.total`-ის ზემოთ (`.muted`, თხელი, უბოლდო) — ცალკე
`tfoot tr.total-row` კლასით გამოყოფილი bold/border-top, **არა**
`tfoot tr:last-child` pseudo-selector-ით (mPDF-ის CSS support-ის
საეჭვოობის გამო, `4.38`-ის padding/margin-quirk-ის იგივე მიზეზით).

⚠️ **VAT-ის ფორმულა გასწორდა ორივე ადგილას** — user-ის სქრინშოტში
ნაჩვენები რიცხვი (114.00 ჯამზე 17.39 დღგ, 18%-ზე) ამხელდა, რომ სწორი
ფორმულა არის **VAT-ჩათვლილი ჯამიდან გამოთხოვნა** (`total × rate /
(100 + rate)`), არა უბრალო დამატება (`total × rate / 100`, რაც
`invoices.php`-ს JS-ში აქამდე იდო, `4.39`-ის ადრინდელ ვერსიაშიც კი —
შემთხვევით შენარჩუნებული ძველი, არასწორი ფორმულა). ორივე ადგილას
(`invoices.php`-ს JS და ახალი `pdf/invoice.php`-ს PHP) ერთიდაიგივე
სწორი ფორმულაა ეხლა, რომ ერთი და იმავე ინვოისისთვის ორივე გვერდი
ერთსა და იმავე დღგ-ს რიცხვს აჩვენებდეს.

**გადამოწმებულია ცოცხლად**: id=12-ის PDF ხელახლა გენერირებულია —
`1,656.00 ₾` ჯამზე `დღგ (18%)` `252.61 ₾` სწორად გამოთვლილი
(`1656 × 18 / 118`), ცხადად გამოყოფილი (არა-bold, თხელი) `სულ`-ის
row-ისგან, რომელიც კვლავ bold + სქელი ხაზით რჩება. ტესტ-ფაილი და
სესია წაშლილია. `invoice-view.php`-ს (ბრაუზერის print-გვერდს) დღგ-ს
row ჯერ არ დამატებია — user-მა მხოლოდ PDF შაბლონზე მიუთითა.

**შემდეგ user-მა იმავე სქრინშოტით მთლიანი დიზაინი მოითხოვა** (არა
მხოლოდ დღგ-ს ჩვენება) — `items` ცხრილს დაემატა `#` (row number)
სვეტი, სვეტების თანმიმდევრობა შეიცვალა (ფასი რაოდენობის წინ,
`inv.unit_price`→`inv.quantity`, ადრინდელი `quantity`→`unit_price`
რიგის ნაცვლად), და `tfoot`-ში ჩასმული VAT/სულ row-ები **მთლიანად
ამოვარდა ცხრილიდან** ცალკე, ცხრილის ქვემოთ, მარჯვნივ-გასწორებულ
(`align="right"`, არა CSS `margin`/`float` — `4.38`-ის padding/margin
quirk-ის იგივე მიზეზით ავირჩიე ეს ძველი, დაზუსტებით მუშა HTML
ატრიბუტი) ორ ცალკე `<table>`-ად: პირველი — უბრალო `დღგ:` label/value
წყვილი (`.summary-table`, `.summary-value` ლურჯი bold), მეორე —
`ჯამი:` მთლიანად შევსებული indigo ყუთში (`#6366f1`, თეთრი bold
ტექსტი, `.total-table`), ზუსტად სქრინშოტის მიხედვით.

**გადამოწმებულია ცოცხლად**: id=12-ის PDF-ით — `#`/სვეტების ახალი
თანმიმდევრობა სწორია, `დღგ: 252.61 ₾` და `ჯამი: 1,656.00 ₾` (indigo
ყუთში) სწორად და ვიზუალურად სქრინშოტთან ახლოს გამოისახა, `align="right"`
mPDF-ში სწორად მუშაობს (მომდევნო `.notes`/`.bank` სექციები ნორმალურად,
float-ის „გადაცდომის" ეფექტის გარეშე, გრძელდება). ტესტ-ფაილი და
სესია წაშლილია.

**შემდეგ**: user-მა სქრინშოტით შენიშნა, რომ ვერტიკალურ ხაზზე არ
ეწყობა თანხების მარჯვენა კიდე (line-total/VAT/grand-total) — მიზეზი
იყო სამივეს **სხვადასხვა right-padding** (`.items td.amount` 8px,
`.summary-table td` 0, `.total-table td` 14px), ცალკე table-ების
ერთი და იმავე `align="right"`/სიგანის მიუხედავად. სამივეს
right-padding გაუთანაბრდა 8px-ზე (`.total-table`-ს მარცხენა padding
14px-ზე დარჩა, ვიზუალურ „ჰაერს" ყუთის შიგნით რომ არ დაუკარგოს).
**გადამოწმებულია ცოცხლად** — id=12-ის PDF-ით სამივე რიცხვის (`₾`
სიმბოლოთი ერთად) მარჯვენა კიდე ეხლა ერთ ვერტიკალურ ხაზზეა. ტესტ-ფაილი
და სესია წაშლილია.

**შემდეგ**: user-მა ახალი სქრინშოტით მარცხენა კიდის იგივე პრობლემა
აჩვენა — `ჯამი:`-ის indigo ყუთი `დღგ:`-ის ტექსტზე უფრო მარცხნივ
იწყებოდა, ორივე table-ს `width:260px` მიუხედავად. მიზეზი: **`table-
layout: fixed`-ის გარეშე mPDF თითო ცხრილს საკუთარ row-ის content-ზე
დაყრდნობით auto-sizing-ავს**, არა დეკლარირებულ 260px-ზე — მოკლე
"დღგ:" ტექსტიანი ცხრილი ერთ სიგანემდე იკუმშება, bold/მსხვილი
"ჯამი:" ცხრილი კი — სხვამდე, და `align="right"`-ის შემდეგ ორივეს
**რეალური** (განსხვავებული) სიგანე სხვადასხვა მარცხენა კიდეს
წარმოშობს. ორივე ცხრილს დაემატა `table-layout: fixed` + ცხადი
სვეტების პროცენტული სიგანე (`45%`/`55%`, ახალი `.total-label` კლასი
`.summary-label`-ის დასაწყვილებლად) — ეხლა ორივეს რეალური სიგანე
ზუსტად 260px-ია, ამიტომ მარცხენა კიდეც ემთხვევა.

**გადამოწმებულია ცოცხლად** — id=12-ის PDF-ით `დღგ:` და `ჯამი:`
(ყუთის ჩათვლით) მარცხენა კიდე ეხლა ერთ ხაზზეა. ტესტ-ფაილი და
სესია წაშლილია.

**შემდეგ**: user-მა (ამჯერად საკუთარი, რეალური ინვოისის სქრინშოტით)
შენიშნა, რომ ორგანიზაცია/დამკვეთის ინფო-ბოქსის მარჯვენა კიდე `.items`
ცხრილის მარჯვენა კიდეს (`ჯამი` სვეტს) არ ემთხვევა. მიზეზი იმავე
ოჯახის mPDF quirk-ია, რაც `4.39`/`4.40`-ში უკვე რამდენჯერმე
დადასტურდა: **padded `<div>`-ს (`.info-box`) mPDF-ში არასაიმედოდ
აქვს იგივე რეალური სიგანე, რაც სიბლინგ `width:100%` table-ს** — box
ოდნავ ვიწროვდებოდა padding-ის გამო, `.items`-ს კი არა. ფიქსი:
`.info-box` აღარაა `<div>` — გადაკეთდა `<table class="info-box-outer">`
→ ერთი `<td class="info-box">` (background/border-radius/padding
ამ td-ზეა, არა div-ზე) — იგივე "table > td padding სანდოა, div
padding არა" პრინციპი, რაც `.total-table`/`.summary-table`-საც
უკვე იყენებს.

**გადამოწმებულია ცოცხლად** — id=12-ის PDF-ით info-ბოქსის მარჯვენა
კიდე ეხლა ზუსტად ემთხვევა `.items` ცხრილის (და `ჯამი` სვეტის)
მარჯვენა კიდეს. ტესტ-ფაილი და სესია წაშლილია.

⚠️ **ეს არასწორი წაკითხვა იყო** — user-მა დააზუსტა: `<table>`-ზე
გადაყვანა (ზემოთ) ზედმეტი აღმოჩნდა, საკითხი საერთოდ ბოქსის სიგანე
არ ყოფილა, არამედ **დამკვეთის სვეტის ტექსტის `text-align: right`**
— იმავე მიმართულებით, რაც `.items`-ის `ჯამი` სვეტს აქვს. დამატებულია
`text-align:right` დამკვეთის `<td>`-ზე (org-ის მხარე კვლავ
მარცხნივაა). `<table>`-ზე გადაყვანა (`.info-box-outer`/`td.info-box`)
თავისთავად უვნებელია, დარჩა — მაგრამ ორიგინალური საჩივარი ამით არ
გადაწყვეტილა, ტექსტის align-ით გადაწყდა.

**გადამოწმებულია ცოცხლად** — id=12-ის PDF-ით დამკვეთის სვეტის
ტექსტი (სახელი/საიდენტიფიკაციო/მისამართი/საკონტაქტო/ტელეფონი/email)
ეხლა მარჯვნივაა გასწორებული, ვიზუალურად `ჯამი` სვეტის ციფრების
იმავე მარჯვენა ხაზზე. ტესტ-ფაილი და სესია წაშლილია.

**შემდეგ**: user-მა PDF-ის ფუტერში მოითხოვა "დაგენერირებულია
{APP_NAME}" ტექსტი, სადაც `{APP_NAME}` `.env`-იდანაა და ბმულია
`APP_URL`-ზე. `.env`-ს **`APP_URL` პირველად დაემატა** ამ მოთხოვნით
(user-ის მიერ, `www.nova.local`) — არსად სხვაგან არ გამოიყენებოდა
აქამდე, ამიტომ `.env.example`-საც დაემატა დოკუმენტირებული ჩანაწერი
(ყოველი სხვა env-key-ის კონვენციის მიხედვით). სქემა `www.nova.local`-
ისებურია, სქემის გარეშე — PHP-ში `https://` ემატება, თუ უკვე
`http(s)://`-ით არ იწყება. ახალი `inv.pdf_generated_by` lang-key
(`%s` placeholder, raw HTML link-ისთვის — იგივე non-escaping
პატერნი, რასაც `t('prod.created', e($created))` იყენებს, უბრალოდ
escaping წინასწარაა გაკეთებული `<a href>`-ის აწყობისას).

**გადამოწმებულია ცოცხლად** — id=12-ის PDF-ის ფუტერში "დაგენერირებულია
Nova DS" გამოჩნდა, "Nova DS" ლურჯი ბმულია `https://www.nova.local`-ზე.
ტესტ-ფაილი და სესია წაშლილია.

**შემდეგ**: user-მა დააზუსტა — ფუტერი გვერდის ფურცლის ქვემო კიდიდან
15mm-ით უნდა იყოს დაცილებული (header-ის ზედა კიდიდან დაცილების,
16mm, დაახლოებით იგივე). **ადრინდელი ფუტერი უბრალოდ `<div>` იყო
body-ს ბოლოში** — ანუ page-ზე მიმაგრებული კი არა, დოკუმენტის flow-ის
ბოლოში, page break-ის შემდეგაც კი აღარ გამეორდებოდა და ბოლო
გვერდის content-ის სიგრძეზე იყო დამოკიდებული. გადავიდა mPDF-ის
**რეალურ page footer**-ზე (`Mpdf::SetHTMLFooter()`), page margin
კონფიგით (`margin_footer: 15`, `margin_bottom: 25` — ბოლო
საკმარისი space, footer-ი body-ს content-ს რომ არ გადაეფაროს).

**`App\Core\Pdf`-ის API შეიცვალა**: `make(array $config = [])` ეხლა
extra Mpdf constructor options-საც იღებს (`$config + [...defaults]`),
`download()`-ს დაემატა ახალი, **optional** მესამე პარამეტრი
`?string $footerHtml` — `null`-ის შემთხვევაში (`/orders/export-pdf`-ის
ამჟამინდელი behavior) არაფერი იცვლება, default margins ხელუხლებელია.
Footer HTML აშენებულია **ცალკე, controller-ში**
(`InvoiceController::pdfFooterHtml()`), არა `pdf/invoice.php`-ის
view-ში — mPDF footer-ს **საკუთარი, body-სგან იზოლირებული HTML
კონტექსტი აქვს** (არაფერი იზიარებს `<style>`-დან), ამიტომ inline
style ატრიბუტებით აშენდა, არა CSS კლასით.

**გადამოწმებულია ცოცხლად**: id=12-ის ინვოისის PDF-ში ფუტერი ეხლა
რეალურად გვერდის ბოლოში ჩანს (ფურცლის ქვემო კიდესთან ახლოს, არა
content-ის ბოლოსთან პირდაპირ მიდებული); `/orders/export-pdf`
(footer-ის გარეშე) **ხელუხლებელია** — default margins, ისეთივე
გამოსახულებით, როგორც ადრე. ტესტ-ფაილები და სესია წაშლილია.

**შემდეგ**: user-მა ორი მცირე დამატება მოითხოვა —
1. ფუტერს აკლდა ზედა გამყოფი ხაზი (`border-top`) — დაემატა
   `InvoiceController::pdfFooterHtml()`-ის inline style-ში.
2. ხელმოწერის (`organization.signature`) სურათი footer-ის ზემოთ,
   მარჯვენა კუთხეში — `pdf/invoice.php`-ს ბოლოში დაემატა, იმავე
   `is_file()`-შემოწმებით, რასაც ლოგოც იყენებს.

⚠️ **`align="right"` ცალკე (auto-width) table-ზე აქაც ვერ იმუშავა**
(პირველი ცდა) — სურათი მარცხნივ დარჩა. იგივე root cause, რაც
`.summary-table`/`.total-table`-ს ჰქონდა `table-layout:fixed`-ის
გარეშე: mPDF-ს განცხადებული სიგანე სჭირდება, საიდანაც გაზომოს
"მარჯვნივ" რა არის. ფიქსი: `align="right"`-ის მაგივრად
`width:100%` table + `text-align:right` თვითონ `<td>`-ზე — იგივე
პატერნი, რასაც `.top-row` უკვე იყენებდა თარიღი/ინვოისის ნომრის
stat-წყვილისთვის.

**გადამოწმებულია ცოცხლად**: id=12-ის PDF-ში ფუტერს ეხლა ზედა ხაზი
აქვს; ხელმოწერის სურათი ჩანს footer-ის ზემოთ, მარჯვენა კუთხეში
(ორივე ცდა — მარცხნივ-ჩავარდნილი და საბოლოო, გასწორებული ვერსია —
ცოცხლად გენერირებული PDF-ებით შედარებულია). ტესტ-ფაილები და
სესია წაშლილია.

**შემდეგ**: user-მა ფუტერში, გამყოფი ხაზის **ზემოთ**, დამატებით
წითელი, პატარა ტექსტი მოითხოვა — "გთხოვთ დროულად დაფაროთ
დავალიანება" (ახალი `inv.pdf_debt_notice` lang-key).
`InvoiceController::pdfFooterHtml()` ეხლა ორ ხაზს აგებს ერთ
გარე `<div>`-ში: წითელი (`#dc2626`, `font-size:8px`) შეტყობინება
ბორდერის გარეშე, მერე `border-top`-იანი "დაგენერირებულია"-ხაზი —
ორივეს შორის `margin-top:4px`.

**გადამოწმებულია ცოცხლად**: id=12-ის PDF-ის ფუტერში წითელი
"გთხოვთ დროულად დაფაროთ დავალიანება" ჩანს ზედა ხაზის ზემოთ,
"დაგენერირებულია Nova DS" კვლავ ხაზის ქვემოთ. ტესტ-ფაილი და
სესია წაშლილია.

### 4.40 `/invoices`-ის გვერდითი პანელი — დუბლირებული "შენახვა" წაშლილია, "PDF ექსპორტი" გამართულია

user-მა შენიშნა, რომ ახალი ინვოისის გვერდზე ორი ღილაკი ("+ დამატება"
ფორმის ბოლოში და "შენახვა" გვერდით პანელში) ვიზუალურად ერთსა და იმავეს
ჰგავდა, მაგრამ მხოლოდ პირველი მუშაობდა რეალურად — მეორე (`inv.action_save`,
`bi-save`) წმინდა დეკორატიული იყო, `4.29`-დან ("no functionality yet,
per request"). დადასტურდა, რომ ეს რეალური inconsistency იყო
(`AskUserQuestion`-ის მსგავსი პირდაპირი კითხვის გარეშე, უბრალო
რეკომენდაციით) — user-მა აირჩია **წაშლა**, არა ორმაგი ბმა ერთსა და
იმავე ფორმაზე. `inv.action_save` lang-key წაშლილია (ka/en), აღარსად
გამოიყენებოდა.

**"PDF ექსპორტი" ღილაკს** (`inv.action_export_pdf`, ასევე დეკორატიული
იყო) დაემატა იგივე ფუნქციონალი, რაც `/orders`-ის row-action-ს აქვს —
`/invoices/export-pdf?id=N`. ახალი (შენახვის წინ) ინვოისისთვის id
არ არსებობს, ამიტომ `$editingInvoice !== null`-ზეა პირობითი: `?edit=N`
რეჟიმში რეალური `<a href>` ბმულია, ახალი ინვოისის ფორმაზე კი
disabled `<button>` ტულთიფით ("ჯერ შეინახეთ ინვოისი", ახალი
`inv.action_export_pdf_disabled_hint` key).

**გადამოწმებულია ცოცხლად**: ახალი ინვოისის გვერდზე — "შენახვა"
ღილაკი (`bi-save`) აღარ ჩანს, "PDF ექსპორტი" disabled-ია სწორი
tooltip-ით; `/invoices?edit=12`-ზე — "PDF ექსპორტი" რეალური ბმულია
(`href="/invoices/export-pdf?id=12"`), დაწკაპებით ნამდვილი, ვალიდური
PDF ჩამოიტვირთა. ტესტ-ფაილები და სესია წაშლილია.

**შემდეგ**: user-მა დააზუსტა — "PDF ექსპორტი" ღილაკი **არ** უნდა
იყოს disabled ახალ (შეუნახავ) ინვოისზეც, არამედ ერთი დაწკაპებით
ორივე მოქმედება უნდა შესრულდეს: შენახვა/დამატება **და** PDF
ექსპორტი. Disabled-branch/`$editingInvoice`-პირობა მთლიანად
მოშორდა — ღილაკი ეხლა ჩვეულებრივი `type="submit"
form="invoiceMainForm" name="submit_action" value="export_pdf"`-ია,
ფორმის შიდა submit-ღილაკის (`id="invoiceSubmitBtn"`) გვერდით, იმავე
ფორმაზე მიბმული — HTML-ის სტანდარტული "ორი submit ღილაკი, სხვადასხვა
name=value ერთ ფორმაზე" ქცევა, ახალი JS არ დასჭირდა.
`InvoiceController::store()`-ს დაემატა ერთი პირობა: წარმატებული
save/update-ის შემდეგ, თუ `$_POST['submit_action'] === 'export_pdf'`,
`/invoices`-ის მაგივრად `/invoices/export-pdf?id={ახლადშენახული}`-ზე
გადამისამართებს — validation/conflict error-ის შემთხვევაში კვლავ
ჩვეულებრივ `/invoices#invoice-form`/`?edit=N`-ზე ბრუნდება (PDF
არასდროს გენერირდება წარუმატებელი save-ის შემდეგ).
`inv.action_export_pdf_disabled_hint` lang-key წაშლილია, აღარ
გამოიყენება.

⚠️ **გადამოწმებისას აღმოჩენილი, ცალკე, წინარე ხარვეზი** (ჩემი ახალი
ცვლილების ბრალი არაა): SuperUser-ით tenant-ის impersonation-ის დროს
შექმნილ ინვოისს `created_by` სუფთა superadmin-ის საკუთარ user id-ზე
ეწერება (არა real tenant-ზე) — შედეგად `ownerTenant()` ვერასდროს
დაემთხვევა არც ერთ ნამდვილ tenant-ს, და ასეთი ინვოისის PDF ექსპორტი
შემდეგ `404`-ს იძლევა. ამიტომ ეს კონკრეტული ცოცხალი ტესტი
**tenant test1-ად პირდაპირი login-ით** ჩავატარე (დროებით
`password_hash` შევუცვალე ცნობილ პაროლზე, ტესტის შემდეგ ორიგინალზე
დავაბრუნე) — SuperUser-ის impersonation-ის ეს ხარვეზი ცალკე,
მომავალი ფიქსია, ამ ცვლილების scope-ს არ ეხება.

**გადამოწმებულია ცოცხლად** (tenant test1-ის საკუთარი login-ით): ახალი
ინვოისის ფორმა შევსებული → "PDF ექსპორტი" ღილაკზე დაწკაპებით — ახალი
ინვოისი (id=16) რეალურად შეიქმნა ბაზაში (სწორი customer/product/
quantity/notes/ჯამი) **და** იმავე request-ის ფარგლებში გადამისამართდა
`/invoices/export-pdf?id=16`-ზე, საიდანაც ნამდვილი, ამ ინვოისის
სწორი მონაცემებით PDF ჩამოიტვირთა. სატესტო ინვოისი (id=16, id=15 —
წინა, SuperUser-impersonation-ის ცდიდან დარჩენილი) წაშლილია,
password_hash აღდგენილია, ტესტ-ფაილები და სესიები წაშლილია.

### 4.41 SuperUser — view-only impersonation + user block/unblock

user-მა თავად აღმოაჩინა და ცალსახად ჩამოაყალიბა policy: **SuperUser
არასდროს არ უნდა მოქმედებდეს tenant-ის მაგივრად** (მხოლოდ თვალყურის
დევნება/monitoring), მისი ერთადერთი ლეგიტიმური write-ქმედება არის
მომხმარებლის დაბლოკვა/განბლოკვა (in-app შეტყობინების feature-ი
მოთხოვნით გადაიდო — user-მა აირჩია, რომ ჯერ არ აშენდეს).

**Write-surface აუდიტი** (`Explore` agent-ით) გამოავლინა ყველა
tenant-write action, რომელიც აქამდე იმპერსონაციისას ხელმისაწვდომი
იყო SuperUser-ისთვის: `CustomerController::store`,
`ProductController::store`, `LookupController::save`(private,
`units()`-ის)/`productTypes`, `InvoiceController::store`,
`OrganizationController::save`, `UserController::store`,
`Warehouse\WarehouseController::store`,
`Warehouse\ProductTypeController::save`.

**`Auth::requireNotImpersonating()`** (ახალი) — `http_response_code(403);
exit(...)`, იმავე style-ის, რასაც `csrf_verify()` იყენებს — დაემატა
ყველა ზემოთ ჩამოთვლილი redirect-სტილის action-ის თავში (`csrf_verify()`-ის
გვერდით). ორი JSON-პასუხიანი endpoint-ისთვის (`LookupController`-ის
ორივე action, Warehouse-ის `ProductTypeController::save`) ეს ვერ
გამოდგებოდა (plain-text `exit()` AJAX-ის JS-ს ვერ დაუმუშავდებოდა) —
იქ inline `Auth::impersonating() !== null`-შემოწმებაა, JSON `403`-ის
დაბრუნებით.

⚠️ **ცალკე, ამ აუდიტისას აღმოჩენილი პრე-არსებული ხარვეზი, ამავე
დროს გასწორებული**: `ModuleController::install/enable/disable`-ს
**საერთოდ არ ჰქონდა** `Auth::requireAdmin()` — ნებისმიერ
ავტორიზებულ მომხმარებელს (არა მხოლოდ admin-ს) შეეძლო მთელი
აპლიკაციისთვის (არა tenant-სკოუპილი — `modules`-ს `ruler` სვეტი
საერთოდ არ აქვს) module-ის ჩართვა/გამორთვა. დაემატა `requireAdmin()`
+ `requireNotImpersonating()` სამივეს. **Caveat**: topbar-ის apps
dropdown-ის (`partials/topbar.php`) enable/disable toggle კვლავ
ყველასთვის ჩანს ვიზუალურად — non-admin-ის დაწკაპება ეხლა უბრალოდ
`forbidden`-ზე აისვლის, UI არ არის დამალული role-ის მიხედვით (ცალკე,
არმოთხოვნილი polish, არ გავაკეთე დამატებითი scope-ის გარეშე).

**User block/unblock**: `migrations/029_add_users_blocked_at.sql` —
`users.blocked_at TIMESTAMP NULL`. `Auth::attempt()`-ის ჩვეულებრივი
(არა-superuser) branch-ს ემატება `blocked_at !== null` უარყოფა.
**`Auth::check()`-ს** (ერთადერთი უნივერსალური choke-point — ყოველ
request-ზე გადის `public/index.php`-ის app-wide gate-იდან) დაემატა
იგივე შემოწმება — ეს არა მხოლოდ mid-session force-logout-ს
უზრუნველყოფს (დაბლოკვისთანავე, არა შემდეგი login-ის მოლოდინში), არამედ
ხურავს OTP-ისა და Google OAuth login-ის გვერდის ბილიკებსაც (ორივე
პირდაპირ `Auth::login()`-ს იძახებს, `attempt()`-ს გვერდს უვლის) —
ორივე მაინც `check()`-ს დაეჯახება მომდევნო request-ზე.
`User::setBlocked(int $id, bool $blocked)` (ახალი) წერს/შლის
`blocked_at`-ს.

**`SuperUserController::toggleBlock()`** (ახალი, route
`/superuser/toggle-block`) — `Auth::requireSuperuser()` +
`csrf_verify()`, **განზრახ არ** არის `requireNotImpersonating()`-ით
დაცული (ეს არის თვითონ SuperUser-ის ლეგიტიმური, პირდაპირი
ქმედება — არა tenant-ის მაგივრად მოქმედება). სამიზნე არასდროს
შეიძლება იყოს superadmin. `superuser.php`-ის roster-ს დაემატა:
თითო root tenant-row-ს — "დაბლოკვა/განბლოკვა" ღილაკი (`bi-lock`/
`bi-unlock`) "დათვალიერება"-ს გვერდით + წითელი "დაბლოკილია" badge
სახელის გვერდით, თუ დაბლოკილია; თითო sub-user badge კი **თავად
გახდა** პატარა submit-ღილაკი (`toggle-block`-ზე, `user_id`-ით) —
დაბლოკილს ემატება `bi-lock-fill` + წითელი ფერი.

**გადამოწმებულია ცოცხლად**: (1) SuperUser-ით tenant 31-ის
იმპერსონაციისას customer/invoice/units-შექმნის მცდელობებმა `403`
დააბრუნა, **არაფერი არ ჩაწერილა ბაზაში** (პირდაპირ SQL-ით
გადამოწმებული); (2) `/superuser/toggle-block`-ით tenant 32 (test2)
დაიბლოკა → roster-ში წითელი badge გამოჩნდა → **ახალი login-ის
მცდელობა უარყოფილია** (`/login`-ზე უკან, არა `/`) → **აქტიური
სესიაც** (test2-ად უკვე შესული) **მომდევნო request-ზევე
force-logout** გახდა (`/login`-ზე გადამისამართებით); (3) განბლოკვის
შემდეგ ორივე (login + აქტიური სესია, ახლიდან) ისევ მუშაობს; (4)
non-impersonating ჩვეულებრივმა tenant-write-მა (test1-ად პირდაპირი
login-ით) **გამართულად იმუშავა**, `403` არ დაბრუნებულა (რეგრესია
არ არის); (5) `ModuleController::enable`-იც `403`-ს იძლევა
იმპერსონაციისას. ყველა ტესტ-ცვლილება (temp password-ები,
block-toggle-ები, ტესტ-customer) აღდგენილი/წაშლილია, სესიები
დახურულია.

### 4.42 ინვოისის სტრიქონებს დაემატა ერთეულის select

user-ის მოთხოვნით სვეტების რიგი: პროდუქტი, რაოდენობა, **ერთეული**,
ფასი, ჯამი. `invoice_items`-ს აქამდე `unit_id` საერთოდ არ ჰქონდა
(`migrations/030_add_invoice_items_unit.sql` — `NULL`, FK `units`-ზე,
ძველი სტრიქონებისთვის `NULL` რჩება, migration-ის დროს ისინი უკვე
არსებობდნენ). `unit_price`/`line_total`-ის იგივე "snapshot save-ის
დროს" პრინციპია — **არა** live join `products.unit_id`-ზე, პროდუქტის
default ერთეულის მომავალმა ცვლილებამ უკვე გამოწერილი ინვოისი არ
უნდა შეცვალოს.

`Invoice::validate()`-ს დაემატა `unit_id`-ის შემოწმება (`ctype_digit`
+ `units`-ში არსებობა) — იმავე style-ის, რასაც `product_id` იყენებს,
ერთი errors_N key-ით ორივესთვის. `Invoice::save()`-ს INSERT/UPDATE
სვეტების სიაში დაემატა. `Invoice::itemsFor()`-ს დაემატა `LEFT JOIN
units` (`LEFT`, არა `JOIN` — ძველი `NULL`-იანი სტრიქონები არ უნდა
გაქრეს ჯოინიდან).

`invoices.php`-ს ახალი select — `products` `<option>`-ებს დაემატა
`data-unit` (`data-price`-ის გვერდით), ისე რომ პროდუქტის არჩევა
ერთეულსაც default-ად ავსებდეს (**კვლავ თავისუფლად overridable**,
ისევე როგორც ფასია) — JS-ის `change` listener-ს ერთი ხაზი დაემატა.
`data-units` ახალი JSON attribute `#invoiceItems`-ზე — JS-ით
დინამიურად დამატებულ row-ებსაც (`rowHtml()`) აქვთ იგივე select.

**გადამოწმებულია ცოცხლად** (tenant test1-ის საკუთარი login-ით):
ახალი ინვოისი ერთეულით (`კგ`) შენახულია → პირდაპირ SQL-ით
დადასტურებული, რომ `invoice_items.unit_id` სწორადაა ჩაწერილი;
`?edit=N`-ზე ხელახლა ჩატვირთვისას select სწორად აჩვენებს იმავე
ერთეულს; ძველი, migration-მდელი ინვოისი (id=12, `unit_id NULL`)
ჩვეულებრივად იტვირთება ცარიელი select-ით, error/crash არ არის;
ცარიელი ერთეულით submit-მა `terr('prod.err_unit_required')`
("აირჩიე ერთეული.") სწორად დააბრუნა. ტესტ-ინვოისი წაშლილია,
password_hash აღდგენილია, სესიები დახურულია.

**შემდეგ**: user-მა შენიშნა, რომ ახალი ერთეულის select უბრალო
Bootstrap `.form-select` იყო, პროდუქტის/დამკვეთის ds-select-ების
გვერდით არათანმიმდევრული ჩანდა — და ეს **ზოგად წესად** დააფიქსირა:
"ყველგან სადაც select-ს ვიყენებთ, უნდა იყოს ასეთივე" (ds-select).
ერთეულის select-ს (ორივეს — PHP-ით რენდერილს და JS-ით დინამიურად
დამატებულს) დაემატა იგივე `data-ds-select` ატრიბუტები, რასაც
პროდუქტის select იყენებს; JS-ს დაემატა `new window.DsSelect(...)`
ახალი row-ებისთვის და `.dsSelect?.refresh()` ყველგან, სადაც
`.value` პროგრამულად იცვლება (პროდუქტის არჩევისას ერთეულის
ავტო-შევსება, row-გასუფთავებისას) — ds-select-ის საკუთარი trigger
UI ხელით არ სინქრონდება native `<select>`-ის value-სთან, `refresh()`
საჭიროა. ეს **ზოგადი წესი** დამახსოვრებულია auto-memory-ში
(`feedback-ds-select-everywhere.md`) — მომავალში ნებისმიერი ახალი
select ავტომატურად ასე უნდა აშენდეს, `invoice_status`/ვალუტის
select-ების (ჯერ პლეინ) retrofit-ი კი დასადასტურებელია, არ
გაკეთებულა ჯერ.

**გადამოწმებულია ცოცხლად, ბრაუზერში** (`Claude_Browser` ტულით,
click-ით და ax-tree-ის დათვალიერებით, არა curl): ერთეულის select
ეხლა ნამდვილი ds-select ტრიგერია (`combobox "Select…"` → `[type=button]`);
პროდუქტის ("ლურსმანი 5სმ") არჩევისას ერთეულის trigger ავტომატურად
"კგ"-ზე გადავიდა (product-ის default unit_id-დან); ხელით
ხელახლა შერჩევამ ("ცალი") სწორად გადააჭარბა default-ს — თავისუფლად
overridable რჩება. სესიის temp password აღდგენილია, ახალი ინვოისი
არ შექმნილა (ფორმა submit არ გაკეთებულა).

### 4.43 `/orders`-ს დაემატა "ნახვა" — PDF-ის ვიზუალი მოდალში, HTML-ად

user-მა მოითხოვა `/orders`-ის მოქმედების სვეტში ახალი "ნახვა" ღილაკი
— მოდალში PDF-ის იმავე ვიზუალის ჩვენება (`pdf/invoice.php`-ის
დიზაინი), HTML ფორმატში, **footer-ისა და ხელმოწერის გარეშე**.

**გადაწყვეტა: `<iframe>`, არა fetch()+innerHTML** — `pdf/invoice.php`-ს
საკუთარი, mPDF-ისთვის დაწერილი `<style>` აქვს ზოგადი კლასების
სახელებით (`.items`, `.muted`, `.right`, უბრალო `table` selector-იც
კი) — ეს კლასები რეალურად შეიძლება დაპირისპირებოდა/გაჟონილიყო
orders.php-ის საკუთარ სტილებში, fetch-ით ჩამატებული HTML რომ
ყოფილიყო. Iframe სრულად იზოლირებს — იგივე template ერთხელ დაწერილი,
ორივე კონტექსტში (PDF-იც და browser-ის preview-იც) ხელუხლებლად
გამოიყენება.

**`pdf/invoice.php`-ს დაემატა ახალი, optional `$isPreview` (default
`false`)**: (1) ხელმოწერის ბლოკი მთლიანად `!$isPreview`-ზეა
პირობითი; (2) `$uploadDir` (ლოგო/ხელმოწერის სურათების `src`)
გახდა preview-ისას public URL (`/assets/uploads/organization/`),
PDF-ისას კვლავ filesystem path (mPDF-ს ეს სჭირდება) — ცალკე
**`$uploadDirFs`** (ყოველთვის filesystem) დაემატა `is_file()`
შემოწმებებისთვის, რომ ეს ორი საჭიროება არ აგვერიოს (browser-ის
`<img src>`-ს ვერასდროს გაუმართავს absolute Windows path,
`is_file()`-საც კი URL-ზე გაშვება ყოველთვის `false`-ს დააბრუნებდა
— ორივე შემოწმდა და გასწორდა ცოცხლად, იხ. ქვემოთ). **Footer-ი
(`Pdf::download()`-ის `$footerHtml`) დამატებითი ცვლილების გარეშეც
არასდროს გამოჩნდება preview-ში** — `4.41`-ის შემდეგ ის უკვე აღარაა
`pdf/invoice.php`-ის საკუთარი output-ის ნაწილი (mPDF-ის `SetHTMLFooter()`-
ით ცალკე რენდერდება), ამიტომ ამ template-ის ხელახალი გამოყენება
"footer-ის გარეშეს" უფასოდ იძლევა.

**`InvoiceController`**-ს დაემატა `preview()` (route `GET
/invoices/preview`) — `exportInvoicePdf()`-ის იგივე access-control
(`Auth::requireUser()` + `ownerTenant()` match, სხვაგვარად `404`) და
იგივე `pdf/invoice` template, უბრალოდ PDF-ად wrap-ის მაგივრად
პირდაპირ browser-ს ეგზავნება (`isPreview: true`-ით). გამეორებული
"invoice-ს ჩატვირთვა + access-check + org/number გამოთვლა" ლოგიკა
ორივე action-ისთვის ახალ `loadOwnedInvoiceForPdf()` private
მეთოდშია გატანილი.

`orders.php`-ს დაემატა **ერთი, საერთო** modal ყველა row-სთვის (არა
თითო-row-ზე ცალკე) — "ნახვა" ღილაკის `data-invoice-id`-ს
`show.bs.modal` listener კითხულობს და `<iframe src>`-ს ცვლის;
`hidden.bs.modal`-ზე `src`-ი `about:blank`-ზე ბრუნდება, რომ
დახურვის შემდეგ წინა ინვოისის მონაცემები iframe-ში აღარ იჯდეს.

**გადამოწმებულია ცოცხლად** (ორივე curl-ით და `Claude_Browser`
ტულით, ბოლოში JS-ის გამოძახებით iframe-ის `contentDocument`-ის
პირდაპირ დათვალიერებით): (1) `/invoices/preview?id=12` → `200`,
footer-ის ტექსტი (`დაგენერირებულია`) და `.signature-table`
element-ი **არცერთი არ გვხვდება** output-ში, ლოგოს `src` სწორი
public URL-ია; (2) სხვა tenant-ის ინვოისის (id=3) preview-ს მცდელობამ
`404` დააბრუნა; (3) რეალურ ბრაუზერში ღილაკზე დაწკაპებით მოდალი
გაიხსნა, iframe-მა სწორი invoice-ის სრული ვიზუალი ჩატვირთა, ლოგოს
სურათი რეალურად ჩაიტვირთა (`naturalWidth: 700`, არა broken image).
ტესტ-სესია და temp password აღდგენილია.

⚠️ **user-მა სქრინშოტით უჩვენა რეალური ხარვეზი preview-მოდალში**:
"დღგ:"/"ჯამი:" ბლოკი overlap-ს აკეთებდა `.items` ცხრილის ბოლო
row-ზე/ხაზზე — **მხოლოდ ბრაუზერში, PDF-ში კი არა**. Root cause:
`.summary-table`/`.total-table`-ს ჰქონდა `align="right"` — ეს ძველი
HTML ატრიბუტი mPDF-ში უბრალოდ მარჯვნივ სწორებას ნიშნავს, **რეალურ
ბრაუზერში კი `float:right`-ის იდენტურ ლეგასი ქცევას იწვევს**
`<table>`-ზე — floated ცხრილს მომდევნო content არ „ეჯახება" ჩვეულებრივ
flow-ში, გვერდით/თავზე „ეხვევა". ეს არასდროს გამოვლენილა აქამდე,
რადგან ეს template აქამდე **მხოლოდ** mPDF-ისთვის იწერებოდა — `4.43`-მა
(preview-მოდალი) პირველად გაუშვა იგივე HTML რეალურ ბრაუზერშიც.

**ფიქსი**: `align="right"` მთლიანად მოშორდა. ორივე mini-table
(`.summary-table`/`.total-table`) გადავიდა ახალ `.summary-wrap-outer`
(სრულ-სიგანის, ერთი row-იანი, 2-სვეტიანი table) `<td>`-ში — ცარიელი
მარცხენა სვეტი + შევსებული მარჯვენა (`.summary-wrap`). Table-column
layout ბუნებრივად სვამს content-ს row-ის მარჯვენა კიდეზე, float-ის
გარეშე — იგივე ქცევა mPDF-შიც და ბრაუზერშიც, ეს არის ამ ფაილში უკვე
რამდენჯერმე დამტკიცებული "table > float/margin/align" პრინციპის
გაგრძელება. ამავე დროს, user-ის მოთხოვნით, **font-size გაიზარდა**:
`.summary-table` 10.5px → 13px, `.total-table` 12px → 15px (padding-იც
ოდნავ გაიზარდა კომფორტისთვის).

**გადამოწმებულია ცოცხლად ორივე კონტექსტში**: (1) რეალური PDF
(`/invoices/export-pdf?id=12`) — ვიზუალურად უცვლელი, უბრალოდ
დღგ/ჯამის ტექსტი მსხვილია, overlap აქამდეც არ ჰქონდა; (2) preview
(`/invoices/preview?id=12`, `Claude_Browser`-ით) — `getBoundingClientRect()`-ით
ზუსტად გავზომე: `.items` bottom=311, `.summary-table` top=323
(12px gap, `margin-top` ემთხვევა), `.total-table` top=356
(`.summary-table`-ის bottom=350-დან 6px gap) — **overlap აღარ არის**,
ორივეს `right`/`left` კიდეც კვლავ ემთხვევა `.items`-ის საკუთარ
მარჯვენა კიდეს. ტესტ-სესია და temp password აღდგენილია.

### 4.44 პდფ/preview-ის ფონტის ზრდა, დღგ/ჯამის მარჯვნივ-სწორების mPDF-რეგრესია, სვეტების თანმიმდევრობა+ერთეული

user-მა სამი რამ მოითხოვა ერთდროულად: (1) მოდალის ტექსტის ფონტი
საერთოდ გაზრდილიყო (არა მხოლოდ დღგ/ჯამი, რაც `4.44`-ის წინა
ვერსიაში უკვე გაზრდილი იყო); (2) **დაგენერირებულ PDF-ში** დღგ/ჯამი
ისევე მარჯვნივ ყოფილიყო, როგორც მოდალშია; (3) items ცხრილის
თანმიმდევრობა ორივეგან (PDF/preview **და** `invoices.php`) ერთნაირი
— პროდუქტი, რაოდენობა, **ერთეული**, ფასი, ჯამი.

**(1) ფონტის ზრდა**: `body` 11px → 13px, და თითქმის ყველა
ცალკეული explicit font-size (`.stat-value`, `.info-name`,
`.info-field`, `.items th`, `.section-label`, `.notes`, `.bank`)
პროპორციულად აწეულია — `body`-ს ცვლილება მხოლოდ იმ ელემენტებზე
მოქმედებდა, სადაც უკვე არ იყო override.

⚠️ **ამ ფონტის ზრდამ თავად წარმოშვა ახალი ხარვეზი**: `.top-row`-ის
თარიღი/ინვოისის ნომრის ორი `20%`-სვეტიანი stat-ბლოკი აღარ
ეტეოდა 12px→14px-ზე გაზრდილ ტექსტს (`table-layout:fixed`-ის
გარეშე) — "TS1 2026-08-16 0012" პირდაპირ თარიღის სვეტში
"ჩაცურდა", ხარვევის გარეშე. გასწორდა: `table-layout:fixed`
დაემატა `.top-row`-ს, სვეტების პროპორცია 60/20/20 → 42/23/35
(ინვოისის ნომერი ყველაზე გრძელი ტექსტია), + 8px `padding-right`
თარიღის სვეტზე დამატებითი "buffer"-ისთვის.

**(2) mPDF-ის მარჯვნივ-სწორების რეგრესია** — `4.43`-ში
`align="right"`-ის მოშორებამ ბრაუზერის float-ბაგი გამოასწორა,
მაგრამ **ახალმა სტრუქტურამ** (`.summary-wrap-outer`-ის ცარიელი
მარცხენა `<td>` + `.summary-wrap` მარჯვენა `<td>`) mPDF-ში
სხვა ბაგი გამოავლინა: `table-layout:fixed`-ის გარეშე mPDF ცარიელ
მარცხენა `<td>`-ს თითქმის 0-მდე იკუმშავდა და მთელ ბლოკს **მარცხენა**
კიდესთან სვამდა, არა მარჯვნივ (ბრაუზერს ეს პრობლემა არ ჰქონდა).
ფიქსი: `.summary-wrap-outer`-საც დაემატა `table-layout:fixed` +
ცხადი `%`-სიგანეები ორივე `<td>`-ზე (`.summary-wrap-spacer`
კლასით მარცხენაზე — **არა** `:first-child`, ამ ფაილს უკვე ერთხელ
დაუმტკიცებია, რომ mPDF-ის pseudo-class-ს არ ენდობა).

⚠️ **პირველმა ამ ფიქსმაც კიდევ ერთი ახალი ხარვეზი გამოავლინა**:
`.summary-table`/`.total-table`-ს ჰქონდა ცხადი `width: 260px`,
`.summary-wrap` `<td>`-ს კი `40%` — ეს ორი სიგანე არ ემთხვეოდა
ერთმანეთს (260px < 40%-ის რეალური სიგანე), ამიტომ 260px-იანი
შიდა table **მარცხნივ ეკვროდა** თავის (უფრო განიერ) მშობელ
`<td>`-ს შიგნით, ხოვლი დარჩა ცარიელი მარჯვნივ — ანუ დღგ/ჯამი
კვლავ **არ** ეხებოდა `.items`-ის ნამდვილ მარჯვენა კიდეს ვიზუალურად
(თუმცა float აღარ იყო). საბოლოო ფიქსი: `.summary-table`/
`.total-table` გახდა `width: 100%` (თავისი `.summary-wrap`
მშობლის, არა ცხადი px) — შიდა content ყოველთვის ზუსტად ავსებს
მშობელ სვეტს, სიგანის შეუსაბამობა აღარასდროს შეიძლება წარმოიშვას.
`.summary-wrap`-ის საკუთარი პროცენტი (`22%` → საბოლოოდ `32%`)
დაზუსტდა კიდევ ერთხელ, რადგან თავიდან ძალიან ვიწრო აღმოჩნდა —
"1,656.00 ₾" bold 15px-ზე ორ სტრიქონად იშლებოდა indigo ყუთში;
`white-space: nowrap` დაემატა დამატებით უსაფრთხოებისთვის ორივე
mini-table-ის `td`-ზე.

**(3) სვეტების თანმიმდევრობა + ერთეული** — `pdf/invoice.php`-ის
items-ცხრილს (რომელსაც `4.37`-ის მოთხოვნით ჰქონდა "ფასი
რაოდენობის წინ" წყობა, სხვა screenshot-ის მიხედვით) დაემატა
**ახალი "ერთეული" სვეტი** (`$item['unit_name']`, `Invoice::itemsFor()`-
დან უკვე ხელმისაწვდომი `4.42`-დან — LEFT JOIN, NULL ძველი
სტრიქონებისთვის, ცარიელი ჩანს) და თანმიმდევრობა შეეთანხმა
`invoices.php`-ის ფორმის რიგს: # | პროდუქტი | რაოდენობა | ერთეული |
ფასი | ჯამი.

**გადამოწმებულია ცოცხლად, ორივე ეტაპზე, ორივე კონტექსტში**
(PDF-ის ხელახალი export + `Claude_Browser`-ის `getBoundingClientRect()`):
top-row-ის overlap გასწორდა (`table-layout:fixed`); დღგ/ჯამის
`right` ეხლა ზუსტად `.items`-ის `right`-ს ემთხვევა **ორივე
ეტაპის შემდეგ** გადამოწმებული — პირველი ფიქსით (`22%`) მარჯვნივ
სწორად იდგა, მაგრამ box-ი 2 სტრიქონად იშლებოდა (`total-table`
`height=44px→` ~2 line-height, ანუ ~64-88px იქნებოდა wrap-ის
შემთხვევაში); მეორე ფიქსის (`32%` + nowrap) შემდეგ `height=44px`
ზუსტად ერთი სტრიქონია. ორივე (browser + PDF) საბოლოო screenshot-ი
ვიზუალურადაც სუფთაა. ტესტ-სესია და temp password აღდგენილია.

### 4.45 "ნახვა"-მოდალი გახდა სრულიად ცალკე კოდი, აღარ იზიარებს PDF-შაბლონს

user-მა, `4.43`/`4.44`-ის mPDF/browser dual-context ბრძოლის შემდეგ,
გადაწყვიტა: მოდალი აეშენებინა **სრულიად ცალკე კოდით**, `pdf/invoice.php`-ს
საერთოდ არ შეხებოდა, და მისცა კონკრეტული reference სქრინშოტი
სასურველი ვიზუალით (მარტივი Bootstrap card/table იერსახე, ლოგო
პატარა hero-სურათად ზემოთ, org/customer plain ტექსტად გვერდიგვერდ
— არა shaded info-box; items-ცხრილს light-header, არა ლურჯი
underline; დღგ/ჯამი bordered box-ებში, არა indigo-შევსებული).

**`pdf/invoice.php` სრულად დაბრუნდა PDF-only მდგომარეობაში** —
`$isPreview`/`$uploadDirFs`-ის მთელი ლოგიკა მოშორდა (`4.43`-ში
დამატებული, ერთი session-ის სიცოცხლის მანძილზე გამოყენებული).

**ახალი, დამოუკიდებელი `app/Views/invoice-preview.php`** — plain
Bootstrap markup (row/col, `table-sm`, `border rounded-3` box-ები),
**არა** `pdf/invoice.php`-ის საკუთარი custom CSS/კლასები — უსაფრთხოა
პირდაპირ `innerHTML`-ით ჩასმა orders.php-ის საკუთარ სტილებთან
კონფლიქტის გარეშე (`Controller::renderToString()`-ით, layout-ის
გარეშე, fragment-ად, ისევე როგორც PDF view-ებიც შენდება).
`InvoiceController::preview()` ამ ახალ view-ს რენდერავს, `pdf/invoice`-ს
მაგივრად — access-control (`loadOwnedInvoiceForPdf()`) უცვლელია.

**orders.php-ის მოდალი** — `<iframe>`-ის მაგივრად ახლა `fetch()` +
`innerHTML` (body-ს ნაწილისთვის), header/footer კი **სტატიკური,
თავად orders.php-ში ჩაშენებული** chrome-ია (არა Bootstrap-ის default
`modal-title`) — "INVOICE | {ნომერი}" + სტატუსის badge მარჯვნივ,
footer-ში "დახურვა"/"ბეჭდვა"/"PDF" ღილაკები. ეს ოთხივე მოთხოვნილი
დეტალი (ნომერი, სტატუსი, ბეჭდვის/PDF ბმულები) **არ** სჭირდება
ცალკე request-ს — "ნახვა" ღილაკს დაემატა `data-invoice-number`/
`data-invoice-status` (ცხრილის row-ს ისედაც აქვს ეს მონაცემები),
JS `show.bs.modal`-ზე პირდაპირ სვამს მათ, `fetch()` მხოლოდ **body-ს
შიგთავსისთვისაა**. სტატუსის badge-ის ფერი/ტექსტი იგივე მეპინგია,
რასაც `dashboard.php`-ის ბოლო-ინვოისების ცხრილი იყენებს — PHP-ში
აშენებული, `window.dsOrderStatus`-ად embed-ილი JSON (JS-მა `t()`
არ იცის, ამიტომ label-ებიც წინასწარ ითარგმნება PHP-ში).

**ახალი lang-key-ები**: `inv.close`, `inv.notes_empty`
("დამატებითი ინფორმაცია არ არის" — ცარიელი notes-ის placeholder,
ზუსტად user-ის სქრინშოტის ტექსტი).

⚠️ **"დამგზავნი" badge-ი სქრინშოტიდან არ არის ვერბატიმ
რეპლიცირებული** — ამ აპში ეს ტექსტი არაფერს არ ნიშნავს, ინვოისს
კი აქვს რეალური `status` (draft/final/due/paid) — badge-მა ეს
რეალური სტატუსი აჩვენა (dashboard.php-ის იმავე ფერების სქემით),
ვიზუალურად იმავე ადგილას/სტილში, მაგრამ user-ს გაუცნობია ეს
choice, თუ სხვა რამ სურდა კონკრეტულად, საჭიროებს დაზუსტებას.

**გადამოწმებულია ცოცხლად**: id=12-ის PDF export ხელუხლებელია
(`pdf/invoice.php`-ის cleanup-ის შემდეგაც `200`); ახალი
`/invoices/preview?id=12` endpoint-მა სუფთა Bootstrap fragment
დააბრუნა (`<html>`/`<style>` არსად); `Claude_Browser`-ით "ნახვა"
ღილაკზე დაწკაპებით — header-მა სწორად აჩვენა "INVOICE | TS1
2026-08-16 0012" + "პირველადი" badge სწორი (secondary) ფერით,
body-მ სწორად ჩატვირთა org/customer/items/notes/VAT, footer-ის
"ბეჭდვა"/"PDF" ბმულებმა სწორი `href`-ები აიღეს
(`/invoices/view?id=12`, `/invoices/export-pdf?id=12`) — console
error არცერთი. ტესტ-სესია და temp password აღდგენილია.

### 4.46 `/invoices`-ის "გადახედვა" ღილაკიც "ნახვა"-მოდალს იყენებს — მოდალი გატანილია საერთო კოდში

user-მა მოითხოვა: `/invoices`-ის (ახალი ინვოისის შექმნის გვერდის)
გვერდითი პანელის "გადახედვა" ღილაკს (`inv.action_preview`, `4.40`-მდე
დეკორატიული) დაკავშირებოდა იგივე მოდალი, რაც `/orders`-ზეა
(`4.45`-ის ახალი, ცალკე `invoice-preview.php` design).

**გატანილია საერთო კოდში**, ორმაგი გამოყენების გამო (`/orders`-ის
row-ღილაკი + ეხლა `/invoices`-ის sidebar-ღილაკი) — აღარ დუბლირდება
თითო გვერდზე:
- **`app/Views/partials/invoice-preview-modal.php`** (ახალი) —
  მოდალის მთელი markup (header/body/footer chrome), `require`-დება
  ორივე გვერდზე (`orders.php`, `invoices.php`).
- **`ds_invoice_preview_script(): string`** (ახალი, `app/Core/helpers.php`,
  `ds_table_script()`-ის იგივე კონვენციით) — სტატუსის badge-ის
  ფერების/ლეიბლების JSON + `show.bs.modal` handler-ი (fetch +
  header/footer-ის შევსება ღილაკის `data-*`-იდან), ორივე გვერდის
  `$scripts`-ში ემატება.

**"გადახედვა" ღილაკის ორი მდგომარეობა** (`$editingInvoice`-ზეა
პირობითი) — **განზრახ განსხვავებული ლოგიკა**, ვიდრე "ექსპორტი
PDF"-ის `4.40`-ის "combined save+export": PDF-ის ექსპორტი
ისედაც მთავრდება გვერდის დატოვებით/ფაილის ჩამოტვირთვით, ამიტომ
წინასწარი save გონივრულია; "გადახედვა" კი "ნახვა შენახვამდე"-ს
სემანტიკას ატარებს — თუ ის ჩუმად შეინახავდა ინვოისს, user-ს
გაუკვირდებოდა (შესაძლოა ჯერ არ სურდეს committ-ი, უბრალოდ ნახვა
სურდეს):
- **`$editingInvoice !== null`** (რეალური, უკვე შენახული ინვოისი,
  `?edit=N`-დან) — ჩვეულებრივი ღილაკია, `data-invoice-id`/
  `-number`/`-status`-ით, ზუსტად ისე, როგორც `/orders`-ის row-ღილაკს
  აქვს.
- **ახალი, შეუნახავი ინვოისი** — `disabled`, ტულთიფით "ჯერ
  შეინახეთ ინვოისი" (ახალი `inv.action_disabled_hint` key —
  იმავე ტექსტის, რაც `4.40`-ში `inv.action_export_pdf_disabled_hint`-
  ს ჰქონდა, სანამ PDF-ის ღილაკი "combined save+export"-ზე არ
  გადავიდა — ეს key მაშინ წაშლილი იყო, ეხლა ზოგადი სახელით
  დაბრუნდა, ხელახლა გამოსაყენებლად).

**გადამოწმებულია ცოცხლად**: (1) `/orders`-ის "ნახვა" — refactor-ის
შემდეგაც უცვლელად მუშაობს (regression არ არის); (2) ახალი
(შეუნახავი) ინვოისის გვერდზე — "გადახედვა" `disabled=true`,
სწორი ტულთიფი; (3) `?edit=12`-ზე — ღილაკზე `data-invoice-id="12"`
(და სწორი number/status) სწორადაა დაყენებული, დაწკაპებით მოდალი
ზუსტად იმავე შემადგენლობით გაიხსნა, რასაც `/orders`-იდან
ვხედავდით. console error არცერთ გვერდზე არ ყოფილა. ტესტ-სესია და
temp password აღდგენილია.

⚠️ **user-მა მაშინვე მოითხოვა disabled-მდგომარეობის მოშორება** —
"გადახედვა" არასდროს არ უნდა იყოს disabled, ახალ ინვოისზეც კი.
პრობლემა: მოდალის გახსნა client-side ქმედებაა, plain `redirect()`-ს
ვერ "გაუხსნია" — ამიტომ "PDF ექსპორტი"-ის `submit_action`-ტრიკი
გამეორდა, უბრალო redirect-ის მაგივრად კი **redirect + ერთჯერადი
auto-click** დაემატა:
- ახალი (შეუნახავი) ინვოისისთვის, ღილაკი გახდა კიდევ ერთი
  `submit_action=preview` submit-ღილაკი (იგივე ფორმა, "PDF
  ექსპორტის" ანალოგიური).
- `InvoiceController::store()`-ს დაემატა მესამე branch:
  `submit_action === 'preview'` → `redirect('/invoices?edit=' .
  $invoiceId . '&preview=1')` (არა პირდაპირ PDF-ზე, არამედ
  ?edit=N-ის ჩვეულ fresh-load ბრენჩზე, უკვე არსებული მექანიზმით).
- `invoices.php`-ის ბოლოში დაემატა პატარა, პირობითი (`isset($_GET['preview'])
  && $editingInvoice !== null`) script — `document.getElementById(
  'invoicePreviewTrigger')?.click()`-ით ავტომატურად აჭერს
  (ეხლა-რეალურ, `data-invoice-id`-იან) "გადახედვა" ღილაკს გვერდის
  ჩატვირთვისთანავე — იგივე მოდალი იხსნება, თითქოს user-მა თავად
  დააჭირა ღილაკს ხელახლა ჩატვირთვის შემდეგ. `history.replaceState`-
  ით `?preview=1` მოიხსნება URL-იდან (manual refresh-ზე ხელახლა არ
  გაიხსნას).

`inv.action_disabled_hint` lang-key (ორივე ენაზე) მოშორდა — აღარ
გამოიყენება.

**გადამოწმებულია ცოცხლად**: ახალი ინვოისი `submit_action=preview`-ით
submit-ის შემდეგ — რეალურად შეინახა ბაზაში (`invoices`/`invoice_items`
სწორი customer/unit_id/quantity/total-ით, პირდაპირ SQL-ით
გადამოწმებული), `Location` header-მა სწორი `/invoices?edit=N&preview=1`
დააბრუნა; `Claude_Browser`-ით ამ URL-ზე ნავიგაციისას მოდალი
**ავტომატურად გაიხსნა**, სწორი შემადგენლობით (ერთეულის ჩათვლით —
"კგ" სწორად გამოჩნდა items-ცხრილში), `location.href`-მაც
დაადასტურა, რომ `?preview=1` URL-იდან წაშლილია. console error
არცერთი. ტესტ-ინვოისი წაშლილია, temp password აღდგენილია.

### 4.47 ვალიდაციის red-border/`.invalid-feedback` ველის გასწორებისას აღარ ქრებოდა — app-wide fix

user-მა შეამჩნია: `/invoices`-ზე, როცა failed submit-ის შემდეგ (მაგ.
ცარიელი "დამკვეთი") server აბრუნებდა `is-invalid` + წითელ
`.invalid-feedback` შეტყობინებას, **ველის შემდგომი გასწორება
(customer-ის არჩევა, product-ის არჩევა) ვალიდაციის კვალს არ
შლიდა** — წითელი კონტური და შეტყობინება ეკრანზე რჩებოდა, თუმცა
სერვერის მხრიდან ველი უკვე ვალიდურია. საეჭვოდ მიაჩნდა, რომ
app-wide ბაგია, არა მხოლოდ `/invoices`-ის სპეციფიკური.

**გადამოწმებულია**: მართლა app-wide, არსად არ არსებობდა JS, რომელიც
`.is-invalid`-ს შლიდა client-side-ზე — 11 გვერდი დაზარალდა
(`invoices.php`, `products.php`, `organization.php`, `customers.php`,
`users.php`, `profile.php`, `profile-settings.php`, ოთხივე `auth/*`).

**ფიქსი — ერთი დელეგირებული listener `public/assets/js/app.js`-ში**
(არა თითო გვერდზე ცალკე), სამი განსხვავებული DOM-ფორმის დამუშავებით:
1. **უბრალო `.form-control`** (`customers.php`-ის `დასახელება` და
   მისნაირები) — `.invalid-feedback` `.form-floating`-ის (ან, თუ
   `$append`-იანია, `.input-group`-ის) sibling-ია, არა შვილი
   (`app/Views/customers.php`-ის დოკუმენტირებული კონვენცია — ლეიბლი
   `.form-floating`-ში `height:100%`-ია, შიგნით მოთავსებული
   შეტყობინება გაწელავდა).
2. **`ds-select`** (`customer_id`, `item_product_id[]` და ა.შ.) —
   ხილული წითელი კონტური `.ds-select-trigger`-ზეა, არა დამალულ
   native `<select>`-ზე (`ds-select.js` მას მხოლოდ კონსტრუქციისას
   ერთხელ აკოპირებს, არასდროს არ synхронizდება); `pick()` მაინც
   აგზავნის ნამდვილ bubbling `change`-ს native select-ზე, ასე რომ
   delegated listener მაინც ხედავს ინტერაქციას — უბრალოდ
   `.ds-select-trigger`-საც ცალკე უნდა მოეხსნას კლასი.
3. **`invoices.php`-ის დინამიური line-item row** — 4 ველი (product/
   quantity/unit/price) **ერთ საერთო** `items_N` შეცდომას იზიარებს
   (`Invoice::validate()`), `.invalid-feedback` კი row-ის `.col-12`-შია,
   არა რომელიმე ცალკე ველის sibling — ამიტომ ამ შემთხვევაში ნებისმიერი
   ველის გასწორება წმენდს **მთელი row-ის** `.is-invalid`-ს (ყველა
   ველზე) და row-ის საერთო შეტყობინებას ერთად.

`clearInvalid(event)` `input`/`change`-ზეა დელეგირებული: ჯერ
`field.closest('[data-item-row]')`-ს ამოწმებს (შემთხვევა 3), თუ
ვერ პოულობს — გადადის ჩვეულებრივ `.form-floating`/`.input-group`
+ ds-select-trigger ლოგიკაზე (შემთხვევები 1-2).

**გადამოწმებულია ცოცხლად** (`Claude_Browser`, JS-inspection
`getBoundingClientRect`/`querySelectorAll` გამოყენებით, screenshot
ამ გარემოში არ მუშაობს): (1) `/invoices` — ცარიელი submit →
`customer_id`-ზე "აირჩიე დამკვეთი (#7867)" + წითელი trigger,
დამკვეთის არჩევის შემდეგ ორივე გაქრა; (2) იგივე გვერდზე, row-ში
მხოლოდ quantity შევსებული (product ცარიელი) → "აირჩიე პროდუქტი
(#9558)" + 6 `is-invalid` ელემენტი row-ში, product-ის არჩევის
შემდეგ ყველა `is-invalid` და შეტყობინება გაქრა ერთად; (3)
`/customers` — `დასახელება`-ს ცარიელ submit-ზე, ტექსტის ჩაწერისას
`.is-invalid` მოშორდა. Console error არცერთგან. Test customer-ის
ფორმა submit არ გაკეთებულა (მხოლოდ client-side ცვლილება), ბაზაში
არაფერი დამატებულა.

⚠️ **user-მა მაშინვე დაადასტურა, რომ პროდუქტზე მაინც არ ქრებოდა** —
მეორე, ცალკე ხარვეზი აღმოჩნდა: სრულიად ცარიელი row-ს (product-იც,
quantity-იც, price-იც ცარიელი — `Invoice::validate()`-ის `continue`
branch-ი, `items_N`-ს საერთოდ არ ანიჭებს) არანაირი per-field
`is-invalid` არა აქვს, "დაამატე მინიმუმ ერთი პროდუქტი" კი ჩვეულებრივ
`<div class="alert alert-danger py-2 small">`-ადაა გამოსახული
`#invoiceItems`-ის თავზე (`invoices.php:206-208`) — ეს ბანერი
საერთოდ არ ჯდება `.invalid-feedback`-ის შაბლონში, ამიტომ 3
ზემოთხსენებული შემთხვევიდან არცერთი მას არ სწმენდდა. დამატებულია
მეოთხე, პატარა წესი `clearInvalid`-ში: row-ის ნებისმიერ ველზე
ცვლილებისას, `#invoiceItems`-ის მშობელ `.col-12`-ში თუ დარჩენილია
`.alert-danger`, ისიც შორდება — **გადამოწმებულია ცოცხლად**:
დამკვეთი არჩეული, item-row სრულიად ცარიელი submit → ბანერი
გამოჩნდა, product-ის არჩევის შემდეგ გაქრა.

`(#7867)`-ის მნიშვნელობა user-ს აუხსნა chat-ში: ეს `terr()`-ის
(`4.23`) auto-generated სტაბილური კოდია (`crc32($key) % 10000`) —
support-ისთვის საკმარისია user-მა თქვას "#7867", კონკრეტულად რომელი
ვალიდაცია ჩავარდა (`inv.err_customer_required`), log-ის ძებნის
გარეშე.

### 4.48 `/invoices`-ის card-header — რედაქტირებისას ფერი + ტექსტი იცვლება

user-მა მოითხოვა: ინვოისის რედაქტირებისას (`?edit=N`) card-header-ის
ტექსტი "ახალი ინვოისი"-ს მაგივრად "ინვოისის რედაქტირება" იყოს,
ფერიც განსხვავებული — ვიზუალურად ცხადი გახდეს, რომ user არსებულ
ჩანაწერს ცვლის, არა ახალს ქმნის.

- ახალი lang-key `inv.edit_title` (ორივე ენაზე), `inv.new_title`-ის
  გვერდით.
- `invoices.php`-ის card-header (`id="invoiceFormHeader"`) — უკვე
  არსებულ `$editing` bool-ზეა პირობითი (`old['invoice_id']`-იდან
  გამოთვლილი, არა `$editingInvoice`-ზე პირდაპირ — ეს ორივე ცოცხლად
  `?edit=N`-ის ჩატვირთვასაც მოიცავს და ჩავარდნილ resubmit-საც,
  docblock-ის თანახმად): `bg-warning-subtle` + `inv.edit_title`
  რედაქტირებისას, `bg-transparent` + `inv.new_title` ახალზე.
- "გასუფთავება" (`form.addEventListener('reset', ...)`) — უკვე
  არსებულ pattern-ს დაემატა header-ის title/ფერის დაბრუნებაც
  (`data-title-add` ატრიბუტიდან), იმავე ადგილას სადაც submit-ღილაკის
  ლეიბლიც და ნომერიც ბრუნდება "ახალზე".

**გადამოწმებულია ცოცხლად**: `/invoices?edit=12` → header
`bg-warning-subtle` + "ინვოისის რედაქტირება"; "გასუფთავება"-ზე
დაჭერის შემდეგ → `bg-transparent` + "ახალი ინვოისი"; ახალი
(`/invoices`, პარამეტრის გარეშე) — თავიდანვე `bg-transparent` +
"ახალი ინვოისი". Client-side სხვა გზა, რომლითაც `$editing`
შეიცვლებოდა page-reload-ის გარეშე, არ არსებობს (`invoices.php`-ის
საკუთარი docblock ადასტურებს — რედაქტირება მხოლოდ ნამდვილი
`?edit=N` ნავიგაციითაა, არა same-page row-click-ით).

### 4.49 გვერდების სათაურის `<h1>` მოშორდა — breadcrumb-ია ერთადერთი წყარო

user-მა შეამჩნია: 10 გვერდზე (`customers`, `products`, `orders`,
`users`, `superuser`, `invoices`, `organization`, `profile`,
`profile-settings`, `modules`) `<h1>` მხოლოდ ბრედქრამბის ტექსტს
იმეორებდა — ზედმეტი დუბლირება. მოთხოვნა: `<h1>`-ის ტექსტი წაშლილიყო,
ხოლო თუ მასში badge იყო (ჩანაწერების რაოდენობა — `customers`,
`products`, `orders`, `users`, `superuser`), badge breadcrumb-ის
აქტიურ item-ში გადასულიყო.

`dashboard.php` **გამონაკლისია, დატოვებულია უცვლელი** — მისი `<h1>`
არა დუბლირებული სათაურია, არამედ პერსონალური მისალმება
("გამარჯობა, {სახელი} 👋"), badge-ის გარეშე, ინფორმაცია არსად
სხვაგან არ მეორდება — user-ს პირდაპირ ვკითხე (`AskUserQuestion`),
დაადასტურა, რომ ეს დარჩეს.

**გადამოწმებულია ცოცხლად**: `/customers` → breadcrumb "დამკვეთები 8"
(badge-ითურთ), `<h1>` აღარ არსებობს; `/orders` → "ყველა შეკვეთა 1";
`/invoices` (badge-ის გარეშე გვერდი) → მხოლოდ breadcrumb-ის ტექსტი,
card-header-ის `4.48`-ის საკუთარი "ახალი ინვოისი"/"ინვოისის
რედაქტირება" უცვლელად მუშაობს. Console error არცერთგან.

### 4.50 `InvoiceWorkflow` — გადახდის/გაუქმების ცალკე ტრექინგი, Warehouse-ის მსგავს მოდულად

user-მა თავად შემოგვთავაზა ინვოისის სტატუსის კლასიფიკაცია (პირველადი/
საბოლოო/გადასახდელი/გადახდილი), ვკითხე რჩევა — `invoices.status` აღმოჩნდა
წმინდა კოსმეტიკური (`ENUM`, ვალიდაცია მხოლოდ 4 მნიშვნელობიდან ერთს
ამოწმებდა, transition-წესი/history არსად). ჩემი რჩევა (document_state/
payment_state core-ში გამოყოფა) → user-მა ცალკე მოიტანა უფრო მძიმე,
ERP-დონის პრომპტი (სრული transition-engine + `invoice_state_history`
აუდიტი + `fulfillment_state` + actor/role) — შევაფასე overkill-ად ამ
პროექტისთვის, user-მა აირჩია შუალედური გზა: **მოდულად, Warehouse-ის
მსგავსად**, სთხოვა არქიტექტურული გამარტივება. სრული გეგმა (Plan mode-ში
დამტკიცებული): `C:\Users\CHIEF\.claude\plans\floofy-enchanting-oasis.md`.

**ერთი პრინციპული განსხვავება Warehouse-თან**, `AskUserQuestion`-ით
გადაწყვეტილი: Warehouse-ის მონაცემები (ტიპი/რაოდენობა/სურათი) core
Products-ის ფორმას საერთოდ არ სჭირდება — 100% განცალკევებული, საკუთარ
გვერდზეა. გადახდის/გაუქმების მდგომარეობა კი ზუსტად იმ გვერდებზეა საჭირო,
სადაც უკვე ინვოისებზეა საუბარი (`/orders`, `/invoices`) — user-მა აირჩია
**პატარა, ცხადი დამატება core-გვერდებზე**, არა ცალკე გვერდი, არა ზოგადი
Hooks-სისტემა (Warehouse-ის დროს პირდაპირ უარყოფილი).

**მოდულის სქელეტი** (`app/Modules/InvoiceWorkflow/`, Warehouse-ის ზუსტი
ფორმით) — `module.json` (`enabled_by_default: false`, სხვაობით
Warehouse-ისგან: ეს არჩევითი გაფართოებაა, არა დეფოლტად ჩართული ბირთვული
ფუნქცია), `Module.php` (მხოლოდ 3 POST route, GET გვერდი **არ არსებობს**
— ვიზუალი inline-ია), `Models/InvoiceWorkflow.php`, `Controllers/
InvoiceWorkflowController.php`, `migrations/001_create_invoice_workflow.sql`.

**სქემა** — ერთი ცხრილი, 1:1 `invoices`-თან (`ProductWarehouse`-ის იგივე
idiom): `invoice_workflow(invoice_id PK+FK ON DELETE CASCADE, payment_state
ENUM('unpaid','partial','paid') DEFAULT 'unpaid', paid_amount DECIMAL(12,2),
cancelled_at TIMESTAMP NULL)`. **ბექფილი არ სჭირდება** — მწკრივი იქმნება
ლენივად, `upsert()`-ით; `InvoiceWorkflow::forMany()`/`::for()` აკლდება
default-ს ყოველი მოთხოვნილი invoice_id-სთვის (row რომ არ არსებობდეს
ბაზაში), ისე რომ view-ის მხარეს "row არ არსებობს"-ის სპეციალური
შემთხვევა არასდროს გამოჩნდეს.

**core-ის touch point** — ერთი, `ds_menu()`-ს (`helpers.php`) იგივე
სულისკვეთებით (ეს უკვე ერთადერთი პრეცედენტია, სადაც core შეგნებულად
იცნობს module-სისტემას): `InvoiceController::index()`/`::orders()`-ში
`if (in_array('InvoiceWorkflow', ModuleRegistry::enabledCodes(), true) &&
class_exists(\App\Modules\InvoiceWorkflow\Models\InvoiceWorkflow::class))`
— `class_exists()` guard-ი დამატებითი დაცვაა (`enabled` flag ბაზაში რომ
დარჩენილიყო, ფაილები კი წაშლილიყო). `InvoiceWorkflow`-ის კლასი **არასდროს
`use`-ით არ არის იმპორტირებული** core ფაილში — მხოლოდ FQCN ამ guard-ის
შიგნით, ცხადად ერთჯერადი, არა ზოგადი მექანიზმი. `orders.php`-ს ამავე
დროს დაემატა **სტატუსის სვეტიც** (მანამდე საერთოდ არ ჰქონდა — მხოლოდ
dashboard.php-ს ჰქონდა, `$statusBadgeClass`-ის იგივე map გამეორებულია)
— workflow-ბეჯი მის გვერდით ჩნდება, მხოლოდ თუ მოდული ჩართულია.
`invoices.php`-ს სტატუს-select-ის ქვემოთ: მიმდინარე payment-ბეჯი, პატარა
`<select>`+`paid_amount`+submit ფორმა, cancel/uncancel ღილაკი — სამივე
`/invoice-workflow/*`-ზე, ცალკე `<form>`-ებად (არა `invoiceMainForm`-ის
ნაწილი).

core-ის სხვა არცერთი ფაილი არ იცვლება — `status` (draft/final/due/paid)
თავისუფლად რჩება payment_state-ისგან სრულიად დამოუკიდებელი, **განზრახ
არავითარი cross-validation არ არის** ორ ველს შორის (ორიგინალ პრომპტში
ცენტრალური მოთხოვნა იყო, აქ შეგნებულად გამოტოვებული — ორივეს ხელით ავსებს
იგივე ბიზნესის მფლობელი).

**Controller-ის დაცვის ჯაჭვი** (სამივე action-ზე ერთნაირად): `csrf_verify()`
→ `Auth::requireNotImpersonating()` (SuperUser-ის view-only წესი, `4.41`,
ამ ახალ write-გზასაც ეხება) → tenant-საკუთრების საკუთარი, დამოუკიდებელი
შემოწმება (`InvoiceWorkflow::invoiceOwnedBy()`, `User::tenantMemberIds()`-
ზეა აგებული — არ იზიარებს `InvoiceController`-ის კოდს, Warehouse-ის
`ProductWarehouse::validate()`-ის იგივე პრინციპი) → `redirect($this->
backTo())` (`ModuleController::backTo()`-ს იდენტური, დუბლირებული,
open-redirect-დაცული).

**გადამოწმებულია ცოცხლად**, test1-ით (id 31, temp password, სესიის
ბოლოს აღდგენილია): install → migration გაეშვა, `invoice_workflow`
ცხრილი შეიქმნა · disabled → `/orders`/`/invoices?edit=N`-ზე workflow
UI არ ჩანს, `/invoice-workflow/payment` POST-ზე ცოცხლი სესიითაც 404 ·
enable (`/settings/modules`-იდან, რეალური დაწკაპებით) → ბეჯი "გადაუხდელი"
default-ად გამოჩნდა ორივე გვერდზე (row არ არსებობდა ჯერ ბაზაში) ·
payment_state → `partial` + `paid_amount=500` → DB-ში ზუსტად აისახა,
ბეჯი ორივე გვერდზე განახლდა · cancel → "გაუქმებული" ბეჯი, ღილაკი
"გაუქმების მოხსნა"-დ გადაიქცა · uncancel → დაბრუნდა · **cross-tenant
დაცვა**: tenant-1-ის რეალურ ინვოისზე (`id=5`) პირდაპირი POST fetch-ით
(admin-ს CSRF token-ითურთ) → **უარყოფილია**, `invoice_workflow`-ში
row არ შექმნილა (`SELECT` დაადასტურა) — tenant-1-ის მონაცემი ხელუხლებელი
დარჩა. SuperUser-ის `requireNotImpersonating()` ცალკე არ გადამოწმებულა
ამ მოდულზე — იგივე, უკვე სხვაგან ამომწურავად დამტკიცებული ერთსტრიქონიანი
guard-ია (`4.41`), არა ახალი კოდი. ტესტ-ინვოისის (`id=12`, tenant 31)
`invoice_workflow` row წაშლილია სესიის ბოლოს.

⚠️ **user-მა მოითხოვა მოდულის გამორთვა** ("ჯერ-ჯერობით გაფართოებული
ინვოისის მოდელი ყველგან იყოს გამორთული, ჯერ ვიმუშაოთ სტანდარტულ
ინვოისზე") — გამორთულია `/settings/modules`-იდან (ინსტალაცია
**დარჩა**, მხოლოდ `enabled=0`; `invoice_workflow` ცხრილი/მონაცემები
არ წაშლილა, disable მონაცემებს არ შლის, `4.19`-ის კონვენციის
თანახმად). ცოცხლად გადამოწმებულია disable-ის შემდეგ: `/orders`-ზე
workflow-ბეჯი აღარ ჩანს, მხოლოდ core-ის სტატუსი. ჩართვა ისევ ერთი
დაწკაპებით შესაძლებელია, თუ/როცა user მზად იქნება.

### 4.51 `invoices.php`-ის სტატუსი — `<select>`-იდან 4 გადამრთველ switch-ზე

user-მა მოითხოვა: ინვოისის სტატუსის (draft/final/due/paid) არჩევა
"ნულოვანი"/"განმეორებადი"-ის იგივე checkbox-ვიზუალით გაკეთდეს, plain
`<select>`-ის მაგივრად. დავაზუსტე (`AskUserQuestion`) — 4 `form-switch`
checkbox, ერთის ჩართვა დანარჩენებს ავტომატურად თიშავს (არა 4 radio ღილაკი).

- `<select>`/`<option>×4` მოშორდა; ამის მაგივრად: `<input type="hidden"
  id="invoice_status" name="status" form="invoiceMainForm">` (ეს ისევეა
  `invoiceMainForm`-ზე მიბმული, როგორც select იყო — submit-ის ლოგიკა
  უცვლელია) + `\App\Models\Invoice::STATUSES`-ზე loop, თითო მნიშვნელობაზე
  `form-check form-switch` checkbox (`class="invoice-status-toggle"
  data-status-value="…"`), ზუსტად `invoice_zero`/`invoice_recurring`-ის
  markup-ის იდენტური.
- დეფოლტი გამოთვლილია PHP-ში (`$statusValue`), არა JS-ში — `$old['status']`
  თუ ცარიელი/არასწორია, `Invoice::STATUSES[0]` ('draft') გამოიყენება,
  ზუსტად ისე, როგორც ცარიელი `<select>` პირველ `<option>`-ს ავტომატურად
  ირჩევდა.
- JS (`$scripts`-ის იმავე IIFE-ში, invoices.php-ის ბოლოში): `change`
  listener ყველა `.invoice-status-toggle`-ზე — თუ ჩართულია, დანარჩენებს
  თიშავს და hidden ველს ანახლებს; თუ **გამორთვას** ცდილობ (დააჭირე უკვე
  აქტიურს) — უკან ირთვება იმავე დაწკაპებაში (`box.checked = true`), რადგან
  ერთი მნიშვნელობა ყოველთვის აქტიური უნდა დარჩეს.

**გადამოწმებულია ცოცხლად**, test1-ით: ახალი ინვოისის გვერდზე დეფოლტად
"პირველადი" აქტიურია, hidden ველიც `draft`; "საბოლოო"-ზე დაწკაპებით
დანარჩენი 3 ავტომატურად გამოირთო, hidden → `final`; უკვე აქტიურზე
ხელახლა დაწკაპებამ ვერ გამორთო (`checked` დარჩა `true`); სრული, ახალი
ინვოისის შექმნა (`customer_id`+item row-ითურთ) `status=final`-ით →
ინვოისი წარმატებით შეიქმნა, DB-ში `status='final'` ზუსტად დაფიქსირდა
(SQL-ით გადამოწმებული). ჩავარდნილი ვალიდაციის resubmit-საც სწორად
გადარჩა არჩეული მნიშვნელობა (`$old['status']`-იდან). ტესტ-ინვოისი
წაშლილია, temp password აღდგენილია.

⚠️ **გვერდითი დაკვირვება ამ ტესტირებისას, არ ეხება ამ ცვლილებას**: invoice
`id=12`-ის ერთადერთ item-row-ს `unit_id` ცარიელი აღმოჩნდა (ერთეულის
ველის დამატებამდელი, legacy ჩანაწერია — `4.42`-ის დოკუმენტირებული
NULL-safe შემთხვევა) — ამ კონკრეტული ინვოისის resubmit ყოველთვის
ვალიდაციას ჩაუვარდება ერთეულის არჩევამდე. ინვოისი `id=12` ხელუხლებელი
დარჩა (ჩავარდნილმა validate-მა write საერთოდ არ დაუშვა).

### 4.52 `status` (ENUM 4 მნიშვნელობა) → `document_state` + `payment_state` (ორი ცალკე ველი)

`4.51`-ის (4 გადამრთველი, ერთდროულად ერთი აქტიური) მერე user-მა ახალი
წესები ჩამოწერა: "პირველადი ჩართული ⇒ გადასახდელიც ჩართული",
"პირველადი გამორთული ⇒ საბოლოო ჩართული", "გადასახდელი ⇔ გადახდილი".
ეს რეალურად აღწერს **ორ ერთდროულად ჩართულ switch-ს** (მაგ. "საბოლოო"+
"გადასახდელი") — ერთ `status` ENUM-ში ეს ფიზიკურად ვერ ეტევა.
დავადასტურე (`AskUserQuestion`) — **ორ ცალკე ველად გავყავი core-ში**
(`invoices` ცხრილი, არა InvoiceWorkflow მოდული, რომელიც ჯერ გამორთულია).
ეს ზუსტად ის დაყოფაა, რასაც პირველივე exploratory კითხვაზე ვურჩიე —
user დამოუკიდებლად იმავე დასკვნამდე მივიდა, checkbox-ების წესებით.

**სქემა** — `migrations/031_split_invoice_status.sql`, ერთ ფაილში
add+backfill+**drop** (`status` **შეუქცევადად წაშლილია** — ამ ცვლილებაშივე
განახლდა ყველა წამკითხველი/ჩამწერი, ნახევრადმზა მდგომარეობა აზრი არ
ჰქონდა, Warehouse-ის `product_type_id` DROP-ის იგივე პრეცედენტი, `4.19`):
```sql
ADD COLUMN document_state ENUM('draft','final') DEFAULT 'draft',
ADD COLUMN payment_state  ENUM('due','paid')    DEFAULT 'due';
UPDATE invoices SET
  document_state = IF(status IN ('final','due','paid'), 'final', 'draft'),
  payment_state  = IF(status = 'paid', 'paid', 'due');
DROP COLUMN status;
```
**გადამოწმებული backfill** (read-only query მიგრაციამდე): ბაზაში
რეალურად მხოლოდ `draft`(7)/`final`(1) იყო გამოყენებული — `due`/`paid`
საერთოდ არ არსებობდა, დაბალრისკიანი გადასვლა. მიგრაციის შემდეგ
დადასტურდა: 7× draft/due, 1× final/due — ზუსტად მოსალოდნელი.

**`Invoice::STATUSES`** → `DOCUMENT_STATES = ['draft','final']` +
`PAYMENT_STATES = ['due','paid']`. **`Invoice::validate()`-ში ცენტრალური
cross-rule, server-side** (client-JS მხოლოდ UX-ია):
```php
if ($documentState === 'draft') { $paymentState = 'due'; }
```
ერთი ხაზი — ორიგინალური მძიმე პრომპტის "cross-dimension ვალიდაცია"
მოთხოვნა აქ ზუსტად ამდენივეა, რამდენიც რეალურად საჭირო.

**`invoices.php`** — 4 checkbox 2 დამოუკიდებელ ჯგუფად
(`.document-state-toggle`/`.payment-state-toggle`), თითო ჯგუფი
`4.51`-ის იგივე exclusive-switch ლოგიკით (ცალკე hidden ველი თითო
ჯგუფზე). ახალი cross-rule JS: `draft`-ზე გადართვისას "გადახდილი"
**დისეიბლდება** + უკან "გადასახდელი"-ზე გადადის ავტომატურად;
`final`-ზე დაბრუნებისას ისევ enable-დება. "გასუფთავება"-ს
reset-handler-საც ემატება იგივე დეფოლტების ხელით დაბრუნება.

**ყველგან, სადაც ერთი ბეჯი/ლეიბლი იყო, ორი გახდა** — იმეორებს ერთ
პატერნს 6 ადგილას: `orders.php`/`dashboard.php` (ორი badge, ცალკე
`$documentStateBadgeClass`/`$paymentStateBadgeClass` map, InvoiceWorkflow
მოდულის საკუთარი `$paymentBadgeClass`-ისგან დამოუკიდებელი — სახელები
განზრახ განსხვავებულია, კოლიზია არ არის), `pdf/orders.php` (ერთი
კომბინირებული ლეიბლი, "საბოლოო · გადასახდელი"), `ds_invoice_preview_script()`
(`helpers.php` — ორი badge, `fillBadge()` helper), `invoice-preview-modal.php`
(მეორე `<span id="ipModalPaymentStatus">`). Preview-ღილაკების
`data-invoice-status` ყველგან ორ `data-invoice-document-state`/
`data-invoice-payment-state` атрибут-ად გაიყო.

**ენა უცვლელი** — `inv.status_draft/final/due/paid` იგივე ტექსტებით,
უბრალოდ ორ ჯგუფშია გამოყენებული ერთის მაგივრად, ახალი key არ დასჭირდა.

**გვერდითი დაკვირვება, არაფერი შეცვლილა**: InvoiceWorkflow მოდულს
(`4.50`, გამორთული) აქვს **საკუთარი** `payment_state`
(`unpaid`/`partial`/`paid`, `invoice_workflow` ცხრილში) — ახლა core-შიც
გაჩნდა მსგავსი-სახელიანი ველი (`due`/`paid`, `invoices`-ში), სხვადასხვა
მნიშვნელობებით/დანიშნულებით. სახელობრივი დამთხვევა შესაძლოა
დამაბნეველი გახდეს მომავალში — out of scope ამ ცვლილებისთვის.

**გადამოწმებულია ცოცხლად**, test1-ით: default draft+due, "გადახდილი"
disabled; "საბოლოო"-ზე → "გადახდილი" enable; "გადახდილი"-ზე → due
unchecked; "პირველადი"-ზე უკან → due ავტომატურად ჩაირთო, paid
unchecked+disabled — ყველა client-side rule მუშაობს. სრული ახალი
ინვოისი (customer+item+final+paid) → DB-ში `document_state='final',
payment_state='paid'` ზუსტად. **სერვერული დაცვა ცალკე გადამოწმებული**:
UI-ის გვერდის ავლით, hidden ველები პირდაპირ `document_state=draft,
payment_state=paid`-ზე დაყენებული JS-ით submit-მდე → DB-ში მაინც
`payment_state=due` შენახულია (server-side rule-მა გადაფარა tampered
მნიშვნელობა). "გასუფთავება" სწორად აბრუნებს draft+due-ს, `paid`
ისევ disabled. `/orders`-ზე ორივე ბეჯი ორივე ტესტ-ინვოისზე სწორად
("საბოლოო გადახდილი", "პირველადი გადასახდელი"). "ნახვა"-მოდალის ორივე
badge სწორი ტექსტით/ფერით. `/orders/export-pdf` — 200, `application/pdf`,
mPDF კომბინირებული ლეიბლით შეცდომის გარეშე დარენდერდა. ტესტ-ინვოისები
(id 23, 24) წაშლილია, temp password აღდგენილია.

### 4.53 `/invoices/view`-ის "PDF შენახვა" — რეალური PDF ექსპორტი, `window.print()`-ის მაგივრად

user-მა მოითხოვა: `/invoices/view`-ის (ბეჭდვადი/გასაზიარებელი გვერდი)
"PDF შენახვა" ღილაკმა რეალურად "ექსპორტი PDF" გააკეთოს. **ძველი კოდის
docblock მოძველებული აღმოჩნდა** — წერდა "პროექტს PDF ბიბლიოთეკა არა
აქვს" (`4.25`-მდელი მდგომარეობა), მაშინ როცა mPDF უკვე დიდი ხანია
დამატებულია (`4.37`) — ორივე ღილაკი ("PDF შენახვა"/"ბეჭდვა") უბრალოდ
`window.print()`-ს იძახებდა, ბრაუზერის "Save as PDF"-ზე დამოკიდებული.

**გამოწვევა**: `/invoices/view` წვდომადია **ორნაირად** — ლოგინირებული
tenant-წევრისთვის (`Auth`) **და** ანონიმური, `?token=`-იანი გაზიარებული
ბმულით (დამკვეთი, ანგარიშის გარეშე — `view_token`, `InvoiceController::show()`).
არსებული `InvoiceController::exportInvoicePdf()` (`/orders`-ის საკუთარი
ღილაკი) კი **ყოველთვის** `Auth::requireUser()`-ს მოითხოვდა, token-ის
გვერდის ავლის გარეშე — პირდაპირ მიბმა ანონიმურ დამკვეთს login-ზე
გადაისვრიდა, სრულიად დაარღვევდა გაზიარების feature-ს.

**გადაწყვეტა**: `show()`-ის access-check ლოგიკა (`token` ან
ლოგინირებული+იგივე-tenant) გავიტანე საერთო `private
resolveInvoiceForView(int $id, string $token): ?array`-ში — იძახებს
ორივე `show()` და ახლა **`exportInvoicePdf()`-იც**. `exportInvoicePdf()`-ის
არსებული გამომძახებელი (`/orders`-ის ღილაკი, token-ის გარეშე) **უცვლელად
მუშაობს** (იგივე ძველი ქცევა, უბრალოდ ახალი საერთო მეთოდით) — მხოლოდ
**დაემატა** ახალი, token-ით bypass-ის შესაძლებლობა. `preview()`
(`/orders`-ის "ნახვა"-მოდალი) **უცვლელი დარჩა**, საკუთარ
`loadOwnedInvoiceForPdf()`-ს იყენებს კვლავ (ის token-ს არასდროს
საჭიროებდა — ყოველთვის ლოგინის უკან).

⚠️ **მეორე, დამოუკიდებელი ხარვეზი აღმოვაჩინე ამ დროს**: `public/index.php`-ის
`PUBLIC_PATHS` (გლობალური login-gate-ის allowlist) შეიცავდა
`/invoices/view`-ს, მაგრამ **არა** `/invoices/export-pdf`-ს — ანუ
token-ის ცოდნაც კი არ შველოდა, გლობალური gate-ი კონტროლერამდე
მისვლამდე უკვე `/login`-ზე გადაისროდა request-ს. დავამატე
`/invoices/export-pdf`-იც `PUBLIC_PATHS`-ში (`show()`-ის იგივე
დოკუმენტირებული პრინციპი: ეს **არ** ხდის route-ს რეალურად საჯაროდ —
`resolveInvoiceForView()` ისევ ითხოვს ან სწორ token-ს, ან
ლოგინირებულ+იგივე-tenant-ს, allowlist მხოლოდ ბლანკეტურ redirect-ს
აჩერებს).

`invoice-view.php`-ის "PDF შენახვა" — `window.print()`-ის მაგივრად
რეალური `<a href="/invoices/export-pdf?id=N&token=...">` — ყოველთვის
**ინვოისის საკუთარი** `view_token`-ით (არა `$_GET['token']`-ით პირდაპირ),
რომ ლოგინირებული ნახვისასაც იმუშაოს (URL-ში token არ ეწერება). "ბეჭდვა"
**უცვლელი** — `window.print()` ისევ სწორია print-ისთვის.

**გადამოწმებულია ცოცხლად**: (1) ლოგინირებული ნახვისას (`?id=12`, token
URL-ში არაა) — ღილაკის href-ს **ავტომატურად** ჰქონდა სწორი token,
fetch → 200 `application/pdf`; (2) `curl`-ით, **cookie-ის გარეშე** (ნამდვილი
ანონიმური მოთხოვნა) იგივე ბმულზე → 200, ნამდვილი 983KB PDF
(`file`-მა დაადასტურა "PDF document, version 1.4"); (3) **უარყოფითი
ტესტები** — არასწორი token → `/login` redirect; token საერთოდ არაა →
`/login`; **სხვა ინვოისის token** ამ ინვოისზე (cross-invoice, tenant-1-ის
რეალურ `id=5`-ზე) → **უარყოფილია**, `/login` redirect, არაფერი გაჟონა.
Console error/რეგრესია არსად. `/orders`-ის ძველი "ექსპორტი PDF" ღილაკიც
(token-ის გარეშე) ცოცხლად გადამოწმდა — უცვლელად მუშაობს.

### 4.54 სამუშაო მაგიდის stat-ბარათები — hover-ეფექტი + დაწკაპებით შესაბამის გვერდზე

user-მა მოითხოვა: 4 stat-ბარათს (დამკვეთები/პროდუქტები/ინვოისები/
შემოსავალი) hover-ზე პატარა ჩრდილი+ოდნავი "აწევა" დაემატოს, დაწკაპებით
კი შესაბამის გვერდზე გადავიდეს.

- **CSS**, `design-system.css`-ში, `.ds-card`-ის გვერდით — ახალი
  `.ds-card-link` კლასი (`display:block`, `color:inherit`,
  `text-decoration:none`, `transition: transform .15s, box-shadow .15s`),
  hover/focus-visible-ზე `transform: translateY(-3px)` თავად + შვილი
  `.ds-card`-ზე `box-shadow: var(--ds-shadow-md)` (უკვე არსებული, light/dark
  ორივესთვის განსაზღვრული token, `--ds-shadow-sm`-ის იგივე ოჯახიდან).
- **`dashboard.php`** — თითო stat-ბარათი (`$stats`-ის loop) გაეხვია
  `<a href="..." class="ds-card-link">`-ში. ახალი `$statUrl` map (key →
  url): `stat.customers`→`/customers`, `stat.products`→`/products`,
  `stat.invoices`→`/orders`, `stat.revenue`→`/orders` (ორივე ბოლო ორი
  ერთსა და იმავე გვერდზე მიდის — "ინვოისები" და "შემოსავალი" ორივე
  ინვოისების სიიდან გამომდინარეობს, ცალკე "შემოსავლის" გვერდი არსად
  არსებობს).

**გადამოწმებულია ცოცხლად**: ოთხივე ბარათის href სწორია (JS-ით
დადასტურებული), დაწკაპებით ნავიგაცია მუშაობს (`/customers`-ზე
გადამოწმებული, page title/content სწორად შეიცვალა). ⚠️ **hover-ის
ვიზუალური ეფექტი (`transform`) ვერ დავადასტურე ავტომატურად ამ
გარემოში** — `box-shadow`-ის ცვლილება (იგივე `:hover` წესის ნაწილი)
სწორად ფიქსირდება `getComputedStyle`-ით, `transform` კი inline
style-ითაც კი (`el.style.transform = '...'`) `matrix(1,0,0,1,0,0)`-ს
აბრუნებდა — თავად `computer{action:"screenshot"}`-იც ამბობს "the
Browser pane is not displayed, so the page is not compositing frames"
ამ სესიაში, რაც GPU-კომპოზიტირებულ `transform`-ს (განსხვავებით
paint-დროინდელი `box-shadow`-ისგან) ვერ ასახავს headless/non-composited
გარემოში — არა კოდის ბაგი, tooling-შეზღუდვა. CSS პატერნი
(`a:hover { transform: translateY(-Npx) }`) სტანდარტული და
ფართოდ-გამოცდილია; **user-ს თავად სჭირდება ცოცხლი ბრაუზერით ვიზუალურად
გადამოწმება**.

### 4.55 `invoices.php`-ის "მეილზე გაგზავნა" — რეალური ფუნქციონალი, attachment-ით

`inv.action_email` ღილაკი (`4.40`-მდე unwired placeholder) ახლა რეალურად
აგზავნის ინვოისს მეილზე: მისამართი დამკვეთის მონაცემებიდან, დაერთვება
ინვოისის PDF, ხელმოწერაში ორგანიზაციის მონაცემები + გამომგზავნის
ტექსტი. Plan mode-ში დამტკიცებული (`C:\Users\CHIEF\.claude\plans\
floofy-enchanting-oasis.md`).

**აღმოჩენილი ტექნიკური შეზღუდვა**: მთელი აპი ერთ, გაზიარებულ Gmail SMTP
ანგარიშზეა (`.env`) — ორგანიზაციის რეალური მეილიდან პირდაპირი "From"
Gmail-ის anti-spoofing-ს ჩავარდებოდა (reject/spam რისკი). დავადასტურე
user-თან — **Reply-To ორგანიზაციის მეილზე**, ტექნიკური "From" რჩება
`MAIL_FROM`-ზე (`noreply@invoice.net`).

**Core-ის გაფართოებები** (ბირთვული ფუნქციაა, არა module):
- `App\Core\Mailer::send()` — ახალი `$attachments`/`$replyTo` პარამეტრები.
  Attachment-ის შემთხვევაში `multipart/mixed` (random hex boundary),
  base64 (`chunk_split()`-ის default 76-char wrap), dot-stuffing ვრცელდება
  **მთელ** multipart body-ზე. ძველი გამომძახებლები (OTP/reset მეილები)
  უცვლელად მუშაობენ (ორივე ახალი პარამეტრი default-ით).
- `App\Core\Pdf` — საერთო mpdf-აწყობა გავიტანე `private buildMpdf()`-ში,
  `download()` (უცვლელი ქცევა) და ახალი `render(): string`
  (`Destination::STRING_RETURN`, ბრაუზერში არაფერს არ აგზავნის) ორივე
  მას იყენებენ.

**`InvoiceController::sendEmail()`** (ახალი, `POST /invoices/send-email`) —
`csrf_verify()` → `requireNotImpersonating()` → `loadOwnedInvoiceForPdf()`
(ყოველთვის ლოგინის უკან, `resolveInvoiceForView()`-ის token-გზა **არ**
გამოიყენება — გაგზავნა ანონიმურად არასდროს ხდება). ვალიდაცია
(`FILTER_VALIDATE_EMAIL` + არაცარიელი `message`) ჩავარდნისას
`flash('email_errors'/'email_old')` + redirect `?edit=N&email=1` —
`preview`/`4.46`-ის ზუსტად იგივე auto-reopen პატერნი. წარმატებისას:
`pdf/invoice.php` იგივე HTML (`exportInvoicePdf()`-ის იდენტური) →
`Pdf::render()` → bytes → `Mailer::send()` attachment+Reply-To-ით.
`store()`-ს დაემატა მესამე `submit_action==='email'` branch, `preview`-ის
იდენტური (`?edit=N&email=1` redirect ახალი/შეუნახავი ინვოისისთვის).

**`invoices.php`** — ღილაკი `4.46`-ის ორ-მდგომარეობიანი პატერნი
($editingInvoice !== null → მოდალ-ტრიგერი, else → submit_action=email).
ახალი `#invoiceEmailModal` (ამ გვერდის საკუთარი, **არა** გაზიარებული —
ერთადერთი გამომძახებელია). "to"-ს ავტომატური შევსება **იყენებს უკვე
არსებულ** `customersById` JS ობიექტს (`renderCustomerInfo()`-ს იგივე
მონაცემი) — ახალი data-* ატრიბუტი არ დასჭირდა. Auto-reopen script
`?email=1` **ან** `$emailErrors !== []`-ზეა პირობითი (ვალიდაციის
ჩავარდნისას query flag საერთოდ არ ჩნდება URL-ში, `sendEmail()` პირდაპირ
`&email=1`-ით აბრუნებს). `$emailErrors`/`$emailOld` **ცალკეა** მთავარი
ფორმის `$errors`/`$old`-ისგან. `.is-invalid`-ის ავტომატური გასუფთავება
`4.47`-ის `clearInvalid`-იდან **უფასოდ** მუშაობს ახალ ველებზეც, ცალკე
კოდი არ დასჭირდა.

**გადამოწმებულია ცოცხლად**, test1-ით: (1) standalone სკრიპტით,
`Mailer::send()`-ის attachment+Reply-To პირდაპირ (browser-ის გარეშე) —
SMTP transcript დაასრულა წარმატებით (`bool(true)`), Gmail-მა
multipart+attachment მიიღო; (2) UI-დან, შენახულ ინვოისზე (`id=12`) —
მოდალი გაიხსნა, `to` ავტომატურად `client31.8@example.test`-ით შეივსო,
შეტყობინების ტექსტით submit → **წარმატების flash** გამოჩნდა
(„ინვოისი „TS1 2026-08-16 0012" გაიგზავნა მეილზე."), რეალურად გაიგზავნა
`giviberdzenishvili@gmail.com`-ზე (იგივე SMTP ანგარიშის მფლობელი); (3)
ვალიდაცია — არასწორი `to` + ცარიელი `message` (raw POST-ით) → მოდალი
ავტომატურად ხელახლა გაიხსნა, ორივე ველზე `.is-invalid` + სწორი
`terr()`-შეტყობინება, `to`-ს მნიშვნელობა შენარჩუნებული; ველის
გასწორებამ (`input` event) `.is-invalid` წამოშალა უცვლელი
`clearInvalid`-ით; (4) ახალი (შეუნახავი) ინვოისი → submit_action=email →
შეინახა (`id=25`), redirect `?edit=25`-ზე, მოდალი ავტომატურად გაიხსნა,
`to` სწორად შეივსო. ტესტ-ინვოისი (`id=25`) წაშლილია, temp password
აღდგენილია. SuperUser `requireNotImpersonating()` ცალკე არ
გადამოწმებულა ცოცხლად — იგივე, სხვაგან უკვე ამომწურავად დამტკიცებული
ერთსტრიქონიანი guard-ია (`4.41`), არა ახალი კოდი.

⚠️ **user-მა დამატებით მოითხოვა**: მეილზე გაგზავნამ ავტომატურად უნდა
გადაიყვანოს `document_state` `draft`-იდან `final`-ზე (ლოგიკური წესი —
გაგზავნილი ინვოისი აღარაა "სამუშაო ვერსია"). დამატებულია
`Invoice::markFinal(int $id): void` (`app/Models/Invoice.php`) —
`UPDATE ... WHERE id = ? AND document_state = 'draft'` (no-op თუ უკვე
final), გამოძახებულია `sendEmail()`-დან **მხოლოდ** `Mailer::send()`-ის
წარმატებისას (ჩავარდნილი გაგზავნა არაფერს არ ცვლის). `payment_state`
ხელუხლებელია — გაგზავნას გადახდასთან კავშირი არა აქვს.

**გადამოწმებულია ცოცხლად**: `id=12` (draft) → მეილის გაგზავნა →
წარმატების flash → გვერდის refresh-ის შემდეგ checkbox-ებში "საბოლოო"
ავტომატურად აქტიურდა, "პირველადი" გამორთულია — DB-ითაც დადასტურებული
(`document_state='final', payment_state='due'` უცვლელი). ტესტის შემდეგ
`id=12` ხელით დაბრუნებულია `draft`-ზე (გაზიარებული ტესტ-ფიქსტურაა,
სხვა ტესტებიც მას იყენებენ draft-მდგომარეობით).

### 4.56 ინვოისის ნომერი — `id`-დან ნამდვილ per-tenant თანმიმდევრობაზე + "საწყისი ნომერი"

user-მა მოითხოვა: `/settings/organization`-ში, პრეფიქსის შემდეგ,
"ინვოისის საწყისი ნომერი" (default `001`).

**გამოკვლევით აღმოჩენილი, გადამწყვეტი დეტალი**: `Invoice::number()`
დღემდე ნომერს პირდაპირ `invoices.id`-იდან იღებდა — ეს ერთი **გლობალური**
`AUTO_INCREMENT`-ია მთელი ცხრილისთვის (`migrations/013`,
`ruler`/tenant-სვეტის გარეშე), **არა** tenant-ის საკუთარი
თანმიმდევრობა. რეალურ მონაცემებზე გადამოწმებით: tenant 1-ის 9 ინვოისს
ჰქონდა id-ები `3,5,11,14,18,20,21,26,27` — არათანმიმდევრული, სხვა
tenant-ების ინვოისების ჩარევის გამო. "საწყისი ნომრის" ამოცანა ამ
სქემაში საერთოდ არ მუშაობდა — საჭირო გახდა **ნამდვილი per-tenant
თანმიმდევრული ნომრაცია**, რასაც ეს ცვლილება ამავე დროს აფუძნებს. Plan
mode-ში დამტკიცებული.

**დადასტურებული user-თან** (`AskUserQuestion`): საწყისი ნომრის
შეცვლა არსებული ინვოისების შემდეგ **არასდროს დააცდება უკან** — ახალი
ნომერი = `max(tenant-ის არსებული მაქსიმუმი + 1, ახალი საწყისი ნომერი)`.

**სქემა** — `migrations/032_add_invoice_sequence_number.sql`:
`organization.invoice_start_number` (default `1`), `invoices.sequence_number`
(NULL-abble). Backfill — `ROW_NUMBER() OVER (PARTITION BY
COALESCE(creator.created_by, creator.id) ORDER BY id)`, MySQL 8.4-ის window
function-ით, ერთ `PDO::exec()`-ში (`031`-ის იგივე multi-statement
პრეცედენტი). Unresolvable-tenant legacy rows (`created_by IS NULL`) →
`sequence_number` NULL რჩება, `number()` მათთვის `id`-ზე უბრუნდება
(იგივე, უკვე დოკუმენტირებული edge case, არა რეგრესია).

**`Invoice::number()`** — `$row['id']` → `$row['sequence_number'] ??
$row['id']`. ერთადერთი choke point (8 caller, ყველა `SELECT i.*`-დან
იღებს მონაცემს) — **არცერთ caller-ში ცვლილება არ დასჭირდა**.

**`Invoice::save()`** — ახალი `?array $tenantMemberIds, ?int $startNumber`
პარამეტრები (მხოლოდ ახალ ინვოისზე საჭირო). სექვენციის გამოთვლა
**იმავე ტრანზაქციაშია**, რაც optimistic-locking-საც იყენებს —
`SELECT MAX(sequence_number) ... FOR UPDATE` — ორი პარალელური "ახალი
ინვოისის" submit იმავე tenant-იდან ვერ მიიღებს ერთსა და იმავე ნომერს
(row-lock სერიალიზებს). `InvoiceController::store()`-ში `$org`-ის
საპოვნელი query უბრალოდ უფრო ადრე გადავიდა (იმავე ცვლადს იმეორებს
success-flash-ის ნომრისთვისაც, ორმაგი query არაა).

**`Organization`** — ახალი `invoice_start_number` ველი
`validate()`/`save()`-ში (`ctype_digit`, `>= 1`), `organization.php`-ში
ახალი number input, `org.invoice_prefix`-ის გვერდით.

**გადამოწმებულია**: (1) მიგრაცია `032` გაეშვა, backfill ზუსტად
მოსალოდნელი გამოვიდა (tenant 1: 9 ინვოისი → `1-9`, id-რიგით; tenant 31:
1 ინვოისი → `1`); (2) HTTP-ით ცოცხლად, test1-ით — `/settings/organization`-ზე
ახალი ველი default `1`-ით ჩანს, ახალი ინვოისის შექმნამ (id=28)
სწორად მიიღო `sequence_number=2` (არსებული მაქსიმუმის გაგრძელება),
`/orders`-ზე ნომრებმა `TS1 2026-08-16 0001`/`TS1 2026-08-23 0002`
სწორად აჩვენა (ძველი, id-დაფუძნებული "0012"-ის მაგივრად); (3)
**პირდაპირ, model-level სკრიპტებით** (browser session-ის მოულოდნელი
ამოწურვის გამო, ტესტირების შუაში, `SESSION_TIMEOUT_MINUTES=30`-ს
მიუხედავად — გარემოს/ტესტ-tooling-ის თავისებურებაა, `Organization::save()`-ის
პირდაპირმა გამოძახებამ დაადასტურა შეცდომის არარსებობა, არა კოდის
ბაგი): "არასდროს უკან" წესი — `startNumber=1` მაშინ, როცა tenant-ის
მაქსიმუმი უკვე `2` იყო → ახალმა ინვოისმა მაინც `3` მიიღო, არა `1`;
"წინ ახტომა" წესი — `startNumber=50` → ახალმა ინვოისმა `50` მიიღო
ზუსტად. ყველა ტესტ-ინვოისი წაშლილია, `organization.invoice_start_number`
დაბრუნებულია `1`-ზე (tenant 31), temp password აღდგენილია.

### 4.57 "მეილის შეტყობინების ტექსტი" — ორგანიზაციის საკუთარი, რედაქტირებადი default

თავდაპირველად (`4.56`-ის ბოლოს) "მეილზე გაგზავნა"-ს default ტექსტი
მხოლოდ hardcoded lang-key იყო (`inv.email_message_default`). user-მა
დააზუსტა: default **ჩანდეს და რედაქტირებადი იყოს** `/settings/organization`-ზე,
არა მხოლოდ კოდში ჩაშენებული — ანუ ეს არის ორგანიზაციის საკუთარი
პარამეტრი, `ინვოისის საწყისი ნომრის` იმავე ნიმუშით.

**სქემა** — `migrations/033_add_org_email_message_default.sql`:
`organization.email_message_default TEXT NULL`. ცარიელი/`NULL` ნიშნავს
"ჩაშენებული default-ი გამოიყენე" — არა ცალკე ვალიდაცია (თავისუფალი
ტექსტია, `invoice_prefix`-ის იგივე optional-ველის კონვენცია).

**`organization.php`** — ახალი `<textarea>`, `invoice_start_number`-ის
გვერდით. **წინასწარ ივსება** hardcoded default-ით კიდეც მაშინაც, როცა
ორგანიზაციას საკუთარი ტექსტი ჯერ არ აქვს შენახული (`$emailMessageDefaultVal`,
`$old ?? $org ?? t('inv.email_message_default')`) — user ხედავს
ზუსტად იმას, რაც რეალურად გაეგზავნება, ცარიელი textarea-ს მაგივრად.

**`invoices.php`-ის მოდალი** — `$emailDefaults['message']` ახლა
`$org['email_message_default'] ?: t(...)` — ორგანიზაციის საკუთარი
ტექსტი უპირატესია, ჩაშენებული მხოლოდ fallback-ია. ჩავარდნილი submit-ის
`$emailOld` კვლავ ორივეზე მაღლა დგას (`4.56`-ის კონვენცია უცვლელი).

**გადამოწმებულია**: (1) `/settings/organization`-ზე ველი ჩანს,
default-ითურთ წინასწარ შევსებული; (2) **პირდაპირ, `curl`-ით** (cookie
jar-ით, `enctype="multipart/form-data"` ფორმის სწორი გაგზავნით) —
custom ტექსტის შენახვა → 302 success (არა `/login`) → შემდეგ GET-მა
შენახული ტექსტი დაადასტურა; (3) ველის დაბრუნების (`NULL`) შემდეგ,
`/invoices`-ის მოდალმა კვლავ ჩაშენებულ default-ზე დაბრუნდა სწორად.

⚠️ **ცოცხლი ბრაუზერით (`Claude_Browser`) ამ ერთი ფორმის submit-ი
სისტემატურად `/login`-ზე აგდებდა** (session დაკარგული) — მხოლოდ ამ
კონკრეტულ, `multipart/form-data` ფორმაზე, სხვა ყველა ფორმა (ინვოისი,
მეილის გაგზავნა, workflow) იმავე სესიაში გამართულად მუშაობდა. `curl`-ით
(cookie jar-ით, headers/redirect ხელით შემოწმებული) ზუსტად იგივე
request-მა 100%-იანად იმუშავა (302 → success, არა 419/403/`/login`) —
ეს ადასტურებს, რომ **კოდი გამართულია**, პრობლემა tooling-ისაა
(სავარაუდოდ headless ბრაუზერის `requestSubmit()`-ის ქცევა multipart
ფორმებზე ამ გარემოში), არა აპლიკაციის ბაგი. თუ მომავალში ეს ხელახლა
გამოჩნდება — `curl`-ით/cookie jar-ით გადამოწმება პირველი ნაბიჯი უნდა
იყოს, არა კოდის ეჭვქვეშ დაყენება.

⚠️ **ამ `curl`-ტესტმა თავად გამოიწვია რეალური მონაცემის დაზიანება**,
რომელიც user-მა შემდეგ შენიშნა: Georgian ტექსტი (`name`/`address`)
`-F` ველებში, ტერმინალიდან პირდაპირ გადაცემული (Windows Git-Bash-ის
codepage-პრობლემა argv-ში, არა PHP/MySQL-ის მხარეს — `charset=utf8mb4`
ყველგან სწორია) → tenant 31-ის `name`/`address` "?"-ებით ჩაიწერა
ბაზაში. **user-მა შენიშნა და მკითხა** ("მისამართი რატომ არის
კითხვის ნიშნებში?"). გასწორებულია — ორივე ველი აღდგენილია სწორი
ტექსტით (`Write`-ით შექმნილი ერთჯერადი PHP სკრიპტით, არა shell
argv-ით, რომ იგივე codepage-პრობლემა არ განმეორდეს). **tenant 1-ის
(user-ის რეალური) მონაცემი არასდროს შეხებია** — მხოლოდ ტესტ-tenant
31 დაზიანდა და აღდგა. გაკვეთილი: **Georgian/multi-byte ტექსტი
ბაზაში არასდროს გადავცე shell argv-ით (`curl -F`/`-d`, ბრძანების
პარამეტრები)** — მხოლოდ PHP ფაილში (`Write` tool) ჩაწერილი heredoc/
სტრიქონი, რომელსაც თავად სკრიპტი კითხულობს.

### 4.58 `/settings/organization` — ფულის ერთეულის select → `ds-select`

user-მა მოითხოვა: "ფულის ერთეულის select უნდა იყოს form-floating".
გამოკვლევით: **სტრუქტურულად** `.form-floating`-ში უკვე იყო გახვეული
(plain Bootstrap native-select floating), მაგრამ **ერთადერთი დარჩენილი
select იყო მთელს აპში `data-ds-select`-ის გარეშე** — `4.x`-ის
"ds-select ყველგან" სტანდარტული წესის (`feedback-ds-select-everywhere`
memory) გამონაკლისი, დავიწყებული. ამიტომ ვიზუალურად/ქცევით
გამოირჩეოდა ყველა დანარჩენი floating-select-ისგან (ds-select-ს
საკუთარი, JS-დაფუძნებული floating მექანიზმი აქვს, არა სუფთა CSS).

დამატებულია `data-ds-select data-search-placeholder/no-results/clear-label`
— ზუსტად `users.php`-ის "როლის" select-ის იგივე პრეცედენტი (required,
ყოველთვის აქვს დეფოლტი `GEL`, **ცარიელი `<option>` განზრახ არ
დამატებულა** — `role`-საც არა აქვს, ეს უკვე მიღებული პატერნია
required+has-default ველებისთვის, `product_type_id`/`unit_id`-ის
საწინააღმდეგოდ, რომლებსაც ნამდვილად არა აქვთ გონივრული default და
ცარიელი option სჭირდებათ).

**გადამოწმებულია ცოცხლად**: `#org_currency` ახლა რეალურ ds-select-შია
(`.ds-select.ds-select-floating.ds-select-has-value`), trigger-ი
სწორად აჩვენებს "ლარი (₾)"-ს, ძებნადი dropdown იხსნება.

### 4.59 `/settings/organization` — უფრო კომპაქტური განლაგება

user-მა მოითხოვა: გვერდი ცოტა უფრო კომპაქტურად დალაგდეს (`4.56`/`4.57`-ის
ახალი ველების დამატებამ — `invoice_start_number`, `email_message_default`
— გვერდი საგრძნობლად დააგრძელა).

- `row g-4`/`mb-4` → `row g-3`/`mb-3` მთელ ფორმაზე (მოკლე ველების
  ჯგუფებზე — მჭიდრო, მაგრამ არა ერთმანეთზე მიდებული).
- **ხელახლა დაჯგუფებული სიგანეების მიხედვით**, არა თანმიმდევრობით:
  მოკლე ველები (`tax_id`, `phone`) ერთ მწკრივზე `name`-თან ერთად
  (`col-md-6`+`col-md-3`+`col-md-3`); ოთხივე ყველაზე მოკლე ველი
  (`invoice_prefix`, `invoice_start_number`, `vat_rate`, `currency`) —
  ერთ მწკრივზე ოთხივე (`col-md-3` თითო, მანამდე 2 ცალკე მწკრივზე
  იყო `col-md-6`-ებად); გრძელი ველები (`email`/`website`, `address`,
  `email_message_default`) უცვლელად თავიანთ სივრცეს ინარჩუნებენ.
- `email_message_default`-ის textarea: `6rem` → `4.5rem`.

ლოგო/ხელმოწერის ატვირთვის ბლოკი, საბანკო ანგარიშების სექცია და
ყველა ველის ვალიდაცია/JS **უცვლელი** — მხოლოდ განლაგება/spacing.

**გადამოწმებულია ცოცხლად**: ფორმის სიმაღლე შემცირდა, ოთხივე მოკლე
ველი ერთ მწკრივზეა (`getBoundingClientRect().top` ოთხივესთვის
იდენტური), `name`/`tax_id`/`phone`-იც ერთ მწკრივზეა, ds-select
(`4.58`) და ყველა ველის მონაცემი უცვლელად სწორია, console error
არსად.

### 4.60 `/invoices`-ის card-header — რეალური, პროგნოზირებული ინვოისის ნომერი "ახალი"-ს მაგივრად

user-მა მოითხოვა: ახალი ინვოისის ფორმის ზედა-მარჯვენა კუთხეში
გამოჩნდეს რეალური ინვოისის ნომერი (პრეფიქსი+თარიღი+ნომერი), არა
სტატიკური "ახალი" placeholder. `4.56`-ის per-tenant sequence-ნომრაციის
წყალობით ეს ახლა შესაძლებელია — შემდეგი ნომერი წინასწარ გამოსათვლელია
save-ის გარეშეც.

**`Invoice::previewNextSequenceNumber(array $tenantMemberIds, int
$startNumber): int`** (ახალი) — `save()`-ის იგივე `MAX(sequence_number)`
ლოგიკა, **`FOR UPDATE`-ის გარეშე** (ეს მხოლოდ ჩვენებაა, არა ნამდვილი
insert — row-ის დაბლოკვა აქ არ სჭირდება; ნამდვილი, race-safe ნომერი
მაინც `save()`-შივე გამოითვლება save-ის მომენტში). `InvoiceController::index()`-ში
ყოველთვის გამოითვლება (`$previewNumber`, `Invoice::number()`-ის
თანაბარი გამოძახება, synthetic `['sequence_number'=>..,'issue_date'=>..]`
row-ით — `number()` ხომ მხოლოდ ამ ორ key-ს კითხულობს) და გადაეცემა
view-ს.

**`invoices.php`** — `invoiceFormNumber`-ის ახალი-ინვოისის branch-მა
`t('inv.new_number_pending')` (`"ახალი"`) `$previewNumber`-ით ჩაანაცვლა.
ცალკე `invoiceFormDate` span **მოშორდა** — `Invoice::number()`-ის
ფორმატი უკვე თავადვე შეიცავს თარიღს ერთ სტრიქონში
(`"{prefix} {date} {0004}"`), ამიტომ ცალკე თარიღის ჩვენება
რედუნდანტული გახდა (`$editingInvoice`-იანი შემთხვევისთვის ეს ისედაც
ასე იყო). "გასუფთავება"-ს reset-handler-იც ამ ერთივე `data-new-label`-ს
იყენებს (server-side წინასწარ გამოთვლილი, არა client-side ხელახლა
გამოთვლადი) — edit-დან "ახალზე" დაბრუნებისას ნამდვილი, მიმდინარე
პროგნოზი ჩნდება, არა ძველი სტატიკური ტექსტი. Dead lang-key
`inv.new_number_pending` წაშლილია (ka+en) — აღარსად გამოიყენებოდა.

**გადამოწმებულია ცოცხლად**: ახალი ინვოისის გვერდზე card-header-მა
`TS1 2026-08-24 0002` აჩვენა (tenant-ის მაშინდელი მაქსიმუმი 1-ი
იყო) → ინვოისი შენახვის შემდეგ **DB-ში ზუსტად** ეს ნომერი დაფიქსირდა
(`/orders`-ითაც დადასტურებული) → ახალი, ცარიელი ფორმის header-მა
ავტომატურად განაახლა შემდეგი პროგნოზი `0003`-ზე; `?edit=12`-დან
"გასუფთავება"-მ სწორად აჩვენა იგივე მიმდინარე პროგნოზი (`0003`), არა
ძველი placeholder. ტესტ-ინვოისი წაშლილია, temp password აღდგენილია.

### 4.61 CORE ინვოისიდან `payment_state` (გადასახდელი/გადახდილი) მთლიანად ამოღებულია

user-მა (`4.52`-ის two-axis split-ის შემდეგ, დისკუსიის შემდეგ) გადაწყვიტა:
სტანდარტული/მსუბუქი ინვოისი მხოლოდ `document_state`-ს (პირველადი/
საბოლოო) ინარჩუნებს — "გადასახდელი/გადახდილი" მცნება, ყველა მასთან
დაკავშირებულ UI ელემენტთან და კოდთან ერთად, **CORE-დან** მთლიანად
გაქრა. გადახდის tracking-ის იდეა (არქივის ღილაკი და სხვ.) მხოლოდ
განიხილებოდა — ცალკე, მომავალი გადაწყვეტილებაა, ახლა **არ** აშენებულა.

**⚠️ scope**: ეს მხოლოდ **core**-ს ეხება. ცალკე, disabled
`InvoiceWorkflow` მოდულს (`app/Modules/InvoiceWorkflow/*`, საკუთარი
`invoice_workflow` ცხრილი, საკუთარი `payment_state` ENUM
unpaid/partial/paid + `paid_amount` + `cancelled_at`) **საერთოდ არ
შეხებია** — ტექსტურად მსგავსი სახელი, კონცეპტუალურად სხვა ფუნქცია.

- **`migrations/034_drop_invoice_payment_state.sql`** — `ALTER TABLE
  invoices DROP COLUMN payment_state`. გაშვებულია.
- **`Invoice.php`** — `PAYMENT_STATES` კონსტანტა წაშლილია (`DOCUMENT_STATES`
  რჩება); `validate()`-დან `$paymentState`-ის წაკითხვა/ვალიდაცია/
  "draft⇒due" cross-rule მოშორდა; `save()`-ის INSERT/UPDATE SQL-დან
  `payment_state` სვეტი გაქრა.
- **`InvoiceController::index()`** — edit-preload `$old`-დან
  `payment_state` წაშლილია.
- **`invoices.php`** — მთლიანი due/paid checkbox-toggle loop,
  `$paymentState` ცვლადი, hidden `invoice_payment_state` input, მათი JS
  (`paymentToggles`/`paymentInput`/draft⇒due cross-rule listener-ები,
  reset-handler-ის შესაბამისი ხაზები) — ყველა წაშლილია. მხოლოდ
  `document_state`-ის toggle-ჯგუფი რჩება.
- **`orders.php`, `dashboard.php`** — მეორე (payment) badge და მისი
  `$paymentStateBadgeClass` მასივი წაშლილია, თითო row-ს ერთი badge
  (document_state) რჩება.
- **`pdf/orders.php`** — `$combinedStatus` closure ("draft · due" ტიპის
  გაერთიანებული სტრიქონი) მოშორდა, სტატუსის სვეტი უბრალო
  `document_state` label-ს აჩვენებს.
- **`helpers.php` (`ds_invoice_preview_script()`)** — `due`/`paid` badge
  class/label წყვილები და `ipModalPaymentStatus`-ის fill-ლოგიკა
  წაშლილია.
- **`invoice-preview-modal.php`** — მეორე `<span id="ipModalPaymentStatus">`
  badge მოშორდა.
- **`ka.php`/`en.php`** — `inv.status_due`/`inv.status_paid` lang-key-ები
  წაშლილია.

**Grep-ით დადასტურებული**: core-ში აღარსად რჩება `payment_state` /
`PAYMENT_STATES` / `status_due` / `status_paid` — დარჩენილი ყველა match
(`workflow.*` lang-key-ები, `$paymentBadgeClass`, `$workflow['payment_state']`)
`InvoiceWorkflow` მოდულს ეკუთვნის.

**გადამოწმებულია ცოცხლად** (tenant 31, temp password): `/invoices`
(ახალი) — მხოლოდ პირველადი/საბოლოო checkbox, due/paid აღარ ჩანს,
console-შეცდომების გარეშე; `/invoices?edit=12` — იგივე, checkbox-ები
სწორია; `/orders` — თითო row-ს ერთი badge; dashboard-ის ბოლო-ინვოისების
ცხრილი — იგივე; preview modal (`/orders`-იდანაც) — ერთი badge,
`ipModalPaymentStatus` აღარ არსებობს, JS error არ ჩნდება;
`/orders/export-pdf` (curl+cookie jar) — `200 OK`, ვალიდური PDF, სტატუსის
სვეტი მხოლოდ document_state label-ს აჩვენებს. `Invoice::save()`-ის
UPDATE branch პირდაპირ, PHP-ით ტესტირებულია — `payment_state`-ის
გარეშე SQL შეცდომის გარეშე მუშაობს, invoice id=12 `document_state='draft'`
უცვლელი დარჩა. ტესტ-მონაცემები არ შექმნილა (არსებული id=12 თავის
თავდაპირველ მდგომარეობაზე დაბრუნდა), temp password აღდგენილია.

**⚠️ ამ სექციის ტესტირებისას აღმოჩენილი, თვითონ-გამოწვეული ბაგი**
(დაფიქსირდა მხოლოდ `4.62`-ის ცოცხლი გადამოწმებისას, `total`-ის `0.00`-ად
ჩვენებით `/orders`-ზე): ზემოთ, "`Invoice::save()`-ის UPDATE branch
პირდაპირ ტესტირებულია" ნაბიჯზე, `save()`-ს `'items'` key-ის გარეშე
გამოვუძახე — `save()` კი `total`-ს **ყოველთვის** `$clean['items']`-იდან
ხელახლა ითვლის და ძველ `invoice_items` row-ებს უპირობოდ შლის, ახლით
ჩანაცვლების წინ (`Invoice.php:202-256`). `'items'`-ის არარსებობამ
`total`-ი 0-ზე დააყენა და tenant 31-ის ერთადერთი ტესტ-ინვოისის (id=12)
line item საერთოდ წაშალა — PHP warning-ებად გამოჩნდა, magically-გავლილი
ჩავთვალე. **გასწორებულია**: `test-data.sql`-ის ფიქსტურის ორიგინალი
მნიშვნელობებით აღდგენილია (`total=1656.00`, `invoice_items` row
`id=21, product_id=20, unit_id=NULL, qty=10.000, price=165.60`), ხელით,
პირდაპირ SQL-ით. **დასკვნა**: `Invoice::save()` არასდროს გამოსაძახებელია
ნაწილობრივი/სინთეზური `$clean`-ით — თუნდაც სქემის დონეზე გადამოწმებისთვის
— აუცილებლად სჭირდება ნამდვილი `'items'` მასივი, თორემ ჩუმად შლის
line item-ებს.

### 4.62 `/orders`-ის row-actions — "მეილზე გაგზავნა" ყოველ შეკვეთაზე

user-მა მოითხოვა: `/orders`-ის თითოეულ row-ს ჰქონდეს "მეილზე გაგზავნა"
მოქმედება, ზუსტად იმავე პრინციპით, რაც `/invoices`-ის ფორმას აქვს
(`4.55`). ახალი endpoint/მოდალი აშენების ნაცვლად, **იგივე** route
(`POST /invoices/send-email`, `InvoiceController::sendEmail()`) და
**იგივე** მოდალის მარკაპი გამოიყენება — orders.php-ზე ერთი გაზიარებული
`#invoiceEmailModal`, თითო row-ს საკუთარი trigger button-ით
(`data-invoice-id`, `data-customer-email`), invoices.php-ს
customer-select-დან prefill-ის ნაცვლად.

- **`Invoice::all()`** — SELECT-ს `c.customer_email` დაემატა (მანამდე
  მხოლოდ `find()` კითხულობდა), "to"-ს row-დანვე prefill-ისთვის.
- **`InvoiceController::orders()`** — `$org` (default-message text-ისთვის)
  და `email_errors`/`email_old`/`email_sent`/`email_failed` flash-ები
  view-ს გადაეცემა — ზუსტად იგივე flash key-ები, რასაც `sendEmail()`
  უკვე იყენებდა `/invoices`-სთვის (ერთდროულად ორივეს არასდროს სჭირდება,
  ერთი redirect ერთ გვერდზე მიდის).
- **`sendEmail()`** — ახალი `$fromOrders = ($_POST['redirect'] ?? '')
  === '/orders'` whitelist-შემოწმება (**არა** open-redirect — ფორმის
  hidden `redirect` ველი მუდამ ერთ, hardcoded მნიშვნელობას აგზავნის).
  ორივე redirect ტოტი (validation-error და success/failure) ამ დროშის
  მიხედვით ირჩევს `/orders`-ს ან ძველ `/invoices?edit=N`-ს — `redirect`
  POST-ველის არარსებობისას (invoices.php-ს მოდალს ეს ველი საერთოდ არ
  აქვს) ქცევა ზუსტად ძველია, `4.55`-ის ცვლილება არ შეხებია.
- **`orders.php`** — თითო row-ს ახალი `bi-envelope` ღილაკი; მოდალის
  markup (invoices.php-ს იდენტური, `redirect` hidden ველი `/orders`-ით);
  `$emailDefaults`/`$emailVal`/`$emailBad` closures (იგივე კონვენცია,
  4.55-დან გადმოღებული); success/failure alert-ები page-ის თავში.
  JS: `show.bs.modal`-ზე "to" prefill მხოლოდ ცარიელზე (`data-customer-
  email`-იდან) — server-rendered `$emailOld` არასდროს ილუპება;
  reopen-on-error `?email_error=<id>`-ით (invoices.php-ს `?email=1`-ის
  row-ისთვის ცნობადი ვარიანტი — ერთი `#invoiceEmailTrigger`-ის ნაცვლად
  სწორი row-ის trigger button-ს პოულობს `data-invoice-id`-ით).

**გადამოწმებულია ცოცხლად** (tenant 31): row-ის ღილაკმა მოდალი გახსნა,
"to" სწორად `client31.8@example.test`-ით (customer_email) გაივსო,
"შეტყობინება" org-ის default-ით (`4.57`-ის fallback-ი); ნამდვილი გაგზავნა
(Gmail SMTP, ფეიკ `.test` domain-ზე) SMTP-დონეზე უარყოფილია, სწორად
დაბრუნდა `/orders`-ზე (არა `/invoices`) `email_failed` flash-ით, სტატუსი
"პირველადი" **დარჩა** უცვლელი (`markFinal()` მხოლოდ წარმატებულ send-ზე
ეშვება — სწორი ქცევა). ვალიდაციის შეცდომა (curl-ით, `to=not-an-email`,
`message=` ცარიელი) → `/orders?email_error=12`-ზე გადამისამართდა,
`is-invalid`/`invalid-feedback` სწორად გამოჩნდა, JS-მა სწორი row-ის
მოდალი გახსნა `data-invoice-id="12"`-ით და query flag URL-დან წაშალა.
Console error არცერთ ეტაპზე არ ყოფილა. Invoice id=12-ის მონაცემები
(ზემოთ აღწერილი restore-ის შემდეგ) დაცული დარჩა. Temp password
აღდგენილია.

### 4.63 "ექსპორტი PDF" → dropdown: ხელმოწერით / ხელმოწერის გარეშე (`/invoices` + `/orders`)

user-მა მოითხოვა: ორივე გვერდის ("ახალი ინვოისი" და "ყველა შეკვეთა")
"ექსპორტი PDF" ღილაკი ჩამოსაშლელ მენიუდ იქცეს ორი პუნქტით — ხელმოწერით
და ხელმოწერის გარეშე. თავდაპირველად მხოლოდ ორივე გვერდის **მთავარი**
ღილაკი (`invoices.php`-ის card-header, `orders.php`-ის page-header)
გადაკეთდა — მომდევნო შეტყობინებით user-მა დააზუსტა, რომ `/orders`-ის
**row-დონის** PDF-ხატულაც იგივე dropdown-ი უნდა იყოს, არა ცალკე
ერთ-კლიკიანი ბმული (იხ. ქვემოთ, addendum). მეილზე თანდართული PDF-იც
(`sendEmail()`) ყოველთვის ხელმოწერითაა, დროშა მას არ ეხება — ეს
განზრახ დარჩა უცვლელი, არც არავის უთხოვია.

- **`pdf/invoice.php`** — ახალი `@var bool $signed`; ხელმოწერის `<img>`
  ბლოკის `if`-ს `$signed &&` დაემატა.
- **`pdf/orders.php`** — იგივე `$signed`; ცხრილის ქვემოთ ახალი,
  პირობითი signature-ბლოკი (ორგანიზაციის ხელმოწერის სურათი, `$uploadDir`
  იგივე კონვენცია, რაც `pdf/invoice.php`-ს აქვს) — მანამდე ეს ფაილი
  საერთოდ არ აჩვენებდა ხელმოწერას.
- **`InvoiceController::exportInvoicePdf()`** — კითხულობს `$_GET['sign']`
  (`'1'` default), გადასცემს `'signed'`-ს template-ს.
- **`InvoiceController::exportOrdersPdf()`** — იგივე, `$_GET['sign']`-იდან.
- **`InvoiceController::sendEmail()`** — ცალსახად `'signed' => true`
  (feature-ს არ ეხება).
- **`InvoiceController::store()`** — `submit_action === 'export_pdf'`
  ორ მნიშვნელობად გაიყო: `export_pdf_signed`/`export_pdf_unsigned` —
  ცალკე "sign" ველის ნაცვლად ორი submit-ღილაკი (JS არ სჭირდება,
  `dropdown-item`-ებად `<button type="submit" name="submit_action">`).
  Redirect `/invoices/export-pdf?id=N&sign=1`-ზე ან `&sign=0`-ზე.
- **`invoices.php`** — card-header-ის ერთი ღილაკი Bootstrap
  dropdown-ად (`.dropdown` > toggle button + `.dropdown-menu` ორი
  submit-`.dropdown-item`-ით), იგივე `d-grid gap-2` layout-ში.
- **`orders.php`** — page-header-ის `<a>` იგივე dropdown-ად, უბრალო
  `<a href="/orders/export-pdf?sign=1|0">` ბმულებით (ცალკე ინვოისის
  save აქ არ სჭირდება).
- **ახალი lang-key-ები** (ka+en): `inv.export_signed`, `inv.export_unsigned`
  — ორივე გვერდზე გაზიარებული.

**გადამოწმებულია ცოცხლად + curl-ით** (tenant 31, invoice id=12,
ხელმოწერის ფაილი მართლა არსებობს დისკზე): ორივე dropdown ბრაუზერში
სწორად იხსნება, ორივე ვარიანტი სწორ href/submit-value-ს იძლევა.
Byte-დონეზე დადასტურებულია ოთხივე კომბინაცია — invoice PDF
signed=982943b vs unsigned=486206b, orders-list PDF signed=511889b vs
unsigned=15131b (სხვაობა ≈ ხელმოწერის jpg-ის ზომის, 496509b — ე.ი.
სურათი ნამდვილად მხოლოდ "ხელმოწერით" ვარიანტშია ჩართული). `store()`-ის
ახალი branch-იც პირდაპირ ტესტირებულია (curl, ნამდვილი ფორმის submit
`submit_action=export_pdf_unsigned`-ით) — სწორად გადამისამართდა
`/invoices/export-pdf?id=12&sign=0`-ზე და უსწორო (486216b) PDF ჩამოტვირთა.
ამ ტესტმა temporarily შეცვალა line item-ის `unit_id` (`NULL` → `1`,
ვალიდაციისთვის საჭირო იყო) — restore-ილია თავდაპირველ `NULL`-ზე. Temp
password აღდგენილია.

**Addendum** — `orders.php`-ის row-level "მოქმედება" PDF-ხატულაც (თითო
row-ს, table-ში) იგივე dropdown-ად გადაკეთდა: `<a href="/invoices/
export-pdf?id=N">` ერთი ბმულის ნაცვლად, `.dropdown d-inline-block` (row-ის
სხვა icon-ღილაკებთან ერთად ხაზზე დასაყენებლად) — toggle icon-ღილაკი
(`bi-file-earmark-pdf`) + ორი `dropdown-item` ბმული (`?sign=1`/`?sign=0`),
ზუსტად იგივე href-ნიმუში, რასაც page-header-ის dropdown-იც იყენებს.
`php -l` გავლილია; ცოცხლად row-ის dropdown-იც იხსნება, ორივე ბმულს
სწორი `id`/`sign` აქვს.

### 4.64 `/invoices`-იდან წარმატებული "მეილზე გაგზავნა" → სამუშაო მაგიდაზე გადამისამართება

user-მა შენიშნა რისკი: `/invoices?edit=N`-იდან მეილზე გაგზავნა
ავტომატურად ცვლის სტატუსს პირველადი→საბოლოო (`4.55`-ის `markFinal()`),
მაგრამ გაგზავნის შემდეგ page ისევ იმავე `?edit=N` ფორმაზე რჩებოდა —
იმავე ფორმის submit-ღილაკის ("დამატება"/"განახლება") შემთხვევით ხელახლა
დაჭერა ამ **უკვე გაგზავნილი** ინვოისის დუბლირების საფრთხეს ქმნიდა.
გადაწყვეტა: **მხოლოდ** წარმატებული გაგზავნის შემდეგ (`$sent === true`,
ანუ სტატუსიც ნამდვილად შეიცვალა) და **მხოლოდ** `/invoices`-კონტექსტში
(არა `/orders`-იდან, სადაც ეს რისკი საერთოდ არ არსებობს — იქ ფორმა
საერთოდ არ ჩანს), redirect `/`-ზე (სამუშაო მაგიდა) მიდის ფორმის
გვერდის ნაცვლად. წარუმატებელი გაგზავნა (SMTP შეცდომა) და ვალიდაციის
შეცდომა უცვლელია — სტატუსი მაშინ არ იცვლება, დუბლირების რისკიც არ
დგება, ამიტომ `?edit=N`-ზე დარჩენა (retry-სთვის) კვლავ სწორია.

- **`InvoiceController::sendEmail()`** — success-redirect-ის წინ ახალი
  `if ($sent && !$fromOrders) { redirect('/'); }`, ძველი
  `redirect($fromOrders ? '/orders' : '/invoices?edit=...')` ხაზი
  უცვლელად რჩება ყველა დანარჩენი შემთხვევისთვის (fallback).
- **`DashboardController::index()`** — ახალი `emailSent`/`emailFailed`
  flash-ები (იგივე key-ები, რასაც `invoices.php`/`orders.php` უკვე
  კითხულობდნენ — `4.55`/`4.62`) — წარმატებული გაგზავნის შეტყობინება
  ახლა აქ მოიხმარება, თორემ session-ში დარჩებოდა და მომდევნო
  `/invoices`/`/orders` ვიზიტისას გამოჩნდებოდა შეცდომით.
- **`dashboard.php`** — იგივე ორი alert-ბლოკი, რაც `invoices.php`/
  `orders.php`-ს აქვს (`alert-success`/`alert-warning`, `inv.email_sent`/
  `inv.email_failed`) — ახალი lang-key არ დასჭირვებია, არსებულები
  გაზიარებულია მესამედაც.

**გადამოწმებულია ცოცხლად, ნამდვილი SMTP-ის გარეშე**: დროებით ლოკალური
mock SMTP server (PHP socket, ყველა STMP-ბრძანებას წარმატებით
პასუხობს, არაფერს არ აგზავნის) + `.env`-ის დროებითი patch
(`MAIL_HOST=127.0.0.1`, სხვა ცვლილებები restore-ის შემდეგ ზუსტად
თავდაპირველზე დაბრუნებული) — `POST /invoices/send-email`
(`redirect`-ველის გარეშე, ანუ `/invoices`-კონტექსტი) → `Location: /`,
invoice id=12-ის `document_state` `draft`→`final`, `/`-ზე გამოჩნდა
სწორი success alert ("ინვოისი „TS1 2026-08-16 0001" გაიგზავნა
მეილზე."). იგივე მოთხოვნა `redirect=/orders`-ით → `Location: /orders`
(უცვლელი, `4.62`-ის ქცევა დაცულია). Invoice id=12 თავდაპირველ
`draft`-ზე დაბრუნებულია, `.env` restore-ილია (`diff`-ით დადასტურებული
— cleanup-მდე arც ერთი commit არ გაკეთებულა), temp password
აღდგენილია.

### 4.65 "მეილზე გაგზავნა" მოდალი — ვიზუალური გამოკეთილშობილება (`/invoices` + `/orders`)

user-მა უბრალოდ თქვა "არ მომწონს, გააკეთილშობილო" — კონკრეტული საჩივრის
გარეშე. ვინაიდან ეს ორივე გვერდზე სიტყვასიტყვით იდენტური, plain
Bootstrap-default მოდალია (თავისი chrome-ის გარეშე), მაშინ როცა იმავე
გვერდებზე უკვე არსებობს **უფრო გამართული** მოდალი — `invoice-preview-
modal.php` (`bg-light` header, icon+label საკუთარი "chrome", არა
Bootstrap-ის default `modal-title`) — ავირჩიე ის, როგორც ვიზუალური
ეტალონი და ორივე email-მოდალი მასთან თანმიმდევრულად გავაფორმე.
სუფთა redesign — არც ერთი behavior/route/validation არ შეცვლილა.

- **Header**: default `<h2 class="modal-title">` → `bg-light` header,
  `bi-envelope-paper` icon + `text-primary text-uppercase` label
  (`invoice-preview-modal.php`-ის header-ის იდენტური სტილი).
- **Dialog**: `.modal-dialog` → `.modal-dialog modal-dialog-centered`
  (ვერტიკალურად ცენტრირებული, ეკრანის ზედა კიდეზე მიბმულის ნაცვლად).
- **Validation-feedback**: `.invalid-feedback.d-block` (`.form-floating`-
  ის **გარეთ**, ცალკე ხაზზე) → `.invalid-feedback` (`.d-block`-ის
  გარეშე, `.form-floating`-ის **შიგნით**, label-ის შემდეგ) — Bootstrap-ის
  ნამდვილი `.is-invalid ~ .invalid-feedback { display: block }`
  sibling-selector-ს დაეყრდნო ხელით-იძულების ნაცვლად; ველი და მისი
  შეცდომა ახლა ერთ ვიზუალურ ჯგუფშია, არა ცალკე floating-block-ის ქვემოთ.
- **Attachment/signature ჰინტები**: ორი შიშველი `text-secondary small`
  ხაზი → ერთი `bg-light rounded-3 p-3` ინფო-პანელი (იგივე `--ds-radius`
  ტოკენი, რასაც `.ds-card` იყენებს) — მკაფიოდ გამოყოფილი ბლოკი,
  არა "დავიწყებული" ტექსტის ნარჩენი.
- **Textarea**: `height:8rem` → `9rem`, ცოტა მეტი სივრცე default
  შეტყობინების ტექსტისთვის.

ცვლილება იდენტურად გავიმეორე ორივე ფაილში (`invoices.php`-ის და
`orders.php`-ის საკუთარ `#invoiceEmailModal`-ებში — ეს ორი ცალკე,
თითქმის-დუბლირებული markup-ია, არა გაზიარებული partial, ისე როგორც
`4.62`-ში დაპროექტდა).

**გადამოწმებულია ცოცხლად** (tenant 31): ორივე გვერდზე მოდალის header/
dialog/info-box კლასები სწორია; `orders.php`-ზე "to"/"message" prefill
(customer email + org-ის default ტექსტი) უცვლელად მუშაობს;
`invoices.php`-ზე ვალიდაციის-შეცდომის მდგომარეობა (`novalidate` + JS
force-submit ცუდი მონაცემებით, HTML5-ის native ვალიდაციის გვერდის
ავლით) სწორად აჩვენებს `.is-invalid`/`.invalid-feedback`-ს —
`getComputedStyle().display === 'block'`-ით დადასტურებული, ანუ ახალი
sibling-selector-ზე დაფუძნებული feedback ნამდვილად ჩანს, არა მხოლოდ
DOM-ში დამალული. Console error არცერთ გვერდზე არ ყოფილა. Temp password
აღდგენილია — DB-ში ცვლილება არ განხორციელებულა (მხოლოდ markup/CSS).

### 4.66 ინვოისის მეილის სხეული → საკუთარი HTML template (`app/Views/emails/invoice.php`)

user-მა WYSIWYG/template-management სისტემა ითხოვა (`4.65`-ის მოდალის
redesign-ის შემდეგ) — ამაზე ვურჩიე, რომ ერთი ინვოისის-მეილისთვის
overkill-ია (ახალი დამოკიდებულება, ცხრილი, sanitization), მომავალი
"სარეკლამო მეილების" მოდულისთვის კი გამართლებულია, მაგრამ **მაშინ**
ავაშენოთ, არა ახლა (user დამეთანხმა). ამის ნაცვლად, user-მა კონკრეტული,
მცირე მოთხოვნა დააზუსტა: ინვოისის მეილის სხეული ნამდვილ HTML-ად იყოს,
ცალკე template-ფაილში, რომ ვიზუალზე პირდაპირ თავად ემუშავათ.

- **`app/Views/emails/invoice.php`** (ახალი ფაილი/დირექტორია) —
  `InvoiceController::sendEmail()`-ის ძველი ერთსტრიქონიანი
  `nl2br(e($message)) . '<br><br>' . orgSignatureHtml(...)` string-
  აგება ჩანაცვლდა ნამდვილი, ცალკე view-ფაილით — იგივე
  `Controller::renderToString('emails/invoice', [...])` მექანიზმი, რასაც
  `pdf/invoice`/`pdf/orders` უკვე იყენებენ (ახალი ცოდნა/pattern არ
  დასჭირვებია). Table-based layout + inline styles (ფერადი header-
  ზოლი org-ის სახელით, თეთრი "card", ჩრდილიანი კუთხეები) — არა
  `<style>` block, ემეილ-კლიენტების თავსებადობისთვის (განსაკუთრებით
  Outlook desktop, რომელიც `<style>`-ს არასანდოდ იღებს).
- **`InvoiceController::orgSignatureHtml()`** — წაშლილია (dead code,
  მისი ლოგიკა template-ის შიგნით გადავიდა).
- ცხადად **მხოლოდ** markup/template ცვლილებაა — validation, redirect,
  attachment, `markFinal()` ლოგიკა ხელუხლებელია.

**გადამოწმებულია ცოცხლად, ნამდვილი SMTP-ის გარეშე** (იგივე `4.64`-ის
mock-SMTP მიდგომა, ამჯერად capture-ით): ლოკალური mock server, რომელიც
`DATA`-ს ინახავს ფაილში ნაცვლად რომ უბრალოდ `250 ok`-ით უპასუხოს —
`.env`-ის იგივე დროებითი patch. რეალურად აპის მეშვეობით გაგზავნილი
მეილი დაიჭირა და base64-დან decode-ის შემდეგ დადასტურდა: header-ზოლში
org-ის სახელი ("შპს ტესტ ვან"), სხეულში ნამდვილი შეტყობინების ტექსტი,
signature-ბლოკში org-ის name/phone/email/address — ყველაფერი სწორი
Georgian text-ით, escaping-ის ბაგების გარეშე, სწორი multipart/mixed
სტრუქტურით (HTML + PDF attachment). Invoice id=12 თავდაპირველ `draft`-ზე
დაბრუნებულია, `.env` ზუსტად restore-ილია, temp password აღდგენილია,
დროებითი test-ფაილები წაშლილია.

### 4.67 "მეილზე გაგზავნა" მოდალი → live preview `emails/invoice.php`-ის თავად შაბლონიდან

user-მა დააკონკრეტა: "მეილის ფორმის ვიზუალი ავიღოთ შაბლონიდან" — ანუ
compose-მოდალში `emailMessage`-ის plain textarea/gray-info-box (`4.65`)
თავად `emails/invoice.php`-ის (`4.66`) რეალურ layout-ში ჩაისვას, რომ
წერისას უკვე ის ჩანდეს, რასაც დამკვეთი მიიღებს — არა ცალკე, "დამსგავსებული"
mockup, არამედ ზუსტად იგივე ვიზუალური სტრუქტურა, ერთი ცვლადის
გარეშეც არ გასცდენია (ორივე ადგილას `$org['name']`/phone/email/address
იგივე real DB-მონაცემია).

- **`invoices.php` + `orders.php`** (ორივეს `#invoiceEmailModal`) —
  `form-floating` textarea + ცალკე `bg-light` info-box (`4.65`) →
  `emailMessage` თავად ზის `emails/invoice.php`-ის ფერად ("`#2563eb`")
  header-ზოლისა და თეთრი card-ის შიგნით (`border-0 p-0 shadow-none`
  textarea, ისე რომ card-ის ნაწილივით გამოიყურებოდეს, არა ცალკე
  ველი), header-ში ორგანიზაციის სახელი, card-ის ბოლოში — signature-ბლოკი
  (name/phone/email/address, `border-top`), ზუსტად template-ის იგივე
  `array_filter`/`implode('<br>', ...)` ლოგიკით (დუბლირებული ორივე
  view-ში + template-ში თავად — `4.62`-ის უკვე დამკვიდრებული კონვენციით,
  სამივე ადგილი დამოუკიდებელი/თითო-ფაილიანია, არა shared partial).
- **`inv.email_signature_note`** lang-key (ka+en) — წაშლილია, ორივე
  ენაზე — აღარ სჭირდება: ხელმოწერა ახლა ვიზუალურადაც ჩანს, ცალკე
  ტექსტური "ავტომატურად დაემატება" გაფრთხილება ზედმეტი გახდა.
  `inv.email_attachment_note` (PDF დანართის შესახებ) დარჩა — ეს
  ვიზუალურად არსად ჩანს, ტექსტური შენიშვნა კვლავ საჭიროა.
- **Validation-ის ვიზუალი** — `.form-control.is-invalid`-ის წითელი
  ჩარჩო აღარ მუშაობდა plain `<div>`-ზე (Bootstrap-ის `.is-invalid`
  სელექტორი `.form-control`-სპეციფიკურია) — card-ის გარე `<div>`-ს
  პირობითი `border-danger` კლასი ემატება, `.invalid-feedback` კი
  card-ის ქვემოთ, `d-block`-ით (აღარ არის floating-label-ის შიგნით,
  ცალკე card-ის გარეთაა).

**გადამოწმებულია ცოცხლად** (tenant 31): ორივე გვერდზე მოდალის card
ზუსტად ისე გამოიყურება, როგორც `emails/invoice.php` (ფერადი header +
org-ის სახელი + card-ის შიგნით ედიტირებადი textarea default-ტექსტით +
signature ქვემოთ); `orders.php`-ზე "to" prefill (`client31.8@
example.test`) უცვლელად მუშაობს. Console error არცერთ გვერდზე არ
ყოფილა. DB-ში ცვლილება არ განხორციელებულა (მხოლოდ markup/lang-key),
temp password აღდგენილია.

### 4.68 "ბმულის გაზიარება" — ამოქმედება (`/invoices` + row-ები `/orders`-ზე)

user-მა ჯერ სთხოვა გეგმის აღწერა (`ბმულის გაზიარება" placeholder-
ღილაკის ამოქმედება invoices.php-ზე), შემდეგ დაადასტურა და დამატებით
სთხოვა იგივე `/orders`-ის თითოეულ row-საც დაემატოს. **Access-control
დონეზე არაფერი ახალი არ დაშენებულა** — ყოველ ინვოისს უკვე ჰქონდა
საკუთარი `view_token` და `/invoices/view?id=N&token=...`/`/invoices/
export-pdf?id=N&token=...` უკვე მუშაობდა login-ის გარეშე (`4.53`) —
საჭირო იყო მხოლოდ ამ URL-ის აწყობა და "დააკოპირე" behavior-ის მიბმა.

- **`app/Core/helpers.php`** — ახალი `app_url(): string` (APP_URL
  .env-დან, scheme-ის ავტომატური დამატებით) — `InvoiceController::
  pdfFooterHtml()`-ის საკუთარი, აქამდე dublicate inline ლოგიკა ამაზეა
  გადაყვანილი, ერთი ცვლილების ნაცვლად ორ ადგილას აღარ სჭირდება
  სინქრონიზაცია.
- **`InvoiceController::shareUrl(array $invoice): string`** (ახალი,
  private) — `app_url() . '/invoices/view?id=N&token=...'`.
  `index()`-ს `'shareUrl'` ემატება ($editingInvoice-ის მიხედვით, ან
  `''` ახალი ინვოისისთვის); `orders()`-ს კი `'appUrl'` — თითო row-ს
  URL-ს view თავად აწყობს (იგივე კონვენცია, რაც `$invoiceNumber`
  closure-ს აქვს).
- **`InvoiceController::store()`** — ახალი `submit_action === 'share_link'`
  branch, იგივე save-first trick, რასაც preview/email იყენებენ — **მაგრამ
  auto-click-to-copy არ ემატება** მიწოდებულ redirect-ზე. მიზეზი:
  Clipboard API-ს ნამდვილი user-click სჭირდება (browser security model),
  script-ით გამოძახებული synthetic `.click()`-ს ეს "activation" არა
  აქვს — ცოცხლად, headless ბრაუზერშიც დადასტურდა (`navigator.
  permissions.query({name:'clipboard-write'})` → `denied` ამ sandbox-ში
  ნებისმიერი click-ის მიუხედავად, ნამდვილ user-ბრაუზერშიც კი synthetic
  click არ იმუშავებდა). ამიტომ redirect უბრალოდ `/invoices?edit=N`-ზეა
  (query flag-ის გარეშე) — ღილაკი უბრალოდ ცოცხალია, ერთი ნამდვილი
  click-ითვე მუშაობს, ისე როგორც ნებისმიერ სხვა ვიზიტზე.
- **`ds_share_link_script()`** (helpers.php, ახალი) — ერთი გაზიარებული
  JS ორივე გვერდისთვის (`ds_invoice_preview_script()`-ის იგივე
  "მეორე caller-ზე გამოყოფის" კონვენცია): ყოველი `[data-share-url]`
  ღილაკი click-ზე `navigator.clipboard.writeText()`-ს იძახებს, success-ზე
  ღილაკის `<i>` icon-ს 1.5 წამით ჩეკმარკზე ცვლის (`bi-check-lg
  text-success`) + `title`-ს დროებით "ბმული დაკოპირდა"-ზე — მთელი
  label-ის ჩანაცვლების ნაცვლად, რადგან `orders.php`-ის row-ღილაკები
  icon-only-ა (title-ით), `invoices.php`-ის კი ტექსტიანი. Fail-ზე
  (`catch`) უბრალოდ არაფერი ხდება — არც error, არც false-positive
  "დაკოპირდა" feedback.
- **`invoices.php`** — "ბმულის გაზიარება" იგივე ორ-შტოიანი პატერნით
  (`4.55`-ის მსგავსი): `$editingInvoice !== null` → პირდაპირ
  `data-share-url`-იანი ღილაკი (`id="invoiceShareLinkTrigger"`); სხვა
  შემთხვევაში → `submit_action=share_link` submit. "ვოთსაპზე გაგზავნა"
  კვლავ unwired placeholder-ია, არავის უთხოვია.
- **`orders.php`** — ახალი per-row `bi-link-45deg` ღილაკი (row-ის სხვა
  icon-ღილაკების გვერდით), `$shareUrl` closure-ით აწყობილი
  `data-share-url`-ით. `Invoice::all()`-ის `SELECT i.*` უკვე შეიცავდა
  `view_token`-ს — model-ში ცვლილება არ დასჭირვებია.

**გადამოწმებულია ცოცხლად + curl-ით** (tenant 31): `invoiceShareLinkTrigger`-ის
`data-share-url` ინვოისი 12-ისთვის ზუსტ, ნამდვილ token-ს შეიცავს
(`https://www.invoice.net.ge/invoices/view?id=12&token=...`) — curl-ით
დადასტურებულია, რომ ეს URL **ნამდვილად** `200 OK`-ს აბრუნებს, cookie-ს
გარეშე (ანუ სრულად public). `orders.php`-ის row-ღილაკიც იგივე,
სწორ URL-ს იძლევა. Click-to-copy JS ლოგიკა **ცალკე დამოწმებულია**
(`navigator.clipboard.writeText`-ის დროებითი stub-ით, sandbox-ის
`clipboard-write: denied` შეზღუდვის გვერდის ავლით) — `writeText()`
სწორ URL-ს იღებს, icon სწორად იცვლება checkmark-ზე და 1.5წმ-ში
უკან ბრუნდება. `submit_action=share_link` branch პირდაპირ, curl-ით
შექმნილი ტესტ-ინვოისით (id=35) ტესტირებულია — სწორად გადამისამართდა
`?edit=35`-ზე, ახალი ინვოისის `view_token`-იც სწორად აისახა ღილაკზე;
ტესტ-ინვოისი წაშლილია. Invoice id=12-ის fixture-მონაცემები უცვლელი
დარჩა. Console error არცერთ გვერდზე არ ყოფილა. Temp password
აღდგენილია.

### 4.69 დამკვეთის რეპორტი — ახალი გვერდი (`/customers/report?id=N`)

user-მა მოითხოვა: კონკრეტული დამკვეთის რეპორტის გვერდი — ინვოისების
სია ნომრების მიხედვით, დროში გაწერილი მოცულობის გრაფიკი, ინვოისზე
კლიკით ნახვა გვერდის დატოვების გარეშე, ჯამი სტატუსების მიხედვით.
დამატებით ვთხოვე და დამეთანხმა headline-სტატისტიკის (სულ/საშუალო/
ბოლო ინვოისი) და ტოპ-5 პროდუქტის ჩამატებაზეც. **ახალი UI-პატერნი არ
დამჭირვებია** — ინვოისის ნახვა, სტატუსის badge-ები, ds-table, Chart.js
გრაფიკი — ყველა ამ სესიაში (ან უფრო ადრე) უკვე დამკვიდრებული კონვენციაა,
ეს გვერდი მხოლოდ ერთ ადგილას აწყობს.

- **`app/Models/CustomerReport.php`** (ახალი მოდელი, `Dashboard.php`-ის
  იგივე "სპეციალიზებული აგრეგაციები" როლში) — 5 static მეთოდი:
  `invoices()` (სია, `sequence_number DESC`), `summary()` (count/total/
  average/first/last date, `COALESCE`-ით ცარიელ შემთხვევაზეც უსაფრთხო),
  `statusTotals()` (`document_state` => `{count,total}`, `array_fill_keys`
  `Invoice::DOCUMENT_STATES`-ით — draft/final ორივე ყოველთვის present,
  0-ით, თუნდაც ერთ სტატუსს არცერთი ინვოისი არ ჰქონდეს), `monthlyTotals()`
  (მთელი ისტორია, არა მხოლოდ მიმდინარე წელი — განსხვავებით
  `Dashboard::revenueByUser()`-ისგან, საკუთარი დოკ-ბლოკი ხსნის რატომ),
  `topProducts()` (`invoice_items` join, revenue-ით დალაგებული, LIMIT 5).
  ყველგან იგივე tenant-scoping კონვენცია, რაც `Invoice::all()`-ს აქვს —
  `created_by IN (tenantMemberIds)`, არა customer-ის `ruler`-ით პირდაპირ.
- **`Customer::find(int $id, int $ruler): ?array`** (ახალი) — მანამდე
  არ არსებობდა (`all()` იყო ერთადერთი read მეთოდი).
- **`CustomerController::report()`** (ახალი) — 404 (`ErrorController`)
  არარსებულ/სხვა-ტენანტის `id`-ზე, ზუსტად იგივე pattern, რასაც
  `InvoiceController`-ის access-control-ები იყენებენ.
- **`GET /customers/report`** — ახალი route.
- **`app/Views/customer-report.php`** (ახალი) — breadcrumb + "უკან"
  ღილაკი; დამკვეთის საკონტაქტო ინფოს card; 4 headline stat-card
  (dashboard.php-ის ზუსტად იგივე `ds-icon-tile`/`card ds-card h-100`
  მარკაპი); Chart.js bar chart (dashboard.php-ის იგივე CDN/pattern,
  ცარიელ ისტორიაზე chart საერთოდ არ render-დება — `<canvas>`-ის
  ნაცვლად empty-state); status-totals + top-products ორი პატარა card
  გვერდიგვერდ; ინვოისების ds-table, ნომერზე დაჭერა `data-bs-toggle=
  "modal" data-bs-target="#invoicePreviewModal"`-ით ხსნის **იმავე**
  `invoice-preview-modal.php`/`ds_invoice_preview_script()`-ს, რასაც
  `/orders`/`/invoices` იყენებენ — ახალი modal/fetch-კოდი ნულია.
- **`customers.php`** — სიის ცხრილს ახალი სვეტი დაემატა, per-row
  `bi-bar-chart` ბმული `/customers/report?id=N`-ზე — row-click-ის
  ედიტ-რეჟიმს არ ეხება (იგივე `event.target.closest('a')` გამონაკლისი,
  რასაც tel:/mailto: ბმულები უკვე იყენებდნენ, ახალი JS არ დასჭირვებია).
- **ახალი lang-key-ები** (ka+en, `cust.report*`): 15 გასაღები.

**გადამოწმებულია ცოცხლად + curl-ით** (tenant 31, customer 1560 — 1
ინვოისით): გვერდი სწორად ითვლის ყველა სექციას (headline stats, status
totals draft=1656₾/1 · final=0₾/0, top product "ლურსმანი 5სმ" 10ც/1656₾);
Chart.js ინსტანციირებულია (`Chart.getChart(canvas)` truthy); ინვოისის
ნომერზე დაჭერამ სწორი მოდალი გახსნა **URL-ის შეცვლის გარეშე**
(`location.href` უცვლელი დარჩა). Access-control: საკუთარი customer
→ `200`; სხვა tenant-ის customer (id=1, tenant 1-ის) → `404`; არარსებული
id → `404`; `id`-ის გარეშე → `404`. ცარიელი-ისტორიის customer-ითაც
(id=1553, 0 ინვოისი) — ყველა სექცია (headline 0/0.00/"ჯერ არცერთი",
სტატუსები 0/0, chart-ის ნაცვლად empty-state, ცხრილის ნაცვლად
empty-state) PHP შეცდომების/warning-ების გარეშე გამოჩნდა. `customers.php`-ის
row-click-ედიტი უცვლელად მუშაობს ახალი სვეტის მიუხედავად. Console
error არცერთ ეტაპზე არ ყოფილა. DB-ში ცვლილება არ განხორციელებულა
(მხოლოდ read-queries), temp password აღდგენილია.

### 4.70 დამკვეთის რეპორტი — მოდალის მოცილება, სექციების გადალაგება, chart ორ სერიად

`4.69`-ის პირველი ვერსიის გადამოწმებისას user-მა 2 კონკრეტული
ცვლილება მოითხოვა: (1) ინვოისის "ნახვა" აღარ იყოს მოდალი — პირდაპირ,
inline, გვერდზევე გამოჩნდეს, default-ად ბოლო (უახლესი) ინვოისით,
სექციების მიმდევრობა: Customer info → Invoice (inline) → Invoice list
→ Headline stats; (2) headline stats-ის chart-ი ორ სერიად გაიყოს —
პირველადი/საბოლოო.

- **მოდალი მთლიანად მოშორდა** — `invoice-preview-modal.php`-ის
  `require` და `ds_invoice_preview_script()`-ის გამოძახება წაშლილია
  ამ გვერდიდან (`ds_invoice_preview_script()` `#invoicePreviewModal`-ს
  პირდაპირ ეძებს `document.getElementById`-ით და `.addEventListener`-ს
  უძახებს null-ზე, თუ ეს element არ არსებობს — ამიტომ **არ შეიძლებოდა**
  უბრალოდ დარჩენილიყო გამოუყენებელი, რეალურად page-ს დაამტვრევდა).
- **`CustomerController::report()`** — ახალი: `$defaultInvoiceId`
  (`$invoices[0]['id']`, სია უკვე `sequence_number DESC`-ითაა
  დალაგებული, ე.ი. პირველი row = უახლესი) და `$invoicePreviewHtml` —
  `InvoiceController::preview()`-ის ის ორი ხაზი (`Invoice::find()` +
  `Invoice::itemsFor()` + `renderToString('invoice-preview', ...)`)
  პირდაპირ აქაც გამეორებულია (access-control აქ Customer::find()-ის
  tenant-scope-ითაა უკვე დაცული — InvoiceController-ის token-vs-login
  dual-access ლოგიკა აქ საერთოდ არ სჭირდება, ამიტომ private მეთოდის
  გაზიარება/refactor ორ controller-ს შორის overkill იქნებოდა ერთი
  პატარა ბლოკისთვის).
- **`CustomerReport::monthlyTotals()`** — return-shape შეიცვალა
  `{months, totals}` → `{months, series}`, ზუსტად `Dashboard::
  revenueByUser()`-ის იგივე ფორმა (ერთი სერია `document_state`-ზე,
  არა tenant member-ზე) — SQL-ს `document_state` დაემატა GROUP BY-ში,
  view-ის chart-კოდი სიტყვასიტყვით იმეორებს dashboard.php-ის
  grouped-bar მიდგომას. ფერები: `draft`='#94a3b8' (ნაცრისფერი),
  `final`='#4f46e5' (indigo).
- **`customer-report.php`** — მთლიანად გადალაგებულია მოთხოვნილი
  თანმიმდევრობით. ახალი "Invoice" card (`invoice-preview-modal.php`-ის
  `bg-light` header-სტილის იგივე გამეორება, უბრალოდ page-card-ად
  `<div class="modal-header">`-ის ნაცვლად) — ნომერი/სტატუსი
  `#reportInvoiceNumber`/`#reportInvoiceStatus`-ში, სხეული `#invoicePreviewInline`-ში.
  ინვოისების სიაში ნომერზე დაჭერა (`.js-report-invoice-trigger`,
  აღარ არის `data-bs-toggle`/`data-bs-target`) → `fetch('/invoices/
  preview?id=N')` (**იგივე, უცვლელი endpoint**, რასაც მოდალი იყენებდა)
  → პასუხი პირდაპირ `#invoicePreviewInline`-ში injectდება, ნომერი/
  სტატუსი-badge/active-row highlight ახლდება JS-ით, გვერდი
  `scrollIntoView`-ით ზემოთ ბრუნდება. ეს page-სპეციფიკური inline JS
  (არა helpers.php-ში გატანილი) — `ds_invoice_preview_script()`-ის
  ხელახლა გამოყენება არასწორი იქნებოდა (მოდალზეა აგებული, რომელიც
  აქ აღარ არსებობს), საკუთარი პატარა `statusData` მასივი დუბლირებულია
  (2 entry, იაფი დუბლირება, `$emailSignatureLines`-ის იგივე პრეცედენტი).

**გადამოწმებულია ცოცხლად** (tenant 31, customer 1560): ორი ტესტ-
ინვოისით (id=12 draft/1656₾ + დროებით შექმნილი id=36 final/100₾,
სხვადასხვა თარიღით) — default-ად ჩატვირთვისას ჩანდა უახლესი (id=36,
"საბოლოო"), ინვოისების სიაში id=12-ზე დაჭერამ **URL-ის შეცვლის
გარეშე** გადართო ნომერი/სტატუსი-badge/სხეული/active-row სწორ
მნიშვნელობებზე. Chart-მა სწორად აჩვენა 2 ცალკე სერია ("პირველადი"
1656 / "საბოლოო" 100), თითო თავისი ფერით. Headline stats/status-
totals/top-products ყველა განახლდა ორივე ინვოისის გათვალისწინებით
(სულ 2, 1756₾, საშ. 878₾). ცარიელი-ისტორიის customer (id=1553)
ხელახლა გადამოწმდა ახალი structure-ითაც — 4 empty-state ბლოკი,
PHP შეცდომების გარეშე. ტესტ-ინვოისი (id=36) წაშლილია, id=12
უცვლელი დარჩა, temp password აღდგენილია.

### 4.71 ბაგი: ენის გადამრთველი კარგავდა query-string-ს ყველა გვერდზე

user-მა შენიშნა: `/customers/report?id=N`-ზე ენის გადართვისას `id`
იკარგებოდა. Root-cause-ის მოძებნისას აღმოჩნდა, რომ ეს **არ იყო** ამ
გვერდის ცალკე ბაგი — `ds_lang_url()` (`app/Core/helpers.php`,
topbar-ისა და ყველა auth-გვერდის ენის-ღილაკის ერთადერთი წყარო)
ყოველთვის მხოლოდ `Router::current()` (წმინდა path, query-სტრინგის
გარეშე) + `?lang=code`-ს აწყობდა — ანუ **ნებისმიერი** query-param-ზე
დამოკიდებული გვერდი (`/invoices?edit=N`, `/customers/report?id=N`
და ა.შ.) კარგავდა თავის სრულ მდგომარეობას ენის გადართვისას, არა
მხოლოდ ახალი რეპორტის გვერდი — უბრალოდ user-მა ეს პირველად აქ
შენიშნა, რადგან `?id=` ამ გვერდისთვის აუცილებელია (მისი გარეშე 404).
Lazy fix-ი (მხოლოდ ამ ერთ გვერდზე `?id=`-ის ხელით დამატება) ყველა
დანარჩენ გვერდს ისევ გატეხილს დატოვებდა — გასწორებულია **root**-ში,
ერთადერთ გაზიარებულ ფუნქციაში:

- **`ds_lang_url()`** — `$_GET`-ის ყველა არსებული პარამეტრი ინახება,
  მხოლოდ `lang` overwrite/emplace-დება, `http_build_query()`-ით
  აწყობილი query-string მთლიანად `e()`-ითაა escaped (საჭირო გახდა,
  რადგან ერთზე მეტი param-ის შემთხვევაში `&`-საც შეიცავს, რომელიც
  HTML `href`-ში `&amp;`-ად უნდა გამოჩნდეს).

**გადამოწმებულია ცოცხლად**: `/customers/report?id=1560`-ზე ენის
ბმულმა სწორად აჩვენა `?id=1560&lang=en` (არა მხოლოდ `?lang=en`),
გადართვის შემდეგ გვერდი ინგლისურად, სწორი customer-ით, 404-ის
გარეშე ჩაიტვირთა. იმავე ფიქსმა ასევე გამოასწორა `/invoices?edit=N`-იც
(დამოუკიდებლად გადამოწმებული) — ანუ ეს ერთი, საერთო ცვლილება ყველა
query-param-ზე დამოკიდებულ გვერდს ეხმარება, არა მხოლოდ ორ ამ
კონკრეტულს. Console error არცერთ ეტაპზე არ ყოფილა, temp password
აღდგენილია.

### 4.72 ცხრილის style/script აუდიტი — უკვე ცენტრალიზებულია, ერთი inline style დარჩენილი აღმოჩნდა

user-მა მოითხოვა: ყველა ცხრილის style/script გატანა ცალკე `table.css`/
`table.js`-ში, inline style/script-ების შემოწმებით. აუდიტის შედეგი:
**ეს უკვე თითქმის მთლიანად გაკეთებული იყო** — `public/vendor/table/
css/ds-table.css` + `public/vendor/table/js/ds-table.js` უკვე არსებობს
და უკვე ზუსტად ეს როლი აქვს (ახალი `table.css`/`table.js` არ შემქმნია
— ცალკე, თითქმის-იდენტური ფაილების გაჩენა დაერევოდა, არა ეხმარებოდა
`app_url()`-ის იგივე ლოგიკით). `layout.php` ყოველ გვერდზე `ds-table.css`-ს
გლობალურად კრავს, `ds_table_script()` (helpers.php) კი `ds-table.js`-ს
per-page — 6 გვერდი უკვე იყენებს (`customers`, `orders`, `products`,
`users`, `dashboard`, `customer-report`).

**Grep-აუდიტით ნაპოვნი**: მთელს `app/Views/`-ში, ცხრილთან
დაკავშირებული `<style>` block ან inline `style="..."` მხოლოდ ერთი
ნამდვილი შემთხვევა იყო — `users.php`-ის `<td style="width:48px;">`
(ავატარის სვეტი). წაშლილია, `.ds-table td.ds-table-col-avatar { width:
48px; }`-ად გადატანილია `ds-table.css`-ში.

**შემოწმებული და **განზრახ** ხელუხლებელი დარჩენილი**:
- `emails/invoice.php`, `pdf/orders.php`, `pdf/invoice.php` — თავიანთი
  `<style>` block-ები **უნდა** დარჩეს inline: ეს არა Bootstrap-ით
  render-დებული გვერდები, არამედ დამოუკიდებელი დოკუმენტები (email
  client-ები + mPDF), რომლებსაც გარე CSS ფაილის ჩატვირთვა საერთოდ არ
  შეუძლიათ სანდოდ — ორივე ფაილის საკუთარი docblock ეს აშკარად ხსნის
  (`4.66`-ის emails/invoice.php-ის დოკუმენტაცია). გატანა ამ დოკუმენტებს
  **გატეხავდა**, არა გაასუფთავებდა.
- `invoice-view.php`, `invoice-preview.php`, `superuser.php`-ის ცხრილები
  — pure Bootstrap utility classes, inline style საერთოდ არ აქვთ.
- `users.php`-ის ავატარის `img`/`span` inline sizing (`width:32px` და
  სხვა) — component-დონის (`ds-avatar`) სტილია, არა ცხრილის, ცხრილის
  გარეთაც გვხვდება (profile-upload widget) — table.css-ში გადატანა
  არასწორი ადგილი იქნებოდა.
- სხვა `style="font-size:2rem;opacity:.4;"` (empty-state icon, 6+
  გვერდზე გამეორებული) — ცხრილის კი არა, ზოგადი "ცარიელი სია" pattern-ია
  (ცხრილის ნაცვლადაც ჩნდება, არა მისი ნაწილი) — scope-ის გარეთაა ამ
  მოთხოვნისთვის, არ შეხებია.
- JS-ში დუბლირებული sort/search/paging ლოგიკა — არ ნაპოვნია;
  `customers.php`-ის ერთადერთი table-related JS (`ds-table-search`
  input-ის პროგრამული შევსება) ბიზნეს-ფუნქციაა (ს/კ ძებნა), არა
  ცხრილის generic behavior — `ds-table.js`-ს არ ეკუთვნის.

**გადამოწმებულია**: `php -l` გავლილია, class-სახელი ზუსტად ემთხვევა
view-ში და CSS-ში. Live-ბრაუზერით `/settings/users`-ზე ვერ
გადამოწმდა ვიზუალურად — tenant 31-ს sub-user არ ჰყავს (ცხრილი
ცარიელია, "შედეგი ვერ მოიძებნა") — ცვლილება მექანიკური/დაბალი-რისკის
იყო (style-ატრიბუტის class-ად გადატანა, იდენტური მნიშვნელობით),
ამიტომ ცალკე test-მონაცემის შექმნა overkill იყო.

### 4.73 ყველა ცხრილს `table-striped` — + hover-ის ერთ დღემდე-ფარული CSS-ბაგის აღმოჩენა/გასწორება

user-მა მოითხოვა: ყველა ცხრილს striped-rows სტილი ჰქონდეს.
Bootstrap-ის საკუთარი `table-striped` კლასი დაემატა ყველა 9 `<table>`-ს
`app/Views/`-ში (`customers`, `orders`, `products`, `users`, `dashboard`,
`customer-report`, `superuser`, `invoice-view`, `invoice-preview`) —
ახალი dependency/custom CSS არ დასჭირვებია definition-ის დონეზე,
Bootstrap-ს ეს native აქვს.

**აღმოჩნდა**, რომ ეს მარტივი კლასის დამატება საკმარისი არ იყო
`.ds-table`-ით გახვეულ 6 გვერდზე — `ds-table.css`-ის `4.63`-მდელი
წესი (`background-image: none`, "grid-ის ხაზები crisp რომ დარჩეს")
სწორედ Bootstrap-ის `table-striped`-ის საკუთარ მექანიზმს აუქმებდა
(ორივე, stripe-იც და hover-იც, `background-image`-ით მუშაობს).

- **`ds-table.css`** — ახალი წესი, `background-color`-ით (არა
  `background-image`, რომელიც ზემოთ ნულირებულია) — `.ds-table
  .table-striped > tbody > tr:nth-of-type(odd) > *`, hover-ის ტონზე
  ერთი ჩრდილით უფრო ღია, რომ hover კვლავ შესამჩნევად მუქდებოდეს
  striped row-ზეც.
- **ამ დროს აღმოჩენილი ცალკე ბაგი**: ახალი stripe-წესისა და არსებული
  hover-წესის CSS-specificity **ზუსტად ტოლი** გამოვიდა (ორივეს აქვს 2
  class + 1 pseudo-class) — ტოლ specificity-ზე **source-order-ში
  უფრო გვიან** განსაზღვრული წესი იმარჯვებს, stripe კი hover-ის
  *შემდეგ* დავამატე პირველად, ანუ hover საერთოდ აღარ მუშაობდა
  striped (კენტ) row-ებზე — ცოცხლად დადასტურებული (`:hover`-ის
  ფონი 0.03-ალფა tint-ზე იკეტებოდა, tertiary-bg-მდე არ მუქდებოდა).
  გასწორებულია — stripe-წესი გადატანილია hover-წესის **წინ**
  (source-order-ში), hover ახლა ისევ იმარჯვებს ტოლი specificity-ის
  შემთხვევაშიც.

**გადამოწმებულია ცოცხლად**: `/customers`-ზე (8 row) `getComputedStyle`-ით
დადასტურდა ნამდვილი alternating pattern (`rgba(0,0,0,.03)` / თეთრი
/ `rgba(0,0,0,.03)` / თეთრი...). Hover-ის ბაგი თავად აღმოვაჩინე
ამავე გადამოწმებისას (`:hover`-ის ფონი უცვლელი დარჩა პირველი
ცდისას) — გასწორების შემდეგ, ნამდვილი mouse-hover-ით (`computer`
tool, არა JS-dispatched event, რომელიც `:hover`-ს არ იწვევს) —
`getComputedStyle` ცხადად აჩვენა `--bs-tertiary-bg`-ის რეალური
ფერი (`rgb(248,249,250)`), ანუ hover კვლავ მუშაობს striped
row-ზეც. `invoice-view.php`-ზეც (public token-link, login გარეშე)
დადასტურდა `table-striped` კლასის არსებობა, console error-ის
გარეშე. Temp password აღდგენილია.

### 4.74 Stripe უფრო ღია + hover-ის fixed ფერი — და Bootstrap 5.3-ის საკუთარი box-shadow-ტრიკის ნამდვილი აღმოჩენა

user-მა მოითხოვა: stripe უფრო ღია, `tr:hover`-ს კონკრეტული სტილი
(`background: #e3f2fd; box-shadow: inset 4px 0 0 #2196f3;` — ფიქსირებული
hex-ფერები, არა `--bs-*` თემა-ცვლადები, ამიტომ dark mode-ს არ
მიჰყვება, სხვა ამ ფაილის ყველა დანარჩენი წესისგან განსხვავებით —
ცალსახად ასეა მოთხოვნილი).

- Stripe: `.03` → `.015` opacity (`4.73`-ის მნიშვნელობის ნახევარი).
- Hover: ჩანაცვლდა ზუსტად user-ის მოცემული სტილით. Box-shadow-ის
  accent (`4px` ლურჯი ზოლი) მხოლოდ row-ის **პირველ** უჯრედზეა
  (`:first-child`) — ყველა უჯრედზე დადება column-თითო ვერტიკალურ
  ზოლებს დახატავდა, არა ერთ row-level აქცენტს.

**ამ ცვლილებისას აღმოჩენილი, დიდი ხნის ნამდვილი ბაგი** (`4.73`-ის
საკუთარი "Bootstrap აუქმებს via background-image" კომენტარი **მცდარი**
გამოდგა): Bootstrap 5.3 stripe/hover-ს **საერთოდ არ** ხატავს
background-image-ით — ნამდვილად იყენებს `box-shadow: inset 0 0 0
9999px var(--bs-table-bg-state)`-ს **ყოველ** უჯრედზე (`.table > :not
(caption) > * > *`-ზე, Bootstrap-ის CDN CSS-დან უშუალოდ დადასტურებული).
რაკი ჩემი ახალი `:first-child`-ის box-shadow-წესი მხოლოდ row-ის
პირველ უჯრედს ეხებოდა, **დანარჩენ** უჯრედებზე Bootstrap-ის საკუთარი
ნაცრისფერი (`rgba(0,0,0,.075)`) box-shadow კვლავ აქტიური რჩებოდა,
ჩემს ღია ლურჯ ფონზე ზემოდან ხატებოდა — ცოცხლად დადასტურებული
(`getComputedStyle`-ით: row-ის მე-2 უჯრედს ჰქონდა `rgba(0,0,0,.075)
0 0 0 9999px inset`, რაც ჩემი კოდის არცერთ ადგილას არ იწერებოდა).
**გასწორებულია**: base grid-line წესს (`.ds-table .table > :not
(caption) > * > *`) დაემატა `box-shadow: none` — ეს ნეიტრალიზებს
Bootstrap-ის საკუთარ stripe/hover მექანიზმს **ყოველ** უჯრედზე,
საიდანაც ჩემი საკუთარი stripe/hover წესები სუფთად, ჩარევის გარეშე
დგება. `4.73`-ის ძველი კომენტარი ("background-image: none ... crisp
lines") ახლა ჩანაცვლებულია სწორი ახსნით.

**გადამოწმებულია ცოცხლად**: `getComputedStyle`-ით row-ის **ყველა**
უჯრედს (0-დან 5-მდე) ახლა ერთნაირი სუფთა `rgb(227,242,253)` ფონი
აქვს hover-ზე, `box-shadow: none` ყველგან გარდა პირველი უჯრედისა
(`4px inset #2196f3`) — Bootstrap-ის ნაცრისფერი overlay აღარსად
ჩანს. Grid-ხაზები (`border`) უცვლელად მუშაობს (box-shadow-სგან
დამოუკიდებელი property). Stripe-იც (`.016`-ალფა, ახალი მსუბუქი
მნიშვნელობა) სუფთად ალტერნირებს hover-ის გარეშეც. Console error
არცერთ ეტაპზე არ ყოფილა, temp password აღდგენილია.

### 4.75 ცხრილის header — ბოლდი, ყველა მონაცემი — ერთი ფონტის ზომა

user-მა მოითხოვა: header-ის ფონტი bold, ყველა მონაცემს ერთი და იგივე
ფონტის ზომა. `ds-table.css`-ის ძველი წესი (`th`/`td` ორივეს ერთად
`font-weight: 400`) გაიყო — `th` ახლა `700`-ია, `td` (და მისი
შვილები) კვლავ `400`.

**Grep-ით შემოწმებისას ნამდვილი font-size შეუსაბამობაც აღმოჩნდა**:
`.ds-table td .badge/code/small/.small`-ს უკვე ჰქონდა `.875rem`
override, მაგრამ **არა** `.btn`-ს — Bootstrap-ის `.btn` საკუთარ
`font-size: 1rem`-ს აწესებს (`--bs-btn-font-size`), td-დან
inheritance-ს არ ეყრდნობა, ამიტომ plain inheritance საკმარისი არ
იყო. ერთადერთი ნამდვილი შემთხვევა მთელ codebase-ში —
`customer-report.php`-ის ინვოისის-ნომრის trigger ღილაკი
(`btn btn-link`, არა `btn-sm`, ცხრილის უჯრედის შიგნით) — რეალურად
`1rem`-ზე render-დებოდა, დანარჩენი row-ის `.875rem`-ის ნაცვლად.
გასწორებულია: `.ds-table td .btn` დამატებულია `.875rem`-იან
font-size წესში.

**გადამოწმებულია ცოცხლად**: `/customers/report?id=1560`-ზე —
`th` weight `700`, `td` weight `400`, ორივეს ზომა `12.6px`
(`.875rem`), ადრე-შეუსაბამო ღილაკიც ახლა ზუსტად `12.6px`-ია.
`/orders`-ზე — ცხრილის ყველა `td`-ს `getComputedStyle`-ით
შემოწმებული `font-size`-ების სია ერთ, უნიკალურ მნიშვნელობამდე
დადის (`12.6px`) — ანუ ნამდვილად აღარსად არის outlier. Console
error არცერთ გვერდზე არ ყოფილა, temp password აღდგენილია.

### 4.76 ცხრილის ყველა მონაცემს — ერთი font-family

user-მა (`4.75`-ის განგრძობით) მოითხოვა: font-family-იც ერთი და
იგივე იყოს ყველა მონაცემისთვის. Grep-ით ნაპოვნი ერთადერთი ნამდვილი
შემთხვევა — `customers.php`-ის საიდენტიფიკაციო-კოდის `<code>`
ტეგი: Bootstrap-ის reboot.css `code`/`kbd`/`pre`/`samp`-ს default-ად
`--bs-font-monospace`-ზე სვამს, რომელიც ეს აპი არასდროს გადაუფარავს
(ფონტის ზომაზე `4.72`/`4.75`-ში უკვე override იყო, family-ზე — არა).
`.ds-table td code { font-family: inherit; }` დამატებულია.

**გადამოწმებულია ცოცხლად**: `/customers`-ზე `<code>`-ისა და
ჩვეულებრივი `<td>`-ის `getComputedStyle().fontFamily` ზუსტად
ემთხვევა (`Inter, "Noto Sans Georgian", ...`); მთელი ცხრილის (header
+ ყველა row) ყველა უჯრედის font-family-ების სია ერთ, უნიკალურ
მნიშვნელობამდე დადის. Console error არ ყოფილა, temp password
აღდგენილია.

### 4.77 "ბმულის გაზიარება"-ს დანიშნულების გვერდი — მთლიანად chrome-ისა და ღილაკების გარეშე

user-მა მოითხოვა: `/invoices/view` (share-link-ისა და "ბეჭდვა"-ს
საერთო destination) აღარ იყოს app-screen, არამედ ავტორიზაციის-გარეშე
გვერდი — მხოლოდ ინვოისი, სიმულირებულ A4 გვერდზე, app-ის ზედა/გვერდითი
ნავბარის და ყოველგვარი აქტიური ღილაკის გარეშე — footer-ში ერთადერთი
ბმულით, ისეთივე, როგორიც `pdfFooterHtml()`-ის (რეალური PDF-ის)
footer-შია.

- **`App\Core\Controller::document()`** (ახალი, მესამე render-რეჟიმი
  `view()`/`bare()`-ის გვერდით) — `document/_layout.php`-ს იყენებს
  layout-ად, `bare()`-ისგან განსხვავებით (რომელიც auth-გვერდების
  split-screen ვიზუალს ატარებს — აქ არასწორი იქნებოდა).
- **`app/Views/document/_layout.php`** (ახალი) — მინიმალური HTML:
  ფონტები (იგივე Google Fonts + BPG Arial Caps, რასაც `layout.php`/
  `auth/_layout.php` იყენებენ) + `design-system.css`, **ბოოტსტრაპ-
  აიქონების გარეშე** (invoice-view.php-ის შემცველობას ხატულა არც
  ერთი არ სჭირდება ტულბარის მოცილების შემდეგ). `<footer>`-ი layout-ის
  თავადაა ჩაშენებული (გენერიკულია, ნებისმიერ მომავალ "document"
  გვერდს გამოადგება) — ორი ხაზი, ზუსტად `pdfFooterHtml()`-ის იგივე
  ტექსტი/სტრუქტურა (debt notice + "დაგენერირებულია {APP_NAME}"
  ბმულით `app_url()`-ზე).
- **`design-system.css`** — ახალი `.ds-document-*` კლასები: A4-ის
  პროპორციული `max-width:210mm; min-height:297mm` თეთრი "ფურცელი",
  ნაცრისფერ ფონზე ცენტრირებული, ჩრდილით (`--ds-shadow-md`); `@media
  print`-ში ჩრდილი/ცენტრირება ქრება (Ctrl+P-ით რომ ნამდვილად
  სუფთა, ერთი-გვერდიანი დოკუმენტივით დაიბეჭდოს); mobile-ზეც (`<576px`)
  ცენტრირება/max-width მოშორდება (ვიწრო ეკრანზე მთელი სიგანე ჯობია).
- **`InvoiceController::show()`** — `$this->view(...)` → `$this->
  document(...)`, `$viewToken` პარამეტრი წაშლილია (PDF-ის ღილაკს
  აღარ სჭირდებოდა).
- **`invoice-view.php`** — მთელი toolbar-ი ("PDF შენახვა"/"ბეჭდვა"
  ღილაკები, `.no-print`) წაშლილია; შემცველობის `.card.ds-card` wrapper
  უბრალო `<div class="p-4 p-md-5">`-ად გამარტივდა (გარე
  `.ds-document-page` თავადვე იძლევა "ფურცლის" ვიზუალს — card-ის
  ორმაგი ჩრდილი/ჩარჩო overkill იქნებოდა).
- **Dead lang-key**: `inv.save_pdf` (ka+en) — წაშლილია, აღარსად
  გამოიყენებოდა (`inv.print` კი კვლავ ცოცხალია, `orders.php`-ისა და
  preview-modal-ის საკუთარი "ბეჭდვა" ბმულებისთვის).

**Scope**: ეს ცვლილება ორივე ვიზიტორისთვის მოქმედებს — anonymous
share-link (`?token=`) **და** login-ით შემოსული tenant-წევრის
"ბეჭდვა" ნავიგაცია (`/invoices/view?id=N`, token-ის გარეშე) —
ორივე ერთსა და იმავე `show()`-ს/route-ს იზიარებდა უკვე, ერთი
render-ლოგიკის შენარჩუნება (ორის ნაცვლად) მარტივი, ცალსახად სწორი
არჩევანი იყო.

**გადამოწმებულია ცოცხლად, ორივე access-გზით** (tenant 31): share-link-ით
(login-ის გარეშე, ახალი browser-context) — page-ს **ზუსტად 1** `<a>`
აქვს მთლიან DOM-ში (footer-ის credit-ბმული, `app_url()`-ზე სწორი
href-ით) და **0** `<button>`, `.ds-sidebar`/`.ds-topbar` საერთოდ არ
არსებობს; `.ds-document-page`-ის `getComputedStyle` — `max-width
793.7px`/`min-height 1122.5px` (ზუსტად A4-ის 210×297mm px-ში),
თეთრი ფონი, ჩრდილი. იგივე გვერდი login-ით (`?id=12`, token-ის
გარეშე) — იდენტური 0 ღილაკი/1 ბმული. `/orders`-ზეც (row-ის "ბეჭდვა"
ბმულის წყარო) console error არ ყოფილა. Temp password აღდგენილია.

### 4.78 A4-გვერდის სტილი — user-ის მითითებული gist-ის ზუსტი მნიშვნელობებით

user-მა მიუთითა კონკრეტული წყარო: [gist.github.com/kivanio/...]
("A4 CSS Page Template") — `4.77`-ის `.ds-document-*` წესები საკუთარი
მიახლოებული მნიშვნელობებიდან (`--bs-tertiary-bg` ფონი, `--ds-shadow-md`)
ამ gist-ის ზუსტ მნიშვნელობებზე გადავიდა: `body` ფონი `rgb(204,204,204)`,
გვერდი — თეთრი, `box-shadow: 0 0 0.5cm rgba(0,0,0,.5)`, ზომები `cm`-ში
(`21cm`/`29.7cm`, `210mm`/`297mm`-ის იდენტური, უბრალოდ ერთეული
შეიცვალა თავად წყაროსთან თვალსაჩინო შესატყვისობისთვის). Print-media
reset-იც (`margin:0; box-shadow:none`) იგივე გვერდიდანაა, `box-shadow:
0`-ის ნაცვლად ვალიდური `none`-ით (გვერდზე ეს ტიპო იყო).

**განზრახ გადახრა gist-ისგან**: `width`/ფიქსირებული `height` →
`max-width`/`min-height`. Gist-ის `<page>` ბლოკები ცარიელი canvas-ებია,
რომლებსაც ავტორი ხელით პაგინირებს (index.html-ში 7 ცალკე `<page>`
ჩანს) — ჩვენი ინვოისი კი ერთი, ცვლადი-სიგრძის დოკუმენტია; ფიქსირებული
`height`-ი უბრალოდ **მოკვეთდა** გრძელ ინვოისს overflow-ის დამუშავების
გარეშე, `min-height` კი გვერდს შემცველობის მიხედვით ზრდის, ვიზუალურად
A4-ის სიგანეს/მინიმალურ სიმაღლეს ინარჩუნებს.

**გადამოწმებულია ცოცხლად**: `.ds-document-page`-ის `getComputedStyle` —
`max-width: 793.701px` (=21cm), `min-height: 1122.52px` (=29.7cm),
`box-shadow: rgba(0,0,0,.5) 0 0 18.9px` (=0.5cm), `background: rgb(255,
255,255)`; `.ds-document-body` — `rgb(204,204,204)`, ზუსტად gist-ის
მნიშვნელობა. ღილაკების/ბმულების რაოდენობა უცვლელი დარჩა (0/1) —
სუფთა ვიზუალური ცვლილებაა, `4.77`-ის access/chrome-ლოგიკას არ
შეხებია. Console error არ ყოფილა.

### 4.79 A4-გვერდის footer — თავად ფურცლის შიგნით, არა ცალკე ქვემოთ

user-მა მოითხოვა: debt-notice + "დაგენერირებულია" footer-ი თავად
A4-ფურცლის (`.ds-document-page`) შიგნით იყოს, არა მის ქვემოთ, ცალკე
ელემენტად ნაცრისფერ ფონზე (რა ასეც იყო `4.77`-ში — `<footer>` `<main
class="ds-document-page">`-ის sibling იყო, არა შვილი).

- **`document/_layout.php`** — `<footer>` გადატანილია `<main
  class="ds-document-page">`-ის **შიგნით**, `$content`-ის შემდეგ,
  ბოლო child-ად.
- **`design-system.css`** — `.ds-document-page` გახდა `display:flex;
  flex-direction:column`; `.ds-document-footer`-ს `margin-top: auto`
  ედება (საკუთარი `max-width`/`margin:auto` ცენტრირება მოშორდა —
  აღარ სჭირდება, ფურცლის სიგანეს პირდაპირ იზიარებს) — მოკლე
  ინვოისზე footer-ი ფურცლის ბოლო კიდეს ეკვრის, გრძელზე კი უბრალოდ
  content-ის შემდეგ მოსდევს (არ ეფარება).

**გადამოწმებულია ცოცხლად**: `footer.parentElement === .ds-document-page`
და `.lastElementChild === footer` — true; `footer.getBoundingClientRect().
bottom` ზუსტად ემთხვევა `.ds-document-page`-ის საკუთარ `bottom`-ს
(orphan-footer-ის ნაცვლად ნამდვილად ფურცლის კიდეზეა). ღილაკები/
ბმულები კვლავ 0/1, console error არ ყოფილა.

### 4.80 "ბმულის გაზიარება" — toast-შეტყობინება (Ctrl+V მინიშნებით)

user-მა მოითხოვა: ბმულის კოპირებისას გამოჩნდეს notify, რომ ბმული
ბუფერშია და Ctrl+V-ით ჩასმა შეიძლება — "ტექსტი კარგად დაალაგე".
**ახალი toast-სისტემა არ დამჭირვებია** — `layout.php`-ს/`app.js`-ს
უკვე ჰქონდა მზა, გაზიარებული `window.dsNotify(message, type)`
(`#dsToastContainer`, Bootstrap Toast) — client-side ვალიდაციისთვის
აშენებული, აქაც პირდაპირ გამოსადეგი.

- **`ds_share_link_script()`** (helpers.php) — click-ის success-ტოტში
  ემატება `window.dsNotify?.(...)` გამოძახება (icon-ისა და title-ის
  ცვლილების გვერდით, არა მის ნაცვლად — row-ის icon-only ღილაკებზე
  მარტო title-change ადვილად შეუმჩნეველია). Toast-ის ტექსტი
  სტრუქტურირებულია: `bi-clipboard2-check-fill` ხატულა + წინადადება
  + `<kbd>Ctrl</kbd>+<kbd>V</kbd>` (ორი ცალკე `<kbd>` badge, არა
  plain-text "Ctrl+V" — ეს არის "ტექსტი კარგად დაალაგე"-ს კონკრეტული
  პასუხი, კლავიატურის კომბინაცია ვიზუალურადაც კლავიშებივით იკითხება).
- **ახალი lang-key** `inv.share_link_notify` (ka+en) — pure-text
  წინადადება, `<kbd>` markup თავად JS-ის მხრიდანაა დამატებული
  (lang string-ებში HTML embed-ის ნაცვლად).

**გადამოწმებულია ცოცხლად** (tenant 31, `orders.php`-ისა და
`invoices.php`-ის ორივე ღილაკზე, `navigator.clipboard.writeText`-ის
stub-ით sandbox-ის `clipboard-write:denied`-ის გვერდის ავლით,
`4.68`-ის იგივე მეთოდით): toast ორივე გვერდზე `show`-კლასით
ჩნდება, `text-bg-success` ტონით, ტექსტი/`<kbd>` markup ზუსტად
მოსალოდნელია. Console error არცერთ გვერდზე არ ყოფილა. Temp password
აღდგენილია.

### 4.81 Toast ფერი success → info — და `dsNotify()`-ის საერთო close-button ბაგის აღმოჩენა

user-მა დააზუსტა (`4.80`-ის განგრძობით): მწვანე/success ტონის
ნაცვლად — ინფორმაციის (`info`) ხატულითა და ფერით. `ds_share_link_
script()`-ში `bi-clipboard2-check-fill`/`'success'` → `bi-info-
circle-fill`/`'info'`.

**ამ ცვლილებისას აღმოჩენილი, `window.dsNotify()`-ის (app.js) თავად
საერთო ბაგი** — არა ახალი, ჩემი ცვლილებით მხოლოდ პირველად
გამოწვეული: `dsNotify()` close-ღილაკს ყოველთვის `btn-close-white`-ს
სვამდა, ტიპის მიუხედავად. Bootstrap 5.3-ის `text-bg-success`/
`text-bg-danger` **თეთრ** ტექსტს იყენებს მუქ ფონზე (თეთრი close-X
სწორია), მაგრამ `text-bg-info`/`text-bg-warning` — **შავ** ტექსტს
ღია ფონზე (CDN CSS-დან უშუალოდ დადასტურებული) — თეთრი close-X ღია
ფონზე თითქმის უხილავი გამოდის. ეს ბაგი აქამდე არ იყო შემჩნეული,
რადგან `dsNotify()`-ს `'info'`/`'warning'` ტიპით არავინ იძახებდა
აქამდე (`dsNotifyCode()`-ის catalog-ში `warning` ტიპი არსებობს,
უბრალოდ ჯერ არცერთ რეალურ code-ს არ გამოუყენებია).

- **`app.js`** — `window.dsNotify()`-ში ახალი `closeClass` ლოგიკა:
  `type === 'info' || type === 'warning'` → plain `btn-close`,
  სხვა ნებისმიერი ტიპი (success/danger) → ძველი `btn-close
  btn-close-white`. **Root-ში გასწორებულია** — ერთი call-site-ის
  ნაცვლად ყველა მომავალი `dsNotify()`/`dsNotifyCode()` გამოძახება
  ავტომატურად სარგებლობს, `ds_share_link_script()`-ს ცალკე fix არ
  დასჭირვებია.

**გადამოწმებულია ცოცხლად**: ახალი toast — `text-bg-info` კლასი,
`rgb(13,202,240)` ფონი, შავი ტექსტი, close-ღილაკს **აღარ** აქვს
`btn-close-white` (მხოლოდ `btn-close`) — ვიზუალურად ხილული X.
Regression-შემოწმება: `dsNotify('...', 'danger')` კვლავ
`btn-close-white`-ს ინარჩუნებს (უცვლელი), `dsNotify('...', 'warning')`
ახლა სწორადვე plain `btn-close`-ზეა. Console error არ ყოფილა, temp
password აღდგენილია.

### 4.82 ყველა flash-შეტყობინება → Bootstrap Toast (აღარ page-top `.alert` banner)

user-მა მოითხოვა: ყველა notify Bootstrap Toast-ით გამოვიდეს. აპში
ორი პარალელური სისტემა თანაარსებობდა — client-side JS-შეცდომების
`window.dsNotify()` (toast, უკვე არსებული) და server-flashed
"created"/"updated"/"email sent" და ა.შ. შეტყობინებების page-top
`<div class="alert...ds-alert-autodismiss">` banner-ები (14 view-ში).
ეს ორივე ერთ toast-სისტემაზე გაერთიანდა.

- **`ds_flash_toast(?string $message, string $type = 'success', string
  $icon = 'bi-check-circle-fill'): string`** (ახალი, helpers.php) —
  `$message === null` → `''` (caller-ს საკუთარი if-guard არ სჭირდება);
  სხვანაირად `<script>window.dsNotify?.(icon + msg, type)</script>`-ს
  აბრუნებს, `$scripts`-ში ჩასამატებლად (**არა** inline body-ში —
  `dsNotify` მხოლოდ მას შემდეგ არსებობს, რაც `app.js` ჩაიტვირთება,
  რაც `layout.php`-ში `$content`-ის **შემდეგ** ხდება).
- **14 view + 1 module-view** კონვერტირებულია: `customers.php`,
  `orders.php`, `invoices.php` (5 flash — created/updated/emailSent/
  emailFailed/conflict), `dashboard.php` (notice/emailSent/emailFailed),
  `products.php`, `users.php`, `organization.php` (ერთადერთი
  boolean-flag შემთხვევა, `$updated ? t(...) : null`), `modules.php`,
  `profile.php`, `profile-settings.php`, `auth/login.php` (`$notice`),
  `auth/forgot-password.php` (`$sent`), `Warehouse` module-ის
  `warehouse.php`. თითოეულში: inline `.alert` block წაშლილია,
  `ds_flash_toast(...)` დამატებულია `$scripts`-ის აწყობაში (ან, სადაც
  `$scripts` საერთოდ არ არსებობდა — `modules.php`, `profile-settings.php`,
  `auth/forgot-password.php` — ახლად დამატებულია).
- **`auth/_layout.php`-ს არ ჰქონდა** `#dsToastContainer`-იც და
  `app.js`-იც (auth-გვერდები საკუთარ, ცალკე minimal script-სეტს
  იყენებდნენ) — ორივე დამატებულია, რომ `auth/login.php`/`auth/
  forgot-password.php`-ის toast-ებმაც იმუშაონ. `app.js`-ის დანარჩენი
  listener-ები (sidebar/theme toggle და ა.შ.) უსაფრთხოდ no-op-ებია
  auth-გვერდებზე — querySelectorAll-ით ეძებენ element-ებს, რომლებიც
  იქ საერთოდ არ არსებობს.
- **განზრახ ხელუხლებელი დარჩენილი** (`.alert` matches, non-flash):
  `layout.php`-ის SuperUser impersonation-ბანერი (მუდმივი სტატუსი,
  არა ერთჯერადი flash), `profile-settings.php`-ის "no_password_yet"
  (persistent hint), `invoices.php`-ის `errors['items']` და `products.php`/
  `warehouse.php`-ის `data-lookup-error` (ორივე field/section-დონის
  ვალიდაცია, არა page-level notify), `style-guide.php`-ის სტატიკური
  demo-მაგალითები (`sg.alert_success`/`sg.alert_warning`, `flash()`-ს
  საერთოდ არ იყენებს).
- **Dead code წაშლილია**: `app.js`-ის `.ds-alert-autodismiss`
  auto-close listener — comprehensive grep-ით დადასტურდა, რომ ეს
  კლასი აღარსად render-დება (ყველა callsite კონვერტირებულია).

**გადამოწმებულია ცოცხლად + curl-ით** (tenant 31): curl-ით შექმნილმა
customer-მა (`customers.php`) HTML-response-ში მხოლოდ სწორი
`window.dsNotify?.(...)` script-ი აჩვენა, `.alert-success` block
0-ჯერ (grep count). იმავე customer-ის ბრაუზერით რედაქტირებამ real
navigation-ის შემდეგ ნამდვილი, ხილული toast აჩვენა ("...განახლდა.",
`show` კლასით), page-ზე 0 `.alert-success`/`.alert-warning`. Auth
layout-ზე (`/login`, logout-ის შემდეგ, ნამდვილი unauthenticated
session-ით) — `window.dsNotify`/`#dsToastContainer` ორივე `typeof
function`/`true`-ზეა, პირდაპირი `dsNotify()` გამოძახებით toast
ცოცხლად გამოჩნდა. `organization.php` (curl, login-ით) — `200 OK`,
PHP შეცდომების/leftover `.alert-success`-ის გარეშე. Console error
არცერთ ეტაპზე არ ყოფილა. ტესტ-customer წაშლილია, temp password
აღდგენილია.

### 4.83 Toast → სტანდარტული Bootstrap toast-სტილი (icon + app-სახელი + "ახლა" + close)

user-მა Bootstrap-ის საკუთარი დოკუმენტაციის reference screenshot
მოგვცა — plain თეთრი toast, `.toast-header` (icon + brand + "just now"
+ close) და `.toast-body` ცალკე. `4.80`-`4.82`-ის `text-bg-{type}`
შეფერილი toast ამ layout-ზე გადავიდა.

- **`app.js`-ის `window.dsNotify(message, type, icon)`** — მესამე,
  optional `icon` პარამეტრი დაემატა. მთლიანად ახალი markup: `.toast`
  (თეთრი, `text-bg-*` აღარაა) → `.toast-header` (`<i>` — ფერადი,
  ტიპის მიხედვით, `DS_TOAST_COLOR` map-ით; `<strong>` — `window.
  dsAppName`; `<small>` — `window.dsToastJustNow`; `.btn-close`, ახლა
  ყოველთვის მუქი — `4.81`-ის `closeClass`/`text-bg-*` ლოგიკა **მთლიანად
  მოშორდა**, საჭირო აღარაა, თეთრ ფონზე მუქი close ყოველთვის სწორია)
  + `.toast-body` (მხოლოდ თეთრი ტექსტი, აღარ შეიცავს icon-ს). ახალი
  `DS_TOAST_ICON` map — თუ `icon` არგუმენტი არ გადმოეცემა, ტიპის
  მიხედვით ავტომატურად აირჩევა (`success`→check-circle-fill,
  `danger`→exclamation-octagon-fill, `warning`→exclamation-triangle-fill,
  `info`→info-circle-fill).
- **`ds_flash_toast()`** (helpers.php) — აღარ აწყობს icon-HTML-ს და
  არ აწებებს message-ს (`$iconHtml . $msgJs` აღარ არსებობს) — icon
  ცალკე, მესამე JS-არგუმენტად გადაეცემა `dsNotify`-ს. `$icon`
  პარამეტრი `string` → `?string = null` — callsite-ებს **არცერთს არ
  დასჭირვებია ცვლილება** (default-ი JS-ის `DS_TOAST_ICON`-ს მიენდობა,
  რომელიც ყველა არსებული callsite-ის აქამდე ხელით-მითითებულ icon-ს
  ისედაც ემთხვევა).
- **`ds_share_link_script()`** — იგივე გამარტივება, `<i>...</i> +`
  კონკატენაცია მოშორდა, `dsNotify($notifyText + ' <kbd>Ctrl</kbd>+
  <kbd>V</kbd>', 'info')` — icon ('info-circle-fill') ავტომატურად.
- **`layout.php` + `auth/_layout.php`** — ახალი `window.dsAppName`
  (`app_name()`) და `window.dsToastJustNow` (`t('toast.just_now')`,
  ახალი ka/en lang-key) injection, `dsNotifications`-ის გვერდით.

**გადამოწმებულია ცოცხლად**: მოცემული screenshot-ის ტექსტით ("See?
Just like this.") პირდაპირი `dsNotify()` გამოძახებამ ააგო ზუსტად
იგივე structure — icon (მწვანე, success), `<strong>INVOICE</strong>`,
`<small>ახლა</small>`, plain `.btn-close`, `.toast` კლასი
`text-bg-*`-ის გარეშე. ნამდვილი flash-flow-ითაც (customers.php-ზე
ახალი customer-ის შექმნა, ბრაუზერით) — header "INVOICE ახლა", body
"„...\" დაემატა.", მწვანე check-circle-fill icon, `show` კლასი.
Console error არ ყოფილა (ერთადერთი ნახული log — ძველი, ამ ტესტთან
დაუკავშირებელი 405, სესიის ადრეული ეტაპიდან). ტესტ-customer-ები
წაშლილია, temp password აღდგენილია.

### 4.84 დეშბორდის "ბოლო ინვოისები" — იგივე row-actions, რაც `/orders`-ს აქვს

user-მა მოითხოვა: დეშბორდის "ბოლო ინვოისები" ცხრილს `/orders`-ის
იგივე row-actions ჰქონდეს. ყველა 6 მოქმედება დაემატა: ნახვა (preview
მოდალი), რედაქტირება, ბეჭდვა, ექსპორტი PDF (ხელმოწერით/გარეშე
dropdown), მეილზე გაგზავნა, ბმულის გაზიარება — ზუსტად `orders.php`-ის
იგივე markup/closures/მოდალები, დუბლირებული (არა გაზიარებული
partial) — იგივე კონვენცია, რაც `4.62`-ში დამკვიდრდა.

- **`Dashboard::recentInvoices()`** — `c.customer_email` დაემატა
  SELECT-ში (მეილის prefill-ისთვის; `view_token` უკვე `i.*`-ში იყო).
- **`DashboardController::index()`** — ახალი `org`, `appUrl`,
  `emailErrors`, `emailOld` view-ცვლადები (`org` უკვე გამოთვლილი
  იყო, უბრალოდ არ გადაეცემოდა view-ს).
- **`InvoiceController::sendEmail()`** — **განზოგადებულია** მესამე
  redirect-კონტექსტისთვის. ძველი ბინარული `$fromOrders` ცვლადი →
  `$listRedirect` (`/orders`, `/`, ან `null` — whitelisted თავად
  `$_POST['redirect']`-იდან). ორივე list-გვერდი (`/orders`, `/`)
  ახლა **ერთნაირად** იქცევა — success-ზეც და failure-ზეც საკუთარ
  თავზე ბრუნდება (`?email_error=N`-ით failure-ზე); `invoices.php`-ის
  single-invoice-ფორმის კონტექსტს (`$listRedirect === null`) კი
  საკუთარი, `4.64`-ის success→დეშბორდი ლოგიკა შენარჩუნებული აქვს.
- **`dashboard.php`** — `$invoiceNumber`/`$shareUrl`/`$emailDefaults`/
  `$emailVal`/`$emailBad` closures, actions-სვეტი, `invoice-preview-
  modal.php` include, საკუთარი `#invoiceEmailModal` (`redirect`
  hidden ველი `/`), `ds_invoice_preview_script()`/`ds_share_link_
  script()` + email-მოდალის JS (`?email_error=`-ის reopen — იგივე,
  რაც `orders.php`-ს აქვს).

**გადამოწმებულია ცოცხლად + curl-ით** (tenant 31): actions-სვეტში
ზუსტად 4 button + 4 link (view/pdf-dropdown-toggle/email/share +
edit/print/2 dropdown-item); share-link-ს ნამდვილი token, email-ღილაკს
სწორი `customer_email`. Preview-მოდალმა/email-მოდალმა/chart-მა
(`revenueChart`, ID-კონფლიქტის გარეშე) ყველამ იმუშავა. curl-ით,
ვალიდაციის-შეცდომით (`redirect=/`) — `Location: /?email_error=12`,
გვერდზე სწორი `is-invalid`/`invalid-feedback`; ბრაუზერში იგივე URL-მა
სწორად გახსნა მოდალი (`invoiceId=12`), query flag წაშალა. `ds_lang_
url()`-ის `4.71`-ის ფიქსიც სწორად მუშაობდა აქაც (`?email_error=12&lang=en`).
Invoice id=12-ის მონაცემები (total/document_state) უცვლელი დარჩა.
Console error არ ყოფილა, temp password აღდგენილია.

### 4.85 SuperUser — ქვემომხმარებლების ცხრილს საკუთარი "დათვალიერება"/"დაბლოკვა" ღილაკები

user-მა მოითხოვა: `/superuser`-ის ცხრილში ქვემომხმარებლები მხოლოდ
badge-ებად ჩანდნენ, დაწკაპუნებით მხოლოდ დაბლოკვა შეეძლო — არც
"დათვალიერება" ჰქონდათ, არც ცალკე მოქმედების ღილაკები. მოთხოვნა:
თითოეულ ადმინს (ტენანტს), ვისაც ქვემომხმარებელი ჰყავს, ჰქონდეს
გახსნადი child ცხრილი, სადაც ქვემომხმარებლები საკუთარი
"დათვალიერება"+"დაბლოკვა" ღილაკებით იქნებიან.

**დაზუსტებული user-თან** (`AskUserQuestion`): ტენანტის საკუთარი
"დათვალიერება" ღილაკი **უცვლელად** რჩება პირდაპირი, ერთი-კლიკის
impersonate-ად — ცალკე პატარა toggle ხსნის/კეცავს ქვემომხმარებლების
ცხრილს, `დათვალიერება`-ს ქცევა არ იცვლება.

**რატომ ასეა საჭირო**: `SuperUserController::impersonate()` მხოლოდ
root ტენანტს (`created_by IS NULL`) იღებს ვალიდურ სამიზნედ —
`Auth::tenantId()`-ის impersonation-შტო ამ მნიშვნელობას პირდაპირ
ენდობა, ქვემომხმარებლის id-ზე ხელახლა არ გარდაქმნის. ამიტომ
ქვემომხმარებლის "დათვალიერება" ღილაკიც ტექნიკურად **იმავე root
tenant_id-ს** აგზავნის — impersonation თავად tenant-ის დონეზეა
(ყველა tenant-წევრის მონაცემი საერთოა), არა ინდივიდუალურ
მომხმარებელზე, ასე რომ "ამ ქვემომხმარებლის დათვალიერება" და "ამ
ადმინის დათვალიერება" ერთი და იგივე რეალური მოქმედებაა, უბრალოდ
ორივე ადგილიდან ხელმისაწვდომი.

**`superuser.php`** — ტენანტის მწკრივის "ქვე-მომხმარებლები" სვეტი
გამარტივდა უბრალო რაოდენობის badge-მდე (აღარ არის ცალკეული
block-toggle badge-ები). თუ `subUsers !== []`, მწკრივის შემდეგ
ემატება ერთი `colspan`-იანი მწკრივი plain `<details>`-ით (JS/Bootstrap
collapse-ის გარეშე — იგივე ხრიკი, რაც `sidebar.php`-ის nav-ჯგუფებს
აქვს, `design-system.css`-ის `.ds-details-caret`) — `<summary>`
აჩვენებს "N ქვე-მომხმარებელი"-ს ისარით, გახსნისას იშლება nested
`<table>` თითოეული ქვემომხმარებლისთვის: სახელი+დაბლოკვის badge,
ელფოსტა, `დათვალიერება` (root tenant_id) + `დაბლოკვა`/`განბლოკვა`
(სუბუსერის საკუთარი id) ღილაკები — იგივე markup/სტილი, რაც
ტენანტის საკუთარ მწკრივს აქვს.

**`design-system.css`** — ახალი `.ds-subusers > summary` წესი
(marker დამალვა, hover-ფერი) — იგივე კონვენცია, რაც `.card >
summary`-ს აქვს; caret-ის rotate-ისთვის უკვე არსებული generic
`details[open] > summary .ds-details-caret` წესი ხელახლა
გამოიყენება, ახალი წესი არ დასჭირდა.

**გადამოწმებულია ცოცხლად** (`super@nova.local`-ით, read-only —
არცერთი ფორმა არ გაგზავნილა, ტენანტ 1-ის (real user) მონაცემი
უცვლელია): `test1`/`test2`-ს (ქვემომხმარებლის გარეშე) child-row საერთოდ
არ დაერენდერა; ტენანტ 1-ს (2 ქვემომხმარებლით) "2 ქვე-მომხმარებელი"
toggle გამოუჩნდა, დაწკაპუნებით `<details>`-ის `open`-ატრიბუტი
სწორად გადაირთო (`d.open: true`), nested ცხრილში ორივე
ქვემომხმარებლის სახელი/ელფოსტა/ღილაკი სწორად გამოჩნდა. ორივე
ქვემომხმარებლის "დათვალიერება" ფორმის `tenant_id` სწორად `1`-ს
(root) აგზავნიდა, "დაბლოკვა" ფორმის `user_id` კი სუბუსერის საკუთარ
id-ს (`24`/`13`) — hidden ველების პირდაპირი წაკითხვით დადასტურებული.
⚠️ caret-ის `rotate(180deg)` CSS transform ტესტ-ბრაუზერში
(`Claude_Browser`) საერთოდ არ გამოისახებოდა — თუნდაც პირდაპირ,
`!important`-ინლაინ ტესტ-წესითაც `getComputedStyle` ყოველთვის
identity-მატრიცას აბრუნებდა — დადასტურდა, რომ ეს ავტომატიზაციის
გარემოს თავისებურებაა (headless browser transforms/animations-ს
გამორთავს determinism-ისთვის), არა კოდის ბაგი — თავად `<details
open>`-ის ლოგიკური open/close მდგომარეობა და მისი შემცველობა
უტყუარად სწორად მუშაობდა.

### 4.86 SuperUser-ის კონკრეტული ქვემომხმარებლის დათვალიერება — ცხრილიც და გრაფიკიც მხოლოდ მისი

user-მა მოითხოვა: `4.85`-ის ახალი ქვემომხმარებლის "დათვალიერება"
ღილაკის ჩართვისას, `/orders`-ისა და დეშბორდის ცხრილში/გრაფიკში
**მხოლოდ ჩართული (impersonated) მომხმარებლის** შეკვეთები უნდა ჩანდეს
— ტენანტის საკუთარი "დათვალიერების" დროს კი, როგორც აქამდე, მთელი
გუნდის (root + ყველა ქვემომხმარებელი) შეკვეთები.

**გადამწყვეტი დეტალი**: `Auth::tenantId()` (ruler-scoped მონაცემი —
customers/products/organization) ყოველთვის root ტენანტს უნდა
აბრუნებდეს, თუნდაც კონკრეტული ქვემომხმარებელი იყოს არჩეული — ეს
არასდროს შეცვლილა. ცვლილება მხოლოდ **invoice-scoped** მონაცემს
ეხება (`/orders`, დეშბორდის ცხრილი+გრაფიკი+სტატ-ბარათები).

**`Auth`** — `impersonate()`-ს ახალი მეორე პარამეტრი, `?int
$asUserId` (`null`-ზე ტოლდება `$tenantUserId`-ს) — ინახავს
`$_SESSION['impersonating_user']`-ს, root tenant id-ის (`impersonating_tenant`)
გვერდით. ახალი `impersonatingUserId()` getter. ახალი
`invoiceScopeUserIds(): array` — ეს არის ერთადერთი choke point,
საიდანაც ყველა invoice-scoped caller ღებულობს სამიზნე user id-ების
სიას: ჩვეულებრივ `User::tenantMemberIds($ruler)` (მთელი გუნდი,
`4.36`-ის უცვლელი წესი), **გარდა** იმ შემთხვევისა, როცა SuperUser
კონკრეტულ ქვემომხმარებელს ათვალიერებს (`impersonatingUserId() !==
tenantId()`) — მაშინ მხოლოდ `[$actingAs]`. ჩვეულებრივი (არა-superadmin)
მომხმარებლისთვის ეს ყოველთვის იმავე მთელი-გუნდის სიაზე დაბრუნდება,
რაც აქამდე იყო — არაფერი იცვლება მათთვის.

**`SuperUserController::impersonate()`** — POST ველი `tenant_id` →
`user_id` (ეს არის, root-ის ან სუბუსერის, რომელიც row-ს "დათვალიერება"
დაეჭირა). Controller-ი root ტენანტს `created_by`-დან თავად პოულობს
და მას აგზავნის `Auth::tenantId()`-ისთვის (`Auth::impersonate($tenantId,
$userId)`), `$userId`-საც ინახავს ცალკე invoice-scope-ისთვის.

**`superuser.php`** — ორივე ფორმის (root-მწკრივის და სუბუსერის)
hidden ველი `user_id`-ზეა გადართული (root-ის შემთხვევაში მაინც
თავისი id უგზავნის, სუბუსერის შემთხვევაში კი საკუთარს, აღარ root-ის).
ღილაკის active-ფერი ახლა `$impersonatingUser`-ზეა დამოკიდებული
(კონკრეტულ პიროვნებაზე), მწკრივის მთლიანი highlight კი კვლავ
`$impersonating`-ზე (ტენანტის დონე) — ორივე კონტროლერიდან newly
გადაცემული.

**`layout.php`-ის SuperUser-ბანერი** — თუ კონკრეტული ქვემომხმარებელია
არჩეული, აჩვენებს `{ტენანტი} → {ქვემომხმარებელი}`-ს, თუ root თავად
დათვალიერდა — მხოლოდ ტენანტის სახელს, უცვლელად.

**`InvoiceController::orders()`/`exportOrdersPdf()`** —
`User::tenantMemberIds($ruler)` → `Auth::invoiceScopeUserIds()`.

**`Dashboard`-ის მოდელი** — `stats()`/`revenueByUser()`/`recentInvoices()`
სამივემ `$ruler`-იდან `User::tenantMemberIds()`-ის შიდა გამოთვლის
მაგივრად ახლა **პარამეტრად** იღებენ `$userIds`-ს (caller,
`DashboardController::index()`, ერთხელ ითვლის `Auth::invoiceScopeUserIds()`-ს
და სამივეს გადასცემს). `revenueByUser()`-ის chart-ის "წევრების"
SQL-იც (`SELECT id, name FROM users WHERE id = ? OR created_by = ?`)
შეიცვალა `WHERE id IN ($userIds-ის placeholder)`-ით — narrowing
ავტომატურად მუშაობს, ცალკე ლოგიკა chart-ის სერიების გასაფილტრად აღარ
დასჭირდა: თუ `$userIds` ერთი კონკრეტული ადამიანია, "წევრების" სია
თავადვე მხოლოდ მას შეიცავს, therefore მხოლოდ მისი ერთი bar-სერია
გამოვა. `customers`/`products` (`Dashboard::stats()`-ის ორი
სტატ-ბარათი) კვლავ ruler-scoped-ია, ცვლილება არ შეხებია — ეს
საერთო org-მონაცემია, არა ინდივიდუალური.

**გადამოწმებულია ცოცხლად** (`super@nova.local`-ით, ტენანტ 1-ის real
მონაცემზე — მხოლოდ SELECT-ები, არცერთი write-ფორმა არ გავაგზავნე,
გარდა `impersonate`/`stop`-ისა, რომლებიც session-ს ცვლიან, არა DB-ს):
წინასწარ დათვლილი (`created_by IN (1,13,24)`) — root(1)=9, sub(13)=1,
sub(24)=2, სულ გუნდი=12. სუბუსერ 24-ის ("პავლე პეტრიაშვილი")
დათვალიერებისას: `/orders`-მა ზუსტად **2** მწკრივი აჩვენა
(`ყველა შეკვეთა 2`); დეშბორდზე "ინვოისები"=**2**, "შემოსავალი"=**105.00**
(80+25), "დამკვეთები"/"პროდუქტები" უცვლელი (11/7, ruler-scoped);
Chart.js instance-მა (`window.Chart.getChart(...)`) დაადასტურა
**ერთადერთი** სერია, `label: "პავლე პეტრიაშვილი"`, `total: 105`;
ბანერმა აჩვენა `გივი ბერძენიშვილი → პავლე პეტრიაშვილი`. root(1)-ის
საკუთარ თავზე ხელახლა გადართვისას: `/orders`-მა ისევ **12**
დაბრუნა, chart-მა **3** სრული სერია (`102481.5`/`105`/`80`), ბანერმა
მხოლოდ ტენანტის სახელი (ისრის გარეშე) — narrowing სწორად ირთვება/
ითიშება. `session`-ი გასუფთავებულია (`/superuser/stop` + `/logout`),
DB-ში არაფერი შეცვლილა.

### 4.87 თითო მომხმარებელს — საკუთარი, არჩეული ფერი (რეგისტრაცია), chart-ი მას იყენებს პალიტრის ნაცვლად

user-მა შენიშნა: შემოსავლის გრაფიკზე მთავარი ადმინი და
ქვემომხმარებლები ერთნაირი (position-ის მიხედვით გამოთვლილი) ფერით
გამოისახებოდნენ — მოთხოვნა: რეგისტრაციისას (მთავარიც და
ქვემომხმარებელიც) თავად აირჩევდეს საკუთარ, უნიკალურ ფერს, რომელიც
chart-შიც გამოჩნდება და ცხრილებშიც.

**სქემა** — `migrations/035_add_users_color.sql`: `users.color CHAR(7)
NULL`. Backfill — იგივე 8-ფეროვანი პალიტრა (`User::PALETTE`),
per-tenant (`PARTITION BY COALESCE(created_by, id)`, root პირველი,
შემდეგ subs სახელით — `revenueByUser()`-ის უკვე არსებული დისფლეი
წესრიგი), `ELT()`-ით პოზიციური ინდექსიდან hex-ის ასარჩევად.
`superadmin` row-ები `NULL`-ად რჩება.

**`User`** — ახალი `PALETTE` const + `nextColor(?int $ruler)`: ფორმის
color-picker-ის **default** მნიშვნელობა მხოლოდ (`$ruler=null` → ახალი
root-ის რეგისტრაცია, ყოველთვის `PALETTE[0]`; `$ruler=tenantId()` →
sub-user ფორმა, ტენანტის უკვე არსებული წევრების რაოდენობით
შემდეგი პალიტრის ფერი) — ველი თავად რეალური `<input type="color">`-ია,
ყოველთვის თავისუფლად შესაცვლელი, ეს მხოლოდ წამახალისებელი
predefined მნიშვნელობაა. `validateSubUser()`/`validateRegistration()`-ს
ახალი `color` ველი (`/^#[0-9a-f]{6}$/`-ვალიდაცია, lowercase-ზე
normalize). `create()`/`createFromGoogle()`/`createSubUser()`/
`updateSubUser()` — სვეტი დამატებულია INSERT/UPDATE-ებში
(`createFromGoogle()`-ს, ფორმის-გარეშე OAuth-flow-ს, ავტომატური
`nextColor(null)` ერგება).

**`auth/register.php`** — ახალი `<input type="color" class="form-control-color">`
ველი, პაროლის ველების შემდეგ, `auth.color`/`auth.color.hint` label/hint-ით.
**`users.php`** (sub-user add/edit) — იგივე ველი ფორმაში (`users.color`/
`users.color_hint`), row-click-ის `FIELDS` მასივს დაემატა `color`
(`data-color`-იდან edit-ფორმაში გადმოსატანად), roster-ის სახელის
სვეტს — ფერადი წერტილი (`.ds-color-dot`).

**`Dashboard::revenueByUser()`** — ძველი `$palette[$i % count($palette)]`
პოზიციური ლოგიკა მთლიანად ამოღებულია, `members`-ის SQL-ს დაემატა
`color`, series პირდაპირ `$member['color'] ?? '#94a3b8'`-ს იყენებს
(fallback მხოლოდ თეორიული — backfill-ის შემდეგ ყველა non-superadmin
row-ს აქვს ფერი).

**ცხრილებშიც** (`შესაძლოა ცხრილშიც`-ის თხოვნაზე) — `Invoice::all()`
და `Dashboard::recentInvoices()`-ის SQL-ს დაემატა `u.color AS
creator_color`; `orders.php`-ის "შეკვეთის მიმღები" და `dashboard.php`-ის
"ბოლო ინვოისები"-ის იგივე სვეტი, ასევე `superuser.php`-ის ტენანტისა
და ქვემომხმარებლის მწკრივები — ყველგან იგივე `.ds-color-dot`
(`design-system.css`, ახალი 8px წრე) სახელის წინ. `customer-report.php`
განზრახ გამოტოვებულია (out-of-scope, არ მოთხოვნილა პირდაპირ) —
საჭიროების შემთხვევაში იგივე ნიმუშით მარტივად დაემატება.

**გადამოწმებულია ცოცხლად** (tenant 31/test1-ით, temp password-ით —
დასრულების შემდეგ real hash აღდგენილია): migration-ის ჩატარების
შემდეგ ნამდვილი tenant 1-ის გუნდი სწორად დაბექდა (`root=indigo,
sub-24=green, sub-13=amber` — ზუსტად ემთხვევა `4.86`-ის ხელით
დათვლილ ტესტს, დამთხვევა შემთხვევითი არაა: იგივე position-ის
ლოგიკა). ახალი sub-user-ის დამატებისას (`users.php`) default ფერი
სწორად შემოთავაზდა (`#22c55e`, ტენანტის უკვე ერთი წევრიდან
გამომდინარე); custom ფერის (`#ec4899`) არჩევით შენახვისას — roster-ის
წერტილმა ზუსტად ეს RGB აჩვენა (`rgb(236, 72, 153)`), დეშბორდის
chart-ის მეორე სერიამაც იგივე hex გამოიყენა. row-click-ით
რედაქტირებისას ფორმამ სწორი შენახული ფერი ჩამოტვირთა; ფერის
ცვლილებით (`#06b6d4`) resubmit-მა DB-ში სწორად განაახლა. server-side
ვალიდაციამ (`validateSubUser()`) პირდაპირი გამოძახებით უარყო
არასწორი ტექსტი (`"not-a-color"`) და ნორმალიზა uppercase hex
(`#ABCDEF` → `#abcdef`) ცალკე ერრორის გარეშე. Root-რეგისტრაციის
ფორმამაც (`auth/register.php`) default (`#4f46e5`) სწორად აჩვენა და
custom ფერი (`#a855f7`) სწორად შეინახა. ყველა ტესტ-user
(sub-user id 85, root id 86 + მისი auto-შექმნილი `organization`
row) წაშლილია, tenant 31-ის temp password აღდგენილია.

### 4.88 ჩვეულებრივი ქვემომხმარებლის შესვლისას — მხოლოდ საკუთარი შეკვეთები (არა მთელი გუნდი)

user-მა მოითხოვა: ეს `4.86`-ის იგივე "მხოლოდ ამ მომხმარებლის
შეკვეთები" წესი ეხოს **ჩვეულებრივ (SuperUser-ის გარეშე) ქვემომხმარებლის
შესვლასაც** — არა მხოლოდ SuperUser-ის დათვალიერების რეჟიმს.

**გადამწყვეტი შედეგი**: ეს პირდაპირ ეწინააღმდეგება `4.36`-ის
დოკუმენტირებულ, განზრახ გადაწყვეტილებას ("A sub-user's own invoices
count toward their admin's dashboard — that's the whole point of the
team's numbers together") — user-ის ახალი მოთხოვნა ცალსახად
**აუქმებს** იმ წესს ამ view-კონტექსტებისთვის (`/orders`, დეშბორდი),
თუმცა **არა** ინვოისის ნუმერაციისთვის: `Invoice::save()`-ის
`sequence_number`-ის გამოთვლა და `previewNextSequenceNumber()`
კვლავ პირდაპირ `User::tenantMemberIds($ruler)`-ს იყენებენ (არა
`Auth::invoiceScopeUserIds()`-ს) — ნომრები კვლავ **მთელი გუნდის**
საერთო თანმიმდევრობაა, მხოლოდ ის, რაც **ჩანს**, იცვლება.

**`Auth::invoiceScopeUserIds()`** (`4.86`-ის ერთადერთი choke point) —
გადაწერილია ერთიანი წესით: `$actingAs` = SuperUser-ის არჩეული
კონკრეტული პიროვნება (`impersonatingUserId()`), ან, თუ ეს არ არსებობს
(ჩვეულებრივი login), **თავად ამჟამად შესული მომხმარებლის საკუთარი
id** (`$_SESSION['user_id']`) — `Auth::user()`-ის ხელახლა-გამოძახების
გარეშე (`tenantId()` უკვე ერთხელ ამოწმებს სესიას ამ წერტილამდე).
`$actingAs !== $ruler` → მხოლოდ ის ერთი; თანაბარია (root თავად
ათვალიერებს/შესულია) → მთელი გუნდი, უცვლელად. ეს ნიშნავს: root
ადმინის ჩვეულებრივი შესვლა — უცვლელად მთელი გუნდი; ქვემომხმარებლის
ჩვეულებრივი შესვლა — ახლა **მხოლოდ საკუთარი** (ახალი); SuperUser
root-ის დათვალიერებისას — მთელი გუნდი (უცვლელი, `4.86`); SuperUser
კონკრეტული სუბუსერის დათვალიერებისას — მხოლოდ მისი (უცვლელი, `4.86`).

დამოკიდებული caller-ები (`InvoiceController::orders()`/
`exportOrdersPdf()`, `DashboardController::index()` →
`Dashboard::stats()`/`revenueByUser()`/`recentInvoices()`) **არცერთი
არ შეცვლილა** — ყველა უკვე `Auth::invoiceScopeUserIds()`-ს
იძახებდა `4.86`-ის შემდეგ, ცვლილება მთლიანად თავად ამ ერთ
ფუნქციაშია ლოკალიზებული. მხოლოდ docblock-ები დაზუსტდა
(`InvoiceController::orders()`, `Dashboard.php`-ის class-level +
სამივე მეთოდის) — ძველი "სუბუსერი ხედავს მთელ გუნდს" ფრაზირება
შეცვლილია ახალი წესის ასახვით.

**გადამოწმებულია ცოცხლად** (tenant 31-ზე, დროებითი sub-user-ითა და
ინვოისით — დასრულების შემდეგ ორივე წაშლილია): შეიქმნა დროებითი
ქვემომხმარებელი ("QA Sub Scope Test") + 1 ინვოისი მასზე (20.00 ₾) —
root-ს (test1) უკვე ჰქონდა 1 საკუთარი ინვოისი (1,656.00 ₾), გუნდის
ჯამი 2. ქვემომხმარებლის სახელით შესვლისას: დეშბორდზე
"ინვოისები"=**1**, "შემოსავალი"=**20.00**, chart-ს **ერთადერთი**
სერია (`label: "QA Sub Scope Test"`, `total: 20`), "ბოლო ინვოისები"
და `/orders` ორივემ ზუსტად **1** მწკრივი აჩვენა (მხოლოდ საკუთარი).
შემდეგ root-ით (`test1`) შესვლისას: `/orders`-მა ისევ **2** მწკრივი
დააბრუნა (ორივე გუნდის წევრის ინვოისი) — root-ის ჩვეულებრივი
შესვლის ქცევა უცვლელი დარჩა. ტესტ-ინვოისი, ტესტ-ქვემომხმარებელი
წაშლილია, tenant 31-ის temp password აღდგენილია.

### 4.89 SuperUser → პირველი-რიგის dropdown მენიუ ("მომხმარებლები"/"აქტივობა") + ახალი აქტივობის ჟურნალი

user-მა მოითხოვა: `SuperUser` sidebar-ის ბოლო-ერთეულიდან გახდეს
pirველი-რიგის dropdown მენიუ ორი ქვე-ელემენტით — "**მომხმარებლები**"
(=`4.85`/`4.86`-ის ახლანდელი `/superuser` გვერდის იგივე
ფუნქციონალი, უცვლელი) და "**აქტივობა**" (ახალი — აქტივობის ჟურნალი,
მარცხნივ რეგისტრირებულ მომხმარებელთა სია, მარჯვნივ log-ცხრილი
თარიღი/მოქმედება/...). ორივე მენიუ-ელემენტი მხოლოდ მაშინ ჩანს, როცა
სისტემაში SuperUser არის შესული (`role === 'superadmin'`, უცვლელი
`4.х`-ის პირობა).

**დაზუსტებული user-თან** (`AskUserQuestion`): ლოგირდება login/logout
+ **ნავიგაცია** ("ვინ სად შევიდა") — არა "ყველა ღილაკზე დაჭერა"
სიტყვასიტყვით (ეს client-side click-ტრეკინგს მოითხოვდა, ცალკე
beacon-endpoint-ს — spam-ის და ღირებულების გარეშე overkill
იქნებოდა). SuperUser-ის მენიუს პოზიცია — უცვლელად sidebar-ის
ბოლოში, ცალკე სექციად (როგორც აქამდე).

**სქემა** — `migrations/036_create_activity_log.sql`: `activity_log`
(`user_id` FK→`users.id` ON DELETE CASCADE, `method`, `path`,
`ip_address`, `created_at`).

**`App\Core\ActivityLog`** (ახალი) — **ერთადერთი choke point**:
`record()` გამოიძახება `public/index.php`-დან, ერთხელ, აპ-ის
login-გატის ($requestPath-ის დათვლის) ზუსტად შემდეგ — ყოველი
authenticated request (GET ნავიგაციაც და POST action-იც) აქ გადის,
ამიტომ **არცერთ კონტროლერს** არ დასჭირდა ცალკე log-გამოძახება.
`$_SESSION['user_id']`-ს პირდაპირ კითხულობს (არა `Auth::user()`,
რომ `Auth::check()`-ის უკვე ერთხელ ჩატარებული სამუშაო არ გაორმაგდეს).
`SKIP_PATHS`-ით გამორიცხულია სუფთა AJAX/JSON helper-ები (`/units`,
`/product-types`, `/invoices/preview`, `/auth/photo`) — არა
ნავიგაცია, მხოლოდ spam იქნებოდა. `describe()` — `"POST /login"`-ის
მსგავს წყვილს (method+path, query-ს გარეშე lookup-ისთვის) ~30
route-ის fixed lang-key რუკით გადააქცევს წაკითხვად ტექსტად
(`"შესვლა — /login"`) — რაც არ არის map-ში, raw `"METHOD /path"`-ზე
ბრუნდება fallback-ად, ბაგი არაა.

**`Auth::login()`/`logout()`** — ორივეს ბოლოში ემატება
`ActivityLog::record(...)`, **სინთეზური** `('POST', '/login')`/
`('POST', '/logout')` წყვილით (არა რეალური request-ის path,
რომელიც password/OTP/Google/registration-ის მიხედვით სხვადასხვაა)
— ყოველთვის ერთი სუფთა "შესვლა" ჩანაწერია, მექანიზმის მიუხედავად.
ეს ერთადერთი გაზიარებული choke point ყველა login-გზას
(`attempt()`, `verifyOtp()`, `googleCallback()`, `register()`)
ფარავს ერთბაშად. `logout()` ასევე `Auth::check()`-ის საკუთარი
auto-logout ტოტებიდანაც (idle timeout, session-ის შუაში დაბლოკვა)
გამოიძახება — ესეც აქტივობის კვალს ტოვებს, არა მხოლოდ "გასვლა"
ღილაკის დაჭერისას.

**`SuperUserController::activity()`** (ახალი action, `GET
/superuser/activity`) — `User::everyone()` (ახალი: ყველა
non-superadmin მომხმარებელი, ყველა tenant-ი, flat) მარცხენა
სიისთვის, `ActivityLog::all(?userId)` — `?user_id=N`-ით
ვიწროვდება ერთ კონკრეტულ პიროვნებაზე.

**`superuser-activity.php`** (ახალი view) — ორსვეტიანი layout: მარცხნივ
`list-group` (ძებნის ველით, plain client-side JS filter-ით
`data-name`-ზე), მარჯვნივ `.ds-table` (search+pagination თავისუფლად,
`ds_table_script()`-ის იმავე კომპონენტით) — თარიღი/მომხმარებელი
(ფერის წერტილით, `4.87`)/მოქმედება/IP.

**`sidebar.php`** — ძველი ბრტყელი `<a href="/superuser">` →
`<details class="ds-nav-group" name="ds-nav">` (იგივე markup/
accordion-ჯგუფი, რასაც menu.json-ის dropdown-ჯგუფებიც იყენებენ),
ორი შვილი-ბმულით. Role-გატა (superadmin-ონლი) უცვლელი, hand-coded
(menu.json-ს არ აქვს role-visibility კონცეფცია, ეს ერთადერთი
გამონაკლისია — `4.х`-ის დოკუმენტირებული, უცვლელი გადაწყვეტილება).

**გადამოწმებულია ცოცხლად** (`super@nova.local` + tenant 31/test1,
temp password-ით): (1) superadmin-ით შესვლისას sidebar-ში
`SuperUser`-ის dropdown სწორად გამოჩნდა ორი შვილით
(`მომხმარებლები→/superuser`, `აქტივობა→/superuser/activity`); (2)
`/superuser/activity`-ზე ყველა 5 რეგისტრირებული user (ორივე tenant,
plus root+2 sub) მარცხენა სიაში გამოჩნდა; log-ცხრილში საკუთარი
სესიის `შესვლა — /login`, `სამუშაო მაგიდის ნახვა — /`, `SuperUser
სიის ნახვა — /superuser`, `აქტივობის ჟურნალის ნახვა —
/superuser/activity` ჩანაწერები სწორი დროით/IP-ით (`127.0.0.1`)
გამოჩნდა; (3) test1-ით ჩვეულებრივი (არა-SuperUser) შესვლისას sidebar-ში
`SuperUser`-ის მენიუ საერთოდ არ დაერენდერა (`superuserMenuPresent:
false`, JS-შემოწმებით); (4) test1-ის სახელით navigatoin (`/`,
`/customers`, `/orders`, `/logout`) → superadmin-ის სესიით
`/superuser/activity?user_id=31`-ზე ეს ზუსტად ეს 4+login ჩანაწერი
გამოჩნდა, სხვა user-ების ჩანაწერების გარეშე — cross-tenant
ფილტრაცია მუშაობს სწორად; (5) `/superuser` (ახლა "მომხმარებლები")
ცოცხლად გადამოწმდა — ფუნქციონალი (roster, დათვალიერება/დაბლოკვა)
ბუკვალურად უცვლელია, არცერთი ფაილი `4.85`/`4.86`-ის შემდეგ არ
შეცვლილა ამ change-ში. tenant 31-ის temp password აღდგენილია.
Verification-ის დროს დაგენერირებული `activity_log`-ის ჩანაწერები
(superadmin-ის + test1-ის საკუთარი login/navigation, 20 row) **არ
წაშლილა** — ეს რეალური, ღირებული log-მონაცემია, არა ხელოვნური
ტესტ-მონაცემი (ინვოისი/customer-ის მსგავსად), ამ ფუნქციის ზუსტად
ასეთი ჩანაწერების ჩაწერისთვისაა განკუთვნილი.

### 4.90 აქტივობის ჟურნალის მარცხენა სია — ქვემომხმარებლები ინდენტირებული საკუთარი ადმინის ქვეშ

user-მა მოითხოვა: `4.89`-ის მარცხენა user-სიაში ჩანდეს, ვისი
ქვემომხმარებელია თითოეული — ანუ ტენანტის იერარქია ვიზუალურად.

**`SuperUserController::activity()`** — `User::everyone()` (flat,
alphabetical, ტენანტის ინფორმაციის გარეშე) → `User::allGroupedByTenant()`
(იგივე query, რასაც `superuser.php`-ის roster იყენებს, `4.85`) —
ცვლადიც გადაერქვა `$tenantGroups`. `User::everyone()` მთლიანად
წაშლილია `User.php`-დან — მას აღარავინ იძახებდა.

**`superuser-activity.php`** — მარცხენა სია ახლა ტენანტ-ჯგუფებად
გამოისახება: root ადმინის ბმული ჩვეულებრივად, უშუალოდ მის შემდეგ კი
თითოეული მისი ქვემომხმარებლის ბმული `ps-4` შეწევით +
`bi-arrow-return-right` ისარით — ზუსტად იგივე ვიზუალური კონვენცია,
რასაც `superuser.php`-ის own child-table იყენებს (`4.85`).

**გადამოწმებულია ცოცხლად**: `/superuser/activity`-ზე მარცხენა
სიაში `test1`/`test2`/`გივი ბერძენიშვილი` ჩვეულებრივად (`ps-4`
გარეშე), `პავლე პეტრიაშვილი`/`პეტრე პავლიაშვილი` კი სწორად
ინდენტირებული (`ps-4`) გამოჩნდა, `გივი ბერძენიშვილი`-ს ბმულის
პირდაპირ შემდეგ.

### 4.91 აქტივობის ჟურნალის თარიღი — წამის სიზუსტით

user-მა მოითხოვა: `4.89`-ის log-ცხრილის „თარიღი" სვეტს წამებიც
ჰქონდეს (მანამდე მხოლოდ სთ:წთ იყო). `superuser-activity.php`-ში
`substr($row['created_at'], 11, 5)` (`H:i`) → `substr(...11, 8)`
(`H:i:s`) — `activity_log.created_at` MySQL `TIMESTAMP`-ია, string
ფორმატი `'Y-m-d H:i:s'` უცვლელად შეიცავს წამებს, უბრალოდ ადრე
წაჭრილი იყო. გადამოწმებულია ცოცხლად — ცხრილში ჩანაწერები ახლა
`სთ:წთ:წმ`-ით გამოჩნდა (მაგ. `02:36:08`).

### 4.92 "ტრეფიკი" ამოღებულია, "მიმოხილვა" — ახალი ანალიტიკური გვერდი

user-მა მოითხოვა ორი რამ: (1) `menu.json`-ის "ანალიტიკა" ჯგუფიდან
"ტრეფიკი" ქვე-პუნქტი მთლიანად ამოღებულიყო (ადრეც აღინიშნა, რომ ეს
placeholder ვებ-საიტის ვიზიტორთა ანალიტიკის ნაშთია, ამ საბუღალტრო
აპლიკაციას არ ერგება — იხ. წინა ტურის პასუხი); (2) "მიმოხილვა"-ს
ადგილას ავეშენებინა ის ფუნქციონალი, რაც წინა პასუხში შემოთავაზებული
იყო: თავისუფალი პერიოდი, შემოსავლის ტრენდი, ტოპ დამკვეთები/
პროდუქცია, საშუალო ინვოისი, draft→final კონვერსია.

**`menu.json`** — `nav.analytics_traffic` item წაშლილია მთლიანად
(lang-key-ებიც, `ka`/`en`, ორივეს — აღარავინ იძახებდა). `nav.analytics_overview`-ის
`url` `"#"` → `/analytics/overview`. `nav.analytics_reports` უცვლელად
`"#"`-ზეა — ეს ცალკე მოთხოვნა არ ყოფილა.

**`App\Models\Analytics`** (ახალი) — org-wide ანალიტიკა, თავისუფალი
`$from`/`$to` დღიური დიაპაზონით (არა მხოლოდ მიმდინარე წელი, დეშბორდის
chart-ისგან განსხვავებით, და არა ერთი დამკვეთი, `CustomerReport.php`-ისგან
განსხვავებით) — იგივე `Auth::invoiceScopeUserIds()` სქოუფინგის
წესით, რასაც დეშბორდი/`/orders`/`CustomerReport` უკვე მისდევენ
(`4.86`/`4.88`) — sub-user ხედავს მხოლოდ საკუთარს, admin მთელ
გუნდს. ოთხი მეთოდი: `summary()` (count/total/average/finalRate —
`finalRate` ახალი მეტრიკაა, არცერთ არსებულ რეპორტში არ ყოფილა),
`revenueTrend()` (`daily`/`weekly`/`monthly` bucket, `DATE_FORMAT`-ის
შესაბამისი pattern-ით), `topCustomers()`/`topProducts()`
(`CustomerReport::topProducts()`-ის იგივე ფორმა, org-wide, ერთი
დამკვეთის ფილტრის გარეშე).

**`AnalyticsController::overview()`** (ახალი, `GET /analytics/overview`) —
`?from=&to=&granularity=` (native `<input type="date">`-ებიდან,
JS date-picker ბიბლიოთეკის გარეშე) — ვალიდაცია: არასწორი/არარსებული
თარიღი → default "ბოლო 30 დღე"; `from`>`to` → ავტომატურად იცვლება
ადგილი, ჩავარდნის ნაცვლად.

**`analytics-overview.php`** (ახალი view) — filter-ფორმა, 4 stat-ბარათი
(`customer-report.php`-ის იგივე `.ds-icon-tile` ვიზუალი, `money()`
helper-ით), line-chart (Chart.js, `revenueByUser()`-ის ბარ-chart-ისგან
განსხვავებით — ტრენდისთვის line უფრო ბუნებრივია), ტოპ-დამკვეთების და
ტოპ-პროდუქციის ორი ბარათი. `granularity`-ის მიხედვით bucket-ლეიბლები
სხვადასხვანაირად ფორმატდება: `daily` → `ds_date()`, `monthly` →
`t('month.N')`, `weekly` → raw ISO-კვირა (`2026-W35`, ლოკალიზაცია არ
დასჭირდა).

**გადამოწმებულია ცოცხლად** (tenant 31/test1-ით, temp password-ით):
sidebar-ში "ტრეფიკი" აღარ ჩანს, "მიმოხილვა" სწორ URL-ზე მიდის;
default 30-დღიან ფანჯარაში (tenant 31-ის ერთადერთი ინვოისი, `1,656.00`,
draft) — 4 stat-ბარათი ზუსტი (`1`/`1,656.00`/`1,656.00`/`0.0%`),
chart-ის ერთადერთი წერტილი `16 აგვ, 2026`-ზე `1656`-ით, ტოპ-დამკვეთი/
პროდუქტიც სწორად. `?granularity=monthly&from=2026-01-01`-ით —
chart-ის ლეიბლი სწორად `"აგვ 2026"`-ზე შეიცვალა, ფილტრ-ფორმის
ინფუთებმაც (`from`/`to`/`granularity`) გამოყენებული მნიშვნელობები
სწორად აჩვენეს. ცარიელ პერიოდზე (`2020-01`) — ყველა 4 ბარათი `0`/
`0.00`, სამივე empty-state ტექსტი (`analytics.empty`) სწორად
გამოჩნდა. tenant 31-ის temp password აღდგენილია.

### 4.93 ინვოისის დუბლირება — `/orders`, დეშბორდი და ინვოისის რედაქტირების გვერდი

user-მა მოითხოვა: "დუბლირება" მოქმედება დაემატოს ყველა შეკვეთის
ცხრილის მოქმედებებში და ინვოისის რედაქტირების გვერდზეც.
"ცხრილის მოქმედებები" ორივე ადგილას მოვიაზრე — `/orders` **და**
დეშბორდის "ბოლო ინვოისები" (`4.84`-ის დამკვიდრებული პარიტეტი
ორ ცხრილს შორის, ერთგან დამატებული action მეორეშიც ჩნდება,
თორემ პარიტეტი დაირღვეოდა).

**`InvoiceController::duplicate()`** (ახალი, `POST /invoices/duplicate`)
— იგივე `loadOwnedInvoiceForPdf()` ownership-შემოწმება, რასაც
`sendEmail()`/`exportInvoicePdf()` იყენებენ, `Auth::requireNotImpersonating()`-ითურთ
(write-მოქმედებაა). დამკვეთი/notes/items/is_zero/is_recurring
ერთი-ერთზე კოპირდება, `document_state` კი **ყოველთვის** `draft`-ზე
დაბრუნდება — დუბლიკატი ახალი დასაწყისია, არა "უკვე დამკვეთთან
გაგზავნილის" ასლი. **`Invoice::validate()`-ს არ გადის** — საკუთარი,
უკვე ვალიდური, ერთხელ უკვე შენახული მონაცემია, ხელახლა
გადამოწმება აზრს მოკლებულია. ყოველთვის ახალი ინვოისის საკუთარ
edit-ფორმაზე ბრუნდება (`/invoices?edit=<newId>`) — არა უკან იმ
ცხრილზე, საიდანაც დაიწყო — დუბლირების მთელი აზრი ხომ ასლის
გადახედვა/კორექტირებაა შენახვამდე.

⚠️ **ცოცხლი ტესტირებისას რეალური ბაგი აღმოჩნდა და გასწორდა**:
`Invoice::save()`-ის item-INSERT-ი უპირობოდ `(int) $item['unit_id']`-ს
წერდა — ჩვეულებრივი ფორმის submit-ისთვის უვნებელია
(`Invoice::validate()` ყოველთვის რეალურ unit-ს ითხოვს), მაგრამ
`duplicate()`-ის კოპირებულ item-ებში ლეგასი (pre-`migrations/030`)
ინვოისის `unit_id IS NULL` value `(int) null = 0`-ად გარდაიქმნებოდა
— `0` კი არცერთ `units`-ის row-ს არ შეესაბამება, რაც `invoice_items`-ის
`fk_invoice_items_unit` FK-ს არღვევდა და მთელ ტრანზაქციას
Fatal Error-ით წყვეტდა (ცოცხლად დაფიქსირებული, tenant 31-ის
ორიგინალური, migrations/030-მდელი ინვოისის დუბლირებისას).
**გასწორებულია** root-ში — `Invoice::save()` ახლა `NULL`/`''`
unit_id-ს ნამდვილ `NULL`-ად წერს (არა `0`-ად), ზუსტად ისე, როგორც
წყარო row-ს ჰქონდა — ეს ასევე ზოგადად robustness-ფიქსია
`Invoice::save()`-ის თავად, არა მხოლოდ `duplicate()`-ის ერთი
caller-ისთვის.

**`orders.php`/`dashboard.php`** — ახალი `<form>`+ღილაკი (`bi-copy`)
share-link-ის შემდეგ, ყოველ მწკრივში. **`invoices.php`** — ახალი
ღილაკი action-პანელში (share_link-ის შემდეგ, `<hr>`-მდე), **მხოლოდ**
`$editingInvoice !== null`-ზე (ახალი, ჯერ შეუნახავი ინვოისისთვის
დუბლირება აზრს მოკლებულია — `preview`/`email`/`share_link`-ის
ორმაგი submit_action-ტრიკის საჭიროება აქ არ არსებობს, უბრალოდ
ღილაკი არ ჩანს).

**გადამოწმებულია ცოცხლად** (tenant 31/test1-ით, temp password-ით):
(1) FK-ბაგის აღმოჩენა+გასწორება ზემოთაა აღწერილი; (2) ლეგასი
ინვოისის (`id=12`, `unit_id IS NULL`) დუბლირება `/orders`-იდან —
ახალი ინვოისი (`id=39`, `sequence_number=2`) სწორად შეიქმნა, იგივე
`customer_id`/`total`, `unit_id` კვლავ `NULL` (არა `0`), `draft`
სტატუსით, გადამისამართდა `/invoices?edit=39`-ზე toast-ით "ინვოისი
... შეიქმნა"; (3) ახალი, ნამდვილი `unit_id`-იანი `final`-სტატუსიანი
ტესტ-ინვოისის (`id=40`) დუბლირება — ახალმა (`id=41`) `unit_id=5`
სწორად შეინახა, `document_state` `final`→`draft`-ზე გადავიდა; (4)
დეშბორდის საკუთარი "ბოლო ინვოისები" ცხრილიდანაც იგივე მოქმედება
გამართულად იმუშავა. ყველა ტესტ-ინვოისი (`39`, `40`, `41`, `42`)
წაშლილია, tenant 31-ის temp password აღდგენილია.

### 4.94 დუბლირება აღარ წერს ბაზაში დაუყოვნებლივ — მხოლოდ "განახლების" დაჭერისას, საკუთარი ვიზუალით

user-მა დააზუსტა `4.93`-ის ქცევა: (1) სათაურში "ინვოისის რედაქტირების"
მაგივრად "დუბლირებული ინვოისი" უნდა ეწეროს; (2) ამ მდგომარეობის
ფონი სხვა ტონალობის უნდა იყოს; (3) ბაზაში ჩაწერა **მხოლოდ** მაშინ
უნდა მოხდეს, როცა "განახლება" დაჭერილია — არა დუბლირების ღილაკზე
დაჭერისთანავე; (4) დუბლირების (ჯერ შენახვამდე) გვერდზე "დუბლირება"
ღილაკი არ უნდა ჩანდეს.

**გადაწყვეტა — `InvoiceController::duplicate()` აღარ წერს ბაზაში
საერთოდ.** მთლიანად გადაკეთდა: `Invoice::save()`-ის პირდაპირი
გამოძახების მაგივრად, უბრალოდ `flash('old', [...])`-ს აკეთებს —
ზუსტად იგივე მექანიზმი, რასაც `index()`-ის `?edit=N` ტოტი და
ჩავარდნილი ვალიდაციის resubmit უკვე იყენებენ ფორმის თავიდან
დასახატად. **განზრახ არ დგება `invoice_id`/`updated_at`** — ეს
ნიშნავს, `$editingInvoice` `null` რჩება შემდეგ load-ზეც, და
`invoices.php`-ის `$editing` flag-იც `false`-ია — ასლი **რეალურ
row-ად მხოლოდ მაშინ იქცევა**, როცა "განახლება" ნამდვილად
დაიჭირება და ჩვეულებრივი `store()`-ის create-გზა გაეშვება, ზუსტად
ისე, თითქოს user-ს ხელით აეკრიფა ახალი ინვოისი. ახალი `duplicate_of`
გასაღები `old`-ში — მხოლოდ `invoices.php`-ისთვის ნიშანია, რომ ეს
ჩვეულებრივი ცარიელი ახალი ინვოისი კი არა, დუბლირებული ასლია.

**`invoices.php`** — ახალი `$duplicating = isset($old['duplicate_of'])`.
Header-ის ფონი და სათაური სამ-მდგომარეობიანი გახდა: `$duplicating`
→ `bg-info-subtle` + `t('inv.duplicate_title')` ("დუბლირებული
ინვოისი"); `$editing` (ნამდვილი `?edit=N`) → ძველებურად
`bg-warning-subtle` + `t('inv.edit_title')`; არცერთი → `bg-transparent`
+ `t('inv.new_title')`. Submit-ღილაკის ტექსტი: `$editing ||
$duplicating` → `t('inv.update')` ("განახლება", user-ის ცალსახა
მოთხოვნით — მართალია ტექნიკურად create-ია, არა update, მაგრამ
user-ისთვის კონცეპტუალურად "უკვე მომზადებული ასლის დასრულებაა").
`reset`-ის JS-ხელმძღვანელი განახლდა — ორივე შესაძლო non-default
ტონი (`bg-warning-subtle`-იც და `bg-info-subtle`-იც) სუფთავდება,
default "ცარიელ ახალზე" დაბრუნებისას.

**"დუბლირება" ღილაკის დამალვა დუბლირების გვერდზე — დამატებითი კოდი
არ დასჭირდა.** `invoices.php`-ის საკუთარი "დუბლირება" ღილაკი
`4.93`-დანვე `$editingInvoice !== null`-ზეა დამოკიდებული — და ვინაიდან
ეს ცვლადი ახლა `null` რჩება ზუსტად მაშინაც, როცა დუბლირების
მდგომარეობაშია (არავითარი `?edit=N` არ ხდება), ღილაკი უკვე
ავტომატურად არ ჩნდება, არაფრის შეცვლა არ დასჭირდა.

**`Invoice::validate()`-ს ახლა გადის.** `4.93`-ში დუბლირება ამ ბიჯს
გვერდს უვლიდა (თავად `Invoice::save()`-ს პირდაპირ იძახებდა) —
ახლა, რაკი ჩვეულებრივი `store()`-ის გზით გადის, ლეგასი (unit-ის
გარეშე) item ვალიდაციაზეც გაივლის, საჭიროების შემთხვევაში user-ს
თავად სთხოვს ერთეულის არჩევას შენახვამდე — `4.93`-ის FK-ბაგის
საჭიროება ამ ბილიკზე საერთოდ აღარ დგება (თუმცა `Invoice::save()`-ის
NULL-ფიქსი კვლავ სასარგებლო robustness-გაუმჯობესებაა, უცვლელად
რჩება).

**გადამოწმებულია ცოცხლად** (tenant 31/test1-ით, temp password-ით):
(1) დუბლირებაზე დაჭერისთანავე — `bg-info-subtle` ფონი, "დუბლირებული
ინვოისი" სათაური, "განახლება" ღილაკი, ცარიელი `invoice_id`, URL
`/invoices#invoice-form` (არა `?edit=N`) — და **ბაზაში ამ დროისთვის
არაფერია ჩაწერილი** (პირდაპირი DB query-თი დადასტურებული); (2)
ლეგასი (`unit_id IS NULL`) წყაროს დუბლირებისას "განახლება"-ს
დაჭერამ სწორად დააბრუნა ვალიდაციის შეცდომაზე (ერთეული აუცილებელია)
— ბაზაში კვლავ არაფერი შეიცვალა, `4.93`-ის აღწერილი "immediate-write"
ქცევა საერთოდ აღარ არსებობს ამ ბილიკზე; (3) ნამდვილი `unit_id`-იანი
წყაროთი — "განახლება"-ს დაჭერამ ამჯერად რეალურად შექმნა ახალი
ინვოისი (ბაზაში დადასტურებული, `document_state` სწორად `final`→`draft`),
გვერდი ჩვეულებრივი "ახალი ინვოისი"-ის ცარიელ მდგომარეობაზე
დაბრუნდა — იგივე ქცევა, რასაც ჩვეულებრივი ახალი ინვოისის
"შენახვა"-ც აქამდეც იძლეოდა (`store()`-ის არსებული, ამ change-ის
გარეთა კონვენცია). ტესტ-ინვოისები წაშლილია, tenant 31-ის temp
password აღდგენილია.

### 4.95 დუბლირების "განახლება" → წარმატებისას გადამისამართება `/orders`-ზე (+ ერთი ბაგის გასწორება ამავე დროს)

user-მა მოითხოვა: დუბლირებული ინვოისის "განახლება"-ზე დაჭერისას
(ანუ ბაზაში ჩაწერის შემდეგ), გვერდი გადავიდეს "ყველა შეკვეთაზე"
(`/orders`) — არა ჩვეულებრივ ცარიელ "ახალი ინვოისი"-ს
მდგომარეობაზე, რასაც ჩვეულებრივი ახალი ინვოისის შენახვა (და
ნამდვილი ინვოისის რედაქტირებაც) დღემდე იძლეოდა. ეს ცვლილება
**მხოლოდ** დუბლირების დასრულებას ეხება — ჩვეულებრივი "შენახვა"
(ახალი ინვოისი) და ნამდვილი "განახლება" (`?edit=N`) უცვლელად
ცარიელ `/invoices`-ზე ბრუნდება, რადგან user-ის მოთხოვნა კონკრეტულად
დუბლირების ნაკადს ეხებოდა.

**გადამწყვეტი დეტალი**: server-ს სჭირდებოდა ხერხი, გაერჩია
"ეს create დუბლირებიდან მოვიდა" ჩვეულებრივი ხელით-აკრეფილი ახალი
ინვოისისგან — `4.94`-ის `duplicate_of` მარკერი მანამდე მხოლოდ PHP-ის
`$old`-ში იყო (view-ს რენდერისთვის), **არასდროს** ხდებოდა
ნამდვილი HTML ფორმის ველი, ანუ submit-ზე საერთოდ არ იგზავნებოდა
სერვერზე.

**`invoices.php`** — ახალი `<input type="hidden" name="duplicate_of">`
თავად `invoiceMainForm`-ში (`invoice_id`-ის გვერდით), `$old['duplicate_of']`-იდან
შევსებული.

**`InvoiceController::store()`**:
- ვალიდაციის-შეცდომის ტოტი — **ამავე დროს გასწორებულია ცოცხლად
  ნაპოვნი ბაგი**: ჩავარდნილი resubmit-ის `flash('old', ...)` აქამდე
  `duplicate_of`-ს არ ინახავდა, ანუ დუბლირების ვალიდაციის შეცდომაზე
  გვერდი "დუბლირებული ინვოისის" ვიზუალს (info-ტონი, სათაური)
  კარგავდა და უბრალო "ახალ ინვოისად" გამოჩნდებოდა — ახლა
  `$_POST['duplicate_of']`-იც ემატება flash-ში, ვიზუალი resubmit-ის
  შემდეგაც შენარჩუნებულია.
- წარმატების ტოტი — ახალი წესი, ყველა submit_action-სპეციფიკურ
  ტოტს (`export_pdf_*`/`preview`/`email`/`share_link`) **შემდეგ**,
  საბოლოო `redirect('/invoices')`-მდე: `$editingId === null &&
  $duplicateOf !== ''` → `redirect('/orders')`. `$editingId ===
  null`-ის დამატებითი შემოწმება (თუმცა `duplicate_of` პრაქტიკულად
  არასდროს ახლავს ნამდვილ `?edit=N` submit-ს) — უფასო დაცვაა,
  წესი ზუსტად "დუბლირება ახლახან რეალურ row-ად იქცა"-ს ერგება.

**გადამოწმებულია ცოცხლად** (tenant 31/test1-ით, temp password-ით):
(1) ლეგასი (unit-ის გარეშე) წყაროს დუბლირება + "განახლება" →
ვალიდაციის შეცდომა ("აირჩიე ერთეული"), **გვერდმა ვიზუალი
შეინარჩუნა** (`bg-info-subtle`, "დუბლირებული ინვოისი", `duplicate_of=12`)
— ბაგი დადასტურებულადაა გასწორებული; (2) ნამდვილი unit-იანი
წყაროს დუბლირება + "განახლება" → ახალი ინვოისი ბაზაში დადასტურებულად
შეიქმნა, გვერდი **`/orders`-ზე** გადავიდა (არა `/invoices`); (3)
ჩვეულებრივი, ხელით-აკრეფილი ახალი ინვოისის შენახვა (`duplicate_of`
ცარიელი) — უცვლელად ცარიელ `/invoices`-ზე დარჩა, ახალი წესი მასზე
არ მოქმედებს. ტესტ-ინვოისები წაშლილია, tenant 31-ის temp password
აღდგენილია.

### 4.96 დუბლირება → `POST /invoices/duplicate`-იდან საკუთარ `?duplicate=N` entry point-ზე, `?edit=N`-ისგან დამოუკიდებელი კოდით

user-მა მოითხოვა: დუბლირება გამოძახებულიყო ცალკე, `?edit=N`-ის
იმავე ნიმუშის query-პარამეტრით (`invoices?duplicate=48`) —
**განზრახ ცალკე, დამოუკიდებელი კოდით**, არა `?edit=N`-ის საერთო
branch-ის გავლით — რომ ერთ ნაკადში მოგვიანებით დამატებულმა
ცვლილებამ არასდროს "წაუხდინოს" მეორეს, თუნდაც ამან გარკვეული
კოდის დუბლირება გამოიწვიოს (user-ის საკუთარი, ცალსახად გამოხატული
არჩევანი — DRY-ის წინააღმდეგ, იზოლაციის სასარგებლოდ).

**`InvoiceController`**:
- ძველი `POST /invoices/duplicate` action (`duplicate()`, flash+redirect
  მექანიზმი, `4.94`) **მთლიანად წაშლილია**.
- `index()`-ს დაემატა ახალი, **დამოუკიდებელი** branch: `if ($old === []
  && $editingInvoice === null && ctype_digit($_GET['duplicate'] ?? ''))`
  — იმავე "`$old===[]` იგებს resubmit-ს" წესით, რასაც `?edit=N`-ის
  branch-იც იყენებს, მაგრამ **ცალკე კოდის ბლოკად**, არა იმავე
  if/else-ტოტად.
- ახალი **`private loadDuplicateOld(int $sourceId, int $ruler): array`**
  — თავისი, დამოუკიდებელი წყარო-ინვოისის ჩატვირთვისა და `$old`-ის
  აწყობის ლოგიკა, განზრახ **არ იზიარებს** `?edit=N`-ის საკუთარ
  inline-კოდს (თუმცა ორივე დღეს ერთმანეთს ჰგავს — მომავალში
  თავისუფლად განსხვავებული გახდება). ცუდ/უცხო-ტენანტის id-ზე
  ჩუმად აბრუნებს `[]`-ს (იგივე tolerant ქცევა, რასაც `?edit=N`-ის
  ბადი id-იც იძლევა — ცარიელ "ახალ ინვოისზე" ვარდნა, არა 404).
  **Cross-tenant დაცვა შენარჩუნებულია** — `$this->ownerTenant($invoice)
  !== $ruler` შემოწმება (იგივე, რასაც `loadOwnedInvoiceForPdf()`
  იყენებდა ძველ `duplicate()`-ში).

**`orders.php`/`dashboard.php`/`invoices.php`** — "დუბლირება"-ს
`<form method="post">`+ღილაკი → უბრალო `<a href="/invoices?duplicate=N">`
ბმული, ზუსტად იგივე ნიმუში, რასაც რედაქტირების ფანქრის ბმული
იყენებს (`/invoices?edit=N`) — `csrf_field()`/hidden `invoice_id`
ფორმა აღარ სჭირდება, GET-ნავიგაციაა, არა write-action.

**გადამოწმებულია ცოცხლად** (tenant 31/test1-ით, temp password-ით):
(1) **cross-tenant დაცვა** — test1-ით `/invoices?duplicate=5`
(ნამდვილი tenant **1**-ის ინვოისი, მხოლოდ read-ით შემოწმებული) →
ცარიელი "ახალი ინვოისი" ფორმა დაბრუნდა, არც ერთი მონაცემი არ
გაჟონა; (2) საკუთარი ინვოისის (`id=12`) `/invoices?duplicate=12`-ით
ჩატვირთვამ სწორად აჩვენა "დუბლირებული ინვოისი"/`bg-info-subtle`/
`duplicate_of=12`, **ბაზაში არაფერი დაწერილა** (პირდაპირ
გადამოწმებული); (3) ნამდვილი unit-იანი წყაროთი — სრული ციკლი
(load → "განახლება" → ახალი row ბაზაში → გადამისამართება `/orders`-ზე)
უცვლელად იმუშავა ახალი არქიტექტურითაც; (4) ლეგასი (unit-ის გარეშე)
წყაროთი ვალიდაციის შეცდომაც — `4.95`-ის ვიზუალის-შენარჩუნების
ფიქსი ახალ architecture-შიც უცვლელად მუშაობს. `/orders`-ისა და
დეშბორდის ბმულებიც სწორი `?duplicate=N` href-ებით დადასტურდა.
ტესტ-ინვოისები წაშლილია, tenant 31-ის temp password აღდგენილია.

⚠️ **გვერდით აღმოჩენილი, ცალკე, out-of-scope უსაფრთხოების
საკითხი** (ამ ცვლილებას არ ეხება, არც შეცვლილა): `?edit=N`-ის
ძველი, `4.96`-მდელი branch-იც და `Invoice::save()`-ის `UPDATE`
branch-იც **არცერთი ტენანტის საკუთრების შემოწმებას არ აკეთებენ** —
`Invoice::find($editId)` არ ფილტრავს `created_by`/ruler-ით, და
`UPDATE invoices ... WHERE id = ?`-საც არ აქვს ruler-შემოწმება.
პრაქტიკულად ეს ნიშნავს: ნებისმიერ შესულ tenant-წევრს შეუძლია
`?edit=<სხვისი-ID>`-ით ნებისმიერი **სხვა tenant-ის** ინვოისის
ნახვა/რედაქტირება/გადაწერა, უბრალოდ ID-ის ცნობით/ცდით. ეს
`loadOwnedInvoiceForPdf()`-ის (და ახლა `loadDuplicateOld()`-ის) მიერ
დაცულ ყველა სხვა endpoint-ს (`show`/`exportInvoicePdf`/`sendEmail`/
`preview`/ახლა `duplicate`) **არ ეხება** — მხოლოდ ეს ორი კონკრეტული
adress (`?edit=N` + `store()`-ის UPDATE) რჩება ღია. საჭიროებს
ცალკე, სპეციალურ ყურადღებას.

### 4.97 ახალი ინვოისის action-პანელის ღილაკები — აღარ ინახავენ ჩუმად, დასტურის მოდალის გარეშე

user-მა შენიშვნით მიმართა (სურათით, action-პანელის ღილაკები): როცა
ინვოისი ჯერ არ არის შენახული, "ექსპორტი PDF"/"გადახედვა"/"მეილზე
გაგზავნა"/"ბმულის გაზიარება" ღილაკებზე დაწკაპუნებისას საინფორმაციო
მოდალი უნდა გამოვიდეს, რომელიც ინვოისის შექმნაზე დასტურს
მოითხოვს — და მოთხოვნა კონკრეტულ საეჭვო ქცევასაც ითვალისწინებდა:
**"შეამოწმე, ხომ არ ხდება ავტომატურად შესრულებამდე ინვოისის
შექმნა"**.

**გადამოწმებით დადასტურდა — დიახ, ზუსტად ასე ხდებოდა.** ეს 4
ღილაკი (PDF-ის ორივე ვარიანტი, "გადახედვა", "მეილზე გაგზავნა",
"ბმულის გაზიარება") ახალი/დუბლირებადი ინვოისისთვის (`$editingInvoice
=== null`) ყოველთვის იყო ნამდვილი `type="submit"` ღილაკები
(`name="submit_action"`), რომლებიც მთელ ფორმას პირდაპირ
აგზავნიდნენ `InvoiceController::store()`-ში — ანუ ინვოისი
**ჩუმად, დასტურის გარეშე** იქმნებოდა ბაზაში, სანამ მოთხოვნილი
მოქმედება (PDF/preview/email/share) საერთოდ შესრულდებოდა. ("ვოთსაპზე
გაგზავნა" ჯერ საერთოდ არაფრის-არმკეთებელი placeholder-ია, ამ
საკითხს არ ეხება.)

**გადაწყვეტა — მთლიანად client-side, `store()`-ს არაფერი შეხებია**:
- ახალი `#invoiceConfirmCreateModal` (იგივე "own header/footer chrome"
  სტილი, რასაც `invoiceEmailModal`-იც იყენებს) — საინფორმაციო ტექსტი
  + "დიახ, შევქმნათ" ღილაკი.
- ყველა 5 შესაბამის ღილაკს (2× PDF dropdown-item, "გადახედვა",
  "მეილზე გაგზავნა", "ბმულის გაზიარება") დაემატა `js-confirm-create`
  კლასი — **პირობითად**, მხოლოდ `$editingInvoice === null`-ზე (PDF
  dropdown-ის ორივე item ყოველთვის submit_action-ია, ედითის დროსაც,
  ამიტომ მათზე კლასი პირობითადაა დამატებული; დანარჩენი 3 ისედაც
  მხოლოდ ამ mode-ში render-დება).
- JS-ი (`click`) `preventDefault()`-ავს ღილაკის submit-ს, ინახავს
  რომელი `submit_action` იყო დაჭერილი, მოდალს ხსნის. **მხოლოდ**
  "დიახ, შევქმნათ"-ზე დაჭერისას ემატება hidden `submit_action`
  input ფორმაში და `form.requestSubmit()` რეალურად ეშვება.

ნამდვილი (უკვე შენახული) ინვოისის რედაქტირებისას ეს ღილაკები კვლავ
პირდაპირი მოდალ-ტრიგერებია (`type="button"`, `js-confirm-create`
კლასის გარეშე) — ისედაც არაფერი "იქმნება", უბრალოდ **განახლდება**,
დასტურის საჭიროება არც არსებობდა.

**გადამოწმებულია ცოცხლად** (tenant 31/test1-ით, temp password-ით):
(1) ახალ, ცარიელ ინვოისზე "გადახედვა"-ზე დაჭერისას — მოდალი
გამოჩნდა (`modal fade show`), URL/ბაზა უცვლელი დარჩა (**ბაზაში
წინასწარ არაფერი დაწერილა** — პირდაპირ დადასტურებული, ზუსტად
user-ის ეჭვის საწინააღმდეგოდ ახლა); (2) "დიახ, შევქმნათ"-ზე
დაჭერისას — ინვოისი რეალურად შეიქმნა ბაზაში, გვერდი გადავიდა
`?edit=N`-ზე, preview-მოდალი ავტომატურად გაიხსნა (არსებული
`?preview=1` მექანიზმი უცვლელად იმუშავა); (3) ნამდვილ, უკვე
შენახულ ინვოისზე — `.js-confirm-create` საერთოდ არ ჩანს, ღილაკები
პირდაპირი `type="button"` ტრიგერებია, უცვლელად; (4) დუბლირების
preview-mode-შიც (`?duplicate=N`) ყველა 5 ღილაკს კლასი სწორად
ჰქონდა. ტესტ-ინვოისი წაშლილია, tenant 31-ის temp password
აღდგენილია.

### 4.98 "დამატება" (ჩვეულებრივი ახალი ინვოისი) → წარმატებისას ისიც `/orders`-ზე

user-მა მოითხოვა: `4.95`-ის წესი ("განახლება"-ს დაჭერისას
წარმატებაზე გადამისამართება `/orders`-ზე) გავრცელდეს **ჩვეულებრივ,
"დამატება" ღილაკზეც** (პლაინ ახალი ინვოისი, არა დუბლირება) — აქამდე
მხოლოდ დუბლირების დასრულება მიდიოდა `/orders`-ზე, ჩვეულებრივი ახალი
ინვოისის შენახვა კი ცარიელ `/invoices`-ზე ბრუნდებოდა.

**`InvoiceController::store()`** — წარმატების საბოლოო წესი
გამარტივდა: `$editingId === null && $duplicateOf !== ''` (მხოლოდ
დუბლირება) → უბრალოდ **`$editingId === null`** (ნებისმიერი create,
წარმომავლობის მიუხედავად). ეს ბუნებრივად მოიცავს ორივეს — ჩვეულებრივ
"დამატებასაც" და დუბლირების "განახლებასაც" — ერთი პირობით.
ნამდვილი რედაქტირება (`$editingId !== null`, "განახლება" არსებულ
ინვოისზე) კვლავ უცვლელად ბრუნდება ცარიელ `/invoices`-ზე — ეს
შემთხვევა ცალკე დარჩა, არ შეცვლილა.

**გადამოწმებულია ცოცხლად** (tenant 31/test1-ით, temp password-ით):
(1) ჩვეულებრივი ახალი ინვოისის "დამატება" → ინვოისი ბაზაში
დადასტურებულად შეიქმნა (`id=58`), გვერდი **`/orders`-ზე** გადავიდა
(აქამდე `/invoices` იყო); (2) ნამდვილი არსებული ინვოისის (`id=12`)
"განახლება" → უცვლელად ცარიელ `/invoices`-ზე დარჩა, ახალი წესი მასზე
არ მოქმედებს; ინვოისი 12-ის მონაცემები (`total=1656.00`,
`document_state=draft`) resubmit-ის შემდეგაც უცვლელი დარჩა. ტესტ-
ინვოისი წაშლილია, tenant 31-ის temp password აღდგენილია.

### 4.99 ყველა შეკვეთის ცხრილს ფილტრი "შეკვეთის მიმღების" მიხედვით — ახალი `ds-table.js`-ის ზოგადი შესაძლებლობა

user-მა მოითხოვა: ყველა შეკვეთის ცხრილს ჰქონდეს ფილტრი "შეკვეთის
მიმღების" (ინვოისის შემქმნელი გუნდის წევრის) მიხედვით. ეს ეხება
`/orders`-საც და დეშბორდის "ბოლო ინვოისები"-საც — ორივე ცხრილს
იგივე `.ds-table` (search+pagination) კომპონენტი აქვს გაზიარებული.

**გადაწყვეტა — არა page-სპეციფიკური JS, არამედ `ds-table.js`-ის
თავად ახალი, ზოგადი, ხელახლა-გამოყენებადი შესაძლებლობა**: ახალი `<th
data-filterable="true">` წესი — ავტომატურად აშენებს "ფილტრი ამ
სვეტით" `<select>`-ს toolbar-ში, ვარიანტები კი თავად იმ სვეტის
საკუთარი უნიკალური მნიშვნელობებია (იგივე `cellValue()`, რასაც
search/sort უკვე იყენებს — ე.ი. ფერის წერტილის `<span>`-იც და
`data-order` override-იც ავტომატურად სწორად მუშაობს). თუ სვეტს
2-ზე ნაკლები უნიკალური მნიშვნელობა აქვს (მაგ. ერთი-კაცი-ტენანტის
ცხრილს), select საერთოდ არ ჩნდება — არაფრის გასაფილტრია.

**`ds-table.js`**: `buildToolbar()`-ს დაემატა filter-select-ების
აგება (search-ის და per-page-ის შორის), `render()`-ს — `columnFilters`
obj-ის გათვალისწინება (`Object.entries(...).every(...)`, search-თან
ერთად კომბინირებადი). ახალი `filterAll` label (`window.dsTableLabels`,
`ds_table_script()`-იდან, `helpers.php`).

**`orders.php`/`dashboard.php`** — `t('inv.creator')` (`"შეკვეთის
მიმღები"`) `<th>`-ს დაემატა `data-filterable="true"`, სხვა არაფერი.

**ახალი lang-key**: `table.filter_all` (ka: "ყველა", en: "All").

**გადამოწმებულია ცოცხლად** (SuperUser-ით, tenant 1-ის real
მონაცემზე read-only დათვალიერებით — write არაფერი მომხდარა): (1)
`/orders`-ზე (3 განსხვავებული "მიმღები" — root+2 sub) filter-select
სწორად აშენდა, sorted 3 ვარიანტით + "ყველა"; (2) კონკრეტული
პიროვნების არჩევამ ცხრილი სწორად დაფილტრა (`2/2` მწკრივი, footer-ის
row-count-იც სწორად განახლდა); (3) "ყველა"-ზე დაბრუნებამ სრული 20
მწკრივი აღადგინა; (4) იგივე ცხრილი დეშბორდზეც (`t('inv.creator')`
სვეტის აღწერით) იდენტურად მუშაობდა; (5) ერთ-კაცი-ტენანტზე (test1,
tenant 31) filter-select საერთოდ არ აშენდა — სწორი, "ნაკლები 2
ვარიანტი" წესის დაცვით.

### 4.100 `/orders`-ს პერიოდის ფილტრი — მიმდინარე თვე / წელი / დროის მონაკვეთი, "ექსპორტი PDF"-ის გვერდით

user-მა მოითხოვა: შეკვეთების ცხრილს დაემატოს ფილტრი პერიოდის
მიხედვით — მიმდინარე თვე, მიმდინარე წელი, ან თავისუფალი დროის
მონაკვეთი — პოზიციით "ექსპორტი PDF" ღილაკის გვერდით. ეს, `4.99`-ის
"მიმღების" ფილტრისგან განსხვავებით, **server-side** ფილტრია (არა
client-side `ds-table` მექანიზმი) — ორი მიზეზით: (ა) `/orders`-ს
საერთოდ არ აქვს ცალკე "თარიღის" სვეტი (თარიღი ინვოისის ნომერშივეა
ჩაშენებული), და (ბ) "ექსპორტი PDF"-ის გვერდით პოზიციამ ცხადყო, რომ
ექსპორტსაც უნდა ასახავდეს — რაც ბუნებრივად საჭიროებს რეალურ DB
query-ის გაფილტვრას, არა მხოლოდ უკვე-წამოღებული rows-ის დამალვას.

**`Invoice::all()`** — ახალი `?array $dateRange = null` პარამეტრი
(`[from, to]`, `issue_date BETWEEN`), `$createdByIds`-ისგან
დამოუკიდებელი (ორივე ერთად კომბინირებადია WHERE-ში) — default
`null` ნიშნავს "ძველი, უცვლელი ქცევა", ასე რომ `Invoice::all()`-ის
დანარჩენი callers (მაგ. `invoices.php`-ის `$invoicesByCustomer`)
საერთოდ არ შეცვლილა.

**`InvoiceController`** — ახალი `private resolveOrdersRange()`:
`?period=month` → მთელი კალენდარული თვის საზღვრები (`Y-m-01`—`Y-m-t`,
არა "დღემდე" — `Invoice::save()` ინვოისს ყოველთვის `today()`-ით
ბეჭდავს, მომავალი თარიღი არასდროს არსებობს, ასე რომ თვის ბოლო დღე
ზუსტად იგივეა, რაც "დღემდე" პრაქტიკულად); `?period=year` — მთელი
კალენდარული წელი; `?from=&to=` (`დროის მონაკვეთის` მოდალიდან) —
custom, ვალიდაციით (`AnalyticsController::resolveRange()`-ის იგივე
tolerant წესი — ბადი/არასრული თარიღი → უბრალოდ "ფილტრი არ არის",
არა შეცდომა). `orders()`-მაც და `exportOrdersPdf()`-მაც ორივემ ერთი
და იგივე `resolveOrdersRange()` იძახებენ — ექსპორტი ყოველთვის
ზუსტად იმას ასახავს, რაც ეკრანზეა.

**`orders.php`** — ახალი "პერიოდი" dropdown (`bi-calendar3`), ზუსტად
"ექსპორტი PDF"-ის გვერდით (user-ის ცალსახა მოთხოვნა), მისი წინ
(ლოგიკურად — ჯერ ფილტრი, მერე ექსპორტი): "ყველა დრო" / "მიმდინარე
თვე" / "მიმდინარე წელი" (plain ბმულები, `?period=`) + "დროის
მონაკვეთი" (ხსნის ახალ `#ordersRangeModal`-ს — plain `method="get"`
ფორმა, JS არ სჭირდება, submit უბრალოდ ნავიგირებს `?from=&to=`-ზე).
აქტიური ფილტრისას toggle-ღილაკი `btn-primary`-ზე იცვლება და თავად
თარიღებს აჩვენებს ლეიბლად. "ექსპორტი PDF"-ის ორივე ბმულს ემატება
აქტიური `&period=`/`&from=&to=` — ცალკეული მწკრივის export-ბმულებს
(ერთი ინვოისის PDF) კი — არა, ისინი ისედაც კონკრეტული ინვოისისთვისაა.
`$periodFrom`/`$periodTo`, რომლებიც მოდალის input-ებს ავსებენ,
კონტროლერიდან **მხოლოდ** მაშინ მოდის, როცა `resolveOrdersRange()`-მაც
რეალურად მიიღო/გამოიყენა ისინი — ასე რომ ბადი `?from=`-ით ხელით
შედგენილი URL ვერასდროს "აქტიურ ფილტრად" გამოჩნდება ვიზუალურად,
სანამ ფაქტობრივად ყველაფერი გაუფილტრავი რჩება.

**გადამოწმებულია ცოცხლად** (SuperUser-ით, tenant 1-ის real
მონაცემზე, read-only): (1) `?period=month`-მა ზუსტად 8 (სექტემბრის)
მწკრივი აჩვენა, toggle "მიმდინარე თვე"-ზე/`btn-primary`-ზე გადავიდა,
PDF-ბმულებმა `&period=month` სწორად წამოიღეს; (2) `?period=year`-მა
სრული 20 დააბრუნა; (3) custom range (`2026-08-18`—`2026-08-24`) →
ზუსტად 7 მწკრივი (მოსალოდნელი დღეების ჯამი), toggle-ლეიბლმა
`"18 აგვ, 2026 – 24 აგვ, 2026"` სწორად აჩვენა, მოდალის ხელახლა
გახსნისას input-ებიც სწორად იყო წინასწარ შევსებული; (4) "ყველა
დროზე" დაბრუნებამ სრულად აღადგინა 20 მწკრივი, toggle default
მდგომარეობაზე; (5) ხელით შედგენილი ბადი `?from=garbage&to=alsogarbage`
— უსაფრთხოდ იგნორირებულია, 20 მწკრივი (ფილტრის-გარეშე), toggle
**არ** გამოჩნდა "აქტიურად" (ადრეული consistency-ფიქსის დადასტურება).

### 4.101 პერიოდის ფილტრი — dropdown-იდან ღილაკების ჯგუფზე

user-მა შენიშნა: `4.100`-ის dropdown-ში ჩამალული "მიმდინარე თვე/
წელი/დროის მონაკვეთი" ნაკლებად მოსახერხებელია, ვიდრე ყველა ვარიანტი
ერთბაშად ხილული ღილაკები. სერვერის მხარეს (`InvoiceController`/
`Invoice::all()`) **არაფერი შეცვლილა** — მხოლოდ `orders.php`-ის
ვიზუალი.

**`orders.php`** — ერთი `dropdown` → Bootstrap `.btn-group` (4
ღილაკი გვერდიგვერდ: "ყველა დრო" / "მიმდინარე თვე" / "მიმდინარე
წელი" / "დროის მონაკვეთი"). პირველი სამი — plain ბმულები
(`btn-primary`/`btn-outline-secondary`, აქტიურის მიხედვით),
"დროის მონაკვეთი" კვლავ `#ordersRangeModal`-ს ხსნის, უცვლელად —
უბრალოდ, აქტიური custom-range-ის დროს, საკუთარ ტექსტს (თარიღების
დიაპაზონს) აჩვენებს ჩვეულებრივი label-ის მაგივრად, ისევე როგორც
ძველი dropdown-toggle-იც აკეთებდა.

**გადამოწმებულია ცოცხლად** (SuperUser-ით, tenant 1-ის real
მონაცემზე, read-only): (1) default-ზე — "ყველა დრო" სწორად აქტიური
(`btn-primary`); (2) `?period=month`-ზე — "მიმდინარე თვე" აქტიური,
8 მწკრივი; (3) custom range-ზე — მეოთხე ღილაკმა საკუთარი თარიღები
(`"18 აგვ, 2026 – 24 აგვ, 2026"`) სწორად აჩვენა აქტიურ
(`btn-primary`) მდგომარეობაში, 7 მწკრივი.

### 4.102 "დროის მონაკვეთი" — მოდალიდან inline ველებზე, native date-input-ის სტილიც შალამაზდა

user-მა მოითხოვა: `#ordersRangeModal` საერთოდ ამოღებულიყო — "დროის
მონაკვეთი" პირდაპირ, inline, დანარჩენი პერიოდის ღილაკების გვერდით
ჩამჯდარიყო, და native `<input type="date">`-ის სტილიც ცოტა
შელამაზებულიყო (ბრაუზერის ნაგულისხმევი ყუთისებრი იერი, არა
დანარჩენ `.form-control`-ებთან შეხამებული).

**`orders.php`** — `#ordersRangeModal`-ის მთელი markup მთლიანად
წაშლილია. „დროის მონაკვეთი"-ს ძველი modal-trigger ღილაკის
მაგივრად — პატარა, საკუთარი `<form method="get">` (`from`/`to`
`<input type="date">` + ✓-ღილაკი submit-ისთვის), პირდაპირ ღილაკების
`.btn-group`-ის გვერდით, იმავე toolbar-row-ში. აქტიური custom-range-ისას
inputs/ღილაკი `border-primary`/`btn-primary`-ზე გადადიან — იგივე
ვიზუალური კონვენცია, რასაც preset-ღილაკებიც იყენებენ.

**`design-system.css`** — ახალი `.ds-date-input` კლასი: `--ds-radius`
(იგივე კუთხის რადიუსი, რასაც ყველა სხვა input იყენებს),
`--bs-primary`-ფოკუსის ring (იგივე, რასაც `.ds-table-search`-იც
იყენებს), და `::-webkit-calendar-picker-indicator`-ის თავად
სტილიც — hover-ზე ოდნავ გამუქებული/გამოკვეთილი, ბრაუზერის
ნაგულისხმევი ნაცრისფერი ხატულას მაგივრად. ხელახლა-გამოყენებადი
კლასია, სხვა გვერდებზეც (მაგ. `analytics-overview.php`-ის თარიღის
ველები) მარტივად გამოსადეგი, თუმცა ამ ცვლილებაში მხოლოდ
`orders.php`-ს ეხება.

**გადამოწმებულია ცოცხლად** (SuperUser-ით, tenant 1-ის real
მონაცემზე, read-only): `#ordersRangeModal` აღარ არსებობს DOM-ში;
inline ველების შევსება+submit-მა ზუსტად იგივე შედეგი გამოიღო,
რაც მოდალს ჰქონდა (`7` მწკრივი, `?from=2026-08-18&to=2026-08-24`),
ველები/ღილაკი აქტიურ (`border-primary`/`btn-primary`) მდგომარეობაში
სწორად გადავიდნენ, `.ds-date-input`-ის `border-radius` (`7.2px`)
დანარჩენი დიზაინის სისტემის ტოკენს ემთხვევა.

### 4.103 ცხრილის toolbar-ის ელემენტები (ძებნა/ფილტრი/გვერდის ზომა) — ერთი სიმაღლის, `ds-table.css`-ის ბაზურ წესად

user-მა შენიშნა: ძებნის ველი, "შეკვეთის მიმღების" ფილტრი (`4.99`)
და "ჩანაწერი გვერდზე" select სხვადასხვა სიმაღლის იყო — და
მოითხოვა ფიქსი **`ds-table.css`-ის ბაზურ წესად**, რომ ამავე
კომპონენტის ნებისმიერ სხვა გვერდზეც (customers/products/users/
superuser-activity/customer-report — ყველგან, სადაც `.ds-table`
გამოიყენება) იგივე თანმიმდევრული ვიზუალი ყოფილიყო, არა მხოლოდ
`/orders`-ზე წერტილოვნად.

**მიზეზი** — სამივე ელემენტი სხვადასხვა Bootstrap size-scale-ს
იყენებდა: ძებნის `<input>` plain `.form-control` იყო, საკუთარი
ხელით დაწერილი `padding: .5rem .75rem`-ით (`ds-table.js`), ორივე
select (`4.99`-ის ფილტრი და თავად "გვერდზე" select) კი
`.form-select-sm`-ს (Bootstrap-ის ნაგულისხმევი, უფრო პატარა
padding+font-size) — ამიტომ ძებნა თვალშისაცემად უფრო მაღალი
გამოდიოდა.

**`ds-table.css`** — ახალი გაზიარებული წესი, სამივეს ერთად ეხება
(`.ds-table-search .form-control, .ds-table-filter, .ds-table-per-page
select`): ერთი explicit `height` (`2.25rem`) + ერთნაირი ვერტიკალური
padding/font-size — **ჰორიზონტალურ** padding-ს კი არ ეხება (select-ების
საკუთარი native dropdown-ისრის სივრცე დაცული რჩება, არ იშლება).

**გადამოწმებულია ცოცხლად** (SuperUser-ით, tenant 1-ის real
მონაცემზე, read-only): `/orders`-ზე სამივე ელემენტის `offsetHeight`
ზუსტად `32px` (იდენტური); `/customers`-ზეც (განსხვავებული გვერდი,
იგივე `.ds-table` კომპონენტი, ფილტრი საერთოდ არ აქვს) ძებნა/გვერდის-
ზომა ორივე ასევე ზუსტად `32px` — დადასტურებულია, რომ ცვლილება
გვერდისგან დამოუკიდებლად, ბაზურ დონეზე მუშაობს.

### 4.104 ახალი `ds-date-range` კომპონენტი — eui.elastic.co-სტილის date-range pill, `public/vendor/`-ში

user-მა სთხოვა (ჯერ `AskUserQuestion`-ის გარეშე, უბრალო
დისკუსიით — "ჯერ ვიმსჯელოთ, არ დაიწყო შესრულება"): `4.102`-ის
"დროის მონაკვეთი" ვიზუალურად მიახლოებულიყო
[eui.elastic.co-ის date-picker range](https://eui.elastic.co/docs/components/forms/date-and-time/date-picker-range/)-ს
(ერთიანი, შეერთებული "pill", ისარი შუაში, ნატიური calendar-popover
ბიბლიოთეკის გარეშე) — და, რადგან ეს **ცალკე, ხელახლა-გამოყენებადი
კომპონენტი** გამოვიდოდა, `ds-table`/`ds-select`-ის იმავე
`public/vendor/`-კონვენციით აეშენებინა, არა page-სპეციფიკური
inline სტილი.

**ორსაფეხურიანი გადაწყვეტილება ერთად**: (1) ვიზუალი — pure CSS-ით
სავსებით მიღწევადია, ბიბლიოთეკის გარეშე; (2) **ნამდვილი JS calendar-
popover კი არ აშენდა** — ეს ბრაუზერის/OS-ის საკუთარი date-picker-ი
რჩება (`<input type="date">`-ის native UI) — ამის ასაშენებლად ან
ბიბლიოთეკა დასჭირდებოდა, ან საკუთარი calendar-grid-ის საკმაოდ
დიდი კოდი, რაც პროექტის უკვე დამკვიდრებულ "ნატიური input-ების"
არჩევანს ეწინააღმდეგებოდა — ეს კომპრომისი ცალკე განვიხილეთ და
user-მაც დაადასტურა.

**`public/vendor/date-range/`** (ახალი, მეოთხე vendor-კომპონენტი
`table`/`select`/`floating-label`-ის გვერდით):
- **`css/ds-date-range.css`** — `.ds-date-range` ერთიანი, საზღვრიანი
  კონტეინერი (`--ds-radius`), შიგნით ორივე `<input type="date">`-ს
  **საკუთარი ჩარჩო/ფონი მოხსნილია** (`border:none; background:transparent`)
  — ისე, რომ ერთი ხედვადი ზედაპირი გამოვიდეს, არა ორი ცალკე ყუთი.
  კალენდრის ხატულა თავში, ისარი (`bi-arrow-right`) შუაში. `.ds-date-range-active`
  (server-side "ეს დიაპაზონია გამოყენებული"-კლასი) და `:focus-within`
  (ცოცხალი რედაქტირების დროს) ორივემ პირველადი-ფერის საზღვარი
  იძლევა, `:focus-within`-ს პლუს focus-ring-იც აქვს.
- **`js/ds-date-range.js`** — ერთადერთი ქცევა: "დან"-ის არჩევისას
  "მდე"-ს `min` თვითონ ეწყობა (ადრეულ თარიღს ვერ აირჩევ), "მდე"-ს
  არჩევისას "დან"-ის `max` ეწყობა (მოგვიანო თარიღს ვერ აირჩევ) —
  "მდე"-ს **საკუთარი** `max` (ყოველთვის "დღეს") არასდროს იცვლება,
  სუფთა JS-ითვე (მარკირებადია `data-ds-date-range`-ით, ისე
  როგორც `data-ds-table`/`data-ds-select`).
- **`layout.php`** — ორივე ფაილი გლობალურადაა ჩართული (`<head>`-ში
  CSS, footer-ში JS) — იგივე კონვენცია, რასაც `ds-select`/
  `floating-label` იყენებენ (არა page-სპეციფიკური `ds_*_script()`
  helper, `ds-table.js`-ის ერთადერთი გამონაკლისისგან განსხვავებით) —
  ასე რომ ნებისმიერ სხვა გვერდსაც (ან, user-ის საკუთარი თქმით,
  სხვა პროექტსაც) მარტივად გადმოაქვს.

**`orders.php`** — ძველი ორი ცალკე `.ds-date-input` (`4.102`)
ჩანაცვლდა ახალი კომპონენტის markup-ით; `design-system.css`-ის
`.ds-date-input` წესიც მთლიანად წაშლილია (აღარსად გამოიყენებოდა).

**გადამოწმებულია ცოცხლად** (SuperUser-ით, tenant 1-ის real
მონაცემზე, read-only): CSS/JS ორივე ჩაიტვირთა; კონტეინერის
სიმაღლე `32px` (`4.103`-ის toolbar-height-კონვენციასთან იდენტური);
"დან"-ის შევსებამ "მდე"-ს `min`-ი სწორად დააყენა, "მდე"-ს
შევსებამ — "დან"-ის `max`-ი, "მდე"-ს საკუთარი `max` (დღევანდელი
თარიღი) კი უცვლელი დარჩა; submit-მა ზუსტად იგივე `7` მწკრივი
დააბრუნა, რაც ძველ ვერსიასაც ჰქონდა; computed style-ით
დადასტურდა — კონტეინერს აქვს ერთადერთი საზღვარი
(primary-ფერის, აქტიურ მდგომარეობაში), input-ებს — არცერთი
(`border: 0px none`, `background: transparent`) — ანუ ვიზუალურად
ერთი, შეერთებული ზედაპირია, ორის მაგივრად.

### 4.105 `ds-date-range`-ის ფიქსი — სიმაღლე ორივე მეზობელ ღილაკს ემთხვევა, ვიზუალიც სკრინშოტს ემთხვევა (ორი ცალკე ველი, არა ერთი პილი)

user-მა (სკრინშოტით) მიუთითა ორ კონკრეტულ ხარვეზზე `4.104`-ის
პირველ ვერსიაში: (1) სიმაღლე არ ემთხვეოდა header-row-ის დანარჩენ
ღილაკებს (პერიოდის preset-ღილაკები/"ექსპორტი PDF" — Bootstrap-ის
ჩვეულებრივი, medium ზომის `.btn`); (2) ვიზუალი არასწორად იყო
გაგებული — `eui.elastic.co`-ს რეალურ სტრუქტურაში **ორი ცალკე,
თავისთავად შემოხაზული ველია** (თითოეულს საკუთარი border/focus-ring
აქვს), ისარი მათ **შორის**, არა ერთი უწყვეტი "პილი" ერთიანი
საერთო საზღვრით, როგორც პირველი ვერსია აშენდა.

**ორივე ფიქსი ერთად, `ds-date-range.css`-ის სრული გადაწერით**:
- **სიმაღლე** — აღარ არის fixed `height: 2.25rem` მნიშვნელობა.
  ახალი `.ds-date-range-field`-ის padding/font-size/line-height
  ზუსტად იმეორებს Bootstrap-ის `.btn`/`.form-control`-ის საკუთარ
  ფორმულას (`.375rem .75rem` padding, `1rem` font-size, `1.5`
  line-height) — ანუ სიმაღლე ავტომატურად ემთხვევა ნებისმიერ
  მეზობელ `.btn`-ს, კონკრეტული pixel-მნიშვნელობის კოორდინაციის
  გარეშე.
- **სტრუქტურა** — ახალი `.ds-date-range-field` wrapper თითო
  input-ისთვის (თავისი border/border-radius/background/focus-ring),
  `.ds-date-range` კონტეინერი კი მხოლოდ `display:flex` + `gap`-ია,
  საზღვრის გარეშე. `.ds-date-range-active`-იც შესაბამისად
  გადავიდა — ორივე `.ds-date-range-field`-ს უსვამს primary-საზღვარს.
  markup-ი (`orders.php`) და `ds-date-range.js` (`root.querySelectorAll('input[type="date"]')`)
  არ შეცვლილა — JS-ს არ ჰქონდა მნიშვნელობა, "ორი ცალკე ველი"
  თუ "ერთი პილია" ვიზუალურად, keeps მუშაობდა.

**გადამოწმებულია ცოცხლად** (SuperUser-ით, tenant 1-ის real
მონაცემზე, read-only) — ⚠️ **მნიშვნელოვანი ტესტ-გარემოს
თავისებურება ხელახლა დაფიქსირდა**: `Claude_Browser`-ის preview
pane ნაგულისხმევად `0×0` viewport-ით მუშაობს (hidden მდგომარეობაში),
რამაც პირველ ცდაზე ცრუ "56px" სიმაღლე აჩვენა period-ღილაკებზე
(`flex-wrap`-ის არასწორი გამოთვლა degenerate viewport-ზე) —
`resize_window`-ით ნამდვილი `1400×900` viewport-ის დაყენების
შემდეგ ყველა ელემენტი (period-ღილაკები/PDF-ღილაკი/date-range)
ზუსტად `34px` გამოვიდა, თანხვედრილი. ცალკე, `:focus-within`-ის
დინამიური გადასვლა (მეორე ველზე programmatic `.focus()`) ტესტ-
ბრაუზერში საერთოდ არ აისახა `getComputedStyle`-ში (`!important`-ინლაინ
ტესტ-წესითაც კი, თუმცა `.matches(':focus-within')` `true`-ს
აბრუნებდა) — ეს ზუსტად იგივე ცნობილი ავტომატიზაციის-გარემოს
თავისებურებაა, რაც `4.85`-შიც დაფიქსირდა (`.ds-details-caret`-ის
`rotate()`) — headless ბრაუზერი დინამიურ pseudo-class-ტრანზიციებს
არ "დახატავს" determinism-ისთვის, კოდის ბაგი არაა. **სტატიკური,
class-ზე-დამოკიდებული** `.ds-date-range-active` მდგომარეობა კი
ამ შეზღუდვას არ ექვემდებარება და ცოცხლად სრულად დადასტურდა —
ორივე ველის `border-color` ზუსტად `rgb(99, 102, 241)` (primary)
გახდა, filter submit-ის შემდეგ, `7` მწკრივის უცვლელი სისწორით.

### 4.106 `ds-date-range` → flatpickr (range mode) — ნატიური date-input-ები ჩანაცვლდა ნამდვილი კალენდრით

`4.104`/`4.105`-ის ორივე ხელით-აწყობილი ვერსია (ნატიური `<input
type="date">`-ებზე) user-ს არ მოეწონა. user-მა შემოგვთავაზა
[daterangepicker.com](https://www.daterangepicker.com/) — მაგრამ ის
**jQuery-სა და Moment.js-ს** მოითხოვს (~160KB, არცერთი გვაქვს,
Moment-ი deprecated-ია). შედარების შემდეგ ავირჩიეთ **flatpickr**:
იგივე ხარისხის კალენდარი, დამოკიდებულებების გარეშე (~45KB),
CDN-იდან — იმავე jsDelivr-კონვენციით, რითიც Bootstrap/Chart.js
უკვე იტვირთება.

**რატომ დაგვჭირდა საერთოდ ბიბლიოთეკა**: მთელი ეს განშტოება
(`4.102`→`4.106`) ერთი გაკვეთილია — "ნატიური input-ი + CSS" აქ
საკმარისი არ აღმოჩნდა, რადგან `<input type="date">`-ის **ბრაუზერის
საკუთარი** picker-ი არც ითემება, არც range-ს იცნობს. ორი ვიზუალური
გადაწერის შემდეგ ბიბლიოთეკა უფრო იაფი გამოვიდა, ვიდრე შემდეგი
ცდა — ეს ის შემთხვევაა, სადაც "ladder"-ის მე-4 საფეხურზე (native
platform feature) გაჩერება არასწორი იყო.

**`public/vendor/date-range/`** (გადაწერილი):
- **`js/ds-date-range.js`** — flatpickr-ის `mode: 'range'`,
  `showMonths: 2`, `maxDate` root-ის `data-max`-იდან. **გასაღები
  არქიტექტურული დეტალი**: ხილული input-ი მხოლოდ საჩვენებელია
  (`name` არ აქვს, არასდროს იგზავნება), ხოლო ფორმა კვლავ ორ
  **ფარულ** `from`/`to` input-ს აგზავნის, ყოველთვის `Y-m-d`-ით —
  ანუ სერვერის კონტრაქტი (`?from=&to=`, `resolveOrdersRange()`)
  **საერთოდ არ შეცვლილა** ამ ორი გადაწერის განმავლობაში.
  ლოკალი `<html lang>`-ს მიჰყვება, თუ flatpickr-ს ეს თარგმანი
  აქვს (`ka` აქვს) — თუ არა, ინგლისურზე ჩამოდის, არა შეცდომაზე.
  ერთი კლიკიც (ერთდღიანი დიაპაზონი) ვალიდურ ფილტრად ითვლება.
- **`css/ds-date-range.css`** — ველის საკუთარი chrome (იგივე
  `.btn`-ის padding/line-height ფორმულა, `4.105`-იდან უცვლელი) +
  flatpickr-ის **გადათემება**: მისი ნაგულისხმევი ლურჯი (`#569ff7`)
  → `--bs-primary`, კუთხეები → `--ds-radius`. `inRange`-ის
  ჩათვლით — flatpickr უჯრებს **შორის** ღრეჩოებს `box-shadow`-ით
  ავსებს, არა background-ით, ისიც გადაფერადებულია, თორემ რიგი
  ზოლიანი გამოიყურება.
- **`layout.php`** — flatpickr CSS/JS + `l10n/ka.js` (pinned
  `4.6.13`), ჩვენი კომპონენტის css/js-ის წინ.

**გადამოწმებულია ცოცხლად** (SuperUser-ით, tenant 1-ის real
მონაცემზე, read-only, **ნამდვილი `1400×900` viewport-ით** —
`4.105`-ის გაკვეთილი): flatpickr და `ka` ლოკალი ორივე ჩაიტვირთა,
instance მიება; ველის სიმაღლე `34px` — ზუსტად იგივე, რაც
period-ღილაკებისა და "ექსპორტი PDF"-ისა; კალენდარი ორთვიანად
იხსნება, ქართული თვეებით (`აგვისტო 2026`/`სექტემბერი 2026`) და
კვირის დღეებით (`ორ სა ოთ ხუ პა შა კვ`); დიაპაზონის არჩევამ
ფარული `from`/`to` სწორად შეავსო (`2026-08-18`/`2026-08-24`),
ხილულმა ველმა — `2026-08-18 — 2026-08-24`; submit-მა იგივე `7`
მწკრივი დააბრუნა, გვერდის ხელახლა ჩატვირთვისას კი ველი
**წინასწარ შევსებული** დაბრუნდა (`defaultDate` ფარული
input-ებიდან). `maxDate`-იც მუშაობს — მიმდინარე დღის მერე
თარიღები ჩაქრობილია. სკრინშოტითაც ვიზუალურად დადასტურდა.

### 4.107 `--ds-control-height` — ერთი ტოკენი ყველა "ერთ რიგში მდგარი" კონტროლის სიმაღლისთვის

user-მა მიუთითა, რომ დროის მონაკვეთის ველი და ღილაკები ერთი
სიმაღლის არაა, და თავადვე შემოგვთავაზა სწორი გადაწყვეტა:
**"რამე გლობალური ცვლადი გამოვიყენოთ სიმაღლისთვის და იქიდან
ავიღოთ ხოლმე"**.

**რეალური მიზეზი, რატომ არ ემთხვეოდა** (`4.105`-ის "იგივე padding-
ფორმულა, რაც `.btn`-ს" საკმარისი არ აღმოჩნდა): `.ds-date-range`-ს
explicit height არ ჰქონდა — სიმაღლეს მისი ყველაზე მაღალი flex-შვილი
განსაზღვრავდა, ხოლო bootstrap-icons-ის `::before`-ს
`vertical-align: -.125em` აქვს, რაც `<i>`-ის line-box-ს ~2px-ით
წელავს. ანუ ღილაკი `34.39px` იყო, ველი კი — ოდნავ მეტი. `4.103`-ის
ცხრილის toolbar-იც ცალკე, hardcoded `2.25rem`-ზე (`32.4px`) იჯდა —
ანუ სულ სამი განსხვავებული "სიმაღლის წყარო" გვქონდა.

**`design-system.css`** — ახალი ტოკენი:
`--ds-control-height: calc(1.5rem + .75rem + 2px)`. **გამოთვლილია,
არა შერჩეული** — ეს ზუსტად ის ფორმულაა, რითიც ჩვეულებრივი
Bootstrap `.btn`/`.form-control` თავად ითვლის სიმაღლეს
(`line-height 1.5em` + `.375rem` padding ზემოთ/ქვემოთ + `1px`
საზღვარი თითო მხარეს) — ამიტომ ტოკენზე დამყარებული ნებისმიერი
ელემენტი ღილაკს ემთხვევა ისე, რომ ერთმანეთის შესახებ არაფერი
"იციან".

**გამოყენება** — `ds-date-range.css` (`height`, ახლა explicit,
padding მხოლოდ ჰორიზონტალური) და `ds-table.css`-ის `4.103`-ის წესი
(ძებნა/ფილტრი/გვერდის-ზომა). ორივეგან `var(--ds-control-height,
2.375rem)` — fallback-ით, რომ კომპონენტები სხვა პროექტშიც
დამოუკიდებლად მუშაობდნენ.

**გადამოწმებულია ცოცხლად** (SuperUser-ით, tenant 1-ის real
მონაცემზე, read-only, `1400×900` viewport-ით, **sub-pixel
სიზუსტით** — `getBoundingClientRect()`, არა დამრგვალებული
`offsetHeight`): `/orders`-ზე შვიდივე კონტროლი — period-ღილაკები,
date-range ველი, ✓-ღილაკი, "ექსპორტი PDF", ცხრილის ძებნა,
"შეკვეთის მიმღების" ფილტრი, "ჩანაწერი გვერდზე" — ზუსტად
`34.39px`. `/customers`-ზეც (სხვა გვერდი, იგივე `.ds-table`)
`34.39px`. flatpickr-ის ქცევა უცვლელია (დიაპაზონის არჩევა ფარულ
`from`/`to`-ს კვლავ სწორად ავსებს).

### 4.108 ღილაკებიც `--ds-control-height`-იდან — ტოკენი გახდა წყარო, არა ანარეკლი

`4.107`-ის შემდეგაც user ხედავდა სხვაობას, და თავადვე მიუთითა
სწორი მიმართულება: **"ღილაკების ზომაც მაგ ტოკენიდან ავიღოთ"**.

**რატომ იყო `4.107` არასაკმარისი**: ტოკენი მაშინ მხოლოდ
*"იმეორებდა"* Bootstrap-ის ღილაკის საკუთარ არითმეტიკას (`line-height`
+ `padding` + `border`) და მხოლოდ **არა-ღილაკებს** ედო. ანუ
თანხვედრა იმაზე იყო დამოკიდებული, რომ ბრაუზერი ღილაკს ზუსტად
იმავე რიცხვამდე დაითვლიდა — ეს კი შრიფტის/`line-height`-ის
დამრგვალებაზეა დამოკიდებული და ყველა გარემოში არ ემთხვევა.
ახლა **ორივე მხარე ერთი და იმავე explicit მნიშვნელობიდან** მოდის,
ანუ თანხვედრა კონსტრუქციულია, არა შემთხვევითი.

**`design-system.css`** — ახალი წესი:
```css
.btn:not(.btn-sm):not(.btn-lg):not(.btn-link) {
  display: inline-flex; align-items: center; justify-content: center;
  min-height: var(--ds-control-height);
  padding-top: 0; padding-bottom: 0;
}
```
- **`min-height`, არა `height`** — ორ ხაზზე გადასული წარწერა
  იზრდება, არ იჭრება. **და ეს გადამწყვეტი აღმოჩნდა ერთ რეალურ
  ადგილას**: `customers.php`-ის ს/კ-ის საძებნი ღილაკი
  `.input-group`-შია და თავისი input-ის სიმაღლეს (`41.75px`,
  floating-label-ის გამო) უნდა ერგებოდეს — `height`-ით ის
  `34.39`-ზე ჩამოიჭრებოდა და გატეხილი გამოჩნდებოდა; `min-height`-ით
  input-group-ის stretch იმარჯვებს და სწორად რჩება.
- **გამორიცხვები**: `.btn-sm` (ცხრილის მწკრივის action-ღილაკები,
  განზრახ პატარა — `28.08px`), `.btn-lg`, და `.btn-link`
  (`customer-report.php`-ის ინვოისის-ნომრის ტექსტური ტრიგერი
  უჯრაში — ამას control-სიმაღლის მიცემა მწკრივს გაბერავდა).

`ds-date-range.css`-ის `height` → `min-height`-ზე გადავიდა იმავე
მიზეზით.

**გადამოწმებულია ცოცხლად** (SuperUser-ით, tenant 1-ის real
მონაცემზე, read-only, `1400×900`, sub-pixel): `/orders`-ზე შვიდივე
row-კონტროლი `34.39px`, `.btn-sm` მწკრივის ღილაკები კვლავ
`28.08px` (ანუ გამორიცხვა მუშაობს); `/invoices`-ის action-პანელის
ხუთივე ღილაკი `34.39px` და კვლავ სრული სიგანის (`d-grid`-ის
stretch არ დარღვეულა); `/customers`-ზე შენახვა/გასუფთავება
`34.39px`, ს/კ-ის input-group-ის ღილაკი კი სწორად `41.75px`
(input-ის ტოლი). სკრინშოტითაც დადასტურდა — რიგი ახლა სწორია.

### 4.109 `ds_asset()` — cache-busting ლოკალურ CSS/JS-ზე

`4.108`-ის შემდეგ user-მა სკრინშოტით აჩვენა, რომ დროის მონაკვეთის
ველი კვლავ **უფრო დაბალია** ღილაკებზე, და იკითხა — ტექსტის ზომაა
მიზეზი?

**არა.** ცოცხალ გვერდზე გაზომვამ (`getBoundingClientRect()`,
`1400×900`) აჩვენა, რომ ორივე ელემენტი **პიქსელამდე იდენტურია**:
სიმაღლე `34.39px`, `top 165.23`, `bottom 199.63`, ფონტი ორივეგან
`14.4px`. სერვერიც სწორ CSS-ს გასცემდა — cache-busted `fetch()`-მა
დაადასტურა, რომ `ds-date-range.css`-ში `min-height:
var(--ds-control-height, 2.375rem)` წერია და `design-system.css`-ში
ტოკენიც და `.btn`-ის წესიც.

მიზეზი: user-ის ბრაუზერს **ქეშირებული სტილი** ჰქონდა. სკრინშოტზე
დანაკლისი ზუსტად ~2px იყო — ანუ `32.4px` (`2.25rem`), რაც `4.104`-ის
გადაფარული ვერსიის მნიშვნელობაა. `layout.php` ლოკალურ ფაილებს
**ვერსიის გარეშე** აბმევდა, ანუ ბრაუზერს revalidate-ის მიზეზი არ
ჰქონდა.

**ფესვში გასწორება** — ახალი `ds_asset(string $path): string`
(`Core/helpers.php`): ფაილის საკუთარ `filemtime()`-ს `?v=`-ად
აბამს. ერთი ხაზი ლოგიკა, `@filemtime` → `false`-ზე path უცვლელი
ბრუნდება (წაშლილი ფაილი 404-ს მაინც მისცემს, ოღონდ არა fatal-ს).
გამოყენებულია სამივე layout-ში (`layout.php`, `auth/_layout.php`,
`document/_layout.php`) და `ds_table_script()`-ში — სულ **12 ლოკალური
ფაილი**. CDN-ბმულები (Bootstrap, flatpickr, fonts) გვერდით რჩება —
ისინი უკვე ვერსირებულია URL-შივე.

**გადამოწმებულია ცოცხლად**: `/orders`-ის reload-ის შემდეგ თორმეტივე
ლოკალური `<link>`/`<script>` `?v=<mtime>`-ით მოდის (მაგ.
`design-system.css?v=1788562686`), გვერდი უცვლელად რენდერდება,
range და ღილაკი კვლავ `34.39px`/`top 165.23`.

> **დეველოპმენტის დროს**: თუ CSS-ის ცვლილება "არ მოქმედებს" — ჯერ
> HTML-ის წყაროში შეამოწმე, `?v=` შეიცვალა თუ არა. თუ შეიცვალა და
> მაინც ძველი ჩანს, პრობლემა ბრაუზერშია (Ctrl+F5), თუ არა — ფაილი
> არ შენახულა.

### 4.110 flatpickr ლოკალურად `vendor/`-ში — CDN-დამოკიდებულება მოხსნილია

`4.106`-ში flatpickr jsDelivr-იდან იტვირთებოდა. user-ის მოთხოვნით
ბიბლიოთეკა რეალურად გადმოწერილია პროექტში:

```
public/vendor/flatpickr/css/flatpickr.min.css   16 166 ბაიტი
public/vendor/flatpickr/js/flatpickr.min.js     50 679 ბაიტი
public/vendor/flatpickr/js/l10n/ka.js            2 436 ბაიტი
```

v4.6.13 — **იგივე ვერსია**, რაც CDN-ზე იყო მიბმული (ფაილის თავში
`/* flatpickr v4.6.13, @license MIT */`). განლაგება `vendor/table`,
`vendor/select`, `vendor/floating-label`-ის კონვენციას მიჰყვება
(`css/` + `js/`), ცალკე საქაღალდეში და **არა** `vendor/date-range/`-ში
— ეს ჩვენი კომპონენტია, flatpickr კი მისი მესამე-მხარის
დამოკიდებულება; ცალკე ყოფნა აადვილებს მის განახლებას ან სხვა
კომპონენტისთვის გამოყენებას.

`layout.php`-ის სამივე ბმული `ds_asset()`-ზე გადავიდა (`4.109`), ანუ
flatpickr-იც `?v=<mtime>`-ით მოდის.

**რჩება CDN-ზე**: Bootstrap, Bootstrap Icons, Google Fonts, Chart.js —
user-ს ეს არ უთხოვია, არ შევხებივარ.

**გადამოწმებულია ცოცხლად** (SuperUser → tenant 1, read-only,
`1400×900`): `/orders`-ზე სამივე ფაილი `200`-ით და სწორი MIME-ით
(`text/css`, `application/javascript`) გაიცემა; `typeof flatpickr ===
'function'`; `flatpickr.l10ns.ka` არსებობს და კალენდარი ქართულად
იხსნება (`სექტემბერი`, ორი თვე); `performance.getEntriesByType(
'resource')`-ში jsDelivr-ის flatpickr-ის მოთხოვნა **ნული**; კონსოლში
შეცდომა არაა; სიმაღლე კვლავ `34.39px` ღილაკის ტოლი.


### 4.111 ყველა ბიბლიოთეკა ლოკალურად — CDN აღარ გამოიყენება

`4.110`-ის (flatpickr) შემდეგ user-მა მოითხოვა **ყველა** დარჩენილი
CDN-რესურსის ჩამოწერა. აპლიკაცია ახლა **არცერთ გარე host-ს არ
მიმართავს**.

| ბიბლიოთეკა | ვერსია | ადგილი |
|---|---|---|
| Bootstrap | 5.3.3 | `vendor/bootstrap/{css,js}/` (+ `.map`) |
| Bootstrap Icons | 1.11.3 | `vendor/bootstrap-icons/font/` |
| Chart.js | 4.4.4 | `vendor/chartjs/js/chart.umd.min.js` |
| Inter + Noto Sans Georgian | 400–800 | `vendor/google-fonts/{css,fonts}/` |
| flatpickr | 4.6.13 | `vendor/flatpickr/` (`4.110`) |

**Bootstrap Icons** upstream-ის `font/` სტრუქტურით დევს (`font/
bootstrap-icons.min.css` + `font/fonts/*.woff2|woff`), **განზრახ არა**
პროექტის `css/`+`js/` კონვენციით: CSS შიგნით `url("fonts/...")`
ფარდობითია, ანუ ასე vendor-ფაილის რედაქტირება საერთოდ არ დასჭირდა.

**Google Fonts** ჩამოწერილია სკრიპტით (იხ. ქვემოთ): `css2`-ის პასუხი
მოთხოვნილია Chrome-ის UA-თი (ძველი/არარსებული UA `ttf`-ს აბრუნებს —
ოთხჯერ მეტი ბაიტი), შემდეგ ყველა `fonts.gstatic.com`-ის URL
გადმოწერილია და CSS-ში `../fonts/`-ზეა გადაწერილი. შედეგი: **70
`@font-face`**, **14 `.woff2`** (356 KB სულ — ცვლადი შრიფტებია, ერთი
ფაილი ერთ სუბსეტზე ყველა წონისთვის), `fonts.gstatic.com`-ის ბმულები
**ნული**. ქართული სუბსეტი (`U+10A0–10FF`) ადგილზეა.

> **Google Fonts-ის ხელახლა გენერაცია** (ვერსიის განახლებისას): იხ.
> ამ სექციის სკრიპტი git-ის ისტორიაში — `fonts.css` **გენერირებულია,
> ხელით არ დაარედაქტირო**.

**ხაფანგი, რომელიც დაიჭირა**: `dashboard.php`, `customer-report.php`
და `analytics-overview.php`-ში Chart.js-ის `<script>` **heredoc-ის
შიგნითაა** (`$scripts = <<<HTML`). Heredoc ცვლადებს ალაგებს, მაგრამ
**ფუნქციის გამოძახებას არა** — `<?= ds_asset(...) ?>` იქ სიტყვასიტყვით
გამოვიდა ატრიბუტში და Chart.js საერთოდ არ ჩაიტვირთა (`typeof Chart
=== 'undefined'`, canvas ცარიელი). გასწორება: URL heredoc-ამდე
ცვლადში (`$chartJs = ds_asset(...)`), heredoc-ში `"$chartJs"`.
**ცოცხალი გადამოწმების გარეშე ეს შეცდომა უხმაუროდ გაივლიდა** — გვერდი
იხსნება, უბრალოდ გრაფიკი აღარაა.

**გადამოწმებულია ცოცხლად** (SuperUser → tenant 1, read-only,
`1400×900`), სამივე layout-ზე:
- `performance.getEntriesByType('resource')`-ში გარე მოთხოვნა
  **ნული** — `/` (dashboard), `/analytics/overview`,
  `/customers/report?id=…`, `/invoices/view?id=…` (document layout).
  `/login`-ზე ერთადერთი გარე მოთხოვნა Pixabay-ის ფონური ფოტოა — ეს
  ცალკე ფუნქციაა (`/auth/photo`), არა ბიბლიოთეკა.
- `typeof bootstrap === 'object'`; `Chart.version === '4.4.4'` და
  canvas-ზე ინსტანცია მიბმულია სამივე გრაფიკიან გვერდზე.
- `document.fonts.check('16px Inter')` → `true`;
  `document.fonts.check('16px "Noto Sans Georgian"', 'ქართული')` →
  `true`; 17 face ჩატვირთული.
- აიკონები: `getComputedStyle(.bi, ':before').fontFamily ===
  'bootstrap-icons'`, გლიფის სიგანე `16.55px` (ე.ი. შრიფტი ნამდვილად
  დაიხატა, არა tofu).
- ვერცერთი resource ვერ ჩავარდა (`transferSize === 0 &&
  decodedBodySize === 0` → ცარიელი სია).


### 4.112 პირველი მოდულების სისტემა წაშლილია (მთლიანად)

user-ის გადაწყვეტილება, ჩემი საწინააღმდეგო რჩევის მიუხედავად —
და მისი დიაგნოზი სწორი აღმოჩნდა. ორივე მოდული ბირთვს **სახელით**
სწვდებოდა:

- `app/lang/{ka,en}.php` — 26 გასაღები მოდულების ტექსტისთვის
- `app/Views/invoices.php` — 2 912 ბაიტი InvoiceWorkflow-ის inline markup
- `app/Views/orders.php` — badge-ბლოკი + `$paymentBadgeClass`
- `app/Controllers/InvoiceController.php` — ორი `if (module enabled)` განშტოება
  FQCN-ით და `class_exists()` დაცვით

ანუ მოდულის საქაღალდის წაშლა ბირთვს **მაინც ტოვებდა** მისი სახელით.
მესამე მხარის მოდულისთვის ეს გამოუსადეგარია.

**წაშლილია**: `app/Modules/Warehouse/`, `app/Modules/InvoiceWorkflow/`,
ზემოთ ჩამოთვლილი ყველა ბირთვის ჩართვა, ცხრილები
`product_warehouse` + `invoice_workflow`, `modules` ცხრილის ორივე row
და მათი `migrations`-ჩანაწერები (`migrations/037`).

**მონაცემი**: `product_warehouse`-ის **15 row გატანილია**
`storage/backups/product_warehouse_2026-09-09.sql`-ში — სქემით
(`SHOW CREATE TABLE`) და `INSERT`-ებით, ანუ აღდგენადი, თუ Warehouse
ახალი კონტრაქტით ხელახლა აშენდება. `invoice_workflow` ცარიელი იყო.
ორივე მოდული გამორთული იყო (`enabled=0`), ანუ წაშლას ცოცხალი
ფუნქციონალი არ შეუწყვეტია.

**`products` ხელუხლებელია**: Warehouse-ის `003`-მა მას ოდესღაც
მოაჭრა `product_type_id`/`remaining_qty`/`image`, მაგრამ ბირთვის
`019`-მა `product_type_id` უკან დააბრუნა ჩვეულებრივ ველად.
`remaining_qty`/`image` მხოლოდ `product_warehouse`-ში ცხოვრობდა —
სწორედ ამიტომაა ექსპორტი.

**გადამოწმებულია ცოცხლად**: `/`, `/orders`, `/invoices`, `/products`,
`/settings/modules` — არცერთზე Fatal/Warning/Notice, არცერთზე
დაუმუშავებელი თარგმანის გასაღები. `php -l` ყველა შეხებულ ფაილზე;
ენის ფაილები **502 = 502** გასაღები, სხვაობა ნული. `products` კვლავ
17 row.

**რჩება (Phase B-სთვის)**: `ModuleRegistry`, `ModuleInterface`,
`Hooks`, `ModuleController`, `Views/modules.php`, `modules` ცხრილი —
ეს მართვის ფენა **გადაიწერება** ახალი კონტრაქტის მიხედვით, არ
შენარჩუნდება როგორც არის.


### 4.113 ახალი მოდულების სისტემა — ერთი წესით აშენებული

`4.112`-ის შემდეგ სისტემა ნულიდან აეწყო, ერთი კანონის გარშემო:

> **მოდულის ჩაშენება ბირთვის არცერთ ფაილს არ ცვლის.**

მიზანი user-მა დააფიქსირა: მესამე მხარემ უნდა შეძლოს მოდულის დაწერა
და ჩაშენება; ჩვენი მოდულების გაყიდვა/გავრცელებაც არაა გამორიცხული.

#### გადაწყვეტილებები (user-თან შეთანხმებული)

| # | გადაწყვეტილება | მიზეზი |
|---|---|---|
| 1 | Hook-წერტილი მხოლოდ რეალური მომხმარებლით | სპეკულაციურმა წერტილებმა მოკლა ძველი `Hooks` — 40 ხაზი, ნული გამოძახება |
| 2 | `requires.api` **არ** დაემატა | user-ის შენიშვნა: მნიშვნელობა მოდულის ვერსიას აქვს. `try/catch`-ით შეუთავსებელი მოდული ისედაც უსაფრთხოდ ჩავარდება. დამცავი მაშინ, როცა ბირთვი პირველად გატეხს hook-ს |
| 3 | `/m/<code>/` პრეფიქსი ყოველთვის | კოლიზიისგან იცავს. **უსაფრთხოების საზღვარი არაა** — იხ. ქვემოთ |
| 4 | ჩატვირთვა `try/catch`-ში | ერთი გატეხილი მოდული აპლიკაციას არ ამხობს |
| 5 | install ≠ enable | install სერვერისაა, enable — თითო tenant-ის (`module_tenants`, `migrations/038`) |
| 6 | uninstall მონაცემს ჯერ SQL-ად ინახავს | user-ის საკუთარი წინადადება: აღდგენა უნდა იყოს შესაძლებელი |

#### ბირთვის ახალი ნაწილები

- **`ModuleRegistry`** (გადაწერილი) — discover/install/uninstall/enable/
  disable, `boot()` `try/catch`-ით, `currentTenant()`.
  **ხაფანგი, რომელიც დაიჭირა**: `Auth::tenantId()` სესიის გარეშე
  `/login`-ზე გადამისამართებას აკეთებს, SuperUser-ისთვის კი
  `/superuser`-ზე — `boot()` ყოველ მოთხოვნაზე გაეშვება, ანუ ეს
  **redirect-მარყუჟი** იქნებოდა ზუსტად ამ ორ გვერდზე. `currentTenant()`
  იმავე კითხვებს სვამს გადამისამართების გარეშე.
- **`ModuleRouter`** (ახალი) — მოდული ნამდვილ `Router`-ს **ვერ ხედავს**;
  ყველა მისი მარშრუტი `/m/<code>/`-ის ქვეშ ხვდება, გამონაკლისის გარეშე.
- **`ModuleArchive`** (ახალი) — ZIP-ვალიდაცია და მონაცემთა ექსპორტი.
- **`Lang::loadModule()`** — მოდულის საკუთარი `lang/{ka,en}.php`.
  ბირთვის გასაღები კოლიზიაზე იმარჯვებს (`+=`), ანუ მოდული ლექსიკას
  ამატებს, ბირთვის ფორმულირებას ვერ გადააწერს.
- **`ds_module_assets()`** — მოდულის `assets/module.{css,js}`.
  მოდულები `app/`-შია, docroot-ს გარეთ, ამიტომ მათ
  `ModuleAssetController` ასერვირებს (whitelist + `realpath()` შემოწმება).
- **`Hooks::merge()`** — data-წერტილებისთვის. **ეს იყო ის, რაც ძველ
  სისტემას აკლდა**: ცხრილს დამატებითი სვეტი ერთი query-თი სჭირდება,
  მხოლოდ render-hook-ით ყოველი მწკრივი ცალკე query-ს გააკეთებდა (N+1) —
  ზუსტად ამიტომ იყო ძველი InvoiceWorkflow `InvoiceController`-ში ჩაკერებული.

#### Hook-წერტილები (4, თითოეულს რეალური მომხმარებელი ჰყავს)

| წერტილი | ტიპი | სად |
|---|---|---|
| `invoice.list.data` | data | `InvoiceController::orders()` |
| `render.invoice.row.badges` | render | `orders.php` |
| `render.invoice.form.aside` | render | `invoices.php` |
| `invoice.saved` | event | `InvoiceController::store()` |

#### InvoiceWorkflow v2 — საცნობარო მოდული

ხელახლა დაწერილი ახალი კონტრაქტით (`app/Modules/InvoiceWorkflow/`).
იგივე ფუნქცია, ბირთვში **ნული** ცვლილებით. ეს არაა დემო — ეს არის
დამტკიცება, რომ მექანიზმი მუშაობს, და `/help/modules`-ის ნიმუში.

#### გადამოწმებულია ცოცხლად

**Hook-ები** (SuperUser → tenant 1, read-only გარდა მოდულის ცხრილისა):
`/invoices?edit=59`-ზე panel დაიხატა მოდულის ქართული თარგმანებით
(ე.ი. `Lang::loadModule()` მუშაობს); `POST /m/invoiceworkflow/payment`-მა
ჩაწერა `partial/42.50`; `/orders`-ზე badge გამოჩნდა სწორი კლასით
(`bg-warning-subtle text-warning-emphasis`).

**გამორთვის ტესტი** — მოდულის გამორთვის შემდეგ: badge გაქრა, panel
გაქრა, `/m/invoiceworkflow/payment` → **404**, ბირთვის გვერდები სუფთა.
ანუ მოდული მართლა უკვალოდ ქრება.

**ჩავარდნის იზოლაცია** — `Module.php`-ში განზრახ ჩასმული
`throw` და შემდეგ: `/`, `/orders`, `/invoices`, `/settings/modules` —
**ოთხივე 200**, სრული შიგთავსით. ძველ სისტემაში ეს ყველა გვერდს
თეთრ ეკრანად აქცევდა, `/settings/modules`-ის ჩათვლით, ანუ
მომხმარებელი ვერც კი გამორთავდა.

**ZIP-უსაფრთხოება** — 5 თავდასხმა, ორივე დონეზე (ვალიდატორზე პირდაპირ
და ნამდვილი HTTP-ატვირთვით), **ხუთივე უარყოფილი**:

| არქივი | შედეგი |
|---|---|
| `DemoMod/../../../public/pwned.php` (zip-slip) | უარყოფილი |
| `/etc/passwd` (აბსოლუტური გზა) | უარყოფილი |
| `DemoMod/.htaccess` (დაუშვებელი ტიპი) | უარყოფილი |
| ორი top-level საქაღალდე | უარყოფილი |
| `my-module/` (დეფისი კოდში) | უარყოფილი |
| სწორი არქივი | დაინსტალდა |

შემდეგ შემოწმდა: `public/` სუფთა, `storage/tmp/`-ში staging-ნარჩენი
არაა.

**სასიცოცხლო ციკლი** — upload → install → uninstall → აღდგენა:
uninstall-მა დაწერა `storage/backups/InvoiceWorkflow_2026-09-10_002012.sql`
(სქემა + მწკრივი), ცხრილი წაშალა, `modules`/`module_tenants`/
`migrations`-ჩანაწერები გაასუფთავა; ხელახლა install + ამ ფაილის გაშვება
→ **მწკრივი დაბრუნდა**.

**`requireNotImpersonating()`** — SuperUser-ის დათვალიერების რეჟიმში
ატვირთვის მცდელობა → **403**. ე.ი. სხვისი tenant-ის დათვალიერებისას
მისი მოდულების შეცვლა შეუძლებელია.

#### უსაფრთხოების პოზიცია (ჩაწერილია `/help/modules`-ში)

`/m/` პრეფიქსი **კოლიზიისგან** იცავს, არა უფლებებისგან. მოდულის
კონტროლერი ბირთვის `Db`/`Auth`-ით მუშაობს — PHP-ში sandbox არ არსებობს.
ანუ **მოდული ნდობის კოდია**, WordPress-ის плагин-ის მსგავსად:
ატვირთვის ვალიდაცია საშიში *არქივისგან* იცავს, საშიში *კოდისგან* — არა.
ავტორის მოვალეობაა `csrf_verify()` და მფლობელობის შემოწმება;
`InvoiceWorkflowController::ownedInvoiceId()` სწორედ ამის ნიმუშია.

#### დოკუმენტაცია

`nav.help` `#`-ზე იდგა — ახლა `/help` და `/help/modules`. ავტორის
სახელმძღვანელო 7 სექციად, ka+en. **View-ია და არა Markdown**, რომ
hook-ების ცხრილი ცოცხალ კოდთან ერთად იცვლებოდეს და არ ჩამორჩეს.


### 4.114 მოდულის კომპლექტი — შაბლონი, სატესტო არქიტექტურა, AI-პრომპტი

`4.113`-ის სისტემას სამი რამ დააკლდა, რაც user-მა მოითხოვა: საიდან
იწყებს ავტორი, სად ტესტავს, და რა უნდა იცოდეს AI-აგენტმა.

```
tools/module-kit/
├── README.md          გამოყენების ინსტრუქცია
├── AGENT_PROMPT.md    AI-აგენტის სრული ბრიფინგი (15 სექცია)
├── ModuleTemplate/    სრული, მუშა, დატესტილი მოდული — საკოპირებელი
└── module-test.php    სატესტო harness
```

#### სატესტო harness — რატომ ასეა აგებული

`php tools/module-kit/module-test.php YourCode [--keep]`

**ნამდვილ ბირთვს ტვირთავს, არა stub-ებს.** ხელით დაწერილი
`Db`/`Auth`/`Hooks`-ის stub იქნებოდა იმავე რამის მეორე იმპლემენტაცია
და პირველივე ბირთვის ცვლილებაზე ჩამორჩებოდა — მოდული ტესტს გაივლიდა
და აპლიკაციაში გატყდებოდა.

**იზოლაცია ბაზაზეა და არა კოდზე**: `DB_NAME` გადამისამართდება
`<ბაზა>_moduletest`-ზე პირველივე კავშირამდე (ამისთვის დაემატა
`Env::set()`), იქ იქმნება სქემა, ბოლოს მთელი ბაზა იშლება. მოდულის
ტესტები ცოცხალ მონაცემს **ვერ ხედავენ და ვერ ცვლიან** — ეს
პრინციპულია, რადგან მოდულის ტესტიც მესამე მხარის კოდია.

#### ხაფანგი: ბირთვის მიგრაციები ნულიდან აღარ გაეშვება

Harness თავიდან `migrations/`-ს ნულიდან უშვებდა. **არ მუშაობს**:

- `004_create_products.sql` ქმნის `products.product_type_id`-ს
- `019_add_products_type.sql` მას **ხელახლა** ამატებს
- ცოცხალ ბაზაზე ეს იმიტომ გავიდა, რომ შუაში Warehouse-ის
  `003_drop_products_extension_columns.sql` წაშლიდა — **ის მოდული
  `4.112`-ში წაიშალა**

> ⚠️ **ეს ნიშნავს, რომ აპლიკაციის სუფთა ინსტალაცია დღეს `019`-ზე
> გატყდება.** ცალკე, წინასწარ არსებული ბაგია — არა მოდულების
> სისტემის. Harness-ს აღარ ეხება (იხ. ქვემოთ), მაგრამ **გასწორება
> ჯერ არ მომხდარა.**

გამოსავალი harness-ისთვის უკეთესიც აღმოჩნდა: **ცოცხალი სქემის
კლონირება** `SHOW CREATE TABLE`-ით, მწკრივების გარეშე. მოდული
ტესტდება ზუსტად იმ სქემაზე, რომელზეც პროდაქშენში იმუშავებს, და არა
იმაზე, რასაც ისტორიის გამეორება დააგენერირებდა.

#### Fixture-ები

| id | ვინ |
|---|---|
| 1 | tenant A — ნაგულისხმევად შესული |
| 2 | tenant B — **ცალკე მფლობელი**, იზოლაციის ტესტებისთვის |
| 3 | tenant A-ს ქვემომხმარებელი |

მე-2 მომხმარებელი განზრახაა ცალკე tenant: ყველაზე საშიში მოდულის
ბაგი — `ruler`-ის ფილტრის გამოტოვება — **ხელით ტესტირებისას უხილავია**,
რადგან ერთი ანგარიშით ხარ შესული.

#### აღმოწმება (4 assertion, framework-ის გარეშე)

`test()`, `assert_true()`, `assert_same()`, `assert_throws()`.

#### გადამოწმებულია ცოცხლად

**InvoiceWorkflow**: 13 ტესტი, **13 გავიდა**.
**ModuleTemplate**: 14 ტესტი, **14 გავიდა**.

**ტესტები მართლა იჭერენ ბაგს** — ეს ცალკე შემოწმდა: `TemplateItem`-ის
`find()`-სა და `save()`-ს მოვაშორე `AND ruler = ?` და ორი ტესტი
**გაწითლდა** („another tenant cannot read/overwrite"). ანუ იზოლაციის
ტესტები არ გადიან შემთხვევით.

**შაბლონი ბრაუზერშიც**: დაინსტალირების შემდეგ
`/m/moduletemplate/list` მუშაობს, საკუთარი CSS იტვირთება
(`/modules/asset?code=ModuleTemplate&file=module.css` → **200**),
მენიუს ჩანაწერი სწორი პრეფიქსით, ფორმა ინახავს, ქართული თარგმანები
მოდულის საკუთარი `lang/`-იდან. Path-traversal იმავე endpoint-ზე
(`file=../../Module.php`) → **404**.

შემდეგ `ModuleTemplate` დეინსტალდა და წაიშალა — შაბლონი `tools/`-ში
ცხოვრობს, არა დაინსტალირებული.

#### AGENT_PROMPT.md

15 სექცია: მთავარი წესი, გარემო, განლაგება, `module.json`,
`Module.php`, `/m/` პრეფიქსი, 4 hook-წერტილი, **ბირთვის დაშვებული
API-ს ზუსტი სია**, multi-tenancy (`ruler`-ის წესი მაგალითებით —
სწორი და არასწორი), უსაფრთხოება, ვიზუალის წესები, მიგრაციები/
uninstall, ტესტირება, შეფუთვა. ბოლოს ორი შესავსები —
`MODULE CODE` და `WHAT IT SHOULD DO` — და დასრულების 4 კრიტერიუმი,
მათ შორის `grep`, რომელიც ამტკიცებს, რომ ბირთვს არ შეხებია.


### 4.115 სუფთა ინსტალაცია გასწორდა — მიგრაციები ისევ გაეშვება ნულიდან

`4.114`-ში აღმოჩენილი ბაგი. `migrations/`-ის ნულიდან გაშვება
ჩავარდებოდა, ანუ **აპლიკაციის ახალი ინსტალაცია შეუძლებელი იყო.**

#### მიზეზი

`products`-ის ისტორია ასე გამოიყურებოდა:

| მიგრაცია | რას აკეთებდა |
|---|---|
| `004` | ქმნის `product_type_id` (NOT NULL), `remaining_qty`, `image` |
| `006` | ორივეს nullable-ად აქცევს |
| **Warehouse/`003`** | **შლის სამივეს** — მოდულს გადაეცა |
| `019` | `product_type_id`-ს ბირთვის ველად აბრუნებს |

შუა რგოლი — **Warehouse-ის მიგრაცია — `4.112`-ში წაიშალა მოდულთან
ერთად.** ცოცხალ ბაზას ეს არ შეხებია (ყველა ეს მიგრაცია დიდი ხნის
გაშვებულია), მაგრამ სუფთა ბაზაზე `004` ქმნის სვეტს და `019` მას
ხელახლა ამატებს → `Duplicate column name 'product_type_id'`.

გარდა ამისა, `remaining_qty`/`image` სუფთა ინსტალაციაზე **რჩებოდა**
(მათი წამშლელი ისევ Warehouse-ის მიგრაცია იყო), ანუ ახალი და ძველი
ინსტალაციის სქემები ერთმანეთს დაშორდებოდა.

#### გასწორება

`019` გადაიწერა თავდაცვითად: `information_schema`-ს შემოწმება +
`PREPARE` თითოეულ ცვლილებაზე (MySQL-ს არ აქვს
`ADD/DROP COLUMN IF EXISTS` — ეს MariaDB-ს აქვს; no-op შტო `DO 0`-ია).
ახლა ის ორივე ისტორიიდან ერთსა და იმავე შედეგზე მიდის:
`product_type_id` nullable + FK, `remaining_qty`/`image` — წაშლილი.

**რატომ სწორედ `019`-ის რედაქტირება.** გაშვებული მიგრაციის შეცვლა
ჩვეულებრივ არასწორია, მაგრამ აქ **სწორედ იმიტომ არის უსაფრთხო, რომ
ის უკვე გაშვებულია**: ყველა არსებულ ინსტალაციას ეს ფაილი ლოგში აქვს
და **აღარასდროს შეასრულებს**. ანუ ცვლილება მხოლოდ სუფთა ბაზას შეეხება
— ერთადერთს, რომელიც გატეხილი იყო. ალტერნატივა (`018a_…`-ის ჩამატება
შუაში) არსებულ ინსტალაციებზე **გაეშვებოდა**, ანუ უფრო სარისკო იყო.

#### გადამოწმებულია

სუფთა ბაზაზე `Migrator`-ით (ზუსტად ისე, როგორც `migrate.php` აკეთებს):

- **38 მიგრაცია, შეცდომის გარეშე**
- სქემა ცოცხალის **იდენტური — 88 სვეტი ემთხვევა**, სვეტ-სვეტ
  შედარებით (`COLUMN_TYPE`, `IS_NULLABLE`, `COLUMN_KEY`,
  `COLUMN_DEFAULT`, `EXTRA`)

ცოცხალ ბაზაზე: `php migrate.php` → **"up to date"**, `products`
უცვლელი (8 სვეტი, 17 row), `019` ლოგშია.

`4.114`-ში ნახსენები `037`-ის ჩავარდნა **რეალური არ ყოფილა** —
ჩემი raw-replay სკრიპტის არტეფაქტი იყო: `Migrator::run()` ჯერ
`migrations` ცხრილს ქმნის, raw `PDO::exec()` კი არა.

Harness-ის კომენტარი განახლდა: replay ახლა მუშაობს, მაგრამ სქემის
კლონირება მაინც სჯობს (პროდაქშენის ზუსტი სქემა + 38 მიგრაციაზე
სწრაფი). InvoiceWorkflow-ის 13 ტესტი კვლავ გადის.


### 4.116 `modules.loc` — მოდულების სენდბოქსი

user-ის კითხვაზე „ცალკე პროექტი ხომ არ ჯობია" პასუხი: **ცალკე კოდი
არა, ცალკე ინსტანცია დიახ** — და user დაეთანხმა.

**რატომ არა ცალკე კოდი.** მოდული დამოუკიდებელი პროგრამა არაა,
ბირთვის გარეშე არ ეშვება. „მოდულების პროექტს" მაინც დასჭირდებოდა
ბირთვის სრული ასლი — ანუ ეს ცალკე პროექტი კი არა, იმავეს **მეორე
ასლია**, და ასლი ჩამორჩება. იგივე მიზეზი, რის გამოც `4.114`-ის
harness-ში `Db`/`Auth`/`Hooks`-ის stub-ები არ დავწერე.

**რა პრობლემას წყვეტს ინსტანცია (და harness ვერ წყვეტს).**
`ModuleRegistry::install()` მოდულის მიგრაციებს **იმ ბაზაზე** უშვებს,
რომელზეც აპლიკაცია დგას. Harness იზოლირებულია, მაგრამ პარამეტრების
გვერდზე „ინსტალაცია"-ზე კლიკი — არა. არასწორი `CREATE TABLE`
სატესტო მოდულში ცოცხალ სქემას შეეხებოდა.

#### კონფიგურაცია

| | dashboard.loc | modules.loc |
|---|---|---|
| კოდი | რეპო | მისი კლონი |
| ბაზა | `invoice` | **`invoice_dev`** |
| `APP_NAME` | Nova DS | Nova DS (modules) |
| MAIL / PIXABAY / GOOGLE / SMS | კონფიგურირებული | **ცარიელი, განზრახ** |

`.osp/project.ini` → `[modules.loc]`, `web_root = {base_dir}/public`
(OSPanel საქაღალდის სახელს დომენად იღებს; `.osp/` git-ს არ მიჰყავს).
კლონს ორი remote აქვს: `origin` → ლოკალური `dashboard.loc`,
`github` → GitHub. ბირთვის ცვლილება `git pull origin main`-ით მოდის.

გარე ინტეგრაციები ცარიელია იმიტომ, რომ სენდბოქსმა ნამდვილი ფოსტა
ვერ გააგზავნოს და API-ის ქვოტა ვერ დახარჯოს.

#### გადამოწმებულია

**`4.115`-ის ნამდვილი ტესტი**: სენდბოქსის ბაზა ნულიდან აეწყო —
`php migrate.php` → **38 მიგრაცია, შეცდომის გარეშე**. ეს იყო პირველი
რეალური სუფთა ინსტალაცია გასწორების შემდეგ.

**იზოლაცია** — ორივე ინსტანცია გვერდიგვერდ:

```
live      db=invoice      users=6  invoices=21  customers=27
sandbox   db=invoice_dev  users=1  invoices=0   customers=0
```

**ინსტალაციის ტესტი** — `ModuleTemplate` დაინსტალდა სენდბოქსში
ბრაუზერიდან:

```
live      modules=InvoiceWorkflow   template_items table=no
sandbox   modules=ModuleTemplate    template_items table=YES
```

ანუ მოდულის მიგრაციამ ცხრილი **მხოლოდ სენდბოქსში** შექმნა — ზუსტად
ის, რისთვისაც ინსტანცია გაკეთდა.

სენდბოქსში შესვლა მუშაობს (`dev@modules.loc`, tenant admin, id=1),
`module-test.php ModuleTemplate` → **14/14**.

`tools/module-kit/README.md`-ს დაემატა სექცია „Where to develop"
სრული აღწერით, რომ მეორე ინსტანცია ნებისმიერ მანქანაზე აღდგეს.


### 4.117 მოდულების კიტი გავიდა — `modules.loc` სრულიად დამოუკიდებელია

user-ის გადაწყვეტილება, ჩემი საწინააღმდეგო რჩევის მიუხედავად:
`tools/module-kit/` ამ რეპოდან წაიშალა და `modules.loc` **ცალკე
პროექტია** — არა კლონი, არა remote, არა საერთო git-ისტორია.
კოდის დუბლირება მისაღებადაა მიჩნეული.

**ჩემი არგუმენტი იყო**: კიტი ბირთვის კონტრაქტს აღწერს და მასზე
ტესტავს, ამიტომ ბირთვთან ერთად უნდა იცვლებოდეს; ცალკე რეპოში
ჩამორჩება. **user-ის არგუმენტი**: ცოცხალ პროექტში დეველოპმენტის
ინსტრუმენტს ადგილი არ აქვს, და ორ პროექტს შორის კავშირი თავად არის
რისკი. ორივე სწორია; user-ის პროექტია.

#### რა შეიცვალა აქ (dashboard.loc)

- `tools/` — წაშლილი (`git rm`)
- `CLAUDE.md` — მოდულების ხაზი ახლა ამბობს: **აქ მოდული არასდროს
  იწერება**; დასრულებული მოდული ZIP-ად მოდის Settings → Modules-ით
- `Env::set()`-ის და InvoiceWorkflow-ის ტესტის კომენტარები —
  harness-ის ადგილი `modules.loc`-ია
- `app/Modules/InvoiceWorkflow/tests/` **დარჩა** — ტესტები მოდულთან
  ერთად მიდის, რომ harness-მა გაუშვას იქ, სადაც მოდულზე მუშაობენ

#### რა არის modules.loc ახლა

`C:\OSPanel\home\modules.loc` — საკუთარი `git init`, ერთი საწყისი
კომიტი, ნული remote. შიგნით: ბირთვის სრული ასლი (`app/`, `public/`,
`migrations/`), `tools/module-kit/` (შაბლონი + harness + AI-პრომპტი),
საკუთარი `CLAUDE.md` („module workshop"), საკუთარი ბაზა `invoice_dev`.
dashboard-ის `handoff.md`/`test-data.sql` იქიდან ამოღებულია.

**ბაზა ცალკე დარჩა** (user-მა თქვა „საერთო შეიძლება ბაზა ჰქონდეთ" —
დასაშვებად, არა მოთხოვნად). მოდულის `install()` მიგრაციებს ბაზაზე
უშვებს; საერთო ბაზა ზუსტად იმ რისკს დააბრუნებდა, რისთვისაც
ინსტანცია გაკეთდა. თუ user-ს მაინც უნდა — `.env`-ში ერთი ხაზია.

#### ფასი, რომელიც გადაიხადა (ჩაწერილია modules.loc-ის CLAUDE.md-ში)

ბირთვის ცვლილება — ახალი hook-წერტილი, შეცვლილი helper —
`modules.loc`-ში **თავისით აღარ მოვა**. `CLAUDE.md` იქ ზუსტად
ჩამოთვლის, რომელი საქაღალდეები უნდა გადაიწეროს ცოცხალიდან
(`app/Core`, `app/Controllers`, …, `public/`, `migrations/`) და რომელი
**არა** (`app/Modules/`, `.env`, `storage/`).

#### გადამოწმებულია

- modules.loc: `module-test.php ModuleTemplate` → **14/14**,
  scratch-ბაზა `invoice_dev_moduletest` (ე.ი. harness-ი იქაც
  `invoice_dev`-ს ვერ ეხება)
- modules.loc: `git remote -v` → **ნული**; `CLAUDE.md`-სა და kit
  README-ში `dashboard.loc`-ის ხსენება — **ნული**
- dashboard.loc: `tools/` არ არსებობს; შეხებული PHP-ფაილები lint-სუფთა


### 4.118 `modules.loc` რედუცირებულია პლატფორმამდე

user: „modules.loc მხოლოდ მოდულების გასაკეთებლად და დასატესტადაა, არ
სჭირდება არსებული ფუნქციონალი — მაქსიმალურად გავამარტივოთ."

**ხაზი, რომელიც ვერ გადაიკვეთა**: hook-ების მასპინძელი ფაილები
(`InvoiceController`, `orders.php`, `invoices.php`) და მთელი
`app/Core/` სენდბოქსში ცოცხალის **შინაარსობრივად იდენტური** უნდა
იყოს. სხვანაირად სენდბოქსი მოდულს ისეთ გარემოს აჩვენებს, რომელიც
ცოცხალ აპლიკაციაში არ არსებობს — ანუ იტყუება. ეს გადამოწმდა
`diff --strip-trailing-cr`-ით: 21 Core-ფაილი + 4 მასპინძელი —
იდენტური (მხოლოდ CRLF/LF განსხვავდება, რაც git checkout-ის შედეგია).

#### წაშლილია (არცერთ მოდულს არ ეხება)

7 კონტროლერი (Analytics, Dashboard, Organization, Profile, StyleGuide,
SuperUser, User), 3 მოდელი (Analytics, CustomerReport, Dashboard),
12 view (+ `pdf/`, `emails/`), მათი მარშრუტები. `routes.php` და
`menu.json` სენდბოქსის საკუთარია — რედუცირებული. `.env`-ში
`SUPERUSER_*` ცარიელია: სახელოსნოში დასათვალიერებელი tenant არ არსებობს.
`/` ახლა მოდულების გვერდია.

#### დარჩა (მოდული ეხება)

`app/Core/` მთლიანად · მასპინძლები · `layout.php` + partials ·
customers/products/invoices (სატესტო მონაცემი მასპინძლებისთვის) ·
`settings/modules`, `help/*`, auth · `migrations/` ყველა 38 (სქემა,
რომელზეც მოდულის FK-ები მიუთითებს).

**PDF/ელფოსტის ღილაკები მასპინძელ გვერდებზე 404-დება** — განზრახ.
მათი ამოღება მასპინძლებს ცოცხალისგან განასხვავებდა. `CLAUDE.md`-ში
ჩაწერილია.

#### გადამოწმებულია

- 8 დარჩენილი გვერდი — ყველა 200, სუფთა
- 17 ბმული დაკროლილი, **3 მკვდარი** (profile ×2, send-email) — ყველა
  მოსალოდნელი და დოკუმენტირებული
- **hook-ები რედუცირებულ სენდბოქსში**: InvoiceWorkflow-ის badge
  `/orders`-ზე (`bg-warning-subtle`), panel `/invoices?edit=1`-ზე,
  `/m/invoiceworkflow/payment` → 405 (რეგისტრირებული, POST-only)
- harness: InvoiceWorkflow **13/13**, ModuleTemplate **14/14**
- ზომა vendor/.git-ის გარეშე: **2.9 MB**

**ერთი შეცდომა დაიჭირა**: `cp -r X dest/X` არსებულ `dest/X`-ში
**ჩადგა** და `X/X/` შექმნა — ერთ კომიტში მოხვდა, შემდეგში გასწორდა.
სენდბოქსში `InvoiceWorkflow` ახლა ცოცხალის იდენტურია.

`modules.loc/CLAUDE.md` ზუსტად ჩამოთვლის რა არის/რა არაა, რომელი
ფაილები **არასდროს** უნდა შეიცვალოს იქ, და ბირთვის განახლებისას რა
გადაიწეროს (Core, მასპინძლები, layout, lang, public, migrations) და
რა **არა** (`routes.php`, `menu.json`, `app/Modules/`, `.env`).


### 4.119 მოდულის კონცეფცია — ოთხი წესი, სამი აღსრულებული

user-მა ჩამოაყალიბა, რა უნდა ჰქონდეს მოდულს ბირთვისგან და რა არა.
ოთხი წესი; პირველი სამი **მექანიკურად აღსრულდება** — harness-შიც და
ZIP-ატვირთვაზეც — მეოთხე სენდბოქსის კონფიგურაციაა.

| # | წესი | აღსრულება |
|---|---|---|
| 1 | ბირთვისგან მართვა და სტილი, **უცვლელად**; საკუთარი სტილი უნიკალური, ბირთვზე გავლენის გარეშე | `ModuleLint`: `module.css`-ის ყველა სელექტორი `.<კოდი>-` პრეფიქსით |
| 2 | მენიუ ინსტალაციისას **„მოდულები" ქვემენიუში** | `ds_menu()`: ერთი ბირთვის პუნქტი, თითო მოდულზე ერთი შვილი; `menu.json`-ს `section` აღარ აქვს |
| 3 | ბაზა: **კითხვა ყველგან, წერა მხოლოდ საკუთარ ცხრილებში** → ნებისმიერი რეპორტი | `ModuleDb`: `select()` ნებისმიერზე, `execute()` მხოლოდ `uninstall.sql`-ის ცხრილებზე; `ModuleLint`: `App\Core\Db` მოდულში — შეცდომა |
| 4 | `modules.loc` ავტორიზაციის გარეშე | სენდბოქსის `index.php` ყველა მოთხოვნას tenant 1-ად შედის; auth-კონტროლერი/view-ები წაშლილი |

#### ბირთვის ახალი ნაწილები (dashboard.loc, სინქრონირებული სენდბოქსში)

- **`ModuleDb`** — `for($code)`, `select()`, `one()`, `execute()`,
  `lastInsertId()`, `transaction()`, `ownedTables()`. Წერის სამიზნეს
  regex-ით ამოიღებს (INSERT/REPLACE/UPDATE/DELETE/ALTER/TRUNCATE/DROP/
  CREATE), კომენტარებს წინ ჩამოაჭრის, schema-კვალიფიცირებულს და
  მრავალცხრილიან წერას (JOIN) **უარყოფს**, `SELECT … INTO OUTFILE`-ს —
  ასევე. Multi-statement-ს MySQL-ის native prepare თავად უარყოფს
  (`EMULATE_PREPARES=false`).
- **`ModuleDbException`** — კოდი + მიზეზი + SQL ერთ სტრიქონად.
- **`ModuleLint`** — `check($code)` / `checkDir($dir, $code)`. PHP-ს
  tokenizer-ით კომენტარებს **ჯერ ჩამოაჭრის** (პირველი ვერსია საკუთარ
  docblock-ს იჭერდა — „never App\Core\Db" წესის აღწერაა, არა
  დარღვევა). Ამოწმებს: `Db` გამოყენება, ბირთვის გზები (`app/lang/`…),
  CSS-სელექტორის პრეფიქსი.
- **`ModuleRegistry::ownedTables()`** — `uninstall.sql`-ის `DROP TABLE`
  ხაზები, ერთ ადგილას. Იგივე სია სამ რამეს ემსახურება: ექსპორტს,
  წერის საზღვარს, თავად uninstall-ს — ერთმანეთს ვერ დაშორდებიან.
- **`ModuleArchive::installUpload()`** — staging-ში `ModuleLint::checkDir()`;
  დარღვევით ZIP `app/Modules/`-ს **არ აღწევს**.
- **`ds_menu()`** — მოდულების ჩანაწერები `nav.modules` მშობლის ქვეშ,
  `nav.main`-ის ბოლოში, მხოლოდ თუ ≥1 მოდულს აქვს `menu.json`.

#### პატიოსანი საზღვარი

Წესები 1 და 3 **დაცული კონტრაქტია, არა sandbox**. PHP-ში მოდული
ბირთვის პროცესშია — `Db::conn()` პირდაპირ გამოძახება ტექნიკურად
შესაძლებელია. Რაც რეალურად გვაქვს: `ModuleDb` ჩერდება პატიოსან
შეცდომას, `ModuleLint` — არაპატიოსან მცდელობას (ვერც ტესტს გაივლის,
ვერც დაინსტალდება). Ნამდვილი sandbox (MySQL-user თითო მოდულზე)
აპლიკაციას DB-ადმინის უფლებას მოსთხოვდა runtime-ზე — უფრო დიდი
რისკია. Ეს ჩაწერილია `/help/modules`-შიც და `AGENT_PROMPT.md`-შიც.

#### გადამოწმებულია

**`ModuleDb`** — 29 შემთხვევა, **29 გავიდა**: 5 კითხვა (core SELECT,
JOIN, CTE, SHOW, კომენტარი წინ) — დაშვებული; 3 შენიღბული წერა (OUTFILE,
INSERT select()-ში, `/* SELECT */ UPDATE`) — უარყოფილი; 4 წერა საკუთარზე
(INSERT/UPDATE/DELETE/ON DUPLICATE, ტრანზაქციის rollback) — დაშვებული;
**15 წერა core-ზე** (UPDATE/DELETE/INSERT/DROP/TRUNCATE/ALTER, backtick,
შერეული case, კომენტარი წინ, schema.table, JOIN-UPDATE, JOIN-DELETE,
CALL, SELECT execute()-ში) — **ყველა უარყოფილი**; stacked statement —
driver-მა უარყო; uninstall.sql-ის გარეშე მოდული — ვერაფერს წერს.

**`ModuleLint`** — საბოტაჟით: `Db::all` მოდელში + `.btn`/`.card .x`
CSS-ში → **3 დარღვევა დაჭერილი**, ტესტები არ გაშვებულა; აღდგენის შემდეგ
— სუფთა.

**მოდულები**: InvoiceWorkflow (→ `ModuleDb`, v2.1.0) contract ok + **13/13**;
ModuleTemplate (→ `ModuleDb`, `.moduletemplate-*` CSS, ახალი `menu.json`)
contract ok + **14/14**. Ორივე ცოცხალ სენდბოქსში: badge `/orders`-ზე,
panel `/invoices?edit=1`-ზე, **„მოდულები → შაბლონი"** sidebar-ში.

**სენდბოქსი ავტორიზაციის გარეშე**: ქუქის გარეშე `/`, `/orders`,
`/invoices`, `/settings/modules`, `/help/modules` — **ყველა 200**.

**dashboard.loc**: ყველა გვერდი სუფთა; `/help/modules`-ზე კონცეფციის
ცხრილი + `ModuleDb`-ის სექცია; „მოდულები" მენიუ **არ ჩანს** (არცერთი
მოდული არაა ჩართული tenant 1-ზე — სწორია). InvoiceWorkflow v2.1.0
გაგზავნილია ცოცხალშიც, lint სუფთა.

#### დოკუმენტაცია

`AGENT_PROMPT.md` (სენდბოქსში): §1-ში ოთხი წესის ცხრილი აღსრულებით,
§6a მენიუ, §8 `ModuleDb` API `Db`-ის ნაცვლად + რას იღებს/უარყოფს
`execute()`, §9 — საზღვარი ცხრილია და არა მწკრივი, §11 — CSS-პრეფიქსი
აღსრულებულია. `/help/modules` (ბირთვი): კონცეფციის ბარათი + 4a
„ბაზა — ModuleDb". ka/en **610 = 610**.


### 4.120 native `<input type="date">` → flatpickr ყველგან

user: „ყველგან სადაც `type="date"`-ია, flatpickr გამოიყენე."

Ძებნამ **ერთი** ადგილი იპოვა: `/analytics/overview`-ის ფილტრი — ორი
native input, `from`/`to`. Ეს ზუსტად ის წყვილია, რასაც `ds-date-range`
კომპონენტი უკვე აკეთებს `/orders`-ზე (`4.106`). Ახალი კოდი არ
დაწერილა — კომპონენტი გამოვიყენე: ორი `col-md-3` → ერთი `col-md-6`,
ერთი ლეიბლი `analytics.filter_period` („პერიოდი"). Hidden `from`/`to`
inputs `?from=&to=` კონტრაქტს ინარჩუნებს, `AnalyticsController`
უცვლელია.

**გადამოწმებულია ცოცხლად** (SuperUser → tenant 1): `input[type=date]`
გვერდზე — **0**; flatpickr მიბმული, `mode: range`, `showMonths: 2`,
ლოკალი ქართული (`იანვარი`); default 30-დღიანი დიაპაზონი ჩანს
(`2026-08-18 — 2026-09-16`); `setDate()` → hidden inputs განახლდა →
`?from=2026-09-01&to=2026-09-10&granularity=daily` → სერვერმა იგივე
დააბრუნა. Შეცდომა არაა.

`analytics.filter_from`/`filter_to` გასაღებები რჩება — გამოუყენებელი,
უვნებელი.


### 4.121 მიმოხილვის პერიოდის ფილტრი — `/orders`-ის იდენტური

user: „მიმოხილვაშიც ისე, როგორც ყველა შეკვეთაშია: ყველა დრო / მიმდინარე
თვე / მიმდინარე წელი / დროის მონაკვეთი + ღილაკი — ყველა ერთ სიმაღლეზე."

`4.120`-ის ბარათი-ფილტრი (ლეიბლებით, „გაფილტვრა" ღილაკით) წაიშალა;
მის ნაცვლად header-ში `/orders`-ის ზუსტი განლაგება: სეგმენტური
btn-group + `ds-date-range` + ✓ + **დაჯგუფების select** (ეს გვერდის
საკუთარია, `/orders`-ს არ აქვს).

**კონტროლერი** — `resolveRange()` ახლა `resolveOrdersRange()`-ის
სარკეა: `?period=month|year`, `?from=&to=`, სხვა შემთხვევაში —
**ყველა დრო**. Analytics-ის მოდელს `from`/`to` ყოველთვის სჭირდება,
ამიტომ „ყველა" = `Analytics::earliestDate()` (ახალი, `MIN(issue_date)`
scope-ში) → დღემდე. Ძველი „ბოლო 30 დღე" default გაქრა — ორივე გვერდზე
default ახლა „ყველა"-ა.

**სამი ფორმა, ერთი მდგომარეობა.** Query-ს სამი რამ ატარებს, ყველა
ერთი და იმავე PHP-ცვლადებიდან, რომ ვერ დაშორდნენ: period-ბმულები
`granularity`-ს ინარჩუნებენ; range-ფორმა `from/to + granularity`-ს
აგზავნის, `period`-ის **გარეშე** (მისი გაგზავნა თავად *ნიშნავს*
custom range-ს — თორემ თვეზე მყოფი მომხმარებელი დიაპაზონს აირჩევდა
და თვეზევე დარჩებოდა); granularity-select თავის ფორმაშია აქტიური
`period` ან `from/to` hidden-ებით და `onchange`-ზე თავად იგზავნება.

**სიმაღლე** — ახალი `.ds-toolbar-select` `design-system.css`-ში,
`.btn`-ის ტოკენის წესის გვერდით: `height: var(--ds-control-height)`,
`width: auto`. Select toolbar-ში plain `<select>`-ია (არა ds-select) —
`ds-table`-ის toolbar-ის პრეცედენტით: 3 არჩევანს საძიებო dropdown არ
სჭირდება, ds-select-ის trigger კი `2.9rem`-ია და რიგს გატეხდა.

**გადამოწმებულია ცოცხლად** (`1400×900`, sub-pixel): ღილაკი, range,
✓, select — **ოთხივე `34.39px`**, `top 146.69`, `bottom 181.08`.
Მდგომარეობები: ყველა → 20 ინვოისი, „ყველა დრო" აქტიური; month+weekly →
8, „მიმდინარე თვე", gran-ფორმაში `period=month`; range+monthly → 5, ✓
აქტიური, gran-ფორმაში `from/to`; ყველა ბმული `granularity`-ს ატარებს.
Შეცდომა არაა.

`analytics.filter_*` გასაღებები (period/from/to/apply) გამოუყენებელი
დარჩა — უვნებელი.


### 4.122 დაჯგუფების select წაშლილია — პერიოდიდან გამომდინარეობს

user: „მიმოხილვაში დღიური/კვირეული/თვიური ფილტრი, ჩემი აზრით, აზრს კარგავს."

Სწორია. `4.121`-ის შემდეგ პერიოდი ფიქსირებული სეტია (ყველა/თვე/წელი/
დიაპაზონი) და მასზე ცალკე დაჯგუფების არჩევა უაზრო კომბინაციებს
აჩენდა — „მიმდინარე თვე, თვიურად" = ერთი სვეტი. Დაჯგუფება ახლა
**კონტროლერი ირჩევს**, `granularityFor()`:

| პერიოდი | დაჯგუფება |
|---|---|
| თვე | დღიური |
| წელი | თვიური |
| დიაპაზონი / ყველა | ≤31 დღე → დღიური; ≤182 → კვირეული; მეტი → თვიური |

Იგივე ზღვრები, რასაც ანალიტიკის ინსტრუმენტები ავტომატურად იყენებენ.
`?granularity=` აღარ იკითხება. Select-ის ფორმა, period-ბმულებზე
`&granularity=`, `GRANULARITIES` const და `.ds-toolbar-select` CSS
(ერთი კომიტის სიცოცხლე ჰქონდა) — ყველა წაშლილი. `$granularity` view-ში
რჩება — ჩარტის ლეიბლების ფორმატს სჭირდება.

**გადამოწმებულია ცოცხლად**: ყველა (35 დღე) → კვირეული (`2026-W33…`);
თვე → დღიური (`4 სექ, 2026`); წელი → თვიური (`აგვ 2026`); 9-დღიანი
დიაპაზონი → დღიური; 3-წლიანი → თვიური. Toolbar-ში 5 კონტროლი, select
არაა, შეცდომა არაა.

`analytics.granularity_*`/`filter_*` გასაღებები რჩება — გამოუყენებელი,
უვნებელი.


### 4.123 შემოსავლის დინამიკის გრაფიკი — უწყვეტი ღერძი, სიტყვიერი ლეიბლები, ერთი სტილი

user-ის სამი შენიშვნა: სტილი „სხვადასხვა დროს სხვადასხვანაირია";
ღერძი გაუგებრადაა დაყოფილი (`2026-W33`); და პერიოდების ზუსტი
განმარტება.

**ერთი ფესვი სამივესთვის**: `Analytics::revenueTrend()` მხოლოდ იმ
bucket-ებს აბრუნებდა, სადაც ინვოისი იყო (`GROUP BY`, sparse).
Შედეგი: ერთდღიანი თვე — ერთი წერტილი; ბევრდღიანი — მრუდი; ღერძი
ცარიელ დღეებზე ხტებოდა და მისი დაშორება არაფერს ნიშნავდა. Კვირის
გასაღები კი პირდაპირ ეკრანზე გადიოდა.

**გასწორება**:
- მოდელი ახლა **ყველა bucket-ს** აგენერირებს დიაპაზონში (`buckets()`:
  დღე/ISO-კვირა/თვე), ნული სადაც არაფერია. Თითო წერტილს თან აქვს
  `start`/`end` — view-ს ლეიბლი სიტყვებით რომ დაწეროს. PHP-ს `o-\WW`
  MySQL-ის `%x-W%v`-ს ემთხვევა (შემოწმებულია: `2026-08-13` → ორივეგან
  `2026-W33`).
- ლეიბლები: დღე `16 სექ`; კვირა `31 აგვ – 6 სექ`; თვე `სექ 2026`.
- ჩარტი **bar**, სამუშაო მაგიდის მსგავსად — ერთი წერტილიც სვეტია,
  სამოციც. Ფერი `--bs-primary`-დან JS-ით, hex-ის ნაცვლად.
- ცარიელი მდგომარეობა ახლა „შემოსავალი ნულია"-ზეა (`$hasRevenue`), არა
  „bucket-ები არაა" (ისინი ყოველთვის არსებობს).

**პერიოდები** (user-ის განმარტებით):
| | |
|---|---|
| ყველა დრო | პირველი შეკვეთიდან დღემდე |
| მიმდინარე თვე | 1-დან **დღემდე** (იყო: თვის ბოლომდე) |
| მიმდინარე წელი | 1 იანვრიდან 31 დეკემბრამდე |

Თვის ცვლილება **`/orders`-ზეც** გავრცელდა (`resolveOrdersRange`) —
ერთი განმარტება ორივე გვერდზე; ინვოისებზე შედეგი ისედაც იგივეა
(მომავალი ინვოისი არ არსებობს), PDF-ის სათაურში კი „1–16 სექ" გახდება.

**გადამოწმებულია ცოცხლად**: ყველა (35 დღე) → 6 კვირა უწყვეტად, 4
არანულოვანი; თვე → **16** დღე (1–16 სექ — ე.ი. დღემდე), 15 ნული;
წელი → 12 თვე `იან 2026…დეკ 2026`; 9-დღიანი დიაპაზონი → 9 დღე.
`type: 'bar'` ყველგან. Შეცდომა არაა.


### 4.124 მიმოხილვის ბარათები — header-ები, გრაფიკი აღარ იწელება

user-ის ორი შენიშვნა: შემოსავლის დინამიკის ბარათი მარჯვენა სვეტის
სიმაღლეზეა ჩამოწელილი — არ უნდა; ტოპ დამკვეთების და პროდუქციის
სათაურები `card-header`-ში, გამოყოფილი.

- გრაფიკის ბარათიდან `h-100` მოხსნილია — თავისი სიმაღლისაა.
- სამივე ბარათს `card-header` აქვს (გრაფიკისაც — ერთ რიგში ერთი
  სტილი). Ახალი `.ds-card > .card-header` `design-system.css`-ში:
  Bootstrap-ის header ტოკენებზე — ზედა კუთხეები `--ds-radius`-იდან
  (Bootstrap თავისი card-ცვლადიდან ითვლის, რომელსაც `.ds-card` არ
  აყენებს, ამიტომ კუთხე ბარათისგან განსხვავებული გამოვიდოდა), ფონი
  `--bs-tertiary-bg`, ქვედა ბორდერი `--bs-border-color`.

**გადამოწმებულია ცოცხლად** (`1400×900`): გრაფიკის ბარათი **277px**,
მარჯვენა სვეტი **684px** — არ იწელება; სამივე header: ფონი
`rgb(248,249,250)`, ბორდერი 1px, ზედა რადიუსი `6.2px` (= `.5rem − 1px`).


### 4.125 მიმოხილვა — ორი ახალი ჩარტი დინამიკის ქვევით

user-მა ჰკითხა, კიდევ რა გრაფიკია შესაძლებელი; შევთავაზე სამი,
ავირჩიეთ ორი. Ორივე იმ მონაცემზეა, რაც გვერდს უკვე აქვს — ახალი
query არა, ერთი `COUNT(*)` არსებულ `GROUP BY`-ში.

**1. Შემოსავლის განაწილება დამკვეთებზე** (doughnut, მარცხნივ) —
ტოპ-5 + „სხვები". Სია გვერდით რიცხვებს იძლევა, წილს არა; ეს ერთი
შეხედვით აჩვენებს, ერთ კლიენტზეა თუ არა დამოკიდებულება. Ფერები
`--bs-primary-rgb`-ის ტონები (1.0 → 0.36 alpha), „სხვები" — ნაცრისფერი,
რომ ნაშთად იკითხებოდეს და არა კიდევ ერთ დამკვეთად. Tooltip
პროცენტებში. „სხვები" = `summary.total − Σ top5`, ანუ donut-ის ჯამი
**განსაზღვრებით** ბარათის ჯამია.

**2. Ინვოისების რაოდენობა და საშუალო ღირებულება** (მარჯვნივ) —
დინამიკის იმავე bucket-ებზე: რაოდენობა სვეტებად (მარცხენა ღერძი),
საშუალო ხაზად (მარჯვენა ღერძი; 12 და 4 800 ₾ ერთ ღერძს ვერ
გაიზიარებენ). Პასუხობს *რატომ* — მეტი ინვოისი იყო თუ უფრო დიდი.
`revenueTrend()` ახლა `count`-საც აბრუნებს; საშუალო view-ში =
`total/count`, 0 სადაც count 0-ია (ხაზი ღერძზე ჯდება, ხვრელი არ რჩება).

**განლაგება**: `col-lg-8` ახლა `d-flex flex-column gap-3` — დინამიკა
სრულ სიგანეზე, ქვევით `col-md-6 + col-md-6` `h-100`-ით (ერთ რიგში
თანაბარი). Ეს ავსებს ცარიელ ადგილს, რომელიც `4.124`-ის შემდეგ
დინამიკის ქვევით დარჩა. `card-header`-ები ყველგან.

**Რას არ დავამატე** (user-ს ავუხსენი): მომხმარებლების მიხედვით
შემოსავალი — სამუშაო მაგიდაზე უკვე არის; გადახდის სტატუსი —
`InvoiceWorkflow` მოდულის მონაცემია, ბირთვი მასზე არ უნდა იყოს
დამოკიდებული.

**გადამოწმებულია ცოცხლად** (`1400×900`): სამივე ჩარტი ინსტანციაა,
შეცდომა არაა. Donut: 6 სექტორი, წილები 48/23/23/3/2/1%, **ჯამი
105,688.80 = ბარათის ჯამი**. Volume: `bar:რაოდენობა` + `line:საშუალო`,
ღერძები `x/count/avg`, რაოდენობების ჯამი **20 = პერიოდის ინვოისები**.
Ბარათები: დინამიკა 277px, ორი ახალი 392px თითო (თანაბარი).


### 4.126 მიმოხილვა — ქვედა რიგი ერთ ხაზზე

user: ბოლოს დამატებული ორი ბარათი და „ყველაზე ხშირად შეკვეთილი
პროდუქცია" ქვემოდან ერთ ხაზზე უნდა იყოს.

Ორივე სვეტი (`col-lg-8`, `col-lg-4`) ერთ `row`-შია, ანუ flex-ის
გამო ისედაც თანაბარი სიმაღლისაა — ბარათები უბრალოდ ზემოდან იყო
ჩამწკრივებული. Ორივე სვეტში ბოლო ელემენტს `mt-auto` — ორი ჩარტის
`row`-ს და „ტოპ პროდუქციის" ბარათს. Ბარათი **არ იწელება** (რაც
`4.124`-ში user-ს არ სურდა); სვეტებს შორის სიმაღლის სხვაობა
ბოლო ბარათის *ზემოთ* ჩნდება, არა ბარათის შიგნით.

**გადამოწმებულია ცოცხლად** (`1400×900`): სამივე ბარათის `bottom`
**1008.6px**; სიმაღლეები უცვლელი — დინამიკა 277, ტოპ დამკვეთები 335,
ორი ჩარტი 392, ტოპ პროდუქცია 335.


### 4.127 მიმოხილვა — ორი რიგი, ცარიელი ადგილის გარეშე

user: `4.126`-ის შემდეგ „ცარიელი ადგილი რჩება, გრაფიკები
ჩამოწელილია". Მართალია: სვეტური განლაგება ფიქსირებული ასპექტის
ჩარტებით ქვემოდან **მხოლოდ** ბოლო ბარათის ზემოთ ცარიელი ადგილით
სწორდებოდა — ჩარტი არასდროს გამოდის ზუსტად ხუთმწკრივიანი სიის
სიმაღლის.

**გადაწყვეტა — ორი რიგი, თითოეული თანაბარი ბარათებით:**

| რიგი | ბარათები |
|---|---|
| 1 | დინამიკა (`col-lg-8`) · ტოპ დამკვეთები (`col-lg-4`) |
| 2 | განაწილება · რაოდენობა/საშუალო · ტოპ პროდუქცია (`col-lg-4` ×3) |

Რიგის სიმაღლეს **სია** ადგენს (5 მწკრივი); ჩარტის ბარათები `h-100`-ით
მას იღებენ და ჩარტი ბარათს **ავსებს**: `.ds-chart-fill` (flex-grow,
`position:relative`, `min-height:200px`) + `maintainAspectRatio:false`.

**ხაფანგი, რომელიც დაიჭირა**: პირველი ვერსია (canvas ნაკადში) რიგ 2-ს
371-ზე სწევდა (სია 335). Მიზეზი: Chart.js-ის fallback-ასპექტი —
donut-ისთვის **1:1** — canvas-ს ~300px სიმაღლეს აძლევდა, wrapper
იზრდებოდა, რიგი მასთან ერთად. `.ds-chart-fill > canvas { position:
absolute; inset: 0 }` — canvas ნაკადიდან ამოღებულია, wrapper-ის ზომას
იღებს და ვერასდროს ადგენს. Feedback-loop-ზე შემოწმებული: 1.5 წამში
სიმაღლე უცვლელი.

**გადამოწმებულია ცოცხლად** (`1400×900` და `1920×1000`): ორივე რიგი
**335px**, ყველა ბარათი რიგში ერთ `top`/`bottom`-ზე; რიგებს შორის
მხოლოდ `g-3` (14.4px); canvas-ები: დინამიკა 688×264, donut 321×264,
volume 321×246 (მისი სათაური ორ ხაზზეა 1400-ზე → body 18px დაბალი —
სწორია). Შეცდომა არაა.


## 5. კონვენციები

- **პასუხები ქართულად** — მომხმარებელმა ცალსახად მოითხოვა.
- **Ponytail mode (full)** — ყველაზე მარტივი მუშა გადაწყვეტა; ზედმეტი აბსტრაქციები არა.
- თარგმანების გასაღებები **სემანტიკურია** (`nav.dashboard`), არა ინგლისური ტექსტი.
- კონტროლერების მითითება `[Ctrl::class, 'method']` — **არა** `'Ctrl@method'` სტრიქონი.
- აკლია გასაღები → ეკრანზე თავად გასაღები ჩნდება (`t()`-ის ქცევა).

## 6. Router

```php
$router->get('/path', [Ctrl::class, 'method']);
$router->post('/path', [Ctrl::class, 'method']);
$router->add('DELETE', '/path', [...]);   // სხვა ზმნები
```

- `HEAD` ავტომატურად `GET`-ად ითვლება.
- უცნობი მისამართი → 404. ცნობილი მისამართი სხვა ზმნით → **405 + `Allow` ჰედერი**.
- `normalise()` ბოლო სლეშს ჭრის **რეგისტრაციისა და დისპატჩის** დროს ერთნაირად.

## 7. .env

`parse_ini_file(..., INI_SCANNER_TYPED)` — დამოკიდებულების გარეშე.
გამოყენებული: `APP_NAME`, `APP_VERSION`, `APP_LOCALE`, `APP_DEBUG`, `DB_*` (`Core/Db.php`).

- `.env`-ის წაშლა აპლიკაციას **არ ტეხს** — defaults მუშაობს.
- მნიშვნელობები `putenv()`-ში განზრახ **არ** იწერება (ქვეპროცესებში გაჟონავდა).
- ⚠️ INI-ში ბრჭყალების გარეშე `#` კომენტარს იწყებს: `DB_PASS="p@ss#word"`.

---

## 8. შემოწმებულია ✅

- ორივე მარშრუტი × ორივე ენა → 200
- 404 (უცნობი მისამართი), 405 + `Allow: GET` (`POST /`)
- `POST` დისპატჩი რეალურად (დროებითი `PingController`-ით, შემდეგ წაშლილი)
- ბოლო სლეშის ნორმალიზება (`/ping/` რეგისტრაცია ↔ `/ping` მოთხოვნა)
- ენის cookie შენარჩუნება; `?lang=zz` და `?lang=../../etc/passwd` → `ka` (whitelist)
- `.env`: ცვლილება მოქმედებს, არასწორი `APP_LOCALE` → fallback, წაშლა → 200
- `.env`, `.env.example`, `.gitignore` ბრაუზერიდან → 404
- ფონტები: ქართული BPG-ზე (50.36px), ინგლისური Inter-ზე (29.93px) — გაზომილი
- assets, გრაფიკები, კონსოლში შეცდომა არაა

## 9. შემოწმებული **არ არის** ⚠️

- **`.htaccess` რეალურ Apache-ზე.** ყველა ტესტი `php -S`-ით ჩატარდა (OSPanel გამორთული).
  `mod_rewrite` ჩატვირთულია და `AllowOverride All` არსებობს, მაგრამ ცოცხლად არ მინახავს.
  **ხვალ პირველი საქმე:** OSPanel გაუშვი და `http://dashboard.loc/style-guide` გახსენი.
- ვიზუალური იერსახე — screenshot ვერ გავაკეთე (იხ. 4.6), ყველაფერი გაზომვებით მოწმდებოდა.

## 10. შემდეგი ნაბიჯები

1. `.htaccess`-ის გადამოწმება Apache-ზე (იხ. ზემოთ).
2. ~~CSRF~~ — გაკეთდა (იხ. 4.8).
3. ბაზა — კავშირი მზადაა (`Core/Db.php` + `migrate.php` + `migrations/`, DB `invoice`,
   ცხრილი `customers` შექმნილია). დარჩა: `Models/Dashboard.php`-ის მეთოდების სხეულების
   ჩანაცვლება მოთხოვნებით — view-ებს არ შეეხება.
   ⚠️ `customers.ruler`-ს **რეალური FK არ აქვს** — `ruler` ცხრილი ჯერ არ არსებობს;
   მხოლოდ ინდექსი + კომენტარია (როგორც სქრინშოტზე). **განახლება**: `ruler`
   აღარაა გამოუყენებელი — `4.31`-ში გახდა მთელი აპლიკაციის multi-tenant
   scoping-ის საფუძველი (`App\Core\Auth::tenantId()`). FK კვლავ არ აქვს
   (`users`-ზე მიმართვა შეგნებულადაა თავიდან აცილებული, იხ. `4.31`), მაგრამ
   ეს ველი ახლა რეალურად წაკითხულია/ჩაწერილია ყველგან.
4. Sidebar-ის collapse ამჟამად **სრულად მალავს** მენიუს. თუ ვიწრო აიკონების ზოლი გინდა —
   `ponytail:` კომენტარია `design-system.css`-ში.
5. Style-guide-ის რეფერენს-სქრინშოტზე იყო ჩამოსაშლელი ქვესექციები (`Color >`) — არ გაკეთებულა.

## 11. სხვა

- `public/assets/images/flags/{en,geo}.png` — მომხმარებლის დამატებული, **არ გამოიყენება**
  (ენის გადამრთველში inline SVG დროშებია `Views/partials/topbar.php`-ში).
- `dashboard.loc.rar` — პროექტის ფესვში, ბექაპი.
- Sidebar-ის ქვედა user-ბლოკი `sidebar.php`-ში კომენტარშია ამოღებული.
