# ElasticSuite Search Integration — Design Spec

**Date:** 2026-04-16  
**Scope:** Search only (category page deferred)  
**Approach:** Full migration (Approach C) — backend install, GraphQL suggest bridge, Daffodil frontend migration

---

## 1. Problem Statement

Three issues exist in the current search implementation:

1. **Backend**: ElasticSuite is not installed — native `Magento_OpenSearch` adapter provides poor relevance, no suggest, no thesaurus
2. **Backend gap**: ElasticSuite's autocomplete suggest has no GraphQL exposure — only a controller action
3. **Frontend**: `src/app/search/SearchService` uses Apollo directly against `products(search:...)`, bypassing `@daffodil/search-product` and NgRx — violating the project's "never direct Apollo from components/services" rule

---

## 2. Architecture Overview

```
[Angular dai-builder]
  ├── Search results page  →  @daffodil/search-product (Magento driver)
  │                               └── products(search: ...) GraphQL  ──→ ElasticSuite index
  └── Autocomplete overlay  →  ElasticsuiteSuggestService (Apollo, no NgRx)
                                  └── elasticsuiteSuggest(query: ...) GraphQL
                                          └── Develo_ElasticSuiteGraphQl
                                                  └── ElasticSuite DataProviders

[Magento / Mage-OS backend]
  ├── smile/elasticsuite ~2.11 installed — replaces native OpenSearch adapter
  ├── existing products GraphQL — now powered by ElasticSuite index (transparent)
  └── NEW: Develo_ElasticSuiteGraphQl module
              └── elasticsuiteSuggest resolver → Product + Category + Term DataProviders
```

**Key decisions:**
- Search results use `@daffodil/search-product` + NgRx — results are global state (pagination, filters, sort, total count persist across navigation)
- Autocomplete does NOT use NgRx — suggest data is ephemeral UI state; `@daffodil/search` has no Magento driver; a dedicated Angular service is the correct pattern
- All Angular data fetching goes through GraphQL — no direct REST calls to ElasticSuite from the frontend

---

## 3. Backend Layer 1 — ElasticSuite Installation

**Package:** `smile/elasticsuite` ~2.11

**Compatibility:**
- Mage-OS 2.2.1 (≈ Magento 2.4.7) ✓
- OpenSearch 2.x at `localhost:9200` ✓
- PHP 8.3 ✓

**Modules enabled by metapackage:**
- `Smile_ElasticsuiteCore` — engine adapter, index management
- `Smile_ElasticsuiteCatalog` — product/category indexing, product suggest data provider, category suggest data provider
- `Smile_ElasticsuiteThesaurus` — synonyms, popular search term data provider
- `Smile_ElasticsuiteVirtualCategory` — virtual category rules (deferred, enabled for future use)
- `Smile_ElasticsuiteCatalogRule` — price rule compatibility
- `Smile_ElasticsuiteSwatches` — swatch data in search results

**What changes:**
- `catalog/search/engine` config set to `elasticsuite` (replaces `opensearch`)
- ElasticSuite creates new versioned indices: `<store>_product_v*`, `<store>_category_v*`, `<store>_thesaurus_v*`
- Native `Magento_OpenSearch` modules remain enabled (harmless, ElasticSuite takes precedence via engine config)

**Post-install commands:**
```bash
php -d memory_limit=-1 bin/magento setup:upgrade
php -d memory_limit=-1 bin/magento setup:di:compile
bin/magento config:set catalog/search/engine elasticsuite
bin/magento indexer:reindex catalogsearch_fulltext catalog_category_product
bin/magento cache:flush
```

**Devcontainer update required:**
- `start.sh`: change `config:set catalog/search/engine opensearch` → `elasticsuite`
- `start.sh`: remove `config:set catalog/search/opensearch_server_hostname` and `opensearch_server_port` lines — ElasticSuite reads these from the install-time config

---

## 4. Backend Layer 2 — `Develo_ElasticSuiteGraphQl` Module

**Location:** `app/code/Develo/ElasticSuiteGraphQl/`

**Purpose:** Expose ElasticSuite's autocomplete data providers via a single GraphQL query, keeping the Angular frontend's data contract uniform (GraphQL only, no REST).

### 4.1 Module Files

```
app/code/Develo/ElasticSuiteGraphQl/
├── registration.php
├── composer.json
├── etc/
│   ├── module.xml              (depends on Smile_ElasticsuiteCatalog, Smile_ElasticsuiteThesaurus)
│   └── graphql/di.xml          (context area DI)
├── Model/
│   └── Resolver/
│       └── Suggest.php
└── etc/schema.graphqls
```

### 4.2 GraphQL Schema

```graphql
type Query {
    elasticsuiteSuggest(query: String!): ElasticsuiteSuggestResult
        @resolver(class: "Develo\\ElasticSuiteGraphQl\\Model\\Resolver\\Suggest")
        @doc(description: "Returns ElasticSuite autocomplete suggestions for products, categories, and popular search terms")
        @cache(cacheable: false)
}

type ElasticsuiteSuggestResult {
    products: [ElasticsuiteSuggestProduct]
    categories: [ElasticsuiteSuggestCategory]
    terms: [ElasticsuiteSuggestTerm]
}

type ElasticsuiteSuggestProduct {
    name: String
    url_key: String
    sku: String
    image_url: String
    price: Float
}

type ElasticsuiteSuggestCategory {
    name: String
    url_path: String
    product_count: Int
}

type ElasticsuiteSuggestTerm {
    query_text: String
    num_results: Int
}
```

