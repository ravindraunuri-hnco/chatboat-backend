# Backend Fixes — Changelog & Instructions

Yeh package tumhare Laravel backend (`Ai_backend`) ke saare bug-fixes aur naye
features ke saath aata hai. Neeche **har file** ka changes explain kiya gaya
hai, aur **kaise apply karna hai** (existing project mein copy + migrations).

---

## 📁 File Mapping (kahan copy karna hai)

| Is package mein                                                  | Apne project mein yahan copy karo |
|-------------------------------------------------------------------|-------------------------------------|
| `app/Models/User.php`                                              | `app/Models/User.php` (overwrite) |
| `app/Models/Product.php`                                           | `app/Models/Product.php` (overwrite) |
| `app/Models/Category.php`                                          | `app/Models/Category.php` (overwrite) |
| `app/Policies/ProductPolicy.php`                                   | `app/Policies/ProductPolicy.php` (overwrite) |
| `app/Policies/CategoryPolicy.php`                                  | `app/Policies/CategoryPolicy.php` (overwrite) |
| `app/Policies/MarginPolicy.php`                                    | `app/Policies/MarginPolicy.php` (overwrite) |
| `app/Providers/AppServiceProvider.php`                             | `app/Providers/AppServiceProvider.php` (overwrite — yeh actually AuthServiceProvider class hai, same file) |
| `app/Http/Controllers/Api/AuthController.php`                      | overwrite |
| `app/Http/Controllers/Api/CategoryController.php`                  | overwrite |
| `app/Http/Controllers/Api/ProductController.php`                   | overwrite |
| `app/Http/Controllers/Api/UserController.php`                      | overwrite |
| `app/Http/Controllers/Api/RoleController.php`                      | overwrite |
| `app/Http/Controllers/Api/PermissionController.php`                | overwrite |
| `routes/api.php`                                                   | overwrite (no real change, sirf reference) |
| `database/seeders/DatabaseSeeder.php`                              | overwrite |
| `database/migrations/2026_06_13_093411_add_role_foreign_key_to_users_table.php` | overwrite (**bug fix** — see below) |
| `database/migrations/2026_06_13_051338_create_permission_role_table.php` | overwrite (**bug fix** — down() table name) |
| `database/migrations/2026_06_15_060000_add_processing_cost_and_margin_to_products_table.php` | **naya file** — add karo |
| `database/migrations/2026_06_15_060100_add_margin_percentage_to_categories_table.php` | **naya file** — add karo |
| `database/migrations/2026_06_15_060200_add_unique_name_to_categories_table.php` | **naya file** — add karo |

**Files jo touch nahi kiye (no change needed):**
- `app/Models/Role.php`, `app/Models/Permission.php`, `app/Models/Margin.php`
- `app/Http/Controllers/Api/MarginController.php`
- Saare config files, web.php, console.php

---

## 🔧 Fix #1 — `User::hasPermission()` aur missing `role()` relation (CRITICAL)

**Problem:** `User` model mein sirf `roles()` (many-to-many, `role_user` table —
jo exist hi nahi karti) tha. Lekin `AuthController`, `ProductPolicy` etc. har
jagah `$user->role` (singular) use karte the. Iska matlab `$user->role` hamesha
`null` aata tha — **login ke baad permissions object hamesha empty hota tha,
aur admin/super-admin dono ke permission checks fail ho jaate the.**

Saath hi, `hasPermission()` method query karta tha
`->where('action', $action)` — lekin `role_permissions` table mein `action`
naam ka column hi nahi hai (uske jagah `read/write/update/delete` boolean
columns hain). Yeh query crash karti ya hamesha empty result deti.

**Fix (`app/Models/User.php`):**
- `role()` ek proper `belongsTo(Role::class)` relation ban gaya (using
  `role_id` column jo `users` table mein already hai).
- `hasPermission($permissionName, $action = 'read')` ab correct boolean
  column check karta hai: `$permission->pivot->{$action}`.
- Super admin ke liye `hasPermission()` hamesha `true` return karta hai
  (short-circuit) — koi extra row check ki zaroorat nahi.

