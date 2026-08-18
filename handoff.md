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
