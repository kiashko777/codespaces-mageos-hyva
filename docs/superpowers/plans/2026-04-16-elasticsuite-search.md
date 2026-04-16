# ElasticSuite Search Integration — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Install ElasticSuite on the Mage-OS backend, expose its autocomplete suggest via a new GraphQL query, and migrate the dai-builder Angular frontend from a custom Apollo-direct SearchService to `@daffodil/search-product` + NgRx, with a lightweight service for autocomplete.

**Architecture:** ElasticSuite replaces the native OpenSearch adapter — the existing `products` GraphQL query transparently benefits. A new `Develo_ElasticSuiteGraphQl` Magento module exposes ElasticSuite's autocomplete DataProviders as an `elasticsuiteSuggest` GraphQL query. On the Angular side, `@daffodil/search-product`'s Magento driver replaces the hand-rolled SearchService for search results; a standalone `ElasticsuiteSuggestService` handles ephemeral autocomplete state via Apollo (correct pattern — not component-level).

**Tech Stack:** PHP 8.3, Mage-OS 2.2.1, `smile/elasticsuite ~2.11`, OpenSearch 2.x, Magento GraphQL, Angular 17+, `@daffodil/search-product@0.90.0`, `@daffodil/search@0.90.0`, NgRx, Apollo Angular

---

## Repos

- **Backend tasks (Tasks 1–7):** `/workspaces/codespaces-mageos-hyva/` (this repo)
- **Frontend tasks (Tasks 8–15):** `dai-builder/` repo root (clone separately if needed)

---

## File Map

### Backend (Mage-OS)

| Action | Path |
|---|---|
| Modify | `composer.json` |
| Modify | `.devcontainer/scripts/start.sh` |
| Create | `app/code/Develo/ElasticSuiteGraphQl/registration.php` |
| Create | `app/code/Develo/ElasticSuiteGraphQl/composer.json` |
| Create | `app/code/Develo/ElasticSuiteGraphQl/etc/module.xml` |
| Create | `app/code/Develo/ElasticSuiteGraphQl/etc/schema.graphqls` |
| Create | `app/code/Develo/ElasticSuiteGraphQl/Model/Resolver/Suggest.php` |
| Create | `app/code/Develo/ElasticSuiteGraphQl/Test/Unit/Model/Resolver/SuggestTest.php` |

### Frontend (dai-builder)

| Action | Path |
|---|---|
| Modify | `package.json` |
| Modify | `src/app/app.module.ts` (or relevant feature module) |
| Create | `src/app/search/graphql/elasticsuite-suggest.query.ts` |
| Create | `src/app/search/services/elasticsuite-suggest.service.ts` |
| Create | `src/app/search/services/elasticsuite-suggest.service.spec.ts` |
| Modify | `src/app/search/pages/search-page/search-page.component.ts` |
| Modify | `src/app/search/components/search-overlay/search-overlay.component.ts` |
| Modify | `src/app/search/components/desktop-search/desktop-search.component.ts` |
| Modify | `src/app/search/resolvers/search.resolver.ts` |
| Delete | `src/app/search/services/search.service.ts` (old Apollo service) |
| Delete | Local search model files (replace with Daffodil types) |

---

## Task 1: Install ElasticSuite via Composer

**Repo:** Mage-OS (`/workspaces/codespaces-mageos-hyva/`)

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Require the package**

```bash
php -d memory_limit=-1 $(which composer) require smile/elasticsuite:~2.11
```

Expected output ends with: `smile/elasticsuite` added under `require` in `composer.json`. This pulls in the full metapackage: `smile/elasticsuite-core`, `smile/elasticsuite-catalog`, `smile/elasticsuite-thesaurus`, `smile/elasticsuite-virtual-category`, `smile/elasticsuite-catalog-rule`, `smile/elasticsuite-swatches`, `smile/elasticsuite-tracker`, `smile/elasticsuite-analytics`.

- [ ] **Step 2: Run setup:upgrade**

```bash
php -d memory_limit=-1 bin/magento setup:upgrade
```

Expected: all new `Smile_Elasticsuite*` modules listed as installed.

- [ ] **Step 3: Compile DI**

```bash
php -d memory_limit=-1 bin/magento setup:di:compile
```

Expected: `Generated code successfully.`

- [ ] **Step 4: Switch search engine to ElasticSuite**

```bash
bin/magento config:set catalog/search/engine elasticsuite
```

- [ ] **Step 5: Reindex**

```bash
bin/magento indexer:reindex catalogsearch_fulltext catalog_category_product
bin/magento cache:flush
```

Expected: `catalogsearch_fulltext index has been rebuilt successfully` and `catalog_category_product index has been rebuilt successfully`.

- [ ] **Step 6: Verify ElasticSuite indices in OpenSearch**

```bash
curl -s http://localhost:9200/_cat/indices | grep -E "product|category"
```

Expected: lines like `yellow open <store_code>_product_v1 ...` and `yellow open <store_code>_category_v1 ...`.

- [ ] **Step 7: Verify products GraphQL still works**

```bash
curl -s -X POST http://localhost:8080/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"{ products(search: \"bag\") { total_count items { name } } }"}' \
  | python3 -m json.tool
```

Expected: JSON with `total_count > 0` and `items` array with product names.

- [ ] **Step 8: Commit**