---

## 🔧 Fix #2 — Permission naming standardized (`product`, `category`, `margin`, `user`)

**Problem:** `ProductPolicy` permission `'product'` check karta tha, lekin
`AuthController` login response mein `manage-products`, `manage-categories`
jaise naam bhejta tha. `UserController`/`RoleController`/`PermissionController`
`manage-users` use karte the. Sab jagah naam mismatch.

**Decision (tumne confirm kiya):** `ProductPolicy` jaisa naam tha (`'product'`)
— wahi single-word convention sab jagah le aaye.

**Fix:**
- `Permission` table ab 4 rows hold karega: `product`, `category`, `margin`,
  `user` (seeder se aayenge).
- `ProductPolicy`, `CategoryPolicy`, `MarginPolicy` — sab `product`/`category`/
  `margin` permission check karte hain (read/write/update/delete).
- `UserController`, `RoleController`, `PermissionController` — `manage-users`
  ki jagah ab `user` permission check karte hain.
- `AuthController@login` ka response ab login ke baad `permissions` object
  mein keys `product`, `category`, etc. dega (super admin ke liye `permissions.all`,
  jaisa pehle tha — woh same hai).

**⚠️ Frontend ko isi naye naming se match karna hoga** — `permissions.product`,
`permissions.category`, etc. (super admin ke liye `permissions.all` hi rahega).

---

## 🔧 Fix #3 — `AppServiceProvider.php` mein missing policies register

**Problem:** `CategoryPolicy` aur `MarginPolicy` files thi, lekin
`$policies` array mein register nahi thi. `$this->authorize('viewAny',
Category::class)` call hote hi `AuthorizationException` (policy not found)
throw hoti.

**Fix:** `Category::class => CategoryPolicy::class` aur
`Margin::class => MarginPolicy::class` add kiye gaye.

---

## 🔧 Fix #4 — Category duplicate validation + new `margin_percentage` field

**Problem:** `CategoryController@store` mein `name` field unique nahi tha —
isliye "Beverages" 3-4 baar create ho gaya tha (jaisa tumhare JSON data mein
dikha).

**Fix:**
- `name` field ab `unique:categories,name` validation ke saath aata hai
  (create + update dono mein).
- Naya migration (`2026_06_15_060200_...`) **existing duplicates ko clean
  karta hai** (sabse purana record rakhta hai, baaki delete karta hai, aur
  unke products ko surviving category se link kar deta hai) — phir unique
  constraint add karta hai. **Isse purani galat data ki wajah se migration
  fail nahi hoga.**
- Naya migration (`2026_06_15_060100_...`) categories table mein
  `margin_percentage` column add karta hai — yeh **default margin %** hai
  jo naye product create hone par auto-apply hota hai (agar product-level
  margin nahi diya gaya). Product-level margin se override ho sakta hai.

---

## 🔧 Fix #5 — Products: `processing_cost`, `margin_percentage`, `margin_amount` fields

**Problem:** Tumhari requirement: Add Product form mein "Processing Cost" aur
"Yield/Margin %" fields chahiye, aur inline edit mein bhi yeh editable hone
chahiye. Backend mein `processing_cost` column hi nahi tha, aur margin
percentage/amount sirf alag `margins` table mein the (jo product create ke
saath link hi nahi hota tha).

**Decision (tumne confirm kiya):** Koi calculation nahi — sab manually enter/
store/show hoga. `yield_cost` waisa hi rahega jaisa hai.

**Fix:**
- Naya migration (`2026_06_15_060000_...`) products table mein 3 columns add
  karta hai: `processing_cost`, `margin_percentage`, `margin_amount` (sab
  nullable, koi calculation nahi).
- `ProductController@store`:
  - `processing_cost`, `margin_percentage`, `margin_amount` ab accept karta
    hai (sab optional/nullable).
  - Agar `margin_percentage` nahi diya gaya AND product ek category se linked
    hai jiska `margin_percentage` set hai → woh category ka default margin
    % automatically product mein copy ho jata hai.
