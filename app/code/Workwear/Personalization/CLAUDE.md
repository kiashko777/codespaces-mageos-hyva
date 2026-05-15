# WORKWEAR PERSONALIZATION MODULE

## WHO YOU ARE
Magento 2 backend expert. Senior PHP developer. You know Magento internals deeply.
You write clean, strict, production-grade code. No shortcuts. No hacks.

## WHAT WE ARE BUILDING
Magento 2 backend module: workwear logo personalization engine.
Customers buy workwear (polos, jackets, hats) and add logos/text to them.
Logo can be embroidered or printed. Multiple positions per garment. Setup fees apply.

## READ THESE FIRST — ALWAYS
- `Website_Process.docx` — business requirements from client
- `Architectural_Blueprint.docx` — technical blueprint (7 phases)

## STACK
- Magento: Mage-OS 2.2.1
- PHP: 8.3
- DB: MariaDB 10.6
- Search: OpenSearch 2.x
- Cache: Redis
- Environment: GitHub Codespaces

## MODULE
- Vendor: `Workwear`
- Module: `Personalization`
- Full name: `Workwear_Personalization`
- Path: `app/code/Workwear/Personalization/`
- Namespace: `Workwear\Personalization`

## FRONTEND (NOT YOUR JOB — BUT KNOW IT EXISTS)
Frontend is Daffodil (Angular-based headless ecommerce framework).
It talks to Magento via:
- GraphQL — products, cart, checkout, personalization data
- REST — file uploads only (logo images)
No Luma. No Hyvä. No frontend PHP templates needed.
Your job: make the APIs work perfectly. Frontend team handles the rest.

## BUSINESS RULES (MEMORIZE THESE)
1. Logo positions are PER PRODUCT — not all positions available on all garments
2. One garment can have MULTIPLE logos (e.g. Left Chest + Right Arm)
3. Different garments in same cart can have DIFFERENT logos
4. Setup fees:
   - New logo file → £9.99 one-time
   - New text logo → £4.99 one-time
   - Same logo approved in previous order → £0.00 (waiver)
   - Same logo appears twice in current cart → £0.00 second time
5. Logo goes through approval: Pending → Approved / Rejected
6. Customer notified by email when logo approved
7. Approved logo = no setup fee on all future orders

## CODING RULES — NON-NEGOTIABLE
```
ALWAYS:
- declare(strict_types=1) in every PHP file
- Full type hints on all arguments and return types
- Inject interfaces, not concrete classes
- Use service contracts — interfaces in Api/
- PSR-2 code style
- Use \Magento\Framework\Exception\ exceptions

NEVER:
- Touch Magento core files
- Use ObjectManager directly
- Hardcode config values (fees, MIME types, paths)
- Use InstallSchema / UpgradeSchema (use db_schema.xml only)
- Use preferences/rewrites unless absolutely no other way
- Skip dependency injection
- Write SQL directly (use ResourceModel)
```

## FILE UPLOAD RULE
File uploads go through REST endpoint — NOT GraphQL.
GraphQL multipart = security risk (memory exhaustion, stream leaks).
REST endpoint: `POST /rest/V1/workwear/logo/upload`
Returns: `logo_uid` string used in GraphQL cart mutations.

## CONFIG VALUES — ALWAYS IN SYSTEM CONFIG
```
workwear/personalization/logo_fee       default: 9.99
workwear/personalization/text_fee       default: 4.99
workwear/personalization/max_file_size  default: 10485760 (10MB)
workwear/personalization/allowed_mime   default: image/png,image/jpeg,image/svg+xml
```
Read via `ScopeConfigInterface`. Never hardcode.

---

## PHASES — WORK ONE AT A TIME

### PHASE 1 — Module Skeleton + Database
**Status: [x] DONE**

Create:
```
app/code/Workwear/Personalization/
├── registration.php
├── composer.json
└── etc/
    ├── module.xml          (sequence: Magento_Quote, Magento_Sales, Magento_GraphQl)
    └── db_schema.xml
```

Tables in db_schema.xml:

`workwear_customer_logo`:
- entity_id INT PK AUTO_INCREMENT
- customer_id INT NULL FK→customer_entity
- file_path VARCHAR(255) NOT NULL
- status SMALLINT NOT NULL DEFAULT 0  (0=Pending, 1=Approved, 2=Rejected)
- file_hash VARCHAR(64) NOT NULL  (SHA-256)
- created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

