<?php

namespace Vendor\Module\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Framework\App\State;
use Magento\Eav\Model\ResourceModel\Entity\Type\CollectionFactory as EntityTypeCollectionFactory;

class ImportProducts extends Command
{
    protected $productRepository;
    protected $productFactory;
    protected $attributeSetRepository;
    protected $state;
    protected $entityTypeCollectionFactory;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        ProductInterfaceFactory $productFactory,
        AttributeSetRepositoryInterface $attributeSetRepository,
        State $state,
        EntityTypeCollectionFactory $entityTypeCollectionFactory
    ) {
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->attributeSetRepository = $attributeSetRepository;
        $this->state = $state;
        $this->entityTypeCollectionFactory = $entityTypeCollectionFactory;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('<file_name>:import:products');
        $this->setDescription('Import products from external API');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);

            // Get the default attribute set ID for products
            $entityType = $this->entityTypeCollectionFactory->create()
                ->addFieldToFilter('entity_type_code', 'catalog_product')
                ->getFirstItem();
            $attributeSetId = $entityType->getDefaultAttributeSetId();

            if (!$attributeSetId) {
                throw new \Exception('Default attribute set for products not found.');
            }

            // Get products from API - configure endpoint in your module's settings
            $apiUrl = $this->getApiUrl();
            $products = $this->getProductsFromApi($apiUrl);

            foreach ($products as $productData) {
                try {
                    $product = $this->createProduct($productData, $attributeSetId);
                    $this->productRepository->save($product);
                    $output->writeln("Imported product: {$productData['sku']}");
                } catch (\Exception $e) {
                    $output->writeln("Error importing {$productData['sku']}: {$e->getMessage()}");
                }
            }

            $output->writeln("Import process completed.");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }

    /**
     * Get API URL from configuration
     */
    protected function getApiUrl(): string
    {
        // Implement your logic to get API URL from config
        return 'https://example.com/api/products';
    }

    /**
     * Fetch products from API
     */
    protected function getProductsFromApi(string $url): array
    {
        $jsonData = file_get_contents($url);
        $products = json_decode($jsonData, true);

        if (!is_array($products)) {
            throw new \Exception('Failed to fetch or decode product data.');
        }

        return $products;
    }

    /**
     * Create product entity from data
     */
    protected function createProduct(array $productData, int $attributeSetId)
    {
        $product = $this->productFactory->create();
        $product->setSku($productData['sku']);
        $product->setName($productData['name']);
        $product->setPrice($productData['price']);
        $product->setAttributeSetId($attributeSetId);
        $product->setTypeId(ProductType::TYPE_SIMPLE);
        $product->setVisibility(4); // Catalog, Search
        $product->setStatus(1); // Enabled

        return $product;
    }
}