```bash
git add composer.json composer.lock
git commit -m "feat: install smile/elasticsuite ~2.11 and switch search engine"
```

---

## Task 2: Update devcontainer start.sh for ElasticSuite

**Repo:** Mage-OS

**Files:**
- Modify: `.devcontainer/scripts/start.sh`

- [ ] **Step 1: Read the current search config lines**

Open `.devcontainer/scripts/start.sh` and find the block that sets search engine config. It looks like:

```bash
php -d memory_limit=-1 bin/magento config:set catalog/search/engine opensearch
php -d memory_limit=-1 bin/magento config:set catalog/search/opensearch_server_hostname localhost
php -d memory_limit=-1 bin/magento config:set catalog/search/opensearch_server_port 9200
```

- [ ] **Step 2: Replace the block**

Replace those three lines with:

```bash
php -d memory_limit=-1 bin/magento config:set catalog/search/engine elasticsuite
```

The hostname and port lines are removed — ElasticSuite reads the OpenSearch connection from the values stored during `setup:install` (already correct at `localhost:9200`).

Also find the `--search-engine` install flag block (used on fresh `INSTALL_MAGENTO=YES` runs):

```bash
      --search-engine='opensearch' \
      --opensearch-host='localhost' \
      --opensearch-port='9200'
```

Leave those lines unchanged — ElasticSuite's engine is set post-install via `config:set` (the block you already updated above).

- [ ] **Step 3: Commit**

```bash
git add .devcontainer/scripts/start.sh
git commit -m "chore: configure ElasticSuite as search engine in devcontainer start.sh"
```

---

## Task 3: Create Develo_ElasticSuiteGraphQl module skeleton

**Repo:** Mage-OS

**Files:**
- Create: `app/code/Develo/ElasticSuiteGraphQl/registration.php`
- Create: `app/code/Develo/ElasticSuiteGraphQl/composer.json`
- Create: `app/code/Develo/ElasticSuiteGraphQl/etc/module.xml`

- [ ] **Step 1: Create registration.php**

```php
<?php
// app/code/Develo/ElasticSuiteGraphQl/registration.php
use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Develo_ElasticSuiteGraphQl',
    __DIR__
);
```

- [ ] **Step 2: Create composer.json**

```json
{
    "name": "develo/module-elasticsuite-graphql",
    "description": "Exposes ElasticSuite autocomplete suggest via GraphQL",
    "type": "magento2-module",
    "version": "1.0.0",
    "require": {
        "php": "~8.3.0",
        "smile/elasticsuite-catalog": "~2.11",
        "smile/elasticsuite-thesaurus": "~2.11"
    },
    "autoload": {
        "files": ["registration.php"],
        "psr-4": {
            "Develo\\ElasticSuiteGraphQl\\": ""
        }
    }
}
```

- [ ] **Step 3: Create etc/module.xml**

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
    <module name="Develo_ElasticSuiteGraphQl">
        <sequence>
            <module name="Magento_GraphQl"/>
            <module name="Smile_ElasticsuiteCatalog"/>
            <module name="Smile_ElasticsuiteThesaurus"/>
        </sequence>
    </module>
</config>
```

- [ ] **Step 4: Enable the module**

```bash
bin/magento module:enable Develo_ElasticSuiteGraphQl
bin/magento setup:upgrade
```

Expected: `Develo_ElasticSuiteGraphQl` listed as enabled, setup completes without errors.

- [ ] **Step 5: Commit**

```bash
git add app/code/Develo/ElasticSuiteGraphQl/
git commit -m "feat: scaffold Develo_ElasticSuiteGraphQl module"
```

---

## Task 4: Define the GraphQL schema

**Repo:** Mage-OS

**Files:**
- Create: `app/code/Develo/ElasticSuiteGraphQl/etc/schema.graphqls`

- [ ] **Step 1: Create schema.graphqls**

```graphql
type Query {
    elasticsuiteSuggest(query: String! @doc(description: "The search term to get suggestions for")): ElasticsuiteSuggestResult
        @resolver(class: "Develo\\ElasticSuiteGraphQl\\Model\\Resolver\\Suggest")
        @doc(description: "Returns ElasticSuite autocomplete suggestions: matching products, categories, and popular search terms")
        @cache(cacheable: false)
}

type ElasticsuiteSuggestResult @doc(description: "Autocomplete suggestions grouped by type") {
    products: [ElasticsuiteSuggestProduct] @doc(description: "Up to 5 matching products")
    categories: [ElasticsuiteSuggestCategory] @doc(description: "Up to 3 matching categories")
    terms: [ElasticsuiteSuggestTerm] @doc(description: "Up to 5 popular search terms")
}

type ElasticsuiteSuggestProduct @doc(description: "A product suggestion from ElasticSuite autocomplete") {
    name: String @doc(description: "Product name")
    url_key: String @doc(description: "Product URL key")
    sku: String @doc(description: "Product SKU")
    image_url: String @doc(description: "Absolute URL of the product thumbnail")
    price: Float @doc(description: "Product price in store currency")
}

type ElasticsuiteSuggestCategory @doc(description: "A category suggestion from ElasticSuite autocomplete") {
    name: String @doc(description: "Category name")
    url_path: String @doc(description: "Category URL path")
    product_count: Int @doc(description: "Number of products in this category")
}