### 4.3 Resolver Design

`Suggest.php` constructor-injects:
- `Smile\ElasticsuiteCatalog\Model\Autocomplete\Product\DataProvider`
- `Smile\ElasticsuiteCatalog\Model\Autocomplete\Category\DataProvider`
- `Smile\ElasticsuiteThesaurus\Model\Autocomplete\DataProvider`
- `Magento\Search\Model\QueryFactory` (to set the current query term on the request context)

`resolve()` method:
1. Sets query text on `QueryFactory` so DataProviders pick it up
2. Calls `getItems()` on each DataProvider
3. Maps `ItemInterface` results to the GraphQL type arrays
4. Returns the typed result array

**Result limits:** 5 products, 3 categories, 5 terms (configurable via ElasticSuite admin — no hardcoding in resolver).

---

## 5. Frontend Layer — Daffodil Migration + Autocomplete Service

### 5.1 Packages

```bash
yarn add @daffodil/search-product@0.90.0 @daffodil/search@0.90.0
```

(Match the `@daffodil/search` version already in `package.json`)

### 5.2 NgRx Wiring

In `AppModule` (or relevant feature module):
```typescript
DaffSearchProductStateModule.forRoot(),
DaffSearchProductMagentoDriverModule.forRoot(),
```

### 5.3 Search Results — Daffodil Migration

**Delete:** `SearchService` Apollo implementation (the `products(search:...)` Apollo call)

**Replace with:** `DaffSearchProductFacade` injected into components

Key facade observables used:
- `searchResult$` — `DaffSearchResult<DaffProduct>[]`
- `loading$` — boolean
- `errors$` — error state
- Dispatch: `DaffSearchProduct({ query, options: { pageSize, currentPage } })`

**Components migrated:**
- `search-page` — dispatches `DaffSearchProduct` on init/route param change, selects results + pagination from facade
- `search-overlay` — selects from facade for results section; separate autocomplete section wired to `ElasticsuiteSuggestService`

**Models:** Existing local search models deleted; Daffodil's `DaffSearchResult` and `DaffProduct` types used directly.

**Resolver:** Angular route resolver updated to dispatch `DaffSearchProduct` and wait for `loading$` to be false before activating.

### 5.4 Autocomplete — `ElasticsuiteSuggestService`

**Location:** `src/app/search/services/elasticsuite-suggest.service.ts`

**Pattern:** Apollo service (not NgRx) — correct for ephemeral UI state.

```typescript
@Injectable({ providedIn: 'root' })
export class ElasticsuiteSuggestService {
  suggest$(query: string): Observable<ElasticsuiteSuggestResult> {
    // debounce handled at call site (component/overlay)
    return this.apollo.query<{ elasticsuiteSuggest: ElasticsuiteSuggestResult }>({
      query: ELASTICSUITE_SUGGEST_QUERY,
      variables: { query },
    }).pipe(map(result => result.data.elasticsuiteSuggest));
  }
}
```

GraphQL query document (`elasticsuite-suggest.query.ts`) requests all fields from Section 4.2.

**Consumed by:**
- `desktop-search` component — `searchControl.valueChanges.pipe(debounceTime(300), switchMap(...))`
- `search-overlay` component — autocomplete section only

### 5.5 Migration Boundary Summary

| File | Action |
|---|---|
| `SearchService` (Apollo + products query) | Deleted |
| `search-page` component | Migrated to `DaffSearchProductFacade` |
| `search-overlay` component | Results → facade; suggest → `ElasticsuiteSuggestService` |
| `desktop-search` component | Updated to use `ElasticsuiteSuggestService` |
| `search-button` component | Unchanged |
| `KeyboardShortcutsService` | Unchanged |
| Route resolver | Updated to use Daffodil dispatch pattern |
| Local search models | Deleted; replaced by Daffodil types |

---

## 6. Testing Checklist

### Backend
- [ ] `bin/magento indexer:status` shows `catalogsearch_fulltext` as `Ready`
- [ ] GraphQL playground: `{ products(search: "jacket") { items { name } } }` returns results
- [ ] GraphQL playground: `{ elasticsuiteSuggest(query: "jack") { products { name } categories { name } terms { query_text } } }` returns results
- [ ] ElasticSuite admin panel accessible at `/admin/smile_elasticsuite_analytics/`

### Frontend
- [ ] Search results page loads via `DaffSearchProductFacade` (no direct Apollo calls in search components)
- [ ] Autocomplete dropdown appears on typing ≥2 chars, debounced at 300ms
- [ ] Products, categories, and popular terms sections visible in suggest dropdown
- [ ] Keyboard shortcuts still work (no regression)
- [ ] Pagination and filters work on search results page
- [ ] No console errors related to Apollo/NgRx mismatch

---

## 7. Out of Scope

- Category page ElasticSuite integration (deferred — separate spec)
- ElasticSuite Tracker / Analytics (module enabled but not wired to UI)
- Virtual categories (module enabled but not configured)
- Thesaurus synonym management UI
- Search merchandising / relevance tuning (admin configuration, post-install)
- ElasticSuite A/B testing
