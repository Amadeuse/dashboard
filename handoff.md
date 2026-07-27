# Nova Dashboard — Handoff

მდგომარეობა: **მუშა, ტესტირებული**. ბოლო სესია: 2026-07-27.

**Git:** `git init`-ი გაკეთდა 2026-07-27. Remote: `https://github.com/Amadeuse/dashboard`
(public, `main`). `.env`, `sql/` (1538 დამკვეთის PII), `.osp/`, `dashboard.loc.rar`
**push-ში არ შესულა** — `.gitignore`-შია, დისტანციურად გადამოწმებულია (404).

---

## 1. რა არის ეს

Bootstrap 5.3-ზე აგებული dashboard-ის დიზაინის სისტემა, გადატანილი მარტივ MVC-ზე.
ორენოვანი (ქართული / ინგლისური). ბაზა **არ არსებობს** — ყველა მონაცემი `app/Models/Dashboard.php`-შია ჩაწერილი.

## 2. გაშვება

```bash
cd C:/OSPanel/home/dashboard.loc/public && C:/OSPanel/modules/PHP-8.2/php.exe -S 127.0.0.1:8090 index.php
```

`index.php` არგუმენტად აუცილებელია — router-სკრიპტად მუშაობს (`.htaccess`-ს `php -S` არ კითხულობს).
OSPanel-ით: `web_root` უკვე `public/`-ზეა მითითებული `.osp/project.ini`-ში.

მარშრუტები: `/`, `/style-guide`, `GET|POST /customers`.

## 3. სტრუქტურა

```
dashboard.loc/
├── .env / .env.example / .gitignore
├── sql/customers.sql           ← phpMyAdmin dump, 1538 დამკვეთი (რეალური PII).
│                                 **docroot-ის გარეთ და .gitignore-ში** — public/-ში
│                                 იდო და HTTP-ით ჩამოიტვირთებოდა.
├── migrations/ + migrate.php   `php migrate.php` — გაუშვებელი *.sql-ები რიგით
├── app/                        ← docroot-ის გარეთ, ბრაუზერიდან მიუწვდომელი
│   ├── bootstrap.php           autoloader → Env::load() → display_errors → Lang::boot()
│   ├── routes.php              $router->get('/', [Ctrl::class,'method'])
│   ├── Core/                   Router, Controller, Lang, Env, helpers
│   ├── Controllers/            Dashboard, StyleGuide, Error
│   ├── Models/Dashboard.php    ჩაწერილი მონაცემები (stats/orders/traffic/activity/goal)
│   ├── Views/                  layout.php + dashboard/style-guide + partials/ + errors/
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