- `ProductController@update`:
  - Same 3 fields ab update bhi ho sakte hain — isse "inline edit (price +
    processing cost + margin %) → Save" feature backend mein fully kaam
    karega.
- Response shape (`index`, `show`, `search`, `getByCategory`) ab consistent
  hai aur har product mein deta hai: `id, product_name, category_id,
  category_name, price, yield_cost, processing_cost, margin_percentage,
  margin_amount, description`.

**Note:** Purana `/margins` (CRUD via `MarginController`) abhi bhi
exist karta hai (koi change nahi), lekin ab use karne ki zaroorat nahi —
margin fields directly product ke saath store/update hote hain.

---

## 🔧 Fix #6 — `ProductController` query bug (`orWhere` scoping)

**Problem:** `index()` aur `search()` mein
`Product::where('name', 'like', ...)->orWhere('description', 'like', ...)`
type query thi — agar koi aur `where` clause (jaise category filter) baad
mein add hoti to `orWhere` SQL precedence ki wajah se sab results return kar
deta (security/logic bug — filters bypass ho jaate).

**Fix:** Search conditions ab closure mein wrap kiye gaye:
`->where(function($q) { $q->where(...)->orWhere(...); })` — taaki future
filters ke saath safely combine ho sakein.

---

## 🔧 Fix #7 — `add_role_foreign_key_to_users_table` migration duplicate column (CRITICAL for fresh installs)

**Problem:** `0001_01_01_000000_create_users_table.php` already `role_id`
column create karta hai (`unsignedBigInteger`, nullable, no FK). Lekin
`2026_06_13_093411_add_role_foreign_key_to_users_table.php` phir se
`$table->foreignId('role_id')...` se naya column add karne ki koshish karta
hai — **iska matlab "duplicate column" SQL error aayega fresh
`migrate` par.**

**Fix:** Yeh migration ab sirf existing `role_id` column par **foreign key
constraint add karta hai** (`$table->foreign('role_id')->references('id')->on('roles')->nullOnDelete()`), naya column nahi banata.

> ⚠️ Agar tumhara database already migrate ho chuka hai (jaisa current dev
> setup mein hai — JSON data already exist karta hai), to ho sakta hai yeh
> migration already kisi tarah se "pass" ho gayi ho ya manually fix ki gayi
> ho. **Production deploy (AWS) ke liye fresh database par `migrate` chalane
> se pehle yeh fixed version use karna zaroori hai**, warna migration fail
> ho jayegi.

---

## 🔧 Fix #8 — `create_permission_role_table` migration ka galat `down()`

**Problem:** Migration table `role_permissions` create karta hai, lekin
`down()` method `dropIfExists('permission_role')` (galat naam, jo exist hi
nahi karta) call karta hai. `migrate:rollback` is migration ko sahi se
rollback nahi karega.

**Fix:** `down()` ab `Schema::dropIfExists('role_permissions')` karta hai —
correct table name.

---

## 🌱 Seeder — `database/seeders/DatabaseSeeder.php`

Naya seeder banaya gaya hai jo zaroori base data set up karta hai:

- **Permissions:** `product`, `category`, `margin`, `user`
- **Roles:** `super_admin`, `admin`
- **Role-Permission mapping:**
  - `super_admin` → sab kuch full access (read/write/update/delete)
  - `admin` → `product: read only`, `category: read only`, `margin: none`,
    `user: none` (matches tumhari requirement: admin Products/Categories
    dekh sakta hai, edit/add/delete nahi, aur Users section bilkul access
    nahi)
- **Default Users:**
  - `superadmin@example.com` / `password` → role `super_admin`
  - `admin@example.com` / `password` → role `admin`

`firstOrCreate` use kiya hai, isliye existing data duplicate nahi hoga —
agar yeh emails already DB mein hain to seeder unko skip kar dega.

---

## 🚀 Deployment Steps

1. Upar wali table ke according saari files apne `Ai_backend` project mein
   copy/overwrite karo, aur 3 naye migration files add karo.