type ElasticsuiteSuggestTerm @doc(description: "A popular search term suggestion") {
    query_text: String @doc(description: "The search term")
    num_results: Int @doc(description: "Number of results this term returns")
}
```

- [ ] **Step 2: Flush cache to pick up schema changes**

```bash
bin/magento cache:flush full_page block_html
```

- [ ] **Step 3: Commit**

```bash
git add app/code/Develo/ElasticSuiteGraphQl/etc/schema.graphqls
git commit -m "feat: add elasticsuiteSuggest GraphQL schema"
```

---

## Task 5: Implement the Suggest resolver

**Repo:** Mage-OS

**Files:**
- Create: `app/code/Develo/ElasticSuiteGraphQl/Model/Resolver/Suggest.php`

- [ ] **Step 1: Create the resolver**

```php
<?php
declare(strict_types=1);

namespace Develo\ElasticSuiteGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Search\Model\QueryFactory;
use Smile\ElasticsuiteCatalog\Model\Autocomplete\Product\DataProvider as ProductDataProvider;
use Smile\ElasticsuiteCatalog\Model\Autocomplete\Category\DataProvider as CategoryDataProvider;
use Smile\ElasticsuiteThesaurus\Model\Autocomplete\DataProvider as TermDataProvider;

class Suggest implements ResolverInterface
{
    public function __construct(
        private readonly QueryFactory $queryFactory,
        private readonly ProductDataProvider $productDataProvider,
        private readonly CategoryDataProvider $categoryDataProvider,
        private readonly TermDataProvider $termDataProvider,
    ) {}

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null): array
    {
        $queryText = trim((string) ($args['query'] ?? ''));
        if ($queryText === '') {
            throw new GraphQlInputException(__('query cannot be empty'));
        }

        // Set the query text on the shared query object so DataProviders can read it
        $query = $this->queryFactory->get();
        $query->setQueryText($queryText);

        return [
            'products'   => $this->resolveProducts(),
            'categories' => $this->resolveCategories(),
            'terms'      => $this->resolveTerms(),
        ];
    }

    private function resolveProducts(): array
    {
        $results = [];
        foreach ($this->productDataProvider->getItems() as $item) {
            $data = $item->toArray();
            $results[] = [
                'name'      => $data['title'] ?? null,
                'url_key'   => $data['url'] ?? null,
                'sku'       => $data['sku'] ?? null,
                'image_url' => $data['image'] ?? null,
                'price'     => isset($data['price']) ? (float) $data['price'] : null,
            ];
        }
        return $results;
    }

    private function resolveCategories(): array
    {
        $results = [];
        foreach ($this->categoryDataProvider->getItems() as $item) {
            $data = $item->toArray();
            $results[] = [
                'name'          => $data['title'] ?? null,
                'url_path'      => $data['url'] ?? null,
                'product_count' => isset($data['num_results']) ? (int) $data['num_results'] : null,
            ];
        }
        return $results;
    }

    private function resolveTerms(): array
    {
        $results = [];
        foreach ($this->termDataProvider->getItems() as $item) {
            $data = $item->toArray();
            $results[] = [
                'query_text'  => $data['title'] ?? null,
                'num_results' => isset($data['num_results']) ? (int) $data['num_results'] : null,
            ];
        }
        return $results;
    }
}
```

- [ ] **Step 2: Recompile DI and flush cache**

```bash
php -d memory_limit=-1 bin/magento setup:di:compile
bin/magento cache:flush
```

Expected: `Generated code successfully.` — no class-not-found errors.

- [ ] **Step 3: Smoke-test the query**

```bash
curl -s -X POST http://localhost:8080/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"{ elasticsuiteSuggest(query: \"bag\") { products { name url_key price } categories { name url_path } terms { query_text } } }"}' \
  | python3 -m json.tool
```

Expected: JSON with `data.elasticsuiteSuggest` containing at least one of `products`, `categories`, or `terms` non-empty.

- [ ] **Step 4: Commit**

```bash
git add app/code/Develo/ElasticSuiteGraphQl/Model/Resolver/Suggest.php
git commit -m "feat: implement ElasticSuite suggest GraphQL resolver"
```

---

## Task 6: Unit test the Suggest resolver

**Repo:** Mage-OS

**Files:**
- Create: `app/code/Develo/ElasticSuiteGraphQl/Test/Unit/Model/Resolver/SuggestTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

namespace Develo\ElasticSuiteGraphQl\Test\Unit\Model\Resolver;

use Develo\ElasticSuiteGraphQl\Model\Resolver\Suggest;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Search\Model\Query;
use Magento\Search\Model\QueryFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Smile\ElasticsuiteCatalog\Model\Autocomplete\Category\DataProvider as CategoryDataProvider;
use Smile\ElasticsuiteCatalog\Model\Autocomplete\Product\DataProvider as ProductDataProvider;
use Smile\ElasticsuiteThesaurus\Model\Autocomplete\DataProvider as TermDataProvider;

class SuggestTest extends TestCase
{
    private Suggest $resolver;
    private MockObject $queryFactory;
    private MockObject $productDataProvider;
    private MockObject $categoryDataProvider;
    private MockObject $termDataProvider;