`workwear_product_position`:
- entity_id INT PK AUTO_INCREMENT
- product_id INT NOT NULL FK→catalog_product_entity
- position_code VARCHAR(50) NOT NULL
  (values: LEFT_CHEST, RIGHT_CHEST, LEFT_ARM, RIGHT_ARM, REAR_NAPE,
   CENTRE_FRONT, CENTRE_BACK, CENTRE_HAT, LEFT_POCKET, RIGHT_POCKET)

Extend `quote_item`:
- personalization_data TEXT NULL  (stores JSON)

Extend `sales_order_item`:
- personalization_data TEXT NULL  (stores JSON)

Test commands:
```bash
php bin/magento module:enable Workwear_Personalization
php bin/magento setup:upgrade
php bin/magento cache:flush
mysql -u root magento -e "SHOW TABLES LIKE 'workwear%';"
php bin/magento module:status Workwear_Personalization
```
**STOP. Wait for confirmation before Phase 2.**

---

### PHASE 2 — REST File Upload API
**Status: [x] DONE**

Create:
```
etc/
├── webapi.xml              POST /rest/V1/workwear/logo/upload → requires customer auth
└── di.xml                  wire LogoUploadInterface → LogoUpload
Api/
└── LogoUploadInterface.php
Model/
├── LogoUpload.php          (implementation)
├── CustomerLogo.php        (data model)
└── ResourceModel/
    ├── CustomerLogo.php
    └── CustomerLogo/
        └── Collection.php
```

LogoUpload logic:
1. Validate MIME via `finfo_file()` — allow PNG, JPG, SVG only
2. Check file size against config max
3. SHA-256 hash the file
4. Check if hash exists in `workwear_customer_logo` → if yes, return existing `logo_uid`
5. Sanitize filename
6. Save to `pub/media/workwear/logos/{customer_id}/`
7. Insert DB record status=0 (Pending)
8. Return `logo_uid` (hash of entity_id + timestamp)

Guest uploads: customer_id = null, still tracked.

Test:
```bash
# Get customer token first
curl -X POST https://<codespace>-8080.app.github.dev/rest/V1/integration/customer/token \
  -H "Content-Type: application/json" \
  -d '{"username":"test@test.com","password":"password"}'

# Upload logo
curl -X POST https://<codespace>-8080.app.github.dev/rest/V1/workwear/logo/upload \
  -H "Authorization: Bearer <token>" \
  -F "logo=@/path/to/logo.png"
```
**STOP. Wait for confirmation before Phase 3.**

---

### PHASE 3 — GraphQL Schema + Cart Mutation
**Status: [x] DONE** (also: ProductInterface.available_positions, CartPrices.personalization_fee)

Create:
```
etc/
└── schema.graphqls
Model/Resolver/
└── UpdateCartItemPersonalization.php
```

schema.graphqls:
```graphql
enum WorkwearApplicationType {
    EMBROIDERY
    PRINT
}

enum WorkwearContentType {
    LOGO
    TEXT
}

input WorkwearPersonalizationInput {
    position_code: String!
    application_type: WorkwearApplicationType!
    content_type: WorkwearContentType!
    logo_uid: String
    text_lines: [String]
    font_family: String
}

extend input CartItemUpdateInput {
    personalizations: [WorkwearPersonalizationInput]
}
```

Resolver validation:
- position_code must exist in workwear_product_position for this product
- text_lines: max 3 items, max 22 chars each
- if content_type=LOGO: logo_uid must exist in workwear_customer_logo
- Serialize validated data to JSON → save to personalization_data on quote_item

Test GraphQL mutation:
```graphql
mutation {
  updateCartItems(input: {
    cart_id: "CART_ID"
    cart_items: [{
      cart_item_uid: "ITEM_UID"
      quantity: 1
      personalizations: [{
        position_code: "LEFT_CHEST"
        application_type: EMBROIDERY
        content_type: LOGO
        logo_uid: "LOGO_UID_FROM_UPLOAD"
      }]
    }]
  }) {
    cart {
      items {
        uid
        quantity
      }
      prices {
        grand_total { value currency }
      }
    }
  }
}
```
**STOP. Wait for confirmation before Phase 4.**

