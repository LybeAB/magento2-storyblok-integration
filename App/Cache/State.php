<?php
namespace MediaLounge\Storyblok\App\Cache;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\App\DeploymentConfig\Writer;
use Psr\Log\LoggerInterface;

class State extends \Magento\Framework\App\Cache\State
{
    private Json $json;

    private RequestInterface $request;

    private LoggerInterface $logger;

    public function __construct(
        Json $json,
        RequestInterface $request,
        DeploymentConfig $config,
        Writer $writer,
        LoggerInterface $logger,
        $banAll = false
    ) {
        parent::__construct($config, $writer, $banAll);

        $this->json = $json;
        $this->request = $request;
        $this->logger = $logger;
    }

    public function isEnabled($cacheType): bool
    {
        $postContent = [];

        if ($this->isJsonPostRequest($this->request)) {
            try {
                $postContent = $this->json->unserialize($this->request->getContent());
            } catch (\InvalidArgumentException $exception) {
                $this->logger->error($exception->getMessage(), [$this->request->getContent()]);
            }
        }

        if (
            in_array($cacheType, ['block_html', 'full_page']) &&
            ($this->request->getParam('_storyblok') || !empty($postContent['_storyblok']))
        ) {
            return false;
        }

        return parent::isEnabled($cacheType);
    }

    private function isJsonPostRequest(RequestInterface $request): bool
    {
        return $request->getContent() && $request->getHeader('Content-Type') === 'application/json';
    }
}