    protected function setUp(): void
    {
        $this->queryFactory = $this->createMock(QueryFactory::class);
        $this->productDataProvider = $this->createMock(ProductDataProvider::class);
        $this->categoryDataProvider = $this->createMock(CategoryDataProvider::class);
        $this->termDataProvider = $this->createMock(TermDataProvider::class);

        $this->resolver = new Suggest(
            $this->queryFactory,
            $this->productDataProvider,
            $this->categoryDataProvider,
            $this->termDataProvider,
        );
    }

    public function testResolveThrowsOnEmptyQuery(): void
    {
        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('query cannot be empty');

        $this->resolver->resolve(
            $this->createMock(Field::class),
            null,
            $this->createMock(ResolveInfo::class),
            null,
            ['query' => '   ']
        );
    }

    public function testResolveSetsQueryTextAndReturnsGroupedResults(): void
    {
        $mockQuery = $this->createMock(Query::class);
        $mockQuery->expects($this->once())->method('setQueryText')->with('bag');
        $this->queryFactory->expects($this->once())->method('get')->willReturn($mockQuery);

        $productItem = $this->createMock(\Magento\Search\Model\Autocomplete\ItemInterface::class);
        $productItem->method('toArray')->willReturn([
            'title' => 'Yoga Bag',
            'url'   => 'yoga-bag',
            'sku'   => 'YB001',
            'image' => 'https://example.com/yoga-bag.jpg',
            'price' => '39.99',
        ]);

        $categoryItem = $this->createMock(\Magento\Search\Model\Autocomplete\ItemInterface::class);
        $categoryItem->method('toArray')->willReturn([
            'title'       => 'Bags',
            'url'         => 'bags',
            'num_results' => '12',
        ]);

        $termItem = $this->createMock(\Magento\Search\Model\Autocomplete\ItemInterface::class);
        $termItem->method('toArray')->willReturn([
            'title'       => 'bag',
            'num_results' => '42',
        ]);

        $this->productDataProvider->method('getItems')->willReturn([$productItem]);
        $this->categoryDataProvider->method('getItems')->willReturn([$categoryItem]);
        $this->termDataProvider->method('getItems')->willReturn([$termItem]);

        $result = $this->resolver->resolve(
            $this->createMock(Field::class),
            null,
            $this->createMock(ResolveInfo::class),
            null,
            ['query' => 'bag']
        );

        $this->assertSame('Yoga Bag', $result['products'][0]['name']);
        $this->assertSame('yoga-bag', $result['products'][0]['url_key']);
        $this->assertSame('YB001', $result['products'][0]['sku']);
        $this->assertSame(39.99, $result['products'][0]['price']);
        $this->assertSame('Bags', $result['categories'][0]['name']);
        $this->assertSame('bags', $result['categories'][0]['url_path']);
        $this->assertSame(12, $result['categories'][0]['product_count']);
        $this->assertSame('bag', $result['terms'][0]['query_text']);
        $this->assertSame(42, $result['terms'][0]['num_results']);
    }

    public function testResolveHandlesEmptyProviderResults(): void
    {
        $mockQuery = $this->createMock(Query::class);
        $mockQuery->method('setQueryText');
        $this->queryFactory->method('get')->willReturn($mockQuery);

        $this->productDataProvider->method('getItems')->willReturn([]);
        $this->categoryDataProvider->method('getItems')->willReturn([]);
        $this->termDataProvider->method('getItems')->willReturn([]);

        $result = $this->resolver->resolve(
            $this->createMock(Field::class),
            null,
            $this->createMock(ResolveInfo::class),
            null,
            ['query' => 'zzznomatch']
        );

        $this->assertSame([], $result['products']);
        $this->assertSame([], $result['categories']);
        $this->assertSame([], $result['terms']);
    }
}
```

- [ ] **Step 2: Run the test — expect it to pass (resolver already written)**

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml \
  app/code/Develo/ElasticSuiteGraphQl/Test/Unit/Model/Resolver/SuggestTest.php \
  --testdox
```

Expected: 3 tests, 3 passed.

If `ProductDataProvider` or `CategoryDataProvider` are `final` and can't be mocked, add `dg/bypass-finals` (already in `require-dev`) to the phpunit bootstrap — check `dev/tests/unit/phpunit.xml` for the bootstrap file and add:

```php
\DG\BypassFinals::enable();
```

at the top of that bootstrap.

- [ ] **Step 3: Commit**

```bash
git add app/code/Develo/ElasticSuiteGraphQl/Test/Unit/
git commit -m "test: unit tests for ElasticSuiteGraphQl Suggest resolver"
```

---

## Task 7: Run phpcs on the new module

**Repo:** Mage-OS

- [ ] **Step 1: Check coding standard**

```bash
vendor/bin/phpcs --standard=Magento2 app/code/Develo/ElasticSuiteGraphQl/
```

- [ ] **Step 2: Auto-fix any fixable violations**

```bash
vendor/bin/phpcbf --standard=Magento2 app/code/Develo/ElasticSuiteGraphQl/
```

- [ ] **Step 3: Re-run phpcs — expect zero errors**

```bash
vendor/bin/phpcs --standard=Magento2 app/code/Develo/ElasticSuiteGraphQl/
```

- [ ] **Step 4: Commit if any fixes applied**

```bash
git add app/code/Develo/ElasticSuiteGraphQl/
git commit -m "style: apply Magento2 coding standard to ElasticSuiteGraphQl module"
```

---

## Task 8: Install @daffodil/search-product and wire NgRx (dai-builder)

**Repo:** `dai-builder/`