---

### PHASE 4 — Setup Fee Total Collector
**Status: [x] DONE** (fee math verified £34 + £9.99 + £4.99 = £48.98; logic shared with GraphQL resolver via PersonalizationFeeCalculator)

Create:
```
etc/
├── sales.xml               register collector (after subtotal, before tax)
├── adminhtml/system.xml    System Config fields for fees
└── config.xml              default values
Model/Total/
└── PersonalizationFee.php  extends AbstractTotal
```

collect() logic:
```
$processedHashes = []

foreach quote->getAllVisibleItems():
    data = json_decode(item->personalization_data)
    if no data: skip

    foreach data as personalization:
        if content_type = LOGO:
            logo = load from workwear_customer_logo by logo_uid
            if logo->status = 1 (Approved):
                fee = 0.00  ← repeat order waiver
            elif logo->file_hash in $processedHashes:
                fee = 0.00  ← already charged this cart
            else:
                fee = config(logo_fee)  ← £9.99
                $processedHashes[] = logo->file_hash

        if content_type = TEXT:
            textHash = sha256(implode(text_lines))
            if textHash in $processedHashes:
                fee = 0.00
            else:
                fee = config(text_fee)  ← £4.99
                $processedHashes[] = textHash

total->setTotalAmount('personalization_fee', totalFee)
total->setBaseTotalAmount('personalization_fee', totalFee)
total->setGrandTotal(grandTotal + totalFee)
```

Test — check fee in cart totals:
```graphql
{ cart(cart_id: "ID") { prices { subtotal_excluding_tax { value } grand_total { value } } } }
```
**STOP. Wait for confirmation before Phase 5.**

---

### PHASE 5 — Quote to Order Persistence
**Status: [x] DONE** (plugin wired; live checkout not exercised)

Create:
```
etc/
└── di.xml                  aroundConvert plugin on Quote\Item\ToOrderItem
Plugin/Quote/Item/
└── ToOrderItemPlugin.php
```

Plugin logic:
```php
public function aroundConvert(ToOrderItem $subject, callable $proceed, Item $item, array $data): OrderItem
{
    $orderItem = $proceed($item, $data);

    $json = $item->getData('personalization_data');
    if (!$json) return $orderItem;

    $orderItem->setData('personalization_data', $json);

    // Map to additional_options so Admin/PDF renders it natively
    $personalizations = json_decode($json, true);
    $additionalOptions = [];
    foreach ($personalizations as $p) {
        $additionalOptions[] = ['label' => 'Position', 'value' => $p['position_code']];
        $additionalOptions[] = ['label' => 'Type', 'value' => $p['application_type']];
        $additionalOptions[] = ['label' => 'Content', 'value' => $p['content_type']];
    }

    $options = $orderItem->getProductOptions();
    $options['additional_options'] = $additionalOptions;
    $orderItem->setProductOptions($options);

    return $orderItem;
}
```

Test: place order via GraphQL checkout → check in Admin → Sales → Orders → view order item details.
**STOP. Wait for confirmation before Phase 6.**

---

### PHASE 6 — Admin Grid + Approval Workflow
**Status: [x] DONE** (mass approve dispatches workwear_logo_status_approved event)

Create:
```
etc/
├── adminhtml/routes.xml
└── acl.xml
Controller/Adminhtml/Logo/
├── Index.php
├── MassApprove.php
└── MassReject.php
view/adminhtml/
├── layout/workwear_logo_index.xml
└── ui_component/workwear_logo_listing.xml
Ui/Component/Listing/Column/
└── LogoPreview.php         (thumbnail + modal expand)
```

Grid columns:
- ID
- Customer Email
- Logo Preview (thumbnail, click to expand modal)
- Status (Pending / Approved / Rejected)
- Created At

Filters: status, created_at, customer_email
Mass actions: Approve, Reject

MassApprove controller:
1. Update status → 1 in workwear_customer_logo
2. Dispatch event: `workwear_logo_status_approved` with logo entity

Test: Admin → Workwear → Logo Moderation → select logos → mass approve.
**STOP. Wait for confirmation before Phase 7.**

---

