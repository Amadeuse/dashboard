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

მომხმარებელმა `app/Views/auth/*.html` ჩააგდო (სხვა დიზაინ-სისტემის mockup-ები,
საკუთარი `flabel`/`i18n` vendor ბიბლიოთეკებით, რომლებიც ამ პროექტში არ
არსებობს) და სთხოვა ეს ფორმები რეალურად ემუშავათ. **მკაფიოდ დადასტურებულია
მომხმარებელთან:** (1) *არანაირი გლობალური auth-gate* — დანარჩენი აპლიკაცია
(`/`, `/customers`, `/products`, `/warehouse`, ...) კვლავ სრულად ღიაა,
login მხოლოდ სესიას ამყარებს; (2) reset-ბმული **რეალურ SMTP-ზე** უნდა
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

⚠️ `customer_taxid`-ის დუბლიკატის შემოწმება **აპლიკაციაშია, არა ბაზაში** —
სქრინშოტის `idx_customers_taxid` განზრახ არაა UNIQUE (ს/კ-ის გარეშე დამკვეთი
ნებადართულია, ხოლო MySQL-ში მრავალი NULL UNIQUE-საც გაივლიდა).

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
   მხოლოდ ინდექსი + კომენტარია (როგორც სქრინშოტზე).
4. Sidebar-ის collapse ამჟამად **სრულად მალავს** მენიუს. თუ ვიწრო აიკონების ზოლი გინდა —
   `ponytail:` კომენტარია `design-system.css`-ში.
5. Style-guide-ის რეფერენს-სქრინშოტზე იყო ჩამოსაშლელი ქვესექციები (`Color >`) — არ გაკეთებულა.

## 11. სხვა

- `public/assets/images/flags/{en,geo}.png` — მომხმარებლის დამატებული, **არ გამოიყენება**
  (ენის გადამრთველში inline SVG დროშებია `Views/partials/topbar.php`-ში).
- `dashboard.loc.rar` — პროექტის ფესვში, ბექაპი.
- Sidebar-ის ქვედა user-ბლოკი `sidebar.php`-ში კომენტარშია ამოღებული.