**Files:**
- Modify: `package.json`
- Modify: `src/app/app.module.ts` (or the search feature module — check which file imports the store)

- [ ] **Step 1: Install the package**

```bash
yarn add @daffodil/search-product@0.90.0
```

`@daffodil/search@0.90.0` is already in `package.json` — do not upgrade it.

- [ ] **Step 2: Add state and driver modules**

In the Angular module that bootstraps the NgRx store (typically `AppModule` or `SearchModule`), add:

```typescript
import { DaffSearchProductStateModule } from '@daffodil/search-product/state';
import { DaffSearchProductMagentoDriverModule } from '@daffodil/search-product/driver/magento';

@NgModule({
  imports: [
    // ... existing imports
    DaffSearchProductStateModule,
    DaffSearchProductMagentoDriverModule.forRoot(),
  ],
})
```

- [ ] **Step 3: Verify compilation**

```bash
yarn build --configuration=development 2>&1 | tail -20
```

Expected: no TypeScript errors related to the new imports. (Full build not required — compilation check is enough.)

- [ ] **Step 4: Commit**

```bash
git add package.json yarn.lock src/app/app.module.ts
git commit -m "feat: install @daffodil/search-product and wire NgRx state + Magento driver"
```

---

## Task 9: Create the ElasticSuite suggest GraphQL query document (dai-builder)

**Repo:** `dai-builder/`

**Files:**
- Create: `src/app/search/graphql/elasticsuite-suggest.query.ts`

- [ ] **Step 1: Create the query file**

```typescript
// src/app/search/graphql/elasticsuite-suggest.query.ts
import { gql } from 'apollo-angular';

export interface ElasticsuiteSuggestProduct {
  name: string;
  url_key: string;
  sku: string;
  image_url: string | null;
  price: number | null;
}

export interface ElasticsuiteSuggestCategory {
  name: string;
  url_path: string;
  product_count: number | null;
}

export interface ElasticsuiteSuggestTerm {
  query_text: string;
  num_results: number | null;
}

export interface ElasticsuiteSuggestResult {
  products: ElasticsuiteSuggestProduct[];
  categories: ElasticsuiteSuggestCategory[];
  terms: ElasticsuiteSuggestTerm[];
}

export interface ElasticsuiteSuggestResponse {
  elasticsuiteSuggest: ElasticsuiteSuggestResult;
}

export const ELASTICSUITE_SUGGEST_QUERY = gql`
  query ElasticsuiteSuggest($query: String!) {
    elasticsuiteSuggest(query: $query) {
      products {
        name
        url_key
        sku
        image_url
        price
      }
      categories {
        name
        url_path
        product_count
      }
      terms {
        query_text
        num_results
      }
    }
  }
`;
```

- [ ] **Step 2: Commit**

```bash
git add src/app/search/graphql/elasticsuite-suggest.query.ts
git commit -m "feat: add ElasticSuite suggest GraphQL query document and types"
```

---

## Task 10: Create ElasticsuiteSuggestService (dai-builder)

**Repo:** `dai-builder/`

**Files:**
- Create: `src/app/search/services/elasticsuite-suggest.service.ts`
- Create: `src/app/search/services/elasticsuite-suggest.service.spec.ts`

- [ ] **Step 1: Write the failing test first**

```typescript
// src/app/search/services/elasticsuite-suggest.service.spec.ts
import { TestBed } from '@angular/core/testing';
import { ApolloTestingModule, ApolloTestingController } from 'apollo-angular/testing';
import { ElasticsuiteSuggestService } from './elasticsuite-suggest.service';
import {
  ELASTICSUITE_SUGGEST_QUERY,
  ElasticsuiteSuggestResult,
} from '../graphql/elasticsuite-suggest.query';

describe('ElasticsuiteSuggestService', () => {
  let service: ElasticsuiteSuggestService;
  let apolloController: ApolloTestingController;

  const mockResult: ElasticsuiteSuggestResult = {
    products: [{ name: 'Yoga Bag', url_key: 'yoga-bag', sku: 'YB001', image_url: null, price: 39.99 }],
    categories: [{ name: 'Bags', url_path: 'bags', product_count: 12 }],
    terms: [{ query_text: 'bag', num_results: 42 }],
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [ApolloTestingModule],
      providers: [ElasticsuiteSuggestService],
    });
    service = TestBed.inject(ElasticsuiteSuggestService);
    apolloController = TestBed.inject(ApolloTestingController);
  });

  afterEach(() => apolloController.verify());

  it('should return suggest results for a query', (done) => {
    service.suggest$('bag').subscribe((result) => {
      expect(result.products.length).toBe(1);
      expect(result.products[0].name).toBe('Yoga Bag');
      expect(result.categories[0].name).toBe('Bags');
      expect(result.terms[0].query_text).toBe('bag');
      done();
    });

    const op = apolloController.expectOne(ELASTICSUITE_SUGGEST_QUERY);
    expect(op.operation.variables['query']).toBe('bag');
    op.flushData({ elasticsuiteSuggest: mockResult });
  });

  it('should return empty collections when nothing matches', (done) => {
    service.suggest$('zzznomatch').subscribe((result) => {
      expect(result.products).toEqual([]);
      expect(result.categories).toEqual([]);
      expect(result.terms).toEqual([]);
      done();
    });

    const op = apolloController.expectOne(ELASTICSUITE_SUGGEST_QUERY);
    op.flushData({ elasticsuiteSuggest: { products: [], categories: [], terms: [] } });
  });
});
```