### PHASE 7 — Email Notifications
**Status: [x] DONE** (observer fires on event; events.xml in global scope; SMTP transport not configured in env — install MagePal/SMTP module to deliver to Mailpit)

Create:
```
etc/
├── events.xml              observe workwear_logo_status_approved
└── email_templates.xml     register template
Observer/
└── SendLogoApprovedEmail.php
view/frontend/email/
└── logo_approved.html
```

Observer logic:
1. Get logo entity from event
2. Load customer by customer_id
3. Build absolute URL to pub/media file
4. Use TransportBuilder to send email
5. Template vars: customer_name, logo_uid, logo_url, store_name

Email template content:
- Logo approved confirmation
- Show logo image
- Inform: future orders with this logo = no setup fee
- Professional workwear branding

Test: trigger approval in Admin → check Mailpit at port 8025.
**STOP. All phases complete.**

---

## FINAL VERIFICATION (run after ALL phases done)
```bash
php bin/magento module:status Workwear_Personalization
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush
mysql -u root magento -e "SHOW TABLES LIKE 'workwear%';"
mysql -u root magento -e "DESCRIBE workwear_customer_logo;"
mysql -u root magento -e "DESCRIBE workwear_product_position;"
mysql -u root magento -e "SHOW COLUMNS FROM quote_item LIKE 'personalization%';"
mysql -u root magento -e "SHOW COLUMNS FROM sales_order_item LIKE 'personalization%';"
```
Flag any compile errors immediately.

---

## GRAPHQL API SURFACE (what Daffodil Angular will consume)

```graphql
# Products with available personalization positions
query GetProduct($sku: String!) {
  products(filter: { sku: { eq: $sku } }) {
    items {
      sku
      name
      available_positions { position_code }  # custom resolver
    }
  }
}

# Customer logo library (requires auth)
query GetCustomerLogos {
  customerLogos {
    uid         # SHA-256 hash — use as logo_uid in cart mutations
    url         # absolute media URL for preview
    filename
    status      # "pending" | "approved" | "rejected"
    created_at
  }
}

# Add personalization to cart item
mutation UpdateCartItemWithPersonalization { ... }

# Cart with personalization fee breakdown
query GetCart($cartId: String!) {
  cart(cart_id: $cartId) {
    items { uid quantity personalization_data }
    prices {
      subtotal_excluding_tax { value }
      personalization_fee { value }   # custom total
      grand_total { value }
    }
  }
}
```

## REST API SURFACE (what Daffodil Angular will consume)

```
POST /rest/V1/workwear/logo/upload
  Auth: Bearer <customer_token>
  Body: multipart/form-data, field: logo
  Returns: { logo_uid: "abc123" }

GET /rest/V1/workwear/logos/mine
  Auth: Bearer <customer_token>
  Returns: [{ logo_uid, file_path, status, created_at }]
  Note: prefer GraphQL customerLogos query — richer response, no extra round-trip
```

---

## ENVIRONMENT NOTES
- Codespaces: Magento at localhost, ports forwarded automatically
- Storefront + GraphiQL: https://<codespace>-8080.app.github.dev/graphql
- phpMyAdmin: https://<codespace>-8081.app.github.dev
- Mailpit (email testing): https://<codespace>-8025.app.github.dev
- Admin URL: https://<codespace>-8080.app.github.dev/admin
- Admin credentials: admin / password1 (check .devcontainer/ setup scripts)
- Supervisor manages: Nginx, PHP-FPM, Redis
- Services status: `.devcontainer/scripts/status.sh`

## MODULE STATUS TRACKING
Update this section as phases complete:
```
[x] Phase 1: DB Schema          — tables verified in DB
[x] Phase 2: REST Upload        — tested with curl, dedup OK
[x] Phase 3: GraphQL            — schema introspection + cart mutation verified
[x] Phase 4: Total Collector    — £34 + £9.99 + £4.99 = £48.98 in cart, double-fee bug fixed
[x] Phase 5: Quote→Order        — plugin wired (live checkout not exercised)
[x] Phase 6: Admin Grid         — mass approve dispatches event
[x] Phase 7: Email              — observer fires; needs SMTP module in env to deliver
[x] Post-phases: customerLogos GraphQL query — CustomerLogosResolver + schema type added; frontend gap closed
```
