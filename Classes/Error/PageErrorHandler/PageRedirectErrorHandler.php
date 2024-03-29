<?php

declare(strict_types=1);

namespace MbhSoftware\PageRedirectHander\Error\PageErrorHandler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Error\PageErrorHandler\PageErrorHandlerInterface;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;

/**
 * An error handler that redirects to a configured uri
 */
class PageRedirectErrorHandler implements PageErrorHandlerInterface
{

    /**
     * @var int
     */
    protected $statusCode;

    /**
     * @var array
     */
    protected $errorHandlerConfiguration;

    /**
     * FluidPageErrorHandler constructor.
     * @param int $statusCode
     * @param array $configuration
     */
    public function __construct(int $statusCode, array $configuration)
    {
        $this->statusCode = $statusCode;
        if (empty($configuration['errorRedirectTarget'])) {
            throw new \InvalidArgumentException('PageRedirectErrorHandler needs to have a proper target set.', 1522826413);
        }
        $this->errorHandlerConfiguration = $configuration;
    }

    /**
     * @param ServerRequestInterface $request
     * @param string $message
     * @param array $reasons
     * @return ResponseInterface
     */
    public function handlePageError(ServerRequestInterface $request, string $message, array $reasons = []): ResponseInterface
    {
        $resolvedUrl = $this->resolveUrl($request, $this->errorHandlerConfiguration['errorRedirectTarget']);
        $queryString = '';
        if (array_key_exists('errorRedirectAdditionalParameters', $this->errorHandlerConfiguration) && is_array($this->errorHandlerConfiguration['errorRedirectAdditionalParameters'])) {
            $queryParams = [];
            foreach ($this->errorHandlerConfiguration['errorRedirectAdditionalParameters'] as $key => $value) {
                $queryParams[$key] = $this->replaceDynamicAdditionalParameterValue($request, $value);
            }
            $queryString = HttpUtility::buildQueryString($queryParams, '?');
        }
        $errorRedirectTargetStatusCode = isset($this->errorHandlerConfiguration['errorRedirectTargetStatuscode']) ? (int)$this->errorHandlerConfiguration['errorRedirectTargetStatuscode']
            : 307;

        return new RedirectResponse(
            $resolvedUrl . $queryString,
            $errorRedirectTargetStatusCode,
            ['X-Redirect-By' => 'TYPO3 ' . $this->statusCode . 'errorHandling ']
        );
    }

    /**
     * @param ServerRequestInterface $request
     * @param string $value
     */
    protected function replaceDynamicAdditionalParameterValue($request, $value)
    {
        $replaces = [];
        preg_match_all('/###([a-zA-Z_3]*)###/', $value, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $key => $match) {
                switch ($match) {
                    case 'CURRENT_URL':
                        $currentUri = $request->getUri();
                        $replaces[$matches[0][$key]] = $currentUri->getPath();
                        break;
                    default:
                        $replaces[$matches[0][$key]] = GeneralUtility::getIndpEnv($match);
                }
            }
        }
        $value = str_replace(
            array_keys($replaces),
            array_values($replaces),
            $value
        );
        return $value;
    }

    /**
     * Resolve the URL (currently only page and external URL are supported)
     *
     * @param ServerRequestInterface $request
     * @param string $typoLinkUrl
     * @return string
     * @throws SiteNotFoundException
     * @throws InvalidRouteArgumentsException
     */
    protected function resolveUrl(ServerRequestInterface $request, string $typoLinkUrl): string
    {
        $linkService = GeneralUtility::makeInstance(LinkService::class);
        $urlParams = $linkService->resolve($typoLinkUrl);
        if ($urlParams['type'] !== 'page' && $urlParams['type'] !== 'url') {
            throw new \InvalidArgumentException('PageContentErrorHandler can only handle TYPO3 urls of types "page" or "url"', 1522826609);
        }
        if ($urlParams['type'] === 'url') {
            return $urlParams['url'];
        }

        $this->pageUid = (int)$urlParams['pageuid'];

        // Get the site related to the configured error page
        $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($this->pageUid);
        // Fall back to current request for the site
        if (!$site instanceof Site) {
            $site = $request->getAttribute('site', null);
        }
        /** @var SiteLanguage $requestLanguage */
        $requestLanguage = $request->getAttribute('language', null);
        // Try to get the current request language from the site that was found above
        if ($requestLanguage instanceof SiteLanguage && $requestLanguage->isEnabled()) {
            try {
                $language = $site->getLanguageById($requestLanguage->getLanguageId());
            } catch (\InvalidArgumentException $e) {
                $language = $site->getDefaultLanguage();
            }
        } else {
            $language = $site->getDefaultLanguage();
        }

        // Build Url
        $uri = $site->getRouter()->generateUri(
            (int)$urlParams['pageuid'],
            ['_language' => $language]
        );

        // Fallback to the current URL if the site is not having a proper scheme and host
        $currentUri = $request->getUri();
        if (empty($uri->getScheme())) {
            $uri = $uri->withScheme($currentUri->getScheme());
        }
        if (empty($uri->getUserInfo())) {
            $uri = $uri->withUserInfo($currentUri->getUserInfo());
        }
        if (empty($uri->getHost())) {
            $uri = $uri->withHost($currentUri->getHost());
        }
        if ($uri->getPort() === null) {
            $uri = $uri->withPort($currentUri->getPort());
        }

        return (string)$uri;
    }
}