- [ ] **Step 2: Run the test — expect it to fail**

```bash
yarn test --include=src/app/search/services/elasticsuite-suggest.service.spec.ts --watch=false
```

Expected: `Cannot find module './elasticsuite-suggest.service'`

- [ ] **Step 3: Implement the service**

```typescript
// src/app/search/services/elasticsuite-suggest.service.ts
import { Injectable } from '@angular/core';
import { Apollo } from 'apollo-angular';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import {
  ELASTICSUITE_SUGGEST_QUERY,
  ElasticsuiteSuggestResponse,
  ElasticsuiteSuggestResult,
} from '../graphql/elasticsuite-suggest.query';

@Injectable({ providedIn: 'root' })
export class ElasticsuiteSuggestService {
  constructor(private apollo: Apollo) {}

  suggest$(query: string): Observable<ElasticsuiteSuggestResult> {
    return this.apollo
      .query<ElasticsuiteSuggestResponse>({
        query: ELASTICSUITE_SUGGEST_QUERY,
        variables: { query },
        fetchPolicy: 'network-only',
      })
      .pipe(map((result) => result.data.elasticsuiteSuggest));
  }
}
```

- [ ] **Step 4: Run the test — expect it to pass**

```bash
yarn test --include=src/app/search/services/elasticsuite-suggest.service.spec.ts --watch=false
```

Expected: 2 specs, 0 failures.

- [ ] **Step 5: Commit**

```bash
git add src/app/search/services/elasticsuite-suggest.service.ts \
        src/app/search/services/elasticsuite-suggest.service.spec.ts
git commit -m "feat: add ElasticsuiteSuggestService"
```

---

## Task 11: Migrate search-page to DaffSearchProductFacade (dai-builder)

**Repo:** `dai-builder/`

**Files:**
- Modify: `src/app/search/pages/search-page/search-page.component.ts`

- [ ] **Step 1: Read the current search-page component**

Open `src/app/search/pages/search-page/search-page.component.ts` and note:
- How it currently calls `SearchService`
- How it reads the route param for the search query
- How results are bound in the template

- [ ] **Step 2: Replace the implementation**

Replace the component class with the Daffodil facade pattern. The key changes:
- Inject `DaffSearchProductFacade` instead of `SearchService`
- Inject `DaffSearchProductCollectionFacade` for pagination
- Dispatch `DaffSearchLoad` on route param change
- Dispatch `DaffSearchProductCollectionChangeCurrentPage` for pagination

```typescript
import { Component, OnInit, OnDestroy } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntil, distinctUntilChanged } from 'rxjs/operators';
import { Store } from '@ngrx/store';
import { DaffSearchLoad } from '@daffodil/search/state';
import { DaffSearchProductFacade } from '@daffodil/search-product/state';
import { DaffSearchProductCollectionFacade } from '@daffodil/search-product/state';
import { DaffSearchProductCollectionChangeCurrentPage } from '@daffodil/search-product/state';
import { DaffSearchProductResult } from '@daffodil/search-product';

@Component({
  selector: 'app-search-page',
  templateUrl: './search-page.component.html',
})
export class SearchPageComponent implements OnInit, OnDestroy {
  productResults$ = this.searchFacade.productResults$;
  loading$ = this.collectionFacade.loading$;
  totalCount$ = this.collectionFacade.count$;
  currentPage$ = this.collectionFacade.currentPage$;
  pageSize$ = this.collectionFacade.pageSize$;

  private destroy$ = new Subject<void>();

  constructor(
    private route: ActivatedRoute,
    private store: Store,
    private searchFacade: DaffSearchProductFacade,
    private collectionFacade: DaffSearchProductCollectionFacade,
  ) {}

  ngOnInit(): void {
    this.route.queryParams
      .pipe(
        takeUntil(this.destroy$),
        distinctUntilChanged((a, b) => a['q'] === b['q'] && a['page'] === b['page']),
      )
      .subscribe(params => {
        const query = params['q'] ?? '';
        const currentPage = params['page'] ? Number(params['page']) : 1;
        if (query) {
          this.store.dispatch(DaffSearchLoad({ query, options: { currentPage } }));
        }
      });
  }

  onPageChange(page: number): void {
    this.store.dispatch(DaffSearchProductCollectionChangeCurrentPage(page));
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }
}
```

- [ ] **Step 3: Update the template** to bind `productResults$ | async` where it previously bound `SearchService` observables. The `DaffSearchProductResult` type extends `DaffProduct` — fields like `name`, `url_key`, `price`, `thumbnail` are all available directly.

- [ ] **Step 4: Verify compilation**

