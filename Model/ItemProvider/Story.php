<?php
namespace MediaLounge\Storyblok\Model\ItemProvider;

use Storyblok\Client;
use Storyblok\ClientFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sitemap\Model\SitemapItemInterfaceFactory;
use Magento\Sitemap\Model\ItemProvider\ConfigReaderInterface;
use Magento\Sitemap\Model\ItemProvider\ItemProviderInterface;

class Story implements ItemProviderInterface
{
    public const STORIES_PER_PAGE = 100;

    private SitemapItemInterfaceFactory $itemFactory;

    private ConfigReaderInterface $configReader;

    private Client $storyblokClient;

    private StoreManagerInterface $storeManager;

    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        ConfigReaderInterface $configReader,
        SitemapItemInterfaceFactory $itemFactory,
        ScopeConfigInterface $scopeConfig,
        ClientFactory $storyblokClient,
        StoreManagerInterface $storeManager
    ) {
        $this->itemFactory = $itemFactory;
        $this->configReader = $configReader;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->storyblokClient = $storyblokClient->create([
            'apiKey' => $scopeConfig->getValue(
                'storyblok/general/api_key',
                ScopeInterface::SCOPE_STORE,
                $this->storeManager->getStore()->getId()
            )
        ]);
    }

    public function getItems($storeId): array
    {
        $response = $this->getStories();
        $stories = $response->getBody()['stories'];

        $totalPages = $response->getHeaders()['Total'][0] / self::STORIES_PER_PAGE;
        $totalPages = ceil($totalPages);

        if ($totalPages > 1) {
            $paginatedStories = [];

            for ($page = 2; $page <= $totalPages; $page++) {
                $pageResponse = $this->getStories($page);
                $paginatedStories = $pageResponse->getBody()['stories'];
            }

            $stories = array_merge($stories, $paginatedStories);
        }

        return array_map(function ($item) use ($storeId) {
            return $this->itemFactory->create([
                'url' => $item['full_slug'],
                'updatedAt' => $item['published_at'],
                'priority' => $this->configReader->getPriority($storeId),
                'changeFrequency' => $this->configReader->getChangeFrequency($storeId)
            ]);
        }, $stories);
    }

    private function getStories(int $page = 1): Client
    {
        return $this->storyblokClient->getStories([
            'page' => $page,
            'per_page' => self::STORIES_PER_PAGE,
            'filter_query[component][like]' => 'page'
        ]);
    }
}