2. **Fresh database (recommended for AWS production):**
   ```bash
   php artisan migrate:fresh --seed
   ```
3. **Existing/dev database (jahan data already hai):**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```
   (Duplicate "Beverages" categories automatically clean ho jayengi migration
   ke andar.)

---

## 📡 Updated API Response Shapes (Frontend ke liye reference)

### `POST /login` (admin/non-super-admin response)
```json
{
  "status": "success",
  "data": {
    "token": "...",
    "user": { "id": 2, "name": "Admin User", "email": "admin@example.com", "role": "admin" },
    "permissions": {
      "product": { "read": true, "write": false, "update": false, "delete": false },
      "category": { "read": true, "write": false, "update": false, "delete": false },
      "margin": { "read": false, "write": false, "update": false, "delete": false },
      "user": { "read": false, "write": false, "update": false, "delete": false }
    }
  }
}
```

### `POST /login` (super_admin response — unchanged)
```json
{
  "status": "success",
  "data": {
    "token": "...",
    "user": { "id": 1, "name": "Super Admin", "email": "superadmin@example.com", "role": "super_admin" },
    "permissions": { "all": { "read": true, "write": true, "update": true, "delete": true } }
  }
}
```

### `GET /products`
```json
{
  "status": "success",
  "data": [
    {
      "id": 5,
      "product_name": "Orange Juice",
      "category_id": 1,
      "category_name": "Electronics",
      "price": "80.00",
      "yield_cost": "50.00",
      "processing_cost": "10.00",
      "margin_percentage": "20.00",
      "margin_amount": "16.00",
      "description": "Fresh Orange Juice"
    }
  ]
}
```

### `POST /products` (request body)
```json
{
  "category_id": 1,
  "name": "Orange Juice",
  "price": 80,
  "yield_cost": 50,
  "processing_cost": 10,
  "margin_percentage": 20,
  "margin_amount": 16,
  "description": "Fresh Orange Juice"
}
```
- `processing_cost`, `margin_percentage`, `margin_amount` sab **optional**.
- Agar `margin_percentage` nahi dia aur category ka default `margin_percentage`
  set hai, to woh automatically copy ho jayega.

### `PUT /products/{id}` (inline edit — request body)
```json
{
  "category_id": 1,
  "name": "Orange Juice",
  "price": 85,
  "yield_cost": 50,
  "processing_cost": 12,
  "margin_percentage": 22,
  "margin_amount": 18.7,
  "description": "Fresh Orange Juice"
}
```

### `GET /product-categories`
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "name": "Electronics",
      "description": "Electronic devices",
      "margin_percentage": "15.00",
      "created_at": "...",
      "updated_at": "..."
    }
  ]
}
```

### `POST /product-categories` (request body)
```json
{
  "name": "Beverages",
  "description": "Drinks and juices",
  "margin_percentage": 15
}
```
- Duplicate `name` ab **422 validation error** dega ("The name has already
  been taken.").

---

## ✅ Summary of all requirement fulfillment

| Requirement | Status |
|---|---|
| No duplicate categories | ✅ Fixed (validation + cleanup migration) |
| Processing Cost field (store + edit) | ✅ Added |
| Margin % / Amount (manual, no calculation) | ✅ Added |
| Category default margin % → auto-apply to new products | ✅ Added |
| Yield Cost (unchanged) | ✅ Untouched |
| Inline edit (price + processing cost + margin %) → Save | ✅ Backend supports it now |
| Admin: view products/categories, no add/edit/delete | ✅ Fixed via permission system |
| Admin: no access to Users section | ✅ Fixed (`user` permission = false for admin) |
| Super admin: full access everywhere | ✅ Fixed (short-circuit in hasPermission) |
| Role change (admin ↔ super_admin) via Users page | ✅ Already worked via `role_id`, now `role()` relation fixed so it persists correctly |
| Migration runs cleanly on fresh AWS database | ✅ Fixed duplicate-column + down() bugs |

Sab kuch ab tumhari requirement ke according match karta hai. Agla step:
Next.js frontend, jo in exact response shapes ke according banaya jayega.