```bash
yarn build --configuration=development 2>&1 | grep -i error
```

Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add src/app/search/pages/search-page/
git commit -m "feat: migrate search-page to DaffSearchProductFacade + NgRx"
```

---

## Task 12: Migrate search-overlay to use facade + suggest service (dai-builder)

**Repo:** `dai-builder/`

**Files:**
- Modify: `src/app/search/components/search-overlay/search-overlay.component.ts`

- [ ] **Step 1: Read the current search-overlay component**

Open the file. Note how it uses `SearchService` — it likely has:
- A search input that drives both results and autocomplete suggestions
- A results section (product grid)
- A suggestions section (autocomplete dropdown)

- [ ] **Step 2: Split the data sources**

Results come from `DaffSearchProductFacade.productResults$` (dispatched via `DaffSearchLoad`). Suggestions come from `ElasticsuiteSuggestService.suggest$()` with debounce.

```typescript
import { Component, OnInit, OnDestroy } from '@angular/core';
import { FormControl } from '@angular/forms';
import { Router } from '@angular/router';
import { Store } from '@ngrx/store';
import { Observable, Subject, EMPTY } from 'rxjs';
import { debounceTime, distinctUntilChanged, switchMap, takeUntil, filter } from 'rxjs/operators';
import { DaffSearchLoad } from '@daffodil/search/state';
import { DaffSearchProductFacade } from '@daffodil/search-product/state';
import { DaffSearchProductResult } from '@daffodil/search-product';
import { ElasticsuiteSuggestService } from '../../services/elasticsuite-suggest.service';
import { ElasticsuiteSuggestResult } from '../../graphql/elasticsuite-suggest.query';

@Component({
  selector: 'app-search-overlay',
  templateUrl: './search-overlay.component.html',
})
export class SearchOverlayComponent implements OnInit, OnDestroy {
  searchControl = new FormControl('');
  productResults$: Observable<DaffSearchProductResult[]> = this.searchFacade.productResults$;
  suggestions$: Observable<ElasticsuiteSuggestResult>;

  private destroy$ = new Subject<void>();

  constructor(
    private store: Store,
    private router: Router,
    private searchFacade: DaffSearchProductFacade,
    private suggestService: ElasticsuiteSuggestService,
  ) {}

  ngOnInit(): void {
    this.suggestions$ = this.searchControl.valueChanges.pipe(
      takeUntil(this.destroy$),
      debounceTime(300),
      distinctUntilChanged(),
      filter((query): query is string => !!query && query.length >= 2),
      switchMap(query => this.suggestService.suggest$(query)),
    );
  }

  submitSearch(query: string): void {
    if (!query.trim()) { return; }
    this.store.dispatch(DaffSearchLoad({ query }));
    this.router.navigate(['/search'], { queryParams: { q: query } });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }
}
```

- [ ] **Step 3: Update the overlay template** to bind `suggestions$ | async` for the autocomplete dropdown and `productResults$ | async` for inline result previews.

- [ ] **Step 4: Verify compilation**

```bash
yarn build --configuration=development 2>&1 | grep -i error
```

- [ ] **Step 5: Commit**

```bash
git add src/app/search/components/search-overlay/
git commit -m "feat: migrate search-overlay to Daffodil facade + ElasticsuiteSuggestService"
```

---

## Task 13: Migrate desktop-search to use suggest service (dai-builder)

**Repo:** `dai-builder/`

**Files:**
- Modify: `src/app/search/components/desktop-search/desktop-search.component.ts`

- [ ] **Step 1: Read the current component** — note how autocomplete is currently triggered

- [ ] **Step 2: Replace autocomplete with suggest service**

```typescript
import { Component, OnInit, OnDestroy } from '@angular/core';
import { FormControl } from '@angular/forms';
import { Router } from '@angular/router';
import { Store } from '@ngrx/store';
import { Observable, Subject, EMPTY } from 'rxjs';
import { debounceTime, distinctUntilChanged, switchMap, takeUntil, filter } from 'rxjs/operators';
import { DaffSearchLoad } from '@daffodil/search/state';
import { ElasticsuiteSuggestService } from '../../services/elasticsuite-suggest.service';
import { ElasticsuiteSuggestResult } from '../../graphql/elasticsuite-suggest.query';

@Component({
  selector: 'app-desktop-search',
  templateUrl: './desktop-search.component.html',
})
export class DesktopSearchComponent implements OnInit, OnDestroy {
  searchControl = new FormControl('');
  suggestions$: Observable<ElasticsuiteSuggestResult>;

  private destroy$ = new Subject<void>();

  constructor(
    private store: Store,
    private router: Router,
    private suggestService: ElasticsuiteSuggestService,
  ) {}

  ngOnInit(): void {
    this.suggestions$ = this.searchControl.valueChanges.pipe(
      takeUntil(this.destroy$),
      debounceTime(300),
      distinctUntilChanged(),
      filter((query): query is string => !!query && query.length >= 2),
      switchMap(query => this.suggestService.suggest$(query)),
    );
  }

  submitSearch(): void {
    const query = this.searchControl.value?.trim() ?? '';
    if (!query) { return; }
    this.store.dispatch(DaffSearchLoad({ query }));
    this.router.navigate(['/search'], { queryParams: { q: query } });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }
}
```

- [ ] **Step 3: Update the template** — bind `suggestions$ | async` to the autocomplete dropdown. Render `result.products`, `result.categories`, `result.terms` sections.

- [ ] **Step 4: Commit**

```bash
git add src/app/search/components/desktop-search/
git commit -m "feat: wire desktop-search autocomplete to ElasticsuiteSuggestService"
```

---

## Task 14: Update route resolver (dai-builder)

**Repo:** `dai-builder/`

**Files:**
- Modify: `src/app/search/resolvers/search.resolver.ts`

- [ ] **Step 1: Read the current resolver**

The resolver likely calls `SearchService.search(query)` and waits for results before activating the route.

- [ ] **Step 2: Replace with Daffodil dispatch + wait pattern**

```typescript
import { Injectable } from '@angular/core';
import { ActivatedRouteSnapshot, Resolve } from '@angular/router';
import { Store } from '@ngrx/store';
import { Observable } from 'rxjs';
import { filter, take } from 'rxjs/operators';
import { DaffSearchLoad } from '@daffodil/search/state';
import { DaffSearchProductCollectionFacade } from '@daffodil/search-product/state';

