# Page Redirect Handler

## Installation

This extension has to apply a patch - using `cweagans/composer-patches` - to the typo3 core to work correct.

Read (https://github.com/cweagans/composer-patches#user-content-allowing-patches-to-be-applied-from-dependencies) to allow
patches to be applied from dependencies.

After running `composer require mbhsoft/page-redirect-handler` the patching will fail, because the package will
be installed after `typo3/cms-frontend`.

To fix this run `composer install`.

## Usage

```yml
errorHandling:
  -
    errorCode: 401
    errorHandler: PHP
    errorPhpClassFQCN: MbhSoftware\PageRedirectHander\Error\PageErrorHandler\PageRedirectErrorHandler
    errorRedirectTarget: 't3://page?uid=42'
    errorRedirectTargetStatuscode: 307 #optional - 307 is default
    errorRedirectAdditionalParameters: #optional
      return_url: '###CURRENT_URL###'
