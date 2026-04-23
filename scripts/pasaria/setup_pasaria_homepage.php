<?php

declare(strict_types=1);

use Magento\Cms\Api\Data\PageInterfaceFactory;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\ResourceModel\Page as PageResource;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\App\Config\Storage\WriterInterface;

require __DIR__ . '/../../app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();

/** @var State $state */
$state = $om->get(State::class);
try {
    $state->setAreaCode('adminhtml');
} catch (Throwable $e) {
}

/** @var ResourceConnection $resource */
$resource = $om->get(ResourceConnection::class);
/** @var PageRepositoryInterface $pageRepository */
$pageRepository = $om->get(PageRepositoryInterface::class);
/** @var PageInterfaceFactory $pageFactory */
$pageFactory = $om->get(PageInterfaceFactory::class);
/** @var PageResource $pageResource */
$pageResource = $om->get(PageResource::class);
/** @var WriterInterface $configWriter */
$configWriter = $om->get(WriterInterface::class);

$conn = $resource->getConnection();

$stores = $conn->fetchAll("SELECT store_id, code, name FROM store WHERE code IN ('pasaria_id', 'pasaria_en') ORDER BY store_id ASC");
if (empty($stores)) {
    throw new RuntimeException('Pasaria store views not found.');
}

$websiteId = (int) $conn->fetchOne("SELECT website_id FROM store_website WHERE code = 'pasaria' LIMIT 1");

$nameAttributeIds = $conn->fetchCol(
    "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id IN (
        SELECT entity_type_id FROM eav_entity_type WHERE entity_type_code = 'catalog_product'
    )"
);
$priceAttributeIds = $conn->fetchCol(
    "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'price' AND entity_type_id IN (
        SELECT entity_type_id FROM eav_entity_type WHERE entity_type_code = 'catalog_product'
    )"
);

$nameIn = empty($nameAttributeIds) ? '0' : implode(',', array_map('intval', $nameAttributeIds));
$priceIn = empty($priceAttributeIds) ? '0' : implode(',', array_map('intval', $priceAttributeIds));

$products = $conn->fetchAll(
    "SELECT cpe.entity_id, cpe.sku,
            MAX(name_v.value) AS product_name,
            MAX(price_d.value) AS product_price
     FROM catalog_product_entity cpe
     INNER JOIN catalog_product_website cpw
       ON cpw.product_id = cpe.entity_id AND cpw.website_id = :website_id
     LEFT JOIN catalog_product_entity_varchar name_v
       ON name_v.entity_id = cpe.entity_id
      AND name_v.store_id = 0
      AND name_v.attribute_id IN ({$nameIn})
     LEFT JOIN catalog_product_entity_decimal price_d
       ON price_d.entity_id = cpe.entity_id
      AND price_d.store_id = 0
      AND price_d.attribute_id IN ({$priceIn})
     GROUP BY cpe.entity_id, cpe.sku
     ORDER BY cpe.entity_id DESC
     LIMIT 12",
    ['website_id' => $websiteId]
);

$cards = '';
foreach ($products as $product) {
    $id = (int) $product['entity_id'];
    $name = htmlspecialchars((string) ($product['product_name'] ?: $product['sku']), ENT_QUOTES, 'UTF-8');
    $price = number_format((float) ($product['product_price'] ?: 0), 0, ',', '.');
    $cards .= '<div style="border:1px solid #ddd;border-radius:10px;padding:14px;background:#fff;">';
    $cards .= '<h4 style="margin:0 0 6px 0;font-size:16px;">' . $name . '</h4>';
    $cards .= '<p style="margin:0 0 10px 0;color:#444;">Rp ' . $price . '</p>';
    $cards .= '<a href="{{store url=\"catalog/product/view/id/' . $id . '/\"}}" style="display:inline-block;padding:8px 12px;background:#1f7a4f;color:#fff;text-decoration:none;border-radius:6px;">Lihat Produk</a>';
    $cards .= '</div>';
}

if ($cards === '') {
    $cards = '<p>Produk Pasaria akan tampil di sini.</p>';
}

$content = '<div style="max-width:1100px;margin:0 auto;padding:20px 16px;font-family:Arial, sans-serif;">';
$content .= '<h1 style="margin:0 0 8px 0;">Pasaria Featured Products</h1>';
$content .= '<p style="margin:0 0 20px 0;color:#555;">Koleksi produk terbaru Pasaria.</p>';
$content .= '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;">' . $cards . '</div>';
$content .= '</div>';

$identifier = 'pasaria-home';
$pageId = (int) $conn->fetchOne('SELECT page_id FROM cms_page WHERE identifier = :identifier LIMIT 1', ['identifier' => $identifier]);

if ($pageId > 0) {
    $page = $pageRepository->getById($pageId);
} else {
    $page = $pageFactory->create();
    $page->setIdentifier($identifier);
}

$page->setTitle('Pasaria Home');
$page->setPageLayout('1column');
$page->setIsActive(true);
$page->setContentHeading('Pasaria Home');
$page->setContent($content);
$page->setStores(array_map(static fn(array $s): int => (int) $s['store_id'], $stores));

$savedPage = $pageRepository->save($page);
$savedPageId = (int) $savedPage->getId();

$pageResource->save($savedPage);

foreach ($stores as $store) {
    $storeId = (int) $store['store_id'];
    $configWriter->save('web/default/cms_home_page', $identifier, 'stores', $storeId);
    echo 'Updated home page for store ' . $store['code'] . ' (store_id=' . $storeId . ')' . PHP_EOL;
}

echo 'Pasaria homepage setup completed. page_id=' . $savedPageId . ', identifier=' . $identifier . PHP_EOL;