@Injectable({ providedIn: 'root' })
export class SearchResolver implements Resolve<boolean> {
  constructor(
    private store: Store,
    private collectionFacade: DaffSearchProductCollectionFacade,
  ) {}

  resolve(route: ActivatedRouteSnapshot): Observable<boolean> {
    const query = route.queryParamMap.get('q') ?? '';
    if (query) {
      this.store.dispatch(DaffSearchLoad({ query }));
    }
    // Wait until loading is false (search complete) before activating the route
    return this.collectionFacade.loading$.pipe(
      filter(loading => !loading),
      take(1),
    );
  }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/app/search/resolvers/search.resolver.ts
git commit -m "feat: update search resolver to dispatch DaffSearchLoad and wait on loading$"
```

---

## Task 15: Remove the old SearchService and local models (dai-builder)

**Repo:** `dai-builder/`

- [ ] **Step 1: Delete the old SearchService**

```bash
rm src/app/search/services/search.service.ts
# Also delete its spec if it exists
rm -f src/app/search/services/search.service.spec.ts
```

- [ ] **Step 2: Delete local search models**

Check `src/app/search/models/` — delete any local model files that defined the old search result shape (now replaced by `DaffSearchProductResult`).

```bash
# Inspect first
ls src/app/search/models/

# Delete models that are no longer used (keep any that are still referenced elsewhere)
rm src/app/search/models/<old-search-result>.model.ts
```

- [ ] **Step 3: Verify no remaining imports of the deleted files**

```bash
grep -r "search.service\|SearchService" src/app/search/ --include="*.ts"
```

Expected: no results (or only the new `elasticsuite-suggest.service` which is the correct one).

- [ ] **Step 4: Full compilation check**

```bash
yarn build --configuration=development 2>&1 | grep -i error
```

Expected: zero TypeScript errors.

- [ ] **Step 5: Run all search-related tests**

```bash
yarn test --include="src/app/search/**/*.spec.ts" --watch=false
```

Expected: all pass.

- [ ] **Step 6: Commit**

```bash
git add -A src/app/search/
git commit -m "refactor: remove old SearchService and replace with Daffodil facade + ElasticsuiteSuggestService"
```

---

## Task 16: End-to-end verification

**Requires both repos running.**

- [ ] **Backend: Confirm ElasticSuite admin is accessible**

  Visit `http://localhost:8080/admin/smile_elasticsuite_analytics/` — should load without error.

- [ ] **Backend: Confirm suggest GraphQL returns data**

```bash
curl -s -X POST http://localhost:8080/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"{ elasticsuiteSuggest(query: \"jacket\") { products { name price } categories { name product_count } terms { query_text } } }"}' \
  | python3 -m json.tool
```

Expected: non-empty results in at least `products` or `terms`.

- [ ] **Frontend: Search results page loads via Daffodil**

  1. Open dai-builder in browser
  2. Navigate to `/search?q=jacket`
  3. Open browser DevTools → Network tab
  4. Confirm the `products(search: ...)` GraphQL request fires and returns results
  5. Confirm no Apollo requests go directly from `SearchService` (it is deleted)

- [ ] **Frontend: Autocomplete appears while typing**

  1. Click the search input
  2. Type `jac` (at least 2 chars)
  3. After 300ms debounce: confirm `elasticsuiteSuggest` GraphQL request fires
  4. Confirm dropdown shows product suggestions, category suggestions, and/or popular terms

- [ ] **Frontend: Keyboard shortcuts still work**

  Test any keyboard shortcuts defined in `KeyboardShortcutsService` — confirm no regressions.

- [ ] **Frontend: Pagination works on search results**

  If the search returns >1 page of results, confirm page change dispatches `DaffSearchProductCollectionChangeCurrentPage` (visible in NgRx DevTools or network requests).

- [ ] **Tag the backend release**

```bash
# In Mage-OS repo
git tag v1.0.0-elasticsuite-search
```

---

## Self-Review Notes

**Spec coverage check:**
- ElasticSuite install → Task 1 ✓
- devcontainer start.sh update → Task 2 ✓
- `Develo_ElasticSuiteGraphQl` module → Tasks 3–4 ✓
- Suggest resolver → Task 5 ✓
- Unit tests for resolver → Task 6 ✓
- Coding standard → Task 7 ✓
- `@daffodil/search-product` NgRx wiring → Task 8 ✓
- GraphQL query document + types → Task 9 ✓
- `ElasticsuiteSuggestService` → Task 10 ✓
- `search-page` migration → Task 11 ✓
- `search-overlay` migration → Task 12 ✓
- `desktop-search` migration → Task 13 ✓
- Route resolver → Task 14 ✓
- Delete old SearchService → Task 15 ✓
- E2E verification → Task 16 ✓

**search-button and KeyboardShortcutsService** — spec says unchanged; correctly omitted from tasks.
